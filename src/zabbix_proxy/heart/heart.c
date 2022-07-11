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

#include "heart.h"

#include "daemon.h"
#include "log.h"
#include "zbxjson.h"
#include "zbxself.h"

#include "zbxcrypto.h"
#include "zbxcompress.h"
#include "comms.h"

extern ZBX_THREAD_LOCAL unsigned char	process_type;
extern unsigned char			program_type;
extern ZBX_THREAD_LOCAL int		server_num, process_num;

extern zbx_vector_ptr_t	zbx_addrs;
extern char		*CONFIG_HOSTNAME;
extern char		*CONFIG_SOURCE_IP;
extern int		CONFIG_TIMEOUT;
extern unsigned int	configured_tls_connect_mode;

static int	send_heartbeat(void)
{
	zbx_socket_t		sock;
	struct zbx_json		j;
	int			ret = SUCCEED;
	char			*error = NULL, *buffer = NULL;
	size_t			buffer_size, reserved;

	zabbix_log(LOG_LEVEL_DEBUG, "In send_heartbeat()");

	zbx_json_init(&j, 128);
	zbx_json_addstring(&j, "request", ZBX_PROTO_VALUE_PROXY_HEARTBEAT, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(&j, "host", CONFIG_HOSTNAME, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(&j, ZBX_PROTO_TAG_VERSION, ZABBIX_VERSION, ZBX_JSON_TYPE_STRING);

	if (SUCCEED != zbx_compress(j.buffer, j.buffer_size, &buffer, &buffer_size))
	{
		zabbix_log(LOG_LEVEL_ERR,"cannot compress data: %s", zbx_compress_strerror());
		goto clean;
	}

	reserved = j.buffer_size;

	if (FAIL == (ret = connect_to_server(&sock, CONFIG_SOURCE_IP, &zbx_addrs, CONFIG_HEARTBEAT_FREQUENCY,
			CONFIG_TIMEOUT, configured_tls_connect_mode, 0, LOG_LEVEL_DEBUG))) /* do not retry */
	{
		goto clean;
	}

	if (SUCCEED != (ret = put_data_to_server(&sock, &buffer, buffer_size, reserved, &error)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot send heartbeat message to server at \"%s\": %s", sock.peer,
				error);
	}

	disconnect_server(&sock);
	zbx_free(error);
clean:
	zbx_free(buffer);
	zbx_json_free(&j);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: periodically send heartbeat message to the server                 *
 *                                                                            *
 ******************************************************************************/
ZBX_THREAD_ENTRY(heart_thread, args)
{
	int	start, sleeptime = 0, res;
	double	sec, total_sec = 0.0, old_total_sec = 0.0;
	time_t	last_stat_time;

#define STAT_INTERVAL	5	/* if a process is busy and does not sleep then update status not faster than */
				/* once in STAT_INTERVAL seconds */

	process_type = ((zbx_thread_args_t *)args)->process_type;
	server_num = ((zbx_thread_args_t *)args)->server_num;
	process_num = ((zbx_thread_args_t *)args)->process_num;

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(program_type),
			server_num, get_process_type_string(process_type), process_num);

	update_selfmon_counter(ZBX_PROCESS_STATE_BUSY);

#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	zbx_tls_init_child();
#endif
	last_stat_time = time(NULL);

	zbx_setproctitle("%s [sending heartbeat message]", get_process_type_string(process_type));

	while (ZBX_IS_RUNNING())
	{
		sec = zbx_time();
		zbx_update_env(sec);

		if (0 != sleeptime)
		{
			zbx_setproctitle("%s [sending heartbeat message %s in " ZBX_FS_DBL " sec, "
					"sending heartbeat message]",
					get_process_type_string(process_type),
					SUCCEED == res ? "success" : "failed", old_total_sec);
		}

		start = time(NULL);
		res = send_heartbeat();
		total_sec += zbx_time() - sec;

		sleeptime = CONFIG_HEARTBEAT_FREQUENCY - (time(NULL) - start);

		if (0 != sleeptime || STAT_INTERVAL <= time(NULL) - last_stat_time)
		{
			if (0 == sleeptime)
			{
				zbx_setproctitle("%s [sending heartbeat message %s in " ZBX_FS_DBL " sec, "
						"sending heartbeat message]",
						get_process_type_string(process_type),
						SUCCEED == res ? "success" : "failed", total_sec);

			}
			else
			{
				zbx_setproctitle("%s [sending heartbeat message %s in " ZBX_FS_DBL " sec, "
						"idle %d sec]",
						get_process_type_string(process_type),
						SUCCEED == res ? "success" : "failed", total_sec, sleeptime);

				old_total_sec = total_sec;
			}
			total_sec = 0.0;
			last_stat_time = time(NULL);
		}

		zbx_sleep_loop(sleeptime);
	}

	zbx_setproctitle("%s #%d [terminated]", get_process_type_string(process_type), process_num);

	while (1)
		zbx_sleep(SEC_PER_MIN);
#undef STAT_INTERVAL
}
