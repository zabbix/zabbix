/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

#include <unistd.h>
#include <stropts.h>
#include <sys/dlpi.h>
#include <sys/dlpi_ext.h>
#include <sys/mib.h>

#include "common.h"
#include "sysinfo.h"
#include "zbxjson.h"

#define PPA(n) (*(dl_hp_ppa_info_t *)(ppa_data_buf + n * sizeof(dl_hp_ppa_info_t)))

static char	buf_ctl[1024];

/* Low Level Discovery needs a way to get the list of network interfaces available */
/* on the monitored system. HP-UX versions starting from 11.31 have if_nameindex() */
/* available in libc, older versions have it in libipv6 which we do not want to    */
/* depend on. So for older versions we use different code to get that list.        */
/* More information:                                                               */
/* h20000.www2.hp.com/bc/docs/support/SupportManual/c02258083/c02258083.pdf        */

static struct strbuf	ctlbuf =
{
	sizeof(buf_ctl),
	0,
	buf_ctl
};

#if HPUX_VERSION < 1131

#define ZBX_IF_SEP	','

static void	add_if_name(char **if_list, size_t *if_list_alloc, size_t *if_list_offset, const char *name)
{
	if (FAIL == str_in_list(*if_list, name, ZBX_IF_SEP))
	{
		if ('\0' != **if_list)
			zbx_chrcpy_alloc(if_list, if_list_alloc, if_list_offset, ZBX_IF_SEP);

		zbx_strcpy_alloc(if_list, if_list_alloc, if_list_offset, name);
	}
}

static int	get_if_names(char **if_list, size_t *if_list_alloc, size_t *if_list_offset)
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

int	NET_IF_DISCOVERY(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	struct zbx_json	j;
	char		*if_name;
#if HPUX_VERSION < 1131
	char		*if_list = NULL, *if_name_end;
	size_t		if_list_alloc = 64, if_list_offset = 0;

	if_list = zbx_malloc(if_list, if_list_alloc);
	*if_list = '\0';

	if (FAIL == get_if_names(&if_list, &if_list_alloc, &if_list_offset))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain network interface information."));
		zbx_free(if_list);
		return SYSINFO_RET_FAIL;
	}

	zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);

	zbx_json_addarray(&j, ZBX_PROTO_TAG_DATA);

	if_name = if_list;

	while (NULL != if_name)
	{
		if (NULL != (if_name_end = strchr(if_name, ZBX_IF_SEP)))
			*if_name_end = '\0';

		zbx_json_addobject(&j, NULL);
		zbx_json_addstring(&j, "{#IFNAME}", if_name, ZBX_JSON_TYPE_STRING);
		zbx_json_close(&j);

		if (NULL != if_name_end)
		{
			*if_name_end = ZBX_IF_SEP;
			if_name = if_name_end + 1;
		}
		else
			if_name = NULL;
	}

	zbx_free(if_list);
#else
	struct if_nameindex	*ni;
	int			i;

	if (NULL == (ni = if_nameindex()))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain system information: %s", zbx_strerror(errno)));
		return SYSINFO_RET_FAIL;
	}

	zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);

	zbx_json_addarray(&j, ZBX_PROTO_TAG_DATA);

	for (i = 0; 0 != ni[i].if_index; i++)
	{
		zbx_json_addobject(&j, NULL);
		zbx_json_addstring(&j, "{#IFNAME}", ni[i].if_name, ZBX_JSON_TYPE_STRING);
		zbx_json_close(&j);
	}

	if_freenameindex(ni);
#endif
	zbx_json_close(&j);

	SET_STR_RESULT(result, zbx_strdup(NULL, j.buffer));

	zbx_json_free(&j);

	return SYSINFO_RET_OK;
}

/* attaches to a PPA via an already open stream to DLPI provider */
static int	dlpi_attach(int fd, int ppa)
{
	dl_attach_req_t		attach_req;
	int			flags = RS_HIPRI;

	attach_req.dl_primitive = DL_ATTACH_REQ;
	attach_req.dl_ppa = ppa;

	ctlbuf.len = sizeof(attach_req);
	ctlbuf.buf = (char *)&attach_req;

	if (0 != putmsg(fd, &ctlbuf, NULL, flags))
		return FAIL;

	ctlbuf.buf = buf_ctl;
	ctlbuf.maxlen = sizeof(buf_ctl);

	if (0 > getmsg(fd, &ctlbuf, NULL, &flags))
		return FAIL;

	if (DL_OK_ACK != *(int *)buf_ctl)
		return FAIL;

	/* Successfully attached to a PPA. */
	return SUCCEED;
}

/* Detaches from a PPA via an already open stream to DLPI provider. */
static int	dlpi_detach(int fd)
{
	dl_detach_req_t		detach_req;
	int			flags = RS_HIPRI;

	detach_req.dl_primitive = DL_DETACH_REQ;

	ctlbuf.len = sizeof(detach_req);
	ctlbuf.buf = (char *)&detach_req;

	if (0 != putmsg(fd, &ctlbuf, NULL, flags))
		return FAIL;

	ctlbuf.buf = buf_ctl;
	ctlbuf.maxlen = sizeof(buf_ctl);

	if (0 > getmsg(fd, &ctlbuf, NULL, &flags))
		return FAIL;

	if (DL_OK_ACK != *(int *)buf_ctl)
		return FAIL;

	/* successfully detached */
	return SUCCEED;
}

static int	dlpi_get_stats(int fd, Ext_mib_t *mib)
{
	dl_get_statistics_req_t		stat_req;
	dl_get_statistics_ack_t		stat_msg;
	int				flags = RS_HIPRI;

	stat_req.dl_primitive = DL_GET_STATISTICS_REQ;

	ctlbuf.len = sizeof(stat_req);
	ctlbuf.buf = (char *)&stat_req;

	if (0 != putmsg(fd, &ctlbuf, NULL, flags))
		return FAIL;

	ctlbuf.buf = buf_ctl;
	ctlbuf.maxlen = sizeof(buf_ctl);

	if (0 > getmsg(fd, &ctlbuf, NULL, &flags))
		return FAIL;

	if (DL_GET_STATISTICS_ACK != *(int *)buf_ctl)
		return FAIL;

	stat_msg = *(dl_get_statistics_ack_t *)buf_ctl;

	memcpy(mib, (Ext_mib_t *)(buf_ctl + stat_msg.dl_stat_offset), sizeof(Ext_mib_t));

	return SUCCEED;
}

static int get_ppa(int fd, const char *if_name, int *ppa)
{
	dl_hp_ppa_req_t		ppa_req;
	dl_hp_ppa_ack_t		*dlp;
	int			i, ret = FAIL, flags = RS_HIPRI, res;
	char			*buf = NULL, *ppa_data_buf = NULL;

	ppa_req.dl_primitive = DL_HP_PPA_REQ;

	ctlbuf.len = sizeof(ppa_req);
	ctlbuf.buf = (char *)&ppa_req;

	if (0 != putmsg(fd, &ctlbuf, NULL, flags))
		return ret;

	ctlbuf.buf = buf_ctl;
	ctlbuf.maxlen = DL_HP_PPA_ACK_SIZE;

	res = getmsg(fd, &ctlbuf, NULL, &flags);

	/* get the head first */
	if (0 > res)
		return ret;

	dlp = (dl_hp_ppa_ack_t *)ctlbuf.buf;

	if (DL_HP_PPA_ACK != dlp->dl_primitive)
		return ret;

	if (DL_HP_PPA_ACK_SIZE > ctlbuf.len)
		return ret;

	if (MORECTL == res)
	{
		size_t	if_name_sz = strlen(if_name) + 1;

		ctlbuf.maxlen = dlp->dl_count * sizeof(dl_hp_ppa_info_t);
		ctlbuf.len = 0;

		ppa_data_buf = zbx_malloc(ppa_data_buf, (size_t)ctlbuf.maxlen);

		ctlbuf.buf = ppa_data_buf;

		/* get the data */
		if (0 > getmsg(fd, &ctlbuf, NULL, &flags) || ctlbuf.len < dlp->dl_length)
		{
			zbx_free(ppa_data_buf);
			return ret;
		}

		buf = zbx_malloc(buf, if_name_sz);

		for (i = 0; i < dlp->dl_count; i++)
		{
			zbx_snprintf(buf, if_name_sz, "%s%d", PPA(i).dl_module_id_1, PPA(i).dl_ppa);

			if (0 == strcmp(if_name, buf))
			{
				*ppa = PPA(i).dl_ppa;
				ret = SUCCEED;
				break;
			}
		}

		zbx_free(buf);
		zbx_free(ppa_data_buf);
	}

	return ret;
}

static int	get_net_stat(Ext_mib_t *mib, const char *if_name)
{
	int	fd, ppa;

	if (-1 == (fd = open("/dev/dlpi", O_RDWR)))
		return FAIL;

	if (FAIL == get_ppa(fd, if_name, &ppa))
	{
		close(fd);
		return FAIL;
	}

	if (FAIL == dlpi_attach(fd, ppa))
		return FAIL;

	if (FAIL == dlpi_get_stats(fd, mib))
		return FAIL;

	dlpi_detach(fd);

	close(fd);

	return SUCCEED;
}

int	NET_IF_IN(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char		*if_name, *mode;
	Ext_mib_t	mib;

	if (2 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	if_name = get_rparam(request, 0);
	mode = get_rparam(request, 1);

	if (FAIL == get_net_stat(&mib, if_name))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain network interface information."));
		return SYSINFO_RET_FAIL;
	}

	if (NULL == mode || '\0' == *mode || 0 == strcmp(mode, "bytes"))
		SET_UI64_RESULT(result, mib.mib_if.ifInOctets);
	else if (0 == strcmp(mode, "packets"))
		SET_UI64_RESULT(result, mib.mib_if.ifInUcastPkts + mib.mib_if.ifInNUcastPkts);
	else if (0 == strcmp(mode, "errors"))
		SET_UI64_RESULT(result, mib.mib_if.ifInErrors);
	else if (0 == strcmp(mode, "dropped"))
		SET_UI64_RESULT(result, mib.mib_if.ifInDiscards);
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		return SYSINFO_RET_FAIL;
	}

	return SYSINFO_RET_OK;
}

int	NET_IF_OUT(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char		*if_name, *mode;
	Ext_mib_t	mib;

	if (2 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	if_name = get_rparam(request, 0);
	mode = get_rparam(request, 1);

	if (FAIL == get_net_stat(&mib, if_name))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain network interface information."));
		return SYSINFO_RET_FAIL;
	}

	if (NULL == mode || '\0' == *mode || 0 == strcmp(mode, "bytes"))
		SET_UI64_RESULT(result, mib.mib_if.ifOutOctets);
	else if (0 == strcmp(mode, "packets"))
		SET_UI64_RESULT(result, mib.mib_if.ifOutUcastPkts + mib.mib_if.ifOutNUcastPkts);
	else if (0 == strcmp(mode, "errors"))
		SET_UI64_RESULT(result, mib.mib_if.ifOutErrors);
	else if (0 == strcmp(mode, "dropped"))
		SET_UI64_RESULT(result, mib.mib_if.ifOutDiscards);
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		return SYSINFO_RET_FAIL;
	}

	return SYSINFO_RET_OK;
}

int	NET_IF_TOTAL(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char		*if_name, *mode;
	Ext_mib_t	mib;

	if (2 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	if_name = get_rparam(request, 0);
	mode = get_rparam(request, 1);

	if (FAIL == get_net_stat(&mib, if_name))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain network interface information."));
		return SYSINFO_RET_FAIL;
	}

	if (NULL == mode || '\0' == *mode || 0 == strcmp(mode, "bytes"))
	{
		SET_UI64_RESULT(result, mib.mib_if.ifInOctets + mib.mib_if.ifOutOctets);
	}
	else if (0 == strcmp(mode, "packets"))
	{
		SET_UI64_RESULT(result, mib.mib_if.ifInUcastPkts + mib.mib_if.ifInNUcastPkts
				+ mib.mib_if.ifOutUcastPkts + mib.mib_if.ifOutNUcastPkts);
	}
	else if (0 == strcmp(mode, "errors"))
	{
		SET_UI64_RESULT(result, mib.mib_if.ifInErrors + mib.mib_if.ifOutErrors);
	}
	else if (0 == strcmp(mode, "dropped"))
	{
		SET_UI64_RESULT(result, mib.mib_if.ifInDiscards + mib.mib_if.ifOutDiscards);
	}
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		return SYSINFO_RET_FAIL;
	}

	return SYSINFO_RET_OK;
}
