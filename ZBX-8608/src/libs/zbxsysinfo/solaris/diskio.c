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
	zbx_uint64_t	nread;
	zbx_uint64_t	nwritten;
	zbx_uint64_t	reads;
	zbx_uint64_t	writes;
} zbx_kstat_t;

static zbx_kstat_t zbx_kstat;

int	get_diskstat(const char *devname, zbx_uint64_t *dstat)
{
	return FAIL;
}

static int	get_kstat_io(const char *name, zbx_kstat_t *zk)
{
	int		result = SYSINFO_RET_FAIL;
	kstat_ctl_t	*kc;
	kstat_t		*kt;
	kstat_io_t	kio;

	if (0 == (kc = kstat_open()))
		return result;

	if ('\0' != *name)
	{
		if (0 == (kt = kstat_lookup(kc, NULL, -1, (char *)name)))
			goto clean;

		if (KSTAT_TYPE_IO != kt->ks_type)
			goto clean;

		if (-1 != kstat_read(kc, kt, &kio))
		{
			zk->nread = kio.nread;
			zk->nwritten = kio.nwritten;
			zk->reads = kio.reads;
			zk->writes = kio.writes;

			result = SYSINFO_RET_OK;
		}
	}
	else
	{
		memset(zk, 0, sizeof(*zk));

		for (kt = kc->kc_chain; NULL != kt; kt = kt->ks_next)
		{
			if (KSTAT_TYPE_IO == kt->ks_type && 0 == strcmp("disk", kt->ks_class))
			{
				kstat_read(kc, kt, &kio);

				zk->nread += kio.nread;
				zk->nwritten += kio.nwritten;
				zk->reads += kio.reads;
				zk->writes += kio.writes;
			}
		}

		result = SYSINFO_RET_OK;
	}
clean:
	kstat_close(kc);

	return result;
}

static int	VFS_DEV_READ_BYTES(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	int	ret;

	if (SYSINFO_RET_OK == (ret = get_kstat_io(param, &zbx_kstat)))
		SET_UI64_RESULT(result, zbx_kstat.nread);

	return ret;
}

static int	VFS_DEV_READ_OPERATIONS(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	int	ret;

	if (SYSINFO_RET_OK == (ret = get_kstat_io(param, &zbx_kstat)))
		SET_UI64_RESULT(result, zbx_kstat.reads);

	return ret;
}

static int	VFS_DEV_WRITE_BYTES(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	int	ret;

	if (SYSINFO_RET_OK == (ret = get_kstat_io(param, &zbx_kstat)))
		SET_UI64_RESULT(result, zbx_kstat.nwritten);

	return ret;
}

static int	VFS_DEV_WRITE_OPERATIONS(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	int	ret;

	if (SYSINFO_RET_OK == (ret = get_kstat_io(param, &zbx_kstat)))
		SET_UI64_RESULT(result, zbx_kstat.writes);

	return ret;
}

static int	process_mode_function(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result, MODE_FUNCTION *fl)
{
	char	devname[MAX_STRING_LEN], mode[16];
	int	i;

	if (2 < num_param(param))
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, devname, sizeof(mode)) || 0 == strcmp("all", devname))
		*devname = '\0';

	if (0 != get_param(param, 2, mode, sizeof(mode)))
		*mode = '\0';

	if ('\0' == *mode)
	{
		/* default parameter */
		strscpy(mode, "bytes");
	}

	for (i = 0; NULL != fl[i].mode; i++)
	{
		if (0 == strcmp(mode, fl[i].mode))
			return (fl[i].function)(cmd, devname, flags, result);
	}

	return SYSINFO_RET_FAIL;
}

int	VFS_DEV_WRITE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	MODE_FUNCTION fl[] =
	{
		{"bytes", 	VFS_DEV_WRITE_BYTES},
		{"operations", 	VFS_DEV_WRITE_OPERATIONS},
		{NULL,		NULL}
	};

	return process_mode_function(cmd, param, flags, result, fl);
}

int	VFS_DEV_READ(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	MODE_FUNCTION fl[] =
	{
		{"bytes",	VFS_DEV_READ_BYTES},
		{"operations",	VFS_DEV_READ_OPERATIONS},
		{NULL,		NULL}
	};

	return process_mode_function(cmd, param, flags, result, fl);
}
