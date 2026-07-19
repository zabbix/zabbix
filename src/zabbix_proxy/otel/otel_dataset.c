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

#include "otel_dataset.h"
#include "zbxcommon.h"
#include "zbxstr.h"

ZBX_VECTOR_LITE_IMPL(otel_row, zbx_otel_row_t)

static const zbx_otel_col_t	otel_col_metrics_gauge[] = {
	{OTEL_COL_MAP},			/* ResourceAttributes */
	{OTEL_COL_STRING},		/* ResourceSchemaUrl */
	{OTEL_COL_STRING},		/* ScopeName */
	{OTEL_COL_STRING},		/* ScopeVersion */
	{OTEL_COL_MAP},			/* ScopeAttributes */
	{OTEL_COL_UINT32},		/* ScopeDroppedAttrCount */
	{OTEL_COL_STRING},		/* ScopeSchemaUrl */
	{OTEL_COL_STRING},		/* ServiceName */
	{OTEL_COL_STRING},		/* MetricName */
	{OTEL_COL_STRING},		/* MetricDescription */
	{OTEL_COL_STRING},		/* MetricUnit */
	{OTEL_COL_MAP},			/* Attributes */
	{OTEL_COL_DATETIME},		/* StartTimeUnix */
	{OTEL_COL_DATETIME},		/* TimeUnix */
	{OTEL_COL_FLOAT64},		/* Value */
	{OTEL_COL_UINT32},		/* Flags */
	{OTEL_COL_ARRAY_MAP},		/* Exemplars.FilteredAttributes */
	{OTEL_COL_ARRAY_DATETIME},	/* Exemplars.TimeUnix */
	{OTEL_COL_ARRAY_FLOAT64},	/* Exemplars.Value */
	{OTEL_COL_ARRAY_STRING},	/* Exemplars.SpanId */
	{OTEL_COL_ARRAY_STRING}		/* Exemplars.TraceId */
};

static const zbx_otel_col_t	otel_col_metrics_sum[] = {
	{OTEL_COL_MAP},			/* ResourceAttributes */
	{OTEL_COL_STRING},		/* ResourceSchemaUrl */
	{OTEL_COL_STRING},		/* ScopeName */
	{OTEL_COL_STRING},		/* ScopeVersion */
	{OTEL_COL_MAP},			/* ScopeAttributes */
	{OTEL_COL_UINT32},		/* ScopeDroppedAttrCount */
	{OTEL_COL_STRING},		/* ScopeSchemaUrl */
	{OTEL_COL_STRING},		/* ServiceName */
	{OTEL_COL_STRING},		/* MetricName */
	{OTEL_COL_STRING},		/* MetricDescription */
	{OTEL_COL_STRING},		/* MetricUnit */
	{OTEL_COL_MAP},			/* Attributes */
	{OTEL_COL_DATETIME},		/* StartTimeUnix */
	{OTEL_COL_DATETIME},		/* TimeUnix */
	{OTEL_COL_FLOAT64},		/* Value */
	{OTEL_COL_UINT32},		/* Flags */
	{OTEL_COL_ARRAY_MAP},		/* Exemplars.FilteredAttributes */
	{OTEL_COL_ARRAY_DATETIME},	/* Exemplars.TimeUnix */
	{OTEL_COL_ARRAY_FLOAT64},	/* Exemplars.Value */
	{OTEL_COL_ARRAY_STRING},	/* Exemplars.SpanId */
	{OTEL_COL_ARRAY_STRING},	/* Exemplars.TraceId */
	{OTEL_COL_INT32},		/* AggregationTemporality */
	{OTEL_COL_BOOL}			/* IsMonotonic */
};

static const zbx_otel_col_t	otel_col_metrics_histogram[] = {
	{OTEL_COL_MAP},			/* ResourceAttributes */
	{OTEL_COL_STRING},		/* ResourceSchemaUrl */
	{OTEL_COL_STRING},		/* ScopeName */
	{OTEL_COL_STRING},		/* ScopeVersion */
	{OTEL_COL_MAP},			/* ScopeAttributes */
	{OTEL_COL_UINT32},		/* ScopeDroppedAttrCount */
	{OTEL_COL_STRING},		/* ScopeSchemaUrl */
	{OTEL_COL_STRING},		/* ServiceName */
	{OTEL_COL_STRING},		/* MetricName */
	{OTEL_COL_STRING},		/* MetricDescription */
	{OTEL_COL_STRING},		/* MetricUnit */
	{OTEL_COL_MAP},			/* Attributes */
	{OTEL_COL_DATETIME},		/* StartTimeUnix */
	{OTEL_COL_DATETIME},		/* TimeUnix */
	{OTEL_COL_UINT64},		/* Count */
	{OTEL_COL_FLOAT64},		/* Sum */
	{OTEL_COL_ARRAY_UINT64},	/* BucketCounts */
	{OTEL_COL_ARRAY_FLOAT64},	/* ExplicitBounds */
	{OTEL_COL_ARRAY_MAP},		/* Exemplars.FilteredAttributes */
	{OTEL_COL_ARRAY_DATETIME},	/* Exemplars.TimeUnix */
	{OTEL_COL_ARRAY_FLOAT64},	/* Exemplars.Value */
	{OTEL_COL_ARRAY_STRING},	/* Exemplars.SpanId */
	{OTEL_COL_ARRAY_STRING},	/* Exemplars.TraceId */
	{OTEL_COL_UINT32},		/* Flags */
	{OTEL_COL_FLOAT64},		/* Min */
	{OTEL_COL_FLOAT64},		/* Max */
	{OTEL_COL_INT32}		/* AggregationTemporality */
};

static const zbx_otel_col_t	otel_col_metrics_exponential_histogram[] = {
	{OTEL_COL_MAP},			/* ResourceAttributes */
	{OTEL_COL_STRING},		/* ResourceSchemaUrl */
	{OTEL_COL_STRING},		/* ScopeName */
	{OTEL_COL_STRING},		/* ScopeVersion */
	{OTEL_COL_MAP},			/* ScopeAttributes */
	{OTEL_COL_UINT32},		/* ScopeDroppedAttrCount */
	{OTEL_COL_STRING},		/* ScopeSchemaUrl */
	{OTEL_COL_STRING},		/* ServiceName */
	{OTEL_COL_STRING},		/* MetricName */
	{OTEL_COL_STRING},		/* MetricDescription */
	{OTEL_COL_STRING},		/* MetricUnit */
	{OTEL_COL_MAP},			/* Attributes */
	{OTEL_COL_DATETIME},		/* StartTimeUnix */
	{OTEL_COL_DATETIME},		/* TimeUnix */
	{OTEL_COL_UINT64},		/* Count */
	{OTEL_COL_FLOAT64},		/* Sum */
	{OTEL_COL_INT32},		/* Scale */
	{OTEL_COL_UINT64},		/* ZeroCount */
	{OTEL_COL_INT32},		/* PositiveOffset */
	{OTEL_COL_ARRAY_UINT64},	/* PositiveBucketCounts */
	{OTEL_COL_INT32},		/* NegativeOffset */
	{OTEL_COL_ARRAY_UINT64},	/* NegativeBucketCounts */
	{OTEL_COL_ARRAY_MAP},		/* Exemplars.FilteredAttributes */
	{OTEL_COL_ARRAY_DATETIME},	/* Exemplars.TimeUnix */
	{OTEL_COL_ARRAY_FLOAT64},	/* Exemplars.Value */
	{OTEL_COL_ARRAY_STRING},	/* Exemplars.SpanId */
	{OTEL_COL_ARRAY_STRING},	/* Exemplars.TraceId */
	{OTEL_COL_UINT32},		/* Flags */
	{OTEL_COL_FLOAT64},		/* Min */
	{OTEL_COL_FLOAT64},		/* Max */
	{OTEL_COL_INT32}		/* AggregationTemporality */
};

static const zbx_otel_col_t	otel_col_metrics_summary[] = {
	{OTEL_COL_MAP},			/* ResourceAttributes */
	{OTEL_COL_STRING},		/* ResourceSchemaUrl */
	{OTEL_COL_STRING},		/* ScopeName */
	{OTEL_COL_STRING},		/* ScopeVersion */
	{OTEL_COL_MAP},			/* ScopeAttributes */
	{OTEL_COL_UINT32},		/* ScopeDroppedAttrCount */
	{OTEL_COL_STRING},		/* ScopeSchemaUrl */
	{OTEL_COL_STRING},		/* ServiceName */
	{OTEL_COL_STRING},		/* MetricName */
	{OTEL_COL_STRING},		/* MetricDescription */
	{OTEL_COL_STRING},		/* MetricUnit */
	{OTEL_COL_MAP},			/* Attributes */
	{OTEL_COL_DATETIME},		/* StartTimeUnix */
	{OTEL_COL_DATETIME},		/* TimeUnix */
	{OTEL_COL_UINT64},		/* Count */
	{OTEL_COL_FLOAT64},		/* Sum */
	{OTEL_COL_ARRAY_FLOAT64},	/* ValueAtQuantiles.Quantile */
	{OTEL_COL_ARRAY_FLOAT64},	/* ValueAtQuantiles.Value */
	{OTEL_COL_UINT32}		/* Flags */
};

static const zbx_otel_col_t	otel_col_logs[] = {
	{OTEL_COL_DATETIME64},		/* Timestamp */
	{OTEL_COL_STRING},		/* TraceId */
	{OTEL_COL_STRING},		/* SpanId */
	{OTEL_COL_UINT8},		/* TraceFlags */
	{OTEL_COL_STRING},		/* SeverityText */
	{OTEL_COL_UINT8},		/* SeverityNumber */
	{OTEL_COL_STRING},		/* ServiceName */
	{OTEL_COL_STRING},		/* Body */
	{OTEL_COL_STRING},		/* ResourceSchemaUrl */
	{OTEL_COL_MAP},			/* ResourceAttributes */
	{OTEL_COL_STRING},		/* ScopeSchemaUrl */
	{OTEL_COL_STRING},		/* ScopeName */
	{OTEL_COL_STRING},		/* ScopeVersion */
	{OTEL_COL_MAP},			/* ScopeAttributes */
	{OTEL_COL_MAP},			/* LogAttributes */
	{OTEL_COL_STRING}		/* EventName */
};

static const zbx_otel_col_t	otel_col_traces[] = {
	{OTEL_COL_DATETIME64},		/* Timestamp */
	{OTEL_COL_STRING},		/* TraceId */
	{OTEL_COL_STRING},		/* SpanId */
	{OTEL_COL_STRING},		/* ParentSpanId */
	{OTEL_COL_STRING},		/* TraceState */
	{OTEL_COL_STRING},		/* SpanName */
	{OTEL_COL_STRING},		/* SpanKind */
	{OTEL_COL_STRING},		/* ServiceName */
	{OTEL_COL_MAP},			/* ResourceAttributes */
	{OTEL_COL_STRING},		/* ScopeName */
	{OTEL_COL_STRING},		/* ScopeVersion */
	{OTEL_COL_MAP},			/* SpanAttributes */
	{OTEL_COL_UINT64},		/* Duration */
	{OTEL_COL_STRING},		/* StatusCode */
	{OTEL_COL_STRING},		/* StatusMessage */
	{OTEL_COL_ARRAY_DATETIME64},	/* Events.Timestamp */
	{OTEL_COL_ARRAY_STRING},	/* Events.Name */
	{OTEL_COL_ARRAY_MAP},		/* Events.Attributes */
	{OTEL_COL_ARRAY_STRING},	/* Links.TraceId */
	{OTEL_COL_ARRAY_STRING},	/* Links.SpanId */
	{OTEL_COL_ARRAY_STRING},	/* Links.TraceState */
	{OTEL_COL_ARRAY_MAP}		/* Links.Attributes */
};

static void	otel_value_free(zbx_otel_value_t *value, const zbx_otel_col_t *col)
{
	switch (col->type)
	{
		case OTEL_COL_STRING:
		case OTEL_COL_MAP:
		case OTEL_COL_ARRAY_STRING:
		case OTEL_COL_ARRAY_UINT64:
		case OTEL_COL_ARRAY_FLOAT64:
		case OTEL_COL_ARRAY_DATETIME:
		case OTEL_COL_ARRAY_DATETIME64:
		case OTEL_COL_ARRAY_MAP:
			zbx_free(value->str);
			break;
		default:
			break;
	}
}


static void	otel_rowset_init(zbx_otel_rowset_t *rs, const zbx_otel_col_t *cols, int cols_num)
{
	zbx_vector_otel_row_create(&rs->rows);
	rs->cols = cols;
	rs->cols_num = cols_num;
}

void	otel_rowset_clear(zbx_otel_rowset_t *rs)
{
	for (int i = 0; i < rs->rows.values_num; i++)
	{
		for (int j = 0; j < rs->cols_num; j++)
			otel_value_free(&rs->rows.values[i].cols[j], &rs->cols[j]);

		zbx_free(rs->rows.values[i].cols);
	}

	zbx_vector_otel_row_destroy(&rs->rows);
}

zbx_otel_row_t	otel_rowset_add(zbx_otel_rowset_t *rs)
{
	zbx_otel_row_t	row;

	row.cols = (zbx_otel_value_t *)zbx_calloc(NULL, rs->cols_num, sizeof(zbx_otel_value_t));
	zbx_vector_otel_row_append(&rs->rows, row);

	return row;
}

void	otel_dataset_init(zbx_otel_dataset_t *ds)
{
#define OTEL_ROWSET_INIT(rs, cols)	otel_rowset_init(rs, cols, (int)ARRSIZE(cols))

	OTEL_ROWSET_INIT(&ds->metrics_gauge, otel_col_metrics_gauge);
	OTEL_ROWSET_INIT(&ds->metrics_sum, otel_col_metrics_sum);
	OTEL_ROWSET_INIT(&ds->metrics_histogram, otel_col_metrics_histogram);
	OTEL_ROWSET_INIT(&ds->metrics_exponential_histogram, otel_col_metrics_exponential_histogram);
	OTEL_ROWSET_INIT(&ds->metrics_summary, otel_col_metrics_summary);
	OTEL_ROWSET_INIT(&ds->logs, otel_col_logs);
	OTEL_ROWSET_INIT(&ds->traces, otel_col_traces);

#undef OTEL_ROWSET_INIT
}

void	otel_dataset_clear(zbx_otel_dataset_t *ds)
{
	otel_rowset_clear(&ds->metrics_gauge);
	otel_rowset_clear(&ds->metrics_sum);
	otel_rowset_clear(&ds->metrics_histogram);
	otel_rowset_clear(&ds->metrics_exponential_histogram);
	otel_rowset_clear(&ds->metrics_summary);
	otel_rowset_clear(&ds->logs);
	otel_rowset_clear(&ds->traces);
}

static void	otel_value_snprintf_alloc(char **str, size_t *str_alloc, size_t *str_offset,
		const zbx_otel_value_t *value, const zbx_otel_col_t *col)
{
	switch (col->type)
	{
		case OTEL_COL_BOOL:
			zbx_strcpy_alloc(str, str_alloc, str_offset, 0 != value->ui64 ? "true" : "false");
			break;
		case OTEL_COL_UINT8:
		case OTEL_COL_UINT32:
		case OTEL_COL_UINT64:
		case OTEL_COL_DATETIME:
		case OTEL_COL_DATETIME64:
			zbx_snprintf_alloc(str, str_alloc, str_offset, ZBX_FS_UI64, value->ui64);
			break;
		case OTEL_COL_INT32:
			zbx_snprintf_alloc(str, str_alloc, str_offset, "%d", value->i32);
			break;
		case OTEL_COL_FLOAT64:
			zbx_snprintf_alloc(str, str_alloc, str_offset, ZBX_FS_DBL, value->dbl);
			break;
		case OTEL_COL_STRING:
		case OTEL_COL_MAP:
		case OTEL_COL_ARRAY_STRING:
		case OTEL_COL_ARRAY_UINT64:
		case OTEL_COL_ARRAY_FLOAT64:
		case OTEL_COL_ARRAY_DATETIME:
		case OTEL_COL_ARRAY_DATETIME64:
		case OTEL_COL_ARRAY_MAP:
			zbx_strcpy_alloc(str, str_alloc, str_offset, ZBX_NULL2EMPTY_STR(value->str));
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN_MSG("unexpected column type %d", (int)col->type);
			break;
	}
}

static void	otel_rowset_dump(const char *name, zbx_otel_rowset_t *rs)
{
	char	*row = NULL;
	size_t	row_alloc = 0;

	zabbix_log(LOG_LEVEL_TRACE, "otel_%s:", name);

	for (int i = 0; i < rs->rows.values_num; i++)
	{
		size_t	row_offset = 0;
		char	delim = ' ';

		for (int j = 0; j < rs->cols_num; j++)
		{
			zbx_chrcpy_alloc(&row, &row_alloc, &row_offset, delim);
			otel_value_snprintf_alloc(&row, &row_alloc, &row_offset, &rs->rows.values[i].cols[j],
					&rs->cols[j]);
			delim = ',';
		}
		zabbix_log(LOG_LEVEL_TRACE, "%s", row);
	}
	zabbix_log(LOG_LEVEL_TRACE, "==");

	zbx_free(row);
}

void	otel_dataset_dump(zbx_otel_dataset_t *ds)
{
	otel_rowset_dump("metrics_gauge", &ds->metrics_gauge);
	otel_rowset_dump("metrics_sum", &ds->metrics_sum);
	otel_rowset_dump("metrics_histogram", &ds->metrics_histogram);
	otel_rowset_dump("metrics_exponential_histogram", &ds->metrics_exponential_histogram);
	otel_rowset_dump("metrics_summary", &ds->metrics_summary);
	otel_rowset_dump("logs", &ds->logs);
	otel_rowset_dump("traces", &ds->traces);
}








