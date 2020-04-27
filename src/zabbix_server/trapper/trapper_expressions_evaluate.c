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
#include "zbxserver.h"
#include "trapper_expressions_evaluate.h"

static int	trapper_parse_expressions_evaluate(const struct zbx_json_parse *jp, zbx_vector_ptr_t *expressions,
				char **error)
{
	char			buffer[MAX_STRING_LEN];
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
		zbx_vector_ptr_append(expressions, zbx_strdup(NULL, buffer));
	}

	ret = SUCCEED;
out:
	return ret;
}

static int	trapper_expressions_evaluate_run(const struct zbx_json_parse *jp, struct zbx_json *json, char **error)
{
	int					ret = FAIL, i;
	zbx_vector_ptr_t			expressions;

	zbx_vector_ptr_create(&expressions);

	if (FAIL == trapper_parse_expressions_evaluate(jp,  &expressions, error))
		goto out;

	zbx_json_addstring(json, ZBX_PROTO_TAG_RESPONSE, "success", ZBX_JSON_TYPE_STRING);
	zbx_json_addarray(json, ZBX_PROTO_TAG_DATA);

	for (i = 0; i < expressions.values_num; i++)
	{
		double			expr_result;
		zbx_vector_ptr_t	unknown_msgs;
		char			evaluate_error[MAX_STRING_LEN];

		evaluate_error[0] = '\0';

		zbx_json_addobject(json, NULL);
		zbx_json_addstring(json, ZBX_PROTO_TAG_EXPRESSION, expressions.values[i], ZBX_JSON_TYPE_STRING);

		if (SUCCEED != evaluate(&expr_result, expressions.values[i], evaluate_error, sizeof(evaluate_error),
			&unknown_msgs))
		{
			zbx_json_addstring(json, ZBX_PROTO_TAG_ERROR, evaluate_error, ZBX_JSON_TYPE_STRING);
		}
		else
		{
			zbx_uint64_t res = (ZBX_INFINITY == expr_result ||
					SUCCEED == zbx_double_compare(expr_result, 0.0)) ? 0 : 1;
			zbx_json_adduint64(json, ZBX_PROTO_TAG_VALUE, res);

		}
		zbx_json_close(json);
	}

	zbx_json_close(json);

	ret = SUCCEED;
out:
	zbx_vector_ptr_clear_ext(&expressions, (zbx_clean_func_t)zbx_ptr_free);
	zbx_vector_ptr_destroy(&expressions);

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
