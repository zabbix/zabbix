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
#include "zbxvault.h"

#define ZBX_TQ_QUERY_TAG_SIGNAL_TYPE		"signal_type"
#define ZBX_TQ_QUERY_TAG_METRIC_POINT_TYPE	"metric_point_type"
#define ZBX_TQ_QUERY_TAG_COLUMNS		"columns"
#define ZBX_TQ_QUERY_TAG_COLUMN			"column"
#define ZBX_TQ_QUERY_TAG_ATTRIBUTE_KEY		"attribute_key"
#define ZBX_TQ_QUERY_TAG_AGGREGATED_COLUMNS	"aggregated_columns"
#define ZBX_TQ_QUERY_TAG_FUNCTION		"function"
#define ZBX_TQ_QUERY_TAG_PARAMETERS		"parameters"
#define ZBX_TQ_QUERY_TAG_ALIAS			"alias"
#define ZBX_TQ_QUERY_TAG_FILTER			"filter"
#define ZBX_TQ_QUERY_TAG_EVALTYPE		"evaltype"
#define ZBX_TQ_QUERY_TAG_FORMULA		"formula"
#define ZBX_TQ_QUERY_TAG_CONDITIONS		"conditions"
#define ZBX_TQ_QUERY_TAG_OPERATOR		"operator"
#define ZBX_TQ_QUERY_TAG_VALUE			"value"

#define ZBX_TQ_TIME_SHIFT_MIN		0
#define ZBX_TQ_TIME_SHIFT_MAX		SEC_PER_DAY
#define ZBX_TQ_LOOKBACK_LIMIT_MIN	1
#define ZBX_TQ_LOOKBACK_LIMIT_MAX	(3 * SEC_PER_DAY)
#define ZBX_TQ_GRANULARITY_MIN		1
#define ZBX_TQ_GRANULARITY_MAX		SEC_PER_DAY

/* apm db */

typedef enum zbx_apm_db_type
{
	ZBX_APM_DB_TYPE_POSTGRESQL = 0,
	ZBX_APM_DB_TYPE_MYSQL,
	ZBX_APM_DB_TYPE_CLICKHOUSE,
	ZBX_APM_DB_TYPE_ELASTIC
}
zbx_apm_db_type_t;

typedef struct zbx_apm_db_config
{
	int			have_local_config; /* 0 - disabled, 1 - enabled */
	zbx_apm_db_type_t	db_type;
	char			*url;
	char			*username;
	char			*password;
	char			*db;
	char			*source_ip;
	char			*vault_path;
	char			*ssl_cert_file;
	char			*ssl_key_file;
	char			*ssl_key_password;
	unsigned char		ssl_verify_peer;
	unsigned char		ssl_verify_host;
	char			*ssl_ca_location;
	char			*ssl_cert_location;
	char			*ssl_key_location;
}
zbx_apm_db_config_t;

int	zbx_apm_db_config_init(zbx_apm_db_config_t *apm_db_config, const char *config_apm_provider,
		const char *config_source_ip, const char *config_ssl_ca_location, const char *config_ssl_cert_location,
		const char *config_ssl_key_location, const zbx_config_vault_t *config_vault, char **error);

void	zbx_apm_db_config_clear(zbx_apm_db_config_t *config);

/* telemetry query */

typedef enum
{
	ZBX_TQ_COLUMN_TYPE_UNKNOWN = -1,
	ZBX_TQ_COLUMN_TYPE_ATTRIBUTES,
	ZBX_TQ_COLUMN_TYPE_ARRAY_ATTRIBUTES,
	ZBX_TQ_COLUMN_TYPE_STR,
	ZBX_TQ_COLUMN_TYPE_ARRAY_STR,
	ZBX_TQ_COLUMN_TYPE_NUM,
	ZBX_TQ_COLUMN_TYPE_ARRAY_NUM,
	ZBX_TQ_COLUMN_TYPE_TIMESTAMP,
	ZBX_TQ_COLUMN_TYPE_ARRAY_TIMESTAMP,
	ZBX_TQ_COLUMN_TYPE_BOOL,
	ZBX_TQ_COLUMN_TYPE_ARRAY_BOOL,
}
zbx_tq_column_type_t;

typedef enum
{
	ZBX_TQ_SIGNAL_TYPE_UNKNOWN	= -1,
	ZBX_TQ_SIGNAL_TYPE_TRACES	= 0,
	ZBX_TQ_SIGNAL_TYPE_METRICS	= 1,
	ZBX_TQ_SIGNAL_TYPE_LOGS	= 2,
}
zbx_tq_signal_type_t;

typedef enum
{
	ZBX_TQ_METRIC_POINT_TYPE_UNKNOWN		= -1,
	ZBX_TQ_METRIC_POINT_TYPE_SUM			= 0,
	ZBX_TQ_METRIC_POINT_TYPE_GAUGE			= 1,
	ZBX_TQ_METRIC_POINT_TYPE_HISTOGRAM		= 2,
	ZBX_TQ_METRIC_POINT_TYPE_EXPONENTIAL_HISTOGRAM	= 3,
}
zbx_tq_metric_point_type_t;

typedef enum
{
	ZBX_TQ_FUNCTION_UNKNOWN		= -1,
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
	char			*column;
	zbx_tq_column_type_t	col_type; /* stored here in order to not look it up every time */
	char			*attribute_key;
}
zbx_tq_column_t;

ZBX_VECTOR_DECL(tq_column, zbx_tq_column_t)

typedef struct
{
	char			*column;
	zbx_tq_column_type_t	col_type; /* stored here in order to not look it up every time */
	zbx_tq_function_type_t	function;
	zbx_vector_str_t	parameters;
	char			*alias;
}
zbx_tq_aggr_column_t;

ZBX_VECTOR_DECL(tq_aggr_column, zbx_tq_aggr_column_t)

typedef struct
{
	char			*column;
	zbx_tq_column_type_t	col_type; /* stored here in order to not look it up every time */
	char			*attribute_key;
	char			*value;
	zbx_tq_operator_t	operator;
}
zbx_tq_condition_t;

ZBX_VECTOR_DECL(tq_condition, zbx_tq_condition_t)

typedef struct tq_formula_node zbx_tq_formula_node_t;

typedef struct
{
	zbx_tq_signal_type_t		signal_type;
	zbx_tq_metric_point_type_t	metric_point_type;
	zbx_vector_tq_column_t		columns;
	zbx_vector_tq_aggr_column_t	aggregated_columns;
	zbx_tq_eval_type_t		evaltype;
	char				*formula;
	zbx_tq_formula_node_t		*formula_parsed;
	zbx_vector_tq_condition_t	conditions;
}
zbx_tq_query_t;

ZBX_PTR_VECTOR_DECL(tq_condition_ptr, zbx_tq_condition_t *)

int	zbx_tq_parse_query(zbx_tq_query_t *query, const char *query_json, char *error, size_t max_error_len);
void	zbx_tq_query_clean(zbx_tq_query_t *query);

int	zbx_tq_validate_time_params(const char *time_shift_str, int *time_shift_out, const char *lookback_limit_str,
		int *lookback_limit_out, const char *granularity_str, int *granularity_out, char *error,
		size_t max_error_len);

void	zbx_tq_sql_generate_clickhouse(const zbx_tq_query_t *query, int time_shift, int lookback_limit, int granularity,
		time_t now, time_t lasttimestamp, char **sql);

void	zbx_tq_get_timestamp_filter_bounds(int time_shift, int lookback_limit, int granularity, time_t now,
		time_t lasttimestamp, time_t *out_lower, time_t *out_upper);
void	zbx_tq_get_newlasttimestamp(int lookback_limit, int granularity, time_t now, time_t lasttimestamp,
		time_t *newlasttimestamp);

int	zbx_tq_clickhouse_parse_resp(const zbx_tq_query_t *query, char *resp, zbx_vector_str_t *values);

void	zbx_tq_clickhouse_get_query_url(const char *base_url, const char *db, char **url);

#endif
