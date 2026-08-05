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

static const tq_column_info_t	column_info_traces[] = {
	{"Timestamp",		ZBX_TQ_COLUMN_TYPE_TIMESTAMP,		0	},
	{"TraceId",		ZBX_TQ_COLUMN_TYPE_STR,			0	},
	{"SpanId",		ZBX_TQ_COLUMN_TYPE_STR,			0	},
	{"ParentSpanId",	ZBX_TQ_COLUMN_TYPE_STR,			0	},
	{"TraceState",		ZBX_TQ_COLUMN_TYPE_STR,			0	},
	{"SpanName",		ZBX_TQ_COLUMN_TYPE_STR,			0	},
	{"SpanKind",		ZBX_TQ_COLUMN_TYPE_STR,			0	},
	{"ServiceName",		ZBX_TQ_COLUMN_TYPE_STR,			0	},
	{"ResourceAttributes",	ZBX_TQ_COLUMN_TYPE_ATTRIBUTES,		0	},
	{"SpanAttributes",	ZBX_TQ_COLUMN_TYPE_ATTRIBUTES,		0	},
	{"ScopeName",		ZBX_TQ_COLUMN_TYPE_STR,			0	},
	{"ScopeVersion",	ZBX_TQ_COLUMN_TYPE_STR,			0	},
	{"Duration",		ZBX_TQ_COLUMN_TYPE_NUM,			0	},
	{"StatusCode",		ZBX_TQ_COLUMN_TYPE_STR,			0	},
	{"StatusMessage",	ZBX_TQ_COLUMN_TYPE_STR,			0	},
	{"Events.Name",		ZBX_TQ_COLUMN_TYPE_ARRAY_STR,		0	},
	{"Events.Attributes",	ZBX_TQ_COLUMN_TYPE_ARRAY_ATTRIBUTES,	0	},
	{0}
};

static const tq_column_info_t	column_info_logs[] = {
	{"Timestamp",		ZBX_TQ_COLUMN_TYPE_TIMESTAMP,	0	},
	{"TraceId",		ZBX_TQ_COLUMN_TYPE_STR,		0	},
	{"SpanId",		ZBX_TQ_COLUMN_TYPE_STR,		0	},
	{"TraceFlags",		ZBX_TQ_COLUMN_TYPE_NUM,		TQ_COLUMN_INFO_FLAG_NO_AGGREGATION	},
	{"SeverityText",	ZBX_TQ_COLUMN_TYPE_STR,		0	},
	{"SeverityNumber",	ZBX_TQ_COLUMN_TYPE_NUM,		0	},
	{"ServiceName",		ZBX_TQ_COLUMN_TYPE_STR,		0	},
	{"Body",		ZBX_TQ_COLUMN_TYPE_STR,		0	},
	{"ResourceSchemaUrl",	ZBX_TQ_COLUMN_TYPE_STR,		0	},
	{"ScopeSchemaUrl",	ZBX_TQ_COLUMN_TYPE_STR,		0	},
	{"ScopeName",		ZBX_TQ_COLUMN_TYPE_STR,		0	},
	{"ScopeVersion",	ZBX_TQ_COLUMN_TYPE_STR,		0	},
	{"ResourceAttributes",	ZBX_TQ_COLUMN_TYPE_ATTRIBUTES,	0	},
	{"ScopeAttributes",	ZBX_TQ_COLUMN_TYPE_ATTRIBUTES,	0	},
	{"LogAttributes",	ZBX_TQ_COLUMN_TYPE_ATTRIBUTES,	0	},
	{"EventName",		ZBX_TQ_COLUMN_TYPE_STR,		0	},
	{0}
};

static const tq_column_info_t	column_info_metrics_sum[] = {
	{"ResourceAttributes",		ZBX_TQ_COLUMN_TYPE_ATTRIBUTES,		0	},
	{"ResourceSchemaUrl",		ZBX_TQ_COLUMN_TYPE_STR,			0	},
	{"ScopeName",			ZBX_TQ_COLUMN_TYPE_STR,			0	},
	{"ScopeVersion",		ZBX_TQ_COLUMN_TYPE_STR,			0	},
	{"ScopeAttributes",		ZBX_TQ_COLUMN_TYPE_ATTRIBUTES,		0	},
	{"ScopeDroppedAttrCount",	ZBX_TQ_COLUMN_TYPE_NUM,			0	},
	{"ScopeSchemaUrl",		ZBX_TQ_COLUMN_TYPE_STR,			0	},
	{"ServiceName",			ZBX_TQ_COLUMN_TYPE_STR,			0	},
	{"MetricName",			ZBX_TQ_COLUMN_TYPE_STR,			0	},
	{"MetricDescription",		ZBX_TQ_COLUMN_TYPE_STR,			0	},
	{"MetricUnit",			ZBX_TQ_COLUMN_TYPE_STR,			0	},
	{"Attributes",			ZBX_TQ_COLUMN_TYPE_ATTRIBUTES,		0	},
	{"StartTimeUnix",		ZBX_TQ_COLUMN_TYPE_TIMESTAMP,		0	},
	{"TimeUnix",			ZBX_TQ_COLUMN_TYPE_TIMESTAMP,		0	},
	{"Value",			ZBX_TQ_COLUMN_TYPE_NUM,			0	},
	{"Flags",			ZBX_TQ_COLUMN_TYPE_NUM,		TQ_COLUMN_INFO_FLAG_NO_AGGREGATION	},
	{"Exemplars.FilteredAttributes",ZBX_TQ_COLUMN_TYPE_ARRAY_ATTRIBUTES,	0	},
	{"AggregationTemporality",	ZBX_TQ_COLUMN_TYPE_NUM,		TQ_COLUMN_INFO_FLAG_NO_AGGREGATION	},
	{"IsMonotonic",			ZBX_TQ_COLUMN_TYPE_BOOL,	TQ_COLUMN_INFO_FLAG_NO_AGGREGATION	},
	{0}
};

static const tq_column_info_t	column_info_metrics_gauge[] = {
	{"ResourceAttributes",		ZBX_TQ_COLUMN_TYPE_ATTRIBUTES,		0	},
	{"ResourceSchemaUrl",		ZBX_TQ_COLUMN_TYPE_STR,			0	},
	{"ScopeName",			ZBX_TQ_COLUMN_TYPE_STR,			0	},
	{"ScopeVersion",		ZBX_TQ_COLUMN_TYPE_STR,			0	},
	{"ScopeAttributes",		ZBX_TQ_COLUMN_TYPE_ATTRIBUTES,		0	},
	{"ScopeDroppedAttrCount",	ZBX_TQ_COLUMN_TYPE_NUM,			0	},
	{"ScopeSchemaUrl",		ZBX_TQ_COLUMN_TYPE_STR,			0	},
	{"ServiceName",			ZBX_TQ_COLUMN_TYPE_STR,			0	},
	{"MetricName",			ZBX_TQ_COLUMN_TYPE_STR,			0	},
	{"MetricDescription",		ZBX_TQ_COLUMN_TYPE_STR,			0	},
	{"MetricUnit",			ZBX_TQ_COLUMN_TYPE_STR,			0	},
	{"Attributes",			ZBX_TQ_COLUMN_TYPE_ATTRIBUTES,		0	},
	{"StartTimeUnix",		ZBX_TQ_COLUMN_TYPE_TIMESTAMP,		0	},
	{"TimeUnix",			ZBX_TQ_COLUMN_TYPE_TIMESTAMP,		0	},
	{"Value",			ZBX_TQ_COLUMN_TYPE_NUM,			0	},
	{"Flags",			ZBX_TQ_COLUMN_TYPE_NUM,		TQ_COLUMN_INFO_FLAG_NO_AGGREGATION	},
	{"Exemplars.FilteredAttributes",ZBX_TQ_COLUMN_TYPE_ARRAY_ATTRIBUTES,	0	},
	{0}
};

static const tq_column_info_t	column_info_metrics_histogram[] = {
	{"ResourceAttributes",		ZBX_TQ_COLUMN_TYPE_ATTRIBUTES,		0	},
	{"ResourceSchemaUrl",		ZBX_TQ_COLUMN_TYPE_STR,			0	},
	{"ScopeName",			ZBX_TQ_COLUMN_TYPE_STR,			0	},
	{"ScopeVersion",		ZBX_TQ_COLUMN_TYPE_STR,			0	},
	{"ScopeAttributes",		ZBX_TQ_COLUMN_TYPE_ATTRIBUTES,		0	},
	{"ScopeDroppedAttrCount",	ZBX_TQ_COLUMN_TYPE_NUM,			0	},
	{"ScopeSchemaUrl",		ZBX_TQ_COLUMN_TYPE_STR,			0	},
	{"ServiceName",			ZBX_TQ_COLUMN_TYPE_STR,			0	},
	{"MetricName",			ZBX_TQ_COLUMN_TYPE_STR,			0	},
	{"MetricDescription",		ZBX_TQ_COLUMN_TYPE_STR,			0	},
	{"MetricUnit",			ZBX_TQ_COLUMN_TYPE_STR,			0	},
	{"Attributes",			ZBX_TQ_COLUMN_TYPE_ATTRIBUTES,		0	},
	{"StartTimeUnix",		ZBX_TQ_COLUMN_TYPE_TIMESTAMP,		0	},
	{"TimeUnix",			ZBX_TQ_COLUMN_TYPE_TIMESTAMP,		0	},
	{"Count",			ZBX_TQ_COLUMN_TYPE_NUM,			0	},
	{"Sum",				ZBX_TQ_COLUMN_TYPE_NUM,			0	},
	{"Exemplars.FilteredAttributes",ZBX_TQ_COLUMN_TYPE_ARRAY_ATTRIBUTES,	0	},
	{"Flags",			ZBX_TQ_COLUMN_TYPE_NUM,		TQ_COLUMN_INFO_FLAG_NO_AGGREGATION	},
	{"Min",				ZBX_TQ_COLUMN_TYPE_NUM,			0	},
	{"Max",				ZBX_TQ_COLUMN_TYPE_NUM,			0	},
	{"AggregationTemporality",	ZBX_TQ_COLUMN_TYPE_NUM,		TQ_COLUMN_INFO_FLAG_NO_AGGREGATION	},
	{0}
};

static const tq_column_info_t	column_info_metrics_exponential_histogram[] = {
	{"ResourceAttributes",		ZBX_TQ_COLUMN_TYPE_ATTRIBUTES,		0	},
	{"ResourceSchemaUrl",		ZBX_TQ_COLUMN_TYPE_STR,			0	},
	{"ScopeName",			ZBX_TQ_COLUMN_TYPE_STR,			0	},
	{"ScopeVersion",		ZBX_TQ_COLUMN_TYPE_STR,			0	},
	{"ScopeAttributes",		ZBX_TQ_COLUMN_TYPE_ATTRIBUTES,		0	},
	{"ScopeDroppedAttrCount",	ZBX_TQ_COLUMN_TYPE_NUM,			0	},
	{"ScopeSchemaUrl",		ZBX_TQ_COLUMN_TYPE_STR,			0	},
	{"ServiceName",			ZBX_TQ_COLUMN_TYPE_STR,			0	},
	{"MetricName",			ZBX_TQ_COLUMN_TYPE_STR,			0	},
	{"MetricDescription",		ZBX_TQ_COLUMN_TYPE_STR,			0	},
	{"MetricUnit",			ZBX_TQ_COLUMN_TYPE_STR,			0	},
	{"Attributes",			ZBX_TQ_COLUMN_TYPE_ATTRIBUTES,		0	},
	{"StartTimeUnix",		ZBX_TQ_COLUMN_TYPE_TIMESTAMP,		0	},
	{"TimeUnix",			ZBX_TQ_COLUMN_TYPE_TIMESTAMP,		0	},
	{"Count",			ZBX_TQ_COLUMN_TYPE_NUM,			0	},
	{"Sum",				ZBX_TQ_COLUMN_TYPE_NUM,			0	},
	{"Scale",			ZBX_TQ_COLUMN_TYPE_NUM,			0	},
	{"ZeroCount",			ZBX_TQ_COLUMN_TYPE_NUM,			0	},
	{"PositiveOffset",		ZBX_TQ_COLUMN_TYPE_NUM,			0	},
	{"NegativeOffset",		ZBX_TQ_COLUMN_TYPE_NUM,			0	},
	{"Exemplars.FilteredAttributes",ZBX_TQ_COLUMN_TYPE_ARRAY_ATTRIBUTES,	0	},
	{"Flags",			ZBX_TQ_COLUMN_TYPE_NUM,		TQ_COLUMN_INFO_FLAG_NO_AGGREGATION	},
	{"Min",				ZBX_TQ_COLUMN_TYPE_NUM,			0	},
	{"Max",				ZBX_TQ_COLUMN_TYPE_NUM,			0	},
	{"AggregationTemporality",	ZBX_TQ_COLUMN_TYPE_NUM,		TQ_COLUMN_INFO_FLAG_NO_AGGREGATION	},
	{0}
};

static const tq_column_info_t	*get_column_info_from_arr(const tq_column_info_t *arr, const char *str)
{
	for (int i = 0; NULL != arr[i].name; i++)
	{
		if (0 == strcmp(arr[i].name, str))
			return &arr[i];
	}

	return NULL;
}

const tq_column_info_t	*tq_get_column_info(zbx_tq_signal_type_t signal_type,
		zbx_tq_metric_point_type_t metric_point_type, const char *column)
{
	if (NULL == column)
		return NULL;

	switch (signal_type)
	{
		case ZBX_TQ_SIGNAL_TYPE_APM_TRACES:
			return get_column_info_from_arr(column_info_traces, column);

		case ZBX_TQ_SIGNAL_TYPE_APM_METRICS:
			switch (metric_point_type)
			{
				case ZBX_TQ_METRIC_POINT_TYPE_SUM:
					return get_column_info_from_arr(column_info_metrics_sum, column);
				case ZBX_TQ_METRIC_POINT_TYPE_GAUGE:
					return get_column_info_from_arr(column_info_metrics_gauge, column);
				case ZBX_TQ_METRIC_POINT_TYPE_HISTOGRAM:
					return get_column_info_from_arr(column_info_metrics_histogram, column);
				case ZBX_TQ_METRIC_POINT_TYPE_EXPONENTIAL_HISTOGRAM:
					return get_column_info_from_arr(column_info_metrics_exponential_histogram,
							column);
				default:
					return NULL;
			}

		case ZBX_TQ_SIGNAL_TYPE_APM_LOGS:
			return get_column_info_from_arr(column_info_logs, column);

		default:
			return NULL;
	}
}

zbx_tq_column_type_t	tq_get_column_type(zbx_tq_signal_type_t signal_type,
		zbx_tq_metric_point_type_t metric_point_type, const char *column)
{
	const tq_column_info_t	*info = tq_get_column_info(signal_type, metric_point_type, column);

	return NULL == info ? ZBX_TQ_COLUMN_TYPE_UNKNOWN : info->type;
}

int	tq_column_type_is_array(zbx_tq_column_type_t type)
{
	return ZBX_TQ_COLUMN_TYPE_ARRAY_STR == type || ZBX_TQ_COLUMN_TYPE_ARRAY_NUM == type ||
			ZBX_TQ_COLUMN_TYPE_ARRAY_ATTRIBUTES == type || ZBX_TQ_COLUMN_TYPE_ARRAY_TIMESTAMP == type ||
			ZBX_TQ_COLUMN_TYPE_ARRAY_BOOL == type ? SUCCEED : FAIL;
}

int	tq_column_type_is_attributes(zbx_tq_column_type_t type)
{
	return ZBX_TQ_COLUMN_TYPE_ATTRIBUTES == type || ZBX_TQ_COLUMN_TYPE_ARRAY_ATTRIBUTES == type ? SUCCEED : FAIL;
}

zbx_tq_column_type_t	tq_get_base_column_type(zbx_tq_column_type_t type)
{
	switch (type)
	{
		case ZBX_TQ_COLUMN_TYPE_ATTRIBUTES:
		case ZBX_TQ_COLUMN_TYPE_ARRAY_ATTRIBUTES:
			return ZBX_TQ_COLUMN_TYPE_ATTRIBUTES;

		case ZBX_TQ_COLUMN_TYPE_STR:
		case ZBX_TQ_COLUMN_TYPE_ARRAY_STR:
			return ZBX_TQ_COLUMN_TYPE_STR;

		case ZBX_TQ_COLUMN_TYPE_NUM:
		case ZBX_TQ_COLUMN_TYPE_ARRAY_NUM:
			return ZBX_TQ_COLUMN_TYPE_NUM;

		case ZBX_TQ_COLUMN_TYPE_TIMESTAMP:
		case ZBX_TQ_COLUMN_TYPE_ARRAY_TIMESTAMP:
			return ZBX_TQ_COLUMN_TYPE_TIMESTAMP;

		case ZBX_TQ_COLUMN_TYPE_BOOL:
		case ZBX_TQ_COLUMN_TYPE_ARRAY_BOOL:
			return ZBX_TQ_COLUMN_TYPE_BOOL;

		default:
		case ZBX_TQ_COLUMN_TYPE_UNKNOWN:
			return ZBX_TQ_COLUMN_TYPE_UNKNOWN;
	}
}
