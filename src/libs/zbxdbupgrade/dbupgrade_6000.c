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
#include "log.h"

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

DBPATCH_END()
