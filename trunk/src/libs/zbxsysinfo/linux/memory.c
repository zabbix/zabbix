/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
#include "proc.h"
#include "log.h"

static int	VM_MEMORY_TOTAL(AGENT_RESULT *result)
{
	struct sysinfo	info;

	if (0 != sysinfo(&info))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain system information: %s", zbx_strerror(errno)));
		return SYSINFO_RET_FAIL;
	}

	SET_UI64_RESULT(result, (zbx_uint64_t)info.totalram * info.mem_unit);

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_FREE(AGENT_RESULT *result)
{
	struct sysinfo	info;

	if (0 != sysinfo(&info))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain system information: %s", zbx_strerror(errno)));
		return SYSINFO_RET_FAIL;
	}

	SET_UI64_RESULT(result, (zbx_uint64_t)info.freeram * info.mem_unit);

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_BUFFERS(AGENT_RESULT *result)
{
	struct sysinfo	info;

	if (0 != sysinfo(&info))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain system information: %s", zbx_strerror(errno)));
		return SYSINFO_RET_FAIL;
	}

	SET_UI64_RESULT(result, (zbx_uint64_t)info.bufferram * info.mem_unit);

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_USED(AGENT_RESULT *result)
{
	struct sysinfo	info;

	if (0 != sysinfo(&info))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain system information: %s", zbx_strerror(errno)));
		return SYSINFO_RET_FAIL;
	}

	SET_UI64_RESULT(result, (zbx_uint64_t)(info.totalram - info.freeram) * info.mem_unit);

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_PUSED(AGENT_RESULT *result)
{
	struct sysinfo	info;

	if (0 != sysinfo(&info))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain system information: %s", zbx_strerror(errno)));
		return SYSINFO_RET_FAIL;
	}

	if (0 == info.totalram)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot calculate percentage because total is zero."));
		return SYSINFO_RET_FAIL;
	}

	SET_DBL_RESULT(result, (info.totalram - info.freeram) / (double)info.totalram * 100);

	return SYSINFO_RET_OK;
}

static int	VM_MEMORY_AVAILABLE(AGENT_RESULT *result)
{
	FILE		*f;
	zbx_uint64_t	value;
	struct sysinfo	info;
	int		res, ret = SYSINFO_RET_FAIL;

	/* try MemAvailable (present since Linux 3.14), falling back to a calculation based on sysinfo() and Cached */

	if (NULL == (f = fopen("/proc/meminfo", "r")))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot open /proc/meminfo: %s", zbx_strerror(errno)));
		return SYSINFO_RET_FAIL;
	}

	if (FAIL == (res = byte_value_from_proc_file(f, "MemAvailable:", "Cached:", &value)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain the value of MemAvailable from /proc/meminfo."));
		goto close;
	}

	if (SUCCEED == res)
	{
		SET_UI64_RESULT(result, value);
		ret = SYSINFO_RET_OK;
		goto close;
	}

	if (FAIL == (res = byte_value_from_proc_file(f, "Cached:", NULL, &value)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain the value of Cached from /proc/meminfo."));
		goto close;
	}

	if (NOTSUPPORTED == res)
		value = 0;

	if (0 != sysinfo(&info))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain system information: %s", zbx_strerror(errno)));
		goto close;
	}

	SET_UI64_RESULT(result, (zbx_uint64_t)(info.freeram + info.bufferram) * info.mem_unit + value);
	ret = SYSINFO_RET_OK;
close:
	zbx_fclose(f);

	return ret;
}

static int	VM_MEMORY_PAVAILABLE(AGENT_RESULT *result)
{
	struct sysinfo	info;
	AGENT_RESULT	result_tmp;
	zbx_uint64_t	available, total;
	int		ret = SYSINFO_RET_FAIL;

	if (0 != sysinfo(&info))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain system information: %s", zbx_strerror(errno)));
		return SYSINFO_RET_FAIL;
	}

	init_result(&result_tmp);

	ret = VM_MEMORY_AVAILABLE(&result_tmp);

	if (SYSINFO_RET_FAIL == ret)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, result_tmp.msg));
		goto clean;
	}

	available = result_tmp.ui64;
	total = (zbx_uint64_t)info.totalram * info.mem_unit;

	if (0 == total)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot calculate percentage because total is zero."));
		ret = SYSINFO_RET_FAIL;
		goto clean;
	}

	SET_DBL_RESULT(result, available / (double)total * 100);
clean:
	free_result(&result_tmp);

	return ret;
}

static int	VM_MEMORY_SHARED(AGENT_RESULT *result)
{
#ifdef KERNEL_2_4
	struct sysinfo	info;

	if (0 != sysinfo(&info))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain system information: %s", zbx_strerror(errno)));
		return SYSINFO_RET_FAIL;
	}

	SET_UI64_RESULT(result, (zbx_uint64_t)info.sharedram * info.mem_unit);

	return SYSINFO_RET_OK;
#else
	SET_MSG_RESULT(result, zbx_strdup(NULL, "Supported for Linux 2.4 only."));
	return SYSINFO_RET_FAIL;
#endif
}

static int	VM_MEMORY_PROC_MEMINFO(const char *meminfo_entry, AGENT_RESULT *result)
{
	FILE		*f;
	zbx_uint64_t	value;
	int		ret = SYSINFO_RET_FAIL;

	if (NULL == (f = fopen("/proc/meminfo", "r")))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot open /proc/meminfo: %s", zbx_strerror(errno)));
		return SYSINFO_RET_FAIL;
	}

	if (SUCCEED == byte_value_from_proc_file(f, meminfo_entry, NULL, &value))
	{
		SET_UI64_RESULT(result, value);
		ret = SYSINFO_RET_OK;
	}
	else
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain value from /proc/meminfo."));

	zbx_fclose(f);

	return ret;
}

int	VM_MEMORY_SIZE(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*mode;
	int	ret;

	if (1 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	mode = get_rparam(request, 0);

	if (NULL == mode || '\0' == *mode || 0 == strcmp(mode, "total"))
		ret = VM_MEMORY_TOTAL(result);
	else if (0 == strcmp(mode, "free"))
		ret = VM_MEMORY_FREE(result);
	else if (0 == strcmp(mode, "buffers"))
		ret = VM_MEMORY_BUFFERS(result);
	else if (0 == strcmp(mode, "used"))
		ret = VM_MEMORY_USED(result);
	else if (0 == strcmp(mode, "pused"))
		ret = VM_MEMORY_PUSED(result);
	else if (0 == strcmp(mode, "available"))
		ret = VM_MEMORY_AVAILABLE(result);
	else if (0 == strcmp(mode, "pavailable"))
		ret = VM_MEMORY_PAVAILABLE(result);
	else if (0 == strcmp(mode, "shared"))
		ret = VM_MEMORY_SHARED(result);
	else if (0 == strcmp(mode, "cached"))
		ret = VM_MEMORY_PROC_MEMINFO("Cached:", result);
	else if (0 == strcmp(mode, "active"))
		ret = VM_MEMORY_PROC_MEMINFO("Active:", result);
	else if (0 == strcmp(mode, "anon"))
		ret = VM_MEMORY_PROC_MEMINFO("AnonPages:", result);
	else if (0 == strcmp(mode, "inactive"))
		ret = VM_MEMORY_PROC_MEMINFO("Inactive:", result);
	else if (0 == strcmp(mode, "slab"))
		ret = VM_MEMORY_PROC_MEMINFO("Slab:", result);
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		ret = SYSINFO_RET_FAIL;
	}

	return ret;
}
