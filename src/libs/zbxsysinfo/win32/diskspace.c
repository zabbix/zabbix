/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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
#include "zbxjson.h"

static int	vfs_fs_size(AGENT_REQUEST *request, AGENT_RESULT *result, HANDLE timeout_event)
{
	char		*path, *mode;
	wchar_t 	*wpath;
	ULARGE_INTEGER	freeBytes, totalBytes;

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

	wpath = zbx_utf8_to_unicode(path);
	if (0 == GetDiskFreeSpaceEx(wpath, &freeBytes, &totalBytes, NULL))
	{
		zbx_free(wpath);
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain filesystem information."));
		return SYSINFO_RET_FAIL;
	}
	zbx_free(wpath);

	if (NULL == mode || '\0' == *mode || 0 == strcmp(mode, "total"))
		SET_UI64_RESULT(result, totalBytes.QuadPart);
	else if (0 == strcmp(mode, "free"))
		SET_UI64_RESULT(result, freeBytes.QuadPart);
	else if (0 == strcmp(mode, "used"))
		SET_UI64_RESULT(result, totalBytes.QuadPart - freeBytes.QuadPart);
	else if (0 == strcmp(mode, "pfree"))
		SET_DBL_RESULT(result, (double)(__int64)freeBytes.QuadPart * 100. / (double)(__int64)totalBytes.QuadPart);
	else if (0 == strcmp(mode, "pused"))
		SET_DBL_RESULT(result, (double)((__int64)totalBytes.QuadPart - (__int64)freeBytes.QuadPart) * 100. /
				(double)(__int64)totalBytes.QuadPart);
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		return SYSINFO_RET_FAIL;
	}

	return SYSINFO_RET_OK;
}

int	VFS_FS_SIZE(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	return zbx_execute_threaded_metric(vfs_fs_size, request, result);
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

static void	add_fs_to_json(wchar_t *path, struct zbx_json *j)
{
	wchar_t	fs_name[MAX_PATH + 1], *long_path = NULL;
	char	*utf8;
	size_t	sz;

	utf8 = zbx_unicode_to_utf8(path);
	sz = strlen(utf8);

	if (0 < sz && '\\' == utf8[--sz])
		utf8[sz] = '\0';

	zbx_json_addobject(j, NULL);
	zbx_json_addstring(j, "{#FSNAME}", utf8, ZBX_JSON_TYPE_STRING);
	zbx_free(utf8);

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

	if (FALSE != GetVolumeInformation(path, NULL, 0, NULL, NULL, NULL, fs_name, ARRSIZE(fs_name)))
	{
		utf8 = zbx_unicode_to_utf8(fs_name);
		zbx_json_addstring(j, "{#FSTYPE}", utf8, ZBX_JSON_TYPE_STRING);
		zbx_free(utf8);
	}
	else
		zbx_json_addstring(j, "{#FSTYPE}", "UNKNOWN", ZBX_JSON_TYPE_STRING);

	zbx_json_addstring(j, "{#FSDRIVETYPE}", get_drive_type_string(GetDriveType(path)),
			ZBX_JSON_TYPE_STRING);
	zbx_json_close(j);

	zbx_free(long_path);
}

int	VFS_FS_DISCOVERY(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	wchar_t		*buffer = NULL, volume_name[MAX_PATH + 1], *p;
	DWORD		size_dw;
	size_t		sz;
	struct zbx_json	j;
	HANDLE		volume;
	int		ret;

	/* make an initial call to GetLogicalDriveStrings() to get the necessary size into the dwSize variable */
	if (0 == (size_dw = GetLogicalDriveStrings(0, buffer)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain necessary buffer size from system."));
		return SYSINFO_RET_FAIL;
	}

	zbx_json_initarray(&j, ZBX_JSON_STAT_BUF_LEN);

	buffer = (wchar_t *)zbx_malloc(buffer, (size_dw + 1) * sizeof(wchar_t));

	/* make a second call to GetLogicalDriveStrings() to get the actual data we require */
	if (0 == (size_dw = GetLogicalDriveStrings(size_dw, buffer)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain a list of filesystems."));
		ret = SYSINFO_RET_FAIL;
		goto out;
	}

	/* add drive letters */
	for (p = buffer, sz = wcslen(p); sz > 0; p += sz + 1, sz = wcslen(p))
		add_fs_to_json(p, &j);

	if (INVALID_HANDLE_VALUE == (volume = FindFirstVolume(volume_name, ARRSIZE(volume_name))))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot find a volume."));
		ret = SYSINFO_RET_FAIL;
		goto out;
	}

	/* search volumes for mount point folder paths */
	do
	{
		while (FALSE == GetVolumePathNamesForVolumeName(volume_name, buffer, size_dw, &size_dw))
		{
			if (ERROR_MORE_DATA != GetLastError())
			{
				FindVolumeClose(volume);
				SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain a list of filesystems."));
				ret = SYSINFO_RET_FAIL;
				goto out;
			}

			buffer = (wchar_t*)zbx_realloc(buffer, size_dw * sizeof(wchar_t));
		}

		for (p = buffer, sz = wcslen(p); sz > 0; p += sz + 1, sz = wcslen(p))
		{
			/* add mount point folder paths but skip drive letters */
			if (3 < sz)
				add_fs_to_json(p, &j);
		}

	} while (FALSE != FindNextVolume(volume, volume_name, ARRSIZE(volume_name)));

	if (ERROR_NO_MORE_FILES != GetLastError())
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain complete list of filesystems."));
		ret = SYSINFO_RET_FAIL;
	}
	else
	{
		zbx_json_close(&j);
		SET_STR_RESULT(result, zbx_strdup(NULL, j.buffer));
		ret = SYSINFO_RET_OK;
	}

	FindVolumeClose(volume);
out:
	zbx_json_free(&j);
	zbx_free(buffer);

	return ret;
}
