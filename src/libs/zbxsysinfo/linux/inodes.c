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
#include "log.h"

#define get_string(field)	#field

#define validate(result, structure, field)								\
													\
do													\
{													\
	if (__UINT64_C(0xffffffffffffffff) == structure.field)						\
	{												\
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain filesystem information:"		\
				" value of " get_string(field) " is unknown."));			\
		return SYSINFO_RET_FAIL;								\
	}												\
}													\
while(0)

static int	vfs_fs_inode(AGENT_REQUEST *request, AGENT_RESULT *result)
{
#ifdef HAVE_SYS_STATVFS_H
#	define ZBX_STATFS	statvfs
#	define ZBX_FFREE	f_favail
#else
#	define ZBX_STATFS	statfs
#	define ZBX_FFREE	f_ffree
#endif
	char			*fsname, *mode;
	zbx_uint64_t		total;
	struct ZBX_STATFS	s;

	if (2 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	fsname = get_rparam(request, 0);
	mode = get_rparam(request, 1);

	if (NULL == fsname || '\0' == *fsname)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		return SYSINFO_RET_FAIL;
	}

	if (0 != ZBX_STATFS(fsname, &s))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain filesystem information: %s",
				zbx_strerror(errno)));
		return SYSINFO_RET_FAIL;
	}

	if (NULL == mode || '\0' == *mode || 0 == strcmp(mode, "total"))	/* default parameter */
	{
		validate(result, s, f_files);
		SET_UI64_RESULT(result, s.f_files);
	}
	else if (0 == strcmp(mode, "free"))
	{
		validate(result, s, ZBX_FFREE);
		SET_UI64_RESULT(result, s.ZBX_FFREE);
	}
	else if (0 == strcmp(mode, "used"))
	{
		validate(result, s, f_files);
		validate(result, s, f_ffree);
		SET_UI64_RESULT(result, s.f_files - s.f_ffree);
	}
	else if (0 == strcmp(mode, "pfree"))
	{
		validate(result, s, f_files);
		total = s.f_files;
#ifdef HAVE_SYS_STATVFS_H
		validate(result, s, f_ffree);
		validate(result, s, f_favail);
		total -= s.f_ffree - s.f_favail;
#endif
		validate(result, s, ZBX_FFREE);

		if (0 != total)
		{
			SET_DBL_RESULT(result, (100.0 * s.ZBX_FFREE) / total);
		}
		else
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot calculate percentage because total is zero."));
			return SYSINFO_RET_FAIL;
		}
	}
	else if (0 == strcmp(mode, "pused"))
	{
		validate(result, s, f_files);
		total = s.f_files;
#ifdef HAVE_SYS_STATVFS_H
		validate(result, s, f_ffree);
		validate(result, s, f_favail);
		total -= s.f_ffree - s.f_favail;
#endif
		validate(result, s, ZBX_FFREE);

		if (0 != total)
		{
			SET_DBL_RESULT(result, (100.0 * (total - s.ZBX_FFREE)) / total);
		}
		else
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot calculate percentage because total is zero."));
			return SYSINFO_RET_FAIL;
		}
	}
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		return SYSINFO_RET_FAIL;
	}

	return SYSINFO_RET_OK;
}

int	VFS_FS_INODE(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	return zbx_execute_threaded_metric(vfs_fs_inode, request, result);
}
