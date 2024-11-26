/*
** Copyright (C) 2001-2024 Zabbix SIA
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

#include "zbxjson.h"

#include <net/route.h>
#include <net/if_dl.h>

static void get_rtaddrs(int, struct sockaddr *, struct sockaddr **);

static void
get_rtaddrs(int addrs, struct sockaddr *sa, struct sockaddr **rti_info)
{
	int i;

	for (i = 0; i < RTAX_MAX; i++) {
		if (addrs & (1 << i)) {
			rti_info[i] = sa;
			sa = (struct sockaddr *)((char *)(sa) +
					RT_ROUNDUP(sa->sa_len));
		} else
			rti_info[i] = NULL;
	}
}

static int	get_ifdata(const char *if_name,
		zbx_uint64_t *ibytes, zbx_uint64_t *ipackets, zbx_uint64_t *ierrors, zbx_uint64_t *idropped,
		zbx_uint64_t *obytes, zbx_uint64_t *opackets, zbx_uint64_t *oerrors,
		zbx_uint64_t *tbytes, zbx_uint64_t *tpackets, zbx_uint64_t *terrors,
		zbx_uint64_t *icollisions, char **error)
{
	struct  if_msghdr *ifm;
	int     mib[6] = { CTL_NET, AF_ROUTE, 0, 0, NET_RT_IFLIST, 0 };
	static char *buf;
	char    *lim, *next;
	struct  rt_msghdr *rtm;
	struct  if_data *ifd = NULL;
	struct  sockaddr *sa, *rti_info[RTAX_MAX];
	struct  sockaddr_dl *sdl;

	size_t  len = 0;
	static size_t olen;
	int	ret = SYSINFO_RET_FAIL;
	static char name[IFNAMSIZ];


	if (NULL == if_name || '\0' == *if_name)
	{
		*error = zbx_strdup(NULL, "Network interface name cannot be empty.");
		return ret;
	}

	if (sysctl(mib, 6, NULL, &len, NULL, 0) == -1) {
		*error = zbx_strdup(NULL, "Failed to read network interfaces data");
		return ret;
	}

	if (len > olen) {
		if (buf)
			free(buf);
		if ((buf = zbx_malloc(NULL, len)) == NULL) {
			*error = zbx_strdup(NULL, "Failed to allocate buffer for network interfaces data");
			return ret;
		}
		olen = len;
	}
	if (sysctl(mib, 6, buf, &len, NULL, 0) == -1) {
		*error = zbx_strdup(NULL, "Failed to allocate buffer for network interfaces data");
		return ret;
	}

	lim = buf + len;
	for (next = buf; next < lim; next += rtm->rtm_msglen) {
		rtm = (struct rt_msghdr *)next;
		if ((rtm->rtm_version == RTM_VERSION) &&
			(rtm->rtm_type == RTM_IFINFO))
		{
			ifm = (struct if_msghdr *)next;
			ifd = &ifm->ifm_data;

			sa = (struct sockaddr *)(ifm + 1);
			get_rtaddrs(ifm->ifm_addrs, sa, rti_info);

			sdl = (struct sockaddr_dl *)rti_info[RTAX_IFP];
			if (sdl == NULL || sdl->sdl_family != AF_LINK) {
				continue;
			}
			bzero(name, sizeof(name));
			if (sdl->sdl_nlen >= IFNAMSIZ)
				memcpy(name, sdl->sdl_data, IFNAMSIZ - 1);
			else if (sdl->sdl_nlen > 0)
				memcpy(name, sdl->sdl_data, sdl->sdl_nlen);
			else
				continue;

			/* ibytes;		total number of octets received */
			/* ipackets;		packets received on interface */
			/* ierrors;		input errors on interface */
			/* iqdrops;		dropped on input, this interface */
			/* obytes;		total number of octets sent */
			/* opackets;		packets sent on interface */
			/* oerrors;		output errors on interface */
			/* icollisions;		collisions on csma interfaces */
			/* tbytes;		total (in+out) bytes */
			/* tpackets;		total (in+out) packets */
			/* terrors;		total (in+out) errors */

			if (ibytes)
				*ibytes = 0;
			if (ipackets)
				*ipackets = 0;
			if (ierrors)
				*ierrors = 0;
			if (idropped)
				*idropped = 0;
			if (obytes)
				*obytes = 0;
			if (opackets)
				*opackets = 0;
			if (oerrors)
				*oerrors = 0;
			if (tbytes)
				*tbytes = 0;
			if (tpackets)
				*tpackets = 0;
			if (terrors)
				*terrors = 0;
			if (icollisions)
				*icollisions = 0;

			if (0 == strcmp(if_name, name))
			{
				if (ibytes)
					*ibytes += ifd->ifi_ibytes;
				if (ipackets)
					*ipackets += ifd->ifi_ipackets;
				if (ierrors)
					*ierrors += ifd->ifi_ierrors;
				if (idropped)
					*idropped += ifd->ifi_iqdrops;
				if (obytes)
					*obytes += ifd->ifi_obytes;
				if (opackets)
					*opackets += ifd->ifi_opackets;
				if (oerrors)
					*oerrors += ifd->ifi_oerrors;
				if (tbytes)
					*tbytes += ifd->ifi_ibytes +
						ifd->ifi_obytes;
				if (tpackets)
					*tpackets += ifd->ifi_ipackets +
						ifd->ifi_opackets;
				if (terrors)
					*terrors += ifd->ifi_ierrors +
						ifd->ifi_oerrors;
				if (icollisions)
					*icollisions += ifd->ifi_collisions;
				return SYSINFO_RET_OK;
			}
		}
	}

	if (SYSINFO_RET_FAIL == ret)
	{
		*error = zbx_strdup(NULL,
			"Cannot find information for this network interface.");
	}

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
