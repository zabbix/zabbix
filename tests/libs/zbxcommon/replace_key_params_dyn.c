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
#include "zbxserver.h"

/******************************************************************************
 *                                                                            *
 * Function: replace_key_param_cb                                             *
 *                                                                            *
 * Comments: auxiliary function for zbx_mock_test_entry()                     *
 *                                                                            *
 ******************************************************************************/
static int	replace_key_param_cb(const char *data, int key_type, int level, int num, int quoted, void *cb_data,
		char **param)
{
	int	ret = SUCCEED;

	ZBX_UNUSED(key_type);
	ZBX_UNUSED(cb_data);
	ZBX_UNUSED(num);

	if (0 == level)
		return ret;

	*param = zbx_strdup(NULL, data);

	unquote_key_param(*param);

	if (FAIL == (ret = quote_key_param(param, quoted)))
		zbx_free(*param);

	return ret;
}

void	zbx_mock_test_entry(void **state)
{
	zbx_mock_error_t	error;
	zbx_mock_handle_t	mh;
	const char		*key, *expected_key, *tmp;
	int			expected_result = 123, actual_result;
	char			function_error[64];

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

	/* optional output parameter "expected_key" */
	if ((ZBX_MOCK_NO_PARAMETER == (error = zbx_mock_out_parameter("expected_key", &mh)) &&
			SUCCEED == expected_result) || (ZBX_MOCK_SUCCESS == error && FAIL == expected_result))
	{
		fail_msg("Malformed test case data, 'expected_key' should be present when result is SUCCEED (for a"
				" valid key) or absent when result is FAIL (for an invalid key).");
	}
	else if (ZBX_MOCK_SUCCESS != (error = zbx_mock_string(mh, &expected_key)) && SUCCEED == expected_result)
	{
		fail_msg("Cannot get 'expected_key' from test case data: %s", zbx_mock_error_string(error));
	}

	if (expected_result != (actual_result = replace_key_params_dyn((char **)&key, ZBX_KEY_TYPE_ITEM,
			replace_key_param_cb, NULL, function_error, sizeof(function_error))))
	{
		fail_msg("Got %s instead of %s as a result: %s", zbx_result_string(actual_result),
				zbx_result_string(expected_result), function_error);
	}

	if (SUCCEED == expected_result)
	{
		if (0 != strcmp(expected_key, key))
			fail_msg("Got '%s' instead of '%s' as the expected key.", key, expected_key);
	}
}
