/*
** Copyright (C) 2001-2024 Zabbix SIA
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
#include "zbxmockutil.h"
#include "zbxmockassert.h"

#include "zbxexpr.h"

void	zbx_mock_test_entry(void **state)
{
	const char	*source = zbx_mock_get_parameter_string("in.source");
	int		exp_return = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.return"));
	char		*result = NULL;

	ZBX_UNUSED(state);

	result = zbx_malloc(result, MAX_STRING_LEN);
	int	freturn = zbx_url_decode(source, &result);

	zbx_mock_assert_int_eq("return value", exp_return, freturn);

	if (SUCCEED == zbx_mock_parameter_exists("out.result"))
	{
		const char	*exp_result = zbx_mock_get_parameter_string("out.result");
		zbx_mock_assert_str_eq("return value str", exp_result, result);
	}
}
