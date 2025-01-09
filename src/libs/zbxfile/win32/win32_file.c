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

#include "zbxfile.h"

#include "zbxwin32.h"

int	__zbx_open(const char *pathname, int flags)
{
	int	ret;
	wchar_t	*wpathname;

	wpathname = zbx_utf8_to_unicode(pathname);
	ret = _wopen(wpathname, flags);
	zbx_free(wpathname);

	return ret;
}

static	int	get_file_time_stat(const char *path, zbx_file_time_t *time)
{
	zbx_stat_t	buf;

	if (0 != zbx_stat(path, &buf))
		return FAIL;

	time->modification_time = buf.st_mtime;
	time->access_time = buf.st_atime;

	/* On Windows st_ctime stores file creation time, not the last change timestamp. */
	/* Assigning st_atime to change_time as the closest one.                         */
	time->change_time = buf.st_atime;

	return SUCCEED;
}

typedef struct {
	LARGE_INTEGER	CreationTime;
	LARGE_INTEGER	LastAccessTime;
	LARGE_INTEGER	LastWriteTime;
	LARGE_INTEGER	ChangeTime;
	DWORD		FileAttributes;
} file_basic_info_t;

int	zbx_get_file_time(const char *path, int sym, zbx_file_time_t *time)
{
	int			f = -1, ret = SUCCEED;
	intptr_t		h;
	file_basic_info_t	info;
	HANDLE			sym_handle = NULL;
	wchar_t			*wpath = NULL;

	if (0 == sym || NULL == zbx_get_GetFileInformationByHandleEx())
	{
		if (NULL == zbx_get_GetFileInformationByHandleEx() || -1 == (f = zbx_open(path, O_RDONLY)))
			return get_file_time_stat(path, time); /* fall back to stat() */

		if (-1 == (h = _get_osfhandle(f)) ||
				0 == (*zbx_get_GetFileInformationByHandleEx())((HANDLE)h, zbx_FileBasicInfo, &info,
				sizeof(info)))
		{
			ret = FAIL;
			goto out;
		}
	}
	else if (NULL == (wpath = zbx_utf8_to_unicode(path)) || INVALID_HANDLE_VALUE == (sym_handle = CreateFile(wpath,
			GENERIC_READ, FILE_SHARE_READ | FILE_SHARE_WRITE, NULL, OPEN_EXISTING,
			FILE_FLAG_BACKUP_SEMANTICS | FILE_FLAG_OPEN_REPARSE_POINT, NULL)) ||
			0 == (*zbx_get_GetFileInformationByHandleEx())(sym_handle, zbx_FileBasicInfo, &info,
			sizeof(info)))
	{
		ret = FAIL;
		goto out;
	}

#define WINDOWS_TICK 10000000
#define SEC_TO_UNIX_EPOCH 11644473600LL

	/* Convert 100-nanosecond intervals since January 1, 1601 (UTC) to epoch */
	time->modification_time = info.LastWriteTime.QuadPart / WINDOWS_TICK - SEC_TO_UNIX_EPOCH;
	time->access_time = info.LastAccessTime.QuadPart / WINDOWS_TICK - SEC_TO_UNIX_EPOCH;
	time->change_time = info.ChangeTime.QuadPart / WINDOWS_TICK - SEC_TO_UNIX_EPOCH;

#undef WINDOWS_TICK
#undef SEC_TO_UNIX_EPOCH

out:
	zbx_free(wpath);

	if (-1 != f)
		close(f);

	if (NULL != sym_handle)
		CloseHandle(sym_handle);

	return ret;
}
