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
#include "comms.h"
#include "db.h"
#include "log.h"
#include "daemon.h"
#include "zbxjson.h"
#include "proxy.h"

#include "datasender.h"
#include "../servercomms.h"

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
 * Author: Aleksander Vladishev                                               *
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
retry:
		if (SUCCEED == connect_to_server(&sock, 600))	/* alarm !!! */
		{
			put_data_to_server(&sock, j);
			disconnect_server(&sock);
		}
		else
		{
			sleep(CONFIG_PROXYDATA_FREQUENCY);
			goto retry;
		}
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
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	history_sender(struct zbx_json *j, int *records)
{
	zbx_sock_t	sock;
	zbx_uint64_t	lastid;

	zabbix_log(LOG_LEVEL_DEBUG, "In history_sender()");

	zbx_json_clean(j);
	zbx_json_addstring(j, ZBX_PROTO_TAG_REQUEST, ZBX_PROTO_VALUE_HISTORY_DATA, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(j, ZBX_PROTO_TAG_HOST, CONFIG_HOSTNAME, ZBX_JSON_TYPE_STRING);

	zbx_json_addarray(j, ZBX_PROTO_TAG_DATA);

	*records = proxy_get_hist_data(j, &lastid);

	zbx_json_close(j);

	zbx_json_adduint64(j, ZBX_PROTO_TAG_CLOCK, (int)time(NULL));

	if (*records > 0)
	{
retry:
		if (SUCCEED == connect_to_server(&sock, 600))	/* alarm !!! */
		{
			if (SUCCEED == put_data_to_server(&sock, j))
			{
				DBbegin();
				proxy_set_hist_lastid(lastid);
				DBcommit();
			}
			else
				*records = 0;

			disconnect_server(&sock);
		}
		else
		{
			sleep(CONFIG_PROXYDATA_FREQUENCY);
			goto retry;
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Function: dhistory_sender                                                  *
 *                                                                            *
 * Purpose:                                                                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	dhistory_sender(struct zbx_json *j, int *records)
{
	zbx_sock_t	sock;
	zbx_uint64_t	lastid;

	zabbix_log(LOG_LEVEL_DEBUG, "In dhistory_sender()");

	zbx_json_clean(j);
	zbx_json_addstring(j, ZBX_PROTO_TAG_REQUEST, ZBX_PROTO_VALUE_DISCOVERY_DATA, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(j, ZBX_PROTO_TAG_HOST, CONFIG_HOSTNAME, ZBX_JSON_TYPE_STRING);

	zbx_json_addarray(j, ZBX_PROTO_TAG_DATA);

	*records = proxy_get_dhis_data(j, &lastid);

	zbx_json_close(j);

	zbx_json_adduint64(j, ZBX_PROTO_TAG_CLOCK, (int)time(NULL));

	if (*records > 0)
	{
retry:
		if (SUCCEED == connect_to_server(&sock, 600))	/* alarm !!! */
		{
			if (SUCCEED == put_data_to_server(&sock, j))
			{
				DBbegin();
				proxy_set_dhis_lastid(lastid);
				DBcommit();
			}
			else
				*records = 0;

			disconnect_server(&sock);
		}
		else
		{
			sleep(CONFIG_PROXYDATA_FREQUENCY);
			goto retry;
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Function: autoreg_host_sender                                              *
 *                                                                            *
 * Purpose:                                                                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	autoreg_host_sender(struct zbx_json *j, int *records)
{
	zbx_sock_t	sock;
	zbx_uint64_t	lastid;

	zabbix_log(LOG_LEVEL_DEBUG, "In autoreg_host_sender()");

	zbx_json_clean(j);
	zbx_json_addstring(j, ZBX_PROTO_TAG_REQUEST, ZBX_PROTO_VALUE_AUTO_REGISTRATION_DATA, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(j, ZBX_PROTO_TAG_HOST, CONFIG_HOSTNAME, ZBX_JSON_TYPE_STRING);

	zbx_json_addarray(j, ZBX_PROTO_TAG_DATA);

	*records = proxy_get_areg_data(j, &lastid);

	zbx_json_close(j);

	zbx_json_adduint64(j, ZBX_PROTO_TAG_CLOCK, (int)time(NULL));

	if (*records > 0)
	{
retry:
		if (SUCCEED == connect_to_server(&sock, 600))	/* alarm !!! */
		{
			if (SUCCEED == put_data_to_server(&sock, j))
			{
				DBbegin();
				proxy_set_areg_lastid(lastid);
				DBcommit();
			}
			else
				*records = 0;

			disconnect_server(&sock);
		}
		else
		{
			sleep(CONFIG_PROXYDATA_FREQUENCY);
			goto retry;
		}
	}
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
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments: never returns                                                    *
 *                                                                            *
 ******************************************************************************/
int	main_datasender_loop()
{
	struct sigaction	phan;
	int			now, sleeptime,
				records, r;
	double			sec;
	struct zbx_json		j;

	zabbix_log(LOG_LEVEL_DEBUG, "In main_datasender_loop()");

	phan.sa_sigaction = child_signal_handler;
	sigemptyset(&phan.sa_mask);
	phan.sa_flags = SA_SIGINFO;
	sigaction(SIGALRM, &phan, NULL);

	zbx_setproctitle("data sender [connecting to the database]");

	DBconnect(ZBX_DB_CONNECT_NORMAL);

	zbx_json_init(&j, 16*1024);

	for (;;) {
		now = time(NULL);
		sec = zbx_time();

		zbx_setproctitle("data sender [sending data]");

		host_availability_sender(&j);

		records = 0;
retry_history:
		history_sender(&j, &r);
		records += r;

		if (r == ZBX_MAX_HRECORDS)
			goto retry_history;

retry_dhistory:
		dhistory_sender(&j, &r);
		records += r;

		if (r == ZBX_MAX_HRECORDS)
			goto retry_dhistory;

retry_autoreg_host:
		autoreg_host_sender(&j, &r);
		records += r;

		if (r == ZBX_MAX_HRECORDS)
			goto retry_autoreg_host;

		zabbix_log(LOG_LEVEL_DEBUG, "Datasender spent " ZBX_FS_DBL " seconds while processing %3d values.",
				zbx_time() - sec,
				records);

		sleeptime = CONFIG_PROXYDATA_FREQUENCY;

		if (sleeptime > 0) {
			zbx_setproctitle("data sender [sleeping for %d seconds]",
					sleeptime);
			zabbix_log(LOG_LEVEL_DEBUG, "Sleeping for %d seconds",
					sleeptime);
			sleep(sleeptime);
		}
	}

	zbx_json_free(&j);

	DBclose();
}
