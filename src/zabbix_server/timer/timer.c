/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

#include "cfg.h"
#include "pid.h"
#include "db.h"
#include "log.h"
#include "../events.h"
#include "dbcache.h"
#include "zbxserver.h"
#include "daemon.h"
#include "zbxself.h"
#include "export.h"

#include "timer.h"

#define ZBX_TIMER_DELAY		SEC_PER_MIN

extern unsigned char	process_type, program_type;
extern int		server_num, process_num;
extern int		CONFIG_TIMER_FORKS;

/******************************************************************************
 *                                                                            *
 * Function: db_update_host_maintenances                                      *
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

	do
	{
		DBbegin();
		DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

		for (i = 0; i < updates->values_num; i++)
		{
			char delim = ' ';

			diff = (const zbx_host_maintenance_diff_t *)updates->values[i];

			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "update hosts set");

			if (0 != (diff->flags & ZBX_FLAG_HOST_MAINTENANCE_UPDATE_MAINTENANCEID))
			{
				if (0 != diff->maintenanceid)
				{
					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%cmaintenanceid=" ZBX_FS_UI64,
						delim, diff->maintenanceid);
				}
				else
					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%cmaintenanceid=null", delim);

				delim = ',';
			}

			if (0 != (diff->flags & ZBX_FLAG_HOST_MAINTENANCE_UPDATE_MAINTENANCE_TYPE))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%cmaintenance_type=%u",
						delim, diff->maintenance_type);
				delim = ',';
			}

			if (0 != (diff->flags & ZBX_FLAG_HOST_MAINTENANCE_UPDATE_MAINTENANCE_STATUS))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%cmaintenance_status=%u",
						delim, diff->maintenance_status);
				delim = ',';
			}

			if (0 != (diff->flags & ZBX_FLAG_HOST_MAINTENANCE_UPDATE_MAINTENANCE_FROM))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%cmaintenance_from=%d",
						delim, diff->maintenance_from);
			}

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " where hostid=" ZBX_FS_UI64 ";\n",
					diff->hostid);

			if (SUCCEED != DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset))
				break;

		}
		DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

		if (16 < sql_offset)
			DBexecute("%s", sql);
	}
	while (ZBX_DB_DOWN == DBcommit());

	zbx_free(sql);
}

/******************************************************************************
 *                                                                            *
 * Function: db_remove_expired_event_suppress_data                            *
 *                                                                            *
 * Purpose: remove expired event_suppress records                             *
 *                                                                            *
 ******************************************************************************/
static void	db_remove_expired_event_suppress_data(int now)
{
	DBexecute("delete from event_suppress where suppress_until<%d", now);
}

/* trigger -> functions cache */
typedef struct
{
	zbx_uint64_t		triggerid;
	zbx_vector_uint64_t	functionids;
}
zbx_trigger_functions_t;

/* addition data for event maintenance calculations to pair with zbx_event_suppress_query_t */
typedef struct
{
	zbx_uint64_t			triggerid;
	zbx_vector_uint64_pair_t	maintenances;
}
zbx_event_suppress_data_t;

/******************************************************************************
 *                                                                            *
 * Function: zbx_event_suppress_query_free                                    *
 *                                                                            *
 * Purpose: free event suppress query structure                               *
 *                                                                            *
 ******************************************************************************/
void	zbx_event_suppress_query_free(zbx_event_suppress_query_t *query)
{
	zbx_vector_uint64_destroy(&query->functionids);
	zbx_vector_uint64_pair_destroy(&query->maintenances);
	zbx_vector_ptr_clear_ext(&query->tags, (zbx_clean_func_t)zbx_free_tag);
	zbx_vector_ptr_destroy(&query->tags);
	zbx_free(query);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_event_suppress_data_free                                     *
 *                                                                            *
 * Purpose: free event suppress data structure                                *
 *                                                                            *
 ******************************************************************************/
void	zbx_event_suppress_data_free(zbx_event_suppress_data_t *data)
{
	zbx_vector_uint64_pair_destroy(&data->maintenances);
	zbx_free(data);
}

/******************************************************************************
 *                                                                            *
 * Function: db_get_query_events                                              *
 *                                                                            *
 * Purpose: get open problems from database and prepare maintenance queries,  *
 *          data structures                                                   *
 *                                                                            *
 ******************************************************************************/
static void	db_get_query_events(int process_num, zbx_vector_ptr_t *event_queries, zbx_vector_ptr_t *event_data)
{
	DB_ROW				row;
	DB_RESULT			result;
	zbx_event_suppress_query_t	*query;
	zbx_event_suppress_data_t	*data;

	result = DBselect("select eventid,objectid"
			" from problem"
			" where r_eventid is null"
				" and source=%d"
				" and object=%d"
				" and " ZBX_SQL_MOD(eventid, %d) "=%d"
			" order by eventid",
			EVENT_SOURCE_TRIGGERS, EVENT_OBJECT_TRIGGER, CONFIG_TIMER_FORKS, process_num - 1);

	while (NULL != (row = DBfetch(result)))
	{
		query = (zbx_event_suppress_query_t *)zbx_malloc(NULL, sizeof(zbx_event_suppress_query_t));
		ZBX_STR2UINT64(query->eventid, row[0]);
		zbx_vector_uint64_create(&query->functionids);
		zbx_vector_ptr_create(&query->tags);
		zbx_vector_uint64_pair_create(&query->maintenances);
		zbx_vector_ptr_append(event_queries, query);

		data = (zbx_event_suppress_data_t *)zbx_malloc(NULL, sizeof(zbx_event_suppress_data_t));
		ZBX_STR2UINT64(data->triggerid, row[1]);
		zbx_vector_uint64_pair_create(&data->maintenances);
		zbx_vector_ptr_append(event_data, data);
	}
	DBfree_result(result);
}

/******************************************************************************
 *                                                                            *
 * Function: db_get_query_functions                                           *
 *                                                                            *
 * Purpose: get query event functionids from database                         *
 *                                                                            *
 ******************************************************************************/
static void	db_get_query_functions(zbx_vector_ptr_t *event_queries, const zbx_vector_ptr_t *event_data)
{
	DB_ROW				row;
	DB_RESULT			result;
	int				i;
	zbx_vector_uint64_t		triggerids;
	zbx_hashset_t			triggers;
	zbx_hashset_iter_t		iter;
	char				*sql = NULL;
	size_t				sql_alloc = 0, sql_offset = 0;
	zbx_trigger_functions_t		*trigger = NULL, trigger_local;
	zbx_uint64_t			triggerid, functionid;
	zbx_event_suppress_query_t	*query;
	zbx_event_suppress_data_t	*data;

	zbx_vector_uint64_create(&triggerids);
	zbx_hashset_create(&triggers, 100, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	for (i = 0; i < event_data->values_num; i++)
	{
		data = (zbx_event_suppress_data_t *)event_data->values[i];
		zbx_vector_uint64_append(&triggerids, data->triggerid);
	}

	zbx_vector_uint64_sort(&triggerids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_uniq(&triggerids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select functionid,triggerid from functions where");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "triggerid", triggerids.values, triggerids.values_num);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " order by triggerid");

	result = DBselect("%s", sql);
	zbx_free(sql);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(functionid, row[0]);
		ZBX_STR2UINT64(triggerid, row[1]);

		if (NULL == trigger || trigger->triggerid != triggerid)
		{
			if (NULL == (trigger = (zbx_trigger_functions_t *)zbx_hashset_search(&triggers, &triggerid)))
			{
				trigger_local.triggerid = triggerid;
				trigger = (zbx_trigger_functions_t *)zbx_hashset_insert(&triggers, &trigger_local,
						sizeof(trigger_local));
				zbx_vector_uint64_create(&trigger->functionids);
			}
		}
		zbx_vector_uint64_append(&trigger->functionids, functionid);
	}
	DBfree_result(result);

	for (i = 0; i < event_queries->values_num; i++)
	{
		query = (zbx_event_suppress_query_t *)event_queries->values[i];
		data = (zbx_event_suppress_data_t *)event_data->values[i];

		if (NULL == (trigger = (zbx_trigger_functions_t *)zbx_hashset_search(&triggers, &data->triggerid)))
			continue;

		zbx_vector_uint64_append_array(&query->functionids, trigger->functionids.values,
				trigger->functionids.values_num);
	}

	zbx_hashset_iter_reset(&triggers, &iter);
	while (NULL != (trigger = (zbx_trigger_functions_t *)zbx_hashset_iter_next(&iter)))
		zbx_vector_uint64_destroy(&trigger->functionids);
	zbx_hashset_destroy(&triggers);

	zbx_vector_uint64_destroy(&triggerids);
}

/******************************************************************************
 *                                                                            *
 * Function: db_get_query_tags                                                *
 *                                                                            *
 * Purpose: get query event tags from database                                *
 *                                                                            *
 ******************************************************************************/
static void	db_get_query_tags(zbx_vector_ptr_t *event_queries)
{
	DB_ROW				row;
	DB_RESULT			result;
	int				i;
	char				*sql = NULL;
	size_t				sql_alloc = 0, sql_offset = 0;
	zbx_event_suppress_query_t	*query;
	zbx_vector_uint64_t		eventids;
	zbx_uint64_t			eventid;
	zbx_tag_t			*tag;

	zbx_vector_uint64_create(&eventids);

	for (i = 0; i < event_queries->values_num; i++)
	{
		query = (zbx_event_suppress_query_t *)event_queries->values[i];
		zbx_vector_uint64_append(&eventids, query->eventid);
	}

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select eventid,tag,value from problem_tag where");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "eventid", eventids.values, eventids.values_num);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " order by eventid");

	result = DBselect("%s", sql);
	zbx_free(sql);

	i = 0;
	query = (zbx_event_suppress_query_t *)event_queries->values[0];

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(eventid, row[0]);

		while (query->eventid != eventid)
			query = (zbx_event_suppress_query_t *)event_queries->values[++i];

		tag = (zbx_tag_t *)zbx_malloc(NULL, sizeof(zbx_tag_t));
		tag->tag = zbx_strdup(NULL, row[1]);
		tag->value = zbx_strdup(NULL, row[2]);
		zbx_vector_ptr_append(&query->tags, tag);
	}
	DBfree_result(result);

	zbx_vector_uint64_destroy(&eventids);
}

/******************************************************************************
 *                                                                            *
 * Function: db_get_suppress_data                                             *
 *                                                                            *
 * Purpose: get query event maintenance information from database             *
 *                                                                            *
 ******************************************************************************/
static void	db_get_suppress_data(zbx_vector_ptr_t *event_queries, const zbx_vector_ptr_t *event_data)
{
	DB_ROW				row;
	DB_RESULT			result;
	int				i;
	char				*sql = NULL;
	size_t				sql_alloc = 0, sql_offset = 0;
	zbx_event_suppress_query_t	*query;
	zbx_event_suppress_data_t	*data;
	zbx_vector_uint64_t		eventids;
	zbx_uint64_t			eventid;
	zbx_uint64_pair_t		pair;

	zbx_vector_uint64_create(&eventids);

	for (i = 0; i < event_queries->values_num; i++)
	{
		query = (zbx_event_suppress_query_t *)event_queries->values[i];
		zbx_vector_uint64_append(&eventids, query->eventid);
	}

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select eventid,maintenanceid,suppress_until"
			" from event_suppress where");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "eventid", eventids.values, eventids.values_num);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " order by eventid");

	result = DBselect("%s", sql);
	zbx_free(sql);

	i = 0;
	query = (zbx_event_suppress_query_t *)event_queries->values[0];

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(eventid, row[0]);

		while (query->eventid != eventid)
			query = (zbx_event_suppress_query_t *)event_queries->values[++i];

		data = (zbx_event_suppress_data_t *)event_data->values[i];

		ZBX_DBROW2UINT64(pair.first, row[1]);
		pair.second = atoi(row[2]);
		zbx_vector_uint64_pair_append(&data->maintenances, pair);
	}
	DBfree_result(result);

	zbx_vector_uint64_destroy(&eventids);
}

/******************************************************************************
 *                                                                            *
 * Function: db_update_event_suppress_data                                    *
 *                                                                            *
 * Purpose: create/update event suppress data to reflect latest maintenance   *
 *          changes in cache                                                  *
 *                                                                            *
 * Parameters: process_num    - [IN] the timer process number                 *
 *             suppressed_num - [OUT] the number of suppressed events         *
 *             updated_num    - [OUT] the number of updated events            *
 *                                                                            *
 ******************************************************************************/
static void	db_update_event_suppress_data(int process_num, int *suppressed_num)
{
	zbx_vector_ptr_t		event_queries, event_data;
	zbx_event_suppress_query_t	*query;
	zbx_event_suppress_data_t	*data;
	int				i, j, k;
	char				*sql = NULL;
	size_t				sql_alloc = 0, sql_offset = 0;
	zbx_db_insert_t			db_insert;

	*suppressed_num = 0;

	zbx_vector_ptr_create(&event_queries);
	zbx_vector_ptr_create(&event_data);

	db_get_query_events(process_num, &event_queries, &event_data);

	if (0 != event_queries.values_num)
	{
		db_get_query_functions(&event_queries, &event_data);
		db_get_query_tags(&event_queries);
		db_get_suppress_data(&event_queries, &event_data);

		zbx_dc_get_event_maintenances(&event_queries);

		DBbegin();

		zbx_db_insert_prepare(&db_insert, "event_suppress", "eventid", "maintenanceid", "suppress_until", NULL);
		DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

		for (i = 0; i < event_queries.values_num; i++)
		{
			query = (zbx_event_suppress_query_t *)event_queries.values[i];
			zbx_vector_uint64_pair_sort(&query->maintenances, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
			data = (zbx_event_suppress_data_t *)event_data.values[i];
			zbx_vector_uint64_pair_sort(&data->maintenances, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

			j = 0;
			k = 0;

			while (j < data->maintenances.values_num && k < query->maintenances.values_num)
			{
				if (data->maintenances.values[j].first < query->maintenances.values[k].first)
				{
					j++;
					continue;
				}

				if (data->maintenances.values[j].first > query->maintenances.values[k].first)
				{
					zbx_db_insert_add_values(&db_insert, query->eventid,
							query->maintenances.values[k].first,
							(int)query->maintenances.values[k].second);
					(*suppressed_num)++;
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
							(int)query->maintenances.values[k].second, query->eventid,
							query->maintenances.values[k].first);

					if (FAIL == DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset))
						goto skip;
				}
				j++;
				k++;
			}

			for (;k < query->maintenances.values_num; k++)
			{
				zbx_db_insert_add_values(&db_insert, query->eventid,
						query->maintenances.values[k].first,
						(int)query->maintenances.values[k].second);
				(*suppressed_num)++;
			}
		}
skip:
		DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

		if (16 < sql_offset)
			DBexecute("%s", sql);
		zbx_free(sql);

		zbx_db_insert_execute(&db_insert);
		zbx_db_insert_clean(&db_insert);

		DBcommit();

		zbx_vector_ptr_clear_ext(&event_data, (zbx_clean_func_t)zbx_event_suppress_data_free);
		zbx_vector_ptr_clear_ext(&event_queries, (zbx_clean_func_t)zbx_event_suppress_query_free);
	}

	zbx_vector_ptr_destroy(&event_data);
	zbx_vector_ptr_destroy(&event_queries);
}


/******************************************************************************
 *                                                                            *
 * Function: main_timer_loop                                                  *
 *                                                                            *
 * Purpose: periodically processes host maintenance                           *
 *                                                                            *
 ******************************************************************************/
ZBX_THREAD_ENTRY(timer_thread, args)
{
	double	sec = 0.0;
	int	maintenance_time, update_time, idle, events_num, hosts_num;
	char	*info = NULL;
	size_t	info_alloc = 0, info_offset = 0;

	process_type = ((zbx_thread_args_t *)args)->process_type;
	server_num = ((zbx_thread_args_t *)args)->server_num;
	process_num = ((zbx_thread_args_t *)args)->process_num;

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(program_type),
			server_num, get_process_type_string(process_type), process_num);

	zbx_setproctitle("%s #%d [connecting to the database]", get_process_type_string(process_type), process_num);
	zbx_strcpy_alloc(&info, &info_alloc, &info_offset, "started");

	DBconnect(ZBX_DB_CONNECT_NORMAL);

	if (SUCCEED == zbx_is_export_enabled())
		zbx_problems_export_init("timer", process_num);

	maintenance_time = time(NULL) - ZBX_TIMER_DELAY;

	for (;;)
	{
		sec = zbx_time();

		if (1 == process_num)
		{
			if (sec - maintenance_time >= ZBX_TIMER_DELAY)
			{
				zbx_vector_ptr_t	updates;

				zbx_setproctitle("%s #%d [%s, processing maintenances]",
						get_process_type_string(process_type), process_num, info);

				zbx_dc_update_maintenances();

				zbx_vector_ptr_create(&updates);
				zbx_vector_ptr_reserve(&updates, 100);

				zbx_dc_update_host_maintenances(&updates);
				hosts_num = updates.values_num;

				if (0 != updates.values_num)
				{
					db_update_host_maintenances(&updates);
					zbx_vector_ptr_clear_ext(&updates, (zbx_clean_func_t)zbx_ptr_free);
				}
				zbx_vector_ptr_destroy(&updates);

				db_remove_expired_event_suppress_data((int)sec);
				zbx_dc_set_maintenance_update_time((int)sec);
				db_update_event_suppress_data(process_num, &events_num);

				update_time = (int)sec;

				info_offset = 0;
				zbx_snprintf_alloc(&info, &info_alloc, &info_offset,
						"updated %d hosts, suppressed %d events in " ZBX_FS_DBL " sec",
						hosts_num, events_num, zbx_time() - sec);
			}
		}
		else
		{
			if (maintenance_time < (update_time = zbx_dc_get_maintenance_update_time()))
			{
				zbx_setproctitle("%s #%d [%s, processing maintenances]",
						get_process_type_string(process_type), process_num, info);

				db_update_event_suppress_data(process_num, &events_num);

				info_offset = 0;
				zbx_snprintf_alloc(&info, &info_alloc, &info_offset,
						"suppressed %d events in " ZBX_FS_DBL " sec",
						events_num, zbx_time() - sec);
			}
		}

		if (maintenance_time != update_time)
		{
			maintenance_time = update_time;

			if (0 > (idle = ZBX_TIMER_DELAY - (zbx_time() - sec)))
				idle = 0;

			zbx_setproctitle("%s #%d [%s, idle %d sec]",
					get_process_type_string(process_type), process_num, info, idle);
		}

		if (0 != idle)
			zbx_sleep_loop(1);

		idle = 1;

		zbx_handle_log();

#if !defined(_WINDOWS) && defined(HAVE_RESOLV_H)
		zbx_update_resolver_conf();	/* handle /etc/resolv.conf update */
#endif
	}
}
