#include <stdio.h>
#include <string.h>
#include <stdarg.h>
#include <syslog.h>

#include <sys/types.h>
#include <unistd.h>

#include <time.h>

#include "log.h"
#include "common.h"

static	FILE *log_file = NULL;

static	int log_type = LOG_TYPE_UNDEFINED;
static	int log_level;

int zabbix_open_log(int type,int level, const char *filename)
{
/* Just return if we do not want to write debug */
	log_level = level;
	if(level == LOG_LEVEL_EMPTY)
	{
		return	SUCCEED;
	}

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
	char	str[MAX_STRING_LEN+1];
	char	str2[MAX_STRING_LEN+1];
	time_t	t;
	struct	tm	*tm;
	va_list ap;

	if( (level>log_level) || (level == LOG_LEVEL_EMPTY))
	{
		return;
	}

	if(log_type == LOG_TYPE_SYSLOG)
	{
		va_start(ap,fmt);
		vsprintf(str,fmt,ap);
		strncat(str,"\n",MAX_STRING_LEN);
		syslog(LOG_DEBUG,str);
		va_end(ap);
	}
	else if(log_type == LOG_TYPE_FILE)
	{
		t=time(NULL);
		tm=localtime(&t);
		sprintf(str2,"%.6d:%.4d%.2d%.2d:%.2d%.2d%.2d ",getpid(),tm->tm_year+1900,tm->tm_mon+1,tm->tm_mday,tm->tm_hour,tm->tm_min,tm->tm_sec);

		va_start(ap,fmt);
		vsprintf(str,fmt,ap);
		strncat(str,"\n",MAX_STRING_LEN);
		strncat(str2,str,MAX_STRING_LEN);
		fprintf(log_file,str2);
		fflush(log_file);
		va_end(ap);
	}
	else
	{
		/* Log is not opened */
	}	
        return;
}
