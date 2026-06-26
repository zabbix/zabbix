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

#ifndef ZABBIX_ZBXTELEMETRY_H
#define ZABBIX_ZBXTELEMETRY_H

#include "zbxalgo.h"
#include "zbxdb.h"

typedef int	(*zbx_tq_macro_expand_func_t)(char **text, void *ctx);

typedef enum
{
	ZBX_TQ_COLUMN_TYPE_UNKNOWN = -1,
	ZBX_TQ_COLUMN_TYPE_ATTRIBUTES,
	ZBX_TQ_COLUMN_TYPE_ARRAY_ATTRIBUTES,
	ZBX_TQ_COLUMN_TYPE_STR,
	ZBX_TQ_COLUMN_TYPE_ARRAY_STR,
	ZBX_TQ_COLUMN_TYPE_NUM,
	ZBX_TQ_COLUMN_TYPE_ARRAY_NUM,
}
zbx_tq_column_type_t;

typedef enum
{
	ZBX_TQ_CATEGORY_UNKNOWN		= -1,
	ZBX_TQ_CATEGORY_APM_TRACES	= 0,
	ZBX_TQ_CATEGORY_APM_METRICS	= 1,
	ZBX_TQ_CATEGORY_APM_LOGS	= 2,
}
zbx_tq_category_t;

typedef enum
{
	ZBX_TQ_METRIC_TYPE_UNKNOWN			= -1,
	ZBX_TQ_METRIC_TYPE_SUM				= 0,
	ZBX_TQ_METRIC_TYPE_GAUGE			= 1,
	ZBX_TQ_METRIC_TYPE_HISTOGRAM			= 2,
	ZBX_TQ_METRIC_TYPE_EXPONENTIAL_HISTOGRAM	= 3,
}
zbx_tq_metric_type_t;

typedef enum
{
	ZBX_TQ_FUNCTION_UNKNOWN = -1,
	ZBX_TQ_FUNCTION_MIN		= 1,
	ZBX_TQ_FUNCTION_MAX		= 2,
	ZBX_TQ_FUNCTION_AVG		= 3,
	ZBX_TQ_FUNCTION_COUNT		= 4,
	ZBX_TQ_FUNCTION_SUM		= 5,
	ZBX_TQ_FUNCTION_PERCENTILE	= 8,
}
zbx_tq_function_type_t;

typedef enum
{
	ZBX_TQ_EVAL_TYPE_UNKNOWN	= -1,
	ZBX_TQ_EVAL_TYPE_AND_OR		= 0,
	ZBX_TQ_EVAL_TYPE_AND		= 1,
	ZBX_TQ_EVAL_TYPE_OR		= 2,
	ZBX_TQ_EVAL_TYPE_EXPRESSION	= 3,
}
zbx_tq_eval_type_t;

typedef enum
{
	ZBX_TQ_OPERATOR_UNKNOWN		= -1,
	ZBX_TQ_OPERATOR_EQUAL		= 0,
	ZBX_TQ_OPERATOR_NOT_EQUAL	= 1,
	ZBX_TQ_OPERATOR_CONTAINS	= 2,
	ZBX_TQ_OPERATOR_NOT_CONTAINS	= 3,
	ZBX_TQ_OPERATOR_EXISTS		= 12,
}
zbx_tq_operator_t;

typedef struct
{
	char			*name;
	zbx_tq_column_type_t	col_type; /* stored here in order to not look it up every time */
	char			*key;
}
zbx_tq_column_t;

ZBX_VECTOR_DECL(tq_column, zbx_tq_column_t)

typedef struct
{
	char			*column_name;
	zbx_tq_column_type_t	col_type; /* stored here in order to not look it up every time */
	zbx_tq_function_type_t	function;
	zbx_vector_str_t	args;
	char			*alias;
}
zbx_tq_aggr_column_t;

ZBX_VECTOR_DECL(tq_aggr_column, zbx_tq_aggr_column_t)

typedef struct
{
	char			*column_name;
	zbx_tq_column_type_t	col_type; /* stored here in order to not look it up every time */
	char			*key;
	char			*value;
	zbx_tq_operator_t	operator;
}
zbx_tq_condition_t;

ZBX_VECTOR_DECL(tq_condition, zbx_tq_condition_t)

typedef struct tq_formula_node zbx_tq_formula_node_t;

typedef struct
{
	zbx_tq_category_t		category;
	zbx_tq_metric_type_t		metric_type;
	zbx_vector_tq_column_t		columns;
	zbx_vector_tq_aggr_column_t	aggregated_columns;
	zbx_tq_eval_type_t		evaltype;
	char				*formula;
	zbx_tq_formula_node_t		*formula_parsed;
	zbx_vector_tq_condition_t	conditions;
}
zbx_tq_query_t;

typedef enum zbx_tq_db_type
{
	ZBX_TQ_DB_TYPE_POSTGRESQL = 0,
	ZBX_TQ_DB_TYPE_MYSQL,
	ZBX_TQ_DB_TYPE_CLICKHOUSE,
	ZBX_TQ_DB_TYPE_ELASTIC
}
zbx_tq_db_type_t;

ZBX_PTR_VECTOR_DECL(tq_condition_ptr, zbx_tq_condition_t *)

int	zbx_tq_parse_query(zbx_tq_query_t *query, const char *query_json, zbx_tq_macro_expand_func_t macro_expand_cb,
		void *macro_expand_ctx, char *error, size_t max_error_len);
void	zbx_tq_query_clean(zbx_tq_query_t *query);

int	zbx_tq_validate_time_params(const char *time_shift_str, int *time_shift_out, const char *lookback_limit_str,
		int *lookback_limit_out, const char *granularity_str, int *granularity_out, char *error,
		size_t max_error_len);

char	*zbx_tq_serialize_query(const zbx_tq_query_t *query);

void	zbx_tq_sql_generate_postgresql(const zbx_tq_query_t *query, int time_shift, int lookback_limit, int granularity,
		time_t now, time_t lasttimestamp, char **sql, const zbx_dbconn_t *db);
void	zbx_tq_sql_generate_mysql(const zbx_tq_query_t *query, int time_shift, int lookback_limit, int granularity,
		time_t now, time_t lasttimestamp, char **sql, const zbx_dbconn_t *db);
void	zbx_tq_sql_generate_clickhouse(const zbx_tq_query_t *query, int time_shift, int lookback_limit, int granularity,
		time_t now, time_t lasttimestamp, char **sql);
void	zbx_tq_generate_elastic(const zbx_tq_query_t *query, int time_shift, int lookback_limit, int granularity,
		time_t now, time_t lasttimestamp, char **dsl);

void	zbx_tq_get_timestamp_filter_bounds(int time_shift, int lookback_limit, int granularity, time_t now,
		time_t lasttimestamp, time_t *out_lower, time_t *out_upper);
void	zbx_tq_get_newlasttimestamp(int lookback_limit, int granularity, time_t now, time_t lasttimestamp,
		time_t *newlasttimestamp);

int	zbx_tq_clickhouse_parse_resp(const zbx_tq_query_t *query, char *resp, zbx_vector_str_t *values);
int	zbx_tq_elastic_parse_resp(const zbx_tq_query_t *query, const char *resp, zbx_vector_str_t *values);
int	zbx_tq_parse_sql_result(const zbx_tq_query_t *query, zbx_db_result_t result, zbx_vector_str_t *values);

const char	*zbx_tq_elastic_get_index_name(zbx_tq_category_t category, zbx_tq_metric_type_t metric_type);

#endif
