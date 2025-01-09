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

#include "timer.h"

#include "zbxtimekeeper.h"
#include "zbxalgo.h"
#include "zbxdb.h"
#include "zbxdbhigh.h"
#include "zbxstr.h"
#include "zbxlog.h"
#include "zbxcacheconfig.h"
#include "zbxnix.h"
#include "zbxself.h"
#include "zbxnum.h"
#include "zbxtime.h"
#include "zbx_host_constants.h"
#include "zbxservice.h"
#include "zbxserialize.h"

/* addition data for event maintenance calculations to pair with zbx_event_suppress_query_t */
typedef struct
{
	zbx_uint64_t			eventid;
	zbx_vector_uint64_pair_t	maintenances;
}
zbx_event_suppress_data_t;

/******************************************************************************
 *                                                                            *
 * Purpose: logs host maintenance changes                                     *
 *                                                                            *
 ******************************************************************************/
static void	log_host_maintenance_update(const zbx_host_maintenance_diff_t* diff)
{
	char	*msg = NULL;
	size_t	msg_alloc = 0, msg_offset = 0;
	int	maintenance_off = 0;

	if (0 != (diff->flags & ZBX_FLAG_HOST_MAINTENANCE_UPDATE_MAINTENANCE_STATUS))
	{
		if (HOST_MAINTENANCE_STATUS_ON == diff->maintenance_status)
		{
			zbx_snprintf_alloc(&msg, &msg_alloc, &msg_offset, "putting host (" ZBX_FS_UI64 ") into",
					diff->hostid);
		}
		else
		{
			maintenance_off = 1;
			zbx_snprintf_alloc(&msg, &msg_alloc, &msg_offset, "taking host (" ZBX_FS_UI64 ") out of",
				diff->hostid);
		}
	}
	else
		zbx_snprintf_alloc(&msg, &msg_alloc, &msg_offset, "changing host (" ZBX_FS_UI64 ")", diff->hostid);

	zbx_strcpy_alloc(&msg, &msg_alloc, &msg_offset, " maintenance");

	if (0 != (diff->flags & ZBX_FLAG_HOST_MAINTENANCE_UPDATE_MAINTENANCEID) && 0 != diff->maintenanceid)
		zbx_snprintf_alloc(&msg, &msg_alloc, &msg_offset, "(" ZBX_FS_UI64 ")", diff->maintenanceid);

	if (0 != (diff->flags & ZBX_FLAG_HOST_MAINTENANCE_UPDATE_MAINTENANCE_TYPE) && 0 == maintenance_off)
	{
		const char	*description[] = {"with data collection", "without data collection"};

		zbx_snprintf_alloc(&msg, &msg_alloc, &msg_offset, " %s", description[diff->maintenance_type]);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "%s", msg);
	zbx_free(msg);
}

/******************************************************************************
 *                                                                            *
 * Purpose: updates host maintenance properties in database                   *
 *                                                                            *
 ******************************************************************************/
static void	db_update_host_maintenances(const zbx_vector_host_maintenance_diff_ptr_t *updates)
{
	char					*sql = NULL;
	size_t					sql_alloc = 0, sql_offset = 0;

	for (int i = 0; i < updates->values_num; i++)
	{
		char					delim = ' ';
		const zbx_host_maintenance_diff_t	*diff = updates->values[i];

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "update hosts set");

		if (0 != (diff->flags & ZBX_FLAG_HOST_MAINTENANCE_UPDATE_MAINTENANCEID))
		{
			if (0 != diff->maintenanceid)
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%cmaintenanceid=" ZBX_FS_UI64, delim,
						diff->maintenanceid);
			}
			else
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%cmaintenanceid=null", delim);
			}

			delim = ',';
		}

		if (0 != (diff->flags & ZBX_FLAG_HOST_MAINTENANCE_UPDATE_MAINTENANCE_TYPE))
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%cmaintenance_type=%u", delim,
					diff->maintenance_type);
			delim = ',';
		}

		if (0 != (diff->flags & ZBX_FLAG_HOST_MAINTENANCE_UPDATE_MAINTENANCE_STATUS))
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%cmaintenance_status=%u", delim,
					diff->maintenance_status);
			delim = ',';
		}

		if (0 != (diff->flags & ZBX_FLAG_HOST_MAINTENANCE_UPDATE_MAINTENANCE_FROM))
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%cmaintenance_from=%d", delim,
					diff->maintenance_from);
		}

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " where hostid=" ZBX_FS_UI64 ";\n", diff->hostid);

		if (SUCCEED != zbx_db_execute_overflowed_sql(&sql, &sql_alloc, &sql_offset))
			break;

		if (SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_DEBUG))
			log_host_maintenance_update(diff);
	}

	(void)zbx_db_flush_overflowed_sql(sql, sql_offset);

	zbx_free(sql);
}

static void     service_send_suppression_data(const zbx_vector_uint64_pair_t *event_maintenance, int suppressed)
{
	unsigned char   *data, *ptr;
	int             i;
	zbx_uint32_t	data_len;

	data_len = (zbx_uint32_t)((size_t)event_maintenance->values_num * sizeof(zbx_uint64_pair_t) + sizeof(int));
	ptr = data = zbx_malloc(NULL, data_len);

	ptr += zbx_serialize_value(ptr, event_maintenance->values_num);
	for (i = 0; i < event_maintenance->values_num; i++)
	{
		ptr += zbx_serialize_value(ptr, event_maintenance->values[i].first);
		ptr += zbx_serialize_value(ptr, event_maintenance->values[i].second);
	}

	if (suppressed == 0)
		zbx_service_flush(ZBX_IPC_SERVICE_SERVICE_EVENTS_UNSUPPRESS, data, data_len);
	else
		zbx_service_flush(ZBX_IPC_SERVICE_SERVICE_EVENTS_SUPPRESS, data, data_len);

	zbx_free(data);
}

/******************************************************************************
 *                                                                            *
 * Purpose: removes expired event_suppress records                            *
 *                                                                            *
 ******************************************************************************/
static void	db_remove_expired_event_suppress_data(time_t now)
{
	zbx_vector_uint64_pair_t	event_maintenance;
	zbx_db_row_t		row;
	zbx_db_result_t		result;

	zbx_vector_uint64_pair_create(&event_maintenance);

	result = zbx_db_select("select eventid,maintenanceid from event_suppress where suppress_until<" ZBX_FS_TIME_T
			" and suppress_until<>0", (zbx_fs_time_t)now);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_uint64_pair_t	pair;

		ZBX_STR2UINT64(pair.first, row[0]);
		ZBX_DBROW2UINT64(pair.second, row[1]);

		zbx_vector_uint64_pair_append(&event_maintenance, pair);
	}
	zbx_db_free_result(result);

	zbx_db_begin();
	zbx_db_execute("delete from event_suppress where suppress_until<" ZBX_FS_TIME_T " and suppress_until<>0",
			(zbx_fs_time_t)now);
	zbx_db_commit();

	if (0 != event_maintenance.values_num && 0 != zbx_dc_get_itservices_num())
		service_send_suppression_data(&event_maintenance, 0);

	zbx_vector_uint64_pair_destroy(&event_maintenance);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees event suppress data structure                               *
 *                                                                            *
 ******************************************************************************/
static void	event_suppress_data_free(zbx_event_suppress_data_t *data)
{
	zbx_vector_uint64_pair_destroy(&data->maintenances);
	zbx_free(data);
}

ZBX_PTR_VECTOR_IMPL(event_suppress_query_ptr, zbx_event_suppress_query_t*)

static int	event_suppress_query_eventid_compare(const void *d1, const void *d2)
{
	const zbx_event_suppress_query_t	*ds1 = *(const zbx_event_suppress_query_t * const *)d1;
	const zbx_event_suppress_query_t	*ds2 = *(const zbx_event_suppress_query_t * const *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(ds1->eventid, ds2->eventid);

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: fetches events that need to be queried for maintenance            *
 *                                                                            *
 ******************************************************************************/
static void	event_queries_fetch(zbx_db_result_t result, zbx_vector_event_suppress_query_ptr_t *event_queries)
{
	zbx_db_row_t			row;
	zbx_uint64_t			eventid;
	zbx_event_suppress_query_t	*query = NULL;

	while (NULL != (row = zbx_db_fetch(result)))
	{
		ZBX_STR2UINT64(eventid, row[0]);

		if (NULL == query || eventid != query->eventid)
		{
			query = (zbx_event_suppress_query_t *)zbx_malloc(NULL, sizeof(zbx_event_suppress_query_t));

			query->eventid = eventid;
			ZBX_STR2UINT64(query->triggerid, row[1]);
			ZBX_DBROW2UINT64(query->r_eventid, row[2]);
			zbx_vector_uint64_create(&query->hostids);
			zbx_vector_uint64_create(&query->functionids);
			zbx_vector_tags_ptr_create(&query->tags);
			zbx_vector_uint64_pair_create(&query->maintenances);
			zbx_vector_event_suppress_query_ptr_append(event_queries, query);
		}

		if (FAIL == zbx_db_is_null(row[3]))
		{
			zbx_tag_t	*tag = (zbx_tag_t *)zbx_malloc(NULL, sizeof(zbx_tag_t));

			tag->tag = zbx_strdup(NULL, row[3]);
			tag->value = zbx_strdup(NULL, row[4]);
			zbx_vector_tags_ptr_append(&query->tags, tag);
		}
	}
}

ZBX_PTR_VECTOR_DECL(event_suppress_data_ptr, zbx_event_suppress_data_t*)
ZBX_PTR_VECTOR_IMPL(event_suppress_data_ptr, zbx_event_suppress_data_t*)

static int	event_suppress_data_eventid_compare(const void *d1, const void *d2)
{
	const zbx_event_suppress_data_t    *ds1 = *(const zbx_event_suppress_data_t * const *)d1;
	const zbx_event_suppress_data_t    *ds2 = *(const zbx_event_suppress_data_t * const *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(ds1->eventid, ds2->eventid);

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: Gets open, recently resolved and resolved problems with suppress  *
 *          data from database and prepares event query, event data           *
 *          structures.                                                       *
 *                                                                            *
 ******************************************************************************/
static void	db_get_query_events(zbx_vector_event_suppress_query_ptr_t *event_queries,
		zbx_vector_event_suppress_data_ptr_t *event_data, int process_num, zbx_get_config_forks_f get_forks_cb)
{
	zbx_db_row_t			row;
	zbx_db_result_t			result;
	zbx_event_suppress_data_t	*data = NULL;
	zbx_uint64_t			eventid;
	zbx_uint64_pair_t		pair;
	zbx_vector_uint64_t		eventids;
	int				read_tags;
	const char			*tag_fields, *tag_join;

	if (SUCCEED == (read_tags = zbx_dc_maintenance_has_tags()))
	{
		tag_fields = "t.tag,t.value";
		tag_join = " left join problem_tag t on p.eventid=t.eventid";
	}
	else
	{
		tag_fields = "null,null";
		tag_join = "";
	}

	/* get open or recently closed problems */
	result = zbx_db_select("select p.eventid,p.objectid,p.r_eventid,%s"
			" from problem p"
			"%s"
			" where p.source=%d"
				" and p.object=%d"
				" and " ZBX_SQL_MOD(p.eventid, %d) "=%d"
			" order by p.eventid",
			tag_fields, tag_join,
			EVENT_SOURCE_TRIGGERS, EVENT_OBJECT_TRIGGER, get_forks_cb(ZBX_PROCESS_TYPE_TIMER),
			process_num - 1);

	event_queries_fetch(result, event_queries);
	zbx_db_free_result(result);

	/* get event suppress data */

	zbx_vector_uint64_create(&eventids);

	result = zbx_db_select("select eventid,maintenanceid,suppress_until"
			" from event_suppress"
			" where " ZBX_SQL_MOD(eventid, %d) "=%d and maintenanceid is not null"
			" order by eventid",
			get_forks_cb(ZBX_PROCESS_TYPE_TIMER), process_num - 1);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		ZBX_STR2UINT64(eventid, row[0]);

		zbx_event_suppress_query_t	event_query_search = {.eventid = eventid};

		if (FAIL == zbx_vector_event_suppress_query_ptr_bsearch(event_queries, &event_query_search,
				event_suppress_query_eventid_compare))
		{
			zbx_vector_uint64_append(&eventids, eventid);
		}

		if (NULL == data || data->eventid != eventid)
		{
			data = (zbx_event_suppress_data_t *)zbx_malloc(NULL, sizeof(zbx_event_suppress_data_t));
			data->eventid = eventid;
			zbx_vector_uint64_pair_create(&data->maintenances);
			zbx_vector_event_suppress_data_ptr_append(event_data, data);
		}

		ZBX_DBROW2UINT64(pair.first, row[1]);
		pair.second = (zbx_uint64_t)atoi(row[2]);
		zbx_vector_uint64_pair_append(&data->maintenances, pair);
	}
	zbx_db_free_result(result);

	/* get missing event data */

	if (0 != eventids.values_num)
	{
		if (SUCCEED == read_tags)
		{
			tag_fields = "t.tag,t.value";
			tag_join = " left join event_tag t on e.eventid=t.eventid";
		}

		zbx_vector_uint64_uniq(&eventids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

#define ZBX_EVENT_BATCH_SIZE	1000
		for (int i = 0; i < eventids.values_num; i += ZBX_EVENT_BATCH_SIZE)
		{
			char	*sql = NULL;
			size_t	sql_alloc = 0, sql_offset = 0;

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
					"select e.eventid,e.objectid,er.r_eventid,%s"
					" from events e"
					" left join event_recovery er"
						" on e.eventid=er.eventid"
					"%s"
					" where",
					tag_fields, tag_join);
			zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "e.eventid",
					eventids.values + i, MIN(eventids.values_num - i, ZBX_EVENT_BATCH_SIZE));
			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " order by e.eventid");

			result = zbx_db_select("%s", sql);
			zbx_free(sql);

			event_queries_fetch(result, event_queries);
			zbx_db_free_result(result);
		}
#undef ZBX_EVENT_BATCH_SIZE
		zbx_vector_event_suppress_query_ptr_sort(event_queries, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);
	}

	zbx_vector_uint64_destroy(&eventids);
}

/******************************************************************************
 *                                                                            *
 * Purpose: Creates/Updates event suppress data to reflect latest maintenance *
 *          changes in cache.                                                 *
 *                                                                            *
 * Parameters: suppressed_num - [OUT]                                         *
 *             process_num    - [IN]                                          *
 *             get_forks_cb   - [IN]                                          *
 *                                                                            *
 ******************************************************************************/
static int	db_update_event_suppress_data(int *suppressed_num, int process_num, zbx_get_config_forks_f get_forks_cb)
{
	zbx_vector_event_suppress_query_ptr_t	event_queries;
	zbx_vector_event_suppress_data_ptr_t	event_data;
	int					txn_rc = ZBX_DB_OK;

	*suppressed_num = 0;

	zbx_vector_event_suppress_query_ptr_create(&event_queries);
	zbx_vector_event_suppress_data_ptr_create(&event_data);

	db_get_query_events(&event_queries, &event_data, process_num, get_forks_cb);

	if (0 != event_queries.values_num)
	{
		zbx_db_insert_t			db_insert;
		char				*sql = NULL;
		size_t				sql_alloc = 0, sql_offset = 0;
		int				j, k;
		zbx_event_suppress_query_t	*query;
		zbx_event_suppress_data_t	*data;
		zbx_vector_uint64_pair_t	del_event_maintenances, suppressed;
		zbx_vector_uint64_t		maintenanceids;
		zbx_uint64_pair_t		pair;

		zbx_vector_uint64_create(&maintenanceids);
		zbx_vector_uint64_pair_create(&del_event_maintenances);
		zbx_vector_uint64_pair_create(&suppressed);

		zbx_dc_get_running_maintenanceids(&maintenanceids);

		zbx_db_begin();

		if (0 != maintenanceids.values_num && SUCCEED == zbx_db_lock_maintenanceids(&maintenanceids))
			zbx_dc_get_event_maintenances(&event_queries, &maintenanceids);

		zbx_db_insert_prepare(&db_insert, "event_suppress", "event_suppressid", "eventid", "maintenanceid",
				"suppress_until", (char *)NULL);

		for (int i = 0; i < event_queries.values_num; i++)
		{
			query = event_queries.values[i];
			zbx_vector_uint64_pair_sort(&query->maintenances, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

			k = 0;

			zbx_event_suppress_data_t event_suppress_data_search = {.eventid = query->eventid};

			if (FAIL != (j = zbx_vector_event_suppress_data_ptr_bsearch(&event_data,
					&event_suppress_data_search, event_suppress_data_eventid_compare)))
			{
				data = event_data.values[j];
				zbx_vector_uint64_pair_sort(&data->maintenances, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

				j = 0;

				while (j < data->maintenances.values_num && k < query->maintenances.values_num)
				{
					if (data->maintenances.values[j].first < query->maintenances.values[k].first)
					{
						pair.first = query->eventid;
						pair.second = data->maintenances.values[j].first;
						zbx_vector_uint64_pair_append(&del_event_maintenances, pair);

						j++;
						continue;
					}

					if (data->maintenances.values[j].first > query->maintenances.values[k].first)
					{
						if (0 == query->r_eventid)
						{
							zbx_db_insert_add_values(&db_insert, __UINT64_C(0),
									query->eventid,
									query->maintenances.values[k].first,
									(int)query->maintenances.values[k].second);

							(*suppressed_num)++;

							pair.first = query->eventid;
							pair.second = query->maintenances.values[k].first;
							zbx_vector_uint64_pair_append(&suppressed, pair);
						}

						k++;
						continue;
					}

					if (data->maintenances.values[j].second != query->maintenances.values[k].second)
					{
						zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
								"update event_suppress"
								" set suppress_until=%d"
								" where eventid=" ZBX_FS_UI64
									" and maintenanceid=" ZBX_FS_UI64 ";\n",
									(int)query->maintenances.values[k].second,
									query->eventid,
									query->maintenances.values[k].first);

						if (FAIL == zbx_db_execute_overflowed_sql(&sql, &sql_alloc,
								&sql_offset))
						{
							goto cleanup;
						}
					}
					j++;
					k++;
				}

				for (;j < data->maintenances.values_num; j++)
				{
					pair.first = query->eventid;
					pair.second = data->maintenances.values[j].first;
					zbx_vector_uint64_pair_append(&del_event_maintenances, pair);
				}
			}

			if (0 == query->r_eventid)
			{
				for (;k < query->maintenances.values_num; k++)
				{
					zbx_db_insert_add_values(&db_insert, __UINT64_C(0), query->eventid,
							query->maintenances.values[k].first,
							(int)query->maintenances.values[k].second);

					(*suppressed_num)++;

					pair.first = query->eventid;
					pair.second = query->maintenances.values[k].first;
					zbx_vector_uint64_pair_append(&suppressed, pair);
				}
			}
		}

		for (int i = 0; i < del_event_maintenances.values_num; i++)
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
					"delete from event_suppress"
					" where eventid=" ZBX_FS_UI64
						" and maintenanceid=" ZBX_FS_UI64 ";\n",
						del_event_maintenances.values[i].first,
						del_event_maintenances.values[i].second);

			if (FAIL == zbx_db_execute_overflowed_sql(&sql, &sql_alloc, &sql_offset))
				goto cleanup;
		}

		if (ZBX_DB_OK > zbx_db_flush_overflowed_sql(sql, sql_offset))
			goto cleanup;

		zbx_db_insert_autoincrement(&db_insert, "event_suppressid");
		zbx_db_insert_execute(&db_insert);
cleanup:
		if (ZBX_DB_OK == (txn_rc = zbx_db_commit()))
		{
			if (0 != del_event_maintenances.values_num || 0 != suppressed.values_num)
			{
				if (0 != zbx_dc_get_itservices_num())
				{
					if (0 != del_event_maintenances.values_num)
						service_send_suppression_data(&del_event_maintenances, 0);

					if (0 != suppressed.values_num)
						service_send_suppression_data(&suppressed, 1);
				}
			}
		}

		zbx_db_insert_clean(&db_insert);
		zbx_free(sql);

		zbx_vector_uint64_pair_destroy(&del_event_maintenances);
		zbx_vector_uint64_destroy(&maintenanceids);

		zbx_vector_uint64_pair_destroy(&suppressed);
	}

	zbx_vector_event_suppress_data_ptr_clear_ext(&event_data, event_suppress_data_free);
	zbx_vector_event_suppress_data_ptr_destroy(&event_data);

	zbx_vector_event_suppress_query_ptr_clear_ext(&event_queries, zbx_event_suppress_query_free);
	zbx_vector_event_suppress_query_ptr_destroy(&event_queries);

	return txn_rc;
}

/******************************************************************************
 *                                                                            *
 * Purpose: updates host maintenance parameters in cache and database         *
 *                                                                            *
 ******************************************************************************/
static int	update_host_maintenances(void)
{
	zbx_vector_uint64_t			maintenanceids;
	zbx_vector_host_maintenance_diff_ptr_t	updates;
	int					tnx_error, hosts_num = 0;

	zbx_vector_uint64_create(&maintenanceids);
	zbx_vector_host_maintenance_diff_ptr_create(&updates);
	zbx_vector_host_maintenance_diff_ptr_reserve(&updates, 100);

	do
	{
		zbx_db_begin();

		if (SUCCEED == zbx_dc_get_running_maintenanceids(&maintenanceids))
			zbx_db_lock_maintenanceids(&maintenanceids);

		/* host maintenance update must be called even with no maintenances running */
		/* to reset host maintenance status if necessary                            */
		zbx_dc_get_host_maintenance_updates(&maintenanceids, &updates);

		if (0 != updates.values_num)
			db_update_host_maintenances(&updates);

		if (ZBX_DB_OK == (tnx_error = zbx_db_commit()) && 0 != (hosts_num = updates.values_num))
			zbx_dc_flush_host_maintenance_updates(&updates);

		zbx_vector_host_maintenance_diff_ptr_clear_ext(&updates, zbx_host_maintenance_diff_free);
		zbx_vector_uint64_clear(&maintenanceids);
	}
	while (ZBX_DB_DOWN == tnx_error);

	zbx_vector_host_maintenance_diff_ptr_destroy(&updates);
	zbx_vector_uint64_destroy(&maintenanceids);

	return hosts_num;
}

/******************************************************************************
 *                                                                            *
 * Purpose: periodically processes maintenance                                *
 *                                                                            *
 ******************************************************************************/
ZBX_THREAD_ENTRY(timer_thread, args)
{
#define ZBX_MAINTENANCE_TIMER_DELAY	SEC_PER_MIN
	time_t			schedule_time = 0, update_time = 0;
	char			*info = NULL;
	size_t			info_alloc = 0, info_offset = 0;
	const zbx_thread_info_t	*thread_info = &((zbx_thread_args_t *)args)->info;
	int			events_num, hosts_num, update, idle = 1,
				server_num = thread_info->server_num,
				process_num = thread_info->process_num;
	unsigned char		process_type = thread_info->process_type;

	zbx_thread_timer_args	*args_in = (zbx_thread_timer_args *)(((zbx_thread_args_t *)args)->args);

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]",
			get_program_type_string(thread_info->program_type), server_num,
			get_process_type_string(process_type), process_num);

	zbx_update_selfmon_counter(thread_info, ZBX_PROCESS_STATE_BUSY);

	zbx_setproctitle("%s #%d [connecting to the database]", get_process_type_string(process_type), process_num);
	zbx_strcpy_alloc(&info, &info_alloc, &info_offset, "started");

	zbx_db_connect(ZBX_DB_CONNECT_NORMAL);

	while (ZBX_IS_RUNNING())
	{
		double	sec = zbx_time();
		zbx_update_env(get_process_type_string(process_type), sec);

		if (1 == process_num)
		{
			zbx_maintenance_timer_t	maintenance_timer;

			if (ZBX_MAINTENANCE_TIMER_DELAY <= sec - (double)schedule_time)
				maintenance_timer = MAINTENANCE_TIMER_PENDING;
			else
				maintenance_timer = MAINTENANCE_TIMER_INITIALIZED;

			/* start update process only when all timers have finished their updates */
			if ((SUCCEED == zbx_dc_maintenance_check_immediate_update() ||
					MAINTENANCE_TIMER_PENDING == maintenance_timer) &&
					FAIL == zbx_dc_maintenance_check_update_flags())
			{
				zbx_setproctitle("%s #%d [%s, processing maintenances]",
						get_process_type_string(process_type), process_num, info);

				update = zbx_dc_update_maintenances(maintenance_timer);

				/* force maintenance updates at server startup */
				if (0 == schedule_time)
					update = SUCCEED;

				/* update hosts if there are modified (stopped, started, changed) maintenances */
				if (SUCCEED == update)
					hosts_num = update_host_maintenances();
				else
					hosts_num = 0;

				if (MAINTENANCE_TIMER_PENDING == maintenance_timer)
					db_remove_expired_event_suppress_data((time_t)sec);

				if (SUCCEED == update)
				{
					zbx_dc_maintenance_set_update_flags();
					while (ZBX_DB_DOWN == db_update_event_suppress_data(&events_num, process_num,
							args_in->get_process_forks_cb_arg))
						;

					zbx_dc_maintenance_reset_update_flag(process_num);
				}
				else
					events_num = 0;

				info_offset = 0;
				zbx_snprintf_alloc(&info, &info_alloc, &info_offset,
						"updated %d hosts, suppressed %d events in " ZBX_FS_DBL " sec",
						hosts_num, events_num, zbx_time() - sec);

				if (MAINTENANCE_TIMER_PENDING == maintenance_timer)
					update_time = (time_t)sec;
			}
		}
		else if (SUCCEED == zbx_dc_maintenance_check_update_flag(process_num))
		{
			zbx_setproctitle("%s #%d [%s, processing maintenances]", get_process_type_string(process_type),
					process_num, info);

			while (ZBX_DB_DOWN == db_update_event_suppress_data(&events_num, process_num,
					args_in->get_process_forks_cb_arg))
				;

			info_offset = 0;
			zbx_snprintf_alloc(&info, &info_alloc, &info_offset, "suppressed %d events in " ZBX_FS_DBL
					" sec", events_num, zbx_time() - sec);

			update_time = (time_t)sec;
			zbx_dc_maintenance_reset_update_flag(process_num);
		}

		if (schedule_time != update_time)
		{
			update_time -= update_time % 60;
			schedule_time = update_time;

			if (0 > (idle = (int)(ZBX_MAINTENANCE_TIMER_DELAY - (zbx_time() - (double)schedule_time))))
				idle = 0;

			zbx_setproctitle("%s #%d [%s, idle %d sec]",
					get_process_type_string(process_type), process_num, info, idle);
		}

		if (0 != idle)
			zbx_sleep_loop(thread_info, 1);

		idle = 1;
	}

	zbx_setproctitle("%s #%d [terminated]", get_process_type_string(process_type), process_num);

	while (1)
		zbx_sleep(SEC_PER_MIN);
#undef ZBX_MAINTENANCE_TIMER_DELAY
}
