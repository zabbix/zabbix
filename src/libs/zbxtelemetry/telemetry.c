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

#include "zbxjson.h"
#include "zbxtelemetry.h"
#include "zbxcommon.h"

ZBX_VECTOR_IMPL(tq_column, zbx_tq_column_t)
ZBX_VECTOR_IMPL(tq_aggr_column, zbx_tq_aggr_column_t)
ZBX_VECTOR_IMPL(tq_condition, zbx_tq_condition_t)

static void	tq_query_init(zbx_tq_query_t *query)
{
	query->category		= ZBX_TQ_CATEGORY_UNKNOWN;
	query->metric_type	= ZBX_TQ_METRIC_TYPE_UNKNOWN;
	zbx_vector_tq_column_create(&query->columns);
	zbx_vector_tq_aggr_column_create(&query->aggregated_columns);
	query->evaltype		= ZBX_TQ_EVAL_TYPE_UNKNOWN;
	query->formula		= NULL;
	zbx_vector_tq_condition_create(&query->conditions);
	query->time_shift	= ZBX_TQ_TIME_INTERVAL_INVALID;
	query->loopback_limit	= ZBX_TQ_TIME_INTERVAL_INVALID;
	query->aggregation_size	= ZBX_TQ_TIME_INTERVAL_INVALID;
}

static void	tq_column_init(zbx_tq_column_t *column)
{
	column->name	= NULL;
	column->key	= NULL;
}

static void	tq_column_clean(zbx_tq_column_t *column)
{
	zbx_free(column->name);
	zbx_free(column->key);
}

static void	tq_aggr_column_init(zbx_tq_aggr_column_t *aggr_column)
{
	aggr_column->column_name	= NULL;
	aggr_column->function		= ZBX_TQ_FUNCTION_UNKNOWN;
	zbx_vector_str_create(&aggr_column->args);
	aggr_column->alias		= NULL;

}

static void	tq_aggr_column_clean(zbx_tq_aggr_column_t *aggr_column)
{
	zbx_free(aggr_column->column_name);
	zbx_free(aggr_column->alias);

	zbx_vector_str_clear(&aggr_column->args);
	zbx_vector_str_destroy(&aggr_column->args);
}

static void	tq_condition_init(zbx_tq_condition_t *condition)
{
	condition->column	= NULL;
	condition->json_path	= NULL;
	condition->value	= NULL;
	condition->operator	= ZBX_TQ_OPERATOR_UNKNOWN;
}

static void	tq_condition_clean(zbx_tq_condition_t *condition)
{
	zbx_free(condition->column);
	zbx_free(condition->json_path);
	zbx_free(condition->value);
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

static int	tq_parse_col(struct zbx_json_parse *jp, zbx_tq_column_t *col, char *buf, size_t buf_size)
{
	int		ret = FAIL;
	zbx_json_type_t	type;
	const char	*p = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	tq_column_init(col);

	while (NULL != (p = zbx_json_pair_next(jp, p, buf, buf_size)))
	{
		if (0 == strcmp(buf, "name"))
		{
			if (NULL != col->name)
				goto out;
			if (NULL == (p = zbx_json_decodevalue(p, buf, buf_size, &type))
					|| ZBX_JSON_TYPE_STRING != type)
				goto out;
			col->name = zbx_strdup(NULL, buf);
		}
		else if (0 == strcmp(buf, "key"))
		{
			if (NULL != col->key)
				goto out;
			if (NULL == (p = zbx_json_decodevalue(p, buf, buf_size, &type))
					|| ZBX_JSON_TYPE_STRING != type)
				goto out;
			col->key = zbx_strdup(NULL, buf);
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
	/* in case of an error the aggregated_columns vector is cleaned by the calling function */
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
	zbx_json_type_t	type;
	const char	*p = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	tq_aggr_column_init(aggr_col);

	while (NULL != (p = zbx_json_pair_next(jp, p, buf, buf_size)))
	{
		if (0 == strcmp(buf, "column_name"))
		{
			if (NULL != aggr_col->column_name)
				goto out;
			if (NULL == (p = zbx_json_decodevalue(p, buf, buf_size, &type))
					|| ZBX_JSON_TYPE_STRING != type)
				goto out;
			aggr_col->column_name = zbx_strdup(NULL, buf);
		}
		else if (0 == strcmp(buf, "function"))
		{
			if (ZBX_TQ_FUNCTION_UNKNOWN != aggr_col->function)
				goto out;
			if (NULL == (p = zbx_json_decodevalue(p, buf, buf_size, &type))
					|| ZBX_JSON_TYPE_STRING != type)
				goto out;
			if (ZBX_TQ_FUNCTION_UNKNOWN == (aggr_col->function = tq_match_function_type(buf)))
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
			if (NULL != aggr_col->alias)
				goto out;
			if (NULL == (p = zbx_json_decodevalue(p, buf, buf_size, &type))
					|| ZBX_JSON_TYPE_STRING != type)
				goto out;
			aggr_col->alias = zbx_strdup(NULL, buf);
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

/******************************************************************************
 *                                                                            *
 * Purpose: parses json_str contents and stores them into query               *
 *                                                                            *
 * Parameters: json_str     - [IN]                                            *
 *             query        - [OUT] must not be initialized beforehand        *
 *                                                                            *
 * Return value: SUCCEED - json_str parsed successfully, query must be        *
 *                         cleaned after use                                  *
 *               FAIL    - otherwise, query is not initialized                *
 *                                                                            *
 ******************************************************************************/
int	zbx_tq_query_from_json(const char *json_str, zbx_tq_query_t *query)
{
	// TODO: macros

	int			ret = FAIL;
	struct zbx_json_parse	jp;
	char			buf[MAX_STRING_LEN];
	size_t			buf_size = sizeof(buf);
	zbx_json_type_t		type;
	const char		*p = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	tq_query_init(query);

	if (FAIL == zbx_json_open(json_str, &jp))
		goto out;

	while (NULL != (p = zbx_json_pair_next(&jp, p, buf, buf_size)))
	{
		zabbix_log(LOG_LEVEL_INFORMATION, "MYTEST: %s: p='%s', buf='%s'", __func__, p, buf);

		if (0 == strcmp(buf, "category"))
		{
			if (ZBX_TQ_CATEGORY_UNKNOWN != query->category)
				goto out;
			if (NULL == (p = zbx_json_decodevalue(p, buf, buf_size, &type))
					|| ZBX_JSON_TYPE_STRING != type)
				goto out;
			if (ZBX_TQ_CATEGORY_UNKNOWN == (query->category = tq_match_category(buf)))
				goto out;

		}
		else if (0 == strcmp(buf, "metric_type"))
		{
			if (ZBX_TQ_METRIC_TYPE_UNKNOWN != query->metric_type)
				goto out;
			if (NULL == (p = zbx_json_decodevalue(p, buf, buf_size, &type))
					|| ZBX_JSON_TYPE_STRING != type)
				goto out;
			if (ZBX_TQ_METRIC_TYPE_UNKNOWN == (query->metric_type = tq_match_metric_type(buf)))
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
			// TODO
		}
		else if (0 == strcmp(buf, "formula"))
		{
			// TODO
		}
		else if (0 == strcmp(buf, "conditions"))
		{
			// TODO
		}
		else if (0 == strcmp(buf, "time_shift"))
		{
			// TODO
		}
		else if (0 == strcmp(buf, "loopback_limit"))
		{
			// TODO
		}
		else if (0 == strcmp(buf, "aggregation_size"))
		{
			// TODO
		}
		else
		{
			zabbix_log(LOG_LEVEL_WARNING, "%s(): unknown tag: '%s'", __func__, buf);
		}
	}

	// TODO: validate

	ret = SUCCEED;
out:
	if (FAIL == ret)
		zbx_tq_query_clean(query);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() ret:%d", __func__, ret);

	return ret;
}

void	zbx_tq_query_clean(zbx_tq_query_t *query)
{
	for (int i = 0; i < query->columns.values_num; i++)
	{
		tq_column_clean(&query->columns.values[i]);
	}
	zbx_vector_tq_column_destroy(&query->columns);

	for (int i = 0; i < query->aggregated_columns.values_num; i++)
	{
		tq_aggr_column_clean(&query->aggregated_columns.values[i]);
	}
	zbx_vector_tq_aggr_column_destroy(&query->aggregated_columns);

	zbx_free(query->formula);

	for (int i = 0; i < query->conditions.values_num; i++) {
		tq_condition_clean(&query->conditions.values[i]);
	}
	zbx_vector_tq_condition_destroy(&query->conditions);
}
