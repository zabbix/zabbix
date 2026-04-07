/*
** Copyright (C) 2001-2026 Zabbix SIA
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

#include "zbxcommon.h"
#include "zbxdb.h"
#include "zbxjson.h"
#include "zbxtelemetry.h"

typedef enum tq_db_type
{
	TQ_SQL_DB_TYPE_POSTGRESQL = 0,
	TQ_SQL_DB_TYPE_MYSQL
}
tq_db_type_t;

static char	*tq_sql_dyn_escape_string(const char *src, tq_db_type_t db_type)
{
	// FIXME: placeholder, zbx_db_dyn_escape_string should not be used, must be implemented for each db separately
	char	*src_esc = zbx_db_dyn_escape_string(src);
	size_t	src_esc_strlen = strlen(src_esc);
	char	*dst = zbx_malloc(NULL, src_esc_strlen + 2 + 1);
	char	quote_char = '\'';

	*dst = quote_char;
	zbx_strlcpy(dst + 1, src_esc, src_esc_strlen + 1);
	*(dst + 1 + src_esc_strlen) = quote_char;
	*(dst + 1 + src_esc_strlen + 1) = '\0';

	zbx_free(src_esc);

	return dst;
}

static char	*tq_sql_dyn_escape_like_pattern(const char *src, tq_db_type_t db_type)
{
	/* FIXME: placeholder   */
	/* TODO: escape % and _ */

	return tq_sql_dyn_escape_string(src, db_type);
}

/******************************************************************************
 *                                                                            *
 * Return value: escaped string to be used as column or table name            *
 *                                                                            *
 ******************************************************************************/
static char	*tq_sql_dyn_escape_name(const char *src, tq_db_type_t db_type)
{
	// TODO: escape the escape sequences inside the string

	size_t	src_strlen = strlen(src);
	char	*dst = zbx_malloc(NULL, src_strlen + 2 + 1);
	char	quote_char;

	switch (db_type)
	{
		case TQ_SQL_DB_TYPE_POSTGRESQL:
			quote_char = '"';
			break;
		case TQ_SQL_DB_TYPE_MYSQL:
			quote_char = '`';
			break;
	}

	*dst = quote_char;
	zbx_strlcpy(dst + 1, src, src_strlen + 1);
	*(dst + 1 + src_strlen) = quote_char;
	*(dst + 1 + src_strlen + 1) = '\0';

	return dst;
}

static char	*tq_sql_dyn_get_json_extract(const char *field, const char *path, tq_db_type_t db_type)
{
	char	*str;
	char	*field_esc = tq_sql_dyn_escape_name(field, db_type);
	char	*path_esc = tq_sql_dyn_escape_string(path, db_type);

	switch (db_type)
	{
		case TQ_SQL_DB_TYPE_POSTGRESQL:
			str = zbx_dsprintf(NULL, "(jsonb_path_query_first(%s, %s) #>> '{}')", field_esc, path_esc);
			break;
		case TQ_SQL_DB_TYPE_MYSQL:
			str = zbx_dsprintf(NULL, "UNIMPLEMENTED");
			break;
	}

	zbx_free(field_esc);
	zbx_free(path_esc);

	return str;
}

static char	*tq_sql_dyn_get_columns_to_select(const zbx_tq_query_t *query, tq_db_type_t db_type)
{
	char	*str = NULL;
	size_t	alloc = 0;
	size_t	offset = 0;

	for (int i = 0; i < query->columns.values_num; i++)
	{
		const zbx_tq_column_t	*col = &query->columns.values[i];

		if (NULL == col->key)
		{
			char	*name_esc = tq_sql_dyn_escape_name(col->name, db_type);
			zbx_snprintf_alloc(&str, &alloc, &offset, "%s", name_esc);
			zbx_free(name_esc);
		}
		else
		{
			char	*path = zbx_strdup(NULL, col->key);

			zbx_json_escape(&path);
			path = zbx_dsprintf(path, "$[\"%s\"]", path);

			char	*extract_expr = tq_sql_dyn_get_json_extract(col->name, path, db_type);

			zbx_snprintf_alloc(&str, &alloc, &offset, "%s", extract_expr);

			zbx_free(extract_expr);
			zbx_free(path);
		}

		if (query->columns.values_num - 1 != i)
			zbx_snprintf_alloc(&str, &alloc, &offset, ",");
	}

	if (str == NULL)
		str = zbx_strdup(NULL, "");

	return str;
}

/******************************************************************************
 *                                                                            *
 * Comments: fraction must be a valid string representation of a double       *
 *                                                                            *
 ******************************************************************************/
static char	*tq_sql_dyn_get_percentile(const char *field, const char *fraction, tq_db_type_t db_type)
{
	char	*str;
	char	*field_esc = tq_sql_dyn_escape_name(field, db_type);

	switch (db_type)
	{
		case TQ_SQL_DB_TYPE_POSTGRESQL:
			str = zbx_dsprintf(NULL, "percentile_cont(%s) WITHIN GROUP (ORDER BY %s)", fraction, field_esc);
			break;
		case TQ_SQL_DB_TYPE_MYSQL:
			str = zbx_dsprintf(NULL, "UNIMPLEMENTED");
			break;
	}

	zbx_free(field_esc);

	return str;
}

static char	*tq_sql_dyn_get_aggr_columns_to_select(const zbx_tq_query_t *query, tq_db_type_t db_type)
{
	char	*str = NULL;
	size_t	alloc = 0;
	size_t	offset = 0;

	for (int i = 0; i < query->aggregated_columns.values_num; i++)
	{
		const zbx_tq_aggr_column_t	*aggr_col = &query->aggregated_columns.values[i];
		char				*col_name_esc = NULL;

		if (ZBX_TQ_FUNCTION_COUNT != aggr_col->function && ZBX_TQ_FUNCTION_PERCENTILE != aggr_col->function)
			col_name_esc = tq_sql_dyn_escape_name(aggr_col->column_name, db_type);

		switch (aggr_col->function)
		{
			case ZBX_TQ_FUNCTION_COUNT:
				zbx_snprintf_alloc(&str, &alloc, &offset, "COUNT(*)");
				break;
			case ZBX_TQ_FUNCTION_MIN:
				zbx_snprintf_alloc(&str, &alloc, &offset, "MIN(%s)", col_name_esc);
				break;
			case ZBX_TQ_FUNCTION_MAX:
				zbx_snprintf_alloc(&str, &alloc, &offset, "MAX(%s)", col_name_esc);
				break;
			case ZBX_TQ_FUNCTION_AVG:
				zbx_snprintf_alloc(&str, &alloc, &offset, "AVG(%s)", col_name_esc);
				break;
			case ZBX_TQ_FUNCTION_SUM:
				zbx_snprintf_alloc(&str, &alloc, &offset, "SUM(%s)", col_name_esc);
				break;
			case ZBX_TQ_FUNCTION_PERCENTILE:
			{
				char	*percentile_expr = tq_sql_dyn_get_percentile(aggr_col->column_name,
						aggr_col->args.values[0], db_type);

				zbx_snprintf_alloc(&str, &alloc, &offset, "%s", percentile_expr);

				zbx_free(percentile_expr);
				break;
			}

			case ZBX_TQ_FUNCTION_UNKNOWN:
				THIS_SHOULD_NEVER_HAPPEN;
		}

		if (query->aggregated_columns.values_num - 1 != i)
			zbx_snprintf_alloc(&str, &alloc, &offset, ",");

		zbx_free(col_name_esc);
	}

	if (str == NULL)
	{
		THIS_SHOULD_NEVER_HAPPEN;
		str = zbx_strdup(NULL, "");
	}

	return str;
}

static char	*tq_sql_dyn_get_table_to_select_from(const zbx_tq_query_t *query, tq_db_type_t db_type)
{
	// TODO
	char	*str = NULL;

	str = zbx_strdup(str, "<table_to_select_from>");

	return str;
}

static char	*tq_sql_dyn_get_conditions(const zbx_tq_query_t *query, tq_db_type_t db_type)
{
	// TODO
	char	*str = NULL;

	str = zbx_strdup(str, "<conditions>");

	if (str == NULL)
		str = zbx_strdup(NULL, "");

	return str;
}

/* TODO: move to another file? */
static void	tq_sql_get_timestamp_filter_bounds(const zbx_tq_query_t *query, time_t *out_lower, time_t *out_upper)
{
	// TODO
	*out_lower = 42;
	*out_upper = 1042;
}

void	zbx_tq_sql_generate_postgresql(const zbx_tq_query_t *query, char **sql)
{
	const int	query_has_columns = (0 != query->columns.values_num);
	const int	query_has_conditions = (0 != query->conditions.values_num);

	size_t	alloc = 0;
	size_t	offset = 0;

	*sql = NULL;

	char	*columns_to_select 	= tq_sql_dyn_get_columns_to_select(query, TQ_SQL_DB_TYPE_POSTGRESQL);
	char	*aggr_columns_to_select = tq_sql_dyn_get_aggr_columns_to_select(query, TQ_SQL_DB_TYPE_POSTGRESQL);
	char	*table_to_select_from 	= tq_sql_dyn_get_table_to_select_from(query, TQ_SQL_DB_TYPE_POSTGRESQL);
	char	*conditions 		= tq_sql_dyn_get_conditions(query, TQ_SQL_DB_TYPE_POSTGRESQL);

	time_t timestamp_filter_lower_bound, timestamp_filter_upper_bound;
	tq_sql_get_timestamp_filter_bounds(query, &timestamp_filter_lower_bound, &timestamp_filter_upper_bound);

	/* select */
	zbx_snprintf_alloc(sql, &alloc, &offset, "SELECT ");
	zbx_snprintf_alloc(sql, &alloc, &offset,"row_number() OVER (ORDER BY rounded_time%s%s) AS row_id,",
			(query_has_columns ? "," : ""), columns_to_select);
	zbx_snprintf_alloc(sql, &alloc, &offset,
			"to_timestamp("
			"FLOOR((EXTRACT(EPOCH FROM \"Timestamp\")+%d)/%d)*%d-%d"
			") AS rounded_time,",
			query->time_shift, query->aggregation_size, query->aggregation_size, query->time_shift);
	zbx_snprintf_alloc(sql, &alloc, &offset, "MIN(\"Timestamp\") AS starttime,");
	if (query_has_columns)
		zbx_snprintf_alloc(sql, &alloc, &offset, "%s,", columns_to_select);
	zbx_snprintf_alloc(sql, &alloc, &offset, "%s ", aggr_columns_to_select);

	/* from */
	zbx_snprintf_alloc(sql, &alloc, &offset, "FROM %s ", table_to_select_from);

	/* where */
	zbx_snprintf_alloc(sql, &alloc, &offset, "WHERE ");
	zbx_snprintf_alloc(sql, &alloc, &offset,
			"\"Timestamp\">to_timestamp(" ZBX_FS_TIME_T ") "
			"AND \"Timestamp\"<=to_timestamp(" ZBX_FS_TIME_T ") ",
			timestamp_filter_lower_bound, timestamp_filter_upper_bound);
	if (query_has_conditions)
		zbx_snprintf_alloc(sql, &alloc, &offset, "AND (%s) ", conditions);

	/* group by */
	zbx_snprintf_alloc(sql, &alloc, &offset, "GROUP BY rounded_time%s%s ",
			(query_has_columns ? "," : ""), columns_to_select);

	/* order by */
	zbx_snprintf_alloc(sql, &alloc, &offset, "ORDER BY rounded_time%s%s;",
			(query_has_columns ? "," : ""), columns_to_select);

	zbx_free(columns_to_select);
	zbx_free(aggr_columns_to_select);
	zbx_free(table_to_select_from);
	zbx_free(conditions);
}
