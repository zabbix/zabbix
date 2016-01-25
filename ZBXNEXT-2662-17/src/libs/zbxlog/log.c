/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
#include "mutexs.h"
#include "threads.h"
#include "cfg.h"
#ifdef _WINDOWS
#	include "messages.h"
#	include "service.h"
static HANDLE		system_log_handle = INVALID_HANDLE_VALUE;
#endif

static char		log_filename[MAX_STRING_LEN];
static int		log_type = LOG_TYPE_UNDEFINED;
static ZBX_MUTEX	log_access = ZBX_MUTEX_NULL;
static int		log_level = LOG_LEVEL_WARNING;

#define LOCK_LOG	zbx_mutex_lock(&log_access)
#define UNLOCK_LOG	zbx_mutex_unlock(&log_access)

#define ZBX_MESSAGE_BUF_SIZE	1024

#define ZBX_CHECK_LOG_LEVEL(level)	\
		((LOG_LEVEL_INFORMATION != level && (level > log_level || LOG_LEVEL_EMPTY == level)) ? FAIL : SUCCEED)

#ifdef _WINDOWS
#	define STDIN_FILENO	_fileno(stdin)
#	define STDOUT_FILENO	_fileno(stdout)
#	define STDERR_FILENO	_fileno(stderr)

#	define ZBX_DEV_NULL	"NUL"

#	define dup2(fd1, fd2)	_dup2(fd1, fd2)
#else
#	define ZBX_DEV_NULL	"/dev/null"
#endif

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

void	zbx_redirect_stdio(const char *filename)
{
	int		fd;
	const char	default_file[] = ZBX_DEV_NULL;
	int		open_flags = O_WRONLY;

	if (NULL != filename && '\0' != *filename)
		open_flags |= O_CREAT | O_APPEND;
	else
		filename = default_file;

	if (-1 == (fd = open(filename, open_flags, 0666)))
	{
		zbx_error("cannot open \"%s\": %s", filename, zbx_strerror(errno));
		exit(EXIT_FAILURE);
	}

	fflush(stdout);
	if (-1 == dup2(fd, STDOUT_FILENO))
		zbx_error("cannot redirect stdout to \"%s\": %s", filename, zbx_strerror(errno));

	fflush(stderr);
	if (-1 == dup2(fd, STDERR_FILENO))
		zbx_error("cannot redirect stderr to \"%s\": %s", filename, zbx_strerror(errno));

	close(fd);

	if (-1 == (fd = open(default_file, O_RDONLY)))
	{
		zbx_error("cannot open \"%s\": %s", default_file, zbx_strerror(errno));
		exit(EXIT_FAILURE);
	}

	if (-1 == dup2(fd, STDIN_FILENO))
		zbx_error("cannot redirect stdin to \"%s\": %s", default_file, zbx_strerror(errno));

	close(fd);
}

/******************************************************************************
 *                                                                            *
 * Function: get_time                                                         *
 *                                                                            *
 * Purpose:                                                                   *
 *     get current time and store it in memory localtions provided by caller  *
 *                                                                            *
 * Parameters:                                                                *
 *     tm           - [OUT] broken-down representation of the current time    *
 *     milliseconds - [OUT] milliseconds since the previous second            *
 *                                                                            *
 * Comments:                                                                  *
 *     On Windows localtime() returns pointer to static, thread-local storage *
 *     location. On Unix localtime() is not thread-safe and re-entrant as it  *
 *     returns pointer to static storage location which can be overwritten    *
 *     by localtime() itself or other time functions in other threads or      *
 *     signal handlers. To avoid this we use localtime_r().                   *
 *                                                                            *
 ******************************************************************************/
static void	get_time(struct tm *tm, long *milliseconds)
{
#ifdef _WINDOWS
	struct _timeb	current_time;
	struct tm	*tm_thread_static;

	_ftime(&current_time);
	if (NULL != (tm_thread_static = localtime(&current_time.time)))
	{
		*tm = *tm_thread_static;
	}
	else
	{
		THIS_SHOULD_NEVER_HAPPEN;
		memset(tm, 0, sizeof(struct tm));
	}

	*milliseconds = current_time.millitm;
#else
	struct timeval	current_time;
	struct tm	tm_local;

	gettimeofday(&current_time, NULL);
	if (NULL != localtime_r(&current_time.tv_sec, &tm_local))
	{
		*tm = tm_local;
	}
	else
	{
		THIS_SHOULD_NEVER_HAPPEN;
		memset(tm, 0, sizeof(struct tm));
	}

	*milliseconds = current_time.tv_usec / 1000;
#endif
}

static void	rotate_log(const char *log_filename)
{
	zbx_stat_t		buf;
	zbx_uint64_t		new_size;
	static zbx_uint64_t	old_size = ZBX_MAX_UINT64;

	if (0 == CONFIG_LOG_FILE_SIZE || NULL == log_filename || '\0' == *log_filename)
	{
		/* redirect only once if log file wasn't specified or there is no log file size limit */
		if (ZBX_MAX_UINT64 == old_size)
		{
			old_size = 0;
			zbx_redirect_stdio(log_filename);
		}

		return;
	}

	if (0 != zbx_stat(log_filename, &buf))
		return;

	new_size = buf.st_size;

	if ((zbx_uint64_t)CONFIG_LOG_FILE_SIZE * ZBX_MEBIBYTE < new_size)
	{
		char	filename_old[MAX_STRING_LEN];

		strscpy(filename_old, log_filename);
		zbx_strlcat(filename_old, ".old", MAX_STRING_LEN);
		remove(filename_old);

		if (0 != rename(log_filename, filename_old))
		{
			FILE	*log_file = NULL;

			if (NULL != (log_file = fopen(log_filename, "w")))
			{
				long		milliseconds;
				struct tm	tm;

				get_time(&tm, &milliseconds);

				fprintf(log_file, "%6li:%.4d%.2d%.2d:%.2d%.2d%.2d.%03ld"
						" cannot rename log file \"%s\" to \"%s\": %s\n",
						zbx_get_thread_id(),
						tm.tm_year + 1900,
						tm.tm_mon + 1,
						tm.tm_mday,
						tm.tm_hour,
						tm.tm_min,
						tm.tm_sec,
						milliseconds,
						log_filename,
						filename_old,
						zbx_strerror(errno));

				fprintf(log_file, "%6li:%.4d%.2d%.2d:%.2d%.2d%.2d.%03ld"
						" Logfile \"%s\" size reached configured limit"
						" LogFileSize but moving it to \"%s\" failed. The logfile"
						" was truncated.\n",
						zbx_get_thread_id(),
						tm.tm_year + 1900,
						tm.tm_mon + 1,
						tm.tm_mday,
						tm.tm_hour,
						tm.tm_min,
						tm.tm_sec,
						milliseconds,
						log_filename,
						filename_old);

				zbx_fclose(log_file);

				new_size = 0;
			}
		}
		else
			new_size = 0;
	}

	if (old_size > new_size)
		zbx_redirect_stdio(log_filename);

	old_size = new_size;
}

#ifndef _WINDOWS
static sigset_t	orig_mask;

static void	lock_log(void)
{
	sigset_t	mask;

	sigemptyset(&mask);
	sigaddset(&mask, SIGUSR1);
	sigaddset(&mask, SIGTERM);	/* block SIGTERM, SIGINT to prevent deadlock on log file mutex */
	sigaddset(&mask, SIGINT);

	if (0 > sigprocmask(SIG_BLOCK, &mask, &orig_mask))
		zbx_error("cannot set sigprocmask to block the user signal");

	LOCK_LOG;
}

static void	unlock_log(void)
{
	UNLOCK_LOG;

	if (0 > sigprocmask(SIG_SETMASK, &orig_mask, NULL))
		zbx_error("cannot restore sigprocmask");
}
#else
static void	lock_log(void)
{
	LOCK_LOG;
}

static void	unlock_log(void)
{
	UNLOCK_LOG;
}
#endif

void	zbx_handle_log(void)
{
	if (LOG_TYPE_FILE != log_type)
		return;

	lock_log();

	rotate_log(log_filename);

	unlock_log();
}

int	zabbix_open_log(int type, int level, const char *filename)
{
	FILE	*log_file = NULL;
#ifdef _WINDOWS
	wchar_t	*wevent_source;
#endif
	log_type = type;
	log_level = level;

	if (LOG_TYPE_SYSTEM == type)
	{
#ifdef _WINDOWS
		wevent_source = zbx_utf8_to_unicode(ZABBIX_EVENT_SOURCE);
		system_log_handle = RegisterEventSource(NULL, wevent_source);
		zbx_free(wevent_source);
#else
		openlog(syslog_app_name, LOG_PID, LOG_DAEMON);
#endif
	}
	else if (LOG_TYPE_FILE == type)
	{
		if (MAX_STRING_LEN <= strlen(filename))
		{
			zbx_error("too long path for logfile");
			exit(EXIT_FAILURE);
		}

		if (FAIL == zbx_mutex_create_force(&log_access, ZBX_MUTEX_LOG))
		{
			zbx_error("unable to create mutex for log file");
			exit(EXIT_FAILURE);
		}

		if (NULL == (log_file = fopen(filename, "a+")))
		{
			zbx_error("unable to open log file [%s]: %s", filename, zbx_strerror(errno));
			exit(EXIT_FAILURE);
		}

		strscpy(log_filename, filename);
		zbx_fclose(log_file);
	}
	else if (LOG_TYPE_CONSOLE == type)
	{
		if (FAIL == zbx_mutex_create_force(&log_access, ZBX_MUTEX_LOG))
		{
			zbx_error("unable to create mutex for standard output");
			exit(EXIT_FAILURE);
		}

		fflush(stderr);
		if (-1 == dup2(STDOUT_FILENO, STDERR_FILENO))
			zbx_error("cannot redirect stderr to stdout: %s", zbx_strerror(errno));
	}

	return SUCCEED;
}

void	zabbix_close_log(void)
{
	if (LOG_TYPE_SYSTEM == log_type)
	{
#ifdef _WINDOWS
		if (NULL != system_log_handle)
			DeregisterEventSource(system_log_handle);
#else
		closelog();
#endif
	}
	else if (LOG_TYPE_FILE == log_type || LOG_TYPE_CONSOLE == log_type)
	{
		zbx_mutex_destroy(&log_access);
	}
}

void	zabbix_set_log_level(int level)
{
	log_level = level;
}

void	zabbix_errlog(zbx_err_codes_t err, ...)
{
	const char	*msg;
	char		*s = NULL;
	va_list		ap;

	switch (err)
	{
		case ERR_Z3001:
			msg = "connection to database '%s' failed: [%d] %s";
			break;
		case ERR_Z3002:
			msg = "cannot create database '%s': [%d] %s";
			break;
		case ERR_Z3003:
			msg = "no connection to the database";
			break;
		case ERR_Z3004:
			msg = "cannot close database: [%d] %s";
			break;
		case ERR_Z3005:
			msg = "query failed: [%d] %s [%s]";
			break;
		case ERR_Z3006:
			msg = "fetch failed: [%d] %s";
			break;
		case ERR_Z3007:
			msg = "query failed: [%d] %s";
			break;
		default:
			msg = "unknown error";
	}

	va_start(ap, err);
	s = zbx_dvsprintf(s, msg, ap);
	va_end(ap);

	zabbix_log(LOG_LEVEL_ERR, "[Z%04d] %s", err, s);

	zbx_free(s);
}

/******************************************************************************
 *                                                                            *
 * Function: zabbix_check_log_level                                           *
 *                                                                            *
 * Purpose: checks if the specified log level must be logged                  *
 *                                                                            *
 * Return value: SUCCEED - the log level must be logged                       *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zabbix_check_log_level(int level)
{
	return ZBX_CHECK_LOG_LEVEL(level);
}

void	__zbx_zabbix_log(int level, const char *fmt, ...)
{
	FILE		*log_file = NULL;
	char		message[MAX_BUFFER_LEN];
	va_list		args;
#ifdef _WINDOWS
	WORD		wType;
	wchar_t		thread_id[20], *strings[2];
#endif
	if (SUCCEED != ZBX_CHECK_LOG_LEVEL(level))
		return;

	if (LOG_TYPE_FILE == log_type)
	{
		lock_log();

		rotate_log(log_filename);

		if (NULL != (log_file = fopen(log_filename, "a+")))
		{
			long		milliseconds;
			struct tm	tm;

			get_time(&tm, &milliseconds);

			fprintf(log_file,
					"%6li:%.4d%.2d%.2d:%.2d%.2d%.2d.%03ld ",
					zbx_get_thread_id(),
					tm.tm_year + 1900,
					tm.tm_mon + 1,
					tm.tm_mday,
					tm.tm_hour,
					tm.tm_min,
					tm.tm_sec,
					milliseconds
					);

			va_start(args, fmt);
			vfprintf(log_file, fmt, args);
			va_end(args);

			fprintf(log_file, "\n");

			zbx_fclose(log_file);
		}

		unlock_log();

		return;
	}

	if (LOG_TYPE_CONSOLE == log_type)
	{
		long		milliseconds;
		struct tm	tm;

		lock_log();

		get_time(&tm, &milliseconds);

		fprintf(stdout,
				"%6li:%.4d%.2d%.2d:%.2d%.2d%.2d.%03ld ",
				zbx_get_thread_id(),
				tm.tm_year + 1900,
				tm.tm_mon + 1,
				tm.tm_mday,
				tm.tm_hour,
				tm.tm_min,
				tm.tm_sec,
				milliseconds
				);

		va_start(args, fmt);
		vfprintf(stdout, fmt, args);
		va_end(args);

		fprintf(stdout, "\n");

		fflush(stdout);

		unlock_log();

		return;
	}

	va_start(args, fmt);
	zbx_vsnprintf(message, sizeof(message), fmt, args);
	va_end(args);

	if (LOG_TYPE_SYSTEM == log_type)
	{
#ifdef _WINDOWS
		switch (level)
		{
			case LOG_LEVEL_CRIT:
			case LOG_LEVEL_ERR:
				wType = EVENTLOG_ERROR_TYPE;
				break;
			case LOG_LEVEL_WARNING:
				wType = EVENTLOG_WARNING_TYPE;
				break;
			default:
				wType = EVENTLOG_INFORMATION_TYPE;
				break;
		}

		StringCchPrintf(thread_id, ARRSIZE(thread_id), TEXT("[%li]: "), zbx_get_thread_id());
		strings[0] = thread_id;
		strings[1] = zbx_utf8_to_unicode(message);

		ReportEvent(
			system_log_handle,
			wType,
			0,
			MSG_ZABBIX_MESSAGE,
			NULL,
			sizeof(strings) / sizeof(*strings),
			0,
			strings,
			NULL);

		zbx_free(strings[1]);

#else	/* not _WINDOWS */

		/* for nice printing into syslog */
		switch (level)
		{
			case LOG_LEVEL_CRIT:
				syslog(LOG_CRIT, "%s", message);
				break;
			case LOG_LEVEL_ERR:
				syslog(LOG_ERR, "%s", message);
				break;
			case LOG_LEVEL_WARNING:
				syslog(LOG_WARNING, "%s", message);
				break;
			case LOG_LEVEL_DEBUG:
			case LOG_LEVEL_TRACE:
				syslog(LOG_DEBUG, "%s", message);
				break;
			case LOG_LEVEL_INFORMATION:
				syslog(LOG_INFO, "%s", message);
				break;
			default:
				/* LOG_LEVEL_EMPTY - print nothing */
				break;
		}

#endif	/* _WINDOWS */
	}	/* LOG_TYPE_SYSLOG */
	else	/* LOG_TYPE_UNDEFINED == log_type */
	{
		lock_log();

		switch (level)
		{
			case LOG_LEVEL_CRIT:
				zbx_error("ERROR: %s", message);
				break;
			case LOG_LEVEL_ERR:
				zbx_error("Error: %s", message);
				break;
			case LOG_LEVEL_WARNING:
				zbx_error("Warning: %s", message);
				break;
			case LOG_LEVEL_DEBUG:
				zbx_error("DEBUG: %s", message);
				break;
			case LOG_LEVEL_TRACE:
				zbx_error("TRACE: %s", message);
				break;
			default:
				zbx_error("%s", message);
				break;
		}

		unlock_log();
	}
}

int	zbx_get_log_type(const char *logtype)
{
	const char	*logtypes[] = {ZBX_OPTION_LOGTYPE_SYSTEM, ZBX_OPTION_LOGTYPE_FILE, ZBX_OPTION_LOGTYPE_CONSOLE};
	size_t		i;

	for (i = 0; i < ARRSIZE(logtypes); i++)
	{
		if (0 == strcmp(logtype, logtypes[i]))
			return i + 1;
	}

	return LOG_TYPE_UNDEFINED;
}

int	zbx_validate_log_parameters(ZBX_TASK_EX *task)
{
	if (LOG_TYPE_UNDEFINED == CONFIG_LOG_TYPE)
	{
		zabbix_log(LOG_LEVEL_CRIT, "invalid \"LogType\" configuration parameter: '%s'", CONFIG_LOG_TYPE_STR);
		return FAIL;
	}

	if (LOG_TYPE_CONSOLE == CONFIG_LOG_TYPE && 0 == (task->flags & ZBX_TASK_FLAG_FOREGROUND) &&
			ZBX_TASK_START == task->task)
	{
		zabbix_log(LOG_LEVEL_CRIT, "\"LogType\" \"console\" parameter can only be used with the"
				" -f (--foreground) command line option");
		return FAIL;
	}

	if (LOG_TYPE_FILE == CONFIG_LOG_TYPE && (NULL == CONFIG_LOG_FILE || '\0' == *CONFIG_LOG_FILE))
	{
		zabbix_log(LOG_LEVEL_CRIT, "\"LogType\" \"file\" parameter requires \"LogFile\" parameter to be set");
		return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Comments: replace strerror to print also the error number                  *
 *                                                                            *
 ******************************************************************************/
char	*zbx_strerror(int errnum)
{
	/* !!! Attention: static !!! Not thread-safe for Win32 */
	static char	utf8_string[ZBX_MESSAGE_BUF_SIZE];

	zbx_snprintf(utf8_string, sizeof(utf8_string), "[%d] %s", errnum, strerror(errnum));

	return utf8_string;
}

char	*strerror_from_system(unsigned long error)
{
#ifdef _WINDOWS
	size_t		offset = 0;
	wchar_t		wide_string[ZBX_MESSAGE_BUF_SIZE];
	/* !!! Attention: static !!! Not thread-safe for Win32 */
	static char	utf8_string[ZBX_MESSAGE_BUF_SIZE];

	offset += zbx_snprintf(utf8_string, sizeof(utf8_string), "[0x%08lX] ", error);

	/* we don't know the inserts so we pass NULL and enable appropriate flag */
	if (0 == FormatMessage(FORMAT_MESSAGE_FROM_SYSTEM | FORMAT_MESSAGE_IGNORE_INSERTS, NULL, error,
			MAKELANGID(LANG_NEUTRAL, SUBLANG_DEFAULT), wide_string, ZBX_MESSAGE_BUF_SIZE, NULL))
	{
		zbx_snprintf(utf8_string + offset, sizeof(utf8_string) - offset,
				"unable to find message text [0x%08lX]", GetLastError());

		return utf8_string;
	}

	zbx_unicode_to_utf8_static(wide_string, utf8_string + offset, (int)(sizeof(utf8_string) - offset));

	zbx_rtrim(utf8_string, "\r\n ");

	return utf8_string;
#else
	return zbx_strerror(errno);
#endif	/* _WINDOWS */
}

#ifdef _WINDOWS
char	*strerror_from_module(unsigned long error, const wchar_t *module)
{
	size_t		offset = 0;
	wchar_t		wide_string[ZBX_MESSAGE_BUF_SIZE];
	HMODULE		hmodule;
	/* !!! Attention: static !!! not thread-safe for Win32 */
	static char	utf8_string[ZBX_MESSAGE_BUF_SIZE];

	*utf8_string = '\0';
	hmodule = GetModuleHandle(module);

	offset += zbx_snprintf(utf8_string, sizeof(utf8_string), "[0x%08lX] ", error);

	/* we don't know the inserts so we pass NULL and enable appropriate flag */
	if (0 == FormatMessage(FORMAT_MESSAGE_FROM_HMODULE | FORMAT_MESSAGE_IGNORE_INSERTS, hmodule, error,
			MAKELANGID(LANG_NEUTRAL, SUBLANG_DEFAULT), wide_string, sizeof(wide_string), NULL))
	{
		zbx_snprintf(utf8_string + offset, sizeof(utf8_string) - offset,
				"unable to find message text: %s", strerror_from_system(GetLastError()));

		return utf8_string;
	}

	zbx_unicode_to_utf8_static(wide_string, utf8_string + offset, (int)(sizeof(utf8_string) - offset));

	zbx_rtrim(utf8_string, "\r\n ");

	return utf8_string;
}
#endif	/* _WINDOWS */
