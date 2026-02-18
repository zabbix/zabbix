/*
** Copyright (C) 2001-2026 Zabbix SIA
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

#include "housekeeper_table.h"

#include "zbxdb.h"
#include "zbxcacheconfig.h"
#include "zbxalgo.h"
#include "zbxnum.h"

#include "trigger_housekeeper.h"

#define ZBX_HK_OBJECT_ITEM	0
#define ZBX_HK_OBJECT_TRIGGER	1
#define ZBX_HK_OBJECT_SERVICE	2
#define ZBX_HK_OBJECT_DHOST	3
#define ZBX_HK_OBJECT_DSERVICE	4

#define ZBX_HK_OBJECT_NUM	5

typedef struct {
	zbx_uint64_t	objectid;
	zbx_uint64_t	housekeeperid;
	unsigned int	progress;
} hk_entry_t;

typedef struct {
	zbx_uint64_t	housekeeperid;
	int		object;
	zbx_uint64_t	objectid;
} hk_housekeeper_t;

ZBX_PTR_VECTOR_DECL(hk_housekeeper, hk_housekeeper_t)
ZBX_PTR_VECTOR_IMPL(hk_housekeeper, hk_housekeeper_t)

/* this structure is used to list tables to cleanup */
typedef struct {
	/* cleanup table name */
	const char	*name;

	/* filter in cleanup table */
	const char	*filter;

	/* related housekeeping mode */
	int		(*get_hk_mode)(void);

	/* statistics type to add deleted entry count */
	int		stats_type;
} hk_cleanup_table_t;

/* Cache of housekeeper entries which require multiple different operations. */
static zbx_hashset_t	hk_cache;

#define ITEM_PROBLEM_FILTER	\
	"source=" ZBX_STR(EVENT_SOURCE_INTERNAL) \
	" and object=" ZBX_STR(EVENT_OBJECT_ITEM) \
	" and objectid=" ZBX_FS_UI64

#define LLDRULE_PROBLEM_FILTER	\
	"source=" ZBX_STR(EVENT_SOURCE_INTERNAL) \
	" and object="ZBX_STR(EVENT_OBJECT_LLDRULE) \
	" and objectid=" ZBX_FS_UI64

#define HK_STATS_HISTORY_TRENDS		0
#define HK_STATS_PROBLEM		1

/* tables to be cleared upon item deletion */
static hk_cleanup_table_t	hk_item_cleanup_order[] = {
/* NOTE: there must be no more than 32 elements in this array as bits of int are used for tracing table cleanup */
	{"history",		"itemid=" ZBX_FS_UI64,	&hk_cfg_history_mode,	HK_STATS_HISTORY_TRENDS},
	{"history_str",		"itemid=" ZBX_FS_UI64,	&hk_cfg_history_mode,	HK_STATS_HISTORY_TRENDS},
	{"history_log",		"itemid=" ZBX_FS_UI64,	&hk_cfg_history_mode,	HK_STATS_HISTORY_TRENDS},
	{"history_uint",	"itemid=" ZBX_FS_UI64,	&hk_cfg_history_mode,	HK_STATS_HISTORY_TRENDS},
	{"history_text",	"itemid=" ZBX_FS_UI64,	&hk_cfg_history_mode,	HK_STATS_HISTORY_TRENDS},
	{"history_bin",		"itemid=" ZBX_FS_UI64,	&hk_cfg_history_mode,	HK_STATS_HISTORY_TRENDS},
	{"history_json",	"itemid=" ZBX_FS_UI64,	&hk_cfg_history_mode,	HK_STATS_HISTORY_TRENDS},
	{"trends",		"itemid=" ZBX_FS_UI64,	&hk_cfg_trends_mode,	HK_STATS_HISTORY_TRENDS},
	{"trends_uint",		"itemid=" ZBX_FS_UI64,	&hk_cfg_trends_mode,	HK_STATS_HISTORY_TRENDS},
	{"problem",		ITEM_PROBLEM_FILTER, 	NULL,			HK_STATS_PROBLEM},
	{"problem",		LLDRULE_PROBLEM_FILTER, NULL,			HK_STATS_PROBLEM}
};

/******************************************************************************
 *                                                                            *
 * Purpose: perform generic table cleanup                                     *
 *                                                                            *
 * Parameters: table                - [IN]                                    *
 *             filter               - [IN]                                    *
 *             config_max_hk_delete - [IN]                                    *
 *             more                 - [OUT] 1 if there might be more data to  *
 *                     remove, otherwise the value is not changed             *
 *                                                                            *
 * Return value: number of rows deleted                                       *
 *                                                                            *
 ******************************************************************************/
static int	hk_table_cleanup(const char *table, const char *filter, int config_max_hk_delete, int *more)
{
	int	ret = hk_delete_from_table(table, filter, config_max_hk_delete);

	if (ZBX_DB_OK > ret || (0 != config_max_hk_delete && ret == config_max_hk_delete))
		*more = 1;

	return ZBX_DB_OK <= ret ? ret : 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: perform cleanup upon item deletion                                *
 *                                                                            *
 * Parameters: hk_entries           - [IN] collected housekeeper entries      *
 *             config_max_hk_delete - [IN]                                    *
 *             deleteids            - [IN/OUT] housekeeper ids to delete      *
 *             deleted_history      - [OUT] count of deleted history and      *
 *                                          trends entries                    *
 *             deleted_problems     - [OUT] count of deleted problems entries *
 *                                                                            *
 ******************************************************************************/
static void	hk_item_cleanup(const zbx_vector_hk_housekeeper_t *hk_entries, int config_max_hk_delete,
		zbx_vector_uint64_t *deleteids, int *deleted_history, int *deleted_problems)
{
	unsigned int	complete_mask = (1 << ARRSIZE(hk_item_cleanup_order)) - 1;

	/* delete in order of tables to optimize database cache */
	for (size_t i = 0; i < ARRSIZE(hk_item_cleanup_order); i++)
	{
		const hk_cleanup_table_t	*table = &hk_item_cleanup_order[i];

		if (NULL != table->get_hk_mode && ZBX_HK_MODE_REGULAR != table->get_hk_mode())
		{
			/* remove this table from expected complete task mask */
			complete_mask &= ~(1 << i);
			continue;
		}

		for (int j = 0; j < hk_entries->values_num; j++)
		{
			zbx_uint64_t	housekeeperid = hk_entries->values[j].housekeeperid;
			zbx_uint64_t	objectid = hk_entries->values[j].objectid;
			hk_entry_t	*entry;

			if (NULL == (entry = zbx_hashset_search(&hk_cache, &objectid)))
			{
				hk_entry_t	entry_local = {objectid, housekeeperid, 0};

				entry = zbx_hashset_insert(&hk_cache, &entry_local, sizeof(entry_local));
			}

			if (0 == (entry->progress & (1 << i)))
			{
				int	more = 0, deleted = 0;
				char	filter[MAX_STRING_LEN];

				zbx_snprintf(filter, sizeof(filter), table->filter, objectid);

				deleted = hk_table_cleanup(table->name, filter, config_max_hk_delete, &more);

				switch (table->stats_type)
				{
					case HK_STATS_HISTORY_TRENDS:
						*deleted_history += deleted;
						break;
					case HK_STATS_PROBLEM:
						*deleted_problems += deleted;
						break;
					default:
						THIS_SHOULD_NEVER_HAPPEN;
						break;
				}

				if (0 == more)
					entry->progress |= (1 << i);
			}
		}
	}

	for (int j = 0; j < hk_entries->values_num; j++)
	{
		zbx_uint64_t	housekeeperid = hk_entries->values[j].housekeeperid;
		zbx_uint64_t	objectid = hk_entries->values[j].objectid;
		hk_entry_t	*entry;

		if (NULL != (entry = zbx_hashset_search(&hk_cache, &objectid)))
		{
			if (complete_mask == entry->progress)
			{
				zbx_vector_uint64_append(deleteids, entry->housekeeperid);
				zbx_hashset_remove_direct(&hk_cache, entry);
			}
		}
		else
		{
			/* nothing to clean */
			zbx_vector_uint64_append(deleteids, housekeeperid);
		}
	}
}

static void	housekeep_events_by_triggerid(const zbx_vector_hk_housekeeper_t *hk_entries, int config_max_hk_delete,
		int events_mode, zbx_vector_uint64_t *deleteids, int *deleted_events, int *deleted_problems)
{
	for (int i = 0; i < hk_entries->values_num; i++)
	{
		zbx_uint64_t	triggerid = hk_entries->values[i].objectid;
		zbx_uint64_t	housekeeperid = hk_entries->values[i].housekeeperid;

		if (SUCCEED == zbx_dc_config_trigger_exists(triggerid))
			continue;

		int	more = 0;
		char	query[MAX_STRING_LEN];

		zbx_snprintf(query, sizeof(query), "select eventid"
				" from events"
				" where source=%d"
					" and object=%d"
					" and objectid=" ZBX_FS_UI64 " order by eventid",
				EVENT_SOURCE_TRIGGERS, EVENT_OBJECT_TRIGGER, triggerid);

		zbx_housekeep_problems_events(query, config_max_hk_delete, events_mode, &more, deleted_events,
				deleted_problems);

		zbx_snprintf(query, sizeof(query), "select eventid"
				" from events"
				" where source=%d"
					" and object=%d"
					" and objectid=" ZBX_FS_UI64 " order by eventid",
					EVENT_SOURCE_INTERNAL, EVENT_OBJECT_TRIGGER, triggerid);

		zbx_housekeep_problems_events(query, config_max_hk_delete, events_mode, &more, deleted_events,
				deleted_problems);

		if (0 == more)
			zbx_vector_uint64_append(deleteids, housekeeperid);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: perform problem table cleanup                                     *
 *                                                                            *
 * Parameters: hk_entries           - [IN] collected housekeeper entries      *
 *             table                - [IN] event/problem table name           *
 *             source               - [IN] event/problem source               *
 *             object               - [IN] event/problem object type          *
 *             config_max_hk_delete - [IN]                                    *
 *             deleteids            - [IN] housekeeper ids to delete          *
 *                                                                            *
 * Return value: number of rows deleted                                       *
 *                                                                            *
 ******************************************************************************/
static int	hk_problem_cleanup(const zbx_vector_hk_housekeeper_t *hk_entries, const char *table, int source,
		int object, int config_max_hk_delete, zbx_vector_uint64_t *deleteids)
{
	int	deleted = 0;

	for (int i = 0; i < hk_entries->values_num; i++)
	{
		char		filter[MAX_STRING_LEN];
		zbx_uint64_t	objectid = hk_entries->values[i].objectid;
		zbx_uint64_t	housekeeperid = hk_entries->values[i].housekeeperid;
		int		ret;

		zbx_snprintf(filter, sizeof(filter), "source=%d and object=%d and objectid=" ZBX_FS_UI64,
				source, object, objectid);

		ret = hk_delete_from_table(table, filter, config_max_hk_delete);

		if (ZBX_DB_OK <= ret)
		{
			deleted += ret;
			if (0 == config_max_hk_delete || ret < config_max_hk_delete)
				zbx_vector_uint64_append(deleteids, housekeeperid);
		}
	}

	return deleted;
}

void	housekeeper_init(void)
{
	zbx_hashset_create(&hk_cache, 5, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
}

void	housekeeper_deinit(void)
{
	zbx_hashset_destroy(&hk_cache);
}

void	housekeeper_process(int config_max_hk_delete, int *deleted_history, int *deleted_events, int *deleted_problems)
{
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	zbx_vector_uint64_t	deleteids;
	int			error_logged = 0;

	zbx_vector_hk_housekeeper_t	ids[ZBX_HK_OBJECT_NUM];

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (NULL == (result = zbx_db_select("select housekeeperid,object,objectid from housekeeper")))
		goto out;

	zbx_vector_uint64_create(&deleteids);

	for (int i = 0; i < ZBX_HK_OBJECT_NUM; i++)
	{
		zbx_vector_hk_housekeeper_create(&ids[i]);
	}

	while (NULL != (row = zbx_db_fetch(result)))
	{
		hk_housekeeper_t	hk;

		ZBX_STR2UINT64(hk.housekeeperid, row[0]);
		hk.object = atoi(row[1]);
		ZBX_STR2UINT64(hk.objectid, row[2]);

		if (ZBX_HK_OBJECT_NUM > hk.object && 0 <= hk.object)
		{
			zbx_vector_hk_housekeeper_append(&ids[hk.object], hk);
		}
		else if (0 == error_logged) /* limit logging */
		{
			THIS_SHOULD_NEVER_HAPPEN_MSG("invalid housekeeperid:" ZBX_FS_UI64 " object: %d",
					hk.housekeeperid, hk.object);
			error_logged = 1;
		}
	}
	zbx_db_free_result(result);

	for (int object = 0; object < ZBX_HK_OBJECT_NUM; object++)
	{
		switch (object)
		{
			case ZBX_HK_OBJECT_ITEM:
				hk_item_cleanup(&ids[object], config_max_hk_delete, &deleteids, deleted_history,
						deleted_problems);
				break;
			case ZBX_HK_OBJECT_TRIGGER:
				housekeep_events_by_triggerid(&ids[object], config_max_hk_delete,
						hk_cfg_events_mode(), &deleteids, deleted_events, deleted_problems);
				break;
			case ZBX_HK_OBJECT_SERVICE:
				*deleted_problems += hk_problem_cleanup(&ids[object], "problem", EVENT_SOURCE_SERVICE,
						EVENT_OBJECT_SERVICE, config_max_hk_delete, &deleteids);
				break;
			case ZBX_HK_OBJECT_DHOST:
				*deleted_events += hk_problem_cleanup(&ids[object], "events", EVENT_SOURCE_DISCOVERY,
						EVENT_OBJECT_DHOST, config_max_hk_delete, &deleteids);
				break;
			case ZBX_HK_OBJECT_DSERVICE:
				*deleted_events += hk_problem_cleanup(&ids[object], "events", EVENT_SOURCE_DISCOVERY,
						EVENT_OBJECT_DSERVICE, config_max_hk_delete, &deleteids);
				break;
		}

		zbx_vector_hk_housekeeper_destroy(&ids[object]);
	}

	if (0 != deleteids.values_num)
	{
		zbx_vector_uint64_sort(&deleteids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_db_execute_multiple_query("delete from housekeeper where", "housekeeperid", &deleteids);
	}

	zbx_vector_uint64_destroy(&deleteids);

out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() deleted_history:%d deleted_events:%d deleted_problems:%d", __func__,
			*deleted_history, *deleted_events, *deleted_problems);
}
