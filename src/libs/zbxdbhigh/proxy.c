/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

#include "proxy.h"

#include "zbxdbhigh.h"
#include "log.h"
#include "sysinfo.h"
#include "zbxserver.h"
#include "zbxtasks.h"

#include "zbxdiscovery.h"
#include "zbxalgo.h"
#include "preproc.h"
#include "zbxcrypto.h"
#include "../zbxkvs/kvs.h"
#include "zbxlld.h"
#include "events.h"
#include "../zbxvault/vault.h"
#include "zbxavailability.h"
#include "zbxcommshigh.h"

extern char	*CONFIG_SERVER;
extern char	*CONFIG_VAULTDBPATH;

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

extern unsigned int	configured_tls_accept_modes;

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

typedef struct
{
	zbx_uint64_t	id;
	size_t		offset;
}
zbx_id_offset_t;

typedef int	(*zbx_client_item_validator_t)(DC_ITEM *item, zbx_socket_t *sock, void *args, char **error);

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

typedef struct
{
	char		*path;
	zbx_hashset_t	keys;
}
zbx_keys_path_t;

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
static int	zbx_host_check_permissions(const DC_HOST *host, const zbx_socket_t *sock, char **error)
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
int	get_active_proxy_from_request(struct zbx_json_parse *jp, DC_PROXY *proxy, char **error)
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

	if (FAIL == zbx_tcp_check_allowed_peers(sock, CONFIG_SERVER))
	{
		zabbix_log(LOG_LEVEL_WARNING, "%s from server \"%s\" is not allowed: %s", req, sock->peer,
				zbx_socket_strerror());

		if (ZBX_SEND_RESPONSE == send_response)
			zbx_send_proxy_response(sock, FAIL, "connection is not allowed", CONFIG_TIMEOUT);

		return FAIL;
	}

	if (0 == (configured_tls_accept_modes & sock->connection_type))
	{
		msg = zbx_dsprintf(NULL, "%s over connection of type \"%s\" is not allowed", req,
				zbx_tcp_connection_type_name(sock->connection_type));

		zabbix_log(LOG_LEVEL_WARNING, "%s from server \"%s\" by proxy configuration parameter \"TLSAccept\"",
				msg, sock->peer);

		if (ZBX_SEND_RESPONSE == send_response)
			zbx_send_proxy_response(sock, FAIL, msg, CONFIG_TIMEOUT);

		zbx_free(msg);
		return FAIL;
	}

#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	if (ZBX_TCP_SEC_TLS_CERT == sock->connection_type)
	{
		if (SUCCEED == zbx_check_server_issuer_subject(sock, &msg))
			return SUCCEED;

		zabbix_log(LOG_LEVEL_WARNING, "%s from server \"%s\" is not allowed: %s", req, sock->peer, msg);

		if (ZBX_SEND_RESPONSE == send_response)
			zbx_send_proxy_response(sock, FAIL, "certificate issuer or subject mismatch", CONFIG_TIMEOUT);

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
			zbx_send_proxy_response(sock, FAIL, "wrong PSK used", CONFIG_TIMEOUT);

		return FAIL;
	}
#endif
	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: add database row to the proxy config json data                    *
 *                                                                            *
 * Parameters: j     - [OUT] the output json                                  *
 *             row   - [IN] the database row to add                           *
 *             table - [IN] the table configuration                           *
 *                                                                            *
 ******************************************************************************/
static void	proxyconfig_add_row(struct zbx_json *j, const DB_ROW row, const ZBX_TABLE *table)
{
	int	fld = 0, i;

	zbx_json_addstring(j, NULL, row[fld++], ZBX_JSON_TYPE_INT);

	for (i = 0; 0 != table->fields[i].name; i++)
	{
		if (0 == (table->fields[i].flags & ZBX_PROXY))
			continue;

		switch (table->fields[i].type)
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
}

typedef struct
{
	zbx_uint64_t	itemid;
	zbx_uint64_t	master_itemid;
	char		*buffer;
}
zbx_proxy_item_config_t;

/******************************************************************************
 *                                                                            *
 * Purpose: prepare items table proxy configuration data                      *
 *                                                                            *
 ******************************************************************************/
static int	get_proxyconfig_table_items(zbx_uint64_t proxy_hostid, struct zbx_json *j, const ZBX_TABLE *table,
		zbx_hashset_t *itemids)
{
	char			*sql = NULL;
	size_t			sql_alloc = 4 * ZBX_KIBIBYTE, sql_offset = 0;
	int			f, fld, fld_type = -1, fld_key = -1, fld_master = -1, ret = SUCCEED;
	DB_RESULT		result;
	DB_ROW			row;
	zbx_hashset_t		proxy_items;
	zbx_vector_ptr_t	items;
	zbx_uint64_t		itemid;
	zbx_hashset_iter_t	iter;
	struct zbx_json		json_array;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() proxy_hostid:" ZBX_FS_UI64, __func__, proxy_hostid);

	zbx_json_addobject(j, table->table);
	zbx_json_addarray(j, "fields");

	sql = (char *)zbx_malloc(sql, sql_alloc);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "select t.%s", table->recid);

	zbx_json_addstring(j, NULL, table->recid, ZBX_JSON_TYPE_STRING);

	for (f = 0, fld = 1; 0 != table->fields[f].name; f++)
	{
		if (0 == (table->fields[f].flags & ZBX_PROXY))
			continue;

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ",t.");
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, table->fields[f].name);

		zbx_json_addstring(j, NULL, table->fields[f].name, ZBX_JSON_TYPE_STRING);

		if (0 == strcmp(table->fields[f].name, "type"))
			fld_type = fld;
		else if (0 == strcmp(table->fields[f].name, "key_"))
			fld_key = fld;
		else if (0 == strcmp(table->fields[f].name, "master_itemid"))
			fld_master = fld;
		fld++;
	}

	if (-1 == fld_type || -1 == fld_key || -1 == fld_master)
	{
		THIS_SHOULD_NEVER_HAPPEN;
		exit(EXIT_FAILURE);
	}

	zbx_json_close(j);	/* fields */

	zbx_json_addarray(j, "data");

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			" from items t,hosts r where t.hostid=r.hostid"
				" and r.proxy_hostid=" ZBX_FS_UI64
				" and r.status in (%d,%d)"
				" and t.flags<>%d"
				" and t.type in (%d,%d,%d,%d,%d,%d,%d,%d,%d,%d,%d,%d,%d,%d,%d,%d,%d)"
			" order by t.%s",
			proxy_hostid,
			HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED,
			ZBX_FLAG_DISCOVERY_PROTOTYPE,
			ITEM_TYPE_ZABBIX, ITEM_TYPE_ZABBIX_ACTIVE, ITEM_TYPE_SNMP, ITEM_TYPE_IPMI, ITEM_TYPE_TRAPPER,
			ITEM_TYPE_SIMPLE, ITEM_TYPE_HTTPTEST, ITEM_TYPE_EXTERNAL, ITEM_TYPE_DB_MONITOR, ITEM_TYPE_SSH,
			ITEM_TYPE_TELNET, ITEM_TYPE_JMX, ITEM_TYPE_SNMPTRAP, ITEM_TYPE_INTERNAL,
			ITEM_TYPE_HTTPAGENT, ITEM_TYPE_DEPENDENT, ITEM_TYPE_SCRIPT,
			table->recid);

	if (NULL == (result = DBselect("%s", sql)))
	{
		ret = FAIL;
		goto skip_data;
	}

	zbx_hashset_create(&proxy_items, 1000, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_json_initarray(&json_array, 256);

	while (NULL != (row = DBfetch(result)))
	{
		if (SUCCEED == is_item_processed_by_server(atoi(row[fld_type]), row[fld_key]))
			continue;

		if (SUCCEED != DBis_null(row[fld_master]))
		{
			zbx_proxy_item_config_t	proxy_item_local, *proxy_item;

			ZBX_STR2UINT64(proxy_item_local.itemid, row[0]);
			ZBX_STR2UINT64(proxy_item_local.master_itemid, row[fld_master]);
			proxy_item = zbx_hashset_insert(&proxy_items, &proxy_item_local, sizeof(proxy_item_local));

			proxyconfig_add_row(&json_array, row, table);

			proxy_item->buffer = zbx_malloc(NULL, json_array.buffer_size + 1);
			memcpy(proxy_item->buffer, json_array.buffer, json_array.buffer_size + 1);

			zbx_json_cleanarray(&json_array);
		}
		else
		{
			ZBX_STR2UINT64(itemid, row[0]);
			zbx_hashset_insert(itemids, &itemid, sizeof(itemid));

			zbx_json_addarray(j, NULL);
			proxyconfig_add_row(j, row, table);
			zbx_json_close(j);
		}
	}
	DBfree_result(result);
	zbx_json_free(&json_array);

	/* flush cached dependent items */

	zbx_vector_ptr_create(&items);
	while (0 != proxy_items.num_data)
	{
		zbx_proxy_item_config_t	*proxy_item;
		int			i;

		zbx_hashset_iter_reset(&proxy_items, &iter);
		while (NULL != (proxy_item = (zbx_proxy_item_config_t *)zbx_hashset_iter_next(&iter)))
		{
			if (NULL == zbx_hashset_search(&proxy_items, &proxy_item->master_itemid))
				zbx_vector_ptr_append(&items, proxy_item);
		}

		if (0 == items.values_num)
		{
			THIS_SHOULD_NEVER_HAPPEN;
			exit(EXIT_FAILURE);
		}

		zbx_vector_ptr_sort(&items, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);
		for (i = 0; i < items.values_num; i++)
		{
			proxy_item = (zbx_proxy_item_config_t *)items.values[i];
			if (NULL != zbx_hashset_search(itemids, &proxy_item->master_itemid))
			{
				zbx_hashset_insert(itemids, &proxy_item->itemid, sizeof(itemid));
				zbx_json_addraw(j, NULL, proxy_item->buffer);
			}
			zbx_free(proxy_item->buffer);
			zbx_hashset_remove_direct(&proxy_items, proxy_item);
		}

		zbx_vector_ptr_clear(&items);
	}
	zbx_vector_ptr_destroy(&items);
	zbx_hashset_destroy(&proxy_items);
skip_data:
	zbx_free(sql);

	zbx_json_close(j);	/* data */
	zbx_json_close(j);	/* table->table */

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: prepare items table proxy configuration data                      *
 *                                                                            *
 ******************************************************************************/
static int	get_proxyconfig_table_items_ext(zbx_uint64_t proxy_hostid, const zbx_hashset_t *itemids,
		struct zbx_json *j, const ZBX_TABLE *table)
{
	char		*sql = NULL;
	size_t		sql_alloc = 4 * ZBX_KIBIBYTE, sql_offset = 0;
	int		f, ret = SUCCEED, index = 1, itemid_index = 0;
	DB_RESULT	result;
	DB_ROW		row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() table:%s", __func__, table->table);

	zbx_json_addobject(j, table->table);
	zbx_json_addarray(j, "fields");

	sql = (char *)zbx_malloc(sql, sql_alloc);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "select t.%s", table->recid);

	zbx_json_addstring(j, NULL, table->recid, ZBX_JSON_TYPE_STRING);

	for (f = 0; 0 != table->fields[f].name; f++)
	{
		if (0 == (table->fields[f].flags & ZBX_PROXY))
			continue;

		/* either the table uses itemid as primary key, then it will be stored in the */
		/* first (0) column as record id, or it will have reference to items table    */
		/* through itemid field                                                       */
		if (0 == strcmp(table->fields[f].name, "itemid"))
			itemid_index = index;

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ",t.");
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, table->fields[f].name);
		zbx_json_addstring(j, NULL, table->fields[f].name, ZBX_JSON_TYPE_STRING);
		index++;
	}

	zbx_json_close(j);	/* fields */

	zbx_json_addarray(j, "data");

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			" from %s t,items i,hosts h"
			" where t.itemid=i.itemid"
				" and i.hostid=h.hostid"
				" and h.proxy_hostid=" ZBX_FS_UI64,
				table->table, proxy_hostid);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " order by t.");
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, table->recid);

	if (NULL == (result = DBselect("%s", sql)))
	{
		ret = FAIL;
		goto skip_data;
	}

	while (NULL != (row = DBfetch(result)))
	{
		zbx_uint64_t	itemid;

		ZBX_STR2UINT64(itemid, row[itemid_index]);
		if (NULL != zbx_hashset_search((zbx_hashset_t *)itemids, &itemid))
		{
			zbx_json_addarray(j, NULL);
			proxyconfig_add_row(j, row, table);
			zbx_json_close(j);
		}
	}
	DBfree_result(result);
skip_data:
	zbx_free(sql);

	zbx_json_close(j);	/* data */
	zbx_json_close(j);	/* table->table */

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

static int	keys_path_compare(const void *d1, const void *d2)
{
	const zbx_keys_path_t	*ptr1 = *((const zbx_keys_path_t **)d1);
	const zbx_keys_path_t	*ptr2 = *((const zbx_keys_path_t **)d2);

	return strcmp(ptr1->path, ptr2->path);
}

static zbx_hash_t	keys_hash(const void *data)
{
	return ZBX_DEFAULT_STRING_HASH_ALGO(*(const char **)data, strlen(*(const char **)data), ZBX_DEFAULT_HASH_SEED);
}

static int	keys_compare(const void *d1, const void *d2)
{
	return strcmp(*(const char **)d1, *(const char **)d2);
}

static void	key_path_free(void *data)
{
	zbx_hashset_iter_t	iter;
	char			**ptr;
	zbx_keys_path_t		*keys_path = (zbx_keys_path_t *)data;

	zbx_hashset_iter_reset(&keys_path->keys, &iter);
	while (NULL != (ptr = (char **)zbx_hashset_iter_next(&iter)))
		zbx_free(*ptr);
	zbx_hashset_destroy(&keys_path->keys);

	zbx_free(keys_path->path);
	zbx_free(keys_path);
}

/******************************************************************************
 *                                                                            *
 * Purpose: prepare proxy configuration data                                  *
 *                                                                            *
 ******************************************************************************/
static int	get_proxyconfig_table(zbx_uint64_t proxy_hostid, struct zbx_json *j, const ZBX_TABLE *table,
		const zbx_vector_uint64_t *hosts, const zbx_vector_uint64_t *httptests, zbx_vector_ptr_t *keys_paths)
{
	char			*sql = NULL;
	size_t			sql_alloc = 4 * ZBX_KIBIBYTE, sql_offset = 0;
	int			f, ret = SUCCEED, i, is_macro = 0;
	DB_RESULT		result;
	DB_ROW			row;
	int			offset;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() proxy_hostid:" ZBX_FS_UI64 " table:'%s'",
			__func__, proxy_hostid, table->table);

	if (0 == strcmp(table->table, "globalmacro"))
	{
		is_macro = 1;
		offset = 0;
	}
	else if (0 == strcmp(table->table, "hostmacro"))
	{
		is_macro = 1;
		offset = 1;
	}

	zbx_json_addobject(j, table->table);
	zbx_json_addarray(j, "fields");

	sql = (char *)zbx_malloc(sql, sql_alloc);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "select t.%s", table->recid);

	zbx_json_addstring(j, NULL, table->recid, ZBX_JSON_TYPE_STRING);

	for (f = 0; 0 != table->fields[f].name; f++)
	{
		if (0 == (table->fields[f].flags & ZBX_PROXY))
			continue;

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ",t.");
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, table->fields[f].name);

		zbx_json_addstring(j, NULL, table->fields[f].name, ZBX_JSON_TYPE_STRING);
	}

	zbx_json_close(j);	/* fields */

	zbx_json_addarray(j, "data");

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " from %s t", table->table);

	if (SUCCEED == str_in_list("hosts,interface,host_inventory,hosts_templates,hostmacro", table->table, ','))
	{
		if (0 == hosts->values_num)
			goto skip_data;

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "t.hostid", hosts->values, hosts->values_num);
	}
	else if (0 == strcmp(table->table, "interface_snmp"))
	{
		if (0 == hosts->values_num)
			goto skip_data;

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ",interface h where t.interfaceid=h.interfaceid and");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "h.hostid", hosts->values, hosts->values_num);
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
	else if (0 == strcmp(table->table, "hstgrp"))
	{
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ",config r where t.groupid=r.discovery_groupid");
	}
	else if (SUCCEED == str_in_list("httptest,httptest_field,httptestitem,httpstep", table->table, ','))
	{
		if (0 == httptests->values_num)
			goto skip_data;

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "t.httptestid",
				httptests->values, httptests->values_num);
	}
	else if (SUCCEED == str_in_list("httpstepitem,httpstep_field", table->table, ','))
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
		zbx_json_addarray(j, NULL);
		proxyconfig_add_row(j, row, table);
		zbx_json_close(j);
		if (1 == is_macro)
		{
			zbx_keys_path_t	*keys_path, keys_path_local;
			unsigned char	type;
			char		*path, *key;

			ZBX_STR2UCHAR(type, row[3 + offset]);

			if (ZBX_MACRO_VALUE_VAULT != type)
				continue;

			zbx_strsplit_last(row[2 + offset], ':', &path, &key);

			if (NULL == key)
			{
				zabbix_log(LOG_LEVEL_WARNING, "cannot parse macro \"%s\" value \"%s\"",
						row[1 + offset], row[2 + offset]);
				goto next;
			}

			if (NULL != CONFIG_VAULTDBPATH && 0 == strcasecmp(CONFIG_VAULTDBPATH, path) &&
					(0 == strcasecmp(key, ZBX_PROTO_TAG_PASSWORD)
							|| 0 == strcasecmp(key, ZBX_PROTO_TAG_USERNAME)))
			{
				zabbix_log(LOG_LEVEL_WARNING, "cannot parse macro \"%s\" value \"%s\":"
						" database credentials should not be used with Vault macros",
						row[1 + offset], row[2 + offset]);
				goto next;
			}

			keys_path_local.path = path;

			if (FAIL == (i = zbx_vector_ptr_search(keys_paths, &keys_path_local, keys_path_compare)))
			{
				keys_path = zbx_malloc(NULL, sizeof(zbx_keys_path_t));
				keys_path->path = path;

				zbx_hashset_create(&keys_path->keys, 0, keys_hash, keys_compare);
				zbx_hashset_insert(&keys_path->keys, &key, sizeof(char **));

				zbx_vector_ptr_append(keys_paths, keys_path);
				path = key = NULL;
			}
			else
			{
				keys_path = (zbx_keys_path_t *)keys_paths->values[i];
				if (NULL == zbx_hashset_search(&keys_path->keys, &key))
				{
					zbx_hashset_insert(&keys_path->keys, &key, sizeof(char **));
					key = NULL;
				}
			}
next:
			zbx_free(key);
			zbx_free(path);
		}
	}
	DBfree_result(result);
skip_data:
	zbx_free(sql);

	zbx_json_close(j);	/* data */
	zbx_json_close(j);	/* table->table */

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

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

	sql = (char *)zbx_malloc(sql, sql_alloc * sizeof(char));

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

static void	get_macro_secrets(const zbx_vector_ptr_t *keys_paths, struct zbx_json *j)
{
	int		i;
	zbx_kvs_t	kvs;

	zbx_kvs_create(&kvs, 100);

	zbx_json_addobject(j, "macro.secrets");

	for (i = 0; i < keys_paths->values_num; i++)
	{
		zbx_keys_path_t		*keys_path;
		char			*error = NULL, **ptr;
		zbx_hashset_iter_t	iter;

		keys_path = (zbx_keys_path_t *)keys_paths->values[i];
		if (FAIL == zbx_vault_kvs_get(keys_path->path, &kvs, &error))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot get secrets for path \"%s\": %s", keys_path->path, error);
			zbx_free(error);
			continue;
		}

		zbx_json_addobject(j, keys_path->path);

		zbx_hashset_iter_reset(&keys_path->keys, &iter);
		while (NULL != (ptr = (char **)zbx_hashset_iter_next(&iter)))
		{
			zbx_kv_t	*kv, kv_local;

			kv_local.key = *ptr;

			if (NULL != (kv = zbx_kvs_search(&kvs, &kv_local)))
				zbx_json_addstring(j, kv->key, kv->value, ZBX_JSON_TYPE_STRING);
		}
		zbx_json_close(j);

		zbx_kvs_clear(&kvs);
	}

	zbx_json_close(j);
	zbx_kvs_destroy(&kvs);
}

/******************************************************************************
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
		"interface_snmp",
		"host_inventory",
		"hosts_templates",
		"hostmacro",
		"items",
		"item_rtdata",
		"item_preproc",
		"item_parameter",
		"drules",
		"dchecks",
		"regexps",
		"expressions",
		"hstgrp",
		"config",
		"httptest",
		"httptestitem",
		"httptest_field",
		"httpstep",
		"httpstepitem",
		"httpstep_field",
		"config_autoreg_tls",
		NULL
	};

	int			i, ret = FAIL;
	const ZBX_TABLE		*table;
	zbx_vector_uint64_t	hosts, httptests;
	zbx_hashset_t		itemids;
	zbx_vector_ptr_t	keys_paths;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() proxy_hostid:" ZBX_FS_UI64, __func__, proxy_hostid);

	zbx_hashset_create(&itemids, 1000, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_create(&hosts);
	zbx_vector_uint64_create(&httptests);
	zbx_vector_ptr_create(&keys_paths);

	DBbegin();
	get_proxy_monitored_hosts(proxy_hostid, &hosts);
	get_proxy_monitored_httptests(proxy_hostid, &httptests);

	for (i = 0; NULL != proxytable[i]; i++)
	{
		table = DBget_table(proxytable[i]);

		if (0 == strcmp(proxytable[i], "items"))
		{
			ret = get_proxyconfig_table_items(proxy_hostid, j, table, &itemids);
		}
		else if (0 == strcmp(proxytable[i], "item_preproc") || 0 == strcmp(proxytable[i], "item_rtdata") ||
				0 == strcmp(proxytable[i], "item_parameter"))
		{
			if (0 != itemids.num_data)
				ret = get_proxyconfig_table_items_ext(proxy_hostid, &itemids, j, table);
		}
		else
			ret = get_proxyconfig_table(proxy_hostid, j, table, &hosts, &httptests, &keys_paths);

		if (SUCCEED != ret)
		{
			*error = zbx_dsprintf(*error, "failed to get data from table \"%s\"", table->table);
			goto out;
		}
	}

	get_macro_secrets(&keys_paths, j);

	ret = SUCCEED;
out:
	DBcommit();
	zbx_vector_ptr_clear_ext(&keys_paths, key_path_free);
	zbx_vector_ptr_destroy(&keys_paths);
	zbx_vector_uint64_destroy(&httptests);
	zbx_vector_uint64_destroy(&hosts);
	zbx_hashset_destroy(&itemids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
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
	const zbx_id_offset_t *p = (zbx_id_offset_t *)data;

	return ZBX_DEFAULT_UINT64_HASH_ALGO(&p->id, sizeof(zbx_uint64_t), ZBX_DEFAULT_HASH_SEED);
}

static int	id_offset_compare_func(const void *d1, const void *d2)
{
	const zbx_id_offset_t *p1 = (zbx_id_offset_t *)d1, *p2 = (zbx_id_offset_t *)d2;

	return ZBX_DEFAULT_UINT64_COMPARE_FUNC(&p1->id, &p2->id);
}

/******************************************************************************
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
 * Purpose: update configuration table                                        *
 *                                                                            *
 * Parameters: ...                                                            *
 *             del - [OUT] ids of the removed records that must be deleted    *
 *                         from database                                      *
 *                                                                            *
 * Return value: SUCCEED - processed successfully                             *
 *               FAIL - an error occurred                                     *
 *                                                                            *
 ******************************************************************************/
static int	process_proxyconfig_table(const ZBX_TABLE *table, struct zbx_json_parse *jp_obj,
		zbx_vector_uint64_t *del, char **error)
{
	int			f, fields_count, ret = FAIL, id_field_nr = 0, move_out = 0,
				move_field_nr = 0;
	const ZBX_FIELD		*fields[ZBX_MAX_FIELDS];
	struct zbx_json_parse	jp_data, jp_row;
	const char		*p, *pf;
	zbx_uint64_t		recid, *p_recid = NULL;
	zbx_vector_uint64_t	ins, moves, availability_interfaceids;
	char			*buf = NULL, *esc, *sql = NULL, *recs = NULL;
	size_t			sql_alloc = 4 * ZBX_KIBIBYTE, sql_offset,
				recs_alloc = 20 * ZBX_KIBIBYTE, recs_offset = 0,
				buf_alloc = 0;
	DB_RESULT		result;
	DB_ROW			row;
	zbx_hashset_t		h_id_offsets, h_del;
	zbx_hashset_iter_t	iter;
	zbx_id_offset_t		id_offset, *p_id_offset = NULL;
	zbx_db_insert_t		db_insert;
	zbx_vector_ptr_t	values;
	static zbx_vector_ptr_t	skip_fields;
	static const ZBX_TABLE	*table_items, *table_interface;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() table:'%s'", __func__, table->table);

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
		table_items = DBget_table("item_rtdata");

		/* do not update existing lastlogsize and mtime fields */
		zbx_vector_ptr_create(&skip_fields);
		zbx_vector_ptr_append(&skip_fields, (void *)DBget_field(table_items, "lastlogsize"));
		zbx_vector_ptr_append(&skip_fields, (void *)DBget_field(table_items, "mtime"));
		zbx_vector_ptr_sort(&skip_fields, ZBX_DEFAULT_PTR_COMPARE_FUNC);
	}

	if (NULL == table_interface)
		table_interface = DBget_table("interface");

	/* get table columns (line 3 in T1) */
	if (FAIL == zbx_json_brackets_by_name(jp_obj, "fields", &jp_data))
	{
		*error = zbx_strdup(*error, zbx_json_strerror());
		goto out;
	}

	p = NULL;
	/* iterate column names (lines 4-6 in T1) */
	for (fields_count = 0; NULL != (p = zbx_json_next_value_dyn(&jp_data, p, &buf, &buf_alloc, NULL)); fields_count++)
	{
		if (NULL == (fields[fields_count] = DBget_field(table, buf)))
		{
			*error = zbx_dsprintf(*error, "invalid field name \"%s.%s\"", table->table, buf);
			goto out;
		}

		if (0 == (fields[fields_count]->flags & ZBX_PROXY) &&
				(0 != strcmp(table->recid, buf) || ZBX_TYPE_ID != fields[fields_count]->type))
		{
			*error = zbx_dsprintf(*error, "unexpected field \"%s.%s\"", table->table, buf);
			goto out;
		}
	}

	if (0 == fields_count)
	{
		*error = zbx_dsprintf(*error, "empty list of field names");
		goto out;
	}

	/* get the entries (line 8 in T1) */
	if (FAIL == zbx_json_brackets_by_name(jp_obj, ZBX_PROTO_TAG_DATA, &jp_data))
	{
		*error = zbx_strdup(*error, zbx_json_strerror());
		goto out;
	}

	/* all records will be stored in one large string */
	recs = (char *)zbx_malloc(recs, recs_alloc);

	/* hash set as index for fast access to records via IDs */
	zbx_hashset_create(&h_id_offsets, 10000, id_offset_hash_func, id_offset_compare_func);

	/* a hash set as a list for finding records to be deleted */
	zbx_hashset_create(&h_del, 10000, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	sql = (char *)zbx_malloc(sql, sql_alloc);

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

	zbx_vector_uint64_create(&availability_interfaceids);

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
				int		last_n = 0;
				size_t		last_pos = 0;
				zbx_json_type_t	type;

				/* locate a copy of this record as found in database */
				id_offset.id = recid;
				if (NULL == (p_id_offset = (zbx_id_offset_t *)zbx_hashset_search(&h_id_offsets, &id_offset)))
				{
					THIS_SHOULD_NEVER_HAPPEN;
					goto clean2;
				}

				/* find the field requiring special preprocessing in JSON record */
				f = 1;
				while (NULL != (pf = zbx_json_next_value_dyn(&jp_row, pf, &buf, &buf_alloc, &type)))
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
						(ZBX_JSON_TYPE_NULL == type), &last_n, &last_pos))
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
	while (NULL != (p_recid = (uint64_t *)zbx_hashset_iter_next(&iter)))
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
			if (0 != moves.values_num)
			{
				zbx_vector_uint64_append_array(&ins, moves.values, moves.values_num);
				zbx_vector_uint64_sort(&ins, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
				zbx_vector_uint64_append_array(del, moves.values, moves.values_num);
				zbx_vector_uint64_sort(del, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
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
		}
		else
		{
			/* force index field update for removed records to avoid potential conflicts */
			if (0 != del->values_num)
				zbx_vector_uint64_append_array(&moves, del->values, del->values_num);

			/* special preprocessing for 'globalmacro', 'hostmacro', 'items', 'drules', 'regexps' and  */
			/* 'httptest' tables to eliminate conflicts in the 'macro', 'hostid,macro', 'hostid,key_', */
			/* 'name', 'name' and 'hostid,name' unique indices */
			if (0 < moves.values_num)
			{
				sql_offset = 0;
#ifdef HAVE_MYSQL
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
						"update %s set %s=concat('#',%s) where",
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
	}

	/* apply insert operations */

	if (0 != ins.values_num)
	{
		zbx_vector_ptr_create(&values);
		zbx_db_insert_prepare_dyn(&db_insert, table, fields, fields_count);

		p = NULL;
		/* iterate the entries (lines 9, 14 and 19 in T1) */
		while (NULL != (p = zbx_json_next(&jp_data, p)))
		{
			zbx_json_type_t	type;
			zbx_db_value_t	*value;

			if (FAIL == zbx_json_brackets_open(p, &jp_row))
			{
				*error = zbx_dsprintf(*error, "invalid data format: %s", zbx_json_strerror());
				goto clean;
			}

			pf = zbx_json_next_value_dyn(&jp_row, NULL, &buf, &buf_alloc, NULL);

			/* check whether we need to insert a new entry or update an existing one */
			ZBX_STR2UINT64(recid, buf);
			if (FAIL == zbx_vector_uint64_bsearch(&ins, recid, ZBX_DEFAULT_UINT64_COMPARE_FUNC))
				continue;

			/* add the id field */
			value = (zbx_db_value_t *)zbx_malloc(NULL, sizeof(zbx_db_value_t));
			value->ui64 = recid;
			zbx_vector_ptr_append(&values, value);

			/* add the rest of fields */
			for (f = 1; NULL != (pf = zbx_json_next_value_dyn(&jp_row, pf, &buf, &buf_alloc, &type));
					f++)
			{
				if (f == fields_count)
				{
					*error = zbx_dsprintf(*error, "invalid number of fields \"%.*s\"",
							(int)(jp_row.end - jp_row.start + 1), jp_row.start);
					goto clean;
				}

				if (ZBX_JSON_TYPE_NULL == type && 0 != (fields[f]->flags & ZBX_NOTNULL))
				{
					*error = zbx_dsprintf(*error, "column \"%s.%s\" cannot be null",
							table->table, fields[f]->name);
					goto clean;
				}

				value = (zbx_db_value_t *)zbx_malloc(NULL, sizeof(zbx_db_value_t));

				switch (fields[f]->type)
				{
					case ZBX_TYPE_INT:
						value->i32 = atoi(buf);
						break;
					case ZBX_TYPE_UINT:
						ZBX_STR2UINT64(value->ui64, buf);
						break;
					case ZBX_TYPE_ID:
						if (ZBX_JSON_TYPE_NULL != type)
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
						value = (zbx_db_value_t *)values.values[f];
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

		if (FAIL == zbx_db_insert_execute(&db_insert))
			goto clean;
	}

	/* apply update operations */

	sql_offset = 0;
	zbx_DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	p = NULL;
	/* iterate the entries (lines 9, 14 and 19 in T1) */
	while (NULL != (p = zbx_json_next(&jp_data, p)))
	{
		int		rec_differ = 0;	/* how many fields differ */
		int		last_n = 0;
		size_t		tmp_offset = sql_offset, last_pos = 0;
		zbx_json_type_t	type;

		if (FAIL == zbx_json_brackets_open(p, &jp_row))
		{
			*error = zbx_dsprintf(*error, "invalid data format: %s", zbx_json_strerror());
			goto clean;
		}

		pf = zbx_json_next_value_dyn(&jp_row, NULL, &buf, &buf_alloc, NULL);

		/* check whether we need to insert a new entry or update an existing one */
		ZBX_STR2UINT64(recid, buf);
		if (FAIL != zbx_vector_uint64_bsearch(&ins, recid, ZBX_DEFAULT_UINT64_COMPARE_FUNC))
			continue;

		if (1 == fields_count)	/* only primary key given, no update needed */
			continue;

		/* locate a copy of this record as found in database */
		id_offset.id = recid;
		if (NULL == (p_id_offset = (zbx_id_offset_t *)zbx_hashset_search(&h_id_offsets, &id_offset)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			goto clean;
		}

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update %s set ", table->table);

		for (f = 1; NULL != (pf = zbx_json_next_value_dyn(&jp_row, pf, &buf, &buf_alloc, &type));
				f++)
		{
			/* parse values for the entry (lines 10-12 in T1) */

			if (f == fields_count)
			{
				*error = zbx_dsprintf(*error, "invalid number of fields \"%.*s\"",
						(int)(jp_row.end - jp_row.start + 1), jp_row.start);
				goto clean;
			}

			if (ZBX_JSON_TYPE_NULL == type && 0 != (fields[f]->flags & ZBX_NOTNULL))
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

			if (0 == compare_nth_field(fields, recs + p_id_offset->offset, f, buf,
					(ZBX_JSON_TYPE_NULL == type), &last_n, &last_pos))
			{
				continue;
			}

			if (table == table_interface && 0 == strcmp(fields[f]->name, "available"))
			{
				/* host availability on server differs from local (proxy) availability - */
				/* reset availability timestamp to re-send availability data to server   */
				zbx_vector_uint64_append(&availability_interfaceids, recid);
				continue;
			}

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%s=", fields[f]->name);
			rec_differ++;

			if (ZBX_JSON_TYPE_NULL == type)
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

			if (SUCCEED != DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset))
				goto clean;
		}
		else
		{
			sql_offset = tmp_offset;	/* discard this update, all fields are the same */
			*(sql + sql_offset) = '\0';
		}
	}

	if (16 < sql_offset)	/* in ORACLE always present begin..end; */
	{
		zbx_DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

		if (ZBX_DB_OK > DBexecute("%s", sql))
			goto clean;
	}

	if (0 != ins.values_num && 0 == strcmp("hstgrp", table->table))
	{
		/* Host groups are not used by proxy and the discovery group record is sent  */
		/* only because of config table foreign key. To keep compatibility between   */
		/* minor versions and comply with hstgrp unique index (name, type) force the */
		/* group name to groupid.                                                    */
		if (ZBX_DB_OK > DBexecute("update hstgrp set name='"  ZBX_FS_UI64 "' where groupid=" ZBX_FS_UI64,
				ins.values[0], ins.values[0]))
		{
			goto clean;
		}
	}

	/* delete operations are performed by the caller using the returned del vector */

	if (0 != availability_interfaceids.values_num)
	{
		zbx_vector_uint64_sort(&availability_interfaceids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_vector_uint64_uniq(&availability_interfaceids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		DCtouch_interfaces_availability(&availability_interfaceids);
	}

	ret = SUCCEED;
clean:
	if (0 != ins.values_num)
	{
		zbx_db_insert_clean(&db_insert);
		zbx_vector_ptr_destroy(&values);
	}
clean2:
	zbx_hashset_destroy(&h_id_offsets);
	zbx_hashset_destroy(&h_del);
	zbx_vector_uint64_destroy(&availability_interfaceids);
	zbx_vector_uint64_destroy(&ins);
	if (1 == move_out)
		zbx_vector_uint64_destroy(&moves);
	zbx_free(sql);
	zbx_free(recs);
out:
	zbx_free(buf);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: update configuration                                              *
 *                                                                            *
 ******************************************************************************/
int	process_proxyconfig(struct zbx_json_parse *jp_data, struct zbx_json_parse *jp_kvs_paths)
{
	typedef struct
	{
		const ZBX_TABLE		*table;
		zbx_vector_uint64_t	ids;
	}
	table_ids_t;

	char			buf[ZBX_TABLENAME_LEN_MAX];
	const char		*p = NULL;
	struct zbx_json_parse	jp_obj;
	char			*error = NULL;
	int			i, ret = SUCCEED;

	table_ids_t		*table_ids;
	zbx_vector_ptr_t	tables_proxy;
	const ZBX_TABLE		*table;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_ptr_create(&tables_proxy);

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

		if (0 == strcmp(buf, "macro.secrets"))
		{
			*jp_kvs_paths = jp_obj;
			continue;
		}

		if (NULL == (table = DBget_table(buf)))
		{
			error = zbx_dsprintf(error, "invalid table name \"%s\"", buf);
			ret = FAIL;
			break;
		}

		table_ids = (table_ids_t *)zbx_malloc(NULL, sizeof(table_ids_t));
		table_ids->table = table;
		zbx_vector_uint64_create(&table_ids->ids);
		zbx_vector_ptr_append(&tables_proxy, table_ids);

		ret = process_proxyconfig_table(table, &jp_obj, &table_ids->ids, &error);
	}

	if (SUCCEED == ret)
	{
		char	*sql = NULL;
		size_t	sql_alloc = 512, sql_offset = 0;

		sql = (char *)zbx_malloc(sql, sql_alloc * sizeof(char));

		zbx_DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

		for (i = tables_proxy.values_num - 1; 0 <= i; i--)
		{
			table_ids = (table_ids_t *)tables_proxy.values[i];

			if (0 == table_ids->ids.values_num)
				continue;

			if (0 == strcmp(table_ids->table->table, "items"))
			{
				/* special case for item preprocessing - remove before removing items */
				zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from item_preproc where");
				DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, table_ids->table->recid,
						table_ids->ids.values, table_ids->ids.values_num);
				zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");

				/* reset master_itemid to avoid recursive removal of dependent items */
				zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
						"update items set master_itemid=null where");
				DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "itemid", table_ids->ids.values,
						table_ids->ids.values_num);
				zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " and master_itemid is not null;\n");
			}

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "delete from %s where",
					table_ids->table->table);
			DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, table_ids->table->recid,
					table_ids->ids.values, table_ids->ids.values_num);
			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");

			DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
		}

		if (sql_offset > 16)	/* in ORACLE always present begin..end; */
		{
			zbx_DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

			if (ZBX_DB_OK > DBexecute("%s", sql))
				ret = FAIL;
		}

		zbx_free(sql);
	}

	for (i = 0; i < tables_proxy.values_num; i++)
	{
		table_ids = (table_ids_t *)tables_proxy.values[i];

		zbx_vector_uint64_destroy(&table_ids->ids);
		zbx_free(table_ids);
	}
	zbx_vector_ptr_destroy(&tables_proxy);

	if (SUCCEED != (ret = DBend(ret)))
	{
		zabbix_log(LOG_LEVEL_ERR, "failed to update local proxy configuration copy: %s",
				(NULL == error ? "database error" : error));
	}

	zbx_free(error);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Return value:  SUCCEED - processed successfully                            *
 *                FAIL - no interface availability has been changed           *
 *                                                                            *
 ******************************************************************************/
int	get_interface_availability_data(struct zbx_json *json, int *ts)
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

		if (SUCCEED != (ret = is_uint64(tmp, &interfaceid)))
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

	result = DBselect("select nextid from ids where table_name='%s' and field_name='%s'",
			table_name, lastidfield);

	if (NULL == (row = DBfetch(result)))
		*lastid = 0;
	else
		ZBX_STR2UINT64(*lastid, row[0]);
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():" ZBX_FS_UI64,	__func__, *lastid);
}

static void	proxy_set_lastid(const char *table_name, const char *lastidfield, const zbx_uint64_t lastid)
{
	DB_RESULT	result;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() [%s.%s:" ZBX_FS_UI64 "]", __func__, table_name, lastidfield, lastid);

	result = DBselect("select 1 from ids where table_name='%s' and field_name='%s'",
			table_name, lastidfield);

	if (NULL == DBfetch(result))
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

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
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

int	proxy_get_delay(const zbx_uint64_t lastid)
{
	DB_RESULT	result;
	DB_ROW		row;
	char		*sql = NULL;
	int		ts = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() [lastid=" ZBX_FS_UI64 "]", __func__, lastid);

	sql = zbx_dsprintf(sql, "select write_clock from proxy_history where id>" ZBX_FS_UI64 " order by id asc",
			lastid);

	result = DBselectN(sql, 1);
	zbx_free(sql);

	if (NULL != (row = DBfetch(result)))
		ts = (int)time(NULL) - atoi(row[0]);

	DBfree_result(result);

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
	DBfree_result(result);

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

	result = DBselectN(sql, ZBX_MAX_HRECORDS - data_num);

	zbx_free(sql);

	while (NULL != (row = DBfetch(result)))
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

				DBfree_result(result);
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
	DBfree_result(result);

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

int	proxy_get_hist_data(struct zbx_json *j, zbx_uint64_t *lastid, int *more)
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

		if (ZBX_PROXY_DATA_DONE == *more || ZBX_MAX_HRECORDS_TOTAL <= records_num)
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
		proxy_get_history_data_simple(j, ZBX_PROTO_TAG_AUTOREGISTRATION, &areg, lastid, &id, &records_num,
				more);

		if (ZBX_PROXY_DATA_DONE == *more || ZBX_MAX_HRECORDS_TOTAL <= records_num)
			break;
	}

	if (0 != records_num)
		zbx_json_close(j);

	return records_num;
}

int	proxy_get_host_active_availability(struct zbx_json *j)
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

void	calc_timestamp(const char *line, int *timestamp, const char *format)
{
	int		hh, mm, ss, yyyy, dd, MM;
	int		hhc = 0, mmc = 0, ssc = 0, yyyyc = 0, ddc = 0, MMc = 0;
	int		i, num;
	struct tm	tm;
	time_t		t;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

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

	zabbix_log(LOG_LEVEL_DEBUG, "%s() %02d:%02d:%02d %02d/%02d/%04d", __func__, hh, mm, ss, MM, dd, yyyy);

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

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() timestamp:%d", __func__, *timestamp);
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
static void	process_item_value(const DC_ITEM *item, AGENT_RESULT *result, zbx_timespec_t *ts, int *h_num,
		char *error)
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
static int	process_history_data_value(DC_ITEM *item, zbx_agent_value_t *value, int *h_num)
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
		zabbix_log(LOG_LEVEL_DEBUG, "item [%s:%s] error: %s", item->host.host, item->key_orig, value->value);

		item->state = ITEM_STATE_NOTSUPPORTED;
		process_item_value(item, NULL, &value->ts, h_num, value->value);
	}
	else
	{
		AGENT_RESULT	result;

		init_result(&result);

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
					calc_timestamp(log->value, &log->timestamp, item->logtimefmt);
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
				set_result_type(&result, ITEM_VALUE_TYPE_TEXT, value->value);
		}

		if (0 != value->meta)
			set_result_meta(&result, value->lastlogsize, value->mtime);

		if (0 != ISSET_VALUE(&result) || 0 != ISSET_META(&result))
		{
			item->state = ITEM_STATE_NORMAL;
			process_item_value(item, &result, &value->ts, h_num, NULL);
		}

		free_result(&result);
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
int	process_history_data(DC_ITEM *items, zbx_agent_value_t *values, int *errcodes, size_t values_num,
		zbx_proxy_suppress_t *nodata_win)
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
			DCconfig_clean_items(&items[i], &errcodes[i], 1);
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
		if (FAIL == is_uint31(tmp, &av->ts.sec))
			goto out;

		if (SUCCEED == zbx_json_value_by_name_dyn(jp_row, ZBX_PROTO_TAG_NS, &tmp, &tmp_alloc, NULL))
		{
			if (FAIL == is_uint_n_range(tmp, tmp_alloc, &av->ts.ns, sizeof(av->ts.ns),
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

			is_uint64(tmp, &av->lastlogsize);

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
			SUCCEED != is_uint64(tmp, &av->id))
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

	if (SUCCEED != is_uint64(buffer, itemid))
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
static int	proxy_item_validator(DC_ITEM *item, zbx_socket_t *sock, void *args, char **error)
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
 * Parameters: proxy      - [IN] the proxy                                    *
 *             jp_data    - [IN] JSON with history data array                 *
 *             session    - [IN] the data session                             *
 *             nodata_win - [OUT] counter of delayed values                   *
 *             info       - [OUT] address of a pointer to the info            *
 *                                     string (should be freed by the caller) *
 *             mode       - [IN]  item retrieve mode is used to retrieve only *
 *                                necessary data to reduce time spent holding *
 *                                read lock                                   *
 *                                                                            *
 * Return value:  SUCCEED - processed successfully                            *
 *                FAIL - an error occurred                                    *
 *                                                                            *
 * Comments: This function is used to parse the new proxy history data        *
 *           protocol introduced in Zabbix v3.3.                              *
 *                                                                            *
 ******************************************************************************/
static int	process_history_data_by_itemids(zbx_socket_t *sock, zbx_client_item_validator_t validator_func,
		void *validator_args, struct zbx_json_parse *jp_data, zbx_data_session_t *session,
		zbx_proxy_suppress_t *nodata_win, char **info, unsigned int mode)
{
	const char		*pnext = NULL;
	int			ret = SUCCEED, processed_num = 0, total_num = 0, values_num, read_num, i, *errcodes;
	double			sec;
	DC_ITEM			*items;
	char			*error = NULL;
	zbx_uint64_t		itemids[ZBX_HISTORY_VALUES_MAX], last_valueid = 0;
	zbx_agent_value_t	values[ZBX_HISTORY_VALUES_MAX];
	zbx_timespec_t		unique_shift = {0, 0};

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	items = (DC_ITEM *)zbx_calloc(NULL, 1, sizeof(DC_ITEM) * ZBX_HISTORY_VALUES_MAX);
	errcodes = (int *)zbx_malloc(NULL, sizeof(int) * ZBX_HISTORY_VALUES_MAX);

	sec = zbx_time();

	while (SUCCEED == parse_history_data_by_itemids(jp_data, &pnext, values, itemids, &values_num, &read_num,
			&unique_shift, &error) && 0 != values_num)
	{
		DCconfig_get_items_by_itemids_partial(items, itemids, errcodes, values_num, mode);

		for (i = 0; i < values_num; i++)
		{
			if (SUCCEED != errcodes[i])
				continue;

			/* check and discard if duplicate data */
			if (NULL != session && 0 != values[i].id && values[i].id <= session->last_valueid)
			{
				DCconfig_clean_items(&items[i], &errcodes[i], 1);
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

				DCconfig_clean_items(&items[i], &errcodes[i], 1);
				errcodes[i] = FAIL;
			}
		}

		processed_num += process_history_data(items, values, errcodes, values_num, nodata_win);

		total_num += read_num;

		last_valueid = values[values_num - 1].id;

		DCconfig_clean_items(items, errcodes, values_num);
		zbx_agent_values_clean(values, values_num);

		if (NULL == pnext)
			break;
	}

	if (NULL != session && 0 != last_valueid)
	{
		if (session->last_valueid > last_valueid)
		{
			zabbix_log(LOG_LEVEL_WARNING, "received id:" ZBX_FS_UI64 " is less than last id:"
					ZBX_FS_UI64, last_valueid, session->last_valueid);
		}
		else
			session->last_valueid = last_valueid;
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
		zbx_substitute_simple_macros(NULL, NULL, NULL, NULL, NULL, NULL, item, NULL, NULL, NULL, NULL, NULL,
				&allowed_peers, MACRO_TYPE_ALLOWED_HOSTS, NULL, 0);
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
	DC_ITEM			*items;
	zbx_data_session_t	*session = NULL;
	zbx_uint64_t		last_hostid = 0;
	zbx_agent_value_t	values[ZBX_HISTORY_VALUES_MAX];
	int			errcodes[ZBX_HISTORY_VALUES_MAX];
	double			sec;

	sec = zbx_time();

	items = (DC_ITEM *)zbx_malloc(NULL, sizeof(DC_ITEM) * ZBX_HISTORY_VALUES_MAX);
	hostkeys = (zbx_host_key_t *)zbx_malloc(NULL, sizeof(zbx_host_key_t) * ZBX_HISTORY_VALUES_MAX);
	memset(hostkeys, 0, sizeof(zbx_host_key_t) * ZBX_HISTORY_VALUES_MAX);

	while (SUCCEED == parse_history_data(jp_data, &pnext, values, hostkeys, &values_num, &read_num,
			&unique_shift) && 0 != values_num)
	{
		DCconfig_get_items_by_keys(items, hostkeys, errcodes, values_num);

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
					session = zbx_dc_get_or_create_data_session(last_hostid, token);
			}

			/* check and discard if duplicate data */
			if (NULL != session && 0 != values[i].id && values[i].id <= session->last_valueid)
			{
				DCconfig_clean_items(&items[i], &errcodes[i], 1);
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

				DCconfig_clean_items(&items[i], &errcodes[i], 1);
				errcodes[i] = FAIL;
			}

			if (NULL != session)
				session->last_valueid = values[i].id;
		}

		processed_num += process_history_data(items, values, errcodes, values_num, NULL);
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

		if (zbx_get_token_len() != (token_len = strlen(token)))
		{
			*info = zbx_dsprintf(*info, "invalid session token length %d", (int)token_len);
			ret = FAIL;
			goto out;
		}
	}

	if (SUCCEED != zbx_json_value_by_name(jp, ZBX_PROTO_TAG_VERSION, tmp, sizeof(tmp), NULL) ||
				FAIL == (version = zbx_get_component_version(tmp)))
	{
		version = ZBX_COMPONENT_VERSION(4, 2);
	}

	if (ZBX_COMPONENT_VERSION(4, 4) <= version &&
			SUCCEED == zbx_json_value_by_name(jp, ZBX_PROTO_TAG_HOST, tmp, sizeof(tmp), NULL))
	{
		zbx_data_session_t	*session;
		zbx_uint64_t		hostid;

		if (SUCCEED != DCconfig_get_hostid_by_name(tmp, &hostid))
		{
			*info = zbx_dsprintf(*info, "unknown host '%s'", tmp);
			ret = SUCCEED;
			goto out;
		}

		if (NULL == token)
			session = NULL;
		else
			session = zbx_dc_get_or_create_data_session(hostid, token);

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
int	process_agent_history_data(zbx_socket_t *sock, struct zbx_json_parse *jp, zbx_timespec_t *ts, char **info)
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
int	process_sender_history_data(zbx_socket_t *sock, struct zbx_json_parse *jp, zbx_timespec_t *ts, char **info)
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
	ZBX_DB_DHOST		dhost;
	zbx_service_t		*service;
	int			services_num, ret = FAIL, i, dchecks = 0;
	zbx_vector_ptr_t	services_old;
	ZBX_DB_DRULE		drule = {.druleid = druleid, .unique_dcheckid = unique_dcheckid};

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	memset(&dhost, 0, sizeof(dhost));

	zbx_vector_ptr_create(&services_old);

	/* find host update */
	for (i = *processed_num; i < services->values_num; i++)
	{
		service = (zbx_service_t *)services->values[i];

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

			service = (zbx_service_t *)services->values[i];

			ip_esc = DBdyn_escape_field("proxy_dhistory", "ip", ip);
			dns_esc = DBdyn_escape_field("proxy_dhistory", "dns", service->dns);
			value_esc = DBdyn_escape_field("proxy_dhistory", "value", service->value);

			DBexecute("insert into proxy_dhistory (clock,druleid,ip,port,value,status,dcheckid,dns)"
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

		result = DBselect(
				"select dcheckid,clock,port,value,status,dns,ip"
				" from proxy_dhistory"
				" where druleid=" ZBX_FS_UI64
				" order by id",
				drule.druleid);

		for (i = 0; NULL != (row = DBfetch(result)); i++)
		{
			if (SUCCEED == DBis_null(row[0]))
				continue;

			ZBX_STR2UINT64(dcheckid, row[0]);

			if (0 == strcmp(ip, row[6]))
			{
				service = (zbx_service_t *)zbx_malloc(NULL, sizeof(zbx_service_t));
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
		DBfree_result(result);

		if (0 != i)
		{
			DBexecute("delete from proxy_dhistory"
					" where druleid=" ZBX_FS_UI64,
					drule.druleid);
		}

		zbx_vector_uint64_sort(dcheckids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_vector_uint64_uniq(dcheckids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		if (SUCCEED != DBlock_druleid(drule.druleid))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "druleid:" ZBX_FS_UI64 " does not exist", drule.druleid);
			goto fail;
		}

		if (SUCCEED != DBlock_ids("dchecks", "dcheckid", dcheckids))
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
		service = (zbx_service_t *)services_old.values[i];

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
		service = (zbx_service_t *)services->values[*processed_num];

		if (FAIL == zbx_vector_uint64_bsearch(dcheckids, service->dcheckid, ZBX_DEFAULT_UINT64_COMPARE_FUNC))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "dcheckid:" ZBX_FS_UI64 " does not exist", service->dcheckid);
			continue;
		}

		zbx_discovery_update_service(&drule, service->dcheckid, &dhost, ip, service->dns, service->port,
				service->status, service->value, service->itemtime);
	}

	service = (zbx_service_t *)services->values[(*processed_num)++];
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
	zbx_service_t		*service;

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

		if (SUCCEED != is_ip(ip))
		{
			zabbix_log(LOG_LEVEL_WARNING, "%s(): \"%s\" is not a valid IP address", __func__, ip);
			continue;
		}

		if (FAIL == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_PORT, tmp, sizeof(tmp), NULL))
		{
			port = 0;
		}
		else if (FAIL == is_ushort(tmp, &port))
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

		service = (zbx_service_t *)zbx_malloc(NULL, sizeof(zbx_service_t));
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

		DBbegin();
		result = DBselect(
				"select dcheckid"
				" from dchecks"
				" where druleid=" ZBX_FS_UI64
					" and uniq=1",
				drule->druleid);

		if (NULL != (row = DBfetch(result)))
			ZBX_STR2UINT64(unique_dcheckid, row[0]);
		else
			unique_dcheckid = 0;
		DBfree_result(result);
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
		DBcommit();
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

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_ptr_create(&autoreg_hosts);
	host_metadata = (char *)zbx_malloc(host_metadata, host_metadata_alloc);

	while (NULL != (p = zbx_json_next(jp_data, p)))
	{
		unsigned int		connection_type;
		zbx_conn_flags_t	flags = ZBX_CONN_DEFAULT;

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
		else if (SUCCEED != is_ip(ip))
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
		else if (FAIL == is_ushort(tmp, &port))
		{
			zabbix_log(LOG_LEVEL_WARNING, "%s(): \"%s\" is not a valid port", __func__, tmp);
			continue;
		}

		if (FAIL == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_TLS_ACCEPTED, tmp, sizeof(tmp), NULL))
		{
			connection_type = ZBX_TCP_SEC_UNENCRYPTED;
		}
		else if (FAIL == is_uint32(tmp, &connection_type) || (ZBX_TCP_SEC_UNENCRYPTED != connection_type &&
				ZBX_TCP_SEC_TLS_PSK != connection_type && ZBX_TCP_SEC_TLS_CERT != connection_type))
		{
			zabbix_log(LOG_LEVEL_WARNING, "%s(): \"%s\" is not a valid value for \""
					ZBX_PROTO_TAG_TLS_ACCEPTED "\"", __func__, tmp);
			continue;
		}

		DBregister_host_prepare(&autoreg_hosts, host, ip, dns, port, connection_type, host_metadata, flags,
				itemtime);
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
int	proxy_get_history_count(void)
{
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	id;
	int		count = 0;

	DBconnect(ZBX_DB_CONNECT_NORMAL);

	proxy_get_lastid("proxy_history", "history_lastid", &id);

	result = DBselect(
			"select count(*)"
			" from proxy_history"
			" where id>" ZBX_FS_UI64,
			id);

	if (NULL != (row = DBfetch(result)))
		count = atoi(row[0]);

	DBfree_result(result);

	DBclose();

	return count;
}

/******************************************************************************
 *                                                                            *
 * Purpose: extracts protocol version from json data                          *
 *                                                                            *
 * Parameters:                                                                *
 *     jp      - [IN] JSON with the proxy version                             *
 *                                                                            *
 * Return value: The protocol version.                                        *
 *     SUCCEED - proxy version was successfully extracted                     *
 *     FAIL    - otherwise                                                    *
 *                                                                            *
 ******************************************************************************/
int	zbx_get_proxy_protocol_version(struct zbx_json_parse *jp)
{
	char	value[MAX_STRING_LEN];
	int	version;

	if (NULL != jp && SUCCEED == zbx_json_value_by_name(jp, ZBX_PROTO_TAG_VERSION, value, sizeof(value), NULL) &&
			-1 != (version = zbx_get_component_version(value)))
	{
		return version;
	}
	else
		return ZBX_COMPONENT_VERSION(3, 2);
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
	zbx_vector_ptr_t	tasks;

	zbx_vector_ptr_create(&tasks);

	zbx_tm_json_deserialize_tasks(jp_tasks, &tasks);

	DBbegin();
	zbx_tm_save_tasks(&tasks);
	DBcommit();

	zbx_vector_ptr_clear_ext(&tasks, (zbx_clean_func_t)zbx_tm_task_free);
	zbx_vector_ptr_destroy(&tasks);
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
int	process_proxy_data(const DC_PROXY *proxy, struct zbx_json_parse *jp, zbx_timespec_t *ts,
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
		zbx_data_session_t	*session = NULL;

		if (SUCCEED == zbx_json_value_by_name(jp, ZBX_PROTO_TAG_SESSION, value, sizeof(value), NULL))
		{
			size_t	token_len;

			if (zbx_get_token_len() != (token_len = strlen(value)))
			{
				*error = zbx_dsprintf(*error, "invalid session token length %d", (int)token_len);
				ret = FAIL;
				goto out;
			}

			session = zbx_dc_get_or_create_data_session(proxy->hostid, value);
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
	else
	{
		unsigned char	*data = NULL;
		zbx_uint32_t	data_len;

		data_len = zbx_availability_serialize_active_proxy_hb_update(&data, proxy->hostid);
		zbx_availability_send(ZBX_IPC_AVAILMAN_ACTIVE_PROXY_HB_UPDATE, data, data_len, NULL);
		zbx_free(data);
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

		DBbegin();
		zbx_DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

		for (i = 0; i < lastaccess.values_num; i++)
		{
			zbx_uint64_pair_t	*pair = &lastaccess.values[i];

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update host_rtdata"
					" set lastaccess=%d"
					" where hostid=" ZBX_FS_UI64 ";\n",
					(int)pair->second, pair->first);

			DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
		}

		zbx_DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

		if (16 < sql_offset)	/* in ORACLE always present begin..end; */
			DBexecute("%s", sql);

		DBcommit();

		zbx_free(sql);
	}

	zbx_vector_uint64_pair_destroy(&lastaccess);
}

/******************************************************************************
 *                                                                            *
 * Purpose: updates proxy runtime properties in cache and database.           *
 *                                                                            *
 * Parameters: proxy      - [IN/OUT] the proxy                                *
 *             version    - [IN] the proxy version                            *
 *             lastaccess - [IN] the last proxy access time                   *
 *             compress   - [IN] 1 if proxy is using data compression,        *
 *                               0 otherwise                                  *
 *             flags_add  - [IN] additional flags for update proxy            *
 *                                                                            *
 * Comments: The proxy parameter properties are also updated.                 *
 *                                                                            *
 ******************************************************************************/
void	zbx_update_proxy_data(DC_PROXY *proxy, int version, int lastaccess, int compress, zbx_uint64_t flags_add)
{
	zbx_proxy_diff_t	diff;

	diff.hostid = proxy->hostid;
	diff.flags = ZBX_FLAGS_PROXY_DIFF_UPDATE | flags_add;
	diff.version = version;
	diff.lastaccess = lastaccess;
	diff.compress = compress;

	zbx_dc_update_proxy(&diff);

	if (0 != (diff.flags & ZBX_FLAGS_PROXY_DIFF_UPDATE_VERSION) && 0 != proxy->version)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "proxy \"%s\" protocol version updated from %d.%d to %d.%d", proxy->host,
				ZBX_COMPONENT_VERSION_MAJOR(proxy->version),
				ZBX_COMPONENT_VERSION_MINOR(proxy->version),
				ZBX_COMPONENT_VERSION_MAJOR(diff.version),
				ZBX_COMPONENT_VERSION_MINOR(diff.version));
	}

	proxy->version = version;
	proxy->auto_compress = compress;
	proxy->lastaccess = lastaccess;

	if (0 != (diff.flags & ZBX_FLAGS_PROXY_DIFF_UPDATE_COMPRESS))
		DBexecute("update hosts set auto_compress=%d where hostid=" ZBX_FS_UI64, diff.compress, diff.hostid);

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
	int	server_version;
	int	ret = SUCCEED;
	int	now;
	int	print_log = 0;

	/* warn if another proxy version is used and proceed with compatibility rules*/
	if ((server_version = ZBX_COMPONENT_VERSION(ZABBIX_VERSION_MAJOR, ZABBIX_VERSION_MINOR)) != version)
	{
		now = (int)time(NULL);

		if (proxy->last_version_error_time <= now)
		{
			print_log = 1;
			proxy->last_version_error_time = now + 5 * SEC_PER_MIN;
			zbx_update_proxy_lasterror(proxy);
		}

		/* don't accept pre 4.2 data */
		if (ZBX_COMPONENT_VERSION(4, 2) > version)
		{
			if (1 == print_log)
			{
				zabbix_log(LOG_LEVEL_WARNING, "cannot process proxy \"%s\":"
						" protocol version %d.%d is not supported anymore",
						proxy->host, ZBX_COMPONENT_VERSION_MAJOR(version),
						ZBX_COMPONENT_VERSION_MINOR(version));
			}
			ret = FAIL;
			goto out;
		}

		if (1 == print_log)
		{
			zabbix_log(LOG_LEVEL_WARNING, "proxy \"%s\" protocol version %d.%d differs from server version"
					" %d.%d", proxy->host, ZBX_COMPONENT_VERSION_MAJOR(version),
					ZBX_COMPONENT_VERSION_MINOR(version),
					ZABBIX_VERSION_MAJOR, ZABBIX_VERSION_MINOR);
		}

		if (version > server_version)
		{
			if (1 == print_log)
				zabbix_log(LOG_LEVEL_WARNING, "cannot accept proxy data");
			ret = FAIL;
		}

	}
out:
	return ret;
}
