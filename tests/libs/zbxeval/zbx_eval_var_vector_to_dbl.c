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
#include "zbxeval.h"
#include "mock_eval.h"
#include "zbxdbwrap.h"

static void	extract_yaml_values(const char *path, zbx_vector_str_t *values)
{
	zbx_mock_handle_t	hvalues, hvalue;
	zbx_mock_error_t	err;
	int			value_num = 0;

	hvalues = zbx_mock_get_parameter_handle(path);

	while (ZBX_MOCK_END_OF_VECTOR != (err = (zbx_mock_vector_element(hvalues, &hvalue))))
	{
		const char	*value;

		if (ZBX_MOCK_SUCCESS != err || ZBX_MOCK_SUCCESS != (err = zbx_mock_string(hvalue, &value)))
		{
			value = NULL;
			fail_msg("Cannot read value #%d: %s", value_num, zbx_mock_error_string(err));
		}

		char	*dup_value = zbx_strdup(NULL, value);
		zbx_vector_str_append(values, dup_value);

		value_num++;
	}
}

static int	upend_var_vector(zbx_vector_var_t *vector, zbx_vector_str_t *str_vector)
{
	zbx_variant_t	variant;

	extract_yaml_values("in.data", str_vector);

	for(int i = 0; i < str_vector->values_num; i++)
	{

	}

}

void	zbx_mock_test_entry(void **state)
{
	zbx_vector_var_t	input_vector;
	zbx_vector_dbl_t	output_vector;
	zbx_vector_str_t	str_vector;
	char			*error = NULL;

	ZBX_UNUSED(state);

	zbx_vector_var_create(&input_vector);
	zbx_vector_str_create(&str_vector);


	zbx_eval_var_vector_to_dbl(&input_vector, &output_vector, &error);

}
