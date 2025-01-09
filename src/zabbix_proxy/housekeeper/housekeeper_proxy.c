/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

#include "housekeeper_proxy.h"

#include "zbxtimekeeper.h"
#include "zbxlog.h"
#include "zbxnix.h"
#include "zbxself.h"
#include "zbxcacheconfig.h"
#include "zbxrtc.h"
#include "zbxnum.h"
#include "zbxtime.h"
#include "zbx_rtc_constants.h"
#include "zbxipcservice.h"
#include "zbxdb.h"
#include "zbxstr.h"

/******************************************************************************
 *                                                                            *
 * Purpose: remove outdated information from historical table                 *
 *                                                                            *
 * Parameters: table                 - [IN]                                   *
 *             lastid_field          - [IN] lastid field name in ids table    *
 *             clock_field           - [IN] record creation timestamp field   *
 *             now                   - [IN] current timestamp                 *
 *             config_offline_buffer - [IN] hours to keep data when offline   *
 *             config_local_buffer   - [IN] hours to keep data                *
 *                                                                            *
 * Return value: number of rows records                                       *
 *                                                                            *
 ******************************************************************************/
static int	delete_history(const char *table, const char *lastid_field, const char *clock_field, int now,
		int config_offline_buffer, int config_local_buffer)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	int		records = 0;
	zbx_uint64_t	lastid, maxid;
	char		*condition = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() table:'%s' now:%d", __func__, table, now);

	zbx_db_begin();

	result = zbx_db_select(
			"select nextid"
			" from ids"
			" where table_name='%s'"
				" and field_name='%s'",
			table, lastid_field);

	if (NULL == (row = zbx_db_fetch(result)))
		goto rollback;

	ZBX_STR2UINT64(lastid, row[0]);
	zbx_db_free_result(result);

	result = zbx_db_select("select max(id) from %s",
			table);

	if (NULL == (row = zbx_db_fetch(result)) || SUCCEED == zbx_db_is_null(row[0]))
		goto rollback;

	ZBX_STR2UINT64(maxid, row[0]);
	zbx_db_free_result(result);

	if (0 != config_local_buffer)
		condition = zbx_dsprintf(NULL, " and %s<%d", clock_field, now - config_local_buffer * SEC_PER_HOUR);

	records = zbx_db_execute(
			"delete from %s"
			" where id<" ZBX_FS_UI64
				" and (%s<%d"
					" or (id<=" ZBX_FS_UI64 "%s))",
			table, maxid,
			clock_field, now - config_offline_buffer * SEC_PER_HOUR,
			lastid, ZBX_NULL2EMPTY_STR(condition));
	zbx_free(condition);

	zbx_db_commit();

	return records;
rollback:
	zbx_db_free_result(result);

	zbx_db_rollback();

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: remove outdated information from history                          *
 *                                                                            *
 * Parameters: now                   - [IN] current timestamp                 *
 *             config_offline_buffer - [IN] hours to keep data when offline   *
 *             config_local_buffer   - [IN] hours to keep data                *
 *                                                                            *
 * Return value: SUCCEED - information removed successfully                   *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
static int	housekeeping_history(int now, int config_offline_buffer, int config_local_buffer)
{
	int	records = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	records += delete_history("proxy_history", "history_lastid", "write_clock", now, config_offline_buffer,
			config_local_buffer);
	records += delete_history("proxy_dhistory", "dhistory_lastid", "clock", now, config_offline_buffer,
			config_local_buffer);
	records += delete_history("proxy_autoreg_host", "autoreg_host_lastid", "clock", now, config_offline_buffer,
			config_local_buffer);

	return records;
}

ZBX_THREAD_ENTRY(housekeeper_thread, args)
{
	int			records, start, sleeptime;
	double			sec, time_now;
	char			sleeptext[25];
	zbx_ipc_async_socket_t	rtc;
	const zbx_thread_info_t	*info = &((zbx_thread_args_t *)args)->info;
	unsigned char		process_type = info->process_type;
	int			server_num = info->server_num;
	int			process_num = info->process_num;
	zbx_uint32_t		rtc_msgs[] = {ZBX_RTC_HOUSEKEEPER_EXECUTE};

	zbx_thread_proxy_housekeeper_args	*housekeeper_args_in = (zbx_thread_proxy_housekeeper_args *)
			((((zbx_thread_args_t *)args))->args);

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(info->program_type),
			server_num, get_process_type_string(process_type), process_num);

	zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_BUSY);

	if (0 == housekeeper_args_in->config_housekeeping_frequency)
	{
		sleeptime = ZBX_IPC_WAIT_FOREVER;
		zbx_setproctitle("%s [waiting for user command]", get_process_type_string(process_type));
		zbx_snprintf(sleeptext, sizeof(sleeptext), "waiting for user command");
	}
	else
	{
		sleeptime = HOUSEKEEPER_STARTUP_DELAY * SEC_PER_MIN;
		zbx_setproctitle("%s [startup idle for %d minutes]", get_process_type_string(process_type),
				HOUSEKEEPER_STARTUP_DELAY);
		zbx_snprintf(sleeptext, sizeof(sleeptext), "idle for %d hour(s)",
				housekeeper_args_in->config_housekeeping_frequency);
	}

	zbx_rtc_subscribe(process_type, process_num, rtc_msgs, ARRSIZE(rtc_msgs), housekeeper_args_in->config_timeout,
			&rtc);

	while (ZBX_IS_RUNNING())
	{
		zbx_uint32_t	rtc_cmd;
		unsigned char	*rtc_data;
		int		hk_execute = 0;

		while (SUCCEED == zbx_rtc_wait(&rtc, info, &rtc_cmd, &rtc_data, sleeptime) && 0 != rtc_cmd)
		{
			switch (rtc_cmd)
			{
				case ZBX_RTC_HOUSEKEEPER_EXECUTE:
					if (0 == hk_execute)
					{
						zabbix_log(LOG_LEVEL_WARNING, "forced execution of the housekeeper");
						hk_execute = 1;
					}
					else
						zabbix_log(LOG_LEVEL_WARNING, "housekeeping procedure is already in"
								" progress");
					break;
				case ZBX_RTC_SHUTDOWN:
					goto out;
				default:
					continue;
			}

			sleeptime = 0;
		}

		if (!ZBX_IS_RUNNING())
			break;

		if (0 == housekeeper_args_in->config_housekeeping_frequency)
			sleeptime = ZBX_IPC_WAIT_FOREVER;
		else
			sleeptime = housekeeper_args_in->config_housekeeping_frequency * SEC_PER_HOUR;

		time_now = zbx_time();
		zbx_update_env(get_process_type_string(process_type), time_now);

		start = time(NULL);

		zabbix_log(LOG_LEVEL_WARNING, "executing housekeeper");

		zbx_setproctitle("%s [connecting to the database]", get_process_type_string(process_type));

		zbx_db_connect(ZBX_DB_CONNECT_NORMAL);

		zbx_setproctitle("%s [removing old history]", get_process_type_string(process_type));

		sec = zbx_time();
		records = housekeeping_history(start, housekeeper_args_in->config_proxy_offline_buffer,
				housekeeper_args_in->config_proxy_local_buffer);
		sec = zbx_time() - sec;

		zbx_db_close();

		zbx_dc_cleanup_sessions();
		zbx_dc_cleanup_autoreg_host();

		zabbix_log(LOG_LEVEL_WARNING, "%s [deleted %d records in " ZBX_FS_DBL " sec, %s]",
				get_process_type_string(process_type), records, sec, sleeptext);

		zbx_setproctitle("%s [deleted %d records in " ZBX_FS_DBL " sec, %s]",
				get_process_type_string(process_type), records, sec, sleeptext);
	}
out:
	zbx_setproctitle("%s #%d [terminated]", get_process_type_string(process_type), process_num);

	while (1)
		zbx_sleep(SEC_PER_MIN);
}
