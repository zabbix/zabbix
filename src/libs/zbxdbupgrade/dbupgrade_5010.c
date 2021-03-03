/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
#include "log.h"
#include "screens_converter.h"

/*
 * 5.2 development database patches
 */

#ifndef HAVE_SQLITE3

extern unsigned char	program_type;

static int	DBpatch_5010000(void)
{
	const ZBX_FIELD	field = {"default_lang", "en_GB", NULL, NULL, 5, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_5010001(void)
{
	const ZBX_FIELD	field = {"lang", "default", NULL, NULL, 7, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("users", &field, NULL);
}

static int	DBpatch_5010002(void)
{
	if (ZBX_DB_OK > DBexecute("update users set lang='default',theme='default' where alias='guest'"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_5010003(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("delete from profiles where idx in ('web.latest.toggle','web.latest.toggle_other')"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_5010004(void)
{
	DB_ROW		row;
	DB_RESULT	result;
	int		ret = SUCCEED;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	result = DBselect("select userid from profiles where idx='web.latest.sort' and value_str='lastclock'");

	while (NULL != (row = DBfetch(result)))
	{
		if (ZBX_DB_OK > DBexecute(
			"delete from profiles"
			" where userid='%s'"
				" and idx in ('web.latest.sort','web.latest.sortorder')", row[0]))
		{
			ret = FAIL;
			break;
		}
	}
	DBfree_result(result);

	return ret;
}

static int	DBpatch_5010005(void)
{
	const ZBX_FIELD	field = {"default_timezone", "system", NULL, NULL, 50, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_5010006(void)
{
	const ZBX_FIELD	field = {"timezone", "default", NULL, NULL, 50, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("users", &field);
}

static int	DBpatch_5010007(void)
{
	const ZBX_FIELD	field = {"login_attempts", "5", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_5010008(void)
{
	const ZBX_FIELD	field = {"login_block", "30s", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_5010009(void)
{
	const ZBX_FIELD	field = {"show_technical_errors", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_5010010(void)
{
	const ZBX_FIELD	field = {"validate_uri_schemes", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_5010011(void)
{
	const ZBX_FIELD	field = {"uri_valid_schemes", "http,https,ftp,file,mailto,tel,ssh", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_5010012(void)
{
	const ZBX_FIELD	field = {"x_frame_options", "SAMEORIGIN", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_5010013(void)
{
	const ZBX_FIELD	field = {"iframe_sandboxing_enabled", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_5010014(void)
{
	const ZBX_FIELD	field = {"iframe_sandboxing_exceptions", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_5010015(void)
{
	const ZBX_FIELD	field = {"max_overview_table_size", "50", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_5010016(void)
{
	const ZBX_FIELD	field = {"history_period", "24h", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_5010017(void)
{
	const ZBX_FIELD	field = {"period_default", "1h", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_5010018(void)
{
	const ZBX_FIELD	field = {"max_period", "2y", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_5010019(void)
{
	const ZBX_FIELD	field = {"socket_timeout", "3s", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_5010020(void)
{
	const ZBX_FIELD	field = {"connect_timeout", "3s", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_5010021(void)
{
	const ZBX_FIELD	field = {"media_type_test_timeout", "65s", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_5010022(void)
{
	const ZBX_FIELD	field = {"script_timeout", "60s", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_5010023(void)
{
	const ZBX_FIELD	field = {"item_test_timeout", "60s", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_5010024(void)
{
	const ZBX_FIELD	field = {"session_key", "", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_5010025(void)
{
	const ZBX_FIELD field = {"value", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("hostmacro", &field, NULL);
}

static int	DBpatch_5010026(void)
{
	const ZBX_FIELD field = {"value", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("globalmacro", &field, NULL);
}

static int	DBpatch_5010027(void)
{
	const ZBX_FIELD	old_field = {"data", "", NULL, NULL, 0, ZBX_TYPE_SHORTTEXT, ZBX_NOTNULL, 0};
	const ZBX_FIELD	field = {"data", "", NULL, NULL, 0, ZBX_TYPE_TEXT, ZBX_NOTNULL, 0};

	return DBmodify_field_type("task_data", &field, &old_field);
}

static int	DBpatch_5010028(void)
{
	const ZBX_FIELD	old_field = {"info", "", NULL, NULL, 0, ZBX_TYPE_SHORTTEXT, ZBX_NOTNULL, 0};
	const ZBX_FIELD	field = {"info", "", NULL, NULL, 0, ZBX_TYPE_TEXT, ZBX_NOTNULL, 0};

	return DBmodify_field_type("task_result", &field, &old_field);
}

static int	DBpatch_5010029(void)
{
	const ZBX_FIELD	old_field = {"params", "", NULL, NULL, 0, ZBX_TYPE_SHORTTEXT, ZBX_NOTNULL, 0};
	const ZBX_FIELD	field = {"params", "", NULL, NULL, 0, ZBX_TYPE_TEXT, ZBX_NOTNULL, 0};

	return DBmodify_field_type("items", &field, &old_field);
}

static int	DBpatch_5010030(void)
{
	const ZBX_FIELD	old_field = {"description", "", NULL, NULL, 0, ZBX_TYPE_SHORTTEXT, ZBX_NOTNULL, 0};
	const ZBX_FIELD	field = {"description", "", NULL, NULL, 0, ZBX_TYPE_TEXT, ZBX_NOTNULL, 0};

	return DBmodify_field_type("items", &field, &old_field);
}

static int	DBpatch_5010031(void)
{
	const ZBX_FIELD	old_field = {"posts", "", NULL, NULL, 0, ZBX_TYPE_SHORTTEXT, ZBX_NOTNULL, 0};
	const ZBX_FIELD	field = {"posts", "", NULL, NULL, 0, ZBX_TYPE_TEXT, ZBX_NOTNULL, 0};

	return DBmodify_field_type("items", &field, &old_field);
}

static int	DBpatch_5010032(void)
{
	const ZBX_FIELD	old_field = {"headers", "", NULL, NULL, 0, ZBX_TYPE_SHORTTEXT, ZBX_NOTNULL, 0};
	const ZBX_FIELD	field = {"headers", "", NULL, NULL, 0, ZBX_TYPE_TEXT, ZBX_NOTNULL, 0};

	return DBmodify_field_type("items", &field, &old_field);
}

static int	DBpatch_5010033(void)
{
	const ZBX_FIELD	field = {"custom_interfaces", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("hosts", &field);
}

static int	DBpatch_5010034(void)
{
	const ZBX_FIELD	old_field = {"value_str", "", NULL, NULL, 255, ZBX_TYPE_SHORTTEXT, ZBX_NOTNULL, 0};
	const ZBX_FIELD	field = {"value_str", "", NULL, NULL, 0, ZBX_TYPE_TEXT, ZBX_NOTNULL, 0};

	return DBmodify_field_type("profiles", &field, &old_field);
}

static int	DBpatch_5010035(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("delete from profiles where idx like 'web.hostsmon.filter.%%' or idx like 'web.problem.filter%%'"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_5010036(void)
{
	const ZBX_FIELD	field = {"event_name", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("triggers", &field);
}

static int	DBpatch_5010037(void)
{
	const ZBX_TABLE	table =
			{"trigger_queue", "", 0,
				{
					{"objectid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"type", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"clock", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"ns", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_5010038(void)
{
	const ZBX_FIELD field = {"templateid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_field("dashboard", &field);
}

static int	DBpatch_5010039(void)
{
	const ZBX_FIELD field = {"userid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0};

	return DBdrop_not_null("dashboard", &field);
}

static int	DBpatch_5010040(void)
{
	return DBcreate_index("dashboard", "dashboard_1", "userid", 0);
}

static int	DBpatch_5010041(void)
{
#ifdef HAVE_MYSQL	/* MySQL automatically creates index and might not remove it on some conditions */
	if (SUCCEED == DBindex_exists("dashboard", "c_dashboard_1"))
		return DBdrop_index("dashboard", "c_dashboard_1");
#endif
	return SUCCEED;
}

static int	DBpatch_5010042(void)
{
	return DBcreate_index("dashboard", "dashboard_2", "templateid", 0);
}

static int	DBpatch_5010043(void)
{
	const ZBX_FIELD	field = {"templateid", 0, "hosts", "hostid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("dashboard", 2, &field);
}

static int	DBpatch_5010044(void)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		ret = SUCCEED;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return ret;

	result = DBselect("select screenid,name,templateid from screens where templateid is not null");

	while (SUCCEED == ret && NULL != (row = DBfetch(result)))
	{
		uint64_t	screenid, templateid;

		ZBX_DBROW2UINT64(screenid, row[0]);
		ZBX_DBROW2UINT64(templateid, row[2]);

		if (SUCCEED == (ret = DBpatch_convert_screen(screenid, row[1], templateid, 0, 0)))
			ret = DBpatch_delete_screen(screenid);
	}

	DBfree_result(result);

	return ret;
}

static int	DBpatch_5010045(void)
{
	return DBdrop_foreign_key("screens", 1);
}

static int	DBpatch_5010046(void)
{
	return DBdrop_field("screens", "templateid");
}

static int	DBpatch_5010047(void)
{
	return DBcreate_index("screens", "screens_1", "userid", 0);
}

static int	DBpatch_5010048(void)
{
#ifdef HAVE_MYSQL	/* fix automatic index name on MySQL */
	if (SUCCEED == DBindex_exists("screens", "c_screens_3"))
	{
		return DBdrop_index("screens", "c_screens_3");
	}
#endif
	return SUCCEED;
}

static int	DBpatch_5010049(void)
{
	const ZBX_TABLE	table =
			{"item_parameter", "item_parameterid", 0,
				{
					{"item_parameterid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"itemid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"name", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"value", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_5010050(void)
{
	return DBcreate_index("item_parameter", "item_parameter_1", "itemid", 0);
}

static int	DBpatch_5010051(void)
{
	const ZBX_FIELD	field = {"itemid", NULL, "items", "itemid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("item_parameter", 1, &field);
}

static int	DBpatch_5010052(void)
{
	return DBdrop_field("config", "refresh_unsupported");
}

static int      DBpatch_5010053(void)
{
	const ZBX_TABLE table =
		{"role", "roleid", 0,
			{
				{"roleid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
				{"name", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{"type", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
				{"readonly", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
				{0}
			},
			NULL
		};

	return DBcreate_table(&table);
}

static int	DBpatch_5010054(void)
{
	return DBcreate_index("role", "role_1", "name", 1);
}

static int	DBpatch_5010055(void)
{
	const ZBX_TABLE table =
		{"role_rule", "role_ruleid", 0,
			{
				{"role_ruleid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
				{"roleid", NULL, "role", "roleid", 0, ZBX_TYPE_ID, ZBX_NOTNULL, ZBX_FK_CASCADE_DELETE},
				{"type", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
				{"name", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{"value_int", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
				{"value_str", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{"value_moduleid", NULL, "module", "moduleid", 0, ZBX_TYPE_ID, 0, 0},
				{0}
			},
			NULL
		};

	return DBcreate_table(&table);
}

static int	DBpatch_5010056(void)
{
	return DBcreate_index("role_rule", "role_rule_1", "roleid", 0);
}

static int	DBpatch_5010057(void)
{
	return DBcreate_index("role_rule", "role_rule_2", "value_moduleid", 0);
}

static int	DBpatch_5010058(void)
{
	int		i;
	const char	*columns = "roleid,name,type,readonly";
	const char	*values[] = {
			"1,'User role',1,0",
			"2,'Admin role',2,0",
			"3,'Super admin role',3,1",
			"4,'Guest role',1,0",
			NULL
		};

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	for (i = 0; NULL != values[i]; i++)
	{
		if (ZBX_DB_OK > DBexecute("insert into role (%s) values (%s)", columns, values[i]))
			return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_5010059(void)
{
	int		i;
	const char	*columns = "role_ruleid,roleid,type,name,value_int,value_str,value_moduleid";
	const char	*values[] = {
			"1,1,0,'ui.default_access',1,'',NULL",
			"2,1,0,'modules.default_access',1,'',NULL",
			"3,1,0,'api.access',1,'',NULL",
			"4,1,0,'actions.default_access',1,'',NULL",
			"5,2,0,'ui.default_access',1,'',NULL",
			"6,2,0,'modules.default_access',1,'',NULL",
			"7,2,0,'api.access',1,'',NULL",
			"8,2,0,'actions.default_access',1,'',NULL",
			"9,3,0,'ui.default_access',1,'',NULL",
			"10,3,0,'modules.default_access',1,'',NULL",
			"11,3,0,'api.access',1,'',NULL",
			"12,3,0,'actions.default_access',1,'',NULL",
			"13,4,0,'ui.default_access',1,'',NULL",
			"14,4,0,'modules.default_access',1,'',NULL",
			"15,4,0,'api.access',0,'',NULL",
			"16,4,0,'actions.default_access',0,'',NULL",
			NULL
		};

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	for (i = 0; NULL != values[i]; i++)
	{
		if (ZBX_DB_OK > DBexecute("insert into role_rule (%s) values (%s)", columns, values[i]))
			return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_5010060(void)
{
	const ZBX_FIELD field = {"roleid", NULL, "role", "roleid", 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_field("users", &field);
}

static int	DBpatch_5010061(void)
{
	int	i;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	for (i = 1; i <= 3; i++)
	{
		if (ZBX_DB_OK > DBexecute("update users set roleid=%d where type=%d", i, i))
			return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_5010062(void)
{
	const ZBX_FIELD field = {"roleid", NULL, "role", "roleid", 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0};

	return DBset_not_null("users", &field);
}

static int	DBpatch_5010063(void)
{
	return DBdrop_field("users", "type");
}

static int	DBpatch_5010064(void)
{
	const ZBX_FIELD field = {"roleid", NULL, "role", "roleid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("users", 1, &field);
}

static int	DBpatch_5010065(void)
{
	const ZBX_FIELD field = {"roleid", NULL, "role", "roleid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("role_rule", 1, &field);
}

static int	DBpatch_5010066(void)
{
	const ZBX_FIELD field = {"value_moduleid", NULL, "module", "moduleid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("role_rule", 2, &field);
}

static int	DBpatch_5010067(void)
{
	int	i;

	/* 1 - USER TYPE / USER ROLE */
	/* 2 - ADMIN TYPE / ADMIN ROLE */
	/* 3 - SUPER ADMIN TYPE / SUPER ADMIN ROLE */
	const char	*values[] = {
			"1",
			"2",
			"3",
			NULL
		};

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	for (i = 0; NULL != values[i]; i++)
	{
		if (ZBX_DB_OK > DBexecute("update profiles set value_id=%s,type=1,value_int=0 "
				"where idx='web.user.filter_type' and value_int=%s", values[i], values[i]))
		{
			return FAIL;
		}
	}

	/* -1 - ANY PROFILE */
	if (ZBX_DB_OK > DBexecute("delete from profiles where idx='web.user.filter_type' and value_int=-1"))
		return FAIL;

	return SUCCEED;
}
#endif

DBPATCH_START(5010)

/* version, duplicates flag, mandatory flag */

DBPATCH_ADD(5010000, 0, 1)
DBPATCH_ADD(5010001, 0, 1)
DBPATCH_ADD(5010002, 0, 1)
DBPATCH_ADD(5010003, 0, 1)
DBPATCH_ADD(5010004, 0, 1)
DBPATCH_ADD(5010005, 0, 1)
DBPATCH_ADD(5010006, 0, 1)
DBPATCH_ADD(5010007, 0, 1)
DBPATCH_ADD(5010008, 0, 1)
DBPATCH_ADD(5010009, 0, 1)
DBPATCH_ADD(5010010, 0, 1)
DBPATCH_ADD(5010011, 0, 1)
DBPATCH_ADD(5010012, 0, 1)
DBPATCH_ADD(5010013, 0, 1)
DBPATCH_ADD(5010014, 0, 1)
DBPATCH_ADD(5010015, 0, 1)
DBPATCH_ADD(5010016, 0, 1)
DBPATCH_ADD(5010017, 0, 1)
DBPATCH_ADD(5010018, 0, 1)
DBPATCH_ADD(5010019, 0, 1)
DBPATCH_ADD(5010020, 0, 1)
DBPATCH_ADD(5010021, 0, 1)
DBPATCH_ADD(5010022, 0, 1)
DBPATCH_ADD(5010023, 0, 1)
DBPATCH_ADD(5010024, 0, 1)
DBPATCH_ADD(5010025, 0, 1)
DBPATCH_ADD(5010026, 0, 1)
DBPATCH_ADD(5010027, 0, 1)
DBPATCH_ADD(5010028, 0, 1)
DBPATCH_ADD(5010029, 0, 1)
DBPATCH_ADD(5010030, 0, 1)
DBPATCH_ADD(5010031, 0, 1)
DBPATCH_ADD(5010032, 0, 1)
DBPATCH_ADD(5010033, 0, 1)
DBPATCH_ADD(5010034, 0, 1)
DBPATCH_ADD(5010035, 0, 1)
DBPATCH_ADD(5010036, 0, 1)
DBPATCH_ADD(5010037, 0, 1)
DBPATCH_ADD(5010038, 0, 1)
DBPATCH_ADD(5010039, 0, 1)
DBPATCH_ADD(5010040, 0, 1)
DBPATCH_ADD(5010041, 0, 1)
DBPATCH_ADD(5010042, 0, 1)
DBPATCH_ADD(5010043, 0, 1)
DBPATCH_ADD(5010044, 0, 1)
DBPATCH_ADD(5010045, 0, 1)
DBPATCH_ADD(5010046, 0, 1)
DBPATCH_ADD(5010047, 0, 1)
DBPATCH_ADD(5010048, 0, 1)
DBPATCH_ADD(5010049, 0, 1)
DBPATCH_ADD(5010050, 0, 1)
DBPATCH_ADD(5010051, 0, 1)
DBPATCH_ADD(5010052, 0, 1)
DBPATCH_ADD(5010053, 0, 1)
DBPATCH_ADD(5010054, 0, 1)
DBPATCH_ADD(5010055, 0, 1)
DBPATCH_ADD(5010056, 0, 1)
DBPATCH_ADD(5010057, 0, 1)
DBPATCH_ADD(5010058, 0, 1)
DBPATCH_ADD(5010059, 0, 1)
DBPATCH_ADD(5010060, 0, 1)
DBPATCH_ADD(5010061, 0, 1)
DBPATCH_ADD(5010062, 0, 1)
DBPATCH_ADD(5010063, 0, 1)
DBPATCH_ADD(5010064, 0, 1)
DBPATCH_ADD(5010065, 0, 1)
DBPATCH_ADD(5010066, 0, 1)
DBPATCH_ADD(5010067, 0, 1)

DBPATCH_END()
