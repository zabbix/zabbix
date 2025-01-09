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

#include "zbxvmware.h"

#include "zbxjson.h"

void	zbx_vmware_stats_ext_get(struct zbx_json *json, const void *arg)
{
	zbx_vmware_stats_t	vmware_stats;

	ZBX_UNUSED(arg);

	/* zabbix[vmware,buffer,<mode>] */
	if (SUCCEED == zbx_vmware_get_statistics(&vmware_stats))
	{
		zbx_json_addobject(json, "vmware");
		zbx_json_adduint64(json, "total", vmware_stats.memory_total);
		zbx_json_adduint64(json, "free", vmware_stats.memory_total - vmware_stats.memory_used);
		zbx_json_addfloat(json, "pfree", (double)(vmware_stats.memory_total - vmware_stats.memory_used) /
				vmware_stats.memory_total * 100);
		zbx_json_adduint64(json, "used", vmware_stats.memory_used);
		zbx_json_addfloat(json, "pused", (double)vmware_stats.memory_used / vmware_stats.memory_total * 100);
		zbx_json_close(json);
	}
}
