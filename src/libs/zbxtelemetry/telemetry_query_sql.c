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
#include "zbxalgo.h"
#include "zbxcommon.h"
#include "zbxdb.h"
#include "zbxstr.h"
#include "zbxtelemetry.h"

ZBX_PTR_VECTOR_DECL(tq_aggr_column_ptr, zbx_tq_aggr_column_t *)
ZBX_PTR_VECTOR_IMPL(tq_aggr_column_ptr, zbx_tq_aggr_column_t *)

/* because clickhouse does not support JSON columns to be arrays on top level, the array is located in a subcolumn */
/* the code assumes it does not contain special symbols and, therefore, does not escape it */
/* TODO: decide if this is the way to go, maybe just have the field be of Array(...) type (at least for clickhouse) */
/* TODO: rename to something more generic, it isn't always attributes */
#define TQ_SQL_ATTRIBUTES_ARRAY_JSON_KEY "attributes"

typedef struct
{
	zbx_tq_db_type_t	db_type;
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
	if (ZBX_TQ_DB_TYPE_CLICKHOUSE == ctx->db_type)
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

	if (ZBX_TQ_DB_TYPE_POSTGRESQL == ctx->db_type)
		src_esc = tq_sql_dyn_escape_with_doubling_generic(src, "\"");
	else if (ZBX_TQ_DB_TYPE_MYSQL == ctx->db_type)
		src_esc = tq_sql_dyn_escape_with_doubling_generic(src, "`");
	else /* clickhouse */
		src_esc = tq_sql_dyn_escape_string_unquoted(src, ctx);

	out = tq_sql_dyn_quote_generic(src_esc, (ZBX_TQ_DB_TYPE_MYSQL == ctx->db_type ? '`' : '"'));

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
	if (ZBX_TQ_DB_TYPE_CLICKHOUSE == ctx->db_type)
	{
		char	*src_esc_like = tq_sql_dyn_escape_with_backslash_generic(src, "_%\\");
		char	*out;

		out = tq_sql_dyn_escape_string_unquoted(src_esc_like, ctx);

		zbx_free(src_esc_like);
		return out;
	}

	return zbx_dbconn_dyn_escape_like_pattern(ctx->db, src);
}

/******************************************************************************
 *                                                                            *
 * Return value: escaped and QUOTED (with '"') string to use in JSON_EXTRACT  *
 *               path in MySQL (e.g. "JSON_EXTRACT(%s,'$.%s')")               *
 *               (should NOT be escaped the second time as a string literal)  *
 *                                                                            *
 ******************************************************************************/
static char	*tq_sql_dyn_escape_json_path_key_mysql(const char *src, const tq_sql_ctx_t *ctx)
{
	char	*src_esc_path = tq_sql_dyn_escape_with_backslash_generic(src, "\"\\");
	char	*src_esc_full = tq_sql_dyn_escape_string_unquoted(src_esc_path, ctx);
	char	*out = tq_sql_dyn_quote_generic(src_esc_full, '"');

	zbx_free(src_esc_path);
	zbx_free(src_esc_full);

	return out;
}

static const char	*tq_sql_key_or_null(const char *key, zbx_tq_column_type_t col_type)
{
	return (SUCCEED == tq_column_type_is_attributes(col_type) ? key : NULL);
}

/******************************************************************************
 *                                                                            *
 * Comments: does not escape operand! If escaping is needed, it must be done  *
 *           by the caller, before passing it to this function.               *
 *                                                                            *
 ******************************************************************************/
static char	*tq_sql_dyn_get_json_subcolumn_raw(const char *operand, const char *key, const tq_sql_ctx_t *ctx)
{
	char	*str;

	switch (ctx->db_type)
	{
		case ZBX_TQ_DB_TYPE_POSTGRESQL:
		{
			/* TODO: check that this is the correct escaping */
			char	*key_esc = tq_sql_dyn_escape_string(key, ctx);

			str = zbx_dsprintf(NULL, "%s->>%s", operand, key_esc);

			zbx_free(key_esc);
			break;
		}
		case ZBX_TQ_DB_TYPE_MYSQL:
		{
			char	*key_esc = tq_sql_dyn_escape_json_path_key_mysql(key, ctx);

			str = zbx_dsprintf(NULL, "JSON_UNQUOTE(JSON_EXTRACT(%s,'$.%s'))", operand, key_esc);

			zbx_free(key_esc);
			break;
		}
		case ZBX_TQ_DB_TYPE_CLICKHOUSE:
		{
			/* TODO: check that this is the correct escaping */
			char	*key_esc_unquoted = tq_sql_dyn_escape_string_unquoted(key, ctx);

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
static char	*tq_sql_dyn_get_operand_raw(const char *x, const char *key, const tq_sql_ctx_t *ctx)
{
	if (NULL == key)
		return zbx_strdup(NULL, x);

	return tq_sql_dyn_get_json_subcolumn_raw(x, key, ctx);
}

static char	*tq_sql_dyn_get_columns_to_select(const zbx_tq_query_t *query, const tq_sql_ctx_t *ctx)
{
	char	*str = NULL;
	size_t	alloc = 0;
	size_t	offset = 0;

	for (int i = 0; i < query->columns.values_num; i++)
	{
		const zbx_tq_column_t	*col = &query->columns.values[i];
		char			*name_esc = tq_sql_dyn_escape_name(col->name, ctx);
		char			*col_to_select = tq_sql_dyn_get_operand_raw(name_esc,
				tq_sql_key_or_null(col->key, col->col_type), ctx);

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

static char	*tq_sql_dyn_get_percentile(const char *name, double fraction, const tq_sql_ctx_t *ctx)
{
	/* TODO: test correctness on all dbs */
	char	*str;
	char	*name_esc_unquoted = tq_sql_dyn_escape_string_unquoted(name, ctx);

	switch (ctx->db_type)
	{
		case ZBX_TQ_DB_TYPE_POSTGRESQL:
			str = zbx_dsprintf(NULL, "(percentile_cont(" ZBX_FS_DBL_EXT(4)
					") WITHIN GROUP (ORDER BY \"%s\"))", fraction, name_esc_unquoted);
			break;
		case ZBX_TQ_DB_TYPE_MYSQL:
			str = zbx_dsprintf(NULL, "MIN(CASE WHEN `__cd_%s` >= " ZBX_FS_DBL_EXT(4) " THEN `%s` END)",
					name_esc_unquoted,
					fraction, name_esc_unquoted);
			break;
		case ZBX_TQ_DB_TYPE_CLICKHOUSE:
			str = zbx_dsprintf(NULL, "quantileTDigest(" ZBX_FS_DBL_EXT(4) ")(\"%s\")", fraction,
					name_esc_unquoted);
			break;

		default:
			THIS_SHOULD_NEVER_HAPPEN;
			str = zbx_strdup(NULL, "");
	}

	zbx_free(name_esc_unquoted);

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

		if (ZBX_TQ_FUNCTION_COUNT != aggr_col->function && ZBX_TQ_FUNCTION_PERCENTILE != aggr_col->function)
			col_name_esc = tq_sql_dyn_escape_name(aggr_col->column_name, ctx);

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
				double	fraction = strtod(aggr_col->args.values[0], NULL) / 100.0;
				char	*percentile_expr = tq_sql_dyn_get_percentile(aggr_col->column_name,
						fraction, ctx);

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
static char	*tq_sql_dyn_get_table_to_select_from(const zbx_tq_query_t *query, const tq_sql_ctx_t *ctx)
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

	str_esc = tq_sql_dyn_escape_name(str, ctx);

	zbx_free(str);

	return str_esc;
}

static char	*tq_sql_dyn_get_condition_contains(const char *operand, const char *value, const tq_sql_ctx_t *ctx)
{
	char	*str;
	char	*value_esc = tq_sql_dyn_escape_like_pattern(value, ctx);

	switch (ctx->db_type)
	{
		case ZBX_TQ_DB_TYPE_POSTGRESQL:
		case ZBX_TQ_DB_TYPE_MYSQL:
			str = zbx_dsprintf(NULL, "%s LIKE '%%%s%%' ESCAPE '%c'", operand, value_esc,
					ZBX_SQL_LIKE_ESCAPE_CHAR);
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

static char	*tq_sql_dyn_get_condition_exists(const char *atom, const char *key, const tq_sql_ctx_t *ctx)
{
	char	*str;

	switch (ctx->db_type)
	{
		case ZBX_TQ_DB_TYPE_POSTGRESQL:
		{
			char	*key_esc = tq_sql_dyn_escape_string(key, ctx);

			str = zbx_dsprintf(NULL, "(%s ? %s)", atom, key_esc);

			zbx_free(key_esc);
			break;
		}
		case ZBX_TQ_DB_TYPE_MYSQL:
		{
			char	*key_esc = tq_sql_dyn_escape_json_path_key_mysql(key, ctx);

			str = zbx_dsprintf(NULL, "JSON_CONTAINS_PATH(%s, 'one', '$.%s')", atom, key_esc);

			zbx_free(key_esc);
			break;
		}
		case ZBX_TQ_DB_TYPE_CLICKHOUSE:
		{
			char	*key_esc_unquoted = tq_sql_dyn_escape_string_unquoted(key, ctx);

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
		zbx_tq_operator_t operator, const tq_sql_ctx_t *ctx)
{
	if (ZBX_TQ_OPERATOR_EQUAL == operator || ZBX_TQ_OPERATOR_NOT_EQUAL == operator)
	{
		char	*str;
		char	*operand = tq_sql_dyn_get_operand_raw(atom, key, ctx);
		char	*value_esc = tq_sql_dyn_escape_string(value, ctx);

		/* ensure that "not equal" results in false if attribute key is missing */
		str = zbx_dsprintf(NULL, "(%s IS NOT NULL AND %s %s %s)", operand, operand,
				(ZBX_TQ_OPERATOR_EQUAL == operator ? "=" : "<>"), value_esc);

		zbx_free(value_esc);
		zbx_free(operand);

		return	str;
	}
	else if (ZBX_TQ_OPERATOR_CONTAINS == operator || ZBX_TQ_OPERATOR_NOT_CONTAINS == operator)
	{
		char	*operand = tq_sql_dyn_get_operand_raw(atom, key, ctx);
		char	*str = tq_sql_dyn_get_condition_contains(operand, value, ctx);

		/* check for NULL for consistency with "equal"/"not equal" behavior */
		str = zbx_dsprintf(str, "(%s IS NOT NULL AND %s%s)", operand,
				(ZBX_TQ_OPERATOR_CONTAINS == operator ? "" : "NOT "), str);

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
	/* TODO: test on non-attribute arrays on all dbs */
	char	*str;
	char	*col_esc = tq_sql_dyn_escape_name(cond->column_name, ctx);

	if (ZBX_TQ_DB_TYPE_POSTGRESQL == ctx->db_type)
	{
		const char	*array_elems_func = (ZBX_TQ_COLUMN_TYPE_ARRAY_ATTRIBUTES == cond->col_type
				? "jsonb_array_elements" : "jsonb_array_elements_text");
		char	*elem_cond = tq_sql_dyn_get_atom_condition("e.elem",
				tq_sql_key_or_null(cond->key, cond->col_type), cond->value, cond->operator, ctx);

		str = zbx_dsprintf(NULL, "(EXISTS(SELECT 1 FROM %s(%s->'" TQ_SQL_ATTRIBUTES_ARRAY_JSON_KEY "')"
				" AS e(elem) WHERE %s))", array_elems_func, col_esc, elem_cond);

		zbx_free(elem_cond);
	}
	else if (ZBX_TQ_DB_TYPE_MYSQL == ctx->db_type)
	{
		char	*elem_cond = tq_sql_dyn_get_atom_condition("e.elem",
				tq_sql_key_or_null(cond->key, cond->col_type), cond->value, cond->operator, ctx);

		str = zbx_dsprintf(NULL,
				"(EXISTS(SELECT 1 FROM JSON_TABLE(JSON_EXTRACT(%s,'$." TQ_SQL_ATTRIBUTES_ARRAY_JSON_KEY
				"'), '$[*]' COLUMNS(elem %s PATH '$')) AS e WHERE %s))",
				col_esc, (ZBX_TQ_COLUMN_TYPE_ARRAY_ATTRIBUTES == cond->col_type ? "JSON" : "TEXT"),
				elem_cond);

		zbx_free(elem_cond);
	}
	else /* clickhouse */
	{
		char	*elem_cond = tq_sql_dyn_get_atom_condition("x", tq_sql_key_or_null(cond->key, cond->col_type),
				cond->value, cond->operator, ctx);

		str = zbx_dsprintf(NULL, "arrayExists(x -> %s, %s." TQ_SQL_ATTRIBUTES_ARRAY_JSON_KEY ".:\"Array(%s)\")",
				elem_cond, col_esc,
				(ZBX_TQ_COLUMN_TYPE_ARRAY_ATTRIBUTES == cond->col_type ? "JSON" : "String"));

		zbx_free(elem_cond);
	}

	zbx_free(col_esc);

	return str;
}

static char	*tq_sql_dyn_get_condition(const zbx_tq_condition_t *cond, const tq_sql_ctx_t *ctx)
{
	if (SUCCEED == tq_column_type_is_array(cond->col_type))
		return tq_sql_dyn_get_array_condition(cond, ctx);
	else
	{
		char	*name_esc = tq_sql_dyn_escape_name(cond->column_name, ctx);
		char	*str = tq_sql_dyn_get_atom_condition(name_esc, tq_sql_key_or_null(cond->key, cond->col_type),
				cond->value, cond->operator, ctx);

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

static int	tq_sql_query_has_percentiles(const zbx_tq_query_t *query)
{
	for (int i = 0; i < query->aggregated_columns.values_num; i++)
	{
		if (ZBX_TQ_FUNCTION_PERCENTILE == query->aggregated_columns.values[i].function)
			return SUCCEED;
	}
	return FAIL;
}

static int	tq_sql_aggr_column_ptr_compare_by_column(const void *a, const void *b)
{
	const zbx_tq_aggr_column_t	*cond_a = *(const zbx_tq_aggr_column_t * const *)a;
	const zbx_tq_aggr_column_t	*cond_b = *(const zbx_tq_aggr_column_t * const *)b;

	return strcmp(cond_a->column_name, cond_b->column_name);
}

static char	*tq_sql_dyn_get_cume_dists_mysql(const zbx_tq_query_t *query, const char *rounded_time_expr,
		const char *columns_to_select, const tq_sql_ctx_t *ctx)
{
	zbx_vector_tq_aggr_column_ptr_t	percentile_cols_sorted;
	char				*str = NULL;
	size_t				alloc = 0;
	size_t				offset = 0;

	if (SUCCEED != tq_sql_query_has_percentiles(query))
		return zbx_strdup(NULL, "");

	zbx_vector_tq_aggr_column_ptr_create(&percentile_cols_sorted);

	for (int i = 0; i < query->aggregated_columns.values_num; i++)
	{
		zbx_tq_aggr_column_t	*aggr_col = &query->aggregated_columns.values[i];

		if (ZBX_TQ_FUNCTION_PERCENTILE == aggr_col->function)
			zbx_vector_tq_aggr_column_ptr_append(&percentile_cols_sorted, aggr_col);
	}

	zbx_vector_tq_aggr_column_ptr_sort(&percentile_cols_sorted, tq_sql_aggr_column_ptr_compare_by_column);

	for (int i = 0; i < percentile_cols_sorted.values_num; i++)
	{
		const char	*name = percentile_cols_sorted.values[i]->column_name;
		char		*name_esc_unquoted;

		if (0 != i && 0 == strcmp(percentile_cols_sorted.values[i-1]->column_name, name))
			continue;

		name_esc_unquoted = tq_sql_dyn_escape_string_unquoted(name, ctx);

		zbx_snprintf_alloc(&str, &alloc, &offset,
				"CUME_DIST() OVER (PARTITION BY %s%s%s ORDER BY `%s`) AS `__cd_%s`,",
				rounded_time_expr, (0 != query->columns.values_num ? "," : ""), columns_to_select,
				name_esc_unquoted, name_esc_unquoted);

		zbx_free(name_esc_unquoted);
	}

	offset--;
	str[offset] = '\0';

	zbx_vector_tq_aggr_column_ptr_destroy(&percentile_cols_sorted);

	return str;
}

static char	*tq_sql_dyn_get_rounded_time_expr_mysql(int granularity, time_t timestamp_filter_lower_bound)
{
	return zbx_dsprintf(NULL,
			"FROM_UNIXTIME(FLOOR((UNIX_TIMESTAMP(`Timestamp`)-" ZBX_FS_TIME_T ")/%d)*%d+" ZBX_FS_TIME_T")",
			timestamp_filter_lower_bound, granularity, granularity, timestamp_filter_lower_bound);
}

static char	*tq_sql_dyn_get_used_columns_mysql(const zbx_tq_query_t *query, const tq_sql_ctx_t *ctx)
{
	zbx_vector_str_t	col_names_sorted;
	char			*str = NULL;
	size_t			alloc = 0;
	size_t			offset = 0;

	zbx_vector_str_create(&col_names_sorted);

	zbx_vector_str_append(&col_names_sorted, "Timestamp");

	for (int i = 0; i < query->columns.values_num; i++)
		zbx_vector_str_append(&col_names_sorted, query->columns.values[i].name);

	for (int i = 0; i < query->aggregated_columns.values_num; i++)
	{
		if (ZBX_TQ_FUNCTION_COUNT == query->aggregated_columns.values[i].function)
			continue;

		zbx_vector_str_append(&col_names_sorted, query->aggregated_columns.values[i].column_name);
	}

	zbx_vector_str_sort(&col_names_sorted, ZBX_DEFAULT_STR_COMPARE_FUNC);

	for (int i = 0; i < col_names_sorted.values_num; i++)
	{
		const char	*name = col_names_sorted.values[i];
		char		*name_esc;

		if (0 != i && 0 == strcmp(col_names_sorted.values[i-1], name))
			continue;

		name_esc = tq_sql_dyn_escape_name(name, ctx);

		zbx_snprintf_alloc(&str, &alloc, &offset, "%s,", name_esc);

		zbx_free(name_esc);
	}

	offset--;
	str[offset] = '\0';

	zbx_vector_str_destroy(&col_names_sorted);

	return str;
}

void	zbx_tq_sql_generate_postgresql(const zbx_tq_query_t *query, time_t now, time_t lasttimestamp, char **sql,
		const zbx_dbconn_t *db)
{
	tq_sql_ctx_t	ctx = {
		.db_type = ZBX_TQ_DB_TYPE_POSTGRESQL,
		.db = db,
	};

	const int	query_has_columns = (0 != query->columns.values_num) ? SUCCEED : FAIL;
	const int	query_has_conditions = (0 != query->conditions.values_num) ? SUCCEED : FAIL;

	size_t	alloc = 0;
	size_t	offset = 0;

	*sql = NULL;

	char	*columns_to_select	= tq_sql_dyn_get_columns_to_select(query, &ctx);
	char	*aggr_columns_to_select	= tq_sql_dyn_get_aggr_columns_to_select(query, &ctx);
	char	*table_to_select_from	= tq_sql_dyn_get_table_to_select_from(query, &ctx);
	char	*conditions		= tq_sql_dyn_get_conditions(query, &ctx);

	time_t timestamp_filter_lower_bound, timestamp_filter_upper_bound;
	zbx_tq_get_timestamp_filter_bounds(query, now, lasttimestamp, &timestamp_filter_lower_bound,
		&timestamp_filter_upper_bound);

	/* select */
	zbx_snprintf_alloc(sql, &alloc, &offset, "SELECT ");
	zbx_snprintf_alloc(sql, &alloc, &offset,
			"to_timestamp("
			"FLOOR((EXTRACT(EPOCH FROM \"Timestamp\")-" ZBX_FS_TIME_T ")/%d)*%d+" ZBX_FS_TIME_T") "
			"AS rounded_time,",
			timestamp_filter_lower_bound, query->granularity, query->granularity,
			timestamp_filter_lower_bound);
	zbx_snprintf_alloc(sql, &alloc, &offset, "EXTRACT(EPOCH FROM MIN(\"Timestamp\"))::bigint AS starttime,");
	if (SUCCEED == query_has_columns)
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
	if (SUCCEED == query_has_conditions)
		zbx_snprintf_alloc(sql, &alloc, &offset, "AND (%s) ", conditions);

	/* group by */
	zbx_snprintf_alloc(sql, &alloc, &offset, "GROUP BY rounded_time%s%s ",
			(SUCCEED == query_has_columns ? "," : ""), columns_to_select);

	/* order by */
	zbx_snprintf_alloc(sql, &alloc, &offset, "ORDER BY rounded_time%s%s;",
			(SUCCEED == query_has_columns ? "," : ""), columns_to_select);

	zbx_free(columns_to_select);
	zbx_free(aggr_columns_to_select);
	zbx_free(table_to_select_from);
	zbx_free(conditions);
}

void	zbx_tq_sql_generate_mysql(const zbx_tq_query_t *query, time_t now, time_t lasttimestamp, char **sql,
		const zbx_dbconn_t *db)
{
	tq_sql_ctx_t	ctx = {
		.db_type = ZBX_TQ_DB_TYPE_MYSQL,
		.db = db,
	};

	const int	query_has_columns = (0 != query->columns.values_num) ? SUCCEED : FAIL;
	const int	query_has_conditions = (0 != query->conditions.values_num) ? SUCCEED : FAIL;
	size_t		alloc = 0, offset = 0;
	time_t		timestamp_filter_lower_bound, timestamp_filter_upper_bound;
	char		*rounded_time_expr;
	char		*columns_to_select;
	char		*used_columns;
	char		*aggr_columns_to_select;
	char		*percentile_ranks;
	char		*table_to_select_from;
	char		*conditions;

	*sql = NULL;

	zbx_tq_get_timestamp_filter_bounds(query, now, lasttimestamp, &timestamp_filter_lower_bound,
			&timestamp_filter_upper_bound);

	rounded_time_expr	= tq_sql_dyn_get_rounded_time_expr_mysql(query->granularity,
			timestamp_filter_lower_bound);
	columns_to_select	= tq_sql_dyn_get_columns_to_select(query, &ctx);
	used_columns		= tq_sql_dyn_get_used_columns_mysql(query, &ctx);
	aggr_columns_to_select	= tq_sql_dyn_get_aggr_columns_to_select(query, &ctx);
	percentile_ranks	= tq_sql_dyn_get_cume_dists_mysql(query, rounded_time_expr, columns_to_select, &ctx);
	table_to_select_from	= tq_sql_dyn_get_table_to_select_from(query, &ctx);
	conditions		= tq_sql_dyn_get_conditions(query, &ctx);


	/* outer select */
	zbx_snprintf_alloc(sql, &alloc, &offset, "SELECT ");
	zbx_snprintf_alloc(sql, &alloc, &offset, "rounded_time,");
	zbx_snprintf_alloc(sql, &alloc, &offset, "UNIX_TIMESTAMP(MIN(`Timestamp`)) AS starttime,");
	if (SUCCEED == query_has_columns)
		zbx_snprintf_alloc(sql, &alloc, &offset, "%s,", columns_to_select);
	zbx_snprintf_alloc(sql, &alloc, &offset, "%s ", aggr_columns_to_select);

	/* inner select */
	zbx_snprintf_alloc(sql, &alloc, &offset, "FROM(SELECT ");
	zbx_snprintf_alloc(sql, &alloc, &offset, "%s AS rounded_time,", rounded_time_expr);
	zbx_snprintf_alloc(sql, &alloc, &offset, "%s%s%s", used_columns,
			(SUCCEED == tq_sql_query_has_percentiles(query) ? "," : ""), percentile_ranks);

	/* inner from */
	zbx_snprintf_alloc(sql, &alloc, &offset, "FROM %s ", table_to_select_from);

	/* inner where */
	zbx_snprintf_alloc(sql, &alloc, &offset, "WHERE ");
	zbx_snprintf_alloc(sql, &alloc, &offset,
			"`Timestamp`>=FROM_UNIXTIME(" ZBX_FS_TIME_T ") "
			"AND `Timestamp`<FROM_UNIXTIME(" ZBX_FS_TIME_T ") ",
			timestamp_filter_lower_bound, timestamp_filter_upper_bound);
	if (SUCCEED == query_has_conditions)
		zbx_snprintf_alloc(sql, &alloc, &offset, "AND (%s) ", conditions);

	zbx_snprintf_alloc(sql, &alloc, &offset, ") AS t ");

	/* group by */
	zbx_snprintf_alloc(sql, &alloc, &offset, "GROUP BY rounded_time%s%s ",
			(SUCCEED == query_has_columns ? "," : ""), columns_to_select);

	/* order by */
	zbx_snprintf_alloc(sql, &alloc, &offset, "ORDER BY rounded_time%s%s;",
			(SUCCEED == query_has_columns ? "," : ""), columns_to_select);

	zbx_free(rounded_time_expr);
	zbx_free(columns_to_select);
	zbx_free(used_columns);
	zbx_free(aggr_columns_to_select);
	zbx_free(percentile_ranks);
	zbx_free(table_to_select_from);
	zbx_free(conditions);
}

void	zbx_tq_sql_generate_clickhouse(const zbx_tq_query_t *query, time_t now, time_t lasttimestamp, char **sql)
{
	tq_sql_ctx_t	ctx = {
		.db_type = ZBX_TQ_DB_TYPE_POSTGRESQL,
		.db = NULL,
	};

	const int	query_has_columns = (0 != query->columns.values_num) ? SUCCEED : FAIL;
	const int	query_has_conditions = (0 != query->conditions.values_num) ? SUCCEED : FAIL;

	size_t	alloc = 0;
	size_t	offset = 0;

	*sql = NULL;

	char	*columns_to_select	= tq_sql_dyn_get_columns_to_select(query, &ctx);
	char	*aggr_columns_to_select	= tq_sql_dyn_get_aggr_columns_to_select(query, &ctx);
	char	*table_to_select_from	= tq_sql_dyn_get_table_to_select_from(query, &ctx);
	char	*conditions		= tq_sql_dyn_get_conditions(query, &ctx);

	time_t timestamp_filter_lower_bound, timestamp_filter_upper_bound;
	zbx_tq_get_timestamp_filter_bounds(query, now, lasttimestamp, &timestamp_filter_lower_bound,
			&timestamp_filter_upper_bound);

	/* select */
	zbx_snprintf_alloc(sql, &alloc, &offset, "SELECT ");
	zbx_snprintf_alloc(sql, &alloc, &offset,
			"toStartOfInterval ("
			"\"Timestamp\" - INTERVAL " ZBX_FS_TIME_T " SECOND, INTERVAL %d SECOND"
			") + INTERVAL " ZBX_FS_TIME_T " SECOND AS rounded_time,",
			timestamp_filter_lower_bound, query->granularity, timestamp_filter_lower_bound);
	zbx_snprintf_alloc(sql, &alloc, &offset, "toUnixTimestamp(MIN(\"Timestamp\")) AS starttime,");
	if (SUCCEED == query_has_columns)
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
	if (SUCCEED == query_has_conditions)
		zbx_snprintf_alloc(sql, &alloc, &offset, "AND (%s) ", conditions);

	/* group by */
	zbx_snprintf_alloc(sql, &alloc, &offset, "GROUP BY rounded_time%s%s ",
			(SUCCEED == query_has_columns ? "," : ""), columns_to_select);

	/* order by */
	zbx_snprintf_alloc(sql, &alloc, &offset, "ORDER BY rounded_time%s%s ",
			(SUCCEED == query_has_columns ? "," : ""), columns_to_select);

	/* format */
	zbx_snprintf_alloc(sql, &alloc, &offset, "FORMAT JSONCompactEachRow;");

	zbx_free(columns_to_select);
	zbx_free(aggr_columns_to_select);
	zbx_free(table_to_select_from);
	zbx_free(conditions);
}
