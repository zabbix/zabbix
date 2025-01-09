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

#ifndef ZABBIX_DISCOVERER_INT_H_
#define ZABBIX_DISCOVERER_INT_H_

#include "discoverer_queue.h"
#include "zbxtimekeeper.h"

#define DISCOVERER_JOB_TASKS_INPROGRESS_MAX	1000

#define DISCOVERER_WORKER_INIT_NONE	0x00
#define DISCOVERER_WORKER_INIT_THREAD	0x01

#define GET_DTYPE(t)		t->ds_dchecks.values[0]->dcheck.type
#define TASK_IP2STR(t, ip_str)	zbx_iprange_ip2str(t->range.ipranges->values[t->range.state.index_ip].type, \
					t->range.state.ipaddress, ip_str, sizeof(ip_str))

typedef struct
{
	zbx_uint64_t	druleid;
	char		ip[ZBX_INTERFACE_IP_LEN_MAX];
	zbx_uint64_t	count;
}
zbx_discoverer_check_count_t;

typedef struct
{
	zbx_discoverer_queue_t	*queue;
	pthread_t		thread;
	int			worker_id;
	int			stop;
	int			flags;
	zbx_timekeeper_t	*timekeeper;
}
zbx_discoverer_worker_t;

typedef struct
{
	int					workers_num;
	zbx_discoverer_worker_t			*workers;
	zbx_vector_discoverer_jobs_ptr_t	job_refs;
	zbx_discoverer_queue_t			queue;

	zbx_hashset_t				incomplete_checks_count;
	zbx_hashset_t				results;
	pthread_mutex_t				results_lock;

	zbx_timekeeper_t			*timekeeper;
	const char				*source_ip;
	const char				*progname;
	unsigned char				process_type;
	int					config_timeout;
}
zbx_discoverer_manager_t;

typedef struct
{
	zbx_uint64_t	dcheckid;
	unsigned short	port;
	char		value[ZBX_MAX_DISCOVERED_VALUE_SIZE];
	int		status;
}
zbx_discoverer_dservice_t;

ZBX_PTR_VECTOR_DECL(discoverer_services_ptr, zbx_discoverer_dservice_t*)

typedef struct
{
	zbx_vector_discoverer_services_ptr_t	services;
	zbx_uint64_t				druleid;
	char					*ip;
	char					*dnsname;
	time_t					now;
	zbx_uint64_t				unique_dcheckid;
	unsigned int				processed_checks_per_ip;
}
zbx_discoverer_results_t;

ZBX_PTR_VECTOR_DECL(discoverer_results_ptr, zbx_discoverer_results_t*)

zbx_discoverer_results_t	*discoverer_result_create(zbx_uint64_t druleid, const zbx_uint64_t unique_dcheckid);
int				discoverer_results_partrange_merge(zbx_hashset_t *hr_dst,
					zbx_vector_discoverer_results_ptr_t *vr_src, zbx_discoverer_task_t *task,
					int force);
void				results_free(zbx_discoverer_results_t *result);
zbx_discoverer_dservice_t	*result_dservice_create(const unsigned short port, const zbx_uint64_t dcheckid);
void				dcheck_port_ranges_get(const char *ports, zbx_vector_portrange_t *ranges);
int				dcheck_is_async(zbx_ds_dcheck_t *ds_dcheck);

#endif /* ZABBIX_DISCOVERER_INT_H_ */
