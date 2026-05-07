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

ZBX_VECTOR_IMPL(tq_column, zbx_tq_column_t)
ZBX_VECTOR_IMPL(tq_aggr_column, zbx_tq_aggr_column_t)
ZBX_VECTOR_IMPL(tq_condition, zbx_tq_condition_t)
ZBX_PTR_VECTOR_IMPL(tq_condition_ptr, zbx_tq_condition_t *)

void	tq_query_init(zbx_tq_query_t *query)
{
	query->category		= ZBX_TQ_CATEGORY_UNKNOWN;
	query->metric_type	= ZBX_TQ_METRIC_TYPE_UNKNOWN;
	zbx_vector_tq_column_create(&query->columns);
	zbx_vector_tq_aggr_column_create(&query->aggregated_columns);
	query->evaltype		= ZBX_TQ_EVAL_TYPE_UNKNOWN;
	query->formula		= NULL;
	zbx_vector_tq_condition_create(&query->conditions);
	query->time_shift	= TQ_TIME_INTERVAL_INVALID;
	query->loopback_limit	= TQ_TIME_INTERVAL_INVALID;
	query->aggregation_size	= TQ_TIME_INTERVAL_INVALID;
}

void	tq_column_init(zbx_tq_column_t *column)
{
	column->name	= NULL;
	column->key	= NULL;
}

void	tq_column_clean(zbx_tq_column_t *column)
{
	zbx_free(column->name);
	zbx_free(column->key);
}

void	tq_aggr_column_init(zbx_tq_aggr_column_t *aggr_column)
{
	aggr_column->column_name	= NULL;
	aggr_column->function		= ZBX_TQ_FUNCTION_UNKNOWN;
	zbx_vector_str_create(&aggr_column->args);
	aggr_column->alias		= NULL;
}

void	tq_aggr_column_clean(zbx_tq_aggr_column_t *aggr_column)
{
	zbx_free(aggr_column->column_name);
	zbx_free(aggr_column->alias);

	zbx_vector_str_clear_ext(&aggr_column->args, zbx_str_free);
	zbx_vector_str_destroy(&aggr_column->args);
}

void	tq_condition_init(zbx_tq_condition_t *condition)
{
	condition->column_name	= NULL;
	condition->json_path	= NULL;
	condition->value	= NULL;
	condition->operator	= ZBX_TQ_OPERATOR_UNKNOWN;
}

void	tq_condition_clean(zbx_tq_condition_t *condition)
{
	zbx_free(condition->column_name);
	zbx_free(condition->json_path);
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

	for (int i = 0; i < query->conditions.values_num; i++) {
		tq_condition_clean(&query->conditions.values[i]);
	}
	zbx_vector_tq_condition_destroy(&query->conditions);
}

int	tq_formula_constant_to_condition_idx(const char *p, int len)
{
	int res = 0;
	int mult = 1;

	for (int i = len - 1; i >= 0; i--)
	{
		res += (p[i] - 'A') * mult;
		mult *= ('Z' - 'A') + 1;
	}

	return res;
}

void	zbx_tq_get_timestamp_filter_bounds(const zbx_tq_query_t *query, time_t now, time_t lasttimestamp,
		time_t *out_lower, time_t *out_upper)
{
	time_t	now_shifted = now - query->time_shift;

	if (lasttimestamp > now_shifted)
		lasttimestamp = now_shifted;

	if (query->aggregation_size > now_shifted - lasttimestamp)
	{
		if (NULL != out_lower)
			*out_lower = now_shifted - query->aggregation_size;
		if (NULL != out_upper)
			*out_upper = now_shifted;
	}
	else
	{
		time_t	start = MAX(lasttimestamp, now_shifted - (time_t)query->loopback_limit);

		if (NULL != out_lower)
			*out_lower = start;
		if (NULL != out_upper)
			*out_upper = start + ((now_shifted - start) / (time_t)query->aggregation_size) *
				(time_t)query->aggregation_size;
	}
}

char	*tq_get_result_field_name_dyn(const zbx_tq_column_t *col)
{
	if (NULL != col->key)
		return zbx_dsprintf(NULL, "%s.%s", col->name, col->key);
	else
		return zbx_strdup(NULL, col->name);
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

void	tq_get_conditions_and_or_sorted(const zbx_tq_query_t *query, zbx_vector_tq_condition_ptr_t *conditions_sorted)
{
	zbx_vector_tq_condition_ptr_create(conditions_sorted);

	for (int i = 0; i < query->conditions.values_num; i++)
		zbx_vector_tq_condition_ptr_append(conditions_sorted, &query->conditions.values[i]);

	zbx_vector_tq_condition_ptr_sort(conditions_sorted, tq_condition_ptr_compare_by_column_and_path);
}
