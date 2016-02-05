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

static int	get_fs_inodes_stat(const char *fs, zbx_uint64_t *total, zbx_uint64_t *free, zbx_uint64_t *used, double *pfree, double *pused)
{
#ifdef HAVE_SYS_STATVFS_H
	struct statvfs	s;
#else
	struct statfs	s;
#endif

	assert(fs);

#ifdef HAVE_SYS_STATVFS_H
	if (0 != statvfs(fs, &s))
#else
	if (0 != statfs(fs, &s))
#endif
	{
		return SYSINFO_RET_FAIL;
	}

#ifdef HAVE_SYS_STATVFS_H
	if (total)
		*total = (zbx_uint64_t)s.f_files;
	if (free)
		*free = (zbx_uint64_t)s.f_ffree;
	if (used)
		*used = (zbx_uint64_t)(s.f_files - s.f_ffree);
	if (pfree)
		*pfree = (double)(100.0 * s.f_ffree) / s.f_files;
	if (pused)
		*pused = (double)(100.0 * (s.f_files - s.f_ffree)) / s.f_files;
#else
	if (total)
		*total = (zbx_uint64_t)s.f_files;
	if (free)
		*free = (zbx_uint64_t)s.f_ffree;
	if (used)
		*used = (zbx_uint64_t)(s.f_files - s.f_ffree);
	if (pfree)
		*pfree = (double)(100.0 * s.f_ffree) / s.f_files;
	if (pused)
		*pused = (double)(100.0 * (s.f_files - s.f_ffree)) / s.f_files;
#endif
	return SYSINFO_RET_OK;
}

static int	VFS_FS_INODE_USED(const char *fs, AGENT_RESULT *result)
{
	zbx_uint64_t	value = 0;

	if (SYSINFO_RET_OK != get_fs_inodes_stat(fs, NULL, NULL, &value, NULL, NULL))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, value);

	return SYSINFO_RET_OK;
}

static int	VFS_FS_INODE_FREE(const char *fs, AGENT_RESULT *result)
{
	zbx_uint64_t	value = 0;

	if (SYSINFO_RET_OK != get_fs_inodes_stat(fs, NULL, &value, NULL, NULL, NULL))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, value);

	return SYSINFO_RET_OK;
}

static int	VFS_FS_INODE_TOTAL(const char *fs, AGENT_RESULT *result)
{
	zbx_uint64_t	value = 0;

	if (SYSINFO_RET_OK != get_fs_inodes_stat(fs, &value, NULL, NULL, NULL, NULL))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, value);

	return SYSINFO_RET_OK;
}

static int	VFS_FS_INODE_PFREE(const char *fs, AGENT_RESULT *result)
{
	double	value = 0;

	if (SYSINFO_RET_OK != get_fs_inodes_stat(fs, NULL, NULL, NULL, &value, NULL))
		return SYSINFO_RET_FAIL;

	SET_DBL_RESULT(result, value);

	return SYSINFO_RET_OK;
}

static int	VFS_FS_INODE_PUSED(const char *fs, AGENT_RESULT *result)
{
	double	value = 0;

	if (SYSINFO_RET_OK != get_fs_inodes_stat(fs, NULL, NULL, NULL, NULL, &value))
		return SYSINFO_RET_FAIL;

	SET_DBL_RESULT(result, value);

	return SYSINFO_RET_OK;
}

int	VFS_FS_INODE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	const MODE_FUNCTION	fl[] =
	{
		{"free",	VFS_FS_INODE_FREE},
		{"total",	VFS_FS_INODE_TOTAL},
		{"used",	VFS_FS_INODE_USED},
		{"pfree",	VFS_FS_INODE_PFREE},
		{"pused",	VFS_FS_INODE_PUSED},
		{NULL,		0}
	};

	char	fsname[MAX_STRING_LEN];
	char	mode[MAX_STRING_LEN];
	int	i;

	if (num_param(param) > 2)
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, fsname, sizeof(mode)))
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 2, mode, sizeof(mode)))
		*mode = '\0';

	/* default parameter */
	if (*mode == '\0')
		zbx_snprintf(mode, sizeof(mode), "total");

	for (i = 0; fl[i].mode != 0; i++)
		if (0 == strncmp(mode, fl[i].mode, MAX_STRING_LEN))
			return (fl[i].function)(fsname, result);

	return SYSINFO_RET_FAIL;
}
