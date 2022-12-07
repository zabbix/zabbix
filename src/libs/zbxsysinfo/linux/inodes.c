/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

#include "inodes.h"

#include "common.h"
#include "sysinfo.h"
#include "log.h"

#define get_string(field)	#field

#define validate(error, structure, field)								\
													\
do													\
{													\
	if (__UINT64_C(0xffffffffffffffff) == structure.field)						\
	{												\
		error =  zbx_strdup(NULL, "Cannot obtain filesystem information: value of " 		\
				get_string(field) " is unknown.");					\
		return SYSINFO_RET_FAIL;								\
	}												\
}													\
while(0)

int	get_fs_inode_stat(const char *fs, zbx_uint64_t *itotal, zbx_uint64_t *ifree, zbx_uint64_t *iused, double *pfree,
		double *pused, const char *mode, char **error)
{
#ifdef HAVE_SYS_STATVFS_H
#	define ZBX_STATFS	statvfs
#	define ZBX_FFREE	f_favail
#else
#	define ZBX_STATFS	statfs
#	define ZBX_FFREE	f_ffree
#endif
	zbx_uint64_t		total;
	struct ZBX_STATFS	s;

	if (0 != ZBX_STATFS(fs, &s))
	{
		*error = zbx_dsprintf(NULL, "Cannot obtain filesystem information: %s", zbx_strerror(errno));
		return SYSINFO_RET_FAIL;
	}

	validate(*error, s, f_files);
	*itotal = (zbx_uint64_t)s.f_files;

	validate(*error, s, ZBX_FFREE);
	*ifree = (zbx_uint64_t)s.ZBX_FFREE;

	validate(*error, s, f_ffree);
	*iused =  (zbx_uint64_t)(s.f_files - s.f_ffree);

	total = s.f_files;
#ifdef HAVE_SYS_STATVFS_H
	validate(*error, s, f_favail);
	total -= s.f_ffree - s.f_favail;
#endif
	if (0 != total)
	{
		*pfree = (100.0 *  s.ZBX_FFREE) / total;
		*pused = (100.0 * (total - s.ZBX_FFREE)) / total;
	}
	else if (NULL != mode && (0 == strcmp(mode, "pfree") || 0 == strcmp(mode, "pused")))
	{
		*pfree = 100.0;
		*pused = 0.0;
	}

	return SYSINFO_RET_OK;
}

static int	vfs_fs_inode(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char			*fsname, *mode, *error;
	zbx_uint64_t 		total, free, used;
	double 			pfree, pused;

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

	if (SYSINFO_RET_OK != get_fs_inode_stat(fsname, &total, &free, &used, &pfree, &pused, mode, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	if (NULL == mode || '\0' == *mode || 0 == strcmp(mode, "total"))	/* default parameter */
	{
		SET_UI64_RESULT(result, total);
	}
	else if (0 == strcmp(mode, "free"))
	{
		SET_UI64_RESULT(result, free);
	}
	else if (0 == strcmp(mode, "used"))
	{
		SET_UI64_RESULT(result, used);
	}
	else if (0 == strcmp(mode, "pfree"))
	{
		SET_DBL_RESULT(result, pfree);
	}
	else if (0 == strcmp(mode, "pused"))
	{
		SET_DBL_RESULT(result, pused);
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
