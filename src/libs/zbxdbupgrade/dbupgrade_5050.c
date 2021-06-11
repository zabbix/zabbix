/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
 * 6.0 development database patches
 */

#ifndef HAVE_SQLITE3

extern unsigned char program_type;

static int	DBpatch_5050000(void)
{
	return SUCCEED;
}

static int	DBpatch_5050001(void)
{
	const ZBX_TABLE	table =
			{"service_problem_tag", "service_problem_tagid", 0,
				{
					{"service_problem_tagid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"serviceid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"tag", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"operator", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"value", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_5050002(void)
{
	const ZBX_FIELD	field = {"serviceid", NULL, "services", "serviceid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("service_problem_tag", 1, &field);
}

static int	DBpatch_5050003(void)
{
	return DBcreate_index("service_problem_tag", "service_problem_tag_1", "serviceid", 0);
}
static int	DBpatch_5050004(void)
{
	const ZBX_TABLE	table =
			{"service_problem", "service_problemid", 0,
				{
					{"service_problemid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"eventid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"serviceid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"severity", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_5050005(void)
{
	return DBcreate_index("service_problem", "service_problem_1", "eventid", 0);
}

static int	DBpatch_5050006(void)
{
	return DBcreate_index("service_problem", "service_problem_2", "serviceid", 0);
}

static int	DBpatch_5050007(void)
{
	const ZBX_FIELD	field = {"eventid", NULL, "problem", "eventid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("service_problem", 1, &field);
}

static int	DBpatch_5050008(void)
{
	const ZBX_FIELD	field = {"serviceid", NULL, "services", "serviceid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("service_problem", 2, &field);
}

#define ZBX_TAGVALUE_MAX_LEN	32

static void	DBpatch_trim_tag_value(char *value)
{
	size_t	len;

	len = zbx_strlen_utf8_nchars(value, ZBX_TAGVALUE_MAX_LEN - ZBX_CONST_STRLEN("..."));

	memcpy(value + len, "...", ZBX_CONST_STRLEN("...") + 1);
}

static void	DBpatch_get_eventid_by_triggerid(zbx_uint64_t triggerid, zbx_vector_uint64_t *eventids)
{
	DB_RESULT	result;
	DB_ROW		row;

	result = DBselect("select eventid from events where value=1 and source=0 and object=0 and objectid="
			ZBX_FS_UI64, triggerid);

	while (NULL != (row = DBfetch(result)))
	{
		zbx_uint64_t	eventid;

		ZBX_STR2UINT64(eventid, row[0]);
		zbx_vector_uint64_append(eventids, eventid);
	}

	DBfree_result(result);
}

static int	DBpatch_5050009(void)
{
	DB_RESULT	result;
	DB_ROW		row;
	zbx_db_insert_t	ins_service_problem_tag, ins_trigger_tag, ins_problem_tag;
	zbx_uint64_t	old_triggerid = 0, triggerid, serviceid;
	int		ret = SUCCEED;

	result = DBselect("select t.triggerid,t.description,s.serviceid from triggers t join services s "
			"on t.triggerid=s.triggerid order by t.triggerid");

	zbx_db_insert_prepare(&ins_service_problem_tag, "service_problem_tag", "service_problem_tagid", "serviceid",
			"tag", "operator", "value", NULL);
	zbx_db_insert_prepare(&ins_trigger_tag, "trigger_tag", "triggertagid", "triggerid", "tag", "value", NULL);
	zbx_db_insert_prepare(&ins_problem_tag, "problem_tag", "problemtagid", "eventid", "tag", "value", NULL);

	while (NULL != (row = DBfetch(result)))
	{
		int	i;
		char	*desc, *tag_value = NULL;

		ZBX_STR2UINT64(triggerid, row[0]);
		desc = row[1];
		ZBX_STR2UINT64(serviceid, row[2]);

		tag_value = zbx_dsprintf(NULL, "%s:%s", row[0], desc);

		if (ZBX_TAGVALUE_MAX_LEN < zbx_strlen_utf8(tag_value))
			DBpatch_trim_tag_value(tag_value);

		zbx_db_insert_add_values(&ins_service_problem_tag, __UINT64_C(0), serviceid, "ServiceLink", 0,
				tag_value);

		if (old_triggerid != triggerid)
		{
			zbx_vector_uint64_t	problemtag_eventids;

			zbx_db_insert_add_values(&ins_trigger_tag, __UINT64_C(0), triggerid, "ServiceLink", tag_value);

			zbx_vector_uint64_create(&problemtag_eventids);

			DBpatch_get_eventid_by_triggerid(triggerid, &problemtag_eventids);

			for (i = 0; i < problemtag_eventids.values_num; i++)
			{
				zbx_db_insert_add_values(&ins_problem_tag, __UINT64_C(0), problemtag_eventids.values[i],
						"ServiceLink", tag_value);
			}

			zbx_vector_uint64_destroy(&problemtag_eventids);
		}

		old_triggerid = triggerid;

		zbx_free(tag_value);
	}

	zbx_db_insert_autoincrement(&ins_service_problem_tag, "service_problem_tagid");
	ret = zbx_db_insert_execute(&ins_service_problem_tag);

	if (FAIL == ret)
		goto out;

	zbx_db_insert_autoincrement(&ins_trigger_tag, "triggertagid");
	ret = zbx_db_insert_execute(&ins_trigger_tag);

	if (FAIL == ret)
		goto out;

	zbx_db_insert_autoincrement(&ins_problem_tag, "problemtagid");
	ret = zbx_db_insert_execute(&ins_problem_tag);
out:
	zbx_db_insert_clean(&ins_service_problem_tag);
	zbx_db_insert_clean(&ins_trigger_tag);
	zbx_db_insert_clean(&ins_problem_tag);
	DBfree_result(result);

	return ret;
}

static int	DBpatch_5050010(void)
{
	return DBdrop_foreign_key("services", 1);
}

static int	DBpatch_5050011(void)
{
	return DBdrop_field("services", "triggerid");
}

#endif

DBPATCH_START(5050)

/* version, duplicates flag, mandatory flag */

DBPATCH_ADD(5050000, 0, 1)
DBPATCH_ADD(5050001, 0, 1)
DBPATCH_ADD(5050002, 0, 1)
DBPATCH_ADD(5050003, 0, 1)
DBPATCH_ADD(5050004, 0, 1)
DBPATCH_ADD(5050005, 0, 1)
DBPATCH_ADD(5050006, 0, 1)
DBPATCH_ADD(5050007, 0, 1)
DBPATCH_ADD(5050008, 0, 1)
DBPATCH_ADD(5050009, 0, 1)
DBPATCH_ADD(5050010, 0, 1)
DBPATCH_ADD(5050011, 0, 1)

DBPATCH_END()
