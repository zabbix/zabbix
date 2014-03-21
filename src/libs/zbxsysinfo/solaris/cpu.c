/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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
#include "sysinfo.h"
#include "stats.h"

int	SYSTEM_CPU_NUM(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*tmp;
	int	name;
	long	ncpu;

	if (1 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters. Only optional type is expected."));
		return SYSINFO_RET_FAIL;
	}

	tmp = get_rparam(request, 0);

	if (NULL == tmp || '\0' == *tmp || 0 == strcmp(tmp, "online"))
		name = _SC_NPROCESSORS_ONLN;
	else if (0 == strcmp(tmp, "max"))
		name = _SC_NPROCESSORS_CONF;
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid type. Must be one of: max, online."));
		return SYSINFO_RET_FAIL;
	}

	if (-1 == (ncpu = sysconf(name)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Failed to get number of CPUs."));
		return SYSINFO_RET_FAIL;
	}

	SET_UI64_RESULT(result, ncpu);

	return SYSINFO_RET_OK;
}

int	SYSTEM_CPU_UTIL(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*tmp;
	int	cpu_num, state, mode;

	if (3 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters. Only optional cpu, type and mode are expected."));
		return SYSINFO_RET_FAIL;
	}

	tmp = get_rparam(request, 0);

	if (NULL == tmp || '\0' == *tmp || 0 == strcmp(tmp, "all"))
		cpu_num = 0;
	else if (SUCCEED != is_uint31_1(tmp, &cpu_num))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid cpu num."));
		return SYSINFO_RET_FAIL;
	}
	else
		cpu_num++;

	tmp = get_rparam(request, 1);

	if (NULL == tmp || '\0' == *tmp || 0 == strcmp(tmp, "user"))
		state = ZBX_CPU_STATE_USER;
	else if (0 == strcmp(tmp, "iowait"))
		state = ZBX_CPU_STATE_IOWAIT;
	else if (0 == strcmp(tmp, "system"))
		state = ZBX_CPU_STATE_SYSTEM;
	else if (0 == strcmp(tmp, "idle"))
		state = ZBX_CPU_STATE_IDLE;
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid type. Must be one of: idle, iowait, system, user."));
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
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid mode. Must be one of: avg1, avg5, avg15."));
		return SYSINFO_RET_FAIL;
	}

	return get_cpustat(result, cpu_num, state, mode);
}

#ifdef HAVE_KSTAT_H
static int	get_kstat_system_misc(char *key, int *value)
{
	kstat_ctl_t	*kc;
	kstat_t		*ksp;
	kstat_named_t	*kn = NULL;
	int		ret = FAIL;

	if (NULL == (kc = kstat_open()))
		return ret;

	if (NULL == (ksp = kstat_lookup(kc, "unix", 0, "system_misc")))
		goto close;

	if (-1 == kstat_read(kc, ksp, NULL))
		goto close;

	if (NULL == (kn = (kstat_named_t *)kstat_data_lookup(ksp, key)))
		goto close;

	*value = get_kstat_numeric_value(kn);

	ret = SUCCEED;
close:
	kstat_close(kc);

	return ret;
}
#endif

int	SYSTEM_CPU_LOAD(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*tmp;
	double	value;
	int	per_cpu = 1, cpu_num;
#if defined(HAVE_GETLOADAVG)
	int	mode;
	double	load[ZBX_AVG_COUNT];
#elif defined(HAVE_KSTAT_H)
	char	*key;
	int	load;
#endif

	if (2 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters. Only optional cpu and mode are expected."));
		return SYSINFO_RET_FAIL;
	}

	tmp = get_rparam(request, 0);

	if (NULL == tmp || '\0' == *tmp || 0 == strcmp(tmp, "all"))
		per_cpu = 0;
	else if (0 != strcmp(tmp, "percpu"))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid cpu. Must be one of: all, percpu."));
		return SYSINFO_RET_FAIL;
	}

#if defined(HAVE_GETLOADAVG)
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
#elif defined(HAVE_KSTAT_H)
	tmp = get_rparam(request, 1);

	if (NULL == tmp || '\0' == *tmp || 0 == strcmp(tmp, "avg1"))
		key = "avenrun_1min";
	else if (0 == strcmp(tmp, "avg5"))
		key = "avenrun_5min";
	else if (0 == strcmp(tmp, "avg15"))
		key = "avenrun_15min";
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid mode. Must be one of: avg1, avg5, avg15."));
		return SYSINFO_RET_FAIL;
	}

	if (FAIL == get_kstat_system_misc(key, &load))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Failed to get load average."));
		return SYSINFO_RET_FAIL;
	}

	value = (double)load / FSCALE;
#else
	SET_MSG_RESULT(result, zbx_strdup(NULL, "Agent does not support load average stats."));
	return SYSINFO_RET_FAIL;
#endif
	if (1 == per_cpu)
	{
		if (0 >= (cpu_num = sysconf(_SC_NPROCESSORS_ONLN)))
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Failed to get number of CPUs."));
			return SYSINFO_RET_FAIL;
		}
		value /= cpu_num;
	}

	SET_DBL_RESULT(result, value);

	return SYSINFO_RET_OK;
}

int	SYSTEM_CPU_SWITCHES(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	kstat_ctl_t	*kc;
	kstat_t		*k;
	cpu_stat_t	*cpu;
	int		cpu_count = 0;
	double		swt_count = 0.0;

	if (NULL != (kc = kstat_open()))
	{
		k = kc->kc_chain;

		while (NULL != k)
		{
			if (0 == strncmp(k->ks_name, "cpu_stat", 8) && -1 != kstat_read(kc, k, NULL))
			{
				cpu = (cpu_stat_t *)k->ks_data;
				swt_count += (double)cpu->cpu_sysinfo.pswitch;
				cpu_count += 1;
			}

			k = k->ks_next;
		}

		kstat_close(kc);
	}

	if (0 == cpu_count)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Failed to get number of context switches."));
		return SYSINFO_RET_FAIL;
	}

	SET_UI64_RESULT(result, swt_count);

	return SYSINFO_RET_OK;
}

int	SYSTEM_CPU_INTR(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	kstat_ctl_t	*kc;
	kstat_t		*k;
	cpu_stat_t	*cpu;
	int		cpu_count = 0;
	double		intr_count = 0.0;

	if (NULL != (kc = kstat_open()))
	{
		k = kc->kc_chain;

		while (NULL != k)
		{
			if (0 == strncmp(k->ks_name, "cpu_stat", 8) && -1 != kstat_read(kc, k, NULL))
			{
				cpu = (cpu_stat_t *)k->ks_data;
				intr_count += (double)cpu->cpu_sysinfo.intr;
				cpu_count += 1;
			}

			k = k->ks_next;
		}

		kstat_close(kc);
	}

	if (0 == cpu_count)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Failed to get number of interrupts."));
		return SYSINFO_RET_FAIL;
	}

	SET_UI64_RESULT(result, intr_count);

	return SYSINFO_RET_OK;
}
