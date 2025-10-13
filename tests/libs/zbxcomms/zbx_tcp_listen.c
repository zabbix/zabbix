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
	const char	*listen_ip = zbx_mock_get_parameter_string("in.ip"),
			*ipv = zbx_mock_get_parameter_string("in.ipv");
	unsigned short	listen_port = (unsigned short)zbx_mock_get_parameter_uint64("in.port");
	int		result, timeout = zbx_mock_get_parameter_int("in.timeout"),
			config_tcp_max_backlog_size = zbx_mock_get_parameter_int("in.backlog_size"),
			exp_result = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.result"));

	ZBX_UNUSED(state);

	result = zbx_tcp_listen(&s, listen_ip, listen_port, timeout, config_tcp_max_backlog_size);


#ifdef HAVE_IPV6
	if (0 == strcmp(ipv, "ipv6"))
	{
		result = zbx_tcp_listen(&s, listen_ip, listen_port, timeout, config_tcp_max_backlog_size);
		printf("Error is: %s\n", zbx_socket_strerror());
		printf("Errno is: %d\n", errno);
		zbx_mock_assert_int_eq("return value:", exp_result, result);
	}
#endif

	if (0 == strcmp(ipv, "ipv4"))
	{
		result = zbx_tcp_listen(&s, listen_ip, listen_port, timeout, config_tcp_max_backlog_size);
		zbx_mock_assert_int_eq("return value:", exp_result, result);
	}

	zbx_tcp_unlisten(&s);
}
