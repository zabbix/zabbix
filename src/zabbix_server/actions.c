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

#include "actions.h"

#include "log.h"
#include "zbxserver.h"
#include "operations.h"
#include "zbxregexp.h"
#include "../../libs/zbxaudit/audit.h"

/******************************************************************************
 *                                                                            *
 * Purpose: compare events by objectid                                        *
 *                                                                            *
 * Parameters: d1 - [IN] event structure to compare to d2                     *
 *             d2 - [IN] event structure to compare to d1                     *
 *                                                                            *
 * Return value: 0 - equal                                                    *
 *               not 0 - otherwise                                            *
 *                                                                            *
 ******************************************************************************/
static int	compare_events(const void *d1, const void *d2)
{
	const DB_EVENT	*p1 = *(const DB_EVENT **)d1;
	const DB_EVENT	*p2 = *(const DB_EVENT **)d2;

	ZBX_RETURN_IF_NOT_EQUAL(p1->objectid, p2->objectid);
	ZBX_RETURN_IF_NOT_EQUAL(p1->object, p2->object);

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: save eventids that match condition                                *
 *                                                                            *
 * Parameters: esc_events - [IN] events to check                              *
 *             condition  - [IN/OUT] condition for matching, outputs          *
 *                                   event ids that match condition           *
 *             objectid   - [IN] object id, for example trigger or item id    *
 *             object     - [IN] object, for example EVENT_OBJECT_TRIGGER     *
 ******************************************************************************/
static void	add_condition_match(const zbx_vector_ptr_t *esc_events, zbx_condition_t *condition,
		zbx_uint64_t objectid, int object)
{
	int		index;
	const DB_EVENT	event_search = {.objectid = objectid, .object = object};

	if (FAIL != (index = zbx_vector_ptr_bsearch(esc_events, &event_search, compare_events)))
	{
		const DB_EVENT	*event = (DB_EVENT *)esc_events->values[index];
		int		i;

		zbx_vector_uint64_append(&condition->eventids, event->eventid);

		for (i = index - 1; 0 <= i; i--)
		{
			event = esc_events->values[i];

			if (event->objectid != objectid || event->object != object)
				break;

			zbx_vector_uint64_append(&condition->eventids, event->eventid);
		}

		for (i = index + 1; i < esc_events->values_num; i++)
		{
			event = (DB_EVENT *)esc_events->values[i];

			if (event->objectid != objectid || event->object != object)
				break;

			zbx_vector_uint64_append(&condition->eventids, event->eventid);
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: get objectids of escalation events                                *
 *                                                                            *
 * Parameters: esc_events [IN]  - events to check                             *
 *             objectids  [OUT] - event objectids to be used in condition     *
 *                                allocation                                  *
 *                                                                            *
 ******************************************************************************/
static void	get_object_ids(const zbx_vector_ptr_t *esc_events, zbx_vector_uint64_t *objectids)
{
	int	i;

	zbx_vector_uint64_reserve(objectids, esc_events->values_num);

	for (i = 0; i < esc_events->values_num; i++)
	{
		const DB_EVENT	*event = (DB_EVENT *)esc_events->values[i];

		zbx_vector_uint64_append(objectids, event->objectid);
	}

	zbx_vector_uint64_uniq(objectids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
}

/******************************************************************************
 *                                                                            *
 * Purpose: check host group condition                                        *
 *                                                                            *
 * Parameters: esc_events - [IN] events to check                              *
 *             condition  - [IN/OUT] condition for matching, outputs          *
 *                                   event ids that match condition           *
 *                                                                            *
 * Return value: SUCCEED - supported operator                                 *
 *               NOTSUPPORTED - not supported operator                        *
 *                                                                            *
 ******************************************************************************/
static int	check_host_group_condition(const zbx_vector_ptr_t *esc_events, zbx_condition_t *condition)
{
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	DB_RESULT		result;
	DB_ROW			row;
	zbx_vector_uint64_t	objectids, groupids;
	zbx_uint64_t		condition_value;

	if (CONDITION_OPERATOR_EQUAL != condition->op && CONDITION_OPERATOR_NOT_EQUAL != condition->op)
		return NOTSUPPORTED;

	ZBX_STR2UINT64(condition_value, condition->value);

	zbx_vector_uint64_create(&objectids);
	zbx_vector_uint64_create(&groupids);

	get_object_ids(esc_events, &objectids);
	zbx_dc_get_nested_hostgroupids(&condition_value, 1, &groupids);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
		"select distinct f.triggerid"
		" from hosts_groups hg,hosts h,items i,functions f"
		" where hg.hostid=h.hostid"
			" and h.hostid=i.hostid"
			" and i.itemid=f.itemid"
			" and");

	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "f.triggerid",
			objectids.values, objectids.values_num);

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " and");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "hg.groupid", groupids.values, groupids.values_num);

	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		zbx_uint64_t	objectid;

		ZBX_STR2UINT64(objectid, row[0]);
		if (CONDITION_OPERATOR_NOT_EQUAL == condition->op)
		{
			int	index;

			if (FAIL != (index = zbx_vector_uint64_search(&objectids, objectid,
					ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
			{
				zbx_vector_uint64_remove_noorder(&objectids, index);
			}
		}
		else
			add_condition_match(esc_events, condition, objectid, EVENT_OBJECT_TRIGGER);

	}
	DBfree_result(result);

	if (CONDITION_OPERATOR_NOT_EQUAL == condition->op)
	{
		int	i;

		for (i = 0; i < objectids.values_num; i++)
			add_condition_match(esc_events, condition, objectids.values[i], EVENT_OBJECT_TRIGGER);
	}

	zbx_vector_uint64_destroy(&groupids);
	zbx_vector_uint64_destroy(&objectids);
	zbx_free(sql);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: mapping between discovered triggers and their prototypes          *
 *                                                                            *
 * Parameters: sql           [IN/OUT] - allocated sql query                   *
 *             sql_alloc     [IN/OUT] - how much bytes allocated              *
 *             objectids_tmp [IN/OUT] - uses to allocate query                *
 *                                                                            *
 *                                                                            *
 ******************************************************************************/
static void	trigger_parents_sql_alloc(char **sql, size_t *sql_alloc, zbx_vector_uint64_t *objectids_tmp)
{
	size_t	sql_offset = 0;

	zbx_snprintf_alloc(sql, sql_alloc, &sql_offset,
			"select triggerid,parent_triggerid"
			" from trigger_discovery"
			" where");

	DBadd_condition_alloc(sql, sql_alloc, &sql_offset, "triggerid", objectids_tmp->values,
			objectids_tmp->values_num);
}

/******************************************************************************
 *                                                                            *
 * Purpose: copy objects to pair, for hierarchy checks                        *
 *                                                                            *
 * Parameters: objectids       [IN]  - objects                                *
 *             objectids_pair  [OUT] - objectids will be copied here          *
 *                                                                            *
 ******************************************************************************/
static void	objectids_to_pair(zbx_vector_uint64_t *objectids, zbx_vector_uint64_pair_t *objectids_pair)
{
	int	i;

	zbx_vector_uint64_pair_reserve(objectids_pair, objectids->values_num);

	for (i = 0; i < objectids->values_num; i++)
	{
		zbx_uint64_pair_t	pair = {objectids->values[i], objectids->values[i]};

		zbx_vector_uint64_pair_append(objectids_pair, pair);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: there can be multiple levels of templates, that need              *
 *          resolving in order to compare to condition                        *
 *                                                                            *
 * Parameters: object          - [IN] type of the object that generated event *
 * Parameters: esc_events      - [IN] events being checked                    *
 *             objectids       - [IN] object ids of the esc_events            *
 *                                    (contents can be changed by processing  *
 *                                    and should not be used by caller)       *
 *             objectids_pair  - [IN] pairs of (objectid, source objectid)    *
 *                                    where objectid are ids of the esc_events*
 *                                    and source objectid is object id for    *
 *                                    normal objects and prototype id for     *
 *                                    discovered objects                      *
 *                                    (contents can be changed by processing  *
 *                                    and should not be used by caller)       *
 *             condition       - [IN/OUT] condition to evaluate, matched      *
 *                                    events will be added to condition       *
 *                                    eventids vector                         *
 *             condition_value - [IN] condition value for matching            *
 *             sql_str         - [IN] custom sql query, must obtain object,   *
 *                                    template object id and value            *
 *             sql_field       - [IN] field name that is added to the sql     *
 *                                    query condition                         *
 *                                                                            *
 ******************************************************************************/
static void	check_object_hierarchy(int object, const zbx_vector_ptr_t *esc_events, zbx_vector_uint64_t *objectids,
		zbx_vector_uint64_pair_t *objectids_pair, zbx_condition_t *condition, zbx_uint64_t condition_value,
		char *sql_str, char *sql_field)
{
	int				i;
	zbx_vector_uint64_t		objectids_tmp;
	zbx_vector_uint64_pair_t	objectids_pair_tmp;
	char				*sql = NULL;
	size_t				sql_alloc = 0;

	zbx_vector_uint64_pair_create(&objectids_pair_tmp);
	zbx_vector_uint64_create(&objectids_tmp);
	zbx_vector_uint64_reserve(&objectids_tmp, objectids_pair->values_num);

	while (0 != objectids_pair->values_num)
	{
		DB_RESULT	result;
		DB_ROW		row;
		size_t		sql_offset = 0;

		/* objectids that need parents to be determined */
		for (i = 0; i < objectids_pair->values_num; i++)
			zbx_vector_uint64_append(&objectids_tmp, objectids_pair->values[i].second);

		zbx_vector_uint64_sort(&objectids_tmp, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		/* multiple hosts can share trigger from same template, don't allocate duplicate ids */
		zbx_vector_uint64_uniq(&objectids_tmp, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, sql_str);

		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, sql_field, objectids_tmp.values,
				objectids_tmp.values_num);

		zbx_vector_uint64_clear(&objectids_tmp);

		result = DBselect("%s", sql);

		while (NULL != (row = DBfetch(result)))
		{
			zbx_uint64_t	objectid, parent_objectid, value;

			ZBX_STR2UINT64(objectid, row[0]);
			ZBX_STR2UINT64(parent_objectid, row[1]);
			ZBX_STR2UINT64(value, row[2]);

			/* find all templates or trigger ids that match our condition and get original id */
			for (i = 0; i < objectids_pair->values_num; i++)
			{
				/* objectid is id that has template id, that match condition */
				/* second are those that we did select on */
				if (objectids_pair->values[i].second != objectid)
					continue;

				if (value == condition_value)
				{
					if (CONDITION_OPERATOR_EQUAL != condition->op)
					{
						int	j;

						/* remove equals from result set, leaving only not equals */
						if (FAIL != (j = zbx_vector_uint64_search(objectids,
								objectids_pair->values[i].first,
								ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
						{
							zbx_vector_uint64_remove_noorder(objectids, j);
						}
					}
					else
					{
						add_condition_match(esc_events, condition,
								objectids_pair->values[i].first, object);
					}
				}
				else
				{
					/* update template id to next level, to compare to condition in next select */

					objectids_pair->values[i].second = parent_objectid;
					zbx_vector_uint64_pair_append(&objectids_pair_tmp, objectids_pair->values[i]);
				}

				objectids_pair->values[i].second = 0;
			}
		}
		zbx_free(sql);
		DBfree_result(result);

		/* resolve in next select only those triggerids that have template id and not equal to condition */
		zbx_vector_uint64_pair_clear(objectids_pair);

		if (0 != objectids_pair_tmp.values_num)
		{
			zbx_vector_uint64_pair_append_array(objectids_pair, objectids_pair_tmp.values,
					objectids_pair_tmp.values_num);
		}

		zbx_vector_uint64_pair_clear(&objectids_pair_tmp);
	}

	/* equals are deleted so copy to result those that are left (not equals)  */
	if (CONDITION_OPERATOR_NOT_EQUAL == condition->op)
	{
		for (i = 0; i < objectids->values_num; i++)
			add_condition_match(esc_events, condition, objectids->values[i], object);
	}

	zbx_vector_uint64_pair_destroy(&objectids_pair_tmp);
	zbx_vector_uint64_destroy(&objectids_tmp);
}

/******************************************************************************
 *                                                                            *
 * Purpose: check host template condition                                     *
 *                                                                            *
 * Parameters: esc_events - [IN] events to check                              *
 *             condition  - [IN/OUT] condition for matching, outputs          *
 *                                   event ids that match condition           *
 *                                                                            *
 * Return value: SUCCEED - supported operator                                 *
 *               NOTSUPPORTED - not supported operator                        *
 *                                                                            *
 ******************************************************************************/
static int	check_host_template_condition(const zbx_vector_ptr_t *esc_events, zbx_condition_t *condition)
{
	char				*sql = NULL;
	size_t				sql_alloc = 0;
	DB_RESULT			result;
	DB_ROW				row;
	zbx_uint64_t			condition_value;
	zbx_vector_uint64_t		objectids;
	zbx_vector_uint64_pair_t	objectids_pair;

	if (CONDITION_OPERATOR_EQUAL != condition->op && CONDITION_OPERATOR_NOT_EQUAL != condition->op)
		return NOTSUPPORTED;

	zbx_vector_uint64_create(&objectids);
	zbx_vector_uint64_pair_create(&objectids_pair);

	get_object_ids(esc_events, &objectids);
	objectids_to_pair(&objectids, &objectids_pair);

	ZBX_STR2UINT64(condition_value, condition->value);

	trigger_parents_sql_alloc(&sql, &sql_alloc, &objectids);

	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		zbx_uint64_pair_t	pair;
		int			i;

		ZBX_STR2UINT64(pair.first, row[0]);

		if (FAIL != (i = zbx_vector_uint64_pair_search(&objectids_pair, pair, ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
			ZBX_STR2UINT64(objectids_pair.values[i].second, row[1]);
	}
	DBfree_result(result);

	check_object_hierarchy(EVENT_OBJECT_TRIGGER, esc_events, &objectids, &objectids_pair, condition, condition_value,
			"select distinct t.triggerid,t.templateid,i.hostid"
				" from items i,functions f,triggers t"
				" where i.itemid=f.itemid"
					" and f.triggerid=t.templateid"
					" and",
			"t.triggerid");

	zbx_vector_uint64_destroy(&objectids);
	zbx_vector_uint64_pair_destroy(&objectids_pair);
	zbx_free(sql);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check host condition                                              *
 *                                                                            *
 * Parameters: esc_events - [IN] events to check                              *
 *             condition  - [IN/OUT] condition for matching, outputs          *
 *                                   event ids that match condition           *
 *                                                                            *
 * Return value: SUCCEED - supported operator                                 *
 *               NOTSUPPORTED - not supported operator                        *
 *                                                                            *
 ******************************************************************************/
static int	check_host_condition(const zbx_vector_ptr_t *esc_events, zbx_condition_t *condition)
{
	char			*sql = NULL, *operation;
	size_t			sql_alloc = 0, sql_offset = 0;
	DB_RESULT		result;
	DB_ROW			row;
	zbx_vector_uint64_t	objectids;
	zbx_uint64_t		condition_value;

	if (CONDITION_OPERATOR_EQUAL == condition->op)
		operation = " and";
	else if (CONDITION_OPERATOR_NOT_EQUAL == condition->op)
		operation = " and not";
	else
		return NOTSUPPORTED;

	ZBX_STR2UINT64(condition_value, condition->value);

	zbx_vector_uint64_create(&objectids);

	get_object_ids(esc_events, &objectids);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select distinct f.triggerid"
			" from items i,functions f"
			" where i.itemid=f.itemid"
				"%s i.hostid=" ZBX_FS_UI64
				" and",
			operation,
			condition_value);

	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "f.triggerid", objectids.values, objectids.values_num);

	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		zbx_uint64_t	objectid;

		ZBX_STR2UINT64(objectid, row[0]);
		add_condition_match(esc_events, condition, objectid, EVENT_OBJECT_TRIGGER);
	}
	DBfree_result(result);

	zbx_vector_uint64_destroy(&objectids);
	zbx_free(sql);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check trigger id condition                                        *
 *                                                                            *
 * Parameters: esc_events - [IN] events to check                              *
 *             condition  - [IN/OUT] condition for matching, outputs          *
 *                                   event ids that match condition           *
 *                                                                            *
 * Return value: SUCCEED - supported operator                                 *
 *               NOTSUPPORTED - not supported operator                        *
 *                                                                            *
 ******************************************************************************/
static int	check_trigger_id_condition(const zbx_vector_ptr_t *esc_events, zbx_condition_t *condition)
{
	zbx_uint64_t			condition_value;
	zbx_vector_uint64_t		objectids;
	zbx_vector_uint64_pair_t	objectids_pair;
	int				i;

	if (CONDITION_OPERATOR_EQUAL != condition->op && CONDITION_OPERATOR_NOT_EQUAL != condition->op)
		return NOTSUPPORTED;

	ZBX_STR2UINT64(condition_value, condition->value);

	zbx_vector_uint64_create(&objectids);
	zbx_vector_uint64_pair_create(&objectids_pair);

	for (i = 0; i < esc_events->values_num; i++)
	{
		const DB_EVENT	*event = (DB_EVENT *)esc_events->values[i];

		if (event->objectid == condition_value)
		{
			if (CONDITION_OPERATOR_EQUAL == condition->op)
				zbx_vector_uint64_append(&condition->eventids, event->eventid);
		}
		else
			zbx_vector_uint64_append(&objectids, event->objectid);
	}

	if (0 != objectids.values_num)
	{
		zbx_vector_uint64_uniq(&objectids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		objectids_to_pair(&objectids, &objectids_pair);

		check_object_hierarchy(EVENT_OBJECT_TRIGGER, esc_events, &objectids, &objectids_pair, condition, condition_value,
				"select triggerid,templateid,templateid"
					" from triggers"
					" where templateid is not null and",
					"triggerid");
	}

	zbx_vector_uint64_destroy(&objectids);
	zbx_vector_uint64_pair_destroy(&objectids_pair);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check trigger name condition                                      *
 *                                                                            *
 * Parameters: esc_events - [IN] events to check                              *
 *             condition  - [IN/OUT] condition for matching, outputs          *
 *                                   event ids that match condition           *
 *                                                                            *
 * Return value: SUCCEED - supported operator                                 *
 *               NOTSUPPORTED - not supported operator                        *
 *                                                                            *
 ******************************************************************************/
static int	check_trigger_name_condition(const zbx_vector_ptr_t *esc_events, zbx_condition_t *condition)
{
	int	i;

	if (CONDITION_OPERATOR_LIKE != condition->op && CONDITION_OPERATOR_NOT_LIKE != condition->op)
		return NOTSUPPORTED;

	for (i = 0; i < esc_events->values_num; i++)
	{
		const DB_EVENT	*event = (DB_EVENT *)esc_events->values[i];

		switch (condition->op)
		{
			case CONDITION_OPERATOR_LIKE:
				if (NULL != strstr(event->name, condition->value))
					zbx_vector_uint64_append(&condition->eventids, event->eventid);
				break;
			case CONDITION_OPERATOR_NOT_LIKE:
				if (NULL == strstr(event->name, condition->value))
					zbx_vector_uint64_append(&condition->eventids, event->eventid);
				break;
		}
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check trigger severity condition                                  *
 *                                                                            *
 * Parameters: esc_events - [IN] events to check                              *
 *             condition  - [IN/OUT] condition for matching, outputs          *
 *                                   event ids that match condition           *
 *                                                                            *
 * Return value: SUCCEED - supported operator                                 *
 *               NOTSUPPORTED - not supported operator                        *
 *                                                                            *
 ******************************************************************************/
static int	check_trigger_severity_condition(const zbx_vector_ptr_t *esc_events, zbx_condition_t *condition)
{
	unsigned char	condition_value;
	int		i;

	condition_value = (unsigned char)atoi(condition->value);

	for (i = 0; i < esc_events->values_num; i++)
	{
		const DB_EVENT	*event = (DB_EVENT *)esc_events->values[i];

		switch (condition->op)
		{
			case CONDITION_OPERATOR_EQUAL:
				if (event->trigger.priority == condition_value)
					zbx_vector_uint64_append(&condition->eventids, event->eventid);
				break;
			case CONDITION_OPERATOR_NOT_EQUAL:
				if (event->trigger.priority != condition_value)
					zbx_vector_uint64_append(&condition->eventids, event->eventid);
				break;
			case CONDITION_OPERATOR_MORE_EQUAL:
				if (event->trigger.priority >= condition_value)
					zbx_vector_uint64_append(&condition->eventids, event->eventid);
				break;
			case CONDITION_OPERATOR_LESS_EQUAL:
				if (event->trigger.priority <= condition_value)
					zbx_vector_uint64_append(&condition->eventids, event->eventid);
				break;
			default:
				return NOTSUPPORTED;
		}
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check time period condition                                       *
 *                                                                            *
 * Parameters: esc_events - [IN] events to check                              *
 *             condition  - [IN/OUT] condition for matching, outputs          *
 *                                   event ids that match condition           *
 *                                                                            *
 * Return value: SUCCEED - supported operator                                 *
 *               NOTSUPPORTED - not supported operator                        *
 *                                                                            *
 ******************************************************************************/
static int	check_time_period_condition(const zbx_vector_ptr_t *esc_events, zbx_condition_t *condition)
{
	char	*period;
	int	i;

	if (CONDITION_OPERATOR_IN != condition->op && CONDITION_OPERATOR_NOT_IN != condition->op)
		return NOTSUPPORTED;

	period = zbx_strdup(NULL, condition->value);
	substitute_simple_macros(NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, &period,
			MACRO_TYPE_COMMON, NULL, 0);

	for (i = 0; i < esc_events->values_num; i++)
	{
		const DB_EVENT	*event = (DB_EVENT *)esc_events->values[i];
		int		res;

		if (SUCCEED == zbx_check_time_period(period, (time_t)event->clock, NULL, &res))
		{
			switch (condition->op)
			{
				case CONDITION_OPERATOR_IN:
					if (SUCCEED == res)
						zbx_vector_uint64_append(&condition->eventids, event->eventid);
					break;
				case CONDITION_OPERATOR_NOT_IN:
					if (FAIL == res)
						zbx_vector_uint64_append(&condition->eventids, event->eventid);
					break;
			}
		}
		else
		{
			zabbix_log(LOG_LEVEL_WARNING, "Invalid time period \"%s\" for condition id [" ZBX_FS_UI64 "]",
					period, condition->conditionid);
		}
	}

	zbx_free(period);

	return SUCCEED;
}

static int	check_suppressed_condition(const zbx_vector_ptr_t *esc_events, zbx_condition_t *condition)
{
	int	i;

	for (i = 0; i < esc_events->values_num; i++)
	{
		const DB_EVENT	*event = (DB_EVENT *)esc_events->values[i];

		switch (condition->op)
		{
			case CONDITION_OPERATOR_YES:
				if (ZBX_PROBLEM_SUPPRESSED_TRUE == event->suppressed)
					zbx_vector_uint64_append(&condition->eventids, event->eventid);
				break;
			case CONDITION_OPERATOR_NO:
				if (ZBX_PROBLEM_SUPPRESSED_FALSE == event->suppressed)
					zbx_vector_uint64_append(&condition->eventids, event->eventid);
				break;
			default:
				return NOTSUPPORTED;
		}
	}

	return SUCCEED;
}

static int	check_acknowledged_condition(const zbx_vector_ptr_t *esc_events, zbx_condition_t *condition)
{
	int			i;
	zbx_vector_uint64_t	eventids;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	DB_RESULT		result;
	DB_ROW			row;
	int			ret = SUCCEED;

	zbx_vector_uint64_create(&eventids);
	zbx_vector_uint64_reserve(&eventids, esc_events->values_num);

	for (i = 0; i < esc_events->values_num; i++)
	{
		const DB_EVENT	*event = (DB_EVENT *)esc_events->values[i];

		zbx_vector_uint64_append(&eventids, event->eventid);
	}

	zbx_vector_uint64_sort(&eventids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select eventid"
			" from events"
			" where acknowledged=%d"
				" and",
			atoi(condition->value));

	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "eventid", eventids.values, eventids.values_num);

	result = DBselect("%s", sql);
	while (NULL != (row = DBfetch(result)))
	{
		zbx_uint64_t	eventid;

		ZBX_STR2UINT64(eventid, row[0]);
		switch (condition->op)
		{
			case CONDITION_OPERATOR_EQUAL:
				zbx_vector_uint64_append(&condition->eventids, eventid);
				break;
			default:
				ret = NOTSUPPORTED;
		}

	}
	DBfree_result(result);
	zbx_free(sql);

	zbx_vector_uint64_destroy(&eventids);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check condition event tag                                         *
 *                                                                            *
 * Parameters: esc_events - [IN] events to check                              *
 *             condition  - [IN/OUT] condition for matching, outputs          *
 *                                   event ids that match condition           *
 *                                                                            *
 ******************************************************************************/
static void	check_condition_event_tag(const zbx_vector_ptr_t *esc_events, zbx_condition_t *condition)
{
	int	i, ret, ret_continue;

	if (CONDITION_OPERATOR_NOT_EQUAL == condition->op || CONDITION_OPERATOR_NOT_LIKE == condition->op)
		ret_continue = SUCCEED;
	else
		ret_continue = FAIL;

	for (i = 0; i < esc_events->values_num; i++)
	{
		const DB_EVENT	*event = (DB_EVENT *)esc_events->values[i];
		int		j;

		ret = ret_continue;

		for (j = 0; j < event->tags.values_num && ret == ret_continue; j++)
		{
			const zbx_tag_t	*tag = (zbx_tag_t *)event->tags.values[j];

			ret = zbx_strmatch_condition(tag->tag, condition->value, condition->op);
		}

		if (SUCCEED == ret)
			zbx_vector_uint64_append(&condition->eventids, event->eventid);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: check condition event tag value                                   *
 *                                                                            *
 * Parameters: esc_events - [IN] events to check                              *
 *             condition  - [IN/OUT] condition for matching, outputs          *
 *                                   event ids that match condition           *
 *                                                                            *
 ******************************************************************************/
static void	check_condition_event_tag_value(const zbx_vector_ptr_t *esc_events, zbx_condition_t *condition)
{
	int	i, ret, ret_continue;

	if (CONDITION_OPERATOR_NOT_EQUAL == condition->op || CONDITION_OPERATOR_NOT_LIKE == condition->op)
		ret_continue = SUCCEED;
	else
		ret_continue = FAIL;

	for (i = 0; i < esc_events->values_num; i++)
	{
		const DB_EVENT	*event = (DB_EVENT *)esc_events->values[i];
		int		j;

		ret = ret_continue;

		for (j = 0; j < event->tags.values_num && ret == ret_continue; j++)
		{
			zbx_tag_t	*tag = (zbx_tag_t *)event->tags.values[j];

			if (0 == strcmp(condition->value2, tag->tag))
				ret = zbx_strmatch_condition(tag->value, condition->value, condition->op);
		}

		if (SUCCEED == ret)
			zbx_vector_uint64_append(&condition->eventids, event->eventid);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if event matches single condition                           *
 *                                                                            *
 * Parameters: event - trigger event to check                                 *
 *                                  (event->source == EVENT_SOURCE_TRIGGERS)  *
 *             condition - condition for matching                             *
 *                                                                            *
 * Return value: SUCCEED - matches, FAIL - otherwise                          *
 *                                                                            *
 ******************************************************************************/
static void	check_trigger_condition(const zbx_vector_ptr_t *esc_events, zbx_condition_t *condition)
{
	int	ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	switch (condition->conditiontype)
	{
		case CONDITION_TYPE_HOST_GROUP:
			ret = check_host_group_condition(esc_events, condition);
			break;
		case CONDITION_TYPE_HOST_TEMPLATE:
			ret = check_host_template_condition(esc_events, condition);
			break;
		case CONDITION_TYPE_HOST:
			ret = check_host_condition(esc_events, condition);
			break;
		case CONDITION_TYPE_TRIGGER:
			ret = check_trigger_id_condition(esc_events, condition);
			break;
		case CONDITION_TYPE_TRIGGER_NAME:
			ret = check_trigger_name_condition(esc_events, condition);
			break;
		case CONDITION_TYPE_TRIGGER_SEVERITY:
			ret = check_trigger_severity_condition(esc_events, condition);
			break;
		case CONDITION_TYPE_TIME_PERIOD:
			ret = check_time_period_condition(esc_events, condition);
			break;
		case CONDITION_TYPE_SUPPRESSED:
			ret = check_suppressed_condition(esc_events, condition);
			break;
		case CONDITION_TYPE_EVENT_ACKNOWLEDGED:
			ret = check_acknowledged_condition(esc_events, condition);
			break;
		case CONDITION_TYPE_EVENT_TAG:
			check_condition_event_tag(esc_events, condition);
			ret = SUCCEED;
			break;
		case CONDITION_TYPE_EVENT_TAG_VALUE:
			check_condition_event_tag_value(esc_events,condition);
			ret = SUCCEED;
			break;
		default:
			zabbix_log(LOG_LEVEL_ERR, "unsupported condition type [%d] for condition id [" ZBX_FS_UI64 "]",
					(int)condition->conditiontype, condition->conditionid);
			ret = FAIL;
	}

	if (NOTSUPPORTED == ret)
	{
		zabbix_log(LOG_LEVEL_ERR, "unsupported operator [%d] for condition id [" ZBX_FS_UI64 "]",
				(int)condition->op, condition->conditionid);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));
}

/******************************************************************************
 *                                                                            *
 * Purpose: get objectids for dhost                                           *
 *                                                                            *
 * Parameters: esc_events - [IN]  events to check                             *
 *             objectids  - [OUT] event objectids to be used in condition     *
 *                                allocation 2 vectors where first one is     *
 *                                dhost ids, second is dservice               *
*                                                                             *
 ******************************************************************************/
static void	get_object_ids_discovery(const zbx_vector_ptr_t *esc_events, zbx_vector_uint64_t *objectids)
{
	int	i;

	for (i = 0; i < esc_events->values_num; i++)
	{
		const DB_EVENT	*event = (DB_EVENT *)esc_events->values[i];

		if (event->object == EVENT_OBJECT_DHOST)
			zbx_vector_uint64_append(&objectids[0], event->objectid);
		else
			zbx_vector_uint64_append(&objectids[1], event->objectid);
	}

	zbx_vector_uint64_uniq(&objectids[0], ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_uniq(&objectids[1], ZBX_DEFAULT_UINT64_COMPARE_FUNC);
}
/******************************************************************************
 *                                                                            *
 * Purpose: check discovery rule condition                                    *
 *                                                                            *
 * Parameters: esc_events - [IN] events to check                              *
 *             condition  - [IN/OUT] condition for matching, outputs          *
 *                                   event ids that match condition           *
 *                                                                            *
 * Return value: SUCCEED - supported operator                                 *
 *               NOTSUPPORTED - not supported operator                        *
 *                                                                            *
 ******************************************************************************/
static int	check_drule_condition(const zbx_vector_ptr_t *esc_events, zbx_condition_t *condition)
{
	char			*sql = NULL, *operation_and, *operation_where;
	size_t			sql_alloc = 0, i;
	DB_RESULT		result;
	DB_ROW			row;
	int			objects[2] = {EVENT_OBJECT_DHOST, EVENT_OBJECT_DSERVICE};
	zbx_vector_uint64_t	objectids[2];
	zbx_uint64_t		condition_value;

	if (CONDITION_OPERATOR_EQUAL == condition->op)
	{
		operation_and = " and";
		operation_where = " where";
	}
	else if (CONDITION_OPERATOR_NOT_EQUAL == condition->op)
	{
		operation_and = " and not";
		operation_where = " where not";
	}
	else
		return NOTSUPPORTED;

	ZBX_STR2UINT64(condition_value, condition->value);

	zbx_vector_uint64_create(&objectids[0]);
	zbx_vector_uint64_create(&objectids[1]);

	get_object_ids_discovery(esc_events, objectids);

	for (i = 0; i < (int)ARRSIZE(objects); i++)
	{
		size_t	sql_offset = 0;

		if (0 == objectids[i].values_num)
			continue;

		if (EVENT_OBJECT_DHOST == objects[i])
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
					"select dhostid"
					" from dhosts"
					"%s druleid=" ZBX_FS_UI64
					" and",
					operation_where,
					condition_value);

			DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "dhostid",
					objectids[i].values, objectids[i].values_num);
		}
		else	/* EVENT_OBJECT_DSERVICE */
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
					"select s.dserviceid"
					" from dhosts h,dservices s"
					" where h.dhostid=s.dhostid"
						"%s h.druleid=" ZBX_FS_UI64
						" and",
					operation_and,
					condition_value);

			DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "s.dserviceid",
					objectids[i].values, objectids[i].values_num);
		}

		result = DBselect("%s", sql);

		while (NULL != (row = DBfetch(result)))
		{
			zbx_uint64_t	objectid;

			ZBX_STR2UINT64(objectid, row[0]);
			add_condition_match(esc_events, condition, objectid, objects[i]);
		}
		DBfree_result(result);
	}

	zbx_vector_uint64_destroy(&objectids[0]);
	zbx_vector_uint64_destroy(&objectids[1]);
	zbx_free(sql);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check discovery check condition                                   *
 *                                                                            *
 * Parameters: esc_events - [IN] events to check                              *
 *             condition  - [IN/OUT] condition for matching, outputs          *
 *                                   event ids that match condition           *
 *                                                                            *
 * Return value: SUCCEED - supported operator                                 *
 *               NOTSUPPORTED - not supported operator                        *
 *                                                                            *
 ******************************************************************************/
static int	check_dcheck_condition(const zbx_vector_ptr_t *esc_events, zbx_condition_t *condition)
{
	char			*sql = NULL, *operation_where;
	size_t			sql_alloc = 0, sql_offset = 0;
	DB_RESULT		result;
	DB_ROW			row;
	int			object = EVENT_OBJECT_DSERVICE, i;
	zbx_vector_uint64_t	objectids;
	zbx_uint64_t		condition_value;

	if (CONDITION_OPERATOR_EQUAL == condition->op)
		operation_where = " where";
	else if (CONDITION_OPERATOR_NOT_EQUAL == condition->op)
		operation_where = " where not";
	else
		return NOTSUPPORTED;

	ZBX_STR2UINT64(condition_value, condition->value);

	zbx_vector_uint64_create(&objectids);

	for (i = 0; i < esc_events->values_num; i++)
	{
		const DB_EVENT	*event = (DB_EVENT *)esc_events->values[i];

		if (object == event->object)
			zbx_vector_uint64_append(&objectids, event->objectid);
	}

	if (0 != objectids.values_num)
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"select dserviceid"
				" from dservices"
				"%s dcheckid=" ZBX_FS_UI64
					" and",
				operation_where,
				condition_value);

		zbx_vector_uint64_uniq(&objectids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "dserviceid", objectids.values,
				objectids.values_num);

		result = DBselect("%s", sql);

		while (NULL != (row = DBfetch(result)))
		{
			zbx_uint64_t	objectid;

			ZBX_STR2UINT64(objectid, row[0]);
			add_condition_match(esc_events, condition, objectid, object);
		}
		DBfree_result(result);
	}

	zbx_vector_uint64_destroy(&objectids);
	zbx_free(sql);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check discovery object condition                                  *
 *                                                                            *
 * Parameters: esc_events - [IN] events to check                              *
 *             condition  - [IN/OUT] condition for matching, outputs          *
 *                                   event ids that match condition           *
 *                                                                            *
 * Return value: SUCCEED - supported operator                                 *
 *               NOTSUPPORTED - not supported operator                        *
 *                                                                            *
 ******************************************************************************/
static int	check_dobject_condition(const zbx_vector_ptr_t *esc_events, zbx_condition_t *condition)
{
	int	i, condition_value_i = atoi(condition->value);

	if (CONDITION_OPERATOR_EQUAL != condition->op)
		return NOTSUPPORTED;

	for (i = 0; i < esc_events->values_num; i++)
	{
		const DB_EVENT	*event = (DB_EVENT *)esc_events->values[i];

		if (event->object == condition_value_i)
			zbx_vector_uint64_append(&condition->eventids, event->eventid);
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check proxy condition for discovery event                         *
 *                                                                            *
 * Parameters: esc_events - [IN] events to check                              *
 *             condition  - [IN/OUT] condition for matching, outputs          *
 *                                   event ids that match condition           *
 *                                                                            *
 * Return value: SUCCEED - supported operator                                 *
 *               NOTSUPPORTED - not supported operator                        *
 *                                                                            *
 ******************************************************************************/
static int	check_proxy_condition(const zbx_vector_ptr_t *esc_events, zbx_condition_t *condition)
{
	char			*sql = NULL, *operation_and;
	size_t			sql_alloc = 0, i;
	DB_RESULT		result;
	DB_ROW			row;
	int			objects[2] = {EVENT_OBJECT_DHOST, EVENT_OBJECT_DSERVICE};
	zbx_vector_uint64_t	objectids[2];
	zbx_uint64_t		condition_value;

	if (CONDITION_OPERATOR_EQUAL == condition->op)
		operation_and = " and";
	else if (CONDITION_OPERATOR_NOT_EQUAL == condition->op)
		operation_and = " and not";
	else
		return NOTSUPPORTED;

	ZBX_STR2UINT64(condition_value, condition->value);

	zbx_vector_uint64_create(&objectids[0]);
	zbx_vector_uint64_create(&objectids[1]);

	get_object_ids_discovery(esc_events, objectids);

	for (i = 0; i < (int)ARRSIZE(objects); i++)
	{
		size_t	sql_offset = 0;

		if (0 == objectids[i].values_num)
			continue;

		if (EVENT_OBJECT_DHOST == objects[i])
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
					"select h.dhostid"
					" from drules r,dhosts h"
					" where r.druleid=h.druleid"
						"%s r.proxy_hostid=" ZBX_FS_UI64
						" and",
					operation_and,
					condition_value);

			DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "h.dhostid", objectids[i].values,
					objectids[i].values_num);
		}
		else	/* EVENT_OBJECT_DSERVICE */
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
					"select s.dserviceid"
					" from drules r,dhosts h,dservices s"
					" where r.druleid=h.druleid"
						" and h.dhostid=s.dhostid"
						"%s r.proxy_hostid=" ZBX_FS_UI64
						" and",
					operation_and,
					condition_value);

			DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "s.dserviceid",
					objectids[i].values, objectids[i].values_num);
		}

		result = DBselect("%s", sql);

		while (NULL != (row = DBfetch(result)))
		{
			zbx_uint64_t	objectid;

			ZBX_STR2UINT64(objectid, row[0]);
			add_condition_match(esc_events, condition, objectid, objects[i]);
		}
		DBfree_result(result);
	}

	zbx_vector_uint64_destroy(&objectids[0]);
	zbx_vector_uint64_destroy(&objectids[1]);
	zbx_free(sql);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check discovery value condition                                   *
 *                                                                            *
 * Parameters: esc_events - [IN] events to check                              *
 *             condition  - [IN/OUT] condition for matching, outputs          *
 *                                   event ids that match condition           *
 *                                                                            *
 * Return value: SUCCEED - supported operator                                 *
 *               NOTSUPPORTED - not supported operator                        *
 *                                                                            *
 ******************************************************************************/
static int	check_dvalue_condition(const zbx_vector_ptr_t *esc_events, zbx_condition_t *condition)
{
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	DB_RESULT		result;
	DB_ROW			row;
	int			object = EVENT_OBJECT_DSERVICE;
	zbx_vector_uint64_t	objectids;
	int			i;

	switch (condition->op)
	{
		case CONDITION_OPERATOR_EQUAL:
		case CONDITION_OPERATOR_NOT_EQUAL:
		case CONDITION_OPERATOR_MORE_EQUAL:
		case CONDITION_OPERATOR_LESS_EQUAL:
		case CONDITION_OPERATOR_LIKE:
		case CONDITION_OPERATOR_NOT_LIKE:
			break;
		default:
			return NOTSUPPORTED;
	}

	zbx_vector_uint64_create(&objectids);

	for (i = 0; i < esc_events->values_num; i++)
	{
		const DB_EVENT	*event = (DB_EVENT *)esc_events->values[i];

		if (object == event->object)
			zbx_vector_uint64_append(&objectids, event->objectid);
	}

	if (0 != objectids.values_num)
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
					"select dserviceid,value"
					" from dservices"
					" where");

		zbx_vector_uint64_uniq(&objectids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "dserviceid", objectids.values,
				objectids.values_num);

		result = DBselect("%s", sql);

		while (NULL != (row = DBfetch(result)))
		{
			zbx_uint64_t	objectid;

			ZBX_STR2UINT64(objectid, row[0]);

			switch (condition->op)
			{
				case CONDITION_OPERATOR_EQUAL:
					if (0 == strcmp(condition->value, row[1]))
						add_condition_match(esc_events, condition, objectid, object);
					break;
				case CONDITION_OPERATOR_NOT_EQUAL:
					if (0 != strcmp(condition->value, row[1]))
						add_condition_match(esc_events, condition, objectid, object);
					break;
				case CONDITION_OPERATOR_MORE_EQUAL:
					if (0 <= strcmp(row[1], condition->value))
						add_condition_match(esc_events, condition, objectid, object);
					break;
				case CONDITION_OPERATOR_LESS_EQUAL:
					if (0 >= strcmp(row[1], condition->value))
						add_condition_match(esc_events, condition, objectid, object);
					break;
				case CONDITION_OPERATOR_LIKE:
					if (NULL != strstr(row[1], condition->value))
						add_condition_match(esc_events, condition, objectid, object);
					break;
				case CONDITION_OPERATOR_NOT_LIKE:
					if (NULL == strstr(row[1], condition->value))
						add_condition_match(esc_events, condition, objectid, object);
					break;
			}
		}
		DBfree_result(result);
	}

	zbx_vector_uint64_destroy(&objectids);
	zbx_free(sql);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check host ip condition for discovery event                       *
 *                                                                            *
 * Parameters: esc_events - [IN] events to check                              *
 *             condition  - [IN/OUT] condition for matching, outputs          *
 *                                   event ids that match condition           *
 *                                                                            *
 * Return value: SUCCEED - supported operator                                 *
 *               NOTSUPPORTED - not supported operator                        *
 *                                                                            *
 ******************************************************************************/
static int	check_dhost_ip_condition(const zbx_vector_ptr_t *esc_events, zbx_condition_t *condition)
{
	char			*sql = NULL;
	size_t			sql_alloc = 0, i;
	DB_RESULT		result;
	DB_ROW			row;
	int			objects[2] = {EVENT_OBJECT_DHOST, EVENT_OBJECT_DSERVICE};
	zbx_vector_uint64_t	objectids[2];
	zbx_uint64_t		condition_value;

	if (CONDITION_OPERATOR_EQUAL != condition->op && CONDITION_OPERATOR_NOT_EQUAL != condition->op)
		return NOTSUPPORTED;

	ZBX_STR2UINT64(condition_value, condition->value);

	zbx_vector_uint64_create(&objectids[0]);
	zbx_vector_uint64_create(&objectids[1]);

	get_object_ids_discovery(esc_events, objectids);

	for (i = 0; i < (int)ARRSIZE(objects); i++)
	{
		size_t	sql_offset = 0;

		if (0 == objectids[i].values_num)
			continue;

		if (EVENT_OBJECT_DHOST == objects[i])
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
					"select distinct dhostid,ip"
					" from dservices"
					" where");

			DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "dhostid", objectids[i].values,
					objectids[i].values_num);
		}
		else	/* EVENT_OBJECT_DSERVICE */
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
					"select distinct dserviceid,ip"
					" from dservices"
					" where");

			DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "dserviceid",
					objectids[i].values, objectids[i].values_num);
		}

		result = DBselect("%s", sql);

		while (NULL != (row = DBfetch(result)))
		{
			zbx_uint64_t	objectid;

			ZBX_STR2UINT64(objectid, row[0]);
			switch (condition->op)
			{
				case CONDITION_OPERATOR_EQUAL:
					if (SUCCEED == ip_in_list(condition->value, row[1]))
						add_condition_match(esc_events, condition, objectid, objects[i]);
					break;
				case CONDITION_OPERATOR_NOT_EQUAL:
					if (SUCCEED != ip_in_list(condition->value, row[1]))
						add_condition_match(esc_events, condition, objectid, objects[i]);
					break;
			}
		}
		DBfree_result(result);
	}

	zbx_vector_uint64_destroy(&objectids[0]);
	zbx_vector_uint64_destroy(&objectids[1]);
	zbx_free(sql);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check service type condition for discovery event                  *
 *                                                                            *
 * Parameters: esc_events - [IN] events to check                              *
 *             condition  - [IN/OUT] condition for matching, outputs          *
 *                                   event ids that match condition           *
 *                                                                            *
 * Return value: SUCCEED - supported operator                                 *
 *               NOTSUPPORTED - not supported operator                        *
 *                                                                            *
 ******************************************************************************/
static int	check_dservice_type_condition(const zbx_vector_ptr_t *esc_events, zbx_condition_t *condition)
{
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	DB_RESULT		result;
	DB_ROW			row;
	int			object = EVENT_OBJECT_DSERVICE;
	zbx_vector_uint64_t	objectids;
	int			i, condition_value_i;

	if (CONDITION_OPERATOR_EQUAL != condition->op && CONDITION_OPERATOR_NOT_EQUAL != condition->op)
		return NOTSUPPORTED;

	condition_value_i = atoi(condition->value);

	zbx_vector_uint64_create(&objectids);

	for (i = 0; i < esc_events->values_num; i++)
	{
		const DB_EVENT	*event = (DB_EVENT *)esc_events->values[i];

		if (object == event->object)
			zbx_vector_uint64_append(&objectids, event->objectid);
	}

	if (0 != objectids.values_num)
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"select ds.dserviceid,dc.type"
				" from dservices ds,dchecks dc"
				" where ds.dcheckid=dc.dcheckid"
					" and");

		zbx_vector_uint64_uniq(&objectids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "ds.dserviceid", objectids.values,
				objectids.values_num);

		result = DBselect("%s", sql);

		while (NULL != (row = DBfetch(result)))
		{
			zbx_uint64_t	objectid;
			int		tmp_int;

			ZBX_STR2UINT64(objectid, row[0]);
			tmp_int = atoi(row[1]);

			switch (condition->op)
			{
				case CONDITION_OPERATOR_EQUAL:
					if (condition_value_i == tmp_int)
						add_condition_match(esc_events, condition, objectid, object);
					break;
				case CONDITION_OPERATOR_NOT_EQUAL:
					if (condition_value_i != tmp_int)
						add_condition_match(esc_events, condition, objectid, object);
					break;
			}
		}
		DBfree_result(result);
	}

	zbx_vector_uint64_destroy(&objectids);
	zbx_free(sql);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check discovery status condition                                  *
 *                                                                            *
 * Parameters: esc_events - [IN] events to check                              *
 *             condition  - [IN/OUT] condition for matching, outputs          *
 *                                   event ids that match condition           *
 *                                                                            *
 * Return value: SUCCEED - supported operator                                 *
 *               NOTSUPPORTED - not supported operator                        *
 *                                                                            *
 ******************************************************************************/
static int	check_dstatus_condition(const zbx_vector_ptr_t *esc_events, zbx_condition_t *condition)
{
	int	i, condition_value_i = atoi(condition->value);

	for (i = 0; i < esc_events->values_num; i++)
	{
		const DB_EVENT	*event = (DB_EVENT *)esc_events->values[i];

		switch (condition->op)
		{
			case CONDITION_OPERATOR_EQUAL:
				if (condition_value_i == event->value)
					zbx_vector_uint64_append(&condition->eventids, event->eventid);
				break;
			case CONDITION_OPERATOR_NOT_EQUAL:
				if (condition_value_i != event->value)
					zbx_vector_uint64_append(&condition->eventids, event->eventid);
				break;
			default:
				return NOTSUPPORTED;
		}
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check uptime condition for discovery                              *
 *                                                                            *
 * Parameters: esc_events - [IN] events to check                              *
 *             condition  - [IN/OUT] condition for matching, outputs          *
 *                                   event ids that match condition           *
 *                                                                            *
 * Return value: SUCCEED - supported operator                                 *
 *               NOTSUPPORTED - not supported operator                        *
 *                                                                            *
 ******************************************************************************/
static int	check_duptime_condition(const zbx_vector_ptr_t *esc_events, zbx_condition_t *condition)
{
	char			*sql = NULL;
	size_t			sql_alloc = 0, i;
	DB_RESULT		result;
	DB_ROW			row;
	int			objects[2] = {EVENT_OBJECT_DHOST, EVENT_OBJECT_DSERVICE};
	zbx_vector_uint64_t	objectids[2];
	int			condition_value_i;

	if (CONDITION_OPERATOR_LESS_EQUAL != condition->op && CONDITION_OPERATOR_MORE_EQUAL != condition->op)
		return NOTSUPPORTED;

	condition_value_i = atoi(condition->value);

	zbx_vector_uint64_create(&objectids[0]);
	zbx_vector_uint64_create(&objectids[1]);

	get_object_ids_discovery(esc_events, objectids);

	for (i = 0; i < (int)ARRSIZE(objects); i++)
	{
		size_t	sql_offset = 0;

		if (0 == objectids[i].values_num)
			continue;

		if (EVENT_OBJECT_DHOST == objects[i])
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
					"select dhostid,status,lastup,lastdown"
					" from dhosts"
					" where");

			DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "dhostid",
					objectids[i].values, objectids[i].values_num);
		}
		else	/* EVENT_OBJECT_DSERVICE */
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
					"select dserviceid,status,lastup,lastdown"
					" from dservices"
					" where");

			DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "dserviceid",
					objectids[i].values, objectids[i].values_num);
		}

		result = DBselect("%s", sql);

		while (NULL != (row = DBfetch(result)))
		{
			zbx_uint64_t	objectid;
			int		now, tmp_int;

			ZBX_STR2UINT64(objectid, row[0]);

			now = time(NULL);
			tmp_int = DOBJECT_STATUS_UP == atoi(row[1]) ? atoi(row[2]) : atoi(row[3]);

			switch (condition->op)
			{
				case CONDITION_OPERATOR_LESS_EQUAL:
					if (0 != tmp_int && (now - tmp_int) <= condition_value_i)
						add_condition_match(esc_events, condition, objectid, objects[i]);
					break;
				case CONDITION_OPERATOR_MORE_EQUAL:
					if (0 != tmp_int && (now - tmp_int) >= condition_value_i)
						add_condition_match(esc_events, condition, objectid, objects[i]);
					break;
			}
		}
		DBfree_result(result);
	}

	zbx_vector_uint64_destroy(&objectids[0]);
	zbx_vector_uint64_destroy(&objectids[1]);
	zbx_free(sql);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check service port condition for discovery                        *
 *                                                                            *
 * Parameters: esc_events - [IN] events to check                              *
 *             condition  - [IN/OUT] condition for matching, outputs          *
 *                                   event ids that match condition           *
 *                                                                            *
 * Return value: SUCCEED - supported operator                                 *
 *               NOTSUPPORTED - not supported operator                        *
 *                                                                            *
 ******************************************************************************/
static int	check_dservice_port_condition(const zbx_vector_ptr_t *esc_events, zbx_condition_t *condition)
{
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	DB_RESULT		result;
	DB_ROW			row;
	int			object = EVENT_OBJECT_DSERVICE;
	zbx_vector_uint64_t	objectids;
	int			i;

	if (CONDITION_OPERATOR_EQUAL != condition->op && CONDITION_OPERATOR_NOT_EQUAL != condition->op)
		return NOTSUPPORTED;

	zbx_vector_uint64_create(&objectids);

	for (i = 0; i < esc_events->values_num; i++)
	{
		const DB_EVENT	*event = (DB_EVENT *)esc_events->values[i];

		if (object == event->object)
			zbx_vector_uint64_append(&objectids, event->objectid);
	}

	if (0 != objectids.values_num)
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"select dserviceid,port"
				" from dservices"
				" where");

		zbx_vector_uint64_uniq(&objectids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "dserviceid", objectids.values,
				objectids.values_num);

		result = DBselect("%s", sql);

		while (NULL != (row = DBfetch(result)))
		{
			zbx_uint64_t	objectid;

			ZBX_STR2UINT64(objectid, row[0]);
			switch (condition->op)
			{
				case CONDITION_OPERATOR_EQUAL:
					if (SUCCEED == int_in_list(condition->value, atoi(row[1])))
						add_condition_match(esc_events, condition, objectid, object);
					break;
				case CONDITION_OPERATOR_NOT_EQUAL:
					if (SUCCEED != int_in_list(condition->value, atoi(row[1])))
						add_condition_match(esc_events, condition, objectid, object);
					break;
			}
		}
		DBfree_result(result);
	}

	zbx_vector_uint64_destroy(&objectids);
	zbx_free(sql);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if event matches single condition                           *
 *                                                                            *
 * Parameters: event - discovery event to check                               *
 *                                 (event->source == EVENT_SOURCE_DISCOVERY)  *
 *             condition - condition for matching                             *
 *                                                                            *
 * Return value: SUCCEED - matches, FAIL - otherwise                          *
 *                                                                            *
 ******************************************************************************/
static void	check_discovery_condition(const zbx_vector_ptr_t *esc_events, zbx_condition_t *condition)
{
	int	ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	switch (condition->conditiontype)
	{
		case CONDITION_TYPE_DRULE:
			ret = check_drule_condition(esc_events, condition);
			break;
		case CONDITION_TYPE_DCHECK:
			ret = check_dcheck_condition(esc_events, condition);
			break;
		case CONDITION_TYPE_DOBJECT:
			ret = check_dobject_condition(esc_events, condition);
			break;
		case CONDITION_TYPE_PROXY:
			ret = check_proxy_condition(esc_events, condition);
			break;
		case CONDITION_TYPE_DVALUE:
			ret = check_dvalue_condition(esc_events, condition);
			break;
		case CONDITION_TYPE_DHOST_IP:
			ret = check_dhost_ip_condition(esc_events, condition);
			break;
		case CONDITION_TYPE_DSERVICE_TYPE:
			ret = check_dservice_type_condition(esc_events, condition);
			break;
		case CONDITION_TYPE_DSTATUS:
			ret = check_dstatus_condition(esc_events, condition);
			break;
		case CONDITION_TYPE_DUPTIME:
			ret = check_duptime_condition(esc_events, condition);
			break;
		case CONDITION_TYPE_DSERVICE_PORT:
			ret = check_dservice_port_condition(esc_events, condition);
			break;
		default:
			ret = FAIL;
			zabbix_log(LOG_LEVEL_ERR, "unsupported condition type [%d] for condition id [" ZBX_FS_UI64 "]",
					(int)condition->conditiontype, condition->conditionid);
	}

	if (NOTSUPPORTED == ret)
	{
		zabbix_log(LOG_LEVEL_ERR, "unsupported operator [%d] for condition id [" ZBX_FS_UI64 "]",
				(int)condition->op, condition->conditionid);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));
}

/******************************************************************************
 *                                                                            *
 * Purpose: check metadata or host condition for auto registration            *
 *                                                                            *
 * Parameters: esc_events - [IN] events to check                              *
 *             condition  - [IN/OUT] condition for matching, outputs          *
 *                                   event ids that match condition           *
 *                                                                            *
 * Return value: SUCCEED - supported operator                                 *
 *               NOTSUPPORTED - not supported operator                        *
 *                                                                            *
 ******************************************************************************/
static int	check_hostname_metadata_condition(const zbx_vector_ptr_t *esc_events, zbx_condition_t *condition)
{
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	DB_RESULT		result;
	DB_ROW			row;
	int			object = EVENT_OBJECT_ZABBIX_ACTIVE;
	zbx_vector_uint64_t	objectids;
	const char		*condition_field;

	switch(condition->op)
	{
		case CONDITION_OPERATOR_LIKE:
		case CONDITION_OPERATOR_NOT_LIKE:
		case CONDITION_OPERATOR_REGEXP:
		case CONDITION_OPERATOR_NOT_REGEXP:
			break;
		default:
			return NOTSUPPORTED;
	}

	if (CONDITION_TYPE_HOST_NAME == condition->conditiontype)
		condition_field = "host";
	else
		condition_field = "host_metadata";

	zbx_vector_uint64_create(&objectids);
	get_object_ids(esc_events, &objectids);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select autoreg_hostid,%s"
			" from autoreg_host"
			" where",
			condition_field);

	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "autoreg_hostid", objectids.values, objectids.values_num);

	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		zbx_uint64_t	objectid;

		ZBX_STR2UINT64(objectid, row[0]);

		switch (condition->op)
		{
			case CONDITION_OPERATOR_LIKE:
				if (NULL != strstr(row[1], condition->value))
					add_condition_match(esc_events, condition, objectid, object);
				break;
			case CONDITION_OPERATOR_NOT_LIKE:
				if (NULL == strstr(row[1], condition->value))
					add_condition_match(esc_events, condition, objectid, object);
				break;
			case CONDITION_OPERATOR_REGEXP:
				if (NULL != zbx_regexp_match(row[1], condition->value, NULL))
					add_condition_match(esc_events, condition, objectid, object);
				break;
			case CONDITION_OPERATOR_NOT_REGEXP:
				if (NULL == zbx_regexp_match(row[1], condition->value, NULL))
					add_condition_match(esc_events, condition, objectid, object);
				break;
		}
	}
	DBfree_result(result);

	zbx_vector_uint64_destroy(&objectids);
	zbx_free(sql);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check proxy condition for auto registration                       *
 *                                                                            *
 * Parameters: esc_events - [IN] events to check                              *
 *             condition  - [IN/OUT] condition for matching, outputs          *
 *                                   event ids that match condition           *
 *                                                                            *
 * Return value: SUCCEED - supported operator                                 *
 *               NOTSUPPORTED - not supported operator                        *
 *                                                                            *
 ******************************************************************************/
static int	check_areg_proxy_condition(const zbx_vector_ptr_t *esc_events, zbx_condition_t *condition)
{
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	DB_RESULT		result;
	DB_ROW			row;
	int			object = EVENT_OBJECT_ZABBIX_ACTIVE;
	zbx_vector_uint64_t	objectids;
	zbx_uint64_t		condition_value;

	ZBX_STR2UINT64(condition_value, condition->value);

	if (CONDITION_OPERATOR_EQUAL != condition->op && CONDITION_OPERATOR_NOT_EQUAL != condition->op)
		return NOTSUPPORTED;

	zbx_vector_uint64_create(&objectids);
	get_object_ids(esc_events, &objectids);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select autoreg_hostid,proxy_hostid"
			" from autoreg_host"
			" where");

	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "autoreg_hostid",
			objectids.values, objectids.values_num);

	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		zbx_uint64_t	id;
		zbx_uint64_t	objectid;

		ZBX_STR2UINT64(objectid, row[0]);
		ZBX_DBROW2UINT64(id, row[1]);

		switch (condition->op)
		{
			case CONDITION_OPERATOR_EQUAL:
				if (id == condition_value)
					add_condition_match(esc_events, condition, objectid, object);
				break;
			case CONDITION_OPERATOR_NOT_EQUAL:
				if (id != condition_value)
					add_condition_match(esc_events, condition, objectid, object);
				break;
		}
	}
	DBfree_result(result);

	zbx_vector_uint64_destroy(&objectids);
	zbx_free(sql);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if event matches single condition                           *
 *                                                                            *
 * Parameters: event - autoregistration event to check                        *
 *                         (event->source == EVENT_SOURCE_AUTOREGISTRATION)   *
 *             condition - condition for matching                             *
 *                                                                            *
 * Return value: SUCCEED - matches, FAIL - otherwise                          *
 *                                                                            *
 ******************************************************************************/
static void	check_autoregistration_condition(const zbx_vector_ptr_t *esc_events, zbx_condition_t *condition)
{
	const char	*__function_name = "check_auto_registration_condition";
	int		ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	switch (condition->conditiontype)
	{
		case CONDITION_TYPE_HOST_NAME:
		case CONDITION_TYPE_HOST_METADATA:
			ret = check_hostname_metadata_condition(esc_events, condition);
			break;
		case CONDITION_TYPE_PROXY:
			ret = check_areg_proxy_condition(esc_events, condition);
			break;
		default:
			zabbix_log(LOG_LEVEL_ERR, "unsupported condition type [%d] for condition id [" ZBX_FS_UI64 "]",
					(int)condition->conditiontype, condition->conditionid);
			ret = FAIL;
	}

	if (NOTSUPPORTED == ret)
	{
		zabbix_log(LOG_LEVEL_ERR, "unsupported operator [%d] for condition id [" ZBX_FS_UI64 "]",
				(int)condition->op, condition->conditionid);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Purpose: not all event objects are supported for internal events           *
 *                                                                            *
 * Parameters: events     - [IN]  events to check                             *
 *                                                                            *
 * Return value: SUCCEED - supported                                          *
 *               FAIL - not supported                                         *
 *                                                                            *
 ******************************************************************************/
static int	is_supported_event_object(const DB_EVENT *event)
{
	return (EVENT_OBJECT_TRIGGER == event->object || EVENT_OBJECT_ITEM == event->object ||
					EVENT_OBJECT_LLDRULE == event->object) ? SUCCEED : FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check event type condition for internal events                    *
 *                                                                            *
 * Parameters: esc_events - [IN] events to check                              *
 *             condition  - [IN/OUT] condition for matching, outputs          *
 *                                   event ids that match condition           *
 *                                                                            *
 * Return value: SUCCEED - supported operator                                 *
 *               NOTSUPPORTED - not supported operator                        *
 *                                                                            *
 ******************************************************************************/
static int	check_intern_event_type_condition(const zbx_vector_ptr_t *esc_events, zbx_condition_t *condition)
{
	int		i;
	zbx_uint64_t	condition_value;

	condition_value = atoi(condition->value);

	for (i = 0; i < esc_events->values_num; i++)
	{
		const DB_EVENT	*event = (DB_EVENT *)esc_events->values[i];

		if (FAIL == is_supported_event_object(event))
		{
			zabbix_log(LOG_LEVEL_ERR, "unsupported event object [%d] for condition id [" ZBX_FS_UI64 "]",
					event->object, condition->conditionid);
			continue;
		}

		switch (condition_value)
		{
			case EVENT_TYPE_ITEM_NOTSUPPORTED:
				if (EVENT_OBJECT_ITEM == event->object && ITEM_STATE_NOTSUPPORTED == event->value)
					zbx_vector_uint64_append(&condition->eventids, event->eventid);
				break;
			case EVENT_TYPE_TRIGGER_UNKNOWN:
				if (EVENT_OBJECT_TRIGGER == event->object && TRIGGER_STATE_UNKNOWN == event->value)
					zbx_vector_uint64_append(&condition->eventids, event->eventid);
				break;
			case EVENT_TYPE_LLDRULE_NOTSUPPORTED:
				if (EVENT_OBJECT_LLDRULE == event->object && ITEM_STATE_NOTSUPPORTED == event->value)
					zbx_vector_uint64_append(&condition->eventids, event->eventid);
				break;
			default:
				return NOTSUPPORTED;
		}
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get objectids of escalation internal events                       *
 *                                                                            *
 * Parameters: esc_events  - [IN]  events to check                            *
 *             objectids   - [OUT] event objectids to be used in condition    *
 *                                 allocation 2 vectors where first one is    *
 *                                 trigger object ids, second is rest         *
 *             objects     - [IN] the array of event objects                  *
 *             objects_num - [IN] the number of objects in objects array      *
 *                                                                            *
 ******************************************************************************/
static void	get_object_ids_internal(const zbx_vector_ptr_t *esc_events, zbx_vector_uint64_t *objectids,
		const int *objects, const int objects_num)
{
	int	i, j;

	for (i = 0; i < esc_events->values_num; i++)
	{
		const DB_EVENT	*event = (DB_EVENT *)esc_events->values[i];

		for (j = 0; j < objects_num; j++)
		{
			if (event->object == objects[j])
			{
				zbx_vector_uint64_append(&objectids[j], event->objectid);
				break;
			}
		}

		if (j == objects_num)
			zabbix_log(LOG_LEVEL_ERR, "unsupported event object [%d]", event->object);
	}

	for (i = 0; i < objects_num; i++)
		zbx_vector_uint64_uniq(&objectids[i], ZBX_DEFAULT_UINT64_COMPARE_FUNC);
}

/******************************************************************************
 *                                                                            *
 * Purpose: check host group condition for internal events                    *
 *                                                                            *
 * Parameters: esc_events - [IN] events to check                              *
 *             condition  - [IN/OUT] condition for matching, outputs          *
 *                                   event ids that match condition           *
 *                                                                            *
 * Return value: SUCCEED - supported operator                                 *
 *               NOTSUPPORTED - not supported operator                        *
 *                                                                            *
 ******************************************************************************/
static int	check_intern_host_group_condition(const zbx_vector_ptr_t *esc_events, zbx_condition_t *condition)
{
	char			*sql = NULL;
	size_t			sql_alloc = 0, i;
	DB_RESULT		result;
	DB_ROW			row;
	int			objects[3] = {EVENT_OBJECT_TRIGGER, EVENT_OBJECT_ITEM, EVENT_OBJECT_LLDRULE};
	zbx_vector_uint64_t	objectids[3], groupids;
	zbx_uint64_t		condition_value;

	if (CONDITION_OPERATOR_EQUAL != condition->op && CONDITION_OPERATOR_NOT_EQUAL != condition->op)
		return NOTSUPPORTED;

	ZBX_STR2UINT64(condition_value, condition->value);

	for (i = 0; i < (int)ARRSIZE(objects); i++)
		zbx_vector_uint64_create(&objectids[i]);

	zbx_vector_uint64_create(&groupids);

	get_object_ids_internal(esc_events, objectids, objects, (int)ARRSIZE(objects));

	zbx_dc_get_nested_hostgroupids(&condition_value, 1, &groupids);

	for (i = 0; i < (int)ARRSIZE(objects); i++)
	{
		size_t	sql_offset = 0;

		if (0 == objectids[i].values_num)
			continue;

		if (EVENT_OBJECT_TRIGGER == objects[i])
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
					"select distinct f.triggerid"
					" from hosts_groups hg,hosts h,items i,functions f"
					" where hg.hostid=h.hostid"
						" and h.hostid=i.hostid"
						" and i.itemid=f.itemid"
						" and");

			DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "f.triggerid", objectids[i].values,
					objectids[i].values_num);
		}
		else	/* EVENT_OBJECT_ITEM, EVENT_OBJECT_LLDRULE */
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
					"select distinct i.itemid"
					" from hosts_groups hg,hosts h,items i"
					" where hg.hostid=h.hostid"
						" and h.hostid=i.hostid"
						" and");

			DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "i.itemid",
					objectids[i].values, objectids[i].values_num);
		}

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " and");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "hg.groupid", groupids.values,
				groupids.values_num);

		result = DBselect("%s", sql);

		while (NULL != (row = DBfetch(result)))
		{
			zbx_uint64_t	objectid;

			ZBX_STR2UINT64(objectid, row[0]);
			if (CONDITION_OPERATOR_NOT_EQUAL == condition->op)
			{
				int	index;

				if (FAIL != (index = zbx_vector_uint64_search(&objectids[i], objectid,
						ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
				{
					zbx_vector_uint64_remove_noorder(&objectids[i], index);
				}
			}
			else
				add_condition_match(esc_events, condition, objectid, objects[i]);
		}
		DBfree_result(result);
	}

	for (i = 0; i < (int)ARRSIZE(objects); i++)
	{
		if (CONDITION_OPERATOR_NOT_EQUAL == condition->op)
		{
			int	j;

			for (j = 0; j < objectids[i].values_num; j++)
				add_condition_match(esc_events, condition, objectids[i].values[j], objects[i]);
		}

		zbx_vector_uint64_destroy(&objectids[i]);
	}

	zbx_vector_uint64_destroy(&groupids);
	zbx_free(sql);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get parent id from item discovery                                 *
 *                                                                            *
 * Parameters: sql           [IN/OUT] - allocated sql query                   *
 *             sql_alloc     [IN/OUT] - how much bytes allocated              *
 *             objectids_tmp [IN/OUT] - uses to allocate query, removes       *
 *                                      duplicates                            *
 *                                                                            *
 ******************************************************************************/
static void	item_parents_sql_alloc(char **sql, size_t *sql_alloc, zbx_vector_uint64_t *objectids_tmp)
{
	size_t	sql_offset = 0;

	zbx_snprintf_alloc(sql, sql_alloc, &sql_offset,
			"select i.itemid,id.parent_itemid"
			" from item_discovery id,items i"
			" where id.itemid=i.itemid"
				" and i.flags=%d"
				" and",
			ZBX_FLAG_DISCOVERY_CREATED);

	DBadd_condition_alloc(sql, sql_alloc, &sql_offset, "i.itemid",
			objectids_tmp->values, objectids_tmp->values_num);
}

/******************************************************************************
 *                                                                            *
 * Purpose: check host template condition for internal events                 *
 *                                                                            *
 * Parameters: esc_events - [IN] events to check                              *
 *             condition  - [IN/OUT] condition for matching, outputs          *
 *                                   event ids that match condition           *
 *                                                                            *
 * Return value: SUCCEED - supported operator                                 *
 *               NOTSUPPORTED - not supported operator                        *
 *                                                                            *
 ******************************************************************************/
static int	check_intern_host_template_condition(const zbx_vector_ptr_t *esc_events, zbx_condition_t *condition)
{
	char				*sql = NULL;
	size_t				sql_alloc = 0;
	DB_RESULT			result;
	DB_ROW				row;
	zbx_uint64_t			condition_value;
	int				i, j;
	int				objects[3] = {EVENT_OBJECT_TRIGGER, EVENT_OBJECT_ITEM, EVENT_OBJECT_LLDRULE};
	zbx_vector_uint64_t		objectids[3];
	zbx_vector_uint64_pair_t	objectids_pair[3];

	if (CONDITION_OPERATOR_EQUAL != condition->op && CONDITION_OPERATOR_NOT_EQUAL != condition->op)
		return NOTSUPPORTED;

	for (i = 0; i < (int)ARRSIZE(objects); i++)
	{
		zbx_vector_uint64_create(&objectids[i]);
		zbx_vector_uint64_pair_create(&objectids_pair[i]);
	}

	get_object_ids_internal(esc_events, objectids, objects, (int)ARRSIZE(objects));

	ZBX_STR2UINT64(condition_value, condition->value);

	for (i = 0; i < (int)ARRSIZE(objects); i++)
	{
		zbx_vector_uint64_t		*objectids_ptr = &objectids[i];
		zbx_vector_uint64_pair_t	*objectids_pair_ptr = &objectids_pair[i];

		if (0 == objectids_ptr->values_num)
			continue;

		objectids_to_pair(objectids_ptr, objectids_pair_ptr);

		if (EVENT_OBJECT_TRIGGER == objects[i])
			trigger_parents_sql_alloc(&sql, &sql_alloc, objectids_ptr);
		else	/* EVENT_OBJECT_ITEM, EVENT_OBJECT_LLDRULE */
			item_parents_sql_alloc(&sql, &sql_alloc, objectids_ptr);

		result = DBselect("%s", sql);

		while (NULL != (row = DBfetch(result)))
		{
			zbx_uint64_pair_t	pair;

			ZBX_STR2UINT64(pair.first, row[0]);

			if (FAIL != (j = zbx_vector_uint64_pair_search(objectids_pair_ptr, pair,
					ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
			{
				ZBX_STR2UINT64(objectids_pair_ptr->values[j].second, row[1]);
			}
		}
		DBfree_result(result);

		check_object_hierarchy(objects[i], esc_events, objectids_ptr, objectids_pair_ptr, condition, condition_value,
				0 == i ?
					"select distinct t.triggerid,t.templateid,i.hostid"
						" from items i,functions f,triggers t"
						" where i.itemid=f.itemid"
							" and f.triggerid=t.templateid"
							" and" :
					"select distinct h.itemid,t.itemid,t.hostid"
						" from items t,items h"
						" where t.itemid=h.templateid"
							" and",
				0 == i ? "t.triggerid" : "h.itemid");
	}

	for (i = 0; i < (int)ARRSIZE(objects); i++)
	{
		zbx_vector_uint64_destroy(&objectids[i]);
		zbx_vector_uint64_pair_destroy(&objectids_pair[i]);
	}

	zbx_free(sql);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check host condition for internal events                          *
 *                                                                            *
 * Parameters: esc_events - [IN] events to check                              *
 *             condition  - [IN/OUT] condition for matching, outputs          *
 *                                   event ids that match condition           *
 *                                                                            *
 * Return value: SUCCEED - supported operator                                 *
 *               NOTSUPPORTED - not supported operator                        *
 *                                                                            *
 ******************************************************************************/
static int	check_intern_host_condition(const zbx_vector_ptr_t *esc_events, zbx_condition_t *condition)
{
	char			*sql = NULL, *operation, *operation_item;
	size_t			sql_alloc = 0, i;
	DB_RESULT		result;
	DB_ROW			row;
	int			objects[3] = {EVENT_OBJECT_TRIGGER, EVENT_OBJECT_ITEM, EVENT_OBJECT_LLDRULE};
	zbx_vector_uint64_t	objectids[3];
	zbx_uint64_t		condition_value;

	if (CONDITION_OPERATOR_EQUAL == condition->op)
	{
		operation = " and";
		operation_item = " where";
	}
	else if (CONDITION_OPERATOR_NOT_EQUAL == condition->op)
	{
		operation = " and not";
		operation_item = " where not";
	}
	else
		return NOTSUPPORTED;

	ZBX_STR2UINT64(condition_value, condition->value);

	for (i = 0; i < (int)ARRSIZE(objects); i++)
		zbx_vector_uint64_create(&objectids[i]);

	get_object_ids_internal(esc_events, objectids, objects, (int)ARRSIZE(objects));

	for (i = 0; i < (int)ARRSIZE(objects); i++)
	{
		size_t	sql_offset = 0;

		if (0 == objectids[i].values_num)
			continue;

		if (EVENT_OBJECT_TRIGGER == objects[i])
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
					"select distinct f.triggerid"
					" from items i,functions f"
					" where i.itemid=f.itemid"
						"%s i.hostid=" ZBX_FS_UI64
						" and",
					operation,
					condition_value);

			DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "f.triggerid",
					objectids[i].values, objectids[i].values_num);
		}
		else	/* EVENT_OBJECT_ITEM, EVENT_OBJECT_LLDRULE */
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
					"select itemid"
					" from items"
					"%s hostid=" ZBX_FS_UI64
						" and",
					operation_item,
					condition_value);

			DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "itemid",
					objectids[i].values, objectids[i].values_num);
		}

		result = DBselect("%s", sql);

		while (NULL != (row = DBfetch(result)))
		{
			zbx_uint64_t	objectid;

			ZBX_STR2UINT64(objectid, row[0]);
			add_condition_match(esc_events, condition, objectid, objects[i]);
		}
		DBfree_result(result);
	}

	for (i = 0; i < (int)ARRSIZE(objects); i++)
		zbx_vector_uint64_destroy(&objectids[i]);

	zbx_free(sql);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if internal event matches single condition                  *
 *                                                                            *
 * Parameters: event     - [IN] trigger event to check                        *
 *             condition - [IN] condition for matching                        *
 *                                                                            *
 * Return value: SUCCEED - matches, FAIL - otherwise                          *
 *                                                                            *
 ******************************************************************************/
static void	check_internal_condition(const zbx_vector_ptr_t *esc_events, zbx_condition_t *condition)
{
	int	ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	switch (condition->conditiontype)
	{
		case CONDITION_TYPE_EVENT_TYPE:
			ret = check_intern_event_type_condition(esc_events, condition);
			break;
		case CONDITION_TYPE_HOST_GROUP:
			ret = check_intern_host_group_condition(esc_events, condition);
			break;
		case CONDITION_TYPE_HOST_TEMPLATE:
			ret = check_intern_host_template_condition(esc_events, condition);
			break;
		case CONDITION_TYPE_HOST:
			ret = check_intern_host_condition(esc_events, condition);
			break;
		case CONDITION_TYPE_EVENT_TAG:
			check_condition_event_tag(esc_events, condition);
			ret = SUCCEED;
			break;
		case CONDITION_TYPE_EVENT_TAG_VALUE:
			check_condition_event_tag_value(esc_events,condition);
			ret = SUCCEED;
			break;
		default:
			ret = FAIL;
			zabbix_log(LOG_LEVEL_ERR, "unsupported condition type [%d] for condition id [" ZBX_FS_UI64 "]",
					(int)condition->conditiontype, condition->conditionid);
	}

	if (NOTSUPPORTED == ret)
	{
		zabbix_log(LOG_LEVEL_ERR, "unsupported operator [%d] for condition id [" ZBX_FS_UI64 "]",
				(int)condition->op, condition->conditionid);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if multiple events matches single condition                 *
 *                                                                            *
 * Parameters: esc_events - [IN] events to check                              *
 *             source     - [IN] specific event source that need checking     *
 *             condition  - [IN/OUT] condition for matching, outputs          *
 *                                   event ids that match condition           *
 *                                                                            *
 ******************************************************************************/
static void	check_events_condition(const zbx_vector_ptr_t *esc_events, unsigned char source, zbx_condition_t *condition)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() actionid:" ZBX_FS_UI64 " conditionid:" ZBX_FS_UI64 " cond.value:'%s'"
			" cond.value2:'%s'", __func__, condition->actionid, condition->conditionid,
			condition->value, condition->value2);

	switch (source)
	{
		case EVENT_SOURCE_TRIGGERS:
			check_trigger_condition(esc_events, condition);
			break;
		case EVENT_SOURCE_DISCOVERY:
			check_discovery_condition(esc_events, condition);
			break;
		case EVENT_SOURCE_AUTOREGISTRATION:
			check_autoregistration_condition(esc_events, condition);
			break;
		case EVENT_SOURCE_INTERNAL:
			check_internal_condition(esc_events, condition);
			break;
		default:
			zabbix_log(LOG_LEVEL_ERR, "unsupported event source [%d] for condition id [" ZBX_FS_UI64 "]",
					source, condition->conditionid);
	}

	zbx_vector_uint64_sort(&condition->eventids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if event matches single condition                           *
 *                                                                            *
 * Parameters: event - event to check                                         *
 *             condition - condition for matching                             *
 *                                                                            *
 * Return value: SUCCEED - matches, FAIL - otherwise                          *
 *                                                                            *
 ******************************************************************************/
int	check_action_condition(const DB_EVENT *event, zbx_condition_t *condition)
{
	int			ret;
	zbx_vector_ptr_t	esc_events;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() actionid:" ZBX_FS_UI64 " conditionid:" ZBX_FS_UI64 " cond.value:'%s'"
			" cond.value2:'%s'", __func__, condition->actionid, condition->conditionid,
			ZBX_NULL2STR(condition->value), ZBX_NULL2STR(condition->value2));

	zbx_vector_ptr_create(&esc_events);

	zbx_vector_ptr_append(&esc_events, (DB_EVENT *)event);

	check_events_condition(&esc_events, event->source, condition);

	ret = 0 != condition->eventids.values_num ? SUCCEED : FAIL;

	zbx_vector_ptr_destroy(&esc_events);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if action have to be processed for the event                *
 *          (check all conditions of the action)                              *
 *                                                                            *
 * Parameters: eventid - [IN] the id of event that will be checked            *
 *             action  - [IN] action for matching                             *
 *                                                                            *
 * Return value: SUCCEED - matches, FAIL - otherwise                          *
 *                                                                            *
 ******************************************************************************/
static int	check_action_conditions(zbx_uint64_t eventid, const zbx_action_eval_t *action)
{
	zbx_condition_t	*condition;
	int		condition_result, ret = SUCCEED, id_len, i;
	unsigned char	old_type = 0xff;
	char		*expression = NULL, tmp[ZBX_MAX_UINT64_LEN + 2], *ptr, error[256];
	double		eval_result;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() actionid:" ZBX_FS_UI64 " eventsource:%d", __func__,
			action->actionid, (int)action->eventsource);

	if (CONDITION_EVAL_TYPE_EXPRESSION == action->evaltype)
		expression = zbx_strdup(expression, action->formula);

	for (i = 0; i < action->conditions.values_num; i++)
	{
		condition = (zbx_condition_t *)action->conditions.values[i];

		if (CONDITION_EVAL_TYPE_AND_OR == action->evaltype && old_type == condition->conditiontype &&
				SUCCEED == ret)
		{
			continue;	/* short-circuit true OR condition block to the next AND condition */
		}

		condition_result = FAIL == zbx_vector_uint64_bsearch(&condition->eventids, eventid,
				ZBX_DEFAULT_UINT64_COMPARE_FUNC) ? FAIL : SUCCEED;

		zabbix_log(LOG_LEVEL_DEBUG, " conditionid:" ZBX_FS_UI64 " conditiontype:%d cond.value:'%s' "
				"cond.value2:'%s' result:%s", condition->conditionid, (int)condition->conditiontype,
				condition->value, condition->value2, zbx_result_string(condition_result));

		switch (action->evaltype)
		{
			case CONDITION_EVAL_TYPE_AND_OR:
				if (old_type == condition->conditiontype)	/* assume conditions are sorted by type */
				{
					if (SUCCEED == condition_result)
						ret = SUCCEED;
				}
				else
				{
					if (FAIL == ret)
						goto clean;

					ret = condition_result;
					old_type = condition->conditiontype;
				}

				break;
			case CONDITION_EVAL_TYPE_AND:
				if (FAIL == condition_result)	/* break if any AND condition is FALSE */
				{
					ret = FAIL;
					goto clean;
				}

				break;
			case CONDITION_EVAL_TYPE_OR:
				if (SUCCEED == condition_result)	/* break if any OR condition is TRUE */
				{
					ret = SUCCEED;
					goto clean;
				}
				ret = FAIL;

				break;
			case CONDITION_EVAL_TYPE_EXPRESSION:
				zbx_snprintf(tmp, sizeof(tmp), "{" ZBX_FS_UI64 "}", condition->conditionid);
				id_len = strlen(tmp);

				for (ptr = expression; NULL != (ptr = strstr(ptr, tmp)); ptr += id_len)
				{
					*ptr = (SUCCEED == condition_result ? '1' : '0');
					memset(ptr + 1, ' ', id_len - 1);
				}

				break;
			default:
				ret = FAIL;
				goto clean;
		}
	}

	if (CONDITION_EVAL_TYPE_EXPRESSION == action->evaltype)
	{
		if (SUCCEED == evaluate(&eval_result, expression, error, sizeof(error), NULL))
			ret = (SUCCEED != zbx_double_compare(eval_result, 0) ? SUCCEED : FAIL);

		zbx_free(expression);
	}
clean:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute host, group, template operations linked to the action     *
 *                                                                            *
 * Parameters: action - action to execute operations for                      *
 *                                                                            *
 * Comments: for message, command operations see                              *
 *           escalation_execute_operations(),                                 *
 *           escalation_execute_recovery_operations().                        *
 *                                                                            *
 ******************************************************************************/
static void	execute_operations(const DB_EVENT *event, zbx_uint64_t actionid)
{
	DB_RESULT		result;
	DB_ROW			row;
	zbx_uint64_t		groupid, templateid;
	zbx_vector_uint64_t	lnk_templateids, del_templateids,
				new_groupids, del_groupids;
	int			i;
	zbx_config_t		cfg;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() actionid:" ZBX_FS_UI64, __func__, actionid);

	zbx_vector_uint64_create(&lnk_templateids);
	zbx_vector_uint64_create(&del_templateids);
	zbx_vector_uint64_create(&new_groupids);
	zbx_vector_uint64_create(&del_groupids);

	result = DBselect(
			"select o.operationtype,g.groupid,t.templateid,oi.inventory_mode"
			" from operations o"
				" left join opgroup g on g.operationid=o.operationid"
				" left join optemplate t on t.operationid=o.operationid"
				" left join opinventory oi on oi.operationid=o.operationid"
			" where o.actionid=" ZBX_FS_UI64
			" order by o.operationid",
			actionid);

	zbx_config_get(&cfg, ZBX_CONFIG_FLAGS_DISCOVERY_GROUPID | ZBX_CONFIG_FLAGS_DEFAULT_INVENTORY_MODE |
			ZBX_CONFIG_FLAGS_AUDITLOG_ENABLED);
	zbx_audit_init(cfg.auditlog_enabled);

	while (NULL != (row = DBfetch(result)))
	{
		int		inventory_mode;
		unsigned char	operationtype;

		operationtype = (unsigned char)atoi(row[0]);
		ZBX_DBROW2UINT64(groupid, row[1]);
		ZBX_DBROW2UINT64(templateid, row[2]);
		inventory_mode = (SUCCEED == DBis_null(row[3]) ? 0 : atoi(row[3]));

		switch (operationtype)
		{
			case OPERATION_TYPE_HOST_ADD:
				op_host_add(event, &cfg);
				break;
			case OPERATION_TYPE_HOST_REMOVE:
				op_host_del(event);
				break;
			case OPERATION_TYPE_HOST_ENABLE:
				op_host_enable(event, &cfg);
				break;
			case OPERATION_TYPE_HOST_DISABLE:
				op_host_disable(event, &cfg);
				break;
			case OPERATION_TYPE_GROUP_ADD:
				if (0 != groupid)
					zbx_vector_uint64_append(&new_groupids, groupid);
				break;
			case OPERATION_TYPE_GROUP_REMOVE:
				if (0 != groupid)
					zbx_vector_uint64_append(&del_groupids, groupid);
				break;
			case OPERATION_TYPE_TEMPLATE_ADD:
				if (0 != templateid)
				{
					if (FAIL != (i = zbx_vector_uint64_search(&del_templateids, templateid,
							ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
					{
						zbx_vector_uint64_remove(&del_templateids, i);
					}

					zbx_vector_uint64_append(&lnk_templateids, templateid);
				}
				break;
			case OPERATION_TYPE_TEMPLATE_REMOVE:
				if (0 != templateid)
				{
					if (FAIL != (i = zbx_vector_uint64_search(&lnk_templateids, templateid,
							ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
					{
						zbx_vector_uint64_remove(&lnk_templateids, i);
					}

					zbx_vector_uint64_append(&del_templateids, templateid);
				}
				break;
			case OPERATION_TYPE_HOST_INVENTORY:
				op_host_inventory_mode(event, &cfg, inventory_mode);
				break;
			default:
				;
		}
	}
	DBfree_result(result);

	if (0 != del_templateids.values_num)
	{
		zbx_vector_uint64_sort(&del_templateids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_vector_uint64_uniq(&del_templateids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		op_template_del(event, &del_templateids);
	}

	if (0 != lnk_templateids.values_num)
	{
		zbx_vector_uint64_sort(&lnk_templateids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_vector_uint64_uniq(&lnk_templateids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		op_template_add(event, &cfg, &lnk_templateids);
	}

	if (0 != new_groupids.values_num)
	{
		zbx_vector_uint64_sort(&new_groupids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_vector_uint64_uniq(&new_groupids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		op_groups_add(event, &cfg, &new_groupids);
	}

	if (0 != del_groupids.values_num)
	{
		zbx_vector_uint64_sort(&del_groupids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_vector_uint64_uniq(&del_groupids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		op_groups_del(event, &del_groupids);
	}

	zbx_vector_uint64_destroy(&del_groupids);
	zbx_vector_uint64_destroy(&new_groupids);
	zbx_vector_uint64_destroy(&del_templateids);
	zbx_vector_uint64_destroy(&lnk_templateids);

	zbx_audit_flush();

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/* data structures used to create new and recover existing escalations */

typedef struct
{
	zbx_uint64_t	actionid;
	const DB_EVENT	*event;
}
zbx_escalation_new_t;

/******************************************************************************
 *                                                                            *
 * Purpose: checks if the event is recovery event                             *
 *                                                                            *
 * Parameters: event - [IN] the event to check                                *
 *                                                                            *
 * Return value: SUCCEED - the event is recovery event                        *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	is_recovery_event(const DB_EVENT *event)
{
	if (EVENT_SOURCE_TRIGGERS == event->source)
	{
		if (EVENT_OBJECT_TRIGGER == event->object && TRIGGER_VALUE_OK == event->value)
			return SUCCEED;
	}
	else if (EVENT_SOURCE_INTERNAL == event->source)
	{
		switch (event->object)
		{
			case EVENT_OBJECT_TRIGGER:
				if (TRIGGER_STATE_NORMAL == event->value)
					return SUCCEED;
				break;
			case EVENT_OBJECT_ITEM:
				if (ITEM_STATE_NORMAL == event->value)
					return SUCCEED;
				break;
			case EVENT_OBJECT_LLDRULE:
				if (ITEM_STATE_NORMAL == event->value)
					return SUCCEED;
				break;
		}
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: to determine if event needs condition checks                      *
 *                                                                            *
 * Parameters: event - [IN] event to validate                                 *
 *                                                                            *
 * Return value: SUCCEED - escalations possible for event                     *
 *               FAIL    - escalations not possible for event                 *
 *                                                                            *
 ******************************************************************************/
static int	is_escalation_event(const DB_EVENT *event)
{
	/* OK events can't start escalations - skip them */
	if (SUCCEED == is_recovery_event(event))
		return FAIL;

	if (0 != (event->flags & ZBX_FLAGS_DB_EVENT_NO_ACTION))
		return FAIL;

	if (0 == (event->flags & ZBX_FLAGS_DB_EVENT_CREATE))
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: compare to find equal conditions                                  *
 *                                                                            *
 * Parameters: d1 - [IN] condition structure to compare to d2                 *
 *             d2 - [IN] condition structure to compare to d1                 *
 *                                                                            *
 * Return value: 0 - equal                                                    *
 *               not 0 - otherwise                                            *
 *                                                                            *
 ******************************************************************************/
static int	uniq_conditions_compare_func(const void *d1, const void *d2)
{
	const zbx_condition_t	*condition1 = (const zbx_condition_t *)d1, *condition2 = (const zbx_condition_t *)d2;
	int			ret;

	ZBX_RETURN_IF_NOT_EQUAL(condition1->conditiontype, condition2->conditiontype);
	ZBX_RETURN_IF_NOT_EQUAL(condition1->op, condition2->op);

	if (0 != (ret = strcmp(condition1->value, condition2->value)))
		return ret;

	if (0 != (ret = strcmp(condition1->value2, condition2->value2)))
		return ret;

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: generate hash based on condition values                           *
 *                                                                            *
 * Parameters: data - [IN] condition structure                                *
 *                                                                            *
 * Return value: hash is generated                                            *
 *                                                                            *
 ******************************************************************************/
static zbx_hash_t	uniq_conditions_hash_func(const void *data)
{
	const zbx_condition_t	*condition = (const zbx_condition_t *)data;
	zbx_hash_t		hash;

	hash = ZBX_DEFAULT_STRING_HASH_ALGO(condition->value, strlen(condition->value), ZBX_DEFAULT_HASH_SEED);
	hash = ZBX_DEFAULT_STRING_HASH_ALGO(condition->value2, strlen(condition->value2), hash);
	hash = ZBX_DEFAULT_STRING_HASH_ALGO((char *)&condition->conditiontype, 1, hash);
	hash = ZBX_DEFAULT_STRING_HASH_ALGO((char *)&condition->op, 1, hash);

	return hash;
}

/******************************************************************************
 *                                                                            *
 * Purpose: add events that have escalation possible and skip others, also    *
 *          adds according to source                                          *
 *                                                                            *
 * Parameters: events       - [IN] events to apply actions for                *
 *             events_num   - [IN] number of events                           *
 *             esc_events   - [OUT] events that need condition checks         *
 *                                                                            *
 ******************************************************************************/
static void	get_escalation_events(const zbx_vector_ptr_t *events, zbx_vector_ptr_t *esc_events)
{
	const DB_EVENT	*event;
	int		i;

	for (i = 0; i < events->values_num; i++)
	{
		event = (DB_EVENT *)events->values[i];
		if (SUCCEED == is_escalation_event(event) && EVENT_SOURCE_COUNT > (size_t)event->source)
			zbx_vector_ptr_append(&esc_events[event->source], (void*)event);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: cleans condition data structure                                   *
 *                                                                            *
 * Parameters: condition - [IN] the condition data to free                    *
 *                                                                            *
 ******************************************************************************/
static void	db_condition_clean(zbx_condition_t *condition)
{
	zbx_free(condition->value2);
	zbx_free(condition->value);
	zbx_vector_uint64_destroy(&condition->eventids);
}

/******************************************************************************
 *                                                                            *
 * Purpose: cleans condition data structures from hashset                     *
 *                                                                            *
 * Parameters: uniq_conditions - [IN] hashset with data structures to clean   *
 *                                                                            *
 ******************************************************************************/
static void	conditions_eval_clean(zbx_hashset_t *uniq_conditions)
{
	zbx_hashset_iter_t	iter;
	zbx_condition_t		*condition;

	zbx_hashset_iter_reset(uniq_conditions, &iter);

	while (NULL != (condition = (zbx_condition_t *)zbx_hashset_iter_next(&iter)))
		db_condition_clean(condition);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees action evaluation data structure                            *
 *                                                                            *
 * Parameters: action - [IN] the action evaluation to free                    *
 *                                                                            *
 ******************************************************************************/
static void	zbx_action_eval_free(zbx_action_eval_t *action)
{
	zbx_free(action->formula);

	zbx_vector_ptr_destroy(&action->conditions);

	zbx_free(action);
}

/******************************************************************************
 *                                                                            *
 * Purpose: make actions to point, to conditions from hashset, where all      *
 *          conditions are unique, this ensures that we don't double check    *
 *          same conditions.                                                  *
 *                                                                            *
 * Parameters: actions         - [IN/OUT] all conditions are added to hashset *
 *                                        then cleaned, actions will now      *
 *                                        point to conditions from hashset.   *
 *                                        for custom expression also          *
 *                                        replaces formula                    *
 *             uniq_conditions - [OUT]    unique conditions that actions      *
 *                                        point to (several sources)          *
 *                                                                            *
 * Comments: The returned conditions must be freed with                       *
 *           conditions_eval_clean() function later.                          *
 *                                                                            *
 ******************************************************************************/
static void	prepare_actions_conditions_eval(zbx_vector_ptr_t *actions, zbx_hashset_t *uniq_conditions)
{
	int	i, j;

	for (i = 0; i < actions->values_num; i++)
	{
		zbx_action_eval_t	*action = actions->values[i];

		for (j = 0; j < action->conditions.values_num; j++)
		{
			zbx_condition_t	*uniq_condition = NULL, *condition = action->conditions.values[j];

			if (EVENT_SOURCE_COUNT <= action->eventsource)
			{
				db_condition_clean(condition);
			}
			else if (NULL == (uniq_condition = zbx_hashset_search(&uniq_conditions[action->eventsource],
					condition)))
			{
				uniq_condition = zbx_hashset_insert(&uniq_conditions[action->eventsource],
						condition, sizeof(zbx_condition_t));
			}
			else
			{
				if (CONDITION_EVAL_TYPE_EXPRESSION == action->evaltype)
				{
					char	search[ZBX_MAX_UINT64_LEN + 2];
					char	replace[ZBX_MAX_UINT64_LEN + 2];
					char	*old_formula;

					zbx_snprintf(search, sizeof(search), "{" ZBX_FS_UI64 "}",
							condition->conditionid);
					zbx_snprintf(replace, sizeof(replace), "{" ZBX_FS_UI64 "}",
							uniq_condition->conditionid);

					old_formula = action->formula;
					action->formula = string_replace(action->formula, search, replace);
					zbx_free(old_formula);
				}

				db_condition_clean(condition);
			}

			zbx_free(action->conditions.values[j]);
			action->conditions.values[j] = uniq_condition;
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: process all actions of each event in a list                       *
 *                                                                            *
 * Parameters: events        - [IN] events to apply actions for               *
 *             closed_events - [IN] a vector of closed event data -           *
 *                                  (PROBLEM eventid, OK eventid) pairs.      *
 *                                                                            *
 ******************************************************************************/
void	process_actions(const zbx_vector_ptr_t *events, const zbx_vector_uint64_pair_t *closed_events)
{
	int				i;
	zbx_vector_ptr_t		actions;
	zbx_vector_ptr_t 		new_escalations;
	zbx_vector_uint64_pair_t	rec_escalations;
	zbx_hashset_t			uniq_conditions[EVENT_SOURCE_COUNT];
	zbx_vector_ptr_t		esc_events[EVENT_SOURCE_COUNT];
	zbx_hashset_iter_t		iter;
	zbx_condition_t			*condition;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() events_num:" ZBX_FS_SIZE_T, __func__, (zbx_fs_size_t)events->values_num);

	zbx_vector_ptr_create(&new_escalations);
	zbx_vector_uint64_pair_create(&rec_escalations);

	for (i = 0; i < EVENT_SOURCE_COUNT; i++)
	{
		zbx_hashset_create(&uniq_conditions[i], 0, uniq_conditions_hash_func, uniq_conditions_compare_func);
		zbx_vector_ptr_create(&esc_events[i]);
	}

	zbx_vector_ptr_create(&actions);
	zbx_dc_get_actions_eval(&actions, ZBX_ACTION_OPCLASS_NORMAL | ZBX_ACTION_OPCLASS_RECOVERY);
	prepare_actions_conditions_eval(&actions, uniq_conditions);
	get_escalation_events(events, esc_events);

	for (i = 0; i < EVENT_SOURCE_COUNT; i++)
	{
		if (0 == esc_events[i].values_num)
			continue;

		zbx_vector_ptr_sort(&esc_events[i], compare_events);

		zbx_hashset_iter_reset(&uniq_conditions[i], &iter);

		while (NULL != (condition = (zbx_condition_t *)zbx_hashset_iter_next(&iter)))
			check_events_condition(&esc_events[i], i, condition);
	}

	/* 1. All event sources: match PROBLEM events to action conditions, add them to 'new_escalations' list.      */
	/* 2. EVENT_SOURCE_DISCOVERY, EVENT_SOURCE_AUTOREGISTRATION: execute operations (except command and message  */
	/*    operations) for events that match action conditions.                                                   */
	for (i = 0; i < events->values_num; i++)
	{
		int		j;
		const DB_EVENT	*event;

		if (FAIL == is_escalation_event((event = (const DB_EVENT *)events->values[i])))
			continue;

		for (j = 0; j < actions.values_num; j++)
		{
			zbx_action_eval_t	*action = (zbx_action_eval_t *)actions.values[j];

			if (action->eventsource != event->source)
				continue;

			if (SUCCEED == check_action_conditions(event->eventid, action))
			{
				zbx_escalation_new_t	*new_escalation;

				/* command and message operations handled by escalators even for    */
				/* EVENT_SOURCE_DISCOVERY and EVENT_SOURCE_AUTOREGISTRATION events  */
				new_escalation = (zbx_escalation_new_t *)zbx_malloc(NULL, sizeof(zbx_escalation_new_t));
				new_escalation->actionid = action->actionid;
				new_escalation->event = event;
				zbx_vector_ptr_append(&new_escalations, new_escalation);

				if (EVENT_SOURCE_DISCOVERY == event->source ||
						EVENT_SOURCE_AUTOREGISTRATION == event->source)
				{
					execute_operations(event, action->actionid);
				}
			}
		}
	}

	for (i = 0; i < EVENT_SOURCE_COUNT; i++)
	{
		zbx_vector_ptr_destroy(&esc_events[i]);
		conditions_eval_clean(&uniq_conditions[i]);
		zbx_hashset_destroy(&uniq_conditions[i]);
	}

	zbx_vector_ptr_clear_ext(&actions, (zbx_clean_func_t)zbx_action_eval_free);
	zbx_vector_ptr_destroy(&actions);

	/* 3. Find recovered escalations and store escalationids in 'rec_escalation' by OK eventids. */
	if (0 != closed_events->values_num)
	{
		char			*sql = NULL;
		size_t			sql_alloc = 0, sql_offset = 0;
		zbx_vector_uint64_t	eventids;
		DB_ROW			row;
		DB_RESULT		result;
		int			j, index;

		zbx_vector_uint64_create(&eventids);

		/* 3.1. Store PROBLEM eventids of recovered events in 'eventids'. */
		for (j = 0; j < closed_events->values_num; j++)
			zbx_vector_uint64_append(&eventids, closed_events->values[j].first);

		/* 3.2. Select escalations that must be recovered. */
		zbx_vector_uint64_sort(&eventids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				"select eventid,escalationid"
				" from escalations"
				" where");

		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "eventid", eventids.values, eventids.values_num);
		result = DBselect("%s", sql);

		zbx_vector_uint64_pair_reserve(&rec_escalations, eventids.values_num);

		/* 3.3. Store the escalationids corresponding to the OK events in 'rec_escalations'. */
		while (NULL != (row = DBfetch(result)))
		{
			zbx_uint64_pair_t	pair;

			ZBX_STR2UINT64(pair.first, row[0]);

			if (FAIL == (index = zbx_vector_uint64_pair_bsearch(closed_events, pair,
					ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
			{
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
			}

			pair.second = closed_events->values[index].second;
			ZBX_DBROW2UINT64(pair.first, row[1]);
			zbx_vector_uint64_pair_append(&rec_escalations, pair);
		}

		DBfree_result(result);
		zbx_free(sql);
		zbx_vector_uint64_destroy(&eventids);
	}

	/* 4. Create new escalations in DB. */
	if (0 != new_escalations.values_num)
	{
		zbx_db_insert_t	db_insert;
		int		j;

		zbx_db_insert_prepare(&db_insert, "escalations", "escalationid", "actionid", "status", "triggerid",
					"itemid", "eventid", "r_eventid", "acknowledgeid", NULL);

		for (j = 0; j < new_escalations.values_num; j++)
		{
			zbx_uint64_t		triggerid = 0, itemid = 0;
			zbx_escalation_new_t	*new_escalation;

			new_escalation = (zbx_escalation_new_t *)new_escalations.values[j];

			switch (new_escalation->event->object)
			{
				case EVENT_OBJECT_TRIGGER:
					triggerid = new_escalation->event->objectid;
					break;
				case EVENT_OBJECT_ITEM:
				case EVENT_OBJECT_LLDRULE:
					itemid = new_escalation->event->objectid;
					break;
			}

			zbx_db_insert_add_values(&db_insert, __UINT64_C(0), new_escalation->actionid,
					(int)ESCALATION_STATUS_ACTIVE, triggerid, itemid,
					new_escalation->event->eventid, __UINT64_C(0), __UINT64_C(0));

			zbx_free(new_escalation);
		}

		zbx_db_insert_autoincrement(&db_insert, "escalationid");
		zbx_db_insert_execute(&db_insert);
		zbx_db_insert_clean(&db_insert);
	}

	/* 5. Modify recovered escalations in DB. */
	if (0 != rec_escalations.values_num)
	{
		char	*sql = NULL;
		size_t	sql_alloc = 0, sql_offset = 0;
		int	j;

		zbx_vector_uint64_pair_sort(&rec_escalations, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

		for (j = 0; j < rec_escalations.values_num; j++)
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
					"update escalations set r_eventid=" ZBX_FS_UI64 ",nextcheck=0"
					" where escalationid=" ZBX_FS_UI64 ";\n",
					rec_escalations.values[j].second, rec_escalations.values[j].first);

			DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
		}

		DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

		if (16 < sql_offset)	/* in ORACLE always present begin..end; */
			DBexecute("%s", sql);

		zbx_free(sql);
	}

	zbx_vector_uint64_pair_destroy(&rec_escalations);
	zbx_vector_ptr_destroy(&new_escalations);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: process actions for each acknowledgment in the array              *
 *                                                                            *
 * Parameters: event_ack        - [IN] vector for eventid/ackid pairs         *
 *                                                                            *
 ******************************************************************************/
int	process_actions_by_acknowledgments(const zbx_vector_ptr_t *ack_tasks)
{
	zbx_vector_ptr_t	actions;
	zbx_hashset_t		uniq_conditions[EVENT_SOURCE_COUNT];
	int			i, j, k, processed_num = 0, knext = 0;
	zbx_vector_uint64_t	eventids;
	zbx_ack_task_t		*ack_task;
	zbx_vector_ptr_t	ack_escalations, events;
	zbx_ack_escalation_t	*ack_escalation;
	zbx_vector_ptr_t	esc_events[EVENT_SOURCE_COUNT];
	zbx_hashset_iter_t	iter;
	zbx_condition_t		*condition;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_ptr_create(&ack_escalations);

	for (i = 0; i < EVENT_SOURCE_COUNT; i++)
	{
		zbx_hashset_create(&uniq_conditions[i], 0, uniq_conditions_hash_func, uniq_conditions_compare_func);
		zbx_vector_ptr_create(&esc_events[i]);
	}

	zbx_vector_ptr_create(&actions);
	zbx_dc_get_actions_eval(&actions, ZBX_ACTION_OPCLASS_ACKNOWLEDGE);
	prepare_actions_conditions_eval(&actions, uniq_conditions);

	if (0 == actions.values_num)
		goto out;

	zbx_vector_uint64_create(&eventids);

	for (i = 0; i < ack_tasks->values_num; i++)
	{
		ack_task = (zbx_ack_task_t *)ack_tasks->values[i];
		zbx_vector_uint64_append(&eventids, ack_task->eventid);
	}

	zbx_vector_uint64_sort(&eventids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_uniq(&eventids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_vector_ptr_create(&events);

	zbx_db_get_events_by_eventids(&eventids, &events);

	for (i = 0; i < events.values_num; i++)
	{
		DB_EVENT	*event = (DB_EVENT *)events.values[i];

		zbx_vector_ptr_append(&esc_events[event->source], (void*)event);
	}

	for (i = 0; i < EVENT_SOURCE_COUNT; i++)
	{
		if (0 == esc_events[i].values_num)
			continue;

		zbx_vector_ptr_sort(&esc_events[i], compare_events);

		zbx_hashset_iter_reset(&uniq_conditions[i], &iter);

		while (NULL != (condition = (zbx_condition_t *)zbx_hashset_iter_next(&iter)))
			check_events_condition(&esc_events[i], i, condition);
	}

	for (i = 0; i < eventids.values_num; i++)
	{
		int 		kcurr = knext;
		DB_EVENT	*event = (DB_EVENT *)events.values[i];

		while (knext < ack_tasks->values_num)
		{
			ack_task = (zbx_ack_task_t *)ack_tasks->values[knext];
			if (ack_task->eventid != event->eventid)
				break;
			knext++;
		}

		if (0 == event->eventid || 0 == event->trigger.triggerid)
			continue;

		for (j = 0; j < actions.values_num; j++)
		{
			zbx_action_eval_t	*action = (zbx_action_eval_t *)actions.values[j];

			if (action->eventsource != event->source)
				continue;

			if (SUCCEED != check_action_conditions(event->eventid, action))
				continue;

			for (k = kcurr; k < knext; k++)
			{
				ack_task = (zbx_ack_task_t *)ack_tasks->values[k];

				ack_escalation = (zbx_ack_escalation_t *)zbx_malloc(NULL, sizeof(zbx_ack_escalation_t));
				ack_escalation->taskid = ack_task->taskid;
				ack_escalation->acknowledgeid = ack_task->acknowledgeid;
				ack_escalation->actionid = action->actionid;
				ack_escalation->eventid = event->eventid;
				ack_escalation->triggerid = event->trigger.triggerid;
				zbx_vector_ptr_append(&ack_escalations, ack_escalation);
			}
		}
	}

	if (0 != ack_escalations.values_num)
	{
		zbx_db_insert_t	db_insert;

		zbx_db_insert_prepare(&db_insert, "escalations", "escalationid", "actionid", "status", "triggerid",
						"itemid", "eventid", "r_eventid", "acknowledgeid", NULL);

		zbx_vector_ptr_sort(&ack_escalations, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

		for (i = 0; i < ack_escalations.values_num; i++)
		{
			ack_escalation = (zbx_ack_escalation_t *)ack_escalations.values[i];

			zbx_db_insert_add_values(&db_insert, __UINT64_C(0), ack_escalation->actionid,
				(int)ESCALATION_STATUS_ACTIVE, ack_escalation->triggerid, __UINT64_C(0),
				ack_escalation->eventid, __UINT64_C(0), ack_escalation->acknowledgeid);
		}

		zbx_db_insert_autoincrement(&db_insert, "escalationid");
		zbx_db_insert_execute(&db_insert);
		zbx_db_insert_clean(&db_insert);

		processed_num = ack_escalations.values_num;
	}

	zbx_vector_ptr_clear_ext(&events, (zbx_clean_func_t)zbx_db_free_event);
	zbx_vector_ptr_destroy(&events);

	zbx_vector_uint64_destroy(&eventids);
out:
	for (i = 0; i < EVENT_SOURCE_COUNT; i++)
	{
		zbx_vector_ptr_destroy(&esc_events[i]);
		conditions_eval_clean(&uniq_conditions[i]);
		zbx_hashset_destroy(&uniq_conditions[i]);
	}

	zbx_vector_ptr_clear_ext(&actions, (zbx_clean_func_t)zbx_action_eval_free);
	zbx_vector_ptr_destroy(&actions);

	zbx_vector_ptr_clear_ext(&ack_escalations, zbx_ptr_free);
	zbx_vector_ptr_destroy(&ack_escalations);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() processed_num:%d", __func__, processed_num);

	return processed_num;
}

/******************************************************************************
 *                                                                            *
 * Purpose: reads actions from database                                       *
 *                                                                            *
 * Parameters: actionids - [IN] requested action ids                          *
 *             actions   - [OUT] the array of actions                         *
 *                                                                            *
 * Comments: use 'free_db_action' function to release allocated memory        *
 *                                                                            *
 ******************************************************************************/
void	get_db_actions_info(zbx_vector_uint64_t *actionids, zbx_vector_ptr_t *actions)
{
	DB_RESULT	result;
	DB_ROW		row;
	char		*filter = NULL;
	size_t		filter_alloc = 0, filter_offset = 0;
	DB_ACTION	*action;

	zbx_vector_uint64_sort(actionids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_uniq(actionids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	DBadd_condition_alloc(&filter, &filter_alloc, &filter_offset, "actionid", actionids->values,
			actionids->values_num);

	result = DBselect("select actionid,name,status,eventsource,esc_period,pause_suppressed,notify_if_canceled"
				" from actions"
				" where%s order by actionid", filter);

	while (NULL != (row = DBfetch(result)))
	{
		char	*tmp;

		action = (DB_ACTION *)zbx_malloc(NULL, sizeof(DB_ACTION));
		ZBX_STR2UINT64(action->actionid, row[0]);
		ZBX_STR2UCHAR(action->status, row[2]);
		ZBX_STR2UCHAR(action->eventsource, row[3]);

		tmp = zbx_strdup(NULL, row[4]);
		substitute_simple_macros(NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, &tmp,
				MACRO_TYPE_COMMON, NULL, 0);
		if (SUCCEED != is_time_suffix(tmp, &action->esc_period, ZBX_LENGTH_UNLIMITED))
		{
			zabbix_log(LOG_LEVEL_WARNING, "Invalid default operation step duration \"%s\" for action"
					" \"%s\", using default value of 1 hour", tmp, row[1]);
			action->esc_period = SEC_PER_HOUR;
		}
		zbx_free(tmp);

		ZBX_STR2UCHAR(action->pause_suppressed, row[5]);
		ZBX_STR2UCHAR(action->notify_if_canceled, row[6]);
		action->name = zbx_strdup(NULL, row[1]);
		action->recovery = ZBX_ACTION_RECOVERY_NONE;

		zbx_vector_ptr_append(actions, action);
	}
	DBfree_result(result);

	result = DBselect("select actionid from operations where recovery=%d and%s",
			ZBX_OPERATION_MODE_RECOVERY, filter);

	while (NULL != (row = DBfetch(result)))
	{
		zbx_uint64_t	actionid;
		int		index;

		ZBX_STR2UINT64(actionid, row[0]);
		if (FAIL != (index = zbx_vector_ptr_bsearch(actions, &actionid, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
		{
			action = (DB_ACTION *)actions->values[index];
			action->recovery = ZBX_ACTION_RECOVERY_OPERATIONS;
		}
	}
	DBfree_result(result);

	zbx_free(filter);
}

void	free_db_action(DB_ACTION *action)
{
	zbx_free(action->name);
	zbx_free(action);
}
