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

#include "telemetry.h"
#include "zbxcommon.h"
#include "zbxdb.h"
#include "zbxjson.h"
#include "zbxstr.h"
#include "zbxtelemetry.h"

ZBX_PTR_VECTOR_DECL(tq_condition_ptr, zbx_tq_condition_t *)
ZBX_PTR_VECTOR_IMPL(tq_condition_ptr, zbx_tq_condition_t *)

typedef enum tq_db_type
{
	TQ_SQL_DB_TYPE_POSTGRESQL = 0,
	TQ_SQL_DB_TYPE_MYSQL
}
tq_db_type_t;

/******************************************************************************
 *                                                                            *
 * Return value: escaped and quoted string to be used as a string literal     *
 *                                                                            *
 ******************************************************************************/
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

/******************************************************************************
 *                                                                            *
 * Return value: escaped and quoted string to be used as column or table name *
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

/******************************************************************************
 *                                                                            *
 * Return value: escaped and UNQUOTED string to use in a LIKE pattern         *
 *                                                                            *
 ******************************************************************************/
static char	*tq_sql_dyn_escape_like_pattern(const char *src, tq_db_type_t db_type)
{
	/* FIXME: placeholder */
	return zbx_db_dyn_escape_like_pattern(src);
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

/******************************************************************************
 *                                                                            *
 *  Return value: escaped and quoted table name                               *
 *                                                                            *
 ******************************************************************************/
static char	*tq_sql_dyn_get_table_to_select_from(const zbx_tq_query_t *query, tq_db_type_t db_type)
{
	/* TODO: replace with actual table names (or probably macros) */

	char	*str;
	char	*str_esc;

	switch (query->category)
	{
		case ZBX_TQ_CATEGORY_APM_TRACES:
			str = zbx_strdup(NULL, "apm_traces");
			break;

		case ZBX_TQ_CATEGORY_APM_METRICS:
			switch (query->metric_type)
			{
				case ZBX_TQ_METRIC_TYPE_SUM:
					str = zbx_strdup(NULL, "apm_metrics_sum");
					break;
				case ZBX_TQ_METRIC_TYPE_GAUGE:
					str = zbx_strdup(NULL, "apm_metrics_gauge");
					break;
				case ZBX_TQ_METRIC_TYPE_HISTOGRAM:
					str = zbx_strdup(NULL, "apm_metrics_histogram");
					break;
				case ZBX_TQ_METRIC_TYPE_EXPONENTIAL_HISTOGRAM:
					str = zbx_strdup(NULL, "apm_metrics_exponentialhistogram");
					break;

				case ZBX_TQ_CATEGORY_UNKNOWN:
					THIS_SHOULD_NEVER_HAPPEN;
					str = zbx_strdup(NULL, "");
			}
			break;

		case ZBX_TQ_CATEGORY_APM_LOGS:
			str = zbx_strdup(NULL, "apm_logs");
			break;

		case ZBX_TQ_CATEGORY_UNKNOWN:
			THIS_SHOULD_NEVER_HAPPEN;
			str = zbx_strdup(NULL, "");
	}

	str_esc = tq_sql_dyn_escape_name(str, db_type);

	zbx_free(str);

	return str_esc;
}

static char	*tq_sql_dyn_get_condition_operand(const zbx_tq_condition_t *cond, tq_db_type_t db_type)
{
	if (NULL == cond->json_path)
		return tq_sql_dyn_escape_name(cond->column_name, db_type);

	return tq_sql_dyn_get_json_extract(cond->column_name, cond->json_path, db_type);
}

static char	*tq_sql_dyn_get_condition_contains(const zbx_tq_condition_t *cond, tq_db_type_t db_type)
{
	char	*str;
	char	*operand = tq_sql_dyn_get_condition_operand(cond, db_type);
	char	*value_esc = tq_sql_dyn_escape_like_pattern(cond->value, db_type);

	switch (db_type)
	{
		case TQ_SQL_DB_TYPE_POSTGRESQL:
			str = zbx_dsprintf(NULL, "%s LIKE '%%%s%%'", operand, value_esc);
			break;
		case TQ_SQL_DB_TYPE_MYSQL:
			str = zbx_dsprintf(NULL, "UNIMPLEMENTED");
			break;
	}

	zbx_free(operand);
	zbx_free(value_esc);

	return str;
}

static char	*tq_sql_dyn_get_condition(const zbx_tq_condition_t *cond, tq_db_type_t db_type)
{
	if (ZBX_TQ_OPERATOR_UNKNOWN == cond->operator)
	{
		THIS_SHOULD_NEVER_HAPPEN;
		return zbx_strdup(NULL, "");
	}

	if (ZBX_TQ_OPERATOR_EQUAL == cond->operator || ZBX_TQ_OPERATOR_NOT_EQUAL == cond->operator)
	{
		char	*str;
		char	*operand = tq_sql_dyn_get_condition_operand(cond, db_type);
		char	*value_esc = tq_sql_dyn_escape_string(cond->value, db_type);

		if (ZBX_TQ_OPERATOR_EQUAL == cond->operator)
			str = zbx_dsprintf(NULL, "%s = %s", operand, value_esc);
		else
			str = zbx_dsprintf(NULL, "%s <> %s", operand, value_esc);

		zbx_free(operand);
		zbx_free(value_esc);

		return	str;
	}

	if (ZBX_TQ_OPERATOR_CONTAINS == cond->operator)
		return tq_sql_dyn_get_condition_contains(cond, db_type);
	else
	{
		char	*contains_str = tq_sql_dyn_get_condition_contains(cond, db_type);
		char	*str = zbx_dsprintf(NULL, "(NOT %s)", contains_str);

		zbx_free(contains_str);

		return str;
	}
}

static char	*tq_sql_dyn_get_conditions_simple(const zbx_tq_query_t *query, tq_db_type_t db_type)
{
	char	*str = NULL;
	size_t	alloc = 0;
	size_t	offset = 0;

	for (int i = 0; i < query->conditions.values_num; i++)
	{
		const zbx_tq_condition_t	*cond = &query->conditions.values[i];

		char	*cond_str = tq_sql_dyn_get_condition(cond, db_type);

		zbx_snprintf_alloc(&str, &alloc, &offset, "%s", cond_str);

		if (query->conditions.values_num - 1 != i)
			zbx_snprintf_alloc(&str, &alloc, &offset, " %s ",
					(query->evaltype == ZBX_TQ_EVAL_TYPE_AND ? "AND" : "OR"));

		zbx_free(cond_str);
	}

	if (str == NULL)
	{
		THIS_SHOULD_NEVER_HAPPEN;
		str = zbx_strdup(NULL, "");
	}

	return str;
}

static int	tq_condition_ptr_compare_by_column_and_path(const void *a, const void *b)
{
	const zbx_tq_condition_t	*cond_a = *(const zbx_tq_condition_t * const *)a;
	const zbx_tq_condition_t	*cond_b = *(const zbx_tq_condition_t * const *)b;

	int	column_name_cmp_res = strcmp(cond_a->column_name, cond_b->column_name);

	if (0 != column_name_cmp_res)
		return column_name_cmp_res;

	if (NULL == cond_a->json_path || NULL == cond_b->json_path)
	{
		if (cond_a->json_path != cond_b->json_path)
			THIS_SHOULD_NEVER_HAPPEN;

		return column_name_cmp_res;
	}

	return strcmp(cond_a->json_path, cond_b->json_path);
}

static char	*tq_sql_dyn_get_conditions_and_or(const zbx_tq_query_t *query, tq_db_type_t db_type)
{
	char				*str = NULL;
	size_t				alloc = 0;
	size_t				offset = 0;
	zbx_vector_tq_condition_ptr_t	conditions_sorted;

	if (0 == query->conditions.values_num)
	{
		THIS_SHOULD_NEVER_HAPPEN;
		return zbx_strdup(NULL, "");
	}

	zbx_vector_tq_condition_ptr_create(&conditions_sorted);

	for (int i = 0; i < query->conditions.values_num; i++)
		zbx_vector_tq_condition_ptr_append(&conditions_sorted, &query->conditions.values[i]);

	zbx_vector_tq_condition_ptr_sort(&conditions_sorted, tq_condition_ptr_compare_by_column_and_path);

	zbx_snprintf_alloc(&str, &alloc, &offset, "(");

	for (int i = 0; i < conditions_sorted.values_num; i++)
	{
		const zbx_tq_condition_t	*cond = conditions_sorted.values[i];
		char				*cond_str = tq_sql_dyn_get_condition(cond, db_type);

		zbx_snprintf_alloc(&str, &alloc, &offset, "%s", cond_str);

		if (conditions_sorted.values_num - 1 == i)
			zbx_snprintf_alloc(&str, &alloc, &offset, ")");
		else if (0 != strcmp(cond->column_name, conditions_sorted.values[i + 1]->column_name))
			zbx_snprintf_alloc(&str, &alloc, &offset, ")AND(");
		else
			zbx_snprintf_alloc(&str, &alloc, &offset, " OR ");

		zbx_free(cond_str);
	}

	zbx_vector_tq_condition_ptr_destroy(&conditions_sorted);

	return str;
}

static char	*tq_sql_dyn_get_conditions_expression(const zbx_tq_query_t *query, tq_db_type_t db_type)
{
	char		*str = NULL;
	size_t		alloc = 0;
	size_t		offset = 0;
	const char	*p = query->formula;

	while ('\0' != *p)
	{
		if (' ' == *p)
		{
			if (query->formula == p || ' ' != *(p - 1))
				zbx_strcpy_alloc(&str, &alloc, &offset, " ");
			p++;
			continue;
		}

		if ('(' == *p || ')' == *p)
		{
			zbx_strncpy_alloc(&str, &alloc, &offset, p, 1);
			p++;
			continue;
		}

		if (islower(*p))
		{
			if (0 == strncmp(p, "and", ZBX_CONST_STRLEN("and")))
			{
				zbx_strcpy_alloc(&str, &alloc, &offset, "AND");
				p += ZBX_CONST_STRLEN("and");
			}
			else if (0 == strncmp(p, "or", ZBX_CONST_STRLEN("or")))
			{
				zbx_strcpy_alloc(&str, &alloc, &offset, "OR");
				p += ZBX_CONST_STRLEN("or");
			}
			else if (0 == strncmp(p, "not", ZBX_CONST_STRLEN("not")))
			{
				zbx_strcpy_alloc(&str, &alloc, &offset, "NOT");
				p += ZBX_CONST_STRLEN("not");
			}
			else
			{
				THIS_SHOULD_NEVER_HAPPEN;
				zbx_free(str);
				alloc = 0;
				offset = 0;
				break;
			}
			continue;
		}

		if (!isupper(*p))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			zbx_free(str);
			alloc = 0;
			offset = 0;
			break;
		}

		int	len = 1;

		while (isupper(p[len]))
			len++;

		int	cond_idx = tq_formula_constant_to_condition_idx(p, len);
		char	*cond_str = tq_sql_dyn_get_condition(&query->conditions.values[cond_idx], db_type);

		zbx_snprintf_alloc(&str, &alloc, &offset, "%s", cond_str);

		zbx_free(cond_str);

		p += len;
	}

	if (str == NULL)
	{
		THIS_SHOULD_NEVER_HAPPEN;
		str = zbx_strdup(NULL, "");
	}

	return str;
}

static char	*tq_sql_dyn_get_conditions(const zbx_tq_query_t *query, tq_db_type_t db_type)
{
	if (0 == query->conditions.values_num)
		return zbx_strdup(NULL, "");

	switch (query->evaltype)
	{
		case ZBX_TQ_EVAL_TYPE_AND:
		case ZBX_TQ_EVAL_TYPE_OR:
			return tq_sql_dyn_get_conditions_simple(query, db_type);

		case ZBX_TQ_EVAL_TYPE_AND_OR:
			return tq_sql_dyn_get_conditions_and_or(query, db_type);

		case ZBX_TQ_EVAL_TYPE_EXPRESSION:
			return tq_sql_dyn_get_conditions_expression(query, db_type);

		case ZBX_TQ_EVAL_TYPE_UNKNOWN:
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			return zbx_strdup(NULL, "");
	}
}

/* TODO: move to another file? */
static void	tq_sql_get_timestamp_filter_bounds(const zbx_tq_query_t *query, int update_interval, time_t now,
		time_t lasttimestamp, time_t *out_lower, time_t *out_upper)
{
	time_t	now_shifted = now - query->time_shift;

	if (query->aggregation_size > update_interval || update_interval > query->loopback_limit)
	{
		*out_lower = now_shifted - query->aggregation_size;
		*out_upper = now_shifted;
	}
	else /* query->aggregation_size <= update_interval || update_interval <= query->loopback_limit */
	{
		time_t	start = start = MAX(lasttimestamp, now_shifted - query->loopback_limit);

		*out_lower = start;
		*out_upper = ((now_shifted - start) / query->aggregation_size) * query->aggregation_size;
	}
}

void	zbx_tq_sql_generate_postgresql(const zbx_tq_query_t *query, int update_interval, time_t now,
		time_t lasttimestamp, char **sql)
{
	const int	query_has_columns = (0 != query->columns.values_num);
	const int	query_has_conditions = (0 != query->conditions.values_num);

	size_t	alloc = 0;
	size_t	offset = 0;

	*sql = NULL;

	char	*columns_to_select	= tq_sql_dyn_get_columns_to_select(query, TQ_SQL_DB_TYPE_POSTGRESQL);
	char	*aggr_columns_to_select	= tq_sql_dyn_get_aggr_columns_to_select(query, TQ_SQL_DB_TYPE_POSTGRESQL);
	char	*table_to_select_from	= tq_sql_dyn_get_table_to_select_from(query, TQ_SQL_DB_TYPE_POSTGRESQL);
	char	*conditions		= tq_sql_dyn_get_conditions(query, TQ_SQL_DB_TYPE_POSTGRESQL);

	time_t timestamp_filter_lower_bound, timestamp_filter_upper_bound;
	tq_sql_get_timestamp_filter_bounds(query, update_interval, now, lasttimestamp, &timestamp_filter_lower_bound,
			&timestamp_filter_upper_bound);

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
