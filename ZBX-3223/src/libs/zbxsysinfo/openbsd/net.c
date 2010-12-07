/*
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/

#include "common.h"

#include "sysinfo.h"

#include <sys/sockio.h>

static struct nlist kernel_symbols[] =
{
	{"_ifnet", N_UNDF, 0, 0, 0},
	{"_tcbtable", N_UNDF, 0, 0, 0},
	{NULL, 0, 0, 0, 0}
};

#define IFNET_ID 0

static int	get_ifdata(const char *if_name, zbx_uint64_t *ibytes, zbx_uint64_t *ipackets, zbx_uint64_t *ierrors, zbx_uint64_t *idropped,
						zbx_uint64_t *obytes, zbx_uint64_t *opackets, zbx_uint64_t *oerrors,
						zbx_uint64_t *tbytes, zbx_uint64_t *tpackets, zbx_uint64_t *terrors, zbx_uint64_t *tdropped,
						zbx_uint64_t *icollisions)
{
	struct ifnet_head	head;
	struct ifnet 		*ifp;

	kvm_t 	*kp;
	int	len = 0;
	int 	ret = SYSINFO_RET_FAIL;

	/* if(i)_ibytes;	total number of octets received */
	/* if(i)_ipackets;	packets received on interface */
	/* if(i)_ierrors;	input errors on interface */
	/* if(i)_iqdrops;	dropped on input, this interface */
	/* if(i)_obytes;	total number of octets sent */
	/* if(i)_opackets;	packets sent on interface */
	/* if(i)_oerrors;	output errors on interface */
	/* if(i)_collisions;	collisions on csma interfaces */

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
	if (tdropped)
		*tdropped = 0;
	if (icollisions)
		*icollisions = 0;

	if (NULL != (kp = kvm_open(NULL, NULL, NULL, O_RDONLY, NULL))) /* requires root privileges */
	{
		struct ifnet	v;

		if (N_UNDF == kernel_symbols[IFNET_ID].n_type)
			if (0 != kvm_nlist(kp, &kernel_symbols[0]))
				kernel_symbols[IFNET_ID].n_type = N_UNDF;

		if (N_UNDF != kernel_symbols[IFNET_ID].n_type)
		{
			len = sizeof(struct ifnet_head);

			if (kvm_read(kp, kernel_symbols[IFNET_ID].n_value, &head, len) >= len)
			{
				len = sizeof(struct ifnet);

				for (ifp = head.tqh_first; ifp; ifp = v.if_list.tqe_next)
				{
					if (kvm_read(kp, (u_long)ifp, &v, len) < len)
						break;

					if ('\0' == *if_name || 0 == strcmp(if_name, v.if_xname))
					{
						if (ibytes)
							*ibytes += v.if_ibytes;
						if (ipackets)
							*ipackets += v.if_ipackets;
						if (ierrors)
							*ierrors += v.if_ierrors;
						if (idropped)
							*idropped += v.if_iqdrops;
						if (obytes)
							*obytes += v.if_obytes;
						if (opackets)
							*opackets += v.if_opackets;
						if (oerrors)
							*oerrors += v.if_oerrors;
						if (tbytes)
							*tbytes += v.if_ibytes + v.if_obytes;
						if (tpackets)
							*tpackets += v.if_ipackets + v.if_opackets;
						if (terrors)
							*terrors += v.if_ierrors + v.if_oerrors;
						if (tdropped)
							*tdropped += v.if_iqdrops;
						if (icollisions)
							*icollisions += v.if_collisions;
						ret = SYSINFO_RET_OK;
					}
				}
			}
		}
		kvm_close(kp);
	}
	else
	{
		/* Fallback to using SIOCGIFDATA */

		int		if_s;
		struct ifreq	ifr;
		struct if_data	v;

		if ((if_s = socket(AF_INET, SOCK_DGRAM, 0)) < 0)
			goto clean;

		zbx_strlcpy(ifr.ifr_name, if_name, IFNAMSIZ - 1);
		ifr.ifr_data = (caddr_t)&v;

		if (ioctl(if_s, SIOCGIFDATA, &ifr) < 0)
			goto clean;

		if ('\0' == *if_name || 0 == strcmp(if_name, ifr.ifr_name))
		{
			if (ibytes)
				*ibytes += v.ifi_ibytes;
			if (ipackets)
				*ipackets += v.ifi_ipackets;
			if (ierrors)
				*ierrors += v.ifi_ierrors;
			if (idropped)
				*idropped += v.ifi_iqdrops;
			if (obytes)
				*obytes += v.ifi_obytes;
			if (opackets)
				*opackets += v.ifi_opackets;
			if (oerrors)
				*oerrors += v.ifi_oerrors;
			if (tbytes)
				*tbytes += v.ifi_ibytes + v.ifi_obytes;
			if (tpackets)
				*tpackets += v.ifi_ipackets + v.ifi_opackets;
			if (terrors)
				*terrors += v.ifi_ierrors + v.ifi_oerrors;
			if (tdropped)
				*tdropped += v.ifi_iqdrops;
			if (icollisions)
				*icollisions += v.ifi_collisions;
		}

		ret = SYSINFO_RET_OK;
clean:
		if (if_s >= 0)
			close(if_s);
	}

	return ret;
}

static int	NET_IF_IN_BYTES(const char *if_name, AGENT_RESULT *result)
{
	zbx_uint64_t	value;

	if (SYSINFO_RET_OK != get_ifdata(if_name, &value, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, value);

	return SYSINFO_RET_OK;
}

static int	NET_IF_IN_PACKETS(const char *if_name, AGENT_RESULT *result)
{
	zbx_uint64_t	value;

	if (SYSINFO_RET_OK != get_ifdata(if_name, NULL, &value, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, value);

	return SYSINFO_RET_OK;
}

static int	NET_IF_IN_ERRORS(const char *if_name, AGENT_RESULT *result)
{
	zbx_uint64_t	value;

	if (SYSINFO_RET_OK != get_ifdata(if_name, NULL, NULL, &value, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, value);

	return SYSINFO_RET_OK;
}

static int	NET_IF_IN_DROPPED(const char *if_name, AGENT_RESULT *result)
{
	zbx_uint64_t	value;

	if (SYSINFO_RET_OK != get_ifdata(if_name, NULL, NULL, NULL, &value, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, value);

	return SYSINFO_RET_OK;
}

int	NET_IF_IN(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#define NET_FNCLIST struct net_fnclist_s
NET_FNCLIST
{
	char	*mode;
	int	(*function)();
};

	NET_FNCLIST fl[] =
	{
		{"bytes",	NET_IF_IN_BYTES},
		{"packets",	NET_IF_IN_PACKETS},
		{"errors",	NET_IF_IN_ERRORS},
		{"dropped",	NET_IF_IN_DROPPED},
		{0,		0}
	};

	char	if_name[MAX_STRING_LEN];
	char	mode[MAX_STRING_LEN];
	int	i;

	assert(result);

	init_result(result);

	if (num_param(param) > 2)
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, if_name, sizeof(if_name)))
		*if_name = '\0';

	if (0 != get_param(param, 2, mode, sizeof(mode)))
		*mode = '\0';

	/* default parameter */
	if (*mode == '\0')
		zbx_snprintf(mode, sizeof(mode), "bytes");

	for (i = 0; fl[i].mode != 0; i++)
		if (0 == strncmp(mode, fl[i].mode, MAX_STRING_LEN))
			return (fl[i].function)(if_name, result);

	return SYSINFO_RET_FAIL;
}

static int	NET_IF_OUT_BYTES(const char *if_name, AGENT_RESULT *result)
{
	zbx_uint64_t	value;

	if (SYSINFO_RET_OK != get_ifdata(if_name, NULL, NULL, NULL, NULL, &value, NULL, NULL, NULL, NULL, NULL, NULL, NULL))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, value);

	return SYSINFO_RET_OK;
}

static int      NET_IF_OUT_PACKETS(const char *if_name, AGENT_RESULT *result)
{
	zbx_uint64_t	value;

	if (SYSINFO_RET_OK != get_ifdata(if_name, NULL, NULL, NULL, NULL, NULL, &value, NULL, NULL, NULL, NULL, NULL, NULL))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, value);

	return SYSINFO_RET_OK;
}

static int      NET_IF_OUT_ERRORS(const char *if_name, AGENT_RESULT *result)
{
	zbx_uint64_t	value;

	if (SYSINFO_RET_OK != get_ifdata(if_name, NULL, NULL, NULL, NULL, NULL, NULL, &value, NULL, NULL, NULL, NULL, NULL))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, value);

	return SYSINFO_RET_OK;
}

int	NET_IF_OUT(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#define NET_FNCLIST struct net_fnclist_s
NET_FNCLIST
{
	char	*mode;
	int	(*function)();
};

	NET_FNCLIST fl[] =
	{
		{"bytes",	NET_IF_OUT_BYTES},
		{"packets",	NET_IF_OUT_PACKETS},
		{"errors",	NET_IF_OUT_ERRORS},
/*		{"dropped",	NET_IF_OUT_DROPPED},*/
		{0,		0}
	};

	char	if_name[MAX_STRING_LEN];
	char	mode[MAX_STRING_LEN];
	int	i;

	assert(result);

	init_result(result);

	if (num_param(param) > 2)
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, if_name, sizeof(if_name)))
		*if_name = '\0';

	if (0 != get_param(param, 2, mode, sizeof(mode)))
		*mode = '\0';

	/* default parameter */
	if (*mode == '\0')
		zbx_snprintf(mode, sizeof(mode), "bytes");

	for (i = 0; fl[i].mode != 0; i++)
		if (0 == strncmp(mode, fl[i].mode, MAX_STRING_LEN))
			return (fl[i].function)(if_name, result);

	return SYSINFO_RET_FAIL;
}

static int	NET_IF_TOTAL_BYTES(const char *if_name, AGENT_RESULT *result)
{
	zbx_uint64_t	value;

	if (SYSINFO_RET_OK != get_ifdata(if_name, NULL, NULL, NULL, NULL, NULL, NULL, NULL, &value, NULL, NULL, NULL, NULL))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, value);

	return SYSINFO_RET_OK;
}

static int	NET_IF_TOTAL_PACKETS(const char *if_name, AGENT_RESULT *result)
{
	zbx_uint64_t	value;

	if (SYSINFO_RET_OK != get_ifdata(if_name, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, &value, NULL, NULL, NULL))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, value);

	return SYSINFO_RET_OK;
}

static int	NET_IF_TOTAL_ERRORS(const char *if_name, AGENT_RESULT *result)
{
	zbx_uint64_t	value;

	if (SYSINFO_RET_OK != get_ifdata(if_name, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, &value, NULL, NULL))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, value);

	return SYSINFO_RET_OK;
}

static int	NET_IF_TOTAL_DROPPED(const char *if_name, AGENT_RESULT *result)
{
	zbx_uint64_t	value;

	if (SYSINFO_RET_OK != get_ifdata(if_name, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, &value, NULL))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, value);

	return SYSINFO_RET_OK;
}

int	NET_IF_TOTAL(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#define NET_FNCLIST struct net_fnclist_s
NET_FNCLIST
{
	char	*mode;
	int	(*function)();
};

	NET_FNCLIST fl[] =
	{
		{"bytes",	NET_IF_TOTAL_BYTES},
		{"packets",	NET_IF_TOTAL_PACKETS},
		{"errors",	NET_IF_TOTAL_ERRORS},
/*		{"dropped",	NET_IF_TOTAL_DROPPED},*/ /* disabled because net.if.out does not support dropped packets */
		{0,		0}
	};

	char	if_name[MAX_STRING_LEN];
	char	mode[MAX_STRING_LEN];
	int	i;

	assert(result);

	init_result(result);

	if (num_param(param) > 2)
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, if_name, sizeof(if_name)))
		*if_name = '\0';

	if (0 != get_param(param, 2, mode, sizeof(mode)))
		*mode = '\0';

	/* default parameter */
	if (*mode == '\0')
		zbx_snprintf(mode, sizeof(mode), "bytes");

	for (i = 0; fl[i].mode != 0; i++)
		if (0 == strncmp(mode, fl[i].mode, MAX_STRING_LEN))
			return (fl[i].function)(if_name, result);

	return SYSINFO_RET_FAIL;
}

int     NET_TCP_LISTEN(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	assert(result);

	init_result(result);

	return SYSINFO_RET_FAIL;
}

int     NET_IF_COLLISIONS(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	zbx_uint64_t	value;
	char		if_name[MAX_STRING_LEN];

	assert(result);

	init_result(result);

	if (num_param(param) > 1)
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, if_name, sizeof(if_name)))
		*if_name = '\0';

	if (SYSINFO_RET_OK != get_ifdata(if_name, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, &value))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, value);

	return SYSINFO_RET_OK;
}
