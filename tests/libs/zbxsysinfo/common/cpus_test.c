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

#include "../../../../src/libs/zbxsysinfo/common/cpustat.c"
#include "../../../../src/libs/zbxsysinfo/common/cpu.c"
#include "../../../../src/libs/zbxsysinfo/common/stats.h"

static ZBX_SINGLE_CPU_STAT_DATA	test_cpus[3];
static zbx_collector_data	*test_collector;

int	__wrap_cpu_collector_started(void);
zbx_collector_data	*__wrap_get_collector(void);

int	__wrap_cpu_collector_started(void)
{
	return 1;
}

zbx_collector_data	*__wrap_get_collector(void)
{
	return test_collector;
}

static int	str_to_cpu_status(const char *str)
{
	if (0 == strcmp(str, "online"))
		return ZBX_CPU_STATUS_ONLINE;

	if (0 == strcmp(str, "offline"))
		return ZBX_CPU_STATUS_OFFLINE;

	if (0 == strcmp(str, "unknown"))
		return ZBX_CPU_STATUS_UNKNOWN;

	fail_msg("Invalid \"status\" parameter in test case data: %s", str);

	return ZBX_CPU_STATUS_UNKNOWN;
}

#define TEST_NAME	"ZBX_GET_CPUS_TEST:"

static void	test_get_cpus(void)
{
	ZBX_SINGLE_CPU_STAT_DATA	*cpu;
	zbx_vector_uint64_pair_t	cpus;
	zbx_collector_data		collector;
	const char			*last_sample;
	char				parameter_string[20];
	int				h_first, h_count, index, ret;
	int				cpu_cnt = 1;
	int				expected_status[2];

	memset(test_cpus, 0, sizeof(test_cpus));
	memset(&test_collector, 0, sizeof(test_collector));

	if (ZBX_MOCK_SUCCESS == zbx_mock_parameter_exists("in.h_cpu_cnt"))
		cpu_cnt = zbx_mock_get_parameter_int("in.h_cpu_cnt");

	collector.cpus.count = cpu_cnt;
	collector.cpus.cpu = test_cpus;
	test_collector = &collector;

	for (int idx = 1; idx <= cpu_cnt; idx++ )
	{
		zbx_snprintf(parameter_string, sizeof(parameter_string),"in.last_sample_%d",idx);
		last_sample = zbx_mock_get_optional_parameter_string(parameter_string);

		zbx_snprintf(parameter_string, sizeof(parameter_string),"out.status_%d",idx);
		expected_status[idx - 1] = str_to_cpu_status(zbx_mock_get_parameter_string(parameter_string));

		zbx_snprintf(parameter_string, sizeof(parameter_string),"in.h_first_%d",idx);
		h_first = zbx_mock_get_parameter_int(parameter_string);

		zbx_snprintf(parameter_string, sizeof(parameter_string),"in.h_count_%d",idx);
		h_count = zbx_mock_get_parameter_int(parameter_string);

		cpu = &test_cpus[idx];
		cpu->h_first = h_first;
		cpu->h_count = h_count;
		cpu->cpu_num = idx - 1;

		if (0 < h_count)
		{
			if (NULL == last_sample)
				fail_msg("\"in.last_sample\" is required when \"in.h_count\" is non-zero");

			if (ZBX_MAX_COLLECTOR_HISTORY <= (index = h_first + h_count - 1))
				index -= ZBX_MAX_COLLECTOR_HISTORY;

			cpu->h_status[index] = (unsigned char)(0 == strcmp(last_sample, "online") ?
					SYSINFO_RET_OK : SYSINFO_RET_FAIL);
		}
	}

	zbx_vector_uint64_pair_create(&cpus);

	ret = get_cpus(&cpus);
	zbx_mock_assert_result_eq(TEST_NAME" return value", SUCCEED, ret);

	if (0 == cpus.values_num)
		fail_msg("get_cpus() returned no CPU entries");

	for (int idx = 0; idx < cpu_cnt; idx++ )
	{
		zbx_mock_assert_int_eq(TEST_NAME" {#CPU.NUMBER}", idx, (int)cpus.values[idx].first);
		zbx_mock_assert_int_eq(TEST_NAME" {#CPU.STATUS}", expected_status[idx],
				(int)cpus.values[idx].second);
	}

	zbx_vector_uint64_pair_destroy(&cpus);
}

#undef TEST_NAME
#define TEST_NAME	"ZBX_SYSTEM_CPU_DISCOVERY_TEST:"

/* item-level counterpart of test_get_cpus(): drives the same mocked collector state, but goes through */
/* the actual system.cpu.discovery entry point and asserts on its JSON contract instead of the internal */
/* get_cpus() vector - so it also covers get_cpu_status_string()/JSON serialization in cpu.c            */
static void	test_system_cpu_discovery(void)
{
	AGENT_REQUEST			request;
	AGENT_RESULT			result;
	ZBX_SINGLE_CPU_STAT_DATA	*cpu;
	zbx_collector_data		collector;
	struct zbx_json_parse		jp, jp_cpu;
	const char			*last_sample, *p;
	char				parameter_string[20], value[MAX_STRING_LEN];
	int				h_first, h_count, index, ret, cpu_idx;
	int				cpu_cnt = 1;

	memset(test_cpus, 0, sizeof(test_cpus));
	memset(&collector, 0, sizeof(collector));

	if (ZBX_MOCK_SUCCESS == zbx_mock_parameter_exists("in.h_cpu_cnt"))
		cpu_cnt = zbx_mock_get_parameter_int("in.h_cpu_cnt");

	collector.cpus.count = cpu_cnt;
	collector.cpus.cpu = test_cpus;
	test_collector = &collector;

	for (int idx = 1; idx <= cpu_cnt; idx++)
	{
		zbx_snprintf(parameter_string, sizeof(parameter_string), "in.last_sample_%d", idx);
		last_sample = zbx_mock_get_optional_parameter_string(parameter_string);

		zbx_snprintf(parameter_string, sizeof(parameter_string), "in.h_first_%d", idx);
		h_first = zbx_mock_get_parameter_int(parameter_string);

		zbx_snprintf(parameter_string, sizeof(parameter_string), "in.h_count_%d", idx);
		h_count = zbx_mock_get_parameter_int(parameter_string);

		cpu = &test_cpus[idx];
		cpu->h_first = h_first;
		cpu->h_count = h_count;
		cpu->cpu_num = idx - 1;

		if (0 < h_count)
		{
			if (NULL == last_sample)
				fail_msg("\"in.last_sample\" is required when \"in.h_count\" is non-zero");

			if (ZBX_MAX_COLLECTOR_HISTORY <= (index = h_first + h_count - 1))
				index -= ZBX_MAX_COLLECTOR_HISTORY;

			cpu->h_status[index] = (unsigned char)(0 == strcmp(last_sample, "online") ?
					SYSINFO_RET_OK : SYSINFO_RET_FAIL);
		}
	}

	/* system_cpu_discovery() takes no key parameters and never touches "request" (ZBX_UNUSED(request)), */
	/* so a zeroed local struct is enough - no need to pull in zbx_init/free_agent_request() from        */
	/* sysinfo.c, which drags the whole registered-metrics table (and every collector-backed item        */
	/* behind it) into this test binary's link                                                           */
	memset(&request, 0, sizeof(request));
	zbx_init_agent_result(&result);

	ret = system_cpu_discovery(&request, &result);

	zbx_mock_assert_sysinfo_ret_eq(TEST_NAME" return value", SYSINFO_RET_OK, ret);
	zbx_mock_assert_ptr_ne(TEST_NAME" result.str", NULL, result.str);

	if (FAIL == zbx_json_open(result.str, &jp))
		fail_msg("system_cpu_discovery() did not return valid JSON: %s", result.str);

	/* walk the returned {#CPU.*} array directly instead of reaching back into internal state - this is  */
	/* the actual contract the server/agent rely on for this item                                        */
	cpu_idx = 0;
	for (p = NULL; NULL != (p = zbx_json_next(&jp, p)); cpu_idx++)
	{
		if (FAIL == zbx_json_brackets_open(p, &jp_cpu))
			fail_msg("{#CPU.*} entry %d is not a JSON object", cpu_idx);

		if (SUCCEED != zbx_json_value_by_name(&jp_cpu, "{#CPU.NUMBER}", value, sizeof(value), NULL))
			fail_msg("{#CPU.NUMBER} missing in entry %d", cpu_idx);

		zbx_mock_assert_int_eq(TEST_NAME" {#CPU.NUMBER}", cpu_idx, atoi(value));

		if (SUCCEED != zbx_json_value_by_name(&jp_cpu, "{#CPU.STATUS}", value, sizeof(value), NULL))
			fail_msg("{#CPU.STATUS} missing in entry %d", cpu_idx);

		zbx_snprintf(parameter_string, sizeof(parameter_string), "out.status_%d", cpu_idx + 1);
		zbx_mock_assert_str_eq(TEST_NAME" {#CPU.STATUS}", zbx_mock_get_parameter_string(parameter_string),
				value);
	}

	zbx_mock_assert_int_eq(TEST_NAME" number of {#CPU.*} entries", cpu_cnt, cpu_idx);

	zbx_free_agent_result(&result);
}

#undef TEST_NAME
#define TEST_NAME	"ZBX_UPDATE_CPU_COUNTERS_TEST:"

static void	test_update_cpu_counters(void)
{
	ZBX_SINGLE_CPU_STAT_DATA	cpu = {0};
	zbx_uint64_t			counter[ZBX_CPU_STATE_COUNT], value = 0;
	int				expected_h_first, expected_h_count, expected_write_index,
					expected_status;

	cpu.h_first = zbx_mock_get_parameter_int("in.h_first");
	cpu.h_count = zbx_mock_get_parameter_int("in.h_count");

	expected_h_first = zbx_mock_get_parameter_int("out.h_first");
	expected_h_count = zbx_mock_get_parameter_int("out.h_count");
	expected_write_index = zbx_mock_get_parameter_int("out.write_index");
	expected_status = (0 == strcmp("online", zbx_mock_get_parameter_string("out.status")) ?
			SYSINFO_RET_OK : SYSINFO_RET_FAIL);

	if (ZBX_MOCK_SUCCESS == zbx_mock_parameter_exists("in.value"))
	{
		zbx_uint64_t	expected_value = zbx_mock_get_parameter_uint64("out.value");

		value = zbx_mock_get_parameter_uint64("in.value");

		for (int idx = 0; idx < ZBX_CPU_STATE_COUNT; idx++)
			counter[idx] = value + idx;

		update_cpu_counters(&cpu, counter);

		for (int idx = 0; idx < ZBX_CPU_STATE_COUNT; idx++)
		{
			zbx_mock_assert_uint64_eq(TEST_NAME" h_counter[ZBX_CPU_STATE][write_index]",
				expected_value + idx, cpu.h_counter[idx][expected_write_index]);
		}
	}
	else
		update_cpu_counters(&cpu, NULL);

	zbx_mock_assert_int_eq(TEST_NAME" h_first", expected_h_first, cpu.h_first);
	zbx_mock_assert_int_eq(TEST_NAME" h_count", expected_h_count, cpu.h_count);
	zbx_mock_assert_int_eq(TEST_NAME" h_status[write_index]", expected_status,
			cpu.h_status[expected_write_index]);
}

#undef TEST_NAME

void	zbx_mock_test_entry(void **state)
{
	const char	*test_type;

	ZBX_UNUSED(state);

	test_type = zbx_mock_get_parameter_string("in.test_type");

	if (0 == strcmp(test_type, "ZBX_GET_CPUS_TEST"))
		test_get_cpus();
	else if (0 == strcmp(test_type, "ZBX_SYSTEM_CPU_DISCOVERY_TEST"))
		test_system_cpu_discovery();
	else if (0 == strcmp(test_type, "ZBX_UPDATE_CPU_COUNTERS_TEST"))
		test_update_cpu_counters();
}
