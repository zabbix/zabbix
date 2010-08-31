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
#include "daemon.h"
#include "log.h"
#include "zbxjson.h"

#include "heart.h"
#include "../servercomms.h"

/******************************************************************************
 *                                                                            *
 * Function: send_heartbeat                                                   *
 *                                                                            *
 * Purpose:                                                                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	send_heartbeat()
{
	zbx_sock_t	sock;
	struct zbx_json	j;

	zabbix_log(LOG_LEVEL_DEBUG, "In send_heartbeat()");

	zbx_json_init(&j, 128);
	zbx_json_addstring(&j, "request", ZBX_PROTO_VALUE_PROXY_HEARTBEAT, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(&j, "host", CONFIG_HOSTNAME, ZBX_JSON_TYPE_STRING);

	if (FAIL == connect_to_server(&sock, CONFIG_HEARTBEAT_FREQUENCY)) /* alarm */
		return;

	if (FAIL == put_data_to_server(&sock, &j))
		zabbix_log(LOG_LEVEL_WARNING, "Heartbeat message failed");

	disconnect_server(&sock);
}

/******************************************************************************
 *                                                                            *
 * Function: main_heart_loop                                                  *
 *                                                                            *
 * Purpose: periodically send heartbeat message to the server                 *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void main_heart_loop()
{
	struct	sigaction phan;
	int	start, sleeptime;

	zabbix_log(LOG_LEVEL_DEBUG, "In main_heart_loop()");

        phan.sa_sigaction = child_signal_handler;
	sigemptyset(&phan.sa_mask);
	phan.sa_flags = SA_SIGINFO;
	sigaction(SIGALRM, &phan, NULL);

	if (CONFIG_HEARTBEAT_FREQUENCY == 0) {
		zbx_setproctitle("heartbeat sender [do nothing]");

		for (;;) /* Do nothing */
			sleep(3600);
	}

	for (;;) {
		start = time(NULL);

		zbx_setproctitle("heartbeat sender [sending heartbeat message]");

		send_heartbeat();

		sleeptime = CONFIG_HEARTBEAT_FREQUENCY - (time(NULL) - start);

		if (sleeptime > 0) {
			zbx_setproctitle("heartbeat sender [sleeping for %d seconds]",
					sleeptime);
			zabbix_log(LOG_LEVEL_DEBUG, "Sleeping for %d seconds",
					sleeptime);
			sleep(sleeptime);
		}
	}
}
