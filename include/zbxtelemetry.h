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

typedef enum
{
	ZBX_TQ_CATEGORY_UNKNOWN = 0,
	ZBX_TQ_CATEGORY_APM_TRACES,
	ZBX_TQ_CATEGORY_APM_METRICS,
	ZBX_TQ_CATEGORY_APM_LOGS,
}
zbx_tq_category_t;

typedef enum
{
	ZBX_TQ_METRIC_TYPE_UNKNOWN = 0,
	ZBX_TQ_METRIC_TYPE_SUM,
	ZBX_TQ_METRIC_TYPE_GAUGE,
	ZBX_TQ_METRIC_TYPE_HISTOGRAM,
	ZBX_TQ_METRIC_TYPE_EXPONENTIAL_HISTOGRAM,
}
zbx_tq_metric_type_t;

typedef enum
{
	ZBX_TQ_FUNCTION_UNKNOWN = 0,
	ZBX_TQ_FUNCTION_COUNT,
	ZBX_TQ_FUNCTION_MIN,
	ZBX_TQ_FUNCTION_MAX,
	ZBX_TQ_FUNCTION_AVG,
	ZBX_TQ_FUNCTION_SUM,
	ZBX_TQ_FUNCTION_PERCENTILE,
}
zbx_tq_function_type_t;

typedef enum
{
	ZBX_TQ_EVAL_TYPE_UNKNOWN = 0,
	ZBX_TQ_EVAL_TYPE_AND_OR,
	ZBX_TQ_EVAL_TYPE_AND,
	ZBX_TQ_EVAL_TYPE_OR,
	ZBX_TQ_EVAL_TYPE_EXPRESSION,
}
zbx_tq_eval_type_t;

typedef enum
{
	ZBX_TQ_OPERATOR_UNKNOWN = 0,
	ZBX_TQ_OPERATOR_EQUAL,
	ZBX_TQ_OPERATOR_NOT_EQUAL,
	ZBX_TQ_OPERATOR_CONTAINS,
	ZBX_TQ_OPERATOR_NOT_CONTAINS,
}
zbx_tq_operator_t;

typedef struct
{
	char	*name;
	char	*key;
}
zbx_tq_column_t;

ZBX_VECTOR_DECL(tq_column, zbx_tq_column_t)

typedef struct
{
	char			*column_name;
	zbx_tq_function_type_t	function;
	zbx_vector_str_t	args;
	char			*alias;
}
zbx_tq_aggr_column_t;

ZBX_VECTOR_DECL(tq_aggr_column, zbx_tq_aggr_column_t)

typedef struct
{
	char			*column_name;
	char			*json_path;
	char			*value;
	zbx_tq_operator_t	operator;
}
zbx_tq_condition_t;

ZBX_VECTOR_DECL(tq_condition, zbx_tq_condition_t)

typedef struct
{
	zbx_tq_category_t		category;
	zbx_tq_metric_type_t		metric_type;
	zbx_vector_tq_column_t		columns;
	zbx_vector_tq_aggr_column_t	aggregated_columns;
	zbx_tq_eval_type_t		evaltype;
	char				*formula;
	zbx_vector_tq_condition_t	conditions;
	int				time_shift;
	int				loopback_limit;
	int				aggregation_size;
}
zbx_tq_query_t;

typedef struct zbx_tq_conn_params_clickhouse
{
	const char	*url;
	const char	*http_proxy;
	int		timeout;
	int 		max_attempts;
	const char	*ssl_cert_file;
	const char	*ssl_key_file;
	const char	*ssl_key_password;
	unsigned char	verify_peer;
	unsigned char	verify_host;
	unsigned char	authtype;
	const char	*username;
	const char	*password;
	const char	*token;
}
zbx_tq_conn_params_clickhouse_t;

int	zbx_tq_query_from_json(const char *json_str, zbx_tq_query_t *query);
void	zbx_tq_query_clean(zbx_tq_query_t *query);

void	zbx_tq_sql_generate_postgresql(const zbx_tq_query_t *query, time_t now, time_t lasttimestamp, char **sql);
void	zbx_tq_sql_generate_clickhouse(const zbx_tq_query_t *query, time_t now, time_t lasttimestamp, char **sql);

int	zbx_tq_send_query_clickhouse(const zbx_tq_query_t *query, time_t now, time_t lasttimestamp,
		const zbx_tq_conn_params_clickhouse_t *conn_params, const char *config_source_ip,
		const char *config_ssl_ca_location, const char *config_ssl_cert_location,
		const char *config_ssl_key_location, char **out, char **error);

#endif
