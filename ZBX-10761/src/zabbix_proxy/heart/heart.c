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
#include "daemon.h"
#include "log.h"
#include "zbxjson.h"
#include "zbxself.h"

#include "heart.h"
#include "../servercomms.h"
#include "../../libs/zbxcrypto/tls.h"

extern unsigned char	process_type, program_type;
extern int		server_num, process_num;

/******************************************************************************
 *                                                                            *
 * Function: send_heartbeat                                                   *
 *                                                                            *
 ******************************************************************************/
static int	send_heartbeat(void)
{
	zbx_socket_t	sock;
	struct zbx_json	j;
	int		ret = SUCCEED;
	char		*error = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In send_heartbeat()");

	zbx_json_init(&j, 128);
	zbx_json_addstring(&j, "request", ZBX_PROTO_VALUE_PROXY_HEARTBEAT, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(&j, "host", CONFIG_HOSTNAME, ZBX_JSON_TYPE_STRING);

	if (FAIL == connect_to_server(&sock, CONFIG_HEARTBEAT_FREQUENCY, 0)) /* do not retry */
		return FAIL;

	if (SUCCEED != put_data_to_server(&sock, &j, &error))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot send heartbeat message to server at \"%s\": %s",
				sock.peer, error);
		ret = FAIL;
	}

	zbx_free(error);
	disconnect_server(&sock);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: main_heart_loop                                                  *
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

#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	zbx_tls_init_child();
#endif
	last_stat_time = time(NULL);

	zbx_setproctitle("%s [sending heartbeat message]", get_process_type_string(process_type));

	for (;;)
	{
		zbx_handle_log();

		if (0 != sleeptime)
		{
			zbx_setproctitle("%s [sending heartbeat message %s in " ZBX_FS_DBL " sec, "
					"sending heartbeat message]",
					get_process_type_string(process_type),
					SUCCEED == res ? "success" : "failed", old_total_sec);
		}

		start = time(NULL);
		sec = zbx_time();
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

#undef STAT_INTERVAL
}
