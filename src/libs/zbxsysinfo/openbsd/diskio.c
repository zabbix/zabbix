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

int	zbx_get_diskstat(const char *devname, zbx_uint64_t *dstat)
{
	return FAIL;
}

static int	get_disk_stats(const char *devname, zbx_uint64_t *rbytes, zbx_uint64_t *wbytes, zbx_uint64_t *roper,
		zbx_uint64_t *woper, char **error)
{
	int			ret = SYSINFO_RET_FAIL, mib[2], drive_count;
	size_t			len = sizeof(drive_count);
	struct diskstats	*stats;

	mib[0] = CTL_HW;
	mib[1] = HW_DISKCOUNT;

	if (0 != sysctl(mib, 2, &drive_count, &len, NULL, 0))
	{
		*error = zbx_dsprintf(NULL, "Cannot obtain number of disks: %s", zbx_strerror(errno));
		return SYSINFO_RET_FAIL;
	}

	len = drive_count * sizeof(struct diskstats);

	stats = zbx_calloc(NULL, drive_count, len);

	mib[0] = CTL_HW;
	mib[1] = HW_DISKSTATS;

	if (NULL != rbytes)
		*rbytes = 0;
	if (NULL != wbytes)
		*wbytes = 0;
	if (NULL != roper)
		*roper = 0;
	if (NULL != woper)
		*woper = 0;

	if (0 != sysctl(mib, 2, stats, &len, NULL, 0))
	{
		zbx_free(stats);
		*error = zbx_dsprintf(NULL, "Cannot obtain disk information: %s", zbx_strerror(errno));
		return SYSINFO_RET_FAIL;
	}

	for (int i = 0; i < drive_count; i++)
	{
		if (NULL == devname || '\0' == *devname || 0 == strcmp(devname, "all") ||
				0 == strcmp(devname, stats[i].ds_name))
		{
			if (NULL != rbytes)
				*rbytes += stats[i].ds_rbytes;
			if (NULL != wbytes)
				*wbytes += stats[i].ds_wbytes;
			if (NULL != roper)
				*roper += stats[i].ds_rxfer;
			if (NULL != woper)
				*woper += stats[i].ds_wxfer;

			ret = SYSINFO_RET_OK;
		}
	}

	zbx_free(stats);

	if (SYSINFO_RET_FAIL == ret)
	{
		*error = zbx_strdup(NULL, "Cannot find information for this disk device.");
		return SYSINFO_RET_FAIL;
	}

	return ret;
}

static int	vfs_dev_read_bytes(const char *devname, AGENT_RESULT *result)
{
	zbx_uint64_t	value;
	char		*error;

	if (SYSINFO_RET_OK != get_disk_stats(devname, &value, NULL, NULL, NULL, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	SET_UI64_RESULT(result, value);

	return SYSINFO_RET_OK;
}

static int	vfs_dev_read_operations(const char *devname, AGENT_RESULT *result)
{
	zbx_uint64_t	value;
	char		*error;

	if (SYSINFO_RET_OK != get_disk_stats(devname, NULL, NULL, &value, NULL, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	SET_UI64_RESULT(result, value);

	return SYSINFO_RET_OK;
}

static int	vfs_dev_write_bytes(const char *devname, AGENT_RESULT *result)
{
	zbx_uint64_t	value;
	char		*error;

	if (SYSINFO_RET_OK != get_disk_stats(devname, NULL, &value, NULL, NULL, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	SET_UI64_RESULT(result, value);

	return SYSINFO_RET_OK;
}

static int	vfs_dev_write_operations(const char *devname, AGENT_RESULT *result)
{
	zbx_uint64_t	value;
	char		*error;

	if (SYSINFO_RET_OK != get_disk_stats(devname, NULL, NULL, NULL, &value, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	SET_UI64_RESULT(result, value);

	return SYSINFO_RET_OK;
}

int	vfs_dev_read(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*devname, *mode;
	int	ret;

	if (2 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	devname = get_rparam(request, 0);
	mode = get_rparam(request, 1);

	if (NULL == mode || '\0' == *mode || 0 == strcmp(mode, "operations"))
		ret = vfs_dev_read_operations(devname, result);
	else if (0 == strcmp(mode, "bytes"))
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
	char	*devname, *mode;
	int	ret;

	if (2 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	devname = get_rparam(request, 0);
	mode = get_rparam(request, 1);

	if (NULL == mode || '\0' == *mode || 0 == strcmp(mode, "operations"))
		ret = vfs_dev_write_operations(devname, result);
	else if (0 == strcmp(mode, "bytes"))
		ret = vfs_dev_write_bytes(devname, result);
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		return SYSINFO_RET_FAIL;
	}

	return ret;
}
