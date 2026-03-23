/*
** Copyright (C) 2001-2026 Zabbix SIA
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
#include "zbxregexp.h"
#include "zbxalgo.h"
#include "zbxnum.h"

#include "../common/stats.h"
#include "../common/diskdevices.h"

#define ZBX_DEV_PFX		"/dev/"
#define ZBX_DEV_READ		0
#define ZBX_DEV_WRITE		1
#define ZBX_SYS_BLKDEV_PFX	"/sys/dev/block/"
#define ZBX_SYS_DISK_BYID_PFX	"/dev/disk/by-id/"
#define ZBX_MODE_DISKS		0
#define ZBX_MODE_DISK_STATS	1
#define ZBX_MODE_DEVICES	2
#define ZBX_MODE_DEVICE_STATS	3

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

typedef struct
{
	zbx_uint64_t	reads_completed;
	zbx_uint64_t	writes_completed;
	zbx_uint64_t	bytes_read;
	zbx_uint64_t	bytes_written;
	zbx_uint64_t	io_time_ms;
}
zbx_dev_stats_t;

typedef struct
{
	unsigned int	major;
	unsigned int	minor;
	char		*devid;
	char		*name;
}
zbx_device_t;

ZBX_PTR_VECTOR_DECL(device_ptr, zbx_device_t*)
ZBX_PTR_VECTOR_IMPL(device_ptr, zbx_device_t*)

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
/******************************************************************************
 *                                                                            *
 * Purpose: gets device type                                                  *
 *                                                                            *
 * Parameters: dev_name    [IN]  - like in /dev/<dev_name>                    *
 *           : sysfs_found [IN]  - 1 if sysfs a filesystem for exporting      *
 *                                 kernel objects is available at             *
 *                                 /sys/dev/block/, otherwise 0               *
 *             stat_buf    [out] - can be used to get major-id and minor-id   *
 *                                 of the device                              *
 *                                                                            *
 * Return value: device type, examples: disk, rom, partition                  *
 *                                                                            *
 * Comments: allocates memory for the return value                            *
 *                                                                            *
 ******************************************************************************/
static char	*dev_type_get(const char *dev_name, const int sysfs_found, zbx_stat_t *stat_buf)
{
/* SCSI device type CD/DVD-ROM. http://en.wikipedia.org/wiki/SCSI_Peripheral_Device_Type */
#define SCSI_TYPE_ROM			0x05
	char		tmp[MAX_STRING_LEN];

	zbx_snprintf(tmp, sizeof(tmp), ZBX_DEV_PFX "%s", dev_name);

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
				return NULL;

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
			if (1 == devtype_found)
				return zbx_strdup(NULL, tmp + offset);
			else if (1 == uevent_found)
				return zbx_strdup(NULL, sys_blkdev_pfx_uevent + offset);
			else
				return zbx_strdup(NULL, "");
		}
	}

	return NULL;
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
			char	*devtype;

			if (NULL != (devtype = dev_type_get(entries->d_name, sysfs_found, &stat_buf)))
			{
				zbx_json_addobject(&j, NULL);
				zbx_json_addstring(&j, "{#DEVNAME}", entries->d_name, ZBX_JSON_TYPE_STRING);
				zbx_json_addstring(&j, "{#DEVTYPE}", devtype, ZBX_JSON_TYPE_STRING);
				zbx_json_close(&j);
				zbx_free(devtype);
			}
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

static void	dev_path_add(const char *d_name, struct zbx_json *j)
{
	char	path[MAX_STRING_LEN];

	zbx_snprintf(path, sizeof(path), ZBX_DEV_PFX "%s", d_name);
	zbx_json_addstring(j, "path", path, ZBX_JSON_TYPE_STRING);
}

static void	dev_name_add(const char *d_name, struct zbx_json *j)
{
	zbx_json_addstring(j, "name", d_name, ZBX_JSON_TYPE_STRING);
}

static void	dev_type_add(const char *type, struct zbx_json *j)
{
	zbx_json_addstring(j, "type", type, ZBX_JSON_TYPE_STRING);
}

static void	dev_model_add(const char *model, struct zbx_json *j)
{
	zbx_json_addstring(j, "model", model, ZBX_JSON_TYPE_STRING);
}

static void	dev_serial_add(const zbx_stat_t *stat_buf, struct zbx_json *j)
{
	FILE	*f;
	int	found = FAIL;
	char	buf[MAX_STRING_LEN];

	zbx_snprintf(buf, sizeof(buf), ZBX_SYS_BLKDEV_PFX "%u:%u/device/serial", major(stat_buf->st_rdev),
			minor(stat_buf->st_rdev));

	if (NULL != (f = fopen(buf, "r")))
	{
		if (NULL != fgets(buf, sizeof(buf), f))
		{
			found = SUCCEED;
			zbx_lrtrim(buf, ZBX_WHITESPACE);
		}
		zbx_fclose(f);
	}

	zbx_json_addstring(j, "serial", (SUCCEED == found ? buf : ""), ZBX_JSON_TYPE_STRING);
}

static void	dev_wwn_add(const zbx_stat_t *stat_buf, struct zbx_json *j)
{
	FILE	*f;
	int	found = FAIL;
	char	buf[MAX_STRING_LEN];

	zbx_snprintf(buf, sizeof(buf), ZBX_SYS_BLKDEV_PFX "%u:%u/device/wwid", major(stat_buf->st_rdev),
			minor(stat_buf->st_rdev));

	if (NULL != (f = fopen(buf, "r")))
	{
		if (NULL != fgets(buf, sizeof(buf), f))
		{
			found = SUCCEED;
			zbx_lrtrim(buf, ZBX_WHITESPACE);
		}
		zbx_fclose(f);
	}

	zbx_json_addstring(j, "wwn", (SUCCEED == found ? buf : ""), ZBX_JSON_TYPE_STRING);
}

/******************************************************************************
 *                                                                            *
 * Comments: allocates memory                                                 *
 *                                                                            *
 ******************************************************************************/
static char	*dev_model_get(zbx_stat_t *stat_buf)
{
	FILE	*f;
	char	buf[MAX_STRING_LEN], *model = NULL;

	zbx_snprintf(buf, sizeof(buf), ZBX_SYS_BLKDEV_PFX "%u:%u/device/model", major(stat_buf->st_rdev),
			minor(stat_buf->st_rdev));

	if (NULL != (f = fopen(buf, "r")))
	{
		if (NULL != fgets(buf, sizeof(buf), f))
			model = zbx_strdup(NULL, buf);
		zbx_fclose(f);
	}

	if (NULL == model)
		model = zbx_strdup(NULL, "");
	else
		zbx_lrtrim(model, ZBX_WHITESPACE);

	return model;
}

static void	dev_size_bytes_add(const zbx_stat_t *stat_buf, struct zbx_json *j)
{
	FILE		*f;
	char		buf[MAX_STRING_LEN];
	zbx_uint64_t	size = 0;

	zbx_snprintf(buf, sizeof(buf), ZBX_SYS_BLKDEV_PFX "%u:%u/size", major(stat_buf->st_rdev),
			minor(stat_buf->st_rdev));

	if (NULL != (f = fopen(buf, "r")))
	{
		if (NULL != fgets(buf, sizeof(buf), f))
		{
			/* The size is in standard UNIX 512 byte blocks           */
			/* and it must be multiplied by 512 to get size in bytes. */
			zbx_lrtrim(buf, ZBX_WHITESPACE);
			ZBX_STR2UINT64(size, buf);
			size *= 512;
		}
		zbx_fclose(f);
	}

	zbx_json_adduint64(j, "size_bytes", size);
}

static void	dev_logical_blksize_add(const zbx_stat_t *stat_buf, struct zbx_json *j)
{
	FILE		*f;
	char		buf[MAX_STRING_LEN];
	zbx_uint64_t	size = 0;

	zbx_snprintf(buf, sizeof(buf), ZBX_SYS_BLKDEV_PFX "%u:%u/queue/logical_block_size", major(stat_buf->st_rdev),
			minor(stat_buf->st_rdev));

	if (NULL != (f = fopen(buf, "r")))
	{
		if (NULL != fgets(buf, sizeof(buf), f))
		{
			zbx_lrtrim(buf, ZBX_WHITESPACE);
			ZBX_STR2UINT64(size, buf);
		}
		zbx_fclose(f);
	}

	zbx_json_adduint64(j, "logical_block_size", size);
}

static void	dev_physical_blksize_add(const zbx_stat_t *stat_buf, struct zbx_json *j)
{
	FILE		*f;
	char		buf[MAX_STRING_LEN];
	zbx_uint64_t	size = 0;

	zbx_snprintf(buf, sizeof(buf), ZBX_SYS_BLKDEV_PFX "%u:%u/queue/physical_block_size", major(stat_buf->st_rdev),
			minor(stat_buf->st_rdev));

	if (NULL != (f = fopen(buf, "r")))
	{
		if (NULL != fgets(buf, sizeof(buf), f))
		{
			zbx_lrtrim(buf, ZBX_WHITESPACE);
			ZBX_STR2UINT64(size, buf);
		}
		zbx_fclose(f);
	}

	zbx_json_adduint64(j, "physical_block_size", size);
}

static void	dev_stats_add(const zbx_stat_t *stat_buf, struct zbx_json *j)
{
	FILE		*f;
	char		buf[MAX_STRING_LEN];
	zbx_dev_stats_t	stats = {0};

	zbx_snprintf(buf, sizeof(buf), ZBX_SYS_BLKDEV_PFX "%u:%u/stat", major(stat_buf->st_rdev),
			minor(stat_buf->st_rdev));

	if (NULL != (f = fopen(buf, "r")))
	{

		if (NULL != fgets(buf, sizeof(buf), f))
		{
			char	*tok, *saveptr;
			int	tok_idx = 0;

			tok = strtok_r(buf, " ", &saveptr);

			while (NULL != tok && 10 >= tok_idx)
			{
				switch (tok_idx)
				{
					case 0:
						stats.reads_completed = (zbx_uint64_t)strtoul(buf, NULL, 10);
						break;
					case 2:
						/* units: sectors, must be multiplied by 512 to convert to bytes */
						stats.bytes_read = (zbx_uint64_t)strtoul(buf, NULL, 10) * 512;
						break;
					case 4:
						stats.writes_completed = (zbx_uint64_t)strtoul(buf, NULL, 10);
						break;
					case 6:
						/* units: sectors, must be multiplied by 512 to convert to bytes */
						stats.bytes_written = (zbx_uint64_t)strtoul(buf, NULL, 10) * 512;
						break;
					case 10:
						stats.io_time_ms = (zbx_uint64_t)strtoul(buf, NULL, 10);
						break;
				}

				tok = strtok_r(NULL, " ", &saveptr);
				tok_idx++;
			}
		}
		zbx_fclose(f);
	}

	zbx_json_addobject(j, "stats");
	zbx_json_adduint64(j, "reads_completed", stats.reads_completed);
	zbx_json_adduint64(j, "writes_completed", stats.writes_completed);
	zbx_json_adduint64(j, "bytes_read", stats.bytes_read);
	zbx_json_adduint64(j, "bytes_written", stats.bytes_written);
	zbx_json_adduint64(j, "io_time_ms", stats.io_time_ms);
	zbx_json_close(j);
}

static void	dev_disk_partition_sizes_add(zbx_stat_t *stat_buf, struct zbx_json *j)
{
	DIR		*dir;
	struct dirent	*entry;
	char		buf[MAX_STRING_LEN];

	zbx_snprintf(buf, sizeof(buf), ZBX_SYS_BLKDEV_PFX "%u:%u", major(stat_buf->st_rdev),
			minor(stat_buf->st_rdev));

	zbx_json_addobject(j, "partitions");

	if (NULL == (dir = opendir(buf)))
		return;

	while (NULL != (entry = readdir(dir)))
	{
		FILE		*f = NULL;
		zbx_stat_t	st;

		zbx_snprintf(buf, sizeof(buf), ZBX_SYS_BLKDEV_PFX "%u:%u/%s/partition", major(stat_buf->st_rdev),
				minor(stat_buf->st_rdev), entry->d_name);

		/* partition directories should contain a text file named "partition" */
		if (0 != zbx_stat(buf, &st) || 0 == S_ISREG(st.st_mode))
			continue;

		zbx_snprintf(buf, sizeof(buf), ZBX_SYS_BLKDEV_PFX "%u:%u/%s/size", major(stat_buf->st_rdev),
				minor(stat_buf->st_rdev), entry->d_name);

		if (NULL != (f = fopen(buf, "r")) && NULL != fgets(buf, sizeof(buf), f))
		{
			/* The size is in standard UNIX 512 byte blocks           */
			/* and it must be multiplied by 512 to get size in bytes. */
			zbx_json_adduint64(j, entry->d_name, (zbx_uint64_t)strtoul(buf, NULL, 10) * 512);
		}

		zbx_fclose(f);
	}

	closedir(dir);
	zbx_json_close(j);
}

static void	device_free(zbx_device_t *device)
{
	zbx_free(device->devid);
	zbx_free(device->name);
	zbx_free(device);
}

static int	device_compare(const void *d1, const void *d2)
{
	const zbx_device_t	*p1 = *(zbx_device_t * const *)d1;
	const zbx_device_t	*p2 = *(zbx_device_t * const *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(p1->major, p2->major);
	ZBX_RETURN_IF_NOT_EQUAL(p1->minor, p2->minor);

	return strcmp(p1->devid, p2->devid);
}

static void	devids_init(zbx_vector_device_ptr_t *devices)
{
	DIR		*dir;
	struct dirent	*entry;
	char		buf[MAX_STRING_LEN];

	zbx_vector_device_ptr_create(devices);

	if (NULL == (dir = opendir(ZBX_SYS_DISK_BYID_PFX)))
		return;

	while (NULL != (entry = readdir(dir)))
	{
		zbx_device_t	*device;
		zbx_stat_t	stat_buf;
		char		*real;

		if (0 == strcmp(entry->d_name, ".") || 0 == strcmp(entry->d_name, ".."))
			continue;

		zbx_snprintf(buf, sizeof(buf), ZBX_SYS_DISK_BYID_PFX "%s", entry->d_name);

		if (NULL == (real = realpath(buf, NULL)))
			continue;

		if (0 != zbx_stat(real, &stat_buf) || 0 == S_ISBLK(stat_buf.st_mode))
		{
			zbx_free(real);
			continue;
		}

		device = zbx_malloc(NULL, sizeof(zbx_device_t));

		device->major = major(stat_buf.st_rdev);
		device->minor = minor(stat_buf.st_rdev);
		device->devid = zbx_strdup(NULL, entry->d_name);
		device->name = zbx_strdup(NULL, basename(real));

		zbx_vector_device_ptr_append(devices, device);

		zbx_free(real);
	}

	closedir(dir);

	if (NULL != devices && 0 < devices->values_num)
		zbx_vector_device_ptr_sort(devices, device_compare);
}

/******************************************************************************
 *                                                                            *
 * Purpose: adds device ID in format like in /dev/disk/by-id/                 *
 *                                                                            *
 * Parameters: devices  [IN]  - cache of pre-sorted devices with IDs          *
 *           : stat_buf [IN]  - needed to identify current device by          *
 *                              major-id and minor-id                         *
 *             model    [IN]                                                  *
 *             json     [out]                                                 *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 * When more than one device ID is present for the particular device,         *
 * the device ID containing the model of the device is preferred since it is  *
 * human readable.                                                            *
 *                                                                            *
 * The algorithm of getting device IDs is the following:                      *
 * 1. Read all names of the symlinks in /dev/disk/by-id/. Treat them as       *
 * device IDs and get <major-ID> and <minor-ID> for each of them.             *
 * 2. Sort devices by <major-ID> and <minor-ID>.                              *
 * Sort IDs of the same device in lexicographical order.                      *
 * 3. If there is only one device ID for the symlink, then use it.            *
 * 4. Else try to find the device ID which contains device model.             *
 *    The device ID containing the model of the device is preferred since it  *
 *    is human readable.                                                      *
 *   4.1. Get the model from                                                  *
 *        /sys/dev/block/<major-ID>:<minor-ID>/device/model                   *
 *   4.2. Take the continuous part of the model from the left which contains  *
 *        only the following symbols: alphanumeric, digits, dots and spaces.  *
 *        Replace spaces by underscores in model.                             *
 *   4.3. Choose the device ID which contains the model if found.             *
 * 5. Else take the first symlink for this device.                            *
 *                                                                            *
 ******************************************************************************/
static void	vfs_dev_get_devid_add(const zbx_vector_device_ptr_t *devices, const zbx_stat_t *stat_buf,
		const char *model, struct zbx_json *json)
{
	int	i, j, l = -1, r = -1, match_idx = -1;
	char	*devid = "", norm_model[MAX_STRING_LEN];

	if (0 == devices->values_num)
		goto out;

	for (i = 0; i < devices->values_num; i++)
	{
		zbx_device_t	*d = devices->values[i];

		if (major(stat_buf->st_rdev) == d->major && minor(stat_buf->st_rdev) == d->minor)
		{
			if (-1 == l)
				l = i;

			r = i;
		}
		else if (-1 != l)
			break;
	}

	if (-1 == l)
		goto out;

	if (l == r || 0 == strlen(model))
	{
		devid = devices->values[l]->devid;
		goto out;
	}

	i = j = 0;

	while ('\0' != model[i] && j < (int)sizeof(norm_model) - 1)
	{
		char c = model[i];

		if (0 != isalnum(c) || '.' == c || ' ' == c)
		{
			norm_model[j] = (' ' == c ? '_' : c);
			i++;
			j++;
		}
		else
			break;
	}

	norm_model[j] = '\0';

	for (i = l; i <= r; i++)
	{
		if (NULL != strstr(devices->values[i]->devid, norm_model))
		{
			match_idx = i;
			break;
		}
	}

	if (-1 != match_idx)
		devid = devices->values[match_idx]->devid;
	else
		devid = devices->values[l]->devid;
out:
	zbx_json_addstring(json, "devid", devid, ZBX_JSON_TYPE_STRING);
}

static int	dev_is_disk(const char *dev_type)
{
	if (0 == strcmp("disk", dev_type) || 0 == strcmp("rom", dev_type))
		return SUCCEED;

	return FAIL;
}

static int	dev_is_partition(const char *dev_type)
{
	if (0 == strcmp("partition", dev_type))
		return SUCCEED;

	return FAIL;
}

static void	vfs_dev_get_process_entry(const char *dev_name, const zbx_regexp_t *devnames_rxp, int mode,
		zbx_vector_device_ptr_t *devices, struct zbx_json *cfg, struct zbx_json *val)
{
	zbx_stat_t	stat_buf;
	char		*type, *model;

	if (NULL != devnames_rxp && 0 != zbx_regexp_match_precompiled(dev_name, devnames_rxp))
		return;

	if (NULL == (type = dev_type_get(dev_name, 1, &stat_buf)))
		return;

	switch (mode)
	{
		case ZBX_MODE_DISKS:
			if (SUCCEED != dev_is_disk(type))
				break;

			model = dev_model_get(&stat_buf);
			zbx_json_addobject(cfg, NULL);

			dev_name_add(dev_name, cfg);
			vfs_dev_get_devid_add(devices, &stat_buf, model, cfg);
			dev_type_add(type, cfg);
			dev_path_add(dev_name, cfg);
			dev_model_add(model, cfg);
			dev_serial_add(&stat_buf, cfg);
			dev_wwn_add(&stat_buf, cfg);
			dev_size_bytes_add(&stat_buf, cfg);
			dev_logical_blksize_add(&stat_buf, cfg);
			dev_physical_blksize_add(&stat_buf, cfg);

			zbx_json_close(cfg);
			zbx_free(model);
			break;
		case ZBX_MODE_DISK_STATS:
			if (SUCCEED != dev_is_disk(type))
				break;

			zbx_json_addobject(cfg, NULL);
			zbx_json_addobject(val, NULL);

			dev_name_add(dev_name, cfg);
			vfs_dev_get_devid_add(devices, &stat_buf, model, cfg);
			dev_type_add(type, cfg);
			dev_size_bytes_add(&stat_buf, cfg);

			dev_name_add(dev_name, val);
			dev_stats_add(&stat_buf, val);

			zbx_json_close(cfg);
			zbx_json_close(val);
			break;
		case ZBX_MODE_DEVICES:
			if (SUCCEED != dev_is_disk(type))
				break;

			model = dev_model_get(&stat_buf);
			zbx_json_addobject(cfg, NULL);

			dev_name_add(dev_name, cfg);
			vfs_dev_get_devid_add(devices, &stat_buf, model, cfg);
			dev_type_add(type, cfg);
			dev_disk_partition_sizes_add(&stat_buf, cfg);

			zbx_json_close(cfg);
			zbx_free(model);
			break;
		case ZBX_MODE_DEVICE_STATS:
			if (SUCCEED != dev_is_disk(type) && SUCCEED != dev_is_partition(type))
				break;

			model = dev_model_get(&stat_buf);
			zbx_json_addobject(cfg, NULL);
			zbx_json_addobject(val, NULL);

			dev_name_add(dev_name, cfg);
			vfs_dev_get_devid_add(devices, &stat_buf, model, cfg);
			dev_type_add(type, cfg);
			dev_size_bytes_add(&stat_buf, cfg);

			dev_name_add(dev_name, val);
			dev_stats_add(&stat_buf, val);

			zbx_json_close(cfg);
			zbx_json_close(val);
			zbx_free(model);
			break;
	}

	zbx_free(type);
}

/******************************************************************************
 *                                                                            *
 * Purpose: discovers disks and partitions                                    *
 *                                                                            *
 * Return value: JSON                                                         *
 *                                                                            *
 ******************************************************************************/
int	vfs_dev_get(AGENT_REQUEST *request, AGENT_RESULT *result)
{

	char			*devnames, *mode, *rxp_error = NULL;
	int			has_vals, imode, ret = SYSINFO_RET_OK;
	DIR			*dir;
	zbx_stat_t		stat_buf;
	struct dirent		*entry;
	struct zbx_json		j, cfg, val;
	zbx_vector_device_ptr_t	devices;
	zbx_regexp_t		*devnames_rxp = NULL;

	if (2 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	mode = get_rparam(request, 0);
	devnames = get_rparam(request, 1);

	if (NULL == mode || '\0' == *mode || 0 == strcmp(mode, "disks"))	/* default parameter */
	{
		imode = ZBX_MODE_DISKS;
		has_vals = 0;
	}
	else if (0 == strcmp(mode, "disk_stats"))
	{
		imode = ZBX_MODE_DISK_STATS;
		has_vals = 1;
	}
	else if (0 == strcmp(mode, "devices"))
	{
		imode = ZBX_MODE_DEVICES;
		has_vals = 0;
	}
	else if (0 == strcmp(mode, "device_stats"))
	{
		imode = ZBX_MODE_DEVICE_STATS;
		has_vals = 1;
	}
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		ret = SYSINFO_RET_FAIL;
		goto clean;
	}

	if (NULL != devnames && '\0' != *devnames && SUCCEED != zbx_regexp_compile(devnames, &devnames_rxp, &rxp_error))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Invalid regular expression in second parameter: %s",
				rxp_error));

		zbx_free(rxp_error);
		ret = SYSINFO_RET_FAIL;
		goto clean;
	}

	/* check if sysfs with block devices is available */
	if (0 != zbx_stat(ZBX_SYS_BLKDEV_PFX, &stat_buf) || 0 == S_ISDIR(stat_buf.st_mode))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain device information: directory \""
				ZBX_SYS_BLKDEV_PFX "\" is not found."));
		ret = SYSINFO_RET_FAIL;
		goto clean;
	}

	if (NULL == (dir = opendir(ZBX_DEV_PFX)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL,
				"Cannot obtain device list: failed to open \"" ZBX_DEV_PFX "\" directory."));
		ret = SYSINFO_RET_FAIL;
		goto clean;
	}

	devids_init(&devices);

	zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);
	zbx_json_initarray(&cfg, ZBX_JSON_STAT_BUF_LEN);
	if (1 == has_vals)
		zbx_json_initarray(&val, ZBX_JSON_STAT_BUF_LEN);

	while (NULL != (entry = readdir(dir)))
		vfs_dev_get_process_entry(entry->d_name, devnames_rxp, imode, &devices, &cfg, &val);
	closedir(dir);

	zbx_json_addraw(&j, "config", cfg.buffer);
	if (1 == has_vals)
		zbx_json_addraw(&j, "values", val.buffer);

	SET_STR_RESULT(result, zbx_strdup(NULL, j.buffer));

	zbx_json_free(&cfg);
	if (1 == has_vals)
		zbx_json_free(&val);
	zbx_json_free(&j);
	zbx_vector_device_ptr_clear_ext(&devices, device_free);
	zbx_vector_device_ptr_destroy(&devices);
clean:
	if (NULL != devnames_rxp)
		zbx_regexp_free(devnames_rxp);

	return ret;
}
