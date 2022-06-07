/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "vmware.h"
#include "zbxnix.h"
#include "zbxself.h"


extern int		CONFIG_VMWARE_FREQUENCY;
extern int		CONFIG_VMWARE_PERF_FREQUENCY;

extern ZBX_THREAD_LOCAL unsigned char	process_type;
extern unsigned char			program_type;
extern ZBX_THREAD_LOCAL int		server_num, process_num;

extern zbx_vmware_t	*vmware;

#define ZBX_VMWARE_CACHE_UPDATE_PERIOD	CONFIG_VMWARE_FREQUENCY
#define ZBX_VMWARE_PERF_UPDATE_PERIOD	CONFIG_VMWARE_PERF_FREQUENCY
#define ZBX_VMWARE_SERVICE_TTL		SEC_PER_HOUR

typedef struct
{
	int	updated;
	int	removed;
}
zbx_vmware_jobs_count_t;

/******************************************************************************
 *                                                                            *
 * Purpose: return string value of vmware job types                           *
 *                                                                            *
 * Parameters: job - [IN] the vmware job                                      *
 *                                                                            *
 * Return value: job type string                                              *
 *                                                                            *
 ******************************************************************************/
static char	*vmware_job_type_string(zbx_vmware_job_t *job)
{
	switch (job->type)
	{
	case ZBX_VMWARE_UPDATE_CONF:
		return "update_conf";
	case ZBX_VMWARE_UPDATE_PERFCOUNTERS:
		return "update_perf_counters";
	case ZBX_VMWARE_UPDATE_REST_TAGS:
		return "update_tags";
	case ZBX_VMWARE_REMOVE_SERVICE:
		return "remove_service";
	default:
		return "unknown_job";
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: pick the next job from the queue                                  *
 *                                                                            *
 * Parameters: vmw      - [IN] the vmware object                              *
 *             time_now - [IN] the current time                               *
 *                                                                            *
 * Return value: job for object or NULL                                       *
 *                                                                            *
 ******************************************************************************/
static zbx_vmware_job_t	*vmware_job_get(zbx_vmware_t *vmw, int time_now)
{
	zbx_binary_heap_elem_t	*elem;
	zbx_vmware_job_t	*job = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() queue:%d", __func__, vmw->jobs_queue.elems_num);

	zbx_vmware_lock();

	if (SUCCEED == zbx_binary_heap_empty(&vmw->jobs_queue))
		goto unlock;;

	elem = zbx_binary_heap_find_min(&vmw->jobs_queue);
	job = (zbx_vmware_job_t *)elem->data;

	if (time_now < job->nextcheck)
	{
		job = NULL;
		goto unlock;
	}

	zbx_binary_heap_remove_min(&vmw->jobs_queue);
	job->nextcheck = 0;
unlock:
	zbx_vmware_unlock();
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() queue:%d type:%s", __func__, vmw->jobs_queue.elems_num,
			NULL == job ? "none" : vmware_job_type_string(job));

	return job;
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute task of job and increase the statistics counters          *
 *                                                                            *
 * Parameters: vmw      - [IN] the vmware object                              *
 *             job      - [IN] the job object                                 *
 *             jobs_num - [IN/OUT] the statistics counters                    *
 *                                                                            *
 ******************************************************************************/
static void	vmware_job_exec(zbx_vmware_job_t *job, zbx_vmware_jobs_count_t *jobs_num)
{
	int	ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() type:%s", __func__, vmware_job_type_string(job));

	switch (job->type)
	{
	case ZBX_VMWARE_UPDATE_CONF:
		if (0 != (job->service->state & ZBX_VMWARE_STATE_REMOVING))
		{
			job->finished = SUCCEED;
			break;
		}

		ret = zbx_vmware_service_update(job->service);
		jobs_num->updated += SUCCEED == ret ? 1 : 0;
		break;
	case ZBX_VMWARE_UPDATE_PERFCOUNTERS:
		if (0 != (job->service->state & ZBX_VMWARE_STATE_REMOVING))
		{
			job->finished = SUCCEED;
			break;
		}
		else if (0 == (job->service->state & ZBX_VMWARE_STATE_READY))
			break;

		ret = zbx_vmware_service_update_perf(job->service);
		jobs_num->updated += SUCCEED == ret ? 1 : 0;
		break;
/*	case ZBX_VMWARE_UPDATE_REST_TAGS:
		if (0 != (job->service->state & ZBX_VMWARE_STATE_REMOVING))
		{
			job->finished = SUCCEED;
			break;
		}
		else if (0 == (job->service->state & ZBX_VMWARE_STATE_READY))
			break;

		ret = vmware_service_update_tags(job->service);
		jobs_num->updated += SUCCEED == ret ? 1 : 0;
		break;
*/	case ZBX_VMWARE_REMOVE_SERVICE:
		if (1 < job->service->jobs_num)
			break;

		zbx_vmware_service_remove(job->service);
		job->service = NULL;
		job->finished = SUCCEED;
		jobs_num->removed += 1;
		break;
	default:
		ret = FAIL;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() type:%s ret:%s", __func__, vmware_job_type_string(job),
			zbx_result_string(ret));
}

/******************************************************************************
 *                                                                            *
 * Purpose: set time of next job execution and return job to the queue        *
 *                                                                            *
 * Parameters: vmw      - [IN] the vmware object                              *
 *             job      - [IN] the job object                                 *
 *             time_now - [IN] the current time                               *
 *                                                                            *
 ******************************************************************************/
static void	vmware_job_schedule(zbx_vmware_t *vmw, zbx_vmware_job_t *job, int time_now)
{
	zbx_binary_heap_elem_t	elem_new = {0, job};

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() queue:%d type:%s", __func__, vmw->jobs_queue.elems_num,
			vmware_job_type_string(job));

	if (SUCCEED == job->finished)
	{
		zbx_vmware_job_remove(job);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() type:%s job removed", __func__, vmware_job_type_string(job));
		return;
	}

	switch (job->type)
	{
	case ZBX_VMWARE_UPDATE_CONF:
		job->nextcheck = time_now + ZBX_VMWARE_CACHE_UPDATE_PERIOD;
		break;
	case ZBX_VMWARE_UPDATE_PERFCOUNTERS:
		job->nextcheck = time_now + ZBX_VMWARE_PERF_UPDATE_PERIOD;
		break;
	case ZBX_VMWARE_UPDATE_REST_TAGS:
		job->nextcheck = time_now + ZBX_VMWARE_CACHE_UPDATE_PERIOD;
		break;
	case ZBX_VMWARE_REMOVE_SERVICE:
		job->nextcheck = time_now + ZBX_VMWARE_CACHE_UPDATE_PERIOD;
		break;
	}


	zbx_vmware_lock();
	zbx_binary_heap_insert(&vmw->jobs_queue, &elem_new);
	zbx_vmware_unlock();

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() type:%s nextcheck:%s", __func__, vmware_job_type_string(job),
			zbx_time2str(job->nextcheck, NULL));
}

/******************************************************************************
 *                                                                            *
 * Purpose: check unused services and create job for remove the service       *
 *                                                                            *
 * Parameters: vmw      - [IN] the vmware object                              *
 *             service  - [IN] the service object                             *
 *             time_now - [IN] the current time                               *
 *                                                                            *
 ******************************************************************************/
static void	vmware_service_ttl_check(zbx_vmware_t *vmw, zbx_vmware_service_t *service, int time_now)
{

	if (time_now - service->lastaccess < ZBX_VMWARE_SERVICE_TTL)
		return;

	zbx_vmware_lock();
	service->state |= ZBX_VMWARE_STATE_REMOVING;
	zbx_vmware_job_create(vmw, service, ZBX_VMWARE_REMOVE_SERVICE);
	zbx_vmware_unlock();
}

/******************************************************************************
 *                                                                            *
 * Purpose: the vmware collector main loop                                    *
 *                                                                            *
 ******************************************************************************/
ZBX_THREAD_ENTRY(vmware_thread, args)
{
#if defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL)
	int			time_now;
	double			time_stat, time_idle = 0;
	zbx_vmware_jobs_count_t	serv_num = {0, 0};

	process_type = ((zbx_thread_args_t *)args)->process_type;
	server_num = ((zbx_thread_args_t *)args)->server_num;
	process_num = ((zbx_thread_args_t *)args)->process_num;

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(program_type),
			server_num, get_process_type_string(process_type), process_num);

	update_selfmon_counter(ZBX_PROCESS_STATE_BUSY);

#define JOB_TIMEOUT	1
#define STAT_INTERVAL	5	/* if a process is busy and does not sleep then update status not faster than */
				/* once in STAT_INTERVAL seconds */

	time_stat = zbx_time();

	while (ZBX_IS_RUNNING())
	{
		zbx_vmware_job_t	*job;

		time_now = zbx_time();
		zbx_update_env(time_now);

		if (STAT_INTERVAL < time_now - time_stat)
		{
			zbx_setproctitle("%s #%d [updated %d, removed %d VMware services, idle " ZBX_FS_DBL
					" sec during " ZBX_FS_DBL " sec]", get_process_type_string(process_type),
					process_num, serv_num.updated, serv_num.removed,
					time_idle, time_now - time_stat);

			time_stat = time_now;
			time_idle = 0;
			serv_num.updated = 0;
			serv_num.removed = 0;
		}

		while (NULL != (job = vmware_job_get(vmware, time_now)))
		{
			vmware_job_exec(job, &serv_num);
			vmware_job_schedule(vmware, job, time_now);
			vmware_service_ttl_check(vmware, job->service, time_now);
		}

		if (zbx_time() - time_now <= JOB_TIMEOUT)
		{
			time_idle += JOB_TIMEOUT;
			update_selfmon_counter(ZBX_PROCESS_STATE_IDLE);
			zbx_sleep_loop(JOB_TIMEOUT);
			update_selfmon_counter(ZBX_PROCESS_STATE_BUSY);
		}
	}

	zbx_setproctitle("%s #%d [terminated]", get_process_type_string(process_type), process_num);

	while (1)
		zbx_sleep(SEC_PER_MIN);
#undef STAT_INTERVAL
#undef JOB_TIMEOUT
#else
	ZBX_UNUSED(args);
	THIS_SHOULD_NEVER_HAPPEN;
	zbx_thread_exit(EXIT_SUCCESS);
#endif
}

