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

#define TQ_INT_ENUM_SET_FUNC_DEF(__set_func_name, __match_func, __type)	\
static int	__set_func_name(int __x, void *__out)		\
{								\
	return (int)(*(__type *)__out = __match_func(__x));	\
}

static zbx_tq_signal_type_t	tq_match_signal_type(int x)
{
	switch (x)
	{
		case ZBX_TQ_SIGNAL_TYPE_APM_TRACES:
		case ZBX_TQ_SIGNAL_TYPE_APM_METRICS:
		case ZBX_TQ_SIGNAL_TYPE_APM_LOGS:
			return x;
		default:
			return ZBX_TQ_SIGNAL_TYPE_UNKNOWN;
	}
}

TQ_INT_ENUM_SET_FUNC_DEF(tq_set_signal_type, tq_match_signal_type, zbx_tq_signal_type_t)

static zbx_tq_metric_point_type_t	tq_match_metric_point_type(int x)
{
	switch (x)
	{
		case ZBX_TQ_METRIC_POINT_TYPE_SUM:
		case ZBX_TQ_METRIC_POINT_TYPE_GAUGE:
		case ZBX_TQ_METRIC_POINT_TYPE_HISTOGRAM:
		case ZBX_TQ_METRIC_POINT_TYPE_EXPONENTIAL_HISTOGRAM:
			return x;
		default:
			return ZBX_TQ_METRIC_POINT_TYPE_UNKNOWN;
	}
}

TQ_INT_ENUM_SET_FUNC_DEF(tq_set_metric_point_type, tq_match_metric_point_type, zbx_tq_metric_point_type_t)

static zbx_tq_function_type_t	tq_match_function_type(int x)
{
	switch (x)
	{
		case ZBX_TQ_FUNCTION_COUNT:
		case ZBX_TQ_FUNCTION_MIN:
		case ZBX_TQ_FUNCTION_MAX:
		case ZBX_TQ_FUNCTION_AVG:
		case ZBX_TQ_FUNCTION_SUM:
		case ZBX_TQ_FUNCTION_PERCENTILE:
			return x;
		default:
			return ZBX_TQ_FUNCTION_UNKNOWN;
	}
}

TQ_INT_ENUM_SET_FUNC_DEF(tq_set_function_type, tq_match_function_type, zbx_tq_function_type_t)

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
		if (0 == strcmp(buf, ZBX_TQ_QUERY_TAG_COLUMN))
		{
			if (FAIL == tq_read_string(p, &col->column, ZBX_TQ_QUERY_TAG_COLUMN, error, max_error_len))
				goto out;
		}
		else if (0 == strcmp(buf, ZBX_TQ_QUERY_TAG_ATTRIBUTE_KEY))
		{
			if (FAIL == tq_read_string(p, &col->attribute_key, ZBX_TQ_QUERY_TAG_ATTRIBUTE_KEY, error,
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

static int	tq_parse_function_parameters(struct zbx_json_parse *jp, zbx_vector_str_t *parameters, char *buf,
		size_t buf_size)
{
	/* in case of an error the parameters vector is cleaned by the calling function */
	int		ret = FAIL;
	const char	*p = NULL;
	zbx_json_type_t	type;

	zabbix_log(LOG_LEVEL_TRACE, "In %s()", __func__);

	while (NULL != (p = zbx_json_next(jp, p)))
	{
		if (NULL == zbx_json_decodevalue(p, buf, buf_size, &type) || ZBX_JSON_TYPE_STRING != type)
			goto out;

		zbx_vector_str_append(parameters, zbx_strdup(NULL, buf));
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
		if (0 == strcmp(buf, ZBX_TQ_QUERY_TAG_COLUMN))
		{
			if (FAIL == tq_read_string(p, &aggr_col->column, ZBX_TQ_QUERY_TAG_COLUMN, error,
					max_error_len))
				goto out;
		}
		else if (0 == strcmp(buf, ZBX_TQ_QUERY_TAG_FUNCTION))
		{
			if (FAIL == tq_read_int_enum(p, buf, buf_size, tq_set_function_type, &aggr_col->function,
				aggr_col->function, ZBX_TQ_FUNCTION_UNKNOWN, ZBX_TQ_QUERY_TAG_FUNCTION, error,
						max_error_len))
				goto out;
		}
		else if (0 == strcmp(buf, ZBX_TQ_QUERY_TAG_PARAMETERS))
		{
			if (0 != aggr_col->parameters.values_num)
			{
				zbx_snprintf(error, max_error_len, "Duplicate \"%s\" tag", ZBX_TQ_QUERY_TAG_PARAMETERS);
				goto out;
			}

			struct zbx_json_parse	jp_params;
			if (FAIL == zbx_json_brackets_open(p, &jp_params))
				goto out;
			if (FAIL == tq_parse_function_parameters(&jp_params, &aggr_col->parameters, buf, buf_size))
				goto out;
		}
		else if (0 == strcmp(buf, ZBX_TQ_QUERY_TAG_ALIAS))
		{
			if (FAIL == tq_read_string(p, &aggr_col->alias, ZBX_TQ_QUERY_TAG_ALIAS, error, max_error_len))
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
		if (0 == strcmp(buf, ZBX_TQ_QUERY_TAG_COLUMN))
		{
			if (FAIL == tq_read_string(p, &cond->column, ZBX_TQ_QUERY_TAG_COLUMN, error,
					max_error_len))
				goto out;
		}
		else if (0 == strcmp(buf, ZBX_TQ_QUERY_TAG_ATTRIBUTE_KEY))
		{
			if (FAIL == tq_read_string(p, &cond->attribute_key, ZBX_TQ_QUERY_TAG_ATTRIBUTE_KEY, error,
					max_error_len))
				goto out;
		}
		else if (0 == strcmp(buf, ZBX_TQ_QUERY_TAG_VALUE))
		{
			if (FAIL == tq_read_string(p, &cond->value, ZBX_TQ_QUERY_TAG_VALUE, error, max_error_len))
				goto out;
		}
		else if (0 == strcmp(buf, ZBX_TQ_QUERY_TAG_OPERATOR))
		{
			if (FAIL == tq_read_int_enum(p, buf, buf_size, tq_set_operator, &cond->operator, cond->operator,
					ZBX_TQ_OPERATOR_UNKNOWN, ZBX_TQ_QUERY_TAG_OPERATOR, error, max_error_len))
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
		if (0 == strcmp(buf, ZBX_TQ_QUERY_TAG_EVALTYPE))
		{
			if (FAIL == tq_read_int_enum(p, buf, buf_size, tq_set_eval_type, &query->evaltype,
					query->evaltype, ZBX_TQ_EVAL_TYPE_UNKNOWN, ZBX_TQ_QUERY_TAG_EVALTYPE, error,
					max_error_len))
				goto out;
		}
		else if (0 == strcmp(buf, ZBX_TQ_QUERY_TAG_FORMULA))
		{
			if (FAIL == tq_read_string(p, &query->formula, ZBX_TQ_QUERY_TAG_FORMULA, error, max_error_len))
				goto out;
		}
		else if (0 == strcmp(buf, ZBX_TQ_QUERY_TAG_CONDITIONS))
		{
			struct zbx_json_parse	jp_conditions;

			if (0 != query->conditions.values_num)
			{
				zbx_snprintf(error, max_error_len, "Duplicate \"%s\" tag", ZBX_TQ_QUERY_TAG_CONDITIONS);
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

static int	tq_parse_query(struct zbx_json_parse *jp, zbx_tq_query_t *query, char *error, size_t max_error_len)
{
	/* in case of an error the query is cleaned by the calling function */
	int		ret = FAIL;
	char		buf[MAX_STRING_LEN];
	size_t		buf_size = sizeof(buf);
	const char	*p = NULL;

	zabbix_log(LOG_LEVEL_TRACE, "In %s()", __func__);

	while (NULL != (p = zbx_json_pair_next(jp, p, buf, buf_size)))
	{
		if (0 == strcmp(buf, ZBX_TQ_QUERY_TAG_SIGNAL_TYPE))
		{
			if (FAIL == tq_read_int_enum(p, buf, buf_size, tq_set_signal_type, &query->signal_type,
					query->signal_type, ZBX_TQ_SIGNAL_TYPE_UNKNOWN, ZBX_TQ_QUERY_TAG_SIGNAL_TYPE,
					error, max_error_len))
				goto out;
		}
		else if (0 == strcmp(buf, ZBX_TQ_QUERY_TAG_METRIC_POINT_TYPE))
		{
			if (FAIL == tq_read_int_enum(p, buf, buf_size, tq_set_metric_point_type,
					&query->metric_point_type, query->metric_point_type,
					ZBX_TQ_METRIC_POINT_TYPE_UNKNOWN, ZBX_TQ_QUERY_TAG_METRIC_POINT_TYPE, error,
					max_error_len))
				goto out;
		}
		else if (0 == strcmp(buf, ZBX_TQ_QUERY_TAG_COLUMNS))
		{
			struct zbx_json_parse	jp_columns;

			if (0 != query->columns.values_num)
			{
				zbx_snprintf(error, max_error_len, "Duplicate \"%s\" tag", ZBX_TQ_QUERY_TAG_COLUMNS);
				goto out;
			}

			if (FAIL == zbx_json_brackets_open(p, &jp_columns))
				goto out;
			if (FAIL == tq_parse_columns(&jp_columns, &query->columns, buf, buf_size, error, max_error_len))
				goto out;
		}
		else if (0 == strcmp(buf, ZBX_TQ_QUERY_TAG_AGGREGATED_COLUMNS))
		{
			struct zbx_json_parse	jp_aggr_columns;

			if (0 != query->aggregated_columns.values_num)
			{
				zbx_snprintf(error, max_error_len, "Duplicate \"%s\" tag",
						ZBX_TQ_QUERY_TAG_AGGREGATED_COLUMNS);
				goto out;
			}

			if (FAIL == zbx_json_brackets_open(p, &jp_aggr_columns))
				goto out;
			if (FAIL == tq_parse_aggregated_columns(&jp_aggr_columns, &query->aggregated_columns, buf,
					buf_size, error, max_error_len))
				goto out;
		}
		else if (0 == strcmp(buf, ZBX_TQ_QUERY_TAG_FILTER))
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

		col->col_type = tq_get_column_type(query->signal_type, query->metric_point_type, col->column);
	}

	for (int i = 0; i < query->aggregated_columns.values_num; i++)
	{
		zbx_tq_aggr_column_t *aggr_col = &query->aggregated_columns.values[i];

		aggr_col->col_type = tq_get_column_type(query->signal_type, query->metric_point_type, aggr_col->column);
	}

	for (int i = 0; i < query->conditions.values_num; i++)
	{
		zbx_tq_condition_t	*condition = &query->conditions.values[i];

		condition->col_type = tq_get_column_type(query->signal_type, query->metric_point_type,
				condition->column);
	}
}

static int	tq_validate_formula_node_indices(const zbx_tq_formula_node_t *node, int condition_count, char *error,
		size_t max_error_len)
{
	if (TQ_FORMULA_NODE_TYPE_LEAF == node->type)
	{
		if (0 > node->condition_idx || node->condition_idx >= condition_count)
			return ret_errf(FAIL, error, max_error_len, "Invalid condition id in \"%s\": %d",
					ZBX_TQ_QUERY_TAG_FORMULA, node->condition_idx);

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
	if (ZBX_TQ_SIGNAL_TYPE_UNKNOWN == query->signal_type)
		return ret_errf(FAIL, error, max_error_len, "\"%s\" is not set", ZBX_TQ_QUERY_TAG_SIGNAL_TYPE);

	if (ZBX_TQ_METRIC_POINT_TYPE_UNKNOWN == query->metric_point_type)
		return ret_errf(FAIL, error, max_error_len, "\"%s\" is not set", ZBX_TQ_QUERY_TAG_METRIC_POINT_TYPE);

	for (int i = 0; i < query->columns.values_num; i++)
	{
		const zbx_tq_column_t	*col = &query->columns.values[i];

		if (NULL == col->column)
			return ret_errf(FAIL, error, max_error_len, "\"%s\" is not set for column #%d",
					ZBX_TQ_QUERY_TAG_COLUMN, i);

		if (ZBX_TQ_COLUMN_TYPE_UNKNOWN == col->col_type)
			return ret_errf(FAIL, error, max_error_len, "\"%s\" is invalid for column #%d",
					ZBX_TQ_QUERY_TAG_COLUMN, i);

		if (!(ZBX_TQ_COLUMN_TYPE_ATTRIBUTES == col->col_type || ZBX_TQ_COLUMN_TYPE_STR == col->col_type ||
				ZBX_TQ_COLUMN_TYPE_NUM == col->col_type
				|| ZBX_TQ_COLUMN_TYPE_TIMESTAMP == col->col_type))
			return ret_errf(FAIL, error, max_error_len, "Unsupported column type for column #%d", i);

		if (NULL == col->attribute_key)
			return ret_errf(FAIL, error, max_error_len, "\"%s\" is not set for column #%d",
					ZBX_TQ_QUERY_TAG_ATTRIBUTE_KEY, i);

		if (FAIL == tq_validate_str(col->attribute_key))
			return ret_errf(FAIL, error, max_error_len, "\"%s\" is not valid UTF-8 for column #%d",
					ZBX_TQ_QUERY_TAG_ATTRIBUTE_KEY, i);
	}

	if (0 == query->aggregated_columns.values_num)
		return ret_errf(FAIL, error, max_error_len, "\"%s\" is not set", ZBX_TQ_QUERY_TAG_AGGREGATED_COLUMNS);

	for (int i = 0; i < query->aggregated_columns.values_num; i++)
	{
		const zbx_tq_aggr_column_t *aggr_col = &query->aggregated_columns.values[i];

		if (NULL == aggr_col->column)
			return ret_errf(FAIL, error, max_error_len,
					"\"%s\" is not set for aggregated column #%d", ZBX_TQ_QUERY_TAG_COLUMN, i);

		if (ZBX_TQ_FUNCTION_COUNT != aggr_col->function)
		{
			const tq_column_info_t	*col_info;

			if (ZBX_TQ_COLUMN_TYPE_UNKNOWN == aggr_col->col_type)
				return ret_errf(FAIL, error, max_error_len,
						"\"%s\" is invalid for aggregated column #%d", ZBX_TQ_QUERY_TAG_COLUMN,
								i);

			if (!(ZBX_TQ_COLUMN_TYPE_NUM == aggr_col->col_type
					|| ZBX_TQ_COLUMN_TYPE_TIMESTAMP == aggr_col->col_type))
				return ret_errf(FAIL, error, max_error_len,
						"Unsupported column type for aggregated column #%d", i);

			col_info = tq_get_column_info(query->signal_type, query->metric_point_type, aggr_col->column);

			if (0 != (col_info->flags & TQ_COLUMN_INFO_FLAG_NO_AGGREGATION))
				return ret_errf(FAIL, error, max_error_len,
						"Aggregation is not supported column \"%s\" in aggregated column #%d",
						aggr_col->column, i);
		}

		if (NULL == aggr_col->alias)
			return ret_errf(FAIL, error, max_error_len, "\"%s\" is not set for aggregated column #%d",
					ZBX_TQ_QUERY_TAG_ALIAS, i);

		if (FAIL == tq_validate_str(aggr_col->alias))
			return ret_errf(FAIL, error, max_error_len, "\"%s\" is not valid UTF-8 for column #%d",
					ZBX_TQ_QUERY_TAG_ALIAS, i);

		if (ZBX_TQ_FUNCTION_UNKNOWN == aggr_col->function)
			return ret_errf(FAIL, error, max_error_len, "\"%s\" is not set for aggregated column #%d",
					ZBX_TQ_QUERY_TAG_FUNCTION, i);

		if (ZBX_TQ_FUNCTION_PERCENTILE == aggr_col->function)
		{
			double	x;

			if (1 != aggr_col->parameters.values_num || NULL == aggr_col->parameters.values[0] ||
					FAIL == zbx_is_double(aggr_col->parameters.values[0], &x) ||
					0.0 > x || x > 100.0)
				return ret_errf(FAIL, error, max_error_len, "Invalid parameters for \"percentile\" "
						"function in aggregated column #%d", i);
		}
	}

	if (ZBX_TQ_EVAL_TYPE_UNKNOWN == query->evaltype)
		return ret_errf(FAIL, error, max_error_len, "\"%s\" is not set", ZBX_TQ_QUERY_TAG_EVALTYPE);

	if (NULL == query->formula)
		return ret_errf(FAIL, error, max_error_len, "\"%s\" is not set", ZBX_TQ_QUERY_TAG_FORMULA);

	if (0 != query->conditions.values_num)
	{
		if (ZBX_TQ_EVAL_TYPE_EXPRESSION == query->evaltype)
		{
			if (NULL == query->formula_parsed)
				return ret_errf(FAIL, error, max_error_len, "\"%s\" is invalid",
						ZBX_TQ_QUERY_TAG_FORMULA);

			if (SUCCEED != tq_validate_formula_node_indices(query->formula_parsed,
					query->conditions.values_num, error, max_error_len))
				return FAIL;
		}

		for (int i = 0; i < query->conditions.values_num; i++)
		{
			const zbx_tq_condition_t	*cond = &query->conditions.values[i];

			if (NULL == cond->column)
				return ret_errf(FAIL, error, max_error_len,
						"\"%s\" is not set for condition #%d", ZBX_TQ_QUERY_TAG_COLUMN, i);

			if (ZBX_TQ_COLUMN_TYPE_UNKNOWN == cond->col_type)
				return ret_errf(FAIL, error, max_error_len,
						"\"%s\" is invalid for condition #%d", ZBX_TQ_QUERY_TAG_COLUMN, i);

			if (ZBX_TQ_OPERATOR_UNKNOWN == cond->operator)
				return ret_errf(FAIL, error, max_error_len, "\"%s\" is not set for condition #%d",
						ZBX_TQ_QUERY_TAG_OPERATOR, i);

			if (NULL == cond->attribute_key)
				return ret_errf(FAIL, error, max_error_len,
						"\"%s\" is not set for condition #%d",
						ZBX_TQ_QUERY_TAG_ATTRIBUTE_KEY, i);

			if (FAIL == tq_validate_str(cond->attribute_key))
				return ret_errf(FAIL, error, max_error_len,
						"\"%s\" is not valid UTF-8 for condition #%d",
						ZBX_TQ_QUERY_TAG_ATTRIBUTE_KEY, i);

			if (NULL == cond->value)
				return ret_errf(FAIL, error, max_error_len, "\"%s\" is not set for condition #%d",
						ZBX_TQ_QUERY_TAG_VALUE, i);

			if (FAIL == tq_validate_str(cond->value))
				return ret_errf(FAIL, error, max_error_len,
						"\"%s\" is not valid UTF-8 for condition #%d",
						ZBX_TQ_QUERY_TAG_VALUE, i);

			if (!(ZBX_TQ_COLUMN_TYPE_ATTRIBUTES == cond->col_type ||
					ZBX_TQ_COLUMN_TYPE_ARRAY_ATTRIBUTES == cond->col_type ||
					ZBX_TQ_COLUMN_TYPE_STR == cond->col_type ||
					ZBX_TQ_COLUMN_TYPE_ARRAY_STR == cond->col_type))
				return ret_errf(FAIL, error, max_error_len, "Unsupported column type for condition #%d",
						i);

			if (ZBX_TQ_OPERATOR_EXISTS == cond->operator
					&& SUCCEED != tq_column_type_is_attributes(cond->col_type))
				return ret_errf(FAIL, error, max_error_len,
						"Operator \"exists\" selected for non-attribute condition #%d", i);

			if (SUCCEED == tq_column_type_is_attributes(cond->col_type) &&
					(ZBX_TQ_OPERATOR_CONTAINS == cond->operator ||
					ZBX_TQ_OPERATOR_NOT_CONTAINS == cond->operator))
				return ret_errf(FAIL, error, max_error_len,
						"Operator \"%s\" selected for attribute condition #%d",
						(ZBX_TQ_OPERATOR_CONTAINS == cond->operator ? "contains" :
						"not contains"), i);
		}
	}

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
 *             macro_expand_cb  - [IN] NULL or callback called on every field *
 *                                     in query where macros are supported    *
 *             macro_expand_ctx - [IN] context passed to macro_expand_cb      *
 *             error            - [OUT]                                       *
 *             max_error_len    - [IN]                                        *
 *                                                                            *
 * Return value: SUCCEED - json_str parsed successfully, query is valid;      *
 *                         query must be cleaned after use                    *
 *               FAIL    - otherwise; query is not initialized                *
 *                                                                            *
 ******************************************************************************/
int	zbx_tq_parse_query(zbx_tq_query_t *query, const char *query_json, char *error, size_t max_error_len)
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

	if (FAIL == tq_parse_query(&jp, query, error, max_error_len))
		goto out;

	tq_set_column_types(query);

	/* formula being set is enforced by tq_validate_query, if it is set, then it must be either empty or valid */
	if (NULL != query->formula && '\0' != *query->formula)
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

int	zbx_tq_validate_time_params(const char *time_shift_str, int *time_shift_out, const char *lookback_limit_str,
		int *lookback_limit_out, const char *granularity_str, int *granularity_out, char *error,
		size_t max_error_len)
{
	int	time_shift_tmp, lookback_limit_tmp, granularity_tmp;

	if (NULL == time_shift_str)
		return ret_errf(FAIL, error, max_error_len, "Time shift is not set");
	if (NULL == lookback_limit_str)
		return ret_errf(FAIL, error, max_error_len, "Lookback limit is not set");
	if (NULL == granularity_str)
		return ret_errf(FAIL, error, max_error_len, "Granularity is not set");

	if (FAIL == zbx_is_time_suffix(time_shift_str, &time_shift_tmp, ZBX_LENGTH_UNLIMITED))
		return ret_errf(FAIL, error, max_error_len, "Unsupported time shift value");
	if (FAIL == zbx_is_time_suffix(lookback_limit_str, &lookback_limit_tmp, ZBX_LENGTH_UNLIMITED))
		return ret_errf(FAIL, error, max_error_len, "Unsupported lookback limit value");
	if (FAIL == zbx_is_time_suffix(granularity_str, &granularity_tmp, ZBX_LENGTH_UNLIMITED))
		return ret_errf(FAIL, error, max_error_len, "Unsupported granularity value");

	if (0 == granularity_tmp)
		return ret_errf(FAIL, error, max_error_len, "Aggregation size cannot be 0");

	if (granularity_tmp > lookback_limit_tmp)
		return ret_errf(FAIL, error, max_error_len, "Aggregation size cannot be larger than lookback limit");

	if (NULL != time_shift_out)
		*time_shift_out = time_shift_tmp;
	if (NULL != lookback_limit_out)
		*lookback_limit_out = lookback_limit_tmp;
	if (NULL != granularity_out)
		*granularity_out = granularity_tmp;

	return SUCCEED;
}
