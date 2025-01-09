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

#include "zbxhttp.h"
#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "zbxmockutil.h"
#include "zbxmockhelper.h"
#include "zbxmockassert.h"

#include "zbxstr.h"

void	zbx_mock_test_entry(void **state)
{
	int		expected_result;
	size_t		in_buffer_length = 0, expected_result_buffer_length = 0;
	char		*in_buffer, *result_buffer, *expected_result_buffer, *encoding, *error = NULL;

	ZBX_UNUSED(state);

	expected_result = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.return"));
	in_buffer = zbx_yaml_assemble_binary_sequence("in.buffer", &in_buffer_length);
	expected_result_buffer = zbx_yaml_assemble_binary_sequence("out.expected_result_buffer",
			&expected_result_buffer_length);

	/* expect all buffers to be null terminated */
	if (0 != expected_result_buffer_length)
		expected_result_buffer_length--;

	if (0 != in_buffer_length)
		in_buffer_length--;

	encoding = zbx_determine_charset(zbx_mock_get_parameter_string("in.encoding"), in_buffer, in_buffer_length);
	zbx_mock_assert_str_eq("", zbx_mock_get_parameter_string("out.encoding"), encoding);

	result_buffer = zbx_convert_to_utf8(in_buffer, in_buffer_length, encoding, &error);

	if (expected_result_buffer_length  != strlen(result_buffer) ||
			0 != memcmp(result_buffer, expected_result_buffer, expected_result_buffer_length))
	{
		if (SUCCEED == expected_result)
		{
			fail_msg("Expected the same result but there are differences expected len:%lu actual:%lu"
					"result '%s'", expected_result_buffer_length, strlen(result_buffer),
					result_buffer);
		}

		zbx_free(expected_result_buffer);
		zbx_free(result_buffer);

	}
	else
	{
		zbx_free(expected_result_buffer);
		zbx_free(result_buffer);

		if (SUCCEED != expected_result)
			fail_msg("Expected differences but result is the same");
	}
	zbx_free(in_buffer);
	zbx_free(encoding);
}
