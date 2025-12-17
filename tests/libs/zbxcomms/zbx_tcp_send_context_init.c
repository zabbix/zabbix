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

int __wrap_zbx_compress(const char *in, size_t size_in, char **out, size_t *size_out);

int __wrap_zbx_compress(const char *in, size_t size_in, char **out, size_t *size_out)
{
	ZBX_UNUSED(in);
	ZBX_UNUSED(size_in);
	ZBX_UNUSED(out);
	ZBX_UNUSED(size_out);

	int	result = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("in.compress_result"));

	return result;
}

void	zbx_mock_test_entry(void **state)
{
	const char	*data = zbx_mock_get_parameter_string("in.data");
	int		exp_result = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.result"));
	size_t		len = zbx_mock_get_parameter_uint64("in.len"),
			reserved = zbx_mock_get_parameter_uint64("in.reserved");
	unsigned char	flags = (unsigned char)zbx_mock_get_parameter_uint64("in.flags");

	ZBX_UNUSED(state);

	zbx_tcp_send_context_t	context;

	int	result = zbx_tcp_send_context_init(data, len, reserved, flags, &context);

	zbx_mock_assert_int_eq("return value", exp_result, result);

	zbx_tcp_send_context_clear(&context);
}
