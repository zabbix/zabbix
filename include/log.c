#include <stdio.h>
#include <stdarg.h>
#include <syslog.h>

#include "log.h"
#include "common.h"

static	FILE *log_file = NULL;

static	int log_type = LOG_TYPE_UNDEFINED;
static	int log_priority;

int zabbix_open_log(int type,int level, const char *filename)
{
	if(type == LOG_TYPE_SYSLOG)
	{
        	openlog("zabbix_suckerd",LOG_PID,LOG_USER);
        	setlogmask(LOG_UPTO(LOG_WARNING));
		log_type = LOG_TYPE_SYSLOG;
	}
	else if(type == LOG_TYPE_FILE)
	{
		log_file = fopen(filename,"a+");
		if(log_file == NULL)
		{
			return	FAIL;
		}
		log_type = LOG_TYPE_FILE;
	}
	else
	{
/* Not supported logging type */
		return	FAIL;
	}
	return	SUCCEED;
}

void zabbix_log(int level, const char *fmt, ...)
{
	char	str[1024];

	va_list ap;
	if(log_type == LOG_TYPE_SYSLOG)
	{
		va_start(ap,fmt);
//		udm_logger(handle,level,fmt,ap);
//		syslog(LOG_DEBUG,fmt);
		va_end(ap);
	}
	else if(log_type == LOG_TYPE_FILE)
	{
		va_start(ap,fmt);
		vsprintf(str,fmt,ap);
		fprintf(log_file,str,"\n");
		va_end(ap);
	}
	else
	{
		/* Log is not opened */
	}	
        return;
}
