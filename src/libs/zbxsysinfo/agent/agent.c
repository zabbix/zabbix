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

extern char	*CONFIG_HOSTNAME;

static int	AGENT_HOSTNAME(AGENT_REQUEST *request, AGENT_RESULT *result);
static int	AGENT_PING(AGENT_REQUEST *request, AGENT_RESULT *result);
static int	AGENT_VERSION(AGENT_REQUEST *request, AGENT_RESULT *result);

ZBX_METRIC	parameters_agent[] =
/*	KEY			FLAG		FUNCTION	TEST PARAMETERS */
{
	{"agent.hostname",	0,		AGENT_HOSTNAME,	NULL},
	{"agent.ping",		0,		AGENT_PING, 	NULL},
	{"agent.version",	0,		AGENT_VERSION,	NULL},
	{NULL}
};

static int	AGENT_HOSTNAME(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	/* zabbix_agent standalone does not support the Hostname in the configuration file */
	if (NULL == CONFIG_HOSTNAME)
		return SYSINFO_RET_FAIL;

	SET_STR_RESULT(result, zbx_strdup(NULL, CONFIG_HOSTNAME));

	return SYSINFO_RET_OK;
}

static int	AGENT_PING(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	SET_UI64_RESULT(result, 1);

	return SYSINFO_RET_OK;
}

static int	AGENT_VERSION(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	SET_STR_RESULT(result, zbx_strdup(NULL, ZABBIX_VERSION));

	return SYSINFO_RET_OK;
}
