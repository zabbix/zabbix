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

#include "zbxjson.h"
#include "zbxstr.h"

#include "../common/stats.h"
#include "../common/diskdevices.h"

#define ZBX_DEV_PFX		"/dev/"
#define ZBX_DEV_READ		0
#define ZBX_DEV_WRITE		1
#define ZBX_SYS_BLKDEV_PFX	"/sys/dev/block/"

#if defined(KERNEL_2_4)
#	define INFO_FILE_NAME	"/proc/partitions"
#	define PARSE(line)	if (sscanf(line, ZBX_FS_UI64 ZBX_FS_UI64 " %*d %s "		\
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

int	zbx_get_diskstat(const char *devname, zbx_uint64_t *dstat)
{
	FILE		*f;
	char		tmp[MAX_STRING_LEN], name[MAX_STRING_LEN], dev_path[MAX_STRING_LEN];
	int		ret = FAIL, dev_exists = FAIL, found = 0;
	zbx_uint64_t	ds[ZBX_DSTAT_MAX], rdev_major, rdev_minor;
	zbx_stat_t	dev_st;

	for (int i = 0; i < ZBX_DSTAT_MAX; i++)
		dstat[i] = (zbx_uint64_t)__UINT64_C(0);

	if (NULL != devname && '\0' != *devname && 0 != strcmp(devname, "all"))
	{
		*dev_path = '\0';
		if (0 != strncmp(devname, ZBX_DEV_PFX, ZBX_CONST_STRLEN(ZBX_DEV_PFX)))
			zbx_strscpy(dev_path, ZBX_DEV_PFX);
		zbx_strscat(dev_path, devname);

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
 * Comments: Translates device name to the one used internally by kernel. The *
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
		zbx_strscpy(dev_path, ZBX_DEV_PFX);
	zbx_strscat(dev_path, devname);

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
	zbx_single_diskdevice_data	*device;
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

		if (SUCCEED != zbx_get_diskstat(devname, dstats))
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

	if (NULL == get_collector())
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
		if (SUCCEED != zbx_get_diskstat(kernel_devname, dstats))
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

int	vfs_dev_read(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	return vfs_dev_rw(request, result, ZBX_DEV_READ);
}

int	vfs_dev_write(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	return vfs_dev_rw(request, result, ZBX_DEV_WRITE);
}

#define DEVTYPE_STR	"DEVTYPE="
#define DEVTYPE_STR_LEN	ZBX_CONST_STRLEN(DEVTYPE_STR)
static void	process_entry(struct dirent *entries, zbx_stat_t *stat_buf, int sysfs_found, struct zbx_json *j)
{
/* SCSI device type CD/DVD-ROM. http://en.wikipedia.org/wiki/SCSI_Peripheral_Device_Type */
#define SCSI_TYPE_ROM			0x05
	char		tmp[MAX_STRING_LEN];

	zbx_snprintf(tmp, sizeof(tmp), ZBX_DEV_PFX "%s", entries->d_name);

	if (0 == zbx_stat(tmp, stat_buf) && 0 != S_ISBLK(stat_buf->st_mode))
	{
		int	devtype_found = 0, dev_bypass = 0, uevent_found = 0, offset = 0;
		char	sys_blkdev_pfx_uevent[MAX_STRING_LEN];

		if (1 == sysfs_found)
		{
			int		type;
			FILE		*f;
			zbx_stat_t	lstat_buf;

			if (0 == lstat(tmp, &lstat_buf))
			{
				char	sys_blkdev_pfx_device_type[MAX_STRING_LEN];

				zbx_snprintf(sys_blkdev_pfx_device_type, sizeof(sys_blkdev_pfx_device_type),
						ZBX_SYS_BLKDEV_PFX "%u:%u/device/type", major(stat_buf->st_rdev),
						minor(stat_buf->st_rdev));

				if (NULL != (f = fopen(sys_blkdev_pfx_device_type, "r")) &&
						1 == fscanf(f, "%d", &type) && SCSI_TYPE_ROM == type)
				{
					devtype_found = 1;

					if (0 != S_ISLNK(lstat_buf.st_mode))
						dev_bypass = 1;
					else
						zbx_snprintf(tmp, sizeof(tmp), "rom");
				}

				zbx_fclose(f);
			}
			else
				return;

			if (0 == devtype_found)
			{
				zbx_snprintf(sys_blkdev_pfx_uevent, sizeof(sys_blkdev_pfx_uevent),
						ZBX_SYS_BLKDEV_PFX "%u:%u/uevent", major(stat_buf->st_rdev),
						minor(stat_buf->st_rdev));

				if (NULL != (f = fopen(sys_blkdev_pfx_uevent, "r")))
				{
					while (NULL != fgets(sys_blkdev_pfx_uevent, sizeof(sys_blkdev_pfx_uevent), f))
					{
						if (0 == strncmp(sys_blkdev_pfx_uevent, DEVTYPE_STR, DEVTYPE_STR_LEN))
						{
							char	*p;
							size_t	l;

							l = strlen(sys_blkdev_pfx_uevent);
							/* dismiss trailing \n */
							p = sys_blkdev_pfx_uevent + l - 1;
							if ('\n' == *p)
								*p = '\0';

							uevent_found = 1;
							offset = DEVTYPE_STR_LEN;
							break;
						}
					}
					zbx_fclose(f);
				}
			}
		}

		if (0 == dev_bypass)
		{
			zbx_json_addobject(j, NULL);
			zbx_json_addstring(j, "{#DEVNAME}", entries->d_name, ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(j, "{#DEVTYPE}", 1 == devtype_found ? tmp + offset :
					(1 == uevent_found ? sys_blkdev_pfx_uevent : ""),
					ZBX_JSON_TYPE_STRING);
			zbx_json_close(j);
		}
	}
#undef SCSI_TYPE_ROM
}

int	vfs_dev_discovery(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	DIR		*dir;
	zbx_stat_t	stat_buf;
	int		sysfs_found;
	struct dirent	*entries;
	struct zbx_json	j;

	ZBX_UNUSED(request);

	if (NULL != (dir = opendir(ZBX_DEV_PFX)))
	{
		zbx_json_initarray(&j, ZBX_JSON_STAT_BUF_LEN);

		/* check if sys fs with block devices is available */
		if (0 == zbx_stat(ZBX_SYS_BLKDEV_PFX, &stat_buf) && 0 != S_ISDIR(stat_buf.st_mode))
			sysfs_found = 1;
		else
			sysfs_found = 0;

		while (NULL != (entries = readdir(dir)))
		{
			process_entry(entries, &stat_buf, sysfs_found, &j);
		}
		closedir(dir);
	}
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL,
				"Cannot obtain device list: failed to open " ZBX_DEV_PFX " directory."));
		return SYSINFO_RET_FAIL;
	}

	SET_STR_RESULT(result, zbx_strdup(NULL, j.buffer));
	zbx_json_free(&j);

	return SYSINFO_RET_OK;
#undef DEVTYPE_STR
#undef DEVTYPE_STR_LEN
}
