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
#include "zbxmockhelper.h"

#include "zbxcommon.h"
#include "zbxcomms.h"

void	zbx_mock_test_entry(void **state)
{
#define ZBX_TCP_HEADER_DATALEN_LEN	13

	char		*buffer;
	zbx_socket_t	s;
	ssize_t		received;
	size_t		out_fragments;
	int		expected_ret, offset = ZBX_TCP_HEADER_DATALEN_LEN;

	ZBX_UNUSED(state);

	zbx_mock_assert_result_eq("zbx_tcp_connect() return code", SUCCEED,
			zbx_tcp_connect(&s, NULL, "127.0.0.1", 10050, 0, ZBX_TCP_SEC_UNENCRYPTED, NULL, NULL));

	expected_ret = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.return"));
	received = zbx_tcp_recv_ext(&s, 0, ZBX_TCP_LARGE);

	if (FAIL == expected_ret)
	{
		zbx_mock_assert_result_eq("zbx_tcp_recv_ext() return code", FAIL, received);
		zbx_tcp_close(&s);
		return;
	}

	zbx_mock_assert_result_eq("zbx_tcp_recv_ext() return code", SUCCEED, SUCCEED_OR_FAIL(received));
	zbx_mock_assert_uint64_eq("Received bytes", zbx_mock_get_parameter_uint64("out.bytes"), received);

	if (0 == received)
		return;

	out_fragments = (size_t)received;
	buffer = zbx_yaml_assemble_binary_sequence("out.fragments", &out_fragments);

	if (0 != (ZBX_TCP_LARGE & s.protocol))
		offset += 8;

	if (0 != memcmp(buffer + offset, s.buffer, received - offset))
		fail_msg("Received message mismatch expected");

	zbx_tcp_close(&s);
	zbx_free(buffer);
#undef ZBX_TCP_HEADER_DATALEN_LEN
}
