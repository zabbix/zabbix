/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

#include "trigger_housekeeper.h"

#include "common.h"
#include "log.h"
#include "zbxself.h"
#include "zbxservice.h"
#include "zbxipcservice.h"
#include "daemon.h"
#include "sighandler.h"
#include "dbcache.h"
#include "zbxalgo.h"
#include "zbxalgo.h"
#include "service_protocol.h"

extern unsigned char	process_type, program_type;
extern int		server_num, process_num;

extern int CONFIG_TRIGGERHOUSEKEEPING_FREQUENCY;

static int	housekeep_problems_without_triggers(void)
{
	DB_RESULT		result;
	DB_ROW			row;
	zbx_vector_uint64_t	ids;
	int			deleted = 0;

	zbx_vector_uint64_create(&ids);

	result = DBselect("select eventid"
			" from problem"
			" where source=%d"
				" and object=%d"
				" and not exists (select NULL from triggers where triggerid=objectid)",
				EVENT_SOURCE_TRIGGERS, EVENT_OBJECT_TRIGGER);

	while (NULL != (row = DBfetch(result)))
	{
		zbx_uint64_t	id;

		ZBX_STR2UINT64(id, row[0]);
		zbx_vector_uint64_append(&ids, id);
	}
	DBfree_result(result);

	zbx_vector_uint64_sort(&ids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	if (0 != ids.values_num)
	{
		if (SUCCEED == DBexecute_multiple_query("delete from problem where", "eventid", &ids))
			deleted = ids.values_num;
	}

	zbx_vector_uint64_destroy(&ids);

	return deleted;
}

ZBX_THREAD_ENTRY(trigger_housekeeper_thread, args)
{
	int	deleted;
	double	sec, time_now;
	char	sleeptext[25];

	process_type = ((zbx_thread_args_t *)args)->process_type;
	server_num = ((zbx_thread_args_t *)args)->server_num;
	process_num = ((zbx_thread_args_t *)args)->process_num;

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(program_type),
			server_num, get_process_type_string(process_type), process_num);

	update_selfmon_counter(ZBX_PROCESS_STATE_BUSY);

	zbx_setproctitle("%s [connecting to the database]", get_process_type_string(process_type));
	DBconnect(ZBX_DB_CONNECT_NORMAL);

	zbx_setproctitle("%s [startup idle for %d minutes]", get_process_type_string(process_type),
			CONFIG_TRIGGERHOUSEKEEPING_FREQUENCY);

	zbx_snprintf(sleeptext, sizeof(sleeptext), "idle for %d second(s)", CONFIG_TRIGGERHOUSEKEEPING_FREQUENCY);

	while (ZBX_IS_RUNNING())
	{
		zbx_sleep_loop(CONFIG_TRIGGERHOUSEKEEPING_FREQUENCY);

		if (!ZBX_IS_RUNNING())
			break;

		time_now = zbx_time();
		zbx_update_env(time_now);

		zbx_setproctitle("%s [removing deleted items data]", get_process_type_string(process_type));

		sec = zbx_time();
		deleted = housekeep_problems_without_triggers();
		sec = zbx_time() - sec;

		zbx_setproctitle("%s [deleted %d problems records in " ZBX_FS_DBL " sec, %s]",
				get_process_type_string(process_type), deleted, sec, sleeptext);
	}

	DBclose();

	zbx_setproctitle("%s #%d [terminated]", get_process_type_string(process_type), process_num);

	while (1)
		zbx_sleep(SEC_PER_MIN);
}

