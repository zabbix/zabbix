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
	const char	*host = zbx_mock_get_parameter_string("in.host");
	int		rc_exp, rc_ret;
	char		*error = NULL;

	ZBX_UNUSED(state);

	rc_exp = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.return"));
	if (FAIL == (rc_ret = zbx_check_prototype_hostname(host, &error)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "zbx_check_prototype_hostname() failed: %s", error);
		zbx_free(error);
	}

	zbx_mock_assert_int_eq("return value", rc_exp, rc_ret);
}
