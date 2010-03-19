/*
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/


#include "common.h"
#include "log.h"

#include "mutexs.h"
#include "threads.h"

static	char log_filename[MAX_STRING_LEN];

static	int log_type = LOG_TYPE_UNDEFINED;
static	int log_level =
#if defined(DEBUG)
	LOG_LEVEL_DEBUG;
#else
	LOG_LEVEL_WARNING;
#endif /* DEBUG */

static ZBX_MUTEX log_file_access;

#if defined(_WINDOWS)

#include "messages.h"

#include "service.h"

static HANDLE system_log_handle = INVALID_HANDLE_VALUE;

#endif /* _WINDOWS */

extern	char title_message[]; /* for nice logging into syslog */

#if !defined(_WINDOWS)

void redirect_std(const char *filename)
{
	int fd;
	const char default_file[] = "/dev/null";
	const char *out_file = default_file;
	int open_flags = O_WRONLY;

	close(fileno(stdin));
	open(default_file, O_RDONLY);    /* stdin, normally fd==0 */

	if( filename && *filename)
	{
		out_file = filename;
		open_flags |= O_CREAT | O_APPEND;
	}

	if ( -1 != (fd = open(out_file, open_flags, 0666)) )
	{
		if(-1 == dup2(fd, fileno(stderr)))
			zbx_error("Cannot redirect stderr to [%s]", filename);

		if(-1 == dup2(fd, fileno(stdout)))
			zbx_error("Cannot redirect stdout to [%s]", filename);
		close(fd);
	}
	else
	{
		zbx_error("Cannot open [%s] [%s]", filename, strerror(errno));
		exit(FAIL);
	}
}

#endif /* not _WINDOWS */

int zabbix_open_log(int type, int level, const char *filename)
{
	FILE	*log_file = NULL;
#if defined(_WINDOWS)
	LPTSTR	wevent_source;
#endif

	log_level = level;

	if(LOG_LEVEL_EMPTY == level)
	{
		return	SUCCEED;
	}

	if(LOG_TYPE_FILE == type && NULL == filename)
	{
		type = LOG_TYPE_SYSLOG;
	}

	if(LOG_TYPE_SYSLOG == type)
	{
		log_type = LOG_TYPE_SYSLOG;

#if defined(_WINDOWS)
		wevent_source = zbx_utf8_to_unicode(ZABBIX_EVENT_SOURCE);
		system_log_handle = RegisterEventSource(NULL, wevent_source);
		zbx_free(wevent_source);
#else /* not _WINDOWS */

		openlog(title_message, LOG_PID, LOG_DAEMON);

#endif /* _WINDOWS */
	}

	else if(LOG_TYPE_FILE == type)
	{
		if(strlen(filename) >= MAX_STRING_LEN)
		{
			zbx_error("Too long path for logfile.");
			exit(FAIL);
		}

		if(ZBX_MUTEX_ERROR == zbx_mutex_create_force(&log_file_access, ZBX_MUTEX_LOG))
		{
			zbx_error("Unable to create mutex for log file");
			exit(FAIL);
		}

		if(NULL == (log_file = fopen(filename,"a+")))
		{
			zbx_error("Unable to open log file [%s] [%s]", filename, strerror(errno));
			exit(FAIL);
		}

		log_type = LOG_TYPE_FILE;
		strscpy(log_filename,filename);
		zbx_fclose(log_file);
	}
	else /* LOG_TYPE_UNDEFINED == type */
	{
		/* Not supported logging type */
		/*
		if(ZBX_MUTEX_ERROR == zbx_mutex_create_force(&log_file_access, ZBX_MUTEX_LOG))
		{
			zbx_error("Unable to create mutex for log file");
			return	FAIL;
		}

		zbx_error("Not supported loggin type [%d]", type);
		return	FAIL;
		*/
	}

	return	SUCCEED;
}

void zabbix_close_log(void)
{
	if(LOG_TYPE_SYSLOG == log_type)
	{
#if defined(_WINDOWS)

		if(system_log_handle)
			DeregisterEventSource(system_log_handle);

#else /* not _WINDOWS */

		closelog();

#endif /* _WINDOWS */
	}
	else if(log_type == LOG_TYPE_FILE)
	{
		zbx_mutex_destroy(&log_file_access);
	}
	else
	{
		/* Not supported logging type */
		/*
		zbx_mutex_destroy(&log_file_access);
		*/
	}
}

void zabbix_set_log_level(int level)
{
	log_level = level;
}

void zabbix_errlog(zbx_err_codes_t err, ...)
{
#define ERR_STRING_LEN	256
	const char	*msg;
	char		*s = NULL;
	va_list		ap;

	switch (err) {
	case ERR_Z3001: msg = "Connection to database '%s' failed: [%d] %s"; break;
	case ERR_Z3002: msg = "Cannot create database '%s': [%d] %s"; break;
	case ERR_Z3003: msg = "No connection to the database."; break;
	case ERR_Z3004: msg = "Cannot close database: [%d] %s"; break;
	case ERR_Z3005: msg = "Query failed: [%d] %s [%s]"; break;
	case ERR_Z3006: msg = "Fetch failed: [%d] %s"; break;
	default: msg = "Unknown error";
	}

	va_start(ap, err);
	s = zbx_dvsprintf(s, msg, ap);
	va_end(ap);

	zabbix_log(LOG_LEVEL_ERR, "[Z%04d] %s", err, s);

	zbx_free(s);
}

void __zbx_zabbix_log(int level, const char *fmt, ...)
{
	FILE *log_file = NULL;

	char	message[MAX_BUF_LEN];

	struct	tm	*tm;
	va_list		args;

	long		milliseconds;

	struct	stat	buf;

	static zbx_uint64_t	old_size = 0;

	char	filename_old[MAX_STRING_LEN];
#if defined(_WINDOWS)
        struct _timeb current_time;

	WORD	wType;
	wchar_t	thread_id[20], *strings[2];

#else /* not _WINDOWS */
	struct timeval	current_time;
#endif /* _WINDOWS */

	if( (level != LOG_LEVEL_INFORMATION) && ((level > log_level) || (LOG_LEVEL_EMPTY == level)) )
	{
		return;
	}

	if(LOG_TYPE_FILE == log_type)
	{
		zbx_mutex_lock(&log_file_access);

		log_file = fopen(log_filename,"a+");

		if(NULL != log_file)
		{

#if defined(_WINDOWS)
		        _ftime(&current_time);

			tm = localtime(&current_time.time);
			milliseconds = current_time.millitm;
#else /* not _WINDOWS */

			gettimeofday(&current_time,NULL);

			tm = localtime(&current_time.tv_sec);

			milliseconds = current_time.tv_usec/1000;
#endif /* _WINDOWS */

			fprintf(log_file,
				"%6li:%.4d%.2d%.2d:%.2d%.2d%.2d.%03ld ",
				zbx_get_thread_id(),
				tm->tm_year+1900,
				tm->tm_mon+1,
				tm->tm_mday,
				tm->tm_hour,
				tm->tm_min,
				tm->tm_sec,
				milliseconds
				);

			va_start(args,fmt);

			vfprintf(log_file,fmt, args);

			va_end(args);

			fprintf(log_file,"\n");
			zbx_fclose(log_file);

			if(CONFIG_LOG_FILE_SIZE != 0 && stat(log_filename,&buf) == 0)
			{
				if(buf.st_size > CONFIG_LOG_FILE_SIZE*1024*1024)
				{
					strscpy(filename_old,log_filename);
					zbx_strlcat(filename_old,".old",MAX_STRING_LEN);
					remove(filename_old);
					if(rename(log_filename,filename_old) != 0)
					{
						zbx_error("Can't rename log file [%s] to [%s] [%s]", log_filename, filename_old, strerror(errno));
					}
				}

				if (old_size > (zbx_uint64_t)buf.st_size)
				{
					redirect_std(log_filename);
				}

				old_size = (zbx_uint64_t)buf.st_size;
			}
		}

		zbx_mutex_unlock(&log_file_access);

		return;
	}

	memset(message, 0, sizeof(message));
	va_start(args, fmt);
	vsnprintf(message, sizeof(message)-1, fmt, args);
	va_end(args);

	if(LOG_TYPE_SYSLOG == log_type)
	{
#if defined(_WINDOWS)
		switch(level)
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

		zbx_wsnprintf(thread_id, sizeof(thread_id)/sizeof(wchar_t), TEXT("[%li]: "),
				zbx_get_thread_id());
		strings[0] = thread_id;
		strings[1] = zbx_utf8_to_unicode(message);

		ReportEvent(
			system_log_handle,
			wType,
			0,
			MSG_ZABBIX_MESSAGE,
			NULL,
			sizeof(*strings)-1,
			0,
			strings,
			NULL);

		zbx_free(strings[1]);
		
#else /* not _WINDOWS */
		
		/* for nice printing into syslog */		
		switch(level)
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

#endif /* _WINDOWS */
	}
	else /* LOG_TYPE_UNDEFINED == log_type */
	{
		zbx_mutex_lock(&log_file_access);

		switch(level)
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

/*
 * Get system error string by call to FormatMessage
 */
 #define ZBX_MESSAGE_BUF_SIZE	1024

char *strerror_from_system(unsigned long error)
{
#if defined(_WINDOWS)

	TCHAR		wide_string[ZBX_MESSAGE_BUF_SIZE];
	static char	utf8_string[ZBX_MESSAGE_BUF_SIZE];  /* !!! Attention static !!! not thread safely - Win32*/

	if (0 == FormatMessage(FORMAT_MESSAGE_FROM_SYSTEM, NULL, error,
			MAKELANGID(LANG_NEUTRAL, SUBLANG_DEFAULT), wide_string, sizeof(wide_string), NULL))
	{
		zbx_snprintf(utf8_string, sizeof(utf8_string), "3. MSG 0x%08X - Unable to find message text [0x%X]",
				error, GetLastError());
		return utf8_string;
	}

	if (FAIL == zbx_unicode_to_utf8_static(wide_string, utf8_string, sizeof(utf8_string)))
		*utf8_string = '\0';

	zbx_rtrim(utf8_string, "\r\n ");

	return utf8_string;

#else /* not _WINDOWS */

	return strerror(errno);

#endif /* _WINDOWS */
}

/*
 * Get system error string by call to FormatMessage
 */

#if defined(_WINDOWS)
char *strerror_from_module(unsigned long error, LPCTSTR module)
{
	TCHAR		wide_string[ZBX_MESSAGE_BUF_SIZE];
	static char	utf8_string[ZBX_MESSAGE_BUF_SIZE];  /* !!! Attention static !!! not thread safely - Win32*/
	char		*strings[2];
	HMODULE		hmodule;

	memset(strings, 0, sizeof(char *) * 2);
	*utf8_string = '\0';
	hmodule = GetModuleHandle(module);

	if (0 == FormatMessage(FORMAT_MESSAGE_FROM_HMODULE | FORMAT_MESSAGE_ARGUMENT_ARRAY, hmodule, error,
			MAKELANGID(LANG_NEUTRAL, SUBLANG_DEFAULT), wide_string, sizeof(wide_string), strings))
	{
		zbx_snprintf(utf8_string, sizeof(utf8_string), "3. MSG 0x%08X - Unable to find message text [%s]",
				error, strerror_from_system(GetLastError()));
		return utf8_string;
	}

	if (FAIL == zbx_unicode_to_utf8_static(wide_string, utf8_string, sizeof(utf8_string)))
		*utf8_string = '\0';

	zbx_rtrim(utf8_string, "\r\n ");

	return utf8_string;
}
#endif /* _WINDOWS */
