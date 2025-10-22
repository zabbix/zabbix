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

#include "zbx_comms_common.h"

ssize_t	__wrap_sendto(int fd, const void *buf, size_t n, int flags, const struct sockaddr *addr, socklen_t addr_len);

ssize_t	__wrap_sendto(int fd, const void *buf, size_t n, int flags, const struct sockaddr *addr, socklen_t addr_len)
{
	ZBX_UNUSED(fd);
	ZBX_UNUSED(buf);
	ZBX_UNUSED(n);
	ZBX_UNUSED(flags);
	ZBX_UNUSED(addr);
	ZBX_UNUSED(addr_len);

	int	ret = zbx_mock_get_parameter_int("in.sendto_return");

	return ret;
}

void	zbx_mock_test_entry(void **state)
{
	zbx_socket_t	s;
	const char	*data = zbx_mock_get_parameter_string("in.data");
	int		exp_result = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.result"));
	size_t		data_len = zbx_mock_get_parameter_uint64("in.data_len");

	ZBX_UNUSED(state);

	zbx_comms_mock_poll_set_mode_from_param(zbx_mock_get_parameter_string("in.poll_mode"));
	set_nonblocking_error();

	/* 0 used for timeout - poll() is wrapped for tests */
	int	result = zbx_udp_send(&s, data, data_len, 0);

	zbx_mock_assert_int_eq("return value:", exp_result, result);
}
