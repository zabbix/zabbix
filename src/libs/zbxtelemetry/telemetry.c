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

static void	tq_aggr_column_init(zbx_tq_aggr_column_t *aggr_column)
{
	aggr_column->column_name	= NULL;
	aggr_column->function		= ZBX_TQ_FUNCTION_UNKNOWN;
	zbx_vector_var_create(&aggr_column->args);
	aggr_column->alias		= NULL;

}

static void	tq_condition_init(zbx_tq_condition_t *condition)
{
	condition->column	= NULL;
	condition->json_path	= NULL;
	condition->value	= NULL;
	condition->operator	= ZBX_TQ_OPERATOR_UNKNOWN;
}

int	zbx_tq_query_from_json(const char *json_str, zbx_tq_query_t *query)
{
	tq_query_init(query);

	// TODO

	return SUCCEED;
}

void	zbx_tq_query_clean(zbx_tq_query_t *query)
{
	for (int i = 0; i < query->columns.values_num; i++)
	{
		zbx_free(query->columns.values[i].key);
		zbx_free(query->columns.values[i].name);
	}
	zbx_vector_tq_column_destroy(&query->columns);

	for (int i = 0; i < query->aggregated_columns.values_num; i++)
	{
		zbx_tq_aggr_column_t	*aggr_col = &query->aggregated_columns.values[i];

		zbx_free(aggr_col->column_name);
		zbx_free(aggr_col->alias);

		for (int j = 0; j < aggr_col->args.values_num; j++)
			zbx_variant_clear(&aggr_col->args.values[j]);
		zbx_vector_var_destroy(&aggr_col->args);
	}
	zbx_vector_tq_aggr_column_destroy(&query->aggregated_columns);

	zbx_free(query->formula);

	for (int i = 0; i < query->conditions.values_num; i++) {
		zbx_free(query->conditions.values[i].column);
		zbx_free(query->conditions.values[i].json_path);
		zbx_free(query->conditions.values[i].value);
	}
	zbx_vector_tq_condition_destroy(&query->conditions);
}
