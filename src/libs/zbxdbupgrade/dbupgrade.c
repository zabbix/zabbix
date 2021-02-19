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
#include "log.h"
#include "sysinfo.h"
#include "zbxdbupgrade.h"
#include "dbupgrade.h"
#include "zbxalgo.h"
#include "../zbxalgo/vectorimpl.h"

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

#if defined(HAVE_POSTGRESQL)
#	define ZBX_DB_ALTER_COLUMN	" alter"
#else
#	define ZBX_DB_ALTER_COLUMN	" modify"
#endif

#if defined(HAVE_POSTGRESQL)
#	define ZBX_DB_SET_TYPE		" type"
#else
#	define ZBX_DB_SET_TYPE		""
#endif

/* NOTE: Do not forget to sync changes in ZBX_TYPE_*_STR defines for Oracle with zbx_oracle_column_type()! */

#if defined(HAVE_POSTGRESQL)
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

#if defined(HAVE_MYSQL)
#	define ZBX_TYPE_FLOAT_STR	"double precision"
#	define ZBX_TYPE_UINT_STR	"bigint unsigned"
#elif defined(HAVE_ORACLE)
#	define ZBX_TYPE_FLOAT_STR	"binary_double"
#	define ZBX_TYPE_UINT_STR	"number(20)"
#elif defined(HAVE_POSTGRESQL)
#	define ZBX_TYPE_FLOAT_STR	"double precision"
#	define ZBX_TYPE_UINT_STR	"numeric(20)"
#endif

#if defined(HAVE_ORACLE)
#	define ZBX_TYPE_SHORTTEXT_STR	"nvarchar2(2048)"
#else
#	define ZBX_TYPE_SHORTTEXT_STR	"text"
#endif

#if defined(HAVE_ORACLE)
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

#ifdef HAVE_ORACLE
typedef enum
{
	ZBX_ORACLE_COLUMN_TYPE_NUMERIC,
	ZBX_ORACLE_COLUMN_TYPE_CHARACTER,
	ZBX_ORACLE_COLUMN_TYPE_DOUBLE,
	ZBX_ORACLE_COLUMN_TYPE_UNKNOWN
}
zbx_oracle_column_type_t;

/******************************************************************************
 *                                                                            *
 * Function: zbx_oracle_column_type                                           *
 *                                                                            *
 * Purpose: determine whether column type is character or numeric             *
 *                                                                            *
 * Parameters: field_type - [IN] column type in Zabbix definitions            *
 *                                                                            *
 * Return value: column type (character/raw, numeric) in Oracle definitions   *
 *                                                                            *
 * Comments: The size of a character or raw column or the precision of a      *
 *           numeric column can be changed, whether or not all the rows       *
 *           contain nulls. Otherwise in order to change the datatype of a    *
 *           column all rows of the column must contain nulls.                *
 *                                                                            *
 ******************************************************************************/
static zbx_oracle_column_type_t	zbx_oracle_column_type(unsigned char field_type)
{
	switch (field_type)
	{
		case ZBX_TYPE_ID:
		case ZBX_TYPE_INT:
		case ZBX_TYPE_UINT:
			return ZBX_ORACLE_COLUMN_TYPE_NUMERIC;
		case ZBX_TYPE_CHAR:
		case ZBX_TYPE_SHORTTEXT:
		case ZBX_TYPE_TEXT:
			return ZBX_ORACLE_COLUMN_TYPE_CHARACTER;
		case ZBX_TYPE_FLOAT:
			return ZBX_ORACLE_COLUMN_TYPE_DOUBLE;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			return ZBX_ORACLE_COLUMN_TYPE_UNKNOWN;
	}
}
#endif

static void	DBfield_definition_string(char **sql, size_t *sql_alloc, size_t *sql_offset, const ZBX_FIELD *field)
{
	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, ZBX_FS_SQL_NAME " ", field->name);
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

static void	DBrename_table_sql(char **sql, size_t *sql_alloc, size_t *sql_offset, const char *table_name,
		const char *new_name)
{
	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "alter table " ZBX_FS_SQL_NAME " rename to " ZBX_FS_SQL_NAME,
			table_name, new_name);
}

static void	DBdrop_table_sql(char **sql, size_t *sql_alloc, size_t *sql_offset, const char *table_name)
{
	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "drop table %s", table_name);
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

static void	DBdrop_default_sql(char **sql, size_t *sql_alloc, size_t *sql_offset,
		const char *table_name, const ZBX_FIELD *field)
{
	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "alter table %s" ZBX_DB_ALTER_COLUMN " ", table_name);

#if defined(HAVE_MYSQL)
	DBfield_definition_string(sql, sql_alloc, sql_offset, field);
#elif defined(HAVE_ORACLE)
	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%s default null", field->name);
#else
	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%s drop default", field->name);
#endif
}

static void	DBmodify_field_type_sql(char **sql, size_t *sql_alloc, size_t *sql_offset,
		const char *table_name, const ZBX_FIELD *field)
{
	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "alter table " ZBX_FS_SQL_NAME ZBX_DB_ALTER_COLUMN " ",
			table_name);

#ifdef HAVE_MYSQL
	DBfield_definition_string(sql, sql_alloc, sql_offset, field);
#else
	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%s" ZBX_DB_SET_TYPE " ", field->name);
	DBfield_type_string(sql, sql_alloc, sql_offset, field);
#ifdef HAVE_POSTGRESQL
	if (NULL != field->default_value)
	{
		zbx_strcpy_alloc(sql, sql_alloc, sql_offset, ";\n");
		DBset_default_sql(sql, sql_alloc, sql_offset, table_name, field);
	}
#endif
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

static void	DBadd_field_sql(char **sql, size_t *sql_alloc, size_t *sql_offset,
		const char *table_name, const ZBX_FIELD *field)
{
	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "alter table " ZBX_FS_SQL_NAME " add ", table_name);
	DBfield_definition_string(sql, sql_alloc, sql_offset, field);
}

static void	DBrename_field_sql(char **sql, size_t *sql_alloc, size_t *sql_offset,
		const char *table_name, const char *field_name, const ZBX_FIELD *field)
{
	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "alter table " ZBX_FS_SQL_NAME " ", table_name);

#ifdef HAVE_MYSQL
	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "change column " ZBX_FS_SQL_NAME " ", field_name);
	DBfield_definition_string(sql, sql_alloc, sql_offset, field);
#else
	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "rename column " ZBX_FS_SQL_NAME " to " ZBX_FS_SQL_NAME,
			field_name, field->name);
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
#else
	ZBX_UNUSED(table_name);
#endif
}

static void	DBrename_index_sql(char **sql, size_t *sql_alloc, size_t *sql_offset, const char *table_name,
		const char *old_name, const char *new_name, const char *fields, int unique)
{
#if defined(HAVE_MYSQL)
	DBcreate_index_sql(sql, sql_alloc, sql_offset, table_name, new_name, fields, unique);
	zbx_strcpy_alloc(sql, sql_alloc, sql_offset, ";\n");
	DBdrop_index_sql(sql, sql_alloc, sql_offset, table_name, old_name);
	zbx_strcpy_alloc(sql, sql_alloc, sql_offset, ";\n");
#elif defined(HAVE_ORACLE) || defined(HAVE_POSTGRESQL)
	ZBX_UNUSED(table_name);
	ZBX_UNUSED(fields);
	ZBX_UNUSED(unique);
	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "alter index %s rename to %s", old_name, new_name);
#endif
}

static void	DBadd_foreign_key_sql(char **sql, size_t *sql_alloc, size_t *sql_offset,
		const char *table_name, int id, const ZBX_FIELD *field)
{
	zbx_snprintf_alloc(sql, sql_alloc, sql_offset,
			"alter table " ZBX_FS_SQL_NAME " add constraint c_%s_%d foreign key (" ZBX_FS_SQL_NAME ")"
					" references " ZBX_FS_SQL_NAME " (" ZBX_FS_SQL_NAME ")", table_name, table_name,
					id, field->name, field->fk_table, field->fk_field);
	if (0 != (field->fk_flags & ZBX_FK_CASCADE_DELETE))
		zbx_strcpy_alloc(sql, sql_alloc, sql_offset, " on delete cascade");
}

static void	DBdrop_foreign_key_sql(char **sql, size_t *sql_alloc, size_t *sql_offset,
		const char *table_name, int id)
{
	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "alter table %s" ZBX_DROP_FK " c_%s_%d",
			table_name, table_name, id);
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

int	DBrename_table(const char *table_name, const char *new_name)
{
	char	*sql = NULL;
	size_t	sql_alloc = 0, sql_offset = 0;
	int	ret = FAIL;

	DBrename_table_sql(&sql, &sql_alloc, &sql_offset, table_name, new_name);

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
		ret = SUCCEED;

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
		ret = SUCCEED;

	zbx_free(sql);

	return ret;
}

#ifdef HAVE_ORACLE
static int	DBmodify_field_type_with_copy(const char *table_name, const ZBX_FIELD *field)
{
#define ZBX_OLD_FIELD	"zbx_old_tmp"

	char	*sql = NULL;
	size_t	sql_alloc = 0, sql_offset = 0;
	int	ret = FAIL;

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "alter table %s rename column %s to " ZBX_OLD_FIELD,
			table_name, field->name);

	if (ZBX_DB_OK > DBexecute("%s", sql))
		goto out;

	if (ZBX_DB_OK > DBadd_field(table_name, field))
		goto out;

	sql_offset = 0;
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update %s set %s=" ZBX_OLD_FIELD, table_name,
			field->name);

	if (ZBX_DB_OK > DBexecute("%s", sql))
		goto out;

	ret = DBdrop_field(table_name, ZBX_OLD_FIELD);
out:
	zbx_free(sql);

	return ret;

#undef ZBX_OLD_FIELD
}
#endif

int	DBmodify_field_type(const char *table_name, const ZBX_FIELD *field, const ZBX_FIELD *old_field)
{
	char	*sql = NULL;
	size_t	sql_alloc = 0, sql_offset = 0;
	int	ret = FAIL;

#ifndef HAVE_ORACLE
	ZBX_UNUSED(old_field);
#else
	/* Oracle cannot change column type in a general case if column contents are not null. Conversions like   */
	/* number -> nvarchar2 or nvarchar2 -> nclob need special processing. New column is created with desired  */
	/* datatype and data from old column is copied there. Then old column is dropped. This method does not    */
	/* preserve column order.                                                                                 */
	/* NOTE: Existing column indexes and constraints are not respected by the current implementation!         */

	if (NULL != old_field && (zbx_oracle_column_type(old_field->type) != zbx_oracle_column_type(field->type) ||
			ZBX_ORACLE_COLUMN_TYPE_DOUBLE == zbx_oracle_column_type(field->type) ||
			(ZBX_TYPE_TEXT == field->type && ZBX_TYPE_SHORTTEXT == old_field->type)))
		return DBmodify_field_type_with_copy(table_name, field);
#endif
	DBmodify_field_type_sql(&sql, &sql_alloc, &sql_offset, table_name, field);

	if (ZBX_DB_OK <= DBexecute("%s", sql))
		ret = SUCCEED;

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
		ret = SUCCEED;

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
		ret = SUCCEED;

	zbx_free(sql);

	return ret;
}

int	DBdrop_default(const char *table_name, const ZBX_FIELD *field)
{
	char	*sql = NULL;
	size_t	sql_alloc = 0, sql_offset = 0;
	int	ret = FAIL;

	DBdrop_default_sql(&sql, &sql_alloc, &sql_offset, table_name, field);

	if (ZBX_DB_OK <= DBexecute("%s", sql))
		ret = SUCCEED;

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
		ret = SUCCEED;

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
		ret = SUCCEED;

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
				},
				NULL
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

	return DBend(ret);
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

typedef struct
{
	uint64_t	screenitemid;
	uint64_t	screenid;
	int		resourcetype;
	uint64_t	resourceid;
	int		width;
	int		height;
	int		x;
	int		y;
	int		colspan;
	int		rowspan;
	int		elements;
	int		style;
	char		*url;
	int		max_columns;
	int		sort_triggers;
	char		*application;
}
zbx_db_screen_item_t;

typedef struct
{
	uint64_t	widget_fieldid;
	int		type;
	char		*name;
	int		value_int;
	char		*value_str;
	uint64_t	value_itemid;
	uint64_t	value_graphid;
	uint64_t	value_groupid;
	uint64_t	value_hostid;
	uint64_t	value_sysmapid;
}
zbx_db_widget_field_t;

typedef struct
{
	int	position;
	int	span;
	int	size;
}
zbx_screen_item_dim_t;

typedef struct
{
	uint64_t	dashboardid;
	char		*name;
	uint64_t	userid;
	int		private;
	uint64_t	templateid;
	int		display_period;
}
zbx_db_dashboard_t;

typedef struct
{
	uint64_t	dashboard_pageid;
	uint64_t	dashboardid;
	char		*name;
}
zbx_db_dashboard_page_t;

typedef struct
{
	uint64_t	widgetid;
	uint64_t	dashboardid;
	char		*type;
	char		*name;
	int		x;
	int		y;
	int		width;
	int		height;
	int		view_mode;
}
zbx_db_widget_t;

#define DASHBOARD_MAX_COLS			(24)
#define DASHBOARD_MAX_ROWS			(64)
#define DASHBOARD_WIDGET_MIN_ROWS		(2)
#define DASHBOARD_WIDGET_MAX_ROWS		(32)
#define SCREEN_MAX_ROWS				(100)
#define SCREEN_MAX_COLS				(100)

#undef SCREEN_RESOURCE_CLOCK
#define SCREEN_RESOURCE_CLOCK			(7)
#undef SCREEN_RESOURCE_GRAPH
#define SCREEN_RESOURCE_GRAPH			(0)
#undef SCREEN_RESOURCE_SIMPLE_GRAPH
#define SCREEN_RESOURCE_SIMPLE_GRAPH		(1)
#undef SCREEN_RESOURCE_LLD_GRAPH
#define SCREEN_RESOURCE_LLD_GRAPH		(20)
#undef SCREEN_RESOURCE_LLD_SIMPLE_GRAPH
#define SCREEN_RESOURCE_LLD_SIMPLE_GRAPH	(19)
#undef SCREEN_RESOURCE_PLAIN_TEXT
#define SCREEN_RESOURCE_PLAIN_TEXT		(3)
#undef SCREEN_RESOURCE_URL
#define SCREEN_RESOURCE_URL			(11)

#undef SCREEN_RESOURCE_MAP
#define SCREEN_RESOURCE_MAP			(2)
#undef SCREEN_RESOURCE_HOST_INFO
#define SCREEN_RESOURCE_HOST_INFO		(4)
#undef SCREEN_RESOURCE_TRIGGER_INFO
#define SCREEN_RESOURCE_TRIGGER_INFO		(5)
#undef SCREEN_RESOURCE_SERVER_INFO
#define SCREEN_RESOURCE_SERVER_INFO		(6)
#undef SCREEN_RESOURCE_SCREEN
#define SCREEN_RESOURCE_SCREEN			(8)
#undef SCREEN_RESOURCE_TRIGGER_OVERVIEW
#define SCREEN_RESOURCE_TRIGGER_OVERVIEW	(9)
#undef SCREEN_RESOURCE_DATA_OVERVIEW
#define SCREEN_RESOURCE_DATA_OVERVIEW		(10)
#undef SCREEN_RESOURCE_ACTIONS
#define SCREEN_RESOURCE_ACTIONS			(12)
#undef SCREEN_RESOURCE_EVENTS
#define SCREEN_RESOURCE_EVENTS			(13)
#undef SCREEN_RESOURCE_HOSTGROUP_TRIGGERS
#define SCREEN_RESOURCE_HOSTGROUP_TRIGGERS	(14)
#undef SCREEN_RESOURCE_SYSTEM_STATUS
#define SCREEN_RESOURCE_SYSTEM_STATUS		(15)
#undef SCREEN_RESOURCE_HOST_TRIGGERS
#define SCREEN_RESOURCE_HOST_TRIGGERS		(16)

#define ZBX_WIDGET_FIELD_TYPE_INT32		(0)
#define ZBX_WIDGET_FIELD_TYPE_STR		(1)
#define ZBX_WIDGET_FIELD_TYPE_GROUP		(2)
#define ZBX_WIDGET_FIELD_TYPE_HOST		(3)
#define ZBX_WIDGET_FIELD_TYPE_ITEM		(4)
#define ZBX_WIDGET_FIELD_TYPE_ITEM_PROTOTYPE	(5)
#define ZBX_WIDGET_FIELD_TYPE_GRAPH		(6)
#define ZBX_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE	(7)
#define ZBX_WIDGET_FIELD_TYPE_MAP		(8)

#define ZBX_WIDGET_FIELD_RESOURCE_GRAPH				(0)
#define ZBX_WIDGET_FIELD_RESOURCE_SIMPLE_GRAPH			(1)
#define ZBX_WIDGET_FIELD_RESOURCE_GRAPH_PROTOTYPE		(2)
#define ZBX_WIDGET_FIELD_RESOURCE_SIMPLE_GRAPH_PROTOTYPE	(3)

#define ZBX_WIDGET_TYPE_CLOCK			("clock")
#define ZBX_WIDGET_TYPE_GRAPH_CLASSIC		("graph")
#define ZBX_WIDGET_TYPE_GRAPH_PROTOTYPE		("graphprototype")
#define ZBX_WIDGET_TYPE_PLAIN_TEXT		("plaintext")
#define ZBX_WIDGET_TYPE_URL			("url")
#define ZBX_WIDGET_TYPE_ACTIONS			("actionlog")
#define ZBX_WIDGET_TYPE_DATA_OVERVIEW		("dataover")
#define ZBX_WIDGET_TYPE_PROBLEMS		("problems")
#define ZBX_WIDGET_TYPE_HOST_INFO		("hostavail")
#define ZBX_WIDGET_TYPE_MAP			("maps")
#define ZBX_WIDGET_TYPE_SYSTEM_STATUS		("problemsbysv")
#define ZBX_WIDGET_TYPE_SERVER_INFO		("systeminfo")
#define ZBX_WIDGET_TYPE_TRIGGER_OVERVIEW	("trigover")

#define POS_EMPTY	(127)
#define POS_TAKEN	(1)

ZBX_VECTOR_DECL(scitem_dim, zbx_screen_item_dim_t);
ZBX_VECTOR_IMPL(scitem_dim, zbx_screen_item_dim_t);
ZBX_VECTOR_DECL(char, char);
ZBX_VECTOR_IMPL(char, char);

#define SKIP_EMPTY(vector,index)	if (POS_EMPTY == vector->values[index]) continue

#define COLLISIONS_MAX_NUMBER	(100)
#define REFERENCE_MAX_LEN	(5)

static int DBpatch_dashboard_name(char *name, char **new_name)
{
	int		affix = 0, ret = FAIL;
	DB_RESULT	result;
	DB_ROW		row;

	*new_name = zbx_strdup(*new_name, name);

	do
	{
		result = DBselect("select count(*)"
				" from dashboard"
				" where name='%s' and templateid is null",
				*new_name);

		if (NULL == result || NULL == (row = DBfetch(result)))
		{
			zbx_free(*new_name);
			break;
		}

		if (0 == strcmp("0", row[0]))
		{
			ret = SUCCEED;
			break;
		}

		*new_name = zbx_dsprintf(*new_name, "(%d)%s", affix + 1, name);
	} while (COLLISIONS_MAX_NUMBER > affix++);

	DBfree_result(result);

	return ret;
}

static int DBpatch_reference_name(char **ref_name)
{
	int		i = 0, j, ret = FAIL;
	char		name[REFERENCE_MAX_LEN + 1];
	const char	*pattern = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
	DB_RESULT	result;
	DB_ROW		row;

	name[REFERENCE_MAX_LEN] = '\0';

	do
	{
		for (j = 0; j < REFERENCE_MAX_LEN; j++)
			name[j] = pattern[rand() % (int)strlen(pattern)];

		result = DBselect("select count(*)"
			" from widget_field"
			" where value_str='%s' and name='reference'",
			name);

		if (NULL == result || NULL == (row = DBfetch(result)))
			break;

		if (0 == strcmp("0", row[0]))
		{
			ret = SUCCEED;
			*ref_name = zbx_strdup(NULL, name);
			break;
		}

	} while (COLLISIONS_MAX_NUMBER > i++);

	DBfree_result(result);

	return ret;
}

static int	DBpatch_init_dashboard(zbx_db_dashboard_t *dashboard, char *name, uint64_t templateid, uint64_t userid,
		int private)
{
	int ret = SUCCEED;

	memset((void *)dashboard, 0, sizeof(zbx_db_dashboard_t));
	dashboard->templateid = templateid;

	if (0 == templateid)
	{
		dashboard->userid = userid;
		dashboard->private = private;
		ret = DBpatch_dashboard_name(name, &dashboard->name);
	}
	else
		dashboard->name = zbx_strdup(NULL, name);

	return ret;
}

static void	DBpatch_widget_field_free(zbx_db_widget_field_t *field)
{
	zbx_free(field->name);
	zbx_free(field->value_str);
	zbx_free(field);
}

static void	DBpatch_screen_item_free(zbx_db_screen_item_t *si)
{
	zbx_free(si->url);
	zbx_free(si->application);
	zbx_free(si);
}

static int	DBpatch_is_convertible_screen_item(int rt)
{
	return SCREEN_RESOURCE_CLOCK == rt || SCREEN_RESOURCE_GRAPH  == rt || SCREEN_RESOURCE_SIMPLE_GRAPH == rt ||
			SCREEN_RESOURCE_LLD_GRAPH == rt || SCREEN_RESOURCE_LLD_SIMPLE_GRAPH == rt ||
			SCREEN_RESOURCE_PLAIN_TEXT == rt || SCREEN_RESOURCE_URL == rt;
}

static size_t	DBpatch_array_max_used_index(char *array, size_t arr_size)
{
	size_t	i, m = 0;

	for (i = 0; i < arr_size; i++)
	{
		if (0 != array[i])
			m = i;
	}

	return m;
}

static void DBpatch_normalize_screen_items_pos(zbx_vector_ptr_t *scr_items)
{
	char	used_x[SCREEN_MAX_COLS], used_y[SCREEN_MAX_ROWS];
	char	keep_x[SCREEN_MAX_COLS], keep_y[SCREEN_MAX_ROWS];
	int	i, n, x;

	memset((void *)used_x, 0, sizeof(used_x));
	memset((void *)used_y, 0, sizeof(used_y));
	memset((void *)keep_x, 0, sizeof(keep_x));
	memset((void *)keep_y, 0, sizeof(keep_y));

	for (i = 0; i < scr_items->values_num; i++)
	{
		zbx_db_screen_item_t	*c = (zbx_db_screen_item_t *)scr_items->values[i];

		for (n = c->x; n < c->x + c->colspan && n < SCREEN_MAX_COLS; n++)
			used_x[n] = 1;
		for (n = c->y; n < c->y + c->rowspan && n < SCREEN_MAX_ROWS; n++)
			used_y[n] = 1;

		keep_x[c->x] = 1;
		if (c->x + c->colspan < SCREEN_MAX_COLS)
			keep_x[c->x + c->colspan] = 1;
		keep_y[c->y] = 1;
		if (c->y + c->rowspan < SCREEN_MAX_ROWS)
			keep_y[c->y + c->rowspan] = 1;
	}

#define COMPRESS_SCREEN_ITEMS(axis, span, a_size)							\
													\
do {													\
	for (x = DBpatch_array_max_used_index(keep_ ## axis, a_size); x >= 0; x--)			\
	{												\
		if (0 != keep_ ## axis[x] && 0 != used_ ## axis[x])					\
			continue;									\
													\
		for (i = 0; i < scr_items->values_num; i++)						\
		{											\
			zbx_db_screen_item_t	*c = (zbx_db_screen_item_t *)scr_items->values[i];	\
													\
			if (x < c->axis)								\
				c->axis--;								\
													\
			if (x > c->axis && x < c->axis + c->span)					\
				c->span--;								\
		}											\
	}												\
} while (0)

	COMPRESS_SCREEN_ITEMS(x, colspan, SCREEN_MAX_COLS);
	COMPRESS_SCREEN_ITEMS(y, rowspan, SCREEN_MAX_ROWS);

#undef COMPRESS_SCREEN_ITEMS
}

static void	DBpatch_get_preferred_widget_size(zbx_db_screen_item_t *item, int *w, int *h)
{
	*w = item->width;
	*h = item->height;

	if (SCREEN_RESOURCE_LLD_GRAPH == item->resourcetype || SCREEN_RESOURCE_LLD_SIMPLE_GRAPH == item->resourcetype ||
			SCREEN_RESOURCE_GRAPH == item->resourcetype || SCREEN_RESOURCE_SIMPLE_GRAPH == item->resourcetype)
	{
		*h += 215;	/* SCREEN_LEGEND_HEIGHT */
	}

	*w = (int)round((double)*w / 1920 * DASHBOARD_MAX_COLS);	/* DISPLAY_WIDTH */
	*h = (int)round((double)*h / 70);				/* WIDGET_ROW_HEIGHT */

	*w = MIN(DASHBOARD_MAX_COLS, MAX(1, *w));
	*h = MIN(DASHBOARD_WIDGET_MAX_ROWS, MAX(DASHBOARD_WIDGET_MIN_ROWS, *h));
}

static void	DBpatch_get_min_widget_size(zbx_db_screen_item_t *item, int *w, int *h)
{
	switch (item->resourcetype)
	{
		case SCREEN_RESOURCE_CLOCK:
			*w = 1; *h = 2;
			break;
		case SCREEN_RESOURCE_GRAPH:
		case SCREEN_RESOURCE_SIMPLE_GRAPH:
		case SCREEN_RESOURCE_LLD_GRAPH:
		case SCREEN_RESOURCE_LLD_SIMPLE_GRAPH:
			*w = 4; *h = 4;
			break;
		case SCREEN_RESOURCE_PLAIN_TEXT:
		case SCREEN_RESOURCE_URL:
			*w = 4; *h = 2;
			break;
		case SCREEN_RESOURCE_MAP:
		case SCREEN_RESOURCE_HOST_INFO:
		case SCREEN_RESOURCE_TRIGGER_INFO:
		case SCREEN_RESOURCE_SERVER_INFO:
		case SCREEN_RESOURCE_TRIGGER_OVERVIEW:
		case SCREEN_RESOURCE_DATA_OVERVIEW:
		case SCREEN_RESOURCE_ACTIONS:
		case SCREEN_RESOURCE_EVENTS:
		case SCREEN_RESOURCE_HOSTGROUP_TRIGGERS:
		case SCREEN_RESOURCE_SYSTEM_STATUS:
		case SCREEN_RESOURCE_HOST_TRIGGERS:
			*w = 4; *h = 2;
			break;
		default:
			zabbix_log(LOG_LEVEL_WARNING, "%s: unknown resource type %d", __func__, item->resourcetype);
	}
}

static char	*lw_array_to_str(zbx_vector_char_t *v)
{
	static char	str[MAX_STRING_LEN];
	char		*ptr;
	int		i, max = MAX_STRING_LEN, len;

	ptr = str;
	len = zbx_snprintf(ptr, max, "[ ");
	ptr += len;
	max -= len;

	for (i = 0; 0 < max && i < v->values_num; i++)
	{
		if (POS_EMPTY != v->values[i])
		{
			len = zbx_snprintf(ptr, max, "%d:%d ", i, (int)v->values[i]);
			ptr += len;
			max -= len;
		}
	}

	if (max > 1)
		strcat(ptr, "]");

	return str;
}

static void	lw_array_debug(char *pfx, zbx_vector_char_t *v)
{
	zabbix_log(LOG_LEVEL_TRACE, "%s: %s", pfx, lw_array_to_str(v));
}

static void	int_array_debug(char *pfx, int *a, int alen, int emptyval)
{
	static char	str[MAX_STRING_LEN];
	char		*ptr;
	int		i, max = MAX_STRING_LEN, len;

	ptr = str;
	len = zbx_snprintf(ptr, max, "[ ");
	ptr += len;
	max -= len;

	for (i = 0; 0 < max && i < alen; i++)
	{
		if (emptyval != a[i])
		{
			len = zbx_snprintf(ptr, max, "%d:%d ", i, a[i]);
			ptr += len;
			max -= len;
		}
	}

	if (max > 1)
		strcat(ptr, "]");

	zabbix_log(LOG_LEVEL_TRACE, "%s: %s", pfx, str);
}

static zbx_vector_char_t	*lw_array_create(void)
{
	zbx_vector_char_t	*v;
	static char		fill[SCREEN_MAX_ROWS];

	if (0 == fill[0])
		memset(fill, POS_EMPTY, SCREEN_MAX_ROWS);

	v = (zbx_vector_char_t *)malloc(sizeof(zbx_vector_char_t));

	zbx_vector_char_create(v);
	zbx_vector_char_append_array(v, fill, SCREEN_MAX_ROWS);

	return v;
}

static void	lw_array_free(zbx_vector_char_t *v)
{
	if (NULL != v)
	{
		zbx_vector_char_destroy(v);
		zbx_free(v);
	}
}

static zbx_vector_char_t	*lw_array_create_fill(int start, size_t num)
{
	size_t			i;
	zbx_vector_char_t	*v;

	v = lw_array_create();

	for (i = start; i < start + num && i < (size_t)v->values_num; i++)
		v->values[i] = POS_TAKEN;

	return v;
}

static zbx_vector_char_t	*lw_array_diff(zbx_vector_char_t *a, zbx_vector_char_t *b)
{
	int			i;
	zbx_vector_char_t	*v;

	v = lw_array_create();

	for (i = 0; i < a->values_num; i++)
	{
		SKIP_EMPTY(a, i);
		if (POS_EMPTY == b->values[i])
			v->values[i] = a->values[i];
	}

	return v;
}

static zbx_vector_char_t	*lw_array_intersect(zbx_vector_char_t *a, zbx_vector_char_t *b)
{
	int			i;
	zbx_vector_char_t	*v;

	v = lw_array_create();

	for (i = 0; i < a->values_num; i++)
	{
		SKIP_EMPTY(a, i);
		if (POS_EMPTY != b->values[i])
			v->values[i] = a->values[i];
	}

	return v;
}

static int	lw_array_count(zbx_vector_char_t *v)
{
	int	i, c = 0;

	for (i = 0; i < v->values_num; i++)
	{
		if (POS_EMPTY != v->values[i])
			c++;
	}

	return c;
}

static int	lw_array_sum(zbx_vector_char_t *v)
{
	int	i, c = 0;

	for (i = 0; i < v->values_num; i++)
	{
		if (POS_EMPTY != v->values[i])
			c += v->values[i];
	}

	return c;
}

typedef struct
{
	int			index;	/* index for zbx_vector_scitem_dim_t */
	zbx_vector_char_t	*r_block;
}
sciitem_block_t;

static zbx_vector_char_t	*sort_dimensions;

static int	DBpatch_block_compare_func(const void *d1, const void *d2)
{
	const sciitem_block_t	*i1 = *(const sciitem_block_t **)d1;
	const sciitem_block_t	*i2 = *(const sciitem_block_t **)d2;
	zbx_vector_char_t	*diff1, *diff2;
	int			unsized_a, unsized_b;

	diff1 = lw_array_diff(i1->r_block, sort_dimensions);
	diff2 = lw_array_diff(i2->r_block, sort_dimensions);

	unsized_a = lw_array_count(diff1);
	unsized_b = lw_array_count(diff2);

	lw_array_free(diff1);
	lw_array_free(diff2);

	ZBX_RETURN_IF_NOT_EQUAL(unsized_a, unsized_b);

	return 0;
}

static zbx_vector_char_t	*DBpatch_get_axis_dimensions(zbx_vector_scitem_dim_t *scitems)
{
	int			i;
	zbx_vector_ptr_t	blocks;
	sciitem_block_t		*block;
	zbx_vector_char_t	*dimensions;

	zabbix_log(LOG_LEVEL_TRACE, "In %s()", __func__);

	zbx_vector_ptr_create(&blocks);
	dimensions = lw_array_create();

	for (i = 0; i < scitems->values_num; i++)
	{
		block = (sciitem_block_t *)malloc(sizeof(sciitem_block_t));
		block->r_block = lw_array_create_fill(scitems->values[i].position, scitems->values[i].span);
		block->index = i;
		zbx_vector_ptr_append(&blocks, (void *)block);
	}

	sort_dimensions = dimensions;

	while (0 < blocks.values_num)
	{
		zbx_vector_char_t	*block_dimensions, *block_unsized, *r_block;
		int			block_dimensions_sum, block_unsized_count, size_overflow, n;

		zbx_vector_ptr_sort(&blocks, DBpatch_block_compare_func);
		block = blocks.values[0];
		r_block = block->r_block;

		block_dimensions = lw_array_intersect(dimensions, r_block);
		block_dimensions_sum = lw_array_sum(block_dimensions);
		lw_array_free(block_dimensions);

		block_unsized = lw_array_diff(r_block, dimensions);
		block_unsized_count = lw_array_count(block_unsized);
		size_overflow = scitems->values[block->index].size - block_dimensions_sum;

		if (0 < block_unsized_count)
		{
			for (n = 0; n < block_unsized->values_num; n++)
			{
				SKIP_EMPTY(block_unsized, n);
				dimensions->values[n] = MAX(1, size_overflow / block_unsized_count);
				size_overflow -= dimensions->values[n];
				block_unsized_count--;
			}
		}
		else if (0 < size_overflow)
		{
			for (n = 0; n < r_block->values_num; n++)
			{
				double	factor;
				int	new_dimension;

				SKIP_EMPTY(r_block, n);
				factor = (double)(size_overflow + block_dimensions_sum) / block_dimensions_sum;
				new_dimension = (int)round(factor * dimensions->values[n]);
				block_dimensions_sum -= dimensions->values[n];
				size_overflow -= new_dimension - dimensions->values[n];
				dimensions->values[n] = new_dimension;
			}
		}

		lw_array_free(block->r_block);
		zbx_free(block);
		lw_array_free(block_unsized);
		zbx_vector_ptr_remove(&blocks, 0);
	}

	zbx_vector_ptr_destroy(&blocks);

	zabbix_log(LOG_LEVEL_TRACE, "End of %s(): dim:%s", __func__, lw_array_to_str(dimensions));

	return dimensions;
}

/* modifies widget units in first argument */
static void	DBpatch_adjust_axis_dimensions(zbx_vector_char_t *d, zbx_vector_char_t *d_min, int target)
{
	int	dimensions_sum, i;

	zabbix_log(LOG_LEVEL_TRACE, "In %s(): d:%s", __func__, lw_array_to_str(d));
	zabbix_log(LOG_LEVEL_TRACE, "  d_min:%s", lw_array_to_str(d_min));

	dimensions_sum = lw_array_sum(d);

	while (dimensions_sum != target)
	{
		int	potential_index = -1;
		double	potential_value;

		for (i = 0; i < d->values_num; i++)
		{
			double	value;

			SKIP_EMPTY(d, i);
			value = (double)d->values[i] / d_min->values[i];

			if (0 > potential_index ||
					(dimensions_sum > target && value > potential_value) ||
					(dimensions_sum < target && value < potential_value))
			{
				potential_index = i;
				potential_value = value;
			}
		}

		zabbix_log(LOG_LEVEL_TRACE, "dim_sum:%d pot_idx/val:%d/%.2lf", dimensions_sum,
				potential_index, potential_value);

		if (dimensions_sum > target && d->values[potential_index] == d_min->values[potential_index])
			break;

		if (dimensions_sum > target)
		{
			d->values[potential_index]--;
			dimensions_sum--;
		}
		else
		{
			d->values[potential_index]++;
			dimensions_sum++;
		}
	}

	zabbix_log(LOG_LEVEL_TRACE, "End of %s(): d:%s", __func__, lw_array_to_str(d));
}

static void	DBpatch_get_dashboard_dimensions(zbx_vector_ptr_t *scr_items, zbx_vector_char_t **x,
		zbx_vector_char_t **y)
{
	zbx_vector_char_t	*dim_x_pref, *dim_x_min;
	zbx_vector_char_t	*dim_y_pref, *dim_y_min;
	zbx_vector_scitem_dim_t	items_x_pref, items_y_pref;
	zbx_vector_scitem_dim_t	items_x_min, items_y_min;
	int			i;

	zabbix_log(LOG_LEVEL_TRACE, "In %s()", __func__);

	zbx_vector_scitem_dim_create(&items_x_pref);
	zbx_vector_scitem_dim_create(&items_y_pref);
	zbx_vector_scitem_dim_create(&items_x_min);
	zbx_vector_scitem_dim_create(&items_y_min);

	for (i = 0; i < scr_items->values_num; i++)
	{
		int			pref_size_w, pref_size_h;
		int			min_size_w, min_size_h;
		zbx_screen_item_dim_t	item;
		zbx_db_screen_item_t	*si;

		si = scr_items->values[i];
		DBpatch_get_preferred_widget_size(si, &pref_size_w, &pref_size_h);
		DBpatch_get_min_widget_size(si, &min_size_w, &min_size_h);

		item.position = si->x;
		item.span = si->colspan;
		item.size = MAX(pref_size_w, min_size_w);
		zbx_vector_scitem_dim_append(&items_x_pref, item);

		item.position = si->y;
		item.span = si->rowspan;
		item.size = MAX(pref_size_h, min_size_h);
		zbx_vector_scitem_dim_append(&items_y_pref, item);

		item.position = si->x;
		item.span = si->colspan;
		item.size = min_size_w;
		zbx_vector_scitem_dim_append(&items_x_min, item);

		item.position = si->y;
		item.span = si->rowspan;
		item.size = min_size_h;
		zbx_vector_scitem_dim_append(&items_y_min, item);
	}

	dim_x_pref = DBpatch_get_axis_dimensions(&items_x_pref);
	dim_x_min = DBpatch_get_axis_dimensions(&items_x_min);

	zabbix_log(LOG_LEVEL_TRACE, "%s: dim_x_pref:%s", __func__, lw_array_to_str(dim_x_pref));
	zabbix_log(LOG_LEVEL_TRACE, "  dim_x_min:%s", lw_array_to_str(dim_x_min));

	DBpatch_adjust_axis_dimensions(dim_x_pref, dim_x_min, DASHBOARD_MAX_COLS);

	dim_y_pref = DBpatch_get_axis_dimensions(&items_y_pref);
	dim_y_min = DBpatch_get_axis_dimensions(&items_y_min);

	if (DASHBOARD_MAX_ROWS < lw_array_sum(dim_y_pref))
		DBpatch_adjust_axis_dimensions(dim_y_pref, dim_y_min, DASHBOARD_MAX_ROWS);

	lw_array_free(dim_x_min);
	lw_array_free(dim_y_min);
	zbx_vector_scitem_dim_destroy(&items_x_pref);
	zbx_vector_scitem_dim_destroy(&items_y_pref);
	zbx_vector_scitem_dim_destroy(&items_x_min);
	zbx_vector_scitem_dim_destroy(&items_y_min);

	*x = dim_x_pref;
	*y = dim_y_pref;

	zabbix_log(LOG_LEVEL_TRACE, "End of %s(): x:%s y:%s", __func__, lw_array_to_str(*x), lw_array_to_str(*y));
}

static zbx_db_widget_field_t	*DBpatch_make_widget_field(int type, char *name, void *value)
{
	zbx_db_widget_field_t	*wf;

	wf = (zbx_db_widget_field_t *)zbx_calloc(NULL, 1, sizeof(zbx_db_widget_field_t));
	wf->name = zbx_strdup(NULL, name);
	wf->type = type;

	switch (type)
	{
		case ZBX_WIDGET_FIELD_TYPE_INT32:
			wf->value_int = *((int *)value);
			break;
		case ZBX_WIDGET_FIELD_TYPE_STR:
			wf->value_str = zbx_strdup(NULL, (char *)value);
			break;
		case ZBX_WIDGET_FIELD_TYPE_GROUP:
			wf->value_groupid = *((uint64_t *)value);
			break;
		case ZBX_WIDGET_FIELD_TYPE_HOST:
			wf->value_hostid = *((uint64_t *)value);
			break;
		case ZBX_WIDGET_FIELD_TYPE_ITEM:
		case ZBX_WIDGET_FIELD_TYPE_ITEM_PROTOTYPE:
			wf->value_itemid = *((uint64_t *)value);
			break;
		case ZBX_WIDGET_FIELD_TYPE_GRAPH:
		case ZBX_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE:
			wf->value_graphid = *((uint64_t *)value);
			break;
		case ZBX_WIDGET_FIELD_TYPE_MAP:
			wf->value_sysmapid = *((uint64_t *)value);
			break;
		default:
			zabbix_log(LOG_LEVEL_WARNING, "%s: unknown field type: %d", __func__, type);
	}

	if (NULL == wf->value_str)
		wf->value_str = zbx_strdup(NULL, "");

	return wf;
}

static void DBpatch_widget_from_screen_item(zbx_db_screen_item_t *si, zbx_db_widget_t *w, zbx_vector_ptr_t *fields)
{
	zbx_db_widget_field_t	*f;
	int			tmp;
	char			*reference = NULL;

	w->name = zbx_strdup(NULL, "");
	w->view_mode = 0;	/* ZBX_WIDGET_VIEW_MODE_NORMAL */

#define ADD_FIELD(a, b, c)				\
							\
do {							\
	f = DBpatch_make_widget_field(a, b, c);		\
	zbx_vector_ptr_append(fields, (void *)f);	\
} while (0)

	switch (si->resourcetype)
	{
		case SCREEN_RESOURCE_CLOCK:
			w->type = zbx_strdup(NULL, ZBX_WIDGET_TYPE_CLOCK);

			/* here are below in this switch we add only those fields that are not */
			/* considered default by frontend API */

			if (0 != si->style)	/* style 0 is default, don't add */
				ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_INT32, "time_type", (void *)&si->style);
			if (2 == si->style)	/* TIME_TYPE_HOST */
				ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_ITEM, "itemid", (void *)&si->resourceid);
			break;
		case SCREEN_RESOURCE_GRAPH:
			w->type = zbx_strdup(NULL, ZBX_WIDGET_TYPE_GRAPH_CLASSIC);
			/* source_type = ZBX_WIDGET_FIELD_RESOURCE_GRAPH (0); don't add because it's default */
			ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_GRAPH, "graphid", (void *)&si->resourceid);
			break;
		case SCREEN_RESOURCE_SIMPLE_GRAPH:
			w->type = zbx_strdup(NULL, ZBX_WIDGET_TYPE_GRAPH_CLASSIC);
			tmp = 1;	/* source_type = ZBX_WIDGET_FIELD_RESOURCE_SIMPLE_GRAPH */
			ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_INT32, "source_type", (void *)&tmp);
			ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_ITEM, "itemid", (void *)&si->resourceid);
			break;
		case SCREEN_RESOURCE_LLD_GRAPH:
			w->type = zbx_strdup(NULL, ZBX_WIDGET_TYPE_GRAPH_PROTOTYPE);
			/* source_type = ZBX_WIDGET_FIELD_RESOURCE_GRAPH_PROTOTYPE (2); don't add because it's default */
			ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE, "graphid", (void *)&si->resourceid);
			/* add field "columns" because the default value is 2 */
			tmp = 1;
			ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_INT32, "columns", (void *)&tmp);
			/* don't add field "rows" because 1 is default */
			break;
		case SCREEN_RESOURCE_LLD_SIMPLE_GRAPH:
			w->type = zbx_strdup(NULL, ZBX_WIDGET_TYPE_GRAPH_PROTOTYPE);
			tmp = 3;	/* source_type = ZBX_WIDGET_FIELD_RESOURCE_SIMPLE_GRAPH_PROTOTYPE */
			ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_INT32, "source_type", (void *)&tmp);
			ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_ITEM_PROTOTYPE, "itemid", (void *)&si->resourceid);
			tmp = 1;
			ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_INT32, "columns", (void *)&tmp);
			/* don't add field "rows" because 1 is default */
			break;
		case SCREEN_RESOURCE_PLAIN_TEXT:
			w->type = zbx_strdup(NULL, ZBX_WIDGET_TYPE_PLAIN_TEXT);
			ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_ITEM, "itemids", (void *)&si->resourceid);
			if (0 != si->style)
				ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_INT32, "show_as_html", (void *)&si->style);
			if (25 != si->elements)
				ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_INT32, "show_lines", (void *)&si->elements);
			break;
		case SCREEN_RESOURCE_URL:
			w->type = zbx_strdup(NULL, ZBX_WIDGET_TYPE_URL);
			ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_STR, "url", (void *)si->url);
			break;
		case SCREEN_RESOURCE_ACTIONS:
			w->type = zbx_strdup(NULL, ZBX_WIDGET_TYPE_ACTIONS);
			if (4 != si->sort_triggers)
				ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_INT32, "sort_triggers", (void *)&si->sort_triggers);
			if (25 != si->elements)
				ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_INT32, "show_lines", (void *)&si->elements);
			break;
		case SCREEN_RESOURCE_DATA_OVERVIEW:
			w->type = zbx_strdup(NULL, ZBX_WIDGET_TYPE_DATA_OVERVIEW);
			tmp = 1;
			if (1 == si->style)
				ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_INT32, "style", (void *)&tmp);
			ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_GROUP, "groupids", (void *)&si->resourceid);
			if ('\0' != *si->application)
				ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_STR, "application", (void *)si->application);
			break;
		case SCREEN_RESOURCE_EVENTS:
			w->type = zbx_strdup(NULL, ZBX_WIDGET_TYPE_PROBLEMS);
			if (25 != si->elements)
				ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_INT32, "show_lines", (void *)&si->elements);
			tmp = 2;
			ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_INT32, "show", (void *)&tmp);
			break;
		case SCREEN_RESOURCE_HOSTGROUP_TRIGGERS:
			w->type = zbx_strdup(NULL, ZBX_WIDGET_TYPE_PROBLEMS);
			if (0 != si->sort_triggers)
				ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_INT32, "sort_triggers", (void *)&si->sort_triggers);
			if (25 != si->elements)
				ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_INT32, "show_lines", (void *)&si->elements);
			if (0 != si->resourceid)
				ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_GROUP, "groupids", (void *)&si->resourceid);
			tmp = 3;
			ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_INT32, "show", (void *)&tmp);
			tmp = 0;
			ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_INT32, "show_timeline", (void *)&tmp);
			break;
		case SCREEN_RESOURCE_HOST_INFO:
			w->type = zbx_strdup(NULL, ZBX_WIDGET_TYPE_HOST_INFO);
			tmp = 1;
			if (1 == si->style)
				ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_INT32, "layout", (void *)&tmp);
			if (0 != si->resourceid)
				ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_GROUP, "groupids", (void *)&si->resourceid);
			break;
		case SCREEN_RESOURCE_HOST_TRIGGERS:
			w->type = zbx_strdup(NULL, ZBX_WIDGET_TYPE_PROBLEMS);
			if (0 != si->sort_triggers)
				ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_INT32, "sort_triggers", (void *)&si->sort_triggers);
			if (25 != si->elements)
				ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_INT32, "show_lines", (void *)&si->elements);
			if (0 != si->resourceid)
				ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_HOST, "hostids", (void *)&si->resourceid);
			tmp = 3;
			ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_INT32, "show", (void *)&tmp);
			tmp = 0;
			ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_INT32, "show_timeline", (void *)&tmp);
			break;
		case SCREEN_RESOURCE_MAP:
			w->type = zbx_strdup(NULL, ZBX_WIDGET_TYPE_MAP);
			ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_MAP, "sysmapid", (void *)&si->resourceid);
			if (SUCCEED == DBpatch_reference_name(&reference))
				ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_STR, "reference", (void *)reference);
			break;
		case SCREEN_RESOURCE_SYSTEM_STATUS:
			w->type = zbx_strdup(NULL, ZBX_WIDGET_TYPE_SYSTEM_STATUS);
			break;
		case SCREEN_RESOURCE_SERVER_INFO:
			w->type = zbx_strdup(NULL, ZBX_WIDGET_TYPE_SERVER_INFO);
			break;
		case SCREEN_RESOURCE_TRIGGER_OVERVIEW:
			w->type = zbx_strdup(NULL, ZBX_WIDGET_TYPE_TRIGGER_OVERVIEW);
			ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_GROUP, "groupids", (void *)&si->resourceid);
			if ('\0' != *si->application)
				ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_STR, "application", (void *)si->application);
			tmp = 1;
			if (1 == si->style)
				ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_INT32, "style", (void *)&tmp);
			tmp = 2;
			ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_INT32, "show", (void *)&tmp);
			break;
		case SCREEN_RESOURCE_TRIGGER_INFO:
			w->type = zbx_strdup(NULL, ZBX_WIDGET_TYPE_SYSTEM_STATUS);
			if (0 != si->resourceid)
				ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_GROUP, "groupids", (void *)&si->resourceid);
			tmp = 1;
			if (1 == si->style)
				ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_INT32, "layout", (void *)&tmp);
			tmp = 1;
			ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_INT32, "show_type", (void *)&tmp);
			break;
		default:
			zabbix_log(LOG_LEVEL_WARNING, "%s: unknown screen resource type: %d", __func__,
					si->resourcetype);
	}
#undef ADD_FIELD
}

static char	*DBpatch_resourcetype_str(int rtype)
{
	switch (rtype)
	{
		case SCREEN_RESOURCE_CLOCK:
			return "clock";
		case SCREEN_RESOURCE_GRAPH:
			return "graph";
		case SCREEN_RESOURCE_SIMPLE_GRAPH:
			return "simplegraph";
		case SCREEN_RESOURCE_LLD_GRAPH:
			return "lldgraph";
		case SCREEN_RESOURCE_LLD_SIMPLE_GRAPH:
			return "lldsimplegraph";
		case SCREEN_RESOURCE_PLAIN_TEXT:
			return "plaintext";
		case SCREEN_RESOURCE_URL:
			return "url";
		/* additional types */
		case SCREEN_RESOURCE_MAP:
			return "map";
		case SCREEN_RESOURCE_HOST_INFO:
			return "host info";
		case SCREEN_RESOURCE_TRIGGER_INFO:
			return "trigger info";
		case SCREEN_RESOURCE_SERVER_INFO:
			return "server info";
		case SCREEN_RESOURCE_TRIGGER_OVERVIEW:
			return "trigger overview";
		case SCREEN_RESOURCE_DATA_OVERVIEW:
			return "data overview";
		case SCREEN_RESOURCE_ACTIONS:
			return "action";
		case SCREEN_RESOURCE_EVENTS:
			return "events";
		case SCREEN_RESOURCE_HOSTGROUP_TRIGGERS:
			return "hostgroup triggers";
		case SCREEN_RESOURCE_SYSTEM_STATUS:
			return "system status";
		case SCREEN_RESOURCE_HOST_TRIGGERS:
			return "host triggers";
	}

	return "*unknown*";
}

static void	DBpatch_trace_screen_item(zbx_db_screen_item_t *item)
{
	zabbix_log(LOG_LEVEL_TRACE, "    screenitemid:" ZBX_FS_UI64 " screenid:" ZBX_FS_UI64,
			item->screenitemid, item->screenid);
	zabbix_log(LOG_LEVEL_TRACE, "        resourcetype: %s resourceid:" ZBX_FS_UI64,
			DBpatch_resourcetype_str(item->resourcetype), item->resourceid);
	zabbix_log(LOG_LEVEL_TRACE, "        w/h: %dx%d (x,y): (%d,%d) (c,rspan): (%d,%d)",
			item->width, item->height, item->x, item->y, item->colspan, item->rowspan);
}

static void	DBpatch_trace_widget(zbx_db_widget_t *w)
{
	zabbix_log(LOG_LEVEL_TRACE, "    widgetid:" ZBX_FS_UI64 " dbid:" ZBX_FS_UI64 " type:%s",
			w->widgetid, w->dashboardid, w->type);
	zabbix_log(LOG_LEVEL_TRACE, "    widget type: %s w/h: %dx%d (x,y): (%d,%d)",
			w->type, w->width, w->height, w->x, w->y);
}

/* adds new dashboard to the DB, sets new dashboardid in the struct */
static int 	DBpatch_add_dashboard(zbx_db_dashboard_t *dashboard)
{
	char	*name_esc;
	int	res;

	dashboard->dashboardid = DBget_maxid("dashboard");
	name_esc = DBdyn_escape_string(dashboard->name);

	zabbix_log(LOG_LEVEL_TRACE, "adding dashboard id:" ZBX_FS_UI64, dashboard->dashboardid);

	if (0 != dashboard->templateid)
	{
		res = DBexecute("insert into dashboard (dashboardid,name,templateid) values ("
				ZBX_FS_UI64 ",'%s'," ZBX_FS_UI64 ")", dashboard->dashboardid, name_esc,
				dashboard->templateid);
	}
	else
	{
		res = DBexecute("insert into dashboard (dashboardid,name,userid,private) values "
				"("ZBX_FS_UI64 ",'%s',"ZBX_FS_UI64 ",%d)",
				dashboard->dashboardid, name_esc, dashboard->userid, dashboard->private);
	}

	zbx_free(name_esc);

	return ZBX_DB_OK > res ? FAIL : SUCCEED;
}

/* adds new dashboard page to the DB, sets new dashboard_pageid in the struct */
static int 	DBpatch_add_dashboard_page(zbx_db_dashboard_page_t *dashboard_page, uint64_t dashboardid)
{
	int	res;

	dashboard_page->dashboard_pageid = DBget_maxid("dashboard_page");
	dashboard_page->dashboardid = dashboardid;

	zabbix_log(LOG_LEVEL_TRACE, "adding dashboard_page id:" ZBX_FS_UI64, dashboard_page->dashboard_pageid);

	res = DBexecute("insert into dashboard_page (dashboard_pageid,dashboardid) values ("ZBX_FS_UI64 ","
			ZBX_FS_UI64 ")", dashboard_page->dashboard_pageid, dashboardid);

	return ZBX_DB_OK > res ? FAIL : SUCCEED;
}

/* adds new widget and widget fields to the DB */
static int	DBpatch_add_widget(uint64_t dashboardid, zbx_db_widget_t *widget, zbx_vector_ptr_t *fields,
		uint64_t templateid)
{
	uint64_t	new_fieldid;
	int		i, ret = SUCCEED;
	char		*name_esc;
	char		*dashboard_idname;

	widget->widgetid = DBget_maxid("widget");
	widget->dashboardid = dashboardid;
	name_esc = DBdyn_escape_string(widget->name);

	zabbix_log(LOG_LEVEL_TRACE, "adding widget id: " ZBX_FS_UI64 ", type: %s", widget->widgetid, widget->type);

	if (0 == templateid)
		dashboard_idname = zbx_strdup(NULL, "dashboard_pageid");
	else
		dashboard_idname = zbx_strdup(NULL, "dashboardid");

	if (ZBX_DB_OK > DBexecute("insert into widget (widgetid,%s,type,name,x,y,width,height,view_mode) "
			"values (" ZBX_FS_UI64 "," ZBX_FS_UI64 ",'%s','%s',%d,%d,%d,%d,%d)",
			dashboard_idname, widget->widgetid, widget->dashboardid, widget->type, name_esc,
			widget->x, widget->y, widget->width, widget->height, widget->view_mode))
	{
		ret = FAIL;
	}

	zbx_free(name_esc);
	zbx_free(dashboard_idname);

	if (SUCCEED == ret && 0 < fields->values_num)
		new_fieldid = DBget_maxid_num("widget_field", fields->values_num);

	for (i = 0; SUCCEED == ret && i < fields->values_num; i++)
	{
		char			s1[ZBX_MAX_UINT64_LEN + 1], s2[ZBX_MAX_UINT64_LEN + 1],
					s3[ZBX_MAX_UINT64_LEN + 1], s4[ZBX_MAX_UINT64_LEN + 1],
					s5[ZBX_MAX_UINT64_LEN + 1], *url_esc;
		zbx_db_widget_field_t	*f;

		f = (zbx_db_widget_field_t *)fields->values[i];
		url_esc = DBdyn_escape_string(f->value_str);

		if (0 != f->value_itemid)
			zbx_snprintf(s1, ZBX_MAX_UINT64_LEN + 1, ZBX_FS_UI64, f->value_itemid);
		else
			zbx_snprintf(s1, ZBX_MAX_UINT64_LEN + 1, "null");

		if (0 != f->value_graphid)
			zbx_snprintf(s2, ZBX_MAX_UINT64_LEN + 1, ZBX_FS_UI64, f->value_graphid);
		else
			zbx_snprintf(s2, ZBX_MAX_UINT64_LEN + 1, "null");

		if (0 != f->value_groupid)
			zbx_snprintf(s3, ZBX_MAX_UINT64_LEN + 1, ZBX_FS_UI64, f->value_groupid);
		else
			zbx_snprintf(s3, ZBX_MAX_UINT64_LEN + 1, "null");

		if (0 != f->value_hostid)
			zbx_snprintf(s4, ZBX_MAX_UINT64_LEN + 1, ZBX_FS_UI64, f->value_hostid);
		else
			zbx_snprintf(s4, ZBX_MAX_UINT64_LEN + 1, "null");

		if (0 != f->value_sysmapid)
			zbx_snprintf(s5, ZBX_MAX_UINT64_LEN + 1, ZBX_FS_UI64, f->value_sysmapid);
		else
			zbx_snprintf(s5, ZBX_MAX_UINT64_LEN + 1, "null");

		if (ZBX_DB_OK > DBexecute("insert into widget_field (widget_fieldid,widgetid,type,name,value_int,"
				"value_str,value_itemid,value_graphid,value_groupid,value_hostid,value_sysmapid)"
				" values (" ZBX_FS_UI64 "," ZBX_FS_UI64 ",%d,'%s',%d,'%s',%s,%s,%s,%s,%s)",
				new_fieldid++, widget->widgetid, f->type, f->name, f->value_int, url_esc,
				s1, s2, s3, s4, s5))
		{
			ret = FAIL;
		}

		zbx_free(url_esc);
	}

	return ret;
}

static int DBpatch_set_permissions(uint64_t dashboardid, uint64_t screenid)
{
	int		ret = SUCCEED, permission;
	uint64_t	userid, usrgrpid, dashboard_userid, dashboard_usrgrpid;
	DB_RESULT	result;
	DB_ROW		row;

	dashboard_userid = DBget_maxid("dashboard_user");

	result = DBselect("select userid, permission from screen_user where screenid=" ZBX_FS_UI64, screenid);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(userid, row[0]);
		permission = atoi(row[1]);

		DBexecute("insert into dashboard_user (dashboard_userid,dashboardid,userid,permission)"
			" values ("ZBX_FS_UI64 ","ZBX_FS_UI64 ","ZBX_FS_UI64 ", %d)",
			dashboard_userid++, dashboardid, userid, permission);
	}

	DBfree_result(result);

	dashboard_usrgrpid = DBget_maxid("dashboard_usrgrp");

	result = DBselect("select usrgrpid, permission from screen_usrgrp where screenid=" ZBX_FS_UI64, screenid);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(usrgrpid, row[0]);
		permission = atoi(row[1]);

		DBexecute("insert into dashboard_usrgrp (dashboard_usrgrpid,dashboardid,usrgrpid,permission)"
			" values ("ZBX_FS_UI64 ","ZBX_FS_UI64 ","ZBX_FS_UI64 ", %d)",
			dashboard_usrgrpid++, dashboardid, usrgrpid, permission);
	}

	DBfree_result(result);

	return ret;
}

int	DBpatch_delete_screen(uint64_t screenid)
{
	if (ZBX_DB_OK > DBexecute("delete from screens_items where screenid=" ZBX_FS_UI64, screenid))
		return FAIL;

	if (ZBX_DB_OK > DBexecute("delete from screens where screenid=" ZBX_FS_UI64, screenid))
		return FAIL;

	return SUCCEED;
}

#define OFFSET_ARRAY_SIZE	(SCREEN_MAX_ROWS + 1)

int	DBpatch_convert_screen(uint64_t screenid, char *name, uint64_t templateid, uint64_t userid, int private)
{
	DB_RESULT		result;
	DB_ROW			row;
	int			i, ret;
	zbx_db_screen_item_t	*scr_item;
	zbx_db_dashboard_t	dashboard;
	zbx_db_dashboard_page_t	dashboard_page;
	zbx_vector_ptr_t	screen_items;
	zbx_vector_char_t	*dim_x, *dim_y;
	int			offsets_x[OFFSET_ARRAY_SIZE], offsets_y[OFFSET_ARRAY_SIZE];
	uint64_t		id;

	result = DBselect(
			"select screenitemid,screenid,resourcetype,resourceid,width,height,x,y,colspan,rowspan"
			",elements,style,url,max_columns,sort_triggers,application from screens_items"
			" where screenid=" ZBX_FS_UI64, screenid);

	if (NULL == result)
		return FAIL;

	if (SUCCEED != DBpatch_init_dashboard(&dashboard, name, templateid, userid, private))
	{
		zabbix_log(LOG_LEVEL_ERR, "Cannot convert screen '%s'due to name collision.", name);
		return FAIL;
	}

	zbx_vector_ptr_create(&screen_items);

	while (NULL != (row = DBfetch(result)))
	{
		scr_item = (zbx_db_screen_item_t*)zbx_calloc(NULL, 1, sizeof(zbx_db_screen_item_t));

		ZBX_DBROW2UINT64(scr_item->screenitemid, row[0]);
		ZBX_DBROW2UINT64(scr_item->screenid, row[1]);
		scr_item->resourcetype = atoi(row[2]);
		ZBX_DBROW2UINT64(scr_item->resourceid, row[3]);
		scr_item->width = atoi(row[4]);
		scr_item->height = atoi(row[5]);
		scr_item->x = atoi(row[6]);
		scr_item->y = atoi(row[7]);
		scr_item->colspan = atoi(row[8]);
		scr_item->rowspan = atoi(row[9]);
		scr_item->elements = atoi(row[10]);
		scr_item->style = atoi(row[11]);
		scr_item->url = zbx_strdup(NULL, row[12]);
		scr_item->max_columns = atoi(row[13]);
		scr_item->sort_triggers = atoi(row[14]);
		scr_item->application = zbx_strdup(NULL, row[15]);

		DBpatch_trace_screen_item(scr_item);

		if (0 != templateid && 0 == DBpatch_is_convertible_screen_item(scr_item->resourcetype))
		{
			zabbix_log(LOG_LEVEL_WARNING, "discarding screen item " ZBX_FS_UI64
					" because it is not convertible", scr_item->screenitemid);
			DBpatch_screen_item_free(scr_item);
			continue;
		}
		else
			zbx_vector_ptr_append(&screen_items, (void *)scr_item);
	}

	DBfree_result(result);

	if (screen_items.values_num > 0)
	{
		zabbix_log(LOG_LEVEL_TRACE, "total %d screen items", screen_items.values_num);

		DBpatch_normalize_screen_items_pos(&screen_items);
		DBpatch_get_dashboard_dimensions(&screen_items, &dim_x, &dim_y);

		lw_array_debug("dim_x", dim_x);
		lw_array_debug("dim_y", dim_y);

		offsets_x[0] = 0;
		offsets_y[0] = 0;
		for (i = 1; i < OFFSET_ARRAY_SIZE; i++)
		{
			offsets_x[i] = -1;
			offsets_y[i] = -1;
		}

		for (i = 0; i < dim_x->values_num; i++)
		{
			if (POS_EMPTY != dim_x->values[i])
				offsets_x[i + 1] = i == 0 ? dim_x->values[i] : offsets_x[i] + dim_x->values[i];
			if (POS_EMPTY != dim_y->values[i])
				offsets_y[i + 1] = i == 0 ? dim_y->values[i] : offsets_y[i] + dim_y->values[i];
		}

		int_array_debug("offsets_x", offsets_x, OFFSET_ARRAY_SIZE, -1);
		int_array_debug("offsets_y", offsets_y, OFFSET_ARRAY_SIZE, -1);
	}

	ret = DBpatch_add_dashboard(&dashboard);
	id = dashboard.dashboardid;

	if (SUCCEED == ret && 0 == templateid)
	{
		ret = DBpatch_add_dashboard_page(&dashboard_page, dashboard.dashboardid);
		id = dashboard_page.dashboard_pageid;
	}

	for (i = 0; SUCCEED == ret && i < screen_items.values_num; i++)
	{
		int			offset_idx_x, offset_idx_y;
		zbx_db_widget_t		w;
		zbx_vector_ptr_t	widget_fields;
		zbx_db_screen_item_t	*si;

		si = screen_items.values[i];

		offset_idx_x = si->x + si->colspan;
		if (offset_idx_x > OFFSET_ARRAY_SIZE - 1)
		{
			offset_idx_x = OFFSET_ARRAY_SIZE - 1;
			zabbix_log(LOG_LEVEL_WARNING, "config error, x screen size overflow for item " ZBX_FS_UI64,
					si->screenitemid);
		}

		offset_idx_y = si->y + si->rowspan;
		if (offset_idx_y > OFFSET_ARRAY_SIZE - 1)
		{
			offset_idx_y = OFFSET_ARRAY_SIZE - 1;
			zabbix_log(LOG_LEVEL_WARNING, "config error, y screen size overflow for item " ZBX_FS_UI64,
					si->screenitemid);
		}

		memset((void *)&w, 0, sizeof(zbx_db_widget_t));
		w.x = offsets_x[si->x];
		w.y = offsets_y[si->y];
		w.width = offsets_x[offset_idx_x] - offsets_x[si->x];
		w.height = offsets_y[offset_idx_y] - offsets_y[si->y];

		/* skip screen items not fitting on the dashboard */
		if (w.x + w.width > DASHBOARD_MAX_COLS || w.y + w.height > DASHBOARD_MAX_ROWS)
		{
			zabbix_log(LOG_LEVEL_WARNING, "skipping screenitemid " ZBX_FS_UI64
					" (too wide, tall or offscreen)", si->screenitemid);
			continue;
		}

		zbx_vector_ptr_create(&widget_fields);

		DBpatch_widget_from_screen_item(si, &w, &widget_fields);

		ret = DBpatch_add_widget(id, &w, &widget_fields, templateid);

		DBpatch_trace_widget(&w);

		zbx_vector_ptr_clear_ext(&widget_fields, (zbx_clean_func_t)DBpatch_widget_field_free);
		zbx_vector_ptr_destroy(&widget_fields);
		zbx_free(w.name);
		zbx_free(w.type);
	}

	ret = DBpatch_set_permissions(dashboard.dashboardid, screenid);

	zbx_free(dashboard.name);

	if (screen_items.values_num > 0)
	{
		lw_array_free(dim_x);
		lw_array_free(dim_y);
	}

	zbx_vector_ptr_clear_ext(&screen_items, (zbx_clean_func_t)DBpatch_screen_item_free);
	zbx_vector_ptr_destroy(&screen_items);

	return ret;
}

int	DBpatch_convert_slideshow(void)
{
	return SUCCEED;
}

#undef OFFSET_ARRAY_SIZE

#undef DASHBOARD_MAX_COLS
#undef DASHBOARD_MAX_ROWS
#undef DASHBOARD_WIDGET_MIN_ROWS
#undef DASHBOARD_WIDGET_MAX_ROWS

#undef SCREEN_MAX_ROWS
#undef SCREEN_MAX_COLS
#undef SCREEN_RESOURCE_CLOCK
#undef SCREEN_RESOURCE_GRAPH
#undef SCREEN_RESOURCE_SIMPLE_GRAPH
#undef SCREEN_RESOURCE_LLD_GRAPH
#undef SCREEN_RESOURCE_LLD_SIMPLE_GRAPH
#undef SCREEN_RESOURCE_PLAIN_TEXT
#undef SCREEN_RESOURCE_URL

#undef SCREEN_RESOURCE_MAP
#undef SCREEN_RESOURCE_HOST_INFO
#undef SCREEN_RESOURCE_TRIGGER_INFO
#undef SCREEN_RESOURCE_SERVER_INFO
#undef SCREEN_RESOURCE_SCREEN
#undef SCREEN_RESOURCE_TRIGGER_OVERVIEW
#undef SCREEN_RESOURCE_DATA_OVERVIEW
#undef SCREEN_RESOURCE_ACTIONS
#undef SCREEN_RESOURCE_EVENTS
#undef SCREEN_RESOURCE_HOSTGROUP_TRIGGERS
#undef SCREEN_RESOURCE_SYSTEM_STATUS
#undef SCREEN_RESOURCE_HOST_TRIGGERS

#undef ZBX_WIDGET_FIELD_TYPE_INT32
#undef ZBX_WIDGET_FIELD_TYPE_STR
#undef ZBX_WIDGET_FIELD_TYPE_ITEM
#undef ZBX_WIDGET_FIELD_TYPE_ITEM_PROTOTYPE
#undef ZBX_WIDGET_FIELD_TYPE_GRAPH
#undef ZBX_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE

#undef ZBX_WIDGET_FIELD_RESOURCE_GRAPH
#undef ZBX_WIDGET_FIELD_RESOURCE_SIMPLE_GRAPH
#undef ZBX_WIDGET_FIELD_RESOURCE_GRAPH_PROTOTYPE
#undef ZBX_WIDGET_FIELD_RESOURCE_SIMPLE_GRAPH_PROTOTYPE

#undef ZBX_WIDGET_TYPE_CLOCK
#undef ZBX_WIDGET_TYPE_GRAPH_CLASSIC
#undef ZBX_WIDGET_TYPE_GRAPH_PROTOTYPE
#undef ZBX_WIDGET_TYPE_PLAIN_TEXT
#undef ZBX_WIDGET_TYPE_URL
#undef POS_EMPTY
#undef POS_TAKEN
#undef SKIP_EMPTY

#endif	/* not HAVE_SQLITE3 */

extern zbx_dbpatch_t	DBPATCH_VERSION(2010)[];
extern zbx_dbpatch_t	DBPATCH_VERSION(2020)[];
extern zbx_dbpatch_t	DBPATCH_VERSION(2030)[];
extern zbx_dbpatch_t	DBPATCH_VERSION(2040)[];
extern zbx_dbpatch_t	DBPATCH_VERSION(2050)[];
extern zbx_dbpatch_t	DBPATCH_VERSION(3000)[];
extern zbx_dbpatch_t	DBPATCH_VERSION(3010)[];
extern zbx_dbpatch_t	DBPATCH_VERSION(3020)[];
extern zbx_dbpatch_t	DBPATCH_VERSION(3030)[];
extern zbx_dbpatch_t	DBPATCH_VERSION(3040)[];
extern zbx_dbpatch_t	DBPATCH_VERSION(3050)[];
extern zbx_dbpatch_t	DBPATCH_VERSION(4000)[];
extern zbx_dbpatch_t	DBPATCH_VERSION(4010)[];
extern zbx_dbpatch_t	DBPATCH_VERSION(4020)[];
extern zbx_dbpatch_t	DBPATCH_VERSION(4030)[];
extern zbx_dbpatch_t	DBPATCH_VERSION(4040)[];
extern zbx_dbpatch_t	DBPATCH_VERSION(4050)[];
extern zbx_dbpatch_t	DBPATCH_VERSION(5000)[];
extern zbx_dbpatch_t	DBPATCH_VERSION(5010)[];
extern zbx_dbpatch_t	DBPATCH_VERSION(5020)[];
extern zbx_dbpatch_t	DBPATCH_VERSION(5030)[];

static zbx_db_version_t dbversions[] = {
	{DBPATCH_VERSION(2010), "2.2 development"},
	{DBPATCH_VERSION(2020), "2.2 maintenance"},
	{DBPATCH_VERSION(2030), "2.4 development"},
	{DBPATCH_VERSION(2040), "2.4 maintenance"},
	{DBPATCH_VERSION(2050), "3.0 development"},
	{DBPATCH_VERSION(3000), "3.0 maintenance"},
	{DBPATCH_VERSION(3010), "3.2 development"},
	{DBPATCH_VERSION(3020), "3.2 maintenance"},
	{DBPATCH_VERSION(3030), "3.4 development"},
	{DBPATCH_VERSION(3040), "3.4 maintenance"},
	{DBPATCH_VERSION(3050), "4.0 development"},
	{DBPATCH_VERSION(4000), "4.0 maintenance"},
	{DBPATCH_VERSION(4010), "4.2 development"},
	{DBPATCH_VERSION(4020), "4.2 maintenance"},
	{DBPATCH_VERSION(4030), "4.4 development"},
	{DBPATCH_VERSION(4040), "4.4 maintenance"},
	{DBPATCH_VERSION(4050), "5.0 development"},
	{DBPATCH_VERSION(5000), "5.0 maintenance"},
	{DBPATCH_VERSION(5010), "5.2 development"},
	{DBPATCH_VERSION(5020), "5.2 maintenance"},
	{DBPATCH_VERSION(5030), "5.4 development"},
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
	const char		*dbversion_table_name = "dbversion";
	int			db_mandatory, db_optional, required, ret = FAIL, i;
	zbx_db_version_t	*dbversion;
	zbx_dbpatch_t		*patches;

#ifndef HAVE_SQLITE3
	int			total = 0, current = 0, completed, last_completed = -1, optional_num = 0;
#endif
	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

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
		zabbix_log(LOG_LEVEL_DEBUG, "%s() \"%s\" does not exist", __func__, dbversion_table_name);

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
		patches = dbversion->patches;

		for (i = 0; 0 != patches[i].version; i++)
		{
			static sigset_t	orig_mask, mask;

			if (db_optional >= patches[i].version)
				continue;

			/* block signals to prevent interruption of statements that cause an implicit commit */
			sigemptyset(&mask);
			sigaddset(&mask, SIGTERM);
			sigaddset(&mask, SIGINT);
			sigaddset(&mask, SIGQUIT);

			if (0 > sigprocmask(SIG_BLOCK, &mask, &orig_mask))
				zabbix_log(LOG_LEVEL_WARNING, "cannot set sigprocmask to block the user signal");

			DBbegin();

			/* skipping the duplicated patches */
			if ((0 != patches[i].duplicates && patches[i].duplicates <= db_optional) ||
					SUCCEED == (ret = patches[i].function()))
			{
				ret = DBset_version(patches[i].version, patches[i].mandatory);
			}

			ret = DBend(ret);

			if (0 > sigprocmask(SIG_SETMASK, &orig_mask, NULL))
				zabbix_log(LOG_LEVEL_WARNING,"cannot restore sigprocmask");

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

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

int	DBcheck_double_type(void)
{
	DB_RESULT	result;
	DB_ROW		row;
	char		*sql = NULL;
	const int	total_dbl_cols = 4;
	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	DBconnect(ZBX_DB_CONNECT_NORMAL);

#if defined(HAVE_MYSQL)
	sql = DBdyn_escape_string(CONFIG_DBNAME);
	sql = zbx_dsprintf(sql, "select count(*) from information_schema.columns"
			" where table_schema='%s' and column_type='double'", sql);
#elif defined(HAVE_POSTGRESQL)
	sql = DBdyn_escape_string(NULL == CONFIG_DBSCHEMA || '\0' == *CONFIG_DBSCHEMA ? "public" : CONFIG_DBSCHEMA);
	sql = zbx_dsprintf(sql, "select count(*) from information_schema.columns"
			" where table_schema='%s' and data_type='double precision'", sql);
#elif defined(HAVE_ORACLE)
	sql = zbx_strdup(sql, "select count(*) from user_tab_columns"
			" where data_type='BINARY_DOUBLE'");
#elif defined(HAVE_SQLITE3)
	/* upgrade patch is not required for sqlite3 */
	ret = SUCCEED;
	goto out;
#endif

	if (NULL == (result = DBselect("%s"
			" and ((lower(table_name)='trends'"
					" and (lower(column_name) in ('value_min', 'value_avg', 'value_max')))"
			" or (lower(table_name)='history' and lower(column_name)='value'))", sql)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot select records with columns information");
		goto out;
	}

	if (NULL != (row = DBfetch(result)) && total_dbl_cols == atoi(row[0]))
		ret = SUCCEED;

	DBfree_result(result);
out:
	DBclose();
	zbx_free(sql);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return ret;
}
