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

#include "cpu.h"

#include "zbxalgo.h"
#include "zbxjson.h"
#include "cpustat.h"
#include "module.h"

static const char	*get_cpu_status_string(int status)
{
	switch (status)
	{
		case ZBX_CPU_STATUS_ONLINE:
			return "online";
		case ZBX_CPU_STATUS_OFFLINE:
			return "offline";
		case ZBX_CPU_STATUS_UNKNOWN:
			return "unknown";
	}

	return NULL;
}

int	system_cpu_discovery(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	zbx_vector_uint64_pair_t	cpus;
	struct zbx_json			json;
	int				ret = SYSINFO_RET_FAIL;

	ZBX_UNUSED(request);

	zbx_vector_uint64_pair_create(&cpus);

	if (SUCCEED != get_cpus(&cpus))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Collector is not started."));
		goto out;
	}

	zbx_json_initarray(&json, ZBX_JSON_STAT_BUF_LEN);

	for (int i = 0; i < cpus.values_num; i++)
	{
		zbx_json_addobject(&json, NULL);

		zbx_json_adduint64(&json, "{#CPU.NUMBER}", cpus.values[i].first);
		zbx_json_addstring(&json, "{#CPU.STATUS}", get_cpu_status_string((int)cpus.values[i].second),
				ZBX_JSON_TYPE_STRING);

		zbx_json_close(&json);
	}

	zbx_json_close(&json);
	SET_STR_RESULT(result, zbx_strdup(result->str, json.buffer));

	zbx_json_free(&json);

	ret = SYSINFO_RET_OK;
out:
	zbx_vector_uint64_pair_destroy(&cpus);

	return ret;
}
