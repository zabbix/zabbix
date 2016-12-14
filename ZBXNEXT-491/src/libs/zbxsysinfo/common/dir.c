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
 *									      *
 * Function: get_file_size						      *
 *									      *
 * Purpose: gets file or directory size				              *
 *									      *
 * Parameters: path       - [IN] absolute file or directory path	      *
 *	       mode       - [IN] SIZE_MODE_APPARENT for apparent file size    *
 *			         SIZE_MODE_DISK for disk space used	      *
 *									      *
 * Return value: On success, nonzero file size returned		              *
 *	         On error, 0 is returned.	                              *
 *									      *
 * Comments: Different approach is used for Windows implementation as	      *
 *	   Windows is not taking size of a directory record in account        *
 *	   when calculating size of directory contents. Implementation for    *
 *	   Windows ignores mode as it is handled directly in VFS_DIR_SIZE.    *
 *	   Current implementation ignores special file types (symlinks,       *
 *	   pipes, sockets, etc.).					      *
 *									      *
 ******************************************************************************/
zbx_uint64_t	get_file_size(const char *path, int mode)
{
	zbx_uint64_t	size = 0;

#ifdef _WINDOWS
	unsigned long	size_high, size_low;
	wchar_t		*wpath;

	wpath = zbx_utf8_to_unicode(path);
	/* GetCompressedFileSize gives more accurate result than zbx_stat for compressed files */
	size_low = GetCompressedFileSize(wpath, &size_high);
	size = ((zbx_uint64_t)size_high << 32) | size_low;
	zbx_free(wpath);
#else
	zbx_stat_t status;

	if (0 == zbx_stat(path, &status))
	{
		if (S_ISREG(status.st_mode) || S_ISDIR(status.st_mode))
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

	}
	else
		zabbix_log(LOG_LEVEL_DEBUG, "cannot process directory entry '%s': %s", path, zbx_strerror(errno));
#endif

	return size;
}

/******************************************************************************
 *									      *
 * Function: process_directory_entry					      *
 *									      *
 * Purpose: processes directory entry (file or a directory), checks depth and *
 *	  regexp, adds subdirectories to a vector so they can be processed    *
 *	  later							              *
 *									      *
 * Parameters: list	    - [IN/OUT] vector used to replace reccursion      *
 *				       with iterative approach		      *
 *	       parent       - [IN] parent directory			      *
 *	       name	    - [IN] file or direcotry name		      *
 *	       mode	    - [IN] mode used for size calculations (see       *
 *				   get_file_size)			      *
 *	       max_depth    - [IN] maximal traversal depth allowed (use -1    *
 *				 for unlimited directory traversal)	      *
 *	       is_directory - [IN] 0/1 is directory			      *
 *	       regex_incl   - [IN] file name pattern for inclusion	      *
 *	       regex_excl   - [IN] file name pattern for exclusion	      *
 *									      *
 * Return value: directory entry (file or directory) size or 0 for ignored    *
 *	         enties			                                      *
 *									      *
 ******************************************************************************/
zbx_uint64_t	process_directory_entry(zbx_vector_ptr_t *list, zbx_directory_item_t *parent, const char *name,
					int mode, int max_depth, int is_directory, regex_t *regex_incl,
					regex_t *regex_excl)
{
	zbx_uint64_t		size = 0;
	char			*path = NULL;
	zbx_directory_item_t	*item;

	/* "du" adds size of a directory to the total size */
	if (0 == strcmp(name, "."))
	{
		/* directory record size is added only for empty regex_incl expression */
		if (NULL == regex_incl)
		{
			size += get_file_size(parent->path, mode);
		}
		goto ret;
	}

	if (0 == strcmp(name, ".."))
	{
		goto ret;
	}

	path = zbx_dsprintf(path, "%s/%s", parent->path, name);

	if (is_directory)
	{
		if (TRAVERSAL_DEPTH_UNLIMITED == max_depth || parent->depth < max_depth)
		{
			item = (zbx_directory_item_t*)zbx_malloc(NULL, sizeof(zbx_directory_item_t));
			item->depth = parent->depth + 1;
			item->path = path;

			zbx_vector_ptr_append(list, item);
			return 0;
		}
		else
		{
			goto ret;
		}
	}

	if ((regex_incl == NULL || 0 == regexec(regex_incl, name, (size_t)0, NULL, 0)) &&
		(regex_excl == NULL || 0 != regexec(regex_excl, name, (size_t)0, NULL, 0)))
	{
		size += get_file_size(path, mode);
	}

ret:
	zbx_free(path);
	return size;
}


/******************************************************************************
 *									      *
 * Function: regex_compile						      *
 *									      *
 * Purpose: creates compiled regex from pattern, checks for errors in pattern *
 *									      *
 * Parameters: pattern    - [IN] pattern				      *
 *	       expression - [OUT] compiled regex			      *
 *	       error      - [OUT] error message if any			      *
 *									      *
 * Return value: On success, nonzero value is returned			      *
 *	       On error, 0 is returned.				              *
 *									      *
 ******************************************************************************/
static int	regex_compile(const char *pattern, regex_t **expression, char **error)
{
	int		reg_error = 0;
	regex_t		*regex = NULL;

	if (NULL == pattern || '\0' == *pattern)
	{
		goto ret;
	}

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
	{
		*expression = regex;
	}
	else
	{
		zbx_free(regex);
	}

	return (reg_error == 0);
}


static int	vfs_dir_size(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char			*dir, *mode_str, *max_depth_str, *regex_incl_str, *regex_excl_str, *error = NULL;
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
#else
	DIR 			*directory;
	struct dirent 		*entry;
#endif
	if (5 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		goto err;
	}

	dir = get_rparam(request, 0);
	regex_incl_str = get_rparam(request, 1);
	regex_excl_str = get_rparam(request, 2);
	mode_str = get_rparam(request, 3);
	max_depth_str = get_rparam(request, 4);

	zbx_vector_ptr_create(&list);

	if (NULL == dir || '\0' == *dir)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		goto err;
	}

	if (0 == regex_compile(regex_incl_str, &regex_incl, &error))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot compile a regular expression in \"regex_incl\""
				" parameter: %s", error));
		zbx_free(error);
		goto err;
	}

	if (0 == regex_compile(regex_excl_str, &regex_excl, &error))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot compile a regular expression in \"regex_excl\""
				" parameter: %s", error));
		zbx_free(error);
		goto err;
	}

	if (NULL == mode_str || '\0' == *mode_str || 0 == strcmp(mode_str, "apparent"))	/* default parameter */
	{
		mode = SIZE_MODE_APPARENT;
	}
	else if (0 == strcmp(mode_str, "disk"))
	{
		mode = SIZE_MODE_DISK;
	}
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid third parameter."));
		goto err;
	}

	if (NULL == max_depth_str || '\0' == *max_depth_str)	/* default parameter */
	{
		max_depth = TRAVERSAL_DEPTH_UNLIMITED;
	}
	else
	{
		max_depth = atoi(max_depth_str);
		if (-1 > max_depth)
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid fourth parameter."));
			goto err;
		}
	}

	dir = zbx_strdup(NULL, dir);
	/* removes directory suffix '/' or '\\' (if any) as stat fails on Windows for directories that end with slash */
	zbx_rtrim(dir, "/\\");
	if (0 != zbx_stat(dir, &status) || 0 == S_ISDIR(status.st_mode))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain directory information: %s",
			zbx_strerror(errno)));
		zbx_free(dir);
		goto err;
	}

	item = (zbx_directory_item_t*)zbx_malloc(NULL, sizeof(zbx_directory_item_t));
	item->depth = 0;
	item->path = dir;
	zbx_vector_ptr_append(&list, item);

	while (0 < list.values_num)
	{
		item = list.values[--list.values_num];

#ifdef _WINDOWS
		name = zbx_dsprintf(NULL, "%s\\*", item->path);
		wpath = zbx_utf8_to_unicode(name);
		zbx_free(name);

		if (-1 == (handle = _wfindfirst(wpath, &data)))
		{
			zbx_free(wpath);

			if (item->depth > 0)
			{
				zabbix_log(LOG_LEVEL_DEBUG, "cannot open directory listing '%s': %s", item->path,
					zbx_strerror(errno));
				goto skip;
			}
			else
			{
				SET_MSG_RESULT(result, zbx_strdup(NULL, "Directory listing is unavailable."));
				list.values_num++;
				goto err;
			}
		}

		if (SIZE_MODE_DISK == mode)
		{
			cluster_size = get_cluster_size(item->path);
			if (0 == cluster_size)
			{
				SET_MSG_RESULT(result, zbx_strdup(NULL, "File system cluster size is unavailable."));
				list.values_num++;
				goto err;
			}
		}

		do
		{
			name = zbx_unicode_to_utf8(data.name);
			file_size = process_directory_entry(&list, item, name, mode, max_depth,
				(data.attrib & _A_SUBDIR), regex_incl, regex_excl);
			zbx_free(name);

			if (SIZE_MODE_DISK == mode)
			{
				mod = size % cluster_size;
				if (0 != mod)
				{
					size += cluster_size - mod;
				}
			}

			size += file_size;

		} while (0 == _wfindnext(handle, &data));

		if (-1 == _findclose(handle))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "cannot close directory listing '%s': %s", item->path,
				zbx_strerror(errno));
		}

		zbx_free(wpath);
#else /* not _WINDOWS */
		directory = opendir(item->path);
		if (NULL == directory)
		{
			if (item->depth > 0)
			{
				zabbix_log(LOG_LEVEL_DEBUG, "cannot open directory listing '%s': %s", item->path,
					zbx_strerror(errno));
				goto skip;
			}
			else
			{
				SET_MSG_RESULT(result, zbx_strdup(NULL, "Directory listing is unavailable."));
				list.values_num++;
				goto err;
			}
		}

		while (NULL != (entry = readdir(directory)))
		{
			size += process_directory_entry(&list, item, entry->d_name, mode, max_depth,
							(DT_DIR == entry->d_type), regex_incl, regex_excl);
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

int		VFS_DIR_SIZE(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	return zbx_execute_threaded_metric(vfs_dir_size, request, result);
}
