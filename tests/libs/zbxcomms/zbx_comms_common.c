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

#include "zbx_comms_common.h"
#include <openssl/ssl.h>

int	__wrap_SSL_write(SSL *ssl, const void *buf, int num);
ssize_t	__wrap_write(int fd, const void *buf, size_t n);

ssize_t	__wrap_write(int fd, const void *buf, size_t n)
{
	ZBX_UNUSED(fd);
	ZBX_UNUSED(buf);

	if (SUCCEED == zbx_mock_parameter_exists("in.write_error"))
	{
		errno = EPERM;

		return -1;
	}
	else
		return (ssize_t)n;
}

int	__wrap_SSL_write(SSL *ssl, const void *buf, int num)
{
	ZBX_UNUSED(ssl);
	ZBX_UNUSED(buf);

	if (SUCCEED == zbx_mock_parameter_exists("in.ssl_write_error"))
		return 0;
	else if (SUCCEED == zbx_mock_parameter_exists("in.ssl_write_fail"))
		return -1;
	else
		return num;
}

void	set_nonblocking_error(void)
{
	if (SUCCEED == zbx_mock_parameter_exists("in.socket_had_blocking_error"))
		errno = EPERM;
	else
		errno = EINTR;
}
