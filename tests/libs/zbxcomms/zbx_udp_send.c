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
#include "zbxcomms.h"

void	zbx_mock_test_entry(void **state)
{
	zbx_socket_t	s;
	const char	*source_ip = zbx_mock_get_parameter_string("in.source_ip"),
			*ip = zbx_mock_get_parameter_string("in.ip");
	unsigned short	port = zbx_mock_get_parameter_uint64("in.port");
	int		result, timeout = zbx_mock_get_parameter_int("in.timeout"),
			exp_result = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.result"));

	ZBX_UNUSED(state);

	result = zbx_udp_connect(&s, source_ip, ip, port, timeout);

	zbx_mock_assert_int_eq("return value:", exp_result, result);

}
