/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "checks_simple_vmware.h"
#include "checks_simple.h"
#include "simple.h"
#include "log.h"

int	get_value_simple(DC_ITEM *item, AGENT_RESULT *result)
{
	const char	*__function_name = "get_value_simple";

	AGENT_REQUEST	request;
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
	else if (0 == strcmp(request.key, "virt.vmware.host.hw.vendor"))
	{
		if (SYSINFO_RET_OK == check_vmware_hosthwvendor(&request, result))
			ret = SUCCEED;
	}
	else if (0 == strcmp(request.key, "virt.vmware.host.hw.model"))
	{
		if (SYSINFO_RET_OK == check_vmware_hosthwmodel(&request, result))
			ret = SUCCEED;
	}
	else if (0 == strcmp(request.key, "virt.vmware.host.hw.uuid"))
	{
		if (SYSINFO_RET_OK == check_vmware_hosthwuuid(&request, result))
			ret = SUCCEED;
	}
	else if (0 == strcmp(request.key, "virt.vmware.host.hw.memory"))
	{
		if (SYSINFO_RET_OK == check_vmware_hosthwmemory(&request, result))
			ret = SUCCEED;
	}
	else if (0 == strcmp(request.key, "virt.vmware.host.hw.cpumodel"))
	{
		if (SYSINFO_RET_OK == check_vmware_hosthwcpumodel(&request, result))
			ret = SUCCEED;
	}
	else if (0 == strcmp(request.key, "virt.vmware.host.hw.cpufreq"))
	{
		if (SYSINFO_RET_OK == check_vmware_hosthwcpufreq(&request, result))
			ret = SUCCEED;
	}
	else if (0 == strcmp(request.key, "virt.vmware.host.hw.cpucores"))
	{
		if (SYSINFO_RET_OK == check_vmware_hosthwcpucores(&request, result))
			ret = SUCCEED;
	}
	else if (0 == strcmp(request.key, "virt.vmware.host.hw.cputhreads"))
	{
		if (SYSINFO_RET_OK == check_vmware_hosthwcputhreads(&request, result))
			ret = SUCCEED;
	}
	else if (0 == strcmp(request.key, "virt.vmware.host.uptime"))
	{
		if (SYSINFO_RET_OK == check_vmware_hostuptime(&request, result))
			ret = SUCCEED;
	}
	else if (0 == strcmp(request.key, "virt.vmware.host.fullname"))
	{
		if (SYSINFO_RET_OK == check_vmware_hostfullname(&request, result))
			ret = SUCCEED;
	}
	else if (0 == strcmp(request.key, "virt.vmware.host.version"))
	{
		if (SYSINFO_RET_OK == check_vmware_hostversion(&request, result))
			ret = SUCCEED;
	}
	else if (0 == strcmp(request.key, "virt.vmware.host.memory.used"))
	{
		if (SYSINFO_RET_OK == check_vmware_hostmemoryused(&request, result))
			ret = SUCCEED;
	}
	else if (0 == strcmp(request.key, "virt.vmware.host.cpu.usage"))
	{
		if (SYSINFO_RET_OK == check_vmware_hostcpuusage(&request, result))
			ret = SUCCEED;
	}
	else if (0 == strcmp(request.key, "virt.vmware.vm.list"))
	{
		if (SYSINFO_RET_OK == check_vmware_vmlist(&request, result))
			ret = SUCCEED;
	}
	else if (0 == strcmp(request.key, "virt.vmware.vm.cpu.num"))
	{
		if (SYSINFO_RET_OK == check_vmware_vmcpunum(&request, result))
			ret = SUCCEED;
	}
	else if (0 == strcmp(request.key, "virt.vmware.vm.cpu.usage"))
	{
		if (SYSINFO_RET_OK == check_vmware_vmcpuusage(&request, result))
			ret = SUCCEED;
	}
	else if (0 == strcmp(request.key, "virt.vmware.vm.memory.size"))
	{
		if (SYSINFO_RET_OK == check_vmware_vmmemsize(&request, result))
			ret = SUCCEED;
	}
	else if (0 == strcmp(request.key, "virt.vmware.vm.memory.size.ballooned"))
	{
		if (SYSINFO_RET_OK == check_vmware_vmmemsizeballooned(&request, result))
			ret = SUCCEED;
	}
	else if (0 == strcmp(request.key, "virt.vmware.vm.memory.size.swapped"))
	{
		if (SYSINFO_RET_OK == check_vmware_vmmemsizeswapped(&request, result))
			ret = SUCCEED;
	}
	else if (0 == strcmp(request.key, "virt.vmware.vm.storage.commited"))
	{
		if (SYSINFO_RET_OK == check_vmware_vmstoragecommited(&request, result))
			ret = SUCCEED;
	}
	else if (0 == strcmp(request.key, "virt.vmware.vm.storage.additional"))
	{
		if (SYSINFO_RET_OK == check_vmware_vmstorageuncommited(&request, result))
			ret = SUCCEED;
	}
	else if (0 == strcmp(request.key, "virt.vmware.vm.storage.additional"))
	{
		if (SYSINFO_RET_OK == check_vmware_vmstorageunshared(&request, result))
			ret = SUCCEED;
	}
	else if (0 == strcmp(request.key, "virt.vmware.vm.uptime"))
	{
		if (SYSINFO_RET_OK == check_vmware_vmuptime(&request, result))
			ret = SUCCEED;
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
