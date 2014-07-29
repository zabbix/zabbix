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
#include "log.h"

static struct ifmibdata	ifmd;

static int	get_ifmib_general(const char *if_name, char **error)
{
	int	mib[6], ifcount;
	size_t	len;

	if (NULL == if_name || '\0'== *if_name)
	{
		*error = zbx_strdup(NULL, "Network interface name cannot be empty.");
		return SYSINFO_RET_FAIL;
	}

	mib[0] = CTL_NET;
	mib[1] = PF_LINK;
	mib[2] = NETLINK_GENERIC;
	mib[3] = IFMIB_SYSTEM;
	mib[4] = IFMIB_IFCOUNT;

	len = sizeof(ifcount);

	if (-1 == sysctl(mib, 5, &ifcount, &len, NULL, 0))
	{
		*error = zbx_dsprintf(NULL, "Cannot obtain number of network interfaces: %s", zbx_strerror(errno));
		return SYSINFO_RET_FAIL;
	}

	mib[3] = IFMIB_IFDATA;
	mib[5] = IFDATA_GENERAL;

	len = sizeof(ifmd);

	for (mib[4] = 1; mib[4] <= ifcount; mib[4]++)
	{
		if (-1 == sysctl(mib, 6, &ifmd, &len, NULL, 0))
		{
			if (ENOENT == errno)
				continue;

			*error = zbx_dsprintf(NULL, "Cannot obtain network interface information: %s",
					zbx_strerror(errno));
			return SYSINFO_RET_FAIL;
		}

		if (0 == strcmp(ifmd.ifmd_name, if_name))
			return SYSINFO_RET_OK;
	}

	*error = zbx_strdup(NULL, "Cannot find information for this network interface.");

	return SYSINFO_RET_FAIL;
}

int	NET_IF_IN(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*if_name, *mode, *error;

	if (2 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	if_name = get_rparam(request, 0);
	mode = get_rparam(request, 1);

	if (SYSINFO_RET_FAIL == get_ifmib_general(if_name, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	if (NULL == mode || '\0' == *mode || 0 == strcmp(mode, "bytes"))	/* default parameter */
		SET_UI64_RESULT(result, ifmd.ifmd_data.ifi_ibytes);
	else if (0 == strcmp(mode, "packets"))
		SET_UI64_RESULT(result, ifmd.ifmd_data.ifi_ipackets);
	else if (0 == strcmp(mode, "errors"))
		SET_UI64_RESULT(result, ifmd.ifmd_data.ifi_ierrors);
	else if (0 == strcmp(mode, "dropped"))
		SET_UI64_RESULT(result, ifmd.ifmd_data.ifi_iqdrops);
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		return SYSINFO_RET_FAIL;
	}

	return SYSINFO_RET_OK;
}

int	NET_IF_OUT(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*if_name, *mode, *error;

	if (2 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	if_name = get_rparam(request, 0);
	mode = get_rparam(request, 1);

	if (SYSINFO_RET_FAIL == get_ifmib_general(if_name, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	if (NULL == mode || '\0' == *mode || 0 == strcmp(mode, "bytes"))	/* default parameter */
		SET_UI64_RESULT(result, ifmd.ifmd_data.ifi_obytes);
	else if (0 == strcmp(mode, "packets"))
		SET_UI64_RESULT(result, ifmd.ifmd_data.ifi_opackets);
	else if (0 == strcmp(mode, "errors"))
		SET_UI64_RESULT(result, ifmd.ifmd_data.ifi_oerrors);
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		return SYSINFO_RET_FAIL;
	}

	return SYSINFO_RET_OK;
}

int	NET_IF_TOTAL(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*if_name, *mode, *error;

	if (2 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	if_name = get_rparam(request, 0);
	mode = get_rparam(request, 1);

	if (SYSINFO_RET_FAIL == get_ifmib_general(if_name, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	if (NULL == mode || '\0' == *mode || 0 == strcmp(mode, "bytes"))	/* default parameter */
		SET_UI64_RESULT(result, (zbx_uint64_t)ifmd.ifmd_data.ifi_ibytes + ifmd.ifmd_data.ifi_obytes);
	else if (0 == strcmp(mode, "packets"))
		SET_UI64_RESULT(result, (zbx_uint64_t)ifmd.ifmd_data.ifi_ipackets + ifmd.ifmd_data.ifi_opackets);
	else if (0 == strcmp(mode, "errors"))
		SET_UI64_RESULT(result, (zbx_uint64_t)ifmd.ifmd_data.ifi_ierrors + ifmd.ifmd_data.ifi_oerrors);
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		return SYSINFO_RET_FAIL;
	}

	return SYSINFO_RET_OK;
}

int     NET_TCP_LISTEN(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char		*port_str, command[64];
	unsigned short	port;
	int		ret;

	if (1 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	port_str = get_rparam(request, 0);

	if (NULL == port_str || SUCCEED != is_ushort(port_str, &port))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		return SYSINFO_RET_FAIL;
	}

	zbx_snprintf(command, sizeof(command), "netstat -an | grep '^tcp.*\\.%hu[^.].*LISTEN' | wc -l", port);

	if (SYSINFO_RET_FAIL == (ret = EXECUTE_INT(command, result)))
		return ret;

	if (1 < result->ui64)
		result->ui64 = 1;

	return ret;
}

int     NET_UDP_LISTEN(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char		*port_str, command[64];
	unsigned short	port;
	int		ret;

	if (1 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	port_str = get_rparam(request, 0);

	if (NULL == port_str || SUCCEED != is_ushort(port_str, &port))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		return SYSINFO_RET_FAIL;
	}

	zbx_snprintf(command, sizeof(command), "netstat -an | grep '^udp.*\\.%hu[^.].*\\*\\.\\*' | wc -l", port);

	if (SYSINFO_RET_FAIL == (ret = EXECUTE_INT(command, result)))
		return ret;

	if (1 < result->ui64)
		result->ui64 = 1;

	return ret;
}

int     NET_IF_COLLISIONS(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*if_name, *error;

	if (1 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	if_name = get_rparam(request, 0);

	if (SYSINFO_RET_FAIL == get_ifmib_general(if_name, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	SET_UI64_RESULT(result, ifmd.ifmd_data.ifi_collisions);

	return SYSINFO_RET_OK;
}
