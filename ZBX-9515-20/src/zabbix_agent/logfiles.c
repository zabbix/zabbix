/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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
#include "logfiles.h"
#include "log.h"
#include "md5.h"

#if defined(_WINDOWS)
#	include "gnuregex.h"
#	include "symbols.h"
#endif /* _WINDOWS */

#define MAX_LEN_MD5	512	/* maximum size of the initial part of the file to calculate MD5 sum for */

#define ZBX_SAME_FILE_ERROR	-1
#define ZBX_SAME_FILE_NO	0
#define ZBX_SAME_FILE_YES	1
#define ZBX_SAME_FILE_RETRY	2

/******************************************************************************
 *                                                                            *
 * Function: split_string                                                     *
 *                                                                            *
 * Purpose: separates given string to two parts by given delimiter in string  *
 *                                                                            *
 * Parameters: str - the string to split                                      *
 *             del - pointer to a character in the string                     *
 *             part1 - pointer to buffer for the first part with delimiter    *
 *             part2 - pointer to buffer for the second part                  *
 *                                                                            *
 * Return value: SUCCEED - on splitting without errors                        *
 *               FAIL - on splitting with errors                              *
 *                                                                            *
 * Author: Dmitry Borovikov, Aleksandrs Saveljevs                             *
 *                                                                            *
 * Comments: Memory for "part1" and "part2" is allocated only on SUCCEED.     *
 *                                                                            *
 ******************************************************************************/
static int	split_string(const char *str, const char *del, char **part1, char **part2)
{
	const char	*__function_name = "split_string";
	size_t		str_length = 0, part1_length = 0, part2_length = 0;
	int		ret = FAIL;

	assert(NULL != str && '\0' != *str);
	assert(NULL != del && '\0' != *del);
	assert(NULL != part1 && '\0' == *part1);	/* target 1 must be empty */
	assert(NULL != part2 && '\0' == *part2);	/* target 2 must be empty */

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() str:'%s' del:'%s'", __function_name, str, del);

	str_length = strlen(str);

	/* since the purpose of this function is to be used in split_filename(), we allow part1 to be */
	/* just *del (e.g., "/" - file system root), but we do not allow part2 (filename) to be empty */
	if (del < str || del >= (str + str_length - 1))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() cannot proceed: delimiter is out of range", __function_name);
		goto out;
	}

	part1_length = (size_t)(del - str + 1);
	part2_length = str_length - part1_length;

	*part1 = zbx_malloc(*part1, part1_length + 1);
	zbx_strlcpy(*part1, str, part1_length + 1);

	*part2 = zbx_malloc(*part2, part2_length + 1);
	zbx_strlcpy(*part2, str + part1_length, part2_length + 1);

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s part1:'%s' part2:'%s'", __function_name, zbx_result_string(ret),
			*part1, *part2);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: split_filename                                                   *
 *                                                                            *
 * Purpose: separates filename to directory and to file format (regexp)       *
 *                                                                            *
 * Parameters: filename - first parameter of log[] item                       *
 *                                                                            *
 * Return value: SUCCEED - on successful splitting                            *
 *               FAIL - on unable to split sensibly                           *
 *                                                                            *
 * Author: Dmitry Borovikov                                                   *
 *                                                                            *
 * Comments: Allocates memory for "directory" and "format" only on success.   *
 *           On fail, memory, allocated for "directory" and "format",         *
 *           is freed.                                                        *
 *                                                                            *
 ******************************************************************************/
static int	split_filename(const char *filename, char **directory, char **format)
{
	const char	*__function_name = "split_filename";
	const char	*separator = NULL;
	zbx_stat_t	buf;
	int		ret = FAIL;
#ifdef _WINDOWS
	size_t		sz;
#endif

	assert(NULL != directory && '\0' == *directory);
	assert(NULL != format && '\0' == *format);

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() filename:'%s'", __function_name, filename ? filename : "NULL");

	if (NULL == filename || '\0' == *filename)
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot split empty path");
		goto out;
	}

#ifdef _WINDOWS
	/* special processing for Windows, since PATH part cannot be simply divided from REGEXP part (file format) */
	for (sz = strlen(filename) - 1, separator = &filename[sz]; separator >= filename; separator--)
	{
		if (PATH_SEPARATOR != *separator)
			continue;

		zabbix_log(LOG_LEVEL_DEBUG, "%s() %s", __function_name, filename);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() %*s", __function_name, separator - filename + 1, "^");

		/* separator must be relative delimiter of the original filename */
		if (FAIL == split_string(filename, separator, directory, format))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot split '%s'", filename);
			goto out;
		}

		sz = strlen(*directory);

		/* Windows world verification */
		if (sz + 1 > MAX_PATH)
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot proceed: directory path is too long");
			zbx_free(*directory);
			zbx_free(*format);
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
		zbx_free(*format);
	}

	if (separator < filename)
		goto out;

#else	/* not _WINDOWS */
	if (NULL == (separator = strrchr(filename, (int)PATH_SEPARATOR)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "filename '%s' does not contain any path separator '%c'", filename,
				PATH_SEPARATOR);
		goto out;
	}
	if (SUCCEED != split_string(filename, separator, directory, format))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot split filename '%s' by '%c'", filename, PATH_SEPARATOR);
		goto out;
	}

	if (-1 == zbx_stat(*directory, &buf))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot find directory '%s' on the file system", *directory);
		zbx_free(*directory);
		zbx_free(*format);
		goto out;
	}

	if (0 == S_ISDIR(buf.st_mode))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot proceed: directory '%s' is a file", *directory);
		zbx_free(*directory);
		zbx_free(*format);
		goto out;
	}
#endif	/* _WINDOWS */

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s directory:'%s' format:'%s'", __function_name, zbx_result_string(ret),
			*directory, *format);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: file_start_md5                                                   *
 *                                                                            *
 * Purpose: calculate the MD5 sum of the first block of the file              *
 *                                                                            *
 * Parameters:                                                                *
 *     f        - [IN] file descriptor                                        *
 *     length   - [IN] length of the block in bytes. Maximum is 512 bytes.    *
 *     md5buf   - [OUT] output buffer, MD5_DIGEST_SIZE-bytes long, where the  *
 *                calculated MD5 sum is placed                                *
 *     filename - [IN] file name, used in error logging                       *
 *                                                                            *
 * Return value: SUCCEED or FAIL                                              *
 *                                                                            *
 ******************************************************************************/
static int	file_start_md5(int f, int length, md5_byte_t *md5buf, const char *filename)
{
	md5_state_t	state;
	char		buf[MAX_LEN_MD5];

	if (MAX_LEN_MD5 < length)
		return FAIL;

	if ((zbx_offset_t)-1 == zbx_lseek(f, 0, SEEK_SET))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot set position to 0 for file \"%s\": %s", filename,
				zbx_strerror(errno));
		return FAIL;
	}

	if (length != (int)read(f, buf, (size_t)length))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot read " ZBX_FS_SIZE_T " bytes from file \"%s\": %s",
				(zbx_fs_size_t)length, filename, zbx_strerror(errno));
		return FAIL;
	}

	md5_init(&state);
	md5_append(&state, (const md5_byte_t *)buf, length);
	md5_finish(&state, md5buf);

	return SUCCEED;
}

#ifdef _WINDOWS
/******************************************************************************
 *                                                                            *
 * Function: file_id                                                          *
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
 *                                                                            *
 * Return value: SUCCEED or FAIL                                              *
 *                                                                            *
 ******************************************************************************/
static int	file_id(int f, int use_ino, zbx_uint64_t *dev, zbx_uint64_t *ino_lo, zbx_uint64_t *ino_hi,
		const char *filename)
{
	int				ret = FAIL;
	intptr_t			h;	/* file HANDLE */
	BY_HANDLE_FILE_INFORMATION	hfi;
	FILE_ID_INFO			fid;

	if (-1 == (h = _get_osfhandle(f)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot get file handle from file descriptor for '%s'", filename);
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
			zabbix_log(LOG_LEVEL_WARNING, "cannot get file information for \"%s\": %s",
					filename, strerror_from_system(GetLastError()));
			return ret;
		}
	}
	else if (2 == use_ino)
	{
		if (NULL != zbx_GetFileInformationByHandleEx)
		{
			if (0 != zbx_GetFileInformationByHandleEx((HANDLE)h, FileIdInfo, &fid, sizeof(fid)))
			{
				*dev = fid.VolumeSerialNumber;
				*ino_lo = fid.FileId.LowPart;
				*ino_hi = fid.FileId.HighPart;
			}
			else
			{
				zabbix_log(LOG_LEVEL_WARNING, "cannot get extended file information for "
						"\"%s\": %s", filename, strerror_from_system(GetLastError()));
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
 * Function: set_use_ino_by_fs_type                                           *
 *                                                                            *
 * Purpose: find file system type and set 'use_ino' parameter                 *
 *                                                                            *
 * Parameters:                                                                *
 *     path     - [IN] directory or file name                                 *
 *     use_ino  - [IN] how to use file IDs                                    *
 *                                                                            *
 * Return value: SUCCEED or FAIL                                              *
 *                                                                            *
 ******************************************************************************/
static int	set_use_ino_by_fs_type(const char *path, int *use_ino)
{
	char	*utf8;
	wchar_t	*path_uni, mount_point[MAX_PATH + 1], fs_type[MAX_PATH + 1];

	path_uni = zbx_utf8_to_unicode(path);

	/* get volume mount point */
	if (0 == GetVolumePathName(path_uni, mount_point,
			sizeof(mount_point) / sizeof(wchar_t)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot get volume mount point for \"%s\": %s", path,
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
		zabbix_log(LOG_LEVEL_WARNING, "cannot get volume information for \"%s\": %s", utf8,
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
 * Function: print_logfile_list                                               *
 *                                                                            *
 * Purpose: write logfile list into log for debugging                         *
 *                                                                            *
 * Parameters:                                                                *
 *     logfiles     - [IN] array of logfiles                                  *
 *     logfiles_num - [IN] number of elements in the array                    *
 *                                                                            *
 ******************************************************************************/
static void	print_logfile_list(struct st_logfile *logfiles, int logfiles_num)
{
	int	i;

	for (i = 0; i < logfiles_num; i++)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "   nr:%d filename:'%s' mtime:%d size:" ZBX_FS_UI64 " processed_size:"
				ZBX_FS_UI64 " seq:%d incomplete:%d dev:" ZBX_FS_UI64 " ino_hi:" ZBX_FS_UI64 " ino_lo:"
				ZBX_FS_UI64
				" md5size:%d md5buf:%02x%02x%02x%02x%02x%02x%02x%02x%02x%02x%02x%02x%02x%02x%02x%02x",
				i, logfiles[i].filename, logfiles[i].mtime, logfiles[i].size,
				logfiles[i].processed_size, logfiles[i].seq, logfiles[i].incomplete, logfiles[i].dev,
				logfiles[i].ino_hi, logfiles[i].ino_lo, logfiles[i].md5size, logfiles[i].md5buf[0],
				logfiles[i].md5buf[1], logfiles[i].md5buf[2], logfiles[i].md5buf[3],
				logfiles[i].md5buf[4], logfiles[i].md5buf[5], logfiles[i].md5buf[6],
				logfiles[i].md5buf[7], logfiles[i].md5buf[8], logfiles[i].md5buf[9],
				logfiles[i].md5buf[10], logfiles[i].md5buf[11], logfiles[i].md5buf[12],
				logfiles[i].md5buf[13], logfiles[i].md5buf[14], logfiles[i].md5buf[15]);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: is_same_file                                                     *
 *                                                                            *
 * Purpose: find out wheter a file from the old list and a file from the new  *
 *          list could be the same file                                       *
 *                                                                            *
 * Parameters:                                                                *
 *          old     - [IN] file from the old list                             *
 *          new     - [IN] file from the new list                             *
 *          use_ino - [IN] 0 - do not use inodes in comparison,               *
 *                         1 - use up to 64-bit inodes in comparison,         *
 *                         2 - use 128-bit inodes in comparison.              *
 *                                                                            *
 * Return value: ZBX_SAME_FILE_NO - it is not the same file,                  *
 *               ZBX_SAME_FILE_YES - it could be the same file,               *
 *               ZBX_SAME_FILE_ERROR - error.                                 *
 *               ZBX_SAME_FILE_RETRY - retry on the next check                *
 *                                                                            *
 * Comments: In some cases we can say that it IS NOT the same file.           *
 *           We can never say that it IS the same file and it has not been    *
 *           truncated and replaced with a similar one.                       *
 *                                                                            *
 ******************************************************************************/
static int	is_same_file(const struct st_logfile *old, const struct st_logfile *new, int use_ino)
{
	int	ret = ZBX_SAME_FILE_NO;

	if (1 == use_ino || 2 == use_ino)
	{
		if (old->ino_lo != new->ino_lo || old->dev != new->dev)
		{
			/* File's inode and device id cannot differ. */
			goto out;
		}
	}

	if (2 == use_ino && old->ino_hi != new->ino_hi)
	{
		/* File's inode (older 64-bits) cannot differ. */
		goto out;
	}

	if (old->mtime > new->mtime)
	{
		/* File's mtime cannot decrease unless manipulated. */
		goto out;
	}

	if (old->size > new->size)
	{
		/* File's size cannot decrease. Truncating or replacing a file with a smaller one */
		/* counts as 2 different files. */
		goto out;
	}

	if (old->size == new->size && old->mtime < new->mtime)
	{
		/* Depending on file system it's possible that stat() was called */
		/* between mtime and file size update. In this situation we will */
		/* get a file with the old size and a new mtime.                 */
		/* On the first try we assume it's the same file, just its size  */
		/* has not been changed yet.                                     */
		/* If the size has not changed on the next check, then we assume */
		/* that some tampering was done and to be safe we will treat it  */
		/* as a different file.                                          */
		if (0 == old->retry)
		{
			zabbix_log(LOG_LEVEL_WARNING, "the modification time of log file \"%s\" has been updated"
					" without changing its size, try checking again later", old->filename);
			ret = ZBX_SAME_FILE_RETRY;
		}
		else
		{
			zabbix_log(LOG_LEVEL_WARNING, "after changing modification time the size of log file \"%s\""
					" still has not been updated, consider it to be a new file", old->filename);
		}

		goto out;
	}

	if (-1 == old->md5size || -1 == new->md5size)
	{
		/* Cannot compare MD5 sums. Assume two different files - reporting twice is better than skipping. */
		goto out;
	}

	if (old->md5size > new->md5size)
	{
		/* File's initial block size from which MD5 sum is calculated cannot decrease. */
		goto out;
	}

	if (old->md5size == new->md5size)
	{
		if (0 != memcmp(old->md5buf, new->md5buf, sizeof(new->md5buf)))
		{
			/* MD5 sums differ */
			goto out;
		}
	}
	else
	{
		if (0 < old->md5size)
		{
			/* MD5 for the old file has been calculated from a smaller block than for the new file */

			int		f;
			md5_byte_t	md5tmp[MD5_DIGEST_SIZE];

			if (-1 == (f = zbx_open(new->filename, O_RDONLY)))
			{
				zabbix_log(LOG_LEVEL_WARNING, "cannot open \"%s\": %s", new->filename,
						zbx_strerror(errno));
				ret = ZBX_SAME_FILE_ERROR;
				goto out;
			}

			if (SUCCEED == file_start_md5(f, old->md5size, md5tmp, new->filename))
			{
				ret = (0 == memcmp(old->md5buf, &md5tmp, sizeof(md5tmp))) ? ZBX_SAME_FILE_YES :
						ZBX_SAME_FILE_NO;
			}
			else
				ret = ZBX_SAME_FILE_ERROR;

			if (0 != close(f))
			{
				zabbix_log(LOG_LEVEL_WARNING, "cannot close file \"%s\": %s", new->filename,
						zbx_strerror(errno));
				ret = ZBX_SAME_FILE_ERROR;
			}

			goto out;
		}
	}

	ret = ZBX_SAME_FILE_YES;
out:
	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: setup_old2new                                                    *
 *                                                                            *
 * Purpose: fill an array of possible mappings from the old log files to the  *
 *          new log files.                                                    *
 *                                                                            *
 * Parameters:                                                                *
 *          old2new - [IN] two dimensional array of possible mappings         *
 *          old     - [IN] old file list                                      *
 *          num_old - [IN] number of elements in the old file list            *
 *          new     - [IN] new file list                                      *
 *          num_new - [IN] number of elements in the new file list            *
 *          use_ino - [IN] how to use inodes in is_same_file()                *
 *                                                                            *
 * Return value: SUCCEED or FAIL                                              *
 *                                                                            *
 * Comments:                                                                  *
 *    The array is filled with '0' and '1' which mean:                        *
 *       old2new[i][j] = '0' - the i-th old file IS NOT the j-th new file     *
 *       old2new[i][j] = '1' - the i-th old file COULD BE the j-th new file   *
 *                                                                            *
 ******************************************************************************/
static int	setup_old2new(char *old2new, struct st_logfile *old, int num_old,
		const struct st_logfile *new, int num_new, int use_ino)
{
	int	i, j, rc;
	char	*p = old2new;

	for (i = 0; i < num_old; i++)
	{
		for (j = 0; j < num_new; j++)
		{
			rc = is_same_file(old + i, new + j, use_ino);

			switch (rc)
			{
				case ZBX_SAME_FILE_NO:
					p[j] = '0';
					break;
				case ZBX_SAME_FILE_YES:
					if (1 == old[i].retry)
					{
						zabbix_log(LOG_LEVEL_DEBUG, "the size of log file \"%s\" has been"
								" updated since modification time change, consider"
								" it to be the same file", old->filename);
						old[i].retry = 0;
					}
					p[j] = '1';
					break;
				case ZBX_SAME_FILE_RETRY:
					old[i].retry = 1;
					/* break; is not missing here */
				case ZBX_SAME_FILE_ERROR:
					return FAIL;
			}

			zabbix_log(LOG_LEVEL_DEBUG, "setup_old2new: is_same_file(%s, %s) = %c",
					old[i].filename, new[j].filename, p[j]);
		}
		p += (size_t)num_new;
	}
	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: cross_out                                                        *
 *                                                                            *
 * Purpose: fill the given row and column with '0' except the element at the  *
 *          cross point and protected columns and protected rows              *
 *                                                                            *
 * Parameters:                                                                *
 *          arr    - [IN] two dimensional array                               *
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
static void	cross_out(char *arr, int n_rows, int n_cols, int row, int col, char *p_rows, char *p_cols)
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
 * Function: is_uniq_row                                                      *
 *                                                                            *
 * Purpose: check if there is only one element '1' in the given row           *
 *                                                                            *
 * Parameters:                                                                *
 *          arr    - [IN] two dimensional array                               *
 *          n_cols - [IN] number of columns in the array                      *
 *          row    - [IN] number of row to search                             *
 *                                                                            *
 * Return value: number of column where the '1' element was found or          *
 *               -1 if there are zero or multiple '1' elements in the row     *
 *                                                                            *
 ******************************************************************************/
static int	is_uniq_row(const char *arr, int n_cols, int row)
{
	int		i, ones = 0, ret = -1;
	const char	*p;

	p = arr + row * n_cols;			/* point to the first element of the 'row' */

	for (i = 0; i < n_cols; i++)
	{
		if ('1' == *p++)
		{
			if (2 == ++ones)
			{
				ret = -1;	/* non-unique mapping in the row */
				break;
			}

			ret = i;
		}
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: is_uniq_col                                                      *
 *                                                                            *
 * Purpose: check if there is only one element '1' in the given column        *
 *                                                                            *
 * Parameters:                                                                *
 *          arr    - [IN] two dimensional array                               *
 *          n_rows - [IN] number of rows in the array                         *
 *          n_cols - [IN] number of columns in the array                      *
 *          col    - [IN] number of column to search                          *
 *                                                                            *
 * Return value: number of row where the '1' element was found or             *
 *               -1 if there are zero or multiple '1' elements in the column  *
 *                                                                            *
 ******************************************************************************/
static int	is_uniq_col(const char *arr, int n_rows, int n_cols, int col)
{
	int		i, ones = 0, ret = -1;
	const char	*p;

	p = arr + col;				/* point to the top element of the 'col' */

	for (i = 0; i < n_rows; i++)
	{
		if ('1' == *p)
		{
			if (2 == ++ones)
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
 * Function: resolve_old2new                                                  *
 *                                                                            *
 * Purpose: resolve non-unique mappings                                       *
 *                                                                            *
 * Parameters:                                                                *
 *          old2new - [IN] two dimensional array of possible mappings         *
 *          num_old - [IN] number of elements in the old file list            *
 *          num_new - [IN] number of elements in the new file list            *
 *                                                                            *
 ******************************************************************************/
static void	resolve_old2new(char *old2new, int num_old, int num_new)
{
	int	i, j, ones;
	char	*p, *protected_rows = NULL, *protected_cols = NULL;

	/* Is there 1:1 mapping in both directions between files in the old and the new list ? */
	/* In this case every row and column has not more than one element '1'. */
	/* This is expected on UNIX (using inode numbers) and MS Windows (using FileID on NTFS, ReFS) */

	p = old2new;

	for (i = 0; i < num_old; i++)		/* loop over rows (old files) */
	{
		ones = 0;

		for (j = 0; j < num_new; j++)	/* loop over columns (new files) */
		{
			if ('1' == *p++)
			{
				if (2 == ++ones)
					goto non_unique;
			}
		}
	}

	for (i = 0; i < num_new; i++)		/* loop over columns */
	{
		p = old2new + i;
		ones = 0;

		for (j = 0; j < num_old; j++)	/* loop over rows */
		{
			if ('1' == *p)
			{
				if (2 == ++ones)
					goto non_unique;
			}
			p += num_new;
		}
	}

	return;
non_unique:
	/* This is expected on MS Windows using FAT32 and other file systems where inodes or file indexes */
	/* are either not preserved if a file is renamed or are not applicable. */

	zabbix_log(LOG_LEVEL_DEBUG, "resolve_old2new(): non-unique mapping");

	/* protect unique mappings from further modifications */

	protected_rows = zbx_calloc(protected_rows, (size_t)num_old, sizeof(char));
	protected_cols = zbx_calloc(protected_cols, (size_t)num_new, sizeof(char));

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
			if ('1' == protected_rows[i])
				continue;

			p = old2new + i * num_new;	/* the first element of the current row */

			for (j = 0; j < num_new; j++)
			{
				if ('1' == p[j] && '1' != protected_cols[j])
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
			if ('1' == protected_rows[i])
				continue;

			p = old2new + i * num_new;	/* the first element of the current row */

			for (j = num_new - 1; j >= 0; j--)
			{
				if ('1' == p[j] && '1' != protected_cols[j])
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
 * Function: find_old2new                                                     *
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
static int	find_old2new(char *old2new, int num_new, int i_old)
{
	int	i;
	char	*p;

	p = old2new + i_old * num_new;

	for (i = 0; i < num_new; i++)		/* loop over columns (new files) on i_old-th row */
	{
		if ('1' == *p++)
			return i;
	}

	return -1;
}

/******************************************************************************
 *                                                                            *
 * Function: add_logfile                                                      *
 *                                                                            *
 * Purpose: adds information of a logfile to the list of logfiles             *
 *                                                                            *
 * Parameters: logfiles - pointer to the list of logfiles                     *
 *             logfiles_alloc - number of logfiles memory was allocated for   *
 *             logfiles_num - number of already inserted logfiles             *
 *             filename - name of a logfile (with full path)                  *
 *             st - structure returned by stat()                              *
 *                                                                            *
 * Return value: none                                                         *
 *                                                                            *
 * Author: Dmitry Borovikov                                                   *
 *                                                                            *
 ******************************************************************************/
static void add_logfile(struct st_logfile **logfiles, int *logfiles_alloc, int *logfiles_num, const char *filename,
		zbx_stat_t *st)
{
	const char	*__function_name = "add_logfile";
	int		i = 0, cmp = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() filename:'%s' mtime:%d size:" ZBX_FS_UI64, __function_name, filename,
			(int)st->st_mtime, (zbx_uint64_t)st->st_size);

	/* must be done in any case */
	if (*logfiles_alloc == *logfiles_num)
	{
		*logfiles_alloc += 64;
		*logfiles = zbx_realloc(*logfiles, (size_t)*logfiles_alloc * sizeof(struct st_logfile));

		zabbix_log(LOG_LEVEL_DEBUG, "%s() logfiles:%p logfiles_alloc:%d",
				__function_name, *logfiles, *logfiles_alloc);
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
			if (0 > (cmp = strcmp(filename, (*logfiles)[i].filename)))
				continue;	/* (2) sort by descending name */

			if (0 == cmp)
			{
				/* the file already exists, quite impossible branch */
				zabbix_log(LOG_LEVEL_WARNING, "%s() file '%s' already added", __function_name,
						filename);
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
	(*logfiles)[i].mtime = st->st_mtime;
	(*logfiles)[i].md5size = -1;
	(*logfiles)[i].seq = 0;
	(*logfiles)[i].incomplete = 0;
#ifndef _WINDOWS
	(*logfiles)[i].dev = (zbx_uint64_t)st->st_dev;
	(*logfiles)[i].ino_lo = (zbx_uint64_t)st->st_ino;
	(*logfiles)[i].ino_hi = 0;
#endif
	(*logfiles)[i].size = (zbx_uint64_t)st->st_size;
	(*logfiles)[i].processed_size = 0;
	(*logfiles)[i].retry = 0;

	++(*logfiles_num);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: destroy_logfile_list                                             *
 *                                                                            *
 * Purpose: release resources allocated to a logfile list                     *
 *                                                                            *
 * Parameters:                                                                *
 *     logfiles       - [IN/OUT] pointer to the list of logfiles              *
 *     logfiles_alloc - [IN/OUT] number of logfiles memory was allocated for  *
 *     logfiles_num   - [IN/OUT] number of already inserted logfiles          *
 *                                                                            *
 ******************************************************************************/
static void	destroy_logfile_list(struct st_logfile **logfiles, int *logfiles_alloc, int *logfiles_num)
{
	int	i;

	for (i = 0; i < *logfiles_num; i++)
		zbx_free((*logfiles)[i].filename);

	*logfiles_num = 0;
	*logfiles_alloc = 0;
	zbx_free(*logfiles);
}

/******************************************************************************
 *                                                                            *
 * Function: pick_logfile                                                     *
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
static void	pick_logfile(const char *directory, const char *filename, int mtime, const regex_t *re,
		struct st_logfile **logfiles, int *logfiles_alloc, int *logfiles_num)
{
	char		*logfile_candidate = NULL;
	zbx_stat_t	file_buf;

	logfile_candidate = zbx_dsprintf(logfile_candidate, "%s%s", directory, filename);

	if (0 == zbx_stat(logfile_candidate, &file_buf))
	{
		if (S_ISREG(file_buf.st_mode) &&
				mtime <= file_buf.st_mtime &&
				0 == regexec(re, filename, (size_t)0, NULL, 0))
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
 * Function: pick_logfiles                                                    *
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
 *                                                                            *
 * Return value: SUCCEED or FAIL                                              *
 *                                                                            *
 * Comments: This is a helper function for make_logfile_list()                *
 *                                                                            *
 ******************************************************************************/
static int	pick_logfiles(const char *directory, int mtime, const regex_t *re, int *use_ino,
		struct st_logfile **logfiles, int *logfiles_alloc, int *logfiles_num)
{
#ifdef _WINDOWS
	int			ret = FAIL;
	char			*find_path = NULL;
	intptr_t		find_handle;
	struct _finddata_t	find_data;

	/* "open" Windows directory */
	find_path = zbx_dsprintf(find_path, "%s*", directory);
	find_handle = _findfirst((const char *)find_path, &find_data);
	if (-1 == find_handle)
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot open directory \"%s\" for reading: %s", directory,
				zbx_strerror(errno));
		zbx_free(find_path);
		return FAIL;
	}

	if (SUCCEED != set_use_ino_by_fs_type(find_path, use_ino))
		goto clean;

	do
	{
		pick_logfile(directory, find_data.name, mtime, re, logfiles, logfiles_alloc, logfiles_num);
	}
	while (0 == _findnext(find_handle, &find_data));

	ret = SUCCEED;
clean:
	if (-1 == _findclose(find_handle))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot close the find directory handle for '%s': %s", find_path,
				zbx_strerror(errno));
		ret = FAIL;
	}

	zbx_free(find_path);

	return ret;
#else
	DIR		*dir = NULL;
	struct dirent	*d_ent = NULL;

	if (NULL == (dir = opendir(directory)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot open directory \"%s\" for reading: %s", directory,
				zbx_strerror(errno));
		return FAIL;
	}

	/* on UNIX file systems we always assume that inodes can be used to identify files */
	*use_ino = 1;

	while (NULL != (d_ent = readdir(dir)))
	{
		pick_logfile(directory, d_ent->d_name, mtime, re, logfiles, logfiles_alloc, logfiles_num);
	}

	if (-1 == closedir(dir))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot close directory '%s': %s", directory, zbx_strerror(errno));
		return FAIL;
	}

	return SUCCEED;
#endif
}

/******************************************************************************
 *                                                                            *
 * Function: make_logfile_list                                                *
 *                                                                            *
 * Purpose: select log files to be analyzed and make a list, set 'use_ino'    *
 *          parameter                                                         *
 *                                                                            *
 * Parameters:                                                                *
 *     is_logrt       - [IN] Item type: 0 - log[], 1 - logrt[]                *
 *     filename       - [IN] logfile name (regular expression with a path)    *
 *     mtime          - [IN] last modification time of the file               *
 *     logfiles       - [IN/OUT] pointer to the list of logfiles              *
 *     logfiles_alloc - [IN/OUT] number of logfiles memory was allocated for  *
 *     logfiles_num   - [IN/OUT] number of already inserted logfiles          *
 *     use_ino        - [IN/OUT] how to use inode numbers                     *
 *                                                                            *
 * Return value: SUCCEED or FAIL                                              *
 *                                                                            *
 ******************************************************************************/
static int	make_logfile_list(int is_logrt, const char *filename, const int *mtime, struct st_logfile **logfiles,
		int *logfiles_alloc, int *logfiles_num, int *use_ino)
{
	int		ret = SUCCEED, i;
	zbx_stat_t	file_buf;

	if (0 == is_logrt)	/* log[] item */
	{
		if (0 != zbx_stat(filename, &file_buf))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot stat '%s': %s", filename, zbx_strerror(errno));
			ret = FAIL;
			goto clean;
		}

		if (!S_ISREG(file_buf.st_mode))
		{
			zabbix_log(LOG_LEVEL_WARNING, "'%s' is not a regular file, it cannot be used in log[] item",
					filename);
			ret = FAIL;
			goto clean;
		}

		add_logfile(logfiles, logfiles_alloc, logfiles_num, filename, &file_buf);
#ifdef _WINDOWS
		if (SUCCEED != set_use_ino_by_fs_type(filename, use_ino))
		{
			ret = FAIL;
			goto clean;
		}
#else
		/* on UNIX file systems we always assume that inodes can be used to identify files */
		*use_ino = 1;
#endif
	}
	else	/* logrt[] item */
	{
		char			*directory = NULL, *format = NULL;
		int			reg_error;
		regex_t			re;

		/* split a filename into directory and file mask (regular expression) parts */
		if (SUCCEED != split_filename(filename, &directory, &format))
		{
			zabbix_log(LOG_LEVEL_WARNING, "filename '%s' does not contain a valid directory and/or format",
					filename);
			ret = FAIL;
			goto clean;
		}

		if (0 != (reg_error = regcomp(&re, format, REG_EXTENDED | REG_NEWLINE | REG_NOSUB)))
		{
			char	err_buf[MAX_STRING_LEN];

			regerror(reg_error, &re, err_buf, sizeof(err_buf));
			zabbix_log(LOG_LEVEL_WARNING, "Cannot compile a regexp describing filename pattern '%s' for "
					"a logrt[] item. Error: %s", format, err_buf);
			ret = FAIL;
			goto clean1;
		}

		if (SUCCEED != pick_logfiles(directory, *mtime, &re, use_ino, logfiles, logfiles_alloc, logfiles_num))
		{
			ret = FAIL;
			goto clean2;
		}

		if (0 == *logfiles_num)
		{
			/* Do not make a logrt[] item NOTSUPPORTED if there are no matching log files or they are not */
			/* accessible (can happen during a rotation), just log the problem. */
#ifdef _WINDOWS
			zabbix_log(LOG_LEVEL_WARNING, "there are no files matching \"%s\" in \"%s\" or insufficient "
					"access rights", format, directory);
#else
			if (0 != access(directory, X_OK))
			{
				zabbix_log(LOG_LEVEL_WARNING, "insufficient access rights (no \"execute\" permission) "
						"to directory \"%s\": %s", directory, zbx_strerror(errno));
			}
			else
			{
				zabbix_log(LOG_LEVEL_WARNING, "there are no files matching \"%s\" in \"%s\"", format,
						directory);
			}
#endif
		}
clean2:
		regfree(&re);
clean1:
		zbx_free(directory);
		zbx_free(format);

		if (FAIL == ret)
			goto clean;
	}

	/* Fill in MD5 sums and file indexes in the logfile list. */
	/* These operations require opening of file, therefore we group them together. */
	for (i = 0; i < *logfiles_num; i++)
	{
		int			f;
		struct st_logfile	*p;

		p = *logfiles + i;

		if (-1 == (f = zbx_open(p->filename, O_RDONLY)))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot open \"%s\"': %s", p->filename, zbx_strerror(errno));
			ret = FAIL;
			break;
		}

		p->md5size = (zbx_uint64_t)MAX_LEN_MD5 > p->size ? (int)p->size : MAX_LEN_MD5;

		if (SUCCEED != file_start_md5(f, p->md5size, p->md5buf, p->filename))
		{
			ret = FAIL;
			goto clean3;
		}
#ifdef _WINDOWS
		if (SUCCEED != file_id(f, *use_ino, &p->dev, &p->ino_lo, &p->ino_hi, p->filename))
			ret = FAIL;
#endif	/*_WINDOWS*/
clean3:
		if (0 != close(f))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot close file '%s': %s", p->filename, zbx_strerror(errno));
			ret = FAIL;
			break;
		}
	}
clean:
	if (FAIL == ret && NULL != *logfiles)
		destroy_logfile_list(logfiles, logfiles_alloc, logfiles_num);

	return	ret;
}

/******************************************************************************
 *                                                                            *
 * Function: process_log                                                      *
 *                                                                            *
 * Purpose: Match new records in logfile with regexp, transmit matching       *
 *          records to Zabbix server                                          *
 *                                                                            *
 * Parameters:                                                                *
 *     filename       - [IN] logfile name                                     *
 *     lastlogsize    - [IN/OUT] offset from the beginning of the file        *
 *     mtime          - [IN] file modification time for reporting to server   *
 *     skip_old_data  - [IN/OUT] start from the beginning of the file or      *
 *                      jump to the end                                       *
 *     big_rec        - [IN/OUT] state variable to remember whether a long    *
 *                      record is being processed                             *
 *     incomplete     - [OUT] 0 - the last record ended with a newline,       *
 *                      1 - there was no newline at the end of the last       *
 *                      record.                                               *
 *     encoding       - [IN] text string describing encoding.                 *
 *                        The following encodings are recognized:             *
 *                          "UNICODE"                                         *
 *                          "UNICODEBIG"                                      *
 *                          "UNICODEFFFE"                                     *
 *                          "UNICODELITTLE"                                   *
 *                          "UTF-16"   "UTF16"                                *
 *                          "UTF-16BE" "UTF16BE"                              *
 *                          "UTF-16LE" "UTF16LE"                              *
 *                          "UTF-32"   "UTF32"                                *
 *                          "UTF-32BE" "UTF32BE"                              *
 *                          "UTF-32LE" "UTF32LE".                             *
 *                        "" (empty string) means a single-byte character set.*
 *     regexps        - [IN] array of regexps                                 *
 *     regexps_num    - [IN] number of regexp                                 *
 *     pattern        - [IN] pattern to match                                 *
 *     p_count        - [IN/OUT] limit of records to be processed             *
 *     s_count        - [IN/OUT] limit of records to be sent to server        *
 *     process_value  - [IN] pointer to function process_value()              *
 *     server         - [IN] server to send data to                           *
 *     port           - [IN] port to send data to                             *
 *     hostname       - [IN] hostname the data comes from                     *
 *     key            - [IN] item key the data belongs to                     *
 *                                                                            *
 * Return value: returns SUCCEED on successful reading,                       *
 *               FAIL on other cases                                          *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *           This function does not deal with log file rotation.              *
 *                                                                            *
 ******************************************************************************/
static int	process_log(char *filename, zbx_uint64_t *lastlogsize, int *mtime, unsigned char *skip_old_data,
		int *big_rec, int *incomplete, const char *encoding, ZBX_REGEXP *regexps, int regexps_num,
		const char *pattern, int *p_count, int *s_count, zbx_process_value_func_t process_value,
		const char *server, unsigned short port, const char *hostname, const char *key)
{
	const char	*__function_name = "process_log";

	int		f, ret = FAIL;
	zbx_stat_t	buf;
	zbx_uint64_t	l_size;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() filename:'%s' lastlogsize:" ZBX_FS_UI64 " mtime:%d",
			__function_name, filename, *lastlogsize, NULL != mtime ? *mtime : 0);

	if (0 != zbx_stat(filename, &buf))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot stat '%s': %s", filename, zbx_strerror(errno));
		goto out;
	}

	if (NULL != mtime)
		*mtime = (int)buf.st_mtime;

	if ((zbx_uint64_t)buf.st_size == *lastlogsize)
	{
		/* The file size has not changed. Nothing to do. Here we do not deal with a case of changing */
		/* a logfile's content while keeping the same length. */
		ret = SUCCEED;
		goto out;
	}

	if (-1 == (f = zbx_open(filename, O_RDONLY)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot open '%s': %s", filename, zbx_strerror(errno));
		goto out;
	}

	l_size = *lastlogsize;

	if (1 == *skip_old_data)
	{
		l_size = (zbx_uint64_t)buf.st_size;
		zabbix_log(LOG_LEVEL_DEBUG, "skipping old data in filename:'%s' to lastlogsize:" ZBX_FS_UI64,
				filename, l_size);
	}

	if ((zbx_uint64_t)buf.st_size < l_size)		/* handle file truncation */
		l_size = 0;

	if ((zbx_offset_t)-1 != zbx_lseek(f, l_size, SEEK_SET))
	{
		*lastlogsize = l_size;
		*skip_old_data = 0;

		ret = zbx_read2(f, lastlogsize, mtime, big_rec, incomplete, encoding, regexps, regexps_num, pattern,
				p_count, s_count, process_value, server, port, hostname, key);
	}
	else
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot set position to " ZBX_FS_UI64 " for '%s': %s",
				l_size, filename, zbx_strerror(errno));
	}

	if (0 != close(f))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot close file '%s': %s", filename, zbx_strerror(errno));
		ret = FAIL;
	}
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() filename:'%s' lastlogsize:" ZBX_FS_UI64 " mtime:%d ret:%s",
			__function_name, filename, *lastlogsize, NULL != mtime ? *mtime : 0, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: process_logrt                                                    *
 *                                                                            *
 * Purpose: Find new records in logfiles                                      *
 *                                                                            *
 * Parameters:                                                                *
 *     is_logrt         - [IN] Item type: 0 - log[], 1 - logrt[]              *
 *     filename         - [IN] logfile name (regular expression with a path)  *
 *     lastlogsize      - [IN/OUT] offset from the beginning of the file      *
 *     mtime            - [IN/OUT] last modification time of the file         *
 *     skip_old_data    - [IN/OUT] start from the beginning of the file or    *
 *                        jump to the end                                     *
 *     big_rec          - [IN/OUT] state variable to remember whether a long  *
 *                        record is being processed                           *
 *     use_ino          - [IN/OUT] how to use inode numbers                   *
 *     error_count      - [IN/OUT] number of errors (for limiting retries)    *
 *     logfiles_old     - [IN/OUT] array of logfiles from the last check      *
 *     logfiles_num_old - [IN/OUT] number of elements in "logfiles_old"       *
 *     encoding         - [IN] text string describing encoding.               *
 *                          The following encodings are recognized:           *
 *                            "UNICODE"                                       *
 *                            "UNICODEBIG"                                    *
 *                            "UNICODEFFFE"                                   *
 *                            "UNICODELITTLE"                                 *
 *                            "UTF-16"   "UTF16"                              *
 *                            "UTF-16BE" "UTF16BE"                            *
 *                            "UTF-16LE" "UTF16LE"                            *
 *                            "UTF-32"   "UTF32"                              *
 *                            "UTF-32BE" "UTF32BE"                            *
 *                            "UTF-32LE" "UTF32LE".                           *
 *                          "" (empty string) means a single-byte character   *
 *                             set.                                           *
 *     regexps          - [IN] array of regexps                               *
 *     regexps_num      - [IN] number of regexp                               *
 *     pattern          - [IN] pattern to match                               *
 *     p_count          - [IN/OUT] limit of records to be processed           *
 *     s_count          - [IN/OUT] limit of records to be sent to server      *
 *     process_value    - [IN] pointer to function process_value()            *
 *     server           - [IN] server to send data to                         *
 *     port             - [IN] port to send data to                           *
 *     hostname         - [IN] hostname the data comes from                   *
 *     key              - [IN] item key the data belongs to                   *
 *                                                                            *
 * Return value: returns SUCCEED on successful reading,                       *
 *               FAIL on other cases                                          *
 *                                                                            *
 * Author: Dmitry Borovikov (logrotation)                                     *
 *                                                                            *
 ******************************************************************************/
int	process_logrt(int is_logrt, char *filename, zbx_uint64_t *lastlogsize, int *mtime, unsigned char *skip_old_data,
		int *big_rec, int *use_ino, int *error_count, struct st_logfile **logfiles_old, int *logfiles_num_old,
		const char *encoding, ZBX_REGEXP *regexps, int regexps_num, const char *pattern, int *p_count,
		int *s_count, zbx_process_value_func_t process_value, const char *server, unsigned short port,
		const char *hostname, const char *key)
{
	const char		*__function_name = "process_logrt";
	int			i, j, start_idx, ret = FAIL, logfiles_num = 0, logfiles_alloc = 0, seq = 1,
				max_old_seq = 0, old_last, from_first_file = 1;
	char			*old2new = NULL;
	struct st_logfile	*logfiles = NULL;
	time_t			now;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() is_logrt:%d filename:'%s' lastlogsize:" ZBX_FS_UI64 " mtime:%d "
			"error_count:%d", __function_name, is_logrt, filename, *lastlogsize, *mtime, *error_count);

	/* Minimize data loss if the system clock has been set back in time. */
	/* Setting the clock ahead of time is harmless in our case. */
	if (*mtime > (now = time(NULL)))
	{
		int	old_mtime;

		old_mtime = *mtime;
		*mtime = (int)now;

		zabbix_log(LOG_LEVEL_WARNING, "System clock has been set back in time. Setting agent mtime %d "
				"seconds back.", (int)(old_mtime - now));
	}

	if (SUCCEED != make_logfile_list(is_logrt, filename, mtime, &logfiles, &logfiles_alloc, &logfiles_num, use_ino))
	{
		/* an error occured or a file was not accessible for a log[] item */
		(*error_count)++;
		ret = SUCCEED;
		goto out;
	}

	if (0 == logfiles_num)
	{
		/* there were no files for a logrt[] item to analyze */
		ret = SUCCEED;
		goto out;
	}

	start_idx = (1 == *skip_old_data ? logfiles_num - 1 : 0);

	/* mark files to be skipped as processed (in case of 'skip_old_data' was set) */
	for (i = 0; i < start_idx; i++)
	{
		logfiles[i].processed_size = logfiles[i].size;
		logfiles[i].seq = seq++;
	}

	if (0 < *logfiles_num_old && 0 < logfiles_num)
	{
		/* set up a mapping array from old files to new files */
		old2new = zbx_malloc(old2new, (size_t)logfiles_num * (size_t)(*logfiles_num_old) * sizeof(char));

		if (SUCCEED != setup_old2new(old2new, *logfiles_old, *logfiles_num_old, logfiles, logfiles_num,
				*use_ino))
		{
			destroy_logfile_list(&logfiles, &logfiles_alloc, &logfiles_num);
			zbx_free(old2new);
			(*error_count)++;
			ret = SUCCEED;
			goto out;
		}

		if (1 < *logfiles_num_old || 1 < logfiles_num)
			resolve_old2new(old2new, *logfiles_num_old, logfiles_num);

		/* Transfer data about fully and partially processed files from the old file list to the new list. */
		for (i = 0; i < *logfiles_num_old; i++)
		{
			if (0 < (*logfiles_old)[i].processed_size && 0 == (*logfiles_old)[i].incomplete &&
					-1 != (j = find_old2new(old2new, logfiles_num, i)))
			{
				if ((*logfiles_old)[i].size == (*logfiles_old)[i].processed_size
						&& (*logfiles_old)[i].size == logfiles[j].size)
				{
					/* the file was fully processed during the previous check and must be ignored */
					/* during this check */
					logfiles[j].processed_size = logfiles[j].size;
					logfiles[j].seq = seq++;
				}
				else
				{
					/* the file was not fully processed during the previous check or has grown */
					logfiles[j].processed_size = (*logfiles_old)[i].processed_size;
				}
			}
			else if (1 == (*logfiles_old)[i].incomplete &&
					-1 != (j = find_old2new(old2new, logfiles_num, i)))
			{
				if ((*logfiles_old)[i].size < logfiles[j].size)
				{
					/* The file was not fully processed because of incomplete last record */
					/* but it has grown. Try to process it further. */
					logfiles[j].incomplete = 0;
				}
				else
					logfiles[j].incomplete = 1;

				logfiles[j].processed_size = (*logfiles_old)[i].processed_size;
			}

			/* find the last file processed (fully or partially) in the previous check */
			if (max_old_seq < (*logfiles_old)[i].seq)
			{
				max_old_seq = (*logfiles_old)[i].seq;
				old_last = i;
			}
		}

		/* find the first file to continue from in the new file list */
		if (0 < max_old_seq && -1 == (start_idx = find_old2new(old2new, logfiles_num, old_last)))
		{
			/* Cannot find the successor of the last processed file from the previous check. */
			/* 'lastlogsize' does not apply in this case. */
			*lastlogsize = 0;
			start_idx = 0;
		}
	}

	zbx_free(old2new);

	zabbix_log(LOG_LEVEL_DEBUG, "process_logrt() old file list:");
	if (NULL != *logfiles_old)
		print_logfile_list(*logfiles_old, *logfiles_num_old);
	else
		zabbix_log(LOG_LEVEL_DEBUG, "   file list empty");

	zabbix_log(LOG_LEVEL_DEBUG, "process_logrt() new file list: (mtime:%d lastlogsize:" ZBX_FS_UI64
			" start_idx:%d)", *mtime, *lastlogsize, start_idx);
	if (NULL != logfiles)
		print_logfile_list(logfiles, logfiles_num);
	else
		zabbix_log(LOG_LEVEL_DEBUG, "   file list empty");

	/* forget the old logfile list */
	if (NULL != *logfiles_old)
	{
		for (i = 0; i < *logfiles_num_old; i++)
			zbx_free((*logfiles_old)[i].filename);

		*logfiles_num_old = 0;
		zbx_free(*logfiles_old);
	}

	/* enter the loop with index of the first file to be processed, later continue the loop from the start */
	i = start_idx;

	/* from now assume success - it could be that there is nothing to do */
	ret = SUCCEED;

	while (i < logfiles_num)
	{
		if (0 == logfiles[i].incomplete && (logfiles[i].size != logfiles[i].processed_size ||
				0 == logfiles[i].seq))
		{
			if (start_idx != i)
				*lastlogsize = logfiles[i].processed_size;

			ret = process_log(logfiles[i].filename, lastlogsize, (1 == is_logrt) ? mtime : NULL,
					skip_old_data, big_rec, &logfiles[i].incomplete, encoding, regexps, regexps_num,
					pattern, p_count, s_count, process_value, server, port, hostname, key);

			/* process_log() advances 'lastlogsize' only on success therefore */
			/* we do not check for errors here */
			logfiles[i].processed_size = *lastlogsize;

			/* Mark file as processed (at least partially). In case if process_log() failed we will stop */
			/* the current checking. In the next check the file will be marked in the list of old files */
			/* and we will know where we left off. */
			logfiles[i].seq = seq++;

			if (SUCCEED != ret)
			{
				(*error_count)++;
				ret = SUCCEED;
				break;
			}

			if (0 >= *p_count || 0 >= *s_count)
			{
				ret = SUCCEED;
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

	/* remember the current logfile list */
	*logfiles_num_old = logfiles_num;

	if (0 < logfiles_num)
		*logfiles_old = logfiles;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s error_count:%d", __function_name, zbx_result_string(ret),
			*error_count);

	return ret;
}
