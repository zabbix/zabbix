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

#ifndef ZABBIX_TELEMETRY_H
#define ZABBIX_TELEMETRY_H

#include "zbxtelemetry.h"

void	tq_query_init(zbx_tq_query_t *query);
void	tq_column_init(zbx_tq_column_t *column);
void	tq_column_clean(zbx_tq_column_t *column);
void	tq_aggr_column_init(zbx_tq_aggr_column_t *aggr_column);
void	tq_aggr_column_clean(zbx_tq_aggr_column_t *aggr_column);
void	tq_condition_init(zbx_tq_condition_t *condition);
void	tq_condition_clean(zbx_tq_condition_t *condition);

#endif
