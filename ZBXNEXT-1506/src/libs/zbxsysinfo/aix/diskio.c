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

typedef struct
{
	zbx_uint64_t	nread;
	zbx_uint64_t	nwritten;
	zbx_uint64_t	nroperations;
	zbx_uint64_t	nwoperations;
}
disk_stat_t;

static disk_stat_t	disk_stat;

int	get_diskstat(const char *devname, zbx_uint64_t *dstat)
{
	return FAIL;
}

static int	get_diskstat_io(const char *devname, disk_stat_t *zk)
{
	int	i, disk_num;

	assert(zk);

#if defined(HAVE_LIBPERFSTAT)

	disk_num = perfstat_disk(NULL, NULL, sizeof(perfstat_disk_t), 0);

	/* check that the system actually has disks */
	if (disk_num == -1)
		return SYSINFO_RET_FAIL;

	/* check whether we want all the devices or a particular one */
	if ('\0' != *devname)
	{
		perfstat_disk_t	*data = NULL;
		perfstat_id_t	name;

		strscpy(name.name, devname);

		data = zbx_malloc(data, sizeof(perfstat_disk_t));

		if (0 < perfstat_disk(&name, data, sizeof(perfstat_disk_t), 1))
		{
			zk->nread = data[0].rblks * data[0].bsize;
			zk->nwritten = data[0].wblks * data[0].bsize;
			zk->nroperations = data[0].xrate;
			zk->nwoperations = data[0].xfers - data[0].xrate;

			zbx_free(data);

			return SYSINFO_RET_OK;
		}
		else
		{
			zbx_free(data);

			return SYSINFO_RET_FAIL;
		}
	}
	else
	{
		perfstat_disk_total_t	data;

		/* obtain the data for all the disks available */
		if (0 < perfstat_disk_total(NULL, &data, sizeof(perfstat_disk_total_t), 1))
		{
			zk->nread = data.rblks * 512;
			zk->nwritten = data.wblks * 512;
			zk->nroperations = data.xrate;
			zk->nwoperations = data.xfers - data.xrate;

			return SYSINFO_RET_OK;
		}
		else
			return SYSINFO_RET_FAIL;
	}
#else
	return SYSINFO_RET_FAIL;
#endif
}

static int	VFS_DEV_READ_BYTES(const char *devname, AGENT_RESULT *result)
{
	if (SYSINFO_RET_OK != get_diskstat_io(devname, &disk_stat))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, disk_stat.nread);

	return SYSINFO_RET_OK;
}

static int	VFS_DEV_WRITE_BYTES(const char *devname, AGENT_RESULT *result)
{
	if (SYSINFO_RET_OK != get_diskstat_io(devname, &disk_stat))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, disk_stat.nwritten);

	return SYSINFO_RET_OK;
}

static int	VFS_DEV_WRITE_OPERATIONS(const char *devname, AGENT_RESULT *result)
{
	zbx_uint64_t	value;

	if (SYSINFO_RET_OK != get_diskstat_io(devname, &disk_stat))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, disk_stat.nwoperations);

	return SYSINFO_RET_OK;
}

static int	VFS_DEV_READ_OPERATIONS(const char *devname, AGENT_RESULT *result)
{
	zbx_uint64_t	value;

	if (SYSINFO_RET_OK != get_diskstat_io(devname, &disk_stat))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, disk_stat.nroperations);

	return SYSINFO_RET_OK;
}

int	VFS_DEV_WRITE(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*devname, *mode;
	int	ret = SYSINFO_RET_FAIL;

	if (2 < request->nparam)
		return SYSINFO_RET_FAIL;

	devname = get_rparam(request, 0);
	mode = get_rparam(request, 1);

	if (NULL == mode || '\0' == *mode || 0 == strcmp(mode, "operations"))
		ret = VFS_DEV_WRITE_OPERATIONS(devname, result);
	else if (0 == strcmp(mode, "bytes"))
		ret = VFS_DEV_WRITE_BYTES(devname, result);
	else
		ret = SYSINFO_RET_FAIL;

	return ret;
}

int	VFS_DEV_READ(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*devname, *mode;
	int	ret = SYSINFO_RET_FAIL;

	if (2 < request->nparam)
		return SYSINFO_RET_FAIL;

	devname = get_rparam(request, 0);
	mode = get_rparam(request, 1);

	if (NULL == mode || '\0' == *mode || 0 == strcmp(mode, "operations"))
		ret = VFS_DEV_READ_OPERATIONS(devname, result);
	else if (0 == strcmp(mode, "bytes"))
		ret = VFS_DEV_READ_BYTES(devname, result);
	else
		ret = SYSINFO_RET_FAIL;

	return ret;
}
