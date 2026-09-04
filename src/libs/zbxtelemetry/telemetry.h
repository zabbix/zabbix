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

#ifndef ZABBIX_TELEMETRY_H
#define ZABBIX_TELEMETRY_H

#include "zbxtelemetry.h"
#include "zbxalgo.h"
#include "zbxjson.h"

#define TQ_COLUMN_INFO_FLAG_NO_AGGREGATION	0x01

typedef struct
{
	const char		*name;
	zbx_tq_column_type_t	type;
	int			flags;
}
tq_column_info_t;

typedef enum
{
	TQ_FORMULA_NODE_TYPE_OR,
	TQ_FORMULA_NODE_TYPE_AND,
	TQ_FORMULA_NODE_TYPE_NOT,
	TQ_FORMULA_NODE_TYPE_LEAF,
}
tq_formula_node_type_t;

void	tq_query_init(zbx_tq_query_t *query);
void	tq_column_init(zbx_tq_column_t *column);
void	tq_column_clean(zbx_tq_column_t *column);
void	tq_aggr_column_init(zbx_tq_aggr_column_t *aggr_column);
void	tq_aggr_column_clean(zbx_tq_aggr_column_t *aggr_column);
void	tq_condition_init(zbx_tq_condition_t *condition);
void	tq_condition_clean(zbx_tq_condition_t *condition);

const tq_column_info_t	*tq_get_column_info(zbx_tq_signal_type_t signal_type,
		zbx_tq_metric_point_type_t metric_point_type, const char *column);
zbx_tq_column_type_t	tq_get_column_type(zbx_tq_signal_type_t signal_type,
		zbx_tq_metric_point_type_t metric_point_type, const char *column);

int	tq_column_type_is_array(zbx_tq_column_type_t type);
int	tq_column_type_is_attributes(zbx_tq_column_type_t type);

zbx_tq_column_type_t	tq_get_base_column_type(zbx_tq_column_type_t type);

char	*tq_get_result_field_name_dyn(const zbx_tq_column_t *col);
int	tq_condition_ptr_compare_by_column_and_key(const void *a, const void *b);
void	tq_get_conditions_and_or_sorted(zbx_tq_query_t *query, zbx_vector_tq_condition_ptr_t *conditions_sorted);

int	tq_validate_result_column_type(zbx_json_type_t type);
int	tq_validate_result_aggr_column_type(zbx_json_type_t type);

#endif
