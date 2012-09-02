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
#include "stats.h"
#include "diskdevices.h"

static struct statinfo	*si = NULL;

int	get_diskstat(const char *devname, zbx_uint64_t *dstat)
{
	int		i;
	struct devstat	*ds = NULL;
	int		ret = FAIL;
	char		dev[DEVSTAT_NAME_LEN + 10];

	assert(devname);

	for (i = 0; i < ZBX_DSTAT_MAX; i++)
		dstat[i] = (zbx_uint64_t)__UINT64_C(0);

	if (NULL == si)
	{
		si = (struct statinfo *)zbx_malloc(si, sizeof(struct statinfo));
		si->dinfo = (struct devinfo *)zbx_malloc(NULL, sizeof(struct devinfo));
		memset(si->dinfo, 0, sizeof(struct devinfo));
	}

#if DEVSTAT_USER_API_VER >= 5
	if (-1 == devstat_getdevs(NULL, si))
#else
	if (-1 == getdevs(si))
#endif
		return FAIL;

	for (i = 0; i < si->dinfo->numdevs; i++)
	{
		ds = &si->dinfo->devices[i];

		if ('\0' != *devname)
		{
			zbx_snprintf(dev, sizeof(dev), "%s%d", ds->device_name, ds->unit_number);
			if (0 != strcmp(dev, devname))
				continue;
		}

#if DEVSTAT_USER_API_VER >= 5
		dstat[ZBX_DSTAT_R_OPER] += (zbx_uint64_t)ds->operations[DEVSTAT_READ];
		dstat[ZBX_DSTAT_W_OPER] += (zbx_uint64_t)ds->operations[DEVSTAT_WRITE];
		dstat[ZBX_DSTAT_R_BYTE] += (zbx_uint64_t)ds->bytes[DEVSTAT_READ];
		dstat[ZBX_DSTAT_W_BYTE] += (zbx_uint64_t)ds->bytes[DEVSTAT_WRITE];
#else
		dstat[ZBX_DSTAT_R_OPER] += (zbx_uint64_t)ds->num_reads;
		dstat[ZBX_DSTAT_W_OPER] += (zbx_uint64_t)ds->num_writes;
		dstat[ZBX_DSTAT_R_BYTE] += (zbx_uint64_t)ds->bytes_read;
		dstat[ZBX_DSTAT_W_BYTE] += (zbx_uint64_t)ds->bytes_written;
#endif
		ret = SUCCEED;
	}

	return ret;
}

int	VFS_DEV_WRITE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	ZBX_SINGLE_DISKDEVICE_DATA *device;
	char		devname[32], tmp[16];
	int		type, mode, nparam;
	zbx_uint64_t	dstats[ZBX_DSTAT_MAX];

	nparam = num_param(param);
	if (nparam > 3)
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, devname, sizeof(devname)))
		return SYSINFO_RET_FAIL;

	if (0 == strcmp(devname, "all"))
		*devname = '\0';

	if (0 != get_param(param, 2, tmp, sizeof(tmp)))
		*tmp = '\0';

	if ('\0' == *tmp || 0 == strcmp(tmp, "bps"))	/* default parameter */
		type = ZBX_DSTAT_TYPE_BPS;
	else if (0 == strcmp(tmp, "ops"))
		type = ZBX_DSTAT_TYPE_OPS;
	else if (0 == strcmp(tmp, "bytes"))
		type = ZBX_DSTAT_TYPE_BYTE;
	else if (0 == strcmp(tmp, "operations"))
		type = ZBX_DSTAT_TYPE_OPER;
	else
		return SYSINFO_RET_FAIL;

	if (type == ZBX_DSTAT_TYPE_BYTE || type == ZBX_DSTAT_TYPE_OPER)
	{
		if (nparam > 2)
			return SYSINFO_RET_FAIL;

		if (FAIL == get_diskstat(devname, dstats))
			return SYSINFO_RET_FAIL;

		if (type == ZBX_DSTAT_TYPE_BYTE)
			SET_UI64_RESULT(result, dstats[ZBX_DSTAT_W_BYTE]);
		else	/* ZBX_DSTAT_TYPE_OPER */
			SET_UI64_RESULT(result, dstats[ZBX_DSTAT_W_OPER]);

		return SYSINFO_RET_OK;
	}

	if (!DISKDEVICE_COLLECTOR_STARTED(collector))
	{
		SET_MSG_RESULT(result, strdup("Collector is not started!"));
		return SYSINFO_RET_OK;
	}

	if (0 != get_param(param, 3, tmp, sizeof(tmp)))
		*tmp = '\0';

	if ('\0' == *tmp || 0 == strcmp(tmp, "avg1"))	/* default parameter */
		mode = ZBX_AVG1;
	else if (0 == strcmp(tmp, "avg5"))
		mode = ZBX_AVG5;
	else if (0 == strcmp(tmp, "avg15"))
		mode = ZBX_AVG15;
	else
		return SYSINFO_RET_FAIL;

	if (NULL == (device = collector_diskdevice_get(devname)))
	{
		if (FAIL == get_diskstat(devname, dstats))	/* validate device name */
			return SYSINFO_RET_FAIL;

		if (NULL == (device = collector_diskdevice_add(devname)))
			return SYSINFO_RET_FAIL;
	}

	if (type == ZBX_DSTAT_TYPE_BPS)	/* default parameter */
		SET_DBL_RESULT(result, device->w_bps[mode]);
	else if (type == ZBX_DSTAT_TYPE_OPS)
		SET_DBL_RESULT(result, device->w_ops[mode]);

	return SYSINFO_RET_OK;
}

int	VFS_DEV_READ(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	ZBX_SINGLE_DISKDEVICE_DATA *device;
	char		devname[32], tmp[16];
	int		type, mode, nparam;
	zbx_uint64_t	dstats[ZBX_DSTAT_MAX];

	nparam = num_param(param);
	if (nparam > 3)
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, devname, sizeof(devname)))
		return SYSINFO_RET_FAIL;

	if (0 == strcmp(devname, "all"))
		*devname = '\0';

	if (0 != get_param(param, 2, tmp, sizeof(tmp)))
		*tmp = '\0';

	if ('\0' == *tmp || 0 == strcmp(tmp, "bps"))	/* default parameter */
		type = ZBX_DSTAT_TYPE_BPS;
	else if (0 == strcmp(tmp, "ops"))
		type = ZBX_DSTAT_TYPE_OPS;
	else if (0 == strcmp(tmp, "bytes"))
		type = ZBX_DSTAT_TYPE_BYTE;
	else if (0 == strcmp(tmp, "operations"))
		type = ZBX_DSTAT_TYPE_OPER;
	else
		return SYSINFO_RET_FAIL;

	if (type == ZBX_DSTAT_TYPE_BYTE || type == ZBX_DSTAT_TYPE_OPER)
	{
		if (nparam > 2)
			return SYSINFO_RET_FAIL;

		if (FAIL == get_diskstat(devname, dstats))
			return SYSINFO_RET_FAIL;

		if (type == ZBX_DSTAT_TYPE_BYTE)
			SET_UI64_RESULT(result, dstats[ZBX_DSTAT_R_BYTE]);
		else	/* ZBX_DSTAT_TYPE_OPER */
			SET_UI64_RESULT(result, dstats[ZBX_DSTAT_R_OPER]);

		return SYSINFO_RET_OK;
	}

	if (!DISKDEVICE_COLLECTOR_STARTED(collector))
	{
		SET_MSG_RESULT(result, strdup("Collector is not started!"));
		return SYSINFO_RET_OK;
	}

	if (0 != get_param(param, 3, tmp, sizeof(tmp)))
		*tmp = '\0';

	if ('\0' == *tmp || 0 == strcmp(tmp, "avg1"))	/* default parameter */
		mode = ZBX_AVG1;
	else if (0 == strcmp(tmp, "avg5"))
		mode = ZBX_AVG5;
	else if (0 == strcmp(tmp, "avg15"))
		mode = ZBX_AVG15;
	else
		return SYSINFO_RET_FAIL;

	if (NULL == (device = collector_diskdevice_get(devname)))
	{
		if (FAIL == get_diskstat(devname, dstats))	/* validate device name */
			return SYSINFO_RET_FAIL;

		if (NULL == (device = collector_diskdevice_add(devname)))
			return SYSINFO_RET_FAIL;
	}

	if (type == ZBX_DSTAT_TYPE_BPS)	/* default parameter */
		SET_DBL_RESULT(result, device->r_bps[mode]);
	else if (type == ZBX_DSTAT_TYPE_OPS)
		SET_DBL_RESULT(result, device->r_ops[mode]);

	return SYSINFO_RET_OK;
}
