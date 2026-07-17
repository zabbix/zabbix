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
#include "zbxstr.h"

ZBX_VECTOR_LITE_IMPL(otel_row, zbx_otel_row_t)

void	otel_rowset_init(zbx_otel_rowset_t *rs, int cols_num)
{
	zbx_vector_otel_row_create(&rs->rows);
	rs->cols_num = cols_num;
}

void	otel_rowset_clear(zbx_otel_rowset_t *rs)
{
	for (int i = 0; i < rs->rows.values_num; i++)
	{
		for (int j = 0; j < rs->cols_num; j++)
			zbx_free(rs->rows.values[i].cols[j]);

		zbx_free(rs->rows.values[i].cols);
	}

	zbx_vector_otel_row_destroy(&rs->rows);
}

zbx_otel_row_t	otel_rowset_add(zbx_otel_rowset_t *rs)
{
	zbx_otel_row_t	row;

	row.cols = (char **)zbx_calloc(NULL, rs->cols_num, sizeof(char *));
	zbx_vector_otel_row_append(&rs->rows, row);

	return row;
}

void	otel_dataset_init(zbx_otel_dataset_t *ds)
{
	otel_rowset_init(&ds->gauge, 21);
	otel_rowset_init(&ds->sum, 23);
	otel_rowset_init(&ds->histogram, 27);
	otel_rowset_init(&ds->exponential_histogram, 31);
	otel_rowset_init(&ds->summary, 19);
	otel_rowset_init(&ds->logs, 16);
	otel_rowset_init(&ds->traces, 22);
}

void	otel_dataset_clear(zbx_otel_dataset_t *ds)
{
	otel_rowset_clear(&ds->gauge);
	otel_rowset_clear(&ds->sum);
	otel_rowset_clear(&ds->histogram);
	otel_rowset_clear(&ds->exponential_histogram);
	otel_rowset_clear(&ds->summary);
	otel_rowset_clear(&ds->logs);
	otel_rowset_clear(&ds->traces);
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
			zbx_snprintf_alloc(&row, &row_alloc, &row_offset, "%c %s",
					delim, ZBX_NULL2STR(rs->rows.values[i].cols[j]));
			delim = ',';
		}
		zabbix_log(LOG_LEVEL_TRACE, "%s", row);
	}
	zabbix_log(LOG_LEVEL_TRACE, "==");

	zbx_free(row);
}

void	otel_dataset_dump(zbx_otel_dataset_t *ds)
{
	otel_rowset_dump("metrics_gauge", &ds->gauge);
	otel_rowset_dump("metrics_sum", &ds->sum);
	otel_rowset_dump("metrics_histogram", &ds->histogram);
	otel_rowset_dump("metrics_exponential_histogram", &ds->exponential_histogram);
	otel_rowset_dump("metrics_summary", &ds->summary);
	otel_rowset_dump("logs", &ds->logs);
	otel_rowset_dump("traces", &ds->traces);
}








