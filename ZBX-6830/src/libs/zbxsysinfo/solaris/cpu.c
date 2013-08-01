/*
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/

#include "common.h"
#include "sysinfo.h"
#include "stats.h"

int	SYSTEM_CPU_NUM(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	char	mode[8];
	int	name;
	long	ncpu;

	if (1 < num_param(param))
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, mode, sizeof(mode)))
		*mode = '\0';

	if ('\0' == *mode || 0 == strcmp(mode, "online"))	/* default parameter */
		name = _SC_NPROCESSORS_ONLN;
	else if (0 == strcmp(mode, "max"))
		name = _SC_NPROCESSORS_CONF;
	else
		return SYSINFO_RET_FAIL;

	if (-1 == (ncpu = sysconf(name)))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, ncpu);

	return SYSINFO_RET_OK;
}

int	SYSTEM_CPU_UTIL(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	char	tmp[16];
	int	cpu_num, mode, state;

	if (num_param(param) > 3)
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, tmp, sizeof(tmp)))
		*tmp = '\0';

	if ('\0' == *tmp || 0 == strcmp(tmp, "all"))	/* default parameter */
		cpu_num = 0;
	else if (1 > (cpu_num = atoi(tmp) + 1))
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 2, tmp, sizeof(tmp)))
		*tmp = '\0';

	if ('\0' == *tmp || 0 == strcmp(tmp, "user"))	/* default parameter */
		state = ZBX_CPU_STATE_USER;
	else if (0 == strcmp(tmp, "wait"))
		state = ZBX_CPU_STATE_IOWAIT;
	else if (0 == strcmp(tmp, "kernel"))
		state = ZBX_CPU_STATE_SYSTEM;
	else if (0 == strcmp(tmp, "idle"))
		state = ZBX_CPU_STATE_IDLE;
	else
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 3, tmp, sizeof(tmp)))
		*tmp = '\0';

	if ('\0' == *tmp || 0 == strcmp(tmp, "avg1"))	/* default parameter */
		mode = ZBX_AVG1;
	else if (0 == strcmp(tmp, "avg5"))
		mode = ZBX_AVG5;
	else if (0 == strcmp(tmp, "avg15"))
		mode = ZBX_AVG15;
	else
		return SYSINFO_RET_FAIL;

	return get_cpustat(result, cpu_num, state, mode);
}

#ifdef HAVE_KSTAT_H
static int	get_kstat_system_misc(char *s, int *value)
{
	kstat_ctl_t	*kc;
	kstat_t		*kp;
	kstat_named_t	*kn = NULL;
	int		n, i;

	if (NULL == (kc = kstat_open()))
		return FAIL;

	if (NULL == (kp = kstat_lookup(kc, "unix", 0, "system_misc")))
	{
		kstat_close(kc);
		return FAIL;
	}

	if (-1 == kstat_read(kc, kp, NULL))
	{
		kstat_close(kc);
		return FAIL;
	}

	if (NULL == (kn = (kstat_named_t*)kstat_data_lookup(kp, s)))
	{
		kstat_close(kc);
		return FAIL;
	}

	kstat_close(kc);

	*value = kn->value.ul;

	return SUCCEED;
}
#endif

int	SYSTEM_CPU_LOAD(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#if defined(HAVE_GETLOADAVG)
	double	load[3];
#elif defined(HAVE_KSTAT_H)
	char	*key;
	int	value;
#endif
	char	tmp[MAX_STRING_LEN];

	if (num_param(param) > 2)
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, tmp, sizeof(tmp)))
		return SYSINFO_RET_FAIL;

	if ('\0' != *tmp && 0 != strcmp(tmp, "all"))	/* default parameter */
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 2, tmp, sizeof(tmp)))
		*tmp = '\0';

#if defined(HAVE_GETLOADAVG)
	if (-1 == getloadavg(load, 3))
		return SYSINFO_RET_FAIL;

	if ('\0' == *tmp || 0 == strcmp(tmp, "avg1"))	/* default parameter */
	{
		SET_DBL_RESULT(result, load[0]);
	}
	else if ('\0' == *tmp || 0 == strcmp(tmp, "avg5"))
	{
		SET_DBL_RESULT(result, load[1]);
	}
	else if ('\0' == *tmp || 0 == strcmp(tmp, "avg15"))
	{
		SET_DBL_RESULT(result, load[2]);
	}
	else
		return SYSINFO_RET_FAIL;
#elif defined(HAVE_KSTAT_H)
	if ('\0' == *tmp || 0 == strcmp(tmp, "avg1"))	/* default parameter */
		key = "avenrun_1min";
	else if ('\0' == *tmp || 0 == strcmp(tmp, "avg5"))
		key = "avenrun_5min";
	else if ('\0' == *tmp || 0 == strcmp(tmp, "avg15"))
		key = "avenrun_15min";
	else
		return SYSINFO_RET_FAIL;

	if (FAIL == get_kstat_system_misc(key, &value))
		return SYSINFO_RET_FAIL;

	SET_DBL_RESULT(result, (double)value/FSCALE);
#else
	return SYSINFO_RET_FAIL;
#endif
	return SYSINFO_RET_OK;
}

int	SYSTEM_CPU_SWITCHES(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	kstat_ctl_t	*kc;
	kstat_t		*k;
	cpu_stat_t	*cpu;

	int	cpu_count = 0;
	double	swt_count = 0.0;

	kc = kstat_open();

	if(kc != NULL)
	{
		k = kc->kc_chain;
		while (k != NULL)
		{
			if( (strncmp(k->ks_name, "cpu_stat", 8) == 0) &&
					(kstat_read(kc, k, NULL) != -1) )
			{
				cpu = (cpu_stat_t*) k->ks_data;
				swt_count += (double) cpu->cpu_sysinfo.pswitch;
				cpu_count += 1;
			}
			k = k->ks_next;
		}
		kstat_close(kc);
	}

	if(cpu_count == 0)
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, swt_count);

	return SYSINFO_RET_OK;
}

int	SYSTEM_CPU_INTR(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	kstat_ctl_t	*kc;
	kstat_t		*k;
	cpu_stat_t	*cpu;

	int	cpu_count = 0;
	double	intr_count = 0.0;

	kc = kstat_open();

	if(kc != NULL)
	{
		k = kc->kc_chain;
		while (k != NULL)
		{
			if( (strncmp(k->ks_name, "cpu_stat", 8) == 0) &&
					(kstat_read(kc, k, NULL) != -1) )
			{
				cpu = (cpu_stat_t*) k->ks_data;
				intr_count += (double) cpu->cpu_sysinfo.intr;
				cpu_count += 1;
			}
			k = k->ks_next;
		}
		kstat_close(kc);
	}

	if(cpu_count == 0)
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, intr_count);

	return SYSINFO_RET_OK;
}
