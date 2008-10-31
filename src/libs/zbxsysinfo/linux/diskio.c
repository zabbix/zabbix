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

#if defined(KERNEL_2_4)
#	define INFO_FILE_NAME	"/proc/partitions"
#	define DEVNAME(line)	if(sscanf(line, "%*d %*d %*d %s ", name) != 1) continue
#	define PARSE(line)	if(sscanf(line, "%*d %*d %*d %s " \
					ZBX_FS_UI64 " %*d " ZBX_FS_UI64 " %*d " \
					ZBX_FS_UI64 " %*d " ZBX_FS_UI64 " %*d %*d %*d %*d", \
				name, 		\
				r_oper, 	\
				r_sect,	\
				w_oper, 	\
				w_sect	\
				) != 5) continue
#else
#	define INFO_FILE_NAME	"/proc/diskstats"
#	define DEVNAME(line)	if(sscanf(line, "%*d %*d %s ", name) != 1) continue
#	define PARSE(line)	if(sscanf(line, "%*d %*d %s " \
					ZBX_FS_UI64 " %*d " ZBX_FS_UI64 " %*d " \
					ZBX_FS_UI64 " %*d " ZBX_FS_UI64 " %*d %*d %*d %*d", \
				name,		\
				r_oper, 	\
				r_sect,	\
				w_oper, 	\
				w_sect	\
				) != 5) continue 
#endif

int	get_diskstat(const char *devname, time_t *now,
		zbx_uint64_t *r_oper, zbx_uint64_t *r_sect, zbx_uint64_t *w_oper, zbx_uint64_t *w_sect)
{
	FILE	*f;
	char	tmp[MAX_STRING_LEN], name[MAX_STRING_LEN];
	int	ret = FAIL;

	assert(devname);

	*now = time(NULL);

	if (NULL != (f = fopen(INFO_FILE_NAME, "r")))
	{
		while (NULL != fgets(tmp, sizeof(tmp), f))
		{
			PARSE(tmp);

			if (0 == strcmp(name, devname))
			{
				ret = SUCCEED;
				break;
			}
		}
		zbx_fclose(f);
	}

	return ret;
}

static ZBX_SINGLE_DISKDEVICE_DATA	*get_diskdevice(const char *devname)
{
	ZBX_SINGLE_DISKDEVICE_DATA	*device;
	FILE				*f;
	char				tmp[MAX_STRING_LEN], name[MAX_STRING_LEN];
	size_t				sz;

	assert(devname);

	if (NULL != (device = collector_diskdevice_get(devname)))
		return device;

	/* device exists? */
	if (NULL != (f = fopen(INFO_FILE_NAME, "r")))
	{
		while (NULL != fgets(tmp, sizeof(tmp), f))
		{
			DEVNAME(tmp);

			if (0 == strcmp(name, devname))
			{
				sz = strlen(devname);

				/* device is disk or partition */
				if (isdigit(devname[sz - 1]))
					break;

				device = collector_diskdevice_add(devname);
				break;
			}
		}
		zbx_fclose(f);
	}

	return device;
}

int	VFS_DEV_WRITE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	ZBX_SINGLE_DISKDEVICE_DATA *device;
	char	devname[MAX_STRING_LEN];
	char	type[16], tmp[16];
	int	mode, ret = SYSINFO_RET_OK;

        assert(result);

        init_result(result);

	if (!DISKDEVICE_COLLECTOR_STARTED(collector))
	{
		SET_MSG_RESULT(result, strdup("Collector is not started!"));
		return SYSINFO_RET_OK;
	}

        if (num_param(param) > 3)
                return SYSINFO_RET_FAIL;

        if (0 != get_param(param, 1, devname, sizeof(devname)))
                return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 2, type, sizeof(type)))
		*type = '\0';

	/* default parameter */
        if (*type == '\0')
		zbx_snprintf(type, sizeof(type), "sectors");

	if (0 != get_param(param, 3, tmp, sizeof(tmp)))
                *tmp = '\0';

	/* default parameter */
        if (*tmp == '\0')
		zbx_snprintf(tmp, sizeof(tmp), "avg1");

	if (0 == strcmp(tmp, "avg1"))
		mode = ZBX_AVG1;
	else if (0 == strcmp(tmp, "avg5"))
		mode = ZBX_AVG5;
	else if (0 == strcmp(tmp, "avg15"))
		mode = ZBX_AVG15;
	else
		return SYSINFO_RET_FAIL;

	if (NULL == (device = get_diskdevice(devname)))
		return SYSINFO_RET_FAIL;

	if (-1 == device->index)
	{
		SET_UI64_RESULT(result, 0);
	}
	else if (0 == strcmp(type, "sectors"))
	{
		SET_UI64_RESULT(result, device->w_sect[device->index]);
	}
	else if (0 == strcmp(type, "operations"))
	{
		SET_UI64_RESULT(result, device->w_oper[device->index]);
	}
	else if (0 == strcmp(type, "sps"))
	{
		SET_UI64_RESULT(result, device->w_sps[mode]);
	}
	else if (0 == strcmp(type, "ops"))
	{
		SET_UI64_RESULT(result, device->w_ops[mode]);
	}
	else
		ret = SYSINFO_RET_FAIL;

	return ret;
}

int	VFS_DEV_READ(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	ZBX_SINGLE_DISKDEVICE_DATA *device;
	char	devname[MAX_STRING_LEN];
	char	type[16], tmp[16];
	int	mode, ret = SYSINFO_RET_OK;

        assert(result);

        init_result(result);

	if (!DISKDEVICE_COLLECTOR_STARTED(collector))
	{
		SET_MSG_RESULT(result, strdup("Collector is not started!"));
		return SYSINFO_RET_OK;
	}

        if (num_param(param) > 3)
                return SYSINFO_RET_FAIL;

        if (0 != get_param(param, 1, devname, sizeof(devname)))
                return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 2, type, sizeof(type)))
		*type = '\0';

	/* default parameter */
        if (*type == '\0')
		zbx_snprintf(type, sizeof(type), "sectors");

	if (0 != get_param(param, 3, tmp, sizeof(tmp)))
                *tmp = '\0';

	/* default parameter */
        if (*tmp == '\0')
		zbx_snprintf(tmp, sizeof(tmp), "avg1");

	if (0 == strcmp(tmp, "avg1"))
		mode = ZBX_AVG1;
	else if (0 == strcmp(tmp, "avg5"))
		mode = ZBX_AVG5;
	else if (0 == strcmp(tmp, "avg15"))
		mode = ZBX_AVG15;
	else
		return SYSINFO_RET_FAIL;

	if (NULL == (device = get_diskdevice(devname)))
		return SYSINFO_RET_FAIL;

	if (-1 == device->index)
	{
		SET_UI64_RESULT(result, 0);
	}
	else if (0 == strcmp(type, "sectors"))
	{
		SET_UI64_RESULT(result, device->r_sect[device->index]);
	}
	else if (0 == strcmp(type, "operations"))
	{
		SET_UI64_RESULT(result, device->r_oper[device->index]);
	}
	else if (0 == strcmp(type, "sps"))
	{
		SET_UI64_RESULT(result, device->r_sps[mode]);
	}
	else if (0 == strcmp(type, "ops"))
	{
		SET_UI64_RESULT(result, device->r_ops[mode]);
	}
	else
		ret = SYSINFO_RET_FAIL;

	return ret;
}
