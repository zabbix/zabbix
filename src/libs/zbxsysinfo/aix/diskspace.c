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
#include "../sysinfo.h"

#include "inodes.h"

#include "zbxjson.h"
#include "zbxalgo.h"

#include <sys/vfs.h>
#include <sys/vmount.h>

static int	get_fs_size_stat(const char *fs, zbx_uint64_t *total, zbx_uint64_t *free,
		zbx_uint64_t *used, double *pfree, double *pused, char **error)
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
	{
		*error = zbx_dsprintf(NULL, "Cannot obtain filesystem information: %s", zbx_strerror(errno));
		zabbix_log(LOG_LEVEL_DEBUG,"%s failed with error: %s",__func__, *error);
		return SYSINFO_RET_FAIL;
	}

	/* Available space could be negative (top bit set) if we hit disk space */
	/* reserved for non-privileged users. Treat it as 0.                    */
	if (0 != ZBX_IS_TOP_BIT_SET(s.f_bavail))
		s.f_bavail = 0;

	if (NULL != total)
		*total = (zbx_uint64_t)s.f_blocks * s.ZBX_BSIZE;

	if (NULL != free)
		*free = (zbx_uint64_t)s.f_bavail * s.ZBX_BSIZE;

	if (NULL != used)
		*used = (zbx_uint64_t)(s.f_blocks - s.f_bfree) * s.ZBX_BSIZE;

	if (NULL != pfree)
	{
		if (0 != s.f_blocks - s.f_bfree + s.f_bavail)
			*pfree = (double)(100.0 * s.f_bavail) / (s.f_blocks - s.f_bfree + s.f_bavail);
		else
			*pfree = 0;
	}

	if (NULL != pused)
	{
		if (0 != s.f_blocks - s.f_bfree + s.f_bavail)
			*pused = 100.0 - (double)(100.0 * s.f_bavail) / (s.f_blocks - s.f_bfree + s.f_bavail);
		else
			*pused = 0;
	}

	return SYSINFO_RET_OK;
#undef ZBX_STATFS
#undef ZBX_BSIZE
}

static int	vfs_fs_used(const char *fs, AGENT_RESULT *result)
{
	zbx_uint64_t	value;
	char		*error;

	if (SYSINFO_RET_OK != get_fs_size_stat(fs, NULL, NULL, &value, NULL, NULL, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	SET_UI64_RESULT(result, value);

	return SYSINFO_RET_OK;
}

static int	vfs_fs_free(const char *fs, AGENT_RESULT *result)
{
	zbx_uint64_t	value;
	char		*error;

	if (SYSINFO_RET_OK != get_fs_size_stat(fs, NULL, &value, NULL, NULL, NULL, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	SET_UI64_RESULT(result, value);

	return SYSINFO_RET_OK;
}

static int	vfs_fs_total(const char *fs, AGENT_RESULT *result)
{
	zbx_uint64_t	value;
	char		*error;

	if (SYSINFO_RET_OK != get_fs_size_stat(fs, &value, NULL, NULL, NULL, NULL, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	SET_UI64_RESULT(result, value);

	return SYSINFO_RET_OK;
}

static int	vfs_fs_pfree(const char *fs, AGENT_RESULT *result)
{
	double	value;
	char	*error;

	if (SYSINFO_RET_OK != get_fs_size_stat(fs, NULL, NULL, NULL, &value, NULL, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	SET_DBL_RESULT(result, value);

	return SYSINFO_RET_OK;
}

static int	vfs_fs_pused(const char *fs, AGENT_RESULT *result)
{
	double	value;
	char	*error;

	if (SYSINFO_RET_OK != get_fs_size_stat(fs, NULL, NULL, NULL, NULL, &value, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	SET_DBL_RESULT(result, value);

	return SYSINFO_RET_OK;
}

static int	vfs_fs_size_local(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*fsname, *mode;

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

	if (NULL == mode || '\0' == *mode || 0 == strcmp(mode, "total"))	/* default parameter */
		return vfs_fs_total(fsname, result);
	if (0 == strcmp(mode, "free"))
		return vfs_fs_free(fsname, result);
	if (0 == strcmp(mode, "pfree"))
		return vfs_fs_pfree(fsname, result);
	if (0 == strcmp(mode, "used"))
		return vfs_fs_used(fsname, result);
	if (0 == strcmp(mode, "pused"))
		return vfs_fs_pused(fsname, result);

	SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));

	return SYSINFO_RET_FAIL;
}

int	vfs_fs_size(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	return zbx_execute_threaded_metric(vfs_fs_size_local, request, result);
}

static const char	*zbx_get_vfs_name_by_type(int type)
{
	struct vfs_ent		*vfs;
	static char		**vfs_names = NULL;
	static size_t		vfs_names_alloc = 0;

	if (type + 1 > vfs_names_alloc)
	{
		size_t	num = type + 1;

		vfs_names = zbx_realloc(vfs_names, sizeof(char *) * num);
		memset(vfs_names + vfs_names_alloc, 0, sizeof(char *) * (num - vfs_names_alloc));
		vfs_names_alloc = num;
	}

	if (NULL == vfs_names)
		return "unknown";

	if (NULL == vfs_names[type] && NULL != (vfs = getvfsbytype(type)))
		vfs_names[type] = zbx_strdup(vfs_names[type], vfs->vfsent_name);

	return NULL != vfs_names[type] ? vfs_names[type] : "unknown";
}

int	vfs_fs_discovery(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	int		rc, sz, i, ret = SYSINFO_RET_FAIL;
	struct vmount	*vms = NULL, *vm;
	struct zbx_json	j;

	ZBX_UNUSED(request);

	/* check how many bytes to allocate for the mounted filesystems */
	if (-1 == (rc = mntctl(MCTL_QUERY, sizeof(sz), (char *)&sz)))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain system information: %s", zbx_strerror(errno)));
		return SYSINFO_RET_FAIL;
	}

	sz *= 2;
	vms = zbx_malloc(vms, (size_t)sz);

	/* get the list of mounted filesystems */
	/* return code is number of filesystems returned */
	if (-1 == (rc = mntctl(MCTL_QUERY, sz, (char *)vms)))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain system information: %s", zbx_strerror(errno)));
		goto error;
	}

	zbx_json_initarray(&j, ZBX_JSON_STAT_BUF_LEN);

	for (i = 0, vm = vms; i < rc; i++)
	{
		zbx_json_addobject(&j, NULL);
		zbx_json_addstring(&j, ZBX_LLD_MACRO_FSNAME, (char *)vm + vm->vmt_data[VMT_STUB].vmt_off,
				ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&j, ZBX_LLD_MACRO_FSTYPE, zbx_get_vfs_name_by_type(vm->vmt_gfstype),
				ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&j, ZBX_LLD_MACRO_FSOPTIONS, (char *)vm + vm->vmt_data[VMT_ARGS].vmt_off,
				ZBX_JSON_TYPE_STRING);
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

static int	vfs_fs_get_local(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	int			rc, sz, i, ret = SYSINFO_RET_FAIL;
	struct vmount		*vms = NULL, *vm;
	struct zbx_json		j;
	zbx_uint64_t		total, not_used, used, itotal, inot_used, iused;
	double			pfree, pused, ipfree, ipused;
	char			*error;
	zbx_vector_ptr_t	mntpoints;
	zbx_mpoint_t		*mntpoint;
	zbx_fsname_t		fsname;

	ZBX_UNUSED(request);

	/* check how many bytes to allocate for the mounted filesystems */
	if (-1 == (rc = mntctl(MCTL_QUERY, sizeof(sz), (char *)&sz)))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain system information: %s", zbx_strerror(errno)));
		return SYSINFO_RET_FAIL;
	}

	zbx_vector_ptr_create(&mntpoints);

	sz *= 2;
	vms = zbx_malloc(vms, (size_t)sz);

	/* get the list of mounted filesystems */
	/* return code is number of filesystems returned */
	if (-1 == (rc = mntctl(MCTL_QUERY, sz, (char *)vms)))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain system information: %s", zbx_strerror(errno)));
		goto out;
	}
	for (i = 0, vm = vms; i < rc; i++)
	{
		char	*mntopts;

		fsname.mpoint = (char *)vm + vm->vmt_data[VMT_STUB].vmt_off;
		mntopts = (char *)vm + vm->vmt_data[VMT_ARGS].vmt_off;

		if (SYSINFO_RET_OK != get_fs_size_stat(fsname.mpoint, &total, &not_used, &used, &pfree, &pused, &error))
		{
			zbx_free(error);
			continue;
		}
		if (SYSINFO_RET_OK != get_fs_inode_stat(fsname.mpoint, &itotal, &inot_used, &iused, &ipfree, &ipused, "pused",
				&error))
		{
			zbx_free(error);
			continue;
		}

		mntpoint = (zbx_mpoint_t *)zbx_malloc(NULL, sizeof(zbx_mpoint_t));
		zbx_strlcpy(mntpoint->fsname, fsname.mpoint, MAX_STRING_LEN);
		zbx_strlcpy(mntpoint->fstype, zbx_get_vfs_name_by_type(vm->vmt_gfstype), MAX_STRING_LEN);
		mntpoint->bytes.total = total;
		mntpoint->bytes.used = used;
		mntpoint->bytes.not_used = not_used;
		mntpoint->bytes.pfree = pfree;
		mntpoint->bytes.pused = pused;
		mntpoint->inodes.total = itotal;
		mntpoint->inodes.used = iused;
		mntpoint->inodes.not_used = inot_used;
		mntpoint->inodes.pfree = ipfree;
		mntpoint->inodes.pused = ipused;
		mntpoint->options = zbx_strdup(NULL, mntopts);

		zbx_vector_ptr_append(&mntpoints, mntpoint);

		/* go to the next vmount structure */
		vm = (struct vmount *)((char *)vm + vm->vmt_length);
	}

	if (-1 == (rc = mntctl(MCTL_QUERY, sz, (char *)vms)))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain system information: %s", zbx_strerror(errno)));
		goto out;
	}

	zbx_json_initarray(&j, ZBX_JSON_STAT_BUF_LEN);

	for (i = 0, vm = vms; i < rc; i++)
	{
		int	idx;
		char	type[MAX_STRING_LEN];

		fsname.mpoint = (char *)vm + vm->vmt_data[VMT_STUB].vmt_off;
		zbx_strlcpy(type, zbx_get_vfs_name_by_type(vm->vmt_gfstype), MAX_STRING_LEN);
		fsname.type = type;

		if (FAIL != (idx = zbx_vector_ptr_search(&mntpoints, &fsname, zbx_fsname_compare)))
		{
			mntpoint = (zbx_mpoint_t *)mntpoints.values[idx];
			zbx_json_addobject(&j, NULL);
			zbx_json_addstring(&j, ZBX_SYSINFO_TAG_FSNAME, mntpoint->fsname, ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&j, ZBX_SYSINFO_TAG_FSTYPE, mntpoint->fstype, ZBX_JSON_TYPE_STRING);
			zbx_json_addobject(&j, ZBX_SYSINFO_TAG_BYTES);
			zbx_json_adduint64(&j, ZBX_SYSINFO_TAG_TOTAL, mntpoint->bytes.total);
			zbx_json_adduint64(&j, ZBX_SYSINFO_TAG_FREE, mntpoint->bytes.not_used);
			zbx_json_adduint64(&j, ZBX_SYSINFO_TAG_USED, mntpoint->bytes.used);
			zbx_json_addfloat(&j, ZBX_SYSINFO_TAG_PFREE, mntpoint->bytes.pfree);
			zbx_json_addfloat(&j, ZBX_SYSINFO_TAG_PUSED, mntpoint->bytes.pused);
			zbx_json_close(&j);
			zbx_json_addobject(&j, ZBX_SYSINFO_TAG_INODES);
			zbx_json_adduint64(&j, ZBX_SYSINFO_TAG_TOTAL, mntpoint->inodes.total);
			zbx_json_adduint64(&j, ZBX_SYSINFO_TAG_FREE, mntpoint->inodes.not_used);
			zbx_json_adduint64(&j, ZBX_SYSINFO_TAG_USED, mntpoint->inodes.used);
			zbx_json_addfloat(&j, ZBX_SYSINFO_TAG_PFREE, mntpoint->inodes.pfree);
			zbx_json_addfloat(&j, ZBX_SYSINFO_TAG_PUSED, mntpoint->inodes.pused);
			zbx_json_close(&j);
			zbx_json_addstring(&j, ZBX_SYSINFO_TAG_FSOPTIONS, mntpoint->options, ZBX_JSON_TYPE_STRING);
			zbx_json_close(&j);
		}

		/* go to the next vmount structure */
		vm = (struct vmount *)((char *)vm + vm->vmt_length);
	}

	zbx_json_close(&j);

	SET_STR_RESULT(result, zbx_strdup(NULL, j.buffer));

	zbx_json_free(&j);

	ret = SYSINFO_RET_OK;
out:
	zbx_free(vms);
	zbx_vector_ptr_clear_ext(&mntpoints, (zbx_clean_func_t)zbx_mpoints_free);
	zbx_vector_ptr_destroy(&mntpoints);

	return ret;
}

int	vfs_fs_get(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	return zbx_execute_threaded_metric(vfs_fs_get_local, request, result);
}
