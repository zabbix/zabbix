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

static int	accept_return, recv_counter = 0;

int	__wrap_accept(int sockfd, struct sockaddr *addr, socklen_t *addrlen);
int	__wrap_fcntl (int __fd, int __cmd, ...);
int	__wrap_getpeername(int fd, struct sockaddr *addr, socklen_t *len);
ssize_t	__wrap_recv (int d, void *buf, size_t n, int flags);

int __wrap_accept(int sockfd, struct sockaddr *addr, socklen_t *addrlen)
{
	ZBX_UNUSED(addr);
	ZBX_UNUSED(addrlen);

	return accept_return;
}

int	__wrap_fcntl (int fd, int cmd, ...)
{
	ZBX_UNUSED(fd);
	ZBX_UNUSED(cmd);

	return SUCCEED;
}

int	__wrap_getpeername(int fd, struct sockaddr *addr, socklen_t *len)
{
	ZBX_UNUSED(fd);
	ZBX_UNUSED(addr);
	ZBX_UNUSED(len);

	return SUCCEED;
}

ssize_t	__wrap_recv (int d, void *buf, size_t n, int flags)
{
	ZBX_UNUSED(d);
	ZBX_UNUSED(n);
	ZBX_UNUSED(flags);

	((unsigned char *)buf)[0] = 0x16; // TLS

	if (0 == recv_counter)
	{
		recv_counter++;

		return FAIL;
	}
	else
		return 1;
}

void	zbx_mock_test_entry(void **state)
{
	zbx_socket_t	s;
	unsigned int	tls_accept = zbx_mock_get_parameter_uint32("in.tls_accept");
	int		result, poll_timeout = zbx_mock_get_parameter_int("in.poll_timeout"),
			exp_result = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.result"));
	char		*tls_listen = NULL;
	const char	*unencrypted_allowed_ip,
			*poll_mode = zbx_mock_get_parameter_string("in.poll_mode");

	ZBX_UNUSED(state);

	errno = 0;
	s.num_socks = zbx_mock_get_parameter_int("in.num_socks");

	if (SUCCEED == zbx_mock_parameter_exists("in.fake_sockfd"))
		accept_return = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("in.fake_sockfd"));
	else
		accept_return = FAIL;

	if (SUCCEED == zbx_mock_parameter_exists("in.unencrypted_allowed_ip"))
		unencrypted_allowed_ip = zbx_mock_get_parameter_string("in.unencrypted_allowed_ip");
	else
		unencrypted_allowed_ip = NULL;

	mock_poll_set_mode_from_param(poll_mode);
	set_nonblocking_error();
	result = zbx_tcp_accept(&s, tls_accept, poll_timeout, tls_listen, unencrypted_allowed_ip);

	zbx_mock_assert_int_eq("return value:", exp_result, result);
}
