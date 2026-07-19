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

#ifndef ZABBIX_OTEL_DATASET_H
#define ZABBIX_OTEL_DATASET_H

#include "zbxalgo.h"

typedef union
{
	zbx_uint64_t	ui64;		/* UINT8/32/64, DATETIME, DATETIME64, BOOL */
	int		i32;		/* INT32 */
	double		dbl;		/* FLOAT64 */
	char		*str;		/* STRING, ARRAY, MAP */
}
zbx_otel_value_t;

typedef struct
{
	zbx_otel_value_t	*cols;
}
zbx_otel_row_t;

ZBX_VECTOR_LITE_DECL(otel_row, zbx_otel_row_t)

typedef enum
{
	OTEL_COL_STRING,		/* String, LowCardinality(String) */
	OTEL_COL_BOOL,			/* Boolean (IsMonotonic) */
	OTEL_COL_UINT8,			/* TraceFlags, SeverityNumber */
	OTEL_COL_UINT32,		/* Flags, ScopeDroppedAttrCount */
	OTEL_COL_INT32,			/* AggregationTemporality, Scale, Positive/NegativeOffset */
	OTEL_COL_UINT64,		/* Count, ZeroCount, Duration */
	OTEL_COL_FLOAT64,		/* Value, Sum, Min, Max */
	OTEL_COL_DATETIME,		/* DateTime, seconds (metrics StartTimeUnix/TimeUnix) */
	OTEL_COL_DATETIME64,		/* DateTime64(9), nanos (logs/traces Timestamp) */
	OTEL_COL_MAP,			/* Map(String, String) -- JSON object */

	/* parallel-array columns (Nested groups and plain arrays) */
	OTEL_COL_ARRAY_STRING,		/* Exemplars.SpanId/TraceId, Events.Name, Links.* */
	OTEL_COL_ARRAY_UINT64,		/* BucketCounts, Positive/NegativeBucketCounts */
	OTEL_COL_ARRAY_FLOAT64,		/* ExplicitBounds, Exemplars.Value, ValueAtQuantiles.* */
	OTEL_COL_ARRAY_DATETIME,	/* Exemplars.TimeUnix */
	OTEL_COL_ARRAY_DATETIME64,	/* Events.Timestamp */
	OTEL_COL_ARRAY_MAP		/* Exemplars.FilteredAttributes, Events/Links.Attributes */
}
zbx_otel_col_type_t;

typedef struct
{
	zbx_otel_col_type_t	type;
}
zbx_otel_col_t;

typedef struct
{
	int			cols_num;
	const zbx_otel_col_t	*cols;

	zbx_vector_otel_row_t	rows;
}
zbx_otel_rowset_t;

typedef struct
{
	zbx_otel_rowset_t	metrics_gauge;
	zbx_otel_rowset_t	metrics_sum;
	zbx_otel_rowset_t	metrics_histogram;
	zbx_otel_rowset_t	metrics_exponential_histogram;
	zbx_otel_rowset_t	metrics_summary;
	zbx_otel_rowset_t	logs;
	zbx_otel_rowset_t	traces;
}
zbx_otel_dataset_t;

void	otel_dataset_init(zbx_otel_dataset_t *ds);
void	otel_dataset_clear(zbx_otel_dataset_t *ds);
void	otel_dataset_dump(zbx_otel_dataset_t *ds);

void	otel_rowset_clear(zbx_otel_rowset_t *rs);
zbx_otel_row_t	otel_rowset_add(zbx_otel_rowset_t *rs);



#endif
