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

#include "common.h"
#include "db.h"
#include "dbupgrade.h"
#include "dbupgrade_macros.h"
#include "log.h"
#include "../zbxalgo/vectorimpl.h"

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

static int	DBpatch_5050016(void)
{
	if (ZBX_DB_OK > DBexecute("update role_rule set name='actions.manage_services'"
			" where name='ui.configuration.services'"))
	{
		return FAIL;
	}

	return SUCCEED;
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

#define DBPATCH_MESSAGE_SUBJECT_LEN		255
#define DBPATCH_GRAPH_NAME_LEN			128
#define DBPATCH_SYSMAPS_LABEL_LEN		255
#define DBPATCH_SYSMAPS_ELEMENTS_LABEL_LEN	2048
#define DBPATCH_SYSMAPS_LINKS_LABEL_LEN		2048

#if defined(HAVE_ORACLE)
#	define DBPATCH_SHORTTEXT_LEN		2048
#else
#	define DBPATCH_SHORTTEXT_LEN		65535
#endif

static int	dbpatch_update_simple_macro(const char *table, const char *field, const char *id, size_t field_len,
		const char *descr)
{
	DB_ROW		row;
	DB_RESULT	result;
	char		*sql;
	size_t		sql_alloc = 4096, sql_offset = 0;
	int		ret = SUCCEED;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	sql = zbx_malloc(NULL, sql_alloc);

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	result = DBselect("select %s,%s from %s", id, field, table);

	while (NULL != (row = DBfetch(result)))
	{
		zbx_token_t	token;
		char		*out = NULL;
		size_t		out_alloc = 0, out_offset = 0, pos = 0, last_pos = 0;

		for (; SUCCEED == zbx_token_find(row[1], (int)pos, &token, ZBX_TOKEN_SEARCH_BASIC |
				ZBX_TOKEN_SEARCH_SIMPLE_MACRO); pos++)
		{
			char	*replace;

			pos = token.loc.r;

			if (ZBX_TOKEN_SIMPLE_MACRO != token.type)
				continue;

			replace = NULL;
			dbpatch_convert_simple_macro(row[1], &token.data.simple_macro, 1, &replace);
			zbx_strncpy_alloc(&out, &out_alloc, &out_offset, row[1] + last_pos, token.loc.l - last_pos);
			replace = zbx_dsprintf(replace, "{?%s}", replace);
			zbx_strcpy_alloc(&out, &out_alloc, &out_offset, replace);
			zbx_free(replace);
			last_pos = token.loc.r + 1;

			pos = token.loc.r;
		}

		if (0 == out_alloc)
			continue;

		zbx_strcpy_alloc(&out, &out_alloc, &out_offset, row[1] + last_pos);

		if (field_len >= zbx_strlen_utf8(out))
		{
			char	*esc;

			esc = DBdyn_escape_field(table, field, out);
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update %s set %s='%s'"
					" where %s=%s;\n", table, field, esc, id, row[0]);
			zbx_free(esc);

			ret = DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
		}
		else
			zabbix_log(LOG_LEVEL_WARNING, "cannot convert %s, too long expression: \"%s\"", descr, row[0]);

		zbx_free(out);
	}
	DBfree_result(result);

	DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (SUCCEED == ret && 16 < sql_offset)
	{
		if (ZBX_DB_OK > DBexecute("%s", sql))
			ret = FAIL;
	}

	zbx_free(sql);

	return ret;
}

static int	DBpatch_5050051(void)
{
	return dbpatch_update_simple_macro("opmessage", "subject", "operationid", DBPATCH_MESSAGE_SUBJECT_LEN,
			"operation message subject");
}

static int	DBpatch_5050052(void)
{
	return dbpatch_update_simple_macro("opmessage", "message", "operationid", DBPATCH_SHORTTEXT_LEN,
			"operation message body");
}

static int	DBpatch_5050053(void)
{
	return dbpatch_update_simple_macro("media_type_message", "subject", "mediatype_messageid",
			DBPATCH_MESSAGE_SUBJECT_LEN, "media message subject");
}

static int	DBpatch_5050054(void)
{
	return dbpatch_update_simple_macro("media_type_message", "message", "mediatype_messageid",
			DBPATCH_SHORTTEXT_LEN, "media message body");
}

static int	DBpatch_5050055(void)
{
	return dbpatch_update_simple_macro("graphs", "name", "graphid", DBPATCH_GRAPH_NAME_LEN, "graph name");
}

static int	DBpatch_5050056(void)
{
	return dbpatch_update_simple_macro("sysmaps", "label_string_host", "sysmapid", DBPATCH_SYSMAPS_LABEL_LEN,
			"maps label host");
}

static int	DBpatch_5050057(void)
{
	return dbpatch_update_simple_macro("sysmaps", "label_string_hostgroup", "sysmapid", DBPATCH_SYSMAPS_LABEL_LEN,
			"maps label hostgroup");
}

static int	DBpatch_5050058(void)
{
	return dbpatch_update_simple_macro("sysmaps", "label_string_trigger", "sysmapid", DBPATCH_SYSMAPS_LABEL_LEN,
			"maps label trigger");
}

static int	DBpatch_5050059(void)
{
	return dbpatch_update_simple_macro("sysmaps", "label_string_map", "sysmapid", DBPATCH_SYSMAPS_LABEL_LEN,
			"maps label map");
}

static int	DBpatch_5050060(void)
{
	return dbpatch_update_simple_macro("sysmaps", "label_string_image", "sysmapid", DBPATCH_SYSMAPS_LABEL_LEN,
			"maps label image");
}

static int	DBpatch_5050061(void)
{
	return dbpatch_update_simple_macro("sysmaps_elements", "label", "selementid",
			DBPATCH_SYSMAPS_ELEMENTS_LABEL_LEN, "maps element label");
}

static int	DBpatch_5050062(void)
{
	return dbpatch_update_simple_macro("sysmaps_links", "label", "linkid", DBPATCH_SYSMAPS_LINKS_LABEL_LEN,
			"maps link label");
}

static int	DBpatch_5050063(void)
{
	return dbpatch_update_simple_macro("sysmap_shape", "text", "sysmap_shapeid", DBPATCH_SHORTTEXT_LEN,
			"maps shape text");
}

static int	DBpatch_5050064(void)
{
	const ZBX_FIELD	field = {"value_serviceid", NULL, "services", "serviceid", 0, ZBX_TYPE_ID, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_field("role_rule", &field);
}

static int	DBpatch_5050065(void)
{
	const ZBX_FIELD	field = {"value_serviceid", NULL, "services", "serviceid", 0, ZBX_TYPE_ID, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("role_rule", 3, &field);
}

static int	DBpatch_5050066(void)
{
	return DBcreate_index("role_rule", "role_rule_3", "value_serviceid", 0);
}

static int	DBpatch_5050067(void)
{
	if (ZBX_DB_OK > DBexecute("update role_rule set name='services.write'"
			" where name='actions.manage_services'"))
	{
		return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: calculate services.write value for the specified role             *
 *                                                                            *
 * Parameters: roleid - [IN] the role identifier                              *
 *             value  - [OUT] the services.write value                        *
 *                                                                            *
 * Return value: SUCCEED - the services.write value is calculated             *
 *               FAIL    - the services.write rule already exists             *
 *                                                                            *
 ******************************************************************************/
static int	DBpatch_5050068_calc_services_write_value(zbx_uint64_t roleid, int *value)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		default_access = 1, ret = FAIL;

	result = DBselect("select name,value_int from role_rule where roleid=" ZBX_FS_UI64, roleid);

	while (NULL != (row = DBfetch(result)))
	{
		/* write rule already exists, skip */
		if (0 == strcmp("services.write", row[0]))
			goto out;

		if (0 == strcmp("actions.default_access", row[0]))
			default_access = atoi(row[1]);
	}

	*value = default_access;
	ret = SUCCEED;
out:
	DBfree_result(result);

	return ret;
}

static int	DBpatch_5050068(void)
{
	DB_RESULT	result;
	DB_ROW		row;
	zbx_db_insert_t	db_insert;
	int		ret = FAIL;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	zbx_db_insert_prepare(&db_insert, "role_rule", "role_ruleid", "roleid", "type", "name", "value_int", NULL);

	result = DBselect("select roleid,type from role");

	while (NULL != (row = DBfetch(result)))
	{
		zbx_uint64_t	roleid;
		int		services_write;

		ZBX_STR2UINT64(roleid, row[0]);

		zbx_db_insert_add_values(&db_insert, __UINT64_C(0), roleid, 0, "service.read", 1);

		if (SUCCEED == DBpatch_5050068_calc_services_write_value(roleid, &services_write))
		{
			int	role_type;

			role_type = atoi(row[1]);

			if (USER_TYPE_ZABBIX_ADMIN != role_type && USER_TYPE_SUPER_ADMIN != role_type)
				services_write = 0;

			zbx_db_insert_add_values(&db_insert, __UINT64_C(0), roleid, 0, "services.write", services_write);
		}

	}
	DBfree_result(result);

	zbx_db_insert_autoincrement(&db_insert, "role_ruleid");
	ret = zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);

	return ret;
}

static int	DBpatch_5050070(void)
{
	const ZBX_FIELD	old_field = {"params", "", NULL, NULL, 0, ZBX_TYPE_SHORTTEXT, ZBX_NOTNULL, 0};
	const ZBX_FIELD	field = {"params", "", NULL, NULL, 0, ZBX_TYPE_TEXT, ZBX_NOTNULL, 0};

	return DBmodify_field_type("item_preproc", &field, &old_field);
}

static int	DBpatch_5050071(void)
{
	const ZBX_FIELD	old_field = {"description", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};
	const ZBX_FIELD	field = {"description", "", NULL, NULL, 0, ZBX_TYPE_SHORTTEXT, ZBX_NOTNULL, 0};

	return DBmodify_field_type("triggers", &field, &old_field);
}

static int	DBpatch_5050072(void)
{
	const ZBX_FIELD	old_field = {"message", "", NULL, NULL, 0, ZBX_TYPE_SHORTTEXT, ZBX_NOTNULL, 0};
	const ZBX_FIELD	field = {"message", "", NULL, NULL, 0, ZBX_TYPE_TEXT, ZBX_NOTNULL, 0};

	return DBmodify_field_type("media_type_message", &field, &old_field);
}

static int	DBpatch_5050073(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("delete from profiles where idx like 'web.overview.%%'"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_5050074(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("delete from role_rule where name='ui.monitoring.overview'"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_5050075(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("update profiles set idx='web.hosts.sort' where idx='web.hosts.php.sort'"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_5050076(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("update profiles set idx='web.hosts.sortorder' where idx='web.hosts.php.sortorder'"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_5050077(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("update profiles set value_str='host.list'"
				" where idx='web.pager.entity' and value_str like 'hosts.php'"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_5050078(void)
{
	const ZBX_FIELD	field = {"ha_failover_delay", "1m", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_5050079(void)
{
	const ZBX_TABLE	table =
			{"ha_node", "ha_nodeid", 0,
				{
					{"ha_nodeid", NULL, NULL, NULL, 0, ZBX_TYPE_CUID, ZBX_NOTNULL, 0},
					{"name", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"address", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"port", "10051", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"lastaccess", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"status", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"ha_sessionid", "", NULL, NULL, 0, ZBX_TYPE_CUID, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_5050080(void)
{
	return DBcreate_index("ha_node", "ha_node_1", "name", 1);
}

static int	DBpatch_5050081(void)
{
	return DBcreate_index("ha_node", "ha_node_2", "status,lastaccess", 0);
}

static int	DBpatch_5050082(void)
{
	return DBdrop_table("auditlog");
}

static int	DBpatch_5050083(void)
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
				{"resourceid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0},
				{"resource_cuid", NULL, NULL, NULL, 0, ZBX_TYPE_CUID, 0, 0},
				{"resourcename", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{"recordsetid", NULL, NULL, NULL, 0, ZBX_TYPE_CUID, ZBX_NOTNULL, 0},
				{"details", "", NULL, NULL, 0, ZBX_TYPE_LONGTEXT, ZBX_NOTNULL, 0},
				{0}
			},
			NULL
		};

	return DBcreate_table(&table);
}

static int	DBpatch_5050084(void)
{
	return DBcreate_index("auditlog", "auditlog_1", "userid,clock", 0);
}

static int	DBpatch_5050085(void)
{
	return DBcreate_index("auditlog", "auditlog_2", "clock", 0);
}

static int	DBpatch_5050086(void)
{
	return DBcreate_index("auditlog", "auditlog_3", "resourcetype,resourceid", 0);
}

static int	DBpatch_5050088(void)
{
	const ZBX_FIELD	field = {"geomaps_tile_provider", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_5050089(void)
{
	const ZBX_FIELD	field = {"geomaps_tile_url", "", NULL, NULL, 1024, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_5050090(void)
{
	const ZBX_FIELD	field = {"geomaps_max_zoom", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_5050091(void)
{
	const ZBX_FIELD	field = {"geomaps_attribution", "", NULL, NULL, 1024, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_5050092(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("update config set geomaps_tile_provider='OpenStreetMap.Mapnik'"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_5050093(void)
{
	const ZBX_TABLE	table =
			{"dbversion", "dbversionid", 0,
				{
					{"dbversionid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"mandatory", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"optional", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{NULL}
				},
				NULL
			};

	if (FAIL == DBdrop_table("dbversion"))
		return FAIL;

	if (FAIL == DBcreate_table(&table))
		return FAIL;

	if (ZBX_DB_OK > DBexecute("insert into dbversion (dbversionid,mandatory,optional) values (1,0,0)"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_5050094(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_PROXY))
		return SUCCEED;

	return DBdrop_table("history");
}

static int	DBpatch_5050095(void)
{
	const ZBX_TABLE	table =
			{"history", "itemid,clock,ns", 0,
				{
					{"itemid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"clock", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"value", "0.0000", NULL, NULL, 0, ZBX_TYPE_FLOAT, ZBX_NOTNULL, 0},
					{"ns", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{NULL}
				},
				NULL
			};

	if (0 == (program_type & ZBX_PROGRAM_TYPE_PROXY))
		return SUCCEED;

	return DBcreate_table(&table);
}

static int	DBpatch_5050096(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_PROXY))
		return SUCCEED;

	return DBdrop_table("history_uint");
}

static int	DBpatch_5050097(void)
{
	const ZBX_TABLE	table =
			{"history_uint", "itemid,clock,ns", 0,
				{
					{"itemid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"clock", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"value", "0", NULL, NULL, 0, ZBX_TYPE_UINT, ZBX_NOTNULL, 0},
					{"ns", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{NULL}
				},
				NULL
			};

	if (0 == (program_type & ZBX_PROGRAM_TYPE_PROXY))
		return SUCCEED;

	return DBcreate_table(&table);
}

static int	DBpatch_5050098(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_PROXY))
		return SUCCEED;

	return DBdrop_table("history_str");
}

static int	DBpatch_5050099(void)
{
	const ZBX_TABLE	table =
			{"history_str", "itemid,clock,ns", 0,
				{
					{"itemid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"clock", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"value", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"ns", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{NULL}
				},
				NULL
			};

	if (0 == (program_type & ZBX_PROGRAM_TYPE_PROXY))
		return SUCCEED;

	return DBcreate_table(&table);
}

static int	DBpatch_5050100(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_PROXY))
		return SUCCEED;

	return DBdrop_table("history_log");
}

static int	DBpatch_5050101(void)
{
	const ZBX_TABLE	table =
			{"history_log", "itemid,clock,ns", 0,
				{
					{"itemid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"clock", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"timestamp", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"source", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"severity", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"value", "", NULL, NULL, 0, ZBX_TYPE_TEXT, ZBX_NOTNULL, 0},
					{"logeventid", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"ns", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{NULL}
				},
				NULL
			};

	if (0 == (program_type & ZBX_PROGRAM_TYPE_PROXY))
		return SUCCEED;

	return DBcreate_table(&table);
}

static int	DBpatch_5050102(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_PROXY))
		return SUCCEED;

	return DBdrop_table("history_text");
}

static int	DBpatch_5050103(void)
{
	const ZBX_TABLE	table =
			{"history_text", "itemid,clock,ns", 0,
				{
					{"itemid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"clock", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"value", "", NULL, NULL, 0, ZBX_TYPE_TEXT, ZBX_NOTNULL, 0},
					{"ns", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{NULL}
				},
				NULL
			};

	if (0 == (program_type & ZBX_PROGRAM_TYPE_PROXY))
		return SUCCEED;

	return DBcreate_table(&table);
}

static int	DBpatch_5050104(void)
{
	const ZBX_FIELD old_field = {"dbversion_status", "", NULL, NULL, 1024, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};
	const ZBX_FIELD new_field = {"dbversion_status", "", NULL, NULL, 0, ZBX_TYPE_SHORTTEXT, ZBX_NOTNULL, 0};

	return DBmodify_field_type("config", &new_field, &old_field);
}

static int	DBpatch_5050105(void)
{
#ifdef HAVE_MYSQL
	return DBdrop_foreign_key("items", 1);
#else
	return SUCCEED;
#endif
}

static int	DBpatch_5050106(void)
{
#ifdef HAVE_MYSQL
	return DBdrop_index("items", "items_1");
#else
	return SUCCEED;
#endif
}

static int	DBpatch_5050107(void)
{
#ifdef HAVE_MYSQL
	return DBcreate_index("items", "items_1", "hostid,key_(764)", 0);
#else
	return SUCCEED;
#endif
}

static int	DBpatch_5050108(void)
{
#ifdef HAVE_MYSQL
	const ZBX_FIELD	field = {"hostid", NULL, "hosts", "hostid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("items", 1, &field);
#else
	return SUCCEED;
#endif
}

static int	DBpatch_5050109(void)
{
#ifdef HAVE_MYSQL
	return DBdrop_index("items", "items_8");
#else
	return SUCCEED;
#endif
}

static int	DBpatch_5050110(void)
{
#ifdef HAVE_MYSQL
	return DBcreate_index("items", "items_8", "key_(768)", 0);
#else
	return SUCCEED;
#endif
}

static int	DBpatch_5050111(void)
{
	if (FAIL != DBindex_exists("alerts", "alerts_8"))
		return SUCCEED;

	return DBcreate_index("alerts", "alerts_8", "acknowledgeid", 0);
}

static int	DBpatch_5050112(void)
{
	const ZBX_FIELD	field = {"notify_if_canceled", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("actions", &field);
}

static int	DBpatch_5050113(void)
{
	const ZBX_FIELD old_field = {"formula", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};
	const ZBX_FIELD new_field = {"formula", "", NULL, NULL, 1024, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("actions", &new_field, &old_field);
}

static int	DBpatch_5050114(void)
{
	DB_RESULT	result;
	DB_ROW		row;
	char		*sql = NULL, *params = NULL;
	const char	*output;
	size_t		sql_alloc = 0, sql_offset = 0, params_alloc = 0, params_offset = 0;
	int		ret = SUCCEED;

	/* 22 - ZBX_PREPROC_PROMETHEUS_PATTERN */
	result = DBselect("select item_preprocid,params from item_preproc where type=22");

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	while (SUCCEED == ret && NULL != (row = DBfetch(result)))
	{
		char	*params_esc;

		if (NULL == (output = strchr(row[1], '\n')))
			continue;

		zbx_strncpy_alloc(&params, &params_alloc, &params_offset, row[1], (size_t)(output - row[1] + 1));
		zbx_strcpy_alloc(&params, &params_alloc, &params_offset, '\0' == output[1] ? "value" : "label");
		zbx_strcpy_alloc(&params, &params_alloc, &params_offset, output);

		params_esc = DBdyn_escape_field("item_preproc", "params", params);

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"update item_preproc set params='%s' where item_preprocid=%s;\n", params_esc, row[0]);
		ret = DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset);

		zbx_free(params_esc);
		params_offset = 0;
	}

	DBfree_result(result);

	DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (SUCCEED == ret && 16 < sql_offset)
	{
		if (ZBX_DB_OK > DBexecute("%s", sql))
			ret = FAIL;
	}

	zbx_free(params);
	zbx_free(sql);

	return ret;
}

static int	DBpatch_5050115(void)
{
	const ZBX_TABLE	table =
		{"sla", "slaid", 0,
			{
				{"slaid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
				{"name", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{"period", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
				{"slo", "99.9", NULL, NULL, 0, ZBX_TYPE_FLOAT, ZBX_NOTNULL, 0},
				{"effective_date", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
				{"timezone", "UTC", NULL, NULL, 50, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{"status", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
				{"description", "", NULL, NULL, 0, ZBX_TYPE_SHORTTEXT, ZBX_NOTNULL, 0},
				{0}
			},
			NULL
		};

	return DBcreate_table(&table);
}

static int	DBpatch_5050116(void)
{
	return DBcreate_index("sla", "sla_1", "name", 1);
}

static int	DBpatch_5050117(void)
{
	const ZBX_TABLE	table =
		{"sla_service_tag", "sla_service_tagid", 0,
			{
				{"sla_service_tagid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
				{"slaid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
				{"tag", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{"operator", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
				{"value", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{0}
			},
			NULL
		};

	return DBcreate_table(&table);
}

static int	DBpatch_5050118(void)
{
	return DBcreate_index("sla_service_tag", "sla_service_tag_1", "slaid", 0);
}

static int	DBpatch_5050119(void)
{
	const ZBX_FIELD	field = {"slaid", NULL, "sla", "slaid", 0, ZBX_TYPE_ID, ZBX_NOTNULL, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("sla_service_tag", 1, &field);
}

static int	DBpatch_5050120(void)
{
	const ZBX_TABLE	table =
		{"sla_schedule", "sla_scheduleid", 0,
			{
				{"sla_scheduleid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
				{"slaid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
				{"period_from", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
				{"period_to", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
				{0}
			},
			NULL
		};

	return DBcreate_table(&table);
}

static int	DBpatch_5050121(void)
{
	return DBcreate_index("sla_schedule", "sla_schedule_1", "slaid", 0);
}

static int	DBpatch_5050122(void)
{
	const ZBX_FIELD	field = {"slaid", NULL, "sla", "slaid", 0, ZBX_TYPE_ID, ZBX_NOTNULL, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("sla_schedule", 1, &field);
}

static int	DBpatch_5050123(void)
{
	const ZBX_TABLE table =
		{"sla_excluded_downtime", "sla_excluded_downtimeid", 0,
			{
				{"sla_excluded_downtimeid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
				{"slaid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
				{"name", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{"period_from", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
				{"period_to", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
				{0}
			},
			NULL
		};

	return DBcreate_table(&table);
}

static int	DBpatch_5050124(void)
{
	return DBcreate_index("sla_excluded_downtime", "sla_excluded_downtime_1", "slaid", 0);
}

static int	DBpatch_5050125(void)
{
	const ZBX_FIELD	field = {"slaid", NULL, "sla", "slaid", 0, ZBX_TYPE_ID, ZBX_NOTNULL, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("sla_excluded_downtime", 1, &field);
}

static int	DBpatch_5050126(void)
{
	const ZBX_FIELD	field = {"description", "", NULL, NULL, 0, ZBX_TYPE_SHORTTEXT, ZBX_NOTNULL, 0};

	return DBadd_field("services", &field);
}

static int	DBpatch_5050127(void)
{
	const ZBX_FIELD	field = {"uuid", "", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("services", &field);
}

typedef struct
{
	int	type;
	int	from;
	int	to;
	char	*note;
}
services_times_t;

ZBX_VECTOR_DECL(services_times, services_times_t)
ZBX_VECTOR_IMPL(services_times, services_times_t)

typedef struct
{
	int				showsla;
	double				goodsla;
	zbx_vector_services_times_t	services_times;
	zbx_vector_uint64_t		serviceids;
}
sla_t;

ZBX_PTR_VECTOR_DECL(sla, sla_t *)
ZBX_PTR_VECTOR_IMPL(sla, sla_t *)

static int	compare_services_time(const void *d1, const void *d2)
{
	const services_times_t	*a, *b;
	int			ret;

	a = (const services_times_t *)d1;
	b = (const services_times_t *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(a->type, b->type);
	ZBX_RETURN_IF_NOT_EQUAL(a->from, b->from);
	ZBX_RETURN_IF_NOT_EQUAL(a->to, b->to);

	if (0 != (ret = strcmp(a->note, b->note)))
		return ret;

	return 0;
}

static int	compare_sla(const void *d1, const void *d2)
{
	const sla_t	*a, *b;
	int		i, ret;

	a = *(const sla_t * const *)d1;
	b = *(const sla_t * const *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(a->showsla, b->showsla);
	ZBX_RETURN_IF_NOT_EQUAL(a->goodsla, b->goodsla);
	ZBX_RETURN_IF_NOT_EQUAL(a->services_times.values_num, b->services_times.values_num);

	for (i = 0; i < a->services_times.values_num; i++)
	{
		if (0 != (ret = compare_services_time(&a->services_times.values[i], &b->services_times.values[i])))
			return ret;
	}

	return 0;
}

static void	services_time_clean(services_times_t *services_time)
{
	zbx_free(services_time->note);
}

static void	sla_clean(sla_t *sla)
{
	int	i;

	for (i = 0; i < sla->services_times.values_num; i++)
		services_time_clean(&sla->services_times.values[i]);

	zbx_vector_services_times_destroy(&sla->services_times);
	zbx_vector_uint64_destroy(&sla->serviceids);
	zbx_free(sla);
}

#define ZBX_SLA_PERIOD_WEEKLY		1

#define SERVICE_TIME_TYPE_UPTIME	0
#define SERVICE_TIME_TYPE_DOWNTIME	1
#define SERVICE_INITIAL_EFFECTIVE_DATE	946684800

#define SLA_TAG_NAME			"SLA"

static int	db_insert_sla(const zbx_vector_sla_t *uniq_slas, const char *default_timezone)
{
	zbx_db_insert_t		db_insert_sla, db_insert_sla_schedule, db_insert_sla_excluded_downtime,
				db_insert_sla_service_tag, db_insert_service_tag;
	int			i, j;
	zbx_uint64_t		slaid;
	int			ret = FAIL;

	zbx_db_insert_prepare(&db_insert_sla, "sla", "slaid", "name", "status", "slo", "effective_date", "period",
			"timezone", NULL);

	zbx_db_insert_prepare(&db_insert_sla_service_tag, "sla_service_tag", "sla_service_tagid", "slaid", "tag",
			"value", NULL);

	zbx_db_insert_prepare(&db_insert_service_tag, "service_tag", "servicetagid", "serviceid", "tag", "value",
			NULL);

	zbx_db_insert_prepare(&db_insert_sla_schedule, "sla_schedule", "sla_scheduleid", "slaid", "period_from",
			"period_to", NULL);
	zbx_db_insert_prepare(&db_insert_sla_excluded_downtime, "sla_excluded_downtime", "sla_excluded_downtimeid",
			"slaid", "period_from", "period_to", "name", NULL);

	for (i = 0, slaid = 0; i < uniq_slas->values_num; i++)
	{
		char		buffer[MAX_STRING_LEN];
		const sla_t	*sla = uniq_slas->values[i];

		zbx_snprintf(buffer, sizeof(buffer), "%s:" ZBX_FS_UI64, SLA_TAG_NAME, ++slaid);

		zbx_db_insert_add_values(&db_insert_sla, slaid, buffer, sla->showsla, sla->goodsla,
				SERVICE_INITIAL_EFFECTIVE_DATE, ZBX_SLA_PERIOD_WEEKLY, default_timezone);

		zbx_snprintf(buffer, sizeof(buffer), ZBX_FS_UI64, slaid);
		zbx_db_insert_add_values(&db_insert_sla_service_tag, slaid, slaid, SLA_TAG_NAME, buffer);

		for (j = 0; j < sla->serviceids.values_num; j++)
		{
			zbx_db_insert_add_values(&db_insert_service_tag, __UINT64_C(0), sla->serviceids.values[j],
					SLA_TAG_NAME, buffer);
		}

		for (j = 0; j < sla->services_times.values_num; j++)
		{
			services_times_t	*services_time = &sla->services_times.values[j];

			if (SERVICE_TIME_TYPE_UPTIME == services_time->type)
			{
				zbx_db_insert_add_values(&db_insert_sla_schedule, __UINT64_C(0), slaid,
						services_time->from, services_time->to);
				continue;
			}

			zbx_db_insert_add_values(&db_insert_sla_excluded_downtime, __UINT64_C(0), slaid,
					services_time->from, services_time->to, services_time->note);
		}
	}

	if (SUCCEED != zbx_db_insert_execute(&db_insert_sla))
		goto out;

	if (SUCCEED != zbx_db_insert_execute(&db_insert_sla_service_tag))
		goto out;

	zbx_db_insert_autoincrement(&db_insert_service_tag, "servicetagid");
	if (SUCCEED != zbx_db_insert_execute(&db_insert_service_tag))
		goto out;

	zbx_db_insert_autoincrement(&db_insert_sla_schedule, "sla_scheduleid");
	if (SUCCEED != zbx_db_insert_execute(&db_insert_sla_schedule))
		goto out;

	zbx_db_insert_autoincrement(&db_insert_sla_excluded_downtime, "sla_excluded_downtimeid");
	if (SUCCEED != zbx_db_insert_execute(&db_insert_sla_excluded_downtime))
		goto out;

	ret = SUCCEED;
out:
	zbx_db_insert_clean(&db_insert_sla);
	zbx_db_insert_clean(&db_insert_sla_service_tag);
	zbx_db_insert_clean(&db_insert_service_tag);
	zbx_db_insert_clean(&db_insert_sla_schedule);
	zbx_db_insert_clean(&db_insert_sla_excluded_downtime);

	return ret;
}

static void	services_times_convert_downtime(zbx_vector_services_times_t *services_times)
{
	int				i, j, uptime_count = 0;
	zbx_vector_services_times_t	services_downtimes;

	zbx_vector_services_times_create(&services_downtimes);

	for (i = 0; i < services_times->values_num; i++)
	{
		services_times_t	service_time = services_times->values[i];

		if (SERVICE_TIME_TYPE_DOWNTIME == service_time.type)
		{
			zbx_vector_services_times_append(&services_downtimes, service_time);
			zbx_vector_services_times_remove(services_times, i);
			i--;
		}
		else if (SERVICE_TIME_TYPE_UPTIME == service_time.type)
			uptime_count++;
	}

	if (0 == uptime_count && 0 != services_downtimes.values_num)
	{
		services_times_t	service_time_new;

		service_time_new.type = SERVICE_TIME_TYPE_UPTIME;
		service_time_new.from = 0;
		service_time_new.to = SEC_PER_WEEK;
		service_time_new.note = zbx_strdup(NULL, "");

		zbx_vector_services_times_append(services_times, service_time_new);
	}

	for (i = 0; i < services_downtimes.values_num; i++)
	{
		services_times_t	*service_downtime = &services_downtimes.values[i];

		for (j = 0; j < services_times->values_num; j++)
		{
			services_times_t	*service_time = &services_times->values[j];

			if (SERVICE_TIME_TYPE_UPTIME != service_time->type)
				continue;

			if (service_time->from <= service_downtime->to && service_time->to >= service_downtime->from)
			{
				if (service_time->from < service_downtime->from)
				{
					if (service_time->to > service_downtime->to)
					{
						services_times_t	service_time_new;

						service_time_new.type = SERVICE_TIME_TYPE_UPTIME;
						service_time_new.from = service_downtime->to;
						service_time_new.to = service_time->to;
						service_time_new.note = zbx_strdup(NULL, "");

						zbx_vector_services_times_append(services_times, service_time_new);
					}

					service_time->to = service_downtime->from;
				}
				else
				{
					if (service_time->to <= service_downtime->to)
					{
						services_time_clean(service_time);
						zbx_vector_services_times_remove(services_times, j);
						j--;
					}
					else
						service_time->from = service_downtime->to;
				}
			}
		}
	}

	for (i = 0; i < services_times->values_num; i++)
	{
		services_times_t	*service_time = &services_times->values[i];

		if (SERVICE_TIME_TYPE_UPTIME != service_time->type)
			continue;

		for (j = 0; j < services_times->values_num; j++)
		{
			services_times_t	*service_time_next = &services_times->values[j];

			if (SERVICE_TIME_TYPE_UPTIME != service_time_next->type)
				continue;

			if (service_time_next->from <= service_time->to &&
					service_time_next->to >= service_time->from && i != j)
			{
				service_time_next->from = MIN(service_time_next->from, service_time->from);
				service_time_next->to = MAX(service_time_next->to, service_time->to);

				services_time_clean(service_time);
				zbx_vector_services_times_remove(services_times, i);
				i--;
				break;
			}
		}
	}

	for (i = 0; i < services_downtimes.values_num; i++)
		services_time_clean(&services_downtimes.values[i]);

	zbx_vector_services_times_destroy(&services_downtimes);
}

static int	DBpatch_5050128(void)
{
	DB_RESULT		result;
	DB_ROW			row;
	zbx_uint64_t		last_serviceid = 0;
	zbx_vector_sla_t	slas, uniq_slas;
	int			i, j, ret;
	char			*default_timezone;
	sla_t			*sla;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	zbx_vector_sla_create(&slas);
	zbx_vector_sla_create(&uniq_slas);

	result = DBselect(
			"select s.serviceid,s.showsla,s.goodsla,t.type,t.ts_from,t.ts_to,t.note"
			" from services s"
			" left join services_times t on s.serviceid=t.serviceid"
			" order by s.serviceid");

	while (NULL != (row = DBfetch(result)))
	{
		zbx_uint64_t	serviceid;

		ZBX_STR2UINT64(serviceid, row[0]);

		if (last_serviceid != serviceid)
		{
			sla = zbx_malloc(NULL, sizeof(sla_t));

			zbx_vector_services_times_create(&sla->services_times);
			zbx_vector_uint64_create(&sla->serviceids);

			sla->showsla = atoi(row[1]);
			sla->goodsla = atof(row[2]);

			zbx_vector_uint64_append(&sla->serviceids, serviceid);

			zbx_vector_sla_append(&slas, sla);
			last_serviceid = serviceid;
		}

		if (NULL != row[3])
		{
			services_times_t	service_time;

			service_time.type = atoi(row[3]);
			service_time.from = atoi(row[4]);
			service_time.to = atoi(row[5]);
			service_time.note = zbx_strdup(NULL, row[6]);

			zbx_vector_services_times_append(&sla->services_times, service_time);
		}
	}
	DBfree_result(result);

	for (i = 0; i < slas.values_num; i++)
	{
		services_times_convert_downtime(&slas.values[i]->services_times);
		zbx_vector_services_times_sort(&slas.values[i]->services_times, compare_services_time);
	}

	for (i = 0; i < slas.values_num; i++)
	{
		if (FAIL == (j = zbx_vector_sla_search(&uniq_slas, slas.values[i], compare_sla)))
		{
			zbx_vector_sla_append(&uniq_slas, slas.values[i]);
			zbx_vector_sla_remove_noorder(&slas, i);
			i--;
			continue;
		}

		zbx_vector_uint64_append(&uniq_slas.values[j]->serviceids, slas.values[i]->serviceids.values[0]);
	}

	for (i = 0; i < uniq_slas.values_num; i++)
		zbx_vector_uint64_sort(&uniq_slas.values[i]->serviceids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	result = DBselect("select default_timezone from config");
	if (NULL != (row = DBfetch(result)))
	{
		default_timezone = zbx_strdup(NULL, row[0]);
	}
	else
	{
		THIS_SHOULD_NEVER_HAPPEN;
		default_timezone = zbx_strdup(NULL, "UTC");
	}
	DBfree_result(result);

	ret = db_insert_sla(&uniq_slas, default_timezone);

	zbx_vector_sla_clear_ext(&slas, sla_clean);
	zbx_vector_sla_clear_ext(&uniq_slas, sla_clean);
	zbx_vector_sla_destroy(&slas);
	zbx_vector_sla_destroy(&uniq_slas);

	zbx_free(default_timezone);

	return ret;
}

static int	DBpatch_5050129(void)
{
	return DBdrop_table("services_times");
}

static int	DBpatch_5050130(void)
{
	return DBdrop_field("services", "showsla");
}

static int	DBpatch_5050131(void)
{
	return DBdrop_field("services", "goodsla");
}

static int	DBpatch_5050132(void)
{
	int		ret = SUCCEED;
	char		*uuid, *sql = NULL;
	size_t		sql_alloc = 0, sql_offset = 0;
	DB_ROW		row;
	DB_RESULT	result;

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	result = DBselect("select serviceid,name from services");

	while (NULL != (row = DBfetch(result)))
	{
		uuid = zbx_gen_uuid4(row[1]);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update services set uuid='%s' where serviceid=%s;\n",
				uuid, row[0]);
		zbx_free(uuid);

		if (SUCCEED != (ret = DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset)))
			goto out;
	}

	DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (16 < sql_offset && ZBX_DB_OK > DBexecute("%s", sql))
		ret = FAIL;
out:
	DBfree_result(result);
	zbx_free(sql);

	return ret;
}

static int	DBpatch_5050133(void)
{
	if (ZBX_DB_OK > DBexecute("update role_rule set name='ui.services.services' where name='ui.monitoring.services'"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_5050134(void)
{
	const ZBX_FIELD	field = {"value_serviceid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_field("widget_field", &field);
}

static int	DBpatch_5050135(void)
{
	return DBcreate_index("widget_field", "widget_field_7", "value_serviceid", 0);
}

static int	DBpatch_5050136(void)
{
	const ZBX_FIELD	field = {"value_serviceid", NULL, "services", "serviceid", 0, ZBX_TYPE_ID, 0,
			ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("widget_field", 7, &field);
}

static int	DBpatch_5050137(void)
{
	const ZBX_FIELD	field = {"value_slaid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_field("widget_field", &field);
}

static int	DBpatch_5050138(void)
{
	return DBcreate_index("widget_field", "widget_field_8", "value_slaid", 0);
}

static int	DBpatch_5050139(void)
{
	const ZBX_FIELD	field = {"value_slaid", NULL, "sla", "slaid", 0, ZBX_TYPE_ID, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("widget_field", 8, &field);
}

static int	DBpatch_5050140(void)
{
	const ZBX_FIELD	field = {"created_at", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("services", &field);
}

static int	DBpatch_5050141(void)
{
	if (ZBX_DB_OK <= DBexecute("update services set created_at=%d", SERVICE_INITIAL_EFFECTIVE_DATE))
		return SUCCEED;

	return FAIL;
}

static int	DBpatch_5050142(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("delete from profiles where idx like 'web.latest.filter.%%'"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_5050143(void)
{
	const ZBX_FIELD	old_field = {"parameters", "{}", NULL, NULL, 0, ZBX_TYPE_SHORTTEXT, ZBX_NOTNULL, 0};
	const ZBX_FIELD	field = {"parameters", "{}", NULL, NULL, 0, ZBX_TYPE_TEXT, ZBX_NOTNULL, 0};

	return DBmodify_field_type("alerts", &field, &old_field);
}

static int	DBpatch_5050144(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("delete from profiles where idx='web.charts.filter.search_type'"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_5050145(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("delete from profiles where idx='web.charts.filter.graphids'"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_5050146(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("delete from profiles where idx='web.charts.filter.graph_patterns'"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_5050147(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("delete from profiles where idx='web.favorite.graphids' and source='graphid'"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_5050148(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("update services set algorithm=case algorithm when 1 then 2 when 2 then 1 else 0 end"))
		return FAIL;

	return SUCCEED;
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
DBPATCH_ADD(5050055, 0, 1)
DBPATCH_ADD(5050056, 0, 1)
DBPATCH_ADD(5050057, 0, 1)
DBPATCH_ADD(5050058, 0, 1)
DBPATCH_ADD(5050059, 0, 1)
DBPATCH_ADD(5050060, 0, 1)
DBPATCH_ADD(5050061, 0, 1)
DBPATCH_ADD(5050062, 0, 1)
DBPATCH_ADD(5050063, 0, 1)
DBPATCH_ADD(5050064, 0, 1)
DBPATCH_ADD(5050065, 0, 1)
DBPATCH_ADD(5050066, 0, 1)
DBPATCH_ADD(5050067, 0, 1)
DBPATCH_ADD(5050068, 0, 1)
DBPATCH_ADD(5050070, 0, 1)
DBPATCH_ADD(5050071, 0, 1)
DBPATCH_ADD(5050072, 0, 1)
DBPATCH_ADD(5050073, 0, 1)
DBPATCH_ADD(5050074, 0, 1)
DBPATCH_ADD(5050075, 0, 1)
DBPATCH_ADD(5050076, 0, 1)
DBPATCH_ADD(5050077, 0, 1)
DBPATCH_ADD(5050078, 0, 1)
DBPATCH_ADD(5050079, 0, 1)
DBPATCH_ADD(5050080, 0, 1)
DBPATCH_ADD(5050081, 0, 1)
DBPATCH_ADD(5050082, 0, 1)
DBPATCH_ADD(5050083, 0, 1)
DBPATCH_ADD(5050084, 0, 1)
DBPATCH_ADD(5050085, 0, 1)
DBPATCH_ADD(5050086, 0, 1)
DBPATCH_ADD(5050088, 0, 1)
DBPATCH_ADD(5050089, 0, 1)
DBPATCH_ADD(5050090, 0, 1)
DBPATCH_ADD(5050091, 0, 1)
DBPATCH_ADD(5050092, 0, 1)
DBPATCH_ADD(5050093, 0, 1)
DBPATCH_ADD(5050094, 0, 1)
DBPATCH_ADD(5050095, 0, 1)
DBPATCH_ADD(5050096, 0, 1)
DBPATCH_ADD(5050097, 0, 1)
DBPATCH_ADD(5050098, 0, 1)
DBPATCH_ADD(5050099, 0, 1)
DBPATCH_ADD(5050100, 0, 1)
DBPATCH_ADD(5050101, 0, 1)
DBPATCH_ADD(5050102, 0, 1)
DBPATCH_ADD(5050103, 0, 1)
DBPATCH_ADD(5050104, 0, 1)
DBPATCH_ADD(5050105, 0, 1)
DBPATCH_ADD(5050106, 0, 1)
DBPATCH_ADD(5050107, 0, 1)
DBPATCH_ADD(5050108, 0, 1)
DBPATCH_ADD(5050109, 0, 1)
DBPATCH_ADD(5050110, 0, 1)
DBPATCH_ADD(5050111, 0, 1)
DBPATCH_ADD(5050112, 0, 1)
DBPATCH_ADD(5050113, 0, 1)
DBPATCH_ADD(5050114, 0, 1)
DBPATCH_ADD(5050115, 0, 1)
DBPATCH_ADD(5050116, 0, 1)
DBPATCH_ADD(5050117, 0, 1)
DBPATCH_ADD(5050118, 0, 1)
DBPATCH_ADD(5050119, 0, 1)
DBPATCH_ADD(5050120, 0, 1)
DBPATCH_ADD(5050121, 0, 1)
DBPATCH_ADD(5050122, 0, 1)
DBPATCH_ADD(5050123, 0, 1)
DBPATCH_ADD(5050124, 0, 1)
DBPATCH_ADD(5050125, 0, 1)
DBPATCH_ADD(5050126, 0, 1)
DBPATCH_ADD(5050127, 0, 1)
DBPATCH_ADD(5050128, 0, 1)
DBPATCH_ADD(5050129, 0, 1)
DBPATCH_ADD(5050130, 0, 1)
DBPATCH_ADD(5050131, 0, 1)
DBPATCH_ADD(5050132, 0, 1)
DBPATCH_ADD(5050133, 0, 1)
DBPATCH_ADD(5050134, 0, 1)
DBPATCH_ADD(5050135, 0, 1)
DBPATCH_ADD(5050136, 0, 1)
DBPATCH_ADD(5050137, 0, 1)
DBPATCH_ADD(5050138, 0, 1)
DBPATCH_ADD(5050139, 0, 1)
DBPATCH_ADD(5050140, 0, 1)
DBPATCH_ADD(5050141, 0, 1)
DBPATCH_ADD(5050142, 0, 1)
DBPATCH_ADD(5050143, 0, 1)
DBPATCH_ADD(5050144, 0, 1)
DBPATCH_ADD(5050145, 0, 1)
DBPATCH_ADD(5050146, 0, 1)
DBPATCH_ADD(5050147, 0, 1)
DBPATCH_ADD(5050148, 0, 1)

DBPATCH_END()
