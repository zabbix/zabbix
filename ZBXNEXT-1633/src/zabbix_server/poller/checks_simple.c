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

#define ZBX_VMWARE_PREFIX	"vmware."

static char	*vmkeys[] =
{
	"vcenter.cluster.discovery",
	"vcenter.cluster.status",
	"vcenter.eventlog",

	"vcenter.hv.cluster.name",
	"vcenter.hv.cpu.usage",
	"vcenter.hv.discovery",
	"vcenter.hv.fullname",
	"vcenter.hv.hw.cpu.num",
	"vcenter.hv.hw.cpu.freq",
	"vcenter.hv.hw.cpu.model",
	"vcenter.hv.hw.cpu.threads",
	"vcenter.hv.hw.memory",
	"vcenter.hv.hw.model",
	"vcenter.hv.hw.uuid",
	"vcenter.hv.hw.vendor",
	"vcenter.hv.memory.size.ballooned",
	"vcenter.hv.memory.used",
	"vcenter.hv.status",
	"vcenter.hv.uptime",
	"vcenter.hv.version",
	"vcenter.hv.vm.num",

	"vcenter.vm.cluster.name",
	"vcenter.vm.cpu.num",
	"vcenter.vm.cpu.usage",
	"vcenter.vm.discovery",
	"vcenter.vm.hv.name",
	"vcenter.vm.memory.size",
	"vcenter.vm.memory.size.ballooned",
	"vcenter.vm.memory.size.compressed",
	"vcenter.vm.memory.size.swapped",
	"vcenter.vm.net.if.discovery",
	"vcenter.vm.net.if.in",
	"vcenter.vm.net.if.out",
	"vcenter.vm.powerstate",
	"vcenter.vm.storage.committed",
	"vcenter.vm.storage.unshared",
	"vcenter.vm.storage.uncommitted",
	"vcenter.vm.uptime",
	"vcenter.vm.vfs.dev.discovery",
	"vcenter.vm.vfs.dev.read",
	"vcenter.vm.vfs.dev.write",
	"vcenter.vm.vfs.fs.discovery",
	"vcenter.vm.vfs.fs.size",

	"vsphere.cpu.usage",
	"vsphere.eventlog",
	"vsphere.fullname",
	"vsphere.hw.cpu.num",
	"vsphere.hw.cpu.freq",
	"vsphere.hw.cpu.model",
	"vsphere.hw.cpu.threads",
	"vsphere.hw.memory",
	"vsphere.hw.model",
	"vsphere.hw.uuid",
	"vsphere.hw.vendor",
	"vsphere.memory.size.ballooned",
	"vsphere.memory.used",
	"vsphere.status",
	"vsphere.uptime",
	"vsphere.version",
	"vsphere.vm.num",

	"vsphere.vm.cpu.num",
	"vsphere.vm.cpu.usage",
	"vsphere.vm.discovery",
	"vsphere.vm.hv.name",
	"vsphere.vm.memory.size",
	"vsphere.vm.memory.size.ballooned",
	"vsphere.vm.memory.size.compressed",
	"vsphere.vm.memory.size.swapped",
	"vsphere.vm.net.if.discovery",
	"vsphere.vm.net.if.in",
	"vsphere.vm.net.if.out",
	"vsphere.vm.powerstate",
	"vsphere.vm.storage.committed",
	"vsphere.vm.storage.unshared",
	"vsphere.vm.storage.uncommitted",
	"vsphere.vm.uptime",
	"vsphere.vm.vfs.dev.discovery",
	"vsphere.vm.vfs.dev.read",
	"vsphere.vm.vfs.dev.write",
	"vsphere.vm.vfs.fs.discovery",
	"vsphere.vm.vfs.fs.size",
	NULL
};

#if defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL)
static vmfunc_t	vmfuncs[] =
{
	check_vcenter_cluster_discovery,
	check_vcenter_cluster_status,
	check_vcenter_eventlog,

	check_vcenter_hv_cluster_name,
	check_vcenter_hv_cpu_usage,
	check_vcenter_hv_discovery,
	check_vcenter_hv_fullname,
	check_vcenter_hv_hw_cpu_num,
	check_vcenter_hv_hw_cpu_freq,
	check_vcenter_hv_hw_cpu_model,
	check_vcenter_hv_hw_cpu_threads,
	check_vcenter_hv_hw_memory,
	check_vcenter_hv_hw_model,
	check_vcenter_hv_hw_uuid,
	check_vcenter_hv_hw_vendor,
	check_vcenter_hv_memory_size_ballooned,
	check_vcenter_hv_memory_used,
	check_vcenter_hv_status,
	check_vcenter_hv_uptime,
	check_vcenter_hv_version,
	check_vcenter_hv_vm_num,

	check_vcenter_vm_cluster_name,
	check_vcenter_vm_cpu_num,
	check_vcenter_vm_cpu_usage,
	check_vcenter_vm_discovery,
	check_vcenter_vm_hv_name,
	check_vcenter_vm_memory_size,
	check_vcenter_vm_memory_size_ballooned,
	check_vcenter_vm_memory_size_compressed,
	check_vcenter_vm_memory_size_swapped,
	check_vcenter_vm_net_if_discovery,
	check_vcenter_vm_net_if_in,
	check_vcenter_vm_net_if_out,
	check_vcenter_vm_powerstate,
	check_vcenter_vm_storage_committed,
	check_vcenter_vm_storage_unshared,
	check_vcenter_vm_storage_uncommitted,
	check_vcenter_vm_uptime,
	check_vcenter_vm_vfs_dev_discovery,
	check_vcenter_vm_vfs_dev_read,
	check_vcenter_vm_vfs_dev_write,
	check_vcenter_vm_vfs_fs_discovery,
	check_vcenter_vm_vfs_fs_size,

	check_vsphere_cpu_usage,
	check_vsphere_eventlog,
	check_vsphere_fullname,
	check_vsphere_hw_cpu_num,
	check_vsphere_hw_cpu_freq,
	check_vsphere_hw_cpu_model,
	check_vsphere_hw_cpu_threads,
	check_vsphere_hw_memory,
	check_vsphere_hw_model,
	check_vsphere_hw_uuid,
	check_vsphere_hw_vendor,
	check_vsphere_memory_size_ballooned,
	check_vsphere_memory_used,
	check_vsphere_status,
	check_vsphere_uptime,
	check_vsphere_version,
	check_vsphere_vm_num,

	check_vsphere_vm_cpu_num,
	check_vsphere_vm_cpu_usage,
	check_vsphere_vm_discovery,
	check_vsphere_vm_hv_name,
	check_vsphere_vm_memory_size,
	check_vsphere_vm_memory_size_ballooned,
	check_vsphere_vm_memory_size_compressed,
	check_vsphere_vm_memory_size_swapped,
	check_vsphere_vm_net_if_discovery,
	check_vsphere_vm_net_if_in,
	check_vsphere_vm_net_if_out,
	check_vsphere_vm_powerstate,
	check_vsphere_vm_storage_committed,
	check_vsphere_vm_storage_unshared,
	check_vsphere_vm_storage_uncommitted,
	check_vsphere_vm_uptime,
	check_vsphere_vm_vfs_dev_discovery,
	check_vsphere_vm_vfs_dev_read,
	check_vsphere_vm_vfs_dev_write,
	check_vsphere_vm_vfs_fs_discovery,
	check_vsphere_vm_vfs_fs_size
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

	if (0 != strncmp(key, ZBX_VMWARE_PREFIX, sizeof(ZBX_VMWARE_PREFIX) - 1))
		return FAIL;

	for (i = 0; NULL != vmkeys[i]; i++)
	{
		if (0 == strcmp(key + sizeof(ZBX_VMWARE_PREFIX) - 1, vmkeys[i]))
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

	request.lastlogsize = item->lastlogsize;

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
