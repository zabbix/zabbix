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

static int	DBpatch_7050051(void)
{
	int			ret = SUCCEED;
	zbx_db_insert_t		db_insert;
	zbx_db_row_t		row;

	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	zbx_db_result_t	result = zbx_db_select("select i.itemid,t.tag,t.value from httptest_tag t"
		" join ("
			"select hsi.itemid,hs.httptestid from httpstepitem hsi"
			" join httpstep hs on hs.httpstepid=hsi.httpstepid"
			" union"
			" select hti.itemid,hti.httptestid from httptestitem hti"
		") as i"
		" on i.httptestid=t.httptestid"
		" where not exists ("
			"select null from item_tag it"
			" where it.itemid=i.itemid and it.tag=t.tag"
		")");

	if (NULL == result)
		return FAIL;

	zbx_db_insert_prepare(&db_insert, "item_tag", "itemtagid", "itemid", "tag", "value", (char *)NULL);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_uint64_t	itemid;

		ZBX_DBROW2UINT64(itemid, row[0]);
		zbx_db_insert_add_values(&db_insert, __UINT64_C(0), itemid, row[1], row[2]);
	}

	zbx_db_insert_autoincrement(&db_insert, "itemtagid");
	ret = zbx_db_insert_execute(&db_insert);

	zbx_db_insert_clean(&db_insert);

	zbx_db_free_result(result);

	return ret;
}

static int	DBpatch_7050052(void)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	char		*sql = NULL;
	size_t		sql_alloc = 0, sql_offset = 0;
	int		ret = SUCCEED;

	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	result = zbx_db_select("select profileid,value_str from profiles"
			" where idx='web.messages' and source='triggers.severities'");

	while (NULL != (row = zbx_db_fetch(result)) && SUCCEED == ret)
	{
		const char	*p = row[1];
		int		count, i, valid = 1;
		struct zbx_json	json;

		/* Validate and parse PHP serialized array header: a:N:{ */
		if ('a' != *p || ':' != *(p + 1))
			continue;

		p += 2;

		count = *p++ - '0';
		if (6 < count || ':' != *p || '{' != *(p + 1))
			continue;

		p += 2;

		zbx_json_initarray(&json, 64);

		for (i = 0; i < count; i++)
		{
			int	key;

			/* Parse key: i:N; */
			if ('i' != *p || ':' != *(p + 1) || '0' > *(p + 2) || *(p + 2) > '5' || ';' != *(p + 3))
			{
				valid = 0;
				break;
			}

			key = *(p + 2) - '0';
			p += 4;

			/* Skip value: i:N; or s:"N"; */
			while ('\0' != *p && ';' != *p)
				p++;

			if (';' != *p)
			{
				valid = 0;
				break;
			}
			p++;

			zbx_json_addint64(&json, NULL, (zbx_int64_t)key);
		}

		if (1 == valid && '}' == *p)
		{
			char	*value_str_esc = zbx_db_dyn_escape_string(json.buffer);

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
					"update profiles set value_str='%s' where profileid=%s;\n",
					value_str_esc, row[0]);
			zbx_free(value_str_esc);

			ret = zbx_db_execute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
		}

		zbx_json_free(&json);
	}

	zbx_db_free_result(result);

	if (SUCCEED == ret && ZBX_DB_OK > zbx_db_flush_overflowed_sql(sql, sql_offset))
		ret = FAIL;

	zbx_free(sql);

	return ret;
}

static int	DBpatch_7050053(void)
{
	zbx_db_result_t	result;
	const char	*macro = "{$TRAPPER.ALLOWED_HOSTS}";
	int		ret = SUCCEED;

	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (NULL == (result = zbx_db_select("select macro from globalmacro where macro='%s'", macro)))
		return FAIL;

	if (NULL == zbx_db_fetch(result))
	{
		if (ZBX_DB_OK > zbx_db_execute("insert into globalmacro (globalmacroid,macro,value,description)"
				" values (" ZBX_FS_UI64 ",'%s','127.0.0.1,::1','')", zbx_db_get_maxid("globalmacro"),
				macro))
		{
			ret = FAIL;
		}
	}

	zbx_db_free_result(result);

	return ret;
}

static int	DBpatch_7050054(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	/* 2  - ITEM_TYPE_TRAPPER   */
	/* 19 - ITEM_TYPE_HTTPAGENT */
	if (ZBX_DB_OK > zbx_db_execute(
			"update items"
				" set trapper_hosts='0.0.0.0/0,::/0'"
				" where (type=2 or (type=19 and allow_traps=1))"
					" and trapper_hosts=''"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_7050055(void)
{
	if (ZBX_DB_OK > zbx_db_execute("update settings set value_str='' where name='session_key'"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_7050056(void)
{
	const zbx_db_field_t	field = {"userid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0};

	return DBdrop_not_null("acknowledges", &field);
}

static int	DBpatch_7050057(void)
{
	const zbx_db_field_t	field = {"maintenanceid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_field("acknowledges", &field);
}

static int	DBpatch_7050058(void)
{
	return DBdrop_foreign_key("acknowledges", 1);
}

static int	DBpatch_7050059(void)
{
	return DBdrop_foreign_key("event_suppress", 2);
}

static int	DBpatch_7050060(void)
{
	return SUCCEED;
}

static int	DBpatch_7050061(void)
{
	const zbx_db_field_t	field = {"auto_start", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBset_default("dashboard", &field);
}

static int	DBpatch_7050062(void)
{
#define ZBX_COLORPALETTE_LIGHT	"1A7C11,F63100,2774A4,A54F10,FC6EA3,6C59DC,AC8C14,611F27,F230E0,5CCD18,BB2A02,"	\
				"5A2B57,89ABF8,7EC25C,274482,2B5429,8048B4,FD5434,790E1F,87AC4D,E89DF4"

	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK <= zbx_db_execute(
			"insert into graph_theme"
			" values (5,'blue-classic-theme','FFFFFF','FFFFFF','CCD5D9','ACBBC2','ACBBC2','1F2C33',"
				"'E33734','429E47','E33734','EBEBEB','" ZBX_COLORPALETTE_LIGHT "')"))
	{
		return SUCCEED;
	}
#undef ZBX_COLORPALETTE_LIGHT

	return FAIL;
}

static int	DBpatch_7050063(void)
{
#define ZBX_COLORPALETTE_DARK	"199C0D,F63100,2774A4,F7941D,FC6EA3,6C59DC,C7A72D,BA2A5D,F230E0,5CCD18,BB2A02,"	\
				"AC41A5,89ABF8,7EC25C,3165D5,79A277,AA73DE,FD5434,F21C3E,87AC4D,E89DF4"

	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK <= zbx_db_execute(
			"insert into graph_theme"
			" values (6,'dark-classic-theme','2B2B2B','2B2B2B','454545','4F4F4F','4F4F4F','F2F2F2',"
				"'E45959','59DB8F','E45959','333333','" ZBX_COLORPALETTE_DARK "')"))
	{
		return SUCCEED;
	}
#undef ZBX_COLORPALETTE_DARK

	return FAIL;
}

static int	DBpatch_7050064(void)
{
	const zbx_db_field_t	field = {"auth_scheme", "0", NULL, NULL, 32, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("token", &field);
}

static int	DBpatch_7050065(void)
{
	return DBdrop_foreign_key("token", 1);
}

static int	DBpatch_7050066(void)
{
	return DBdrop_index("token", "token_2");
}

static int	DBpatch_7050067(void)
{
	return DBcreate_index("token", "token_2", "userid,auth_scheme,name", 1);
}

static int	DBpatch_7050068(void)
{
	const zbx_db_field_t	field = {"userid", NULL, "users", "userid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("token", 1, &field);
}

static int	DBpatch_7050069(void)
{
	const zbx_db_table_t	table =
			{"dpop_jti_cache", "jti", 0,
				{
					{"jti", "", NULL, NULL, 36, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"expires_at", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_7050070(void)
{
	const zbx_db_table_t	table =
			{"device", "deviceid", 0,
				{
					{"deviceid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"userid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0},
					{"uuid", "", NULL, NULL, 36, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"name", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"status", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"push_token", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"activated_at", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_7050071(void)
{
	const zbx_db_field_t	field = {"userid", NULL, "users", "userid", 0, 0, 0, 0};

	return DBadd_foreign_key("device", 1, &field);
}

static int	DBpatch_7050072(void)
{
	return DBcreate_index("device", "device_1", "userid", 0);
}

static int	DBpatch_7050073(void)
{
	const zbx_db_table_t	table =
			{"token_device", "tokenid", 0,
				{
					{"tokenid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"deviceid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_7050074(void)
{
	const zbx_db_field_t	field = {"tokenid", NULL, "token", "tokenid", 0, 0, 0, 0};

	return DBadd_foreign_key("token_device", 1, &field);
}

static int	DBpatch_7050075(void)
{
	const zbx_db_field_t	field = {"deviceid", NULL, "device", "deviceid", 0, 0, 0, 0};

	return DBadd_foreign_key("token_device", 2, &field);
}

static int	DBpatch_7050076(void)
{
	return DBcreate_index("token_device", "token_device_1", "deviceid", 0);
}

static int	DBpatch_7050077(void)
{
	const zbx_db_table_t	table =
			{"device_key", "device_keyid", 0,
				{
					{"device_keyid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"deviceid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"scope", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"kid", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"key_", "", NULL, NULL, 512, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"active", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"created_at", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_7050078(void)
{
	const zbx_db_field_t	field = {"deviceid", NULL, "device", "deviceid", 0, ZBX_TYPE_ID, ZBX_NOTNULL,
			ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("device_key", 1, &field);
}

static int	DBpatch_7050079(void)
{
	return DBcreate_index("device_key", "device_key_1", "deviceid", 0);
}

static int	DBpatch_7050080(void)
{
	return DBcreate_index("device_key", "device_key_2", "kid", 0);
}

static int	DBpatch_7050081(void)
{
	const zbx_db_table_t	table =
			{"device_enrollment_token", "deviceid", 0,
				{
					{"deviceid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"token", "", NULL, NULL, 128, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"expires_at", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_7050082(void)
{
	const zbx_db_field_t	field = {"deviceid", NULL, "device", "deviceid", 0, ZBX_TYPE_ID, ZBX_NOTNULL,
			ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("device_enrollment_token", 1, &field);
}

static int	DBpatch_7050083(void)
{
	return DBcreate_index("device_enrollment_token", "device_enrollment_token_1", "token", 1);
}

static int	DBpatch_7050084(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > zbx_db_execute("insert into settings (name,type,value_str) values"
			" ('device_link_timeout',1,'60s')"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_7050085(void)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	zbx_db_insert_t	db_insert;
	int		ret;

	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	zbx_db_insert_prepare(&db_insert, "role_rule", "role_ruleid", "roleid", "type", "name", "value_int",
			(char *)NULL);

	result = zbx_db_select("select roleid from role");

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_uint64_t	roleid;
		int		access;

		ZBX_STR2UINT64(roleid, row[0]);
		access = (3 == roleid) ? 1 : 0; /* roleid=3 -> default role with type=USER_TYPE_SUPER_ADMIN */

		zbx_db_insert_add_values(&db_insert, __UINT64_C(0), roleid, 0, "devices.access", access);
		zbx_db_insert_add_values(&db_insert, __UINT64_C(0), roleid, 0, "devices.actions.default_access",
				access);
	}
	zbx_db_free_result(result);

	zbx_db_insert_autoincrement(&db_insert, "role_ruleid");
	ret = zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);

	return ret;
}

static int	DBpatch_7050086(void)
{
	zbx_db_insert_t	db_insert;
	zbx_uint64_t	mediatypeid;
	int		ret;

	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	mediatypeid = zbx_db_get_maxid("media_type");

	if (ZBX_DB_OK > zbx_db_execute("insert into media_type (mediatypeid,type,name,script,description) values "
			"(" ZBX_FS_UI64 ",5,'Push notification','','')", mediatypeid))
	{
		return FAIL;
	}

	zbx_db_insert_prepare(&db_insert, "media_type_message", "mediatype_messageid", "mediatypeid", "eventsource",
			"recovery", "subject", "message", (char *)NULL);

	zbx_db_insert_add_values(&db_insert, __UINT64_C(0), mediatypeid, 0, 0, "{HOST.NAME} - {EVENT.NAME}",
			"Started on {{EVENT.TIMESTAMP}.fmttime(\"%x %X\")}\nData: {EVENT.OPDATA}");
	zbx_db_insert_add_values(&db_insert, __UINT64_C(0), mediatypeid, 0, 1,
			"[RESOLVED] {HOST.NAME} - {EVENT.NAME}",
			"Resolved on {{EVENT.RECOVERY.TIMESTAMP}.fmttime(\"%x %X\")}\nDuration: {EVENT.DURATION}");
	zbx_db_insert_add_values(&db_insert, __UINT64_C(0), mediatypeid, 0, 2,
			"[UPDATED] {HOST.NAME} - {EVENT.NAME}",
			"{USER.FULLNAME} {EVENT.UPDATE.ACTION} problem on "
			"{{EVENT.UPDATE.TIMESTAMP}.fmttime(\"%x %X\")}\n{EVENT.UPDATE.MESSAGE}");

	zbx_db_insert_autoincrement(&db_insert, "mediatype_messageid");
	ret = zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);

	return ret;
}

static int	DBpatch_7050087(void)
{
	return DBcreate_index("device", "device_2", "uuid", 1);
}

static int	DBpatch_7050088(void)
{
	const zbx_db_field_t	field = {"description", "", NULL, NULL, 0, ZBX_TYPE_TEXT, ZBX_NOTNULL, 0};

	return DBadd_field("regexps", &field);
}

static int	DBpatch_7050089(void)
{
	const zbx_db_field_t	field = {"expression", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("expressions", &field, NULL);
}

static int	DBpatch_7050090(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	/* 1  - REGEX_TYPE_CONTAINS_ANY_SUBSTRING   */
	if (ZBX_DB_OK > zbx_db_execute("update expressions set exp_delimiter='' where expression_type<>1"
			" and exp_delimiter<>''"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_7050091(void)
{
	int		i;
	const char	*values[] = {
		"web.hosts.host_discovery.filter.active", "web.hosts.lldrules.filter.active",
		"web.hosts.host_discovery.filter.groupids", "web.hosts.lldrules.filter.groupids",
		"web.hosts.host_discovery.filter.hostids", "web.hosts.lldrules.filter.hostids",
		"web.hosts.host_discovery.filter.name", "web.hosts.lldrules.filter.name",
		"web.hosts.host_discovery.filter.key", "web.hosts.lldrules.filter.key",
		"web.hosts.host_discovery.filter.type", "web.hosts.lldrules.filter.type",
		"web.hosts.host_discovery.filter.delay", "web.hosts.lldrules.filter.delay",
		"web.hosts.host_discovery.filter.lifetime_type", "web.hosts.lldrules.filter.lifetime_type",
		"web.hosts.host_discovery.filter.lifetime", "web.hosts.lldrules.filter.lifetime",
		"web.hosts.host_discovery.filter.enabled_lifetime_type",
		"web.hosts.lldrules.filter.enabled_lifetime_type",
		"web.hosts.host_discovery.filter.enabled_lifetime", "web.hosts.lldrules.filter.enabled_lifetime",
		"web.hosts.host_discovery.filter.snmp_oid", "web.hosts.lldrules.filter.snmp_oid",
		"web.hosts.host_discovery.filter.state", "web.hosts.lldrules.filter.state",
		"web.hosts.host_discovery.filter.status", "web.hosts.lldrules.filter.status",
		"web.hosts.host_discovery.php.sort", "web.hosts.lldrules.sort",
		"web.hosts.host_discovery.php.sortorder", "web.hosts.lldrules.sortorder",

		"web.templates.host_discovery.filter.active", "web.templates.lldrules.filter.active",
		"web.templates.host_discovery.filter.groupids", "web.templates.lldrules.filter.groupids",
		"web.templates.host_discovery.filter.hostids", "web.templates.lldrules.filter.hostids",
		"web.templates.host_discovery.filter.name", "web.templates.lldrules.filter.name",
		"web.templates.host_discovery.filter.key", "web.templates.lldrules.filter.key",
		"web.templates.host_discovery.filter.type", "web.templates.lldrules.filter.type",
		"web.templates.host_discovery.filter.delay", "web.templates.lldrules.filter.delay",
		"web.templates.host_discovery.filter.lifetime_type", "web.templates.lldrules.filter.lifetime_type",
		"web.templates.host_discovery.filter.lifetime", "web.templates.lldrules.filter.lifetime",
		"web.templates.host_discovery.filter.enabled_lifetime_type",
		"web.templates.lldrules.filter.enabled_lifetime_type",
		"web.templates.host_discovery.filter.enabled_lifetime",
		"web.templates.lldrules.filter.enabled_lifetime",
		"web.templates.host_discovery.filter.snmp_oid", "web.templates.lldrules.filter.snmp_oid",
		"web.templates.host_discovery.filter.state", "web.templates.lldrules.filter.state",
		"web.templates.host_discovery.filter.status", "web.templates.lldrules.filter.status",
		"web.templates.host_discovery.php.sort", "web.templates.lldrules.sort",
		"web.templates.host_discovery.php.sortorder", "web.templates.lldrules.sortorder",

		"web.hosts.discovery_prototypes.filter.active", "web.hosts.lldrules.prototypes.filter.active",
		"web.hosts.host_discovery_prototypes.php.sort", "web.hosts.lldrules.prototypes.sort",
		"web.hosts.host_discovery_prototypes.php.sortorder", "web.hosts.lldrules.prototypes.sortorder",

		"web.templates.discovery_prototypes.filter.active", "web.templates.lldrules.prototypes.filter.active",
		"web.templates.host_discovery_prototypes.php.sort", "web.templates.lldrules.prototypes.sort",
		"web.templates.host_discovery_prototypes.php.sortorder", "web.templates.lldrules.prototypes.sortorder"
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
DBPATCH_ADD(7050051, 0, 1)
DBPATCH_ADD(7050052, 0, 1)
DBPATCH_ADD(7050053, 0, 1)
DBPATCH_ADD(7050054, 0, 1)
DBPATCH_ADD(7050055, 0, 1)
DBPATCH_ADD(7050056, 0, 1)
DBPATCH_ADD(7050057, 0, 1)
DBPATCH_ADD(7050058, 0, 1)
DBPATCH_ADD(7050059, 0, 1)
DBPATCH_ADD(7050060, 0, 1)
DBPATCH_ADD(7050061, 0, 1)
DBPATCH_ADD(7050062, 0, 1)
DBPATCH_ADD(7050063, 0, 1)
DBPATCH_ADD(7050064, 0, 1)
DBPATCH_ADD(7050065, 0, 1)
DBPATCH_ADD(7050066, 0, 1)
DBPATCH_ADD(7050067, 0, 1)
DBPATCH_ADD(7050068, 0, 1)
DBPATCH_ADD(7050069, 0, 1)
DBPATCH_ADD(7050070, 0, 1)
DBPATCH_ADD(7050071, 0, 1)
DBPATCH_ADD(7050072, 0, 1)
DBPATCH_ADD(7050073, 0, 1)
DBPATCH_ADD(7050074, 0, 1)
DBPATCH_ADD(7050075, 0, 1)
DBPATCH_ADD(7050076, 0, 1)
DBPATCH_ADD(7050077, 0, 1)
DBPATCH_ADD(7050078, 0, 1)
DBPATCH_ADD(7050079, 0, 1)
DBPATCH_ADD(7050080, 0, 1)
DBPATCH_ADD(7050081, 0, 1)
DBPATCH_ADD(7050082, 0, 1)
DBPATCH_ADD(7050083, 0, 1)
DBPATCH_ADD(7050084, 0, 1)
DBPATCH_ADD(7050085, 0, 1)
DBPATCH_ADD(7050086, 0, 1)
DBPATCH_ADD(7050087, 0, 1)
DBPATCH_ADD(7050088, 0, 1)
DBPATCH_ADD(7050089, 0, 1)
DBPATCH_ADD(7050090, 0, 1)
DBPATCH_ADD(7050091, 0, 1)

DBPATCH_END()
