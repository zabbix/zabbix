/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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
#include "dbcache.h"
#include "log.h"
#include "daemon.h"
#include "zbxself.h"
#include "zbxalgo.h"

#include "housekeeper.h"

extern unsigned char	process_type, daemon_type;
extern int		server_num, process_num;

static int	hk_period;

#define HK_INITIAL_DELETE_QUEUE_SIZE	4096

/* the maximum number of housekeeping periods to be removed per single housekeeping cycle */
#define HK_MAX_DELETE_PERIODS		4

/* global configuration data containing housekeeping configuration */
static zbx_config_t	cfg;

/* Housekeeping rule definition.                                */
/* A housekeeping rule describes table from which records older */
/* than history setting must be removed according to optional   */
/* filter.                                                      */
typedef struct
{
	/* target table name */
	char	*table;

	/* Optional filter, must be empty string if not used. Only the records matching */
	/* filter are subject to housekeeping procedures.                               */
	char	*filter;

	/* The oldest record in table (with filter in effect). The min_clock value is   */
	/* read from the database when accessed for the first time and then during      */
	/* housekeeping procedures updated to the last 'cutoff' value.                  */
	int	min_clock;

	/* a reference to the settings value specifying number of days the records must be kept */
	int	*phistory;
}
zbx_hk_rule_t;

/* housekeeper table => configuration data mapping.                       */
/* This structure is used to map table names used in housekeeper table to */
/* configuration data.                                                    */
typedef struct
{
	/* housekeeper table name */
	char		*name;

	/* a reference to housekeeping configuration enable value for this table */
	unsigned char	*poption_mode;
}
zbx_hk_cleanup_table_t;

/* Housekeeper table mapping to housekeeping configuration values.    */
/* This mapping is used to exclude disabled tables from housekeeping  */
/* cleanup procedure.                                                 */
static zbx_hk_cleanup_table_t	hk_cleanup_tables[] = {
	{"history", &cfg.hk.history_mode},
	{"history_log", &cfg.hk.history_mode},
	{"history_str", &cfg.hk.history_mode},
	{"history_text", &cfg.hk.history_mode},
	{"history_uint", &cfg.hk.history_mode},
	{"trends", &cfg.hk.trends_mode},
	{"trends_uint", &cfg.hk.trends_mode},
	{NULL}
};

/* trends table offsets in the hk_cleanup_tables[] mapping  */
#define HK_UPDATE_CACHE_OFFSET_TREND_FLOAT	ITEM_VALUE_TYPE_MAX
#define HK_UPDATE_CACHE_OFFSET_TREND_UINT	(HK_UPDATE_CACHE_OFFSET_TREND_FLOAT + 1)

/* the oldest record timestamp cache for items in history tables */
typedef struct
{
	zbx_uint64_t	itemid;
	int		min_clock;
}
zbx_hk_item_cache_t;

/* Delete queue item definition.                                     */
/* The delete queue item defines an item that should be processed by */
/* housekeeping procedure (records older than min_clock seconds      */
/* must be removed from database).                                   */
typedef struct
{
	zbx_uint64_t	itemid;
	int		min_clock;
}
zbx_hk_delete_queue_t;

/* this structure is used to remove old records from history (trends) tables */
typedef struct
{
	/* the target table name */
	char			*table;

	/* history setting field name in items table (history|trends) */
	char			*history;

	/* a reference to the housekeeping configuration mode (enable) option for this table */
	unsigned char		*poption_mode;

	/* a reference to the housekeeping configuration overwrite option for this table */
	unsigned char		*poption_global;

	/* a reference to the housekeeping configuration history value for this table */
	int			*poption;

	/* the oldest item record timestamp cache for target table */
	zbx_hashset_t		item_cache;

	/* the item delete queue */
	zbx_vector_ptr_t	delete_queue;
}
zbx_hk_history_rule_t;

/* the history item rules, used for housekeeping history and trends tables */
static zbx_hk_history_rule_t	hk_history_rules[] = {
	{"history", "history", &cfg.hk.history_mode, &cfg.hk.history_global, &cfg.hk.history},
	{"history_str", "history", &cfg.hk.history_mode, &cfg.hk.history_global, &cfg.hk.history},
	{"history_log", "history", &cfg.hk.history_mode, &cfg.hk.history_global, &cfg.hk.history},
	{"history_uint", "history", &cfg.hk.history_mode, &cfg.hk.history_global, &cfg.hk.history},
	{"history_text", "history", &cfg.hk.history_mode, &cfg.hk.history_global, &cfg.hk.history},
	{"trends", "trends", &cfg.hk.trends_mode, &cfg.hk.trends_global, &cfg.hk.trends},
	{"trends_uint","trends", &cfg.hk.trends_mode, &cfg.hk.trends_global, &cfg.hk.trends},
	{NULL}
};

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
 * Function: hk_item_update_cache_compare                                     *
 *                                                                            *
 * Purpose: compare two delete queue items by their itemid                    *
 *                                                                            *
 * Parameters: d1 - [IN] the first delete queue item to compare               *
 *             d2 - [IN] the second delete queue item to compare              *
 *                                                                            *
 * Return value: <0 - the first item is less than the second                  *
 *               >0 - the first item is greater than the second               *
 *               =0 - the items are the same                                  *
 *                                                                            *
 * Author: Andris Zeila                                                       *
 *                                                                            *
 * Comments: this function is used to sort delete queue by itemids            *
 *                                                                            *
 ******************************************************************************/
static int	hk_item_update_cache_compare(const void *d1, const void *d2)
{
	zbx_hk_delete_queue_t	*r1 = *(zbx_hk_delete_queue_t **)d1;
	zbx_hk_delete_queue_t	*r2 = *(zbx_hk_delete_queue_t **)d2;

	ZBX_RETURN_IF_NOT_EQUAL(r1->itemid, r2->itemid);

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Function: hk_history_delete_queue_append                                   *
 *                                                                            *
 * Purpose: add item to the delete queue if necessary                         *
 *                                                                            *
 * Parameters: rule        - [IN/OUT] the history housekeeping rule           *
 *             now         - [IN] the current timestamp                       *
 *             item_record - [IN/OUT] the record from item cache containing   *
 *                           item to process and its oldest record timestamp  *
 *             history     - [IN] a number of days the history data for       *
 *                           item_record must be kept.                        *
 *                                                                            *
 * Author: Andris Zeila                                                       *
 *                                                                            *
 * Comments: If item is added to delete queue, its oldest record timestamp    *
 *           (min_clock) is updated to the calculated 'cutoff' value.         *
 *                                                                            *
 ******************************************************************************/
static void	hk_history_delete_queue_append(zbx_hk_history_rule_t *rule, int now,
		zbx_hk_item_cache_t *item_record, int history)
{
	int	keep_from = now - history * SEC_PER_DAY;

	if (keep_from > item_record->min_clock)
	{
		zbx_hk_delete_queue_t	*update_record;

		/* update oldest timestamp in item cache */
		item_record->min_clock = MIN(keep_from, item_record->min_clock + HK_MAX_DELETE_PERIODS * hk_period);

		update_record = zbx_malloc(NULL, sizeof(zbx_hk_delete_queue_t));
		update_record->itemid = item_record->itemid;
		update_record->min_clock = item_record->min_clock;
		zbx_vector_ptr_append(&rule->delete_queue, update_record);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: hk_history_prepare                                               *
 *                                                                            *
 * Purpose: prepares history housekeeping rule                                *
 *                                                                            *
 * Parameters: rule        - [IN/OUT] the history housekeeping rule           *
 *             now         - [IN] the current timestamp                       *
 *                                                                            *
 * Author: Andris Zeila                                                       *
 *                                                                            *
 * Comments: This function is called to initialize history rule data either   *
 *           at start or when housekeeping is enabled for this rule.          *
 *           It caches item history data and also prepares delete queue to be *
 *           processed during the first run.                                  *
 *                                                                            *
 ******************************************************************************/
static void	hk_history_prepare(zbx_hk_history_rule_t *rule, int now)
{
	DB_RESULT	result;
	DB_ROW		row;

	zbx_hashset_create(&rule->item_cache, 1024, zbx_default_uint64_hash_func, zbx_default_uint64_compare_func);

	zbx_vector_ptr_create(&rule->delete_queue);
	zbx_vector_ptr_reserve(&rule->delete_queue, HK_INITIAL_DELETE_QUEUE_SIZE);

	result = DBselect("select itemid,min(clock) from %s group by itemid", rule->table);

	while (NULL != (row = DBfetch(result)))
	{
		zbx_uint64_t		itemid;
		int			min_clock;
		zbx_hk_item_cache_t	item_record;

		ZBX_STR2UINT64(itemid, row[0]);
		min_clock = atoi(row[1]);

		item_record.itemid = itemid;
		item_record.min_clock = min_clock;

		zbx_hashset_insert(&rule->item_cache, &item_record, sizeof(zbx_hk_item_cache_t));
	}
	DBfree_result(result);
}

/******************************************************************************
 *                                                                            *
 * Function: hk_history_release                                               *
 *                                                                            *
 * Purpose: releases history housekeeping rule                                *
 *                                                                            *
 * Parameters: rule  - [IN/OUT] the history housekeeping rule                 *
 *                                                                            *
 * Author: Andris Zeila                                                       *
 *                                                                            *
 * Comments: This function is called to release resources allocated by        *
 *           history housekeeping rule after housekeeping was disabled        *
 *           for the table referred by this rule.                             *
 *                                                                            *
 ******************************************************************************/
static void	hk_history_release(zbx_hk_history_rule_t *rule)
{
	if (0 == rule->item_cache.num_slots)
		return;

	zbx_hashset_destroy(&rule->item_cache);
	zbx_vector_ptr_destroy(&rule->delete_queue);
}

/******************************************************************************
 *                                                                            *
 * Function: hk_history_item_update                                           *
 *                                                                            *
 * Purpose: updates history housekeeping rule with item history setting and   *
 *          adds item to the delete queue if necessary                        *
 *                                                                            *
 * Parameters: rule    - [IN/OUT] the history housekeeping rule               *
 *             now     - [IN] the current timestamp                           *
 *             itemid  - [IN] the item to update                              *
 *             history - [IN] the number of days the item data should be kept *
 *                       in history                                           *
 *                                                                            *
 * Author: Andris Zeila                                                       *
 *                                                                            *
 ******************************************************************************/
static void	hk_history_item_update(zbx_hk_history_rule_t *rule, int now, zbx_uint64_t itemid, int history)
{
	zbx_hk_item_cache_t	*item_record;

	if (ZBX_HK_OPTION_DISABLED == *rule->poption_mode)
		return;

	item_record = zbx_hashset_search(&rule->item_cache, &itemid);

	if (NULL == item_record)
	{
		zbx_hk_item_cache_t	item_data = {itemid, now};

		item_record = zbx_hashset_insert(&rule->item_cache, &item_data, sizeof(zbx_hk_item_cache_t));
		if (NULL == item_record)
			return;
	}

	hk_history_delete_queue_append(rule, now, item_record, history);
}

/******************************************************************************
 *                                                                            *
 * Function: hk_history_update                                                *
 *                                                                            *
 * Purpose: updates history housekeeping rule with the latest item history    *
 *          settings and prepares delete queue                                *
 *                                                                            *
 * Parameters: rule  - [IN/OUT] the history housekeeping rule                 *
 *             now   - [IN] the current timestamp                             *
 *                                                                            *
 * Author: Andris Zeila                                                       *
 *                                                                            *
 * Comments: This function is called to release resources allocated by        *
 *           history housekeeping rule after housekeeping was disabled        *
 *           for the table referred by this rule.                             *
 *                                                                            *
 ******************************************************************************/
static void	hk_history_update(zbx_hk_history_rule_t *rules, int now)
{
	DB_RESULT	result;
	DB_ROW		row;

	result = DBselect(
			"select i.itemid,i.value_type,i.history,i.trends"
			" from items i,hosts h"
			" where i.hostid=h.hostid"
				" and h.status in (%d,%d)",
			HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED);

	while (NULL != (row = DBfetch(result)))
	{
		zbx_uint64_t		itemid;
		int			history, trends, value_type;
		zbx_hk_history_rule_t	*rule;

		ZBX_STR2UINT64(itemid, row[0]);
		value_type = atoi(row[1]);
		history = atoi(row[2]);
		trends = atoi(row[3]);

		if (value_type < ITEM_VALUE_TYPE_MAX)
		{
			rule = rules + value_type;
			if (ZBX_HK_OPTION_ENABLED == *rule->poption_global)
				history = *rule->poption;
			hk_history_item_update(rule, now, itemid, history);
		}
		if (value_type == ITEM_VALUE_TYPE_FLOAT)
		{
			rule = rules + HK_UPDATE_CACHE_OFFSET_TREND_FLOAT;
			if (ZBX_HK_OPTION_ENABLED == *rule->poption_global)
				trends = *rule->poption;
			hk_history_item_update(rule, now, itemid, trends);
		}
		else if (value_type == ITEM_VALUE_TYPE_UINT64)
		{
			rule = rules + HK_UPDATE_CACHE_OFFSET_TREND_UINT;

			if (ZBX_HK_OPTION_ENABLED == *rule->poption_global)
				trends = *rule->poption;
			hk_history_item_update(rule, now, itemid, trends);
		}
	}
	DBfree_result(result);
}

/******************************************************************************
 *                                                                            *
 * Function: hk_history_delete_queue_prepare_all                              *
 *                                                                            *
 * Purpose: prepares history housekeeping delete queues for all defined       *
 *          history rules.                                                    *
 *                                                                            *
 * Parameters: rules  - [IN/OUT] the history housekeeping rules               *
 *             now    - [IN] the current timestamp                            *
 *                                                                            *
 * Author: Andris Zeila                                                       *
 *                                                                            *
 * Comments: This function also handles history rule initializing/releasing   *
 *           when the rule just became enabled/disabled.                      *
 *                                                                            *
 ******************************************************************************/
static void	hk_history_delete_queue_prepare_all(zbx_hk_history_rule_t *rules, int now)
{
	const char		*__function_name = "hk_history_delete_queue_prepare_all";

	zbx_hk_history_rule_t	*rule;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	/* prepare history item cache (hashset containing itemid:min_clock values) */
	for (rule = rules; NULL != rule->table; rule++)
	{
		if (ZBX_HK_OPTION_ENABLED == *rule->poption_mode)
		{
			if (0 == rule->item_cache.num_slots)
				hk_history_prepare(rule, now);
		}
		else if (0 != rule->item_cache.num_slots)
			hk_history_release(rule);
	}

	hk_history_update(rules, now);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: hk_history_delete_queue_clear                                    *
 *                                                                            *
 * Purpose: clears the history housekeeping delete queue                      *
 *                                                                            *
 * Parameters: rule   - [IN/OUT] the history housekeeping rule                *
 *             now    - [IN] the current timestamp                            *
 *                                                                            *
 * Author: Andris Zeila                                                       *
 *                                                                            *
 ******************************************************************************/
static void	hk_history_delete_queue_clear(zbx_hk_history_rule_t *rule)
{
	zbx_vector_ptr_clear_ext(&rule->delete_queue, zbx_ptr_free);
}

/******************************************************************************
 *                                                                            *
 * Function: housekeeping_history_and_trends                                  *
 *                                                                            *
 * Purpose: performs housekeeping for history and trends tables               *
 *                                                                            *
 * Parameters: now    - [IN] the current timestamp                            *
 *                                                                            *
 * Author: Andris Zeila                                                       *
 *                                                                            *
 ******************************************************************************/
static int	housekeeping_history_and_trends(int now)
{
	const char		*__function_name = "housekeeping_history_and_trends";

	int			deleted = 0, i, rc;
	zbx_hk_history_rule_t	*rule;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() now:%d", __function_name, now);

	/* prepare delete queues for all history housekeeping rules */
	hk_history_delete_queue_prepare_all(hk_history_rules, now);

	for (rule = hk_history_rules; NULL != rule->table; rule++)
	{
		if (ZBX_HK_OPTION_DISABLED == *rule->poption_mode)
			continue;

		/* process housekeeping rule */

		zbx_vector_ptr_sort(&rule->delete_queue, hk_item_update_cache_compare);

		for (i = 0; i < rule->delete_queue.values_num; i++)
		{
			zbx_hk_delete_queue_t	*item_record = rule->delete_queue.values[i];

			rc = DBexecute("delete from %s where itemid=" ZBX_FS_UI64 " and clock<%d",
					rule->table, item_record->itemid, item_record->min_clock);
			if (ZBX_DB_OK < rc)
				deleted += rc;
		}

		/* clear history rule delete queue so it's ready for the next housekeeping cycle */
		hk_history_delete_queue_clear(rule);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d", __function_name, deleted);

	return deleted;
}

/******************************************************************************
 *                                                                            *
 * Function: housekeeping_process_rule                                        *
 *                                                                            *
 * Purpose: removes old records from a table according to the specified rule  *
 *                                                                            *
 * Parameters: now  - [IN] the current time in seconds                        *
 *             rule - [IN/OUT] the housekeeping rule specifying table to      *
 *                    clean and the required data (fields, filters, time)     *
 *                                                                            *
 * Return value: the number of deleted records                                *
 *                                                                            *
 * Author: Andris Zeila                                                       *
 *                                                                            *
 ******************************************************************************/
static int	housekeeping_process_rule(int now, zbx_hk_rule_t *rule)
{
	const char	*__function_name = "housekeeping_process_rule";

	DB_RESULT	result;
	DB_ROW		row;
	int		keep_from, deleted = 0;
	int		rc;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() table:'%s' filter:'%s' min_clock:%d now:%d",
			__function_name, rule->table, rule->filter, rule->min_clock, now);

	/* initialize min_clock with the oldest record timestamp from database */
	if (0 == rule->min_clock)
	{
		result = DBselect("select min(clock) from %s%s%s", rule->table,
				('\0' != *rule->filter ? " where " : ""), rule->filter);
		if (NULL != (row = DBfetch(result)) && SUCCEED != DBis_null(row[0]))
			rule->min_clock = atoi(row[0]);
		else
			rule->min_clock = now;

		DBfree_result(result);
	}

	/* Delete the old records from database. Don't remove more than 4 x housekeeping */
	/* periods worth of data to prevent database stalling.                           */
	keep_from = now - *rule->phistory * SEC_PER_DAY;
	if (keep_from > rule->min_clock)
	{
		rule->min_clock = MIN(keep_from, rule->min_clock + HK_MAX_DELETE_PERIODS * hk_period);

		rc = DBexecute("delete from %s where %s%sclock<%d", rule->table, rule->filter,
				('\0' != *rule->filter ? " and " : ""), rule->min_clock);

		if (ZBX_DB_OK <= rc)
			deleted = rc;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d", __function_name, deleted);

	return deleted;
}

/******************************************************************************
 *                                                                            *
 * Function: housekeeping_cleanup                                             *
 *                                                                            *
 * Purpose: remove deleted items data                                         *
 *                                                                            *
 * Return value: number of rows deleted                                       *
 *                                                                            *
 * Author: Alexei Vladishev, Dmitry Borovikov                                 *
 *                                                                            *
 * Comments: sqlite3 does not use CONFIG_MAX_HOUSEKEEPER_DELETE, deletes all  *
 *                                                                            *
 ******************************************************************************/
static int	housekeeping_cleanup()
{
	const char		*__function_name = "housekeeping_cleanup";

	DB_HOUSEKEEPER		housekeeper;
	DB_RESULT		result;
	DB_ROW			row;
	int			d, deleted = 0;
	zbx_vector_uint64_t	housekeeperids;
	char			*sql = NULL, *table_name_esc;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_hk_cleanup_table_t *table;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	/* first handle the trivial case when history and trend housekeeping is disabled */
	if (ZBX_HK_OPTION_DISABLED == cfg.hk.history_mode && ZBX_HK_OPTION_DISABLED == cfg.hk.trends_mode)
		goto out;

	zbx_vector_uint64_create(&housekeeperids);

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select housekeeperid,tablename,field,value"
			" from housekeeper"
			" where tablename in (");

	/* assemble list of tables excluded from housekeeping procedure */
	for (table = hk_cleanup_tables; NULL != table->name; table++)
	{
		if (ZBX_HK_OPTION_ENABLED != *table->poption_mode)
			continue;

		table_name_esc = DBdyn_escape_string(table->name);

		zbx_chrcpy_alloc(&sql, &sql_alloc, &sql_offset, '\'');
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, table_name_esc);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "',");

		zbx_free(table_name_esc);
	}
	sql_offset--;

	/* order by tablename to effectively use DB cache */
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ") order by tablename");

	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(housekeeper.housekeeperid, row[0]);
		housekeeper.tablename = row[1];
		housekeeper.field = row[2];
		ZBX_STR2UINT64(housekeeper.value, row[3]);

		if (0 == CONFIG_MAX_HOUSEKEEPER_DELETE)
		{
			d = DBexecute(
					"delete from %s"
					" where %s=" ZBX_FS_UI64,
					housekeeper.tablename,
					housekeeper.field,
					housekeeper.value);
		}
		else
		{
#if defined(HAVE_IBM_DB2) || defined(HAVE_ORACLE)
			d = DBexecute(
					"delete from %s"
					" where %s=" ZBX_FS_UI64
						" and rownum<=%d",
					housekeeper.tablename,
					housekeeper.field,
					housekeeper.value,
					CONFIG_MAX_HOUSEKEEPER_DELETE);
#elif defined(HAVE_MYSQL)
			d = DBexecute(
					"delete from %s"
					" where %s=" ZBX_FS_UI64 " limit %d",
					housekeeper.tablename,
					housekeeper.field,
					housekeeper.value,
					CONFIG_MAX_HOUSEKEEPER_DELETE);
#elif defined(HAVE_POSTGRESQL)
			d = DBexecute(
					"delete from %s"
					" where ctid = any(array(select ctid from %s"
						" where %s=" ZBX_FS_UI64 " limit %d))",
					housekeeper.tablename,
					housekeeper.tablename,
					housekeeper.field,
					housekeeper.value,
					CONFIG_MAX_HOUSEKEEPER_DELETE);
#elif defined(HAVE_SQLITE3)
			d = 0;
#endif
		}

		if (ZBX_DB_OK <= d)
		{
			if (0 == CONFIG_MAX_HOUSEKEEPER_DELETE || CONFIG_MAX_HOUSEKEEPER_DELETE > d)
				zbx_vector_uint64_append(&housekeeperids, housekeeper.housekeeperid);

			deleted += d;
		}
	}
	DBfree_result(result);

	if (0 != housekeeperids.values_num)
	{
		zbx_vector_uint64_sort(&housekeeperids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		sql_offset = 0;
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from housekeeper where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "housekeeperid",
				housekeeperids.values, housekeeperids.values_num);

		DBexecute("%s", sql);
	}

	zbx_free(sql);

	zbx_vector_uint64_destroy(&housekeeperids);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d", __function_name, deleted);

	return deleted;
}

static int	housekeeping_sessions(int now)
{
	const char	*__function_name = "housekeeping_sessions";

	int		deleted = 0, rc;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() now:%d", __function_name, now);

	if (ZBX_HK_OPTION_ENABLED == cfg.hk.sessions_mode)
	{
		rc = DBexecute("delete from sessions where lastaccess<%d", now - SEC_PER_DAY * cfg.hk.sessions);

		if (ZBX_DB_OK <= rc)
			deleted = rc;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d", __function_name, deleted);

	return deleted;
}

static int	housekeeping_services(int now)
{
	static zbx_hk_rule_t	rule = {"service_alarms", "", 0, &cfg.hk.services};

	if (ZBX_HK_OPTION_ENABLED == cfg.hk.services_mode)
		return housekeeping_process_rule(now, &rule);

	return 0;
}

static int	housekeeping_audit(int now)
{
	static zbx_hk_rule_t	rule = {"auditlog", "", 0, &cfg.hk.audit};

	if (ZBX_HK_OPTION_ENABLED == cfg.hk.audit_mode)
		return housekeeping_process_rule(now, &rule);

	return 0;
}

static int	housekeeping_events(int now)
{
	static zbx_hk_rule_t 	rules[] = {
		{"events", "source=" ZBX_STR(EVENT_SOURCE_TRIGGERS)
			" and object=" ZBX_STR(EVENT_OBJECT_TRIGGER), 0, &cfg.hk.events_trigger},
		{"events", "source=" ZBX_STR(EVENT_SOURCE_DISCOVERY)
			" and object=" ZBX_STR(EVENT_OBJECT_DHOST), 0, &cfg.hk.events_discovery},
		{"events", "source=" ZBX_STR(EVENT_SOURCE_DISCOVERY)
			" and object=" ZBX_STR(EVENT_OBJECT_DSERVICE), 0, &cfg.hk.events_discovery},
		{"events", "source=" ZBX_STR(EVENT_SOURCE_AUTO_REGISTRATION)
			" and object=" ZBX_STR(EVENT_OBJECT_ZABBIX_ACTIVE), 0, &cfg.hk.events_autoreg},
		{"events", "source=" ZBX_STR(EVENT_SOURCE_INTERNAL)
			" and object=" ZBX_STR(EVENT_OBJECT_TRIGGER), 0, &cfg.hk.events_internal},
		{"events", "source=" ZBX_STR(EVENT_SOURCE_INTERNAL)
			" and object=" ZBX_STR(EVENT_OBJECT_ITEM), 0, &cfg.hk.events_internal},
		{"events", "source=" ZBX_STR(EVENT_SOURCE_INTERNAL)
			" and object=" ZBX_STR(EVENT_OBJECT_LLDRULE), 0, &cfg.hk.events_internal},
		{NULL}
	};

	int			deleted = 0;
	zbx_hk_rule_t		*rule;

	if (ZBX_HK_OPTION_ENABLED != cfg.hk.events_mode)
		return 0;

	for (rule = rules; NULL != rule->table; rule++)
		deleted += housekeeping_process_rule(now, rule);

	return deleted;
}

static int	get_housekeeping_period(double time_slept)
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
	int	now, d_history_and_trends, d_cleanup, d_events, d_sessions, d_services, d_audit, sleeptime;
	double	sec, time_slept;
	char	sleeptext[25];

	process_type = ((zbx_thread_args_t *)args)->process_type;
	server_num = ((zbx_thread_args_t *)args)->server_num;
	process_num = ((zbx_thread_args_t *)args)->process_num;

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_daemon_type_string(daemon_type),
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

		time_slept = zbx_time() - sec;

		hk_period = get_housekeeping_period(time_slept);

		zabbix_log(LOG_LEVEL_WARNING, "executing housekeeper");

		now = time(NULL);

		zbx_setproctitle("%s [connecting to the database]", get_process_type_string(process_type));
		DBconnect(ZBX_DB_CONNECT_NORMAL);

		zbx_config_get(&cfg, ZBX_CONFIG_FLAGS_HOUSEKEEPER);

		zbx_setproctitle("%s [removing old history and trends]", get_process_type_string(process_type));
		sec = zbx_time();
		d_history_and_trends = housekeeping_history_and_trends(now);

		zbx_setproctitle("%s [removing deleted items data]", get_process_type_string(process_type));
		d_cleanup = housekeeping_cleanup();

		zbx_setproctitle("%s [removing old events]", get_process_type_string(process_type));
		d_events = housekeeping_events(now);

		zbx_setproctitle("%s [removing old sessions]", get_process_type_string(process_type));
		d_sessions = housekeeping_sessions(now);

		zbx_setproctitle("%s [removing old service alarms]", get_process_type_string(process_type));
		d_services = housekeeping_services(now);

		zbx_setproctitle("%s [removing old audit log items]", get_process_type_string(process_type));
		d_audit = housekeeping_audit(now);

		sec = zbx_time() - sec;

		zabbix_log(LOG_LEVEL_WARNING, "%s [deleted %d hist/trends, %d items, %d events, %d sessions, %d alarms,"
				" %d audit items in " ZBX_FS_DBL " sec, %s]",
				get_process_type_string(process_type), d_history_and_trends, d_cleanup, d_events,
				d_sessions, d_services, d_audit, sec, sleeptext);

		zbx_config_clean(&cfg);

		DBclose();

		zbx_setproctitle("%s [deleted %d hist/trends, %d items, %d events, %d sessions, %d alarms, %d audit "
				"items in " ZBX_FS_DBL " sec, %s]",
				get_process_type_string(process_type), d_history_and_trends, d_cleanup, d_events,
				d_sessions, d_services, d_audit, sec, sleeptext);

		if (0 != CONFIG_HOUSEKEEPING_FREQUENCY)
			sleeptime = CONFIG_HOUSEKEEPING_FREQUENCY * SEC_PER_HOUR;
	}
}
