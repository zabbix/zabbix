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

#include "../sysinfo.h"

#include "inodes.h"

#include "zbxjson.h"
#include "zbxalgo.h"

static int	get_fs_size_stat(const char *fs, zbx_uint64_t *total, zbx_uint64_t *free,
		zbx_uint64_t *used, double *pfree, double *pused, char **error)
{
#ifdef HAVE_SYS_STATVFS_H
#	define ZBX_STATFS	statvfs
#	define ZBX_BSIZE	f_frsize
#else
#	define ZBX_STATFS	statfs
#	define ZBX_BSIZE	f_bsize
#endif
	struct ZBX_STATFS	s;

	if (NULL == fs || '\0' == *fs)
	{
		*error = zbx_strdup(NULL, "Filesystem name cannot be empty.");
		zabbix_log(LOG_LEVEL_DEBUG,"%s failed with error: %s",__func__, *error);
		return SYSINFO_RET_FAIL;
	}

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

static int	vfs_fs_size_local(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char		*fsname, *mode, *error;
	zbx_uint64_t	total, free, used;
	double		pfree, pused;

	if (2 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	fsname = get_rparam(request, 0);
	mode = get_rparam(request, 1);

	if (SYSINFO_RET_OK != get_fs_size_stat(fsname, &total, &free, &used, &pfree, &pused, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	if (NULL == mode || '\0' == *mode || 0 == strcmp(mode, "total"))	/* default parameter */
		SET_UI64_RESULT(result, total);
	else if (0 == strcmp(mode, "free"))
		SET_UI64_RESULT(result, free);
	else if (0 == strcmp(mode, "used"))
		SET_UI64_RESULT(result, used);
	else if (0 == strcmp(mode, "pfree"))
		SET_DBL_RESULT(result, pfree);
	else if (0 == strcmp(mode, "pused"))
		SET_DBL_RESULT(result, pused);
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		return SYSINFO_RET_FAIL;
	}

	return SYSINFO_RET_OK;
}

int	vfs_fs_size(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	return zbx_execute_threaded_metric(vfs_fs_size_local, request, result);
}

int	vfs_fs_discovery(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char		line[MAX_STRING_LEN], *p, *mpoint, *mtype, *mntopts;
	FILE		*f;
	struct zbx_json	j;

	ZBX_UNUSED(request);

	if (NULL == (f = fopen("/proc/mounts", "r")))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot open /proc/mounts: %s", zbx_strerror(errno)));
		return SYSINFO_RET_FAIL;
	}

	zbx_json_initarray(&j, ZBX_JSON_STAT_BUF_LEN);

	while (NULL != fgets(line, sizeof(line), f))
	{
		if (NULL == (p = strchr(line, ' ')))
			continue;

		mpoint = ++p;

		if (NULL == (p = strchr(mpoint, ' ')))
			continue;

		*p = '\0';

		mtype = ++p;

		if (NULL == (p = strchr(mtype, ' ')))
			continue;

		*p = '\0';

		mntopts = ++p;

		if (NULL == (p = strchr(mntopts, ' ')))
			continue;

		*p = '\0';

		zbx_json_addobject(&j, NULL);
		zbx_json_addstring(&j, ZBX_LLD_MACRO_FSNAME, mpoint, ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&j, ZBX_LLD_MACRO_FSTYPE, mtype, ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&j, ZBX_LLD_MACRO_FSOPTIONS, mntopts, ZBX_JSON_TYPE_STRING);
		zbx_json_close(&j);
	}

	zbx_fclose(f);

	zbx_json_close(&j);

	SET_STR_RESULT(result, zbx_strdup(NULL, j.buffer));

	zbx_json_free(&j);

	return SYSINFO_RET_OK;
}

static int	vfs_fs_get_local(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char			line[MAX_STRING_LEN], *p, *mntopts, *error;
	FILE			*f;
	zbx_uint64_t		total, not_used, used, itotal, inot_used, iused;
	double			pfree, pused, ipfree, ipused;
	struct zbx_json		j;
	zbx_vector_ptr_t	mntpoints;
	zbx_mpoint_t		*mntpoint;
	zbx_fsname_t		fsname;
	int			ret = SYSINFO_RET_FAIL;

	ZBX_UNUSED(request);

	if (NULL == (f = fopen("/proc/mounts", "r")))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot open /proc/mounts: %s", zbx_strerror(errno)));
		return SYSINFO_RET_FAIL;
	}

	zbx_vector_ptr_create(&mntpoints);

	while (NULL != fgets(line, sizeof(line), f))
	{
		if (NULL == (p = strchr(line, ' ')))
			continue;

		fsname.mpoint = ++p;

		if (NULL == (p = strchr(fsname.mpoint, ' ')))
			continue;

		*p = '\0';

		fsname.type = ++p;

		if (NULL == (p = strchr(fsname.type, ' ')))
			continue;

		*p = '\0';

		mntopts = ++p;

		if (NULL == (p = strchr(mntopts, ' ')))
			continue;

		*p = '\0';

		if (SYSINFO_RET_OK != get_fs_size_stat(fsname.mpoint, &total, &not_used, &used, &pfree, &pused, &error))
		{
			zbx_free(error);
			continue;
		}
		if (SYSINFO_RET_OK != get_fs_inode_stat(fsname.mpoint, &itotal, &inot_used, &iused, &ipfree, &ipused,
				"pused", &error))
		{
			zbx_free(error);
			continue;
		}

		mntpoint = (zbx_mpoint_t *)zbx_malloc(NULL, sizeof(zbx_mpoint_t));
		zbx_strlcpy(mntpoint->fsname, fsname.mpoint, MAX_STRING_LEN);
		zbx_strlcpy(mntpoint->fstype, fsname.type, MAX_STRING_LEN);
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
	}
	zbx_fclose(f);

	if (NULL == (f = fopen("/proc/mounts", "r")))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot open /proc/mounts: %s", zbx_strerror(errno)));
		goto out;
	}
	zbx_json_initarray(&j, ZBX_JSON_STAT_BUF_LEN);

	while (NULL != fgets(line, sizeof(line), f))
	{
		int idx;

		if (NULL == (p = strchr(line, ' ')))
			continue;

		fsname.mpoint = ++p;

		if (NULL == (p = strchr(fsname.mpoint, ' ')))
			continue;

		*p = '\0';

		fsname.type = ++p;

		if (NULL == (p = strchr(fsname.type, ' ')))
			continue;

		*p = '\0';

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
	}

	zbx_fclose(f);

	zbx_json_close(&j);

	SET_STR_RESULT(result, zbx_strdup(NULL, j.buffer));

	zbx_json_free(&j);
	ret = SYSINFO_RET_OK;
out:
	zbx_vector_ptr_clear_ext(&mntpoints, (zbx_clean_func_t)zbx_mpoints_free);
	zbx_vector_ptr_destroy(&mntpoints);

	return ret;
}

int	vfs_fs_get(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	return zbx_execute_threaded_metric(vfs_fs_get_local, request, result);
}
