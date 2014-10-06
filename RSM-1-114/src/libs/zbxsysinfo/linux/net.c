/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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
	zbx_uint64_t idrop;
	zbx_uint64_t obytes;
	zbx_uint64_t opackets;
	zbx_uint64_t oerr;
	zbx_uint64_t odrop;
	zbx_uint64_t colls;
}
net_stat_t;

static int	get_net_stat(const char *if_name, net_stat_t *result)
{
	int	ret = SYSINFO_RET_FAIL;
	char	line[MAX_STRING_LEN], name[MAX_STRING_LEN], *p;
	FILE	*f;

	assert(result);

	if (NULL != (f = fopen("/proc/net/dev", "r")))
	{
		while (NULL != fgets(line, sizeof(line), f))
		{
			if (NULL == (p = strstr(line, ":")))
				continue;

			*p = '\t';

			if (10 == sscanf(line, "%s\t" ZBX_FS_UI64 "\t" ZBX_FS_UI64 "\t"
					ZBX_FS_UI64 "\t" ZBX_FS_UI64 "\t%*s\t%*s\t%*s\t%*s\t"
					ZBX_FS_UI64 "\t" ZBX_FS_UI64 "\t" ZBX_FS_UI64 "\t"
					ZBX_FS_UI64 "\t%*s\t" ZBX_FS_UI64 "\t%*s\t%*s\n",
					name,
					&(result->ibytes),	/* bytes */
					&(result->ipackets),	/* packets */
					&(result->ierr),	/* errs */
					&(result->idrop),	/* drop */
					&(result->obytes),	/* bytes */
					&(result->opackets),	/* packets*/
					&(result->oerr),	/* errs */
					&(result->odrop),	/* drop */
					&(result->colls)))	/* icolls */
			{
				if (0 == strcmp(name, if_name))
				{
					ret = SYSINFO_RET_OK;
					break;
				}
			}
		}

		zbx_fclose(f);
	}

	if (ret != SYSINFO_RET_OK)
		memset(result, 0, sizeof(net_stat_t));

	return ret;
}

int	NET_IF_IN(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	net_stat_t	ns;
	char		if_name[MAX_STRING_LEN], mode[16];

	if (num_param(param) > 2)
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, if_name, sizeof(if_name)))
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 2, mode, sizeof(mode)))
		*mode = '\0';

	if (SYSINFO_RET_OK != get_net_stat(if_name, &ns))
		return SYSINFO_RET_FAIL;

	if ('\0' == *mode || 0 == strcmp(mode, "bytes"))	/* default parameter */
		SET_UI64_RESULT(result, ns.ibytes);
	else if (0 == strcmp(mode, "packets"))
		SET_UI64_RESULT(result, ns.ipackets);
	else if (0 == strcmp(mode, "errors"))
		SET_UI64_RESULT(result, ns.ierr);
	else if (0 == strcmp(mode, "dropped"))
		SET_UI64_RESULT(result, ns.idrop);
	else
		return SYSINFO_RET_FAIL;

	return SYSINFO_RET_OK;
}

int	NET_IF_OUT(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	net_stat_t	ns;
	char		if_name[MAX_STRING_LEN], mode[16];

	if (num_param(param) > 2)
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, if_name, sizeof(if_name)))
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 2, mode, sizeof(mode)))
		*mode = '\0';

	if (SYSINFO_RET_OK != get_net_stat(if_name, &ns))
		return SYSINFO_RET_FAIL;

	if ('\0' == *mode || 0 == strcmp(mode, "bytes"))	/* default parameter */
		SET_UI64_RESULT(result, ns.obytes);
	else if (0 == strcmp(mode, "packets"))
		SET_UI64_RESULT(result, ns.opackets);
	else if (0 == strcmp(mode, "errors"))
		SET_UI64_RESULT(result, ns.oerr);
	else if (0 == strcmp(mode, "dropped"))
		SET_UI64_RESULT(result, ns.odrop);
	else
		return SYSINFO_RET_FAIL;

	return SYSINFO_RET_OK;
}

int	NET_IF_TOTAL(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	net_stat_t	ns;
	char		if_name[MAX_STRING_LEN], mode[16];

	if (num_param(param) > 2)
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, if_name, sizeof(if_name)))
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 2, mode, sizeof(mode)))
		*mode = '\0';

	if (SYSINFO_RET_OK != get_net_stat(if_name, &ns))
		return SYSINFO_RET_FAIL;

	if ('\0' == *mode || 0 == strcmp(mode, "bytes"))	/* default parameter */
		SET_UI64_RESULT(result, ns.ibytes + ns.obytes);
	else if (0 == strcmp(mode, "packets"))
		SET_UI64_RESULT(result, ns.ipackets + ns.opackets);
	else if (0 == strcmp(mode, "errors"))
		SET_UI64_RESULT(result, ns.ierr + ns.oerr);
	else if (0 == strcmp(mode, "dropped"))
		SET_UI64_RESULT(result, ns.idrop + ns.odrop);
	else
		return SYSINFO_RET_FAIL;

	return SYSINFO_RET_OK;
}

int	NET_IF_COLLISIONS(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	net_stat_t	ns;
	char		if_name[MAX_STRING_LEN];

	if (num_param(param) > 1)
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, if_name, sizeof(if_name)))
		return SYSINFO_RET_FAIL;

	if (SYSINFO_RET_OK != get_net_stat(if_name, &ns))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, ns.colls);

	return SYSINFO_RET_OK;
}

int	NET_IF_DISCOVERY(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	int		ret = SYSINFO_RET_FAIL;
	char		line[MAX_STRING_LEN], *p;
	FILE		*f;
	struct zbx_json	j;

	zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);

	zbx_json_addarray(&j, ZBX_PROTO_TAG_DATA);

	if (NULL != (f = fopen("/proc/net/dev", "r")))
	{
		while (NULL != fgets(line, sizeof(line), f))
		{
			if (NULL == (p = strstr(line, ":")))
				continue;

			*p = '\0';

			/* trim left spaces */
			for (p = line; ' ' == *p && '\0' != *p; p++)
				;

			zbx_json_addobject(&j, NULL);
			zbx_json_addstring(&j, "{#IFNAME}", p, ZBX_JSON_TYPE_STRING);
			zbx_json_close(&j);
		}

		zbx_fclose(f);

		ret = SYSINFO_RET_OK;
	}

	zbx_json_close(&j);

	SET_STR_RESULT(result, strdup(j.buffer));

	zbx_json_free(&j);

	return ret;
}

int	NET_TCP_LISTEN(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	FILE		*f = NULL;
	char		tmp[MAX_STRING_LEN], pattern[64];
	unsigned short	port;
	zbx_uint64_t	listen = 0;
	int		ret = SYSINFO_RET_FAIL;

	if (num_param(param) > 1)
		return ret;

	if (0 != get_param(param, 1, tmp, sizeof(tmp)))
		return ret;

	if (SUCCEED != is_ushort(tmp, &port))
		return ret;

	if (NULL != (f = fopen("/proc/net/tcp", "r")))
	{
		zbx_snprintf(pattern, sizeof(pattern), "%04X 00000000:0000 0A", (unsigned int)port);

		while (NULL != fgets(tmp, sizeof(tmp), f))
		{
			if (NULL != strstr(tmp, pattern))
			{
				listen = 1;
				break;
			}
		}
		zbx_fclose(f);

		ret = SYSINFO_RET_OK;
	}

	if (0 == listen && NULL != (f = fopen("/proc/net/tcp6", "r")))
	{
		zbx_snprintf(pattern, sizeof(pattern), "%04X 00000000000000000000000000000000:0000 0A", (unsigned int)port);

		while (NULL != fgets(tmp, sizeof(tmp), f))
		{
			if (NULL != strstr(tmp, pattern))
			{
				listen = 1;
				break;
			}
		}
		zbx_fclose(f);

		ret = SYSINFO_RET_OK;
	}

	SET_UI64_RESULT(result, listen);

	return ret;
}

int	NET_UDP_LISTEN(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	FILE		*f = NULL;
	char		tmp[MAX_STRING_LEN], pattern[64];
	unsigned short	port;
	zbx_uint64_t	listen = 0;
	int		ret = SYSINFO_RET_FAIL;

	if (num_param(param) > 1)
		return ret;

	if (0 != get_param(param, 1, tmp, sizeof(tmp)))
		return ret;

	if (SUCCEED != is_ushort(tmp, &port))
		return ret;

	if (NULL != (f = fopen("/proc/net/udp", "r")))
	{
		zbx_snprintf(pattern, sizeof(pattern), "%04X 00000000:0000 07", (unsigned int)port);

		while (NULL != fgets(tmp, sizeof(tmp), f))
		{
			if (NULL != strstr(tmp, pattern))
			{
				listen = 1;
				break;
			}
		}
		zbx_fclose(f);

		ret = SYSINFO_RET_OK;
	}

	if (0 == listen && NULL != (f = fopen("/proc/net/udp6", "r")))
	{
		zbx_snprintf(pattern, sizeof(pattern), "%04X 00000000000000000000000000000000:0000 07", (unsigned int)port);

		while (NULL != fgets(tmp, sizeof(tmp), f))
		{
			if (NULL != strstr(tmp, pattern))
			{
				listen = 1;
				break;
			}
		}
		zbx_fclose(f);

		ret = SYSINFO_RET_OK;
	}

	SET_UI64_RESULT(result, listen);

	return ret;
}
