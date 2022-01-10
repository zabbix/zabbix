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

#include "common.h"
#include "sysinfo.h"

const char *request_parameter_type_to_str(zbx_request_parameter_type_t type);

const char *request_parameter_type_to_str(zbx_request_parameter_type_t type)
{
	const char *ret = "undefined";

	switch(type)
	{
		case REQUEST_PARAMETER_TYPE_STRING:
			ret = "string";
			break;
		case REQUEST_PARAMETER_TYPE_ARRAY:
			ret = "array";
			break;
		default:
			break;
	}

	return ret;
}

void	zbx_mock_test_entry(void **state)
{
	zbx_mock_error_t	error;
	zbx_mock_handle_t	in_key, out_key, out_parameters, out_types;
	AGENT_REQUEST		request;
	const char		*item_key, *expected_key;
	int			expected_result = 123, actual_result;

	ZBX_UNUSED(state);

	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_in_parameter("key", &in_key)) ||
			ZBX_MOCK_SUCCESS != (error = zbx_mock_string(in_key, &item_key)))
	{
		fail_msg("Cannot get item key from test case data: %s", zbx_mock_error_string(error));
	}

	if (ZBX_MOCK_NO_PARAMETER == (error = zbx_mock_out_parameter("key", &out_key)))
		expected_result = FAIL;
	else if (ZBX_MOCK_SUCCESS != error || ZBX_MOCK_SUCCESS != (error = zbx_mock_string(out_key, &expected_key)))
		fail_msg("Cannot get expected key from test case data: %s", zbx_mock_error_string(error));
	else
		expected_result = SUCCEED;

	if ((ZBX_MOCK_NO_PARAMETER == (error = zbx_mock_out_parameter("parameters", &out_parameters)) ||
			ZBX_MOCK_NO_PARAMETER == (error = zbx_mock_out_parameter("types", &out_types))) &&
			SUCCEED == expected_result)
	{
		fail_msg("Malformed test case data, expected key, parameters and type should be either all present"
				" (for a valid key) or all absent (for an invalid key).");
	}
	else if (ZBX_MOCK_SUCCESS != error && SUCCEED == expected_result)
	{
		fail_msg("Cannot get expected parameters from test case data: %s", zbx_mock_error_string(error));
	}

	init_request(&request);

	if (expected_result != (actual_result = parse_item_key(item_key, &request)))
	{
		fail_msg("Got %s instead of %s as a result.", zbx_result_string(actual_result),
				zbx_result_string(expected_result));
	}

	if (SUCCEED == expected_result)
	{
		zbx_mock_handle_t	out_parameter, out_type;
		const char		*expected_parameter, *expected_type;
		int			i;

		if (0 != strcmp(expected_key, request.key))
			fail_msg("Got '%s' instead of '%s' as a key.", request.key, expected_key);

		for (i = 0; ZBX_MOCK_SUCCESS == (error = zbx_mock_vector_element(out_parameters, &out_parameter)) &&
			ZBX_MOCK_SUCCESS == (error = zbx_mock_vector_element(out_types, &out_type)); i++)
		{
			if (ZBX_MOCK_SUCCESS != (error = zbx_mock_string(out_parameter, &expected_parameter)) ||
					ZBX_MOCK_SUCCESS != (error = zbx_mock_string(out_type, &expected_type)))
				break;

			if (i >= request.nparam)
				fail_msg("There are fewer actual parameters (%d) than expected.", request.nparam);

			if (0 != strcmp(expected_parameter, request.params[i]))
			{
				fail_msg("Unexpected parameter #%d: '%s' instead of '%s'.", i + 1, request.params[i],
						expected_parameter);
			}

			if (0 != strcmp(expected_type, request_parameter_type_to_str(request.types[i])))
			{
				fail_msg("Unexpected parameter type #%d: '%s' instead of '%s'.", i + 1,
						request_parameter_type_to_str(request.types[i]), expected_type);
			}
		}

		if (ZBX_MOCK_END_OF_VECTOR != error)
		{
			fail_msg("Cannot get expected parameter/type #%d from test case data: %s", i,
					zbx_mock_error_string(error));
		}

		if (i < request.nparam)
			fail_msg("There are more actual parameters (%d) than expected (%d).", i, request.nparam);
	}

	free_request(&request);
}
