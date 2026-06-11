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
#include "zbxstr.h"
#include "zbxjson.h"
#include "zbxnum.h"
#include "zbxcommon.h"
#include "zbxtime.h"
#include "zbxalgo.h"

/******************************************************************************
 *                                                                            *
 * Return value: resulting integer value of the enum that out points to       *
 *                                                                            *
 ******************************************************************************/
typedef int	(*tq_string_enum_set_func_t)(const char *str, void *out);

/******************************************************************************
 *                                                                            *
 * Return value: resulting integer value of the enum that out points to       *
 *                                                                            *
 ******************************************************************************/
typedef int	(*tq_int_enum_set_func_t)(int x, void *out);

__zbx_attr_format_printf(4, 5)
static int	ret_errf(int ret, char *error, size_t max_error_len, const char *fmt, ...)
{
	va_list	args;

	va_start(args, fmt);
	zbx_vsnprintf(error, max_error_len, fmt, args);
	va_end(args);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Comments: it is the caller's responsibility to free the string allocated   *
 *           and assigned to out if the function succeeded                    *
 *                                                                            *
 ******************************************************************************/
static int	tq_read_string(const char *p, char **out, const char *tag, char *error, size_t max_error_len)
{
	size_t		out_alloc = 0;
	zbx_json_type_t	type;

	if (NULL != *out)
		return ret_errf(FAIL, error, max_error_len, "Duplicate \"%s\" tag", tag);

	if (NULL == zbx_json_decodevalue_dyn(p, out, &out_alloc, &type) || ZBX_JSON_TYPE_STRING != type)
	{
		zbx_snprintf(error, max_error_len, "Failed to read \"%s\"", tag);
		zbx_free(*out);

		return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Comments: val must be set to the integer value of the enum that out points *
 *           to                                                               *
 *                                                                            *
 ******************************************************************************/
static int	tq_read_string_enum(const char *p, char *buf, size_t buf_size, tq_string_enum_set_func_t set_func,
		void *out, int cur_val, int val_unknown, const char *tag, char *error, size_t max_error_len)
{
	zbx_json_type_t	type;

	if (cur_val != val_unknown)
		return ret_errf(FAIL, error, max_error_len, "Duplicate \"%s\" tag", tag);

	if (NULL == zbx_json_decodevalue(p, buf, buf_size, &type) || ZBX_JSON_TYPE_STRING != type)
		return ret_errf(FAIL, error, max_error_len, "Failed to read \"%s\"", tag);

	if (set_func(buf, out) == val_unknown)
		return ret_errf(FAIL, error, max_error_len, "Invalid \"%s\" value: \"%s\"", tag, buf);

	return SUCCEED;
}

static int	tq_read_int_enum(const char *p, char *buf, size_t buf_size, tq_int_enum_set_func_t set_func,
		void *out, int cur_val, int val_unknown, const char *tag, char *error, size_t max_error_len)
{
	int	new_val;

	if (cur_val != val_unknown)
		return ret_errf(FAIL, error, max_error_len, "Duplicate \"%s\" tag", tag);

	if (NULL == zbx_json_decodevalue(p, buf, buf_size, NULL) || SUCCEED != zbx_is_int(buf, &new_val))
		return ret_errf(FAIL, error, max_error_len, "Failed to read \"%s\"", tag);

	if (set_func(new_val, out) == val_unknown)
		return ret_errf(FAIL, error, max_error_len, "Invalid \"%s\" value: %d", tag, new_val);

	return SUCCEED;
}

#define TQ_STRING_ENUM_SET_FUNC_DEF(__set_func_name, __match_func, __type)	\
static int	__set_func_name(const char *__str, void *__out)	\
{								\
	return (int)(*(__type *)__out = __match_func(__str));	\
}

#define TQ_INT_ENUM_SET_FUNC_DEF(__set_func_name, __match_func, __type)	\
static int	__set_func_name(int __x, void *__out)		\
{								\
	return (int)(*(__type *)__out = __match_func(__x));	\
}

static zbx_tq_category_t	tq_match_category(int x)
{
	switch (x)
	{
		case ZBX_TQ_CATEGORY_APM_TRACES:
		case ZBX_TQ_CATEGORY_APM_METRICS:
		case ZBX_TQ_CATEGORY_APM_LOGS:
			return x;
		default:
			return ZBX_TQ_CATEGORY_UNKNOWN;
	}
}

TQ_INT_ENUM_SET_FUNC_DEF(tq_set_category, tq_match_category, zbx_tq_category_t)

static zbx_tq_metric_type_t	tq_match_metric_type(int x)
{
	switch (x)
	{
		case ZBX_TQ_METRIC_TYPE_SUM:
		case ZBX_TQ_METRIC_TYPE_GAUGE:
		case ZBX_TQ_METRIC_TYPE_HISTOGRAM:
		case ZBX_TQ_METRIC_TYPE_EXPONENTIAL_HISTOGRAM:
			return x;
		default:
			return ZBX_TQ_METRIC_TYPE_UNKNOWN;
	}
}

TQ_INT_ENUM_SET_FUNC_DEF(tq_set_metric_type, tq_match_metric_type, zbx_tq_metric_type_t)

static zbx_tq_function_type_t	tq_match_function_type(const char *str)
{
	if (0 == strcmp(str, "count"))
		return ZBX_TQ_FUNCTION_COUNT;
	else if (0 == strcmp(str, "min"))
		return ZBX_TQ_FUNCTION_MIN;
	else if (0 == strcmp(str, "max"))
		return ZBX_TQ_FUNCTION_MAX;
	else if (0 == strcmp(str, "avg"))
		return ZBX_TQ_FUNCTION_AVG;
	else if (0 == strcmp(str, "sum"))
		return ZBX_TQ_FUNCTION_SUM;
	else if (0 == strcmp(str, "percentile"))
		return ZBX_TQ_FUNCTION_PERCENTILE;

	return ZBX_TQ_FUNCTION_UNKNOWN;
}

TQ_STRING_ENUM_SET_FUNC_DEF(tq_set_function_type, tq_match_function_type, zbx_tq_function_type_t)

static zbx_tq_eval_type_t	tq_match_eval_type(int x)
{
	switch (x)
	{
		case ZBX_TQ_EVAL_TYPE_AND_OR:
		case ZBX_TQ_EVAL_TYPE_AND:
		case ZBX_TQ_EVAL_TYPE_OR:
		case ZBX_TQ_EVAL_TYPE_EXPRESSION:
			return x;
		default:
			return ZBX_TQ_EVAL_TYPE_UNKNOWN;
	}
}

TQ_INT_ENUM_SET_FUNC_DEF(tq_set_eval_type, tq_match_eval_type, zbx_tq_eval_type_t)

static zbx_tq_operator_t	tq_match_operator(int x)
{
	switch (x)
	{
		case ZBX_TQ_OPERATOR_EQUAL:
		case ZBX_TQ_OPERATOR_NOT_EQUAL:
		case ZBX_TQ_OPERATOR_CONTAINS:
		case ZBX_TQ_OPERATOR_NOT_CONTAINS:
		case ZBX_TQ_OPERATOR_EXISTS:
			return x;
		default:
			return ZBX_TQ_OPERATOR_UNKNOWN;
	}
}

TQ_INT_ENUM_SET_FUNC_DEF(tq_set_operator, tq_match_operator, zbx_tq_operator_t)

static int	tq_parse_col(struct zbx_json_parse *jp, zbx_tq_column_t *col, char *buf, size_t buf_size, char *error,
		size_t max_error_len)
{
	int		ret = FAIL;
	const char	*p = NULL;

	zabbix_log(LOG_LEVEL_TRACE, "In %s()", __func__);

	tq_column_init(col);

	while (NULL != (p = zbx_json_pair_next(jp, p, buf, buf_size)))
	{
		if (0 == strcmp(buf, "column"))
		{
			if (FAIL == tq_read_string(p, &col->name, "column", error, max_error_len))
				goto out;
		}
		else if (0 == strcmp(buf, "attribute_key"))
		{
			if (FAIL == tq_read_string(p, &col->key, "attribute_key", error, max_error_len))
				goto out;
		}
		else
		{
			zabbix_log(LOG_LEVEL_WARNING, "%s(): unknown tag: \"%s\"", __func__, buf);
		}
	}

	ret = SUCCEED;
out:
	if (FAIL == ret)
		tq_column_clean(col);

	zabbix_log(LOG_LEVEL_TRACE, "End of %s() ret:%d", __func__, ret);

	return ret;
}

static int	tq_parse_columns(struct zbx_json_parse *jp, zbx_vector_tq_column_t *columns, char *buf, size_t buf_size,
		char *error, size_t max_error_len)
{
	/* in case of an error the columns vector is cleaned by the calling function */
	int		ret = FAIL;
	const char	*p = NULL;

	zabbix_log(LOG_LEVEL_TRACE, "In %s()", __func__);

	while (NULL != (p = zbx_json_next(jp, p)))
	{
		struct zbx_json_parse	jp_elem;

		if (FAIL == zbx_json_brackets_open(p, &jp_elem))
			goto out;

		zbx_tq_column_t	col;
		if (FAIL == tq_parse_col(&jp_elem, &col, buf, buf_size, error, max_error_len))
			goto out;

		zbx_vector_tq_column_append(columns, col);
	}

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_TRACE, "End of %s() ret:%d", __func__, ret);

	return ret;
}

static int	tq_parse_function_args(struct zbx_json_parse *jp, zbx_vector_str_t *args, char *buf, size_t buf_size)
{
	/* in case of an error the args vector is cleaned by the calling function */
	int		ret = FAIL;
	const char	*p = NULL;
	zbx_json_type_t	type;

	zabbix_log(LOG_LEVEL_TRACE, "In %s()", __func__);

	while (NULL != (p = zbx_json_next(jp, p)))
	{
		if (NULL == zbx_json_decodevalue(p, buf, buf_size, &type) || ZBX_JSON_TYPE_STRING != type)
			goto out;

		zbx_vector_str_append(args, zbx_strdup(NULL, buf));
	}

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_TRACE, "End of %s() ret:%d", __func__, ret);

	return ret;
}

static int	tq_parse_aggr_col(struct zbx_json_parse *jp, zbx_tq_aggr_column_t *aggr_col, char *buf, size_t buf_size,
		char *error, size_t max_error_len)
{
	int		ret = FAIL;
	const char	*p = NULL;

	zabbix_log(LOG_LEVEL_TRACE, "In %s()", __func__);

	tq_aggr_column_init(aggr_col);

	while (NULL != (p = zbx_json_pair_next(jp, p, buf, buf_size)))
	{
		if (0 == strcmp(buf, "column"))
		{
			if (FAIL == tq_read_string(p, &aggr_col->column_name, "column", error, max_error_len))
				goto out;
		}
		else if (0 == strcmp(buf, "function"))
		{
			if (FAIL == tq_read_string_enum(p, buf, buf_size, tq_set_function_type, &aggr_col->function,
				aggr_col->function, ZBX_TQ_FUNCTION_UNKNOWN, "function", error, max_error_len))
				goto out;
		}
		else if (0 == strcmp(buf, "parameters"))
		{
			if (0 != aggr_col->args.values_num)
			{
				zbx_snprintf(error, max_error_len, "Duplicate \"parameters\" tag");
				goto out;
			}

			struct zbx_json_parse	jp_args;
			if (FAIL == zbx_json_brackets_open(p, &jp_args))
				goto out;
			if (FAIL == tq_parse_function_args(&jp_args, &aggr_col->args, buf, buf_size))
				goto out;
		}
		else if (0 == strcmp(buf, "alias"))
		{
			if (FAIL == tq_read_string(p, &aggr_col->alias, "alias", error, max_error_len))
				goto out;
		}
		else
		{
			zabbix_log(LOG_LEVEL_WARNING, "%s(): unknown tag: \"%s\"", __func__, buf);
		}
	}

	ret = SUCCEED;
out:
	if (FAIL == ret)
		tq_aggr_column_clean(aggr_col);

	zabbix_log(LOG_LEVEL_TRACE, "End of %s() ret:%d", __func__, ret);

	return ret;
}

static int	tq_parse_aggregated_columns(struct zbx_json_parse *jp, zbx_vector_tq_aggr_column_t *aggregated_columns,
		char *buf, size_t buf_size, char *error, size_t max_error_len)
{
	/* in case of an error the aggregated_columns vector is cleaned by the calling function */
	int		ret = FAIL;
	const char	*p = NULL;

	zabbix_log(LOG_LEVEL_TRACE, "In %s()", __func__);

	while (NULL != (p = zbx_json_next(jp, p)))
	{
		struct zbx_json_parse	jp_elem;

		if (FAIL == zbx_json_brackets_open(p, &jp_elem))
			goto out;

		zbx_tq_aggr_column_t	aggr_col;
		if (FAIL == tq_parse_aggr_col(&jp_elem, &aggr_col, buf, buf_size, error, max_error_len))
			goto out;

		zbx_vector_tq_aggr_column_append(aggregated_columns, aggr_col);
	}

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_TRACE, "End of %s() ret:%d", __func__, ret);

	return ret;
}

static int	tq_parse_cond(struct zbx_json_parse *jp, zbx_tq_condition_t *cond, char *buf, size_t buf_size,
		char *error, size_t max_error_len)
{
	int		ret = FAIL;
	const char	*p = NULL;

	zabbix_log(LOG_LEVEL_TRACE, "In %s()", __func__);

	tq_condition_init(cond);

	while (NULL != (p = zbx_json_pair_next(jp, p, buf, buf_size)))
	{
		if (0 == strcmp(buf, "column"))
		{
			if (FAIL == tq_read_string(p, &cond->column_name, "column", error, max_error_len))
				goto out;
		}
		else if (0 == strcmp(buf, "attribute_key"))
		{
			if (FAIL == tq_read_string(p, &cond->key, "attribute_key", error, max_error_len))
				goto out;
		}
		else if (0 == strcmp(buf, "value"))
		{
			if (FAIL == tq_read_string(p, &cond->value, "value", error, max_error_len))
				goto out;
		}
		else if (0 == strcmp(buf, "operator"))
		{
			if (FAIL == tq_read_int_enum(p, buf, buf_size, tq_set_operator, &cond->operator, cond->operator,
					ZBX_TQ_OPERATOR_UNKNOWN, "operator", error, max_error_len))
				goto out;
		}
		else
		{
			zabbix_log(LOG_LEVEL_WARNING, "%s(): unknown tag: \"%s\"", __func__, buf);
		}
	}

	ret = SUCCEED;
out:
	if (FAIL == ret)
		tq_condition_clean(cond);

	zabbix_log(LOG_LEVEL_TRACE, "End of %s() ret:%d", __func__, ret);

	return ret;
}

static int	tq_parse_conditions(struct zbx_json_parse *jp, zbx_vector_tq_condition_t *conditions, char *buf,
		size_t buf_size, char *error, size_t max_error_len)
{
	/* in case of an error the conditions vector is cleaned by the calling function */
	int		ret = FAIL;
	const char	*p = NULL;

	zabbix_log(LOG_LEVEL_TRACE, "In %s()", __func__);

	while (NULL != (p = zbx_json_next(jp, p)))
	{
		struct zbx_json_parse	jp_elem;
		zbx_tq_condition_t	cond;

		if (FAIL == zbx_json_brackets_open(p, &jp_elem))
			goto out;

		if (FAIL == tq_parse_cond(&jp_elem, &cond, buf, buf_size, error, max_error_len))
			goto out;

		zbx_vector_tq_condition_append(conditions, cond);
	}

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_TRACE, "End of %s() ret:%d", __func__, ret);

	return ret;
}

static int	tq_parse_filter(struct zbx_json_parse *jp, zbx_tq_query_t *query, char *buf, size_t buf_size,
		char *error, size_t max_error_len)
{
	/* in case of an error the query is cleaned by the calling function */
	int		ret = FAIL;
	const char	*p = NULL;

	zabbix_log(LOG_LEVEL_TRACE, "In %s()", __func__);

	while (NULL != (p = zbx_json_pair_next(jp, p, buf, buf_size)))
	{
		if (0 == strcmp(buf, "evaltype"))
		{
			if (FAIL == tq_read_int_enum(p, buf, buf_size, tq_set_eval_type, &query->evaltype,
					query->evaltype, ZBX_TQ_EVAL_TYPE_UNKNOWN, "evaltype", error, max_error_len))
				goto out;
		}
		else if (0 == strcmp(buf, "formula"))
		{
			if (FAIL == tq_read_string(p, &query->formula, "formula", error, max_error_len))
				goto out;
		}
		else if (0 == strcmp(buf, "conditions"))
		{
			struct zbx_json_parse	jp_conditions;

			if (0 != query->conditions.values_num)
			{
				zbx_snprintf(error, max_error_len, "Duplicate \"conditions\" tag");
				goto out;
			}

			if (FAIL == zbx_json_brackets_open(p, &jp_conditions))
				goto out;
			if (FAIL == tq_parse_conditions(&jp_conditions, &query->conditions, buf, buf_size, error,
					max_error_len))
				goto out;
		}
		else
		{
			zabbix_log(LOG_LEVEL_WARNING, "%s(): unknown tag: \"%s\"", __func__, buf);
		}
	}

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_TRACE, "End of %s() ret:%d", __func__, ret);

	return ret;
}

static int tq_expand_macros(char **text, zbx_tq_macro_expand_func_t macro_expand_cb, void *macro_expand_ctx)
{
	if (NULL == *text)
		return SUCCEED;

	return macro_expand_cb(text, macro_expand_ctx);
}

static int	tq_parse_query(struct zbx_json_parse *jp, zbx_tq_query_t *query,
		zbx_tq_macro_expand_func_t macro_expand_cb, void *macro_expand_ctx, char *error, size_t max_error_len)
{
	/* in case of an error the query is cleaned by the calling function */
	int		ret = FAIL;
	char		buf[MAX_STRING_LEN];
	size_t		buf_size = sizeof(buf);
	const char	*p = NULL;

	zabbix_log(LOG_LEVEL_TRACE, "In %s()", __func__);

	while (NULL != (p = zbx_json_pair_next(jp, p, buf, buf_size)))
	{
		if (0 == strcmp(buf, "signal_type"))
		{
			if (FAIL == tq_read_int_enum(p, buf, buf_size, tq_set_category, &query->category,
					query->category, ZBX_TQ_CATEGORY_UNKNOWN, "signal_type", error, max_error_len))
				goto out;
		}
		else if (0 == strcmp(buf, "metric_point_type"))
		{
			if (FAIL == tq_read_int_enum(p, buf, buf_size, tq_set_metric_type, &query->metric_type,
					query->metric_type, ZBX_TQ_METRIC_TYPE_UNKNOWN, "metric_point_type", error,
					max_error_len))
				goto out;
		}
		else if (0 == strcmp(buf, "columns"))
		{
			struct zbx_json_parse	jp_columns;

			if (0 != query->columns.values_num)
			{
				zbx_snprintf(error, max_error_len, "Duplicate \"columns\" tag");
				goto out;
			}

			if (FAIL == zbx_json_brackets_open(p, &jp_columns))
				goto out;
			if (FAIL == tq_parse_columns(&jp_columns, &query->columns, buf, buf_size, error, max_error_len))
				goto out;
		}
		else if (0 == strcmp(buf, "aggregated_columns"))
		{
			struct zbx_json_parse	jp_aggr_columns;

			if (0 != query->aggregated_columns.values_num)
			{
				zbx_snprintf(error, max_error_len, "Duplicate \"aggregated_columns\" tag");
				goto out;
			}

			if (FAIL == zbx_json_brackets_open(p, &jp_aggr_columns))
				goto out;
			if (FAIL == tq_parse_aggregated_columns(&jp_aggr_columns, &query->aggregated_columns, buf,
					buf_size, error, max_error_len))
				goto out;
		}
		else if (0 == strcmp(buf, "filter"))
		{
			struct zbx_json_parse	jp_filter;

			if (FAIL == zbx_json_brackets_open(p, &jp_filter))
				goto out;
			if (FAIL == tq_parse_filter(&jp_filter, query, buf, buf_size, error, max_error_len))
				goto out;
		}
		else
		{
			zabbix_log(LOG_LEVEL_WARNING, "%s(): unknown tag: \"%s\"", __func__, buf);
		}
	}

	if (NULL != macro_expand_cb)
	{
		for (int i = 0; i < query->columns.values_num; i++)
		{
			if (SUCCEED != tq_expand_macros(&query->columns.values[i].key, macro_expand_cb,
					macro_expand_ctx))
				goto out;
		}

		for (int i = 0; i < query->aggregated_columns.values_num; i++)
		{
			zbx_tq_aggr_column_t *aggr_col = &query->aggregated_columns.values[i];

			if (SUCCEED != tq_expand_macros(&aggr_col->alias, macro_expand_cb, macro_expand_ctx))
				goto out;

			for (int j = 0; j < aggr_col->args.values_num; j++)
			{
				if (SUCCEED != tq_expand_macros(&aggr_col->args.values[j], macro_expand_cb,
						macro_expand_ctx))
					goto out;
			}
		}

		for (int i = 0; i < query->conditions.values_num; i++) {
			if (SUCCEED != tq_expand_macros(&query->conditions.values[i].key, macro_expand_cb,
					macro_expand_ctx))
				goto out;
			if (SUCCEED != tq_expand_macros(&query->conditions.values[i].value, macro_expand_cb,
					macro_expand_ctx))
				goto out;
		}
	}

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_TRACE, "End of %s() ret:%d", __func__, ret);

	return ret;
}

static int	tq_validate_str(const char *str)
{
	return NULL == str || SUCCEED == zbx_is_utf8(str) ? SUCCEED : FAIL;
}

static void	tq_set_column_types(zbx_tq_query_t *query)
{
	for (int i = 0; i < query->columns.values_num; i++)
	{
		zbx_tq_column_t	*col = &query->columns.values[i];

		col->col_type = tq_get_column_type(query->category, query->metric_type, col->name);
	}

	for (int i = 0; i < query->aggregated_columns.values_num; i++)
	{
		zbx_tq_aggr_column_t *aggr_col = &query->aggregated_columns.values[i];

		aggr_col->col_type = tq_get_column_type(query->category, query->metric_type, aggr_col->column_name);
	}

	for (int i = 0; i < query->conditions.values_num; i++)
	{
		zbx_tq_condition_t	*condition = &query->conditions.values[i];

		condition->col_type = tq_get_column_type(query->category, query->metric_type, condition->column_name);
	}
}

static int	tq_validate_formula_node_indices(const zbx_tq_formula_node_t *node, int condition_count, char *error,
		size_t max_error_len)
{
	if (TQ_FORMULA_NODE_TYPE_LEAF == node->type)
	{
		if (0 > node->condition_idx || node->condition_idx >= condition_count)
			return ret_errf(FAIL, error, max_error_len, "Invalid condition id in formula: %d",
					node->condition_idx);

		return SUCCEED;
	}


	for (int i = 0; i < node->children.values_num; i++)
	{
		if (SUCCEED != tq_validate_formula_node_indices(node->children.values[i], condition_count, error,
				max_error_len))
			return FAIL;
	}

	return SUCCEED;
}

static int	tq_validate_query(const zbx_tq_query_t *query, char *error, size_t max_error_len)
{
	if (ZBX_TQ_CATEGORY_UNKNOWN == query->category)
		return ret_errf(FAIL, error, max_error_len, "Category is not set");

	if (ZBX_TQ_CATEGORY_APM_METRICS == query->category && ZBX_TQ_METRIC_TYPE_UNKNOWN == query->metric_type)
		return ret_errf(FAIL, error, max_error_len,
				"Metric type is not set (when category is \"APM metrics\")");

	for (int i = 0; i < query->columns.values_num; i++)
	{
		const zbx_tq_column_t	*col = &query->columns.values[i];

		if (NULL == col->name)
			return ret_errf(FAIL, error, max_error_len, "Column name is not set for column #%d", i);

		if (ZBX_TQ_COLUMN_TYPE_UNKNOWN == col->col_type)
			return ret_errf(FAIL, error, max_error_len, "Column name is invalid for column #%d", i);

		if (NULL == col->key && SUCCEED == tq_column_type_is_attributes(col->col_type))
			return ret_errf(FAIL, error, max_error_len, "Key is not set for attribute column #%d", i);

		if (FAIL == tq_validate_str(col->key))
			return ret_errf(FAIL, error, max_error_len, "Key is not valid UTF-8 for column #%d", i);
	}

	if (0 == query->aggregated_columns.values_num)
		return ret_errf(FAIL, error, max_error_len, "Aggregated columns are not set");

	for (int i = 0; i < query->aggregated_columns.values_num; i++)
	{
		const zbx_tq_aggr_column_t *aggr_col = &query->aggregated_columns.values[i];

		if (ZBX_TQ_FUNCTION_COUNT != aggr_col->function)
		{
			if (NULL == aggr_col->column_name)
				return ret_errf(FAIL, error, max_error_len,
					"Column name is not set for aggregated column #%d", i);

			if (ZBX_TQ_COLUMN_TYPE_UNKNOWN == aggr_col->col_type)
				return ret_errf(FAIL, error, max_error_len,
						"Column name is invalid for aggregated column #%d", i);

			if (ZBX_TQ_COLUMN_TYPE_NUM != aggr_col->col_type)
				return ret_errf(FAIL, error, max_error_len,
						"Invalid column type for aggregated column #%d", i);
		}

		if (NULL == aggr_col->alias)
			return ret_errf(FAIL, error, max_error_len, "Alias is not set for aggregated column #%d", i);

		if (FAIL == tq_validate_str(aggr_col->alias))
			return ret_errf(FAIL, error, max_error_len, "Alias is not valid UTF-8 for column #%d", i);

		if (ZBX_TQ_FUNCTION_UNKNOWN == aggr_col->function)
			return ret_errf(FAIL, error, max_error_len, "Function is not set for aggregated column #%d", i);

		if (ZBX_TQ_FUNCTION_PERCENTILE == aggr_col->function)
		{
			double	x;

			/* TODO: check if 0 and 100 (and other edge values) work on all dbs */
			if (1 != aggr_col->args.values_num || NULL == aggr_col->args.values[0] ||
					FAIL == zbx_is_double(aggr_col->args.values[0], &x) || x < 0.0 || x > 100.0)
				return ret_errf(FAIL, error, max_error_len, "Invalid arguments for \"percentile\" "
						"function in aggregated column #%d", i);
		}
	}

	if (0 != query->conditions.values_num)
	{
		if (ZBX_TQ_EVAL_TYPE_UNKNOWN == query->evaltype)
			return ret_errf(FAIL, error, max_error_len, "Evaltype is not set");

		if (ZBX_TQ_EVAL_TYPE_EXPRESSION == query->evaltype)
		{
			if (NULL == query->formula_parsed)
				return ret_errf(FAIL, error, max_error_len,
						"Formula is not set (when evaltype is \"expression\")");

			if (SUCCEED != tq_validate_formula_node_indices(query->formula_parsed,
					query->conditions.values_num, error, max_error_len))
				return FAIL;
		}

		for (int i = 0; i < query->conditions.values_num; i++)
		{
			const zbx_tq_condition_t	*condition = &query->conditions.values[i];

			if (NULL == condition->column_name)
				return ret_errf(FAIL, error, max_error_len,
						"Column name is not set for condition #%d", i);

			if (ZBX_TQ_COLUMN_TYPE_UNKNOWN == condition->col_type)
				return ret_errf(FAIL, error, max_error_len,
						"Column name is invalid for condition #%d", i);

			if (ZBX_TQ_OPERATOR_UNKNOWN == condition->operator)
				return ret_errf(FAIL, error, max_error_len, "Operator is not set for condition #%d", i);

			if (NULL == condition->key && SUCCEED == tq_column_type_is_attributes(condition->col_type))
				return ret_errf(FAIL, error, max_error_len,
						"Key is not set for attribute condition #%d", i);

			if (FAIL == tq_validate_str(condition->key))
				return ret_errf(FAIL, error, max_error_len,
						"Key is not valid UTF-8 for condition #%d", i);

			if (NULL == condition->value && ZBX_TQ_OPERATOR_EXISTS != condition->operator)
				return ret_errf(FAIL, error, max_error_len, "value is not set for condition #%d", i);

			if (FAIL == tq_validate_str(condition->value))
				return ret_errf(FAIL, error, max_error_len,
						"Value is not valid UTF-8 for condition #%d", i);

			if (ZBX_TQ_OPERATOR_EXISTS == condition->operator
					&& SUCCEED != tq_column_type_is_attributes(condition->col_type))
				return ret_errf(FAIL, error, max_error_len,
						"Operator \"exists\" selected for non-attribute condition #%d", i);

			if (SUCCEED == tq_column_type_is_attributes(condition->col_type) &&
					(ZBX_TQ_OPERATOR_CONTAINS == condition->operator ||
					ZBX_TQ_OPERATOR_NOT_CONTAINS == condition->operator))
				return ret_errf(FAIL, error, max_error_len,
						"Operator \"%s\" selected for attribute condition #%d",
						(ZBX_TQ_OPERATOR_CONTAINS == condition->operator ? "contains" :
						"not contains"), i);
		}
	}

	if (TQ_TIME_INTERVAL_INVALID == query->time_shift)
		return ret_errf(FAIL, error, max_error_len, "Time shift is not set");

	if (TQ_TIME_INTERVAL_INVALID == query->lookback_limit)
		return ret_errf(FAIL, error, max_error_len, "Lookback limit is not set");

	if (TQ_TIME_INTERVAL_INVALID == query->granularity)
		return ret_errf(FAIL, error, max_error_len, "Aggregation size is not set");

	if (0 == query->granularity)
		return ret_errf(FAIL, error, max_error_len, "Aggregation size cannot be 0");

	if (query->granularity > query->lookback_limit)
		return ret_errf(FAIL, error, max_error_len, "Aggregation size cannot be larger than lookback limit");

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: parses json_str contents and stores them into query and validates *
 *          it                                                                *
 *                                                                            *
 * Parameters: query            - [OUT] parsed query, must not be initialized *
 *                                     beforehand                             *
 *             query_json       - [IN]                                        *
 *             time_shift       - [IN]                                        *
 *             lookback_limit   - [IN]                                        *
 *             granularity      - [IN]                                        *
 *             macro_expand_cb  - [IN] NULL or callback called on every field *
 *                                     in query where macros are supported    *
 *                                     (macros in time_shift, lookback_limit  *
 *                                     and granularity are not expanded)      *
 *             macro_expand_ctx - [IN] context passed to macro_expand_cb      *
 *             error            - [OUT]                                       *
 *             max_error_len    - [IN]                                        *
 *                                                                            *
 * Return value: SUCCEED - json_str parsed successfully, query is valid;      *
 *                         query must be cleaned after use                    *
 *               FAIL    - otherwise; query is not initialized                *
 *                                                                            *
 ******************************************************************************/
int	zbx_tq_parse_query(zbx_tq_query_t *query, const char *query_json, const char *time_shift,
		const char *lookback_limit, const char *granularity, zbx_tq_macro_expand_func_t macro_expand_cb,
		void *macro_expand_ctx, char *error, size_t max_error_len)
{
	int			ret = FAIL;
	struct zbx_json_parse	jp;

	zabbix_log(LOG_LEVEL_TRACE, "In %s()", __func__);

	if (0 != max_error_len)
		error[0] = '\0';

	tq_query_init(query);

	if (FAIL == zbx_json_open(query_json, &jp))
	{
		zabbix_log(LOG_LEVEL_ERR, "%s(): Cannot open JSON: %s", __func__, zbx_json_strerror());
		zbx_snprintf(error, max_error_len, "Cannot open query JSON");
		goto out;
	}

	if (FAIL == tq_parse_query(&jp, query, macro_expand_cb, macro_expand_ctx, error, max_error_len))
		goto out;

	if (NULL != time_shift)
	{
		if (FAIL == zbx_is_time_suffix(time_shift, &query->time_shift, ZBX_LENGTH_UNLIMITED))
		{
			zbx_snprintf(error, max_error_len, "Unsupported time shift value");
			goto out;
		}
	}

	if (NULL != lookback_limit)
	{
		if (FAIL == zbx_is_time_suffix(lookback_limit, &query->lookback_limit, ZBX_LENGTH_UNLIMITED))
		{
			zbx_snprintf(error, max_error_len, "Unsupported lookback limit value");
			goto out;
		}
	}

	if (NULL != granularity)
	{
		if (FAIL == zbx_is_time_suffix(granularity, &query->granularity, ZBX_LENGTH_UNLIMITED))
		{
			zbx_snprintf(error, max_error_len, "Unsupported granularity limit value");
			goto out;
		}
	}

	tq_set_column_types(query);

	if (NULL != query->formula)
	{
		if (NULL == (query->formula_parsed = tq_formula_parse(query->formula, error, max_error_len)))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "Failed to parse formula: %s", error);
			zbx_snprintf(error, max_error_len, "Failed to parse formula");
			goto out;
		}
	}

	if (FAIL == tq_validate_query(query, error, max_error_len))
		goto out;

	ret = SUCCEED;
out:
	if (FAIL == ret)
		zbx_tq_query_clean(query);

	if (FAIL == ret && 0 != max_error_len && '\0' == error[0])
		zbx_snprintf(error, max_error_len, "Failed to parse query");

	zabbix_log(LOG_LEVEL_TRACE, "End of %s() ret:%d", __func__, ret);

	return ret;
}
