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


#include "common.h"
#include "log.h"
#include "zbxself.h"
#include "zbxservice.h"
#include "daemon.h"
#include "zbxalgo.h"
#include "service_protocol.h"

#include "problem_housekeeper.h"

extern ZBX_THREAD_LOCAL unsigned char	process_type;
extern unsigned char			program_type;
extern ZBX_THREAD_LOCAL int		server_num, process_num;

extern int		CONFIG_PROBLEMHOUSEKEEPING_FREQUENCY;

static void	housekeep_service_problems(const zbx_vector_uint64_t *eventids)
{
	unsigned char	*data = NULL;
	size_t		data_alloc = 0, data_offset = 0;
	int		i;

	for (i = 0; i < eventids->values_num; i++)
		zbx_service_serialize_id(&data, &data_alloc, &data_offset, eventids->values[i]);

	if (NULL == data)
		return;

	zbx_service_flush(ZBX_IPC_SERVICE_SERVICE_PROBLEMS_DELETE, data, (zbx_uint32_t)data_offset);
	zbx_free(data);
}

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

		housekeep_service_problems(&ids);
	}

	zbx_vector_uint64_destroy(&ids);

	return deleted;
}

static void	zbx_trigger_housekeeper_sigusr_handler(int flags)
{
	if (ZBX_RTC_TRIGGER_HOUSEKEEPER_EXECUTE == ZBX_RTC_GET_MSG(flags))
	{
		if (0 < zbx_sleep_get_remainder())
		{
			zabbix_log(LOG_LEVEL_WARNING, "forced execution of the trigger housekeeper");
			zbx_wakeup();
		}
		else
			zabbix_log(LOG_LEVEL_WARNING, "trigger housekeeping procedure is already in progress");
	}
}

ZBX_THREAD_ENTRY(trigger_housekeeper_thread, args)
{
	int	deleted;
	double	sec;

	process_type = ((zbx_thread_args_t *)args)->process_type;
	server_num = ((zbx_thread_args_t *)args)->server_num;
	process_num = ((zbx_thread_args_t *)args)->process_num;

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(program_type),
			server_num, get_process_type_string(process_type), process_num);

	update_selfmon_counter(ZBX_PROCESS_STATE_BUSY);

	zbx_setproctitle("%s [connecting to the database]", get_process_type_string(process_type));
	DBconnect(ZBX_DB_CONNECT_NORMAL);

	zbx_setproctitle("%s [startup idle for %d second(s)]", get_process_type_string(process_type),
			CONFIG_PROBLEMHOUSEKEEPING_FREQUENCY);

	zbx_set_sigusr_handler(zbx_trigger_housekeeper_sigusr_handler);

	while (ZBX_IS_RUNNING())
	{
		zbx_sleep_loop(CONFIG_PROBLEMHOUSEKEEPING_FREQUENCY);

		if (!ZBX_IS_RUNNING())
			break;

		zbx_update_env(zbx_time());

		zbx_setproctitle("%s [removing deleted triggers problems]", get_process_type_string(process_type));

		sec = zbx_time();
		deleted = housekeep_problems_without_triggers();

		zbx_setproctitle("%s [deleted %d problems records in " ZBX_FS_DBL " sec, idle for %d second(s)]",
				get_process_type_string(process_type), deleted, zbx_time() - sec,
				CONFIG_PROBLEMHOUSEKEEPING_FREQUENCY);
	}

	DBclose();

	zbx_setproctitle("%s #%d [terminated]", get_process_type_string(process_type), process_num);

	while (1)
		zbx_sleep(SEC_PER_MIN);
}
