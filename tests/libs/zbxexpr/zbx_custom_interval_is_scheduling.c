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
#include "zbxmockutil.h"
#include "zbxmockassert.h"

#include "zbxexpr.h"

void	zbx_mock_test_entry(void **state)
{
	const char	*delay = zbx_mock_get_parameter_string("in.params");
	int		simple_interval,
			exp_result = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.result"));
	char		*error = NULL;

	ZBX_UNUSED(state);

	zbx_custom_interval_t	*custom_intervals = NULL;

	if (SUCCEED != zbx_interval_preproc(delay, &simple_interval, &custom_intervals, &error))
		fail_msg("Value of 'delay' is not a valid update interval: %s.", error);

	int	result = zbx_custom_interval_is_scheduling(custom_intervals);

	zbx_custom_interval_free(custom_intervals);

	zbx_mock_assert_int_eq("return value:", exp_result, result);
}
