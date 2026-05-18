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
#include "zbxstr.h"
#include "zbxtelemetry.h"

/* because clickhouse does not support JSON columns to be arrays on top level, the array is located in a subcolumn */
/* TODO: decide if this is the way to go, maybe just have the field be of Array(...) type (at least for clickhouse) */
#define TQ_SQL_ATTRIBUTES_ARRAY_JSON_KEY "attributes"

static int	tq_sql_is_escape_sequence_clickhoouse(char c)
{
	if ('\'' == c || '\\' == c || '"' == c)
		return SUCCEED;
	return FAIL;
}

static size_t	tq_sql_dyn_escape_string_unquoted_size_clickhouse(const char *s)
{
	size_t	csize, len = 1;

	if (NULL == s)
		return len;

	while ('\0' != *s)
	{
		csize = zbx_utf8_char_len(s);

		/* process non-UTF-8 characters as single byte characters */
		if (0 == csize)
			csize = 1;

		if (SUCCEED == tq_sql_is_escape_sequence_clickhoouse(*s))
			len++;

		s += csize;
		len += csize;
	}

	return len;
}

static char	*tq_sql_dyn_escape_string_unquoted_clickhouse(const char *src)
{
	size_t		len = tq_sql_dyn_escape_string_unquoted_size_clickhouse(src);
	char		*dst = zbx_malloc(NULL, len);
	const char	*s;
	char		*d;

	for (s = src, d = dst; NULL != s && '\0' != *s; s++)
	{
		if (SUCCEED == tq_sql_is_escape_sequence_clickhoouse(*s))
			*d++ = '\\';

		*d++ = *s;
	}
	*d = '\0';

	return dst;
}

static char	*tq_sql_dyn_escape_string_unquoted(const char *src, zbx_tq_db_type_t db_type)
{
	if (ZBX_TQ_DB_TYPE_CLICKHOUSE == db_type)
		return tq_sql_dyn_escape_string_unquoted_clickhouse(src);

	return zbx_db_dyn_escape_string(src);
}

/******************************************************************************
 *                                                                            *
 * Return value: escaped and quoted string to be used as a string literal     *
 *                                                                            *
 ******************************************************************************/
static char	*tq_sql_dyn_escape_string(const char *src, zbx_tq_db_type_t db_type)
{
	char	*src_esc = tq_sql_dyn_escape_string_unquoted(src, db_type);
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
static char	*tq_sql_dyn_escape_name(const char *src, zbx_tq_db_type_t db_type)
{
	char	*src_esc = tq_sql_dyn_escape_string_unquoted(src, db_type);
	size_t	src_esc_strlen = strlen(src_esc);
	char	*dst = zbx_malloc(NULL, src_esc_strlen + 2 + 1);
	char	quote_char;

	switch (db_type)
	{
		case ZBX_TQ_DB_TYPE_POSTGRESQL:
		case ZBX_TQ_DB_TYPE_CLICKHOUSE:
			quote_char = '"';
			break;

		case ZBX_TQ_DB_TYPE_MYSQL:
			quote_char = '`';
			break;

		default:
			THIS_SHOULD_NEVER_HAPPEN;
			quote_char = '\0';
	}

	*dst = quote_char;
	zbx_strlcpy(dst + 1, src_esc, src_esc_strlen + 1);
	*(dst + 1 + src_esc_strlen) = quote_char;
	*(dst + 1 + src_esc_strlen + 1) = '\0';

	zbx_free(src_esc);

	return dst;
}

/******************************************************************************
 *                                                                            *
 * Return value: escaped and UNQUOTED string to use in a LIKE pattern         *
 *                                                                            *
 ******************************************************************************/
static char	*tq_sql_dyn_escape_like_pattern(const char *src, zbx_tq_db_type_t db_type)
{
	if (ZBX_TQ_DB_TYPE_POSTGRESQL == db_type || ZBX_TQ_DB_TYPE_MYSQL == db_type)
		return zbx_db_dyn_escape_like_pattern(src);

	char	*tmp = tq_sql_dyn_escape_string_unquoted(src, db_type);
	size_t	len = strlen(tmp) + 1;

	for (const char *p = tmp; '\0' != *p; p++)
	{
		if ('_' == *p || '%' == *p)
			len++;
	}

	char	*dst = zbx_malloc(NULL, len);
	char	*d = dst;

	for (const char	*p = tmp; '\0' != *p; p++)
	{
		if ('_' == *p || '%' == *p)
			*d++ = '\\';

		*d++ = *p;
	}
	*d = '\0';

	zbx_free(tmp);

	return dst;
}

/******************************************************************************
 *                                                                            *
 * Comments: does not escape operand! If escaping is needed, it must be done  *
 *           by the caller, before passing it to this function.               *
 *                                                                            *
 ******************************************************************************/
static char	*tq_sql_dyn_get_json_subcolumn_raw(const char *operand, const char *key, zbx_tq_db_type_t db_type)
{
	char	*str;

	switch (db_type)
	{
		case ZBX_TQ_DB_TYPE_POSTGRESQL:
			/* TODO */
			str = zbx_dsprintf(NULL, "UNIMPLEMENTED");
			break;
		case ZBX_TQ_DB_TYPE_MYSQL:
			str = zbx_dsprintf(NULL, "UNIMPLEMENTED");
			break;
		case ZBX_TQ_DB_TYPE_CLICKHOUSE:
		{
			char	*key_esc_unquoted = tq_sql_dyn_escape_string_unquoted(key, db_type);

			/* casting to string so that it can be used in group by */
			str = zbx_dsprintf(NULL, "%s.\"%s\".:String", operand, key_esc_unquoted);

			zbx_free(key_esc_unquoted);
			break;
		}

		default:
			THIS_SHOULD_NEVER_HAPPEN;
			str = zbx_strdup(NULL, "");
	}

	return str;
}

/******************************************************************************
 *                                                                            *
 * Comments: does not escape x! If escaping is needed, it must be done        *
 *           by the caller, before passing it to this function.               *
 *                                                                            *
 ******************************************************************************/
static char	*tq_sql_dyn_get_operand_raw(const char *x, const char *key, zbx_tq_db_type_t db_type)
{
	if (NULL == key)
		return zbx_strdup(NULL, x);

	return tq_sql_dyn_get_json_subcolumn_raw(x, key, db_type);
}

static char	*tq_sql_dyn_get_columns_to_select(const zbx_tq_query_t *query, zbx_tq_db_type_t db_type)
{
	char	*str = NULL;
	size_t	alloc = 0;
	size_t	offset = 0;

	for (int i = 0; i < query->columns.values_num; i++)
	{
		const zbx_tq_column_t	*col = &query->columns.values[i];
		char			*name_esc = tq_sql_dyn_escape_name(col->name, db_type);
		char			*col_to_select = tq_sql_dyn_get_operand_raw(name_esc, col->key, db_type);

		zbx_snprintf_alloc(&str, &alloc, &offset, "%s", col_to_select);

		zbx_free(name_esc);
		zbx_free(col_to_select);

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
static char	*tq_sql_dyn_get_percentile(const char *field, const char *fraction, zbx_tq_db_type_t db_type)
{
	char	*str;
	char	*field_esc = tq_sql_dyn_escape_name(field, db_type);

	switch (db_type)
	{
		case ZBX_TQ_DB_TYPE_POSTGRESQL:
			str = zbx_dsprintf(NULL, "percentile_cont(%s) WITHIN GROUP (ORDER BY %s)", fraction, field_esc);
			break;
		case ZBX_TQ_DB_TYPE_MYSQL:
			str = zbx_dsprintf(NULL, "UNIMPLEMENTED");
			break;
		case ZBX_TQ_DB_TYPE_CLICKHOUSE:
			str = zbx_dsprintf(NULL, "quantile(%s)(%s)", fraction, field_esc);
			break;

		default:
			THIS_SHOULD_NEVER_HAPPEN;
			str = zbx_strdup(NULL, "");
	}

	zbx_free(field_esc);

	return str;
}

static char	*tq_sql_dyn_get_aggr_columns_to_select(const zbx_tq_query_t *query, zbx_tq_db_type_t db_type)
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
static char	*tq_sql_dyn_get_table_to_select_from(const zbx_tq_query_t *query, zbx_tq_db_type_t db_type)
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

static char	*tq_sql_dyn_get_condition_contains(const char *operand, const char *value, zbx_tq_db_type_t db_type)
{
	char	*str;
	char	*value_esc = tq_sql_dyn_escape_like_pattern(value, db_type);

	switch (db_type)
	{
		case ZBX_TQ_DB_TYPE_POSTGRESQL:
			str = zbx_dsprintf(NULL, "%s LIKE '%%%s%%' ESCAPE '%c'", operand, value_esc,
					ZBX_SQL_LIKE_ESCAPE_CHAR);
			break;
		case ZBX_TQ_DB_TYPE_MYSQL:
			str = zbx_dsprintf(NULL, "UNIMPLEMENTED");
			break;
		case ZBX_TQ_DB_TYPE_CLICKHOUSE:
			str = zbx_dsprintf(NULL, "%s LIKE '%%%s%%'", operand, value_esc);
			break;

		default:
			THIS_SHOULD_NEVER_HAPPEN;
			str = zbx_strdup(NULL, "");
	}

	zbx_free(value_esc);

	return str;
}

static char	*tq_sql_dyn_get_condition_exists(const char *atom, const char *key, zbx_tq_db_type_t db_type)
{
	char	*str;

	switch (db_type)
	{
		case ZBX_TQ_DB_TYPE_POSTGRESQL:
			/* TODO */
			str = zbx_dsprintf(NULL, "UNIMPLEMENTED");
			break;
		case ZBX_TQ_DB_TYPE_MYSQL:
			str = zbx_dsprintf(NULL, "UNIMPLEMENTED");
			break;
		case ZBX_TQ_DB_TYPE_CLICKHOUSE:
		{
			char	*key_esc_unquoted = tq_sql_dyn_escape_string_unquoted(key, db_type);

			str = zbx_dsprintf(NULL, "isNotNull(%s.\"%s\")", atom, key_esc_unquoted);

			zbx_free(key_esc_unquoted);
			break;
		}

		default:
			THIS_SHOULD_NEVER_HAPPEN;
			str = zbx_strdup(NULL, "");
	}

	return str;
}

static char	*tq_sql_dyn_get_atom_condition(const char *atom, const char *key, const char *value,
		zbx_tq_operator_t operator, zbx_tq_db_type_t db_type)
{
	if (ZBX_TQ_OPERATOR_UNKNOWN == operator)
	{
		THIS_SHOULD_NEVER_HAPPEN;
		return zbx_strdup(NULL, "");
	}

	if (ZBX_TQ_OPERATOR_EQUAL == operator || ZBX_TQ_OPERATOR_NOT_EQUAL == operator)
	{
		char	*str;
		char	*operand = tq_sql_dyn_get_operand_raw(atom, key, db_type);
		char	*value_esc = tq_sql_dyn_escape_string(value, db_type);

		if (ZBX_TQ_OPERATOR_EQUAL == operator)
			str = zbx_dsprintf(NULL, "(%s IS NOT NULL AND %s = %s)", operand, operand, value_esc);
		else
			str = zbx_dsprintf(NULL, "(%s IS NULL OR %s <> %s)", operand, operand, value_esc);

		zbx_free(value_esc);
		zbx_free(operand);

		return	str;
	}
	else if (ZBX_TQ_OPERATOR_CONTAINS == operator || ZBX_TQ_OPERATOR_NOT_CONTAINS == operator)
	{
		char	*operand = tq_sql_dyn_get_operand_raw(atom, key, db_type);
		char	*str = tq_sql_dyn_get_condition_contains(operand, value, db_type);

		if (ZBX_TQ_OPERATOR_NOT_CONTAINS == operator)
			str = zbx_dsprintf(str, "(NOT %s)", str);

		zbx_free(operand);

		return str;
	}
	else /* exists */
	{
		return tq_sql_dyn_get_condition_exists(atom, key, db_type);
	}
}

static char	*tq_sql_dyn_get_array_condition(const zbx_tq_condition_t *cond, tq_column_type_t col_type,
		zbx_tq_db_type_t db_type)
{
	char	*str;

	if (ZBX_TQ_DB_TYPE_POSTGRESQL == db_type)
	{
		/* TODO */
		str = zbx_strdup(NULL, "UNIMPLEMENTED");
	}
	else if (ZBX_TQ_DB_TYPE_MYSQL == db_type)
	{
		str = zbx_strdup(NULL, "UNIMPLEMENTED");
	}
	else /* clickhouse */
	{
		char	*elem_cond = tq_sql_dyn_get_atom_condition("x", cond->key, cond->value, cond->operator,
				db_type);
		char	*col_esc = tq_sql_dyn_escape_name(cond->column_name, db_type);

		str = zbx_dsprintf(NULL, "arrayExists(x -> %s, %s." TQ_SQL_ATTRIBUTES_ARRAY_JSON_KEY ".:\"Array(%s)\")",
				elem_cond, col_esc, TQ_COLUMN_TYPE_ARRAY_ATTRIBUTES == col_type ? "JSON" : "String");

		zbx_free(elem_cond);
		zbx_free(col_esc);
	}

	return str;
}

static char	*tq_sql_dyn_get_condition(const zbx_tq_condition_t *cond, zbx_tq_category_t category,
		zbx_tq_metric_type_t metric_type, zbx_tq_db_type_t db_type)
{
	tq_column_type_t	col_type = tq_get_column_type(category, metric_type, cond->column_name);

	if (SUCCEED == tq_column_type_is_arr(col_type))
		return tq_sql_dyn_get_array_condition(cond, col_type, db_type);
	else
	{
		char	*name_esc = tq_sql_dyn_escape_name(cond->column_name, db_type);
		char	*str = tq_sql_dyn_get_atom_condition(name_esc, cond->key, cond->value, cond->operator, db_type);

		zbx_free(name_esc);

		return str;
	}
}

static char	*tq_sql_dyn_get_conditions_simple(const zbx_tq_query_t *query, zbx_tq_db_type_t db_type)
{
	char	*str = NULL;
	size_t	alloc = 0;
	size_t	offset = 0;

	for (int i = 0; i < query->conditions.values_num; i++)
	{
		const zbx_tq_condition_t	*cond = &query->conditions.values[i];

		char	*cond_str = tq_sql_dyn_get_condition(cond, query->category, query->metric_type, db_type);

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

static char	*tq_sql_dyn_get_conditions_and_or(const zbx_tq_query_t *query, zbx_tq_db_type_t db_type)
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

	tq_get_conditions_and_or_sorted(query, &conditions_sorted);

	zbx_snprintf_alloc(&str, &alloc, &offset, "(");

	for (int i = 0; i < conditions_sorted.values_num; i++)
	{
		const zbx_tq_condition_t	*cond = conditions_sorted.values[i];
		char				*cond_str = tq_sql_dyn_get_condition(cond, query->category,
				query->metric_type, db_type);

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

static char	*tq_sql_dyn_get_conditions_expression(const zbx_tq_query_t *query, zbx_tq_db_type_t db_type)
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

		if (islower((unsigned char)*p))
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

		if (!isupper((unsigned char)*p))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			zbx_free(str);
			alloc = 0;
			offset = 0;
			break;
		}

		int	len = 1;

		while (isupper((unsigned char)p[len]))
			len++;

		int	cond_idx = tq_formula_constant_to_condition_idx(p, len);
		char	*cond_str = tq_sql_dyn_get_condition(&query->conditions.values[cond_idx], query->category,
				query->metric_type, db_type);

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

static char	*tq_sql_dyn_get_conditions(const zbx_tq_query_t *query, zbx_tq_db_type_t db_type)
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

void	zbx_tq_sql_generate_postgresql(const zbx_tq_query_t *query, time_t now, time_t lasttimestamp, char **sql)
{
	const int	query_has_columns = (0 != query->columns.values_num);
	const int	query_has_conditions = (0 != query->conditions.values_num);

	size_t	alloc = 0;
	size_t	offset = 0;

	*sql = NULL;

	char	*columns_to_select	= tq_sql_dyn_get_columns_to_select(query, ZBX_TQ_DB_TYPE_POSTGRESQL);
	char	*aggr_columns_to_select	= tq_sql_dyn_get_aggr_columns_to_select(query, ZBX_TQ_DB_TYPE_POSTGRESQL);
	char	*table_to_select_from	= tq_sql_dyn_get_table_to_select_from(query, ZBX_TQ_DB_TYPE_POSTGRESQL);
	char	*conditions		= tq_sql_dyn_get_conditions(query, ZBX_TQ_DB_TYPE_POSTGRESQL);

	time_t timestamp_filter_lower_bound, timestamp_filter_upper_bound;
	zbx_tq_get_timestamp_filter_bounds(query, now, lasttimestamp, &timestamp_filter_lower_bound,
		&timestamp_filter_upper_bound);

	/* select */
	zbx_snprintf_alloc(sql, &alloc, &offset, "SELECT ");
	zbx_snprintf_alloc(sql, &alloc, &offset,
			"to_timestamp("
			"FLOOR((EXTRACT(EPOCH FROM \"Timestamp\")-" ZBX_FS_TIME_T ")/%d)*%d+" ZBX_FS_TIME_T") "
			"AS rounded_time,",
			timestamp_filter_lower_bound, query->aggregation_size, query->aggregation_size,
			timestamp_filter_lower_bound);
	zbx_snprintf_alloc(sql, &alloc, &offset, "EXTRACT(EPOCH FROM MIN(\"Timestamp\"))::bigint AS starttime,");
	if (query_has_columns)
		zbx_snprintf_alloc(sql, &alloc, &offset, "%s,", columns_to_select);
	zbx_snprintf_alloc(sql, &alloc, &offset, "%s ", aggr_columns_to_select);

	/* from */
	zbx_snprintf_alloc(sql, &alloc, &offset, "FROM %s ", table_to_select_from);

	/* where */
	zbx_snprintf_alloc(sql, &alloc, &offset, "WHERE ");
	zbx_snprintf_alloc(sql, &alloc, &offset,
			"\"Timestamp\">=to_timestamp(" ZBX_FS_TIME_T ") "
			"AND \"Timestamp\"<to_timestamp(" ZBX_FS_TIME_T ") ",
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

void	zbx_tq_sql_generate_clickhouse(const zbx_tq_query_t *query, time_t now, time_t lasttimestamp, char **sql)
{
	const int	query_has_columns = (0 != query->columns.values_num);
	const int	query_has_conditions = (0 != query->conditions.values_num);

	size_t	alloc = 0;
	size_t	offset = 0;

	*sql = NULL;

	char	*columns_to_select	= tq_sql_dyn_get_columns_to_select(query, ZBX_TQ_DB_TYPE_CLICKHOUSE);
	char	*aggr_columns_to_select	= tq_sql_dyn_get_aggr_columns_to_select(query, ZBX_TQ_DB_TYPE_CLICKHOUSE);
	char	*table_to_select_from	= tq_sql_dyn_get_table_to_select_from(query, ZBX_TQ_DB_TYPE_CLICKHOUSE);
	char	*conditions		= tq_sql_dyn_get_conditions(query, ZBX_TQ_DB_TYPE_CLICKHOUSE);

	time_t timestamp_filter_lower_bound, timestamp_filter_upper_bound;
	zbx_tq_get_timestamp_filter_bounds(query, now, lasttimestamp, &timestamp_filter_lower_bound,
			&timestamp_filter_upper_bound);

	/* select */
	zbx_snprintf_alloc(sql, &alloc, &offset, "SELECT ");
	zbx_snprintf_alloc(sql, &alloc, &offset,
			"toStartOfInterval ("
			"\"Timestamp\" - INTERVAL " ZBX_FS_TIME_T " SECOND, INTERVAL %d SECOND"
			") + INTERVAL " ZBX_FS_TIME_T " SECOND AS rounded_time,",
			timestamp_filter_lower_bound, query->aggregation_size, timestamp_filter_lower_bound);
	zbx_snprintf_alloc(sql, &alloc, &offset, "toUnixTimestamp(MIN(\"Timestamp\")) AS starttime,");
	if (query_has_columns)
		zbx_snprintf_alloc(sql, &alloc, &offset, "%s,", columns_to_select);
	zbx_snprintf_alloc(sql, &alloc, &offset, "%s ", aggr_columns_to_select);

	/* from */
	zbx_snprintf_alloc(sql, &alloc, &offset, "FROM %s ", table_to_select_from);

	/* where */
	zbx_snprintf_alloc(sql, &alloc, &offset, "WHERE ");
	zbx_snprintf_alloc(sql, &alloc, &offset,
			"\"Timestamp\">=toDateTime(" ZBX_FS_TIME_T ") "
			"AND \"Timestamp\"<toDateTime(" ZBX_FS_TIME_T ") ",
			timestamp_filter_lower_bound, timestamp_filter_upper_bound);
	if (query_has_conditions)
		zbx_snprintf_alloc(sql, &alloc, &offset, "AND (%s) ", conditions);

	/* group by */
	zbx_snprintf_alloc(sql, &alloc, &offset, "GROUP BY rounded_time%s%s ",
			(query_has_columns ? "," : ""), columns_to_select);

	/* order by */
	zbx_snprintf_alloc(sql, &alloc, &offset, "ORDER BY rounded_time%s%s ",
			(query_has_columns ? "," : ""), columns_to_select);

	/* format */
	zbx_snprintf_alloc(sql, &alloc, &offset, "FORMAT JSONCompactEachRow;");

	zbx_free(columns_to_select);
	zbx_free(aggr_columns_to_select);
	zbx_free(table_to_select_from);
	zbx_free(conditions);
}
