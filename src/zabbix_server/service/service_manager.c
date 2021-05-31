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

#include "common.h"
#include "log.h"
#include "zbxself.h"
#include "zbxservice.h"
#include "zbxipcservice.h"
#include "service_manager.h"
#include "daemon.h"
#include "sighandler.h"
#include "dbcache.h"
#include "zbxalgo.h"
#include "zbxalgo.h"
#include "service_protocol.h"

extern unsigned char	process_type, program_type;
extern int		server_num, process_num;

//#define ZBX_AVAILABILITY_MANAGER_DELAY		1
//#define ZBX_AVAILABILITY_MANAGER_FLUSH_DELAY_SEC	5

static void	event_clean(zbx_event_t *event)
{
	zbx_vector_ptr_clear_ext(&event->tags, (zbx_clean_func_t)zbx_free_tag);
	zbx_vector_ptr_destroy(&event->tags);
	zbx_free(event);
}

static void	event_ptr_clean(zbx_event_t **event)
{
	event_clean(*event);
}

static zbx_hash_t	default_uint64_ptr_hash_func(const void *d)
{
	return ZBX_DEFAULT_UINT64_HASH_FUNC(*(const zbx_uint64_t **)d);
}

static void	process_events(zbx_hashset_t *problem_events, zbx_hashset_t *recovery_events, zbx_vector_ptr_t *events)
{
	int	i;

	for (i = 0; i < events->values_num; i++)
	{
		zbx_event_t	*event, **ptr;

		event = events->values[i];

		switch (event->value)
		{
			case TRIGGER_VALUE_OK:
				if (NULL == (ptr = zbx_hashset_search(problem_events, &event)))
				{
					/* handle possible race condition when recovery is received before problem */
					zbx_hashset_insert(recovery_events, &event, sizeof(zbx_event_t **));
					continue;
				}

				event_clean(event);
				zbx_hashset_remove_direct(problem_events, ptr);
				break;
			case TRIGGER_VALUE_PROBLEM:
				if (NULL != (ptr = zbx_hashset_search(problem_events, &event)))
				{
					zabbix_log(LOG_LEVEL_ERR, "cannot process event \"" ZBX_FS_UI64 "\": event"
							" already processed", event->eventid);
					THIS_SHOULD_NEVER_HAPPEN;
					event_clean(event);
					continue;
				}

				if (NULL != (ptr = zbx_hashset_search(recovery_events, &event)))
				{
					/* handle possible race condition when recovery is received before problem */
					zbx_hashset_remove_direct(recovery_events, ptr);
					event_clean(event);
					continue;
				}

				zbx_hashset_insert(problem_events, &event, sizeof(zbx_event_t **));
				break;
			default:
				zabbix_log(LOG_LEVEL_ERR, "cannot process event \"" ZBX_FS_UI64 "\" unexpected value:%d",
						event->eventid, event->value);
				THIS_SHOULD_NEVER_HAPPEN;
				event_clean(event);
		}
	}
}

ZBX_THREAD_ENTRY(service_manager_thread, args)
{
	zbx_ipc_service_t	service;
	char			*error = NULL;
	zbx_ipc_client_t	*client;
	zbx_ipc_message_t	*message;
	int			ret, processed_num = 0;
	double			time_stat, time_idle = 0, time_now, time_flush, sec;
	zbx_vector_ptr_t	events;
	zbx_hashset_t		problem_events, recovery_events;

#define	STAT_INTERVAL	5	/* if a process is busy and does not sleep then update status not faster than */
				/* once in STAT_INTERVAL seconds */

	process_type = ((zbx_thread_args_t *)args)->process_type;
	server_num = ((zbx_thread_args_t *)args)->server_num;
	process_num = ((zbx_thread_args_t *)args)->process_num;

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(program_type),
				server_num, get_process_type_string(process_type), process_num);

	update_selfmon_counter(ZBX_PROCESS_STATE_BUSY);

	zbx_setproctitle("%s #%d [connecting to the database]", get_process_type_string(process_type), process_num);

	DBconnect(ZBX_DB_CONNECT_NORMAL);

	if (FAIL == zbx_ipc_service_start(&service, ZBX_IPC_SERVICE_SERVICE, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot start service manager service: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	/* initialize statistics */
	time_stat = zbx_time();
	time_flush = time_stat;

	zbx_vector_ptr_create(&events);
	zbx_hashset_create_ext(&problem_events, 1000, default_uint64_ptr_hash_func,
			zbx_default_uint64_ptr_compare_func, (zbx_clean_func_t)event_ptr_clean,
			ZBX_DEFAULT_MEM_MALLOC_FUNC, ZBX_DEFAULT_MEM_REALLOC_FUNC, ZBX_DEFAULT_MEM_FREE_FUNC);

	zbx_hashset_create_ext(&recovery_events, 1, default_uint64_ptr_hash_func,
			zbx_default_uint64_ptr_compare_func, (zbx_clean_func_t)event_ptr_clean,
			ZBX_DEFAULT_MEM_MALLOC_FUNC, ZBX_DEFAULT_MEM_REALLOC_FUNC, ZBX_DEFAULT_MEM_FREE_FUNC);

	zbx_setproctitle("%s #%d started", get_process_type_string(process_type), process_num);
//
	while (ZBX_IS_RUNNING())
	{
		time_now = zbx_time();
//
//		if (STAT_INTERVAL < time_now - time_stat)
//		{
//			zbx_setproctitle("%s #%d [queued %d, processed %d values, idle "
//					ZBX_FS_DBL " sec during " ZBX_FS_DBL " sec]",
//					get_process_type_string(process_type), process_num,
//					interface_availabilities.values_num, processed_num, time_idle, time_now - time_stat);
//
//			time_stat = time_now;
//			time_idle = 0;
//			processed_num = 0;
//		}
//
		update_selfmon_counter(ZBX_PROCESS_STATE_IDLE);
		ret = zbx_ipc_service_recv(&service, 60, &client, &message);
		update_selfmon_counter(ZBX_PROCESS_STATE_BUSY);
		sec = zbx_time();
		zbx_update_env(sec);
//
		if (ZBX_IPC_RECV_IMMEDIATE != ret)
			time_idle += sec - time_now;
//
		if (NULL != message)
		{
			zbx_service_deserialize(message->data, message->size, &events);
			zbx_ipc_message_free(message);
			process_events(&problem_events, &recovery_events, &events);
			zbx_vector_ptr_clear(&events);
		}
//
		if (NULL != client)
			zbx_ipc_client_release(client);
//
//		if (ZBX_AVAILABILITY_MANAGER_FLUSH_DELAY_SEC < time_now - time_flush)
//		{
//			time_flush = time_now;
//
//			if (0 == interface_availabilities.values_num)
//				continue;
//
//			zbx_block_signals(&orig_mask);
//			zbx_vector_availability_ptr_sort(&interface_availabilities, interface_availability_compare);
//			zbx_db_update_interface_availabilities(&interface_availabilities);
//			zbx_unblock_signals(&orig_mask);
//
//			processed_num = interface_availabilities.values_num;
//			zbx_vector_availability_ptr_clear_ext(&interface_availabilities,
//					zbx_interface_availability_free);
//		}
	}

	zbx_hashset_destroy(&problem_events);
	zbx_hashset_destroy(&recovery_events);
	zbx_vector_ptr_destroy(&events);
	DBclose();

	exit(EXIT_SUCCESS);
#undef STAT_INTERVAL
}

