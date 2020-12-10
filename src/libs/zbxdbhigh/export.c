/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
#include "log.h"
#include "export.h"

extern char		*CONFIG_EXPORT_DIR;
extern zbx_uint64_t	CONFIG_EXPORT_FILE_SIZE;

typedef struct
{
	char	*name;
	FILE	*file;
	time_t	last_check;
	int	missing;
}
zbx_export_file_t;

static zbx_export_file_t	*history_file;
static zbx_export_file_t	*trends_file;
static zbx_export_file_t	*problems_file;

static char	*export_dir;

int	zbx_is_export_enabled(void)
{
	if (NULL == CONFIG_EXPORT_DIR)
		return FAIL;

	return SUCCEED;
}

int	zbx_export_init(char **error)
{
	struct stat	fs;

	if (FAIL == zbx_is_export_enabled())
		return SUCCEED;

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

	return SUCCEED;
}

void	zbx_history_export_init(const char *process_name, int process_num)
{
	char	*error = NULL;

	history_file = (zbx_export_file_t *)zbx_malloc(NULL, sizeof(zbx_export_file_t));

	history_file->name = zbx_dsprintf(NULL, "%s/history-%s-%d.ndjson", export_dir, process_name, process_num);

	if (FAIL == open_export_file(history_file, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "%s", error);

		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	history_file->last_check = 0;
	history_file->missing = 0;

	trends_file = (zbx_export_file_t *)zbx_malloc(NULL, sizeof(zbx_export_file_t));

	trends_file->name = zbx_dsprintf(NULL, "%s/trends-%s-%d.ndjson", export_dir, process_name, process_num);

	if (FAIL == open_export_file(trends_file, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "%s", error);

		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	trends_file->last_check = 0;
	trends_file->missing = 0;
}

void	zbx_problems_export_init(const char *process_name, int process_num)
{
	char	*error = NULL;

	problems_file = (zbx_export_file_t *)zbx_malloc(NULL, sizeof(zbx_export_file_t));

	problems_file->name = zbx_dsprintf(NULL, "%s/problems-%s-%d.ndjson", export_dir, process_name, process_num);

	if (FAIL == open_export_file(problems_file, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "%s", error);

		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	problems_file->last_check = 0;
	problems_file->missing = 0;
}

static void	file_write(const char *buf, size_t count, zbx_export_file_t *file)
{
#define ZBX_SUSPEND_TIME	10

	time_t		now;
	char		*error = NULL;
	long		file_offset;

	now = time(NULL);

	if (ZBX_SUSPEND_TIME < now - file->last_check)
	{
		if (0 == file->missing && 0 != access(file->name, F_OK))
				file->missing = 1;

		if (1 == file->missing)
		{
			if (SUCCEED == open_export_file(file, &error))
			{
				file->missing = 0;
				zabbix_log(LOG_LEVEL_ERR, "regained access to export file '%s'", file->name);
			}
			else
				goto error;
		}

		file->last_check = now;
	}

	if (NULL == file->file && FAIL == open_export_file(file, &error))
		goto error;

	if (-1 == (file_offset = ftell(file->file)))
	{
		error = zbx_dsprintf(error, "cannot get current position in export file '%s': %s",
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
			error = zbx_dsprintf(error, "cannot remove export file '%s': %s",
					filename_old, zbx_strerror(errno));
			goto error;
		}

		if (0 != fclose(file->file))
		{
			error = zbx_dsprintf(error, "cannot close export file %s': %s",
					file->name, zbx_strerror(errno));
			file->file = NULL;
			goto error;
		}
		file->file = NULL;

		if (0 != rename(file->name, filename_old))
		{
			error = zbx_dsprintf(error, "cannot rename export file '%s': %s",
					file->name, zbx_strerror(errno));
			goto error;
		}

		if (FAIL == open_export_file(file, &error))
			goto error;
	}

	if (count != fwrite(buf, 1, count, file->file) || '\n' != fputc('\n', file->file))
	{
		error = zbx_dsprintf(error, "cannot write to export file '%s': %s", file->name, zbx_strerror(errno));
		goto error;
	}

	return;
error:
	if (NULL != file->file && 0 != fclose(file->file))
	{
		error = zbx_dsprintf(error, "%s; cannot close export file %s': %s",
				error, file->name, zbx_strerror(errno));
	}

	file->file = NULL;

	if (ZBX_SUSPEND_TIME < now - file->last_check)
	{
		zabbix_log(LOG_LEVEL_ERR, "%s", error);
		file->last_check = now;
	}

	zbx_free(error);

#undef ZBX_SUSPEND_TIME
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
