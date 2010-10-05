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

#include "cfg.h"
#include "db.h"
#include "log.h"
#include "zlog.h"
#include "daemon.h"
#include "zbxmedia.h"
#include "zbxserver.h"

#include "alerter.h"

/******************************************************************************
 *                                                                            *
 * Function: execute_action                                                   *
 *                                                                            *
 * Purpose: execute an action depending on mediatype                          *
 *                                                                            *
 * Parameters: alert - alert details                                          *
 *             mediatype - media details                                      *
 *                                                                            *
 * Return value: SUCCESS - action executed sucessfully                        *
 *               FAIL - otherwise, error will contain error message           *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int	execute_action(DB_ALERT *alert, DB_MEDIATYPE *mediatype, char *error, int max_error_len)
{
	const char	*__function_name = "execute_action";

	int 	pid, res = FAIL;
	char	full_path[MAX_STRING_LEN];

	zabbix_log(LOG_LEVEL_DEBUG, "In %s(): alertid [" ZBX_FS_UI64 "] mediatype [%d]",
			__function_name, alert->alertid, mediatype->type);

	if (MEDIA_TYPE_EMAIL == mediatype->type)
	{
		alarm(40);
		res = send_email(mediatype->smtp_server, mediatype->smtp_helo, mediatype->smtp_email,
				alert->sendto, alert->subject, alert->message, error, max_error_len);
		alarm(0);
	}
#if defined(HAVE_JABBER)
	else if (MEDIA_TYPE_JABBER == mediatype->type)
	{
		/* Jabber uses its own timeouts */
		res = send_jabber(mediatype->username, mediatype->passwd,
				alert->sendto, alert->subject, alert->message, error, max_error_len);
	}
#endif
	else if (MEDIA_TYPE_SMS == mediatype->type)
	{
		/* SMS uses its own timeouts */
		res = send_sms(mediatype->gsm_modem, alert->sendto, alert->message, error, max_error_len);
	}
	else if (MEDIA_TYPE_EZ_TEXTING == mediatype->type)
	{
		/* Ez Texting uses its own timeouts */
		res = send_ez_texting(mediatype->username, mediatype->passwd,
				alert->sendto, alert->message, mediatype->exec_path, error, max_error_len);
	}
	else if (MEDIA_TYPE_EXEC == mediatype->type)
	{
		pid = zbx_fork();
		if (0 != pid)
		{
			waitpid(pid, NULL, 0);
			res = SUCCEED;
		}
		else
		{
			zbx_snprintf(full_path, sizeof(full_path), "%s/%s",
					CONFIG_ALERT_SCRIPTS_PATH, mediatype->exec_path);

			zabbix_log(LOG_LEVEL_DEBUG, "Before executing [%s]", full_path);

			if (-1 == execl(full_path, mediatype->exec_path, alert->sendto,
						alert->subject, alert->message, (char *)NULL))
			{
				zabbix_log(LOG_LEVEL_ERR, "Error executing [%s] [%s]",
					full_path,
					strerror(errno));
				zabbix_syslog("Error executing [%s] [%s]",
					full_path,
					strerror(errno));
				exit(FAIL);
			}
			else
				THIS_SHOULD_NEVER_HAPPEN;
		}
	}
	else
	{
		zabbix_log(LOG_LEVEL_ERR, "Unsupported media type [%d] for alert ID [" ZBX_FS_UI64 "]",
			mediatype->type,
			alert->alertid);
		zabbix_syslog("Unsupported media type [%d] for alert ID [" ZBX_FS_UI64 "]",
			mediatype->type,
			alert->alertid);
		zbx_snprintf(error, max_error_len, "Unsupported media type [%d]",
			mediatype->type);
		res = FAIL;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s(): %d", __function_name, zbx_result_string(res));

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
int	main_alerter_loop()
{
	char			error[MAX_STRING_LEN], *error_esc;
	int			res, now;
	struct	sigaction	phan;
	DB_RESULT		result;
	DB_ROW			row;
	DB_ALERT		alert;
	DB_MEDIATYPE		mediatype;

        phan.sa_sigaction = child_signal_handler;
	sigemptyset(&phan.sa_mask);
	phan.sa_flags = SA_SIGINFO;
	sigaction(SIGALRM, &phan, NULL);

	zbx_setproctitle("connecting to the database");

	DBconnect(ZBX_DB_CONNECT_NORMAL);

	for (;;)
	{
		now = time(NULL);

		result = DBselect("select a.alertid,a.mediatypeid,a.sendto,a.subject,a.message,a.status,mt.mediatypeid"
				",mt.type,mt.description,mt.smtp_server,mt.smtp_helo,mt.smtp_email,mt.exec_path"
				",mt.gsm_modem,mt.username,mt.passwd,a.retries from alerts a,media_type mt"
				" where a.status=%d and a.mediatypeid=mt.mediatypeid and a.alerttype=%d" DB_NODE
				" order by a.clock",
				ALERT_STATUS_NOT_SENT,
				ALERT_TYPE_MESSAGE,
				DBnode_local("mt.mediatypeid"));

		while (NULL != (row = DBfetch(result)))
		{
			ZBX_STR2UINT64(alert.alertid, row[0]);
			ZBX_STR2UINT64(alert.mediatypeid, row[1]);
			alert.sendto		= row[2];
			alert.subject		= row[3];
			alert.message		= row[4];
			alert.status		= atoi(row[5]);

			ZBX_STR2UINT64(mediatype.mediatypeid, row[6]);
			mediatype.type		= atoi(row[7]);
			mediatype.description	= row[8];
			mediatype.smtp_server	= row[9];
			mediatype.smtp_helo	= row[10];
			mediatype.smtp_email	= row[11];
			mediatype.exec_path	= row[12];
			mediatype.gsm_modem	= row[13];
			mediatype.username	= row[14];
			mediatype.passwd	= row[15];

			alert.retries		= atoi(row[16]);

			*error = '\0';
			res = execute_action(&alert, &mediatype, error, sizeof(error));

			if (res == SUCCEED)
			{
				zabbix_log(LOG_LEVEL_DEBUG, "Alert ID [" ZBX_FS_UI64 "] was sent successfully",
					alert.alertid);
				DBexecute("update alerts set status=%d,error='' where alertid=" ZBX_FS_UI64,
					ALERT_STATUS_SENT,
					alert.alertid);
			}
			else
			{
				zabbix_log(LOG_LEVEL_DEBUG, "Error sending alert ID [" ZBX_FS_UI64 "]",
					alert.alertid);
				zabbix_syslog("Error sending alert ID [" ZBX_FS_UI64 "]",
					alert.alertid);

				error_esc = DBdyn_escape_string_len(error, ALERT_ERROR_LEN);

				alert.retries++;
				if (alert.retries < ALERT_MAX_RETRIES)
				{
					DBexecute("update alerts set retries=%d,error='%s' where alertid=" ZBX_FS_UI64,
						alert.retries,
						error_esc,
						alert.alertid);
				}
				else
				{
					DBexecute("update alerts set status=%d,retries=%d,error='%s' where alertid=" ZBX_FS_UI64,
						ALERT_STATUS_FAILED,
						alert.retries,
						error_esc,
						alert.alertid);
				}

				zbx_free(error_esc);
			}

		}
		DBfree_result(result);

		zbx_setproctitle("sender [sleeping for %d seconds]",
				CONFIG_SENDER_FREQUENCY);

		sleep(CONFIG_SENDER_FREQUENCY);
	}

	/* Never reached */
	DBclose();
}
