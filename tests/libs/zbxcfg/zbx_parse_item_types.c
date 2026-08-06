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

#include "zbxcfg.h"

void	zbx_mock_test_entry(void **state)
{
	zbx_mock_error_t	error;
	zbx_mock_handle_t	mh, out;
	const char		*itemtypes;
	const char		*tmp;
	const char		*expected_error = NULL;
	char			*actual_error = NULL;
	int			expected_result, actual_result;
	zbx_uint32_t		expected_mask = 0, actual_mask = 0xffffffff;

	ZBX_UNUSED(state);

	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_in_parameter("itemtypes", &mh)) ||
			ZBX_MOCK_SUCCESS != (error = zbx_mock_string(mh, &itemtypes)))
	{
		fail_msg("Cannot get 'itemtypes' from test case data: %s", zbx_mock_error_string(error));
	}

	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_out_parameter("return", &out)) ||
			ZBX_MOCK_SUCCESS != (error = zbx_mock_string(out, &tmp)))
	{
		fail_msg("Cannot get expected 'return' from test case data: %s", zbx_mock_error_string(error));
	}

	if (0 == strcmp("SUCCEED", tmp))
		expected_result = SUCCEED;
	else if (0 == strcmp("FAIL", tmp))
		expected_result = FAIL;
	else
		fail_msg("Got unexpected 'return' parameter from test case data: %s", tmp);

	if (SUCCEED == expected_result)
	{
		if (ZBX_MOCK_SUCCESS != (error = zbx_mock_out_parameter("mask", &out)) ||
				ZBX_MOCK_SUCCESS != (error = zbx_mock_uint32(out, &expected_mask)))
		{
			fail_msg("Cannot get expected 'mask' from test case data: %s", zbx_mock_error_string(error));
		}
	}
	else if (ZBX_MOCK_SUCCESS == (error = zbx_mock_out_parameter("error", &out)))
	{
		if (ZBX_MOCK_SUCCESS != (error = zbx_mock_string(out, &expected_error)))
			fail_msg("Cannot get expected 'error' from test case data: %s", zbx_mock_error_string(error));
	}

	actual_result = zbx_parse_item_types(itemtypes, &actual_mask, &actual_error);

	if (expected_result != actual_result)
	{
		fail_msg("Got %s instead of %s as a result.",
				SUCCEED == actual_result ? "SUCCEED" : "FAIL",
				SUCCEED == expected_result ? "SUCCEED" : "FAIL");
	}

	if (SUCCEED == expected_result && expected_mask != actual_mask)
	{
		fail_msg("Got mask 0x%x instead of 0x%x.", (unsigned int)actual_mask, (unsigned int)expected_mask);
	}

	if (NULL != expected_error)
	{
		if (NULL == actual_error)
			fail_msg("Expected error message \"%s\" but got none.", expected_error);
		else if (0 != strcmp(expected_error, actual_error))
			fail_msg("Got error message \"%s\" instead of \"%s\".", actual_error, expected_error);
	}

	zbx_free(actual_error);
}
