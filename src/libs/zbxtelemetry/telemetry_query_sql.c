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

#include "zbxtelemetry.h"

#include "telemetry.h"

#include "zbxalgo.h"
#include "zbxcommon.h"
#include "zbxdb.h"
#include "zbxstr.h"

ZBX_PTR_VECTOR_DECL(tq_aggr_column_ptr, zbx_tq_aggr_column_t *)
ZBX_PTR_VECTOR_IMPL(tq_aggr_column_ptr, zbx_tq_aggr_column_t *)

typedef struct
{
	zbx_apm_db_type_t	db_type;
	const zbx_dbconn_t	*db;
}
tq_sql_ctx_t;

static char	*tq_sql_dyn_escape_with_backslash_generic(const char *src, const char *esc_chars)
{
	size_t	len = 1; /* '\0' */
	char	*dst, *d;

	if (NULL == src)
		src = "";

	for (const char *p = src; '\0' != *p; p++)
	{
		if (NULL != strchr(esc_chars, *p))
			len++;
		len++;
	}

	d = (dst = zbx_malloc(NULL, len));

	for (const char *p = src; '\0' != *p; p++)
	{
		if (NULL != strchr(esc_chars, *p))
			*d++ = '\\';
		*d++ = *p;
	}

	*d = '\0';

	return dst;
}

static char	*tq_sql_dyn_escape_with_doubling_generic(const char *src, const char *esc_chars)
{
	size_t	len = 1; /* '\0' */
	char	*dst, *d;

	if (NULL == src)
		src = "";

	for (const char *p = src; '\0' != *p; p++)
	{
		if (NULL != strchr(esc_chars, *p))
			len++;
		len++;
	}

	d = (dst = zbx_malloc(NULL, len));

	for (const char *p = src; '\0' != *p; p++)
	{
		if (NULL != strchr(esc_chars, *p))
			*d++ = *p;
		*d++ = *p;
	}

	*d = '\0';

	return dst;
}

static char	*tq_sql_dyn_quote_generic(const char *src, char quote_char)
{
	size_t	src_strlen;
	char	*dst;

	if (NULL == src)
		src = "";

	src_strlen = strlen(src);
	dst = zbx_malloc(NULL, src_strlen + 2 + 1);

	*dst = quote_char;
	zbx_strlcpy(dst + 1, src, src_strlen + 1);
	*(dst + 1 + src_strlen) = quote_char;
	*(dst + 1 + src_strlen + 1) = '\0';

	return dst;
}

static char	*tq_sql_dyn_escape_string_unquoted(const char *src, const tq_sql_ctx_t *ctx)
{
	if (ZBX_APM_DB_TYPE_CLICKHOUSE == ctx->db_type)
		return tq_sql_dyn_escape_with_backslash_generic(src, "'\"`\\");

	return zbx_dbconn_dyn_escape_string(ctx->db, src);
}

/******************************************************************************
 *                                                                            *
 * Return value: escaped and quoted string to be used as a string literal     *
 *                                                                            *
 ******************************************************************************/
static char	*tq_sql_dyn_escape_string(const char *src, const tq_sql_ctx_t *ctx)
{
	char	*src_esc = tq_sql_dyn_escape_string_unquoted(src, ctx);
	char	*out = tq_sql_dyn_quote_generic(src_esc, '\'');

	zbx_free(src_esc);

	return out;
}

/******************************************************************************
 *                                                                            *
 * Return value: escaped and quoted string to be used as column or table name *
 *                                                                            *
 ******************************************************************************/
static char	*tq_sql_dyn_escape_name(const char *src, const tq_sql_ctx_t *ctx)
{
	char	*src_esc, *out;

	if (ZBX_APM_DB_TYPE_POSTGRESQL == ctx->db_type)
		src_esc = tq_sql_dyn_escape_with_doubling_generic(src, "\"");
	else if (ZBX_APM_DB_TYPE_MYSQL == ctx->db_type)
		src_esc = tq_sql_dyn_escape_with_doubling_generic(src, "`");
	else /* clickhouse */
		src_esc = tq_sql_dyn_escape_string_unquoted(src, ctx);

	out = tq_sql_dyn_quote_generic(src_esc, (ZBX_APM_DB_TYPE_MYSQL == ctx->db_type ? '`' : '"'));

	zbx_free(src_esc);
	return out;
}

/******************************************************************************
 *                                                                            *
 * Return value: escaped and UNQUOTED string to use in a LIKE pattern         *
 *                                                                            *
 ******************************************************************************/
static char	*tq_sql_dyn_escape_like_pattern(const char *src, const tq_sql_ctx_t *ctx)
{
	if (ZBX_APM_DB_TYPE_CLICKHOUSE == ctx->db_type)
	{
		char	*src_esc_like = tq_sql_dyn_escape_with_backslash_generic(src, "_%\\");
		char	*out;

		out = tq_sql_dyn_escape_string_unquoted(src_esc_like, ctx);

		zbx_free(src_esc_like);
		return out;
	}

	return zbx_dbconn_dyn_escape_like_pattern(ctx->db, src);
}

static char	*tq_sql_dyn_get_attribute_by_key(const char *atom, const char *key, const tq_sql_ctx_t *ctx)
{
	char	*str;

	if (ZBX_APM_DB_TYPE_CLICKHOUSE == ctx->db_type)
	{
		char	*key_esc = tq_sql_dyn_escape_string(key, ctx);

		str = zbx_dsprintf(NULL, "%s[%s]", atom, key_esc);

		zbx_free(key_esc);
	}
	else
	{
		THIS_SHOULD_NEVER_HAPPEN;
		str = zbx_strdup(NULL, "");
	}

	return str;
}

static char	*tq_sql_dyn_get_operand(const char *atom, zbx_tq_column_type_t type, const char *key,
		const tq_sql_ctx_t *ctx)
{
	if (ZBX_TQ_COLUMN_TYPE_ATTRIBUTES == type)
	{
		return tq_sql_dyn_get_attribute_by_key(atom, key, ctx);
	}
	else if (ZBX_TQ_COLUMN_TYPE_TIMESTAMP == type)
	{
		if (ZBX_APM_DB_TYPE_CLICKHOUSE == ctx->db_type)
		{
			return zbx_dsprintf(NULL, "toUnixTimestamp(%s)", atom);
		}
		else
		{
			THIS_SHOULD_NEVER_HAPPEN;
			return zbx_strdup(NULL, "");
		}
	}
	else if (ZBX_TQ_COLUMN_TYPE_BOOL == type)
	{
		if (ZBX_APM_DB_TYPE_CLICKHOUSE == ctx->db_type)
		{
			return zbx_dsprintf(NULL, "toUInt8(%s)", atom);
		}
		else
		{
			THIS_SHOULD_NEVER_HAPPEN;
			return zbx_strdup(NULL, "");
		}
	}
	else
	{
		return zbx_strdup(NULL, atom);
	}
}

static char	*tq_sql_dyn_get_columns_to_select(const zbx_tq_query_t *query, const tq_sql_ctx_t *ctx)
{
	char	*str = NULL;
	size_t	alloc = 0;
	size_t	offset = 0;

	for (int i = 0; i < query->columns.values_num; i++)
	{
		const zbx_tq_column_t	*col = &query->columns.values[i];
		char			*name_esc = tq_sql_dyn_escape_name(col->column, ctx);
		char			*col_to_select = tq_sql_dyn_get_operand(name_esc, col->col_type,
				col->attribute_key, ctx);

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

static char	*tq_sql_dyn_get_percentile(const char *atom, double fraction, const tq_sql_ctx_t *ctx)
{
	char	*str;

	if (ZBX_APM_DB_TYPE_CLICKHOUSE == ctx->db_type)
	{
		str = zbx_dsprintf(NULL, "quantileTDigest(" ZBX_FS_DBL_EXT(4) ")(%s)", fraction, atom);
	}
	else
	{
		THIS_SHOULD_NEVER_HAPPEN;
		str = zbx_strdup(NULL, "");
	}

	return str;
}

static char	*tq_sql_dyn_get_aggr_columns_to_select(const zbx_tq_query_t *query, const tq_sql_ctx_t *ctx)
{
	char	*str = NULL;
	size_t	alloc = 0;
	size_t	offset = 0;

	for (int i = 0; i < query->aggregated_columns.values_num; i++)
	{
		const zbx_tq_aggr_column_t	*aggr_col = &query->aggregated_columns.values[i];
		char				*col_name_esc = NULL;
		char				*operand = NULL;

		if (ZBX_TQ_FUNCTION_COUNT != aggr_col->function)
		{
			col_name_esc = tq_sql_dyn_escape_name(aggr_col->column, ctx);
			operand = tq_sql_dyn_get_operand(col_name_esc, aggr_col->col_type, NULL, ctx);
		}

		switch (aggr_col->function)
		{
			case ZBX_TQ_FUNCTION_COUNT:
				zbx_snprintf_alloc(&str, &alloc, &offset, "COUNT(*)");
				break;
			case ZBX_TQ_FUNCTION_MIN:
				zbx_snprintf_alloc(&str, &alloc, &offset, "MIN(%s)", operand);
				break;
			case ZBX_TQ_FUNCTION_MAX:
				zbx_snprintf_alloc(&str, &alloc, &offset, "MAX(%s)", operand);
				break;
			case ZBX_TQ_FUNCTION_AVG:
				zbx_snprintf_alloc(&str, &alloc, &offset, "AVG(%s)", operand);
				break;
			case ZBX_TQ_FUNCTION_SUM:
				zbx_snprintf_alloc(&str, &alloc, &offset, "SUM(%s)", operand);
				break;
			case ZBX_TQ_FUNCTION_PERCENTILE:
			{
				double	fraction = strtod(aggr_col->parameters.values[0], NULL) / 100.0;
				char	*percentile_expr = tq_sql_dyn_get_percentile(operand, fraction, ctx);

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
		zbx_free(operand);
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
static char	*tq_sql_dyn_get_table_to_select_from(const zbx_tq_query_t *query, const tq_sql_ctx_t *ctx)
{
	char	*str = NULL;
	char	*str_esc;

	switch (query->signal_type)
	{
		case ZBX_TQ_SIGNAL_TYPE_TRACES:
			str = zbx_strdup(NULL, "otel_traces");
			break;

		case ZBX_TQ_SIGNAL_TYPE_METRICS:
			switch (query->metric_point_type)
			{
				case ZBX_TQ_METRIC_POINT_TYPE_SUM:
					str = zbx_strdup(NULL, "otel_metrics_sum");
					break;
				case ZBX_TQ_METRIC_POINT_TYPE_GAUGE:
					str = zbx_strdup(NULL, "otel_metrics_gauge");
					break;
				case ZBX_TQ_METRIC_POINT_TYPE_HISTOGRAM:
					str = zbx_strdup(NULL, "otel_metrics_histogram");
					break;
				case ZBX_TQ_METRIC_POINT_TYPE_EXPONENTIAL_HISTOGRAM:
					str = zbx_strdup(NULL, "otel_metrics_exponential_histogram");
					break;

				case ZBX_TQ_METRIC_POINT_TYPE_UNKNOWN:
					THIS_SHOULD_NEVER_HAPPEN;
			}
			break;

		case ZBX_TQ_SIGNAL_TYPE_LOGS:
			str = zbx_strdup(NULL, "otel_logs");
			break;

		case ZBX_TQ_SIGNAL_TYPE_UNKNOWN:
			THIS_SHOULD_NEVER_HAPPEN;
	}

	str_esc = tq_sql_dyn_escape_name(str, ctx);

	zbx_free(str);

	return str_esc;
}

static char	*tq_sql_dyn_get_condition_contains(const char *atom, const char *value, const tq_sql_ctx_t *ctx)
{
	char	*str;
	char	*value_esc = tq_sql_dyn_escape_like_pattern(value, ctx);

	if (ZBX_APM_DB_TYPE_CLICKHOUSE == ctx->db_type)
	{
		str = zbx_dsprintf(NULL, "%s LIKE '%%%s%%'", atom, value_esc);
	}
	else
	{
		THIS_SHOULD_NEVER_HAPPEN;
		str = zbx_strdup(NULL, "");
	}

	zbx_free(value_esc);

	return str;
}

static char	*tq_sql_dyn_get_condition_exists(const char *atom, const char *key, const tq_sql_ctx_t *ctx)
{
	char	*str;

	if (ZBX_APM_DB_TYPE_CLICKHOUSE == ctx->db_type)
	{
		char	*key_esc = tq_sql_dyn_escape_string(key, ctx);

		str = zbx_dsprintf(NULL, "mapContainsKey(%s, %s)", atom, key_esc);

		zbx_free(key_esc);
	}
	else
	{
		THIS_SHOULD_NEVER_HAPPEN;
		str = zbx_strdup(NULL, "");
	}

	return str;
}

static char	*tq_sql_dyn_get_atom_condition(const char *atom, zbx_tq_column_type_t type, const char *key,
		const char *value, zbx_tq_operator_t operator, const tq_sql_ctx_t *ctx)
{
	if (ZBX_TQ_OPERATOR_EQUAL == operator || ZBX_TQ_OPERATOR_NOT_EQUAL == operator)
	{
		char		*str;
		char		*operand = tq_sql_dyn_get_operand(atom, type, key, ctx);
		char		*value_esc = tq_sql_dyn_escape_string(value, ctx);
		const char	*operator_str = (ZBX_TQ_OPERATOR_EQUAL == operator ? "=" : "<>");

		if (ZBX_TQ_COLUMN_TYPE_ATTRIBUTES == type)
		{
			char	*exists_check = tq_sql_dyn_get_condition_exists(atom, key, ctx);

			/* ensure that "not equal" results in false if attribute key is missing */
			str = zbx_dsprintf(NULL, "(%s AND %s%s%s)", exists_check, operand, operator_str, value_esc);

			zbx_free(exists_check);
		}
		else
		{
			str = zbx_dsprintf(NULL, "(%s%s%s)", operand, operator_str, value_esc);
		}

		zbx_free(operand);
		zbx_free(value_esc);

		return	str;
	}
	else if (ZBX_TQ_OPERATOR_CONTAINS == operator || ZBX_TQ_OPERATOR_NOT_CONTAINS == operator)
	{
		char	*operand = tq_sql_dyn_get_operand(atom, type, key, ctx);
		char	*str = tq_sql_dyn_get_condition_contains(operand, value, ctx);

		str = zbx_dsprintf(str, "(%s%s)", (ZBX_TQ_OPERATOR_CONTAINS == operator ? "" : "NOT "), str);

		zbx_free(operand);

		return str;
	}
	else /* exists */
	{
		return tq_sql_dyn_get_condition_exists(atom, key, ctx);
	}
}

static char	*tq_sql_dyn_get_array_condition(const zbx_tq_condition_t *cond, const tq_sql_ctx_t *ctx)
{
	char	*str;
	char	*col_esc = tq_sql_dyn_escape_name(cond->column, ctx);

	if (ZBX_APM_DB_TYPE_CLICKHOUSE == ctx->db_type)
	{
		char	*elem_cond = tq_sql_dyn_get_atom_condition("x", tq_get_base_column_type(cond->col_type),
				cond->attribute_key, cond->value, cond->operator, ctx);

		str = zbx_dsprintf(NULL, "arrayExists(x -> %s, %s)", elem_cond, col_esc);

		zbx_free(elem_cond);
	}
	else
	{
		THIS_SHOULD_NEVER_HAPPEN;
		str = zbx_strdup(NULL, "");
	}

	zbx_free(col_esc);

	return str;
}

static char	*tq_sql_dyn_get_condition(const zbx_tq_condition_t *cond, const tq_sql_ctx_t *ctx)
{
	if (SUCCEED == tq_column_type_is_array(cond->col_type))
	{
		return tq_sql_dyn_get_array_condition(cond, ctx);
	}
	else
	{
		char	*name_esc = tq_sql_dyn_escape_name(cond->column, ctx);
		char	*str = tq_sql_dyn_get_atom_condition(name_esc, cond->col_type, cond->attribute_key, cond->value,
				cond->operator, ctx);

		zbx_free(name_esc);

		return str;
	}
}

static char	*tq_sql_dyn_get_conditions_simple(const zbx_tq_query_t *query, const tq_sql_ctx_t *ctx)
{
	char	*str = NULL;
	size_t	alloc = 0;
	size_t	offset = 0;

	for (int i = 0; i < query->conditions.values_num; i++)
	{
		const zbx_tq_condition_t	*cond = &query->conditions.values[i];

		char	*cond_str = tq_sql_dyn_get_condition(cond, ctx);

		zbx_snprintf_alloc(&str, &alloc, &offset, "%s", cond_str);

		if (query->conditions.values_num - 1 != i)
		{
			zbx_snprintf_alloc(&str, &alloc, &offset, " %s ",
					(query->evaltype == ZBX_TQ_EVAL_TYPE_AND ? "AND" : "OR"));
		}

		zbx_free(cond_str);
	}

	if (str == NULL)
	{
		THIS_SHOULD_NEVER_HAPPEN;
		str = zbx_strdup(NULL, "");
	}

	return str;
}

static char	*tq_sql_dyn_get_conditions_and_or(const zbx_tq_query_t *query, const tq_sql_ctx_t *ctx)
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
		char				*cond_str = tq_sql_dyn_get_condition(cond, ctx);

		zbx_snprintf_alloc(&str, &alloc, &offset, "%s", cond_str);

		if (conditions_sorted.values_num - 1 == i)
		{
			zbx_snprintf_alloc(&str, &alloc, &offset, ")");
		}
		else if (0 != tq_condition_ptr_compare_by_column_and_key((void *)&cond,
				(void *)&conditions_sorted.values[i + 1]))
		{
			zbx_snprintf_alloc(&str, &alloc, &offset, ")AND(");
		}
		else
		{
			zbx_snprintf_alloc(&str, &alloc, &offset, " OR ");
		}

		zbx_free(cond_str);
	}

	zbx_vector_tq_condition_ptr_destroy(&conditions_sorted);

	return str;
}

static void	tq_sql_add_condition_node(const zbx_tq_query_t *query, const zbx_tq_formula_node_t *node, char **str,
		size_t *alloc, size_t *offset, const tq_sql_ctx_t *ctx)
{
	if (TQ_FORMULA_NODE_TYPE_OR == node->type || TQ_FORMULA_NODE_TYPE_AND == node->type)
	{
		zbx_strcpy_alloc(str, alloc, offset, "(");

		for (int i = 0; i < node->children.values_num; i++)
		{
			tq_sql_add_condition_node(query, node->children.values[i], str, alloc, offset, ctx);

			if (node->children.values_num - 1 != i)
			{
				zbx_strcpy_alloc(str, alloc, offset,
						(TQ_FORMULA_NODE_TYPE_OR == node->type ? " OR " : " AND "));
			}
		}

		zbx_strcpy_alloc(str, alloc, offset, ")");
	}
	else if (TQ_FORMULA_NODE_TYPE_NOT == node->type)
	{
		zbx_strcpy_alloc(str, alloc, offset, "(NOT ");

		tq_sql_add_condition_node(query, node->children.values[0], str, alloc, offset, ctx);

		zbx_strcpy_alloc(str, alloc, offset, ")");
	}
	else /* TQ_FORMULA_NODE_TYPE_LEAF */
	{
		char	*cond_str = tq_sql_dyn_get_condition(&query->conditions.values[node->condition_idx], ctx);

		zbx_snprintf_alloc(str, alloc, offset, "%s", cond_str);

		zbx_free(cond_str);
	}
}

static char	*tq_sql_dyn_get_conditions_expression(const zbx_tq_query_t *query, const tq_sql_ctx_t *ctx)
{
	char		*str = NULL;
	size_t		alloc = 0;
	size_t		offset = 0;

	tq_sql_add_condition_node(query, query->formula_parsed, &str, &alloc, &offset, ctx);

	return str;
}

static char	*tq_sql_dyn_get_conditions(const zbx_tq_query_t *query, const tq_sql_ctx_t *ctx)
{
	if (0 == query->conditions.values_num)
		return zbx_strdup(NULL, "");

	switch (query->evaltype)
	{
		case ZBX_TQ_EVAL_TYPE_AND:
		case ZBX_TQ_EVAL_TYPE_OR:
			return tq_sql_dyn_get_conditions_simple(query, ctx);

		case ZBX_TQ_EVAL_TYPE_AND_OR:
			return tq_sql_dyn_get_conditions_and_or(query, ctx);

		case ZBX_TQ_EVAL_TYPE_EXPRESSION:
			return tq_sql_dyn_get_conditions_expression(query, ctx);

		case ZBX_TQ_EVAL_TYPE_UNKNOWN:
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			return zbx_strdup(NULL, "");
	}
}

static const char	*tq_sql_get_timestamp_column_name(const zbx_tq_query_t *query)
{
	return (ZBX_TQ_SIGNAL_TYPE_METRICS == query->signal_type ? "TimeUnix" : "Timestamp");
}

void	zbx_tq_sql_generate_clickhouse(const zbx_tq_query_t *query, int time_shift, int lookback_limit, int granularity,
		time_t now, time_t lasttimestamp, char **sql)
{
	tq_sql_ctx_t	ctx = {
		.db_type = ZBX_APM_DB_TYPE_CLICKHOUSE,
		.db = NULL,
	};

	const int	query_has_columns = (0 != query->columns.values_num) ? SUCCEED : FAIL;
	const int	query_has_conditions = (0 != query->conditions.values_num) ? SUCCEED : FAIL;
	const char	*ts_col = tq_sql_get_timestamp_column_name(query);
	size_t		alloc = 0, offset = 0;
	time_t		timestamp_filter_lower_bound, timestamp_filter_upper_bound;
	char		*columns_to_select;
	char		*aggr_columns_to_select;
	char		*table_to_select_from;
	char		*conditions;

	*sql = NULL;

	columns_to_select	= tq_sql_dyn_get_columns_to_select(query, &ctx);
	aggr_columns_to_select	= tq_sql_dyn_get_aggr_columns_to_select(query, &ctx);
	table_to_select_from	= tq_sql_dyn_get_table_to_select_from(query, &ctx);
	conditions		= tq_sql_dyn_get_conditions(query, &ctx);

	zbx_tq_get_timestamp_filter_bounds(time_shift, lookback_limit, granularity, now, lasttimestamp,
			&timestamp_filter_lower_bound, &timestamp_filter_upper_bound);

	/* select */
	zbx_snprintf_alloc(sql, &alloc, &offset, "SELECT ");
	zbx_snprintf_alloc(sql, &alloc, &offset,
			"intDiv((toUnixTimestamp(\"%s\")-" ZBX_FS_TIME_T "), %d)*%d+" ZBX_FS_TIME_T " AS rounded_time,",
			ts_col, (zbx_fs_time_t)timestamp_filter_lower_bound, granularity, granularity,
			(zbx_fs_time_t)timestamp_filter_lower_bound);

	if (SUCCEED == query_has_columns)
		zbx_snprintf_alloc(sql, &alloc, &offset, "%s,", columns_to_select);

	zbx_snprintf_alloc(sql, &alloc, &offset, "%s ", aggr_columns_to_select);

	/* from */
	zbx_snprintf_alloc(sql, &alloc, &offset, "FROM %s ", table_to_select_from);

	/* where */
	zbx_snprintf_alloc(sql, &alloc, &offset, "WHERE ");
	zbx_snprintf_alloc(sql, &alloc, &offset,
			"\"%s\">=toDateTime(" ZBX_FS_TIME_T ") "
			"AND \"%s\"<toDateTime(" ZBX_FS_TIME_T ") ",
			ts_col, (zbx_fs_time_t)timestamp_filter_lower_bound, ts_col,
			(zbx_fs_time_t)timestamp_filter_upper_bound);
	if (SUCCEED == query_has_conditions)
		zbx_snprintf_alloc(sql, &alloc, &offset, "AND (%s) ", conditions);

	/* group by */
	zbx_snprintf_alloc(sql, &alloc, &offset, "GROUP BY rounded_time%s%s ",
			(SUCCEED == query_has_columns ? "," : ""), columns_to_select);

	/* order by */
	zbx_snprintf_alloc(sql, &alloc, &offset, "ORDER BY rounded_time%s%s ",
			(SUCCEED == query_has_columns ? "," : ""), columns_to_select);

	zbx_snprintf_alloc(sql, &alloc, &offset, "FORMAT JSONCompactEachRow ");
	zbx_snprintf_alloc(sql, &alloc, &offset, "SETTINGS output_format_json_quote_64bit_integers=0;");

	zbx_free(columns_to_select);
	zbx_free(aggr_columns_to_select);
	zbx_free(table_to_select_from);
	zbx_free(conditions);
}
