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

void	zbx_export_init(const char *process_name, int process_num)
{
	history_file_name = zbx_dsprintf(NULL, "history-%s-%d.ndjson", process_name, process_num);
	history_file = fopen(history_file_name, "a");

	trends_file_name = zbx_dsprintf(NULL, "trends-%s-%d.ndjson", process_name, process_num);
	trends_file = fopen(trends_file_name, "a");
}

int	zbx_history_export_write(const char *buf, size_t count)
{
	if (count != fwrite(buf, 1, count, history_file) || '\n' != fputc('\n', history_file))
	{
		zabbix_log(LOG_LEVEL_WARNING, "failed to write '%s': %s", buf, zbx_strerror(errno));
		return FAIL;
	}

	return SUCCEED;
}
void	zbx_history_export_flush(void)
{
	if (0 != fflush(history_file))
		zabbix_log(LOG_LEVEL_WARNING, "failed to flush into '%s': %s", history_file_name, zbx_strerror(errno));
}

int	zbx_trends_export_write(const char *buf, size_t count)
{
	if (count != fwrite(buf, 1, count, trends_file) || 1 != fputc("\n", trends_file))
	{
		zabbix_log(LOG_LEVEL_WARNING, "failed to write '%s': %s", buf, zbx_strerror(errno));
		return FAIL;
	}

	return SUCCEED;
}
void	zbx_trends_export_flush(void)
{
	if (0 != fflush(trends_file))
		zabbix_log(LOG_LEVEL_WARNING, "failed to flush into '%s': %s", trends_file_name, zbx_strerror(errno));
}
