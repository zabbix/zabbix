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
#include "zbxdbwrap.h"

void	zbx_mock_test_entry(void **state)
{
	int		ret,
			exp_result = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.result"));
	const char	*macro = zbx_mock_get_parameter_string("in.macro"),
			*itemquery = zbx_mock_get_parameter_string("in.itemquery"),
			*macro_data = zbx_mock_get_parameter_string("in.macro_data");
	char		*out = NULL, **error = NULL, *error_val = NULL;
	size_t		len;

	ZBX_UNUSED(state);

	len = strlen(itemquery);

	if (SUCCEED == zbx_mock_parameter_exists("in.error"))
		error = &error_val;

	ret = zbx_eval_query_subtitute_user_macros(itemquery, len, &out, error, query_macro_resolver, macro,
			macro_data);

	zbx_mock_assert_int_eq("return value:", exp_result, ret);

	if (NULL != out)
	{
		char	*exp_out = zbx_strdup(NULL ,zbx_mock_get_parameter_string("out.string"));

		zbx_mock_assert_str_eq("return out:", exp_out, out);
		zbx_free(exp_out);
	}

	if (SUCCEED == zbx_mock_parameter_exists("in.error"))
		zbx_free(error_val);
	else
		zbx_free(error);

	zbx_free(out);
}
