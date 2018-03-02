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

static char	*history_file_name;
static FILE	*history_file;

static char	*trends_file_name;
static FILE	*trends_file;

static char	*problems_file_name;
static FILE	*problems_file;

void	zbx_history_export_init(const char *process_name, int process_num)
{
	history_file_name = zbx_dsprintf(NULL, "history-%s-%d.ndjson", process_name, process_num);

	if (NULL == (history_file = fopen(history_file_name, "a")))
	{
		zabbix_log(LOG_LEVEL_CRIT, "failed to open export file: %s", zbx_strerror(errno));
	}

	trends_file_name = zbx_dsprintf(NULL, "trends-%s-%d.ndjson", process_name, process_num);

	if (NULL == (trends_file = fopen(trends_file_name, "a")))
	{
		zabbix_log(LOG_LEVEL_CRIT, "failed to open export file: %s", zbx_strerror(errno));
	}
}

void	zbx_problems_export_init(const char *process_name, int process_num)
{
	problems_file_name = zbx_dsprintf(NULL, "problems-%s-%d.ndjson", process_name, process_num);

	if (NULL == (problems_file = fopen(problems_file_name, "a")))
	{
		zabbix_log(LOG_LEVEL_CRIT, "failed to open export file: %s", zbx_strerror(errno));
	}
}

static	int	file_write(const char *buf, size_t count, FILE *file)
{
	if (count != fwrite(buf, 1, count, file) || '\n' != fputc('\n', file))
	{
		zabbix_log(LOG_LEVEL_WARNING, "failed to write '%s': %s", buf, zbx_strerror(errno));
		return FAIL;
	}

	return SUCCEED;
}

int	zbx_problems_export_write(const char *buf, size_t count)
{
	return file_write(buf, count, problems_file);
}

int	zbx_history_export_write(const char *buf, size_t count)
{
	return file_write(buf, count, history_file);
}

int	zbx_trends_export_write(const char *buf, size_t count)
{
	return file_write(buf, count, trends_file);
}

void	zbx_problems_export_flush(void)
{
	if (0 != fflush(problems_file))
		zabbix_log(LOG_LEVEL_WARNING, "failed to flush into '%s': %s", history_file_name, zbx_strerror(errno));
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
