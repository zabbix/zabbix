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

#ifndef ZABBIX_ZBX_APM_CLIENT_H
#define ZABBIX_ZBX_APM_CLIENT_H

#include "zbxtypes.h"
#include "zbx_rtc_constants.h"

#define ZBX_IPC_SERVICE_APM	"apm"

#define ZBX_APM_GET_STATS	(ZBX_IPC_RTC_MAX + 1)

typedef struct
{
	zbx_uint64_t	written_logs;
	zbx_uint64_t	written_traces;
	zbx_uint64_t	written_metrics_gauge;
	zbx_uint64_t	written_metrics_sum;
	zbx_uint64_t	written_metrics_histogram;
	zbx_uint64_t	written_metrics_exponential_histogram;
	zbx_uint64_t	written_metrics_summary;
	zbx_uint64_t	processed_requests;
}
zbx_apm_stats_t;

int	zbx_apm_get_stats(zbx_apm_stats_t *stats, char **error);
zbx_uint32_t	zbx_apm_serialize_stats(zbx_apm_stats_t *stats, unsigned char *buf, zbx_uint64_t len);


#endif

