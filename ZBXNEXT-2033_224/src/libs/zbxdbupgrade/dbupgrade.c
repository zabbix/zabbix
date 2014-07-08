/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

typedef struct
{
	int		(*function)(void);
	int		version;
	int		duplicates;
	unsigned char	mandatory;
}
zbx_dbpatch_t;

extern unsigned char	daemon_type;

#ifndef HAVE_SQLITE3
/*********************************************************************************
 *                                                                               *
 * Function: parse_db_monitor_item_params                                        *
 *                                                                               *
 * Purpose: parse database monitor item params string "user=<user> password=     *
 *          <passsword> DSN=<dsn> sql=<sql>" into parameter values.              *
 *                                                                               *
 * Parameters:  params     - [IN] the params string                              *
 *              dsn        - [OUT] the ODBC DSN output buffer                    *
 *              user       - [OUT] the user name output buffer                   *
 *              password   - [OUT] the password output buffer                    *
 *              sql        - [OUT] the sql query output buffer                   *
 *                                                                               *
 * Comments: This function allocated memory to store parsed parameters, which    *
 *           must be freed later by the caller.                                  *
 *           Failed (or absent) parameters will contain empty string "".         *
 *                                                                               *
 *********************************************************************************/
static void	parse_db_monitor_item_params(const char *params, char **dsn, char **user, char **password, char **sql)
{
	const char	*pvalue, *pnext, *pend;
	char		**var;

	for (; '\0' != *params; params = pnext)
	{
		while (0 != isspace(*params))
			params++;

		pvalue = strchr(params, '=');
		pnext = strchr(params, '\n');

		if (NULL == pvalue)
			break;

		if (NULL == pnext)
			pnext = params + strlen(params);

		if (pvalue > pnext || pvalue == params)
			continue;

		for (pend = pvalue - 1; 0 != isspace(*pend); pend--)
			;
		pend++;

		if (0 == strncmp(params, "user", pend - params))
			var = user;
		else if (0 == strncmp(params, "password", pend - params))
			var = password;
		else if (0 == strncmp(params, "DSN", pend - params))
			var = dsn;
		else if (0 == strncmp(params, "sql", pend - params))
			var = sql;
		else
			continue;

		pvalue++;
		while (0 != isspace(*pvalue))
			pvalue++;

		if (pvalue > pnext)
			continue;

		if ('\0' == *pvalue)
			continue;

		for (pend = pnext - 1; 0 != isspace(*pend); pend--)
			;
		pend++;

		if (NULL == *var)
		{
			*var = zbx_malloc(*var, pend - pvalue + 1);
			memmove(*var, pvalue, pend - pvalue);
			(*var)[pend - pvalue] = '\0';
		}
	}

	if (NULL == *user)
		*user = zbx_strdup(NULL, "");

	if (NULL == *password)
		*password = zbx_strdup(NULL, "");

	if (NULL == *dsn)
		*dsn = zbx_strdup(NULL, "");

	if (NULL == *sql)
		*sql = zbx_strdup(NULL, "");
}

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

static int	DBdrop_table(const char *table_name)
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

static int	DBrename_index(const char *table_name, const char *old_name, const char *new_name, const char *fields,
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
	const ZBX_FIELD field = {"hk_trends_mode", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

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
				},
				NULL
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

static int	DBpatch_2010087(void)
{
	return DBdrop_field("items", "lastclock");
}

static int	DBpatch_2010088(void)
{
	return DBdrop_field("items", "lastns");
}

static int	DBpatch_2010089(void)
{
	return DBdrop_field("items", "lastvalue");
}

static int	DBpatch_2010090(void)
{
	return DBdrop_field("items", "prevvalue");
}

static int	DBpatch_2010091(void)
{
	return DBdrop_field("items", "prevorgvalue");
}

static int	DBpatch_2010092(void)
{
	const ZBX_FIELD	field = {"width", "900", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBset_default("graphs", &field);
}

static int	DBpatch_2010093(void)
{
	const ZBX_FIELD	field = {"height", "200", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBset_default("graphs", &field);
}

static int	DBpatch_2010094(void)
{
	if (ZBX_DB_OK <= DBexecute("update items set history=1 where history=0"))
	{
		return SUCCEED;
	}

	return FAIL;
}

static int	DBpatch_2010095(void)
{
#ifdef HAVE_MYSQL
	return DBdrop_index("history_sync", "id");
#else
	return SUCCEED;
#endif
}

static int	DBpatch_2010096(void)
{
#ifdef HAVE_MYSQL
	return DBdrop_index("history_uint_sync", "id");
#else
	return SUCCEED;
#endif
}

static int	DBpatch_2010097(void)
{
#ifdef HAVE_MYSQL
	return DBdrop_index("history_str_sync", "id");
#else
	return SUCCEED;
#endif
}

static int	DBpatch_2010098(void)
{
#ifdef HAVE_MYSQL
	return DBdrop_index("proxy_history", "id");
#else
	return SUCCEED;
#endif
}

static int	DBpatch_2010099(void)
{
#ifdef HAVE_MYSQL
	return DBdrop_index("proxy_dhistory", "id");
#else
	return SUCCEED;
#endif
}

static int	DBpatch_2010100(void)
{
#ifdef HAVE_MYSQL
	return DBdrop_index("proxy_autoreg_host", "id");
#else
	return SUCCEED;
#endif
}

static int	DBpatch_2010101(void)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		ret = SUCCEED;
	char		*key = NULL;
	size_t		key_alloc = 0, key_offset;

	result = DBselect(
			"select i.itemid,i.key_,i.params,h.name"
			" from items i,hosts h"
			" where i.hostid=h.hostid"
				" and i.type=%d",
			ITEM_TYPE_DB_MONITOR);

	while (NULL != (row = DBfetch(result)) && SUCCEED == ret)
	{
		char		*user = NULL, *password = NULL, *dsn = NULL, *sql = NULL, *error_message = NULL;
		zbx_uint64_t	itemid;
		size_t		key_len;

		key_len = strlen(row[1]);

		parse_db_monitor_item_params(row[2], &dsn, &user, &password, &sql);

		if (0 != strncmp(row[1], "db.odbc.select[", 15) || ']' != row[1][key_len - 1])
			error_message = zbx_dsprintf(error_message, "key \"%s\" is invalid", row[1]);
		else if (ITEM_USERNAME_LEN < strlen(user))
			error_message = zbx_dsprintf(error_message, "ODBC username \"%s\" is too long", user);
		else if (ITEM_PASSWORD_LEN < strlen(password))
			error_message = zbx_dsprintf(error_message, "ODBC password \"%s\" is too long", password);
		else
		{
			char	*param = NULL;
			size_t	param_alloc = 0, param_offset = 0;
			int	nparam;

			zbx_strncpy_alloc(&param, &param_alloc, &param_offset, row[1] + 15, key_len - 16);

			if (1 != (nparam = num_param(param)))
				quote_key_param(&param, 0);
			quote_key_param(&dsn, 0);

			key_offset = 0;
			zbx_snprintf_alloc(&key, &key_alloc, &key_offset, "db.odbc.select[%s,%s]", param, dsn);

			zbx_free(param);

			if (255 /* ITEM_KEY_LEN */ < zbx_strlen_utf8(key))
				error_message = zbx_dsprintf(error_message, "key \"%s\" is too long", row[1]);
		}

		if (NULL == error_message)
		{
			char	*username_esc, *password_esc, *params_esc, *key_esc;

			ZBX_STR2UINT64(itemid, row[0]);

			username_esc = DBdyn_escape_string(user);
			password_esc = DBdyn_escape_string(password);
			params_esc = DBdyn_escape_string(sql);
			key_esc = DBdyn_escape_string(key);

			if (ZBX_DB_OK > DBexecute("update items set username='%s',password='%s',key_='%s',params='%s'"
					" where itemid=" ZBX_FS_UI64,
					username_esc, password_esc, key_esc, params_esc, itemid))
			{
				ret = FAIL;
			}

			zbx_free(username_esc);
			zbx_free(password_esc);
			zbx_free(params_esc);
			zbx_free(key_esc);
		}
		else
		{
			zabbix_log(LOG_LEVEL_WARNING, "Failed to convert host \"%s\" db monitoring item because"
					" %s. See upgrade notes for manual database monitor item conversion.",
					row[3], error_message);
		}

		zbx_free(error_message);
		zbx_free(user);
		zbx_free(password);
		zbx_free(dsn);
		zbx_free(sql);
	}
	DBfree_result(result);

	zbx_free(key);

	return ret;
}

static int	DBpatch_2010102(void)
{
	return DBcreate_index("hosts", "hosts_5", "maintenanceid", 0);
}

static int	DBpatch_2010103(void)
{
	return DBcreate_index("screens", "screens_1", "templateid", 0);
}

static int	DBpatch_2010104(void)
{
	return DBcreate_index("screens_items", "screens_items_1", "screenid", 0);
}

static int	DBpatch_2010105(void)
{
	return DBcreate_index("slides", "slides_2", "screenid", 0);
}

static int	DBpatch_2010106(void)
{
	return DBcreate_index("drules", "drules_1", "proxy_hostid", 0);
}

static int	DBpatch_2010107(void)
{
	return DBcreate_index("items", "items_6", "interfaceid", 0);
}

static int	DBpatch_2010108(void)
{
	return DBcreate_index("httpstepitem", "httpstepitem_2", "itemid", 0);
}

static int	DBpatch_2010109(void)
{
	return DBcreate_index("httptestitem", "httptestitem_2", "itemid", 0);
}

static int	DBpatch_2010110(void)
{
	return DBcreate_index("users_groups", "users_groups_2", "userid", 0);
}

static int	DBpatch_2010111(void)
{
	return DBcreate_index("scripts", "scripts_1", "usrgrpid", 0);
}

static int	DBpatch_2010112(void)
{
	return DBcreate_index("scripts", "scripts_2", "groupid", 0);
}

static int	DBpatch_2010113(void)
{
	return DBcreate_index("opmessage", "opmessage_1", "mediatypeid", 0);
}

static int	DBpatch_2010114(void)
{
	return DBcreate_index("opmessage_grp", "opmessage_grp_2", "usrgrpid", 0);
}

static int	DBpatch_2010115(void)
{
	return DBcreate_index("opmessage_usr", "opmessage_usr_2", "userid", 0);
}

static int	DBpatch_2010116(void)
{
	return DBcreate_index("opcommand", "opcommand_1", "scriptid", 0);
}

static int	DBpatch_2010117(void)
{
	return DBcreate_index("opcommand_hst", "opcommand_hst_2", "hostid", 0);
}

static int	DBpatch_2010118(void)
{
	return DBcreate_index("opcommand_grp", "opcommand_grp_2", "groupid", 0);
}

static int	DBpatch_2010119(void)
{
	return DBcreate_index("opgroup", "opgroup_2", "groupid", 0);
}

static int	DBpatch_2010120(void)
{
	return DBcreate_index("optemplate", "optemplate_2", "templateid", 0);
}

static int	DBpatch_2010121(void)
{
	return DBcreate_index("config", "config_1", "alert_usrgrpid", 0);
}

static int	DBpatch_2010122(void)
{
	return DBcreate_index("config", "config_2", "discovery_groupid", 0);
}

static int	DBpatch_2010123(void)
{
	return DBcreate_index("triggers", "triggers_3", "templateid", 0);
}

static int	DBpatch_2010124(void)
{
	return DBcreate_index("graphs", "graphs_2", "templateid", 0);
}

static int	DBpatch_2010125(void)
{
	return DBcreate_index("graphs", "graphs_3", "ymin_itemid", 0);
}

static int	DBpatch_2010126(void)
{
	return DBcreate_index("graphs", "graphs_4", "ymax_itemid", 0);
}

static int	DBpatch_2010127(void)
{
	return DBcreate_index("icon_map", "icon_map_2", "default_iconid", 0);
}

static int	DBpatch_2010128(void)
{
	return DBcreate_index("icon_mapping", "icon_mapping_2", "iconid", 0);
}

static int	DBpatch_2010129(void)
{
	return DBcreate_index("sysmaps", "sysmaps_2", "backgroundid", 0);
}

static int	DBpatch_2010130(void)
{
	return DBcreate_index("sysmaps", "sysmaps_3", "iconmapid", 0);
}

static int	DBpatch_2010131(void)
{
	return DBcreate_index("sysmaps_elements", "sysmaps_elements_1", "sysmapid", 0);
}

static int	DBpatch_2010132(void)
{
	return DBcreate_index("sysmaps_elements", "sysmaps_elements_2", "iconid_off", 0);
}

static int	DBpatch_2010133(void)
{
	return DBcreate_index("sysmaps_elements", "sysmaps_elements_3", "iconid_on", 0);
}

static int	DBpatch_2010134(void)
{
	return DBcreate_index("sysmaps_elements", "sysmaps_elements_4", "iconid_disabled", 0);
}

static int	DBpatch_2010135(void)
{
	return DBcreate_index("sysmaps_elements", "sysmaps_elements_5", "iconid_maintenance", 0);
}

static int	DBpatch_2010136(void)
{
	return DBcreate_index("sysmaps_links", "sysmaps_links_1", "sysmapid", 0);
}

static int	DBpatch_2010137(void)
{
	return DBcreate_index("sysmaps_links", "sysmaps_links_2", "selementid1", 0);
}

static int	DBpatch_2010138(void)
{
	return DBcreate_index("sysmaps_links", "sysmaps_links_3", "selementid2", 0);
}

static int	DBpatch_2010139(void)
{
	return DBcreate_index("sysmaps_link_triggers", "sysmaps_link_triggers_2", "triggerid", 0);
}

static int	DBpatch_2010140(void)
{
	return DBcreate_index("maintenances_hosts", "maintenances_hosts_2", "hostid", 0);
}

static int	DBpatch_2010141(void)
{
	return DBcreate_index("maintenances_groups", "maintenances_groups_2", "groupid", 0);
}

static int	DBpatch_2010142(void)
{
	return DBcreate_index("maintenances_windows", "maintenances_windows_2", "timeperiodid", 0);
}

static int	DBpatch_2010143(void)
{
	return DBcreate_index("nodes", "nodes_1", "masterid", 0);
}

static int	DBpatch_2010144(void)
{
	return DBcreate_index("graph_discovery", "graph_discovery_2", "parent_graphid", 0);
}

static int	DBpatch_2010145(void)
{
	return DBcreate_index("item_discovery", "item_discovery_2", "parent_itemid", 0);
}

static int	DBpatch_2010146(void)
{
	return DBcreate_index("trigger_discovery", "trigger_discovery_2", "parent_triggerid", 0);
}

static int	DBpatch_2010147(void)
{
	return DBcreate_index("application_template", "application_template_2", "templateid", 0);
}

static int	DBpatch_2010148(void)
{
	return DBrename_index("slides", "slides_slides_1", "slides_1", "slideshowid", 0);
}

static int	DBpatch_2010149(void)
{
	return DBrename_index("httptest", "httptest_httptest_1", "httptest_1", "applicationid", 0);
}

static int	DBpatch_2010150(void)
{
	return DBrename_index("httpstep", "httpstep_httpstep_1", "httpstep_1", "httptestid", 0);
}

static int	DBpatch_2010151(void)
{
	return DBrename_index("httpstepitem", "httpstepitem_httpstepitem_1", "httpstepitem_1", "httpstepid,itemid", 1);
}

static int	DBpatch_2010152(void)
{
	return DBrename_index("httptestitem", "httptestitem_httptestitem_1", "httptestitem_1", "httptestid,itemid", 1);
}

static int	DBpatch_2010153(void)
{
	return DBrename_index("graphs", "graphs_graphs_1", "graphs_1", "name", 0);
}

static int	DBpatch_2010154(void)
{
	return DBrename_index("services_links", "services_links_links_1", "services_links_1", "servicedownid", 0);
}

static int	DBpatch_2010155(void)
{
	return DBrename_index("services_links", "services_links_links_2", "services_links_2",
			"serviceupid,servicedownid", 1);
}

static int	DBpatch_2010156(void)
{
	return DBrename_index("services_times", "services_times_times_1", "services_times_1",
			"serviceid,type,ts_from,ts_to", 0);
}

static int	DBpatch_2010157(void)
{
	const ZBX_FIELD field = {"flags", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("hosts", &field);
}

static int	DBpatch_2010158(void)
{
	const ZBX_TABLE	table =
			{"host_discovery", "hostid", 0,
				{
					{"hostid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"parent_hostid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0},
					{"parent_itemid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0},
					{"host", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"lastcheck", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"ts_delete", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{NULL}
				}
			};

	return DBcreate_table(&table);
}

static int	DBpatch_2010159(void)
{
	const ZBX_FIELD	field = {"hostid", NULL, "hosts", "hostid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("host_discovery", 1, &field);
}

static int	DBpatch_2010160(void)
{
	const ZBX_FIELD	field = {"parent_hostid", NULL, "hosts", "hostid", 0, 0, 0, 0};

	return DBadd_foreign_key("host_discovery", 2, &field);
}

static int	DBpatch_2010161(void)
{
	const ZBX_FIELD	field = {"parent_itemid", NULL, "items", "itemid", 0, 0, 0, 0};

	return DBadd_foreign_key("host_discovery", 3, &field);
}

static int	DBpatch_2010162(void)
{
	const ZBX_FIELD field = {"templateid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_field("hosts", &field);
}

static int	DBpatch_2010163(void)
{
	const ZBX_FIELD	field = {"templateid", NULL, "hosts", "hostid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("hosts", 3, &field);
}

static int	DBpatch_2010164(void)
{
	const ZBX_TABLE	table =
			{"interface_discovery", "interfaceid", 0,
				{
					{"interfaceid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"parent_interfaceid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{NULL}
				}
			};

	return DBcreate_table(&table);
}

static int	DBpatch_2010165(void)
{
	const ZBX_FIELD	field = {"interfaceid", NULL, "interface", "interfaceid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("interface_discovery", 1, &field);
}

static int	DBpatch_2010166(void)
{
	const ZBX_FIELD	field =
			{"parent_interfaceid", NULL, "interface", "interfaceid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("interface_discovery", 2, &field);
}

static int	DBpatch_2010167(void)
{
	const ZBX_TABLE	table =
			{"group_prototype", "group_prototypeid", 0,
				{
					{"group_prototypeid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"hostid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"name", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"groupid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0},
					{"templateid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0},
					{NULL}
				}
			};

	return DBcreate_table(&table);
}

static int	DBpatch_2010168(void)
{
	const ZBX_FIELD	field = {"hostid", NULL, "hosts", "hostid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("group_prototype", 1, &field);
}

static int	DBpatch_2010169(void)
{
	const ZBX_FIELD	field = {"groupid", NULL, "groups", "groupid", 0, 0, 0, 0};

	return DBadd_foreign_key("group_prototype", 2, &field);
}

static int	DBpatch_2010170(void)
{
	const ZBX_FIELD	field = {"templateid", NULL, "group_prototype", "group_prototypeid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("group_prototype", 3, &field);
}

static int	DBpatch_2010171(void)
{
	return DBcreate_index("group_prototype", "group_prototype_1", "hostid", 0);
}

static int	DBpatch_2010172(void)
{
	const ZBX_TABLE	table =
			{"group_discovery", "groupid", 0,
				{
					{"groupid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"parent_group_prototypeid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"name", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"lastcheck", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"ts_delete", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{NULL}
				}
			};

	return DBcreate_table(&table);
}

static int	DBpatch_2010173(void)
{
	const ZBX_FIELD	field = {"groupid", NULL, "groups", "groupid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("group_discovery", 1, &field);
}

static int	DBpatch_2010174(void)
{
	const ZBX_FIELD	field = {"parent_group_prototypeid", NULL, "group_prototype", "group_prototypeid", 0, 0, 0, 0};

	return DBadd_foreign_key("group_discovery", 2, &field);
}

static int	DBpatch_2010175(void)
{
	const ZBX_FIELD field = {"flags", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("groups", &field);
}

static int	DBpatch_2010176(void)
{
	DB_RESULT	result;
	DB_ROW		row;
	char		*name, *name_esc;
	int		ret = SUCCEED;

	result = DBselect("select scriptid,name from scripts");

	while (SUCCEED == ret && NULL != (row = DBfetch(result)))
	{
		name = zbx_dyn_escape_string(row[1], "/\\");

		if (0 != strcmp(name, row[1]))
		{
			name_esc = DBdyn_escape_string_len(name, 255);

			if (ZBX_DB_OK > DBexecute("update scripts set name='%s' where scriptid=%s", name_esc, row[0]))
				ret = FAIL;

			zbx_free(name_esc);
		}

		zbx_free(name);
	}
	DBfree_result(result);

	return ret;
}

static int	DBpatch_2010177(void)
{
	const char	*rf_rate_strings[] = {"syssum", "hoststat", "stszbx", "lastiss", "webovr", "dscvry", NULL};
	int		i;

	for (i = 0; NULL != rf_rate_strings[i]; i++)
	{
		if (ZBX_DB_OK > DBexecute(
				"update profiles"
				" set idx='web.dashboard.widget.%s.rf_rate'"
				" where idx='web.dashboard.rf_rate.hat_%s'",
				rf_rate_strings[i], rf_rate_strings[i]))
		{
			return FAIL;
		}
	}

	return SUCCEED;
}

static int	DBpatch_2010178(void)
{
	const char	*state_strings[] = {"favgrph", "favscr", "favmap", "syssum", "hoststat", "stszbx", "lastiss",
			"webovr", "dscvry", NULL};
	int		i;

	for (i = 0; NULL != state_strings[i]; i++)
	{
		if (ZBX_DB_OK > DBexecute(
				"update profiles"
				" set idx='web.dashboard.widget.%s.state'"
				" where idx='web.dashboard.hats.hat_%s.state'",
				state_strings[i], state_strings[i]))
		{
			return FAIL;
		}
	}

	return SUCCEED;
}

static int	DBpatch_2010179(void)
{
	const ZBX_FIELD	field = {"yaxismax", "100", NULL, NULL, 0, ZBX_TYPE_FLOAT, ZBX_NOTNULL, 0};

	return DBset_default("graphs", &field);
}

static int	DBpatch_2010180(void)
{
	const ZBX_FIELD	field = {"yaxisside", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBset_default("graphs_items", &field);
}

static int	DBpatch_2010181(void)
{
	const ZBX_FIELD	field = {"ip", "127.0.0.1", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("interface", &field);
}

static int	DBpatch_2010182(void)
{
	const ZBX_FIELD	field = {"label", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("sysmaps_elements", &field);
}

static int	DBpatch_2010183(void)
{
	const ZBX_FIELD	field = {"label", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("sysmaps_links", &field);
}

static int	DBpatch_2010184(void)
{
	const ZBX_FIELD	field = {"label_location", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBset_default("sysmaps", &field);
}

static int	DBpatch_2010185(void)
{
	if (ZBX_DB_OK > DBexecute("update sysmaps_elements set label_location=-1 where label_location is null"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_2010186(void)
{
	const ZBX_FIELD	field = {"label_location", "-1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBset_default("sysmaps_elements", &field);
}

static int	DBpatch_2010187(void)
{
	const ZBX_FIELD	field = {"label_location", "-1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBset_not_null("sysmaps_elements", &field);
}

static int	DBpatch_2010188(void)
{
	return DBdrop_index("events", "events_1");
}

static int	DBpatch_2010189(void)
{
	return DBdrop_index("events", "events_2");
}

static int	DBpatch_2010190(void)
{
	return DBcreate_index("events", "events_1", "source,object,objectid,clock", 0);
}

static int	DBpatch_2010191(void)
{
	return DBcreate_index("events", "events_2", "source,object,clock", 0);
}

static int	DBpatch_2010192(void)
{
	if (ZBX_DB_OK <= DBexecute(
			"update triggers"
			" set state=%d,value=%d,lastchange=0,error=''"
			" where exists ("
				"select null"
				" from functions f,items i,hosts h"
				" where triggers.triggerid=f.triggerid"
					" and f.itemid=i.itemid"
					" and i.hostid=h.hostid"
					" and h.status=%d"
			")",
			TRIGGER_STATE_NORMAL, TRIGGER_VALUE_OK, HOST_STATUS_TEMPLATE))
	{
		return SUCCEED;
	}

	return FAIL;
}

static int	DBpatch_2010193(void)
{
	if (ZBX_DB_OK <= DBexecute(
			"update items"
			" set state=%d,error=''"
			" where exists ("
				"select null"
				" from hosts h"
				" where items.hostid=h.hostid"
					" and h.status=%d"
			")",
			ITEM_STATE_NORMAL, HOST_STATUS_TEMPLATE))
	{
		return SUCCEED;
	}

	return FAIL;
}

static int	DBpatch_2010194(void)
{
	return DBdrop_table("help_items");
}

/******************************************************************************
 *                                                                            *
 * Function: replace_key_param                                                *
 *                                                                            *
 * Comments: auxiliary function for DBpatch_2010195()                         *
 *                                                                            *
 ******************************************************************************/
static char	*replace_key_param(const char *data, int key_type, int level, int num, int quoted, void *cb_data)
{
	char	*param, *new_param;

	if (1 != level || 4 != num)	/* the fourth parameter on first level should be updated */
		return NULL;

	param = zbx_strdup(NULL, data);

	unquote_key_param(param);

	if ('\0' == *param)
	{
		zbx_free(param);
		return NULL;
	}

	new_param = zbx_dsprintf(NULL, "^%s$", param);

	zbx_free(param);

	quote_key_param(&new_param, quoted);

	return new_param;
}

static int	DBpatch_2010195(void)
{
	DB_RESULT	result;
	DB_ROW		row;
	char		*key = NULL, *key_esc, error[64];
	int		ret = SUCCEED;

	result = DBselect("select itemid,key_ from items where key_ like 'eventlog[%%'");

	while (SUCCEED == ret && NULL != (row = DBfetch(result)))
	{
		key = zbx_strdup(key, row[1]);

		if (SUCCEED != replace_key_params_dyn(&key, ZBX_KEY_TYPE_ITEM, replace_key_param, NULL,
				error, sizeof(error)))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot convert item key \"%s\": %s", row[1], error);
			continue;
		}

		if (255 /* ITEM_KEY_LEN */ < zbx_strlen_utf8(key))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot convert item key \"%s\": key is too long", row[1]);
			continue;
		}

		if (0 != strcmp(key, row[1]))
		{
			key_esc = DBdyn_escape_string(key);

			if (ZBX_DB_OK > DBexecute("update items set key_='%s' where itemid=%s", key_esc, row[0]))
				ret = FAIL;

			zbx_free(key_esc);
		}
	}
	DBfree_result(result);

	zbx_free(key);

	return ret;
}

static int	DBpatch_2010196(void)
{
#ifdef HAVE_ORACLE
	const ZBX_FIELD	field = {"message_tmp", "", NULL, NULL, 0, ZBX_TYPE_TEXT, ZBX_NOTNULL, 0};

	return DBadd_field("alerts", &field);
#else
	return SUCCEED;
#endif
}

static int	DBpatch_2010197(void)
{
#ifdef HAVE_ORACLE
	return ZBX_DB_OK > DBexecute("update alerts set message_tmp=message") ? FAIL : SUCCEED;
#else
	return SUCCEED;
#endif
}

static int	DBpatch_2010198(void)
{
#ifdef HAVE_ORACLE
	return DBdrop_field("alerts", "message");
#else
	return SUCCEED;
#endif
}

static int	DBpatch_2010199(void)
{
#ifdef HAVE_ORACLE
	const ZBX_FIELD	field = {"message", "", NULL, NULL, 0, ZBX_TYPE_TEXT, ZBX_NOTNULL, 0};

	return DBrename_field("alerts", "message_tmp", &field);
#else
	return SUCCEED;
#endif
}

static int	DBpatch_2020000(void)
{
	return SUCCEED;
}

static int	DBpatch_2020001(void)
{
	/* 16 - CONDITION_TYPE_MAINTENANCE */
	if (ZBX_DB_OK > DBexecute("update conditions set value='' where conditiontype=16"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_2020002(void)
{
	const ZBX_TABLE	table = {"ticket", "ticketid", 0,
		{
			{"ticketid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
			{"externalid", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
			{"eventid", NULL, "events", "eventid", 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
			{"triggerid", NULL, "triggers", "triggerid", 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
			{"clock", NULL, NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
			{"new", NULL, NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
			{NULL}
		},
		NULL
	};

	return DBcreate_table(&table);
}

static int	DBpatch_2020003(void)
{
	return DBcreate_index("ticket", "ticket_1", "eventid", 0);
}

static int	DBpatch_2020004(void)
{
	return DBcreate_index("ticket", "ticket_2", "triggerid,clock", 0);
}

static int	DBpatch_2020005(void)
{
	return DBcreate_index("ticket", "ticket_3", "externalid,new", 0);
}

static int	DBpatch_2020006(void)
{
	const ZBX_FIELD field = {"externalid", NULL, NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBset_default("ticket", &field);
}

static int	DBpatch_2020007(void)
{
	return DBdrop_index("ticket", "ticket_1");
}

static int	DBpatch_2020008(void)
{
	return DBcreate_index("ticket", "ticket_1", "eventid,clock", 0);
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
	int		i, total = 0, current = 0, completed, last_completed = -1, optional_num = 0;
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
	DBPATCH_ADD(2010087, 0, 1)
	DBPATCH_ADD(2010088, 0, 1)
	DBPATCH_ADD(2010089, 0, 1)
	DBPATCH_ADD(2010090, 0, 1)
	DBPATCH_ADD(2010091, 0, 1)
	DBPATCH_ADD(2010092, 0, 1)
	DBPATCH_ADD(2010093, 0, 1)
	DBPATCH_ADD(2010094, 0, 1)
	DBPATCH_ADD(2010095, 0, 0)
	DBPATCH_ADD(2010096, 0, 0)
	DBPATCH_ADD(2010097, 0, 0)
	DBPATCH_ADD(2010098, 0, 0)
	DBPATCH_ADD(2010099, 0, 0)
	DBPATCH_ADD(2010100, 0, 0)
	DBPATCH_ADD(2010101, 0, 1)
	DBPATCH_ADD(2010102, 0, 0)
	DBPATCH_ADD(2010103, 0, 0)
	DBPATCH_ADD(2010104, 0, 0)
	DBPATCH_ADD(2010105, 0, 0)
	DBPATCH_ADD(2010106, 0, 0)
	DBPATCH_ADD(2010107, 0, 0)
	DBPATCH_ADD(2010108, 0, 0)
	DBPATCH_ADD(2010109, 0, 0)
	DBPATCH_ADD(2010110, 0, 0)
	DBPATCH_ADD(2010111, 0, 0)
	DBPATCH_ADD(2010112, 0, 0)
	DBPATCH_ADD(2010113, 0, 0)
	DBPATCH_ADD(2010114, 0, 0)
	DBPATCH_ADD(2010115, 0, 0)
	DBPATCH_ADD(2010116, 0, 0)
	DBPATCH_ADD(2010117, 0, 0)
	DBPATCH_ADD(2010118, 0, 0)
	DBPATCH_ADD(2010119, 0, 0)
	DBPATCH_ADD(2010120, 0, 0)
	DBPATCH_ADD(2010121, 0, 0)
	DBPATCH_ADD(2010122, 0, 0)
	DBPATCH_ADD(2010123, 0, 0)
	DBPATCH_ADD(2010124, 0, 0)
	DBPATCH_ADD(2010125, 0, 0)
	DBPATCH_ADD(2010126, 0, 0)
	DBPATCH_ADD(2010127, 0, 0)
	DBPATCH_ADD(2010128, 0, 0)
	DBPATCH_ADD(2010129, 0, 0)
	DBPATCH_ADD(2010130, 0, 0)
	DBPATCH_ADD(2010131, 0, 0)
	DBPATCH_ADD(2010132, 0, 0)
	DBPATCH_ADD(2010133, 0, 0)
	DBPATCH_ADD(2010134, 0, 0)
	DBPATCH_ADD(2010135, 0, 0)
	DBPATCH_ADD(2010136, 0, 0)
	DBPATCH_ADD(2010137, 0, 0)
	DBPATCH_ADD(2010138, 0, 0)
	DBPATCH_ADD(2010139, 0, 0)
	DBPATCH_ADD(2010140, 0, 0)
	DBPATCH_ADD(2010141, 0, 0)
	DBPATCH_ADD(2010142, 0, 0)
	DBPATCH_ADD(2010143, 0, 0)
	DBPATCH_ADD(2010144, 0, 0)
	DBPATCH_ADD(2010145, 0, 0)
	DBPATCH_ADD(2010146, 0, 0)
	DBPATCH_ADD(2010147, 0, 0)
	DBPATCH_ADD(2010148, 0, 0)
	DBPATCH_ADD(2010149, 0, 0)
	DBPATCH_ADD(2010150, 0, 0)
	DBPATCH_ADD(2010151, 0, 0)
	DBPATCH_ADD(2010152, 0, 0)
	DBPATCH_ADD(2010153, 0, 0)
	DBPATCH_ADD(2010154, 0, 0)
	DBPATCH_ADD(2010155, 0, 0)
	DBPATCH_ADD(2010156, 0, 0)
	DBPATCH_ADD(2010157, 0, 1)
	DBPATCH_ADD(2010158, 0, 1)
	DBPATCH_ADD(2010159, 0, 1)
	DBPATCH_ADD(2010160, 0, 1)
	DBPATCH_ADD(2010161, 0, 1)
	DBPATCH_ADD(2010162, 0, 1)
	DBPATCH_ADD(2010163, 0, 1)
	DBPATCH_ADD(2010164, 0, 1)
	DBPATCH_ADD(2010165, 0, 1)
	DBPATCH_ADD(2010166, 0, 1)
	DBPATCH_ADD(2010167, 0, 1)
	DBPATCH_ADD(2010168, 0, 1)
	DBPATCH_ADD(2010169, 0, 1)
	DBPATCH_ADD(2010170, 0, 1)
	DBPATCH_ADD(2010171, 0, 1)
	DBPATCH_ADD(2010172, 0, 1)
	DBPATCH_ADD(2010173, 0, 1)
	DBPATCH_ADD(2010174, 0, 1)
	DBPATCH_ADD(2010175, 0, 1)
	DBPATCH_ADD(2010176, 0, 1)
	DBPATCH_ADD(2010177, 0, 1)
	DBPATCH_ADD(2010178, 0, 1)
	DBPATCH_ADD(2010179, 0, 1)
	DBPATCH_ADD(2010180, 0, 1)
	DBPATCH_ADD(2010181, 0, 1)
	DBPATCH_ADD(2010182, 0, 1)
	DBPATCH_ADD(2010183, 0, 1)
	DBPATCH_ADD(2010184, 0, 1)
	DBPATCH_ADD(2010185, 0, 1)
	DBPATCH_ADD(2010186, 0, 1)
	DBPATCH_ADD(2010187, 0, 1)
	DBPATCH_ADD(2010188, 0, 1)
	DBPATCH_ADD(2010189, 0, 1)
	DBPATCH_ADD(2010190, 0, 1)
	DBPATCH_ADD(2010191, 0, 1)
	DBPATCH_ADD(2010192, 0, 0)
	DBPATCH_ADD(2010193, 0, 0)
	DBPATCH_ADD(2010194, 0, 1)
	DBPATCH_ADD(2010195, 0, 1)
	DBPATCH_ADD(2010196, 0, 1)
	DBPATCH_ADD(2010197, 0, 1)
	DBPATCH_ADD(2010198, 0, 1)
	DBPATCH_ADD(2010199, 0, 1)
	DBPATCH_ADD(2020000, 0, 1)
	DBPATCH_ADD(2020001, 0, 0)
	DBPATCH_ADD(2020002, 0, 1)
	DBPATCH_ADD(2020003, 0, 1)
	DBPATCH_ADD(2020004, 0, 1)
	DBPATCH_ADD(2020005, 0, 1)
	DBPATCH_ADD(2020006, 0, 1)
	DBPATCH_ADD(2020007, 0, 1)
	DBPATCH_ADD(2020008, 0, 1)

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
		{
			required = patches[i].version;
			optional_num = 0;
		}
		else
			optional_num++;

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

	if (0 != optional_num)
		zabbix_log(LOG_LEVEL_INFORMATION, "optional patches were found");

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
