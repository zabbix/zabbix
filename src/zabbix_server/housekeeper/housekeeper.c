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
#include "db.h"
#include "dbcache.h"
#include "log.h"
#include "daemon.h"
#include "zbxself.h"
#include "zbxalgo.h"
#include "zbxserver.h"

#include "zbxhistory.h"
#include "housekeeper.h"

extern unsigned char	process_type, program_type;
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
	const char	*table;

	/* Optional filter, must be empty string if not used. Only the records matching */
	/* filter are subject to housekeeping procedures.                               */
	const char	*filter;

	/* The oldest record in table (with filter in effect). The min_clock value is   */
	/* read from the database when accessed for the first time and then during      */
	/* housekeeping procedures updated to the last 'cutoff' value.                  */
	int		min_clock;

	/* a reference to the settings value specifying number of seconds the records must be kept */
	int		*phistory;
}
zbx_hk_rule_t;

/* housekeeper table => configuration data mapping.                       */
/* This structure is used to map table names used in housekeeper table to */
/* configuration data.                                                    */
typedef struct
{
	/* housekeeper table name */
	const char		*name;

	/* a reference to housekeeping configuration enable value for this table */
	unsigned char		*poption_mode;
}
zbx_hk_cleanup_table_t;

static unsigned char poption_mode_enabled = ZBX_HK_OPTION_ENABLED;

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
	/* force events housekeeping mode on to perform problem cleanup when events housekeeping is disabled */
	{"events", &poption_mode_enabled},
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
	const char		*table;

	/* history setting field name in items table (history|trends) */
	const char		*history;

	/* a reference to the housekeeping configuration mode (enable) option for this table */
	unsigned char		*poption_mode;

	/* a reference to the housekeeping configuration overwrite option for this table */
	unsigned char		*poption_global;

	/* a reference to the housekeeping configuration history value for this table */
	int			*poption;

	/* type for checking which values are sent to the history storage */
	unsigned char		type;

	/* the oldest item record timestamp cache for target table */
	zbx_hashset_t		item_cache;

	/* the item delete queue */
	zbx_vector_ptr_t	delete_queue;
}
zbx_hk_history_rule_t;

/* the history item rules, used for housekeeping history and trends tables */
static zbx_hk_history_rule_t	hk_history_rules[] = {
	{.table = "history",		.history = "history",	.poption_mode = &cfg.hk.history_mode,
			.poption_global = &cfg.hk.history_global,	.poption = &cfg.hk.history,
			.type = ITEM_VALUE_TYPE_FLOAT},
	{.table = "history_str",	.history = "history",	.poption_mode = &cfg.hk.history_mode,
			.poption_global = &cfg.hk.history_global,	.poption = &cfg.hk.history,
			.type = ITEM_VALUE_TYPE_STR},
	{.table = "history_log",	.history = "history",	.poption_mode = &cfg.hk.history_mode,
			.poption_global = &cfg.hk.history_global,	.poption = &cfg.hk.history,
			.type = ITEM_VALUE_TYPE_LOG},
	{.table = "history_uint",	.history = "history",	.poption_mode = &cfg.hk.history_mode,
			.poption_global = &cfg.hk.history_global,	.poption = &cfg.hk.history,
			.type = ITEM_VALUE_TYPE_UINT64},
	{.table = "history_text",	.history = "history",	.poption_mode = &cfg.hk.history_mode,
			.poption_global = &cfg.hk.history_global,	.poption = &cfg.hk.history,
			.type = ITEM_VALUE_TYPE_TEXT},
	{.table = "trends",		.history = "trends",	.poption_mode = &cfg.hk.trends_mode,
			.poption_global = &cfg.hk.trends_global,	.poption = &cfg.hk.trends,
			.type = ITEM_VALUE_TYPE_FLOAT},
	{.table = "trends_uint",	.history = "trends",	.poption_mode = &cfg.hk.trends_mode,
			.poption_global = &cfg.hk.trends_global,	.poption = &cfg.hk.trends,
			.type = ITEM_VALUE_TYPE_UINT64},
	{NULL}
};

static void	zbx_housekeeper_sigusr_handler(int flags)
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
 *             history     - [IN] a number of seconds the history data for    *
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
	int	keep_from;

	if (history > now)
		return;	/* there shouldn't be any records with negative timestamps, nothing to do */

	keep_from = now - history;

	if (keep_from > item_record->min_clock)
	{
		zbx_hk_delete_queue_t	*update_record;

		/* update oldest timestamp in item cache */
		item_record->min_clock = MIN(keep_from, item_record->min_clock + HK_MAX_DELETE_PERIODS * hk_period);

		update_record = (zbx_hk_delete_queue_t *)zbx_malloc(NULL, sizeof(zbx_hk_delete_queue_t));
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
 *                                                                            *
 * Author: Andris Zeila                                                       *
 *                                                                            *
 * Comments: This function is called to initialize history rule data either   *
 *           at start or when housekeeping is enabled for this rule.          *
 *           It caches item history data and also prepares delete queue to be *
 *           processed during the first run.                                  *
 *                                                                            *
 ******************************************************************************/
static void	hk_history_prepare(zbx_hk_history_rule_t *rule)
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

	item_record = (zbx_hk_item_cache_t *)zbx_hashset_search(&rule->item_cache, &itemid);

	if (NULL == item_record)
	{
		zbx_hk_item_cache_t	item_data = {itemid, now};

		item_record = (zbx_hk_item_cache_t *)zbx_hashset_insert(&rule->item_cache, &item_data, sizeof(zbx_hk_item_cache_t));
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
	char		*tmp = NULL;

	result = DBselect(
			"select i.itemid,i.value_type,i.history,i.trends,h.hostid"
			" from items i,hosts h"
			" where i.hostid=h.hostid"
				" and h.status in (%d,%d)",
			HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED);

	while (NULL != (row = DBfetch(result)))
	{
		zbx_uint64_t		itemid, hostid;
		int			history, trends, value_type;
		zbx_hk_history_rule_t	*rule;

		ZBX_STR2UINT64(itemid, row[0]);
		value_type = atoi(row[1]);
		ZBX_STR2UINT64(hostid, row[4]);

		if (value_type < ITEM_VALUE_TYPE_MAX)
		{
			rule = rules + value_type;
			if (ZBX_HK_OPTION_DISABLED == *rule->poption_global)
			{
				tmp = zbx_strdup(tmp, row[2]);
				substitute_simple_macros(NULL, NULL, NULL, NULL, &hostid, NULL, NULL, NULL, NULL, &tmp,
						MACRO_TYPE_COMMON, NULL, 0);

				if (SUCCEED != is_time_suffix(tmp, &history, ZBX_LENGTH_UNLIMITED))
				{
					zabbix_log(LOG_LEVEL_DEBUG, "invalid history storage '%s' for itemid '%s'", tmp,
							row[0]);
				}
				else if (0 != history && ZBX_HK_HISTORY_MIN > history)
				{
					zabbix_log(LOG_LEVEL_DEBUG, "history storage too low for itemid '%s'", row[0]);
				}
				else
					hk_history_item_update(rule, now, itemid, history);
			}
			else
				hk_history_item_update(rule, now, itemid, *rule->poption);
		}

		if (ITEM_VALUE_TYPE_FLOAT == value_type || ITEM_VALUE_TYPE_UINT64 == value_type)
		{
			rule = rules + (value_type == ITEM_VALUE_TYPE_FLOAT ?
					HK_UPDATE_CACHE_OFFSET_TREND_FLOAT : HK_UPDATE_CACHE_OFFSET_TREND_UINT);

			if (ZBX_HK_OPTION_DISABLED == *rule->poption_global)
			{
				tmp = zbx_strdup(tmp, row[3]);
				substitute_simple_macros(NULL, NULL, NULL, NULL, &hostid, NULL, NULL, NULL, NULL, &tmp,
						MACRO_TYPE_COMMON, NULL, 0);

				if (SUCCEED != is_time_suffix(tmp, &trends, ZBX_LENGTH_UNLIMITED))
				{
					zabbix_log(LOG_LEVEL_DEBUG, "invalid trends storage '%s' for itemid '%s'", tmp,
							row[0]);
				}
				else if (0 != trends && ZBX_HK_TRENDS_MIN > trends)
				{
					zabbix_log(LOG_LEVEL_DEBUG, "trends storage too low for itemid '%s'", row[0]);
				}
				else
					hk_history_item_update(rule, now, itemid, trends);
			}
			else
				hk_history_item_update(rule, now, itemid, *rule->poption);
		}
	}
	DBfree_result(result);

	zbx_free(tmp);
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
				hk_history_prepare(rule);
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
		if (ZBX_HK_OPTION_DISABLED == *rule->poption_mode || FAIL == zbx_history_requires_trends(rule->type))
			continue;

		/* process housekeeping rule */

		zbx_vector_ptr_sort(&rule->delete_queue, hk_item_update_cache_compare);

		for (i = 0; i < rule->delete_queue.values_num; i++)
		{
			zbx_hk_delete_queue_t	*item_record = (zbx_hk_delete_queue_t *)rule->delete_queue.values[i];

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
	keep_from = now - *rule->phistory;
	if (keep_from > rule->min_clock)
	{
		rule->min_clock = MIN(keep_from, rule->min_clock + HK_MAX_DELETE_PERIODS * hk_period);

		rc = DBexecute("delete from %s where clock<%d%s%s", rule->table, rule->min_clock,
				('\0' != *rule->filter ? " and " : ""), rule->filter);

		if (ZBX_DB_OK <= rc)
			deleted = rc;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d", __function_name, deleted);

	return deleted;
}

/******************************************************************************
 *                                                                            *
 * Function: DBdelete_from_table                                              *
 *                                                                            *
 * Purpose: delete limited count of rows from table                           *
 *                                                                            *
 * Return value: number of deleted rows or less than 0 if an error occurred   *
 *                                                                            *
 ******************************************************************************/
static int	DBdelete_from_table(const char *tablename, const char *filter, int limit)
{
	if (0 == limit)
	{
		return DBexecute(
				"delete from %s"
				" where %s",
				tablename,
				filter);
	}
	else
	{
#if defined(HAVE_IBM_DB2) || defined(HAVE_ORACLE)
		return DBexecute(
				"delete from %s"
				" where %s"
					" and rownum<=%d",
				tablename,
				filter,
				limit);
#elif defined(HAVE_MYSQL)
		return DBexecute(
				"delete from %s"
				" where %s limit %d",
				tablename,
				filter,
				limit);
#elif defined(HAVE_POSTGRESQL)
		return DBexecute(
				"delete from %s"
				" where %s and ctid = any(array(select ctid from %s"
					" where %s limit %d))",
				tablename,
				filter,
				tablename,
				filter,
				limit);
#elif defined(HAVE_SQLITE3)
		return DBexecute(
				"delete from %s"
				" where %s",
				tablename,
				filter);
#endif
	}

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Function: hk_problem_cleanup                                               *
 *                                                                            *
 * Purpose: perform problem table cleanup                                     *
 *                                                                            *
 * Parameters: table    - [IN] the problem table name                         *
 * Parameters: source   - [IN] the event source                               *
 *             object   - [IN] the event object type                          *
 *             objectid - [IN] the event object identifier                    *
 *             more     - [OUT] 1 if there might be more data to remove,      *
 *                              otherwise the value is not changed            *
 *                                                                            *
 * Return value: number of rows deleted                                       *
 *                                                                            *
 ******************************************************************************/
static int	hk_problem_cleanup(const char *table, int source, int object, zbx_uint64_t objectid, int *more)
{
	char	filter[MAX_STRING_LEN];
	int	ret;

	zbx_snprintf(filter, sizeof(filter), "source=%d and object=%d and objectid=" ZBX_FS_UI64,
			source, object, objectid);

	ret = DBdelete_from_table(table, filter, CONFIG_MAX_HOUSEKEEPER_DELETE);

	if (ZBX_DB_OK > ret || (0 != CONFIG_MAX_HOUSEKEEPER_DELETE && ret >= CONFIG_MAX_HOUSEKEEPER_DELETE))
		*more = 1;

	return ZBX_DB_OK <= ret ? ret : 0;
}

/******************************************************************************
 *                                                                            *
 * Function: hk_table_cleanup                                                 *
 *                                                                            *
 * Purpose: perform generic table cleanup                                     *
 *                                                                            *
 * Parameters: table    - [IN] the table name                                 *
 *             field    - [IN] the field name                                 *
 *             objectid - [IN] the field value                                *
 *             more     - [OUT] 1 if there might be more data to remove,      *
 *                              otherwise the value is not changed            *
 *                                                                            *
 * Return value: number of rows deleted                                       *
 *                                                                            *
 ******************************************************************************/
static int	hk_table_cleanup(const char *table, const char *field, zbx_uint64_t id, int *more)
{
	char	filter[MAX_STRING_LEN];
	int	ret;

	zbx_snprintf(filter, sizeof(filter), "%s=" ZBX_FS_UI64, field, id);

	ret = DBdelete_from_table(table, filter, CONFIG_MAX_HOUSEKEEPER_DELETE);

	if (ZBX_DB_OK > ret || (0 != CONFIG_MAX_HOUSEKEEPER_DELETE && ret >= CONFIG_MAX_HOUSEKEEPER_DELETE))
		*more = 1;

	return ZBX_DB_OK <= ret ? ret : 0;
}

/******************************************************************************
 *                                                                            *
 * Function: housekeeping_cleanup                                             *
 *                                                                            *
 * Purpose: remove deleted items/triggers data                                *
 *                                                                            *
 * Return value: number of rows deleted                                       *
 *                                                                            *
 * Author: Alexei Vladishev, Dmitry Borovikov                                 *
 *                                                                            *
 * Comments: sqlite3 does not use CONFIG_MAX_HOUSEKEEPER_DELETE, deletes all  *
 *                                                                            *
 ******************************************************************************/
static int	housekeeping_cleanup(void)
{
	const char		*__function_name = "housekeeping_cleanup";

	DB_RESULT		result;
	DB_ROW			row;
	int			deleted = 0;
	zbx_vector_uint64_t	housekeeperids;
	char			*sql = NULL, *table_name_esc;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_hk_cleanup_table_t *table;
	zbx_uint64_t		housekeeperid, objectid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

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
		int	more = 0;

		ZBX_STR2UINT64(housekeeperid, row[0]);
		ZBX_STR2UINT64(objectid, row[3]);

		if (0 == strcmp(row[1], "events")) /* events name is used for backwards compatibility with frontend */
		{
			const char	*table_name = "problem";

			if (0 == strcmp(row[2], "triggerid"))
			{
				deleted += hk_problem_cleanup(table_name, EVENT_SOURCE_TRIGGERS, EVENT_OBJECT_TRIGGER,
						objectid, &more);
				deleted += hk_problem_cleanup(table_name, EVENT_SOURCE_INTERNAL, EVENT_OBJECT_TRIGGER,
						objectid, &more);
			}
			else if (0 == strcmp(row[2], "itemid"))
			{
				deleted += hk_problem_cleanup(table_name, EVENT_SOURCE_INTERNAL, EVENT_OBJECT_ITEM,
						objectid, &more);
			}
			else if (0 == strcmp(row[2], "lldruleid"))
			{
				deleted += hk_problem_cleanup(table_name, EVENT_SOURCE_INTERNAL, EVENT_OBJECT_LLDRULE,
						objectid, &more);
			}
		}
		else
			deleted += hk_table_cleanup(row[1], row[2], objectid, &more);

		if (0 == more)
			zbx_vector_uint64_append(&housekeeperids, housekeeperid);
	}
	DBfree_result(result);

	if (0 != housekeeperids.values_num)
	{
		zbx_vector_uint64_sort(&housekeeperids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		DBexecute_multiple_query("delete from housekeeper where", "housekeeperid", &housekeeperids);
	}

	zbx_free(sql);

	zbx_vector_uint64_destroy(&housekeeperids);

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
		char	*sql = NULL;
		size_t	sql_alloc = 0, sql_offset = 0;

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "lastaccess<%d", now - cfg.hk.sessions);
		rc = DBdelete_from_table("sessions", sql, CONFIG_MAX_HOUSEKEEPER_DELETE);
		zbx_free(sql);

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
#define ZBX_HK_EVENT_RULE	" and not exists (select null from problem where events.eventid=problem.eventid)" \
				" and not exists (select null from problem where events.eventid=problem.r_eventid)"

	static zbx_hk_rule_t 	rules[] = {
		{"events", "events.source=" ZBX_STR(EVENT_SOURCE_TRIGGERS)
			" and events.object=" ZBX_STR(EVENT_OBJECT_TRIGGER)
			ZBX_HK_EVENT_RULE, 0, &cfg.hk.events_trigger},
		{"events", "events.source=" ZBX_STR(EVENT_SOURCE_INTERNAL)
			" and events.object=" ZBX_STR(EVENT_OBJECT_TRIGGER)
			ZBX_HK_EVENT_RULE, 0, &cfg.hk.events_internal},
		{"events", "events.source=" ZBX_STR(EVENT_SOURCE_INTERNAL)
			" and events.object=" ZBX_STR(EVENT_OBJECT_ITEM)
			ZBX_HK_EVENT_RULE, 0, &cfg.hk.events_internal},
		{"events", "events.source=" ZBX_STR(EVENT_SOURCE_INTERNAL)
			" and events.object=" ZBX_STR(EVENT_OBJECT_LLDRULE)
			ZBX_HK_EVENT_RULE, 0, &cfg.hk.events_internal},
		{"events", "events.source=" ZBX_STR(EVENT_SOURCE_DISCOVERY)
			" and events.object=" ZBX_STR(EVENT_OBJECT_DHOST), 0, &cfg.hk.events_discovery},
		{"events", "events.source=" ZBX_STR(EVENT_SOURCE_DISCOVERY)
			" and events.object=" ZBX_STR(EVENT_OBJECT_DSERVICE), 0, &cfg.hk.events_discovery},
		{"events", "events.source=" ZBX_STR(EVENT_SOURCE_AUTO_REGISTRATION)
			" and events.object=" ZBX_STR(EVENT_OBJECT_ZABBIX_ACTIVE), 0, &cfg.hk.events_autoreg},
		{NULL}
	};

	int			deleted = 0;
	zbx_hk_rule_t		*rule;

	if (ZBX_HK_OPTION_ENABLED != cfg.hk.events_mode)
		return 0;

	for (rule = rules; NULL != rule->table; rule++)
		deleted += housekeeping_process_rule(now, rule);

	return deleted;
#undef ZBX_HK_EVENT_RULE
}

static int	housekeeping_problems(int now)
{
	const char	*__function_name = "housekeeping_problems";

	int		deleted = 0, rc;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() now:%d", __function_name, now);

	rc = DBexecute("delete from problem where r_clock<>0 and r_clock<%d", now - SEC_PER_DAY);

	if (ZBX_DB_OK <= rc)
		deleted = rc;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d", __function_name, deleted);

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
	int	now, d_history_and_trends, d_cleanup, d_events, d_problems, d_sessions, d_services, d_audit, sleeptime;
	double	sec, time_slept;
	char	sleeptext[25];

	process_type = ((zbx_thread_args_t *)args)->process_type;
	server_num = ((zbx_thread_args_t *)args)->server_num;
	process_num = ((zbx_thread_args_t *)args)->process_num;

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(program_type),
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

		zbx_handle_log();

		time_slept = zbx_time() - sec;

		hk_period = get_housekeeping_period(time_slept);

		zabbix_log(LOG_LEVEL_WARNING, "executing housekeeper");

		now = time(NULL);

		zbx_setproctitle("%s [connecting to the database]", get_process_type_string(process_type));
		DBconnect(ZBX_DB_CONNECT_NORMAL);

		zbx_config_get(&cfg, ZBX_CONFIG_FLAGS_HOUSEKEEPER);

		zbx_setproctitle("%s [removing old history and trends]",
				get_process_type_string(process_type));
		sec = zbx_time();
		d_history_and_trends = housekeeping_history_and_trends(now);

		zbx_setproctitle("%s [removing old problems]", get_process_type_string(process_type));
		d_problems = housekeeping_problems(now);

		zbx_setproctitle("%s [removing old events]", get_process_type_string(process_type));
		d_events = housekeeping_events(now);

		zbx_setproctitle("%s [removing old sessions]", get_process_type_string(process_type));
		d_sessions = housekeeping_sessions(now);

		zbx_setproctitle("%s [removing old service alarms]", get_process_type_string(process_type));
		d_services = housekeeping_services(now);

		zbx_setproctitle("%s [removing old audit log items]", get_process_type_string(process_type));
		d_audit = housekeeping_audit(now);

		zbx_setproctitle("%s [removing deleted items data]", get_process_type_string(process_type));
		d_cleanup = housekeeping_cleanup();

		sec = zbx_time() - sec;

		zabbix_log(LOG_LEVEL_WARNING, "%s [deleted %d hist/trends, %d items/triggers, %d events, %d problems,"
				" %d sessions, %d alarms, %d audit items in " ZBX_FS_DBL " sec, %s]",
				get_process_type_string(process_type), d_history_and_trends,
				d_cleanup, d_events, d_problems, d_sessions, d_services, d_audit, sec,
				sleeptext);

		zbx_config_clean(&cfg);

		DBclose();

		zbx_setproctitle("%s [deleted %d hist/trends, %d items/triggers, %d events, %d sessions, %d alarms,"
				" %d audit items in " ZBX_FS_DBL " sec, %s]",
				get_process_type_string(process_type), d_history_and_trends, d_cleanup, d_events,
				d_sessions, d_services, d_audit, sec, sleeptext);

		if (0 != CONFIG_HOUSEKEEPING_FREQUENCY)
			sleeptime = CONFIG_HOUSEKEEPING_FREQUENCY * SEC_PER_HOUR;

#if !defined(_WINDOWS) && defined(HAVE_RESOLV_H)
		zbx_update_resolver_conf();	/* handle /etc/resolv.conf update */
#endif
	}
}
