/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "common.h"
#include "sysinfo.h"

static int	get_fs_inodes_stat(char *fs, double *total, double *free, double *usage)
{
#ifdef HAVE_SYS_STATVFS_H
	struct statvfs   s;
#else
	struct statfs   s;
#endif

	assert(fs);

#ifdef HAVE_SYS_STATVFS_H
	if ( statvfs( fs, &s) != 0 )
#else
	if ( statfs( fs, &s) != 0 )
#endif
	{
		return SYSINFO_RET_FAIL;
	}

	if(total)
		(*total) = (double)(s.f_files);
#ifdef HAVE_SYS_STATVFS_H
	if(free)
		(*free)  = (double)(s.f_favail);
	if(usage)
		(*usage) = (double)(s.f_files - s.f_favail);
#else
	if(free)
		(*free)  = (double)(s.f_ffree);
	if(usage)
		(*usage) = (double)(s.f_files - s.f_ffree);
#endif
	return SYSINFO_RET_OK;
}

static int	VFS_FS_INODE_USED(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	char 	mountPoint[MAX_STRING_LEN];
	double	value = 0;

        if(num_param(param) > 1)
                return SYSINFO_RET_FAIL;

        if(get_param(param, 1, mountPoint, MAX_STRING_LEN) != 0)
                return SYSINFO_RET_FAIL;

	if(get_fs_inodes_stat(mountPoint, NULL, NULL, &value) != SYSINFO_RET_OK)
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, value);

	return SYSINFO_RET_OK;
}

static int	VFS_FS_INODE_FREE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	char 	mountPoint[MAX_STRING_LEN];
	double	value = 0;

        if(num_param(param) > 1)
                return SYSINFO_RET_FAIL;

        if(get_param(param, 1, mountPoint, MAX_STRING_LEN) != 0)
                return SYSINFO_RET_FAIL;

	if(get_fs_inodes_stat(mountPoint, NULL, &value, NULL) != SYSINFO_RET_OK)
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, value);

	return SYSINFO_RET_OK;
}

static int	VFS_FS_INODE_TOTAL(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	char 	mountPoint[MAX_STRING_LEN];
	double	value = 0;

        if(num_param(param) > 1)
                return SYSINFO_RET_FAIL;

        if(get_param(param, 1, mountPoint, MAX_STRING_LEN) != 0)
        {
                return SYSINFO_RET_FAIL;
        }

	if(get_fs_inodes_stat(mountPoint, &value, NULL, NULL) != SYSINFO_RET_OK)
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, value);

	return SYSINFO_RET_OK;
}

static int	VFS_FS_INODE_PFREE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	char 	mountPoint[MAX_STRING_LEN];
	double	tot_val = 0;
	double	free_val = 0;

        if(num_param(param) > 1)
                return SYSINFO_RET_FAIL;

        if(get_param(param, 1, mountPoint, MAX_STRING_LEN) != 0)
                return SYSINFO_RET_FAIL;

	if(get_fs_inodes_stat(mountPoint, &tot_val, &free_val, NULL) != SYSINFO_RET_OK)
		return SYSINFO_RET_FAIL;

	SET_DBL_RESULT(result, (100.0 * free_val) / tot_val);

	return SYSINFO_RET_OK;
}

static int	VFS_FS_INODE_PUSED(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	char 	mountPoint[MAX_STRING_LEN];
	double	tot_val = 0;
	double	usg_val = 0;

        if(num_param(param) > 1)
                return SYSINFO_RET_FAIL;

        if(get_param(param, 1, mountPoint, MAX_STRING_LEN) != 0)
                return SYSINFO_RET_FAIL;

	if(get_fs_inodes_stat(mountPoint, &tot_val, NULL, &usg_val) != SYSINFO_RET_OK)
		return SYSINFO_RET_FAIL;

	SET_DBL_RESULT(result, (100.0 * usg_val) / tot_val);

	return SYSINFO_RET_OK;
}

int	VFS_FS_INODE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	const MODE_FUNCTION	fl[] =
	{
		{"free" ,	VFS_FS_INODE_FREE},
		{"total" ,	VFS_FS_INODE_TOTAL},
		{"used",	VFS_FS_INODE_USED},
		{"pfree" ,	VFS_FS_INODE_PFREE},
		{"pused" ,	VFS_FS_INODE_PUSED},
		{NULL,		0}
	};

	char fsname[MAX_STRING_LEN];
	char mode[MAX_STRING_LEN];
	int i;

        if(num_param(param) > 2)
                return SYSINFO_RET_FAIL;

        if(get_param(param, 1, fsname, sizeof(fsname)) != 0)
                return SYSINFO_RET_FAIL;

	if(get_param(param, 2, mode, sizeof(mode)) != 0)
                mode[0] = '\0';

        if(mode[0] == '\0')
	{
		/* default parameter */
		zbx_snprintf(mode, sizeof(mode), "total");
	}

	for(i=0; fl[i].mode!=0; i++)
		if(strncmp(mode, fl[i].mode, MAX_STRING_LEN)==0)
			return (fl[i].function)(cmd, fsname, flags, result);

	return SYSINFO_RET_FAIL;
}
