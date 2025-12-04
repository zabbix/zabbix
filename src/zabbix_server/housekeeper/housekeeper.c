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

#include "zbxdb.h"
#include "zbxcacheconfig.h"
#include "zbxalgo.h"
#include "zbxnum.h"

#include "housekeeper.h"
#include "trigger_housekeeper.h"

#define ZBX_HK_OBJECT_ITEM	0
#define ZBX_HK_OBJECT_TRIGGER	1
#define ZBX_HK_OBJECT_SERVICE	2
#define ZBX_HK_OBJECT_DHOST	3
#define ZBX_HK_OBJECT_DSERVICE	4

typedef struct {
	zbx_uint64_t	housekeeperid;
	zbx_uint64_t	objectid;
	int		progress;
} hk_entry_t;

/* This structure is used to list tabled to cleanup */
typedef struct {
	/* cleanup table name */
	const char	*name;

	/* filter in cleanup table */
	const char	*filter;

	/* relative housekeeping more */
	int	(*get_hk_mode)(void);
} hk_cleanup_table_t;

static zbx_hashset_t	hk_cache;

#define ITEM_PROBLEM_FILTER	\
	"source=" ZBX_STR(EVENT_SOURCE_INTERNAL) \
	" and object=" ZBX_STR(EVENT_OBJECT_ITEM) \
	" and objectid=" ZBX_FS_UI64

#define LLDRULE_PROBLEM_FILTER	\
	"source=" ZBX_STR(EVENT_SOURCE_INTERNAL) \
	" and object="ZBX_STR(EVENT_OBJECT_LLDRULE) \
	" and objectid=" ZBX_FS_UI64

static hk_cleanup_table_t	hk_item_cleanup_order[] = {
	{"history",		"itemid=" ZBX_FS_UI64,	&hk_cfg_history_mode},
	{"history_str",		"itemid=" ZBX_FS_UI64,	&hk_cfg_history_mode},
	{"history_log",		"itemid=" ZBX_FS_UI64,	&hk_cfg_history_mode},
	{"history_uint",	"itemid=" ZBX_FS_UI64,	&hk_cfg_history_mode},
	{"history_text",	"itemid=" ZBX_FS_UI64,	&hk_cfg_history_mode},
	{"history_bin",		"itemid=" ZBX_FS_UI64,	&hk_cfg_history_mode},
	/* TODO: history_json */
	{"trends",		"itemid=" ZBX_FS_UI64,	&hk_cfg_trends_mode},
	{"trends_uint",		"itemid=" ZBX_FS_UI64,	&hk_cfg_trends_mode},
	{"problem",		ITEM_PROBLEM_FILTER, 	NULL},
	{"problem",		LLDRULE_PROBLEM_FILTER, NULL},
	{0}
};

/******************************************************************************
 *                                                                            *
 * Purpose: perform generic table cleanup                                     *
 *                                                                            *
 * Parameters: table                - [IN]                                    *
 *             field                - [IN]                                    *
 *             fieldid              - [IN]                                    *
 *             config_max_hk_delete - [IN]                                    *
 *             more                 - [OUT] 1 if there might be more data to  *
 *                     remove, otherwise the value is not changed             *
 *                                                                            *
 * Return value: number of rows deleted                                       *
 *                                                                            *
 ******************************************************************************/
static int	hk_table_cleanup(const char *table, const char *filter_pattern, zbx_uint64_t objectid,
		int config_max_hk_delete, int *more)
{
	char	filter[MAX_STRING_LEN];
	int	ret;

	zbx_snprintf(filter, sizeof(filter), filter_pattern, objectid);

	ret = hk_delete_from_table(table, filter, config_max_hk_delete);

	if (ZBX_DB_OK > ret || (0 != config_max_hk_delete && ret >= config_max_hk_delete))
		*more = 1;

	return ZBX_DB_OK <= ret ? ret : 0;
}

static int	hk_multiple_cleanup(zbx_uint64_t housekeeperid, zbx_uint64_t objectid,
		const hk_cleanup_table_t *cleanup_tables, int config_max_hk_delete, int *more)
{
	const hk_cleanup_table_t	*table;
	int				i, deleted = 0, mask = 0;
	hk_entry_t			*entry;

	if (NULL == (entry = zbx_hashset_search(&hk_cache, &objectid)))
	{
		hk_entry_t	entry_local = {housekeeperid, objectid, 0};

		entry = zbx_hashset_insert(&hk_cache, &entry_local, sizeof(entry_local));
	}

	for (i = 0, table = cleanup_tables; NULL != table->name; i++, table++)
	{
		mask |= (1 << i);

		if (NULL != table->get_hk_mode && ZBX_HK_MODE_REGULAR != table->get_hk_mode()) {
			entry->progress |= (1 << i);
			continue;
		}

		if (0 == (entry->progress & (1 << i)))
		{
			int	 m = 0;

			deleted += hk_table_cleanup(table->name, table->filter, objectid, config_max_hk_delete, &m);

			if (0 == m)
				entry->progress |= (1 << i);
		}
	}

	if (mask == entry->progress)
		zbx_hashset_remove_direct(&hk_cache, entry);
	else
		*more = 1;

	return deleted;
}

static int	housekeep_events_by_triggerid(zbx_uint64_t triggerid, int config_max_hk_delete, int events_mode,
		int *more)
{
	char	query[MAX_STRING_LEN];
	int	deleted = 0;

	if (SUCCEED == zbx_dc_config_trigger_exists(triggerid))
	{
		*more = 1;
		return 0;
	}

	zbx_snprintf(query, sizeof(query), "select eventid"
			" from events"
			" where source=%d"
				" and object=%d"
				" and objectid=" ZBX_FS_UI64 " order by eventid",
			EVENT_SOURCE_TRIGGERS, EVENT_OBJECT_TRIGGER, triggerid);

	deleted = zbx_housekeep_problems_events(query, config_max_hk_delete, events_mode, more);

	zbx_snprintf(query, sizeof(query), "select eventid"
			" from events"
			" where source=%d"
				" and object=%d"
				" and objectid=" ZBX_FS_UI64 " order by eventid",
				EVENT_SOURCE_INTERNAL, EVENT_OBJECT_TRIGGER, triggerid);

	deleted += zbx_housekeep_problems_events(query, config_max_hk_delete, events_mode, more);

	return deleted;
}

/******************************************************************************
 *                                                                            *
 * Purpose: perform problem table cleanup                                     *
 *                                                                            *
 * Parameters: table                - [IN] problem table name                 *
 * Parameters: source               - [IN] event source                       *
 *             object               - [IN] event object type                  *
 *             objectid             - [IN] event object identifier            *
 *             config_max_hk_delete - [IN]                                    *
 *             more                 - [OUT] 1 if there might be more data to  *
 *                     remove, otherwise the value is not changed             *
 *                                                                            *
 * Return value: number of rows deleted                                       *
 *                                                                            *
 ******************************************************************************/
static int	hk_problem_cleanup(const char *table, int source, int object, zbx_uint64_t objectid,
		int config_max_hk_delete, int *more)
{
	char	filter[MAX_STRING_LEN];
	int	ret;

	zbx_snprintf(filter, sizeof(filter), "source=%d and object=%d and objectid=" ZBX_FS_UI64,
			source, object, objectid);

	ret = hk_delete_from_table(table, filter, config_max_hk_delete);

	if (ZBX_DB_OK > ret || (0 != config_max_hk_delete && ret >= config_max_hk_delete))
		*more = 1;

	return ZBX_DB_OK <= ret ? ret : 0;
}

void	housekeeper_init(void)
{
	zbx_hashset_create(&hk_cache, 5, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
}

void	housekeeper_deinit(void)
{
	zbx_hashset_destroy(&hk_cache);
}

int	housekeeper_process(int config_max_hk_delete)
{
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	zbx_vector_uint64_t	deleteids;
	int			deleted;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&deleteids);

	result = zbx_db_select("select housekeeperid,object,objectid"
			" from housekeeper");

	while (NULL != (row = zbx_db_fetch(result)))
	{
		int		object;
		zbx_uint64_t	housekeeperid, objectid;
		int		more = 0;

		ZBX_STR2UINT64(housekeeperid, row[0]);
		object = atoi(row[1]);
		ZBX_STR2UINT64(objectid, row[2]);

		switch (object)
		{
			case ZBX_HK_OBJECT_ITEM:
				deleted += hk_multiple_cleanup(housekeeperid, objectid, hk_item_cleanup_order,
						config_max_hk_delete, &more);
				break;
			case ZBX_HK_OBJECT_TRIGGER:
				deleted += housekeep_events_by_triggerid(objectid, config_max_hk_delete,
						hk_cfg_events_mode(), &more);
				break;
			case ZBX_HK_OBJECT_SERVICE:
				deleted += hk_problem_cleanup("problems", EVENT_SOURCE_SERVICE, EVENT_OBJECT_SERVICE,
						objectid, config_max_hk_delete, &more);
				break;
			case ZBX_HK_OBJECT_DHOST:
				deleted += hk_problem_cleanup("events", EVENT_SOURCE_DISCOVERY, EVENT_OBJECT_DHOST,
						objectid, config_max_hk_delete, &more);
				break;
			case ZBX_HK_OBJECT_DSERVICE:
				deleted += hk_problem_cleanup("events", EVENT_SOURCE_DISCOVERY, EVENT_OBJECT_DSERVICE,
						objectid, config_max_hk_delete, &more);
				break;
			default:
				THIS_SHOULD_NEVER_HAPPEN;
				break;
		}

		if (0 == more)
			zbx_vector_uint64_append(&deleteids, housekeeperid);
	}
	zbx_db_free_result(result);

	if (0 != deleteids.values_num)
	{
		zbx_vector_uint64_sort(&deleteids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_db_execute_multiple_query("delete from housekeeper where", "housekeeperid", &deleteids);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return deleted;
}
