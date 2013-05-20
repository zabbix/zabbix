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
	"vcenter.vm.list",
	"vcenter.vm.memory.size",
	"vcenter.vm.memory.size.compressed",
	"vcenter.vm.memory.size.ballooned",
	"vcenter.vm.memory.size.swapped",
	"vcenter.vm.storage.unshared",
	"vcenter.vm.storage.committed",
	"vcenter.vm.storage.uncommitted",
	"vcenter.vm.cpu.num",
	"vcenter.vm.cpu.usage",
	"vcenter.vm.uptime",
	"vcenter.vm.powerstate",

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
	"vm.memory.size.compressed",
	"vm.memory.size.ballooned",
	"vm.memory.size.swapped",
	"vm.powerstate",
	"vm.storage.unshared",
	"vm.storage.committed",
	"vm.storage.uncommitted",
	"vm.uptime",
	NULL
};

#if defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL)
static vmfunc_t	vmfuncs[] =
{
	check_vcenter_vmlist,
	check_vcenter_vmmemsize,
	check_vcenter_vmmemsizecompressed,
	check_vcenter_vmmemsizeballooned,
	check_vcenter_vmmemsizeswapped,
	check_vcenter_vmstorageunshared,
	check_vcenter_vmstoragecommitted,
	check_vcenter_vmstorageuncommitted,
	check_vcenter_vmcpunum,
	check_vcenter_vmcpuusage,
	check_vcenter_vmuptime,
	check_vcenter_vmpowerstate,

	check_vsphere_hostcpuusage,
	check_vsphere_hostfullname,
	check_vsphere_hosthwcpucores,
	check_vsphere_hosthwcpufreq,
	check_vsphere_hosthwcpumodel,
	check_vsphere_hosthwcputhreads,
	check_vsphere_hosthwmemory,
	check_vsphere_hosthwmodel,
	check_vsphere_hosthwuuid,
	check_vsphere_hosthwvendor,
	check_vsphere_hostmemoryused,
	check_vsphere_hoststatus,
	check_vsphere_hostuptime,
	check_vsphere_hostversion,
	check_vsphere_vmcpunum,
	check_vsphere_vmcpuusage,
	check_vsphere_vmlist,
	check_vsphere_vmmemsize,
	check_vsphere_vmmemsizecompressed,
	check_vsphere_vmmemsizeballooned,
	check_vsphere_vmmemsizeswapped,
	check_vsphere_vmpowerstate,
	check_vsphere_vmstorageunshared,
	check_vsphere_vmstoragecommitted,
	check_vsphere_vmstorageuncommitted,
	check_vsphere_vmuptime
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
		{
			if (SYSINFO_RET_OK == vmfunc(&request, result))
				ret = SUCCEED;
		}
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
