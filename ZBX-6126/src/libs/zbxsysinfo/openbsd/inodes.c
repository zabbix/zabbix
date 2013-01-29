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

int	VFS_FS_INODE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#ifdef HAVE_SYS_STATVFS_H
#	define ZBX_STATFS	statvfs
#	define ZBX_FFREE	f_favail
#else
#	define ZBX_STATFS	statfs
#	define ZBX_FFREE	f_ffree
#endif
	char			fsname[MAX_STRING_LEN], mode[8];
	zbx_uint64_t		total;
	struct ZBX_STATFS	s;

	if (2 < num_param(param))
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, fsname, sizeof(fsname)))
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 2, mode, sizeof(mode)))
		*mode = '\0';

	if (0 != ZBX_STATFS(fsname, &s))
		return SYSINFO_RET_FAIL;

	if ('\0' == *mode || 0 == strcmp(mode, "total"))	/* default parameter */
	{
		SET_UI64_RESULT(result, s.f_files);
	}
	else if (0 == strcmp(mode, "free"))
	{
		SET_UI64_RESULT(result, s.ZBX_FFREE);
	}
	else if (0 == strcmp(mode, "used"))
	{
		SET_UI64_RESULT(result, s.f_files - s.f_ffree);
	}
	else if (0 == strcmp(mode, "pfree"))
	{
		total = s.f_files;
#ifdef HAVE_SYS_STATVFS_H
		total -= s.f_ffree - s.f_favail;
#endif
		if (0 != total)
			SET_DBL_RESULT(result, (double)(100.0 * s.ZBX_FFREE) / total);
		else
			return SYSINFO_RET_FAIL;
	}
	else if (0 == strcmp(mode, "pused"))
	{
		total = s.f_files;
#ifdef HAVE_SYS_STATVFS_H
		total -= s.f_ffree - s.f_favail;
#endif
		if (0 != total)
			SET_DBL_RESULT(result, 100.0 - (double)(100.0 * s.ZBX_FFREE) / total);
		else
			return SYSINFO_RET_FAIL;
	}
	else
		return SYSINFO_RET_FAIL;

	return SYSINFO_RET_OK;
}
