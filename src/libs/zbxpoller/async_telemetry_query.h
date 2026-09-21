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

#ifndef ZABBIX_ASYNC_TELEMETRY_QUERY_H
#define ZABBIX_ASYNC_TELEMETRY_QUERY_H

#include "zbxpoller.h"
#include "zbxtelemetry.h"
#include "zbxcacheconfig.h"
#include "zbxtime.h"

#ifdef HAVE_LIBCURL
#	include "zbxhttp.h"
#endif

#ifdef HAVE_LIBCURL
typedef struct
{
	zbx_uint64_t		itemid;
	unsigned char		value_type;
	unsigned char		flags;
	char			*posts;
	unsigned char		preprocessing;
	zbx_tq_query_t		*query;
	time_t			newlasttimestamp;
	zbx_timespec_t		min_free_ts;
	zbx_apm_db_type_t	db_type;
}
zbx_dc_tq_item_context_t;

typedef struct
{
	zbx_http_context_t		http_context;
	zbx_dc_tq_item_context_t	item_context;
}
zbx_telemetry_query_context;
#endif

int	zbx_async_check_telemetry_query(zbx_dc_telemetry_query_item_t *item, AGENT_RESULT *result,
		zbx_poller_config_t *poller_config);

#ifdef HAVE_LIBCURL
void	zbx_async_check_telemetry_query_clean(zbx_telemetry_query_context *telemetry_query_context);
#endif

#endif
