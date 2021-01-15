/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
 * 5.4 development database patches
 */

#ifndef HAVE_SQLITE3

extern unsigned char	program_type;

static int	DBpatch_5030000(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("delete from profiles where idx='web.queue.config'"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_5030001(void)
{
	const ZBX_TABLE table =
			{"item_tag", "itemtagid", 0,
				{
					{"itemtagid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"itemid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"tag", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"value", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_5030002(void)
{
	return DBcreate_index("item_tag", "item_tag_1", "itemid", 0);
}

static int	DBpatch_5030003(void)
{
	const ZBX_FIELD	field = {"itemid", NULL, "items", "itemid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("item_tag", 1, &field);
}

static int	DBpatch_5030004(void)
{
	const ZBX_TABLE table =
			{"httptest_tag", "httptesttagid", 0,
				{
					{"httptesttagid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"httptestid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"tag", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"value", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_5030005(void)
{
	return DBcreate_index("httptest_tag", "httptest_tag_1", "httptestid", 0);
}

static int	DBpatch_5030006(void)
{
	const ZBX_FIELD	field = {"httptestid", NULL, "httptest", "httptestid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("httptest_tag", 1, &field);
}

static int	DBpatch_5030007(void)
{
	const ZBX_TABLE table =
			{"sysmaps_element_tag", "selementtagid", 0,
				{
					{"selementtagid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"selementid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"tag", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"value", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"operator", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_5030008(void)
{
	return DBcreate_index("sysmaps_element_tag", "sysmaps_element_tag_1", "selementid", 0);
}

static int	DBpatch_5030009(void)
{
	const ZBX_FIELD	field = {"selementid", NULL, "sysmaps_elements", "selementid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("sysmaps_element_tag", 1, &field);
}

static int	DBpatch_5030010(void)
{
	const ZBX_FIELD	field = {"evaltype", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("sysmaps_elements", &field);
}

static int	DBpatch_5030011(void)
{
	DB_ROW		row;
	DB_RESULT	result;
	zbx_uint64_t	itemid, itemtagid = 1;
	int		ret = SUCCEED;
	char		*value;
	zbx_db_insert_t	db_insert;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	zbx_db_insert_prepare(&db_insert, "item_tag", "itemtagid", "itemid", "tag", "value", NULL);
	result = DBselect(
			"select i.itemid,a.name from items i"
			" join items_applications ip on i.itemid=ip.itemid"
			" join applications a on ip.applicationid=a.applicationid;");

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_DBROW2UINT64(itemid, row[0]);
		value = DBdyn_escape_string(row[1]);
		zbx_db_insert_add_values(&db_insert, itemtagid++, itemid, "Application", value);
		zbx_free(value);
	}
	DBfree_result(result);

	ret = zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);

	return ret;
}

static int	DBpatch_5030012(void)
{
	DB_ROW		row;
	DB_RESULT	result;
	zbx_uint64_t	itemid;
	int		ret = SUCCEED;
	char		*value;
	zbx_db_insert_t	db_insert;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	zbx_db_insert_prepare(&db_insert, "item_tag", "itemtagid", "itemid", "tag", "value", NULL);

	result = DBselect(
			"select i.itemid,ap.name from items i"
			" join item_application_prototype ip on i.itemid=ip.itemid"
			" join application_prototype ap on ip.application_prototypeid=ap.application_prototypeid;");

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_DBROW2UINT64(itemid, row[0]);
		value = DBdyn_escape_string(row[1]);
		zbx_db_insert_add_values(&db_insert, __UINT64_C(0), itemid, "Application", value);
		zbx_free(value);
	}
	DBfree_result(result);

	zbx_db_insert_autoincrement(&db_insert, "itemtagid");
	ret = zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);

	return ret;
}

static int	DBpatch_5030013(void)
{
	DB_ROW		row;
	DB_RESULT	result;
	zbx_uint64_t	httptestid, httptesttagid = 1;
	int		ret = SUCCEED;
	char		*value;
	zbx_db_insert_t	db_insert;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	zbx_db_insert_prepare(&db_insert, "httptest_tag", "httptesttagid", "httptestid", "tag", "value", NULL);
	result = DBselect(
			"select h.httptestid,a.name from httptest h"
			" join applications a on h.applicationid=a.applicationid;");

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_DBROW2UINT64(httptestid, row[0]);
		value = DBdyn_escape_string(row[1]);
		zbx_db_insert_add_values(&db_insert, httptesttagid++, httptestid, "Application", value);
		zbx_free(value);
	}
	DBfree_result(result);

	ret = zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);

	return ret;
}

static int	DBpatch_5030014(void)
{
	DB_ROW		row;
	DB_RESULT	result;
	zbx_uint64_t	selementid, selementtagid = 1;
	int		ret = SUCCEED;
	char		*value;
	zbx_db_insert_t	db_insert;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	zbx_db_insert_prepare(&db_insert, "sysmaps_element_tag", "selementtagid", "selementid", "tag", "value", NULL);
	result = DBselect(
			"select selementid,application from sysmaps_elements"
			" where elementtype in (0,3) and application<>'';");

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_DBROW2UINT64(selementid, row[0]);
		value = DBdyn_escape_string(row[1]);
		zbx_db_insert_add_values(&db_insert, selementtagid++, selementid, "Application", value);
		zbx_free(value);
	}
	DBfree_result(result);

	ret = zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);

	return ret;
}

static int	DBpatch_5030015(void)
{
#define CONDITION_TYPE_APPLICATION	15
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("update conditions set conditiontype=%d where conditiontype=%d",
			CONDITION_TYPE_ITEM_TAG, CONDITION_TYPE_APPLICATION))
	{
		return FAIL;
	}

	return SUCCEED;
#undef CONDITION_TYPE_APPLICATION
}

static int	DBpatch_5030016(void)
{
#define AUDIT_RESOURCE_APPLICATION	12
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("delete from auditlog where resourcetype=%d", AUDIT_RESOURCE_APPLICATION))
		return FAIL;

	return SUCCEED;
#undef AUDIT_RESOURCE_APPLICATION
}

static int	DBpatch_5030017(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("delete from profiles where idx in ("
			"'web.items.subfilter_apps','web.latest.filter.application',"
			"'web.overview.filter.application','web.applications.filter.active',"
			"'web.applications.filter_groups','web.applications.filter_hostids',"
			"'web.applications.php.sort','web.applications.php.sortorder')"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_5030018(void)
{
	return DBdrop_foreign_key("httptest", 1);
}

static int	DBpatch_5030019(void)
{
	return DBdrop_index("httptest", "httptest_1");
}

static int	DBpatch_5030020(void)
{
	return DBdrop_field("httptest", "applicationid");
}

static int	DBpatch_5030021(void)
{
	return DBdrop_field("sysmaps_elements", "application");
}

static int	DBpatch_5030022(void)
{
	return DBdrop_table("application_discovery");
}

static int	DBpatch_5030023(void)
{
	return DBdrop_table("item_application_prototype");
}

static int	DBpatch_5030024(void)
{
	return DBdrop_table("application_prototype");
}

static int	DBpatch_5030025(void)
{
	return DBdrop_table("application_template");
}

static int	DBpatch_5030026(void)
{
	return DBdrop_table("items_applications");
}

static int	DBpatch_5030027(void)
{
	return DBdrop_table("applications");
}

#endif

DBPATCH_START(5030)

/* version, duplicates flag, mandatory flag */

DBPATCH_ADD(5030000, 0, 1)
DBPATCH_ADD(5030001, 0, 1)
DBPATCH_ADD(5030002, 0, 1)
DBPATCH_ADD(5030003, 0, 1)
DBPATCH_ADD(5030004, 0, 1)
DBPATCH_ADD(5030005, 0, 1)
DBPATCH_ADD(5030006, 0, 1)
DBPATCH_ADD(5030007, 0, 1)
DBPATCH_ADD(5030008, 0, 1)
DBPATCH_ADD(5030009, 0, 1)
DBPATCH_ADD(5030010, 0, 1)
DBPATCH_ADD(5030011, 0, 1)
DBPATCH_ADD(5030012, 0, 1)
DBPATCH_ADD(5030013, 0, 1)
DBPATCH_ADD(5030014, 0, 1)
DBPATCH_ADD(5030015, 0, 1)
DBPATCH_ADD(5030016, 0, 1)
DBPATCH_ADD(5030017, 0, 1)
DBPATCH_ADD(5030018, 0, 1)
DBPATCH_ADD(5030019, 0, 1)
DBPATCH_ADD(5030020, 0, 1)
DBPATCH_ADD(5030021, 0, 1)
DBPATCH_ADD(5030022, 0, 1)
DBPATCH_ADD(5030023, 0, 1)
DBPATCH_ADD(5030024, 0, 1)
DBPATCH_ADD(5030025, 0, 1)
DBPATCH_ADD(5030026, 0, 1)
DBPATCH_ADD(5030027, 0, 1)

DBPATCH_END()
