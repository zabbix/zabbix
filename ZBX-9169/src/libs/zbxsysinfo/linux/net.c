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
#include "log.h"

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

static int	get_net_stat(const char *if_name, net_stat_t *result, char **error)
{
	int	ret = SYSINFO_RET_FAIL;
	char	line[MAX_STRING_LEN], name[MAX_STRING_LEN], *p;
	FILE	*f;

	if (NULL == if_name || '\0' == *if_name)
	{
		*error = zbx_strdup(NULL, "Network interface name cannot be empty.");
		return SYSINFO_RET_FAIL;
	}

	if (NULL == (f = fopen("/proc/net/dev", "r")))
	{
		*error = zbx_dsprintf(NULL, "Cannot open /proc/net/dev: %s", zbx_strerror(errno));
		return SYSINFO_RET_FAIL;
	}

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
				&result->ibytes,	/* bytes */
				&result->ipackets,	/* packets */
				&result->ierr,		/* errs */
				&result->idrop,		/* drop */
				&result->obytes,	/* bytes */
				&result->opackets,	/* packets */
				&result->oerr,		/* errs */
				&result->odrop,		/* drop */
				&result->colls))	/* icolls */
		{
			if (0 == strcmp(name, if_name))
			{
				ret = SYSINFO_RET_OK;
				break;
			}
		}
	}

	zbx_fclose(f);

	if (SYSINFO_RET_FAIL == ret)
	{
		*error = zbx_strdup(NULL, "Cannot find information for this network interface in /proc/net/dev.");
		return SYSINFO_RET_FAIL;
	}

	return SYSINFO_RET_OK;
}

/******************************************************************************
 *                                                                            *
 * Function: proc_read_file                                                   *
 *                                                                            *
 * Purpose: reads whole file into a buffer in a single read operation         *
 *                                                                            *
 * Parameters: filename     - [IN] the file to read                           *
 *             buffer       - [IN/OUT] the output buffer                      *
 *             buffer_alloc - [IN/OUT] the output buffer size                 *
 *                                                                            *
 * Return value: -1 error occurred during reading                             *
 *                0 empty file (shouldn't happen)                             *
 *               >0 the number of bytes read                                  *
 *                                                                            *
 * Comments: When reading line by line the file might be changed between      *
 *           reads resulting in a possible information loss. To avoid it      *
 *           try reading/expanding the buffer until it fits the whole file.   *
 *                                                                            *
 ******************************************************************************/
static int	proc_read_file(const char *filename, char **buffer, int *buffer_alloc)
{
	int	n, fd, ret = -1;
	size_t	offset = 0;

	if (-1 == (fd = open(filename, O_RDONLY)))
		return -1;

	while (0 != (n = read(fd, *buffer + offset, *buffer_alloc - offset)))
	{
		if (-1 == n)
			goto out;

		offset += n;

		if (offset == *buffer_alloc)
		{
			*buffer_alloc *= 2;
			*buffer = zbx_realloc(*buffer, *buffer_alloc);
		}
	}

	ret = offset;
out:
	close(fd);

	return ret;
}

int	NET_IF_IN(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	net_stat_t	ns;
	char		*if_name, *mode, *error;

	if (2 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	if_name = get_rparam(request, 0);
	mode = get_rparam(request, 1);

	if (SYSINFO_RET_OK != get_net_stat(if_name, &ns, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	if (NULL == mode || '\0' == *mode || 0 == strcmp(mode, "bytes"))	/* default parameter */
		SET_UI64_RESULT(result, ns.ibytes);
	else if (0 == strcmp(mode, "packets"))
		SET_UI64_RESULT(result, ns.ipackets);
	else if (0 == strcmp(mode, "errors"))
		SET_UI64_RESULT(result, ns.ierr);
	else if (0 == strcmp(mode, "dropped"))
		SET_UI64_RESULT(result, ns.idrop);
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		return SYSINFO_RET_FAIL;
	}

	return SYSINFO_RET_OK;
}

int	NET_IF_OUT(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	net_stat_t	ns;
	char		*if_name, *mode, *error;

	if (2 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	if_name = get_rparam(request, 0);
	mode = get_rparam(request, 1);

	if (SYSINFO_RET_OK != get_net_stat(if_name, &ns, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	if (NULL == mode || '\0' == *mode || 0 == strcmp(mode, "bytes"))	/* default parameter */
		SET_UI64_RESULT(result, ns.obytes);
	else if (0 == strcmp(mode, "packets"))
		SET_UI64_RESULT(result, ns.opackets);
	else if (0 == strcmp(mode, "errors"))
		SET_UI64_RESULT(result, ns.oerr);
	else if (0 == strcmp(mode, "dropped"))
		SET_UI64_RESULT(result, ns.odrop);
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		return SYSINFO_RET_FAIL;
	}

	return SYSINFO_RET_OK;
}

int	NET_IF_TOTAL(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	net_stat_t	ns;
	char		*if_name, *mode, *error;

	if (2 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	if_name = get_rparam(request, 0);
	mode = get_rparam(request, 1);

	if (SYSINFO_RET_OK != get_net_stat(if_name, &ns, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	if (NULL == mode || '\0' == *mode || 0 == strcmp(mode, "bytes"))	/* default parameter */
		SET_UI64_RESULT(result, ns.ibytes + ns.obytes);
	else if (0 == strcmp(mode, "packets"))
		SET_UI64_RESULT(result, ns.ipackets + ns.opackets);
	else if (0 == strcmp(mode, "errors"))
		SET_UI64_RESULT(result, ns.ierr + ns.oerr);
	else if (0 == strcmp(mode, "dropped"))
		SET_UI64_RESULT(result, ns.idrop + ns.odrop);
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		return SYSINFO_RET_FAIL;
	}

	return SYSINFO_RET_OK;
}

int	NET_IF_COLLISIONS(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	net_stat_t	ns;
	char		*if_name, *error;

	if (1 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	if_name = get_rparam(request, 0);

	if (SYSINFO_RET_OK != get_net_stat(if_name, &ns, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	SET_UI64_RESULT(result, ns.colls);

	return SYSINFO_RET_OK;
}

int	NET_IF_DISCOVERY(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char		line[MAX_STRING_LEN], *p;
	FILE		*f;
	struct zbx_json	j;

	if (NULL == (f = fopen("/proc/net/dev", "r")))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot open /proc/net/dev: %s", zbx_strerror(errno)));
		return SYSINFO_RET_FAIL;
	}

	zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);

	zbx_json_addarray(&j, ZBX_PROTO_TAG_DATA);

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

	zbx_json_close(&j);

	SET_STR_RESULT(result, strdup(j.buffer));

	zbx_json_free(&j);

	return SYSINFO_RET_OK;
}

int	NET_TCP_LISTEN(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char		pattern[64], *port_str, *buffer = NULL;
	unsigned short	port;
	zbx_uint64_t	listen = 0;
	int		ret = SYSINFO_RET_FAIL, n, buffer_alloc = 64 * ZBX_KIBIBYTE;

	if (1 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	port_str = get_rparam(request, 0);

	if (NULL == port_str || SUCCEED != is_ushort(port_str, &port))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		return SYSINFO_RET_FAIL;
	}

	buffer = zbx_malloc(NULL, buffer_alloc);

	if (0 < (n = proc_read_file("/proc/net/tcp", &buffer, &buffer_alloc)))
	{
		ret = SYSINFO_RET_OK;

		zbx_snprintf(pattern, sizeof(pattern), "%04X 00000000:0000 0A", (unsigned int)port);

		buffer[n] = '\0';

		if (NULL != strstr(buffer, pattern))
		{
			listen = 1;
			goto out;
		}
	}

	if (0 < (n = proc_read_file("/proc/net/tcp6", &buffer, &buffer_alloc)))
	{
		ret = SYSINFO_RET_OK;

		zbx_snprintf(pattern, sizeof(pattern), "%04X 00000000000000000000000000000000:0000 0A",
				(unsigned int)port);

		buffer[n] = '\0';

		if (NULL != strstr(buffer, pattern))
			listen = 1;
	}
out:
	zbx_free(buffer);

	SET_UI64_RESULT(result, listen);

	return ret;
}

int	NET_UDP_LISTEN(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char		pattern[64], *port_str, *buffer = NULL;
	unsigned short	port;
	zbx_uint64_t	listen = 0;
	int		ret = SYSINFO_RET_FAIL, n, buffer_alloc = 64 * ZBX_KIBIBYTE;

	if (1 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	port_str = get_rparam(request, 0);

	if (NULL == port_str || SUCCEED != is_ushort(port_str, &port))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		return SYSINFO_RET_FAIL;
	}

	buffer = zbx_malloc(NULL, buffer_alloc);

	if (0 < (n = proc_read_file("/proc/net/udp", &buffer, &buffer_alloc)))
	{
		ret = SYSINFO_RET_OK;

		zbx_snprintf(pattern, sizeof(pattern), "%04X 00000000:0000 07", (unsigned int)port);

		buffer[n] = '\0';

		if (NULL != strstr(buffer, pattern))
		{
			listen = 1;
			goto out;
		}
	}

	if (0 < (n = proc_read_file("/proc/net/udp6", &buffer, &buffer_alloc)))
	{
		ret = SYSINFO_RET_OK;

		zbx_snprintf(pattern, sizeof(pattern), "%04X 00000000000000000000000000000000:0000 07",
				(unsigned int)port);

		buffer[n] = '\0';

		if (NULL != strstr(buffer, pattern))
			listen = 1;
	}
out:
	zbx_free(buffer);

	SET_UI64_RESULT(result, listen);

	return ret;
}
