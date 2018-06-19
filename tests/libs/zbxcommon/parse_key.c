/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

void	zbx_mock_test_entry(void **state)
{
	zbx_mock_error_t	error;
	zbx_mock_handle_t	mh;
	const char		*key, *key_moving_pointer, *expected_valid_part, *expected_invalid_part, *tmp;
	int			expected_result = 123, actual_result;

	ZBX_UNUSED(state);

	/* mandatory input parameter "key" */
	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_in_parameter("key", &mh)) ||
			ZBX_MOCK_SUCCESS != (error = zbx_mock_string(mh, &key)))
	{
		fail_msg("Cannot get 'key' from test case data: %s", zbx_mock_error_string(error));
	}

	/* mandatory output parameter "return" */
	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_out_parameter("return", &mh)) ||
			ZBX_MOCK_SUCCESS != (error = zbx_mock_string(mh,&tmp)))
	{
		fail_msg("Cannot get expected 'return' parameter from test case data: %s",
				zbx_mock_error_string(error));
	}
	else
	{
		if (0 == strcmp("SUCCEED", tmp))
			expected_result = SUCCEED;
		else if (0 == strcmp("FAIL", tmp))
			expected_result = FAIL;
		else
			fail_msg("Get unexpected 'return' parameter from test case data: %s", tmp);
	}

	/* mandatory output parameter "valid_part" */
	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_out_parameter("valid_part", &mh)) ||
			ZBX_MOCK_SUCCESS != (error = zbx_mock_string(mh, &expected_valid_part)))
	{
		fail_msg("Cannot get expected 'valid_part' from test case data: %s", zbx_mock_error_string(error));
	}

	/* mandatory output parameter "invalid_part" */
	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_out_parameter("invalid_part", &mh)) ||
			ZBX_MOCK_SUCCESS != (error = zbx_mock_string(mh, &expected_invalid_part)))
	{
		fail_msg("Cannot get expected 'invalid_part' from test case data: %s", zbx_mock_error_string(error));
	}

	/* call the function under test */

	key_moving_pointer = key;

	if (expected_result != (actual_result = parse_key(&key_moving_pointer)))
	{
		fail_msg("Got %s instead of %s as a result.", zbx_result_string(actual_result),
				zbx_result_string(expected_result));
	}

	/* examine results */
	if (NULL == key_moving_pointer)
		fail_msg("parse_key() corrupted the pointer - it was set to NULL");

	if (key > key_moving_pointer)
	{
		fail_msg("parse_key() corrupted the pointer - it was moved backward from %p to %p",
				key, key_moving_pointer);
	}

	if (key_moving_pointer == key)
	{
		if (0 != strcmp(expected_valid_part, key))
			fail_msg("Got '%s' instead of '%s' as the valid_part.", key, expected_valid_part);
	}

	if (key_moving_pointer > key)
	{
		if (0 != strncmp(expected_valid_part, key, (size_t)(key_moving_pointer - key)))
		{
			*(char *)key_moving_pointer = '\0';

			fail_msg("Got '%s' instead of '%s' as the valid_part.", key, expected_valid_part);
		}
	}

	if (0 != strcmp(expected_invalid_part, key_moving_pointer))
		fail_msg("Got '%s' instead of '%s' as the invalid_part.", key_moving_pointer, expected_invalid_part);
}
