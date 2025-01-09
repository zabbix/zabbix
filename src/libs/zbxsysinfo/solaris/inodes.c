/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

#include "zbxsysinfo.h"
#include "inodes.h"
#include "../sysinfo.h"

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
		*error = zbx_dsprintf(NULL, "Cannot obtain filesystem information: %s",
				zbx_strerror(errno));
		return SYSINFO_RET_FAIL;
	}
	*itotal = (zbx_uint64_t)s.f_files;
	*ifree = (zbx_uint64_t)s.ZBX_FFREE;
	*iused =  (zbx_uint64_t)(s.f_files - s.f_ffree);
	total = s.f_files;
#ifdef HAVE_SYS_STATVFS_H
	total -= s.f_ffree - s.f_favail;
#endif
	if (0 != total)
	{
		*pfree = (100.0 *  s.ZBX_FFREE) / total;
		*pused = 100.0 - (double)(100.0 * s.ZBX_FFREE) / total;
	}
	else if (NULL != mode && (0 == strcmp(mode, "pfree") || 0 == strcmp(mode, "pused")))
	{
		*pfree = 100.0;
		*pused = 0.0;
	}

	return SYSINFO_RET_OK;
#undef ZBX_STATFS
#undef ZBX_FFREE
}

static int	vfs_fs_inode_local(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char			*fsname, *mode, *error;
	zbx_uint64_t		total, free, used;
	double			pfree, pused;

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

int	vfs_fs_inode(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	return zbx_execute_threaded_metric(vfs_fs_inode_local, request, result);
}
