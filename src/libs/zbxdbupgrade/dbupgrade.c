/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "common.h"

#include "db.h"
#include "log.h"

#if defined(HAVE_MYSQL)
#	define ZBX_DB_TABLE_OPTIONS	" engine=innodb"
#	define ZBX_DROP_FK		" drop foreign key"
#else
#	define ZBX_DB_TABLE_OPTIONS	""
#	define ZBX_DROP_FK		" drop constraint"
#endif

#if defined(HAVE_POSTGRESQL)
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

#if defined(HAVE_ORACLE)
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

#if !defined(HAVE_SQLITE3)
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
		zbx_strcpy_alloc(sql, sql_alloc, sql_offset, " not null");
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
	zbx_strcpy_alloc(sql, sql_alloc, sql_offset, "\n)" ZBX_DB_TABLE_OPTIONS);
}

static void	DBmodify_field_type_sql(char **sql, size_t *sql_alloc, size_t *sql_offset,
		const char *table_name, const ZBX_FIELD *field)
{
	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "alter table" ZBX_DB_ONLY " %s" ZBX_DB_ALTER_COLUMN " ",
			table_name);

#if defined(HAVE_MYSQL)
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

static void	DBadd_field_sql(char **sql, size_t *sql_alloc, size_t *sql_offset,
		const char *table_name, const ZBX_FIELD *field)
{
	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "alter table" ZBX_DB_ONLY " %s add ", table_name);
	DBfield_definition_string(sql, sql_alloc, sql_offset, field);
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
#if defined(HAVE_MYSQL)
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
#if defined(HAVE_IBM_DB2)
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
	size_t	sql_alloc = 64, sql_offset = 0;
	int	ret = FAIL;

	sql = zbx_malloc(sql, sql_alloc);

	DBcreate_table_sql(&sql, &sql_alloc, &sql_offset, table);

	if (ZBX_DB_OK <= DBexecute("%s", sql))
		ret = SUCCEED;

	zbx_free(sql);

	return ret;
}

static int	DBadd_field(const char *table_name, const ZBX_FIELD *field)
{
	char	*sql = NULL;
	size_t	sql_alloc = 64, sql_offset = 0;
	int	ret = FAIL;

	sql = zbx_malloc(sql, sql_alloc);

	DBadd_field_sql(&sql, &sql_alloc, &sql_offset, table_name, field);

	if (ZBX_DB_OK <= DBexecute("%s", sql))
		ret = DBreorg_table(table_name);

	zbx_free(sql);

	return ret;
}

static int	DBmodify_field_type(const char *table_name, const ZBX_FIELD *field)
{
	char	*sql = NULL;
	size_t	sql_alloc = 64, sql_offset = 0;
	int	ret = FAIL;

	sql = zbx_malloc(sql, sql_alloc);

	DBmodify_field_type_sql(&sql, &sql_alloc, &sql_offset, table_name, field);

	if (ZBX_DB_OK <= DBexecute("%s", sql))
		ret = DBreorg_table(table_name);

	zbx_free(sql);

	return ret;
}

static int	DBset_not_null(const char *table_name, const ZBX_FIELD *field)
{
	char	*sql = NULL;
	size_t	sql_alloc = 64, sql_offset = 0;
	int	ret = FAIL;

	sql = zbx_malloc(sql, sql_alloc);

	DBset_not_null_sql(&sql, &sql_alloc, &sql_offset, table_name, field);

	if (ZBX_DB_OK <= DBexecute("%s", sql))
		ret = DBreorg_table(table_name);

	zbx_free(sql);

	return ret;
}

static int	DBdrop_not_null(const char *table_name, const ZBX_FIELD *field)
{
	char	*sql = NULL;
	size_t	sql_alloc = 64, sql_offset = 0;
	int	ret = FAIL;

	sql = zbx_malloc(sql, sql_alloc);

	DBdrop_not_null_sql(&sql, &sql_alloc, &sql_offset, table_name, field);

	if (ZBX_DB_OK <= DBexecute("%s", sql))
		ret = DBreorg_table(table_name);

	zbx_free(sql);

	return ret;
}

static int	DBcreate_index(const char *table_name, const char *index_name, const char *fields, int unique)
{
	char	*sql = NULL;
	size_t	sql_alloc = 64, sql_offset = 0;
	int	ret = FAIL;

	sql = zbx_malloc(sql, sql_alloc);

	DBcreate_index_sql(&sql, &sql_alloc, &sql_offset, table_name, index_name, fields, unique);

	if (ZBX_DB_OK <= DBexecute("%s", sql))
		ret = SUCCEED;

	zbx_free(sql);

	return ret;
}

static int	DBdrop_index(const char *table_name, const char *index_name)
{
	char	*sql = NULL;
	size_t	sql_alloc = 64, sql_offset = 0;
	int	ret = FAIL;

	sql = zbx_malloc(sql, sql_alloc);

	DBdrop_index_sql(&sql, &sql_alloc, &sql_offset, table_name, index_name);

	if (ZBX_DB_OK <= DBexecute("%s", sql))
		ret = SUCCEED;

	zbx_free(sql);

	return ret;
}

static int	DBadd_foreign_key(const char *table_name, int id, const ZBX_FIELD *field)
{
	char	*sql = NULL;
	size_t	sql_alloc = 64, sql_offset = 0;
	int	ret = FAIL;

	sql = zbx_malloc(sql, sql_alloc);

	DBadd_foreign_key_sql(&sql, &sql_alloc, &sql_offset, table_name, id, field);

	if (ZBX_DB_OK <= DBexecute("%s", sql))
		ret = SUCCEED;

	zbx_free(sql);

	return ret;
}

static int	DBdrop_foreign_key(const char *table_name, int id)
{
	char	*sql = NULL;
	size_t	sql_alloc = 64, sql_offset = 0;
	int	ret = FAIL;

	sql = zbx_malloc(sql, sql_alloc);

	DBdrop_foreign_key_sql(&sql, &sql_alloc, &sql_offset, table_name, id);

	if (ZBX_DB_OK <= DBexecute("%s", sql))
		ret = SUCCEED;

	zbx_free(sql);

	return ret;
}

static int	DBcreate_dbversion_table()
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
#if defined(HAVE_POSTGRESQL)
	const ZBX_FIELD	field = {"id", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0};

	return DBmodify_field_type(table_name, &field);
#else
	return SUCCEED;
#endif
}

static int	DBpatch_02010001()
{
	return DBmodify_proxy_table_id_field("proxy_autoreg_host");
}

static int	DBpatch_02010002()
{
	return DBmodify_proxy_table_id_field("proxy_dhistory");
}

static int	DBpatch_02010003()
{
	return DBmodify_proxy_table_id_field("proxy_history");
}

static int	DBpatch_02010004()
{
	return DBmodify_proxy_table_id_field("history_str_sync");
}

static int	DBpatch_02010005()
{
	return DBmodify_proxy_table_id_field("history_sync");
}

static int	DBpatch_02010006()
{
	return DBmodify_proxy_table_id_field("history_uint_sync");
}

static int	DBpatch_02010007()
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

static int	DBpatch_02010008()
{
	const ZBX_FIELD	field = {"expression", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("triggers", &field);
}

static int	DBpatch_02010009()
{
	const ZBX_FIELD	field = {"applicationid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0};

	return DBdrop_not_null("httptest", &field);
}

static int	DBpatch_02010010()
{
	const ZBX_FIELD	field = {"hostid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_field("httptest", &field);
}

static int	DBpatch_02010011()
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

static int	DBpatch_02010012()
{
	const ZBX_FIELD	field = {"hostid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0};

	return DBset_not_null("httptest", &field);
}

static int	DBpatch_02010013()
{
	const ZBX_FIELD	field = {"templateid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_field("httptest", &field);
}

static int	DBpatch_02010014()
{
	return DBdrop_index("httptest", "httptest_2");
}

static int	DBpatch_02010015()
{
	return DBcreate_index("httptest", "httptest_2", "hostid,name", 1);
}

static int	DBpatch_02010016()
{
	return DBcreate_index("httptest", "httptest_4", "templateid", 0);
}

static int	DBpatch_02010017()
{
	return DBdrop_foreign_key("httptest", 1);
}

static int	DBpatch_02010018()
{
	const ZBX_FIELD	field = {"applicationid", NULL, "applications", "applicationid", 0, 0, 0, 0};

	return DBadd_foreign_key("httptest", 1, &field);
}

static int	DBpatch_02010019()
{
	const ZBX_FIELD	field = {"hostid", NULL, "hosts", "hostid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("httptest", 2, &field);
}

static int	DBpatch_02010020()
{
	const ZBX_FIELD	field = {"templateid", NULL, "httptest", "httptestid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("httptest", 3, &field);
}

static int	DBpatch_02010021()
{
	const ZBX_FIELD	field = {"http_proxy", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("httptest", &field);
}

static int	DBpatch_02010022()
{
	const ZBX_FIELD field = {"snmpv3_authprotocol", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("items", &field);
}

static int	DBpatch_02010023()
{
	const ZBX_FIELD field = {"snmpv3_privprotocol", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("items", &field);
}

static int	DBpatch_02010024()
{
	const ZBX_FIELD field = {"snmpv3_authprotocol", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("dchecks", &field);
}

static int	DBpatch_02010025()
{
	const ZBX_FIELD field = {"snmpv3_privprotocol", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("dchecks", &field);
}

static int	DBpatch_02010026()
{
	const ZBX_FIELD field = {"retries", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("httptest", &field);
}
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

int	DBcheck_version()
{
	const char	*__function_name = "DBcheck_version";
	const char	*dbversion_table_name = "dbversion";
	int		db_mandatory, db_optional, required, ret = FAIL;

#if !defined(HAVE_SQLITE3)
	int		i, total = 0, current = 0, completed, last_completed = -1;

	zbx_dbpatch_t	patches[] =
	{
		/* function, version, duplicates flag, mandatory flag */
		{DBpatch_02010001, 2010001, 0, 1},
		{DBpatch_02010002, 2010002, 0, 1},
		{DBpatch_02010003, 2010003, 0, 1},
		{DBpatch_02010004, 2010004, 0, 1},
		{DBpatch_02010005, 2010005, 0, 1},
		{DBpatch_02010006, 2010006, 0, 1},
		{DBpatch_02010007, 2010007, 0, 0},
		{DBpatch_02010008, 2010008, 0, 1},
		{DBpatch_02010009, 2010009, 0, 1},
		{DBpatch_02010010, 2010010, 0, 1},
		{DBpatch_02010011, 2010011, 0, 1},
		{DBpatch_02010012, 2010012, 0, 1},
		{DBpatch_02010013, 2010013, 0, 1},
		{DBpatch_02010014, 2010014, 0, 1},
		{DBpatch_02010015, 2010015, 0, 1},
		{DBpatch_02010016, 2010016, 0, 1},
		{DBpatch_02010017, 2010017, 0, 1},
		{DBpatch_02010018, 2010018, 0, 1},
		{DBpatch_02010019, 2010019, 0, 1},
		{DBpatch_02010020, 2010020, 0, 1},
		{DBpatch_02010021, 2010021, 0, 1},
		{DBpatch_02010022, 2010022, 0, 1},
		{DBpatch_02010023, 2010023, 0, 1},
		{DBpatch_02010024, 2010024, 0, 1},
		{DBpatch_02010025, 2010025, 0, 1},
		{DBpatch_02010026, 2010026, 0, 1},
		/* IMPORTANT! When adding a new mandatory DBPatch don't forget to update it for SQLite, too. */
		{NULL}
	};
#else
	required = 2010026;	/* <---- Update mandatory DBpatch for SQLite here. */
#endif

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	DBconnect(ZBX_DB_CONNECT_NORMAL);

	if (SUCCEED != DBtable_exists(dbversion_table_name))
	{
#if !defined(HAVE_SQLITE3)
		zabbix_log(LOG_LEVEL_DEBUG, "%s() \"%s\" doesn't exist",
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

#if !defined(HAVE_SQLITE3)
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

#if !defined(HAVE_SQLITE3)
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
