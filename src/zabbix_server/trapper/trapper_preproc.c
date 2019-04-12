/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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
#include "log.h"
#include "zbxjson.h"
#include "zbxalgo.h"
#include "preproc.h"
#include "trapper_preproc.h"

/******************************************************************************
 *                                                                            *
 * Function: zbx_trapper_preproc_test                                         *
 *                                                                            *
 * Purpose: processes preprocessing test request                              *
 *                                                                            *
 * Parameters: sock - [IN] the request source socket (frontend)               *
 *             jp   - [IN] the request                                        *
 *                                                                            *
 * Return value: SUCCEED - the request was processed successfully             *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: This function will send proper (success/fail) response to the    *
 *           request socket.                                                  *
 *           Preprocessing failure (error returned by a preprocessing step)   *
 *           is counted as successful test and will return success response.  *
 *                                                                            *
 ******************************************************************************/
int	zbx_trapper_preproc_test(zbx_socket_t *sock, const struct zbx_json_parse *jp)
{
	char			buffer[MAX_STRING_LEN], *error = NULL, *value = NULL, *last_value = NULL,
				*last_ts = NULL, *step_params = NULL, *error_handler_params = NULL,
				*preproc_error = NULL;
	zbx_user_t		user;
	int			ret = FAIL, i;
	struct zbx_json_parse	jp_data, jp_history, jp_steps, jp_step;
	size_t			size;
	unsigned char		value_type, step_type, error_handler;
	zbx_vector_ptr_t	steps, results;
	const char		*pnext;
	struct zbx_json		json;

	zbx_vector_ptr_create(&steps);
	zbx_vector_ptr_create(&results);

	if (FAIL == zbx_json_value_by_name(jp, ZBX_PROTO_TAG_SID, buffer, sizeof(buffer)) ||
			SUCCEED != DBget_user_by_active_session(buffer, &user) || USER_TYPE_ZABBIX_ADMIN > user.type)
	{
		error = zbx_strdup(NULL, "Permission denied.");
		goto out;
	}

	if (FAIL == zbx_json_brackets_by_name(jp, ZBX_PROTO_TAG_DATA, &jp_data))
	{
		error = zbx_strdup(NULL, "Missing data field.");
		goto out;
	}

	size = 0;
	if (FAIL == zbx_json_value_by_name_dyn(&jp_data, ZBX_PROTO_TAG_VALUE, &value, &size))
	{
		error = zbx_strdup(NULL, "Missing value field.");
		goto out;
	}

	if (FAIL == zbx_json_value_by_name(&jp_data, ZBX_PROTO_TAG_VALUE_TYPE, buffer, sizeof(buffer)))
	{
		error = zbx_strdup(NULL, "Missing value type field.");
		goto out;
	}
	value_type = atoi(buffer);

	if (SUCCEED == zbx_json_brackets_by_name(&jp_data, ZBX_PROTO_TAG_HISTORY, &jp_history))
	{
		size = 0;
		if (FAIL == zbx_json_value_by_name_dyn(&jp_history, ZBX_PROTO_TAG_VALUE, &last_value, &size))
		{
			error = zbx_strdup(NULL, "Missing history value field.");
			goto out;
		}

		size = 0;
		if (FAIL == zbx_json_value_by_name_dyn(&jp_history, ZBX_PROTO_TAG_TIMESTAMP, &last_ts, &size))
		{
			error = zbx_strdup(NULL, "Missing history timestamp field.");
			goto out;
		}
	}

	if (FAIL == zbx_json_brackets_by_name(&jp_data, ZBX_PROTO_TAG_STEPS, &jp_steps))
	{
		error = zbx_strdup(NULL, "Missing preprocessing steps field.");
		goto out;
	}

	for (pnext = NULL; NULL != (pnext = zbx_json_next(&jp_steps, pnext));)
	{
		zbx_preproc_op_t	*step;

		if (FAIL == zbx_json_brackets_open(pnext, &jp_step))
		{
			error = zbx_strdup(NULL, "Cannot parse preprocessing step.");
			goto out;
		}

		if (FAIL == zbx_json_value_by_name(&jp_step, ZBX_PROTO_TAG_TYPE, buffer, sizeof(buffer)))
		{
			error = zbx_strdup(NULL, "Missing preprocessing step type field.");
			goto out;
		}
		step_type = atoi(buffer);

		if (FAIL == zbx_json_value_by_name(&jp_step, ZBX_PROTO_TAG_ERROR_HANDLER, buffer, sizeof(buffer)))
		{
			error = zbx_strdup(NULL, "Missing preprocessing step type error handler field.");
			goto out;
		}
		error_handler = atoi(buffer);

		size = 0;
		if (FAIL == zbx_json_value_by_name_dyn(&jp_step, ZBX_PROTO_TAG_PARAMS, &step_params, &size))
		{
			error = zbx_strdup(NULL, "Missing preprocessing step type params field.");
			goto out;
		}

		size = 0;
		if (FAIL == zbx_json_value_by_name_dyn(&jp_step, ZBX_PROTO_TAG_ERROR_HANDLER_PARAMS,
				&error_handler_params, &size))
		{
			error = zbx_strdup(NULL, "Missing preprocessing step type error handler params field.");
			goto out;
		}

		step = (zbx_preproc_op_t *)zbx_malloc(NULL, sizeof(zbx_preproc_op_t));
		step->type = step_type;
		step->params = step_params;
		step->error_handler = error_handler;
		step->error_handler_params = error_handler_params;
		zbx_vector_ptr_append(&steps, step);

		step_params = NULL;
		error_handler_params = NULL;
	}

	if (FAIL == zbx_preprocessor_test(value_type, value, last_value, last_ts, &steps, &results, &preproc_error,
			&error))
	{
		goto out;
	}

	zbx_json_init(&json, results.values_num * 256);

	zbx_json_addstring(&json, ZBX_PROTO_TAG_RESPONSE, "success", ZBX_JSON_TYPE_STRING);
	zbx_json_addarray(&json, ZBX_PROTO_TAG_DATA);

	for (i = 0; i < results.values_num; i++)
	{
		zbx_preproc_result_t	*result = (zbx_preproc_result_t *)results.values[i];

		zbx_json_addobject(&json, NULL);

		if (NULL != result->error)
			zbx_json_addstring(&json, ZBX_PROTO_TAG_ERROR, result->error, ZBX_JSON_TYPE_STRING);

		if (ZBX_PREPROC_FAIL_DEFAULT != result->action)
			zbx_json_adduint64(&json, ZBX_PROTO_TAG_ACTION, result->action);

		if (i == results.values_num - 1 && NULL != preproc_error)
		{
			if (ZBX_PREPROC_FAIL_SET_ERROR == result->action)
				zbx_json_addstring(&json, ZBX_PROTO_TAG_FAILED, preproc_error, ZBX_JSON_TYPE_STRING);
		}
		else
		{
			if (ZBX_VARIANT_NONE != result->value.type)
			{
				zbx_json_addstring(&json, ZBX_PROTO_TAG_RESULT, zbx_variant_value_desc(&result->value),
						ZBX_JSON_TYPE_STRING);
			}
			else
				zbx_json_addstring(&json, ZBX_PROTO_TAG_RESULT, NULL, ZBX_JSON_TYPE_NULL);
		}

		zbx_json_close(&json);
	}

	zbx_tcp_send_bytes_to(sock, json.buffer, json.buffer_size, CONFIG_TIMEOUT);

	zbx_json_free(&json);

	ret = SUCCEED;
out:
	if (FAIL == ret)
	{
		zbx_send_response(sock, ret, error, CONFIG_TIMEOUT);
		zbx_free(error);
	}

	zbx_free(preproc_error);
	zbx_free(error);
	zbx_free(error_handler_params);
	zbx_free(step_params);
	zbx_free(last_ts);
	zbx_free(last_value);
	zbx_free(value);

	zbx_vector_ptr_clear_ext(&results, (zbx_clean_func_t)zbx_preproc_result_free);
	zbx_vector_ptr_destroy(&results);
	zbx_vector_ptr_clear_ext(&steps, (zbx_clean_func_t)zbx_preproc_op_free);
	zbx_vector_ptr_destroy(&steps);

	return ret;
}
