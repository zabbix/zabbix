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
	char		*data = zbx_strdup(NULL, zbx_mock_get_parameter_string("in.data"));
	const char	*data_out = zbx_mock_get_parameter_string("out.data");
	const char	*from = zbx_mock_get_parameter_string("in.from");
	size_t		data_alloc = zbx_mock_get_parameter_uint64("in.data_alloc");
	size_t		data_len = zbx_mock_get_parameter_uint64("in.data_len");
	size_t		offset = zbx_mock_get_parameter_uint64("in.offset");
	size_t		sz_to = zbx_mock_get_parameter_uint64("in.sz_to");
	size_t		sz_from = zbx_mock_get_parameter_uint64("in.sz_from");
	int		exp_result = atoi(zbx_mock_get_parameter_string("out.exp_result"));

	ZBX_UNUSED(state);

	int		act_result = zbx_replace_mem_dyn(&data, &data_alloc, &data_len, offset, sz_to, from, sz_from);

	zbx_mock_assert_int_eq("Unexpected error message int", exp_result, act_result);
	zbx_mock_assert_str_eq("Unexpected error message str", data_out, data);
	zbx_free(data);
}
