/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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

#include "common.h"
#include "db.h"
#include "log.h"
#include "sysinfo.h"
#include "zbxserver.h"

#include "proxy.h"
#include "dbcache.h"
#include "discovery.h"
#include "zbxalgo.h"
#include "../zbxcrypto/tls_tcp_active.h"

/* the space reserved in json buffer to hold at least one record plus service data */
#define ZBX_DATA_JSON_RESERVED  	(HISTORY_TEXT_VALUE_LEN * 4 + ZBX_KIBIBYTE * 4)

#define ZBX_DATA_JSON_RECORD_LIMIT 	(ZBX_MAX_RECV_DATA_SIZE - ZBX_DATA_JSON_RESERVED)
#define ZBX_DATA_JSON_BATCH_LIMIT	((ZBX_MAX_RECV_DATA_SIZE - ZBX_DATA_JSON_RESERVED) / 2)

/* the maximum number of values processed in one batch */
#define ZBX_HISTORY_VALUES_MAX		256

extern unsigned int	configured_tls_accept_modes;

typedef struct
{
	const char		*field;
	const char		*tag;
	zbx_json_type_t		jt;
	char			*default_value;
}
zbx_history_field_t;

typedef struct
{
	const char		*table, *lastidfield;
	zbx_history_field_t	fields[ZBX_MAX_FIELDS];
}
zbx_history_table_t;

typedef struct
{
	zbx_uint64_t	id;
	size_t		offset;
}
zbx_id_offset_t;


typedef int (*zbx_client_item_validator_t)(DC_ITEM *item, zbx_socket_t *sock, void *args, char **error);

typedef struct
{
	zbx_uint64_t	hostid;
	int		value;
}
zbx_host_rights_t;

static zbx_history_table_t dht = {
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

static zbx_history_table_t areg = {
	"proxy_autoreg_host", "autoreg_host_lastid",
		{
		{"clock",		ZBX_PROTO_TAG_CLOCK,		ZBX_JSON_TYPE_INT,	NULL},
		{"host",		ZBX_PROTO_TAG_HOST,		ZBX_JSON_TYPE_STRING,	NULL},
		{"listen_ip",		ZBX_PROTO_TAG_IP,		ZBX_JSON_TYPE_STRING,	""},
		{"listen_dns",		ZBX_PROTO_TAG_DNS,		ZBX_JSON_TYPE_STRING,	""},
		{"listen_port",		ZBX_PROTO_TAG_PORT,		ZBX_JSON_TYPE_STRING,	"0"},
		{"host_metadata",	ZBX_PROTO_TAG_HOST_METADATA,	ZBX_JSON_TYPE_STRING,	""},
		{NULL}
		}
};

static const char	*availability_tag_available[ZBX_AGENT_MAX] = {ZBX_PROTO_TAG_AVAILABLE,
					ZBX_PROTO_TAG_SNMP_AVAILABLE, ZBX_PROTO_TAG_IPMI_AVAILABLE,
					ZBX_PROTO_TAG_JMX_AVAILABLE};
static const char	*availability_tag_error[ZBX_AGENT_MAX] = {ZBX_PROTO_TAG_ERROR,
					ZBX_PROTO_TAG_SNMP_ERROR, ZBX_PROTO_TAG_IPMI_ERROR,
					ZBX_PROTO_TAG_JMX_ERROR};

/******************************************************************************
 *                                                                            *
 * Function: zbx_proxy_check_permissions                                      *
 *                                                                            *
 * Purpose: checks proxy connection permissions (encryption configuration)    *
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
#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
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
	else if (ZBX_TCP_SEC_TLS_PSK == sock->connection_type)
	{
		if (SUCCEED != zbx_tls_get_attr_psk(sock, &attr))
		{
			*error = zbx_strdup(*error, "internal error: cannot get connection attributes");
			THIS_SHOULD_NEVER_HAPPEN;
			return FAIL;
		}
	}
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
				zbx_tls_connection_type_name(sock->connection_type), proxy->host);
		return FAIL;
	}

#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
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
	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_host_check_permissions                                       *
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
int	zbx_host_check_permissions(const DC_HOST *host, const zbx_socket_t *sock, char **error)
{
#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
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
	else if (ZBX_TCP_SEC_TLS_PSK == sock->connection_type)
	{
		if (SUCCEED != zbx_tls_get_attr_psk(sock, &attr))
		{
			*error = zbx_strdup(*error, "internal error: cannot get connection attributes");
			THIS_SHOULD_NEVER_HAPPEN;
			return FAIL;
		}
	}
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
				zbx_tls_connection_type_name(sock->connection_type), host->host);
		return FAIL;
	}

#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
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
	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: get_active_proxy_from_request                                    *
 *                                                                            *
 * Purpose:                                                                   *
 *     Extract a proxy name from JSON and find the proxy ID in configuration  *
 *     cache, and check access rights. The proxy must be configured in active *
 *     mode.                                                                  *
 *                                                                            *
 * Parameters:                                                                *
 *     jp      - [IN] JSON with the proxy name                                *
 *     sock    - [IN] connection socket context                               *
 *     proxy   - [OUT] the proxy data                                         *
 *     error   - [OUT] error message                                          *
 *                                                                            *
 * Return value:                                                              *
 *     SUCCEED - proxy ID was found in database                               *
 *     FAIL    - an error occurred (e.g. an unknown proxy, the proxy is       *
 *               configured in passive mode or access denied)                 *
 *                                                                            *
 ******************************************************************************/
int	get_active_proxy_from_request(struct zbx_json_parse *jp, const zbx_socket_t *sock, DC_PROXY *proxy,
		char **error)
{
	char	*ch_error, host[HOST_HOST_LEN_MAX];

	if (SUCCEED != zbx_json_value_by_name(jp, ZBX_PROTO_TAG_HOST, host, HOST_HOST_LEN_MAX))
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

	if (SUCCEED != zbx_dc_get_active_proxy_by_name(host, proxy, error))
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: check_access_passive_proxy                                       *
 *                                                                            *
 * Purpose:                                                                   *
 *     Check access rights to a passive proxy for the given connection and    *
 *     send a response if denied.                                             *
 *                                                                            *
 * Parameters:                                                                *
 *     sock          - [IN] connection socket context                         *
 *     send_response - [IN] to send or not to send a response to server.      *
 *                          Value: ZBX_SEND_RESPONSE or                       *
 *                          ZBX_DO_NOT_SEND_RESPONSE                          *
 *     req           - [IN] request, included into error message              *
 *                                                                            *
 * Return value:                                                              *
 *     SUCCEED - access is allowed                                            *
 *     FAIL    - access is denied                                             *
 *                                                                            *
 ******************************************************************************/
int	check_access_passive_proxy(zbx_socket_t *sock, int send_response, const char *req)
{
	char	*msg = NULL;

	if (0 == (configured_tls_accept_modes & sock->connection_type))
	{
		msg = zbx_dsprintf(NULL, "%s from server over connection of type \"%s\" is not allowed", req,
				zbx_tls_connection_type_name(sock->connection_type));

		zabbix_log(LOG_LEVEL_WARNING, "%s by proxy configuration parameter \"TLSAccept\"", msg);

		if (ZBX_SEND_RESPONSE == send_response)
			zbx_send_proxy_response(sock, FAIL, msg, CONFIG_TIMEOUT);

		zbx_free(msg);
		return FAIL;
	}

#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	if (ZBX_TCP_SEC_TLS_CERT == sock->connection_type && SUCCEED != zbx_check_server_issuer_subject(sock, &msg))
	{
		zabbix_log(LOG_LEVEL_WARNING, "%s from server is not allowed: %s", req, msg);

		if (ZBX_SEND_RESPONSE == send_response)
			zbx_send_proxy_response(sock, FAIL, "certificate issuer or subject mismatch", CONFIG_TIMEOUT);

		zbx_free(msg);
		return FAIL;
	}
#endif
	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: update_proxy_lastaccess                                          *
 *                                                                            *
 ******************************************************************************/
void	update_proxy_lastaccess(const zbx_uint64_t hostid)
{
	DBexecute("update hosts set lastaccess=%d where hostid=" ZBX_FS_UI64, time(NULL), hostid);
}

/******************************************************************************
 *                                                                            *
 * Function: get_proxyconfig_table                                            *
 *                                                                            *
 * Purpose: prepare proxy configuration data                                  *
 *                                                                            *
 ******************************************************************************/
static int	get_proxyconfig_table(zbx_uint64_t proxy_hostid, struct zbx_json *j, const ZBX_TABLE *table,
		zbx_vector_uint64_t *hosts, zbx_vector_uint64_t *httptests)
{
	const char		*__function_name = "get_proxyconfig_table";

	char			*sql = NULL;
	size_t			sql_alloc = 4 * ZBX_KIBIBYTE, sql_offset = 0;
	int			f, fld, fld_type = -1, fld_key = -1, ret = SUCCEED;
	DB_RESULT		result;
	DB_ROW			row;
	static const ZBX_TABLE	*table_items = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() proxy_hostid:" ZBX_FS_UI64 " table:'%s'",
			__function_name, proxy_hostid, table->table);

	if (NULL == table_items)
		table_items = DBget_table("items");

	zbx_json_addobject(j, table->table);
	zbx_json_addarray(j, "fields");

	sql = zbx_malloc(sql, sql_alloc);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "select t.%s", table->recid);

	zbx_json_addstring(j, NULL, table->recid, ZBX_JSON_TYPE_STRING);

	for (f = 0, fld = 1; 0 != table->fields[f].name; f++)
	{
		if (0 == (table->fields[f].flags & ZBX_PROXY))
			continue;

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ",t.");
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, table->fields[f].name);

		zbx_json_addstring(j, NULL, table->fields[f].name, ZBX_JSON_TYPE_STRING);

		if (table == table_items)
		{
			if (0 == strcmp(table->fields[f].name, "type"))
				fld_type = fld;
			else if (0 == strcmp(table->fields[f].name, "key_"))
				fld_key = fld;

			fld++;
		}
	}

	if (table == table_items && (-1 == fld_type || -1 == fld_key))
	{
		THIS_SHOULD_NEVER_HAPPEN;
		exit(EXIT_FAILURE);
	}

	zbx_json_close(j);	/* fields */

	zbx_json_addarray(j, "data");

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " from %s t", table->table);

	if (SUCCEED == str_in_list("hosts,interface,hosts_templates,hostmacro", table->table, ','))
	{
		if (0 == hosts->values_num)
			goto skip_data;

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "t.hostid", hosts->values, hosts->values_num);
	}
	else if (table == table_items)
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				",hosts r where t.hostid=r.hostid"
					" and r.proxy_hostid=" ZBX_FS_UI64
					" and r.status in (%d,%d)"
					" and t.type in (%d,%d,%d,%d,%d,%d,%d,%d,%d,%d,%d,%d,%d,%d,%d,%d)",
				proxy_hostid,
				HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED,
				ITEM_TYPE_ZABBIX, ITEM_TYPE_ZABBIX_ACTIVE, ITEM_TYPE_SNMPv1, ITEM_TYPE_SNMPv2c,
				ITEM_TYPE_SNMPv3, ITEM_TYPE_IPMI, ITEM_TYPE_TRAPPER, ITEM_TYPE_SIMPLE,
				ITEM_TYPE_HTTPTEST, ITEM_TYPE_EXTERNAL, ITEM_TYPE_DB_MONITOR, ITEM_TYPE_SSH,
				ITEM_TYPE_TELNET, ITEM_TYPE_JMX, ITEM_TYPE_SNMPTRAP, ITEM_TYPE_INTERNAL);
	}
	else if (0 == strcmp(table->table, "drules"))
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				" where t.proxy_hostid=" ZBX_FS_UI64
					" and t.status=%d",
				proxy_hostid, DRULE_STATUS_MONITORED);
	}
	else if (0 == strcmp(table->table, "dchecks"))
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				",drules r where t.druleid=r.druleid"
					" and r.proxy_hostid=" ZBX_FS_UI64
					" and r.status=%d",
				proxy_hostid, DRULE_STATUS_MONITORED);
	}
	else if (0 == strcmp(table->table, "groups"))
	{
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ",config r where t.groupid=r.discovery_groupid");
	}
	else if (SUCCEED == str_in_list("httptest,httptestitem,httpstep", table->table, ','))
	{
		if (0 == httptests->values_num)
			goto skip_data;

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "t.httptestid",
				httptests->values, httptests->values_num);
	}
	else if (0 == strcmp(table->table, "httpstepitem"))
	{
		if (0 == httptests->values_num)
			goto skip_data;

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				",httpstep r where t.httpstepid=r.httpstepid"
					" and");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "r.httptestid",
				httptests->values, httptests->values_num);
	}

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " order by t.");
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, table->recid);

	if (NULL == (result = DBselect("%s", sql)))
	{
		ret = FAIL;
		goto skip_data;
	}

	while (NULL != (row = DBfetch(result)))
	{
		if (table == table_items)
		{
			unsigned char	type;

			ZBX_STR2UCHAR(type, row[fld_type]);

			if (SUCCEED == is_item_processed_by_server(type, row[fld_key]))
				continue;
		}

		fld = 0;
		zbx_json_addarray(j, NULL);
		zbx_json_addstring(j, NULL, row[fld++], ZBX_JSON_TYPE_INT);

		for (f = 0; 0 != table->fields[f].name; f++)
		{
			if (0 == (table->fields[f].flags & ZBX_PROXY))
				continue;

			switch (table->fields[f].type)
			{
				case ZBX_TYPE_INT:
				case ZBX_TYPE_UINT:
				case ZBX_TYPE_ID:
					if (SUCCEED != DBis_null(row[fld]))
						zbx_json_addstring(j, NULL, row[fld], ZBX_JSON_TYPE_INT);
					else
						zbx_json_addstring(j, NULL, NULL, ZBX_JSON_TYPE_NULL);
					break;
				default:
					zbx_json_addstring(j, NULL, row[fld], ZBX_JSON_TYPE_STRING);
					break;
			}

			fld++;
		}
		zbx_json_close(j);
	}
	DBfree_result(result);
skip_data:
	zbx_free(sql);

	zbx_json_close(j);	/* data */
	zbx_json_close(j);	/* table->table */

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

static void	get_proxy_monitored_hosts(zbx_uint64_t proxy_hostid, zbx_vector_uint64_t *hosts)
{
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	hostid, *ids = NULL;
	int		ids_alloc = 0, ids_num = 0;
	char		*sql = NULL;
	size_t		sql_alloc = 512, sql_offset;

	sql = zbx_malloc(sql, sql_alloc * sizeof(char));

	result = DBselect(
			"select hostid"
			" from hosts"
			" where proxy_hostid=" ZBX_FS_UI64
				" and status in (%d,%d)"
				" and flags<>%d",
			proxy_hostid, HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED, ZBX_FLAG_DISCOVERY_PROTOTYPE);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(hostid, row[0]);

		zbx_vector_uint64_append(hosts, hostid);
		uint64_array_add(&ids, &ids_alloc, &ids_num, hostid, 64);
	}
	DBfree_result(result);

	while (0 != ids_num)
	{
		sql_offset = 0;
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				"select distinct templateid"
				" from hosts_templates"
				" where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid", ids, ids_num);

		ids_num = 0;

		result = DBselect("%s", sql);

		while (NULL != (row = DBfetch(result)))
		{
			ZBX_STR2UINT64(hostid, row[0]);

			zbx_vector_uint64_append(hosts, hostid);
			uint64_array_add(&ids, &ids_alloc, &ids_num, hostid, 64);
		}
		DBfree_result(result);
	}

	zbx_free(ids);
	zbx_free(sql);

	zbx_vector_uint64_sort(hosts, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
}

static void	get_proxy_monitored_httptests(zbx_uint64_t proxy_hostid, zbx_vector_uint64_t *httptests)
{
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	httptestid;

	result = DBselect(
			"select httptestid"
			" from httptest t,hosts h"
			" where t.hostid=h.hostid"
				" and t.status=%d"
				" and h.proxy_hostid=" ZBX_FS_UI64
				" and h.status=%d",
			HTTPTEST_STATUS_MONITORED, proxy_hostid, HOST_STATUS_MONITORED);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(httptestid, row[0]);

		zbx_vector_uint64_append(httptests, httptestid);
	}
	DBfree_result(result);

	zbx_vector_uint64_sort(httptests, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
}

/******************************************************************************
 *                                                                            *
 * Function: get_proxyconfig_data                                             *
 *                                                                            *
 * Purpose: prepare proxy configuration data                                  *
 *                                                                            *
 ******************************************************************************/
int	get_proxyconfig_data(zbx_uint64_t proxy_hostid, struct zbx_json *j, char **error)
{
	static const char	*proxytable[] =
	{
		"globalmacro",
		"hosts",
		"interface",
		"hosts_templates",
		"hostmacro",
		"items",
		"drules",
		"dchecks",
		"regexps",
		"expressions",
		"groups",
		"config",
		"httptest",
		"httptestitem",
		"httpstep",
		"httpstepitem",
		NULL
	};

	const char		*__function_name = "get_proxyconfig_data";

	int			i, ret = FAIL;
	const ZBX_TABLE		*table;
	zbx_vector_uint64_t	hosts, httptests;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() proxy_hostid:" ZBX_FS_UI64, __function_name, proxy_hostid);

	assert(proxy_hostid);

	zbx_vector_uint64_create(&hosts);
	zbx_vector_uint64_create(&httptests);

	get_proxy_monitored_hosts(proxy_hostid, &hosts);
	get_proxy_monitored_httptests(proxy_hostid, &httptests);

	for (i = 0; NULL != proxytable[i]; i++)
	{
		table = DBget_table(proxytable[i]);
		assert(NULL != table);

		if (SUCCEED != get_proxyconfig_table(proxy_hostid, j, table, &hosts, &httptests))
		{
			*error = zbx_dsprintf(*error, "failed to get data from table \"%s\"", table->table);
			goto out;
		}
	}

	ret = SUCCEED;
out:
	zbx_vector_uint64_destroy(&httptests);
	zbx_vector_uint64_destroy(&hosts);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: remember_record                                                  *
 *                                                                            *
 * Purpose: A record is stored as a sequence of fields and flag bytes for     *
 *          handling NULL values. A field is stored as a null-terminated      *
 *          string to preserve field boundaries. If a field value can be NULL *
 *          a flag byte is inserted after the field to distinguish between    *
 *          empty string and NULL value. The flag byte can be '\1'            *
 *          (not NULL value) or '\2' (NULL value).                            *
 *                                                                            *
 * Examples of representation:                                                *
 *          \0\2    - the field can be NULL and it is NULL                    *
 *          \0\1    - the field can be NULL but is empty string               *
 *          abc\0\1 - the field can be NULL but is a string "abc"             *
 *          \0      - the field can not be NULL and is empty string           *
 *          abc\0   - the field can not be NULL and is a string "abc"         *
 *                                                                            *
 ******************************************************************************/
static void	remember_record(const ZBX_FIELD **fields, int fields_count, char **recs, size_t *recs_alloc,
		size_t *recs_offset, DB_ROW row)
{
	int	f;

	for (f = 0; f < fields_count; f++)
	{
		if (0 != (fields[f]->flags & ZBX_NOTNULL))
		{
			zbx_strcpy_alloc(recs, recs_alloc, recs_offset, row[f]);
			*recs_offset += sizeof(char);
		}
		else if (SUCCEED != DBis_null(row[f]))
		{
			zbx_strcpy_alloc(recs, recs_alloc, recs_offset, row[f]);
			*recs_offset += sizeof(char);
			zbx_chrcpy_alloc(recs, recs_alloc, recs_offset, '\1');
		}
		else
		{
			zbx_strcpy_alloc(recs, recs_alloc, recs_offset, "");
			*recs_offset += sizeof(char);
			zbx_chrcpy_alloc(recs, recs_alloc, recs_offset, '\2');
		}
	}
}

static zbx_hash_t	id_offset_hash_func(const void *data)
{
	const zbx_id_offset_t *p = data;

	return ZBX_DEFAULT_UINT64_HASH_ALGO(&p->id, sizeof(zbx_uint64_t), ZBX_DEFAULT_HASH_SEED);
}

static int	id_offset_compare_func(const void *d1, const void *d2)
{
	const zbx_id_offset_t *p1 = d1, *p2 = d2;

	return ZBX_DEFAULT_UINT64_COMPARE_FUNC(&p1->id, &p2->id);
}

/******************************************************************************
 *                                                                            *
 * Function: find_field_by_name                                               *
 *                                                                            *
 * Purpose: find a number of the field                                        *
 *                                                                            *
 ******************************************************************************/
static int	find_field_by_name(const ZBX_FIELD **fields, int fields_count, const char *field_name)
{
	int	f;

	for (f = 0; f < fields_count; f++)
	{
		if (0 == strcmp(fields[f]->name, field_name))
			break;
	}

	return f;
}

/******************************************************************************
 *                                                                            *
 * Function: compare_nth_field                                                *
 *                                                                            *
 * Purpose: This function compares a value from JSON record with the value    *
 *          of the n-th field of DB record. For description how DB record is  *
 *          stored in memory see comments in function remember_record().      *
 *                                                                            *
 * Comparing deals with 4 cases:                                              *
 *          - JSON value is not NULL, DB value is not NULL                    *
 *          - JSON value is not NULL, DB value is NULL                        *
 *          - JSON value is NULL, DB value is NULL                            *
 *          - JSON value is NULL, DB value is not NULL                        *
 *                                                                            *
 ******************************************************************************/
static int	compare_nth_field(const ZBX_FIELD **fields, const char *rec_data, int n, const char *str, int is_null,
		int *last_n, size_t *last_pos)
{
	int		i = *last_n, null_in_db = 0;
	const char	*p = rec_data + *last_pos, *field_start = NULL;

	do	/* find starting position of the n-th field */
	{
		field_start = p;
		while ('\0' != *p++)
			;

		null_in_db = 0;

		if (0 == (fields[i++]->flags & ZBX_NOTNULL))	/* field could be NULL */
		{
			if ('\2' == *p && (rec_data == p - 1 || '\0' == *(p - 2) || '\1' == *(p - 2) ||
					'\2' == *(p - 2)))	/* field value is NULL */
			{
				null_in_db = 1;
				p++;
			}
			else if ('\1' == *p)
			{
				p++;
			}
			else
			{
				THIS_SHOULD_NEVER_HAPPEN;
				*last_n = 0;
				*last_pos = 0;
				return 1;
			}
		}
	}
	while (n >= i);

	*last_n = i;				/* preserve number of field and its start position */
	*last_pos = (size_t)(p - rec_data);	/* across calls to avoid searching from start */

	if (0 == is_null)	/* value in JSON is not NULL */
	{
		if (0 == null_in_db)
			return strcmp(field_start, str);
		else
			return 1;
	}
	else
	{
		if ('\0' == *str)
		{
			if (1 == null_in_db)
				return 0;	/* fields are "equal" - both contain NULL */
			else
				return 1;
		}
		else
		{
			THIS_SHOULD_NEVER_HAPPEN;
			*last_n = 0;
			*last_pos = 0;
			return 1;
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Function: process_proxyconfig_table                                        *
 *                                                                            *
 * Purpose: update configuration table                                        *
 *                                                                            *
 * Return value: SUCCEED - processed successfully                             *
 *               FAIL - an error occurred                                     *
 *                                                                            *
 ******************************************************************************/
static int	process_proxyconfig_table(const ZBX_TABLE *table, struct zbx_json_parse *jp_obj,
		zbx_vector_uint64_t *del, char **error)
{
	const char		*__function_name = "process_proxyconfig_table";

	int			f, fields_count = 0, insert, is_null, i, ret = FAIL, id_field_nr = 0, move_out = 0,
				move_field_nr = 0;
	const ZBX_FIELD		*fields[ZBX_MAX_FIELDS];
	struct zbx_json_parse	jp_data, jp_row;
	const char		*p, *pf;
	zbx_uint64_t		recid, *p_recid = NULL;
	zbx_vector_uint64_t	ins, moves;
	char			*buf = NULL, *esc, *sql = NULL, *recs = NULL;
	size_t			sql_alloc = 4 * ZBX_KIBIBYTE, sql_offset,
				recs_alloc = 20 * ZBX_KIBIBYTE, recs_offset = 0,
				buf_alloc = 0;
	DB_RESULT		result;
	DB_ROW			row;
	zbx_hashset_t           h_id_offsets, h_del;
	zbx_hashset_iter_t	iter;
	zbx_id_offset_t		id_offset, *p_id_offset = NULL;
	zbx_db_insert_t		db_insert;
	zbx_vector_ptr_t	values;
	static zbx_vector_ptr_t	skip_fields;
	static const ZBX_TABLE	*table_items = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() table:'%s'", __function_name, table->table);

	/************************************************************************************/
	/* T1. RECEIVED JSON (jp_obj) DATA FORMAT                                           */
	/************************************************************************************/
	/* Line |                  Data                     | Corresponding structure in DB */
	/* -----+-------------------------------------------+------------------------------ */
	/*   1  | {                                         |                               */
	/*   2  |         "hosts": {                        | first table                   */
	/*   3  |                 "fields": [               | list of table's columns       */
	/*   4  |                         "hostid",         | first column                  */
	/*   5  |                         "host",           | second column                 */
	/*   6  |                         ...               | ...columns                    */
	/*   7  |                 ],                        |                               */
	/*   8  |                 "data": [                 | the table data                */
	/*   9  |                         [                 | first entry                   */
	/*  10  |                               1,          | value for first column        */
	/*  11  |                               "zbx01",    | value for second column       */
	/*  12  |                               ...         | ...values                     */
	/*  13  |                         ],                |                               */
	/*  14  |                         [                 | second entry                  */
	/*  15  |                               2,          | value for first column        */
	/*  16  |                               "zbx02",    | value for second column       */
	/*  17  |                               ...         | ...values                     */
	/*  18  |                         ],                |                               */
	/*  19  |                         ...               | ...entries                    */
	/*  20  |                 ]                         |                               */
	/*  21  |         },                                |                               */
	/*  22  |         "items": {                        | second table                  */
	/*  23  |                 ...                       | ...                           */
	/*  24  |         },                                |                               */
	/*  25  |         ...                               | ...tables                     */
	/*  26  | }                                         |                               */
	/************************************************************************************/

	if (NULL == table_items)
	{
		table_items = DBget_table("items");

		/* do not update existing lastlogsize and mtime fields */
		zbx_vector_ptr_create(&skip_fields);
		zbx_vector_ptr_append(&skip_fields, (void *)DBget_field(table_items, "lastlogsize"));
		zbx_vector_ptr_append(&skip_fields, (void *)DBget_field(table_items, "mtime"));
		zbx_vector_ptr_sort(&skip_fields, ZBX_DEFAULT_PTR_COMPARE_FUNC);
	}

	/* get table columns (line 3 in T1) */
	if (FAIL == zbx_json_brackets_by_name(jp_obj, "fields", &jp_data))
	{
		*error = zbx_strdup(*error, zbx_json_strerror());
		goto out;
	}

	p = NULL;
	/* iterate column names (lines 4-6 in T1) */
	while (NULL != (p = zbx_json_next_value_dyn(&jp_data, p, &buf, &buf_alloc, NULL)))
	{
		if (NULL == (fields[fields_count++] = DBget_field(table, buf)))
		{
			*error = zbx_dsprintf(*error, "invalid field name \"%s.%s\"", table->table, buf);
			goto out;
		}
	}

	/* get the entries (line 8 in T1) */
	if (FAIL == zbx_json_brackets_by_name(jp_obj, ZBX_PROTO_TAG_DATA, &jp_data))
	{
		*error = zbx_strdup(*error, zbx_json_strerror());
		goto out;
	}

	/* all records will be stored in one large string */
	recs = zbx_malloc(recs, recs_alloc);

	/* hash set as index for fast access to records via IDs */
	zbx_hashset_create(&h_id_offsets, 10000, id_offset_hash_func, id_offset_compare_func);

	/* a hash set as a list for finding records to be deleted */
	zbx_hashset_create(&h_del, 10000, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	sql = zbx_malloc(sql, sql_alloc);

	sql_offset = 0;
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select ");

	/* make a string with a list of fields for SELECT */
	for (f = 0; f < fields_count; f++)
	{
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, fields[f]->name);
		zbx_chrcpy_alloc(&sql, &sql_alloc, &sql_offset, ',');
	}

	sql_offset--;
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " from ");
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, table->table);

	/* Find a number of the ID field. Usually the 1st field. */
	id_field_nr = find_field_by_name(fields, fields_count, table->recid);

	/* select all existing records */
	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(recid, row[id_field_nr]);

		id_offset.id = recid;
		id_offset.offset = recs_offset;

		zbx_hashset_insert(&h_id_offsets, &id_offset, sizeof(id_offset));
		zbx_hashset_insert(&h_del, &recid, sizeof(recid));

		remember_record(fields, fields_count, &recs, &recs_alloc, &recs_offset, row);
	}
	DBfree_result(result);

	/* these tables have unique indices, need special preparation to avoid conflicts during inserts/updates */
	if (0 == strcmp("globalmacro", table->table))
	{
		move_out = 1;
		move_field_nr = find_field_by_name(fields, fields_count, "macro");
	}
	else if (0 == strcmp("hosts_templates", table->table))
	{
		move_out = 1;
		move_field_nr = find_field_by_name(fields, fields_count, "templateid");
	}
	else if (0 == strcmp("hostmacro", table->table))
	{
		move_out = 1;
		move_field_nr = find_field_by_name(fields, fields_count, "macro");
	}
	else if (0 == strcmp("items", table->table))
	{
		move_out = 1;
		move_field_nr = find_field_by_name(fields, fields_count, "key_");
	}
	else if (0 == strcmp("drules", table->table))
	{
		move_out = 1;
		move_field_nr = find_field_by_name(fields, fields_count, "name");
	}
	else if (0 == strcmp("regexps", table->table))
	{
		move_out = 1;
		move_field_nr = find_field_by_name(fields, fields_count, "name");
	}
	else if (0 == strcmp("httptest", table->table))
	{
		move_out = 1;
		move_field_nr = find_field_by_name(fields, fields_count, "name");
	}

	zbx_vector_uint64_create(&ins);

	if (1 == move_out)
		zbx_vector_uint64_create(&moves);

	p = NULL;
	/* iterate the entries (lines 9, 14 and 19 in T1) */
	while (NULL != (p = zbx_json_next(&jp_data, p)))
	{
		if (FAIL == zbx_json_brackets_open(p, &jp_row) ||
				NULL == (pf = zbx_json_next_value_dyn(&jp_row, NULL, &buf, &buf_alloc, NULL)))
		{
			*error = zbx_strdup(*error, zbx_json_strerror());
			goto clean2;
		}

		/* check whether we need to update existing entry or insert a new one */

		ZBX_STR2UINT64(recid, buf);

		if (NULL != zbx_hashset_search(&h_del, &recid))
		{
			zbx_hashset_remove(&h_del, &recid);

			if (1 == move_out)
			{
				int	last_n = 0;
				size_t	last_pos = 0;

				/* locate a copy of this record as found in database */
				id_offset.id = recid;
				if (NULL == (p_id_offset = zbx_hashset_search(&h_id_offsets, &id_offset)))
				{
					THIS_SHOULD_NEVER_HAPPEN;
					goto clean2;
				}

				/* find the field requiring special preprocessing in JSON record */
				f = 1;
				while (NULL != (pf = zbx_json_next_value_dyn(&jp_row, pf, &buf, &buf_alloc, &is_null)))
				{
					/* parse values for the entry (lines 10-12 in T1) */

					if (fields_count == f)
					{
						*error = zbx_dsprintf(*error, "invalid number of fields \"%.*s\"",
								(int)(jp_row.end - jp_row.start + 1), jp_row.start);
						goto clean2;
					}

					if (move_field_nr == f)
						break;
					f++;
				}

				if (0 != compare_nth_field(fields, recs + p_id_offset->offset, move_field_nr, buf,
						is_null, &last_n, &last_pos))
				{
					zbx_vector_uint64_append(&moves, recid);
				}
			}
		}
		else
			zbx_vector_uint64_append(&ins, recid);
	}

	/* copy IDs of records to be deleted from hash set to vector */
	zbx_hashset_iter_reset(&h_del, &iter);
	while (NULL != (p_recid = zbx_hashset_iter_next(&iter)))
		zbx_vector_uint64_append(del, *p_recid);
	zbx_vector_uint64_sort(del, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_vector_uint64_sort(&ins, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	if (1 == move_out)
	{
		/* special preprocessing for 'hosts_templates' table to eliminate conflicts */
		/* in the 'hostid, templateid' unique index */
		if (0 == strcmp("hosts_templates", table->table))
		{
			/* Making the 'hostid, templateid' combination unique to avoid collisions when new records */
			/* are inserted and existing ones are updated is a bit complex. Let's take a simpler approach */
			/* - delete affected old records and insert the new ones. */
			for (i = 0; i < moves.values_num; i++)
			{
				zbx_vector_uint64_append(del, moves.values[i]);
				zbx_vector_uint64_append(&ins, moves.values[i]);
			}

			if (0 < moves.values_num)
			{
				zbx_vector_uint64_clear(&moves);
				zbx_vector_uint64_sort(del, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
				zbx_vector_uint64_sort(&ins, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
			}
		}

		if (0 != del->values_num)
		{
			sql_offset = 0;
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "delete from %s where", table->table);
			DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, table->recid, del->values,
					del->values_num);

			if (ZBX_DB_OK > DBexecute("%s", sql))
				goto clean2;

			zbx_vector_uint64_clear(del);
		}

		/* special preprocessing for 'globalmacro', 'hostmacro', 'items', 'drules', 'regexps' and 'httptest' */
		/* tables to eliminate conflicts */
		/* in the 'macro', 'hostid,macro', 'hostid,key_', 'name', 'name' and 'hostid,name' unique indices */
		if (1 < moves.values_num)
		{
			sql_offset = 0;
#ifdef HAVE_MYSQL
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update %s set %s=concat('#',%s) where",
					table->table, fields[move_field_nr]->name, table->recid);
#else
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update %s set %s='#'||%s where",
					table->table, fields[move_field_nr]->name, table->recid);
#endif
			zbx_vector_uint64_sort(&moves, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
			DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, table->recid, moves.values,
					moves.values_num);

			if (ZBX_DB_OK > DBexecute("%s", sql))
				goto clean2;
		}
	}

	if (0 != ins.values_num)
	{
		zbx_vector_ptr_create(&values);

		zbx_db_insert_prepare_dyn(&db_insert, table, fields, fields_count);
	}

	sql_offset = 0;
	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	p = NULL;
	/* iterate the entries (lines 9, 14 and 19 in T1) */
	while (NULL != (p = zbx_json_next(&jp_data, p)))
	{
		int	rec_differ = 0;	/* how many fields differ */
		int	last_n = 0;
		size_t	tmp_offset = sql_offset, last_pos = 0;

		zbx_json_brackets_open(p, &jp_row);
		pf = zbx_json_next_value_dyn(&jp_row, NULL, &buf, &buf_alloc, NULL);

		/* check whether we need to insert a new entry or update an existing one */
		ZBX_STR2UINT64(recid, buf);
		insert = (FAIL != zbx_vector_uint64_bsearch(&ins, recid, ZBX_DEFAULT_UINT64_COMPARE_FUNC));

		if (0 != insert)
		{
			/* perform insert operation */

			zbx_db_value_t	*value;

			/* add the id field */
			value = zbx_malloc(NULL, sizeof(zbx_db_value_t));
			value->ui64 = recid;
			zbx_vector_ptr_append(&values, value);

			/* add the rest of fields */
			for (f = 1; NULL != (pf = zbx_json_next_value_dyn(&jp_row, pf, &buf, &buf_alloc, &is_null));
					f++)
			{
				if (f == fields_count)
				{
					*error = zbx_dsprintf(*error, "invalid number of fields \"%.*s\"",
							(int)(jp_row.end - jp_row.start + 1), jp_row.start);
					goto clean;
				}

				if (0 != is_null && 0 != (fields[f]->flags & ZBX_NOTNULL))
				{
					*error = zbx_dsprintf(*error, "column \"%s.%s\" cannot be null",
							table->table, fields[f]->name);
					goto clean;
				}

				value = zbx_malloc(NULL, sizeof(zbx_db_value_t));

				switch (fields[f]->type)
				{
					case ZBX_TYPE_INT:
						value->i32 = atoi(buf);
						break;
					case ZBX_TYPE_UINT:
						ZBX_STR2UINT64(value->ui64, buf);
						break;
					case ZBX_TYPE_ID:
						if (0 == is_null)
							ZBX_STR2UINT64(value->ui64, buf);
						else
							value->ui64 = 0;
						break;
					case ZBX_TYPE_FLOAT:
						value->dbl = atof(buf);
						break;
					case ZBX_TYPE_CHAR:
					case ZBX_TYPE_TEXT:
					case ZBX_TYPE_SHORTTEXT:
					case ZBX_TYPE_LONGTEXT:
						value->str = zbx_strdup(NULL, buf);
						break;
					default:
						*error = zbx_dsprintf(*error, "unsupported field type %d in \"%s.%s\"",
								(int)fields[f]->type, table->table, fields[f]->name);
						zbx_free(value);
						goto clean;

				}

				zbx_vector_ptr_append(&values, value);
			}

			zbx_db_insert_add_values_dyn(&db_insert, (const zbx_db_value_t **)values.values,
					values.values_num);

			for (f = 0; f < fields_count; f++)
			{
				switch (fields[f]->type)
				{
					case ZBX_TYPE_CHAR:
					case ZBX_TYPE_TEXT:
					case ZBX_TYPE_SHORTTEXT:
					case ZBX_TYPE_LONGTEXT:
						value = values.values[f];
						zbx_free(value->str);
				}
			}
			zbx_vector_ptr_clear_ext(&values, zbx_ptr_free);

			if (f != fields_count)
			{
				*error = zbx_dsprintf(*error, "invalid number of fields \"%.*s\"",
						(int)(jp_row.end - jp_row.start + 1), jp_row.start);
				goto clean;
			}
		}
		else
		{
			/* perform update operation */

			if (1 == fields_count)	/* only primary key given, no update needed */
				continue;

			/* locate a copy of this record as found in database */
			id_offset.id = recid;
			if (NULL == (p_id_offset = zbx_hashset_search(&h_id_offsets, &id_offset)))
			{
				THIS_SHOULD_NEVER_HAPPEN;
				goto clean;
			}

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update %s set ", table->table);

			for (f = 1; NULL != (pf = zbx_json_next_value_dyn(&jp_row, pf, &buf, &buf_alloc, &is_null));
					f++)
			{
				int	field_differ = 1;

				/* parse values for the entry (lines 10-12 in T1) */

				if (f == fields_count)
				{
					*error = zbx_dsprintf(*error, "invalid number of fields \"%.*s\"",
							(int)(jp_row.end - jp_row.start + 1), jp_row.start);
					goto clean;
				}

				if (0 != is_null && 0 != (fields[f]->flags & ZBX_NOTNULL))
				{
					*error = zbx_dsprintf(*error, "column \"%s.%s\" cannot be null",
							table->table, fields[f]->name);
					goto clean;
				}

				/* do not update existing lastlogsize and mtime fields */
				if (FAIL != zbx_vector_ptr_bsearch(&skip_fields, fields[f],
						ZBX_DEFAULT_PTR_COMPARE_FUNC))
				{
					continue;
				}

				if (0 == (field_differ = compare_nth_field(fields, recs + p_id_offset->offset, f, buf,
						is_null, &last_n, &last_pos)))
				{
					continue;
				}

				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%s=", fields[f]->name);
				rec_differ++;

				if (0 != is_null)
				{
					zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "null,");
					continue;
				}

				switch (fields[f]->type)
				{
					case ZBX_TYPE_INT:
					case ZBX_TYPE_UINT:
					case ZBX_TYPE_ID:
					case ZBX_TYPE_FLOAT:
						zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%s,", buf);
						break;
					default:
						esc = DBdyn_escape_string(buf);
						zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "'%s',", esc);
						zbx_free(esc);
				}
			}

			if (f != fields_count)
			{
				*error = zbx_dsprintf(*error, "invalid number of fields \"%.*s\"",
						(int)(jp_row.end - jp_row.start + 1), jp_row.start);
				goto clean;
			}

			sql_offset--;

			if (0 != rec_differ)
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " where %s=" ZBX_FS_UI64 ";\n",
						table->recid, recid);
			}
			else
			{
				sql_offset = tmp_offset;	/* discard this update, all fields are the same */
				*(sql + sql_offset) = '\0';
			}

			if (SUCCEED != DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset))
				goto clean;
		}
	}

	if (sql_offset > 16)	/* in ORACLE always present begin..end; */
	{
		DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

		if (ZBX_DB_OK > DBexecute("%s", sql))
			goto clean;
	}

	ret = (0 == ins.values_num ? SUCCEED : zbx_db_insert_execute(&db_insert));
clean:
	if (0 != ins.values_num)
	{
		zbx_db_insert_clean(&db_insert);
		zbx_vector_ptr_destroy(&values);
	}
clean2:
	zbx_hashset_destroy(&h_id_offsets);
	zbx_hashset_destroy(&h_del);
	zbx_vector_uint64_destroy(&ins);
	if (1 == move_out)
		zbx_vector_uint64_destroy(&moves);
	zbx_free(sql);
	zbx_free(recs);
out:
	zbx_free(buf);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: process_proxyconfig                                              *
 *                                                                            *
 * Purpose: update configuration                                              *
 *                                                                            *
 ******************************************************************************/
void	process_proxyconfig(struct zbx_json_parse *jp_data)
{
	typedef struct
	{
		const ZBX_TABLE		*table;
		zbx_vector_uint64_t	ids;
	}
	table_ids_t;

	const char		*__function_name = "process_proxyconfig";
	char			buf[ZBX_TABLENAME_LEN_MAX];
	const char		*p = NULL;
	struct zbx_json_parse	jp_obj;
	char			*error = NULL;
	int			i, ret = SUCCEED;

	table_ids_t		*table_ids;
	zbx_vector_ptr_t	tables;
	const ZBX_TABLE		*table;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_ptr_create(&tables);

	DBbegin();

	/* iterate the tables (lines 2, 22 and 25 in T1) */
	while (NULL != (p = zbx_json_pair_next(jp_data, p, buf, sizeof(buf))) && SUCCEED == ret)
	{
		if (FAIL == zbx_json_brackets_open(p, &jp_obj))
		{
			error = zbx_strdup(error, zbx_json_strerror());
			ret = FAIL;
			break;
		}

		if (NULL == (table = DBget_table(buf)))
		{
			error = zbx_dsprintf(error, "invalid table name \"%s\"", buf);
			ret = FAIL;
			break;
		}

		table_ids = zbx_malloc(NULL, sizeof(table_ids_t));
		table_ids->table = table;
		zbx_vector_uint64_create(&table_ids->ids);
		zbx_vector_ptr_append(&tables, table_ids);

		ret = process_proxyconfig_table(table, &jp_obj, &table_ids->ids, &error);
	}

	if (SUCCEED == ret)
	{
		char 	*sql = NULL;
		size_t	sql_alloc = 512, sql_offset = 0;

		sql = zbx_malloc(sql, sql_alloc * sizeof(char));

		DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

		for (i = tables.values_num - 1; 0 <= i; i--)
		{
			table_ids = tables.values[i];

			if (0 == table_ids->ids.values_num)
				continue;

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "delete from %s where",
					table_ids->table->table);
			DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, table_ids->table->recid,
					table_ids->ids.values, table_ids->ids.values_num);
			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");
		}

		if (sql_offset > 16)	/* in ORACLE always present begin..end; */
		{
			DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

			if (ZBX_DB_OK > DBexecute("%s", sql))
				ret = FAIL;
		}

		zbx_free(sql);
	}

	for (i = 0; i < tables.values_num; i++)
	{
		table_ids = tables.values[i];

		zbx_vector_uint64_destroy(&table_ids->ids);
		zbx_free(table_ids);
	}
	zbx_vector_ptr_destroy(&tables);

	DBend(ret);

	if (SUCCEED != ret)
	{
		zabbix_log(LOG_LEVEL_ERR, "failed to update local proxy configuration copy: %s",
				(NULL == error ? "database error" : error));
	}
	else
	{
		DCsync_configuration();
		DCupdate_hosts_availability();
	}

	zbx_free(error);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: get_host_availability_data                                       *
 *                                                                            *
 * Return value:  SUCCEED - processed successfully                            *
 *                FAIL - no host availability has been changed                *
 *                                                                            *
 ******************************************************************************/
int	get_host_availability_data(struct zbx_json *json, int *ts)
{
	const char			*__function_name = "get_host_availability_data";
	int				i, j, ret = FAIL;
	zbx_vector_ptr_t		hosts;
	zbx_host_availability_t		*ha;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_ptr_create(&hosts);

	if (SUCCEED != DCget_hosts_availability(&hosts, ts))
		goto out;

	zbx_json_addarray(json, ZBX_PROTO_TAG_HOST_AVAILABILITY);

	for (i = 0; i < hosts.values_num; i++)
	{
		ha = (zbx_host_availability_t *)hosts.values[i];

		zbx_json_addobject(json, NULL);
		zbx_json_adduint64(json, ZBX_PROTO_TAG_HOSTID, ha->hostid);

		for (j = 0; j < ZBX_AGENT_MAX; j++)
		{
			zbx_json_adduint64(json, availability_tag_available[j], ha->agents[j].available);
			zbx_json_addstring(json, availability_tag_error[j], ha->agents[j].error, ZBX_JSON_TYPE_STRING);
		}

		zbx_json_close(json);
	}

	zbx_json_close(json);

	ret = SUCCEED;
out:
	zbx_vector_ptr_clear_ext(&hosts, (zbx_mem_free_func_t)zbx_host_availability_free);
	zbx_vector_ptr_destroy(&hosts);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: process_host_availability_contents                               *
 *                                                                            *
 * Purpose: parses host availability data contents and processes it           *
 *                                                                            *
 * Return value:  SUCCEED - processed successfully                            *
 *                FAIL - an error occurred                                    *
 *                                                                            *
 ******************************************************************************/
static int	process_host_availability_contents(struct zbx_json_parse *jp_data, char **error)
{
	zbx_uint64_t		hostid;
	struct zbx_json_parse	jp_row;
	const char		*p = NULL;
	char			*tmp = NULL;
	size_t			tmp_alloc = 129;
	zbx_host_availability_t	*ha = NULL;
	zbx_vector_ptr_t	hosts;
	int			i, ret;

	tmp = (char *)zbx_malloc(NULL, tmp_alloc);

	zbx_vector_ptr_create(&hosts);

	while (NULL != (p = zbx_json_next(jp_data, p)))	/* iterate the host entries */
	{
		if (SUCCEED != (ret = zbx_json_brackets_open(p, &jp_row)))
		{
			*error = zbx_strdup(*error, zbx_json_strerror());
			goto out;
		}

		if (SUCCEED != (ret = zbx_json_value_by_name_dyn(&jp_row, ZBX_PROTO_TAG_HOSTID, &tmp, &tmp_alloc)))
		{
			*error = zbx_strdup(*error, zbx_json_strerror());
			goto out;
		}

		if (SUCCEED != (ret = is_uint64(tmp, &hostid)))
		{
			*error = zbx_strdup(*error, "hostid is not a valid numeric");
			goto out;
		}

		ha = (zbx_host_availability_t *)zbx_malloc(NULL, sizeof(zbx_host_availability_t));
		zbx_host_availability_init(ha, hostid);

		for (i = 0; i < ZBX_AGENT_MAX; i++)
		{
			if (SUCCEED != zbx_json_value_by_name_dyn(&jp_row, availability_tag_available[i], &tmp,
					&tmp_alloc))
			{
				continue;
			}

			ha->agents[i].available = atoi(tmp);
			ha->agents[i].flags |= ZBX_FLAGS_AGENT_STATUS_AVAILABLE;
		}

		for (i = 0; i < ZBX_AGENT_MAX; i++)
		{
			if (SUCCEED != zbx_json_value_by_name_dyn(&jp_row, availability_tag_error[i], &tmp, &tmp_alloc))
				continue;

			ha->agents[i].error = zbx_strdup(NULL, tmp);
			ha->agents[i].flags |= ZBX_FLAGS_AGENT_STATUS_ERROR;
		}

		if (SUCCEED != (ret = zbx_host_availability_is_set(ha)))
		{
			zbx_free(ha);
			*error = zbx_dsprintf(*error, "no availability data for \"hostid\":" ZBX_FS_UI64, hostid);
			goto out;
		}

		zbx_vector_ptr_append(&hosts, ha);
	}

	if (0 < hosts.values_num && SUCCEED == DCset_hosts_availability(&hosts))
	{
		int	i;
		char	*sql = NULL;
		size_t	sql_alloc = 4 * ZBX_KIBIBYTE, sql_offset = 0;

		sql = zbx_malloc(sql, sql_alloc);

		DBbegin();
		DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

		for (i = 0; i < hosts.values_num; i++)
		{
			if (SUCCEED == zbx_sql_add_host_availability(&sql, &sql_alloc, &sql_offset, hosts.values[i]))
				zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");

			DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
		}

		DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

		if (16 < sql_offset)
			DBexecute("%s", sql);

		DBcommit();

		zbx_free(sql);
	}

	ret = SUCCEED;
out:
	zbx_vector_ptr_clear_ext(&hosts, (zbx_mem_free_func_t)zbx_host_availability_free);
	zbx_vector_ptr_destroy(&hosts);

	zbx_free(tmp);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: process_host_availability                                        *
 *                                                                            *
 * Purpose: update proxy hosts availability                                   *
 *                                                                            *
 * Return value:  SUCCEED - processed successfully                            *
 *                FAIL - an error occurred                                    *
 *                                                                            *
 ******************************************************************************/
int	process_host_availability(struct zbx_json_parse *jp, char **error)
{
	const char		*__function_name = "process_host_availability";
	struct zbx_json_parse	jp_data;
	int			ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (SUCCEED != (ret = zbx_json_brackets_by_name(jp, ZBX_PROTO_TAG_DATA, &jp_data)))
	{
		*error = zbx_strdup(*error, zbx_json_strerror());
		goto out;
	}

	if (SUCCEED == zbx_json_object_is_empty(&jp_data))
		goto out;

	ret = process_host_availability_contents(&jp_data, error);

out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: proxy_get_lastid                                                 *
 *                                                                            *
 ******************************************************************************/
static void	proxy_get_lastid(const char *table_name, const char *lastidfield, zbx_uint64_t *lastid)
{
	const char	*__function_name = "proxy_get_lastid";
	DB_RESULT	result;
	DB_ROW		row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() field:'%s.%s'", __function_name, table_name, lastidfield);

	result = DBselect("select nextid from ids where table_name='%s' and field_name='%s'",
			table_name, lastidfield);

	if (NULL == (row = DBfetch(result)))
		*lastid = 0;
	else
		ZBX_STR2UINT64(*lastid, row[0]);
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():" ZBX_FS_UI64,	__function_name, *lastid);
}

/******************************************************************************
 *                                                                            *
 * Function: proxy_set_lastid                                                 *
 *                                                                            *
 ******************************************************************************/
static void	proxy_set_lastid(const char *table_name, const char *lastidfield, const zbx_uint64_t lastid)
{
	const char	*__function_name = "proxy_set_lastid";
	DB_RESULT	result;
	DB_ROW		row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() [%s.%s:" ZBX_FS_UI64 "]",
			__function_name, table_name, lastidfield, lastid);

	result = DBselect("select 1 from ids where table_name='%s' and field_name='%s'",
			table_name, lastidfield);

	if (NULL == (row = DBfetch(result)))
	{
		DBexecute("insert into ids (table_name,field_name,nextid) values ('%s','%s'," ZBX_FS_UI64 ")",
				table_name, lastidfield, lastid);
	}
	else
	{
		DBexecute("update ids set nextid=" ZBX_FS_UI64 " where table_name='%s' and field_name='%s'",
				lastid, table_name, lastidfield);
	}
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

void	proxy_set_hist_lastid(const zbx_uint64_t lastid)
{
	proxy_set_lastid("proxy_history", "history_lastid", lastid);
}

void	proxy_set_dhis_lastid(const zbx_uint64_t lastid)
{
	proxy_set_lastid(dht.table, dht.lastidfield, lastid);
}

void	proxy_set_areg_lastid(const zbx_uint64_t lastid)
{
	proxy_set_lastid(areg.table, areg.lastidfield, lastid);
}

/******************************************************************************
 *                                                                            *
 * Function: proxy_get_history_data_simple                                    *
 *                                                                            *
 * Purpose: Get history data from the database.                               *
 *                                                                            *
 ******************************************************************************/
static void	proxy_get_history_data_simple(struct zbx_json *j, const char *proto_tag, const zbx_history_table_t *ht,
		zbx_uint64_t *lastid, zbx_uint64_t *id, int *records_num, int *more)
{
	const char	*__function_name = "proxy_get_history_data_simple";
	size_t		offset = 0;
	int		f, records_num_last = *records_num, retries = 1;
	char		sql[MAX_STRING_LEN];
	DB_RESULT	result;
	DB_ROW		row;
	struct timespec	t_sleep = { 0, 100000000L }, t_rem;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() table:'%s'", __function_name, ht->table);

	*more = ZBX_PROXY_DATA_DONE;

	offset += zbx_snprintf(sql + offset, sizeof(sql) - offset, "select id");

	for (f = 0; NULL != ht->fields[f].field; f++)
		offset += zbx_snprintf(sql + offset, sizeof(sql) - offset, ",%s", ht->fields[f].field);
try_again:
	zbx_snprintf(sql + offset, sizeof(sql) - offset, " from %s where id>" ZBX_FS_UI64 " order by id",
			ht->table, *id);

	result = DBselectN(sql, ZBX_MAX_HRECORDS);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(*lastid, row[0]);

		if (1 < *lastid - *id)
		{
			/* At least one record is missing. It can happen if some DB syncer process has */
			/* started but not yet committed a transaction or a rollback occurred in a DB syncer. */
			if (0 < retries--)
			{
				DBfree_result(result);
				zabbix_log(LOG_LEVEL_DEBUG, "%s() " ZBX_FS_UI64 " record(s) missing."
						" Waiting " ZBX_FS_DBL " sec, retrying.",
						__function_name, *lastid - *id - 1,
						t_sleep.tv_sec + t_sleep.tv_nsec / 1e9);
				nanosleep(&t_sleep, &t_rem);
				goto try_again;
			}
			else
			{
				zabbix_log(LOG_LEVEL_DEBUG, "%s() " ZBX_FS_UI64 " record(s) missing. No more retries.",
						__function_name, *lastid - *id - 1);
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
	DBfree_result(result);

	if (ZBX_MAX_HRECORDS == *records_num)
		*more = ZBX_PROXY_DATA_MORE;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d lastid:" ZBX_FS_UI64 " more:%d size:" ZBX_FS_SIZE_T,
			__function_name, *records_num - records_num_last, *lastid, *more, j->buffer_offset);
}

/******************************************************************************
 *                                                                            *
 * Function: proxy_get_history_data                                           *
 *                                                                            *
 * Purpose: Get history data from the database. Get items configuration from  *
 *          cache to speed things up.                                         *
 *                                                                            *
 ******************************************************************************/
static void	proxy_get_history_data(struct zbx_json *j, zbx_uint64_t *lastid, zbx_uint64_t *id, int *records_num,
		int *more)
{
	const char			*__function_name = "proxy_get_history_data";

	typedef struct
	{
		zbx_uint64_t	itemid;
		zbx_uint64_t	lastlogsize;
		size_t		psource;
		size_t		pvalue;
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

	char				*sql = NULL;
	size_t				sql_alloc = 0, sql_offset = 0;
	DB_RESULT			result;
	DB_ROW				row;
	static char			*string_buffer = NULL;
	static size_t			string_buffer_alloc = ZBX_KIBIBYTE;
	size_t				string_buffer_offset = 0, len1, len2;
	static zbx_uint64_t		*itemids = NULL;
	static zbx_history_data_t	*data = NULL;
	static size_t			data_alloc = 0;
	size_t				data_num = 0, i;
	DC_ITEM				*dc_items;
	int				*errcodes, retries = 1, records_num_last = *records_num;
	zbx_history_data_t		*hd;
	struct timespec			t_sleep = { 0, 100000000L }, t_rem;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (NULL == string_buffer)
		string_buffer = zbx_malloc(string_buffer, string_buffer_alloc);

	*more = ZBX_PROXY_DATA_DONE;

try_again:
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select id,itemid,clock,ns,timestamp,source,severity,"
				"value,logeventid,state,lastlogsize,mtime,flags"
			" from proxy_history"
			" where id>" ZBX_FS_UI64
			" order by id",
			*id);

	result = DBselectN(sql, ZBX_MAX_HRECORDS);

	zbx_free(sql);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(*lastid, row[0]);

		if (1 < *lastid - *id)
		{
			/* At least one record is missing. It can happen if some DB syncer process has */
			/* started but not yet committed a transaction or a rollback occurred in a DB syncer. */
			if (0 < retries--)
			{
				DBfree_result(result);
				zabbix_log(LOG_LEVEL_DEBUG, "%s() " ZBX_FS_UI64 " record(s) missing."
						" Waiting " ZBX_FS_DBL " sec, retrying.",
						__function_name, *lastid - *id - 1,
						t_sleep.tv_sec + t_sleep.tv_nsec / 1e9);
				nanosleep(&t_sleep, &t_rem);
				goto try_again;
			}
			else
			{
				zabbix_log(LOG_LEVEL_DEBUG, "%s() " ZBX_FS_UI64 " record(s) missing. No more retries.",
						__function_name, *lastid - *id - 1);
			}
		}

		if (data_alloc == data_num)
		{
			data_alloc += 8;
			data = zbx_realloc(data, sizeof(zbx_history_data_t) * data_alloc);
			itemids = zbx_realloc(itemids, sizeof(zbx_uint64_t) * data_alloc);
		}

		ZBX_STR2UINT64(itemids[data_num], row[1]);

		hd = &data[data_num++];

		hd->itemid = *lastid;
		hd->clock = atoi(row[2]);
		hd->ns = atoi(row[3]);
		hd->timestamp = atoi(row[4]);
		hd->severity = atoi(row[6]);
		hd->logeventid = atoi(row[8]);
		ZBX_STR2UCHAR(hd->state, row[9]);
		ZBX_STR2UINT64(hd->lastlogsize, row[10]);
		hd->mtime = atoi(row[11]);
		ZBX_STR2UCHAR(hd->flags, row[12]);

		len1 = strlen(row[5]) + 1;
		len2 = strlen(row[7]) + 1;

		if (string_buffer_alloc < string_buffer_offset + len1 + len2)
		{
			while (string_buffer_alloc < string_buffer_offset + len1 + len2)
				string_buffer_alloc += ZBX_KIBIBYTE;

			string_buffer = zbx_realloc(string_buffer, string_buffer_alloc);
		}

		hd->psource = string_buffer_offset;
		memcpy(&string_buffer[string_buffer_offset], row[5], len1);
		string_buffer_offset += len1;
		hd->pvalue = string_buffer_offset;
		memcpy(&string_buffer[string_buffer_offset], row[7], len2);
		string_buffer_offset += len2;

		*id = *lastid;
	}
	DBfree_result(result);

	dc_items = zbx_malloc(NULL, (sizeof(DC_ITEM) + sizeof(int)) * data_num);
	errcodes = (int *)(dc_items + data_num);

	DCconfig_get_items_by_itemids(dc_items, itemids, errcodes, data_num);

	for (i = 0; i < data_num; i++)
	{
		if (SUCCEED != errcodes[i])
			continue;

		if (ITEM_STATUS_ACTIVE != dc_items[i].status)
			continue;

		if (HOST_STATUS_MONITORED != dc_items[i].host.status)
			continue;

		hd = &data[i];

		if (0 == *records_num)
			zbx_json_addarray(j, ZBX_PROTO_TAG_HISTORY_DATA);

		zbx_json_addobject(j, NULL);
		zbx_json_adduint64(j, ZBX_PROTO_TAG_ITEMID, dc_items[i].itemid);
		zbx_json_adduint64(j, ZBX_PROTO_TAG_CLOCK, hd->clock);
		zbx_json_adduint64(j, ZBX_PROTO_TAG_NS, hd->ns);

		if (0 != hd->timestamp)
			zbx_json_adduint64(j, ZBX_PROTO_TAG_LOGTIMESTAMP, hd->timestamp);

		if ('\0' != string_buffer[hd->psource])
		{
			zbx_json_addstring(j, ZBX_PROTO_TAG_LOGSOURCE, &string_buffer[hd->psource],
					ZBX_JSON_TYPE_STRING);
		}

		if (0 != hd->severity)
			zbx_json_adduint64(j, ZBX_PROTO_TAG_LOGSEVERITY, hd->severity);

		if (0 != hd->logeventid)
			zbx_json_adduint64(j, ZBX_PROTO_TAG_LOGEVENTID, hd->logeventid);

		if (0 != hd->state)
			zbx_json_adduint64(j, ZBX_PROTO_TAG_STATE, hd->state);

		if (0 == (PROXY_HISTORY_FLAG_NOVALUE & hd->flags))
			zbx_json_addstring(j, ZBX_PROTO_TAG_VALUE, &string_buffer[hd->pvalue], ZBX_JSON_TYPE_STRING);

		if (0 != (PROXY_HISTORY_FLAG_META & hd->flags))
		{
			zbx_json_adduint64(j, ZBX_PROTO_TAG_LASTLOGSIZE, hd->lastlogsize);
			zbx_json_adduint64(j, ZBX_PROTO_TAG_MTIME, hd->mtime);
		}

		zbx_json_close(j);

		(*records_num)++;

		/* stop gathering data to avoid exceeding the maximum packet size */
		if (ZBX_DATA_JSON_RECORD_LIMIT < j->buffer_offset)
		{
			/* rollback lastid and id to the last added itemid */
			*lastid = hd->itemid;
			*id = hd->itemid;

			*more = ZBX_PROXY_DATA_MORE;
			break;
		}
	}
	DCconfig_clean_items(dc_items, errcodes, data_num);
	zbx_free(dc_items);

	if (ZBX_MAX_HRECORDS == data_num)
		*more = ZBX_PROXY_DATA_MORE;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d selected:%d lastid:" ZBX_FS_UI64 " more:%d size:"
			ZBX_FS_SIZE_T, __function_name, *records_num - records_num_last, data_num, *lastid, *more,
			(int)j->buffer_offset);
}

int	proxy_get_hist_data(struct zbx_json *j, zbx_uint64_t *lastid, int *more)
{
	int		records_num = 0;
	zbx_uint64_t	id;

	proxy_get_lastid("proxy_history", "history_lastid", &id);

	/* get history data in batches by ZBX_MAX_HRECORDS records and stop if: */
	/*   1) there are no more data to read                                  */
	/*   2) we have retrieved more than the total maximum number of records */
	/*   3) we have gathered more than half of the maximum packet size      */
	while (ZBX_DATA_JSON_BATCH_LIMIT > j->buffer_offset)
	{
		proxy_get_history_data(j, lastid, &id, &records_num, more);

		if (ZBX_PROXY_DATA_DONE == *more || ZBX_MAX_HRECORDS_TOTAL < records_num)
			break;
	}

	if (0 != records_num)
		zbx_json_close(j);

	return records_num;
}

int	proxy_get_dhis_data(struct zbx_json *j, zbx_uint64_t *lastid, int *more)
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

		if (ZBX_PROXY_DATA_DONE == *more || ZBX_MAX_HRECORDS_TOTAL < records_num)
			break;
	}

	if (0 != records_num)
		zbx_json_close(j);

	return records_num;
}

int	proxy_get_areg_data(struct zbx_json *j, zbx_uint64_t *lastid, int *more)
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
		proxy_get_history_data_simple(j, ZBX_PROTO_TAG_AUTO_REGISTRATION, &areg, lastid, &id, &records_num,
				more);

		if (ZBX_PROXY_DATA_DONE == *more || ZBX_MAX_HRECORDS_TOTAL < records_num)
			break;
	}

	if (0 != records_num)
		zbx_json_close(j);

	return records_num;
}

void	calc_timestamp(const char *line, int *timestamp, const char *format)
{
	const char	*__function_name = "calc_timestamp";
	int		hh, mm, ss, yyyy, dd, MM;
	int		hhc = 0, mmc = 0, ssc = 0, yyyyc = 0, ddc = 0, MMc = 0;
	int		i, num;
	struct tm	tm;
	time_t		t;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	hh = mm = ss = yyyy = dd = MM = 0;

	for (i = 0; '\0' != format[i] && '\0' != line[i]; i++)
	{
		if (0 == isdigit(line[i]))
			continue;

		num = (int)line[i] - 48;

		switch ((char)format[i])
		{
			case 'h':
				hh = 10 * hh + num;
				hhc++;
				break;
			case 'm':
				mm = 10 * mm + num;
				mmc++;
				break;
			case 's':
				ss = 10 * ss + num;
				ssc++;
				break;
			case 'y':
				yyyy = 10 * yyyy + num;
				yyyyc++;
				break;
			case 'd':
				dd = 10 * dd + num;
				ddc++;
				break;
			case 'M':
				MM = 10 * MM + num;
				MMc++;
				break;
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "%s() %02d:%02d:%02d %02d/%02d/%04d",
			__function_name, hh, mm, ss, MM, dd, yyyy);

	/* seconds can be ignored, no ssc here */
	if (0 != hhc && 0 != mmc && 0 != yyyyc && 0 != ddc && 0 != MMc)
	{
		tm.tm_sec = ss;
		tm.tm_min = mm;
		tm.tm_hour = hh;
		tm.tm_mday = dd;
		tm.tm_mon = MM - 1;
		tm.tm_year = yyyy - 1900;
		tm.tm_isdst = -1;

		if (0 < (t = mktime(&tm)))
			*timestamp = t;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() timestamp:%d", __function_name, *timestamp);
}

/******************************************************************************
 *                                                                            *
 * Function: process_history_data_value                                       *
 *                                                                            *
 * Purpose: process single value from incoming history data                   *
 *                                                                            *
 * Parameters: item    - [IN] the item to process                             *
 *             value   - [IN] the value to process                            *
 *                                                                            *
 * Return value: SUCCEED - the value was processed successfully               *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	process_history_data_value(DC_ITEM *item, zbx_agent_value_t *value)
{
	if (ITEM_STATUS_ACTIVE != item->status)
		return FAIL;

	if (HOST_STATUS_MONITORED != item->host.status)
		return FAIL;

	if (SUCCEED == in_maintenance_without_data_collection(item->host.maintenance_status,
			item->host.maintenance_type, item->type) &&
			item->host.maintenance_from <= value->ts.sec)
		return FAIL;

	/* empty values are only allowed for meta information update packets */
	if (NULL == value->value && 0 == value->meta)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "item %s value is empty", item->key_orig);
		return FAIL;
	}

	if (ITEM_STATE_NOTSUPPORTED == value->state ||
			(NULL != value->value && 0 == strcmp(value->value, ZBX_NOTSUPPORTED)))
	{
		item->state = ITEM_STATE_NOTSUPPORTED;
		dc_add_history(item->itemid, item->value_type, item->flags, NULL, &value->ts,
				item->state, value->value);
	}
	else
	{
		int		res = SUCCEED;
		AGENT_RESULT	result;

		init_result(&result);

		if (NULL != value->value)
			res = set_result_type(&result, item->value_type, item->data_type, value->value);

		if (SUCCEED == res)
		{
			if (ITEM_VALUE_TYPE_LOG == item->value_type && NULL != value->value)
			{
				result.log->timestamp = value->timestamp;
				if (NULL != value->source)
				{
					zbx_replace_invalid_utf8(value->source);
					result.log->source = zbx_strdup(result.log->source, value->source);
				}
				result.log->severity = value->severity;
				result.log->logeventid = value->logeventid;

				calc_timestamp(result.log->value, &result.log->timestamp, item->logtimefmt);
			}

			if (0 != value->meta)
				set_result_meta(&result, value->lastlogsize, value->mtime);

			item->state = ITEM_STATE_NORMAL;
			dc_add_history(item->itemid, item->value_type, item->flags, &result,
					&value->ts, item->state, NULL);
		}
		else if (ISSET_MSG(&result))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "item [%s:%s] error: %s", item->host.host, item->key_orig,
					result.msg);

			item->state = ITEM_STATE_NOTSUPPORTED;
			dc_add_history(item->itemid, item->value_type, item->flags, NULL, &value->ts, item->state,
					result.msg);
		}
		else
			THIS_SHOULD_NEVER_HAPPEN;	/* set_result_type() always sets MSG result if not SUCCEED */

		free_result(&result);
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: process_history_data                                             *
 *                                                                            *
 * Purpose: process new item values                                           *
 *                                                                            *
 * Parameters: items      - [IN] the items to process                         *
 *             values     - [IN] the item values value to process             *
 *             errcodes   - [IN/OUT] in - item configuration error code       *
 *                                      (FAIL - item/host was not found)      *
 *                                   out - value processing result            *
 *                                      (SUCCEED - processed, FAIL - error)   *
 *             values_num - [IN] the number of items/values to process        *
 *                                                                            *
 * Return value: the number of processed values                               *
 *                                                                            *
 ******************************************************************************/
int	process_history_data(DC_ITEM *items, zbx_agent_value_t *values, int *errcodes, size_t values_num)
{
	const char	*__function_name = "process_history_data";
	size_t		i;
	int		processed_num = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	for (i = 0; i < values_num; i++)
	{
		if (SUCCEED != errcodes[i])
			continue;

		if (SUCCEED != process_history_data_value(&items[i], &values[i]))
		{
			/* clean failed items to avoid updating their runtime data */
			DCconfig_clean_items(&items[i], &errcodes[i], 1);
			errcodes[i] = FAIL;
			continue;
		}

		processed_num++;
	}

	if (0 < processed_num)
	{
		zbx_dc_items_update_runtime_data(items, values, errcodes, values_num);
		dc_flush_history();
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() processed:%d", __function_name, processed_num);

	return processed_num;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_agent_values_clean                                           *
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
 * Function: get_client_timediff                                              *
 *                                                                            *
 * Purpose: calculates difference between server and client (proxy, active    *
 *          agent or sender) time                                             *
 *                                                                            *
 * Parameters: jp           - [IN] JSON with clock, [ns] fields               *
 *             ts_recv      - [IN] the connection timestamp                   *
 *             ts_diff      - [OUT] proxy - server timestamp difference       *
 *                                                                            *
 * Return value:  SUCCEED - processed successfully                            *
 *                FAIL - the JSON does not have timestamp data                *
 *                                                                            *
 ******************************************************************************/
static int	get_client_timediff(struct zbx_json_parse *jp, const zbx_timespec_t *ts_recv, zbx_timespec_t *ts_diff)
{
	char	tmp[32];

	if (SUCCEED == zbx_json_value_by_name(jp, ZBX_PROTO_TAG_CLOCK, tmp, sizeof(tmp)))
	{
		ts_diff->sec = ts_recv->sec - atoi(tmp);

		if (SUCCEED == zbx_json_value_by_name(jp, ZBX_PROTO_TAG_NS, tmp, sizeof(tmp)))
		{
			ts_diff->ns = ts_recv->ns - atoi(tmp);

			if (ts_diff->ns < 0)
			{
				ts_diff->sec--;
				ts_diff->ns += 1000000000;
			}
		}
		return SUCCEED;
	}
	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: parse_history_data_row_value                                     *
 *                                                                            *
 * Purpose: parses agent value from history data json row                     *
 *                                                                            *
 * Parameters: jp_row  - [IN] JSON with history data row                      *
 *             ts_diff - [IN] proxy - server timestamp difference             *
 *             av      - [OUT] the agent value                                *
 *                                                                            *
 * Return value:  SUCCEED - the value was parsed successfully                 *
 *                FAIL    - otherwise                                         *
 *                                                                            *
 ******************************************************************************/
static int	parse_history_data_row_value(const struct zbx_json_parse *jp_row, const zbx_timespec_t *ts_diff,
		zbx_agent_value_t *av)
{
	char	*tmp = NULL;
	size_t	tmp_alloc = 0;
	int	ret = FAIL;

	memset(av, 0, sizeof(zbx_agent_value_t));

	if (SUCCEED == zbx_json_value_by_name_dyn(jp_row, ZBX_PROTO_TAG_CLOCK, &tmp, &tmp_alloc))
	{
		if (FAIL == is_uint31(tmp, &av->ts.sec))
			goto out;

		av->ts.sec += ts_diff->sec;

		if (SUCCEED == zbx_json_value_by_name_dyn(jp_row, ZBX_PROTO_TAG_NS, &tmp, &tmp_alloc))
		{
			if (FAIL == is_uint_n_range(tmp, tmp_alloc, &av->ts.ns, sizeof(av->ts.ns),
				0LL, 999999999LL))
			{
				goto out;
			}

			av->ts.ns += ts_diff->ns;

			if (av->ts.ns > 999999999)
			{
				av->ts.sec++;
				av->ts.ns -= 1000000000;
			}
		}
		else
			av->ts.ns = ts_diff->ns;
	}
	else
		zbx_timespec(&av->ts);

	if (SUCCEED == zbx_json_value_by_name_dyn(jp_row, ZBX_PROTO_TAG_STATE, &tmp, &tmp_alloc))
		av->state = (unsigned char)atoi(tmp);

	if (SUCCEED == zbx_json_value_by_name_dyn(jp_row, ZBX_PROTO_TAG_LASTLOGSIZE, &tmp, &tmp_alloc))
	{
		av->meta = 1;	/* contains meta information */

		is_uint64(tmp, &av->lastlogsize);
	}

	if (SUCCEED == zbx_json_value_by_name_dyn(jp_row, ZBX_PROTO_TAG_VALUE, &tmp, &tmp_alloc))
	{
		av->value = zbx_strdup(av->value, tmp);
	}
	else
	{
		if (ITEM_STATE_NOTSUPPORTED == av->state)
		{
			/* unsupported items cannot have empty error message */
			goto out;
		}

		if (0 == av->meta)
		{
			/* only meta information update packets can have empty value*/
			goto out;
		}
	}

	if (SUCCEED == zbx_json_value_by_name_dyn(jp_row, ZBX_PROTO_TAG_MTIME, &tmp, &tmp_alloc))
		av->mtime = atoi(tmp);

	if (SUCCEED == zbx_json_value_by_name_dyn(jp_row, ZBX_PROTO_TAG_LOGTIMESTAMP, &tmp, &tmp_alloc))
		av->timestamp = atoi(tmp);

	if (SUCCEED == zbx_json_value_by_name_dyn(jp_row, ZBX_PROTO_TAG_LOGSOURCE, &tmp, &tmp_alloc))
		av->source = zbx_strdup(av->source, tmp);

	if (SUCCEED == zbx_json_value_by_name_dyn(jp_row, ZBX_PROTO_TAG_LOGSEVERITY, &tmp, &tmp_alloc))
		av->severity = atoi(tmp);

	if (SUCCEED == zbx_json_value_by_name_dyn(jp_row, ZBX_PROTO_TAG_LOGEVENTID, &tmp, &tmp_alloc))
		av->logeventid = atoi(tmp);

	zbx_free(tmp);

	ret = SUCCEED;
out:
	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: parse_history_data_row_itemid                                    *
 *                                                                            *
 * Purpose: parses item identifier from history data json row                 *
 *                                                                            *
 * Parameters: jp_row  - [IN] JSON with history data row                      *
 *             ts_diff - [IN] proxy - server timestamp difference             *
 *             itemid  - [OUT] the item identifier                            *
 *                                                                            *
 * Return value:  SUCCEED - the item identifier was parsed successfully       *
 *                FAIL    - otherwise                                         *
 *                                                                            *
 ******************************************************************************/
static int	parse_history_data_row_itemid(const struct zbx_json_parse *jp_row, zbx_uint64_t *itemid)
{
	char	buffer[MAX_ID_LEN + 1];

	if (SUCCEED != zbx_json_value_by_name(jp_row, ZBX_PROTO_TAG_ITEMID, buffer, sizeof(buffer)))
		return FAIL;

	if (SUCCEED != is_uint64(buffer, itemid))
		return FAIL;

	return SUCCEED;
}
/******************************************************************************
 *                                                                            *
 * Function: parse_history_data_row_hostkey                                   *
 *                                                                            *
 * Purpose: parses host,key pair from history data json row                   *
 *                                                                            *
 * Parameters: jp_row  - [IN] JSON with history data row                      *
 *             ts_diff - [IN] proxy - server timestamp difference             *
 *             hk      - [OUT] the host,key pair                              *
 *                                                                            *
 * Return value:  SUCCEED - the host,key pair was parsed successfully         *
 *                FAIL    - otherwise                                         *
 *                                                                            *
 ******************************************************************************/
static int	parse_history_data_row_hostkey(const struct zbx_json_parse *jp_row, zbx_host_key_t *hk)
{
	char	buffer[MAX_STRING_LEN];

	if (SUCCEED != zbx_json_value_by_name(jp_row, ZBX_PROTO_TAG_HOST, buffer, sizeof(buffer)))
		return FAIL;

	hk->host = zbx_strdup(hk->host, buffer);

	if (SUCCEED != zbx_json_value_by_name(jp_row, ZBX_PROTO_TAG_KEY, buffer, sizeof(buffer)))
	{
		zbx_free(hk->host);
		return FAIL;
	}

	hk->key = zbx_strdup(hk->key, buffer);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: parse_history_data                                               *
 *                                                                            *
 * Purpose: parses up to ZBX_HISTORY_VALUES_MAX item values and host,key      *
 *          pairs from history data json                                      *
 *                                                                            *
 * Parameters: jp_data      - [IN] JSON with history data array               *
 *             pnext        - [IN/OUT] the pointer to the next item in json,  *
 *                                     NULL - no more data left               *
 *             values       - [OUT] the item values                           *
 *             hostkeys     - [OUT] the corresponding host,key pairs          *
 *             values_num   - [OUT] the number of values stored in values and *
 *                                  hostkeys arrays                           *
 *             total_num    - [OUT] the number of values parsed               *
 *             ts_diff      - [IN] proxy - server timestamp difference        *
 *             info         - [OUT] address of a pointer to the info string   *
 *                                  (should be freed by the caller)           *
 *                                                                            *
 * Return value:  SUCCEED - values were parsed successfully                   *
 *                FAIL    - an error occurred                                 *
 *                                                                            *
 ******************************************************************************/
static int	parse_history_data(struct zbx_json_parse *jp_data, const char **pnext, zbx_agent_value_t *values,
		zbx_host_key_t *hostkeys, int *values_num, int *parsed_num, const zbx_timespec_t *ts_diff, char **error)
{
	const char		*__function_name = "parse_history_data";

	struct zbx_json_parse	jp_row;
	int			ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

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

		if (SUCCEED != parse_history_data_row_hostkey(&jp_row, &hostkeys[*values_num]))
			continue;

		if (SUCCEED != parse_history_data_row_value(&jp_row, ts_diff, &values[*values_num]))
			continue;

		(*values_num)++;
	}
	while (NULL != (*pnext = zbx_json_next(jp_data, *pnext)) && *values_num < ZBX_HISTORY_VALUES_MAX);

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() processed:%d/%d", __function_name, *values_num, *parsed_num);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: parse_history_data_33                                            *
 *                                                                            *
 * Purpose: parses up to ZBX_HISTORY_VALUES_MAX item values and item          *
 *          identifiers from history data json                                *
 *                                                                            *
 * Parameters: jp_data      - [IN] JSON with history data array               *
 *             pnext        - [IN/OUT] the pointer to the next item in json,  *
 *                                     NULL - no more data to parse           *
 *             values       - [OUT] the item values                           *
 *             itemids      - [OUT] the corresponding item identifieres       *
 *             values_num   - [OUT] the number of values stored in values and *
 *                                  hostkeys arrays                           *
 *             parsed_num   - [OUT] the number of values parsed               *
 *             ts_diff      - [IN] proxy - server timestamp difference        *
 *             info         - [OUT] address of a pointer to the info string   *
 *                                  (should be freed by the caller)           *
 *                                                                            *
 * Return value:  SUCCEED - values were parsed successfully                   *
 *                FAIL    - an error occurred                                 *
 *                                                                            *
 * Comments: This function is used to parse the new proxy history data        *
 *           protocol introduced in Zabbix v3.3                               *
 *                                                                            *
 ******************************************************************************/
static int	parse_history_data_33(struct zbx_json_parse *jp_data, const char **pnext, zbx_agent_value_t *values,
		zbx_uint64_t *itemids, int *values_num, int *parsed_num, const zbx_timespec_t *ts_diff, char **error)
{
	const char		*__function_name = "parse_history_data_33";

	struct zbx_json_parse	jp_row;
	int			ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

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

		if (SUCCEED != parse_history_data_row_value(&jp_row, ts_diff, &values[*values_num]))
			continue;

		(*values_num)++;
	}
	while (NULL != (*pnext = zbx_json_next(jp_data, *pnext)) && *values_num < ZBX_HISTORY_VALUES_MAX);

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() processed:%d/%d", __function_name, *values_num, *parsed_num);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: proxy_item_validator                                             *
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
static int	proxy_item_validator(DC_ITEM *item, zbx_socket_t *sock, void *args, char **error)
{
	zbx_uint64_t	*proxyid = (zbx_uint64_t *)args;

	/* don't process item if its host was assigned to another proxy */
	if (item->host.proxy_hostid != *proxyid)
		return FAIL;

	/* don't process aggregate/calculated items coming from proxy */
	if (ITEM_TYPE_AGGREGATE == item->type || ITEM_TYPE_CALCULATED == item->type)
		return FAIL;

	/* item has been already converted to decimal format by proxy - */
	/* reset its data type to decimal to prevent double conversion  */
	if (ITEM_VALUE_TYPE_UINT64 == item->value_type)
		item->data_type = ITEM_DATA_TYPE_DECIMAL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: agent_item_validator                                             *
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
static int	agent_item_validator(DC_ITEM *item, zbx_socket_t *sock, void *args, char **error)
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
 * Function: sender_item_validator                                            *
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
static int	sender_item_validator(DC_ITEM *item, zbx_socket_t *sock, void *args, char **error)
{
	char			*allowed_hosts;
	int			ret;
	zbx_host_rights_t	*rights;

	if (0 != item->host.proxy_hostid)
		return FAIL;

	if (ITEM_TYPE_TRAPPER != item->type)
		return FAIL;

	allowed_hosts = zbx_strdup(NULL, item->trapper_hosts);
	substitute_simple_macros(NULL, NULL, NULL, NULL, NULL, NULL, item, NULL, &allowed_hosts,
			MACRO_TYPE_PARAMS_FIELD, NULL, 0);
	ret = zbx_tcp_check_security(sock, allowed_hosts, 1);
	zbx_free(allowed_hosts);

	if (FAIL == ret)
	{
		*error = zbx_dsprintf(*error,  "cannot process trapper item \"%s\": %s", item->key_orig,
				zbx_socket_strerror());
		return FAIL;
	}

	rights = (zbx_host_rights_t *)args;

	if (rights->hostid != item->host.hostid)
	{
		rights->hostid = item->host.hostid;
		rights->value = zbx_host_check_permissions(&item->host, sock, error);
	}

	return rights->value;
}

/******************************************************************************
 *                                                                            *
 * Function: process_client_history_data                                      *
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
	const char		*__function_name = "process_client_history_data";

	int			ret = FAIL, values_num, read_num, processed_num = 0, total_num = 0, i,
				errcodes[ZBX_HISTORY_VALUES_MAX];
	struct zbx_json_parse	jp_data;
	zbx_timespec_t		ts_diff;
	const char		*pnext = NULL;
	char			*error = NULL;
	zbx_agent_value_t	values[ZBX_HISTORY_VALUES_MAX];
	zbx_host_key_t		*hostkeys;
	DC_ITEM			*items;
	double			sec;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (SUCCEED != get_client_timediff(jp, ts, &ts_diff))
	{
		ts_diff.sec = 0;
		ts_diff.ns = 0;
	}

	sec = zbx_time();

	if (SUCCEED != (ret = zbx_json_brackets_by_name(jp, ZBX_PROTO_TAG_DATA, &jp_data)))
	{
		*info = zbx_strdup(*info, zbx_json_strerror());
		goto out;
	}

	items = (DC_ITEM *)zbx_malloc(NULL, sizeof(DC_ITEM) * ZBX_HISTORY_VALUES_MAX);
	hostkeys = (zbx_host_key_t *)zbx_malloc(NULL, sizeof(zbx_host_key_t) * ZBX_HISTORY_VALUES_MAX);
	memset(hostkeys, 0, sizeof(zbx_host_key_t) * ZBX_HISTORY_VALUES_MAX);

	while (SUCCEED == parse_history_data(&jp_data, &pnext, values, hostkeys, &values_num, &read_num, &ts_diff,
			&error) && 0 != values_num)
	{
		DCconfig_get_items_by_keys(items, hostkeys, errcodes, values_num);

		for (i = 0; i < values_num; i++)
		{
			if (SUCCEED != errcodes[i])
				continue;

			if (SUCCEED != validator_func(&items[i], sock, validator_args, &error))
			{
				if (NULL != error)
				{
					zabbix_log(LOG_LEVEL_WARNING, "%s", error);
					zbx_free(error);
				}

				DCconfig_clean_items(&items[i], &errcodes[i], 1);
				errcodes[i] = FAIL;
			}
		}

		processed_num += process_history_data(items, values, errcodes, values_num);
		total_num += read_num;

		DCconfig_clean_items(items, errcodes, values_num);
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
out:
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

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: process_proxy_history_data                                       *
 *                                                                            *
 * Purpose: process history data received form Zabbix proxy                   *
 *                                                                            *
 * Parameters: proxy        - [IN] the source proxy                           *
 *             jp           - [IN] the JSON with history data                 *
 *             ts           - [IN] the connection timestamp                   *
 *             info         - [OUT] address of a pointer to the info string   *
 *                                  (should be freed by the caller)           *
 *                                                                            *
 * Return value:  SUCCEED - processed successfully                            *
 *                FAIL - an error occurred                                    *
 *                                                                            *
 ******************************************************************************/
int	process_proxy_history_data(const DC_PROXY *proxy, struct zbx_json_parse *jp, zbx_timespec_t *ts, char **info)
{
	return process_client_history_data(NULL, jp, ts, proxy_item_validator, (void *)&proxy->hostid, info);
}

/******************************************************************************
 *                                                                            *
 * Function: process_agent_history_data                                       *
 *                                                                            *
 * Purpose: process history data received form Zabbix active agent            *
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
int	process_agent_history_data(zbx_socket_t *sock, struct zbx_json_parse *jp, zbx_timespec_t *ts, char **info)
{
	zbx_host_rights_t	rights = {0};

	return process_client_history_data(sock, jp, ts, agent_item_validator, &rights, info);
}

/******************************************************************************
 *                                                                            *
 * Function: process_sender_history_data                                      *
 *                                                                            *
 * Purpose: process history data received form Zabbix sender                  *
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
int	process_sender_history_data(zbx_socket_t *sock, struct zbx_json_parse *jp, zbx_timespec_t *ts, char **info)
{
	zbx_host_rights_t	rights = {0};

	return process_client_history_data(sock, jp, ts, sender_item_validator, &rights, info);
}

/******************************************************************************
 *                                                                            *
 * Function: process_discovery_data_contents                                  *
 *                                                                            *
 * Purpose: parse discovery data contents and process it                      *
 *                                                                            *
 * Parameters: jp_data      - [IN] JSON with discovery data                   *
 *             ts_diff      - [IN] proxy - server timestamp difference        *
 *             info         - [OUT] address of a pointer to the info string   *
 *                                  (should be freed by the caller)           *
 *                                                                            *
 * Return value:  SUCCEED - processed successfully                            *
 *                FAIL - an error occurred                                    *
 *                                                                            *
 ******************************************************************************/
static int	process_discovery_data_contents(struct zbx_json_parse *jp_data, zbx_timespec_t *ts_diff, char **error)
{
	const char		*__function_name = "process_discovery_data_contents";
	DB_RESULT		result;
	DB_ROW			row;
	DB_DRULE		drule;
	DB_DHOST		dhost;
	zbx_uint64_t		last_druleid = 0, dcheckid;
	struct zbx_json_parse	jp_row;
	int			port, status, ret = SUCCEED;
	const char		*p = NULL;
	char			last_ip[INTERFACE_IP_LEN_MAX], ip[INTERFACE_IP_LEN_MAX],
				tmp[MAX_STRING_LEN], *value = NULL, dns[INTERFACE_DNS_LEN_MAX];
	time_t			itemtime;
	size_t			value_alloc = 128;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	memset(&drule, 0, sizeof(drule));
	*last_ip = '\0';

	value = zbx_malloc(value, value_alloc);

	while (NULL != (p = zbx_json_next(jp_data, p)))
	{
		if (FAIL == zbx_json_brackets_open(p, &jp_row))
			goto json_parse_error;

		*value = '\0';
		*dns = '\0';
		port = 0;
		status = 0;

		if (FAIL == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_CLOCK, tmp, sizeof(tmp)))
			goto json_parse_error;

		itemtime = atoi(tmp) + ts_diff->sec;

		if (FAIL == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_DRULE, tmp, sizeof(tmp)))
			goto json_parse_error;

		ZBX_STR2UINT64(drule.druleid, tmp);

		if (FAIL == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_DCHECK, tmp, sizeof(tmp)))
			goto json_parse_error;

		if ('\0' != *tmp)
			ZBX_STR2UINT64(dcheckid, tmp);
		else
			dcheckid = 0;

		if (FAIL == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_IP, ip, sizeof(ip)))
			goto json_parse_error;

		if (SUCCEED == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_PORT, tmp, sizeof(tmp)))
			port = atoi(tmp);

		zbx_json_value_by_name_dyn(&jp_row, ZBX_PROTO_TAG_VALUE, &value, &value_alloc);
		zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_DNS, dns, sizeof(dns));

		if (SUCCEED == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_STATUS, tmp, sizeof(tmp)))
			status = atoi(tmp);

		if (0 == last_druleid || drule.druleid != last_druleid)
		{
			result = DBselect(
					"select dcheckid"
					" from dchecks"
					" where druleid=" ZBX_FS_UI64
						" and uniq=1",
					drule.druleid);

			if (NULL != (row = DBfetch(result)))
				ZBX_STR2UINT64(drule.unique_dcheckid, row[0]);

			DBfree_result(result);

			last_druleid = drule.druleid;
		}

		if ('\0' == *last_ip || 0 != strcmp(ip, last_ip))
		{
			memset(&dhost, 0, sizeof(dhost));
			strscpy(last_ip, ip);
		}

		zabbix_log(LOG_LEVEL_DEBUG, "%s() druleid:" ZBX_FS_UI64 " dcheckid:" ZBX_FS_UI64 " unique_dcheckid:"
				ZBX_FS_UI64 " time:'%s %s' ip:'%s' dns:'%s' port:%d value:'%s'",
				__function_name, drule.druleid, dcheckid, drule.unique_dcheckid, zbx_date2str(itemtime),
				zbx_time2str(itemtime), ip, dns, port, value);

		DBbegin();

		if (0 == dcheckid)
		{
			if (SUCCEED != DBlock_druleid(drule.druleid))
			{
				DBrollback();

				zabbix_log(LOG_LEVEL_DEBUG, "druleid:" ZBX_FS_UI64 " does not exist", drule.druleid);

				continue;
			}

			discovery_update_host(&dhost, status, itemtime);
		}
		else
		{
			if (SUCCEED != DBlock_dcheckid(dcheckid, drule.druleid))
			{
				DBrollback();

				zabbix_log(LOG_LEVEL_DEBUG, "dcheckid:" ZBX_FS_UI64 " either does not exist or does not"
						" belong to druleid:" ZBX_FS_UI64, dcheckid, drule.druleid);

				continue;
			}

			discovery_update_service(&drule, dcheckid, &dhost, ip, dns, port, status, value, itemtime);
		}

		DBcommit();

		continue;
json_parse_error:
		*error = zbx_strdup(*error, zbx_json_strerror());
		ret = FAIL;
		break;
	}

	zbx_free(value);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: process_discovery_data                                           *
 *                                                                            *
 * Purpose: update discovery data, received from proxy                        *
 *                                                                            *
 * Parameters: jp           - [IN] JSON with historical data                  *
 *             ts           - [IN] timestamp when the proxy connection was    *
 *                                 established                                *
 *             error        - [OUT] address of a pointer to the info string   *
 *                                  (should be freed by the caller)           *
 *                                                                            *
 * Return value:  SUCCEED - processed successfully                            *
 *                FAIL - an error occurred                                    *
 *                                                                            *
 ******************************************************************************/
int	process_discovery_data(struct zbx_json_parse *jp, zbx_timespec_t *ts, char **error)
{
	const char		*__function_name = "process_discovery_data";
	int			ret;
	struct zbx_json_parse	jp_data;
	zbx_timespec_t		ts_diff;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (SUCCEED != (ret = get_client_timediff(jp, ts, &ts_diff)))
	{
		*error = zbx_strdup(*error, zbx_json_strerror());
		goto out;
	}

	if (SUCCEED != (ret = zbx_json_brackets_by_name(jp, ZBX_PROTO_TAG_DATA, &jp_data)))
	{
		*error = zbx_strdup(*error, zbx_json_strerror());
		goto out;
	}

	ret = process_discovery_data_contents(&jp_data, &ts_diff, error);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: process_auto_registration_contents                               *
 *                                                                            *
 * Purpose: parse auto registration data contents and process it              *
 *                                                                            *
 * Parameters: jp_data      - [IN] JSON with auto registration data           *
 *             proxy_hostid - [IN] proxy identifier from database             *
 *             ts_diff      - [IN] proxy - server timestamp difference        *
 *             info         - [OUT] address of a pointer to the info string   *
 *                                  (should be freed by the caller)           *
 *                                                                            *
 * Return value:  SUCCEED - processed successfully                            *
 *                FAIL - an error occurred                                    *
 *                                                                            *
 ******************************************************************************/
static int	process_auto_registration_contents(struct zbx_json_parse *jp_data, zbx_uint64_t proxy_hostid,
		zbx_timespec_t *ts_diff, char **error)
{
	const char		*__function_name = "process_auto_registration_contents";

	struct zbx_json_parse	jp_row;
	int			ret = SUCCEED;
	const char		*p = NULL;
	time_t			itemtime;
	char			host[HOST_HOST_LEN_MAX], ip[INTERFACE_IP_LEN_MAX], dns[INTERFACE_DNS_LEN_MAX],
				tmp[MAX_STRING_LEN], *host_metadata = NULL;
	unsigned short		port;
	size_t			host_metadata_alloc = 1;	/* for at least NUL-termination char */
	zbx_vector_ptr_t	autoreg_hosts;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_ptr_create(&autoreg_hosts);
	host_metadata = zbx_malloc(host_metadata, host_metadata_alloc);

	while (NULL != (p = zbx_json_next(jp_data, p)))
	{
		if (FAIL == (ret = zbx_json_brackets_open(p, &jp_row)))
			break;

		if (FAIL == (ret = zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_CLOCK, tmp, sizeof(tmp))))
			break;

		itemtime = atoi(tmp) + ts_diff->sec;

		if (FAIL == (ret = zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_HOST, host, sizeof(host))))
			break;

		if (FAIL == zbx_json_value_by_name_dyn(&jp_row, ZBX_PROTO_TAG_HOST_METADATA,
				&host_metadata, &host_metadata_alloc))
		{
			*host_metadata = '\0';
		}

		if (FAIL == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_IP, ip, sizeof(ip)))
			*ip = '\0';

		if (FAIL == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_DNS, dns, sizeof(dns)))
			*dns = '\0';

		if (FAIL == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_PORT, tmp, sizeof(tmp)))
			*tmp = '\0';

		if (FAIL == is_ushort(tmp, &port))
			port = ZBX_DEFAULT_AGENT_PORT;

		DBregister_host_prepare(&autoreg_hosts, host, ip, dns, port, host_metadata, itemtime);
	}

	if (0 != autoreg_hosts.values_num)
	{
		DBbegin();
		DBregister_host_flush(&autoreg_hosts, proxy_hostid);
		DBcommit();
	}

	zbx_free(host_metadata);
	DBregister_host_clean(&autoreg_hosts);
	zbx_vector_ptr_destroy(&autoreg_hosts);

	if (SUCCEED != ret)
		*error = zbx_strdup(*error, zbx_json_strerror());

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: process_auto_registration                                        *
 *                                                                            *
 * Purpose: update auto registration data, received from proxy                *
 *                                                                            *
 * Parameters: jp           - [IN] JSON with historical data                  *
 *             proxy_hostid - [IN] proxy identifier from database             *
 *             ts           - [IN] timestamp when the proxy connection was    *
 *                                 established                                *
 *             error        - [OUT] address of a pointer to the info string   *
 *                                  (should be freed by the caller)           *
 *                                                                            *
 * Return value:  SUCCEED - processed successfully                            *
 *                FAIL - an error occurred                                    *
 *                                                                            *
 ******************************************************************************/
int	process_auto_registration(struct zbx_json_parse *jp, zbx_uint64_t proxy_hostid, zbx_timespec_t *ts,
		char **error)
{
	const char		*__function_name = "process_auto_registration";

	struct zbx_json_parse	jp_data;
	int			ret;
	zbx_timespec_t		ts_diff;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (SUCCEED != (ret = get_client_timediff(jp, ts, &ts_diff)))
	{
		*error = zbx_strdup(*error, zbx_json_strerror());
		goto out;
	}

	if (SUCCEED != (ret = zbx_json_brackets_by_name(jp, ZBX_PROTO_TAG_DATA, &jp_data)))
	{
		*error = zbx_strdup(*error, zbx_json_strerror());
		goto out;
	}

	ret = process_auto_registration_contents(&jp_data, proxy_hostid, &ts_diff, error);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: proxy_get_history_count                                          *
 *                                                                            *
 * Purpose: get the number of values waiting to be sent to the sever          *
 *                                                                            *
 * Return value: the number of history values                                 *
 *                                                                            *
 ******************************************************************************/
int	proxy_get_history_count(void)
{
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	id;
	int 		count = 0;

	proxy_get_lastid("proxy_history", "history_lastid", &id);

	result = DBselect(
			"select count(*)"
			" from proxy_history"
			" where id>" ZBX_FS_UI64,
			id);

	if (NULL != (row = DBfetch(result)))
		count = atoi(row[0]);

	DBfree_result(result);

	return count;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_proxy_update_version                                         *
 *                                                                            *
 * Purpose: updates proxy version based on the received version field         *
 *                                                                            *
 * Parameters:                                                                *
 *     jp      - [IN] JSON with the proxy version                             *
 *     version - [OUT] proxy version                                          *
 *                                                                            *
 * Return value:                                                              *
 *     SUCCEED - proxy version was successfully extracted                     *
 *     FAIL    - otherwise                                                    *
 *                                                                            *
 ******************************************************************************/
int	zbx_proxy_update_version(const DC_PROXY *proxy, struct zbx_json_parse *jp)
{
	char	value[MAX_STRING_LEN], *pminor, *ptr;
	int	version;

	if (NULL != jp &&
			SUCCEED == zbx_json_value_by_name(jp, ZBX_PROTO_TAG_VERSION, value, sizeof(value)) &&
			NULL != (pminor = strchr(value, '.')))
	{
		*pminor++ = '\0';

		if (NULL != (ptr = strchr(pminor, '.')))
			*ptr = '\0';

		version = ZBX_COMPONENT_VERSION(atoi(value), atoi(pminor));
	}
	else
		version = ZBX_COMPONENT_VERSION(3, 2);

	if (proxy->version != version)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "proxy \"%s\" version updated from %d.%d to %d.%d", proxy->host,
				ZBX_COMPONENT_VERSION_MAJOR(proxy->version),
				ZBX_COMPONENT_VERSION_MINOR(proxy->version),
				ZBX_COMPONENT_VERSION_MAJOR(version),
				ZBX_COMPONENT_VERSION_MINOR(version));

		zbx_dc_update_proxy_version(proxy->hostid, version);
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: process_proxy_history_data_33                                    *
 *                                                                            *
 * Purpose: parses history data array and process the data                    *
 *                                                                            *
 * Parameters: jp_data      - [IN] JSON with history data array               *
 *             ts_diff      - [IN] proxy - server timestamp difference        *
 *             info         - [OUT] address of a pointer to the info string   *
 *                                  (should be freed by the caller)           *
 *                                                                            *
 * Return value:  SUCCEED - processed successfully                            *
 *                FAIL - an error occurred                                    *
 *                                                                            *
 * Comments: This function is used to parse the new proxy history data        *
 *           protocol introduced in Zabbix v3.3                               *
 *                                                                            *
 ******************************************************************************/
static int	process_proxy_history_data_33(const DC_PROXY *proxy, struct zbx_json_parse *jp_data,
		zbx_timespec_t *ts_diff, char **info)
{
	const char		*__function_name = "process_proxy_history_data_33";

	const char		*pnext = NULL;
	int			ret = SUCCEED, processed_num = 0, total_num = 0, values_num, read_num, i, *errcodes;
	double			sec;
	zbx_agent_value_t	values[ZBX_HISTORY_VALUES_MAX];
	zbx_uint64_t		itemids[ZBX_HISTORY_VALUES_MAX];
	DC_ITEM			*items;
	char			*error = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	items = zbx_malloc(NULL, sizeof(DC_ITEM) * ZBX_HISTORY_VALUES_MAX);
	errcodes = zbx_malloc(NULL, sizeof(int) * ZBX_HISTORY_VALUES_MAX);

	sec = zbx_time();

	while (SUCCEED == parse_history_data_33(jp_data, &pnext, values, itemids, &values_num, &read_num, ts_diff,
			&error) && 0 != values_num)
	{
		DCconfig_get_items_by_itemids(items, itemids, errcodes, values_num);

		for (i = 0; i < values_num; i++)
		{
			if (SUCCEED != errcodes[i])
				continue;

			if (SUCCEED != proxy_item_validator(&items[i], NULL, (void *)&proxy->hostid, &error))
			{
				if (NULL != error)
				{
					zabbix_log(LOG_LEVEL_WARNING, "%s", error);
					zbx_free(error);
				}

				DCconfig_clean_items(&items[i], &errcodes[i], 1);
				errcodes[i] = FAIL;
			}
		}

		processed_num += process_history_data(items, values, errcodes, values_num);
		DCconfig_clean_items(items, errcodes, values_num);

		total_num += read_num;

		DCconfig_clean_items(items, errcodes, values_num);
		zbx_agent_values_clean(values, values_num);

		if (NULL == pnext)
			break;
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

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_strcatnl_alloc                                               *
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
 * Function: process_proxy_data                                               *
 *                                                                            *
 * Purpose: process 'proxy data' request                                      *
 *                                                                            *
 * Parameters: proxy        - [IN] the source proxy                           *
 *             jp           - [IN] JSON with proxy data                       *
 *             proxy_hostid - [IN] proxy identifier from database             *
 *             ts           - [IN] timestamp when the proxy connection was    *
 *                                 established                                *
 *             error        - [OUT] address of a pointer to the info string   *
 *                                  (should be freed by the caller)           *
 *                                                                            *
 * Return value:  SUCCEED - processed successfully                            *
 *                FAIL - an error occurred                                    *
 *                                                                            *
 ******************************************************************************/
int	process_proxy_data(const DC_PROXY *proxy, struct zbx_json_parse *jp, zbx_timespec_t *ts, char **error)
{
	const char		*__function_name = "process_proxy_data";

	struct zbx_json_parse	jp_data;
	int			ret = SUCCEED;
	zbx_timespec_t		ts_diff;
	char			*error_step = NULL;
	size_t			error_alloc = 0, error_offset = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (SUCCEED != (ret = get_client_timediff(jp, ts, &ts_diff)))
	{
		*error = zbx_strdup(*error, zbx_json_strerror());
		goto out;
	}

	DCconfig_set_proxy_timediff(proxy->hostid, &ts_diff);

	if (SUCCEED == zbx_json_brackets_by_name(jp, ZBX_PROTO_TAG_HOST_AVAILABILITY, &jp_data))
	{
		if (SUCCEED != process_host_availability_contents(&jp_data, &error_step))
			zbx_strcatnl_alloc(error, &error_alloc, &error_offset, error_step);
	}

	if (SUCCEED == zbx_json_brackets_by_name(jp, ZBX_PROTO_TAG_HISTORY_DATA, &jp_data))
	{
		process_proxy_history_data_33(proxy, &jp_data, &ts_diff, &error_step);
		zbx_strcatnl_alloc(error, &error_alloc, &error_offset, error_step);
	}

	if (SUCCEED == zbx_json_brackets_by_name(jp, ZBX_PROTO_TAG_DISCOVERY_DATA, &jp_data))
	{
		if (SUCCEED != process_discovery_data_contents(&jp_data, &ts_diff, &error_step))
			zbx_strcatnl_alloc(error, &error_alloc, &error_offset, error_step);
	}

	if (SUCCEED == zbx_json_brackets_by_name(jp, ZBX_PROTO_TAG_AUTO_REGISTRATION, &jp_data))
	{
		if (SUCCEED != process_auto_registration_contents(&jp_data, proxy->hostid, &ts_diff, &error_step))
			zbx_strcatnl_alloc(error, &error_alloc, &error_offset, error_step);
	}

	zbx_free(error_step);
	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}
