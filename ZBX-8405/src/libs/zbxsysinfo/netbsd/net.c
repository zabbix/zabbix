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
#include "zbxjson.h"

static struct nlist kernel_symbols[] =
{
	{"_ifnet", N_UNDF, 0, 0, 0},
	{"_tcbtable", N_UNDF, 0, 0, 0},
	{NULL, 0, 0, 0, 0}
};

#define IFNET_ID 0

static int	get_ifdata(const char *if_name,
		zbx_uint64_t *ibytes, zbx_uint64_t *ipackets, zbx_uint64_t *ierrors, zbx_uint64_t *idropped,
		zbx_uint64_t *obytes, zbx_uint64_t *opackets, zbx_uint64_t *oerrors,
		zbx_uint64_t *tbytes, zbx_uint64_t *tpackets, zbx_uint64_t *terrors,
		zbx_uint64_t *icollisions)
{
	struct ifnet_head	head;
	struct ifnet		*ifp;
	struct ifnet		v;

	kvm_t	*kp;
	int	len = 0;
	int	ret = SYSINFO_RET_FAIL;

	if (NULL == if_name || '\0' == *if_name)
		return SYSINFO_RET_FAIL;

	if (NULL != (kp = kvm_open(NULL, NULL, NULL, O_RDONLY, NULL))) /* requires root privileges */
	{
		if (N_UNDF == kernel_symbols[IFNET_ID].n_type)
			if (0 != kvm_nlist(kp, &kernel_symbols[0]))
				kernel_symbols[IFNET_ID].n_type = N_UNDF;

		if (N_UNDF != kernel_symbols[IFNET_ID].n_type)
		{
			len = sizeof(struct ifnet_head);

			if (kvm_read(kp, kernel_symbols[IFNET_ID].n_value, &head, len) >= len)
			{
				len = sizeof(struct ifnet);

				/* if_ibytes;		total number of octets received */
				/* if_ipackets;		packets received on interface */
				/* if_ierrors;		input errors on interface */
				/* if_iqdrops;		dropped on input, this interface */
				/* if_obytes;		total number of octets sent */
				/* if_opackets;		packets sent on interface */
				/* if_oerrors;		output errors on interface */
				/* if_collisions;	collisions on csma interfaces */

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
	}

	return ret;
}

int	NET_IF_IN(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char		*if_name, *mode;
	zbx_uint64_t	ibytes, ipackets, ierrors, idropped;

	if (2 < request->nparam)
		return SYSINFO_RET_FAIL;

	if_name = get_rparam(request, 0);
	mode = get_rparam(request, 1);

	if (SYSINFO_RET_OK != get_ifdata(if_name, &ibytes, &ipackets, &ierrors, &idropped, NULL, NULL, NULL, NULL, NULL, NULL, NULL))
		return SYSINFO_RET_FAIL;

	if (NULL == mode || '\0' == *mode || 0 == strcmp(mode, "bytes"))	/* default parameter */
		SET_UI64_RESULT(result, ibytes);
	else if (0 == strcmp(mode, "packets"))
		SET_UI64_RESULT(result, ipackets);
	else if (0 == strcmp(mode, "errors"))
		SET_UI64_RESULT(result, ierrors);
	else if (0 == strcmp(mode, "dropped"))
		SET_UI64_RESULT(result, idropped);
	else
		return SYSINFO_RET_FAIL;

	return SYSINFO_RET_OK;
}

int	NET_IF_OUT(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char		*if_name, *mode;
	zbx_uint64_t	obytes, opackets, oerrors;

	if (2 < request->nparam)
		return SYSINFO_RET_FAIL;

	if_name = get_rparam(request, 0);
	mode = get_rparam(request, 1);

	if (SYSINFO_RET_OK != get_ifdata(if_name, NULL, NULL, NULL, NULL, &obytes, &opackets, &oerrors, NULL, NULL, NULL, NULL))
		return SYSINFO_RET_FAIL;

	if (NULL == mode || '\0' == *mode || 0 == strcmp(mode, "bytes"))	/* default parameter */
		SET_UI64_RESULT(result, obytes);
	else if (0 == strcmp(mode, "packets"))
		SET_UI64_RESULT(result, opackets);
	else if (0 == strcmp(mode, "errors"))
		SET_UI64_RESULT(result, oerrors);
	else
		return SYSINFO_RET_FAIL;

	return SYSINFO_RET_OK;
}

int	NET_IF_TOTAL(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char		*if_name, *mode;
	zbx_uint64_t	tbytes, tpackets, terrors;

	if (2 < request->nparam)
		return SYSINFO_RET_FAIL;

	if_name = get_rparam(request, 0);
	mode = get_rparam(request, 1);

	if (SYSINFO_RET_OK != get_ifdata(if_name, NULL, NULL, NULL, NULL, NULL, NULL, NULL, &tbytes, &tpackets, &terrors, NULL))
		return SYSINFO_RET_FAIL;

	if (NULL == mode || '\0' == *mode || 0 == strcmp(mode, "bytes"))	/* default parameter */
		SET_UI64_RESULT(result, tbytes);
	else if (0 == strcmp(mode, "packets"))
		SET_UI64_RESULT(result, tpackets);
	else if (0 == strcmp(mode, "errors"))
		SET_UI64_RESULT(result, terrors);
	else
		return SYSINFO_RET_FAIL;

	return SYSINFO_RET_OK;
}

int	NET_IF_COLLISIONS(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char		*if_name;
	zbx_uint64_t	icollisions;

	if (1 < request->nparam)
		return SYSINFO_RET_FAIL;

	if_name = get_rparam(request, 0);

	if (SYSINFO_RET_OK != get_ifdata(if_name, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, &icollisions))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, icollisions);

	return SYSINFO_RET_OK;
}

int	NET_IF_DISCOVERY(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	int			ret = SYSINFO_RET_FAIL, i;
	struct zbx_json		j;
	struct if_nameindex	*interfaces;

	zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);

	zbx_json_addarray(&j, ZBX_PROTO_TAG_DATA);

	if (NULL != (interfaces = if_nameindex()))
	{
		i = 0;

		while (0 != interfaces[i].if_index)
		{
			zbx_json_addobject(&j, NULL);
			zbx_json_addstring(&j, "{#IFNAME}", interfaces[i].if_name, ZBX_JSON_TYPE_STRING);
			zbx_json_close(&j);
			i++;
		}

		ret = SYSINFO_RET_OK;
	}

	zbx_json_close(&j);

	SET_STR_RESULT(result, strdup(j.buffer));

	zbx_json_free(&j);

	return ret;
}
