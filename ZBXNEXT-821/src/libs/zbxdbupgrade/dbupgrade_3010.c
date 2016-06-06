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

/* DBpatch_301002 () */

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

DBPATCH_END()
