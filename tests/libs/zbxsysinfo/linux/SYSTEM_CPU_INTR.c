#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "zbxmockhelper.h"

#include "common.h"
#include "module.h"
#include "sysinfo.h"

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

	if (zbx_read_yaml_expected_ret() != (ret = SYSTEM_CPU_INTR(&request, &result)))
		fail_msg("unexpected return code '%s'", zbx_sysinfo_ret_string(ret));

	if (SYSINFO_RET_OK == ret)
	{
		zbx_uint64_t	interr;

		if (NULL == GET_UI64_RESULT(&result))
			fail_msg("result does not contain numeric unsigned value");

		if ((interr = zbx_read_yaml_expected_uint64("interrupts_since_boot")) != result.ui64)
			fail_msg("expected:" ZBX_FS_UI64 " actual:" ZBX_FS_UI64, interr, result.ui64);
	}
	else if (NULL == GET_MSG_RESULT(&result))
		fail_msg("result does not contain failure message");

	free_request(&request);
	free_result(&result);
}
