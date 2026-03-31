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

#include "cep_correlation.h"
#include "cep_task.h"

#include "zbx_trigger_constants.h"
#include "zbxalgo.h"
#include "zbxcalc.h"
#include "zbxcommon.h"
#include "zbxdb.h"
#include "zbxdbhigh.h"
#include "zbxcacheconfig.h"
#include "zbxexpr.h"
#include "zbxnum.h"
#include "zbxstr.h"
#include "zbxvariant.h"
#include "../events/events.h"

typedef enum
{
	CORRELATION_NEW_EVENTS,
	CORRELATION_OLD_EVENTS
}
zbx_correlation_scope_t;

typedef enum
{
	CORRELATION_MATCH = 0,
	CORRELATION_NO_MATCH,
	CORRELATION_MAY_MATCH
}
zbx_correlation_match_result_t;

typedef struct
{
	zbx_uint64_t	eventid;
	zbx_uint64_t	objectid;
	zbx_uint64_t	correlationid;
	int		clock;
	int		ns;
}
zbx_correlation_result_t;

ZBX_VECTOR_DECL(correlation_result, zbx_correlation_result_t)
ZBX_VECTOR_IMPL(correlation_result, zbx_correlation_result_t)

/******************************************************************************
 *                                                                            *
 * Purpose: get correlation condition by ID                                   *
 *                                                                            *
 * Parameters: correlation - [IN] correlation rule containing the condition   *
 *             conditionid - [IN] ID of the correlation condition to find     *
 *                                                                            *
 * Return value: pointer to the correlation condition if found,               *
 *               NULL otherwise                                               *
 *                                                                            *
 ******************************************************************************/
static const zbx_corr_condition_t	*cep_correlation_condition(const zbx_correlation_t *correlation,
		zbx_uint64_t conditionid)
{
	for (int i = 0; i < correlation->conditions.values_num; i++)
	{
		if (correlation->conditions.values[i]->corr_conditionid == conditionid)
			return correlation->conditions.values[i];
	}

	return NULL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if the event matches the specified host group               *
 *          (including nested groups)                                         *
 *                                                                            *
 * Parameters: event   - [IN] new event to check                              *
 *             groupid - [IN] group id to match                               *
 *             db      - [IN] database connection                             *
 *                                                                            *
 * Return value: SUCCEED - the group matches                                  *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	correlation_match_event_hostgroup(const zbx_db_event *event, zbx_uint64_t groupid, zbx_dbconn_t *db)
{
	zbx_db_result_t		result;
	int			ret = FAIL;
	zbx_vector_uint64_t	groupids;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;

	zbx_vector_uint64_create(&groupids);
	zbx_dc_get_nested_hostgroupids(&groupid, 1, &groupids);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select hg.groupid"
				" from hstgrp g,hosts_groups hg,items i,functions f"
				" where f.triggerid=" ZBX_FS_UI64
				" and i.itemid=f.itemid"
				" and hg.hostid=i.hostid"
				" and",
				event->objectid);

	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "hg.groupid", groupids.values,
			groupids.values_num);

	result = zbx_dbconn_select(db, "%s", sql);

	if (NULL != zbx_db_fetch(result))
		ret = SUCCEED;

	zbx_db_free_result(result);
	zbx_free(sql);
	zbx_vector_uint64_destroy(&groupids);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if the correlation condition matches the new event          *
 *                                                                            *
 * Parameters: condition - [IN] correlation condition to check                *
 *             event     - [IN] new event to match                            *
 *             old_value - [IN] SUCCEED - old event conditions may            *
 *                                        match event                         *
 *                              FAIL    - old event conditions never          *
 *                                        match event                         *
 *             db         - [IN] database connection                          *
 *                                                                            *
 * Return value: "1"            - correlation rule match event                *
 *               "0"            - correlation rule doesn't match event        *
 *               "ZBX_UNKNOWN " - correlation rule might match                *
 *                                depending on old events                     *
 *                                                                            *
 ******************************************************************************/
static const char	*correlation_condition_match_new_event(const zbx_corr_condition_t *condition,
		const zbx_db_event *event, int old_value, zbx_dbconn_t *db)
{
	/* return SUCCEED for conditions using old events */
	switch (condition->type)
	{
		case ZBX_CORR_CONDITION_OLD_EVENT_TAG:
		case ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE:
			return (SUCCEED == old_value) ? ZBX_UNKNOWN_STR "0" : "0";
	}

	int	ret;

	switch (condition->type)
	{
		case ZBX_CORR_CONDITION_NEW_EVENT_TAG:
			for (int i = 0; i < event->tags.values_num; i++)
			{
				const zbx_tag_t	*tag = event->tags.values[i];

				if (0 == strcmp(tag->tag, condition->data.tag.tag))
					return "1";
			}
			break;

		case ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE:
			for (int i = 0; i < event->tags.values_num; i++)
			{
				const zbx_corr_condition_tag_value_t	*cond = &condition->data.tag_value;
				const zbx_tag_t				*tag = event->tags.values[i];

				if (0 == strcmp(tag->tag, cond->tag) &&
					SUCCEED == zbx_strmatch_condition(tag->value, cond->value, cond->op))
				{
					return "1";
				}
			}
			break;

		case ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP:
			ret =  correlation_match_event_hostgroup(event, condition->data.group.groupid, db);

			if (ZBX_CONDITION_OPERATOR_NOT_EQUAL == condition->data.group.op)
				return (SUCCEED == ret ? "0" : "1");

			return (SUCCEED == ret ? "1" : "0");

		case ZBX_CORR_CONDITION_EVENT_TAG_PAIR:
			for (int i = 0; i < event->tags.values_num; i++)
			{
				const zbx_tag_t	*tag = event->tags.values[i];

				if (0 == strcmp(tag->tag, condition->data.tag_pair.newtag))
					return (SUCCEED == old_value) ? ZBX_UNKNOWN_STR "0" : "0";
			}
			break;
	}

	return "0";
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if the correlation rule might match the new event           *
 *                                                                            *
 * Parameters: correlation - [IN] correlation rule to check                   *
 *             event       - [IN] new event to match                          *
 *             old_value   - [IN] SUCCEED - old event conditions may          *
 *                                          match event                       *
 *                                FAIL    - old event conditions never        *
 *                                          match event                       *
 *             db          - [IN] database connection                         *
 *                                                                            *
 * Return value: CORRELATION_MATCH     - correlation rule match               *
 *               CORRELATION_MAY_MATCH - correlation rule might match         *
 *                                       depending on old events              *
 *               CORRELATION_NO_MATCH  - correlation rule doesn't match       *
 *                                                                            *
 ******************************************************************************/
static zbx_correlation_match_result_t	correlation_match_new_event(const zbx_correlation_t *correlation,
		const zbx_db_event *event, int old_value, zbx_dbconn_t *db)
{
	char				*expression, error[256];
	const char			*value;
	zbx_token_t			token;
	int				pos = 0;
	zbx_uint64_t			conditionid;
	zbx_strloc_t			*loc;
	double				result;
	zbx_correlation_match_result_t	ret = CORRELATION_NO_MATCH;

	if ('\0' == *correlation->formula)
		return CORRELATION_MAY_MATCH;

	expression = zbx_strdup(NULL, correlation->formula);

	for (; SUCCEED == zbx_token_find(expression, pos, &token, ZBX_TOKEN_SEARCH_BASIC); pos++)
	{
		const zbx_corr_condition_t	*condition;

		if (ZBX_TOKEN_OBJECTID != token.type)
			continue;

		loc = &token.data.objectid.name;

		if (SUCCEED != zbx_is_uint64_n(expression + loc->l, loc->r - loc->l + 1, &conditionid))
			continue;

		if (NULL == (condition = cep_correlation_condition(correlation, conditionid)))
			goto out;

		value = correlation_condition_match_new_event(condition, event, old_value, db);

		zbx_replace_string(&expression, token.loc.l, &token.loc.r, value);
		pos = token.loc.r;
	}

	if (SUCCEED == zbx_evaluate_unknown(expression, &result, error, sizeof(error)))
	{
		if (result == ZBX_UNKNOWN)
			ret = CORRELATION_MAY_MATCH;
		else if (SUCCEED == zbx_double_compare(result, 1))
			ret = CORRELATION_MATCH;
	}

out:
	zbx_free(expression);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if correlation has operations to change old events          *
 *                                                                            *
 * Parameters: correlation - [IN] correlation to check                        *
 *                                                                            *
 * Return value: SUCCEED - correlation has operations to change old events    *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	correlation_has_old_event_operation(const zbx_correlation_t *correlation)
{
	if (0 != (correlation->operations & CORRELATION_OP_CLOSE_OLD))
		return SUCCEED;

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: add a correlation result entry to the result set                  *
 *                                                                            *
 * Parameters: results       - [IN/OUT] hash set to store correlation results *
 *             correlationid - [IN] ID of the correlation rule                *
 *             eventid       - [IN] ID of the correlated event                *
 *             objectid      - [IN] ID of the target object (for action)      *
 *             clock         - [IN] event timestamp (seconds)                 *
 *             ns            - [IN] event timestamp (nanoseconds)             *
 *                                                                            *
 ******************************************************************************/
static void	correlation_add_result(zbx_hashset_t *results, zbx_uint64_t correlationid, zbx_uint64_t eventid,
		zbx_uint64_t objectid, int clock, int ns)
{
	zbx_correlation_result_t	result_local = {
		.correlationid = correlationid,
		.eventid = eventid,
		.objectid = objectid,
		.clock = clock,
		.ns = ns
	};

	zbx_hashset_insert(results, &result_local, sizeof(result_local));
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute correlation operations for the new event and matched      *
 *          old eventid                                                       *
 *                                                                            *
 * Parameters: correlation  - [IN] correlation to execute                     *
 *             event        - [IN/OUT] new event                              *
 *             old_eventid  - [IN]                                            *
 *             old_objectid - [IN]                                            *
 *             results      - [IN] correlation results (closed triggers)      *
 *                                                                            *
 ******************************************************************************/
static void	correlation_execute_operations(const zbx_correlation_t *correlation, const zbx_db_event *event,
		zbx_uint64_t old_eventid, zbx_uint64_t old_objectid, zbx_hashset_t *results)
{
	if (0 != (correlation->operations & CORRELATION_OP_CLOSE_NEW))
	{
		correlation_add_result(results, correlation->correlationid, event->eventid, event->objectid,
				event->clock, event->ns);
	}

	if (0 != (correlation->operations & CORRELATION_OP_CLOSE_OLD))
	{
		correlation_add_result(results, correlation->correlationid, old_eventid, old_objectid, event->clock,
				event->ns);
	}
}

/***********************************************************************************
 *                                                                                 *
 * Purpose: add sql statement to match tag according to the defined                *
 *          matching operation                                                     *
 *                                                                                 *
 * Parameters: sql         - [IN/OUT]                                              *
 *             sql_alloc   - [IN/OUT]                                              *
 *             sql_offset  - [IN/OUT]                                              *
 *             tag         - [IN] tag to match                                     *
 *             value       - [IN] tag value to match                               *
 *             op          - [IN] matching operation (ZBX_CONDITION_OPERATOR_)     *
 *                                                                                 *
 ***********************************************************************************/
static void	correlation_condition_add_tag_match(char **sql, size_t *sql_alloc, size_t *sql_offset, const char *tag,
		const char *value, unsigned char op)
{
	char	*tag_esc, *value_esc;

	tag_esc = zbx_db_dyn_escape_string(tag);
	value_esc = zbx_db_dyn_escape_string(value);

	switch (op)
	{
		case ZBX_CONDITION_OPERATOR_NOT_EQUAL:
		case ZBX_CONDITION_OPERATOR_NOT_LIKE:
			zbx_strcpy_alloc(sql, sql_alloc, sql_offset, "not ");
			break;
	}

	zbx_strcpy_alloc(sql, sql_alloc, sql_offset,
			"exists (select null from problem_tag pt where p.eventid=pt.eventid and ");

	switch (op)
	{
		case ZBX_CONDITION_OPERATOR_EQUAL:
		case ZBX_CONDITION_OPERATOR_NOT_EQUAL:
			zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "pt.tag='%s' and pt.value" ZBX_SQL_STRCMP,
					tag_esc, ZBX_SQL_STRVAL_EQ(value_esc));
			break;
		case ZBX_CONDITION_OPERATOR_LIKE:
		case ZBX_CONDITION_OPERATOR_NOT_LIKE:
			zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "pt.tag='%s' and pt.value like '%%%s%%'",
					tag_esc, value_esc);
			break;
	}

	zbx_chrcpy_alloc(sql, sql_alloc, sql_offset, ')');

	zbx_free(value_esc);
	zbx_free(tag_esc);
}

/******************************************************************************
 *                                                                            *
 * Purpose: create sql filter to find events matching a correlation           *
 *          condition                                                         *
 *                                                                            *
 * Parameters: condition - [IN] correlation condition to match                *
 *             event     - [IN] new event to match                            *
*              db        - [IN] database connection                           *
 *                                                                            *
 * Return value: the created filter or NULL                                   *
 *                                                                            *
 ******************************************************************************/
static char	*correlation_condition_get_event_filter(const zbx_corr_condition_t *condition,
		const zbx_db_event *event, zbx_dbconn_t *db)
{
	int			i;
	zbx_tag_t		*tag;
	char			*tag_esc, *filter = NULL;
	size_t			filter_alloc = 0, filter_offset = 0;
	zbx_vector_str_t	values;

	/* replace new event dependent condition with precalculated value */
	switch (condition->type)
	{
		case ZBX_CORR_CONDITION_NEW_EVENT_TAG:
		case ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE:
		case ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP:
			return zbx_dsprintf(NULL, "%s=1",
					correlation_condition_match_new_event(condition, event, SUCCEED, db));
	}

	/* replace old event dependent condition with sql filter on problem_tag pt table */
	switch (condition->type)
	{
		case ZBX_CORR_CONDITION_OLD_EVENT_TAG:
			tag_esc = zbx_db_dyn_escape_string(condition->data.tag.tag);
			zbx_snprintf_alloc(&filter, &filter_alloc, &filter_offset,
					"exists (select null from problem_tag pt"
						" where p.eventid=pt.eventid"
							" and pt.tag='%s')",
					tag_esc);
			zbx_free(tag_esc);
			return filter;

		case ZBX_CORR_CONDITION_EVENT_TAG_PAIR:
			zbx_vector_str_create(&values);

			for (i = 0; i < event->tags.values_num; i++)
			{
				tag = event->tags.values[i];
				if (0 == strcmp(tag->tag, condition->data.tag_pair.newtag))
					zbx_vector_str_append(&values, zbx_strdup(NULL, tag->value));
			}

			if (0 == values.values_num)
			{
				/* no new tag found, substitute condition with failure expression */
				filter = zbx_strdup(NULL, "1=0");
			}
			else
			{
				tag_esc = zbx_db_dyn_escape_string(condition->data.tag_pair.oldtag);

				zbx_snprintf_alloc(&filter, &filter_alloc, &filter_offset,
						"exists (select null from problem_tag pt"
							" where p.eventid=pt.eventid"
								" and pt.tag='%s'"
								" and",
						tag_esc);

				zbx_db_add_str_condition_alloc(&filter, &filter_alloc, &filter_offset, "pt.value",
						(const char **)values.values, values.values_num);

				zbx_chrcpy_alloc(&filter, &filter_alloc, &filter_offset, ')');

				zbx_free(tag_esc);
				zbx_vector_str_clear_ext(&values, zbx_str_free);
			}

			zbx_vector_str_destroy(&values);
			return filter;

		case ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE:
			correlation_condition_add_tag_match(&filter, &filter_alloc, &filter_offset,
					condition->data.tag_value.tag, condition->data.tag_value.value,
					condition->data.tag_value.op);
			return filter;
	}

	return NULL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: add sql statement to filter out correlation conditions and        *
 *          matching events                                                   *
 *                                                                            *
 * Parameters: sql         - [IN/OUT]                                         *
 *             sql_alloc   - [IN/OUT]                                         *
 *             sql_offset  - [IN/OUT]                                         *
 *             correlation - [IN] correlation rule to match                   *
 *             event       - [IN] new event to match                          *
 *             db          - [IN] database connection                         *
 *                                                                            *
 * Return value: SUCCEED - filter was added successfully                      *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	correlation_add_event_filter(char **sql, size_t *sql_alloc, size_t *sql_offset,
		const zbx_correlation_t *correlation, const zbx_db_event *event, zbx_dbconn_t *db)
{
	char			*expression, *filter;
	zbx_token_t		token;
	int			pos = 0, ret = FAIL;
	zbx_uint64_t		conditionid;
	zbx_strloc_t		*loc;
	const zbx_corr_condition_t	*condition;

	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "c.correlationid=" ZBX_FS_UI64, correlation->correlationid);

	expression = zbx_strdup(NULL, correlation->formula);

	for (; SUCCEED == zbx_token_find(expression, pos, &token, ZBX_TOKEN_SEARCH_BASIC); pos++)
	{
		if (ZBX_TOKEN_OBJECTID != token.type)
			continue;

		loc = &token.data.objectid.name;

		if (SUCCEED != zbx_is_uint64_n(expression + loc->l, loc->r - loc->l + 1, &conditionid))
			continue;

		if (NULL == (condition = cep_correlation_condition(correlation, conditionid)))
			goto out;

		if (NULL == (filter = correlation_condition_get_event_filter(condition, event, db)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			goto out;
		}

		zbx_replace_string(&expression, token.loc.l, &token.loc.r, filter);
		pos = token.loc.r;
		zbx_free(filter);
	}

	if ('\0' != *expression)
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, " and (%s)", expression);

	ret = SUCCEED;
out:
	zbx_free(expression);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: create a recovery (OK) event to close problem event               *
 *                                                                            *
 * Parameters: problem - [IN] problem event to close                          *
 *                                                                            *
 * Return value: pointer to a newly allocated OK event                        *
 *                                                                            *
 ******************************************************************************/
static zbx_db_event	*cep_create_close_event(const zbx_db_event *problem)
{
	zbx_db_event	*ok;

	ok = (zbx_db_event *)zbx_malloc(NULL, sizeof(zbx_db_event));
	memset(ok, 0, sizeof(zbx_db_event));
	ok->clock = problem->clock;
	ok->ns = problem->ns;
	ok->source = problem->source;
	ok->object = problem->object;
	ok->objectid = problem->objectid;
	ok->name = zbx_strdup(NULL, problem->name);
	ok->value = TRIGGER_VALUE_OK;

	zbx_vector_tags_ptr_create(&ok->tags);
	if (0 != problem->tags.values_num)
	{
		zbx_vector_tags_ptr_reserve(&ok->tags, (size_t)problem->tags.values_num);
		for (int i = 0; i < problem->tags.values_num; i++)
		{
			zbx_tag_t	*tag;

			tag = (zbx_tag_t *)zbx_malloc(NULL, sizeof(zbx_tag_t));
			tag->tag = zbx_strdup(NULL, problem->tags.values[i]->tag);
			tag->value = zbx_strdup(NULL, problem->tags.values[i]->value);
			zbx_vector_tags_ptr_append(&ok->tags, tag);
		}
	}

	return ok;
}

/******************************************************************************
 *                                                                            *
 * Purpose: add a CEP task to close a newly created problem event             *
 *                                                                            *
 * Parameters: tasks    - [IN/OUT] vector of CEP tasks                        *
 *             db_event - [IN] problem event to close                         *
 *             result   - [IN] correlation result describing the problem      *
 *                                                                            *
 * Return value: none                                                         *
 *                                                                            *
 ******************************************************************************/
static void	correlation_add_close_new_task(zbx_vector_mw_task_ptr_t *tasks, const zbx_db_event *db_event,
		const zbx_correlation_result_t *result)
{
	zbx_mw_task_t	*task;

	task = cep_create_task_close_event(cep_create_close_event(db_event), result->eventid, 0, result->correlationid);

	/* 'close new' operations must skip actions for the problem and generated ok event */
	((zbx_cep_task_close_event_t *)task)->parent.action_state = CEP_ACTION_DISABLED;

	zbx_vector_mw_task_ptr_append(tasks, task);
}

/******************************************************************************
 *                                                                            *
 * Purpose: add CEP tasks to close matched old problem events                 *
 *                                                                            *
 * Parameters: tasks   - [IN/OUT] vector of CEP tasks                         *
 *             results - [IN]     hash set of correlation results for old     *
 *                               problem events                               *
 *                                                                            *
 * Return value: none                                                         *
 *                                                                            *
 ******************************************************************************/
static void	correlation_add_close_old_tasks(zbx_vector_mw_task_ptr_t *tasks, zbx_hashset_t *results)
{
	zbx_mw_task_t			*task;
	zbx_vector_uint64_t		triggerids;
	zbx_hashset_iter_t		iter;
	zbx_correlation_result_t	*result;
	zbx_dc_trigger_t		*triggers;
	int				*errcodes;

	zbx_vector_uint64_create(&triggerids);

	zbx_hashset_iter_reset(results, &iter);
	while (NULL != (result = (zbx_correlation_result_t *)zbx_hashset_iter_next(&iter)))
		zbx_vector_uint64_append(&triggerids, result->objectid);

	zbx_vector_uint64_sort(&triggerids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_uniq(&triggerids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	triggers = (zbx_dc_trigger_t *)zbx_malloc(NULL, sizeof(zbx_dc_trigger_t) * triggerids.values_num);
	errcodes = (int *)zbx_malloc(NULL, sizeof(int) * triggerids.values_num);

	zbx_dc_config_get_triggers_by_triggerids(triggers, triggerids.values, errcodes, (size_t)triggerids.values_num);

	zbx_hashset_iter_reset(results, &iter);
	while (NULL != (result = (zbx_correlation_result_t *)zbx_hashset_iter_next(&iter)))
	{
		zbx_db_event		*db_event;
		int			index;

		if (FAIL == (index = zbx_vector_uint64_bsearch(&triggerids, result->objectid,
				ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		if (SUCCEED != errcodes[index])
			continue;

		db_event = zbx_create_trigger_event(&triggers[index], result->clock, result->ns, TRIGGER_VALUE_OK);
		task = cep_create_task_close_event(db_event, result->eventid, 0, result->correlationid);
		zbx_vector_mw_task_ptr_append(tasks, task);
	}

	zbx_dc_config_clean_triggers(triggers, errcodes, triggerids.values_num);
	zbx_free(triggers);
	zbx_free(errcodes);
	zbx_vector_uint64_destroy(&triggerids);
}

/******************************************************************************
 *                                                                            *
 * Purpose: correlate a database event                                        *
 *                                                                            *
 * Parameters: db_event - [IN]     event to correlate                         *
 *             dbpool   - [IN/OUT] database connection pool                   *
 *             tasks    - [OUT]    vector of CEP tasks created by correlation *
 *                                                                            *
 * Return value: bitmask of correlation results (see CORRELATION_RESULT_*     *
 *                defines)                                                    *
 *                                                                            *
 ******************************************************************************/
int	cep_correlate_db_event(const zbx_db_event *db_event, zbx_dbconn_pool_t *dbpool,
		zbx_vector_mw_task_ptr_t *tasks)
{
	int				op_result = CORRELATION_RESULT_NONE;
	zbx_correlation_t		*correlation;
	char				*sql = NULL;
	const char			*delim = "";
	size_t				sql_alloc = 0, sql_offset = 0;
	zbx_uint64_t			eventid, objectid;
	zbx_vector_correlation_ptr_t	*rules, corr_old, corr_new;
	zbx_dbconn_t			*db;
	zbx_hashset_t			results;
	zbx_correlation_cache_handle_t	handle;

	if (NULL == (handle = zbx_correlation_cache_open()))
		return op_result;

	zbx_hashset_create(&results, 100, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_vector_correlation_ptr_create(&corr_old);
	zbx_vector_correlation_ptr_create(&corr_new);

	rules = zbx_correlation_cache_get_correlations(handle);

	db = zbx_dbconn_pool_acquire_connection(dbpool);

	for (int i = 0; i < rules->values_num; i++)
	{
		zbx_correlation_scope_t	scope;

		correlation = rules->values[i];

		switch (correlation_match_new_event(correlation, db_event, SUCCEED, db))
		{
			case CORRELATION_MATCH:
				if (SUCCEED == correlation_has_old_event_operation(correlation))
					scope = CORRELATION_OLD_EVENTS;
				else
					scope = CORRELATION_NEW_EVENTS;
				break;
			case CORRELATION_NO_MATCH:	/* proceed with next rule */
				continue;
			case CORRELATION_MAY_MATCH:	/* might match depending on old events */
				scope = CORRELATION_OLD_EVENTS;
				break;
			default:
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
		}

		if (CORRELATION_OLD_EVENTS == scope)
			zbx_vector_correlation_ptr_append(&corr_old, correlation);
		else
			zbx_vector_correlation_ptr_append(&corr_new, correlation);
	}

	if (0 != corr_new.values_num)
	{
		/* Process correlations that matches new event and does not use or affect old events. */
		/* Those correlations can be executed directly, without checking database.            */
		for (int i = 0; i < corr_new.values_num; i++)
		{
			correlation_execute_operations((zbx_correlation_t *)corr_new.values[i], db_event, 0, 0,
					&results);
		}
	}

	if (0 != corr_old.values_num)
	{
		zbx_db_result_t	result;
		zbx_db_row_t	row;

		/* Process correlations that matches new event and either uses old events in conditions */
		/* or has operations involving old events.                                              */

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select p.eventid,p.objectid,c.correlationid"
								" from correlation c,problem p"
								" where p.r_eventid is null"
								" and p.source=" ZBX_STR(EVENT_SOURCE_TRIGGERS)
								" and (");

		for (int i = 0; i < corr_old.values_num; i++)
		{
			correlation = (zbx_correlation_t *)corr_old.values[i];

			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, delim);
			correlation_add_event_filter(&sql, &sql_alloc, &sql_offset, correlation, db_event, db);
			delim = " or ";
		}

		zbx_chrcpy_alloc(&sql, &sql_alloc, &sql_offset, ')');
		result = zbx_dbconn_select(db, "%s", sql);

		while (NULL != (row = zbx_db_fetch(result)))
		{
			zbx_correlation_t	corr_local;

			int	i;

			ZBX_STR2UINT64(eventid, row[0]);
			ZBX_STR2UINT64(corr_local.correlationid, row[2]);

			if (FAIL == (i = zbx_vector_correlation_ptr_bsearch(&corr_old, &corr_local,
					ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
			{
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
			}

			ZBX_STR2UINT64(objectid, row[1]);

			correlation_execute_operations((zbx_correlation_t *)corr_old.values[i], db_event,
					eventid, objectid, &results);
		}

		zbx_db_free_result(result);
		zbx_free(sql);
	}

	zbx_dbconn_pool_release_connection(dbpool, db);
	zbx_correlation_cache_close(handle);

	/* process 'close new' operation */

	zbx_correlation_result_t	*result;

	if (NULL != (result = (zbx_correlation_result_t *)zbx_hashset_search(&results, &db_event->eventid)))
	{
		op_result |= CORRELATION_RESULT_CLOSE_NEW;
		correlation_add_close_new_task(tasks, db_event, result);
		zbx_hashset_remove_direct(&results, result);
	}

	/* only 'close old' operations have left, process them */
	if (0 != results.num_data)
	{
		op_result |= CORRELATION_RESULT_CLOSE_OLD;
		correlation_add_close_old_tasks(tasks, &results);
	}

	zbx_vector_correlation_ptr_destroy(&corr_new);
	zbx_vector_correlation_ptr_destroy(&corr_old);
	zbx_hashset_destroy(&results);

	return op_result;
}

