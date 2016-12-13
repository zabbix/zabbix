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
#include "comms.h"
#include "db.h"
#include "log.h"
#include "daemon.h"
#include "zbxjson.h"
#include "proxy.h"
#include "zbxself.h"
#include "dbcache.h"

#include "datasender.h"
#include "../servercomms.h"
#include "../../libs/zbxcrypto/tls.h"

extern unsigned char	process_type, program_type;
extern int		server_num, process_num;

/******************************************************************************
 *                                                                            *
 * Function: proxy_data_sender                                                *
 *                                                                            *
 * Purpose: collects host availability, history, discovery, auto registration *
 *          data and sends 'proxy data' request                               *
 *                                                                            *
 ******************************************************************************/
static int	proxy_data_sender(int *more)
{
	const char	*__function_name = "proxy_data_sender";
	zbx_socket_t	sock;
	struct zbx_json	j;
	int		ret = FAIL, availability_ts, history_records, discovery_records, areg_records, more_history,
			more_discovery, more_areg;
	zbx_uint64_t	history_lastid = 0, discovery_lastid = 0, areg_lastid = 0;
	zbx_timespec_t	ts;
	char		*error = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_json_init(&j, 16 * ZBX_KIBIBYTE);

	zbx_json_addstring(&j, ZBX_PROTO_TAG_REQUEST, ZBX_PROTO_VALUE_PROXY_DATA, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(&j, ZBX_PROTO_TAG_HOST, CONFIG_HOSTNAME, ZBX_JSON_TYPE_STRING);

	if (SUCCEED == get_host_availability_data(&j, &availability_ts))
		ret = SUCCEED;

	if  (0 != (history_records = proxy_get_hist_data(&j, &history_lastid, &more_history)))
		ret = SUCCEED;

	if  (0 != (discovery_records = proxy_get_dhis_data(&j, &discovery_lastid, &more_discovery)))
		ret = SUCCEED;

	if  (0 != (areg_records = proxy_get_areg_data(&j, &areg_lastid, &more_areg)))
		ret = SUCCEED;

	if (SUCCEED == ret)
	{
		if (ZBX_PROXY_DATA_MORE == more_history || ZBX_PROXY_DATA_MORE == more_discovery ||
				ZBX_PROXY_DATA_MORE == more_areg)
		{
			zbx_json_adduint64(&j, ZBX_PROTO_TAG_MORE, ZBX_PROXY_DATA_MORE);
		}

		zbx_json_addstring(&j, ZBX_PROTO_TAG_VERSION, ZABBIX_VERSION, ZBX_JSON_TYPE_STRING);

		connect_to_server(&sock, 600, CONFIG_PROXYDATA_FREQUENCY); /* retry till have a connection */

		zbx_timespec(&ts);
		zbx_json_adduint64(&j, ZBX_PROTO_TAG_CLOCK, ts.sec);
		zbx_json_adduint64(&j, ZBX_PROTO_TAG_NS, ts.ns);

		if (SUCCEED != (ret = put_data_to_server(&sock, &j, &error)))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot send proxy data to server at \"%s\": %s",
					sock.peer, error);
			zbx_free(error);
		}

		disconnect_server(&sock);
	}

	if (SUCCEED == ret)
	{
		*more = more_history | more_discovery | more_areg;

		zbx_set_availability_diff_ts(availability_ts);

		if (0 != history_lastid || 0 != discovery_lastid || 0 != areg_lastid)
		{
			DBbegin();

			if (0 != history_lastid)
				proxy_set_hist_lastid(history_lastid);

			if (0 != discovery_lastid)
				proxy_set_dhis_lastid(discovery_lastid);

			if (0 != areg_lastid)
				proxy_set_areg_lastid(areg_lastid);

			DBcommit();
		}
	}
	else
		*more = ZBX_PROXY_DATA_DONE;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s more:%d", __function_name, zbx_result_string(ret), *more);

	return history_records + discovery_records + areg_records;
}

/******************************************************************************
 *                                                                            *
 * Function: main_datasender_loop                                             *
 *                                                                            *
 * Purpose: periodically sends history and events to the server               *
 *                                                                            *
 ******************************************************************************/
ZBX_THREAD_ENTRY(datasender_thread, args)
{
	int		records = 0, more;
	double		time_start, time_diff = 0.0;

	process_type = ((zbx_thread_args_t *)args)->process_type;
	server_num = ((zbx_thread_args_t *)args)->server_num;
	process_num = ((zbx_thread_args_t *)args)->process_num;

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(program_type),
			server_num, get_process_type_string(process_type), process_num);

#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	zbx_tls_init_child();
#endif
	zbx_setproctitle("%s [connecting to the database]", get_process_type_string(process_type));

	DBconnect(ZBX_DB_CONNECT_NORMAL);

	for (;;)
	{
		zbx_handle_log();

		zbx_setproctitle("%s [sent %d values in " ZBX_FS_DBL " sec, sending data]",
				get_process_type_string(process_type), records, time_diff);

		records = 0;
		time_start = zbx_time();

		do
		{
			records += proxy_data_sender(&more);
			time_diff = zbx_time() - time_start;
		}
		while (ZBX_PROXY_DATA_MORE == more && time_diff < SEC_PER_MIN);

		zbx_setproctitle("%s [sent %d values in " ZBX_FS_DBL " sec, idle %d sec]",
				get_process_type_string(process_type), records, time_diff, CONFIG_PROXYDATA_FREQUENCY);

		if (ZBX_PROXY_DATA_MORE != more)
			zbx_sleep_loop(CONFIG_PROXYDATA_FREQUENCY);
	}
}
