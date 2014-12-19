/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

#include "common.h"
#include "sysinfo.h"
#include "../common/common.h"
#include "zbxalgo.h"
#include "zbxjson.h"

int	SYSTEM_CPU_DISCOVERY(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	int			i;
	zbx_vector_uint64_t	cpus;
	struct zbx_json		json;

	zbx_vector_uint64_create(&cpus);

	if (SUCCEED != get_cpu_statuses(&cpus))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Collector is not started."));
		zbx_vector_uint64_destroy(&cpus);
		return SYSINFO_RET_FAIL;
	}

	zbx_json_init(&json, ZBX_JSON_STAT_BUF_LEN);
	zbx_json_addarray(&json, ZBX_PROTO_TAG_DATA);

	for (i = 0; i < cpus.values_num; i++)
	{
		zbx_json_addobject(&json, NULL);

		zbx_json_adduint64(&json, "{#CPU.NUMBER}", i);
		zbx_json_addstring(&json, "{#CPU.STATUS}", (SYSINFO_RET_OK == cpus.values[i] ?
				"online" : "offline"), ZBX_JSON_TYPE_STRING);

		zbx_json_close(&json);
	}

	zbx_json_close(&json);
	SET_STR_RESULT(result, zbx_strdup(result->str, json.buffer));

	zbx_json_free(&json);
	zbx_vector_uint64_destroy(&cpus);

	return SYSINFO_RET_OK;
}

int	SYSTEM_CPU_UTIL(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*tmp;
	int	ret = SYSINFO_RET_FAIL;

	if (3 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	tmp = get_rparam(request, 0);

	/* only "all" (default) for parameter "cpu" is supported */
	if (NULL != tmp && '\0' != *tmp && 0 != strcmp(tmp, "all"))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		return SYSINFO_RET_FAIL;
	}

	tmp = get_rparam(request, 2);

	/* only "avg1" (default) for parameter "mode" is supported */
	if (NULL != tmp && '\0' != *tmp && 0 != strcmp(tmp, "avg1"))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid third parameter."));
		return SYSINFO_RET_FAIL;
	}

	tmp = get_rparam(request, 1);

	if (NULL == tmp || '\0' == *tmp || 0 == strcmp(tmp, "user"))
		ret = EXECUTE_DBL("iostat 1 2 | tail -n 1 | awk '{printf(\"%s\",$(NF-3))}'", result);
	else if (0 == strcmp(tmp, "nice"))
		ret = EXECUTE_DBL("iostat 1 2 | tail -n 1 | awk '{printf(\"%s\",$(NF-2))}'", result);
	else if (0 == strcmp(tmp, "system"))
		ret = EXECUTE_DBL("iostat 1 2 | tail -n 1 | awk '{printf(\"%s\",$(NF-1))}'", result);
	else if (0 == strcmp(tmp, "idle"))
		ret = EXECUTE_DBL("iostat 1 2 | tail -n 1 | awk '{printf(\"%s\",$(NF))}'", result);
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		return SYSINFO_RET_FAIL;
	}

	return ret;
}

int	SYSTEM_CPU_LOAD(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*tmp;
	int	ret = SYSINFO_RET_FAIL;

	if (2 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	tmp = get_rparam(request, 0);

	/* only "all" (default) for parameter "cpu" is supported */
	if (NULL != tmp && '\0' != *tmp && 0 != strcmp(tmp, "all"))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		return SYSINFO_RET_FAIL;
	}

	tmp = get_rparam(request, 1);

	if (NULL == tmp || '\0' == *tmp || 0 == strcmp(tmp, "avg1"))
		ret = EXECUTE_DBL("uptime | awk '{printf(\"%s\", $(NF))}' | sed 's/[ ,]//g'", result);
	else if (0 == strcmp(tmp, "avg5"))
		ret = EXECUTE_DBL("uptime | awk '{printf(\"%s\", $(NF-1))}' | sed 's/[ ,]//g'", result);
	else if (0 == strcmp(tmp, "avg15"))
		ret = EXECUTE_DBL(cmd, "uptime | awk '{printf(\"%s\", $(NF-2))}' | sed 's/[ ,]//g'", result);
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		return SYSINFO_RET_FAIL;
	}

	return ret;
}
