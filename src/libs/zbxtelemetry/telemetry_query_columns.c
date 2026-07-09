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
	{"Timestamp",		ZBX_TQ_COLUMN_TYPE_NUM			},
	{"TraceId",		ZBX_TQ_COLUMN_TYPE_STR			},
	{"SpanId",		ZBX_TQ_COLUMN_TYPE_STR			},
	{"ParentSpanId",	ZBX_TQ_COLUMN_TYPE_STR			},
	{"TraceState",		ZBX_TQ_COLUMN_TYPE_STR			},
	{"SpanName",		ZBX_TQ_COLUMN_TYPE_STR			},
	{"SpanKind",		ZBX_TQ_COLUMN_TYPE_STR			},
	{"ServiceName",		ZBX_TQ_COLUMN_TYPE_STR			},
	{"ResourceAttributes",	ZBX_TQ_COLUMN_TYPE_ATTRIBUTES		},
	{"SpanAttributes",	ZBX_TQ_COLUMN_TYPE_ATTRIBUTES		},
	{"ScopeName",		ZBX_TQ_COLUMN_TYPE_STR			},
	{"ScopeVersion",	ZBX_TQ_COLUMN_TYPE_STR			},
	{"Duration",		ZBX_TQ_COLUMN_TYPE_NUM			},
	{"StatusCode",		ZBX_TQ_COLUMN_TYPE_STR			},
	{"StatusMessage",	ZBX_TQ_COLUMN_TYPE_STR			},
	{"Events.Name",		ZBX_TQ_COLUMN_TYPE_ARRAY_STR		},
	{"Events.Attributes",	ZBX_TQ_COLUMN_TYPE_ARRAY_ATTRIBUTES	},
	{0}
};

static const tq_column_info_t	column_info_apm_logs[] = {
	{"Timestamp",		ZBX_TQ_COLUMN_TYPE_NUM		},
	{"TraceId",		ZBX_TQ_COLUMN_TYPE_STR		},
	{"SpanId",		ZBX_TQ_COLUMN_TYPE_STR		},
	{"TraceFlags",		ZBX_TQ_COLUMN_TYPE_NUM		},
	{"SeverityText",	ZBX_TQ_COLUMN_TYPE_STR		},
	{"SeverityNumber",	ZBX_TQ_COLUMN_TYPE_NUM		},
	{"ServiceName",		ZBX_TQ_COLUMN_TYPE_STR		},
	{"Body",		ZBX_TQ_COLUMN_TYPE_STR		},
	{"ResourceSchemaUrl",	ZBX_TQ_COLUMN_TYPE_STR		},
	{"ScopeSchemaUrl",	ZBX_TQ_COLUMN_TYPE_STR		},
	{"ScopeName",		ZBX_TQ_COLUMN_TYPE_STR		},
	{"ScopeVersion",	ZBX_TQ_COLUMN_TYPE_STR		},
	{"ResourceAttributes",	ZBX_TQ_COLUMN_TYPE_ATTRIBUTES	},
	{"ScopeAttributes",	ZBX_TQ_COLUMN_TYPE_ATTRIBUTES	},
	{"LogAttributes",	ZBX_TQ_COLUMN_TYPE_ATTRIBUTES	},
	{"EventName",		ZBX_TQ_COLUMN_TYPE_STR		},
	{0}
};

static const tq_column_info_t	column_info_apm_metrics_sum[] = {
	{"ResourceAttributes",		ZBX_TQ_COLUMN_TYPE_ATTRIBUTES		},
	{"ResourceSchemaUrl",		ZBX_TQ_COLUMN_TYPE_STR			},
	{"ScopeName",			ZBX_TQ_COLUMN_TYPE_STR			},
	{"ScopeVersion",		ZBX_TQ_COLUMN_TYPE_STR			},
	{"ScopeAttributes",		ZBX_TQ_COLUMN_TYPE_ATTRIBUTES		},
	{"ScopeDroppedAttrCount",	ZBX_TQ_COLUMN_TYPE_NUM			},
	{"ScopeSchemaUrl",		ZBX_TQ_COLUMN_TYPE_STR			},
	{"ServiceName",			ZBX_TQ_COLUMN_TYPE_STR			},
	{"MetricName",			ZBX_TQ_COLUMN_TYPE_STR			},
	{"MetricDescription",		ZBX_TQ_COLUMN_TYPE_STR			},
	{"MetricUnit",			ZBX_TQ_COLUMN_TYPE_STR			},
	{"Attributes",			ZBX_TQ_COLUMN_TYPE_ATTRIBUTES		},
	{"StartTimeUnix",		ZBX_TQ_COLUMN_TYPE_NUM			},
	{"TimeUnix",			ZBX_TQ_COLUMN_TYPE_NUM			},
	{"Value",			ZBX_TQ_COLUMN_TYPE_NUM			},
	{"Flags",			ZBX_TQ_COLUMN_TYPE_NUM			},
	{"Exemplars.FilteredAttributes",ZBX_TQ_COLUMN_TYPE_ARRAY_ATTRIBUTES	},
	{"Exemplars.Value",		ZBX_TQ_COLUMN_TYPE_ARRAY_NUM		},
	{"AggregationTemporality",	ZBX_TQ_COLUMN_TYPE_NUM			},
	{"IsMonotonic",			ZBX_TQ_COLUMN_TYPE_NUM			},
	{0}
};

static const tq_column_info_t	column_info_apm_metrics_gauge[] = {
	{"ResourceAttributes",		ZBX_TQ_COLUMN_TYPE_ATTRIBUTES		},
	{"ResourceSchemaUrl",		ZBX_TQ_COLUMN_TYPE_STR			},
	{"ScopeName",			ZBX_TQ_COLUMN_TYPE_STR			},
	{"ScopeVersion",		ZBX_TQ_COLUMN_TYPE_STR			},
	{"ScopeAttributes",		ZBX_TQ_COLUMN_TYPE_ATTRIBUTES		},
	{"ScopeDroppedAttrCount",	ZBX_TQ_COLUMN_TYPE_NUM			},
	{"ScopeSchemaUrl",		ZBX_TQ_COLUMN_TYPE_STR			},
	{"ServiceName",			ZBX_TQ_COLUMN_TYPE_STR			},
	{"MetricName",			ZBX_TQ_COLUMN_TYPE_STR			},
	{"MetricDescription",		ZBX_TQ_COLUMN_TYPE_STR			},
	{"MetricUnit",			ZBX_TQ_COLUMN_TYPE_STR			},
	{"Attributes",			ZBX_TQ_COLUMN_TYPE_ATTRIBUTES		},
	{"StartTimeUnix",		ZBX_TQ_COLUMN_TYPE_NUM			},
	{"TimeUnix",			ZBX_TQ_COLUMN_TYPE_NUM			},
	{"Value",			ZBX_TQ_COLUMN_TYPE_NUM			},
	{"Flags",			ZBX_TQ_COLUMN_TYPE_NUM			},
	{"Exemplars.FilteredAttributes",ZBX_TQ_COLUMN_TYPE_ARRAY_ATTRIBUTES	},
	{"Exemplars.Value",		ZBX_TQ_COLUMN_TYPE_ARRAY_NUM		},
	{0}
};

static const tq_column_info_t	column_info_apm_metrics_histogram[] = {
	{"ResourceAttributes",		ZBX_TQ_COLUMN_TYPE_ATTRIBUTES		},
	{"ResourceSchemaUrl",		ZBX_TQ_COLUMN_TYPE_STR			},
	{"ScopeName",			ZBX_TQ_COLUMN_TYPE_STR			},
	{"ScopeVersion",		ZBX_TQ_COLUMN_TYPE_STR			},
	{"ScopeAttributes",		ZBX_TQ_COLUMN_TYPE_ATTRIBUTES		},
	{"ScopeDroppedAttrCount",	ZBX_TQ_COLUMN_TYPE_NUM			},
	{"ScopeSchemaUrl",		ZBX_TQ_COLUMN_TYPE_STR			},
	{"ServiceName",			ZBX_TQ_COLUMN_TYPE_STR			},
	{"MetricName",			ZBX_TQ_COLUMN_TYPE_STR			},
	{"MetricDescription",		ZBX_TQ_COLUMN_TYPE_STR			},
	{"MetricUnit",			ZBX_TQ_COLUMN_TYPE_STR			},
	{"Attributes",			ZBX_TQ_COLUMN_TYPE_ATTRIBUTES		},
	{"StartTimeUnix",		ZBX_TQ_COLUMN_TYPE_NUM			},
	{"TimeUnix",			ZBX_TQ_COLUMN_TYPE_NUM			},
	{"Count",			ZBX_TQ_COLUMN_TYPE_NUM			},
	{"Sum",				ZBX_TQ_COLUMN_TYPE_NUM			},
	{"Exemplars.FilteredAttributes",ZBX_TQ_COLUMN_TYPE_ARRAY_ATTRIBUTES	},
	{"Exemplars.Value",		ZBX_TQ_COLUMN_TYPE_ARRAY_NUM		},
	{"Flags",			ZBX_TQ_COLUMN_TYPE_NUM			},
	{"Min",				ZBX_TQ_COLUMN_TYPE_NUM			},
	{"Max",				ZBX_TQ_COLUMN_TYPE_NUM			},
	{"AggregationTemporality",	ZBX_TQ_COLUMN_TYPE_NUM			},
	{0}
};

static const tq_column_info_t	column_info_apm_metrics_exponentialhistogram[] = {
	{"ResourceAttributes",		ZBX_TQ_COLUMN_TYPE_ATTRIBUTES		},
	{"ResourceSchemaUrl",		ZBX_TQ_COLUMN_TYPE_STR			},
	{"ScopeName",			ZBX_TQ_COLUMN_TYPE_STR			},
	{"ScopeVersion",		ZBX_TQ_COLUMN_TYPE_STR			},
	{"ScopeAttributes",		ZBX_TQ_COLUMN_TYPE_ATTRIBUTES		},
	{"ScopeDroppedAttrCount",	ZBX_TQ_COLUMN_TYPE_NUM			},
	{"ScopeSchemaUrl",		ZBX_TQ_COLUMN_TYPE_STR			},
	{"ServiceName",			ZBX_TQ_COLUMN_TYPE_STR			},
	{"MetricName",			ZBX_TQ_COLUMN_TYPE_STR			},
	{"MetricDescription",		ZBX_TQ_COLUMN_TYPE_STR			},
	{"MetricUnit",			ZBX_TQ_COLUMN_TYPE_STR			},
	{"Attributes",			ZBX_TQ_COLUMN_TYPE_ATTRIBUTES		},
	{"StartTimeUnix",		ZBX_TQ_COLUMN_TYPE_NUM			},
	{"TimeUnix",			ZBX_TQ_COLUMN_TYPE_NUM			},
	{"Count",			ZBX_TQ_COLUMN_TYPE_NUM			},
	{"Sum",				ZBX_TQ_COLUMN_TYPE_NUM			},
	{"Scale",			ZBX_TQ_COLUMN_TYPE_NUM			},
	{"ZeroCount",			ZBX_TQ_COLUMN_TYPE_NUM			},
	{"PositiveOffset",		ZBX_TQ_COLUMN_TYPE_NUM			},
	{"NegativeOffset",		ZBX_TQ_COLUMN_TYPE_NUM			},
	{"Exemplars.FilteredAttributes",ZBX_TQ_COLUMN_TYPE_ARRAY_ATTRIBUTES	},
	{"Exemplars.Value",		ZBX_TQ_COLUMN_TYPE_ARRAY_NUM		},
	{"Flags",			ZBX_TQ_COLUMN_TYPE_NUM			},
	{"Min",				ZBX_TQ_COLUMN_TYPE_NUM			},
	{"Max",				ZBX_TQ_COLUMN_TYPE_NUM			},
	{"AggregationTemporality",	ZBX_TQ_COLUMN_TYPE_NUM			},
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
			return get_column_info_from_arr(column_info_apm_traces, column);

		case ZBX_TQ_SIGNAL_TYPE_APM_METRICS:
			switch (metric_point_type)
			{
				case ZBX_TQ_METRIC_POINT_TYPE_SUM:
					return get_column_info_from_arr(column_info_apm_metrics_sum, column);
				case ZBX_TQ_METRIC_POINT_TYPE_GAUGE:
					return get_column_info_from_arr(column_info_apm_metrics_gauge, column);
				case ZBX_TQ_METRIC_POINT_TYPE_HISTOGRAM:
					return get_column_info_from_arr(column_info_apm_metrics_histogram, column);
				case ZBX_TQ_METRIC_POINT_TYPE_EXPONENTIAL_HISTOGRAM:
					return get_column_info_from_arr(column_info_apm_metrics_exponentialhistogram,
							column);
				default:
					return NULL;
			}

		case ZBX_TQ_SIGNAL_TYPE_APM_LOGS:
			return get_column_info_from_arr(column_info_apm_logs, column);

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
	return ZBX_TQ_COLUMN_TYPE_ARRAY_STR == type || ZBX_TQ_COLUMN_TYPE_ARRAY_NUM == type
		|| ZBX_TQ_COLUMN_TYPE_ARRAY_ATTRIBUTES == type ? SUCCEED : FAIL;
}

int	tq_column_type_is_attributes(zbx_tq_column_type_t type)
{
	return ZBX_TQ_COLUMN_TYPE_ATTRIBUTES == type || ZBX_TQ_COLUMN_TYPE_ARRAY_ATTRIBUTES == type ? SUCCEED : FAIL;
}
