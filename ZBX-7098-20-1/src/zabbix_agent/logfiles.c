/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

#if defined(_WINDOWS)
#	include "gnuregex.h"
#	include "symbols.h"
#endif /* _WINDOWS */

#define MAX_LEN_MD5	512	/* maximum size of the initial part of the file to calculate MD5 sum for */

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

	part1_length = del - str + 1;
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
	struct stat	buf;
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

#else /* not _WINDOWS */
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
#endif /* _WINDOWS */

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s directory:'%s' format:'%s'", __function_name, zbx_result_string(ret),
			*directory, *format);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: file_part_md5sum_id                                              *
 *                                                                            *
 * Purpose: calculate MD5 sum of a specified part of the file and             *
 *          (optionally, only on Microsoft Windows) obtain 64-bit FileIndex   *
 *          or 128-bit FileId.                                                *
 *                                                                            *
 * Parameters:                                                                *
 *     filename - [IN] full pathname                                          *
 *     offset   - [IN] offset from the beginning of the file                  *
 *     length   - [IN] length of the part in bytes. Maximum is 512 bytes.     *
 *     md5buf   - [OUT] output buffer, 16-bytes long, where the calculated    *
 *                MD5 sum is placed                                           *
 *     use_ino  - [IN] how to use file IDs (on Microsoft Windows)             *
 *     dev      - [OUT] device ID                                             *
 *     use_lo   - [OUT] 64-bit nFileIndex or lower 64-bits of FileId          *
 *     use_hi   - [OUT] higher 64-bits of FileId                              *
 *                                                                            *
 * Return value: SUCCEED or FAIL                                              *
 *                                                                            *
 * Comments: Calculating MD5 sum and getting FileIndex or FileId both require *
 *           opening of file. For efficiency they are combined into one       *
 *           function.                                                        *
 *                                                                            *
 ******************************************************************************/
#ifdef _WINDOWS
static int	file_part_md5sum_id(const char *filename, zbx_uint64_t offset, int length, md5_byte_t *md5buf,
		int use_ino, zbx_uint64_t *dev, zbx_uint64_t *ino_lo, zbx_uint64_t *ino_hi)
#else
static int	file_part_md5sum_id(const char *filename, zbx_uint64_t offset, int length, md5_byte_t *md5buf)
#endif
{
	int		ret = FAIL, f;
	md5_state_t	state;
	char		buf[MAX_LEN_MD5];
#ifdef _WINDOWS
	intptr_t			h;	/* file HANDLE */
	BY_HANDLE_FILE_INFORMATION	hfi;
	FILE_ID_INFO			fid;
#endif

	if (MAX_LEN_MD5 < length)
		return ret;

	if (-1 == (f = zbx_open(filename, O_RDONLY)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot open \"%s\"': %s", filename, zbx_strerror(errno));
		return ret;
	}

#ifdef _WINDOWS
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
			zabbix_log(LOG_LEVEL_WARNING, "cannot get file information for \"%s\", error code:%u",
					filename, GetLastError());
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
						"\"%s\", error code:%u", filename, GetLastError());
				return ret;
			}
		}
	}
	else
		THIS_SHOULD_NEVER_HAPPEN;
#endif
	if (0 < offset && (zbx_offset_t)-1 == zbx_lseek(f, offset, SEEK_SET))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot set position to " ZBX_FS_UI64 " for file \"%s\": %s",
				offset, filename, zbx_strerror(errno));
		return ret;
	}

	if (length != (int)read(f, buf, (size_t)length))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot read " ZBX_FS_SIZE_T " bytes from file \"%s\": %s",
				(zbx_fs_size_t)length, filename, zbx_strerror(errno));
		return ret;
	}

	md5_init(&state);
	md5_append(&state, (const md5_byte_t *)buf, length);
	md5_finish(&state, md5buf);

	ret = SUCCEED;

	if (0 != close(f))
		zabbix_log(LOG_LEVEL_WARNING, "cannot close file '%s': %s", filename, zbx_strerror(errno));

	return ret;
}

static void	print_logfile_list(struct st_logfile *logfiles, int logfiles_num)
{
	int	i;

	for (i = 0; i < logfiles_num; i++)
	{
#ifdef _WINDOWS
		zabbix_log(LOG_LEVEL_DEBUG, "   nr:%d filename:'%s' mtime:%d size:" ZBX_FS_UI64 " processed_size:"
				ZBX_FS_UI64 " seq:%d dev:" ZBX_FS_UI64 " ino_hi:" ZBX_FS_UI64 " ino_lo:" ZBX_FS_UI64
				" md5size:%d md5buf:%02x%02x%02x%02x%02x%02x%02x%02x%02x%02x%02x%02x%02x%02x%02x%02x",
				i, logfiles[i].filename, logfiles[i].mtime, logfiles[i].size,
				logfiles[i].processed_size, logfiles[i].seq, logfiles[i].dev, logfiles[i].ino_hi,
				logfiles[i].ino_lo, logfiles[i].md5size, logfiles[i].md5buf[0], logfiles[i].md5buf[1],
				logfiles[i].md5buf[2], logfiles[i].md5buf[3], logfiles[i].md5buf[4],
				logfiles[i].md5buf[5], logfiles[i].md5buf[6], logfiles[i].md5buf[7],
				logfiles[i].md5buf[8], logfiles[i].md5buf[9], logfiles[i].md5buf[10],
				logfiles[i].md5buf[11], logfiles[i].md5buf[12], logfiles[i].md5buf[13],
				logfiles[i].md5buf[14], logfiles[i].md5buf[15]);
#else /* not _WINDOWS */
		zabbix_log(LOG_LEVEL_DEBUG, "   nr:%d filename:'%s' mtime:%d size:" ZBX_FS_UI64 " processed_size:"
				ZBX_FS_UI64 " seq:%d dev:" ZBX_FS_UI64 " ino:" ZBX_FS_UI64 " md5size:%d "
				"md5buf:%02x%02x%02x%02x%02x%02x%02x%02x%02x%02x%02x%02x%02x%02x%02x%02x",
				i, logfiles[i].filename, logfiles[i].mtime, logfiles[i].size,
				logfiles[i].processed_size, logfiles[i].seq, logfiles[i].dev, logfiles[i].ino_lo,
				logfiles[i].md5size, logfiles[i].md5buf[0], logfiles[i].md5buf[1],
				logfiles[i].md5buf[2], logfiles[i].md5buf[3], logfiles[i].md5buf[4],
				logfiles[i].md5buf[5], logfiles[i].md5buf[6], logfiles[i].md5buf[7],
				logfiles[i].md5buf[8], logfiles[i].md5buf[9], logfiles[i].md5buf[10],
				logfiles[i].md5buf[11], logfiles[i].md5buf[12], logfiles[i].md5buf[13],
				logfiles[i].md5buf[14], logfiles[i].md5buf[15]);
#endif /* _WINDOWS */
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
 * Return value: 0 - it is not the same file,                                 *
 *               1 - it could be the same file,                               *
 *                                                                            *
 * Comments: In some cases we can say that it IS NOT the same file.           *
 *           We can never say that it IS the same file and it has not been    *
 *           truncated and replaced with a similar one.                       *
 *                                                                            *
 ******************************************************************************/
#ifdef _WINDOWS
static int	is_same_file(const struct st_logfile *old, const struct st_logfile *new, int use_ino)
#else
static int	is_same_file(const struct st_logfile *old, const struct st_logfile *new)
#endif
{
#ifdef _WINDOWS
	if (1 == use_ino || 2 == use_ino)
	{
#endif
		if (old->ino_lo != new->ino_lo || old->dev != new->dev)
		{
			/* File's inode and device id cannot differ. */
			goto not_same;
		}
#ifdef _WINDOWS
	}

	if (2 == use_ino && (old->ino_hi != new->ino_hi))
	{
		/* File's inode (older 64-bits) cannot differ. */
		goto not_same;
	}
#endif

	if (old->mtime > new->mtime)
	{
		/* File's mtime cannot decrease unless manipulated. */
		goto not_same;
	}

	if (old->size > new->size)
	{
		/* File's size cannot decrease. Truncating or replacing a file with a smaller one */
		/* counts as 2 different files. */
		goto not_same;
	}

	if (old->size == new->size && old->mtime < new->mtime)
	{
		/* File's mtime cannot increase without changing size unless manipulated. */
		goto not_same;
	}

	if (-1 == old->md5size || -1 == new->md5size)
	{
		/* Cannot compare MD5 sums. Assume two different files - reporting twice is better than skipping. */
		goto not_same;
	}

	if (old->md5size > new->md5size)
	{
		/* File's initial block size from which MD5 sum is calculated cannot decrease. */
		goto not_same;
	}

	if (old->md5size == new->md5size)
	{
		if (0 != memcmp(old->md5buf, new->md5buf, sizeof(new->md5buf)))
		{
			/* MD5 sums differ */
			goto not_same;
		}
	}
	else
	{
		if (0 < old->md5size)
		{
			md5_byte_t	md5tmp;

			/* MD5 for the old file has been calculated from a smaller initial block */
#ifdef _WINDOWS
			if (FAIL == file_part_md5sum_id(new->filename, (zbx_uint64_t)0, old->md5size, &md5tmp, 0, NULL,
					NULL, NULL) || 0 != memcmp(old->md5buf, &md5tmp, sizeof(md5tmp)))
#else
			if (FAIL == file_part_md5sum_id(new->filename, (zbx_uint64_t)0, old->md5size, &md5tmp)
					|| 0 != memcmp(old->md5buf, &md5tmp, sizeof(md5tmp)))
#endif
			{
				goto not_same;
			}
		}
	}

	return 1;
not_same:
	return 0;
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
 * Comments:                                                                  *
 *          old2new[i][j] = "0" - i-th old file cannot be the j-th new file   *
 *          old2new[i][j] = "1" - i-th old file could be the j-th new file    *
 *                                                                            *
 ******************************************************************************/
#ifdef _WINDOWS
static void	setup_old2new(char *old2new, const struct st_logfile *old, int num_old,
		const struct st_logfile *new, int num_new, int use_ino)
#else
static void	setup_old2new(char *old2new, const struct st_logfile *old, int num_old,
		const struct st_logfile *new, int num_new)
#endif
{
	int	i, j;
	char	*p = old2new;

	for (i = 0; i < num_old; i++)
	{
		for (j = 0; j < num_new; j++)
		{
#ifdef _WINDOWS
			if (1 == is_same_file(old + i, new + j, use_ino))
#else
			if (1 == is_same_file(old + i, new + j))
#endif
				*(p + j) = '1';
			else
				*(p + j) = '0';

			zabbix_log(LOG_LEVEL_DEBUG, "setup_old2new: is_same_file(%s, %s) = %c",
					(old + i)->filename, (new + j)->filename, *(p + j));
		}
		p += (size_t)num_new * sizeof(char);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: resolve_old2new                                                  *
 *                                                                            *
 * Purpose: resolve non-unique mappings                                       *
 *                                                                            *
 * Parameters:                                                                *
 *          old2new - [IN] two dimensional array of possible mappings         *
 *          old     - [IN] old file list                                      *
 *          num_old - [IN] number of elements in the old file list            *
 *          new     - [IN] new file list                                      *
 *          num_new - [IN] number of elements in the new file list            *
 *                                                                            *
 * Return value: index of the new file or                                     *
 *               -1 if no mapping was found                                   *
 *                                                                            *
 ******************************************************************************/
static void	resolve_old2new(char *old2new, const struct st_logfile *old, int num_old,
		const struct st_logfile *new, int num_new)
{
	int	i, j, ones;
	char	*p;

	/* Is there 1:1 mapping ? Every row and column should have not more than one element '1'. */

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
non_unique:
	zabbix_log(LOG_LEVEL_DEBUG, "resolve_old2new(): non-unique mapping");
	return;
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

	p = old2new + (size_t)i_old * (size_t)num_new * sizeof(char);

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
 *             filename - name of a logfile (without a path)                  *
 *             st - structure returned by stat()                              *
 *             use_ino - parameter passed to file_part_md5sum()               *
 *                                                                            *
 * Return value: none                                                         *
 *                                                                            *
 * Author: Dmitry Borovikov                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
#ifdef _WINDOWS
static void add_logfile(struct st_logfile **logfiles, int *logfiles_alloc, int *logfiles_num, const char *filename,
		struct stat *st, int use_ino)
#else
static void add_logfile(struct st_logfile **logfiles, int *logfiles_alloc, int *logfiles_num, const char *filename,
		struct stat *st)
#endif
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

	if (!(0 == i && 0 == *logfiles_num) && !(0 < *logfiles_num && *logfiles_num == i))
	{
		/* do not move if there are no logfiles or we are appending the logfile */
		memmove((void *)&(*logfiles)[i + 1], (const void *)&(*logfiles)[i],
				(size_t)((*logfiles_num - i) * sizeof(struct st_logfile)));
	}

	(*logfiles)[i].filename = zbx_strdup(NULL, filename);
	(*logfiles)[i].mtime = st->st_mtime;
	(*logfiles)[i].size = (zbx_uint64_t)st->st_size;
	(*logfiles)[i].processed_size = 0;
	(*logfiles)[i].seq = 0;
	(*logfiles)[i].md5size = (zbx_uint64_t)MAX_LEN_MD5 > (zbx_uint64_t)st->st_size ? (int)st->st_size : MAX_LEN_MD5;

#ifdef _WINDOWS
	if (SUCCEED != file_part_md5sum_id(filename, (zbx_uint64_t)0, (*logfiles)[i].md5size, (*logfiles)[i].md5buf,
			use_ino, &(*logfiles)[i].dev, &(*logfiles)[i].ino_lo, &(*logfiles)[i].ino_hi))
#else
	(*logfiles)[i].dev = (zbx_uint64_t)st->st_dev;
	(*logfiles)[i].ino_lo = (zbx_uint64_t)st->st_ino;

	if (SUCCEED != file_part_md5sum_id(filename, (zbx_uint64_t)0, (*logfiles)[i].md5size, (*logfiles)[i].md5buf))
#endif
	{
		(*logfiles)[i].md5size = -1;
	}

	++(*logfiles_num);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
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
	int			i, j, start_idx, ret = FAIL, logfiles_num = 0, logfiles_alloc = 0, reg_error, seq = 1,
				max_old_seq = 0, old_last, first_file = 0;
	char			err_buf[MAX_STRING_LEN], *directory = NULL, *format = NULL, *logfile_candidate = NULL,
				*old2new = NULL;
	struct stat		file_buf;
	struct st_logfile	*logfiles = NULL;
#ifdef _WINDOWS
	int			win_err = 0;
	char			*find_path = NULL;
	intptr_t		find_handle;
	struct _finddata_t	find_data;
	char			*utf8;
	TCHAR			*find_path_uni, volume_mount_point[MAX_PATH + 1], fs_type[MAX_PATH + 1];
#else
	DIR			*dir = NULL;
	struct dirent		*d_ent = NULL;
#endif
	regex_t			re;
	time_t			now;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() is_logrt:%d filename:'%s' lastlogsize:" ZBX_FS_UI64 " mtime:%d "
			"error_count:%d", __function_name, is_logrt, filename, *lastlogsize, *mtime, *error_count);

	if (1 == is_logrt)
	{
		/* splitting filename into directory and file mask (reg exp) parts */
		if (SUCCEED != split_filename(filename, &directory, &format))
		{
			zabbix_log(LOG_LEVEL_WARNING, "filename '%s' does not contain a valid directory and/or format",
					filename);
			goto out;
		}

		if (0 != (reg_error = regcomp(&re, format, REG_EXTENDED | REG_NEWLINE | REG_NOSUB)))
		{
			regerror(reg_error, &re, err_buf, sizeof(err_buf));
			zabbix_log(LOG_LEVEL_WARNING, "Cannot compile a regexp describing filename pattern '%s' for "
					"a logrt[] item. Error: %s", format, err_buf);
			goto out;
		}
	}

	/* Minimize data loss if the system clock has been set back in time. */
	/* Setting the clock ahead of time is harmless in our case. */
	if ((time_t)-1 != (now = time(NULL)))
	{
		if (now < *mtime)
		{
			int	old_mtime;

			old_mtime = *mtime;
			*mtime = (int)now;

			zabbix_log(LOG_LEVEL_WARNING, "System clock has been set back in time. Setting agent mtime %d "
					"seconds back.", (int)(old_mtime - now));
		}
	}
	else
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot get system time");

		if (1 == is_logrt)
			regfree(&re);

		(*error_count)++;
		ret = SUCCEED;
		goto out;
	}

#ifdef _WINDOWS
	if (1 == is_logrt)
	{
		/* try to "open" Windows directory */
		find_path = zbx_dsprintf(find_path, "%s*", directory);
		find_handle = _findfirst((const char *)find_path, &find_data);
		if (-1 == find_handle)
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot open directory \"%s\" for reading: %s", directory,
					zbx_strerror(errno));
			win_err = 1;
		}
	}

	if (0 == win_err)
	{
		if (1 == is_logrt)
			find_path_uni = zbx_utf8_to_unicode(find_path);
		else
			find_path_uni = zbx_utf8_to_unicode(filename);

		if (0 == GetVolumePathName(find_path_uni, volume_mount_point,
			sizeof(volume_mount_point) / sizeof(TCHAR)))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot get volume mount point for \"%s\": error code:%u",
					(1 == is_logrt) ? find_path : filename, GetLastError());
			win_err = 1;
		}

		zbx_free(find_path_uni);
	}

	/* Which file system type this directory resides on ? */
	if (0 == win_err && 0 == GetVolumeInformation(volume_mount_point, NULL, 0, NULL, NULL, NULL, fs_type,
			sizeof(fs_type) / sizeof(TCHAR)))
	{
		utf8 = zbx_unicode_to_utf8(volume_mount_point);
		zabbix_log(LOG_LEVEL_WARNING, "cannot get volume information for \"%s\": error code:%u", utf8,
				GetLastError());
		zbx_free(utf8);
		win_err = 1;
	}

	if (1 == win_err)
	{
		if (1 == is_logrt)
		{
			regfree(&re);
			zbx_free(directory);
			zbx_free(format);
			zbx_free(find_path);
		}

		(*error_count)++;
		ret = SUCCEED;
		goto out;
	}

	utf8 = zbx_unicode_to_utf8(fs_type);

	if (0 == strcmp(utf8, "NTFS"))
		*use_ino = 1;			/* 64-bit FileIndex */
	else if (0 == strcmp(utf8, "ReFS"))
		*use_ino = 2;			/* 128-bit FileId */
	else
		*use_ino = 0;			/* cannot use inodes to identify files */

	zabbix_log(LOG_LEVEL_DEBUG, "log files reside on '%s' file system", utf8);
	zbx_free(utf8);

	if (1 == is_logrt)
	{
		do
		{
			logfile_candidate = zbx_dsprintf(logfile_candidate, "%s%s", directory, find_data.name);

			if (0 == zbx_stat(logfile_candidate, &file_buf))
			{
				if (S_ISREG(file_buf.st_mode) &&
						*mtime <= file_buf.st_mtime &&
						0 == regexec(&re, find_data.name, (size_t)0, NULL, 0))
				{
					zabbix_log(LOG_LEVEL_DEBUG, "adding file '%s' to logfiles", logfile_candidate);
					add_logfile(&logfiles, &logfiles_alloc, &logfiles_num, logfile_candidate,
							&file_buf, *use_ino);
				}
			}
			else
				zabbix_log(LOG_LEVEL_DEBUG, "cannot process entry '%s'", logfile_candidate);

			zbx_free(logfile_candidate);
		}
		while (0 == _findnext(find_handle, &find_data));

		if (-1 == _findclose(find_handle))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot close the find directory handle for '%s': %s", find_path,
					zbx_strerror(errno));
		}

		zbx_free(find_path);
	}
#else	/* not _WINDOWS */
	if (1 == is_logrt)
	{
		if (NULL == (dir = opendir(directory)))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot open directory '%s' for reading: %s", directory,
					zbx_strerror(errno));
			regfree(&re);
			zbx_free(directory);
			zbx_free(format);
			(*error_count)++;
			ret = SUCCEED;
			goto out;
		}

		/* on UNIX file systems we always assume that inodes can be used to identify files */
		/* i.e. use_ino = 1; */

		while (NULL != (d_ent = readdir(dir)))
		{
			logfile_candidate = zbx_dsprintf(logfile_candidate, "%s%s", directory, d_ent->d_name);

			if (0 == zbx_stat(logfile_candidate, &file_buf))
			{
				if (S_ISREG(file_buf.st_mode) &&
						*mtime <= file_buf.st_mtime &&
						0 == regexec(&re, d_ent->d_name, (size_t)0, NULL, 0))
				{
					zabbix_log(LOG_LEVEL_DEBUG, "adding file '%s' to logfiles", logfile_candidate);
					add_logfile(&logfiles, &logfiles_alloc, &logfiles_num, logfile_candidate,
							&file_buf);
				}
			}
			else
				zabbix_log(LOG_LEVEL_DEBUG, "cannot process entry '%s'", logfile_candidate);

			zbx_free(logfile_candidate);
		}

		if (-1 == closedir(dir))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot close directory '%s': %s", directory,
					zbx_strerror(errno));
		}
	}
#endif	/*_WINDOWS*/

	if (1 == is_logrt)
		regfree(&re);

	if (0 == is_logrt)
	{
		if (0 == zbx_stat(filename, &file_buf))
		{
			if (S_ISREG(file_buf.st_mode))
			{
				zabbix_log(LOG_LEVEL_DEBUG, "adding file '%s' to logfiles", filename);
#ifdef _WINDOWS
				add_logfile(&logfiles, &logfiles_alloc, &logfiles_num, filename, &file_buf, *use_ino);
#else
				add_logfile(&logfiles, &logfiles_alloc, &logfiles_num, filename, &file_buf);
#endif
			}
			else
			{
				zabbix_log(LOG_LEVEL_WARNING, "'%s' is not a regular file, it cannot be used in log[] "
						"item", filename);
				(*error_count)++;
				ret = SUCCEED;
				goto out;
			}
		}
		else
		{
			/* for log[] item a non-existing file means an error causing NOTSUPPORTED state */
			zabbix_log(LOG_LEVEL_WARNING, "cannot stat '%s': %s", filename, zbx_strerror(errno));
			(*error_count)++;
			ret = SUCCEED;
			goto out;
		}
	}

	start_idx = (1 == *skip_old_data && 0 < logfiles_num) ? logfiles_num - 1 : 0;

	/* mark files to be skipped as processed */
	for (i = 0; i < start_idx; i++)
	{
		logfiles[i].processed_size = logfiles[i].size;
		logfiles[i].seq = seq++;
	}

	if (0 < *logfiles_num_old && 0 < logfiles_num)
	{
		/* set up a mapping matrix from old files to new files */
		old2new = zbx_malloc(old2new, (size_t)logfiles_num * (size_t)(*logfiles_num_old) * sizeof(char));
#ifdef _WINDOWS
		setup_old2new(old2new, *logfiles_old, *logfiles_num_old, logfiles, logfiles_num, *use_ino);
#else
		setup_old2new(old2new, *logfiles_old, *logfiles_num_old, logfiles, logfiles_num);
#endif
		if (1 < *logfiles_num_old || 1 < logfiles_num)
			resolve_old2new(old2new, *logfiles_old, *logfiles_num_old, logfiles, logfiles_num);

		/* Find and mark for skipping files processed during the previous check. Such files can get into the */
		/* new file list if several files had the same mtime but their processing was not finished because of */
		/* error or maxlines limit. */
		for (i = 0; i < *logfiles_num_old; i++)
		{
			if ((*logfiles_old)[i].processed_size == (*logfiles_old)[i].size
					&& -1 != (j = find_old2new(old2new, logfiles_num, i))
					&& logfiles[j].size == (*logfiles_old)[i].size)
			{
				logfiles[j].processed_size = logfiles[j].size;
				logfiles[j].seq = seq++;
			}

			/* find the last file processed in the previous check */
			if (max_old_seq < (*logfiles_old)[i].seq)
			{
				max_old_seq = (*logfiles_old)[i].seq;
				old_last = i;
			}
		}

		/* find the first file to continue with in the new file list */
		if (0 < max_old_seq)
		{
			if (-1 == (start_idx = find_old2new(old2new, logfiles_num, old_last)))
			{
				/* Cannot find the successor of the last processed file from the previous check. */
				/* 'lastlogsize' does not apply in this case. */
				*lastlogsize = 0;
				start_idx = 0;
			}
			else
				first_file = 1;
		}
	}

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

	/* enter the loop with index of the first file to be processed, later continue the loop from the start */
	i = start_idx;

	/* from now assume success - it could be that there is nothing to do */
	ret = SUCCEED;

	while (i < logfiles_num)
	{
		if (logfiles[i].size != logfiles[i].processed_size)
		{
			ret = process_log(logfiles[i].filename, lastlogsize, (1 == is_logrt) ? mtime : NULL,
					skip_old_data, big_rec, encoding, regexps, regexps_num, pattern, p_count,
					s_count, process_value, server, port, hostname, key);

			logfiles[i].processed_size = *lastlogsize;
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

			if (i != logfiles_num - 1)
				*lastlogsize = 0;
		}

		if (0 != first_file)
		{
			first_file = 0;
			i = 0;
			continue;
		}

		i++;
	}

	if (0 == logfiles_num && 1 == is_logrt)
	{
		/* Do not make a logrt[] item NOTSUPPORTED if there are no matching log files or they are not */
		/* accessible (can happen during a rotation), just log the problem. */
		zabbix_log(LOG_LEVEL_WARNING, "there are no files matching '%s' in '%s'", format, directory);
		ret = SUCCEED;
	}

	zbx_free(old2new);
	zbx_free(directory);
	zbx_free(format);

	/* delete the old logfile list from the previous check */
	if (NULL != *logfiles_old)
	{
		for (i = 0; i < *logfiles_num_old; i++)
			zbx_free((*logfiles_old)[i].filename);

		*logfiles_num_old = 0;
		zbx_free(*logfiles_old);
	}

	/* remember the composed list of log files for using in the next check */
	*logfiles_num_old = logfiles_num;

	if (0 < logfiles_num)
		*logfiles_old = logfiles;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s error_count:%d", __function_name, zbx_result_string(ret), *error_count);

	return ret;
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
int	process_log(char *filename, zbx_uint64_t *lastlogsize, int *mtime, unsigned char *skip_old_data, int *big_rec,
		const char *encoding, ZBX_REGEXP *regexps, int regexps_num, const char *pattern, int *p_count,
		int *s_count, zbx_process_value_func_t process_value, const char *server, unsigned short port,
		const char *hostname, const char *key)
{
	const char	*__function_name = "process_log";

	int		f, ret = FAIL;
	struct stat	buf;
	zbx_uint64_t	l_size;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() filename:'%s' lastlogsize:" ZBX_FS_UI64 " mtime: %d",
			__function_name, filename, *lastlogsize, NULL != mtime ? *mtime : 0);

	if (0 != zbx_stat(filename, &buf))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot stat '%s': %s", filename, zbx_strerror(errno));
		goto out;
	}

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

		if (NULL != mtime)
			*mtime = (int)buf.st_mtime;

		ret = zbx_read2(f, lastlogsize, mtime, big_rec, encoding, regexps, regexps_num, pattern, p_count,
				s_count, process_value, server, port, hostname, key);
	}
	else
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot set position to " ZBX_FS_UI64 " for '%s': %s",
				l_size, filename, zbx_strerror(errno));
	}

	if (0 != close(f))
		zabbix_log(LOG_LEVEL_WARNING, "cannot close file '%s': %s", filename, zbx_strerror(errno));
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() filename:'%s' lastlogsize:" ZBX_FS_UI64 " mtime: %d ret:%s",
			__function_name, filename, *lastlogsize, NULL != mtime ? *mtime : 0, zbx_result_string(ret));

	return ret;
}
