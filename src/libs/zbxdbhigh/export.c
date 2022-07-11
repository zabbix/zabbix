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

#include "export.h"

#include "log.h"

#define ZBX_OPTION_EXPTYPE_EVENTS	"events"
#define ZBX_OPTION_EXPTYPE_HISTORY	"history"
#define ZBX_OPTION_EXPTYPE_TRENDS	"trends"

extern char		*CONFIG_EXPORT_DIR;
extern char		*CONFIG_EXPORT_TYPE;
extern zbx_uint64_t	CONFIG_EXPORT_FILE_SIZE;

typedef struct
{
	char	*name;
	FILE	*file;
	int	missing;
}
zbx_export_file_t;

static zbx_export_file_t	*history_file;
static zbx_export_file_t	*trends_file;
static zbx_export_file_t	*problems_file;

static char	*export_dir;

/******************************************************************************
 *                                                                            *
 * Purpose: validate export type                                              *
 *                                                                            *
 * Parameters:  export_type - [in] list of export types                       *
 *              export_mask - [out] export types mask (if SUCCEED)            *
 *                                                                            *
 * Return value: SUCCEED - valid configuration                                *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_validate_export_type(char *export_type, uint32_t *export_mask)
{
	int		ret = SUCCEED;
	char		*start = export_type;
	uint32_t	mask;
	char		*types[] = {
				ZBX_OPTION_EXPTYPE_EVENTS,
				ZBX_OPTION_EXPTYPE_HISTORY,
				ZBX_OPTION_EXPTYPE_TRENDS,
				NULL};
	size_t		lengths[] = {
				ZBX_CONST_STRLEN(ZBX_OPTION_EXPTYPE_EVENTS),
				ZBX_CONST_STRLEN(ZBX_OPTION_EXPTYPE_HISTORY),
				ZBX_CONST_STRLEN(ZBX_OPTION_EXPTYPE_TRENDS),
				0};

	if (NULL != start)
	{
		mask = 0;

		do
		{
			int	i;
			char	*end;

			end = strchr(start, ',');

			for (i = 0; NULL != types[i]; i++)
			{
				if ((NULL != end && lengths[i] == (size_t)(end - start) &&
						0 == strncmp(start, types[i], lengths[i])) ||
						(NULL == end && 0 == strcmp(start, types[i])))
				{
					mask |= (uint32_t)(1 << i);
					break;
				}
			}

			if (NULL == types[i])
			{
				ret = FAIL;
				break;
			}

			start = end;
		} while (NULL != start++);
	}
	else
		mask = ZBX_FLAG_EXPTYPE_EVENTS | ZBX_FLAG_EXPTYPE_HISTORY | ZBX_FLAG_EXPTYPE_TRENDS;

	if (NULL != export_mask)
		*export_mask = mask;

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: checks if export is enabled for given type(s)                     *
 *                                                                            *
 * Parameters: flag - ZBX_FLAG_EXPTYPE_EVENTS events are enabled              *
 *                    ZBX_FLAG_EXPTYPE_HISTORY history is enabled             *
 *                    ZBX_FLAG_EXPTYPE_TRENDS trends are enabled              *
 *                                                                            *
 * Return value: SUCCEED - export enabled                                     *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_is_export_enabled(uint32_t flags)
{
	int			ret = FAIL;
	static uint32_t		export_types;

	if (NULL == CONFIG_EXPORT_DIR)
		return ret;

	if (NULL != CONFIG_EXPORT_TYPE)
	{
		if (0 == export_types)
			zbx_validate_export_type(CONFIG_EXPORT_TYPE, &export_types);

		if (0 != (export_types & flags))
			ret = SUCCEED;
	}
	else
		ret = SUCCEED;

	return ret;
}

int	zbx_export_init(char **error)
{
	struct stat	fs;

	if (FAIL == zbx_is_export_enabled(ZBX_FLAG_EXPTYPE_EVENTS | ZBX_FLAG_EXPTYPE_TRENDS | ZBX_FLAG_EXPTYPE_HISTORY))
	{
		if (NULL != CONFIG_EXPORT_TYPE)
		{
			*error = zbx_dsprintf(*error, "Misconfiguration: \"ExportType\" is set to '%s' "
					"while \"ExportDir\" is unset.", CONFIG_EXPORT_TYPE);
			return FAIL;
		}
		return SUCCEED;
	}

	if (NULL == CONFIG_EXPORT_TYPE)
	{
		CONFIG_EXPORT_TYPE = zbx_dsprintf(CONFIG_EXPORT_TYPE, "%s,%s,%s", ZBX_OPTION_EXPTYPE_EVENTS,
				ZBX_OPTION_EXPTYPE_HISTORY, ZBX_OPTION_EXPTYPE_TRENDS);
	}

	if (0 != stat(CONFIG_EXPORT_DIR, &fs))
	{
		*error = zbx_dsprintf(*error, "Failed to stat the specified path \"%s\": %s.", CONFIG_EXPORT_DIR,
				zbx_strerror(errno));
		return FAIL;
	}

	if (0 == S_ISDIR(fs.st_mode))
	{
		*error = zbx_dsprintf(*error, "The specified path \"%s\" is not a directory.", CONFIG_EXPORT_DIR);
		return FAIL;
	}

	if (0 != access(CONFIG_EXPORT_DIR, W_OK | R_OK))
	{
		*error = zbx_dsprintf(*error, "Cannot access path \"%s\": %s.", CONFIG_EXPORT_DIR, zbx_strerror(errno));
		return FAIL;
	}

	export_dir = zbx_strdup(NULL, CONFIG_EXPORT_DIR);

	if ('/' == export_dir[strlen(export_dir) - 1])
		export_dir[strlen(export_dir) - 1] = '\0';

	return SUCCEED;
}

static int	open_export_file(zbx_export_file_t *file, char **error)
{
	if (NULL == (file->file = fopen(file->name, "a")))
	{
		*error = zbx_dsprintf(*error, "cannot open export file '%s': %s", file->name, zbx_strerror(errno));
		return FAIL;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "successfully created export file '%s'", file->name);

	return SUCCEED;
}

static zbx_export_file_t	*export_init(zbx_export_file_t *file, const char *process_type, const char
				*process_name,	int process_num)
{
	char	*error = NULL;

	file = (zbx_export_file_t *)zbx_malloc(NULL, sizeof(zbx_export_file_t));
	file->name = zbx_dsprintf(NULL, "%s/%s-%s-%d.ndjson", export_dir, process_type, process_name, process_num);

	if (FAIL == open_export_file(file, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "%s", error);
		exit(EXIT_FAILURE);
	}

	file->missing = 0;

	return file;
}

void	zbx_history_export_init(const char *process_name, int process_num)
{
	history_file = export_init(history_file, "history", process_name, process_num);
}

void	zbx_trends_export_init(const char *process_name, int process_num)
{
	trends_file = export_init(trends_file, "trends", process_name, process_num);
}

void	zbx_problems_export_init(const char *process_name, int process_num)
{
	problems_file = export_init(problems_file, "problems", process_name, process_num);
}

static void	file_write(const char *buf, size_t count, zbx_export_file_t *file)
{
#define ZBX_LOGGING_SUSPEND_TIME	10

	static time_t	last_log_time = 0;
	time_t		now;
	char		*error_msg = NULL;
	long		file_offset;

	if (0 == file->missing && 0 != access(file->name, F_OK))
	{
		if (NULL != file->file && 0 != fclose(file->file))
			zabbix_log(LOG_LEVEL_DEBUG, "cannot close export file '%s': %s",file->name, zbx_strerror(errno));

		file->file = NULL;
	}

	if (NULL == file->file && FAIL == open_export_file(file, &error_msg))
	{
		file->missing = 1;
		goto error;
	}

	if (1 == file->missing)
	{
		file->missing = 0;
		zabbix_log(LOG_LEVEL_ERR, "regained access to export file '%s'", file->name);
	}

	if (-1 == (file_offset = ftell(file->file)))
	{
		error_msg = zbx_dsprintf(error_msg, "cannot get current position in export file '%s': %s",
				file->name, zbx_strerror(errno));
		goto error;
	}

	if (CONFIG_EXPORT_FILE_SIZE <= count + (size_t)file_offset + 1)
	{
		char	filename_old[MAX_STRING_LEN];

		strscpy(filename_old, file->name);
		zbx_strlcat(filename_old, ".old", MAX_STRING_LEN);

		if (0 == access(filename_old, F_OK) && 0 != remove(filename_old))
		{
			error_msg = zbx_dsprintf(error_msg, "cannot remove export file '%s': %s",
					filename_old, zbx_strerror(errno));
			goto error;
		}

		if (0 != fclose(file->file))
		{
			error_msg = zbx_dsprintf(error_msg, "cannot close export file %s': %s",
					file->name, zbx_strerror(errno));
			file->file = NULL;
			goto error;
		}
		file->file = NULL;

		if (0 != rename(file->name, filename_old))
		{
			error_msg = zbx_dsprintf(error_msg, "cannot rename export file '%s': %s",
					file->name, zbx_strerror(errno));
			goto error;
		}

		if (FAIL == open_export_file(file, &error_msg))
			goto error;
	}

	if (count != fwrite(buf, 1, count, file->file) || '\n' != fputc('\n', file->file))
	{
		error_msg = zbx_dsprintf(error_msg, "cannot write to export file '%s': %s", file->name,
				zbx_strerror(errno));
		goto error;
	}

	return;
error:
	if (NULL != file->file && 0 != fclose(file->file))
	{
		error_msg = zbx_dsprintf(error_msg, "%s; cannot close export file %s': %s",
				error_msg, file->name, zbx_strerror(errno));
	}

	file->file = NULL;
	now = time(NULL);

	if (ZBX_LOGGING_SUSPEND_TIME < now - last_log_time)
	{
		zabbix_log(LOG_LEVEL_ERR, "%s", error_msg);
		last_log_time = now;
	}

	zbx_free(error_msg);

#undef ZBX_LOGGING_SUSPEND_TIME
}

void	zbx_problems_export_write(const char *buf, size_t count)
{
	file_write(buf, count, problems_file);
}

void	zbx_history_export_write(const char *buf, size_t count)
{
	file_write(buf, count, history_file);
}

void	zbx_trends_export_write(const char *buf, size_t count)
{
	file_write(buf, count, trends_file);
}

static void	zbx_flush(FILE *file, const char *file_name)
{
	if (0 != fflush(file))
		zabbix_log(LOG_LEVEL_ERR, "cannot flush export file '%s': %s", file_name, zbx_strerror(errno));
}

void	zbx_problems_export_flush(void)
{
	if (NULL != problems_file->file)
		zbx_flush(problems_file->file, problems_file->name);
}

void	zbx_history_export_flush(void)
{
	if (NULL != history_file->file)
		zbx_flush(history_file->file, history_file->name);
}

void	zbx_trends_export_flush(void)
{
	if (NULL != trends_file->file)
		zbx_flush(trends_file->file, trends_file->name);
}
