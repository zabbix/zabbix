/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

#include "dbupgrade.h"
#include "zbxdbupgrade.h"

#include "zbxdbschema.h"
#include "zbxtime.h"
#include "zbxdb.h"
#include "zbxstr.h"
#include "zbx_ha_constants.h"
#include "zbxdbhigh.h"

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

#if defined(HAVE_MYSQL)
#	define ZBX_TYPE_ID_STR			"bigint unsigned"
#	define ZBX_TYPE_FLOAT_STR		"double precision"
#	define ZBX_TYPE_UINT_STR		"bigint unsigned"
#	define ZBX_TYPE_LONGTEXT_STR		"longtext"
#	define ZBX_TYPE_BLOB_STR		"longblob"
#	define ZBX_TYPE_SERIAL_STR		"bigint unsigned"
#	define ZBX_TYPE_SERIAL_SUFFIX_STR	"auto_increment"
#elif defined(HAVE_POSTGRESQL)
#	define ZBX_TYPE_ID_STR			"bigint"
#	define ZBX_TYPE_FLOAT_STR		"double precision"
#	define ZBX_TYPE_UINT_STR		"numeric(20)"
#	define ZBX_TYPE_LONGTEXT_STR		"text"
#	define ZBX_TYPE_BLOB_STR		"bytea"
#	define ZBX_TYPE_SERIAL_STR		"bigserial"
#	define ZBX_TYPE_SERIAL_SUFFIX_STR	""
#endif

#define ZBX_TYPE_INT_STR	"integer"
#define ZBX_TYPE_CHAR_STR	"varchar"
#define ZBX_TYPE_TEXT_STR	"text"

#define ZBX_FIRST_DB_VERSION		2010000

#ifndef HAVE_SQLITE3
static void	DBfield_type_string(char **sql, size_t *sql_alloc, size_t *sql_offset, const zbx_db_field_t *field)
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
		case ZBX_TYPE_LONGTEXT:
			zbx_strcpy_alloc(sql, sql_alloc, sql_offset, ZBX_TYPE_LONGTEXT_STR);
			break;
		case ZBX_TYPE_TEXT:
			zbx_strcpy_alloc(sql, sql_alloc, sql_offset, ZBX_TYPE_TEXT_STR);
			break;
		case ZBX_TYPE_BLOB:
			zbx_strcpy_alloc(sql, sql_alloc, sql_offset, ZBX_TYPE_BLOB_STR);
			break;
		case ZBX_TYPE_CUID:
			zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%s(%d)", ZBX_TYPE_CHAR_STR, CUID_LEN - 1);
			break;
		case ZBX_TYPE_SERIAL:
			zbx_strcpy_alloc(sql, sql_alloc, sql_offset, ZBX_TYPE_SERIAL_STR);
			break;
		default:
			zbx_this_should_never_happen_backtrace();
			assert(0);
	}
}

static void	DBfield_type_suffix_string(char **sql, size_t *sql_alloc, size_t *sql_offset,
		const zbx_db_field_t *field)
{
	switch (field->type)
	{
		case ZBX_TYPE_ID:
		case ZBX_TYPE_INT:
		case ZBX_TYPE_CHAR:
		case ZBX_TYPE_FLOAT:
		case ZBX_TYPE_UINT:
		case ZBX_TYPE_LONGTEXT:
		case ZBX_TYPE_TEXT:
		case ZBX_TYPE_BLOB:
		case ZBX_TYPE_CUID:
			return;
		case ZBX_TYPE_SERIAL:
			zbx_snprintf_alloc(sql, sql_alloc, sql_offset, " %s", ZBX_TYPE_SERIAL_SUFFIX_STR);
			break;
		default:
			zbx_this_should_never_happen_backtrace();
			assert(0);
	}
}

static void	DBfield_definition_string(char **sql, size_t *sql_alloc, size_t *sql_offset,
		const zbx_db_field_t *field)
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
			case ZBX_TYPE_LONGTEXT:
				/* MySQL: BLOB and TEXT columns cannot be assigned a default value */
				break;
			default:
#endif
				default_value_esc = zbx_db_dyn_escape_string(field->default_value);
				zbx_snprintf_alloc(sql, sql_alloc, sql_offset, " default '%s'", default_value_esc);
				zbx_free(default_value_esc);
#if defined(HAVE_MYSQL)
		}
#endif
	}

	if (0 != (field->flags & ZBX_NOTNULL))
	{
		zbx_strcpy_alloc(sql, sql_alloc, sql_offset, " not null");
	}

	DBfield_type_suffix_string(sql, sql_alloc, sql_offset, field);
}

static void	DBcreate_table_sql(char **sql, size_t *sql_alloc, size_t *sql_offset, const zbx_db_table_t *table)
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

static void	DBset_default_sql(char **sql, size_t *sql_alloc, size_t *sql_offset, const char *table_name,
		const zbx_db_field_t *field)
{
	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "alter table %s" ZBX_DB_ALTER_COLUMN " ", table_name);

#if defined(HAVE_MYSQL)
	DBfield_definition_string(sql, sql_alloc, sql_offset, field);
#else
	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%s set default '%s'", field->name, field->default_value);
#endif
}

static void	DBdrop_default_sql(char **sql, size_t *sql_alloc, size_t *sql_offset, const char *table_name,
		const zbx_db_field_t *field)
{
	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "alter table %s" ZBX_DB_ALTER_COLUMN " ", table_name);

#if defined(HAVE_MYSQL)
	DBfield_definition_string(sql, sql_alloc, sql_offset, field);
#else
	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%s drop default", field->name);
#endif
}

static void	DBmodify_field_type_sql(char **sql, size_t *sql_alloc, size_t *sql_offset, const char *table_name,
		const zbx_db_field_t *field)
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

static void	DBdrop_not_null_sql(char **sql, size_t *sql_alloc, size_t *sql_offset, const char *table_name,
		const zbx_db_field_t *field)
{
	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "alter table %s" ZBX_DB_ALTER_COLUMN " ", table_name);

#if defined(HAVE_MYSQL)
	DBfield_definition_string(sql, sql_alloc, sql_offset, field);
#else
	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%s drop not null", field->name);
#endif
}

static void	DBset_not_null_sql(char **sql, size_t *sql_alloc, size_t *sql_offset, const char *table_name,
		const zbx_db_field_t *field)
{
	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "alter table %s" ZBX_DB_ALTER_COLUMN " ", table_name);

#if defined(HAVE_MYSQL)
	DBfield_definition_string(sql, sql_alloc, sql_offset, field);
#else
	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%s set not null", field->name);
#endif
}

static void	DBadd_field_sql(char **sql, size_t *sql_alloc, size_t *sql_offset, const char *table_name,
		const zbx_db_field_t *field)
{
	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "alter table " ZBX_FS_SQL_NAME " add ", table_name);
	DBfield_definition_string(sql, sql_alloc, sql_offset, field);
}

static void	DBrename_field_sql(char **sql, size_t *sql_alloc, size_t *sql_offset, const char *table_name,
		const char *field_name, const zbx_db_field_t *field)
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
#elif defined(HAVE_POSTGRESQL)
	ZBX_UNUSED(table_name);
	ZBX_UNUSED(fields);
	ZBX_UNUSED(unique);
	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "alter index %s rename to %s", old_name, new_name);
#endif
}

static void	DBadd_foreign_key_sql(char **sql, size_t *sql_alloc, size_t *sql_offset, const char *table_name,
		int id, const zbx_db_field_t *field)
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

int	DBcreate_table(const zbx_db_table_t *table)
{
	char	*sql = NULL;
	size_t	sql_alloc = 0, sql_offset = 0;
	int	ret = FAIL;

	DBcreate_table_sql(&sql, &sql_alloc, &sql_offset, table);

	if (ZBX_DB_OK <= zbx_db_execute("%s", sql))
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

	if (ZBX_DB_OK <= zbx_db_execute("%s", sql))
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

	if (ZBX_DB_OK <= zbx_db_execute("%s", sql))
		ret = SUCCEED;

	zbx_free(sql);

	return ret;
}

int	DBadd_field(const char *table_name, const zbx_db_field_t *field)
{
	char	*sql = NULL;
	size_t	sql_alloc = 0, sql_offset = 0;
	int	ret = FAIL;

	DBadd_field_sql(&sql, &sql_alloc, &sql_offset, table_name, field);

	if (ZBX_DB_OK <= zbx_db_execute("%s", sql))
		ret = SUCCEED;

	zbx_free(sql);

	return ret;
}

int	DBrename_field(const char *table_name, const char *field_name, const zbx_db_field_t *field)
{
	char	*sql = NULL;
	size_t	sql_alloc = 0, sql_offset = 0;
	int	ret = FAIL;

	DBrename_field_sql(&sql, &sql_alloc, &sql_offset, table_name, field_name, field);

	if (ZBX_DB_OK <= zbx_db_execute("%s", sql))
		ret = SUCCEED;

	zbx_free(sql);

	return ret;
}

#if defined(HAVE_POSTGRESQL)
int	DBcheck_field_type(const char *table_name, const zbx_db_field_t *field)
{
	zbx_db_result_t	result;
	int		ret;
	char		*sql = NULL;
	size_t		sql_alloc, sql_offset;

	DBfield_type_string(&sql, &sql_alloc, &sql_offset, field);

	result = zbx_db_select(
			"select 1"
			" from information_schema.columns"
			" where table_name='%s'"
				" and column_name='%s'"
				" and data_type='%s'"
				" and table_schema='%s'",
			table_name, field->name, sql, zbx_db_get_schema_esc());

	ret = (NULL == zbx_db_fetch(result) ? FAIL : SUCCEED);
	zbx_db_free_result(result);

	zbx_free(sql);

	return ret;
}
#endif

int	DBmodify_field_type(const char *table_name, const zbx_db_field_t *field, const zbx_db_field_t *old_field)
{
	char	*sql = NULL;
	size_t	sql_alloc = 0, sql_offset = 0;
	int	ret = FAIL;

	ZBX_UNUSED(old_field);
	DBmodify_field_type_sql(&sql, &sql_alloc, &sql_offset, table_name, field);

	if (ZBX_DB_OK <= zbx_db_execute("%s", sql))
		ret = SUCCEED;

	zbx_free(sql);

	return ret;
}

int	DBdrop_field_autoincrement(const char *table_name, const zbx_db_field_t *field)
{
#if defined(HAVE_MYSQL)

	return DBmodify_field_type(table_name, field, NULL);

#elif defined(HAVE_POSTGRESQL)

	if (SUCCEED != DBdrop_default(table_name, field))
		return FAIL;

	if (ZBX_DB_OK > zbx_db_execute("drop sequence if exists %s_%s_seq", table_name, field->name))
		return FAIL;

	return SUCCEED;
#endif
}

int	DBset_not_null(const char *table_name, const zbx_db_field_t *field)
{
	char	*sql = NULL;
	size_t	sql_alloc = 0, sql_offset = 0;
	int	ret = FAIL;

	DBset_not_null_sql(&sql, &sql_alloc, &sql_offset, table_name, field);

	if (ZBX_DB_OK <= zbx_db_execute("%s", sql))
		ret = SUCCEED;

	zbx_free(sql);

	return ret;
}

int	DBset_default(const char *table_name, const zbx_db_field_t *field)
{
	char	*sql = NULL;
	size_t	sql_alloc = 0, sql_offset = 0;
	int	ret = FAIL;

	DBset_default_sql(&sql, &sql_alloc, &sql_offset, table_name, field);

	if (ZBX_DB_OK <= zbx_db_execute("%s", sql))
		ret = SUCCEED;

	zbx_free(sql);

	return ret;
}

int	DBdrop_default(const char *table_name, const zbx_db_field_t *field)
{
	char	*sql = NULL;
	size_t	sql_alloc = 0, sql_offset = 0;
	int	ret = FAIL;

	DBdrop_default_sql(&sql, &sql_alloc, &sql_offset, table_name, field);

	if (ZBX_DB_OK <= zbx_db_execute("%s", sql))
		ret = SUCCEED;

	zbx_free(sql);

	return ret;
}

int	DBdrop_not_null(const char *table_name, const zbx_db_field_t *field)
{
	char	*sql = NULL;
	size_t	sql_alloc = 0, sql_offset = 0;
	int	ret = FAIL;

	DBdrop_not_null_sql(&sql, &sql_alloc, &sql_offset, table_name, field);

	if (ZBX_DB_OK <= zbx_db_execute("%s", sql))
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

	if (ZBX_DB_OK <= zbx_db_execute("%s", sql))
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

	if (ZBX_DB_OK <= zbx_db_execute("%s", sql))
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

	if (ZBX_DB_OK <= zbx_db_execute("%s", sql))
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

	if (ZBX_DB_OK <= zbx_db_execute("%s", sql))
		ret = SUCCEED;

	zbx_free(sql);

	return ret;
}

int	DBadd_foreign_key(const char *table_name, int id, const zbx_db_field_t *field)
{
	char	*sql = NULL;
	size_t	sql_alloc = 0, sql_offset = 0;
	int	ret = FAIL;

	DBadd_foreign_key_sql(&sql, &sql_alloc, &sql_offset, table_name, id, field);

	if (ZBX_DB_OK <= zbx_db_execute("%s", sql))
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

	if (ZBX_DB_OK <= zbx_db_execute("%s", sql))
		ret = SUCCEED;

	zbx_free(sql);

	return ret;
}

static int	DBcreate_dbversion_table(void)
{
	const zbx_db_table_t	table =
			{"dbversion", "", 0,
				{
					{"mandatory", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"optional", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};
	int		ret;

	zbx_db_begin();
	if (SUCCEED == (ret = DBcreate_table(&table)))
	{
		if (ZBX_DB_OK > zbx_db_execute("insert into dbversion (mandatory,optional) values (%d,%d)",
				ZBX_FIRST_DB_VERSION, ZBX_FIRST_DB_VERSION))
		{
			ret = FAIL;
		}
	}

	return zbx_db_end(ret);
}

static int	DBset_version(int version, unsigned char mandatory)
{
	char	sql[64];
	size_t	offset;

	offset = zbx_snprintf(sql, sizeof(sql),  "update dbversion set ");
	if (0 != mandatory)
		offset += zbx_snprintf(sql + offset, sizeof(sql) - offset, "mandatory=%d,", version);
	zbx_snprintf(sql + offset, sizeof(sql) - offset, "optional=%d", version);

	if (ZBX_DB_OK <= zbx_db_execute("%s", sql))
		return SUCCEED;

	return FAIL;
}

#endif	/* not HAVE_SQLITE3 */

zbx_get_program_type_f	DBget_program_type_cb;
zbx_get_config_int_f	DBget_config_timeout_cb;

DBPATCHES_ARRAY_DECL(2010);
DBPATCHES_ARRAY_DECL(2020);
DBPATCHES_ARRAY_DECL(2030);
DBPATCHES_ARRAY_DECL(2040);
DBPATCHES_ARRAY_DECL(2050);
DBPATCHES_ARRAY_DECL(3000);
DBPATCHES_ARRAY_DECL(3010);
DBPATCHES_ARRAY_DECL(3020);
DBPATCHES_ARRAY_DECL(3030);
DBPATCHES_ARRAY_DECL(3040);
DBPATCHES_ARRAY_DECL(3050);
DBPATCHES_ARRAY_DECL(4000);
DBPATCHES_ARRAY_DECL(4010);
DBPATCHES_ARRAY_DECL(4020);
DBPATCHES_ARRAY_DECL(4030);
DBPATCHES_ARRAY_DECL(4040);
DBPATCHES_ARRAY_DECL(4050);
DBPATCHES_ARRAY_DECL(5000);
DBPATCHES_ARRAY_DECL(5010);
DBPATCHES_ARRAY_DECL(5020);
DBPATCHES_ARRAY_DECL(5030);
DBPATCHES_ARRAY_DECL(5040);
DBPATCHES_ARRAY_DECL(5050);
DBPATCHES_ARRAY_DECL(6000);
DBPATCHES_ARRAY_DECL(6010);
DBPATCHES_ARRAY_DECL(6020);
DBPATCHES_ARRAY_DECL(6030);
DBPATCHES_ARRAY_DECL(6040);
DBPATCHES_ARRAY_DECL(6050);
DBPATCHES_ARRAY_DECL(7000);
DBPATCHES_ARRAY_DECL(7010);
DBPATCHES_ARRAY_DECL(7020);
DBPATCHES_ARRAY_DECL(7030);
DBPATCHES_ARRAY_DECL(7040);
DBPATCHES_ARRAY_DECL(7050);

static zbx_dbpatch_t *dbversions[] = {
	DBPATCH_VERSION(2010), /* 2.2 development */
	DBPATCH_VERSION(2020), /* 2.2 maintenance */
	DBPATCH_VERSION(2030), /* 2.4 development */
	DBPATCH_VERSION(2040), /* 2.4 maintenance */
	DBPATCH_VERSION(2050), /* 3.0 development */
	DBPATCH_VERSION(3000), /* 3.0 maintenance */
	DBPATCH_VERSION(3010), /* 3.2 development */
	DBPATCH_VERSION(3020), /* 3.2 maintenance */
	DBPATCH_VERSION(3030), /* 3.4 development */
	DBPATCH_VERSION(3040), /* 3.4 maintenance */
	DBPATCH_VERSION(3050), /* 4.0 development */
	DBPATCH_VERSION(4000), /* 4.0 maintenance */
	DBPATCH_VERSION(4010), /* 4.2 development */
	DBPATCH_VERSION(4020), /* 4.2 maintenance */
	DBPATCH_VERSION(4030), /* 4.4 development */
	DBPATCH_VERSION(4040), /* 4.4 maintenance */
	DBPATCH_VERSION(4050), /* 5.0 development */
	DBPATCH_VERSION(5000), /* 5.0 maintenance */
	DBPATCH_VERSION(5010), /* 5.2 development */
	DBPATCH_VERSION(5020), /* 5.2 maintenance */
	DBPATCH_VERSION(5030), /* 5.4 development */
	DBPATCH_VERSION(5040), /* 5.4 maintenance */
	DBPATCH_VERSION(5050), /* 6.0 development */
	DBPATCH_VERSION(6000), /* 6.0 maintenance */
	DBPATCH_VERSION(6010), /* 6.2 development */
	DBPATCH_VERSION(6020), /* 6.2 maintenance */
	DBPATCH_VERSION(6030), /* 6.4 development */
	DBPATCH_VERSION(6040), /* 6.4 maintenance */
	DBPATCH_VERSION(6050), /* 7.0 development */
	DBPATCH_VERSION(7000), /* 7.0 maintenance */
	DBPATCH_VERSION(7010), /* 7.2 development */
	DBPATCH_VERSION(7020), /* 7.2 maintenance */
	DBPATCH_VERSION(7030), /* 7.4 development */
	DBPATCH_VERSION(7040), /* 7.4 maintenance */
	DBPATCH_VERSION(7050), /* 8.0 development */
	NULL
};

static void	DBget_version(int *mandatory, int *optional)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;

	*mandatory = -1;
	*optional = -1;

	result = zbx_db_select("select mandatory,optional from dbversion");

	if (NULL != (row = zbx_db_fetch(result)))
	{
		*mandatory = atoi(row[0]);
		*optional = atoi(row[1]);
	}
	zbx_db_free_result(result);

	if (-1 == *mandatory)
	{
		zabbix_log(LOG_LEVEL_CRIT, "Cannot get the database version. Exiting ...");
		exit(EXIT_FAILURE);
	}
}

unsigned char	DBget_program_type(void)
{
	return DBget_program_type_cb();
}

int	DBget_config_timeout(void)
{
	return DBget_config_timeout_cb();
}

void	zbx_init_library_dbupgrade(zbx_get_program_type_f get_program_type_cb,
		zbx_get_config_int_f get_config_timeout_cb)
{
	DBget_program_type_cb = get_program_type_cb;
	DBget_config_timeout_cb = get_config_timeout_cb;
}
#ifndef HAVE_SQLITE3
static int	DBcheck_nodes(void)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	int		ret = SUCCEED, db_time = 0, failover_delay = ZBX_HA_DEFAULT_FAILOVER_DELAY;

	zbx_db_begin();

	/* pre 7.4 has config table */
	if (SUCCEED == zbx_db_table_exists("config"))
	{
		result = zbx_db_select("select " ZBX_DB_TIMESTAMP() ",ha_failover_delay from config");
	}
	else if (SUCCEED == zbx_db_table_exists("settings"))
	{
		result = zbx_db_select("select " ZBX_DB_TIMESTAMP()
				", value_str from settings where name='ha_failover_delay'");
	}
	else
	{
		zbx_db_rollback();
		THIS_SHOULD_NEVER_HAPPEN;
		return FAIL;
	}

	if (NULL != (row = zbx_db_fetch(result)))
	{
		db_time = atoi(row[0]);

		if (SUCCEED != zbx_is_time_suffix(row[1], &failover_delay, ZBX_LENGTH_UNLIMITED))
			THIS_SHOULD_NEVER_HAPPEN;
	}
	else
		zabbix_log(LOG_LEVEL_WARNING, "cannot retrieve database time");

	zbx_db_free_result(result);

	/* check if there are recently accessed ZBX_NODE_STATUS_STANDBY or ZBX_NODE_STATUS_ACTIVE nodes */
	result = zbx_db_select("select lastaccess,name"
			" from ha_node"
			" where status not in (%d,%d)"
			" order by ha_nodeid" ZBX_FOR_UPDATE,
			ZBX_NODE_STATUS_STOPPED, ZBX_NODE_STATUS_UNAVAILABLE);
	while (NULL != (row = zbx_db_fetch(result)))
	{
		int	lastaccess, age;

		lastaccess = atoi(row[0]);

		if ((age = lastaccess + failover_delay - db_time) <= 0)
			continue;

		zabbix_log(LOG_LEVEL_WARNING, "cannot perform database upgrade: node \"%s\" is still running, if node"
				" is unreachable it will be skipped in %s",
				'\0' != *row[1] ? row[1] : "<standalone server>", zbx_age2str(age));

		ret = FAIL;
	}
	zbx_db_free_result(result);

	if (SUCCEED == ret)
	{
		if (ZBX_DB_OK != zbx_db_commit())
			ret = FAIL;
	}
	else
		zbx_db_rollback();

	return ret;
}
#endif

int	zbx_db_check_version_and_upgrade(zbx_ha_mode_t ha_mode)
{
#define ZBX_DB_WAIT_UPGRADE	10
	const char		*dbversion_table_name = "dbversion";
	int			db_mandatory, db_optional, required, ret = FAIL, i;
	zbx_dbpatch_t		**dbversion;
	zbx_dbpatch_t		*patches;

#ifndef HAVE_SQLITE3
	const char		*ha_node_table_name = "ha_node";
	int			total = 0, current = 0, completed, last_completed = -1, mandatory_num = 0;
#else
	ZBX_UNUSED(ha_mode);
#endif
	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	required = ZBX_FIRST_DB_VERSION;

	/* find out the required version number by getting the last mandatory version */
	/* of the last version patch array                                            */
	for (dbversion = dbversions; NULL != *dbversion; dbversion++)
		;

	patches = *(--dbversion);

	for (i = 0; 0 != patches[i].version; i++)
	{
		if (0 != patches[i].mandatory)
			required = patches[i].version;
	}

	if (SUCCEED != zbx_db_table_exists(dbversion_table_name))
	{
#ifndef HAVE_SQLITE3
		zabbix_log(LOG_LEVEL_DEBUG, "%s() \"%s\" does not exist", __func__, dbversion_table_name);

		if (SUCCEED != zbx_db_field_exists("config", "server_check_interval") &&
			SUCCEED != zbx_db_table_exists("settings") &&
			SUCCEED != zbx_db_setting_exists("server_check_interval"))
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
				get_program_type_string(DBget_program_type_cb()), required);
		zabbix_log(LOG_LEVEL_CRIT, "Zabbix does not support SQLite3 database upgrade.");

		ret = NOTSUPPORTED;
		goto out;
#endif
	}

	DBget_version(&db_mandatory, &db_optional);

#ifndef HAVE_SQLITE3
	for (dbversion = dbversions; NULL != (patches = *dbversion); dbversion++)
	{
		for (i = 0; 0 != patches[i].version; i++)
		{
			if (0 != patches[i].mandatory)
			{
				if (db_mandatory < patches[i].version)
					mandatory_num++;
			}

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
				get_program_type_string(DBget_program_type_cb()), db_mandatory, db_optional,
				required);
#ifdef HAVE_SQLITE3
		if (required > db_mandatory)
			zabbix_log(LOG_LEVEL_WARNING, "Zabbix does not support SQLite3 database upgrade.");
		else
			ret = NOTSUPPORTED;
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

	if (0 != mandatory_num)
	{
		zabbix_log(LOG_LEVEL_INFORMATION, "mandatory patches were found");
		if (SUCCEED == zbx_db_table_exists(ha_node_table_name))
			ret = DBcheck_nodes();

		if (ZBX_HA_MODE_CLUSTER == ha_mode)
		{
			zabbix_log(LOG_LEVEL_CRIT, "cannot perform database upgrade in HA mode: all nodes need to be"
					" stopped and Zabbix server started in standalone mode for the time of"
					" upgrade.");
			ret = FAIL;
		}

		if (FAIL == ret)
			goto out;
	}
	else
		zabbix_log(LOG_LEVEL_INFORMATION, "optional patches were found");

	zabbix_log(LOG_LEVEL_WARNING, "starting automatic database upgrade");

	for (dbversion = dbversions; NULL != *dbversion; dbversion++)
	{
		patches = *dbversion;

		for (i = 0; 0 != patches[i].version; i++)
		{
			static sigset_t	orig_mask, mask;
			zbx_db_result_t	result;
			zbx_db_row_t		row;

			if (db_optional >= patches[i].version)
				continue;

			/* block signals to prevent interruption of statements that cause an implicit commit */
			sigemptyset(&mask);
			sigaddset(&mask, SIGTERM);
			sigaddset(&mask, SIGINT);
			sigaddset(&mask, SIGQUIT);

			if (0 > zbx_sigmask(SIG_BLOCK, &mask, &orig_mask))
				zabbix_log(LOG_LEVEL_WARNING, "cannot set signal mask to block the user signal");

			zbx_db_begin();

			result = zbx_db_select("select optional,mandatory from dbversion" ZBX_FOR_UPDATE);
			if (NULL != (row = zbx_db_fetch(result)))
				db_optional = atoi(row[0]);

			zbx_db_free_result(result);
			if (db_optional >= patches[i].version)
			{
				zabbix_log(LOG_LEVEL_INFORMATION, "cannot perform database upgrade:"
						" patch with version %08d was already performed by other node",
						patches[i].version);
				ret = FAIL;
			}
			else
			{
				/* skipping the duplicated patches */
				if ((0 != patches[i].duplicates && patches[i].duplicates <= db_optional) ||
						SUCCEED == (ret = patches[i].function()))
				{
					ret = DBset_version(patches[i].version, patches[i].mandatory);
				}
			}

			ret = zbx_db_end(ret);

			if (0 > zbx_sigmask(SIG_SETMASK, &orig_mask, NULL))
				zabbix_log(LOG_LEVEL_WARNING,"cannot restore signal mask");

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
	{
		/* clear changelog after successful upgrade, doesn't matter if it fails */
		(void)zbx_db_execute("delete from changelog");

		zabbix_log(LOG_LEVEL_WARNING, "database upgrade fully completed");
	}
	else
	{
		zabbix_log(LOG_LEVEL_CRIT, "database upgrade failed on patch %08d, exiting in %d seconds",
				patches[i].version, ZBX_DB_WAIT_UPGRADE);
		sleep(ZBX_DB_WAIT_UPGRADE);
	}
#endif	/* not HAVE_SQLITE3 */

out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
#undef ZBX_DB_WAIT_UPGRADE
}

#ifndef HAVE_SQLITE3

#define ZBX_CHANGELOG_OP_INSERT	1
#define ZBX_CHANGELOG_OP_UPDATE	2
#define ZBX_CHANGELOG_OP_DELETE	3

static int	DBget_changelog_table_by_name(const char *table_name)
{
	const zbx_db_table_changelog_t	*table;

	for (table = zbx_dbschema_get_changelog_tables(); NULL != table->table; table++)
	{
		if (0 == strcmp(table_name, table->table))
			return table->object;
	}

	return FAIL;
}

int	DBcreate_changelog_insert_trigger(const char *table_name, const char *field_name)
{
	char	*sql = NULL;
	size_t	sql_alloc = 0, sql_offset = 0;
	int	table_type, ret = FAIL;

	if (FAIL == (table_type = DBget_changelog_table_by_name(table_name)))
	{
		THIS_SHOULD_NEVER_HAPPEN;
		return FAIL;
	}

#if HAVE_MYSQL
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"create trigger %s_insert after insert on %s\n"
				"for each row\n"
					"insert into changelog (object,objectid,operation,clock)\n"
						"values (%d,new.%s,%d,unix_timestamp())",
				table_name, table_name, table_type, field_name, ZBX_CHANGELOG_OP_INSERT);
#elif HAVE_POSTGRESQL
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"create or replace function changelog_%s_insert() returns trigger as $$\n"
			"begin\n"
				"insert into changelog (object,objectid,operation,clock)\n"
					"values (%d,new.%s,%d,cast(extract(epoch from now()) as int));\n"
				"return new;\n"
			"end;\n"
			"$$ language plpgsql;\n"
			"create trigger %s_insert after insert on %s\n"
				"for each row\n"
					"execute procedure changelog_%s_insert();",
				table_name, table_type, field_name, ZBX_CHANGELOG_OP_INSERT, table_name, table_name,
				table_name);
#endif

	if (ZBX_DB_OK <= zbx_db_execute("%s", sql))
		ret = SUCCEED;

	zbx_free(sql);

	return ret;
}

int	DBcreate_changelog_update_trigger(const char *table_name, const char *field_name)
{
	char	*sql = NULL;
	size_t	sql_alloc = 0, sql_offset = 0;
	int	table_type, ret = FAIL;

	if (FAIL == (table_type = DBget_changelog_table_by_name(table_name)))
	{
		THIS_SHOULD_NEVER_HAPPEN;
		return FAIL;
	}

#if HAVE_MYSQL
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"create trigger %s_update after update on %s\n"
				"for each row\n"
					"insert into changelog (object,objectid,operation,clock)\n"
						"values (%d,old.%s,%d,unix_timestamp())",
				table_name, table_name, table_type, field_name, ZBX_CHANGELOG_OP_UPDATE);
#elif HAVE_POSTGRESQL
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"create or replace function changelog_%s_update() returns trigger as $$\n"
			"begin\n"
				"insert into changelog (object,objectid,operation,clock)\n"
					"values (%d,old.%s,%d,cast(extract(epoch from now()) as int));\n"
				"return new;\n"
			"end;\n"
			"$$ language plpgsql;\n"
			"create trigger %s_update after update on %s\n"
				"for each row\n"
					"execute procedure changelog_%s_update();",
				table_name, table_type, field_name, ZBX_CHANGELOG_OP_UPDATE, table_name, table_name,
				table_name);
#endif

	if (ZBX_DB_OK <= zbx_db_execute("%s", sql))
		ret = SUCCEED;

	zbx_free(sql);

	return ret;
}

int	DBcreate_changelog_delete_trigger(const char *table_name, const char *field_name)
{
	char	*sql = NULL;
	size_t	sql_alloc = 0, sql_offset = 0;
	int	table_type, ret = FAIL;

	if (FAIL == (table_type = DBget_changelog_table_by_name(table_name)))
	{
		THIS_SHOULD_NEVER_HAPPEN;
		return FAIL;
	}

#if HAVE_MYSQL
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"create trigger %s_delete before delete on %s\n"
				"for each row\n"
					"insert into changelog (object,objectid,operation,clock)\n"
						"values (%d,old.%s,%d,unix_timestamp())",
				table_name, table_name, table_type, field_name, ZBX_CHANGELOG_OP_DELETE);
#elif HAVE_POSTGRESQL
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"create or replace function changelog_%s_delete() returns trigger as $$\n"
			"begin\n"
				"insert into changelog (object,objectid,operation,clock)\n"
					"values (%d,old.%s,%d,cast(extract(epoch from now()) as int));\n"
				"return old;\n"
			"end;\n"
			"$$ language plpgsql;\n"
			"create trigger %s_delete before delete on %s\n"
				"for each row\n"
					"execute procedure changelog_%s_delete();",
				table_name, table_type, field_name, ZBX_CHANGELOG_OP_DELETE, table_name, table_name,
				table_name);
#endif

	if (ZBX_DB_OK <= zbx_db_execute("%s", sql))
		ret = SUCCEED;

	zbx_free(sql);

	return ret;
}

int	zbx_dbupgrade_attach_trigger_with_function_on_insert(const char *table_name,
		const char *original_column_name, const char *indexed_column_name, const char *function,
		const char *idname)
{
	char	*sql = NULL;
	size_t	sql_alloc = 0, sql_offset = 0;
	int	ret = FAIL;
#if HAVE_MYSQL
	ZBX_UNUSED(idname);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"create trigger %s_%s_insert\n"
			"before insert on %s for each row\n"
				"set new.%s=%s(new.%s)",
			table_name, indexed_column_name, table_name, indexed_column_name, function,
			original_column_name);
#elif defined(HAVE_POSTGRESQL)
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"create or replace function %s_%s_%s()\n"
			"returns trigger language plpgsql AS $func$\n"
			"begin\n"
				"update %s set %s=%s(%s)\n"
				"where %s=new.%s;\n"
				"return null;\n"
			"end $func$;\n"

			"create trigger %s_%s_insert after insert\n"
				"on %s\n"
				"for each row execute function %s_%s_%s();",
			table_name, indexed_column_name, function, table_name, indexed_column_name, function,
			original_column_name, idname, idname, table_name, indexed_column_name, table_name,
			table_name, indexed_column_name, function);
#endif
	if (ZBX_DB_OK <= zbx_db_execute("%s", sql))
		ret = SUCCEED;

	zbx_free(sql);

	return ret;
}

int	zbx_dbupgrade_attach_trigger_with_function_on_update(const char *table_name,
		const char *original_column_name, const char *indexed_column_name, const char *function,
		const char *idname)
{
	char	*sql = NULL;
	size_t	sql_alloc = 0, sql_offset = 0;
	int	ret = FAIL;
#if HAVE_MYSQL
	ZBX_UNUSED(idname);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"create trigger %s_%s_update\n"
			"before update on %s for each row\n"
			"begin\n"
				"if new.%s<>old.%s\n"
				"then\n"
					"set new.%s=%s(new.%s);\n"
				"end if;\n"
			"end",
			table_name, indexed_column_name, table_name, original_column_name,
			original_column_name, indexed_column_name, function, original_column_name);
#elif defined(HAVE_POSTGRESQL)
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"create or replace function %s_%s_%s()\n"
			"returns trigger language plpgsql AS $func$\n"
			"begin\n"
				"update %s set %s=%s(%s)\n"
				"where %s=new.%s;\n"
				"return null;\n"
			"end $func$;\n"

			"create trigger %s_%s_update after update of %s on %s\n"
				"for each row execute function %s_%s_%s();",
			table_name, indexed_column_name, function, table_name, indexed_column_name, function,
			original_column_name, idname, idname, table_name, indexed_column_name,
			original_column_name, table_name, table_name, indexed_column_name, function);
#endif
	if (ZBX_DB_OK <= zbx_db_execute("%s", sql))
		ret = SUCCEED;

	zbx_free(sql);

	return ret;
}

static int	dbupgrade_drop_trigger_on_statement(const char *table_name,
		const char *indexed_column_name, const char *statement)
{
#ifdef HAVE_POSTGRESQL
	if (ZBX_DB_OK > zbx_db_execute("drop trigger %s_%s_%s on %s", table_name, indexed_column_name, statement,
			table_name))
	{
		return FAIL;
	}
#else
	if (ZBX_DB_OK > zbx_db_execute("drop trigger %s_%s_%s", table_name, indexed_column_name, statement))
	{
		return FAIL;
	}
#endif
	return SUCCEED;
}

int	zbx_dbupgrade_drop_trigger_on_insert(const char *table_name,
		const char *indexed_column_name)
{
	return dbupgrade_drop_trigger_on_statement(table_name, indexed_column_name, "insert");
}

int	zbx_dbupgrade_drop_trigger_on_update(const char *table_name,
		const char *indexed_column_name)
{
	return dbupgrade_drop_trigger_on_statement(table_name, indexed_column_name, "update");
}

static int	dbupgrade_drop_trigger_function(const char *table_name, const char *indexed_column_name,
		const char *function)
{
#ifdef HAVE_POSTGRESQL
	/* same function can depend on multiple triggers */
	if (ZBX_DB_OK > zbx_db_execute("drop function if exists %s_%s_%s", table_name, indexed_column_name, function))
		return FAIL;
#else
	ZBX_UNUSED(table_name);
	ZBX_UNUSED(indexed_column_name);
	ZBX_UNUSED(function);
#endif
	return SUCCEED;
}

int	zbx_dbupgrade_drop_trigger_function_on_insert(const char *table_name, const char *indexed_column_name,
		const char *function)
{
	return dbupgrade_drop_trigger_function(table_name, indexed_column_name, function);
}

int	zbx_dbupgrade_drop_trigger_function_on_update(const char *table_name, const char *indexed_column_name,
		const char *function)
{
	return dbupgrade_drop_trigger_function(table_name, indexed_column_name, function);
}
#endif
