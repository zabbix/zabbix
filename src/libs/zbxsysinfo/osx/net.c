/*
** Copyright (C) 2001-2026 Zabbix SIA
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

#include "../common/zbxsysinfo_common.h"

#include "zbxregexp.h"
#include "zbxjson.h"
#include "zbxcomms.h"
#include "zbxnum.h"
#include "zbxip.h"
#include "zbxstr.h"

#include <sys/types.h>
#include <sys/socket.h>
#include <sys/ioctl.h>
#include <sys/sysctl.h>
#include <net/if.h>
#include <net/if_dl.h>
#include <net/if_media.h>
#include <net/route.h>
#include <ifaddrs.h>
#include <net/if_types.h>

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

int	net_if_in(AGENT_REQUEST *request, AGENT_RESULT *result)
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

int	net_if_out(AGENT_REQUEST *request, AGENT_RESULT *result)
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

int	net_if_total(AGENT_REQUEST *request, AGENT_RESULT *result)
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

int	net_tcp_listen(AGENT_REQUEST *request, AGENT_RESULT *result)
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

	if (NULL == port_str || SUCCEED != zbx_is_ushort(port_str, &port))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		return SYSINFO_RET_FAIL;
	}

	zbx_snprintf(command, sizeof(command), "netstat -an | grep '^tcp.*\\.%hu[^.].*LISTEN' | wc -l", port);

	if (SYSINFO_RET_FAIL == (ret = execute_int(command, result, request->timeout)))
		return ret;

	if (1 < result->ui64)
		result->ui64 = 1;

	return ret;
}

int	net_udp_listen(AGENT_REQUEST *request, AGENT_RESULT *result)
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

	if (NULL == port_str || SUCCEED != zbx_is_ushort(port_str, &port))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		return SYSINFO_RET_FAIL;
	}

	zbx_snprintf(command, sizeof(command), "netstat -an | grep '^udp.*\\.%hu[^.].*\\*\\.\\*' | wc -l", port);

	if (SYSINFO_RET_FAIL == (ret = execute_int(command, result, request->timeout)))
		return ret;

	if (1 < result->ui64)
		result->ui64 = 1;

	return ret;
}

int	net_if_collisions(AGENT_REQUEST *request, AGENT_RESULT *result)
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

static void	zbx_get_link_settings(const char* interface, struct zbx_json *j)
{
	int			sock;
	struct ifmediareq	ifmr;
	const char		*autoneg;

	sock = socket(AF_INET, SOCK_DGRAM, 0);
	if (0 > sock)
		return;

	memset(&ifmr, 0, sizeof(ifmr));
	zbx_strlcpy(ifmr.ifm_name, interface, sizeof(ifmr.ifm_name));

	if (0 > ioctl(sock, SIOCGIFMEDIA, &ifmr))
	{
		close(sock);
		return;
	}

	if (0 != (ifmr.ifm_status & IFM_AVALID))
		zbx_json_addint64(j, "carrier", (0 != (ifmr.ifm_status & IFM_ACTIVE)) ? 1 : 0);

	zbx_json_addint64(j, "speed", ifmd.ifmd_data.ifi_baudrate / 1000000);

	if (0 != (ifmr.ifm_current & IFM_FDX))
		zbx_json_addstring(j, "duplex", "full", ZBX_JSON_TYPE_STRING);
	else if (0 != (ifmr.ifm_current & IFM_HDX))
		zbx_json_addstring(j, "duplex", "half", ZBX_JSON_TYPE_STRING);
	else
		zbx_json_addstring(j, "duplex", "unknown", ZBX_JSON_TYPE_STRING);

	if (IFM_AUTO == IFM_SUBTYPE(ifmr.ifm_current))
	{
		autoneg = "on";
	} else if (IFM_NONE == IFM_SUBTYPE(ifmr.ifm_current))
	{
		autoneg = "unknown";
	}  else {
		autoneg = "off";
	}

	zbx_json_addstring(j, "negotiation", autoneg, ZBX_JSON_TYPE_STRING);

	close(sock);
}

static void	zbx_get_link_flags(const char* interface, struct zbx_json *j)
{
	int	sock;
	struct ifreq	ifr;

	if (0 > (sock = socket(AF_INET, SOCK_DGRAM, 0)))
		return;

	memset(&ifr, 0, sizeof(ifr));
	zbx_strlcpy(ifr.ifr_name, interface, sizeof(ifr.ifr_name));

	if (0 > ioctl(sock, SIOCGIFFLAGS, &ifr))
	{
		close(sock);
		return;
	}

	zbx_json_addstring(j, "administrative_state",
					(0 != (ifr.ifr_flags & IFF_UP)) ? "up" : "down", ZBX_JSON_TYPE_STRING);

	zbx_json_addstring(j, "operational_state",
					(0 != (ifr.ifr_flags & IFF_RUNNING)) ? "up" : "down", ZBX_JSON_TYPE_STRING);

	close(sock);
}

static char	*zbx_get_interface_description(const char* interface)
{
	FILE	*fp;
	size_t	cmd_alloc = 0, cmd_offset = 0;
	char	*cmd, *desc = NULL;;
	char	line[MAX_STRING_LEN];

	zbx_snprintf_alloc(&cmd, &cmd_alloc, &cmd_offset, "networksetup -listallhardwareports | grep -B 1 'Device: %s'",
			interface);

	if (NULL == (fp = popen(cmd, "r")))
	{
		zbx_free(cmd);
		return NULL;
	}

	zbx_free(cmd);

	while (NULL != fgets(line, sizeof(line) - 1, fp))
	{
		if (NULL != strstr(line, "Hardware Port:"))
		{
			desc = strchr(line, ':');
			if (NULL != desc)
			{
				desc++;
				while (*desc == ' ')
					desc++;
				desc[strcspn(desc, "\n")] = 0;
				pclose(fp);

				return zbx_strdup(NULL, desc);
			}
		}
	}

	pclose(fp);

	return NULL;
}

int	net_if_get(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	struct ifaddrs		*ifap, *ifa;
	struct zbx_json		jcfg, jval, j;
	zbx_regexp_t		*rxp = NULL;
	char			*pmac, *value = NULL, *pattern = NULL, *error = NULL;
	unsigned char		*mac;

	if (1 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return FAIL;
	}

	if (0 != getifaddrs(&ifap))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot get interface list: %s", zbx_strerror(errno)));
		return SYSINFO_RET_FAIL;
	}

	if (1 == request->nparam)
	{
		pattern = get_rparam(request, 0);

		if (FAIL == zbx_regexp_compile(pattern, &rxp, &error))
		{
			SET_MSG_RESULT(result, zbx_dsprintf(NULL, "invalid regular expression: %s", error));
			zbx_free(error);
			freeifaddrs(ifap);
			return FAIL;
		}
	}

	zbx_json_initarray(&jval, ZBX_JSON_STAT_BUF_LEN);
	zbx_json_initarray(&jcfg, ZBX_JSON_STAT_BUF_LEN);

	for (ifa = ifap; NULL != ifa; ifa = ifa->ifa_next)
	{
		if (NULL == ifa->ifa_addr)
			continue;

		if (AF_LINK != ifa->ifa_addr->sa_family)
			continue;

		if (NULL != rxp && 0 != zbx_regexp_match_precompiled(ifa->ifa_name, rxp))
			continue;

		if (SYSINFO_RET_FAIL == get_ifmib_general(ifa->ifa_name, &error))
		{
			zbx_free(error);
			continue;
		}

		zbx_json_addobject(&jcfg, NULL);
		zbx_json_addobject(&jval, NULL);
		zbx_json_addstring(&jcfg, "name", ifa->ifa_name, ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&jval, "name", ifa->ifa_name, ZBX_JSON_TYPE_STRING);
		zbx_get_link_flags(ifa->ifa_name, &jcfg);

		if (NULL != (pmac = zbx_get_interface_description(ifa->ifa_name)))
		{
			zbx_json_addstring(&jcfg, "ifalias", pmac, ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&jval, "ifalias", pmac, ZBX_JSON_TYPE_STRING);
			zbx_free(pmac);
		}

		mac = (unsigned char *)LLADDR((struct sockaddr_dl *)ifa->ifa_addr);
		value = zbx_dsprintf(NULL, "%02x:%02x:%02x:%02x:%02x:%02x",
				mac[0], mac[1], mac[2], mac[3], mac[4], mac[5]);

		zbx_json_addstring(&jcfg, "mac", value, ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&jval, "mac", value, ZBX_JSON_TYPE_STRING);

		zbx_free(value);

		zbx_json_addobject(&jval, "in");
		zbx_json_adduint64(&jval, "bytes", ifmd.ifmd_data.ifi_ibytes);
		zbx_json_adduint64(&jval, "packets", ifmd.ifmd_data.ifi_ipackets);
		zbx_json_adduint64(&jval, "errors", ifmd.ifmd_data.ifi_ierrors);
		zbx_json_adduint64(&jval, "dropped", ifmd.ifmd_data.ifi_iqdrops);

		zbx_json_close(&jval);
		zbx_json_addobject(&jval, "out");
		zbx_json_adduint64(&jval, "bytes", ifmd.ifmd_data.ifi_obytes);
		zbx_json_adduint64(&jval, "packets", ifmd.ifmd_data.ifi_opackets);
		zbx_json_adduint64(&jval, "errors", ifmd.ifmd_data.ifi_oerrors);
		zbx_json_adduint64(&jval, "collisions", ifmd.ifmd_data.ifi_collisions);
		zbx_json_close(&jval);
		zbx_json_addint64(&jval, "type", ((struct sockaddr_dl *)ifa->ifa_addr)->sdl_type);

		zbx_get_link_settings(ifa->ifa_name, &jval);
		zbx_json_close(&jval);
		zbx_json_close(&jcfg);
	}

	if (NULL != rxp)
		zbx_regexp_free(rxp);

	freeifaddrs(ifap);
	zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);
	zbx_json_addraw(&j, "config", jcfg.buffer);
	zbx_json_addraw(&j, "values", jval.buffer);
	zbx_json_close(&j);

	SET_STR_RESULT(result, strdup(j.buffer));

	zbx_json_free(&j);
	zbx_json_free(&jval);
	zbx_json_free(&jcfg);

	return SYSINFO_RET_OK;
}
