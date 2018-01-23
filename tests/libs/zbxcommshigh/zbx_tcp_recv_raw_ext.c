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
#include "zbxmockhelper.h"

#include "common.h"
#include "comms.h"

void	zbx_mock_test_entry(void **state)
{
	char		*buffer;
	zbx_socket_t	s;
	ssize_t		received, expected;

	ZBX_UNUSED(state);

	if (SUCCEED != zbx_tcp_connect(&s, NULL, "127.0.0.1", 10050, 0, ZBX_TCP_SEC_UNENCRYPTED, NULL, NULL))
		fail_msg("Failed to connect");

	if (zbx_read_yaml_expected_ret() != SUCCEED_OR_FAIL((received = zbx_tcp_recv_raw_ext(&s, 0))))
		fail_msg("Unexpected return code '%s'", zbx_result_string(SUCCEED_OR_FAIL(received)));

	if (FAIL == SUCCEED_OR_FAIL(received))
	{
		zbx_tcp_close(&s);
		return;
	}

	if (received != (expected = zbx_read_yaml_expected_uint64("number of bytes")))
		fail_msg("Expected bytes to receive:" ZBX_FS_UI64 " received:" ZBX_FS_UI64, expected, received);

	buffer = zbx_yaml_assemble_binary_sequence("fragments", received);

	if (0 != memcmp(buffer, s.buffer, received))
		fail_msg("Received message mismatch expected");

	zbx_tcp_close(&s);
	zbx_free(buffer);
}
