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
#include "zbxeval.h"

static void	mock_read_token(zbx_eval_token_t *token, zbx_mock_handle_t htoken)
{
	zbx_mock_handle_t	hvalue;
	zbx_mock_error_t	err;

	token->type = (zbx_uint32_t)zbx_mock_get_object_member_uint64(htoken, "type");
	token->opt = (zbx_uint32_t)zbx_mock_get_object_member_uint64(htoken, "opt");

	if (ZBX_MOCK_SUCCESS == zbx_mock_object_member(htoken, "value", &hvalue))
	{
		const char	*value;
		zbx_uint64_t	ui64;
		int		len;

		token->loc.l = token->loc.r = 0;
		if (ZBX_MOCK_SUCCESS != (err = zbx_mock_string(hvalue, &value)))
			fail_msg("cannot read token value: %s", zbx_mock_error_string(err));

		if (SUCCEED == is_uint64(value, &ui64))
			zbx_variant_set_ui64(&token->value, ui64);
		else if (SUCCEED == zbx_number_parse(value, &len) && strlen(value) == (size_t)len)
			zbx_variant_set_dbl(&token->value, atof(value));
		else
			zbx_variant_set_str(&token->value, zbx_strdup(NULL, value));
	}
	else
	{
		zbx_variant_set_none(&token->value);
		token->loc.l = (size_t)zbx_mock_get_object_member_uint64(htoken, "left");
		token->loc.r = (size_t)zbx_mock_get_object_member_uint64(htoken, "right");
	}
}

static void	mock_read_stack(zbx_vector_eval_token_t *stack, const char *path)
{
	zbx_mock_handle_t	hstack, htoken, hrepeat;
	zbx_mock_error_t	err;

	hstack = zbx_mock_get_parameter_handle(path);

	while (ZBX_MOCK_END_OF_VECTOR != (err = (zbx_mock_vector_element(hstack, &htoken))))
	{
		zbx_eval_token_t	token;

		if (ZBX_MOCK_SUCCESS != err)
			fail_msg("cannot read token $%d", stack->values_num);

		mock_read_token(&token, htoken);
		zbx_vector_eval_token_append_ptr(stack, &token);
	}

	if (ZBX_MOCK_SUCCESS == zbx_mock_in_parameter("repeat", &hrepeat))
	{
		const char		*repeat;
		int			i, repeat_num;
		zbx_vector_eval_token_t	template;

		if (ZBX_MOCK_SUCCESS != (err = zbx_mock_string(hrepeat, &repeat)))
			fail_msg("cannot read repeat number: %s", zbx_mock_error_string(err));

		if (SUCCEED != is_uint32(repeat, &repeat_num))
			fail_msg("invalid repeat value");

		zbx_vector_eval_token_create(&template);
		zbx_vector_eval_token_append_array(&template, stack->values, stack->values_num);

		for (i = 0; i < repeat_num - 1; i++)
		{
			zbx_vector_eval_token_append_array(stack, template.values, template.values_num);
		}

		zbx_vector_eval_token_destroy(&template);
	}
}

static void	mock_compare_stacks(const zbx_vector_eval_token_t *stack1, const zbx_vector_eval_token_t *stack2)
{
	int	i;

	if (stack1->values_num != stack2->values_num)
		fail_msg("serialized %d tokens while deserialized %d", stack1->values_num, stack2->values_num);

	for (i = 0; i < stack1->values_num; i++)
	{
		char	msg[1024];

		zbx_snprintf(msg, sizeof(msg), "token #%d: ", i);

		zbx_mock_assert_int_eq(msg, stack1->values[i].type, stack2->values[i].type);
		zbx_mock_assert_int_eq(msg, stack1->values[i].opt, stack2->values[i].opt);
		zbx_mock_assert_uint64_eq(msg, stack1->values[i].loc.l, stack2->values[i].loc.l);
		zbx_mock_assert_uint64_eq(msg, stack1->values[i].loc.r, stack2->values[i].loc.r);

		if (0 != zbx_variant_compare(&stack1->values[i].value, &stack2->values[i].value))
		{
			fail_msg("%sexpected value '%s' while got '%s'", msg,
					zbx_variant_value_desc(&stack1->values[i].value),
					zbx_variant_value_desc(&stack2->values[i].value));
		}
	}
}

void	zbx_mock_test_entry(void **state)
{
	zbx_eval_context_t	ctx1, ctx2;
	unsigned char		*data;

	ZBX_UNUSED(state);

	memset(&ctx1, 0, sizeof(ctx1));
	zbx_vector_eval_token_create(&ctx1.stack);

	memset(&ctx2, 0, sizeof(ctx2));
	zbx_vector_eval_token_create(&ctx2.stack);

	mock_read_stack(&ctx1.stack, "in.stack");

	zbx_eval_serialize(&ctx1, NULL, &data);
	zbx_eval_deserialize(&ctx2, NULL, 0, data);

	zbx_free(data);

	mock_compare_stacks(&ctx1.stack, &ctx2.stack);

	zbx_eval_clear(&ctx1);
	zbx_eval_clear(&ctx2);
}
