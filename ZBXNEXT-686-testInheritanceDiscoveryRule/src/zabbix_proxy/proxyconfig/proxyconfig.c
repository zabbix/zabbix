/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "common.h"
#include "db.h"
#include "log.h"
#include "daemon.h"
#include "proxy.h"
#include "zbxself.h"

#include "proxyconfig.h"
#include "../servercomms.h"

#define CONFIG_PROXYCONFIG_RETRY	120	/* seconds */

extern unsigned char	process_type;

/******************************************************************************
 *                                                                            *
 * Function: process_configuration_sync                                       *
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
static void	process_configuration_sync()
{
	const char	*__function_name = "process_configuration_sync";

	zbx_sock_t	sock;
	char		*data;
	struct		zbx_json_parse jp;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	connect_to_server(&sock, 600, CONFIG_PROXYCONFIG_RETRY); /* retry till have a connection */

	if (FAIL == get_data_from_server(&sock, ZBX_PROTO_VALUE_PROXY_CONFIG, &data))
		goto exit;

	if ('\0' != *data)
		zabbix_log(LOG_LEVEL_WARNING, "Received configuration data from server. Datalen " ZBX_FS_SIZE_T,
				(zbx_fs_size_t)strlen(data));
	else
		zabbix_log(LOG_LEVEL_WARNING, "Cannot obtain configuration data from server. "
				"Proxy host name might not be matching that on the server.");

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
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments: never returns                                                    *
 *                                                                            *
 ******************************************************************************/
void	main_proxyconfig_loop()
{
	zabbix_log(LOG_LEVEL_DEBUG, "In main_proxyconfig_loop()");

	zbx_setproctitle("%s [connecting to the database]", get_process_type_string(process_type));

	DBconnect(ZBX_DB_CONNECT_NORMAL);

	for (;;)
	{
		zbx_setproctitle("%s [loading configuration]", get_process_type_string(process_type));

		process_configuration_sync();

		zbx_sleep_loop(CONFIG_PROXYCONFIG_FREQUENCY);
	}
}
