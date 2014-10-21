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
#include "log.h"

typedef struct
{
	zbx_uint64_t	nread;
	zbx_uint64_t	nwritten;
	zbx_uint64_t	reads;
	zbx_uint64_t	writes;
}
zbx_kstat_t;

int	get_diskstat(const char *devname, zbx_uint64_t *dstat)
{
	return FAIL;
}

static int	get_kstat_io(const char *name, zbx_kstat_t *zk, char **error)
{
	int		ret = SYSINFO_RET_FAIL;
	kstat_ctl_t	*kc;
	kstat_t		*kt;
	kstat_io_t	kio;

	if (NULL == (kc = kstat_open()))
	{
		*error = zbx_dsprintf(NULL, "Cannot open kernel statistics facility: %s", zbx_strerror(errno));
		return ret;
	}

	if ('\0' != *name)
	{
		if (NULL == (kt = kstat_lookup(kc, NULL, -1, (char *)name)))
		{
			*error = zbx_dsprintf(NULL, "Cannot look up in kernel statistics facility: %s",
					zbx_strerror(errno));
			goto clean;
		}

		if (KSTAT_TYPE_IO != kt->ks_type)
		{
			*error = zbx_strdup(NULL, "Information looked up in kernel statistics facility"
					" is of the wrong type.");
			goto clean;
		}

		if (-1 == kstat_read(kc, kt, &kio))
		{
			*error = zbx_dsprintf(NULL, "Cannot read from kernel statistics facility: %s",
					zbx_strerror(errno));
			goto clean;
		}

		zk->nread = kio.nread;
		zk->nwritten = kio.nwritten;
		zk->reads = kio.reads;
		zk->writes = kio.writes;
	}
	else
	{
		memset(zk, 0, sizeof(*zk));

		for (kt = kc->kc_chain; NULL != kt; kt = kt->ks_next)
		{
			if (KSTAT_TYPE_IO == kt->ks_type && 0 == strcmp("disk", kt->ks_class))
			{
				if (-1 == kstat_read(kc, kt, &kio))
				{
					*error = zbx_dsprintf(NULL, "Cannot read from kernel statistics facility: %s",
							zbx_strerror(errno));
					goto clean;
				}

				zk->nread += kio.nread;
				zk->nwritten += kio.nwritten;
				zk->reads += kio.reads;
				zk->writes += kio.writes;
			}
		}
	}

	ret = SYSINFO_RET_OK;
clean:
	kstat_close(kc);

	return ret;
}

static int	VFS_DEV_READ_BYTES(const char *devname, AGENT_RESULT *result)
{
	zbx_kstat_t	zk;
	char		*error;

	if (SYSINFO_RET_OK != get_kstat_io(devname, &zk, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	SET_UI64_RESULT(result, zk.nread);

	return SYSINFO_RET_OK;
}

static int	VFS_DEV_READ_OPERATIONS(const char *devname, AGENT_RESULT *result)
{
	zbx_kstat_t	zk;
	char		*error;

	if (SYSINFO_RET_OK != get_kstat_io(devname, &zk, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	SET_UI64_RESULT(result, zk.reads);

	return SYSINFO_RET_OK;
}

static int	VFS_DEV_WRITE_BYTES(const char *devname, AGENT_RESULT *result)
{
	zbx_kstat_t	zk;
	char		*error;

	if (SYSINFO_RET_OK != get_kstat_io(devname, &zk, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	SET_UI64_RESULT(result, zk.nwritten);

	return SYSINFO_RET_OK;
}

static int	VFS_DEV_WRITE_OPERATIONS(const char *devname, AGENT_RESULT *result)
{
	zbx_kstat_t	zk;
	char		*error;

	if (SYSINFO_RET_OK != get_kstat_io(devname, &zk, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	SET_UI64_RESULT(result, zk.writes);

	return SYSINFO_RET_OK;
}

static int	process_mode_function(AGENT_REQUEST *request, AGENT_RESULT *result, const MODE_FUNCTION *fl)
{
	const char	*devname, *mode;
	int		i;

	if (2 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	devname = get_rparam(request, 0);

	if (NULL == devname || 0 == strcmp("all", devname))
		devname = "";

	mode = get_rparam(request, 1);

	if (NULL == mode || '\0' == *mode)
		mode = "bytes";

	for (i = 0; NULL != fl[i].mode; i++)
	{
		if (0 == strcmp(mode, fl[i].mode))
			return (fl[i].function)(devname, result);
	}

	SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));

	return SYSINFO_RET_FAIL;
}

int	VFS_DEV_READ(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	const MODE_FUNCTION	fl[] =
	{
		{"bytes",	VFS_DEV_READ_BYTES},
		{"operations",	VFS_DEV_READ_OPERATIONS},
		{NULL,		NULL}
	};

	return process_mode_function(request, result, fl);
}

int	VFS_DEV_WRITE(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	const MODE_FUNCTION	fl[] =
	{
		{"bytes", 	VFS_DEV_WRITE_BYTES},
		{"operations", 	VFS_DEV_WRITE_OPERATIONS},
		{NULL,		NULL}
	};

	return process_mode_function(request, result, fl);
}
