/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

extern char	*CONFIG_EXPORT_DIR;

static char	*history_file_name;
static FILE	*history_file;

static char	*trends_file_name;
static FILE	*trends_file;

static char	*problems_file_name;
static FILE	*problems_file;
static char	*export_dir;

#define ZBX_EXPORT_WAIT_FAIL 10

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
		zabbix_log(LOG_LEVEL_CRIT, "failed to open export file '%s': %s", history_file_name,
				zbx_strerror(errno));
	}

	trends_file_name = zbx_dsprintf(NULL, "%s/trends-%s-%d.ndjson", export_dir, process_name, process_num);

	if (NULL == (trends_file = fopen(trends_file_name, "a")))
	{
		zabbix_log(LOG_LEVEL_CRIT, "failed to open export file '%s': %s", trends_file_name,
				zbx_strerror(errno));
	}
}

void	zbx_problems_export_init(const char *process_name, int process_num)
{
	problems_file_name = zbx_dsprintf(NULL, "%s/problems-%s-%d.ndjson", export_dir, process_name, process_num);

	if (NULL == (problems_file = fopen(problems_file_name, "a")))
	{
		zabbix_log(LOG_LEVEL_CRIT, "failed to open export file '%s': %s", problems_file_name,
				zbx_strerror(errno));
	}
}

static	int	file_write(const char *buf, size_t count, FILE **file, const char *name)
{
	if ((int)count > ZBX_GIBIBYTE)
	{
		zabbix_log(LOG_LEVEL_WARNING, "maximum file size is too small");
		return FAIL;
	}

	if (ZBX_GIBIBYTE <= (long)count + ftell(*file) + 1)
	{
		char	filename_old[MAX_STRING_LEN];

		strscpy(filename_old, name);
		zbx_strlcat(filename_old, ".old", MAX_STRING_LEN);
		remove(filename_old);
		zbx_fclose(*file);

		if (0 != rename(name, filename_old))
			zabbix_log(LOG_LEVEL_WARNING, "cannot rename export file '%s': %s", name, zbx_strerror(errno));

		while (NULL == (*file = fopen(name, "a")))
		{
			zabbix_log(LOG_LEVEL_CRIT, "failed to open export file '%s': %s: retrying in %d seconds",
					name, zbx_strerror(errno), ZBX_EXPORT_WAIT_FAIL);
			sleep(ZBX_EXPORT_WAIT_FAIL);
		}
	}

	if (count != fwrite(buf, 1, count, *file) || '\n' != fputc('\n', *file))
	{
		zabbix_log(LOG_LEVEL_WARNING, "failed to write '%s': %s", buf, zbx_strerror(errno));
		return FAIL;
	}

	return SUCCEED;
}

int	zbx_problems_export_write(const char *buf, size_t count)
{
	return file_write(buf, count, &problems_file, problems_file_name);
}

int	zbx_history_export_write(const char *buf, size_t count)
{
	return file_write(buf, count, &history_file, history_file_name);
}

int	zbx_trends_export_write(const char *buf, size_t count)
{
	return file_write(buf, count, &trends_file, trends_file_name);
}

void	zbx_problems_export_flush(void)
{
	if (0 != fflush(problems_file))
		zabbix_log(LOG_LEVEL_WARNING, "failed to flush into '%s': %s", problems_file_name, zbx_strerror(errno));
}

void	zbx_history_export_flush(void)
{
	if (0 != fflush(history_file))
		zabbix_log(LOG_LEVEL_WARNING, "failed to flush into '%s': %s", history_file_name, zbx_strerror(errno));
}

void	zbx_trends_export_flush(void)
{
	if (0 != fflush(trends_file))
		zabbix_log(LOG_LEVEL_WARNING, "failed to flush into '%s': %s", trends_file_name, zbx_strerror(errno));
}
