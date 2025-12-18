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
	int		exp_result = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.result"));
	short		events;

	zbx_tcp_send_context_t	context;
	zbx_socket_t	s;

	ZBX_UNUSED(state);

#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	s.tls_ctx = NULL;
#endif

	s.connection_type = ZBX_TCP_SEC_UNENCRYPTED;
	context.compressed_data = NULL;
	context.written = (ssize_t)zbx_mock_get_parameter_int("in.written");
	context.written_header = (ssize_t)zbx_mock_get_parameter_int("in.written_header");
	context.header_len = zbx_mock_get_parameter_uint64("in.header_len");
	context.data = zbx_mock_get_parameter_string("in.data");
	context.send_len = zbx_mock_get_parameter_uint64("in.send_len");

	int	result = zbx_tcp_send_context(&s, &context, &events);

	zbx_mock_assert_int_eq("return value", exp_result, result);
}
