/*
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
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
	int	str_length = 0;
	int	part1_length = 0;
	int	part2_length = 0;

	assert(str);
	assert(*str);/* why to split an empty string */
	assert(del);
	assert(*del);/* why to split if "part2" is empty */
	assert(part1);
	assert(NULL == *part1);/* target 1 must be empty */
	assert(part2);
	assert(NULL == *part2);/* target 2 must be empty */

	zabbix_log(LOG_LEVEL_DEBUG, "In split_string(): str [%s] del [%s]", str, del);

	str_length = strlen(str);

	/* since the purpose of this function is to be used in split_filename(), we allow part1 to be */
	/* just *del (e.g., "/" - file system root), but we do not allow part2 (filename) to be empty */
	if (del < str || del >= (str + str_length - 1))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Delimiter is out of range. Cannot proceed.");
		return FAIL;
	}

	part1_length = del - str + 1;
	part2_length = str_length - part1_length;

	*part1 = zbx_malloc(*part1, part1_length + 1);
	zbx_strlcpy(*part1, str, part1_length + 1);

	*part2 = zbx_malloc(*part2, part2_length + 1);
	zbx_strlcpy(*part2, str + part1_length, part2_length + 1);

	zabbix_log(LOG_LEVEL_DEBUG, "End split_string(): part1 [%s] part2 [%s]", *part1, *part2);

	return SUCCEED;
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
#ifdef _WINDOWS
	size_t		sz;
#endif/*_WINDOWS*/

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() filename:'%s'",
			__function_name, filename);

	assert(directory && !*directory);
	assert(format && !*format);

	if (!filename || *filename == '\0')
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Cannot split empty path!");
		return FAIL;
	}

/* special processing for Windows world, since PATH part cannot be simply divided from REGEXP part (file format) */
#ifdef _WINDOWS
	for (sz = strlen(filename) - 1, separator = &filename[sz]; separator >= filename; separator--)
	{
		if (PATH_SEPARATOR != *separator)
			continue;

		zabbix_log(LOG_LEVEL_DEBUG, "%s() %s",
				__function_name, filename);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() %*s",
				__function_name, separator - filename + 1, "^");

		/* separator must be relative delimiter of the original filename */
		if (FAIL == split_string(filename, separator, directory, format))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "Cannot split [%s].", filename);
			return FAIL;
		}

		sz = strlen(*directory);

		/* Windows world verification */
		if (sz + 1 > MAX_PATH)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "Directory path is too long. Cannot proceed.");
			zbx_free(*directory);
			zbx_free(*format);
			return FAIL;
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

		zabbix_log(LOG_LEVEL_DEBUG, "Cannot find [%s] directory.", *directory);
		zbx_free(*directory);
		zbx_free(*format);
	}
	
	if (separator < filename)
		return FAIL;

#else/* _WINDOWS */
	separator = strrchr(filename, (int)PATH_SEPARATOR);
	if (separator == NULL)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Filename [%s] does not contain any path separator [%c].", filename, PATH_SEPARATOR);
		return FAIL;
	}
	if (SUCCEED != split_string(filename, separator, directory, format))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Filename [%s] cannot be sensibly split by [%c].", filename, PATH_SEPARATOR);
		return FAIL;
	}
	/* Checking whether directory exists. */
	if (-1 == zbx_stat(*directory, &buf))
	{
		zabbix_log(LOG_LEVEL_WARNING, "Directory [%s] cannot be found on the file system.", *directory);
		zbx_free(*directory);
		zbx_free(*format);
		return FAIL;
	}
	/* Checking whether directory is really directory, not file. */
	if (!S_ISDIR(buf.st_mode))
	{
		zabbix_log(LOG_LEVEL_WARNING, "Directory [%s] is a file. Cannot proceed.", *directory);
		zbx_free(*directory);
		zbx_free(*format);
		return FAIL;
	}
#endif/* _WINDOWS */

	zabbix_log(LOG_LEVEL_DEBUG, "End %s() directory:'%s' format:'%s'",
			__function_name, *directory, *format);

	return SUCCEED;
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
	int	i;

	zabbix_log(LOG_LEVEL_DEBUG, "In free_logfiles() number of logfiles [%d]", *logfiles_num);

	for (i = 0; i < *logfiles_num; i++)
	{
		zbx_free((*logfiles)[i].filename);
	}
	zbx_free(*logfiles);
	*logfiles_alloc = 0;
	*logfiles_num = 0;
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

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() filename:'%s' mtime:'%d'", __function_name, filename, mtime);

	assert(logfiles);
	assert(logfiles_alloc);
	assert(logfiles_num);
	assert(0 <= *logfiles_num);

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
				zabbix_log(LOG_LEVEL_DEBUG, "End add_logfile(). The file already added.");
				return;
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
int	process_logrt(char *filename, long *lastlogsize, int *mtime, char **value, const char *encoding)
{
	int			i = 0, nbytes, ret = FAIL, logfiles_num = 0, logfiles_alloc = 0, fd = 0, length = 0, j = 0;
	char			buffer[MAX_BUFFER_LEN], *directory = NULL, *format = NULL, *logfile_candidate = NULL;
	struct stat		file_buf;
	struct st_logfile	*logfiles = NULL;
#ifdef _WINDOWS
	char			*find_path = NULL;
	intptr_t		find_handle;
	struct _finddata_t	find_data;
#else
	DIR			*dir = NULL;
	struct dirent		*d_ent = NULL;
#endif

	zabbix_log(LOG_LEVEL_DEBUG, "In process_logrt() filename [%s] lastlogsize [%li] mtime [%d]",
			filename, *lastlogsize, *mtime);

	/* splitting filename */
	if (SUCCEED != split_filename(filename, &directory, &format))
	{
		zabbix_log(LOG_LEVEL_WARNING, "filename [%s] does not contain a valid directory and/or format", filename);
		return FAIL;
	}

#ifdef _WINDOWS
	/* try to "open" Windows directory */
	find_path = zbx_dsprintf(find_path, "%s*", directory);
	find_handle = _findfirst((const char *)find_path, &find_data);
	if (-1 == find_handle)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "cannot get entries from [%s] directory: %s", directory, zbx_strerror(errno));
		zbx_free(directory);
		zbx_free(format);
		zbx_free(find_path);
		return FAIL;
	}
	zbx_free(find_path);

	zabbix_log(LOG_LEVEL_DEBUG, "we are in the Windows directory reading cycle");
	do
	{
		logfile_candidate = zbx_dsprintf(logfile_candidate, "%s%s", directory, find_data.name);

		if (-1 == zbx_stat(logfile_candidate, &file_buf) || !S_ISREG(file_buf.st_mode))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "cannot process read entry [%s]", logfile_candidate);
		}
		else if (NULL != zbx_regexp_match(find_data.name, format, &length))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "adding the file [%s] to logfiles", logfile_candidate);
			add_logfile(&logfiles, &logfiles_alloc, &logfiles_num, find_data.name, (int)file_buf.st_mtime);
		}
		else
			zabbix_log(LOG_LEVEL_DEBUG, "[%s] does not match [%s]", logfile_candidate, format);

		zbx_free(logfile_candidate);

	}
	while (0 == _findnext(find_handle, &find_data));

#else	/* _WINDOWS */
	if (NULL == (dir = opendir(directory)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot open directory [%s] for reading: %s", directory, zbx_strerror(errno));
		zbx_free(directory);
		zbx_free(format);
		return FAIL;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "we are in the *nix directory reading cycle");
	while (NULL != (d_ent = readdir(dir)))
	{
		logfile_candidate = zbx_dsprintf(logfile_candidate, "%s%s", directory, d_ent->d_name);

		if (-1 == zbx_stat(logfile_candidate, &file_buf) || !S_ISREG(file_buf.st_mode))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "cannot process read entry [%s]", logfile_candidate);
		}
		else if (NULL != zbx_regexp_match(d_ent->d_name, format, &length))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "adding the file [%s] to logfiles", logfile_candidate);
			add_logfile(&logfiles, &logfiles_alloc, &logfiles_num, d_ent->d_name, (int)file_buf.st_mtime);
		}
		else
			zabbix_log(LOG_LEVEL_DEBUG, "[%s] does not match [%s]", logfile_candidate, format);

		zbx_free(logfile_candidate);
	}
#endif	/*_WINDOWS*/

	/* find the oldest file that match */
	for (i = 0; i < logfiles_num; i++)
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
	for ( ; i < logfiles_num; i++)
	{
		logfile_candidate = zbx_dsprintf(logfile_candidate, "%s%s", directory, logfiles[i].filename);
		if (0 != zbx_stat(logfile_candidate, &file_buf))/* situation could have changed */
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot stat [%s]: %s", logfile_candidate, zbx_strerror(errno));
			break;	/* must return, situation could have changed */
		}

		*mtime = (int)file_buf.st_mtime;	/* must contain the latest mtime as possible */
		if (file_buf.st_size < *lastlogsize)
		{
			*lastlogsize = 0;	/* maintain backward compatibility */
		}

		if (-1 == (fd = zbx_open(logfile_candidate, O_RDONLY)))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot open [%s]: %s", logfile_candidate, zbx_strerror(errno));
			break;	/* must return, situation could have changed */
		}

		if ((off_t)-1 != lseek(fd, (off_t)*lastlogsize, SEEK_SET))
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
				zabbix_log(LOG_LEVEL_WARNING, "cannot read from [%s]: %s",
						logfile_candidate, zbx_strerror(errno));
				break;	/* must return, situation could have changed */
			}
		}
		else	/* cannot position in the file */
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot set position to [%li] for [%s]: %s",
					*lastlogsize, logfile_candidate, zbx_strerror(errno));
			break;	/* must return, situation could have changed */
		}
	}	/* trying to read from logfiles */

	if (0 == logfiles_num)
	{
		zabbix_log(LOG_LEVEL_WARNING, "there are no files matching [%s] in [%s]",
				format, directory);
	}

	free_logfiles(&logfiles, &logfiles_alloc, &logfiles_num);
	if (0 != fd && -1 == close(fd))
	{
		zabbix_log(LOG_LEVEL_WARNING, "could not close the file [%s]: %s",
					logfile_candidate, zbx_strerror(errno));
	}

#ifdef _WINDOWS
	if (0 != find_handle && -1 == _findclose(find_handle))
	{
		zabbix_log(LOG_LEVEL_WARNING, "could not close the find directory handle: %s",
				zbx_strerror(errno));
	}
#else
	if (NULL != dir && -1 == closedir(dir))
	{
		zabbix_log(LOG_LEVEL_WARNING, "could not close directory [%s]: %s",
				directory, zbx_strerror(errno));
	}
#endif

	zbx_free(logfile_candidate);
	zbx_free(directory);
	zbx_free(format);

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
int	process_log(char *filename, long *lastlogsize, char **value, const char *encoding)
{
	int		f;
	struct stat	buf;
	int		nbytes, ret = FAIL;
	char		buffer[MAX_BUFFER_LEN];

	assert(filename);
	assert(lastlogsize);
	assert(value);
	assert(encoding);

	zabbix_log(LOG_LEVEL_DEBUG, "In process_log() filename:'%s' lastlogsize:%li", filename, *lastlogsize);

	/* handling of file shrinking */
	if (0 != zbx_stat(filename, &buf))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot stat [%s]: %s", filename, zbx_strerror(errno));
		return ret;
	}

	if (2147483647 < buf.st_size)
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot process log [%s]: file size is over limit (2 GB)", filename);
		return ret;
	}

	if (buf.st_size < *lastlogsize)
		*lastlogsize = 0;

	if (-1 == (f = zbx_open(filename, O_RDONLY)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot open [%s]: %s", filename, zbx_strerror(errno));
		return ret;
	}

	if ((off_t)-1 != lseek(f, (off_t)*lastlogsize, SEEK_SET))
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
			zabbix_log(LOG_LEVEL_WARNING, "cannot read from [%s]: %s", filename, zbx_strerror(errno));
	}
	else
		zabbix_log(LOG_LEVEL_WARNING, "cannot set position to [%li] for [%s]: %s", *lastlogsize, filename, zbx_strerror(errno));

	close(f);

	return ret;
}
