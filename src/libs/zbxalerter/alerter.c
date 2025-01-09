/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

#include "zbxalerter.h"
#include "alerter_defs.h"

#include "alerter_protocol.h"

#include "zbxtimekeeper.h"
#include "zbxlog.h"
#include "zbxcacheconfig.h"
#include "zbxembed.h"
#include "zbxexec.h"
#include "zbxhash.h"
#include "zbxipcservice.h"
#include "zbxmedia.h"
#include "zbxnix.h"
#include "zbxself.h"
#include "zbxstr.h"
#include "zbxtime.h"
#include "zbxexpression.h"
#include "zbxdbwrap.h"
#include "zbxalgo.h"
#include "zbxdbhigh.h"
#include "zbxthreads.h"

#define	ALARM_ACTION_TIMEOUT	40

ZBX_PTR_VECTOR_IMPL(am_source_stats_ptr, zbx_am_source_stats_t *)

static zbx_es_t	es_engine;

/******************************************************************************
 *                                                                            *
 * Purpose: executes script alert type                                        *
 *                                                                            *
 * Parameters: command       - [IN] command for execution                     *
 *             error         - [OUT] error string if function fails           *
 *             max_error_len - [IN] length of error buffer                    *
 *                                                                            *
 * Return value: SUCCEED if processed successfully, TIMEOUT_ERROR if          *
 *               timeout occurred, SIG_ERROR if interrupted by signal or FAIL *
 *               otherwise                                                    *
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
 * Parameters: socket - [IN] connection socket                                *
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
 * Parameters: socket  - [IN] connection socket                               *
 *             value   - [IN] value or error message                          *
 *             errcode - [IN]                                                 *
 *             error   - [IN] error message                                   *
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
 * Parameters: mediatypeid - [IN]                                             *
 *             sendto      - [IN] message's Send-To field                     *
 *             eventid     - [IN]                                             *
 *                                                                            *
 * Return value: In-Reply_To field value                                      *
 *                                                                            *
 ******************************************************************************/
static char	*create_email_inreplyto(zbx_uint64_t mediatypeid, const char *sendto, zbx_uint64_t eventid)
{
	const char	*hex = "0123456789abcdef";
	char		*str = NULL;
	md5_state_t	state;
	md5_byte_t	hash[ZBX_MD5_DIGEST_SIZE];
	int		i;
	size_t		str_alloc = 0, str_offset = 0;

	zbx_md5_init(&state);
	zbx_md5_append(&state, (const md5_byte_t *)sendto, strlen(sendto));
	zbx_md5_finish(&state, hash);

	zbx_snprintf_alloc(&str, &str_alloc, &str_offset, "<" ZBX_FS_UI64 ".", eventid);

	for (i = 0; i < ZBX_MD5_DIGEST_SIZE; i++)
	{
		zbx_chrcpy_alloc(&str, &str_alloc, &str_offset, hex[hash[i] >> 4]);
		zbx_chrcpy_alloc(&str, &str_alloc, &str_offset, hex[hash[i] & 15]);
	}

	zbx_snprintf_alloc(&str, &str_alloc, &str_offset, "." ZBX_FS_UI64 ".%s@zabbix.com>", mediatypeid,
			zbx_dc_get_instanceid());

	return str;
}

/****************************************************************************************
 *                                                                                      *
 * Purpose: processes email alert                                                       *
 *                                                                                      *
 * Parameters: socket                 - [IN] connection socket                          *
 *             ipc_message            - [IN] ipc message with media type and alert data *
 *             config_source_ip       - [IN]                                            *
 *             config_ssl_ca_location - [IN]                                            *
 *                                                                                      *
 ****************************************************************************************/
static void	alerter_process_email(zbx_ipc_socket_t *socket, zbx_ipc_message_t *ipc_message,
		const char *config_source_ip, const char *config_ssl_ca_location)
{
	zbx_uint64_t	alertid, mediatypeid, eventid, objectid;
	char		*sendto, *subject, *message, *smtp_server, *smtp_helo, *smtp_email, *username, *password,
			*inreplyto, *expression, *recovery_expression, *error = NULL;
	unsigned short	smtp_port;
	unsigned char	smtp_security, smtp_verify_peer, smtp_verify_host, smtp_authentication, message_format;
	int		object, source, ret;

	zbx_alerter_deserialize_email(ipc_message->data, &alertid, &mediatypeid, &eventid, &source, &object, &objectid,
			&sendto, &subject, &message, &smtp_server, &smtp_port, &smtp_helo, &smtp_email, &smtp_security,
			&smtp_verify_peer, &smtp_verify_host, &smtp_authentication, &username, &password,
			&message_format, &expression, &recovery_expression);

	inreplyto = create_email_inreplyto(mediatypeid, sendto, eventid);

	if (SMTP_AUTHENTICATION_NORMAL_PASSWORD == smtp_authentication)
	{
		/* fill data required by substitute_simple_macros_unmasked() for ZBX_MACRO_TYPE_ALERT_EMAIL */

		zbx_db_event	event = {.eventid = eventid, .source = source, .object = object, .objectid = objectid};

		memset(&event.trigger, 0, sizeof(zbx_db_trigger));
		event.trigger.expression = expression;
		event.trigger.recovery_expression = recovery_expression;

		zbx_dc_um_handle_t	*um_handle = zbx_dc_open_user_macros();

		zbx_substitute_simple_macros_unmasked(NULL, &event, NULL, NULL, NULL, NULL, NULL, NULL, NULL,
				NULL, NULL, NULL, &username, ZBX_MACRO_TYPE_ALERT_EMAIL, NULL, 0);
		zbx_substitute_simple_macros_unmasked(NULL, &event, NULL, NULL, NULL, NULL, NULL, NULL, NULL,
				NULL, NULL, NULL, &password, ZBX_MACRO_TYPE_ALERT_EMAIL, NULL, 0);

		zbx_dc_close_user_macros(um_handle);
		zbx_db_trigger_clean(&event.trigger);
	}
	else
	{
		zbx_free(expression);
		zbx_free(recovery_expression);
	}

	ret = send_email(smtp_server, smtp_port, smtp_helo, smtp_email, sendto, inreplyto, subject, message,
			smtp_security, smtp_verify_peer, smtp_verify_host, smtp_authentication, username, password,
			message_format, ALARM_ACTION_TIMEOUT, config_source_ip, config_ssl_ca_location, &error);

	alerter_send_result(socket, NULL, ret, (SUCCEED == ret ? NULL : error), NULL);

	zbx_free(error);
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
 * Parameters: socket             - [IN] connection socket                    *
 *             ipc_message        - [IN] ipc message with media type and      *
 *                                       alert data                           *
 *             config_sms_devices - [IN] allowed list of modem devices        *
 *                                                                            *
 ******************************************************************************/
static void	alerter_process_sms(zbx_ipc_socket_t *socket, zbx_ipc_message_t *ipc_message,
		const char *config_sms_devices)
{
	zbx_uint64_t	alertid;
	char		*sendto, *message, *gsm_modem, error[MAX_STRING_LEN];
	int		ret;

	zbx_alerter_deserialize_sms(ipc_message->data, &alertid, &sendto, &message, &gsm_modem);

	if (NULL != config_sms_devices && SUCCEED == zbx_str_in_list(config_sms_devices, gsm_modem, ','))
	{
		/* SMS uses its own timeouts */
		ret = send_sms(gsm_modem, sendto, message, error, sizeof(error));
	}
	else
	{
		zbx_snprintf(error, sizeof(error), "SMSDevices not configured for %s", gsm_modem);
		zabbix_log(LOG_LEVEL_WARNING, "failed to send SMS: %s", error);
		ret = FAIL;
	}

	alerter_send_result(socket, NULL, ret, (SUCCEED == ret ? NULL : error), NULL);

	zbx_free(sendto);
	zbx_free(message);
	zbx_free(gsm_modem);
}

/******************************************************************************
 *                                                                            *
 * Purpose: processes script alert                                            *
 *                                                                            *
 * Parameters: socket      - [IN] connection socket                           *
 *             ipc_message - [IN] ipc message with media type and alert data  *
 *                                                                            *
 ******************************************************************************/
static void	alerter_process_exec(zbx_ipc_socket_t *socket, zbx_ipc_message_t *ipc_message)
{
	zbx_uint64_t	alertid;
	char		*command, error[MAX_STRING_LEN];
	int		ret;

	zbx_alerter_deserialize_exec(ipc_message->data, &alertid, &command);

	ret = execute_script_alert(command, error, sizeof(error));
	alerter_send_result(socket, NULL, ret, (SUCCEED == ret ? NULL : error), NULL);

	zbx_free(command);
}

/***********************************************************************************
 *                                                                                 *
 * Purpose: processes webhook alert                                                *
 *                                                                                 *
 * Parameters: socket           - [IN] connection socket                           *
 *             ipc_message      - [IN] ipc message with media type and alert data  *
 *             config_source_ip - [IN]                                             *
 *                                                                                 *
 ***********************************************************************************/
static void	alerter_process_webhook(zbx_ipc_socket_t *socket, zbx_ipc_message_t *ipc_message,
		const char *config_source_ip)
{
	char		*script_bin = NULL, *params = NULL, *error = NULL, *output = NULL;
	int		script_bin_sz, ret, timeout;
	unsigned char	debug;

	zbx_alerter_deserialize_webhook(ipc_message->data, &script_bin, &script_bin_sz, &timeout, &params, &debug);

	if (SUCCEED != (ret = zbx_es_is_env_initialized(&es_engine)))
		ret = zbx_es_init_env(&es_engine, config_source_ip, &error);

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
ZBX_THREAD_ENTRY(zbx_alerter_thread, args)
{
	char			*error = NULL;
	int			success_num = 0, fail_num = 0;
	zbx_ipc_socket_t	alerter_socket;
	zbx_ipc_message_t	message;
	double			time_stat, time_idle = 0, time_now, time_read;
	const zbx_thread_info_t	*info = &((zbx_thread_args_t *)args)->info;
	int			server_num = ((zbx_thread_args_t *)args)->info.server_num;
	int			process_num = ((zbx_thread_args_t *)args)->info.process_num;
	unsigned char		process_type = ((zbx_thread_args_t *)args)->info.process_type;
	zbx_thread_alerter_args	*alerter_args_in = (zbx_thread_alerter_args *)(((zbx_thread_args_t *)args)->args);

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(info->program_type),
			server_num, get_process_type_string(process_type), process_num);

	zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_BUSY);

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

	zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_BUSY);

	while (ZBX_IS_RUNNING())
	{
		time_now = zbx_time();

#define	STAT_INTERVAL	5	/* if a process is busy and does not sleep then update status not faster than */
				/* once in STAT_INTERVAL seconds */

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

#undef STAT_INTERVAL

		zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_IDLE);

		if (SUCCEED != zbx_ipc_socket_read(&alerter_socket, &message))
		{
			if (ZBX_IS_RUNNING())
				zabbix_log(LOG_LEVEL_CRIT, "cannot read alert manager service request");
			exit(EXIT_FAILURE);
		}

		zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_BUSY);

		time_read = zbx_time();
		time_idle += time_read - time_now;
		zbx_update_env(get_process_type_string(process_type), time_read);

		switch (message.code)
		{
			case ZBX_IPC_ALERTER_EMAIL:
				alerter_process_email(&alerter_socket, &message, alerter_args_in->config_source_ip,
						alerter_args_in->config_ssl_ca_location);
				break;
			case ZBX_IPC_ALERTER_SMS:
				alerter_process_sms(&alerter_socket, &message, alerter_args_in->config_sms_devices);
				break;
			case ZBX_IPC_ALERTER_EXEC:
				alerter_process_exec(&alerter_socket, &message);
				break;
			case ZBX_IPC_ALERTER_WEBHOOK:
				alerter_process_webhook(&alerter_socket, &message, alerter_args_in->config_source_ip);
				break;
		}

		zbx_ipc_message_clean(&message);
	}

	zbx_setproctitle("%s #%d [terminated]", get_process_type_string(process_type), process_num);

	while (1)
		zbx_sleep(SEC_PER_MIN);
}
