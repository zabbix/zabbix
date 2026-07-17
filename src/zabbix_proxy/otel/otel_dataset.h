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

typedef struct
{
	char	**cols;
}
zbx_otel_row_t;

ZBX_VECTOR_LITE_DECL(otel_row, zbx_otel_row_t)

typedef struct
{
	int			cols_num;
	zbx_vector_otel_row_t	rows;
}
zbx_otel_rowset_t;

typedef struct
{
	zbx_otel_rowset_t	gauge;
	zbx_otel_rowset_t	sum;
	zbx_otel_rowset_t	histogram;
	zbx_otel_rowset_t	exponential_histogram;
	zbx_otel_rowset_t	summary;
	zbx_otel_rowset_t	logs;
	zbx_otel_rowset_t	traces;
}
zbx_otel_dataset_t;

void	otel_dataset_init(zbx_otel_dataset_t *ds);
void	otel_dataset_clear(zbx_otel_dataset_t *ds);
void	otel_dataset_dump(zbx_otel_dataset_t *ds);

void	otel_rowset_init(zbx_otel_rowset_t *rs, int cols_num);
void	otel_rowset_clear(zbx_otel_rowset_t *rs);
zbx_otel_row_t	otel_rowset_add(zbx_otel_rowset_t *rs);



#endif
