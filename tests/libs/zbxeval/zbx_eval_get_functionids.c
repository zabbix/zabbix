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

#include "zbxcommon.h"
#include "zbxeval.h"
#include "mock_eval.h"

static int	compare_vectors_uint64(const zbx_vector_uint64_t *v1, const zbx_vector_uint64_t *v2)
{
	if (v1->values_num != v2->values_num)
		return FAIL;

	for (int i = 0; i < v1->values_num; i++)
	{
		if (v1->values[i] != v2->values[i])
			return FAIL;
	}

	return SUCCEED;
}

void	zbx_mock_test_entry(void **state)
{
	int			returned_ret;
	zbx_eval_context_t	ctx;
	char			*error = NULL;
	zbx_uint64_t		rules;
	zbx_vector_uint64_t	functionids;
int ret = FAIL;


	ZBX_UNUSED(state);

	rules = mock_eval_read_rules("in.rules");
	returned_ret = zbx_eval_parse_expression(&ctx, zbx_mock_get_parameter_string("in.expression"), rules, &error);

	if (SUCCEED != returned_ret)
		printf("ERROR: %s\n", error);
	else
		mock_dump_stack(&ctx);

	zbx_vector_uint64_create(&functionids);

	zbx_eval_get_functionids(&ctx, &functionids);

	for (int i = 0; i < functionids.values_num; i++)
	{
		printf("vector members: %ld\n", functionids.values[i]);
	}

	zbx_mock_assert_int_eq("returned value", returned_ret, ret);
	zbx_eval_clear(&ctx);
	zbx_free(error);

}