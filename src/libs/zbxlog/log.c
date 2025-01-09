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

#include "zbxlog.h"

#include "zbxmutexs.h"
#include "zbxstr.h"
#include "zbxtime.h"

#ifdef _WINDOWS
#	include "messages.h"
#	include "zbxwinservice.h"
#	include <strsafe.h> /* StringCchPrintf */
static HANDLE		system_log_handle = INVALID_HANDLE_VALUE;
#endif

static char		log_filename[MAX_STRING_LEN];
static int		log_type = ZBX_LOG_TYPE_UNDEFINED;
static zbx_mutex_t	log_access = ZBX_MUTEX_NULL;

static int		config_log_file_size = -1;	/* max log file size in MB */

static int	get_config_log_file_size(void)
{
	if (-1 != config_log_file_size)
		return config_log_file_size;

	THIS_SHOULD_NEVER_HAPPEN;
	exit(EXIT_FAILURE);
}

#ifdef _WINDOWS
#	define LOCK_LOG		zbx_mutex_lock(log_access)
#	define UNLOCK_LOG	zbx_mutex_unlock(log_access)
#else
#	define LOCK_LOG		lock_log()
#	define UNLOCK_LOG	unlock_log()
#endif

#ifdef _WINDOWS
#	define STDIN_FILENO	_fileno(stdin)
#	define STDOUT_FILENO	_fileno(stdout)
#	define STDERR_FILENO	_fileno(stderr)

#	define ZBX_DEV_NULL	"NUL"

#	define dup2(fd1, fd2)	_dup2(fd1, fd2)
#else
#	define ZBX_DEV_NULL	"/dev/null"
#endif

int	zbx_redirect_stdio(const char *filename)
{
	const char	default_file[] = ZBX_DEV_NULL;
	int		open_flags = O_WRONLY, fd;

	if (NULL != filename && '\0' != *filename)
		open_flags |= O_CREAT | O_APPEND;
	else
		filename = default_file;

	if (-1 == (fd = open(filename, open_flags, 0666)))
	{
		zbx_error("cannot open \"%s\": %s", filename, zbx_strerror(errno));
		return FAIL;
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
		return FAIL;
	}

	if (-1 == dup2(fd, STDIN_FILENO))
		zbx_error("cannot redirect stdin to \"%s\": %s", default_file, zbx_strerror(errno));

	close(fd);

	return SUCCEED;
}

static void	rotate_log(const char *filename)
{
	zbx_stat_t		buf;
	zbx_uint64_t		new_size;
	static zbx_uint64_t	old_size = ZBX_MAX_UINT64;	/* redirect stdout and stderr */
#if !defined(_WINDOWS)
	static zbx_uint64_t	st_ino, st_dev;
#endif

	if (0 != zbx_stat(filename, &buf))
	{
		zbx_redirect_stdio(filename);
		return;
	}

	new_size = buf.st_size;

	if (0 != get_config_log_file_size() && (zbx_uint64_t)get_config_log_file_size() * ZBX_MEBIBYTE < new_size)
	{
		char	filename_old[MAX_STRING_LEN];

		zbx_strscpy(filename_old, filename);
		zbx_strlcat(filename_old, ".old", MAX_STRING_LEN);
		remove(filename_old);
#ifdef _WINDOWS
		zbx_redirect_stdio(NULL);
#endif
		if (0 != rename(filename, filename_old))
		{
			FILE	*log_file = NULL;

			if (NULL != (log_file = fopen(filename, "w")))
			{
				long		milliseconds;
				struct tm	tm;

				zbx_get_time(&tm, &milliseconds, NULL);

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
						filename,
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
						filename,
						filename_old);

				zbx_fclose(log_file);

				new_size = 0;
			}
		}
		else
			new_size = 0;
	}

	if (old_size > new_size)
		zbx_redirect_stdio(filename);
#if !defined(_WINDOWS)
	else if (st_ino != buf.st_ino || st_dev != buf.st_dev)
	{
		st_ino = buf.st_ino;
		st_dev = buf.st_dev;
		zbx_redirect_stdio(filename);
	}
#endif

	old_size = new_size;
}

#ifndef _WINDOWS
static ZBX_THREAD_LOCAL sigset_t	orig_mask;

static void	lock_log(void)
{
	sigset_t	mask;

	/* block signals to prevent deadlock on log file mutex when signal handler attempts to lock log */
	sigemptyset(&mask);
	sigaddset(&mask, SIGUSR1);
	sigaddset(&mask, SIGUSR2);
	sigaddset(&mask, SIGTERM);
	sigaddset(&mask, SIGINT);
	sigaddset(&mask, SIGQUIT);
	sigaddset(&mask, SIGHUP);

	if (0 > zbx_sigmask(SIG_BLOCK, &mask, &orig_mask))
		zbx_error("cannot set signal mask to block the user signal");

	zbx_mutex_lock(log_access);
}

static void	unlock_log(void)
{
	zbx_mutex_unlock(log_access);

	if (0 > zbx_sigmask(SIG_SETMASK, &orig_mask, NULL))
		zbx_error("cannot restore signal mask");
}
#else
static void	lock_log(void)
{
#ifdef ZABBIX_AGENT
	if (0 == (ZBX_MUTEX_LOGGING_DENIED & zbx_get_thread_global_mutex_flag()))
#endif
		LOCK_LOG;
}

static void	unlock_log(void)
{
#ifdef ZABBIX_AGENT
	if (0 == (ZBX_MUTEX_LOGGING_DENIED & zbx_get_thread_global_mutex_flag()))
#endif
		UNLOCK_LOG;
}
#endif

void	zbx_handle_log(void)
{
#ifndef _WINDOWS
	zabbix_report_log_level_change();
#endif
	if (ZBX_LOG_TYPE_FILE != log_type)
		return;

	LOCK_LOG;

	rotate_log(log_filename);

	UNLOCK_LOG;
}

int	zbx_open_log(const zbx_config_log_t *log_file_cfg, int level, const char *syslog_app_name,
		const char *event_source, char **error)
{
	const char	*filename = log_file_cfg->log_file_name;
	int		type = log_file_cfg->log_type;

	log_type = type;
	zbx_set_log_level(level);
	config_log_file_size = log_file_cfg->log_file_size;

	if (ZBX_LOG_TYPE_SYSTEM == type)
	{
#ifdef _WINDOWS
		wchar_t	*wevent_source;

		wevent_source = zbx_utf8_to_unicode(event_source);
		system_log_handle = RegisterEventSource(NULL, wevent_source);
		zbx_free(wevent_source);
#else
		ZBX_UNUSED(event_source);
		openlog(syslog_app_name, LOG_PID, LOG_DAEMON);
#endif
	}
	else if (ZBX_LOG_TYPE_FILE == type)
	{
		FILE	*log_file = NULL;

		if (MAX_STRING_LEN <= strlen(filename))
		{
			*error = zbx_strdup(*error, "too long path for logfile");
			return FAIL;
		}

		if (SUCCEED != zbx_mutex_create(&log_access, ZBX_MUTEX_LOG, error))
			return FAIL;

		if (NULL == (log_file = fopen(filename, "a+")))
		{
			*error = zbx_dsprintf(*error, "unable to open log file [%s]: %s", filename,
					zbx_strerror(errno));
			return FAIL;
		}

		zbx_strscpy(log_filename, filename);
		zbx_fclose(log_file);
	}
	else if (ZBX_LOG_TYPE_CONSOLE == type || ZBX_LOG_TYPE_UNDEFINED == type)
	{
		if (SUCCEED != zbx_mutex_create(&log_access, ZBX_MUTEX_LOG, error))
		{
			*error = zbx_strdup(*error, "unable to create mutex for standard output");
			return FAIL;
		}

		fflush(stderr);
		if (-1 == dup2(STDOUT_FILENO, STDERR_FILENO))
			zbx_error("cannot redirect stderr to stdout: %s", zbx_strerror(errno));
	}
	else
	{
		*error = zbx_strdup(*error, "unknown log type");
		return FAIL;
	}

	return SUCCEED;
}

void	zbx_close_log(void)
{
	if (ZBX_LOG_TYPE_SYSTEM == log_type)
	{
#ifdef _WINDOWS
		if (NULL != system_log_handle)
			DeregisterEventSource(system_log_handle);
#else
		closelog();
#endif
	}
	else if (ZBX_LOG_TYPE_FILE == log_type || ZBX_LOG_TYPE_CONSOLE == log_type ||
			ZBX_LOG_TYPE_UNDEFINED == log_type)
	{
		zbx_mutex_destroy(&log_access);
	}

	log_type = ZBX_LOG_TYPE_UNDEFINED;
}

void	zbx_log_impl(int level, const char *fmt, va_list args)
{
	char		message[MAX_BUFFER_LEN];
#ifdef _WINDOWS
	WORD		wType;
	wchar_t		thread_id[20], *strings[2];
#else
	zabbix_report_log_level_change();
#endif

#ifndef ZBX_ZABBIX_LOG_CHECK
	if (SUCCEED != ZBX_CHECK_LOG_LEVEL(level))
		return;
#endif

	if (ZBX_LOG_TYPE_FILE == log_type)
	{
		FILE	*log_file;

		LOCK_LOG;

		if (0 != get_config_log_file_size())
			rotate_log(log_filename);

		if (NULL != (log_file = fopen(log_filename, "a+")))
		{
			long		milliseconds;
			struct tm	tm;

			zbx_get_time(&tm, &milliseconds, NULL);

			fprintf(log_file,
					"%6li:%.4d%.2d%.2d:%.2d%.2d%.2d.%03ld %s",
					zbx_get_thread_id(),
					tm.tm_year + 1900,
					tm.tm_mon + 1,
					tm.tm_mday,
					tm.tm_hour,
					tm.tm_min,
					tm.tm_sec,
					milliseconds,
					zbx_get_log_component_name()
					);

			vfprintf(log_file, fmt, args);

			fprintf(log_file, "\n");

			zbx_fclose(log_file);
		}
		else
		{
			zbx_error("failed to open log file: %s", zbx_strerror(errno));

			zbx_vsnprintf(message, sizeof(message), fmt, args);

			zbx_error("failed to write [%s] into log file", message);
		}

		UNLOCK_LOG;

		return;
	}

	if (ZBX_LOG_TYPE_CONSOLE == log_type)
	{
		long		milliseconds;
		struct tm	tm;

		LOCK_LOG;

		zbx_get_time(&tm, &milliseconds, NULL);

		fprintf(stdout,
				"%6li:%.4d%.2d%.2d:%.2d%.2d%.2d.%03ld %s",
				zbx_get_thread_id(),
				tm.tm_year + 1900,
				tm.tm_mon + 1,
				tm.tm_mday,
				tm.tm_hour,
				tm.tm_min,
				tm.tm_sec,
				milliseconds,
				zbx_get_log_component_name()
				);

		vfprintf(stdout, fmt, args);

		fprintf(stdout, "\n");

		fflush(stdout);

		UNLOCK_LOG;

		return;
	}

	zbx_vsnprintf(message, sizeof(message), fmt, args);

	if (ZBX_LOG_TYPE_SYSTEM == log_type)
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
				syslog(LOG_CRIT, "%s%s", zbx_get_log_component_name(), message);
				break;
			case LOG_LEVEL_ERR:
				syslog(LOG_ERR, "%s%s", zbx_get_log_component_name(), message);
				break;
			case LOG_LEVEL_WARNING:
				syslog(LOG_WARNING, "%s%s", zbx_get_log_component_name(), message);
				break;
			case LOG_LEVEL_DEBUG:
			case LOG_LEVEL_TRACE:
				syslog(LOG_DEBUG, "%s%s", zbx_get_log_component_name(), message);
				break;
			case LOG_LEVEL_INFORMATION:
				syslog(LOG_INFO, "%s%s", zbx_get_log_component_name(), message);
				break;
			default:
				/* LOG_LEVEL_EMPTY - print nothing */
				break;
		}

#endif	/* _WINDOWS */
	}	/* ZBX_LOG_TYPE_SYSTEM */
	else	/* ZBX_LOG_TYPE_UNDEFINED == log_type */
	{
		LOCK_LOG;

		switch (level)
		{
			case LOG_LEVEL_CRIT:
				zbx_error("ERROR: %s%s", zbx_get_log_component_name(), message);
				break;
			case LOG_LEVEL_ERR:
				zbx_error("Error: %s%s", zbx_get_log_component_name(), message);
				break;
			case LOG_LEVEL_WARNING:
				zbx_error("Warning: %s%s", zbx_get_log_component_name(), message);
				break;
			case LOG_LEVEL_DEBUG:
				zbx_error("DEBUG: %s%s", zbx_get_log_component_name(), message);
				break;
			case LOG_LEVEL_TRACE:
				zbx_error("TRACE: %s%s", zbx_get_log_component_name(), message);
				break;
			default:
				zbx_error("%s%s", zbx_get_log_component_name(), message);
				break;
		}

		UNLOCK_LOG;
	}
}

int	zbx_get_log_type(const char *logtype)
{
	const char	*logtypes[] = {ZBX_OPTION_LOGTYPE_SYSTEM, ZBX_OPTION_LOGTYPE_FILE, ZBX_OPTION_LOGTYPE_CONSOLE};
	int		i;

	for (i = 0; i < (int)ARRSIZE(logtypes); i++)
	{
		if (0 == strcmp(logtype, logtypes[i]))
			return i + 1;
	}

	return ZBX_LOG_TYPE_UNDEFINED;
}

int	zbx_validate_log_parameters(ZBX_TASK_EX *task, const zbx_config_log_t *log_file_cfg)
{
	if (ZBX_LOG_TYPE_UNDEFINED == log_file_cfg->log_type)
	{
		zabbix_log(LOG_LEVEL_CRIT, "invalid \"LogType\" configuration parameter: '%s'",
				log_file_cfg->log_type_str);
		return FAIL;
	}

	if (ZBX_LOG_TYPE_CONSOLE == log_file_cfg->log_type && 0 == (task->flags & ZBX_TASK_FLAG_FOREGROUND) &&
			ZBX_TASK_START == task->task)
	{
		zabbix_log(LOG_LEVEL_CRIT, "\"LogType\" \"console\" parameter can only be used with the"
				" -f (--foreground) command line option");
		return FAIL;
	}

	if (ZBX_LOG_TYPE_FILE == log_file_cfg->log_type && (NULL == log_file_cfg->log_file_name || '\0' ==
			*log_file_cfg->log_file_name))
	{
		zabbix_log(LOG_LEVEL_CRIT, "\"LogType\" \"file\" parameter requires \"LogFile\" parameter to be set");
		return FAIL;
	}

	return SUCCEED;
}

char	*zbx_strerror_from_system(zbx_syserror_t error)
{
#ifdef _WINDOWS
	size_t		offset = 0;
	wchar_t		wide_string[ZBX_MESSAGE_BUF_SIZE];
	/* !!! Attention: static !!! Not thread-safe for Win32 */
	static ZBX_THREAD_LOCAL char	utf8_string[ZBX_MESSAGE_BUF_SIZE];

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
#else	/* not _WINDOWS */
	ZBX_UNUSED(error);

	return zbx_strerror(errno);
#endif	/* _WINDOWS */
}

#ifdef _WINDOWS
char	*zbx_strerror_from_module(zbx_syserror_t error, const wchar_t *module)
{
	size_t		offset = 0;
	wchar_t		wide_string[ZBX_MESSAGE_BUF_SIZE];
	HMODULE		hmodule;
	/* !!! Attention: static !!! not thread-safe for Win32 */
	static ZBX_THREAD_LOCAL char	utf8_string[ZBX_MESSAGE_BUF_SIZE];

	*utf8_string = '\0';
	hmodule = GetModuleHandle(module);

	offset += zbx_snprintf(utf8_string, sizeof(utf8_string), "[0x%08lX] ", error);

	/* we don't know the inserts so we pass NULL and enable appropriate flag */
	if (0 == FormatMessage(FORMAT_MESSAGE_FROM_HMODULE | FORMAT_MESSAGE_IGNORE_INSERTS, hmodule, error,
			MAKELANGID(LANG_NEUTRAL, SUBLANG_DEFAULT), wide_string, sizeof(wide_string), NULL))
	{
		zbx_snprintf(utf8_string + offset, sizeof(utf8_string) - offset,
				"unable to find message text: %s", zbx_strerror_from_system(GetLastError()));

		return utf8_string;
	}

	zbx_unicode_to_utf8_static(wide_string, utf8_string + offset, (int)(sizeof(utf8_string) - offset));

	zbx_rtrim(utf8_string, "\r\n ");

	return utf8_string;
}
#endif	/* _WINDOWS */

/******************************************************************************
 *                                                                            *
 * Purpose: log the message optionally appending to a string buffer           *
 *                                                                            *
 * Parameters: level      - [IN] log level                                    *
 *             out        - [OUT] output buffer (optional)                    *
 *             out_alloc  - [OUT] output buffer size                          *
 *             out_offset - [OUT] output buffer offset                        *
 *             format     - [IN] format string                                *
 *                                                                            *
 * Return value: SUCCEED - socket was successfully opened                     *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
void	zbx_strlog_alloc(int level, char **out, size_t *out_alloc, size_t *out_offset, const char *format, ...)
{
	va_list	args;
	size_t	len;
	char	*buf;

	if (SUCCEED != ZBX_CHECK_LOG_LEVEL(level) && NULL == out)
		return;

#if defined(__hpux)
	if (SUCCEED != zbx_hpux_vsnprintf_is_c99())
	{
#define INITIAL_ALLOC_LEN	128

		len = INITIAL_ALLOC_LEN;
		buf = (char *)zbx_malloc(NULL, len);

		while (1)
		{
			/* try printing and extending buffer until the buffer is large enough to */
			/* store all data and 2 free bytes remain for adding '\n\0' */
			int	bytes_written;

			va_start(args, format);
			bytes_written = vsnprintf(buf, len, format, args);
			va_end(args);

			if (0 <= bytes_written && 2 <= len - (size_t)bytes_written)
			{
				len = bytes_written;
				goto finish;
			}

			if (-1 == bytes_written || (0 <= bytes_written && 2 > len - bytes_written))
			{
				len *= 2;
				buf = (char *)zbx_realloc(buf, len);
				continue;
			}

			zabbix_log(LOG_LEVEL_CRIT, "vsnprintf() returned %d", bytes_written);
			THIS_SHOULD_NEVER_HAPPEN;
			exit(EXIT_FAILURE);
#undef INITIAL_ALLOC_LEN
		}
	}
	/* HP-UX vsnprintf() looks C99-compliant, proceed with common implementation */
#endif
	va_start(args, format);

	/* zbx_vsnprintf_check_len() cannot return negative result */
	len = (size_t)zbx_vsnprintf_check_len(format, args) + 2;

	va_end(args);

	buf = (char *)zbx_malloc(NULL, len);

	va_start(args, format);
	len = zbx_vsnprintf(buf, len, format, args);
	va_end(args);

#if defined(__hpux)
finish:
#endif
	if (SUCCEED == ZBX_CHECK_LOG_LEVEL(level))
		zabbix_log(level, "%s", buf);

	if (NULL != out)
	{
		buf[0] = (char)toupper((unsigned char)buf[0]);
		buf[len++] = '\n';
		buf[len] = '\0';

		zbx_strcpy_alloc(out, out_alloc, out_offset, buf);
	}

	zbx_free(buf);
}

/* Since 2.26 the GNU C Library will detect when /etc/resolv.conf has been modified and reload the changed */
/* configuration. For performance reasons manual reloading should be avoided when unnecessary. */
#if !defined(_WINDOWS) && defined(HAVE_RESOLV_H) && defined(__GLIBC__) && __GLIBC__ == 2 && __GLIBC_MINOR__ < 26
/******************************************************************************
 *                                                                            *
 * Purpose: react to "/etc/resolv.conf" update                                *
 *                                                                            *
 * Comments: it is intended to call this function in the end of each process  *
 *           main loop. The purpose of calling it at the end (instead of the  *
 *           beginning of main loop) is to let the first initialization of    *
 *           libc resolver proceed internally.                                *
 *                                                                            *
 ******************************************************************************/
static void	update_resolver_conf(void)
{
#define ZBX_RESOLV_CONF_FILE	"/etc/resolv.conf"

	static time_t	mtime = 0;
	zbx_stat_t	buf;

	if (0 == zbx_stat(ZBX_RESOLV_CONF_FILE, &buf) && mtime != buf.st_mtime)
	{
		mtime = buf.st_mtime;

		if (0 != res_init())
			zabbix_log(LOG_LEVEL_WARNING, "update_resolver_conf(): res_init() failed");
	}

#undef ZBX_RESOLV_CONF_FILE
}
#endif

/******************************************************************************
 *                                                                            *
 * Purpose: throttling of update "/etc/resolv.conf" and "stdio" to the new    *
 *          log file after rotation                                           *
 *                                                                            *
 * Parameters: time_now - [IN] time for compare in seconds                    *
 *                                                                            *
 ******************************************************************************/
void	__zbx_update_env(double time_now)
{
	static double	time_update = 0;

	/* handle /etc/resolv.conf update and log rotate less often than once a second */
	if (1.0 < time_now - time_update)
	{
		time_update = time_now;
		zbx_handle_log();
#if !defined(_WINDOWS) && defined(HAVE_RESOLV_H) && defined(__GLIBC__) && __GLIBC__ == 2 && __GLIBC_MINOR__ < 26
		update_resolver_conf();
#endif
	}
}
