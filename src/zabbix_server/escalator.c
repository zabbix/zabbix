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

#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <sys/socket.h>
#include <netinet/in.h>

#include <sys/wait.h>

#include <string.h>

#ifdef HAVE_NETDB_H
	#include <netdb.h>
#endif

/* Required for getpwuid */
#include <pwd.h>

#include <signal.h>
#include <errno.h>

#include <time.h>

#include "common.h"
#include "cfg.h"
#include "db.h"
#include "functions.h"
#include "log.h"
#include "zlog.h"
#include "email.h"

#include "escalator.h"

int process_escalation(DB_ESCALATION_LOG *escalation_log)
{
	return SUCCEED;
}

int main_escalator_loop()
{
	char	sql[MAX_STRING_LEN];
	char	error[MAX_STRING_LEN];
	char	error_esc[MAX_STRING_LEN];

	int	i,res;
	int	now;

	struct	sigaction phan;

	DB_ESCALATION_LOG	escalation_log;

	for(;;)
	{
		zabbix_log( LOG_LEVEL_WARNING, "Selecting data from escalation_log]");
#ifdef HAVE_FUNCTION_SETPROCTITLE
		setproctitle("connecting to the database");
#endif

		DBconnect();

		now=time(NULL);
		snprintf(sql,sizeof(sql)-1,"select escalationlogid,triggerid, alarmid, escalationid, level, adminlevel, nextcheck, status from escalation_log where status=0 and nextcheck<=%d", now);
		result = DBselect(sql);

		for(i=0;i<DBnum_rows(result);i++)
		{
			escalation_log.escalationlogid=atoi(DBget_field(result,i,0));
			escalation_log.triggerid=atoi(DBget_field(result,i,1));
			escalation_log.alarmid=atoi(DBget_field(result,i,2));
			escalation_log.escalationid=atoi(DBget_field(result,i,3));
			escalation_log.level=atoi(DBget_field(result,i,4));
			escalation_log.adminlevel=atoi(DBget_field(result,i,5));
			escalation_log.nextcheck=atoi(DBget_field(result,i,6));
			escalation_log.status=atoi(DBget_field(result,i,7));

			res=process_escalation(&escalation_log);

			if(res==SUCCEED)
			{
				zabbix_log( LOG_LEVEL_WARNING, "Processing escalation_log ID [%d]", escalation_log.escalationlogid);
			}
			else
			{
				zabbix_log( LOG_LEVEL_WARNING, "Processing escalation_log ID [%d] failed", escalation_log.escalationlogid);
			}

		}
		DBfree_result(result);

		DBclose();
#ifdef HAVE_FUNCTION_SETPROCTITLE
		setproctitle("escalator [sleeping for %d seconds]", CONFIG_SENDER_FREQUENCY);
#endif

		sleep(CONFIG_SENDER_FREQUENCY);
	}
}
