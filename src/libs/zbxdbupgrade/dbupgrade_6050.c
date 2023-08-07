/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

#include "zbxdbschema.h"
#include "zbxdbhigh.h"

/*
 * 7.0 development database patches
 */

#ifndef HAVE_SQLITE3

static int	DBpatch_6050000(void)
{
	const zbx_db_field_t	field = {"url", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("config", &field, NULL);
}

static int	DBpatch_6050001(void)
{
	const zbx_db_field_t	field = {"geomaps_tile_url", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("config", &field, NULL);
}

static int	DBpatch_6050002(void)
{
	const zbx_db_field_t	field = {"url", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("sysmap_url", &field, NULL);
}

static int	DBpatch_6050003(void)
{
	const zbx_db_field_t	field = {"url", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("sysmap_element_url", &field, NULL);
}

static int	DBpatch_6050004(void)
{
	const zbx_db_field_t	field = {"url_a", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("host_inventory", &field, NULL);
}

static int	DBpatch_6050005(void)
{
	const zbx_db_field_t	field = {"url_b", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("host_inventory", &field, NULL);
}

static int	DBpatch_6050006(void)
{
	const zbx_db_field_t	field = {"url_c", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("host_inventory", &field, NULL);
}

static int	DBpatch_6050007(void)
{
	const zbx_db_field_t	field = {"value_str", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("widget_field", &field, NULL);
}

static int	DBpatch_6050008(void)
{
	const zbx_db_field_t	field = {"value", "0.0000", NULL, NULL, 0, ZBX_TYPE_FLOAT, ZBX_NOTNULL, 0};

	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

#if defined(HAVE_ORACLE)
	if (SUCCEED == zbx_db_check_oracle_colum_type("history", "value", ZBX_TYPE_FLOAT))
		return SUCCEED;
#endif /* defined(HAVE_ORACLE) */

	return DBmodify_field_type("history", &field, &field);
}

static int	DBpatch_6050009(void)
{
	const zbx_db_field_t	field = {"value_min", "0.0000", NULL, NULL, 0, ZBX_TYPE_FLOAT, ZBX_NOTNULL, 0};

	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

#if defined(HAVE_ORACLE)
	if (SUCCEED == zbx_db_check_oracle_colum_type("trends", "value_min", ZBX_TYPE_FLOAT))
		return SUCCEED;
#endif /* defined(HAVE_ORACLE) */

	return DBmodify_field_type("trends", &field, &field);
}

static int	DBpatch_6050010(void)
{
	const zbx_db_field_t	field = {"value_avg", "0.0000", NULL, NULL, 0, ZBX_TYPE_FLOAT, ZBX_NOTNULL, 0};

	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

#if defined(HAVE_ORACLE)
	if (SUCCEED == zbx_db_check_oracle_colum_type("trends", "value_avg", ZBX_TYPE_FLOAT))
		return SUCCEED;
#endif /* defined(HAVE_ORACLE) */

	return DBmodify_field_type("trends", &field, &field);
}

static int	DBpatch_6050011(void)
{
	const zbx_db_field_t	field = {"value_max", "0.0000", NULL, NULL, 0, ZBX_TYPE_FLOAT, ZBX_NOTNULL, 0};

#if defined(HAVE_ORACLE)
	if (SUCCEED == zbx_db_check_oracle_colum_type("trends", "value_max", ZBX_TYPE_FLOAT))
		return SUCCEED;
#endif /* defined(HAVE_ORACLE) */

	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	return DBmodify_field_type("trends", &field, &field);
}

static int	DBpatch_6050012(void)
{
	const zbx_db_field_t	field = {"allow_redirect", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("dchecks", &field);
}

static int	DBpatch_6050013(void)
{
	const zbx_db_table_t	table =
			{"history_bin", "itemid,clock,ns", 0,
				{
					{"itemid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"clock", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"ns", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"value", "", NULL, NULL, 0, ZBX_TYPE_BLOB, ZBX_NOTNULL, 0},
					{NULL}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_6050014(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > zbx_db_execute(
			"delete from widget_field"
			" where name='adv_conf' and widgetid in ("
				"select widgetid"
				" from widget"
				" where type in ('clock', 'item')"
			")"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_6050015(void)
{
	const zbx_db_field_t	field = {"http_user", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL | ZBX_PROXY, 0};

	return DBmodify_field_type("httptest", &field, NULL);
}

static int	DBpatch_6050016(void)
{
	const zbx_db_field_t	field = {"http_password", "", NULL, NULL, 255, ZBX_TYPE_CHAR,
			ZBX_NOTNULL | ZBX_PROXY, 0};

	return DBmodify_field_type("httptest", &field, NULL);
}

static int	DBpatch_6050017(void)
{
	const zbx_db_field_t	field = {"username", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL | ZBX_PROXY, 0};

	return DBmodify_field_type("items", &field, NULL);
}

static int	DBpatch_6050018(void)
{
	const zbx_db_field_t	field = {"password", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL | ZBX_PROXY, 0};

	return DBmodify_field_type("items", &field, NULL);
}

static int	DBpatch_6050019(void)
{
	const zbx_db_field_t	field = {"username", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("connector", &field, NULL);
}

static int	DBpatch_6050020(void)
{
	const zbx_db_field_t	field = {"password", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("connector", &field, NULL);
}

static int	DBpatch_6050021(void)
{
	const zbx_db_field_t	field = {"concurrency_max", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("drules", &field);
}

static int	DBpatch_6050022(void)
{
	if (ZBX_DB_OK > zbx_db_execute("update drules set concurrency_max=1"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_6050023(void)
{
	const char	*sql =
			"update widget_field"
			" set name='acknowledgement_status'"
			" where name='unacknowledged'"
				" and exists ("
					"select null"
					" from widget w"
					" where widget_field.widgetid=w.widgetid"
						" and w.type='problems'"
				")";

	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK <= zbx_db_execute("%s", sql))
		return SUCCEED;

	return FAIL;
}

static int	DBpatch_6050024(void)
{
	const char	*sql =
			"update widget_field"
			" set name='show_lines'"
			" where name='count'"
				" and exists ("
					"select null"
					" from widget w"
					" where widget_field.widgetid=w.widgetid"
						" and w.type='tophosts'"
				")";

	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK <= zbx_db_execute("%s", sql))
		return SUCCEED;

	return FAIL;
}

static int	DBpatch_6050025(void)
{
	if (FAIL == zbx_db_index_exists("problem", "problem_4"))
		return DBcreate_index("problem", "problem_4", "cause_eventid", 0);

	return SUCCEED;
}

static int	DBpatch_6050026(void)
{
	const zbx_db_field_t	field = {"id", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0};

	return DBdrop_field_autoincrement("proxy_history", &field);

	return SUCCEED;
}

static int	DBpatch_6050027(void)
{
	const zbx_db_field_t	field = {"id", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0};

	return DBdrop_field_autoincrement("proxy_dhistory", &field);

	return SUCCEED;
}

static int	DBpatch_6050028(void)
{
	const zbx_db_field_t	field = {"id", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0};

	return DBdrop_field_autoincrement("proxy_autoreg_host", &field);

	return SUCCEED;
}

static int	DBpatch_6050029(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > zbx_db_execute("insert into module (moduleid,id,relative_path,status,config) values"
			" (" ZBX_FS_UI64 ",'gauge','widgets/gauge',%d,'[]')", zbx_db_get_maxid("module"), 1))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_6050030(void)
{
	const zbx_db_table_t table =
			{"optag", "optagid", 0,
				{
					{"optagid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"operationid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"tag", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"value", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int  DBpatch_6050031(void)
{
	return DBcreate_index("optag", "optag_1", "operationid", 0);
}

static int	DBpatch_6050032(void)
{
	const zbx_db_field_t	field = {"operationid", NULL, "operations", "operationid", 0, 0, 0,
			ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("optag", 1, &field);
}

static int	DBpatch_6050033(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > zbx_db_execute("insert into module (moduleid,id,relative_path,status,config) values"
			" (" ZBX_FS_UI64 ",'toptriggers','widgets/toptriggers',%d,'[]')", zbx_db_get_maxid("module"), 1))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_6050034(void)
{
	return DBrename_table("group_discovery", "group_discovery_tmp");
}

static int	DBpatch_6050035(void)
{
	const zbx_db_table_t	table =
			{"group_discovery", "groupdiscoveryid", 0,
				{
					{"groupdiscoveryid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"groupid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"parent_group_prototypeid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"name", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"lastcheck", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"ts_delete", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_6050036(void)
{
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	zbx_db_insert_t		db_insert;
	int			ret = SUCCEED;

	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	result = zbx_db_select("select groupid,parent_group_prototypeid,name,lastcheck,ts_delete"
					" from group_discovery_tmp");

	zbx_db_insert_prepare(&db_insert, "group_discovery", "groupdiscoveryid", "groupid", "parent_group_prototypeid",
			"name", "lastcheck", "ts_delete", NULL);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_uint64_t	groupid, parent_group_prototypeid;

		ZBX_DBROW2UINT64(groupid, row[0]);
		ZBX_DBROW2UINT64(parent_group_prototypeid, row[1]);

		zbx_db_insert_add_values(&db_insert, __UINT64_C(0), groupid, parent_group_prototypeid, row[2],
				atoi(row[3]), atoi(row[4]));
	}

	zbx_db_free_result(result);

	zbx_db_insert_autoincrement(&db_insert, "groupdiscoveryid");

	ret = zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);

	return ret;
}

static int	DBpatch_6050037(void)
{
	return DBdrop_table("group_discovery_tmp");
}

static int	DBpatch_6050038(void)
{
	return DBcreate_index("group_discovery", "group_discovery_1", "groupid,parent_group_prototypeid", 1);
}

static int	DBpatch_6050039(void)
{
	return DBcreate_index("group_discovery", "group_discovery_2", "parent_group_prototypeid", 0);
}

static int	DBpatch_6050040(void)
{
	const zbx_db_field_t	field = {"groupid", NULL, "hstgrp", "groupid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("group_discovery", 1, &field);
}

static int	DBpatch_6050041(void)
{
	const zbx_db_field_t	field = {"parent_group_prototypeid", NULL, "group_prototype", "group_prototypeid", 0, 0,
			0, 0};

	return DBadd_foreign_key("group_discovery", 2, &field);
}

#endif

DBPATCH_START(6050)

/* version, duplicates flag, mandatory flag */

DBPATCH_ADD(6050000, 0, 1)
DBPATCH_ADD(6050001, 0, 1)
DBPATCH_ADD(6050002, 0, 1)
DBPATCH_ADD(6050003, 0, 1)
DBPATCH_ADD(6050004, 0, 1)
DBPATCH_ADD(6050005, 0, 1)
DBPATCH_ADD(6050006, 0, 1)
DBPATCH_ADD(6050007, 0, 1)
DBPATCH_ADD(6050008, 0, 1)
DBPATCH_ADD(6050009, 0, 1)
DBPATCH_ADD(6050010, 0, 1)
DBPATCH_ADD(6050011, 0, 1)
DBPATCH_ADD(6050012, 0, 1)
DBPATCH_ADD(6050013, 0, 1)
DBPATCH_ADD(6050014, 0, 1)
DBPATCH_ADD(6050015, 0, 1)
DBPATCH_ADD(6050016, 0, 1)
DBPATCH_ADD(6050017, 0, 1)
DBPATCH_ADD(6050018, 0, 1)
DBPATCH_ADD(6050019, 0, 1)
DBPATCH_ADD(6050020, 0, 1)
DBPATCH_ADD(6050021, 0, 1)
DBPATCH_ADD(6050022, 0, 1)
DBPATCH_ADD(6050023, 0, 1)
DBPATCH_ADD(6050024, 0, 1)
DBPATCH_ADD(6050025, 0, 1)
DBPATCH_ADD(6050026, 0, 1)
DBPATCH_ADD(6050027, 0, 1)
DBPATCH_ADD(6050028, 0, 1)
DBPATCH_ADD(6050029, 0, 1)
DBPATCH_ADD(6050030, 0, 1)
DBPATCH_ADD(6050031, 0, 1)
DBPATCH_ADD(6050032, 0, 1)
DBPATCH_ADD(6050033, 0, 1)
DBPATCH_ADD(6050034, 0, 1)
DBPATCH_ADD(6050035, 0, 1)
DBPATCH_ADD(6050036, 0, 1)
DBPATCH_ADD(6050037, 0, 1)
DBPATCH_ADD(6050038, 0, 1)
DBPATCH_ADD(6050039, 0, 1)
DBPATCH_ADD(6050040, 0, 1)
DBPATCH_ADD(6050041, 0, 1)

DBPATCH_END()
