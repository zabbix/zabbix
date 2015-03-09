/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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

typedef struct
{
	zbx_uint64_t ibytes;
	zbx_uint64_t ipackets;
	zbx_uint64_t ierr;
	zbx_uint64_t obytes;
	zbx_uint64_t opackets;
	zbx_uint64_t oerr;
	zbx_uint64_t colls;
}
net_stat_t;

static int	get_net_stat(const char *if_name, net_stat_t *ns)
{
#if defined(HAVE_LIBPERFSTAT)
	perfstat_id_t		ps_id;
	perfstat_netinterface_t	ps_netif;
#endif

	assert(ns);

#if defined(HAVE_LIBPERFSTAT)
	strscpy(ps_id.name, if_name);

	if (-1 == perfstat_netinterface(&ps_id, &ps_netif, sizeof(ps_netif), 1))
		return SYSINFO_RET_FAIL;

	ns->ibytes = (zbx_uint64_t)ps_netif.ibytes;
	ns->ipackets = (zbx_uint64_t)ps_netif.ipackets;
	ns->ierr = (zbx_uint64_t)ps_netif.ierrors;

	ns->obytes = (zbx_uint64_t)ps_netif.obytes;
	ns->opackets = (zbx_uint64_t)ps_netif.opackets;
	ns->oerr = (zbx_uint64_t)ps_netif.oerrors;

	ns->colls = (zbx_uint64_t)ps_netif.collisions;

	return SYSINFO_RET_OK;
#else
	return SYSINFO_RET_FAIL;
#endif
}

int	NET_IF_IN(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	char		if_name[MAX_STRING_LEN], mode[MAX_STRING_LEN];
	net_stat_t	ns;
	int		ret = SYSINFO_RET_OK;

	if (num_param(param) > 2)
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, if_name, sizeof(if_name)) || *if_name == '\0')
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 2, mode, sizeof(mode)))
		*mode = '\0';

	/* default parameter */
	if ('\0' == *mode)
		zbx_snprintf(mode, sizeof(mode), "bytes");

	if (SYSINFO_RET_OK == get_net_stat(if_name, &ns))
	{
		if (0 == strcmp(mode, "bytes"))
		{
			SET_UI64_RESULT(result, ns.ibytes);
		}
		else if (0 == strcmp(mode, "packets"))
		{
			SET_UI64_RESULT(result, ns.ipackets);
		}
		else if (0 == strcmp(mode, "errors"))
		{
			SET_UI64_RESULT(result, ns.ierr);
		}
		else
			ret = SYSINFO_RET_FAIL;
	}
	else
		ret = SYSINFO_RET_FAIL;

	return ret;
}

int	NET_IF_OUT(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	char		if_name[MAX_STRING_LEN], mode[MAX_STRING_LEN];
	net_stat_t	ns;
	int		ret = SYSINFO_RET_OK;

	if (num_param(param) > 2)
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, if_name, sizeof(if_name)) || *if_name == '\0')
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 2, mode, sizeof(mode)))
		*mode = '\0';

	/* default parameter */
	if ('\0' == *mode)
		zbx_snprintf(mode, sizeof(mode), "bytes");

	if (SYSINFO_RET_OK == get_net_stat(if_name, &ns))
	{
		if (0 == strcmp(mode, "bytes"))
		{
			SET_UI64_RESULT(result, ns.obytes);
		}
		else if (0 == strcmp(mode, "packets"))
		{
			SET_UI64_RESULT(result, ns.opackets);
		}
		else if (0 == strcmp(mode, "errors"))
		{
			SET_UI64_RESULT(result, ns.oerr);
		}
		else
			ret = SYSINFO_RET_FAIL;
	}
	else
		ret = SYSINFO_RET_FAIL;

	return ret;
}

int	NET_IF_TOTAL(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	char		if_name[MAX_STRING_LEN], mode[MAX_STRING_LEN];
	net_stat_t	ns;
	int		ret = SYSINFO_RET_OK;

	if (num_param(param) > 2)
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, if_name, sizeof(if_name)) || *if_name == '\0')
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 2, mode, sizeof(mode)))
		*mode = '\0';

	/* default parameter */
	if ('\0' == *mode)
		zbx_snprintf(mode, sizeof(mode), "bytes");

	if (SYSINFO_RET_OK == get_net_stat(if_name, &ns))
	{
		if (0 == strcmp(mode, "bytes"))
		{
			SET_UI64_RESULT(result, ns.ibytes + ns.obytes);
		}
		else if (0 == strcmp(mode, "packets"))
		{
			SET_UI64_RESULT(result, ns.ipackets + ns.opackets);
		}
		else if (0 == strcmp(mode, "errors"))
		{
			SET_UI64_RESULT(result, ns.ierr + ns.oerr);
		}
		else
			ret = SYSINFO_RET_FAIL;
	}
	else
		ret = SYSINFO_RET_FAIL;

	return ret;
}

int	NET_IF_COLLISIONS(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	char		if_name[MAX_STRING_LEN];
	net_stat_t	ns;
	int		ret = SYSINFO_RET_OK;

	if (num_param(param) > 1)
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, if_name, sizeof(if_name)))
		return SYSINFO_RET_FAIL;

	if (SYSINFO_RET_OK == get_net_stat(if_name, &ns))
	{
		SET_UI64_RESULT(result, ns.colls);
	}
	else
		ret = SYSINFO_RET_FAIL;

	return ret;
}

int	NET_IF_DISCOVERY(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#if defined(HAVE_LIBPERFSTAT)
	int			rc, i, ret = SYSINFO_RET_FAIL;
	perfstat_id_t		ps_id;
	perfstat_netinterface_t	*ps_netif = NULL;
	struct zbx_json		j;

	/* check how many perfstat_netinterface_t structures are available */
	if (-1 == (rc = perfstat_netinterface(NULL, NULL, sizeof(perfstat_netinterface_t), 0)))
		return ret;

	zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);

	zbx_json_addarray(&j, ZBX_PROTO_TAG_DATA);

	if (0 == rc)	/* no network interfaces found */
	{
		ret = SYSINFO_RET_OK;
		goto end;
	}

	ps_netif = zbx_malloc(ps_netif, rc * sizeof(perfstat_netinterface_t));

	/* set name to first interface */
	strscpy(ps_id.name, FIRST_NETINTERFACE);	/* pseudo-name for the first network interface */

	/* ask to get all the structures available in one call */
	/* return code is number of structures returned */
	if (-1 != (rc = perfstat_netinterface(&ps_id, ps_netif, sizeof(perfstat_netinterface_t), rc)))
		ret = SYSINFO_RET_OK;

	/* collecting of the information for each of the interfaces */
	for (i = 0; i < rc; i++)
	{
		zbx_json_addobject(&j, NULL);
		zbx_json_addstring(&j, "{#IFNAME}", ps_netif[i].name, ZBX_JSON_TYPE_STRING);
		zbx_json_close(&j);
	}

	zbx_free(ps_netif);
end:
	zbx_json_close(&j);

	SET_STR_RESULT(result, strdup(j.buffer));

	zbx_json_free(&j);

	return ret;
#else
	return SYSINFO_RET_FAIL;
#endif
}
