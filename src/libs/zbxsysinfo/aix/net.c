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

#include "zbxjson.h"
#include "zbxstr.h"

#include "zbxregexp.h"
#include <sys/ioctl.h>
#include <net/if.h>
#include <sys/socket.h>
#include <sys/kinfo.h>
#include <sys/ndd_var.h>

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

static int	get_net_stat(const char *if_name, net_stat_t *ns, char **error)
{
#if defined(HAVE_LIBPERFSTAT)
	perfstat_id_t		ps_id;
	perfstat_netinterface_t	ps_netif;

	if (NULL == if_name || '\0' == *if_name)
	{
		*error = zbx_strdup(NULL, "Network interface name cannot be empty.");
		return SYSINFO_RET_FAIL;
	}

	zbx_strscpy(ps_id.name, if_name);

	if (-1 == perfstat_netinterface(&ps_id, &ps_netif, sizeof(ps_netif), 1))
	{
		*error = zbx_dsprintf(NULL, "Cannot obtain system information: %s", zbx_strerror(errno));
		return SYSINFO_RET_FAIL;
	}

	ns->ibytes = (zbx_uint64_t)ps_netif.ibytes;
	ns->ipackets = (zbx_uint64_t)ps_netif.ipackets;
	ns->ierr = (zbx_uint64_t)ps_netif.ierrors;

	ns->obytes = (zbx_uint64_t)ps_netif.obytes;
	ns->opackets = (zbx_uint64_t)ps_netif.opackets;
	ns->oerr = (zbx_uint64_t)ps_netif.oerrors;

	ns->colls = (zbx_uint64_t)ps_netif.collisions;

	return SYSINFO_RET_OK;
#else
	SET_MSG_RESULT(result, zbx_strdup(NULL, "Agent was compiled without support for Perfstat API."));

	return SYSINFO_RET_FAIL;
#endif
}

int	net_if_in(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char		*if_name, *mode, *error;
	net_stat_t	ns;

	if (2 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	if_name = get_rparam(request, 0);
	mode = get_rparam(request, 1);

	if (SYSINFO_RET_FAIL == get_net_stat(if_name, &ns, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	if (NULL == mode || '\0' == *mode || 0 == strcmp(mode, "bytes"))
		SET_UI64_RESULT(result, ns.ibytes);
	else if (0 == strcmp(mode, "packets"))
		SET_UI64_RESULT(result, ns.ipackets);
	else if (0 == strcmp(mode, "errors"))
		SET_UI64_RESULT(result, ns.ierr);
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
	net_stat_t	ns;

	if (2 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	if_name = get_rparam(request, 0);
	mode = get_rparam(request, 1);

	if (SYSINFO_RET_FAIL == get_net_stat(if_name, &ns, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	if (NULL == mode || '\0' == *mode || 0 == strcmp(mode, "bytes"))
		SET_UI64_RESULT(result, ns.obytes);
	else if (0 == strcmp(mode, "packets"))
		SET_UI64_RESULT(result, ns.opackets);
	else if (0 == strcmp(mode, "errors"))
		SET_UI64_RESULT(result, ns.oerr);
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
	net_stat_t	ns;

	if (2 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	if_name = get_rparam(request, 0);
	mode = get_rparam(request, 1);

	if (SYSINFO_RET_FAIL == get_net_stat(if_name, &ns, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	if (NULL == mode || '\0' == *mode || 0 == strcmp(mode, "bytes"))
		SET_UI64_RESULT(result, ns.ibytes + ns.obytes);
	else if (0 == strcmp(mode, "packets"))
		SET_UI64_RESULT(result, ns.ipackets + ns.opackets);
	else if (0 == strcmp(mode, "errors"))
		SET_UI64_RESULT(result, ns.ierr + ns.oerr);
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
	net_stat_t	ns;

	if (1 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	if_name = get_rparam(request, 0);

	if (SYSINFO_RET_FAIL == get_net_stat(if_name, &ns, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	SET_UI64_RESULT(result, ns.colls);

	return SYSINFO_RET_OK;
}

int	net_if_discovery(AGENT_REQUEST *request, AGENT_RESULT *result)
{
#if defined(HAVE_LIBPERFSTAT)
	int			rc, ret = SYSINFO_RET_FAIL;
	perfstat_id_t		ps_id;
	perfstat_netinterface_t	*ps_netif = NULL;
	struct zbx_json		j;

	ZBX_UNUSED(request);

	/* check how many perfstat_netinterface_t structures are available */
	if (-1 == (rc = perfstat_netinterface(NULL, NULL, sizeof(perfstat_netinterface_t), 0)))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain system information: %s", zbx_strerror(errno)));
		return SYSINFO_RET_FAIL;
	}

	zbx_json_initarray(&j, ZBX_JSON_STAT_BUF_LEN);

	if (0 == rc)	/* no network interfaces found */
	{
		ret = SYSINFO_RET_OK;
		goto end;
	}

	ps_netif = zbx_malloc(ps_netif, rc * sizeof(perfstat_netinterface_t));

	/* set name to first interface */
	zbx_strscpy(ps_id.name, FIRST_NETINTERFACE);	/* pseudo-name for the first network interface */

	/* ask to get all the structures available in one call */
	/* return code is number of structures returned */
	if (-1 != (rc = perfstat_netinterface(&ps_id, ps_netif, sizeof(perfstat_netinterface_t), rc)))
		ret = SYSINFO_RET_OK;

	/* collecting of the information for each of the interfaces */
	for (int i = 0; i < rc; i++)
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
	ZBX_UNUSED(request);

	SET_MSG_RESULT(result, zbx_strdup(NULL, "Agent was compiled without support for Perfstat API."));

	return SYSINFO_RET_FAIL;
#endif
}

static int	get_mac_address_aix(const char *interface_name, char *mac_str, size_t mac_str_len)
{
	int		size, i;
	struct kinfo_ndd	*nddp;
	char		*buf = NULL;
	int		found = FAIL;
	unsigned char	*addr_ptr;

	if (0 == (size = getkerninfo(KINFO_NDD, 0, 0, 0)))
		return FAIL;

	if (0 > size)
		return FAIL;

	if (NULL == (buf = (char *)zbx_malloc(NULL, size)))
		return FAIL;

	nddp = (struct kinfo_ndd *)buf;

	if (0 > getkerninfo(KINFO_NDD, nddp, &size, 0))
	{
		zbx_free(buf);
		return FAIL;
	}

	for (i = 0; i < size / sizeof(struct kinfo_ndd); i++)
	{
		if (0 == strcmp(nddp->ndd_name, interface_name) || 0 == strcmp(nddp->ndd_alias, interface_name))
		{
			addr_ptr = (unsigned char *)nddp->ndd_addr;

			zbx_snprintf(mac_str, mac_str_len, "%02x:%02x:%02x:%02x:%02x:%02x",
						addr_ptr[0], addr_ptr[1], addr_ptr[2],
						addr_ptr[3], addr_ptr[4], addr_ptr[5]);

			found = SUCCEED;
			break;
		}
		nddp++;
	}

	zbx_free(buf);

	return found;
}

int	net_if_get(AGENT_REQUEST *request, AGENT_RESULT *result)
{
#if defined(HAVE_LIBPERFSTAT)
	int			rc, ret = SYSINFO_RET_FAIL;
	perfstat_id_t		ps_id;
	perfstat_netinterface_t	*ps_netif = NULL;
	struct zbx_json		jcfg, jval, j;
	zbx_regexp_t		*rxp = NULL;
	char			*error = NULL;
	char			*pattern;
	int			sock = -1;
	struct ifreq		ifr;
	char			mac_addr_str[32];

	if (1 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	if (-1 == (rc = perfstat_netinterface(NULL, NULL, sizeof(perfstat_netinterface_t), 0)))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain system information: %s", zbx_strerror(errno)));
		return SYSINFO_RET_FAIL;
	}

	if (1 == request->nparam)
	{
		pattern = get_rparam(request, 0);

		if (FAIL == zbx_regexp_compile(pattern, &rxp, &error))
		{
			zbx_set_json_strerror("invalid regular expression in JSON path: %s", error);
			zbx_free(error);
			return SYSINFO_RET_FAIL;
		}
	}

	ps_netif = zbx_malloc(ps_netif, rc * sizeof(perfstat_netinterface_t));
	zbx_strscpy(ps_id.name, FIRST_NETINTERFACE);

	if (-1 != (rc = perfstat_netinterface(&ps_id, ps_netif, sizeof(perfstat_netinterface_t), rc)))
		ret = SYSINFO_RET_OK;

	if (0 > (sock = socket(AF_INET, SOCK_DGRAM, 0)))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain system information: %s", zbx_strerror(errno)));
		return SYSINFO_RET_FAIL;
	}

	zbx_json_initarray(&jval, ZBX_JSON_STAT_BUF_LEN);
	zbx_json_initarray(&jcfg, ZBX_JSON_STAT_BUF_LEN);

	for (int i = 0; i < rc; i++)
	{
		if (NULL != rxp && 0 != zbx_regexp_match_precompiled(ps_netif[i].name, rxp))
			continue;

		zbx_json_addobject(&jcfg, NULL);
		zbx_json_addobject(&jval, NULL);
		zbx_json_addstring(&jcfg, "name", ps_netif[i].name, ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&jcfg, "ifalias", ps_netif[i].description, ZBX_JSON_TYPE_STRING);

		zbx_json_addstring(&jval, "name", ps_netif[i].name, ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&jval, "ifalias", ps_netif[i].description, ZBX_JSON_TYPE_STRING);
		zbx_json_adduint64(&jval, "type", ps_netif[i].type);
		zbx_json_addobject(&jval, "in");
		zbx_json_adduint64(&jval, "bytes", ps_netif[i].ibytes);
		zbx_json_adduint64(&jval, "packets", ps_netif[i].ipackets);
		zbx_json_adduint64(&jval, "errors", ps_netif[i].ierrors);
		zbx_json_close(&jval);
		zbx_json_addobject(&jval, "out");
		zbx_json_adduint64(&jval, "bytes", ps_netif[i].obytes);
		zbx_json_adduint64(&jval, "packets", ps_netif[i].opackets);
		zbx_json_adduint64(&jval, "errors", ps_netif[i].oerrors);
		zbx_json_adduint64(&jval, "collisions", ps_netif[i].collisions);
		zbx_json_close(&jval);

		if (sock >= 0)
		{
			memset(&ifr, 0, sizeof(ifr));
			zbx_strlcpy(ifr.ifr_name, ps_netif[i].name, IFNAMSIZ);

			if (ioctl(sock, SIOCGIFFLAGS, &ifr) < 0)
			{
				zbx_json_addstring(&jcfg, "administrative_state", "unknown", ZBX_JSON_TYPE_STRING);
				zbx_json_addstring(&jcfg, "operational_state", "unknown", ZBX_JSON_TYPE_STRING);
			}
			else
			{
				zbx_json_addstring(&jcfg, "administrative_state",
					(ifr.ifr_flags & IFF_UP) ? "up" : "down", ZBX_JSON_TYPE_STRING);

				zbx_json_addstring(&jcfg, "operational_state",
					(ifr.ifr_flags & IFF_RUNNING) ? "up" : "down", ZBX_JSON_TYPE_STRING);
			}
		}
		else
		{
			zbx_json_addstring(&jcfg, "administrative_state", "unknown", ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&jcfg, "operational_state", "unknown", ZBX_JSON_TYPE_STRING);
		}

		/* Get MAC Address */
		if (SUCCEED == get_mac_address_aix(ps_netif[i].name, mac_addr_str, sizeof(mac_addr_str)))
		{
			zbx_json_addstring(&jval, "mac", mac_addr_str, ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&jcfg, "mac", mac_addr_str, ZBX_JSON_TYPE_STRING);
		}

		zbx_json_close(&jval);
		zbx_json_close(&jcfg);
	}

	if (sock >= 0)
		close(sock);

	if (NULL != rxp)
		zbx_regexp_free(rxp);
	zbx_free(ps_netif);
end:
	zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);
	zbx_json_addraw(&j, "config", jcfg.buffer);
	zbx_json_addraw(&j, "values", jval.buffer);
	zbx_json_close(&j);

	SET_STR_RESULT(result, strdup(j.buffer));
	zbx_json_free(&jval);
	zbx_json_free(&jcfg);
	zbx_json_free(&j);

	return ret;
#else
	ZBX_UNUSED(request);

	SET_MSG_RESULT(result, zbx_strdup(NULL, "Agent was compiled without support for Perfstat API."));

	return SYSINFO_RET_FAIL;
#endif
}
