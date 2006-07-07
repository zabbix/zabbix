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

#include "config.h"

#include <netdb.h>

#include <stdlib.h>
#include <stdio.h>

#include <unistd.h>
#include <signal.h>

#include <errno.h>
#include <sys/socket.h>
#include <netinet/in.h>
#include <arpa/inet.h>

#include <time.h>

/* No warning for bzero */
#include <string.h>
#include <strings.h>

/* For config file operations */
#include <sys/types.h>
#include <sys/stat.h>
#include <fcntl.h>

/* For setpriority */
#include <sys/time.h>
#include <sys/resource.h>

/* Required for getpwuid */
#include <pwd.h>

#include "common.h"
#include "sysinfo.h"
#include "security.h"
#include "zabbix_agent.h"

#include "log.h"
#include "cfg.h"
#include "stats.h"

/*INTERFACE interfaces[MAX_INTERFACE]=
{
	{0}
};*/

extern char    *CONFIG_STAT_FILE;

void	collect_statistics()
{
	FILE	*file;

	char	tmpname[MAX_STRING_LEN];

	for(;;)
	{
		memset(tmpname, 0, MAX_STRING_LEN);
		strscpy(tmpname, CONFIG_STAT_FILE);
		strncat(tmpname, "2", MAX_STRING_LEN);
		file=fopen(tmpname,"w");
		if(NULL == file)
		{
			zabbix_log( LOG_LEVEL_CRIT, "Cannot open file [%s] [%s]",tmpname, strerror(errno));
			return;
		}
		/* Here is list of functions to call periodically */
		collect_stats_interfaces(file);
		collect_stats_diskdevices(file);
		collect_stats_cpustat(file);

		fclose(file);
		if(-1 == rename(tmpname,CONFIG_STAT_FILE))
		{
			zabbix_log( LOG_LEVEL_CRIT, "Cannot rename file [%s] to [%s] [%s]",tmpname,CONFIG_STAT_FILE,strerror(errno));
			return;
		}

		sleep(1);
	}
}
