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

#include "zbxstr.h"
#include "zbxjson.h"
#include "zbxalgo.h"
#include "zbxlog.h"

typedef struct
{
	char		*fsname;
	char		*fstype;
	char		*fslabel;
	char		*fsdrivetype;
	zbx_uint64_t	total;
	zbx_uint64_t	not_used;
	zbx_uint64_t	used;
	double		pfree;
	double		pused;
}
zbx_wmpoint_t;


static wchar_t	*zbx_wcsdup2(const char *filename, int line, wchar_t *old, const wchar_t *str)
{
	wchar_t	*ptr = NULL;

	zbx_free(old);

	for (int retry = 10; 0 < retry && NULL == ptr; ptr = wcsdup(str), retry--)
		;

	if (NULL != ptr)
		return ptr;

	zabbix_log(LOG_LEVEL_CRIT, "[file:%s,line:%d] zbx_wcsdup: out of memory. Requested " ZBX_FS_SIZE_T " bytes.",
			filename, line, (zbx_fs_size_t)((wcslen(str) + 1) * sizeof(wchar_t)));

	exit(EXIT_FAILURE);
}

static int	wmpoint_compare_func(const void *d1, const void *d2)
{
	const zbx_wmpoint_t	*m1 = *(const zbx_wmpoint_t **)d1;
	const zbx_wmpoint_t	*m2 = *(const zbx_wmpoint_t **)d2;

	return strcmp(m1->fsname, m2->fsname);
}

static int	get_fs_size_stat(const char *fs, zbx_uint64_t *total, zbx_uint64_t *not_used,
		zbx_uint64_t *used, double *pfree, double *pused, char **error)
{
	wchar_t		*wpath;
	ULARGE_INTEGER	freeBytes, totalBytes;

	wpath = zbx_utf8_to_unicode(fs);
	if (0 == GetDiskFreeSpaceEx(wpath, &freeBytes, &totalBytes, NULL))
	{
		zbx_free(wpath);
		*error = zbx_dsprintf(NULL, "Cannot obtain filesystem information: %s",
				zbx_strerror_from_system(GetLastError()));
		zabbix_log(LOG_LEVEL_DEBUG,"%s failed with error: %s",__func__, *error);
		return SYSINFO_RET_FAIL;
	}
	zbx_free(wpath);

	*total = totalBytes.QuadPart;
	*not_used = freeBytes.QuadPart;

	if (0 != totalBytes.QuadPart)
	{
		*used = totalBytes.QuadPart - freeBytes.QuadPart;
		*pfree = (double)(__int64)freeBytes.QuadPart * 100. / (double)(__int64)totalBytes.QuadPart;
		*pused = (double)((__int64)totalBytes.QuadPart - (__int64)freeBytes.QuadPart) * 100. /
				(double)(__int64)totalBytes.QuadPart;
	}
	else
	{
		*used = 0;
		*pfree = 0;
		*pused = 0;
	}

	return SYSINFO_RET_OK;

}

static int	vfs_fs_size_local(AGENT_REQUEST *request, AGENT_RESULT *result, HANDLE timeout_event)
{
	char		*path, *mode, *error;
	zbx_uint64_t	total, used, free;
	double		pused, pfree;

	/* 'timeout_event' argument is here to make the vfs_fs_size() prototype as required by */
	/* zbx_execute_threaded_metric() on MS Windows */
	ZBX_UNUSED(timeout_event);

	if (2 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	path = get_rparam(request, 0);
	mode = get_rparam(request, 1);

	if (NULL == path || '\0' == *path)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		return SYSINFO_RET_FAIL;
	}

	if (SYSINFO_RET_OK != get_fs_size_stat(path, &total, &free, &used, &pfree, &pused, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	if (NULL == mode || '\0' == *mode || 0 == strcmp(mode, "total"))
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

static const char	*get_drive_type_string(UINT type)
{
	switch (type)
	{
		case DRIVE_UNKNOWN:
			return "unknown";
		case DRIVE_NO_ROOT_DIR:
			return "norootdir";
		case DRIVE_REMOVABLE:
			return "removable";
		case DRIVE_FIXED:
			return "fixed";
		case DRIVE_REMOTE:
			return "remote";
		case DRIVE_CDROM:
			return "cdrom";
		case DRIVE_RAMDISK:
			return "ramdisk";
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			return "unknown";
	}
}

static void	get_fs_data(const wchar_t* path, char **fsname, char **fstype, char **fslabel, char **fsdrivetype)
{
	wchar_t	fs_name[MAX_PATH + 1], vol_name[MAX_PATH + 1], *long_path = NULL;
	size_t	sz;

	*fsname = zbx_unicode_to_utf8(path);
	if (0 < (sz = strlen(*fsname)) && '\\' == (*fsname)[--sz])
		(*fsname)[sz] = '\0';

	/* add \\?\ prefix if path exceeds MAX_PATH */
	if (MAX_PATH < (sz = wcslen(path) + 1) && 0 != wcsncmp(path, L"\\\\?\\", 4))
	{
		/* allocate memory buffer enough to hold null-terminated path and prefix */
		long_path = (wchar_t*)zbx_malloc(long_path, (sz + 4) * sizeof(wchar_t));

		long_path[0] = L'\\';
		long_path[1] = L'\\';
		long_path[2] = L'?';
		long_path[3] = L'\\';

		memcpy(long_path + 4, path, sz * sizeof(wchar_t));
		path = long_path;
	}

	if (FALSE != GetVolumeInformation(path, vol_name, ARRSIZE(vol_name), NULL, NULL, NULL, fs_name,
			ARRSIZE(fs_name)))
	{
		*fstype = zbx_unicode_to_utf8(fs_name);
		*fslabel = zbx_unicode_to_utf8(vol_name);
	}
	else
	{
		*fstype = zbx_strdup(NULL, "UNKNOWN");
		*fslabel = zbx_strdup(NULL, "");
	}

	*fsdrivetype = zbx_strdup(NULL, get_drive_type_string(GetDriveType(path)));

	zbx_free(long_path);
}

static int	add_fs_to_vector(zbx_vector_ptr_t *mntpoints, wchar_t *path, char **error)
{
	zbx_wmpoint_t	*mntpoint;
	zbx_uint64_t	total, not_used, used;
	double		pfree, pused;
	char		*fsname = NULL, *fstype = NULL, *fslabel = NULL, *fsdrivetype = NULL;

	get_fs_data(path, &fsname, &fstype, &fslabel, &fsdrivetype);

	if (SYSINFO_RET_OK != get_fs_size_stat(fsname, &total, &not_used, &used, &pfree, &pused, error))
	{
		zbx_free(fsname);
		zbx_free(fstype);
		zbx_free(fsdrivetype);
		return FAIL;
	}

	mntpoint = (zbx_wmpoint_t *)zbx_malloc(NULL, sizeof(zbx_wmpoint_t));
	mntpoint->fsname = fsname;
	mntpoint->fstype = fstype;
	mntpoint->fslabel = fslabel;
	mntpoint->fsdrivetype = fsdrivetype;
	mntpoint->total = total;
	mntpoint->not_used = not_used;
	mntpoint->used = used;
	mntpoint->pfree = pfree;
	mntpoint->pused = pused;
	zbx_vector_ptr_append(mntpoints, mntpoint);

	return SUCCEED;
}

static void	zbx_wmpoints_free(zbx_wmpoint_t *mpoint)
{
	zbx_free(mpoint->fsname);
	zbx_free(mpoint->fstype);
	zbx_free(mpoint->fslabel);
	zbx_free(mpoint->fsdrivetype);
	zbx_free(mpoint);
}

static int	get_mount_paths(zbx_vector_ptr_t *mount_paths, char **error)
{
#define zbx_wcsdup(old, str)		zbx_wcsdup2(__FILE__, __LINE__, old, str)
	wchar_t	*buffer = NULL, volume_name[MAX_PATH + 1], *p;
	DWORD	size_dw, last_error;
	HANDLE	volume = INVALID_HANDLE_VALUE;
	size_t	sz;
	int	ret = FAIL;

	/* make an initial call to GetLogicalDriveStrings() to get the necessary size into the dwSize variable */
	if (0 == (size_dw = GetLogicalDriveStrings(0, buffer)))
	{
		*error = zbx_strdup(*error, "Cannot obtain necessary buffer size from system.");
		return FAIL;
	}

	buffer = (wchar_t *)zbx_malloc(buffer, (size_dw + 1) * sizeof(wchar_t));

	/* make a second call to GetLogicalDriveStrings() to get the actual data we require */
	if (0 == (size_dw = GetLogicalDriveStrings(size_dw, buffer)))
	{
		*error = zbx_strdup(*error, "Cannot obtain necessary buffer size from system.");
		goto out;
	}

	/* add drive letters */
	for (p = buffer, sz = wcslen(p); sz > 0; p += sz + 1, sz = wcslen(p))
		zbx_vector_ptr_append(mount_paths, zbx_wcsdup(NULL, p));

	if (INVALID_HANDLE_VALUE == (volume = FindFirstVolume(volume_name, ARRSIZE(volume_name))))
	{
		*error = zbx_strdup(*error, "Cannot find a volume.");
		goto out;
	}

	/* search volumes for mount point folder paths */
	do
	{
		while (FALSE == GetVolumePathNamesForVolumeName(volume_name, buffer, size_dw, &size_dw))
		{
			if (ERROR_MORE_DATA != (last_error = GetLastError()))
			{
				char	*volume = zbx_unicode_to_utf8(volume_name);

				*error = zbx_dsprintf(*error, "Cannot obtain a list of filesystems. Volume: %s Error: %s",
						volume, zbx_strerror_from_system(last_error));
				zbx_free(volume);
				goto out;
			}

			buffer = (wchar_t*)zbx_realloc(buffer, size_dw * sizeof(wchar_t));
		}

		for (p = buffer, sz = wcslen(p); sz > 0; p += sz + 1, sz = wcslen(p))
		{
			/* add mount point folder paths but skip drive letters */
			if (3 < sz)
				zbx_vector_ptr_append(mount_paths, zbx_wcsdup(NULL, p));
		}

	} while (FALSE != FindNextVolume(volume, volume_name, ARRSIZE(volume_name)));

	if (ERROR_NO_MORE_FILES != (last_error = GetLastError()))
	{
		*error = zbx_dsprintf(*error, "Cannot obtain complete list of filesystems.",
				zbx_strerror_from_system(last_error));
		goto out;
	}

	ret = SUCCEED;
out:
	if (INVALID_HANDLE_VALUE != volume)
		FindVolumeClose(volume);

	zbx_free(buffer);

	return ret;
#undef zbx_wcsdup
}

int	vfs_fs_discovery(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	struct zbx_json		j;
	int			ret = SYSINFO_RET_FAIL;
	zbx_vector_ptr_t	mount_paths;
	char			*error = NULL, *fsname, *fstype, *fslabel, *fsdrivetype;

	zbx_vector_ptr_create(&mount_paths);
	zbx_json_initarray(&j, ZBX_JSON_STAT_BUF_LEN);

	if (FAIL == get_mount_paths(&mount_paths, &error))
	{
		SET_MSG_RESULT(result, error);
		goto out;
	}

	for (int i = 0; i < mount_paths.values_num; i++)
	{
		get_fs_data(mount_paths.values[i], &fsname, &fstype, &fslabel, &fsdrivetype);

		zbx_json_addobject(&j, NULL);
		zbx_json_addstring(&j, ZBX_LLD_MACRO_FSNAME, fsname, ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&j, ZBX_LLD_MACRO_FSTYPE, fstype, ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&j, ZBX_LLD_MACRO_FSLABEL, fslabel, ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&j, ZBX_LLD_MACRO_FSDRIVETYPE, fsdrivetype, ZBX_JSON_TYPE_STRING);
		zbx_json_close(&j);

		zbx_free(fsname);
		zbx_free(fstype);
		zbx_free(fsdrivetype);
	}

	zbx_json_close(&j);

	SET_STR_RESULT(result, zbx_strdup(NULL, j.buffer));
	ret = SYSINFO_RET_OK;
out:
	zbx_vector_ptr_clear_ext(&mount_paths, (zbx_clean_func_t)zbx_ptr_free);
	zbx_vector_ptr_destroy(&mount_paths);

	zbx_json_free(&j);

	return ret;
}

static int	vfs_fs_get_local(AGENT_REQUEST *request, AGENT_RESULT *result, HANDLE timeout_event)
{
	size_t			sz;
	struct zbx_json		j;
	zbx_vector_ptr_t	mntpoints;
	zbx_wmpoint_t		*mpoint;
	int			ret = SYSINFO_RET_FAIL;
	char			*error = NULL;
	zbx_vector_ptr_t	mount_paths;

	zbx_vector_ptr_create(&mount_paths);
	zbx_vector_ptr_create(&mntpoints);
	zbx_json_initarray(&j, ZBX_JSON_STAT_BUF_LEN);

	if (FAIL == get_mount_paths(&mount_paths, &error))
	{
		SET_MSG_RESULT(result, error);
		goto out;
	}

	/* 'timeout_event' argument is here to make the vfs_fs_size() prototype as required by */
	/* zbx_execute_threaded_metric() on MS Windows */
	ZBX_UNUSED(timeout_event);

	for (int i = 0; i < mount_paths.values_num; i++)
	{
		if (FAIL == add_fs_to_vector(&mntpoints, mount_paths.values[i], &error))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s", error);
			zbx_free(error);
			continue;
		}
	}

	zbx_vector_ptr_clear_ext(&mount_paths, (zbx_clean_func_t)zbx_ptr_free);
	if (FAIL == get_mount_paths(&mount_paths, &error))
	{
		SET_MSG_RESULT(result, error);
		goto out;
	}

	for (int i = 0; i < mount_paths.values_num; i++)
	{
		zbx_wmpoint_t	mpoint_local;
		int		idx;

		mpoint_local.fsname = zbx_unicode_to_utf8(mount_paths.values[i]);
		if (0 < (sz = strlen(mpoint_local.fsname)) && '\\' == mpoint_local.fsname[--sz])
			mpoint_local.fsname[sz] = '\0';

		if (FAIL != (idx = zbx_vector_ptr_search(&mntpoints, &mpoint_local, wmpoint_compare_func)))
		{
			mpoint = (zbx_wmpoint_t *)mntpoints.values[idx];
			zbx_json_addobject(&j, NULL);
			zbx_json_addstring(&j, ZBX_SYSINFO_TAG_FSNAME, mpoint->fsname, ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&j, ZBX_SYSINFO_TAG_FSTYPE, mpoint->fstype, ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&j, ZBX_SYSINFO_TAG_FSLABEL, mpoint->fslabel, ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&j, ZBX_SYSINFO_TAG_FSDRIVETYPE, mpoint->fsdrivetype, ZBX_JSON_TYPE_STRING);
			zbx_json_addobject(&j, ZBX_SYSINFO_TAG_BYTES);
			zbx_json_adduint64(&j, ZBX_SYSINFO_TAG_TOTAL, mpoint->total);
			zbx_json_adduint64(&j, ZBX_SYSINFO_TAG_FREE, mpoint->not_used);
			zbx_json_adduint64(&j, ZBX_SYSINFO_TAG_USED, mpoint->used);
			zbx_json_addfloat(&j, ZBX_SYSINFO_TAG_PFREE, mpoint->pfree);
			zbx_json_addfloat(&j, ZBX_SYSINFO_TAG_PUSED, mpoint->pused);
			zbx_json_close(&j);
			zbx_json_close(&j);
		}
		zbx_free(mpoint_local.fsname);
	}

	zbx_json_close(&j);

	SET_STR_RESULT(result, zbx_strdup(NULL, j.buffer));
	ret = SYSINFO_RET_OK;
out:
	zbx_vector_ptr_clear_ext(&mount_paths, (zbx_clean_func_t)zbx_ptr_free);
	zbx_vector_ptr_destroy(&mount_paths);
	zbx_vector_ptr_clear_ext(&mntpoints, (zbx_clean_func_t)zbx_wmpoints_free);
	zbx_vector_ptr_destroy(&mntpoints);
	zbx_json_free(&j);

	return ret;
}

int	vfs_fs_get(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	return zbx_execute_threaded_metric(vfs_fs_get_local, request, result);
}
