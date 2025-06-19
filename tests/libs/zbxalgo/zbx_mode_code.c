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

#include "zbxalgo.h"

void	zbx_mock_test_entry(void **state)
{
	zbx_mode_t	mode;
	char		*mode_str = zbx_strdup(NULL, zbx_mock_get_parameter_string("in.str_mode"));
	char		*error = NULL;
	int		exp_result = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.return"));
	const char	*mode_str_exp = zbx_mock_get_parameter_string("out.mode_str_exp");
	char		mode_str_out[8];

	ZBX_UNUSED(state);

	int		result = zbx_mode_code(mode_str, &mode, &error);

	switch (mode)
	{
		case MODE_VALUE:
			zbx_strlcpy(mode_str_out, "VALUE", sizeof(mode_str_out));
			break;
		case MODE_MAX:
			zbx_strlcpy(mode_str_out, "MAX", sizeof(mode_str_out));
			break;
		case MODE_MIN:
			zbx_strlcpy(mode_str_out, "MIN", sizeof(mode_str_out));
			break;
		case MODE_DELTA:
			zbx_strlcpy(mode_str_out, "DELTA", sizeof(mode_str_out));
			break;
		case MODE_AVG:
			zbx_strlcpy(mode_str_out, "AVG", sizeof(mode_str_out));
			break;
		default:
			fail_msg("Unknown mode");
			break;
	}

	zbx_mock_assert_int_eq("return value", exp_result, result);
	zbx_mock_assert_str_eq("return value", mode_str_exp, mode_str_out);
	zbx_free(mode_str);
	zbx_free(error);
}
