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

static zbx_get_progname_f	get_progname_cb = NULL;

static void	log_errorv(const char *fmt, va_list args)
{
	fprintf(stderr, "%s [%li]: ", (NULL != get_progname_cb) ? get_progname_cb() : "", zbx_get_thread_id());
	vfprintf(stderr, fmt, args);
	fprintf(stderr, "\n");
	fflush(stderr);
}

static void	log_handle_stub(int level, const char *fmt, va_list args)
{
	ZBX_UNUSED(level);

	log_errorv(fmt, args);
}

static int	log_level_stub(void)
{
	return LOG_LEVEL_WARNING;
}

static zbx_log_cb_t	zbx_log_handle_impl = log_handle_stub;
static zbx_log_level_cb_t	zbx_get_log_level_impl = log_level_stub;

static zbx_backtrace_f		backtrace_cb = NULL;

void	zbx_init_library_common(zbx_log_cb_t log_func, zbx_log_level_cb_t log_level_func,
		zbx_get_progname_f get_progname, zbx_backtrace_f backtrace)
{
	zbx_log_handle_impl = log_func;
	zbx_get_log_level_impl = log_level_func;
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
	va_list	args;

	va_start(args, fmt);
	zbx_log_handle_impl(level, fmt, args);
	va_end(args);
}

int	zbx_get_log_level(void)
{
	return zbx_get_log_level_impl();
}

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
	log_errorv(fmt, args);
	va_end(args);
}
