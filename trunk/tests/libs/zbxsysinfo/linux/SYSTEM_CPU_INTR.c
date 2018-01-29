#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "common.h"
#include "module.h"
#include "sysinfo.h"

static zbx_uint64_t	read_yaml_uint64(const char *out)
{
	zbx_mock_handle_t	handle;
	zbx_mock_error_t	error;
	const char		*str;
	zbx_uint64_t		value;

	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_out_parameter(out, &handle)))
		fail_msg("Cannot get interruptions since boot: %s", zbx_mock_error_string(error));

	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_string(handle, &str)))
		fail_msg("Cannot read interruptions since boot: %s", zbx_mock_error_string(error));

	if (FAIL == is_uint64(str, &value))
		fail_msg("\"%s\" is not a valid numeric unsigned value", str);

	return value;
}

static int	read_yaml_ret(void)
{
	zbx_mock_handle_t	handle;
	zbx_mock_error_t	error;
	const char		*str;

	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_out_parameter("ret", &handle)))
		fail_msg("Cannot get return code: %s", zbx_mock_error_string(error));

	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_string(handle, &str)))
		fail_msg("Cannot read return code: %s", zbx_mock_error_string(error));

	if (0 == strcasecmp(str, "succeed"))
		return SYSINFO_RET_OK;

	if (0 != strcasecmp(str, "fail"))
		fail_msg("Incorrect return code '%s'", str);

	return SYSINFO_RET_FAIL;
}

void	zbx_mock_test_entry(void **state)
{
	const char	*itemkey = "system.cpu.intr";
	AGENT_RESULT	result;
	AGENT_REQUEST	request;
	int		ret;

	ZBX_UNUSED(state);

	init_result(&result);
	init_request(&request);

	if (SUCCEED != parse_item_key(itemkey, &request))
		fail_msg("Invalid item key format '%s'", itemkey);

	if (read_yaml_ret() != (ret = SYSTEM_CPU_INTR(&request, &result)))
		fail_msg("unexpected return code '%s'", zbx_sysinfo_ret_string(ret));

	if (SYSINFO_RET_OK == ret)
	{
		zbx_uint64_t	interr;

		if (NULL == GET_UI64_RESULT(&result))
			fail_msg("result does not contain numeric unsigned value");

		if ((interr = read_yaml_uint64("interrupts_since_boot")) != result.ui64)
			fail_msg("expected:" ZBX_FS_UI64 " actual:" ZBX_FS_UI64, interr, result.ui64);
	}
	else if (NULL == GET_MSG_RESULT(&result))
		fail_msg("result does not contain failure message");

	free_request(&request);
	free_result(&result);
}
