/*
** ZABBIX
** Copyright (C) 2000-2011 SIA Zabbix
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
#include "db.h"
#include "log.h"
#include "daemon.h"
#include "proxy.h"

#include "proxyconfig.h"
#include "../servercomms.h"

#define CONFIG_PROXYCONFIG_RETRY 120 /* seconds */

/******************************************************************************
 *                                                                            *
 * Function: process_configuration_sync                                       *
 *                                                                            *
 * Purpose: calculates checksum of config data                                *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments: never returns                                                    *
 *                                                                            *
 ******************************************************************************/
static void	process_configuration_sync()
{
	const char	*__function_name = "process_configuration_sync";

	zbx_sock_t	sock;
	char		*data;
	struct		zbx_json_parse jp;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	while (FAIL == connect_to_server(&sock, 600))	/* alarm */
	{
		zabbix_log(LOG_LEVEL_WARNING, "Connect to the server failed."
				" Retry after %d seconds",
				CONFIG_PROXYCONFIG_RETRY);

		sleep(CONFIG_PROXYCONFIG_RETRY);
	}

	if (FAIL == get_data_from_server(&sock,
			ZBX_PROTO_VALUE_PROXY_CONFIG, &data))
		goto exit;

	if (FAIL == zbx_json_open(data, &jp))
		goto exit;

	process_proxyconfig(&jp);
exit:
	disconnect_server(&sock);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: main_proxyconfig_loop                                            *
 *                                                                            *
 * Purpose: periodically request config data                                  *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments: never returns                                                    *
 *                                                                            *
 ******************************************************************************/
void	main_proxyconfig_loop()
{
	struct sigaction	phan;

	zabbix_log(LOG_LEVEL_DEBUG, "In main_proxyconfig_loop()");

	phan.sa_sigaction = child_signal_handler;
	sigemptyset(&phan.sa_mask);
	phan.sa_flags = SA_SIGINFO;
	sigaction(SIGALRM, &phan, NULL);

	zbx_setproctitle("configuration syncer [connecting to the database]]");

	DBconnect(ZBX_DB_CONNECT_NORMAL);

	for (;;)
	{
		zbx_setproctitle("configuration syncer [load configuration]");

		process_configuration_sync();

		zbx_setproctitle("configuration syncer"
				" [sleeping for %d seconds]",
				CONFIG_PROXYCONFIG_FREQUENCY);
		zabbix_log(LOG_LEVEL_DEBUG, "Sleeping for %d seconds",
				CONFIG_PROXYCONFIG_FREQUENCY);

		sleep(CONFIG_PROXYCONFIG_FREQUENCY);
	}
}
