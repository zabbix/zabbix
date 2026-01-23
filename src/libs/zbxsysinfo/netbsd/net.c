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

#include <net/route.h>
#include <net/if.h>
#include <net/if_dl.h>
#include <net/if_types.h>
#include <net/if_media.h>
#include <sys/types.h>
#include <sys/socket.h>
#include <sys/ioctl.h>
#include <sys/sysctl.h>
#include <ifaddrs.h>

#include "zbxregexp.h"
#include "zbxcomms.h"
#include "zbxsysinfo.h"
#include "../sysinfo.h"

#include "zbxjson.h"

static void	get_rtaddrs(int addrs, struct sockaddr *sa, struct sockaddr **rti_info)
{
	int i;

	for (i = 0; i < RTAX_MAX; i++)
	{
		if (addrs & (1 << i))
		{
			rti_info[i] = sa;
			sa = (struct sockaddr *)((char *)(sa) + RT_ROUNDUP(sa->sa_len));
		}
		else
			rti_info[i] = NULL;
	}
}

static int	get_ifdata(const char *if_name,
				zbx_uint64_t *ibytes, zbx_uint64_t *ipackets,
				zbx_uint64_t *ierrors, zbx_uint64_t *idropped,
				zbx_uint64_t *obytes, zbx_uint64_t *opackets,
				zbx_uint64_t *oerrors, zbx_uint64_t *tbytes,
				zbx_uint64_t *tpackets, zbx_uint64_t *terrors,
				zbx_uint64_t *icollisions, char **error)
{
	static size_t		olen;
	static char		*buf = NULL;
	int			ret = SYSINFO_RET_FAIL;
	int			mib[6] = { CTL_NET, AF_ROUTE, 0, 0, NET_RT_IFLIST, 0 };
	size_t			len;
	char			name[IFNAMSIZ + 1];
	char			*next, *end, *cp;
	struct if_msghdr	*ifm;
	struct rt_msghdr	*rtm;
	struct if_data		*ifd = NULL;
	struct sockaddr		*sa, *rti_info[RTAX_MAX];
	struct sockaddr_dl	*sdl;

	if (NULL == if_name || '\0' == *if_name)
	{
		*error = zbx_strdup(NULL, "Network interface name cannot be empty.");
		return FAIL;
	}

	if (-1 == sysctl(mib, 6, NULL, &len, NULL, 0))
		return FAIL;

	if (len > olen)
	{
		zbx_free(buf);

		if (NULL == (buf = zbx_malloc(buf, len)))
		{
			*error = zbx_strdup(NULL, "sysctl get-length failed");
			return FAIL;
		}

		olen = len;
	}

	if (-1 == sysctl(mib, 6, buf, &len, NULL, 0))
	{
		*error = zbx_strdup(NULL, "sysctl get-if-list failed");
		return FAIL;
	}

	for (next = buf, end = buf + len; next < end; next += rtm->rtm_msglen)
	{
		rtm = (struct rt_msghdr *)next;
		if (RTM_VERSION != rtm->rtm_version)
			continue;

		if (RTM_IFINFO != rtm->rtm_type)
			continue;

		ifm = (struct if_msghdr *)next;
		ifd = &ifm->ifm_data;
		sa = (struct sockaddr *)(ifm + 1);
		get_rtaddrs(ifm->ifm_addrs, sa, rti_info);

		sdl = (struct sockaddr_dl *)rti_info[RTAX_IFP];

		memset(name, 0, sizeof(name));
		if (IFNAMSIZ <= sdl->sdl_nlen)
			memcpy(name, sdl->sdl_data, IFNAMSIZ - 1);
		else if (0 < sdl->sdl_nlen)
			memcpy(name, sdl->sdl_data, sdl->sdl_nlen);

		if (0 != strcmp(name, if_name))
			continue;

		/*
		 * ifi_ibytes		total number of octets received
		 * ifi_ipackets		packets received on interface
		 * ifi_ierrors		input errors on interface
		 * ifi_iqdrops		dropped on input, this interface
		 * ifi_obytes		total number of octets sent
		 * ifi_opackets		packets sent on interface
		 * ifi_oerrors		output errors on interface
		 * ifi_collisions	collisions on csma interfaces
		 */

		if (ibytes)
			*ibytes = ifd->ifi_ibytes;
		if (ipackets)
			*ipackets = ifd->ifi_ipackets;
		if (ierrors)
			*ierrors = ifd->ifi_ierrors;
		if (idropped)
			*idropped = ifd->ifi_iqdrops;
		if (obytes)
			*obytes = ifd->ifi_obytes;
		if (opackets)
			*opackets = ifd->ifi_opackets;
		if (oerrors)
			*oerrors = ifd->ifi_oerrors;
		if (tbytes)
			*tbytes = ifd->ifi_ibytes + ifd->ifi_obytes;
		if (tpackets)
			*tpackets = ifd->ifi_ipackets + ifd->ifi_opackets;
		if (terrors)
			*terrors = ifd->ifi_ierrors + ifd->ifi_oerrors;
		if (icollisions)
			*icollisions = ifd->ifi_collisions;

		ret = SYSINFO_RET_OK;
	}

	if (SYSINFO_RET_OK != ret)
		*error = zbx_strdup(NULL, "Cannot find information for this network interface.");

	return ret;
}

int	net_if_in(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char		*if_name, *mode, *error;
	zbx_uint64_t	ibytes, ipackets, ierrors, idropped;

	if (2 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	if_name = get_rparam(request, 0);
	mode = get_rparam(request, 1);

	if (SYSINFO_RET_OK != get_ifdata(if_name, &ibytes, &ipackets, &ierrors, &idropped, NULL, NULL, NULL, NULL, NULL,
			NULL, NULL, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	if (NULL == mode || '\0' == *mode || 0 == strcmp(mode, "bytes"))	/* default parameter */
		SET_UI64_RESULT(result, ibytes);
	else if (0 == strcmp(mode, "packets"))
		SET_UI64_RESULT(result, ipackets);
	else if (0 == strcmp(mode, "errors"))
		SET_UI64_RESULT(result, ierrors);
	else if (0 == strcmp(mode, "dropped"))
		SET_UI64_RESULT(result, idropped);
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		return SYSINFO_RET_FAIL;
	}

	return SYSINFO_RET_OK;
}

int	net_if_out(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char		*if_name, *mode, *error;
	zbx_uint64_t	obytes, opackets, oerrors;

	if (2 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	if_name = get_rparam(request, 0);
	mode = get_rparam(request, 1);

	if (SYSINFO_RET_OK != get_ifdata(if_name, NULL, NULL, NULL, NULL, &obytes, &opackets, &oerrors, NULL, NULL,
			NULL, NULL, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	if (NULL == mode || '\0' == *mode || 0 == strcmp(mode, "bytes"))	/* default parameter */
		SET_UI64_RESULT(result, obytes);
	else if (0 == strcmp(mode, "packets"))
		SET_UI64_RESULT(result, opackets);
	else if (0 == strcmp(mode, "errors"))
		SET_UI64_RESULT(result, oerrors);
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		return SYSINFO_RET_FAIL;
	}

	return SYSINFO_RET_OK;
}

int	net_if_total(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char		*if_name, *mode, *error;
	zbx_uint64_t	tbytes, tpackets, terrors;

	if (2 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	if_name = get_rparam(request, 0);
	mode = get_rparam(request, 1);

	if (SYSINFO_RET_OK != get_ifdata(if_name, NULL, NULL, NULL, NULL, NULL, NULL, NULL, &tbytes, &tpackets,
			&terrors, NULL, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	if (NULL == mode || '\0' == *mode || 0 == strcmp(mode, "bytes"))	/* default parameter */
		SET_UI64_RESULT(result, tbytes);
	else if (0 == strcmp(mode, "packets"))
		SET_UI64_RESULT(result, tpackets);
	else if (0 == strcmp(mode, "errors"))
		SET_UI64_RESULT(result, terrors);
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		return SYSINFO_RET_FAIL;
	}

	return SYSINFO_RET_OK;
}

int	net_if_collisions(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char		*if_name, *error;
	zbx_uint64_t	icollisions;

	if (1 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	if_name = get_rparam(request, 0);

	if (SYSINFO_RET_OK != get_ifdata(if_name, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL,
			NULL, &icollisions, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	SET_UI64_RESULT(result, icollisions);

	return SYSINFO_RET_OK;
}

int	net_if_discovery(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	int			i;
	struct zbx_json		j;
	struct if_nameindex	*interfaces;

	if (NULL == (interfaces = if_nameindex()))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain system information: %s", zbx_strerror(errno)));
		return SYSINFO_RET_FAIL;
	}

	zbx_json_initarray(&j, ZBX_JSON_STAT_BUF_LEN);

	for (i = 0; 0 != interfaces[i].if_index; i++)
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

static void get_link_flags(const char* interface, struct zbx_json *j)
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

static void if_description(const char* interface, struct zbx_json *j1, struct zbx_json *j2)
{
#ifdef SIOCGIFDESCR
	int	sock, ret;
	struct	ifreq ifr;
	char	desc[IFDESCRSIZE];

	sock = socket(AF_INET, SOCK_DGRAM, 0);

	if (0 > sock)
		return;

	memset(&ifr, 0, sizeof(ifr));
	memset(desc, 0, sizeof(desc));
	zbx_strlcpy(ifr.ifr_name, interface, sizeof(ifr.ifr_name));
	ifr.ifr_buflen = IFDESCRSIZE;
	ifr.ifr_buf = desc;

	if (-1 != (ret = ioctl(sock, SIOCGIFDESCR, &ifr)))
	{
		if ('\0' != *desc)
		{
			zbx_json_addstring(j1, "ifalias", desc, ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(j2, "ifalias", desc, ZBX_JSON_TYPE_STRING);
		}
	}

	close(sock);
#else
	ZBX_UNUSED(interface);
	ZBX_UNUSED(j1);
	ZBX_UNUSED(j2);
#endif
}

int	net_if_get(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	struct if_nameindex	*interfaces;
	struct zbx_json		jcfg, jval, j;
	zbx_regexp_t		*rxp = NULL;
	struct ifaddrs		*ifap, *ifa;
	char			*value = NULL, *pattern = NULL, *error = NULL;
	int			sock;

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

	if ((sock = socket(AF_INET, SOCK_DGRAM, 0)) < 0)
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot create socket: %s", zbx_strerror(errno)));
		if_freenameindex(interfaces);
		freeifaddrs(ifap);
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
			close(sock);
			return SYSINFO_RET_FAIL;
		}
	}

	zbx_json_initarray(&jval, ZBX_JSON_STAT_BUF_LEN);
	zbx_json_initarray(&jcfg, ZBX_JSON_STAT_BUF_LEN);

	for (int i = 0; 0 != interfaces[i].if_index; i++)
	{
		const char		*if_name = interfaces[i].if_name;
		struct ifdatareq	ifdr;

		if (NULL != rxp && 0 != zbx_regexp_match_precompiled(if_name, rxp))
			continue;

		memset(&ifdr, 0, sizeof(ifdr));
		zbx_strlcpy(ifdr.ifdr_name, if_name, sizeof(ifdr.ifdr_name));

		zbx_json_addobject(&jcfg, NULL);
		zbx_json_addobject(&jval, NULL);
		zbx_json_addstring(&jcfg, "name", if_name, ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&jval, "name", if_name, ZBX_JSON_TYPE_STRING);
		if_description(if_name, &jcfg, &jval);

		get_link_flags(if_name, &jcfg);

		if (0 == ioctl(sock, SIOCGIFDATA, &ifdr))
		{
			zbx_json_addobject(&jval, "in");
			zbx_json_adduint64(&jval, "bytes", ifdr.ifdr_data.ifi_ibytes);
			zbx_json_adduint64(&jval, "packets", ifdr.ifdr_data.ifi_ipackets);
			zbx_json_adduint64(&jval, "errors", ifdr.ifdr_data.ifi_ierrors);
			zbx_json_close(&jval);
			zbx_json_addobject(&jval, "out");
			zbx_json_adduint64(&jval, "bytes", ifdr.ifdr_data.ifi_obytes);
			zbx_json_adduint64(&jval, "packets", ifdr.ifdr_data.ifi_opackets);
			zbx_json_adduint64(&jval, "errors", ifdr.ifdr_data.ifi_oerrors);
			zbx_json_adduint64(&jval, "collisions", ifdr.ifdr_data.ifi_collisions);
			zbx_json_close(&jval);

			if (0 < ifdr.ifdr_data.ifi_baudrate)
				zbx_json_addint64(&jval, "speed", ifdr.ifdr_data.ifi_baudrate / 1000000);
		}

		for (ifa = ifap; ifa != NULL; ifa = ifa->ifa_next)
		{
			if (strcmp(ifa->ifa_name, if_name) == 0 &&
				NULL != ifa->ifa_addr && ifa->ifa_addr->sa_family == AF_LINK)
			{
				struct sockaddr_dl	*sdl = (struct sockaddr_dl *)ifa->ifa_addr;
				unsigned char		*mac = (unsigned char *)LLADDR(sdl);

				zbx_json_addint64(&jval, "type", sdl->sdl_type);

				if (sdl->sdl_alen > 0)
				{
					value = zbx_dsprintf(NULL, "%02x:%02x:%02x:%02x:%02x:%02x",
							mac[0], mac[1], mac[2], mac[3], mac[4], mac[5]);
					zbx_json_addstring(&jval, "mac", value, ZBX_JSON_TYPE_STRING);
					zbx_json_addstring(&jcfg, "mac", value, ZBX_JSON_TYPE_STRING);
					zbx_free(value);
				}
				break;
			}
		}

		zbx_get_link_settings(if_name, &jval);
		zbx_json_close(&jval);
		zbx_json_close(&jcfg);
	}

	if (NULL != rxp)
		zbx_regexp_free(rxp);

	close(sock);
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
