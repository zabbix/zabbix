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
#include "log.h"
#include "db.h"
#include "dbupgrade.h"
#include "zbxserver.h"

/*
 * 5.4 development database patches
 */

#define MIN_TEMPLATE_NAME_LEN 5

#ifndef HAVE_SQLITE3

extern unsigned char	program_type;

static int	DBpatch_5030000(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("delete from profiles where idx='web.queue.config'"))
		return FAIL;

	return SUCCEED;
}

typedef struct
{
	zbx_uint64_t	id;
	zbx_uint64_t	userid;
	char		*idx;
	zbx_uint64_t	idx2;
	zbx_uint64_t	value_id;
	int		value_int;
	char		*value_str;
	char		*source;
	int		type;
}
zbx_dbpatch_profile_t;

static void	DBpatch_get_key_fields(DB_ROW row, zbx_dbpatch_profile_t *profile, char **subsect, char **field, char **key)
{
	int	tok_idx = 0;
	char	*token;

	ZBX_DBROW2UINT64(profile->id, row[0]);
	ZBX_DBROW2UINT64(profile->userid, row[1]);
	profile->idx = zbx_strdup(profile->idx, row[2]);
	ZBX_DBROW2UINT64(profile->idx2, row[3]);
	ZBX_DBROW2UINT64(profile->value_id, row[4]);
	profile->value_int = atoi(row[5]);
	profile->value_str = zbx_strdup(profile->value_str, row[6]);
	profile->source = zbx_strdup(profile->source, row[7]);
	profile->type = atoi(row[8]);

	token = strtok(profile->idx, ".");

	while (NULL != token)
	{
		token = strtok(NULL, ".");
		tok_idx++;

		if (1 == tok_idx)
		{
			*subsect = zbx_strdup(*subsect, token);
		}
		else if (2 == tok_idx)
		{
			*key = zbx_strdup(*key, token);
		}
		else if (3 == tok_idx)
		{
			*field = zbx_strdup(*field, token);
			break;
		}
	}
}

static int	DBpatch_5030001(void)
{
	int		i, ret = SUCCEED;
	const char	*keys[] =
	{
		"web.items.php.sort",
		"web.items.php.sortorder",
		"web.triggers.php.sort",
		"web.triggers.php.sortorder",
		"web.graphs.php.sort",
		"web.graphs.php.sortorder",
		"web.host_discovery.php.sort",
		"web.host_discovery.php.sortorder",
		"web.httpconf.php.sort",
		"web.httpconf.php.sortorder",
		"web.disc_prototypes.php.sort",
		"web.disc_prototypes.php.sortorder",
		"web.trigger_prototypes.php.sort",
		"web.trigger_prototypes.php.sortorder",
		"web.host_prototypes.php.sort",
		"web.host_prototypes.php.sortorder"
	};

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	for (i = 0; SUCCEED == ret && i < (int)ARRSIZE(keys); i++)
	{
		char			*subsect = NULL, *field = NULL, *key = NULL;
		DB_ROW			row;
		DB_RESULT		result;
		zbx_dbpatch_profile_t	profile = {0};

		result = DBselect("select profileid,userid,idx,idx2,value_id,value_int,value_str,source,type"
				" from profiles where idx='%s'", keys[i]);

		if (NULL == (row = DBfetch(result)))
		{
			DBfree_result(result);
			continue;
		}

		DBpatch_get_key_fields(row, &profile, &subsect, &field, &key);

		DBfree_result(result);

		if (NULL == subsect || NULL == field || NULL == key)
		{
			zabbix_log(LOG_LEVEL_ERR, "failed to parse profile key fields for key '%s'", keys[i]);
			ret = FAIL;
		}

		if (SUCCEED == ret && ZBX_DB_OK > DBexecute("insert into profiles "
				"(profileid,userid,idx,idx2,value_id,value_int,value_str,source,type) values "
				"(" ZBX_FS_UI64 "," ZBX_FS_UI64 ",'web.hosts.%s.%s.%s'," ZBX_FS_UI64 ","
				ZBX_FS_UI64 ",%d,'%s','%s',%d)",
				DBget_maxid("profiles"), profile.userid, subsect, key, field, profile.idx2, profile.value_id,
				profile.value_int, profile.value_str, profile.source, profile.type))
		{
			ret = FAIL;
		}

		if (SUCCEED == ret && ZBX_DB_OK > DBexecute("insert into profiles "
				"(profileid,userid,idx,idx2,value_id,value_int,value_str,source,type) values "
				"(" ZBX_FS_UI64 "," ZBX_FS_UI64 ",'web.templates.%s.%s.%s'," ZBX_FS_UI64 ","
				ZBX_FS_UI64 ",%d,'%s','%s',%d)",
				DBget_maxid("profiles"), profile.userid, subsect, key, field, profile.idx2, profile.value_id,
				profile.value_int, profile.value_str, profile.source, profile.type))
		{
			ret = FAIL;
		}

		if (SUCCEED == ret &&
				ZBX_DB_OK > DBexecute("delete from profiles where profileid=" ZBX_FS_UI64, profile.id))
		{
			ret = FAIL;
		}

		zbx_free(profile.idx);
		zbx_free(profile.value_str);
		zbx_free(profile.source);
		zbx_free(subsect);
		zbx_free(field);
		zbx_free(key);
	}

	return ret;
}

static int	DBpatch_5030002(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("delete from profiles where "
			"idx like 'web.items.%%filter%%' or "
			"idx like 'web.triggers.%%filter%%' or "
			"idx like 'web.graphs.%%filter%%' or "
			"idx like 'web.host_discovery.%%filter%%' or "
			"idx like 'web.httpconf.%%filter%%' or "
			"idx like 'web.disc_prototypes.%%filter%%' or "
			"idx like 'web.trigger_prototypes.%%filter%%' or "
			"idx like 'web.host_prototypes.%%filter%%'"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_5030003(void)
{
	int			ret = SUCCEED;
	char			*subsect = NULL, *field = NULL, *key = NULL;
	DB_ROW			row;
	DB_RESULT		result;
	zbx_dbpatch_profile_t	profile = {0};

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	result = DBselect("select profileid,userid,idx,idx2,value_id,value_int,value_str,source,type"
			" from profiles where idx in ('web.dashbrd.list.sort','web.dashbrd.list.sortorder')");

	while (NULL != (row = DBfetch(result)))
	{
		DBpatch_get_key_fields(row, &profile, &subsect, &field, &key);

		if (ZBX_DB_OK > DBexecute("insert into profiles "
				"(profileid,userid,idx,idx2,value_id,value_int,value_str,source,type) values "
				"(" ZBX_FS_UI64 "," ZBX_FS_UI64 ",'web.templates.%s.%s.%s'," ZBX_FS_UI64 ","
				ZBX_FS_UI64 ",%d,'%s','%s',%d)",
				DBget_maxid("profiles"), profile.userid, subsect, key, field, profile.idx2,
				profile.value_id, profile.value_int, profile.value_str, profile.source, profile.type))
		{
			ret = FAIL;
			break;
		}
	}

	DBfree_result(result);

	zbx_free(profile.idx);
	zbx_free(profile.value_str);
	zbx_free(profile.source);
	zbx_free(subsect);
	zbx_free(field);
	zbx_free(key);

	return ret;
}

static int	DBpatch_5030004(void)
{
	const ZBX_FIELD	field = {"available", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("interface", &field);
}

static int	DBpatch_5030005(void)
{
	const ZBX_FIELD	field = {"error", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("interface", &field);
}

static int	DBpatch_5030006(void)
{
	const ZBX_FIELD	field = {"errors_from", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("interface", &field);
}

static int	DBpatch_5030007(void)
{
	const ZBX_FIELD	field = {"disable_until", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("interface", &field);
}

static int	DBpatch_5030008(void)
{
	return DBdrop_field("hosts", "available");
}

static int	DBpatch_5030009(void)
{
	return DBdrop_field("hosts", "ipmi_available");
}

static int	DBpatch_5030010(void)
{
	return DBdrop_field("hosts", "snmp_available");
}

static int	DBpatch_5030011(void)
{
	return DBdrop_field("hosts", "jmx_available");
}

static int	DBpatch_5030012(void)
{
	return DBdrop_field("hosts", "disable_until");
}

static int	DBpatch_5030013(void)
{
	return DBdrop_field("hosts", "ipmi_disable_until");
}

static int	DBpatch_5030014(void)
{
	return DBdrop_field("hosts", "snmp_disable_until");
}

static int	DBpatch_5030015(void)
{
	return DBdrop_field("hosts", "jmx_disable_until");
}

static int	DBpatch_5030016(void)
{
	return DBdrop_field("hosts", "errors_from");
}

static int	DBpatch_5030017(void)
{
	return DBdrop_field("hosts", "ipmi_errors_from");
}

static int	DBpatch_5030018(void)
{
	return DBdrop_field("hosts", "snmp_errors_from");
}

static int	DBpatch_5030019(void)
{
	return DBdrop_field("hosts", "jmx_errors_from");
}

static int	DBpatch_5030020(void)
{
	return DBdrop_field("hosts", "error");
}

static int	DBpatch_5030021(void)
{
	return DBdrop_field("hosts", "ipmi_error");
}

static int	DBpatch_5030022(void)
{
	return DBdrop_field("hosts", "snmp_error");
}

static int	DBpatch_5030023(void)
{
	return DBdrop_field("hosts", "jmx_error");
}

static int	DBpatch_5030024(void)
{
	return DBcreate_index("interface", "interface_3", "available", 0);
}

static int	DBpatch_5030025(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("delete from profiles where idx='web.overview.type' or idx='web.actionconf.eventsource'"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_5030026(void)
{
	const ZBX_TABLE table =
		{"token", "tokenid", 0,
			{
				{"tokenid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
				{"name", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{"description", "", NULL, NULL, 0, ZBX_TYPE_SHORTTEXT, ZBX_NOTNULL, 0},
				{"userid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
				{"token", NULL, NULL, NULL, 128, ZBX_TYPE_CHAR, 0, 0},
				{"lastaccess", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
				{"status", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
				{"expires_at", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
				{"created_at", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
				{"creator_userid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0},
				{0}
			},
			NULL
		};

	return DBcreate_table(&table);
}

static int	DBpatch_5030027(void)
{
	return DBcreate_index("token", "token_1", "name", 0);
}

static int	DBpatch_5030028(void)
{
	return DBcreate_index("token", "token_2", "userid,name", 1);
}

static int	DBpatch_5030029(void)
{
	return DBcreate_index("token", "token_3", "token", 1);
}

static int	DBpatch_5030030(void)
{
	return DBcreate_index("token", "token_4", "creator_userid", 0);
}

static int	DBpatch_5030031(void)
{
	const ZBX_FIELD field = {"userid", NULL, "users", "userid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("token", 1, &field);
}

static int	DBpatch_5030032(void)
{
	const ZBX_FIELD field = {"creator_userid", NULL, "users", "userid", 0, 0, 0, 0};

	return DBadd_foreign_key("token", 2, &field);
}

static int	DBpatch_5030033(void)
{
	const ZBX_FIELD	field = {"timeout", "30s", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("scripts", &field);
}

static int	DBpatch_5030034(void)
{
	const ZBX_FIELD	old_field = {"command", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};
	const ZBX_FIELD	field = {"command", "", NULL, NULL, 0, ZBX_TYPE_TEXT, ZBX_NOTNULL, 0};

	return DBmodify_field_type("scripts", &field, &old_field);
}

static int	DBpatch_5030035(void)
{
	const ZBX_TABLE	table =
			{"script_param", "script_paramid", 0,
				{
					{"script_paramid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"scriptid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"name", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"value", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_5030036(void)
{
	const ZBX_FIELD	field = {"scriptid", NULL, "scripts", "scriptid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("script_param", 1, &field);
}

static int	DBpatch_5030037(void)
{
	return DBcreate_index("script_param", "script_param_1", "scriptid,name", 1);
}

static int	DBpatch_5030038(void)
{
	const ZBX_FIELD field = {"type", "5", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBset_default("scripts", &field);
}

static int	DBpatch_5030039(void)
{
	const ZBX_FIELD	field = {"uuid", "", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("items", &field);
}

static int	DBpatch_5030040(void)
{
	const ZBX_FIELD	field = {"uuid", "", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("hosts", &field);
}

static int	DBpatch_5030041(void)
{
	const ZBX_FIELD	field = {"uuid", "", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("triggers", &field);
}

static int	DBpatch_5030042(void)
{
	const ZBX_FIELD	field = {"uuid", "", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("dashboard", &field);
}

static int	DBpatch_5030043(void)
{
	const ZBX_FIELD	field = {"uuid", "", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("graphs", &field);
}

static int	DBpatch_5030044(void)
{
	const ZBX_FIELD	field = {"uuid", "", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("hstgrp", &field);
}

static int	DBpatch_5030045(void)
{
	const ZBX_FIELD	field = {"uuid", "", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("httptest", &field);
}

static int	DBpatch_5030046(void)
{
	const ZBX_FIELD	field = {"uuid", "", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("valuemap", &field);
}

static char *Update_template_name(char *old)
{
	char	*ptr, new[MAX_STRING_LEN];

	ptr = old;

	if (1 == sscanf(old, "Template %*[^ ] %s", new) && strlen(new) > MIN_TEMPLATE_NAME_LEN)
		ptr = zbx_strdup(ptr, new);

	return ptr;
}

static int	DBpatch_5030047(void)
{
	int			ret = SUCCEED;
	char			*name, *uuid, *sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	DB_ROW			row;
	DB_RESULT		result;

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	result = DBselect(
				"select h.hostid, h.name"
				" from hosts h"
				" where h.status=%d",
				HOST_STATUS_TEMPLATE);

	while (NULL != (row = DBfetch(result)))
	{
		name = DBdyn_escape_string(row[1]);
		name = Update_template_name(name);
		uuid = zbx_gen_uuid4(name);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update hosts set uuid='%s' where hostid=%s;\n",
				uuid, row[0]);
		zbx_free(name);
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

static int	DBpatch_5030048(void)
{
	int			ret = SUCCEED;
	char			*name, *key, *uuid, *sql = NULL, *value = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	DB_ROW			row;
	DB_RESULT		result;

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	result = DBselect(
				"select i.itemid, i.key_, h.name"
				" from items i"
				" left join hosts h on h.hostid = i.hostid"
				" where h.status=%d and i.flags in (%d , %d)",
				HOST_STATUS_TEMPLATE, ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_RULE);

	while (NULL != (row = DBfetch(result)))
	{
		name = DBdyn_escape_string(row[2]);
		name = Update_template_name(name);
		key = DBdyn_escape_string(row[1]);
		value = zbx_dsprintf(value, "%s%s", name, key);
		uuid = zbx_gen_uuid4(value);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update items set uuid ='%s' where itemid = %s;\n",
				uuid, row[0]);
		zbx_free(name);
		zbx_free(key);
		zbx_free(uuid);
		zbx_free(value);

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

static int	DBpatch_5030049(void)
{
	int		ret = SUCCEED;
	char		*name, *uuid, *sql = NULL, *value = NULL;
	size_t		sql_alloc = 0, sql_offset = 0, value_alloc = 0, value_offset = 0;
	DB_ROW		row;
	DB_RESULT	result;

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	result = DBselect(
			"select t.triggerid, t.description, t.expression, t.recovery_expression"
			" from triggers t"
			" where t.templateid is null and t.flags = %d",
			ZBX_FLAG_DISCOVERY_NORMAL);

	while (NULL != (row = DBfetch(result)))
	{
		const char	*pexpr, *pexpr_f;
		char		*expr, *pexpr_s;
		char		*expression = NULL;
		int		i;
		size_t		expression_alloc = 0,	expression_offset = 0;
		zbx_uint64_t 	functionid;
		DB_ROW		row2;
		DB_RESULT	result2;

		name = DBdyn_escape_string(row[1]);

		for (i = 0; i < 2; i++)
		{
			expr = DBdyn_escape_string(row[i + 2]);
			pexpr = pexpr_f = (const char *)expr;

			while (SUCCEED == get_N_functionid(pexpr, 1, &functionid, (const char **)&pexpr_s, &pexpr_f))
			{
				*pexpr_s = '\0';

				result2 = DBselect(
						"select h.name, i.key_, f.name, f.parameter"
						" from functions f"
						" left join items i on i.itemid = f.itemid"
						" join hosts h on h.hostid = i.hostid"
						" where f.functionid = " ZBX_FS_UI64,
						functionid);

				while (NULL != (row2 = DBfetch(result2)))
				{
					zbx_snprintf_alloc(&expression, &expression_alloc, &expression_offset,
							"%s{%s:%s.%s(%s)}",pexpr, row2[0], row2[1], row2[2], row2[3]);
					pexpr = pexpr_f;
				}

				DBfree_result(result2);
			}

			if (pexpr != expr)
				zbx_snprintf_alloc(&expression, &expression_alloc, &expression_offset, "%s", pexpr);

			zbx_free(expr);
		}

		zbx_snprintf_alloc(&value, &value_alloc, &value_offset,"%s", name);
		zbx_snprintf_alloc(&value, &value_alloc, &value_offset,"%s", expression);

		uuid = zbx_gen_uuid4(value);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update triggers set uuid='%s'"
				" where triggerid=%s;\n", uuid, row[0]);
		zbx_free(expression);
		zbx_free(name);
		zbx_free(uuid);
		zbx_free(value);

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

static int	DBpatch_5030050(void)
{
	int			ret = SUCCEED;
	char			*name, *host_name, *uuid, *sql = NULL, *value = NULL;
	size_t			sql_alloc = 0, sql_offset = 0, value_alloc = 0, value_offset = 0;
	DB_ROW			row;
	DB_RESULT		result;

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);
	result = DBselect(
			"select g.graphid, g.name"
			" from graphs g"
			" where g.templateid is null and g.flags = %d",
			ZBX_FLAG_DISCOVERY_NORMAL);

	while (NULL != (row = DBfetch(result)))
	{
		DB_ROW			row2;
		DB_RESULT		result2;

		name = DBdyn_escape_string(row[1]);
		zbx_snprintf_alloc(&value, &value_alloc, &value_offset,"%s", name);

		result2 = DBselect(
				"select h.name, h.status"
				" from graphs_items gi"
				" left join items i on i.itemid=gi.itemid"
				" join hosts h on h.hostid=i.hostid"
				" where gi.graphid = %s",
				row[0]);

		while (NULL != (row2 = DBfetch(result2)))
		{
			int status;

			status = atoi(row2[1]);
			host_name = DBdyn_escape_string(row2[0]);

			if (HOST_STATUS_TEMPLATE == status)
				host_name = Update_template_name(host_name);

			zbx_snprintf_alloc(&value, &value_alloc, &value_offset,"%s", host_name);
			zbx_free(host_name);
		}

		uuid = zbx_gen_uuid4(value);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update graphs set uuid='%s'"
				" where graphid=%s;\n", uuid, row[0]);
		zbx_free(name);
		zbx_free(uuid);
		zbx_free(value);

		DBfree_result(result2);

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

static int	DBpatch_5030051(void)
{
	int			ret = SUCCEED;
	char			*name, *dashboard, *uuid, *sql = NULL, *value = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	DB_ROW			row;
	DB_RESULT		result;

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	result = DBselect(
			"select d.dashboardid, d.name, h.name"
			" from dashboard d"
			" left join hosts h on h.hostid=d.templateid"
			" where h.status=%d",
			HOST_STATUS_TEMPLATE);

	while (NULL != (row = DBfetch(result)))
	{
		name = DBdyn_escape_string(row[2]);
		name = Update_template_name(name);
		dashboard = DBdyn_escape_string(row[1]);
		value = zbx_dsprintf(value, "%s%s", name, dashboard);
		uuid = zbx_gen_uuid4(value);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"update dashboard set uuid='%s' where dashboardid=%s;\n", uuid, row[0]);
		zbx_free(name);
		zbx_free(dashboard);
		zbx_free(uuid);
		zbx_free(value);

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

static int	DBpatch_5030052(void)
{
	int			ret = SUCCEED;
	char			*name, *httptest, *uuid, *sql = NULL, *value = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	DB_ROW			row;
	DB_RESULT		result;

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	result = DBselect(
			"select ht.httptestid, ht.name, h.name"
			" from httptest ht"
			" left join hosts h on h.hostid=ht.hostid"
			" where h.status=%d",
			HOST_STATUS_TEMPLATE);

	while (NULL != (row = DBfetch(result)))
	{
		name = DBdyn_escape_string(row[2]);
		name = Update_template_name(name);
		httptest = DBdyn_escape_string(row[1]);
		value = zbx_dsprintf(value, "%s%s", name, httptest);
		uuid = zbx_gen_uuid4(value);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"update httptest set uuid='%s' where httptestid=%s;\n", uuid, row[0]);
		zbx_free(name);
		zbx_free(httptest);
		zbx_free(uuid);
		zbx_free(value);

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

static int	DBpatch_5030053(void)
{
	int			ret = SUCCEED;
	char			*name, *valuemap, *uuid, *sql = NULL, *value = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	DB_ROW			row;
	DB_RESULT		result;

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	result = DBselect(
			"select v.valuemapid, v.name, h.name"
			" from valuemap v"
			" left join hosts h on h.hostid=v.hostid"
			" where h.status=%d",
			HOST_STATUS_TEMPLATE);

	while (NULL != (row = DBfetch(result)))
	{
		name = DBdyn_escape_string(row[2]);
		name = Update_template_name(name);
		valuemap = DBdyn_escape_string(row[1]);
		value = zbx_dsprintf(value, "%s%s", name, valuemap);
		uuid = zbx_gen_uuid4(value);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"update valuemap set uuid='%s' where valuemapid=%s;\n", uuid, row[0]);
		zbx_free(name);
		zbx_free(valuemap);
		zbx_free(uuid);
		zbx_free(value);

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

static int	DBpatch_5030054(void)
{
	int			ret = SUCCEED;
	char			*name, *uuid, *sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	DB_ROW			row;
	DB_RESULT		result;

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	result = DBselect(
			"select hg.groupid, hg.name"
			" from hstgrp hg");

	while (NULL != (row = DBfetch(result)))
	{
		name = DBdyn_escape_string(row[1]);
		uuid = zbx_gen_uuid4(name);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"update hstgrp set uuid='%s' where groupid=%s;\n", uuid, row[0]);
		zbx_free(name);
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

static int	DBpatch_5030055(void)
{
	int			ret = SUCCEED;
	char			*name, *key, *key_discovery, *uuid, *sql = NULL, *value = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	DB_ROW			row;
	DB_RESULT		result;

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	result = DBselect(
			"select i.itemid, i.key_, h.name, i2.key_"
			" from items i"
			" left join hosts h on h.hostid=i.hostid"
			" join item_discovery id on id.itemid=i.itemid"
			" join items i2 on id.parent_itemid=i2.itemid"
			" where h.status=%d and i.flags=%d",
			HOST_STATUS_TEMPLATE, ZBX_FLAG_DISCOVERY_PROTOTYPE);

	while (NULL != (row = DBfetch(result)))
	{
		name = DBdyn_escape_string(row[2]);
		name = Update_template_name(name);
		key = DBdyn_escape_string(row[1]);
		key_discovery = DBdyn_escape_string(row[3]);
		value = zbx_dsprintf(value, "%s%s%s", name, key_discovery, key);
		uuid = zbx_gen_uuid4(value);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update items set uuid='%s' where itemid=%s;\n",
				uuid, row[0]);
		zbx_free(name);
		zbx_free(key);
		zbx_free(key_discovery);
		zbx_free(uuid);
		zbx_free(value);

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

static int	DBpatch_5030056(void)
{
	int		ret = SUCCEED;
	char		*name, *uuid, *sql = NULL, *value = NULL;
	size_t		sql_alloc = 0, sql_offset = 0, value_alloc = 0, value_offset = 0;
	DB_ROW		row;
	DB_RESULT	result;

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	result = DBselect(
			"select t.triggerid, t.description, t.expression, t.recovery_expression"
			" from triggers t"
			" where t.templateid is null and t.flags=%d",
			ZBX_FLAG_DISCOVERY_PROTOTYPE);

	while (NULL != (row = DBfetch(result)))
	{
		const char	*pexpr, *pexpr_f;
		char		*expr, *pexpr_s;
		char		*expression = NULL;
		int		i;
		size_t		expression_alloc = 0,	expression_offset = 0;
		zbx_uint64_t 	functionid;
		DB_ROW		row2;
		DB_RESULT	result2;

		result2 = DBselect(
				"select i2.key_"
				" from items i"
				" join hosts h on h.hostid=i.hostid and h.status=%d"
				" left join item_discovery id on id.itemid=i.itemid"
				" join items i2 on id.parent_itemid=i2.itemid"
				" join functions f on i.itemid=f.itemid and f.triggerid=%s"
				" where i.flags=%d",
				HOST_STATUS_TEMPLATE, row[0], ZBX_FLAG_DISCOVERY_PROTOTYPE);

		if (NULL == (row2 = DBfetch(result2)))
		{
			DBfree_result(result2);
			continue;
		}

		zbx_snprintf_alloc(&value, &value_alloc, &value_offset,"%s", row2[0]);

		DBfree_result(result2);

		name = DBdyn_escape_string(row[1]);

		for (i = 0; i < 2; i++)
		{
			expr = DBdyn_escape_string(row[i + 2]);
			pexpr = pexpr_f = (const char *)expr;

			while (SUCCEED == get_N_functionid(pexpr, 1, &functionid, (const char **)&pexpr_s, &pexpr_f))
			{
				*pexpr_s = '\0';

				result2 = DBselect(
						"select h.name, i.key_, f.name, f.parameter"
						" from functions f"
						" left join items i on i.itemid=f.itemid"
						" join hosts h on h.hostid=i.hostid"
						" where f.functionid=" ZBX_FS_UI64,
						functionid);

				while (NULL != (row2 = DBfetch(result2)))
				{
					zbx_snprintf_alloc(&expression, &expression_alloc, &expression_offset,
							"%s{%s:%s.%s(%s)}",pexpr, row2[0], row2[1], row2[2], row2[3]);
					pexpr = pexpr_f;
				}

				DBfree_result(result2);
			}

			if (pexpr != expr)
				zbx_snprintf_alloc(&expression, &expression_alloc, &expression_offset, "%s", pexpr);

			zbx_free(expr);
		}

		zbx_snprintf_alloc(&value, &value_alloc, &value_offset,"%s", name);
		zbx_snprintf_alloc(&value, &value_alloc, &value_offset,"%s", expression);

		uuid = zbx_gen_uuid4(value);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update triggers set uuid='%s'"
				" where triggerid=%s;\n", uuid, row[0]);
		zbx_free(expression);
		zbx_free(name);
		zbx_free(uuid);
		zbx_free(value);

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

static int	DBpatch_5030057(void)
{
	int			ret = SUCCEED;
	char			*name, *templ_name, *key, *uuid, *sql = NULL, *value = NULL;
	size_t			sql_alloc = 0, sql_offset = 0, value_alloc = 0, value_offset = 0;
	DB_ROW			row;
	DB_RESULT		result;

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);
	result = DBselect(
			"select distinct g.graphid, g.name, h.name, i2.key_"
			" from graphs g"
			" left join graphs_items gi on gi.graphid=g.graphid"
			" join items i on i.itemid=gi.itemid and i.flags=%d"
			" join hosts h on h.hostid=i.hostid and h.status=%d"
			" join item_discovery id on id.itemid=i.itemid"
			" join items i2 on id.parent_itemid=i2.itemid"
			" where g.templateid is null and g.flags=%d",
			ZBX_FLAG_DISCOVERY_PROTOTYPE, HOST_STATUS_TEMPLATE, ZBX_FLAG_DISCOVERY_PROTOTYPE);

	while (NULL != (row = DBfetch(result)))
	{
		name = DBdyn_escape_string(row[1]);
		templ_name = DBdyn_escape_string(row[2]);
		templ_name = Update_template_name(templ_name);
		key = DBdyn_escape_string(row[3]);
		zbx_snprintf_alloc(&value, &value_alloc, &value_offset,"%s%s%s", templ_name, key, name);

		uuid = zbx_gen_uuid4(value);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update graphs set uuid='%s'"
				" where graphid=%s;\n", uuid, row[0]);
		zbx_free(name);
		zbx_free(templ_name);
		zbx_free(key);
		zbx_free(uuid);
		zbx_free(value);

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

static int	DBpatch_5030058(void)
{
	int			ret = SUCCEED;
	char			*name, *name_tmpl, *key, *uuid, *value = NULL, *sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	DB_ROW			row;
	DB_RESULT		result;

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	result = DBselect(
			"select h.hostid, h.name, h2.name, i.key_"
			" from hosts h"
			" left join host_discovery hd on hd.hostid=h.hostid"
			" join items i on i.itemid=hd.parent_itemid"
			" join hosts h2 on h2.hostid=i.hostid and h2.status=%d"
			" where h.flags=%d and h.templateid is null",
			HOST_STATUS_TEMPLATE, ZBX_FLAG_DISCOVERY_PROTOTYPE);

	while (NULL != (row = DBfetch(result)))
	{
		name = DBdyn_escape_string(row[1]);
		name_tmpl = DBdyn_escape_string(row[2]);
		name_tmpl = Update_template_name(name_tmpl);
		key = DBdyn_escape_string(row[3]);
		value = zbx_dsprintf(value, "%s%s%s", name_tmpl, key, name);
		uuid = zbx_gen_uuid4(value);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update hosts set uuid='%s' where hostid=%s;\n",
				uuid, row[0]);
		zbx_free(name);
		zbx_free(name_tmpl);
		zbx_free(key);
		zbx_free(value);
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
#endif

DBPATCH_START(5030)

/* version, duplicates flag, mandatory flag */

DBPATCH_ADD(5030000, 0, 1)
DBPATCH_ADD(5030001, 0, 1)
DBPATCH_ADD(5030002, 0, 1)
DBPATCH_ADD(5030003, 0, 1)
DBPATCH_ADD(5030004, 0, 1)
DBPATCH_ADD(5030005, 0, 1)
DBPATCH_ADD(5030006, 0, 1)
DBPATCH_ADD(5030007, 0, 1)
DBPATCH_ADD(5030008, 0, 1)
DBPATCH_ADD(5030009, 0, 1)
DBPATCH_ADD(5030010, 0, 1)
DBPATCH_ADD(5030011, 0, 1)
DBPATCH_ADD(5030012, 0, 1)
DBPATCH_ADD(5030013, 0, 1)
DBPATCH_ADD(5030014, 0, 1)
DBPATCH_ADD(5030015, 0, 1)
DBPATCH_ADD(5030016, 0, 1)
DBPATCH_ADD(5030017, 0, 1)
DBPATCH_ADD(5030018, 0, 1)
DBPATCH_ADD(5030019, 0, 1)
DBPATCH_ADD(5030020, 0, 1)
DBPATCH_ADD(5030021, 0, 1)
DBPATCH_ADD(5030022, 0, 1)
DBPATCH_ADD(5030023, 0, 1)
DBPATCH_ADD(5030024, 0, 1)
DBPATCH_ADD(5030025, 0, 1)
DBPATCH_ADD(5030026, 0, 1)
DBPATCH_ADD(5030027, 0, 1)
DBPATCH_ADD(5030028, 0, 1)
DBPATCH_ADD(5030029, 0, 1)
DBPATCH_ADD(5030030, 0, 1)
DBPATCH_ADD(5030031, 0, 1)
DBPATCH_ADD(5030032, 0, 1)
DBPATCH_ADD(5030033, 0, 1)
DBPATCH_ADD(5030034, 0, 1)
DBPATCH_ADD(5030035, 0, 1)
DBPATCH_ADD(5030036, 0, 1)
DBPATCH_ADD(5030037, 0, 1)
DBPATCH_ADD(5030038, 0, 1)
DBPATCH_ADD(5030039, 0, 1)
DBPATCH_ADD(5030040, 0, 1)
DBPATCH_ADD(5030041, 0, 1)
DBPATCH_ADD(5030042, 0, 1)
DBPATCH_ADD(5030043, 0, 1)
DBPATCH_ADD(5030044, 0, 1)
DBPATCH_ADD(5030045, 0, 1)
DBPATCH_ADD(5030046, 0, 1)
DBPATCH_ADD(5030047, 0, 1)
DBPATCH_ADD(5030048, 0, 1)
DBPATCH_ADD(5030049, 0, 1)
DBPATCH_ADD(5030050, 0, 1)
DBPATCH_ADD(5030051, 0, 1)
DBPATCH_ADD(5030052, 0, 1)
DBPATCH_ADD(5030053, 0, 1)
DBPATCH_ADD(5030054, 0, 1)
DBPATCH_ADD(5030055, 0, 1)
DBPATCH_ADD(5030056, 0, 1)
DBPATCH_ADD(5030057, 0, 1)
DBPATCH_ADD(5030058, 0, 1)

DBPATCH_END()
