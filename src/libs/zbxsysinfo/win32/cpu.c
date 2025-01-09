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
#include "win32_cpu.h"

#include "../common/stats.h"
#include "perfstat/perfstat.h"

/* shortcut to avoid extra verbosity */
typedef PSYSTEM_LOGICAL_PROCESSOR_INFORMATION_EX PSYS_LPI_EX;

/* pointer to GetLogicalProcessorInformationEx(), it's not guaranteed to be available */
typedef BOOL (WINAPI *GETLPIEX)(LOGICAL_PROCESSOR_RELATIONSHIP, PSYS_LPI_EX, PDWORD);
static GETLPIEX		get_lpiex;

/******************************************************************************
 *                                                                            *
 * Purpose: finds number of active logical CPUs                               *
 *                                                                            *
 * Return value: number of CPUs or 0 on failure                               *
 *                                                                            *
 ******************************************************************************/
int	get_cpu_num_win32(void)
{
	/* pointer to GetActiveProcessorCount() */
	typedef DWORD (WINAPI *GETACTIVEPC)(WORD);

	ZBX_THREAD_LOCAL static GETACTIVEPC	get_act;
	SYSTEM_INFO				sysInfo;
	PSYS_LPI_EX				buffer = NULL;
	int					cpu_count = 0;

	/* The rationale for checking dynamically if specific functions are implemented */
	/* in kernel32.lib is because these may not be available in certain Windows versions. */
	/* E.g. GetActiveProcessorCount() is available from Windows 7 onward (and not in Windows Vista or XP) */
	/* We can't resolve this using conditional compilation unless we release multiple agents */
	/* targeting different sets of Windows APIs. */

	/* First, let's try GetLogicalProcessorInformationEx() method. It's the most reliable way */
	/* because it counts logical CPUs (aka threads) regardless of whether the application is */
	/* 32 or 64-bit. GetActiveProcessorCount() may return incorrect value (e.g. 64 CPUs for systems */
	/* with 128 CPUs) if executed under WoW64. */

	if (NULL == get_lpiex)
	{
		get_lpiex = (GETLPIEX)GetProcAddress(GetModuleHandle(L"kernel32.dll"),
				"GetLogicalProcessorInformationEx");
	}

	if (NULL != get_lpiex)
	{
		DWORD buffer_length = 0;

		/* first run with empty arguments to figure the buffer length */
		if (get_lpiex(RelationProcessorCore, NULL, &buffer_length) ||
				ERROR_INSUFFICIENT_BUFFER != GetLastError())
		{
			goto fallback;
		}

		buffer = (PSYS_LPI_EX)zbx_malloc(buffer, (size_t)buffer_length);

		if (get_lpiex(RelationProcessorCore, buffer, &buffer_length))
		{
			PSYS_LPI_EX	ptr;

			for (unsigned int i = 0; i < buffer_length; i += (unsigned int)ptr->Size)
			{
				ptr = (PSYS_LPI_EX)((PBYTE)buffer + i);

				for (WORD group = 0; group < ptr->Processor.GroupCount; group++)
				{
					for (KAFFINITY mask = ptr->Processor.GroupMask[group].Mask; mask != 0;
							mask >>= 1)
					{
						cpu_count += mask & 1;
					}
				}
			}

			goto finish;
		}
	}

fallback:
	if (NULL == get_act)
		get_act = (GETACTIVEPC)GetProcAddress(GetModuleHandle(L"kernel32.dll"), "GetActiveProcessorCount");

	if (NULL != get_act)
	{
		/* cpu_count set to 0 if GetActiveProcessorCount() fails */
		cpu_count = (int)get_act(ALL_PROCESSOR_GROUPS);
		goto finish;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "GetActiveProcessorCount() not supported, fall back to GetNativeSystemInfo()");

	GetNativeSystemInfo(&sysInfo);
	cpu_count = (int)sysInfo.dwNumberOfProcessors;
finish:
	zbx_free(buffer);

	zabbix_log(LOG_LEVEL_DEBUG, "logical CPU count %d", cpu_count);

	return cpu_count;
}

/******************************************************************************
 *                                                                            *
 * Purpose: returns number of active processor groups                         *
 *                                                                            *
 * Return value: number of groups, 1 if groups are not supported              *
 *                                                                            *
 ******************************************************************************/
int	get_cpu_group_num_win32(void)
{
	/* pointer type for the GetActiveProcessorGroupCount() */
	typedef WORD (WINAPI *GETACTIVEPGC)();
	ZBX_THREAD_LOCAL static GETACTIVEPGC	get_act;

	/* Check if GetActiveProcessorGroupCount() is available. See comments in get_cpu_num_win32() for details. */

	if (NULL == get_act)
	{
		get_act = (GETACTIVEPGC)GetProcAddress(GetModuleHandle(L"kernel32.dll"),
				"GetActiveProcessorGroupCount");
	}

	if (NULL != get_act)
	{
		int groups = (int)get_act();

		if (0 >= groups)
			zabbix_log(LOG_LEVEL_WARNING, "GetActiveProcessorGroupCount() failed");
		else
			return groups;
	}
	else
	{
		zabbix_log(LOG_LEVEL_DEBUG, "GetActiveProcessorGroupCount() not supported, assuming 1");
	}

	return 1;
}

/******************************************************************************
 *                                                                            *
 * Purpose: returns number of NUMA nodes                                      *
 *                                                                            *
 * Return value: number of NUMA nodes, 1 if NUMA not supported                *
 *                                                                            *
 ******************************************************************************/
int	get_numa_node_num_win32(void)
{
	int	numa_node_count = 1;

	if (NULL == get_lpiex)
	{
		get_lpiex = (GETLPIEX)GetProcAddress(GetModuleHandle(L"kernel32.dll"),
				"GetLogicalProcessorInformationEx");
	}

	if (NULL != get_lpiex)
	{
		DWORD 		buffer_length = 0;
		PSYS_LPI_EX	buffer = NULL;

		/* first run with empty arguments to figure the buffer length */
		if (get_lpiex(RelationNumaNode, NULL, &buffer_length) || ERROR_INSUFFICIENT_BUFFER != GetLastError())
			goto finish;

		buffer = (PSYS_LPI_EX)zbx_malloc(buffer, (size_t)buffer_length);

		if (get_lpiex(RelationNumaNode, buffer, &buffer_length))
		{
			numa_node_count = 0;

			for (unsigned int i = 0; i < buffer_length; numa_node_count++)
			{
				PSYS_LPI_EX ptr = (PSYS_LPI_EX)((PBYTE)buffer + i);
				i += (unsigned)ptr->Size;
			}
		}

		zbx_free(buffer);
	}
finish:
	zabbix_log(LOG_LEVEL_DEBUG, "NUMA node count %d", numa_node_count);

	return numa_node_count;
}

int	system_cpu_num(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*tmp;
	int	cpu_num;

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

	if (0 >= (cpu_num = get_cpu_num_win32()))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Error getting number of CPUs."));
		return SYSINFO_RET_FAIL;
	}

	SET_UI64_RESULT(result, cpu_num);

	return SYSINFO_RET_OK;
}

int	system_cpu_util(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*tmp, *error = NULL;
	int	cpu_num, interval;
	double	value;

	if (0 == cpu_collector_started())
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
	else if (SUCCEED != zbx_is_uint_range(tmp, &cpu_num, 0, (get_collector())->cpus.count - 1))
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

int	system_cpu_load(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*tmp, *error = NULL;
	double	value;
	int	cpu_num, ret = FAIL;

	if (0 == cpu_collector_started())
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
		ret = get_perf_counter_value((get_collector())->cpus.queue_counter, 1 * SEC_PER_MIN, &value, &error);
	}
	else if (0 == strcmp(tmp, "avg5"))
	{
		ret = get_perf_counter_value((get_collector())->cpus.queue_counter, 5 * SEC_PER_MIN, &value, &error);
	}
	else if (0 == strcmp(tmp, "avg15"))
	{
		ret = get_perf_counter_value((get_collector())->cpus.queue_counter, 15 * SEC_PER_MIN, &value, &error);
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
