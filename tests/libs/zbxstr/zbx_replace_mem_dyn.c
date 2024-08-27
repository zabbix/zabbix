/*
** Copyright (C) 2001-2024 Zabbix SIA
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
	ZBX_UNUSED(state);
//zbx_replace_mem_dyn(char **data, size_t *data_alloc, size_t *data_len, size_t offset, size_t sz_to, const char *from, size_t sz_from)
	char		*data = zbx_mock_get_parameter_string("in.data");
    printf("1------------\n");
	size_t		*data_alloc = zbx_mock_get_parameter_uint64("in.data_alloc");
    printf("2------------\n");
	size_t		*data_len = zbx_mock_get_parameter_uint64("in.data_len");
    printf("3------------\n");
    printf("%ld\n", data_len);
	size_t		offset = zbx_mock_get_parameter_uint64("in.offset");
    printf("4------------\n");
	size_t		sz_to = zbx_mock_get_parameter_uint64("in.sz_to");
    printf("5------------\n");
	const char		*from = zbx_mock_get_parameter_string("in.from");
    printf("6------------\n");
	size_t		sz_from = zbx_mock_get_parameter_uint64("in.sz_from");
    printf("7------------\n");
	int		exp_result = zbx_mock_get_parameter_uint64("out.exp_result");
    printf("8------------\n");
	int		act_result = zbx_replace_mem_dyn(&data, data_alloc, data_len, offset, sz_to, from, sz_from);
    printf("9------------\n");
	zbx_mock_assert_int_eq("Unexpected error message X", exp_result, act_result);
}
