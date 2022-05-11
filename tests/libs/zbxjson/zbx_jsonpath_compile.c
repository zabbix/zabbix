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

#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "zbxmockassert.h"
#include "zbxmockutil.h"

#include "common.h"
#include "zbxjson.h"

#include "../../../src/libs/zbxjson/jsonpath.h"
#include "../../../src/libs/zbxjson/json.h"

static int	mock_str_to_segment_type(const char *segment_type)
{
	if (0 == strcmp("ZBX_JSONPATH_SEGMENT_MATCH_ALL", segment_type))
		return ZBX_JSONPATH_SEGMENT_MATCH_ALL;
	if (0 == strcmp("ZBX_JSONPATH_SEGMENT_MATCH_LIST", segment_type))
		return ZBX_JSONPATH_SEGMENT_MATCH_LIST;
	if (0 == strcmp("ZBX_JSONPATH_SEGMENT_MATCH_SLICE", segment_type))
		return ZBX_JSONPATH_SEGMENT_MATCH_RANGE;
	if (0 == strcmp("ZBX_JSONPATH_SEGMENT_MATCH_EXPRESSION", segment_type))
		return ZBX_JSONPATH_SEGMENT_MATCH_EXPRESSION;
	if (0 == strcmp("ZBX_JSONPATH_SEGMENT_FUNCTION", segment_type))
		return ZBX_JSONPATH_SEGMENT_FUNCTION;

	fail_msg("Unknown jsonpath segment type: %s", segment_type);
	return -1;
}

static void	jsonpath_token_print(char **data, size_t *data_alloc, size_t *data_offset,
		const zbx_jsonpath_token_t *token)
{
	switch (token->type)
	{
		case ZBX_JSONPATH_TOKEN_PATH_ABSOLUTE:
		case ZBX_JSONPATH_TOKEN_PATH_RELATIVE:
		case ZBX_JSONPATH_TOKEN_CONST_STR:
		case ZBX_JSONPATH_TOKEN_CONST_NUM:
			zbx_strcpy_alloc(data, data_alloc, data_offset, token->data);
			break;
		case ZBX_JSONPATH_TOKEN_PAREN_LEFT:
			zbx_strcpy_alloc(data, data_alloc, data_offset, "(");
			break;
		case ZBX_JSONPATH_TOKEN_PAREN_RIGHT:
			zbx_strcpy_alloc(data, data_alloc, data_offset, ")");
			break;
		case ZBX_JSONPATH_TOKEN_OP_PLUS:
			zbx_strcpy_alloc(data, data_alloc, data_offset, "+");
			break;
		case ZBX_JSONPATH_TOKEN_OP_MINUS:
			zbx_strcpy_alloc(data, data_alloc, data_offset, "-");
			break;
		case ZBX_JSONPATH_TOKEN_OP_MULT:
			zbx_strcpy_alloc(data, data_alloc, data_offset, "*");
			break;
		case ZBX_JSONPATH_TOKEN_OP_DIV:
			zbx_strcpy_alloc(data, data_alloc, data_offset, "/");
			break;
		case ZBX_JSONPATH_TOKEN_OP_EQ:
			zbx_strcpy_alloc(data, data_alloc, data_offset, "==");
			break;
		case ZBX_JSONPATH_TOKEN_OP_NE:
			zbx_strcpy_alloc(data, data_alloc, data_offset, "!=");
			break;
		case ZBX_JSONPATH_TOKEN_OP_GT:
			zbx_strcpy_alloc(data, data_alloc, data_offset, ">");
			break;
		case ZBX_JSONPATH_TOKEN_OP_GE:
			zbx_strcpy_alloc(data, data_alloc, data_offset, ">=");
			break;
		case ZBX_JSONPATH_TOKEN_OP_LT:
			zbx_strcpy_alloc(data, data_alloc, data_offset, "<");
			break;
		case ZBX_JSONPATH_TOKEN_OP_LE:
			zbx_strcpy_alloc(data, data_alloc, data_offset, "<=");
			break;
		case ZBX_JSONPATH_TOKEN_OP_NOT:
			zbx_strcpy_alloc(data, data_alloc, data_offset, "!");
			break;
		case ZBX_JSONPATH_TOKEN_OP_AND:
			zbx_strcpy_alloc(data, data_alloc, data_offset, "&&");
			break;
		case ZBX_JSONPATH_TOKEN_OP_OR:
			zbx_strcpy_alloc(data, data_alloc, data_offset, "||");
			break;
		case ZBX_JSONPATH_TOKEN_OP_REGEXP:
			zbx_strcpy_alloc(data, data_alloc, data_offset, "=~");
			break;
		default:
			zbx_strcpy_alloc(data, data_alloc, data_offset, "?");
			break;
	}
}

static char	*segment_data_to_str(const zbx_jsonpath_segment_t *segment)
{
	const char			*functions[] = {"unknown", "min()", "max()", "avg()", "length()", "first()",
							"sum()", "~"};
	char				*data = NULL;
	size_t				data_alloc = 0, data_offset = 0;
	int				i;
	zbx_jsonpath_list_node_t	*node;
	zbx_vector_ptr_t		nodes;

	switch (segment->type)
	{
		case ZBX_JSONPATH_SEGMENT_MATCH_ALL:
			data = zbx_strdup(NULL, "*");
			break;
		case ZBX_JSONPATH_SEGMENT_MATCH_LIST:
			zbx_vector_ptr_create(&nodes);

			/* lists are kept in reverse order, invert it for clarity */
			for (node = segment->data.list.values; NULL != node; node = node->next)
				zbx_vector_ptr_append(&nodes, node);

			for (i = nodes.values_num - 1; i >= 0; i--)
			{
				node = (zbx_jsonpath_list_node_t *)nodes.values[i];

				if (ZBX_JSONPATH_LIST_NAME == segment->data.list.type)
				{
					zbx_snprintf_alloc(&data, &data_alloc, &data_offset, "\'%s'",
							(char *)&node->data);
				}
				else
				{
					zbx_snprintf_alloc(&data, &data_alloc, &data_offset, "%d",
							*(int *)&node->data);
				}

				if (0 != i)
					zbx_chrcpy_alloc(&data, &data_alloc, &data_offset, ',');
			}

			zbx_vector_ptr_destroy(&nodes);
			break;
		case ZBX_JSONPATH_SEGMENT_MATCH_RANGE:
			if (0 != (segment->data.range.flags & 0x01))
				zbx_snprintf_alloc(&data, &data_alloc, &data_offset, "%d", segment->data.range.start);
			zbx_chrcpy_alloc(&data, &data_alloc, &data_offset, ':');
			if (0 != (segment->data.range.flags & 0x02))
				zbx_snprintf_alloc(&data, &data_alloc, &data_offset, "%d", segment->data.range.end);
			break;
		case ZBX_JSONPATH_SEGMENT_MATCH_EXPRESSION:
			for (i = 0; i < segment->data.expression.tokens.values_num; i++)
			{
				if (0 != i)
					zbx_strcpy_alloc(&data, &data_alloc, &data_offset, " , ");

				jsonpath_token_print(&data, &data_alloc, &data_offset,
						segment->data.expression.tokens.values[i]);
			}
			break;
		case ZBX_JSONPATH_SEGMENT_FUNCTION:
			zbx_strcpy_alloc(&data, &data_alloc, &data_offset, functions[segment->data.function.type]);
			break;
		default:
			data = zbx_strdup(NULL, "unknown");
			break;
	}

	return data;
}

static void	validate_segment(int index, const char *segment_type, const char *segment_data, int detached,
		const zbx_jsonpath_segment_t *segment)
{
	int	type;
	char	prefix[MAX_STRING_LEN];
	char	*data = NULL;

	zbx_snprintf(prefix, sizeof(prefix), "jsonpath segment #%d type", index + 1);
	type = mock_str_to_segment_type(segment_type);
	zbx_mock_assert_int_eq(prefix, type, segment->type);

	zbx_snprintf(prefix, sizeof(prefix), "jsonpath segment #%d detached", index + 1);
	zbx_mock_assert_int_eq(prefix, detached, segment->detached);

	data = segment_data_to_str(segment);
	zbx_snprintf(prefix, sizeof(prefix), "jsonpath segment #%d data", index + 1);
	zbx_mock_assert_str_eq(prefix, segment_data, data);
	zbx_free(data);
}

void	zbx_mock_test_entry(void **state)
{
	zbx_jsonpath_t		jsonpath;
	int			returned_ret, expected_ret, index = 0;
	zbx_mock_handle_t	hsegments, hsegment, hdetached;
	const char		*segment_data, *segment_type, *value;

	ZBX_UNUSED(state);

	/* reset json error to check if compilation will set it */
	zbx_set_json_strerror("%s", "");

	returned_ret = zbx_jsonpath_compile(zbx_mock_get_parameter_string("in.path"), &jsonpath);

	if (FAIL == returned_ret)
		printf("zbx_jsonpath_compile() error: %s\n", zbx_json_strerror());

	expected_ret = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.result"));
	zbx_mock_assert_result_eq("zbx_jsonpath_compile() return value", expected_ret, returned_ret);

	if (SUCCEED == returned_ret)
	{
		zbx_mock_assert_uint64_eq("jsonpath definite flag", zbx_mock_get_parameter_uint64("out.definite"),
				jsonpath.definite);
		hsegments = zbx_mock_get_parameter_handle("out.segments");

		while (ZBX_MOCK_SUCCESS == zbx_mock_vector_element(hsegments, &hsegment))
		{
			int	detached = 0;

			zbx_mock_assert_int_ne("Too many path segments parsed", index, jsonpath.segments_num);

			segment_type = zbx_mock_get_object_member_string(hsegment, "type");
			segment_data = zbx_mock_get_object_member_string(hsegment, "data");

			if (ZBX_MOCK_SUCCESS == zbx_mock_object_member(hsegment, "detached", &hdetached) &&
					ZBX_MOCK_SUCCESS == zbx_mock_string(hdetached, &value))
			{
				detached = atoi(value);
			}

			validate_segment(index, segment_type, segment_data, detached, &jsonpath.segments[index]);
			index++;
		}

		zbx_mock_assert_int_eq("Not enough path segments parsed", index, jsonpath.segments_num);

		zbx_jsonpath_clear(&jsonpath);
	}
	else
		zbx_mock_assert_str_ne("zbx_jsonpath_compile() error", "", zbx_json_strerror());
}
