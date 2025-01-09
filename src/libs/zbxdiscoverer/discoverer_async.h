/*
** Copyright (C) 2001-2025 Zabbix SIA
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

#ifndef ZABBIX_DISCOVERER_ASYNC_H
#define ZABBIX_DISCOVERER_ASYNC_H

#include "discoverer_int.h"
#include "discoverer_job.h"
#include "discoverer_queue.h"

#include <event2/dns.h>

typedef struct
{
	int			processing;
	int			config_timeout;
	const char		*config_source_ip;
	const char		*progname;
	struct event_base	*base;
	struct evdns_base	*dnsbase;
}
discovery_poller_config_t;

typedef struct
{
	discovery_poller_config_t	*poller_config;
	zbx_discoverer_results_t	*dresult;
	zbx_uint64_t			dcheckid;
}
discovery_async_result_t;

void	process_http_result(void *data);
int	discovery_net_check_range(zbx_uint64_t druleid, zbx_discoverer_task_t *task, int worker_max, int *stop,
		zbx_discoverer_manager_t *dmanager, int worker_id, char **error);
int	discovery_pending_checks_count_decrease(zbx_discoverer_queue_t *queue, int worker_max,
		zbx_uint64_t total, zbx_uint64_t dec_counter);

#endif /* ZABBIX_DISCOVERER_ASYNC_H */
