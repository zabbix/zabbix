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

#include "zbxexport.h"

#include "zbxcommon.h"
#include "zbxstr.h"
#include "zbxtypes.h"

#define ZBX_OPTION_EXPTYPE_EVENTS	"events"
#define ZBX_OPTION_EXPTYPE_HISTORY	"history"
#define ZBX_OPTION_EXPTYPE_TRENDS	"trends"

static zbx_get_export_file_f	get_history_file;
static zbx_get_export_file_f	get_trends_file;
static zbx_get_export_file_f	get_problems_file;
static zbx_config_export_t	*config_export;

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

static int	is_export_enabled(zbx_config_export_t *zbx_config_export, uint32_t flags)
{
	int			ret = FAIL;
	static uint32_t		export_types;

	if (NULL == zbx_config_export || NULL == zbx_config_export->dir)
		return ret;

	if (NULL != zbx_config_export->type)
	{
		if (0 == export_types)
			zbx_validate_export_type(zbx_config_export->type, &export_types);

		if (0 != (export_types & flags))
			ret = SUCCEED;
	}
	else
		ret = SUCCEED;

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
	return is_export_enabled(config_export, flags);
}

int	zbx_has_export_dir(void)
{
	return NULL != config_export && NULL != config_export->dir;
}

int	zbx_init_library_export(zbx_config_export_t *zbx_config_export, char **error)
{
	struct stat	fs;

	if (FAIL == is_export_enabled(zbx_config_export, ZBX_FLAG_EXPTYPE_EVENTS | ZBX_FLAG_EXPTYPE_TRENDS |
					ZBX_FLAG_EXPTYPE_HISTORY))
	{
		if (NULL != zbx_config_export->type)
		{
			*error = zbx_dsprintf(*error, "Misconfiguration: \"ExportType\" is set to '%s' "
					"while \"ExportDir\" is unset.", zbx_config_export->type);
			return FAIL;
		}
		return SUCCEED;
	}

	if (NULL == zbx_config_export->type)
	{
		zbx_config_export->type = zbx_dsprintf(zbx_config_export->type, "%s,%s,%s", ZBX_OPTION_EXPTYPE_EVENTS,
				ZBX_OPTION_EXPTYPE_HISTORY, ZBX_OPTION_EXPTYPE_TRENDS);
	}

	if (0 != stat(zbx_config_export->dir, &fs))
	{
		*error = zbx_dsprintf(*error, "Failed to stat the specified path \"%s\": %s.", zbx_config_export->dir,
				zbx_strerror(errno));
		return FAIL;
	}

	if (0 == S_ISDIR(fs.st_mode))
	{
		*error = zbx_dsprintf(*error, "The specified path \"%s\" is not a directory.", zbx_config_export->dir);
		return FAIL;
	}

	if (0 != access(zbx_config_export->dir, W_OK | R_OK))
	{
		*error = zbx_dsprintf(*error, "Cannot access path \"%s\": %s.", zbx_config_export->dir,
				zbx_strerror(errno));
		return FAIL;
	}

	config_export = zbx_config_export;

	return SUCCEED;
}

void	zbx_deinit_library_export(void)
{
	if (NULL != config_export)
	{
		zbx_free(config_export->dir);
		zbx_free(config_export->type);
	}
	get_history_file = NULL;
	get_trends_file = NULL;
	get_problems_file = NULL;
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

static zbx_export_file_t	*export_init(const char *process_type, const char *process_name, int process_num)
{
	char			*export_dir, *error = NULL;
	zbx_export_file_t	*file = NULL;

	if (NULL == config_export)
	{
		zabbix_log(LOG_LEVEL_CRIT, "export library is not initialized");
		exit(EXIT_FAILURE);
	}

	export_dir = zbx_strdup(NULL, config_export->dir);
	if ('/' == export_dir[strlen(export_dir) - 1])
		export_dir[strlen(export_dir) - 1] = '\0';

	if (NULL != file)
	{
		zbx_free(file->name);
		zbx_free(file);
	}

	file = (zbx_export_file_t *)zbx_malloc(NULL, sizeof(zbx_export_file_t));
	file->name = zbx_dsprintf(NULL, "%s/%s-%s-%d.ndjson", export_dir, process_type, process_name, process_num);

	free(export_dir);

	if (FAIL == open_export_file(file, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "%s", error);
		exit(EXIT_FAILURE);
	}

	file->missing = 0;

	return file;
}

zbx_export_file_t	*zbx_history_export_init(zbx_get_export_file_f get_export_file_cb, const char *process_name,
		int process_num)
{
	get_history_file = get_export_file_cb;

	return export_init("history", process_name, process_num);
}

zbx_export_file_t	*zbx_trends_export_init(zbx_get_export_file_f get_export_file_cb, const char *process_name,
		int process_num)
{
	get_trends_file = get_export_file_cb;

	return export_init("trends", process_name, process_num);
}

zbx_export_file_t	*zbx_problems_export_init(zbx_get_export_file_f get_export_file_cb, const char *process_name,
		int process_num)
{
	get_problems_file = get_export_file_cb;

	return export_init("problems", process_name, process_num);
}

void	zbx_export_deinit(zbx_export_file_t *file)
{
	zbx_fclose(file->file);
	zbx_free(file->name);
	zbx_free(file);
}

static void	export_write(const char *buf, size_t count, zbx_export_file_t *file)
{
#define ZBX_LOGGING_SUSPEND_TIME	10

	static time_t	last_log_time = 0;
	time_t		now;
	char		*error_msg = NULL;
	long		file_offset;

	if (NULL == config_export)
	{
		zabbix_log(LOG_LEVEL_CRIT, "export library is not initialized");
		exit(EXIT_FAILURE);
	}

	if (0 == file->missing && 0 != access(file->name, F_OK))
	{
		if (NULL != file->file && 0 != fclose(file->file))
			zabbix_log(LOG_LEVEL_DEBUG, "cannot close export file '%s': %s",file->name,
					zbx_strerror(errno));

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

	if (config_export->file_size <= count + (size_t)file_offset + 1)
	{
		char	filename_old[MAX_STRING_LEN];

		zbx_strscpy(filename_old, file->name);
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
	export_write(buf, count, get_problems_file());
}

void	zbx_history_export_write(const char *buf, size_t count)
{
	export_write(buf, count, get_history_file());
}

void	zbx_trends_export_write(const char *buf, size_t count)
{
	export_write(buf, count, get_trends_file());
}

static void	export_flush(zbx_export_file_t *file)
{
	if (NULL != file && NULL != file->file && 0 != fflush(file->file))
		zabbix_log(LOG_LEVEL_ERR, "cannot flush export file '%s': %s", file->name, zbx_strerror(errno));
}

void	zbx_problems_export_flush(void)
{
	export_flush(get_problems_file());
}

void	zbx_history_export_flush(void)
{
	export_flush(get_history_file());
}

void	zbx_trends_export_flush(void)
{
	export_flush(get_trends_file());
}
