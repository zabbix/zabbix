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
	FILE *log_file = NULL;

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

		system_log_handle = RegisterEventSource(NULL, ZABBIX_EVENT_SOURCE);

#else /* not _WINDOWS */

        	openlog("zabbix_suckerd", LOG_PID, LOG_USER);
        	setlogmask(LOG_UPTO(LOG_WARNING));

#endif /* _WINDOWS */
	}

	else if(LOG_TYPE_FILE == type)
	{
		if(strlen(filename) >= MAX_STRING_LEN)
		{
			zbx_error("To large path for logfile.");
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
	else
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
		/* Not supported loggin type */
		/*
		zbx_mutex_destroy(&log_file_access);
		*/
	}
}

void zabbix_set_log_level(int level)
{
	log_level = level;
}

void __zbx_zabbix_log(int level, const char *fmt, ...)
{
#ifdef TEST
	time_t	t;
	struct	tm	*tm;
	va_list ap;
	
		t=time(NULL);
		tm=localtime(&t);
		printf("%.6li:%.4d%.2d%.2d:%.2d%.2d%.2d ",zbx_get_thread_id(),tm->tm_year+1900,tm->tm_mon+1,tm->tm_mday,tm->tm_hour,tm->tm_min,tm->tm_sec);
		va_start(ap,fmt);
		vprintf(fmt,ap);
		va_end(ap);

		printf("\n");
		return;
#else /* TEST */
	
	FILE *log_file = NULL;

	char	message[MAX_BUF_LEN];

	time_t		t;
	struct	tm	*tm;
	va_list		args;

	struct	stat	buf;

	static size_t	old_size = 0;

	char	filename_old[MAX_STRING_LEN];
#if defined(_WINDOWS)

	WORD	wType;
	char	thread_id[20];
	char	*(strings[]) = {thread_id, message, NULL};
	
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
			t = time(NULL);
			tm = localtime(&t);

			fprintf(log_file,
				"%6li:%.4d%.2d%.2d:%.2d%.2d%.2d ",
				zbx_get_thread_id(),
				tm->tm_year+1900,
				tm->tm_mon+1,
				tm->tm_mday,
				tm->tm_hour,
				tm->tm_min,
				tm->tm_sec
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

				if(old_size > (size_t)(buf.st_size))
				{
					redirect_std(log_filename);
				}

				old_size = buf.st_size;
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
		t = time(NULL);
		tm = localtime(&t);

		memset(thread_id, 0, sizeof(thread_id));
		zbx_snprintf(thread_id, sizeof(thread_id),"[%li]: ",zbx_get_thread_id());

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

#else /* not _WINDOWS */

		syslog(LOG_DEBUG, "%s", message);
		
#endif /* _WINDOWS */
	}
	else
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
	
#endif /* TEST */

}

/*
 * Get system error string by call to FormatMessage
 */
#define ZBX_MESSAGE_BUF_SIZE	1024

char *strerror_from_system(unsigned long error)
{
#if defined(_WINDOWS)

	static char buffer[ZBX_MESSAGE_BUF_SIZE];  /* !!! Attention static !!! not thread safely - Win32*/

	memset(buffer, 0, sizeof(buffer));

	if(FormatMessage(
		FORMAT_MESSAGE_FROM_SYSTEM, 
		NULL, 
		error,
		MAKELANGID(LANG_NEUTRAL, SUBLANG_DEFAULT), 
		buffer, 
		sizeof(buffer), 
		NULL) == 0)
	{
		zbx_snprintf(buffer, sizeof(buffer), "3. MSG 0x%08X - Unable to find message text [0x%X]", error , GetLastError());
	}

	return buffer;

#else /* not _WINDOWS */

	return strerror(errno);

#endif /* _WINDOWS */
}

/*
 * Get system error string by call to FormatMessage
 */

char *strerror_from_module(unsigned long error, const char *module)
{
#if defined(_WINDOWS)

	static char buffer[ZBX_MESSAGE_BUF_SIZE]; /* !!! Attention static !!! not thread safely - Win32*/
	char *strings[2];

	memset(strings,0,sizeof(char *)*2);
	memset(buffer, 0, sizeof(buffer));

	if (FormatMessage(
		FORMAT_MESSAGE_FROM_HMODULE | FORMAT_MESSAGE_ARGUMENT_ARRAY,
		module ? GetModuleHandle(module) : NULL,
		error,
		MAKELANGID(LANG_NEUTRAL, SUBLANG_DEFAULT), /* Default language */
		(LPTSTR)buffer,
		sizeof(buffer),
		strings) == 0)
	{
		zbx_snprintf(buffer, sizeof(buffer), "3. MSG 0x%08X - Unable to find message text [%s]", error , strerror_from_system(GetLastError()));
	}

	return (char *)buffer;

#else /* not _WINDOWS */

	return strerror(errno);

#endif /* _WINDOWS */

}
