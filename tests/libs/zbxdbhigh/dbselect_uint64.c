/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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

#include "common.h"
#include "zbxalgo.h"
#include "db.h"

void	zbx_mock_test_entry(void **state)
{
	zbx_vector_uint64_t	func_result_ids;
	zbx_uint64_t		json_return_id;
	int			json_return_num, i, test_result = SUCCEED;
	char			json_return_idx[64];

	ZBX_UNUSED(state);

	/* init an array for tested function results */
	zbx_vector_uint64_create(&func_result_ids);
	/* call a tested function */
	DBselect_uint64(get_in_param_by_name("sql"), &func_result_ids);

	/* get the expected number of IDs from json file */
	json_return_num = atoi(get_out_param_by_name("ids_num"));

	/* compare the received number of IDs with expected */
	test_result = (func_result_ids.values_num != json_return_num);
	for (i = 0; i < json_return_num && test_result == SUCCEED; i++)
	{
		/* get the value of ID from json file by index */
		zbx_snprintf(json_return_idx, sizeof(json_return_idx), "ids[%d]", i);
		ZBX_STR2UINT64(json_return_id, get_out_param_by_name(json_return_idx));
		/* compare the received ID with expected */
		test_result = (json_return_id != func_result_ids.values[i]);
	}

	zbx_vector_uint64_destroy(&func_result_ids);
	/* check test result */
	assert_int_equal(test_result, atoi(get_out_param_by_name("return")));
}
