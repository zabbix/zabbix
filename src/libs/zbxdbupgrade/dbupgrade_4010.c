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

#include "dbupgrade.h"

#include "zbxdbhigh.h"

extern unsigned char	program_type;

/*
 * 4.2 development database patches
 */

#ifndef HAVE_SQLITE3

static int	DBpatch_4010001(void)
{
	const ZBX_FIELD	field = {"content_type", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("media_type", &field);
}

static int	DBpatch_4010002(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("update media_type set content_type=0"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_4010003(void)
{
	const ZBX_FIELD	field = {"error_handler", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("item_preproc", &field);
}

static int	DBpatch_4010004(void)
{
	const ZBX_FIELD	field = {"error_handler_params", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("item_preproc", &field);
}

static int	DBpatch_4010005(void)
{
	const ZBX_TABLE table =
			{"lld_macro_path", "lld_macro_pathid", 0,
				{
					{"lld_macro_pathid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"itemid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"lld_macro", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"path", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_4010006(void)
{
	return DBcreate_index("lld_macro_path", "lld_macro_path_1", "itemid,lld_macro", 1);
}

static int	DBpatch_4010007(void)
{
	const ZBX_FIELD	field = {"itemid", NULL, "items", "itemid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("lld_macro_path", 1, &field);
}

static int	DBpatch_4010008(void)
{
	const ZBX_FIELD	field = {"db_extension", "", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_4010009(void)
{
	const ZBX_TABLE table =
		{"host_tag", "hosttagid", 0,
			{
				{"hosttagid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
				{"hostid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
				{"tag", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{"value", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{0}
			},
			NULL
		};

	return DBcreate_table(&table);
}

static int	DBpatch_4010010(void)
{
	return DBcreate_index("host_tag", "host_tag_1", "hostid", 0);
}

static int	DBpatch_4010011(void)
{
	const ZBX_FIELD	field = {"hostid", NULL, "hosts", "hostid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("host_tag", 1, &field);
}

static int	DBpatch_4010012(void)
{
	const ZBX_FIELD	field = {"params", "", NULL, NULL, 0, ZBX_TYPE_SHORTTEXT, ZBX_NOTNULL, 0};

	return DBmodify_field_type("item_preproc", &field, NULL);
}

static int	DBpatch_4010013(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("update profiles set idx='web.items.filter_groupids'"
				" where idx='web.items.filter_groupid'"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_4010014(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("update profiles set idx='web.items.filter_hostids'"
				" where idx='web.items.filter_hostid'"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_4010015(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("update profiles set idx='web.items.filter_inherited'"
				" where idx='web.items.filter_templated_items'"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_4010016(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("delete from profiles where idx='web.triggers.filter_priority' and value_int='-1'"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_4010017(void)
{
	const ZBX_FIELD	field = {"host_source", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("dchecks", &field);
}

static int	DBpatch_4010018(void)
{
	const ZBX_FIELD	field = {"name_source", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("dchecks", &field);
}

static int	DBpatch_4010019(void)
{
	return DBdrop_foreign_key("dchecks", 1);
}

static int	DBpatch_4010020(void)
{
	return DBdrop_index("dchecks", "dchecks_1");
}

static int	DBpatch_4010021(void)
{
	return DBcreate_index("dchecks", "dchecks_1", "druleid,host_source,name_source", 0);
}

static int	DBpatch_4010022(void)
{
	const ZBX_FIELD	field = {"druleid", NULL, "drules", "druleid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("dchecks", 1, &field);
}

static int	DBpatch_4010023(void)
{
	return DBcreate_index("proxy_dhistory", "proxy_dhistory_2", "druleid", 0);
}

static int	DBpatch_4010024(void)
{
	const ZBX_FIELD	field = {"height", "2", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBmodify_field_type("widget", &field, NULL);
}

static int	DBpatch_4010025(void)
{
	DB_ROW		row;
	DB_RESULT	result;
	zbx_uint64_t	nextid;

	if (0 != (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("delete from ids where table_name='proxy_history'"))
		return FAIL;

	result = DBselect("select max(id) from proxy_history");

	if (NULL != (row = DBfetch(result)))
		ZBX_DBROW2UINT64(nextid, row[0]);
	else
		nextid = 0;

	DBfree_result(result);

	if (0 != nextid && ZBX_DB_OK > DBexecute("insert into ids values ('proxy_history','history_lastid'," ZBX_FS_UI64
			")", nextid))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_4010026(void)
{
	if (0 != (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("update hosts set status=1"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_4010027(void)
{
	const ZBX_FIELD	field = {"details", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("triggers", &field);
}

#endif

DBPATCH_START(4010)

/* version, duplicates flag, mandatory flag */

DBPATCH_ADD(4010001, 0, 1)
DBPATCH_ADD(4010002, 0, 1)
DBPATCH_ADD(4010003, 0, 1)
DBPATCH_ADD(4010004, 0, 1)
DBPATCH_ADD(4010005, 0, 1)
DBPATCH_ADD(4010006, 0, 1)
DBPATCH_ADD(4010007, 0, 1)
DBPATCH_ADD(4010008, 0, 1)
DBPATCH_ADD(4010009, 0, 1)
DBPATCH_ADD(4010010, 0, 1)
DBPATCH_ADD(4010011, 0, 1)
DBPATCH_ADD(4010012, 0, 1)
DBPATCH_ADD(4010013, 0, 1)
DBPATCH_ADD(4010014, 0, 1)
DBPATCH_ADD(4010015, 0, 1)
DBPATCH_ADD(4010016, 0, 1)
DBPATCH_ADD(4010017, 0, 1)
DBPATCH_ADD(4010018, 0, 1)
DBPATCH_ADD(4010019, 0, 1)
DBPATCH_ADD(4010020, 0, 1)
DBPATCH_ADD(4010021, 0, 1)
DBPATCH_ADD(4010022, 0, 1)
DBPATCH_ADD(4010023, 0, 1)
DBPATCH_ADD(4010024, 0, 1)
DBPATCH_ADD(4010025, 0, 1)
DBPATCH_ADD(4010026, 0, 1)
DBPATCH_ADD(4010027, 0, 1)

DBPATCH_END()
