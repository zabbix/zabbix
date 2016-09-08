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

#define _KERNEL	1

#include <sys/param.h>
#include <sys/proc.h>
#include <sys/socket.h>
#include <sys/sockio.h>
#include <net/if.h>
#include <fcntl.h>
#include <kvm.h>

#if OpenBSD >= 201405
#	include <net/if_var.h>	/* structs ifnet and ifnet_head are defined in this header since OpenBSD 5.5 */
#endif

#include "zbxtypes.h"

#define IFDATA_RET_FAIL	0
#define IFDATA_RET_OK	1

static struct nlist kernel_symbols[] =
{
	{"_ifnet", N_UNDF, 0, 0, 0},
	{"_tcbtable", N_UNDF, 0, 0, 0},
	{NULL, 0, 0, 0, 0}
};

#define IFNET_ID 0

int	get_ifdata(const char *if_name,
		zbx_uint64_t *ibytes, zbx_uint64_t *ipackets, zbx_uint64_t *ierrors, zbx_uint64_t *idropped,
		zbx_uint64_t *obytes, zbx_uint64_t *opackets, zbx_uint64_t *oerrors,
		zbx_uint64_t *tbytes, zbx_uint64_t *tpackets, zbx_uint64_t *terrors,
		zbx_uint64_t *icollisions)
{
	struct ifnet_head	head;
	struct ifnet		*ifp;

	kvm_t	*kp;
	int	len = 0;
	int	ret = IFDATA_RET_FAIL;

	if (NULL == if_name || '\0' == *if_name)
		return IFDATA_RET_FAIL;

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
						ret = IFDATA_RET_OK;
					}
				}
			}
		}
		kvm_close(kp);
	}
	else
	{
		/* fallback to using SIOCGIFDATA */

		int		if_s;
		struct ifreq	ifr;
		struct if_data	v;

		if ((if_s = socket(AF_INET, SOCK_DGRAM, 0)) < 0)
			goto clean;

		zbx_strlcpy(ifr.ifr_name, if_name, IFNAMSIZ - 1);
		ifr.ifr_data = (caddr_t)&v;

		if (ioctl(if_s, SIOCGIFDATA, &ifr) < 0)
			goto clean;

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

		ret = IFDATA_RET_OK;
clean:
		if (if_s >= 0)
			close(if_s);
	}

	return ret;
}
