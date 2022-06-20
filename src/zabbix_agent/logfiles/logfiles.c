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

#include "logfiles.h"

#include "log.h"
#include "sysinfo.h"
#include "cfg.h"
#include "zbxregexp.h"

#if defined(_WINDOWS) || defined(__MINGW32__)
#	include "zbxsymbols.h"
#	include "zbxtypes.h"	/* ssize_t */
#endif /* _WINDOWS */

#define MAX_LEN_MD5	512	/* maximum size of the first and the last blocks of the file to calculate MD5 sum for */

#define ZBX_SAME_FILE_ERROR	-1
#define ZBX_SAME_FILE_NO	0
#define ZBX_SAME_FILE_YES	1
#define ZBX_SAME_FILE_RETRY	2
#define ZBX_NO_FILE_ERROR	3
#define ZBX_SAME_FILE_COPY	4

#define ZBX_FILE_PLACE_UNKNOWN	-1	/* cannot compare file device and inode numbers */
#define ZBX_FILE_PLACE_OTHER	0	/* both files have different device or inode numbers */
#define ZBX_FILE_PLACE_SAME	1	/* both files have the same device and inode numbers */

extern int	CONFIG_MAX_LINES_PER_SECOND;

extern ZBX_THREAD_LOCAL char	*CONFIG_HOSTNAME;

/******************************************************************************
 *                                                                            *
 * Purpose: separates given string to two parts by given delimiter in string  *
 *                                                                            *
 * Parameters:                                                                *
 *     str -   [IN] a not-empty string to split                               *
 *     del -   [IN] pointer to a character in the string                      *
 *     part1 - [OUT] pointer to buffer for the first part with delimiter      *
 *     part2 - [OUT] pointer to buffer for the second part                    *
 *                                                                            *
 * Return value: SUCCEED - on splitting without errors                        *
 *               FAIL - on splitting with errors                              *
 *                                                                            *
 * Comments: Memory for "part1" and "part2" is allocated only on SUCCEED.     *
 *                                                                            *
 ******************************************************************************/
static int	split_string(const char *str, const char *del, char **part1, char **part2)
{
	size_t	str_length, part1_length, part2_length;
	int	ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() str:'%s' del:'%s'", __func__, str, del);

	str_length = strlen(str);

	/* since the purpose of this function is to be used in split_filename(), we allow part1 to be */
	/* just *del (e.g., "/" - file system root), but we do not allow part2 (filename) to be empty */
	if (del < str || del >= (str + str_length - 1))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() cannot proceed: delimiter is out of range", __func__);
		goto out;
	}

	part1_length = (size_t)(del - str + 1);
	part2_length = str_length - part1_length;

	*part1 = (char *)zbx_malloc(*part1, part1_length + 1);
	zbx_strlcpy(*part1, str, part1_length + 1);

	*part2 = (char *)zbx_malloc(*part2, part2_length + 1);
	zbx_strlcpy(*part2, str + part1_length, part2_length + 1);

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s part1:'%s' part2:'%s'", __func__, zbx_result_string(ret),
			ZBX_NULL2STR(*part1), ZBX_NULL2STR(*part2));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: separates full-path file name into directory and file name regexp *
 *          parts                                                             *
 *                                                                            *
 * Parameters:                                                                *
 *     filename        - [IN] first parameter of logrt[] or logrt.count[]     *
 *                       item                                                 *
 *     directory       - [IN/OUT] directory part of the 'filename'            *
 *     filename_regexp - [IN/OUT] file name regular expression part           *
 *     err_msg         - [IN/OUT] error message why an item became            *
 *                       NOTSUPPORTED                                         *
 *                                                                            *
 * Return value: SUCCEED - on successful splitting                            *
 *               FAIL - on unable to split sensibly                           *
 *                                                                            *
 * Comments: Allocates memory for "directory" and "filename_regexp" only on   *
 *           SUCCEED. On FAIL memory, allocated for "directory" and           *
 *           "filename_regexp" is freed.                                      *
 *                                                                            *
 *           Thread-safe                                                      *
 *                                                                            *
 ******************************************************************************/
static int	split_filename(const char *filename, char **directory, char **filename_regexp, char **err_msg)
{
	const char	*separator = NULL;
	zbx_stat_t	buf;
	int		ret = FAIL;
#if defined(_WINDOWS) || defined(__MINGW32__)
	size_t		sz;
#endif
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() filename:'%s'", __func__, ZBX_NULL2STR(filename));

	if (NULL == filename || '\0' == *filename)
	{
		*err_msg = zbx_strdup(*err_msg, "Cannot split empty path.");
		goto out;
	}

#if defined(_WINDOWS) || defined(__MINGW32__)
	/* special processing for Windows, since directory name cannot be simply separated from file name regexp */
	for (sz = strlen(filename) - 1, separator = &filename[sz]; separator >= filename; separator--)
	{
		if (PATH_SEPARATOR != *separator)
			continue;

		zabbix_log(LOG_LEVEL_DEBUG, "%s() %s", __func__, filename);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() %*s", __func__, separator - filename + 1, "^");

		/* separator must be relative delimiter of the original filename */
		if (FAIL == split_string(filename, separator, directory, filename_regexp))
		{
			*err_msg = zbx_dsprintf(*err_msg, "Cannot split path by \"%c\".", PATH_SEPARATOR);
			goto out;
		}

		sz = strlen(*directory);

		/* Windows world verification */
		if (sz + 1 > MAX_PATH)
		{
			*err_msg = zbx_strdup(*err_msg, "Directory path is too long.");
			zbx_free(*directory);
			zbx_free(*filename_regexp);
			goto out;
		}

		/* Windows "stat" functions cannot get info about directories with '\' at the end of the path, */
		/* except for root directories 'x:\' */
		if (0 == zbx_stat(*directory, &buf) && S_ISDIR(buf.st_mode))
			break;

		if (sz > 0 && PATH_SEPARATOR == (*directory)[sz - 1])
		{
			(*directory)[sz - 1] = '\0';

			if (0 == zbx_stat(*directory, &buf) && S_ISDIR(buf.st_mode))
			{
				(*directory)[sz - 1] = PATH_SEPARATOR;
				break;
			}
		}

		zabbix_log(LOG_LEVEL_DEBUG, "cannot find directory '%s'", *directory);
		zbx_free(*directory);
		zbx_free(*filename_regexp);
	}

	if (separator < filename)
	{
		*err_msg = zbx_strdup(*err_msg, "Non-existing disk or directory.");
		goto out;
	}
#else	/* not _WINDOWS */
	if (NULL == (separator = strrchr(filename, PATH_SEPARATOR)))
	{
		*err_msg = zbx_dsprintf(*err_msg, "Cannot find separator \"%c\" in path.", PATH_SEPARATOR);
		goto out;
	}

	if (SUCCEED != split_string(filename, separator, directory, filename_regexp))
	{
		*err_msg = zbx_dsprintf(*err_msg, "Cannot split path by \"%c\".", PATH_SEPARATOR);
		goto out;
	}

	if (-1 == zbx_stat(*directory, &buf))
	{
		*err_msg = zbx_dsprintf(*err_msg, "Cannot obtain directory information: %s", zbx_strerror(errno));
		zbx_free(*directory);
		zbx_free(*filename_regexp);
		goto out;
	}

	if (0 == S_ISDIR(buf.st_mode))
	{
		*err_msg = zbx_dsprintf(*err_msg, "Base path \"%s\" is not a directory.", ZBX_NULL2STR(*directory));
		zbx_free(*directory);
		zbx_free(*filename_regexp);
		goto out;
	}
#endif	/* _WINDOWS */

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s directory:'%s' filename_regexp:'%s'", __func__,
			zbx_result_string(ret), ZBX_NULL2STR(*directory), ZBX_NULL2STR(*filename_regexp));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: calculate the MD5 sum of the specified part of the file           *
 *                                                                            *
 * Parameters:                                                                *
 *     f        - [IN] file descriptor                                        *
 *     offset   - [IN] start position of the part                             *
 *     length   - [IN] length of the part in bytes. Maximum is 512 bytes.     *
 *     md5buf   - [OUT] output buffer, ZBX_MD5_DIGEST_SIZE-bytes long, where  *
 *                the calculated MD5 sum is placed                            *
 *     filename - [IN] file name, used in error logging                       *
 *     err_msg  - [IN/OUT] error message why FAIL-ed                          *
 *                                                                            *
 * Return value: SUCCEED or FAIL                                              *
 *                                                                            *
 ******************************************************************************/
static int	file_part_md5(int f, size_t offset, int length, md5_byte_t *md5buf, const char *filename,
		char **err_msg)
{
	md5_state_t	state;
	char		buf[MAX_LEN_MD5];
	int		rc;

	if (MAX_LEN_MD5 < length)
	{
		*err_msg = zbx_dsprintf(*err_msg, "Length %d exceeds maximum MD5 fragment length %d.", length,
				MAX_LEN_MD5);
		return FAIL;
	}

	if ((zbx_offset_t)-1 == zbx_lseek(f, offset, SEEK_SET))
	{
		*err_msg = zbx_dsprintf(*err_msg, "Cannot set position to " ZBX_FS_SIZE_T " for file \"%s\": %s",
				(zbx_fs_size_t)offset, filename, zbx_strerror(errno));
		return FAIL;
	}

	if (length != (rc = (int)read(f, buf, (size_t)length)))
	{
		if (-1 == rc)
		{
			*err_msg = zbx_dsprintf(*err_msg, "Cannot read %d bytes from file \"%s\": %s", length, filename,
					zbx_strerror(errno));
		}
		else
		{
			*err_msg = zbx_dsprintf(*err_msg, "Cannot read %d bytes from file \"%s\". Read %d bytes only.",
					length, filename, rc);
		}

		return FAIL;
	}

	zbx_md5_init(&state);
	zbx_md5_append(&state, (const md5_byte_t *)buf, length);
	zbx_md5_finish(&state, md5buf);

	return SUCCEED;
}

#if defined(_WINDOWS) || defined(__MINGW32__)
/******************************************************************************
 *                                                                            *
 * Purpose: get Microsoft Windows file device ID, 64-bit FileIndex or         *
 *          128-bit FileId                                                    *
 *                                                                            *
 * Parameters:                                                                *
 *     f        - [IN] file descriptor                                        *
 *     use_ino  - [IN] how to use file IDs                                    *
 *     dev      - [OUT] device ID                                             *
 *     ino_lo   - [OUT] 64-bit nFileIndex or lower 64-bits of FileId          *
 *     ino_hi   - [OUT] higher 64-bits of FileId                              *
 *     filename - [IN] file name, used in error logging                       *
 *     err_msg  - [IN/OUT] error message why an item became NOTSUPPORTED      *
 *                                                                            *
 * Return value: SUCCEED or FAIL                                              *
 *                                                                            *
 ******************************************************************************/
static int	file_id(int f, int use_ino, zbx_uint64_t *dev, zbx_uint64_t *ino_lo, zbx_uint64_t *ino_hi,
		const char *filename, char **err_msg)
{
	int				ret = FAIL;
	intptr_t			h;	/* file HANDLE */
	BY_HANDLE_FILE_INFORMATION	hfi;
	ZBX_FILE_ID_INFO		fid;

	if (-1 == (h = _get_osfhandle(f)))
	{
		*err_msg = zbx_dsprintf(*err_msg, "Cannot obtain handle from descriptor of file \"%s\": %s",
				filename, zbx_strerror(errno));
		return ret;
	}

	if (1 == use_ino || 0 == use_ino)
	{
		/* Although nFileIndexHigh and nFileIndexLow cannot be reliably used to identify files when */
		/* use_ino = 0 (e.g. on FAT32, exFAT), we copy indexes to have at least correct debug logs. */
		if (0 != GetFileInformationByHandle((HANDLE)h, &hfi))
		{
			*dev = hfi.dwVolumeSerialNumber;
			*ino_lo = (zbx_uint64_t)hfi.nFileIndexHigh << 32 | (zbx_uint64_t)hfi.nFileIndexLow;
			*ino_hi = 0;
		}
		else
		{
			*err_msg = zbx_dsprintf(*err_msg, "Cannot obtain information for file \"%s\": %s",
					filename, strerror_from_system(GetLastError()));
			return ret;
		}
	}
	else if (2 == use_ino)
	{
		if (NULL != zbx_GetFileInformationByHandleEx)
		{
			if (0 != zbx_GetFileInformationByHandleEx((HANDLE)h, zbx_FileIdInfo, &fid, sizeof(fid)))
			{
				*dev = fid.VolumeSerialNumber;
				*ino_lo = fid.FileId.LowPart;
				*ino_hi = fid.FileId.HighPart;
			}
			else
			{
				*err_msg = zbx_dsprintf(*err_msg, "Cannot obtain extended information for file"
						" \"%s\": %s", filename, strerror_from_system(GetLastError()));
				return ret;
			}
		}
	}
	else
	{
		THIS_SHOULD_NEVER_HAPPEN;
		return ret;
	}

	ret = SUCCEED;

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: find file system type and set 'use_ino' parameter                 *
 *                                                                            *
 * Parameters:                                                                *
 *     path     - [IN] directory or file name                                 *
 *     use_ino  - [IN] how to use file IDs                                    *
 *     err_msg  - [IN/OUT] error message why an item became NOTSUPPORTED      *
 *                                                                            *
 * Return value: SUCCEED or FAIL                                              *
 *                                                                            *
 ******************************************************************************/
static int	set_use_ino_by_fs_type(const char *path, int *use_ino, char **err_msg)
{
	char	*utf8;
	wchar_t	*path_uni, mount_point[MAX_PATH + 1], fs_type[MAX_PATH + 1];

	path_uni = zbx_utf8_to_unicode(path);

	/* get volume mount point */
	if (0 == GetVolumePathName(path_uni, mount_point,
			sizeof(mount_point) / sizeof(wchar_t)))
	{
		*err_msg = zbx_dsprintf(*err_msg, "Cannot obtain volume mount point for file \"%s\": %s", path,
				strerror_from_system(GetLastError()));
		zbx_free(path_uni);
		return FAIL;
	}

	zbx_free(path_uni);

	/* Which file system type this directory resides on ? */
	if (0 == GetVolumeInformation(mount_point, NULL, 0, NULL, NULL, NULL, fs_type,
			sizeof(fs_type) / sizeof(wchar_t)))
	{
		utf8 = zbx_unicode_to_utf8(mount_point);
		*err_msg = zbx_dsprintf(*err_msg, "Cannot obtain volume information for directory \"%s\": %s", utf8,
				strerror_from_system(GetLastError()));
		zbx_free(utf8);
		return FAIL;
	}

	utf8 = zbx_unicode_to_utf8(fs_type);

	if (0 == strcmp(utf8, "NTFS"))
		*use_ino = 1;			/* 64-bit FileIndex */
	else if (0 == strcmp(utf8, "ReFS"))
		*use_ino = 2;			/* 128-bit FileId */
	else
		*use_ino = 0;			/* cannot use inodes to identify files (e.g. FAT32) */

	zabbix_log(LOG_LEVEL_DEBUG, "log files reside on '%s' file system", utf8);
	zbx_free(utf8);

	return SUCCEED;
}
#endif

/******************************************************************************
 *                                                                            *
 * Purpose: write logfile list into log for debugging                         *
 *                                                                            *
 * Parameters:                                                                *
 *     logfiles     - [IN] array of logfiles                                  *
 *     logfiles_num - [IN] number of elements in the array                    *
 *                                                                            *
 ******************************************************************************/
static void	print_logfile_list(const struct st_logfile *logfiles, int logfiles_num)
{
	if (SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_DEBUG))
	{
		int	i;

		for (i = 0; i < logfiles_num; i++)
		{
			char	first_buf[ZBX_MD5_PRINT_BUF_LEN], last_buf[ZBX_MD5_PRINT_BUF_LEN];

			zbx_md5buf2str(logfiles[i].first_block_md5, first_buf);
			zbx_md5buf2str(logfiles[i].last_block_md5, last_buf);

			zabbix_log(LOG_LEVEL_DEBUG, "   nr:%d filename:'%s' mtime:%d size:" ZBX_FS_UI64
					" processed_size:" ZBX_FS_UI64 " seq:%d copy_of:%d incomplete:%d dev:"
					ZBX_FS_UI64 " ino_hi:" ZBX_FS_UI64 " ino_lo:" ZBX_FS_UI64 " md5_block_size:%d"
					" first_block_md5:%s last_block_offset:" ZBX_FS_UI64 " last_block_md5:%s", i,
					logfiles[i].filename, logfiles[i].mtime, logfiles[i].size,
					logfiles[i].processed_size, logfiles[i].seq, logfiles[i].copy_of,
					logfiles[i].incomplete, logfiles[i].dev, logfiles[i].ino_hi, logfiles[i].ino_lo,
					logfiles[i].md5_block_size, first_buf, logfiles[i].last_block_offset, last_buf);
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: compare device numbers and inode numbers of 2 files               *
 *                                                                            *
 * Parameters: old_file - [IN] details of the 1st log file                    *
 *             new_file - [IN] details of the 2nd log file                    *
 *             use_ino  - [IN] 0 - do not use inodes in comparison,           *
 *                             1 - use up to 64-bit inodes in comparison,     *
 *                             2 - use 128-bit inodes in comparison.          *
 *                                                                            *
 * Return value: ZBX_FILE_PLACE_SAME - both files have the same place         *
 *               ZBX_FILE_PLACE_OTHER - files reside in different places      *
 *               ZBX_FILE_PLACE_UNKNOWN - cannot compare places (no inodes)   *
 *                                                                            *
 ******************************************************************************/
static int	compare_file_places(const struct st_logfile *old_file, const struct st_logfile *new_file, int use_ino)
{
	if (1 == use_ino || 2 == use_ino)
	{
		if (old_file->ino_lo != new_file->ino_lo || old_file->dev != new_file->dev ||
				(2 == use_ino && old_file->ino_hi != new_file->ino_hi))
		{
			return ZBX_FILE_PLACE_OTHER;
		}
		else
			return ZBX_FILE_PLACE_SAME;
	}

	return ZBX_FILE_PLACE_UNKNOWN;
}

/******************************************************************************
 *                                                                            *
 * Purpose: open specified file for reading                                   *
 *                                                                            *
 * Parameters: pathname - [IN] full pathname of file                          *
 *             err_msg  - [IN/OUT] error message why file could not be opened *
 *                                                                            *
 * Return value: file descriptor on success or -1 on error                    *
 *                                                                            *
 ******************************************************************************/
static int	open_file_helper(const char *pathname, char **err_msg)
{
	int	fd;

	if (-1 == (fd = zbx_open(pathname, O_RDONLY)))
		*err_msg = zbx_dsprintf(*err_msg, "Cannot open file \"%s\": %s", pathname, zbx_strerror(errno));

	return fd;
}

/******************************************************************************
 *                                                                            *
 * Purpose: close specified file                                              *
 *                                                                            *
 * Parameters: fd       - [IN] file descriptor to close                       *
 *             pathname - [IN] pathname of file, used for error reporting     *
 *             err_msg  - [IN/OUT] error message why file could not be closed *
 *                             unless an earlier error has been already       *
 *                             reported                                       *
 *                                                                            *
 * Return value: SUCCEED or FAIL                                              *
 *                                                                            *
 ******************************************************************************/
static int	close_file_helper(int fd, const char *pathname, char **err_msg)
{
	if (0 == close(fd))
		return SUCCEED;

	if (NULL == *err_msg)
		*err_msg = zbx_dsprintf(NULL, "Cannot close file \"%s\": %s", pathname, zbx_strerror(errno));

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: from MD5 sums of blocks and places of 2 files make a conclusion   *
 *          is it the same file, a pair 'original/copy' or 2 different files  *
 *                                                                            *
 * Parameters:  buf1          - [IN] MD5 sum of block in the 1st file         *
 *              buf2          - [IN] MD5 sum of block in the 2nd file         *
 *              size          - [IN] size of MD5 sum                          *
 *              is_same_place - [IN] equality of file places                  *
 *                                                                            *
 * Return value: ZBX_SAME_FILE_NO - they are 2 different files                *
 *               ZBX_SAME_FILE_YES - 2 files are (assumed) to be the same     *
 *               ZBX_SAME_FILE_COPY - one file is copy of the other           *
 *                                                                            *
 * Comments: in case files places are unknown but MD5 sums of block pairs     *
 *           match it is assumed to be the same file                          *
 *                                                                            *
 ******************************************************************************/
static int	examine_md5_and_place(const md5_byte_t *buf1, const md5_byte_t *buf2, size_t size, int is_same_place)
{
	if (0 == memcmp(buf1, buf2, size))
	{
		switch (is_same_place)
		{
			case ZBX_FILE_PLACE_UNKNOWN:
			case ZBX_FILE_PLACE_SAME:
				return ZBX_SAME_FILE_YES;
			case ZBX_FILE_PLACE_OTHER:
				return ZBX_SAME_FILE_COPY;
		}
	}

	return ZBX_SAME_FILE_NO;
}

/******************************************************************************
 *                                                                            *
 * Purpose: find out if a file from the old list and a file from the new list *
 *          could be the same file or copy in case of copy/truncate rotation  *
 *                                                                            *
 * Parameters:                                                                *
 *          old_file - [IN] file from the old list                            *
 *          new_file - [IN] file from the new list                            *
 *          use_ino  - [IN] 0 - do not use inodes in comparison,              *
 *                          1 - use up to 64-bit inodes in comparison,        *
 *                          2 - use 128-bit inodes in comparison.             *
 *         new_files - [IN] new file list                                     *
 *          num_new  - [IN] number of elements in the new file list           *
 *          err_msg  - [IN/OUT] error message why an item became              *
 *                     NOTSUPPORTED                                           *
 *                                                                            *
 * Return value: ZBX_SAME_FILE_NO - it is not the same file                   *
 *               ZBX_SAME_FILE_YES - it could be the same file                *
 *               ZBX_SAME_FILE_COPY - it is a copy                            *
 *               ZBX_SAME_FILE_ERROR - error                                  *
 *                                                                            *
 * Comments: In some cases we can say that it IS NOT the same file.           *
 *           In other cases it COULD BE the same file or copy.                *
 *                                                                            *
 *           Thread-safe                                                      *
 *                                                                            *
 ******************************************************************************/
static int	is_same_file_logcpt(const struct st_logfile *old_file, const struct st_logfile *new_file, int use_ino,
		const struct st_logfile *new_files, int num_new, char **err_msg)
{
	int	is_same_place, ret = ZBX_SAME_FILE_NO, found_matching_md5 = 0, same_name_in_new_list = 0, i, f;

	if (old_file->mtime > new_file->mtime)
		return ZBX_SAME_FILE_NO;

	if (-1 == old_file->md5_block_size || -1 == new_file->md5_block_size)
	{
		/* Cannot compare MD5 sums. Assume two different files - reporting twice is better than skipping. */
		return ZBX_SAME_FILE_NO;
	}

	is_same_place = compare_file_places(old_file, new_file, use_ino);

	if (old_file->md5_block_size == new_file->md5_block_size &&
			old_file->last_block_offset == new_file->last_block_offset)
	{
		if (ZBX_SAME_FILE_NO == (ret = examine_md5_and_place(old_file->first_block_md5,
				new_file->first_block_md5, sizeof(new_file->first_block_md5), is_same_place)))
		{
			return ret;
		}

		return examine_md5_and_place(old_file->last_block_md5, new_file->last_block_md5,
				sizeof(new_file->last_block_md5), is_same_place);
	}

	if (0 == old_file->md5_block_size || 0 == new_file->md5_block_size)
		return ZBX_SAME_FILE_NO;

	/* MD5 sums have been calculated from blocks of different sizes or last blocks offsets differ */

	if (old_file->md5_block_size < new_file->md5_block_size ||
			(old_file->md5_block_size == new_file->md5_block_size &&
			old_file->last_block_offset != new_file->last_block_offset))
	{
		md5_byte_t	md5tmp[ZBX_MD5_DIGEST_SIZE];

		if (-1 == (f = open_file_helper(new_file->filename, err_msg)))
			return ZBX_SAME_FILE_ERROR;

		if (SUCCEED != file_part_md5(f, 0, old_file->md5_block_size, md5tmp, new_file->filename, err_msg))
		{
			ret = ZBX_SAME_FILE_ERROR;
			goto clean1;
		}

		if (ZBX_SAME_FILE_NO == (ret = examine_md5_and_place(old_file->first_block_md5, md5tmp, sizeof(md5tmp),
				is_same_place)))
		{
			goto clean1;
		}

		if (0 < old_file->last_block_offset)
		{
			if (SUCCEED != file_part_md5(f, old_file->last_block_offset, old_file->md5_block_size, md5tmp,
					new_file->filename, err_msg))
			{
				ret = ZBX_SAME_FILE_ERROR;
				goto clean1;
			}

			ret = examine_md5_and_place(old_file->last_block_md5, md5tmp, sizeof(md5tmp), is_same_place);
		}
clean1:
		if (0 != close(f) && ZBX_SAME_FILE_ERROR != ret)
		{
			*err_msg = zbx_dsprintf(*err_msg, "Cannot close file \"%s\": %s",
					new_file->filename, zbx_strerror(errno));
			ret = ZBX_SAME_FILE_ERROR;
		}

		return ret;
	}

	/* Remaining case: old_file->md5_block_size > new_file->md5_block_size */

	/* Now it is necessary to read the first 'new_file->md5_block_size' bytes */
	/* of the old file to calculate MD5 sum to compare. Unfortunately we  */
	/* cannot reliably use 'old_file->filename' to open the file because: */
	/*    - being from the old list it might be no longer available,      */
	/*    - it can have a different name in the new file list;            */
	/*    - 'old_file->filename' can be the same as 'new_file->filename'  */
	/*      (see ZBX-18883) making comparison pointless.                  */

	for (i = 0; i < num_new; i++)
	{
		md5_byte_t	md5tmp[ZBX_MD5_DIGEST_SIZE];

		if ((zbx_uint64_t)new_file->md5_block_size > new_files[i].size)
			continue;

		if (0 == strcmp(old_file->filename, new_file->filename) ||
				0 == strcmp(new_files[i].filename, new_file->filename))	/* do not compare with self */
		{
			same_name_in_new_list = 1;
			continue;
		}

		if (-1 == (f = open_file_helper(new_files[i].filename, err_msg)))
			return ZBX_SAME_FILE_ERROR;

		if (SUCCEED != file_part_md5(f, 0, new_file->md5_block_size, md5tmp, new_files[i].filename, err_msg))
		{
			ret = ZBX_SAME_FILE_ERROR;
			goto clean2;
		}

		if (ZBX_SAME_FILE_NO == (ret = examine_md5_and_place(new_file->first_block_md5, md5tmp, sizeof(md5tmp),
				compare_file_places(old_file, new_files + i, use_ino))))
		{
			goto clean2;
		}

		if (0 < new_file->last_block_offset)
		{
			if (SUCCEED != file_part_md5(f, new_file->last_block_offset, new_file->md5_block_size, md5tmp,
					new_files[i].filename, err_msg))
			{
				ret = ZBX_SAME_FILE_ERROR;
				goto clean2;
			}

			ret = examine_md5_and_place(new_file->last_block_md5, md5tmp, sizeof(md5tmp),
					compare_file_places(old_file, new_files + i, use_ino));
		}

		if (ZBX_SAME_FILE_YES == ret || ZBX_SAME_FILE_COPY == ret)
			found_matching_md5 = 1;
clean2:
		if (0 != close(f) && ZBX_SAME_FILE_ERROR != ret)
		{
			*err_msg = zbx_dsprintf(*err_msg, "Cannot close file \"%s\": %s", new_files[i].filename,
					zbx_strerror(errno));
			ret = ZBX_SAME_FILE_ERROR;
		}

		if (0 != found_matching_md5)
			break;
	}

	if (0 == found_matching_md5 && 0 == same_name_in_new_list)
	{
		md5_byte_t	md5tmp[ZBX_MD5_DIGEST_SIZE];

		/* last try - opening file with the name from the old list */

		if (-1 == (f = open_file_helper(old_file->filename, err_msg)))
			return ZBX_SAME_FILE_NO;	/* not an error if it is no longer available */

		if (SUCCEED != file_part_md5(f, 0, new_file->md5_block_size, md5tmp, old_file->filename, err_msg))
		{
			ret = ZBX_SAME_FILE_NO;
			goto clean3;
		}

		if (ZBX_SAME_FILE_NO == (ret = examine_md5_and_place(new_file->first_block_md5, md5tmp, sizeof(md5tmp),
				compare_file_places(old_file, new_file, use_ino))))
		{
			goto clean3;
		}

		if (0 < new_file->last_block_offset)
		{
			if (SUCCEED != file_part_md5(f, new_file->last_block_offset, new_file->md5_block_size, md5tmp,
					old_file->filename, err_msg))
			{
				ret = ZBX_SAME_FILE_NO;
				goto clean3;
			}

			ret = examine_md5_and_place(new_file->last_block_md5, md5tmp, sizeof(md5tmp),
					compare_file_places(old_file, new_file, use_ino));
		}
clean3:
		if (0 != close(f))
		{
			*err_msg = zbx_dsprintf(*err_msg, "Cannot close file \"%s\": %s", old_file->filename,
					zbx_strerror(errno));
			ret = ZBX_SAME_FILE_ERROR;
		}
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: find out if a file from the old list and a file from the new list *
 *          could be the same file in case of simple rotation                 *
 *                                                                            *
 * Parameters:                                                                *
 *          old_file - [IN] file from the old list                            *
 *          new_file - [IN] file from the new list                            *
 *          use_ino  - [IN] 0 - do not use inodes in comparison,              *
 *                          1 - use up to 64-bit inodes in comparison,        *
 *                          2 - use 128-bit inodes in comparison.             *
 *          options  - [IN] log rotation options                              *
 *         new_files - [IN] new file list                                     *
 *          num_new  - [IN] number of elements in the new file list           *
 *          err_msg  - [IN/OUT] error message why an item became              *
 *                     NOTSUPPORTED                                           *
 *                                                                            *
 * Return value: ZBX_SAME_FILE_NO - it is not the same file,                  *
 *               ZBX_SAME_FILE_YES - it could be the same file,               *
 *               ZBX_SAME_FILE_ERROR - error,                                 *
 *               ZBX_SAME_FILE_RETRY - retry on the next check.               *
 *                                                                            *
 * Comments: In some cases we can say that it IS NOT the same file.           *
 *           We can never say that it IS the same file and it has not been    *
 *           truncated and replaced with a similar one.                       *
 *                                                                            *
 * Comments: Thread-safe                                                      *
 *                                                                            *
 ******************************************************************************/
static int	is_same_file_logrt(const struct st_logfile *old_file, const struct st_logfile *new_file, int use_ino,
		zbx_log_rotation_options_t options, const struct st_logfile *new_files, int num_new, char **err_msg)
{
	if (ZBX_LOG_ROTATION_LOGCPT == options)
		return is_same_file_logcpt(old_file, new_file, use_ino, new_files, num_new, err_msg);

	if (ZBX_FILE_PLACE_OTHER == compare_file_places(old_file, new_file, use_ino))
	{
		/* files cannot reside on different devices or occupy different inodes and be the same */
		return ZBX_SAME_FILE_NO;
	}

	if (old_file->size > new_file->size || old_file->processed_size > new_file->size)
	{
		/* File size cannot decrease. Truncating or replacing a file with a smaller one */
		/* counts as 2 different files. */
		return ZBX_SAME_FILE_NO;
	}

	/* the old file and the new file occupy the same device and inode (index), */
	/* the new file is not smaller than the old one */

	if (old_file->size == new_file->size && old_file->mtime < new_file->mtime)
	{
		int	same_first_block = 0, same_last_block = 0;

		if (0 < old_file->md5_block_size && old_file->md5_block_size == new_file->md5_block_size)
		{
			if (0 != memcmp(old_file->first_block_md5, new_file->first_block_md5,
					sizeof(new_file->first_block_md5)))
			{
				return ZBX_SAME_FILE_NO;
			}

			same_first_block = 1;

			if (old_file->last_block_offset == new_file->last_block_offset)
			{
				if (0 != memcmp(old_file->last_block_md5, new_file->last_block_md5,
						sizeof(new_file->last_block_md5)))
				{
					return ZBX_SAME_FILE_NO;
				}

				same_last_block = 1;
			}
		}

		/* There is one problematic case: log file size stays the same   */
		/* but its modification time (mtime) changes. This can be caused */
		/* by 3 scenarios:                                               */
		/*   1) the log file is rewritten with the same content at the   */
		/*     same location on disk. Very rare but possible.            */
		/*   2) depending on file system it's possible that stat() was   */
		/*     called between mtime and file size update. In this        */
		/*     situation the agent registers a file with the old size    */
		/*     and a new mtime.                                          */
		/*   3) application somehow "touch"-es the log file: mtime       */
		/*     increases, size does not.                                 */
		/*                                                               */
		/* Agent cannot distinguish between these cases. Only users      */
		/* familiar with their applications and log file rotation can    */
		/* know which scenario takes place with which log file.          */
		/* Most users would choose "noreread" option (it is not enabled  */
		/* by default!) to handle it as the same log file without no     */
		/* new records to report.                                        */
		/* Some users might want to handle it as a new log file (it is   */
		/* the default setting) (e.g. for log*.count[] purpose).         */

		if (0 != same_first_block && 0 != same_last_block && ZBX_LOG_ROTATION_NO_REREAD == options)
			return ZBX_SAME_FILE_YES;

		/* On the first try we assume it's the same file, just its size  */
		/* has not been changed yet.                                     */
		/* If the size has not changed on the next check, then we assume */
		/* that some tampering was done and to be safe we will treat it  */
		/* as a different file unless "noreread" option is specified.    */
		if (0 == old_file->retry)
		{
			if (ZBX_LOG_ROTATION_NO_REREAD != options)
			{
				zabbix_log(LOG_LEVEL_WARNING, "the modification time of log file \"%s\" has been"
						" updated without changing its size, try checking again later",
						old_file->filename);
			}

			return ZBX_SAME_FILE_RETRY;
		}

		if (ZBX_LOG_ROTATION_NO_REREAD == options)
		{
			zabbix_log(LOG_LEVEL_WARNING, "after changing modification time the size of log file \"%s\""
					" still has not been updated, consider it to be same file",
					old_file->filename);
			return ZBX_SAME_FILE_YES;
		}

		zabbix_log(LOG_LEVEL_WARNING, "after changing modification time the size of log file \"%s\""
				" still has not been updated, consider it to be a new file", old_file->filename);
		return ZBX_SAME_FILE_NO;
	}

	if (-1 == old_file->md5_block_size || -1 == new_file->md5_block_size)
	{
		/* Cannot compare MD5 sums. Assume two different files - reporting twice is better than skipping. */
		return ZBX_SAME_FILE_NO;
	}

	if (old_file->md5_block_size > new_file->md5_block_size)
	{
		/* file initial block size from which MD5 sum is calculated cannot decrease */
		return ZBX_SAME_FILE_NO;
	}

	if (old_file->md5_block_size == new_file->md5_block_size)
	{
		if (0 != memcmp(old_file->first_block_md5, new_file->first_block_md5,
				sizeof(new_file->first_block_md5)))
		{
			return ZBX_SAME_FILE_NO;
		}

		if (old_file->last_block_offset == new_file->last_block_offset &&
				0 == memcmp(old_file->last_block_md5, new_file->last_block_md5,
				sizeof(new_file->last_block_md5)))
		{
			return ZBX_SAME_FILE_YES;
		}
	}

	if (0 < old_file->md5_block_size)
	{
		/* MD5 for the old file has been calculated from a smaller block or */
		/* with a different offset than for the new file */

		int		f, ret;
		md5_byte_t	md5tmp[ZBX_MD5_DIGEST_SIZE];

		if (-1 == (f = open_file_helper(new_file->filename, err_msg)))
			return ZBX_SAME_FILE_ERROR;

		if (SUCCEED != file_part_md5(f, 0, old_file->md5_block_size, md5tmp, new_file->filename, err_msg))
		{
			ret = ZBX_SAME_FILE_ERROR;
			goto clean;
		}

		if (0 != memcmp(old_file->first_block_md5, md5tmp, sizeof(md5tmp)))
		{
			ret = ZBX_SAME_FILE_NO;
			goto clean;
		}

		if (0 == old_file->last_block_offset)
		{
			ret = ZBX_SAME_FILE_YES;
			goto clean;
		}

		if (SUCCEED != file_part_md5(f, old_file->last_block_offset, old_file->md5_block_size, md5tmp,
				new_file->filename, err_msg))
		{
			ret = ZBX_SAME_FILE_ERROR;
			goto clean;
		}

		if (0 == memcmp(old_file->last_block_md5, md5tmp, sizeof(md5tmp)))
			ret = ZBX_SAME_FILE_YES;
		else
			ret = ZBX_SAME_FILE_NO;
clean:
		if (0 != close(f) && ZBX_SAME_FILE_ERROR != ret)
		{
			*err_msg = zbx_dsprintf(*err_msg, "Cannot close file \"%s\": %s", new_file->filename,
					zbx_strerror(errno));
			ret = ZBX_SAME_FILE_ERROR;
		}

		return ret;
	}

	return ZBX_SAME_FILE_YES;
}

/******************************************************************************
 *                                                                            *
 * Purpose: fill the given row and column with '0' except the element at the  *
 *          cross point and protected columns and protected rows              *
 *                                                                            *
 * Parameters:                                                                *
 *          arr    - [IN/OUT] two dimensional array                           *
 *          n_rows - [IN] number of rows in the array                         *
 *          n_cols - [IN] number of columns in the array                      *
 *          row    - [IN] number of cross point row                           *
 *          col    - [IN] number of cross point column                        *
 *          p_rows - [IN] vector with 'n_rows' elements.                      *
 *                        Value '1' means protected row.                      *
 *          p_cols - [IN] vector with 'n_cols' elements.                      *
 *                        Value '1' means protected column.                   *
 *                                                                            *
 * Example:                                                                   *
 *     Given array                                                            *
 *                                                                            *
 *         1 1 1 1                                                            *
 *         1 1 1 1                                                            *
 *         1 1 1 1                                                            *
 *                                                                            *
 *     and row = 1, col = 2 and no protected rows and columns                 *
 *     the array is modified as                                               *
 *                                                                            *
 *         1 1 0 1                                                            *
 *         0 0 1 0                                                            *
 *         1 1 0 1                                                            *
 *                                                                            *
 ******************************************************************************/
static void	cross_out(char *arr, int n_rows, int n_cols, int row, int col, const char *p_rows, const char *p_cols)
{
	int	i;
	char	*p;

	p = arr + row * n_cols;		/* point to the first element of the 'row' */

	for (i = 0; i < n_cols; i++)	/* process row */
	{
		if ('1' != p_cols[i] && col != i)
			p[i] = '0';
	}

	p = arr + col;			/* point to the top element of the 'col' */

	for (i = 0; i < n_rows; i++)	/* process column */
	{
		if ('1' != p_rows[i] && row != i)
			p[i * n_cols] = '0';
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if there is only one element '1' or '2' in the given row    *
 *                                                                            *
 * Parameters:                                                                *
 *          arr    - [IN] two dimensional array                               *
 *          n_cols - [IN] number of columns in the array                      *
 *          row    - [IN] number of row to search                             *
 *                                                                            *
 * Return value: number of column where the element '1' or '2' was found or   *
 *               -1 if there are zero or multiple elements '1' or '2' in the  *
 *               row                                                          *
 *                                                                            *
 ******************************************************************************/
static int	is_uniq_row(const char * const arr, int n_cols, int row)
{
	int		i, mappings = 0, ret = -1;
	const char	*p;

	p = arr + row * n_cols;			/* point to the first element of the 'row' */

	for (i = 0; i < n_cols; i++)
	{
		if ('1' == *p || '2' == *p)
		{
			if (2 == ++mappings)
			{
				ret = -1;	/* non-unique mapping in the row */
				break;
			}

			ret = i;
		}

		p++;
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if there is only one element '1' or '2' in the given column *
 *                                                                            *
 * Parameters:                                                                *
 *          arr    - [IN] two dimensional array                               *
 *          n_rows - [IN] number of rows in the array                         *
 *          n_cols - [IN] number of columns in the array                      *
 *          col    - [IN] number of column to search                          *
 *                                                                            *
 * Return value: number of row where the element '1' or '2 ' was found or     *
 *               -1 if there are zero or multiple elements '1' or '2' in the  *
 *               column                                                       *
 *                                                                            *
 ******************************************************************************/
static int	is_uniq_col(const char * const arr, int n_rows, int n_cols, int col)
{
	int		i, mappings = 0, ret = -1;
	const char	*p;

	p = arr + col;				/* point to the top element of the 'col' */

	for (i = 0; i < n_rows; i++)
	{
		if ('1' == *p || '2' == *p)
		{
			if (2 == ++mappings)
			{
				ret = -1;	/* non-unique mapping in the column */
				break;
			}

			ret = i;
		}

		p += n_cols;
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if 'old2new' array has only unique mappings                 *
 *                                                                            *
 * Parameters:                                                                *
 *          old2new - [IN] two dimensional array of possible mappings         *
 *          num_old - [IN] number of elements in the old file list            *
 *          num_new - [IN] number of elements in the new file list            *
 *                                                                            *
 * Return value: SUCCEED - all mappings are unique,                           *
 *               FAIL - there are non-unique mappings                         *
 *                                                                            *
 ******************************************************************************/
static int	is_old2new_unique_mapping(const char * const old2new, int num_old, int num_new)
{
	int	i;

	/* Is there 1:1 mapping in both directions between files in the old and the new list ? */
	/* In this case every row and column has not more than one element '1' or '2', others are '0'. */
	/* This is expected on UNIX (using inode numbers) and MS Windows (using FileID on NTFS, ReFS) */
	/* unless 'copytruncate' rotation type is combined with multiple log file copies. */

	for (i = 0; i < num_old; i++)		/* loop over rows (old files) */
	{
		if (-1 == is_uniq_row(old2new, num_new, i))
			return FAIL;
	}

	for (i = 0; i < num_new; i++)		/* loop over columns (new files) */
	{
		if (-1 == is_uniq_col(old2new, num_old, num_new, i))
			return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: resolve non-unique mappings                                       *
 *                                                                            *
 * Parameters:                                                                *
 *     old2new - [IN] two dimensional array of possible mappings              *
 *     num_old - [IN] number of elements in the old file list                 *
 *     num_new - [IN] number of elements in the new file list                 *
 *                                                                            *
 ******************************************************************************/
static void	resolve_old2new(char *old2new, int num_old, int num_new)
{
	int	i;
	char	*protected_rows = NULL, *protected_cols = NULL;

	if (SUCCEED == is_old2new_unique_mapping(old2new, num_old, num_new))
		return;

	/* Non-unique mapping is expected: */
	/*   - on MS Windows using FAT32 and other file systems where inodes or file indexes are either not */
	/*     preserved if a file is renamed or are not applicable, */
	/*   - in 'copytruncate' rotation mode if multiple copies of log files are present. */

	zabbix_log(LOG_LEVEL_DEBUG, "resolve_old2new(): non-unique mapping");

	/* protect unique mappings from further modifications */

	protected_rows = (char *)zbx_calloc(protected_rows, (size_t)num_old, sizeof(char));
	protected_cols = (char *)zbx_calloc(protected_cols, (size_t)num_new, sizeof(char));

	for (i = 0; i < num_old; i++)
	{
		int	c;

		if (-1 != (c = is_uniq_row(old2new, num_new, i)) && -1 != is_uniq_col(old2new, num_old, num_new, c))
		{
			protected_rows[i] = '1';
			protected_cols[c] = '1';
		}
	}

	/* resolve the remaining non-unique mappings - turn them into unique ones */

	if (num_old <= num_new)				/* square or wide array */
	{
		/****************************************************************************************************
		 *                                                                                                  *
		 * Example for a wide array:                                                                        *
		 *                                                                                                  *
		 *            D.log C.log B.log A.log                                                               *
		 *           ------------------------                                                               *
		 *    3.log | <1>    1     1     1                                                                  *
		 *    2.log |  1    <1>    1     1                                                                  *
		 *    1.log |  1     1    <1>    1                                                                  *
		 *                                                                                                  *
		 * There are 3 files in the old log file list and 4 files in the new log file list.                 *
		 * The mapping is totally non-unique: the old log file '3.log' could have become the new 'D.log' or *
		 * 'C.log', or 'B.log', or 'A.log' - we don't know for sure.                                        *
		 * We make an assumption that a reasonable solution will be to proceed as if '3.log' was renamed to *
		 * 'D.log', '2.log' - to 'C.log' and '1.log' - to 'B.log'.                                          *
		 * We modify the array according to this assumption:                                                *
		 *                                                                                                  *
		 *            D.log C.log B.log A.log                                                               *
		 *           ------------------------                                                               *
		 *    3.log | <1>    0     0     0                                                                  *
		 *    2.log |  0    <1>    0     0                                                                  *
		 *    1.log |  0     0    <1>    0                                                                  *
		 *                                                                                                  *
		 * Now the mapping is unique. The file 'A.log' is counted as a new file to be analyzed from the     *
		 * start.                                                                                           *
		 *                                                                                                  *
		 ****************************************************************************************************/

		for (i = 0; i < num_old; i++)		/* loop over rows from top-left corner */
		{
			char	*p;
			int	j;

			if ('1' == protected_rows[i])
				continue;

			p = old2new + i * num_new;	/* the first element of the current row */

			for (j = 0; j < num_new; j++)
			{
				if (('1' == p[j] || '2' == p[j]) && '1' != protected_cols[j])
				{
					cross_out(old2new, num_old, num_new, i, j, protected_rows, protected_cols);
					break;
				}
			}
		}
	}
	else	/* tall array */
	{
		/****************************************************************************************************
		 *                                                                                                  *
		 * Example for a tall array:                                                                        *
		 *                                                                                                  *
		 *            D.log C.log B.log A.log                                                               *
		 *           ------------------------                                                               *
		 *    6.log |  1     1     1     1                                                                  *
		 *    5.log |  1     1     1     1                                                                  *
		 *    4.log | <1>    1     1     1                                                                  *
		 *    3.log |  1    <1>    1     1                                                                  *
		 *    2.log |  1     1    <1>    1                                                                  *
		 *    1.log |  1     1     1    <1>                                                                 *
		 *                                                                                                  *
		 * There are 6 files in the old log file list and 4 files in the new log file list.                 *
		 * The mapping is totally non-unique: the old log file '6.log' could have become the new 'D.log' or *
		 * 'C.log', or 'B.log', or 'A.log' - we don't know for sure.                                        *
		 * We make an assumption that a reasonable solution will be to proceed as if '1.log' was renamed to *
		 * 'A.log', '2.log' - to 'B.log', '3.log' - to 'C.log', '4.log' - to 'D.log'.                       *
		 * We modify the array according to this assumption:                                                *
		 *                                                                                                  *
		 *            D.log C.log B.log A.log                                                               *
		 *           ------------------------                                                               *
		 *    6.log |  0     0     0     0                                                                  *
		 *    5.log |  0     0     0     0                                                                  *
		 *    4.log | <1>    0     0     0                                                                  *
		 *    3.log |  0    <1>    0     0                                                                  *
		 *    2.log |  0     0    <1>    0                                                                  *
		 *    1.log |  0     0     0    <1>                                                                 *
		 *                                                                                                  *
		 * Now the mapping is unique. Files '6.log' and '5.log' are counted as not present in the new file. *
		 *                                                                                                  *
		 ****************************************************************************************************/

		for (i = num_old - 1; i >= 0; i--)	/* loop over rows from bottom-right corner */
		{
			char	*p;
			int	j;

			if ('1' == protected_rows[i])
				continue;

			p = old2new + i * num_new;	/* the first element of the current row */

			for (j = num_new - 1; j >= 0; j--)
			{
				if (('1' == p[j] || '2' == p[j]) && '1' != protected_cols[j])
				{
					cross_out(old2new, num_old, num_new, i, j, protected_rows, protected_cols);
					break;
				}
			}
		}
	}

	zbx_free(protected_cols);
	zbx_free(protected_rows);
}

/******************************************************************************
 *                                                                            *
 * Purpose: allocate and fill an array of possible mappings from the old log  *
 *          files to the new log files                                        *
 *                                                                            *
 * Parameters:                                                                *
 *     rotation_type - [IN] file rotation type                                *
 *     old_files     - [IN] old file list                                     *
 *     num_old       - [IN] number of elements in the old file list           *
 *     new_files     - [IN] new file list                                     *
 *     num_new       - [IN] number of elements in the new file list           *
 *     use_ino       - [IN] how to use inodes in is_same_file()               *
 *     err_msg       - [IN/OUT] error message why an item became NOTSUPPORTED *
 *                                                                            *
 * Return value: pointer to allocated array or NULL                           *
 *                                                                            *
 * Comments:                                                                  *
 *    The array is filled with '0', '1' and '2'  which mean:                  *
 *       old2new[i][j] = '0' - the i-th old file IS NOT the j-th new file     *
 *       old2new[i][j] = '1' - the i-th old file COULD BE the j-th new file   *
 *       old2new[i][j] = '2' - the j-th new file is a copy of the i-th old    *
 *                             file                                           *
 *                                                                            *
 *    Thread-safe                                                             *
 *                                                                            *
 ******************************************************************************/
static char	*create_old2new_and_copy_of(zbx_log_rotation_options_t rotation_type, struct st_logfile *old_files,
		int num_old, struct st_logfile *new_files, int num_new, int use_ino, char **err_msg)
{
	int		i, j;
	char		*old2new, *p;

	/* set up a two dimensional array of possible mappings from old files to new files */
	old2new = (char *)zbx_malloc(NULL, (size_t)num_new * (size_t)num_old * sizeof(char));
	p = old2new;

	for (i = 0; i < num_old; i++)
	{
		for (j = 0; j < num_new; j++)
		{
			switch (is_same_file_logrt(old_files + i, new_files + j, use_ino, rotation_type, new_files,
					num_new, err_msg))
			{
				case ZBX_SAME_FILE_NO:
					p[j] = '0';
					break;
				case ZBX_SAME_FILE_YES:
					if (1 == old_files[i].retry)
					{
						zabbix_log(LOG_LEVEL_DEBUG, "%s(): the size of log file \"%s\" has been"
								" updated since modification time change, consider"
								" it to be the same file", __func__,
								old_files[i].filename);
						old_files[i].retry = 0;
					}
					p[j] = '1';
					break;
				case ZBX_SAME_FILE_COPY:
					p[j] = '2';
					new_files[j].copy_of = i;
					break;
				case ZBX_SAME_FILE_RETRY:
					old_files[i].retry = 1;
					zbx_free(old2new);
					return NULL;
				case ZBX_SAME_FILE_ERROR:
					zbx_free(old2new);
					return NULL;
			}

			zabbix_log(LOG_LEVEL_DEBUG, "%s(): is_same_file(%s, %s) = %c", __func__,
					old_files[i].filename, new_files[j].filename, p[j]);
		}

		p += (size_t)num_new;
	}

	if (ZBX_LOG_ROTATION_LOGCPT != rotation_type && (1 < num_old || 1 < num_new))
		resolve_old2new(old2new, num_old, num_new);

	return old2new;
}

/******************************************************************************
 *                                                                            *
 * Purpose: find a mapping from old to new file                               *
 *                                                                            *
 * Parameters:                                                                *
 *          old2new - [IN] two dimensional array of possible mappings         *
 *          num_new - [IN] number of elements in the new file list            *
 *          i_old   - [IN] index of the old file                              *
 *                                                                            *
 * Return value: index of the new file or                                     *
 *               -1 if no mapping was found                                   *
 *                                                                            *
 ******************************************************************************/
static int	find_old2new(const char * const old2new, int num_new, int i_old)
{
	int		i;
	const char	*p = old2new + i_old * num_new;

	for (i = 0; i < num_new; i++)		/* loop over columns (new files) on i_old-th row */
	{
		if ('1' == *p || '2' == *p)
			return i;

		p++;
	}

	return -1;
}

/******************************************************************************
 *                                                                            *
 * Purpose: adds information of a logfile to the list of logfiles             *
 *                                                                            *
 * Parameters: logfiles - pointer to the list of logfiles                     *
 *             logfiles_alloc - number of logfiles memory was allocated for   *
 *             logfiles_num - number of already inserted logfiles             *
 *             filename - name of a logfile (with full path)                  *
 *             st - structure returned by stat()                              *
 *                                                                            *
 ******************************************************************************/
static void	add_logfile(struct st_logfile **logfiles, int *logfiles_alloc, int *logfiles_num, const char *filename,
		zbx_stat_t *st)
{
	int	i = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() filename:'%s' mtime:%d size:" ZBX_FS_UI64, __func__, filename,
			(int)st->st_mtime, (zbx_uint64_t)st->st_size);

	if (*logfiles_alloc == *logfiles_num)
	{
		*logfiles_alloc += 64;
		*logfiles = (struct st_logfile *)zbx_realloc(*logfiles,
				(size_t)*logfiles_alloc * sizeof(struct st_logfile));

		zabbix_log(LOG_LEVEL_DEBUG, "%s() logfiles:%p logfiles_alloc:%d",
				__func__, (void *)*logfiles, *logfiles_alloc);
	}

	/************************************************************************************************/
	/* (1) sort by ascending mtimes                                                                 */
	/* (2) if mtimes are equal, sort alphabetically by descending names                             */
	/* the oldest is put first, the most current is at the end                                      */
	/*                                                                                              */
	/*      filename.log.3 mtime3, filename.log.2 mtime2, filename.log1 mtime1, filename.log mtime  */
	/*      --------------------------------------------------------------------------------------  */
	/*      mtime3          <=      mtime2          <=      mtime1          <=      mtime           */
	/*      --------------------------------------------------------------------------------------  */
	/*      filename.log.3  >      filename.log.2   >       filename.log.1  >       filename.log    */
	/*      --------------------------------------------------------------------------------------  */
	/*      array[i=0]             array[i=1]               array[i=2]              array[i=3]      */
	/*                                                                                              */
	/* note: the application is writing into filename.log, mtimes are more important than filenames */
	/************************************************************************************************/

	for (; i < *logfiles_num; i++)
	{
		if (st->st_mtime > (*logfiles)[i].mtime)
			continue;	/* (1) sort by ascending mtime */

		if (st->st_mtime == (*logfiles)[i].mtime)
		{
			int	cmp = strcmp(filename, (*logfiles)[i].filename);

			if (0 > cmp)
				continue;	/* (2) sort by descending name */

			if (0 == cmp)
			{
				/* the file already exists, quite impossible branch */
				zabbix_log(LOG_LEVEL_WARNING, "%s() file '%s' already added", __func__, filename);
				goto out;
			}

			/* filename is smaller, must insert here */
		}

		/* the place is found, move all from the position forward by one struct */
		break;
	}

	if (*logfiles_num > i)
	{
		/* free a gap for inserting the new element */
		memmove((void *)&(*logfiles)[i + 1], (const void *)&(*logfiles)[i],
				(size_t)(*logfiles_num - i) * sizeof(struct st_logfile));
	}

	(*logfiles)[i].filename = zbx_strdup(NULL, filename);
	(*logfiles)[i].mtime = (int)st->st_mtime;
	(*logfiles)[i].seq = 0;
	(*logfiles)[i].retry = 0;
	(*logfiles)[i].incomplete = 0;
	(*logfiles)[i].copy_of = -1;
#if !defined(_WINDOWS) && !defined(__MINGW32__)		/* on MS Windows these attributes are not initialized here */
	(*logfiles)[i].dev = (zbx_uint64_t)st->st_dev;
	(*logfiles)[i].ino_lo = (zbx_uint64_t)st->st_ino;
	(*logfiles)[i].ino_hi = 0;
#endif
	(*logfiles)[i].size = (zbx_uint64_t)st->st_size;
	(*logfiles)[i].processed_size = 0;
	(*logfiles)[i].md5_block_size = -1;
	(*logfiles)[i].last_block_offset = 0;
	/* 'first_block_md5' and 'last_block_md5' are not initialized here */

	++(*logfiles_num);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: release resources allocated to a logfile list                     *
 *                                                                            *
 * Parameters:                                                                *
 *     logfiles       - [IN/OUT] pointer to the list of logfiles, can be NULL *
 *     logfiles_alloc - [IN/OUT] pointer to number of logfiles memory was     *
 *                               allocated for, can be NULL.                  *
 *     logfiles_num   - [IN/OUT] valid pointer to number of inserted logfiles *
 *                                                                            *
 ******************************************************************************/
void	destroy_logfile_list(struct st_logfile **logfiles, int *logfiles_alloc, int *logfiles_num)
{
	int	i;

	for (i = 0; i < *logfiles_num; i++)
		zbx_free((*logfiles)[i].filename);

	*logfiles_num = 0;

	if (NULL != logfiles_alloc)
		*logfiles_alloc = 0;

	zbx_free(*logfiles);
}

/******************************************************************************
 *                                                                            *
 * Purpose: checks if the specified file meets requirements and adds it to    *
 *          the logfile list                                                  *
 *                                                                            *
 * Parameters:                                                                *
 *     directory      - [IN] directory where the logfiles reside              *
 *     filename       - [IN] name of the logfile (without path)               *
 *     mtime          - [IN] selection criterion "logfile modification time"  *
 *                      The logfile will be selected if modified not before   *
 *                      'mtime'.                                              *
 *     re             - [IN] selection criterion "regexp describing filename  *
 *                      pattern"                                              *
 *     logfiles       - [IN/OUT] pointer to the list of logfiles              *
 *     logfiles_alloc - [IN/OUT] number of logfiles memory was allocated for  *
 *     logfiles_num   - [IN/OUT] number of already inserted logfiles          *
 *                                                                            *
 * Comments: This is a helper function for pick_logfiles()                    *
 *                                                                            *
 ******************************************************************************/
static void	pick_logfile(const char *directory, const char *filename, int mtime, const zbx_regexp_t *re,
		struct st_logfile **logfiles, int *logfiles_alloc, int *logfiles_num)
{
	char		*logfile_candidate;
	zbx_stat_t	file_buf;

	logfile_candidate = zbx_dsprintf(NULL, "%s%s", directory, filename);

	if (0 == zbx_stat(logfile_candidate, &file_buf))
	{
		if (S_ISREG(file_buf.st_mode) &&
				mtime <= file_buf.st_mtime &&
				0 == zbx_regexp_match_precompiled(filename, re))
		{
			add_logfile(logfiles, logfiles_alloc, logfiles_num, logfile_candidate, &file_buf);
		}
	}
	else
		zabbix_log(LOG_LEVEL_DEBUG, "cannot process entry '%s': %s", logfile_candidate, zbx_strerror(errno));

	zbx_free(logfile_candidate);
}

/******************************************************************************
 *                                                                            *
 * Purpose: find logfiles in a directory and put them into a list             *
 *                                                                            *
 * Parameters:                                                                *
 *     directory      - [IN] directory where the logfiles reside              *
 *     mtime          - [IN] selection criterion "logfile modification time"  *
 *                      The logfile will be selected if modified not before   *
 *                      'mtime'.                                              *
 *     re             - [IN] selection criterion "regexp describing filename  *
 *                      pattern"                                              *
 *     use_ino        - [OUT] how to use inodes in is_same_file()             *
 *     logfiles       - [IN/OUT] pointer to the list of logfiles              *
 *     logfiles_alloc - [IN/OUT] number of logfiles memory was allocated for  *
 *     logfiles_num   - [IN/OUT] number of already inserted logfiles          *
 *     err_msg        - [IN/OUT] error message why an item became             *
 *                      NOTSUPPORTED                                          *
 *                                                                            *
 * Return value: SUCCEED or FAIL                                              *
 *                                                                            *
 * Comments: This is a helper function for make_logfile_list()                *
 *                                                                            *
 * Comments: Thread-safety - readdir() is a gray area, supposed to work on    *
 *           modern implementations when the directory stream is not shared   *
 *           between threads.                                                 *
 *                                                                            *
 ******************************************************************************/
static int	pick_logfiles(const char *directory, int mtime, const zbx_regexp_t *re, int *use_ino,
		struct st_logfile **logfiles, int *logfiles_alloc, int *logfiles_num, char **err_msg)
{
#if defined(_WINDOWS) || defined(__MINGW32__)
	int			ret = FAIL;
	intptr_t		find_handle;
	struct _wfinddata_t	find_data;

	/* "open" Windows directory */
	char	*find_path = zbx_dsprintf(NULL, "%s*", directory);
	wchar_t	*find_wpath = zbx_utf8_to_unicode(find_path);

	if (-1 == (find_handle = _wfindfirst(find_wpath, &find_data)))
	{
		*err_msg = zbx_dsprintf(*err_msg, "Cannot open directory \"%s\" for reading: %s", directory,
				zbx_strerror(errno));
		zbx_free(find_wpath);
		zbx_free(find_path);
		return FAIL;
	}

	if (SUCCEED != set_use_ino_by_fs_type(find_path, use_ino, err_msg))
		goto clean;

	do
	{
		char	*file_name_utf8 = zbx_unicode_to_utf8(find_data.name);

		pick_logfile(directory, file_name_utf8, mtime, re, logfiles, logfiles_alloc, logfiles_num);
		zbx_free(file_name_utf8);
	}
	while (0 == _wfindnext(find_handle, &find_data));

	ret = SUCCEED;
clean:
	if (-1 == _findclose(find_handle))
	{
		*err_msg = zbx_dsprintf(*err_msg, "Cannot close directory \"%s\": %s", directory, zbx_strerror(errno));
		ret = FAIL;
	}

	zbx_free(find_wpath);
	zbx_free(find_path);

	return ret;
#else
	DIR		*dir = NULL;
	struct dirent	*d_ent = NULL;

	if (NULL == (dir = opendir(directory)))
	{
		*err_msg = zbx_dsprintf(*err_msg, "Cannot open directory \"%s\" for reading: %s", directory,
				zbx_strerror(errno));
		return FAIL;
	}

	/* on UNIX file systems we always assume that inodes can be used to identify files */
	*use_ino = 1;

	while (NULL != (d_ent = readdir(dir)))
		pick_logfile(directory, d_ent->d_name, mtime, re, logfiles, logfiles_alloc, logfiles_num);

	if (-1 == closedir(dir))
	{
		*err_msg = zbx_dsprintf(*err_msg, "Cannot close directory \"%s\": %s", directory, zbx_strerror(errno));
		return FAIL;
	}

	return SUCCEED;
#endif
}

/******************************************************************************
 *                                                                            *
 * Purpose: compile regular expression                                        *
 *                                                                            *
 * Parameters:                                                                *
 *     filename_regexp - [IN] regexp to be compiled                           *
 *     re              - [OUT] compiled regexp                                *
 *     err_msg         - [OUT] error message why regexp could not be          *
 *                       compiled                                             *
 *                                                                            *
 * Return value: SUCCEED or FAIL                                              *
 *                                                                            *
 ******************************************************************************/
static int	compile_filename_regexp(const char *filename_regexp, zbx_regexp_t **re, char **err_msg)
{
	const char	*regexp_err;

	if (SUCCEED != zbx_regexp_compile(filename_regexp, re, &regexp_err))
	{
		*err_msg = zbx_dsprintf(*err_msg, "Cannot compile a regular expression describing filename pattern: %s",
				regexp_err);
		zbx_regexp_err_msg_free(regexp_err);
		return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: fill-in MD5 sums, device and inode numbers for files in the list  *
 *                                                                            *
 * Parameters:                                                                *
 *     logfiles     - [IN/OUT] list of log files                              *
 *     logfiles_num - [IN] number of elements in 'logfiles'                   *
 *     use_ino      - [IN] how to get file IDs in file_id()                   *
 *     err_msg      - [IN/OUT] error message why operation failed             *
 *                                                                            *
 * Return value: SUCCEED or FAIL                                              *
 *                                                                            *
 * Comments: Thread-safe                                                      *
 *                                                                            *
 ******************************************************************************/
#if defined(_WINDOWS) || defined(__MINGW32__)
static int	fill_file_details(struct st_logfile *logfiles, int logfiles_num, int use_ino, char **err_msg)
#else
static int	fill_file_details(struct st_logfile *logfiles, int logfiles_num, char **err_msg)
#endif
{
	int	i, ret = SUCCEED;

	/* Fill in MD5 sums and file indexes in the logfile list. */
	/* These operations require opening of file, therefore we group them together. */

	for (i = 0; i < logfiles_num; i++)
	{
		int			f;
		struct st_logfile	*p = logfiles + i;

		if (-1 == (f = open_file_helper(p->filename, err_msg)))
			return FAIL;

		/* get MD5 sums of the first and the last blocks */

		p->md5_block_size = (int)MIN(MAX_LEN_MD5, p->size);

		if (SUCCEED != (ret = file_part_md5(f, 0, p->md5_block_size, p->first_block_md5, p->filename,
				err_msg)))
		{
			goto clean;
		}

		if (0 < (p->last_block_offset = p->size - (size_t)p->md5_block_size))
		{
			if (SUCCEED != (ret = file_part_md5(f, p->last_block_offset, p->md5_block_size,
					p->last_block_md5, p->filename, err_msg)))
			{
				goto clean;
			}
		}
		else	/* file is small, set the last block MD5 equal to the first block's one */
			memcpy(p->last_block_md5, p->first_block_md5, sizeof(p->last_block_md5));

#if defined(_WINDOWS) || defined(__MINGW32__)
		ret = file_id(f, use_ino, &p->dev, &p->ino_lo, &p->ino_hi, p->filename, err_msg);
#endif	/*_WINDOWS*/
clean:
		if (SUCCEED != close_file_helper(f, p->filename, err_msg) || FAIL == ret)
			return FAIL;
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: select log files to be analyzed and make a list, set 'use_ino'    *
 *          parameter                                                         *
 *                                                                            *
 * Parameters:                                                                *
 *     flags          - [IN] bit flags with item type: log, logrt, log.count  *
 *                      or logrt.count                                        *
 *     filename       - [IN] logfile name (regular expression with a path)    *
 *     mtime          - [IN] last modification time of the file               *
 *     logfiles       - [IN/OUT] pointer to the list of logfiles              *
 *     logfiles_alloc - [IN/OUT] number of logfiles memory was allocated for  *
 *     logfiles_num   - [IN/OUT] number of already inserted logfiles          *
 *     use_ino        - [IN/OUT] how to use inode numbers                     *
 *     err_msg        - [IN/OUT] error message (if FAIL or ZBX_NO_FILE_ERROR  *
 *                      is returned)                                          *
 *                                                                            *
 * Return value: SUCCEED - file list successfully built,                      *
 *               ZBX_NO_FILE_ERROR - file(s) do not exist,                    *
 *               FAIL - other errors                                          *
 *                                                                            *
 * Comments: Supposed to be thread-safe, see pick_logfiles() comments.        *
 *                                                                            *
 ******************************************************************************/
static int	make_logfile_list(unsigned char flags, const char *filename, int mtime,
		struct st_logfile **logfiles, int *logfiles_alloc, int *logfiles_num, int *use_ino, char **err_msg)
{
	int	ret = SUCCEED;

	if (0 != (ZBX_METRIC_FLAG_LOG_LOG & flags))	/* log[] or log.count[] item */
	{
		zbx_stat_t	file_buf;

		if (0 != zbx_stat(filename, &file_buf))
		{
			*err_msg = zbx_dsprintf(*err_msg, "Cannot obtain information for file \"%s\": %s", filename,
					zbx_strerror(errno));
			ret = ZBX_NO_FILE_ERROR;
			goto clean;
		}

		if (!S_ISREG(file_buf.st_mode))
		{
			*err_msg = zbx_dsprintf(*err_msg, "\"%s\" is not a regular file.", filename);
			ret = FAIL;
			goto clean;
		}

		/* mtime is not used for log, log.count items, reset to ignore */
		file_buf.st_mtime = 0;

		add_logfile(logfiles, logfiles_alloc, logfiles_num, filename, &file_buf);
#if defined(_WINDOWS) || defined(__MINGW32__)
		if (SUCCEED != (ret = set_use_ino_by_fs_type(filename, use_ino, err_msg)))
			goto clean;
#else
		/* on UNIX file systems we always assume that inodes can be used to identify files */
		*use_ino = 1;
#endif
	}
	else if (0 != (ZBX_METRIC_FLAG_LOG_LOGRT & flags))	/* logrt[] or logrt.count[] item */
	{
		char	*directory = NULL, *filename_regexp = NULL;
		zbx_regexp_t	*re;

		/* split a filename into directory and file name regular expression parts */
		if (SUCCEED != (ret = split_filename(filename, &directory, &filename_regexp, err_msg)))
			goto clean;

		if (SUCCEED != (ret = compile_filename_regexp(filename_regexp, &re, err_msg)))
			goto clean1;

		if (SUCCEED != (ret = pick_logfiles(directory, mtime, re, use_ino, logfiles, logfiles_alloc,
				logfiles_num, err_msg)))
		{
			goto clean2;
		}

		if (0 == *logfiles_num)
		{
			/* do not make logrt[] and logrt.count[] items NOTSUPPORTED if there are no matching log */
			/* files or they are not accessible (can happen during a rotation), just log the problem */
#if defined(_WINDOWS) || defined(__MINGW32__)
			zabbix_log(LOG_LEVEL_WARNING, "there are no recently modified files matching \"%s\" in \"%s\"",
					filename_regexp, directory);

			ret = ZBX_NO_FILE_ERROR;
#else
			if (0 != access(directory, X_OK))
			{
				zabbix_log(LOG_LEVEL_WARNING, "insufficient access rights (no \"execute\" permission) "
						"to directory \"%s\": %s", directory, zbx_strerror(errno));
				/* No access could be a transient condition if file rotation is manipulating */
				/* directories. Therefore 'ret' is not set to FAIL or ZBX_NO_FILE_ERROR here. */
			}
			else
			{
				zabbix_log(LOG_LEVEL_WARNING, "there are no recently modified files matching \"%s\" in"
						" \"%s\"", filename_regexp, directory);
				ret = ZBX_NO_FILE_ERROR;
			}
#endif
		}
clean2:
		zbx_regexp_free(re);
clean1:
		zbx_free(directory);
		zbx_free(filename_regexp);

		if (FAIL == ret || ZBX_NO_FILE_ERROR == ret)
			goto clean;
	}
	else
	{
		THIS_SHOULD_NEVER_HAPPEN;
		*err_msg = zbx_dsprintf(*err_msg, "%s(): internal error: invalid flags:%hhu", __func__, flags);
		ret = FAIL;
		goto clean;
	}

#if defined(_WINDOWS) || defined(__MINGW32__)
	ret = fill_file_details(*logfiles, *logfiles_num, *use_ino, err_msg);
#else
	ret = fill_file_details(*logfiles, *logfiles_num, err_msg);
#endif
clean:
	if ((FAIL == ret || ZBX_NO_FILE_ERROR == ret) && NULL != *logfiles)
		destroy_logfile_list(logfiles, logfiles_alloc, logfiles_num);

	return	ret;
}

static char	*buf_find_newline(char *p, char **p_next, const char *p_end, const char *cr, const char *lf,
		size_t szbyte)
{
	if (1 == szbyte)	/* single-byte character set */
	{
		for (; p < p_end; p++)
		{
			/* detect NULL byte and replace it with '?' character */
			if (0x0 == *p)
			{
				*p = '?';
				continue;
			}

			if (0xd < *p || 0xa > *p)
				continue;

			if (0xa == *p)  /* LF (Unix) */
			{
				*p_next = p + 1;
				return p;
			}

			if (0xd == *p)	/* CR (Mac) */
			{
				if (p < p_end - 1 && 0xa == *(p + 1))   /* CR+LF (Windows) */
				{
					*p_next = p + 2;
					return p;
				}

				*p_next = p + 1;
				return p;
			}
		}
		return (char *)NULL;
	}
	else
	{
		while (p <= p_end - szbyte)
		{
			/* detect NULL byte in UTF-16 encoding and replace it with '?' character */
			if (2 == szbyte && 0x0 == *p && 0x0 == *(p + 1))
			{
				if (0x0 == *cr)			/* Big-endian */
					p[1] = '?';
				else				/* Little-endian */
					*p = '?';
			}

			if (0 == memcmp(p, lf, szbyte))		/* LF (Unix) */
			{
				*p_next = p + szbyte;
				return p;
			}

			if (0 == memcmp(p, cr, szbyte))		/* CR (Mac) */
			{
				if (p <= p_end - szbyte - szbyte && 0 == memcmp(p + szbyte, lf, szbyte))
				{
					/* CR+LF (Windows) */
					*p_next = p + szbyte + szbyte;
					return p;
				}

				*p_next = p + szbyte;
				return p;
			}

			p += szbyte;
		}
		return (char *)NULL;
	}
}

static int	zbx_match_log_rec(const zbx_vector_ptr_t *regexps, const char *value, const char *pattern,
		const char *output_template, char **output, char **err_msg)
{
	int	ret;

	if (FAIL == (ret = regexp_sub_ex(regexps, value, pattern, ZBX_CASE_SENSITIVE, output_template, output)))
		*err_msg = zbx_dsprintf(*err_msg, "cannot compile regular expression");

	return ret;	/* ZBX_REGEXP_MATCH, ZBX_REGEXP_NO_MATCH or FAIL */
}

/******************************************************************************
 *                                                                            *
 * Comments: Thread-safe                                                      *
 *                                                                            *
 ******************************************************************************/
static int	zbx_read2(int fd, unsigned char flags, struct st_logfile *logfile, zbx_uint64_t *lastlogsize,
		const int *mtime, int *big_rec, const char *encoding, zbx_vector_ptr_t *regexps, const char *pattern,
		const char *output_template, int *p_count, int *s_count, zbx_process_value_func_t process_value,
		zbx_vector_ptr_t *addrs, zbx_vector_ptr_t *agent2_result, const char *hostname, const char *key,
		zbx_uint64_t *lastlogsize_sent, int *mtime_sent, const char *persistent_file_name,
		zbx_vector_pre_persistent_t *prep_vec, char **err_msg)
{
	static ZBX_THREAD_LOCAL char	*buf = NULL;

	int				ret, nbytes, regexp_ret;
	const char			*cr, *lf, *p_end;
	char				*p_start, *p, *p_nl, *p_next, *item_value = NULL;
	size_t				szbyte;
	zbx_offset_t			offset;
	const int			is_count_item = (0 != (ZBX_METRIC_FLAG_LOG_COUNT & flags)) ? 1 : 0;
#if !defined(_WINDOWS) && !defined(__MINGW32__)
	int				prep_vec_idx = -1;	/* index in 'prep_vec' vector */
#endif
	zbx_uint64_t			processed_size;

#define BUF_SIZE	(256 * ZBX_KIBIBYTE)	/* The longest encodings use 4 bytes for every character. To send */
						/* up to 64 k characters to Zabbix server a 256 kB buffer might be */
						/* required. */
	if (NULL == buf)
		buf = (char *)zbx_malloc(buf, (size_t)(BUF_SIZE + 1));

	find_cr_lf_szbyte(encoding, &cr, &lf, &szbyte);

	for (;;)
	{
		if (0 >= *p_count || 0 >= *s_count)
		{
			/* limit on number of processed or sent-to-server lines reached */
			ret = SUCCEED;
			goto out;
		}

		if ((zbx_offset_t)-1 == (offset = zbx_lseek(fd, 0, SEEK_CUR)))
		{
			*big_rec = 0;
			*err_msg = zbx_dsprintf(*err_msg, "Cannot set position to 0 in file: %s", zbx_strerror(errno));
			ret = FAIL;
			goto out;
		}

		if (-1 == (nbytes = (int)read(fd, buf, (size_t)BUF_SIZE)))
		{
			/* error on read */
			*big_rec = 0;
			*err_msg = zbx_dsprintf(*err_msg, "Cannot read from file: %s", zbx_strerror(errno));
			ret = FAIL;
			goto out;
		}

		if (0 == nbytes)
		{
			/* end of file reached */
			ret = SUCCEED;
			goto out;
		}

		p_start = buf;			/* beginning of current line */
		p = buf;			/* current byte */
		p_end = buf + (size_t)nbytes;	/* no data from this position */

		if (NULL == (p_nl = buf_find_newline(p, &p_next, p_end, cr, lf, szbyte)))
		{
			if (p_end > p)
				logfile->incomplete = 1;

			if (BUF_SIZE > nbytes)
			{
				/* Buffer is not full (no more data available) and there is no "newline" in it. */
				/* Do not analyze it now, keep the same position in the file and wait the next check, */
				/* maybe more data will come. */

				*lastlogsize = (zbx_uint64_t)offset;
				ret = SUCCEED;
				goto out;
			}
			else
			{
				/* buffer is full and there is no "newline" in it */

				if (0 == *big_rec)
				{
					/* It is the first, beginning part of a long record. Match it against the */
					/* regexp now (our buffer length corresponds to what we can save in the */
					/* database). */

					char	*value;
					int	send_err;

					buf[BUF_SIZE] = '\0';

					if ('\0' != *encoding)
						value = convert_to_utf8(buf, (size_t)BUF_SIZE, encoding);
					else
						value = buf;

					zabbix_log(LOG_LEVEL_WARNING, "Logfile contains a large record: \"%.64s\""
							" (showing only the first 64 characters). Only the first 256 kB"
							" will be analyzed, the rest will be ignored while Zabbix agent"
							" is running.", value);

					processed_size = (size_t)offset + (size_t)nbytes;
					send_err = FAIL;

					regexp_ret = zbx_match_log_rec(regexps, value, pattern,
							(0 == is_count_item) ? output_template : NULL,
							(0 == is_count_item) ? &item_value : NULL, err_msg);
#if !defined(_WINDOWS) && !defined(__MINGW32__)
					if (NULL != persistent_file_name && (ZBX_REGEXP_MATCH == regexp_ret ||
							ZBX_REGEXP_NO_MATCH == regexp_ret))
					{
						/* Prepare 'prep_vec' element even if the current record won't match. */
						/* Its mtime and lastlogsize could be sent to server later as */
						/* metadata update, then a persistent file should be written. */
						/* 'prep_vec' can be emptied at any call to process_value() which */
						/* calls send_buffer(), so be ready to reinitialize. */
						if (-1 == prep_vec_idx || 0 == prep_vec->values_num)
						{
							prep_vec_idx = zbx_find_or_create_prep_vec_element(prep_vec,
									key, persistent_file_name);
							zbx_init_prep_vec_data(logfile,
									prep_vec->values + prep_vec_idx);
						}

						zbx_update_prep_vec_data(logfile, processed_size,
								prep_vec->values + prep_vec_idx);
					}
#else
					ZBX_UNUSED(persistent_file_name);
					ZBX_UNUSED(prep_vec);
#endif
					if (ZBX_REGEXP_MATCH == regexp_ret)
					{
						if (0 == is_count_item)		/* log[] or logrt[] */
						{
							if (SUCCEED == (send_err = process_value(addrs, agent2_result,
									hostname, key, item_value, ITEM_STATE_NORMAL,
									&processed_size, mtime, NULL, NULL, NULL, NULL,
									flags | ZBX_METRIC_FLAG_PERSISTENT)))
							{
								*lastlogsize_sent = processed_size;

								if (NULL != mtime_sent)
									*mtime_sent = *mtime;

								(*s_count)--;
								zbx_free(item_value);
							}
							else
							{
								zbx_free(item_value);

								if ('\0' != *encoding)
									zbx_free(value);

								/* Sending of buffer failed. */
								/* Try to resend it in the next check. */
								ret = SUCCEED;
								goto out;
							}
						}
						else	/* log.count[] or logrt.count[] */
							(*s_count)--;
					}

					if ('\0' != *encoding)
						zbx_free(value);

					if (FAIL == regexp_ret)
					{
						ret = FAIL;
						goto out;
					}

					(*p_count)--;

					if (0 != is_count_item ||
							ZBX_REGEXP_NO_MATCH == regexp_ret || SUCCEED == send_err)
					{
						*lastlogsize = processed_size;
						*big_rec = 1;	/* ignore the rest of this record */
					}
				}
				else
				{
					/* It is a middle part of a long record. Ignore it. We have already */
					/* checked the first part against the regexp. */
					*lastlogsize = (size_t)offset + (size_t)nbytes;
				}
			}
		}
		else
		{
			/* the "newline" was found, so there is at least one complete record */
			/* (or trailing part of a large record) in the buffer */
			logfile->incomplete = 0;

			for (;;)
			{
				if (0 >= *p_count || 0 >= *s_count)
				{
					/* limit on number of processed or sent-to-server lines reached */
					ret = SUCCEED;
					goto out;
				}

				if (0 == *big_rec)
				{
					char	*value;
					int	send_err;

					*p_nl = '\0';

					if ('\0' != *encoding)
						value = convert_to_utf8(p_start, (size_t)(p_nl - p_start), encoding);
					else
						value = p_start;

					processed_size = (size_t)offset + (size_t)(p_next - buf);
					send_err = FAIL;

					regexp_ret = zbx_match_log_rec(regexps, value, pattern,
							(0 == is_count_item) ? output_template : NULL,
							(0 == is_count_item) ? &item_value : NULL, err_msg);
#if !defined(_WINDOWS) && !defined(__MINGW32__)
					if (NULL != persistent_file_name && (ZBX_REGEXP_MATCH == regexp_ret ||
							ZBX_REGEXP_NO_MATCH == regexp_ret))
					{
						/* Prepare 'prep_vec' element even if the current record won't match. */
						/* Its mtime and lastlogsize could be sent to server later as */
						/* metadata update, then a persistent file should be written. */
						/* 'prep_vec' can be emptied at any call to process_value() which */
						/* calls send_buffer(), so be ready to reinitialize. */
						if (-1 == prep_vec_idx || 0 == prep_vec->values_num)
						{
							prep_vec_idx = zbx_find_or_create_prep_vec_element(prep_vec,
									key, persistent_file_name);
							zbx_init_prep_vec_data(logfile,
									prep_vec->values + prep_vec_idx);
						}

						zbx_update_prep_vec_data(logfile, processed_size,
								prep_vec->values + prep_vec_idx);
					}
#endif
					if (ZBX_REGEXP_MATCH == regexp_ret)
					{
						if (0 == is_count_item)		/* log[] or logrt[] */
						{
							if (SUCCEED == (send_err = process_value(addrs, agent2_result,
									hostname, key, item_value, ITEM_STATE_NORMAL,
									&processed_size, mtime, NULL, NULL, NULL, NULL,
									flags | ZBX_METRIC_FLAG_PERSISTENT)))
							{
								*lastlogsize_sent = processed_size;

								if (NULL != mtime_sent)
									*mtime_sent = *mtime;

								(*s_count)--;
								zbx_free(item_value);
							}
							else
							{
								zbx_free(item_value);

								if ('\0' != *encoding)
									zbx_free(value);

								/* Sending of buffer failed. */
								/* Try to resend it in the next check. */
								ret = SUCCEED;
								goto out;
							}
						}
						else	/* log.count[] or logrt.count[] */
							(*s_count)--;
					}

					if ('\0' != *encoding)
						zbx_free(value);

					if (FAIL == regexp_ret)
					{
						ret = FAIL;
						goto out;
					}

					(*p_count)--;

					if (0 != is_count_item ||
							ZBX_REGEXP_NO_MATCH == regexp_ret || SUCCEED == send_err)
					{
						*lastlogsize = processed_size;
					}
				}
				else
				{
					/* skip the trailing part of a long record */
					*lastlogsize = (size_t)offset + (size_t)(p_next - buf);
					*big_rec = 0;
				}

				/* move to the next record in the buffer */
				p_start = p_next;
				p = p_next;

				if (NULL == (p_nl = buf_find_newline(p, &p_next, p_end, cr, lf, szbyte)))
				{
					/* There are no complete records in the buffer. */
					/* Try to read more data from this position if available. */
					if (p_end > p)
						logfile->incomplete = 1;

					if ((zbx_offset_t)-1 == zbx_lseek(fd, *lastlogsize, SEEK_SET))
					{
						*err_msg = zbx_dsprintf(*err_msg, "Cannot set position to " ZBX_FS_UI64
								" in file: %s", *lastlogsize, zbx_strerror(errno));
						ret = FAIL;
						goto out;
					}
					else
						break;
				}
				else
					logfile->incomplete = 0;
			}
		}
	}
out:
	return ret;

#undef BUF_SIZE
}

/******************************************************************************
 *                                                                            *
 * Purpose: Match new records in logfile with regexp, transmit matching       *
 *          records to Zabbix server                                          *
 *                                                                            *
 * Parameters:                                                                *
 *     flags           - [IN] bit flags with item type: log, logrt, log.count *
 *                       or logrt.count                                       *
 *     logfile         - [IN/OUT] logfile attributes                          *
 *     lastlogsize     - [IN/OUT] offset from the beginning of the file       *
 *     mtime           - [IN/OUT] file modification time for reporting to     *
 *                       server                                               *
 *     lastlogsize_sent - [OUT] lastlogsize value that was last sent          *
 *     mtime_sent      - [OUT] mtime value that was last sent                 *
 *     skip_old_data   - [IN/OUT] start from the beginning of the file or     *
 *                       jump to the end                                      *
 *     big_rec         - [IN/OUT] state variable to remember whether a long   *
 *                       record is being processed                            *
 *     encoding        - [IN] text string describing encoding.                *
 *                       See function find_cr_lf_szbyte() for supported       *
 *                       encodings.                                           *
 *                       "" (empty string) means a single-byte character set  *
 *                       (e.g. ASCII).                                        *
 *     regexps         - [IN] array of regexps                                *
 *     pattern         - [IN] pattern to match                                *
 *     output_template - [IN] output formatting template                      *
 *     p_count         - [IN/OUT] limit of records to be processed            *
 *     s_count         - [IN/OUT] limit of records to be sent to server       *
 *     process_value   - [IN] pointer to function process_value()             *
 *     addrs           - [IN] vector for passing server and port where to     *
 *                            send data                                       *
 *     agent2_result   - [IN] address of buffer where to store matching log   *
 *                            records (used only in Agent2)                   *
 *     hostname        - [IN] hostname the data comes from                    *
 *     key             - [IN] item key the data belongs to                    *
 *     processed_bytes - [OUT] number of processed bytes in logfile           *
 *     seek_offset     - [IN] position to seek in file                        *
 *     persistent_file_name - [IN] name of file for saving persistent data    *
 *     prep_vec        - [IN/OUT] vector with data for writing into           *
 *                                persistent files                            *
 *     err_msg         - [IN/OUT] error message why an item became            *
 *                       NOTSUPPORTED                                         *
 *                                                                            *
 * Return value: returns SUCCEED on successful reading,                       *
 *               FAIL on other cases                                          *
 *                                                                            *
 * Comments:                                                                  *
 *           This function does not deal with log file rotation.              *
 *                                                                            *
 *           Thread-safe                                                      *
 *                                                                            *
 ******************************************************************************/
static int	process_log(unsigned char flags, struct st_logfile *logfile, zbx_uint64_t *lastlogsize, int *mtime,
		zbx_uint64_t *lastlogsize_sent, int *mtime_sent, unsigned char *skip_old_data, int *big_rec,
		const char *encoding, zbx_vector_ptr_t *regexps, const char *pattern, const char *output_template,
		int *p_count, int *s_count, zbx_process_value_func_t process_value, zbx_vector_ptr_t *addrs,
		zbx_vector_ptr_t *agent2_result, const char *hostname, const char *key, zbx_uint64_t *processed_bytes,
		zbx_uint64_t seek_offset, const char *persistent_file_name, zbx_vector_pre_persistent_t *prep_vec,
		char **err_msg)
{
	int	f, ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() filename:'%s' lastlogsize:" ZBX_FS_UI64 " mtime:%d seek_offset:"
			ZBX_FS_UI64, __func__, logfile->filename, *lastlogsize, NULL != mtime ? *mtime : 0,
			seek_offset);

	if (-1 == (f = open_file_helper(logfile->filename, err_msg)))
		goto out;

	if ((zbx_offset_t)-1 != zbx_lseek(f, seek_offset, SEEK_SET))
	{
		*lastlogsize = seek_offset;
		*skip_old_data = 0;

		if (SUCCEED == (ret = zbx_read2(f, flags, logfile, lastlogsize, mtime, big_rec, encoding, regexps,
				pattern, output_template, p_count, s_count, process_value, addrs, agent2_result,
				hostname, key, lastlogsize_sent, mtime_sent, persistent_file_name, prep_vec, err_msg)))
		{
			*processed_bytes = *lastlogsize - seek_offset;
		}
	}
	else
	{
		*err_msg = zbx_dsprintf(*err_msg, "Cannot set position to " ZBX_FS_UI64 " in file \"%s\": %s",
				seek_offset, logfile->filename, zbx_strerror(errno));
	}

	if (SUCCEED != close_file_helper(f, logfile->filename, err_msg))
		ret = FAIL;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() filename:'%s' lastlogsize:" ZBX_FS_UI64 " mtime:%d ret:%s"
			" processed_bytes:" ZBX_FS_UI64, __func__, logfile->filename, *lastlogsize,
			NULL != mtime ? *mtime : 0, zbx_result_string(ret),
			SUCCEED == ret ? *processed_bytes : (zbx_uint64_t)0);

	return ret;
}

static void	adjust_mtime_to_clock(int *mtime)
{
	time_t	now;

	/* Adjust 'mtime' if the system clock has been set back in time. */
	/* Setting the clock ahead of time is harmless in our case. */

	if (*mtime > (now = time(NULL)))
	{
		int	old_mtime;

		old_mtime = *mtime;
		*mtime = (int)now;

		zabbix_log(LOG_LEVEL_WARNING, "System clock has been set back in time. Setting agent mtime %d "
				"seconds back.", (int)(old_mtime - now));
	}
}

static int	is_swap_required(const struct st_logfile *old_files, struct st_logfile *new_files, int use_ino, int idx)
{
	int	is_same_place;

	/* if the 1st file is not processed at all while the 2nd file was processed (at least partially) */
	/* then swap them */
	if (0 == new_files[idx].seq && 0 < new_files[idx + 1].seq)
		return SUCCEED;

	/* if the 2nd file is not a copy of some other file then no need to swap */
	if (-1 == new_files[idx + 1].copy_of)
		return FAIL;

	/* The 2nd file is a copy. But is it a copy of the 1st file ? */

	/* On file systems with inodes or file indices if a file is copied and truncated, we assume that */
	/* there is a high possibility that the truncated file has the same inode (index) as before. */

	if (NULL == old_files)	/* cannot consult the old file list */
		return FAIL;

	is_same_place = compare_file_places(old_files + new_files[idx + 1].copy_of, new_files + idx, use_ino);

	if (ZBX_FILE_PLACE_SAME == is_same_place && new_files[idx].seq >= new_files[idx + 1].seq)
		return SUCCEED;

	/* The last attempt - compare file names. It is less reliable as file rotation can change file names. */
	if (ZBX_FILE_PLACE_OTHER == is_same_place || ZBX_FILE_PLACE_UNKNOWN == is_same_place)
	{
		if (0 == strcmp((old_files + new_files[idx + 1].copy_of)->filename, (new_files + idx)->filename))
			return SUCCEED;
	}

	return FAIL;
}

static void	swap_logfile_array_elements(struct st_logfile *array, int idx1, int idx2)
{
	struct st_logfile	*p1 = array + idx1;
	struct st_logfile	*p2 = array + idx2;
	struct st_logfile	tmp;

	memcpy(&tmp, p1, sizeof(struct st_logfile));
	memcpy(p1, p2, sizeof(struct st_logfile));
	memcpy(p2, &tmp, sizeof(struct st_logfile));
}

static void	ensure_order_if_mtimes_equal(const struct st_logfile *logfiles_old, struct st_logfile *logfiles,
		int logfiles_num, int use_ino, int *start_idx)
{
	int	i;

	/* There is a special case when within 1 second of time:       */
	/*   1. a log file ORG.log is copied to other file COPY.log,   */
	/*   2. the original file ORG.log is truncated,                */
	/*   3. new records are appended to the original file ORG.log, */
	/*   4. both files ORG.log and COPY.log have the same 'mtime'. */
	/* Now in the list 'logfiles' the file ORG.log precedes the COPY.log because if 'mtime' is the same   */
	/* then add_logfile() function sorts files by name in descending order. This would lead to an error - */
	/* processing ORG.log before COPY.log. We need to correct the order by swapping ORG.log and COPY.log  */
	/* elements in the 'logfiles' list. */

	for (i = 0; i < logfiles_num - 1; i++)
	{
		if (logfiles[i].mtime == logfiles[i + 1].mtime &&
				SUCCEED == is_swap_required(logfiles_old, logfiles, use_ino, i))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "ensure_order_if_mtimes_equal() swapping files '%s' and '%s'",
					logfiles[i].filename, logfiles[i + 1].filename);

			swap_logfile_array_elements(logfiles, i, i + 1);

			if (*start_idx == i + 1)
				*start_idx = i;
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: compare MD5 sums of first and last blocks between 2 files. If MD5 *
 *          sums have been calculated for blocks of different sizes or        *
 *          offsets then open the log file with larger size of blocks and get *
 *          MD5 for the smaller size                                          *
 *                                                                            *
 * Parameters:                                                                *
 *     log1 - [IN] log file 1 attributes                                      *
 *     log2 - [IN] log file 2 attributes                                      *
 *                                                                            *
 * Return value: SUCCEED or FAIL                                              *
 *                                                                            *
 * Comments: Use this function to compare log files from the 'new' file list. *
 *           DO NOT use it with a file which belongs to the 'old' list (the   *
 *           old file name now could point to different file)                 *
 *                                                                            *
 ******************************************************************************/
static int	files_have_same_blocks_md5(const struct st_logfile *log1, const struct st_logfile *log2)
{
	if (-1 == log1->md5_block_size || -1 == log2->md5_block_size)
		return FAIL;

	if (log1->md5_block_size == log2->md5_block_size)	/* this works for empty files, too */
	{
		if (0 != memcmp(log1->first_block_md5, log2->first_block_md5, sizeof(log1->first_block_md5)))
			return FAIL;

		if (log1->last_block_offset == log2->last_block_offset)
		{
			if (0 != memcmp(log1->last_block_md5, log2->last_block_md5, sizeof(log1->last_block_md5)))
				return FAIL;

			return SUCCEED;
		}
	}

	/* we have MD5 sums but they were calculated from blocks of different sizes or offsets */

	if (0 < log1->md5_block_size && 0 < log2->md5_block_size)
	{
		const struct st_logfile	*file_smaller, *file_larger;
		int			fd, ret = FAIL;
		char			*err_msg = NULL;		/* required, but not used */
		md5_byte_t		md5tmp[ZBX_MD5_DIGEST_SIZE];

		if (log1->md5_block_size < log2->md5_block_size)
		{
			file_smaller = log1;
			file_larger = log2;
		}
		else
		{
			file_smaller = log2;
			file_larger = log1;
		}

		if (-1 == (fd = zbx_open(file_larger->filename, O_RDONLY)))
			return FAIL;

		if (SUCCEED != file_part_md5(fd, 0, file_smaller->md5_block_size, md5tmp, "", &err_msg))
			goto clean;

		if (0 != memcmp(file_smaller->first_block_md5, md5tmp, sizeof(md5tmp)))
			goto clean;

		if (0 == file_smaller->last_block_offset)
		{
			ret = SUCCEED;
			goto clean;
		}

		if (SUCCEED != file_part_md5(fd, file_smaller->last_block_offset, file_smaller->md5_block_size, md5tmp,
				"", &err_msg))
		{
			goto clean;
		}

		if (0 == memcmp(file_smaller->last_block_md5, md5tmp, sizeof(md5tmp)))
			ret = SUCCEED;
clean:
		zbx_free(err_msg);
		close(fd);

		return ret;
	}

	return FAIL;
}

static void	handle_multiple_copies(struct st_logfile *logfiles, int logfiles_num, int i)
{
	/* There is a special case when the latest log file is copied to other file but not yet truncated. */
	/* So there are two files and we don't know which one will stay as the copy and which one will be  */
	/* truncated. Similar cases: the latest log file is copied but never truncated or is copied multiple */
	/* times. */

	int	j;

	for (j = i + 1; j < logfiles_num; j++)
	{
		if (SUCCEED == files_have_same_blocks_md5(logfiles + i, logfiles + j))
		{
			/* logfiles[i] and logfiles[j] are original and copy (or vice versa). */
			/* If logfiles[i] has been at least partially processed then transfer its */
			/* processed size to logfiles[j], too. */

			if (logfiles[j].processed_size < logfiles[i].processed_size)
			{
				logfiles[j].processed_size = MIN(logfiles[i].processed_size, logfiles[j].size);

				zabbix_log(LOG_LEVEL_DEBUG, "handle_multiple_copies() file '%s' processed_size:"
						ZBX_FS_UI64 " transferred to" " file '%s' processed_size:" ZBX_FS_UI64,
						logfiles[i].filename, logfiles[i].processed_size,
						logfiles[j].filename, logfiles[j].processed_size);
			}
			else if (logfiles[i].processed_size < logfiles[j].processed_size)
			{
				logfiles[i].processed_size = MIN(logfiles[j].processed_size, logfiles[i].size);

				zabbix_log(LOG_LEVEL_DEBUG, "handle_multiple_copies() file '%s' processed_size:"
						ZBX_FS_UI64 " transferred to" " file '%s' processed_size:" ZBX_FS_UI64,
						logfiles[j].filename, logfiles[j].processed_size,
						logfiles[i].filename, logfiles[i].processed_size);
			}
		}
	}
}

static void	delay_update_if_copies(struct st_logfile *logfiles, int logfiles_num, int *mtime,
		zbx_uint64_t *lastlogsize)
{
	int	i, idx_to_keep = logfiles_num - 1;

	/* If there are copies in 'logfiles' list then find the element with the smallest index which must be */
	/* preserved in the list to keep information about copies. */

	for (i = 0; i < logfiles_num - 1; i++)
	{
		int	j, largest_for_i = -1;

		if (0 == logfiles[i].size)
			continue;

		for (j = i + 1; j < logfiles_num; j++)
		{
			if (0 == logfiles[j].size)
				continue;

			if (SUCCEED == files_have_same_blocks_md5(logfiles + i, logfiles + j))
			{
				int	more_processed;

				/* logfiles[i] and logfiles[j] are original and copy (or vice versa) */

				more_processed = (logfiles[i].processed_size > logfiles[j].processed_size) ? i : j;

				if (largest_for_i < more_processed)
					largest_for_i = more_processed;
			}
		}

		if (-1 != largest_for_i && idx_to_keep > largest_for_i)
			idx_to_keep = largest_for_i;
	}

	if (logfiles[idx_to_keep].mtime < *mtime)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "delay_update_if_copies(): setting mtime back from %d to %d,"
				" lastlogsize from " ZBX_FS_UI64 " to " ZBX_FS_UI64, *mtime,
				logfiles[idx_to_keep].mtime, *lastlogsize, logfiles[idx_to_keep].processed_size);

		/* ensure that next time element 'idx_to_keep' is included in file list with the right 'lastlogsize' */
		*mtime = logfiles[idx_to_keep].mtime;
		*lastlogsize = logfiles[idx_to_keep].processed_size;

		if (logfiles_num - 1 > idx_to_keep)
		{
			/* ensure that next time processing starts from element'idx_to_keep' */
			for (i = idx_to_keep + 1; i < logfiles_num; i++)
				logfiles[i].seq = 0;
		}
	}
}

static zbx_uint64_t	max_processed_size_in_copies(const struct st_logfile *logfiles, int logfiles_num, int i)
{
	zbx_uint64_t	max_processed = 0;
	int		j;

	for (j = 0; j < logfiles_num; j++)
	{
		if (i != j && SUCCEED == files_have_same_blocks_md5(logfiles + i, logfiles + j))
		{
			/* logfiles[i] and logfiles[j] are original and copy (or vice versa). */
			if (max_processed < logfiles[j].processed_size)
				max_processed = logfiles[j].processed_size;
		}
	}

	return max_processed;
}

/******************************************************************************
 *                                                                            *
 * Purpose: calculate delay based on number of processed and remaining bytes, *
 *          and processing time                                               *
 *                                                                            *
 * Parameters:                                                                *
 *     processed_bytes - [IN] number of processed bytes in logfile            *
 *     remaining_bytes - [IN] number of remaining bytes in all logfiles       *
 *     t_proc          - [IN] processing time, s                              *
 *                                                                            *
 * Return value:                                                              *
 *     delay in seconds or 0 (if cannot be calculated)                        *
 *                                                                            *
 ******************************************************************************/
static double	calculate_delay(zbx_uint64_t processed_bytes, zbx_uint64_t remaining_bytes, double t_proc)
{
	double	delay = 0.0;

	/* Processing time could be negative or 0 if the system clock has been set back in time. */
	/* In this case return 0, then a jump over log lines will not take place. */

	if (0 != processed_bytes && 0.0 < t_proc)
	{
		delay = (double)remaining_bytes * t_proc / (double)processed_bytes;

		zabbix_log(LOG_LEVEL_DEBUG, "calculate_delay(): processed bytes:" ZBX_FS_UI64
				" remaining bytes:" ZBX_FS_UI64 " t_proc:%e s speed:%e B/s remaining full checks:"
				ZBX_FS_UI64 " delay:%e s", processed_bytes, remaining_bytes, t_proc,
				(double)processed_bytes / t_proc, remaining_bytes / processed_bytes, delay);
	}

	return delay;
}

static void	jump_remaining_bytes_logrt(struct st_logfile *logfiles, int logfiles_num, const char *key,
		int start_from, zbx_uint64_t bytes_to_jump, int *seq, zbx_uint64_t *lastlogsize, int *mtime,
		int *jumped_to)
{
	int	first_pass = 1;
	int	i = start_from;		/* enter the loop with index of the last file processed, */
					/* later continue the loop from the start */

	while (i < logfiles_num)
	{
		if (logfiles[i].size != logfiles[i].processed_size)
		{
			zbx_uint64_t	bytes_jumped, new_processed_size;

			bytes_jumped = MIN(bytes_to_jump, logfiles[i].size - logfiles[i].processed_size);
			new_processed_size = logfiles[i].processed_size + bytes_jumped;

			zabbix_log(LOG_LEVEL_WARNING, "item:\"%s\" logfile:\"%s\" skipping " ZBX_FS_UI64 " bytes (from"
					" byte " ZBX_FS_UI64 " to byte " ZBX_FS_UI64 ") to meet maxdelay", key,
					logfiles[i].filename, bytes_jumped, logfiles[i].processed_size,
					new_processed_size);

			logfiles[i].processed_size = new_processed_size;
			*lastlogsize = new_processed_size;
			*mtime = logfiles[i].mtime;

			logfiles[i].seq = (*seq)++;

			bytes_to_jump -= bytes_jumped;

			*jumped_to = i;
		}

		if (0 == bytes_to_jump)
			break;

		if (0 != first_pass)
		{
			/* 'start_from' element was processed, now proceed from the beginning of file list */
			first_pass = 0;
			i = 0;
			continue;
		}

		i++;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose:                                                                   *
 *    After jumping over a number of bytes we "land" most likely somewhere in *
 *    the middle of log file line. This function tries to adjust position to  *
 *    the beginning of the log line.                                          *
 *                                                                            *
 * Parameters:                                                                *
 *     logfile     - [IN/OUT] log file data                                   *
 *     lastlogsize - [IN/OUT] offset from the beginning of the file           *
 *     min_size    - [IN] minimum offset to search from                       *
 *     encoding    - [IN] text string describing encoding                     *
 *     err_msg     - [IN/OUT] error message                                   *
 *                                                                            *
 * Return value: SUCCEED or FAIL (with error message allocated in 'err_msg')  *
 *                                                                            *
 ******************************************************************************/
static int	adjust_position_after_jump(struct st_logfile *logfile, zbx_uint64_t *lastlogsize, zbx_uint64_t min_size,
		const char *encoding, char **err_msg)
{
	int		fd, ret = FAIL;
	size_t		szbyte;
	ssize_t		nbytes;
	const char	*cr, *lf, *p_end;
	char		*p, *p_next;
	zbx_uint64_t	lastlogsize_tmp, lastlogsize_aligned, lastlogsize_org, seek_pos, remainder;
	char		buf[32 * ZBX_KIBIBYTE];		/* buffer must be of size multiple of 4 as some character */
							/* encodings use 4 bytes for every character */

	if (-1 == (fd = open_file_helper(logfile->filename, err_msg)))
		return FAIL;

	find_cr_lf_szbyte(encoding, &cr, &lf, &szbyte);

	/* For multibyte character encodings 'lastlogsize' needs to be aligned to character border. */
	/* Align it towards smaller offset. We assume that log file contains no corrupted data stream. */

	lastlogsize_org = *lastlogsize;
	lastlogsize_aligned = *lastlogsize;

	if (1 < szbyte && 0 != (remainder = lastlogsize_aligned % szbyte))	/* remainder can be 0, 1, 2 or 3 */
	{
		if (min_size <= lastlogsize_aligned - remainder)
			lastlogsize_aligned -= remainder;
		else
			lastlogsize_aligned = min_size;
	}

	if ((zbx_offset_t)-1 == zbx_lseek(fd, lastlogsize_aligned, SEEK_SET))
	{
		*err_msg = zbx_dsprintf(*err_msg, "Cannot set position to " ZBX_FS_UI64 " in file \"%s\": %s",
				lastlogsize_aligned, logfile->filename, zbx_strerror(errno));
		goto out;
	}

	/* search forward for the first newline until EOF */

	lastlogsize_tmp = lastlogsize_aligned;

	for (;;)
	{
		if (-1 == (nbytes = read(fd, buf, sizeof(buf))))
		{
			*err_msg = zbx_dsprintf(*err_msg, "Cannot read from file \"%s\": %s", logfile->filename,
					zbx_strerror(errno));
			goto out;
		}

		if (0 == nbytes)	/* end of file reached */
			break;

		p = buf;
		p_end = buf + nbytes;	/* no data from this position */

		if (NULL != buf_find_newline(p, &p_next, p_end, cr, lf, szbyte))
		{
			/* found the beginning of line */

			*lastlogsize = lastlogsize_tmp + (zbx_uint64_t)(p_next - buf);
			logfile->processed_size = *lastlogsize;
			ret = SUCCEED;
			goto out;
		}

		lastlogsize_tmp += (zbx_uint64_t)nbytes;
	}

	/* Searching forward did not find a newline. Now search backwards until 'min_size'. */

	seek_pos = lastlogsize_aligned;

	for (;;)
	{
		if (sizeof(buf) <= seek_pos)
			seek_pos -= MIN(sizeof(buf), seek_pos - min_size);
		else
			seek_pos = min_size;

		if ((zbx_offset_t)-1 == zbx_lseek(fd, seek_pos, SEEK_SET))
		{
			*err_msg = zbx_dsprintf(*err_msg, "Cannot set position to " ZBX_FS_UI64 " in file \"%s\": %s",
					lastlogsize_aligned, logfile->filename, zbx_strerror(errno));
			goto out;
		}

		if (-1 == (nbytes = read(fd, buf, sizeof(buf))))
		{
			*err_msg = zbx_dsprintf(*err_msg, "Cannot read from file \"%s\": %s", logfile->filename,
					zbx_strerror(errno));
			goto out;
		}

		if (0 == nbytes)	/* end of file reached */
		{
			*err_msg = zbx_dsprintf(*err_msg, "Unexpected end of file while reading file \"%s\"",
					logfile->filename);
			goto out;
		}

		p = buf;
		p_end = buf + nbytes;	/* no data from this position */

		if (NULL != buf_find_newline(p, &p_next, p_end, cr, lf, szbyte))
		{
			/* Found the beginning of line. It may not be the closest beginning of line to the place  */
			/* we jumped to (it could be about sizeof(buf) bytes away) but it is ok for our purposes. */

			*lastlogsize = seek_pos + (zbx_uint64_t)(p_next - buf);
			logfile->processed_size = *lastlogsize;
			ret = SUCCEED;
			goto out;
		}

		if (min_size == seek_pos)
		{
			/* We have searched backwards until 'min_size' and did not find a 'newline'. */
			/* Effectively it turned out to be a jump with zero-length. */

			*lastlogsize = min_size;
			logfile->processed_size = *lastlogsize;
			ret = SUCCEED;
			goto out;
		}
	}
out:
	if (SUCCEED != close_file_helper(fd, logfile->filename, err_msg))
		ret = FAIL;

	if (SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_DEBUG))
	{
		const char	*dbg_msg;

		if (SUCCEED == ret)
			dbg_msg = "NEWLINE FOUND";
		else
			dbg_msg = "NEWLINE NOT FOUND";

		zabbix_log(LOG_LEVEL_DEBUG, "adjust_position_after_jump(): szbyte:" ZBX_FS_SIZE_T " lastlogsize_org:"
				ZBX_FS_UI64 " lastlogsize_aligned:" ZBX_FS_UI64 " (change " ZBX_FS_I64 " bytes)"
				" lastlogsize_after:" ZBX_FS_UI64 " (change " ZBX_FS_I64 " bytes) %s %s",
				(zbx_fs_size_t)szbyte, lastlogsize_org, lastlogsize_aligned,
				(zbx_int64_t)lastlogsize_aligned - (zbx_int64_t)lastlogsize_org, *lastlogsize,
				(zbx_int64_t)*lastlogsize - (zbx_int64_t)lastlogsize_aligned,
				dbg_msg, ZBX_NULL2EMPTY_STR(*err_msg));
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: move forward to a new position in the log file list               *
 *                                                                            *
 * Parameters:                                                                *
 *     key           - [IN] item key for logging                              *
 *     logfiles      - [IN/OUT] list of log files                             *
 *     logfiles_num  - [IN] number of elements in 'logfiles'                  *
 *     jump_from_to  - [IN/OUT] on input - number of element where to start   *
 *                     jump, on output - number of element we jumped into     *
 *     seq           - [IN/OUT] sequence number of last processed file        *
 *     lastlogsize   - [IN/OUT] offset from the beginning of the file         *
 *     mtime         - [IN/OUT] last modification time of the file            *
 *     encoding      - [IN] text string describing encoding                   *
 *     bytes_to_jump - [IN] number of bytes to jump ahead                     *
 *     err_msg       - [IN/OUT] error message                                 *
 *                                                                            *
 * Return value: SUCCEED or FAIL (with error message allocated in 'err_msg')  *
 *                                                                            *
 * Comments: Thread-safe                                                      *
 *                                                                            *
 ******************************************************************************/
static int	jump_ahead(const char *key, struct st_logfile *logfiles, int logfiles_num,
		int *jump_from_to, int *seq, zbx_uint64_t *lastlogsize, int *mtime, const char *encoding,
		zbx_uint64_t bytes_to_jump, char **err_msg)
{
	zbx_uint64_t	lastlogsize_org, min_size;
	int		jumped_to = -1;		/* number of file in 'logfiles' list we jumped to */

	lastlogsize_org = *lastlogsize;

	jump_remaining_bytes_logrt(logfiles, logfiles_num, key, *jump_from_to, bytes_to_jump, seq, lastlogsize,
			mtime, &jumped_to);

	if (-1 == jumped_to)		/* no actual jump took place, no need to modify 'jump_from_to' */
		return SUCCEED;

	/* We have jumped into file, most likely somewhere in the middle of log line. Now find the beginning */
	/* of a line to avoid pattern-matching a line from a random position. */

	if (*jump_from_to == jumped_to)
	{
		/* jumped within the same file - do not search the beginning of a line before "pre-jump" position */
		min_size = lastlogsize_org;
	}
	else
	{
		*jump_from_to = jumped_to;

		/* jumped into different file - may search the beginning of a line from beginning of file */
		min_size = 0;
	}

	return adjust_position_after_jump(&logfiles[jumped_to], lastlogsize, min_size, encoding, err_msg);
}

static zbx_uint64_t	calculate_remaining_bytes(struct st_logfile *logfiles, int logfiles_num)
{
	zbx_uint64_t	remaining_bytes = 0;
	int		i;

	for (i = 0; i < logfiles_num; i++)
		remaining_bytes += logfiles[i].size - logfiles[i].processed_size;

	return remaining_bytes;
}

static void	transfer_for_rotate(const struct st_logfile *logfiles_old, int idx, struct st_logfile *logfiles,
		int logfiles_num, const char *old2new, int *seq)
{
	int	j;

	if (0 < logfiles_old[idx].processed_size && 0 == logfiles_old[idx].incomplete &&
			-1 != (j = find_old2new(old2new, logfiles_num, idx)))
	{
		if (logfiles_old[idx].size == logfiles_old[idx].processed_size &&
				logfiles_old[idx].size == logfiles[j].size)
		{
			/* the file was fully processed during the previous check and must be ignored during this */
			/* check */
			logfiles[j].processed_size = logfiles[j].size;
			logfiles[j].seq = (*seq)++;
		}
		else
		{
			/* the file was not fully processed during the previous check or has grown */
			if (logfiles[j].processed_size < logfiles_old[idx].processed_size)
				logfiles[j].processed_size = MIN(logfiles[j].size, logfiles_old[idx].processed_size);
		}
	}
	else if (1 == logfiles_old[idx].incomplete && -1 != (j = find_old2new(old2new, logfiles_num, idx)))
	{
		if (logfiles_old[idx].size < logfiles[j].size)
		{
			/* The file was not fully processed because of incomplete last record but it has grown. */
			/* Try to process it further. */
			logfiles[j].incomplete = 0;
		}
		else
			logfiles[j].incomplete = 1;

		if (logfiles[j].processed_size < logfiles_old[idx].processed_size)
			logfiles[j].processed_size = MIN(logfiles[j].size, logfiles_old[idx].processed_size);
	}
}

static void	transfer_for_copytruncate(const struct st_logfile *logfiles_old, int idx, struct st_logfile *logfiles,
		int logfiles_num, const char *old2new, int *seq)
{
	const char	*p = old2new + idx * logfiles_num;	/* start of idx-th row in 'old2new' array */
	int		j;

	if (0 < logfiles_old[idx].processed_size && 0 == logfiles_old[idx].incomplete)
	{
		for (j = 0; j < logfiles_num; j++, p++)		/* loop over columns (new files) on idx-th row */
		{
			if ('1' == *p || '2' == *p)
			{
				if (logfiles_old[idx].size == logfiles_old[idx].processed_size &&
						logfiles_old[idx].size == logfiles[j].size)
				{
					/* the file was fully processed during the previous check and must be ignored */
					/* during this check */
					logfiles[j].processed_size = logfiles[j].size;
					logfiles[j].seq = (*seq)++;
				}
				else
				{
					/* the file was not fully processed during the previous check or has grown */
					if (logfiles[j].processed_size < logfiles_old[idx].processed_size)
					{
						logfiles[j].processed_size = MIN(logfiles[j].size,
								logfiles_old[idx].processed_size);
					}
				}
			}
		}
	}
	else if (1 == logfiles_old[idx].incomplete)
	{
		for (j = 0; j < logfiles_num; j++, p++)		/* loop over columns (new files) on idx-th row */
		{
			if ('1' == *p || '2' == *p)
			{
				if (logfiles_old[idx].size < logfiles[j].size)
				{
					/* The file was not fully processed because of incomplete last record but it */
					/* has grown. Try to process it further. */
					logfiles[j].incomplete = 0;
				}
				else
					logfiles[j].incomplete = 1;

				if (logfiles[j].processed_size < logfiles_old[idx].processed_size)
				{
					logfiles[j].processed_size = MIN(logfiles[j].size,
							logfiles_old[idx].processed_size);
				}
			}
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Comments: Thread-safe                                                      *
 *                                                                            *
 ******************************************************************************/
static int	update_new_list_from_old(zbx_log_rotation_options_t rotation_type, struct st_logfile *logfiles_old,
		int logfiles_num_old, struct st_logfile *logfiles, int logfiles_num, int use_ino, int *seq,
		int *start_idx, zbx_uint64_t *lastlogsize, char **err_msg)
{
	char	*old2new;
	int	i, max_old_seq = 0, old_last;

	if (NULL == (old2new = create_old2new_and_copy_of(rotation_type, logfiles_old, logfiles_num_old,
			logfiles, logfiles_num, use_ino, err_msg)))
	{
		return FAIL;
	}

	/* transfer data about fully and partially processed files from the old file list to the new list */
	for (i = 0; i < logfiles_num_old; i++)
	{
		if (ZBX_LOG_ROTATION_LOGCPT == rotation_type)
			transfer_for_copytruncate(logfiles_old, i, logfiles, logfiles_num, old2new, seq);
		else
			transfer_for_rotate(logfiles_old, i, logfiles, logfiles_num, old2new, seq);

		/* find the last file processed (fully or partially) in the previous check */
		if (max_old_seq < logfiles_old[i].seq)
		{
			max_old_seq = logfiles_old[i].seq;
			old_last = i;
		}
	}

	/* find the first file to continue from in the new file list */
	if (0 < max_old_seq && -1 == (*start_idx = find_old2new(old2new, logfiles_num, old_last)))
	{
		/* Cannot find the successor of the last processed file from the previous check. */
		/* Adjust 'lastlogsize' for this case. */
		*start_idx = 0;
		*lastlogsize = logfiles[*start_idx].processed_size;
	}

	zbx_free(old2new);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: Find new records in logfiles                                      *
 *                                                                            *
 * Parameters:                                                                *
 *     flags            - [IN] bit flags with item type: log, logrt,          *
 *                        log.count or logrt.count                            *
 *     filename         - [IN] logfile name (regular expression with a path)  *
 *     lastlogsize      - [IN/OUT] offset from the beginning of the file      *
 *     mtime            - [IN/OUT] last modification time of the file         *
 *     lastlogsize_sent - [OUT] lastlogsize value that was last sent          *
 *     mtime_sent       - [OUT] mtime value that was last sent                *
 *     skip_old_data    - [IN/OUT] start from the beginning of the file or    *
 *                        jump to the end                                     *
 *     big_rec          - [IN/OUT] state variable to remember whether a long  *
 *                        record is being processed                           *
 *     use_ino          - [IN/OUT] how to use inode numbers                   *
 *     err_msg          - [IN/OUT] error message why an item became           *
 *                        NOTSUPPORTED                                        *
 *     logfiles_old     - [IN/OUT] array of logfiles from the last check      *
 *     logfiles_num_old - [IN] number of elements in "logfiles_old"           *
 *     logfiles_new     - [OUT] new array of logfiles                         *
 *     logfiles_num_new - [OUT] number of elements in "logfiles_new"          *
 *     encoding         - [IN] text string describing encoding.               *
 *                        See function find_cr_lf_szbyte() for supported      *
 *                        encodings.                                          *
 *                        "" (empty string) means a single-byte character set *
 *                        (e.g. ASCII).                                       *
 *     regexps          - [IN] array of regexps                               *
 *     pattern          - [IN] pattern to match                               *
 *     output_template  - [IN] output formatting template                     *
 *     p_count          - [IN/OUT] limit of records to be processed           *
 *     s_count          - [IN/OUT] limit of records to be sent to server      *
 *     process_value    - [IN] pointer to function process_value()            *
 *     addrs            - [IN] vector for passing server and port where to    *
 *                             to send data                                   *
 *     agent2_result    - [IN] address of buffer where to store matching log  *
 *                             records (used only in Agent2)                  *
 *     hostname         - [IN] hostname the data comes from                   *
 *     key              - [IN] item key the data belongs to                   *
 *     jumped           - [OUT] flag to indicate that a jump took place       *
 *     max_delay        - [IN] maximum allowed delay, s                       *
 *     start_time       - [IN/OUT] start time of check                        *
 *     processed_bytes  - [IN/OUT] number of bytes processed                  *
 *     rotation_type    - [IN] simple rotation or copy/truncate rotation      *
 *     persistent_file_name - [IN] name of file for saving persistent data    *
 *     prep_vec         - [IN/OUT] vector with data for writing into          *
 *                                 persistent files                           *
 *                                                                            *
 * Return value: returns SUCCEED on successful reading,                       *
 *               FAIL on other cases                                          *
 *                                                                            *
 * Comments: Supposed to be thread-safe, see pick_logfiles() comments.        *
 *                                                                            *
 ******************************************************************************/
static int	process_logrt(unsigned char flags, const char *filename, zbx_uint64_t *lastlogsize, int *mtime,
		zbx_uint64_t *lastlogsize_sent, int *mtime_sent, unsigned char *skip_old_data, int *big_rec,
		int *use_ino, char **err_msg, struct st_logfile **logfiles_old, int logfiles_num_old,
		struct st_logfile **logfiles_new, int *logfiles_num_new, const char *encoding,
		zbx_vector_ptr_t *regexps, const char *pattern, const char *output_template, int *p_count, int *s_count,
		zbx_process_value_func_t process_value, zbx_vector_ptr_t *addrs, zbx_vector_ptr_t *agent2_result,
		const char *hostname, const char *key, int *jumped, float max_delay, double *start_time,
		zbx_uint64_t *processed_bytes, zbx_log_rotation_options_t rotation_type,
		const char *persistent_file_name, zbx_vector_pre_persistent_t *prep_vec)
{
	int			i, start_idx, ret = FAIL, logfiles_num = 0, logfiles_alloc = 0, seq = 1,
				from_first_file = 1, last_processed, limit_reached = 0, res;
	struct st_logfile	*logfiles = NULL;
	zbx_uint64_t		processed_bytes_sum = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() flags:0x%02x filename:'%s' lastlogsize:" ZBX_FS_UI64 " mtime:%d",
			__func__, (unsigned int)flags, filename, *lastlogsize, *mtime);

	adjust_mtime_to_clock(mtime);

	if (SUCCEED != (res = make_logfile_list(flags, filename, *mtime, &logfiles, &logfiles_alloc, &logfiles_num,
			use_ino, err_msg)))
	{
		if (ZBX_NO_FILE_ERROR == res)
		{
			if (1 == *skip_old_data)
			{
				*skip_old_data = 0;

				zabbix_log(LOG_LEVEL_DEBUG, "%s(): no files, setting skip_old_data to 0", __func__);
			}

			if (0 != (ZBX_METRIC_FLAG_LOG_LOGRT & flags) && 0 == logfiles_num_old)
			{
				/* Both the old and the new log file lists are empty. That means the agent has not */
				/* seen any log files for this logrt[] item since started. If log files appear later */
				/* then analyze them from start, do not apply the 'lastlogsize' received from server */
				/* anymore. */

				*lastlogsize = 0;
			}
		}

		/* file was not accessible for a log[] or log.count[] item or an error occurred */
		if (0 != (ZBX_METRIC_FLAG_LOG_LOG & flags) || (0 != (ZBX_METRIC_FLAG_LOG_LOGRT & flags) && FAIL == res))
			goto out;
	}

	if (0 == logfiles_num)
	{
		/* there were no files for a logrt[] or logrt.count[] item to analyze */
		ret = SUCCEED;
		goto out;
	}

	if (1 == *skip_old_data)
	{
		start_idx = logfiles_num - 1;

		/* mark files to be skipped as processed (except the last one) */
		for (i = 0; i < start_idx; i++)
		{
			logfiles[i].processed_size = logfiles[i].size;
			logfiles[i].seq = seq++;
		}
	}
	else
		start_idx = 0;

	if (0 < logfiles_num_old && 0 < logfiles_num && SUCCEED != update_new_list_from_old(rotation_type,
			*logfiles_old, logfiles_num_old, logfiles, logfiles_num, *use_ino, &seq, &start_idx,
			lastlogsize, err_msg))
	{
		destroy_logfile_list(&logfiles, &logfiles_alloc, &logfiles_num);
		goto out;
	}

	if (ZBX_LOG_ROTATION_LOGCPT == rotation_type && 1 < logfiles_num)
		ensure_order_if_mtimes_equal(*logfiles_old, logfiles, logfiles_num, *use_ino, &start_idx);

	if (SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_DEBUG))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() old file list:", __func__);
		if (NULL != *logfiles_old)
			print_logfile_list(*logfiles_old, logfiles_num_old);
		else
			zabbix_log(LOG_LEVEL_DEBUG, "   file list empty");

		zabbix_log(LOG_LEVEL_DEBUG, "%s() new file list: (mtime:%d lastlogsize:" ZBX_FS_UI64 " start_idx:%d)",
				__func__, *mtime, *lastlogsize, start_idx);
		if (NULL != logfiles)
			print_logfile_list(logfiles, logfiles_num);
		else
			zabbix_log(LOG_LEVEL_DEBUG, "   file list empty");
	}

	/* number of file last processed - start from this */
	last_processed = start_idx;

	/* from now assume success - it could be that there is nothing to do */
	ret = SUCCEED;

	if (0.0f != max_delay)
	{
		if (0.0 != *start_time)
		{
			zbx_uint64_t	remaining_bytes;

			if (0 != (remaining_bytes = calculate_remaining_bytes(logfiles, logfiles_num)))
			{
				/* calculate delay and jump if necessary */

				double	delay;

				if ((double)max_delay < (delay = calculate_delay(*processed_bytes, remaining_bytes,
						zbx_time() - *start_time)))
				{
					zbx_uint64_t	bytes_to_jump;

					bytes_to_jump = (zbx_uint64_t)((double)remaining_bytes *
							(delay - (double)max_delay) / delay);

					if (SUCCEED == (ret = jump_ahead(key, logfiles, logfiles_num,
							&last_processed, &seq, lastlogsize, mtime, encoding,
							bytes_to_jump, err_msg)))
					{
						*jumped = 1;
					}
				}
			}
		}

		*start_time = zbx_time();	/* mark new start time for using in the next check */
	}

	/* enter the loop with index of the first file to be processed, later continue the loop from the start */
	i = last_processed;

	while (NULL != logfiles && i < logfiles_num)
	{
		if (0 == logfiles[i].incomplete &&
				(logfiles[i].size != logfiles[i].processed_size || 0 == logfiles[i].seq))
		{
			zbx_uint64_t	processed_bytes_tmp = 0, seek_offset;
			int		process_this_file = 1;

			*mtime = logfiles[i].mtime;

			if (start_idx != i)
			{
				*lastlogsize = logfiles[i].processed_size;
			}
			else
			{
				/* When agent starts it can receive from server an out-of-date lastlogsize value, */
				/* larger than current log file size. */

				if (*lastlogsize > logfiles[i].size)
				{
					int	j, found = 0;

					/* check if there are other log files with the same mtime and size */
					/* greater or equal to lastlogsize */
					for (j = 0; j < logfiles_num; j++)
					{
						if (i == j || logfiles[i].mtime != logfiles[j].mtime)
							continue;

						if (*lastlogsize <= logfiles[j].size)
						{
							found = 1;
							break;
						}
					}

					if (0 == found)
						*lastlogsize = logfiles[i].processed_size;
				}
			}

			if (0 == *skip_old_data)
			{
				seek_offset = *lastlogsize;
			}
			else
			{
				seek_offset = logfiles[i].size;

				zabbix_log(LOG_LEVEL_DEBUG, "skipping old data in filename:'%s' to seek_offset:"
						ZBX_FS_UI64, logfiles[i].filename, seek_offset);
			}

			if (ZBX_LOG_ROTATION_LOGCPT == rotation_type)
			{
				zbx_uint64_t	max_processed;

				if (seek_offset < (max_processed = max_processed_size_in_copies(logfiles, logfiles_num,
						i)))
				{
					logfiles[i].processed_size = MIN(logfiles[i].size, max_processed);

					if (logfiles[i].size == logfiles[i].processed_size)
						process_this_file = 0;

					*lastlogsize = max_processed;
				}
			}

			if (0 != process_this_file)
			{
				ret = process_log(flags, logfiles + i, lastlogsize, mtime, lastlogsize_sent,
						mtime_sent, skip_old_data, big_rec, encoding, regexps, pattern,
						output_template, p_count, s_count, process_value, addrs, agent2_result,
						hostname, key, &processed_bytes_tmp, seek_offset, persistent_file_name,
						prep_vec, err_msg);

				/* process_log() advances 'lastlogsize' only on success therefore */
				/* we do not check for errors here */
				logfiles[i].processed_size = *lastlogsize;

				/* log file could grow during processing, update size in our list */
				if (*lastlogsize > logfiles[i].size)
					logfiles[i].size = *lastlogsize;
			}

			/* Mark file as processed (at least partially). In case if process_log() failed we will stop */
			/* the current checking. In the next check the file will be marked in the list of old files */
			/* and we will know where we left off. */
			logfiles[i].seq = seq++;

			if (ZBX_LOG_ROTATION_LOGCPT == rotation_type && 1 < logfiles_num)
			{
				int	k;

				for (k = 0; k < logfiles_num - 1; k++)
					handle_multiple_copies(logfiles, logfiles_num, k);
			}

			if (SUCCEED != ret)
				break;

			if (0.0f != max_delay)
				processed_bytes_sum += processed_bytes_tmp;

			if (0 >= *p_count || 0 >= *s_count)
			{
				limit_reached = 1;
				break;
			}
		}

		if (0 != from_first_file)
		{
			/* We have processed the file where we left off in the previous check. */
			from_first_file = 0;

			/* Now proceed from the beginning of the new file list to process the remaining files. */
			i = 0;
			continue;
		}

		i++;
	}

	if (ZBX_LOG_ROTATION_LOGCPT == rotation_type && 1 < logfiles_num)
	{
		/* If logrt[] or logrt.count[] item is checked often but rotation by copying is slow it could happen */
		/* that the original file is completely processed but the copy with a newer timestamp is still in */
		/* progress. The original file goes out of the list of files and the copy is analyzed as new file, */
		/* so the matching lines are reported twice. To prevent this we manipulate our stored 'mtime' */
		/* and 'lastlogsize' to keep information about copies in the list as long as necessary to prevent */
		/* reporting twice. */

		delay_update_if_copies(logfiles, logfiles_num, mtime, lastlogsize);
	}

	/* store the new log file list for using in the next check */
	*logfiles_num_new = logfiles_num;

	if (0 < logfiles_num)
	{
		/* Try to update MD5 sums of initial blocks if they were calculated for small blocks. */
		/* Log file processing has been done. Errors here only prevent updating MD5 sums for */
		/* a single file but do not affect function return value. */
		char	*err_tmp = NULL;
		int	k;

		for (k = 0; k < logfiles_num; k++)
		{
			if (MAX_LEN_MD5 > logfiles[k].md5_block_size &&
					logfiles[k].size > (zbx_uint64_t)logfiles[k].md5_block_size)
			{
				int		f, new_md5_block_size = (int)MIN(MAX_LEN_MD5, logfiles[k].size);
				size_t		new_last_block_offset;
				md5_byte_t	new_first_block_md5[ZBX_MD5_DIGEST_SIZE],
						new_last_block_md5[ZBX_MD5_DIGEST_SIZE];

				if (-1 == (f = zbx_open(logfiles[k].filename, O_RDONLY)))
					continue;

				if (SUCCEED != file_part_md5(f, 0, new_md5_block_size, new_first_block_md5,
						logfiles[k].filename, &err_tmp))
				{
					zbx_free(err_tmp);
					goto clean;
				}

				if (0 < (new_last_block_offset = logfiles[k].size - (size_t)new_md5_block_size))
				{
					if (SUCCEED != file_part_md5(f, new_last_block_offset,
							new_md5_block_size, new_last_block_md5, logfiles[k].filename,
							&err_tmp))
					{
						zbx_free(err_tmp);
						goto clean;
					}
				}
				else	/* file is small, set the last block MD5 equal to the first block's one */
					memcpy(new_last_block_md5, new_first_block_md5, sizeof(new_last_block_md5));

				logfiles[k].md5_block_size = new_md5_block_size;
				logfiles[k].last_block_offset = new_last_block_offset;

				memcpy(logfiles[k].first_block_md5, new_first_block_md5,
						sizeof(logfiles[k].first_block_md5));
				memcpy(logfiles[k].last_block_md5, new_last_block_md5,
						sizeof(logfiles[k].last_block_md5));
clean:
				if (0 != close(f))
					continue;
			}
		}

		*logfiles_new = logfiles;
	}
out:
	if (0.0f != max_delay)
	{
		if (SUCCEED == ret)
			*processed_bytes = processed_bytes_sum;

		if (SUCCEED != ret || 0 == limit_reached)
		{
			/* FAIL or number of lines limits were not reached. */
			/* Invalidate start_time to prevent jump in the next check. */
			*start_time = 0.0;
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

static int	check_number_of_parameters(unsigned char flags, const AGENT_REQUEST *request, char **error)
{
	int	parameter_num, max_parameter_num;

	if (0 == (parameter_num = get_rparams_num(request)))
	{
		*error = zbx_strdup(*error, "Invalid number of parameters.");
		return FAIL;
	}

	if (0 != (ZBX_METRIC_FLAG_LOG_COUNT & flags))
		max_parameter_num = 8;	/* log.count or logrt.count */
	else
		max_parameter_num = 9;	/* log or logrt */

	if (max_parameter_num < parameter_num)
	{
		*error = zbx_strdup(*error, "Too many parameters.");
		return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Comments: thread-safe if CONFIG_MAX_LINES_PER_SECOND is updated when log   *
 *           checks are not running                                           *
 *                                                                            *
 ******************************************************************************/
static int	init_max_lines_per_sec(int is_count_item, const AGENT_REQUEST *request, int *max_lines_per_sec,
		char **error)
{
	const char	*p;
	int		rate;

	if (NULL == (p = get_rparam(request, 3)) || '\0' == *p)
	{
		if (0 == is_count_item)				/* log[], logrt[] */
			*max_lines_per_sec = CONFIG_MAX_LINES_PER_SECOND;
		else						/* log.count[], logrt.count[] */
			*max_lines_per_sec = MAX_VALUE_LINES_MULTIPLIER * CONFIG_MAX_LINES_PER_SECOND;

		return SUCCEED;
	}

	if (MIN_VALUE_LINES > (rate = atoi(p)) ||
			(0 == is_count_item && MAX_VALUE_LINES < rate) ||
			(0 != is_count_item && MAX_VALUE_LINES_MULTIPLIER * MAX_VALUE_LINES < rate))
	{
		*error = zbx_strdup(*error, "Invalid fourth parameter.");
		return FAIL;
	}

	*max_lines_per_sec = rate;
	return SUCCEED;
}

static int	init_max_delay(int is_count_item, const AGENT_REQUEST *request, float *max_delay, char **error)
{
	const char	*max_delay_str;
	double		max_delay_tmp;
	int		max_delay_par_nr;

	/* <maxdelay> is parameter 6 for log[], logrt[], but parameter 5 for log.count[], logrt.count[] */

	if (0 == is_count_item)
		max_delay_par_nr = 6;
	else
		max_delay_par_nr = 5;

	if (NULL == (max_delay_str = get_rparam(request, max_delay_par_nr)) || '\0' == *max_delay_str)
	{
		*max_delay = 0.0f;
		return SUCCEED;
	}

	if (SUCCEED != is_double(max_delay_str, &max_delay_tmp) || 0.0 > max_delay_tmp)
	{
		*error = zbx_dsprintf(*error, "Invalid %s parameter.", (5 == max_delay_par_nr) ? "sixth" : "seventh");
		return FAIL;
	}

	*max_delay = (float)max_delay_tmp;
	return SUCCEED;
}

static int	init_rotation_type(unsigned char flags, const AGENT_REQUEST *request,
		zbx_log_rotation_options_t *rotation_type, char **error)
{
	char	*options;
	int	options_par_nr;

	if (0 == (ZBX_METRIC_FLAG_LOG_COUNT & flags))	/* log, logrt */
		options_par_nr = 7;
	else						/* log.count, logrt.count */
		options_par_nr = 6;

	options = get_rparam(request, options_par_nr);

	if (NULL == options || '\0' == *options)	/* default options */
	{
		if (0 != (ZBX_METRIC_FLAG_LOG_LOGRT & flags))
			*rotation_type = ZBX_LOG_ROTATION_LOGRT;
		else
			*rotation_type = ZBX_LOG_ROTATION_REREAD;
	}
	else
	{
		if (0 != (ZBX_METRIC_FLAG_LOG_LOGRT & flags))	/* logrt, logrt.count */
		{
			if (0 == strcmp(options, "copytruncate"))
				*rotation_type = ZBX_LOG_ROTATION_LOGCPT;
			else if (0 == strcmp(options, "rotate") || 0 == strcmp(options, "mtime-reread"))
				*rotation_type = ZBX_LOG_ROTATION_LOGRT;
			else if (0 == strcmp(options, "mtime-noreread"))
				*rotation_type = ZBX_LOG_ROTATION_NO_REREAD;
			else
				goto err;
		}
		else	/* log, log.count */
		{
			if (0 == strcmp(options, "mtime-reread"))
				*rotation_type = ZBX_LOG_ROTATION_REREAD;
			else if (0 == strcmp(options, "mtime-noreread"))
				*rotation_type = ZBX_LOG_ROTATION_NO_REREAD;
			else
				goto err;
		}
	}

	return SUCCEED;
err:
	*error = zbx_strdup(*error, "Invalid parameter \"options\".");

	return FAIL;
}

static int	init_persistent_dir_parameter(const char *server, unsigned short port, const char *item_key,
		int is_count_item, const AGENT_REQUEST *request, char **persistent_file_name, char **error)
{
	/* <persistent_dir> is parameter 8 for log[], logrt[], but parameter 7 for log.count[], logrt.count[] */
	/* (here counting starts from 0) */

	const int	persistent_dir_param_nr = (0 == is_count_item) ? 8 : 7;
	char		*persistent_dir;
#if !defined(_WINDOWS) && !defined(__MINGW32__)
	char		*persistent_serv_dir;
#endif

	if (NULL == (persistent_dir = get_rparam(request, persistent_dir_param_nr)) || '\0' == *persistent_dir)
		return SUCCEED;

#if defined(_WINDOWS) || defined(__MINGW32__)
	ZBX_UNUSED(server);
	ZBX_UNUSED(port);
	ZBX_UNUSED(item_key);
	ZBX_UNUSED(persistent_file_name);

	*error = zbx_dsprintf(*error, "The %s parameter (persistent directory) is not supported on Microsoft Windows.",
			(8 == persistent_dir_param_nr) ? "ninth" : "eighth");
	return FAIL;
#else
	if (NULL != *persistent_file_name)	/* name is set, so all preparation has been done earlier */
		return SUCCEED;

	/* set up directory for persistent file */

	if (SUCCEED != is_ascii_string(persistent_dir))		/* reject non-ASCII directory name */
	{
		*error = zbx_dsprintf(*error, "Invalid %s parameter. It contains non-ASCII characters.",
				(8 == persistent_dir_param_nr) ? "ninth" : "eighth");
		return FAIL;
	}

	if (NULL == (persistent_serv_dir = zbx_create_persistent_server_directory(persistent_dir, server, port, error)))
		return FAIL;

	*persistent_file_name = zbx_make_persistent_file_name(persistent_serv_dir, item_key);

	zbx_free(persistent_serv_dir);

	zabbix_log(LOG_LEVEL_DEBUG, "%s(): set persistent_file_name:[%s]", __func__, *persistent_file_name);

	return SUCCEED;
#endif
}

/******************************************************************************
 *                                                                            *
 * Comments: Function body is thread-safe if CONFIG_HOSTNAME is not updated   *
 *           while log checks are running. Uses callback function             *
 *           process_value_cb, so overall thread-safety depends on caller.    *
 *           Otherwise supposed to be thread-safe, see pick_logfiles()        *
 *           comments.                                                        *
 *                                                                            *
 ******************************************************************************/
int	process_log_check(zbx_vector_ptr_t *addrs, zbx_vector_ptr_t *agent2_result, zbx_vector_ptr_t *regexps,
		ZBX_ACTIVE_METRIC *metric, zbx_process_value_func_t process_value_cb, zbx_uint64_t *lastlogsize_sent,
		int *mtime_sent, char **error, zbx_vector_pre_persistent_t *prep_vec)
{
	AGENT_REQUEST			request;
	const char			*filename, *regexp, *encoding, *skip, *output_template;
	char				*encoding_uc = NULL;
	int				max_lines_per_sec, ret = FAIL, s_count, p_count, s_count_orig, is_count_item,
					mtime_orig, big_rec_orig, logfiles_num_new = 0, jumped = 0;
	zbx_log_rotation_options_t	rotation_type;
	zbx_uint64_t			lastlogsize_orig;
	float				max_delay;
	struct st_logfile		*logfiles_new = NULL;

	if (0 != (ZBX_METRIC_FLAG_LOG_COUNT & metric->flags))
		is_count_item = 1;
	else
		is_count_item = 0;

	init_request(&request);

	/* Expected parameters by item: */
	/* log        [file,       <regexp>,<encoding>,<maxlines>,    <mode>,<output>,<maxdelay>, <options>,<persistent_dir>] 9 params */
	/* log.count  [file,       <regexp>,<encoding>,<maxproclines>,<mode>,         <maxdelay>, <options>,<persistent_dir>] 8 params */
	/* logrt      [file_regexp,<regexp>,<encoding>,<maxlines>,    <mode>,<output>,<maxdelay>, <options>,<persistent_dir>] 9 params */
	/* logrt.count[file_regexp,<regexp>,<encoding>,<maxproclines>,<mode>,         <maxdelay>, <options>,<persistent_dir>] 8 params */

	if (SUCCEED != parse_item_key(metric->key, &request))
	{
		*error = zbx_strdup(*error, "Invalid item key format.");
		goto out;
	}

	if (SUCCEED != check_number_of_parameters(metric->flags, &request, error))
		goto out;

	/* parameter 'file' or 'file_regexp' */

	if (NULL == (filename = get_rparam(&request, 0)) || '\0' == *filename)
	{
		*error = zbx_strdup(*error, "Invalid first parameter.");
		goto out;
	}

	/* parameter 'regexp' */

	if (NULL == (regexp = get_rparam(&request, 1)))
	{
		regexp = "";
	}
	else if ('@' == *regexp && SUCCEED != zbx_global_regexp_exists(regexp + 1, regexps))
	{
		*error = zbx_dsprintf(*error, "Global regular expression \"%s\" does not exist.", regexp + 1);
		goto out;
	}

	/* parameter 'encoding' */

	if (NULL == (encoding = get_rparam(&request, 2)))
	{
		encoding = "";
	}
	else
	{
		encoding_uc = zbx_strdup(encoding_uc, encoding);
		zbx_strupper(encoding_uc);
		encoding = encoding_uc;
	}

	/* parameter 'maxlines' or 'maxproclines' */
	if (SUCCEED !=  init_max_lines_per_sec(is_count_item, &request, &max_lines_per_sec, error))
		goto out;

	/* parameter 'mode' */

	if (NULL == (skip = get_rparam(&request, 4)) || '\0' == *skip || 0 == strcmp(skip, "all"))
	{
		metric->skip_old_data = 0;
	}
	else if (0 != strcmp(skip, "skip"))
	{
		*error = zbx_strdup(*error, "Invalid fifth parameter.");
		goto out;
	}

	/* parameter 'output' (not used for log.count[], logrt.count[]) */
	if (0 != is_count_item || (NULL == (output_template = get_rparam(&request, 5))))
		output_template = "";

	/* parameter 'maxdelay' */
	if (SUCCEED != init_max_delay(is_count_item, &request, &max_delay, error))
		goto out;

	/* parameter 'options' */
	if (SUCCEED != init_rotation_type(metric->flags, &request, &rotation_type, error))
		goto out;

	/* parameter 'persistent_dir' */
	if (NULL != addrs)	/* When process_log_check() is called in C agent it receives 'addrs' argument with  */
				/* server hostname or IP address and port number where to send matching records.    */
				/* When process_log_check() is called in Agent2 (Golang) the 'addrs' argument is    */
				/* NULL and the 'agent2_result' argument is used to pass an address of buffer where */
				/* to store the matching records in memory. Currently Agent2 does not support       */
				/* persistent files therefore skip init_persistent_dir_parameter().                 */
	{
		if (SUCCEED != init_persistent_dir_parameter(((zbx_addr_t *)addrs->values[0])->ip,
				((zbx_addr_t *)addrs->values[0])->port, metric->key, is_count_item, &request,
				&metric->persistent_file_name, error))
		{
			goto out;
		}
	}

	/* jumping over fast growing log files is not supported with 'copytruncate' */
	if (ZBX_LOG_ROTATION_LOGCPT == rotation_type && 0.0f != max_delay)
	{
		*error = zbx_strdup(*error, "maxdelay > 0 is not supported with copytruncate option.");
		goto out;
	}

	/* do not flood Zabbix server if file grows too fast */
	s_count = max_lines_per_sec * metric->refresh;

	/* do not flood local system if file grows too fast */
	if (0 == is_count_item)
	{
		p_count = MAX_VALUE_LINES_MULTIPLIER * s_count;	/* log[], logrt[] */
	}
	else
	{
		/* In log.count[] and logrt.count[] items the variable 's_count' (max number of lines allowed to be */
		/* sent to server) is used for counting matching lines in logfile(s). 's_count' is counted from max */
		/* value down towards 0. */

		p_count = s_count_orig = s_count;

		/* remember current state, we may need to restore it if log.count[] or logrt.count[] result cannot */
		/* be sent to server */

		lastlogsize_orig = metric->lastlogsize;
		mtime_orig = metric->mtime;
		big_rec_orig = metric->big_rec;

		/* process_logrt() may modify old log file list 'metric->logfiles' but currently modifications are */
		/* limited to 'retry' flag in existing list elements. We do not preserve original 'retry' flag values */
		/* as there is no need to "rollback" their modifications if log.count[] or logrt.count[] result can */
		/* not be sent to server. */
	}

#if !defined(_WINDOWS) && !defined(__MINGW32__)
	/* recover state from persistent file only if agent has no already established state */
	if (0 != (ZBX_METRIC_FLAG_NEW & metric->flags) && NULL != metric->persistent_file_name &&
			0 == metric->logfiles_num)
	{
		/* try to restore state from persistent file */
		char	*err_msg = NULL;
		char	buf[MAX_STRING_LEN];

		if (SUCCEED == zbx_read_persistent_file(metric->persistent_file_name, buf, sizeof(buf), &err_msg))
		{
			zbx_uint64_t	processed_size_tmp = 0;
			int		mtime_tmp = 0;

			zabbix_log(LOG_LEVEL_DEBUG, "%s(): item \"%s\": persistent file \"%s\" found, data:[%s]",
					__func__, metric->key, metric->persistent_file_name, buf);

			if (SUCCEED == zbx_restore_file_details(buf, &metric->logfiles, &metric->logfiles_num,
					&processed_size_tmp, &mtime_tmp, &err_msg))
			{
				/* If 'lastlogsize' value from server is not equal to 'processed_size' from   */
				/* persistent file then give priority to persistent file to prevent unwanted  */
				/* re-reading of log file records. */
				/* For 'mtime' give priority to persistent file only if server sent a smaller */
				/* 'mtime' value' (unusual case, it should not happen). The value of 'mtime'  */
				/* from server larger than 'mtime' from persistent file most likely means     */
				/* that some other agent instance has analyzed log files up to server-supplied */
				/* 'mtime' and the current instance needs not to start analysis from 'mtime'  */
				/* restored from persistent file. */

				if (metric->lastlogsize != processed_size_tmp || metric->mtime < mtime_tmp)
				{
					char	*msg = NULL;
					size_t	msg_alloc = 0, msg_offset = 0;

					zbx_snprintf_alloc(&msg, &msg_alloc, &msg_offset, "%s(): item \"%s\":"
							" overriding", __func__, metric->key);

					if (metric->lastlogsize != processed_size_tmp)
					{
						zbx_snprintf_alloc(&msg, &msg_alloc, &msg_offset,
								" lastlogsize: " ZBX_FS_UI64 " -> " ZBX_FS_UI64,
								metric->lastlogsize, processed_size_tmp);

						metric->lastlogsize = processed_size_tmp;
					}

					if (metric->mtime < mtime_tmp)
					{
						zbx_snprintf_alloc(&msg, &msg_alloc, &msg_offset,
								" mtime: %d -> %d",
								metric->mtime, mtime_tmp);

						metric->mtime = mtime_tmp;
					}

					zabbix_log(LOG_LEVEL_WARNING, "%s from persistent file", msg);

					zbx_free(msg);
				}
			}
			else
			{
				zabbix_log(LOG_LEVEL_WARNING, "%s(): item \"%s\": persistent file \"%s\" restore error:"
						" %s", __func__, metric->key, metric->persistent_file_name, err_msg);
				zbx_free(err_msg);
			}
		}
		else
		{
			/* persistent file errors are not fatal */
			zabbix_log(LOG_LEVEL_DEBUG, "%s(): item \"%s\": persistent file [%s] does not exist or error:"
					" %s", __func__, metric->key, metric->persistent_file_name, err_msg);
			zbx_free(err_msg);
		}
	}
#endif
	ret = process_logrt(metric->flags, filename, &metric->lastlogsize, &metric->mtime, lastlogsize_sent, mtime_sent,
			&metric->skip_old_data, &metric->big_rec, &metric->use_ino, error, &metric->logfiles,
			metric->logfiles_num, &logfiles_new, &logfiles_num_new, encoding, regexps, regexp,
			output_template, &p_count, &s_count, process_value_cb, addrs, agent2_result, CONFIG_HOSTNAME,
			metric->key_orig, &jumped, max_delay, &metric->start_time, &metric->processed_bytes,
			rotation_type, metric->persistent_file_name, prep_vec);

	if (0 == is_count_item && NULL != logfiles_new)
	{
		/* for log[] and logrt[] items - switch to the new log file list */

		destroy_logfile_list(&metric->logfiles, NULL, &metric->logfiles_num);
		metric->logfiles = logfiles_new;
		metric->logfiles_num = logfiles_num_new;
	}

	if (SUCCEED == ret)
	{
		metric->error_count = 0;

		if (0 != is_count_item)
		{
			/* send log.count[] or logrt.count[] item value to server */

			int	match_count;			/* number of matching lines */
			char	buf[ZBX_MAX_UINT64_LEN];

			match_count = s_count_orig - s_count;

			zbx_snprintf(buf, sizeof(buf), "%d", match_count);

			if (SUCCEED == process_value_cb(addrs, agent2_result, CONFIG_HOSTNAME, metric->key_orig, buf,
					ITEM_STATE_NORMAL, &metric->lastlogsize, &metric->mtime, NULL, NULL, NULL, NULL,
					metric->flags | ZBX_METRIC_FLAG_PERSISTENT) || 0 != jumped)
			{
				/* if process_value() fails (i.e. log(rt).count result cannot be sent to server) but */
				/* a jump took place to meet <maxdelay> then we discard the result and keep the state */
				/* after jump */

				*lastlogsize_sent = metric->lastlogsize;
				*mtime_sent = metric->mtime;

				/* switch to the new log file list */
				destroy_logfile_list(&metric->logfiles, NULL, &metric->logfiles_num);
				metric->logfiles = logfiles_new;
				metric->logfiles_num = logfiles_num_new;
			}
			else
			{
				/* unable to send data and no jump took place, restore original state to try again */
				/* during the next check */

				metric->lastlogsize = lastlogsize_orig;
				metric->mtime =  mtime_orig;
				metric->big_rec = big_rec_orig;

				/* the old log file list 'metric->logfiles' stays in its place, drop the new list */
				destroy_logfile_list(&logfiles_new, NULL, &logfiles_num_new);
			}
		}
	}
	else
	{
		metric->error_count++;

		if (0 != is_count_item)
		{
			/* restore original state to try again during the next check */

			metric->lastlogsize = lastlogsize_orig;
			metric->mtime =  mtime_orig;
			metric->big_rec = big_rec_orig;

			/* the old log file list 'metric->logfiles' stays in its place, drop the new list */
			destroy_logfile_list(&logfiles_new, NULL, &logfiles_num_new);
		}

		/* suppress first two errors */
		if (3 > metric->error_count)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "suppressing log(rt)(.count) processing error #%d: %s",
					metric->error_count, NULL != *error ? *error : "unknown error");

			zbx_free(*error);
			ret = SUCCEED;
		}
	}
out:
	zbx_free(encoding_uc);
	free_request(&request);

	return ret;
}

struct st_logfile	*find_last_processed_file_in_logfiles_list(struct st_logfile *logfiles, int logfiles_num)
{
	int	i, max_seq = -1, last_file_idx = 0;

	if (1 == logfiles_num)
		return logfiles;

	/* The last (at least partially) processed file is the one with the maximum 'seq' value. */
	/* If no any file is processed then return pointer to the list first element. */

	for (i = 0; i < logfiles_num; i++)
	{
		if (max_seq < logfiles[i].seq)
		{
			max_seq = logfiles[i].seq;
			last_file_idx = i;
		}
	}

	return logfiles + last_file_idx;
}
