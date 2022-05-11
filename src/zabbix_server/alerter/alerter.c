/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

#include "alerter.h"

#include "log.h"
#include "daemon.h"
#include "zbxmedia.h"
#include "zbxself.h"
#include "zbxexec.h"
#include "zbxipcservice.h"
#include "dbcache.h"
#include "alerter_protocol.h"
#include "zbxembed.h"

#define	ALARM_ACTION_TIMEOUT	40

extern ZBX_THREAD_LOCAL unsigned char	process_type;
extern unsigned char			program_type;
extern ZBX_THREAD_LOCAL int		server_num, process_num;

static zbx_es_t	es_engine;

/******************************************************************************
 *                                                                            *
 * Purpose: execute script alert type                                         *
 *                                                                            *
 ******************************************************************************/
static int	execute_script_alert(const char *command, char *error, size_t max_error_len)
{
	char	*output = NULL;
	int	ret = FAIL;

	if (SUCCEED == (ret = zbx_execute(command, &output, error, max_error_len, ALARM_ACTION_TIMEOUT,
			ZBX_EXIT_CODE_CHECKS_ENABLED, NULL)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s output:\n%s", command, output);
		zbx_free(output);
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: registers alerter with alert manager                              *
 *                                                                            *
 * Parameters: socket - [IN] the connections socket                           *
 *                                                                            *
 ******************************************************************************/
static void	alerter_register(zbx_ipc_socket_t *socket)
{
	pid_t	ppid;

	ppid = getppid();

	zbx_ipc_socket_write(socket, ZBX_IPC_ALERTER_REGISTER, (unsigned char *)&ppid, sizeof(ppid));
}

/******************************************************************************
 *                                                                            *
 * Purpose: sends alert sending result to alert manager                       *
 *                                                                            *
 * Parameters: socket  - [IN] the connections socket                          *
 *             errcode - [IN] the error code                                  *
 *             value   - [IN] the value or error message                      *
 *             debug   - [IN] debug message                                   *
 *                                                                            *
 ******************************************************************************/
static void	alerter_send_result(zbx_ipc_socket_t *socket, const char *value, int errcode, const char *error,
		const char *debug)
{
	unsigned char	*data;
	zbx_uint32_t	data_len;

	data_len = zbx_alerter_serialize_result(&data, value, errcode, error, debug);
	zbx_ipc_socket_write(socket, ZBX_IPC_ALERTER_RESULT, data, data_len);

	zbx_free(data);
}

/******************************************************************************
 *                                                                            *
 * Purpose: create email In-Reply_To field value to group related messages    *
 *                                                                            *
 ******************************************************************************/
static char	*create_email_inreplyto(zbx_uint64_t mediatypeid, const char *sendto, zbx_uint64_t eventid)
{
	const char	*hex = "0123456789abcdef";
	char		*str = NULL;
	md5_state_t	state;
	md5_byte_t	hash[MD5_DIGEST_SIZE];
	int		i;
	size_t		str_alloc = 0, str_offset = 0;

	zbx_md5_init(&state);
	zbx_md5_append(&state, (const md5_byte_t *)sendto, strlen(sendto));
	zbx_md5_finish(&state, hash);

	zbx_snprintf_alloc(&str, &str_alloc, &str_offset, ZBX_FS_UI64 ".", eventid);

	for (i = 0; i < MD5_DIGEST_SIZE; i++)
	{
		zbx_chrcpy_alloc(&str, &str_alloc, &str_offset, hex[hash[i] >> 4]);
		zbx_chrcpy_alloc(&str, &str_alloc, &str_offset, hex[hash[i] & 15]);
	}

	zbx_snprintf_alloc(&str, &str_alloc, &str_offset, "." ZBX_FS_UI64 ".%s@zabbix.com", mediatypeid,
			zbx_dc_get_instanceid());

	return str;
}

/******************************************************************************
 *                                                                            *
 * Purpose: processes email alert                                             *
 *                                                                            *
 * Parameters: socket      - [IN] the connections socket                      *
 *             ipc_message - [IN] the ipc message with media type and alert   *
 *                                data                                        *
 *                                                                            *
 ******************************************************************************/
static void	alerter_process_email(zbx_ipc_socket_t *socket, zbx_ipc_message_t *ipc_message)
{
	zbx_uint64_t	alertid, mediatypeid, eventid;
	char		*sendto, *subject, *message, *smtp_server, *smtp_helo, *smtp_email, *username, *password,
			*inreplyto;
	unsigned short	smtp_port;
	unsigned char	smtp_security, smtp_verify_peer, smtp_verify_host, smtp_authentication, content_type;
	int		ret;
	char		error[MAX_STRING_LEN];

	zbx_alerter_deserialize_email(ipc_message->data, &alertid, &mediatypeid, &eventid, &sendto, &subject, &message,
			&smtp_server, &smtp_port, &smtp_helo, &smtp_email, &smtp_security, &smtp_verify_peer,
			&smtp_verify_host, &smtp_authentication, &username, &password, &content_type);

	inreplyto = create_email_inreplyto(mediatypeid, sendto, eventid);
	ret = send_email(smtp_server, smtp_port, smtp_helo, smtp_email, sendto, inreplyto, subject, message,
			smtp_security, smtp_verify_peer, smtp_verify_host, smtp_authentication, username, password,
			content_type, ALARM_ACTION_TIMEOUT, error, sizeof(error));

	alerter_send_result(socket, NULL, ret, (SUCCEED == ret ? NULL : error), NULL);

	zbx_free(inreplyto);
	zbx_free(sendto);
	zbx_free(subject);
	zbx_free(message);
	zbx_free(smtp_server);
	zbx_free(smtp_helo);
	zbx_free(smtp_email);
	zbx_free(username);
	zbx_free(password);
}

/******************************************************************************
 *                                                                            *
 * Purpose: processes SMS alert                                               *
 *                                                                            *
 * Parameters: socket      - [IN] the connections socket                      *
 *             ipc_message - [IN] the ipc message with media type and alert   *
 *                                data                                        *
 *                                                                            *
 ******************************************************************************/
static void	alerter_process_sms(zbx_ipc_socket_t *socket, zbx_ipc_message_t *ipc_message)
{
	zbx_uint64_t	alertid;
	char		*sendto, *message, *gsm_modem;
	int		ret;
	char		error[MAX_STRING_LEN];

	zbx_alerter_deserialize_sms(ipc_message->data, &alertid, &sendto, &message, &gsm_modem);

	/* SMS uses its own timeouts */
	ret = send_sms(gsm_modem, sendto, message, error, sizeof(error));
	alerter_send_result(socket, NULL, ret, (SUCCEED == ret ? NULL : error), NULL);

	zbx_free(sendto);
	zbx_free(message);
	zbx_free(gsm_modem);
}

/******************************************************************************
 *                                                                            *
 * Purpose: processes script alert                                            *
 *                                                                            *
 * Parameters: socket      - [IN] the connections socket                      *
 *             ipc_message - [IN] the ipc message with media type and alert   *
 *                                data                                        *
 *                                                                            *
 ******************************************************************************/
static void	alerter_process_exec(zbx_ipc_socket_t *socket, zbx_ipc_message_t *ipc_message)
{
	zbx_uint64_t	alertid;
	char		*command;
	int		ret;
	char		error[MAX_STRING_LEN];

	zbx_alerter_deserialize_exec(ipc_message->data, &alertid, &command);

	ret = execute_script_alert(command, error, sizeof(error));
	alerter_send_result(socket, NULL, ret, (SUCCEED == ret ? NULL : error), NULL);

	zbx_free(command);
}

/******************************************************************************
 *                                                                            *
 * Purpose: processes webhook alert                                           *
 *                                                                            *
 * Parameters: socket      - [IN] the connections socket                      *
 *             ipc_message - [IN] the ipc message with media type and alert   *
 *                                data                                        *
 *                                                                            *
 ******************************************************************************/
static void	alerter_process_webhook(zbx_ipc_socket_t *socket, zbx_ipc_message_t *ipc_message)
{
	char			*script_bin = NULL, *params = NULL, *error = NULL, *output = NULL;
	int			script_bin_sz, ret, timeout;
	unsigned char		debug;

	zbx_alerter_deserialize_webhook(ipc_message->data, &script_bin, &script_bin_sz, &timeout, &params, &debug);

	if (SUCCEED != (ret = zbx_es_is_env_initialized(&es_engine)))
		ret = zbx_es_init_env(&es_engine, &error);

	if (SUCCEED == ret)
	{
		zbx_es_set_timeout(&es_engine, timeout);

		if (ZBX_ALERT_DEBUG == debug)
			zbx_es_debug_enable(&es_engine);

		ret = zbx_es_execute(&es_engine, NULL, script_bin, script_bin_sz, params, &output, &error);
	}

	if (ZBX_ALERT_DEBUG == debug && SUCCEED == zbx_es_is_env_initialized(&es_engine))
	{
		alerter_send_result(socket, output, ret, error, zbx_es_debug_info(&es_engine));
		zbx_es_debug_disable(&es_engine);
	}
	else
		alerter_send_result(socket, output, ret, error, NULL);

	if (SUCCEED == zbx_es_fatal_error(&es_engine))
	{
		char	*errmsg = NULL;
		if (SUCCEED != zbx_es_destroy_env(&es_engine, &errmsg))
		{
			zabbix_log(LOG_LEVEL_WARNING,
					"Cannot destroy embedded scripting engine environment: %s", errmsg);
			zbx_free(errmsg);
		}
	}

	zbx_free(output);
	zbx_free(error);
	zbx_free(params);
	zbx_free(script_bin);
}

/******************************************************************************
 *                                                                            *
 * Purpose: periodically check table alerts and send notifications if needed  *
 *                                                                            *
 ******************************************************************************/
ZBX_THREAD_ENTRY(alerter_thread, args)
{
#define	STAT_INTERVAL	5	/* if a process is busy and does not sleep then update status not faster than */
				/* once in STAT_INTERVAL seconds */

	char			*error = NULL;
	int			success_num = 0, fail_num = 0;
	zbx_ipc_socket_t	alerter_socket;
	zbx_ipc_message_t	message;
	double			time_stat, time_idle = 0, time_now, time_read;

	process_type = ((zbx_thread_args_t *)args)->process_type;
	server_num = ((zbx_thread_args_t *)args)->server_num;
	process_num = ((zbx_thread_args_t *)args)->process_num;

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(program_type),
			server_num, get_process_type_string(process_type), process_num);

	update_selfmon_counter(ZBX_PROCESS_STATE_BUSY);

	zbx_setproctitle("%s [connecting to the database]", get_process_type_string(process_type));

	zbx_es_init(&es_engine);

	zbx_ipc_message_init(&message);

	if (FAIL == zbx_ipc_socket_open(&alerter_socket, ZBX_IPC_SERVICE_ALERTER, SEC_PER_MIN, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot connect to alert manager service: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	alerter_register(&alerter_socket);

	time_stat = zbx_time();

	zbx_setproctitle("%s #%d started", get_process_type_string(process_type), process_num);

	update_selfmon_counter(ZBX_PROCESS_STATE_BUSY);

	while (ZBX_IS_RUNNING())
	{
		time_now = zbx_time();

		if (STAT_INTERVAL < time_now - time_stat)
		{
			zbx_setproctitle("%s #%d [sent %d, failed %d alerts, idle " ZBX_FS_DBL " sec during "
					ZBX_FS_DBL " sec]", get_process_type_string(process_type), process_num,
					success_num, fail_num, time_idle, time_now - time_stat);

			time_stat = time_now;
			time_idle = 0;
			success_num = 0;
			fail_num = 0;
		}

		update_selfmon_counter(ZBX_PROCESS_STATE_IDLE);

		if (SUCCEED != zbx_ipc_socket_read(&alerter_socket, &message))
		{
			zabbix_log(LOG_LEVEL_CRIT, "cannot read alert manager service request");
			exit(EXIT_FAILURE);
		}

		update_selfmon_counter(ZBX_PROCESS_STATE_BUSY);

		time_read = zbx_time();
		time_idle += time_read - time_now;
		zbx_update_env(time_read);

		switch (message.code)
		{
			case ZBX_IPC_ALERTER_EMAIL:
				alerter_process_email(&alerter_socket, &message);
				break;
			case ZBX_IPC_ALERTER_SMS:
				alerter_process_sms(&alerter_socket, &message);
				break;
			case ZBX_IPC_ALERTER_EXEC:
				alerter_process_exec(&alerter_socket, &message);
				break;
			case ZBX_IPC_ALERTER_WEBHOOK:
				alerter_process_webhook(&alerter_socket, &message);
				break;
		}

		zbx_ipc_message_clean(&message);
	}

	zbx_setproctitle("%s #%d [terminated]", get_process_type_string(process_type), process_num);

	while (1)
		zbx_sleep(SEC_PER_MIN);

	zbx_ipc_socket_close(&alerter_socket);
}
