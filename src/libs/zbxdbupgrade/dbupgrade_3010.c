/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
#include "dbupgrade.h"
#include "log.h"

/*
 * 3.2 development database patches
 */

#ifndef HAVE_SQLITE3

static int	DBpatch_3010000(void)
{
	return DBdrop_index("history_log", "history_log_2");
}

static int	DBpatch_3010001(void)
{
	return DBdrop_field("history_log", "id");
}

static int	DBpatch_3010002(void)
{
	return DBdrop_index("history_text", "history_text_2");
}

static int	DBpatch_3010003(void)
{
	return DBdrop_field("history_text", "id");
}

static int	DBpatch_3010004(void)
{
	const ZBX_FIELD	field = {"recovery_mode", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("triggers", &field);
}

static int	DBpatch_3010005(void)
{
	const ZBX_FIELD	field = {"recovery_expression", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("triggers", &field);
}

static int	DBpatch_3010006(void)
{
	const ZBX_TABLE table =
			{"trigger_tag", "triggertagid", 0,
				{
					{"triggertagid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"triggerid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"tag", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"value", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_3010007(void)
{
	return DBcreate_index("trigger_tag", "trigger_tag_1", "triggerid", 0);
}

static int	DBpatch_3010008(void)
{
	const ZBX_FIELD	field = {"triggerid", NULL, "triggers", "triggerid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("trigger_tag", 1, &field);
}

static int	DBpatch_3010009(void)
{
	const ZBX_TABLE table =
			{"event_tag", "eventtagid", 0,
				{
					{"eventtagid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"eventid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"tag", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"value", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_3010010(void)
{
	return DBcreate_index("event_tag", "event_tag_1", "eventid", 0);
}

static int	DBpatch_3010011(void)
{
	const ZBX_FIELD	field = {"eventid", NULL, "events", "eventid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("event_tag", 1, &field);
}

static int	DBpatch_3010012(void)
{
	const ZBX_FIELD	field = {"value2", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("conditions", &field);
}

static int	DBpatch_3010013(void)
{
	const ZBX_FIELD	field = {"maintenance_mode", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("actions", &field);
}

static int	DBpatch_3010014(void)
{
	const ZBX_TABLE table =
			{"problem", "eventid", 0,
				{
					{"eventid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"source", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"object", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"objectid", "0", NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_3010015(void)
{
	return DBcreate_index("problem", "problem_1", "source,object,objectid", 0);
}

static int	DBpatch_3010016(void)
{
	const ZBX_FIELD field = {"eventid", NULL, "events", "eventid", 0, ZBX_TYPE_ID, ZBX_NOTNULL,
			ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("problem", 1, &field);
}

static int	DBpatch_3010017(void)
{
	const ZBX_TABLE table =
			{"event_recovery", "eventid", 0,
				{
					{"eventid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"r_eventid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_3010018(void)
{
	return DBcreate_index("event_recovery", "event_recovery_1", "r_eventid", 0);
}

static int	DBpatch_3010019(void)
{
	const ZBX_FIELD field = {"eventid", NULL, "events", "eventid", 0, ZBX_TYPE_ID, ZBX_NOTNULL,
			ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("event_recovery", 1, &field);
}

static int	DBpatch_3010020(void)
{
	const ZBX_FIELD field = {"r_eventid", NULL, "events", "eventid", 0, ZBX_TYPE_ID, ZBX_NOTNULL,
			ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("event_recovery", 2, &field);
}

/* DBpatch_3010021 () */

#define ZBX_OPEN_EVENT_WARNING_NUM	10000000

/* problem eventids by triggerid */
typedef struct
{
	int			source;
	int			object;
	zbx_uint64_t		objectid;
	zbx_vector_uint64_t	eventids;
}
zbx_object_events_t;


/* source events hashset support */
static zbx_hash_t	DBpatch_3010021_trigger_events_hash_func(const void *data)
{
	const zbx_object_events_t	*oe = (const zbx_object_events_t *)data;

	zbx_hash_t		hash;

	hash = ZBX_DEFAULT_UINT64_HASH_FUNC(&oe->objectid);
	hash = ZBX_DEFAULT_UINT64_HASH_ALGO(&oe->source, sizeof(oe->source), hash);
	hash = ZBX_DEFAULT_UINT64_HASH_ALGO(&oe->object, sizeof(oe->object), hash);

	return hash;
}

static int	DBpatch_3010021_trigger_events_compare_func(const void *d1, const void *d2)
{
	const zbx_object_events_t	*oe1 = (const zbx_object_events_t *)d1;
	const zbx_object_events_t	*oe2 = (const zbx_object_events_t *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(oe1->source, oe2->source);
	ZBX_RETURN_IF_NOT_EQUAL(oe1->object, oe2->object);
	ZBX_RETURN_IF_NOT_EQUAL(oe1->objectid, oe2->objectid);

	return 0;
}


/******************************************************************************
 *                                                                            *
 * Function: DBpatch_3010021_update_event_recovery                            *
 *                                                                            *
 * Purpose: set events.r_eventid field with corresponding recovery event id   *
 *                                                                            *
 * Parameters: events   - [IN/OUT] unrecovered events indexed by triggerid    *
 *             eventid  - [IN/OUT] the last processed event id                *
 *                                                                            *
 * Return value: SUCCEED - the operation was completed successfully           *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	DBpatch_3010021_update_event_recovery(zbx_hashset_t *events, zbx_uint64_t *eventid)
{
	DB_ROW			row;
	DB_RESULT		result;
	char			*sql = NULL;
	size_t			sql_alloc = 4096, sql_offset = 0;
	int			i, value, ret = FAIL;
	zbx_object_events_t	*object_events, object_events_local;
	zbx_db_insert_t		db_insert;

	sql = zbx_malloc(NULL, sql_alloc);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select source,object,objectid,eventid,value"
			" from events"
			" where eventid>" ZBX_FS_UI64
			" order by eventid",
			*eventid);

	/* process events by 10k large batches */
	if (NULL == (result = DBselectN(sql, 10000)))
		goto out;

	zbx_db_insert_prepare(&db_insert, "event_recovery", "eventid", "r_eventid", NULL);

	while (NULL != (row = DBfetch(result)))
	{
		object_events_local.source = atoi(row[0]);
		object_events_local.object = atoi(row[1]);

		/* source: 0 - EVENT_SOURCE_TRIGGERS, 3 - EVENT_SOURCE_INTERNAL */
		if (0 != atoi(row[0]) && 3 != atoi(row[0]))
			continue;

		ZBX_STR2UINT64(object_events_local.objectid, row[2]);
		ZBX_STR2UINT64(*eventid, row[3]);
		value = atoi(row[4]);

		if (NULL == (object_events = (zbx_object_events_t *)zbx_hashset_search(events, &object_events_local)))
		{
			object_events = (zbx_object_events_t *)zbx_hashset_insert(events, &object_events_local,
					sizeof(object_events_local));

			zbx_vector_uint64_create(&object_events->eventids);
		}

		if (1 == value)
		{
			/* 1 - TRIGGER_VALUE_TRUE (PROBLEM state) */

			zbx_vector_uint64_append(&object_events->eventids, *eventid);

			if (ZBX_OPEN_EVENT_WARNING_NUM == object_events->eventids.values_num)
			{
				zabbix_log(LOG_LEVEL_WARNING, "too many open problem events by event source:%d,"
						" object:%d and objectid:" ZBX_FS_UI64, object_events->source,
						object_events->object, object_events->objectid);
			}
		}
		else
		{
			/* 0 - TRIGGER_VALUE_FALSE (OK state) */

			for (i = 0; i < object_events->eventids.values_num; i++)
				zbx_db_insert_add_values(&db_insert, object_events->eventids.values[i], *eventid);

			zbx_vector_uint64_clear(&object_events->eventids);
		}
	}
	DBfree_result(result);

	ret = zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);
out:
	zbx_free(sql);

	return ret;
}

static int	DBpatch_3010021(void)
{
	int			i, ret = FAIL;
	zbx_uint64_t		eventid = 0, old_eventid;
	zbx_db_insert_t		db_insert;
	zbx_hashset_t		events;
	zbx_hashset_iter_t	iter;
	zbx_object_events_t	*object_events;

	zbx_hashset_create(&events, 1024, DBpatch_3010021_trigger_events_hash_func,
			DBpatch_3010021_trigger_events_compare_func);
	zbx_db_insert_prepare(&db_insert, "problem", "eventid", "source", "object", "objectid", NULL);

	do
	{
		old_eventid = eventid;

		if (SUCCEED != DBpatch_3010021_update_event_recovery(&events, &eventid))
			goto out;
	}
	while (eventid != old_eventid);

	/* generate problems from unrecovered events */

	zbx_hashset_iter_reset(&events, &iter);
	while (NULL != (object_events = (zbx_object_events_t *)zbx_hashset_iter_next(&iter)))
	{
		for (i = 0; i < object_events->eventids.values_num; i++)
		{
			zbx_db_insert_add_values(&db_insert, object_events->eventids.values[i], object_events->source,
					object_events->object, object_events->objectid);
		}

		if (1000 < db_insert.rows.values_num)
		{
			if (SUCCEED != zbx_db_insert_execute(&db_insert))
				goto out;

			zbx_db_insert_clean(&db_insert);
			zbx_db_insert_prepare(&db_insert, "problem", "eventid", "source", "object", "objectid", NULL);
		}

		zbx_vector_uint64_destroy(&object_events->eventids);
	}

	if (SUCCEED != zbx_db_insert_execute(&db_insert))
		goto out;

	ret = SUCCEED;
out:
	zbx_db_insert_clean(&db_insert);
	zbx_hashset_destroy(&events);

	return ret;
}

static int	DBpatch_3010022(void)
{
	const ZBX_FIELD	field = {"recovery", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("operations", &field);
}

static int	DBpatch_3010023(void)
{
	zbx_db_insert_t	db_insert;
	DB_ROW		row;
	DB_RESULT	result;
	int		ret;
	zbx_uint64_t	actionid;

	zbx_db_insert_prepare(&db_insert, "operations", "operationid", "actionid", "operationtype", "recovery", 0);

	result = DBselect("select actionid from actions where recovery_msg=1");

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(actionid, row[0]);
		/* operationtype: 11 - OPERATION_TYPE_RECOVERY_MESSAGE */
		zbx_db_insert_add_values(&db_insert, __UINT64_C(0), actionid, 11, 1);
	}
	DBfree_result(result);

	zbx_db_insert_autoincrement(&db_insert, "operationid");
	ret = zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);

	return ret;
}

static int	DBpatch_3010024(void)
{
	return DBdrop_field("actions", "recovery_msg");
}

/* patch 3010025 */

#define	ZBX_3010025_ACTION_NOTHING	0
#define	ZBX_3010025_ACTION_DISABLE	1
#define	ZBX_3010025_ACTION_CONVERT	2

/******************************************************************************
 *                                                                            *
 * Function: DBpatch_3010025_validate_action                                  *
 *                                                                            *
 * Purpose: checks if the action must be disabled or its operations converted *
 *          to recovery operations                                            *
 *                                                                            *
 * Return value: ZBX_3010025_ACTION_NOTHING - do nothing                      *
 *               ZBX_3010025_ACTION_DISABLE - disable action                  *
 *               ZBX_3010025_ACTION_CONVERT - convert action operations to    *
 *                                            recovery operations             *
 *                                                                            *
 * Comments: This function does not analyze expressions so it might ask to    *
 *           disable actions that can't match success event. However correct  *
 *           analysis is not easy to do, so to be safe failure is returned.   *
 *                                                                            *
 ******************************************************************************/
static int	DBpatch_3010025_validate_action(zbx_uint64_t actionid, int eventsource, int evaltype)
{
	DB_ROW		row;
	DB_RESULT	result;
	int		conditiontype, ret = ZBX_3010025_ACTION_DISABLE, value;

	/* evaltype: 0 - CONDITION_EVAL_TYPE_AND_OR, 1 - CONDITION_EVAL_TYPE_AND */
	if (evaltype != 0 && evaltype != 1)
		return ret;

	result = DBselect("select conditiontype,value from conditions where actionid=" ZBX_FS_UI64, actionid);

	while (NULL != (row = DBfetch(result)))
	{
		conditiontype = atoi(row[0]);

		/* eventsource: 0 - EVENT_SOURCE_TRIGGERS, 3 - EVENT_SOURCE_INTERNAL  */
		if (0 == eventsource)
		{
			/* conditiontype: 5 - CONDITION_TYPE_TRIGGER_VALUE */
			if (5 != conditiontype)
				continue;

			value = atoi(row[1]);

			/* condition 'Trigger value = OK' */
			if (0 == value)
			{
				if (ZBX_3010025_ACTION_NOTHING == ret)
				{
					ret = ZBX_3010025_ACTION_DISABLE;
					break;

				}
				ret = ZBX_3010025_ACTION_CONVERT;
			}

			/* condition 'Trigger value = PROBLEM' */
			if (1 == value)
			{
				if (ZBX_3010025_ACTION_CONVERT == ret)
				{
					ret = ZBX_3010025_ACTION_DISABLE;
					break;

				}
				ret = ZBX_3010025_ACTION_NOTHING;
			}
		}
		else if (3 == eventsource)
		{
			/* conditiontype: 23 -  CONDITION_TYPE_EVENT_TYPE */
			if (23 != conditiontype)
				continue;

			value = atoi(row[1]);

			/* event types:                                                          */
			/*            1 - Event type:  Item in "normal" state                    */
			/*            3 - Low-level discovery rule in "normal" state             */
			/*            5 - Trigger in "normal" state                              */
			if (1 == value || 3 == value || 5 == value)
			{
				if (ZBX_3010025_ACTION_NOTHING == ret)
				{
					ret = ZBX_3010025_ACTION_DISABLE;
					break;

				}
				ret = ZBX_3010025_ACTION_CONVERT;
			}

			/* event types:                                                          */
			/*            0 - Event type:  Item in "not supported" state             */
			/*            2 - Low-level discovery rule in "not supported" state      */
			/*            4 - Trigger in "unknown" state                             */
			if (0 == value || 2 == value || 4 == value)
			{
				if (ZBX_3010025_ACTION_CONVERT == ret)
				{
					ret = ZBX_3010025_ACTION_DISABLE;
					break;

				}
				ret = ZBX_3010025_ACTION_NOTHING;
			}
		}
	}
	DBfree_result(result);

	return ret;
}

static int	DBpatch_3010025(void)
{
	DB_ROW			row;
	DB_RESULT		result;
	zbx_vector_uint64_t	actionids_disable, actionids_convert;
	int			ret, evaltype, eventsource;
	zbx_uint64_t		actionid;

	zbx_vector_uint64_create(&actionids_disable);
	zbx_vector_uint64_create(&actionids_convert);

	/* status: 0 - ACTION_STATUS_ACTIVE */
	result = DBselect("select actionid,name,eventsource,evaltype from actions where status=0");

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(actionid, row[0]);
		eventsource = atoi(row[2]);
		evaltype = atoi(row[3]);

		ret = DBpatch_3010025_validate_action(actionid, eventsource, evaltype);

		if (ZBX_3010025_ACTION_DISABLE == ret)
		{
			zbx_vector_uint64_append(&actionids_disable, actionid);
			zabbix_log(LOG_LEVEL_WARNING, "Action \"%s\" was disabled during database upgrade:"
					" conditions might have matched success event which is not supported anymore.",
					row[1]);
		}
		else if (ZBX_3010025_ACTION_CONVERT == ret)
		{
			zbx_vector_uint64_append(&actionids_convert, actionid);
			zabbix_log(LOG_LEVEL_WARNING, "Action \"%s\" operations were converted to recovery operations"
					" during database upgrade.", row[1]);
		}
	}
	DBfree_result(result);

	ret = SUCCEED;

	if (0 != actionids_disable.values_num || 0 != actionids_convert.values_num)
	{
		char	*sql = NULL;
		size_t	sql_alloc = 0, sql_offset = 0;

		DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

		if (0 != actionids_disable.values_num)
		{
			/* status: 1 - ACTION_STATUS_DISABLED */

			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "update actions set status=1 where");
			DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "actionid", actionids_disable.values,
					actionids_disable.values_num);
			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");
		}

		if (0 != actionids_convert.values_num)
		{
			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "update operations set recovery=1 where");
			DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "actionid", actionids_convert.values,
					actionids_convert.values_num);
			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");
		}

		DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

		if (ZBX_DB_OK > DBexecute("%s", sql))
			ret = FAIL;

		zbx_free(sql);
	}

	zbx_vector_uint64_destroy(&actionids_convert);
	zbx_vector_uint64_destroy(&actionids_disable);

	return ret;
}

/* patch 3010026 */

#define	ZBX_3010026_TOKEN_UNKNOWN	0
#define	ZBX_3010026_TOKEN_OPEN		1
#define	ZBX_3010026_TOKEN_CLOSE		2
#define	ZBX_3010026_TOKEN_AND		3
#define	ZBX_3010026_TOKEN_OR		4
#define	ZBX_3010026_TOKEN_VALUE		5
#define	ZBX_3010026_TOKEN_END		6

#define ZBX_3010026_PARSE_VALUE		0
#define ZBX_3010026_PARSE_OP		1

static void	DBpatch_3010026_get_conditionids(zbx_uint64_t actionid, int eventsource,
		zbx_vector_uint64_t *conditionids)
{
	DB_ROW		row;
	DB_RESULT	result;
	zbx_uint64_t	conditionid;

	/* eventsource: 0 - EVENT_SOURCE_TRIGGERS, 3 - EVENT_SOURCE_INTERNAL  */
	if (0 == eventsource)
	{
		/* conditiontype: 5 - CONDITION_TYPE_TRIGGER_VALUE */
		result = DBselect("select conditionid from conditions"
				" where actionid=" ZBX_FS_UI64
					" and conditiontype=5",
				actionid);
	}
	else if (3 == eventsource)
	{
		/* conditiontype: 23 -  CONDITION_TYPE_EVENT_TYPE */
		result = DBselect("select conditionid from conditions"
				" where actionid=" ZBX_FS_UI64
					" and conditiontype=23"
					" and value in ('1', '3', '5')",
				actionid);
	}
	else
		return;

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(conditionid, row[0]);
		zbx_vector_uint64_append(conditionids, conditionid);
	}

	DBfree_result(result);
}

/******************************************************************************
 *                                                                            *
 * Function: DBpatch_3010026_expression_skip_whitespace                       *
 *                                                                            *
 * Purpose: skips whitespace characters                                       *
 *                                                                            *
 * Parameters: expression - [IN] the expression to process                    *
 *             offset     - [IN] the starting offset in expression            *
 *                                                                            *
 * Return value: the position of first non-whitespace character after offset  *
 *                                                                            *
 ******************************************************************************/
static size_t	DBpatch_3010026_expression_skip_whitespace(const char *expression, size_t offset)
{
	while (' ' == expression[offset])
		offset++;

	return offset;
}

/******************************************************************************
 *                                                                            *
 * Function: DBpatch_3010026_expression_get_token                             *
 *                                                                            *
 * Purpose: gets the next expression token starting with offset               *
 *                                                                            *
 * Parameters: expression - [IN] the expression to process                    *
 *             offset     - [IN] the starting offset in expression            *
 *             token      - [OUT] the token location in expression            *
 *                                                                            *
 * Return value: the token type (see ZBX_3010026_TOKEN_* defines)             *
 *                                                                            *
 * Comments: The recognized tokens are '(', ')', 'and', 'or' and '{<id>}'.    *
 *                                                                            *
 ******************************************************************************/
static int	DBpatch_3010026_expression_get_token(const char *expression, int offset, zbx_strloc_t *token)
{
	int	ret = ZBX_3010026_TOKEN_UNKNOWN;

	offset = DBpatch_3010026_expression_skip_whitespace(expression, offset);
	token->l = offset;

	switch (expression[offset])
	{
		case '\0':
			token->r = offset;
			ret = ZBX_3010026_TOKEN_END;
			break;
		case '(':
			token->r = offset;
			ret = ZBX_3010026_TOKEN_OPEN;
			break;
		case ')':
			token->r = offset;
			ret = ZBX_3010026_TOKEN_CLOSE;
			break;
		case 'o':
			if ('r' == expression[offset + 1])
			{
				token->r = offset + 1;
				ret = ZBX_3010026_TOKEN_OR;
			}
			break;
		case 'a':
			if ('n' == expression[offset + 1] && 'd' == expression[offset + 2])
			{
				token->r = offset + 2;
				ret = ZBX_3010026_TOKEN_AND;
			}
			break;
		case '{':
			while (0 != isdigit(expression[++offset]))
				;
			if ('}' == expression[offset])
			{
				token->r = offset;
				ret = ZBX_3010026_TOKEN_VALUE;
			}
			break;
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: DBpatch_3010026_expression_validate_value                        *
 *                                                                            *
 * Purpose: checks if the value does not match any filter value               *
 *                                                                            *
 * Parameters: expression - [IN] the expression to process                    *
 *             value      - [IN] the location of value in expression          *
 *             filter     - [IN] a list of values to compare                  *
 *                                                                            *
 * Return value: SUCCEED - the value does not match any filter values         *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	DBpatch_3010026_expression_validate_value(const char *expression, zbx_strloc_t *value,
		const zbx_vector_str_t *filter)
{
	int	i;

	for (i = 0; i < filter->values_num; i++)
	{
		if (0 == strncmp(expression + value->l, filter->values[i], value->r - value->l + 1))
			return SUCCEED;
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: DBpatch_3010026_expression_cut_substring                         *
 *                                                                            *
 * Purpose: cuts substring from the expression                                *
 *                                                                            *
 * Parameters: expression - [IN] the expression to process                    *
 *             cu         - [IN] the substring location                       *
 *                                                                            *
 ******************************************************************************/
static void	DBpatch_3010026_expression_cut_substring(char *expression, zbx_strloc_t *cut)
{
	if (cut->l <= cut->r)
		memmove(expression + cut->l, expression + cut->r + 1, strlen(expression + cut->r + 1) + 1);
}

/******************************************************************************
 *                                                                            *
 * Function: DBpatch_3010026_expression_move_location                         *
 *                                                                            *
 * Purpose: location by the specified offset                                  *
 *                                                                            *
 * Parameters: location  - [IN] the location to adjust                        *
 *             offset    - [IN] the offset                                    *
 *                                                                            *
 ******************************************************************************/
static void	DBpatch_3010026_expression_move_location(zbx_strloc_t *location, int offset)
{
	location->l += offset;
	location->r += offset;
}

/******************************************************************************
 *                                                                            *
 * Function: DBpatch_3010026_expression_remove_values_impl                    *
 *                                                                            *
 * Purpose: removes values specified in filter from the location              *
 *                                                                            *
 * Parameters: expression - [IN] the expression to process                    *
 *             exp_token  - [IN] the current location in expression           *
 *             filter     - [IN] a list of values                             *
 *                                                                            *
 * Return value: SUCCEED - the expression was processed successfully          *
 *               FAIL    - failed to parse expression                         *
 *                                                                            *
 ******************************************************************************/
static int	DBpatch_3010026_expression_remove_values_impl(char *expression, zbx_strloc_t *exp_token,
		const zbx_vector_str_t *filter)
{
	zbx_strloc_t	token, cut_loc, op_token, value_token;
	int		token_type, cut_value = 0, state = ZBX_3010026_PARSE_VALUE,
			prevop_type = ZBX_3010026_TOKEN_UNKNOWN;

	exp_token->r = exp_token->l;

	while (ZBX_3010026_TOKEN_UNKNOWN != (token_type =
			DBpatch_3010026_expression_get_token(expression, exp_token->r, &token)))
	{
		/* parse value */
		if (ZBX_3010026_PARSE_VALUE == state)
		{
			state = ZBX_3010026_PARSE_OP;

			if (ZBX_3010026_TOKEN_OPEN == token_type)
			{
				token.l = token.r + 1;

				if (FAIL == DBpatch_3010026_expression_remove_values_impl(expression, &token, filter))
					return FAIL;

				if (')' != expression[token.r])
					return FAIL;

				if (token.r == DBpatch_3010026_expression_skip_whitespace(expression, token.l))
					cut_value = 1;

				/* include opening '(' into token */
				token.l--;

				value_token = token;
				exp_token->r = token.r + 1;

				continue;
			}
			else if (ZBX_3010026_TOKEN_VALUE != token_type)
				return FAIL;

			if (SUCCEED == DBpatch_3010026_expression_validate_value(expression, &token, filter))
				cut_value = 1;

			value_token = token;
			exp_token->r = token.r + 1;

			continue;
		}

		/* parse operator */
		state = ZBX_3010026_PARSE_VALUE;

		if (1 == cut_value)
		{
			if (ZBX_3010026_TOKEN_AND == prevop_type || (ZBX_3010026_TOKEN_OR == prevop_type &&
					(ZBX_3010026_TOKEN_CLOSE == token_type || ZBX_3010026_TOKEN_END == token_type)))
			{
				cut_loc.l = op_token.l;
				cut_loc.r = value_token.r;
				DBpatch_3010026_expression_move_location(&token, -(cut_loc.r - cut_loc.l + 1));
				prevop_type = token_type;
				op_token = token;
			}
			else
			{
				cut_loc.l = value_token.l;

				if (ZBX_3010026_TOKEN_CLOSE == token_type || ZBX_3010026_TOKEN_END == token_type)
					cut_loc.r = token.l - 1;
				else
					cut_loc.r = token.r;

				DBpatch_3010026_expression_move_location(&token, -(cut_loc.r - cut_loc.l + 1));
			}
			DBpatch_3010026_expression_cut_substring(expression, &cut_loc);
			cut_value = 0;
		}
		else
		{
			prevop_type = token_type;
			op_token = token;
		}

		if (ZBX_3010026_TOKEN_CLOSE == token_type || ZBX_3010026_TOKEN_END == token_type)
		{
			exp_token->r = token.r;
			return SUCCEED;
		}

		if (ZBX_3010026_TOKEN_AND != token_type && ZBX_3010026_TOKEN_OR != token_type)
			return FAIL;

		exp_token->r = token.r + 1;
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: DBpatch_3010026_expression_remove_values                         *
 *                                                                            *
 * Purpose: removes values specified in filter from the location              *
 *                                                                            *
 * Parameters: expression - [IN] the expression to process                    *
 *             filter     - [IN] a list of values                             *
 *                                                                            *
 * Return value: SUCCEED - the expression was processed successfully          *
 *               FAIL    - failed to parse expression                         *
 *                                                                            *
 ******************************************************************************/
static int	DBpatch_3010026_expression_remove_values(char *expression, const zbx_vector_str_t *filter)
{
	int		ret;
	zbx_strloc_t	token = {0};

	if (SUCCEED == (ret = DBpatch_3010026_expression_remove_values_impl(expression, &token, filter)))
		zbx_lrtrim(expression, " ");

	return ret;
}

static int	DBpatch_3010026(void)
{
	DB_ROW			row;
	DB_RESULT		result;
	zbx_vector_uint64_t	conditionids;
	int			ret = FAIL, evaltype, index, i, eventsource;
	zbx_uint64_t		actionid;
	char			*sql = NULL, *formula;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_vector_str_t	filter;

	zbx_vector_uint64_create(&conditionids);
	zbx_vector_str_create(&filter);
	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	/* status: 0 - ACTION_STATUS_ACTIVE */
	result = DBselect("select actionid,eventsource,evaltype,formula from actions");

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(actionid, row[0]);
		eventsource = atoi(row[1]);
		evaltype = atoi(row[2]);

		index = conditionids.values_num;
		DBpatch_3010026_get_conditionids(actionid, eventsource, &conditionids);

		/* evaltype: 3 - CONDITION_EVAL_TYPE_EXPRESSION */
		if (3 != evaltype)
			continue;

		/* no new conditions to remove, process next action */
		if (index == conditionids.values_num)
			continue;

		formula = zbx_strdup(NULL, row[3]);

		for (i = index; i < conditionids.values_num; i++)
			zbx_vector_str_append(&filter, zbx_dsprintf(NULL, "{" ZBX_FS_UI64 "}", conditionids.values[i]));

		if (SUCCEED == DBpatch_3010026_expression_remove_values(formula, &filter))
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update actions set formula='%s'", formula);
			if ('\0' == *formula)
			{
				/* evaltype: 0 = CONDITION_EVAL_TYPE_AND_OR */
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, ",evaltype=0");
			}
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " where actionid=" ZBX_FS_UI64 ";\n",
					actionid);
		}

		zbx_free(formula);
		zbx_vector_str_clear_ext(&filter, zbx_ptr_free);

		if (SUCCEED != DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset))
			goto out;
	}

	DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (16 < sql_offset)	/* in ORACLE always present begin..end; */
	{
		if (ZBX_DB_OK > DBexecute("%s", sql))
			goto out;
	}

	if (0 != conditionids.values_num)
	{
		sql_offset = 0;
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from conditions where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "conditionid", conditionids.values,
				conditionids.values_num);

		if (ZBX_DB_OK > DBexecute("%s", sql))
			goto out;
	}

	ret = SUCCEED;

out:
	DBfree_result(result);
	zbx_free(sql);
	zbx_vector_str_destroy(&filter);
	zbx_vector_uint64_destroy(&conditionids);

	return ret;
}

#endif

DBPATCH_START(3010)

/* version, duplicates flag, mandatory flag */

DBPATCH_ADD(3010000, 0, 1)
DBPATCH_ADD(3010001, 0, 1)
DBPATCH_ADD(3010002, 0, 1)
DBPATCH_ADD(3010003, 0, 1)
DBPATCH_ADD(3010004, 0, 1)
DBPATCH_ADD(3010005, 0, 1)
DBPATCH_ADD(3010006, 0, 1)
DBPATCH_ADD(3010007, 0, 1)
DBPATCH_ADD(3010008, 0, 1)
DBPATCH_ADD(3010009, 0, 1)
DBPATCH_ADD(3010010, 0, 1)
DBPATCH_ADD(3010011, 0, 1)
DBPATCH_ADD(3010012, 0, 1)
DBPATCH_ADD(3010013, 0, 1)
DBPATCH_ADD(3010014, 0, 1)
DBPATCH_ADD(3010015, 0, 1)
DBPATCH_ADD(3010016, 0, 1)
DBPATCH_ADD(3010017, 0, 1)
DBPATCH_ADD(3010018, 0, 1)
DBPATCH_ADD(3010019, 0, 1)
DBPATCH_ADD(3010020, 0, 1)
DBPATCH_ADD(3010021, 0, 1)
DBPATCH_ADD(3010022, 0, 1)
DBPATCH_ADD(3010023, 0, 1)
DBPATCH_ADD(3010024, 0, 1)
DBPATCH_ADD(3010025, 0, 1)
DBPATCH_ADD(3010026, 0, 1)

DBPATCH_END()
