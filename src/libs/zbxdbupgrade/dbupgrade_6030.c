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

#include "zbxdbhigh.h"
#include "dbupgrade.h"
#include "zbxdbschema.h"
#include "zbxcrypto.h"
#include "zbxeval.h"
#include "zbxalgo.h"
#include "zbxexpr.h"
#include "zbxstr.h"
#include "zbxvariant.h"

/*
 * 6.4 development database patches
 */

#ifndef HAVE_SQLITE3

static int	DBpatch_6030000(void)
{
	DB_RESULT		result;
	DB_ROW			row;
	zbx_db_insert_t		db_insert;
	int			ret = SUCCEED;

	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	result = DBselect("select roleid,type,name,value_int from role_rule where name in ("
			"'ui.configuration.actions',"
			"'ui.services.actions',"
			"'ui.administration.general')");

	zbx_db_insert_prepare(&db_insert, "role_rule", "role_ruleid", "roleid", "type", "name", "value_int", NULL);

	while (NULL != (row = DBfetch(result)))
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
	DBfree_result(result);

	zbx_db_insert_autoincrement(&db_insert, "role_ruleid");

	if (SUCCEED == (ret = zbx_db_insert_execute(&db_insert)))
	{
		if (ZBX_DB_OK > DBexecute("delete from role_rule where name in ("
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
	const ZBX_FIELD	field = {"name", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("group_discovery", &field, NULL);
}

static int	DBpatch_6030002(void)
{
	if (ZBX_DB_OK > DBexecute(
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
	const ZBX_FIELD	field = {"url", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("scripts", &field);
}

static int	DBpatch_6030004(void)
{
	const ZBX_FIELD	field = {"new_window", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("scripts", &field);
}

static int	DBpatch_6030005(void)
{
	const ZBX_FIELD	old_field = {"host_metadata", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};
	const ZBX_FIELD	field = {"host_metadata", "", NULL, NULL, 0, ZBX_TYPE_TEXT, ZBX_NOTNULL, 0};

	return DBmodify_field_type("autoreg_host", &field, &old_field);
}

static int	DBpatch_6030006(void)
{
	const ZBX_FIELD	old_field = {"host_metadata", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};
	const ZBX_FIELD	field = {"host_metadata", "", NULL, NULL, 0, ZBX_TYPE_TEXT, ZBX_NOTNULL, 0};

	return DBmodify_field_type("proxy_autoreg_host", &field, &old_field);
}

static int	DBpatch_6030007(void)
{
	const ZBX_FIELD	field = {"server_status", "", NULL, NULL, 0, ZBX_TYPE_SHORTTEXT, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_6030008(void)
{
	const ZBX_FIELD	field = {"version", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("host_rtdata", &field);
}

static int	DBpatch_6030009(void)
{
	const ZBX_FIELD	field = {"compatibility", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("host_rtdata", &field);
}

static int	DBpatch_6030010(void)
{
	const ZBX_FIELD	field = {"url", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

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
	const ZBX_FIELD field = {"discovery_groupid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0};

	return DBdrop_not_null("config", &field);
}

static int	DBpatch_6030038(void)
{
	return DBdrop_foreign_key("dchecks", 1);
}

static int	DBpatch_6030039(void)
{
	const ZBX_FIELD	field = {"druleid", NULL, "drules", "druleid", 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_foreign_key("dchecks", 1, &field);
}

static int	DBpatch_6030040(void)
{
	return DBdrop_foreign_key("httptest", 2);
}

static int	DBpatch_6030041(void)
{
	const ZBX_FIELD	field = {"hostid", NULL, "hosts", "hostid", 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_foreign_key("httptest", 2, &field);
}

static int	DBpatch_6030042(void)
{
	return DBdrop_foreign_key("httptest", 3);
}

static int	DBpatch_6030043(void)
{
	const ZBX_FIELD	field = {"templateid", NULL, "httptest", "httptestid", 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_foreign_key("httptest", 3, &field);
}

static int	DBpatch_6030044(void)
{
	return DBdrop_foreign_key("httpstep", 1);
}

static int	DBpatch_6030045(void)
{
	const ZBX_FIELD	field = {"httptestid", NULL, "httptest", "httptestid", 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_foreign_key("httpstep", 1, &field);
}

static int	DBpatch_6030046(void)
{
	return DBdrop_foreign_key("httptestitem", 1);
}

static int	DBpatch_6030047(void)
{
	const ZBX_FIELD	field = {"httptestid", NULL, "httptest", "httptestid", 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_foreign_key("httptestitem", 1, &field);
}

static int	DBpatch_6030048(void)
{
	return DBdrop_foreign_key("httptestitem", 2);
}

static int	DBpatch_6030049(void)
{
	const ZBX_FIELD	field = {"itemid", NULL, "items", "itemid", 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_foreign_key("httptestitem", 2, &field);
}

static int	DBpatch_6030050(void)
{
	return DBdrop_foreign_key("httpstepitem", 1);
}

static int	DBpatch_6030051(void)
{
	const ZBX_FIELD	field = {"httpstepid", NULL, "httpstep", "httpstepid", 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_foreign_key("httpstepitem", 1, &field);
}

static int	DBpatch_6030052(void)
{
	return DBdrop_foreign_key("httpstepitem", 2);
}

static int	DBpatch_6030053(void)
{
	const ZBX_FIELD	field = {"itemid", NULL, "items", "itemid", 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_foreign_key("httpstepitem", 2, &field);
}

static int	DBpatch_6030054(void)
{
	return DBdrop_foreign_key("httptest_field", 1);
}

static int	DBpatch_6030055(void)
{
	const ZBX_FIELD	field = {"httptestid", NULL, "httptest", "httptestid", 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_foreign_key("httptest_field", 1, &field);
}

static int	DBpatch_6030056(void)
{
	return DBdrop_foreign_key("httpstep_field", 1);
}

static int	DBpatch_6030057(void)
{
	const ZBX_FIELD	field = {"httpstepid", NULL, "httpstep", "httpstepid", 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_foreign_key("httpstep_field", 1, &field);
}

static int	DBpatch_6030058(void)
{
	const ZBX_FIELD field = {"provider", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("media_type", &field);
}

static int	DBpatch_6030059(void)
{
	const ZBX_FIELD field = {"status", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBset_default("media_type", &field);
}

static int DBpatch_6030060(void)
{
	const ZBX_FIELD	field = {"url_name", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("triggers", &field);
}

static int DBpatch_6030061(void)
{
	const ZBX_FIELD	field = {"url", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("triggers", &field, NULL);
}

static int	DBpatch_6030062(void)
{
	DB_RESULT		result;
	DB_ROW			row;
	char			*sql;
	size_t			sql_alloc = 4096, sql_offset = 0;
	int			ret = SUCCEED;

	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	sql = zbx_malloc(NULL, sql_alloc);

	zbx_DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	result = DBselect("select moduleid,relative_path from module");

	while (NULL != (row = DBfetch(result)))
	{
		const char	*rel_path = row[1];
		char		*updated_path, *updated_path_esc;

		if (NULL == rel_path || '\0' == *rel_path)
			continue;

		updated_path = zbx_dsprintf(NULL, "modules/%s", rel_path);

		updated_path_esc = DBdyn_escape_string(updated_path);

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update module set relative_path='%s' "
				"where moduleid=%s;\n", updated_path_esc, row[0]);

		zbx_free(updated_path);
		zbx_free(updated_path_esc);

		ret = DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
	}
	DBfree_result(result);

	zbx_DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (SUCCEED == ret && 16 < sql_offset)
	{
		if (ZBX_DB_OK > DBexecute("%s", sql))
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

	zbx_db_insert_prepare(&db_insert, "module", "moduleid", "id", "relative_path", "status", "config", NULL);

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
	const ZBX_FIELD	field = {"name_upper", "", NULL, NULL, 128, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	if (SUCCEED == DBtrigger_exists("hosts", "hosts_name_upper_update"))
	{
		zabbix_log(LOG_LEVEL_WARNING, "hosts_name_upper_update trigger for table \"hosts\" already exists,"
				" skipping patch of adding \"name_upper\" column to \"hosts\" table");
		return SUCCEED;
	}

	return DBadd_field("hosts", &field);
}

static int	DBpatch_6030065(void)
{
	if (SUCCEED == DBtrigger_exists("hosts", "hosts_name_upper_update"))
	{
		zabbix_log(LOG_LEVEL_WARNING, "hosts_name_upper_update trigger for table \"hosts\" already exists,"
				" skipping patch of adding index to \"name_upper\" column");
		return SUCCEED;
	}

	return DBcreate_index("hosts", "hosts_6", "name_upper", 0);
}

static int	DBpatch_6030066(void)
{
	if (SUCCEED == DBtrigger_exists("hosts", "hosts_name_upper_update"))
	{
		zabbix_log(LOG_LEVEL_WARNING, "hosts_name_upper_update trigger for table \"hosts\" already exists,"
				" skipping patch of updating \"name_upper\" column");

		return SUCCEED;
	}

	if (ZBX_DB_OK > DBexecute("update hosts set name_upper=upper(name)"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_6030067(void)
{
	if (SUCCEED == DBtrigger_exists("hosts", "hosts_name_upper_insert"))
	{
		zabbix_log(LOG_LEVEL_WARNING, "hosts_name_upper_insert trigger for table \"hosts\" already exists,"
				" skipping patch of adding it to \"hosts\" table");
		return SUCCEED;
	}

	return zbx_dbupgrade_attach_trigger_with_function_on_insert("hosts", "name", "name_upper", "upper", "hostid");
}

static int	DBpatch_6030068(void)
{
	if (SUCCEED == DBtrigger_exists("hosts", "hosts_name_upper_update"))
	{
		zabbix_log(LOG_LEVEL_WARNING, "hosts_name_upper_update trigger for table \"hosts\" already exists,"
				" skipping patch of adding it to \"hosts\" table");
		return SUCCEED;
	}

	return zbx_dbupgrade_attach_trigger_with_function_on_update("hosts", "name", "name_upper", "upper", "hostid");
}

static int	DBpatch_6030069(void)
{
	const ZBX_FIELD field = {"name_upper", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	if (SUCCEED == DBtrigger_exists("items", "items_name_upper_update"))
	{
		zabbix_log(LOG_LEVEL_WARNING, "items_name_upper_update trigger for table \"items\" already exists,"
				" skipping patch of adding \"name_upper\" column to \"items\" table");
		return SUCCEED;
	}

	return DBadd_field("items", &field);
}

static int	DBpatch_6030070(void)
{
	if (SUCCEED == DBtrigger_exists("items", "items_name_upper_update"))
	{
		zabbix_log(LOG_LEVEL_WARNING, "items_name_upper_update trigger for table \"items\" already exists,"
				" skipping patch of adding index to \"name_upper\" column");

		return SUCCEED;
	}

	return DBcreate_index("items", "items_9", "hostid,name_upper", 0);
}

static int	DBpatch_6030071(void)
{
	if (SUCCEED == DBtrigger_exists("items", "items_name_upper_update"))
	{
		zabbix_log(LOG_LEVEL_WARNING, "items_name_upper_update trigger for table \"items\" already exists,"
				" skipping patch of updating \"name_upper\" column");
		return SUCCEED;
	}

	if (ZBX_DB_OK > DBexecute("update items set name_upper=upper(name)"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_6030072(void)
{
	if (SUCCEED == DBtrigger_exists("items", "items_name_upper_insert"))
	{
		zabbix_log(LOG_LEVEL_WARNING, "items_name_upper_insert trigger for table \"items\" already exists,"
				" skipping patch of adding it to \"items\" table");
		return SUCCEED;
	}

	return zbx_dbupgrade_attach_trigger_with_function_on_insert("items", "name", "name_upper", "upper", "itemid");
}

static int	DBpatch_6030073(void)
{
	if (SUCCEED == DBtrigger_exists("items", "items_name_upper_update"))
	{
		zabbix_log(LOG_LEVEL_WARNING, "items_name_upper_update trigger for table \"items\" already exists,"
				" skipping patch of adding it to \"items\" table");
		return SUCCEED;
	}

	return zbx_dbupgrade_attach_trigger_with_function_on_update("items", "name", "name_upper", "upper", "itemid");
}

static int	DBpatch_6030074(void)
{
	int		i;
	const char	*values[] = {
			"web.auditacts.filter.from", "web.actionlog.filter.from",
			"web.auditacts.filter.to", "web.actionlog.filter.to",
			"web.auditacts.filter.active", "web.actionlog.filter.active",
			"web.auditacts.filter.userids", "web.actionlog.filter.userids"
		};

	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	for (i = 0; i < (int)ARRSIZE(values); i += 2)
	{
		if (ZBX_DB_OK > DBexecute("update profiles set idx='%s' where idx='%s'", values[i + 1], values[i]))
			return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_6030075(void)
{
	const ZBX_FIELD	field = {"value_userid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_field("widget_field", &field);
}

static int	DBpatch_6030076(void)
{
	return DBcreate_index("widget_field", "widget_field_9", "value_userid", 0);
}

static int	DBpatch_6030077(void)
{
	const ZBX_FIELD	field = {"value_userid", NULL, "users", "userid", 0, ZBX_TYPE_ID, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("widget_field", 9, &field);
}

static int	DBpatch_6030078(void)
{
	const ZBX_FIELD	field = {"value_actionid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_field("widget_field", &field);
}

static int	DBpatch_6030079(void)
{
	return DBcreate_index("widget_field", "widget_field_10", "value_actionid", 0);
}

static int	DBpatch_6030080(void)
{
	const ZBX_FIELD	field = {"value_actionid", NULL, "actions", "actionid", 0, ZBX_TYPE_ID, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("widget_field", 10, &field);
}

static int	DBpatch_6030081(void)
{
	const ZBX_FIELD	field = {"value_mediatypeid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_field("widget_field", &field);
}

static int	DBpatch_6030082(void)
{
	return DBcreate_index("widget_field", "widget_field_11", "value_mediatypeid", 0);
}

static int	DBpatch_6030083(void)
{
	const ZBX_FIELD	field = {"value_mediatypeid", NULL, "media_type", "mediatypeid", 0, ZBX_TYPE_ID, 0,
			ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("widget_field", 11, &field);
}

/* patches for ZBXNEXT-276 */
/* create new tables */

static int	DBpatch_6030084(void)
{
	const ZBX_TABLE table =
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
	const ZBX_TABLE table =
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
	const ZBX_FIELD field = {"userid", NULL, "users", "userid", 0, ZBX_TYPE_ID, ZBX_NOTNULL, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("user_scim_group", 1, &field);
}

static int	DBpatch_6030090(void)
{
	const ZBX_FIELD field = {"scim_groupid", NULL, "scim_group", "scim_groupid", 0, ZBX_TYPE_ID, ZBX_NOTNULL,
			ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("user_scim_group", 2, &field);
}

static int	DBpatch_6030091(void)
{
	const ZBX_TABLE	table =
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
	const ZBX_FIELD	field = {"userdirectoryid", NULL, "userdirectory", "userdirectoryid", 0, ZBX_TYPE_ID,
			ZBX_NOTNULL, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("userdirectory_saml", 1, &field);
}

static int	DBpatch_6030093(void)
{
	const ZBX_TABLE	table =
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
	const ZBX_FIELD	field = {"userdirectoryid", NULL, "userdirectory", "userdirectoryid", 0, ZBX_TYPE_ID,
			ZBX_NOTNULL, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("userdirectory_ldap", 1, &field);
}

static int	DBpatch_6030095(void)
{
	const ZBX_TABLE	table =
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
	const ZBX_FIELD	field = {"userdirectoryid", NULL, "userdirectory", "userdirectoryid", 0, ZBX_TYPE_ID,
			ZBX_NOTNULL, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("userdirectory_media", 1, &field);
}

static int	DBpatch_6030099(void)
{
	const ZBX_FIELD	field = {"mediatypeid", NULL, "media_type", "mediatypeid", 0, ZBX_TYPE_ID, ZBX_NOTNULL,
			ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("userdirectory_media", 2, &field);
}

static int	DBpatch_6030100(void)
{
	const ZBX_TABLE	table =
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
	const ZBX_FIELD	field = {"userdirectoryid", NULL, "userdirectory", "userdirectoryid", 0, ZBX_TYPE_ID,
			ZBX_NOTNULL, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("userdirectory_idpgroup", 1, &field);
}

static int	DBpatch_6030104(void)
{
	const ZBX_FIELD	field = {"roleid", NULL, "role", "roleid", 0, ZBX_TYPE_ID, ZBX_NOTNULL,
			ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("userdirectory_idpgroup", 2, &field);
}

static int	DBpatch_6030105(void)
{
	const ZBX_TABLE	table =
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
	const ZBX_FIELD	field = {"userdirectory_idpgroupid", NULL, "userdirectory_idpgroup", "userdirectory_idpgroupid",
			0, ZBX_TYPE_ID, ZBX_NOTNULL, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("userdirectory_usrgrp", 1, &field);
}

static int	DBpatch_6030110(void)
{
	const ZBX_FIELD	field = {"usrgrpid", NULL, "usrgrp", "usrgrpid", 0, ZBX_TYPE_ID, ZBX_NOTNULL,
			ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("userdirectory_usrgrp", 2, &field);
}

/* add new fields to existing tables */

static int	DBpatch_6030111(void)
{
	const ZBX_FIELD	field = {"jit_provision_interval", "1h", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_6030112(void)
{
	const ZBX_FIELD	field = {"saml_jit_status", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_6030113(void)
{
	const ZBX_FIELD field = {"ldap_jit_status", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_6030114(void)
{
	const ZBX_FIELD	field = {"disabled_usrgrpid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_6030115(void)
{
	return DBcreate_index("config", "config_4", "disabled_usrgrpid", 0);
}

static int	DBpatch_6030116(void)
{
	const ZBX_FIELD	field = {"disabled_usrgrpid", NULL, "usrgrp", "usrgrpid", 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_foreign_key("config", 4, &field);
}

static int	DBpatch_6030117(void)
{
	const ZBX_FIELD field = {"idp_type", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("userdirectory", &field);
}

static int	DBpatch_6030118(void)
{
	const ZBX_FIELD field = {"provision_status", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("userdirectory", &field);
}

static int	DBpatch_6030119(void)
{
	return DBcreate_index("userdirectory", "userdirectory_1", "idp_type", 0);
}

static int	DBpatch_6030120(void)
{
	const ZBX_FIELD field = {"userdirectoryid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_field("users", &field);
}

static int	DBpatch_6030121(void)
{
	const ZBX_FIELD field = {"ts_provisioned", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("users", &field);
}

static int	DBpatch_6030122(void)
{
	return DBcreate_index("users", "users_2", "userdirectoryid", 0);
}

static int	DBpatch_6030123(void)
{
	const ZBX_FIELD field = {"userdirectoryid", NULL, "userdirectory", "userdirectoryid", 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_foreign_key("users", 2, &field);
}

/* migrate data */

static int	migrate_ldap_data(void)
{
	DB_RESULT	result = DBselect("select userdirectoryid,host,port,base_dn,bind_dn,bind_password,"
					"search_attribute,start_tls,search_filter"
					" from userdirectory");
	if (NULL == result)
		return FAIL;

	DB_ROW	row;

	while (NULL != (row = DBfetch(result)))
	{
		char	*host = DBdyn_escape_string(row[1]);
		char	*base_dn = DBdyn_escape_string(row[3]);
		char	*bind_dn = DBdyn_escape_string(row[4]);
		char	*bind_password = DBdyn_escape_string(row[5]);
		char	*search_attribute = DBdyn_escape_string(row[6]);
		char	*search_filter = DBdyn_escape_string(row[8]);

		int	rc = DBexecute("insert into userdirectory_ldap (userdirectoryid,host,port,"
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
			DBfree_result(result);
			return FAIL;
		}

#define IDP_TYPE_LDAP	1	/* user directory of type LDAP */
		if (ZBX_DB_OK > DBexecute("update userdirectory set idp_type=%d where userdirectoryid=%s",
				IDP_TYPE_LDAP, row[0]))
		{
			DBfree_result(result);
			return FAIL;
		}
#undef IDP_TYPE_LDAP
	}

	DBfree_result(result);

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
	DB_RESULT	result = DBselect("select saml_idp_entityid,saml_sso_url,saml_slo_url,saml_username_attribute,"
					"saml_sp_entityid,saml_nameid_format,saml_sign_messages,saml_sign_assertions,"
					"saml_sign_authn_requests,saml_sign_logout_requests,saml_sign_logout_responses,"
					"saml_encrypt_nameid,saml_encrypt_assertions"
					" from config");
	if (NULL == result)
		return FAIL;

	DB_ROW	row = DBfetch(result);

	if (NULL == row)
	{
		DBfree_result(result);
		return FAIL;
	}

	if ('\0' == *row[0] && '\0' == *row[1] && '\0' == *row[2] && '\0' == *row[3] && '\0' == *row[4] &&
			'\0' == *row[5] && 0 == atoi(row[6]) && 0 == atoi(row[7]) && 0 == atoi(row[8]) &&
			0 == atoi(row[9]) && 0 == atoi(row[10]) && 0 == atoi(row[11]) && 0 == atoi(row[12]))
	{
		DBfree_result(result);
		return SUCCEED;
	}

	zbx_uint64_t	userdirectoryid = DBget_maxid("userdirectory");

#define IDP_TYPE_SAML	2	/* user directory of type SAML */
	int	rc = DBexecute("insert into userdirectory (userdirectoryid,idp_type,description) values"
			" (" ZBX_FS_UI64 ",%d,'')", userdirectoryid, IDP_TYPE_SAML);
#undef IDP_TYPE_SAML
	if (ZBX_DB_OK > rc)
	{
		DBfree_result(result);
		return FAIL;
	}

	char	*idp_entityid = DBdyn_escape_string(row[0]);
	char	*sso_url = DBdyn_escape_string(row[1]);
	char	*slo_url = DBdyn_escape_string(row[2]);
	char	*username_attribute = DBdyn_escape_string(row[3]);
	char	*sp_entityid = DBdyn_escape_string(row[4]);
	char	*nameid_format = DBdyn_escape_string(row[5]);

	int	rc2 = DBexecute("insert into userdirectory_saml (userdirectoryid,idp_entityid,sso_url,slo_url,"
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

	DBfree_result(result);

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
	const ZBX_FIELD	field = {"ldap_auth_enabled", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBrename_field("config", "ldap_configured", &field);
}

/* modify fields in tables */

static int	DBpatch_6030127(void)
{
	const ZBX_FIELD field = {"roleid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0};

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
	const ZBX_FIELD	old_field = {"info", "", NULL, NULL, 0, ZBX_TYPE_TEXT, ZBX_NOTNULL, 0};
	const ZBX_FIELD	field = {"info", "", NULL, NULL, 0, ZBX_TYPE_LONGTEXT, ZBX_NOTNULL, 0};

	return DBmodify_field_type("task_result", &field, &old_field);
}

static int	DBpatch_6030150(void)
{
	const ZBX_FIELD	field = {"max_repetitions", "10", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("interface_snmp", &field);
}

static int	DBpatch_6030151(void)
{
	const ZBX_TABLE table =
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
	const ZBX_FIELD	field = {"eventid", NULL, "events", "eventid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("event_symptom", 1, &field);
}

static int	DBpatch_6030153(void)
{
	const ZBX_FIELD	field = {"cause_eventid", NULL, "events", "eventid", 0, 0, 0, 0};

	return DBadd_foreign_key("event_symptom", 2, &field);
}

static int	DBpatch_6030154(void)
{
	const ZBX_FIELD field = {"cause_eventid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_field("problem", &field);
}

static int	DBpatch_6030155(void)
{
	const ZBX_FIELD	field = {"cause_eventid", NULL, "events", "eventid", 0, 0, 0, 0};

	return DBadd_foreign_key("problem", 3, &field);
}

static int	DBpatch_6030156(void)
{
	const ZBX_FIELD field = {"pause_symptoms", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("actions", &field);
}

static int	DBpatch_6030157(void)
{
	const ZBX_FIELD field = {"taskid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_field("acknowledges", &field);
}

static int	DBpatch_6030158(void)
{
	return DBcreate_index("event_symptom", "event_symptom_1", "cause_eventid", 0);
}

#undef HOST_STATUS_TEMPLATE
#define HOST_STATUS_TEMPLATE		3

typedef struct
{
	char	*value;
	char	*newvalue;
	int	type;
	int	sortorder;
}
zbx_db_valuemap_mapping_t;

ZBX_PTR_VECTOR_DECL(valuemap_mapping_ptr, zbx_db_valuemap_mapping_t *)
ZBX_PTR_VECTOR_IMPL(valuemap_mapping_ptr, zbx_db_valuemap_mapping_t *)

typedef struct
{
	uint64_t				child_templateid;
	uint64_t				parent_valuemapid;
	char					*uuid;
	char					*name;

	zbx_vector_uint64_t			itemids;
	zbx_vector_valuemap_mapping_ptr_t	mappings;
	int					uniq;
}
zbx_db_valuemap_t;

typedef struct
{
	uint64_t	templateid;
	char		*name;
}
zbx_child_valuemap_t;

ZBX_PTR_VECTOR_DECL(valuemap_ptr, zbx_db_valuemap_t *)
ZBX_PTR_VECTOR_IMPL(valuemap_ptr, zbx_db_valuemap_t *)

ZBX_PTR_VECTOR_DECL(child_valuemap_ptr, zbx_child_valuemap_t *)
ZBX_PTR_VECTOR_IMPL(child_valuemap_ptr, zbx_child_valuemap_t *)

static int	DBpatch_propogate_valuemap(zbx_db_valuemap_t *valuemap, uint64_t valuemapid)
{
	DB_RESULT		result;
	DB_ROW			row;
	uint64_t		itemid, child_templateid;

	child_templateid = valuemap->child_templateid;

	result = DBselect("select i.itemid from items i"
			" where i.valuemapid=" ZBX_FS_UI64" and (i.hostid=" ZBX_FS_UI64" or i.hostid in"
			" (select h.hostid from hosts h,hosts_templates ht"
			" where ht.hostid=h.hostid and h.status <>%d and ht.templateid=" ZBX_FS_UI64"))",
			valuemapid, child_templateid, HOST_STATUS_TEMPLATE, child_templateid);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_DBROW2UINT64(itemid, row[0]);
		zbx_vector_uint64_append(&valuemap->itemids, itemid);
	}

	DBfree_result(result);

	result = DBselect("select value,newvalue,type,sortorder from valuemap_mapping where valuemapid=" ZBX_FS_UI64,
			valuemapid);

	while (NULL != (row = DBfetch(result)))
	{
		zbx_db_valuemap_mapping_t	*mapping;

		mapping = zbx_malloc(NULL, sizeof(zbx_db_valuemap_mapping_t));

		mapping->value = zbx_strdup(NULL, row[0]);
		mapping->newvalue = zbx_strdup(NULL, row[1]);
		mapping->type = atoi(row[2]);
		mapping->sortorder = atoi(row[3]);

		zbx_vector_valuemap_mapping_ptr_append(&valuemap->mappings, mapping);
	}

	DBfree_result(result);

	return valuemap->mappings.values_num;
}

static void	mapping_clear(zbx_db_valuemap_mapping_t *mapping)
{
	zbx_free(mapping->value);
	zbx_free(mapping->newvalue);
}

static void	mapping_free(zbx_db_valuemap_mapping_t *mapping)
{
	mapping_clear(mapping);
	zbx_free(mapping);
}

static void	valuemap_clear(zbx_db_valuemap_t *valuemap)
{
	zbx_free(valuemap->uuid);
	zbx_free(valuemap->name);
	zbx_vector_uint64_destroy(&valuemap->itemids);
	zbx_vector_valuemap_mapping_ptr_clear_ext(&valuemap->mappings, mapping_free);
	zbx_vector_valuemap_mapping_ptr_destroy(&valuemap->mappings);
}

static void	valuemap_free(zbx_db_valuemap_t *valuemap)
{
	valuemap_clear(valuemap);
	zbx_free(valuemap);
}

static void	child_valuemap_free(zbx_child_valuemap_t *valuemap)
{
	zbx_free(valuemap->name);
	zbx_free(valuemap);
}

static void	select_pure_parents(zbx_vector_uint64_t *ids)
{
	DB_RESULT	result;
	DB_ROW		row;

	result = DBselect("select distinct ht.templateid from hosts_templates ht,hosts h"
			" where ht.hostid=h.hostid and h.status=%d and ht.templateid not in "
			"(select hostid from hosts_templates)", HOST_STATUS_TEMPLATE);

	while (NULL != (row = DBfetch(result)))
	{
		zbx_uint64_t		id;

		ZBX_DBROW2UINT64(id, row[0]);
		zbx_vector_uint64_append(ids, id);
	}

	DBfree_result(result);

		if (0 == ids->values_num)
			return;

	zbx_vector_uint64_sort(ids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_uniq(ids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
}

static int	valuemap_compare(const zbx_db_valuemap_t **vm1, const zbx_db_valuemap_t **vm2)
{
	const zbx_db_valuemap_t	*v1, *v2;

	v1 = *vm1;
	v2 = *vm2;

	if (v1->child_templateid == v2->child_templateid && v1->parent_valuemapid == v2->parent_valuemapid)
		return 0;

	return 1;
}

static void	zbx_vector_valuemap_ptr_uniq2(zbx_vector_valuemap_ptr_t *vector, zbx_compare_func_t compare_func)
{
	if (2 <= vector->values_num)
	{
		int	i, j;

		for (i = 0; i < vector->values_num; i++)
		{
			j = i + 1;

			while (j < vector->values_num)
			{
				if (0 == compare_func(&vector->values[i], &vector->values[j]))
				{
					valuemap_free(vector->values[j]);
					zbx_vector_valuemap_ptr_remove(vector, j);
				}
				else
					j++;
			}
		}
	}
}

static void	collect_valuemaps(zbx_vector_uint64_t *parent_ids, zbx_vector_uint64_t *child_templateids,
		zbx_vector_valuemap_ptr_t *valuemaps, int *mappings_num)
{
	DB_RESULT		result;
	DB_ROW			row;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_vector_uint64_t	loc_child_templateids;

	if (0 == parent_ids->values_num)
		return;

	zbx_vector_uint64_create(&loc_child_templateids);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select v.valuemapid,v.name,ht.hostid,h.host,ht.templateid"
			" from hosts_templates ht"
			" join hosts h on h.hostid=ht.hostid and h.status=%d"
			" left join valuemap v on v.hostid=ht.templateid where", HOST_STATUS_TEMPLATE);
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "ht.templateid", parent_ids->values,
			parent_ids->values_num);
	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		char			*template_name, *seed = NULL;
		zbx_db_valuemap_t	*valuemap, *valuemap_copy;
		zbx_uint64_t		valuemapid, child_templateid, parent_templateid;
		int			i;

		ZBX_DBROW2UINT64(valuemapid, row[0]);
		ZBX_DBROW2UINT64(child_templateid, row[2]);
		ZBX_DBROW2UINT64(parent_templateid, row[4]);
		template_name = zbx_strdup(NULL, row[3]);
		template_name = zbx_update_template_name(template_name);

		if (0 != valuemapid)
		{
			valuemap = zbx_malloc(NULL, sizeof(zbx_db_valuemap_t));
			valuemap->child_templateid = child_templateid;
			valuemap->parent_valuemapid = valuemapid;
			valuemap->name = zbx_strdup(NULL, row[1]);
			seed = zbx_dsprintf(seed, "%s/%s", template_name, row[1]);
			valuemap->uuid = zbx_gen_uuid4(seed);
			zbx_free(seed);
			valuemap->uniq = 0;
			zbx_vector_uint64_create(&valuemap->itemids);
			zbx_vector_valuemap_mapping_ptr_create(&valuemap->mappings);

			mappings_num += DBpatch_propogate_valuemap(valuemap, valuemapid);
			zbx_vector_valuemap_ptr_append(valuemaps, valuemap);
		}

		zbx_vector_uint64_append(&loc_child_templateids, child_templateid);

		for (i = 0; i < valuemaps->values_num; i++)
		{
			valuemap = valuemaps->values[i];

			if (parent_templateid == valuemap->child_templateid)
			{
				valuemap_copy = zbx_malloc(NULL, sizeof(zbx_db_valuemap_t));
				valuemap_copy->child_templateid = child_templateid;
				valuemap_copy->parent_valuemapid = valuemap->parent_valuemapid;
				valuemap_copy->name = zbx_strdup(NULL, valuemap->name);
				seed = zbx_dsprintf(seed, "%s/%s", template_name, valuemap->name);
				valuemap_copy->uuid = zbx_gen_uuid4(seed);
				zbx_free(seed);
				zbx_vector_uint64_create(&valuemap_copy->itemids);
				zbx_vector_valuemap_mapping_ptr_create(&valuemap_copy->mappings);

				mappings_num += DBpatch_propogate_valuemap(valuemap_copy, valuemap->parent_valuemapid);
				valuemap_copy->uniq = 0;
				zbx_vector_valuemap_ptr_append(valuemaps, valuemap_copy);
			}
		}

		zbx_free(template_name);
	}

	DBfree_result(result);
	zbx_free(sql);

	collect_valuemaps(&loc_child_templateids, child_templateids, valuemaps, mappings_num);

	zbx_vector_uint64_append_array(child_templateids, loc_child_templateids.values,
			loc_child_templateids.values_num);
	zbx_vector_uint64_destroy(&loc_child_templateids);
}

static int	DBpatch_6030159(void)
{
	zbx_vector_valuemap_ptr_t		valuemaps;
	zbx_vector_child_valuemap_ptr_t		child_valuemaps;
	zbx_vector_uint64_t			parent_ids, child_templateids;
	DB_RESULT				result;
	DB_ROW					row;
	int					changed, i, j, mappings_num = 0, ret = SUCCEED;
	char					*sql = NULL;
	size_t					sql_alloc = 0, sql_offset = 0;
	zbx_db_insert_t				db_insert_valuemap, db_insert_valuemap_mapping;
	zbx_uint64_t				valuemapid, valuemap_mappingid;

	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return ret;

	zbx_vector_uint64_create(&parent_ids);
	select_pure_parents(&parent_ids);

	if (0 == parent_ids.values_num)
		goto out;

	zbx_vector_valuemap_ptr_create(&valuemaps);
	zbx_vector_child_valuemap_ptr_create(&child_valuemaps);
	zbx_vector_uint64_create(&child_templateids);

	collect_valuemaps(&parent_ids, &child_templateids, &valuemaps, &mappings_num);

	if (0 == valuemaps.values_num)
		goto clean;

	zbx_vector_uint64_sort(&child_templateids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_uniq(&child_templateids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select name,hostid from valuemap where");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid", child_templateids.values,
			child_templateids.values_num);
	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		zbx_child_valuemap_t	*valuemap;
		uint64_t		templateid;

		ZBX_DBROW2UINT64(templateid, row[1]);

		valuemap = zbx_malloc(NULL, sizeof(zbx_child_valuemap_t));
		valuemap->templateid = templateid;
		valuemap->name = zbx_strdup(NULL, row[0]);

		zbx_vector_child_valuemap_ptr_append(&child_valuemaps, valuemap);
	}

	DBfree_result(result);
	zbx_free(sql);
	sql_alloc = 0;
	sql_offset = 0;

	zbx_vector_valuemap_ptr_uniq2(&valuemaps, (zbx_compare_func_t)valuemap_compare);

	do
	{
		zbx_db_valuemap_t	*valuemap, *valuemap2;

		changed = 0;

		for (i = 0; i < child_valuemaps.values_num; i++)
		{
			zbx_child_valuemap_t	*child_valuemap;

			child_valuemap = child_valuemaps.values[i];

			for (j = 0; j < valuemaps.values_num; j++)
			{
				valuemap = valuemaps.values[j];

				if (valuemap->child_templateid == child_valuemap->templateid &&
						0 == strcmp(valuemap->name, child_valuemap->name) &&
						0 == valuemap->uniq)
				{
					changed++;
					valuemap->uniq++;
				}
			}
		}

		for (i = 0; i < valuemaps.values_num; i++)
		{
			valuemap = valuemaps.values[i];

			for (j = i + 1; j < valuemaps.values_num; j++)
			{
				valuemap2 = valuemaps.values[j];

				if (valuemap->child_templateid == valuemap2->child_templateid &&
						0 == strcmp(valuemap->name, valuemap2->name) &&
						valuemap->uniq == valuemap2->uniq)
				{
					changed++;
					valuemap2->uniq++;
				}
			}
		}

		for (i = 0; i < valuemaps.values_num; i++)
		{
			valuemap = valuemaps.values[i];

			if (0 != valuemap->uniq)
			{
				valuemap->name = zbx_dsprintf(valuemap->name, "%s %d", valuemap->name, valuemap->uniq);
				valuemap->uniq = 0;
			}
		}
	}while (0 != changed);

	zbx_DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	valuemapid = DBget_maxid_num("valuemap", valuemaps.values_num);
	valuemap_mappingid = DBget_maxid_num("valuemap_mapping", mappings_num);

	zbx_db_insert_prepare(&db_insert_valuemap, "valuemap", "valuemapid", "hostid", "name", "uuid", NULL);
	zbx_db_insert_prepare(&db_insert_valuemap_mapping, "valuemap_mapping", "valuemap_mappingid",
			"valuemapid", "value", "newvalue", "type", "sortorder", NULL);

	for (i = 0; i < valuemaps.values_num; i++)
	{
		zbx_db_valuemap_t	*valuemap;

		valuemap = valuemaps.values[i];
		zbx_db_insert_add_values(&db_insert_valuemap, valuemapid, valuemap->child_templateid,
				valuemap->name, valuemap->uuid);

		for (j = 0; j < valuemap->itemids.values_num; j++)
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update items set ");
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "valuemapid=%s",
					DBsql_id_ins(valuemapid));
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " where itemid=" ZBX_FS_UI64 ";\n",
					valuemap->itemids.values[j]);
		}

		for (j = 0; j < valuemap->mappings.values_num; j++)
		{
			zbx_db_valuemap_mapping_t	*valuemap_mapping;

			valuemap_mapping = valuemap->mappings.values[j];
			zbx_db_insert_add_values(&db_insert_valuemap_mapping, valuemap_mappingid, valuemapid,
					valuemap_mapping->value, valuemap_mapping->newvalue,
					valuemap_mapping->type, valuemap_mapping->sortorder);
			valuemap_mappingid++;
		}

		valuemapid++;
	}

	zbx_db_insert_execute(&db_insert_valuemap);
	zbx_db_insert_clean(&db_insert_valuemap);

	zbx_db_insert_execute(&db_insert_valuemap_mapping);
	zbx_db_insert_clean(&db_insert_valuemap_mapping);

	zbx_DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (16 < sql_offset && ZBX_DB_OK > DBexecute("%s", sql))
		ret = FAIL;

	zbx_free(sql);
clean:
	zbx_vector_uint64_destroy(&child_templateids);
	zbx_vector_child_valuemap_ptr_clear_ext(&child_valuemaps, child_valuemap_free);
	zbx_vector_child_valuemap_ptr_destroy(&child_valuemaps);
	zbx_vector_valuemap_ptr_clear_ext(&valuemaps, valuemap_free);
	zbx_vector_valuemap_ptr_destroy(&valuemaps);
out:
	zbx_vector_uint64_destroy(&parent_ids);

	return ret;
}

typedef struct
{
	uint64_t	child_templateid;
	uint64_t	parent_hostmacroid;
	uint64_t	parent_templateid;
	char		*macro;
	char		*value;
	char		*description;
	int		type;
	int		automatic;
}
zbx_db_hostmacro_t;

typedef struct
{
	uint64_t	templateid;
	char		*macro;
}
zbx_child_hostmacro_t;

ZBX_PTR_VECTOR_DECL(hostmacro_ptr, zbx_db_hostmacro_t *)
ZBX_PTR_VECTOR_IMPL(hostmacro_ptr, zbx_db_hostmacro_t *)

ZBX_PTR_VECTOR_DECL(child_hostmacro_ptr, zbx_child_hostmacro_t *)
ZBX_PTR_VECTOR_IMPL(child_hostmacro_ptr, zbx_child_hostmacro_t *)

static void	hostmacro_clear(zbx_db_hostmacro_t *hostmacro)
{
	zbx_free(hostmacro->macro);
	zbx_free(hostmacro->value);
	zbx_free(hostmacro->description);
}

static void	hostmacro_free(zbx_db_hostmacro_t *hostmacro)
{
	hostmacro_clear(hostmacro);
	zbx_free(hostmacro);
}

static void	child_hostmacro_free(zbx_child_hostmacro_t *hostmacro)
{
	zbx_free(hostmacro->macro);
	zbx_free(hostmacro);
}

static int	hostmacro_sort(const zbx_db_hostmacro_t **hm1, const zbx_db_hostmacro_t **hm2)
{
	const zbx_db_hostmacro_t	*m1, *m2;

	m1 = *hm1;
	m2 = *hm2;

	if (m1->parent_templateid < m2->parent_templateid )
		return -1;

	if (m1->parent_templateid > m2->parent_templateid )
		return 1;

	return 0;
}

static int	hostmacro_compare(const zbx_db_hostmacro_t **hm1, const zbx_db_hostmacro_t **hm2)
{
	const zbx_db_hostmacro_t	*m1, *m2;

	m1 = *hm1;
	m2 = *hm2;

	if (m1->child_templateid == m2->child_templateid && 0 == strcmp(m1->macro, m2->macro))
		return 0;

	return 1;
}

static void	zbx_vector_hostmacro_ptr_uniq2(zbx_vector_hostmacro_ptr_t *vector, zbx_compare_func_t compare_func)
{
	if (2 <= vector->values_num)
	{
		int	i, j;

		for (i = 0; i < vector->values_num; i++)
		{
			j = i + 1;

			while (j < vector->values_num)
			{
				if (0 == compare_func(&vector->values[i], &vector->values[j]))
				{
					hostmacro_free(vector->values[j]);
					zbx_vector_hostmacro_ptr_remove(vector, j);
				}
				else
					j++;
			}
		}
	}
}

static void	collect_hostmacros(zbx_vector_uint64_t *parent_ids, zbx_vector_uint64_t *child_templateids,
		zbx_vector_hostmacro_ptr_t *hostmacros)
{
	DB_RESULT		result;
	DB_ROW			row;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_vector_uint64_t	loc_child_templateids;

	if (0 == parent_ids->values_num)
		return;

	zbx_vector_uint64_create(&loc_child_templateids);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select hm.hostmacroid,hm.macro,hm.value,hm.description,hm.type,hm.automatic,ht.hostid,"
			"ht.templateid"
			" from hosts_templates ht"
			" join hosts h on h.hostid=ht.hostid and h.status=%d"
			" left join hostmacro hm on hm.hostid=ht.templateid where", HOST_STATUS_TEMPLATE);
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "ht.templateid", parent_ids->values,
			parent_ids->values_num);
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " order by ht.templateid");
	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		zbx_db_hostmacro_t	*hostmacro, *hostmacro_copy;
		zbx_uint64_t		hostmacroid, child_templateid, parent_templateid;
		int			i;

		ZBX_DBROW2UINT64(hostmacroid, row[0]);
		ZBX_DBROW2UINT64(child_templateid, row[6]);
		ZBX_DBROW2UINT64(parent_templateid, row[7]);

		if (0 != hostmacroid)
		{
			hostmacro = zbx_malloc(NULL, sizeof(zbx_db_hostmacro_t));
			hostmacro->child_templateid = child_templateid;
			hostmacro->parent_hostmacroid = hostmacroid;
			hostmacro->parent_templateid = parent_templateid;
			hostmacro->macro = zbx_strdup(NULL, row[1]);
			hostmacro->value = zbx_strdup(NULL, row[2]);
			hostmacro->description = zbx_strdup(NULL, row[3]);
			hostmacro->type = atoi(row[4]);
			hostmacro->automatic = atoi(row[5]);
			zbx_vector_hostmacro_ptr_append(hostmacros, hostmacro);
		}

		zbx_vector_uint64_append(&loc_child_templateids, child_templateid);

		for (i = 0; i < hostmacros->values_num; i++)
		{
			hostmacro = hostmacros->values[i];

			if (parent_templateid == hostmacro->child_templateid)
			{
				hostmacro_copy = zbx_malloc(NULL, sizeof(zbx_db_hostmacro_t));
				hostmacro_copy->child_templateid = child_templateid;
				hostmacro_copy->parent_hostmacroid = hostmacro->parent_hostmacroid;
				hostmacro_copy->parent_templateid = parent_templateid;
				hostmacro_copy->macro = zbx_strdup(NULL, hostmacro->macro);
				hostmacro_copy->value = zbx_strdup(NULL, hostmacro->value);
				hostmacro_copy->description = zbx_strdup(NULL, hostmacro->description);
				hostmacro_copy->type = hostmacro->type;
				hostmacro_copy->automatic = hostmacro->automatic;
				zbx_vector_hostmacro_ptr_append(hostmacros, hostmacro_copy);
			}
		}
	}

	DBfree_result(result);
	zbx_free(sql);

	zbx_vector_uint64_sort(&loc_child_templateids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_uniq(&loc_child_templateids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	collect_hostmacros(&loc_child_templateids, child_templateids, hostmacros);

	zbx_vector_uint64_append_array(child_templateids, loc_child_templateids.values,
			loc_child_templateids.values_num);
	zbx_vector_uint64_destroy(&loc_child_templateids);
}


static int	DBpatch_6030160(void)
{
	zbx_vector_hostmacro_ptr_t		hostmacros;
	zbx_vector_child_hostmacro_ptr_t	child_hostmacros;
	zbx_vector_uint64_t			parent_ids, child_templateids;
	DB_RESULT				result;
	DB_ROW					row;
	int					i, j;
	char					*sql = NULL;
	size_t					sql_alloc = 0, sql_offset = 0;
	zbx_db_insert_t				db_insert_hostmacro;

	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	zbx_vector_uint64_create(&parent_ids);
	select_pure_parents(&parent_ids);

	if (0 == parent_ids.values_num)
		goto out;

	zbx_vector_hostmacro_ptr_create(&hostmacros);
	zbx_vector_child_hostmacro_ptr_create(&child_hostmacros);
	zbx_vector_uint64_create(&child_templateids);

	collect_hostmacros(&parent_ids, &child_templateids, &hostmacros);

	if (0 == hostmacros.values_num)
		goto clean;

	zbx_vector_uint64_sort(&child_templateids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_uniq(&child_templateids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select macro,hostid from hostmacro where");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid", child_templateids.values,
			child_templateids.values_num);
	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		zbx_child_hostmacro_t	*hostmacro;
		uint64_t		templateid;

		ZBX_DBROW2UINT64(templateid, row[1]);

		hostmacro = zbx_malloc(NULL, sizeof(zbx_child_hostmacro_t));
		hostmacro->templateid = templateid;
		hostmacro->macro = zbx_strdup(NULL, row[0]);

		zbx_vector_child_hostmacro_ptr_append(&child_hostmacros, hostmacro);
	}

	DBfree_result(result);
	zbx_free(sql);

	zbx_vector_hostmacro_ptr_sort(&hostmacros, (zbx_compare_func_t)hostmacro_sort);
	zbx_vector_hostmacro_ptr_uniq2(&hostmacros, (zbx_compare_func_t)hostmacro_compare);

	for (i = 0; i < child_hostmacros.values_num; i++)
	{
		zbx_child_hostmacro_t	*child_hostmacro;

		child_hostmacro = child_hostmacros.values[i];

		for (j = 0; j < hostmacros.values_num; j++)
		{
			zbx_db_hostmacro_t	*hostmacro;

			hostmacro = hostmacros.values[j];

			if (hostmacro->child_templateid == child_hostmacro->templateid &&
					0 == strcmp(hostmacro->macro, child_hostmacro->macro))
			{
				hostmacro_free(hostmacro);
				zbx_vector_hostmacro_ptr_remove(&hostmacros, j);
			}
		}
	}

	if (0 != hostmacros.values_num)
	{
		zbx_uint64_t	hostmacroid;

		hostmacroid = DBget_maxid_num("hostmacro", hostmacros.values_num);
		zbx_db_insert_prepare(&db_insert_hostmacro, "hostmacro", "hostmacroid", "hostid", "macro", "value",
				"description", "type", "automatic", NULL);

		for (i = 0; i < hostmacros.values_num; i++)
		{
			zbx_db_hostmacro_t	*hostmacro;

			hostmacro = hostmacros.values[i];
			zbx_db_insert_add_values(&db_insert_hostmacro, hostmacroid, hostmacro->child_templateid,
					hostmacro->macro, hostmacro->value, hostmacro->description, hostmacro->type,
					hostmacro->automatic);
			hostmacroid++;
		}

		zbx_db_insert_execute(&db_insert_hostmacro);
		zbx_db_insert_clean(&db_insert_hostmacro);
	}

clean:
	zbx_vector_uint64_destroy(&child_templateids);
	zbx_vector_child_hostmacro_ptr_clear_ext(&child_hostmacros, child_hostmacro_free);
	zbx_vector_child_hostmacro_ptr_destroy(&child_hostmacros);
	zbx_vector_hostmacro_ptr_clear_ext(&hostmacros, hostmacro_free);
	zbx_vector_hostmacro_ptr_destroy(&hostmacros);
out:
	zbx_vector_uint64_destroy(&parent_ids);

	return SUCCEED;
}

typedef struct zbx_db_patch_tag
{
	char			*tag;
	char			*value;
	zbx_vector_uint64_t	itemids;
}
zbx_db_patch_tag_t;

ZBX_PTR_VECTOR_DECL(tag_ptr, zbx_db_patch_tag_t *)
ZBX_PTR_VECTOR_IMPL(tag_ptr, zbx_db_patch_tag_t *)

static void	tag_free(zbx_db_patch_tag_t *tag)
{
	zbx_vector_uint64_destroy(&tag->itemids);
	zbx_free(tag->tag);
	zbx_free(tag->value);
	zbx_free(tag);
}

static void	DBpatch_propogate_tag(zbx_db_patch_tag_t *tag, zbx_uint64_t hostid, zbx_uint64_t itemid)
{
	DB_RESULT	result;
	DB_ROW		row;

	result = DBselect("select h.hostid,i.itemid from hosts h,items i,hosts_templates ht"
			" where h.hostid=ht.hostid and ht.templateid=" ZBX_FS_UI64" and i.hostid=h.hostid and"
			" i.templateid=" ZBX_FS_UI64, hostid, itemid);

	while (NULL != (row = DBfetch(result)))
	{
		zbx_uint64_t		child_hostid, child_itemid;

		ZBX_DBROW2UINT64(child_hostid, row[0]);
		ZBX_DBROW2UINT64(child_itemid, row[1]);
		zbx_vector_uint64_append(&tag->itemids, child_itemid);
		DBpatch_propogate_tag(tag, child_hostid, child_itemid);
	}

	DBfree_result(result);
}

static int	DBpatch_6030161(void)
{
	zbx_vector_tag_ptr_t	tags;
	DB_RESULT		result;
	DB_ROW			row;
	int			i, j, new_tags = 0;
	zbx_db_patch_tag_t	*tag;
	zbx_uint64_t		itemtagid;
	zbx_db_insert_t		db_insert_itemtag;

	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	zbx_vector_tag_ptr_create(&tags);

	result = DBselect("select th.tag,th.value,h2.hostid,i.itemid from host_tag th,hosts h2,items i"
			" where th.hostid=h2.hostid and i.hostid=h2.hostid and i.templateid is null and"
			" h2.hostid in (select distinct h.hostid from hosts h,hosts h1,hosts_templates ht"
			" where h.status=%d and ht.templateid=h.hostid and ht.hostid=h1.hostid and h1.status=%d)"
			" order by h2.hostid asc", HOST_STATUS_TEMPLATE, HOST_STATUS_TEMPLATE);

	while (NULL != (row = DBfetch(result)))
	{
		zbx_uint64_t		hostid, itemid;

		tag = zbx_malloc(NULL, sizeof(zbx_db_tag_t));
		tag->tag = zbx_strdup(NULL, row[0]);
		tag->value = zbx_strdup(NULL, row[1]);
		ZBX_DBROW2UINT64(hostid, row[2]);
		ZBX_DBROW2UINT64(itemid, row[3]);
		zbx_vector_uint64_create(&tag->itemids);

		DBpatch_propogate_tag(tag, hostid, itemid);

		zbx_vector_tag_ptr_append(&tags, tag);
	}

	DBfree_result(result);

	if (0 == tags.values_num)
		goto out;

	if (1 < tags.values_num)
	{
		for (i = 0; i < tags.values_num; i++)
		{
			tag = tags.values[i];

			for (j = i + 1; j < tags.values_num; j++)
			{
				zbx_db_patch_tag_t	*tag2;

				tag2 = tags.values[j];

				if (0 == strcmp(tag->tag, tag2->tag) && 0 == strcmp(tag->value, tag2->value))
				{
					zbx_vector_uint64_append_array(&tag->itemids, tag2->itemids.values,
							tag2->itemids.values_num);
					tag_free(tag2);
					zbx_vector_tag_ptr_remove_noorder(&tags, j);
				}
			}

			zbx_vector_uint64_sort(&tag->itemids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
			zbx_vector_uint64_uniq(&tag->itemids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
			new_tags += tag->itemids.values_num;
		}
	}

	itemtagid = DBget_maxid_num("item_tag", new_tags);
	zbx_db_insert_prepare(&db_insert_itemtag, "item_tag", "itemtagid", "itemid", "tag", "value",NULL);

	for (i = 0; i < tags.values_num; i++)
	{
		tag = tags.values[i];

		for(j = 0; j < tag->itemids.values_num; j++)
		{
			zbx_uint64_t	itemid;

			itemid = tag->itemids.values[j];
			zbx_db_insert_add_values(&db_insert_itemtag, itemtagid, itemid, tag->tag, tag->value);
			itemtagid++;
		}
	}

	zbx_db_insert_execute(&db_insert_itemtag);
	zbx_db_insert_clean(&db_insert_itemtag);
out:
	zbx_vector_tag_ptr_clear_ext(&tags, tag_free);
	zbx_vector_tag_ptr_destroy(&tags);

	return SUCCEED;
}

typedef struct
{
	int		type;
	char		*name;
	int		value_int;
	char		*value_str;
	zbx_uint64_t	value_groupid;
	zbx_uint64_t	value_hostid;
	zbx_uint64_t	value_itemid;
	zbx_uint64_t	value_graphid;
	zbx_uint64_t	value_sysmapid;
	zbx_uint64_t	value_serviceid;
	zbx_uint64_t	value_slaid;
	zbx_uint64_t	value_userid;
	zbx_uint64_t	value_actionid;
	zbx_uint64_t	value_mediatypeid;
} zbx_db_widget_field_t;

ZBX_PTR_VECTOR_DECL(widget_field_ptr, zbx_db_widget_field_t *)
ZBX_PTR_VECTOR_IMPL(widget_field_ptr, zbx_db_widget_field_t *)

typedef struct
{
	char				*type;
	char				*name;
	int				x;
	int				y;
	int				width;
	int				height;
	int				view_mode;

	zbx_vector_widget_field_ptr_t	fields;
}
zbx_db_widget_t;

ZBX_PTR_VECTOR_DECL(widget_ptr, zbx_db_widget_t *)
ZBX_PTR_VECTOR_IMPL(widget_ptr, zbx_db_widget_t *)

typedef struct
{
	char			*name;
	int			display_period;
	int			sortorder;

	zbx_vector_widget_ptr_t	widgets;
}
zbx_db_dashboard_page_t;

ZBX_PTR_VECTOR_DECL(dashboard_page_ptr, zbx_db_dashboard_page_t *)
ZBX_PTR_VECTOR_IMPL(dashboard_page_ptr, zbx_db_dashboard_page_t *)

typedef struct
{
	uint64_t			child_templateid;
	char				*uuid;
	uint64_t			parent_dashboardid;
	char				*name;
	int				display_period;
	int				auto_start;

	zbx_vector_dashboard_page_ptr_t	pages;
	int				uniq;
}
zbx_db_dashboard_t;

ZBX_PTR_VECTOR_DECL(dashboard_ptr, zbx_db_dashboard_t *)
ZBX_PTR_VECTOR_IMPL(dashboard_ptr, zbx_db_dashboard_t *)

typedef struct
{
	uint64_t				templateid;
	char					*name;
}
zbx_child_dashboard_t;

ZBX_PTR_VECTOR_DECL(child_dashboard_ptr, zbx_child_dashboard_t *)
ZBX_PTR_VECTOR_IMPL(child_dashboard_ptr, zbx_child_dashboard_t *)

static int	DBpatch_propogate_widget(zbx_db_widget_t *widget, uint64_t widgetid,
		zbx_vector_uint64_t *value_itemids, zbx_vector_uint64_t *value_graphids)
{
	DB_RESULT		result;
	DB_ROW			row;

	result = DBselect("select widget_fieldid,type,name,value_int,value_str,value_groupid,value_hostid,value_itemid,"
			"value_graphid,value_sysmapid,value_serviceid,value_slaid,value_userid,value_actionid,"
			"value_mediatypeid from widget_field where widgetid=" ZBX_FS_UI64, widgetid);

	while (NULL != (row = DBfetch(result)))
	{
		zbx_db_widget_field_t	*field;
		zbx_uint64_t		fieldid;

		field = zbx_malloc(NULL, sizeof(zbx_db_widget_field_t));
		ZBX_DBROW2UINT64(fieldid, row[0]);
		field->type = atoi(row[1]);
		field->name = zbx_strdup(NULL, row[2]);
		field->value_int = atoi(row[3]);
		field->value_str = zbx_strdup(NULL, row[4]);
		ZBX_DBROW2UINT64(field->value_groupid, row[5]);
		ZBX_DBROW2UINT64(field->value_hostid, row[6]);

		ZBX_DBROW2UINT64(field->value_itemid, row[7]);
		if (0 != field->value_itemid)
			zbx_vector_uint64_append(value_itemids, field->value_itemid);

		ZBX_DBROW2UINT64(field->value_graphid, row[8]);
		if (0 != field->value_graphid)
			zbx_vector_uint64_append(value_graphids, field->value_graphid);

		ZBX_DBROW2UINT64(field->value_sysmapid, row[9]);
		ZBX_DBROW2UINT64(field->value_serviceid, row[10]);
		ZBX_DBROW2UINT64(field->value_slaid, row[11]);
		ZBX_DBROW2UINT64(field->value_userid, row[12]);
		ZBX_DBROW2UINT64(field->value_actionid, row[13]);
		ZBX_DBROW2UINT64(field->value_mediatypeid, row[14]);

		zbx_vector_widget_field_ptr_append(&widget->fields, field);
	}

	DBfree_result(result);

	return widget->fields.values_num;
}

static int	DBpatch_propogate_page(zbx_db_dashboard_page_t *page, uint64_t pageid, int *fields_num,
		zbx_vector_uint64_t *value_itemids, zbx_vector_uint64_t *value_graphids)
{
	DB_RESULT		result;
	DB_ROW			row;

	result = DBselect("select widgetid,type,name,x,y,width,height,view_mode from widget"
			" where dashboard_pageid=" ZBX_FS_UI64, pageid);

	while (NULL != (row = DBfetch(result)))
	{
		zbx_db_widget_t	*widget;
		zbx_uint64_t	widgetid;

		widget = zbx_malloc(NULL, sizeof(zbx_db_widget_t));
		ZBX_DBROW2UINT64(widgetid, row[0]);
		widget->type = zbx_strdup(NULL, row[1]);
		widget->name = zbx_strdup(NULL, row[2]);
		widget->x = atoi(row[3]);
		widget->y = atoi(row[4]);
		widget->width = atoi(row[5]);
		widget->height = atoi(row[6]);
		widget->view_mode = atoi(row[7]);
		zbx_vector_widget_field_ptr_create(&widget->fields);
		*fields_num += DBpatch_propogate_widget(widget, widgetid, value_itemids, value_graphids);
		zbx_vector_widget_ptr_append(&page->widgets, widget);
	}

	DBfree_result(result);

	return page->widgets.values_num;
}

static int	DBpatch_propogate_dashboard(zbx_db_dashboard_t *dashboard, uint64_t dashboardid, int *widgets_num,
		int *fields_num, zbx_vector_uint64_t *value_itemids, zbx_vector_uint64_t *value_graphids)
{
	DB_RESULT		result;
	DB_ROW			row;

	result = DBselect("select dashboard_pageid,name,display_period,sortorder from dashboard_page"
			" where dashboardid=" ZBX_FS_UI64, dashboardid);

	while (NULL != (row = DBfetch(result)))
	{
		zbx_db_dashboard_page_t	*page;
		zbx_uint64_t		pageid;

		page = zbx_malloc(NULL, sizeof(zbx_db_dashboard_page_t));
		ZBX_DBROW2UINT64(pageid, row[0]);
		page->name = zbx_strdup(NULL, row[1]);
		page->display_period = atoi(row[2]);
		page->sortorder = atoi(row[3]);
		zbx_vector_widget_ptr_create(&page->widgets);
		*widgets_num += DBpatch_propogate_page(page, pageid, fields_num, value_itemids, value_graphids);
		zbx_vector_dashboard_page_ptr_append(&dashboard->pages, page);
	}

	DBfree_result(result);

	return dashboard->pages.values_num;
}

static void	fields_free(zbx_db_widget_field_t *field)
{
	zbx_free(field->name);
	zbx_free(field->value_str);
	zbx_free(field);
}

static void	widgets_free(zbx_db_widget_t *widget)
{
	zbx_free(widget->type);
	zbx_free(widget->name);
	zbx_vector_widget_field_ptr_clear_ext(&widget->fields, fields_free);
	zbx_vector_widget_field_ptr_destroy(&widget->fields);
	zbx_free(widget);
}

static void	dashboard_page_free(zbx_db_dashboard_page_t *page)
{
	zbx_free(page->name);
	zbx_vector_widget_ptr_clear_ext(&page->widgets, widgets_free);
	zbx_vector_widget_ptr_destroy(&page->widgets);
	zbx_free(page);
}

static void	dashboard_clear(zbx_db_dashboard_t *dashboard)
{
	zbx_free(dashboard->uuid);
	zbx_free(dashboard->name);
	zbx_vector_dashboard_page_ptr_clear_ext(&dashboard->pages, dashboard_page_free);
	zbx_vector_dashboard_page_ptr_destroy(&dashboard->pages);
}

static void	dashboard_free(zbx_db_dashboard_t *dashboard)
{
	dashboard_clear(dashboard);
	zbx_free(dashboard);
}

static void	child_dashboard_free(zbx_child_dashboard_t *dashboard)
{
	zbx_free(dashboard->name);
	zbx_free(dashboard);
}

static void	change_item_ids(zbx_db_dashboard_t *dashboard, zbx_vector_uint64_t *item_ids)
{
	zbx_vector_uint64_pair_t	itemid_pairs;
	char				*sql = NULL;
	size_t				sql_alloc = 0, sql_offset = 0;
	DB_RESULT			result;
	DB_ROW				row;
	zbx_uint64_pair_t		pair;
	int				i, j, k;

	if (0 == item_ids->values_num)
		return;

	zbx_vector_uint64_pair_create(&itemid_pairs);

	zbx_vector_uint64_sort(item_ids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_uniq(item_ids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "select i.templateid,i.itemid from items i,hosts h where"
			" i.hostid=h.hostid and h.hostid=" ZBX_FS_UI64" and", dashboard->child_templateid);
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "i.templateid", item_ids->values, item_ids->values_num);
	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_DBROW2UINT64(pair.first, row[0]);
		ZBX_DBROW2UINT64(pair.second, row[1]);

		zbx_vector_uint64_pair_append(&itemid_pairs, pair);
	}

	DBfree_result(result);
	zbx_free(sql);

	for (i = 0; i < dashboard->pages.values_num; i++)
	{
		zbx_db_dashboard_page_t	*page;

		page = dashboard->pages.values[i];

		for (j = 0; j < page->widgets.values_num; j++)
		{
			zbx_db_widget_t	*widget;

			widget = page->widgets.values[j];

			for (k = 0; k < widget->fields.values_num; k++)
			{
				zbx_db_widget_field_t *field;

				field = widget->fields.values[k];

				if (0 != field->value_itemid)
				{
					int	index;

					pair.first = field->value_itemid;

					if (FAIL != (index = zbx_vector_uint64_pair_search(&itemid_pairs, pair,
							ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
					{
						field->value_itemid = itemid_pairs.values[index].second;
					}
				}
			}
		}
	}

	zbx_vector_uint64_pair_destroy(&itemid_pairs);
}

static void	change_graph_ids(zbx_db_dashboard_t *dashboard, zbx_vector_uint64_t *graph_ids)
{
	zbx_vector_uint64_pair_t	graphid_pairs;
	char				*sql = NULL;
	size_t				sql_alloc = 0, sql_offset = 0;
	DB_RESULT			result;
	DB_ROW				row;
	zbx_uint64_pair_t		pair;
	int				i, j, k;

	if (0 == graph_ids->values_num)
		return;

	zbx_vector_uint64_pair_create(&graphid_pairs);

	zbx_vector_uint64_sort(graph_ids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_uniq(graph_ids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);


	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select distinct g.templateid,g.graphid from graphs g,graphs_items gi,items i"
			" where gi.graphid=g.graphid and i.itemid=gi.itemid and i.hostid=" ZBX_FS_UI64" and",
			dashboard->child_templateid);
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "g.templateid", graph_ids->values, graph_ids->values_num);
	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_DBROW2UINT64(pair.first, row[0]);
		ZBX_DBROW2UINT64(pair.second, row[1]);

		zbx_vector_uint64_pair_append(&graphid_pairs, pair);
	}

	DBfree_result(result);
	zbx_free(sql);

	for (i = 0; i < dashboard->pages.values_num; i++)
	{
		zbx_db_dashboard_page_t	*page;

		page = dashboard->pages.values[i];

		for (j = 0; j < page->widgets.values_num; j++)
		{
			zbx_db_widget_t	*widget;

			widget = page->widgets.values[j];

			for (k = 0; k < widget->fields.values_num; k++)
			{
				zbx_db_widget_field_t *field;

				field = widget->fields.values[k];

				if (0 != field->value_graphid)
				{
					int	index;

					pair.first = field->value_graphid;

					if (FAIL != (index = zbx_vector_uint64_pair_search(&graphid_pairs, pair,
							ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
					{
						field->value_graphid = graphid_pairs.values[index].second;
					}
				}
			}
		}
	}

	zbx_vector_uint64_pair_destroy(&graphid_pairs);
}

static int	dashboard_compare(const zbx_db_dashboard_t **pd1, const zbx_db_dashboard_t **pd2)
{
	const zbx_db_dashboard_t	*d1, *d2;

	d1 = *pd1;
	d2 = *pd2;

	if (d1->child_templateid == d2->child_templateid && d1->parent_dashboardid == d2->parent_dashboardid)
		return 0;

	return 1;
}

static void	zbx_vector_dashboard_ptr_uniq2(zbx_vector_dashboard_ptr_t *vector, zbx_compare_func_t compare_func)
{
	if (2 <= vector->values_num)
	{
		int	i, j;

		for (i = 0; i < vector->values_num; i++)
		{
			j = i + 1;

			while (j < vector->values_num)
			{
				if (0 == compare_func(&vector->values[i], &vector->values[j]))
				{
					dashboard_free(vector->values[j]);
					zbx_vector_dashboard_ptr_remove(vector, j);
				}
				else
					j++;
			}
		}
	}
}

static void	collect_dashboards(zbx_vector_uint64_t *parent_ids, zbx_vector_uint64_t *child_templateids,
		zbx_vector_dashboard_ptr_t *dashboards, int *pages_num, int *widgets_num, int *fields_num)
{
	DB_RESULT		result;
	DB_ROW			row;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_vector_uint64_t	loc_child_templateids, value_itemids, value_graphids;

	if (0 == parent_ids->values_num)
		return;

	zbx_vector_uint64_create(&loc_child_templateids);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select d.dashboardid,d.name,d.display_period,d.auto_start,ht.hostid,h.host,ht.templateid"
			" from hosts_templates ht"
			" join hosts h on h.hostid=ht.hostid and h.status=%d"
			" left join dashboard d on d.templateid=ht.templateid where", HOST_STATUS_TEMPLATE);
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "ht.templateid", parent_ids->values,
			parent_ids->values_num);
	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		char			*template_name, *seed = NULL;
		zbx_db_dashboard_t	*dashboard, *dashboard_copy;
		zbx_uint64_t		dashboardid, child_templateid, parent_templateid;
		int			i;

		ZBX_DBROW2UINT64(dashboardid, row[0]);
		ZBX_DBROW2UINT64(child_templateid, row[4]);
		ZBX_DBROW2UINT64(parent_templateid, row[6]);
		template_name = zbx_strdup(NULL, row[5]);
		template_name = zbx_update_template_name(template_name);

		if (0 != dashboardid)
		{
			zbx_vector_uint64_create(&value_itemids);
			zbx_vector_uint64_create(&value_graphids);

			dashboard = zbx_malloc(NULL, sizeof(zbx_db_dashboard_t));
			dashboard->child_templateid = child_templateid;
			dashboard->parent_dashboardid = dashboardid;
			dashboard->name = zbx_strdup(NULL, row[1]);
			dashboard->display_period = atoi(row[2]);
			dashboard->auto_start = atoi(row[3]);

			seed = zbx_dsprintf(seed, "%s/%s", template_name, row[1]);
			dashboard->uuid = zbx_gen_uuid4(seed);
			zbx_free(seed);
			dashboard->uniq = 0;
			zbx_vector_dashboard_page_ptr_create(&dashboard->pages);
			pages_num += DBpatch_propogate_dashboard(dashboard, dashboardid, widgets_num, fields_num,
					&value_itemids, &value_graphids);
			zbx_vector_dashboard_ptr_append(dashboards, dashboard);
			change_item_ids(dashboard, &value_itemids);
			change_graph_ids(dashboard, &value_graphids);

			zbx_vector_uint64_destroy(&value_itemids);
			zbx_vector_uint64_destroy(&value_graphids);
		}

		zbx_vector_uint64_append(&loc_child_templateids, child_templateid);

		for (i = 0; i < dashboards->values_num; i++)
		{
			dashboard = dashboards->values[i];

			if (parent_templateid == dashboard->child_templateid)
			{
				zbx_vector_uint64_create(&value_itemids);
				zbx_vector_uint64_create(&value_graphids);

				dashboard_copy = zbx_malloc(NULL, sizeof(zbx_db_dashboard_t));
				dashboard_copy->child_templateid = child_templateid;
				dashboard_copy->parent_dashboardid = dashboard->parent_dashboardid;
				dashboard_copy->name = zbx_strdup(NULL, dashboard->name);
				dashboard_copy->display_period = dashboard->display_period;
				dashboard_copy->auto_start= dashboard->auto_start;
				seed = zbx_dsprintf(seed, "%s/%s", template_name, dashboard->name);
				dashboard_copy->uuid = zbx_gen_uuid4(seed);
				zbx_free(seed);
				dashboard_copy->uniq = 0;
				zbx_vector_dashboard_page_ptr_create(&dashboard_copy->pages);
				pages_num += DBpatch_propogate_dashboard(dashboard_copy, dashboard->parent_dashboardid,
						widgets_num, fields_num, &value_itemids, &value_graphids);
				zbx_vector_dashboard_ptr_append(dashboards, dashboard_copy);

				change_item_ids(dashboard_copy, &value_itemids);
				change_graph_ids(dashboard_copy, &value_graphids);

				zbx_vector_uint64_destroy(&value_itemids);
				zbx_vector_uint64_destroy(&value_graphids);
			}
		}

		zbx_free(template_name);
	}

	DBfree_result(result);
	zbx_free(sql);

	collect_dashboards(&loc_child_templateids, child_templateids, dashboards, pages_num, widgets_num, fields_num);

	zbx_vector_uint64_append_array(child_templateids, loc_child_templateids.values,
			loc_child_templateids.values_num);
	zbx_vector_uint64_destroy(&loc_child_templateids);
}

static int	DBpatch_6030162(void)
{
	zbx_vector_dashboard_ptr_t		dashboards;
	zbx_vector_child_dashboard_ptr_t	child_dashboards;
	zbx_vector_uint64_t			parent_ids, child_templateids;
	DB_RESULT				result;
	DB_ROW					row;
	int					changed, i, j, k, l, pages_num = 0, widgets_num = 0, fields_num = 0;
	char					*sql = NULL;
	size_t					sql_alloc = 0, sql_offset = 0;
	zbx_db_insert_t				db_insert_dashboard, db_insert_dashboard_page, db_insert_widget,
						db_insert_widget_field;
	zbx_uint64_t				dashboardid, dashboard_pageid, widgetid, widget_fieldid;

	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	zbx_vector_uint64_create(&parent_ids);
	select_pure_parents(&parent_ids);

	if (0 == parent_ids.values_num)
		goto out;

	zbx_vector_dashboard_ptr_create(&dashboards);
	zbx_vector_child_dashboard_ptr_create(&child_dashboards);
	zbx_vector_uint64_create(&child_templateids);

	collect_dashboards(&parent_ids, &child_templateids, &dashboards, &pages_num, &widgets_num, &fields_num);

	if (0 == dashboards.values_num)
		goto clean;

	zbx_vector_uint64_sort(&child_templateids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_uniq(&child_templateids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select name,templateid from dashboard where");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "templateid", child_templateids.values,
			child_templateids.values_num);
	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		zbx_child_dashboard_t	*dashboard;
		uint64_t		templateid;

		ZBX_DBROW2UINT64(templateid, row[1]);

		dashboard = zbx_malloc(NULL, sizeof(zbx_child_dashboard_t));
		dashboard->templateid = templateid;
		dashboard->name = zbx_strdup(NULL, row[0]);

		zbx_vector_child_dashboard_ptr_append(&child_dashboards, dashboard);
	}

	DBfree_result(result);
	zbx_free(sql);

	zbx_vector_dashboard_ptr_uniq2(&dashboards, (zbx_compare_func_t)dashboard_compare);

	do
	{
		zbx_db_dashboard_t	*dashboard, *dashboard2;

		changed = 0;

		for (i = 0; i < child_dashboards.values_num; i++)
		{
			zbx_child_dashboard_t	*child_dashboard;

			child_dashboard = child_dashboards.values[i];

			for (j = 0; j < dashboards.values_num; j++)
			{
				dashboard = dashboards.values[j];

				if (dashboard->child_templateid == child_dashboard->templateid &&
						0 == strcmp(dashboard->name, child_dashboard->name) &&
						0 == dashboard->uniq)
				{
					changed++;
					dashboard->uniq++;
				}
			}
		}

		for (i = 0; i < dashboards.values_num; i++)
		{
			dashboard = dashboards.values[i];

			for (j = i + 1; j < dashboards.values_num; j++)
			{
				dashboard2 = dashboards.values[j];

				if (dashboard->child_templateid == dashboard2->child_templateid &&
						0 == strcmp(dashboard->name, dashboard2->name) &&
						dashboard->uniq == dashboard2->uniq)
				{
					changed++;
					dashboard2->uniq++;
				}
			}
		}

		for (i = 0; i < dashboards.values_num; i++)
		{
			dashboard = dashboards.values[i];

			if (0 != dashboard->uniq)
			{
				dashboard->name = zbx_dsprintf(dashboard->name, "%s %d", dashboard->name,
						dashboard->uniq);
				dashboard->uniq = 0;
			}
		}
	}while (0 != changed);

	dashboardid = DBget_maxid_num("dashboard", dashboards.values_num);
	dashboard_pageid = DBget_maxid_num("dashboard_page", pages_num);
	widgetid = DBget_maxid_num("widget", widgets_num);
	widget_fieldid = DBget_maxid_num("widget_field", fields_num);

	zbx_db_insert_prepare(&db_insert_dashboard, "dashboard", "dashboardid", "templateid", "name",
			"display_period","auto_start", "uuid", NULL);
	zbx_db_insert_prepare(&db_insert_dashboard_page, "dashboard_page", "dashboard_pageid", "dashboardid",
			"name", "display_period", "sortorder", NULL);
	zbx_db_insert_prepare(&db_insert_widget, "widget", "widgetid", "dashboard_pageid", "type", "name", "x",
			"y", "width", "height", "view_mode", NULL);
	zbx_db_insert_prepare(&db_insert_widget_field, "widget_field", "widget_fieldid", "widgetid", "type",
			"name", "value_int", "value_str", "value_groupid", "value_hostid", "value_itemid",
			"value_graphid", "value_sysmapid", "value_serviceid", "value_slaid", "value_userid",
			"value_actionid", "value_mediatypeid",NULL);

	for (i = 0; i < dashboards.values_num; i++)
	{
		zbx_db_dashboard_t	*dashboard;

		dashboard = dashboards.values[i];
		zbx_db_insert_add_values(&db_insert_dashboard, dashboardid, dashboard->child_templateid,
				dashboard->name, dashboard->display_period, dashboard->auto_start,
				dashboard->uuid);

		for (j = 0; j < dashboard->pages.values_num; j++)
		{
			zbx_db_dashboard_page_t	*dashboard_page;

			dashboard_page = dashboard->pages.values[j];
			zbx_db_insert_add_values(&db_insert_dashboard_page, dashboard_pageid, dashboardid,
					dashboard_page->name, dashboard_page->display_period,
					dashboard_page->sortorder);

			for (k = 0; k < dashboard_page->widgets.values_num; k++)
			{
				zbx_db_widget_t	*widget;

				widget = dashboard_page->widgets.values[k];
				zbx_db_insert_add_values(&db_insert_widget, widgetid, dashboard_pageid,
						widget->type, widget->name, widget->x, widget->y, widget->width,
						widget->height, widget->view_mode);

				for (l = 0; l < widget->fields.values_num; l++)
				{
					zbx_db_widget_field_t	*field;

					field = widget->fields.values[l];
					zbx_db_insert_add_values(&db_insert_widget_field, widget_fieldid,
							widgetid, field->type, field->name, field->value_int,
							field->value_str, field->value_groupid,
							field->value_hostid, field->value_itemid,
							field->value_graphid, field->value_sysmapid,
							field->value_serviceid, field->value_slaid,
							field->value_userid, field->value_actionid,
							field->value_mediatypeid);
					widget_fieldid++;
				}

				widgetid++;
			}

			dashboard_pageid++;
		}

		dashboardid++;
	}

	zbx_db_insert_execute(&db_insert_dashboard);
	zbx_db_insert_clean(&db_insert_dashboard);

	zbx_db_insert_execute(&db_insert_dashboard_page);
	zbx_db_insert_clean(&db_insert_dashboard_page);

	zbx_db_insert_execute(&db_insert_widget);
	zbx_db_insert_clean(&db_insert_widget);

	zbx_db_insert_execute(&db_insert_widget_field);
	zbx_db_insert_clean(&db_insert_widget_field);
clean:
	zbx_vector_uint64_destroy(&child_templateids);
	zbx_vector_child_dashboard_ptr_clear_ext(&child_dashboards, child_dashboard_free);
	zbx_vector_child_dashboard_ptr_destroy(&child_dashboards);
	zbx_vector_dashboard_ptr_clear_ext(&dashboards, dashboard_free);
	zbx_vector_dashboard_ptr_destroy(&dashboards);
out:
	zbx_vector_uint64_destroy(&parent_ids);

	return SUCCEED;
}

static int	DBpatch_6030163(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("delete from profiles where idx='web.templates.filter_templates'"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_6030164(void)
{
	zbx_vector_uint64_t	itemids;
	zbx_vector_str_t	uuids;
	DB_RESULT		result;
	DB_ROW			row;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	int			ret = SUCCEED;

	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return ret;

	zbx_vector_uint64_create(&itemids);
	zbx_vector_str_create(&uuids);

	result = DBselect("select i.itemid,i.key_,h.host from items i left join hosts h on h.hostid=i.hostid"
			" where h.status=%d and i.templateid is not null", HOST_STATUS_TEMPLATE);

	while (NULL != (row = DBfetch(result)))
	{
		zbx_uint64_t		itemid;
		char			*name, *seed = NULL;

		ZBX_DBROW2UINT64(itemid, row[0]);
		zbx_vector_uint64_append(&itemids, itemid);

		name = zbx_strdup(NULL, row[2]);
		name = zbx_update_template_name(name);
		seed = zbx_dsprintf(seed, "%s/%s", name, row[1]);
		zbx_vector_str_append(&uuids, zbx_gen_uuid4(seed));
		zbx_free(name);
		zbx_free(seed);
	}

	DBfree_result(result);

	if (0 != itemids.values_num)
	{
		int	i;

		zbx_DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);
		for (i = 0; i < itemids.values_num; i++)
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update items set templateid=null,uuid='%s'"
					" where itemid=" ZBX_FS_UI64 ";\n", uuids.values[i], itemids.values[i]);

			if (SUCCEED != (ret = DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset)))
				goto out;
		}

		zbx_DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

		if (16 < sql_offset && ZBX_DB_OK > DBexecute("%s", sql))
			ret = FAIL;
out:
		zbx_free(sql);
	}

	zbx_vector_str_clear_ext(&uuids, zbx_str_free);
	zbx_vector_str_destroy(&uuids);
	zbx_vector_uint64_destroy(&itemids);

	return ret;
}

static int	DBpatch_6030165(void)
{
	int		ret = SUCCEED;
	char		*sql = NULL;
	size_t		sql_alloc = 0, sql_offset = 0;
	DB_ROW		row;
	DB_RESULT	result;

	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return ret;

	zbx_DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	result = DBselect(
			"select distinct t.triggerid,t.description,t.expression,t.recovery_expression"
			" from triggers t"
			" join functions f on f.triggerid=t.triggerid"
			" join items i on i.itemid=f.itemid"
			" join hosts h on h.hostid=i.hostid and h.status=%d"
			" where t.templateid is not null", HOST_STATUS_TEMPLATE);

	while (NULL != (row = DBfetch(result)))
	{
		char		*trigger_expr, *uuid, *seed = NULL;
		char		*composed_expr[] = { NULL, NULL };
		int		i;
		size_t		seed_alloc = 0, seed_offset = 0;
		DB_ROW		row2;
		DB_RESULT	result2;

		for (i = 0; i < 2; i++)
		{
			int			j;
			char			*error = NULL;
			zbx_eval_context_t	ctx;

			trigger_expr = row[i + 2];

			if ('\0' == *trigger_expr)
			{
				if (0 == i)
				{
					zabbix_log(LOG_LEVEL_WARNING, "%s: empty expression for trigger %s",
							__func__, row[0]);
				}
				continue;
			}

			if (FAIL == zbx_eval_parse_expression(&ctx, trigger_expr, ZBX_EVAL_PARSE_TRIGGER_EXPRESSION,
					&error))
			{
				zabbix_log(LOG_LEVEL_CRIT, "%s: error parsing trigger expression for %s: %s",
						__func__, row[0], error);
				zbx_free(error);
				DBfree_result(result);
				return FAIL;
			}

			for (j = 0; j < ctx.stack.values_num; j++)
			{
				zbx_eval_token_t	*token = &ctx.stack.values[j];
				zbx_uint64_t		functionid;

				if (ZBX_EVAL_TOKEN_FUNCTIONID != token->type)
					continue;

				if (SUCCEED != zbx_is_uint64_n(ctx.expression + token->loc.l + 1,
						token->loc.r - token->loc.l - 1, &functionid))
				{
					zabbix_log(LOG_LEVEL_CRIT, "%s: error parsing trigger expression %s,"
							" zbx_is_uint64_n error", __func__, row[0]);
					DBfree_result(result);
					return FAIL;
				}

				result2 = DBselect(
						"select h.host,i.key_,f.name,f.parameter"
						" from functions f"
						" join items i on i.itemid=f.itemid"
						" join hosts h on h.hostid=i.hostid"
						" where f.functionid=" ZBX_FS_UI64,
						functionid);

				if (NULL != (row2 = DBfetch(result2)))
				{
					char	*func;

					func = zbx_dbpatch_make_trigger_function(row2[2], row2[0], row2[1], row2[3]);
					zbx_variant_clear(&token->value);
					zbx_variant_set_str(&token->value, func);
				}

				DBfree_result(result2);
			}

			zbx_eval_compose_expression(&ctx, &composed_expr[i]);
			zbx_eval_clear(&ctx);
		}

		zbx_snprintf_alloc(&seed, &seed_alloc, &seed_offset, "%s/", row[1]);
		zbx_snprintf_alloc(&seed, &seed_alloc, &seed_offset, "%s", composed_expr[0]);
		if (NULL != composed_expr[1])
			zbx_snprintf_alloc(&seed, &seed_alloc, &seed_offset, "/%s", composed_expr[1]);

		uuid = zbx_gen_uuid4(seed);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update triggers set templateid=null,uuid='%s'"
				" where triggerid=%s;\n", uuid, row[0]);

		zbx_free(composed_expr[0]);
		zbx_free(composed_expr[1]);
		zbx_free(uuid);
		zbx_free(seed);

		if (SUCCEED != (ret = DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset)))
			goto out;
	}

	zbx_DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (16 < sql_offset && ZBX_DB_OK > DBexecute("%s", sql))
		ret = FAIL;
out:
	DBfree_result(result);
	zbx_free(sql);

	return ret;
}

static int	DBpatch_6030166(void)
{
	int		ret = SUCCEED;
	char		*host_name, *uuid, *sql = NULL, *seed = NULL;
	size_t		sql_alloc = 0, sql_offset = 0, seed_alloc = 0, seed_offset = 0;
	DB_ROW		row;
	DB_RESULT	result;

	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return ret;

	zbx_DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);
	result = DBselect(
			"select distinct g.graphid,g.name"
			" from graphs g"
			" join graphs_items gi on gi.graphid=g.graphid"
			" join items i on i.itemid=gi.itemid"
			" join hosts h on h.hostid=i.hostid and h.status=%d"
			" where g.templateid is not null", HOST_STATUS_TEMPLATE);

	while (NULL != (row = DBfetch(result)))
	{
		DB_ROW		row2;
		DB_RESULT	result2;

		zbx_snprintf_alloc(&seed, &seed_alloc, &seed_offset, "%s", row[1]);

		result2 = DBselect(
				"select h.host"
				" from graphs_items gi"
				" join items i on i.itemid=gi.itemid"
				" join hosts h on h.hostid=i.hostid"
				" where gi.graphid=%s"
				" order by h.host",
				row[0]);

		while (NULL != (row2 = DBfetch(result2)))
		{
			host_name = zbx_strdup(NULL, row2[0]);
			host_name = zbx_update_template_name(host_name);

			zbx_snprintf_alloc(&seed, &seed_alloc, &seed_offset, "/%s", host_name);
			zbx_free(host_name);
		}

		uuid = zbx_gen_uuid4(seed);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update graphs set templateid=null,uuid='%s'"
				" where graphid=%s;\n", uuid, row[0]);
		zbx_free(uuid);
		zbx_free(seed);

		DBfree_result(result2);

		if (SUCCEED != (ret = DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset)))
			goto out;
	}

	zbx_DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (16 < sql_offset && ZBX_DB_OK > DBexecute("%s", sql))
		ret = FAIL;
out:
	DBfree_result(result);
	zbx_free(sql);

	return ret;
}

static int	DBpatch_6030167(void)
{
	int		ret = SUCCEED;
	char		*template_name, *uuid, *sql = NULL, *seed = NULL;
	size_t		sql_alloc = 0, sql_offset = 0;
	DB_ROW		row;
	DB_RESULT	result;

	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return ret;

	zbx_DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	result = DBselect(
			"select ht.httptestid,ht.name,h.host"
			" from httptest ht"
			" join hosts h on h.hostid=ht.hostid and h.status=%d"
			" where ht.templateid is not null", HOST_STATUS_TEMPLATE);

	while (NULL != (row = DBfetch(result)))
	{
		template_name = zbx_strdup(NULL, row[2]);
		template_name = zbx_update_template_name(template_name);
		seed = zbx_dsprintf(seed, "%s/%s", template_name, row[1]);
		uuid = zbx_gen_uuid4(seed);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"update httptest set templateid=null,uuid='%s' where httptestid=%s;\n", uuid, row[0]);
		zbx_free(template_name);
		zbx_free(uuid);
		zbx_free(seed);

		if (SUCCEED != (ret = DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset)))
			goto out;
	}

	zbx_DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (16 < sql_offset && ZBX_DB_OK > DBexecute("%s", sql))
		ret = FAIL;
out:
	DBfree_result(result);
	zbx_free(sql);

	return ret;
}

static int	DBpatch_6030168(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("delete from hosts_templates where hostid in (select hostid from hosts"
			" where status=%d)", HOST_STATUS_TEMPLATE))
	{
		return FAIL;
	}

	return SUCCEED;
}
#undef HOST_STATUS_TEMPLATE

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
DBPATCH_ADD(6030074, 0, 1)
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
DBPATCH_ADD(6030159, 0, 1)
DBPATCH_ADD(6030160, 0, 1)
DBPATCH_ADD(6030161, 0, 1)
DBPATCH_ADD(6030162, 0, 1)
DBPATCH_ADD(6030163, 0, 1)
DBPATCH_ADD(6030164, 0, 1)
DBPATCH_ADD(6030165, 0, 1)
DBPATCH_ADD(6030166, 0, 1)
DBPATCH_ADD(6030167, 0, 1)
DBPATCH_ADD(6030168, 0, 1)

DBPATCH_END()
