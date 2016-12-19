/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
#include "dir.h"
#include "zbxregexp.h"
#include "log.h"

#ifdef _WINDOWS
#	include "disk.h"
#	include "gnuregex.h"
#endif

/******************************************************************************
 *                                                                            *
 * Function: regex_compile                                                    *
 *                                                                            *
 * Purpose: creates compiled regex from pattern, checks for errors in pattern *
 *                                                                            *
 * Parameters: pattern    - [IN] pattern                                      *
 *             expression - [OUT] compiled regex                              *
 *             error      - [OUT] error message if any                        *
 *                                                                            *
 * Return value: On success, 0 value is returned                              *
 *               On error, nonzero value is returned.                         *
 *                                                                            *
 ******************************************************************************/
static int	regex_compile(const char *pattern, regex_t **expression, char **error)
{
	int		reg_error = 0;
	regex_t		*regex = NULL;

	if (NULL == pattern || '\0' == *pattern)
		goto ret;

	regex = (regex_t*)zbx_malloc(regex, sizeof(regex_t));

	if (0 != (reg_error = regcomp(regex, pattern, REG_EXTENDED | REG_NEWLINE | REG_NOSUB)))
	{
		if (NULL != error)
		{
			char	buffer[MAX_STRING_LEN];

			regerror(reg_error, regex, buffer, sizeof(buffer));
			*error = zbx_strdup(*error, buffer);
		}
#ifdef _WINDOWS
		/* the Windows gnuregex implementation does not correctly clean up */
		/* allocated memory after regcomp() failure */
		regfree(regex);
#endif
		zbx_free(regex);
		regex = NULL;
	}

ret:
	if (NULL != expression)
		*expression = regex;
	else
		zbx_free(regex);

	return reg_error;
}


/******************************************************************************
 *                                                                            *
 * Function: filename_matches                                                 *
 *                                                                            *
 * Purpose: checks if filename matches regexp pattern for filenames to be     *
 *          included (regex_incl) and doesn't match regexp pattern for        *
 *          filenames to be excluded (regex_excl)                             *
 *                                                                            *
 * Parameters: name       - [IN] filename to be checked                       *
 *             regex_incl - [IN] regexp for filenames to include (NULL for    *
 *                               none)                                        *
 *             regex_excl - [IN] regexp for filenames to exclude (NULL for    *
 *                               none)                                        *
 *                                                                            *
 * Return value: If filename passes both checks, nonzero value is returned    *
 *               If filename fails to pass, 0 is returned.                    *
 *                                                                            *
 ******************************************************************************/
static int	filename_matches(const char *name, regex_t *regex_incl, regex_t *regex_excl)
{
	return ((regex_incl == NULL || 0 == regexec(regex_incl, name, (size_t)0, NULL, 0)) &&
			(regex_excl == NULL || 0 != regexec(regex_excl, name, (size_t)0, NULL, 0)));
}


/******************************************************************************
 *                                                                            *
 * Function: queue_directory                                                  *
 *                                                                            *
 * Purpose: adds directory to processing queue after checking if current      *
 *          depth is less than max_depth                                      *
 *                                                                            *
 * Parameters: list      - [IN/OUT] vector used to replace reccursion         *
 *                                  with iterative approach		      *
 *	       path      - [IN] directory path		                      *
 *             depth     - [IN] current traversal depth of directory          *
 *             max_depth - [IN] maximal traversal depth allowed (use -1       *
 *                              for unlimited directory traversal)	      *
 *                                                                            *
 ******************************************************************************/
static void	queue_directory(zbx_vector_ptr_t *list, const char *path, int depth, int max_depth)
{
	zbx_directory_item_t	*item;

	if (TRAVERSAL_DEPTH_UNLIMITED == max_depth || depth < max_depth)
	{
		item = (zbx_directory_item_t*)zbx_malloc(NULL, sizeof(zbx_directory_item_t));
		item->depth = depth + 1;
		item->path = zbx_strdup(NULL, path);

		zbx_vector_ptr_append(list, item);
	}
}



/******************************************************************************
 *                                                                            *
 * Different approach is used for Windows implementation as Windows is not    *
 * taking size of a directory record in account when calculating size of      *
 * directory contents.                                                        *
 *                                                                            *
 * Current implementation ignores special file types (symlinks, pipes,        *
 * sockets, etc.).                                                            *
 *                                                                            *
 *****************************************************************************/
static int	vfs_dir_size(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char			*dir, *path = NULL, *mode_str, *max_depth_str, *regex_incl_str, *regex_excl_str;
	char 			*error = NULL;
	int			mode, max_depth;
	zbx_uint64_t		size = 0;
	zbx_vector_ptr_t	list;
	zbx_directory_item_t	*item;
	zbx_stat_t		status;
	int			ret = SYSINFO_RET_FAIL;
	regex_t	 		*regex_incl = NULL, *regex_excl = NULL;

#ifdef _WINDOWS
	char			*name = NULL;
	wchar_t	 		*wpath = NULL;
	intptr_t		handle;
	struct _wfinddata_t	data;
	zbx_uint64_t		file_size, cluster_size, mod;
	DWORD			size_high, size_low;
#else
	DIR 			*directory;
	struct dirent 		*entry;
#endif
	if (5 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		goto err;
	}

	path = get_rparam(request, 0);
	regex_incl_str = get_rparam(request, 1);
	regex_excl_str = get_rparam(request, 2);
	mode_str = get_rparam(request, 3);
	max_depth_str = get_rparam(request, 4);

	zbx_vector_ptr_create(&list);

	if (NULL == path || '\0' == *path)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		goto err;
	}

	if (0 != regex_compile(regex_incl_str, &regex_incl, &error))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot compile a regular expression in second parameter: %s",
				error));
		zbx_free(error);
		goto err;
	}

	if (0 != regex_compile(regex_excl_str, &regex_excl, &error))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot compile a regular expression in third parameter: %s",
				error));
		zbx_free(error);
		goto err;
	}

	if (NULL == mode_str || '\0' == *mode_str || 0 == strcmp(mode_str, "apparent"))	/* <mode> default value */
	{
		mode = SIZE_MODE_APPARENT;
	}
	else if (0 == strcmp(mode_str, "disk"))
	{
		mode = SIZE_MODE_DISK;
	}
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid fourth parameter."));
		goto err;
	}

	if (NULL == max_depth_str || '\0' == *max_depth_str)		/* <max_depth> default value */
	{
		max_depth = TRAVERSAL_DEPTH_UNLIMITED;
	}
	else if (-1 > (max_depth = atoi(max_depth_str)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid fifth parameter."));
		goto err;
	}

	dir = zbx_strdup(NULL, path);
	path = NULL;

	/* remove directory suffix '/' or '\\' (if any) as stat() fails on Windows for directories ending with slash */
	zbx_rtrim(dir, "/\\");

	if (0 != zbx_stat(dir, &status))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain directory information: %s",
				zbx_strerror(errno)));
		zbx_free(dir);
		goto err;
	}

	if (0 == S_ISDIR(status.st_mode))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "First parameter is not a directory."));
		zbx_free(dir);
		goto err;
	}

	item = (zbx_directory_item_t*)zbx_malloc(NULL, sizeof(zbx_directory_item_t));
	item->depth = 0;
	item->path = dir;
	zbx_vector_ptr_append(&list, item);

#ifndef _WINDOWS
	if (SIZE_MODE_APPARENT == mode)
	{
		size += status.st_size;
	}
	else if (SIZE_MODE_DISK == mode)
	{
		size += status.st_blocks * DISK_BLOCK_SIZE;
	}
	else
	{
		THIS_SHOULD_NEVER_HAPPEN;
		exit(EXIT_FAILURE);
	}
#endif /* not _WINDOWS */

	while (0 < list.values_num)
	{
		item = list.values[--list.values_num];

#ifdef _WINDOWS
		name = zbx_dsprintf(NULL, "%s\\*", item->path);
		wpath = zbx_utf8_to_unicode(name);
		zbx_free(name);

		handle = _wfindfirst(wpath, &data);
		zbx_free(wpath);

		if (-1 == handle)
		{
			if (item->depth > 0)
			{
				zabbix_log(LOG_LEVEL_DEBUG, "cannot open directory listing '%s': %s", item->path,
					zbx_strerror(errno));
				goto skip;
			}

			SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain directory listing."));
			list.values_num++;
			goto err;
		}

		if (SIZE_MODE_DISK == mode)
		{
			cluster_size = get_cluster_size(item->path);

			if (0 == cluster_size)
			{
				SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain file system cluster size."));
				list.values_num++;
				goto err;
			}
		}

		do
		{
			if (0 == wcscmp(data.name, L".") || 0 == wcscmp(data.name, L".."))
				continue;

			name = zbx_unicode_to_utf8(data.name);
			path = zbx_dsprintf(path, "%s/%s", item->path, name);

			if (0 == (data.attrib & _A_SUBDIR))
			{
				if (0 != filename_matches(name, regex_incl, regex_excl))
				{
					wpath = zbx_utf8_to_unicode(path);
					/* GetCompressedFileSize gives more accurate result than zbx_stat for */
					/* compressed files */
					size_low = GetCompressedFileSize(wpath, &size_high);

					if (size_low != INVALID_FILE_SIZE || NO_ERROR == GetLastError())
					{
						file_size = ((zbx_uint64_t)size_high << 32) | size_low;

						if (SIZE_MODE_DISK == mode)
						{
							if (0 != (mod = size % cluster_size))
								file_size += cluster_size - mod;
						}

						size += file_size;
					}
					zbx_free(wpath);
				}
			}
			else
				queue_directory(&list, path, item->depth, max_depth);

			zbx_free(name);

		} while (0 == _wfindnext(handle, &data));

		if (-1 == _findclose(handle))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "cannot close directory listing '%s': %s", item->path,
				zbx_strerror(errno));
		}
#else /* not _WINDOWS */
		if (NULL == (directory = opendir(item->path)))
		{
			if (item->depth > 0)
			{
				zabbix_log(LOG_LEVEL_DEBUG, "cannot open directory listing '%s': %s", item->path,
					zbx_strerror(errno));
				goto skip;
			}

			SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain directory listing."));
			list.values_num++;
			goto err;
		}

		while (NULL != (entry = readdir(directory)))
		{
			if (0 == strcmp(entry->d_name, ".") || 0 == strcmp(entry->d_name, ".."))
				continue;

			path = zbx_dsprintf(path, "%s/%s", item->path, entry->d_name);

			if (0 == zbx_stat(path, &status))
			{
				if ((0 != S_ISREG(status.st_mode) || 0 != S_ISDIR(status.st_mode)) &&
						0 != filename_matches(entry->d_name, regex_incl, regex_excl))
				{
					if (SIZE_MODE_APPARENT == mode)
					{
						size += status.st_size;
					}
					else if (SIZE_MODE_DISK == mode)
					{
						size += status.st_blocks * DISK_BLOCK_SIZE;
					}
					else
					{
						THIS_SHOULD_NEVER_HAPPEN;
						exit(EXIT_FAILURE);
					}
				}

				if (0 != S_ISDIR(status.st_mode))
					queue_directory(&list, path, item->depth, max_depth);
			}
			else
				zabbix_log(LOG_LEVEL_DEBUG, "cannot process directory entry '%s': %s", path,
						zbx_strerror(errno));
		}

		closedir(directory);
#endif /* _WINDOWS */
	skip:
		zbx_free(item->path);
		zbx_free(item);
	}

	SET_UI64_RESULT(result, size);
	ret = SYSINFO_RET_OK;
err:
	zbx_free(path);

	if (NULL != regex_incl)
	{
		regfree(regex_incl);
		zbx_free(regex_incl);
	}

	if (NULL != regex_excl)
	{
		regfree(regex_excl);
		zbx_free(regex_excl);
	}

	while (0 < list.values_num)
	{
		item = list.values[--list.values_num];
		zbx_free(item->path);
		zbx_free(item);
	}
	zbx_vector_ptr_destroy(&list);

	return ret;
}

int	VFS_DIR_SIZE(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	return zbx_execute_threaded_metric(vfs_dir_size, request, result);
}
