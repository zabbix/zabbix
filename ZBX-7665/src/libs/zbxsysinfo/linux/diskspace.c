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
#include "zbxjson.h"

static int	get_fs_size_stat(const char *fsname, zbx_uint64_t *total, zbx_uint64_t *free,
		zbx_uint64_t *used, double *pfree, double *pused)
{
#ifdef HAVE_SYS_STATVFS_H
#	define ZBX_STATFS	statvfs
#	define ZBX_BSIZE	f_frsize
#else
#	define ZBX_STATFS	statfs
#	define ZBX_BSIZE	f_bsize
#endif
	struct ZBX_STATFS	s;

	assert(fsname);

	if (0 != ZBX_STATFS(fsname, &s))
		return SYSINFO_RET_FAIL;

	if (total)
		*total = (zbx_uint64_t)s.f_blocks * s.ZBX_BSIZE;
	if (free)
		*free = (zbx_uint64_t)s.f_bavail * s.ZBX_BSIZE;
	if (used)
		*used = (zbx_uint64_t)(s.f_blocks - s.f_bfree) * s.ZBX_BSIZE;
	if (pfree)
	{
		if (0 != s.f_blocks - s.f_bfree + s.f_bavail)
			*pfree = (double)(100.0 * s.f_bavail) /
					(s.f_blocks - s.f_bfree + s.f_bavail);
		else
			*pfree = 0;
	}
	if (pused)
	{
		if (0 != s.f_blocks - s.f_bfree + s.f_bavail)
			*pused = 100.0 - (double)(100.0 * s.f_bavail) /
					(s.f_blocks - s.f_bfree + s.f_bavail);
		else
			*pused = 0;
	}

	return SYSINFO_RET_OK;
}

int	VFS_FS_SIZE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	char		fsname[MAX_STRING_LEN], mode[8];
	zbx_uint64_t	total, free, used;
	double		pfree, pused;

	if (num_param(param) > 2)
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, fsname, sizeof(fsname)))
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 2, mode, sizeof(mode)))
		*mode = '\0';

	if (SYSINFO_RET_OK != get_fs_size_stat(fsname, &total, &free, &used, &pfree, &pused))
		return SYSINFO_RET_FAIL;

	/* default parameter */
	if ('\0' == *mode || 0 == strcmp(mode, "total"))	/* default parameter */
		SET_UI64_RESULT(result, total);
	else if (0 == strcmp(mode, "free"))
		SET_UI64_RESULT(result, free);
	else if (0 == strcmp(mode, "used"))
		SET_UI64_RESULT(result, used);
	else if (0 == strcmp(mode, "pfree"))
		SET_DBL_RESULT(result, pfree);
	else if (0 == strcmp(mode, "pused"))
		SET_DBL_RESULT(result, pused);
	else
		return SYSINFO_RET_FAIL;

	return SYSINFO_RET_OK;
}

int	VFS_FS_DISCOVERY(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	int		ret = SYSINFO_RET_FAIL;
	char		line[MAX_STRING_LEN], *p, *mpoint, *mtype;
	FILE		*f;
	struct zbx_json	j;

	zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);

	zbx_json_addarray(&j, ZBX_PROTO_TAG_DATA);

	if (NULL != (f = fopen("/proc/mounts", "r")))
	{
		while (NULL != fgets(line, sizeof(line), f))
		{
			if (NULL == (p = strchr(line, ' ')))
				continue;

			mpoint = ++p;

			if (NULL == (p = strchr(mpoint, ' ')))
				continue;

			*p = '\0';

			mtype = ++p;

			if (NULL == (p = strchr(mtype, ' ')))
				continue;

			*p = '\0';

			zbx_json_addobject(&j, NULL);
			zbx_json_addstring(&j, "{#FSNAME}", mpoint, ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&j, "{#FSTYPE}", mtype, ZBX_JSON_TYPE_STRING);
			zbx_json_close(&j);
		}

		zbx_fclose(f);

		ret = SYSINFO_RET_OK;
	}

	zbx_json_close(&j);

	SET_STR_RESULT(result, zbx_strdup(NULL, j.buffer));

	zbx_json_free(&j);

	return ret;
}
