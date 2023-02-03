/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "zbxdbwrap.h"

#include "zbxdbhigh.h"
#include "log.h"
#include "zbxsysinfo.h"
#include "zbxserver.h"
#include "zbxtasks.h"
#include "zbxdiscovery.h"
#include "zbxalgo.h"
#include "preproc.h"
#include "zbxcrypto.h"
#include "zbxlld.h"
#include "events.h"
#include "zbxavailability.h"
#include "zbxcommshigh.h"
#include "zbxnum.h"
#include "zbxtime.h"
#include "zbxip.h"
#include "version.h"
#include "zbxversion.h"
#include "zbx_host_constants.h"
#include "zbx_item_constants.h"
#include "zbxcachehistory.h"

extern char	*CONFIG_SERVER;

/* the space reserved in json buffer to hold at least one record plus service data */
#define ZBX_DATA_JSON_RESERVED		(ZBX_HISTORY_TEXT_VALUE_LEN * 4 + ZBX_KIBIBYTE * 4)

#define ZBX_DATA_JSON_RECORD_LIMIT	(ZBX_MAX_RECV_DATA_SIZE - ZBX_DATA_JSON_RESERVED)
#define ZBX_DATA_JSON_BATCH_LIMIT	((ZBX_MAX_RECV_DATA_SIZE - ZBX_DATA_JSON_RESERVED) / 2)

/* the maximum number of values processed in one batch */
#define ZBX_HISTORY_VALUES_MAX		256

typedef struct
{
	zbx_uint64_t		druleid;
	zbx_vector_uint64_t	dcheckids;
	zbx_vector_ptr_t	ips;
}
zbx_drule_t;

typedef struct
{
	char			ip[ZBX_INTERFACE_IP_LEN_MAX];
	zbx_vector_ptr_t	services;
}
zbx_drule_ip_t;

typedef struct
{
	const char		*field;
	const char		*tag;
	zbx_json_type_t		jt;
	const char		*default_value;
}
zbx_history_field_t;

typedef struct
{
	const char		*table, *lastidfield;
	zbx_history_field_t	fields[ZBX_MAX_FIELDS];
}
zbx_history_table_t;

typedef int	(*zbx_client_item_validator_t)(zbx_history_recv_item_t *item, zbx_socket_t *sock, void *args,
		char **error);

typedef struct
{
	zbx_uint64_t	hostid;
	int		value;
}
zbx_host_rights_t;

static zbx_history_table_t	dht = {
	"proxy_dhistory", "dhistory_lastid",
		{
		{"clock",		ZBX_PROTO_TAG_CLOCK,		ZBX_JSON_TYPE_INT,	NULL},
		{"druleid",		ZBX_PROTO_TAG_DRULE,		ZBX_JSON_TYPE_INT,	NULL},
		{"dcheckid",		ZBX_PROTO_TAG_DCHECK,		ZBX_JSON_TYPE_INT,	NULL},
		{"ip",			ZBX_PROTO_TAG_IP,		ZBX_JSON_TYPE_STRING,	NULL},
		{"dns",			ZBX_PROTO_TAG_DNS,		ZBX_JSON_TYPE_STRING,	NULL},
		{"port",		ZBX_PROTO_TAG_PORT,		ZBX_JSON_TYPE_INT,	"0"},
		{"value",		ZBX_PROTO_TAG_VALUE,		ZBX_JSON_TYPE_STRING,	""},
		{"status",		ZBX_PROTO_TAG_STATUS,		ZBX_JSON_TYPE_INT,	"0"},
		{NULL}
		}
};

static zbx_history_table_t	areg = {
	"proxy_autoreg_host", "autoreg_host_lastid",
		{
		{"clock",		ZBX_PROTO_TAG_CLOCK,		ZBX_JSON_TYPE_INT,	NULL},
		{"host",		ZBX_PROTO_TAG_HOST,		ZBX_JSON_TYPE_STRING,	NULL},
		{"listen_ip",		ZBX_PROTO_TAG_IP,		ZBX_JSON_TYPE_STRING,	""},
		{"listen_dns",		ZBX_PROTO_TAG_DNS,		ZBX_JSON_TYPE_STRING,	""},
		{"listen_port",		ZBX_PROTO_TAG_PORT,		ZBX_JSON_TYPE_STRING,	"0"},
		{"host_metadata",	ZBX_PROTO_TAG_HOST_METADATA,	ZBX_JSON_TYPE_STRING,	""},
		{"flags",		ZBX_PROTO_TAG_FLAGS,		ZBX_JSON_TYPE_STRING,	"0"},
		{"tls_accepted",	ZBX_PROTO_TAG_TLS_ACCEPTED,	ZBX_JSON_TYPE_INT,	"0"},
		{NULL}
		}
};

/******************************************************************************
 *                                                                            *
 * Purpose: check proxy connection permissions (encryption configuration and  *
 *          if peer proxy address is allowed)                                 *
 *                                                                            *
 * Parameters:                                                                *
 *     proxy   - [IN] the proxy data                                          *
 *     sock    - [IN] connection socket context                               *
 *     error   - [OUT] error message                                          *
 *                                                                            *
 * Return value:                                                              *
 *     SUCCEED - connection permission check was successful                   *
 *     FAIL    - otherwise                                                    *
 *                                                                            *
 ******************************************************************************/
int	zbx_proxy_check_permissions(const DC_PROXY *proxy, const zbx_socket_t *sock, char **error)
{
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	zbx_tls_conn_attr_t	attr;
#endif
	if ('\0' != *proxy->proxy_address && FAIL == zbx_tcp_check_allowed_peers(sock, proxy->proxy_address))
	{
		*error = zbx_strdup(*error, "connection is not allowed");
		return FAIL;
	}

#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	if (ZBX_TCP_SEC_TLS_CERT == sock->connection_type)
	{
		if (SUCCEED != zbx_tls_get_attr_cert(sock, &attr))
		{
			*error = zbx_strdup(*error, "internal error: cannot get connection attributes");
			THIS_SHOULD_NEVER_HAPPEN;
			return FAIL;
		}
	}
#if defined(HAVE_GNUTLS) || (defined(HAVE_OPENSSL) && defined(HAVE_OPENSSL_WITH_PSK))
	else if (ZBX_TCP_SEC_TLS_PSK == sock->connection_type)
	{
		if (SUCCEED != zbx_tls_get_attr_psk(sock, &attr))
		{
			*error = zbx_strdup(*error, "internal error: cannot get connection attributes");
			THIS_SHOULD_NEVER_HAPPEN;
			return FAIL;
		}
	}
#endif
	else if (ZBX_TCP_SEC_UNENCRYPTED != sock->connection_type)
	{
		*error = zbx_strdup(*error, "internal error: invalid connection type");
		THIS_SHOULD_NEVER_HAPPEN;
		return FAIL;
	}
#endif
	if (0 == ((unsigned int)proxy->tls_accept & sock->connection_type))
	{
		*error = zbx_dsprintf(NULL, "connection of type \"%s\" is not allowed for proxy \"%s\"",
				zbx_tcp_connection_type_name(sock->connection_type), proxy->host);
		return FAIL;
	}

#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	if (ZBX_TCP_SEC_TLS_CERT == sock->connection_type)
	{
		/* simplified match, not compliant with RFC 4517, 4518 */
		if ('\0' != *proxy->tls_issuer && 0 != strcmp(proxy->tls_issuer, attr.issuer))
		{
			*error = zbx_dsprintf(*error, "proxy \"%s\" certificate issuer does not match", proxy->host);
			return FAIL;
		}

		/* simplified match, not compliant with RFC 4517, 4518 */
		if ('\0' != *proxy->tls_subject && 0 != strcmp(proxy->tls_subject, attr.subject))
		{
			*error = zbx_dsprintf(*error, "proxy \"%s\" certificate subject does not match", proxy->host);
			return FAIL;
		}
	}
#if defined(HAVE_GNUTLS) || (defined(HAVE_OPENSSL) && defined(HAVE_OPENSSL_WITH_PSK))
	else if (ZBX_TCP_SEC_TLS_PSK == sock->connection_type)
	{
		if (strlen(proxy->tls_psk_identity) != attr.psk_identity_len ||
				0 != memcmp(proxy->tls_psk_identity, attr.psk_identity, attr.psk_identity_len))
		{
			*error = zbx_dsprintf(*error, "proxy \"%s\" is using false PSK identity", proxy->host);
			return FAIL;
		}
	}
#endif
#endif
	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: checks host connection permissions (encryption configuration)     *
 *                                                                            *
 * Parameters:                                                                *
 *     host  - [IN] the host data                                             *
 *     sock  - [IN] connection socket context                                 *
 *     error - [OUT] error message                                            *
 *                                                                            *
 * Return value:                                                              *
 *     SUCCEED - connection permission check was successful                   *
 *     FAIL    - otherwise                                                    *
 *                                                                            *
 ******************************************************************************/
static int	zbx_host_check_permissions(const zbx_history_recv_host_t *host, const zbx_socket_t *sock, char **error)
{
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	zbx_tls_conn_attr_t	attr;

	if (ZBX_TCP_SEC_TLS_CERT == sock->connection_type)
	{
		if (SUCCEED != zbx_tls_get_attr_cert(sock, &attr))
		{
			*error = zbx_strdup(*error, "internal error: cannot get connection attributes");
			THIS_SHOULD_NEVER_HAPPEN;
			return FAIL;
		}
	}
#if defined(HAVE_GNUTLS) || (defined(HAVE_OPENSSL) && defined(HAVE_OPENSSL_WITH_PSK))
	else if (ZBX_TCP_SEC_TLS_PSK == sock->connection_type)
	{
		if (SUCCEED != zbx_tls_get_attr_psk(sock, &attr))
		{
			*error = zbx_strdup(*error, "internal error: cannot get connection attributes");
			THIS_SHOULD_NEVER_HAPPEN;
			return FAIL;
		}
	}
#endif
	else if (ZBX_TCP_SEC_UNENCRYPTED != sock->connection_type)
	{
		*error = zbx_strdup(*error, "internal error: invalid connection type");
		THIS_SHOULD_NEVER_HAPPEN;
		return FAIL;
	}
#endif
	if (0 == ((unsigned int)host->tls_accept & sock->connection_type))
	{
		*error = zbx_dsprintf(NULL, "connection of type \"%s\" is not allowed for host \"%s\"",
				zbx_tcp_connection_type_name(sock->connection_type), host->host);
		return FAIL;
	}

#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	if (ZBX_TCP_SEC_TLS_CERT == sock->connection_type)
	{
		/* simplified match, not compliant with RFC 4517, 4518 */
		if ('\0' != *host->tls_issuer && 0 != strcmp(host->tls_issuer, attr.issuer))
		{
			*error = zbx_dsprintf(*error, "host \"%s\" certificate issuer does not match", host->host);
			return FAIL;
		}

		/* simplified match, not compliant with RFC 4517, 4518 */
		if ('\0' != *host->tls_subject && 0 != strcmp(host->tls_subject, attr.subject))
		{
			*error = zbx_dsprintf(*error, "host \"%s\" certificate subject does not match", host->host);
			return FAIL;
		}
	}
#if defined(HAVE_GNUTLS) || (defined(HAVE_OPENSSL) && defined(HAVE_OPENSSL_WITH_PSK))
	else if (ZBX_TCP_SEC_TLS_PSK == sock->connection_type)
	{
		if (strlen(host->tls_psk_identity) != attr.psk_identity_len ||
				0 != memcmp(host->tls_psk_identity, attr.psk_identity, attr.psk_identity_len))
		{
			*error = zbx_dsprintf(*error, "host \"%s\" is using false PSK identity", host->host);
			return FAIL;
		}
	}
#endif
#endif
	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose:                                                                   *
 *     Extract a proxy name from JSON and find the proxy ID in configuration  *
 *     cache, and check access rights. The proxy must be configured in active *
 *     mode.                                                                  *
 *                                                                            *
 * Parameters:                                                                *
 *     jp      - [IN] JSON with the proxy name                                *
 *     proxy   - [OUT] the proxy data                                         *
 *     error   - [OUT] error message                                          *
 *                                                                            *
 * Return value:                                                              *
 *     SUCCEED - proxy ID was found in database                               *
 *     FAIL    - an error occurred (e.g. an unknown proxy, the proxy is       *
 *               configured in passive mode or access denied)                 *
 *                                                                            *
 ******************************************************************************/
int	zbx_get_active_proxy_from_request(const struct zbx_json_parse *jp, DC_PROXY *proxy, char **error)
{
	char	*ch_error, host[ZBX_HOSTNAME_BUF_LEN];

	if (SUCCEED != zbx_json_value_by_name(jp, ZBX_PROTO_TAG_HOST, host, sizeof(host), NULL))
	{
		*error = zbx_strdup(*error, "missing name of proxy");
		return FAIL;
	}

	if (SUCCEED != zbx_check_hostname(host, &ch_error))
	{
		*error = zbx_dsprintf(*error, "invalid proxy name \"%s\": %s", host, ch_error);
		zbx_free(ch_error);
		return FAIL;
	}

	return zbx_dc_get_active_proxy_by_name(host, proxy, error);
}

/******************************************************************************
 *                                                                            *
 * Purpose:                                                                   *
 *     Check access rights to a passive proxy for the given connection and    *
 *     send a response if denied.                                             *
 *                                                                            *
 * Parameters:                                                                *
 *     sock           - [IN] connection socket context                        *
 *     send_response  - [IN] to send or not to send a response to server.     *
 *                          Value: ZBX_SEND_RESPONSE or                       *
 *                          ZBX_DO_NOT_SEND_RESPONSE                          *
 *     req            - [IN] request, included into error message             *
 *     config_tls     - [IN] configured requirements to allow access          *
 *     config_timeout - [IN]                                                  *
 *                                                                            *
 * Return value:                                                              *
 *     SUCCEED - access is allowed                                            *
 *     FAIL    - access is denied                                             *
 *                                                                            *
 ******************************************************************************/
int	zbx_check_access_passive_proxy(zbx_socket_t *sock, int send_response, const char *req,
		const zbx_config_tls_t *config_tls, int config_timeout)
{
	char	*msg = NULL;

	if (FAIL == zbx_tcp_check_allowed_peers(sock, CONFIG_SERVER))
	{
		zabbix_log(LOG_LEVEL_WARNING, "%s from server \"%s\" is not allowed: %s", req, sock->peer,
				zbx_socket_strerror());

		if (ZBX_SEND_RESPONSE == send_response)
			zbx_send_proxy_response(sock, FAIL, "connection is not allowed", config_timeout);

		return FAIL;
	}

	if (0 == (config_tls->accept_modes & sock->connection_type))
	{
		msg = zbx_dsprintf(NULL, "%s over connection of type \"%s\" is not allowed", req,
				zbx_tcp_connection_type_name(sock->connection_type));

		zabbix_log(LOG_LEVEL_WARNING, "%s from server \"%s\" by proxy configuration parameter \"TLSAccept\"",
				msg, sock->peer);

		if (ZBX_SEND_RESPONSE == send_response)
			zbx_send_proxy_response(sock, FAIL, msg, config_timeout);

		zbx_free(msg);
		return FAIL;
	}

#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	if (ZBX_TCP_SEC_TLS_CERT == sock->connection_type)
	{
		if (SUCCEED == zbx_check_server_issuer_subject(sock, config_tls->server_cert_issuer,
				config_tls->server_cert_subject, &msg))
		{
			return SUCCEED;
		}

		zabbix_log(LOG_LEVEL_WARNING, "%s from server \"%s\" is not allowed: %s", req, sock->peer, msg);

		if (ZBX_SEND_RESPONSE == send_response)
			zbx_send_proxy_response(sock, FAIL, "certificate issuer or subject mismatch", config_timeout);

		zbx_free(msg);
		return FAIL;
	}
	else if (ZBX_TCP_SEC_TLS_PSK == sock->connection_type)
	{
		if (0 != (ZBX_PSK_FOR_PROXY & zbx_tls_get_psk_usage()))
			return SUCCEED;

		zabbix_log(LOG_LEVEL_WARNING, "%s from server \"%s\" is not allowed: it used PSK which is not"
				" configured for proxy communication with server", req, sock->peer);

		if (ZBX_SEND_RESPONSE == send_response)
			zbx_send_proxy_response(sock, FAIL, "wrong PSK used", config_timeout);

		return FAIL;
	}
#endif
	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Return value:  SUCCEED - processed successfully                            *
 *                FAIL - no interface availability has been changed           *
 *                                                                            *
 ******************************************************************************/
int	zbx_get_interface_availability_data(struct zbx_json *json, int *ts)
{
	int				i, ret = FAIL;
	zbx_vector_ptr_t		interfaces;
	zbx_interface_availability_t	*ia;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_ptr_create(&interfaces);

	if (SUCCEED != DCget_interfaces_availability(&interfaces, ts))
		goto out;

	zbx_json_addarray(json, ZBX_PROTO_TAG_INTERFACE_AVAILABILITY);

	for (i = 0; i < interfaces.values_num; i++)
	{
		ia = (zbx_interface_availability_t *)interfaces.values[i];

		zbx_json_addobject(json, NULL);
		zbx_json_adduint64(json, ZBX_PROTO_TAG_INTERFACE_ID, ia->interfaceid);

		zbx_json_adduint64(json, ZBX_PROTO_TAG_AVAILABLE, ia->agent.available);
		zbx_json_addstring(json, ZBX_PROTO_TAG_ERROR, ia->agent.error, ZBX_JSON_TYPE_STRING);

		zbx_json_close(json);
	}

	zbx_json_close(json);

	ret = SUCCEED;
out:
	zbx_vector_ptr_clear_ext(&interfaces, (zbx_mem_free_func_t)zbx_interface_availability_free);
	zbx_vector_ptr_destroy(&interfaces);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: parses interfaces availability data contents and processes it     *
 *                                                                            *
 * Return value:  SUCCEED - processed successfully                            *
 *                FAIL - an error occurred                                    *
 *                                                                            *
 ******************************************************************************/
static int	process_interfaces_availability_contents(struct zbx_json_parse *jp_data, char **error)
{
	zbx_uint64_t			interfaceid;
	struct zbx_json_parse		jp_row;
	const char			*p = NULL;
	char				*tmp;
	size_t				tmp_alloc = 129;
	zbx_interface_availability_t	*ia = NULL;
	zbx_vector_availability_ptr_t	interfaces;
	int				ret;

	tmp = (char *)zbx_malloc(NULL, tmp_alloc);

	zbx_vector_availability_ptr_create(&interfaces);

	while (NULL != (p = zbx_json_next(jp_data, p)))	/* iterate the interface entries */
	{
		if (SUCCEED != (ret = zbx_json_brackets_open(p, &jp_row)))
		{
			*error = zbx_strdup(*error, zbx_json_strerror());
			goto out;
		}

		if (SUCCEED != (ret = zbx_json_value_by_name_dyn(&jp_row, ZBX_PROTO_TAG_INTERFACE_ID, &tmp, &tmp_alloc,
				NULL)))
		{
			*error = zbx_strdup(*error, zbx_json_strerror());
			goto out;
		}

		if (SUCCEED != (ret = zbx_is_uint64(tmp, &interfaceid)))
		{
			*error = zbx_strdup(*error, "interfaceid is not a valid numeric");
			goto out;
		}

		ia = (zbx_interface_availability_t *)zbx_malloc(NULL, sizeof(zbx_interface_availability_t));
		zbx_interface_availability_init(ia, interfaceid);

		if (SUCCEED == zbx_json_value_by_name_dyn(&jp_row, ZBX_PROTO_TAG_AVAILABLE, &tmp, &tmp_alloc, NULL))
		{
			ia->agent.available = atoi(tmp);
			ia->agent.flags |= ZBX_FLAGS_AGENT_STATUS_AVAILABLE;
		}

		if (SUCCEED == zbx_json_value_by_name_dyn(&jp_row, ZBX_PROTO_TAG_ERROR, &tmp, &tmp_alloc, NULL))
		{
			ia->agent.error = zbx_strdup(NULL, tmp);
			ia->agent.flags |= ZBX_FLAGS_AGENT_STATUS_ERROR;
		}

		if (SUCCEED != (ret = zbx_interface_availability_is_set(ia)))
		{
			zbx_free(ia);
			*error = zbx_dsprintf(*error, "no availability data for \"interfaceid\":" ZBX_FS_UI64,
					interfaceid);
			goto out;
		}

		zbx_vector_availability_ptr_append(&interfaces, ia);
	}

	if (0 < interfaces.values_num && SUCCEED == DCset_interfaces_availability(&interfaces))
		zbx_availabilities_flush(&interfaces);

	ret = SUCCEED;
out:
	zbx_vector_availability_ptr_clear_ext(&interfaces, zbx_interface_availability_free);
	zbx_vector_availability_ptr_destroy(&interfaces);

	zbx_free(tmp);

	return ret;
}

static void	proxy_get_lastid(const char *table_name, const char *lastidfield, zbx_uint64_t *lastid)
{
	DB_RESULT	result;
	DB_ROW		row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() field:'%s.%s'", __func__, table_name, lastidfield);

	result = zbx_db_select("select nextid from ids where table_name='%s' and field_name='%s'",
			table_name, lastidfield);

	if (NULL == (row = zbx_db_fetch(result)))
		*lastid = 0;
	else
		ZBX_STR2UINT64(*lastid, row[0]);
	zbx_db_free_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():" ZBX_FS_UI64,	__func__, *lastid);
}

static void	proxy_set_lastid(const char *table_name, const char *lastidfield, const zbx_uint64_t lastid)
{
	DB_RESULT	result;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() [%s.%s:" ZBX_FS_UI64 "]", __func__, table_name, lastidfield, lastid);

	result = zbx_db_select("select 1 from ids where table_name='%s' and field_name='%s'",
			table_name, lastidfield);

	if (NULL == zbx_db_fetch(result))
	{
		zbx_db_execute("insert into ids (table_name,field_name,nextid) values ('%s','%s'," ZBX_FS_UI64 ")",
				table_name, lastidfield, lastid);
	}
	else
	{
		zbx_db_execute("update ids set nextid=" ZBX_FS_UI64 " where table_name='%s' and field_name='%s'",
				lastid, table_name, lastidfield);
	}
	zbx_db_free_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

void	zbx_proxy_set_hist_lastid(const zbx_uint64_t lastid)
{
	proxy_set_lastid("proxy_history", "history_lastid", lastid);
}

void	zbx_proxy_set_dhis_lastid(const zbx_uint64_t lastid)
{
	proxy_set_lastid(dht.table, dht.lastidfield, lastid);
}

void	zbx_proxy_set_areg_lastid(const zbx_uint64_t lastid)
{
	proxy_set_lastid(areg.table, areg.lastidfield, lastid);
}

int	zbx_proxy_get_delay(const zbx_uint64_t lastid)
{
	DB_RESULT	result;
	DB_ROW		row;
	char		*sql = NULL;
	int		ts = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() [lastid=" ZBX_FS_UI64 "]", __func__, lastid);

	sql = zbx_dsprintf(sql, "select write_clock from proxy_history where id>" ZBX_FS_UI64 " order by id asc",
			lastid);

	result = zbx_db_select_n(sql, 1);
	zbx_free(sql);

	if (NULL != (row = zbx_db_fetch(result)))
		ts = (int)time(NULL) - atoi(row[0]);

	zbx_db_free_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return ts;
}

/******************************************************************************
 *                                                                            *
 * Purpose: Get history data from the database.                               *
 *                                                                            *
 ******************************************************************************/
static void	proxy_get_history_data_simple(struct zbx_json *j, const char *proto_tag, const zbx_history_table_t *ht,
		zbx_uint64_t *lastid, zbx_uint64_t *id, int *records_num, int *more)
{
	size_t		offset = 0;
	int		f, records_num_last = *records_num, retries = 1;
	char		sql[MAX_STRING_LEN];
	DB_RESULT	result;
	DB_ROW		row;
	struct timespec	t_sleep = { 0, 100000000L }, t_rem;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() table:'%s'", __func__, ht->table);

	*more = ZBX_PROXY_DATA_DONE;

	offset += zbx_snprintf(sql + offset, sizeof(sql) - offset, "select id");

	for (f = 0; NULL != ht->fields[f].field; f++)
		offset += zbx_snprintf(sql + offset, sizeof(sql) - offset, ",%s", ht->fields[f].field);
try_again:
	zbx_snprintf(sql + offset, sizeof(sql) - offset, " from %s where id>" ZBX_FS_UI64 " order by id",
			ht->table, *id);

	result = zbx_db_select_n(sql, ZBX_MAX_HRECORDS);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		ZBX_STR2UINT64(*lastid, row[0]);

		if (1 < *lastid - *id)
		{
			/* At least one record is missing. It can happen if some DB syncer process has */
			/* started but not yet committed a transaction or a rollback occurred in a DB syncer. */
			if (0 < retries--)
			{
				zbx_db_free_result(result);
				zabbix_log(LOG_LEVEL_DEBUG, "%s() " ZBX_FS_UI64 " record(s) missing."
						" Waiting " ZBX_FS_DBL " sec, retrying.",
						__func__, *lastid - *id - 1,
						t_sleep.tv_sec + t_sleep.tv_nsec / 1e9);
				nanosleep(&t_sleep, &t_rem);
				goto try_again;
			}
			else
			{
				zabbix_log(LOG_LEVEL_DEBUG, "%s() " ZBX_FS_UI64 " record(s) missing. No more retries.",
						__func__, *lastid - *id - 1);
			}
		}

		if (0 == *records_num)
			zbx_json_addarray(j, proto_tag);

		zbx_json_addobject(j, NULL);

		for (f = 0; NULL != ht->fields[f].field; f++)
		{
			if (NULL != ht->fields[f].default_value && 0 == strcmp(row[f + 1], ht->fields[f].default_value))
				continue;

			zbx_json_addstring(j, ht->fields[f].tag, row[f + 1], ht->fields[f].jt);
		}

		(*records_num)++;

		zbx_json_close(j);

		/* stop gathering data to avoid exceeding the maximum packet size */
		if (ZBX_DATA_JSON_RECORD_LIMIT < j->buffer_offset)
		{
			*more = ZBX_PROXY_DATA_MORE;
			break;
		}

		*id = *lastid;
	}
	zbx_db_free_result(result);

	if (ZBX_MAX_HRECORDS == *records_num - records_num_last)
		*more = ZBX_PROXY_DATA_MORE;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d lastid:" ZBX_FS_UI64 " more:%d size:" ZBX_FS_SIZE_T,
			__func__, *records_num - records_num_last, *lastid, *more,
			(zbx_fs_size_t)j->buffer_offset);
}

typedef struct
{
	zbx_uint64_t	id;
	zbx_uint64_t	itemid;
	zbx_uint64_t	lastlogsize;
	size_t		source_offset;
	size_t		value_offset;
	int		clock;
	int		ns;
	int		timestamp;
	int		severity;
	int		logeventid;
	int		mtime;
	unsigned char	state;
	unsigned char	flags;
}
zbx_history_data_t;

/******************************************************************************
 *                                                                            *
 * Purpose: read proxy history data from the database                         *
 *                                                                            *
 * Parameters: lastid             - [IN] the id of last processed proxy       *
 *                                       history record                       *
 *             data               - [IN/OUT] the proxy history data buffer    *
 *             data_alloc         - [IN/OUT] the size of proxy history data   *
 *                                           buffer                           *
 *             string_buffer      - [IN/OUT] the string buffer                *
 *             string_buffer_size - [IN/OUT] the size of string buffer        *
 *             more               - [OUT] set to ZBX_PROXY_DATA_MORE if there *
 *                                        might be more data to read          *
 *                                                                            *
 * Return value: The number of records read.                                  *
 *                                                                            *
 ******************************************************************************/
static int	proxy_get_history_data(zbx_uint64_t lastid, zbx_history_data_t **data, size_t *data_alloc,
		char **string_buffer, size_t *string_buffer_alloc, int *more)
{
	DB_RESULT		result;
	DB_ROW			row;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0, data_num = 0;
	size_t			string_buffer_offset = 0;
	zbx_uint64_t		id;
	int			retries = 1, total_retries = 10;
	struct timespec		t_sleep = { 0, 100000000L }, t_rem;
	zbx_history_data_t	*hd;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() lastid:" ZBX_FS_UI64, __func__, lastid);

try_again:
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select id,itemid,clock,ns,timestamp,source,severity,"
				"value,logeventid,state,lastlogsize,mtime,flags"
			" from proxy_history"
			" where id>" ZBX_FS_UI64
			" order by id",
			lastid);

	result = zbx_db_select_n(sql, ZBX_MAX_HRECORDS - data_num);

	zbx_free(sql);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		ZBX_STR2UINT64(id, row[0]);

		if (1 < id - lastid)
		{
			/* At least one record is missing. It can happen if some DB syncer process has */
			/* started but not yet committed a transaction or a rollback occurred in a DB syncer. */
			if (0 < retries--)
			{
				/* limit the number of total retries to avoid being stuck */
				/* in history full of 'holes' for a long time             */
				if (0 >= total_retries--)
					break;

				zbx_db_free_result(result);
				zabbix_log(LOG_LEVEL_DEBUG, "%s() " ZBX_FS_UI64 " record(s) missing."
						" Waiting " ZBX_FS_DBL " sec, retrying.",
						__func__, id - lastid - 1,
						t_sleep.tv_sec + t_sleep.tv_nsec / 1e9);
				nanosleep(&t_sleep, &t_rem);
				goto try_again;
			}
			else
			{
				zabbix_log(LOG_LEVEL_DEBUG, "%s() " ZBX_FS_UI64 " record(s) missing. No more retries.",
						__func__, id - lastid - 1);
			}
		}

		retries = 1;

		if (*data_alloc == data_num)
		{
			*data_alloc *= 2;
			*data = (zbx_history_data_t *)zbx_realloc(*data, sizeof(zbx_history_data_t) * *data_alloc);
		}

		hd = *data + data_num++;
		hd->id = id;
		ZBX_STR2UINT64(hd->itemid, row[1]);
		ZBX_STR2UCHAR(hd->flags, row[12]);
		hd->clock = atoi(row[2]);
		hd->ns = atoi(row[3]);

		if (PROXY_HISTORY_FLAG_NOVALUE != (hd->flags & PROXY_HISTORY_MASK_NOVALUE))
		{
			ZBX_STR2UCHAR(hd->state, row[9]);

			if (0 == (hd->flags & PROXY_HISTORY_FLAG_NOVALUE))
			{
				size_t	len1, len2;

				hd->timestamp = atoi(row[4]);
				hd->severity = atoi(row[6]);
				hd->logeventid = atoi(row[8]);

				len1 = strlen(row[5]) + 1;
				len2 = strlen(row[7]) + 1;

				if (*string_buffer_alloc < string_buffer_offset + len1 + len2)
				{
					while (*string_buffer_alloc < string_buffer_offset + len1 + len2)
						*string_buffer_alloc += ZBX_KIBIBYTE;

					*string_buffer = (char *)zbx_realloc(*string_buffer, *string_buffer_alloc);
				}

				hd->source_offset = string_buffer_offset;
				memcpy(*string_buffer + hd->source_offset, row[5], len1);
				string_buffer_offset += len1;

				hd->value_offset = string_buffer_offset;
				memcpy(*string_buffer + hd->value_offset, row[7], len2);
				string_buffer_offset += len2;
			}

			if (0 != (hd->flags & PROXY_HISTORY_FLAG_META))
			{
				ZBX_STR2UINT64(hd->lastlogsize, row[10]);
				hd->mtime = atoi(row[11]);
			}
		}

		lastid = id;
	}
	zbx_db_free_result(result);

	if (ZBX_MAX_HRECORDS != data_num && 1 == retries)
		*more = ZBX_PROXY_DATA_DONE;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() data_num:" ZBX_FS_SIZE_T, __func__, data_num);

	return data_num;
}

/******************************************************************************
 *                                                                            *
 * Purpose: add history records to output json                                *
 *                                                                            *
 * Parameters: j             - [IN] the json output buffer                    *
 *             records_num   - [IN] the total number of records added         *
 *             dc_items      - [IN] the item configuration data               *
 *             errcodes      - [IN] the item configuration status codes       *
 *             records       - [IN] the records to add                        *
 *             string_buffer - [IN] the string buffer holding string values   *
 *             lastid        - [OUT] the id of last added record              *
 *                                                                            *
 * Return value: The total number of records added.                           *
 *                                                                            *
 ******************************************************************************/
static int	proxy_add_hist_data(struct zbx_json *j, int records_num, const DC_ITEM *dc_items, const int *errcodes,
		const zbx_vector_ptr_t *records, const char *string_buffer, zbx_uint64_t *lastid)
{
	int				i;
	const zbx_history_data_t	*hd;

	for (i = records->values_num - 1; i >= 0; i--)
	{
		hd = (const zbx_history_data_t *)records->values[i];
		*lastid = hd->id;

		if (SUCCEED != errcodes[i])
			continue;

		if (ITEM_STATUS_ACTIVE != dc_items[i].status)
			continue;

		if (HOST_STATUS_MONITORED != dc_items[i].host.status)
			continue;

		if (PROXY_HISTORY_FLAG_NOVALUE == (hd->flags & PROXY_HISTORY_MASK_NOVALUE))
		{
			if (SUCCEED != zbx_is_counted_in_item_queue(dc_items[i].type, dc_items[i].key_orig))
				continue;
		}

		if (0 == records_num)
			zbx_json_addarray(j, ZBX_PROTO_TAG_HISTORY_DATA);

		zbx_json_addobject(j, NULL);
		zbx_json_adduint64(j, ZBX_PROTO_TAG_ID, hd->id);
		zbx_json_adduint64(j, ZBX_PROTO_TAG_ITEMID, hd->itemid);
		zbx_json_adduint64(j, ZBX_PROTO_TAG_CLOCK, hd->clock);
		zbx_json_adduint64(j, ZBX_PROTO_TAG_NS, hd->ns);

		if (PROXY_HISTORY_FLAG_NOVALUE != (hd->flags & PROXY_HISTORY_MASK_NOVALUE))
		{
			if (ITEM_STATE_NORMAL != hd->state)
				zbx_json_adduint64(j, ZBX_PROTO_TAG_STATE, hd->state);

			if (0 == (hd->flags & PROXY_HISTORY_FLAG_NOVALUE))
			{
				if (0 != hd->timestamp)
					zbx_json_adduint64(j, ZBX_PROTO_TAG_LOGTIMESTAMP, hd->timestamp);

				if ('\0' != string_buffer[hd->source_offset])
				{
					zbx_json_addstring(j, ZBX_PROTO_TAG_LOGSOURCE,
							string_buffer + hd->source_offset, ZBX_JSON_TYPE_STRING);
				}

				if (0 != hd->severity)
					zbx_json_adduint64(j, ZBX_PROTO_TAG_LOGSEVERITY, hd->severity);

				if (0 != hd->logeventid)
					zbx_json_adduint64(j, ZBX_PROTO_TAG_LOGEVENTID, hd->logeventid);

				zbx_json_addstring(j, ZBX_PROTO_TAG_VALUE, string_buffer + hd->value_offset,
						ZBX_JSON_TYPE_STRING);
			}

			if (0 != (hd->flags & PROXY_HISTORY_FLAG_META))
			{
				zbx_json_adduint64(j, ZBX_PROTO_TAG_LASTLOGSIZE, hd->lastlogsize);
				zbx_json_adduint64(j, ZBX_PROTO_TAG_MTIME, hd->mtime);
			}
		}

		zbx_json_close(j);
		records_num++;

		/* stop gathering data to avoid exceeding the maximum packet size */
		if (ZBX_DATA_JSON_RECORD_LIMIT < j->buffer_offset)
			break;
	}

	return records_num;
}

int	zbx_proxy_get_hist_data(struct zbx_json *j, zbx_uint64_t *lastid, int *more)
{
	int			records_num = 0, data_num, i, *errcodes = NULL, items_alloc = 0;
	zbx_uint64_t		id;
	zbx_hashset_t		itemids_added;
	zbx_history_data_t	*data;
	char			*string_buffer;
	size_t			data_alloc = 16, string_buffer_alloc = ZBX_KIBIBYTE;
	zbx_vector_uint64_t	itemids;
	zbx_vector_ptr_t	records;
	DC_ITEM			*dc_items = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&itemids);
	zbx_vector_ptr_create(&records);
	data = (zbx_history_data_t *)zbx_malloc(NULL, data_alloc * sizeof(zbx_history_data_t));
	string_buffer = (char *)zbx_malloc(NULL, string_buffer_alloc);

	*more = ZBX_PROXY_DATA_MORE;
	proxy_get_lastid("proxy_history", "history_lastid", &id);

	zbx_hashset_create(&itemids_added, data_alloc, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	/* get history data in batches by ZBX_MAX_HRECORDS records and stop if: */
	/*   1) there are no more data to read                                  */
	/*   2) we have retrieved more than the total maximum number of records */
	/*   3) we have gathered more than half of the maximum packet size      */
	while (ZBX_DATA_JSON_BATCH_LIMIT > j->buffer_offset && ZBX_MAX_HRECORDS_TOTAL > records_num &&
			0 != (data_num = proxy_get_history_data(id, &data, &data_alloc, &string_buffer,
					&string_buffer_alloc, more)))
	{
		zbx_vector_uint64_reserve(&itemids, data_num);
		zbx_vector_ptr_reserve(&records, data_num);

		/* filter out duplicate novalue updates */
		for (i = data_num - 1; i >= 0; i--)
		{
			if (PROXY_HISTORY_FLAG_NOVALUE == (data[i].flags & PROXY_HISTORY_MASK_NOVALUE))
			{
				if (NULL != zbx_hashset_search(&itemids_added, &data[i].itemid))
					continue;

				zbx_hashset_insert(&itemids_added, &data[i].itemid, sizeof(data[i].itemid));
			}

			zbx_vector_ptr_append(&records, &data[i]);
			zbx_vector_uint64_append(&itemids, data[i].itemid);
		}

		/* append history records to json */

		if (itemids.values_num > items_alloc)
		{
			items_alloc = itemids.values_num;
			dc_items = (DC_ITEM *)zbx_realloc(dc_items, items_alloc * sizeof(DC_ITEM));
			errcodes = (int *)zbx_realloc(errcodes, items_alloc * sizeof(int));
		}

		DCconfig_get_items_by_itemids(dc_items, itemids.values, errcodes, itemids.values_num);

		records_num = proxy_add_hist_data(j, records_num, dc_items, errcodes, &records, string_buffer, lastid);
		DCconfig_clean_items(dc_items, errcodes, itemids.values_num);

		/* got less data than requested - either no more data to read or the history is full of */
		/* holes. In this case send retrieved data before attempting to read/wait for more data */
		if (ZBX_MAX_HRECORDS > data_num)
			break;

		zbx_vector_uint64_clear(&itemids);
		zbx_vector_ptr_clear(&records);
		zbx_hashset_clear(&itemids_added);
		id = *lastid;
	}

	if (0 != records_num)
		zbx_json_close(j);

	zbx_hashset_destroy(&itemids_added);

	zbx_free(dc_items);
	zbx_free(errcodes);
	zbx_free(data);
	zbx_free(string_buffer);
	zbx_vector_ptr_destroy(&records);
	zbx_vector_uint64_destroy(&itemids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() lastid:" ZBX_FS_UI64 " records_num:%d size:~" ZBX_FS_SIZE_T " more:%d",
			__func__, *lastid, records_num, j->buffer_offset, *more);

	return records_num;
}

int	zbx_proxy_get_dhis_data(struct zbx_json *j, zbx_uint64_t *lastid, int *more)
{
	int		records_num = 0;
	zbx_uint64_t	id;

	proxy_get_lastid(dht.table, dht.lastidfield, &id);

	/* get history data in batches by ZBX_MAX_HRECORDS records and stop if: */
	/*   1) there are no more data to read                                  */
	/*   2) we have retrieved more than the total maximum number of records */
	/*   3) we have gathered more than half of the maximum packet size      */
	while (ZBX_DATA_JSON_BATCH_LIMIT > j->buffer_offset)
	{
		proxy_get_history_data_simple(j, ZBX_PROTO_TAG_DISCOVERY_DATA, &dht, lastid, &id, &records_num, more);

		if (ZBX_PROXY_DATA_DONE == *more || ZBX_MAX_HRECORDS_TOTAL <= records_num)
			break;
	}

	if (0 != records_num)
		zbx_json_close(j);

	return records_num;
}

int	zbx_proxy_get_areg_data(struct zbx_json *j, zbx_uint64_t *lastid, int *more)
{
	int		records_num = 0;
	zbx_uint64_t	id;

	proxy_get_lastid(areg.table, areg.lastidfield, &id);

	/* get history data in batches by ZBX_MAX_HRECORDS records and stop if: */
	/*   1) there are no more data to read                                  */
	/*   2) we have retrieved more than the total maximum number of records */
	/*   3) we have gathered more than half of the maximum packet size      */
	while (ZBX_DATA_JSON_BATCH_LIMIT > j->buffer_offset)
	{
		proxy_get_history_data_simple(j, ZBX_PROTO_TAG_AUTOREGISTRATION, &areg, lastid, &id, &records_num,
				more);

		if (ZBX_PROXY_DATA_DONE == *more || ZBX_MAX_HRECORDS_TOTAL <= records_num)
			break;
	}

	if (0 != records_num)
		zbx_json_close(j);

	return records_num;
}

int	zbx_proxy_get_host_active_availability(struct zbx_json *j)
{
	zbx_ipc_message_t	response;
	int			records_num = 0;

	zbx_ipc_message_init(&response);
	zbx_availability_send(ZBX_IPC_AVAILMAN_ACTIVE_HOSTDATA, 0, 0, &response);

	if (0 != response.size)
	{
		zbx_vector_proxy_hostdata_ptr_t	hostdata;

		zbx_vector_proxy_hostdata_ptr_create(&hostdata);
		zbx_availability_deserialize_hostdata(response.data, &hostdata);
		zbx_availability_serialize_json_hostdata(&hostdata, j);

		records_num = hostdata.values_num;

		zbx_vector_proxy_hostdata_ptr_clear_ext(&hostdata, (zbx_proxy_hostdata_ptr_free_func_t)zbx_ptr_free);
		zbx_vector_proxy_hostdata_ptr_destroy(&hostdata);
	}

	zbx_ipc_message_clean(&response);

	return records_num;
}

/******************************************************************************
 *                                                                            *
 * Purpose: processes item value depending on proxy/flags settings            *
 *                                                                            *
 * Parameters: item    - [IN] the item to process                             *
 *             result  - [IN] the item result                                 *
 *                                                                            *
 * Comments: Values gathered by server are sent to the preprocessing manager, *
 *           while values received from proxy are already preprocessed and    *
 *           must be either directly stored to history cache or sent to lld   *
 *           manager.                                                         *
 *                                                                            *
 ******************************************************************************/
static void	process_item_value(const zbx_history_recv_item_t *item, AGENT_RESULT *result, zbx_timespec_t *ts,
		int *h_num, char *error)
{
	if (0 == item->host.proxy_hostid)
	{
		zbx_preprocess_item_value(item->itemid, item->host.hostid, item->value_type, item->flags, result, ts,
				item->state, error);
		*h_num = 0;
	}
	else
	{
		if (0 != (ZBX_FLAG_DISCOVERY_RULE & item->flags))
		{
			zbx_lld_process_agent_result(item->itemid, item->host.hostid, result, ts, error);
			*h_num = 0;
		}
		else
		{
			dc_add_history(item->itemid, item->value_type, item->flags, result, ts, item->state, error);
			*h_num = 1;
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: process single value from incoming history data                   *
 *                                                                            *
 * Parameters: item    - [IN] the item to process                             *
 *             value   - [IN] the value to process                            *
 *             hval    - [OUT] indication that value was added to history     *
 *                                                                            *
 * Return value: SUCCEED - the value was processed successfully               *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	process_history_data_value(zbx_history_recv_item_t *item, zbx_agent_value_t *value, int *h_num)
{
	if (ITEM_STATUS_ACTIVE != item->status)
		return FAIL;

	if (HOST_STATUS_MONITORED != item->host.status)
		return FAIL;

	/* update item nextcheck during maintenance */
	if (SUCCEED == in_maintenance_without_data_collection(item->host.maintenance_status,
			item->host.maintenance_type, item->type) &&
			item->host.maintenance_from <= value->ts.sec)
	{
		return SUCCEED;
	}

	if (NULL == value->value && ITEM_STATE_NOTSUPPORTED == value->state)
	{
		THIS_SHOULD_NEVER_HAPPEN;
		return FAIL;
	}

	if (ITEM_STATE_NOTSUPPORTED == value->state ||
			(NULL != value->value && 0 == strcmp(value->value, ZBX_NOTSUPPORTED)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "hostid:" ZBX_FS_UI64 " item %s error: %s", item->host.hostid,
				item->key_orig, value->value);

		item->state = ITEM_STATE_NOTSUPPORTED;
		process_item_value(item, NULL, &value->ts, h_num, value->value);
	}
	else
	{
		AGENT_RESULT	result;

		zbx_init_agent_result(&result);

		if (NULL != value->value)
		{
			if (ITEM_VALUE_TYPE_LOG == item->value_type)
			{
				zbx_log_t	*log;

				log = (zbx_log_t *)zbx_malloc(NULL, sizeof(zbx_log_t));
				log->value = zbx_strdup(NULL, value->value);
				zbx_replace_invalid_utf8(log->value);

				if (0 == value->timestamp)
				{
					log->timestamp = 0;
					zbx_calc_timestamp(log->value, &log->timestamp, item->logtimefmt);
				}
				else
					log->timestamp = value->timestamp;

				log->logeventid = value->logeventid;
				log->severity = value->severity;

				if (NULL != value->source)
				{
					log->source = zbx_strdup(NULL, value->source);
					zbx_replace_invalid_utf8(log->source);
				}
				else
					log->source = NULL;

				SET_LOG_RESULT(&result, log);
			}
			else
				zbx_set_agent_result_type(&result, ITEM_VALUE_TYPE_TEXT, value->value);
		}

		if (0 != value->meta)
			zbx_set_agent_result_meta(&result, value->lastlogsize, value->mtime);

		if (0 != ZBX_ISSET_VALUE(&result) || 0 != ZBX_ISSET_META(&result))
		{
			item->state = ITEM_STATE_NORMAL;
			process_item_value(item, &result, &value->ts, h_num, NULL);
		}

		zbx_free_agent_result(&result);
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: process new item values                                           *
 *                                                                            *
 * Parameters: items    - [IN] the items to process                           *
 *             values   - [IN] the item values value to process               *
 *             errcodes - [IN/OUT] in - item configuration error code         *
 *                                      (FAIL - item/host was not found)      *
 *                                 out - value processing result              *
 *                                      (SUCCEED - processed, FAIL - error)   *
 *             values_num - [IN] the number of items/values to process        *
 *             nodata_win - [IN/OUT] proxy communication delay info           *
 *                                                                            *
 * Return value: the number of processed values                               *
 *                                                                            *
 ******************************************************************************/
int	zbx_process_history_data(zbx_history_recv_item_t *items, zbx_agent_value_t *values, int *errcodes,
		size_t values_num, zbx_proxy_suppress_t *nodata_win)
{
	size_t	i;
	int	processed_num = 0, history_num;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	for (i = 0; i < values_num; i++)
	{
		if (SUCCEED != errcodes[i])
			continue;

		history_num = 0;

		if (SUCCEED != process_history_data_value(&items[i], &values[i], &history_num))
		{
			/* clean failed items to avoid updating their runtime data */
			errcodes[i] = FAIL;
			continue;
		}

		if (0 != items[i].host.proxy_hostid && NULL != nodata_win &&
				0 != (nodata_win->flags & ZBX_PROXY_SUPPRESS_ACTIVE) && 0 < history_num)
		{
			if (values[i].ts.sec <= nodata_win->period_end)
			{
				nodata_win->values_num++;
			}
			else
			{
				nodata_win->flags &= (~ZBX_PROXY_SUPPRESS_MORE);
			}

			zabbix_log(LOG_LEVEL_TRACE, "%s() flags:%d values_num:%d value_time:%d period_end:%d",
					__func__, nodata_win->flags, nodata_win->values_num, values[i].ts.sec,
					nodata_win->period_end);
		}

		processed_num++;
	}

	if (0 < processed_num)
		zbx_dc_items_update_nextcheck(items, values, errcodes, values_num);

	zbx_preprocessor_flush();
	dc_flush_history();

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() processed:%d", __func__, processed_num);

	return processed_num;
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees resources allocated to store agent values                   *
 *                                                                            *
 * Parameters: values     - [IN] the values to clean                          *
 *             values_num - [IN] the number of items in values array          *
 *                                                                            *
 ******************************************************************************/
static void	zbx_agent_values_clean(zbx_agent_value_t *values, size_t values_num)
{
	size_t	i;

	for (i = 0; i < values_num; i++)
	{
		zbx_free(values[i].value);
		zbx_free(values[i].source);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: calculates difference between server and client (proxy, active    *
 *          agent or sender) time and log it                                  *
 *                                                                            *
 * Parameters: level   - [IN] log level                                       *
 *             jp      - [IN] JSON with clock, [ns] fields                    *
 *             ts_recv - [IN] the connection timestamp                        *
 *                                                                            *
 ******************************************************************************/
static void	log_client_timediff(int level, struct zbx_json_parse *jp, const zbx_timespec_t *ts_recv)
{
	char		tmp[32];
	zbx_timespec_t	client_timediff;
	int		sec, ns;

	if (SUCCEED != ZBX_CHECK_LOG_LEVEL(level))
		return;

	if (SUCCEED == zbx_json_value_by_name(jp, ZBX_PROTO_TAG_CLOCK, tmp, sizeof(tmp), NULL))
	{
		sec = atoi(tmp);
		client_timediff.sec = ts_recv->sec - sec;

		if (SUCCEED == zbx_json_value_by_name(jp, ZBX_PROTO_TAG_NS, tmp, sizeof(tmp), NULL))
		{
			ns = atoi(tmp);
			client_timediff.ns = ts_recv->ns - ns;

			if (client_timediff.sec > 0 && client_timediff.ns < 0)
			{
				client_timediff.sec--;
				client_timediff.ns += 1000000000;
			}
			else if (client_timediff.sec < 0 && client_timediff.ns > 0)
			{
				client_timediff.sec++;
				client_timediff.ns -= 1000000000;
			}

			zabbix_log(level, "%s(): timestamp from json %d seconds and %d nanosecond, "
					"delta time from json %d seconds and %d nanosecond",
					__func__, sec, ns, client_timediff.sec, client_timediff.ns);
		}
		else
		{
			zabbix_log(level, "%s(): timestamp from json %d seconds, "
				"delta time from json %d seconds", __func__, sec, client_timediff.sec);
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: parses agent value from history data json row                     *
 *                                                                            *
 * Parameters: jp_row       - [IN] JSON with history data row                 *
 *             unique_shift - [IN/OUT] auto increment nanoseconds to ensure   *
 *                                     unique value of timestamps             *
 *             av           - [OUT] the agent value                           *
 *                                                                            *
 * Return value:  SUCCEED - the value was parsed successfully                 *
 *                FAIL    - otherwise                                         *
 *                                                                            *
 ******************************************************************************/
static int	parse_history_data_row_value(const struct zbx_json_parse *jp_row, zbx_timespec_t *unique_shift,
		zbx_agent_value_t *av)
{
	char	*tmp = NULL;
	size_t	tmp_alloc = 0;
	int	ret = FAIL;

	memset(av, 0, sizeof(zbx_agent_value_t));

	if (SUCCEED == zbx_json_value_by_name_dyn(jp_row, ZBX_PROTO_TAG_CLOCK, &tmp, &tmp_alloc, NULL))
	{
		if (FAIL == zbx_is_uint31(tmp, &av->ts.sec))
			goto out;

		if (SUCCEED == zbx_json_value_by_name_dyn(jp_row, ZBX_PROTO_TAG_NS, &tmp, &tmp_alloc, NULL))
		{
			if (FAIL == zbx_is_uint_n_range(tmp, tmp_alloc, &av->ts.ns, sizeof(av->ts.ns),
				0LL, 999999999LL))
			{
				goto out;
			}
		}
		else
		{
			/* ensure unique value timestamp (clock, ns) if only clock is available */

			av->ts.sec += unique_shift->sec;
			av->ts.ns = unique_shift->ns++;

			if (unique_shift->ns > 999999999)
			{
				unique_shift->sec++;
				unique_shift->ns = 0;
			}
		}
	}
	else
		zbx_timespec(&av->ts);

	if (SUCCEED == zbx_json_value_by_name_dyn(jp_row, ZBX_PROTO_TAG_STATE, &tmp, &tmp_alloc, NULL))
		av->state = (unsigned char)atoi(tmp);

	/* Unsupported item meta information must be ignored for backwards compatibility. */
	/* New agents will not send meta information for items in unsupported state.      */
	if (ITEM_STATE_NOTSUPPORTED != av->state)
	{
		if (SUCCEED == zbx_json_value_by_name_dyn(jp_row, ZBX_PROTO_TAG_LASTLOGSIZE, &tmp, &tmp_alloc, NULL))
		{
			av->meta = 1;	/* contains meta information */

			zbx_is_uint64(tmp, &av->lastlogsize);

			if (SUCCEED == zbx_json_value_by_name_dyn(jp_row, ZBX_PROTO_TAG_MTIME, &tmp, &tmp_alloc, NULL))
				av->mtime = atoi(tmp);
		}
	}

	if (SUCCEED == zbx_json_value_by_name_dyn(jp_row, ZBX_PROTO_TAG_VALUE, &tmp, &tmp_alloc, NULL))
		av->value = zbx_strdup(av->value, tmp);

	if (SUCCEED == zbx_json_value_by_name_dyn(jp_row, ZBX_PROTO_TAG_LOGTIMESTAMP, &tmp, &tmp_alloc, NULL))
		av->timestamp = atoi(tmp);

	if (SUCCEED == zbx_json_value_by_name_dyn(jp_row, ZBX_PROTO_TAG_LOGSOURCE, &tmp, &tmp_alloc, NULL))
		av->source = zbx_strdup(av->source, tmp);

	if (SUCCEED == zbx_json_value_by_name_dyn(jp_row, ZBX_PROTO_TAG_LOGSEVERITY, &tmp, &tmp_alloc, NULL))
		av->severity = atoi(tmp);

	if (SUCCEED == zbx_json_value_by_name_dyn(jp_row, ZBX_PROTO_TAG_LOGEVENTID, &tmp, &tmp_alloc, NULL))
		av->logeventid = atoi(tmp);

	if (SUCCEED != zbx_json_value_by_name_dyn(jp_row, ZBX_PROTO_TAG_ID, &tmp, &tmp_alloc, NULL) ||
			SUCCEED != zbx_is_uint64(tmp, &av->id))
	{
		av->id = 0;
	}

	zbx_free(tmp);

	ret = SUCCEED;
out:
	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: parses item identifier from history data json row                 *
 *                                                                            *
 * Parameters: jp_row - [IN] JSON with history data row                       *
 *             itemid - [OUT] the item identifier                             *
 *                                                                            *
 * Return value:  SUCCEED - the item identifier was parsed successfully       *
 *                FAIL    - otherwise                                         *
 *                                                                            *
 ******************************************************************************/
static int	parse_history_data_row_itemid(const struct zbx_json_parse *jp_row, zbx_uint64_t *itemid)
{
	char	buffer[MAX_ID_LEN + 1];

	if (SUCCEED != zbx_json_value_by_name(jp_row, ZBX_PROTO_TAG_ITEMID, buffer, sizeof(buffer), NULL))
		return FAIL;

	if (SUCCEED != zbx_is_uint64(buffer, itemid))
		return FAIL;

	return SUCCEED;
}
/******************************************************************************
 *                                                                            *
 * Purpose: parses host,key pair from history data json row                   *
 *                                                                            *
 * Parameters: jp_row - [IN] JSON with history data row                       *
 *             hk     - [OUT] the host,key pair                               *
 *                                                                            *
 * Return value:  SUCCEED - the host,key pair was parsed successfully         *
 *                FAIL    - otherwise                                         *
 *                                                                            *
 ******************************************************************************/
static int	parse_history_data_row_hostkey(const struct zbx_json_parse *jp_row, zbx_host_key_t *hk)
{
	size_t str_alloc;

	str_alloc = 0;
	zbx_free(hk->host);

	if (SUCCEED != zbx_json_value_by_name_dyn(jp_row, ZBX_PROTO_TAG_HOST, &hk->host, &str_alloc, NULL))
		return FAIL;

	str_alloc = 0;
	zbx_free(hk->key);

	if (SUCCEED != zbx_json_value_by_name_dyn(jp_row, ZBX_PROTO_TAG_KEY, &hk->key, &str_alloc, NULL))
	{
		zbx_free(hk->host);
		return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: parses up to ZBX_HISTORY_VALUES_MAX item values and host,key      *
 *          pairs from history data json                                      *
 *                                                                            *
 * Parameters: jp_data      - [IN] JSON with history data array               *
 *             pnext        - [IN/OUT] the pointer to the next item in json,  *
 *                                     NULL - no more data left               *
 *             values       - [OUT] the item values                           *
 *             hostkeys     - [OUT] the corresponding host,key pairs          *
 *             values_num   - [OUT] number of elements in values and hostkeys *
 *                                  arrays                                    *
 *             parsed_num   - [OUT] the number of values parsed               *
 *             unique_shift - [IN/OUT] auto increment nanoseconds to ensure   *
 *                                     unique value of timestamps             *
 *                                                                            *
 * Return value:  SUCCEED - values were parsed successfully                   *
 *                FAIL    - an error occurred                                 *
 *                                                                            *
 ******************************************************************************/
static int	parse_history_data(struct zbx_json_parse *jp_data, const char **pnext, zbx_agent_value_t *values,
		zbx_host_key_t *hostkeys, int *values_num, int *parsed_num, zbx_timespec_t *unique_shift)
{
	struct zbx_json_parse	jp_row;
	int			ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	*values_num = 0;
	*parsed_num = 0;

	if (NULL == *pnext)
	{
		if (NULL == (*pnext = zbx_json_next(jp_data, *pnext)) && *values_num < ZBX_HISTORY_VALUES_MAX)
		{
			ret = SUCCEED;
			goto out;
		}
	}

	/* iterate the history data rows */
	do
	{
		if (FAIL == zbx_json_brackets_open(*pnext, &jp_row))
		{
			zabbix_log(LOG_LEVEL_WARNING, "%s", zbx_json_strerror());
			goto out;
		}

		(*parsed_num)++;

		if (SUCCEED != parse_history_data_row_hostkey(&jp_row, &hostkeys[*values_num]))
			continue;

		if (SUCCEED != parse_history_data_row_value(&jp_row, unique_shift, &values[*values_num]))
			continue;

		(*values_num)++;
	}
	while (NULL != (*pnext = zbx_json_next(jp_data, *pnext)) && *values_num < ZBX_HISTORY_VALUES_MAX);

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s processed:%d/%d", __func__, zbx_result_string(ret),
			*values_num, *parsed_num);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: parses up to ZBX_HISTORY_VALUES_MAX item values and item          *
 *          identifiers from history data json                                *
 *                                                                            *
 * Parameters: jp_data      - [IN] JSON with history data array               *
 *             pnext        - [IN/OUT] the pointer to the next item in        *
 *                                        json, NULL - no more data left      *
 *             values       - [OUT] the item values                           *
 *             itemids      - [OUT] the corresponding item identifiers        *
 *             values_num   - [OUT] number of elements in values and itemids  *
 *                                  arrays                                    *
 *             parsed_num   - [OUT] the number of values parsed               *
 *             unique_shift - [IN/OUT] auto increment nanoseconds to ensure   *
 *                                     unique value of timestamps             *
 *             info         - [OUT] address of a pointer to the info string   *
 *                                  (should be freed by the caller)           *
 *                                                                            *
 * Return value:  SUCCEED - values were parsed successfully                   *
 *                FAIL    - an error occurred                                 *
 *                                                                            *
 * Comments: This function is used to parse the new proxy history data        *
 *           protocol introduced in Zabbix v3.3.                              *
 *                                                                            *
 ******************************************************************************/
static int	parse_history_data_by_itemids(struct zbx_json_parse *jp_data, const char **pnext,
		zbx_agent_value_t *values, zbx_uint64_t *itemids, int *values_num, int *parsed_num,
		zbx_timespec_t *unique_shift, char **error)
{
	struct zbx_json_parse	jp_row;
	int			ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	*values_num = 0;
	*parsed_num = 0;

	if (NULL == *pnext)
	{
		if (NULL == (*pnext = zbx_json_next(jp_data, *pnext)) && *values_num < ZBX_HISTORY_VALUES_MAX)
		{
			ret = SUCCEED;
			goto out;
		}
	}

	/* iterate the history data rows */
	do
	{
		if (FAIL == zbx_json_brackets_open(*pnext, &jp_row))
		{
			*error = zbx_strdup(*error, zbx_json_strerror());
			goto out;
		}

		(*parsed_num)++;

		if (SUCCEED != parse_history_data_row_itemid(&jp_row, &itemids[*values_num]))
			continue;

		if (SUCCEED != parse_history_data_row_value(&jp_row, unique_shift, &values[*values_num]))
			continue;

		(*values_num)++;
	}
	while (NULL != (*pnext = zbx_json_next(jp_data, *pnext)) && *values_num < ZBX_HISTORY_VALUES_MAX);

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s processed:%d/%d", __func__, zbx_result_string(ret),
			*values_num, *parsed_num);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: validates item received from proxy                                *
 *                                                                            *
 * Parameters: item  - [IN/OUT] the item data                                 *
 *             sock  - [IN] the connection socket                             *
 *             args  - [IN] the validator arguments                           *
 *             error - unused                                                 *
 *                                                                            *
 * Return value:  SUCCEED - the validation was successful                     *
 *                FAIL    - otherwise                                         *
 *                                                                            *
 ******************************************************************************/
static int	proxy_item_validator(zbx_history_recv_item_t *item, zbx_socket_t *sock, void *args, char **error)
{
	zbx_uint64_t	*proxyid = (zbx_uint64_t *)args;

	ZBX_UNUSED(sock);
	ZBX_UNUSED(error);

	/* don't process item if its host was assigned to another proxy */
	if (item->host.proxy_hostid != *proxyid)
		return FAIL;

	/* don't process aggregate/calculated items coming from proxy */
	if (ITEM_TYPE_CALCULATED == item->type)
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: parses history data array and process the data                    *
 *                                                                            *
 *                                                                            *
 * Parameters: sock           - [IN]  socket for host permission validation   *
 *             validator_func - [IN]  function to validate item permission    *
 *             validator_args - [IN]  validator function arguments            *
 *             jp_data        - [IN]  JSON with history data array            *
 *             session        - [IN]  the data session                        *
 *             nodata_win     - [OUT] counter of delayed values               *
 *             info           - [OUT] address of a pointer to the info        *
 *                                    string (should be freed by the caller)  *
 *             mode           - [IN]  item retrieve mode is used to retrieve  *
 *                                    only necessary data to reduce time      *
 *                                    spent holding read lock                 *
 *                                                                            *
 * Return value:  SUCCEED - processed successfully                            *
 *                FAIL - an error occurred                                    *
 *                                                                            *
 * Comments: This function is used to parse the new proxy history data        *
 *           protocol introduced in Zabbix v3.3.                              *
 *                                                                            *
 ******************************************************************************/
static int	process_history_data_by_itemids(zbx_socket_t *sock, zbx_client_item_validator_t validator_func,
		void *validator_args, struct zbx_json_parse *jp_data, zbx_session_t *session,
		zbx_proxy_suppress_t *nodata_win, char **info, unsigned int mode)
{
	const char		*pnext = NULL;
	int			ret = SUCCEED, processed_num = 0, total_num = 0, values_num, read_num, i, *errcodes;
	double			sec;
	zbx_history_recv_item_t	*items;
	char			*error = NULL;
	zbx_uint64_t		itemids[ZBX_HISTORY_VALUES_MAX], last_valueid = 0;
	zbx_agent_value_t	values[ZBX_HISTORY_VALUES_MAX];
	zbx_timespec_t		unique_shift = {0, 0};

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	items = (zbx_history_recv_item_t *)zbx_malloc(NULL, sizeof(zbx_history_recv_item_t) * ZBX_HISTORY_VALUES_MAX);
	errcodes = (int *)zbx_malloc(NULL, sizeof(int) * ZBX_HISTORY_VALUES_MAX);

	sec = zbx_time();

	while (SUCCEED == parse_history_data_by_itemids(jp_data, &pnext, values, itemids, &values_num, &read_num,
			&unique_shift, &error) && 0 != values_num)
	{
		zbx_dc_config_history_recv_get_items_by_itemids(items, itemids, errcodes, (size_t)values_num, mode);

		for (i = 0; i < values_num; i++)
		{
			if (SUCCEED != errcodes[i])
				continue;

			/* check and discard if duplicate data */
			if (NULL != session && 0 != values[i].id && values[i].id <= session->last_id)
			{
				errcodes[i] = FAIL;
				continue;
			}

			if (SUCCEED != validator_func(&items[i], sock, validator_args, &error))
			{
				if (NULL != error)
				{
					zabbix_log(LOG_LEVEL_WARNING, "%s", error);
					zbx_free(error);
				}

				errcodes[i] = FAIL;
			}
		}

		processed_num += zbx_process_history_data(items, values, errcodes, values_num, nodata_win);

		total_num += read_num;

		last_valueid = values[values_num - 1].id;

		zbx_agent_values_clean(values, values_num);

		if (NULL == pnext)
			break;
	}

	if (NULL != session && 0 != last_valueid)
	{
		if (session->last_id > last_valueid)
		{
			zabbix_log(LOG_LEVEL_WARNING, "received id:" ZBX_FS_UI64 " is less than last id:"
					ZBX_FS_UI64, last_valueid, session->last_id);
		}
		else
			session->last_id = last_valueid;
	}

	zbx_free(errcodes);
	zbx_free(items);

	if (NULL == error)
	{
		ret = SUCCEED;
		*info = zbx_dsprintf(*info, "processed: %d; failed: %d; total: %d; seconds spent: " ZBX_FS_DBL,
				processed_num, total_num - processed_num, total_num, zbx_time() - sec);
	}
	else
	{
		zbx_free(*info);
		*info = error;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: validates item received from active agent                         *
 *                                                                            *
 * Parameters: item  - [IN] the item data                                     *
 *             sock  - [IN] the connection socket                             *
 *             args  - [IN] the validator arguments                           *
 *             error - [OUT] the error message                                *
 *                                                                            *
 * Return value:  SUCCEED - the validation was successful                     *
 *                FAIL    - otherwise                                         *
 *                                                                            *
 ******************************************************************************/
static int	agent_item_validator(zbx_history_recv_item_t *item, zbx_socket_t *sock, void *args, char **error)
{
	zbx_host_rights_t	*rights = (zbx_host_rights_t *)args;

	if (0 != item->host.proxy_hostid)
		return FAIL;

	if (ITEM_TYPE_ZABBIX_ACTIVE != item->type)
		return FAIL;

	if (rights->hostid != item->host.hostid)
	{
		rights->hostid = item->host.hostid;
		rights->value = zbx_host_check_permissions(&item->host, sock, error);
	}

	return rights->value;
}

/******************************************************************************
 *                                                                            *
 * Purpose: validates item received from sender                               *
 *                                                                            *
 * Parameters: item  - [IN] the item data                                     *
 *             sock  - [IN] the connection socket                             *
 *             args  - [IN] the validator arguments                           *
 *             error - [OUT] the error message                                *
 *                                                                            *
 * Return value:  SUCCEED - the validation was successful                     *
 *                FAIL    - otherwise                                         *
 *                                                                            *
 ******************************************************************************/
static int	sender_item_validator(zbx_history_recv_item_t *item, zbx_socket_t *sock, void *args, char **error)
{
	zbx_host_rights_t	*rights;
	char			key_short[VALUE_ERRMSG_MAX * ZBX_MAX_BYTES_IN_UTF8_CHAR + 1];

	if (0 != item->host.proxy_hostid)
		return FAIL;

	switch(item->type)
	{
		case ITEM_TYPE_HTTPAGENT:
			if (0 == item->allow_traps)
			{
				*error = zbx_dsprintf(*error, "cannot process HTTP agent item \"%s\" trap:"
						" trapping is not enabled", zbx_truncate_itemkey(item->key_orig,
						VALUE_ERRMSG_MAX, key_short, sizeof(key_short)));
				return FAIL;
			}
			break;
		case ITEM_TYPE_TRAPPER:
			break;
		default:
			*error = zbx_dsprintf(*error, "cannot process item \"%s\" trap:"
					" item type \"%d\" cannot be used with traps",
					zbx_truncate_itemkey(item->key_orig, VALUE_ERRMSG_MAX, key_short,
					sizeof(key_short)), item->type);
			return FAIL;
	}

	if ('\0' != *item->trapper_hosts)	/* list of allowed hosts not empty */
	{
		char	*allowed_peers;
		int	ret;

		allowed_peers = zbx_strdup(NULL, item->trapper_hosts);
		zbx_substitute_simple_macros_allowed_hosts(item, &allowed_peers);
		ret = zbx_tcp_check_allowed_peers(sock, allowed_peers);
		zbx_free(allowed_peers);

		if (FAIL == ret)
		{
			*error = zbx_dsprintf(*error, "cannot process item \"%s\" trap: %s",
					zbx_truncate_itemkey(item->key_orig, VALUE_ERRMSG_MAX, key_short,
					sizeof(key_short)), zbx_socket_strerror());
			return FAIL;
		}
	}

	rights = (zbx_host_rights_t *)args;

	if (rights->hostid != item->host.hostid)
	{
		rights->hostid = item->host.hostid;
		rights->value = zbx_host_check_permissions(&item->host, sock, error);
	}

	return rights->value;
}

static void	process_history_data_by_keys(zbx_socket_t *sock, zbx_client_item_validator_t validator_func,
		void *validator_args, char **info, struct zbx_json_parse *jp_data, const char *token)
{
	int			values_num, read_num, processed_num = 0, total_num = 0, i;
	zbx_timespec_t		unique_shift = {0, 0};
	const char		*pnext = NULL;
	char			*error = NULL;
	zbx_host_key_t		*hostkeys;
	zbx_history_recv_item_t	*items;
	zbx_session_t		*session = NULL;
	zbx_uint64_t		last_hostid = 0;
	zbx_agent_value_t	values[ZBX_HISTORY_VALUES_MAX];
	int			errcodes[ZBX_HISTORY_VALUES_MAX];
	double			sec;

	sec = zbx_time();

	items = (zbx_history_recv_item_t *)zbx_malloc(NULL, sizeof(zbx_history_recv_item_t) * ZBX_HISTORY_VALUES_MAX);
	hostkeys = (zbx_host_key_t *)zbx_malloc(NULL, sizeof(zbx_host_key_t) * ZBX_HISTORY_VALUES_MAX);
	memset(hostkeys, 0, sizeof(zbx_host_key_t) * ZBX_HISTORY_VALUES_MAX);

	while (SUCCEED == parse_history_data(jp_data, &pnext, values, hostkeys, &values_num, &read_num,
			&unique_shift) && 0 != values_num)
	{
		zbx_dc_config_history_recv_get_items_by_keys(items, hostkeys, errcodes, (size_t)values_num);

		for (i = 0; i < values_num; i++)
		{
			if (SUCCEED != errcodes[i])
			{
				zabbix_log(LOG_LEVEL_DEBUG, "cannot retrieve key \"%s\" on host \"%s\" from "
						"configuration cache", hostkeys[i].key, hostkeys[i].host);
				continue;
			}

			if (last_hostid != items[i].host.hostid)
			{
				last_hostid = items[i].host.hostid;

				if (NULL != token)
				{
					session = zbx_dc_get_or_create_session(last_hostid, token,
							ZBX_SESSION_TYPE_DATA);
				}
			}

			/* check and discard if duplicate data */
			if (NULL != session && 0 != values[i].id && values[i].id <= session->last_id)
			{
				errcodes[i] = FAIL;
				continue;
			}

			if (SUCCEED != validator_func(&items[i], sock, validator_args, &error))
			{
				if (NULL != error)
				{
					zabbix_log(LOG_LEVEL_WARNING, "%s", error);
					zbx_free(error);
				}
				else
				{
					zabbix_log(LOG_LEVEL_DEBUG, "unknown validation error for item \"%s\"",
							(NULL == items[i].key) ? items[i].key_orig : items[i].key);
				}

				errcodes[i] = FAIL;
			}

			if (NULL != session)
				session->last_id = values[i].id;
		}

		processed_num += zbx_process_history_data(items, values, errcodes, values_num, NULL);
		total_num += read_num;

		zbx_agent_values_clean(values, values_num);

		if (NULL == pnext)
			break;
	}

	for (i = 0; i < ZBX_HISTORY_VALUES_MAX; i++)
	{
		zbx_free(hostkeys[i].host);
		zbx_free(hostkeys[i].key);
	}

	zbx_free(hostkeys);
	zbx_free(items);

	*info = zbx_dsprintf(*info, "processed: %d; failed: %d; total: %d; seconds spent: " ZBX_FS_DBL,
			processed_num, total_num - processed_num, total_num, zbx_time() - sec);
}

/******************************************************************************
 *                                                                            *
 * Purpose: process history data sent by proxy/agent/sender                   *
 *                                                                            *
 * Parameters: sock           - [IN] the connection socket                    *
 *             jp             - [IN] JSON with historical data                *
 *             ts             - [IN] the client connection timestamp          *
 *             validator_func - [IN] the item validator callback function     *
 *             validator_args - [IN] the user arguments passed to validator   *
 *                                   function                                 *
 *             info           - [OUT] address of a pointer to the info string *
 *                                    (should be freed by the caller)         *
 *                                                                            *
 * Return value:  SUCCEED - processed successfully                            *
 *                FAIL - an error occurred                                    *
 *                                                                            *
 ******************************************************************************/
static int	process_client_history_data(zbx_socket_t *sock, struct zbx_json_parse *jp, zbx_timespec_t *ts,
		zbx_client_item_validator_t validator_func, void *validator_args, char **info)
{
	int			ret;
	char			*token = NULL;
	size_t			token_alloc = 0;
	struct zbx_json_parse	jp_data;
	char			tmp[MAX_STRING_LEN];
	int			version;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	log_client_timediff(LOG_LEVEL_DEBUG, jp, ts);

	if (SUCCEED != (ret = zbx_json_brackets_by_name(jp, ZBX_PROTO_TAG_DATA, &jp_data)))
	{
		*info = zbx_strdup(*info, zbx_json_strerror());
		goto out;
	}

	if (SUCCEED == zbx_json_value_by_name_dyn(jp, ZBX_PROTO_TAG_SESSION, &token, &token_alloc, NULL))
	{
		size_t	token_len;

		if (ZBX_SESSION_TOKEN_SIZE != (token_len = strlen(token)))
		{
			*info = zbx_dsprintf(*info, "invalid session token length %d", (int)token_len);
			ret = FAIL;
			goto out;
		}
	}

	if (SUCCEED != zbx_json_value_by_name(jp, ZBX_PROTO_TAG_VERSION, tmp, sizeof(tmp), NULL) ||
				FAIL == (version = zbx_get_component_version_without_patch(tmp)))
	{
		version = ZBX_COMPONENT_VERSION(4, 2, 0);
	}

	if (ZBX_COMPONENT_VERSION(4, 4, 0) <= version &&
			SUCCEED == zbx_json_value_by_name(jp, ZBX_PROTO_TAG_HOST, tmp, sizeof(tmp), NULL))
	{
		zbx_session_t	*session;
		zbx_uint64_t	hostid;

		if (SUCCEED != DCconfig_get_hostid_by_name(tmp, &hostid))
		{
			*info = zbx_dsprintf(*info, "unknown host '%s'", tmp);
			ret = SUCCEED;
			goto out;
		}

		if (NULL == token)
			session = NULL;
		else
			session = zbx_dc_get_or_create_session(hostid, token, ZBX_SESSION_TYPE_DATA);

		if (SUCCEED != (ret = process_history_data_by_itemids(sock, validator_func, validator_args, &jp_data,
				session, NULL, info, ZBX_ITEM_GET_DEFAULT)))
		{
			goto out;
		}
	}
	else
		process_history_data_by_keys(sock, validator_func, validator_args, info, &jp_data, token);
out:
	zbx_free(token);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: process history data received from Zabbix active agent            *
 *                                                                            *
 * Parameters: sock         - [IN] the connection socket                      *
 *             jp           - [IN] the JSON with history data                 *
 *             ts           - [IN] the connection timestamp                   *
 *             info         - [OUT] address of a pointer to the info string   *
 *                                  (should be freed by the caller)           *
 *                                                                            *
 * Return value:  SUCCEED - processed successfully                            *
 *                FAIL - an error occurred                                    *
 *                                                                            *
 ******************************************************************************/
int	zbx_process_agent_history_data(zbx_socket_t *sock, struct zbx_json_parse *jp, zbx_timespec_t *ts, char **info)
{
	zbx_host_rights_t	rights = {0};

	return process_client_history_data(sock, jp, ts, agent_item_validator, &rights, info);
}

/******************************************************************************
 *                                                                            *
 * Purpose: process history data received from Zabbix sender                  *
 *                                                                            *
 * Parameters: sock         - [IN] the connection socket                      *
 *             jp           - [IN] the JSON with history data                 *
 *             ts           - [IN] the connection timestamp                   *
 *             info         - [OUT] address of a pointer to the info string   *
 *                                  (should be freed by the caller)           *
 *                                                                            *
 * Return value:  SUCCEED - processed successfully                            *
 *                FAIL - an error occurred                                    *
 *                                                                            *
 ******************************************************************************/
int	zbx_process_sender_history_data(zbx_socket_t *sock, struct zbx_json_parse *jp, zbx_timespec_t *ts, char **info)
{
	zbx_host_rights_t	rights = {0};
	int			ret;
	zbx_dc_um_handle_t	*um_handle;

	um_handle = zbx_dc_open_user_macros();

	ret = process_client_history_data(sock, jp, ts, sender_item_validator, &rights, info);

	zbx_dc_close_user_macros(um_handle);

	return ret;
}

static void	zbx_drule_ip_free(zbx_drule_ip_t *ip)
{
	zbx_vector_ptr_clear_ext(&ip->services, zbx_ptr_free);
	zbx_vector_ptr_destroy(&ip->services);
	zbx_free(ip);
}

static void	zbx_drule_free(zbx_drule_t *drule)
{
	zbx_vector_ptr_clear_ext(&drule->ips, (zbx_clean_func_t)zbx_drule_ip_free);
	zbx_vector_ptr_destroy(&drule->ips);
	zbx_vector_uint64_destroy(&drule->dcheckids);
	zbx_free(drule);
}

/******************************************************************************
 *                                                                            *
 * Purpose: process services discovered on IP address                         *
 *                                                                            *
 * Parameters: drule_ptr         - [IN] discovery rule structure              *
 *             ip_discovered_ptr - [IN] vector of ip addresses                *
 *                                                                            *
 ******************************************************************************/
static int	process_services(const zbx_vector_ptr_t *services, const char *ip, zbx_uint64_t druleid,
		zbx_vector_uint64_t *dcheckids, zbx_uint64_t unique_dcheckid, int *processed_num, int ip_idx)
{
	zbx_db_dhost		dhost;
	zbx_dservice_t		*service;
	int			services_num, ret = FAIL, i, dchecks = 0;
	zbx_vector_ptr_t	services_old;
	zbx_db_drule		drule = {.druleid = druleid, .unique_dcheckid = unique_dcheckid};

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	memset(&dhost, 0, sizeof(dhost));

	zbx_vector_ptr_create(&services_old);

	/* find host update */
	for (i = *processed_num; i < services->values_num; i++)
	{
		service = (zbx_dservice_t *)services->values[i];

		zabbix_log(LOG_LEVEL_DEBUG, "%s() druleid:" ZBX_FS_UI64 " dcheckid:" ZBX_FS_UI64 " unique_dcheckid:"
				ZBX_FS_UI64 " time:'%s %s' ip:'%s' dns:'%s' port:%hu status:%d value:'%s'",
				__func__, drule.druleid, service->dcheckid, drule.unique_dcheckid,
				zbx_date2str(service->itemtime, NULL), zbx_time2str(service->itemtime, NULL), ip, service->dns,
				service->port, service->status, service->value);

		if (0 == service->dcheckid)
			break;

		dchecks++;
	}

	/* stop processing current discovery rule and save proxy history until host update is available */
	if (i == services->values_num)
	{
		for (i = *processed_num; i < services->values_num; i++)
		{
			char	*ip_esc, *dns_esc, *value_esc;

			service = (zbx_dservice_t *)services->values[i];

			ip_esc = zbx_db_dyn_escape_field("proxy_dhistory", "ip", ip);
			dns_esc = zbx_db_dyn_escape_field("proxy_dhistory", "dns", service->dns);
			value_esc = zbx_db_dyn_escape_field("proxy_dhistory", "value", service->value);

			zbx_db_execute("insert into proxy_dhistory (clock,druleid,ip,port,value,status,dcheckid,dns)"
					" values (%d," ZBX_FS_UI64 ",'%s',%d,'%s',%d," ZBX_FS_UI64 ",'%s')",
					(int)service->itemtime, drule.druleid, ip_esc, service->port,
					value_esc, service->status, service->dcheckid, dns_esc);
			zbx_free(value_esc);
			zbx_free(dns_esc);
			zbx_free(ip_esc);
		}

		goto fail;
	}

	services_num = i;

	if (0 == *processed_num && 0 == ip_idx)
	{
		DB_RESULT	result;
		DB_ROW		row;
		zbx_uint64_t	dcheckid;

		result = zbx_db_select(
				"select dcheckid,clock,port,value,status,dns,ip"
				" from proxy_dhistory"
				" where druleid=" ZBX_FS_UI64
				" order by id",
				drule.druleid);

		for (i = 0; NULL != (row = zbx_db_fetch(result)); i++)
		{
			if (SUCCEED == zbx_db_is_null(row[0]))
				continue;

			ZBX_STR2UINT64(dcheckid, row[0]);

			if (0 == strcmp(ip, row[6]))
			{
				service = (zbx_dservice_t *)zbx_malloc(NULL, sizeof(zbx_dservice_t));
				service->dcheckid = dcheckid;
				service->itemtime = (time_t)atoi(row[1]);
				service->port = atoi(row[2]);
				zbx_strlcpy_utf8(service->value, row[3], ZBX_MAX_DISCOVERED_VALUE_SIZE);
				service->status = atoi(row[4]);
				zbx_strlcpy(service->dns, row[5], ZBX_INTERFACE_DNS_LEN_MAX);
				zbx_vector_ptr_append(&services_old, service);
				zbx_vector_uint64_append(dcheckids, service->dcheckid);
				dchecks++;
			}
		}
		zbx_db_free_result(result);

		if (0 != i)
		{
			zbx_db_execute("delete from proxy_dhistory"
					" where druleid=" ZBX_FS_UI64,
					drule.druleid);
		}

		zbx_vector_uint64_sort(dcheckids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_vector_uint64_uniq(dcheckids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		if (SUCCEED != zbx_db_lock_druleid(drule.druleid))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "druleid:" ZBX_FS_UI64 " does not exist", drule.druleid);
			goto fail;
		}

		if (SUCCEED != zbx_db_lock_ids("dchecks", "dcheckid", dcheckids))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "checks are not available for druleid:" ZBX_FS_UI64, drule.druleid);
			goto fail;
		}
	}

	if (0 == dchecks)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "cannot process host update without services");
		goto fail;
	}

	for (i = 0; i < services_old.values_num; i++)
	{
		service = (zbx_dservice_t *)services_old.values[i];

		if (FAIL == zbx_vector_uint64_bsearch(dcheckids, service->dcheckid, ZBX_DEFAULT_UINT64_COMPARE_FUNC))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "dcheckid:" ZBX_FS_UI64 " does not exist", service->dcheckid);
			continue;
		}

		zbx_discovery_update_service(&drule, service->dcheckid, &dhost, ip, service->dns, service->port,
				service->status, service->value, service->itemtime);
	}

	for (;*processed_num < services_num; (*processed_num)++)
	{
		service = (zbx_dservice_t *)services->values[*processed_num];

		if (FAIL == zbx_vector_uint64_bsearch(dcheckids, service->dcheckid, ZBX_DEFAULT_UINT64_COMPARE_FUNC))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "dcheckid:" ZBX_FS_UI64 " does not exist", service->dcheckid);
			continue;
		}

		zbx_discovery_update_service(&drule, service->dcheckid, &dhost, ip, service->dns, service->port,
				service->status, service->value, service->itemtime);
	}

	service = (zbx_dservice_t *)services->values[(*processed_num)++];
	zbx_discovery_update_host(&dhost, service->status, service->itemtime);

	ret = SUCCEED;
fail:
	zbx_vector_ptr_clear_ext(&services_old, zbx_ptr_free);
	zbx_vector_ptr_destroy(&services_old);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: parse discovery data contents and process it                      *
 *                                                                            *
 * Parameters: jp_data         - [IN] JSON with discovery data                *
 *             error           - [OUT] address of a pointer to the info       *
 *                                     string (should be freed by the caller) *
 *                                                                            *
 * Return value:  SUCCEED - processed successfully                            *
 *                FAIL - an error occurred                                    *
 *                                                                            *
 ******************************************************************************/
static int	process_discovery_data_contents(struct zbx_json_parse *jp_data, char **error)
{
	DB_RESULT		result;
	DB_ROW			row;
	zbx_uint64_t		dcheckid, druleid;
	struct zbx_json_parse	jp_row;
	int			status, ret = SUCCEED, i, j;
	unsigned short		port;
	const char		*p = NULL;
	char			ip[ZBX_INTERFACE_IP_LEN_MAX], tmp[MAX_STRING_LEN],
				dns[ZBX_INTERFACE_DNS_LEN_MAX], *value = NULL;
	time_t			itemtime;
	size_t			value_alloc = ZBX_MAX_DISCOVERED_VALUE_SIZE;
	zbx_vector_ptr_t	drules;
	zbx_drule_t		*drule;
	zbx_drule_ip_t		*drule_ip;
	zbx_dservice_t		*service;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	value = (char *)zbx_malloc(value, value_alloc);

	zbx_vector_ptr_create(&drules);

	while (NULL != (p = zbx_json_next(jp_data, p)))
	{
		if (FAIL == zbx_json_brackets_open(p, &jp_row))
			goto json_parse_error;

		if (FAIL == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_CLOCK, tmp, sizeof(tmp), NULL))
			goto json_parse_error;

		itemtime = atoi(tmp);

		if (FAIL == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_DRULE, tmp, sizeof(tmp), NULL))
			goto json_parse_error;

		ZBX_STR2UINT64(druleid, tmp);

		if (FAIL == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_DCHECK, tmp, sizeof(tmp), NULL))
			goto json_parse_error;

		if ('\0' != *tmp)
			ZBX_STR2UINT64(dcheckid, tmp);
		else
			dcheckid = 0;

		if (FAIL == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_IP, ip, sizeof(ip), NULL))
			goto json_parse_error;

		if (SUCCEED != zbx_is_ip(ip))
		{
			zabbix_log(LOG_LEVEL_WARNING, "%s(): \"%s\" is not a valid IP address", __func__, ip);
			continue;
		}

		if (FAIL == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_PORT, tmp, sizeof(tmp), NULL))
		{
			port = 0;
		}
		else if (FAIL == zbx_is_ushort(tmp, &port))
		{
			zabbix_log(LOG_LEVEL_WARNING, "%s(): \"%s\" is not a valid port", __func__, tmp);
			continue;
		}

		if (SUCCEED != zbx_json_value_by_name_dyn(&jp_row, ZBX_PROTO_TAG_VALUE, &value, &value_alloc, NULL))
			*value = '\0';

		if (FAIL == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_DNS, dns, sizeof(dns), NULL))
		{
			*dns = '\0';
		}
		else if ('\0' != *dns && FAIL == zbx_validate_hostname(dns))
		{
			zabbix_log(LOG_LEVEL_WARNING, "%s(): \"%s\" is not a valid hostname", __func__, dns);
			continue;
		}

		if (SUCCEED == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_STATUS, tmp, sizeof(tmp), NULL))
			status = atoi(tmp);
		else
			status = 0;

		if (FAIL == (i = zbx_vector_ptr_search(&drules, &druleid, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
		{
			drule = (zbx_drule_t *)zbx_malloc(NULL, sizeof(zbx_drule_t));
			drule->druleid = druleid;
			zbx_vector_ptr_create(&drule->ips);
			zbx_vector_uint64_create(&drule->dcheckids);
			zbx_vector_ptr_append(&drules, drule);
		}
		else
			drule = drules.values[i];

		if (FAIL == (i = zbx_vector_ptr_search(&drule->ips, ip, ZBX_DEFAULT_STR_COMPARE_FUNC)))
		{
			drule_ip = (zbx_drule_ip_t *)zbx_malloc(NULL, sizeof(zbx_drule_ip_t));
			zbx_strlcpy(drule_ip->ip, ip, ZBX_INTERFACE_IP_LEN_MAX);
			zbx_vector_ptr_create(&drule_ip->services);
			zbx_vector_ptr_append(&drule->ips, drule_ip);
		}
		else
			drule_ip = drule->ips.values[i];

		service = (zbx_dservice_t *)zbx_malloc(NULL, sizeof(zbx_dservice_t));
		if (0 != (service->dcheckid = dcheckid))
			zbx_vector_uint64_append(&drule->dcheckids, service->dcheckid);
		service->port = port;
		service->status = status;
		zbx_strlcpy_utf8(service->value, value, ZBX_MAX_DISCOVERED_VALUE_SIZE);
		zbx_strlcpy(service->dns, dns, ZBX_INTERFACE_DNS_LEN_MAX);
		service->itemtime = itemtime;
		zbx_vector_ptr_append(&drule_ip->services, service);

		continue;
json_parse_error:
		*error = zbx_strdup(*error, zbx_json_strerror());
		ret = FAIL;
		goto json_parse_return;
	}

	for (i = 0; i < drules.values_num; i++)
	{
		zbx_uint64_t	unique_dcheckid;
		int		ret2 = SUCCEED;

		drule = (zbx_drule_t *)drules.values[i];

		zbx_db_begin();
		result = zbx_db_select(
				"select dcheckid"
				" from dchecks"
				" where druleid=" ZBX_FS_UI64
					" and uniq=1",
				drule->druleid);

		if (NULL != (row = zbx_db_fetch(result)))
			ZBX_STR2UINT64(unique_dcheckid, row[0]);
		else
			unique_dcheckid = 0;
		zbx_db_free_result(result);
		for (j = 0; j < drule->ips.values_num && SUCCEED == ret2; j++)
		{
			int	processed_num = 0;

			drule_ip = (zbx_drule_ip_t *)drule->ips.values[j];

			while (processed_num != drule_ip->services.values_num)
			{
				if (FAIL == (ret2 = process_services(&drule_ip->services, drule_ip->ip, drule->druleid,
						&drule->dcheckids, unique_dcheckid, &processed_num, j)))
				{
					break;
				}
			}
		}

		zbx_process_events(NULL, NULL);
		zbx_clean_events();
		zbx_db_commit();
	}
json_parse_return:
	zbx_free(value);

	zbx_vector_ptr_clear_ext(&drules, (zbx_clean_func_t)zbx_drule_free);
	zbx_vector_ptr_destroy(&drules);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: parse autoregistration data contents and process it               *
 *                                                                            *
 * Parameters: jp_data         - [IN] JSON with autoregistration data         *
 *             proxy_hostid    - [IN] proxy identifier from database          *
 *             error           - [OUT] address of a pointer to the info       *
 *                                     string (should be freed by the caller) *
 *                                                                            *
 * Return value:  SUCCEED - processed successfully                            *
 *                FAIL - an error occurred                                    *
 *                                                                            *
 ******************************************************************************/
static int	process_autoregistration_contents(struct zbx_json_parse *jp_data, zbx_uint64_t proxy_hostid,
		char **error)
{
	struct zbx_json_parse	jp_row;
	int			ret = SUCCEED;
	const char		*p = NULL;
	time_t			itemtime;
	char			host[ZBX_HOSTNAME_BUF_LEN], ip[ZBX_INTERFACE_IP_LEN_MAX],
				dns[ZBX_INTERFACE_DNS_LEN_MAX], tmp[MAX_STRING_LEN], *host_metadata = NULL;
	unsigned short		port;
	size_t			host_metadata_alloc = 1;	/* for at least NUL-terminating string */
	zbx_vector_ptr_t	autoreg_hosts;
	zbx_conn_flags_t	flags = ZBX_CONN_DEFAULT;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (0 == DCget_auto_registration_action_count())
	{
		zabbix_log(LOG_LEVEL_DEBUG, "cannot process auto registration contents, all autoregistration actions"
				" are disabled");
		goto out;
	}

	zbx_vector_ptr_create(&autoreg_hosts);
	host_metadata = (char *)zbx_malloc(host_metadata, host_metadata_alloc);

	while (NULL != (p = zbx_json_next(jp_data, p)))
	{
		unsigned int	connection_type;

		if (FAIL == (ret = zbx_json_brackets_open(p, &jp_row)))
			break;

		if (FAIL == (ret = zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_CLOCK, tmp, sizeof(tmp), NULL)))
			break;

		itemtime = atoi(tmp);

		if (FAIL == (ret = zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_HOST, host, sizeof(host), NULL)))
			break;

		if (FAIL == zbx_check_hostname(host, NULL))
		{
			zabbix_log(LOG_LEVEL_WARNING, "%s(): \"%s\" is not a valid Zabbix host name", __func__, host);
			continue;
		}

		if (FAIL == zbx_json_value_by_name_dyn(&jp_row, ZBX_PROTO_TAG_HOST_METADATA,
				&host_metadata, &host_metadata_alloc, NULL))
		{
			*host_metadata = '\0';
		}

		if (FAIL != zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_FLAGS, tmp, sizeof(tmp), NULL))
		{
			int flags_int;

			flags_int = atoi(tmp);

			switch (flags_int)
			{
				case ZBX_CONN_DEFAULT:
				case ZBX_CONN_IP:
				case ZBX_CONN_DNS:
					flags = (zbx_conn_flags_t)flags_int;
					break;
				default:
					flags = ZBX_CONN_DEFAULT;
					zabbix_log(LOG_LEVEL_WARNING, "wrong flags value: %d for host \"%s\":",
							flags_int, host);
			}
		}

		if (FAIL == (ret = zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_IP, ip, sizeof(ip), NULL)))
		{
			if (ZBX_CONN_DNS == flags)
			{
				*ip = '\0';
				ret = SUCCEED;
			}
			else
				break;
		}
		else if (SUCCEED != zbx_is_ip(ip))
		{
			zabbix_log(LOG_LEVEL_WARNING, "%s(): \"%s\" is not a valid IP address", __func__, ip);
			continue;
		}

		if (FAIL == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_DNS, dns, sizeof(dns), NULL))
		{
			*dns = '\0';
		}
		else if ('\0' != *dns && FAIL == zbx_validate_hostname(dns))
		{
			zabbix_log(LOG_LEVEL_WARNING, "%s(): \"%s\" is not a valid hostname", __func__, dns);
			continue;
		}

		if (FAIL == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_PORT, tmp, sizeof(tmp), NULL))
		{
			port = ZBX_DEFAULT_AGENT_PORT;
		}
		else if (FAIL == zbx_is_ushort(tmp, &port))
		{
			zabbix_log(LOG_LEVEL_WARNING, "%s(): \"%s\" is not a valid port", __func__, tmp);
			continue;
		}

		if (FAIL == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_TLS_ACCEPTED, tmp, sizeof(tmp), NULL))
		{
			connection_type = ZBX_TCP_SEC_UNENCRYPTED;
		}
		else if (FAIL == zbx_is_uint32(tmp, &connection_type) || (ZBX_TCP_SEC_UNENCRYPTED != connection_type &&
				ZBX_TCP_SEC_TLS_PSK != connection_type && ZBX_TCP_SEC_TLS_CERT != connection_type))
		{
			zabbix_log(LOG_LEVEL_WARNING, "%s(): \"%s\" is not a valid value for \""
					ZBX_PROTO_TAG_TLS_ACCEPTED "\"", __func__, tmp);
			continue;
		}

		zbx_db_register_host_prepare(&autoreg_hosts, host, ip, dns, port, connection_type, host_metadata, flags,
				itemtime);
	}

	if (0 != autoreg_hosts.values_num)
	{
		zbx_db_begin();
		zbx_db_register_host_flush(&autoreg_hosts, proxy_hostid);
		zbx_db_commit();
		DCconfig_delete_autoreg_host(&autoreg_hosts);
	}

	zbx_free(host_metadata);
	zbx_db_register_host_clean(&autoreg_hosts);
	zbx_vector_ptr_destroy(&autoreg_hosts);

	if (SUCCEED != ret)
		*error = zbx_strdup(*error, zbx_json_strerror());
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get the number of values waiting to be sent to the server         *
 *                                                                            *
 * Return value: the number of history values                                 *
 *                                                                            *
 ******************************************************************************/
int	zbx_proxy_get_history_count(void)
{
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	id;
	int		count = 0;

	zbx_db_connect(ZBX_DB_CONNECT_NORMAL);

	proxy_get_lastid("proxy_history", "history_lastid", &id);

	result = zbx_db_select(
			"select count(*)"
			" from proxy_history"
			" where id>" ZBX_FS_UI64,
			id);

	if (NULL != (row = zbx_db_fetch(result)))
		count = atoi(row[0]);

	zbx_db_free_result(result);

	zbx_db_close();

	return count;
}

/******************************************************************************
 *                                                                            *
 * Purpose: parse tasks contents and saves the received tasks                 *
 *                                                                            *
 * Parameters: jp_tasks - [IN] JSON with tasks data                           *
 *                                                                            *
 ******************************************************************************/
static void	process_tasks_contents(struct zbx_json_parse *jp_tasks)
{
	zbx_vector_tm_task_t	tasks;

	zbx_vector_tm_task_create(&tasks);

	zbx_tm_json_deserialize_tasks(jp_tasks, &tasks);

	zbx_db_begin();
	zbx_tm_save_tasks(&tasks);
	zbx_db_commit();

	zbx_vector_tm_task_clear_ext(&tasks, zbx_tm_task_free);
	zbx_vector_tm_task_destroy(&tasks);
}

/******************************************************************************
 *                                                                            *
 * Purpose: appends text to the string on a new line                          *
 *                                                                            *
 ******************************************************************************/
static void	zbx_strcatnl_alloc(char **info, size_t *info_alloc, size_t *info_offset, const char *text)
{
	if (0 != *info_offset)
		zbx_chrcpy_alloc(info, info_alloc, info_offset, '\n');

	zbx_strcpy_alloc(info, info_alloc, info_offset, text);
}

/******************************************************************************
 *                                                                            *
 * Purpose: detect lost connection with proxy and calculate suppression       *
 *          window if possible                                                *
 *                                                                            *
 * Parameters: ts          - [IN] timestamp when the proxy connection was     *
 *                                established                                 *
 *             proxy_staus - [IN] - active or passive proxy                   *
 *             diff        - [IN/OUT] the properties to update                *
 *                                                                            *
 ******************************************************************************/
static void	check_proxy_nodata(zbx_timespec_t *ts, unsigned char proxy_status, zbx_proxy_diff_t *diff)
{
	int	delay;

	if (0 != (diff->nodata_win.flags & ZBX_PROXY_SUPPRESS_ACTIVE))
	{
		diff->nodata_win.values_num = 0;	/* reset counter of new suppress values received from proxy */
		return;					/* only for current packet */
	}

	delay = ts->sec - diff->lastaccess;

	if ((HOST_STATUS_PROXY_PASSIVE == proxy_status &&
			(2 * CONFIG_PROXYDATA_FREQUENCY) < delay && NET_DELAY_MAX < delay) ||
			(HOST_STATUS_PROXY_ACTIVE == proxy_status && NET_DELAY_MAX < delay))
	{
		diff->nodata_win.values_num = 0;
		diff->nodata_win.period_end = ts->sec;
		diff->flags |= ZBX_FLAGS_PROXY_DIFF_UPDATE_SUPPRESS_WIN;
		diff->nodata_win.flags |= ZBX_PROXY_SUPPRESS_ENABLE;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: detect lack of data during lost connectivity                      *
 *                                                                            *
 * Parameters: ts          - [IN] timestamp when the proxy connection was     *
 *                                established                                 *
 *             proxy_staus - [IN] - active or passive proxy                   *
 *             diff        - [IN/OUT] the properties to update                *
 *                                                                            *
 ******************************************************************************/
static void	check_proxy_nodata_empty(zbx_timespec_t *ts, unsigned char proxy_status, zbx_proxy_diff_t *diff)
{
	int	delay_empty;

	if (0 != (diff->nodata_win.flags & ZBX_PROXY_SUPPRESS_EMPTY) && 0 != diff->nodata_win.values_num)
		diff->nodata_win.flags &= (~ZBX_PROXY_SUPPRESS_EMPTY);

	if (0 == (diff->nodata_win.flags & ZBX_PROXY_SUPPRESS_EMPTY) || 0 != diff->nodata_win.values_num)
		return;

	delay_empty = ts->sec - diff->nodata_win.period_end;

	if (HOST_STATUS_PROXY_PASSIVE == proxy_status ||
			(HOST_STATUS_PROXY_ACTIVE == proxy_status && NET_DELAY_MAX < delay_empty))
	{
		diff->nodata_win.period_end = 0;
		diff->nodata_win.flags = ZBX_PROXY_SUPPRESS_DISABLE;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: process 'proxy data' request                                      *
 *                                                                            *
 * Parameters: proxy        - [IN] the source proxy                           *
 *             jp           - [IN] JSON with proxy data                       *
 *             proxy_hostid - [IN] proxy identifier from database             *
 *             ts           - [IN] timestamp when the proxy connection was    *
 *                                 established                                *
 *             proxy_status - [IN] active or passive proxy mode               *
 *             more         - [OUT] available data flag                       *
 *             error        - [OUT] address of a pointer to the info string   *
 *                                  (should be freed by the caller)           *
 *                                                                            *
 * Return value:  SUCCEED - processed successfully                            *
 *                FAIL - an error occurred                                    *
 *                                                                            *
 ******************************************************************************/
int	zbx_process_proxy_data(const DC_PROXY *proxy, struct zbx_json_parse *jp, zbx_timespec_t *ts,
		unsigned char proxy_status, int *more, char **error)
{
	struct zbx_json_parse	jp_data;
	int			ret = SUCCEED, flags_old;
	char			*error_step = NULL, value[MAX_STRING_LEN];
	size_t			error_alloc = 0, error_offset = 0;
	zbx_proxy_diff_t	proxy_diff;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	proxy_diff.flags = ZBX_FLAGS_PROXY_DIFF_UNSET;
	proxy_diff.hostid = proxy->hostid;

	if (SUCCEED != (ret = DCget_proxy_nodata_win(proxy_diff.hostid, &proxy_diff.nodata_win,
			&proxy_diff.lastaccess)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot get proxy communication delay");
		ret = FAIL;
		goto out;
	}

	if (SUCCEED == zbx_json_value_by_name(jp, ZBX_PROTO_TAG_MORE, value, sizeof(value), NULL))
		proxy_diff.more_data = atoi(value);
	else
		proxy_diff.more_data = ZBX_PROXY_DATA_DONE;

	if (NULL != more)
		*more = proxy_diff.more_data;

	if (SUCCEED == zbx_json_value_by_name(jp, ZBX_PROTO_TAG_PROXY_DELAY, value, sizeof(value), NULL))
		proxy_diff.proxy_delay = atoi(value);
	else
		proxy_diff.proxy_delay = 0;

	proxy_diff.flags |= ZBX_FLAGS_PROXY_DIFF_UPDATE_PROXYDELAY;
	flags_old = proxy_diff.nodata_win.flags;
	check_proxy_nodata(ts, proxy_status, &proxy_diff);	/* first packet can be empty for active proxy */

	zabbix_log(LOG_LEVEL_DEBUG, "%s() flag_win:%d/%d flag:%d proxy_status:%d period_end:%d delay:%d"
			" timestamp:%d lastaccess:%d proxy_delay:%d more:%d", __func__, proxy_diff.nodata_win.flags,
			flags_old, (int)proxy_diff.flags, proxy_status, proxy_diff.nodata_win.period_end,
			ts->sec - proxy_diff.lastaccess, ts->sec, proxy_diff.lastaccess, proxy_diff.proxy_delay,
			proxy_diff.more_data);

	if (ZBX_FLAGS_PROXY_DIFF_UNSET != proxy_diff.flags)
		zbx_dc_update_proxy(&proxy_diff);

	if (SUCCEED == zbx_json_brackets_by_name(jp, ZBX_PROTO_TAG_INTERFACE_AVAILABILITY, &jp_data))
	{
		if (SUCCEED != (ret = process_interfaces_availability_contents(&jp_data, &error_step)))
			zbx_strcatnl_alloc(error, &error_alloc, &error_offset, error_step);
	}

	flags_old = proxy_diff.nodata_win.flags;

	if (SUCCEED == zbx_json_brackets_by_name(jp, ZBX_PROTO_TAG_HISTORY_DATA, &jp_data))
	{
		zbx_session_t	*session = NULL;

		if (SUCCEED == zbx_json_value_by_name(jp, ZBX_PROTO_TAG_SESSION, value, sizeof(value), NULL))
		{
			size_t	token_len;

			if (ZBX_SESSION_TOKEN_SIZE != (token_len = strlen(value)))
			{
				*error = zbx_dsprintf(*error, "invalid session token length %d", (int)token_len);
				ret = FAIL;
				goto out;
			}

			session = zbx_dc_get_or_create_session(proxy->hostid, value, ZBX_SESSION_TYPE_DATA);
		}

		if (SUCCEED != (ret = process_history_data_by_itemids(NULL, proxy_item_validator,
				(void *)&proxy->hostid, &jp_data, session, &proxy_diff.nodata_win, &error_step,
				ZBX_ITEM_GET_PROCESS)))
		{
			zbx_strcatnl_alloc(error, &error_alloc, &error_offset, error_step);
		}
	}

	if (0 != (proxy_diff.nodata_win.flags & ZBX_PROXY_SUPPRESS_ACTIVE))
	{
		check_proxy_nodata_empty(ts, proxy_status, &proxy_diff);

		if (0 < proxy_diff.nodata_win.values_num || flags_old != proxy_diff.nodata_win.flags)
			proxy_diff.flags |= ZBX_FLAGS_PROXY_DIFF_UPDATE_SUPPRESS_WIN;

		zabbix_log(LOG_LEVEL_DEBUG, "Result of %s() flag_win:%d/%d flag:%d values_num:%d",
				__func__, proxy_diff.nodata_win.flags, flags_old, (int)proxy_diff.flags,
				proxy_diff.nodata_win.values_num);
	}

	if (ZBX_FLAGS_PROXY_DIFF_UNSET != proxy_diff.flags)
		zbx_dc_update_proxy(&proxy_diff);

	if (SUCCEED == zbx_json_brackets_by_name(jp, ZBX_PROTO_TAG_DISCOVERY_DATA, &jp_data))
	{
		if (SUCCEED != (ret = process_discovery_data_contents(&jp_data, &error_step)))
			zbx_strcatnl_alloc(error, &error_alloc, &error_offset, error_step);
	}

	if (SUCCEED == zbx_json_brackets_by_name(jp, ZBX_PROTO_TAG_AUTOREGISTRATION, &jp_data))
	{
		if (SUCCEED != (ret = process_autoregistration_contents(&jp_data, proxy->hostid, &error_step)))
			zbx_strcatnl_alloc(error, &error_alloc, &error_offset, error_step);
	}

	if (SUCCEED == zbx_json_brackets_by_name(jp, ZBX_PROTO_TAG_TASKS, &jp_data))
		process_tasks_contents(&jp_data);

	if (SUCCEED == zbx_json_brackets_by_name(jp, ZBX_PROTO_TAG_PROXY_ACTIVE_AVAIL_DATA, &jp_data))
	{
		const char			*ptr;
		zbx_vector_proxy_hostdata_ptr_t	host_avails;
		struct zbx_json_parse		jp_host;
		char				buffer[ZBX_KIBIBYTE];

		zbx_vector_proxy_hostdata_ptr_create(&host_avails);

		for (ptr = NULL; NULL != (ptr = zbx_json_next(&jp_data, ptr));)
		{
			zbx_proxy_hostdata_t	*host;

			if (SUCCEED != zbx_json_brackets_open(ptr, &jp_host))
				continue;

			if (SUCCEED == zbx_json_value_by_name(&jp_host, ZBX_PROTO_TAG_HOSTID, buffer, sizeof(buffer), NULL))
			{
				host = (zbx_proxy_hostdata_t *)zbx_malloc(NULL, sizeof(zbx_proxy_hostdata_t));
				host->hostid = atoi(buffer);
			}
			else
				continue;

			if (FAIL == zbx_json_value_by_name(&jp_host, ZBX_PROTO_TAG_ACTIVE_STATUS, buffer, sizeof(buffer), NULL))
			{
				zbx_free(host);
				continue;
			}

			host->status = atoi(buffer);

			zbx_vector_proxy_hostdata_ptr_append(&host_avails, host);
		}

		if (0 != host_avails.values_num)
		{
			unsigned char			*data = NULL;
			zbx_uint32_t			data_len;
			DC_HOST				*hosts;
			int				i, *errcodes;
			zbx_vector_uint64_t		hostids;
			zbx_vector_proxy_hostdata_ptr_t	proxy_host_avails;

			zbx_vector_uint64_create(&hostids);

			for (i = 0; i < host_avails.values_num; i++)
				zbx_vector_uint64_append(&hostids, host_avails.values[i]->hostid);

			hosts = (DC_HOST *)zbx_malloc(NULL, sizeof(DC_HOST) * host_avails.values_num);
			errcodes = (int *)zbx_malloc(NULL, sizeof(int) * host_avails.values_num);
			DCconfig_get_hosts_by_hostids(hosts, hostids.values, errcodes, hostids.values_num);

			zbx_vector_uint64_destroy(&hostids);

			zbx_vector_proxy_hostdata_ptr_create(&proxy_host_avails);

			for (i = 0; i < host_avails.values_num; i++)
			{
				if (SUCCEED == errcodes[i] && hosts[i].proxy_hostid == proxy->hostid)
					zbx_vector_proxy_hostdata_ptr_append(&proxy_host_avails, host_avails.values[i]);
			}

			zbx_free(errcodes);
			zbx_free(hosts);

			data_len = zbx_availability_serialize_proxy_hostdata(&data, &proxy_host_avails, proxy->hostid);
			zbx_availability_send(ZBX_IPC_AVAILMAN_PROCESS_PROXY_HOSTDATA, data, data_len, NULL);

			zbx_vector_proxy_hostdata_ptr_destroy(&proxy_host_avails);
			zbx_vector_proxy_hostdata_ptr_clear_ext(&host_avails, (zbx_proxy_hostdata_ptr_free_func_t)zbx_ptr_free);
			zbx_free(data);
		}

		zbx_vector_proxy_hostdata_ptr_destroy(&host_avails);
	}

out:
	zbx_free(error_step);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: flushes lastaccess changes for proxies every                      *
 *          ZBX_PROXY_LASTACCESS_UPDATE_FREQUENCY seconds                     *
 *                                                                            *
 ******************************************************************************/
static void	zbx_db_flush_proxy_lastaccess(void)
{
	zbx_vector_uint64_pair_t	lastaccess;

	zbx_vector_uint64_pair_create(&lastaccess);

	zbx_dc_get_proxy_lastaccess(&lastaccess);

	if (0 != lastaccess.values_num)
	{
		char	*sql;
		size_t	sql_alloc = 256, sql_offset = 0;
		int	i;

		sql = (char *)zbx_malloc(NULL, sql_alloc);

		zbx_db_begin();
		zbx_db_begin_multiple_update(&sql, &sql_alloc, &sql_offset);

		for (i = 0; i < lastaccess.values_num; i++)
		{
			zbx_uint64_pair_t	*pair = &lastaccess.values[i];

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update host_rtdata"
					" set lastaccess=%d"
					" where hostid=" ZBX_FS_UI64 ";\n",
					(int)pair->second, pair->first);

			zbx_db_execute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
		}

		zbx_db_end_multiple_update(&sql, &sql_alloc, &sql_offset);

		if (16 < sql_offset)	/* in ORACLE always present begin..end; */
			zbx_db_execute("%s", sql);

		zbx_db_commit();

		zbx_free(sql);
	}

	zbx_vector_uint64_pair_destroy(&lastaccess);
}

/******************************************************************************
 *                                                                            *
 * Purpose: updates proxy version and compatibility with server in database   *
 *                                                                            *
 * Parameters: proxy - [IN] the proxy to update version for                   *
 *             diff  - [IN] indicates changes to the proxy                    *
 *                                                                            *
 ******************************************************************************/
static void	db_update_proxy_version(DC_PROXY *proxy, zbx_proxy_diff_t *diff)
{
	if (0 != (diff->flags & ZBX_FLAGS_PROXY_DIFF_UPDATE_VERSION))
	{
		if (0 != proxy->version_int)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "proxy \"%s\" protocol version updated from %u.%u to %u.%u",
					proxy->host,
					ZBX_COMPONENT_VERSION_MAJOR(proxy->version_int),
					ZBX_COMPONENT_VERSION_MINOR(proxy->version_int),
					ZBX_COMPONENT_VERSION_MAJOR(diff->version_int),
					ZBX_COMPONENT_VERSION_MINOR(diff->version_int));
		}

		if (ZBX_DB_OK > zbx_db_execute(
				"update host_rtdata"
				" set version=%u,compatibility=%u"
				" where hostid=" ZBX_FS_UI64,
				ZBX_COMPONENT_VERSION_TO_DEC_FORMAT(diff->version_int), diff->compatibility,
						diff->hostid))
		{
			zabbix_log(LOG_LEVEL_WARNING, "Failed to update proxy version and compatibility with server for"
					" proxy '%s'.", proxy->host);
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: gets proxy version compatibility with server version              *
 *                                                                            *
 * Parameters: proxy_version - [IN] proxy_version                             *
 *                                                                            *
 * Return value: proxy version compatibility with server version              *
 *                                                                            *
 ******************************************************************************/
static zbx_proxy_compatibility_t	zbx_get_proxy_compatibility(int proxy_version)
{
#define SERVER_VERSION	ZBX_COMPONENT_VERSION(ZABBIX_VERSION_MAJOR, ZABBIX_VERSION_MINOR, 0)

	if (0 == proxy_version)
		return ZBX_PROXY_VERSION_UNDEFINED;

	proxy_version = ZBX_COMPONENT_VERSION_WITHOUT_PATCH(proxy_version);

	if (SERVER_VERSION == proxy_version)
		return ZBX_PROXY_VERSION_CURRENT;

	if (SERVER_VERSION < proxy_version)
		return ZBX_PROXY_VERSION_UNSUPPORTED;
#if (ZABBIX_VERSION_MINOR == 0)
	if (ZABBIX_VERSION_MAJOR == 1 + ZBX_COMPONENT_VERSION_MAJOR(proxy_version))
		return ZBX_PROXY_VERSION_STATUS_OUTDATED;
#elif (ZABBIX_VERSION_MINOR > 0)
	if (ZABBIX_VERSION_MAJOR == ZBX_COMPONENT_VERSION_MAJOR(proxy_version))
		return ZBX_PROXY_VERSION_OUTDATED;
#endif
	return ZBX_PROXY_VERSION_UNSUPPORTED;

#undef SERVER_VERSION
}

/******************************************************************************
 *                                                                            *
 * Purpose: updates proxy runtime properties in cache and database.           *
 *                                                                            *
 * Parameters: proxy       - [IN/OUT] the proxy                               *
 *             version_str - [IN] the proxy version as string                 *
 *             version_int - [IN] the proxy version in numeric representation *
 *             lastaccess  - [IN] the last proxy access time                  *
 *             compress    - [IN] 1 if proxy is using data compression,       *
 *                                0 otherwise                                 *
 *             flags_add   - [IN] additional flags for update proxy           *
 *                                                                            *
 * Comments: The proxy parameter properties are also updated.                 *
 *                                                                            *
 ******************************************************************************/
void	zbx_update_proxy_data(DC_PROXY *proxy, char *version_str, int version_int, int lastaccess, int compress,
		zbx_uint64_t flags_add)
{
	zbx_proxy_diff_t		diff;
	zbx_proxy_compatibility_t	compatibility;

	compatibility = zbx_get_proxy_compatibility(version_int);

	diff.hostid = proxy->hostid;
	diff.flags = ZBX_FLAGS_PROXY_DIFF_UPDATE | flags_add;
	diff.version_str = version_str;
	diff.version_int = version_int;
	diff.compatibility = compatibility;
	diff.lastaccess = lastaccess;
	diff.compress = compress;

	zbx_dc_update_proxy(&diff);

	db_update_proxy_version(proxy, &diff);

	zbx_strlcpy(proxy->version_str, version_str, sizeof(proxy->version_str));
	proxy->version_int = version_int;
	proxy->compatibility = compatibility;
	proxy->auto_compress = compress;
	proxy->lastaccess = lastaccess;

	if (0 != (diff.flags & ZBX_FLAGS_PROXY_DIFF_UPDATE_COMPRESS))
		zbx_db_execute("update hosts set auto_compress=%d where hostid=" ZBX_FS_UI64, diff.compress, diff.hostid);

	zbx_db_flush_proxy_lastaccess();
}
/******************************************************************************
 *                                                                            *
 * Purpose: flushes last_version_error_time changes runtime                   *
 *          variable for proxies structures                                   *
 *                                                                            *
 ******************************************************************************/
static void	zbx_update_proxy_lasterror(DC_PROXY *proxy)
{
	zbx_proxy_diff_t	diff;

	diff.hostid = proxy->hostid;
	diff.flags = ZBX_FLAGS_PROXY_DIFF_UPDATE_LASTERROR;
	diff.lastaccess = time(NULL);
	diff.last_version_error_time = proxy->last_version_error_time;

	zbx_dc_update_proxy(&diff);
}
/******************************************************************************
 *                                                                            *
 * Purpose: check server and proxy versions and compatibility rules           *
 *                                                                            *
 * Parameters:                                                                *
 *     proxy        - [IN] the source proxy                                   *
 *     version      - [IN] the version of proxy                               *
 *                                                                            *
 * Return value:                                                              *
 *     SUCCEED - no compatibility issue                                       *
 *     FAIL    - compatibility check fault                                    *
 *                                                                            *
 ******************************************************************************/
int	zbx_check_protocol_version(DC_PROXY *proxy, int version)
{
	zbx_proxy_compatibility_t	compatibility;

	compatibility = zbx_get_proxy_compatibility(version);

	/* warn if another proxy version is used and proceed with compatibility rules*/
	if (ZBX_PROXY_VERSION_CURRENT != compatibility)
	{
		int	now = zbx_time(), print_log = 0;

		if (proxy->last_version_error_time <= now)
		{
			print_log = 1;
			proxy->last_version_error_time = now + 5 * SEC_PER_MIN;
			zbx_update_proxy_lasterror(proxy);
		}

		if (ZBX_PROXY_VERSION_UNSUPPORTED == compatibility)
		{
			if (1 == print_log)
			{
				zabbix_log(LOG_LEVEL_WARNING, "Proxy \"%s\" version %u.%u.%u is not supported by server"
						" version %d.%d.%d.", proxy->host,
						ZBX_COMPONENT_VERSION_MAJOR(version),
						ZBX_COMPONENT_VERSION_MINOR(version),
						ZBX_COMPONENT_VERSION_PATCH(version), ZABBIX_VERSION_MAJOR,
						ZABBIX_VERSION_MINOR, ZABBIX_VERSION_PATCH);
			}
			return FAIL;
		}
		else if (ZBX_PROXY_VERSION_OUTDATED == compatibility && 1 == print_log)
		{
			zabbix_log(LOG_LEVEL_WARNING, "Proxy \"%s\" version %u.%u.%u is outdated, only data collection"
					" and remote execution is available with server version %d.%d.%d.", proxy->host,
					ZBX_COMPONENT_VERSION_MAJOR(version), ZBX_COMPONENT_VERSION_MINOR(version),
					ZBX_COMPONENT_VERSION_PATCH(version), ZABBIX_VERSION_MAJOR,
					ZABBIX_VERSION_MINOR, ZABBIX_VERSION_PATCH);
		}
		else if (ZBX_PROXY_VERSION_UNDEFINED == compatibility)
			return FAIL;
	}

	return SUCCEED;
}

