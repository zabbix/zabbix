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
#include "zbxdbschema.h"
#include "zbxdb.h"
#include "zbxexpr.h"
#include "zbxnum.h"
#include "zbxstr.h"
#include "zbxjson.h"

/*
 * 6.4 development database patches
 */

#ifndef HAVE_SQLITE3

static int	DBpatch_6030000(void)
{
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	zbx_db_insert_t		db_insert;
	int			ret = SUCCEED;

	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	result = zbx_db_select("select roleid,type,name,value_int from role_rule where name in ("
			"'ui.configuration.actions',"
			"'ui.services.actions',"
			"'ui.administration.general')");

	zbx_db_insert_prepare(&db_insert, "role_rule", "role_ruleid", "roleid", "type", "name", "value_int",
			(char *)NULL);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_uint64_t	roleid;
		int		value_int, type;

		ZBX_STR2UINT64(roleid, row[0]);
		type = atoi(row[1]);
		value_int = atoi(row[3]);

		if (0 == strcmp(row[2], "ui.configuration.actions"))
		{
			zbx_db_insert_add_values(&db_insert, __UINT64_C(0), roleid, type,
					"ui.configuration.autoregistration_actions", value_int);

			zbx_db_insert_add_values(&db_insert, __UINT64_C(0), roleid, type,
					"ui.configuration.discovery_actions", value_int);

			zbx_db_insert_add_values(&db_insert, __UINT64_C(0), roleid, type,
					"ui.configuration.internal_actions", value_int);

			zbx_db_insert_add_values(&db_insert, __UINT64_C(0), roleid, type,
					"ui.configuration.trigger_actions", value_int);
		}
		else if (0 == strcmp(row[2], "ui.administration.general"))
		{
			zbx_db_insert_add_values(&db_insert, __UINT64_C(0), roleid, type,
					"ui.administration.housekeeping", value_int);

			zbx_db_insert_add_values(&db_insert, __UINT64_C(0), roleid, type,
					"ui.administration.macros", value_int);

			zbx_db_insert_add_values(&db_insert, __UINT64_C(0), roleid, type,
					"ui.administration.api_tokens", value_int);

			zbx_db_insert_add_values(&db_insert, __UINT64_C(0), roleid, type,
					"ui.administration.audit_log", value_int);
		}
		else
		{
			zbx_db_insert_add_values(&db_insert, __UINT64_C(0), roleid, type,
					"ui.configuration.service_actions", value_int);
		}
	}
	zbx_db_free_result(result);

	zbx_db_insert_autoincrement(&db_insert, "role_ruleid");

	if (SUCCEED == (ret = zbx_db_insert_execute(&db_insert)))
	{
		if (ZBX_DB_OK > zbx_db_execute("delete from role_rule where name in ("
			"'ui.configuration.actions',"
			"'ui.services.actions')"))
		{
			ret = FAIL;
		}
	}

	zbx_db_insert_clean(&db_insert);

	return ret;
}

static int	DBpatch_6030001(void)
{
	const zbx_db_field_t	field = {"name", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("group_discovery", &field, NULL);
}

static int	DBpatch_6030002(void)
{
	if (ZBX_DB_OK > zbx_db_execute(
			"update group_discovery gd"
			" set name=("
				"select gp.name"
				" from group_prototype gp"
				" where gd.parent_group_prototypeid=gp.group_prototypeid"
			")"
			" where " ZBX_DB_CHAR_LENGTH(gd.name) "=64"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_6030003(void)
{
	const zbx_db_field_t	field = {"url", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("scripts", &field);
}

static int	DBpatch_6030004(void)
{
	const zbx_db_field_t	field = {"new_window", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("scripts", &field);
}

static int	DBpatch_6030005(void)
{
	const zbx_db_field_t	old_field = {"host_metadata", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};
	const zbx_db_field_t	field = {"host_metadata", "", NULL, NULL, 0, ZBX_TYPE_TEXT, ZBX_NOTNULL, 0};

	return DBmodify_field_type("autoreg_host", &field, &old_field);
}

static int	DBpatch_6030006(void)
{
	const zbx_db_field_t	old_field = {"host_metadata", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};
	const zbx_db_field_t	field = {"host_metadata", "", NULL, NULL, 0, ZBX_TYPE_TEXT, ZBX_NOTNULL, 0};

	return DBmodify_field_type("proxy_autoreg_host", &field, &old_field);
}

static int	DBpatch_6030007(void)
{
	const zbx_db_field_t	field = {"server_status", "", NULL, NULL, 0, ZBX_TYPE_TEXT, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_6030008(void)
{
	const zbx_db_field_t	field = {"version", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("host_rtdata", &field);
}

static int	DBpatch_6030009(void)
{
	const zbx_db_field_t	field = {"compatibility", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("host_rtdata", &field);
}

static int	DBpatch_6030010(void)
{
	const zbx_db_field_t	field = {"url", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("users", &field, NULL);
}

static int	DBpatch_6030011(void)
{
	return DBcreate_changelog_insert_trigger("drules", "druleid");
}

static int	DBpatch_6030012(void)
{
	return DBcreate_changelog_update_trigger("drules", "druleid");
}

static int	DBpatch_6030013(void)
{
	return DBcreate_changelog_delete_trigger("drules", "druleid");
}

static int	DBpatch_6030014(void)
{
	return DBcreate_changelog_insert_trigger("dchecks", "dcheckid");
}

static int	DBpatch_6030015(void)
{
	return DBcreate_changelog_update_trigger("dchecks", "dcheckid");
}

static int	DBpatch_6030016(void)
{
	return DBcreate_changelog_delete_trigger("dchecks", "dcheckid");
}

static int	DBpatch_6030017(void)
{
	return DBcreate_changelog_insert_trigger("httptest", "httptestid");
}

static int	DBpatch_6030018(void)
{
	return DBcreate_changelog_update_trigger("httptest", "httptestid");
}

static int	DBpatch_6030019(void)
{
	return DBcreate_changelog_delete_trigger("httptest", "httptestid");
}

static int	DBpatch_6030020(void)
{
	return DBcreate_changelog_insert_trigger("httptest_field", "httptest_fieldid");
}

static int	DBpatch_6030021(void)
{
	return DBcreate_changelog_update_trigger("httptest_field", "httptest_fieldid");
}

static int	DBpatch_6030022(void)
{
	return DBcreate_changelog_delete_trigger("httptest_field", "httptest_fieldid");
}

static int	DBpatch_6030023(void)
{
	return DBcreate_changelog_insert_trigger("httptestitem", "httptestitemid");
}

static int	DBpatch_6030024(void)
{
	return DBcreate_changelog_update_trigger("httptestitem", "httptestitemid");
}

static int	DBpatch_6030025(void)
{
	return DBcreate_changelog_delete_trigger("httptestitem", "httptestitemid");
}

static int	DBpatch_6030026(void)
{
	return DBcreate_changelog_insert_trigger("httpstep", "httpstepid");
}

static int	DBpatch_6030027(void)
{
	return DBcreate_changelog_update_trigger("httpstep", "httpstepid");
}

static int	DBpatch_6030028(void)
{
	return DBcreate_changelog_delete_trigger("httpstep", "httpstepid");
}

static int	DBpatch_6030029(void)
{
	return DBcreate_changelog_insert_trigger("httpstep_field", "httpstep_fieldid");
}

static int	DBpatch_6030030(void)
{
	return DBcreate_changelog_update_trigger("httpstep_field", "httpstep_fieldid");
}

static int	DBpatch_6030031(void)
{
	return DBcreate_changelog_delete_trigger("httpstep_field", "httpstep_fieldid");
}

static int	DBpatch_6030032(void)
{
	return DBcreate_changelog_insert_trigger("httpstepitem", "httpstepitemid");
}

static int	DBpatch_6030033(void)
{
	return DBcreate_changelog_update_trigger("httpstepitem", "httpstepitemid");
}

static int	DBpatch_6030034(void)
{
	return DBcreate_changelog_delete_trigger("httpstepitem", "httpstepitemid");
}

static int	DBpatch_6030035(void)
{
	return DBdrop_field("drules", "nextcheck");
}

static int	DBpatch_6030036(void)
{
	return DBdrop_field("httptest", "nextcheck");
}

static int	DBpatch_6030037(void)
{
	const zbx_db_field_t	field = {"discovery_groupid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0};

	return DBdrop_not_null("config", &field);
}

static int	DBpatch_6030038(void)
{
	return DBdrop_foreign_key("dchecks", 1);
}

static int	DBpatch_6030039(void)
{
	const zbx_db_field_t	field = {"druleid", NULL, "drules", "druleid", 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_foreign_key("dchecks", 1, &field);
}

static int	DBpatch_6030040(void)
{
	return DBdrop_foreign_key("httptest", 2);
}

static int	DBpatch_6030041(void)
{
	const zbx_db_field_t	field = {"hostid", NULL, "hosts", "hostid", 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_foreign_key("httptest", 2, &field);
}

static int	DBpatch_6030042(void)
{
	return DBdrop_foreign_key("httptest", 3);
}

static int	DBpatch_6030043(void)
{
	const zbx_db_field_t	field = {"templateid", NULL, "httptest", "httptestid", 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_foreign_key("httptest", 3, &field);
}

static int	DBpatch_6030044(void)
{
	return DBdrop_foreign_key("httpstep", 1);
}

static int	DBpatch_6030045(void)
{
	const zbx_db_field_t	field = {"httptestid", NULL, "httptest", "httptestid", 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_foreign_key("httpstep", 1, &field);
}

static int	DBpatch_6030046(void)
{
	return DBdrop_foreign_key("httptestitem", 1);
}

static int	DBpatch_6030047(void)
{
	const zbx_db_field_t	field = {"httptestid", NULL, "httptest", "httptestid", 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_foreign_key("httptestitem", 1, &field);
}

static int	DBpatch_6030048(void)
{
	return DBdrop_foreign_key("httptestitem", 2);
}

static int	DBpatch_6030049(void)
{
	const zbx_db_field_t	field = {"itemid", NULL, "items", "itemid", 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_foreign_key("httptestitem", 2, &field);
}

static int	DBpatch_6030050(void)
{
	return DBdrop_foreign_key("httpstepitem", 1);
}

static int	DBpatch_6030051(void)
{
	const zbx_db_field_t	field = {"httpstepid", NULL, "httpstep", "httpstepid", 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_foreign_key("httpstepitem", 1, &field);
}

static int	DBpatch_6030052(void)
{
	return DBdrop_foreign_key("httpstepitem", 2);
}

static int	DBpatch_6030053(void)
{
	const zbx_db_field_t	field = {"itemid", NULL, "items", "itemid", 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_foreign_key("httpstepitem", 2, &field);
}

static int	DBpatch_6030054(void)
{
	return DBdrop_foreign_key("httptest_field", 1);
}

static int	DBpatch_6030055(void)
{
	const zbx_db_field_t	field = {"httptestid", NULL, "httptest", "httptestid", 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_foreign_key("httptest_field", 1, &field);
}

static int	DBpatch_6030056(void)
{
	return DBdrop_foreign_key("httpstep_field", 1);
}

static int	DBpatch_6030057(void)
{
	const zbx_db_field_t	field = {"httpstepid", NULL, "httpstep", "httpstepid", 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_foreign_key("httpstep_field", 1, &field);
}

static int	DBpatch_6030058(void)
{
	const zbx_db_field_t	field = {"provider", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("media_type", &field);
}

static int	DBpatch_6030059(void)
{
	const zbx_db_field_t	field = {"status", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBset_default("media_type", &field);
}

static int	DBpatch_6030060(void)
{
	const zbx_db_field_t	field = {"url_name", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("triggers", &field);
}

static int	DBpatch_6030061(void)
{
	const zbx_db_field_t	field = {"url", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("triggers", &field, NULL);
}

static int	DBpatch_6030062(void)
{
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	char			*sql;
	size_t			sql_alloc = 4096, sql_offset = 0;
	int			ret = SUCCEED;

	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	sql = zbx_malloc(NULL, sql_alloc);

	result = zbx_db_select("select moduleid,relative_path from module");

	while (NULL != (row = zbx_db_fetch(result)))
	{
		const char	*rel_path = row[1];
		char		*updated_path, *updated_path_esc;

		if (NULL == rel_path || '\0' == *rel_path)
			continue;

		updated_path = zbx_dsprintf(NULL, "modules/%s", rel_path);

		updated_path_esc = zbx_db_dyn_escape_string(updated_path);

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update module set relative_path='%s' "
				"where moduleid=%s;\n", updated_path_esc, row[0]);

		zbx_free(updated_path);
		zbx_free(updated_path_esc);

		ret = zbx_db_execute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
	}
	zbx_db_free_result(result);

	if (SUCCEED == ret)
	{
		if (ZBX_DB_OK > zbx_db_flush_overflowed_sql(sql, sql_offset))
			ret = FAIL;
	}

	zbx_free(sql);

	return ret;
}

static int	DBpatch_6030063(void)
{
	zbx_db_insert_t	db_insert;
	int		i, ret = FAIL;

	const char	*modules[] = {
			"actionlog", "clock", "dataover", "discovery", "favgraphs", "favmaps", "geomap", "graph",
			"graphprototype", "hostavail", "item", "map", "navtree", "plaintext", "problemhosts",
			"problems", "problemsbysv", "slareport", "svggraph", "systeminfo", "tophosts", "trigover",
			"url", "web"
		};

	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	zbx_db_insert_prepare(&db_insert, "module", "moduleid", "id", "relative_path", "status", "config",
			(char *)NULL);

	for (i = 0; i < (int)ARRSIZE(modules); i++)
	{
		char	*path;

		path = zbx_dsprintf(NULL, "widgets/%s", modules[i]);
		zbx_db_insert_add_values(&db_insert, __UINT64_C(0), modules[i], path, 1, "[]");
		zbx_free(path);
	}

	zbx_db_insert_autoincrement(&db_insert, "moduleid");
	ret = zbx_db_insert_execute(&db_insert);

	zbx_db_insert_clean(&db_insert);

	return ret;
}

static int	DBpatch_6030064(void)
{
	const zbx_db_field_t	field = {"name_upper", "", NULL, NULL, 128, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	if (SUCCEED == zbx_db_trigger_exists("hosts", "hosts_name_upper_update"))
	{
		zabbix_log(LOG_LEVEL_WARNING, "hosts_name_upper_update trigger for table \"hosts\" already exists,"
				" skipping patch of adding \"name_upper\" column to \"hosts\" table");
		return SUCCEED;
	}

	return DBadd_field("hosts", &field);
}

static int	DBpatch_6030065(void)
{
	if (SUCCEED == zbx_db_trigger_exists("hosts", "hosts_name_upper_update"))
	{
		zabbix_log(LOG_LEVEL_WARNING, "hosts_name_upper_update trigger for table \"hosts\" already exists,"
				" skipping patch of adding index to \"name_upper\" column");
		return SUCCEED;
	}

	return DBcreate_index("hosts", "hosts_6", "name_upper", 0);
}

static int	DBpatch_6030066(void)
{
	if (SUCCEED == zbx_db_trigger_exists("hosts", "hosts_name_upper_update"))
	{
		zabbix_log(LOG_LEVEL_WARNING, "hosts_name_upper_update trigger for table \"hosts\" already exists,"
				" skipping patch of updating \"name_upper\" column");

		return SUCCEED;
	}

	if (ZBX_DB_OK > zbx_db_execute("update hosts set name_upper=upper(name)"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_6030067(void)
{
	if (SUCCEED == zbx_db_trigger_exists("hosts", "hosts_name_upper_insert"))
	{
		zabbix_log(LOG_LEVEL_WARNING, "hosts_name_upper_insert trigger for table \"hosts\" already exists,"
				" skipping patch of adding it to \"hosts\" table");
		return SUCCEED;
	}

	return zbx_dbupgrade_attach_trigger_with_function_on_insert("hosts", "name", "name_upper", "upper", "hostid");
}

static int	DBpatch_6030068(void)
{
	if (SUCCEED == zbx_db_trigger_exists("hosts", "hosts_name_upper_update"))
	{
		zabbix_log(LOG_LEVEL_WARNING, "hosts_name_upper_update trigger for table \"hosts\" already exists,"
				" skipping patch of adding it to \"hosts\" table");
		return SUCCEED;
	}

	return zbx_dbupgrade_attach_trigger_with_function_on_update("hosts", "name", "name_upper", "upper", "hostid");
}

static int	DBpatch_6030069(void)
{
	const zbx_db_field_t	field = {"name_upper", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	if (SUCCEED == zbx_db_trigger_exists("items", "items_name_upper_update"))
	{
		zabbix_log(LOG_LEVEL_WARNING, "items_name_upper_update trigger for table \"items\" already exists,"
				" skipping patch of adding \"name_upper\" column to \"items\" table");
		return SUCCEED;
	}

	return DBadd_field("items", &field);
}

static int	DBpatch_6030070(void)
{
	if (SUCCEED == zbx_db_trigger_exists("items", "items_name_upper_update"))
	{
		zabbix_log(LOG_LEVEL_WARNING, "items_name_upper_update trigger for table \"items\" already exists,"
				" skipping patch of adding index to \"name_upper\" column");

		return SUCCEED;
	}

	return DBcreate_index("items", "items_9", "hostid,name_upper", 0);
}

static int	DBpatch_6030071(void)
{
	if (SUCCEED == zbx_db_trigger_exists("items", "items_name_upper_update"))
	{
		zabbix_log(LOG_LEVEL_WARNING, "items_name_upper_update trigger for table \"items\" already exists,"
				" skipping patch of updating \"name_upper\" column");
		return SUCCEED;
	}

	if (ZBX_DB_OK > zbx_db_execute("update items set name_upper=upper(name)"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_6030072(void)
{
	if (SUCCEED == zbx_db_trigger_exists("items", "items_name_upper_insert"))
	{
		zabbix_log(LOG_LEVEL_WARNING, "items_name_upper_insert trigger for table \"items\" already exists,"
				" skipping patch of adding it to \"items\" table");
		return SUCCEED;
	}

	return zbx_dbupgrade_attach_trigger_with_function_on_insert("items", "name", "name_upper", "upper", "itemid");
}

static int	DBpatch_6030073(void)
{
	if (SUCCEED == zbx_db_trigger_exists("items", "items_name_upper_update"))
	{
		zabbix_log(LOG_LEVEL_WARNING, "items_name_upper_update trigger for table \"items\" already exists,"
				" skipping patch of adding it to \"items\" table");
		return SUCCEED;
	}

	return zbx_dbupgrade_attach_trigger_with_function_on_update("items", "name", "name_upper", "upper", "itemid");
}

static int	DBpatch_6030075(void)
{
	const zbx_db_field_t	field = {"value_userid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_field("widget_field", &field);
}

static int	DBpatch_6030076(void)
{
	return DBcreate_index("widget_field", "widget_field_9", "value_userid", 0);
}

static int	DBpatch_6030077(void)
{
	const zbx_db_field_t	field = {"value_userid", NULL, "users", "userid", 0, ZBX_TYPE_ID, 0,
			ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("widget_field", 9, &field);
}

static int	DBpatch_6030078(void)
{
	const zbx_db_field_t	field = {"value_actionid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_field("widget_field", &field);
}

static int	DBpatch_6030079(void)
{
	return DBcreate_index("widget_field", "widget_field_10", "value_actionid", 0);
}

static int	DBpatch_6030080(void)
{
	const zbx_db_field_t	field = {"value_actionid", NULL, "actions", "actionid", 0, ZBX_TYPE_ID, 0,
			ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("widget_field", 10, &field);
}

static int	DBpatch_6030081(void)
{
	const zbx_db_field_t	field = {"value_mediatypeid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_field("widget_field", &field);
}

static int	DBpatch_6030082(void)
{
	return DBcreate_index("widget_field", "widget_field_11", "value_mediatypeid", 0);
}

static int	DBpatch_6030083(void)
{
	const zbx_db_field_t	field = {"value_mediatypeid", NULL, "media_type", "mediatypeid", 0, ZBX_TYPE_ID, 0,
			ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("widget_field", 11, &field);
}

/* patches for ZBXNEXT-276 */
/* create new tables */

static int	DBpatch_6030084(void)
{
	const zbx_db_table_t	table =
			{"scim_group", "scim_groupid", 0,
				{
					{"scim_groupid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"name", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_6030085(void)
{
	return DBcreate_index("scim_group", "scim_group_1", "name", 1);
}

static int	DBpatch_6030086(void)
{
	const zbx_db_table_t	table =
			{"user_scim_group", "user_scim_groupid", 0,
				{
					{"user_scim_groupid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"userid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"scim_groupid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_6030087(void)
{
	return DBcreate_index("user_scim_group", "user_scim_group_1", "userid", 0);
}

static int	DBpatch_6030088(void)
{
	return DBcreate_index("user_scim_group", "user_scim_group_2", "scim_groupid", 0);
}

static int	DBpatch_6030089(void)
{
	const zbx_db_field_t	field = {"userid", NULL, "users", "userid", 0, ZBX_TYPE_ID, ZBX_NOTNULL,
			ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("user_scim_group", 1, &field);
}

static int	DBpatch_6030090(void)
{
	const zbx_db_field_t	field = {"scim_groupid", NULL, "scim_group", "scim_groupid", 0, ZBX_TYPE_ID,
			ZBX_NOTNULL, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("user_scim_group", 2, &field);
}

static int	DBpatch_6030091(void)
{
	const zbx_db_table_t	table =
			{"userdirectory_saml", "userdirectoryid", 0,
				{
					{"userdirectoryid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"idp_entityid", "", NULL, NULL, 1024, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"sso_url", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"slo_url", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"username_attribute", "", NULL, NULL, 128, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"sp_entityid", "", NULL, NULL, 1024, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"nameid_format", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"sign_messages", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"sign_assertions", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"sign_authn_requests", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"sign_logout_requests", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"sign_logout_responses", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"encrypt_nameid", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"encrypt_assertions", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"group_name", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"user_username", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"user_lastname", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"scim_status", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_6030092(void)
{
	const zbx_db_field_t	field = {"userdirectoryid", NULL, "userdirectory", "userdirectoryid", 0, ZBX_TYPE_ID,
			ZBX_NOTNULL, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("userdirectory_saml", 1, &field);
}

static int	DBpatch_6030093(void)
{
	const zbx_db_table_t	table =
			{"userdirectory_ldap", "userdirectoryid", 0,
				{
					{"userdirectoryid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"host", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"port", "389", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"base_dn", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"search_attribute", "", NULL, NULL, 128, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"bind_dn", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"bind_password", "", NULL, NULL, 128, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"start_tls", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"search_filter", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"group_basedn", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"group_name", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"group_member", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"user_ref_attr", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"group_filter", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"group_membership", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"user_username", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"user_lastname", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_6030094(void)
{
	const zbx_db_field_t	field = {"userdirectoryid", NULL, "userdirectory", "userdirectoryid", 0, ZBX_TYPE_ID,
			ZBX_NOTNULL, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("userdirectory_ldap", 1, &field);
}

static int	DBpatch_6030095(void)
{
	const zbx_db_table_t	table =
			{"userdirectory_media", "userdirectory_mediaid", 0,
				{
					{"userdirectory_mediaid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"userdirectoryid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"mediatypeid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"name", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"attribute", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_6030096(void)
{
	return DBcreate_index("userdirectory_media", "userdirectory_media_1", "userdirectoryid", 0);
}

static int	DBpatch_6030097(void)
{
	return DBcreate_index("userdirectory_media", "userdirectory_media_2", "mediatypeid", 0);
}

static int	DBpatch_6030098(void)
{
	const zbx_db_field_t	field = {"userdirectoryid", NULL, "userdirectory", "userdirectoryid", 0, ZBX_TYPE_ID,
			ZBX_NOTNULL, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("userdirectory_media", 1, &field);
}

static int	DBpatch_6030099(void)
{
	const zbx_db_field_t	field = {"mediatypeid", NULL, "media_type", "mediatypeid", 0, ZBX_TYPE_ID, ZBX_NOTNULL,
			ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("userdirectory_media", 2, &field);
}

static int	DBpatch_6030100(void)
{
	const zbx_db_table_t	table =
			{"userdirectory_idpgroup", "userdirectory_idpgroupid", 0,
				{
					{"userdirectory_idpgroupid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"userdirectoryid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"roleid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"name", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_6030101(void)
{
	return DBcreate_index("userdirectory_idpgroup", "userdirectory_idpgroup_1", "userdirectoryid", 0);
}

static int	DBpatch_6030102(void)
{
	return DBcreate_index("userdirectory_idpgroup", "userdirectory_idpgroup_2", "roleid", 0);
}

static int	DBpatch_6030103(void)
{
	const zbx_db_field_t	field = {"userdirectoryid", NULL, "userdirectory", "userdirectoryid", 0, ZBX_TYPE_ID,
			ZBX_NOTNULL, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("userdirectory_idpgroup", 1, &field);
}

static int	DBpatch_6030104(void)
{
	const zbx_db_field_t	field = {"roleid", NULL, "role", "roleid", 0, ZBX_TYPE_ID, ZBX_NOTNULL,
			ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("userdirectory_idpgroup", 2, &field);
}

static int	DBpatch_6030105(void)
{
	const zbx_db_table_t	table =
			{"userdirectory_usrgrp", "userdirectory_usrgrpid", 0,
				{
					{"userdirectory_usrgrpid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"userdirectory_idpgroupid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"usrgrpid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_6030106(void)
{
	return DBcreate_index("userdirectory_usrgrp", "userdirectory_usrgrp_1", "userdirectory_idpgroupid,usrgrpid", 1);
}

static int	DBpatch_6030107(void)
{
	return DBcreate_index("userdirectory_usrgrp", "userdirectory_usrgrp_2", "usrgrpid", 0);
}

static int	DBpatch_6030108(void)
{
	return DBcreate_index("userdirectory_usrgrp", "userdirectory_usrgrp_3", "userdirectory_idpgroupid", 0);
}

static int	DBpatch_6030109(void)
{
	const zbx_db_field_t	field = {"userdirectory_idpgroupid", NULL, "userdirectory_idpgroup",
			"userdirectory_idpgroupid", 0, ZBX_TYPE_ID, ZBX_NOTNULL, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("userdirectory_usrgrp", 1, &field);
}

static int	DBpatch_6030110(void)
{
	const zbx_db_field_t	field = {"usrgrpid", NULL, "usrgrp", "usrgrpid", 0, ZBX_TYPE_ID, ZBX_NOTNULL,
			ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("userdirectory_usrgrp", 2, &field);
}

/* add new fields to existing tables */

static int	DBpatch_6030111(void)
{
	const zbx_db_field_t	field = {"jit_provision_interval", "1h", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_6030112(void)
{
	const zbx_db_field_t	field = {"saml_jit_status", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_6030113(void)
{
	const zbx_db_field_t	field = {"ldap_jit_status", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_6030114(void)
{
	const zbx_db_field_t	field = {"disabled_usrgrpid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_6030115(void)
{
	return DBcreate_index("config", "config_4", "disabled_usrgrpid", 0);
}

static int	DBpatch_6030116(void)
{
	const zbx_db_field_t	field = {"disabled_usrgrpid", NULL, "usrgrp", "usrgrpid", 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_foreign_key("config", 4, &field);
}

static int	DBpatch_6030117(void)
{
	const zbx_db_field_t	field = {"idp_type", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("userdirectory", &field);
}

static int	DBpatch_6030118(void)
{
	const zbx_db_field_t	field = {"provision_status", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("userdirectory", &field);
}

static int	DBpatch_6030119(void)
{
	return DBcreate_index("userdirectory", "userdirectory_1", "idp_type", 0);
}

static int	DBpatch_6030120(void)
{
	const zbx_db_field_t	field = {"userdirectoryid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_field("users", &field);
}

static int	DBpatch_6030121(void)
{
	const zbx_db_field_t	field = {"ts_provisioned", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("users", &field);
}

static int	DBpatch_6030122(void)
{
	return DBcreate_index("users", "users_2", "userdirectoryid", 0);
}

static int	DBpatch_6030123(void)
{
	const zbx_db_field_t	field = {"userdirectoryid", NULL, "userdirectory", "userdirectoryid", 0, ZBX_TYPE_ID,
			0, 0};

	return DBadd_foreign_key("users", 2, &field);
}

/* migrate data */

static int	migrate_ldap_data(void)
{
	zbx_db_result_t	result = zbx_db_select("select userdirectoryid,host,port,base_dn,bind_dn,bind_password,"
					"search_attribute,start_tls,search_filter"
					" from userdirectory");
	if (NULL == result)
		return FAIL;

	zbx_db_row_t	row;

	while (NULL != (row = zbx_db_fetch(result)))
	{
		char	*host = zbx_db_dyn_escape_string(row[1]);
		char	*base_dn = zbx_db_dyn_escape_string(row[3]);
		char	*bind_dn = zbx_db_dyn_escape_string(row[4]);
		char	*bind_password = zbx_db_dyn_escape_string(row[5]);
		char	*search_attribute = zbx_db_dyn_escape_string(row[6]);
		char	*search_filter = zbx_db_dyn_escape_string(row[8]);

		int	rc = zbx_db_execute("insert into userdirectory_ldap (userdirectoryid,host,port,"
					"base_dn,search_attribute,bind_dn,bind_password,start_tls,search_filter)"
					" values (%s,'%s',%s,'%s','%s','%s','%s',%s,'%s')", row[0], host, row[2],
					base_dn, search_attribute, bind_dn, bind_password, row[7], search_filter);

		zbx_free(search_filter);
		zbx_free(search_attribute);
		zbx_free(bind_password);
		zbx_free(bind_dn);
		zbx_free(base_dn);
		zbx_free(host);

		if (ZBX_DB_OK > rc)
		{
			zbx_db_free_result(result);
			return FAIL;
		}

#define IDP_TYPE_LDAP	1	/* user directory of type LDAP */
		if (ZBX_DB_OK > zbx_db_execute("update userdirectory set idp_type=%d where userdirectoryid=%s",
				IDP_TYPE_LDAP, row[0]))
		{
			zbx_db_free_result(result);
			return FAIL;
		}
#undef IDP_TYPE_LDAP
	}

	zbx_db_free_result(result);

	return SUCCEED;
}

static int	DBpatch_6030124(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	return migrate_ldap_data();
}

static int	migrate_saml_data(void)
{
	zbx_db_result_t	result = zbx_db_select("select saml_idp_entityid,saml_sso_url,saml_slo_url,saml_username_attribute,"
					"saml_sp_entityid,saml_nameid_format,saml_sign_messages,saml_sign_assertions,"
					"saml_sign_authn_requests,saml_sign_logout_requests,saml_sign_logout_responses,"
					"saml_encrypt_nameid,saml_encrypt_assertions"
					" from config");
	if (NULL == result)
		return FAIL;

	zbx_db_row_t	row = zbx_db_fetch(result);

	if (NULL == row)
	{
		zbx_db_free_result(result);
		return FAIL;
	}

	if ('\0' == *row[0] && '\0' == *row[1] && '\0' == *row[2] && '\0' == *row[3] && '\0' == *row[4] &&
			'\0' == *row[5] && 0 == atoi(row[6]) && 0 == atoi(row[7]) && 0 == atoi(row[8]) &&
			0 == atoi(row[9]) && 0 == atoi(row[10]) && 0 == atoi(row[11]) && 0 == atoi(row[12]))
	{
		zbx_db_free_result(result);
		return SUCCEED;
	}

	zbx_uint64_t	userdirectoryid = zbx_db_get_maxid("userdirectory");

#define IDP_TYPE_SAML	2	/* user directory of type SAML */
	int	rc = zbx_db_execute("insert into userdirectory (userdirectoryid,idp_type,description) values"
			" (" ZBX_FS_UI64 ",%d,'')", userdirectoryid, IDP_TYPE_SAML);
#undef IDP_TYPE_SAML
	if (ZBX_DB_OK > rc)
	{
		zbx_db_free_result(result);
		return FAIL;
	}

	char	*idp_entityid = zbx_db_dyn_escape_string(row[0]);
	char	*sso_url = zbx_db_dyn_escape_string(row[1]);
	char	*slo_url = zbx_db_dyn_escape_string(row[2]);
	char	*username_attribute = zbx_db_dyn_escape_string(row[3]);
	char	*sp_entityid = zbx_db_dyn_escape_string(row[4]);
	char	*nameid_format = zbx_db_dyn_escape_string(row[5]);

	int	rc2 = zbx_db_execute("insert into userdirectory_saml (userdirectoryid,idp_entityid,sso_url,slo_url,"
				"username_attribute,sp_entityid,nameid_format,sign_messages,sign_assertions,"
				"sign_authn_requests,sign_logout_requests,sign_logout_responses,encrypt_nameid,"
				"encrypt_assertions) values (" ZBX_FS_UI64 ",'%s','%s','%s','%s','%s','%s',%s,%s,%s,%s,"
				"%s,%s,%s)", userdirectoryid, idp_entityid, sso_url, slo_url, username_attribute,
				sp_entityid, nameid_format, row[6], row[7], row[8], row[9], row[10], row[11], row[12]);

	zbx_free(nameid_format);
	zbx_free(sp_entityid);
	zbx_free(username_attribute);
	zbx_free(slo_url);
	zbx_free(sso_url);
	zbx_free(idp_entityid);

	zbx_db_free_result(result);

	if (ZBX_DB_OK > rc2)
	return FAIL;

	return SUCCEED;
}

static int	DBpatch_6030125(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	return migrate_saml_data();
}

/* rename fields */

static int	DBpatch_6030126(void)
{
	const zbx_db_field_t	field = {"ldap_auth_enabled", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBrename_field("config", "ldap_configured", &field);
}

/* modify fields in tables */

static int	DBpatch_6030127(void)
{
	const zbx_db_field_t	field = {"roleid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0};

	return DBdrop_not_null("users", &field);
}

/* drop fields */

static int	DBpatch_6030128(void)
{
	return DBdrop_field("config", "saml_idp_entityid");
}

static int	DBpatch_6030129(void)
{
	return DBdrop_field("config", "saml_sso_url");
}

static int	DBpatch_6030130(void)
{
	return DBdrop_field("config", "saml_slo_url");
}

static int	DBpatch_6030131(void)
{
	return DBdrop_field("config", "saml_username_attribute");
}

static int	DBpatch_6030132(void)
{
	return DBdrop_field("config", "saml_sp_entityid");
}

static int	DBpatch_6030133(void)
{
	return DBdrop_field("config", "saml_nameid_format");
}

static int	DBpatch_6030134(void)
{
	return DBdrop_field("config", "saml_sign_messages");
}

static int	DBpatch_6030135(void)
{
	return DBdrop_field("config", "saml_sign_assertions");
}

static int	DBpatch_6030136(void)
{
	return DBdrop_field("config", "saml_sign_authn_requests");
}

static int	DBpatch_6030137(void)
{
	return DBdrop_field("config", "saml_sign_logout_requests");
}

static int	DBpatch_6030138(void)
{
	return DBdrop_field("config", "saml_sign_logout_responses");
}

static int	DBpatch_6030139(void)
{
	return DBdrop_field("config", "saml_encrypt_nameid");
}

static int	DBpatch_6030140(void)
{
	return DBdrop_field("config", "saml_encrypt_assertions");
}

static int	DBpatch_6030141(void)
{
	return DBdrop_field("userdirectory", "host");
}

static int	DBpatch_6030142(void)
{
	return DBdrop_field("userdirectory", "port");
}

static int	DBpatch_6030143(void)
{
	return DBdrop_field("userdirectory", "base_dn");
}

static int	DBpatch_6030144(void)
{
	return DBdrop_field("userdirectory", "bind_dn");
}

static int	DBpatch_6030145(void)
{
	return DBdrop_field("userdirectory", "bind_password");
}

static int	DBpatch_6030146(void)
{
	return DBdrop_field("userdirectory", "search_attribute");
}

static int	DBpatch_6030147(void)
{
	return DBdrop_field("userdirectory", "start_tls");
}

static int	DBpatch_6030148(void)
{
	return DBdrop_field("userdirectory", "search_filter");
}
/* end of ZBXNEXT-276 patches */

static int	DBpatch_6030149(void)
{
	const zbx_db_field_t	old_field = {"info", "", NULL, NULL, 0, ZBX_TYPE_TEXT, ZBX_NOTNULL, 0};
	const zbx_db_field_t	field = {"info", "", NULL, NULL, 0, ZBX_TYPE_LONGTEXT, ZBX_NOTNULL, 0};

	return DBmodify_field_type("task_result", &field, &old_field);
}

static int	DBpatch_6030150(void)
{
	const zbx_db_field_t	field = {"max_repetitions", "10", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("interface_snmp", &field);
}

static int	DBpatch_6030151(void)
{
	const zbx_db_table_t	table =
			{"event_symptom", "eventid", 0,
				{
					{"eventid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"cause_eventid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_6030152(void)
{
	const zbx_db_field_t	field = {"eventid", NULL, "events", "eventid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("event_symptom", 1, &field);
}

static int	DBpatch_6030153(void)
{
	const zbx_db_field_t	field = {"cause_eventid", NULL, "events", "eventid", 0, 0, 0, 0};

	return DBadd_foreign_key("event_symptom", 2, &field);
}

static int	DBpatch_6030154(void)
{
	const zbx_db_field_t	field = {"cause_eventid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_field("problem", &field);
}

static int	DBpatch_6030155(void)
{
	const zbx_db_field_t	field = {"cause_eventid", NULL, "events", "eventid", 0, 0, 0, 0};

	return DBadd_foreign_key("problem", 3, &field);
}

static int	DBpatch_6030156(void)
{
	const zbx_db_field_t	field = {"pause_symptoms", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("actions", &field);
}

static int	DBpatch_6030157(void)
{
	const zbx_db_field_t	field = {"taskid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_field("acknowledges", &field);
}

static int	DBpatch_6030158(void)
{
	return DBcreate_index("event_symptom", "event_symptom_1", "cause_eventid", 0);
}

static int	DBpatch_6030160(void)
{
	const zbx_db_field_t	field = {"secret", "", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("sessions", &field);
}

static int	DBpatch_6030161(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > zbx_db_execute("update sessions set secret=sessionid"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_6030162(void)
{
	const zbx_db_field_t	field = {"vendor_name", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("hosts", &field);
}

static int	DBpatch_6030163(void)
{
	const zbx_db_field_t	field = {"vendor_version", "", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("hosts", &field);
}

static int	DBpatch_6030164(void)
{
	const zbx_db_table_t	table =
			{"connector", "connectorid", 0,
				{
					{"connectorid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"name", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"protocol", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"data_type", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"url", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"max_records", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"max_senders", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"max_attempts", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"timeout", "5s", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"http_proxy", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"authtype", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"username", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"password", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"token", "", NULL, NULL, 128, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"verify_peer", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"verify_host", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"ssl_cert_file", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"ssl_key_file", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"ssl_key_password", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"description", "", NULL, NULL, 0, ZBX_TYPE_TEXT, ZBX_NOTNULL, 0},
					{"status", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"tags_evaltype", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_6030165(void)
{
	return DBcreate_index("connector", "connector_1", "name", 1);
}

static int	DBpatch_6030166(void)
{
	return DBcreate_changelog_insert_trigger("connector", "connectorid");
}

static int	DBpatch_6030167(void)
{
	return DBcreate_changelog_update_trigger("connector", "connectorid");
}

static int	DBpatch_6030168(void)
{
	return DBcreate_changelog_delete_trigger("connector", "connectorid");
}

static int	DBpatch_6030169(void)
{
	const zbx_db_table_t	table =
			{"connector_tag", "connector_tagid", 0,
				{
					{"connector_tagid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"connectorid", NULL, "connector", "connectorid", 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"tag", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"operator", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"value", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_6030170(void)
{
	return DBcreate_index("connector_tag", "connector_tag_1", "connectorid", 0);
}

static int	DBpatch_6030171(void)
{
	const zbx_db_field_t	field = {"connectorid", NULL, "connector", "connectorid", 0, 0, 0, 0};

	return DBadd_foreign_key("connector_tag", 1, &field);
}

static int	DBpatch_6030172(void)
{
	return DBcreate_changelog_insert_trigger("connector_tag", "connector_tagid");
}

static int	DBpatch_6030173(void)
{
	return DBcreate_changelog_update_trigger("connector_tag", "connector_tagid");
}

static int	DBpatch_6030174(void)
{
	return DBcreate_changelog_delete_trigger("connector_tag", "connector_tagid");
}

static int	DBpatch_6030187(void)
{
	const zbx_db_field_t	field = {"sortorder", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("media_type_param", &field);
}

static int	DBpatch_6030188(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	zbx_db_result_t	result = zbx_db_select("select mediatypeid,exec_params from media_type where type=1");
	zbx_db_row_t	row;
	zbx_db_insert_t	db_insert;

	zbx_db_insert_prepare(&db_insert, "media_type_param", "mediatype_paramid", "mediatypeid", "name", "value",
			"sortorder", (char *)NULL);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_uint64_t	mediatypeid;

		ZBX_STR2UINT64(mediatypeid, row[0]);

		char	*params = zbx_strdup(NULL, row[1]);
		char	*saveptr;
		char	*token = strtok_r(params, "\r\n", &saveptr);

		for (int i = 0; NULL != token; i++)
		{
			zbx_db_insert_add_values(&db_insert, __UINT64_C(0), mediatypeid, "", token, i);

			token = strtok_r(NULL, "\r\n", &saveptr);
		}

		zbx_free(params);
	}

	zbx_db_free_result(result);

	zbx_db_insert_autoincrement(&db_insert, "mediatype_paramid");

	int	ret = zbx_db_insert_execute(&db_insert);

	zbx_db_insert_clean(&db_insert);

	return ret;
}

static void	substitute_macro(const char *in, const char *macro, const char *macrovalue, char **out,
		size_t *out_alloc)
{
	zbx_token_t	token;
	int		pos = 0;
	size_t		out_offset = 0, macrovalue_len;

	macrovalue_len = strlen(macrovalue);
	zbx_strcpy_alloc(out, out_alloc, &out_offset, in);
	out_offset++;

	for (; SUCCEED == zbx_token_find(*out, pos, &token, ZBX_TOKEN_SIMPLE_MACRO); pos++)
	{
		pos = token.loc.r;

		if (0 == strncmp(*out + token.loc.l, macro, token.loc.r - token.loc.l + 1))
		{
			pos += zbx_replace_mem_dyn(out, out_alloc, &out_offset, token.loc.l,
					token.loc.r - token.loc.l + 1, macrovalue, macrovalue_len);
		}
	}
}

static void	get_mediatype_params(zbx_uint64_t mediatypeid, const char *sendto, const char *subject,
		const char *message, char **params)
{
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	struct zbx_json		json;
	char			*value = NULL;
	size_t			value_alloc = 0;

	result = zbx_db_select(
			"select value"
			" from media_type_param"
				" where mediatypeid=" ZBX_FS_UI64
			" order by sortorder",
			mediatypeid);

	zbx_json_initarray(&json, 1024);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		char	*param = NULL;

		param = zbx_strdup(param, row[0]);
		substitute_macro(param, "{ALERT.SENDTO}", sendto, &value, &value_alloc);

		param = zbx_strdup(param, value);
		substitute_macro(param, "{ALERT.SUBJECT}", subject, &value, &value_alloc);

		param = zbx_strdup(param, value);
		substitute_macro(param, "{ALERT.MESSAGE}", message, &value, &value_alloc);

		zbx_free(param);

		zbx_json_addstring(&json, NULL, value, ZBX_JSON_TYPE_STRING);
	}

	zbx_db_free_result(result);

	zbx_free(value);

	*params = zbx_strdup(NULL, json.buffer);

	zbx_json_free(&json);
}

static int	DBpatch_6030189(void)
{
	int	ret = FAIL;

	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	/* select alerts of Script Mediatype that aren't sent */
	zbx_db_result_t	result = zbx_db_select(
			"select a.alertid,m.mediatypeid,a.sendto,a.subject,a.message"
			" from alerts a,media_type m"
			" where a.mediatypeid=m.mediatypeid"
				" and a.status in (0,3)"
				" and m.type=1"
			" order by a.mediatypeid");

	zbx_db_row_t	row;

	/* set their parameters according to how we now store them */
	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_uint64_t	alertid, mediatypeid;
		char		*params, *params_esc;

		ZBX_STR2UINT64(alertid, row[0]);
		ZBX_STR2UINT64(mediatypeid, row[1]);

		get_mediatype_params(mediatypeid, row[2], row[3], row[4], &params);

		params_esc = zbx_db_dyn_escape_field("alerts", "parameters", params);

		zbx_free(params);

		int	rv = zbx_db_execute("update alerts set parameters='%s' where alertid=" ZBX_FS_UI64,
				params_esc, alertid);

		zbx_free(params_esc);

		if (ZBX_DB_OK > rv)
			goto out;
	}

	ret = SUCCEED;
out:
	zbx_db_free_result(result);

	return ret;
}

static int	DBpatch_6030190(void)
{
	return DBdrop_field("media_type", "exec_params");
}

static int	DBpatch_6030191(void)
{
	int		i;
	const char	*values[] = {
			"web.auditacts.filter.from", "web.actionlog.filter.from",
			"web.auditacts.filter.to", "web.actionlog.filter.to",
			"web.auditacts.filter.active", "web.actionlog.filter.active",
			"web.auditacts.filter.userids", "web.actionlog.filter.userids",
			"web.actionconf.php.sort", "web.action.list.sort",
			"web.actionconf.php.sortorder", "web.action.list.sortorder",
			"web.actionconf.filter_name", "web.action.list.filter_name",
			"web.actionconf.filter_status", "web.action.list.filter_status",
			"web.actionconf.filter.active", "web.action.list.filter.active",
			"web.maintenance.php.sortorder", "web.maintenance.list.sortorder",
			"web.maintenance.php.sort", "web.maintenance.list.sort"
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

static int	DBpatch_6030192(void)
{
	return DBdrop_index("scripts", "scripts_3");
}

static int	DBpatch_6030193(void)
{
	return DBcreate_index("scripts", "scripts_3", "name,menu_path", 1);
}

#endif

DBPATCH_START(6030)

/* version, duplicates flag, mandatory flag */

DBPATCH_ADD(6030000, 0, 1)
DBPATCH_ADD(6030001, 0, 1)
DBPATCH_ADD(6030002, 0, 1)
DBPATCH_ADD(6030003, 0, 1)
DBPATCH_ADD(6030004, 0, 1)
DBPATCH_ADD(6030005, 0, 1)
DBPATCH_ADD(6030006, 0, 1)
DBPATCH_ADD(6030007, 0, 1)
DBPATCH_ADD(6030008, 0, 1)
DBPATCH_ADD(6030009, 0, 1)
DBPATCH_ADD(6030010, 0, 1)
DBPATCH_ADD(6030011, 0, 1)
DBPATCH_ADD(6030012, 0, 1)
DBPATCH_ADD(6030013, 0, 1)
DBPATCH_ADD(6030014, 0, 1)
DBPATCH_ADD(6030015, 0, 1)
DBPATCH_ADD(6030016, 0, 1)
DBPATCH_ADD(6030017, 0, 1)
DBPATCH_ADD(6030018, 0, 1)
DBPATCH_ADD(6030019, 0, 1)
DBPATCH_ADD(6030020, 0, 1)
DBPATCH_ADD(6030021, 0, 1)
DBPATCH_ADD(6030022, 0, 1)
DBPATCH_ADD(6030023, 0, 1)
DBPATCH_ADD(6030024, 0, 1)
DBPATCH_ADD(6030025, 0, 1)
DBPATCH_ADD(6030026, 0, 1)
DBPATCH_ADD(6030027, 0, 1)
DBPATCH_ADD(6030028, 0, 1)
DBPATCH_ADD(6030029, 0, 1)
DBPATCH_ADD(6030030, 0, 1)
DBPATCH_ADD(6030031, 0, 1)
DBPATCH_ADD(6030032, 0, 1)
DBPATCH_ADD(6030033, 0, 1)
DBPATCH_ADD(6030034, 0, 1)
DBPATCH_ADD(6030035, 0, 1)
DBPATCH_ADD(6030036, 0, 1)
DBPATCH_ADD(6030037, 0, 1)
DBPATCH_ADD(6030038, 0, 1)
DBPATCH_ADD(6030039, 0, 1)
DBPATCH_ADD(6030040, 0, 1)
DBPATCH_ADD(6030041, 0, 1)
DBPATCH_ADD(6030042, 0, 1)
DBPATCH_ADD(6030043, 0, 1)
DBPATCH_ADD(6030044, 0, 1)
DBPATCH_ADD(6030045, 0, 1)
DBPATCH_ADD(6030046, 0, 1)
DBPATCH_ADD(6030047, 0, 1)
DBPATCH_ADD(6030048, 0, 1)
DBPATCH_ADD(6030049, 0, 1)
DBPATCH_ADD(6030050, 0, 1)
DBPATCH_ADD(6030051, 0, 1)
DBPATCH_ADD(6030052, 0, 1)
DBPATCH_ADD(6030053, 0, 1)
DBPATCH_ADD(6030054, 0, 1)
DBPATCH_ADD(6030055, 0, 1)
DBPATCH_ADD(6030056, 0, 1)
DBPATCH_ADD(6030057, 0, 1)
DBPATCH_ADD(6030058, 0, 1)
DBPATCH_ADD(6030059, 0, 1)
DBPATCH_ADD(6030060, 0, 1)
DBPATCH_ADD(6030061, 0, 1)
DBPATCH_ADD(6030062, 0, 1)
DBPATCH_ADD(6030063, 0, 1)
DBPATCH_ADD(6030064, 0, 1)
DBPATCH_ADD(6030065, 0, 1)
DBPATCH_ADD(6030066, 0, 1)
DBPATCH_ADD(6030067, 0, 1)
DBPATCH_ADD(6030068, 0, 1)
DBPATCH_ADD(6030069, 0, 1)
DBPATCH_ADD(6030070, 0, 1)
DBPATCH_ADD(6030071, 0, 1)
DBPATCH_ADD(6030072, 0, 1)
DBPATCH_ADD(6030073, 0, 1)
DBPATCH_ADD(6030075, 0, 1)
DBPATCH_ADD(6030076, 0, 1)
DBPATCH_ADD(6030077, 0, 1)
DBPATCH_ADD(6030078, 0, 1)
DBPATCH_ADD(6030079, 0, 1)
DBPATCH_ADD(6030080, 0, 1)
DBPATCH_ADD(6030081, 0, 1)
DBPATCH_ADD(6030082, 0, 1)
DBPATCH_ADD(6030083, 0, 1)
DBPATCH_ADD(6030084, 0, 1)
DBPATCH_ADD(6030085, 0, 1)
DBPATCH_ADD(6030086, 0, 1)
DBPATCH_ADD(6030087, 0, 1)
DBPATCH_ADD(6030088, 0, 1)
DBPATCH_ADD(6030089, 0, 1)
DBPATCH_ADD(6030090, 0, 1)
DBPATCH_ADD(6030091, 0, 1)
DBPATCH_ADD(6030092, 0, 1)
DBPATCH_ADD(6030093, 0, 1)
DBPATCH_ADD(6030094, 0, 1)
DBPATCH_ADD(6030095, 0, 1)
DBPATCH_ADD(6030096, 0, 1)
DBPATCH_ADD(6030097, 0, 1)
DBPATCH_ADD(6030098, 0, 1)
DBPATCH_ADD(6030099, 0, 1)
DBPATCH_ADD(6030100, 0, 1)
DBPATCH_ADD(6030101, 0, 1)
DBPATCH_ADD(6030102, 0, 1)
DBPATCH_ADD(6030103, 0, 1)
DBPATCH_ADD(6030104, 0, 1)
DBPATCH_ADD(6030105, 0, 1)
DBPATCH_ADD(6030106, 0, 1)
DBPATCH_ADD(6030107, 0, 1)
DBPATCH_ADD(6030108, 0, 1)
DBPATCH_ADD(6030109, 0, 1)
DBPATCH_ADD(6030110, 0, 1)
DBPATCH_ADD(6030111, 0, 1)
DBPATCH_ADD(6030112, 0, 1)
DBPATCH_ADD(6030113, 0, 1)
DBPATCH_ADD(6030114, 0, 1)
DBPATCH_ADD(6030115, 0, 1)
DBPATCH_ADD(6030116, 0, 1)
DBPATCH_ADD(6030117, 0, 1)
DBPATCH_ADD(6030118, 0, 1)
DBPATCH_ADD(6030119, 0, 1)
DBPATCH_ADD(6030120, 0, 1)
DBPATCH_ADD(6030121, 0, 1)
DBPATCH_ADD(6030122, 0, 1)
DBPATCH_ADD(6030123, 0, 1)
DBPATCH_ADD(6030124, 0, 1)
DBPATCH_ADD(6030125, 0, 1)
DBPATCH_ADD(6030126, 0, 1)
DBPATCH_ADD(6030127, 0, 1)
DBPATCH_ADD(6030128, 0, 1)
DBPATCH_ADD(6030129, 0, 1)
DBPATCH_ADD(6030130, 0, 1)
DBPATCH_ADD(6030131, 0, 1)
DBPATCH_ADD(6030132, 0, 1)
DBPATCH_ADD(6030133, 0, 1)
DBPATCH_ADD(6030134, 0, 1)
DBPATCH_ADD(6030135, 0, 1)
DBPATCH_ADD(6030136, 0, 1)
DBPATCH_ADD(6030137, 0, 1)
DBPATCH_ADD(6030138, 0, 1)
DBPATCH_ADD(6030139, 0, 1)
DBPATCH_ADD(6030140, 0, 1)
DBPATCH_ADD(6030141, 0, 1)
DBPATCH_ADD(6030142, 0, 1)
DBPATCH_ADD(6030143, 0, 1)
DBPATCH_ADD(6030144, 0, 1)
DBPATCH_ADD(6030145, 0, 1)
DBPATCH_ADD(6030146, 0, 1)
DBPATCH_ADD(6030147, 0, 1)
DBPATCH_ADD(6030148, 0, 1)
DBPATCH_ADD(6030149, 0, 1)
DBPATCH_ADD(6030150, 0, 1)
DBPATCH_ADD(6030151, 0, 1)
DBPATCH_ADD(6030152, 0, 1)
DBPATCH_ADD(6030153, 0, 1)
DBPATCH_ADD(6030154, 0, 1)
DBPATCH_ADD(6030155, 0, 1)
DBPATCH_ADD(6030156, 0, 1)
DBPATCH_ADD(6030157, 0, 1)
DBPATCH_ADD(6030158, 0, 1)
DBPATCH_ADD(6030160, 0, 1)
DBPATCH_ADD(6030161, 0, 1)
DBPATCH_ADD(6030162, 0, 1)
DBPATCH_ADD(6030163, 0, 1)
DBPATCH_ADD(6030164, 0, 1)
DBPATCH_ADD(6030165, 0, 1)
DBPATCH_ADD(6030166, 0, 1)
DBPATCH_ADD(6030167, 0, 1)
DBPATCH_ADD(6030168, 0, 1)
DBPATCH_ADD(6030169, 0, 1)
DBPATCH_ADD(6030170, 0, 1)
DBPATCH_ADD(6030171, 0, 1)
DBPATCH_ADD(6030172, 0, 1)
DBPATCH_ADD(6030173, 0, 1)
DBPATCH_ADD(6030174, 0, 1)
DBPATCH_ADD(6030187, 0, 1)
DBPATCH_ADD(6030188, 0, 1)
DBPATCH_ADD(6030189, 0, 1)
DBPATCH_ADD(6030190, 0, 1)
DBPATCH_ADD(6030191, 0, 1)
DBPATCH_ADD(6030192, 0, 1)
DBPATCH_ADD(6030193, 0, 1)

DBPATCH_END()
