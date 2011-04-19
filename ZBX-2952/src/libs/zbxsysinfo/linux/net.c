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
	int ret = SYSINFO_RET_FAIL;
	char line[MAX_STRING_LEN];

	char name[MAX_STRING_LEN];
	zbx_uint64_t tmp = 0;

	FILE *f;
	char	*p;

	assert(result);

	if(NULL != (f = fopen("/proc/net/dev","r") ))
	{
		while(fgets(line,MAX_STRING_LEN,f) != NULL)
		{
			p = strstr(line,":");
			if(p) p[0]='\t';

			if(sscanf(line,"%s\t" ZBX_FS_UI64 "\t" ZBX_FS_UI64 "\t" ZBX_FS_UI64 "\t" ZBX_FS_UI64 "\t" ZBX_FS_UI64 "\t"
					ZBX_FS_UI64 "\t" ZBX_FS_UI64 "\t" ZBX_FS_UI64 "\t \
					" ZBX_FS_UI64 "\t" ZBX_FS_UI64 "\t" ZBX_FS_UI64 "\t" ZBX_FS_UI64 "\t" ZBX_FS_UI64 "\t"
					ZBX_FS_UI64 "\t" ZBX_FS_UI64 "\t" ZBX_FS_UI64 "\n",
				name,
				&(result->ibytes),	/* bytes */
				&(result->ipackets),	/* packets */
				&(result->ierr),	/* errs */
				&(result->idrop),	/* drop */
				&(tmp),			/* fifo */
				&(tmp),			/* frame */
				&(tmp),			/* compressed */
				&(tmp),			/* multicast */
				&(result->obytes),	/* bytes */
				&(result->opackets),	/* packets*/
				&(result->oerr),	/* errs */
				&(result->odrop),	/* drop */
				&(tmp),			/* fifo */
				&(result->colls),	/* icolls */
				&(tmp),			/* carrier */
				&(tmp)			/* compressed */
				) == 17)
			{
				if(strncmp(name, if_name, MAX_STRING_LEN) == 0)
				{
					ret = SYSINFO_RET_OK;
					break;
				}
			}
		}
		zbx_fclose(f);
	}

	if(ret != SYSINFO_RET_OK)
		memset(result, 0, sizeof(net_stat_t));

	return ret;
}

int	NET_IF_IN(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	net_stat_t	ns;

	char	if_name[MAX_STRING_LEN];
	char	mode[MAX_STRING_LEN];

	int ret = SYSINFO_RET_FAIL;

	if(num_param(param) > 2)
		return SYSINFO_RET_FAIL;

	if(get_param(param, 1, if_name, sizeof(if_name)) != 0)
		return SYSINFO_RET_FAIL;

	if(get_param(param, 2, mode, sizeof(mode)) != 0)
	{
		mode[0] = '\0';
	}
	if(mode[0] == '\0')
	{
		/* default parameter */
		zbx_snprintf(mode, sizeof(mode), "bytes");
	}

	ret = get_net_stat(if_name, &ns);

	if(ret == SYSINFO_RET_OK)
	{
		if(strncmp(mode, "bytes", MAX_STRING_LEN) == 0)
		{
			SET_UI64_RESULT(result, ns.ibytes);
		}
		else if(strncmp(mode, "packets", MAX_STRING_LEN) == 0)
		{
			SET_UI64_RESULT(result, ns.ipackets);
		}
		else if(strncmp(mode, "errors", MAX_STRING_LEN) == 0)
		{
			SET_UI64_RESULT(result, ns.ierr);
		}
		else if(strncmp(mode, "dropped", MAX_STRING_LEN) == 0)
		{
			SET_UI64_RESULT(result, ns.idrop);
		}
		else
			ret = SYSINFO_RET_FAIL;
	}

	return ret;
}

int	NET_IF_OUT(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	net_stat_t	ns;

	char	if_name[MAX_STRING_LEN];
	char	mode[MAX_STRING_LEN];

	int	ret = SYSINFO_RET_FAIL;

	if(num_param(param) > 2)
		return SYSINFO_RET_FAIL;

	if(get_param(param, 1, if_name, sizeof(if_name)) != 0)
		return SYSINFO_RET_FAIL;

	if(get_param(param, 2, mode, sizeof(mode)) != 0)
	{
		mode[0] = '\0';
	}
	if(mode[0] == '\0')
	{
		/* default parameter */
		zbx_snprintf(mode, sizeof(mode), "bytes");
	}

	ret = get_net_stat(if_name, &ns);

	if(ret == SYSINFO_RET_OK)
	{
		if(strncmp(mode, "bytes", MAX_STRING_LEN) == 0)
		{
			SET_UI64_RESULT(result, ns.obytes);
		}
		else if(strncmp(mode, "packets", MAX_STRING_LEN) == 0)
		{
			SET_UI64_RESULT(result, ns.opackets);
		}
		else if(strncmp(mode, "errors", MAX_STRING_LEN) == 0)
		{
			SET_UI64_RESULT(result, ns.oerr);
		}
		else if(strncmp(mode, "dropped", MAX_STRING_LEN) == 0)
		{
			SET_UI64_RESULT(result, ns.odrop);
		}
		else
			ret = SYSINFO_RET_FAIL;
	}

	return ret;
}

int	NET_IF_TOTAL(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	net_stat_t	ns;

	char	if_name[MAX_STRING_LEN];
	char	mode[MAX_STRING_LEN];

	int ret = SYSINFO_RET_FAIL;

	if(num_param(param) > 2)
		return SYSINFO_RET_FAIL;

	if(get_param(param, 1, if_name, sizeof(if_name)) != 0)
		return SYSINFO_RET_FAIL;

	if(get_param(param, 2, mode, sizeof(mode)) != 0)
	{
		mode[0] = '\0';
	}
	if(mode[0] == '\0')
	{
		/* default parameter */
		zbx_snprintf(mode, sizeof(mode), "bytes");
	}

	ret = get_net_stat(if_name, &ns);

	if(ret == SYSINFO_RET_OK)
	{
		if(strncmp(mode, "bytes", MAX_STRING_LEN) == 0)
		{
			SET_UI64_RESULT(result, ns.ibytes + ns.obytes);
		}
		else if(strncmp(mode, "packets", MAX_STRING_LEN) == 0)
		{
			SET_UI64_RESULT(result, ns.ipackets + ns.opackets);
		}
		else if(strncmp(mode, "errors", MAX_STRING_LEN) == 0)
		{
			SET_UI64_RESULT(result, ns.ierr + ns.oerr);
		}
		else if(strncmp(mode, "dropped", MAX_STRING_LEN) == 0)
		{
			SET_UI64_RESULT(result, ns.idrop + ns.odrop);
		}
		else
			ret = SYSINFO_RET_FAIL;
	}

	return ret;
}

int	NET_IF_COLLISIONS(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	net_stat_t	ns;

	char	if_name[MAX_STRING_LEN];
	int	ret = SYSINFO_RET_FAIL;

	if(num_param(param) > 1)
		return SYSINFO_RET_FAIL;

	if(get_param(param, 1, if_name, MAX_STRING_LEN) != 0)
		return SYSINFO_RET_FAIL;

	ret = get_net_stat(if_name, &ns);

	if(ret == SYSINFO_RET_OK)
		SET_UI64_RESULT(result, ns.colls);

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
