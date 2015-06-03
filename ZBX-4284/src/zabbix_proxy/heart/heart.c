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
#include "daemon.h"
#include "log.h"
#include "zbxjson.h"
#include "zbxself.h"

#include "heart.h"
#include "../servercomms.h"

extern unsigned char	process_type;

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

	if (FAIL == connect_to_server(&sock, CONFIG_HEARTBEAT_FREQUENCY, 0)) /* do not retry */
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
void	main_heart_loop()
{
	int	start, sleeptime;

	zabbix_log(LOG_LEVEL_DEBUG, "In main_heart_loop()");

	for (;;)
	{
		start = time(NULL);

		zbx_setproctitle("%s [sending heartbeat message]", get_process_type_string(process_type));

		send_heartbeat();

		sleeptime = CONFIG_HEARTBEAT_FREQUENCY - (time(NULL) - start);

		zbx_sleep_loop(sleeptime);
	}
}
