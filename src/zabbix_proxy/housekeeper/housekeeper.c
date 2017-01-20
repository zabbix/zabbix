/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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
#include "zbxself.h"

#include "housekeeper.h"

extern unsigned char	process_type, program_type;
extern int		server_num, process_num;

static int	hk_period;

/* the maximum number of housekeeping periods to be removed per single housekeeping cycle */
#define HK_MAX_DELETE_PERIODS	4

void	zbx_housekeeper_sigusr_handler(int flags)
{
	if (ZBX_RTC_HOUSEKEEPER_EXECUTE == ZBX_RTC_GET_MSG(flags))
	{
		if (0 < zbx_sleep_get_remainder())
		{
			zabbix_log(LOG_LEVEL_WARNING, "forced execution of the housekeeper");
			zbx_wakeup();
		}
		else
			zabbix_log(LOG_LEVEL_WARNING, "housekeeping procedure is already in progress");
	}
}

/******************************************************************************
 *                                                                            *
 * Function: delete_history                                                   *
 *                                                                            *
 * Purpose: remove outdated information from historical table                 *
 *                                                                            *
 * Parameters: now - current timestamp                                        *
 *                                                                            *
 * Return value: number of rows records                                       *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int	delete_history(const char *table, const char *fieldname, int now)
{
	const char	*__function_name = "delete_history";
	DB_RESULT       result;
	DB_ROW          row;
	int             minclock, records = 0;
	zbx_uint64_t	lastid, maxid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() table:'%s' now:%d",
			__function_name, table, now);

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
 * Function: housekeeping_history                                             *
 *                                                                            *
 * Purpose: remove outdated information from history                          *
 *                                                                            *
 * Parameters: now - current timestamp                                        *
 *                                                                            *
 * Return value: SUCCEED - information removed successfully                   *
 *               FAIL - otherwise                                             *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
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
	int	records, start, sleeptime;
	double	sec, time_slept;
	char	sleeptext[25];

	process_type = ((zbx_thread_args_t *)args)->process_type;
	server_num = ((zbx_thread_args_t *)args)->server_num;
	process_num = ((zbx_thread_args_t *)args)->process_num;

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(program_type),
			server_num, get_process_type_string(process_type), process_num);

	if (0 == CONFIG_HOUSEKEEPING_FREQUENCY)
	{
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

	zbx_set_sigusr_handler(zbx_housekeeper_sigusr_handler);

	for (;;)
	{
		sec = zbx_time();

		if (0 == CONFIG_HOUSEKEEPING_FREQUENCY)
			zbx_sleep_forever();
		else
			zbx_sleep_loop(sleeptime);

		zbx_handle_log();

		time_slept = zbx_time() - sec;

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

		zabbix_log(LOG_LEVEL_WARNING, "%s [deleted %d records in " ZBX_FS_DBL " sec, %s]",
				get_process_type_string(process_type), records, sec, sleeptext);

		zbx_setproctitle("%s [deleted %d records in " ZBX_FS_DBL " sec, %s]",
				get_process_type_string(process_type), records, sec, sleeptext);

		if (0 != CONFIG_HOUSEKEEPING_FREQUENCY)
			sleeptime = CONFIG_HOUSEKEEPING_FREQUENCY * SEC_PER_HOUR;
	}
}
