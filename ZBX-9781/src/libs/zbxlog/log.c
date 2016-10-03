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
#ifdef _WINDOWS
#	include "messages.h"
#	include "service.h"
static HANDLE		system_log_handle = INVALID_HANDLE_VALUE;
#endif

static char		log_filename[MAX_STRING_LEN];
static int		log_type = LOG_TYPE_UNDEFINED;
static ZBX_MUTEX	log_file_access = ZBX_MUTEX_NULL;
#ifdef DEBUG
static int		log_level = LOG_LEVEL_DEBUG;
#else
static int		log_level = LOG_LEVEL_WARNING;
#endif

#define ZBX_MESSAGE_BUF_SIZE	1024

#if !defined(_WINDOWS)
void	redirect_std(const char *filename)
{
	int		fd;
	const char	default_file[] = "/dev/null";
	const char	*out_file = default_file;
	int		open_flags = O_WRONLY;

	close(STDIN_FILENO);
	open(default_file, O_RDONLY);	/* stdin, normally fd==0 */

	if (NULL != filename && '\0' != *filename)
	{
		out_file = filename;
		open_flags |= O_CREAT | O_APPEND;
	}

	if (-1 != (fd = open(out_file, open_flags, 0666)))
	{
		if (-1 == dup2(fd, STDERR_FILENO))
			zbx_error("cannot redirect stderr to [%s]", filename);

		if (-1 == dup2(fd, STDOUT_FILENO))
			zbx_error("cannot redirect stdout to [%s]", filename);

		close(fd);
	}
	else
	{
		zbx_error("cannot open [%s]: %s", filename, zbx_strerror(errno));
		exit(FAIL);
	}
}
#endif	/* not _WINDOWS */

int zabbix_open_log(int type, int level, const char *filename)
{
	FILE	*log_file = NULL;
#ifdef _WINDOWS
	LPTSTR	wevent_source;
#endif

	log_level = level;

	if (LOG_LEVEL_EMPTY == level)
		return SUCCEED;

	if (LOG_TYPE_FILE == type && NULL == filename)
		type = LOG_TYPE_SYSLOG;

	if (LOG_TYPE_SYSLOG == type)
	{
		log_type = LOG_TYPE_SYSLOG;

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
			exit(FAIL);
		}

		if (ZBX_MUTEX_ERROR == zbx_mutex_create_force(&log_file_access, ZBX_MUTEX_LOG))
		{
			zbx_error("unable to create mutex for log file");
			exit(FAIL);
		}

		if (NULL == (log_file = fopen(filename, "a+")))
		{
			zbx_error("unable to open log file [%s]: %s", filename, zbx_strerror(errno));
			exit(FAIL);
		}

		log_type = LOG_TYPE_FILE;
		strscpy(log_filename, filename);
		zbx_fclose(log_file);
	}

	return SUCCEED;
}

void zabbix_close_log()
{
	if (LOG_TYPE_SYSLOG == log_type)
	{
#ifdef _WINDOWS
		if (NULL != system_log_handle)
			DeregisterEventSource(system_log_handle);
#else
		closelog();
#endif
	}
	else if (LOG_TYPE_FILE == log_type)
	{
		zbx_mutex_destroy(&log_file_access);
	}
}

void zabbix_set_log_level(int level)
{
	log_level = level;
}

void zabbix_errlog(zbx_err_codes_t err, ...)
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

void __zbx_zabbix_log(int level, const char *fmt, ...)
{
	FILE			*log_file = NULL;
	char			message[MAX_BUFFER_LEN], filename_old[MAX_STRING_LEN];
	long			milliseconds;
	static zbx_uint64_t	old_size = 0;
	va_list			args;
	struct tm		*tm;
	zbx_stat_t		buf;
#ifdef _WINDOWS
	struct _timeb		current_time;
	WORD			wType;
	wchar_t			thread_id[20], *strings[2];
#else
	struct timeval		current_time;
#endif

	if (LOG_LEVEL_INFORMATION != level && (level > log_level || LOG_LEVEL_EMPTY == level))
		return;

	if (LOG_TYPE_FILE == log_type)
	{
		zbx_mutex_lock(&log_file_access);

		if (0 != CONFIG_LOG_FILE_SIZE && 0 == zbx_stat(log_filename, &buf))
		{
			if (CONFIG_LOG_FILE_SIZE * ZBX_MEBIBYTE < buf.st_size)
			{
				strscpy(filename_old, log_filename);
				zbx_strlcat(filename_old, ".old", MAX_STRING_LEN);
				remove(filename_old);

				if (0 != rename(log_filename, filename_old))
				{
					log_file = fopen(log_filename, "w");

					if (NULL != log_file)
					{
#ifdef _WINDOWS
						_ftime(&current_time);
						tm = localtime(&current_time.time);
						milliseconds = current_time.millitm;
#else
						gettimeofday(&current_time,NULL);
						tm = localtime(&current_time.tv_sec);
						milliseconds = current_time.tv_usec / 1000;
#endif
						fprintf(log_file, "%6li:%.4d%.2d%.2d:%.2d%.2d%.2d.%03ld"
								" cannot rename log file \"%s\" to \"%s\": %s\n",
								zbx_get_thread_id(),
								tm->tm_year + 1900,
								tm->tm_mon + 1,
								tm->tm_mday,
								tm->tm_hour,
								tm->tm_min,
								tm->tm_sec,
								milliseconds,
								log_filename,
								filename_old,
								zbx_strerror(errno));

						fprintf(log_file, "%6li:%.4d%.2d%.2d:%.2d%.2d%.2d.%03ld"
								" Logfile \"%s\" size reached configured limit"
								" LogFileSize. Renaming the logfile to \"%s\" and"
								" starting a new logfile failed. The logfile"
								" was truncated and started from beginning.\n",
								zbx_get_thread_id(),
								tm->tm_year + 1900,
								tm->tm_mon + 1,
								tm->tm_mday,
								tm->tm_hour,
								tm->tm_min,
								tm->tm_sec,
								milliseconds,
								log_filename,
								filename_old);

						zbx_fclose(log_file);
					}
				}
			}

			if (old_size > (zbx_uint64_t)buf.st_size)
				redirect_std(log_filename);

			old_size = (zbx_uint64_t)buf.st_size;
		}

		log_file = fopen(log_filename,"a+");

		if (NULL != log_file)
		{
#ifdef _WINDOWS
		        _ftime(&current_time);
			tm = localtime(&current_time.time);
			milliseconds = current_time.millitm;
#else
			gettimeofday(&current_time,NULL);
			tm = localtime(&current_time.tv_sec);
			milliseconds = current_time.tv_usec / 1000;
#endif
			fprintf(log_file,
				"%6li:%.4d%.2d%.2d:%.2d%.2d%.2d.%03ld ",
				zbx_get_thread_id(),
				tm->tm_year + 1900,
				tm->tm_mon + 1,
				tm->tm_mday,
				tm->tm_hour,
				tm->tm_min,
				tm->tm_sec,
				milliseconds
				);

			va_start(args, fmt);
			vfprintf(log_file, fmt, args);
			va_end(args);

			fprintf(log_file, "\n");
			zbx_fclose(log_file);
		}

		zbx_mutex_unlock(&log_file_access);

		return;
	}

	va_start(args, fmt);
	zbx_vsnprintf(message, sizeof(message), fmt, args);
	va_end(args);

	if (LOG_TYPE_SYSLOG == log_type)
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

		zbx_wsnprintf(thread_id, sizeof(thread_id) / sizeof(wchar_t), TEXT("[%li]: "), zbx_get_thread_id());
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
		zbx_mutex_lock(&log_file_access);

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
			default:
				zbx_error("%s", message);
				break;
		}

		zbx_mutex_unlock(&log_file_access);
	}
}

/******************************************************************************
 *                                                                            *
 * Comments: replace strerror to print also the error number                  *
 *                                                                            *
 ******************************************************************************/
char *zbx_strerror(int errnum)
{
	static char	utf8_string[ZBX_MESSAGE_BUF_SIZE];	/* !!! Attention: static !!! Not thread-safe for Win32 */

	zbx_snprintf(utf8_string, sizeof(utf8_string), "[%d] %s", errnum, strerror(errnum));

	return utf8_string;
}

char *strerror_from_system(unsigned long error)
{
#ifdef _WINDOWS
	size_t		offset = 0;
	TCHAR		wide_string[ZBX_MESSAGE_BUF_SIZE];
	static char	utf8_string[ZBX_MESSAGE_BUF_SIZE];	/* !!! Attention: static !!! Not thread-safe for Win32 */

	offset += zbx_snprintf(utf8_string, sizeof(utf8_string), "[0x%08lX] ", error);

	/* we don't know the inserts so we pass NULL and enable appropriate flag */
	if (0 == FormatMessage(FORMAT_MESSAGE_FROM_SYSTEM | FORMAT_MESSAGE_IGNORE_INSERTS, NULL, error,
			MAKELANGID(LANG_NEUTRAL, SUBLANG_DEFAULT), wide_string, sizeof(wide_string), NULL))
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
char	*strerror_from_module(unsigned long error, LPCTSTR module)
{
	size_t		offset = 0;
	TCHAR		wide_string[ZBX_MESSAGE_BUF_SIZE];
	static char	utf8_string[ZBX_MESSAGE_BUF_SIZE];	/* !!! Attention: static !!! not thread-safe for Win32 */
	HMODULE		hmodule;

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
