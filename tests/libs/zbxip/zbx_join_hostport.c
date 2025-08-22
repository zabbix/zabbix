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
#include "zbxmockutil.h"
#include "zbxmockassert.h"

#include "zbxip.h"

void	zbx_mock_test_entry(void **state)
{
	const char	*host = zbx_mock_get_parameter_string("in.host");
	unsigned int	port = zbx_mock_get_parameter_uint32("in.port");
	const char	*expected = zbx_mock_get_parameter_string("out.hostport");
	char		result[MAX_STRING_LEN];

	ZBX_UNUSED(state);

	zbx_join_hostport(result, sizeof(result), host, (unsigned short)port);

	zbx_mock_assert_str_eq("joined hostport", expected, result);
}
