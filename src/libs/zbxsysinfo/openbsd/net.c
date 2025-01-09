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

#include "zbxjson.h"

#include <sys/ioctl.h>
#include <sys/sockio.h>

#if OpenBSD >= 201405			/* if OpenBSD 5.5 or newer */
#	if OpenBSD >= 201510		/* if Openbsd 5.8 or newer */
#		include <sys/malloc.h>	/* Workaround: include malloc.h here without _KERNEL to prevent its */
					/* inclusion later from if_var.h to avoid malloc() and free() redefinition. */
#		define _KERNEL	/* define _KERNEL to enable 'ifnet' and 'ifnet_head' definitions in if_var.h */
#	endif
#	include <net/if_var.h>  /* structs ifnet and ifnet_head are defined in this header since OpenBSD 5.5 */
#endif

static struct nlist kernel_symbols[] =
{
	{"_ifnet", N_UNDF, 0, 0, 0},
	{"_tcbtable", N_UNDF, 0, 0, 0},
	{NULL, 0, 0, 0, 0}
};


static int	get_ifdata(const char *if_name,
		zbx_uint64_t *ibytes, zbx_uint64_t *ipackets, zbx_uint64_t *ierrors, zbx_uint64_t *idropped,
		zbx_uint64_t *obytes, zbx_uint64_t *opackets, zbx_uint64_t *oerrors,
		zbx_uint64_t *tbytes, zbx_uint64_t *tpackets, zbx_uint64_t *terrors,
		zbx_uint64_t *icollisions, char **error)
{
#define IFNET_ID 0
	struct ifnet_head	head;
	struct ifnet		*ifp;

	kvm_t	*kp;
	int	len = 0, ret = SYSINFO_RET_FAIL;

	if (NULL == if_name || '\0' == *if_name)
	{
		*error = zbx_strdup(NULL, "Network interface name cannot be empty.");
		return SYSINFO_RET_FAIL;
	}

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

					if (0 == strcmp(if_name, v.if_xname))
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
						if (icollisions)
							*icollisions += v.if_collisions;

						ret = SYSINFO_RET_OK;
					}
				}
			}
		}
		kvm_close(kp);

		if (SYSINFO_RET_FAIL == ret)
			*error = zbx_strdup(NULL, "Cannot find information for this network interface.");
	}
	else
	{
		/* fallback to using SIOCGIFDATA */

		int		if_s;
		struct ifreq	ifr;
		struct if_data	v;

		if ((if_s = socket(AF_INET, SOCK_DGRAM, 0)) < 0)
		{
			*error = zbx_dsprintf(NULL, "Cannot create socket: %s", zbx_strerror(errno));
			goto clean;
		}

		zbx_strlcpy(ifr.ifr_name, if_name, IFNAMSIZ - 1);
		ifr.ifr_data = (caddr_t)&v;

		if (ioctl(if_s, SIOCGIFDATA, &ifr) < 0)
		{
			*error = zbx_dsprintf(NULL, "Cannot set socket parameters: %s", zbx_strerror(errno));
			goto clean;
		}

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
		if (icollisions)
			*icollisions += v.ifi_collisions;

		ret = SYSINFO_RET_OK;
clean:
		if (if_s >= 0)
			close(if_s);
	}

	return ret;
#undef IFNET_ID
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
