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

#include "zbxjson.h"
#include "zbxnum.h"
#include "zbxregexp.h"
#include "zbxcomms.h"

#include <sys/types.h>
#include <sys/socket.h>
#include <sys/ioctl.h>
#include <sys/sysctl.h>
#include <net/if.h>
#include <net/if_dl.h>
#include <net/if_media.h>
#include <ifaddrs.h>

static struct ifmibdata	ifmd;

static int	get_ifmib_general(const char *if_name, char **error)
{
	int	mib[6], ifcount;
	size_t	len;

	if (NULL == if_name || '\0' == *if_name)
	{
		*error = zbx_strdup(NULL, "Network interface name cannot be empty.");
		return FAIL;
	}

	mib[0] = CTL_NET;
	mib[1] = PF_LINK;
	mib[2] = NETLINK_GENERIC;
	mib[3] = IFMIB_SYSTEM;
	mib[4] = IFMIB_IFCOUNT;

	len = sizeof(ifcount);

	if (-1 == sysctl(mib, 5, &ifcount, &len, NULL, 0))
	{
		*error = zbx_dsprintf(NULL, "Cannot obtain system information: %s", zbx_strerror(errno));
		return FAIL;
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

			break;
		}

		if (0 == strcmp(ifmd.ifmd_name, if_name))
			return SUCCEED;
	}

	*error = zbx_strdup(NULL, "Cannot find information for this network interface.");

	return FAIL;
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

	if (FAIL == get_ifmib_general(if_name,&error))
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

	if (FAIL == get_ifmib_general(if_name, &error))
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

	if (FAIL == get_ifmib_general(if_name, &error))
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
	int		res;

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

	if (SYSINFO_RET_FAIL == (res = execute_int(command, result, request->timeout)))
		return res;

	if (1 < result->ui64)
		result->ui64 = 1;

	return res;
}

int	net_udp_listen(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char		*port_str, command[64];
	unsigned short	port;
	int		res;

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

	if (SYSINFO_RET_FAIL == (res = execute_int(command, result, request->timeout)))
		return res;

	if (1 < result->ui64)
		result->ui64 = 1;

	return res;
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

	if (FAIL == get_ifmib_general(if_name, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	SET_UI64_RESULT(result, ifmd.ifmd_data.ifi_collisions);

	return SYSINFO_RET_OK;
}

int	net_if_discovery(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	struct zbx_json		j;
	struct if_nameindex	*interfaces;

	if (NULL == (interfaces = if_nameindex()))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain system information: %s", zbx_strerror(errno)));
		return SYSINFO_RET_FAIL;
	}

	zbx_json_initarray(&j, ZBX_JSON_STAT_BUF_LEN);

	for (int i = 0; 0 != interfaces[i].if_index; i++)
	{
		zbx_json_addobject(&j, NULL);
		zbx_json_addstring(&j, "{#IFNAME}", interfaces[i].if_name, ZBX_JSON_TYPE_STRING);
		zbx_json_close(&j);
	}

	zbx_json_close(&j);

	SET_STR_RESULT(result, strdup(j.buffer));

	zbx_json_free(&j);

	if_freenameindex(interfaces);

	return SYSINFO_RET_OK;
}

static void	zbx_get_link_settings(const char* interface, struct zbx_json *j)
{
	int			sock;
	struct ifmediareq	ifmr;
	const char		*autoneg = "unknown";

	if (0 > (sock = socket(AF_INET, SOCK_DGRAM, 0)))
		return;

	memset(&ifmr, 0, sizeof(ifmr));
	zbx_strlcpy(ifmr.ifm_name, interface, sizeof(ifmr.ifm_name));

	if (0 > ioctl(sock, SIOCGIFMEDIA, &ifmr))
	{
		close(sock);
		return;
	}

	if (IFM_SUBTYPE(ifmr.ifm_current) == IFM_AUTO)
		autoneg = "on";
	else if (IFM_SUBTYPE(ifmr.ifm_current) == IFM_NONE)
		autoneg = "unknown";
	else
		autoneg = "off";

	zbx_json_addstring(j, "negotiation", autoneg, ZBX_JSON_TYPE_STRING);

	if (0 != (ifmr.ifm_current & IFM_FDX))
		zbx_json_addstring(j, "duplex", "full", ZBX_JSON_TYPE_STRING);
	else if (0 != (ifmr.ifm_current & IFM_HDX))
		zbx_json_addstring(j, "duplex", "half", ZBX_JSON_TYPE_STRING);
	else
		zbx_json_addstring(j, "duplex", "unknown", ZBX_JSON_TYPE_STRING);

	if (0 != (ifmr.ifm_status & IFM_AVALID))
		zbx_json_addint64(j, "carrier", (0 != (ifmr.ifm_status & IFM_ACTIVE)) ? 1 : 0);

	close(sock);
}

static void	get_link_flags(const char* interface, struct zbx_json *j)
{
	int		sock;
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

static void	if_description(const char* interface, struct zbx_json *j1, struct zbx_json *j2)
{
	int	sock;
	struct	ifreq ifr;
	char	desc[1024];

	sock = socket(AF_INET, SOCK_DGRAM, 0);
	if (0 > sock)
		return;

	memset(&ifr, 0, sizeof(ifr));
	memset(desc, 0, sizeof(desc));
	zbx_strlcpy(ifr.ifr_name, interface, sizeof(ifr.ifr_name));


	ifr.ifr_buffer.length = sizeof(desc);
	ifr.ifr_buffer.buffer = desc;

	if (0 == ioctl(sock, SIOCGIFDESCR, &ifr))
	{
		zbx_json_addstring(j1, "ifalias", desc, ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(j2, "ifalias", desc, ZBX_JSON_TYPE_STRING);
	}

	close(sock);
}

int	net_if_get(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	struct if_nameindex	*interfaces;
	struct zbx_json		jcfg, jval, j;
	zbx_regexp_t		*rxp = NULL;
	struct ifaddrs		*ifap, *ifa;
	char			*value = NULL, *pattern = NULL, *error = NULL;

	if (1 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	if (NULL == (interfaces = if_nameindex()) || 0 != getifaddrs(&ifap))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain system information: %s", zbx_strerror(errno)));
		return SYSINFO_RET_FAIL;
	}

	if (1 == request->nparam)
	{
		pattern = get_rparam(request, 0);

		if (FAIL == zbx_regexp_compile(pattern, &rxp, &error))
		{
			SET_MSG_RESULT(result, zbx_dsprintf(NULL, "invalid regular expression: %s", error));
			zbx_free(error);
			if_freenameindex(interfaces);
			freeifaddrs(ifap);
			return SYSINFO_RET_FAIL;
		}
	}

	zbx_json_initarray(&jval, ZBX_JSON_STAT_BUF_LEN);
	zbx_json_initarray(&jcfg, ZBX_JSON_STAT_BUF_LEN);

	for (int i = 0; 0 != interfaces[i].if_index; i++)
	{
		const char *if_name = interfaces[i].if_name;

		if (NULL != rxp && 0 != zbx_regexp_match_precompiled(if_name, rxp))
			continue;

		if (SYSINFO_RET_FAIL == get_ifmib_general(if_name, &error))
		{
			zbx_free(error);
			continue;
		}

		zbx_json_addobject(&jcfg, NULL);
		zbx_json_addobject(&jval, NULL);
		zbx_json_addstring(&jcfg, "name", if_name, ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&jval, "name", if_name, ZBX_JSON_TYPE_STRING);

		get_link_flags(if_name, &jcfg);
		zbx_json_addobject(&jval, "in");
		zbx_json_adduint64(&jval, "bytes", ifmd.ifmd_data.ifi_ibytes);
		zbx_json_adduint64(&jval, "packets", ifmd.ifmd_data.ifi_ipackets);
		zbx_json_adduint64(&jval, "errors", ifmd.ifmd_data.ifi_ierrors);
		zbx_json_close(&jval);
		zbx_json_addobject(&jval, "out");
		zbx_json_adduint64(&jval, "bytes", ifmd.ifmd_data.ifi_obytes);
		zbx_json_adduint64(&jval, "packets", ifmd.ifmd_data.ifi_opackets);
		zbx_json_adduint64(&jval, "errors", ifmd.ifmd_data.ifi_oerrors);
		zbx_json_adduint64(&jval, "collisions", ifmd.ifmd_data.ifi_collisions);
		zbx_json_close(&jval);
		zbx_json_addint64(&jval, "speed", ifmd.ifmd_data.ifi_baudrate / 1000000);

		for (ifa = ifap; ifa != NULL; ifa = ifa->ifa_next)
		{
			if (strcmp(ifa->ifa_name, if_name) == 0 &&
				NULL != ifa->ifa_addr && ifa->ifa_addr->sa_family == AF_LINK)
			{
				struct sockaddr_dl	*sdl = (struct sockaddr_dl *)ifa->ifa_addr;
				unsigned char		*mac = (unsigned char *)LLADDR(sdl);

				zbx_json_addint64(&jval, "type", sdl->sdl_type);

				value = zbx_dsprintf(NULL, "%02x:%02x:%02x:%02x:%02x:%02x",
						mac[0], mac[1], mac[2], mac[3], mac[4], mac[5]);

				zbx_json_addstring(&jval, "mac", value, ZBX_JSON_TYPE_STRING);
				zbx_json_addstring(&jcfg, "mac", value, ZBX_JSON_TYPE_STRING);

				zbx_free(value);
				break;
			}
		}

		if_description(if_name, &jcfg, &jval);
		zbx_get_link_settings(if_name, &jval);
		zbx_json_close(&jval);
		zbx_json_close(&jcfg);
	}

	if (NULL != rxp)
		zbx_regexp_free(rxp);

	if_freenameindex(interfaces);
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
