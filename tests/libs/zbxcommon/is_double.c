#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "common.h"

void zbx_mock_test_entry(void** state)
{
	zbx_mock_error_t error;
	zbx_mock_handle_t in_str;
	zbx_mock_handle_t in_flags;
	zbx_mock_handle_t out_return;
	const char* str_string;
	const char* flags_string;
	const char* return_string;
	unsigned int flags_uint32;
	int expected_result = FAIL;
	int actual_result = FAIL;

	ZBX_UNUSED(state);

	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_in_parameter("str", &in_str)) ||
			ZBX_MOCK_SUCCESS != (error = zbx_mock_string(in_str, &str_string)))
	{
		fail_msg("Cannot get 'str' parameter from test case data: %s", zbx_mock_error_string(error));
	}

	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_in_parameter("flags", &in_flags)) ||
			ZBX_MOCK_SUCCESS != (error = zbx_mock_string(in_flags, &flags_string)))
	{
		fail_msg("Cannot get 'flags' parameter from test case data: %s", zbx_mock_error_string(error));
	}

	if (SUCCEED != is_uint32(flags_string, &flags_uint32))
		fail_msg("Cannot convert 'flags' to unsigned 32 bit integer");

	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_out_parameter("return", &out_return)) ||
			ZBX_MOCK_SUCCESS != (error = zbx_mock_string(out_return, &return_string)))
	{
		fail_msg("Cannot get expected 'return' parameter from test case data: %s",
				zbx_mock_error_string(error));
	}
	else
	{
		if (0 == strcmp("SUCCEED", return_string))
			expected_result = SUCCEED;
		else if (0 == strcmp("FAIL", return_string))
			expected_result = FAIL;
		else
			fail_msg("Get unexpected 'return' parameter from test case data: %s", return_string);
	}

	if (expected_result != (actual_result = is_double(str_string, flags_uint32)))
	{
		fail_msg("Got %s instead of %s as a result", zbx_sysinfo_ret_string(actual_result),
				zbx_sysinfo_ret_string(expected_result));
	}
}
