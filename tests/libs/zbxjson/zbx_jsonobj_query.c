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
#include "zbxmockjson.h"

#include "zbxstr.h"
#include "zbxjson.h"
#include "../../../src/libs/zbxjson/json.h"

static void	check_definite_path_result(zbx_mock_handle_t handle, const char *returned_output)
{
	const char		*expected_output;
	struct zbx_json_parse	jp;

	if (ZBX_MOCK_SUCCESS != zbx_mock_string(handle, &expected_output))
		fail_msg("Invalid test case out.value parameter");

	if (FAIL == zbx_json_open(expected_output, &jp))
		zbx_mock_assert_str_eq("Definite query result", expected_output, returned_output);
	else
		zbx_mock_assert_json_eq("Indefinite query result", expected_output, returned_output);
}

static void	check_indefinite_path_result(zbx_mock_handle_t handle, const char *returned_output)
{
	const char	*expected_output;

	if (ZBX_MOCK_SUCCESS != zbx_mock_string(handle, &expected_output))
		fail_msg("Invalid test case out.values parameter");

	zbx_mock_assert_json_eq("Indefinite query result", expected_output, returned_output);
}

static void	test_query(zbx_jsonobj_t *obj, const char *path, int expected_ret)
{
	char			*output = NULL;
	int			returned_ret;
	zbx_mock_handle_t	handle;

	returned_ret = zbx_jsonobj_query(obj, path, &output);

	if (FAIL == returned_ret)
		printf("\tzbx_jsonpath_query() failed with: %s\n", zbx_json_strerror());

	zbx_mock_assert_result_eq("zbx_jsonpath_query() return value", expected_ret, returned_ret);

	if (SUCCEED == returned_ret)
	{
		printf("\tzbx_jsonpath_query() query result: %s\n", ZBX_NULL2EMPTY_STR(output));
		if (ZBX_MOCK_SUCCESS == zbx_mock_parameter("out.value", &handle))
		{
			zbx_mock_assert_ptr_ne("Query result", NULL, output);
			check_definite_path_result(handle, output);
		}
		else if (ZBX_MOCK_SUCCESS == zbx_mock_parameter("out.values", &handle))
		{
			zbx_mock_assert_ptr_ne("Query result", NULL, output);
			check_indefinite_path_result(handle, output);
		}
		else
			zbx_mock_assert_ptr_eq("Query result", NULL, output);
	}
	else
		zbx_mock_assert_str_ne("tzbx_jsonpath_query() error", "", zbx_json_strerror());

	zbx_free(output);

}

void	zbx_mock_test_entry(void **state)
{
	const char	*data, *path;
	int		expected_ret;
	zbx_jsonobj_t	obj;

	ZBX_UNUSED(state);

	/* reset json error to check if compilation will set it */
	zbx_set_json_strerror("%s", "");

	data = zbx_mock_get_parameter_string("in.data");
	if (FAIL == zbx_jsonobj_open(data, &obj))
		fail_msg("Invalid json data: %s", zbx_json_strerror());

	path = zbx_mock_get_parameter_string("in.path");
	expected_ret = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.return"));

	test_query(&obj, path, expected_ret);

	/* query second time to check index reuse */
	test_query(&obj, path, expected_ret);

	zbx_jsonobj_clear(&obj);
}
