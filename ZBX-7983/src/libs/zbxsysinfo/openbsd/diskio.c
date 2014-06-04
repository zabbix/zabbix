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

int	get_diskstat(const char *devname, zbx_uint64_t *dstat)
{
	return FAIL;
}

static int	get_disk_stats(const char *devname, zbx_uint64_t *rbytes, zbx_uint64_t *wbytes, zbx_uint64_t *roper, zbx_uint64_t *woper)
{
	int			ret = SYSINFO_RET_FAIL, mib[2], drive_count;
	size_t			len;
	struct diskstats	*stats;
	int			i;

	mib[0] = CTL_HW;
	mib[1] = HW_DISKCOUNT;

	len = sizeof(drive_count);

	if (0 != sysctl(mib, 2, &drive_count, &len, NULL, 0))
		return SYSINFO_RET_FAIL;

	len = (drive_count * sizeof(struct diskstats));

	if (NULL == (stats = zbx_calloc(NULL, drive_count, len)))
		return SYSINFO_RET_FAIL;

	mib[0] = CTL_HW;
	mib[1] = HW_DISKSTATS;

	if (rbytes)
		*rbytes = 0;
	if (wbytes)
		*wbytes = 0;
	if (roper)
		*roper = 0;
	if (woper)
		*woper = 0;

	if (0 == sysctl(mib, 2, stats, &len, NULL, 0))
	{
		for (i = 0; i < drive_count; i++)
		{
			if (NULL == devname || '\0' == *devname || 0 == strcmp(devname, "all") ||
				0 == strcmp(devname, stats[i].ds_name))
			{
				if (rbytes)
					*rbytes += stats[i].ds_rbytes;
				if (wbytes)
					*wbytes += stats[i].ds_wbytes;
				if (roper)
					*roper += stats[i].ds_rxfer;
				if (woper)
					*woper += stats[i].ds_wxfer;
				ret = SYSINFO_RET_OK;
			}
		}
	}

	free(stats);

	return ret;
}

static int	VFS_DEV_READ_BYTES(const char *devname, AGENT_RESULT *result)
{
	zbx_uint64_t	value;

	if (SYSINFO_RET_OK != get_disk_stats(devname, &value, NULL, NULL, NULL))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, value);

	return SYSINFO_RET_OK;
}

static int	VFS_DEV_READ_OPERATIONS(const char *devname, AGENT_RESULT *result)
{
	zbx_uint64_t	value;

	if (SYSINFO_RET_OK != get_disk_stats(devname, NULL, NULL, &value, NULL))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, value);

	return SYSINFO_RET_OK;
}

static int	VFS_DEV_WRITE_BYTES(const char *devname, AGENT_RESULT *result)
{
	zbx_uint64_t	value;

	if (SYSINFO_RET_OK != get_disk_stats(devname, NULL, &value, NULL, NULL))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, value);

	return SYSINFO_RET_OK;
}

static int	VFS_DEV_WRITE_OPERATIONS(const char *devname, AGENT_RESULT *result)
{
	zbx_uint64_t	value;

	if (SYSINFO_RET_OK != get_disk_stats(devname, NULL, NULL, NULL, &value))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, value);

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
