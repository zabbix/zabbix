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

int	__wrap_SSL_write(SSL *ssl, const void *buf, int num);
ssize_t	__wrap_write(int fd, const void *buf, size_t n);

int	__wrap_SSL_write(SSL *ssl, const void *buf, int num)
{
	ZBX_UNUSED(ssl);
	ZBX_UNUSED(buf);

	if (SUCCEED == zbx_mock_parameter_exists("in.ssl_write_error"))
		return 0;
	else
		return num;
}

ssize_t	__wrap_write(int fd, const void *buf, size_t n)
{
	ZBX_UNUSED(fd);
	ZBX_UNUSED(buf);

	if (SUCCEED == zbx_mock_parameter_exists("in.write_error"))
		return FAIL;
	else
		return n;
}

void	set_socket_blocking_error(const char *err)
{
	if (0 == strcmp(err, "yes"))
		errno = EPERM;
	else
		errno = EINTR;
}

void	zbx_mock_test_entry(void **state)
{
	zbx_socket_t	s;
	char		*error, *exp_error;
	ssize_t		offset;
	int		exp_offset = zbx_mock_get_parameter_int("out.result");
	size_t		len = zbx_mock_get_parameter_uint64("in.len");
	char		buf[ZBX_MAX_HOSTNAME_LEN];

	ZBX_UNUSED(state);

	zbx_socket_clean(&s);
	mock_poll_set_mode_from_param(zbx_mock_get_parameter_string("in.poll_mode"));
	set_socket_blocking_error(zbx_mock_get_parameter_string("in.socket_blocking_error"));

	offset = zbx_tcp_write(&s, buf, len, NULL);
	zbx_mock_assert_int_eq("return value:", exp_offset, offset);
}
