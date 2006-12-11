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


#include <stdio.h>
#include <string.h>
#include <stdarg.h>
#include <syslog.h>

#include <sys/types.h>
#include <sys/stat.h>
#include <unistd.h>

#include <time.h>

#include "log.h"
#include "common.h"

int	create_pid_file(const char *pidfile)
{
	FILE	*f;

/* Check if PID file already exists */
	f = fopen(pidfile, "r");
	if(f != NULL)
	{
		fprintf(stderr, "File [%s] exists. Is this process already running ?\n",
			pidfile);
		zabbix_log( LOG_LEVEL_CRIT, "File [%s] exists. Is this process already running ?",
			pidfile);
		if(fclose(f) != 0)
		{
			fprintf(stderr, "Cannot close file [%s] [%s]", pidfile, strerror(errno));
			zabbix_log( LOG_LEVEL_WARNING, "Cannot close file [%s] [%s]",
				pidfile, strerror(errno));
		}

		return FAIL;
	}

	f = fopen(pidfile, "w");

	if( f == NULL)
	{
		fprintf(stderr, "Cannot create PID file [%s] [%s]\n",
			pidfile, strerror(errno));
		zabbix_log( LOG_LEVEL_CRIT, "Cannot create PID file [%s] [%s]",
			pidfile, strerror(errno));

		return FAIL;
	}

	fprintf(f,"%d",(int)getpid());
	if(fclose(f) != 0)
	{
		fprintf(stderr, "Cannot close file [%s] [%s]", pidfile, strerror(errno));
		zabbix_log( LOG_LEVEL_WARNING, "Cannot close file [%s] [%s]",
			pidfile, strerror(errno));
	}

	return SUCCEED;
}
