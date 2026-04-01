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

ZBX_VECTOR_IMPL(tq_column, zbx_tq_column_t)
ZBX_VECTOR_IMPL(tq_aggr_column, zbx_tq_aggr_column_t)
ZBX_VECTOR_IMPL(tq_condition, zbx_tq_condition_t)

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
