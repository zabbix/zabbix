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
#include "zbxalgo.h"

/*
 * 7.2 development database patches
 */

#ifndef HAVE_SQLITE3

static int	DBpatch_7010000(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > zbx_db_execute(
			"update profiles"
				" set value_str='operating_mode'"
				" where idx='web.proxies.php.sort'"
				" and value_str like 'status'"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_7010001(void)
{
	if (FAIL == zbx_db_index_exists("auditlog", "auditlog_4"))
		return DBcreate_index("auditlog", "auditlog_4", "recordsetid", 0);

	return SUCCEED;
}

static int	DBpatch_7010002(void)
{
	int		i;
	const char	*values[] = {
			"web.avail_report.filter.active", "web.availabilityreport.filter.active",
			"web.avail_report.filter.from", "web.availabilityreport.filter.from",
			"web.avail_report.filter.to", "web.availabilityreport.filter.to",
			"web.avail_report.mode", "web.availabilityreport.filter.mode",
			"web.avail_report.0.groupids", "web.availabilityreport.filter.0.host_groups",
			"web.avail_report.0.hostids", "web.availabilityreport.filter.0.hosts",
			"web.avail_report.1.groupid", "web.availabilityreport.filter.1.template_groups",
			"web.avail_report.1.hostid", "web.availabilityreport.filter.1.templates",
			"web.avail_report.1.tpl_triggerid", "web.availabilityreport.filter.1.triggers",
			"web.avail_report.1.hostgroupid", "web.availabilityreport.filter.1.host_groups"
		};

	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	for (i = 0; i < (int)ARRSIZE(values); i += 2)
	{
		if (ZBX_DB_OK > zbx_db_execute("update profiles set idx='%s' where idx='%s'", values[i + 1], values[i]))
			return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_7010003(void)
{
	if (SUCCEED == zbx_db_index_exists("userdirectory_usrgrp", "userdirectory_usrgrp_3"))
		return DBdrop_index("userdirectory_usrgrp", "userdirectory_usrgrp_3");

	return SUCCEED;
}

static int	DBpatch_7010004(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > zbx_db_execute(
			"delete from widget_field"
			" where name in ('time_size','date_size','tzone_size')"
				" and widgetid in (select widgetid from widget where type='clock')"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_7010005(void)
{
	if (FAIL == zbx_db_index_exists("items", "items_10"))
		return DBcreate_index("items", "items_10", "uuid", 0);

	return SUCCEED;
}

static int	DBpatch_7010006(void)
{
	if (FAIL == zbx_db_index_exists("hosts", "hosts_9"))
		return DBcreate_index("hosts", "hosts_9", "uuid", 0);

	return SUCCEED;
}

static int	DBpatch_7010007(void)
{
	if (FAIL == zbx_db_index_exists("hstgrp", "hstgrp_2"))
		return DBcreate_index("hstgrp", "hstgrp_2", "uuid", 0);

	return SUCCEED;
}

static int	DBpatch_7010008(void)
{
	if (FAIL == zbx_db_index_exists("httptest", "httptest_5"))
		return DBcreate_index("httptest", "httptest_5", "uuid", 0);

	return SUCCEED;
}

static int	DBpatch_7010009(void)
{
	if (FAIL == zbx_db_index_exists("valuemap", "valuemap_2"))
		return DBcreate_index("valuemap", "valuemap_2", "uuid", 0);

	return SUCCEED;
}

static int	DBpatch_7010010(void)
{
	if (FAIL == zbx_db_index_exists("triggers", "triggers_4"))
		return DBcreate_index("triggers", "triggers_4", "uuid", 0);

	return SUCCEED;
}

static int	DBpatch_7010011(void)
{
	if (FAIL == zbx_db_index_exists("graphs", "graphs_5"))
		return DBcreate_index("graphs", "graphs_5", "uuid", 0);

	return SUCCEED;
}

static int	DBpatch_7010012(void)
{
	if (FAIL == zbx_db_index_exists("services", "services_1"))
		return DBcreate_index("services", "services_1", "uuid", 0);

	return SUCCEED;
}

static int	DBpatch_7010013(void)
{
	if (FAIL == zbx_db_index_exists("dashboard", "dashboard_3"))
		return DBcreate_index("dashboard", "dashboard_3", "uuid", 0);

	return SUCCEED;
}

static int	DBpatch_7010014(void)
{
	if (FAIL == zbx_db_index_exists("auditlog", "auditlog_5"))
		return DBcreate_index("auditlog", "auditlog_5", "ip", 0);

	return SUCCEED;
}

static int	DBpatch_7010015(void)
{
	return DBdrop_index("proxy_history", "proxy_history_1");
}

static int	DBpatch_7010016(void)
{
	if (FAIL == zbx_db_index_exists("proxy_history", "proxy_history_2"))
		return DBcreate_index("proxy_history", "proxy_history_2", "write_clock", 0);

	return SUCCEED;
}

static int	DBpatch_7010017(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > zbx_db_execute(
			"update widget_field"
			" set value_int="
				"case when value_int=1 then 0"
				" when value_int=2 then 1"
				" when value_int=3 then 2 end"
			" where name like 'columns.%%.history'"
				" and exists ("
					"select null"
					" from widget w"
					" where widget_field.widgetid=w.widgetid"
						" and w.type='tophosts'"
				")"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_7010018(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > zbx_db_execute(
			"delete from widget_field"
			" where ((name like 'columns.%%.base_color' and value_str='')"
				" or (name like 'columns.%%.display' and value_int=1)"
				" or (name like 'columns.%%.decimal_places' and value_int=2)"
				" or (name like 'columns.%%.aggregate_function' and value_int=0)"
				" or (name like 'columns.%%.history' and value_int=0))"
				" and exists ("
					"select null"
					" from widget w"
					" where widget_field.widgetid=w.widgetid"
						" and w.type='tophosts'"
				")"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_7010019(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	/* 0 - HOST_STATUS_MONITORED */
	/* 1 - HOST_STATUS_NOT_MONITORED */
	if (ZBX_DB_OK > zbx_db_execute(
			"update items"
				" set uuid=''"
				" where hostid in (select hostid from hosts where status in (0,1))"
					" and uuid" ZBX_SQL_STRCMP, ZBX_SQL_STRVAL_NE("")))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_7010020(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > zbx_db_execute("insert into module (moduleid,id,relative_path,status,config) values"
			" (" ZBX_FS_UI64 ",'hostcard','widgets/hostcard',%d,'[]')", zbx_db_get_maxid("module"), 1))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_7010021(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > zbx_db_execute(
			"update widget_field"
				" set name=replace(name,'tags','columns.0.item_tags')"
				" where widgetid in (select widgetid from widget where type='dataover')"
					" and name like 'tags.%%'"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_7010022(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > zbx_db_execute(
			"update widget_field"
				" set name='columns.0.item_tags_evaltype'"
				" where widgetid in ("
					"select widgetid from widget where type='dataover'"
				")"
					" and name='evaltype'"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_7010023(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > zbx_db_execute(
			"update widget_field"
				" set name='layout'"
				" where widgetid in ("
					"select widgetid from widget where type='dataover' or type='trigover'"
				")"
					" and name='style'"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_7010024(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > zbx_db_execute("update widget set type='topitems' where type='dataover'"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_7010025(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > zbx_db_execute(
			"update module set id='topitems',relative_path='widgets/topitems' where id='dataover'"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_7010026(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > zbx_db_execute(
			"update widget_field"
				" set name='problems',value_int=0"	/* 0 - PROBLEMS_ALL */
				" where widgetid in (select widgetid from widget where type='topitems')"
					" and name='show_suppressed'"))
	{
		return FAIL;
	}

	return SUCCEED;
}

#define ZBX_WIDGET_FIELD_TYPE_INT32	0

static int	DBpatch_7010027(void)
{
	int			size, ret = SUCCEED;
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	zbx_vector_uint64_t	widgetids;
	zbx_db_insert_t		db_insert;

	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	zbx_vector_uint64_create(&widgetids);

	if (NULL == (result = zbx_db_select("select max_overview_table_size from config")) ||
			NULL == (row = zbx_db_fetch(result)))
	{
		ret = FAIL;
		goto out;
	}

	size = atoi(row[0]);

	/* 10 - DEFAULT_HOSTS_COUNT / DEFAULT_ITEMS_COUNT */
	if (10 == size)
		goto out;

	/* 100 - ZBX_MAX_WIDGET_LINES */
	if (100 < size)
		size = 100;

	zbx_db_select_uint64("select widgetid from widget where type='topitems'", &widgetids);

	if (0 == widgetids.values_num)
		goto out;

	zbx_db_insert_prepare(&db_insert, "widget_field", "widget_fieldid", "widgetid", "type", "name", "value_int",
			(char *)NULL);

	for (int i = 0; i < widgetids.values_num; i++)
	{
		zbx_db_insert_add_values(&db_insert, __UINT64_C(0), widgetids.values[i], ZBX_WIDGET_FIELD_TYPE_INT32,
				"host_ordering_limit", size);
		zbx_db_insert_add_values(&db_insert, __UINT64_C(0), widgetids.values[i], ZBX_WIDGET_FIELD_TYPE_INT32,
				"item_ordering_limit", size);
	}

	zbx_db_insert_autoincrement(&db_insert, "widget_fieldid");
	ret = zbx_db_insert_execute(&db_insert);

	zbx_db_insert_clean(&db_insert);
out:
	zbx_vector_uint64_destroy(&widgetids);
	zbx_db_free_result(result);

	return ret;
}

static int	DBpatch_7010028(void)
{
	int			ret = SUCCEED;
	zbx_vector_uint64_t	widgetids;
	zbx_db_insert_t		db_insert;

	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	zbx_vector_uint64_create(&widgetids);

	zbx_db_select_uint64("select widgetid from widget where type='topitems'", &widgetids);

	if (0 == widgetids.values_num)
		goto out;

	zbx_db_insert_prepare(&db_insert, "widget_field", "widget_fieldid", "widgetid", "type", "name", "value_int",
			"value_str", (char *)NULL);

	for (int i = 0; i < widgetids.values_num; i++)
	{
#		define ZBX_WIDGET_FIELD_TYPE_STR	1
#		define ORDERBY_ITEM_NAME		2
		zbx_db_insert_add_values(&db_insert, __UINT64_C(0), widgetids.values[i], ZBX_WIDGET_FIELD_TYPE_STR,
				"columns.0.items.0", 0, "*");
		zbx_db_insert_add_values(&db_insert, __UINT64_C(0), widgetids.values[i], ZBX_WIDGET_FIELD_TYPE_INT32,
				"item_ordering_order_by", ORDERBY_ITEM_NAME, "");
#		undef ORDERBY_ITEM_NAME
#		undef ZBX_WIDGET_FIELD_TYPE_STR
	}

	zbx_db_insert_autoincrement(&db_insert, "widget_fieldid");
	ret = zbx_db_insert_execute(&db_insert);

	zbx_db_insert_clean(&db_insert);
out:
	zbx_vector_uint64_destroy(&widgetids);

	return ret;
}

static int	DBpatch_7010029(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > zbx_db_execute(
			"update profiles"
				" set value_str='topitems'"
				" where idx='web.dashboard.last_widget_type'"
					" and value_str='dataover'"))
	{
		return FAIL;
	}

	return SUCCEED;
}

#undef ZBX_WIDGET_FIELD_TYPE_INT32

#endif

DBPATCH_START(7010)

/* version, duplicates flag, mandatory flag */

DBPATCH_ADD(7010000, 0, 1)
DBPATCH_ADD(7010001, 0, 1)
DBPATCH_ADD(7010002, 0, 1)
DBPATCH_ADD(7010003, 0, 1)
DBPATCH_ADD(7010004, 0, 1)
DBPATCH_ADD(7010005, 0, 1)
DBPATCH_ADD(7010006, 0, 1)
DBPATCH_ADD(7010007, 0, 1)
DBPATCH_ADD(7010008, 0, 1)
DBPATCH_ADD(7010009, 0, 1)
DBPATCH_ADD(7010010, 0, 1)
DBPATCH_ADD(7010011, 0, 1)
DBPATCH_ADD(7010012, 0, 1)
DBPATCH_ADD(7010013, 0, 1)
DBPATCH_ADD(7010014, 0, 1)
DBPATCH_ADD(7010015, 0, 1)
DBPATCH_ADD(7010016, 0, 1)
DBPATCH_ADD(7010017, 0, 1)
DBPATCH_ADD(7010018, 0, 1)
DBPATCH_ADD(7010019, 0, 1)
DBPATCH_ADD(7010020, 0, 1)
DBPATCH_ADD(7010021, 0, 1)
DBPATCH_ADD(7010022, 0, 1)
DBPATCH_ADD(7010023, 0, 1)
DBPATCH_ADD(7010024, 0, 1)
DBPATCH_ADD(7010025, 0, 1)
DBPATCH_ADD(7010026, 0, 1)
DBPATCH_ADD(7010027, 0, 1)
DBPATCH_ADD(7010028, 0, 1)
DBPATCH_ADD(7010029, 0, 1)

DBPATCH_END()
