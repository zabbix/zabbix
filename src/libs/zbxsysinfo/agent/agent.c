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
#include "agent.h"

#include "modbtype.h"

static int	agent_hostname(AGENT_REQUEST *request, AGENT_RESULT *result);
static int	agent_hostmetadata(AGENT_REQUEST *request, AGENT_RESULT *result);
static int	agent_ping(AGENT_REQUEST *request, AGENT_RESULT *result);
static int	agent_version(AGENT_REQUEST *request, AGENT_RESULT *result);
static int	agent_variant(AGENT_REQUEST *request, AGENT_RESULT *result);

static zbx_metric_t	parameters_agent[] =
/*	KEY			FLAG		FUNCTION		TEST PARAMETERS */
{
	{"agent.hostname",	0,		agent_hostname,		NULL},
	{"agent.hostmetadata",	0,		agent_hostmetadata,	NULL},
	{"agent.ping",		0,		agent_ping,		NULL},
	{"agent.variant",	0,		agent_variant,		NULL},
	{"agent.version",	0,		agent_version,		NULL},
	{"modbus.get",		CF_HAVEPARAMS,	modbus_get,		"tcp://127.0.0.1"},
	{0}
};

zbx_metric_t	*get_parameters_agent(void)
{
	return &parameters_agent[0];
}

static int	agent_hostname(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	ZBX_UNUSED(request);

	if (NULL == sysinfo_get_config_hostname())
	{
		char	*p;

		SET_STR_RESULT(result, NULL != (p = strchr(sysinfo_get_config_hostnames(), ',')) ?
				zbx_dsprintf(NULL, "%.*s", (int)(p - sysinfo_get_config_hostnames()),
				sysinfo_get_config_hostnames()) : zbx_strdup(NULL, sysinfo_get_config_hostnames()));
	}
	else
		SET_STR_RESULT(result, zbx_strdup(NULL, sysinfo_get_config_hostname()));

	return SYSINFO_RET_OK;
}

static int	agent_hostmetadata(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	int	ret = SYSINFO_RET_OK;

	ZBX_UNUSED(request);

	if (NULL != sysinfo_get_config_host_metadata())
	{
		SET_STR_RESULT(result, zbx_strdup(NULL, sysinfo_get_config_host_metadata()));
	}
	else if (NULL != sysinfo_get_config_host_metadata_item())
	{
		if (SUCCEED != zbx_execute_agent_check(sysinfo_get_config_host_metadata_item(),
				ZBX_PROCESS_LOCAL_COMMAND | ZBX_PROCESS_WITH_ALIAS, result,
				ZBX_CHECK_TIMEOUT_UNDEFINED) || NULL == ZBX_GET_STR_RESULT(result))
		{
			SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot get host metadata using item \"%s\"",
					sysinfo_get_config_host_metadata_item()));
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
