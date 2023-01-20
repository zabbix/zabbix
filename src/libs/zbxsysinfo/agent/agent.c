/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

#include "zbxsysinfo.h"

#include "modbtype.h"

extern char			*CONFIG_HOSTNAMES;
extern ZBX_THREAD_LOCAL char	*CONFIG_HOSTNAME;
extern char			*CONFIG_HOST_METADATA;
extern char			*CONFIG_HOST_METADATA_ITEM;

static int	agent_hostname(AGENT_REQUEST *request, AGENT_RESULT *result);
static int	agent_hostmetadata(AGENT_REQUEST *request, AGENT_RESULT *result);
static int	agent_ping(AGENT_REQUEST *request, AGENT_RESULT *result);
static int	agent_version(AGENT_REQUEST *request, AGENT_RESULT *result);
static int	agent_variant(AGENT_REQUEST *request, AGENT_RESULT *result);

ZBX_METRIC	parameters_agent[] =
/*	KEY			FLAG		FUNCTION		TEST PARAMETERS */
{
	{"agent.hostname",	0,		agent_hostname,		NULL},
	{"agent.hostmetadata",	0,		agent_hostmetadata,	NULL},
	{"agent.ping",		0,		agent_ping,		NULL},
	{"agent.variant",	0,		agent_variant,		NULL},
	{"agent.version",	0,		agent_version,		NULL},
	{"modbus.get",		CF_HAVEPARAMS,	modbus_get,		"tcp://127.0.0.1"},
	{NULL}
};

static int	agent_hostname(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	ZBX_UNUSED(request);

	if (NULL == CONFIG_HOSTNAME)
	{
		char	*p;

		SET_STR_RESULT(result, NULL != (p = strchr(CONFIG_HOSTNAMES, ',')) ?
				zbx_dsprintf(NULL, "%.*s", (int)(p - CONFIG_HOSTNAMES), CONFIG_HOSTNAMES) :
				zbx_strdup(NULL, CONFIG_HOSTNAMES));
	}
	else
		SET_STR_RESULT(result, zbx_strdup(NULL, CONFIG_HOSTNAME));

	return SYSINFO_RET_OK;
}

static int	agent_hostmetadata(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	int	ret = SYSINFO_RET_OK;

	ZBX_UNUSED(request);

	if (NULL != CONFIG_HOST_METADATA)
	{
		SET_STR_RESULT(result, zbx_strdup(NULL, CONFIG_HOST_METADATA));
	}
	else if (NULL != CONFIG_HOST_METADATA_ITEM)
	{
		if (SUCCEED != zbx_execute_agent_check(CONFIG_HOST_METADATA_ITEM, ZBX_PROCESS_LOCAL_COMMAND |
				ZBX_PROCESS_WITH_ALIAS, result) || NULL == ZBX_GET_STR_RESULT(result))
		{
			SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot get host metadata using item \"%s\"",
					CONFIG_HOST_METADATA_ITEM));
			ret = SYSINFO_RET_FAIL;
		}
	}
	else
		SET_STR_RESULT(result, zbx_strdup(NULL, ""));

	return ret;
}

static int	agent_ping(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	ZBX_UNUSED(request);

	SET_UI64_RESULT(result, 1);

	return SYSINFO_RET_OK;
}

static int	agent_version(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	ZBX_UNUSED(request);

	SET_STR_RESULT(result, zbx_strdup(NULL, ZABBIX_VERSION));

	return SYSINFO_RET_OK;
}

static int	agent_variant(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	ZBX_UNUSED(request);

	SET_UI64_RESULT(result, 1);

	return SYSINFO_RET_OK;
}
