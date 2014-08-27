/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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
static void	process_configuration_sync(size_t *data_size)
{
	const char	*__function_name = "process_configuration_sync";

	zbx_sock_t	sock;
	struct		zbx_json_parse jp;
	char		value[16];

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	/* reset the performance metric */
	*data_size = 0;

	connect_to_server(&sock, 600, CONFIG_PROXYCONFIG_RETRY); /* retry till have a connection */

	if (SUCCEED != get_data_from_server(&sock, ZBX_PROTO_VALUE_PROXY_CONFIG))
		goto out;

	if ('\0' == *sock.buffer)
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot obtain configuration data from server: empty string received");
		goto out;
	}

	if (SUCCEED != zbx_json_open(sock.buffer, &jp))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot obtain configuration data from server: %s", zbx_json_strerror());
		goto out;
	}

	*data_size = (size_t)(jp.end - jp.start + 1);     /* performance metric */

	/* if the answer is short then most likely it is a negative answer "response":"failed" */
	if (128 > *data_size &&
			SUCCEED == zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_RESPONSE, value, sizeof(value)) &&
			0 == strcmp(value, ZBX_PROTO_VALUE_FAILED))
	{
		char	*info = NULL;
		size_t	info_alloc = 0;

		zbx_json_value_by_name_dyn(&jp, ZBX_PROTO_TAG_INFO, &info, &info_alloc);

		zabbix_log(LOG_LEVEL_WARNING, "cannot obtain configuration data from server: %s",
				ZBX_NULL2EMPTY_STR(info));
		zbx_free(info);
		goto out;
	}

	zabbix_log(LOG_LEVEL_WARNING, "received configuration data from server, datalen " ZBX_FS_SIZE_T,
			(zbx_fs_size_t)*data_size);

	process_proxyconfig(&jp);
out:
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
void	main_proxyconfig_loop(void)
{
	size_t	data_size;
	double	sec;

	zbx_setproctitle("%s [connecting to the database]", get_process_type_string(process_type));

	DBconnect(ZBX_DB_CONNECT_NORMAL);

	for (;;)
	{
		zbx_setproctitle("%s [loading configuration]", get_process_type_string(process_type));

		sec = zbx_time();
		process_configuration_sync(&data_size);
		sec = zbx_time() - sec;

		zbx_setproctitle("%s [synced config " ZBX_FS_SIZE_T " bytes in " ZBX_FS_DBL " sec, idle %d sec]",
				get_process_type_string(process_type), (zbx_fs_size_t)data_size, sec,
				CONFIG_PROXYCONFIG_FREQUENCY);

		zbx_sleep_loop(CONFIG_PROXYCONFIG_FREQUENCY);
	}
}
