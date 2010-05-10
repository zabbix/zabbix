/*
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
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
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/

#include "common.h"
#include "sysinfo.h"
#include "stats.h"

int	SYSTEM_STAT(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	char	section[16], type[8];
	int	nparams;

	assert(result);

	init_result(result);

	if (!VMSTAT_COLLECTOR_STARTED(collector))
	{
		SET_MSG_RESULT(result, strdup("Collector is not started!"));
		return SYSINFO_RET_FAIL;
	}

	nparams = num_param(param);

	if (nparams > 2)
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, section, sizeof(section)))
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 2, type, sizeof(type)))
		*type = '\0';

	if (0 == strcmp(section, "kthr"))
	{
		if (0 == strcmp(type, "r"))
			SET_DBL_RESULT(result, collector->vmstat.kthr_r)
		else if (0 == strcmp(type, "b"))
			SET_DBL_RESULT(result, collector->vmstat.kthr_b)
/*		else if (0 == strcmp(type, "p"))
			SET_DBL_RESULT(result, collector->vmstat.kthr_p)*/
		else
			return SYSINFO_RET_FAIL;
	}
	else if (0 == strcmp(section, "page"))
	{
		if (0 == strcmp(type, "fi"))
			SET_DBL_RESULT(result, collector->vmstat.fi)
		else if (0 == strcmp(type, "fo"))
			SET_DBL_RESULT(result, collector->vmstat.fo)
		else if (0 == strcmp(type, "pi"))
			SET_DBL_RESULT(result, collector->vmstat.pi)
		else if (0 == strcmp(type, "po"))
			SET_DBL_RESULT(result, collector->vmstat.po)
		else if (0 == strcmp(type, "fr"))
			SET_DBL_RESULT(result, collector->vmstat.fr)
		else if (0 == strcmp(type, "sr"))
			SET_DBL_RESULT(result, collector->vmstat.sr)
		else
			return SYSINFO_RET_FAIL;
	}
	else if (0 == strcmp(section, "faults"))
	{
		if (0 == strcmp(type, "in"))
			SET_DBL_RESULT(result, collector->vmstat.in)
		else if (0 == strcmp(type, "sy"))
			SET_DBL_RESULT(result, collector->vmstat.sy)
		else if (0 == strcmp(type, "cs"))
			SET_DBL_RESULT(result, collector->vmstat.cs)
		else
			return SYSINFO_RET_FAIL;
	}
	else if (0 == strcmp(section, "cpu"))
	{
		if (0 == strcmp(type, "us"))
			SET_DBL_RESULT(result, collector->vmstat.cpu_us)
		else if (0 == strcmp(type, "sy"))
			SET_DBL_RESULT(result, collector->vmstat.cpu_sy)
		else if (0 == strcmp(type, "id"))
			SET_DBL_RESULT(result, collector->vmstat.cpu_id)
		else if (0 == strcmp(type, "wa"))
			SET_DBL_RESULT(result, collector->vmstat.cpu_wa)
		else if (0 == strcmp(type, "pc") && collector->vmstat.shared_enabled)
			SET_DBL_RESULT(result, collector->vmstat.cpu_pc)
		else if (0 == strcmp(type, "ec") && collector->vmstat.shared_enabled)
			SET_DBL_RESULT(result, collector->vmstat.cpu_ec)
		else if (0 == strcmp(type, "lbusy") && collector->vmstat.shared_enabled)
			SET_DBL_RESULT(result, collector->vmstat.cpu_lbusy)
		else if (0 == strcmp(type, "app") && collector->vmstat.shared_enabled && collector->vmstat.pool_util_authority)
			SET_DBL_RESULT(result, collector->vmstat.cpu_app)
		else
			return SYSINFO_RET_FAIL;
	}
	else if (0 == strcmp(section, "disk"))
	{
		if (0 == strcmp(type, "bps"))
			SET_UI64_RESULT(result, collector->vmstat.disk_bps)
		else if (0 == strcmp(type, "tps"))
			SET_DBL_RESULT(result, collector->vmstat.disk_tps)
		else
			return SYSINFO_RET_FAIL;
	}
	else if (0 == strcmp(section, "ent") && nparams == 1 && collector->vmstat.shared_enabled)
		SET_DBL_RESULT(result, collector->vmstat.ent)
	else if (0 == strcmp(section, "memory"))
	{
		if (0 == strcmp(type, "avm") && collector->vmstat.aix52stats)
			SET_UI64_RESULT(result, collector->vmstat.mem_avm)
		else if (0 == strcmp(type, "fre"))
			SET_UI64_RESULT(result, collector->vmstat.mem_fre)
		else
			return SYSINFO_RET_FAIL;
	}
	else
		return SYSINFO_RET_FAIL;

	return SYSINFO_RET_OK;
}
