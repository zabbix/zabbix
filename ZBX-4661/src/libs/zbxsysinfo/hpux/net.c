/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
#include "zbxjson.h"

/* Low Level Discovery needs a way to get the list of network interfaces available */
/* on the monitored system. HP-UX versions starting from 11.31 have if_nameindex() */
/* available in libc, older versions have it in libipv6 which we do not want to    */
/* depend on. So for older versions we use different code to get that list.        */
/* More information:                                                               */
/* h20000.www2.hp.com/bc/docs/support/SupportManual/c02258083/c02258083.pdf        */

#if HPUX_VERSION < 1131

#define ZBX_IF_SEP	','

void	add_if_name(char **if_list, size_t *if_list_alloc, size_t *if_list_offset, const char *name)
{
	if (FAIL == str_in_list(*if_list, name, ZBX_IF_SEP))
	{
		if ('\0' != **if_list)
			zbx_chrcpy_alloc(if_list, if_list_alloc, if_list_offset, ZBX_IF_SEP);

		zbx_strcpy_alloc(if_list, if_list_alloc, if_list_offset, name);
	}
}

int	get_if_names(char **if_list, size_t *if_list_alloc, size_t *if_list_offset)
{
	int			s, ifreq_size, numifs, i, family = AF_INET;
	struct sockaddr		*from;
	size_t			fromlen;
	u_char			*buffer = NULL;
	struct ifconf		ifc;
	struct ifreq		*ifr;
	struct if_laddrconf	lifc;
	struct if_laddrreq	*lifr;

	if (-1 == (s = socket(family, SOCK_DGRAM, 0)))
		return FAIL;

	ifc.ifc_buf = 0;
	ifc.ifc_len = 0;

	if (0 == ioctl(s, SIOCGIFCONF, (caddr_t)&ifc) && 0 != ifc.ifc_len)
		ifreq_size = 2 * ifc.ifc_len;
	else
		ifreq_size = 2 * 512;

	buffer = zbx_malloc(buffer, ifreq_size);
	memset(buffer, 0, ifreq_size);

	ifc.ifc_buf = (caddr_t)buffer;
	ifc.ifc_len = ifreq_size;

	if (-1 == ioctl(s, SIOCGIFCONF, &ifc))
		goto next;

	/* check all IPv4 interfaces */
	ifr = (struct ifreq *)ifc.ifc_req;
	while ((u_char *)ifr < (u_char *)(buffer + ifc.ifc_len))
	{
		from = &ifr->ifr_addr;

		if (AF_INET6 != from->sa_family && AF_INET != from->sa_family)
			continue;

		add_if_name(if_list, if_list_alloc, if_list_offset, ifr->ifr_name);

#ifdef _SOCKADDR_LEN
		ifr = (struct ifreq *)((char *)ifr + sizeof(*ifr) + (from->sa_len > sizeof(*from) ? from->sa_len - sizeof(*from) : 0));
#else
		ifr++;
#endif
	}
next:
	zbx_free(buffer);
	close(s);

#if defined (SIOCGLIFCONF)
	family = AF_INET6;

	if (-1 == (s = socket(family, SOCK_DGRAM, 0)))
		return FAIL;

	i = ioctl(s, SIOCGLIFNUM, (char *)&numifs);
	if (0 == numifs)
	{
		close(s);
		return SUCCEED;
	}

	lifc.iflc_len = numifs * sizeof(struct if_laddrreq);
	lifc.iflc_buf = zbx_malloc(NULL, lifc.iflc_len);
	buffer = (u_char *)lifc.iflc_buf;

	if (-1 == ioctl(s, SIOCGLIFCONF, &lifc))
		goto end;

	/* check all IPv6 interfaces */
	for (lifr = lifc.iflc_req; '\0' != *lifr->iflr_name; lifr++)
	{
		from = (struct sockaddr *)&lifr->iflr_addr;

		if (AF_INET6 != from->sa_family && AF_INET != from->sa_family)
			continue;

		add_if_name(if_list, if_list_alloc, if_list_offset, lifr->iflr_name);
	}
end:
	zbx_free(buffer);
	close(s);
#endif
	return SUCCEED;
}
#endif	/* HPUX_VERSION < 1131 */

int	NET_IF_DISCOVERY(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#if HPUX_VERSION < 1131
	char			*if_list = NULL, *if_name_end;
	size_t			if_list_alloc = 64, if_list_offset = 0;
#else
	struct if_nameindex	*ni;
	int			i;
#endif
	struct zbx_json		j;
	char			*if_name;

	zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);

	zbx_json_addarray(&j, ZBX_PROTO_TAG_DATA);

#if HPUX_VERSION < 1131
	if_list = zbx_malloc(if_list, if_list_alloc);
	*if_list = '\0';

	if (FAIL == get_if_names(&if_list, &if_list_alloc, &if_list_offset))
		return SYSINFO_RET_FAIL;

	if_name = if_list;

	while (NULL != if_name)
	{
		if (NULL != (if_name_end = strchr(if_name, ZBX_IF_SEP)))
			*if_name_end = '\0';
#else
	for (ni = if_nameindex(), i = 0; 0 != ni[i].if_index; i++)
	{
		if_name = ni[i].if_name;
#endif
		zbx_json_addobject(&j, NULL);
		zbx_json_addstring(&j, "{#IFNAME}", if_name, ZBX_JSON_TYPE_STRING);
		zbx_json_close(&j);
#if HPUX_VERSION < 1131

		if (NULL != if_name_end)
		{
			*if_name_end = ZBX_IF_SEP;
			if_name = if_name_end + 1;
		}
		else
			if_name = NULL;
#endif
	}

#if HPUX_VERSION < 1131
	zbx_free(if_list);
#else
	if_freenameindex(ni);
#endif
	zbx_json_close(&j);

	SET_STR_RESULT(result, strdup(j.buffer));

	zbx_json_free(&j);

	return SYSINFO_RET_OK;
}
