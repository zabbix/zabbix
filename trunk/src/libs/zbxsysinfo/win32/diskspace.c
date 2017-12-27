/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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

static int	vfs_fs_size(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char		*path, *mode;
	wchar_t 	*wpath;
	ULARGE_INTEGER	freeBytes, totalBytes;

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

int	VFS_FS_DISCOVERY(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	wchar_t		fsName[MAX_PATH + 1];
	wchar_t 	*buffer = NULL, *p;
	char		*utf8;
	DWORD		dwSize;
	size_t		sz;
	struct zbx_json	j;

	/* Make an initial call to GetLogicalDriveStrings to
	   get the necessary size into the dwSize variable */
	if (0 == (dwSize = GetLogicalDriveStrings(0, buffer)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain necessary buffer size from system."));
		return SYSINFO_RET_FAIL;
	}

	buffer = (wchar_t *)zbx_malloc(buffer, (dwSize + 1) * sizeof(wchar_t));

	/* Make a second call to GetLogicalDriveStrings to get
	   the actual data we require */
	if (0 == (dwSize = GetLogicalDriveStrings(dwSize, buffer)))
	{
		zbx_free(buffer);
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain a list of filesystems."));
		return SYSINFO_RET_FAIL;
	}

	zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);

	zbx_json_addarray(&j, ZBX_PROTO_TAG_DATA);

	for (p = buffer, sz = wcslen(p); sz > 0; p += sz + 1, sz = wcslen(p))
	{
		zbx_json_addobject(&j, NULL);

		utf8 = zbx_unicode_to_utf8(p);

		/* remove trailing backslash */
		if ('A' <= utf8[0] && utf8[0] <= 'Z' && ':' == utf8[1] && '\\' == utf8[2] && '\0' == utf8[3])
			utf8[2] = '\0';

		zbx_json_addstring(&j, "{#FSNAME}", utf8, ZBX_JSON_TYPE_STRING);
		zbx_free(utf8);

		if (TRUE == GetVolumeInformation(p, NULL, 0, NULL, NULL, NULL, fsName, ARRSIZE(fsName)))
		{
			utf8 = zbx_unicode_to_utf8(fsName);
			zbx_json_addstring(&j, "{#FSTYPE}", utf8, ZBX_JSON_TYPE_STRING);
			zbx_free(utf8);
		}
		else
			zbx_json_addstring(&j, "{#FSTYPE}", "UNKNOWN", ZBX_JSON_TYPE_STRING);

		zbx_json_addstring(&j, "{#FSDRIVETYPE}", get_drive_type_string(GetDriveType(p)), ZBX_JSON_TYPE_STRING);

		zbx_json_close(&j);
	}

	zbx_free(buffer);

	zbx_json_close(&j);

	SET_STR_RESULT(result, zbx_strdup(NULL, j.buffer));

	zbx_json_free(&j);

	return SYSINFO_RET_OK;
}
