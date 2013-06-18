/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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
#include "log.h"

#ifdef HAVE_MYSQL
#	define ZBX_DB_TABLE_OPTIONS	" engine=innodb"
#	define ZBX_DROP_FK		" drop foreign key"
#else
#	define ZBX_DB_TABLE_OPTIONS	""
#	define ZBX_DROP_FK		" drop constraint"
#endif

#ifdef HAVE_POSTGRESQL
#	define ZBX_DB_ONLY		" only"
#else
#	define ZBX_DB_ONLY		""
#endif

#if defined(HAVE_IBM_DB2)
#	define ZBX_DB_ALTER_COLUMN	" alter column"
#elif defined(HAVE_POSTGRESQL)
#	define ZBX_DB_ALTER_COLUMN	" alter"
#else
#	define ZBX_DB_ALTER_COLUMN	" modify"
#endif

#if defined(HAVE_IBM_DB2)
#	define ZBX_DB_SET_TYPE		" set data type"
#elif defined(HAVE_POSTGRESQL)
#	define ZBX_DB_SET_TYPE		" type"
#else
#	define ZBX_DB_SET_TYPE		""
#endif

#if defined(HAVE_IBM_DB2) || defined(HAVE_POSTGRESQL)
#	define ZBX_TYPE_ID_STR		"bigint"
#elif defined(HAVE_MYSQL)
#	define ZBX_TYPE_ID_STR		"bigint unsigned"
#elif defined(HAVE_ORACLE)
#	define ZBX_TYPE_ID_STR		"number(20)"
#endif

#ifdef HAVE_ORACLE
#	define ZBX_TYPE_INT_STR		"number(10)"
#	define ZBX_TYPE_CHAR_STR	"nvarchar2"
#else
#	define ZBX_TYPE_INT_STR		"integer"
#	define ZBX_TYPE_CHAR_STR	"varchar"
#endif

#if defined(HAVE_IBM_DB2)
#	define ZBX_TYPE_UINT_STR	"bigint"
#elif defined(HAVE_MYSQL)
#	define ZBX_TYPE_UINT_STR	"bigint unsigned"
#elif defined(HAVE_ORACLE)
#	define ZBX_TYPE_UINT_STR	"number(20)"
#elif defined(HAVE_POSTGRESQL)
#	define ZBX_TYPE_UINT_STR	"numeric(20)"
#endif

#if defined(HAVE_IBM_DB2)
#	define ZBX_TYPE_SHORTTEXT_STR	"varchar(2048)"
#elif defined(HAVE_ORACLE)
#	define ZBX_TYPE_SHORTTEXT_STR	"nvarchar2(2048)"
#else
#	define ZBX_TYPE_SHORTTEXT_STR	"text"
#endif

#define ZBX_FIRST_DB_VERSION		2010000

typedef struct
{
	int		(*function)();
	int		version;
	int		duplicates;
	unsigned char	mandatory;
}
zbx_dbpatch_t;

extern unsigned char	daemon_type;

#ifndef HAVE_SQLITE3
static void	DBfield_type_string(char **sql, size_t *sql_alloc, size_t *sql_offset, const ZBX_FIELD *field)
{
	switch (field->type)
	{
		case ZBX_TYPE_ID:
			zbx_strcpy_alloc(sql, sql_alloc, sql_offset, ZBX_TYPE_ID_STR);
			break;
		case ZBX_TYPE_INT:
			zbx_strcpy_alloc(sql, sql_alloc, sql_offset, ZBX_TYPE_INT_STR);
			break;
		case ZBX_TYPE_CHAR:
			zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%s(%hu)", ZBX_TYPE_CHAR_STR, field->length);
			break;
		case ZBX_TYPE_UINT:
			zbx_strcpy_alloc(sql, sql_alloc, sql_offset, ZBX_TYPE_UINT_STR);
			break;
		case ZBX_TYPE_SHORTTEXT:
			zbx_strcpy_alloc(sql, sql_alloc, sql_offset, ZBX_TYPE_SHORTTEXT_STR);
			break;
		default:
			assert(0);
	}
}

static void	DBfield_definition_string(char **sql, size_t *sql_alloc, size_t *sql_offset, const ZBX_FIELD *field)
{
	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%s ", field->name);
	DBfield_type_string(sql, sql_alloc, sql_offset, field);
	if (NULL != field->default_value)
	{
		char	*default_value_esc;

		default_value_esc = DBdyn_escape_string(field->default_value);
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, " default '%s'", default_value_esc);
		zbx_free(default_value_esc);
	}

	if (0 != (field->flags & ZBX_NOTNULL))
	{
#if defined(HAVE_ORACLE)
		switch (field->type)
		{
			case ZBX_TYPE_INT:
			case ZBX_TYPE_FLOAT:
			case ZBX_TYPE_BLOB:
			case ZBX_TYPE_UINT:
			case ZBX_TYPE_ID:
				zbx_strcpy_alloc(sql, sql_alloc, sql_offset, " not null");
				break;
			default:	/* ZBX_TYPE_CHAR, ZBX_TYPE_TEXT, ZBX_TYPE_SHORTTEXT or ZBX_TYPE_LONGTEXT */
				/* nothing to do */;
		}
#else
		zbx_strcpy_alloc(sql, sql_alloc, sql_offset, " not null");
#endif
	}
}

static void	DBcreate_table_sql(char **sql, size_t *sql_alloc, size_t *sql_offset, const ZBX_TABLE *table)
{
	int	i;

	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "create table %s (\n", table->table);

	for (i = 0; NULL != table->fields[i].name; i++)
	{
		if (0 != i)
			zbx_strcpy_alloc(sql, sql_alloc, sql_offset, ",\n");
		DBfield_definition_string(sql, sql_alloc, sql_offset, &table->fields[i]);
	}
	if ('\0' != *table->recid)
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, ",\nprimary key (%s)", table->recid);

	zbx_strcpy_alloc(sql, sql_alloc, sql_offset, "\n)" ZBX_DB_TABLE_OPTIONS);
}

static void	DBmodify_field_type_sql(char **sql, size_t *sql_alloc, size_t *sql_offset,
		const char *table_name, const ZBX_FIELD *field)
{
	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "alter table" ZBX_DB_ONLY " %s" ZBX_DB_ALTER_COLUMN " ",
			table_name);

#ifdef HAVE_MYSQL
	DBfield_definition_string(sql, sql_alloc, sql_offset, field);
#else
	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%s" ZBX_DB_SET_TYPE " ", field->name);
	DBfield_type_string(sql, sql_alloc, sql_offset, field);
#endif
}

static void	DBdrop_not_null_sql(char **sql, size_t *sql_alloc, size_t *sql_offset,
		const char *table_name, const ZBX_FIELD *field)
{
	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "alter table" ZBX_DB_ONLY " %s" ZBX_DB_ALTER_COLUMN " ",
			table_name);

#if defined(HAVE_MYSQL)
	DBfield_definition_string(sql, sql_alloc, sql_offset, field);
#elif defined(HAVE_ORACLE)
	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%s null", field->name);
#else
	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%s drop not null", field->name);
#endif
}

static void	DBset_not_null_sql(char **sql, size_t *sql_alloc, size_t *sql_offset,
		const char *table_name, const ZBX_FIELD *field)
{
	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "alter table" ZBX_DB_ONLY " %s" ZBX_DB_ALTER_COLUMN " ",
			table_name);

#if defined(HAVE_MYSQL)
	DBfield_definition_string(sql, sql_alloc, sql_offset, field);
#elif defined(HAVE_ORACLE)
	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%s not null", field->name);
#else
	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%s set not null", field->name);
#endif
}

static void	DBset_default_sql(char **sql, size_t *sql_alloc, size_t *sql_offset,
		const char *table_name, const ZBX_FIELD *field)
{
	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "alter table" ZBX_DB_ONLY " %s" ZBX_DB_ALTER_COLUMN " ",
			table_name);

#if defined(HAVE_MYSQL)
	DBfield_definition_string(sql, sql_alloc, sql_offset, field);
#elif defined(HAVE_ORACLE)
	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%s default '%s'", field->name, field->default_value);
#else
	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%s set default '%s'", field->name, field->default_value);
#endif
}

static void	DBadd_field_sql(char **sql, size_t *sql_alloc, size_t *sql_offset,
		const char *table_name, const ZBX_FIELD *field)
{
	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "alter table" ZBX_DB_ONLY " %s add ", table_name);
	DBfield_definition_string(sql, sql_alloc, sql_offset, field);
}

static void	DBrename_field_sql(char **sql, size_t *sql_alloc, size_t *sql_offset,
		const char *table_name, const char *field_name, const ZBX_FIELD *field)
{
	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "alter table" ZBX_DB_ONLY " %s ", table_name);

#ifdef HAVE_MYSQL
	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "change column %s ", field_name);
	DBfield_definition_string(sql, sql_alloc, sql_offset, field);
#else
	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "rename column %s to %s", field_name, field->name);
#endif
}

static void	DBdrop_field_sql(char **sql, size_t *sql_alloc, size_t *sql_offset,
		const char *table_name, const char *field_name)
{
	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "alter table" ZBX_DB_ONLY " %s drop column %s",
			table_name, field_name);
}

static void	DBcreate_index_sql(char **sql, size_t *sql_alloc, size_t *sql_offset,
		const char *table_name, const char *index_name, const char *fields, int unique)
{
	zbx_strcpy_alloc(sql, sql_alloc, sql_offset, "create");
	if (0 != unique)
		zbx_strcpy_alloc(sql, sql_alloc, sql_offset, " unique");
	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, " index %s on %s (%s)", index_name, table_name, fields);
}

static void	DBdrop_index_sql(char **sql, size_t *sql_alloc, size_t *sql_offset,
		const char *table_name, const char *index_name)
{
	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "drop index %s", index_name);
#ifdef HAVE_MYSQL
	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, " on %s", table_name);
#endif
}

static void	DBadd_foreign_key_sql(char **sql, size_t *sql_alloc, size_t *sql_offset,
		const char *table_name, int id, const ZBX_FIELD *field)
{
	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "alter table" ZBX_DB_ONLY " %s"
			" add constraint c_%s_%d foreign key (%s) references %s (%s)",
			table_name, table_name, id, field->name, field->fk_table, field->fk_field);
	if (0 != (field->fk_flags & ZBX_FK_CASCADE_DELETE))
		zbx_strcpy_alloc(sql, sql_alloc, sql_offset, " on delete cascade");
}

static void	DBdrop_foreign_key_sql(char **sql, size_t *sql_alloc, size_t *sql_offset,
		const char *table_name, int id)
{
	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "alter table" ZBX_DB_ONLY " %s" ZBX_DROP_FK " c_%s_%d",
			table_name, table_name, id);
}

static int	DBreorg_table(const char *table_name)
{
#ifdef HAVE_IBM_DB2
	if (ZBX_DB_OK <= DBexecute("call sysproc.admin_cmd ('reorg table %s')", table_name))
		return SUCCEED;

	return FAIL;
#else
	return SUCCEED;
#endif
}

static int	DBcreate_table(const ZBX_TABLE *table)
{
	char	*sql = NULL;
	size_t	sql_alloc = 0, sql_offset = 0;
	int	ret = FAIL;

	DBcreate_table_sql(&sql, &sql_alloc, &sql_offset, table);

	if (ZBX_DB_OK <= DBexecute("%s", sql))
		ret = SUCCEED;

	zbx_free(sql);

	return ret;
}

static int	DBadd_field(const char *table_name, const ZBX_FIELD *field)
{
	char	*sql = NULL;
	size_t	sql_alloc = 0, sql_offset = 0;
	int	ret = FAIL;

	DBadd_field_sql(&sql, &sql_alloc, &sql_offset, table_name, field);

	if (ZBX_DB_OK <= DBexecute("%s", sql))
		ret = DBreorg_table(table_name);

	zbx_free(sql);

	return ret;
}

static int	DBrename_field(const char *table_name, const char *field_name, const ZBX_FIELD *field)
{
	char	*sql = NULL;
	size_t	sql_alloc = 0, sql_offset = 0;
	int	ret = FAIL;

	DBrename_field_sql(&sql, &sql_alloc, &sql_offset, table_name, field_name, field);

	if (ZBX_DB_OK <= DBexecute("%s", sql))
		ret = DBreorg_table(table_name);

	zbx_free(sql);

	return ret;
}

static int	DBmodify_field_type(const char *table_name, const ZBX_FIELD *field)
{
	char	*sql = NULL;
	size_t	sql_alloc = 0, sql_offset = 0;
	int	ret = FAIL;

	DBmodify_field_type_sql(&sql, &sql_alloc, &sql_offset, table_name, field);

	if (ZBX_DB_OK <= DBexecute("%s", sql))
		ret = DBreorg_table(table_name);

	zbx_free(sql);

	return ret;
}

static int	DBset_not_null(const char *table_name, const ZBX_FIELD *field)
{
	char	*sql = NULL;
	size_t	sql_alloc = 0, sql_offset = 0;
	int	ret = FAIL;

	DBset_not_null_sql(&sql, &sql_alloc, &sql_offset, table_name, field);

	if (ZBX_DB_OK <= DBexecute("%s", sql))
		ret = DBreorg_table(table_name);

	zbx_free(sql);

	return ret;
}

static int	DBset_default(const char *table_name, const ZBX_FIELD *field)
{
	char	*sql = NULL;
	size_t	sql_alloc = 0, sql_offset = 0;
	int	ret = FAIL;

	DBset_default_sql(&sql, &sql_alloc, &sql_offset, table_name, field);

	if (ZBX_DB_OK <= DBexecute("%s", sql))
		ret = DBreorg_table(table_name);

	zbx_free(sql);

	return ret;
}

static int	DBdrop_not_null(const char *table_name, const ZBX_FIELD *field)
{
	char	*sql = NULL;
	size_t	sql_alloc = 0, sql_offset = 0;
	int	ret = FAIL;

	DBdrop_not_null_sql(&sql, &sql_alloc, &sql_offset, table_name, field);

	if (ZBX_DB_OK <= DBexecute("%s", sql))
		ret = DBreorg_table(table_name);

	zbx_free(sql);

	return ret;
}

static int	DBdrop_field(const char *table_name, const char *field_name)
{
	char	*sql = NULL;
	size_t	sql_alloc = 0, sql_offset = 0;
	int	ret = FAIL;

	DBdrop_field_sql(&sql, &sql_alloc, &sql_offset, table_name, field_name);

	if (ZBX_DB_OK <= DBexecute("%s", sql))
		ret = DBreorg_table(table_name);

	zbx_free(sql);

	return ret;
}

static int	DBcreate_index(const char *table_name, const char *index_name, const char *fields, int unique)
{
	char	*sql = NULL;
	size_t	sql_alloc = 0, sql_offset = 0;
	int	ret = FAIL;

	DBcreate_index_sql(&sql, &sql_alloc, &sql_offset, table_name, index_name, fields, unique);

	if (ZBX_DB_OK <= DBexecute("%s", sql))
		ret = SUCCEED;

	zbx_free(sql);

	return ret;
}

static int	DBdrop_index(const char *table_name, const char *index_name)
{
	char	*sql = NULL;
	size_t	sql_alloc = 0, sql_offset = 0;
	int	ret = FAIL;

	DBdrop_index_sql(&sql, &sql_alloc, &sql_offset, table_name, index_name);

	if (ZBX_DB_OK <= DBexecute("%s", sql))
		ret = SUCCEED;

	zbx_free(sql);

	return ret;
}

static int	DBadd_foreign_key(const char *table_name, int id, const ZBX_FIELD *field)
{
	char	*sql = NULL;
	size_t	sql_alloc = 0, sql_offset = 0;
	int	ret = FAIL;

	DBadd_foreign_key_sql(&sql, &sql_alloc, &sql_offset, table_name, id, field);

	if (ZBX_DB_OK <= DBexecute("%s", sql))
		ret = SUCCEED;

	zbx_free(sql);

	return ret;
}

static int	DBdrop_foreign_key(const char *table_name, int id)
{
	char	*sql = NULL;
	size_t	sql_alloc = 0, sql_offset = 0;
	int	ret = FAIL;

	DBdrop_foreign_key_sql(&sql, &sql_alloc, &sql_offset, table_name, id);

	if (ZBX_DB_OK <= DBexecute("%s", sql))
		ret = SUCCEED;

	zbx_free(sql);

	return ret;
}

static int	DBcreate_dbversion_table(void)
{
	const ZBX_TABLE	*table;
	int		ret;

	if (NULL == (table = DBget_table("dbversion")))
		assert(0);

	DBbegin();
	if (SUCCEED == (ret = DBcreate_table(table)))
	{
		if (ZBX_DB_OK > DBexecute("insert into dbversion (mandatory,optional) values (%d,%d)",
				ZBX_FIRST_DB_VERSION, ZBX_FIRST_DB_VERSION))
		{
			ret = FAIL;
		}
	}
	DBend(ret);

	return ret;
}

static int	DBset_version(int version, unsigned char mandatory)
{
	char	sql[64];
	size_t	offset;

	offset = zbx_snprintf(sql, sizeof(sql),  "update dbversion set ");
	if (0 != mandatory)
		offset += zbx_snprintf(sql + offset, sizeof(sql) - offset, "mandatory=%d,", version);
	zbx_snprintf(sql + offset, sizeof(sql) - offset, "optional=%d", version);

	if (ZBX_DB_OK <= DBexecute("%s", sql))
		return SUCCEED;

	return FAIL;
}

static int	DBmodify_proxy_table_id_field(const char *table_name)
{
#ifdef HAVE_POSTGRESQL
	const ZBX_FIELD	field = {"id", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0};

	return DBmodify_field_type(table_name, &field);
#else
	return SUCCEED;
#endif
}

static int	DBpatch_2010001(void)
{
	return DBmodify_proxy_table_id_field("proxy_autoreg_host");
}

static int	DBpatch_2010002(void)
{
	return DBmodify_proxy_table_id_field("proxy_dhistory");
}

static int	DBpatch_2010003(void)
{
	return DBmodify_proxy_table_id_field("proxy_history");
}

static int	DBpatch_2010004(void)
{
	return DBmodify_proxy_table_id_field("history_str_sync");
}

static int	DBpatch_2010005(void)
{
	return DBmodify_proxy_table_id_field("history_sync");
}

static int	DBpatch_2010006(void)
{
	return DBmodify_proxy_table_id_field("history_uint_sync");
}

static int	DBpatch_2010007(void)
{
	const char	*strings[] = {"period", "stime", "timelinefixed", NULL};
	int		i;

	for (i = 0; NULL != strings[i]; i++)
	{
		if (ZBX_DB_OK > DBexecute("update profiles set idx='web.screens.%s' where idx='web.charts.%s'",
				strings[i], strings[i]))
		{
			return FAIL;
		}
	}

	return SUCCEED;
}

static int	DBpatch_2010008(void)
{
	const ZBX_FIELD	field = {"expression", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("triggers", &field);
}

static int	DBpatch_2010009(void)
{
	const ZBX_FIELD	field = {"applicationid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0};

	return DBdrop_not_null("httptest", &field);
}

static int	DBpatch_2010010(void)
{
	const ZBX_FIELD	field = {"hostid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_field("httptest", &field);
}

static int	DBpatch_2010011(void)
{
	const char	*sql =
			"update httptest set hostid=("
				"select a.hostid"
				" from applications a"
				" where a.applicationid = httptest.applicationid"
			")";

	if (ZBX_DB_OK <= DBexecute("%s", sql))
		return SUCCEED;

	return FAIL;
}

static int	DBpatch_2010012(void)
{
	const ZBX_FIELD	field = {"hostid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0};

	return DBset_not_null("httptest", &field);
}

static int	DBpatch_2010013(void)
{
	const ZBX_FIELD	field = {"templateid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_field("httptest", &field);
}

static int	DBpatch_2010014(void)
{
	return DBdrop_index("httptest", "httptest_2");
}

static int	DBpatch_2010015(void)
{
	return DBcreate_index("httptest", "httptest_2", "hostid,name", 1);
}

static int	DBpatch_2010016(void)
{
	return DBcreate_index("httptest", "httptest_4", "templateid", 0);
}

static int	DBpatch_2010017(void)
{
	return DBdrop_foreign_key("httptest", 1);
}

static int	DBpatch_2010018(void)
{
	const ZBX_FIELD	field = {"applicationid", NULL, "applications", "applicationid", 0, 0, 0, 0};

	return DBadd_foreign_key("httptest", 1, &field);
}

static int	DBpatch_2010019(void)
{
	const ZBX_FIELD	field = {"hostid", NULL, "hosts", "hostid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("httptest", 2, &field);
}

static int	DBpatch_2010020(void)
{
	const ZBX_FIELD	field = {"templateid", NULL, "httptest", "httptestid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("httptest", 3, &field);
}

static int	DBpatch_2010021(void)
{
	const ZBX_FIELD	field = {"http_proxy", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("httptest", &field);
}

static int	DBpatch_2010022(void)
{
	const ZBX_FIELD field = {"snmpv3_authprotocol", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("items", &field);
}

static int	DBpatch_2010023(void)
{
	const ZBX_FIELD field = {"snmpv3_privprotocol", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("items", &field);
}

static int	DBpatch_2010024(void)
{
	const ZBX_FIELD field = {"snmpv3_authprotocol", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("dchecks", &field);
}

static int	DBpatch_2010025(void)
{
	const ZBX_FIELD field = {"snmpv3_privprotocol", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("dchecks", &field);
}

static int	DBpatch_2010026(void)
{
	const ZBX_FIELD field = {"retries", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("httptest", &field);
}

static int	DBpatch_2010027(void)
{
	const ZBX_FIELD field = {"application", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("screens_items", &field);
}

static int	DBpatch_2010028(void)
{
	const char	*sql =
			"update profiles"
			" set value_int=case when value_str='0' then 0 else 1 end,"
				"value_str='',"
				"type=2"	/* PROFILE_TYPE_INT */
			" where idx='web.httpconf.showdisabled'";

	if (ZBX_DB_OK <= DBexecute("%s", sql))
		return SUCCEED;

	return FAIL;
}

static int	DBpatch_2010029(void)
{
	const char	*sql =
			"delete from profiles where idx in ('web.httpconf.applications','web.httpmon.applications')";

	if (ZBX_DB_OK <= DBexecute("%s", sql))
		return SUCCEED;

	return FAIL;
}

static int	DBpatch_2010030(void)
{
	const char	*sql = "delete from profiles where idx='web.items.filter_groupid'";

	if (ZBX_DB_OK <= DBexecute("%s", sql))
		return SUCCEED;

	return FAIL;
}

static int	DBpatch_2010031(void)
{
	const char	*sql =
			"update profiles"
			" set value_id=value_int,"
				"value_int=0"
			" where idx like 'web.avail_report.%.groupid'"
				" or idx like 'web.avail_report.%.hostid'";

	if (ZBX_DB_OK <= DBexecute("%s", sql))
		return SUCCEED;

	return FAIL;
}

static int	DBpatch_2010032(void)
{
	const ZBX_FIELD	field = {"type", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBset_default("users", &field);
}

static int	DBpatch_2010033(void)
{
	if (ZBX_DB_OK <= DBexecute(
			"delete from events"
			" where source=%d"
				" and object=%d"
				" and (value=%d or value_changed=%d)",
			EVENT_SOURCE_TRIGGERS,
			EVENT_OBJECT_TRIGGER,
			TRIGGER_VALUE_UNKNOWN,
			0))	/*TRIGGER_VALUE_CHANGED_NO*/
	{
		return SUCCEED;
	}

	return FAIL;
}

static int	DBpatch_2010034(void)
{
	return DBdrop_field("events", "value_changed");
}

static int	DBpatch_2010035(void)
{
	const char	*sql = "delete from profiles where idx='web.events.filter.showUnknown'";

	if (ZBX_DB_OK <= DBexecute("%s", sql))
		return SUCCEED;

	return FAIL;
}

static int	DBpatch_2010036(void)
{
	const char	*sql =
			"update profiles"
			" set value_int=case when value_str='1' then 1 else 0 end,"
				"value_str='',"
				"type=2"	/* PROFILE_TYPE_INT */
			" where idx like '%isnow'";

	if (ZBX_DB_OK <= DBexecute("%s", sql))
		return SUCCEED;

	return FAIL;
}

static int	DBpatch_2010037(void)
{
	if (ZBX_DB_OK <= DBexecute("update config set server_check_interval=10"))
		return SUCCEED;

	return FAIL;
}

static int	DBpatch_2010038(void)
{
	const ZBX_FIELD	field = {"server_check_interval", "10", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBset_default("config", &field);
}

static int	DBpatch_2010039(void)
{
	return DBdrop_field("alerts", "nextcheck");
}

static int	DBpatch_2010040(void)
{
	const ZBX_FIELD	field = {"state", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBrename_field("triggers", "value_flags", &field);
}

static int	DBpatch_2010041(void)
{
	return DBdrop_index("events", "events_1");
}

static int	DBpatch_2010042(void)
{
	return DBcreate_index("events", "events_1", "source,object,objectid,eventid", 1);
}

static int	DBpatch_2010043(void)
{
	const ZBX_FIELD field = {"state", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("items", &field);
}

static int	DBpatch_2010044(void)
{
	if (ZBX_DB_OK <= DBexecute(
			"update items"
			" set state=%d,"
				"status=%d"
			" where status=%d",
			ITEM_STATE_NOTSUPPORTED, ITEM_STATUS_ACTIVE, 3 /*ITEM_STATUS_NOTSUPPORTED*/))
		return SUCCEED;

	return FAIL;
}

static int	DBpatch_2010045(void)
{
	const ZBX_FIELD	field = {"state", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBrename_field("proxy_history", "status", &field);
}

static int	DBpatch_2010046(void)
{
	if (ZBX_DB_OK <= DBexecute(
			"update proxy_history"
			" set state=%d"
			" where state=%d",
			ITEM_STATE_NOTSUPPORTED, 3 /*ITEM_STATUS_NOTSUPPORTED*/))
		return SUCCEED;

	return FAIL;
}

static int	DBpatch_2010047(void)
{
	const ZBX_FIELD	field = {"itemid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_field("escalations", &field);
}

static int	DBpatch_2010048(void)
{
	return DBdrop_index("escalations", "escalations_1");
}

static int	DBpatch_2010049(void)
{
	return DBcreate_index("escalations", "escalations_1", "actionid,triggerid,itemid,escalationid", 1);
}

static int	DBpatch_2010050(void)
{
	char		*fields[] = {"ts_from", "ts_to", NULL};
	DB_RESULT	result;
	DB_ROW		row;
	int		i;
	time_t		ts;
	struct tm	*tm;

	for (i = 0; NULL != fields[i]; i++)
	{
		result = DBselect(
				"select timeid,%s"
				" from services_times"
				" where type in (%d,%d)"
					" and %s>%d",
				fields[i], 0 /* SERVICE_TIME_TYPE_UPTIME */, 1 /* SERVICE_TIME_TYPE_DOWNTIME */,
				fields[i], SEC_PER_WEEK);

		while (NULL != (row = DBfetch(result)))
		{
			if (SEC_PER_WEEK < (ts = (time_t)atoi(row[1])))
			{
				tm = localtime(&ts);
				ts = tm->tm_wday * SEC_PER_DAY + tm->tm_hour * SEC_PER_HOUR + tm->tm_min * SEC_PER_MIN;
				DBexecute("update services_times set %s=%d where timeid=%s",
						fields[i], (int)ts, row[0]);
			}
		}
		DBfree_result(result);
	}

	return SUCCEED;
}

static int	DBpatch_2010051(void)
{
	const ZBX_FIELD field = {"hk_events_mode", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_2010052(void)
{
	const ZBX_FIELD field = {"hk_events_trigger", "365", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_2010053(void)
{
	const ZBX_FIELD field = {"hk_events_internal", "365", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_2010054(void)
{
	const ZBX_FIELD field = {"hk_events_discovery", "365", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_2010055(void)
{
	const ZBX_FIELD field = {"hk_events_autoreg", "365", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_2010056(void)
{
	const ZBX_FIELD field = {"hk_services_mode", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_2010057(void)
{
	const ZBX_FIELD field = {"hk_services", "365", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_2010058(void)
{
	const ZBX_FIELD field = {"hk_audit_mode", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_2010059(void)
{
	const ZBX_FIELD field = {"hk_audit", "365", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_2010060(void)
{
	const ZBX_FIELD field = {"hk_sessions_mode", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_2010061(void)
{
	const ZBX_FIELD field = {"hk_sessions", "365", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_2010062(void)
{
	const ZBX_FIELD field = {"hk_history_mode", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_2010063(void)
{
	const ZBX_FIELD field = {"hk_history_global", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_2010064(void)
{
	const ZBX_FIELD field = {"hk_history", "90", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_2010065(void)
{
	const ZBX_FIELD field = {"hk_trends_mode", "1 ", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_2010066(void)
{
	const ZBX_FIELD field = {"hk_trends_global", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_2010067(void)
{
	const ZBX_FIELD field = {"hk_trends", "365", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_2010068(void)
{
	if (ZBX_DB_OK <= DBexecute(
			"update config"
			" set hk_events_mode=0,"
				"hk_services_mode=0,"
				"hk_audit_mode=0,"
				"hk_sessions_mode=0,"
				"hk_history_mode=0,"
				"hk_trends_mode=0,"
				"hk_events_trigger="
					"case when event_history>alert_history"
					" then event_history else alert_history end,"
				"hk_events_discovery="
					"case when event_history>alert_history"
					" then event_history else alert_history end,"
				"hk_events_autoreg="
					"case when event_history>alert_history"
					" then event_history else alert_history end,"
				"hk_events_internal="
					"case when event_history>alert_history"
					" then event_history else alert_history end"))
	{
		return SUCCEED;
	}

	return FAIL;
}

static int	DBpatch_2010069(void)
{
	return DBdrop_field("config", "event_history");
}

static int	DBpatch_2010070(void)
{
	return DBdrop_field("config", "alert_history");
}

static int	DBpatch_2010071(void)
{
	const ZBX_FIELD	field = {"snmpv3_contextname", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("items", &field);
}

static int	DBpatch_2010072(void)
{
	const ZBX_FIELD	field = {"snmpv3_contextname", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("dchecks", &field);
}

static int	DBpatch_2010073(void)
{
	const char	*sql = "delete from ids where table_name='events'";

	if (ZBX_DB_OK <= DBexecute("%s", sql))
		return SUCCEED;

	return FAIL;
}

static int	DBpatch_2010074(void)
{
	const ZBX_FIELD	field = {"variables", "", NULL, NULL, 0, ZBX_TYPE_SHORTTEXT, ZBX_NOTNULL, 0};

	return DBrename_field("httptest", "macros", &field);
}

static int	DBpatch_2010075(void)
{
	const ZBX_FIELD	field = {"variables", "", NULL, NULL, 0, ZBX_TYPE_SHORTTEXT, ZBX_NOTNULL, 0};

	return DBadd_field("httpstep", &field);
}

static int	DBpatch_2010076(void)
{
	const ZBX_TABLE	table =
			{"application_template", "application_templateid", 0,
				{
					{"application_templateid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"applicationid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"templateid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{NULL}
				}
			};

	return DBcreate_table(&table);
}

static int	DBpatch_2010077(void)
{
	return DBcreate_index("application_template", "application_template_1", "applicationid,templateid", 1);
}

static int	DBpatch_2010078(void)
{
	const ZBX_FIELD	field = {"applicationid", NULL, "applications", "applicationid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("application_template", 1, &field);
}

static int	DBpatch_2010079(void)
{
	const ZBX_FIELD	field = {"templateid", NULL, "applications", "applicationid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("application_template", 2, &field);
}

static int	DBpatch_2010080(void)
{
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	id = 1, applicationid, templateid, application_templateid;
	int		ret = FAIL;

	result = DBselect("select applicationid,templateid from applications where templateid is not null");

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(applicationid, row[0]);
		ZBX_STR2UINT64(templateid, row[1]);
		application_templateid = get_nodeid_by_id(applicationid) * ZBX_DM_MAX_HISTORY_IDS + id++;

		if (ZBX_DB_OK > DBexecute(
				"insert into application_template"
					" (application_templateid,applicationid,templateid)"
					" values (" ZBX_FS_UI64 "," ZBX_FS_UI64 "," ZBX_FS_UI64 ")",
				application_templateid, applicationid, templateid))
		{
			goto out;
		}
	}

	ret = SUCCEED;
out:
	DBfree_result(result);

	return ret;
}

static int	DBpatch_2010081(void)
{
	return DBdrop_foreign_key("applications", 2);
}

static int	DBpatch_2010082(void)
{
	return DBdrop_index("applications", "applications_1");
}

static int	DBpatch_2010083(void)
{
	return DBdrop_field("applications", "templateid");
}

static int	DBpatch_2010084(void)
{
	const ZBX_FIELD field = {"severity_min", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("sysmaps", &field);
}

static int	DBpatch_2010085(void)
{
	const ZBX_FIELD	field = {"host_metadata", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("autoreg_host", &field);
}

static int	DBpatch_2010086(void)
{
	const ZBX_FIELD	field = {"host_metadata", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("proxy_autoreg_host", &field);
}

#define DBPATCH_START()					zbx_dbpatch_t	patches[] = {
#define DBPATCH_ADD(version, duplicates, mandatory)	{DBpatch_##version, version, duplicates, mandatory},
#define DBPATCH_END()					{NULL}};

#else

#define DBPATCH_START()
#define DBPATCH_ADD(version, duplicates, mandatory)	if (1 == mandatory) required = version;
#define DBPATCH_END()

#endif	/* not HAVE_SQLITE3 */

static void	DBget_version(int *mandatory, int *optional)
{
	DB_RESULT	result;
	DB_ROW		row;

	*mandatory = -1;
	*optional = -1;

	result = DBselect("select mandatory,optional from dbversion");

	if (NULL != (row = DBfetch(result)))
	{
		*mandatory = atoi(row[0]);
		*optional = atoi(row[1]);
	}
	DBfree_result(result);

	if (-1 == *mandatory)
	{
		zabbix_log(LOG_LEVEL_CRIT, "Cannot get the database version. Exiting ...");
		exit(EXIT_FAILURE);
	}
}

int	DBcheck_version(void)
{
	const char	*__function_name = "DBcheck_version";
	const char	*dbversion_table_name = "dbversion";
	int		db_mandatory, db_optional, required, ret = FAIL;

#ifndef HAVE_SQLITE3
	int		i, total = 0, current = 0, completed, last_completed = -1;
#endif

	DBPATCH_START()

	/* version, duplicates flag, mandatory flag */
	DBPATCH_ADD(2010001, 0, 1)
	DBPATCH_ADD(2010002, 0, 1)
	DBPATCH_ADD(2010003, 0, 1)
	DBPATCH_ADD(2010004, 0, 1)
	DBPATCH_ADD(2010005, 0, 1)
	DBPATCH_ADD(2010006, 0, 1)
	DBPATCH_ADD(2010007, 0, 0)
	DBPATCH_ADD(2010008, 0, 1)
	DBPATCH_ADD(2010009, 0, 1)
	DBPATCH_ADD(2010010, 0, 1)
	DBPATCH_ADD(2010011, 0, 1)
	DBPATCH_ADD(2010012, 0, 1)
	DBPATCH_ADD(2010013, 0, 1)
	DBPATCH_ADD(2010014, 0, 1)
	DBPATCH_ADD(2010015, 0, 1)
	DBPATCH_ADD(2010016, 0, 1)
	DBPATCH_ADD(2010017, 0, 1)
	DBPATCH_ADD(2010018, 0, 1)
	DBPATCH_ADD(2010019, 0, 1)
	DBPATCH_ADD(2010020, 0, 1)
	DBPATCH_ADD(2010021, 0, 1)
	DBPATCH_ADD(2010022, 0, 1)
	DBPATCH_ADD(2010023, 0, 1)
	DBPATCH_ADD(2010024, 0, 1)
	DBPATCH_ADD(2010025, 0, 1)
	DBPATCH_ADD(2010026, 0, 1)
	DBPATCH_ADD(2010027, 0, 1)
	DBPATCH_ADD(2010028, 0, 0)
	DBPATCH_ADD(2010029, 0, 0)
	DBPATCH_ADD(2010030, 0, 0)
	DBPATCH_ADD(2010031, 0, 0)
	DBPATCH_ADD(2010032, 0, 1)
	DBPATCH_ADD(2010033, 0, 1)
	DBPATCH_ADD(2010034, 0, 1)
	DBPATCH_ADD(2010035, 0, 0)
	DBPATCH_ADD(2010036, 0, 0)
	DBPATCH_ADD(2010037, 0, 0)
	DBPATCH_ADD(2010038, 0, 0)
	DBPATCH_ADD(2010039, 0, 0)
	DBPATCH_ADD(2010040, 0, 1)
	DBPATCH_ADD(2010041, 0, 0)
	DBPATCH_ADD(2010042, 0, 0)
	DBPATCH_ADD(2010043, 0, 1)
	DBPATCH_ADD(2010044, 0, 1)
	DBPATCH_ADD(2010045, 0, 1)
	DBPATCH_ADD(2010046, 0, 1)
	DBPATCH_ADD(2010047, 0, 1)
	DBPATCH_ADD(2010048, 0, 0)
	DBPATCH_ADD(2010049, 0, 0)
	DBPATCH_ADD(2010050, 0, 1)
	DBPATCH_ADD(2010051, 0, 1)
	DBPATCH_ADD(2010052, 0, 1)
	DBPATCH_ADD(2010053, 0, 1)
	DBPATCH_ADD(2010054, 0, 1)
	DBPATCH_ADD(2010055, 0, 1)
	DBPATCH_ADD(2010056, 0, 1)
	DBPATCH_ADD(2010057, 0, 1)
	DBPATCH_ADD(2010058, 0, 1)
	DBPATCH_ADD(2010059, 0, 1)
	DBPATCH_ADD(2010060, 0, 1)
	DBPATCH_ADD(2010061, 0, 1)
	DBPATCH_ADD(2010062, 0, 1)
	DBPATCH_ADD(2010063, 0, 1)
	DBPATCH_ADD(2010064, 0, 1)
	DBPATCH_ADD(2010065, 0, 1)
	DBPATCH_ADD(2010066, 0, 1)
	DBPATCH_ADD(2010067, 0, 1)
	DBPATCH_ADD(2010068, 0, 1)
	DBPATCH_ADD(2010069, 0, 0)
	DBPATCH_ADD(2010070, 0, 0)
	DBPATCH_ADD(2010071, 0, 1)
	DBPATCH_ADD(2010072, 0, 1)
	DBPATCH_ADD(2010073, 0, 0)
	DBPATCH_ADD(2010074, 0, 1)
	DBPATCH_ADD(2010075, 0, 1)
	DBPATCH_ADD(2010076, 0, 1)
	DBPATCH_ADD(2010077, 0, 1)
	DBPATCH_ADD(2010078, 0, 1)
	DBPATCH_ADD(2010079, 0, 1)
	DBPATCH_ADD(2010080, 0, 1)
	DBPATCH_ADD(2010081, 0, 1)
	DBPATCH_ADD(2010082, 0, 1)
	DBPATCH_ADD(2010083, 0, 1)
	DBPATCH_ADD(2010084, 0, 1)
	DBPATCH_ADD(2010085, 0, 1)
	DBPATCH_ADD(2010086, 0, 1)

	DBPATCH_END()

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	DBconnect(ZBX_DB_CONNECT_NORMAL);

	if (SUCCEED != DBtable_exists(dbversion_table_name))
	{
#ifndef HAVE_SQLITE3
		zabbix_log(LOG_LEVEL_DEBUG, "%s() \"%s\" does not exist",
				__function_name, dbversion_table_name);

		if (SUCCEED != DBfield_exists("config", "server_check_interval"))
		{
			zabbix_log(LOG_LEVEL_CRIT, "Cannot upgrade database: the database must"
					" correspond to version 2.0 or later. Exiting ...");
			goto out;
		}

		if (SUCCEED != DBcreate_dbversion_table())
			goto out;
#else
		zabbix_log(LOG_LEVEL_CRIT, "The %s does not match Zabbix database."
				" Current database version (mandatory/optional): UNKNOWN."
				" Required mandatory version: %08d.",
				ZBX_DAEMON_TYPE_SERVER == daemon_type ? "server" : "proxy", required);
		goto out;
#endif
	}

	DBget_version(&db_mandatory, &db_optional);

#ifndef HAVE_SQLITE3
	required = ZBX_FIRST_DB_VERSION;

	for (i = 0; NULL != patches[i].function; i++)
	{
		if (0 != patches[i].mandatory)
			required = patches[i].version;

		if (db_optional < patches[i].version)
			total++;
	}

	if (required < db_mandatory)
#else
	if (required != db_mandatory)
#endif
	{
		zabbix_log(LOG_LEVEL_CRIT, "The %s does not match Zabbix database."
				" Current database version (mandatory/optional): %08d/%08d."
				" Required mandatory version: %08d.",
				ZBX_DAEMON_TYPE_SERVER == daemon_type ? "server" : "proxy",
				db_mandatory, db_optional, required);
		goto out;
	}

	zabbix_log(LOG_LEVEL_INFORMATION, "current database version (mandatory/optional): %08d/%08d",
			db_mandatory, db_optional);
	zabbix_log(LOG_LEVEL_INFORMATION, "required mandatory version: %08d", required);

	ret = SUCCEED;

#ifndef HAVE_SQLITE3
	if (0 == total)
		goto out;

	zabbix_log(LOG_LEVEL_WARNING, "starting automatic database upgrade");

	for (i = 0; NULL != patches[i].function; i++)
	{
		if (db_optional >= patches[i].version)
			continue;

		DBbegin();

		/* skipping the duplicated patches */
		if ((0 != patches[i].duplicates && patches[i].duplicates <= db_optional) ||
				SUCCEED == (ret = patches[i].function()))
		{
			ret = DBset_version(patches[i].version, patches[i].mandatory);
		}

		DBend(ret);

		if (SUCCEED != ret)
			break;

		current++;
		completed = (int)(100.0 * current / total);
		if (last_completed != completed)
		{
			zabbix_log(LOG_LEVEL_WARNING, "completed %d%% of database upgrade", completed);
			last_completed = completed;
		}
	}

	if (SUCCEED == ret)
		zabbix_log(LOG_LEVEL_WARNING, "database upgrade fully completed");
	else
		zabbix_log(LOG_LEVEL_CRIT, "database upgrade failed");
#endif	/* not HAVE_SQLITE3 */

out:
	DBclose();

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}
