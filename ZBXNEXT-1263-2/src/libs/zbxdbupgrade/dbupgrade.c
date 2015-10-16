/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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
#include "sysinfo.h"
#include "zbxdbupgrade.h"
#include "dbupgrade.h"

typedef struct
{
	zbx_dbpatch_t	*patches;
	const char	*description;
}
zbx_db_version_t;

#ifdef HAVE_MYSQL
#	define ZBX_DB_TABLE_OPTIONS	" engine=innodb"
#	define ZBX_DROP_FK		" drop foreign key"
#else
#	define ZBX_DB_TABLE_OPTIONS	""
#	define ZBX_DROP_FK		" drop constraint"
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
#	define ZBX_TYPE_FLOAT_STR	"decfloat(16)"
#	define ZBX_TYPE_UINT_STR	"bigint"
#elif defined(HAVE_MYSQL)
#	define ZBX_TYPE_FLOAT_STR	"double(16,4)"
#	define ZBX_TYPE_UINT_STR	"bigint unsigned"
#elif defined(HAVE_ORACLE)
#	define ZBX_TYPE_FLOAT_STR	"number(20,4)"
#	define ZBX_TYPE_UINT_STR	"number(20)"
#elif defined(HAVE_POSTGRESQL)
#	define ZBX_TYPE_FLOAT_STR	"numeric(16,4)"
#	define ZBX_TYPE_UINT_STR	"numeric(20)"
#endif

#if defined(HAVE_IBM_DB2)
#	define ZBX_TYPE_SHORTTEXT_STR	"varchar(2048)"
#elif defined(HAVE_ORACLE)
#	define ZBX_TYPE_SHORTTEXT_STR	"nvarchar2(2048)"
#else
#	define ZBX_TYPE_SHORTTEXT_STR	"text"
#endif

#if defined(HAVE_IBM_DB2)
#	define ZBX_TYPE_TEXT_STR	"varchar(2048)"
#elif defined(HAVE_ORACLE)
#	define ZBX_TYPE_TEXT_STR	"nclob"
#else
#	define ZBX_TYPE_TEXT_STR	"text"
#endif

#define ZBX_FIRST_DB_VERSION		2010000

extern unsigned char	program_type;


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
		case ZBX_TYPE_FLOAT:
			zbx_strcpy_alloc(sql, sql_alloc, sql_offset, ZBX_TYPE_FLOAT_STR);
			break;
		case ZBX_TYPE_UINT:
			zbx_strcpy_alloc(sql, sql_alloc, sql_offset, ZBX_TYPE_UINT_STR);
			break;
		case ZBX_TYPE_SHORTTEXT:
			zbx_strcpy_alloc(sql, sql_alloc, sql_offset, ZBX_TYPE_SHORTTEXT_STR);
			break;
		case ZBX_TYPE_TEXT:
			zbx_strcpy_alloc(sql, sql_alloc, sql_offset, ZBX_TYPE_TEXT_STR);
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

#if defined(HAVE_MYSQL)
		switch (field->type)
		{
			case ZBX_TYPE_BLOB:
			case ZBX_TYPE_TEXT:
			case ZBX_TYPE_SHORTTEXT:
			case ZBX_TYPE_LONGTEXT:
				/* MySQL: BLOB and TEXT columns cannot be assigned a default value */
				break;
			default:
#endif
				default_value_esc = DBdyn_escape_string(field->default_value);
				zbx_snprintf_alloc(sql, sql_alloc, sql_offset, " default '%s'", default_value_esc);
				zbx_free(default_value_esc);
#if defined(HAVE_MYSQL)
		}
#endif
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

static void	DBdrop_table_sql(char **sql, size_t *sql_alloc, size_t *sql_offset, const char *table_name)
{
	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "drop table %s", table_name);
}

static void	DBmodify_field_type_sql(char **sql, size_t *sql_alloc, size_t *sql_offset,
		const char *table_name, const ZBX_FIELD *field)
{
	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "alter table %s" ZBX_DB_ALTER_COLUMN " ", table_name);

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
	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "alter table %s" ZBX_DB_ALTER_COLUMN " ", table_name);

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
	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "alter table %s" ZBX_DB_ALTER_COLUMN " ", table_name);

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
	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "alter table %s" ZBX_DB_ALTER_COLUMN " ", table_name);

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
	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "alter table %s add ", table_name);
	DBfield_definition_string(sql, sql_alloc, sql_offset, field);
}

static void	DBrename_field_sql(char **sql, size_t *sql_alloc, size_t *sql_offset,
		const char *table_name, const char *field_name, const ZBX_FIELD *field)
{
	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "alter table %s ", table_name);

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
	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "alter table %s drop column %s", table_name, field_name);
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

static void	DBrename_index_sql(char **sql, size_t *sql_alloc, size_t *sql_offset, const char *table_name,
		const char *old_name, const char *new_name, const char *fields, int unique)
{
#if defined(HAVE_IBM_DB2)
	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "rename index %s to %s", old_name, new_name);
#elif defined(HAVE_MYSQL)
	DBcreate_index_sql(sql, sql_alloc, sql_offset, table_name, new_name, fields, unique);
	zbx_strcpy_alloc(sql, sql_alloc, sql_offset, ";\n");
	DBdrop_index_sql(sql, sql_alloc, sql_offset, table_name, old_name);
	zbx_strcpy_alloc(sql, sql_alloc, sql_offset, ";\n");
#elif defined(HAVE_ORACLE) || defined(HAVE_POSTGRESQL)
	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "alter index %s rename to %s", old_name, new_name);
#endif
}

static void	DBadd_foreign_key_sql(char **sql, size_t *sql_alloc, size_t *sql_offset,
		const char *table_name, int id, const ZBX_FIELD *field)
{
	zbx_snprintf_alloc(sql, sql_alloc, sql_offset,
			"alter table %s add constraint c_%s_%d foreign key (%s) references %s (%s)",
			table_name, table_name, id, field->name, field->fk_table, field->fk_field);
	if (0 != (field->fk_flags & ZBX_FK_CASCADE_DELETE))
		zbx_strcpy_alloc(sql, sql_alloc, sql_offset, " on delete cascade");
}

static void	DBdrop_foreign_key_sql(char **sql, size_t *sql_alloc, size_t *sql_offset,
		const char *table_name, int id)
{
	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "alter table %s" ZBX_DROP_FK " c_%s_%d",
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

int	DBcreate_table(const ZBX_TABLE *table)
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

int	DBdrop_table(const char *table_name)
{
	char	*sql = NULL;
	size_t	sql_alloc = 0, sql_offset = 0;
	int	ret = FAIL;

	DBdrop_table_sql(&sql, &sql_alloc, &sql_offset, table_name);

	if (ZBX_DB_OK <= DBexecute("%s", sql))
		ret = SUCCEED;

	zbx_free(sql);

	return ret;
}

int	DBadd_field(const char *table_name, const ZBX_FIELD *field)
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

int	DBrename_field(const char *table_name, const char *field_name, const ZBX_FIELD *field)
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

int	DBmodify_field_type(const char *table_name, const ZBX_FIELD *field)
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

int	DBset_not_null(const char *table_name, const ZBX_FIELD *field)
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

int	DBset_default(const char *table_name, const ZBX_FIELD *field)
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

int	DBdrop_not_null(const char *table_name, const ZBX_FIELD *field)
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

int	DBdrop_field(const char *table_name, const char *field_name)
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

int	DBcreate_index(const char *table_name, const char *index_name, const char *fields, int unique)
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

int	DBdrop_index(const char *table_name, const char *index_name)
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

int	DBrename_index(const char *table_name, const char *old_name, const char *new_name, const char *fields,
				int unique)
{
	char	*sql = NULL;
	size_t	sql_alloc = 0, sql_offset = 0;
	int	ret = FAIL;

	DBrename_index_sql(&sql, &sql_alloc, &sql_offset, table_name, old_name, new_name, fields, unique);

	if (ZBX_DB_OK <= DBexecute("%s", sql))
		ret = SUCCEED;

	zbx_free(sql);

	return ret;
}

int	DBadd_foreign_key(const char *table_name, int id, const ZBX_FIELD *field)
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

int	DBdrop_foreign_key(const char *table_name, int id)
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
	const ZBX_TABLE	table =
			{"dbversion", "", 0,
				{
					{"mandatory", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"optional", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{NULL}
				}
			};
	int		ret;

	DBbegin();
	if (SUCCEED == (ret = DBcreate_table(&table)))
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

#endif	/* not HAVE_SQLITE3 */

extern zbx_dbpatch_t	DBPATCH_VERSION(2010)[];
extern zbx_dbpatch_t	DBPATCH_VERSION(2020)[];
extern zbx_dbpatch_t	DBPATCH_VERSION(2030)[];
extern zbx_dbpatch_t	DBPATCH_VERSION(2040)[];
extern zbx_dbpatch_t	DBPATCH_VERSION(2050)[];

static zbx_db_version_t dbversions[] = {
	{DBPATCH_VERSION(2010), "2.2 development"},
	{DBPATCH_VERSION(2020), "2.2 maintenance"},
	{DBPATCH_VERSION(2030), "2.4 development"},
	{DBPATCH_VERSION(2040), "2.4 maintenance"},
	{DBPATCH_VERSION(2050), "3.0 development"},
	{NULL}
};

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
	const char		*__function_name = "DBcheck_version";
	const char		*dbversion_table_name = "dbversion";
	int			db_mandatory, db_optional, required, ret = FAIL, i;
	zbx_db_version_t	*dbversion;
	zbx_dbpatch_t		*patches;

#ifndef HAVE_SQLITE3
	int			total = 0, current = 0, completed, last_completed = -1, optional_num = 0;
#endif
	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	required = ZBX_FIRST_DB_VERSION;

	/* find out the required version number by getting the last mandatory version */
	/* of the last version patch array                                            */
	for (dbversion = dbversions; NULL != dbversion->patches; dbversion++)
		;

	patches = (--dbversion)->patches;

	for (i = 0; 0 != patches[i].version; i++)
	{
		if (0 != patches[i].mandatory)
			required = patches[i].version;
	}

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
				get_program_type_string(program_type), required);
		zabbix_log(LOG_LEVEL_CRIT, "Zabbix does not support SQLite3 database upgrade.");

		goto out;
#endif
	}

	DBget_version(&db_mandatory, &db_optional);

#ifndef HAVE_SQLITE3
	for (dbversion = dbversions; NULL != (patches = dbversion->patches); dbversion++)
	{
		for (i = 0; 0 != patches[i].version; i++)
		{
			if (0 != patches[i].mandatory)
				optional_num = 0;
			else
				optional_num++;

			if (db_optional < patches[i].version)
				total++;
		}
	}

	if (required < db_mandatory)
#else
	if (required != db_mandatory)
#endif
	{
		zabbix_log(LOG_LEVEL_CRIT, "The %s does not match Zabbix database."
				" Current database version (mandatory/optional): %08d/%08d."
				" Required mandatory version: %08d.",
				get_program_type_string(program_type), db_mandatory, db_optional, required);
#ifdef HAVE_SQLITE3
		if (required > db_mandatory)
			zabbix_log(LOG_LEVEL_CRIT, "Zabbix does not support SQLite3 database upgrade.");
#endif
		goto out;
	}

	zabbix_log(LOG_LEVEL_INFORMATION, "current database version (mandatory/optional): %08d/%08d",
			db_mandatory, db_optional);
	zabbix_log(LOG_LEVEL_INFORMATION, "required mandatory version: %08d", required);

	ret = SUCCEED;

#ifndef HAVE_SQLITE3
	if (0 == total)
		goto out;

	if (0 != optional_num)
		zabbix_log(LOG_LEVEL_INFORMATION, "optional patches were found");

	zabbix_log(LOG_LEVEL_WARNING, "starting automatic database upgrade");

	for (dbversion = dbversions; NULL != dbversion->patches; dbversion++)
	{
		zbx_dbpatch_t	*patches = dbversion->patches;

		for (i = 0; 0 != patches[i].version; i++)
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

		if (SUCCEED != ret)
			break;
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
