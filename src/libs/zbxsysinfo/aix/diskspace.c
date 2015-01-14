/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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
#	ifdef HAVE_SYS_STATVFS64
#		define ZBX_STATFS	statvfs64
#	else
#		define ZBX_STATFS	statvfs
#	endif
#	define ZBX_BSIZE	f_frsize
#else
#	ifdef HAVE_SYS_STATFS64
#		define ZBX_STATFS	statfs64
#	else
#		define ZBX_STATFS	statfs
#	endif
#	define ZBX_BSIZE	f_bsize
#endif
	struct ZBX_STATFS	s;

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

int	VFS_FS_SIZE(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*fsname, *mode;
	int	ret = SYSINFO_RET_FAIL;

	if (2 < request->nparam)
		return SYSINFO_RET_FAIL;

	fsname = get_rparam(request, 0);
	mode = get_rparam(request, 1);

	if (NULL == fsname || '\0' == *fsname)
		return ret;

	if (NULL == mode || '\0' == *mode || 0 == strcmp(mode, "total"))
		ret = VFS_FS_TOTAL(fsname, result);
	else if (0 == strcmp(mode, "free"))
		ret = VFS_FS_FREE(fsname, result);
	else if (0 == strcmp(mode, "pfree"))
		ret = VFS_FS_PFREE(fsname, result);
	else if (0 == strcmp(mode, "used"))
		ret = VFS_FS_USED(fsname, result);
	else if (0 == strcmp(mode, "pused"))
		ret = VFS_FS_PUSED(fsname, result);
	else
		ret = SYSINFO_RET_FAIL;

	return ret;
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

int	VFS_FS_DISCOVERY(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	int		rc, sz, i, ret = SYSINFO_RET_FAIL;
	struct vmount	*vms = NULL, *vm;
	struct zbx_json	j;

	/* check how many bytes to allocate for the mounted filesystems */
	if (-1 == (rc = mntctl(MCTL_QUERY, sizeof(sz), (char *)&sz)))
		return ret;

	vms = zbx_malloc(vms, (size_t)sz);

	/* get the list of mounted filesystems */
	/* return code is number of filesystems returned */
	if (-1 == (rc = mntctl(MCTL_QUERY, sz, (char *)vms)))
		goto error;

	zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);

	zbx_json_addarray(&j, ZBX_PROTO_TAG_DATA);

	for (i = 0, vm = vms; i < rc; i++)
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
	zbx_free(vms);

	return ret;
}
