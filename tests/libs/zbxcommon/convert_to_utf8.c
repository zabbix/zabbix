/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
#include "zbxmockutil.h"
#include "zbxmockhelper.h"

#include "common.h"

void	zbx_mock_test_entry(void **state)
{
	int		expected_result;
	size_t		in_buffer_length, expected_result_buffer_length;
	char		*in_buffer, *result_buffer, *expected_result_buffer;
	const char	*encoding;

	ZBX_UNUSED(state);

	expected_result = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.return"));
	in_buffer_length = zbx_mock_get_parameter_uint64("in.buffer_length");
	expected_result_buffer_length = zbx_mock_get_parameter_uint64("out.expected_result_buffer_length");
	in_buffer = zbx_yaml_assemble_binary_sequence("in.buffer", in_buffer_length);

	expected_result_buffer = zbx_yaml_assemble_binary_sequence("out.expected_result_buffer",
			expected_result_buffer_length);

	encoding  = zbx_mock_get_parameter_string("in.encoding");

	result_buffer = convert_to_utf8(in_buffer, in_buffer_length, encoding);

	zbx_free(in_buffer);

	if (expected_result_buffer_length != strlen(result_buffer) ||
			0 != memcmp(result_buffer, expected_result_buffer, expected_result_buffer_length))
	{
		zbx_free(expected_result_buffer);
		zbx_free(result_buffer);

		if (SUCCEED == expected_result)
			fail_msg("Expected the same result but there are differences");
	}
	else
	{
		zbx_free(expected_result_buffer);
		zbx_free(result_buffer);

		if (SUCCEED != expected_result)
			fail_msg("Expected differences but result is the same");
	}
}
