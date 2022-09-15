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

#include "zbxcommon.h"
#include "zbxdbhigh.h"
#include "dbupgrade.h"

extern unsigned char	program_type;

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

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
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
	return DBcreate_changelog_insert_trigger("drules", "druleid");
}

static int	DBpatch_6030008(void)
{
	return DBcreate_changelog_update_trigger("drules", "druleid");
}

static int	DBpatch_6030009(void)
{
	return DBcreate_changelog_delete_trigger("drules", "druleid");
}

static int	DBpatch_6030010(void)
{
	return DBcreate_changelog_insert_trigger("dchecks", "dcheckid");
}

static int	DBpatch_6030011(void)
{
	return DBcreate_changelog_update_trigger("dchecks", "dcheckid");
}

static int	DBpatch_6030012(void)
{
	return DBcreate_changelog_delete_trigger("dchecks", "dcheckid");
}

static int	DBpatch_6030013(void)
{
	return DBcreate_changelog_insert_trigger("httptest", "httptestid");
}

static int	DBpatch_6030014(void)
{
	return DBcreate_changelog_update_trigger("httptest", "httptestid");
}

static int	DBpatch_6030015(void)
{
	return DBcreate_changelog_delete_trigger("httptest", "httptestid");
}

static int	DBpatch_6030016(void)
{
	return DBcreate_changelog_insert_trigger("httptest_field", "httptest_fieldid");
}

static int	DBpatch_6030017(void)
{
	return DBcreate_changelog_update_trigger("httptest_field", "httptest_fieldid");
}

static int	DBpatch_6030018(void)
{
	return DBcreate_changelog_delete_trigger("httptest_field", "httptest_fieldid");
}

static int	DBpatch_6030019(void)
{
	return DBcreate_changelog_insert_trigger("httptestitem", "httptestitemid");
}

static int	DBpatch_6030020(void)
{
	return DBcreate_changelog_update_trigger("httptestitem", "httptestitemid");
}

static int	DBpatch_6030021(void)
{
	return DBcreate_changelog_delete_trigger("httptestitem", "httptestitemid");
}

static int	DBpatch_6030022(void)
{
	return DBcreate_changelog_insert_trigger("httpstep", "httpstepid");
}

static int	DBpatch_6030023(void)
{
	return DBcreate_changelog_update_trigger("httpstep", "httpstepid");
}

static int	DBpatch_6030024(void)
{
	return DBcreate_changelog_delete_trigger("httpstep", "httpstepid");
}

static int	DBpatch_6030025(void)
{
	return DBcreate_changelog_insert_trigger("httpstep_field", "httpstep_fieldid");
}

static int	DBpatch_6030026(void)
{
	return DBcreate_changelog_update_trigger("httpstep_field", "httpstep_fieldid");
}

static int	DBpatch_6030027(void)
{
	return DBcreate_changelog_delete_trigger("httpstep_field", "httpstep_fieldid");
}

static int	DBpatch_6030028(void)
{
	return DBcreate_changelog_insert_trigger("httpstepitem", "httpstepitemid");
}

static int	DBpatch_6030029(void)
{
	return DBcreate_changelog_update_trigger("httpstepitem", "httpstepitemid");
}

static int	DBpatch_6030030(void)
{
	return DBcreate_changelog_delete_trigger("httpstepitem", "httpstepitemid");
}

static int	DBpatch_6030031(void)
{
	return DBdrop_field("drules", "nextcheck");
}

static int	DBpatch_6030032(void)
{
	return DBdrop_field("httptest", "nextcheck");
}

static int	DBpatch_6030033(void)
{
	const ZBX_FIELD field = {"discovery_groupid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0};

	return DBdrop_not_null("config", &field);
}

static int	DBpatch_6030034(void)
{
	return DBdrop_foreign_key("dchecks", 1);
}

static int	DBpatch_6030035(void)
{
	const ZBX_FIELD	field = {"druleid", NULL, "drules", "druleid", 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_foreign_key("dchecks", 1, &field);
}

static int	DBpatch_6030036(void)
{
	return DBdrop_foreign_key("httptest", 2);
}

static int	DBpatch_6030037(void)
{
	const ZBX_FIELD	field = {"hostid", NULL, "hosts", "hostid", 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_foreign_key("httptest", 2, &field);
}

static int	DBpatch_6030038(void)
{
	return DBdrop_foreign_key("httptest", 3);
}

static int	DBpatch_6030039(void)
{
	const ZBX_FIELD	field = {"templateid", NULL, "httptest", "httptestid", 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_foreign_key("httptest", 3, &field);
}

static int	DBpatch_6030040(void)
{
	return DBdrop_foreign_key("httpstep", 1);
}

static int	DBpatch_6030041(void)
{
	const ZBX_FIELD	field = {"httptestid", NULL, "httptest", "httptestid", 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_foreign_key("httpstep", 1, &field);
}

static int	DBpatch_6030042(void)
{
	return DBdrop_foreign_key("httptestitem", 1);
}

static int	DBpatch_6030043(void)
{
	const ZBX_FIELD	field = {"httptestid", NULL, "httptest", "httptestid", 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_foreign_key("httptestitem", 1, &field);
}

static int	DBpatch_6030044(void)
{
	return DBdrop_foreign_key("httptestitem", 2);
}

static int	DBpatch_6030045(void)
{
	const ZBX_FIELD	field = {"itemid", NULL, "items", "itemid", 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_foreign_key("httptestitem", 2, &field);
}

static int	DBpatch_6030046(void)
{
	return DBdrop_foreign_key("httpstepitem", 1);
}

static int	DBpatch_6030047(void)
{
	const ZBX_FIELD	field = {"httpstepid", NULL, "httpstep", "httpstepid", 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_foreign_key("httpstepitem", 1, &field);
}

static int	DBpatch_6030048(void)
{
	return DBdrop_foreign_key("httpstepitem", 2);
}

static int	DBpatch_6030049(void)
{
	const ZBX_FIELD	field = {"itemid", NULL, "items", "itemid", 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_foreign_key("httpstepitem", 2, &field);
}

static int	DBpatch_6030050(void)
{
	return DBdrop_foreign_key("httptest_field", 1);
}

static int	DBpatch_6030051(void)
{
	const ZBX_FIELD	field = {"httptestid", NULL, "httptest", "httptestid", 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_foreign_key("httptest_field", 1, &field);
}

static int	DBpatch_6030052(void)
{
	return DBdrop_foreign_key("httpstep_field", 1);
}

static int	DBpatch_6030053(void)
{
	const ZBX_FIELD	field = {"httpstepid", NULL, "httpstep", "httpstepid", 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_foreign_key("httpstep_field", 1, &field);
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

DBPATCH_END()
