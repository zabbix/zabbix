/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

#include "zbxcommon.h"

static int		log_level = LOG_LEVEL_WARNING;
static zbx_log_func_t	log_func_callback = NULL;

void	zbx_logging_init(zbx_log_func_t log_func)
{
	log_func_callback = log_func;
}

void	zbx_log_handle(int level, const char *fmt, ...)
{
	va_list args;

	va_start(args, fmt);
	log_func_callback(level, fmt, args);
	va_end(args);
}

int	zbx_get_log_level(void)
{
	return log_level;
}

void	zbx_set_log_level(int level)
{
	log_level = level;
}

#ifndef _WINDOWS
const char	*zabbix_get_log_level_string(void)
{
	switch (log_level)
	{
		case LOG_LEVEL_EMPTY:
			return "0 (none)";
		case LOG_LEVEL_CRIT:
			return "1 (critical)";
		case LOG_LEVEL_ERR:
			return "2 (error)";
		case LOG_LEVEL_WARNING:
			return "3 (warning)";
		case LOG_LEVEL_DEBUG:
			return "4 (debug)";
		case LOG_LEVEL_TRACE:
			return "5 (trace)";
	}

	THIS_SHOULD_NEVER_HAPPEN;
	exit(EXIT_FAILURE);
}

int	zabbix_increase_log_level(void)
{
	if (LOG_LEVEL_TRACE == log_level)
		return FAIL;

	log_level = log_level + 1;

	return SUCCEED;
}

int	zabbix_decrease_log_level(void)
{
	if (LOG_LEVEL_EMPTY == log_level)
		return FAIL;

	log_level = log_level - 1;

	return SUCCEED;
}
#endif
