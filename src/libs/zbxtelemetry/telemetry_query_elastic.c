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

#include "telemetry.h"
#include "zbxalgo.h"
#include "zbxcommon.h"
#include "zbxjson.h"
#include "zbxtelemetry.h"
#include "zbxtypes.h"
#include <time.h>

/* TODO: maybe this should be user-defined? or at least documented? */
#define TQ_ELASTIC_MAX_BUCKETS 10000

/******************************************************************************
 *                                                                            *
 * Return value: escaped and UNQUOTED string to use in a LIKE pattern         *
 *                                                                            *
 ******************************************************************************/
static char	*tq_es_escape_wildcard_pattern_dyn(const char *src)
{
	size_t	len = strlen(src) + 1;

	for (const char *p = src; '\0' != *p; p++)
	{
		if ('*' == *p || '?' == *p || '\\' == *p)
			len++;
	}

	char	*dst = zbx_malloc(NULL, len);
	char	*d = dst;

	for (const char	*p = src; '\0' != *p; p++)
	{
		if ('*' == *p || '?' == *p || '\\' == *p)
			*d++ = '\\';

		*d++ = *p;
	}
	*d = '\0';

	return dst;
}

static void	tq_es_add_condition(const zbx_tq_condition_t *cond, struct zbx_json *j)
{
	/* FIXME: with this, json_path must start with column name, this is likely not desired and $. should be used */
	const char	*operand = NULL == cond->json_path ? cond->column_name : cond->json_path;

	zbx_json_addobject(j, NULL);

	if (ZBX_TQ_OPERATOR_NOT_EQUAL == cond->operator || ZBX_TQ_OPERATOR_NOT_CONTAINS == cond->operator)
	{
		zbx_json_addobject(j, "bool");
		zbx_json_addobject(j, "must_not");
	}

	if (ZBX_TQ_OPERATOR_EQUAL == cond->operator || ZBX_TQ_OPERATOR_NOT_EQUAL == cond->operator)
	{
		zbx_json_addobject(j, "term");
		zbx_json_addstring(j, operand, cond->value, ZBX_JSON_TYPE_STRING);
		zbx_json_close(j); /* term */
	}
	else /* contains or not contains */
	{
		char	*value_esc = tq_es_escape_wildcard_pattern_dyn(cond->value);
		char	*pattern = zbx_dsprintf(NULL, "*%s*", value_esc);

		/* TODO: remove this if in the final elastic index json keys are not flattened */
		if (NULL != cond->json_path)
			THIS_SHOULD_NEVER_HAPPEN; /* wildcard queries are not supported on flattened fields */

		zbx_json_addobject(j, "wildcard");
		zbx_json_addobject(j, operand);

		zbx_json_addstring(j, "value", pattern, ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(j, "case_insensitive", "false", ZBX_JSON_TYPE_FALSE);

		zbx_json_close(j); /* operand */
		zbx_json_close(j); /* wildcard */

		zbx_free(value_esc);
		zbx_free(pattern);
	}

	if (ZBX_TQ_OPERATOR_NOT_EQUAL == cond->operator || ZBX_TQ_OPERATOR_NOT_CONTAINS == cond->operator)
	{
		zbx_json_close(j); /* must_not */
		zbx_json_close(j); /* bool */
	}

	zbx_json_close(j);
}

static void	tq_es_add_conditions_simple(const zbx_tq_query_t *query, struct zbx_json *j)
{
	zbx_json_addobject(j, NULL);
	zbx_json_addobject(j, "bool");

	if (ZBX_TQ_EVAL_TYPE_OR == query->evaltype)
		zbx_json_adduint64(j, "minimum_should_match", 1);

	/* AND and OR respectively */
	zbx_json_addarray(j, ZBX_TQ_EVAL_TYPE_AND == query->evaltype ? "filter" : "should");

	for (int i = 0; i < query->conditions.values_num; i++)
	{
		tq_es_add_condition(&query->conditions.values[i], j);
	}

	zbx_json_close(j); /* should or filter */
	zbx_json_close(j); /* bool */
	zbx_json_close(j);
}

static void	tq_es_add_conditions(const zbx_tq_query_t *query, struct zbx_json *j)
{
	if (0 == query->conditions.values_num)
		return;

	switch (query->evaltype)
	{
		case ZBX_TQ_EVAL_TYPE_AND:
		case ZBX_TQ_EVAL_TYPE_OR:
			tq_es_add_conditions_simple(query, j);
			break;

		case ZBX_TQ_EVAL_TYPE_AND_OR:
			/* TODO */
			break;

		case ZBX_TQ_EVAL_TYPE_EXPRESSION:
			/* TODO */
			break;

		case ZBX_TQ_EVAL_TYPE_UNKNOWN:
		default:
			THIS_SHOULD_NEVER_HAPPEN;
	}
}

static void	tq_es_add_query(const zbx_tq_query_t *query, struct zbx_json *j, time_t timestamp_lo,
		time_t timestamp_hi)
{
	zbx_json_addobject(j, "query");
	zbx_json_addobject(j, "bool");
	zbx_json_addarray(j, "filter");

	zbx_json_addobject(j, NULL);
	zbx_json_addobject(j, "range");
	zbx_json_addobject(j, "Timestamp");

	zbx_json_adduint64(j, "gte", timestamp_lo);
	zbx_json_adduint64(j, "lt", timestamp_hi);
	zbx_json_addstring(j, "format", "epoch_second", ZBX_JSON_TYPE_STRING);

	zbx_json_close(j); /* Timestamp */
	zbx_json_close(j); /* range */
	zbx_json_close(j);

	tq_es_add_conditions(query, j);

	zbx_json_close(j); /* filter */
	zbx_json_close(j); /* bool */
	zbx_json_close(j); /* query */
}

static void	tq_es_add_columns(const zbx_vector_tq_column_t *cols, struct zbx_json *j, char *buf, size_t buf_size)
{
	for (int i = 0; i < cols->values_num; i++)
	{
		zbx_json_addobject(j, NULL);

		zbx_snprintf(buf, buf_size, "col_%d", i);
		zbx_json_addobject(j, buf);

		zbx_json_addobject(j, "terms");

		if (NULL == cols->values[i].key)
			zbx_strlcpy(buf, cols->values[i].name, buf_size);
		else
			zbx_snprintf(buf, buf_size, "%s.%s", cols->values[i].name, cols->values[i].key);

		zbx_json_addstring(j, "field", buf, ZBX_JSON_TYPE_STRING);

		zbx_json_close(j); /* terms */
		zbx_json_close(j); /* col_X */
		zbx_json_close(j);
	}
}

static const char	*tq_es_get_func_name(zbx_tq_function_type_t function)
{
	switch (function)
	{
		case ZBX_TQ_FUNCTION_MIN:
			return "min";
		case ZBX_TQ_FUNCTION_MAX:
			return "max";
		case ZBX_TQ_FUNCTION_AVG:
			return "avg";
		case ZBX_TQ_FUNCTION_SUM:
			return "sum";
		case ZBX_TQ_FUNCTION_PERCENTILE:
			return "percentiles";

		default:
		case ZBX_TQ_FUNCTION_UNKNOWN:
		case ZBX_TQ_FUNCTION_COUNT:
			THIS_SHOULD_NEVER_HAPPEN;
			return "";
	}
}

static void	tq_es_add_aggr_columns(const zbx_vector_tq_aggr_column_t *aggr_cols, struct zbx_json *j, char *buf,
		size_t buf_size)
{
	for (int i = 0; i < aggr_cols->values_num; i++)
	{
		const zbx_tq_aggr_column_t	*col = &aggr_cols->values[i];

		if (ZBX_TQ_FUNCTION_COUNT == col->function)
			continue; /* doc_count is already returned */

		zbx_snprintf(buf, buf_size, "aggr_col_%d", i);
		zbx_json_addobject(j, buf);
		zbx_json_addobject(j, tq_es_get_func_name(col->function));

		zbx_json_addstring(j, "field", col->column_name, ZBX_JSON_TYPE_STRING);

		if (ZBX_TQ_FUNCTION_PERCENTILE == col->function)
		{
			double fraction = 0;

			if (SUCCEED != zbx_is_double(col->args.values[0], &fraction))
				THIS_SHOULD_NEVER_HAPPEN;

			zbx_json_addarray(j, "percents");

			/* "percentiles" expects percents, not fraction */
			zbx_json_addfloat(j, NULL, fraction * 100);

			zbx_json_close(j); /* percents */
		}

		zbx_json_close(j); /* function */
		zbx_json_close(j); /* alias */
	}
}

static void	tq_es_add_aggs(const zbx_tq_query_t *query, struct zbx_json *j, time_t timestamp_lo,
		char *buf, size_t buf_size)
{
	zbx_json_addobject(j, "aggs");
	zbx_json_addobject(j, "grouped");

	zbx_json_addobject(j, "composite");
	zbx_json_adduint64(j, "size", TQ_ELASTIC_MAX_BUCKETS);
	zbx_json_addarray(j, "sources");

	zbx_json_addobject(j, NULL);
	zbx_json_addobject(j, "rounded_time");
	zbx_json_addobject(j, "date_histogram");

	zbx_json_addstring(j, "field", "Timestamp", ZBX_JSON_TYPE_STRING);

	zbx_snprintf(buf, buf_size, "%ds", query->aggregation_size);
	zbx_json_addstring(j, "fixed_interval", buf, ZBX_JSON_TYPE_STRING);

	zbx_snprintf(buf, buf_size, "+" ZBX_FS_TIME_T "s", timestamp_lo);
	zbx_json_addstring(j, "offset", buf, ZBX_JSON_TYPE_STRING);

	zbx_json_addstring(j, "format", "epoch_second", ZBX_JSON_TYPE_STRING);

	zbx_json_close(j); /* date_histogram */
	zbx_json_close(j); /* rounded_time */
	zbx_json_close(j);

	tq_es_add_columns(&query->columns, j, buf, buf_size);

	zbx_json_close(j); /* sources */
	zbx_json_close(j); /* composite */

	zbx_json_addobject(j, "aggs");

	zbx_json_addobject(j, "starttime");
	zbx_json_addobject(j, "min");
	zbx_json_addstring(j, "field", "Timestamp", ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(j, "format", "epoch_second", ZBX_JSON_TYPE_STRING);
	zbx_json_close(j); /* min */
	zbx_json_close(j); /* starttime */

	tq_es_add_aggr_columns(&query->aggregated_columns, j, buf, buf_size);

	zbx_json_close(j); /* aggs */

	zbx_json_close(j); /* grouped */
	zbx_json_close(j); /* aggs */
}

void	zbx_tq_generate_elastic(const zbx_tq_query_t *query, time_t now, time_t lasttimestamp, char **dsl)
{
	struct zbx_json	j;
	time_t		timestamp_filter_lower_bound, timestamp_filter_upper_bound;
	char		buf[MAX_STRING_LEN];
	size_t		buf_size = sizeof(buf);

	zbx_tq_get_timestamp_filter_bounds(query, now, lasttimestamp, &timestamp_filter_lower_bound,
		&timestamp_filter_upper_bound);

	zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);

	zbx_json_adduint64(&j, "size", 0);
	tq_es_add_query(query, &j, timestamp_filter_lower_bound, timestamp_filter_upper_bound);
	tq_es_add_aggs(query, &j, timestamp_filter_lower_bound, buf, buf_size);

	*dsl = zbx_strdup(NULL, j.buffer);
	zbx_json_free(&j);
}

const char	*zbx_tq_elastic_get_index_name(zbx_tq_category_t category, zbx_tq_metric_type_t metric_type)
{
	switch (category)
	{
		case ZBX_TQ_CATEGORY_APM_TRACES:
			return "apm_traces";

		case ZBX_TQ_CATEGORY_APM_METRICS:
			switch (metric_type)
			{
				case ZBX_TQ_METRIC_TYPE_SUM:
					return "apm_metrics_sum";
				case ZBX_TQ_METRIC_TYPE_GAUGE:
					return "apm_metrics_gauge";
				case ZBX_TQ_METRIC_TYPE_HISTOGRAM:
					return "apm_metrics_histogram";
				case ZBX_TQ_METRIC_TYPE_EXPONENTIAL_HISTOGRAM:
					return "apm_metrics_exponentialhistogram";

				default:
					THIS_SHOULD_NEVER_HAPPEN;
					return "";
			}
			break;

		case ZBX_TQ_CATEGORY_APM_LOGS:
			return "apm_logs";

		default:
			THIS_SHOULD_NEVER_HAPPEN;
			return "";
	}
}

static char	*tq_elastic_parse_bucket(const zbx_tq_query_t *query, struct zbx_json_parse *jp, int bucket_id)
{
	struct zbx_json		j;
	char			*str = NULL;
	struct zbx_json_parse	jp_starttime;
	struct zbx_json_parse	jp_key;
	char			buf[MAX_STRING_LEN];
	zbx_uint64_t		timestamp;

	zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);

	/* bucket id */
	zbx_json_adduint64(&j, "id", bucket_id);

	if (SUCCEED != zbx_json_brackets_by_name(jp, "starttime", &jp_starttime))
		goto out;

	/* timestamp */
	if (SUCCEED != zbx_json_value_by_name(&jp_starttime, "value_as_string", buf, sizeof(buf), NULL) ||
			SUCCEED != zbx_is_uint64(buf, &timestamp))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot parse timestamp from bucket %d", bucket_id);
		goto out;
	}

	zbx_json_adduint64(&j, "timestamp", timestamp);

	if (SUCCEED != zbx_json_brackets_by_name(jp, "key", &jp_key))
		goto out;

	zbx_json_addobject(&j, "columns");

	for (int i = 0; i < query->columns.values_num; i++)
	{
		const zbx_tq_column_t	*col = &query->columns.values[i];
		char			*field_name = tq_get_result_field_name_dyn(col);
		zbx_json_type_t		type;
		char			*col_name = zbx_dsprintf(NULL, "col_%d", i);

		if (SUCCEED != zbx_json_value_by_name(&jp_key, col_name, buf, sizeof(buf), &type))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot parse column \"%s\" from bucket %d", field_name,
					bucket_id);
			zbx_free(field_name);
			zbx_free(col_name);
			goto out;
		}

		zbx_json_addstring(&j, field_name, buf, type);

		zbx_free(field_name);
		zbx_free(col_name);
	}

	for (int i = 0; i < query->aggregated_columns.values_num; i++)
	{
		const zbx_tq_aggr_column_t	*aggr_col = &query->aggregated_columns.values[i];
		zbx_json_type_t			type;

		if (ZBX_TQ_FUNCTION_COUNT == aggr_col->function)
		{
			zbx_json_value_by_name(jp, "doc_count", buf, sizeof(buf), &type);
		}
		else
		{
			struct zbx_json_parse	jp_res;

			zbx_snprintf(buf, sizeof(buf), "aggr_col_%d", i);

			if (SUCCEED != zbx_json_brackets_by_name(jp, buf, &jp_res))
			{
				zabbix_log(LOG_LEVEL_WARNING, "cannot parse column \"%s\" from bucket %d",
						aggr_col->alias, bucket_id);
				goto out;
			}

			if (ZBX_TQ_FUNCTION_PERCENTILE == aggr_col->function)
			{
				struct zbx_json_parse	jp_values;
				const char		*pvalue;

				zbx_json_brackets_by_name(&jp_res, "values", &jp_values);

				/* name of the value varies, so, to extract it, the first value is taken */
				/* (as there should be only one value) */
				if (NULL == (pvalue = zbx_json_pair_next(&jp_values, NULL, buf, sizeof(buf))) ||
						NULL == zbx_json_decodevalue(pvalue, buf, sizeof(buf), &type))
				{
					zabbix_log(LOG_LEVEL_WARNING, "cannot parse column \"%s\" from bucket %d",
							aggr_col->alias, bucket_id);
					goto out;
				}


			}
			else
			{
				if (SUCCEED != zbx_json_value_by_name(&jp_res, "value", buf, sizeof(buf), &type))
				{
					zabbix_log(LOG_LEVEL_WARNING, "cannot parse column \"%s\" from bucket %d",
							aggr_col->alias, bucket_id);
					goto out;
				}
			}
		}

		zbx_json_addstring(&j, aggr_col->alias, buf, type);
	}

	zbx_json_close(&j);
	zbx_json_close(&j);
	str = zbx_strdup(NULL, j.buffer);
out:
	zbx_json_free(&j);

	return str;
}

int	zbx_tq_elastic_parse_resp(const zbx_tq_query_t *query, const char *resp, zbx_vector_str_t *values)
{
	int			ret = FAIL;
	struct zbx_json_parse	jp_root;
	struct zbx_json_parse	jp_body;
	struct zbx_json_parse	jp_aggregations;
	struct zbx_json_parse	jp_grouped;
	struct zbx_json_parse	jp_buckets;
	const char		*p = NULL;
	int			bucket_count = 0;

	zbx_vector_str_create(values);

	if (SUCCEED != zbx_json_open(resp, &jp_root))
		goto out;
	if (SUCCEED != zbx_json_brackets_by_name(&jp_root, "body", &jp_body))
		goto out;
	if (SUCCEED != zbx_json_brackets_by_name(&jp_body, "aggregations", &jp_aggregations))
		goto out;
	if (SUCCEED != zbx_json_brackets_by_name(&jp_aggregations, "grouped", &jp_grouped))
		goto out;
	if (SUCCEED != zbx_json_brackets_by_name(&jp_grouped, "buckets", &jp_buckets))
		goto out;

	while (NULL != (p = zbx_json_next(&jp_buckets, p)))
	{
		struct zbx_json_parse	jp;
		char			*str = NULL;

		if (SUCCEED != zbx_json_brackets_open(p, &jp))
			goto out;

		if (NULL == (str = tq_elastic_parse_bucket(query, &jp, ++bucket_count)))
			goto out;

		zbx_vector_str_append(values, str);
	}

	ret = SUCCEED;
out:
	if (SUCCEED != ret)
	{
		zbx_vector_str_clear_ext(values, zbx_str_free);
		zbx_vector_str_destroy(values);
	}

	return ret;
}
