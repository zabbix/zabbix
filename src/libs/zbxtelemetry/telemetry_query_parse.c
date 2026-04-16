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
#include "zbxcalc.h"
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
typedef int (*tq_enum_set_func_t)(const char *str, void *out);

/******************************************************************************
 *                                                                            *
 * Comments: it is the caller's responsibility to free the string allocated   *
 *           and assigned to out if the function succeeded                    *
 *                                                                            *
 ******************************************************************************/
static int	tq_read_string(const char *p, char **out) {
	size_t		out_alloc = 0;
	zbx_json_type_t	type;
	const char	*p_new = p;

	if (NULL != *out)
		return FAIL;

	if (NULL == (p_new = zbx_json_decodevalue_dyn(p, out, &out_alloc, &type)) || ZBX_JSON_TYPE_STRING != type)
	{
		zabbix_log(LOG_LEVEL_ERR, "%s(): failed to read string at '%s'", __func__, p);
		p = p_new;
		zbx_free(*out);

		return FAIL;
	}

	p = p_new;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Comments: val must be set to the integer value of the enum that out points *
 *           to                                                               *
 *                                                                            *
 ******************************************************************************/
static int	tq_read_enum(const char *p, char *buf, size_t buf_size, tq_enum_set_func_t set_func, void *out,
		int val, int val_unknown)
{
	int		ret = FAIL;
	zbx_json_type_t	type;
	const char	*p_new = p;

	if (val != val_unknown)
		goto out;

	if (NULL == (p_new = zbx_json_decodevalue(p, buf, buf_size, &type)) || ZBX_JSON_TYPE_STRING != type)
	{
		zabbix_log(LOG_LEVEL_ERR, "%s(): failed to read string at '%s'", __func__, p);
		goto out;
	}

	if (set_func(buf, out) == val_unknown)
	{
		zabbix_log(LOG_LEVEL_ERR, "%s(): invalid value '%s' at '%s'", __func__, buf, p);
		goto out;
	}

	ret = SUCCEED;
out:
	p = p_new;

	return ret;
}

#define TQ_SET_FUNC_DEF(__set_func_name, __match_func, __type)	\
static int	__set_func_name(const char *__str, void *__out)	\
{								\
	return (int)(*(__type *)__out = __match_func(__str));		\
}

static zbx_tq_category_t	tq_match_category(const char *str)
{
	if (0 == strcmp(str, "apm_traces"))
		return ZBX_TQ_CATEGORY_APM_TRACES;
	else if (0 == strcmp(str, "apm_metrics"))
		return ZBX_TQ_CATEGORY_APM_METRICS;
	else if (0 == strcmp(str, "apm_logs"))
		return ZBX_TQ_CATEGORY_APM_LOGS;

	return ZBX_TQ_CATEGORY_UNKNOWN;
}

TQ_SET_FUNC_DEF(tq_set_category, tq_match_category, zbx_tq_category_t)

static zbx_tq_metric_type_t	tq_match_metric_type(const char *str)
{
	if (0 == strcmp(str, "sum"))
		return ZBX_TQ_METRIC_TYPE_SUM;
	else if (0 == strcmp(str, "gauge"))
		return ZBX_TQ_METRIC_TYPE_GAUGE;
	else if (0 == strcmp(str, "histogram"))
		return ZBX_TQ_METRIC_TYPE_HISTOGRAM;
	else if (0 == strcmp(str, "exponential_histogram"))
		return ZBX_TQ_METRIC_TYPE_EXPONENTIAL_HISTOGRAM;

	return ZBX_TQ_METRIC_TYPE_UNKNOWN;
}

TQ_SET_FUNC_DEF(tq_set_metric_type, tq_match_metric_type, zbx_tq_metric_type_t)

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

TQ_SET_FUNC_DEF(tq_set_function_type, tq_match_function_type, zbx_tq_function_type_t)

static zbx_tq_eval_type_t	tq_match_eval_type(const char *str)
{
	if (0 == strcmp(str, "and"))
		return ZBX_TQ_EVAL_TYPE_AND;
	else if (0 == strcmp(str, "or"))
		return ZBX_TQ_EVAL_TYPE_OR;
	else if (0 == strcmp(str, "and_or"))
		return ZBX_TQ_EVAL_TYPE_AND_OR;
	else if (0 == strcmp(str, "expression"))
		return ZBX_TQ_EVAL_TYPE_EXPRESSION;

	return ZBX_TQ_EVAL_TYPE_UNKNOWN;
}

TQ_SET_FUNC_DEF(tq_set_eval_type, tq_match_eval_type, zbx_tq_eval_type_t)

static zbx_tq_operator_t	tq_match_operator(const char *str)
{
	if (0 == strcmp(str, "equal"))
		return ZBX_TQ_OPERATOR_EQUAL;
	else if (0 == strcmp(str, "not_equal"))
		return ZBX_TQ_OPERATOR_NOT_EQUAL;
	else if (0 == strcmp(str, "contains"))
		return ZBX_TQ_OPERATOR_CONTAINS;
	else if (0 == strcmp(str, "not_contains"))
		return ZBX_TQ_OPERATOR_NOT_CONTAINS;

	return ZBX_TQ_OPERATOR_UNKNOWN;
}

TQ_SET_FUNC_DEF(tq_set_operator, tq_match_operator, zbx_tq_operator_t)

static int	tq_parse_col(struct zbx_json_parse *jp, zbx_tq_column_t *col, char *buf, size_t buf_size)
{
	int		ret = FAIL;
	const char	*p = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	tq_column_init(col);

	while (NULL != (p = zbx_json_pair_next(jp, p, buf, buf_size)))
	{
		if (0 == strcmp(buf, "name"))
		{
			if (FAIL == tq_read_string(p, &col->name))
				goto out;
		}
		else if (0 == strcmp(buf, "key"))
		{
			if (FAIL == tq_read_string(p, &col->key))
				goto out;
		}
		else
		{
			zabbix_log(LOG_LEVEL_WARNING, "%s(): unknown tag: '%s'", __func__, buf);
		}
	}

	ret = SUCCEED;
out:
	if (FAIL == ret)
		tq_column_clean(col);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() ret:%d", __func__, ret);

	return ret;
}

static int	tq_parse_columns(struct zbx_json_parse *jp, zbx_vector_tq_column_t *columns, char *buf, size_t buf_size)
{
	/* in case of an error the columns vector is cleaned by the calling function */
	int		ret = FAIL;
	const char	*p = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	while (NULL != (p = zbx_json_next(jp, p)))
	{
		struct zbx_json_parse	jp_elem;

		if (FAIL == zbx_json_brackets_open(p, &jp_elem))
			goto out;

		zbx_tq_column_t	col;
		if (FAIL == tq_parse_col(&jp_elem, &col, buf, buf_size))
			goto out;

		zbx_vector_tq_column_append(columns, col);
	}

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() ret:%d", __func__, ret);

	return ret;
}

static int	tq_parse_function_args(struct zbx_json_parse *jp, zbx_vector_str_t *args, char *buf, size_t buf_size)
{
	/* in case of an error the args vector is cleaned by the calling function */
	int		ret = FAIL;
	const char	*p = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	while (NULL != (p = zbx_json_next(jp, p)))
	{
		if (NULL == (p = zbx_json_decodevalue(p, buf, buf_size, NULL)))
			goto out;

		zbx_vector_str_append(args, zbx_strdup(NULL, buf));
	}

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() ret:%d", __func__, ret);

	return ret;
}

static int	tq_parse_aggr_col(struct zbx_json_parse *jp, zbx_tq_aggr_column_t *aggr_col, char *buf, size_t buf_size)
{
	int		ret = FAIL;
	const char	*p = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	tq_aggr_column_init(aggr_col);

	while (NULL != (p = zbx_json_pair_next(jp, p, buf, buf_size)))
	{
		if (0 == strcmp(buf, "column_name"))
		{
			if (FAIL == tq_read_string(p, &aggr_col->column_name))
				goto out;
		}
		else if (0 == strcmp(buf, "function"))
		{
			if (FAIL == tq_read_enum(p, buf, buf_size, tq_set_function_type, &aggr_col->function,
				aggr_col->function, ZBX_TQ_FUNCTION_UNKNOWN))
				goto out;
		}
		else if (0 == strcmp(buf, "args"))
		{
			if (0 != aggr_col->args.values_num)
				goto out;

			struct zbx_json_parse	jp_args;
			if (FAIL == zbx_json_brackets_open(p, &jp_args))
				goto out;
			if (FAIL == tq_parse_function_args(&jp_args, &aggr_col->args, buf, buf_size))
				goto out;
		}
		else if (0 == strcmp(buf, "alias"))
		{
			if (FAIL == tq_read_string(p, &aggr_col->alias))
				goto out;
		}
		else
		{
			zabbix_log(LOG_LEVEL_WARNING, "%s(): unknown tag: '%s'", __func__, buf);
		}
	}

	ret = SUCCEED;
out:
	if (FAIL == ret)
		tq_aggr_column_clean(aggr_col);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() ret:%d", __func__, ret);

	return ret;
}

static int	tq_parse_aggregated_columns(struct zbx_json_parse *jp, zbx_vector_tq_aggr_column_t *aggregated_columns,
		char *buf, size_t buf_size)
{
	/* in case of an error the aggregated_columns vector is cleaned by the calling function */
	int		ret = FAIL;
	const char	*p = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	while (NULL != (p = zbx_json_next(jp, p)))
	{
		struct zbx_json_parse	jp_elem;

		if (FAIL == zbx_json_brackets_open(p, &jp_elem))
			goto out;

		zbx_tq_aggr_column_t	aggr_col;
		if (FAIL == tq_parse_aggr_col(&jp_elem, &aggr_col, buf, buf_size))
			goto out;

		zbx_vector_tq_aggr_column_append(aggregated_columns, aggr_col);
	}

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() ret:%d", __func__, ret);

	return ret;
}

static int	tq_parse_cond(struct zbx_json_parse *jp, zbx_tq_condition_t *cond, char *buf, size_t buf_size)
{
	int		ret = FAIL;
	const char	*p = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	tq_condition_init(cond);

	while (NULL != (p = zbx_json_pair_next(jp, p, buf, buf_size)))
	{
		if (0 == strcmp(buf, "column_name"))
		{
			if (FAIL == tq_read_string(p, &cond->column_name))
				goto out;
		}
		else if (0 == strcmp(buf, "json_path"))
		{
			if (FAIL == tq_read_string(p, &cond->json_path))
				goto out;
		}
		else if (0 == strcmp(buf, "value"))
		{
			if (FAIL == tq_read_string(p, &cond->value))
				goto out;
		}
		else if (0 == strcmp(buf, "operator"))
		{
			if (FAIL == tq_read_enum(p, buf, buf_size, tq_set_operator, &cond->operator, cond->operator,
					ZBX_TQ_OPERATOR_UNKNOWN))
				goto out;
		}
		else
		{
			zabbix_log(LOG_LEVEL_WARNING, "%s(): unknown tag: '%s'", __func__, buf);
		}
	}

	ret = SUCCEED;
out:
	if (FAIL == ret)
		tq_condition_clean(cond);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() ret:%d", __func__, ret);

	return ret;
}

static int	tq_parse_conditions(struct zbx_json_parse *jp, zbx_vector_tq_condition_t *conditions, char *buf,
		size_t buf_size)
{
	/* in case of an error the conditions vector is cleaned by the calling function */
	int		ret = FAIL;
	const char	*p = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	while (NULL != (p = zbx_json_next(jp, p)))
	{
		struct zbx_json_parse	jp_elem;

		if (FAIL == zbx_json_brackets_open(p, &jp_elem))
			goto out;

		zbx_tq_condition_t cond;
		if (FAIL == tq_parse_cond(&jp_elem, &cond, buf, buf_size))
			goto out;

		zbx_vector_tq_condition_append(conditions, cond);
	}

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() ret:%d", __func__, ret);

	return ret;
}

static int	tq_parse_query(struct zbx_json_parse *jp, zbx_tq_query_t *query)
{
	/* in case of an error the query is cleaned by the calling function */
	int			ret = FAIL;
	char			buf[MAX_STRING_LEN];
	size_t			buf_size = sizeof(buf);
	const char		*p = NULL;
	char			*time_shift = NULL;
	char			*loopback_limit = NULL;
	char			*aggregation_size = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	while (NULL != (p = zbx_json_pair_next(jp, p, buf, buf_size)))
	{
		zabbix_log(LOG_LEVEL_TRACE, "%s: p:'%s', buf:'%s'", __func__, p, buf);

		if (0 == strcmp(buf, "category"))
		{
			if (FAIL == tq_read_enum(p, buf, buf_size, tq_set_category, &query->category, query->category,
					ZBX_TQ_CATEGORY_UNKNOWN))
				goto out;
		}
		else if (0 == strcmp(buf, "metric_type"))
		{
			if (FAIL == tq_read_enum(p, buf, buf_size, tq_set_metric_type, &query->metric_type,
					query->metric_type, ZBX_TQ_METRIC_TYPE_UNKNOWN))
				goto out;
		}
		else if (0 == strcmp(buf, "columns"))
		{
			struct zbx_json_parse	jp_columns;

			if (0 != query->columns.values_num)
				goto out;
			if (FAIL == zbx_json_brackets_open(p, &jp_columns))
				goto out;
			if (FAIL == tq_parse_columns(&jp_columns, &query->columns, buf, buf_size))
				goto out;
		}
		else if (0 == strcmp(buf, "aggregated_columns"))
		{
			struct zbx_json_parse	jp_aggr_columns;

			if (0 != query->aggregated_columns.values_num)
				goto out;
			if (FAIL == zbx_json_brackets_open(p, &jp_aggr_columns))
				goto out;
			if (FAIL == tq_parse_aggregated_columns(&jp_aggr_columns, &query->aggregated_columns, buf,
					buf_size))
				goto out;
		}
		else if (0 == strcmp(buf, "evaltype"))
		{
			if (FAIL == tq_read_enum(p, buf, buf_size, tq_set_eval_type, &query->evaltype,
					query->evaltype, ZBX_TQ_EVAL_TYPE_UNKNOWN))
				goto out;
		}
		else if (0 == strcmp(buf, "formula"))
		{
			if (FAIL == tq_read_string(p, &query->formula))
				goto out;
		}
		else if (0 == strcmp(buf, "conditions"))
		{
			struct zbx_json_parse	jp_conditions;

			if (0 != query->conditions.values_num)
				goto out;
			if (FAIL == zbx_json_brackets_open(p, &jp_conditions))
				goto out;
			if (FAIL == tq_parse_conditions(&jp_conditions, &query->conditions, buf, buf_size))
				goto out;
		}
		else if (0 == strcmp(buf, "time_shift"))
		{
			if (FAIL == tq_read_string(p, &time_shift))
				goto out;
		}
		else if (0 == strcmp(buf, "loopback_limit"))
		{
			if (FAIL == tq_read_string(p, &loopback_limit))
				goto out;
		}
		else if (0 == strcmp(buf, "aggregation_size"))
		{
			if (FAIL == tq_read_string(p, &aggregation_size))
				goto out;
		}
		else
		{
			zabbix_log(LOG_LEVEL_WARNING, "%s(): unknown tag: \"%s\"", __func__, buf);
		}
	}

	/* TODO: expand macros */

	if (NULL != time_shift)
	{
		if (FAIL == zbx_is_time_suffix(time_shift, &query->time_shift, ZBX_LENGTH_UNLIMITED))
			goto out;
	}

	if (NULL != loopback_limit)
	{
		if (FAIL == zbx_is_time_suffix(loopback_limit, &query->loopback_limit, ZBX_LENGTH_UNLIMITED))
			goto out;
	}

	if (NULL != aggregation_size)
	{
		if (FAIL == zbx_is_time_suffix(aggregation_size, &query->aggregation_size, ZBX_LENGTH_UNLIMITED))
			goto out;
	}

	ret = SUCCEED;
out:
	zbx_free(time_shift);
	zbx_free(loopback_limit);
	zbx_free(aggregation_size);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() ret:%d", __func__, ret);

	return ret;
}

static int	tq_validate_formula_char(char c)
{
	return (isalpha(c) || '(' == c || ')' == c || ' ' == c) ? SUCCEED : FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: validates that:                                                   *
 *              1) formula only contains valid characters                     *
 *                 (see tq_validate_formula_char)                             *
 *              2) every constant (e.g. A, B, AA, AB, ...) maps to an index   *
 *                 that is < condition_count                                  *
 *              3) formula evaluates correctly by zbx_evaluate()              *
 *                                                                            *
 ******************************************************************************/
static int	tq_validate_formula(const char *formula, int condition_count, char *error, size_t max_error_len)
{
	int	ret = FAIL;
	char	*formula_copy = zbx_strdup(NULL, formula);
	double	dummy_value;

	char	*p = formula_copy;
	while ('\0' != *p)
	{
		if (FAIL == tq_validate_formula_char(*p))
			goto out;

		if (!isupper(*p))
		{
			p++;
			continue;
		}

		int	len = 1;
		while (isupper(p[len]))
			len++;

		if (tq_formula_constant_to_condition_idx(p, len) >= condition_count)
			goto out;

		*p = '1';
		memset(p + 1, ' ', len - 1);

		p += len;
	}

	if (FAIL == zbx_evaluate(&dummy_value, formula_copy, error, max_error_len, NULL))
		goto out;

	ret = SUCCEED;
out:
	zbx_free(formula_copy);

	return ret;
}

__zbx_attr_format_printf(4, 5)
static int	ret_errf(int ret, char *error, size_t max_error_len, const char *fmt, ...)
{
	va_list	args;

	va_start(args, fmt);
	zbx_vsnprintf(error, max_error_len, fmt, args);
	va_end(args);

	return ret;
}

static int	tq_validate_query(const zbx_tq_query_t *query, char *error, size_t max_error_len)
{
	if (ZBX_TQ_CATEGORY_UNKNOWN == query->category)
		return ret_errf(FAIL, error, max_error_len, "catagory is not set");
	if (ZBX_TQ_CATEGORY_APM_METRICS == query->category && ZBX_TQ_METRIC_TYPE_UNKNOWN == query->metric_type)
		return ret_errf(FAIL, error, max_error_len, "metric type is not (when category is \"APM metrics\")");

	for (int i = 0; i < query->columns.values_num; i++)
	{
		if (NULL == query->columns.values[i].name)
		return ret_errf(FAIL, error, max_error_len, "column name is not set for column #%d", i);
	}

	if (0 == query->aggregated_columns.values_num)
		return ret_errf(FAIL, error, max_error_len, "aggregated columns are not set");
	for (int i = 0; i < query->aggregated_columns.values_num; i++)
	{
		const zbx_tq_aggr_column_t *aggr_col = &query->aggregated_columns.values[i];

		if (ZBX_TQ_FUNCTION_COUNT != aggr_col->function && NULL == aggr_col->column_name)
			return ret_errf(FAIL, error, max_error_len,
					"column name is not set for aggregated column #%d", i);
		if (NULL == aggr_col->alias)
			return ret_errf(FAIL, error, max_error_len, "alias is not set for aggregated column #%d", i);
		if (ZBX_TQ_FUNCTION_UNKNOWN == aggr_col->function)
			return ret_errf(FAIL, error, max_error_len, "function is not set for aggregated column #%d", i);

		if (ZBX_TQ_FUNCTION_PERCENTILE == aggr_col->function)
		{
			if (1 != aggr_col->args.values_num || NULL == aggr_col->args.values[0] ||
					FAIL == zbx_is_double(aggr_col->args.values[0], NULL))
				return ret_errf(FAIL, error, max_error_len,
						"invalid arguments for \"percentile\"function in aggregated column #%d",
						i);
		}
	}

	if (0 != query->conditions.values_num)
	{
		if (ZBX_TQ_EVAL_TYPE_UNKNOWN == query->evaltype)
			return ret_errf(FAIL, error, max_error_len, "evaltype is not set");

		if (ZBX_TQ_EVAL_TYPE_EXPRESSION == query->evaltype)
		{
			if (NULL == query->formula)
				return ret_errf(FAIL, error, max_error_len,
						"formula is not (when evaltype is \"expression\")");
			if (FAIL == tq_validate_formula(query->formula, query->conditions.values_num, error,
					max_error_len))
				return FAIL;
		}

		for (int i = 0; i < query->conditions.values_num; i++)
		{
			const zbx_tq_condition_t *condition = &query->conditions.values[i];

			if (NULL == condition->column_name)
				return ret_errf(FAIL, error, max_error_len,
						"column name is not set for condition #%d", i);
			if (NULL == condition->value)
				return ret_errf(FAIL, error, max_error_len, "value is not set for condition #%d", i);
			if (ZBX_TQ_OPERATOR_UNKNOWN == condition->operator)
				return ret_errf(FAIL, error, max_error_len, "operator is not set for condition #%d", i);
		}
	}

	if (TQ_TIME_INTERVAL_INVALID == query->time_shift)
		return ret_errf(FAIL, error, max_error_len, "time shift is not set");
	if (TQ_TIME_INTERVAL_INVALID == query->loopback_limit)
		return ret_errf(FAIL, error, max_error_len, "loopback limit is not set");
	if (TQ_TIME_INTERVAL_INVALID == query->aggregation_size)
		return ret_errf(FAIL, error, max_error_len, "aggregation size is not set");

	if (0 == query->aggregation_size)
		return ret_errf(FAIL, error, max_error_len, "aggregation cannot be 0");

	if (query->aggregation_size > query->loopback_limit)
		return ret_errf(FAIL, error, max_error_len, "aggregation size cannot be larger than loopback limit");
	/* TODO: probably add upper limit */

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: parses json_str contents and stores them into query and validates *
 *          it                                                                *
 *                                                                            *
 * Parameters: json_str     - [IN]                                            *
 *             query        - [OUT] parsed query, must not be initialized     *
 *                                  beforehand                                *
 *                                                                            *
 * Return value: SUCCEED - json_str parsed successfully, query is valid,      *
 *                         query must be cleaned after use                    *
 *               FAIL    - otherwise, query is not initialized                *
 *                                                                            *
 ******************************************************************************/
int	zbx_tq_query_from_json(const char *json_str, zbx_tq_query_t *query)
{
	int			ret = FAIL;
	struct zbx_json_parse	jp;
	char			error[256];

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	tq_query_init(query);

	if (FAIL == zbx_json_open(json_str, &jp))
	{
		zabbix_log(LOG_LEVEL_ERR, "%s(): cannot open JSON: \"%s\"", __func__, zbx_json_strerror());
		goto out;
	}

	if (FAIL == tq_parse_query(&jp, query))
	{
		zabbix_log(LOG_LEVEL_ERR, "%s(): cannot parse query", __func__);
		goto out;
	}

	if (FAIL == tq_validate_query(query, error, sizeof(error)))
	{
		zabbix_log(LOG_LEVEL_ERR, "%s(): query validation failed: \"%s\"", __func__, error);
		goto out;
	}

	ret = SUCCEED;
out:
	if (FAIL == ret)
		zbx_tq_query_clean(query);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() ret:%d", __func__, ret);

	return ret;
}
