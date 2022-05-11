/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
#include "modbtype.h"

extern char			*CONFIG_HOSTNAMES;
extern ZBX_THREAD_LOCAL char	*CONFIG_HOSTNAME;
extern char			*CONFIG_HOST_METADATA;
extern char			*CONFIG_HOST_METADATA_ITEM;

static int	AGENT_HOSTNAME(AGENT_REQUEST *request, AGENT_RESULT *result);
static int	AGENT_HOSTMETADATA(AGENT_REQUEST *request, AGENT_RESULT *result);
static int	AGENT_PING(AGENT_REQUEST *request, AGENT_RESULT *result);
static int	AGENT_VERSION(AGENT_REQUEST *request, AGENT_RESULT *result);
static int	AGENT_VARIANT(AGENT_REQUEST *request, AGENT_RESULT *result);

ZBX_METRIC	parameters_agent[] =
/*	KEY			FLAG		FUNCTION		TEST PARAMETERS */
{
	{"agent.hostname",	0,		AGENT_HOSTNAME,		NULL},
	{"agent.hostmetadata",	0,		AGENT_HOSTMETADATA,	NULL},
	{"agent.ping",		0,		AGENT_PING,		NULL},
	{"agent.variant",	0,		AGENT_VARIANT,		NULL},
	{"agent.version",	0,		AGENT_VERSION,		NULL},
	{"modbus.get",		CF_HAVEPARAMS,	MODBUS_GET,		"tcp://127.0.0.1"},
	{NULL}
};

static int	AGENT_HOSTNAME(AGENT_REQUEST *request, AGENT_RESULT *result)
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

static int	AGENT_HOSTMETADATA(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	int	ret = SYSINFO_RET_OK;

	ZBX_UNUSED(request);

	if (NULL != CONFIG_HOST_METADATA)
	{
		SET_STR_RESULT(result, zbx_strdup(NULL, CONFIG_HOST_METADATA));
	}
	else if (NULL != CONFIG_HOST_METADATA_ITEM)
	{
		if (SUCCEED != process(CONFIG_HOST_METADATA_ITEM, PROCESS_LOCAL_COMMAND | PROCESS_WITH_ALIAS, result) ||
				NULL == GET_STR_RESULT(result))
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

static int	AGENT_PING(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	ZBX_UNUSED(request);

	SET_UI64_RESULT(result, 1);

	return SYSINFO_RET_OK;
}

static int	AGENT_VERSION(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	ZBX_UNUSED(request);

	SET_STR_RESULT(result, zbx_strdup(NULL, ZABBIX_VERSION));

	return SYSINFO_RET_OK;
}

static int	AGENT_VARIANT(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	ZBX_UNUSED(request);

	SET_UI64_RESULT(result, 1);

	return SYSINFO_RET_OK;
}
