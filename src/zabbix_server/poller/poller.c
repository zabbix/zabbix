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

#include "poller.h"

#include "zbxnix.h"
#include "zbxserver.h"
#include "zbxself.h"
#include "preproc.h"
#include "zbxrtc.h"

#include "checks_agent.h"
#include "checks_external.h"
#include "checks_internal.h"
#include "checks_script.h"
#include "checks_simple.h"
#include "checks_snmp.h"
#include "checks_db.h"
#include "checks_ssh.h"
#include "checks_telnet.h"
#include "checks_java.h"
#include "checks_calculated.h"
#include "checks_http.h"
#include "zbxcrypto.h"
#include "zbxjson.h"
#include "zbxhttp.h"
#include "log.h"
#include "zbxavailability.h"
#include "zbxcomms.h"

extern ZBX_THREAD_LOCAL unsigned char	process_type;
extern unsigned char			program_type;
extern ZBX_THREAD_LOCAL int		server_num, process_num;

/******************************************************************************
 *                                                                            *
 * Purpose: write interface availability changes into database                *
 *                                                                            *
 * Parameters: data        - [IN/OUT] the serialized availability data        *
 *             data_alloc  - [IN/OUT] the serialized availability data size   *
 *             data_alloc  - [IN/OUT] the serialized availability data offset *
 *             ia          - [IN] the interface availability data             *
 *                                                                            *
 * Return value: SUCCEED - the availability changes were written into db      *
 *               FAIL    - no changes in availability data were detected      *
 *                                                                            *
 ******************************************************************************/
static int	update_interface_availability(unsigned char **data, size_t *data_alloc, size_t *data_offset,
		const zbx_interface_availability_t *ia)
{
	if (FAIL == zbx_interface_availability_is_set(ia))
		return FAIL;

	zbx_availability_serialize_interface(data, data_alloc, data_offset, ia);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get interface availability data                                   *
 *                                                                            *
 * Parameters: dc_interface - [IN] the interface                              *
 *             ia           - [OUT] the interface availability data           *
 *                                                                            *
 ******************************************************************************/
static void	interface_get_availability(const DC_INTERFACE *dc_interface, zbx_interface_availability_t *ia)
{
	zbx_agent_availability_t	*availability = &ia->agent;

	availability->flags = ZBX_FLAGS_AGENT_STATUS;

	availability->available = dc_interface->available;
	availability->error = zbx_strdup(NULL, dc_interface->error);
	availability->errors_from = dc_interface->errors_from;
	availability->disable_until = dc_interface->disable_until;

	ia->interfaceid = dc_interface->interfaceid;
}

/********************************************************************************
 *                                                                              *
 * Purpose: sets interface availability data                                    *
 *                                                                              *
 * Parameters: dc_interface - [IN/OUT] the interface                            *
 *             ia           - [IN] the interface availability data              *
 *                                                                              *
 *******************************************************************************/
static void	interface_set_availability(DC_INTERFACE *dc_interface, const zbx_interface_availability_t *ia)
{
	const zbx_agent_availability_t	*availability = &ia->agent;
	unsigned char			*pavailable;
	int				*perrors_from, *pdisable_until;
	char				*perror;

	pavailable = &dc_interface->available;
	perror = dc_interface->error;
	perrors_from = &dc_interface->errors_from;
	pdisable_until = &dc_interface->disable_until;

	if (0 != (availability->flags & ZBX_FLAGS_AGENT_STATUS_AVAILABLE))
		*pavailable = availability->available;

	if (0 != (availability->flags & ZBX_FLAGS_AGENT_STATUS_ERROR))
		zbx_strlcpy(perror, availability->error, ZBX_INTERFACE_ERROR_LEN_MAX);

	if (0 != (availability->flags & ZBX_FLAGS_AGENT_STATUS_ERRORS_FROM))
		*perrors_from = availability->errors_from;

	if (0 != (availability->flags & ZBX_FLAGS_AGENT_STATUS_DISABLE_UNTIL))
		*pdisable_until = availability->disable_until;
}

static int	interface_availability_by_item_type(unsigned char item_type, unsigned char interface_type)
{
	if ((ITEM_TYPE_ZABBIX == item_type && INTERFACE_TYPE_AGENT == interface_type) ||
			(ITEM_TYPE_SNMP == item_type && INTERFACE_TYPE_SNMP == interface_type) ||
			(ITEM_TYPE_JMX == item_type && INTERFACE_TYPE_JMX == interface_type) ||
			(ITEM_TYPE_IPMI == item_type && INTERFACE_TYPE_IPMI == interface_type))
		return SUCCEED;

	return FAIL;
}

/********************************************************************************
 *                                                                              *
 * Purpose: activate item interface                                             *
 *                                                                              *
 * Parameters: ts         - [IN] the timestamp                                  *
 *             item       - [IN/OUT] the item                                   *
 *             data       - [IN/OUT] the serialized availability data           *
 *             data_alloc - [IN/OUT] the serialized availability data size      *
 *             data_alloc - [IN/OUT] the serialized availability data offset    *
 *             ts         - [IN] the timestamp                                  *
 *                                                                              *
 *******************************************************************************/
void	zbx_activate_item_interface(zbx_timespec_t *ts, DC_ITEM *item,  unsigned char **data, size_t *data_alloc,
		size_t *data_offset)
{
	zbx_interface_availability_t	in, out;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() interfaceid:" ZBX_FS_UI64 " itemid:" ZBX_FS_UI64 " type:%d",
			__func__, item->interface.interfaceid, item->itemid, (int)item->type);

	zbx_interface_availability_init(&in, item->interface.interfaceid);
	zbx_interface_availability_init(&out, item->interface.interfaceid);

	if (FAIL == interface_availability_by_item_type(item->type, item->interface.type))
		goto out;

	interface_get_availability(&item->interface, &in);

	if (FAIL == DCinterface_activate(item->interface.interfaceid, ts, &in.agent, &out.agent))
		goto out;

	if (FAIL == update_interface_availability(data, data_alloc, data_offset, &out))
		goto out;

	interface_set_availability(&item->interface, &out);

	if (INTERFACE_AVAILABLE_TRUE == in.agent.available)
	{
		zabbix_log(LOG_LEVEL_WARNING, "resuming %s checks on host \"%s\": connection restored",
				zbx_agent_type_string(item->type), item->host.host);
	}
	else
	{
		zabbix_log(LOG_LEVEL_WARNING, "enabling %s checks on host \"%s\": interface became available",
				zbx_agent_type_string(item->type), item->host.host);
	}
out:
	zbx_interface_availability_clean(&out);
	zbx_interface_availability_clean(&in);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/********************************************************************************
 *                                                                              *
 * Purpose: deactivate item interface                                           *
 *                                                                              *
 * Parameters: ts         - [IN] the timestamp                                  *
 *             item       - [IN/OUT] the item                                   *
 *             data       - [IN/OUT] the serialized availability data           *
 *             data_alloc - [IN/OUT] the serialized availability data size      *
 *             data_alloc - [IN/OUT] the serialized availability data offset    *
 *             ts         - [IN] the timestamp                                  *
 *                                                                              *
 *******************************************************************************/
void	zbx_deactivate_item_interface(zbx_timespec_t *ts, DC_ITEM *item, unsigned char **data, size_t *data_alloc,
		size_t *data_offset, const char *error)
{
	zbx_interface_availability_t	in, out;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() interfaceid:" ZBX_FS_UI64 " itemid:" ZBX_FS_UI64 " type:%d",
			__func__, item->interface.interfaceid, item->itemid, (int)item->type);

	zbx_interface_availability_init(&in, item->interface.interfaceid);
	zbx_interface_availability_init(&out,item->interface.interfaceid);

	if (FAIL == interface_availability_by_item_type(item->type, item->interface.type))
		goto out;

	interface_get_availability(&item->interface, &in);

	if (FAIL == DCinterface_deactivate(item->interface.interfaceid, ts, &in.agent, &out.agent, error))
		goto out;

	if (FAIL == update_interface_availability(data, data_alloc, data_offset, &out))
		goto out;

	interface_set_availability(&item->interface, &out);

	if (0 == in.agent.errors_from)
	{
		zabbix_log(LOG_LEVEL_WARNING, "%s item \"%s\" on host \"%s\" failed:"
				" first network error, wait for %d seconds",
				zbx_agent_type_string(item->type), item->key_orig, item->host.host,
				out.agent.disable_until - ts->sec);
	}
	else if (INTERFACE_AVAILABLE_FALSE != in.agent.available)
	{
		if (INTERFACE_AVAILABLE_FALSE != out.agent.available)
		{
			zabbix_log(LOG_LEVEL_WARNING, "%s item \"%s\" on host \"%s\" failed:"
					" another network error, wait for %d seconds",
					zbx_agent_type_string(item->type), item->key_orig, item->host.host,
					out.agent.disable_until - ts->sec);
		}
		else
		{
			zabbix_log(LOG_LEVEL_WARNING, "temporarily disabling %s checks on host \"%s\":"
					" interface unavailable",
					zbx_agent_type_string(item->type), item->host.host);
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "%s() errors_from:%d available:%d", __func__,
			out.agent.errors_from, out.agent.available);
out:
	zbx_interface_availability_clean(&out);
	zbx_interface_availability_clean(&in);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

void	zbx_free_result_ptr(AGENT_RESULT *result)
{
	free_result(result);
	zbx_free(result);
}

static int	get_value(DC_ITEM *item, AGENT_RESULT *result, zbx_vector_ptr_t *add_results)
{
	int	res = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() key:'%s'", __func__, item->key_orig);

	switch (item->type)
	{
		case ITEM_TYPE_ZABBIX:
			zbx_alarm_on(CONFIG_TIMEOUT);
			res = get_value_agent(item, result);
			zbx_alarm_off();
			break;
		case ITEM_TYPE_SIMPLE:
			/* simple checks use their own timeouts */
			res = get_value_simple(item, result, add_results);
			break;
		case ITEM_TYPE_INTERNAL:
			res = get_value_internal(item, result);
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
			res = get_value_external(item, result);
			break;
		case ITEM_TYPE_SSH:
#if defined(HAVE_SSH2) || defined(HAVE_SSH)
			zbx_alarm_on(CONFIG_TIMEOUT);
			res = get_value_ssh(item, result);
			zbx_alarm_off();
#else
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Support for SSH checks was not compiled in."));
			res = CONFIG_ERROR;
#endif
			break;
		case ITEM_TYPE_TELNET:
			zbx_alarm_on(CONFIG_TIMEOUT);
			res = get_value_telnet(item, result);
			zbx_alarm_off();
			break;
		case ITEM_TYPE_CALCULATED:
			res = get_value_calculated(item, result);
			break;
		case ITEM_TYPE_HTTPAGENT:
#ifdef HAVE_LIBCURL
			res = get_value_http(item, result);
#else
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Support for HTTP agent checks was not compiled in."));
			res = CONFIG_ERROR;
#endif
			break;
		case ITEM_TYPE_SCRIPT:
			res = get_value_script(item, result);
			break;
		default:
			SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Not supported item type:%d", item->type));
			res = CONFIG_ERROR;
	}

	if (SUCCEED != res)
	{
		if (!ISSET_MSG(result))
			SET_MSG_RESULT(result, zbx_strdup(NULL, ZBX_NOTSUPPORTED_MSG));

		zabbix_log(LOG_LEVEL_DEBUG, "Item [%s:%s] error: %s", item->host.host, item->key_orig, result->msg);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(res));

	return res;
}

static int	parse_query_fields(const DC_ITEM *item, char **query_fields, unsigned char expand_macros)
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
		if (MACRO_EXPAND_YES == expand_macros)
		{
			zbx_substitute_simple_macros(NULL, NULL, NULL,NULL, NULL, &item->host, item, NULL, NULL, NULL, NULL,
					NULL, &data, MACRO_TYPE_HTTP_RAW, NULL, 0);
		}
		zbx_http_url_encode(data, &data);
		zbx_strcpy_alloc(&str, &alloc_len, &offset, data);
		zbx_chrcpy_alloc(&str, &alloc_len, &offset, '=');

		data = zbx_strdup(data, value);
		if (MACRO_EXPAND_YES == expand_macros)
		{
			zbx_substitute_simple_macros_unmasked(NULL, NULL, NULL,NULL, NULL, &item->host, item, NULL, NULL,
					NULL, NULL, NULL, &data, MACRO_TYPE_HTTP_RAW, NULL, 0);
		}

		zbx_http_url_encode(data, &data);
		zbx_strcpy_alloc(&str, &alloc_len, &offset, data);

		free(data);
	}
	while (NULL != (element = zbx_json_next(&jp_array, element)));

	zbx_free(*query_fields);
	*query_fields = str;

	return SUCCEED;
}

void	zbx_prepare_items(DC_ITEM *items, int *errcodes, int num, AGENT_RESULT *results, unsigned char expand_macros)
{
	int			i;
	char			*port = NULL, error[ZBX_ITEM_ERROR_LEN_MAX];
	zbx_dc_um_handle_t	*um_handle;

	if (MACRO_EXPAND_YES == expand_macros)
		um_handle = zbx_dc_open_user_macros();

	for (i = 0; i < num; i++)
	{
		init_result(&results[i]);
		errcodes[i] = SUCCEED;

		if (MACRO_EXPAND_YES == expand_macros)
		{
			ZBX_STRDUP(items[i].key, items[i].key_orig);
			if (SUCCEED != zbx_substitute_key_macros_unmasked(&items[i].key, NULL, &items[i], NULL, NULL,
					MACRO_TYPE_ITEM_KEY, error, sizeof(error)))
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
				ZBX_STRDUP(port, items[i].interface.port_orig);
				if (MACRO_EXPAND_YES == expand_macros)
				{
					zbx_substitute_simple_macros(NULL, NULL, NULL, NULL, &items[i].host.hostid, NULL,
							NULL, NULL, NULL, NULL, NULL, NULL, &port, MACRO_TYPE_COMMON,
							NULL, 0);
				}

				if (FAIL == is_ushort(port, &items[i].interface.port))
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
			case ITEM_TYPE_SNMP:
				if (MACRO_EXPAND_NO == expand_macros)
					break;

				if (ZBX_IF_SNMP_VERSION_3 == items[i].snmp_version)
				{
					ZBX_STRDUP(items[i].snmpv3_securityname, items[i].snmpv3_securityname_orig);
					ZBX_STRDUP(items[i].snmpv3_authpassphrase, items[i].snmpv3_authpassphrase_orig);
					ZBX_STRDUP(items[i].snmpv3_privpassphrase, items[i].snmpv3_privpassphrase_orig);
					ZBX_STRDUP(items[i].snmpv3_contextname, items[i].snmpv3_contextname_orig);

					zbx_substitute_simple_macros_unmasked(NULL, NULL, NULL, NULL, &items[i].host.hostid,
							NULL, NULL, NULL, NULL, NULL, NULL, NULL,
							&items[i].snmpv3_securityname, MACRO_TYPE_COMMON, NULL,
							0);
					zbx_substitute_simple_macros_unmasked(NULL, NULL, NULL, NULL, &items[i].host.hostid,
							NULL, NULL, NULL, NULL, NULL, NULL, NULL,
							&items[i].snmpv3_authpassphrase, MACRO_TYPE_COMMON,
							NULL, 0);
					zbx_substitute_simple_macros_unmasked(NULL, NULL, NULL, NULL, &items[i].host.hostid,
							NULL, NULL, NULL, NULL, NULL, NULL, NULL,
							&items[i].snmpv3_privpassphrase, MACRO_TYPE_COMMON,
							NULL, 0);
					zbx_substitute_simple_macros_unmasked(NULL, NULL, NULL, NULL, &items[i].host.hostid,
							NULL, NULL, NULL, NULL, NULL, NULL, NULL,
							&items[i].snmpv3_contextname, MACRO_TYPE_COMMON, NULL,
							0);
				}

				ZBX_STRDUP(items[i].snmp_community, items[i].snmp_community_orig);
				ZBX_STRDUP(items[i].snmp_oid, items[i].snmp_oid_orig);

				zbx_substitute_simple_macros_unmasked(NULL, NULL, NULL, NULL, &items[i].host.hostid, NULL,
						NULL, NULL, NULL, NULL, NULL, NULL, &items[i].snmp_community,
						MACRO_TYPE_COMMON, NULL, 0);
				if (SUCCEED != zbx_substitute_key_macros(&items[i].snmp_oid, &items[i].host.hostid,
						NULL, NULL, NULL, MACRO_TYPE_SNMP_OID, error, sizeof(error)))
				{
					SET_MSG_RESULT(&results[i], zbx_strdup(NULL, error));
					errcodes[i] = CONFIG_ERROR;
					continue;
				}
				break;
			case ITEM_TYPE_SCRIPT:
				if (MACRO_EXPAND_NO == expand_macros)
					break;

				ZBX_STRDUP(items[i].timeout, items[i].timeout_orig);

				zbx_substitute_simple_macros(NULL, NULL, NULL, NULL, &items[i].host.hostid, NULL, NULL,
						NULL, NULL, NULL, NULL, NULL, &items[i].timeout, MACRO_TYPE_COMMON,
						NULL, 0);
				zbx_substitute_simple_macros_unmasked(NULL, NULL, NULL, NULL, NULL, NULL, &items[i], NULL,
						NULL, NULL, NULL, NULL, &items[i].script_params,
						MACRO_TYPE_SCRIPT_PARAMS_FIELD, NULL, 0);
				zbx_substitute_simple_macros_unmasked(NULL, NULL, NULL, NULL, &items[i].host.hostid, NULL,
						NULL, NULL, NULL, NULL, NULL, NULL, &items[i].params, MACRO_TYPE_COMMON,
						NULL, 0);
				break;
			case ITEM_TYPE_SSH:
				if (MACRO_EXPAND_NO == expand_macros)
					break;

				ZBX_STRDUP(items[i].publickey, items[i].publickey_orig);
				ZBX_STRDUP(items[i].privatekey, items[i].privatekey_orig);

				zbx_substitute_simple_macros(NULL, NULL, NULL, NULL, &items[i].host.hostid, NULL, NULL,
						NULL, NULL, NULL, NULL, NULL, &items[i].publickey, MACRO_TYPE_COMMON,
						NULL, 0);
				zbx_substitute_simple_macros(NULL, NULL, NULL, NULL, &items[i].host.hostid, NULL, NULL,
						NULL, NULL, NULL, NULL, NULL, &items[i].privatekey, MACRO_TYPE_COMMON, NULL, 0);
				ZBX_FALLTHROUGH;
			case ITEM_TYPE_TELNET:
			case ITEM_TYPE_DB_MONITOR:
				if (MACRO_EXPAND_NO == expand_macros)
					break;

				zbx_substitute_simple_macros_unmasked(NULL, NULL, NULL, NULL, NULL, NULL, &items[i], NULL,
						NULL, NULL, NULL, NULL, &items[i].params, MACRO_TYPE_PARAMS_FIELD,
						NULL, 0);
				ZBX_FALLTHROUGH;
			case ITEM_TYPE_SIMPLE:
				if (MACRO_EXPAND_NO == expand_macros)
					break;

				items[i].username = zbx_strdup(items[i].username, items[i].username_orig);
				items[i].password = zbx_strdup(items[i].password, items[i].password_orig);

				zbx_substitute_simple_macros_unmasked(NULL, NULL, NULL, NULL, &items[i].host.hostid, NULL,
						NULL, NULL, NULL, NULL, NULL, NULL, &items[i].username,
						MACRO_TYPE_COMMON, NULL, 0);
				zbx_substitute_simple_macros_unmasked(NULL, NULL, NULL, NULL, &items[i].host.hostid, NULL,
						NULL, NULL, NULL, NULL, NULL, NULL, &items[i].password,
						MACRO_TYPE_COMMON, NULL, 0);
				break;
			case ITEM_TYPE_JMX:
				if (MACRO_EXPAND_NO == expand_macros)
					break;

				items[i].username = zbx_strdup(items[i].username, items[i].username_orig);
				items[i].password = zbx_strdup(items[i].password, items[i].password_orig);
				items[i].jmx_endpoint = zbx_strdup(items[i].jmx_endpoint, items[i].jmx_endpoint_orig);

				zbx_substitute_simple_macros_unmasked(NULL, NULL, NULL, NULL, &items[i].host.hostid, NULL,
						NULL, NULL, NULL, NULL, NULL, NULL, &items[i].username,
						MACRO_TYPE_COMMON, NULL, 0);
				zbx_substitute_simple_macros_unmasked(NULL, NULL, NULL, NULL, &items[i].host.hostid, NULL,
						NULL, NULL, NULL, NULL, NULL, NULL, &items[i].password,
						MACRO_TYPE_COMMON, NULL, 0);
				zbx_substitute_simple_macros(NULL, NULL, NULL, NULL, NULL, NULL, &items[i],
						NULL, NULL, NULL, NULL, NULL, &items[i].jmx_endpoint, MACRO_TYPE_JMX_ENDPOINT,
						NULL, 0);
				break;
			case ITEM_TYPE_HTTPAGENT:
				if (MACRO_EXPAND_YES == expand_macros)
				{
					ZBX_STRDUP(items[i].timeout, items[i].timeout_orig);
					ZBX_STRDUP(items[i].url, items[i].url_orig);
					ZBX_STRDUP(items[i].status_codes, items[i].status_codes_orig);
					ZBX_STRDUP(items[i].http_proxy, items[i].http_proxy_orig);
					ZBX_STRDUP(items[i].ssl_cert_file, items[i].ssl_cert_file_orig);
					ZBX_STRDUP(items[i].ssl_key_file, items[i].ssl_key_file_orig);
					ZBX_STRDUP(items[i].ssl_key_password, items[i].ssl_key_password_orig);
					ZBX_STRDUP(items[i].username, items[i].username_orig);
					ZBX_STRDUP(items[i].password, items[i].password_orig);
					ZBX_STRDUP(items[i].query_fields, items[i].query_fields_orig);

					zbx_substitute_simple_macros(NULL, NULL, NULL, NULL, &items[i].host.hostid, NULL,
							NULL, NULL, NULL, NULL, NULL, NULL, &items[i].timeout,
							MACRO_TYPE_COMMON, NULL, 0);
					zbx_substitute_simple_macros_unmasked(NULL, NULL, NULL, NULL, NULL, &items[i].host,
							&items[i], NULL, NULL, NULL, NULL, NULL, &items[i].url,
							MACRO_TYPE_HTTP_RAW, NULL, 0);
				}

				if (SUCCEED != zbx_http_punycode_encode_url(&items[i].url))
				{
					SET_MSG_RESULT(&results[i], zbx_strdup(NULL, "Cannot encode URL into punycode"));
					errcodes[i] = CONFIG_ERROR;
					continue;
				}

				if (FAIL == parse_query_fields(&items[i], &items[i].query_fields, expand_macros))
				{
					SET_MSG_RESULT(&results[i], zbx_strdup(NULL, "Invalid query fields"));
					errcodes[i] = CONFIG_ERROR;
					continue;
				}

				if (MACRO_EXPAND_NO == expand_macros)
					break;

				switch (items[i].post_type)
				{
					case ZBX_POSTTYPE_XML:
						if (SUCCEED != zbx_substitute_macros_xml_unmasked(&items[i].posts, &items[i],
								NULL, NULL, error, sizeof(error)))
						{
							SET_MSG_RESULT(&results[i], zbx_dsprintf(NULL, "%s.", error));
							errcodes[i] = CONFIG_ERROR;
							continue;
						}
						break;
					case ZBX_POSTTYPE_JSON:
						zbx_substitute_simple_macros_unmasked(NULL, NULL, NULL,NULL, NULL,
								&items[i].host, &items[i], NULL, NULL, NULL, NULL, NULL,
								&items[i].posts, MACRO_TYPE_HTTP_JSON, NULL, 0);
						break;
					default:
						zbx_substitute_simple_macros_unmasked(NULL, NULL, NULL,NULL, NULL,
								&items[i].host, &items[i], NULL, NULL, NULL, NULL, NULL,
								&items[i].posts, MACRO_TYPE_HTTP_RAW, NULL, 0);
						break;
				}

				zbx_substitute_simple_macros_unmasked(NULL, NULL, NULL,NULL, NULL, &items[i].host,
						&items[i], NULL, NULL, NULL, NULL, NULL, &items[i].headers,
						MACRO_TYPE_HTTP_RAW, NULL, 0);
				zbx_substitute_simple_macros(NULL, NULL, NULL, NULL, &items[i].host.hostid, NULL,
						NULL, NULL, NULL, NULL, NULL, NULL, &items[i].status_codes,
						MACRO_TYPE_COMMON, NULL, 0);
				zbx_substitute_simple_macros(NULL, NULL, NULL, NULL, &items[i].host.hostid, NULL,
						NULL, NULL, NULL, NULL, NULL, NULL, &items[i].http_proxy,
						MACRO_TYPE_COMMON, NULL, 0);
				zbx_substitute_simple_macros(NULL, NULL, NULL,NULL, NULL, &items[i].host, &items[i], NULL,
						NULL, NULL, NULL, NULL, &items[i].ssl_cert_file, MACRO_TYPE_HTTP_RAW,
						NULL, 0);
				zbx_substitute_simple_macros(NULL, NULL, NULL,NULL, NULL, &items[i].host, &items[i], NULL,
						NULL, NULL, NULL, NULL, &items[i].ssl_key_file, MACRO_TYPE_HTTP_RAW,
						NULL, 0);
				zbx_substitute_simple_macros_unmasked(NULL, NULL, NULL, NULL, &items[i].host.hostid, NULL,
						NULL, NULL, NULL, NULL, NULL, NULL, &items[i].ssl_key_password,
						MACRO_TYPE_COMMON, NULL, 0);
				zbx_substitute_simple_macros_unmasked(NULL, NULL, NULL, NULL, &items[i].host.hostid, NULL,
						NULL, NULL, NULL, NULL, NULL, NULL, &items[i].username,
						MACRO_TYPE_COMMON, NULL, 0);
				zbx_substitute_simple_macros_unmasked(NULL, NULL, NULL, NULL, &items[i].host.hostid, NULL,
						NULL, NULL, NULL, NULL, NULL, NULL, &items[i].password,
						MACRO_TYPE_COMMON, NULL, 0);
				break;
		}
	}

	zbx_free(port);

	if (MACRO_EXPAND_YES == expand_macros)
		zbx_dc_close_user_macros(um_handle);

}

void	zbx_check_items(DC_ITEM *items, int *errcodes, int num, AGENT_RESULT *results, zbx_vector_ptr_t *add_results,
		unsigned char poller_type)
{
	if (ITEM_TYPE_SNMP == items[0].type)
	{
#ifndef HAVE_NETSNMP
		int	i;

		ZBX_UNUSED(poller_type);

		for (i = 0; i < num; i++)
		{
			if (SUCCEED != errcodes[i])
				continue;

			SET_MSG_RESULT(&results[i], zbx_strdup(NULL, "Support for SNMP checks was not compiled in."));
			errcodes[i] = CONFIG_ERROR;
		}
#else
		/* SNMP checks use their own timeouts */
		get_values_snmp(items, results, errcodes, num, poller_type);
#endif
	}
	else if (ITEM_TYPE_JMX == items[0].type)
	{
		zbx_alarm_on(CONFIG_TIMEOUT);
		get_values_java(ZBX_JAVA_GATEWAY_REQUEST_JMX, items, results, errcodes, num);
		zbx_alarm_off();
	}
	else if (1 == num)
	{
		if (SUCCEED == errcodes[0])
			errcodes[0] = get_value(&items[0], &results[0], add_results);
	}
	else
		THIS_SHOULD_NEVER_HAPPEN;
}

void	zbx_clean_items(DC_ITEM *items, int num, AGENT_RESULT *results)
{
	int	i;

	for (i = 0; i < num; i++)
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
				zbx_free(items[i].timeout);
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
			case ITEM_TYPE_SCRIPT:
				zbx_free(items[i].timeout);
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

		free_result(&results[i]);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieve values of metrics from monitored hosts                   *
 *                                                                            *
 * Parameters: poller_type - [IN] poller type (ZBX_POLLER_TYPE_...)           *
 *             nextcheck   - [OUT] item nextcheck                             *
 *                                                                            *
 * Return value: number of items processed                                    *
 *                                                                            *
 * Comments: processes single item at a time except for Java, SNMP items,     *
 *           see DCconfig_get_poller_items()                                  *
 *                                                                            *
 ******************************************************************************/
static int	get_values(unsigned char poller_type, int *nextcheck)
{
	DC_ITEM			item, *items;
	AGENT_RESULT		results[MAX_POLLER_ITEMS];
	int			errcodes[MAX_POLLER_ITEMS];
	zbx_timespec_t		timespec;
	int			i, num, last_available = INTERFACE_AVAILABLE_UNKNOWN;
	zbx_vector_ptr_t	add_results;
	unsigned char		*data = NULL;
	size_t			data_alloc = 0, data_offset = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	items = &item;
	num = DCconfig_get_poller_items(poller_type, &items);

	if (0 == num)
	{
		*nextcheck = DCconfig_get_poller_nextcheck(poller_type);
		goto exit;
	}

	zbx_vector_ptr_create(&add_results);

	zbx_prepare_items(items, errcodes, num, results, MACRO_EXPAND_YES);
	zbx_check_items(items, errcodes, num, results, &add_results, poller_type);

	zbx_timespec(&timespec);

	/* process item values */
	for (i = 0; i < num; i++)
	{
		switch (errcodes[i])
		{
			case SUCCEED:
			case NOTSUPPORTED:
			case AGENT_ERROR:
				if (INTERFACE_AVAILABLE_TRUE != last_available)
				{
					zbx_activate_item_interface(&timespec, &items[i], &data, &data_alloc,
							&data_offset);
					last_available = INTERFACE_AVAILABLE_TRUE;
				}
				break;
			case NETWORK_ERROR:
			case GATEWAY_ERROR:
			case TIMEOUT_ERROR:
				if (INTERFACE_AVAILABLE_FALSE != last_available)
				{
					zbx_deactivate_item_interface(&timespec, &items[i], &data, &data_alloc,
							&data_offset, results[i].msg);
					last_available = INTERFACE_AVAILABLE_FALSE;
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

				int		j;
				zbx_timespec_t	ts_tmp = timespec;

				for (j = 0; j < add_results.values_num; j++)
				{
					AGENT_RESULT	*add_result = (AGENT_RESULT *)add_results.values[j];

					if (ISSET_MSG(add_result))
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

		DCpoller_requeue_items(&items[i].itemid, &timespec.sec, &errcodes[i], 1, poller_type,
				nextcheck);
	}

	zbx_preprocessor_flush();
	zbx_clean_items(items, num, results);
	DCconfig_clean_items(items, NULL, num);
	zbx_vector_ptr_clear_ext(&add_results, (zbx_mem_free_func_t)zbx_free_result_ptr);
	zbx_vector_ptr_destroy(&add_results);

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

ZBX_THREAD_ENTRY(poller_thread, args)
{
	int			nextcheck, sleeptime = -1, processed = 0, old_processed = 0;
	double			sec, total_sec = 0.0, old_total_sec = 0.0;
	time_t			last_stat_time;
	unsigned char		poller_type;
	zbx_ipc_async_socket_t	rtc;

#define	STAT_INTERVAL	5	/* if a process is busy and does not sleep then update status not faster than */
				/* once in STAT_INTERVAL seconds */

	poller_type = *(unsigned char *)((zbx_thread_args_t *)args)->args;
	process_type = ((zbx_thread_args_t *)args)->process_type;
	server_num = ((zbx_thread_args_t *)args)->server_num;
	process_num = ((zbx_thread_args_t *)args)->process_num;

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(program_type),
			server_num, get_process_type_string(process_type), process_num);

	update_selfmon_counter(ZBX_PROCESS_STATE_BUSY);

	scriptitem_es_engine_init();

#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	zbx_tls_init_child();
#endif
	if (ZBX_POLLER_TYPE_HISTORY == poller_type)
	{
		zbx_setproctitle("%s #%d [connecting to the database]", get_process_type_string(process_type), process_num);

		DBconnect(ZBX_DB_CONNECT_NORMAL);
	}
	zbx_setproctitle("%s #%d started", get_process_type_string(process_type), process_num);
	last_stat_time = time(NULL);

	zbx_rtc_subscribe(&rtc, process_type, process_num);

	while (ZBX_IS_RUNNING())
	{
		zbx_uint32_t	rtc_cmd;
		unsigned char	*rtc_data;

		sec = zbx_time();
		zbx_update_env(sec);

		if (0 != sleeptime)
		{
			zbx_setproctitle("%s #%d [got %d values in " ZBX_FS_DBL " sec, getting values]",
					get_process_type_string(process_type), process_num, old_processed,
					old_total_sec);
		}

		processed += get_values(poller_type, &nextcheck);
		total_sec += zbx_time() - sec;

		sleeptime = calculate_sleeptime(nextcheck, POLLER_DELAY);

		if (0 != sleeptime || STAT_INTERVAL <= time(NULL) - last_stat_time)
		{
			if (0 == sleeptime)
			{
				zbx_setproctitle("%s #%d [got %d values in " ZBX_FS_DBL " sec, getting values]",
					get_process_type_string(process_type), process_num, processed, total_sec);
			}
			else
			{
				zbx_setproctitle("%s #%d [got %d values in " ZBX_FS_DBL " sec, idle %d sec]",
					get_process_type_string(process_type), process_num, processed, total_sec,
					sleeptime);
				old_processed = processed;
				old_total_sec = total_sec;
			}
			processed = 0;
			total_sec = 0.0;
			last_stat_time = time(NULL);
		}

		if (SUCCEED == zbx_rtc_wait(&rtc, &rtc_cmd, &rtc_data, sleeptime) && 0 != rtc_cmd)
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
	}

	scriptitem_es_engine_destroy();

	zbx_setproctitle("%s #%d [terminated]", get_process_type_string(process_type), process_num);

	while (1)
		zbx_sleep(SEC_PER_MIN);
#undef STAT_INTERVAL
}
