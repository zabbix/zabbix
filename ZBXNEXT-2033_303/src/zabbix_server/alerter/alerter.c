/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "common.h"

#include "cfg.h"
#include "db.h"
#include "log.h"
#include "daemon.h"
#include "zbxmedia.h"
#include "zbxserver.h"
#include "zbxself.h"
#include "zbxexec.h"

#include "alerter.h"

#define	ALARM_ACTION_TIMEOUT	40

extern unsigned char	process_type, program_type;
extern int		server_num, process_num;

/******************************************************************************
 *                                                                            *
 * Function: execute_action                                                   *
 *                                                                            *
 * Purpose: execute an action depending on mediatype                          *
 *                                                                            *
 * Parameters: alert - alert details                                          *
 *             mediatype - media details                                      *
 *                                                                            *
 * Return value: SUCCESS - action executed successfully                       *
 *               FAIL - otherwise, error will contain error message           *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 ******************************************************************************/
int	execute_action(DB_ALERT *alert, DB_MEDIATYPE *mediatype, char *error, int max_error_len)
{
	const char	*__function_name = "execute_action";

	int		res = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s(): alertid [" ZBX_FS_UI64 "] mediatype [%d]",
			__function_name, alert->alertid, mediatype->type);

	if (MEDIA_TYPE_EMAIL == mediatype->type)
	{
		res = send_email(mediatype->smtp_server, mediatype->smtp_port, mediatype->smtp_helo,
				mediatype->smtp_email, alert->sendto, alert->subject, alert->message,
				mediatype->smtp_security, mediatype->smtp_verify_peer, mediatype->smtp_verify_host,
				mediatype->smtp_authentication, mediatype->username, mediatype->passwd,
				ALARM_ACTION_TIMEOUT, error, max_error_len);
	}
#ifdef HAVE_JABBER
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
		char	*cmd = NULL, *output = NULL;
		size_t	cmd_alloc = ZBX_KIBIBYTE, cmd_offset = 0;

		cmd = zbx_malloc(cmd, cmd_alloc);

		zbx_snprintf_alloc(&cmd, &cmd_alloc, &cmd_offset, "%s/%s",
				CONFIG_ALERT_SCRIPTS_PATH, mediatype->exec_path);

		if (0 == access(cmd, X_OK))
		{
			char	*pstart, *pend, *param = NULL;
			size_t	param_alloc = 0, param_offset;

			for (pstart = mediatype->exec_params; NULL != (pend = strchr(pstart, '\n')); pstart = pend + 1)
			{
				char	*param_esc;

				param_offset = 0;

				zbx_strncpy_alloc(&param, &param_alloc, &param_offset, pstart, pend - pstart);

				substitute_simple_macros(NULL, NULL, NULL, NULL, NULL, NULL, NULL, alert, &param,
						MACRO_TYPE_ALERT, NULL, 0);

				param_esc = zbx_dyn_escape_shell_single_quote(param);

				zbx_snprintf_alloc(&cmd, &cmd_alloc, &cmd_offset, " '%s'", param_esc);

				zbx_free(param_esc);
			}

			zbx_free(param);

			if (SUCCEED == (res = zbx_execute(cmd, &output, error, max_error_len, ALARM_ACTION_TIMEOUT)))
			{
				zabbix_log(LOG_LEVEL_DEBUG, "%s output:\n%s", mediatype->exec_path, output);
				zbx_free(output);
			}
			else
				res = FAIL;
		}
		else
			zbx_snprintf(error, max_error_len, "%s: %s", cmd, zbx_strerror(errno));

		zbx_free(cmd);
	}
	else if (MEDIA_TYPE_REMEDY == mediatype->type)
	{
		char	*error_dyn = NULL;

		res = zbx_remedy_process_alert(alert, mediatype, &error_dyn);

		if (NULL != error_dyn)
		{
			zbx_strlcpy(error, error_dyn, max_error_len);
			zbx_free(error_dyn);
		}
	}
	else
	{
		zbx_snprintf(error, max_error_len, "unsupported media type [%d]", mediatype->type);
		zabbix_log(LOG_LEVEL_ERR, "alert ID [" ZBX_FS_UI64 "]: %s", alert->alertid, error);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(res));

	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: main_alerter_loop                                                *
 *                                                                            *
 * Purpose: periodically check table alerts and send notifications if needed  *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 ******************************************************************************/
ZBX_THREAD_ENTRY(alerter_thread, args)
{
	char		error[MAX_STRING_LEN], *error_esc;
	int		res, alerts_success, alerts_fail;
	double		sec;
	DB_RESULT	result;
	DB_ROW		row;
	DB_ALERT	alert;
	DB_MEDIATYPE	mediatype;

	process_type = ((zbx_thread_args_t *)args)->process_type;
	server_num = ((zbx_thread_args_t *)args)->server_num;
	process_num = ((zbx_thread_args_t *)args)->process_num;

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(program_type),
			server_num, get_process_type_string(process_type), process_num);

	zbx_setproctitle("%s [connecting to the database]", get_process_type_string(process_type));

	DBconnect(ZBX_DB_CONNECT_NORMAL);

	for (;;)
	{
		zbx_handle_log();

		zbx_setproctitle("%s [sending alerts]", get_process_type_string(process_type));

		sec = zbx_time();

		alerts_success = alerts_fail = 0;

		result = DBselect(
				"select a.alertid,a.mediatypeid,a.sendto,a.subject,a.message,a.status,mt.mediatypeid,"
					"mt.type,mt.description,mt.smtp_server,mt.smtp_helo,mt.smtp_email,mt.exec_path,"
					"mt.gsm_modem,mt.username,mt.passwd,mt.smtp_port,mt.smtp_security,"
					"mt.smtp_verify_peer,mt.smtp_verify_host,mt.smtp_authentication,mt.exec_params,"
					"a.retries,a.eventid,a.userid"
				" from alerts a,media_type mt"
				" where a.mediatypeid=mt.mediatypeid"
					" and a.status=%d"
					" and a.alerttype=%d"
				" order by a.alertid",
				ALERT_STATUS_NOT_SENT,
				ALERT_TYPE_MESSAGE);

		while (NULL != (row = DBfetch(result)))
		{
			ZBX_STR2UINT64(alert.alertid, row[0]);
			ZBX_STR2UINT64(alert.mediatypeid, row[1]);
			alert.sendto = row[2];
			alert.subject = row[3];
			alert.message = row[4];
			alert.status = atoi(row[5]);

			ZBX_STR2UINT64(mediatype.mediatypeid, row[6]);
			mediatype.type = atoi(row[7]);
			mediatype.description = row[8];
			mediatype.smtp_server = row[9];
			mediatype.smtp_helo = row[10];
			mediatype.smtp_email = row[11];
			mediatype.exec_path = row[12];
			mediatype.exec_params = row[21];
			mediatype.gsm_modem = row[13];
			mediatype.username = row[14];
			mediatype.passwd = row[15];
			mediatype.smtp_port = (unsigned short)atoi(row[16]);
			ZBX_STR2UCHAR(mediatype.smtp_security, row[17]);
			ZBX_STR2UCHAR(mediatype.smtp_verify_peer, row[18]);
			ZBX_STR2UCHAR(mediatype.smtp_verify_host, row[19]);
			ZBX_STR2UCHAR(mediatype.smtp_authentication, row[20]);
			alert.retries = atoi(row[22]);
			ZBX_STR2UINT64(alert.eventid, row[23]);
			ZBX_STR2UINT64(alert.userid, row[24]);

			*error = '\0';
			res = execute_action(&alert, &mediatype, error, sizeof(error));

			if (SUCCEED == res)
			{
				zabbix_log(LOG_LEVEL_DEBUG, "alert ID [" ZBX_FS_UI64 "] was sent successfully",
						alert.alertid);
				DBexecute("update alerts set status=%d,error='' where alertid=" ZBX_FS_UI64,
						ALERT_STATUS_SENT, alert.alertid);
				alerts_success++;
			}
			else
			{
				zabbix_log(LOG_LEVEL_DEBUG, "error sending alert ID [" ZBX_FS_UI64 "]", alert.alertid);

				error_esc = DBdyn_escape_string_len(error, ALERT_ERROR_LEN);

				alert.retries++;

				if (ALERT_MAX_RETRIES > alert.retries)
				{
					DBexecute("update alerts set retries=%d,error='%s' where alertid=" ZBX_FS_UI64,
							alert.retries, error_esc, alert.alertid);
				}
				else
				{
					DBexecute("update alerts set status=%d,retries=%d,error='%s' where alertid=" ZBX_FS_UI64,
							ALERT_STATUS_FAILED, alert.retries, error_esc, alert.alertid);
				}

				zbx_free(error_esc);

				alerts_fail++;
			}

		}
		DBfree_result(result);

		sec = zbx_time() - sec;

		zbx_setproctitle("%s [sent alerts: %d success, %d fail in " ZBX_FS_DBL " sec, idle %d sec]",
				get_process_type_string(process_type), alerts_success, alerts_fail, sec,
				CONFIG_SENDER_FREQUENCY);

		zbx_sleep_loop(CONFIG_SENDER_FREQUENCY);
	}
}
