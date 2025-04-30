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

#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "zbxmockdb.h"

/* make sure that __wrap_*() prototypes match unwrapped counterparts */

#define zbx_db_vselect			__wrap_zbx_db_vselect
#define zbx_db_execute			__wrap_zbx_db_execute
#define zbx_db_execute_multiple_query	__wrap_zbx_db_execute_multiple_query
#define zbx_db_begin			__wrap_zbx_db_begin
#define zbx_db_commit			__wrap_zbx_db_commit
#include "zbxdb.h"
#undef zbx_db_vselect
#undef zbx_db_execute
#undef zbx_db_execute_multiple_query
#undef zbx_db_begin
#undef zbx_db_commit

#define ZBX_MOCK_DB_RESULT_COLUMNS_MAX	128

typedef struct
{
	char	*data_source;
	int	num;
}
zbx_mockdb_query_t;

typedef struct
{
	zbx_hashset_t	queries;
}
zbx_mockdb_t;

static zbx_mockdb_t	mockdb;

struct zbx_db_result
{
	zbx_db_row_t		row;
	char			*data_source;	/* for error messages */
	zbx_mock_handle_t	rows;
	int			row_to_fetch;	/* for error messages */
	int			columns;	/* to make sure that rows have identical number of columns */
};

zbx_db_result_t	__wrap_zbx_db_select(const char *fmt, ...);
zbx_db_result_t	__wrap_zbx_db_select_n(const char *query, int n);

zbx_db_result_t	__wrap_zbx_dbconn_select(zbx_dbconn_t *db, const char *fmt, ...);

/* zbx_mockdb_t:queries hashset support */
static zbx_hash_t	mockdb_query_hash(const void *data)
{
	const zbx_mockdb_query_t	*query = (const zbx_mockdb_query_t *)data;
	return ZBX_DEFAULT_STRING_HASH_FUNC(query->data_source);
}

static int	mockdb_query_compare(const void *d1, const void *d2)
{
	const zbx_mockdb_query_t	*q1 = (const zbx_mockdb_query_t *)d1;
	const zbx_mockdb_query_t	*q2 = (const zbx_mockdb_query_t *)d2;

	return strcmp(q1->data_source, q2->data_source);
}

static void	mockdb_query_clear(void *data)
{
	zbx_mockdb_query_t	*query = (zbx_mockdb_query_t *)data;

	zbx_free(query->data_source);
}

/* <data_source> = <table name> , { "_" , <table name> } */
static char	*generate_data_source(const char *sql)
{
	int		found = 0;
	char		*data_source = NULL, *ptr_ds;
	const char	*ptr_sql = sql, *ptr_tmp;

	data_source = zbx_calloc(NULL, 64, sizeof(char *));
	ptr_ds = data_source;

	while ('\0' != *ptr_sql)
	{
		if (0 != found)
		{
			if (' ' == *ptr_sql || ';' == *ptr_sql)
			{
				found = 0;
				*(ptr_ds++) = ' ';
			}
			else
				*(ptr_ds++) = *ptr_sql;

			ptr_sql++;
		}
		else if (NULL != (ptr_tmp = strstr(ptr_sql, "from ")))
		{
			found = 1;
			ptr_sql = ptr_tmp + strlen("from ");
		}
		else if (NULL != (ptr_tmp = strstr(ptr_sql, "join ")))
		{
			found = 1;
			ptr_sql = ptr_tmp + strlen("join ");
		}
		else
			break;
	}

	if (ptr_ds != data_source)
	{
		if (' ' == *(ptr_ds - 1))
			ptr_ds--;

		*ptr_ds = '\0';
	}
	else
		zbx_free(data_source);	/* failed to generate data_source */

	return data_source;
}

zbx_db_result_t	__wrap_zbx_db_vselect(const char *fmt, va_list args)
{
	char			*sql = NULL, *data_source = NULL;
	zbx_mock_error_t	error;
	zbx_mock_handle_t	rows;
	zbx_mockdb_query_t	*query, query_local;
	zbx_db_result_t		result = NULL;

	sql = zbx_dvsprintf(sql, fmt, args);
	printf("\tSQL: %s\n", sql);

	if (NULL == (query_local.data_source = generate_data_source(sql)))
		fail_msg("Cannot generate data source string from SQL query: %s", sql);

	zbx_free(sql);

	if (NULL == (query = zbx_hashset_search(&mockdb.queries, &query_local)))
	{
		query_local.num = 1;
		query = zbx_hashset_insert(&mockdb.queries, &query_local, sizeof(query_local));
		data_source = zbx_strdup(NULL, query->data_source);
	}
	else
	{
		query->num++;
		zbx_free(query_local.data_source);
		data_source = zbx_dsprintf(NULL, "%s (%d)", query->data_source, query->num);
	}

	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_db_rows(data_source, &rows)))
		fail_msg("Cannot find data for data source \"%s\": %s", data_source, zbx_mock_error_string(error));

	result = zbx_malloc(result, sizeof(struct zbx_db_result));
	result->row = zbx_malloc(NULL, ZBX_MOCK_DB_RESULT_COLUMNS_MAX * sizeof(char *));
	result->data_source = data_source;
	result->rows = rows;
	result->row_to_fetch = 1;
	result->columns = -1;

	return result;
}

zbx_db_result_t	__wrap_zbx_db_select(const char *fmt, ...)
{
	va_list		args;
	zbx_db_result_t	result;

	va_start(args, fmt);
	result = __wrap_zbx_db_vselect(fmt, args);
	va_end(args);

	return result;
}

zbx_db_result_t	__wrap_zbx_db_select_n(const char *query, int n)
{
	return __wrap_zbx_db_select("%s limit %d", query, n);
}

zbx_db_row_t	zbx_db_fetch(zbx_db_result_t result)
{
	zbx_mock_error_t	error;
	zbx_mock_handle_t	row, field;
	int			column = 0;

	if (NULL == result || ZBX_MOCK_END_OF_VECTOR == (error = zbx_mock_vector_element(result->rows, &row)))
		return NULL;

	if (ZBX_MOCK_SUCCESS != error)
	{
		fail_msg("Cannot fetch row %d for data source \"%s\": %s", result->row_to_fetch, result->data_source,
				zbx_mock_error_string(error));
	}

	while (ZBX_MOCK_SUCCESS == (error = zbx_mock_vector_element(row, &field)))
	{
		if (ZBX_MOCK_DB_RESULT_COLUMNS_MAX <= column)
			fail_msg("Too many columns for data source \"%s\".", result->data_source);

		if (ZBX_MOCK_SUCCESS != (error = zbx_mock_string(field, (const char **)&result->row[column])))
			break;

		column++;
	}

	if (ZBX_MOCK_END_OF_VECTOR != error)
	{
		fail_msg("Cannot get value of column %d, row %d for data source \"%s\": %s", column + 1,
				result->row_to_fetch, result->data_source, zbx_mock_error_string(error));
	}

	if (0 <= result->columns)
	{
		if (column < result->columns)
		{
			fail_msg("Too few columns in row %d for data source \"%s\".", result->row_to_fetch,
					result->data_source);
		}

		if (column > result->columns)
		{
			fail_msg("Too many columns in row %d for data source \"%s\".", result->row_to_fetch,
					result->data_source);
		}
	}
	else
		result->columns = column;

	while (column < ZBX_MOCK_DB_RESULT_COLUMNS_MAX)
		result->row[column++] = NULL;

	result->row_to_fetch++;

	return result->row;
}


void	zbx_db_free_result(zbx_db_result_t result)
{
	if (NULL != result)
	{
		zbx_free(result->row);
		zbx_free(result->data_source);
	}

	zbx_free(result);
}

int	__wrap_zbx_db_execute(const char *fmt, ...)
{
	ZBX_UNUSED(fmt);

	return 0;
}

int	__wrap_zbx_db_execute_multiple_query(const char *query, const char *field_name, const zbx_vector_uint64_t *ids)
{
	ZBX_UNUSED(query);
	ZBX_UNUSED(field_name);
	ZBX_UNUSED(ids);

	return SUCCEED;
}

void	__wrap_zbx_db_begin(void)
{
}

int	__wrap_zbx_db_commit(void)
{
	return ZBX_DB_OK;
}

void	zbx_mockdb_init(void)
{
	zbx_hashset_create_ext(&mockdb.queries, 0, mockdb_query_hash, mockdb_query_compare, mockdb_query_clear,
			ZBX_DEFAULT_MEM_MALLOC_FUNC, ZBX_DEFAULT_MEM_REALLOC_FUNC, ZBX_DEFAULT_MEM_FREE_FUNC);
}

void	zbx_mockdb_destroy(void)
{
	zbx_hashset_destroy(&mockdb.queries);
}

zbx_db_result_t	zbx_dbconn_vselect(zbx_dbconn_t *db, const char *fmt, va_list args)
{
	ZBX_UNUSED(db);

	return __wrap_zbx_db_vselect(fmt, args);
}
