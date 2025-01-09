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
#define PORT_DEFAULT	80
	const char	*str = zbx_mock_get_parameter_string("in.str");
	const char	*host_out = zbx_mock_get_parameter_string("out.host");
	char		*host = NULL;
	int		ipv = zbx_mock_get_parameter_int("in.ipv");

	ZBX_UNUSED(state);

#ifndef HAVE_IPV6
	if (ZBX_IPRANGE_V4 == ipv)
#else
	if (ZBX_IPRANGE_V4 == ipv || ZBX_IPRANGE_V6 == ipv)
#endif
	{
		unsigned short	port_out = (unsigned short)zbx_mock_get_parameter_uint64("out.port");
		unsigned short	port;
		int		exp_result = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.return"));
		int		act_result = zbx_parse_serveractive_element(str, &host, &port, PORT_DEFAULT);

		zbx_mock_assert_int_eq("return value result", exp_result, act_result);

		if (NULL != host)
			zbx_mock_assert_str_eq("return value host", host, host_out);

		zbx_mock_assert_int_eq("return value port", port_out, port);
	}
	zbx_free(host);
#undef PORT_DEFAULT
}
