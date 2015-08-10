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

static int	get_fs_inodes_stat(const char *fs, zbx_uint64_t *total, zbx_uint64_t *used, zbx_uint64_t *free)
{
#ifdef HAVE_SYS_STATVFS_H
#	define ZBX_STATFS	statvfs
#	define ZBX_FAVAIL	f_favail
#else
#	define ZBX_STATFS	statfs
#	define ZBX_FAVAIL	f_ffree
#endif
	struct ZBX_STATFS	s;

	if (0 != ZBX_STATFS(fs, &s))
		return SYSINFO_RET_FAIL;

	if (NULL != total)
		*total = s.f_files;
	if (NULL != used)
		*used = s.f_files - s.ZBX_FAVAIL;
	if (NULL != free)
		*free = s.ZBX_FAVAIL;

	return SYSINFO_RET_OK;
}

static int	VFS_FS_INODE_TOTAL(const char *fs, AGENT_RESULT *result)
{
	zbx_uint64_t	total;

	if (SYSINFO_RET_OK != get_fs_inodes_stat(fs, &total, NULL, NULL))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, total);

	return SYSINFO_RET_OK;
}

static int	VFS_FS_INODE_USED(const char *fs, AGENT_RESULT *result)
{
	zbx_uint64_t	used;

	if (SYSINFO_RET_OK != get_fs_inodes_stat(fs, NULL, &used, NULL))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, used);

	return SYSINFO_RET_OK;
}

static int	VFS_FS_INODE_PUSED(const char *fs, AGENT_RESULT *result)
{
	zbx_uint64_t	used, total;

	if (SYSINFO_RET_OK != get_fs_inodes_stat(fs, &total, &used, NULL))
		return SYSINFO_RET_FAIL;

	SET_DBL_RESULT(result, used / (double)total * 100);

	return SYSINFO_RET_OK;
}

static int	VFS_FS_INODE_FREE(const char *fs, AGENT_RESULT *result)
{
	zbx_uint64_t	free;

	if (SYSINFO_RET_OK != get_fs_inodes_stat(fs, NULL, NULL, &free))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, free);

	return SYSINFO_RET_OK;
}

static int	VFS_FS_INODE_PFREE(const char *fs, AGENT_RESULT *result)
{
	zbx_uint64_t	free, total;

	if (SYSINFO_RET_OK != get_fs_inodes_stat(fs, &total, NULL, &free))
		return SYSINFO_RET_FAIL;

	SET_DBL_RESULT(result, free / (double)total * 100);

	return SYSINFO_RET_OK;
}

int	VFS_FS_INODE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	const MODE_FUNCTION	fl[] =
	{
		{"total" ,	VFS_FS_INODE_TOTAL},
		{"used",	VFS_FS_INODE_USED},
		{"pused" ,	VFS_FS_INODE_PUSED},
		{"free" ,	VFS_FS_INODE_FREE},
		{"pfree" ,	VFS_FS_INODE_PFREE},
		{NULL,		0}
	};

	char	fsname[MAX_STRING_LEN], mode[8];
	int	i;

	if (2 < num_param(param))
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, fsname, sizeof(fsname)))
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 2, mode, sizeof(mode)) || '\0' == *mode)
		strscpy(mode, "total");

	for (i = 0; NULL != fl[i].mode; i++)
		if (0 == strcmp(mode, fl[i].mode))
			return (fl[i].function)(fsname, result);

	return SYSINFO_RET_FAIL;
}
