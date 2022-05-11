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

#include "timer.h"

#include "log.h"
#include "dbcache.h"
#include "daemon.h"
#include "zbxself.h"

#define ZBX_TIMER_DELAY		SEC_PER_MIN

#define ZBX_EVENT_BATCH_SIZE	1000

extern ZBX_THREAD_LOCAL unsigned char	process_type;
extern unsigned char			program_type;
extern ZBX_THREAD_LOCAL int		server_num, process_num;
extern int				CONFIG_TIMER_FORKS;

/* addition data for event maintenance calculations to pair with zbx_event_suppress_query_t */
typedef struct
{
	zbx_uint64_t			eventid;
	zbx_vector_uint64_pair_t	maintenances;
}
zbx_event_suppress_data_t;

/******************************************************************************
 *                                                                            *
 * Purpose: log host maintenance changes                                      *
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
 * Purpose: update host maintenance properties in database                    *
 *                                                                            *
 ******************************************************************************/
static void	db_update_host_maintenances(const zbx_vector_ptr_t *updates)
{
	int					i;
	const zbx_host_maintenance_diff_t	*diff;
	char					*sql = NULL;
	size_t					sql_alloc = 0, sql_offset = 0;

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	for (i = 0; i < updates->values_num; i++)
	{
		char	delim = ' ';

		diff = (const zbx_host_maintenance_diff_t *)updates->values[i];

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

		if (SUCCEED != DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset))
			break;

		if (SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_DEBUG))
			log_host_maintenance_update(diff);
	}

	DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (16 < sql_offset)
		DBexecute("%s", sql);

	zbx_free(sql);
}

/******************************************************************************
 *                                                                            *
 * Purpose: remove expired event_suppress records                             *
 *                                                                            *
 ******************************************************************************/
static void	db_remove_expired_event_suppress_data(int now)
{
	DBbegin();
	DBexecute("delete from event_suppress where suppress_until<%d", now);
	DBcommit();
}

/******************************************************************************
 *                                                                            *
 * Purpose: free event suppress data structure                                *
 *                                                                            *
 ******************************************************************************/
static void	event_suppress_data_free(zbx_event_suppress_data_t *data)
{
	zbx_vector_uint64_pair_destroy(&data->maintenances);
	zbx_free(data);
}

/******************************************************************************
 *                                                                            *
 * Purpose: fetch events that need to be queried for maintenance              *
 *                                                                            *
 ******************************************************************************/
static void	event_queries_fetch(DB_RESULT result, zbx_vector_ptr_t *event_queries)
{
	DB_ROW				row;
	zbx_uint64_t			eventid;
	zbx_event_suppress_query_t	*query = NULL;

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(eventid, row[0]);

		if (NULL == query || eventid != query->eventid)
		{
			query = (zbx_event_suppress_query_t *)zbx_malloc(NULL, sizeof(zbx_event_suppress_query_t));

			query->eventid = eventid;
			ZBX_STR2UINT64(query->triggerid, row[1]);
			ZBX_DBROW2UINT64(query->r_eventid, row[2]);
			zbx_vector_uint64_create(&query->functionids);
			zbx_vector_ptr_create(&query->tags);
			zbx_vector_uint64_pair_create(&query->maintenances);
			zbx_vector_ptr_append(event_queries, query);
		}

		if (FAIL == DBis_null(row[3]))
		{
			zbx_tag_t	*tag;

			tag = (zbx_tag_t *)zbx_malloc(NULL, sizeof(zbx_tag_t));
			tag->tag = zbx_strdup(NULL, row[3]);
			tag->value = zbx_strdup(NULL, row[4]);
			zbx_vector_ptr_append(&query->tags, tag);

		}
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: get open, recently resolved and resolved problems with suppress   *
 *          data from database and prepare event query, event data structures *
 *                                                                            *
 ******************************************************************************/
static void	db_get_query_events(zbx_vector_ptr_t *event_queries, zbx_vector_ptr_t *event_data)
{
	DB_ROW				row;
	DB_RESULT			result;
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
	result = DBselect("select p.eventid,p.objectid,p.r_eventid,%s"
			" from problem p"
			"%s"
			" where p.source=%d"
				" and p.object=%d"
				" and " ZBX_SQL_MOD(p.eventid, %d) "=%d"
			" order by p.eventid",
			tag_fields, tag_join,
			EVENT_SOURCE_TRIGGERS, EVENT_OBJECT_TRIGGER, CONFIG_TIMER_FORKS, process_num - 1);

	event_queries_fetch(result, event_queries);
	DBfree_result(result);

	/* get event suppress data */

	zbx_vector_uint64_create(&eventids);

	result = DBselect("select eventid,maintenanceid,suppress_until"
			" from event_suppress"
			" where " ZBX_SQL_MOD(eventid, %d) "=%d"
			" order by eventid",
			CONFIG_TIMER_FORKS, process_num - 1);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(eventid, row[0]);

		if (FAIL == zbx_vector_ptr_bsearch(event_queries, &eventid, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC))
			zbx_vector_uint64_append(&eventids, eventid);

		if (NULL == data || data->eventid != eventid)
		{
			data = (zbx_event_suppress_data_t *)zbx_malloc(NULL, sizeof(zbx_event_suppress_data_t));
			data->eventid = eventid;
			zbx_vector_uint64_pair_create(&data->maintenances);
			zbx_vector_ptr_append(event_data, data);
		}

		ZBX_DBROW2UINT64(pair.first, row[1]);
		pair.second = atoi(row[2]);
		zbx_vector_uint64_pair_append(&data->maintenances, pair);
	}
	DBfree_result(result);

	/* get missing event data */

	if (0 != eventids.values_num)
	{
		int	i;

		if (SUCCEED == read_tags)
		{
			tag_fields = "t.tag,t.value";
			tag_join = " left join event_tag t on e.eventid=t.eventid";
		}

		zbx_vector_uint64_uniq(&eventids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		for (i = 0; i < eventids.values_num; i += ZBX_EVENT_BATCH_SIZE)
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
			DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "e.eventid",
					eventids.values + i, MIN(eventids.values_num - i, ZBX_EVENT_BATCH_SIZE));
			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " order by e.eventid");

			result = DBselect("%s", sql);
			zbx_free(sql);

			event_queries_fetch(result, event_queries);
			DBfree_result(result);
		}

		zbx_vector_ptr_sort(event_queries, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);
	}

	zbx_vector_uint64_destroy(&eventids);
}

/******************************************************************************
 *                                                                            *
 * Purpose: create/update event suppress data to reflect latest maintenance   *
 *          changes in cache                                                  *
 *                                                                            *
 * Parameters: suppressed_num - [OUT] the number of suppressed events         *
 *                                                                            *
 ******************************************************************************/
static void	db_update_event_suppress_data(int *suppressed_num)
{
	zbx_vector_ptr_t	event_queries, event_data;

	*suppressed_num = 0;

	zbx_vector_ptr_create(&event_queries);
	zbx_vector_ptr_create(&event_data);

	db_get_query_events(&event_queries, &event_data);

	if (0 != event_queries.values_num)
	{
		zbx_db_insert_t			db_insert;
		char				*sql = NULL;
		size_t				sql_alloc = 0, sql_offset = 0;
		int				i, j, k;
		zbx_event_suppress_query_t	*query;
		zbx_event_suppress_data_t	*data;
		zbx_vector_uint64_pair_t	del_event_maintenances;
		zbx_vector_uint64_t		maintenanceids;
		zbx_uint64_pair_t		pair;

		zbx_vector_uint64_create(&maintenanceids);
		zbx_vector_uint64_pair_create(&del_event_maintenances);

		zbx_dc_get_running_maintenanceids(&maintenanceids);

		DBbegin();

		if (0 != maintenanceids.values_num && SUCCEED == zbx_db_lock_maintenanceids(&maintenanceids))
			zbx_dc_get_event_maintenances(&event_queries, &maintenanceids);

		zbx_db_insert_prepare(&db_insert, "event_suppress", "event_suppressid", "eventid", "maintenanceid",
				"suppress_until", NULL);
		DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

		for (i = 0; i < event_queries.values_num; i++)
		{
			query = (zbx_event_suppress_query_t *)event_queries.values[i];
			zbx_vector_uint64_pair_sort(&query->maintenances, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

			k = 0;

			if (FAIL != (j = zbx_vector_ptr_bsearch(&event_data, &query->eventid,
					ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
			{
				data = (zbx_event_suppress_data_t *)event_data.values[j];
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

						if (FAIL == DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset))
							goto cleanup;
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
				}
			}
		}

		for (i = 0; i < del_event_maintenances.values_num; i++)
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
					"delete from event_suppress"
					" where eventid=" ZBX_FS_UI64
						" and maintenanceid=" ZBX_FS_UI64 ";\n",
						del_event_maintenances.values[i].first,
						del_event_maintenances.values[i].second);

			if (FAIL == DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset))
				goto cleanup;
		}

		DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

		if (16 < sql_offset)
		{
			if (ZBX_DB_OK > DBexecute("%s", sql))
				goto cleanup;
		}

		zbx_db_insert_autoincrement(&db_insert, "event_suppressid");
		zbx_db_insert_execute(&db_insert);
cleanup:
		DBcommit();

		zbx_db_insert_clean(&db_insert);
		zbx_free(sql);

		zbx_vector_uint64_pair_destroy(&del_event_maintenances);
		zbx_vector_uint64_destroy(&maintenanceids);
	}

	zbx_vector_ptr_clear_ext(&event_data, (zbx_clean_func_t)event_suppress_data_free);
	zbx_vector_ptr_destroy(&event_data);

	zbx_vector_ptr_clear_ext(&event_queries, (zbx_clean_func_t)zbx_event_suppress_query_free);
	zbx_vector_ptr_destroy(&event_queries);
}

/******************************************************************************
 *                                                                            *
 * Purpose: update host maintenance parameters in cache and database          *
 *                                                                            *
 ******************************************************************************/
static int	update_host_maintenances(void)
{
	zbx_vector_uint64_t	maintenanceids;
	zbx_vector_ptr_t	updates;
	int			hosts_num = 0;
	int			tnx_error;

	zbx_vector_uint64_create(&maintenanceids);
	zbx_vector_ptr_create(&updates);
	zbx_vector_ptr_reserve(&updates, 100);

	do
	{
		DBbegin();

		if (SUCCEED == zbx_dc_get_running_maintenanceids(&maintenanceids))
			zbx_db_lock_maintenanceids(&maintenanceids);

		/* host maintenance update must be called even with no maintenances running */
		/* to reset host maintenance status if necessary                            */
		zbx_dc_get_host_maintenance_updates(&maintenanceids, &updates);

		if (0 != updates.values_num)
			db_update_host_maintenances(&updates);

		if (ZBX_DB_OK == (tnx_error = DBcommit()) && 0 != (hosts_num = updates.values_num))
			zbx_dc_flush_host_maintenance_updates(&updates);

		zbx_vector_ptr_clear_ext(&updates, (zbx_clean_func_t)zbx_ptr_free);
		zbx_vector_uint64_clear(&maintenanceids);
	}
	while (ZBX_DB_DOWN == tnx_error);

	zbx_vector_ptr_destroy(&updates);
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
	double		sec;
	int		maintenance_time = 0, update_time = 0, idle = 1, events_num, hosts_num, update;
	char		*info = NULL;
	size_t		info_alloc = 0, info_offset = 0;

	process_type = ((zbx_thread_args_t *)args)->process_type;
	server_num = ((zbx_thread_args_t *)args)->server_num;
	process_num = ((zbx_thread_args_t *)args)->process_num;

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(program_type),
			server_num, get_process_type_string(process_type), process_num);

	update_selfmon_counter(ZBX_PROCESS_STATE_BUSY);

	zbx_setproctitle("%s #%d [connecting to the database]", get_process_type_string(process_type), process_num);
	zbx_strcpy_alloc(&info, &info_alloc, &info_offset, "started");

	DBconnect(ZBX_DB_CONNECT_NORMAL);

	while (ZBX_IS_RUNNING())
	{
		sec = zbx_time();
		zbx_update_env(sec);

		if (1 == process_num)
		{
			/* start update process only when all timers have finished their updates */
			if (sec - maintenance_time >= ZBX_TIMER_DELAY && FAIL == zbx_dc_maintenance_check_update_flags())
			{
				zbx_setproctitle("%s #%d [%s, processing maintenances]",
						get_process_type_string(process_type), process_num, info);

				update = zbx_dc_update_maintenances();

				/* force maintenance updates at server startup */
				if (0 == maintenance_time)
					update = SUCCEED;

				/* update hosts if there are modified (stopped, started, changed) maintenances */
				if (SUCCEED == update)
					hosts_num = update_host_maintenances();
				else
					hosts_num = 0;

				db_remove_expired_event_suppress_data((int)sec);

				if (SUCCEED == update)
				{
					zbx_dc_maintenance_set_update_flags();
					db_update_event_suppress_data(&events_num);
					zbx_dc_maintenance_reset_update_flag(process_num);
				}
				else
					events_num = 0;

				info_offset = 0;
				zbx_snprintf_alloc(&info, &info_alloc, &info_offset,
						"updated %d hosts, suppressed %d events in " ZBX_FS_DBL " sec",
						hosts_num, events_num, zbx_time() - sec);

				update_time = (int)sec;
			}
		}
		else if (SUCCEED == zbx_dc_maintenance_check_update_flag(process_num))
		{
			zbx_setproctitle("%s #%d [%s, processing maintenances]", get_process_type_string(process_type),
					process_num, info);

			db_update_event_suppress_data(&events_num);

			info_offset = 0;
			zbx_snprintf_alloc(&info, &info_alloc, &info_offset, "suppressed %d events in " ZBX_FS_DBL
					" sec", events_num, zbx_time() - sec);

			update_time = (int)sec;
			zbx_dc_maintenance_reset_update_flag(process_num);
		}

		if (maintenance_time != update_time)
		{
			update_time -= update_time % 60;
			maintenance_time = update_time;

			if (0 > (idle = ZBX_TIMER_DELAY - (zbx_time() - maintenance_time)))
				idle = 0;

			zbx_setproctitle("%s #%d [%s, idle %d sec]",
					get_process_type_string(process_type), process_num, info, idle);
		}

		if (0 != idle)
			zbx_sleep_loop(1);

		idle = 1;
	}

	zbx_setproctitle("%s #%d [terminated]", get_process_type_string(process_type), process_num);

	while (1)
		zbx_sleep(SEC_PER_MIN);
}
