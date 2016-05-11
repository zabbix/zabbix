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
			{"problem", "problemid", 0,
				{
					{"problemid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"triggerid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"eventid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_3010007(void)
{
	return DBcreate_index("problem", "problem_1", "triggerid", 0);
}

static int	DBpatch_3010008(void)
{

	const ZBX_FIELD field = {"triggerid", NULL, "triggers", "triggerid", 0, ZBX_TYPE_ID, ZBX_NOTNULL,
			ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("problem", 1, &field);
}

static int	DBpatch_3010009(void)
{

	const ZBX_FIELD field = {"eventid", NULL, "events", "eventid", 0, ZBX_TYPE_ID, ZBX_NOTNULL,
			ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("problem", 2, &field);
}

static int	DBpatch_3010010(void)
{
	const ZBX_FIELD	field = {"r_eventid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_field("events", &field);
}

/* problem eventids by triggerid */
typedef struct
{
	zbx_uint64_t		triggerid;
	zbx_vector_uint64_t	eventids;
}
zbx_trigger_events_t;

/******************************************************************************
 *                                                                            *
 * Function: assign_recovery_events                                           *
 *                                                                            *
 * Purpose: set events.r_eventid field with corresponding recovery event id   *
 *                                                                            *
 * Parameters: events   - [IN/OUT] unrecovered events indexed by triggerid    *
 *             *eventid - [IN/OUT] the last processed event id                *
 *                                                                            *
 * Return value: SUCCEED - the operation was completed successfully           *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	assign_recovery_events(zbx_hashset_t *events, zbx_uint64_t *eventid)
{
	DB_ROW			row;
	DB_RESULT		result;
	char			*sql = NULL;
	size_t			sql_alloc = 4096, sql_offset = 0;
	zbx_uint64_t		triggerid;
	int			value, ret = FAIL;
	zbx_trigger_events_t	*trigger_events, trigger_events_local;

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

	sql_offset = 0;

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_alloc);

	while (NULL != (row = DBfetch(result)))
	{
		/* source: 0 - EVENT_SOURCE_TRIGGERS, object: 0 - EVENT_OBJECT_TRIGGER */
		if (0 != atoi(row[0]) || 0 != atoi(row[1]))
			continue;

		ZBX_STR2UINT64(triggerid, row[2]);
		ZBX_STR2UINT64(*eventid, row[3]);
		value = atoi(row[4]);

		if (NULL == (trigger_events = (zbx_trigger_events_t *)zbx_hashset_search(events, &triggerid)))
		{
			trigger_events_local.triggerid = triggerid;

			trigger_events = (zbx_trigger_events_t *)zbx_hashset_insert(events, &trigger_events_local,
					sizeof(trigger_events_local));

			zbx_vector_uint64_create(&trigger_events->eventids);
		}

		if (1 == value)
		{
			/* 1 - TRIGGER_VALUE_TRUE (PROBLEM state) */

			zbx_vector_uint64_append(&trigger_events->eventids, *eventid);
		}
		else
		{
			/* 0 - TRIGGER_VALUE_FALSE (OK state) */

			if (0 < trigger_events->eventids.values_num)
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update events set r_eventid="
						ZBX_FS_UI64 " where", *eventid);
				DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "eventid",
						trigger_events->eventids.values, trigger_events->eventids.values_num);
				zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");

				if (SUCCEED != DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset))
					goto out;

				zbx_vector_uint64_clear(&trigger_events->eventids);
			}
		}
	}

	DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (16 < sql_offset)	/* in ORACLE always present begin..end; */
	{
		if (ZBX_DB_OK > DBexecute("%s", sql))
			goto out;
	}

	ret = SUCCEED;
out:
	zbx_free(sql);

	return ret;
}

static int	DBpatch_3010011(void)
{
	int			i, ret = FAIL;
	zbx_uint64_t		eventid = 0, old_eventid;
	zbx_db_insert_t		db_insert;
	zbx_hashset_t		events;
	zbx_hashset_iter_t	iter;
	zbx_trigger_events_t	*trigger_events;

	zbx_hashset_create(&events, 1024, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_db_insert_prepare(&db_insert, "problem", "problemid", "triggerid", "eventid", NULL);

	do
	{
		old_eventid = eventid;

		if (SUCCEED != assign_recovery_events(&events, &eventid))
			goto out;
	}
	while (eventid != old_eventid);

	/* generate problems from unrecovered events */

	zbx_hashset_iter_reset(&events, &iter);
	while (NULL != (trigger_events = (zbx_trigger_events_t *)zbx_hashset_iter_next(&iter)))
	{
		for (i = 0; i < trigger_events->eventids.values_num; i++)
		{
			zbx_db_insert_add_values(&db_insert, __UINT64_C(0), trigger_events->triggerid,
					trigger_events->eventids.values[i]);
		}

		if (1000 < db_insert.rows.values_num)
		{
			zbx_db_insert_autoincrement(&db_insert, "problemid");
			if (SUCCEED != zbx_db_insert_execute(&db_insert))
				goto out;

			zbx_db_insert_clean(&db_insert);
			zbx_db_insert_prepare(&db_insert, "problem", "problemid", "triggerid", "eventid", NULL);
		}

		zbx_vector_uint64_destroy(&trigger_events->eventids);
	}

	zbx_db_insert_autoincrement(&db_insert, "problemid");
	if (SUCCEED != zbx_db_insert_execute(&db_insert))
		goto out;

	ret = SUCCEED;
out:
	zbx_db_insert_clean(&db_insert);
	zbx_hashset_destroy(&events);

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

DBPATCH_END()
