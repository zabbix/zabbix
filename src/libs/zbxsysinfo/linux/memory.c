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

#include "../sysinfo.h"

#include "proc.h"

static int	vm_memory_total(AGENT_RESULT *result)
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

static int	vm_memory_free(AGENT_RESULT *result)
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

static int	vm_memory_buffers(AGENT_RESULT *result)
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

static int	vm_memory_used(AGENT_RESULT *result)
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

static int	vm_memory_pused(AGENT_RESULT *result)
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

static int	vm_memory_available(AGENT_RESULT *result)
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

static int	vm_memory_pavailable(AGENT_RESULT *result)
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

	zbx_init_agent_result(&result_tmp);

	ret = vm_memory_available(&result_tmp);

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
	zbx_free_agent_result(&result_tmp);

	return ret;
}

static int	vm_memory_shared(AGENT_RESULT *result)
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

static int	vm_memory_proc_meminfo(const char *meminfo_entry, AGENT_RESULT *result)
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

int	vm_memory_size(AGENT_REQUEST *request, AGENT_RESULT *result)
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
		ret = vm_memory_total(result);
	else if (0 == strcmp(mode, "free"))
		ret = vm_memory_free(result);
	else if (0 == strcmp(mode, "buffers"))
		ret = vm_memory_buffers(result);
	else if (0 == strcmp(mode, "used"))
		ret = vm_memory_used(result);
	else if (0 == strcmp(mode, "pused"))
		ret = vm_memory_pused(result);
	else if (0 == strcmp(mode, "available"))
		ret = vm_memory_available(result);
	else if (0 == strcmp(mode, "pavailable"))
		ret = vm_memory_pavailable(result);
	else if (0 == strcmp(mode, "shared"))
		ret = vm_memory_shared(result);
	else if (0 == strcmp(mode, "cached"))
		ret = vm_memory_proc_meminfo("Cached:", result);
	else if (0 == strcmp(mode, "active"))
		ret = vm_memory_proc_meminfo("Active:", result);
	else if (0 == strcmp(mode, "anon"))
		ret = vm_memory_proc_meminfo("AnonPages:", result);
	else if (0 == strcmp(mode, "inactive"))
		ret = vm_memory_proc_meminfo("Inactive:", result);
	else if (0 == strcmp(mode, "slab"))
		ret = vm_memory_proc_meminfo("Slab:", result);
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		ret = SYSINFO_RET_FAIL;
	}

	return ret;
}
