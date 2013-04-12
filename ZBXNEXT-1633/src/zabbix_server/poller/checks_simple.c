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

#include "checks_simple_vmware.h"
#include "checks_simple.h"
#include "simple.h"
#include "log.h"

typedef int	(*vmfunc_t)(AGENT_REQUEST *, AGENT_RESULT *);

#define ZBX_VIRT_VMWARE_PREFIX	"virt.vmware."

static char	*vmkeys[] =
{
	"host.cpu.usage",
	"host.fullname",
	"host.hw.cpucores",
	"host.hw.cpufreq",
	"host.hw.cpumodel",
	"host.hw.cputhreads",
	"host.hw.memory",
	"host.hw.model",
	"host.hw.uuid",
	"host.hw.vendor",
	"host.memory.used",
	"host.status",
	"host.uptime",
	"host.version",
	"vm.cpu.num",
	"vm.cpu.usage",
	"vm.list",
	"vm.memory.size",
	"vm.memory.size.ballooned",
	"vm.memory.size.swapped",
	"vm.storage.additional",
	"vm.storage.committed",
	"vm.storage.uncommitted",
	"vm.uptime",
	NULL
};

#if defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL)
static vmfunc_t	vmfuncs[] =
{
	check_vmware_hostcpuusage,
	check_vmware_hostfullname,
	check_vmware_hosthwcpucores,
	check_vmware_hosthwcpufreq,
	check_vmware_hosthwcpumodel,
	check_vmware_hosthwcputhreads,
	check_vmware_hosthwmemory,
	check_vmware_hosthwmodel,
	check_vmware_hosthwuuid,
	check_vmware_hosthwvendor,
	check_vmware_hostmemoryused,
	check_vmware_hoststatus,
	check_vmware_hostuptime,
	check_vmware_hostversion,
	check_vmware_vmcpunum,
	check_vmware_vmcpuusage,
	check_vmware_vmlist,
	check_vmware_vmmemsize,
	check_vmware_vmmemsizeballooned,
	check_vmware_vmmemsizeswapped,
	check_vmware_vmstorageunshared,
	check_vmware_vmstoragecommitted,
	check_vmware_vmstorageuncommitted,
	check_vmware_vmuptime
};
#endif

/******************************************************************************
 *                                                                            *
 * Function: get_vmware_function                                              *
 *                                                                            *
 * Purpose: Retrieves a handler of the item key                               *
 *                                                                            *
 * Paramaters: key    - [IN] an item key (without parameters)                 *
 *             vmfunc - [OUT] a handler of the item key; can be NULL if       *
 *                            libxml2 or libcurl is not compiled in           *
 *                                                                            *
 * Return value: SUCCEED if key is a valid VMware key, FAIL - otherwise       *
 *                                                                            *
 ******************************************************************************/
static int	get_vmware_function(const char *key, vmfunc_t *vmfunc)
{
	int	i;

	if (0 != strncmp(key, ZBX_VIRT_VMWARE_PREFIX, sizeof(ZBX_VIRT_VMWARE_PREFIX) - 1))
		return FAIL;

	for (i = 0; NULL != vmkeys[i]; i++)
	{
		if (0 == strcmp(key + sizeof(ZBX_VIRT_VMWARE_PREFIX) - 1, vmkeys[i]))
		{
#if defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL)
			*vmfunc = vmfuncs[i];
#else
			*vmfunc = NULL;
#endif
			return SUCCEED;
		}
	}

	return FAIL;
}

int	get_value_simple(DC_ITEM *item, AGENT_RESULT *result)
{
	const char	*__function_name = "get_value_simple";

	AGENT_REQUEST	request;
	vmfunc_t	vmfunc;
	int		ret = NOTSUPPORTED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() key_orig:'%s' addr:'%s'",
			__function_name, item->key_orig, item->interface.addr);

	init_request(&request);

	if (SUCCEED != parse_item_key(item->key, &request))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Key is badly formatted"));
		goto notsupported;
	}

	if (0 == strcmp(request.key, "net.tcp.service"))
	{
		if (SYSINFO_RET_OK == check_service(&request, item->interface.addr, result, 0))
			ret = SUCCEED;
	}
	else if (0 == strcmp(request.key, "net.tcp.service.perf"))
	{
		if (SYSINFO_RET_OK == check_service(&request, item->interface.addr, result, 1))
			ret = SUCCEED;
	}
	else if (SUCCEED == get_vmware_function(request.key, &vmfunc))
	{
		if (NULL != vmfunc)
			ret = vmfunc(&request, result);
		else
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Support for VMware checks was not compiled in"));
	}
	else
	{
		/* it will execute item from a loadable module if any */
		if (SUCCEED == process(item->key, PROCESS_MODULE_COMMAND, result))
			ret = SUCCEED;
	}

	if (NOTSUPPORTED == ret && !ISSET_MSG(result))
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Simple check is not supported"));
notsupported:
	free_request(&request);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}
