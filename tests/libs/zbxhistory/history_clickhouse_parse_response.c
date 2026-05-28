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
#include "zbxmockdb.h"
#include "mocks/valuecache/valuecache_mock.h"


#include "../../../src/libs/zbxhistory/history_clickhouse.c"

void	zbx_mock_test_entry(void **state)
{
	char				*response;
	unsigned char			value_type;
	zbx_vector_history_record_t	values_exp, values_out;
	zbx_history_record_t		*values = NULL;
	int				ret_out, ret_exp;

	ZBX_UNUSED(state);

	zbx_vector_history_record_create(&values_exp);
	zbx_vector_history_record_create(&values_out);

	response = zbx_strdup(NULL, zbx_mock_get_parameter_string("in.data"));
	value_type = zbx_mock_str_to_value_type(zbx_mock_get_parameter_string("in['value type']"));
	ret_exp = zbx_mock_get_parameter_int("out.result");

	if (0 < ret_exp)
		zbx_vcmock_read_values(zbx_mock_get_parameter_handle("out.values"), value_type, &values_exp);

	ret_out = history_clickhouse_parse_response(response, value_type, &values);

	zbx_mock_assert_int_eq("history_clickhouse_parse_response() return value", ret_exp, ret_out);

	if (0 < ret_out)
	{
		values_out.values = values;
		values_out.values_num = ret_out;
		values_out.values_alloc = ret_out;
		zbx_vcmock_check_records("returned values", value_type,  &values_exp, &values_out);
	}

	zbx_history_record_vector_clean(&values_exp, value_type);
	zbx_vector_history_record_destroy(&values_exp);

	zbx_free(response);
	zbx_history_record_vector_clean(&values_out, value_type);
	zbx_vector_history_record_destroy(&values_out);
}
