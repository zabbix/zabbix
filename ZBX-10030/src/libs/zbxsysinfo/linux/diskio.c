/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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

#if defined(KERNEL_2_4)
#	define INFO_FILE_NAME	"/proc/partitions"
#	define PARSE(line)	if (sscanf(line, ZBX_FS_UI64 ZBX_FS_UI64 " %*d %s " 		\
					ZBX_FS_UI64 " %*d " ZBX_FS_UI64 " %*d "			\
					ZBX_FS_UI64 " %*d " ZBX_FS_UI64 " %*d %*d %*d %*d",	\
				&rdev_major,							\
				&rdev_minor,							\
				name,								\
				&ds[ZBX_DSTAT_R_OPER],						\
				&ds[ZBX_DSTAT_R_SECT],						\
				&ds[ZBX_DSTAT_W_OPER],						\
				&ds[ZBX_DSTAT_W_SECT]						\
				) != 7) continue
#else
#	define INFO_FILE_NAME	"/proc/diskstats"
#	define PARSE(line)	if (sscanf(line, ZBX_FS_UI64 ZBX_FS_UI64 " %s "			\
					ZBX_FS_UI64 " %*d " ZBX_FS_UI64 " %*d "			\
					ZBX_FS_UI64 " %*d " ZBX_FS_UI64 " %*d %*d %*d %*d",	\
				&rdev_major,							\
				&rdev_minor,							\
				name,								\
				&ds[ZBX_DSTAT_R_OPER],						\
				&ds[ZBX_DSTAT_R_SECT],						\
				&ds[ZBX_DSTAT_W_OPER],						\
				&ds[ZBX_DSTAT_W_SECT]						\
				) != 7								\
				&&								\
				/* some disk partitions */					\
				sscanf(line, ZBX_FS_UI64 ZBX_FS_UI64 " %s "			\
					ZBX_FS_UI64 ZBX_FS_UI64					\
					ZBX_FS_UI64 ZBX_FS_UI64,				\
				&rdev_major,							\
				&rdev_minor,							\
				name,								\
				&ds[ZBX_DSTAT_R_OPER],						\
				&ds[ZBX_DSTAT_R_SECT],						\
				&ds[ZBX_DSTAT_W_OPER],						\
				&ds[ZBX_DSTAT_W_SECT]						\
				) != 7								\
				) continue
#endif

int	get_diskstat(const char *devname, zbx_uint64_t *dstat)
{
	FILE		*f;
	char		tmp[MAX_STRING_LEN], name[MAX_STRING_LEN], dev_path[MAX_STRING_LEN];
	int		i, ret = FAIL, dev_exists = FAIL;
	zbx_uint64_t	ds[ZBX_DSTAT_MAX], rdev_major, rdev_minor;
	zbx_stat_t 	dev_st;
	int		found = 0;

	for (i = 0; i < ZBX_DSTAT_MAX; i++)
		dstat[i] = (zbx_uint64_t)__UINT64_C(0);

	if (NULL != devname && '\0' != *devname && 0 != strcmp(devname, "all"))
	{
		*dev_path = '\0';
		if (0 != strncmp(devname, ZBX_DEV_PFX, ZBX_CONST_STRLEN(ZBX_DEV_PFX)))
			strscpy(dev_path, ZBX_DEV_PFX);
		strscat(dev_path, devname);

		if (zbx_stat(dev_path, &dev_st) == 0)
			dev_exists = SUCCEED;
	}

	if (NULL == (f = fopen(INFO_FILE_NAME, "r")))
		return FAIL;

	while (NULL != fgets(tmp, sizeof(tmp), f))
	{
		PARSE(tmp);

		if (NULL != devname && '\0' != *devname && 0 != strcmp(devname, "all"))
		{
			if (0 != strcmp(name, devname))
			{
				if (SUCCEED != dev_exists
					|| major(dev_st.st_rdev) != rdev_major
					|| minor(dev_st.st_rdev) != rdev_minor)
					continue;
			}
			else
				found = 1;
		}

		dstat[ZBX_DSTAT_R_OPER] += ds[ZBX_DSTAT_R_OPER];
		dstat[ZBX_DSTAT_R_SECT] += ds[ZBX_DSTAT_R_SECT];
		dstat[ZBX_DSTAT_W_OPER] += ds[ZBX_DSTAT_W_OPER];
		dstat[ZBX_DSTAT_W_SECT] += ds[ZBX_DSTAT_W_SECT];

		ret = SUCCEED;

		if (1 == found)
			break;
	}
	zbx_fclose(f);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Comments: Translate device name to the one used internally by kernel. The  *
 *           translation is done based on minor and major device numbers      *
 *           listed in INFO_FILE_NAME . If the names differ it is usually an  *
 *           LVM device which is listed in kernel device mapper.              *
 *                                                                            *
 ******************************************************************************/
static int	get_kernel_devname(const char *devname, char *kernel_devname, size_t max_kernel_devname_len)
{
	FILE		*f;
	char		tmp[MAX_STRING_LEN], name[MAX_STRING_LEN], dev_path[MAX_STRING_LEN];
	int		ret = FAIL;
	zbx_uint64_t	ds[ZBX_DSTAT_MAX], rdev_major, rdev_minor;
	zbx_stat_t	dev_st;

	if ('\0' == *devname)
		return ret;

	*dev_path = '\0';
	if (0 != strncmp(devname, ZBX_DEV_PFX, ZBX_CONST_STRLEN(ZBX_DEV_PFX)))
		strscpy(dev_path, ZBX_DEV_PFX);
	strscat(dev_path, devname);

	if (zbx_stat(dev_path, &dev_st) < 0 || NULL == (f = fopen(INFO_FILE_NAME, "r")))
		return ret;

	while (NULL != fgets(tmp, sizeof(tmp), f))
	{
		PARSE(tmp);
		if (major(dev_st.st_rdev) != rdev_major || minor(dev_st.st_rdev) != rdev_minor)
			continue;

		zbx_strlcpy(kernel_devname, name, max_kernel_devname_len);
		ret = SUCCEED;
		break;
	}
	zbx_fclose(f);

	return ret;
}

static int	vfs_dev_rw(AGENT_REQUEST *request, AGENT_RESULT *result, int rw)
{
	ZBX_SINGLE_DISKDEVICE_DATA	*device;
	char				*devname, *tmp, kernel_devname[MAX_STRING_LEN];
	int				type, mode;
	zbx_uint64_t			dstats[ZBX_DSTAT_MAX];

	if (3 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	devname = get_rparam(request, 0);
	tmp = get_rparam(request, 1);

	if (NULL == tmp || '\0' == *tmp || 0 == strcmp(tmp, "sps"))	/* default parameter */
		type = ZBX_DSTAT_TYPE_SPS;
	else if (0 == strcmp(tmp, "ops"))
		type = ZBX_DSTAT_TYPE_OPS;
	else if (0 == strcmp(tmp, "sectors"))
		type = ZBX_DSTAT_TYPE_SECT;
	else if (0 == strcmp(tmp, "operations"))
		type = ZBX_DSTAT_TYPE_OPER;
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		return SYSINFO_RET_FAIL;
	}

	if (type == ZBX_DSTAT_TYPE_SECT || type == ZBX_DSTAT_TYPE_OPER)
	{
		if (request->nparam > 2)
		{
			/* Mode is supported only if type is in: operations, sectors. */
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
			return SYSINFO_RET_FAIL;
		}

		if (SUCCEED != get_diskstat(devname, dstats))
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain disk information."));
			return SYSINFO_RET_FAIL;
		}

		if (ZBX_DSTAT_TYPE_SECT == type)
			SET_UI64_RESULT(result, dstats[(ZBX_DEV_READ == rw ? ZBX_DSTAT_R_SECT : ZBX_DSTAT_W_SECT)]);
		else
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
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid third parameter."));
		return SYSINFO_RET_FAIL;
	}

	if (NULL == collector)
	{
		/* CPU statistics collector and (optionally) disk statistics collector is started only when Zabbix */
		/* agentd is running as a daemon. When Zabbix agent or agentd is started with "-p" or "-t" parameter */
		/* the collectors are not available and keys "vfs.dev.read", "vfs.dev.write" with some parameters */
		/* (e.g. sps, ops) are not supported. */

		SET_MSG_RESULT(result, zbx_strdup(NULL, "This item is available only in daemon mode when collectors are"
				" started."));
		return SYSINFO_RET_FAIL;
	}

	if (NULL == devname || '\0' == *devname || 0 == strcmp(devname, "all"))
	{
		*kernel_devname = '\0';
	}
	else if (SUCCEED != get_kernel_devname(devname, kernel_devname, sizeof(kernel_devname)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain device name used internally by the kernel."));
		return SYSINFO_RET_FAIL;
	}

	if (NULL == (device = collector_diskdevice_get(kernel_devname)))
	{
		if (SUCCEED != get_diskstat(kernel_devname, dstats))
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain disk information."));
			return SYSINFO_RET_FAIL;
		}

		if (NULL == (device = collector_diskdevice_add(kernel_devname)))
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot add disk device to agent collector."));
			return SYSINFO_RET_FAIL;
		}
	}

	if (ZBX_DSTAT_TYPE_SPS == type)
		SET_DBL_RESULT(result, (ZBX_DEV_READ == rw ? device->r_sps[mode] : device->w_sps[mode]));
	else
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
