/*
** ZABBIX
** Copyright (C) 2000-2006 SIA Zabbix
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
#include "comms.h"
#include "db.h"
#include "log.h"
#include "daemon.h"
#include "zbxjson.h"
#include "proxy.h"
#include "zbxself.h"

#include "datasender.h"
#include "../servercomms.h"

extern unsigned char	process_type;

/******************************************************************************
 *                                                                            *
 * Function: host_availability_sender                                         *
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
static void	host_availability_sender(struct zbx_json *j)
{
	const char	*__function_name = "host_availability_sender";
	zbx_sock_t	sock;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_json_clean(j);
	zbx_json_addstring(j, ZBX_PROTO_TAG_REQUEST, ZBX_PROTO_VALUE_HOST_AVAILABILITY, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(j, ZBX_PROTO_TAG_HOST, CONFIG_HOSTNAME, ZBX_JSON_TYPE_STRING);

	if (SUCCEED == get_host_availability_data(j))
	{
		connect_to_server(&sock, 600, CONFIG_PROXYDATA_FREQUENCY); /* retry till have a connection */
		put_data_to_server(&sock, j);
		disconnect_server(&sock);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: history_sender                                                   *
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
static void	history_sender(struct zbx_json *j, int *records, const char *tag,
		int (*f_get_data)(), void (*f_set_lastid)())
{
	const char	*__function_name = "history_sender";

	zbx_sock_t	sock;
	zbx_uint64_t	lastid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_json_clean(j);
	zbx_json_addstring(j, ZBX_PROTO_TAG_REQUEST, tag, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(j, ZBX_PROTO_TAG_HOST, CONFIG_HOSTNAME, ZBX_JSON_TYPE_STRING);

	zbx_json_addarray(j, ZBX_PROTO_TAG_DATA);

	*records = f_get_data(j, &lastid);

	zbx_json_close(j);

	if (*records > 0)
	{
		connect_to_server(&sock, 600, CONFIG_PROXYDATA_FREQUENCY); /* retry till have a connection */

		zbx_json_adduint64(j, ZBX_PROTO_TAG_CLOCK, (int)time(NULL));

		if (SUCCEED == put_data_to_server(&sock, j))
		{
			DBbegin();
			f_set_lastid(lastid);
			DBcommit();
		}
		else
			*records = 0;

		disconnect_server(&sock);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: main_datasender_loop                                             *
 *                                                                            *
 * Purpose: periodically sends history and events to the server               *
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
void	main_datasender_loop()
{
	int		records, r;
	double		sec;
	struct zbx_json	j;

	zabbix_log(LOG_LEVEL_DEBUG, "In main_datasender_loop()");

	zbx_setproctitle("%s [connecting to the database]", get_process_type_string(process_type));

	DBconnect(ZBX_DB_CONNECT_NORMAL);

	zbx_json_init(&j, 16 * ZBX_KIBIBYTE);

	for (;;)
	{
		sec = zbx_time();

		zbx_setproctitle("%s [sending data]", get_process_type_string(process_type));

		host_availability_sender(&j);

		records = 0;
retry_history:
		history_sender(&j, &r, ZBX_PROTO_VALUE_HISTORY_DATA,
				proxy_get_hist_data, proxy_set_hist_lastid);
		records += r;

		if (ZBX_MAX_HRECORDS == r)
			goto retry_history;
retry_dhistory:
		history_sender(&j, &r, ZBX_PROTO_VALUE_DISCOVERY_DATA,
				proxy_get_dhis_data, proxy_set_dhis_lastid);
		records += r;

		if (ZBX_MAX_HRECORDS == r)
			goto retry_dhistory;
retry_autoreg_host:
		history_sender(&j, &r, ZBX_PROTO_VALUE_AUTO_REGISTRATION_DATA,
				proxy_get_areg_data, proxy_set_areg_lastid);
		records += r;

		if (ZBX_MAX_HRECORDS == r)
			goto retry_autoreg_host;

		zabbix_log(LOG_LEVEL_DEBUG, "Datasender spent " ZBX_FS_DBL " seconds while processing %3d values.",
				zbx_time() - sec, records);

		zbx_sleep_loop(CONFIG_PROXYDATA_FREQUENCY);
	}
}
