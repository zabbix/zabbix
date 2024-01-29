/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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
#include "zbxeval.h"

extern unsigned char	program_type;

/*
 * 6.0 maintenance database patches
 */

#ifndef HAVE_SQLITE3

static int	DBpatch_6000000(void)
{
	return SUCCEED;
}

static int	DBpatch_6000001(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("delete from profiles where idx='web.auditlog.filter.action' and value_int=-1"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_6000002(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("update profiles set idx='web.auditlog.filter.actions' where"
			" idx='web.auditlog.filter.action'"))
	{
		return FAIL;
	}

	return SUCCEED;
}

#define HTTPSTEP_ITEM_TYPE_RSPCODE	0
#define HTTPSTEP_ITEM_TYPE_TIME		1
#define HTTPSTEP_ITEM_TYPE_IN		2
#define HTTPSTEP_ITEM_TYPE_LASTSTEP	3
#define HTTPSTEP_ITEM_TYPE_LASTERROR	4

static int	DBpatch_6000003(void)
{
	DB_ROW		row;
	DB_RESULT	result;
	int		ret = SUCCEED;
	char		*sql = NULL;
	size_t		sql_alloc = 0, sql_offset = 0, out_alloc = 0;
	char		*out = NULL;

	if (ZBX_PROGRAM_TYPE_SERVER != program_type)
		return SUCCEED;

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	result = DBselect(
			"select hi.itemid,hi.type,ht.name"
			" from httptestitem hi,httptest ht"
			" where hi.httptestid=ht.httptestid");

	while (SUCCEED == ret && NULL != (row = DBfetch(result)))
	{
		zbx_uint64_t	itemid;
		char		*esc;
		size_t		out_offset = 0;
		unsigned char	type;

		ZBX_STR2UINT64(itemid, row[0]);
		ZBX_STR2UCHAR(type, row[1]);

		switch (type)
		{
			case HTTPSTEP_ITEM_TYPE_IN:
				zbx_snprintf_alloc(&out, &out_alloc, &out_offset,
						"Download speed for scenario \"%s\".", row[2]);
				break;
			case HTTPSTEP_ITEM_TYPE_LASTSTEP:
				zbx_snprintf_alloc(&out, &out_alloc, &out_offset,
						"Failed step of scenario \"%s\".", row[2]);
				break;
			case HTTPSTEP_ITEM_TYPE_LASTERROR:
				zbx_snprintf_alloc(&out, &out_alloc, &out_offset,
						"Last error message of scenario \"%s\".", row[2]);
				break;
		}
		esc = DBdyn_escape_field("items", "name", out);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update items set name='%s' where itemid="
				ZBX_FS_UI64 ";\n", esc, itemid);
		zbx_free(esc);

		ret = DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
	}
	DBfree_result(result);

	DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (SUCCEED == ret && 16 < sql_offset)
	{
		if (ZBX_DB_OK > DBexecute("%s", sql))
			ret = FAIL;
	}

	zbx_free(sql);
	zbx_free(out);

	return ret;
}

static int	DBpatch_6000004(void)
{
	DB_ROW		row;
	DB_RESULT	result;
	int		ret = SUCCEED;
	char		*sql = NULL;
	size_t		sql_alloc = 0, sql_offset = 0, out_alloc = 0;
	char		*out = NULL;

	if (ZBX_PROGRAM_TYPE_SERVER != program_type)
		return SUCCEED;

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	result = DBselect(
			"select hi.itemid,hi.type,hs.name,ht.name"
			" from httpstepitem hi,httpstep hs,httptest ht"
			" where hi.httpstepid=hs.httpstepid"
				" and hs.httptestid=ht.httptestid");

	while (SUCCEED == ret && NULL != (row = DBfetch(result)))
	{
		zbx_uint64_t	itemid;
		char		*esc;
		size_t		out_offset = 0;
		unsigned char	type;

		ZBX_STR2UINT64(itemid, row[0]);
		ZBX_STR2UCHAR(type, row[1]);

		switch (type)
		{
			case HTTPSTEP_ITEM_TYPE_IN:
				zbx_snprintf_alloc(&out, &out_alloc, &out_offset,
						"Download speed for step \"%s\" of scenario \"%s\".", row[2], row[3]);
				break;
			case HTTPSTEP_ITEM_TYPE_TIME:
				zbx_snprintf_alloc(&out, &out_alloc, &out_offset,
						"Response time for step \"%s\" of scenario \"%s\".", row[2], row[3]);
				break;
			case HTTPSTEP_ITEM_TYPE_RSPCODE:
				zbx_snprintf_alloc(&out, &out_alloc, &out_offset,
						"Response code for step \"%s\" of scenario \"%s\".", row[2], row[3]);
				break;
		}

		esc = DBdyn_escape_field("items", "name", out);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update items set name='%s' where itemid="
				ZBX_FS_UI64 ";\n", esc, itemid);
		zbx_free(esc);

		ret = DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
	}
	DBfree_result(result);

	DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (SUCCEED == ret && 16 < sql_offset)
	{
		if (ZBX_DB_OK > DBexecute("%s", sql))
			ret = FAIL;
	}

	zbx_free(sql);
	zbx_free(out);

	return ret;
}

static int	DBpatch_6000005(void)
{
	const ZBX_FIELD	field = {"name", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("group_discovery", &field, NULL);
}

static int	DBpatch_6000006(void)
{
#if defined(HAVE_MYSQL) || defined(HAVE_POSTGRESQL)
#	define ZBX_DB_CHAR_LENGTH(str)	"char_length(" #str ")"
#else /* HAVE_ORACLE */
#	define ZBX_DB_CHAR_LENGTH(str)	"length(" #str ")"
#endif
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
#undef ZBX_DB_CHAR_LENGTH
}

#undef HTTPSTEP_ITEM_TYPE_RSPCODE
#undef HTTPSTEP_ITEM_TYPE_TIME
#undef HTTPSTEP_ITEM_TYPE_IN
#undef HTTPSTEP_ITEM_TYPE_LASTSTEP
#undef HTTPSTEP_ITEM_TYPE_LASTERROR

static int	DBpatch_6000007(void)
{
	const ZBX_FIELD	field = {"url", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("users", &field, NULL);
}

static int	DBpatch_6000008(void)
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

static int	DBpatch_6000009(void)
{
	if (SUCCEED == DBtrigger_exists("hosts", "hosts_name_upper_update"))
	{
		zabbix_log(LOG_LEVEL_WARNING, "hosts_name_upper_update trigger for table \"hosts\" already exists,"
				" skipping patch of adding index to \"name_upper\" column");
		return SUCCEED;
	}

	return DBcreate_index("hosts", "hosts_6", "name_upper", 0);
}

static int	DBpatch_6000010(void)
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

static int	DBpatch_6000011(void)
{
	if (SUCCEED == DBtrigger_exists("hosts", "hosts_name_upper_insert"))
	{
		zabbix_log(LOG_LEVEL_WARNING, "hosts_name_upper_insert trigger for table \"hosts\" already exists,"
				" skipping patch of adding it to \"hosts\" table");
		return SUCCEED;
	}

	return zbx_dbupgrade_attach_trigger_with_function_on_insert("hosts", "name", "name_upper", "upper", "hostid");
}

static int	DBpatch_6000012(void)
{
	if (SUCCEED == DBtrigger_exists("hosts", "hosts_name_upper_update"))
	{
		zabbix_log(LOG_LEVEL_WARNING, "hosts_name_upper_update trigger for table \"hosts\" already exists,"
				" skipping patch of adding it to \"hosts\" table");
		return SUCCEED;
	}

	return zbx_dbupgrade_attach_trigger_with_function_on_update("hosts", "name", "name_upper", "upper", "hostid");
}

static int	DBpatch_6000013(void)
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

static int	DBpatch_6000014(void)
{
	if (SUCCEED == DBtrigger_exists("items", "items_name_upper_update"))
	{
		zabbix_log(LOG_LEVEL_WARNING, "items_name_upper_update trigger for table \"items\" already exists,"
				" skipping patch of adding index to \"name_upper\" column");

		return SUCCEED;
	}

	return DBcreate_index("items", "items_9", "hostid,name_upper", 0);
}

static int	DBpatch_6000015(void)
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

static int	DBpatch_6000016(void)
{
	if (SUCCEED == DBtrigger_exists("items", "items_name_upper_insert"))
	{
		zabbix_log(LOG_LEVEL_WARNING, "items_name_upper_insert trigger for table \"items\" already exists,"
				" skipping patch of adding it to \"items\" table");
		return SUCCEED;
	}

	return zbx_dbupgrade_attach_trigger_with_function_on_insert("items", "name", "name_upper", "upper", "itemid");
}

static int	DBpatch_6000017(void)
{
	if (SUCCEED == DBtrigger_exists("items", "items_name_upper_update"))
	{
		zabbix_log(LOG_LEVEL_WARNING, "items_name_upper_update trigger for table \"items\" already exists,"
				" skipping patch of adding it to \"items\" table");
		return SUCCEED;
	}

	return zbx_dbupgrade_attach_trigger_with_function_on_update("items", "name", "name_upper", "upper", "itemid");
}

static int	DBpatch_6000018(void)
{
	const ZBX_FIELD	old_field = {"info", "", NULL, NULL, 0, ZBX_TYPE_TEXT, ZBX_NOTNULL, 0};
	const ZBX_FIELD	field = {"info", "", NULL, NULL, 0, ZBX_TYPE_LONGTEXT, ZBX_NOTNULL, 0};

	return DBmodify_field_type("task_result", &field, &old_field);
}

static int	DBpatch_6000019(void)
{
	return DBdrop_index("scripts", "scripts_3");
}

static int	DBpatch_6000020(void)
{
	return DBcreate_index("scripts", "scripts_3", "name,menu_path", 1);
}

static int	DBpatch_6000021(void)
{
	return DBcreate_index("dashboard_user", "dashboard_user_2", "userid", 0);
}

static int	DBpatch_6000022(void)
{
	return DBcreate_index("dashboard_usrgrp", "dashboard_usrgrp_2", "usrgrpid", 0);
}

static int	DBpatch_6000023(void)
{
	return DBcreate_index("group_discovery", "group_discovery_1", "parent_group_prototypeid", 0);
}

static int	DBpatch_6000024(void)
{
	return DBcreate_index("group_prototype", "group_prototype_2", "groupid", 0);
}

static int	DBpatch_6000025(void)
{
	return DBcreate_index("group_prototype", "group_prototype_3", "templateid", 0);
}

static int	DBpatch_6000026(void)
{
	return DBcreate_index("host_discovery", "host_discovery_1", "parent_hostid", 0);
}

static int	DBpatch_6000027(void)
{
	return DBcreate_index("host_discovery", "host_discovery_2", "parent_itemid", 0);
}

static int	DBpatch_6000028(void)
{
	return DBcreate_index("hosts", "hosts_7", "templateid", 0);
}

static int	DBpatch_6000029(void)
{
	return DBcreate_index("interface_discovery", "interface_discovery_1", "parent_interfaceid", 0);
}

static int	DBpatch_6000030(void)
{
	return DBcreate_index("report", "report_2", "userid", 0);
}

static int	DBpatch_6000031(void)
{
	return DBcreate_index("report", "report_3", "dashboardid", 0);
}

static int	DBpatch_6000032(void)
{
	return DBcreate_index("report_user", "report_user_2", "userid", 0);
}

static int	DBpatch_6000033(void)
{
	return DBcreate_index("report_user", "report_user_3", "access_userid", 0);
}

static int	DBpatch_6000034(void)
{
	return DBcreate_index("report_usrgrp", "report_usrgrp_2", "usrgrpid", 0);
}

static int	DBpatch_6000035(void)
{
	return DBcreate_index("report_usrgrp", "report_usrgrp_3", "access_userid", 0);
}

static int	DBpatch_6000036(void)
{
	return DBcreate_index("sysmaps", "sysmaps_4", "userid", 0);
}

static int	DBpatch_6000037(void)
{
	return DBcreate_index("sysmap_element_trigger", "sysmap_element_trigger_2", "triggerid", 0);
}

static int	DBpatch_6000038(void)
{
	return DBcreate_index("sysmap_user", "sysmap_user_2", "userid", 0);
}

static int	DBpatch_6000039(void)
{
	return DBcreate_index("sysmap_usrgrp", "sysmap_usrgrp_2", "usrgrpid", 0);
}

static int	DBpatch_6000040(void)
{
	return DBcreate_index("tag_filter", "tag_filter_1", "usrgrpid", 0);
}

static int	DBpatch_6000041(void)
{
	return DBcreate_index("tag_filter", "tag_filter_2", "groupid", 0);
}

static int	DBpatch_6000042(void)
{
	return DBcreate_index("task", "task_2", "proxy_hostid", 0);
}

static int	DBpatch_6000043(void)
{
	return DBcreate_index("users", "users_3", "roleid", 0);
}

static int	DBpatch_6000044(void)
{
/* -------------------------------------------------------*/
/* Formula:                                               */
/* aggregate_function(last_foreach(filter))               */
/* aggregate_function(last_foreach(filter,time))          */
/*--------------------------------------------------------*/
/* Relative positioning of tokens on a stack              */
/*----------------------------+---------------------------*/
/* Time is present in formula | Time is absent in formula */
/*----------------------------+---------------------------*/
/* [i-2] filter               |                           */
/* [i-1] time                 | [i-1] filter              */
/*   [i] last_foreach         |   [i] last_foreach        */
/* [i+2] aggregate function   | [i+2]                     */
/*----------------------------+---------------------------*/

/* Offset in stack of tokens is relative to last_foreach() history function token, */
/* assuming that time is present in formula. */
#define OFFSET_TIME	(-1)
#define TOKEN_LEN(loc)	(loc->r - loc->l + 1)
#define LAST_FOREACH	"last_foreach"
	DB_ROW			row;
	DB_RESULT		result;
	int			ret = SUCCEED;
	size_t			sql_alloc = 0, sql_offset = 0;
	char			*sql = NULL, *params = NULL;
	zbx_eval_context_t	ctx;
	zbx_vector_uint64_t	del_idx;

	zbx_eval_init(&ctx);
	zbx_vector_uint64_create(&del_idx);

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	/* ITEM_TYPE_CALCULATED = 15 */
	result = DBselect("select itemid,params from items where type=15 and params like '%%%s%%'", LAST_FOREACH);

	while (SUCCEED == ret && NULL != (row = DBfetch(result)))
	{
		int	i;
		char	*esc, *error = NULL;

		zbx_eval_clear(&ctx);

		if (FAIL == zbx_eval_parse_expression(&ctx, row[1], ZBX_EVAL_PARSE_CALC_EXPRESSION, &error))
		{
			zabbix_log(LOG_LEVEL_WARNING, "%s: error parsing calculated item formula '%s' for itemid %s",
					__func__, row[1], row[0]);
			zbx_free(error);
			continue;
		}

		zbx_vector_uint64_clear(&del_idx);

		for (i = 0; i < ctx.stack.values_num; i++)
		{
			int		sec;
			zbx_strloc_t	*loc;

			if (ZBX_EVAL_TOKEN_HIST_FUNCTION != ctx.stack.values[i].type)
				continue;

			loc = &ctx.stack.values[i].loc;

			if (0 != strncmp(LAST_FOREACH, &ctx.expression[loc->l], TOKEN_LEN(loc)))
				continue;

			/* if time is absent in formula */
			if (ZBX_EVAL_TOKEN_ARG_QUERY == ctx.stack.values[i + OFFSET_TIME].type)
				continue;

			if (ZBX_EVAL_TOKEN_ARG_NULL == ctx.stack.values[i + OFFSET_TIME].type)
				continue;

			loc = &ctx.stack.values[i + OFFSET_TIME].loc;

			if (FAIL == is_time_suffix(&ctx.expression[loc->l], &sec, (int)TOKEN_LEN(loc)) || 0 != sec)
			{
				continue;
			}

			zbx_vector_uint64_append(&del_idx, (zbx_uint32_t)(i + OFFSET_TIME));
		}

		if (0 == del_idx.values_num)
			continue;

		params = zbx_strdup(params, ctx.expression);

		for (i = del_idx.values_num - 1; i >= 0; i--)
		{
			size_t		l, r;
			zbx_strloc_t	*loc = &ctx.stack.values[(int)del_idx.values[i]].loc;

			for (l = loc->l - 1; ',' != params[l]; l--) {}
			for (r = loc->r + 1; ')' != params[r]; r++) {}

			memmove(&params[l], &params[r], strlen(params) - r + 1);
		}

		esc = DBdyn_escape_string(params);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"update items set params='%s' where itemid=%s;\n", esc, row[0]);
		zbx_free(esc);

		ret = DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
	}
	DBfree_result(result);

	DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (SUCCEED == ret && 16 < sql_offset)
	{
		if (ZBX_DB_OK > DBexecute("%s", sql))
			ret = FAIL;
	}

	zbx_eval_clear(&ctx);
	zbx_vector_uint64_destroy(&del_idx);

	zbx_free(sql);
	zbx_free(params);

	return ret;
#undef OFFSET_TIME
#undef TOKEN_LEN
#undef LAST_FOREACH
}
#endif

DBPATCH_START(6000)

/* version, duplicates flag, mandatory flag */

DBPATCH_ADD(6000000, 0, 1)
DBPATCH_ADD(6000001, 0, 0)
DBPATCH_ADD(6000002, 0, 0)
DBPATCH_ADD(6000003, 0, 0)
DBPATCH_ADD(6000004, 0, 0)
DBPATCH_ADD(6000005, 0, 0)
DBPATCH_ADD(6000006, 0, 0)
DBPATCH_ADD(6000007, 0, 0)
DBPATCH_ADD(6000008, 0, 0)
DBPATCH_ADD(6000009, 0, 0)
DBPATCH_ADD(6000010, 0, 0)
DBPATCH_ADD(6000011, 0, 0)
DBPATCH_ADD(6000012, 0, 0)
DBPATCH_ADD(6000013, 0, 0)
DBPATCH_ADD(6000014, 0, 0)
DBPATCH_ADD(6000015, 0, 0)
DBPATCH_ADD(6000016, 0, 0)
DBPATCH_ADD(6000017, 0, 0)
DBPATCH_ADD(6000018, 0, 0)
DBPATCH_ADD(6000019, 0, 0)
DBPATCH_ADD(6000020, 0, 0)
DBPATCH_ADD(6000021, 0, 0)
DBPATCH_ADD(6000022, 0, 0)
DBPATCH_ADD(6000023, 0, 0)
DBPATCH_ADD(6000024, 0, 0)
DBPATCH_ADD(6000025, 0, 0)
DBPATCH_ADD(6000026, 0, 0)
DBPATCH_ADD(6000027, 0, 0)
DBPATCH_ADD(6000028, 0, 0)
DBPATCH_ADD(6000029, 0, 0)
DBPATCH_ADD(6000030, 0, 0)
DBPATCH_ADD(6000031, 0, 0)
DBPATCH_ADD(6000032, 0, 0)
DBPATCH_ADD(6000033, 0, 0)
DBPATCH_ADD(6000034, 0, 0)
DBPATCH_ADD(6000035, 0, 0)
DBPATCH_ADD(6000036, 0, 0)
DBPATCH_ADD(6000037, 0, 0)
DBPATCH_ADD(6000038, 0, 0)
DBPATCH_ADD(6000039, 0, 0)
DBPATCH_ADD(6000040, 0, 0)
DBPATCH_ADD(6000041, 0, 0)
DBPATCH_ADD(6000042, 0, 0)
DBPATCH_ADD(6000043, 0, 0)
DBPATCH_ADD(6000044, 0, 0)

DBPATCH_END()
