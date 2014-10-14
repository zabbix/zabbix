/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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

static int	get_fs_size_stat(const char *fs, zbx_uint64_t *total, zbx_uint64_t *free,
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

	assert(fs);

	if (0 != ZBX_STATFS(fs, &s))
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

static int	VFS_FS_USED(const char *fs, AGENT_RESULT *result)
{
	zbx_uint64_t	value = 0;

	if (SYSINFO_RET_OK != get_fs_size_stat(fs, NULL, NULL, &value, NULL, NULL))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, value);

	return SYSINFO_RET_OK;
}

static int	VFS_FS_FREE(const char *fs, AGENT_RESULT *result)
{
	zbx_uint64_t	value = 0;

	if (SYSINFO_RET_OK != get_fs_size_stat(fs, NULL, &value, NULL, NULL, NULL))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, value);

	return SYSINFO_RET_OK;
}

static int	VFS_FS_TOTAL(const char *fs, AGENT_RESULT *result)
{
	zbx_uint64_t	value = 0;

	if (SYSINFO_RET_OK != get_fs_size_stat(fs, &value, NULL, NULL, NULL, NULL))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, value);

	return SYSINFO_RET_OK;

}

static int	VFS_FS_PFREE(const char *fs, AGENT_RESULT *result)
{
	double	value = 0;

	if (SYSINFO_RET_OK != get_fs_size_stat(fs, NULL, NULL, NULL, &value, NULL))
		return SYSINFO_RET_FAIL;

	SET_DBL_RESULT(result, value);

	return SYSINFO_RET_OK;
}

static int	VFS_FS_PUSED(const char *fs, AGENT_RESULT *result)
{
	double	value = 0;

	if (SYSINFO_RET_OK != get_fs_size_stat(fs, NULL, NULL, NULL, NULL, &value))
		return SYSINFO_RET_FAIL;

	SET_DBL_RESULT(result, value);

	return SYSINFO_RET_OK;
}

int	VFS_FS_SIZE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	const MODE_FUNCTION	fl[] =
	{
		{"free",	VFS_FS_FREE},
		{"total",	VFS_FS_TOTAL},
		{"used",	VFS_FS_USED},
		{"pfree",	VFS_FS_PFREE},
		{"pused",	VFS_FS_PUSED},
		{NULL,		0}
	};

	char	fsname[MAX_STRING_LEN], mode[8];
	int	i;

	if (num_param(param) > 2)
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, fsname, sizeof(fsname)))
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 2, mode, sizeof(mode)))
		*mode = '\0';

	/* default parameter */
	if ('\0' == *mode)
		zbx_snprintf(mode, sizeof(mode), "total");

	for (i = 0; fl[i].mode != 0; i++)
		if (0 == strcmp(mode, fl[i].mode))
			return (fl[i].function)(fsname, result);

	return SYSINFO_RET_FAIL;
}

static const char	*zbx_get_vfs_name_by_type(int type)
{
	extern struct vfs_ent	*getvfsbytype(int type);

	struct vfs_ent	*vfs;
	static char	*vfs_names[MNT_AIXLAST + 1] = {};

	if (NULL == vfs_names[type] && NULL != (vfs = getvfsbytype(type)))
		vfs_names[type] = zbx_strdup(vfs_names[type], vfs->vfsent_name);

	return NULL != vfs_names[type] ? vfs_names[type] : "unknown";
}

int	VFS_FS_DISCOVERY(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	int		rc, sz, i, ret = SYSINFO_RET_FAIL;
	struct vmount	*vm = NULL;
	struct zbx_json	j;

	/* check how many bytes to allocate for the mounted filesystems */
	if (-1 == (rc = mntctl(MCTL_QUERY, sizeof(sz), (char *)&sz)))
		return ret;

	vm = zbx_malloc(vm, (size_t)sz);

	/* get the list of mounted filesystems */
	/* return code is number of filesystems returned */
	if (-1 == (rc = mntctl(MCTL_QUERY, sz, (char *)vm)))
		goto error;

	zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);

	zbx_json_addarray(&j, ZBX_PROTO_TAG_DATA);

	for (i = 0; i < rc; i++)
	{
		zbx_json_addobject(&j, NULL);
		zbx_json_addstring(&j, "{#FSNAME}", (char *)vm + vm->vmt_data[VMT_STUB].vmt_off, ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&j, "{#FSTYPE}", zbx_get_vfs_name_by_type(vm->vmt_gfstype), ZBX_JSON_TYPE_STRING);
		zbx_json_close(&j);

		/* go to the next vmount structure */
		vm = (struct vmount *)((char *)vm + vm->vmt_length);
	}

	zbx_json_close(&j);

	SET_STR_RESULT(result, zbx_strdup(NULL, j.buffer));

	zbx_json_free(&j);

	ret = SYSINFO_RET_OK;
error:
	zbx_free(vm);

	return ret;
}
