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

/* TODO: sync with real schema */

static const tq_column_info_t	column_info_apm_traces[] = {
	{"Timestamp",		ZBX_TQ_COLUMN_TYPE_NUM,		NULL,		NULL			},
	{"TraceId",		ZBX_TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"SpanId",		ZBX_TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"ParentSpanId",	ZBX_TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"TraceState",		ZBX_TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"SpanName",		ZBX_TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"SpanKind",		ZBX_TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"ServiceName",		ZBX_TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"ResourceAttributes",	ZBX_TQ_COLUMN_TYPE_ATTRIBUTES,	NULL,		NULL			},
	{"SpanAttributes",	ZBX_TQ_COLUMN_TYPE_ATTRIBUTES,	NULL,		NULL			},
	{"ScopeName",		ZBX_TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"ScopeVersion",	ZBX_TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"Duration",		ZBX_TQ_COLUMN_TYPE_NUM,		NULL,		NULL			},
	{"StatusCode",		ZBX_TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"StatusMessage",	ZBX_TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"Events.Timestamp",	ZBX_TQ_COLUMN_TYPE_ARRAY_NUM,	"Events",	"Events.Timestamp"	},
	{"Events.Name",		ZBX_TQ_COLUMN_TYPE_ARRAY_STR,	"Events",	"Events.Name"		},
	{"Events.Attributes",	ZBX_TQ_COLUMN_TYPE_ARRAY_ATTRIBUTES,"Events",	"Events.Attributes"	},
	{"Links.TraceId",	ZBX_TQ_COLUMN_TYPE_ARRAY_STR,	"Links",	"Links.TraceId"		},
	{"Links.SpanId",	ZBX_TQ_COLUMN_TYPE_ARRAY_STR,	"Links",	"Links.SpanId"		},
	{"Links.TraceState",	ZBX_TQ_COLUMN_TYPE_ARRAY_STR,	"Links",	"Links.TraceState"	},
	{"Links.Attributes",	ZBX_TQ_COLUMN_TYPE_ARRAY_ATTRIBUTES,"Links",	"Links.Attributes"	},
	{0}
};

static const tq_column_info_t	column_info_apm_logs[] = {
	{"Timestamp",		ZBX_TQ_COLUMN_TYPE_NUM,		NULL,		NULL	},
	{"TraceId",		ZBX_TQ_COLUMN_TYPE_STR,		NULL,		NULL	},
	{"SpanId",		ZBX_TQ_COLUMN_TYPE_STR,		NULL,		NULL	},
	{"TraceFlags",		ZBX_TQ_COLUMN_TYPE_NUM,		NULL,		NULL	},
	{"SeverityText",	ZBX_TQ_COLUMN_TYPE_STR,		NULL,		NULL	},
	{"SeverityNumber",	ZBX_TQ_COLUMN_TYPE_NUM,		NULL,		NULL	},
	{"ServiceName",		ZBX_TQ_COLUMN_TYPE_STR,		NULL,		NULL	},
	{"Body",		ZBX_TQ_COLUMN_TYPE_STR,		NULL,		NULL	},
	{"ResourceSchemaUrl",	ZBX_TQ_COLUMN_TYPE_STR,		NULL,		NULL	},
	{"ResourceAttributes",	ZBX_TQ_COLUMN_TYPE_ATTRIBUTES,	NULL,		NULL	},
	{"ScopeSchemaUrl",	ZBX_TQ_COLUMN_TYPE_STR,		NULL,		NULL	},
	{"ScopeName",		ZBX_TQ_COLUMN_TYPE_STR,		NULL,		NULL	},
	{"ScopeVersion",	ZBX_TQ_COLUMN_TYPE_STR,		NULL,		NULL	},
	{"ScopeAttributes",	ZBX_TQ_COLUMN_TYPE_ATTRIBUTES,	NULL,		NULL	},
	{"LogAttributes",	ZBX_TQ_COLUMN_TYPE_ATTRIBUTES,	NULL,		NULL	},
	{"EventName",		ZBX_TQ_COLUMN_TYPE_STR,		NULL,		NULL	},
	{0}
};

static const tq_column_info_t	column_info_apm_metrics_sum[] = {
	{"ResourceAttributes",		ZBX_TQ_COLUMN_TYPE_ATTRIBUTES,	NULL,		NULL			},
	{"ResourceSchemaUrl",		ZBX_TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"ScopeName",			ZBX_TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"ScopeVersion",		ZBX_TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"ScopeAttributes",		ZBX_TQ_COLUMN_TYPE_ATTRIBUTES,	NULL,		NULL			},
	{"ScopeDroppedAttrCount",	ZBX_TQ_COLUMN_TYPE_NUM,		NULL,		NULL			},
	{"ScopeSchemaUrl",		ZBX_TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"ServiceName",			ZBX_TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"MetricName",			ZBX_TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"MetricDescription",		ZBX_TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"MetricUnit",			ZBX_TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"Attributes",			ZBX_TQ_COLUMN_TYPE_ATTRIBUTES,	NULL,		NULL			},
	{"StartTimeUnix",		ZBX_TQ_COLUMN_TYPE_NUM,		NULL,		NULL			},
	{"TimeUnix",			ZBX_TQ_COLUMN_TYPE_NUM,		NULL,		NULL			},
	{"Value",			ZBX_TQ_COLUMN_TYPE_NUM,		NULL,		NULL			},
	{"Flags",			ZBX_TQ_COLUMN_TYPE_NUM,		NULL,		NULL			},
	{"Exemplars.FilteredAttributes",ZBX_TQ_COLUMN_TYPE_ARRAY_ATTRIBUTES,"Exemplars","Exemplars.FilteredAttributes"},
	{"Exemplars.TimeUnix",		ZBX_TQ_COLUMN_TYPE_ARRAY_NUM,	"Exemplars",	"Exemplars.TimeUnix"	},
	{"Exemplars.Value",		ZBX_TQ_COLUMN_TYPE_ARRAY_NUM,	"Exemplars",	"Exemplars.Value"	},
	{"Exemplars.SpanId",		ZBX_TQ_COLUMN_TYPE_ARRAY_STR,	"Exemplars",	"Exemplars.SpanId"	},
	{"Exemplars.TraceId",		ZBX_TQ_COLUMN_TYPE_ARRAY_STR,	"Exemplars",	"Exemplars.TraceId"	},
	{"AggregationTemporality",	ZBX_TQ_COLUMN_TYPE_NUM,		NULL,		NULL			},
	{"IsMonotonic",			ZBX_TQ_COLUMN_TYPE_NUM,		NULL,		NULL			},
	{0}
};

static const tq_column_info_t	column_info_apm_metrics_gauge[] = {
	{"ResourceAttributes",		ZBX_TQ_COLUMN_TYPE_ATTRIBUTES,	NULL,		NULL			},
	{"ResourceSchemaUrl",		ZBX_TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"ScopeName",			ZBX_TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"ScopeVersion",		ZBX_TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"ScopeAttributes",		ZBX_TQ_COLUMN_TYPE_ATTRIBUTES,	NULL,		NULL			},
	{"ScopeDroppedAttrCount",	ZBX_TQ_COLUMN_TYPE_NUM,		NULL,		NULL			},
	{"ScopeSchemaUrl",		ZBX_TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"ServiceName",			ZBX_TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"MetricName",			ZBX_TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"MetricDescription",		ZBX_TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"MetricUnit",			ZBX_TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"Attributes",			ZBX_TQ_COLUMN_TYPE_ATTRIBUTES,	NULL,		NULL			},
	{"StartTimeUnix",		ZBX_TQ_COLUMN_TYPE_NUM,		NULL,		NULL			},
	{"TimeUnix",			ZBX_TQ_COLUMN_TYPE_NUM,		NULL,		NULL			},
	{"Value",			ZBX_TQ_COLUMN_TYPE_NUM,		NULL,		NULL			},
	{"Flags",			ZBX_TQ_COLUMN_TYPE_NUM,		NULL,		NULL			},
	{"Exemplars.FilteredAttributes",ZBX_TQ_COLUMN_TYPE_ARRAY_ATTRIBUTES,"Exemplars","Exemplars.FilteredAttributes"},
	{"Exemplars.TimeUnix",		ZBX_TQ_COLUMN_TYPE_ARRAY_NUM,	"Exemplars",	"Exemplars.TimeUnix"	},
	{"Exemplars.Value",		ZBX_TQ_COLUMN_TYPE_ARRAY_NUM,	"Exemplars",	"Exemplars.Value"	},
	{"Exemplars.SpanId",		ZBX_TQ_COLUMN_TYPE_ARRAY_STR,	"Exemplars",	"Exemplars.SpanId"	},
	{"Exemplars.TraceId",		ZBX_TQ_COLUMN_TYPE_ARRAY_STR,	"Exemplars",	"Exemplars.TraceId"	},
	{0}
};

static const tq_column_info_t	column_info_apm_metrics_histogram[] = {
	{"ResourceAttributes",		ZBX_TQ_COLUMN_TYPE_ATTRIBUTES,	NULL,		NULL			},
	{"ResourceSchemaUrl",		ZBX_TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"ScopeName",			ZBX_TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"ScopeVersion",		ZBX_TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"ScopeAttributes",		ZBX_TQ_COLUMN_TYPE_ATTRIBUTES,	NULL,		NULL			},
	{"ScopeDroppedAttrCount",	ZBX_TQ_COLUMN_TYPE_NUM,		NULL,		NULL			},
	{"ScopeSchemaUrl",		ZBX_TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"ServiceName",			ZBX_TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"MetricName",			ZBX_TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"MetricDescription",		ZBX_TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"MetricUnit",			ZBX_TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"Attributes",			ZBX_TQ_COLUMN_TYPE_ATTRIBUTES,	NULL,		NULL			},
	{"StartTimeUnix",		ZBX_TQ_COLUMN_TYPE_NUM,		NULL,		NULL			},
	{"TimeUnix",			ZBX_TQ_COLUMN_TYPE_NUM,		NULL,		NULL			},
	{"Count",			ZBX_TQ_COLUMN_TYPE_NUM,		NULL,		NULL			},
	{"Sum",				ZBX_TQ_COLUMN_TYPE_NUM,		NULL,		NULL			},
	{"BucketCounts",		ZBX_TQ_COLUMN_TYPE_ARRAY_NUM,	"BucketCounts",	"BucketCounts.value"	},
	{"ExplicitBounds",		ZBX_TQ_COLUMN_TYPE_ARRAY_NUM,	"ExplicitBounds","ExplicitBounds.value"	},
	{"Exemplars.FilteredAttributes",ZBX_TQ_COLUMN_TYPE_ARRAY_ATTRIBUTES,"Exemplars","Exemplars.FilteredAttributes"},
	{"Exemplars.TimeUnix",		ZBX_TQ_COLUMN_TYPE_ARRAY_NUM,	"Exemplars",	"Exemplars.TimeUnix"	},
	{"Exemplars.Value",		ZBX_TQ_COLUMN_TYPE_ARRAY_NUM,	"Exemplars",	"Exemplars.Value"	},
	{"Exemplars.SpanId",		ZBX_TQ_COLUMN_TYPE_ARRAY_STR,	"Exemplars",	"Exemplars.SpanId"	},
	{"Exemplars.TraceId",		ZBX_TQ_COLUMN_TYPE_ARRAY_STR,	"Exemplars",	"Exemplars.TraceId"	},
	{"Flags",			ZBX_TQ_COLUMN_TYPE_NUM,		NULL,		NULL			},
	{"Min",				ZBX_TQ_COLUMN_TYPE_NUM,		NULL,		NULL			},
	{"Max",				ZBX_TQ_COLUMN_TYPE_NUM,		NULL,		NULL			},
	{"AggregationTemporality",	ZBX_TQ_COLUMN_TYPE_NUM,		NULL,		NULL			},
	{0}
};

static const tq_column_info_t	column_info_apm_metrics_exponentialhistogram[] = {
	{"ResourceAttributes",		ZBX_TQ_COLUMN_TYPE_ATTRIBUTES,	NULL,		NULL			},
	{"ResourceSchemaUrl",		ZBX_TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"ScopeName",			ZBX_TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"ScopeVersion",		ZBX_TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"ScopeAttributes",		ZBX_TQ_COLUMN_TYPE_ATTRIBUTES,	NULL,		NULL			},
	{"ScopeDroppedAttrCount",	ZBX_TQ_COLUMN_TYPE_NUM,		NULL,		NULL			},
	{"ScopeSchemaUrl",		ZBX_TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"ServiceName",			ZBX_TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"MetricName",			ZBX_TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"MetricDescription",		ZBX_TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"MetricUnit",			ZBX_TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"Attributes",			ZBX_TQ_COLUMN_TYPE_ATTRIBUTES,	NULL,		NULL			},
	{"StartTimeUnix",		ZBX_TQ_COLUMN_TYPE_NUM,		NULL,		NULL			},
	{"TimeUnix",			ZBX_TQ_COLUMN_TYPE_NUM,		NULL,		NULL			},
	{"Count",			ZBX_TQ_COLUMN_TYPE_NUM,		NULL,		NULL			},
	{"Sum",				ZBX_TQ_COLUMN_TYPE_NUM,		NULL,		NULL			},
	{"Scale",			ZBX_TQ_COLUMN_TYPE_NUM,		NULL,		NULL			},
	{"ZeroCount",			ZBX_TQ_COLUMN_TYPE_NUM,		NULL,		NULL			},
	{"PositiveOffset",		ZBX_TQ_COLUMN_TYPE_NUM,		NULL,		NULL			},
	{"PositiveBucketCounts",	ZBX_TQ_COLUMN_TYPE_ARRAY_NUM,	NULL,		NULL			},
	{"NegativeOffset",		ZBX_TQ_COLUMN_TYPE_NUM,		"NegativeOffset","NegativeOffset.value"	},
	{"NegativeBucketCounts",      ZBX_TQ_COLUMN_TYPE_ARRAY_NUM,"NegativeBucketCounts","NegativeBucketCounts.value"},
	{"Exemplars.FilteredAttributes",ZBX_TQ_COLUMN_TYPE_ARRAY_ATTRIBUTES,"Exemplars","Exemplars.FilteredAttributes"},
	{"Exemplars.TimeUnix",		ZBX_TQ_COLUMN_TYPE_ARRAY_NUM,	"Exemplars",	"Exemplars.TimeUnix"	},
	{"Exemplars.Value",		ZBX_TQ_COLUMN_TYPE_ARRAY_NUM,	"Exemplars",	"Exemplars.Value"	},
	{"Exemplars.SpanId",		ZBX_TQ_COLUMN_TYPE_ARRAY_STR,	"Exemplars",	"Exemplars.SpanId"	},
	{"Exemplars.TraceId",		ZBX_TQ_COLUMN_TYPE_ARRAY_STR,	"Exemplars",	"Exemplars.TraceId"	},
	{"Flags",			ZBX_TQ_COLUMN_TYPE_NUM,		NULL,		NULL			},
	{"Min",				ZBX_TQ_COLUMN_TYPE_NUM,		NULL,		NULL			},
	{"Max",				ZBX_TQ_COLUMN_TYPE_NUM,		NULL,		NULL			},
	{"AggregationTemporality",	ZBX_TQ_COLUMN_TYPE_NUM,		NULL,		NULL			},
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

const tq_column_info_t	*tq_get_column_info(zbx_tq_category_t category, zbx_tq_metric_type_t metric_type,
		const char *column_name)
{
	if (NULL == column_name)
		return NULL;

	switch (category)
	{
		case ZBX_TQ_CATEGORY_APM_TRACES:
			return get_column_info_from_arr(column_info_apm_traces, column_name);

		case ZBX_TQ_CATEGORY_APM_METRICS:
			switch (metric_type)
			{
				case ZBX_TQ_METRIC_TYPE_SUM:
					return get_column_info_from_arr(column_info_apm_metrics_sum, column_name);
				case ZBX_TQ_METRIC_TYPE_GAUGE:
					return get_column_info_from_arr(column_info_apm_metrics_gauge, column_name);
				case ZBX_TQ_METRIC_TYPE_HISTOGRAM:
					return get_column_info_from_arr(column_info_apm_metrics_histogram, column_name);
				case ZBX_TQ_METRIC_TYPE_EXPONENTIAL_HISTOGRAM:
					return get_column_info_from_arr(column_info_apm_metrics_exponentialhistogram,
							column_name);
				default:
					return NULL;
			}

		case ZBX_TQ_CATEGORY_APM_LOGS:
			return get_column_info_from_arr(column_info_apm_logs, column_name);

		default:
			return NULL;
	}
}

zbx_tq_column_type_t	tq_get_column_type(zbx_tq_category_t category, zbx_tq_metric_type_t metric_type,
		const char *column_name)
{
	const tq_column_info_t	*info = tq_get_column_info(category, metric_type, column_name);

	return NULL == info ? ZBX_TQ_COLUMN_TYPE_UNKNOWN : info->type;
}

int	tq_column_type_is_array(zbx_tq_column_type_t type)
{
	return ZBX_TQ_COLUMN_TYPE_ARRAY_STR == type || ZBX_TQ_COLUMN_TYPE_ARRAY_NUM == type
		|| ZBX_TQ_COLUMN_TYPE_ARRAY_ATTRIBUTES == type ? SUCCEED : FAIL;
}

int	tq_column_type_is_attributes(zbx_tq_column_type_t type)
{
	return ZBX_TQ_COLUMN_TYPE_ATTRIBUTES == type || ZBX_TQ_COLUMN_TYPE_ARRAY_ATTRIBUTES == type ? SUCCEED : FAIL;
}
