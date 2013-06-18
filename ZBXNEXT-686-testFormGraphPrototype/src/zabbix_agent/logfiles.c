/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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
		zabbix_log(LOG_LEVEL_DEBUG, "cannot split empty path");
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
			zabbix_log(LOG_LEVEL_DEBUG, "cannot split '%s'", filename);
			goto out;
		}

		sz = strlen(*directory);

		/* Windows world verification */
		if (sz + 1 > MAX_PATH)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "cannot proceed: directory path is too long");
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
		zabbix_log(LOG_LEVEL_DEBUG, "filename '%s' does not contain any path separator '%c'", filename, PATH_SEPARATOR);
		goto out;
	}
	if (SUCCEED != split_string(filename, separator, directory, format))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "cannot split filename '%s' by '%c'", filename, PATH_SEPARATOR);
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

struct st_logfile
{
	char	*filename;
	int	mtime;
};

/******************************************************************************
 *                                                                            *
 * Function: free_logfiles                                                    *
 *                                                                            *
 * Purpose: releases memory allocated for logfiles                            *
 *                                                                            *
 * Parameters: logfiles - pointer to the list of logfiles                     *
 *             logfiles_alloc - number of logfiles memory was allocated for   *
 *             logfiles_num - number of already inserted logfiles             *
 *                                                                            *
 * Return value: none                                                         *
 *                                                                            *
 * Author: Dmitry Borovikov                                                   *
 *                                                                            *
 * Comments: none                                                             *
 *                                                                            *
 ******************************************************************************/
static void free_logfiles(struct st_logfile **logfiles, int *logfiles_alloc, int *logfiles_num)
{
	const char	*__function_name = "free_logfiles";
	int		i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() logfiles_num:%d", __function_name, *logfiles_num);

	for (i = 0; i < *logfiles_num; i++)
		zbx_free((*logfiles)[i].filename);

	zbx_free(*logfiles);
	*logfiles_alloc = 0;
	*logfiles_num = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
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
 *             mtime - modification time of a logfile                         *
 *                                                                            *
 * Return value: none                                                         *
 *                                                                            *
 * Author: Dmitry Borovikov                                                   *
 *                                                                            *
 * Comments: Must change sorting order to decrease a number of memory moves!  *
 *           Do not forget to change process_log() accordingly!               *
 *                                                                            *
 ******************************************************************************/
static void add_logfile(struct st_logfile **logfiles, int *logfiles_alloc, int *logfiles_num, const char *filename, int mtime)
{
	const char	*__function_name = "add_logfile";
	int		i = 0, cmp = 0;

	assert(NULL != logfiles);
	assert(NULL != logfiles_alloc);
	assert(NULL != logfiles_num);
	assert(0 <= *logfiles_num);

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() filename:'%s' mtime:%d", __function_name, filename, mtime);

	/* must be done in any case */
	if (*logfiles_alloc == *logfiles_num)
	{
		*logfiles_alloc += 64;
		*logfiles = zbx_realloc(*logfiles, *logfiles_alloc * sizeof(struct st_logfile));

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
		if (mtime > (*logfiles)[i].mtime)
			continue;	/* (1) sort by ascending mtime */

		if (mtime == (*logfiles)[i].mtime)
		{
			if (0 > (cmp = strcmp(filename, (*logfiles)[i].filename)))
				continue;	/* (2) sort by descending name */

			if (0 == cmp)
			{
				/* the file already exists, quite impossible branch */
				zabbix_log(LOG_LEVEL_DEBUG, "%s() file '%s' already added", __function_name, filename);
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

	(*logfiles)[i].filename = strdup(filename);
	(*logfiles)[i].mtime = mtime;
	++(*logfiles_num);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: process_logrt                                                    *
 *                                                                            *
 * Purpose: Get message from logfile with rotation                            *
 *                                                                            *
 * Parameters: filename - logfile name (regular expression with a path)       *
 *             lastlogsize - offset for message                               *
 *             mtime - last modification time of the file                     *
 *             value - pointer for logged message                             *
 *                                                                            *
 * Return value: returns SUCCEED on successful reading,                       *
 *               FAIL on other cases                                          *
 *                                                                            *
 * Author: Dmitry Borovikov (logrotation)                                     *
 *                                                                            *
 * Comments:                                                                  *
 *    This function allocates memory for 'value', because use zbx_free.       *
 *    Return SUCCEED and NULL value if end of file received.                  *
 *                                                                            *
 ******************************************************************************/
int	process_logrt(char *filename, zbx_uint64_t *lastlogsize, int *mtime, char **value, const char *encoding,
		unsigned char skip_old_data)
{
	const char		*__function_name = "process_logrt";
	int			i = 0, nbytes, ret = FAIL, logfiles_num = 0, logfiles_alloc = 0, fd = 0, length = 0, j = 0;
	char			buffer[MAX_BUFFER_LEN], *directory = NULL, *format = NULL, *logfile_candidate = NULL;
	struct stat		file_buf;
	struct st_logfile	*logfiles = NULL;
#ifdef _WINDOWS
	char			*find_path = NULL, *file_name_utf8;
	wchar_t			*find_wpath;
	intptr_t		find_handle;
	struct _wfinddata_t	find_data;
#else
	DIR			*dir = NULL;
	struct dirent		*d_ent = NULL;
#endif

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() filename:'%s' lastlogsize:" ZBX_FS_UI64 " mtime:%d",
			__function_name, filename, *lastlogsize, *mtime);

	/* splitting filename */
	if (SUCCEED != split_filename(filename, &directory, &format))
	{
		zabbix_log(LOG_LEVEL_WARNING, "filename '%s' does not contain a valid directory and/or format", filename);
		goto out;
	}

#ifdef _WINDOWS
	/* try to "open" Windows directory */
	find_path = zbx_dsprintf(find_path, "%s*", directory);
	find_wpath = zbx_utf8_to_unicode(find_path);
	zbx_free(find_path);

	if (-1 == (find_handle = _wfindfirst(find_wpath, &find_data)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "cannot get entries from '%s' directory: %s", directory, zbx_strerror(errno));
		zbx_free(directory);
		zbx_free(format);
		zbx_free(find_wpath);
		goto out;
	}
	zbx_free(find_wpath);

	zabbix_log(LOG_LEVEL_DEBUG, "we are in the Windows directory reading cycle");
	do
	{
		file_name_utf8 = zbx_unicode_to_utf8(find_data.name);
		logfile_candidate = zbx_dsprintf(logfile_candidate, "%s%s", directory, file_name_utf8);

		if (-1 == zbx_stat(logfile_candidate, &file_buf) || !S_ISREG(file_buf.st_mode))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "cannot process read entry '%s'", logfile_candidate);
		}
		else if (NULL != zbx_regexp_match(file_name_utf8, format, &length))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "adding file '%s' to logfiles", logfile_candidate);
			add_logfile(&logfiles, &logfiles_alloc, &logfiles_num, file_name_utf8, (int)file_buf.st_mtime);
		}
		else
			zabbix_log(LOG_LEVEL_DEBUG, "'%s' does not match '%s'", logfile_candidate, format);

		zbx_free(logfile_candidate);
		zbx_free(file_name_utf8);

	}
	while (0 == _wfindnext(find_handle, &find_data));

#else	/* not _WINDOWS */
	if (NULL == (dir = opendir(directory)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot open directory '%s' for reading: %s", directory, zbx_strerror(errno));
		zbx_free(directory);
		zbx_free(format);
		goto out;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "we are in the *nix directory reading cycle");
	while (NULL != (d_ent = readdir(dir)))
	{
		logfile_candidate = zbx_dsprintf(logfile_candidate, "%s%s", directory, d_ent->d_name);

		if (-1 == zbx_stat(logfile_candidate, &file_buf) || !S_ISREG(file_buf.st_mode))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "cannot process read entry '%s'", logfile_candidate);
		}
		else if (NULL != zbx_regexp_match(d_ent->d_name, format, &length))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "adding file '%s' to logfiles", logfile_candidate);
			add_logfile(&logfiles, &logfiles_alloc, &logfiles_num, d_ent->d_name, (int)file_buf.st_mtime);
		}
		else
			zabbix_log(LOG_LEVEL_DEBUG, "'%s' does not match '%s'", logfile_candidate, format);

		zbx_free(logfile_candidate);
	}
#endif	/*_WINDOWS*/

	if (1 == skip_old_data)
		i = logfiles_num ? logfiles_num - 1 : 0;
	else
		i = 0;

	/* find the oldest file that match */
	for ( ; i < logfiles_num; i++)
	{
		if (logfiles[i].mtime < *mtime)
			continue;	/* not interested in mtimes less than the given mtime */
		else
			break;	/* the first occurrence is found */
	}

	/* escaping those with the same mtime, taking the latest one (without exceptions!) */
	for (j = i + 1; j < logfiles_num; j++)
	{
		if (logfiles[j].mtime == logfiles[i].mtime)
			i = j;	/* moving to the newer one */
		else
			break;	/* all next mtimes are bigger */
	}

	/* if all mtimes are less than the given one, take the latest file from existing ones */
	if (0 < logfiles_num && i == logfiles_num)
		i = logfiles_num - 1;	/* i cannot be bigger than logfiles_num */

	/* processing matched or moving to the newer one and repeating the cycle */
	for (; i < logfiles_num; i++)
	{
		logfile_candidate = zbx_dsprintf(logfile_candidate, "%s%s", directory, logfiles[i].filename);
		if (0 != zbx_stat(logfile_candidate, &file_buf))/* situation could have changed */
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot stat '%s': %s", logfile_candidate, zbx_strerror(errno));
			break;	/* must return, situation could have changed */
		}

		if (1 == skip_old_data)
		{
			*lastlogsize = (zbx_uint64_t)file_buf.st_size;
			zabbix_log(LOG_LEVEL_DEBUG, "skipping existing filename:'%s' lastlogsize:" ZBX_FS_UI64,
					logfile_candidate, *lastlogsize);
		}

		*mtime = (int)file_buf.st_mtime;	/* must contain the latest mtime as possible */

		if (file_buf.st_size < *lastlogsize)
			*lastlogsize = 0;	/* maintain backward compatibility */

		if (-1 == (fd = zbx_open(logfile_candidate, O_RDONLY)))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot open '%s': %s", logfile_candidate, zbx_strerror(errno));
			break;	/* must return, situation could have changed */
		}

#ifdef _WINDOWS
		if (-1L != _lseeki64(fd, (__int64)*lastlogsize, SEEK_SET))
#else
		if ((off_t)-1 != lseek(fd, (off_t)*lastlogsize, SEEK_SET))
#endif
		{
			if (-1 != (nbytes = zbx_read(fd, buffer, sizeof(buffer), encoding)))
			{
				if (0 != nbytes)
				{
					*lastlogsize += nbytes;
					*value = convert_to_utf8(buffer, nbytes, encoding);
					zbx_rtrim(*value, "\r\n ");
					ret = SUCCEED;
					break;	/* return at this point */
				}
				else	/* EOF is reached, but there can be other files to try reading from */
				{
					if (i == logfiles_num - 1)
					{
						ret = SUCCEED;	/* EOF of the the most current file is reached */
						break;
					}
					else
					{
						zbx_free(logfile_candidate);
						*lastlogsize = 0;
						close(fd);
						continue;	/* try to read from more current file */
					}
				}
			}
			else	/* cannot read from the file */
			{
				zabbix_log(LOG_LEVEL_WARNING, "cannot read from '%s': %s", logfile_candidate,
						zbx_strerror(errno));
				break;	/* must return, situation could have changed */
			}
		}
		else	/* cannot position in the file */
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot set position to " ZBX_FS_UI64 " for file '%s': %s",
					*lastlogsize, logfile_candidate, zbx_strerror(errno));
			break;	/* must return, situation could have changed */
		}
	}	/* trying to read from logfiles */

	if (0 == logfiles_num)
		zabbix_log(LOG_LEVEL_WARNING, "there are no files matching '%s' in '%s'", format, directory);

	free_logfiles(&logfiles, &logfiles_alloc, &logfiles_num);
	if (0 < fd && -1 == close(fd))
		zabbix_log(LOG_LEVEL_WARNING, "cannot close file '%s': %s", logfile_candidate, zbx_strerror(errno));

#ifdef _WINDOWS
	if (0 != find_handle && -1 == _findclose(find_handle))
		zabbix_log(LOG_LEVEL_WARNING, "cannot close the find directory handle: %s", zbx_strerror(errno));
#else
	if (NULL != dir && -1 == closedir(dir))
		zabbix_log(LOG_LEVEL_WARNING, "camnot close directory '%s': %s", directory, zbx_strerror(errno));
#endif

	zbx_free(logfile_candidate);
	zbx_free(directory);
	zbx_free(format);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: process_log                                                      *
 *                                                                            *
 * Purpose: Get message from logfile WITHOUT rotation                         *
 *                                                                            *
 * Parameters: filename - logfile name                                        *
 *             lastlogsize - offset for message                               *
 *             value - pointer for logged message                             *
 *                                                                            *
 * Return value: returns SUCCEED on successful reading,                       *
 *               FAIL on other cases                                          *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *    This function allocates memory for 'value', because use zbx_free.       *
 *    Return SUCCEED and NULL value if end of file received.                  *
 *                                                                            *
 ******************************************************************************/
int	process_log(char *filename, zbx_uint64_t *lastlogsize, char **value, const char *encoding,
		unsigned char skip_old_data)
{
	const char	*__function_name = "process_log";

	int		f;
	struct stat	buf;
	int		nbytes, ret = FAIL;
	char		buffer[MAX_BUFFER_LEN];

	assert(NULL != filename);
	assert(NULL != lastlogsize);
	assert(NULL != value);
	assert(NULL != encoding);

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() filename:'%s' lastlogsize:" ZBX_FS_UI64,
			__function_name, filename, *lastlogsize);

	/* handling of file shrinking */
	if (0 != zbx_stat(filename, &buf))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot stat '%s': %s", filename, zbx_strerror(errno));
		return ret;
	}

	if (1 == skip_old_data)
	{
		*lastlogsize = (zbx_uint64_t)buf.st_size;
		zabbix_log(LOG_LEVEL_DEBUG, "skipping existing filename:'%s' lastlogsize:" ZBX_FS_UI64,
				filename, *lastlogsize);
	}

	if (buf.st_size < *lastlogsize)
		*lastlogsize = 0;

	if (-1 == (f = zbx_open(filename, O_RDONLY)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot open '%s': %s", filename, zbx_strerror(errno));
		return ret;
	}

#ifdef _WINDOWS
	if (-1L != _lseeki64(f, (__int64)*lastlogsize, SEEK_SET))
#else
	if ((off_t)-1 != lseek(f, (off_t)*lastlogsize, SEEK_SET))
#endif
	{
		if (-1 != (nbytes = zbx_read(f, buffer, sizeof(buffer), encoding)))
		{
			if (0 != nbytes)
			{
				*lastlogsize += nbytes;
				*value = convert_to_utf8(buffer, nbytes, encoding);
				zbx_rtrim(*value, "\r\n ");
			}
			ret = SUCCEED;
		}
		else
			zabbix_log(LOG_LEVEL_WARNING, "cannot read from '%s': %s", filename, zbx_strerror(errno));
	}
	else
		zabbix_log(LOG_LEVEL_WARNING, "cannot set position to " ZBX_FS_UI64 " for '%s': %s",
				*lastlogsize, filename, zbx_strerror(errno));

	close(f);

	return ret;
}
