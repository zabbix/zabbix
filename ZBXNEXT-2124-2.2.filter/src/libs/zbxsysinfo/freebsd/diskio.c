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
#include "stats.h"
#include "diskdevices.h"

#define ZBX_DEV_PFX	"/dev/"
#define ZBX_DEV_READ	0
#define ZBX_DEV_WRITE	1

static struct statinfo	*si = NULL;

int	get_diskstat(const char *devname, zbx_uint64_t *dstat)
{
	int		i;
	struct devstat	*ds = NULL;
	int		ret = FAIL;
	char		dev[DEVSTAT_NAME_LEN + 10];
	const char	*pd;	/* pointer to device name without '/dev/' prefix, e.g. 'da0' */

	assert(devname);

	for (i = 0; i < ZBX_DSTAT_MAX; i++)
		dstat[i] = (zbx_uint64_t)__UINT64_C(0);

	if (NULL == si)
	{
		si = (struct statinfo *)zbx_malloc(si, sizeof(struct statinfo));
		si->dinfo = (struct devinfo *)zbx_malloc(NULL, sizeof(struct devinfo));
		memset(si->dinfo, 0, sizeof(struct devinfo));
	}

	pd = devname;

	/* skip prefix ZBX_DEV_PFX, if present */
	if ('\0' != *devname && 0 == strncmp(pd, ZBX_DEV_PFX, sizeof(ZBX_DEV_PFX) - 1))
			pd += sizeof(ZBX_DEV_PFX) - 1;

#if DEVSTAT_USER_API_VER >= 5
	if (-1 == devstat_getdevs(NULL, si))
#else
	if (-1 == getdevs(si))
#endif
		return FAIL;

	for (i = 0; i < si->dinfo->numdevs; i++)
	{
		ds = &si->dinfo->devices[i];

		/* empty '*devname' string means adding statistics for all disks together */
		if ('\0' != *devname)
		{
			zbx_snprintf(dev, sizeof(dev), "%s%d", ds->device_name, ds->unit_number);
			if (0 != strcmp(dev, pd))
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

		if ('\0' != *devname)
			break;
	}

	return ret;
}

static int	vfs_dev_rw(AGENT_REQUEST *request, AGENT_RESULT *result, int rw)
{
	ZBX_SINGLE_DISKDEVICE_DATA *device;
	char		devname[32], *tmp;
	int		type, mode;
	zbx_uint64_t	dstats[ZBX_DSTAT_MAX];
	char		*pd;			/* pointer to device name without '/dev/' prefix, e.g. 'da0' */

	if (3 < request->nparam)
		return SYSINFO_RET_FAIL;

	tmp = get_rparam(request, 0);

	if (NULL == tmp || 0 == strcmp(tmp, "all"))
		*devname = '\0';
	else
		strscpy(devname, tmp);

	pd = devname;

	if ('\0' != *pd)
	{
		/* skip prefix ZBX_DEV_PFX, if present */
		if (0 == strncmp(pd, ZBX_DEV_PFX, sizeof(ZBX_DEV_PFX) - 1))
			pd += sizeof(ZBX_DEV_PFX) - 1;
	}

	tmp = get_rparam(request, 1);

	if (NULL == tmp || '\0' == *tmp || 0 == strcmp(tmp, "bps"))	/* default parameter */
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
		if (request->nparam > 2)
			return SYSINFO_RET_FAIL;

		if (FAIL == get_diskstat(pd, dstats))
			return SYSINFO_RET_FAIL;

		if (ZBX_DSTAT_TYPE_BYTE == type)
			SET_UI64_RESULT(result, dstats[(ZBX_DEV_READ == rw ? ZBX_DSTAT_R_BYTE : ZBX_DSTAT_W_BYTE)]);
		else	/* ZBX_DSTAT_TYPE_OPER */
			SET_UI64_RESULT(result, dstats[(ZBX_DEV_READ == rw ? ZBX_DSTAT_R_OPER : ZBX_DSTAT_W_OPER)]);

		return SYSINFO_RET_OK;
	}

	tmp = get_rparam(request, 2);

	if (NULL == tmp || '\0' == *tmp || 0 == strcmp(tmp, "avg1"))	/* default parameter */
		mode = ZBX_AVG1;
	else if (0 == strcmp(tmp, "avg5"))
		mode = ZBX_AVG5;
	else if (0 == strcmp(tmp, "avg15"))
		mode = ZBX_AVG15;
	else
		return SYSINFO_RET_FAIL;

	if (NULL == collector)
	{
		/* CPU statistics collector and (optionally) disk statistics collector is started only when Zabbix */
		/* agentd is running as a daemon. When Zabbix agent or agentd is started with "-p" or "-t" parameter */
		/* the collectors are not available and keys "vfs.dev.read", "vfs.dev.write" with some parameters */
		/* (e.g. sps, ops) are not supported. */

		SET_MSG_RESULT(result, strdup("This parameter is available only in daemon mode when collectors are started."));
		return SYSINFO_RET_FAIL;
	}

	if (NULL == (device = collector_diskdevice_get(pd)))
	{
		if (FAIL == get_diskstat(pd, dstats))	/* validate device name */
			return SYSINFO_RET_FAIL;

		if (NULL == (device = collector_diskdevice_add(pd)))
			return SYSINFO_RET_FAIL;
	}

	if (ZBX_DSTAT_TYPE_BPS == type)	/* default parameter */
		SET_DBL_RESULT(result, (ZBX_DEV_READ == rw ? device->r_bps[mode] : device->w_bps[mode]));
	else if (ZBX_DSTAT_TYPE_OPS == type)
		SET_DBL_RESULT(result, (ZBX_DEV_READ == rw ? device->r_ops[mode] : device->w_ops[mode]));

	return SYSINFO_RET_OK;
}

int	VFS_DEV_READ(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	return vfs_dev_rw(request, result, ZBX_DEV_READ);
}

int	VFS_DEV_WRITE(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	return vfs_dev_rw(request, result, ZBX_DEV_WRITE);
}
