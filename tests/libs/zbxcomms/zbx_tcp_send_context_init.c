/*
** Copyright (C) 2001-2026 Zabbix SIA
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

int	__wrap_zbx_compress(const char *in, size_t size_in, char **out, size_t *size_out);
int	__wrap_zbx_compress_zstd(const char *in, size_t size_in, char **out, size_t *size_out);

int	__wrap_zbx_compress(const char *in, size_t size_in, char **out, size_t *size_out)
{
	int	result;

	result = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("in.compress_result"));

	if (SUCCEED == result)
	{
		*out = zbx_malloc(NULL, size_in);
		memcpy(*out, in, size_in);
		*size_out = size_in;
	}

	return result;
}

int	__wrap_zbx_compress_zstd(const char *in, size_t size_in, char **out, size_t *size_out)
{
	return __wrap_zbx_compress(in, size_in, out, size_out);
}

void	zbx_mock_test_entry(void **state)
{
	const char		*data = zbx_mock_get_parameter_string("in.data");
	int			exp_result = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.result")),
				result;
	size_t			len = zbx_mock_get_parameter_uint64("in.len"),
				reserved = zbx_mock_get_parameter_uint64("in.reserved");
	unsigned char		flags = (unsigned char)zbx_mock_get_parameter_uint64("in.flags");
	zbx_tcp_send_context_t	context;

	ZBX_UNUSED(state);

	result = zbx_tcp_send_context_init(data, len, reserved, flags, &context);

	zbx_mock_assert_int_eq("return value", exp_result, result);

	if (SUCCEED == result && ZBX_MOCK_SUCCESS == zbx_mock_parameter_exists("out.header_len"))
	{
		zbx_mock_assert_uint64_eq("header length", zbx_mock_get_parameter_uint64("out.header_len"),
				(zbx_uint64_t)context.header_len);
	}

	zbx_tcp_send_context_clear(&context);
}
