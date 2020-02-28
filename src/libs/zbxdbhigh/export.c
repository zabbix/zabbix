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

static char	*history_file_name;
static FILE	*history_file;

static char	*trends_file_name;
static FILE	*trends_file;

static char	*problems_file_name;
static FILE	*problems_file;
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

void	zbx_history_export_init(const char *process_name, int process_num)
{
	history_file_name = zbx_dsprintf(NULL, "%s/history-%s-%d.ndjson", export_dir, process_name, process_num);

	if (NULL == (history_file = fopen(history_file_name, "a")))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot open export file '%s': %s", history_file_name,
				zbx_strerror(errno));
		exit(EXIT_FAILURE);
	}

	trends_file_name = zbx_dsprintf(NULL, "%s/trends-%s-%d.ndjson", export_dir, process_name, process_num);

	if (NULL == (trends_file = fopen(trends_file_name, "a")))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot open export file '%s': %s", trends_file_name,
				zbx_strerror(errno));
		exit(EXIT_FAILURE);
	}
}

void	zbx_problems_export_init(const char *process_name, int process_num)
{
	problems_file_name = zbx_dsprintf(NULL, "%s/problems-%s-%d.ndjson", export_dir, process_name, process_num);

	if (NULL == (problems_file = fopen(problems_file_name, "a")))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot open export file '%s': %s", problems_file_name,
				zbx_strerror(errno));
		exit(EXIT_FAILURE);
	}
}

static void	file_write(const char *buf, size_t count, FILE **file, const char *name)
{
#define ZBX_LOGGING_SUSPEND_TIME	10

	static time_t	last_log_time = 0;
	time_t		now;
	char		log_str[MAX_STRING_LEN];
	long		file_offset;
	size_t		log_str_offset = 0;

	if (NULL == *file && (NULL == (*file = fopen(name, "a"))))
	{
		log_str_offset = zbx_snprintf(log_str, sizeof(log_str), "cannot open export file '%s': %s",
				name, zbx_strerror(errno));
		goto error;
	}

	if (-1 == (file_offset = ftell(*file)))
	{
		log_str_offset = zbx_snprintf(log_str, sizeof(log_str),
				"cannot get current position in export file '%s': %s", name, zbx_strerror(errno));
		goto error;
	}

	if (CONFIG_EXPORT_FILE_SIZE <= count + (size_t)file_offset + 1)
	{
		char	filename_old[MAX_STRING_LEN];

		strscpy(filename_old, name);
		zbx_strlcat(filename_old, ".old", MAX_STRING_LEN);

		if (0 == access(filename_old, F_OK) && 0 != remove(filename_old))
		{
			log_str_offset = zbx_snprintf(log_str, sizeof(log_str), "cannot remove export file '%s': %s",
					filename_old, zbx_strerror(errno));
			goto error;
		}

		if (0 != fclose(*file))
		{
			log_str_offset = zbx_snprintf(log_str, sizeof(log_str), "cannot close export file %s': %s",
					name, zbx_strerror(errno));
			*file = NULL;
			goto error;
		}
		*file = NULL;

		if (0 != rename(name, filename_old))
		{
			log_str_offset = zbx_snprintf(log_str, sizeof(log_str), "cannot rename export file '%s': %s",
					name, zbx_strerror(errno));
			goto error;
		}

		if (NULL == (*file = fopen(name, "a")))
		{
			log_str_offset = zbx_snprintf(log_str, sizeof(log_str), "cannot open export file '%s': %s",
					name, zbx_strerror(errno));
			goto error;
		}
	}

	if (count != fwrite(buf, 1, count, *file) || '\n' != fputc('\n', *file))
	{
		log_str_offset = zbx_snprintf(log_str, sizeof(log_str), "cannot write to export file '%s': %s",
				name, zbx_strerror(errno));
		goto error;
	}

	return;
error:
	if (NULL != *file && 0 != fclose(*file))
	{
		zbx_snprintf(log_str + log_str_offset, sizeof(log_str) - log_str_offset,
				"; cannot close export file %s': %s", name, zbx_strerror(errno));
	}

	*file = NULL;
	now = time(NULL);

	if (ZBX_LOGGING_SUSPEND_TIME < now - last_log_time)
	{
		zabbix_log(LOG_LEVEL_ERR, "%s", log_str);
		last_log_time = now;
	}

#undef ZBX_LOGGING_SUSPEND_TIME
}

void	zbx_problems_export_write(const char *buf, size_t count)
{
	file_write(buf, count, &problems_file, problems_file_name);
}

void	zbx_history_export_write(const char *buf, size_t count)
{
	file_write(buf, count, &history_file, history_file_name);
}

void	zbx_trends_export_write(const char *buf, size_t count)
{
	file_write(buf, count, &trends_file, trends_file_name);
}

static void	zbx_flush(FILE *file, const char *file_name)
{
	if (0 != fflush(file))
		zabbix_log(LOG_LEVEL_ERR, "cannot flush export file '%s': %s", file_name, zbx_strerror(errno));
}

void	zbx_problems_export_flush(void)
{
	if (NULL != problems_file)
		zbx_flush(problems_file, problems_file_name);
}

void	zbx_history_export_flush(void)
{
	if (NULL != history_file)
		zbx_flush(history_file, history_file_name);
}

void	zbx_trends_export_flush(void)
{
	if (NULL != trends_file)
		zbx_flush(trends_file, trends_file_name);
}
