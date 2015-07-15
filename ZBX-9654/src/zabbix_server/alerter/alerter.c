/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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
#define WAITPID_WAIT_ANY	(-1)
#define WORKER_NOT_RUNNING_PID	0

typedef struct zbx_alerter_worker_s
{
	char	*w_mediatypeid;
	char	*w_description;
	pid_t	w_pid;
	struct zbx_alerter_worker_s	*prev;
	struct zbx_alerter_worker_s	*next;
}
zbx_alerter_worker_t;

extern unsigned char	process_type, daemon_type;
extern int		server_num, process_num;

static zbx_thread_args_t	thread_args;
static zbx_alerter_worker_t	*workers;
int				workers_running, workers_started, workers_terminated;

static void	remove_defunct_workers(void);
static void	add_and_update_workers(void);
static void	start_new_workers(void);
static void	add_worker(char *mediatypeid, char *description, pid_t pid);
static void	remove_worker(zbx_alerter_worker_t *w);
static int	count_workers(void);
static zbx_alerter_worker_t	*find_next_worker_by_pid(zbx_alerter_worker_t *from, pid_t pid);
static zbx_alerter_worker_t	*find_next_worker_by_mediatypeid(zbx_alerter_worker_t *from, char *mediatypeid);
static ZBX_THREAD_ENTRY(alerter_worker_thread, args);

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

	int 	res = FAIL;

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
		char	*cmd = NULL, *send_to, *subject, *message, *output = NULL;
		size_t	cmd_alloc = ZBX_KIBIBYTE, cmd_offset = 0;

		cmd = zbx_malloc(cmd, cmd_alloc);

		zbx_snprintf_alloc(&cmd, &cmd_alloc, &cmd_offset, "%s/%s",
				CONFIG_ALERT_SCRIPTS_PATH, mediatype->exec_path);

		if (0 == access(cmd, X_OK))
		{
			send_to = zbx_dyn_escape_shell_single_quote(alert->sendto);
			subject = zbx_dyn_escape_shell_single_quote(alert->subject);
			message = zbx_dyn_escape_shell_single_quote(alert->message);

			zbx_snprintf_alloc(&cmd, &cmd_alloc, &cmd_offset, " '%s' '%s' '%s'", send_to, subject, message);

			zbx_free(message);
			zbx_free(subject);
			zbx_free(send_to);

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
 * Function: alerter_thread                                                   *
 *                                                                            *
 * Purpose: maitain alerter worker threads                                    *
 *                                                                            *
 * Author: Sandis Neilands                                                    *
 *                                                                            *
 * Comments: Information about workers stored in doubly linked list. Each     *
 *           worker corresponds to a configured media type. The workers are   *
 *           identified by mediatypeid field of the mediatype database table. *
 *                                                                            *
 *           * Upon media type addition a main alerter thread starts a new    *
 *             worker thread.                                                 *
 *           * Worker quits upon media type deletion and main alerter thread  *
 *             removes it from the list of workers.                           *
 *           * The main alerter thread starts a replacement worker if a       *
 *             worker quits unexpectedly.                                     *
 *           * Media type disabling has no effect on alert sending of already *
 *             existing alerts for that media type.                           *
 *                                                                            *
 ******************************************************************************/
ZBX_THREAD_ENTRY(alerter_thread, args)
{
	process_type = ((zbx_thread_args_t *)args)->process_type;
	server_num = ((zbx_thread_args_t *)args)->server_num;
	process_num = ((zbx_thread_args_t *)args)->process_num;

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_daemon_type_string(daemon_type),
			server_num, get_process_type_string(process_type), process_num);

	for (;;)
	{
		double	sec;

		workers_running = workers_started = workers_terminated = 0;
		sec = zbx_time();

		remove_defunct_workers();
		add_and_update_workers();
		start_new_workers();

		workers_running = count_workers();

		sec = zbx_time() - sec;
		zbx_setproctitle("%s [workers (%d running): "
				"%d started, %d terminated in " ZBX_FS_DBL " sec, idle %d sec]",
				get_process_type_string(process_type),
				workers_running, workers_started, workers_terminated,
				sec, CONFIG_SENDER_FREQUENCY);

		zbx_sleep_loop(CONFIG_SENDER_FREQUENCY);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: alerter_worker                                                   *
 *                                                                            *
 * Purpose: periodically check table alerts and send notifications if needed  *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: Quits if the corresponding media type is deleted from the        *
 *           configuration. Updates worker description if the corresponding   *
 *           media type name has changed.                                     *
 *                                                                            *
 ******************************************************************************/
static ZBX_THREAD_ENTRY(alerter_worker_thread, args)
{
	char		error[MAX_STRING_LEN], *error_esc;
	int		res, alerts_success, alerts_fail;
	double		sec;
	DB_RESULT	result;
	DB_ROW		row;
	DB_ALERT	alert;
	DB_MEDIATYPE	mediatype;
	zbx_alerter_worker_t	*worker = ((zbx_thread_args_t *)args)->args;

	zabbix_log(LOG_LEVEL_INFORMATION, "%s worker: started for handling media type '%s'",
			get_process_type_string(process_type), worker->w_description);

	zbx_setproctitle("%s [connecting to the database]: '%s'",
			get_process_type_string(process_type), worker->w_description);

	DBconnect(ZBX_DB_CONNECT_NORMAL);

	for (;;)
	{
		zbx_setproctitle("%s [updating status]: '%s'",
				get_process_type_string(process_type), worker->w_description);

		result = DBselect(
				"select mt.mediatypeid, mt.description from media_type mt where mt.mediatypeid = %s",
				worker->w_mediatypeid);

		if (NULL == (row = DBfetch(result)))
		{
			zabbix_log(LOG_LEVEL_INFORMATION, "%s worker: quitting (alerts might be orphaned) - "
					"media type '%s' deleted",
					get_process_type_string(process_type), worker->w_description);

			zbx_thread_exit(EXIT_SUCCESS);
		}

		if (0 != strcmp(worker->w_description, row[1]))
		{
			zabbix_log(LOG_LEVEL_INFORMATION, "%s worker: media type '%s' renamed to '%s'",
					get_process_type_string(process_type), worker->w_description, row[1]);

			worker->w_description = zbx_strdup(worker->w_description, row[1]);
		}

		DBfree_result(result);

		zbx_setproctitle("%s [sending alerts]: '%s'",
				get_process_type_string(process_type), worker->w_description);

		sec = zbx_time();

		alerts_success = alerts_fail = 0;

		result = DBselect(
				"select a.alertid,a.mediatypeid,a.sendto,a.subject,a.message,a.status,mt.mediatypeid,"
					"mt.type,mt.description,mt.smtp_server,mt.smtp_helo,mt.smtp_email,mt.exec_path,"
					"mt.gsm_modem,mt.username,mt.passwd,mt.smtp_port,mt.smtp_security,"
					"mt.smtp_verify_peer,mt.smtp_verify_host,mt.smtp_authentication,a.retries"
				" from alerts a,media_type mt"
				" where a.mediatypeid=mt.mediatypeid"
					" and a.status=%d"
					" and a.alerttype=%d"
					" and a.mediatypeid=%s"
				" order by a.alertid",
				ALERT_STATUS_NOT_SENT,
				ALERT_TYPE_MESSAGE,
				worker->w_mediatypeid);

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
			mediatype.gsm_modem = row[13];
			mediatype.username = row[14];
			mediatype.passwd = row[15];
			mediatype.smtp_port = (unsigned short)atoi(row[16]);
			ZBX_STR2UCHAR(mediatype.smtp_security, row[17]);
			ZBX_STR2UCHAR(mediatype.smtp_verify_peer, row[18]);
			ZBX_STR2UCHAR(mediatype.smtp_verify_host, row[19]);
			ZBX_STR2UCHAR(mediatype.smtp_authentication, row[20]);

			alert.retries = atoi(row[21]);

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
					DBexecute("update alerts set retries=%d,error='%s' where alertid="
							ZBX_FS_UI64, alert.retries, error_esc, alert.alertid);
				}
				else
				{
					DBexecute("update alerts set status=%d,retries=%d,error='%s' where alertid="
							ZBX_FS_UI64, ALERT_STATUS_FAILED, alert.retries, error_esc,
							alert.alertid);
				}

				zbx_free(error_esc);

				alerts_fail++;
			}

		}

		DBfree_result(result);

		sec = zbx_time() - sec;

		zbx_setproctitle("%s [sent alerts: %d success, %d fail in " ZBX_FS_DBL " sec, idle %d sec]: '%s'",
				get_process_type_string(process_type), alerts_success, alerts_fail, sec,
				CONFIG_SENDER_FREQUENCY, worker->w_description);

		zbx_sleep_loop(CONFIG_SENDER_FREQUENCY);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: remove_defunct_workers                                           *
 *                                                                            *
 * Purpose: remove from the workers' list those workers that have quitted     *
 *                                                                            *
 * Author: Sandis Neilands                                                    *
 *                                                                            *
 ******************************************************************************/
static void	remove_defunct_workers(void)
{
	pid_t			pid;
	int			status;
	zbx_alerter_worker_t	*w;

	zbx_setproctitle("%s [terminating defunct workers]", get_process_type_string(process_type));

	while (0 < (pid = waitpid(WAITPID_WAIT_ANY, &status, WNOHANG)))
	{
		if (NULL != (w = find_next_worker_by_pid(workers, pid)))
		{
			remove_worker(w);
			workers_terminated++;
		}
	}

	if (-1 == pid && ECHILD != errno)
		zabbix_log(LOG_LEVEL_ERR, "waitpid() failed with unexpected reason: %s", strerror(errno));

}

/******************************************************************************
 *                                                                            *
 * Function: add_and_update_workers                                           *
 *                                                                            *
 * Purpose: add newly configured workers to the list, update description of   *
 *          the existing workers                                              *
 *                                                                            *
 * Author: Sandis Neilands                                                    *
 *                                                                            *
 * Comments: workers not started in this function, see start_new_workers()    *
 *                                                                            *
 ******************************************************************************/
static void	add_and_update_workers(void)
{
	DB_RESULT		result;
	DB_ROW			row;
	zbx_alerter_worker_t	*w;

	zbx_setproctitle("%s [connecting to the database]", get_process_type_string(process_type));
	DBconnect(ZBX_DB_CONNECT_NORMAL);
	zbx_setproctitle("%s [updating list of workers]", get_process_type_string(process_type));
	result = DBselect("select mt.mediatypeid, mt.description from media_type mt");

	while (NULL != (row = DBfetch(result)))
		if (NULL == (w = find_next_worker_by_mediatypeid(workers, row[0])))
			add_worker(row[0], row[1], WORKER_NOT_RUNNING_PID);
		else if (0 != strcmp(w->w_description, row[1]))
			w->w_description = zbx_strdup(w->w_description, row[1]);

	DBfree_result(result);
	DBclose();
}

/******************************************************************************
 *                                                                            *
 * Function: start_new_workers                                                *
 *                                                                            *
 * Purpose: start workers that are marked with WORKER_NOT_RUNNING_PID.        *
 *                                                                            *
 * Author: Sandis Neilands                                                    *
 *                                                                            *
 ******************************************************************************/
static void	start_new_workers(void)
{
	zbx_alerter_worker_t	*w;

	zbx_setproctitle("%s [starting workers]", get_process_type_string(process_type));

	w = workers;
	while (NULL != (w = find_next_worker_by_pid(w, WORKER_NOT_RUNNING_PID)))
	{
		thread_args.args = w;
		w->w_pid = zbx_thread_start(alerter_worker_thread, &thread_args);
		workers_started++;
		w = w->next;
	}
}

static void	add_worker(char *mediatypeid, char *description, pid_t pid)
{
	zbx_alerter_worker_t	*w = NULL;

	w = zbx_calloc(w, 1, sizeof(zbx_alerter_worker_t));

	if (NULL != workers)
	{
		w->prev = NULL;
		w->next = workers;
		workers->prev = w;
	}

	workers = w;

	w->w_mediatypeid = zbx_strdup(w->w_mediatypeid, mediatypeid);
	w->w_description = zbx_strdup(w->w_description, description);
	w->w_pid = pid;
}

static void	remove_worker(zbx_alerter_worker_t *w)
{
	if (w->next)
		w->next->prev = w->prev;

	if (w->prev)
		w->prev->next = w->next;

	if (w == workers)
		workers = w->next;

	zbx_free(w->w_mediatypeid);
	zbx_free(w->w_description);
	zbx_free(w);
}

static int	count_workers(void)
{
	zbx_alerter_worker_t	*w;
	int count = 0;

	for (w = workers; NULL != w; w = w->next, count++);

	return count;
}

static zbx_alerter_worker_t	*find_next_worker_by_pid(zbx_alerter_worker_t *from, pid_t pid)
{
	zbx_alerter_worker_t	*w;
	zbx_alerter_worker_t	*found = NULL;

	for (w = from; NULL != w && NULL == found; w = w->next)
		if (pid == w->w_pid)
			found = w;

	return found;
}

static zbx_alerter_worker_t	*find_next_worker_by_mediatypeid(zbx_alerter_worker_t *from, char *mediatypeid)
{
	zbx_alerter_worker_t	*w;
	zbx_alerter_worker_t	*found = NULL;

	for (w = from; NULL != w && NULL == found; w = w->next)
		if (0 == strcmp(w->w_mediatypeid, mediatypeid))
			found = w;

	return found;
}
