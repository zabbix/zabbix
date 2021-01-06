/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

#include "log.h"
#include "zbxjson.h"
#include "dbcache.h"
#include "zbxserver.h"
#include "trapper_item_test.h"
#include "../poller/poller.h"
#include "zbxtasks.h"
#ifdef HAVE_OPENIPMI
#include "../ipmi/ipmi.h"
#endif

static void	dump_item(const DC_ITEM *item)
{
	zabbix_log(LOG_LEVEL_TRACE, "key:'%s'", item->key);
	zabbix_log(LOG_LEVEL_TRACE, "  type: %u", item->type);
	zabbix_log(LOG_LEVEL_TRACE, "  snmp_version: %u", item->snmp_version);
	zabbix_log(LOG_LEVEL_TRACE, "  value_type: %u", item->value_type);
	zabbix_log(LOG_LEVEL_TRACE, "  snmpv3_securitylevel: %u", item->snmpv3_securitylevel);
	zabbix_log(LOG_LEVEL_TRACE, "  authtype: %u", item->authtype);
	zabbix_log(LOG_LEVEL_TRACE, "  flags: %u", item->flags);
	zabbix_log(LOG_LEVEL_TRACE, "  snmpv3_authprotocol: %u", item->snmpv3_authprotocol);
	zabbix_log(LOG_LEVEL_TRACE, "  snmpv3_privprotocol: %u", item->snmpv3_privprotocol);
	zabbix_log(LOG_LEVEL_TRACE, "  follow_redirects: %u", item->follow_redirects);
	zabbix_log(LOG_LEVEL_TRACE, "  post_type: %u", item->post_type);
	zabbix_log(LOG_LEVEL_TRACE, "  retrieve_mode: %u", item->retrieve_mode);
	zabbix_log(LOG_LEVEL_TRACE, "  request_method: %u", item->request_method);
	zabbix_log(LOG_LEVEL_TRACE, "  output_format: %u", item->output_format);
	zabbix_log(LOG_LEVEL_TRACE, "  verify_peer: %u", item->verify_peer);
	zabbix_log(LOG_LEVEL_TRACE, "  verify_host: %u", item->verify_host);
	zabbix_log(LOG_LEVEL_TRACE, "  ipmi_sensor:'%s'", item->ipmi_sensor);
	zabbix_log(LOG_LEVEL_TRACE, "  snmp_community:'%s'", item->snmp_community);
	zabbix_log(LOG_LEVEL_TRACE, "  snmp_oid:'%s'", item->snmp_oid);
	zabbix_log(LOG_LEVEL_TRACE, "  snmpv3_securityname:'%s'", item->snmpv3_securityname);
	zabbix_log(LOG_LEVEL_TRACE, "  snmpv3_authpassphrase:'%s'", item->snmpv3_authpassphrase);
	zabbix_log(LOG_LEVEL_TRACE, "  snmpv3_privpassphrase:'%s'", item->snmpv3_privpassphrase);
	zabbix_log(LOG_LEVEL_TRACE, "  params:'%s'", ZBX_NULL2STR(item->params));
	zabbix_log(LOG_LEVEL_TRACE, "  username:'%s'", item->username);
	zabbix_log(LOG_LEVEL_TRACE, "  publickey:'%s'", item->publickey);
	zabbix_log(LOG_LEVEL_TRACE, "  privatekey:'%s'", item->privatekey);
	zabbix_log(LOG_LEVEL_TRACE, "  password:'%s'", item->password);
	zabbix_log(LOG_LEVEL_TRACE, "  snmpv3_contextname:'%s'", item->snmpv3_contextname);
	zabbix_log(LOG_LEVEL_TRACE, "  jmx_endpoint:'%s'", item->jmx_endpoint);
	zabbix_log(LOG_LEVEL_TRACE, "  timeout:'%s'", item->timeout);
	zabbix_log(LOG_LEVEL_TRACE, "  url:'%s'", item->url);
	zabbix_log(LOG_LEVEL_TRACE, "  query_fields:'%s'", item->query_fields);
	zabbix_log(LOG_LEVEL_TRACE, "  posts:'%s'", ZBX_NULL2STR(item->posts));
	zabbix_log(LOG_LEVEL_TRACE, "  status_codes:'%s'", item->status_codes);
	zabbix_log(LOG_LEVEL_TRACE, "  http_proxy:'%s'", item->http_proxy);
	zabbix_log(LOG_LEVEL_TRACE, "  headers:'%s'", ZBX_NULL2STR(item->headers));
	zabbix_log(LOG_LEVEL_TRACE, "  ssl_cert_file:'%s'", item->ssl_cert_file);
	zabbix_log(LOG_LEVEL_TRACE, "  ssl_key_file:'%s'", item->ssl_key_file);
	zabbix_log(LOG_LEVEL_TRACE, "  ssl_key_password:'%s'", item->ssl_key_password);
	zabbix_log(LOG_LEVEL_TRACE, "interfaceid: " ZBX_FS_UI64, item->interface.interfaceid);
	zabbix_log(LOG_LEVEL_TRACE, "  useip: %u", item->interface.useip);
	zabbix_log(LOG_LEVEL_TRACE, "  address:'%s'", ZBX_NULL2STR(item->interface.addr));
	zabbix_log(LOG_LEVEL_TRACE, "  port: %u", item->interface.port);
	zabbix_log(LOG_LEVEL_TRACE, "hostid: " ZBX_FS_UI64, item->host.hostid);
	zabbix_log(LOG_LEVEL_TRACE, "  proxy_hostid: " ZBX_FS_UI64, item->host.proxy_hostid);
	zabbix_log(LOG_LEVEL_TRACE, "  host:'%s'", item->host.host);
	zabbix_log(LOG_LEVEL_TRACE, "  maintenance_status: %u", item->host.maintenance_status);
	zabbix_log(LOG_LEVEL_TRACE, "  maintenance_type: %u", item->host.maintenance_type);
	zabbix_log(LOG_LEVEL_TRACE, "  snmp_available: %u", item->host.snmp_available);
	zabbix_log(LOG_LEVEL_TRACE, "  available: %u", item->host.available);
	zabbix_log(LOG_LEVEL_TRACE, "  ipmi_available: %u", item->host.ipmi_available);
	zabbix_log(LOG_LEVEL_TRACE, "  ipmi_authtype: %d", item->host.ipmi_authtype);
	zabbix_log(LOG_LEVEL_TRACE, "  ipmi_privilege: %u", item->host.ipmi_privilege);
	zabbix_log(LOG_LEVEL_TRACE, "  ipmi_username:'%s'", item->host.ipmi_username);
	zabbix_log(LOG_LEVEL_TRACE, "  ipmi_password:'%s'", item->host.ipmi_password);
	zabbix_log(LOG_LEVEL_TRACE, "  jmx_available: %u", item->host.jmx_available);
	zabbix_log(LOG_LEVEL_TRACE, "  tls_connect: %u", item->host.tls_connect);
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	zabbix_log(LOG_LEVEL_TRACE, "  tls_issuer:'%s'", item->host.tls_issuer);
	zabbix_log(LOG_LEVEL_TRACE, "  tls_subject:'%s'", item->host.tls_subject);
	zabbix_log(LOG_LEVEL_TRACE, "  tls_psk_identity:'%s'", item->host.tls_psk_identity);
	zabbix_log(LOG_LEVEL_TRACE, "  tls_psk:'%s'", item->host.tls_psk);
#endif
}

static char	*db_string_from_json_dyn(const struct zbx_json_parse *jp, const char *name, const ZBX_TABLE *table,
		const char *fieldname)
{
	char	*string = NULL;
	size_t	size = 0;

	if (SUCCEED == zbx_json_value_by_name_dyn(jp, name, &string, &size, NULL))
		return string;

	return zbx_strdup(NULL, DBget_field(table, fieldname)->default_value);
}

static void	db_string_from_json(const struct zbx_json_parse *jp, const char *name, const ZBX_TABLE *table,
		const char *fieldname, char *string, size_t len)
{

	if (SUCCEED != zbx_json_value_by_name(jp, name, string, len, NULL))
		zbx_strlcpy(string, DBget_field(table, fieldname)->default_value, len);
}

static void	db_uchar_from_json(const struct zbx_json_parse *jp, const char *name, const ZBX_TABLE *table,
		const char *fieldname, unsigned char *string)
{
	char	tmp[ZBX_MAX_UINT64_LEN + 1];

	if (SUCCEED == zbx_json_value_by_name(jp, name, tmp, sizeof(tmp), NULL))
		ZBX_STR2UCHAR(*string, tmp);
	else
		ZBX_STR2UCHAR(*string, DBget_field(table, fieldname)->default_value);
}

int	zbx_trapper_item_test_run(const struct zbx_json_parse *jp_data, zbx_uint64_t proxy_hostid, char **info)
{
	char			tmp[MAX_STRING_LEN + 1], **pvalue;
	DC_ITEM			item;
	static const ZBX_TABLE	*table_items, *table_interface, *table_interface_snmp, *table_hosts;
	struct zbx_json_parse	jp_interface, jp_host, jp_details;
	AGENT_RESULT		result;
	int			errcode, ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	memset(&item, 0, sizeof(item));

	if (NULL == table_items)
		table_items = DBget_table("items");

	db_uchar_from_json(jp_data, ZBX_PROTO_TAG_TYPE, table_items, "type", &item.type);
	db_string_from_json(jp_data, ZBX_PROTO_TAG_KEY, table_items, "key_", item.key_orig, sizeof(item.key_orig));

	if (0 != proxy_hostid && FAIL == is_item_processed_by_server(item.type, item.key_orig))
	{
		ret = zbx_tm_execute_task_data(jp_data->start, jp_data->end - jp_data->start + 1, proxy_hostid, info);
		goto out;
	}

	db_uchar_from_json(jp_data, ZBX_PROTO_TAG_VALUE_TYPE, table_items, "value_type", &item.value_type);
	db_uchar_from_json(jp_data, ZBX_PROTO_TAG_AUTHTYPE, table_items, "authtype", &item.authtype);
	db_uchar_from_json(jp_data, ZBX_PROTO_TAG_FLAGS, table_items, "flags", &item.flags);
	db_uchar_from_json(jp_data, ZBX_PROTO_TAG_FOLLOW_REDIRECTS, table_items, "follow_redirects",
			&item.follow_redirects);
	db_uchar_from_json(jp_data, ZBX_PROTO_TAG_POST_TYPE, table_items, "post_type", &item.post_type);
	db_uchar_from_json(jp_data, ZBX_PROTO_TAG_RETRIEVE_MODE, table_items, "retrieve_mode", &item.retrieve_mode);
	db_uchar_from_json(jp_data, ZBX_PROTO_TAG_REQUEST_METHOD, table_items, "request_method", &item.request_method);
	db_uchar_from_json(jp_data, ZBX_PROTO_TAG_OUTPUT_FORMAT, table_items, "output_format", &item.output_format);
	db_uchar_from_json(jp_data, ZBX_PROTO_TAG_VERIFY_PEER, table_items, "verify_peer", &item.verify_peer);
	db_uchar_from_json(jp_data, ZBX_PROTO_TAG_VERIFY_HOST, table_items, "verify_host", &item.verify_host);

	db_string_from_json(jp_data, ZBX_PROTO_TAG_IPMI_SENSOR, table_items, "ipmi_sensor", item.ipmi_sensor,
			sizeof(item.ipmi_sensor));

	db_string_from_json(jp_data, ZBX_PROTO_TAG_SNMP_OID, table_items, "snmp_oid", item.snmp_oid_orig,
			sizeof(item.snmp_oid_orig));
	item.params = db_string_from_json_dyn(jp_data, ZBX_PROTO_TAG_PARAMS, table_items, "params");

	db_string_from_json(jp_data, ZBX_PROTO_TAG_USERNAME, table_items, "username", item.username_orig,
			sizeof(item.username_orig));
	db_string_from_json(jp_data, ZBX_PROTO_TAG_PUBLICKEY, table_items, "publickey", item.publickey_orig,
			sizeof(item.publickey_orig));
	db_string_from_json(jp_data, ZBX_PROTO_TAG_PRIVATEKEY, table_items, "privatekey", item.privatekey_orig,
			sizeof(item.privatekey_orig));
	db_string_from_json(jp_data, ZBX_PROTO_TAG_PASSWORD, table_items, "password", item.password_orig,
			sizeof(item.password_orig));
	db_string_from_json(jp_data, ZBX_PROTO_TAG_JMX_ENDPOINT, table_items, "jmx_endpoint",
			item.jmx_endpoint_orig, sizeof(item.jmx_endpoint_orig));
	db_string_from_json(jp_data, ZBX_PROTO_TAG_TIMEOUT, table_items, "timeout", item.timeout_orig,
			sizeof(item.timeout_orig));
	db_string_from_json(jp_data, ZBX_PROTO_TAG_URL, table_items, "url", item.url_orig, sizeof(item.url_orig));
	db_string_from_json(jp_data, ZBX_PROTO_TAG_QUERY_FIELDS, table_items, "query_fields",
			item.query_fields_orig, sizeof(item.query_fields_orig));

	item.posts = db_string_from_json_dyn(jp_data, ZBX_PROTO_TAG_POSTS, table_items, "posts");

	db_string_from_json(jp_data, ZBX_PROTO_TAG_STATUS_CODES, table_items, "status_codes", item.status_codes_orig,
			sizeof(item.status_codes_orig));
	db_string_from_json(jp_data, ZBX_PROTO_TAG_HTTP_PROXY, table_items, "http_proxy", item.http_proxy_orig,
			sizeof(item.http_proxy_orig));

	item.headers = db_string_from_json_dyn(jp_data, ZBX_PROTO_TAG_HTTP_HEADERS, table_items, "headers");

	db_string_from_json(jp_data, ZBX_PROTO_TAG_SSL_CERT_FILE, table_items, "ssl_cert_file",
			item.ssl_cert_file_orig, sizeof(item.ssl_cert_file_orig));
	db_string_from_json(jp_data, ZBX_PROTO_TAG_SSL_KEY_FILE, table_items, "ssl_key_file", item.ssl_key_file_orig,
			sizeof(item.ssl_key_file_orig));
	db_string_from_json(jp_data, ZBX_PROTO_TAG_SSL_KEY_PASSWORD, table_items, "ssl_key_password",
			item.ssl_key_password_orig, sizeof(item.ssl_key_password_orig));

	if (NULL == table_interface)
		table_interface = DBget_table("interface");

	if (FAIL == zbx_json_brackets_by_name(jp_data, ZBX_PROTO_TAG_INTERFACE, &jp_interface))
		zbx_json_open("{}", &jp_interface);

	if (SUCCEED == zbx_json_value_by_name(&jp_interface, ZBX_PROTO_TAG_INTERFACE_ID, tmp, sizeof(tmp), NULL))
		ZBX_STR2UINT64(item.interface.interfaceid, tmp);
	else
		item.interface.interfaceid = 0;

	db_uchar_from_json(&jp_interface, ZBX_PROTO_TAG_USEIP, table_interface, "useip", &item.interface.useip);

	if (1 == item.interface.useip)
	{
		db_string_from_json(&jp_interface, ZBX_PROTO_TAG_ADDRESS, table_interface, "ip", item.interface.ip_orig,
				sizeof(item.interface.ip_orig));
		item.interface.addr = item.interface.ip_orig;
	}
	else
	{
		db_string_from_json(&jp_interface, ZBX_PROTO_TAG_ADDRESS, table_interface, "dns", item.interface.dns_orig,
				sizeof(item.interface.dns_orig));
		item.interface.addr = item.interface.dns_orig;
	}

	db_string_from_json(&jp_interface, ZBX_PROTO_TAG_PORT, table_interface, "port", item.interface.port_orig,
			sizeof(item.interface.port_orig));

	if (FAIL == zbx_json_brackets_by_name(&jp_interface, ZBX_PROTO_TAG_DETAILS, &jp_details))
		zbx_json_open("{}", &jp_details);

	if (NULL == table_interface_snmp)
		table_interface_snmp = DBget_table("interface_snmp");

	db_uchar_from_json(&jp_details, ZBX_PROTO_TAG_VERSION, table_interface_snmp, "version", &item.snmp_version);
	db_string_from_json(&jp_details, ZBX_PROTO_TAG_COMMUNITY, table_interface_snmp, "community",
			item.snmp_community_orig, sizeof(item.snmp_community_orig));
	db_string_from_json(&jp_details, ZBX_PROTO_TAG_SECURITYNAME, table_interface_snmp, "securityname",
			item.snmpv3_securityname_orig, sizeof(item.snmpv3_securityname_orig));
	db_uchar_from_json(&jp_details, ZBX_PROTO_TAG_SECURITYLEVEL, table_interface_snmp, "securitylevel",
			&item.snmpv3_securitylevel);
	db_string_from_json(&jp_details, ZBX_PROTO_TAG_AUTHPASSPHRASE, table_interface_snmp, "authpassphrase",
			item.snmpv3_authpassphrase_orig, sizeof(item.snmpv3_authpassphrase_orig));
	db_string_from_json(&jp_details, ZBX_PROTO_TAG_PRIVPASSPHRASE, table_interface_snmp, "privpassphrase",
			item.snmpv3_privpassphrase_orig, sizeof(item.snmpv3_privpassphrase_orig));
	db_uchar_from_json(&jp_details, ZBX_PROTO_TAG_AUTHPROTOCOL, table_interface_snmp, "authprotocol",
			&item.snmpv3_authprotocol);
	db_uchar_from_json(&jp_details, ZBX_PROTO_TAG_PRIVPROTOCOL, table_interface_snmp, "privprotocol",
			&item.snmpv3_privprotocol);
	db_string_from_json(&jp_details, ZBX_PROTO_TAG_CONTEXTNAME, table_interface_snmp, "contextname",
			item.snmpv3_contextname_orig, sizeof(item.snmpv3_contextname_orig));

	if (NULL == table_hosts)
		table_hosts = DBget_table("hosts");

	if (FAIL == zbx_json_brackets_by_name(jp_data, ZBX_PROTO_TAG_HOST, &jp_host))
		zbx_json_open("{}", &jp_host);

	db_string_from_json(&jp_host, ZBX_PROTO_TAG_HOST, table_hosts, "host", item.host.host, sizeof(item.host.host));

	if (SUCCEED == zbx_json_value_by_name(&jp_host, ZBX_PROTO_TAG_HOSTID, tmp, sizeof(tmp), NULL))
		ZBX_STR2UINT64(item.host.hostid, tmp);
	else
		item.host.hostid = 0;

	db_uchar_from_json(&jp_host, ZBX_PROTO_TAG_MAINTENANCE_STATUS, table_hosts, "maintenance_status",
			&item.host.maintenance_status);
	db_uchar_from_json(&jp_host, ZBX_PROTO_TAG_MAINTENANCE_TYPE, table_hosts, "maintenance_type",
			&item.host.maintenance_type);
	db_uchar_from_json(&jp_host, ZBX_PROTO_TAG_AVAILABLE, table_hosts, "available", &item.host.available);
	db_uchar_from_json(&jp_host, ZBX_PROTO_TAG_SNMP_AVAILABLE, table_hosts, "snmp_available",
			&item.host.snmp_available);
	db_uchar_from_json(&jp_host, ZBX_PROTO_TAG_IPMI_AVAILABLE, table_hosts, "ipmi_available",
			&item.host.ipmi_available);
	if (SUCCEED == zbx_json_value_by_name(&jp_host, ZBX_PROTO_TAG_IPMI_AUTHTYPE, tmp, sizeof(tmp), NULL))
		item.host.ipmi_authtype = atoi(tmp);
	else
		item.host.ipmi_authtype = atoi(DBget_field(table_hosts, "ipmi_authtype")->default_value);
	db_uchar_from_json(&jp_host, ZBX_PROTO_TAG_IPMI_PRIVILEGE, table_hosts, "ipmi_privilege",
			&item.host.ipmi_privilege);
	db_string_from_json(&jp_host, ZBX_PROTO_TAG_IPMI_USERNAME, table_hosts, "ipmi_username",
			item.host.ipmi_username, sizeof(item.host.ipmi_username));
	db_string_from_json(&jp_host, ZBX_PROTO_TAG_IPMI_PASSWORD, table_hosts, "ipmi_password",
			item.host.ipmi_password, sizeof(item.host.ipmi_password));
	db_uchar_from_json(&jp_host, ZBX_PROTO_TAG_JMX_AVAILABLE, table_hosts, "jmx_available",
			&item.host.jmx_available);
	db_uchar_from_json(&jp_host, ZBX_PROTO_TAG_TLS_CONNECT, table_hosts, "tls_connect", &item.host.tls_connect);
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	db_string_from_json(&jp_host, ZBX_PROTO_TAG_TLS_ISSUER, table_hosts, "tls_issuer", item.host.tls_issuer,
			sizeof(item.host.tls_issuer));
	db_string_from_json(&jp_host, ZBX_PROTO_TAG_TLS_SUBJECT, table_hosts, "tls_subject", item.host.tls_subject,
			sizeof(item.host.tls_subject));
	db_string_from_json(&jp_host, ZBX_PROTO_TAG_TLS_PSK_IDENTITY, table_hosts, "tls_psk_identity",
			item.host.tls_psk_identity, sizeof(item.host.tls_psk_identity));
	db_string_from_json(&jp_host, ZBX_PROTO_TAG_TLS_PSK, table_hosts, "tls_psk", item.host.tls_psk,
			sizeof(item.host.tls_psk));
#endif

	if (ITEM_TYPE_IPMI == item.type)
	{
		init_result(&result);

		ZBX_STRDUP(item.key, item.key_orig);

		if (FAIL == is_ushort(item.interface.port_orig, &item.interface.port))
		{
			*info = zbx_dsprintf(NULL, "Invalid port number [%s]", item.interface.port_orig);
		}
		else
		{
#ifdef HAVE_OPENIPMI
			if (0 == CONFIG_IPMIPOLLER_FORKS)
			{
				*info = zbx_strdup(NULL, "Cannot perform IPMI request: configuration parameter"
						" \"StartIPMIPollers\" is 0.");
			}
			else
				ret = zbx_ipmi_test_item(&item, info);
#else
			*info = zbx_strdup(NULL, "Support for IPMI was not compiled in.");
#endif
		}

		if (SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_TRACE))
			dump_item(&item);
	}
	else
	{
		zbx_vector_ptr_t	add_results;

		zbx_vector_ptr_create(&add_results);

		zbx_prepare_items(&item, &errcode, 1, &result, MACRO_EXPAND_NO);

		if (SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_TRACE))
			dump_item(&item);

		zbx_check_items(&item, &errcode, 1, &result, &add_results, ZBX_NO_POLLER);

		switch (errcode)
		{
			case SUCCEED:
				if (NULL == (pvalue = GET_TEXT_RESULT(&result)))
				{
					*info = zbx_strdup(NULL, "no value");
				}
				else
				{
					*info = zbx_strdup(NULL, *pvalue);
					ret = SUCCEED;
				}
				break;
			default:
				if (NULL == (pvalue = GET_MSG_RESULT(&result)))
					*info = zbx_dsprintf(NULL, "unknown error with code %d", errcode);
				else
					*info = zbx_strdup(NULL, *pvalue);
		}

		zbx_vector_ptr_clear_ext(&add_results, (zbx_mem_free_func_t)zbx_free_result_ptr);
		zbx_vector_ptr_destroy(&add_results);
	}

	zbx_clean_items(&item, 1, &result);

	zbx_free(item.params);
	zbx_free(item.posts);
	zbx_free(item.headers);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

void	zbx_trapper_item_test(zbx_socket_t *sock, const struct zbx_json_parse *jp)
{
	char			sessionid[MAX_STRING_LEN];
	zbx_user_t		user;
	struct zbx_json_parse	jp_data;
	struct zbx_json		json;
	char			tmp[MAX_ID_LEN + 1];
	zbx_uint64_t		proxy_hostid;
	int			ret;
	char			*info;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (SUCCEED != zbx_json_value_by_name(jp, ZBX_PROTO_TAG_SID, sessionid, sizeof(sessionid), NULL) ||
			SUCCEED != DBget_user_by_active_session(sessionid, &user) || USER_TYPE_ZABBIX_ADMIN > user.type)
	{
		zbx_send_response(sock, FAIL, "Permission denied.", CONFIG_TIMEOUT);
		return;
	}

	if (SUCCEED != zbx_json_brackets_by_name(jp, ZBX_PROTO_TAG_DATA, &jp_data))
	{
		char	*error;

		error = zbx_dsprintf(NULL, "Cannot parse request tag: %s.", ZBX_PROTO_TAG_DATA);
		zbx_send_response(sock, FAIL, error, CONFIG_TIMEOUT);
		zbx_free(error);
		return;
	}

	zbx_json_init(&json, 1024);

	if (SUCCEED == zbx_json_value_by_name(&jp_data, ZBX_PROTO_TAG_PROXY_HOSTID, tmp, sizeof(tmp), NULL))
		ZBX_STR2UINT64(proxy_hostid, tmp);
	else
		proxy_hostid = 0;

	ret = zbx_trapper_item_test_run(&jp_data, proxy_hostid, &info);

	zbx_json_addstring(&json, ZBX_PROTO_TAG_RESPONSE, "success", ZBX_JSON_TYPE_STRING);
	zbx_json_addobject(&json, ZBX_PROTO_TAG_DATA);
	zbx_json_addstring(&json, SUCCEED == ret ? ZBX_PROTO_TAG_RESULT : ZBX_PROTO_TAG_ERROR, info,
			ZBX_JSON_TYPE_STRING);
	zbx_tcp_send_bytes_to(sock, json.buffer, json.buffer_size, CONFIG_TIMEOUT);

	zabbix_log(LOG_LEVEL_DEBUG, "%s() json.buffer:'%s'", __func__, json.buffer);

	zbx_free(info);
	zbx_json_free(&json);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}
