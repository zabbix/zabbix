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

#include "avail_manager.h"

#include "log.h"
#include "zbxself.h"
#include "zbxavailability.h"
#include "zbxipcservice.h"
#include "zbxnix.h"

extern ZBX_THREAD_LOCAL unsigned char	process_type;
extern unsigned char			program_type;
extern ZBX_THREAD_LOCAL int		server_num, process_num;
static sigset_t				orig_mask;

typedef struct
{
	zbx_uint64_t	hostid;
	int		lastaccess;
}
zbx_active_avail_proxy_t;

#define ZBX_AVAILABILITY_MANAGER_DELAY				1
#define ZBX_AVAILABILITY_MANAGER_FLUSH_DELAY_SEC		5
#define ZBX_AVAILABILITY_MANAGER_ACTIVE_HEARTBEAT_DELAY_SEC	10
#define ZBX_AVAILABILITY_MANAGER_PROXY_ACTIVE_AVAIL_DELAY_SEC	(SEC_PER_MIN * 10)
#define ZBX_AVAILABILITY_MANAGER_PROXY_ACTIVE_AUTOFLUSH_DELAY	(SEC_PER_MIN * 5)

static int	interface_availability_compare(const void *d1, const void *d2)
{
	const zbx_interface_availability_t	*ia1 = *(const zbx_interface_availability_t * const *)d1;
	const zbx_interface_availability_t	*ia2 = *(const zbx_interface_availability_t * const *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(ia1->interfaceid, ia2->interfaceid);

	return ia1->id - ia2->id;
}

static void	process_new_active_check_heartbeat(zbx_avail_active_hb_cache_t *cache,
		zbx_host_active_avail_t *avail_new)
{
	zbx_host_active_avail_t	*avail_cached, *avail_queued;

	if (NULL == (avail_cached = zbx_hashset_search(&cache->hosts, &avail_new->hostid)))
	{
		avail_new->active_status = INTERFACE_AVAILABLE_TRUE;

		zbx_hashset_insert(&cache->hosts, avail_new, sizeof(zbx_host_active_avail_t));

		if (NULL == (avail_queued = zbx_hashset_search(&cache->queue, &avail_new->hostid)))
		{
			zbx_hashset_insert(&cache->queue, avail_new, sizeof(zbx_host_active_avail_t));
		}
		else
		{
			avail_queued->active_status = INTERFACE_AVAILABLE_TRUE;
		}
	}
	else
	{
		avail_cached->heartbeat_freq = avail_new->heartbeat_freq;
		avail_cached->lastaccess_active = avail_new->lastaccess_active;
	}
}

static void	calculate_cached_active_check_availabilities(zbx_avail_active_hb_cache_t *cache)
{
	zbx_hashset_iter_t	iter;
	zbx_host_active_avail_t	*host;

	zbx_hashset_iter_reset(&cache->hosts, &iter);

	while (NULL != (host = (zbx_host_active_avail_t *)zbx_hashset_iter_next(&iter)))
	{
		int	now, prev_status;

		now = (int)time(NULL);

		prev_status = host->active_status;

		if (now - host->lastaccess_active <= host->heartbeat_freq * 2)
		{
			host->active_status = INTERFACE_AVAILABLE_TRUE;
		}
		else
			host->active_status = INTERFACE_AVAILABLE_FALSE;

		if (prev_status != host->active_status)
		{
			zbx_host_active_avail_t	*queued_host;

			if (NULL == (queued_host = zbx_hashset_search(&cache->queue, &host->hostid)))
				zbx_hashset_insert(&cache->queue, host, sizeof(zbx_host_active_avail_t));
			else
				queued_host->active_status = host->active_status;
		}
	}
}

static void	db_update_active_check_status(zbx_vector_uint64_t *hostids, int status)
{
	char	*sql = NULL;
	size_t	sql_alloc = 0, sql_offset = 0;

	if (0 == hostids->values_num)
		return;

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"update host_rtdata set active_available=%i where", status);

	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid", hostids->values, hostids->values_num);

	DBexecute("%s", sql);
	zbx_free(sql);
}

static void	flush_active_hb_queue(zbx_avail_active_hb_cache_t *cache)
{
	zbx_hashset_iter_t	iter;
	zbx_host_active_avail_t	*host;
	zbx_vector_uint64_t	status_unknown, status_available, status_unavailable;

	if (0 == cache->queue.num_data)
		return;

	zbx_vector_uint64_create(&status_unknown);
	zbx_vector_uint64_create(&status_available);
	zbx_vector_uint64_create(&status_unavailable);

	zbx_hashset_iter_reset(&cache->queue, &iter);

	DBbegin();

	while (NULL != (host = (zbx_host_active_avail_t *)zbx_hashset_iter_next(&iter)))
	{
		if (host->active_status == INTERFACE_AVAILABLE_UNKNOWN)
		{
			zbx_vector_uint64_append(&status_unknown, host->hostid);
		}
		else if (host->active_status == INTERFACE_AVAILABLE_TRUE)
		{
			zbx_vector_uint64_append(&status_available, host->hostid);
		}
		else if (host->active_status == INTERFACE_AVAILABLE_FALSE)
			zbx_vector_uint64_append(&status_unavailable, host->hostid);
	}

	db_update_active_check_status(&status_unknown, INTERFACE_AVAILABLE_UNKNOWN);
	db_update_active_check_status(&status_available, INTERFACE_AVAILABLE_TRUE);
	db_update_active_check_status(&status_unavailable, INTERFACE_AVAILABLE_FALSE);

	if (ZBX_DB_OK == DBcommit())
		zbx_hashset_clear(&cache->queue);

	zbx_vector_uint64_destroy(&status_unknown);
	zbx_vector_uint64_destroy(&status_available);
	zbx_vector_uint64_destroy(&status_unavailable);
}

static void	process_active_hb(zbx_avail_active_hb_cache_t *cache, zbx_ipc_message_t *message)
{
	zbx_host_active_avail_t	avail;

	zbx_availability_deserialize_active_hb(message->data, &avail);

	avail.lastaccess_active = (int)time(NULL);
	process_new_active_check_heartbeat(cache, &avail);
}

static void	send_hostdata_response(zbx_avail_active_hb_cache_t *cache, zbx_ipc_client_t *client)
{
	unsigned char		*data = NULL;
	zbx_uint32_t		data_len;

	data_len = zbx_availability_serialize_hostdata(&data, &cache->queue);
	zbx_ipc_client_send(client, ZBX_IPC_AVAILMAN_ACTIVE_HOSTDATA, data, data_len);
	zbx_free(data);
}

static void	send_avail_check_status_response(zbx_avail_active_hb_cache_t *cache, zbx_ipc_client_t *client,
		zbx_ipc_message_t *message)
{
	unsigned char		*data = NULL;
	zbx_uint32_t		data_len;
	zbx_uint64_t		hostid;
	int			status = INTERFACE_AVAILABLE_UNKNOWN;
	zbx_host_active_avail_t *host;

	zbx_availability_deserialize_active_status_request(message->data, &hostid);

	if (NULL == (host = zbx_hashset_search(&cache->queue, &hostid)))
	{
		if (NULL != (host = zbx_hashset_search(&cache->hosts, &hostid)))
			status = host->active_status;
	}
	else
		status = host->active_status;

	data_len = zbx_availability_serialize_active_status_response(&data, status);
	zbx_ipc_client_send(client, ZBX_IPC_AVAILMAN_ACTIVE_STATUS, data, data_len);
}

static void	process_confsync_diff(zbx_avail_active_hb_cache_t *cache, zbx_ipc_message_t *message)
{
	int			i;
	zbx_vector_uint64_t	hostids;

	zbx_vector_uint64_create(&hostids);

	zbx_availability_deserialize_hostids(message->data, &hostids);

	for (i = 0; i < hostids.values_num; i++)
	{
		zbx_host_active_avail_t	*queued_host;
		zbx_uint64_t		hostid;

		hostid = hostids.values[i];

		zbx_hashset_remove(&cache->hosts, &hostid);

		if (NULL != (queued_host = zbx_hashset_search(&cache->queue, &hostid)))
		{
			queued_host->active_status = INTERFACE_AVAILABLE_UNKNOWN;
		}
		else
		{
			zbx_host_active_avail_t	host_local;

			host_local.active_status = INTERFACE_AVAILABLE_UNKNOWN;
			host_local.hostid = hostid;
			host_local.lastaccess_active = 0;
			host_local.heartbeat_freq = 0;

			zbx_hashset_insert(&cache->queue, &host_local, sizeof(zbx_host_active_avail_t));
		}
	}

	zbx_vector_uint64_destroy(&hostids);
}

static void	init_active_availability(zbx_avail_active_hb_cache_t *cache)
{
	if (0 != (program_type & ZBX_PROGRAM_TYPE_SERVER))
	{
		if (ZBX_DB_OK > DBexecute("update host_rtdata hr set active_available=%i where exists (select null "
				"from hosts h where h.hostid=hr.hostid and proxy_hostid is null)", INTERFACE_AVAILABLE_UNKNOWN))
		{
			zabbix_log(LOG_LEVEL_WARNING, "Failed to reset availability status for active checks");
		}
	}
	else
	{
		DB_RESULT	result;
		DB_ROW		row;

		result = DBselect("select hostid from hosts");

		while (NULL != (row = DBfetch(result)))
		{
			zbx_host_active_avail_t avail_local;

			ZBX_STR2UINT64(avail_local.hostid, row[0]);
			avail_local.active_status = INTERFACE_AVAILABLE_UNKNOWN;
			avail_local.lastaccess_active = 0;
			avail_local.heartbeat_freq = 0;

			zbx_hashset_insert(&cache->queue, &avail_local, sizeof(zbx_host_active_avail_t));
		}
		DBfree_result(result);
	}
}
static void	flush_proxy_hostdata(zbx_avail_active_hb_cache_t *cache, zbx_ipc_message_t *message)
{
	zbx_uint64_t				proxy_hostid;
	zbx_vector_proxy_hostdata_ptr_t		hosts;
	zbx_proxy_hostdata_t			*host;
	zbx_vector_uint64_t			status_unknown, status_available, status_unavailable;
	zbx_active_avail_proxy_t		*proxy_avail;
	int					i;

	zbx_vector_proxy_hostdata_ptr_create(&hosts);

	zbx_availability_deserialize_proxy_hostdata(message->data, &hosts, &proxy_hostid);

	zbx_vector_uint64_create(&status_unknown);
	zbx_vector_uint64_create(&status_available);
	zbx_vector_uint64_create(&status_unavailable);

	for (i = 0; i < hosts.values_num; i++)
	{
		host = hosts.values[i];

		if (host->status == INTERFACE_AVAILABLE_UNKNOWN)
		{
			zbx_vector_uint64_append(&status_unknown, host->hostid);
		}
		else if (host->status == INTERFACE_AVAILABLE_TRUE)
		{
			zbx_vector_uint64_append(&status_available, host->hostid);
		}
		else if (host->status == INTERFACE_AVAILABLE_FALSE)
			zbx_vector_uint64_append(&status_unavailable, host->hostid);
	}

	DBbegin();

	db_update_active_check_status(&status_unknown, INTERFACE_AVAILABLE_UNKNOWN);
	db_update_active_check_status(&status_available, INTERFACE_AVAILABLE_TRUE);
	db_update_active_check_status(&status_unavailable, INTERFACE_AVAILABLE_FALSE);


	if (ZBX_DB_OK == DBcommit())
		zbx_hashset_clear(&cache->queue);

	if (NULL == (proxy_avail = zbx_hashset_search(&cache->proxy_avail, &proxy_hostid)))
	{
		zbx_active_avail_proxy_t	proxy_avail_local;

		proxy_avail_local.hostid = proxy_hostid;
		proxy_avail_local.lastaccess = (int)time(NULL);

		zbx_hashset_insert(&cache->proxy_avail, &proxy_avail_local, sizeof(zbx_active_avail_proxy_t));
	}
	else
		proxy_avail->lastaccess = (int)time(NULL);

	zbx_vector_uint64_destroy(&status_unknown);
	zbx_vector_uint64_destroy(&status_available);
	zbx_vector_uint64_destroy(&status_unavailable);
	zbx_vector_proxy_hostdata_ptr_clear_ext(&hosts, (zbx_proxy_hostdata_ptr_free_func_t)zbx_ptr_free);
	zbx_vector_proxy_hostdata_ptr_destroy(&hosts);
}

static void flush_all_hosts(zbx_avail_active_hb_cache_t *cache)
{
	zbx_hashset_iter_t	iter;
	zbx_host_active_avail_t	*host;

	if (0 == cache->hosts.num_data)
		return;

	zbx_hashset_iter_reset(&cache->hosts, &iter);

	while (NULL != (host = (zbx_host_active_avail_t *)zbx_hashset_iter_next(&iter)))
	{
		if (NULL == zbx_hashset_search(&cache->queue, &host->hostid))
		{
			zbx_hashset_insert(&cache->queue, host, sizeof(zbx_host_active_avail_t));
		}
	}
}

static void	active_checks_calculate_proxy_availability(zbx_avail_active_hb_cache_t *cache)
{
	zbx_hashset_iter_t		iter;
	zbx_active_avail_proxy_t	*proxy_avail;
	int				now;

	if (0 == cache->proxy_avail.num_data)
		return;

	now = time(NULL);
	zbx_hashset_iter_reset(&cache->proxy_avail, &iter);

	while (NULL != (proxy_avail = (zbx_active_avail_proxy_t *)zbx_hashset_iter_next(&iter)))
	{
		if (proxy_avail->lastaccess + ZBX_AVAILABILITY_MANAGER_PROXY_ACTIVE_AVAIL_DELAY_SEC <= now)
		{
			if (ZBX_DB_OK > DBexecute("update host_rtdata set active_available=%i"
					" where hostid in (select hostid from hosts where proxy_hostid=" ZBX_FS_UI64 ")",
					INTERFACE_AVAILABLE_UNKNOWN, proxy_avail->hostid))
			{
				continue;
			}
			zbx_hashset_iter_remove(&iter);
		}
	}
}

static void	update_proxy_heartbeat(zbx_avail_active_hb_cache_t *cache, zbx_ipc_message_t *message)
{
	zbx_active_avail_proxy_t	*proxy_avail;
	zbx_uint64_t			proxy_hostid;

	zbx_availability_deserialize_active_proxy_hb_update(message->data, &proxy_hostid);

	if (NULL != (proxy_avail = zbx_hashset_search(&cache->proxy_avail, &proxy_hostid)))
		proxy_avail->lastaccess = (int)time(NULL);
}

ZBX_THREAD_ENTRY(availability_manager_thread, args)
{
	zbx_ipc_service_t		service;
	char				*error = NULL;
	zbx_ipc_client_t		*client;
	zbx_ipc_message_t		*message;
	int				ret, processed_num = 0;
	double				time_stat, time_idle = 0, time_now, time_flush, sec, last_proxy_flush;
	zbx_vector_availability_ptr_t	interface_availabilities;
	zbx_timespec_t			timeout = {ZBX_AVAILABILITY_MANAGER_DELAY, 0};
	zbx_avail_active_hb_cache_t	active_hb_cache;

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

	if (FAIL == zbx_ipc_service_start(&service, ZBX_IPC_SERVICE_AVAILABILITY, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot start availability manager service: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	/* initialize statistics */
	time_stat = last_proxy_flush = zbx_time();
	time_flush = time_stat;
	active_hb_cache.last_proxy_avail_refresh = active_hb_cache.last_status_refresh = zbx_time();

	zbx_vector_availability_ptr_create(&interface_availabilities);
	zbx_hashset_create(&active_hb_cache.queue, 100, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_hashset_create(&active_hb_cache.hosts, 100, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_hashset_create(&active_hb_cache.proxy_avail, 100, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_setproctitle("%s #%d started", get_process_type_string(process_type), process_num);

	init_active_availability(&active_hb_cache);

	while (ZBX_IS_RUNNING())
	{
		time_now = zbx_time();

		if (STAT_INTERVAL < time_now - time_stat)
		{
			zbx_setproctitle("%s #%d [queued %d, processed %d values, idle "
					ZBX_FS_DBL " sec during " ZBX_FS_DBL " sec]",
					get_process_type_string(process_type), process_num,
					interface_availabilities.values_num, processed_num, time_idle, time_now - time_stat);

			time_stat = time_now;
			time_idle = 0;
			processed_num = 0;
		}

		update_selfmon_counter(ZBX_PROCESS_STATE_IDLE);
		ret = zbx_ipc_service_recv(&service, &timeout, &client, &message);
		update_selfmon_counter(ZBX_PROCESS_STATE_BUSY);
		sec = zbx_time();
		zbx_update_env(sec);

		if (ZBX_IPC_RECV_IMMEDIATE != ret)
			time_idle += sec - time_now;

		if (NULL != message)
		{
			switch (message->code)
			{
				case ZBX_IPC_AVAILABILITY_REQUEST:
					zbx_availability_deserialize(message->data, message->size,
							&interface_availabilities);
					break;
				case ZBX_IPC_AVAILMAN_ACTIVE_HB:
					process_active_hb(&active_hb_cache, message);
					break;
				case ZBX_IPC_AVAILMAN_ACTIVE_HOSTDATA:
					send_hostdata_response(&active_hb_cache, client);
					zbx_hashset_clear(&active_hb_cache.queue);
					break;
				case ZBX_IPC_AVAILMAN_ACTIVE_STATUS:
					send_avail_check_status_response(&active_hb_cache, client, message);
					break;
				case ZBX_IPC_AVAILMAN_CONFSYNC_DIFF:
					process_confsync_diff(&active_hb_cache, message);
					break;
				case ZBX_IPC_AVAILMAN_PROCESS_PROXY_HOSTDATA:
					flush_proxy_hostdata(&active_hb_cache, message);
					break;
				case ZBX_IPC_AVAILMAN_ACTIVE_PROXY_HB_UPDATE:
					update_proxy_heartbeat(&active_hb_cache, message);
					break;
				default:
					THIS_SHOULD_NEVER_HAPPEN;
			}

			zbx_ipc_message_free(message);
		}

		if (NULL != client)
			zbx_ipc_client_release(client);

		if (ZBX_AVAILABILITY_MANAGER_ACTIVE_HEARTBEAT_DELAY_SEC < time_now - active_hb_cache.last_status_refresh)
		{
			active_hb_cache.last_status_refresh = time_now;
			calculate_cached_active_check_availabilities(&active_hb_cache);
		}

		if (ZBX_AVAILABILITY_MANAGER_FLUSH_DELAY_SEC < time_now - time_flush)
		{
			time_flush = time_now;

			if (0 != interface_availabilities.values_num)
			{
				zbx_block_signals(&orig_mask);
				zbx_vector_availability_ptr_sort(&interface_availabilities,
						interface_availability_compare);

				zbx_db_update_interface_availabilities(&interface_availabilities);
				zbx_unblock_signals(&orig_mask);

				processed_num = interface_availabilities.values_num;
				zbx_vector_availability_ptr_clear_ext(&interface_availabilities,
						zbx_interface_availability_free);
			}

			if (0 != active_hb_cache.queue.num_data && 0 != (program_type & ZBX_PROGRAM_TYPE_SERVER))
			{
				flush_active_hb_queue(&active_hb_cache);
				zbx_hashset_clear(&active_hb_cache.queue);
			}
		}

		if (ZBX_AVAILABILITY_MANAGER_PROXY_ACTIVE_AVAIL_DELAY_SEC < time_now -
				active_hb_cache.last_proxy_avail_refresh && 0 != (program_type & ZBX_PROGRAM_TYPE_SERVER))
		{
			active_hb_cache.last_proxy_avail_refresh = time_now;
			active_checks_calculate_proxy_availability(&active_hb_cache);
		}

		if (0 != (program_type & ZBX_PROGRAM_TYPE_PROXY) &&
				last_proxy_flush + ZBX_AVAILABILITY_MANAGER_PROXY_ACTIVE_AUTOFLUSH_DELAY <= time_now)
		{
			flush_all_hosts(&active_hb_cache);
			last_proxy_flush = time_now;
		}
	}

	zbx_block_signals(&orig_mask);
	if (0 != interface_availabilities.values_num)
	{
		zbx_vector_availability_ptr_sort(&interface_availabilities, interface_availability_compare);
		zbx_db_update_interface_availabilities(&interface_availabilities);
	}
	DBclose();
	zbx_unblock_signals(&orig_mask);

	exit(EXIT_SUCCESS);
#undef STAT_INTERVAL
}
