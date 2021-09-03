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

extern unsigned char	program_type;

/*
 * 6.0 development database patches
 */

#ifndef HAVE_SQLITE3

static int	DBpatch_5050001(void)
{
	const ZBX_TABLE	table =
			{"service_problem_tag", "service_problem_tagid", 0,
				{
					{"service_problem_tagid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"serviceid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"tag", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"operator", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"value", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_5050002(void)
{
	const ZBX_FIELD	field = {"serviceid", NULL, "services", "serviceid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("service_problem_tag", 1, &field);
}

static int	DBpatch_5050003(void)
{
	return DBcreate_index("service_problem_tag", "service_problem_tag_1", "serviceid", 0);
}
static int	DBpatch_5050004(void)
{
	const ZBX_TABLE	table =
			{"service_problem", "service_problemid", 0,
				{
					{"service_problemid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"eventid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"serviceid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"severity", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_5050005(void)
{
	return DBcreate_index("service_problem", "service_problem_1", "eventid", 0);
}

static int	DBpatch_5050006(void)
{
	return DBcreate_index("service_problem", "service_problem_2", "serviceid", 0);
}

static int	DBpatch_5050007(void)
{
	const ZBX_FIELD	field = {"eventid", NULL, "problem", "eventid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("service_problem", 1, &field);
}

static int	DBpatch_5050008(void)
{
	const ZBX_FIELD	field = {"serviceid", NULL, "services", "serviceid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("service_problem", 2, &field);
}

#define ZBX_TAGVALUE_MAX_LEN	32

static void	DBpatch_trim_tag_value(char *value)
{
	size_t	len;

	len = zbx_strlen_utf8_nchars(value, ZBX_TAGVALUE_MAX_LEN - ZBX_CONST_STRLEN("..."));

	memcpy(value + len, "...", ZBX_CONST_STRLEN("...") + 1);
}

static void	DBpatch_get_problems_by_triggerid(zbx_uint64_t triggerid, zbx_vector_uint64_t *eventids)
{
	DB_RESULT	result;
	DB_ROW		row;

	result = DBselect("select eventid from problem where source=0 and object=0 and objectid="
			ZBX_FS_UI64, triggerid);

	while (NULL != (row = DBfetch(result)))
	{
		zbx_uint64_t	eventid;

		ZBX_STR2UINT64(eventid, row[0]);
		zbx_vector_uint64_append(eventids, eventid);
	}

	DBfree_result(result);
}

static int	DBpatch_5050009(void)
{
	DB_RESULT	result;
	DB_ROW		row;
	zbx_db_insert_t	ins_service_problem_tag, ins_trigger_tag, ins_problem_tag;
	zbx_uint64_t	old_triggerid = 0, triggerid, serviceid;
	int		ret = SUCCEED;

	result = DBselect("select t.triggerid,t.description,s.serviceid from triggers t join services s "
			"on t.triggerid=s.triggerid order by t.triggerid");

	zbx_db_insert_prepare(&ins_service_problem_tag, "service_problem_tag", "service_problem_tagid", "serviceid",
			"tag", "operator", "value", NULL);
	zbx_db_insert_prepare(&ins_trigger_tag, "trigger_tag", "triggertagid", "triggerid", "tag", "value", NULL);
	zbx_db_insert_prepare(&ins_problem_tag, "problem_tag", "problemtagid", "eventid", "tag", "value", NULL);

	while (NULL != (row = DBfetch(result)))
	{
		int	i;
		char	*desc, *tag_value = NULL;

		ZBX_STR2UINT64(triggerid, row[0]);
		desc = row[1];
		ZBX_STR2UINT64(serviceid, row[2]);

		tag_value = zbx_dsprintf(NULL, "%s:%s", row[0], desc);

		if (ZBX_TAGVALUE_MAX_LEN < zbx_strlen_utf8(tag_value))
			DBpatch_trim_tag_value(tag_value);

		zbx_db_insert_add_values(&ins_service_problem_tag, __UINT64_C(0), serviceid, "ServiceLink", 0,
				tag_value);

		if (old_triggerid != triggerid)
		{
			zbx_vector_uint64_t	problemtag_eventids;

			zbx_db_insert_add_values(&ins_trigger_tag, __UINT64_C(0), triggerid, "ServiceLink", tag_value);

			zbx_vector_uint64_create(&problemtag_eventids);

			DBpatch_get_problems_by_triggerid(triggerid, &problemtag_eventids);

			for (i = 0; i < problemtag_eventids.values_num; i++)
			{
				zbx_db_insert_add_values(&ins_problem_tag, __UINT64_C(0), problemtag_eventids.values[i],
						"ServiceLink", tag_value);
			}

			zbx_vector_uint64_destroy(&problemtag_eventids);
		}

		old_triggerid = triggerid;

		zbx_free(tag_value);
	}

	zbx_db_insert_autoincrement(&ins_service_problem_tag, "service_problem_tagid");
	ret = zbx_db_insert_execute(&ins_service_problem_tag);

	if (FAIL == ret)
		goto out;

	zbx_db_insert_autoincrement(&ins_trigger_tag, "triggertagid");
	ret = zbx_db_insert_execute(&ins_trigger_tag);

	if (FAIL == ret)
		goto out;

	zbx_db_insert_autoincrement(&ins_problem_tag, "problemtagid");
	ret = zbx_db_insert_execute(&ins_problem_tag);
out:
	zbx_db_insert_clean(&ins_service_problem_tag);
	zbx_db_insert_clean(&ins_trigger_tag);
	zbx_db_insert_clean(&ins_problem_tag);
	DBfree_result(result);

	return ret;
}

static int	DBpatch_5050010(void)
{
	return DBdrop_foreign_key("services", 1);
}

static int	DBpatch_5050011(void)
{
	return DBdrop_field("services", "triggerid");
}

static int	DBpatch_5050012(void)
{
	const ZBX_TABLE table =
		{"service_tag", "servicetagid", 0,
			{
				{"servicetagid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
				{"serviceid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
				{"tag", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{"value", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{0}
			},
			NULL
		};

	return DBcreate_table(&table);
}

static int	DBpatch_5050013(void)
{
	const ZBX_FIELD	field = {"serviceid", NULL, "services", "serviceid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("service_tag", 1, &field);
}

static int	DBpatch_5050014(void)
{
	return DBdrop_field("services_links", "soft");
}

static int	DBpatch_5050015(void)
{
	return DBcreate_index("service_tag", "service_tag_1", "serviceid", 0);
}

static void	DBpatch_get_role_rules(zbx_uint64_t roleid, int *ui_default_access, int *actions_default_access,
		int *ui_conf_services)
{
	DB_ROW		row;
	DB_RESULT	result;

	result = DBselect("select value_int,name from role_rule where roleid=" ZBX_FS_UI64 " and name in "
			"('ui.default_access', 'actions.default_access', 'ui.configuration.services')", roleid);

	while (NULL != (row = DBfetch(result)))
	{
		int	value;
		char	*name;

		value = atoi(row[0]);
		name = row[1];

		if (0 == strcmp(name, "ui.default_access"))
		{
			*ui_default_access = value;
		}
		else if (0 == strcmp(name, "actions.default_access"))
		{
			*actions_default_access = value;
		}
		else if (0 == strcmp(name, "ui.configuration.services"))
		{
			*ui_conf_services = value;
		}
	}

	DBfree_result(result);
}

static int	DBpatch_5050016(void)
{
	DB_ROW		row;
	DB_RESULT	result;
	int		ret = SUCCEED;

	result = DBselect("select distinct roleid from role_rule");

	while (NULL != (row = DBfetch(result)))
	{
		zbx_uint64_t	roleid;
		int ui_def_access = -1, act_def_access = -1, ui_conf_services = -1;

		ZBX_STR2UINT64(roleid, row[0]);

		DBpatch_get_role_rules(roleid, &ui_def_access, &act_def_access, &ui_conf_services);

		if (ui_def_access == act_def_access && -1 != ui_conf_services)
		{
			if (ZBX_DB_OK > DBexecute("update role_rule set name='actions.manage_services' where"
					" roleid=" ZBX_FS_UI64 " and name='ui.configuration.services'", roleid))
			{
				ret = FAIL;
				goto out;
			}
		}
		else if (1 == ui_def_access && -1 == ui_conf_services && 0 == act_def_access)
		{
			if (ZBX_DB_OK > DBexecute("insert into role_rule (role_ruleid,roleid,type,name,value_int,"
					"value_str,value_moduleid) values (" ZBX_FS_UI64 "," ZBX_FS_UI64 ",0,"
					"'actions.manage_services',1,'',NULL)", DBget_maxid("role_rule"), roleid))
			{
				ret = FAIL;
				goto out;
			}
		}
		else if (0 == ui_def_access && 1 == ui_conf_services && 1 == act_def_access)
		{
			if (ZBX_DB_OK > DBexecute("delete from role_rule where roleid=" ZBX_FS_UI64 " and "
					"name='ui.configuration.services'", roleid))
			{
				ret = FAIL;
				goto out;
			}
		}
	}
out:
	DBfree_result(result);

	return ret;
}

static int	DBpatch_5050017(void)
{
	const ZBX_FIELD	field = {"servicealarmid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_field("escalations", &field);
}

static int	DBpatch_5050018(void)
{
	const ZBX_FIELD	field = {"serviceid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_field("escalations", &field);
}

static int	DBpatch_5050019(void)
{
	return DBdrop_index("escalations", "escalations_1");
}

static int	DBpatch_5050020(void)
{
	return DBcreate_index("escalations", "escalations_1", "triggerid,itemid,serviceid,escalationid", 1);
}

static int	DBpatch_5050021(void)
{
	const ZBX_FIELD	field = {"hk_events_service", "1d", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_5050022(void)
{
	const ZBX_FIELD	field = {"passwd_min_length", "8", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_5050023(void)
{
	const ZBX_FIELD	field = {"passwd_check_rules", "8", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_5050024(void)
{
	return DBdrop_table("auditlog_details");
}

static int	DBpatch_5050030(void)
{
	if (0 == (ZBX_PROGRAM_TYPE_SERVER & program_type))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("delete from ids where table_name='auditlog_details' and field_name='auditdetailid'"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_5050031(void)
{
	const ZBX_FIELD	field = {"auditlog_enabled", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_5050032(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("update config set default_lang='en_US' where default_lang='en_GB'"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_5050033(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("update users set lang='en_US' where lang='en_GB'"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_5050034(void)
{
	const ZBX_FIELD	field = {"default_lang", "en_US", NULL, NULL, 5, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBset_default("config", &field);
}

static int	DBpatch_5050035(void)
{
	return DBdrop_table("auditlog");
}

static int	DBpatch_5050036(void)
{
	const ZBX_TABLE table =
		{"auditlog", "auditid", 0,
			{
				{"auditid", NULL, NULL, NULL, 0, ZBX_TYPE_CUID, ZBX_NOTNULL, 0},
				{"userid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0},
				{"username", "", NULL, NULL, 100, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{"clock", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
				{"ip", "", NULL, NULL, 39, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{"action", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
				{"resourcetype", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
				{"resourceid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
				{"resourcename", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{"recordsetid", NULL, NULL, NULL, 0, ZBX_TYPE_CUID, ZBX_NOTNULL, 0},
				{"details", "", NULL, NULL, 0, ZBX_TYPE_LONGTEXT, ZBX_NOTNULL, 0},
				{0}
			},
			NULL
		};

	return DBcreate_table(&table);
}

static int	DBpatch_5050037(void)
{
	return DBcreate_index("auditlog", "auditlog_1", "userid,clock", 0);
}

static int	DBpatch_5050038(void)
{
	return DBcreate_index("auditlog", "auditlog_2", "clock", 0);
}

static int	DBpatch_5050039(void)
{
	return DBcreate_index("auditlog", "auditlog_3", "resourcetype,resourceid", 0);
}

static int	DBpatch_5050040(void)
{
	if (0 == (ZBX_PROGRAM_TYPE_SERVER & program_type))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("delete from ids where table_name='auditlog' and field_name='auditid'"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_5050041(void)
{
	const ZBX_FIELD	field = {"weight", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("services", &field);
}

static int	DBpatch_5050042(void)
{
	const ZBX_FIELD	field = {"propagation_rule", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("services", &field);
}

static int	DBpatch_5050043(void)
{
	const ZBX_FIELD	field = {"propagation_value", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("services", &field);
}

static int	DBpatch_5050044(void)
{
	const ZBX_TABLE table =
		{"service_status_rule", "service_status_ruleid", 0,
			{
				{"service_status_ruleid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
				{"serviceid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
				{"type", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
				{"limit_value", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
				{"limit_status", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
				{"new_status", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
				{0}
			},
			NULL
		};

	return DBcreate_table(&table);
}

static int	DBpatch_5050045(void)
{
	return DBcreate_index("service_status_rule", "service_status_rule_1", "serviceid", 0);
}

static int	DBpatch_5050046(void)
{
	const ZBX_FIELD	field = {"serviceid", NULL, "services", "serviceid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("service_status_rule", 1, &field);
}

static int	DBpatch_5050047(void)
{
	const ZBX_FIELD	field = {"status", "-1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBset_default("services", &field);
}

static int	DBpatch_5050048(void)
{
	const ZBX_FIELD	field = {"value", "-1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBset_default("service_alarms", &field);
}

static int	DBpatch_5050049(void)
{
	if (ZBX_DB_OK > DBexecute("update services set status=-1 where status=0"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_5050050(void)
{
	if (ZBX_DB_OK > DBexecute("update service_alarms set value=-1 where value=0"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_5050051(void)
{
	const ZBX_FIELD	field = {"value_serviceid", NULL, "services", "serviceid", 0, ZBX_TYPE_ID, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_field("role_rule", &field);
}

static int	DBpatch_5050052(void)
{
	const ZBX_FIELD	field = {"value_serviceid", NULL, "services", "serviceid", 0, ZBX_TYPE_ID, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("role_rule", 3, &field);
}

static int	DBpatch_5050053(void)
{
	return DBcreate_index("role_rule", "role_rule_3", "value_serviceid", 0);
}

static void	DBpatch_5050054_calc_services_write_value(zbx_uint64_t roleid, int *value)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		manage_services = -1, default_access = 1;

	result = DBselect("select name,value_int from role_rule where roleid=" ZBX_FS_UI64, roleid);

	while (NULL != (row = DBfetch(result)))
	{
		if (0 == strcmp("actions.manage_services", row[0]))
		{
			manage_services = atoi(row[1]);
			break;
		}
		else if (0 == strcmp("actions.default_access", row[0]))
			default_access = atoi(row[1]);
	}
	DBfree_result(result);

	if (-1 != manage_services)
		*value = manage_services;
	else
		*value = default_access;
}

static int	DBpatch_5050054(void)
{
	DB_RESULT	result;
	DB_ROW		row;
	zbx_db_insert_t	db_insert;
	int		ret = FAIL;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	zbx_db_insert_prepare(&db_insert, "role_rule", "role_ruleid", "roleid", "type", "name", "value_int",
			"value_str", "value_moduleid", NULL);

	result = DBselect("select roleid from role");

	while (NULL != (row = DBfetch(result)))
	{
		zbx_uint64_t	roleid;
		int		services_write;

		ZBX_STR2UINT64(roleid, row[0]);

		zbx_db_insert_add_values(&db_insert, __UINT64_C(0), roleid, 0, "service.read", 1, "", NULL);
		DBpatch_5050054_calc_services_write_value(roleid, &services_write);

		zbx_db_insert_add_values(&db_insert, __UINT64_C(0), roleid, 0, "services.write", services_write, "",
				NULL);

	}
	DBfree_result(result);

	zbx_db_insert_autoincrement(&db_insert, "role_ruleid");
	ret = zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);

	return ret;
}

#endif

DBPATCH_START(5050)

/* version, duplicates flag, mandatory flag */

DBPATCH_ADD(5050001, 0, 1)
DBPATCH_ADD(5050002, 0, 1)
DBPATCH_ADD(5050003, 0, 1)
DBPATCH_ADD(5050004, 0, 1)
DBPATCH_ADD(5050005, 0, 1)
DBPATCH_ADD(5050006, 0, 1)
DBPATCH_ADD(5050007, 0, 1)
DBPATCH_ADD(5050008, 0, 1)
DBPATCH_ADD(5050009, 0, 1)
DBPATCH_ADD(5050010, 0, 1)
DBPATCH_ADD(5050011, 0, 1)
DBPATCH_ADD(5050012, 0, 1)
DBPATCH_ADD(5050013, 0, 1)
DBPATCH_ADD(5050014, 0, 1)
DBPATCH_ADD(5050015, 0, 1)
DBPATCH_ADD(5050016, 0, 1)
DBPATCH_ADD(5050017, 0, 1)
DBPATCH_ADD(5050018, 0, 1)
DBPATCH_ADD(5050019, 0, 1)
DBPATCH_ADD(5050020, 0, 1)
DBPATCH_ADD(5050021, 0, 1)
DBPATCH_ADD(5050022, 0, 1)
DBPATCH_ADD(5050023, 0, 1)
DBPATCH_ADD(5050024, 0, 1)
DBPATCH_ADD(5050030, 0, 1)
DBPATCH_ADD(5050031, 0, 1)
DBPATCH_ADD(5050032, 0, 1)
DBPATCH_ADD(5050033, 0, 1)
DBPATCH_ADD(5050034, 0, 1)
DBPATCH_ADD(5050035, 0, 1)
DBPATCH_ADD(5050036, 0, 1)
DBPATCH_ADD(5050037, 0, 1)
DBPATCH_ADD(5050038, 0, 1)
DBPATCH_ADD(5050039, 0, 1)
DBPATCH_ADD(5050040, 0, 1)
DBPATCH_ADD(5050041, 0, 1)
DBPATCH_ADD(5050042, 0, 1)
DBPATCH_ADD(5050043, 0, 1)
DBPATCH_ADD(5050044, 0, 1)
DBPATCH_ADD(5050045, 0, 1)
DBPATCH_ADD(5050046, 0, 1)
DBPATCH_ADD(5050047, 0, 1)
DBPATCH_ADD(5050048, 0, 1)
DBPATCH_ADD(5050049, 0, 1)
DBPATCH_ADD(5050050, 0, 1)
DBPATCH_ADD(5050051, 0, 1)
DBPATCH_ADD(5050052, 0, 1)
DBPATCH_ADD(5050053, 0, 1)
DBPATCH_ADD(5050054, 0, 1)

DBPATCH_END()
