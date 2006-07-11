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
#include "../functions.h"
#include "log.h"
#include "zlog.h"
#include "email.h"
#include "sms.h"

#include "alerter.h"
#include "daemon.h"

/******************************************************************************
 *                                                                            *
 * Function: execute_action                                                   *
 *                                                                            *
 * Purpose: executa an action depending on mediatype                          *
 *                                                                            *
 * Parameters: alert - alert details                                          *
 *             mediatype - media details                                      *
 *                                                                            *
 * Return value: SUCCESS - action executed sucesfully                         * 
 *               FAIL - otherwise, error will contain error message           *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int execute_action(DB_ALERT *alert,DB_MEDIATYPE *mediatype, char *error, int max_error_len)
{
	int 	res=FAIL;
	int	pid;

	char	full_path[MAX_STRING_LEN];

	zabbix_log( LOG_LEVEL_DEBUG, "In execute_action()");

	if(mediatype->type==ALERT_TYPE_EMAIL)
	{
		res = send_email(mediatype->smtp_server,mediatype->smtp_helo,mediatype->smtp_email,alert->sendto,alert->subject,
			alert->message, error, max_error_len);
	}
	else if(mediatype->type==ALERT_TYPE_SMS)
	{
		res = send_sms(mediatype->gsm_modem,alert->sendto,alert->message, error, max_error_len);
	}
	else if(mediatype->type==ALERT_TYPE_EXEC)
	{
/*		if(-1 == execl(CONFIG_ALERT_SCRIPTS_PATH,mediatype->exec_path,alert->sendto,alert->subject,alert->message))*/
		zabbix_log( LOG_LEVEL_DEBUG, "Before execl([%s],[%s])",CONFIG_ALERT_SCRIPTS_PATH,mediatype->exec_path);

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
				zabbix_log( LOG_LEVEL_ERR, "Error executing [%s] [%s]", full_path, strerror(errno));
				zabbix_syslog("Error executing [%s] [%s]", full_path, strerror(errno));
				zbx_snprintf(error,max_error_len,"Error executing [%s] [%s]", full_path, strerror(errno));
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
		zabbix_syslog("Unsupported media type [%d] for alert ID [%d]", mediatype->type,alert->alertid);
		zbx_snprintf(error,max_error_len,"Unsupported media type [%d]", mediatype->type);
		res=FAIL;
	}

	zabbix_log( LOG_LEVEL_DEBUG, "End of execute_action()");

	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: main_alerter_loop                                                *
 *                                                                            *
 * Purpose: periodically check table alerts and send notifications if needed  *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              * 
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: never returns                                                    *
 *                                                                            *
 ******************************************************************************/
int main_alerter_loop()
{
	char	sql[MAX_STRING_LEN];
	char	error[MAX_STRING_LEN];
	char	error_esc[MAX_STRING_LEN];

	int	res, now;

	struct	sigaction phan;

	DB_RESULT	result;
	DB_ROW		row;
	DB_ALERT	alert;
	DB_MEDIATYPE	mediatype;

	for(;;)
	{

		zbx_setproctitle("connecting to the database");

		DBconnect();

		now  = time(NULL);

/*		zbx_snprintf(sql,sizeof(sql),"select a.alertid,a.mediatypeid,a.sendto,a.subject,a.message,a.status,a.retries,mt.mediatypeid,mt.type,mt.description,mt.smtp_server,mt.smtp_helo,mt.smtp_email,mt.exec_path from alerts a,media_type mt where a.status=0 and a.retries<3 and a.mediatypeid=mt.mediatypeid order by a.clock"); */
		zbx_snprintf(sql,sizeof(sql),"select a.alertid,a.mediatypeid,a.sendto,a.subject,a.message,a.status,a.retries,mt.mediatypeid,mt.type,mt.description,mt.smtp_server,mt.smtp_helo,mt.smtp_email,mt.exec_path,a.delay,mt.gsm_modem from alerts a,media_type mt where a.status=%d and a.retries<3 and (a.repeats<a.maxrepeats or a.maxrepeats=0) and a.nextcheck<=%d and a.mediatypeid=mt.mediatypeid order by a.clock", ALERT_STATUS_NOT_SENT, now);
		result = DBselect(sql);

		while((row=DBfetch(result)))
		{
			alert.alertid=atoi(row[0]);
			alert.mediatypeid=atoi(row[1]);
			alert.sendto=row[2];
			alert.subject=row[3];
			alert.message=row[4];
			alert.status=atoi(row[5]);
			alert.retries=atoi(row[6]);

			mediatype.mediatypeid=atoi(row[7]);
			mediatype.type=atoi(row[8]);
			mediatype.description=row[9];
			mediatype.smtp_server=row[10];
			mediatype.smtp_helo=row[11];
			mediatype.smtp_email=row[12];
			mediatype.exec_path=row[13];

			alert.delay=atoi(row[14]);

			mediatype.gsm_modem=row[15];

			phan.sa_handler = child_signal_handler;
			sigemptyset(&phan.sa_mask);
			phan.sa_flags = 0;
			sigaction(SIGALRM, &phan, NULL);

			/* Hardcoded value */
			/* SMS requires 12.5s for sending */
			alarm(20);
			res=execute_action(&alert,&mediatype,error,sizeof(error));
			alarm(0);

			if(res==SUCCEED)
			{
				zabbix_log( LOG_LEVEL_DEBUG, "Alert ID [%d] was sent successfully", alert.alertid);
				zbx_snprintf(sql,sizeof(sql),"update alerts set repeats=repeats+1, nextcheck=%d where alertid=%d", now+alert.delay, alert.alertid);
				DBexecute(sql);
				zbx_snprintf(sql,sizeof(sql),"update alerts set status=%d where alertid=%d and repeats>=maxrepeats and status=%d and retries<3", ALERT_STATUS_SENT, alert.alertid, ALERT_STATUS_NOT_SENT);
				DBexecute(sql);
			}
			else
			{
				zabbix_log( LOG_LEVEL_DEBUG, "Error sending alert ID [%d]", alert.alertid);
				zabbix_syslog("Error sending alert ID [%d]", alert.alertid);
				DBescape_string(error,error_esc,MAX_STRING_LEN);
				zbx_snprintf(sql,sizeof(sql),"update alerts set retries=retries+1,error='%s' where alertid=%d", error_esc, alert.alertid);
				DBexecute(sql);
			}

		}
		DBfree_result(result);

		DBclose();

		zbx_setproctitle("sender [sleeping for %d seconds]", CONFIG_SENDER_FREQUENCY);

		sleep(CONFIG_SENDER_FREQUENCY);
	}
}
