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
#include "zbxlog.h"
#include "mock_eval.h"

void	zbx_mock_test_entry(void **state)
{
	zbx_eval_context_t	ctx;
	char			*error = NULL, *filter = NULL;
	const char		*filter_exp, *group;
	zbx_vector_str_t	groups;
	int			index = 0;
	zbx_mock_handle_t	hgroups, hgroup;
	zbx_mock_error_t	err;

	ZBX_UNUSED(state);

	zbx_vector_str_create(&groups);

	if (SUCCEED != zbx_eval_parse_expression(&ctx, zbx_mock_get_parameter_string("in.expression"),
				ZBX_EVAL_PARSE_QUERY_EXPRESSION, &error))
	{
		fail_msg("failed to parse expression: %s", error);
	}

	zbx_eval_prepare_filter(&ctx);

	if (SUCCEED != zbx_eval_get_group_filter(&ctx, &groups, &filter, &error))
		fail_msg("failed to get group filter: %s", error);


	filter_exp = zbx_mock_get_parameter_string("out.filter");

	if (NULL == filter)
	{
		if ('\0' != *filter_exp)
			fail_msg("got empty filter while expected %s", filter_exp);
	}
	else
		zbx_mock_assert_str_eq("group filter", filter_exp, filter);

	hgroups = zbx_mock_get_parameter_handle("out.groups");
	while (ZBX_MOCK_END_OF_VECTOR != (err = (zbx_mock_vector_element(hgroups, &hgroup))))
	{
		if (index >= groups.values_num)
			fail_msg("got %d groups while expected more", groups.values_num);

		if (ZBX_MOCK_SUCCESS != err || ZBX_MOCK_SUCCESS != (err = zbx_mock_string(hgroup, &group)))
			fail_msg("Cannot read group #%d: %s", index, zbx_mock_error_string(err));

		zbx_mock_assert_str_eq("group values", group, groups.values[index++]);
	}

	if (index != groups.values_num)
		fail_msg("got %d groups while expected %d", groups.values_num, index);

	zbx_vector_str_clear_ext(&groups, zbx_str_free);
	zbx_vector_str_destroy(&groups);
	zbx_free(filter);

	zbx_free(error);
	zbx_eval_clear(&ctx);
}
