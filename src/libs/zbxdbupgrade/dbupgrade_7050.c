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

#include "dbupgrade.h"

#include "dbupgrade_common.h"

#include "zbxdbschema.h"
#include "zbxdb.h"
#include "zbxnum.h"

/*
 * 8.0 development database patches
 */

#ifndef HAVE_SQLITE3

static int	DBpatch_7050000(void)
{
	const zbx_db_field_t	field = {"idp_certificate", "", NULL, NULL, 0, ZBX_TYPE_TEXT, ZBX_NOTNULL, 0};

	return DBadd_field("userdirectory_saml", &field);
}

static int	DBpatch_7050001(void)
{
	const zbx_db_field_t	field = {"sp_certificate", "", NULL, NULL, 0, ZBX_TYPE_TEXT, ZBX_NOTNULL, 0};

	return DBadd_field("userdirectory_saml", &field);
}

static int	DBpatch_7050002(void)
{
	const zbx_db_field_t	field = {"sp_private_key", "", NULL, NULL, 0, ZBX_TYPE_TEXT, ZBX_NOTNULL, 0};

	return DBadd_field("userdirectory_saml", &field);
}

static int	DBpatch_7050003(void)
{
	return DBdrop_foreign_key("event_recovery", 2);
}

static int	DBpatch_7050004(void)
{
	const zbx_db_field_t	field = {"r_eventid", NULL, "events", "eventid", 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0};

	return DBadd_foreign_key("event_recovery", 2, &field);
}

static int	DBpatch_7050005(void)
{
	return DBdrop_foreign_key("problem", 2);
}

static int	DBpatch_7050006(void)
{
	const zbx_db_field_t	field = {"r_eventid", NULL, "events", "eventid", 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0};

	return DBadd_foreign_key("problem", 2, &field);
}

static int	DBpatch_7050007(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > zbx_db_execute("insert into module (moduleid,id,relative_path,status,config) values"
		" (" ZBX_FS_UI64 ",'scatterplot','widgets/scatterplot',%d,'[]')", zbx_db_get_maxid("module"), 1))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_7050008(void)
{
	int		i;
	const char	*values[] = {
			"web.hosts.host_prototypes.php.sort", "web.hosts.host.prototype.list.sort",
			"web.hosts.host_prototypes.php.sortorder", "web.hosts.host.prototype.list.sortorder",
			"web.templates.host_prototypes.php.sort", "web.templates.host.prototype.list.sort",
			"web.templates.host_prototypes.php.sortorder", "web.templates.host.prototype.list.sortorder"
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

static int	DBpatch_7050009(void)
{
	const zbx_db_table_t	table =
			{"host_template_cache", "hostid, link_hostid", 0,
				{
					{"hostid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"link_hostid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_7050010(void)
{
	const zbx_db_field_t	field = {"hostid", NULL, "hosts", "hostid", 0, 0, 0,
			ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("host_template_cache", 1, &field);
}

static int	DBpatch_7050011(void)
{
	const zbx_db_field_t	field = {"link_hostid", NULL, "hosts", "hostid", 0, 0, 0,
			ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("host_template_cache", 2, &field);
}

static int	DBpatch_7050012(void)
{
	return DBcreate_index("host_template_cache", "host_template_cache_1", "link_hostid", 0);
}

static int	DBpatch_7050013(void)
{
	if (ZBX_DB_OK > zbx_db_execute(
			"insert into host_template_cache ("
			"	with recursive cte as ("
					"select h0.templateid,h0.hostid from hosts_templates h0"
					" union all "
					"select h1.templateid,c.hostid from cte c"
					" join hosts_templates h1 on c.templateid=h1.hostid"
				")"
				" select hostid,templateid from cte"
			")"))
	{
		return FAIL;
	}

	if (ZBX_DB_OK > zbx_db_execute("insert into host_template_cache (select hostid,hostid from hosts)"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_7050014(void)
{
	const zbx_db_table_t	table =
			{"item_template_cache", "itemid, link_hostid", 0,
				{
					{"itemid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"link_hostid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_7050015(void)
{
	const zbx_db_field_t	field = {"itemid", NULL, "items", "itemid", 0, 0, 0,
			ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("item_template_cache", 1, &field);
}

static int	DBpatch_7050016(void)
{
	const zbx_db_field_t	field = {"link_hostid", NULL, "hosts", "hostid", 0, 0, 0,
			ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("item_template_cache", 2, &field);
}

static int	DBpatch_7050017(void)
{
	return DBcreate_index("item_template_cache", "item_template_cache_1", "link_hostid", 0);
}

static int	DBpatch_7050018(void)
{
	/* 0 - ZBX_FLAG_DISCOVERY_NORMAL */
	/* 2 - ZBX_FLAG_DISCOVERY_PROTOTYPE */
	/* 4 - ZBX_FLAG_DISCOVERY_CREATED */

	if (ZBX_DB_OK > zbx_db_execute(
			"insert into item_template_cache ("
				"with recursive cte as ("
					"select i0.templateid,i0.itemid from items i0"
					" where i0.flags in (0,2,4)"
					" union all "
					"select i1.templateid,c.itemid from cte c"
					" join items i1 on c.templateid=i1.itemid"
					" where i1.templateid is not null"
				")"
				" select cte.itemid,h.hostid from cte,hosts h,items i"
				" where cte.templateid=i.itemid and i.hostid=h.hostid"
			")"))
	{
		return FAIL;
	}


	if (ZBX_DB_OK > zbx_db_execute(
			"insert into item_template_cache ("
				"select i.itemid,h.hostid from items i,hosts h"
				" where i.hostid=h.hostid and i.flags in (0,2,4)"
			")"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_7050019(void)
{
	/* 2 - ZBX_FLAG_DISCOVERY_PROTOTYPE */
	/* 6 - ZBX_FLAG_DISCOVERY_PROTOTYPE_CREATED (host prototype discovered via nested LLD) */
	if (ZBX_DB_OK > zbx_db_execute(
			"delete from httptestitem"
			" where httptestid in ("
				"select ht.httptestid from hosts h,httptest ht"
				" where h.hostid=ht.hostid and h.flags in (2,6)"
			")"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_7050020(void)
{
	/* 2 - ZBX_FLAG_DISCOVERY_PROTOTYPE */
	/* 6 - ZBX_FLAG_DISCOVERY_PROTOTYPE_CREATED (host prototype discovered via nested LLD) */
	if (ZBX_DB_OK > zbx_db_execute(
			"delete from httpstepitem"
			" where httpstepid in ("
				"select hts.httpstepid"
				" from hosts h,httptest ht,httpstep hts"
				" where h.hostid=ht.hostid"
					" and ht.httptestid=hts.httptestid and h.flags in (2,6)"
			")"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_7050021(void)
{
	/* 2 - ZBX_FLAG_DISCOVERY_PROTOTYPE */
	/* 6 - ZBX_FLAG_DISCOVERY_PROTOTYPE_CREATED (host prototype discovered via nested LLD) */
	if (ZBX_DB_OK > zbx_db_execute(
			"delete from item_tag"
			" where itemid in ("
				"select i.itemid from hosts h,items i"
				" where h.hostid=i.hostid and h.flags in (2,6)"
			")"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_7050022(void)
{
	/* 2 - ZBX_FLAG_DISCOVERY_PROTOTYPE */
	/* 6 - ZBX_FLAG_DISCOVERY_PROTOTYPE_CREATED (host prototype discovered via nested LLD) */
	if (ZBX_DB_OK > zbx_db_execute(
			"delete from items"
			" where exists ("
				"select null from hosts h"
				" where h.hostid=items.hostid and h.flags in (2,6)"
			")"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_7050023(void)
{
	/* 2 - ZBX_FLAG_DISCOVERY_PROTOTYPE */
	/* 6 - ZBX_FLAG_DISCOVERY_PROTOTYPE_CREATED (host prototype discovered via nested LLD) */
	if (ZBX_DB_OK > zbx_db_execute(
			"delete from httpstep_field"
			" where httpstepid in ("
				"select hts.httpstepid"
				" from hosts h,httptest ht,httpstep hts"
				" where h.hostid=ht.hostid"
					" and ht.httptestid=hts.httptestid and h.flags in (2,6)"
			")"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_7050024(void)
{
	/* 2 - ZBX_FLAG_DISCOVERY_PROTOTYPE */
	/* 6 - ZBX_FLAG_DISCOVERY_PROTOTYPE_CREATED (host prototype discovered via nested LLD) */
	if (ZBX_DB_OK > zbx_db_execute(
			"delete from httpstep"
			" where httptestid in ("
				"select ht.httptestid from hosts h,httptest ht"
				" where h.hostid=ht.hostid and h.flags in (2,6)"
			")"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_7050025(void)
{
	/* 2 - ZBX_FLAG_DISCOVERY_PROTOTYPE */
	/* 6 - ZBX_FLAG_DISCOVERY_PROTOTYPE_CREATED (host prototype discovered via nested LLD) */
	if (ZBX_DB_OK > zbx_db_execute(
			"delete from httptest_field"
			" where httptestid in ("
				"select ht.httptestid from hosts h,httptest ht"
				" where h.hostid=ht.hostid and h.flags in (2,6)"
			")"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_7050026(void)
{
	/* 2 - ZBX_FLAG_DISCOVERY_PROTOTYPE */
	/* 6 - ZBX_FLAG_DISCOVERY_PROTOTYPE_CREATED (host prototype discovered via nested LLD) */
	if (ZBX_DB_OK > zbx_db_execute(
			"delete from httptest"
			" where exists ("
				"select null from hosts h"
				" where h.hostid=httptest.hostid and h.flags in (2,6)"
			")"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_7050027(void)
{
	/* 3 - HOST_STATUS_TEMPLATE */
	if (ZBX_DB_OK > zbx_db_execute("delete from item_rtdata"
			" where exists ("
				"select null from items i,hosts h"
				" where item_rtdata.itemid=i.itemid"
					" and i.hostid=h.hostid and h.status=3"
				")"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_7050028(void)
{
	const zbx_db_field_t	field = {"automatic", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("trigger_tag", &field);
}

static int	DBpatch_7050029(void)
{
	if (ZBX_DB_OK > zbx_db_execute(
			"update trigger_tag"
			" set automatic=1"	/* ZBX_TAG_AUTOMATIC */
			" where triggerid in ("
				"select triggerid"
				" from trigger_discovery"
			")"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_7050030(void)
{
	if (ZBX_DB_OK > zbx_db_execute("delete from role_rule"
			" where name like 'api.method.%%'"
				" and value_str in ("
					"'*.massupdate',"
					"'host.massupdate',"
					"'hostgroup.massupdate',"
					"'template.massupdate',"
					"'templategroup.massupdate',"
					"'*.replacehostinterfaces',"
					"'hostinterface.replacehostinterfaces'"
				")"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_7050031(void)
{
	const zbx_db_table_t	table =
			{"history_json", "itemid,clock,ns", 0,
				{
					{"itemid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"clock", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"ns", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"value", NULL, NULL, NULL, 0, ZBX_TYPE_JSON, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_7050032(void)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	int				ret;
	zbx_db_insert_t	db_insert;

	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	zbx_db_insert_prepare(&db_insert, "widget_field", "widget_fieldid", "widgetid", "type", "name", "value_int",
			(char *)NULL);

	result = zbx_db_select("select w.widgetid"
			" from widget w"
			" join dashboard_page dp on w.dashboard_pageid=dp.dashboard_pageid"
			" join dashboard d on dp.dashboardid=d.dashboardid and d.templateid is null"
			" where w.type='scatterplot' or w.type='svggraph'");

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_uint64_t	widgetid;

		ZBX_STR2UINT64(widgetid, row[0]);
		zbx_db_insert_add_values(&db_insert, __UINT64_C(0), widgetid, 0, "show_hostnames", 1);
	}
	zbx_db_free_result(result);

	zbx_db_insert_autoincrement(&db_insert, "widget_fieldid");
	ret = zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);

	return ret;
}

static int	DBpatch_7050033(void)
{
	const zbx_db_field_t	field = {"value_str", "", NULL, NULL, 0, ZBX_TYPE_TEXT, ZBX_NOTNULL, 0};

	return DBmodify_field_type("widget_field", &field, NULL);
}

static int	DBpatch_7050034(void)
{
	return DBrename_table("housekeeper", "housekeeper_old");
}

static int	DBpatch_7050035(void)
{
	const zbx_db_table_t	table =
			{"housekeeper", "housekeeperid", 0,
				{
					{"housekeeperid", NULL, NULL, NULL, 0, ZBX_TYPE_SERIAL, ZBX_NOTNULL, 0},
					{"object", NULL, NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"objectid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_7050036(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	/* 0 - ZBX_HK_OBJECT_ITEM */
	/* 1 - ZBX_HK_OBJECT_TRIGGER */
	/* 2 - ZBX_HK_OBJECT_SERVICE */
	if (ZBX_DB_OK > zbx_db_execute("insert into housekeeper(object,objectid)"
			"select distinct"
			" case"
				" when tablename in ('history','history_str','history_log','history_uint',"
					"'history_text','history_bin','history_json','trends','trends_uint') then 0"
				" when tablename = 'events' and field = 'triggerid' then 1"
				" when tablename = 'events' and field = 'itemid' then 0"
				" when tablename = 'events' and field = 'lldruleid' then 0"
				" when tablename = 'events' and field = 'serviceid' then 2"
			" end as object,"
			" value as objectid"
			" from housekeeper_old"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_7050037(void)
{
	return DBdrop_table("housekeeper_old");
}

static int	DBpatch_7050038(void)
{
#ifdef HAVE_POSTGRESQL
	if (FAIL == zbx_db_index_exists("housekeeper", "housekeeper_pkey1"))
		return SUCCEED;

	return DBrename_index("housekeeper", "housekeeper_pkey1", "housekeeper_pkey",
			"housekeeperid", 1);
#else
	return SUCCEED;
#endif
}

static int	DBpatch_7050039(void)
{
	return DBcreate_housekeeper_trigger("items", "itemid");
}

static int	DBpatch_7050040(void)
{
	return DBcreate_housekeeper_trigger("triggers", "triggerid");
}

static int	DBpatch_7050041(void)
{
	return DBcreate_housekeeper_trigger("services", "serviceid");
}

static int	DBpatch_7050042(void)
{
	return DBcreate_housekeeper_trigger("dhosts", "dhostid");
}

static int	DBpatch_7050043(void)
{
	return DBcreate_housekeeper_trigger("dservices", "dserviceid");
}

static int	DBpatch_7050044(void)
{
	if (ZBX_DB_OK > zbx_db_execute("delete from ids where table_name='housekeeper'"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_7050045(void)
{
	return DBdrop_foreign_key("dhosts", 1);
}

static int	DBpatch_7050046(void)
{
	const zbx_db_field_t	field = {"druleid", NULL, "drules", "druleid", 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_foreign_key("dhosts", 1, &field);
}

static int	DBpatch_7050047(void)
{
	return DBdrop_foreign_key("dservices", 1);
}

static int	DBpatch_7050048(void)
{
	const zbx_db_field_t	field = {"dhostid", NULL, "dhosts", "dhostid", 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_foreign_key("dservices", 1, &field);
}

static int	DBpatch_7050049(void)
{
	return DBdrop_foreign_key("dservices", 2);
}

static int	DBpatch_7050050(void)
{
	const zbx_db_field_t	field = {"dcheckid", NULL, "dchecks", "dcheckid", 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_foreign_key("dservices", 2, &field);
}

#endif

DBPATCH_START(7050)

/* version, duplicates flag, mandatory flag */

DBPATCH_ADD(7050000, 0, 1)
DBPATCH_ADD(7050001, 0, 1)
DBPATCH_ADD(7050002, 0, 1)
DBPATCH_ADD(7050003, 0, 1)
DBPATCH_ADD(7050004, 0, 1)
DBPATCH_ADD(7050005, 0, 1)
DBPATCH_ADD(7050006, 0, 1)
DBPATCH_ADD(7050007, 0, 1)
DBPATCH_ADD(7050008, 0, 1)
DBPATCH_ADD(7050009, 0, 1)
DBPATCH_ADD(7050010, 0, 1)
DBPATCH_ADD(7050011, 0, 1)
DBPATCH_ADD(7050012, 0, 1)
DBPATCH_ADD(7050013, 0, 1)
DBPATCH_ADD(7050014, 0, 1)
DBPATCH_ADD(7050015, 0, 1)
DBPATCH_ADD(7050016, 0, 1)
DBPATCH_ADD(7050017, 0, 1)
DBPATCH_ADD(7050018, 0, 1)
DBPATCH_ADD(7050019, 0, 1)
DBPATCH_ADD(7050020, 0, 1)
DBPATCH_ADD(7050021, 0, 1)
DBPATCH_ADD(7050022, 0, 1)
DBPATCH_ADD(7050023, 0, 1)
DBPATCH_ADD(7050024, 0, 1)
DBPATCH_ADD(7050025, 0, 1)
DBPATCH_ADD(7050026, 0, 1)
DBPATCH_ADD(7050027, 0, 1)
DBPATCH_ADD(7050028, 0, 1)
DBPATCH_ADD(7050029, 0, 1)
DBPATCH_ADD(7050030, 0, 1)
DBPATCH_ADD(7050031, 0, 1)
DBPATCH_ADD(7050032, 0, 1)
DBPATCH_ADD(7050033, 0, 1)
DBPATCH_ADD(7050034, 0, 1)
DBPATCH_ADD(7050035, 0, 1)
DBPATCH_ADD(7050036, 0, 1)
DBPATCH_ADD(7050037, 0, 1)
DBPATCH_ADD(7050038, 0, 1)
DBPATCH_ADD(7050039, 0, 1)
DBPATCH_ADD(7050040, 0, 1)
DBPATCH_ADD(7050041, 0, 1)
DBPATCH_ADD(7050042, 0, 1)
DBPATCH_ADD(7050043, 0, 1)
DBPATCH_ADD(7050044, 0, 1)
DBPATCH_ADD(7050045, 0, 1)
DBPATCH_ADD(7050046, 0, 1)
DBPATCH_ADD(7050047, 0, 1)
DBPATCH_ADD(7050048, 0, 1)
DBPATCH_ADD(7050049, 0, 1)
DBPATCH_ADD(7050050, 0, 1)

DBPATCH_END()
