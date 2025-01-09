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

#include "zbxstr.h"

typedef struct
{
	zbx_uint64_t	nread;
	zbx_uint64_t	nwritten;
	zbx_uint64_t	reads;
	zbx_uint64_t	writes;
}
zbx_perfstat_t;

int	zbx_get_diskstat(const char *devname, zbx_uint64_t *dstat)
{
	ZBX_UNUSED(devname);
	ZBX_UNUSED(dstat);

	return FAIL;
}

static int	get_perfstat_io(const char *devname, zbx_perfstat_t *zp, char **error)
{
#if defined(HAVE_LIBPERFSTAT)
	int	err;

	if ('\0' != *devname)
	{
		perfstat_id_t	name;
		perfstat_disk_t	data;

		zbx_strscpy(name.name, devname);

		if (0 < (err = perfstat_disk(&name, &data, sizeof(data), 1)))
		{
			zp->nread = data.rblks * data.bsize;
			zp->nwritten = data.wblks * data.bsize;
			zp->reads = data.xrate;
			zp->writes = data.xfers - data.xrate;

			return SYSINFO_RET_OK;
		}
	}
	else
	{
		perfstat_disk_total_t	data;

		if (0 < (err = perfstat_disk_total(NULL, &data, sizeof(data), 1)))
		{
			zp->nread = data.rblks * 512;
			zp->nwritten = data.wblks * 512;
			zp->reads = data.xrate;
			zp->writes = data.xfers - data.xrate;

			return SYSINFO_RET_OK;
		}
	}

	if (0 == err)
		*error = zbx_strdup(NULL, "Cannot obtain system information.");
	else
		*error = zbx_dsprintf(NULL, "Cannot obtain system information: %s", zbx_strerror(errno));

	return SYSINFO_RET_FAIL;
#else
	*error = zbx_strdup(NULL, "Agent was compiled without support for Perfstat API.");

	return SYSINFO_RET_FAIL;
#endif
}

static int	vfs_dev_read_bytes(const char *devname, AGENT_RESULT *result)
{
	zbx_perfstat_t	zp;
	char		*error;

	if (SYSINFO_RET_OK != get_perfstat_io(devname, &zp, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	SET_UI64_RESULT(result, zp.nread);

	return SYSINFO_RET_OK;
}

static int	vfs_dev_read_operations(const char *devname, AGENT_RESULT *result)
{
	zbx_perfstat_t	zp;
	char		*error;

	if (SYSINFO_RET_OK != get_perfstat_io(devname, &zp, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	SET_UI64_RESULT(result, zp.reads);

	return SYSINFO_RET_OK;
}

static int	vfs_dev_write_bytes(const char *devname, AGENT_RESULT *result)
{
	zbx_perfstat_t	zp;
	char		*error;

	if (SYSINFO_RET_OK != get_perfstat_io(devname, &zp, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	SET_UI64_RESULT(result, zp.nwritten);

	return SYSINFO_RET_OK;
}

static int	vfs_dev_write_operations(const char *devname, AGENT_RESULT *result)
{
	zbx_perfstat_t	zp;
	char		*error;

	if (SYSINFO_RET_OK != get_perfstat_io(devname, &zp, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	SET_UI64_RESULT(result, zp.writes);

	return SYSINFO_RET_OK;
}

#define ZBX_DEV_PFX	"/dev/"

int	vfs_dev_read(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	const char	*devname, *type;
	int		ret;

	if (2 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	devname = get_rparam(request, 0);

	if (NULL == devname || 0 == strcmp("all", devname))
		devname = "";
	else if (0 == strncmp(ZBX_DEV_PFX, devname, ZBX_CONST_STRLEN(ZBX_DEV_PFX)))
		devname += ZBX_CONST_STRLEN(ZBX_DEV_PFX);

	type = get_rparam(request, 1);

	if (NULL == type || '\0' == *type || 0 == strcmp(type, "operations"))
		ret = vfs_dev_read_operations(devname, result);
	else if (0 == strcmp(type, "bytes"))
		ret = vfs_dev_read_bytes(devname, result);
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		return SYSINFO_RET_FAIL;
	}

	return ret;
}

int	vfs_dev_write(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	const char	*devname, *type;
	int		ret;

	if (2 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	devname = get_rparam(request, 0);

	if (NULL == devname || 0 == strcmp("all", devname))
		devname = "";
	else if (0 == strncmp(ZBX_DEV_PFX, devname, ZBX_CONST_STRLEN(ZBX_DEV_PFX)))
		devname += ZBX_CONST_STRLEN(ZBX_DEV_PFX);

	type = get_rparam(request, 1);

	if (NULL == type || '\0' == *type || 0 == strcmp(type, "operations"))
		ret = vfs_dev_write_operations(devname, result);
	else if (0 == strcmp(type, "bytes"))
		ret = vfs_dev_write_bytes(devname, result);
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		return SYSINFO_RET_FAIL;
	}

	return ret;
}
#undef ZBX_DEV_PFX
