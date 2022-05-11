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
#include "zbxmockutil.h"

void	zbx_mock_test_entry(void **state)
{
	const char	*params, *param;
	int		num;
	char		*rvalue;

	ZBX_UNUSED(state);

	params = zbx_mock_get_parameter_string("in.params");
	num = (int)zbx_mock_get_parameter_uint64("in.num");
	param = zbx_mock_get_parameter_string("out.param");

	rvalue = zbx_function_get_param_dyn(params, num);

	if (NULL != rvalue )
	{
		if (0 != strcmp(param, rvalue))
			fail_msg("Got '%s' instead of '%s' as a value.", rvalue, param);
	}
	else if (0 != strcmp("NULL", param))
		fail_msg("Got 'NULL' response instead of '%s' as a value.", param);

	zbx_free(rvalue);
}
