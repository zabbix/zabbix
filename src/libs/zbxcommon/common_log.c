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

#include "zbxcommon.h"

#define LOG_LEVEL_DEC_FAIL	-2
#define LOG_LEVEL_DEC_SUCCEED	-1
#define LOG_LEVEL_UNCHANGED	0
#define LOG_LEVEL_INC_SUCCEED	1
#define LOG_LEVEL_INC_FAIL	2

static int			log_level = LOG_LEVEL_WARNING;
static ZBX_THREAD_LOCAL int	*plog_level = &log_level;
static zbx_log_func_t		log_func_callback = NULL;
static zbx_get_progname_f	get_progname_cb = NULL;
static zbx_backtrace_f		backtrace_cb = NULL;

#define LOG_COMPONENT_NAME_LEN	64
static ZBX_THREAD_LOCAL int	log_level_change = LOG_LEVEL_UNCHANGED;
static ZBX_THREAD_LOCAL char	log_component_name[LOG_COMPONENT_NAME_LEN + 1];
#undef LOG_COMPONENT_NAME_LEN

void	zbx_init_library_common(zbx_log_func_t log_func, zbx_get_progname_f get_progname, zbx_backtrace_f backtrace)
{
	log_func_callback = log_func;
	get_progname_cb = get_progname;
	backtrace_cb = backtrace;
}

void	zbx_this_should_never_happen_backtrace(void)
{
	if (NULL != backtrace_cb)
		backtrace_cb();
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
	return *plog_level;
}

void	zbx_set_log_level(int level)
{
	log_level = level;
}

const char	*zbx_get_log_component_name(void)
{
	return log_component_name;
}

#ifndef _WINDOWS
static const char	*zabbix_get_log_level_ref_string(int loglevel)
{
	switch (loglevel)
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

const char	*zabbix_get_log_level_string(void)
{
	return zabbix_get_log_level_ref_string(*plog_level);
}

void	zabbix_increase_log_level(void)
{
	if (LOG_LEVEL_TRACE == *plog_level)
	{
		log_level_change = LOG_LEVEL_INC_FAIL;
		return;
	}

	log_level_change = LOG_LEVEL_INC_SUCCEED;

	*plog_level = *plog_level + 1;

	return;
}

void	zabbix_decrease_log_level(void)
{
	if (LOG_LEVEL_EMPTY == *plog_level)
	{
		log_level_change = LOG_LEVEL_DEC_FAIL;
		return;
	}

	log_level_change = LOG_LEVEL_DEC_SUCCEED;

	*plog_level = *plog_level - 1;

	return;
}

/******************************************************************************
 *                                                                            *
 * Purpose: log last loglevel change result                                   *
 *                                                                            *
 * Comments: With consequent fast changes only the last attempt result would  *
 *           be logged.                                                       *
 *                                                                            *
 ******************************************************************************/
void	 zabbix_report_log_level_change(void)
{
	int	change;

	if (0 == log_level_change)
		return;

	/* reset log level change history to avoid recursion */
	change = log_level_change;
	log_level_change = LOG_LEVEL_UNCHANGED;

	switch (change)
	{
		case LOG_LEVEL_DEC_FAIL:
			zabbix_log(LOG_LEVEL_INFORMATION, "cannot decrease log level:"
					" minimum level has been already set");
			break;
		case LOG_LEVEL_DEC_SUCCEED:
			zabbix_log(LOG_LEVEL_INFORMATION, "log level has been decreased to %s",
					zabbix_get_log_level_string());
			break;
		case LOG_LEVEL_INC_SUCCEED:
			zabbix_log(LOG_LEVEL_INFORMATION, "log level has been increased to %s",
					zabbix_get_log_level_string());
			break;
		case LOG_LEVEL_INC_FAIL:
			zabbix_log(LOG_LEVEL_INFORMATION, "cannot increase log level:"
					" maximum level has been already set");
			break;
	}
}

void	zbx_set_log_component(const char *name, zbx_log_component_t *component)
{
	int	ll = *plog_level;

	zbx_snprintf(log_component_name, sizeof(log_component_name), "[%s] ", name);

	plog_level = &component->level;
	component->level = ll;
	component->name = log_component_name;
}

/******************************************************************************
 *                                                                            *
 * Purpose: change log level of the specified component                       *
 *                                                                            *
 * Comments: This function is used to change log level managed threads.       *
 *                                                                            *
 ******************************************************************************/
void	zbx_change_component_log_level(zbx_log_component_t *component, int direction)
{
	if (0 > direction)
	{
		if (LOG_LEVEL_EMPTY == component->level)
		{
			zabbix_log(LOG_LEVEL_INFORMATION, "%scannot decrease log level:"
					" minimum level has been already set", component->name);
		}
		else
		{
			component->level += direction;
			zabbix_log(LOG_LEVEL_INFORMATION, "%slog level has been decreased to %s",
					component->name, zabbix_get_log_level_ref_string(component->level));
		}
	}
	else
	{
		if (LOG_LEVEL_TRACE == component->level)
		{
			zabbix_log(LOG_LEVEL_INFORMATION, "%scannot increase log level:"
					" maximum level has been already set", component->name);
		}
		else
		{
			component->level += direction;
			zabbix_log(LOG_LEVEL_INFORMATION, "%slog level has been increased to %s",
					component->name, zabbix_get_log_level_ref_string(component->level));
		}
	}
}
#endif

/******************************************************************************
 *                                                                            *
 * Purpose: Print error text to the stderr                                    *
 *                                                                            *
 * Parameters: fmt - format of message                                        *
 *                                                                            *
 ******************************************************************************/
void	zbx_error(const char *fmt, ...)
{
	va_list	args;

	va_start(args, fmt);

	fprintf(stderr, "%s [%li]: ", (NULL != get_progname_cb) ? get_progname_cb() : "", zbx_get_thread_id());
	vfprintf(stderr, fmt, args);
	fprintf(stderr, "\n");
	fflush(stderr);

	va_end(args);
}
