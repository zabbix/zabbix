/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
#include "zbxmockdata.h"

#include "common.h"

static zbx_mock_handle_t	fragments;

int	__wrap_connect(int fd, __CONST_SOCKADDR_ARG addr, socklen_t len)
{
	zbx_mock_error_t	error;

	ZBX_UNUSED(fd);
	ZBX_UNUSED(addr);
	ZBX_UNUSED(len);

	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_in_parameter("fragments", &fragments)))
		fail_msg("Cannot get fragments handle: %s", zbx_mock_error_string(error));

	return 0;
}

ssize_t	__wrap_read(int fd, void *buf, size_t nbytes)
{
	static int		remaining_length;
	static const char	*data;
	zbx_mock_error_t	error;
	zbx_mock_handle_t	fragment;
	size_t			length;

	ZBX_UNUSED(fd);

	if (0 == remaining_length)
	{
		if (ZBX_MOCK_SUCCESS != zbx_mock_vector_element(fragments, &fragment))
			return 0;	/* no more data */

		if (ZBX_MOCK_SUCCESS != (error = zbx_mock_binary(fragment, &data, &length)))
			fail_msg("Cannot read data '%s'", zbx_mock_error_string(error));
	}
	else
		length = remaining_length;

	if (nbytes < length)
	{
		remaining_length = length - nbytes;
		length = nbytes;
	}
	else
		remaining_length = 0;

	memcpy(buf, data, length);

	if (0 != remaining_length)
		data += length;

	return length;
}
