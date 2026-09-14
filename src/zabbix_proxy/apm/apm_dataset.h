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

#ifndef ZABBIX_APM_DATASET_H
#define ZABBIX_APM_DATASET_H

#include "zbxtypes.h"
#include "zbxtypes_ext.h"
#include "zbxalgo.h"

typedef union
{
	zbx_uint64_t	ui64;		/* UINT8/32/64, DATETIME, DATETIME64, BOOL */
	int		i32;		/* INT32 */
	double		dbl;		/* FLOAT64 */
	char		*str;		/* STRING, ARRAY, MAP */
}
zbx_apm_value_t;

typedef struct
{
	zbx_apm_value_t	*cols;
}
zbx_apm_row_t;

ZBX_VECTOR_LITE_DECL(apm_row, zbx_apm_row_t)

typedef enum
{
	APM_COL_STRING,		/* String, LowCardinality(String) */
	APM_COL_BOOL,			/* Boolean (IsMonotonic) */
	APM_COL_UINT8,			/* TraceFlags, SeverityNumber */
	APM_COL_UINT32,		/* Flags, ScopeDroppedAttrCount */
	APM_COL_INT32,			/* AggregationTemporality, Scale, Positive/NegativeOffset */
	APM_COL_UINT64,		/* Count, ZeroCount, Duration */
	APM_COL_FLOAT64,		/* Value, Sum, Min, Max */
	APM_COL_DATETIME,		/* DateTime, seconds (metrics StartTimeUnix/TimeUnix) */
	APM_COL_DATETIME64,		/* DateTime64(9), nanos (logs/traces Timestamp) */
	APM_COL_MAP,			/* Map(String, String) -- JSON object */

	/* parallel-array columns (Nested groups and plain arrays) */
	APM_COL_ARRAY_STRING,		/* Exemplars.SpanId/TraceId, Events.Name, Links.* */
	APM_COL_ARRAY_UINT64,		/* BucketCounts, Positive/NegativeBucketCounts */
	APM_COL_ARRAY_FLOAT64,		/* ExplicitBounds, Exemplars.Value, ValueAtQuantiles.* */
	APM_COL_ARRAY_DATETIME,	/* Exemplars.TimeUnix */
	APM_COL_ARRAY_DATETIME64,	/* Events.Timestamp */
	APM_COL_ARRAY_MAP		/* Exemplars.FilteredAttributes, Events/Links.Attributes */
}
zbx_apm_col_type_t;

typedef struct
{
	zbx_apm_col_type_t	type;
}
zbx_apm_col_t;

typedef struct
{
	int			cols_num;
	const zbx_apm_col_t	*cols;

	zbx_vector_apm_row_t	rows;
}
zbx_apm_rowset_t;

typedef struct
{
	zbx_apm_rowset_t	metrics_gauge;
	zbx_apm_rowset_t	metrics_sum;
	zbx_apm_rowset_t	metrics_histogram;
	zbx_apm_rowset_t	metrics_exponential_histogram;
	zbx_apm_rowset_t	metrics_summary;
	zbx_apm_rowset_t	logs;
	zbx_apm_rowset_t	traces;
}
zbx_apm_dataset_t;

void	apm_dataset_init(zbx_apm_dataset_t *ds);
void	apm_dataset_destroy(zbx_apm_dataset_t *ds);
void	apm_dataset_dump(zbx_apm_dataset_t *ds);

void	apm_rowset_clear(zbx_apm_rowset_t *rs);
zbx_apm_row_t	apm_rowset_add(zbx_apm_rowset_t *rs);

typedef struct
{
	zbx_atomic_uint64_t	metrics_gauge;
	zbx_atomic_uint64_t	metrics_sum;
	zbx_atomic_uint64_t	metrics_histogram;
	zbx_atomic_uint64_t	metrics_exponential_histogram;
	zbx_atomic_uint64_t	metrics_summary;
	zbx_atomic_uint64_t	logs;
	zbx_atomic_uint64_t	traces;
}
zbx_apm_commit_stats_t;

void	apm_dataset_flush_stats(const zbx_apm_dataset_t *ds, zbx_apm_commit_stats_t *stats);
void	apm_dataset_undo_stats(const zbx_apm_dataset_t *ds, zbx_apm_commit_stats_t *stats);

#endif
