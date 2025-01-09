/*
** Copyright (C) 2001-2025 Zabbix SIA
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

#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "zbxmockassert.h"
#include "zbxmockutil.h"

#include "zbxtagfilter.h"
#include "zbxalgo.h"
#include "zbxdbhigh.h"
#include "zbxexpr.h"

static int	get_eval_type(const char *eval_type_str)
{
	int	eval_type;

	if (0 == strcasecmp(eval_type_str, "AND/OR"))
	{
		eval_type = ZBX_CONDITION_EVAL_TYPE_AND_OR;
	}
	else if (0 == strcasecmp(eval_type_str, "OR"))
	{
		eval_type = ZBX_CONDITION_EVAL_TYPE_OR;
	}
	else
	{
		fail_msg("unknown eval_type value '%s'", eval_type_str);

		return 0;
	}

	return eval_type;
}

static const char	*get_eval_type_str(int eval_type)
{
	const char	*eval_type_str;

	switch (eval_type)
	{
		case ZBX_CONDITION_EVAL_TYPE_AND_OR:
			eval_type_str = "ZBX_CONDITION_EVAL_TYPE_AND_OR";
			break;
		case ZBX_CONDITION_EVAL_TYPE_OR:
			eval_type_str = "ZBX_CONDITION_EVAL_TYPE_OR";
			break;
		default:
			eval_type_str = "unknown eval_type value";
			fail_msg("unknown eval_type value '%s'", eval_type_str);
			break;
	}

	return eval_type_str;
}

static unsigned char	get_operator(const char *op_str)
{
	unsigned char op;

	if (0 == strcasecmp(op_str, "EQUAL"))
	{
		op = ZBX_CONDITION_OPERATOR_EQUAL;
	}
	else if (0 == strcasecmp(op_str, "NOT EQUAL"))
	{
		op = ZBX_CONDITION_OPERATOR_NOT_EQUAL;
	}
	else if (0 == strcasecmp(op_str, "LIKE"))
	{
		op = ZBX_CONDITION_OPERATOR_LIKE;
	}
	else if (0 == strcasecmp(op_str, "NOT LIKE"))
	{
		op = ZBX_CONDITION_OPERATOR_NOT_LIKE;
	}
	else if (0 == strcasecmp(op_str, "EXIST"))
	{
		op = ZBX_CONDITION_OPERATOR_EXIST;
	}
	else if (0 == strcasecmp(op_str, "NOT EXIST"))
	{
		op = ZBX_CONDITION_OPERATOR_NOT_EXIST;
	}
	else
	{
		fail_msg("unknown operator value '%s'", op_str);

		return 0;
	}

	return op;
}

static const char	*get_operator_str(unsigned char op)
{
	const char	*op_str;

	switch (op)
	{
		case ZBX_CONDITION_OPERATOR_EQUAL:
			op_str = "ZBX_CONDITION_OPERATOR_EQUAL";
			break;
		case ZBX_CONDITION_OPERATOR_NOT_EQUAL:
			op_str = "ZBX_CONDITION_OPERATOR_NOT_EQUAL";
			break;
		case ZBX_CONDITION_OPERATOR_LIKE:
			op_str = "ZBX_CONDITION_OPERATOR_LIKE";
			break;
		case ZBX_CONDITION_OPERATOR_NOT_LIKE:
			op_str = "ZBX_CONDITION_OPERATOR_NOT_LIKE";
			break;
		case ZBX_CONDITION_OPERATOR_EXIST:
			op_str = "ZBX_CONDITION_OPERATOR_EXIST";
			break;
		case ZBX_CONDITION_OPERATOR_NOT_EXIST:
			op_str = "ZBX_CONDITION_OPERATOR_NOT_EXIST";
			break;
		default:
			op_str = "unknown operator value";
			fail_msg("unknown operator value '%d'", op);
			break;
	}

	return op_str;
}

static void	get_mtags(zbx_mock_handle_t handle, zbx_vector_match_tags_ptr_t *tags)
{
	zbx_mock_error_t	mock_err;
	zbx_mock_handle_t	htag;

	while (ZBX_MOCK_END_OF_VECTOR != (mock_err = (zbx_mock_vector_element(handle, &htag))))
	{
		zbx_match_tag_t	*tag;

		tag = (zbx_match_tag_t *)zbx_malloc(NULL, sizeof(*tag));
		tag->op = get_operator(zbx_mock_get_object_member_string(htag, "operator"));
		tag->tag = zbx_strdup(NULL, zbx_mock_get_object_member_string(htag, "tag"));
		tag->value = zbx_strdup(NULL, zbx_mock_get_object_member_string(htag, "value"));

		zbx_vector_match_tags_ptr_append(tags, tag);
	}

	zbx_vector_match_tags_ptr_sort(tags, zbx_compare_match_tags);
}

static void	get_etags(zbx_mock_handle_t handle, zbx_vector_tags_ptr_t *tags)
{
	zbx_mock_error_t	mock_err;
	zbx_mock_handle_t	htag;

	while (ZBX_MOCK_END_OF_VECTOR != (mock_err = (zbx_mock_vector_element(handle, &htag))))
	{
		zbx_tag_t	*tag;

		tag = (zbx_tag_t *)zbx_malloc(NULL, sizeof(*tag));
		tag->tag = zbx_strdup(NULL, zbx_mock_get_object_member_string(htag, "tag"));
		tag->value = zbx_strdup(NULL, zbx_mock_get_object_member_string(htag, "value"));

		zbx_vector_tags_ptr_append(tags, tag);
	}

	zbx_vector_tags_ptr_sort(tags, zbx_compare_tags);
}

static void	dump_debug_info(int eval_type, const zbx_vector_match_tags_ptr_t *mtags,
		const zbx_vector_tags_ptr_t *etags, int expected_ret, int returned_ret)
{
	printf("\n");

	printf("eval_type = %s\n", get_eval_type_str(eval_type));
	printf("\n");

	printf("mtags (%d):\n", mtags->values_num);
	for (int i = 0; i < mtags->values_num; i++)
	{
		printf("- tag: '%s', value: '%s', op: %s\n",
				mtags->values[i]->tag, mtags->values[i]->value, get_operator_str(mtags->values[i]->op));
	}
	printf("\n");

	printf("etags (%d):\n", etags->values_num);
	for (int i = 0; i < etags->values_num; i++)
	{
		printf("- tag: '%s', value: '%s'\n", etags->values[i]->tag, etags->values[i]->value);
	}
	printf("\n");

	printf("expected_ret = %d\n", expected_ret);
	printf("returned_ret = %d\n", returned_ret);

	printf("\n");
}

void	zbx_mock_test_entry(void **state)
{
	int				eval_type;
	zbx_vector_match_tags_ptr_t	mtags;
	zbx_vector_tags_ptr_t		etags;
	int				expected_ret, returned_ret;

	ZBX_UNUSED(state);

	zbx_vector_match_tags_ptr_create(&mtags);
	zbx_vector_tags_ptr_create(&etags);

	eval_type = get_eval_type(zbx_mock_get_parameter_string("in.eval_type"));
	get_mtags(zbx_mock_get_parameter_handle("in.match_tags"), &mtags);
	get_etags(zbx_mock_get_parameter_handle("in.tags"), &etags);

	returned_ret = zbx_match_tags(eval_type, &mtags, &etags);
	expected_ret = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.return"));

	if (returned_ret != expected_ret)
	{
		dump_debug_info(eval_type, &mtags, &etags, expected_ret, returned_ret);
	}

	zbx_mock_assert_int_eq("tagfilter_match_tags return value", expected_ret, returned_ret);

	zbx_vector_match_tags_ptr_clear_ext(&mtags, zbx_match_tag_free);
	zbx_vector_match_tags_ptr_destroy(&mtags);

	zbx_vector_tags_ptr_clear_ext(&etags, zbx_free_tag);
	zbx_vector_tags_ptr_destroy(&etags);
}
