/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
#include "dbcache.h"

typedef struct
{
	char	*expression;
	double	value;
	char	error[MAX_STRING_LEN];
}
zbx_expressions_evaluate_result_t;

static int	trapper_parse_expressions_evaluate(const struct zbx_json_parse *jp, zbx_vector_ptr_t *expressions,
				char **error)
{
	char			buffer[MAX_STRING_LEN], *step_params = NULL, *error_handler_params = NULL;
	const char		*ptr;
	zbx_user_t		user;
	int			ret = FAIL;
	struct zbx_json_parse	jp_data, jp_expressions;

	if (FAIL == zbx_json_value_by_name(jp, ZBX_PROTO_TAG_SID, buffer, sizeof(buffer), NULL) ||
			SUCCEED != DBget_user_by_active_session(buffer, &user) || USER_TYPE_ZABBIX_ADMIN > user.type)
	{
		*error = zbx_strdup(NULL, "Permission denied.");
		goto out;
	}

	if (FAIL == zbx_json_brackets_by_name(jp, ZBX_PROTO_TAG_DATA, &jp_data))
	{
		*error = zbx_strdup(NULL, "Missing data field.");
		goto out;
	}

	if (FAIL == zbx_json_brackets_by_name(&jp_data, ZBX_PROTO_TAG_EXPRESSIONS, &jp_expressions))
	{
		*error = zbx_strdup(NULL, "Missing expressions field.");
		goto out;
	}

	for (ptr = NULL; NULL != (ptr = zbx_json_next_value(&jp_expressions, ptr, buffer, sizeof(buffer), NULL));)
	{
		zabbix_log(LOG_LEVEL_INFORMATION, "EXPRESSIONS_PARSE NEXT 444: ->%s<-",buffer);
		zbx_vector_ptr_append(expressions, zbx_strdup(NULL, buffer));
	}

	ret = SUCCEED;
	zbx_free(step_params);
out:
	if (FAIL == ret)
		zbx_vector_ptr_clear_ext(expressions, (zbx_clean_func_t)zbx_ptr_free);

	zbx_free(step_params);
	zbx_free(error_handler_params);

	return ret;
}



static int	trapper_expressions_evaluate_run(const struct zbx_json_parse *jp, struct zbx_json *json, char **error)
{
	zabbix_log(LOG_LEVEL_INFORMATION, "EXPRESSIONS_EVALUATE RUN FUNC 222");

	char			*evaluate_error = NULL;
	int			ret = FAIL, i;
	unsigned char		value_type;
	zbx_vector_ptr_t	expressions, results, history;
	zbx_timespec_t		ts[2];
	zbx_expressions_evaluate_result_t	*result;

	zbx_vector_ptr_create(&expressions);
	zbx_vector_ptr_create(&results);

	if (FAIL == trapper_parse_expressions_evaluate(jp,  &expressions, error))
		goto out;

	zbx_vector_ptr_clear_ext(&results, (zbx_clean_func_t)zbx_ptr_free);

	for (int ii = 0; ii < expressions.values_num; ii++)
	{
		double expr_result;
		zbx_vector_ptr_t	unknown_msgs;

		result = (zbx_expressions_evaluate_result_t *)zbx_malloc(NULL,
				sizeof(zbx_expressions_evaluate_result_t));
		zbx_vector_ptr_append(&results, result);

		result->expression = zbx_strdup(NULL, expressions.values[ii]);
		(result->error)[0]='\0';
		if (SUCCEED != evaluate(&expr_result, expressions.values[ii], result->error, sizeof(result->error),
				&unknown_msgs))
		{
			zabbix_log(LOG_LEVEL_INFORMATION, "BADGER");
			continue;
		}
		result->value = expr_result;
	}

	zbx_json_addstring(json, ZBX_PROTO_TAG_RESPONSE, "success", ZBX_JSON_TYPE_STRING);
	zbx_json_addarray(json, ZBX_PROTO_TAG_DATA);


	for (i = 0; i < results.values_num; i++)
	{
		result = (zbx_expressions_evaluate_result_t *)results.values[i];

		zbx_json_addobject(json, NULL);
		zbx_json_addstring(json, ZBX_PROTO_TAG_EXPRESSION, result->expression, ZBX_JSON_TYPE_STRING);

		if (NULL != result->error && 0 != strlen(result->error))
		{
			zbx_json_addstring(json, ZBX_PROTO_TAG_ERROR, result->error, ZBX_JSON_TYPE_STRING);
		}
		else
		{
			zbx_uint64_t res = (ZBX_INFINITY == result->value ||
					SUCCEED == zbx_double_compare(result->value, 0.0)) ? 0 : 1;
			zbx_json_adduint64(json, ZBX_PROTO_TAG_VALUE, res);

		}
		zbx_json_close(json);
	}

	zbx_json_close(json);

	ret = SUCCEED;
out:

	zbx_free(evaluate_error);

	zbx_vector_ptr_clear_ext(&expressions, (zbx_clean_func_t)zbx_ptr_free);
	zbx_vector_ptr_destroy(&expressions);
	zbx_vector_ptr_clear_ext(&results, (zbx_clean_func_t)zbx_ptr_free);
	zbx_vector_ptr_destroy(&results);

	return ret;
}


int	zbx_trapper_expressions_evaluate(zbx_socket_t *sock, const struct zbx_json_parse *jp)
{
	char		*error = NULL;
	int		ret;
	struct zbx_json	json;

	zbx_json_init(&json, 1024);

	if (SUCCEED == (ret = trapper_expressions_evaluate_run(jp, &json, &error)))
	{
		zbx_tcp_send_bytes_to(sock, json.buffer, json.buffer_size, CONFIG_TIMEOUT);
	}
	else
	{
		zbx_send_response(sock, ret, error, CONFIG_TIMEOUT);
		zbx_free(error);
	}

	zbx_json_free(&json);

	return ret;
}
