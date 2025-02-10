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

#include "proxyconfigwrite.h"

#include "zbxdbwrap.h"
#include "zbxcommshigh.h"
#include "zbxrtc.h"
#include "zbx_host_constants.h"
#include "zbxalgo.h"
#include "zbxcacheconfig.h"
#include "zbxdb.h"
#include "zbxdbschema.h"
#include "zbxjson.h"
#include "zbxnum.h"
#include "zbxstr.h"

/*
 * The configuration sync is split into 4 parts for each table:
 * 1) proxyconfig_prepare_rows()
 *    Renames rename_field to '#'||rename_field for all rows to be updated. It's done to avoid
 *    possible conflicts when two field values used in unique index are swapped.
 *    Resets reset_field to null for all rows to be deleted or updated. It's done to avoid
 *    possible foreign key violation when rows in self referencing table are updated or deleted.
 *    The changed fields are marked as updated, so during update processing the correct values
 *    will be assigned to them.
 *
 * 2) proxyconfig_delete_rows()
 *    Deletes all existing rows within scope that are not present in received table data. The scope
 *    is limited to received hosts when partial updates are done.
 *
 * 3) proxyconfig_insert_rows()
 *    Inserts new rows that were not present in database.
 *
 * 4) proxyconfig_update_rows()
 *    Update changed fields.
 *
 * When processing related tables (for example drules, dchecks) the prepare and delete operations are
 * done from child tables to master tables. The insert and update operations are done from master
 * tables to child tables. This is done to avoid child rows being removed with cascaded deletes and
 * have parent rows updated/inserted when updating/inserting child rows.
 *
 */

typedef struct
{
	const zbx_db_field_t	*field;
}
zbx_const_field_ptr_t;

ZBX_VECTOR_DECL(const_field, zbx_const_field_ptr_t)
ZBX_VECTOR_IMPL(const_field, zbx_const_field_ptr_t)

/*
 * 128 bit flags to support update flags for host_invetory table which has more that 64 columns
 */
typedef struct
{
	zbx_uint64_t	blocks[128 / 64];
}
zbx_flags128_t;

static void	zbx_flags128_set(zbx_flags128_t *flags, int bit)
{
	flags->blocks[bit >> 6] |= (__UINT64_C(1) << (bit & 0x3f));
}

static void	zbx_flags128_clear(zbx_flags128_t *flags, int bit)
{
	flags->blocks[bit >> 6] &= ~(__UINT64_C(1) << (bit & 0x3f));
}

static void	zbx_flags128_init(zbx_flags128_t *flags)
{
	memset(flags->blocks, 0, sizeof(zbx_uint64_t) * (128 / 64));
}

static int	zbx_flags128_isset(zbx_flags128_t *flags, int bit)
{
	return (0 != (flags->blocks[bit >> 6] & (__UINT64_C(1) << (bit & 0x3f)))) ? SUCCEED : FAIL;
}

static int	zbx_flags128_isclear(zbx_flags128_t *flags)
{
	if (0 != flags->blocks[0])
		return FAIL;

	if (0 != flags->blocks[1])
		return FAIL;

	return SUCCEED;
}

ZBX_PTR_VECTOR_DECL(table_row_ptr, struct zbx_table_row *)

/* bit defines for proxyconfig row flags, lower bits are reserved for field update flags */
#define PROXYCONFIG_ROW_EXISTS		127

typedef struct zbx_table_row
{
	zbx_uint64_t			recid;
	struct zbx_json_parse		columns;
	zbx_flags128_t			flags;
}
zbx_table_row_t;

ZBX_PTR_VECTOR_IMPL(table_row_ptr, zbx_table_row_t *)

typedef struct
{
	const zbx_db_table_t		*table;
	zbx_vector_const_field_t	fields;
	zbx_hashset_t			rows;

	/* identifiers of the rows that must be deleted */
	zbx_vector_uint64_t		del_ids;

	/* row that must be updated */
	zbx_vector_table_row_ptr_t	updates;

	/* to avoid unique key conflicts when syncing rows the key */
	/* field will be renamed for all update rows and marked    */
	/* for update                                              */
	const char			*rename_field;

	/* To avoid self referencing foreign key conflicts when */
	/* syncing rows the reset field will be set to NULL for */
	/* all update and delete rows and marked for update.    */
	/* As such only ID fields can be set as reset_field.    */
	const char			*reset_field;

	/* optional sql filter to limit managed object scope (exclude templates from hosts) */
	char				*sql_filter;
}
zbx_table_data_t;

ZBX_PTR_VECTOR_DECL(table_data_ptr, zbx_table_data_t *)
ZBX_PTR_VECTOR_IMPL(table_data_ptr, zbx_table_data_t *)

static void	table_data_free(zbx_table_data_t *td)
{
	zbx_vector_const_field_destroy(&td->fields);
	zbx_vector_uint64_destroy(&td->del_ids);
	zbx_vector_table_row_ptr_destroy(&td->updates);
	zbx_hashset_destroy(&td->rows);
	zbx_free(td->sql_filter);
	zbx_free(td);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get table data by name from configuration updates                 *
 *                                                                            *
 * Return: The parsed table data or NULL if table data was not found          *
 *                                                                            *
 ******************************************************************************/
static zbx_table_data_t	*proxyconfig_get_table(zbx_vector_table_data_ptr_t *config_tables, const char *name)
{
	int	i;

	for (i = 0; i < config_tables->values_num; i++)
	{
		if (0 == strcmp(config_tables->values[i]->table->table, name))
			return config_tables->values[i];
	}

	return NULL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: validate parsed table fields against fields retrieved from        *
 *          database schema                                                   *
 *                                                                            *
 * Parameters: td       - [IN] the table                                      *
 *             jp_table - [IN] the received table data in json format         *
 *             error    - [OUT] the error message                             *
 *                                                                            *
 * Return: SUCCEED - the parsed fields match schema fields                    *
 *         FAIL    - otherwise                                                *
 *                                                                            *
 ******************************************************************************/
static int	proxyconfig_validate_table_fields(const zbx_table_data_t *td, struct zbx_json_parse *jp_table,
		char **error)
{
	const char		*p;
	int			ret = FAIL, i;
	struct zbx_json_parse	jp;
	char			buf[ZBX_FIELDNAME_LEN_MAX];

	/* get table columns (line 3 in T1) */
	if (FAIL == zbx_json_brackets_by_name(jp_table, "fields", &jp))
	{
		*error = zbx_strdup(*error, zbx_json_strerror());
		goto out;
	}

	/* validate received fields */

	p = NULL;
	/* iterate column names (lines 4-6 in T1) */
	for (i = 0; NULL != (p = zbx_json_next_value(&jp, p, buf, sizeof(buf), NULL)); i++)
	{
		if (i >= td->fields.values_num || 0 != strcmp(buf, td->fields.values[i].field->name))
		{
			*error = zbx_dsprintf(*error, "unexpected field \"%s.%s\"", td->table->table, buf);
			goto out;
		}
	}

	if (i != td->fields.values_num)
	{
		*error = zbx_dsprintf(*error, "missing field \"%s.%s\"", td->table->table,
				td->fields.values[i].field->name);
		goto out;
	}

	ret = SUCCEED;
out:
	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: parse table rows in received configuration data                   *
 *                                                                            *
 * Parameters: td       - [IN] the table                                      *
 *             jp_table - [IN] the received table data in json format         *
 *             error    - [OUT] the error message                             *
 *                                                                            *
 * Return: SUCCEED - the rows were parsed successfully                        *
 *         FAIL    - otherwise                                                *
 *                                                                            *
 ******************************************************************************/
static int	proxyconfig_parse_table_rows(zbx_table_data_t *td, struct zbx_json_parse *jp_table, char **error)
{
	const char		*p;
	int			ret = FAIL;
	struct zbx_json_parse	jp, jp_row;
	char			*buf;
	size_t			buf_alloc = ZBX_KIBIBYTE;

	buf = (char *)zbx_malloc(NULL, buf_alloc);

	/* get the entries (line 8 in T1) */
	if (FAIL == zbx_json_brackets_by_name(jp_table, ZBX_PROTO_TAG_DATA, &jp))
	{
		*error = zbx_strdup(*error, zbx_json_strerror());
		goto out;
	}

	for (p = NULL; NULL != (p = zbx_json_next(&jp, p)); )
	{
		zbx_table_row_t	*row, row_local;

		if (FAIL == zbx_json_brackets_open(p, &jp_row) ||
				NULL == zbx_json_next_value_dyn(&jp_row, NULL, &buf, &buf_alloc, NULL))
		{
			*error = zbx_strdup(*error, zbx_json_strerror());
			goto out;
		}

		if (SUCCEED != zbx_is_uint64(buf, &row_local.recid))
		{
			*error = zbx_dsprintf(*error, "invalid record identifier: \"%s\"", buf);
			goto out;
		}
		row = (zbx_table_row_t *)zbx_hashset_insert(&td->rows, &row_local, sizeof(row_local));
		row->columns = jp_row;
		zbx_flags128_init(&row->flags);
	}

	ret = SUCCEED;
out:
	zbx_free(buf);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: create and initialize table data object                           *
 *                                                                            *
 * Parameters: name - [IN] the table name                                     *
 *                                                                            *
 * Return: The table data object or null, if invalid table name was given.    *
 *                                                                            *
 ******************************************************************************/
static zbx_table_data_t	*proxyconfig_create_table(const char *name)
{
	const zbx_db_table_t	*table;
	const zbx_db_field_t	*field;
	zbx_table_data_t	*td;
	zbx_const_field_ptr_t	ptr;

	if (NULL == (table = zbx_db_get_table(name)))
		return NULL;

	td = (zbx_table_data_t *)zbx_malloc(NULL, sizeof(zbx_table_data_t));

	td->table = table;
	td->rename_field = NULL;
	td->reset_field = NULL;
	td->sql_filter = NULL;

	zbx_vector_const_field_create(&td->fields);
	zbx_hashset_create(&td->rows, 100, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_create(&td->del_ids);
	zbx_vector_table_row_ptr_create(&td->updates);

	/* apply table specific configuration settings */

	if (0 == strcmp(table->table, "globalmacro"))
	{
		td->rename_field = "macro";
	}
	if (0 == strcmp(table->table, "hostmacro"))
	{
		td->rename_field = "macro";
	}
	else if (0 == strcmp(table->table, "drules"))
	{
		td->rename_field = "name";
	}
	else if (0 == strcmp(table->table, "regexps"))
	{
		td->rename_field = "name";
	}
	else if (0 == strcmp(table->table, "httptest"))
	{
		td->rename_field = "name";
	}
	else if (0 == strcmp(table->table, "hosts"))
	{
		td->sql_filter = zbx_dsprintf(NULL, "status<>%d", HOST_STATUS_TEMPLATE);
	}
	else if (0 == strcmp(table->table, "items"))
	{
		td->reset_field = "master_itemid";
	}
	else if (0 == strcmp(table->table, "proxy"))
	{
		td->rename_field = "name";
	}
	else if (0 == strcmp(table->table, "host_proxy"))
	{
		td->reset_field = "proxyid";
	}

	/* get table fields from database schema */

	field = table->fields;
	ptr.field = field++;
	zbx_vector_const_field_append(&td->fields, ptr);

	for (; NULL != field->name; field++)
	{
		if (0 != (field->flags & ZBX_PROXY))
		{
			ptr.field = field;
			zbx_vector_const_field_append(&td->fields, ptr);
		}
	}

	return td;
}

/******************************************************************************
 *                                                                            *
 * Purpose: parse received configuration data                                 *
 *                                                                            *
 * Parameters: jp_data       - [IN] the configuration data in json format     *
 *             config_tables - [OUT] the parsed table data                    *
 *             error         - [IN] the error message                         *
 *                                                                            *
 * Return: SUCCEED - the rows were parsed successfully                        *
 *         FAIL    - otherwise                                                *
 *                                                                            *
 ******************************************************************************/
static int	proxyconfig_parse_data(struct zbx_json_parse *jp_data, zbx_vector_table_data_ptr_t *config_tables,
		char **error)
{
	const char		*p;
	char			buf[ZBX_TABLENAME_LEN_MAX];
	struct zbx_json_parse	jp_table;
	int			ret = FAIL;

	/************************************************************************************/
	/* T1. RECEIVED JSON (jp_obj) DATA FORMAT                                           */
	/************************************************************************************/
	/* Line |                  Data                     | Corresponding structure in DB */
	/* -----+-------------------------------------------+------------------------------ */
	/*   1  | {                                         |                               */
	/*   2  |         "hosts": {                        | first table                   */
	/*   3  |                 "fields": [               | list of table's columns       */
	/*   4  |                         "hostid",         | first column                  */
	/*   5  |                         "host",           | second column                 */
	/*   6  |                         ...               | ...columns                    */
	/*   7  |                 ],                        |                               */
	/*   8  |                 "data": [                 | the table data                */
	/*   9  |                         [                 | first entry                   */
	/*  10  |                               1,          | value for first column        */
	/*  11  |                               "zbx01",    | value for second column       */
	/*  12  |                               ...         | ...values                     */
	/*  13  |                         ],                |                               */
	/*  14  |                         [                 | second entry                  */
	/*  15  |                               2,          | value for first column        */
	/*  16  |                               "zbx02",    | value for second column       */
	/*  17  |                               ...         | ...values                     */
	/*  18  |                         ],                |                               */
	/*  19  |                         ...               | ...entries                    */
	/*  20  |                 ]                         |                               */
	/*  21  |         },                                |                               */
	/*  22  |         "items": {                        | second table                  */
	/*  23  |                 ...                       | ...                           */
	/*  24  |         },                                |                               */
	/*  25  |         ...                               | ...tables                     */
	/*  26  | }                                         |                               */
	/************************************************************************************/

	/* iterate the tables (lines 2, 22 and 25 in T1) */
	for (p = NULL; NULL != (p = zbx_json_pair_next(jp_data, p, buf, sizeof(buf))); )
	{
		zbx_table_data_t	*td;

		if (FAIL == zbx_json_brackets_open(p, &jp_table))
		{
			*error = zbx_strdup(NULL, zbx_json_strerror());
			goto out;
		}

		if (NULL == (td = proxyconfig_create_table(buf)))
		{
			*error = zbx_dsprintf(NULL, "invalid table name \"%s\"", buf);
			goto out;
		}

		if (SUCCEED != proxyconfig_validate_table_fields(td, &jp_table, error) ||
				SUCCEED != proxyconfig_parse_table_rows(td, &jp_table, error))
		{
			table_data_free(td);
			goto out;
		}

		zbx_vector_table_data_ptr_append(config_tables, td);
	}

	ret = SUCCEED;
out:
	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: add default tables to configuration data                          *
 *                                                                            *
 * Parameters: config_tables - [IN] the parsed table data                     *
 *                                                                            *
 * Comments: In some cases empty tables might not be sent, but still need to  *
 *           be processed to support old data removal. This function adds     *
 *           empty tables that will be used to check and remove old records.  *
 *                                                                            *
 ******************************************************************************/
static void	proxyconfig_add_default_tables(zbx_vector_table_data_ptr_t *config_tables)
{
	if (NULL != proxyconfig_get_table(config_tables, "hosts") &&
			NULL == proxyconfig_get_table(config_tables, "httptest"))
	{
		char	*httptest_tables[] = {"httptest", "httptestitem", "httptest_field",
				"httpstep", "httpstepitem", "httpstep_field"};
		size_t	i;

		for (i = 0; i < ARRSIZE(httptest_tables); i++)
			zbx_vector_table_data_ptr_append(config_tables, proxyconfig_create_table(httptest_tables[i]));
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: dump table data object contents                                   *
 *                                                                            *
 * Parameters: td - [IN] the table data object                                *
 *                                                                            *
 ******************************************************************************/
static void	proxyconfig_dump_table(zbx_table_data_t *td)
{
	char			*str = NULL;
	size_t			str_alloc = 0, str_offset = 0, buf_alloc = ZBX_KIBIBYTE;
	int			i;
	zbx_hashset_iter_t	iter;
	zbx_table_row_t		*row;
	char			*buf;

	buf = (char *)zbx_malloc(NULL, buf_alloc);

	zabbix_log(LOG_LEVEL_TRACE, "table:%s", td->table->table);

	zbx_strcpy_alloc(&str, &str_alloc, &str_offset, "||");

	for (i = 0; i < td->fields.values_num; i++)
		zbx_snprintf_alloc(&str, &str_alloc, &str_offset, "%s||", td->fields.values[i].field->name);

	zabbix_log(LOG_LEVEL_TRACE, "  %s", str);

	zbx_hashset_iter_reset(&td->rows, &iter);
	while (NULL != (row = (zbx_table_row_t *)zbx_hashset_iter_next(&iter)))
	{
		const char	*pf = NULL;

		str_offset = 0;
		zbx_chrcpy_alloc(&str, &str_alloc, &str_offset, '|');

		while (NULL != (pf = zbx_json_next_value_dyn(&row->columns, pf, &buf, &buf_alloc, NULL)))
			zbx_snprintf_alloc(&str, &str_alloc, &str_offset, "%s|", buf);

		zabbix_log(LOG_LEVEL_TRACE, "  %s", str);
	}

	zbx_free(str);
	zbx_free(buf);
}

/******************************************************************************
 *                                                                            *
 * Purpose: dump parsed configuration contents                                *
 *                                                                            *
 * Parameters: config_tables - [IN] the parsed table data                     *
 *                                                                            *
 ******************************************************************************/
static void	proxyconfig_dump_data(const zbx_vector_table_data_ptr_t *config_tables)
{
	int	i;

	zabbix_log(LOG_LEVEL_TRACE, "=== Received configuration ===");

	for (i = 0; i < config_tables->values_num; i++)
		proxyconfig_dump_table(config_tables->values[i]);
}

/******************************************************************************
 *                                                                            *
 * Purpose: compare database row with received data                           *
 *                                                                            *
 * Parameters: row       - [IN] the received row                              *
 *             dbrow     - [IN] the database row                              *
 *             buf       - [IN/OUT] the buffer for value parsing              *
 *             buf_alloc - [IN/OUT] the buffer size                           *
 *                                                                            *
 * Return value: SUCCEED - the rows match                                     *
 *               FAIl - the rows doesn't match                                *
 *                                                                            *
 * Comments: The checked rows will be flagged as 'exists'. Also update flag   *
 *           will be set for each not matching column. Finally global row     *
 *           update flag will be set if at last one match failed.             *
 *                                                                            *
 ******************************************************************************/
static int	proxyconfig_compare_row(zbx_table_row_t *row, zbx_db_row_t dbrow, char **buf, size_t *buf_alloc)
{
	int		i, ret = SUCCEED;
	const char	*pf;
	zbx_json_type_t	type;

	/* skip first row containing record id */
	pf = zbx_json_next(&row->columns, NULL);

	for (i = 1; NULL != (pf = zbx_json_next_value_dyn(&row->columns, pf, buf, buf_alloc, &type)); i++)
	{
		if (ZBX_JSON_TYPE_NULL == type)
		{
			if (SUCCEED != zbx_db_is_null(dbrow[i]))
				zbx_flags128_set(&row->flags, i);
			continue;
		}

		if (SUCCEED == zbx_db_is_null(dbrow[i]) || 0 != strcmp(*buf, dbrow[i]))
			zbx_flags128_set(&row->flags, i);
	}

	if (SUCCEED != zbx_flags128_isclear(&row->flags))
		ret = FAIL;

	zbx_flags128_set(&row->flags, PROXYCONFIG_ROW_EXISTS);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get field index in table fields list by name                      *
 *                                                                            *
 * Parameters: td         - [IN] the table data object                        *
 *             field_name - [OUT] the field name                              *
 *                                                                            *
 * Return value: The field index or -1 if field was not found.                *
 *                                                                            *
 ******************************************************************************/
static int	table_data_get_field_index(const zbx_table_data_t *td, const char *field_name)
{
	int	i;

	/* skip first field - recid */
	for (i = 1; i < td->fields.values_num; i++)
	{
		if (0 == strcmp(td->fields.values[i].field->name, field_name))
			return i;
	}

	return -1;
}

/******************************************************************************
 *                                                                            *
 * Purpose: delete rows that are not present in new configuration data        *
 *                                                                            *
 * Parameters: td    - [IN] the table data object                             *
 *             error - [OUT] the error message                                *
 *                                                                            *
 * Return value: SUCCEED - the rows were deleted successfully                 *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	proxyconfig_delete_rows(const zbx_table_data_t *td, char **error)
{
	char	*sql = NULL;
	size_t	sql_alloc = 0, sql_offset = 0;
	int	ret;

	if (0 == td->del_ids.values_num)
		return SUCCEED;

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "delete from %s where", td->table->table);
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, td->table->recid, td->del_ids.values,
			td->del_ids.values_num);

	if (ZBX_DB_OK > zbx_db_execute("%s", sql))
	{
		*error = zbx_dsprintf(NULL, "cannot remove old objects from table \"%s\"", td->table->table);
		ret = FAIL;
	}
	else
		ret = SUCCEED;

	zbx_free(sql);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: prepare existing rows for update/delete                           *
 *                                                                            *
 * Parameters: td    - [IN] the table data object                             *
 *             error - [OUT] the error message                                *
 *                                                                            *
 * Return value: SUCCEED - the rows were prepared successfully                *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	proxyconfig_prepare_rows(zbx_table_data_t *td, char **error)
{
	char			*sql = NULL, delim = ' ';
	size_t			sql_alloc = 0, sql_offset = 0;
	int			i, ret, rename_index = -1, reset_index = -1;
	zbx_vector_uint64_t	updateids;

	if (NULL == td->rename_field && NULL == td->reset_field)
		return SUCCEED;

	if (NULL != td->rename_field && -1 == (rename_index = table_data_get_field_index(td, td->rename_field)))
	{
		*error = zbx_dsprintf(NULL, "unknown rename field \"%s\" for table \"%s\"", td->rename_field,
				td->table->table);
		return FAIL;
	}

	if (NULL != td->reset_field)
	{
		if (-1 == (reset_index = table_data_get_field_index(td, td->reset_field)))
		{
			*error = zbx_dsprintf(NULL, "unknown reset field \"%s\" for table \"%s\"", td->reset_field,
					td->table->table);
			return FAIL;
		}

		if (ZBX_TYPE_ID != td->fields.values[reset_index].field->type)
		{
			*error = zbx_dsprintf(NULL, "only ID fields can be reset");
			return FAIL;
		}
	}

	zbx_vector_uint64_create(&updateids);
	zbx_vector_uint64_reserve(&updateids, (size_t)td->updates.values_num);

	for (i = 0; i < td->updates.values_num; i++)
	{
		zbx_vector_uint64_append(&updateids, td->updates.values[i]->recid);

		/* force renamed/reset fields to be updated */

		if (-1 != rename_index)
			zbx_flags128_set(&td->updates.values[i]->flags, rename_index);

		if (-1 != reset_index)
			zbx_flags128_set(&td->updates.values[i]->flags, reset_index);
	}

	if (-1 != reset_index)
		zbx_vector_uint64_append_array(&updateids, td->del_ids.values, td->del_ids.values_num);

	if (0 == updateids.values_num)
	{
		ret = SUCCEED;
		goto out;
	}

	zbx_vector_uint64_sort(&updateids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update %s set", td->table->table);

	if (-1 != rename_index)
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%c%s=" ZBX_SQL_CONCAT(),
				delim, td->rename_field, "'#'", td->table->recid);
		delim = ',';
	}

	if (-1 != reset_index)
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%c%s=null", delim, td->reset_field);

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " where");
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, td->table->recid, updateids.values, updateids.values_num);

	if (ZBX_DB_OK > zbx_db_execute("%s", sql))
	{
		*error = zbx_dsprintf(NULL, "cannot prepare rows for update in table \"%s\"", td->table->table);
		ret = FAIL;
	}
	else
		ret = SUCCEED;

	zbx_free(sql);
out:
	zbx_vector_uint64_destroy(&updateids);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: convert text value to the database value according to its field   *
 *          type                                                              *
 *                                                                            *
 * Parameters: table - [IN]                                                   *
 *             field - [IN]                                                   *
 *             buf   - [IN] the value to convert                              *
 *             type  - [IN] the json value type                               *
 *             value - [OUT] the converted value (optional)                   *
 *             error - [OUT] the error message                                *
 *                                                                            *
 * Return value: SUCCEED - the operation was successful                       *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: This function can be used to validate buffer by using NULL       *
 *           output value.                                                    *
 *                                                                            *
 ******************************************************************************/
static int	proxyconfig_convert_value(const zbx_db_table_t *table, const zbx_db_field_t *field, const char *buf,
		zbx_json_type_t type, zbx_db_value_t **value, char **error)
{
	zbx_db_value_t	value_local;
	int		ret;

	switch (field->type)
	{
		case ZBX_TYPE_INT:
			ret = zbx_is_int(buf, &value_local.i32);
			break;
		case ZBX_TYPE_UINT:
			ret = zbx_is_uint64(buf, &value_local.ui64);
			break;
		case ZBX_TYPE_ID:
			if (ZBX_JSON_TYPE_NULL == type)
			{
				value_local.ui64 = 0;
				ret = SUCCEED;
			}
			else
				ret = zbx_is_uint64(buf, &value_local.ui64);
			break;
		case ZBX_TYPE_FLOAT:
			ret = zbx_is_double(buf, &value_local.dbl);
			break;
		case ZBX_TYPE_CHAR:
		case ZBX_TYPE_TEXT:
		case ZBX_TYPE_LONGTEXT:
			if (NULL != value)
				value_local.str = zbx_strdup(NULL, ZBX_NULL2EMPTY_STR(buf));
			ret = SUCCEED;
			break;
		default:
			*error = zbx_dsprintf(*error, "unsupported field type %d in \"%s.%s\"",
					field->type, table->table, field->name);
			return FAIL;
	}

	if (SUCCEED != ret)
	{
		*error = zbx_dsprintf(*error, "invalid field \"%s.%s\" value \"%s\"",
				table->table, field->name, buf);
		return FAIL;
	}

	if (NULL != value)
	{
		*value = (zbx_db_value_t *)zbx_malloc(NULL, sizeof(zbx_db_value_t));
		**value = value_local;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: update existing rows with new field values                        *
 *                                                                            *
 * Parameters: td    - [IN] the table data object                             *
 *             error - [OUT] the error message                                *
 *                                                                            *
 * Return value: SUCCEED - the rows were updated successfully                 *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	proxyconfig_update_rows(zbx_table_data_t *td, char **error)
{
	char	*sql = NULL, *buf;
	size_t	sql_alloc = 0, sql_offset = 0, buf_alloc = ZBX_KIBIBYTE;
	int	i, j, ret = FAIL;

	if (0 == td->updates.values_num)
		return SUCCEED;

	buf = (char *)zbx_malloc(NULL, buf_alloc);

	for (i = 0; i < td->updates.values_num; i++)
	{
		char		delim = ' ';
		const char	*pf;
		zbx_table_row_t	*row = td->updates.values[i];
		zbx_json_type_t	type;

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update %s set", td->table->table);

		pf = zbx_json_next(&row->columns, NULL);

		for (j = 1; NULL != (pf = zbx_json_next_value_dyn(&row->columns, pf, &buf, &buf_alloc, &type)); j++)
		{
			const zbx_db_field_t	*field = td->fields.values[j].field;
			char			*value_esc;

			if (SUCCEED != zbx_flags128_isset(&row->flags, j))
				continue;

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%c%s=", delim, field->name);
			delim = ',';

			if (ZBX_JSON_TYPE_NULL == type)
			{
				zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "null");
				continue;
			}

			if (SUCCEED != proxyconfig_convert_value(td->table, field, buf, type, NULL, error))
				goto out;

			switch (field->type)
			{
				case ZBX_TYPE_ID:
				case ZBX_TYPE_UINT:
				case ZBX_TYPE_FLOAT:
				case ZBX_TYPE_INT:
					zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, buf);
					break;
				case ZBX_TYPE_CHAR:
				case ZBX_TYPE_TEXT:
				case ZBX_TYPE_LONGTEXT:
					value_esc = zbx_db_dyn_escape_string_len(buf, field->length);
					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "'%s'", value_esc);
					zbx_free(value_esc);
					break;
				default:
					*error = zbx_dsprintf(*error, "unsupported field type %d in \"%s.%s\"",
							(int)field->type, td->table->table, field->name);
					goto out;
			}
		}

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " where %s=" ZBX_FS_UI64 ";\n",
				td->table->recid, row->recid);

		if (SUCCEED != zbx_db_execute_overflowed_sql(&sql, &sql_alloc, &sql_offset))
			goto out;
	}

	if (ZBX_DB_OK > zbx_db_flush_overflowed_sql(sql, sql_offset))
		goto out;

	ret = SUCCEED;
out:
	zbx_free(sql);
	zbx_free(buf);

	if (SUCCEED != ret && NULL == *error)
		*error = zbx_dsprintf(NULL, "cannot update rows in table \"%s\"", td->table->table);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: insert new rows                                                   *
 *                                                                            *
 * Parameters: td    - [IN] the table data object                             *
 *             error - [OUT] the error message                                *
 *                                                                            *
 * Return value: SUCCEED - the rows were inserted successfully                *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	proxyconfig_insert_rows(zbx_table_data_t *td, char **error)
{
	int				ret = SUCCEED, reset_index = -1;
	zbx_hashset_iter_t		iter;
	zbx_vector_table_row_ptr_t	rows;
	zbx_table_row_t			*row;

	/* invalid reset_index would have generated error during row preparation */
	if (NULL != td->reset_field)
		reset_index = table_data_get_field_index(td, td->reset_field);

	zbx_vector_table_row_ptr_create(&rows);

	zbx_hashset_iter_reset(&td->rows, &iter);
	while (NULL != (row = (zbx_table_row_t *)zbx_hashset_iter_next(&iter)))
	{
		if (SUCCEED != zbx_flags128_isset(&row->flags, PROXYCONFIG_ROW_EXISTS))
			zbx_vector_table_row_ptr_append(&rows, row);
	}

	if (0 != rows.values_num)
	{
		zbx_vector_db_value_ptr_t	values;
		zbx_db_insert_t			db_insert;
		const zbx_db_field_t		*fields[ZBX_MAX_FIELDS];
		int				i, j;
		char				*buf;
		size_t				buf_alloc = ZBX_KIBIBYTE;

		buf = (char *)zbx_malloc(NULL, buf_alloc);

		zbx_vector_db_value_ptr_create(&values);

		for (i = 0; i < td->fields.values_num; i++)
			fields[i] = td->fields.values[i].field;

		zbx_db_insert_prepare_dyn(&db_insert, td->table, fields, td->fields.values_num);

		for (i = 0; i < rows.values_num && SUCCEED == ret; i++)
		{
			const char	*pf = NULL;
			zbx_json_type_t	type;

			row = rows.values[i];

			for (j = 0; NULL != (pf = zbx_json_next_value_dyn(&row->columns, pf, &buf, &buf_alloc, &type));
					j++)
			{
				zbx_db_value_t	*value;

				if (j == reset_index)
				{
					if (ZBX_TYPE_ID != fields[j]->type)
					{
						/* Field resetting is used to avoid foreign key conflicts in self */
						/* referenced tables during inserts. Such fields are inserted as  */
						/* nulls and then updated to correct values.                      */
						/* For now only ID fields can be used in foreign keys.            */
						THIS_SHOULD_NEVER_HAPPEN;

						*error = zbx_dsprintf(NULL, "cannot reset field \"%s.%s\" of type %d "
								"to NULL value",
								td->table->table, fields[j]->name, fields[j]->type);
						ret = FAIL;
						goto clean;
					}

					/* insert null ID and add this row to updates, */
					/* so the correct ID will be updated later     */
					zbx_flags128_set(&row->flags, PROXYCONFIG_ROW_EXISTS);
					zbx_flags128_set(&row->flags, j);
					zbx_vector_table_row_ptr_append(&td->updates, row);

					value = (zbx_db_value_t *)zbx_malloc(NULL, sizeof(zbx_db_value_t));
					value->ui64 = 0;
				}
				else
				{
					if (SUCCEED != (ret = proxyconfig_convert_value(td->table, fields[j], buf, type,
							&value, error)))
					{
						goto clean;
					}
				}

				zbx_vector_db_value_ptr_append(&values, value);
			}

			zbx_db_insert_add_values_dyn(&db_insert, values.values, values.values_num);
clean:
			for (j = 0; j < values.values_num; j++)
			{
				switch (fields[j]->type)
				{
					case ZBX_TYPE_CHAR:
					case ZBX_TYPE_TEXT:
					case ZBX_TYPE_LONGTEXT:
						zbx_free(values.values[j]->str);
				}
				zbx_free(values.values[j]);
			}
			zbx_vector_db_value_ptr_clear(&values);
		}

		if (SUCCEED == ret)
			ret = zbx_db_insert_execute(&db_insert);

		zbx_db_insert_clean(&db_insert);
		zbx_vector_db_value_ptr_destroy(&values);
		zbx_free(buf);

	}

	zbx_vector_table_row_ptr_destroy(&rows);

	if (SUCCEED != ret && NULL == *error)
		*error = zbx_dsprintf(NULL, "cannot insert rows in table \"%s\"", td->table->table);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: prepare table for configuration sync by checking existing data    *
 *          against received data and mark row updates/deletes accordingly    *
 *                                                                            *
 * Parameters: td        - [IN] the table data object                         *
 *             key_field - [IN] the key field (optional)                      *
 *             key_ids   - [IN] the key identifiers (optional)                *
 *             recids    - [OUT] the selected row record identifiers          *
 *                               (optional)                                   *
 *                                                                            *
 * Comments: The key_field and key_ids allow to specify scope within which the*
 *           table sync will be made.                                         *
 *                                                                            *
 ******************************************************************************/
static void	proxyconfig_prepare_table(zbx_table_data_t *td, const char *key_field, zbx_vector_uint64_t *key_ids,
		zbx_vector_uint64_t *recids)
{
	zbx_db_result_t	result;
	zbx_db_row_t	dbrow;
	char		*sql = NULL, *buf, *delim = " where";
	size_t		sql_alloc = 0, sql_offset = 0, buf_alloc = ZBX_KIBIBYTE;
	zbx_uint64_t	recid;
	zbx_table_row_t	*row;
	int		i;

	if (NULL != key_ids && 0 == key_ids->values_num)
		return;

	buf = (char *)zbx_malloc(NULL, buf_alloc);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "select %s", td->table->recid);

	for (i = 1; i < td->fields.values_num; i++)
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, ",%s", td->fields.values[i].field->name);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " from %s", td->table->table);
	if (NULL != key_ids)
	{
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, delim);
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, key_field, key_ids->values, key_ids->values_num);
		delim = " and";
	}

	if (NULL != td->sql_filter)
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%s %s", delim, td->sql_filter);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " order by %s", td->table->recid);

	result = zbx_db_select("%s", sql);

	while (NULL != (dbrow = zbx_db_fetch(result)))
	{
		ZBX_STR2UINT64(recid, dbrow[0]);

		if (NULL != recids)
			zbx_vector_uint64_append(recids, recid);

		if (NULL == (row = (zbx_table_row_t *)zbx_hashset_search(&td->rows, &recid)))
		{
			zbx_vector_uint64_append(&td->del_ids, recid);
			continue;
		}

		if (SUCCEED != proxyconfig_compare_row(row, dbrow, &buf, &buf_alloc))
			zbx_vector_table_row_ptr_append(&td->updates, row);
	}
	zbx_db_free_result(result);

	zbx_free(sql);
	zbx_free(buf);

	if (0 != td->del_ids.values_num)
		zbx_vector_uint64_sort(&td->del_ids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	if (0 != td->updates.values_num)
		zbx_vector_table_row_ptr_sort(&td->updates, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

	if (NULL != recids)
		zbx_vector_uint64_sort(recids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
}

/******************************************************************************
 *                                                                            *
 * Purpose: sync table rows                                                   *
 *                                                                            *
 * Parameters: config_tables - [IN] the received table data                   *
 *             table         - [IN] the name of table to sync                 *
 *             error         - [OUT] the error message                        *
 *                                                                            *
 * Return value: SUCCEED - the table was synced successfully                  *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	proxyconfig_sync_table(zbx_vector_table_data_ptr_t *config_tables, const char *table, char **error)
{
	zbx_table_data_t	*td;

	if (NULL == (td = proxyconfig_get_table(config_tables, table)))
		return SUCCEED;

	proxyconfig_prepare_table(td, NULL, NULL, NULL);

	if (SUCCEED != proxyconfig_prepare_rows(td, error))
		return FAIL;

	if (SUCCEED != proxyconfig_delete_rows(td, error))
		return FAIL;

	if (SUCCEED != proxyconfig_insert_rows(td, error))
		return FAIL;

	return proxyconfig_update_rows(td, error);
}

/******************************************************************************
 *                                                                            *
 * Purpose: sync network discovery tables                                     *
 *                                                                            *
 * Parameters: config_tables - [IN] the received table data                   *
 *             error         - [OUT] the error message                        *
 *                                                                            *
 * Return value: SUCCEED - the tables were synced successfully                *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	proxyconfig_sync_network_discovery(zbx_vector_table_data_ptr_t *config_tables, char **error)
{
	zbx_table_data_t	*dchecks;

	dchecks = proxyconfig_get_table(config_tables, "dchecks");

	if (NULL != dchecks)
	{
		proxyconfig_prepare_table(dchecks, NULL, NULL, NULL);

		if (SUCCEED != proxyconfig_prepare_rows(dchecks, error))
			return FAIL;

		if (SUCCEED != proxyconfig_delete_rows(dchecks, error))
			return FAIL;
	}

	if (SUCCEED != proxyconfig_sync_table(config_tables, "drules", error))
		return FAIL;

	if (NULL == dchecks)
		return SUCCEED;

	if (SUCCEED != proxyconfig_insert_rows(dchecks, error))
		return FAIL;

	return proxyconfig_update_rows(dchecks, error);
}

/******************************************************************************
 *                                                                            *
 * Purpose: sync global regular expression tables                             *
 *                                                                            *
 * Parameters: config_tables - [IN] the received table data                   *
 *             error         - [OUT] the error message                        *
 *                                                                            *
 * Return value: SUCCEED - the tables were synced successfully                *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	proxyconfig_sync_regexps(zbx_vector_table_data_ptr_t *config_tables, char **error)
{
	zbx_table_data_t	*expressions;

	expressions = proxyconfig_get_table(config_tables, "expressions");

	if (NULL != expressions)
	{
		proxyconfig_prepare_table(expressions, NULL, NULL, NULL);

		if (SUCCEED != proxyconfig_prepare_rows(expressions, error))
			return FAIL;

		if (SUCCEED != proxyconfig_delete_rows(expressions, error))
			return FAIL;
	}

	if (SUCCEED != proxyconfig_sync_table(config_tables, "regexps", error))
		return FAIL;

	if (NULL == expressions)
		return SUCCEED;

	if (SUCCEED != proxyconfig_insert_rows(expressions, error))
		return FAIL;

	return proxyconfig_update_rows(expressions, error);
}

/******************************************************************************
 *                                                                            *
 * Purpose: force proxy to re-send host availability data if server and proxy *
 *          interface availability value is different and block proxy from    *
 *          updating interface availability in database                       *
 *                                                                            *
 * Parameters: td - [IN] the interface table data                             *
 *                                                                            *
 ******************************************************************************/
static void	proxyconfig_check_interface_availability(zbx_table_data_t *td)
{
	zbx_vector_uint64_t	interfaceids;
	int			i, index;

	if (-1 == (index = table_data_get_field_index(td, "available")))
		return;

	zbx_vector_uint64_create(&interfaceids);

	for (i = 0; i < td->updates.values_num;)
	{
		if (SUCCEED == zbx_flags128_isset(&td->updates.values[i]->flags, index))
		{
			zbx_flags128_t	flags;

			zbx_vector_uint64_append(&interfaceids, td->updates.values[i]->recid);
			zbx_flags128_clear(&td->updates.values[i]->flags, index);

			flags = td->updates.values[i]->flags;
			zbx_flags128_clear(&flags, PROXYCONFIG_ROW_EXISTS);
			if (SUCCEED == zbx_flags128_isclear(&flags))
			{
				zbx_vector_table_row_ptr_remove(&td->updates, i);
				continue;
			}
		}

		i++;
	}

	if (0 != interfaceids.values_num)
		zbx_dc_touch_interfaces_availability(&interfaceids);

	zbx_vector_uint64_destroy(&interfaceids);
}

#define ZBX_PROXYCONFIG_GET_TABLE(table)					\
	if (NULL == (table = proxyconfig_get_table(config_tables, #table)))	\
	{									\
		*error = zbx_strdup(NULL, "cannot find " #table " data");	\
		goto out;							\
	}									\
	zbx_vector_table_data_ptr_append(&host_tables, table)

/******************************************************************************
 *                                                                            *
 * Purpose: sync host and related tables                                      *
 *                                                                            *
 * Parameters: config_tables - [IN] the received table data                   *
 *             full_sync     - [IN] 1 if full sync must be done, 0 otherwise  *
 *             table         - [IN] the name of table to sync                 *
 *             error         - [OUT] the error message                        *
 *                                                                            *
 * Return value: SUCCEED - the tables were synced successfully                *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	proxyconfig_sync_hosts(zbx_vector_table_data_ptr_t *config_tables, int full_sync, char **error)
{
	zbx_table_data_t		*hosts, *host_inventory, *interface, *interface_snmp, *items, *item_rtdata,
					*item_preproc, *item_parameter, *httptest, *httptestitem, *httptest_field,
					*httpstep, *httpstepitem, *httpstep_field;
	int				i, ret = FAIL;
	zbx_vector_table_data_ptr_t	host_tables;

	if (NULL == (hosts = proxyconfig_get_table(config_tables, "hosts")))
		return SUCCEED;

	zbx_vector_table_data_ptr_create(&host_tables);
	zbx_vector_table_data_ptr_append(&host_tables, hosts);

	/* host related tables must always be present (even empty) if at least one host is synced */
	ZBX_PROXYCONFIG_GET_TABLE(host_inventory);
	ZBX_PROXYCONFIG_GET_TABLE(interface);
	ZBX_PROXYCONFIG_GET_TABLE(interface_snmp);
	ZBX_PROXYCONFIG_GET_TABLE(items);
	ZBX_PROXYCONFIG_GET_TABLE(item_rtdata);
	ZBX_PROXYCONFIG_GET_TABLE(item_preproc);
	ZBX_PROXYCONFIG_GET_TABLE(item_parameter);
	ZBX_PROXYCONFIG_GET_TABLE(httptest);
	ZBX_PROXYCONFIG_GET_TABLE(httptestitem);
	ZBX_PROXYCONFIG_GET_TABLE(httptest_field);
	ZBX_PROXYCONFIG_GET_TABLE(httpstep);
	ZBX_PROXYCONFIG_GET_TABLE(httpstepitem);
	ZBX_PROXYCONFIG_GET_TABLE(httpstep_field);

	if (0 == full_sync)
	{
		zbx_vector_uint64_t	recids, hostids, interfaceids, itemids, httptestids, httpstepids;
		zbx_hashset_iter_t	iter;
		zbx_table_row_t		*row;

		zbx_vector_uint64_create(&recids);
		zbx_vector_uint64_create(&hostids);
		zbx_vector_uint64_create(&interfaceids);
		zbx_vector_uint64_create(&itemids);
		zbx_vector_uint64_create(&httptestids);
		zbx_vector_uint64_create(&httpstepids);

		zbx_hashset_iter_reset(&hosts->rows, &iter);
		while (NULL != (row = (zbx_table_row_t *)zbx_hashset_iter_next(&iter)))
			zbx_vector_uint64_append(&recids, row->recid);

		proxyconfig_prepare_table(hosts, "hostid", &recids, &hostids);
		proxyconfig_prepare_table(host_inventory, "hostid", &hostids, NULL);
		proxyconfig_prepare_table(interface, "hostid", &hostids, &interfaceids);
		proxyconfig_prepare_table(interface_snmp, "interfaceid", &interfaceids, NULL);

		proxyconfig_prepare_table(items, "hostid", &hostids, &itemids);
		proxyconfig_prepare_table(item_rtdata, "itemid", &itemids, NULL);
		proxyconfig_prepare_table(item_preproc, "itemid", &itemids, NULL);
		proxyconfig_prepare_table(item_parameter, "itemid", &itemids, NULL);

		proxyconfig_prepare_table(httptest, "hostid", &hostids, &httptestids);
		proxyconfig_prepare_table(httptestitem, "httptestid", &httptestids, NULL);
		proxyconfig_prepare_table(httptest_field, "httptestid", &httptestids, NULL);
		proxyconfig_prepare_table(httpstep, "httptestid", &httptestids, &httpstepids);
		proxyconfig_prepare_table(httpstepitem, "httpstepid", &httpstepids, NULL);
		proxyconfig_prepare_table(httpstep_field, "httpstepid", &httpstepids, NULL);

		zbx_vector_uint64_destroy(&httpstepids);
		zbx_vector_uint64_destroy(&httptestids);
		zbx_vector_uint64_destroy(&itemids);
		zbx_vector_uint64_destroy(&interfaceids);
		zbx_vector_uint64_destroy(&hostids);
		zbx_vector_uint64_destroy(&recids);
	}
	else
	{
		proxyconfig_prepare_table(hosts, NULL, NULL, NULL);
		proxyconfig_prepare_table(host_inventory, NULL, NULL, NULL);
		proxyconfig_prepare_table(interface, NULL, NULL, NULL);
		proxyconfig_prepare_table(interface_snmp, NULL, NULL, NULL);

		proxyconfig_prepare_table(items, NULL, NULL, NULL);
		proxyconfig_prepare_table(item_rtdata, NULL, NULL, NULL);
		proxyconfig_prepare_table(item_preproc, NULL, NULL, NULL);
		proxyconfig_prepare_table(item_parameter, NULL, NULL, NULL);

		proxyconfig_prepare_table(httptest, NULL, NULL, NULL);
		proxyconfig_prepare_table(httptestitem, NULL, NULL, NULL);
		proxyconfig_prepare_table(httptest_field, NULL, NULL, NULL);
		proxyconfig_prepare_table(httpstep, NULL, NULL, NULL);
		proxyconfig_prepare_table(httpstepitem, NULL, NULL, NULL);
		proxyconfig_prepare_table(httpstep_field, NULL, NULL, NULL);
	}

	/* item_rtdata must be only inserted/removed and never updated */
	zbx_vector_table_row_ptr_clear(&item_rtdata->updates);

	/* interface availability changes are never updated in database, but must be marked in cache */
	proxyconfig_check_interface_availability(interface);

	/* remove rows in reverse order to avoid depending on cascaded deletes */
	for (i = host_tables.values_num - 1; 0 <= i; i--)
	{
		if (SUCCEED != proxyconfig_prepare_rows(host_tables.values[i], error))
			goto out;

		if (SUCCEED != proxyconfig_delete_rows(host_tables.values[i], error))
			goto out;
	}

	for (i = 0; i < host_tables.values_num; i++)
	{
		if (SUCCEED != proxyconfig_insert_rows(host_tables.values[i], error))
			goto out;

		if (SUCCEED != proxyconfig_update_rows(host_tables.values[i], error))
			goto out;
	}

	ret = SUCCEED;
out:
	zbx_vector_table_data_ptr_destroy(&host_tables);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: prepare hostmacro and hosts_templates tables                      *
 *                                                                            *
 * Parameters: hostmacro       - [IN] the hostmacro table                     *
 *             hosts_templates - [IN] the hosts templates table               *
 *             full_sync       - [IN] 1 if full sync must be done, 0 otherwise*
 *                                                                            *
 ******************************************************************************/
static void	proxyconfig_prepare_hostmacros(zbx_table_data_t *hostmacro, zbx_table_data_t *hosts_templates,
		int full_sync)
{
	zbx_vector_uint64_t	hostids, *key_ids = NULL;
	zbx_hashset_iter_t	iter;
	zbx_table_row_t		*row;
	char			*key_field = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&hostids);

	if (0 == full_sync)
	{
		/* limit the scope to the hosts sent with hostmacros or hosts_templates */
		zbx_hashset_iter_reset(&hostmacro->rows, &iter);
		while (NULL != (row = (zbx_table_row_t *)zbx_hashset_iter_next(&iter)))
		{
			const char	*pf;
			char		buf[ZBX_MAX_UINT64_LEN + 1];
			zbx_uint64_t	hostid;

			pf = zbx_json_next(&row->columns, NULL);

			if (NULL != zbx_json_next_value(&row->columns, pf, buf, sizeof(buf), NULL) &&
					SUCCEED == zbx_is_uint64(buf, &hostid))
			{
				zbx_vector_uint64_append(&hostids, hostid);
			}
		}

		zbx_hashset_iter_reset(&hosts_templates->rows, &iter);
		while (NULL != (row = (zbx_table_row_t *)zbx_hashset_iter_next(&iter)))
		{
			const char	*pf;
			char		buf[ZBX_MAX_UINT64_LEN + 1];
			zbx_uint64_t	hostid;

			pf = zbx_json_next(&row->columns, NULL);

			if (NULL != zbx_json_next_value(&row->columns, pf, buf, sizeof(buf), NULL) &&
					SUCCEED == zbx_is_uint64(buf, &hostid))
			{
				zbx_vector_uint64_append(&hostids, hostid);
			}
		}

		zbx_vector_uint64_sort(&hostids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_vector_uint64_uniq(&hostids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		key_ids = &hostids;
		key_field = "hostid";
	}

	proxyconfig_prepare_table(hostmacro, key_field, key_ids, NULL);
	proxyconfig_prepare_table(hosts_templates, key_field, key_ids, NULL);

	zbx_vector_uint64_destroy(&hostids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: sync templates by creating empty templates when necessary to link *
 *          to other templates                                                *
 *                                                                            *
 * Parameters: hosts_templates - [IN] the hosts_temlates data                 *
 *             hostmacro       - [IN] the hostmacro data                      *
 *             error           - [OUT] the error message                      *
 *                                                                            *
 * Return value: SUCCEED - the templates were synced successfully             *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	proxyconfig_sync_templates(zbx_table_data_t *hosts_templates, zbx_table_data_t *hostmacro, char **error)
{
	zbx_hashset_iter_t	iter;
	zbx_table_row_t		*row;
	zbx_vector_uint64_t	templateids;
	int			ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&templateids);

	zbx_hashset_iter_reset(&hosts_templates->rows, &iter);
	while (NULL != (row = (zbx_table_row_t *)zbx_hashset_iter_next(&iter)))
	{
		const char	*pf;
		char		buf[ZBX_MAX_UINT64_LEN + 1];
		zbx_uint64_t	templateid;

		pf = zbx_json_next(&row->columns, NULL);

		if (NULL != (pf = zbx_json_next_value(&row->columns, pf, buf, sizeof(buf), NULL)) &&
				SUCCEED == zbx_is_uint64(buf, &templateid))
		{
			zbx_vector_uint64_append(&templateids, templateid);
		}

		if (NULL != zbx_json_next_value(&row->columns, pf, buf, sizeof(buf), NULL) &&
				SUCCEED == zbx_is_uint64(buf, &templateid))
		{
			zbx_vector_uint64_append(&templateids, templateid);
		}
	}

	zbx_hashset_iter_reset(&hostmacro->rows, &iter);
	while (NULL != (row = (zbx_table_row_t *)zbx_hashset_iter_next(&iter)))
	{
		const char	*pf;
		char		buf[ZBX_MAX_UINT64_LEN + 1];
		zbx_uint64_t	templateid;

		pf = zbx_json_next(&row->columns, NULL);

		if (NULL != zbx_json_next_value(&row->columns, pf, buf, sizeof(buf), NULL) &&
				SUCCEED == zbx_is_uint64(buf, &templateid))
		{
			zbx_vector_uint64_append(&templateids, templateid);
		}
	}

	/* check for existing templates and create empty templates if necessary */
	if (0 != templateids.values_num)
	{
		zbx_db_row_t	dbrow;
		zbx_db_result_t	result;
		char		*sql = NULL;
		size_t		sql_alloc = 0, sql_offset = 0;
		zbx_hashset_t	templates;
		zbx_db_insert_t	db_insert;
		int		i;

		zbx_hashset_create(&templates, 100, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		zbx_vector_uint64_sort(&templateids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_vector_uint64_uniq(&templateids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "select hostid from hosts where");
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid", templateids.values,
				templateids.values_num);

		result = zbx_db_select("%s", sql);
		zbx_free(sql);

		while (NULL != (dbrow = zbx_db_fetch(result)))
		{
			zbx_uint64_t	templateid;

			ZBX_STR2UINT64(templateid, dbrow[0]);
			zbx_hashset_insert(&templates, &templateid, sizeof(templateid));
		}
		zbx_db_free_result(result);

		zbx_db_insert_prepare(&db_insert, "hosts", "hostid", "status", (char *)NULL);

		for (i = 0; i < templateids.values_num; i++)
		{
			if (NULL != zbx_hashset_search(&templates, &templateids.values[i]))
				continue;

			zbx_db_insert_add_values(&db_insert, templateids.values[i], (int)HOST_STATUS_TEMPLATE);
		}

		ret = zbx_db_insert_execute(&db_insert);
		zbx_db_insert_clean(&db_insert);

		zbx_hashset_destroy(&templates);

		if (SUCCEED != ret)
			goto out;
	}

	if (SUCCEED != proxyconfig_insert_rows(hosts_templates, error))
		goto out;

	ret = proxyconfig_update_rows(hosts_templates, error);
out:
	zbx_vector_uint64_destroy(&templateids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: sync received configuration data                                  *
 *                                                                            *
 * Parameters: config_tables - [IN] the received table data                   *
 *             full_sync     - [IN] 1 if full sync must be done, 0 otherwise  *
 *             error         - [OUT] the error message                        *
 *                                                                            *
 * Return value: SUCCEED - the tables were synced successfully                *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	proxyconfig_sync_data(zbx_vector_table_data_ptr_t *config_tables, int full_sync, char **error)
{
	zbx_table_data_t	*hostmacro, *hosts_templates;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	/* first sync isolated tables without relations to other tables */

	if (SUCCEED != proxyconfig_sync_table(config_tables, "globalmacro", error))
		return FAIL;

	if (SUCCEED != proxyconfig_sync_table(config_tables, "config_autoreg_tls", error))
		return FAIL;

	if (SUCCEED != proxyconfig_sync_table(config_tables, "config", error))
		return FAIL;

	/* process related tables by scope */

	if (SUCCEED != proxyconfig_sync_network_discovery(config_tables, error))
		return FAIL;

	if (SUCCEED != proxyconfig_sync_regexps(config_tables, error))
		return FAIL;

	if (NULL != (hostmacro = proxyconfig_get_table(config_tables, "hostmacro")))
	{
		if (NULL == (hosts_templates = proxyconfig_get_table(config_tables, "hosts_templates")))
		{
			*error = zbx_strdup(NULL, "cannot find host template data");
			return FAIL;
		}

		proxyconfig_prepare_hostmacros(hostmacro, hosts_templates, full_sync);

		if (SUCCEED != proxyconfig_prepare_rows(hostmacro, error))
			return FAIL;

		if (SUCCEED != proxyconfig_delete_rows(hostmacro, error))
			return FAIL;

		if (SUCCEED != proxyconfig_prepare_rows(hosts_templates, error))
			return FAIL;

		if (SUCCEED != proxyconfig_delete_rows(hosts_templates, error))
			return FAIL;
	}

	if (SUCCEED != proxyconfig_sync_hosts(config_tables, full_sync, error))
		return FAIL;

	if (NULL != hostmacro)
	{
		if (SUCCEED != proxyconfig_sync_templates(hosts_templates, hostmacro, error))
			return FAIL;

		if (SUCCEED != proxyconfig_insert_rows(hostmacro, error))
			return FAIL;

		if (SUCCEED != proxyconfig_update_rows(hostmacro, error))
			return FAIL;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: delete unmonitored hosts and their contents                       *
 *                                                                            *
 * Parameters: hostids - [IN] identifiers of the hosts to delete              *
 *             error   - [OUT] the error message                              *
 *                                                                            *
 * Return value: SUCCEED - the hosts were deleted successfully                *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	proxyconfig_delete_hosts(const zbx_vector_uint64_t *hostids, char **error)
{
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	int			ret = FAIL;
	zbx_vector_uint64_t	itemids, httptestids, httpstepids;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&itemids);
	zbx_vector_uint64_create(&httptestids);
	zbx_vector_uint64_create(&httpstepids);

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select itemid from items where");
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid", hostids->values, hostids->values_num);
	zbx_db_select_uint64(sql, &itemids);

	sql_offset = 0;
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select httptestid from httptest where");
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid", hostids->values, hostids->values_num);
	zbx_db_select_uint64(sql, &httptestids);

	if (0 != httptestids.values_num)
	{
		sql_offset = 0;
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select httpstepid from httpstep where");
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "httptestid", httptestids.values,
				httptestids.values_num);
		zbx_db_select_uint64(sql, &httpstepids);

		if (0 != httpstepids.values_num)
		{
			sql_offset = 0;
			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from httpstep_field where");
			zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "httpstepid", httpstepids.values,
					httpstepids.values_num);
			if (ZBX_DB_OK > zbx_db_execute("%s", sql))
				goto out;

			sql_offset = 0;
			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from httpstepitem where");
			zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "httpstepid", httpstepids.values,
					httpstepids.values_num);
			if (ZBX_DB_OK > zbx_db_execute("%s", sql))
				goto out;

			sql_offset = 0;
			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from httpstep where");
			zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "httpstepid", httpstepids.values,
					httpstepids.values_num);
			if (ZBX_DB_OK > zbx_db_execute("%s", sql))
				goto out;

		}
		sql_offset = 0;
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from httptest_field where");
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "httptestid", httptestids.values,
				httptestids.values_num);
		if (ZBX_DB_OK > zbx_db_execute("%s", sql))
			goto out;

		sql_offset = 0;
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from httptestitem where");
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "httptestid", httptestids.values,
				httptestids.values_num);
		if (ZBX_DB_OK > zbx_db_execute("%s", sql))
			goto out;

		sql_offset = 0;
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from httptest where");
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "httptestid", httptestids.values,
				httptestids.values_num);
		if (ZBX_DB_OK > zbx_db_execute("%s", sql))
			goto out;
	}

	if (0 != itemids.values_num)
	{
		sql_offset = 0;
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from item_preproc where");
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "itemid", itemids.values, itemids.values_num);
		if (ZBX_DB_OK > zbx_db_execute("%s", sql))
			goto out;

		sql_offset = 0;
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "update items set master_itemid=null where");
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "itemid", itemids.values, itemids.values_num);
		if (ZBX_DB_OK > zbx_db_execute("%s", sql))
			goto out;

		sql_offset = 0;
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from items where");
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "itemid", itemids.values, itemids.values_num);
		if (ZBX_DB_OK > zbx_db_execute("%s", sql))
			goto out;
	}

	sql_offset = 0;
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from hosts where");
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid", hostids->values, hostids->values_num);
	if (ZBX_DB_OK > zbx_db_execute("%s", sql))
		goto out;

	ret = SUCCEED;
out:
	zbx_free(sql);

	zbx_vector_uint64_destroy(&httpstepids);
	zbx_vector_uint64_destroy(&httptestids);
	zbx_vector_uint64_destroy(&itemids);

	if (SUCCEED != ret)
		*error = zbx_strdup(NULL, "cannot delete hosts");

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: delete corresponding records when all macros are removed/templates*
 *          unlinked from a host                                              *
 *                                                                            *
 * Parameters: hostids - [IN] identifiers of the cleared hosts                *
 *             error   - [OUT] the error message                              *
 *                                                                            *
 * Return value: SUCCEED - the hosts were cleared successfully                *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	proxyconfig_delete_hostmacros(const zbx_vector_uint64_t *hostids, char **error)
{
	char	*sql = NULL;
	size_t	sql_alloc = 0, sql_offset = 0;
	int	ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from hostmacro where");
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid", hostids->values, hostids->values_num);
	if (ZBX_DB_OK > zbx_db_execute("%s", sql))
		goto out;

	sql_offset = 0;
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from hosts_templates where");
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid", hostids->values, hostids->values_num);
	if (ZBX_DB_OK > zbx_db_execute("%s", sql))
		goto out;

	ret = SUCCEED;

out:
	zbx_free(sql);

	if (FAIL == ret)
		*error = zbx_strdup(NULL, "cannot delete host macros");

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: delete all global macros                                          *
 *                                                                            *
 * Return value: SUCCEED - the global macros were deleted successfully        *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	proxyconfig_delete_globalmacros(char **error)
{
	int	ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (ZBX_DB_OK > zbx_db_execute("delete from globalmacro"))
	{
		*error = zbx_strdup(NULL, "cannot delete global macros");
		ret = FAIL;
	}
	else
		ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return ret;
}

static int	proxyconfig_clear_host_proxy(zbx_table_data_t *proxy, char **error)
{
	char	*sql = NULL;
	size_t	sql_alloc = 0, sql_offset = 0;
	int	ret;

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "delete from host_proxy where");
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "proxyid", proxy->del_ids.values,
			proxy->del_ids.values_num);

	ret = zbx_db_execute("%s", sql);
	zbx_free(sql);

	if (ZBX_DB_OK > ret)
	{
		*error = zbx_strdup(NULL, "cannot delete host_proxy records");
		return FAIL;
	}

	return SUCCEED;
}

static int	proxyconfig_sync_proxy_group(zbx_vector_table_data_ptr_t *config_tables, struct zbx_json_parse *jp,
		int full_sync, char **error)
{
	zbx_table_data_t	*host_proxy, *proxy;

	if (NULL == (host_proxy = proxyconfig_get_table(config_tables, "host_proxy")))
		return proxyconfig_sync_table(config_tables, "proxy", error);

	if (NULL == (proxy = proxyconfig_get_table(config_tables, "proxy")))
	{
		/* proxy table is always added if host_proxy table is sent */
		*error = zbx_strdup(NULL, "cannot find proxy data");
		return FAIL;
	}

	if (0 == full_sync)
	{
		zbx_vector_uint64_t	recids;
		zbx_hashset_iter_t	iter;
		zbx_table_row_t		*row;

		zbx_vector_uint64_create(&recids);

		zbx_hashset_iter_reset(&host_proxy->rows, &iter);
		while (NULL != (row = (zbx_table_row_t *)zbx_hashset_iter_next(&iter)))
			zbx_vector_uint64_append(&recids, row->recid);

		proxyconfig_prepare_table(host_proxy, "hostproxyid", &recids, NULL);

		/* read deleted hotsproxyids and add to table data */

		struct zbx_json_parse	jp_del;
		char	tmp[ZBX_MAX_UINT64_LEN + 1];

		if (SUCCEED == zbx_json_brackets_by_name(jp, ZBX_PROTO_TAG_DEL_HOSTPROXYIDS, &jp_del))
		{
			const char	*p;
			zbx_uint64_t	hostproxyid;

			for (p = 0; NULL != (p = zbx_json_next_value(&jp_del, p, tmp, sizeof(tmp), NULL));)
			{
				if (SUCCEED == zbx_is_uint64(tmp, &hostproxyid))
					zbx_vector_uint64_append(&host_proxy->del_ids, hostproxyid);
			}
		}

		zbx_vector_uint64_destroy(&recids);

		proxyconfig_prepare_table(proxy, NULL, NULL, NULL);

		if (0 != proxy->del_ids.values_num && SUCCEED != proxyconfig_clear_host_proxy(proxy, error))
			return FAIL;
	}
	else
	{
		proxyconfig_prepare_table(host_proxy, NULL, NULL, NULL);
		proxyconfig_prepare_table(proxy, NULL, NULL, NULL);
	}

	if (SUCCEED != proxyconfig_prepare_rows(host_proxy, error))
		return FAIL;

	if (SUCCEED != proxyconfig_delete_rows(host_proxy, error))
		return FAIL;

	if (SUCCEED != proxyconfig_prepare_rows(proxy, error))
		return FAIL;

	if (SUCCEED != proxyconfig_delete_rows(proxy, error))
		return FAIL;

	if (SUCCEED != proxyconfig_insert_rows(proxy, error))
		return FAIL;

	if (SUCCEED != proxyconfig_update_rows(proxy, error))
		return FAIL;

	if (SUCCEED != proxyconfig_insert_rows(host_proxy, error))
		return FAIL;

	return proxyconfig_update_rows(host_proxy, error);
}

static int	proxyconfig_prepare_proxy_group(zbx_vector_table_data_ptr_t *config_tables, int full_sync,
		struct zbx_json_parse *jp, zbx_uint64_t *hostmap_revision, char **error)
{
	if (NULL == jp->start)
	{
		/* proxy is not assigned to proxy group - purge residual configuration data */
		zbx_uint64_t	cfg_revision, cfg_hostmap_revision;

		zbx_dc_get_upstream_revision(&cfg_revision, &cfg_hostmap_revision);

		if (0 != cfg_hostmap_revision || 0 != full_sync)
		{
			*hostmap_revision = 0;
			if (ZBX_DB_OK <= zbx_db_execute("delete from host_proxy") &&
					ZBX_DB_OK <= zbx_db_execute("delete from proxy"))
			{
				return SUCCEED;
			}

			return FAIL;
		}

		return SUCCEED;
	}

	char	tmp[ZBX_MAX_UINT64_LEN + 1];

	if (SUCCEED != zbx_json_value_by_name(jp, ZBX_PROTO_TAG_HOSTMAP_REVISION, tmp, sizeof(tmp), NULL))
	{
		*error = zbx_strdup(NULL, "no hostmap_revision tag in proxy group configuration");
		return FAIL;
	}

	if (SUCCEED != zbx_is_uint64(tmp, hostmap_revision))
	{
		*error = zbx_strdup(NULL, "invalid hostmap_revision value in proxy group configuration");
		return FAIL;
	}

	if (SUCCEED != zbx_json_value_by_name(jp, ZBX_PROTO_TAG_FAILOVER_DELAY, tmp, sizeof(tmp), NULL))
	{
		*error = zbx_strdup(NULL, "no failover_delay tag in proxy group configuration");
		return FAIL;
	}

	zbx_dc_set_proxy_failover_delay(tmp);

	return proxyconfig_sync_proxy_group(config_tables, jp, full_sync, error);
}

#define PROXYCONFIG_ZBX_TABLE_NUM	26

/******************************************************************************
 *                                                                            *
 * Purpose: update configuration                                              *
 *                                                                            *
 ******************************************************************************/
int	zbx_proxyconfig_process(const char *addr, struct zbx_json_parse *jp, zbx_proxyconfig_write_status_t *status,
		char **error)
{
	zbx_vector_table_data_ptr_t	config_tables;
	int			ret = SUCCEED, full_sync = 0, delete_globalmacros = 0, loglevel;
	char			tmp[ZBX_MAX_UINT64_LEN + 1];
	struct zbx_json_parse	jp_data = {NULL, NULL}, jp_del_hostids = {NULL, NULL}, jp_proxy_group = {NULL, NULL},
				jp_del_macro_hostids = {NULL, NULL};
	zbx_uint64_t		config_revision, hostmap_revision = 0;
	zbx_vector_uint64_t	del_hostids, del_macro_hostids;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	(void)zbx_json_brackets_by_name(jp, ZBX_PROTO_TAG_DATA, &jp_data);
	(void)zbx_json_brackets_by_name(jp, ZBX_PROTO_TAG_REMOVED_HOSTIDS, &jp_del_hostids);
	(void)zbx_json_brackets_by_name(jp, ZBX_PROTO_TAG_REMOVED_MACRO_HOSTIDS, &jp_del_macro_hostids);
	(void)zbx_json_brackets_by_name(jp, ZBX_PROTO_TAG_PROXY_GROUP, &jp_proxy_group);

	if ((NULL == jp_data.start || 1 == jp_data.end - jp_data.start) && NULL == jp_del_hostids.start &&
			NULL == jp_del_macro_hostids.start)
	{
		loglevel = LOG_LEVEL_DEBUG;
	}
	else
		loglevel = LOG_LEVEL_WARNING;

	zabbix_log(loglevel, "received configuration data from server at \"%s\", datalen " ZBX_FS_SSIZE_T,
			addr, jp->end - jp->start + 1);

	if (1 == jp->end - jp->start)
	{
		*status = ZBX_PROXYCONFIG_WRITE_STATUS_EMPTY;
		/* no configuration updates */
		goto out;
	}

	if (SUCCEED != (ret = zbx_json_value_by_name(jp, ZBX_PROTO_TAG_CONFIG_REVISION, tmp, sizeof(tmp), NULL)))
	{
		*error = zbx_strdup(NULL, "no config_revision tag in proxy configuration response");
		goto out;
	}

	if (SUCCEED != (ret = zbx_is_uint64(tmp, &config_revision)))
	{
		*error = zbx_strdup(NULL, "invalid config_revision value in proxy configuration response");
		goto out;
	}

	if (SUCCEED == zbx_json_value_by_name(jp, ZBX_PROTO_TAG_FULL_SYNC, tmp, sizeof(tmp), NULL))
		full_sync = atoi(tmp);

	zbx_vector_table_data_ptr_create(&config_tables);
	zbx_vector_table_data_ptr_reserve(&config_tables, PROXYCONFIG_ZBX_TABLE_NUM);
	zbx_vector_uint64_create(&del_hostids);
	zbx_vector_uint64_create(&del_macro_hostids);

	if (NULL != jp_data.start)
	{
		if (SUCCEED != (ret = proxyconfig_parse_data(&jp_data, &config_tables, error)))
			goto clean;

		if (SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_TRACE))
			proxyconfig_dump_data(&config_tables);

		proxyconfig_add_default_tables(&config_tables);
	}

	if (NULL != jp_del_hostids.start)
	{
		const char	*p;
		zbx_uint64_t	hostid;

		for (p = 0; NULL != (p = zbx_json_next_value(&jp_del_hostids, p, tmp, sizeof(tmp), NULL));)
		{
			if (SUCCEED == zbx_is_uint64(tmp, &hostid))
				zbx_vector_uint64_append(&del_hostids, hostid);
		}
	}

	if (NULL != jp_del_macro_hostids.start)
	{
		const char	*p;
		zbx_uint64_t	hostid;

		for (p = 0; NULL != (p = zbx_json_next_value(&jp_del_macro_hostids, p, tmp, sizeof(tmp), NULL));)
		{
			if (SUCCEED == zbx_is_uint64(tmp, &hostid))
			{
				if (0 != hostid)
					zbx_vector_uint64_append(&del_macro_hostids, hostid);
				else
					delete_globalmacros = 1;
			}
		}
	}

	zbx_db_begin();

	if (0 != config_tables.values_num)
		ret = proxyconfig_sync_data(&config_tables, full_sync, error);

	if (SUCCEED == ret && 0 != del_hostids.values_num)
		ret = proxyconfig_delete_hosts(&del_hostids, error);

	if (SUCCEED == ret && 0 != del_macro_hostids.values_num)
		ret = proxyconfig_delete_hostmacros(&del_macro_hostids, error);

	if (SUCCEED == ret && 0 != delete_globalmacros)
		ret = proxyconfig_delete_globalmacros(error);

	if (SUCCEED == ret)
	{
		ret = proxyconfig_prepare_proxy_group(&config_tables, full_sync, &jp_proxy_group, &hostmap_revision,
				error);
	}

	if (SUCCEED == ret)
	{
		if (ZBX_DB_OK == zbx_db_commit())
			zbx_dc_set_upstream_revision(config_revision, hostmap_revision);
	}
	else
		zbx_db_rollback();
clean:
	zbx_vector_uint64_destroy(&del_macro_hostids);
	zbx_vector_uint64_destroy(&del_hostids);
	zbx_vector_table_data_ptr_clear_ext(&config_tables, table_data_free);
	zbx_vector_table_data_ptr_destroy(&config_tables);

out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: receive configuration tables from server (passive proxies)        *
 *                                                                            *
 ******************************************************************************/
void	zbx_recv_proxyconfig(zbx_socket_t *sock, const zbx_config_tls_t *config_tls,
		const zbx_config_vault_t *config_vault, int config_timeout, int config_trapper_timeout,
		const char *config_source_ip, const char *config_ssl_ca_location, const char *config_ssl_cert_location,
		const char *config_ssl_key_location, const char *server)
{
	struct zbx_json_parse		jp_config, jp_kvs_paths = {0};
	int				ret;
	struct zbx_json			j;
	char				*error = NULL;
	zbx_uint64_t			config_revision, hostmap_revision;
	zbx_proxyconfig_write_status_t	status = ZBX_PROXYCONFIG_WRITE_STATUS_DATA;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (SUCCEED != zbx_check_access_passive_proxy(sock, ZBX_SEND_RESPONSE, "configuration update", config_tls,
			config_timeout, server))
	{
		goto out;
	}

	if (SUCCEED != (ret = zbx_dc_sync_lock()))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot process proxy configuration data received from server at"
			" \"%s\": configuration sync is already in progress", sock->peer);
		zbx_send_proxy_response(sock, ret, "configuration sync is already in progress", config_timeout);
		goto out;
	}

	zbx_dc_get_upstream_revision(&config_revision, &hostmap_revision);

	zbx_json_init(&j, 1024);
	zbx_json_addstring(&j, ZBX_PROTO_TAG_VERSION, ZABBIX_VERSION, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(&j, ZBX_PROTO_TAG_SESSION, zbx_dc_get_session_token(), ZBX_JSON_TYPE_STRING);
	zbx_json_adduint64(&j, ZBX_PROTO_TAG_CONFIG_REVISION, config_revision);

	if (0 != hostmap_revision)
		zbx_json_adduint64(&j, ZBX_PROTO_TAG_HOSTMAP_REVISION, hostmap_revision);

	if (SUCCEED != zbx_tcp_send_ext(sock, j.buffer, j.buffer_size, 0, (unsigned char)sock->protocol,
			config_timeout))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot send proxy configuration information to sever at \"%s\": %s",
				sock->peer, zbx_socket_strerror());
		goto out;
	}

	if (FAIL == zbx_tcp_recv_ext(sock, config_trapper_timeout, ZBX_TCP_LARGE))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot receive proxy configuration data from server at \"%s\": %s",
				sock->peer, zbx_socket_strerror());
		goto out;
	}

	if (NULL == sock->buffer)
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot parse empty proxy configuration data received from server at"
				" \"%s\"", sock->peer);
		zbx_send_proxy_response(sock, FAIL, "cannot parse empty data", config_timeout);
		goto out;
	}

	if (SUCCEED != (ret = zbx_json_open(sock->buffer, &jp_config)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot parse proxy configuration data received from server at"
				" \"%s\": %s", sock->peer, zbx_json_strerror());
		zbx_send_proxy_response(sock, ret, zbx_json_strerror(), config_timeout);
		goto out;
	}

	if (SUCCEED == (ret = zbx_proxyconfig_process(sock->peer, &jp_config, &status, &error)))
	{
		if (SUCCEED == zbx_rtc_reload_config_cache(&error))
		{
			if (SUCCEED == zbx_json_brackets_by_name(&jp_config, ZBX_PROTO_TAG_MACRO_SECRETS, &jp_kvs_paths))
			{
				zbx_dc_sync_kvs_paths(&jp_kvs_paths, config_vault, config_source_ip,
						config_ssl_ca_location, config_ssl_cert_location,
						config_ssl_key_location);
			}
		}
		else
		{
			THIS_SHOULD_NEVER_HAPPEN;
			zabbix_log(LOG_LEVEL_WARNING, "cannot send message to configuration syncer: %s", error);
			zbx_free(error);
		}

		zbx_dc_set_proxy_lastonline((int)time(NULL));
	}
	else
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot process proxy onfiguration data received from server at"
				" \"%s\": %s", sock->peer, error);
	}

	zbx_dc_sync_unlock();
	zbx_send_proxy_response(sock, ret, error, config_timeout);
	zbx_free(error);
out:
#ifdef	HAVE_MALLOC_TRIM
	/* avoid memory not being released back to the system if large proxy configuration is retrieved from database */
	if (ZBX_PROXYCONFIG_WRITE_STATUS_DATA == status)
		malloc_trim(ZBX_MALLOC_TRIM);
#endif
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}
