/* 
** Zabbix
** Copyright (C) 2000,2001,2002,2003 Alexei Vladishev
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

#include "alerter.h"

void	signal_handler2( int sig )
{
	zabbix_log( LOG_LEVEL_DEBUG, "Got signal [%d]", sig);
}

int send_alert(DB_ALERT	*alert,DB_MEDIATYPE *mediatype)
{
	int res=FAIL;
	struct	sigaction phan;
	int	pid;

	char	full_path[MAX_STRING_LEN+1];

	zabbix_log( LOG_LEVEL_DEBUG, "In send_alert()");

	if(mediatype->type==ALERT_TYPE_EMAIL)
	{
		res = send_email(mediatype->smtp_server,mediatype->smtp_helo,mediatype->smtp_email,alert->sendto,alert->subject,alert->message);
	}
	else if(mediatype->type==ALERT_TYPE_EXEC)
	{
/*		if(-1 == execl(CONFIG_ALERT_SCRIPTS_PATH,mediatype->exec_path,alert->sendto,alert->subject,alert->message))*/
		zabbix_log( LOG_LEVEL_DEBUG, "Before execl([%s],[%s])",CONFIG_ALERT_SCRIPTS_PATH,mediatype->exec_path);

		phan.sa_handler = &signal_handler2;
		phan.sa_handler = SIG_IGN;
/*		signal( SIGCHLD, SIG_IGN );*/

		sigemptyset(&phan.sa_mask);
		phan.sa_flags = 0;
		sigaction(SIGCHLD, &phan, NULL);

/*		if(-1 == execl("/home/zabbix/bin/lmt.sh","lmt.sh",alert->sendto,alert->subject,alert->message,(char *)0))*/

		pid=fork();
		if(0 != pid)
		{
			waitpid(pid,NULL,0);
		}
		else
		{
			strncpy(full_path,CONFIG_ALERT_SCRIPTS_PATH,MAX_STRING_LEN);
			strncat(full_path,"/",MAX_STRING_LEN);
			strncat(full_path,mediatype->exec_path,MAX_STRING_LEN);
			zabbix_log( LOG_LEVEL_DEBUG, "Before executing [%s] [%m]", full_path);
			if(-1 == execl("/bin/sh","-c",full_path,alert->sendto,alert->subject,alert->message,(char *)0))
			{
				zabbix_log( LOG_LEVEL_ERR, "Error executing [%s] [%m]", full_path);
				res = FAIL;
			}
			else
			{
				res = SUCCEED;
			}
			zabbix_log( LOG_LEVEL_DEBUG, "After execl()");
			exit(0);
		}
		res = SUCCEED;
	}
	else
	{
		zabbix_log( LOG_LEVEL_ERR, "Unsupported media type [%d] for alert ID [%d]", mediatype->type,alert->alertid);
		res=FAIL;
	}

	zabbix_log( LOG_LEVEL_DEBUG, "End of send_alert()");

	return res;
}

int pinger_loop()
{
	char	sql[MAX_STRING_LEN+1];

	int	i,res,now;

	struct	sigaction phan;

	DB_RESULT	*result;
	DB_ALERT	alert;
	DB_MEDIATYPE	mediatype;

	for(;;)
	{
#ifdef HAVE_FUNCTION_SETPROCTITLE
		setproctitle("connecting to the database");
#endif
		DBconnect(CONFIG_DBHOST, CONFIG_DBNAME, CONFIG_DBUSER, CONFIG_DBPASSWORD, CONFIG_DBSOCKET);

		now=time(NULL);

		sprintf(sql,"select h.useip,h.ip,h.status from items i,hosts h where (h.status=%d or (h.status=%d and h.disable_until<=%d))", HOST_STATUS_MONITORED, HOST_STATUS_UNREACHABLE, now);

		sprintf(sql,"select i.itemid,i.key_,h.host,h.port,i.delay,i.description,i.nextcheck,i.type,i.snmp_community,i.snmp_oid,h.useip,h.ip,i.history,i.lastvalue,i.prevvalue,i.hostid,h.status,i.value_type,h.network_errors,i.snmp_port from items i,hosts h where i.status=%d and i.type=%d and (h.status=0 or (h.status=2 and h.disable_until<=%d)) and h.hostid=i.hostid i.key_='%s' order by i.nextcheck", ITEM_STATUS_ACTIVE, ITEM_TYPE_SIMPLE, now, ICMP_PING_KEY);

		result = DBselect(sql);

		for(i=0;i<DBnum_rows(result);i++)
		{
			item.itemid=atoi(DBget_field(result,i,0));
			item.key=DBget_field(result,i,1);
			item.host=DBget_field(result,i,2);
			item.port=atoi(DBget_field(result,i,3));
			item.delay=atoi(DBget_field(result,i,4));
			item.description=DBget_field(result,i,5);
			item.nextcheck=atoi(DBget_field(result,i,6));
			item.type=atoi(DBget_field(result,i,7));
			item.snmp_community=DBget_field(result,i,8);
			item.snmp_oid=DBget_field(result,i,9);
			item.useip=atoi(DBget_field(result,i,10));
			item.ip=DBget_field(result,i,11);
			item.history=atoi(DBget_field(result,i,12));
			s=DBget_field(result,i,13);
			if(s==NULL)
			{
				item.lastvalue_null=1;
			}
			else
			{
				item.lastvalue_null=0;
				item.lastvalue_str=s;
				item.lastvalue=atof(s);
			}
			s=DBget_field(result,i,14);
			if(s==NULL)
			{
				item.prevvalue_null=1;
			}
			else
			{
				item.prevvalue_null=0;
				item.prevvalue_str=s;
				item.prevvalue=atof(s);
			}
			item.hostid=atoi(DBget_field(result,i,15));
			host_status=atoi(DBget_field(result,i,16));
			item.value_type=atoi(DBget_field(result,i,17));

			network_errors=atoi(DBget_field(result,i,18));
			item.snmp_port=atoi(DBget_field(result,i,18));

			/* Hardcoded value */
			alarm(10);
			res=ping_hosts(&alert,&mediatype);
			alarm(0);

			if(res==SUCCEED)
			{
				zabbix_log( LOG_LEVEL_DEBUG, "Alert ID [%d] was sent successfully", alert.alertid);
				sprintf(sql,"update alerts set status=1 where alertid=%d", alert.alertid);
				DBexecute(sql);
			}
			else
			{
				zabbix_log( LOG_LEVEL_ERR, "Error sending alert ID [%d]", alert.alertid);
				sprintf(sql,"update alerts set retries=retries+1 where alertid=%d", alert.alertid);
				DBexecute(sql);
			}

		}
		DBfree_result(result);

		DBclose();
#ifdef HAVE_FUNCTION_SETPROCTITLE
		setproctitle("pinger [sleeping for %d seconds]", CONFIG_PINGER_FREQUENCY);
#endif
		sleep(CONFIG_PINGER_FREQUENCY);
	}
}
