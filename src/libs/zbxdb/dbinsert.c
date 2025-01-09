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

#include "dbconn.h"
#include "zbxcrypto.h"
#include "zbxalgo.h"
#include "zbxcommon.h"
#include "zbxdb.h"
#include "zbxdbschema.h"
#include "zbxstr.h"
#include "zbxtypes.h"

/******************************************************************************
 *                                                                            *
 * Purpose: releases resources allocated by bulk insert operations            *
 *                                                                            *
 * Parameters: self        - [IN] the bulk insert data                        *
 *                                                                            *
 ******************************************************************************/
static void	db_insert_clear_rows(zbx_db_insert_t *db_insert)
{
	for (int i = 0; i < db_insert->rows.values_num; i++)
	{
		zbx_db_value_t	*row = db_insert->rows.values[i];

		for (int j = 0; j < db_insert->fields.values_num; j++)
		{
			const zbx_db_field_t	*field = db_insert->fields.values[j];

			switch (field->type)
			{
				case ZBX_TYPE_CHAR:
				case ZBX_TYPE_TEXT:
				case ZBX_TYPE_LONGTEXT:
				case ZBX_TYPE_CUID:
				case ZBX_TYPE_BLOB:
					zbx_free(row[j].str);
					break;
			}
		}

		zbx_free(row);
	}

	zbx_vector_db_value_ptr_clear(&db_insert->rows);
}

/******************************************************************************
 *                                                                            *
 * Purpose: releases resources allocated by bulk insert operations            *
 *                                                                            *
 * Parameters: self        - [IN] the bulk insert data                        *
 *                                                                            *
 ******************************************************************************/
void	zbx_db_insert_clean(zbx_db_insert_t *db_insert)
{
	db_insert_clear_rows(db_insert);
	zbx_vector_db_value_ptr_destroy(&db_insert->rows);

	zbx_vector_const_db_field_ptr_destroy(&db_insert->fields);
}

/******************************************************************************
 *                                                                            *
 * Purpose: prepare for database bulk insert operation                        *
 *                                                                            *
 * Parameters: self        - [IN] the bulk insert data                        *
 *             table       - [IN] the target table name                       *
 *             fields      - [IN] names of the fields to insert               *
 *             fields_num  - [IN] the number of items in fields array         *
 *                                                                            *
 * Comments: The operation fails if the target table does not have the        *
 *           specified fields defined in its schema.                          *
 *                                                                            *
 *           Usage example:                                                   *
 *             zbx_db_insert_t ins;                                           *
 *                                                                            *
 *             zbx_db_insert_prepare(&ins, "history", "id", "value");         *
 *             zbx_db_insert_add_values(&ins, (zbx_uint64_t)1, 1.0);          *
 *             zbx_db_insert_add_values(&ins, (zbx_uint64_t)2, 2.0);          *
 *               ...                                                          *
 *             zbx_db_insert_execute(&ins);                                   *
 *             zbx_db_insert_clean(&ins);                                     *
 *                                                                            *
 ******************************************************************************/
void	zbx_dbconn_prepare_insert_dyn(zbx_dbconn_t *db, zbx_db_insert_t *db_insert, const zbx_db_table_t *table,
		const zbx_db_field_t * const *fields, int fields_num)
{
	if (0 == fields_num)
	{
		THIS_SHOULD_NEVER_HAPPEN;
		exit(EXIT_FAILURE);
	}

	db_insert->db = db;
	db_insert->autoincrement = -1;
	db_insert->lastid = 0;
	db_insert->batch_size = 0;

	zbx_vector_const_db_field_ptr_create(&db_insert->fields);
	zbx_vector_db_value_ptr_create(&db_insert->rows);

	db_insert->table = table;

	for (int i = 0; i < fields_num; i++)
		zbx_vector_const_db_field_ptr_append(&db_insert->fields, fields[i]);
}

/******************************************************************************
 *                                                                            *
 * Purpose: prepare for database bulk insert operation                        *
 *                                                                            *
 * Comments: This is a convenience wrapper for zbx_db_insert_prepare_dyn()    *
 *           function.                                                        *
 *                                                                            *
 ******************************************************************************/
void	zbx_dbconn_prepare_vinsert(zbx_dbconn_t *db, zbx_db_insert_t *db_insert, const char *table, va_list args)
{
	zbx_vector_const_db_field_ptr_t	fields;
	char				*field;
	const zbx_db_table_t		*ptable;
	const zbx_db_field_t		*pfield;

	/* find the table and fields in database schema */
	if (NULL == (ptable = zbx_db_get_table(table)))
	{
		THIS_SHOULD_NEVER_HAPPEN;
		exit(EXIT_FAILURE);
	}

	zbx_vector_const_db_field_ptr_create(&fields);

	while (NULL != (field = va_arg(args, char *)))
	{
		if (NULL == (pfield = zbx_db_get_field(ptable, field)))
		{
			zabbix_log(LOG_LEVEL_ERR, "Cannot locate table \"%s\" field \"%s\" in database schema",
					table, field);
			THIS_SHOULD_NEVER_HAPPEN;
			exit(EXIT_FAILURE);
		}

		zbx_vector_const_db_field_ptr_append(&fields, pfield);
	}

	zbx_dbconn_prepare_insert_dyn(db, db_insert, ptable, (const zbx_db_field_t * const *)fields.values,
			fields.values_num);

	zbx_vector_const_db_field_ptr_destroy(&fields);
}

/******************************************************************************
 *                                                                            *
 * Purpose: prepare for database bulk insert operation                        *
 *                                                                            *
 ******************************************************************************/
void	zbx_dbconn_prepare_insert(zbx_dbconn_t *db, zbx_db_insert_t *db_insert, const char *table, ...)
{
	va_list	args;

	va_start(args, table);
	zbx_dbconn_prepare_vinsert(db, db_insert, table, args);
	va_end(args);
}

/******************************************************************************
 *                                                                            *
 * Purpose: adds row values for database bulk insert operation                *
 *                                                                            *
 * Parameters: self        - [IN] the bulk insert data                        *
 *             values      - [IN] the values to insert                        *
 *             fields_num  - [IN] the number of items in values array         *
 *                                                                            *
 * Comments: The values must be listed in the same order as the field names   *
 *           for insert preparation functions.                                *
 *                                                                            *
 ******************************************************************************/
void	zbx_db_insert_add_values_dyn(zbx_db_insert_t *db_insert, zbx_db_value_t **values, int values_num)
{
	int		i;
	zbx_db_value_t	*row;

	if (values_num != db_insert->fields.values_num)
	{
		THIS_SHOULD_NEVER_HAPPEN;
		exit(EXIT_FAILURE);
	}

	if (0 != db_insert->batch_size && db_insert->batch_size <= db_insert->rows.values_num)
	{
		zbx_db_insert_execute(db_insert);
		db_insert_clear_rows(db_insert);
	}

	row = (zbx_db_value_t *)zbx_malloc(NULL, (size_t)db_insert->fields.values_num * sizeof(zbx_db_value_t));

	for (i = 0; i < db_insert->fields.values_num; i++)
	{
		const zbx_db_field_t	*field = db_insert->fields.values[i];
		const zbx_db_value_t	*value = values[i];

		switch (field->type)
		{
			case ZBX_TYPE_LONGTEXT:
			case ZBX_TYPE_CHAR:
			case ZBX_TYPE_TEXT:
			case ZBX_TYPE_CUID:
			case ZBX_TYPE_BLOB:
				row[i].str = db_dyn_escape_field_len(field, value->str, ESCAPE_SEQUENCE_ON);
				break;
			case ZBX_TYPE_INT:
			case ZBX_TYPE_FLOAT:
			case ZBX_TYPE_UINT:
			case ZBX_TYPE_ID:
			case ZBX_TYPE_SERIAL:
				row[i] = *value;
				break;
			default:
				THIS_SHOULD_NEVER_HAPPEN;
				exit(EXIT_FAILURE);
		}
	}

	zbx_vector_db_value_ptr_append(&db_insert->rows, row);
}

/******************************************************************************
 *                                                                            *
 * Purpose: adds row values for database bulk insert operation                *
 *                                                                            *
 * Parameters: self - [IN] the bulk insert data                               *
 *             ...  - [IN] the values to insert                               *
 *                                                                            *
 * Comments: This is a convenience wrapper for zbx_db_insert_add_values_dyn() *
 *           function.                                                        *
 *           Note that the types of the passed values must conform to the     *
 *           corresponding field types.                                       *
 *                                                                            *
 ******************************************************************************/
void	zbx_db_insert_add_values(zbx_db_insert_t *db_insert, ...)
{
	zbx_vector_ptr_t	values;
	va_list			args;
	int			i;
	const zbx_db_field_t	*field;
	zbx_db_value_t		*value;

	va_start(args, db_insert);

	zbx_vector_ptr_create(&values);

	for (i = 0; i < db_insert->fields.values_num; i++)
	{
		field = (const zbx_db_field_t *)db_insert->fields.values[i];

		value = (zbx_db_value_t *)zbx_malloc(NULL, sizeof(zbx_db_value_t));

		switch (field->type)
		{
			case ZBX_TYPE_CHAR:
			case ZBX_TYPE_TEXT:
			case ZBX_TYPE_LONGTEXT:
			case ZBX_TYPE_CUID:
			case ZBX_TYPE_BLOB:
				value->str = va_arg(args, char *);
				break;
			case ZBX_TYPE_INT:
				value->i32 = va_arg(args, int);
				break;
			case ZBX_TYPE_FLOAT:
				value->dbl = va_arg(args, double);
				break;
			case ZBX_TYPE_UINT:
			case ZBX_TYPE_ID:
				value->ui64 = va_arg(args, zbx_uint64_t);
				break;
			default:
				THIS_SHOULD_NEVER_HAPPEN;
				exit(EXIT_FAILURE);
		}

		zbx_vector_ptr_append(&values, value);
	}

	va_end(args);

	zbx_db_insert_add_values_dyn(db_insert, (zbx_db_value_t **)values.values, values.values_num);

	zbx_vector_ptr_clear_ext(&values, zbx_ptr_free);
	zbx_vector_ptr_destroy(&values);
}

#if defined(HAVE_MYSQL) || defined(HAVE_POSTGRESQL)

static void	dbconn_escape_bin(zbx_dbconn_t *db, const char *src, char **dst, size_t size)
{
#if defined(HAVE_MYSQL)
	mysql_real_escape_string(db->conn, *dst, src, size);
#elif defined(HAVE_POSTGRESQL)
	size_t	dst_size;

	*dst = (char*)PQescapeByteaConn(db->conn, (const unsigned char*)src, size, &dst_size);
#endif
}

/******************************************************************************
 *                                                                            *
 * Purpose: decodes Base64 encoded binary data and escapes it allowing it to  *
 *          be used inside sql statement                                      *
 *                                                                            *
 * Parameters: db                  - [IN] database connection                 *
 *             sql_insert_data     - [IN/OUT] base64 encoded unescaped data   *
 *                                                                            *
 * Comment: input data is released from memory and replaced with pointer      *
 *          to the output data.                                               *
 *                                                                            *
 ******************************************************************************/
static void	decode_and_escape_binary_value_for_sql(zbx_dbconn_t *db, char **sql_insert_data)
{
	size_t	binary_data_len;
	char	*escaped_binary;

	size_t	binary_data_max_len = strlen(*sql_insert_data) * 3 / 4 + 1;
	char	*binary_data = (char*)zbx_malloc(NULL, binary_data_max_len);

	zbx_base64_decode(*sql_insert_data, binary_data, binary_data_max_len, &binary_data_len);

#if defined (HAVE_MYSQL)
	escaped_binary = (char*)zbx_malloc(NULL, 2 * binary_data_len);
#endif
	dbconn_escape_bin(db, binary_data, &escaped_binary, binary_data_len);

	zbx_free(binary_data);
	zbx_free(*sql_insert_data);
	*sql_insert_data = escaped_binary;
}
#else
static void	decode_and_escape_binary_value_for_sql(zbx_dbconn_t *db, char **sql_insert_data)
{
	ZBX_UNUSED(db);
	ZBX_UNUSED(sql_insert_data);
}
#endif

/******************************************************************************
 *                                                                            *
 * Purpose: executes the prepared database bulk insert operation              *
 *                                                                            *
 * Parameters: self - [IN] the bulk insert data                               *
 *                                                                            *
 * Return value: SUCCEED if the operation completed successfully or           *
 *               FAIL otherwise.                                              *
 *                                                                            *
 ******************************************************************************/
int	zbx_db_insert_execute(zbx_db_insert_t *db_insert)
{
#ifdef HAVE_MULTIROW_INSERT
#	define ZBX_ROW_DL	","
#else
#	define ZBX_ROW_DL	";\n"
#endif

	int			ret = FAIL, i, j;
	const zbx_db_field_t	*field;
	char			*sql_command, delim[2] = {',', '('}, *sql;
	size_t			sql_command_alloc = 512, sql_command_offset = 0,
				sql_alloc = 16 * ZBX_KIBIBYTE, sql_offset = 0;
#ifdef HAVE_MYSQL
	char		*sql_values = NULL;
	size_t		sql_values_alloc = 0, sql_values_offset = 0;
#endif

	if (0 == db_insert->rows.values_num)
		return SUCCEED;

	/* process the auto increment field */
	if (-1 != db_insert->autoincrement)
	{
		zbx_uint64_t	id;

		if (0 == (id = zbx_dbconn_get_maxid_num(db_insert->db, db_insert->table->table,
				db_insert->rows.values_num)))
		{
			/* returning 0 nextid means failed transaction */
			return FAIL;
		}

		for (i = 0; i < db_insert->rows.values_num; i++)
		{
			zbx_db_value_t	*values = (zbx_db_value_t *)db_insert->rows.values[i];

			values[db_insert->autoincrement].ui64 = id++;
		}

		db_insert->lastid = id - 1;
		/* reset autoincrement so execute could be retried with the same ids */
		db_insert->autoincrement = -1;
	}

	sql = (char *)zbx_malloc(NULL, sql_alloc);
	sql_command = (char *)zbx_malloc(NULL, sql_command_alloc);

	/* create sql insert statement command */

	zbx_strcpy_alloc(&sql_command, &sql_command_alloc, &sql_command_offset, "insert into ");
	zbx_strcpy_alloc(&sql_command, &sql_command_alloc, &sql_command_offset, db_insert->table->table);
	zbx_chrcpy_alloc(&sql_command, &sql_command_alloc, &sql_command_offset, ' ');

	for (i = 0; i < db_insert->fields.values_num; i++)
	{
		field = (const zbx_db_field_t *)db_insert->fields.values[i];

		zbx_chrcpy_alloc(&sql_command, &sql_command_alloc, &sql_command_offset, delim[0 == i]);
		zbx_strcpy_alloc(&sql_command, &sql_command_alloc, &sql_command_offset, field->name);
	}
#ifdef HAVE_MYSQL
	/* MySQL workaround - explicitly add missing text fields with '' default value */
	for (field = db_insert->table->fields; NULL != field->name; field++)
	{
		switch (field->type)
		{
			case ZBX_TYPE_BLOB:
			case ZBX_TYPE_TEXT:
			case ZBX_TYPE_LONGTEXT:
			case ZBX_TYPE_CUID:
				if (FAIL != zbx_vector_const_db_field_ptr_search(&db_insert->fields, field,
						ZBX_DEFAULT_PTR_COMPARE_FUNC))
				{
					continue;
				}

				zbx_chrcpy_alloc(&sql_command, &sql_command_alloc, &sql_command_offset, ',');
				zbx_strcpy_alloc(&sql_command, &sql_command_alloc, &sql_command_offset, field->name);
				zbx_strcpy_alloc(&sql_values, &sql_values_alloc, &sql_values_offset, ",''");
				break;
		}
	}
#endif
	zbx_strcpy_alloc(&sql_command, &sql_command_alloc, &sql_command_offset, ") values ");

	for (i = 0; i < db_insert->rows.values_num; i++)
	{
		zbx_db_value_t	*values = (zbx_db_value_t *)db_insert->rows.values[i];

#ifdef HAVE_MULTIROW_INSERT
		if (16 > sql_offset)
			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, sql_command);
#else
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, sql_command);
#endif
		for (j = 0; j < db_insert->fields.values_num; j++)
		{
			zbx_db_value_t	*value = &values[j];

			field = (const zbx_db_field_t *)db_insert->fields.values[j];

			zbx_chrcpy_alloc(&sql, &sql_alloc, &sql_offset, delim[0 == j]);

			switch (field->type)
			{
				case ZBX_TYPE_CHAR:
				case ZBX_TYPE_TEXT:
				case ZBX_TYPE_LONGTEXT:
				case ZBX_TYPE_CUID:
					if (0 != (field->flags & ZBX_UPPER))
					{
						zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "upper(\'");
					}
					else
						zbx_chrcpy_alloc(&sql, &sql_alloc, &sql_offset, '\'');

					zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, value->str);

					if (0 != (field->flags & ZBX_UPPER))
					{
						zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "\')");
					}
					else
						zbx_chrcpy_alloc(&sql, &sql_alloc, &sql_offset, '\'');
					break;
				case ZBX_TYPE_BLOB:
					zbx_chrcpy_alloc(&sql, &sql_alloc, &sql_offset, '\'');
					decode_and_escape_binary_value_for_sql(db_insert->db, &(value->str));
					zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, value->str);
					zbx_chrcpy_alloc(&sql, &sql_alloc, &sql_offset, '\'');
					break;
				case ZBX_TYPE_INT:
					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%d", value->i32);
					break;
				case ZBX_TYPE_FLOAT:
					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, ZBX_FS_DBL64_SQL, value->dbl);
					break;
				case ZBX_TYPE_UINT:
					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, ZBX_FS_UI64,
							value->ui64);
					break;
				case ZBX_TYPE_ID:
					zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
							zbx_db_sql_id_ins(value->ui64));
					break;
				default:
					THIS_SHOULD_NEVER_HAPPEN;
					exit(EXIT_FAILURE);
			}
		}
#ifdef HAVE_MYSQL
		if (NULL != sql_values)
			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, sql_values);
#endif

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ")" ZBX_ROW_DL);

		if (SUCCEED != (ret = zbx_dbconn_execute_overflowed_sql(db_insert->db, &sql, &sql_alloc, &sql_offset)))
			goto out;
	}

	if (0 != sql_offset)
	{
#ifdef HAVE_MULTIROW_INSERT
		if (',' == sql[sql_offset - 1])
		{
			sql_offset--;
			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");
		}
#endif

		if (ZBX_DB_OK > zbx_dbconn_execute(db_insert->db, "%s", sql))
			ret = FAIL;
	}
out:
	zbx_free(sql_command);
	zbx_free(sql);
#ifdef HAVE_MYSQL
	zbx_free(sql_values);
#endif

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: executes the prepared database bulk insert operation              *
 *                                                                            *
 * Parameters: self - [IN] the bulk insert data                               *
 *                                                                            *
 ******************************************************************************/
void	zbx_db_insert_autoincrement(zbx_db_insert_t *db_insert, const char *field_name)
{
	int	i;

	for (i = 0; i < db_insert->fields.values_num; i++)
	{
		const zbx_db_field_t	*field = (const zbx_db_field_t *)db_insert->fields.values[i];

		if (ZBX_TYPE_ID == field->type && 0 == strcmp(field_name, field->name))
		{
			db_insert->autoincrement = i;
			return;
		}
	}

	THIS_SHOULD_NEVER_HAPPEN;
	exit(EXIT_FAILURE);
}

/******************************************************************************
 *                                                                            *
 * Purpose: return the last id assigned by autoincrement                      *
 *                                                                            *
 ******************************************************************************/
zbx_uint64_t	zbx_db_insert_get_lastid(zbx_db_insert_t *self)
{
	return self->lastid;
}

/******************************************************************************
 *                                                                            *
 * Purpose: set batch size                                                    *
 *                                                                            *
 * Parameters: self       - [IN] bulk insert data                             *
 *             batch_size - [IN] maximum number of rows to cache before       *
 *                               flushing (inserting) to database.            *
 *                               0 - no limit                                 *
 *                                                                            *
 *                                                                            *
 ******************************************************************************/
void	zbx_db_insert_set_batch_size(zbx_db_insert_t *self, int batch_size)
{
	self->batch_size = batch_size;
}
