/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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

#include "trapper_item_test.h"
#include "zbxexpression.h"

#include "../poller/poller.h"
#include "zbxtasks.h"
#include "zbxcommshigh.h"
#include "zbxversion.h"
#ifdef HAVE_OPENIPMI
#include "zbxipmi.h"
#endif
#include "zbxnum.h"
#include "zbxsysinfo.h"
#include "trapper_auth.h"
#include "zbxpreproc.h"
#include "zbxpreprocbase.h"
#include "zbxdbhigh.h"
#include "zbxtime.h"

#define ZBX_STATE_NOT_SUPPORTED	1

static void	dump_item(const zbx_dc_item_t *item)
{
	if (SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_TRACE))
	{
		zbx_log_handle(LOG_LEVEL_TRACE, "key:'%s'", item->key);
		zbx_log_handle(LOG_LEVEL_TRACE, "  type: %u", item->type);
		zbx_log_handle(LOG_LEVEL_TRACE, "  snmp_version: %u", item->snmp_version);
		zbx_log_handle(LOG_LEVEL_TRACE, "  value_type: %u", item->value_type);
		zbx_log_handle(LOG_LEVEL_TRACE, "  snmpv3_securitylevel: %u", item->snmpv3_securitylevel);
		zbx_log_handle(LOG_LEVEL_TRACE, "  authtype: %u", item->authtype);
		zbx_log_handle(LOG_LEVEL_TRACE, "  flags: %u", item->flags);
		zbx_log_handle(LOG_LEVEL_TRACE, "  snmpv3_authprotocol: %u", item->snmpv3_authprotocol);
		zbx_log_handle(LOG_LEVEL_TRACE, "  snmpv3_privprotocol: %u", item->snmpv3_privprotocol);
		zbx_log_handle(LOG_LEVEL_TRACE, "  follow_redirects: %u", item->follow_redirects);
		zbx_log_handle(LOG_LEVEL_TRACE, "  post_type: %u", item->post_type);
		zbx_log_handle(LOG_LEVEL_TRACE, "  retrieve_mode: %u", item->retrieve_mode);
		zbx_log_handle(LOG_LEVEL_TRACE, "  request_method: %u", item->request_method);
		zbx_log_handle(LOG_LEVEL_TRACE, "  output_format: %u", item->output_format);
		zbx_log_handle(LOG_LEVEL_TRACE, "  verify_peer: %u", item->verify_peer);
		zbx_log_handle(LOG_LEVEL_TRACE, "  verify_host: %u", item->verify_host);
		zbx_log_handle(LOG_LEVEL_TRACE, "  ipmi_sensor:'%s'", item->ipmi_sensor);
		zbx_log_handle(LOG_LEVEL_TRACE, "  snmp_community:'%s'", item->snmp_community);
		zbx_log_handle(LOG_LEVEL_TRACE, "  snmp_oid:'%s'", item->snmp_oid);
		zbx_log_handle(LOG_LEVEL_TRACE, "  snmpv3_securityname:'%s'", item->snmpv3_securityname);
		zbx_log_handle(LOG_LEVEL_TRACE, "  snmpv3_authpassphrase:'%s'", item->snmpv3_authpassphrase);
		zbx_log_handle(LOG_LEVEL_TRACE, "  snmpv3_privpassphrase:'%s'", item->snmpv3_privpassphrase);
		zbx_log_handle(LOG_LEVEL_TRACE, "  params:'%s'", ZBX_NULL2STR(item->params));
		zbx_log_handle(LOG_LEVEL_TRACE, "  username:'%s'", item->username);
		zbx_log_handle(LOG_LEVEL_TRACE, "  publickey:'%s'", item->publickey);
		zbx_log_handle(LOG_LEVEL_TRACE, "  privatekey:'%s'", item->privatekey);
		zbx_log_handle(LOG_LEVEL_TRACE, "  password:'%s'", item->password);
		zbx_log_handle(LOG_LEVEL_TRACE, "  snmpv3_contextname:'%s'", item->snmpv3_contextname);
		zbx_log_handle(LOG_LEVEL_TRACE, "  jmx_endpoint:'%s'", item->jmx_endpoint);
		zbx_log_handle(LOG_LEVEL_TRACE, "  timeout: %d", item->timeout);
		zbx_log_handle(LOG_LEVEL_TRACE, "  url:'%s'", item->url);
		zbx_log_handle(LOG_LEVEL_TRACE, "  query_fields:'%s'", item->query_fields);
		zbx_log_handle(LOG_LEVEL_TRACE, "  posts:'%s'", ZBX_NULL2STR(item->posts));
		zbx_log_handle(LOG_LEVEL_TRACE, "  status_codes:'%s'", item->status_codes);
		zbx_log_handle(LOG_LEVEL_TRACE, "  http_proxy:'%s'", item->http_proxy);
		zbx_log_handle(LOG_LEVEL_TRACE, "  headers:'%s'", ZBX_NULL2STR(item->headers));
		zbx_log_handle(LOG_LEVEL_TRACE, "  ssl_cert_file:'%s'", item->ssl_cert_file);
		zbx_log_handle(LOG_LEVEL_TRACE, "  ssl_key_file:'%s'", item->ssl_key_file);
		zbx_log_handle(LOG_LEVEL_TRACE, "  ssl_key_password:'%s'", item->ssl_key_password);
		zbx_log_handle(LOG_LEVEL_TRACE, "interfaceid: " ZBX_FS_UI64, item->interface.interfaceid);
		zbx_log_handle(LOG_LEVEL_TRACE, "  useip: %u", item->interface.useip);
		zbx_log_handle(LOG_LEVEL_TRACE, "  address:'%s'", ZBX_NULL2STR(item->interface.addr));
		zbx_log_handle(LOG_LEVEL_TRACE, "  port: %u", item->interface.port);
		zbx_log_handle(LOG_LEVEL_TRACE, "hostid: " ZBX_FS_UI64, item->host.hostid);
		zbx_log_handle(LOG_LEVEL_TRACE, "  proxyid: " ZBX_FS_UI64, item->host.proxyid);
		zbx_log_handle(LOG_LEVEL_TRACE, "  host:'%s'", item->host.host);
		zbx_log_handle(LOG_LEVEL_TRACE, "  maintenance_status: %u", item->host.maintenance_status);
		zbx_log_handle(LOG_LEVEL_TRACE, "  maintenance_type: %u", item->host.maintenance_type);
		zbx_log_handle(LOG_LEVEL_TRACE, "  available: %u", item->interface.available);
		zbx_log_handle(LOG_LEVEL_TRACE, "  ipmi_authtype: %d", item->host.ipmi_authtype);
		zbx_log_handle(LOG_LEVEL_TRACE, "  ipmi_privilege: %u", item->host.ipmi_privilege);
		zbx_log_handle(LOG_LEVEL_TRACE, "  ipmi_username:'%s'", item->host.ipmi_username);
		zbx_log_handle(LOG_LEVEL_TRACE, "  ipmi_password:'%s'", item->host.ipmi_password);
		zbx_log_handle(LOG_LEVEL_TRACE, "  tls_connect: %u", item->host.tls_connect);
	#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
		zbx_log_handle(LOG_LEVEL_TRACE, "  tls_issuer:'%s'", item->host.tls_issuer);
		zbx_log_handle(LOG_LEVEL_TRACE, "  tls_subject:'%s'", item->host.tls_subject);
		zbx_log_handle(LOG_LEVEL_TRACE, "  tls_psk_identity:'%s'", item->host.tls_psk_identity);
		zbx_log_handle(LOG_LEVEL_TRACE, "  tls_psk:'%s'", item->host.tls_psk);
	#endif
	}
}

static char	*db_string_from_json_dyn(const struct zbx_json_parse *jp, const char *name, const zbx_db_table_t *table,
		const char *fieldname)
{
	char	*string = NULL;
	size_t	size = 0;

	if (SUCCEED == zbx_json_value_by_name_dyn(jp, name, &string, &size, NULL))
		return string;

	return zbx_strdup(NULL, zbx_db_get_field(table, fieldname)->default_value);
}

static void	db_string_from_json(const struct zbx_json_parse *jp, const char *name, const zbx_db_table_t *table,
		const char *fieldname, char *string, size_t len)
{

	if (SUCCEED != zbx_json_value_by_name(jp, name, string, len, NULL))
		zbx_strlcpy(string, zbx_db_get_field(table, fieldname)->default_value, len);
}

static void	db_uchar_from_json(const struct zbx_json_parse *jp, const char *name, const zbx_db_table_t *table,
		const char *fieldname, unsigned char *string)
{
	char	tmp[ZBX_MAX_UINT64_LEN + 1];

	if (SUCCEED == zbx_json_value_by_name(jp, name, tmp, sizeof(tmp), NULL))
		ZBX_STR2UCHAR(*string, tmp);
	else
		ZBX_STR2UCHAR(*string, zbx_db_get_field(table, fieldname)->default_value);
}

static void	db_int_from_json(const struct zbx_json_parse *jp, const char *name, const zbx_db_table_t *table,
		const char *fieldname, int *num)
{
	char	tmp[ZBX_MAX_UINT64_LEN + 1];

	if (SUCCEED == zbx_json_value_by_name(jp, name, tmp, sizeof(tmp), NULL))
		*num = atoi(tmp);
	else
		*num = atoi(zbx_db_get_field(table, fieldname)->default_value);
}

/******************************************************************************
 *                                                                            *
 * Purpose: parses preprocessing test request                                 *
 *                                                                            *
 * Parameters: jp_item      - [IN] item object of the request                 *
 *             jp_options   - [IN] options object of the request              *
 *             jp_steps     - [IN] steps object of the request                *
 *             value        - [IN] item value for preprocessing               *
 *             value_size   - [IN] size of the item value for preprocessing   *
 *             state        - [IN] the item state                             *
 *             values       - [OUT] the values to test optional               *
 *                                  (history + current)                       *
 *             ts           - [OUT] value timestamps                          *
 *             values_num   - [OUT] the number of values                      *
 *             value_type   - [OUT] the value type                            *
 *             steps        - [OUT] the preprocessing steps                   *
 *             single       - [OUT] is preproc step single                    *
 *             bypass_first - [OUT] the flag to bypass first step             *
 *             error        - [OUT] the error message                         *
 *                                                                            *
 * Return value: SUCCEED - the request was parsed successfully                *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	trapper_parse_preproc_test(const struct zbx_json_parse *jp_item,
		const struct zbx_json_parse *jp_options, const struct zbx_json_parse *jp_steps, char *value,
		size_t value_size, int state, char **values, zbx_timespec_t *ts, int *values_num,
		unsigned char *value_type, zbx_vector_pp_step_ptr_t *steps, int *single, int *bypass_first,
		char **error)
{
	char			buffer[MAX_STRING_LEN], *step_params = NULL, *error_handler_params = NULL;
	const char		*ptr;
	int			ret = FAIL;
	struct zbx_json_parse	jp_history, jp_step;
	size_t			size;
	zbx_timespec_t		ts_now;

	if (FAIL == zbx_json_value_by_name(jp_item, ZBX_PROTO_TAG_VALUE_TYPE, buffer, sizeof(buffer), NULL))
	{
		*error = zbx_strdup(NULL, "Missing value type field.");
		goto out;
	}

	*value_type = (unsigned char)atoi(buffer);

	if (FAIL == zbx_json_value_by_name(jp_options, ZBX_PROTO_TAG_SINGLE, buffer, sizeof(buffer), NULL))
		*single = 0;
	else
		*single = (0 == strcmp(buffer, "true") ? 1 : 0);

	zbx_timespec(&ts_now);

	if (SUCCEED == zbx_json_brackets_by_name(jp_options, ZBX_PROTO_TAG_HISTORY, &jp_history))
	{
		size = 0;

		if (FAIL == zbx_json_value_by_name_dyn(&jp_history, ZBX_PROTO_TAG_VALUE, values, &size, NULL))
		{
			*error = zbx_strdup(NULL, "Missing history value field.");
			goto out;
		}

		(*values_num)++;

		if (FAIL == zbx_json_value_by_name(&jp_history, ZBX_PROTO_TAG_TIMESTAMP, buffer, sizeof(buffer), NULL))
		{
			*error = zbx_strdup(NULL, "Missing history timestamp field.");
			goto out;
		}

		if (0 != strncmp(buffer, "now", ZBX_CONST_STRLEN("now")))
		{
			*error = zbx_dsprintf(NULL, "invalid history value timestamp: %s", buffer);
			goto out;
		}

		ts[0] = ts_now;
		ptr = buffer + ZBX_CONST_STRLEN("now");

		if ('\0' != *ptr)
		{
			int	delay;

			if ('-' != *ptr || FAIL == zbx_is_time_suffix(ptr + 1, &delay, (int)strlen(ptr + 1)))
			{
				*error = zbx_dsprintf(NULL, "invalid history value timestamp: %s", buffer);
				goto out;
			}

			ts[0].sec -= delay;
		}
	}

	values[*values_num] = zbx_strdup(NULL, value);
	size = value_size;

	ts[(*values_num)++] = ts_now;
	*bypass_first = 0;

	for (ptr = NULL; NULL != (ptr = zbx_json_next(jp_steps, ptr));)
	{
		zbx_pp_step_t	*step;
		int		step_type, error_handler;

		if (FAIL == zbx_json_brackets_open(ptr, &jp_step))
		{
			*error = zbx_strdup(NULL, "Cannot parse preprocessing step.");
			goto out;
		}

		if (FAIL == zbx_json_value_by_name(&jp_step, ZBX_PROTO_TAG_TYPE, buffer, sizeof(buffer), NULL))
		{
			*error = zbx_strdup(NULL, "Missing preprocessing step type field.");
			goto out;
		}

		step_type = atoi(buffer);

		if (FAIL == zbx_json_value_by_name(&jp_step, ZBX_PROTO_TAG_ERROR_HANDLER, buffer, sizeof(buffer), NULL))
		{
			*error = zbx_strdup(NULL, "Missing preprocessing step type error handler field.");
			goto out;
		}

		error_handler = atoi(buffer);

		size = 0;

		if (FAIL == zbx_json_value_by_name_dyn(&jp_step, ZBX_PROTO_TAG_PARAMS, &step_params, &size, NULL))
		{
			*error = zbx_strdup(NULL, "Missing preprocessing step type params field.");
			goto out;
		}

		size = 0;

		if (FAIL == zbx_json_value_by_name_dyn(&jp_step, ZBX_PROTO_TAG_ERROR_HANDLER_PARAMS,
				&error_handler_params, &size, NULL))
		{
			*error = zbx_strdup(NULL, "Missing preprocessing step type error handler params field.");
			goto out;
		}

		if (ZBX_PREPROC_VALIDATE_NOT_SUPPORTED != step_type || ZBX_STATE_NOT_SUPPORTED == state)
		{
			step = (zbx_pp_step_t *)zbx_malloc(NULL, sizeof(zbx_pp_step_t));
			step->type = step_type;
			step->params = step_params;
			step->error_handler = error_handler;
			step->error_handler_params = error_handler_params;
			zbx_vector_pp_step_ptr_append(steps, step);
		}
		else
		{
			zbx_free(step_params);
			zbx_free(error_handler_params);
			(*bypass_first)++;
		}

		step_params = NULL;
		error_handler_params = NULL;
	}

	ret = SUCCEED;
out:
	if (FAIL == ret)
	{
		zbx_vector_pp_step_ptr_clear_ext(steps, zbx_pp_step_free);
		zbx_free(values[0]);
		zbx_free(values[1]);
	}

	zbx_free(step_params);
	zbx_free(error_handler_params);

	return ret;
}

static void	json_add_string_with_limit(struct zbx_json *j, const char *tag, const char *value, zbx_json_type_t type)
{
	size_t	original_size = zbx_json_addstring_limit(j, tag, value, type, ZBX_JSON_TEST_DATA_MAX_SIZE);

	if (ZBX_JSON_TEST_DATA_MAX_SIZE < original_size)
	{
		zbx_json_addstring(j, ZBX_PROTO_TAG_TRUNCATED, "true", ZBX_JSON_TYPE_TRUE);
		zbx_json_adduint64(j, ZBX_PROTO_TAG_ORIGINAL_SIZE, original_size);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: executes preprocessing test request                               *
 *                                                                            *
 * Parameters: jp_item    - [IN] item object of the request                   *
 *             jp_options - [IN] options object of the request                *
 *             jp_steps   - [IN] steps object of the request                  *
 *             value      - [IN] item value for preprocessing                 *
 *             value_size - [IN] size of the item value for preprocessing     *
 *             state      - [IN] the item state                               *
 *             json       - [OUT] the output json                             *
 *             error      - [OUT] the error message                           *
 *                                                                            *
 * Return value: SUCCEED - the request was executed successfully              *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: This function will fail if the request format is not valid or    *
 *           there was connection (to preprocessing manager) error.           *
 *           Any errors in the preprocessing itself are reported in output    *
 *           json and success is returned.                                    *
 *                                                                            *
 ******************************************************************************/
int	trapper_preproc_test_run(const struct zbx_json_parse *jp_item, const struct zbx_json_parse *jp_options,
		const struct zbx_json_parse *jp_steps, char *value, size_t value_size, int state, struct zbx_json *json,
		char **error)
{
	char				*values[2] = {NULL, NULL}, *preproc_error = NULL;
	int				i, single, bypass_first, ret = FAIL, values_num = 0, first_step_type;
	unsigned char			value_type;
	zbx_vector_pp_step_ptr_t	steps;
	zbx_timespec_t			ts[2];
	zbx_pp_result_t			*result;
	zbx_vector_pp_result_ptr_t	results;
	zbx_pp_history_t		history;

	zbx_vector_pp_step_ptr_create(&steps);
	zbx_vector_pp_result_ptr_create(&results);
	zbx_pp_history_init(&history);

	if (FAIL == trapper_parse_preproc_test(jp_item, jp_options, jp_steps, value, value_size, state, values, ts,
			&values_num, &value_type, &steps, &single, &bypass_first, error))
	{
		goto out;
	}

	first_step_type = 0;
	if (0 != steps.values_num)
		first_step_type  = steps.values[0]->type;

	if (ZBX_PREPROC_VALIDATE_NOT_SUPPORTED != first_step_type && ZBX_STATE_NOT_SUPPORTED == state)
	{
		preproc_error = zbx_strdup(NULL, "This item is not supported. Please, add a preprocessing step"
				" \"Check for not supported value\" to process it.");
		zbx_json_addarray(json, ZBX_PROTO_TAG_STEPS);
		goto err;
	}

	for (i = 0; i < values_num; i++)
	{
		zbx_vector_pp_result_ptr_clear_ext(&results, zbx_pp_result_free);

		if (0 == steps.values_num)
		{
			result = (zbx_pp_result_t *)zbx_malloc(NULL, sizeof(zbx_pp_result_t));

			result->action = ZBX_PREPROC_FAIL_DEFAULT;
			zbx_variant_set_str(&result->value, zbx_strdup(NULL, values[i]));
			zbx_variant_set_none(&result->value_raw);
			zbx_vector_pp_result_ptr_append(&results, result);
		}
		else if (FAIL == zbx_preprocessor_test(value_type, values[i], &ts[i], (unsigned char)state, &steps,
				&results, &history, error))
		{
			goto out;
		}

		if (ZBX_VARIANT_ERR == results.values[results.values_num - 1]->value.type)
		{
			preproc_error = zbx_strdup(NULL, results.values[results.values_num - 1]->value.data.err);
			break;
		}

		if (0 == single)
		{
			result = (zbx_pp_result_t *)results.values[results.values_num - 1];
			if (ZBX_VARIANT_NONE != result->value.type && FAIL == zbx_variant_to_value_type(&result->value,
					value_type, &preproc_error))
			{
				break;
			}
		}
	}

	if (i + 1 < values_num)
		zbx_json_addstring(json, ZBX_PROTO_TAG_PREVIOUS, "true", ZBX_JSON_TYPE_INT);

	zbx_json_addarray(json, ZBX_PROTO_TAG_STEPS);

	for (i = 0; i < bypass_first; i++)
	{
		zbx_json_addobject(json, NULL);
		json_add_string_with_limit(json, ZBX_PROTO_TAG_RESULT, values[values_num - 1], ZBX_JSON_TYPE_STRING);
		zbx_json_close(json);
	}

	if (0 != steps.values_num)
	{
		for (i = 0; i < results.values_num; i++)
		{
			result = (zbx_pp_result_t *)results.values[i];

			zbx_json_addobject(json, NULL);

			if (ZBX_PREPROC_FAIL_DEFAULT != result->action)
			{
				zbx_json_addint64(json, ZBX_PROTO_TAG_ACTION, result->action);
				zbx_json_addobject(json, ZBX_PROTO_TAG_ERROR);
				json_add_string_with_limit(json, ZBX_PROTO_TAG_VALUE, result->value_raw.data.err,
						ZBX_JSON_TYPE_STRING);
				zbx_json_close(json);

				if (ZBX_PREPROC_FAIL_SET_ERROR == result->action)
				{
					json_add_string_with_limit(json, ZBX_PROTO_TAG_FAILED, result->value.data.err,
							ZBX_JSON_TYPE_STRING);
				}
			}

			if (ZBX_VARIANT_ERR == result->value.type)
			{
				if (ZBX_PREPROC_FAIL_DEFAULT == result->action)
				{
					zbx_json_addobject(json, ZBX_PROTO_TAG_ERROR);
					json_add_string_with_limit(json, ZBX_PROTO_TAG_VALUE, result->value.data.err,
							ZBX_JSON_TYPE_STRING);
					zbx_json_close(json);
				}
			}
			else if (ZBX_VARIANT_NONE != result->value.type)
			{
				json_add_string_with_limit(json, ZBX_PROTO_TAG_RESULT,
						zbx_variant_value_desc(&result->value), ZBX_JSON_TYPE_STRING);
			}
			else
				zbx_json_addstring(json, ZBX_PROTO_TAG_RESULT, NULL, ZBX_JSON_TYPE_NULL);

			zbx_json_close(json);
		}
	}
err:
	zbx_json_close(json);

	if (NULL == preproc_error)
	{
		result = (zbx_pp_result_t *)results.values[results.values_num - 1];

		if (ZBX_VARIANT_NONE != result->value.type)
		{
			json_add_string_with_limit(json, ZBX_PROTO_TAG_RESULT, zbx_variant_value_desc(&result->value),
					ZBX_JSON_TYPE_STRING);
		}
		else
			zbx_json_addstring(json, ZBX_PROTO_TAG_RESULT, NULL, ZBX_JSON_TYPE_NULL);
	}
	else
		json_add_string_with_limit(json, ZBX_PROTO_TAG_ERROR, preproc_error, ZBX_JSON_TYPE_STRING);

	ret = SUCCEED;
out:
	zbx_free(preproc_error);

	for (i = 0; i < values_num; i++)
		zbx_free(values[i]);

	zbx_pp_history_clear(&history);

	zbx_vector_pp_result_ptr_clear_ext(&results, zbx_pp_result_free);
	zbx_vector_pp_result_ptr_destroy(&results);

	zbx_vector_pp_step_ptr_clear_ext(&steps, zbx_pp_step_free);
	zbx_vector_pp_step_ptr_destroy(&steps);

	return ret;
}

int	zbx_trapper_item_test_run(const struct zbx_json_parse *jp_data, zbx_uint64_t proxyid, char **info,
		const zbx_config_comms_args_t *config_comms, int config_startup_time, unsigned char program_type,
		const char *progname, zbx_get_config_forks_f get_config_forks,  const char *config_java_gateway,
		int config_java_gateway_port, const char *config_externalscripts)
{
	char				tmp[MAX_STRING_LEN + 1], **pvalue;
	zbx_dc_item_t			item;
	static const zbx_db_table_t	*table_items, *table_interface, *table_interface_snmp, *table_hosts;
	struct zbx_json_parse		jp_item, jp_host, jp_steps, jp_interface, jp_details, jp_script_params;
	AGENT_RESULT			result;
	int				errcode, ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	// item JSON object presence is checked in the calling function
	if (FAIL == zbx_json_brackets_by_name(jp_data, ZBX_PROTO_TAG_ITEM, &jp_item))
		THIS_SHOULD_NEVER_HAPPEN;

	if (FAIL == zbx_json_brackets_by_name(jp_data, ZBX_PROTO_TAG_HOST, &jp_host))
		zbx_json_open("{}", &jp_host);

	if (FAIL == zbx_json_brackets_by_name(&jp_item, ZBX_PROTO_TAG_STEPS, &jp_steps))
		jp_steps.end = jp_steps.start = NULL;

	memset(&item, 0, sizeof(item));

	if (NULL == table_items)
		table_items = zbx_db_get_table("items");

	db_uchar_from_json(&jp_item, ZBX_PROTO_TAG_TYPE, table_items, "type", &item.type);
	db_string_from_json(&jp_item, ZBX_PROTO_TAG_KEY, table_items, "key_", item.key_orig, sizeof(item.key_orig));
	item.key = db_string_from_json_dyn(&jp_item, ZBX_PROTO_TAG_KEY, table_items, "key_");

	if (0 != proxyid && FAIL == zbx_is_item_processed_by_server(item.type, item.key))
	{
		ret = zbx_tm_execute_task_data(jp_data->start, (size_t)(jp_data->end - jp_data->start + 1),
				proxyid, info);
		goto out;
	}

	db_uchar_from_json(&jp_item, ZBX_PROTO_TAG_VALUE_TYPE, table_items, "value_type", &item.value_type);
	db_uchar_from_json(&jp_item, ZBX_PROTO_TAG_AUTHTYPE, table_items, "authtype", &item.authtype);
	db_uchar_from_json(&jp_item, ZBX_PROTO_TAG_FLAGS, table_items, "flags", &item.flags);
	db_uchar_from_json(&jp_item, ZBX_PROTO_TAG_FOLLOW_REDIRECTS, table_items, "follow_redirects",
			&item.follow_redirects);
	db_uchar_from_json(&jp_item, ZBX_PROTO_TAG_POST_TYPE, table_items, "post_type", &item.post_type);
	db_uchar_from_json(&jp_item, ZBX_PROTO_TAG_RETRIEVE_MODE, table_items, "retrieve_mode", &item.retrieve_mode);
	db_uchar_from_json(&jp_item, ZBX_PROTO_TAG_REQUEST_METHOD, table_items, "request_method", &item.request_method);
	db_uchar_from_json(&jp_item, ZBX_PROTO_TAG_OUTPUT_FORMAT, table_items, "output_format", &item.output_format);
	db_uchar_from_json(&jp_item, ZBX_PROTO_TAG_VERIFY_PEER, table_items, "verify_peer", &item.verify_peer);
	db_uchar_from_json(&jp_item, ZBX_PROTO_TAG_VERIFY_HOST, table_items, "verify_host", &item.verify_host);

	db_string_from_json(&jp_item, ZBX_PROTO_TAG_IPMI_SENSOR, table_items, "ipmi_sensor", item.ipmi_sensor,
			sizeof(item.ipmi_sensor));

	item.snmp_oid = db_string_from_json_dyn(&jp_item, ZBX_PROTO_TAG_SNMP_OID, table_items, "snmp_oid");
	item.params = db_string_from_json_dyn(&jp_item, ZBX_PROTO_TAG_PARAMS, table_items, "params");
	item.username = db_string_from_json_dyn(&jp_item, ZBX_PROTO_TAG_USERNAME, table_items, "username");
	item.publickey = db_string_from_json_dyn(&jp_item, ZBX_PROTO_TAG_PUBLICKEY, table_items, "publickey");
	item.privatekey = db_string_from_json_dyn(&jp_item, ZBX_PROTO_TAG_PRIVATEKEY, table_items, "privatekey");
	item.password = db_string_from_json_dyn(&jp_item, ZBX_PROTO_TAG_PASSWORD, table_items, "password");
	item.jmx_endpoint = db_string_from_json_dyn(&jp_item, ZBX_PROTO_TAG_JMX_ENDPOINT, table_items, "jmx_endpoint");

	switch (item.type)
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
		case ITEM_TYPE_HTTPAGENT:
			db_string_from_json(&jp_item, ZBX_PROTO_TAG_TIMEOUT, table_items, "timeout", item.timeout_orig,
					sizeof(item.timeout_orig));
			break;
	}

	item.url = db_string_from_json_dyn(&jp_item, ZBX_PROTO_TAG_URL, table_items, "url");
	item.query_fields = db_string_from_json_dyn(&jp_item, ZBX_PROTO_TAG_QUERY_FIELDS, table_items, "query_fields");

	item.posts = db_string_from_json_dyn(&jp_item, ZBX_PROTO_TAG_POSTS, table_items, "posts");

	item.status_codes = db_string_from_json_dyn(&jp_item, ZBX_PROTO_TAG_STATUS_CODES, table_items, "status_codes");
	item.http_proxy = db_string_from_json_dyn(&jp_item, ZBX_PROTO_TAG_HTTP_PROXY, table_items, "http_proxy");

	item.headers = db_string_from_json_dyn(&jp_item, ZBX_PROTO_TAG_HTTP_HEADERS, table_items, "headers");

	item.ssl_cert_file = db_string_from_json_dyn(&jp_item, ZBX_PROTO_TAG_SSL_CERT_FILE, table_items,
			"ssl_cert_file");
	item.ssl_key_file = db_string_from_json_dyn(&jp_item, ZBX_PROTO_TAG_SSL_KEY_FILE, table_items, "ssl_key_file");
	item.ssl_key_password = db_string_from_json_dyn(&jp_item, ZBX_PROTO_TAG_SSL_KEY_PASSWORD, table_items,
			"ssl_key_password");

	if (ITEM_TYPE_SCRIPT == item.type &&
			SUCCEED == zbx_json_brackets_by_name(&jp_item, ZBX_PROTO_TAG_PARAMETERS, &jp_script_params))
	{
		item.script_params = zbx_dsprintf(NULL, "%.*s",
				(int)(jp_script_params.end - jp_script_params.start + 1), jp_script_params.start);
	}
	else
		item.script_params = zbx_strdup(NULL, "");

	if (NULL == table_interface)
		table_interface = zbx_db_get_table("interface");

	if (FAIL == zbx_json_brackets_by_name(&jp_host, ZBX_PROTO_TAG_INTERFACE, &jp_interface))
		zbx_json_open("{}", &jp_interface);

	if (SUCCEED == zbx_json_value_by_name(&jp_interface, ZBX_PROTO_TAG_INTERFACE_ID, tmp, sizeof(tmp), NULL))
		ZBX_STR2UINT64(item.interface.interfaceid, tmp);
	else
		item.interface.interfaceid = 0;

	item.interface.version = ZBX_COMPONENT_VERSION(7, 0, 0);

	db_uchar_from_json(&jp_interface, ZBX_PROTO_TAG_USEIP, table_interface, "useip", &item.interface.useip);

	if (1 == item.interface.useip)
	{
		db_string_from_json(&jp_interface, ZBX_PROTO_TAG_ADDRESS, table_interface, "ip", item.interface.ip_orig,
				sizeof(item.interface.ip_orig));
		item.interface.addr = item.interface.ip_orig;
	}
	else
	{
		db_string_from_json(&jp_interface, ZBX_PROTO_TAG_ADDRESS, table_interface, "dns",
				item.interface.dns_orig, sizeof(item.interface.dns_orig));
		item.interface.addr = item.interface.dns_orig;
	}

	db_string_from_json(&jp_interface, ZBX_PROTO_TAG_PORT, table_interface, "port", item.interface.port_orig,
			sizeof(item.interface.port_orig));

	db_uchar_from_json(&jp_interface, ZBX_PROTO_TAG_AVAILABLE, table_interface, "available",
			&item.interface.available);

	if (FAIL == zbx_json_brackets_by_name(&jp_interface, ZBX_PROTO_TAG_DETAILS, &jp_details))
		zbx_json_open("{}", &jp_details);

	if (NULL == table_interface_snmp)
		table_interface_snmp = zbx_db_get_table("interface_snmp");

	db_uchar_from_json(&jp_details, ZBX_PROTO_TAG_VERSION, table_interface_snmp, "version", &item.snmp_version);
	item.snmp_community = db_string_from_json_dyn(&jp_details, ZBX_PROTO_TAG_COMMUNITY, table_interface_snmp,
			"community");
	item.snmpv3_securityname = db_string_from_json_dyn(&jp_details, ZBX_PROTO_TAG_SECURITYNAME,
			table_interface_snmp, "securityname");

	db_uchar_from_json(&jp_details, ZBX_PROTO_TAG_SECURITYLEVEL, table_interface_snmp, "securitylevel",
			&item.snmpv3_securitylevel);

	item.snmpv3_authpassphrase = db_string_from_json_dyn(&jp_details, ZBX_PROTO_TAG_AUTHPASSPHRASE,
			table_interface_snmp, "authpassphrase");
	item.snmpv3_privpassphrase = db_string_from_json_dyn(&jp_details, ZBX_PROTO_TAG_PRIVPASSPHRASE,
			table_interface_snmp, "privpassphrase");

	db_int_from_json(&jp_details, ZBX_PROTO_TAG_MAX_REPS, table_interface_snmp, "max_repetitions",
			&item.snmp_max_repetitions);

	db_uchar_from_json(&jp_details, ZBX_PROTO_TAG_AUTHPROTOCOL, table_interface_snmp, "authprotocol",
			&item.snmpv3_authprotocol);
	db_uchar_from_json(&jp_details, ZBX_PROTO_TAG_PRIVPROTOCOL, table_interface_snmp, "privprotocol",
			&item.snmpv3_privprotocol);

	item.snmpv3_contextname = db_string_from_json_dyn(&jp_details, ZBX_PROTO_TAG_CONTEXTNAME, table_interface_snmp,
			"contextname");

	if (NULL == table_hosts)
		table_hosts = zbx_db_get_table("hosts");

	db_string_from_json(&jp_host, ZBX_PROTO_TAG_HOST, table_hosts, "host", item.host.host, sizeof(item.host.host));

	if (SUCCEED == zbx_json_value_by_name(&jp_host, ZBX_PROTO_TAG_HOSTID, tmp, sizeof(tmp), NULL))
		ZBX_STR2UINT64(item.host.hostid, tmp);
	else
		item.host.hostid = 0;

	db_uchar_from_json(&jp_host, ZBX_PROTO_TAG_MAINTENANCE_STATUS, table_hosts, "maintenance_status",
			&item.host.maintenance_status);
	db_uchar_from_json(&jp_host, ZBX_PROTO_TAG_MAINTENANCE_TYPE, table_hosts, "maintenance_type",
			&item.host.maintenance_type);
	if (SUCCEED == zbx_json_value_by_name(&jp_host, ZBX_PROTO_TAG_IPMI_AUTHTYPE, tmp, sizeof(tmp), NULL))
		item.host.ipmi_authtype = (char)atoi(tmp);
	else
		item.host.ipmi_authtype = (char)atoi(zbx_db_get_field(table_hosts, "ipmi_authtype")->default_value);
	db_uchar_from_json(&jp_host, ZBX_PROTO_TAG_IPMI_PRIVILEGE, table_hosts, "ipmi_privilege",
			&item.host.ipmi_privilege);
	db_string_from_json(&jp_host, ZBX_PROTO_TAG_IPMI_USERNAME, table_hosts, "ipmi_username",
			item.host.ipmi_username, sizeof(item.host.ipmi_username));
	db_string_from_json(&jp_host, ZBX_PROTO_TAG_IPMI_PASSWORD, table_hosts, "ipmi_password",
			item.host.ipmi_password, sizeof(item.host.ipmi_password));
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
		zbx_init_agent_result(&result);

		if (FAIL == zbx_is_ushort(item.interface.port_orig, &item.interface.port))
		{
			*info = zbx_dsprintf(NULL, "Invalid port number [%s]", item.interface.port_orig);
		}
		else
		{
#ifdef HAVE_OPENIPMI
			if (0 == get_config_forks(ZBX_PROCESS_TYPE_IPMIPOLLER))
			{
				*info = zbx_strdup(NULL, "Cannot perform IPMI request: configuration parameter"
						" \"StartIPMIPollers\" is 0.");
			}
			else
				ret = zbx_ipmi_test_item(&item, info);
#else
			ZBX_UNUSED(get_config_forks);
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

		zbx_prepare_items(&item, &errcode, 1, &result, ZBX_MACRO_EXPAND_NO);

		if (SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_TRACE))
			dump_item(&item);

		if (ITEM_TYPE_CALCULATED == item.type)
		{
			zbx_eval_context_t	ctx;
			char			*error = NULL;

			if (FAIL == zbx_eval_parse_expression(&ctx, item.params, ZBX_EVAL_PARSE_CALC_EXPRESSION,
					&error))
			{
				zbx_eval_set_exception(&ctx, zbx_dsprintf(NULL, "Cannot parse formula: %s", error));
				zbx_free(error);
			}

			zbx_eval_serialize(&ctx, NULL, &item.formula_bin);
			zbx_eval_clear(&ctx);
		}

		zbx_check_items(&item, &errcode, 1, &result, &add_results, ZBX_NO_POLLER, config_comms,
				config_startup_time, program_type, progname, get_config_forks, config_java_gateway,
				config_java_gateway_port, config_externalscripts);

		switch (errcode)
		{
			case SUCCEED:
				if (NULL == (pvalue = ZBX_GET_TEXT_RESULT(&result)))
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
				if (NULL == (pvalue = ZBX_GET_MSG_RESULT(&result)))
					*info = zbx_dsprintf(NULL, "unknown error with code %d", errcode);
				else
					*info = zbx_strdup(NULL, *pvalue);
		}

		zbx_vector_ptr_clear_ext(&add_results, (zbx_mem_free_func_t)zbx_free_agent_result_ptr);
		zbx_vector_ptr_destroy(&add_results);
	}

	zbx_clean_items(&item, 1, &result);
out:
	zbx_free(item.key);
	zbx_free(item.snmp_oid);
	zbx_free(item.params);
	zbx_free(item.username);
	zbx_free(item.publickey);
	zbx_free(item.privatekey);
	zbx_free(item.password);
	zbx_free(item.jmx_endpoint);
	zbx_free(item.url);
	zbx_free(item.query_fields);
	zbx_free(item.posts);
	zbx_free(item.status_codes);
	zbx_free(item.http_proxy);
	zbx_free(item.headers);
	zbx_free(item.ssl_cert_file);
	zbx_free(item.ssl_key_file);
	zbx_free(item.ssl_key_password);
	zbx_free(item.snmp_community);
	zbx_free(item.snmpv3_securityname);
	zbx_free(item.snmpv3_authpassphrase);
	zbx_free(item.snmpv3_privpassphrase);
	zbx_free(item.snmpv3_contextname);
	zbx_free(item.script_params);
	zbx_free(item.formula_bin);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

static int	try_find_preproc_value(const struct zbx_json_parse *jp_first, const char *name_first,
		const struct zbx_json_parse *jp_second, char *name_second, char **value, size_t *value_size)
{
	int ret = zbx_json_value_by_name_dyn(jp_first, name_first, value, value_size, NULL);

	if (FAIL == ret)
		ret = zbx_json_value_by_name_dyn(jp_second, name_second, value, value_size, NULL);

	return ret;
}

static int	trapper_item_test(const struct zbx_json_parse *jp, const zbx_config_comms_args_t *config_comms,
		int config_startup_time, unsigned char program_type, const char *progname,
		zbx_get_config_forks_f get_config_forks, const char *config_java_gateway, int config_java_gateway_port,
		const char *config_externalscripts, struct zbx_json *json, char **error)
{
	zbx_user_t		user;
	struct zbx_json_parse	jp_data, jp_item, jp_host, jp_options, jp_steps;
	char			tmp[MAX_ID_LEN + 1], *info = NULL, *value = NULL, buffer[MAX_STRING_LEN];
	zbx_uint64_t		proxyid;
	int			ret = FAIL, state = 0;
	size_t			value_size = 0;

	zbx_user_init(&user);

	if (FAIL == zbx_get_user_from_json(jp, &user, NULL) || USER_TYPE_ZABBIX_ADMIN > user.type)
	{
		*error = zbx_strdup(NULL, "Permission denied.");
		goto out;
	}

	if (SUCCEED != zbx_json_brackets_by_name(jp, ZBX_PROTO_TAG_DATA, &jp_data))
	{
		*error = zbx_dsprintf(NULL, "Cannot parse request tag: %s.", ZBX_PROTO_TAG_DATA);
		goto out;
	}

	if (FAIL == zbx_json_brackets_by_name(&jp_data, ZBX_PROTO_TAG_ITEM, &jp_item))
	{
		*error = zbx_strdup(NULL, "Missing item field.");
		goto out;
	}

	if (FAIL == zbx_json_brackets_by_name(&jp_data, ZBX_PROTO_TAG_HOST, &jp_host))
		zbx_json_open("{}", &jp_host);

	if (FAIL == zbx_json_brackets_by_name(&jp_data, ZBX_PROTO_TAG_OPTIONS, &jp_options))
		zbx_json_open("{}", &jp_options);

	if (FAIL == zbx_json_brackets_by_name(&jp_item, ZBX_PROTO_TAG_STEPS, &jp_steps))
		jp_steps.end = jp_steps.start = NULL;

	if (NULL != jp_steps.start)
	{
		int	value_found;

		if (SUCCEED == zbx_json_value_by_name(&jp_options, ZBX_PROTO_TAG_STATE, buffer, sizeof(buffer), NULL))
			state = atoi(buffer);

		if (ZBX_STATE_NOT_SUPPORTED == state)
		{
			value_found = try_find_preproc_value(&jp_options, ZBX_PROTO_TAG_RUNTIME_ERROR, &jp_item,
					ZBX_PROTO_TAG_VALUE, &value, &value_size);
		}
		else
		{
			value_found = try_find_preproc_value(&jp_item, ZBX_PROTO_TAG_VALUE, &jp_options,
					ZBX_PROTO_TAG_RUNTIME_ERROR, &value, &value_size);
		}

		if (FAIL != value_found)
			goto preproc_test;
	}

	if (SUCCEED == zbx_json_value_by_name(&jp_data, ZBX_PROTO_TAG_PROXYID, tmp, sizeof(tmp), NULL))
		ZBX_STR2UINT64(proxyid, tmp);
	else
		proxyid = 0;

	ret = zbx_trapper_item_test_run(&jp_data, proxyid, &info, config_comms, config_startup_time, program_type,
			progname, get_config_forks, config_java_gateway, config_java_gateway_port,
			config_externalscripts);

	if (FAIL == ret)
		state = ZBX_STATE_NOT_SUPPORTED;

	zabbix_log(LOG_LEVEL_DEBUG, "%s() json.buffer:'%s'", __func__, json->buffer);

preproc_test:
	zbx_json_addstring(json, ZBX_PROTO_TAG_RESPONSE, "success", ZBX_JSON_TYPE_STRING);
	zbx_json_addobject(json, ZBX_PROTO_TAG_DATA);

	if (NULL != info)
	{
		zbx_json_addobject(json, ZBX_PROTO_TAG_ITEM);

		json_add_string_with_limit(json, SUCCEED == ret ? ZBX_PROTO_TAG_RESULT : ZBX_PROTO_TAG_ERROR, info,
				ZBX_JSON_TYPE_STRING);

		zbx_json_addstring(json, ZBX_PROTO_TAG_EOL, NULL != strstr(info, "\r\n") ? "CRLF" : "LF",
				ZBX_JSON_TYPE_STRING);

		zbx_json_close(json);

		value = zbx_strdup(NULL, info);
		value_size = strlen(value);
		ret = SUCCEED;
	}

	if (NULL == jp_steps.start)
		goto out;

	zbx_json_addobject(json, ZBX_PROTO_TAG_PREPROCESSING);
	ret = trapper_preproc_test_run(&jp_item, &jp_options, &jp_steps, value, value_size, state, json, error);
	zbx_json_close(json);
out:
	zbx_free(value);
	zbx_free(info);
	zbx_user_free(&user);

	return ret;
}

void	zbx_trapper_item_test(zbx_socket_t *sock, const struct zbx_json_parse *jp,
		const zbx_config_comms_args_t *config_comms, int config_startup_time, unsigned char program_type,
		const char *progname, zbx_get_config_forks_f get_config_forks, const char *config_java_gateway,
		int config_java_gateway_port, const char *config_externalscripts)
{
	struct zbx_json	json;
	int		ret;
	char		*error = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_json_init(&json, 1024);

	if (SUCCEED == (ret = trapper_item_test(jp, config_comms, config_startup_time, program_type, progname,
			get_config_forks, config_java_gateway, config_java_gateway_port, config_externalscripts, &json,
			&error)))
	{
		zbx_tcp_send_bytes_to(sock, json.buffer, json.buffer_size, config_comms->config_timeout);
	}
	else
	{
		zbx_send_response(sock, ret, error, config_comms->config_timeout);
		zbx_free(error);
	}

	zbx_json_free(&json);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}
