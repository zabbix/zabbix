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
#endif /* _WINDOWS */

#define MAX_LEN_MD5	512	/* maximum size of the initial part of the file to calculate md5 sum for */

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

#else/* _WINDOWS */
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
#endif/* _WINDOWS */

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s directory:'%s' format:'%s'", __function_name, zbx_result_string(ret),
			*directory, *format);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: file_part_md5sum                                                 *
 *                                                                            *
 * Purpose: calculate md5sum of specified part of the file                    *
 *                                                                            *
 * Parameters:                                                                *
 *     filename - [IN] full pathname                                          *
 *     offset   - [IN] offset from the beginning of the file                  *
 *     length   - [IN] length of the part in bytes. Maximum is 512 bytes.     *
 *     md5buf   - [OUT] output buffer, 16-bytes long, where the calculated    *
 *                md5 sum is placed                                           *
 *                                                                            *
 * Return value: SUCCEED or FAIL                                              *
 *                                                                            *
 ******************************************************************************/
static int	file_part_md5sum(const char *filename, zbx_uint64_t offset, int length, md5_byte_t *md5buf)
{
	int		ret = FAIL, f;
	md5_state_t	state;
	char		buf[MAX_LEN_MD5];

	if (MAX_LEN_MD5 < length)
		return ret;

	if (-1 == (f = zbx_open(filename, O_RDONLY)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot open '%s': %s", filename, zbx_strerror(errno));
		return ret;
	}

	if (0 < offset && (zbx_offset_t)-1 == zbx_lseek(f, offset, SEEK_SET))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot set position to " ZBX_FS_UI64 " for file \"%s\": %s",
				offset, filename, zbx_strerror(errno));
		return ret;
	}

	if (length != (int)read(f, buf, length))
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
 *             size  - logfile size in bytes                                  *
 *                                                                            *
 * Return value: none                                                         *
 *                                                                            *
 * Author: Dmitry Borovikov                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void add_logfile(struct st_logfile **logfiles, int *logfiles_alloc, int *logfiles_num, const char *filename,
		int mtime, zbx_uint64_t size)
{
	const char	*__function_name = "add_logfile";
	int		i = 0, cmp = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() filename:'%s' mtime:%d size:" ZBX_FS_UI64, __function_name, filename,
			mtime, size);

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

	(*logfiles)[i].filename = zbx_strdup(NULL, filename);
	(*logfiles)[i].mtime = mtime;
	(*logfiles)[i].size = size;
	(*logfiles)[i].processed_size = 0;
	(*logfiles)[i].md5size = (zbx_uint64_t)MAX_LEN_MD5 > size ? (int)size : MAX_LEN_MD5;

	if (SUCCEED != file_part_md5sum(filename, (zbx_uint64_t)0,  (*logfiles)[i].md5size, (*logfiles)[i].md5buf))
		(*logfiles)[i].md5size = -1;

	++(*logfiles_num);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: process_logrt                                                    *
 *                                                                            *
 * Purpose: Find new records in logfiles with rotation                        *
 *                                                                            *
 * Parameters:                                                                *
 *     filename         - [IN] logfile name (regular expression with a path)  *
 *     lastlogsize      - [IN/OUT] offset from the beginning of the file      *
 *     mtime            - [IN/OUT] last modification time of the file         *
 *     skip_old_data    - [IN/OUT] start from the beginning of the file or    *
 *                        jump to the end                                     *
 *     big_rec          - [IN/OUT] state variable to remember whether a long  *
 *                        record is being processed                           *
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
 * Author: Dmitry Borovikov (logrotation)                                     *
 *                                                                            *
 * Comments:                                                                  *
 *    This function allocates memory for 'value', because use zbx_free.       *
 *    Return SUCCEED and NULL value if end of file received.                  *
 *                                                                            *
 ******************************************************************************/
int	process_logrt(char *filename, zbx_uint64_t *lastlogsize, int *mtime, unsigned char *skip_old_data,
		int *big_rec, struct st_logfile **logfiles_old, int *logfiles_num_old, const char *encoding,
		ZBX_REGEXP *regexps, int regexps_num, const char *pattern, int *p_count, int *s_count,
		zbx_process_value_func_t process_value, const char *server, unsigned short port, const char *hostname,
		const char *key)
{
	const char		*__function_name = "process_logrt";
	int			i = 0, ret = FAIL, logfiles_num = 0, logfiles_alloc = 0, j = 0, reg_error;
	char			err_buf[MAX_STRING_LEN], *directory = NULL, *format = NULL, *logfile_candidate = NULL;
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
	regex_t			re;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() filename:'%s' lastlogsize:" ZBX_FS_UI64 " mtime:%d",
			__function_name, filename, *lastlogsize, *mtime);

	/* splitting filename */
	if (SUCCEED != split_filename(filename, &directory, &format))
	{
		zabbix_log(LOG_LEVEL_WARNING, "filename '%s' does not contain a valid directory and/or format",
				filename);
		goto out;
	}

	if (0 != (reg_error = regcomp(&re, format, REG_EXTENDED | REG_NEWLINE | REG_NOSUB)))
	{
		regerror(reg_error, &re, err_buf, sizeof(err_buf));
		zabbix_log(LOG_LEVEL_WARNING, "Cannot compile a regexp describing filename pattern '%s' for a logrt[] "
				"item. Error: %s", format, err_buf);
		goto out;
	}

#ifdef _WINDOWS
	/* try to "open" Windows directory */
	find_path = zbx_dsprintf(find_path, "%s*", directory);
	find_handle = _findfirst((const char *)find_path, &find_data);
	if (-1 == find_handle)
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot open directory \"%s\" for reading: %s", directory,
				zbx_strerror(errno));
		regfree(&re);
		zbx_free(directory);
		zbx_free(format);
		zbx_free(find_path);
		goto out;
	}

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
						(int)file_buf.st_mtime, (zbx_uint64_t)file_buf.st_size);
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

#else	/* _WINDOWS */
	if (NULL == (dir = opendir(directory)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot open directory '%s' for reading: %s", directory, zbx_strerror(errno));
		regfree(&re);
		zbx_free(directory);
		zbx_free(format);
		goto out;
	}

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
						(int)file_buf.st_mtime, (zbx_uint64_t)file_buf.st_size);
			}
		}
		else
			zabbix_log(LOG_LEVEL_DEBUG, "cannot process entry '%s'", logfile_candidate);

		zbx_free(logfile_candidate);
	}

	if (-1 == closedir(dir))
		zabbix_log(LOG_LEVEL_WARNING, "cannot close directory '%s': %s", directory, zbx_strerror(errno));

#endif	/*_WINDOWS*/

	regfree(&re);

	i = (1 == *skip_old_data && 0 < logfiles_num) ? logfiles_num - 1 : 0;

	/* escaping those with the same mtime, taking the latest one (without exceptions!) */
	for (j = i + 1; j < logfiles_num; j++)
	{
		if (logfiles[j].mtime == logfiles[i].mtime)
			i = j;	/* moving to the newer one */
		else
			break;	/* all next mtimes are bigger */
	}

	/* processing matched logfiles starting from the older one to the newer one */
	for (; i < logfiles_num; i++)
	{
		if (SUCCEED != (ret = process_log(logfiles[i].filename, lastlogsize, mtime, skip_old_data, big_rec,
				encoding, regexps, regexps_num, pattern, p_count, s_count, process_value, server, port,
				hostname, key)))
		{
			/* Do not make a logrt[] item NOTSUPPORTED if one of selected files is not accessible */
			/* (can happen during a rotation). Maybe during the next check all will be well. */
			ret = SUCCEED;
			break;
		}

		if (i != logfiles_num - 1)
			*lastlogsize = 0;
	}

	if (0 == logfiles_num)
	{
		zabbix_log(LOG_LEVEL_WARNING, "there are no files matching '%s' in '%s'", format, directory);

		/* do not make a logrt[] item NOTSUPPORTED if there are no matching files in the directory */
		ret = SUCCEED;
	}

	zbx_free(directory);
	zbx_free(format);

	/* remember the composed list of log files for using in the next check */
	if (NULL != *logfiles_old)
	{
		for (j = 0; j < *logfiles_num_old; j++)
			zbx_free((*logfiles_old)[j].filename);

		zbx_free(*logfiles_old);
	}
	*logfiles_old = logfiles;
	*logfiles_num_old = logfiles_num;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

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

	if (NULL != mtime)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "In %s() filename:'%s' lastlogsize:" ZBX_FS_UI64 " mtime: %d",
				__function_name, filename, *lastlogsize, *mtime);
	}
	else
	{
		zabbix_log(LOG_LEVEL_DEBUG, "In %s() filename:'%s' lastlogsize:" ZBX_FS_UI64 " mtime: NULL",
				__function_name, filename, *lastlogsize);
	}

	if (0 != zbx_stat(filename, &buf))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot stat '%s': %s", filename, zbx_strerror(errno));
		zabbix_log(LOG_LEVEL_DEBUG, "End of %s() filename:'%s' lastlogsize:" ZBX_FS_UI64 " ret:%s",
			__function_name, filename, *lastlogsize, zbx_result_string(ret));
		return ret;
	}

	if ((zbx_uint64_t)buf.st_size == *lastlogsize)
	{
		/* The file size has not changed. Nothing to do. Here we do not deal with a case of changing */
		/* a logfile's content while keeping the same length. */
		zabbix_log(LOG_LEVEL_DEBUG, "End of %s() filename:'%s' lastlogsize:" ZBX_FS_UI64 " (not changed) "
			"ret:SUCCEED", __function_name, filename, *lastlogsize);
		return SUCCEED;
	}

	if (-1 == (f = zbx_open(filename, O_RDONLY)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot open '%s': %s", filename, zbx_strerror(errno));
		zabbix_log(LOG_LEVEL_DEBUG, "End of %s() filename:'%s' lastlogsize:" ZBX_FS_UI64 " ret:%s",
			__function_name, filename, *lastlogsize, zbx_result_string(ret));
		return ret;
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

	if (NULL != mtime)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "End of %s() filename:'%s' lastlogsize:" ZBX_FS_UI64 " mtime: %d ret:%s",
				__function_name, filename, *lastlogsize, *mtime, zbx_result_string(ret));
	}
	else
	{
		zabbix_log(LOG_LEVEL_DEBUG, "End of %s() filename:'%s' lastlogsize:" ZBX_FS_UI64 " mtime: NULL ret:%s",
				__function_name, filename, *lastlogsize, zbx_result_string(ret));
	}

	return ret;
}
