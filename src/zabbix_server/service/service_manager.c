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
#define ZBX_FLAG_LLD_HOST_DISCOVERED			__UINT64_C(0x00000001)

typedef struct
{
	zbx_uint64_t		serviceid;
	zbx_uint64_t		current_eventid;
	int			status;
	int			algorithm;
	zbx_vector_ptr_t	service_problems;
	zbx_vector_ptr_t	service_problem_tags;
	zbx_vector_ptr_t	children;
	zbx_vector_ptr_t	parents;
}
zbx_service_t;

/* preprocessing manager data */
typedef struct
{
	zbx_hashset_t		services;	/* item value history cache */
}
zbx_service_manager_t;

/*#define ZBX_AVAILABILITY_MANAGER_DELAY		1*/
#define ZBX_SERVICE_MANAGER_SYNC_DELAY_SEC		5

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

static void	db_get_events(zbx_hashset_t *problem_events)
{
	DB_RESULT	result;
	zbx_event_t	*event = NULL;
	DB_ROW		row;
	zbx_uint64_t	eventid;

	result = DBselect("select p.eventid,p.clock,p.severity,t.tag,t.value"
			" from problem p"
			" left join problem_tag t"
				" on p.eventid=t.eventid"
			" where p.source=%d"
				" and p.object=%d"
				" and r_eventid is null"
			" order by p.eventid",
			EVENT_SOURCE_TRIGGERS, EVENT_OBJECT_TRIGGER);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(eventid, row[0]);

		if (NULL == event || eventid != event->eventid)
		{
			event = (zbx_event_t *)zbx_malloc(NULL, sizeof(zbx_event_t));

			event->eventid = eventid;
			event->clock = atoi(row[1]);
			event->value = TRIGGER_VALUE_PROBLEM;
			event->severity = atoi(row[2]);
			zbx_vector_ptr_create(&event->tags);
			zbx_hashset_insert(problem_events, &event, sizeof(zbx_event_t **));
		}

		if (FAIL == DBis_null(row[3]))
		{
			zbx_tag_t	*tag;

			tag = (zbx_tag_t *)zbx_malloc(NULL, sizeof(zbx_tag_t));
			tag->tag = zbx_strdup(NULL, row[3]);
			tag->value = zbx_strdup(NULL, row[4]);
			zbx_vector_ptr_append(&event->tags, tag);
		}
	}
	DBfree_result(result);
}

static void	sync_services(zbx_hashset_t *services)
{
	DB_RESULT	result;
	DB_ROW		row;
	zbx_service_t	service, *pservice;

	result = DBselect("select serviceid,status,algorithm from services");

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(service.serviceid, row[0]);
		service.status = atoi(row[1]);
		service.algorithm = atoi(row[2]);

		if (NULL == (pservice = zbx_hashset_search(services, &service)))
		{
			service.current_eventid = 0;
			zbx_vector_ptr_create(&service.children);
			zbx_vector_ptr_create(&service.parents);
			zbx_vector_ptr_create(&service.service_problem_tags);
			zbx_vector_ptr_create(&service.service_problems);

			zbx_hashset_insert(services, &service, sizeof(service));
			continue;
		}

		pservice->status = service.status;		/* status can only be changed by service manager */
		pservice->algorithm = service.algorithm;	/* TODO: recalculate services with changed algorithm */
	}
	DBfree_result(result);
}

static void	service_clean(zbx_service_t *service)
{
	zbx_vector_ptr_create(&service->children);
	zbx_vector_ptr_create(&service->parents);
	zbx_vector_ptr_create(&service->service_problem_tags);
	zbx_vector_ptr_create(&service->service_problems);
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
	zbx_service_manager_t	service_manager;

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

	zbx_hashset_create_ext(&service_manager.services, 1000, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC, (zbx_clean_func_t)service_clean,
			ZBX_DEFAULT_MEM_MALLOC_FUNC, ZBX_DEFAULT_MEM_REALLOC_FUNC, ZBX_DEFAULT_MEM_FREE_FUNC);

	db_get_events(&problem_events);	/* TODO: housekeeping*/

	zbx_setproctitle("%s #%d started", get_process_type_string(process_type), process_num);

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

		if (ZBX_IPC_RECV_IMMEDIATE != ret)
			time_idle += sec - time_now;

		if (NULL != message)
		{
			zbx_service_deserialize(message->data, message->size, &events);
			zbx_ipc_message_free(message);
			process_events(&problem_events, &recovery_events, &events);
			zbx_vector_ptr_clear(&events);
		}

		if (NULL != client)
			zbx_ipc_client_release(client);

		if (ZBX_SERVICE_MANAGER_SYNC_DELAY_SEC < time_now - time_flush)
		{
			sync_services(&service_manager.services);
			time_flush = time_now;
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
		}
	}

	zbx_hashset_destroy(&service_manager.services);
	zbx_hashset_destroy(&problem_events);
	zbx_hashset_destroy(&recovery_events);
	zbx_vector_ptr_destroy(&events);
	DBclose();

	exit(EXIT_SUCCESS);
#undef STAT_INTERVAL
}

