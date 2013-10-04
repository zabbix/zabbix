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

typedef int	(*vmfunc_t)(AGENT_REQUEST *, const char *, const char *, AGENT_RESULT *);

#define ZBX_VMWARE_PREFIX	"vmware."


typedef struct
{
	const char	*key;
	vmfunc_t	func;
}
zbx_vmcheck_t;

#if defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL)
# define VMCHECK_FUNC(func)	func
#else
# define VMCHECK_FUNC(func)	NULL
#endif

static zbx_vmcheck_t	vmchecks[] =
{
	{"vcenter.cluster.discovery", VMCHECK_FUNC(check_vcenter_cluster_discovery)},
	{"vcenter.cluster.status", VMCHECK_FUNC(check_vcenter_cluster_status)},
	{"vcenter.eventlog", VMCHECK_FUNC(check_vcenter_eventlog)},

	{"vcenter.hv.cluster.name", VMCHECK_FUNC(check_vcenter_hv_cluster_name)},
	{"vcenter.hv.cpu.usage", VMCHECK_FUNC(check_vcenter_hv_cpu_usage)},
	{"vcenter.hv.discovery", VMCHECK_FUNC(check_vcenter_hv_discovery)},
	{"vcenter.hv.fullname", VMCHECK_FUNC(check_vcenter_hv_fullname)},
	{"vcenter.hv.hw.cpu.num", VMCHECK_FUNC(check_vcenter_hv_hw_cpu_num)},
	{"vcenter.hv.hw.cpu.freq", VMCHECK_FUNC(check_vcenter_hv_hw_cpu_freq)},
	{"vcenter.hv.hw.cpu.model", VMCHECK_FUNC(check_vcenter_hv_hw_cpu_model)},
	{"vcenter.hv.hw.cpu.threads", VMCHECK_FUNC(check_vcenter_hv_hw_cpu_threads)},
	{"vcenter.hv.hw.memory", VMCHECK_FUNC(check_vcenter_hv_hw_memory)},
	{"vcenter.hv.hw.model", VMCHECK_FUNC(check_vcenter_hv_hw_model)},
	{"vcenter.hv.hw.uuid", VMCHECK_FUNC(check_vcenter_hv_hw_uuid)},
	{"vcenter.hv.hw.vendor", VMCHECK_FUNC(check_vcenter_hv_hw_vendor)},
	{"vcenter.hv.memory.size.ballooned", VMCHECK_FUNC(check_vcenter_hv_memory_size_ballooned)},
	{"vcenter.hv.memory.used", VMCHECK_FUNC(check_vcenter_hv_memory_used)},
	{"vcenter.hv.status", VMCHECK_FUNC(check_vcenter_hv_status)},
	{"vcenter.hv.uptime", VMCHECK_FUNC(check_vcenter_hv_uptime)},
	{"vcenter.hv.version", VMCHECK_FUNC(check_vcenter_hv_version)},
	{"vcenter.hv.vm.num", VMCHECK_FUNC(check_vcenter_hv_vm_num)},
	{"vcenter.hv.network.in", VMCHECK_FUNC(check_vcenter_hv_network_in)},
	{"vcenter.hv.network.out", VMCHECK_FUNC(check_vcenter_hv_network_out)},
	{"vcenter.hv.datastore.discovery", VMCHECK_FUNC(check_vcenter_hv_datastore_discovery)},
	{"vcenter.hv.datastore.read", VMCHECK_FUNC(check_vcenter_hv_datastore_read)},
	{"vcenter.hv.datastore.write", VMCHECK_FUNC(check_vcenter_hv_datastore_write)},

	{"vcenter.vm.cluster.name", VMCHECK_FUNC(check_vcenter_vm_cluster_name)},
	{"vcenter.vm.cpu.num", VMCHECK_FUNC(check_vcenter_vm_cpu_num)},
	{"vcenter.vm.cpu.usage", VMCHECK_FUNC(check_vcenter_vm_cpu_usage)},
	{"vcenter.vm.discovery", VMCHECK_FUNC(check_vcenter_vm_discovery)},
	{"vcenter.vm.hv.name", VMCHECK_FUNC(check_vcenter_vm_hv_name)},
	{"vcenter.vm.memory.size", VMCHECK_FUNC(check_vcenter_vm_memory_size)},
	{"vcenter.vm.memory.size.ballooned", VMCHECK_FUNC(check_vcenter_vm_memory_size_ballooned)},
	{"vcenter.vm.memory.size.compressed", VMCHECK_FUNC(check_vcenter_vm_memory_size_compressed)},
	{"vcenter.vm.memory.size.swapped", VMCHECK_FUNC(check_vcenter_vm_memory_size_swapped)},
	{"vcenter.vm.memory.size.usage.guest", VMCHECK_FUNC(check_vcenter_vm_memory_size_usage_guest)},
	{"vcenter.vm.memory.size.usage.host", VMCHECK_FUNC(check_vcenter_vm_memory_size_usage_host)},
	{"vcenter.vm.memory.size.private", VMCHECK_FUNC(check_vcenter_vm_memory_size_private)},
	{"vcenter.vm.memory.size.shared", VMCHECK_FUNC(check_vcenter_vm_memory_size_shared)},
	{"vcenter.vm.net.if.discovery", VMCHECK_FUNC(check_vcenter_vm_net_if_discovery)},
	{"vcenter.vm.net.if.in", VMCHECK_FUNC(check_vcenter_vm_net_if_in)},
	{"vcenter.vm.net.if.out", VMCHECK_FUNC(check_vcenter_vm_net_if_out)},
	{"vcenter.vm.powerstate", VMCHECK_FUNC(check_vcenter_vm_powerstate)},
	{"vcenter.vm.storage.committed", VMCHECK_FUNC(check_vcenter_vm_storage_committed)},
	{"vcenter.vm.storage.unshared", VMCHECK_FUNC(check_vcenter_vm_storage_unshared)},
	{"vcenter.vm.storage.uncommitted", VMCHECK_FUNC(check_vcenter_vm_storage_uncommitted)},
	{"vcenter.vm.uptime", VMCHECK_FUNC(check_vcenter_vm_uptime)},
	{"vcenter.vm.vfs.dev.discovery", VMCHECK_FUNC(check_vcenter_vm_vfs_dev_discovery)},
	{"vcenter.vm.vfs.dev.read", VMCHECK_FUNC(check_vcenter_vm_vfs_dev_read)},
	{"vcenter.vm.vfs.dev.write", VMCHECK_FUNC(check_vcenter_vm_vfs_dev_write)},
	{"vcenter.vm.vfs.fs.discovery", VMCHECK_FUNC(check_vcenter_vm_vfs_fs_discovery)},
	{"vcenter.vm.vfs.fs.size", VMCHECK_FUNC(check_vcenter_vm_vfs_fs_size)},

	{"vsphere.cpu.usage", VMCHECK_FUNC(check_vsphere_cpu_usage)},
	{"vsphere.eventlog", VMCHECK_FUNC(check_vsphere_eventlog)},
	{"vsphere.fullname", VMCHECK_FUNC(check_vsphere_fullname)},
	{"vsphere.hw.cpu.num", VMCHECK_FUNC(check_vsphere_hw_cpu_num)},
	{"vsphere.hw.cpu.freq", VMCHECK_FUNC(check_vsphere_hw_cpu_freq)},
	{"vsphere.hw.cpu.model", VMCHECK_FUNC(check_vsphere_hw_cpu_model)},
	{"vsphere.hw.cpu.threads", VMCHECK_FUNC(check_vsphere_hw_cpu_threads)},
	{"vsphere.hw.memory", VMCHECK_FUNC(check_vsphere_hw_memory)},
	{"vsphere.hw.model", VMCHECK_FUNC(check_vsphere_hw_model)},
	{"vsphere.hw.uuid", VMCHECK_FUNC(check_vsphere_hw_uuid)},
	{"vsphere.hw.vendor", VMCHECK_FUNC(check_vsphere_hw_vendor)},
	{"vsphere.memory.size.ballooned", VMCHECK_FUNC(check_vsphere_memory_size_ballooned)},
	{"vsphere.memory.used", VMCHECK_FUNC(check_vsphere_memory_used)},
	{"vsphere.status", VMCHECK_FUNC(check_vsphere_status)},
	{"vsphere.uptime", VMCHECK_FUNC(check_vsphere_uptime)},
	{"vsphere.version", VMCHECK_FUNC(check_vsphere_version)},
	{"vsphere.vm.num", VMCHECK_FUNC(check_vsphere_vm_num)},
	{"vsphere.network.in", VMCHECK_FUNC(check_vsphere_hv_network_in)},
	{"vsphere.network.out", VMCHECK_FUNC(check_vsphere_hv_network_out)},
	{"vsphere.datastore.discovery", VMCHECK_FUNC(check_vsphere_hv_datastore_discovery)},
	{"vsphere.datastore.read", VMCHECK_FUNC(check_vsphere_hv_datastore_read)},
	{"vsphere.datastore.write", VMCHECK_FUNC(check_vsphere_hv_datastore_write)},

	{"vsphere.vm.cpu.num", VMCHECK_FUNC(check_vsphere_vm_cpu_num)},
	{"vsphere.vm.cpu.usage", VMCHECK_FUNC(check_vsphere_vm_cpu_usage)},
	{"vsphere.vm.discovery", VMCHECK_FUNC(check_vsphere_vm_discovery)},
	{"vsphere.vm.hv.name", VMCHECK_FUNC(check_vsphere_vm_hv_name)},
	{"vsphere.vm.memory.size", VMCHECK_FUNC(check_vsphere_vm_memory_size)},
	{"vsphere.vm.memory.size.ballooned", VMCHECK_FUNC(check_vsphere_vm_memory_size_ballooned)},
	{"vsphere.vm.memory.size.compressed", VMCHECK_FUNC(check_vsphere_vm_memory_size_compressed)},
	{"vsphere.vm.memory.size.swapped", VMCHECK_FUNC(check_vsphere_vm_memory_size_swapped)},
	{"vsphere.vm.memory.size.usage.guest", VMCHECK_FUNC(check_vsphere_vm_memory_size_usage_guest)},
	{"vsphere.vm.memory.size.usage.host", VMCHECK_FUNC(check_vsphere_vm_memory_size_usage_host)},
	{"vsphere.vm.memory.size.private", VMCHECK_FUNC(check_vsphere_vm_memory_size_private)},
	{"vsphere.vm.memory.size.shared", VMCHECK_FUNC(check_vsphere_vm_memory_size_shared)},
	{"vsphere.vm.net.if.discovery", VMCHECK_FUNC(check_vsphere_vm_net_if_discovery)},
	{"vsphere.vm.net.if.in", VMCHECK_FUNC(check_vsphere_vm_net_if_in)},
	{"vsphere.vm.net.if.out", VMCHECK_FUNC(check_vsphere_vm_net_if_out)},
	{"vsphere.vm.powerstate", VMCHECK_FUNC(check_vsphere_vm_powerstate)},
	{"vsphere.vm.storage.committed", VMCHECK_FUNC(check_vsphere_vm_storage_committed)},
	{"vsphere.vm.storage.unshared", VMCHECK_FUNC(check_vsphere_vm_storage_unshared)},
	{"vsphere.vm.storage.uncommitted", VMCHECK_FUNC(check_vsphere_vm_storage_uncommitted)},
	{"vsphere.vm.uptime", VMCHECK_FUNC(check_vsphere_vm_uptime)},
	{"vsphere.vm.vfs.dev.discovery", VMCHECK_FUNC(check_vsphere_vm_vfs_dev_discovery)},
	{"vsphere.vm.vfs.dev.read", VMCHECK_FUNC(check_vsphere_vm_vfs_dev_read)},
	{"vsphere.vm.vfs.dev.write", VMCHECK_FUNC(check_vsphere_vm_vfs_dev_write)},
	{"vsphere.vm.vfs.fs.discovery", VMCHECK_FUNC(check_vsphere_vm_vfs_fs_discovery)},
	{"vsphere.vm.vfs.fs.size", VMCHECK_FUNC(check_vsphere_vm_vfs_fs_size)},
	{NULL, NULL}
};

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
	zbx_vmcheck_t	*check;

	if (0 != strncmp(key, ZBX_VMWARE_PREFIX, sizeof(ZBX_VMWARE_PREFIX) - 1))
		return FAIL;

	for (check = vmchecks; NULL != check->key; check++)
	{
		if (0 == strcmp(key + sizeof(ZBX_VMWARE_PREFIX) - 1, check->key))
		{
			*vmfunc = check->func;
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
			if (SYSINFO_RET_OK == vmfunc(&request, item->username, item->password, result))
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
