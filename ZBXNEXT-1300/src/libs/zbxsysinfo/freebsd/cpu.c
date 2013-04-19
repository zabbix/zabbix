/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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

#include "common.h"
#include "../common/common.h"
#include "sysinfo.h"
#include "stats.h"
#include "zbxjson.h"

static int	get_cpu_num(int online)
{
#if defined(_SC_NPROCESSORS_ONLN)	/* FreeBSD 6.2 i386; FreeBSD 7.0 i386 */
	if (1 == online)
		return sysconf(_SC_NPROCESSORS_ONLN);

	return sysconf(_SC_NPROCESSORS_CONF);
#elif defined(HAVE_FUNCTION_SYSCTL_HW_NCPU)	/* FreeBSD 4.2 i386; FreeBSD 6.2 i386; FreeBSD 7.0 i386 */
	size_t	len;
	int	mib[] = {CTL_HW, HW_NCPU}, ncpu;

	len = sizeof(ncpu);

	if (1 == online && -1 != sysctl(mib, 2, &ncpu, &len, NULL, 0))
		return ncpu;
#endif
	return -1;
}

int	SYSTEM_CPU_NUM(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*tmp;
	int	online = 0, ncpu;

	if (1 < request->nparam)
		return SYSINFO_RET_FAIL;

	tmp = get_rparam(request, 0);

	if (NULL == tmp || '\0' == *tmp || 0 == strcmp(tmp, "online"))
		online = 1;
	else if (0 != strcmp(tmp, "max"))
		return SYSINFO_RET_FAIL;

	if (-1 == (ncpu = get_cpu_num(online)))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, ncpu);

	return SYSINFO_RET_OK;
}

int	SYSTEM_CPU_UTIL(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*tmp;
	int	cpu_num, state, mode;

	if (3 < request->nparam)
		return SYSINFO_RET_FAIL;

	tmp = get_rparam(request, 0);

	if (NULL == tmp || '\0' == *tmp || 0 == strcmp(tmp, "all"))
		cpu_num = 0;
	else if (SUCCEED != is_uint31_1(tmp, &cpu_num))
		return SYSINFO_RET_FAIL;
	else
		cpu_num++;

	tmp = get_rparam(request, 1);

	if (NULL == tmp || '\0' == *tmp || 0 == strcmp(tmp, "user"))
		state = ZBX_CPU_STATE_USER;
	else if (0 == strcmp(tmp, "nice"))
		state = ZBX_CPU_STATE_NICE;
	else if (0 == strcmp(tmp, "system"))
		state = ZBX_CPU_STATE_SYSTEM;
	else if (0 == strcmp(tmp, "idle"))
		state = ZBX_CPU_STATE_IDLE;
	else if (0 == strcmp(tmp, "interrupt"))
		state = ZBX_CPU_STATE_INTERRUPT;
	else
		return SYSINFO_RET_FAIL;

	tmp = get_rparam(request, 2);

	if (NULL == tmp || '\0' == *tmp || 0 == strcmp(tmp, "avg1"))
		mode = ZBX_AVG1;
	else if (0 == strcmp(tmp, "avg5"))
		mode = ZBX_AVG5;
	else if (0 == strcmp(tmp, "avg15"))
		mode = ZBX_AVG15;
	else
		return SYSINFO_RET_FAIL;

	return get_cpustat(result, cpu_num, state, mode);
}

int SYSTEM_CPU_DISCOVERY(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char		*type, *end, *error[MAX_STRING_LEN], *output = NULL, id_part[64], match[8], *cpu_offline;
	int		res, online = 0, ret = SYSINFO_RET_FAIL;
	long		cpuid;
	struct zbx_json	j;

	if (1 < request->nparam)
		return SYSINFO_RET_FAIL;

	type = get_rparam(request, 0);

	if (NULL == type || '\0' == *type || 0 == strcmp(type, "online"))
		online = 1;
	else if (0 != strcmp(type, "all"))
		return SYSINFO_RET_FAIL;

	if (SUCCEED != (res = zbx_execute("grep 'APIC ID:  [0-9]\\+\\( (disabled)\\)\\?$' /var/run/dmesg.boot -o",
			&output, error, sizeof(error), 10)))
		goto out;

	while (NULL != (end = strstr(output, "APIC ID:  0\n")))
	{
		output = end + 1;
	}

	zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);
	zbx_json_addarray(&j, ZBX_PROTO_TAG_DATA);

	while (NULL != (end = strchr(output, '\n')))
	{
		zbx_strlcpy(id_part, output, end - output + 1);
		cpu_offline = strstr(id_part, "disabled");
		if (1 == online && NULL == cpu_offline || 0 == online)
		{
			zbx_json_addobject(&j, NULL);

			cpuid = atoi(id_part + 10);

			zbx_json_adduint64(&j, ZBX_MACRO_CPUID, cpuid);
			zbx_json_addstring(&j, ZBX_MACRO_CPU_STATUS, (NULL != cpu_offline ? "offline" : "online"),
					ZBX_JSON_TYPE_STRING);

			zbx_json_close(&j);
		}
		output = end + 1;
	}

	zbx_json_close(&j);

	ret = SYSINFO_RET_OK;

	SET_STR_RESULT(result, zbx_strdup(result->str, j.buffer));

	zbx_json_free(&j);

out:
	zbx_free(output);

	return ret;
}

int	SYSTEM_CPU_LOAD(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*tmp;
	int	mode, per_cpu = 1, cpu_num;
	double	load[ZBX_AVG_COUNT], value;

	if (2 < request->nparam)
		return SYSINFO_RET_FAIL;

	tmp = get_rparam(request, 0);

	if (NULL == tmp || '\0' == *tmp || 0 == strcmp(tmp, "all"))
		per_cpu = 0;
	else if (0 != strcmp(tmp, "percpu"))
		return SYSINFO_RET_FAIL;

	tmp = get_rparam(request, 1);

	if (NULL == tmp || '\0' == *tmp || 0 == strcmp(tmp, "avg1"))
		mode = ZBX_AVG1;
	else if (0 == strcmp(tmp, "avg5"))
		mode = ZBX_AVG5;
	else if (0 == strcmp(tmp, "avg15"))
		mode = ZBX_AVG15;
	else
		return SYSINFO_RET_FAIL;

	if (mode >= getloadavg(load, 3))
		return SYSINFO_RET_FAIL;

	value = load[mode];

	if (1 == per_cpu)
	{
		if (0 >= (cpu_num = get_cpu_num(1)))
			return SYSINFO_RET_FAIL;
		value /= cpu_num;
	}

	SET_DBL_RESULT(result, value);

	return SYSINFO_RET_OK;
}

int     SYSTEM_CPU_SWITCHES(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	u_int	v_swtch;
	size_t	len;

	len = sizeof(v_swtch);

	if (0 != sysctlbyname("vm.stats.sys.v_swtch", &v_swtch, &len, NULL, 0))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, v_swtch);

	return SYSINFO_RET_OK;
}

int     SYSTEM_CPU_INTR(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	u_int	v_intr;
	size_t	len;

	len = sizeof(v_intr);

	if (0 != sysctlbyname("vm.stats.sys.v_intr", &v_intr, &len, NULL, 0))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, v_intr);

	return SYSINFO_RET_OK;
}
