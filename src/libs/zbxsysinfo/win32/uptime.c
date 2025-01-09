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

#include "zbxwin32.h"

int	system_uptime(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char		counter_path[64];
	AGENT_REQUEST	request_tmp;
	int		ret;

	zbx_snprintf(counter_path, sizeof(counter_path), "\\%u\\%u",
			(unsigned int)zbx_get_builtin_object_index(PCI_SYSTEM_UP_TIME),
			(unsigned int)zbx_get_builtin_counter_index(PCI_SYSTEM_UP_TIME));

	request_tmp.nparam = 1;
	request_tmp.params = zbx_malloc(NULL, request_tmp.nparam * sizeof(char *));
	request_tmp.params[0] = counter_path;

	ret = perf_counter(&request_tmp, result);

	zbx_free(request_tmp.params);

	if (SYSINFO_RET_FAIL == ret)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain system information."));
		return SYSINFO_RET_FAIL;
	}

	/* result must be integer to correctly interpret it in frontend (uptime) */
	if (!ZBX_GET_UI64_RESULT(result))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid result. Unsigned integer is expected."));
		return SYSINFO_RET_FAIL;
	}

	ZBX_UNSET_RESULT_EXCLUDING(result, AR_UINT64);

	return SYSINFO_RET_OK;
}
