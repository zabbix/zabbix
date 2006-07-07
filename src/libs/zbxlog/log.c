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
static	int log_level = LOG_LEVEL_ERR;

static ZBX_MUTEX log_file_access;

#if defined(WIN32)

#include "messages.h"
#include "service.h"

static HANDLE system_log_handle = INVALID_HANDLE_VALUE;

#endif

int zabbix_open_log(int type, int level, const char *filename)
{
	FILE *log_file = NULL;

	zbx_error("Log file is '%s'",filename);


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

#if defined(WIN32)

		system_log_handle = RegisterEventSource(NULL, ZABBIX_EVENT_SOURCE);

#else /* not WIN32 */

        	openlog("zabbix_suckerd", LOG_PID, LOG_USER);
        	setlogmask(LOG_UPTO(LOG_WARNING));

#endif /* WIN32 */
	}

	else if(LOG_TYPE_FILE == type)
	{
		if(strlen(filename) >= MAX_STRING_LEN)
		{
			zbx_error("To large path for logfile.");
			return	FAIL;
		}

		if(ZBX_MUTEX_ERROR == zbx_mutex_create(&log_file_access, "log"))
		{
			zbx_error("Unable to create mutex for log file");
			return	FAIL;
		}
		
		if(NULL == (log_file = fopen(filename,"a+")))
		{
			zbx_error("Unable to open log file [%s] [%s]", filename, strerror(errno));
			return	FAIL;
		}

		log_type = LOG_TYPE_FILE;
		strscpy(log_filename,filename);
		zbx_fclose(log_file);
	}
	else
	{
		/* Not supported logging type */

		if(ZBX_MUTEX_ERROR == zbx_mutex_create(&log_file_access, "/tmp/zbxlmtx"))
		{
			zbx_error("Unable to create mutex for log file");
			return	FAIL;
		}

		zbx_error("Not supported loggin type [%d]", type);
		return	FAIL;
	}

	return	SUCCEED;
}

void zabbix_close_log(void)
{
	if(LOG_TYPE_SYSLOG == log_type)
	{
#if defined(WIN32)

		if(system_log_handle) 
			DeregisterEventSource(system_log_handle);

#else /* not WIN32 */

		closelog();

#endif /* WIN32 */
	}
	else if(log_type == LOG_TYPE_FILE)
	{
		zbx_mutex_destroy(&log_file_access);
	}
	else
	{
		/* Not supported loggin type */
		zbx_mutex_destroy(&log_file_access);
	}
}

void zabbix_set_log_level(int level)
{
	log_level = level;
}

void zabbix_log(int level, const char *fmt, ...)
{
	FILE	*log_file = NULL;

	char	message[MAX_STRING_LEN];

	time_t	t;
	struct	tm	*tm;
	va_list args;

	struct	stat	buf;

	char	filename_old[MAX_STRING_LEN];

#if defined(WIN32)

	WORD	wType;
	char	*strings[2] = {message, NULL};
	
#endif /* WIN32 */

	if( (level > log_level) || (LOG_LEVEL_EMPTY == level))
	{
		return;
	}

	va_start(args, fmt);
	vsnprintf(message, MAX_STRING_LEN-2, fmt, args);
	va_end(args);
	strncat(message,"\0",MAX_STRING_LEN);

	if(LOG_TYPE_SYSLOG == log_type)
	{
#if defined(WIN32)
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

		ReportEvent(system_log_handle, wType, 0, MSG_ZABBIX_MESSAGE, NULL, 1, 0, strings, NULL);

#else /* not WIN32 */

		syslog(LOG_DEBUG,message);
		
#endif /* WIN32 */
	}
	else if(log_type == LOG_TYPE_FILE)
	{
		zbx_mutex_lock(&log_file_access);
		
		log_file = fopen(log_filename,"a+");

		if(NULL != log_file)
		{

			t = time(NULL);
			tm = localtime(&t);

			fprintf(log_file,
				"%lu:%.4d%.2d%.2d:%.2d%.2d%.2d ",
				(unsigned long)zbx_get_thread_id(),
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


			if(stat(log_filename,&buf) == 0)
			{
				if(buf.st_size > MAX_LOG_FILE_LEN)
				{
					strscpy(filename_old,log_filename);
					strncat(filename_old,".old",MAX_STRING_LEN);
					remove(filename_old);
					if(rename(log_filename,filename_old) != 0)
					{
						zbx_error("Can't rename log file [%s] to [%s] [%s]", log_filename, filename_old, strerror(errno));
					}
				}
			}
		}

		zbx_mutex_unlock(&log_file_access);
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
        return;
}

//
// Get system error string by call to FormatMessage
//
#define ZBX_MESSAGE_BUF_SIZE	1024

char *strerror_from_system(unsigned long error)
{
#if defined(WIN32)

	static char buffer[ZBX_MESSAGE_BUF_SIZE];  /* !!! Attention static !!! not thread safely - Win32*/

	memset(buffer, 0, ZBX_MESSAGE_BUF_SIZE);

	if(FormatMessage(
		FORMAT_MESSAGE_FROM_SYSTEM, 
		NULL, 
		error,
		MAKELANGID(LANG_NEUTRAL, SUBLANG_DEFAULT), 
		buffer, 
		1023, 
		NULL) == 0)
	{
		zbx_snprintf(buffer, sizeof(buffer), "3. MSG 0x%08X - Unable to find message text [0x%X]", error , GetLastError());
	}

	return buffer;

#else /* not WIN32 */

	return strerror(errno);

#endif /* WIN32 */
}

//
// Get system error string by call to FormatMessage
//

char *strerror_from_module(unsigned long error, const char *module)
{
#if defined(WIN32)

	static char buffer[ZBX_MESSAGE_BUF_SIZE]; /* !!! Attention static !!! not thread safely - Win32*/

	assert(module);

	memset(buffer, 0, ZBX_MESSAGE_BUF_SIZE);

	if (FormatMessage(
		FORMAT_MESSAGE_FROM_HMODULE,
		GetModuleHandle(module),
		error,
		MAKELANGID(LANG_NEUTRAL, SUBLANG_DEFAULT), // Default language
		buffer,
		1024,
		NULL) == 0)
	{
		zbx_snprintf(buffer, sizeof(buffer), "3. MSG 0x%08X - Unable to find message text [0x%X]", error , GetLastError());
	}

	return buffer;

#else /* not WIN32 */

	return strerror(errno);

#endif /* WIN32 */

}
