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

#include "datasender.h"

#include "../taskmanager/taskmanager_proxy.h"

#include "version.h"
#include "zbxtimekeeper.h"
#include "zbxcommshigh.h"
#include "zbxlog.h"
#include "zbxnix.h"
#include "zbxdbwrap.h"
#include "zbxself.h"
#include "zbxtasks.h"
#include "zbxcompress.h"
#include "zbxtime.h"
#include "zbxjson.h"
#include "zbxproxybuffer.h"
#include "zbxcacheconfig.h"
#include "zbxdb.h"
#include "zbxdbhigh.h"

#define ZBX_DATASENDER_AVAILABILITY		0x0001
#define ZBX_DATASENDER_HISTORY			0x0002
#define ZBX_DATASENDER_DISCOVERY		0x0004
#define ZBX_DATASENDER_AUTOREGISTRATION		0x0008
#define ZBX_DATASENDER_TASKS			0x0010
#define ZBX_DATASENDER_TASKS_RECV		0x0020
#define ZBX_DATASENDER_TASKS_REQUEST		0x8000

#define ZBX_DATASENDER_DB_UPDATE	(ZBX_DATASENDER_HISTORY | ZBX_DATASENDER_DISCOVERY |		\
					ZBX_DATASENDER_AUTOREGISTRATION | ZBX_DATASENDER_TASKS |	\
					ZBX_DATASENDER_TASKS_RECV)

/******************************************************************************
 *                                                                            *
 * Purpose: Get current history upload state (disabled/enabled)               *
 *                                                                            *
 * Parameters: buffer - [IN] contents of a packet (JSON)                      *
 *             state  - [OUT]                                                 *
 *                                                                            *
 * Return value: SUCCEED - processed successfully                             *
 *               FAIL - an error occurred                                     *
 *                                                                            *
 ******************************************************************************/
static void	get_hist_upload_state(const char *buffer, int *state)
{
	struct zbx_json_parse	jp;
	char			value[MAX_STRING_LEN];

	if (NULL == buffer || '\0' == *buffer || SUCCEED != zbx_json_open(buffer, &jp))
		return;

	if (SUCCEED == zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_HISTORY_UPLOAD, value, sizeof(value), NULL))
	{
		if (0 == strcmp(value, ZBX_PROTO_VALUE_HISTORY_UPLOAD_ENABLED))
			*state = ZBX_PROXY_UPLOAD_ENABLED;
		else if (0 == strcmp(value, ZBX_PROTO_VALUE_HISTORY_UPLOAD_DISABLED))
			*state = ZBX_PROXY_UPLOAD_DISABLED;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: collects host availability, history, discovery, autoregistration  *
 *          data and sends 'proxy data' request                               *
 *                                                                            *
 ******************************************************************************/
static int	proxy_data_sender(int *more, int now, int *hist_upload_state, const zbx_thread_info_t *info,
		zbx_thread_datasender_args *args)
{
	static int		data_timestamp = 0, task_timestamp = 0, upload_state = SUCCEED;

	zbx_socket_t		sock;
	struct zbx_json		j;
	struct zbx_json_parse	jp, jp_tasks;
	int			availability_ts, history_records = 0, discovery_records = 0,
				areg_records = 0, more_history = 0, more_discovery = 0, more_areg = 0, proxy_delay,
				host_avail_records = 0, data_read = FAIL;
	zbx_uint64_t		history_lastid = 0, discovery_lastid = 0, areg_lastid = 0, flags = 0;
	zbx_timespec_t		ts;
	char			*error = NULL, *buffer = NULL;
	zbx_vector_tm_task_t	tasks;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	*more = ZBX_PROXY_DATA_DONE;
	zbx_json_init(&j, 16 * ZBX_KIBIBYTE);

	zbx_json_addstring(&j, ZBX_PROTO_TAG_REQUEST, ZBX_PROTO_VALUE_PROXY_DATA, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(&j, ZBX_PROTO_TAG_HOST, args->config_hostname, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(&j, ZBX_PROTO_TAG_SESSION, zbx_dc_get_session_token(), ZBX_JSON_TYPE_STRING);

	if (SUCCEED == upload_state && args->config_proxydata_frequency <= now - data_timestamp &&
			ZBX_PROXY_UPLOAD_DISABLED != *hist_upload_state)
	{
		if (SUCCEED == zbx_get_interface_availability_data(&j, &availability_ts))
			flags |= ZBX_DATASENDER_AVAILABILITY;

		history_records = zbx_pb_history_get_rows(&j, &history_lastid, &more_history);
		if (0 != history_lastid)
			flags |= ZBX_DATASENDER_HISTORY;

		discovery_records = zbx_pb_discovery_get_rows(&j, &discovery_lastid, &more_discovery);
		if (0 != discovery_records)
			flags |= ZBX_DATASENDER_DISCOVERY;

		areg_records = zbx_pb_autoreg_get_rows(&j, &areg_lastid, &more_areg);
		if (0 != areg_records)
			flags |= ZBX_DATASENDER_AUTOREGISTRATION;

		host_avail_records = zbx_proxy_get_host_active_availability(&j);

		if (ZBX_PROXY_DATA_MORE != more_history && ZBX_PROXY_DATA_MORE != more_discovery &&
						ZBX_PROXY_DATA_MORE != more_areg)
		{
			data_timestamp = now;
		}

		data_read = SUCCEED;
	}

	zbx_vector_tm_task_create(&tasks);

	if (SUCCEED == upload_state && ZBX_TASK_UPDATE_FREQUENCY <= now - task_timestamp)
	{
		task_timestamp = now;

		zbx_tm_get_remote_tasks(&tasks, 0, 0);

		if (0 != tasks.values_num)
		{
			zbx_tm_json_serialize_tasks(&j, &tasks);
			flags |= ZBX_DATASENDER_TASKS;
		}

		flags |= ZBX_DATASENDER_TASKS_REQUEST;
	}

	if (SUCCEED != upload_state)
		flags |= ZBX_DATASENDER_TASKS_REQUEST;

	if (0 != flags)
	{
		size_t	buffer_size, reserved;
		time_t	time_connect;

		if (ZBX_PROXY_DATA_MORE == more_history || ZBX_PROXY_DATA_MORE == more_discovery ||
				ZBX_PROXY_DATA_MORE == more_areg)
		{
			zbx_json_adduint64(&j, ZBX_PROTO_TAG_MORE, ZBX_PROXY_DATA_MORE);
			*more = ZBX_PROXY_DATA_MORE;
		}

		zbx_json_addstring(&j, ZBX_PROTO_TAG_VERSION, ZABBIX_VERSION, ZBX_JSON_TYPE_STRING);

		zbx_timespec(&ts);
		zbx_json_adduint64(&j, ZBX_PROTO_TAG_CLOCK, ts.sec);
		zbx_json_adduint64(&j, ZBX_PROTO_TAG_NS, ts.ns);

		if (0 != (flags & ZBX_DATASENDER_HISTORY) && 0 != (proxy_delay = zbx_proxy_get_delay(history_lastid)))
			zbx_json_adduint64(&j, ZBX_PROTO_TAG_PROXY_DELAY, proxy_delay);

		if (SUCCEED != zbx_compress(j.buffer, j.buffer_size, &buffer, &buffer_size))
		{
			zabbix_log(LOG_LEVEL_ERR,"cannot compress data: %s", zbx_compress_strerror());
			goto clean;
		}

		reserved = j.buffer_size;
		zbx_json_free(&j);	/* json buffer can be large, free as fast as possible */

		time_connect = time(NULL);

		zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_IDLE);

		/* retry till have a connection */
		if (FAIL == zbx_connect_to_server(&sock, args->config_source_ip, args->config_server_addrs, 600,
				args->config_timeout, args->config_proxydata_frequency, LOG_LEVEL_WARNING,
				args->zbx_config_tls))
		{
			zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_BUSY);

			goto clean;
		}

		zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_BUSY);

		upload_state = zbx_put_data_to_server(&sock, &buffer, buffer_size, reserved, &error);
		get_hist_upload_state(sock.buffer, hist_upload_state);

		if (SUCCEED != upload_state)
		{
			zbx_addrs_failover(args->config_server_addrs);

			*more = ZBX_PROXY_DATA_DONE;
			if (ZBX_PROXY_UPLOAD_DISABLED != *hist_upload_state)
			{
				zabbix_log(LOG_LEVEL_WARNING, "cannot send proxy data to server at \"%s\": %s",
						sock.peer, error);
			}
			zbx_free(error);
		}
		else
		{
			if (0 != (flags & ZBX_DATASENDER_AVAILABILITY))
				zbx_set_availability_diff_ts(availability_ts);

			if (SUCCEED == zbx_json_open(sock.buffer, &jp))
			{
				if (SUCCEED == zbx_json_brackets_by_name(&jp, ZBX_PROTO_TAG_TASKS, &jp_tasks))
					flags |= ZBX_DATASENDER_TASKS_RECV;
			}

			if (0 != (flags & ZBX_DATASENDER_DB_UPDATE))
			{
				zbx_db_begin();

				if (0 != (flags & ZBX_DATASENDER_TASKS))
				{
					zbx_tm_update_task_status(&tasks, ZBX_TM_STATUS_DONE);
					zbx_vector_tm_task_clear_ext(&tasks, zbx_tm_task_free);
				}

				if (0 != (flags & ZBX_DATASENDER_TASKS_RECV))
				{
					zbx_tm_json_deserialize_tasks(&jp_tasks, &tasks);
					zbx_tm_save_tasks(&tasks);
				}

				if (0 != (flags & ZBX_DATASENDER_HISTORY))
					zbx_pb_set_history_lastid(history_lastid);

				if (0 != (flags & ZBX_DATASENDER_DISCOVERY))
					zbx_pb_discovery_set_lastid(discovery_lastid);

				if (0 != (flags & ZBX_DATASENDER_AUTOREGISTRATION))
					zbx_pb_autoreg_set_lastid(areg_lastid);

				zbx_db_commit();
			}

			if (SUCCEED == data_read)
			{
				/* elapsed time being greater than connection timeout means */
				/* there were connection retries and the 'more' flag might  */
				/* not represent the latest database buffer state           */
				if (time(NULL) - time_connect <= args->config_timeout)
					zbx_pb_update_state(*more);
			}
		}

		zbx_dc_set_proxy_lastonline(now);

		zbx_disconnect_from_server(&sock);
	}
clean:
	zbx_vector_tm_task_clear_ext(&tasks, zbx_tm_task_free);
	zbx_vector_tm_task_destroy(&tasks);

	zbx_json_free(&j);
	zbx_free(buffer);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s more:%d flags:0x" ZBX_FS_UX64, __func__,
			zbx_result_string(upload_state), *more, flags);

	return history_records + discovery_records + areg_records + host_avail_records;
}

/******************************************************************************
 *                                                                            *
 * Purpose: periodically sends history and events to the server               *
 *                                                                            *
 ******************************************************************************/
ZBX_THREAD_ENTRY(datasender_thread, args)
{
	zbx_thread_datasender_args	*datasender_args_in = (zbx_thread_datasender_args *)
							(((zbx_thread_args_t *)args)->args);
	int				records = 0, hist_upload_state = ZBX_PROXY_UPLOAD_ENABLED, more;
	double				time_start, time_diff = 0.0, time_now;
	const zbx_thread_info_t		*info = &((zbx_thread_args_t *)args)->info;
	unsigned char			process_type = info->process_type;
	int				server_num = info->server_num;
	int				process_num = info->process_num;

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(info->program_type),
			server_num, get_process_type_string(process_type), process_num);

	zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_BUSY);

#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	zbx_tls_init_child(datasender_args_in->zbx_config_tls, datasender_args_in->zbx_get_program_type_cb_arg,
			zbx_dc_get_psk_by_identity);
#endif
	zbx_setproctitle("%s [connecting to the database]", get_process_type_string(process_type));

	zbx_db_connect(ZBX_DB_CONNECT_NORMAL);

	while (ZBX_IS_RUNNING())
	{
		time_now = zbx_time();
		zbx_update_env(get_process_type_string(process_type), time_now);

		zbx_setproctitle("%s [sent %d values in " ZBX_FS_DBL " sec, sending data]",
				get_process_type_string(process_type), records, time_diff);

		records = 0;
		time_start = time_now;

		do
		{
			records += proxy_data_sender(&more, (int)time_now, &hist_upload_state, info,
					datasender_args_in);

			time_now = zbx_time();
			time_diff = time_now - time_start;
		}
		while (ZBX_PROXY_DATA_MORE == more && time_diff < SEC_PER_MIN && ZBX_IS_RUNNING());

		zbx_setproctitle("%s [sent %d values in " ZBX_FS_DBL " sec, idle %d sec]",
				get_process_type_string(process_type), records, time_diff,
				ZBX_PROXY_DATA_MORE != more ? ZBX_TASK_UPDATE_FREQUENCY : 0);

		if (ZBX_PROXY_DATA_MORE != more)
			zbx_sleep_loop(info, ZBX_TASK_UPDATE_FREQUENCY);

	}

	zbx_setproctitle("%s #%d [terminated]", get_process_type_string(process_type), process_num);

	while (1)
		zbx_sleep(SEC_PER_MIN);
}
