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
#include "db.h"
#include "log.h"
#include "daemon.h"
#include "zbxself.h"

#include "housekeeper.h"

extern unsigned char	process_type;

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
					minclock + 4 * CONFIG_HOUSEKEEPING_FREQUENCY * SEC_PER_HOUR));

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
static int housekeeping_history(int now)
{
        int	records = 0;

        zabbix_log(LOG_LEVEL_DEBUG, "In housekeeping_history()");

	records += delete_history("proxy_history", "history_lastid", now);
	records += delete_history("proxy_dhistory", "dhistory_lastid", now);
	records += delete_history("proxy_autoreg_host", "autoreg_host_lastid", now);

        return records;
}

void	main_housekeeper_loop(void)
{
	int	records, start, sleeptime;
	double	sec;

	for (;;)
	{
		start = time(NULL);

		zabbix_log(LOG_LEVEL_WARNING, "executing housekeeper");

		zbx_setproctitle("%s [connecting to the database]", get_process_type_string(process_type));

		DBconnect(ZBX_DB_CONNECT_NORMAL);

		zbx_setproctitle("%s [removing old history]", get_process_type_string(process_type));

		sec = zbx_time();
		records = housekeeping_history(start);
		sec = zbx_time() - sec;

		DBclose();

		sleeptime = CONFIG_HOUSEKEEPING_FREQUENCY * SEC_PER_HOUR - (time(NULL) - start);

		zabbix_log(LOG_LEVEL_WARNING, "%s [deleted %d records in " ZBX_FS_DBL " sec, idle %d sec]",
				get_process_type_string(process_type), records, sec, sleeptime);

		zbx_setproctitle("%s [deleted %d records in " ZBX_FS_DBL " sec, idle %d sec]",
				get_process_type_string(process_type), records, sec, sleeptime);

		zbx_sleep_loop(sleeptime);
	}
}
