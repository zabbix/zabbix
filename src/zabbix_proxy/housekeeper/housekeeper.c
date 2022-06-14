/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

#include "housekeeper.h"

#include "log.h"
#include "daemon.h"
#include "zbxself.h"
#include "dbcache.h"
#include "zbxrtc.h"


extern ZBX_THREAD_LOCAL unsigned char	process_type;
extern unsigned char			program_type;
extern ZBX_THREAD_LOCAL int		server_num, process_num;

static int	hk_period;

/* the maximum number of housekeeping periods to be removed per single housekeeping cycle */
#define HK_MAX_DELETE_PERIODS	4

/******************************************************************************
 *                                                                            *
 * Purpose: remove outdated information from historical table                 *
 *                                                                            *
 * Parameters: now - current timestamp                                        *
 *                                                                            *
 * Return value: number of rows records                                       *
 *                                                                            *
 ******************************************************************************/
static int	delete_history(const char *table, const char *fieldname, int now)
{
	DB_RESULT       result;
	DB_ROW          row;
	int             minclock, records = 0;
	zbx_uint64_t	lastid, maxid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() table:'%s' now:%d", __func__, table, now);

	DBbegin();

	result = DBselect(
			"select nextid"
			" from ids"
			" where table_name='%s'"
				" and field_name='%s'",
			table, fieldname);

	if (NULL == (row = DBfetch(result)))
		goto rollback;

	ZBX_STR2UINT64(lastid, row[0]);
	DBfree_result(result);

	result = DBselect("select min(clock) from %s",
			table);

	if (NULL == (row = DBfetch(result)) || SUCCEED == DBis_null(row[0]))
		goto rollback;

	minclock = atoi(row[0]);
	DBfree_result(result);

	result = DBselect("select max(id) from %s",
			table);

	if (NULL == (row = DBfetch(result)) || SUCCEED == DBis_null(row[0]))
		goto rollback;

	ZBX_STR2UINT64(maxid, row[0]);
	DBfree_result(result);

	records = DBexecute(
			"delete from %s"
			" where id<" ZBX_FS_UI64
				" and (clock<%d"
					" or (id<=" ZBX_FS_UI64 " and clock<%d))",
			table, maxid,
			now - CONFIG_PROXY_OFFLINE_BUFFER * SEC_PER_HOUR,
			lastid,
			MIN(now - CONFIG_PROXY_LOCAL_BUFFER * SEC_PER_HOUR,
					minclock + HK_MAX_DELETE_PERIODS * hk_period));

	DBcommit();

	return records;
rollback:
	DBfree_result(result);

	DBrollback();

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: remove outdated information from history                          *
 *                                                                            *
 * Parameters: now - current timestamp                                        *
 *                                                                            *
 * Return value: SUCCEED - information removed successfully                   *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
static int	housekeeping_history(int now)
{
        int	records = 0;

        zabbix_log(LOG_LEVEL_DEBUG, "In housekeeping_history()");

	records += delete_history("proxy_history", "history_lastid", now);
	records += delete_history("proxy_dhistory", "dhistory_lastid", now);
	records += delete_history("proxy_autoreg_host", "autoreg_host_lastid", now);

        return records;
}

static int	get_housekeeper_period(double time_slept)
{
	if (SEC_PER_HOUR > time_slept)
		return SEC_PER_HOUR;
	else if (24 * SEC_PER_HOUR < time_slept)
		return 24 * SEC_PER_HOUR;
	else
		return (int)time_slept;
}

ZBX_THREAD_ENTRY(housekeeper_thread, args)
{
	int			records, start, sleeptime;
	double			sec, time_slept, time_now;
	char			sleeptext[25];
	zbx_ipc_async_socket_t	rtc;

	process_type = ((zbx_thread_args_t *)args)->process_type;
	server_num = ((zbx_thread_args_t *)args)->server_num;
	process_num = ((zbx_thread_args_t *)args)->process_num;

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(program_type),
			server_num, get_process_type_string(process_type), process_num);

	update_selfmon_counter(ZBX_PROCESS_STATE_BUSY);

	if (0 == CONFIG_HOUSEKEEPING_FREQUENCY)
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
		zbx_snprintf(sleeptext, sizeof(sleeptext), "idle for %d hour(s)", CONFIG_HOUSEKEEPING_FREQUENCY);
	}

	zbx_rtc_subscribe(&rtc, process_type, process_num);

	while (ZBX_IS_RUNNING())
	{
		zbx_uint32_t	rtc_cmd;
		unsigned char	*rtc_data;
		int		hk_execute = 0;

		sec = zbx_time();

		while (SUCCEED == zbx_rtc_wait(&rtc, &rtc_cmd, &rtc_data, sleeptime) && 0 != rtc_cmd)
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

		if (0 == CONFIG_HOUSEKEEPING_FREQUENCY)
			sleeptime = ZBX_IPC_WAIT_FOREVER;
		else
			sleeptime = CONFIG_HOUSEKEEPING_FREQUENCY * SEC_PER_HOUR;

		time_now = zbx_time();
		time_slept = time_now - sec;
		zbx_update_env(time_now);

		hk_period = get_housekeeper_period(time_slept);

		start = time(NULL);

		zabbix_log(LOG_LEVEL_WARNING, "executing housekeeper");

		zbx_setproctitle("%s [connecting to the database]", get_process_type_string(process_type));

		DBconnect(ZBX_DB_CONNECT_NORMAL);

		zbx_setproctitle("%s [removing old history]", get_process_type_string(process_type));

		sec = zbx_time();
		records = housekeeping_history(start);
		sec = zbx_time() - sec;

		DBclose();

		zbx_dc_cleanup_data_sessions();

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
