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

#include "zbxjson.h"
#include "zbxalgo.h"

ZBX_VECTOR_IMPL(tq_column, zbx_tq_column_t)
ZBX_VECTOR_IMPL(tq_aggr_column, zbx_tq_aggr_column_t)
ZBX_VECTOR_IMPL(tq_condition, zbx_tq_condition_t)
ZBX_PTR_VECTOR_IMPL(tq_condition_ptr, zbx_tq_condition_t *)

void	tq_query_init(zbx_tq_query_t *query)
{
	query->signal_type		= ZBX_TQ_SIGNAL_TYPE_UNKNOWN;
	query->metric_point_type	= ZBX_TQ_METRIC_POINT_TYPE_UNKNOWN;
	zbx_vector_tq_column_create(&query->columns);
	zbx_vector_tq_aggr_column_create(&query->aggregated_columns);
	query->evaltype			= ZBX_TQ_EVAL_TYPE_UNKNOWN;
	query->formula			= NULL;
	query->formula_parsed		= NULL;
	zbx_vector_tq_condition_create(&query->conditions);
}

void	tq_column_init(zbx_tq_column_t *column)
{
	column->column		= NULL;
	column->col_type	= ZBX_TQ_COLUMN_TYPE_UNKNOWN;
	column->attribute_key	= NULL;
}

void	tq_column_clean(zbx_tq_column_t *column)
{
	zbx_free(column->column);
	zbx_free(column->attribute_key);
}

void	tq_aggr_column_init(zbx_tq_aggr_column_t *aggr_column)
{
	aggr_column->column		= NULL;
	aggr_column->col_type		= ZBX_TQ_COLUMN_TYPE_UNKNOWN;
	aggr_column->function		= ZBX_TQ_FUNCTION_UNKNOWN;
	zbx_vector_str_create(&aggr_column->parameters);
	aggr_column->alias		= NULL;
}

void	tq_aggr_column_clean(zbx_tq_aggr_column_t *aggr_column)
{
	zbx_free(aggr_column->column);
	zbx_free(aggr_column->alias);

	zbx_vector_str_clear_ext(&aggr_column->parameters, zbx_str_free);
	zbx_vector_str_destroy(&aggr_column->parameters);
}

void	tq_condition_init(zbx_tq_condition_t *condition)
{
	condition->column		= NULL;
	condition->col_type		= ZBX_TQ_COLUMN_TYPE_UNKNOWN;
	condition->attribute_key	= NULL;
	condition->value		= NULL;
	condition->operator		= ZBX_TQ_OPERATOR_UNKNOWN;
}

void	tq_condition_clean(zbx_tq_condition_t *condition)
{
	zbx_free(condition->column);
	zbx_free(condition->attribute_key);
	zbx_free(condition->value);
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

	if (NULL != query->formula_parsed)
	{
		tq_formula_node_free(query->formula_parsed);
		query->formula_parsed = NULL;
	}

	for (int i = 0; i < query->conditions.values_num; i++)
	{
		tq_condition_clean(&query->conditions.values[i]);
	}
	zbx_vector_tq_condition_destroy(&query->conditions);
}

static void	tq_get_timestamp_filter_bounds_unshifted(int lookback_limit, int granularity, time_t now,
		time_t lasttimestamp, time_t *out_lower, time_t *out_upper)
{
	if (lasttimestamp > now)
	{
		zabbix_log(LOG_LEVEL_WARNING, "%s(): lasttimestamp (" ZBX_FS_TIME_T ") is larger than now ("
				ZBX_FS_TIME_T "), setting lasttimestamp to now",
				__func__, (zbx_fs_time_t)lasttimestamp, (zbx_fs_time_t)now);
		lasttimestamp = now;
	}

	if (granularity > now - lasttimestamp)
	{
		if (NULL != out_lower)
			*out_lower = now - granularity;
		if (NULL != out_upper)
			*out_upper = now;
	}
	else
	{
		time_t	start = MAX(lasttimestamp, now - (time_t)lookback_limit);

		if (NULL != out_lower)
			*out_lower = start;
		if (NULL != out_upper)
			*out_upper = start + ((now - start) / (time_t)granularity) * (time_t)granularity;
	}
}

void	zbx_tq_get_timestamp_filter_bounds(int time_shift, int lookback_limit, int granularity, time_t now,
		time_t lasttimestamp, time_t *out_lower, time_t *out_upper)
{
	tq_get_timestamp_filter_bounds_unshifted(lookback_limit, granularity, now, lasttimestamp, out_lower, out_upper);

	if (NULL != out_lower)
		*out_lower = MAX(*out_lower - time_shift, 0);

	if (NULL != out_upper)
		*out_upper = MAX(*out_upper - time_shift, 0);
}

void	zbx_tq_get_newlasttimestamp(int lookback_limit, int granularity, time_t now, time_t lasttimestamp,
		time_t *newlasttimestamp)
{
	tq_get_timestamp_filter_bounds_unshifted(lookback_limit, granularity, now, lasttimestamp, NULL,
			newlasttimestamp);
}

char	*tq_get_result_field_name_dyn(const zbx_tq_column_t *col)
{
	if (SUCCEED == tq_column_type_is_attributes(col->col_type))
		return zbx_dsprintf(NULL, "%s.%s", col->column, col->attribute_key);
	else
		return zbx_strdup(NULL, col->column);
}

int	tq_condition_ptr_compare_by_column_and_key(const void *a, const void *b)
{
	const zbx_tq_condition_t	*cond_a = *(const zbx_tq_condition_t * const *)a;
	const zbx_tq_condition_t	*cond_b = *(const zbx_tq_condition_t * const *)b;
	int				is_attr_a, is_attr_b;

	int	column_name_cmp_res = strcmp(cond_a->column, cond_b->column);

	if (0 != column_name_cmp_res)
		return column_name_cmp_res;

	is_attr_a = tq_column_type_is_attributes(cond_a->col_type);
	is_attr_b = tq_column_type_is_attributes(cond_b->col_type);

	if (SUCCEED != is_attr_a || SUCCEED != is_attr_b)
	{
		if (!(SUCCEED != is_attr_a && SUCCEED != is_attr_b))
			THIS_SHOULD_NEVER_HAPPEN;

		return column_name_cmp_res;
	}

	return strcmp(cond_a->attribute_key, cond_b->attribute_key);
}

void	tq_get_conditions_and_or_sorted(const zbx_tq_query_t *query, zbx_vector_tq_condition_ptr_t *conditions_sorted)
{
	for (int i = 0; i < query->conditions.values_num; i++)
		zbx_vector_tq_condition_ptr_append(conditions_sorted, &query->conditions.values[i]);

	zbx_vector_tq_condition_ptr_sort(conditions_sorted, tq_condition_ptr_compare_by_column_and_key);
}

int	tq_validate_result_column_type(zbx_json_type_t type)
{
	switch (type)
	{
		case ZBX_JSON_TYPE_STRING:
		case ZBX_JSON_TYPE_INT:
		case ZBX_JSON_TYPE_NUMBER:
		case ZBX_JSON_TYPE_NULL:
			return SUCCEED;
		default:
			return FAIL;
	}
}

int	tq_validate_result_aggr_column_type(zbx_json_type_t type)
{
	switch (type)
	{
		case ZBX_JSON_TYPE_STRING:
		case ZBX_JSON_TYPE_INT:
		case ZBX_JSON_TYPE_NUMBER:
		case ZBX_JSON_TYPE_NULL:
			return SUCCEED;
		default:
			return FAIL;
	}
}
