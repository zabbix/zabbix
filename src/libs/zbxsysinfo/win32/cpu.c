/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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
#include "log.h"
#include "sysinfo.h"
#include "stats.h"
#include "perfstat.h"

/******************************************************************************
 *                                                                            *
 * Function: get_cpu_num_win32                                                *
 *                                                                            *
 * Purpose: returns the number of active logical CPUs (threads)               *
 *                                                                            *
 * Return value: number of logical CPUs                                       *
 *                                                                            *
 ******************************************************************************/
int	get_cpu_num_win32(void)
{
	/* shortcut just to avoid extra verbosity */
	typedef PSYSTEM_LOGICAL_PROCESSOR_INFORMATION_EX PSYS_LPI_EX;
	/* define function pointer types for the GetActiveProcessorCount() and GetLogicalProcessorInformationEx() API */
	typedef DWORD (WINAPI *GETACTIVEPC)(WORD);
	typedef BOOL (WINAPI *GETLPIEX)(LOGICAL_PROCESSOR_RELATIONSHIP, PSYS_LPI_EX, PDWORD);

	GETACTIVEPC	get_act;
	GETLPIEX	get_lpiex;
	SYSTEM_INFO	sysInfo;
	DWORD		buffer_length;
	PSYSTEM_LOGICAL_PROCESSOR_INFORMATION_EX buffer;
	int		cpu_count = 0;

	/* The rationale for checking dynamically if specific functions are implemented */
	/* in kernel32.lib because these may not be available in certain Windows versions. */
	/* E.g. GetActiveProcessorCount() available from Windows 7 onward (and not in Windows Vista or XP) */
	/* We can't resolve this using conditional compilation unless we release multiple agents */
	/* targeting different sets of Windows APIs. */

	/* First, lets try GetLogicalProcessorInformationEx() method. It's the most reliable way */
	/* because it counts logical CPUs (aka threads) regardless of whether the application is */
	/* 32 or 64-bit. GetActiveProcessorCount() may return incorrect value (e.g. 64 CPUs for systems */
	/* with 128 CPUs) if executed under under WoW64. */

	get_lpiex = (GETLPIEX)GetProcAddress(GetModuleHandle(TEXT("kernel32.dll")), "GetLogicalProcessorInformationEx");

	if (NULL != get_lpiex)
	{
		/* first run with empty arguments to figure the buffer length */
		if (get_lpiex(RelationAll, NULL, &buffer_length) || ERROR_INSUFFICIENT_BUFFER != GetLastError())
			goto fallback;

		buffer = (PSYS_LPI_EX)malloc((size_t)buffer_length);
		if (NULL != buffer && get_lpiex(RelationProcessorCore, buffer, &buffer_length))
		{
			for (unsigned i = 0; i < buffer_length;)
			{
				PSYS_LPI_EX ptr = (PSYS_LPI_EX)((PBYTE)buffer + i);
				for (WORD group = 0; group < ptr->Processor.GroupCount; group++)
				{
					zabbix_log(LOG_LEVEL_DEBUG, "\tgroup %d, mask %X", group,
							ptr->Processor.GroupMask[group].Mask);
					for (KAFFINITY mask = ptr->Processor.GroupMask[group].Mask; mask != 0; mask >>= 1)
						cpu_count += mask & 1;
				}
				i += (unsigned)ptr->Size;
			}
			zabbix_log(LOG_LEVEL_DEBUG,"found thread count %d\n", cpu_count);
			return cpu_count;
		}
	}

fallback:
	get_act = (GETACTIVEPC)GetProcAddress(GetModuleHandle(TEXT("kernel32.dll")), "GetActiveProcessorCount");

	if (NULL != get_act)
		return (int)get_act(ALL_PROCESSOR_GROUPS);

	zabbix_log(LOG_LEVEL_DEBUG, "Cannot find address of GetActiveProcessorCount function");

	GetNativeSystemInfo(&sysInfo);

	return (int)sysInfo.dwNumberOfProcessors;
}

/******************************************************************************
 *                                                                            *
 * Function: get_cpu_group_num_win32                                          *
 *                                                                            *
 * Purpose: returns the number of active processor groups                     *
 *                                                                            *
 * Return value: number of groups, 1 if groups are not supported              *
 *                                                                            *
 ******************************************************************************/
int	get_cpu_group_num_win32(void)
{
	/* Define a function pointer type for the GetActiveProcessorGroupCount API */
	typedef WORD (WINAPI *GETACTIVEPGC)();

	GETACTIVEPGC	get_act;

	/* please see comments in get_cpu_num_win32() */
	get_act = (GETACTIVEPGC)GetProcAddress(GetModuleHandle(TEXT("kernel32.dll")), "GetActiveProcessorGroupCount");

	if (NULL != get_act)
		return (int)get_act();
	else
		zabbix_log(LOG_LEVEL_DEBUG, "GetActiveProcessorGroupCount() not supported, assuming 1");

	return 1;
}

int	SYSTEM_CPU_NUM(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*tmp;

	if (1 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	/* only "online" (default) for parameter "type" is supported */
	if (NULL != (tmp = get_rparam(request, 0)) && '\0' != *tmp && 0 != strcmp(tmp, "online"))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		return SYSINFO_RET_FAIL;
	}

	SET_UI64_RESULT(result, get_cpu_num_win32());

	return SYSINFO_RET_OK;
}

int	SYSTEM_CPU_UTIL(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*tmp, *error = NULL;
	int	cpu_num, interval;
	double	value;

	if (0 == CPU_COLLECTOR_STARTED(collector))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Collector is not started."));
		return SYSINFO_RET_FAIL;
	}

	if (3 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	if (NULL == (tmp = get_rparam(request, 0)) || '\0' == *tmp || 0 == strcmp(tmp, "all"))
		cpu_num = ZBX_CPUNUM_ALL;
	else if (SUCCEED != is_uint_range(tmp, &cpu_num, 0, collector->cpus.count - 1))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		return SYSINFO_RET_FAIL;
	}

	/* only "system" (default) for parameter "type" is supported */
	if (NULL != (tmp = get_rparam(request, 1)) && '\0' != *tmp && 0 != strcmp(tmp, "system"))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		return SYSINFO_RET_FAIL;
	}

	if (NULL == (tmp = get_rparam(request, 2)) || '\0' == *tmp || 0 == strcmp(tmp, "avg1"))
	{
		interval = 1 * SEC_PER_MIN;
	}
	else if (0 == strcmp(tmp, "avg5"))
	{
		interval = 5 * SEC_PER_MIN;
	}
	else if (0 == strcmp(tmp, "avg15"))
	{
		interval = 15 * SEC_PER_MIN;
	}
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid third parameter."));
		return SYSINFO_RET_FAIL;
	}

	if (SUCCEED == get_cpu_perf_counter_value(cpu_num, interval, &value, &error))
	{
		SET_DBL_RESULT(result, value);
		return SYSINFO_RET_OK;
	}

	SET_MSG_RESULT(result, NULL != error ? error :
			zbx_strdup(NULL, "Cannot obtain performance information from collector."));

	return SYSINFO_RET_FAIL;
}

int	SYSTEM_CPU_LOAD(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*tmp, *error = NULL;
	double	value;
	int	cpu_num, ret = FAIL;

	if (0 == CPU_COLLECTOR_STARTED(collector))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Collector is not started."));
		return SYSINFO_RET_FAIL;
	}

	if (2 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	if (NULL == (tmp = get_rparam(request, 0)) || '\0' == *tmp || 0 == strcmp(tmp, "all"))
	{
		cpu_num = 1;
	}
	else if (0 == strcmp(tmp, "percpu"))
	{
		if (0 >= (cpu_num = get_cpu_num_win32()))
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain number of CPUs."));
			return SYSINFO_RET_FAIL;
		}
	}
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		return SYSINFO_RET_FAIL;
	}

	if (NULL == (tmp = get_rparam(request, 1)) || '\0' == *tmp || 0 == strcmp(tmp, "avg1"))
	{
		ret = get_perf_counter_value(collector->cpus.queue_counter, 1 * SEC_PER_MIN, &value, &error);
	}
	else if (0 == strcmp(tmp, "avg5"))
	{
		ret = get_perf_counter_value(collector->cpus.queue_counter, 5 * SEC_PER_MIN, &value, &error);
	}
	else if (0 == strcmp(tmp, "avg15"))
	{
		ret = get_perf_counter_value(collector->cpus.queue_counter, 15 * SEC_PER_MIN, &value, &error);
	}
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		return SYSINFO_RET_FAIL;
	}

	if (SUCCEED == ret)
	{
		SET_DBL_RESULT(result, value / cpu_num);
		return SYSINFO_RET_OK;
	}

	SET_MSG_RESULT(result, NULL != error ? error :
			zbx_strdup(NULL, "Cannot obtain performance information from collector."));

	return SYSINFO_RET_FAIL;
}
