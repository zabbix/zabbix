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

#include "dbupgrade.h"

#include "zbxdb.h"
#include "zbxdbschema.h"

/*
 * 4.2 development database patches
 */

#ifndef HAVE_SQLITE3

static int	DBpatch_4010001(void)
{
	const zbx_db_field_t	field = {"content_type", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("media_type", &field);
}

static int	DBpatch_4010002(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > zbx_db_execute("update media_type set content_type=0"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_4010003(void)
{
	const zbx_db_field_t	field = {"error_handler", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("item_preproc", &field);
}

static int	DBpatch_4010004(void)
{
	const zbx_db_field_t	field = {"error_handler_params", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("item_preproc", &field);
}

static int	DBpatch_4010005(void)
{
	const zbx_db_table_t	table =
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
	const zbx_db_field_t	field = {"itemid", NULL, "items", "itemid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("lld_macro_path", 1, &field);
}

static int	DBpatch_4010008(void)
{
	const zbx_db_field_t	field = {"db_extension", "", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_4010009(void)
{
	const zbx_db_table_t	table =
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
	const zbx_db_field_t	field = {"hostid", NULL, "hosts", "hostid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("host_tag", 1, &field);
}

static int	DBpatch_4010012(void)
{
	const zbx_db_field_t	field = {"params", "", NULL, NULL, 0, ZBX_TYPE_TEXT, ZBX_NOTNULL, 0};

	return DBmodify_field_type("item_preproc", &field, NULL);
}

static int	DBpatch_4010013(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > zbx_db_execute("update profiles set idx='web.items.filter_groupids'"
				" where idx='web.items.filter_groupid'"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_4010014(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > zbx_db_execute("update profiles set idx='web.items.filter_hostids'"
				" where idx='web.items.filter_hostid'"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_4010015(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > zbx_db_execute("update profiles set idx='web.items.filter_inherited'"
				" where idx='web.items.filter_templated_items'"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_4010016(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > zbx_db_execute("delete from profiles where idx='web.triggers.filter_priority' and value_int='-1'"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_4010017(void)
{
	const zbx_db_field_t	field = {"host_source", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("dchecks", &field);
}

static int	DBpatch_4010018(void)
{
	const zbx_db_field_t	field = {"name_source", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

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
	const zbx_db_field_t	field = {"druleid", NULL, "drules", "druleid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("dchecks", 1, &field);
}

static int	DBpatch_4010023(void)
{
	return DBcreate_index("proxy_dhistory", "proxy_dhistory_2", "druleid", 0);
}

static int	DBpatch_4010024(void)
{
	const zbx_db_field_t	field = {"height", "2", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBmodify_field_type("widget", &field, NULL);
}

static int	DBpatch_4010025(void)
{
	zbx_db_row_t	row;
	zbx_db_result_t	result;
	zbx_uint64_t	nextid;

	if (0 != (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > zbx_db_execute("delete from ids where table_name='proxy_history'"))
		return FAIL;

	result = zbx_db_select("select max(id) from proxy_history");

	if (NULL != (row = zbx_db_fetch(result)))
		ZBX_DBROW2UINT64(nextid, row[0]);
	else
		nextid = 0;

	zbx_db_free_result(result);

	if (0 != nextid && ZBX_DB_OK > zbx_db_execute("insert into ids values ('proxy_history','history_lastid'," ZBX_FS_UI64
			")", nextid))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_4010026(void)
{
	if (0 != (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > zbx_db_execute("update hosts set status=1"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_4010027(void)
{
	const zbx_db_field_t	field = {"details", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

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
