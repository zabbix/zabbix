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

#ifndef ZABBIX_APM_WORKER_H
#define ZABBIX_APM_WORKER_H

#include "apm_exporter.h"
#include "apm_dataset.h"
#include "zbxmw.h"

typedef struct
{
	zbx_mw_worker_t		base;
	zbx_apm_exporter_pool_t	*exporters;
	zbx_apm_commit_stats_t	*commit_stats;
}
zbx_apm_worker_t;

zbx_apm_worker_t	*apm_worker_create(zbx_apm_exporter_pool_t *exporters, zbx_apm_commit_stats_t *commit_stats);

void	*apm_worker_entry(void *args);

#endif
