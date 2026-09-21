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

#include "apm_dataset.h"
#include "zbxcommon.h"
#include "zbxstr.h"
#include <stdatomic.h>

ZBX_VECTOR_LITE_IMPL(apm_row, zbx_apm_row_t)

static const zbx_apm_col_t	apm_col_metrics_gauge[] = {
	{APM_COL_MAP},			/* ResourceAttributes */
	{APM_COL_STRING},		/* ResourceSchemaUrl */
	{APM_COL_STRING},		/* ScopeName */
	{APM_COL_STRING},		/* ScopeVersion */
	{APM_COL_MAP},			/* ScopeAttributes */
	{APM_COL_UINT32},		/* ScopeDroppedAttrCount */
	{APM_COL_STRING},		/* ScopeSchemaUrl */
	{APM_COL_STRING},		/* ServiceName */
	{APM_COL_STRING},		/* MetricName */
	{APM_COL_STRING},		/* MetricDescription */
	{APM_COL_STRING},		/* MetricUnit */
	{APM_COL_MAP},			/* Attributes */
	{APM_COL_DATETIME},		/* StartTimeUnix */
	{APM_COL_DATETIME},		/* TimeUnix */
	{APM_COL_FLOAT64},		/* Value */
	{APM_COL_UINT32},		/* Flags */
	{APM_COL_ARRAY_MAP},		/* Exemplars.FilteredAttributes */
	{APM_COL_ARRAY_DATETIME},	/* Exemplars.TimeUnix */
	{APM_COL_ARRAY_FLOAT64},	/* Exemplars.Value */
	{APM_COL_ARRAY_STRING},		/* Exemplars.SpanId */
	{APM_COL_ARRAY_STRING}		/* Exemplars.TraceId */
};

static const zbx_apm_col_t	apm_col_metrics_sum[] = {
	{APM_COL_MAP},			/* ResourceAttributes */
	{APM_COL_STRING},		/* ResourceSchemaUrl */
	{APM_COL_STRING},		/* ScopeName */
	{APM_COL_STRING},		/* ScopeVersion */
	{APM_COL_MAP},			/* ScopeAttributes */
	{APM_COL_UINT32},		/* ScopeDroppedAttrCount */
	{APM_COL_STRING},		/* ScopeSchemaUrl */
	{APM_COL_STRING},		/* ServiceName */
	{APM_COL_STRING},		/* MetricName */
	{APM_COL_STRING},		/* MetricDescription */
	{APM_COL_STRING},		/* MetricUnit */
	{APM_COL_MAP},			/* Attributes */
	{APM_COL_DATETIME},		/* StartTimeUnix */
	{APM_COL_DATETIME},		/* TimeUnix */
	{APM_COL_FLOAT64},		/* Value */
	{APM_COL_UINT32},		/* Flags */
	{APM_COL_ARRAY_MAP},		/* Exemplars.FilteredAttributes */
	{APM_COL_ARRAY_DATETIME},	/* Exemplars.TimeUnix */
	{APM_COL_ARRAY_FLOAT64},	/* Exemplars.Value */
	{APM_COL_ARRAY_STRING},		/* Exemplars.SpanId */
	{APM_COL_ARRAY_STRING},		/* Exemplars.TraceId */
	{APM_COL_INT32},		/* AggregationTemporality */
	{APM_COL_BOOL}			/* IsMonotonic */
};

static const zbx_apm_col_t	apm_col_metrics_histogram[] = {
	{APM_COL_MAP},			/* ResourceAttributes */
	{APM_COL_STRING},		/* ResourceSchemaUrl */
	{APM_COL_STRING},		/* ScopeName */
	{APM_COL_STRING},		/* ScopeVersion */
	{APM_COL_MAP},			/* ScopeAttributes */
	{APM_COL_UINT32},		/* ScopeDroppedAttrCount */
	{APM_COL_STRING},		/* ScopeSchemaUrl */
	{APM_COL_STRING},		/* ServiceName */
	{APM_COL_STRING},		/* MetricName */
	{APM_COL_STRING},		/* MetricDescription */
	{APM_COL_STRING},		/* MetricUnit */
	{APM_COL_MAP},			/* Attributes */
	{APM_COL_DATETIME},		/* StartTimeUnix */
	{APM_COL_DATETIME},		/* TimeUnix */
	{APM_COL_UINT64},		/* Count */
	{APM_COL_FLOAT64},		/* Sum */
	{APM_COL_ARRAY_UINT64},		/* BucketCounts */
	{APM_COL_ARRAY_FLOAT64},	/* ExplicitBounds */
	{APM_COL_ARRAY_MAP},		/* Exemplars.FilteredAttributes */
	{APM_COL_ARRAY_DATETIME},	/* Exemplars.TimeUnix */
	{APM_COL_ARRAY_FLOAT64},	/* Exemplars.Value */
	{APM_COL_ARRAY_STRING},		/* Exemplars.SpanId */
	{APM_COL_ARRAY_STRING},		/* Exemplars.TraceId */
	{APM_COL_UINT32},		/* Flags */
	{APM_COL_FLOAT64},		/* Min */
	{APM_COL_FLOAT64},		/* Max */
	{APM_COL_INT32}			/* AggregationTemporality */
};

static const zbx_apm_col_t	apm_col_metrics_exponential_histogram[] = {
	{APM_COL_MAP},			/* ResourceAttributes */
	{APM_COL_STRING},		/* ResourceSchemaUrl */
	{APM_COL_STRING},		/* ScopeName */
	{APM_COL_STRING},		/* ScopeVersion */
	{APM_COL_MAP},			/* ScopeAttributes */
	{APM_COL_UINT32},		/* ScopeDroppedAttrCount */
	{APM_COL_STRING},		/* ScopeSchemaUrl */
	{APM_COL_STRING},		/* ServiceName */
	{APM_COL_STRING},		/* MetricName */
	{APM_COL_STRING},		/* MetricDescription */
	{APM_COL_STRING},		/* MetricUnit */
	{APM_COL_MAP},			/* Attributes */
	{APM_COL_DATETIME},		/* StartTimeUnix */
	{APM_COL_DATETIME},		/* TimeUnix */
	{APM_COL_UINT64},		/* Count */
	{APM_COL_FLOAT64},		/* Sum */
	{APM_COL_INT32},		/* Scale */
	{APM_COL_UINT64},		/* ZeroCount */
	{APM_COL_INT32},		/* PositiveOffset */
	{APM_COL_ARRAY_UINT64},		/* PositiveBucketCounts */
	{APM_COL_INT32},		/* NegativeOffset */
	{APM_COL_ARRAY_UINT64},		/* NegativeBucketCounts */
	{APM_COL_ARRAY_MAP},		/* Exemplars.FilteredAttributes */
	{APM_COL_ARRAY_DATETIME},	/* Exemplars.TimeUnix */
	{APM_COL_ARRAY_FLOAT64},	/* Exemplars.Value */
	{APM_COL_ARRAY_STRING},		/* Exemplars.SpanId */
	{APM_COL_ARRAY_STRING},		/* Exemplars.TraceId */
	{APM_COL_UINT32},		/* Flags */
	{APM_COL_FLOAT64},		/* Min */
	{APM_COL_FLOAT64},		/* Max */
	{APM_COL_INT32}			/* AggregationTemporality */
};

static const zbx_apm_col_t	apm_col_metrics_summary[] = {
	{APM_COL_MAP},			/* ResourceAttributes */
	{APM_COL_STRING},		/* ResourceSchemaUrl */
	{APM_COL_STRING},		/* ScopeName */
	{APM_COL_STRING},		/* ScopeVersion */
	{APM_COL_MAP},			/* ScopeAttributes */
	{APM_COL_UINT32},		/* ScopeDroppedAttrCount */
	{APM_COL_STRING},		/* ScopeSchemaUrl */
	{APM_COL_STRING},		/* ServiceName */
	{APM_COL_STRING},		/* MetricName */
	{APM_COL_STRING},		/* MetricDescription */
	{APM_COL_STRING},		/* MetricUnit */
	{APM_COL_MAP},			/* Attributes */
	{APM_COL_DATETIME},		/* StartTimeUnix */
	{APM_COL_DATETIME},		/* TimeUnix */
	{APM_COL_UINT64},		/* Count */
	{APM_COL_FLOAT64},		/* Sum */
	{APM_COL_ARRAY_FLOAT64},	/* ValueAtQuantiles.Quantile */
	{APM_COL_ARRAY_FLOAT64},	/* ValueAtQuantiles.Value */
	{APM_COL_UINT32}		/* Flags */
};

static const zbx_apm_col_t	apm_col_logs[] = {
	{APM_COL_DATETIME64},		/* Timestamp */
	{APM_COL_STRING},		/* TraceId */
	{APM_COL_STRING},		/* SpanId */
	{APM_COL_UINT8},		/* TraceFlags */
	{APM_COL_STRING},		/* SeverityText */
	{APM_COL_UINT8},		/* SeverityNumber */
	{APM_COL_STRING},		/* ServiceName */
	{APM_COL_STRING},		/* Body */
	{APM_COL_STRING},		/* ResourceSchemaUrl */
	{APM_COL_MAP},			/* ResourceAttributes */
	{APM_COL_STRING},		/* ScopeSchemaUrl */
	{APM_COL_STRING},		/* ScopeName */
	{APM_COL_STRING},		/* ScopeVersion */
	{APM_COL_MAP},			/* ScopeAttributes */
	{APM_COL_MAP},			/* LogAttributes */
	{APM_COL_STRING}		/* EventName */
};

static const zbx_apm_col_t	apm_col_traces[] = {
	{APM_COL_DATETIME64},		/* Timestamp */
	{APM_COL_STRING},		/* TraceId */
	{APM_COL_STRING},		/* SpanId */
	{APM_COL_STRING},		/* ParentSpanId */
	{APM_COL_STRING},		/* TraceState */
	{APM_COL_STRING},		/* SpanName */
	{APM_COL_STRING},		/* SpanKind */
	{APM_COL_STRING},		/* ServiceName */
	{APM_COL_MAP},			/* ResourceAttributes */
	{APM_COL_STRING},		/* ScopeName */
	{APM_COL_STRING},		/* ScopeVersion */
	{APM_COL_MAP},			/* SpanAttributes */
	{APM_COL_UINT64},		/* Duration */
	{APM_COL_STRING},		/* StatusCode */
	{APM_COL_STRING},		/* StatusMessage */
	{APM_COL_ARRAY_DATETIME64},	/* Events.Timestamp */
	{APM_COL_ARRAY_STRING},		/* Events.Name */
	{APM_COL_ARRAY_MAP},		/* Events.Attributes */
	{APM_COL_ARRAY_STRING},		/* Links.TraceId */
	{APM_COL_ARRAY_STRING},		/* Links.SpanId */
	{APM_COL_ARRAY_STRING},		/* Links.TraceState */
	{APM_COL_ARRAY_MAP}		/* Links.Attributes */
};

/******************************************************************************
 *                                                                            *
 * Purpose: free a column value if its type owns allocated memory             *
 *                                                                            *
 * Parameters: value - [IN] value to free                                     *
 *             col   - [IN] column definition describing the value type       *
 *                                                                            *
 ******************************************************************************/
static void	apm_value_free(zbx_apm_value_t *value, const zbx_apm_col_t *col)
{
	switch (col->type)
	{
		case APM_COL_STRING:
		case APM_COL_MAP:
		case APM_COL_ARRAY_STRING:
		case APM_COL_ARRAY_UINT64:
		case APM_COL_ARRAY_FLOAT64:
		case APM_COL_ARRAY_DATETIME:
		case APM_COL_ARRAY_DATETIME64:
		case APM_COL_ARRAY_MAP:
			zbx_free(value->str);
			break;
		default:
			break;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: initialize rowset with the specified column definitions           *
 *                                                                            *
 * Parameters: rs       - [OUT] rowset to initialize                          *
 *             cols     - [IN] column definitions                             *
 *             cols_num - [IN] number of columns                              *
 *                                                                            *
 ******************************************************************************/
static void	apm_rowset_init(zbx_apm_rowset_t *rs, const zbx_apm_col_t *cols, int cols_num)
{
	zbx_vector_apm_row_create(&rs->rows);
	rs->cols = cols;
	rs->cols_num = cols_num;
}

/******************************************************************************
 *                                                                            *
 * Purpose: free row contents of a rowset                                     *
 *                                                                            *
 * Parameters: rs - [IN/OUT] rowset to clear                                  *
 *                                                                            *
 * Comments: The rowset is cleared, but not destroyed.                        *
 *                                                                            *
 ******************************************************************************/
void	apm_rowset_clear(zbx_apm_rowset_t *rs)
{
	for (int i = 0; i < rs->rows.values_num; i++)
	{
		for (int j = 0; j < rs->cols_num; j++)
			apm_value_free(&rs->rows.values[i].cols[j], &rs->cols[j]);

		zbx_free(rs->rows.values[i].cols);
	}

	zbx_vector_apm_row_clear(&rs->rows);
}

/******************************************************************************
 *                                                                            *
 * Purpose: append a new zero-initialized row to the rowset                   *
 *                                                                            *
 * Parameters: rs - [IN/OUT] rowset to append the row to                      *
 *                                                                            *
 * Return value: the appended row                                             *
 *                                                                            *
 ******************************************************************************/
zbx_apm_row_t	apm_rowset_add(zbx_apm_rowset_t *rs)
{
	zbx_apm_row_t	row;

	row.cols = (zbx_apm_value_t *)zbx_calloc(NULL, rs->cols_num, sizeof(zbx_apm_value_t));
	zbx_vector_apm_row_append(&rs->rows, row);

	return row;
}

/******************************************************************************
 *                                                                            *
 * Purpose: initialize dataset rowsets for all supported signal types         *
 *                                                                            *
 ******************************************************************************/
void	apm_dataset_init(zbx_apm_dataset_t *ds)
{
#define APM_ROWSET_INIT(rs, cols)	apm_rowset_init(rs, cols, (int)ARRSIZE(cols))

	APM_ROWSET_INIT(&ds->metrics_gauge, apm_col_metrics_gauge);
	APM_ROWSET_INIT(&ds->metrics_sum, apm_col_metrics_sum);
	APM_ROWSET_INIT(&ds->metrics_histogram, apm_col_metrics_histogram);
	APM_ROWSET_INIT(&ds->metrics_exponential_histogram, apm_col_metrics_exponential_histogram);
	APM_ROWSET_INIT(&ds->metrics_summary, apm_col_metrics_summary);
	APM_ROWSET_INIT(&ds->logs, apm_col_logs);
	APM_ROWSET_INIT(&ds->traces, apm_col_traces);

#undef APM_ROWSET_INIT
}

/******************************************************************************
 *                                                                            *
 * Purpose: free all resources allocated by dataset rowsets                   *
 *                                                                            *
 ******************************************************************************/
void	apm_dataset_destroy(zbx_apm_dataset_t *ds)
{
	apm_rowset_clear(&ds->metrics_gauge);
	zbx_vector_apm_row_destroy(&ds->metrics_gauge.rows);

	apm_rowset_clear(&ds->metrics_sum);
	zbx_vector_apm_row_destroy(&ds->metrics_sum.rows);

	apm_rowset_clear(&ds->metrics_histogram);
	zbx_vector_apm_row_destroy(&ds->metrics_histogram.rows);

	apm_rowset_clear(&ds->metrics_exponential_histogram);
	zbx_vector_apm_row_destroy(&ds->metrics_exponential_histogram.rows);

	apm_rowset_clear(&ds->metrics_summary);
	zbx_vector_apm_row_destroy(&ds->metrics_summary.rows);

	apm_rowset_clear(&ds->logs);
	zbx_vector_apm_row_destroy(&ds->logs.rows);

	apm_rowset_clear(&ds->traces);
	zbx_vector_apm_row_destroy(&ds->traces.rows);
}

/******************************************************************************
 *                                                                            *
 * Purpose: append the string representation of a column value to a           *
 *          dynamic buffer                                                    *
 *                                                                            *
 * Parameters: str        - [IN/OUT] dynamic buffer                           *
 *             str_alloc  - [IN/OUT] allocated buffer size                    *
 *             str_offset - [IN/OUT] current buffer offset                    *
 *             value      - [IN] value to format                              *
 *             col        - [IN] column definition describing the value type  *
 *                                                                            *
 ******************************************************************************/
static void	apm_value_snprintf_alloc(char **str, size_t *str_alloc, size_t *str_offset,
		const zbx_apm_value_t *value, const zbx_apm_col_t *col)
{
	switch (col->type)
	{
		case APM_COL_BOOL:
			zbx_strcpy_alloc(str, str_alloc, str_offset, 0 != value->ui64 ? "true" : "false");
			break;
		case APM_COL_UINT8:
		case APM_COL_UINT32:
		case APM_COL_UINT64:
		case APM_COL_DATETIME:
		case APM_COL_DATETIME64:
			zbx_snprintf_alloc(str, str_alloc, str_offset, ZBX_FS_UI64, value->ui64);
			break;
		case APM_COL_INT32:
			zbx_snprintf_alloc(str, str_alloc, str_offset, "%d", value->i32);
			break;
		case APM_COL_FLOAT64:
			zbx_snprintf_alloc(str, str_alloc, str_offset, ZBX_FS_DBL, value->dbl);
			break;
		case APM_COL_STRING:
		case APM_COL_MAP:
		case APM_COL_ARRAY_STRING:
		case APM_COL_ARRAY_UINT64:
		case APM_COL_ARRAY_FLOAT64:
		case APM_COL_ARRAY_DATETIME:
		case APM_COL_ARRAY_DATETIME64:
		case APM_COL_ARRAY_MAP:
			zbx_strcpy_alloc(str, str_alloc, str_offset, ZBX_NULL2EMPTY_STR(value->str));
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN_MSG("unexpected column type %d", (int)col->type);
			break;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: write rowset rows to the trace log                                *
 *                                                                            *
 * Parameters: name - [IN] rowset name used in the trace log                  *
 *             rs   - [IN] rowset to dump                                     *
 *                                                                            *
 ******************************************************************************/
static void	apm_rowset_dump(const char *name, zbx_apm_rowset_t *rs)
{
	char	*row = NULL;
	size_t	row_alloc = 0;

	zabbix_log(LOG_LEVEL_TRACE, "apm_%s:", name);

	for (int i = 0; i < rs->rows.values_num; i++)
	{
		size_t	row_offset = 0;
		char	delim = ' ';

		for (int j = 0; j < rs->cols_num; j++)
		{
			zbx_chrcpy_alloc(&row, &row_alloc, &row_offset, delim);
			apm_value_snprintf_alloc(&row, &row_alloc, &row_offset, &rs->rows.values[i].cols[j],
					&rs->cols[j]);
			delim = ',';
		}
		zabbix_log(LOG_LEVEL_TRACE, "%s", row);
	}
	zabbix_log(LOG_LEVEL_TRACE, "==");

	zbx_free(row);
}

/******************************************************************************
 *                                                                            *
 * Purpose: write dataset rowsets to the trace log                            *
 *                                                                            *
 ******************************************************************************/
void	apm_dataset_dump(zbx_apm_dataset_t *ds)
{
	apm_rowset_dump("metrics_gauge", &ds->metrics_gauge);
	apm_rowset_dump("metrics_sum", &ds->metrics_sum);
	apm_rowset_dump("metrics_histogram", &ds->metrics_histogram);
	apm_rowset_dump("metrics_exponential_histogram", &ds->metrics_exponential_histogram);
	apm_rowset_dump("metrics_summary", &ds->metrics_summary);
	apm_rowset_dump("logs", &ds->logs);
	apm_rowset_dump("traces", &ds->traces);
}

/******************************************************************************
 *                                                                            *
 * Purpose: add dataset row counts to APM commit statistics                   *
 *                                                                            *
 * Parameters: ds    - [IN] dataset with rows to account for                  *
 *             stats - [IN/OUT] commit statistics to update                   *
 *                                                                            *
 ******************************************************************************/
void	apm_dataset_flush_stats(const zbx_apm_dataset_t *ds, zbx_apm_commit_stats_t *stats)
{
	if (0 != ds->logs.rows.values_num)
		atomic_fetch_add(&stats->logs, (zbx_uint64_t)ds->logs.rows.values_num);

	if (0 != ds->traces.rows.values_num)
		atomic_fetch_add(&stats->traces, (zbx_uint64_t)ds->traces.rows.values_num);

	if (0 != ds->metrics_exponential_histogram.rows.values_num)
	{
		atomic_fetch_add(&stats->metrics_exponential_histogram,
				(zbx_uint64_t)ds->metrics_exponential_histogram.rows.values_num);
	}

	if (0 != ds->metrics_histogram.rows.values_num)
		atomic_fetch_add(&stats->metrics_histogram, (zbx_uint64_t)ds->metrics_histogram.rows.values_num);

	if (0 != ds->metrics_gauge.rows.values_num)
		atomic_fetch_add(&stats->metrics_gauge, (zbx_uint64_t)ds->metrics_gauge.rows.values_num);

	if (0 != ds->metrics_sum.rows.values_num)
		atomic_fetch_add(&stats->metrics_sum, (zbx_uint64_t)ds->metrics_sum.rows.values_num);

	if (0 != ds->metrics_summary.rows.values_num)
		atomic_fetch_add(&stats->metrics_summary, (zbx_uint64_t)ds->metrics_summary.rows.values_num);
}

/******************************************************************************
 *                                                                            *
 * Purpose: subtract dataset row counts from APM commit statistics            *
 *                                                                            *
 * Parameters: ds    - [IN] dataset with rows to account for                  *
 *             stats - [IN/OUT] commit statistics to update                   *
 *                                                                            *
 * Comments: Used to revert counts added by apm_dataset_flush_stats() for     *
 *           rows that failed to commit.                                      *
 *                                                                            *
 ******************************************************************************/
void	apm_dataset_undo_stats(const zbx_apm_dataset_t *ds, zbx_apm_commit_stats_t *stats)
{
	if (0 != ds->logs.rows.values_num)
		atomic_fetch_sub(&stats->logs, (zbx_uint64_t)ds->logs.rows.values_num);

	if (0 != ds->traces.rows.values_num)
		atomic_fetch_sub(&stats->traces, (zbx_uint64_t)ds->traces.rows.values_num);

	if (0 != ds->metrics_exponential_histogram.rows.values_num)
	{
		atomic_fetch_sub(&stats->metrics_exponential_histogram,
				(zbx_uint64_t)ds->metrics_exponential_histogram.rows.values_num);
	}

	if (0 != ds->metrics_histogram.rows.values_num)
		atomic_fetch_sub(&stats->metrics_histogram, (zbx_uint64_t)ds->metrics_histogram.rows.values_num);

	if (0 != ds->metrics_gauge.rows.values_num)
		atomic_fetch_sub(&stats->metrics_gauge, (zbx_uint64_t)ds->metrics_gauge.rows.values_num);

	if (0 != ds->metrics_sum.rows.values_num)
		atomic_fetch_sub(&stats->metrics_sum, (zbx_uint64_t)ds->metrics_sum.rows.values_num);

	if (0 != ds->metrics_summary.rows.values_num)
		atomic_fetch_sub(&stats->metrics_summary, (zbx_uint64_t)ds->metrics_summary.rows.values_num);
}
