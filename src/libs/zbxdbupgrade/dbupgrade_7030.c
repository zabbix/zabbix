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
	/* 2 - ZBX_FLAG_DISCOVERY_PROTOTYPE */
	if (ZBX_DB_OK > zbx_db_execute("delete from item_rtdata"
			" where exists ("
				"select null from items i where item_rtdata.itemid=i.itemid and i.flags=2"
			")"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_7030034(void)
{
	const zbx_db_table_t	table =
			{"media_type_oauth", "mediatypeid", 0,
				{
					{"mediatypeid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"redirection_url", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"client_id", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"client_secret", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"authorization_url", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"tokens_status", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"access_token", "", NULL, NULL, 0, ZBX_TYPE_TEXT, ZBX_NOTNULL, 0},
					{"access_token_updated", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"access_expires_in", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"refresh_token", "", NULL, NULL, 0, ZBX_TYPE_TEXT, ZBX_NOTNULL, 0},
					{"token_url", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_7030035(void)
{
	const zbx_db_field_t	field = {"mediatypeid", NULL, "media_type", "mediatypeid", 0, ZBX_TYPE_ID, 0,
						ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("media_type_oauth", 1, &field);
}

static int	DBpatch_7030036(void)
{
	const zbx_db_table_t	table =
			{"lld_macro_export", "lld_macro_exportid", 0,
				{
					{"lld_macro_exportid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0},
					{"itemid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"lld_macro", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"value", "", NULL, NULL, 0, ZBX_TYPE_TEXT, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_7030037(void)
{
	return DBcreate_index("lld_macro_export", "lld_macro_export_1", "itemid", 0);
}

static int	DBpatch_7030038(void)
{
	const zbx_db_field_t	field = {"itemid", NULL, "items", "itemid", 0, ZBX_TYPE_ID, ZBX_NOTNULL,
			ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("lld_macro_export", 1, &field);
}

static int	DBpatch_7030039(void)
{
	const zbx_db_field_t	field = {"lldruleid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_field("item_discovery", &field);
}

static int	DBpatch_7030040(void)
{
	return DBcreate_index("item_discovery", "item_discovery_3", "lldruleid", 0);
}

static int	DBpatch_7030041(void)
{
	const zbx_db_field_t	field = {"lldruleid", NULL, "items", "itemid", 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_foreign_key("item_discovery", 3, &field);
}

static int	DBpatch_7030042(void)
{
	const zbx_db_field_t	field = {"parent_itemid", NULL, "items", "itemid", 0, ZBX_TYPE_ID, 0, 0};

	return DBdrop_not_null("item_discovery", &field);
}

static int	DBpatch_7030043(void)
{
	if (ZBX_DB_OK > zbx_db_execute("update item_discovery id"
					" set lldruleid=parent_itemid,parent_itemid=NULL"
					" where exists ("
						"select null from items i"
						" where i.itemid=id.itemid"
							" and i.flags&%d<>0"
					");", ZBX_FLAG_DISCOVERY_PROTOTYPE))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_7030044(void)
{
	const zbx_db_field_t	field = {"lldruleid", NULL, "items", "itemid", 0, ZBX_TYPE_ID, 0, 0};

	return DBrename_field("host_discovery", "parent_itemid", &field);
}


static int	DBpatch_7030045(void)
{
	const zbx_db_field_t	field = {"wizard_ready", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("hosts", &field);
}

static int	DBpatch_7030046(void)
{
	const zbx_db_field_t	field = {"readme", "", NULL, NULL, 0, ZBX_TYPE_TEXT, ZBX_NOTNULL, 0};

	return DBadd_field("hosts", &field);
}

static int	DBpatch_7030047(void)
{
	const zbx_db_table_t	table =
			{"hostmacro_config", "hostmacroid", 0,
				{
					{"hostmacroid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"type", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"priority", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"section_name", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"label", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"description", "", NULL, NULL, 0, ZBX_TYPE_TEXT, ZBX_NOTNULL, 0},
					{"required", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"regex", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"options", "", NULL, NULL, 0, ZBX_TYPE_TEXT, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_7030048(void)
{
	const zbx_db_field_t	field = {"hostmacroid", NULL, "hostmacro", "hostmacroid", 0, 0, 0,
			ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("hostmacro_config", 1, &field);
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
DBPATCH_ADD(7030048, 0, 1)

DBPATCH_END()
