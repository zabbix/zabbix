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

#include "zbxcacheconfig.h"
#include "zbxdb.h"
#include "zbxdbschema.h"
#include "zbxalgo.h"
#include "zbxnum.h"
#include "dbupgrade_common.h"
#include "zbxtasks.h"

/*
 * 7.4 development database patches
 */

#ifndef HAVE_SQLITE3

static int	DBpatch_7030000(void)
{
	const zbx_db_field_t	field = {"proxy_secrets_provider", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_7030001(void)
{
	const zbx_db_table_t	table =
			{"settings", "name", 0,
				{
					{"name", NULL, NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"type", NULL, NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"value_str", "", NULL, NULL, 0, ZBX_TYPE_TEXT, ZBX_NOTNULL, 0},
					{"value_int", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"value_usrgrpid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0},
					{"value_hostgroupid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0},
					{"value_userdirectoryid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0},
					{"value_mfaid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_7030002(void)
{
	if (FAIL == zbx_db_index_exists("settings", "settings_2"))
		return DBcreate_index("settings", "settings_2", "value_usrgrpid", 0);

	return SUCCEED;
}

static int	DBpatch_7030003(void)
{
	if (FAIL == zbx_db_index_exists("settings", "settings_3"))
		return DBcreate_index("settings", "settings_3", "value_hostgroupid", 0);

	return SUCCEED;
}

static int	DBpatch_7030004(void)
{
	if (FAIL == zbx_db_index_exists("settings", "settings_4"))
		return DBcreate_index("settings", "settings_4", "value_userdirectoryid", 0);

	return SUCCEED;
}

static int	DBpatch_7030005(void)
{
	if (FAIL == zbx_db_index_exists("settings", "settings_5"))
		return DBcreate_index("settings", "settings_5", "value_mfaid", 0);

	return SUCCEED;
}

static int	DBpatch_7030006(void)
{
	const zbx_db_field_t	field = {"value_usrgrpid", NULL, "usrgrp", "usrgrpid", 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_foreign_key("settings", 2, &field);
}

static int	DBpatch_7030007(void)
{
	const zbx_db_field_t	field = {"value_hostgroupid", NULL, "hstgrp", "groupid", 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_foreign_key("settings", 3, &field);
}

static int	DBpatch_7030008(void)
{
	const zbx_db_field_t	field = {"value_userdirectoryid", NULL, "userdirectory", "userdirectoryid", 0,
			ZBX_TYPE_ID, 0, 0};

	return DBadd_foreign_key("settings", 4, &field);
}

static int	DBpatch_7030009(void)
{
	const zbx_db_field_t	field = {"value_mfaid", NULL, "mfa", "mfaid", 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_foreign_key("settings", 5, &field);
}

const char	*target_column[ZBX_SETTING_TYPE_MAX - 1] = {
	"value_str", "value_int", "value_usrgrpid", "value_hostgroupid",
	"value_userdirectoryid", "value_mfaid"
};

static int	DBpatch_7030010(void)
{
	const zbx_setting_entry_t	*table = zbx_settings_desc_table_get();

	for (size_t i = 0; i < zbx_settings_descr_table_size(); i++)
	{
		const zbx_setting_entry_t	*e = &table[i];
		const char			*target_field = target_column[e->type - 1];

		if (ZBX_SETTING_TYPE_STR != e->type)
		{
			if (ZBX_DB_OK > zbx_db_execute("insert into settings (name, type, %s, value_str)"
					" values ('%s', %d, coalesce((select %s from config), %s), '')",
					target_field, e->name, e->type, e->name, e->default_value))
			{
				return FAIL;
			}
		}
		else if (ZBX_DB_OK > zbx_db_execute("insert into settings (name, type, %s)"
				" values ('%s', %d, coalesce((select %s from config), '%s'))",
				target_field, e->name, ZBX_SETTING_TYPE_STR, e->name, e->default_value))
		{
			return FAIL;
		}
	}

	return SUCCEED;
}

static int	DBpatch_7030011(void)
{
	return DBdrop_table("config");
}

static int	DBpatch_7030012(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	/* 1 - ZBX_FLAG_DISCOVERY */
	/* 2 - LIFETIME_TYPE_IMMEDIATELY */
	if (ZBX_DB_OK > zbx_db_execute(
			"update items"
				" set enabled_lifetime_type=2"
				" where flags=1"
					" and lifetime_type=2"
					" and enabled_lifetime_type<>2"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_7030013(void)
{
	int			ret = SUCCEED;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_vector_uint64_t	ids, hgsetids;
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	zbx_db_insert_t		db_insert;

	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	zbx_vector_uint64_create(&ids);
	zbx_vector_uint64_create(&hgsetids);

	/* 3 - HOST_STATUS_TEMPLATE */
	zbx_db_select_uint64("select hostid from hosts"
			" where status=3"
				" and hostid not in (select hostid from host_hgset)", &ids);

	if (0 == ids.values_num)
		goto out;

	ret = permission_hgsets_add(&ids, &hgsetids);

	if (FAIL == ret || 0 == hgsetids.values_num)
		goto out;

	zbx_vector_uint64_sort(&hgsetids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_db_insert_prepare(&db_insert, "permission", "ugsetid", "hgsetid", "permission", (char*)NULL);
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "h.hgsetid", hgsetids.values, hgsetids.values_num);

	result = zbx_db_select("select u.ugsetid,h.hgsetid,max(r.permission)"
			" from hgset h"
			" join hgset_group hg"
				" on h.hgsetid=hg.hgsetid"
			" join rights r on hg.groupid=r.id"
			" join ugset_group ug"
				" on r.groupid=ug.usrgrpid"
			" join ugset u"
				" on ug.ugsetid=u.ugsetid"
			" where%s"
			" group by u.ugsetid,h.hgsetid"
			" having min(r.permission)>0"
			" order by u.ugsetid,h.hgsetid", sql);
	zbx_free(sql);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_uint64_t	hgsetid, ugsetid;
		int		permission;

		ZBX_STR2UINT64(ugsetid, row[0]);
		ZBX_STR2UINT64(hgsetid, row[1]);
		permission = atoi(row[2]);

		zbx_db_insert_add_values(&db_insert, ugsetid, hgsetid, permission);
	}
	zbx_db_free_result(result);

	ret = zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);
out:
	zbx_vector_uint64_destroy(&hgsetids);
	zbx_vector_uint64_destroy(&ids);

	return ret;
}

static int	DBpatch_7030014(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	/* 2 - SYSMAP_ELEMENT_TYPE_TRIGGER */
	if (ZBX_DB_OK > zbx_db_execute("delete from sysmaps_elements"
			" where elementtype=2"
				" and selementid not in ("
					"select distinct selementid from sysmap_element_trigger"
				")"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_7030015(void)
{
	const zbx_db_table_t	table =
			{"sysmap_link_threshold", "linkthresholdid", 0,
				{
					{"linkthresholdid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"linkid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"drawtype", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"color", "000000", NULL, NULL, 6, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"type", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"threshold", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"pattern", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"sortorder", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_7030016(void)
{
	const zbx_db_field_t	field = {"linkid", NULL, "sysmaps_links", "linkid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("sysmap_link_threshold", 1, &field);
}

static int	DBpatch_7030017(void)
{
	if (FAIL == zbx_db_index_exists("sysmap_link_threshold", "sysmap_link_threshold_1"))
		return DBcreate_index("sysmap_link_threshold", "sysmap_link_threshold_1", "linkid", 0);

	return SUCCEED;
}

static int	DBpatch_7030018(void)
{
	const zbx_db_field_t	field = {"background_scale", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("sysmaps", &field);
}

static int	DBpatch_7030019(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > zbx_db_execute("update sysmaps set background_scale=0"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_7030020(void)
{
	const zbx_db_field_t	field = {"show_element_label", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("sysmaps", &field);
}

static int	DBpatch_7030021(void)
{
	const zbx_db_field_t	field = {"show_link_label", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("sysmaps", &field);
}

static int	DBpatch_7030022(void)
{
	const zbx_db_field_t	field = {"show_label", "-1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("sysmaps_elements", &field);
}

static int	DBpatch_7030023(void)
{
	const zbx_db_field_t	field = {"show_label", "-1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("sysmaps_links", &field);
}

static int	DBpatch_7030024(void)
{
	const zbx_db_field_t	field = {"indicator_type", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("sysmaps_links", &field);
}

static int	DBpatch_7030025(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > zbx_db_execute("update sysmaps_links sl set indicator_type = 1 "
			"where exists (select null from sysmaps_link_triggers slt where slt.linkid = sl.linkid)"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_7030026(void)
{
	const zbx_db_field_t	field = {"itemid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_field("sysmaps_links", &field);
}

static int	DBpatch_7030027(void)
{
	const zbx_db_field_t	field = {"itemid", NULL, "items", "itemid", 0, 0, 0, 0};

	return DBadd_foreign_key("sysmaps_links", 4, &field);
}

static int	DBpatch_7030028(void)
{
	if (FAIL == zbx_db_index_exists("sysmaps_links", "sysmaps_links_4"))
		return DBcreate_index("sysmaps_links", "sysmaps_links_4", "itemid", 0);

	return SUCCEED;
}

static int	DBpatch_7030029(void)
{
	int		i;
	const char	*values[] = {
			"web.hosts.graphs.php.sort", "web.hosts.graph.list.sort",
			"web.hosts.graphs.php.sortorder", "web.hosts.graph.list.sortorder",
			"web.hosts.graphs.filter_hostids", "web.hosts.graph.list.filter_hostids",
			"web.hosts.graphs.filter_groupids", "web.hosts.graph.list.filter_groupids",
			"web.hosts.graphs.filter.active", "web.hosts.graph.list.filter.active",
			"web.templates.graphs.php.sort", "web.templates.graph.list.sort",
			"web.templates.graphs.php.sortorder", "web.templates.graph.list.sortorder",
			"web.templates.graphs.filter_hostids", "web.templates.graph.list.filter_hostids",
			"web.templates.graphs.filter_groupids", "web.templates.graph.list.filter_groupids",
			"web.templates.graphs.filter.active", "web.templates.graph.list.filter.active",
		};

	for (i = 0; i < (int)ARRSIZE(values); i += 2)
	{
		if (ZBX_DB_OK > zbx_db_execute("update profiles set idx='%s' where idx='%s'", values[i + 1], values[i]))
			return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_7030030(void)
{
	zbx_db_insert_t	db_insert;
	int		ret;

	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	zbx_db_insert_prepare(&db_insert, "task", "taskid", "type", "status", "clock", (char *)NULL);
	zbx_db_insert_add_values(&db_insert, __UINT64_C(0), ZBX_TM_TASK_COPY_NESTED_HOST_PROTOTYPES, ZBX_TM_STATUS_NEW,
			time(NULL));
	zbx_db_insert_autoincrement(&db_insert, "taskid");
	ret = zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);

	return ret;
}

static int	DBpatch_7030031(void)
{
	const zbx_db_field_t	field = {"zindex", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("sysmaps_elements", &field);
}

static int	DBpatch_7030032(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > zbx_db_execute("insert into module (moduleid,id,relative_path,status,config) values"
			" (" ZBX_FS_UI64 ",'itemcard','widgets/itemcard',%d,'[]')", zbx_db_get_maxid("module"), 1))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_7030033(void)
{
	const zbx_db_table_t	table =
			{"host_template_cache", "hostid,link_hostid", 0,
				{
					{"hostid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"link_hostid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_7030034(void)
{
	const zbx_db_field_t	field = {"hostid", NULL, "hosts", "hostid", 0, 0, 0,
			ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("host_template_cache", 1, &field);
}

static int	DBpatch_7030035(void)
{
	const zbx_db_field_t	field = {"link_hostid", NULL, "hosts", "hostid", 0, 0, 0,
			ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("host_template_cache", 2, &field);
}

static int	DBpatch_7030036(void)
{
	return DBcreate_index("host_template_cache", "host_template_cache_1", "link_hostid", 0);
}

static int	DBpatch_7030037(void)
{
	if (ZBX_DB_OK > zbx_db_execute(
			" insert into host_template_cache"
				" (with recursive cte as"
					" ("
					" select h0.templateid, h0.hostid from hosts_templates h0"
						" union all"
					" select h1.templateid, c.hostid from cte c"
						" join hosts_templates h1 on c.templateid=h1.hostid"
					" )"
				" select hostid,templateid from cte)"))
	{
		return FAIL;
	}

	if (ZBX_DB_OK > zbx_db_execute("insert into host_template_cache (select hostid,hostid from hosts)"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_7030038(void)
{
	const zbx_db_table_t	table =
			{"item_template_cache", "itemid,link_hostid", 0,
				{
					{"itemid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"link_hostid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_7030039(void)
{
	const zbx_db_field_t	field = {"itemid", NULL, "items", "itemid", 0, 0, 0,
			ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("item_template_cache", 1, &field);
}

static int	DBpatch_7030040(void)
{
	const zbx_db_field_t	field = {"link_hostid", NULL, "hosts", "hostid", 0, 0, 0,
			ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("item_template_cache", 2, &field);
}

static int	DBpatch_7030041(void)
{
	return DBcreate_index("item_template_cache", "item_template_cache_1", "link_hostid", 0);
}

static int	DBpatch_7030042(void)
{
	/* 1 - ZBX_FLAG_DISCOVERY_RULE */
	if (ZBX_DB_OK > zbx_db_execute(
			" insert into item_template_cache"
				" (with recursive cte as"
					" ("
						"select i0.templateid, i0.itemid from items i0 where flags != 1"
						" union all"
						" select i1.templateid, c.itemid from cte c join items i1 on"
							" c.templateid=i1.itemid"
							" where i1.templateid is not null"
					" )"
			" select cte.itemid,h.hostid from cte,hosts h,items i where"
				" cte.templateid=i.itemid and i.hostid=h.hostid)"))
	{
		return FAIL;
	}

	/* 1 - ZBX_FLAG_DISCOVERY_RULE */
	if (ZBX_DB_OK > zbx_db_execute(
			"insert into item_template_cache"
				" (select i.itemid,h.hostid from items i,hosts h"
				" where i.hostid=h.hostid and i.flags != 1)"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_7030043(void)
{
	const zbx_db_table_t	table =
			{"httptest_template_cache", "httptestid,link_hostid", 0,
				{
					{"httptestid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"link_hostid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_7030044(void)
{
	const zbx_db_field_t	field = {"httptestid", NULL, "httptest", "httptestid", 0, 0, 0,
			ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("httptest_template_cache", 1, &field);
}

static int	DBpatch_7030045(void)
{
	const zbx_db_field_t	field = {"link_hostid", NULL, "hosts", "hostid", 0, 0, 0,
			ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("httptest_template_cache", 2, &field);
}

static int  DBpatch_7030046(void)
{
	return DBcreate_index("httptest_template_cache", "httptest_template_cache_1", "link_hostid", 0);
}

static int	DBpatch_7030047(void)
{
	if (ZBX_DB_OK > zbx_db_execute(
			" insert into httptest_template_cache"
				" (with recursive cte as"
					" ("
						" select ht0.templateid, ht0.httptestid from httptest ht0"
						" union all"
						" select ht1.templateid, c.httptestid from cte c join httptest ht1 on"
							" c.templateid=ht1.httptestid"
							" where ht1.templateid is not null"
					" )"
			" select cte.httptestid,ht.hostid from cte,hosts h,httptest ht where"
			"	cte.templateid=ht.httptestid and ht.hostid=h.hostid)"))
	{
		return FAIL;
	}

	if (ZBX_DB_OK > zbx_db_execute(
			"insert into httptest_template_cache"
				" (select ht.httptestid,h.hostid from httptest ht,hosts h"
					" where ht.hostid=h.hostid)"))
	{
		return FAIL;
	}

	return SUCCEED;
}

#endif

DBPATCH_START(7030)

/* version, duplicates flag, mandatory flag */

DBPATCH_ADD(7030000, 0, 1)
DBPATCH_ADD(7030001, 0, 1)
DBPATCH_ADD(7030002, 0, 1)
DBPATCH_ADD(7030003, 0, 1)
DBPATCH_ADD(7030004, 0, 1)
DBPATCH_ADD(7030005, 0, 1)
DBPATCH_ADD(7030006, 0, 1)
DBPATCH_ADD(7030007, 0, 1)
DBPATCH_ADD(7030008, 0, 1)
DBPATCH_ADD(7030009, 0, 1)
DBPATCH_ADD(7030010, 0, 1)
DBPATCH_ADD(7030011, 0, 1)
DBPATCH_ADD(7030012, 0, 1)
DBPATCH_ADD(7030013, 0, 1)
DBPATCH_ADD(7030014, 0, 1)
DBPATCH_ADD(7030015, 0, 1)
DBPATCH_ADD(7030016, 0, 1)
DBPATCH_ADD(7030017, 0, 1)
DBPATCH_ADD(7030018, 0, 1)
DBPATCH_ADD(7030019, 0, 1)
DBPATCH_ADD(7030020, 0, 1)
DBPATCH_ADD(7030021, 0, 1)
DBPATCH_ADD(7030022, 0, 1)
DBPATCH_ADD(7030023, 0, 1)
DBPATCH_ADD(7030024, 0, 1)
DBPATCH_ADD(7030025, 0, 1)
DBPATCH_ADD(7030026, 0, 1)
DBPATCH_ADD(7030027, 0, 1)
DBPATCH_ADD(7030028, 0, 1)
DBPATCH_ADD(7030029, 0, 1)
DBPATCH_ADD(7030030, 0, 1)
DBPATCH_ADD(7030031, 0, 1)
DBPATCH_ADD(7030032, 0, 1)
DBPATCH_ADD(7030033, 0, 1)
DBPATCH_ADD(7030034, 0, 1)
DBPATCH_ADD(7030035, 0, 1)
DBPATCH_ADD(7030036, 0, 1)
DBPATCH_ADD(7030037, 0, 1)
DBPATCH_ADD(7030038, 0, 1)
DBPATCH_ADD(7030039, 0, 1)
DBPATCH_ADD(7030040, 0, 1)
DBPATCH_ADD(7030041, 0, 1)
DBPATCH_ADD(7030042, 0, 1)
DBPATCH_ADD(7030043, 0, 1)
DBPATCH_ADD(7030044, 0, 1)
DBPATCH_ADD(7030045, 0, 1)
DBPATCH_ADD(7030046, 0, 1)
DBPATCH_ADD(7030047, 0, 1)

DBPATCH_END()
