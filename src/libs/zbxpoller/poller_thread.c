/*
** Copyright (C) 2001-2024 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

#include "poller.h"
#include "zbxpoller.h"

#include "zbxdb.h"
#include "checks_external.h"
#include "checks_internal.h"
#include "checks_script.h"
#include "checks_browser.h"
#include "checks_simple.h"

#ifdef HAVE_NETSNMP
#	include "checks_snmp.h"
#endif

#ifdef HAVE_LIBCURL
#	include "checks_http.h"
#endif

#ifdef HAVE_UNIXODBC
#	include "checks_db.h"
#endif

#include "checks_java.h"
#include "checks_calculated.h"

#include "zbxexpression.h"
#include "zbxalgo.h"
#include "zbxcacheconfig.h"
#include "zbxdbhigh.h"
#include "zbxipcservice.h"
#include "zbxstr.h"
#include "zbxthreads.h"
#include "zbxtimekeeper.h"
#include "zbxnix.h"
#include "zbxself.h"
#include "zbxrtc.h"
#include "zbxjson.h"
#include "zbxhttp.h"
#include "zbxexpr.h"
#include "zbxlog.h"
#include "zbxavailability.h"
#include "zbx_availability_constants.h"
#include "zbxcomms.h"
#include "zbxnum.h"
#include "zbxtime.h"
#include "zbx_rtc_constants.h"
#include "zbx_item_constants.h"
#include "zbxpreproc.h"
#include "zbxsysinfo.h"
#include "zbx_expression_constants.h"
#include "zbxinterface.h"

static zbx_get_progname_f	zbx_get_progname_cb = NULL;

zbx_get_progname_f	poller_get_progname(void)
{
	return zbx_get_progname_cb;
}

static zbx_get_program_type_f	zbx_get_program_type_cb = NULL;

zbx_get_program_type_f	poller_get_program_type(void)
{
	return zbx_get_program_type_cb;
}

void	zbx_free_agent_result_ptr(AGENT_RESULT *result)
{
	zbx_free_agent_result(result);
	zbx_free(result);
}

static int	get_value(zbx_dc_item_t *item, AGENT_RESULT *result, zbx_vector_agent_result_ptr_t *add_results,
		const zbx_config_comms_args_t *config_comms, int config_startup_time, unsigned char program_type,
		zbx_get_config_forks_f get_config_forks, const char *config_java_gateway, int config_java_gateway_port,
		const char *config_externalscripts, zbx_get_value_internal_ext_f get_value_internal_ext_cb,
		const char *config_ssh_key_location, const char *config_webdriver_url)
{
	int	res = FAIL, version = item->interface.version;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() key:'%s'", __func__, item->key_orig);

	switch (item->type)
	{
		case ITEM_TYPE_ZABBIX:
			res = zbx_agent_get_value(item, config_comms->config_source_ip, program_type, result, &version);
			break;
		case ITEM_TYPE_SIMPLE:
			/* simple checks use their own timeouts */
			res = get_value_simple(item, result, add_results, get_config_forks);
			break;
		case ITEM_TYPE_INTERNAL:
			res = get_value_internal(item, result, config_comms, config_startup_time, config_java_gateway,
					config_java_gateway_port, get_config_forks, get_value_internal_ext_cb);
			break;
		case ITEM_TYPE_DB_MONITOR:
#ifdef HAVE_UNIXODBC
			res = get_value_db(item, result);
#else
			SET_MSG_RESULT(result,
					zbx_strdup(NULL, "Support for Database monitor checks was not compiled in."));
			res = CONFIG_ERROR;
#endif
			break;
		case ITEM_TYPE_EXTERNAL:
			/* external checks use their own timeouts */
			res = get_value_external(item, config_externalscripts, result);
			break;
		case ITEM_TYPE_SSH:
#if defined(HAVE_SSH2) || defined(HAVE_SSH)
			res = zbx_ssh_get_value(item, config_comms->config_source_ip, config_ssh_key_location, result);
#else
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Support for SSH checks was not compiled in."));
			res = CONFIG_ERROR;
#endif
			break;
		case ITEM_TYPE_TELNET:
			res = zbx_telnet_get_value(item, config_comms->config_source_ip, config_ssh_key_location,
					result);
			break;
		case ITEM_TYPE_CALCULATED:
			res = get_value_calculated(item, result);
			break;
		case ITEM_TYPE_HTTPAGENT:
#ifdef HAVE_LIBCURL
			res = get_value_http(item, config_comms->config_source_ip, config_comms->config_ssl_ca_location,
					config_comms->config_ssl_cert_location, config_comms->config_ssl_key_location,
					result);
#else
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Support for HTTP agent checks was not compiled in."));
			res = CONFIG_ERROR;
#endif
			break;
		case ITEM_TYPE_SCRIPT:
			res = get_value_script(item, config_comms->config_source_ip, result);
			break;
		case ITEM_TYPE_BROWSER:
			res = get_value_browser(item, config_webdriver_url, config_comms->config_source_ip, result);
			break;
		default:
			SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Not supported item type:%d", item->type));
			res = CONFIG_ERROR;
	}

	if (SUCCEED != res)
	{
		if (!ZBX_ISSET_MSG(result))
			SET_MSG_RESULT(result, zbx_strdup(NULL, ZBX_NOTSUPPORTED_MSG));

		zabbix_log(LOG_LEVEL_DEBUG, "Item [%s:%s] error: %s", item->host.host, item->key_orig, result->msg);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(res));

	return res;
}

static int	parse_query_fields(const zbx_dc_item_t *item, char **query_fields, unsigned char expand_macros)
{
	struct zbx_json_parse	jp_array, jp_object;
	char			name[MAX_STRING_LEN], value[MAX_STRING_LEN], *str = NULL;
	const char		*member, *element = NULL;
	size_t			alloc_len, offset;

	if ('\0' == **query_fields)
		return SUCCEED;

	if (SUCCEED != zbx_json_open(*query_fields, &jp_array))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot parse query fields: %s", zbx_json_strerror());
		return FAIL;
	}

	if (NULL == (element = zbx_json_next(&jp_array, element)))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot parse query fields: array is empty");
		return FAIL;
	}

	do
	{
		char	*data = NULL;

		if (SUCCEED != zbx_json_brackets_open(element, &jp_object) ||
				NULL == (member = zbx_json_pair_next(&jp_object, NULL, name, sizeof(name))) ||
				NULL == zbx_json_decodevalue(member, value, sizeof(value), NULL))
		{
			zabbix_log(LOG_LEVEL_ERR, "cannot parse query fields: %s", zbx_json_strerror());
			zbx_free(str);
			return FAIL;
		}

		if (NULL == str && NULL == strchr(item->url, '?'))
			zbx_chrcpy_alloc(&str, &alloc_len, &offset, '?');
		else
			zbx_chrcpy_alloc(&str, &alloc_len, &offset, '&');

		data = zbx_strdup(data, name);
		if (ZBX_MACRO_EXPAND_YES == expand_macros)
		{
			zbx_substitute_simple_macros(NULL, NULL, NULL,NULL, NULL, &item->host, item, NULL, NULL, NULL,
					NULL, NULL, &data, ZBX_MACRO_TYPE_HTTP_RAW, NULL, 0);
		}
		zbx_url_encode(data, &data);
		zbx_strcpy_alloc(&str, &alloc_len, &offset, data);
		zbx_chrcpy_alloc(&str, &alloc_len, &offset, '=');

		data = zbx_strdup(data, value);
		if (ZBX_MACRO_EXPAND_YES == expand_macros)
		{
			zbx_substitute_simple_macros_unmasked(NULL, NULL, NULL,NULL, NULL, &item->host, item, NULL,
					NULL, NULL, NULL, NULL, &data, ZBX_MACRO_TYPE_HTTP_RAW, NULL, 0);
		}

		zbx_url_encode(data, &data);
		zbx_strcpy_alloc(&str, &alloc_len, &offset, data);

		free(data);
	}
	while (NULL != (element = zbx_json_next(&jp_array, element)));

	zbx_free(*query_fields);
	*query_fields = str;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: resolves macros in JMX endpoint context                           *
 *                                                                            *
 * Parameters: p            - [IN] macro resolver data structure              *
 *             args         - [IN] list of variadic parameters                *
 *                                 Expected content:                          *
 *                                  - zbx_dc_um_handle_t *um_handle: user     *
 *                                      macro cache handle                    *
 *                                  - const zbx_dc_item_t *dc_item: item      *
 *                                      information                           *
 *             replace_with - [OUT] pointer to value to replace macro with    *
 *             data         - [IN/OUT] pointer to original input raw string   *
 *                                  (for macro in macro resolving), not used  *
 *             error        - [OUT] pointer to pre-allocated error message    *
 *                                  buffer (can be NULL), not used            *
 *             maxerrlen    - [IN] size of error message buffer (can be 0 if  *
 *                                 'error' is NULL), not used                 *
 *                                                                            *
 * Return value: SUCCEED if macro data were resolved successfully.            *
 *               Otherwise FAIL.                                              *
 *                                                                            *
 ******************************************************************************/
static int	macro_jmx_endpoint_resolv(zbx_macro_resolv_data_t *p, va_list args, char **replace_with, char **data,
		char *error, size_t maxerrlen)
{
	int			ret = SUCCEED;

	/* Passed arguments */
	zbx_dc_um_handle_t	*um_handle = va_arg(args, zbx_dc_um_handle_t *);
	zbx_dc_item_t		*dc_item = va_arg(args, zbx_dc_item_t *);

	ZBX_UNUSED(data);
	ZBX_UNUSED(error);
	ZBX_UNUSED(maxerrlen);

	if (0 == p->indexed)
	{
		if (ZBX_TOKEN_USER_MACRO == p->token.type || (ZBX_TOKEN_USER_FUNC_MACRO == p->token.type &&
				0 == strncmp(p->macro, MVAR_USER_MACRO, ZBX_CONST_STRLEN(MVAR_USER_MACRO))))
		{
			zbx_dc_get_user_macro(um_handle, p->macro, &dc_item->host.hostid, 1, replace_with);
			p->pos = p->token.loc.r;
		}
		else if (0 == strcmp(p->macro, MVAR_HOST_HOST) || 0 == strcmp(p->macro, MVAR_HOSTNAME))
		{
			*replace_with = zbx_strdup(*replace_with, dc_item->host.host);
		}
		else if (0 == strcmp(p->macro, MVAR_HOST_NAME))
		{
			*replace_with = zbx_strdup(*replace_with, dc_item->host.name);
		}
		else if (0 == strcmp(p->macro, MVAR_HOST_IP) || 0 == strcmp(p->macro, MVAR_IPADDRESS))
		{
			if (INTERFACE_TYPE_UNKNOWN != dc_item->interface.type)
			{
				*replace_with = zbx_strdup(*replace_with, dc_item->interface.ip_orig);
			}
			else
			{
				ret = zbx_dc_get_interface_value(dc_item->host.hostid, dc_item->itemid, replace_with,
						ZBX_REQUEST_HOST_IP);
			}
		}
		else if	(0 == strcmp(p->macro, MVAR_HOST_DNS))
		{
			if (INTERFACE_TYPE_UNKNOWN != dc_item->interface.type)
			{
				*replace_with = zbx_strdup(*replace_with, dc_item->interface.dns_orig);
			}
			else
			{
				ret = zbx_dc_get_interface_value(dc_item->host.hostid, dc_item->itemid, replace_with,
						ZBX_REQUEST_HOST_DNS);
			}
		}
		else if (0 == strcmp(p->macro, MVAR_HOST_CONN))
		{
			if (INTERFACE_TYPE_UNKNOWN != dc_item->interface.type)
			{
				*replace_with = zbx_strdup(*replace_with, dc_item->interface.addr);
			}
			else
			{
				ret = zbx_dc_get_interface_value(dc_item->host.hostid, dc_item->itemid, replace_with,
						ZBX_REQUEST_HOST_CONN);
			}
		}
		else if (0 == strcmp(p->macro, MVAR_HOST_PORT))
		{
			if (INTERFACE_TYPE_UNKNOWN != dc_item->interface.type)
			{
				*replace_with = zbx_dsprintf(*replace_with, "%u", dc_item->interface.port);
			}
			else
			{
				ret = zbx_dc_get_interface_value(dc_item->host.hostid, dc_item->itemid, replace_with,
					ZBX_REQUEST_HOST_PORT);
			}
		}
	}

	return ret;
}

void	zbx_prepare_items(zbx_dc_item_t *items, int *errcodes, int num, AGENT_RESULT *results,
		unsigned char expand_macros)
{
	char			error[ZBX_ITEM_ERROR_LEN_MAX], *timeout = NULL;
	zbx_dc_um_handle_t	*um_handle;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() num:%d", __func__, num);

	if (ZBX_MACRO_EXPAND_YES == expand_macros)
		um_handle = zbx_dc_open_user_macros();

	for (int i = 0; i < num; i++)
	{
		zbx_init_agent_result(&results[i]);
		errcodes[i] = SUCCEED;

		if (ZBX_MACRO_EXPAND_YES == expand_macros)
		{
			ZBX_STRDUP(items[i].key, items[i].key_orig);
			if (SUCCEED != zbx_substitute_key_macros_unmasked(&items[i].key, NULL, &items[i], NULL, NULL,
					ZBX_MACRO_TYPE_ITEM_KEY, error, sizeof(error)))
			{
				SET_MSG_RESULT(&results[i], zbx_strdup(NULL, error));
				errcodes[i] = CONFIG_ERROR;
				continue;
			}
		}

		switch (items[i].type)
		{
			case ITEM_TYPE_ZABBIX:
			case ITEM_TYPE_SNMP:
			case ITEM_TYPE_JMX:
				if (FAIL == zbx_is_ushort(items[i].interface.port_orig, &items[i].interface.port))
				{
					SET_MSG_RESULT(&results[i], zbx_dsprintf(NULL, "Invalid port number [%s]",
								items[i].interface.port_orig));
					errcodes[i] = CONFIG_ERROR;
					continue;
				}
				break;
		}

		switch (items[i].type)
		{
			case ITEM_TYPE_ZABBIX:
			case ITEM_TYPE_ZABBIX_ACTIVE:
			case ITEM_TYPE_SIMPLE:
			case ITEM_TYPE_EXTERNAL:
			case ITEM_TYPE_DB_MONITOR:
			case ITEM_TYPE_SSH:
			case ITEM_TYPE_TELNET:
			case ITEM_TYPE_SNMP:
			case ITEM_TYPE_SCRIPT:
			case ITEM_TYPE_BROWSER:
			case ITEM_TYPE_HTTPAGENT:
				ZBX_STRDUP(timeout, items[i].timeout_orig);
				break;
		}

		switch (items[i].type)
		{
			case ITEM_TYPE_ZABBIX:
			case ITEM_TYPE_ZABBIX_ACTIVE:
			case ITEM_TYPE_EXTERNAL:
				if (ZBX_MACRO_EXPAND_NO == expand_macros)
					break;

				zbx_substitute_simple_macros(NULL, NULL, NULL, NULL, &items[i].host.hostid, NULL, NULL,
						NULL, NULL, NULL, NULL, NULL, &timeout, ZBX_MACRO_TYPE_COMMON, NULL,
						0);
				break;
			case ITEM_TYPE_SNMP:
				if (ZBX_MACRO_EXPAND_NO == expand_macros)
					break;

				if (ZBX_IF_SNMP_VERSION_3 == items[i].snmp_version)
				{
					ZBX_STRDUP(items[i].snmpv3_securityname, items[i].snmpv3_securityname_orig);
					ZBX_STRDUP(items[i].snmpv3_authpassphrase, items[i].snmpv3_authpassphrase_orig);
					ZBX_STRDUP(items[i].snmpv3_privpassphrase, items[i].snmpv3_privpassphrase_orig);
					ZBX_STRDUP(items[i].snmpv3_contextname, items[i].snmpv3_contextname_orig);

					zbx_substitute_simple_macros_unmasked(NULL, NULL, NULL, NULL,
							&items[i].host.hostid, NULL, NULL, NULL, NULL, NULL, NULL, NULL,
							&items[i].snmpv3_securityname, ZBX_MACRO_TYPE_COMMON, NULL, 0);
					zbx_substitute_simple_macros_unmasked(NULL, NULL, NULL, NULL,
							&items[i].host.hostid, NULL, NULL, NULL, NULL, NULL, NULL, NULL,
							&items[i].snmpv3_authpassphrase, ZBX_MACRO_TYPE_COMMON, NULL,
							0);
					zbx_substitute_simple_macros_unmasked(NULL, NULL, NULL, NULL,
							&items[i].host.hostid, NULL, NULL, NULL, NULL, NULL, NULL, NULL,
							&items[i].snmpv3_privpassphrase, ZBX_MACRO_TYPE_COMMON, NULL,
							0);
					zbx_substitute_simple_macros_unmasked(NULL, NULL, NULL, NULL,
							&items[i].host.hostid, NULL, NULL, NULL, NULL, NULL, NULL, NULL,
							&items[i].snmpv3_contextname, ZBX_MACRO_TYPE_COMMON, NULL, 0);
				}

				ZBX_STRDUP(items[i].snmp_community, items[i].snmp_community_orig);
				ZBX_STRDUP(items[i].snmp_oid, items[i].snmp_oid_orig);

				zbx_substitute_simple_macros_unmasked(NULL, NULL, NULL, NULL, &items[i].host.hostid,
						NULL, NULL, NULL, NULL, NULL, NULL, NULL, &items[i].snmp_community,
						ZBX_MACRO_TYPE_COMMON, NULL, 0);
				if (SUCCEED != zbx_substitute_key_macros(&items[i].snmp_oid, &items[i].host.hostid,
						NULL, NULL, NULL, ZBX_MACRO_TYPE_SNMP_OID, error, sizeof(error)))
				{
					SET_MSG_RESULT(&results[i], zbx_strdup(NULL, error));
					errcodes[i] = CONFIG_ERROR;
					zbx_free(timeout);
					continue;
				}

				zbx_substitute_simple_macros(NULL, NULL, NULL, NULL, &items[i].host.hostid, NULL, NULL,
						NULL, NULL, NULL, NULL, NULL, &timeout, ZBX_MACRO_TYPE_COMMON, NULL,
						0);
				break;
			case ITEM_TYPE_SCRIPT:
			case ITEM_TYPE_BROWSER:
				if (ZBX_MACRO_EXPAND_NO == expand_macros)
					break;

				zbx_substitute_simple_macros(NULL, NULL, NULL, NULL, &items[i].host.hostid, NULL, NULL,
						NULL, NULL, NULL, NULL, NULL, &timeout, ZBX_MACRO_TYPE_COMMON, NULL, 0);
				for (int j = 0; j < items[i].script_params.values_num; j++)
				{
					zbx_substitute_simple_macros_unmasked(NULL, NULL, NULL, NULL, NULL, NULL,
							&items[i], NULL, NULL, NULL, NULL, NULL,
							(char **)&items[i].script_params.values[j].first,
							ZBX_MACRO_TYPE_SCRIPT_PARAMS_FIELD, NULL, 0);

					zbx_substitute_simple_macros_unmasked(NULL, NULL, NULL, NULL, NULL, NULL,
							&items[i], NULL, NULL, NULL, NULL, NULL,
							(char **)&items[i].script_params.values[j].second,
							ZBX_MACRO_TYPE_SCRIPT_PARAMS_FIELD, NULL, 0);
				}

				zbx_substitute_simple_macros_unmasked(NULL, NULL, NULL, NULL, &items[i].host.hostid,
						NULL, NULL, NULL, NULL, NULL, NULL, NULL, &items[i].params,
						ZBX_MACRO_TYPE_COMMON, NULL, 0);
				break;
			case ITEM_TYPE_SSH:
				if (ZBX_MACRO_EXPAND_NO == expand_macros)
					break;

				ZBX_STRDUP(items[i].publickey, items[i].publickey_orig);
				ZBX_STRDUP(items[i].privatekey, items[i].privatekey_orig);

				zbx_substitute_simple_macros(NULL, NULL, NULL, NULL, &items[i].host.hostid, NULL, NULL,
						NULL, NULL, NULL, NULL, NULL, &items[i].publickey,
						ZBX_MACRO_TYPE_COMMON, NULL, 0);
				zbx_substitute_simple_macros(NULL, NULL, NULL, NULL, &items[i].host.hostid, NULL, NULL,
						NULL, NULL, NULL, NULL, NULL, &items[i].privatekey,
						ZBX_MACRO_TYPE_COMMON, NULL, 0);
				ZBX_FALLTHROUGH;
			case ITEM_TYPE_TELNET:
			case ITEM_TYPE_DB_MONITOR:
				if (ZBX_MACRO_EXPAND_NO == expand_macros)
					break;

				zbx_substitute_simple_macros_unmasked(NULL, NULL, NULL, NULL, NULL, NULL, &items[i],
						NULL, NULL, NULL, NULL, NULL, &items[i].params,
						ZBX_MACRO_TYPE_PARAMS_FIELD, NULL, 0);
				ZBX_FALLTHROUGH;
			case ITEM_TYPE_SIMPLE:
				if (ZBX_MACRO_EXPAND_NO == expand_macros)
					break;

				items[i].username = zbx_strdup(items[i].username, items[i].username_orig);
				items[i].password = zbx_strdup(items[i].password, items[i].password_orig);

				zbx_substitute_simple_macros_unmasked(NULL, NULL, NULL, NULL, &items[i].host.hostid,
						NULL, NULL, NULL, NULL, NULL, NULL, NULL, &items[i].username,
						ZBX_MACRO_TYPE_COMMON, NULL, 0);
				zbx_substitute_simple_macros_unmasked(NULL, NULL, NULL, NULL, &items[i].host.hostid,
						NULL, NULL, NULL, NULL, NULL, NULL, NULL, &items[i].password,
						ZBX_MACRO_TYPE_COMMON, NULL, 0);

				zbx_substitute_simple_macros(NULL, NULL, NULL, NULL, &items[i].host.hostid, NULL, NULL,
						NULL, NULL, NULL, NULL, NULL, &timeout, ZBX_MACRO_TYPE_COMMON, NULL,
						0);
				break;
			case ITEM_TYPE_JMX:
				if (ZBX_MACRO_EXPAND_NO == expand_macros)
					break;

				items[i].username = zbx_strdup(items[i].username, items[i].username_orig);
				items[i].password = zbx_strdup(items[i].password, items[i].password_orig);
				items[i].jmx_endpoint = zbx_strdup(items[i].jmx_endpoint, items[i].jmx_endpoint_orig);

				zbx_substitute_simple_macros_unmasked(NULL, NULL, NULL, NULL, &items[i].host.hostid,
						NULL, NULL, NULL, NULL, NULL, NULL, NULL, &items[i].username,
						ZBX_MACRO_TYPE_COMMON, NULL, 0);
				zbx_substitute_simple_macros_unmasked(NULL, NULL, NULL, NULL, &items[i].host.hostid,
						NULL, NULL, NULL, NULL, NULL, NULL, NULL, &items[i].password,
						ZBX_MACRO_TYPE_COMMON, NULL, 0);
				zbx_substitute_macros(&items[i].jmx_endpoint, NULL, 0, &macro_jmx_endpoint_resolv,
						um_handle, &items[i]);
				break;
			case ITEM_TYPE_HTTPAGENT:
				if (ZBX_MACRO_EXPAND_YES == expand_macros)
				{
					ZBX_STRDUP(items[i].url, items[i].url_orig);
					ZBX_STRDUP(items[i].status_codes, items[i].status_codes_orig);
					ZBX_STRDUP(items[i].http_proxy, items[i].http_proxy_orig);
					ZBX_STRDUP(items[i].ssl_cert_file, items[i].ssl_cert_file_orig);
					ZBX_STRDUP(items[i].ssl_key_file, items[i].ssl_key_file_orig);
					ZBX_STRDUP(items[i].ssl_key_password, items[i].ssl_key_password_orig);
					ZBX_STRDUP(items[i].username, items[i].username_orig);
					ZBX_STRDUP(items[i].password, items[i].password_orig);
					ZBX_STRDUP(items[i].query_fields, items[i].query_fields_orig);

					zbx_substitute_simple_macros(NULL, NULL, NULL, NULL, &items[i].host.hostid,
							NULL, NULL, NULL, NULL, NULL, NULL, NULL, &timeout,
							ZBX_MACRO_TYPE_COMMON, NULL, 0);

					zbx_substitute_simple_macros_unmasked(NULL, NULL, NULL, NULL, NULL,
							&items[i].host, &items[i], NULL, NULL, NULL, NULL, NULL,
							&items[i].url, ZBX_MACRO_TYPE_HTTP_RAW, NULL, 0);
				}

				if (SUCCEED != zbx_http_punycode_encode_url(&items[i].url))
				{
					SET_MSG_RESULT(&results[i], zbx_strdup(NULL,
							"Cannot encode URL into punycode"));
					errcodes[i] = CONFIG_ERROR;
					zbx_free(timeout);
					continue;
				}

				if (FAIL == parse_query_fields(&items[i], &items[i].query_fields, expand_macros))
				{
					SET_MSG_RESULT(&results[i], zbx_strdup(NULL, "Invalid query fields"));
					errcodes[i] = CONFIG_ERROR;
					zbx_free(timeout);
					continue;
				}

				if (ZBX_MACRO_EXPAND_NO == expand_macros)
					break;

				switch (items[i].post_type)
				{
					case ZBX_POSTTYPE_XML:
						if (SUCCEED != zbx_substitute_macros_xml_unmasked(&items[i].posts,
								&items[i], NULL, NULL, error, sizeof(error)))
						{
							SET_MSG_RESULT(&results[i], zbx_dsprintf(NULL, "%s.", error));
							errcodes[i] = CONFIG_ERROR;
							zbx_free(timeout);
							continue;
						}
						break;
					case ZBX_POSTTYPE_JSON:
						zbx_substitute_simple_macros_unmasked(NULL, NULL, NULL,NULL, NULL,
								&items[i].host, &items[i], NULL, NULL, NULL, NULL, NULL,
								&items[i].posts, ZBX_MACRO_TYPE_HTTP_JSON, NULL, 0);
						break;
					default:
						zbx_substitute_simple_macros_unmasked(NULL, NULL, NULL,NULL, NULL,
								&items[i].host, &items[i], NULL, NULL, NULL, NULL, NULL,
								&items[i].posts, ZBX_MACRO_TYPE_HTTP_RAW, NULL, 0);
						break;
				}

				zbx_substitute_simple_macros_unmasked(NULL, NULL, NULL,NULL, NULL, &items[i].host,
						&items[i], NULL, NULL, NULL, NULL, NULL, &items[i].headers,
						ZBX_MACRO_TYPE_HTTP_RAW, NULL, 0);
				zbx_substitute_simple_macros(NULL, NULL, NULL, NULL, &items[i].host.hostid, NULL,
						NULL, NULL, NULL, NULL, NULL, NULL, &items[i].status_codes,
						ZBX_MACRO_TYPE_COMMON, NULL, 0);
				zbx_substitute_simple_macros(NULL, NULL, NULL, NULL, &items[i].host.hostid, NULL,
						NULL, NULL, NULL, NULL, NULL, NULL, &items[i].http_proxy,
						ZBX_MACRO_TYPE_COMMON, NULL, 0);
				zbx_substitute_simple_macros(NULL, NULL, NULL,NULL, NULL, &items[i].host, &items[i],
						NULL, NULL, NULL, NULL, NULL, &items[i].ssl_cert_file,
						ZBX_MACRO_TYPE_HTTP_RAW, NULL, 0);
				zbx_substitute_simple_macros(NULL, NULL, NULL,NULL, NULL, &items[i].host, &items[i],
						NULL, NULL, NULL, NULL, NULL, &items[i].ssl_key_file,
						ZBX_MACRO_TYPE_HTTP_RAW, NULL, 0);
				zbx_substitute_simple_macros_unmasked(NULL, NULL, NULL, NULL, &items[i].host.hostid,
						NULL, NULL, NULL, NULL, NULL, NULL, NULL, &items[i].ssl_key_password,
						ZBX_MACRO_TYPE_COMMON, NULL, 0);
				zbx_substitute_simple_macros_unmasked(NULL, NULL, NULL, NULL, &items[i].host.hostid,
						NULL, NULL, NULL, NULL, NULL, NULL, NULL, &items[i].username,
						ZBX_MACRO_TYPE_COMMON, NULL, 0);
				zbx_substitute_simple_macros_unmasked(NULL, NULL, NULL, NULL, &items[i].host.hostid,
						NULL, NULL, NULL, NULL, NULL, NULL, NULL, &items[i].password,
						ZBX_MACRO_TYPE_COMMON, NULL, 0);
				break;
		}

		if (NULL != timeout)
		{
			int	timeout_sec = 0;

			if (FAIL == zbx_validate_item_timeout(timeout, &timeout_sec, error, sizeof(error)))
			{
				SET_MSG_RESULT(&results[i], zbx_strdup(NULL, error));
				errcodes[i] = CONFIG_ERROR;
			}
			else
				items[i].timeout = timeout_sec;
		}
		zbx_free(timeout);
	}

	if (ZBX_MACRO_EXPAND_YES == expand_macros)
		zbx_dc_close_user_macros(um_handle);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/* Actually this could be called by trapper, without poller being initialized, */
/* so cannot call poller_get_progname(), need progname to be passed directly. */
void	zbx_check_items(zbx_dc_item_t *items, int *errcodes, int num, AGENT_RESULT *results,
		zbx_vector_agent_result_ptr_t *add_results, unsigned char poller_type,
		const zbx_config_comms_args_t *config_comms, int config_startup_time, unsigned char program_type,
		const char *progname, zbx_get_config_forks_f get_config_forks, const char *config_java_gateway,
		int config_java_gateway_port, const char *config_externalscripts,
		zbx_get_value_internal_ext_f get_value_internal_ext_cb, const char *config_ssh_key_location,
		const char *config_webdriver_url)
{
	if (ITEM_TYPE_SNMP == items[0].type)
	{
#ifndef HAVE_NETSNMP
		ZBX_UNUSED(poller_type);
		ZBX_UNUSED(progname);

		for (int i = 0; i < num; i++)
		{
			if (SUCCEED != errcodes[i])
				continue;

			SET_MSG_RESULT(&results[i], zbx_strdup(NULL, "Support for SNMP checks was not compiled in."));
			errcodes[i] = CONFIG_ERROR;
		}
#else
		/* legacy SNMP checks use config timeout
		 * walk[ and get[ use item timeout */
		get_values_snmp(items, results, errcodes, num, poller_type, config_comms->config_source_ip,
				config_comms->config_timeout, progname);
#endif
	}
	else if (ITEM_TYPE_JMX == items[0].type)
	{
		get_values_java(ZBX_JAVA_GATEWAY_REQUEST_JMX, items, results, errcodes, num,
				config_comms->config_timeout, config_comms->config_source_ip, config_java_gateway,
				config_java_gateway_port);
	}
	else if (1 == num)
	{
		if (SUCCEED == errcodes[0])
		{
			errcodes[0] = get_value(&items[0], &results[0], add_results, config_comms,
					config_startup_time, program_type, get_config_forks, config_java_gateway,
					config_java_gateway_port, config_externalscripts, get_value_internal_ext_cb,
					config_ssh_key_location, config_webdriver_url);
		}
	}
	else
		THIS_SHOULD_NEVER_HAPPEN;
}

void	zbx_clean_items(zbx_dc_item_t *items, int num, AGENT_RESULT *results)
{
	for (int i = 0; i < num; i++)
	{
		zbx_free(items[i].key);

		switch (items[i].type)
		{
			case ITEM_TYPE_SNMP:
				if (ZBX_IF_SNMP_VERSION_3 == items[i].snmp_version)
				{
					zbx_free(items[i].snmpv3_securityname);
					zbx_free(items[i].snmpv3_authpassphrase);
					zbx_free(items[i].snmpv3_privpassphrase);
					zbx_free(items[i].snmpv3_contextname);
				}

				zbx_free(items[i].snmp_community);
				zbx_free(items[i].snmp_oid);
				break;
			case ITEM_TYPE_HTTPAGENT:
				zbx_free(items[i].url);
				zbx_free(items[i].query_fields);
				zbx_free(items[i].status_codes);
				zbx_free(items[i].http_proxy);
				zbx_free(items[i].ssl_cert_file);
				zbx_free(items[i].ssl_key_file);
				zbx_free(items[i].ssl_key_password);
				zbx_free(items[i].username);
				zbx_free(items[i].password);
				break;
			case ITEM_TYPE_SSH:
				zbx_free(items[i].publickey);
				zbx_free(items[i].privatekey);
				ZBX_FALLTHROUGH;
			case ITEM_TYPE_TELNET:
			case ITEM_TYPE_DB_MONITOR:
			case ITEM_TYPE_SIMPLE:
				zbx_free(items[i].username);
				zbx_free(items[i].password);
				break;
			case ITEM_TYPE_JMX:
				zbx_free(items[i].username);
				zbx_free(items[i].password);
				zbx_free(items[i].jmx_endpoint);
				break;
		}

		zbx_free_agent_result(&results[i]);
	}
}

ZBX_PTR_VECTOR_IMPL(agent_result_ptr, AGENT_RESULT*)

/***********************************************************************************
 *                                                                                 *
 * Purpose: retrieves values of metrics from monitored hosts                       *
 *                                                                                 *
 * Parameters: poller_type                - [IN] poller type (ZBX_POLLER_TYPE_...) *
 *             nextcheck                  - [OUT] item nextcheck                   *
 *             config_comms               - [IN] server/proxy configuration for    *
 *                                               communication                     *
 *             config_startup_time        - [IN] program startup time              *
 *             config_unavailable_delay   - [IN]                                   *
 *             config_unreachable_period  - [IN]                                   *
 *             config_unreachable_delay   - [IN]                                   *
 *             program_type               - [IN]                                   *
 *             progname                   - [IN]                                   *
 *             get_config_forks           - [IN]                                   *
 *             config_java_gateway        - [IN]                                   *
 *             config_java_gateway_port   - [IN]                                   *
 *             config_externalscripts     - [IN]                                   *
 *             get_value_internal_ext_cb  - [IN]                                   *
 *             config_ssh_key_location    - [IN]                                   *
 *             config_webdriver_url       - [IN]                                   *
 *                                                                                 *
 * Return value: number of items processed                                         *
 *                                                                                 *
 * Comments: Processes single item at a time except for Java, SNMP items,          *
 *           see zbx_dc_config_get_poller_items().                                 *
 *                                                                                 *
 **********************************************************************************/
static int	get_values(unsigned char poller_type, int *nextcheck, const zbx_config_comms_args_t *config_comms,
		int config_startup_time, int config_unavailable_delay, int config_unreachable_period,
		int config_unreachable_delay, unsigned char program_type, const char *progname,
		zbx_get_config_forks_f get_config_forks, const char *config_java_gateway, int config_java_gateway_port,
		const char *config_externalscripts, zbx_get_value_internal_ext_f get_value_internal_ext_cb,
		const char *config_ssh_key_location, const char *config_webdriver_url)
{
	zbx_dc_item_t			item, *items;
	AGENT_RESULT			results[ZBX_MAX_POLLER_ITEMS];
	int				errcodes[ZBX_MAX_POLLER_ITEMS];
	zbx_timespec_t			timespec;
	int				num, last_available = ZBX_INTERFACE_AVAILABLE_UNKNOWN;
	zbx_vector_agent_result_ptr_t	add_results;
	unsigned char			*data = NULL;
	size_t				data_alloc = 0, data_offset = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	items = &item;
	num = zbx_dc_config_get_poller_items(poller_type, config_comms->config_timeout, 0, 0, &items);

	if (0 == num)
	{
		*nextcheck = zbx_dc_config_get_poller_nextcheck(poller_type);
		goto exit;
	}

	zbx_vector_agent_result_ptr_create(&add_results);

	zbx_prepare_items(items, errcodes, num, results, ZBX_MACRO_EXPAND_YES);
	zbx_check_items(items, errcodes, num, results, &add_results, poller_type, config_comms, config_startup_time,
			program_type, progname, get_config_forks, config_java_gateway, config_java_gateway_port,
			config_externalscripts, get_value_internal_ext_cb, config_ssh_key_location,
			config_webdriver_url);

	zbx_timespec(&timespec);

	/* process item values */
	for (int i = 0; i < num; i++)
	{
		switch (errcodes[i])
		{
			case SUCCEED:
			case NOTSUPPORTED:
			case AGENT_ERROR:
				if (ZBX_INTERFACE_AVAILABLE_TRUE != last_available)
				{
					zbx_activate_item_interface(&timespec, &items[i].interface, items[i].itemid,
							items[i].type, items[i].host.host, 0, &data, &data_alloc,
							&data_offset);
					last_available = ZBX_INTERFACE_AVAILABLE_TRUE;
				}
				break;
			case NETWORK_ERROR:
			case GATEWAY_ERROR:
			case TIMEOUT_ERROR:
				if (ZBX_INTERFACE_AVAILABLE_FALSE != last_available)
				{
					zbx_deactivate_item_interface(&timespec, &items[i].interface, items[i].itemid,
							items[i].type, items[i].host.host, items[i].key_orig, &data,
							&data_alloc, &data_offset, config_unavailable_delay,
							config_unreachable_period, config_unreachable_delay,
							results[i].msg);
					last_available = ZBX_INTERFACE_AVAILABLE_FALSE;
				}
				break;
			case CONFIG_ERROR:
				/* nothing to do */
				break;
			case SIG_ERROR:
				/* nothing to do, execution was forcibly interrupted by signal */
				break;
			default:
				zbx_error("unknown response code returned: %d", errcodes[i]);
				THIS_SHOULD_NEVER_HAPPEN;
		}

		if (SUCCEED == errcodes[i])
		{
			if (0 == add_results.values_num)
			{
				items[i].state = ITEM_STATE_NORMAL;
				zbx_preprocess_item_value(items[i].itemid, items[i].host.hostid, items[i].value_type,
						items[i].flags, &results[i], &timespec, items[i].state, NULL);
			}
			else
			{
				/* vmware.eventlog item returns vector of AGENT_RESULT representing events */

				zbx_timespec_t	ts_tmp = timespec;

				for (int j = 0; j < add_results.values_num; j++)
				{
					AGENT_RESULT	*add_result = (AGENT_RESULT *)add_results.values[j];

					if (ZBX_ISSET_MSG(add_result))
					{
						items[i].state = ITEM_STATE_NOTSUPPORTED;
						zbx_preprocess_item_value(items[i].itemid, items[i].host.hostid,
						items[i].value_type, items[i].flags, NULL, &ts_tmp, items[i].state,
								add_result->msg);
					}
					else
					{
						items[i].state = ITEM_STATE_NORMAL;
						zbx_preprocess_item_value(items[i].itemid, items[i].host.hostid,
								items[i].value_type, items[i].flags, add_result,
								&ts_tmp, items[i].state, NULL);
					}

					/* ensure that every log item value timestamp is unique */
					if (++ts_tmp.ns == 1000000000)
					{
						ts_tmp.sec++;
						ts_tmp.ns = 0;
					}
				}
			}
		}
		else if (NOTSUPPORTED == errcodes[i] || AGENT_ERROR == errcodes[i] || CONFIG_ERROR == errcodes[i])
		{
			items[i].state = ITEM_STATE_NOTSUPPORTED;
			zbx_preprocess_item_value(items[i].itemid, items[i].host.hostid, items[i].value_type,
					items[i].flags, NULL, &timespec, items[i].state, results[i].msg);
		}

		zbx_dc_poller_requeue_items(&items[i].itemid, &timespec.sec, &errcodes[i], 1, poller_type,
				nextcheck);
	}

	zbx_preprocessor_flush();
	zbx_clean_items(items, num, results);
	zbx_dc_config_clean_items(items, NULL, (size_t)num);
	zbx_vector_agent_result_ptr_clear_ext(&add_results, zbx_free_agent_result_ptr);
	zbx_vector_agent_result_ptr_destroy(&add_results);

	if (NULL != data)
	{
		zbx_availability_send(ZBX_IPC_AVAILABILITY_REQUEST, data, (zbx_uint32_t)data_offset, NULL);
		zbx_free(data);
	}

	if (items != &item)
		zbx_free(items);
exit:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d", __func__, num);

	return num;
}

ZBX_THREAD_ENTRY(zbx_poller_thread, args)
{
	zbx_thread_poller_args	*poller_args_in = (zbx_thread_poller_args *)(((zbx_thread_args_t *)args)->args);

	int			nextcheck, sleeptime = -1, processed = 0, old_processed = 0,
				server_num = ((zbx_thread_args_t *)args)->info.server_num,
				process_num = ((zbx_thread_args_t *)args)->info.process_num;
	double			sec, total_sec = 0.0, old_total_sec = 0.0;
	time_t			last_stat_time;
	unsigned char		poller_type;
	zbx_ipc_async_socket_t	rtc;
	const zbx_thread_info_t	*info = &((zbx_thread_args_t *)args)->info;
	unsigned char		process_type = ((zbx_thread_args_t *)args)->info.process_type;
	zbx_uint32_t		rtc_msgs[] = {ZBX_RTC_SNMP_CACHE_RELOAD};
#ifdef HAVE_NETSNMP
	time_t			last_snmp_engineid_hk_time = 0;
#endif

#define	STAT_INTERVAL	5	/* if a process is busy and does not sleep then update status not faster than */
				/* once in STAT_INTERVAL seconds */

	poller_type = (poller_args_in->poller_type);

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(info->program_type),
			server_num, get_process_type_string(process_type), process_num);

	zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_BUSY);

	scriptitem_es_engine_init();

	zbx_get_program_type_cb = poller_args_in->zbx_get_program_type_cb_arg;
	zbx_get_progname_cb = poller_args_in->zbx_get_progname_cb_arg;
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	zbx_tls_init_child(poller_args_in->config_comms->config_tls,
			poller_args_in->zbx_get_program_type_cb_arg, zbx_dc_get_psk_by_identity);
#endif
	if (ZBX_POLLER_TYPE_HISTORY == poller_type)
	{
		zbx_setproctitle("%s #%d [connecting to the database]", get_process_type_string(process_type),
				process_num);

		zbx_db_connect(ZBX_DB_CONNECT_NORMAL);
	}
	zbx_setproctitle("%s #%d started", get_process_type_string(process_type), process_num);
	last_stat_time = time(NULL);

	zbx_rtc_subscribe(process_type, process_num, rtc_msgs, ARRSIZE(rtc_msgs),
			poller_args_in->config_comms->config_timeout, &rtc);

	while (ZBX_IS_RUNNING())
	{
		zbx_uint32_t	rtc_cmd;
		unsigned char	*rtc_data;

		sec = zbx_time();
		zbx_update_env(get_process_type_string(process_type), sec);

		if (0 != sleeptime)
		{
			zbx_setproctitle("%s #%d [got %d values in " ZBX_FS_DBL " sec, getting values]",
					get_process_type_string(process_type), process_num, old_processed,
					old_total_sec);
		}

		if (ZBX_POLLER_TYPE_INTERNAL == poller_type || FAIL == zbx_vps_monitor_capped())
		{
			processed += get_values(poller_type, &nextcheck, poller_args_in->config_comms,
					poller_args_in->config_startup_time, poller_args_in->config_unavailable_delay,
					poller_args_in->config_unreachable_period,
					poller_args_in->config_unreachable_delay, info->program_type,
					poller_args_in->zbx_get_progname_cb_arg(), poller_args_in->get_config_forks,
					poller_args_in->config_java_gateway, poller_args_in->config_java_gateway_port,
					poller_args_in->config_externalscripts,
					poller_args_in->zbx_get_value_internal_ext_cb,
					poller_args_in->config_ssh_key_location, poller_args_in->config_webdriver_url);

			sleeptime = zbx_calculate_sleeptime(nextcheck, POLLER_DELAY);
		}
		else
			sleeptime = POLLER_DELAY;

		total_sec += zbx_time() - sec;

		if (0 != sleeptime || STAT_INTERVAL <= time(NULL) - last_stat_time)
		{
			if (0 == sleeptime)
			{
				zbx_setproctitle("%s #%d [got %d values in " ZBX_FS_DBL " sec, getting values]",
					get_process_type_string(process_type), process_num, processed, total_sec);
			}
			else
			{
				const char	*ext;

				ext = (ZBX_POLLER_TYPE_INTERNAL == poller_type ? "" : zbx_vps_monitor_status());

				zbx_setproctitle("%s #%d [got %d values in " ZBX_FS_DBL " sec, idle %d sec%s]",
					get_process_type_string(process_type), process_num, processed, total_sec,
					sleeptime, ext);
				old_processed = processed;
				old_total_sec = total_sec;
			}
			processed = 0;
			total_sec = 0.0;
			last_stat_time = time(NULL);
		}

		if (SUCCEED == zbx_rtc_wait(&rtc, info, &rtc_cmd, &rtc_data, sleeptime) && 0 != rtc_cmd)
		{
#ifdef HAVE_NETSNMP
			if (ZBX_RTC_SNMP_CACHE_RELOAD == rtc_cmd)
			{
				if (ZBX_POLLER_TYPE_NORMAL == poller_type || ZBX_POLLER_TYPE_UNREACHABLE == poller_type)
					zbx_clear_cache_snmp(process_type, process_num);
			}
#endif
			if (ZBX_RTC_SHUTDOWN == rtc_cmd)
				break;
		}
#ifdef HAVE_NETSNMP
#define	SNMP_ENGINEID_HK_INTERVAL	86400
		if ((ZBX_POLLER_TYPE_NORMAL == poller_type || ZBX_POLLER_TYPE_UNREACHABLE == poller_type) &&
				time(NULL) >= SNMP_ENGINEID_HK_INTERVAL + last_snmp_engineid_hk_time)
		{
			last_snmp_engineid_hk_time = time(NULL);
			zbx_clear_cache_snmp(process_type, process_num);
		}
#undef SNMP_ENGINEID_HK_INTERVAL
#endif
	}

	scriptitem_es_engine_destroy();

	zbx_setproctitle("%s #%d [terminated]", get_process_type_string(process_type), process_num);

	while (1)
		zbx_sleep(SEC_PER_MIN);
#undef STAT_INTERVAL
}
