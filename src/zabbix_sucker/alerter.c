/* 
** Zabbix
** Copyright (C) 2000,2001,2002,2003,2004 Alexei Vladishev
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

	char	full_path[MAX_STRING_LEN];

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
			strscpy(full_path,CONFIG_ALERT_SCRIPTS_PATH);
			strncat(full_path,"/",MAX_STRING_LEN);
			strncat(full_path,mediatype->exec_path,MAX_STRING_LEN);
			zabbix_log( LOG_LEVEL_DEBUG, "Before executing [%s] [%m]", full_path);
			if(-1 == execl(full_path,mediatype->exec_path,alert->sendto,alert->subject,alert->message,(char *)0))
			{
				zabbix_log( LOG_LEVEL_ERR, "Error executing [%s] [%m]", full_path);
				res = FAIL;
			}
			else
			{
				res = SUCCEED;
			}
			/* In normal case the program will never reach this point */
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

int main_alerter_loop()
{
	char	sql[MAX_STRING_LEN];

	int	i,res;

	struct	sigaction phan;

	DB_RESULT	*result;
	DB_ALERT	alert;
	DB_MEDIATYPE	mediatype;

	for(;;)
	{
#ifdef HAVE_FUNCTION_SETPROCTITLE
		setproctitle("connecting to the database");
#endif
		DBconnect();

		snprintf(sql,sizeof(sql)-1,"select a.alertid,a.mediatypeid,a.sendto,a.subject,a.message,a.status,a.retries,mt.mediatypeid,mt.type,mt.description,mt.smtp_server,mt.smtp_helo,mt.smtp_email,mt.exec_path from alerts a,media_type mt where a.status=0 and a.retries<3 and a.mediatypeid=mt.mediatypeid order by a.clock");
		result = DBselect(sql);

		for(i=0;i<DBnum_rows(result);i++)
		{
			alert.alertid=atoi(DBget_field(result,i,0));
			alert.mediatypeid=atoi(DBget_field(result,i,1));
			alert.sendto=DBget_field(result,i,2);
			alert.subject=DBget_field(result,i,3);
			alert.message=DBget_field(result,i,4);
			alert.status=atoi(DBget_field(result,i,5));
			alert.retries=atoi(DBget_field(result,i,6));

			mediatype.mediatypeid=atoi(DBget_field(result,i,7));
			mediatype.type=atoi(DBget_field(result,i,8));
			mediatype.description=DBget_field(result,i,9);
			mediatype.smtp_server=DBget_field(result,i,10);
			mediatype.smtp_helo=DBget_field(result,i,11);
			mediatype.smtp_email=DBget_field(result,i,12);
			mediatype.exec_path=DBget_field(result,i,13);

			phan.sa_handler = &signal_handler;
			sigemptyset(&phan.sa_mask);
			phan.sa_flags = 0;
			sigaction(SIGALRM, &phan, NULL);

			/* Hardcoded value */
			alarm(10);
			res=send_alert(&alert,&mediatype);
			alarm(0);

			if(res==SUCCEED)
			{
				zabbix_log( LOG_LEVEL_DEBUG, "Alert ID [%d] was sent successfully", alert.alertid);
				snprintf(sql,sizeof(sql)-1,"update alerts set status=1 where alertid=%d", alert.alertid);
				DBexecute(sql);
			}
			else
			{
				zabbix_log( LOG_LEVEL_ERR, "Error sending alert ID [%d]", alert.alertid);
				snprintf(sql,sizeof(sql)-1,"update alerts set retries=retries+1 where alertid=%d", alert.alertid);
				DBexecute(sql);
			}

		}
		DBfree_result(result);

		DBclose();
#ifdef HAVE_FUNCTION_SETPROCTITLE
		setproctitle("sender [sleeping for %d seconds]", CONFIG_SENDER_FREQUENCY);
#endif
		sleep(CONFIG_SENDER_FREQUENCY);
	}
}
