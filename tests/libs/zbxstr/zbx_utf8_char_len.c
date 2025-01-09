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
#include "zbxmockassert.h"
#include "zbxmockhelper.h"

#include "zbxstr.h"

void	zbx_mock_test_entry(void **state)
{
	size_t		in_buffer_length = zbx_mock_get_parameter_uint64("in.buffer_length");
	char		*in_buffer = zbx_yaml_assemble_binary_sequence("in.buffer", &in_buffer_length);
	size_t		exp_result = zbx_mock_get_parameter_uint64("out.return");

	ZBX_UNUSED(state);

	size_t		act_result = zbx_utf8_char_len(in_buffer);

	zbx_mock_assert_uint64_eq("return value", exp_result, act_result);
	zbx_free(in_buffer);
}
