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

static const tq_column_info_t	column_info_apm_traces[] = {
	{"Timestamp",		TQ_COLUMN_TYPE_NUM,		NULL,		NULL			},
	{"TraceId",		TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"SpanId",		TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"ParentSpanId",	TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"TraceState",		TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"SpanName",		TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"SpanKind",		TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"ServiceName",		TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"ResourceAttributes",	TQ_COLUMN_TYPE_ATTRIBUTES,	NULL,		NULL			},
	{"ScopeName",		TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"ScopeVersion",	TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"SpanAttributes",	TQ_COLUMN_TYPE_ATTRIBUTES,	NULL,		NULL			},
	{"Duration",		TQ_COLUMN_TYPE_NUM,		NULL,		NULL			},
	{"StatusCode",		TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"StatusMessage",	TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"Events.Timestamp",	TQ_COLUMN_TYPE_ARRAY_NUM,	"Events",	"Events.Timestamp"	},
	{"Events.Name",		TQ_COLUMN_TYPE_ARRAY_STR,	"Events",	"Events.Name"		},
	{"Events.Attributes",	TQ_COLUMN_TYPE_ARRAY_ATTRIBUTES,"Events",	"Events.Attributes"	},
	{"Links.TraceId",	TQ_COLUMN_TYPE_ARRAY_STR,	"Links",	"Links.TraceId"		},
	{"Links.SpanId",	TQ_COLUMN_TYPE_ARRAY_STR,	"Links",	"Links.SpanId"		},
	{"Links.TraceState",	TQ_COLUMN_TYPE_ARRAY_STR,	"Links",	"Links.TraceState"	},
	{"Links.Attributes",	TQ_COLUMN_TYPE_ARRAY_ATTRIBUTES,"Links",	"Links.Attributes"	},
	{0}
};

static const tq_column_info_t	column_info_apm_logs[] = {
	{"Timestamp",			TQ_COLUMN_TYPE_NUM,		NULL,		NULL	},
	{"TraceId",			TQ_COLUMN_TYPE_STR,		NULL,		NULL	},
	{"SpanId",			TQ_COLUMN_TYPE_STR,		NULL,		NULL	},
	{"TraceFlags",			TQ_COLUMN_TYPE_NUM,		NULL,		NULL	},
	{"SeverityText",		TQ_COLUMN_TYPE_STR,		NULL,		NULL	},
	{"SeverityNumber",		TQ_COLUMN_TYPE_NUM,		NULL,		NULL	},
	{"ServiceName",			TQ_COLUMN_TYPE_STR,		NULL,		NULL	},
	{"Body",			TQ_COLUMN_TYPE_STR,		NULL,		NULL	},
	{"ResourceSchemaUrl",		TQ_COLUMN_TYPE_STR,		NULL,		NULL	},
	{"ResourceAttributes",		TQ_COLUMN_TYPE_ATTRIBUTES,	NULL,		NULL	},
	{"ScopeSchemaUrl",		TQ_COLUMN_TYPE_STR,		NULL,		NULL	},
	{"ScopeName",			TQ_COLUMN_TYPE_STR,		NULL,		NULL	},
	{"ScopeVersion",		TQ_COLUMN_TYPE_STR,		NULL,		NULL	},
	{"ScopeAttributes",		TQ_COLUMN_TYPE_ATTRIBUTES,	NULL,		NULL	},
	{"LogAttributes",		TQ_COLUMN_TYPE_ATTRIBUTES,	NULL,		NULL	},
	{0}
};

static const tq_column_info_t	column_info_apm_metrics_sum[] = {
	{"ResourceAttributes",		TQ_COLUMN_TYPE_ATTRIBUTES,	NULL,		NULL			},
	{"ResourceSchemaUrl",		TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"ScopeName",			TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"ScopeVersion",		TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"ScopeAttributes",		TQ_COLUMN_TYPE_ATTRIBUTES,	NULL,		NULL			},
	{"ScopeDroppedAttrCount",	TQ_COLUMN_TYPE_NUM,		NULL,		NULL			},
	{"ScopeSchemaUrl",		TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"ServiceName",			TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"MetricName",			TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"MetricDescription",		TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"MetricUnit",			TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"Attributes",			TQ_COLUMN_TYPE_ATTRIBUTES,	NULL,		NULL			},
	{"StartTimeUnix",		TQ_COLUMN_TYPE_NUM,		NULL,		NULL			},
	{"TimeUnix",			TQ_COLUMN_TYPE_NUM,		NULL,		NULL			},
	{"Value",			TQ_COLUMN_TYPE_NUM,		NULL,		NULL			},
	{"Flags",			TQ_COLUMN_TYPE_NUM,		NULL,		NULL			},
	{"Exemplars.FilteredAttributes",TQ_COLUMN_TYPE_ARRAY_ATTRIBUTES,"Exemplars",    "Exemplars.FilteredAttributes"},
	{"Exemplars.TimeUnix",		TQ_COLUMN_TYPE_ARRAY_NUM,	"Exemplars",	"Exemplars.TimeUnix"	},
	{"Exemplars.Value",		TQ_COLUMN_TYPE_ARRAY_NUM,	"Exemplars",	"Exemplars.Value"	},
	{"Exemplars.SpanId",		TQ_COLUMN_TYPE_ARRAY_STR,	"Exemplars",	"Exemplars.SpanId"	},
	{"Exemplars.TraceId",		TQ_COLUMN_TYPE_ARRAY_STR,	"Exemplars",	"Exemplars.TraceId"	},
	{"AggregationTemporality",	TQ_COLUMN_TYPE_NUM,		NULL,		NULL			},
	{"IsMonotonic",			TQ_COLUMN_TYPE_NUM,		NULL,		NULL			},
	{0}
};

static const tq_column_info_t	column_info_apm_metrics_gauge[] = {
	{"ResourceAttributes",		TQ_COLUMN_TYPE_ATTRIBUTES,	NULL,		NULL			},
	{"ResourceSchemaUrl",		TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"ScopeName",			TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"ScopeVersion",		TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"ScopeAttributes",		TQ_COLUMN_TYPE_ATTRIBUTES,	NULL,		NULL			},
	{"ScopeDroppedAttrCount",	TQ_COLUMN_TYPE_NUM,		NULL,		NULL			},
	{"ScopeSchemaUrl",		TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"ServiceName",			TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"MetricName",			TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"MetricDescription",		TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"MetricUnit",			TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"Attributes",			TQ_COLUMN_TYPE_ATTRIBUTES,	NULL,		NULL			},
	{"StartTimeUnix",		TQ_COLUMN_TYPE_NUM,		NULL,		NULL			},
	{"TimeUnix",			TQ_COLUMN_TYPE_NUM,		NULL,		NULL			},
	{"Value",			TQ_COLUMN_TYPE_NUM,		NULL,		NULL			},
	{"Flags",			TQ_COLUMN_TYPE_NUM,		NULL,		NULL			},
	{"Exemplars.FilteredAttributes",TQ_COLUMN_TYPE_ARRAY_ATTRIBUTES,"Exemplars",    "Exemplars.FilteredAttributes"},
	{"Exemplars.TimeUnix",		TQ_COLUMN_TYPE_ARRAY_NUM,	"Exemplars",	"Exemplars.TimeUnix"	},
	{"Exemplars.Value",		TQ_COLUMN_TYPE_ARRAY_NUM,	"Exemplars",	"Exemplars.Value"	},
	{"Exemplars.SpanId",		TQ_COLUMN_TYPE_ARRAY_STR,	"Exemplars",	"Exemplars.SpanId"	},
	{"Exemplars.TraceId",		TQ_COLUMN_TYPE_ARRAY_STR,	"Exemplars",	"Exemplars.TraceId"	},
	{0}
};

static const tq_column_info_t	column_info_apm_metrics_histogram[] = {
	{"ResourceAttributes",		TQ_COLUMN_TYPE_ATTRIBUTES,	NULL,		NULL			},
	{"ResourceSchemaUrl",		TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"ScopeName",			TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"ScopeVersion",		TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"ScopeAttributes",		TQ_COLUMN_TYPE_ATTRIBUTES,	NULL,		NULL			},
	{"ScopeDroppedAttrCount",	TQ_COLUMN_TYPE_NUM,		NULL,		NULL			},
	{"ScopeSchemaUrl",		TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"ServiceName",			TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"MetricName",			TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"MetricDescription",		TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"MetricUnit",			TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"Attributes",			TQ_COLUMN_TYPE_ATTRIBUTES,	NULL,		NULL			},
	{"StartTimeUnix",		TQ_COLUMN_TYPE_NUM,		NULL,		NULL			},
	{"TimeUnix",			TQ_COLUMN_TYPE_NUM,		NULL,		NULL			},
	{"Count",			TQ_COLUMN_TYPE_NUM,		NULL,		NULL			},
	{"Sum",				TQ_COLUMN_TYPE_NUM,		NULL,		NULL			},
	{"BucketCounts",		TQ_COLUMN_TYPE_ARRAY_NUM,	"BucketCounts",	"BucketCounts.value"	},
	{"ExplicitBounds",		TQ_COLUMN_TYPE_ARRAY_NUM,	"ExplicitBounds","ExplicitBounds.value"	},
	{"Exemplars.FilteredAttributes",TQ_COLUMN_TYPE_ARRAY_ATTRIBUTES,"Exemplars",	"Exemplars.FilteredAttributes"},
	{"Exemplars.TimeUnix",		TQ_COLUMN_TYPE_ARRAY_NUM,	"Exemplars",	"Exemplars.TimeUnix"	},
	{"Exemplars.Value",		TQ_COLUMN_TYPE_ARRAY_NUM,	"Exemplars",	"Exemplars.Value"	},
	{"Exemplars.SpanId",		TQ_COLUMN_TYPE_ARRAY_STR,	"Exemplars",	"Exemplars.SpanId"	},
	{"Exemplars.TraceId",		TQ_COLUMN_TYPE_ARRAY_STR,	"Exemplars",	"Exemplars.TraceId"	},
	{"Flags",			TQ_COLUMN_TYPE_NUM,		NULL,		NULL			},
	{"Min",				TQ_COLUMN_TYPE_NUM,		NULL,		NULL			},
	{"Max",				TQ_COLUMN_TYPE_NUM,		NULL,		NULL			},
	{"AggregationTemporality",	TQ_COLUMN_TYPE_NUM,		NULL,		NULL			},
	{0}
};

static const tq_column_info_t	column_info_apm_metrics_exponentialhistogram[] = {
	{"ResourceAttributes",		TQ_COLUMN_TYPE_ATTRIBUTES,	NULL,		NULL			},
	{"ResourceSchemaUrl",		TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"ScopeName",			TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"ScopeVersion",		TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"ScopeAttributes",		TQ_COLUMN_TYPE_ATTRIBUTES,	NULL,		NULL			},
	{"ScopeDroppedAttrCount",	TQ_COLUMN_TYPE_NUM,		NULL,		NULL			},
	{"ScopeSchemaUrl",		TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"ServiceName",			TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"MetricName",			TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"MetricDescription",		TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"MetricUnit",			TQ_COLUMN_TYPE_STR,		NULL,		NULL			},
	{"Attributes",			TQ_COLUMN_TYPE_ATTRIBUTES,	NULL,		NULL			},
	{"StartTimeUnix",		TQ_COLUMN_TYPE_NUM,		NULL,		NULL			},
	{"TimeUnix",			TQ_COLUMN_TYPE_NUM,		NULL,		NULL			},
	{"Count",			TQ_COLUMN_TYPE_NUM,		NULL,		NULL			},
	{"Sum",				TQ_COLUMN_TYPE_NUM,		NULL,		NULL			},
	{"Scale",			TQ_COLUMN_TYPE_NUM,		NULL,		NULL			},
	{"ZeroCount",			TQ_COLUMN_TYPE_NUM,		NULL,		NULL			},
	{"PositiveOffset",		TQ_COLUMN_TYPE_NUM,		NULL,		NULL			},
	{"PositiveBucketCounts",	TQ_COLUMN_TYPE_ARRAY_NUM,	NULL,		NULL			},
	{"NegativeOffset",		TQ_COLUMN_TYPE_NUM,		"NegativeOffset","NegativeOffset.value"	},
	{"NegativeBucketCounts",	TQ_COLUMN_TYPE_ARRAY_NUM,  "NegativeBucketCounts","NegativeBucketCounts.value"},
	{"Exemplars.FilteredAttributes",TQ_COLUMN_TYPE_ARRAY_ATTRIBUTES,"Exemplars",	"Exemplars.FilteredAttributes"},
	{"Exemplars.TimeUnix",		TQ_COLUMN_TYPE_ARRAY_NUM,	"Exemplars",	"Exemplars.TimeUnix"	},
	{"Exemplars.Value",		TQ_COLUMN_TYPE_ARRAY_NUM,	"Exemplars",	"Exemplars.Value"	},
	{"Exemplars.SpanId",		TQ_COLUMN_TYPE_ARRAY_STR,	"Exemplars",	"Exemplars.SpanId"	},
	{"Exemplars.TraceId",		TQ_COLUMN_TYPE_ARRAY_STR,	"Exemplars",	"Exemplars.TraceId"	},
	{"Flags",			TQ_COLUMN_TYPE_NUM,		NULL,		NULL			},
	{"Min",				TQ_COLUMN_TYPE_NUM,		NULL,		NULL			},
	{"Max",				TQ_COLUMN_TYPE_NUM,		NULL,		NULL			},
	{"AggregationTemporality",	TQ_COLUMN_TYPE_NUM,		NULL,		NULL			},
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

tq_column_type_t	tq_get_column_type(zbx_tq_category_t category, zbx_tq_metric_type_t metric_type,
		const char *column_name)
{
	const tq_column_info_t	*info = tq_get_column_info(category, metric_type, column_name);

	return NULL == info ? TQ_COLUMN_TYPE_UNKNOWN : info->type;
}

int	tq_column_type_is_array(tq_column_type_t type)
{
	return TQ_COLUMN_TYPE_ARRAY_STR == type || TQ_COLUMN_TYPE_ARRAY_NUM == type
		|| TQ_COLUMN_TYPE_ARRAY_ATTRIBUTES == type ? SUCCEED : FAIL;
}

int	tq_column_type_is_attributes(tq_column_type_t type)
{
	return TQ_COLUMN_TYPE_ATTRIBUTES == type || TQ_COLUMN_TYPE_ARRAY_ATTRIBUTES == type ? SUCCEED : FAIL;
}
