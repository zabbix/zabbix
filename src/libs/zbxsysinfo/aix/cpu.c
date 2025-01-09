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

#include "zbxsysinfo.h"
#include "../sysinfo.h"

#include "../common/stats.h"

#include "zbxnum.h"

#include <sys/dr.h>

int	system_cpu_num(AGENT_REQUEST *request, AGENT_RESULT *result)
{
#ifdef HAVE_LIBPERFSTAT
	char			*tmp;
	lpar_info_format2_t	buf;

	if (1 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	tmp = get_rparam(request, 0);

	/* only "online" (default) for parameter "type" is supported */
	if (NULL != tmp && '\0' != *tmp && 0 != strcmp(tmp, "online"))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		return SYSINFO_RET_FAIL;
	}

	if (0 != lpar_get_info(LPAR_INFO_FORMAT2, &buf, sizeof(buf)))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain system information: %s", zbx_strerror(errno)));
		return SYSINFO_RET_FAIL;
	}

	SET_UI64_RESULT(result, buf.online_lcpus);

	return SYSINFO_RET_OK;
#else
	SET_MSG_RESULT(result, zbx_strdup(NULL, "Agent was compiled without support for Perfstat API."));

	return SYSINFO_RET_FAIL;
#endif
}

int	system_cpu_util(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*tmp;
	int	cpu_num, state, mode, res;

	if (4 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	tmp = get_rparam(request, 0);

	if (NULL == tmp || '\0' == *tmp || 0 == strcmp(tmp, "all"))
		cpu_num = ZBX_CPUNUM_ALL;
	else if (SUCCEED != zbx_is_uint31_1(tmp, &cpu_num))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		return SYSINFO_RET_FAIL;
	}

	tmp = get_rparam(request, 1);

	if (NULL == tmp || '\0' == *tmp || 0 == strcmp(tmp, "user"))
		state = ZBX_CPU_STATE_USER;
	else if (0 == strcmp(tmp, "system"))
		state = ZBX_CPU_STATE_SYSTEM;
	else if (0 == strcmp(tmp, "idle"))
		state = ZBX_CPU_STATE_IDLE;
	else if (0 == strcmp(tmp, "iowait"))
		state = ZBX_CPU_STATE_IOWAIT;
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		return SYSINFO_RET_FAIL;
	}

	tmp = get_rparam(request, 2);

	if (NULL == tmp || '\0' == *tmp || 0 == strcmp(tmp, "avg1"))
		mode = ZBX_AVG1;
	else if (0 == strcmp(tmp, "avg5"))
		mode = ZBX_AVG5;
	else if (0 == strcmp(tmp, "avg15"))
		mode = ZBX_AVG15;
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid third parameter."));
		return SYSINFO_RET_FAIL;
	}

	tmp = get_rparam(request, 3);

	if (NULL == tmp || '\0' == *tmp || 0 == strcmp(tmp, "logical"))
	{
		res = get_cpustat(result, cpu_num, state, mode);
	}
	else if (0 == strcmp(tmp, "physical"))
	{
		res = get_cpustat_physical(result, cpu_num, state, mode);
	}
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid fourth parameter."));
		return SYSINFO_RET_FAIL;
	}

	if (SYSINFO_RET_FAIL == res)
	{
		if (!ZBX_ISSET_MSG(result))
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain CPU information."));

		return SYSINFO_RET_FAIL;
	}

	return SYSINFO_RET_OK;
}

int	system_cpu_load(AGENT_REQUEST *request, AGENT_RESULT *result)
{
#ifdef HAVE_LIBPERFSTAT
#if !defined(SBITS)
#	define SBITS 16
#endif
	char			*tmp;
	int			mode, per_cpu = 1;
	perfstat_cpu_total_t	ps_cpu_total;
	double			value;

	if (2 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	tmp = get_rparam(request, 0);

	if (NULL == tmp || '\0' == *tmp || 0 == strcmp(tmp, "all"))
		per_cpu = 0;
	else if (0 != strcmp(tmp, "percpu"))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		return SYSINFO_RET_FAIL;
	}

	tmp = get_rparam(request, 1);

	if (NULL == tmp || '\0' == *tmp || 0 == strcmp(tmp, "avg1"))
		mode = ZBX_AVG1;
	else if (0 == strcmp(tmp, "avg5"))
		mode = ZBX_AVG5;
	else if (0 == strcmp(tmp, "avg15"))
		mode = ZBX_AVG15;
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		return SYSINFO_RET_FAIL;
	}

	if (-1 == perfstat_cpu_total(NULL, &ps_cpu_total, sizeof(ps_cpu_total), 1))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain system information: %s", zbx_strerror(errno)));
		return SYSINFO_RET_FAIL;
	}

	value = (double)ps_cpu_total.loadavg[mode] / (1 << SBITS);

	if (1 == per_cpu)
	{
		if (0 >= ps_cpu_total.ncpus)
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain number of CPUs."));
			return SYSINFO_RET_FAIL;
		}
		value /= ps_cpu_total.ncpus;
	}

	SET_DBL_RESULT(result, value);

	return SYSINFO_RET_OK;
#else
	SET_MSG_RESULT(result, zbx_strdup(NULL, "Agent was compiled without support for Perfstat API."));

	return SYSINFO_RET_FAIL;
#endif
}

int	system_cpu_switches(AGENT_REQUEST *request, AGENT_RESULT *result)
{
#ifdef HAVE_LIBPERFSTAT
	perfstat_cpu_total_t	ps_cpu_total;

	ZBX_UNUSED(request);

	if (-1 == perfstat_cpu_total(NULL, &ps_cpu_total, sizeof(ps_cpu_total), 1))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain system information: %s", zbx_strerror(errno)));
		return SYSINFO_RET_FAIL;
	}

	SET_UI64_RESULT(result, (zbx_uint64_t)ps_cpu_total.pswitch);

	return SYSINFO_RET_OK;
#else
	ZBX_UNUSED(request);

	SET_MSG_RESULT(result, zbx_strdup(NULL, "Agent was compiled without support for Perfstat API."));

	return SYSINFO_RET_FAIL;
#endif
}

int	system_cpu_intr(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	ZBX_UNUSED(request);
#ifdef HAVE_LIBPERFSTAT
	perfstat_cpu_total_t	ps_cpu_total;

	if (-1 == perfstat_cpu_total(NULL, &ps_cpu_total, sizeof(ps_cpu_total), 1))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain system information: %s", zbx_strerror(errno)));
		return SYSINFO_RET_FAIL;
	}

	SET_UI64_RESULT(result, (zbx_uint64_t)ps_cpu_total.devintrs);

	return SYSINFO_RET_OK;
#else
	SET_MSG_RESULT(result, zbx_strdup(NULL, "Agent was compiled without support for Perfstat API."));

	return SYSINFO_RET_FAIL;
#endif
}
