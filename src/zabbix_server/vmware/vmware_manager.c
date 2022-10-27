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

#if defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL)

extern int				CONFIG_VMWARE_FREQUENCY;
extern int				CONFIG_VMWARE_PERF_FREQUENCY;

#define ZBX_VMWARE_CACHE_UPDATE_PERIOD	CONFIG_VMWARE_FREQUENCY
#define ZBX_VMWARE_PERF_UPDATE_PERIOD	CONFIG_VMWARE_PERF_FREQUENCY
#define ZBX_VMWARE_SERVICE_TTL		SEC_PER_HOUR

extern unsigned char			program_type;
extern ZBX_THREAD_LOCAL unsigned char	process_type;
extern ZBX_THREAD_LOCAL int		server_num, process_num;
extern zbx_vmware_t			*vmware;

/******************************************************************************
 *                                                                            *
 * Purpose: return string value of vmware job types                           *
 *                                                                            *
 * Parameters: job - [IN] the vmware job                                      *
 *                                                                            *
 * Return value: job type string                                              *
 *                                                                            *
 ******************************************************************************/
static const char	*vmware_job_type_string(zbx_vmware_job_t *job)
{
	switch (job->type)
	{
		case ZBX_VMWARE_UPDATE_CONF:
			return "update_conf";
		case ZBX_VMWARE_UPDATE_PERFCOUNTERS:
			return "update_perf_counters";
		case ZBX_VMWARE_UPDATE_REST_TAGS:
			return "update_tags";
		default:
			return "unknown_job";
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: pick the next job from the queue and service ttl check            *
 *                                                                            *
 * Parameters: vmw      - [IN] the vmware object                              *
 *             time_now - [IN] the current time                               *
 *                                                                            *
 * Return value: job for object or NULL                                       *
 *                                                                            *
 ******************************************************************************/
static zbx_vmware_job_t	*vmware_job_get(zbx_vmware_t *vmw, time_t time_now)
{
	zbx_binary_heap_elem_t	*elem;
	zbx_vmware_job_t	*job = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() queue:%d", __func__, vmw->jobs_queue.elems_num);

	zbx_vmware_lock();

	if (SUCCEED == zbx_binary_heap_empty(&vmw->jobs_queue))
		goto unlock;

	elem = zbx_binary_heap_find_min(&vmw->jobs_queue);
	job = (zbx_vmware_job_t *)elem->data;

	if (time_now < job->nextcheck)
	{
		job = NULL;
		goto unlock;
	}

	zbx_binary_heap_remove_min(&vmw->jobs_queue);
	job->nextcheck = 0;

	if (0 != job->service->lastaccess && time_now - job->service->lastaccess > ZBX_VMWARE_SERVICE_TTL)
		job->expired = SUCCEED;
unlock:
	zbx_vmware_unlock();
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() queue:%d type:%s", __func__, vmw->jobs_queue.elems_num,
			NULL == job ? "none" : vmware_job_type_string(job));

	return job;
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute task of job                                               *
 *                                                                            *
 * Parameters: job - [IN] the job object                                      *
 *                                                                            *
 * Return value: count of successfully executed jobs                          *
 *                                                                            *
 ******************************************************************************/
static int	vmware_job_exec(zbx_vmware_job_t *job)
{
	int	ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() type:%s", __func__, vmware_job_type_string(job));

	if (ZBX_VMWARE_UPDATE_CONF != job->type && 0 == (job->service->state & ZBX_VMWARE_STATE_READY))
		goto out;

	switch (job->type)
	{
		case ZBX_VMWARE_UPDATE_CONF:
			ret = zbx_vmware_service_update(job->service);
			break;
		case ZBX_VMWARE_UPDATE_PERFCOUNTERS:
			ret = zbx_vmware_service_update_perf(job->service);
			break;
		case ZBX_VMWARE_UPDATE_REST_TAGS:
			ret = zbx_vmware_service_update_tags(job->service);
			break;
		default:
			ret = FAIL;
	}
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() type:%s ret:%s", __func__, vmware_job_type_string(job),
			zbx_result_string(ret));

	return SUCCEED == ret ? 1 : 0;
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
static void	vmware_job_schedule(zbx_vmware_t *vmw, zbx_vmware_job_t *job, time_t time_now)
{
	zbx_binary_heap_elem_t	elem_new = {0, job};

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() queue:%d type:%s", __func__, vmw->jobs_queue.elems_num,
			vmware_job_type_string(job));

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
	}

	zbx_vmware_lock();
	zbx_binary_heap_insert(&vmw->jobs_queue, &elem_new);
	zbx_vmware_unlock();

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() type:%s nextcheck:%s", __func__, vmware_job_type_string(job),
			zbx_time2str(job->nextcheck, NULL));
}

#endif

/******************************************************************************
 *                                                                            *
 * Purpose: the vmware collector main loop                                    *
 *                                                                            *
 ******************************************************************************/
ZBX_THREAD_ENTRY(vmware_thread, args)
{
#if defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL)
	int	services_updated = 0, services_removed = 0;
	double	time_now, time_stat, time_idle = 0;

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
					process_num, services_updated, services_removed,
					time_idle, time_now - time_stat);

			time_stat = time_now;
			time_idle = 0;
			services_updated = 0;
			services_removed = 0;
		}

		while (NULL != (job = vmware_job_get(vmware, (int)time_now)))
		{
			if (SUCCEED == job->expired)
			{
				services_removed += zbx_vmware_job_remove(job);
				continue;
			}

			services_updated += vmware_job_exec(job);
			vmware_job_schedule(vmware, job, (time_t)time_now);
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
