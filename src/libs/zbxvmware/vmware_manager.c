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

#include "zbxvmware.h"
#include "zbxthreads.h"

#if defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL)

#include "vmware_internal.h"

#include "vmware_perfcntr.h"
#include "vmware_event.h"

#include "zbxtimekeeper.h"
#include "zbxalgo.h"
#include "zbxnix.h"
#include "zbxself.h"
#include "zbxtime.h"
#include "zbxlog.h"

/******************************************************************************
 *                                                                            *
 * Purpose: returns string value of vmware job types                          *
 *                                                                            *
 * Parameters: job - [IN]                                                     *
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
		case ZBX_VMWARE_UPDATE_EVENTLOG:
			return "update_eventlog";
		default:
			return "unknown_job";
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: picks next job from queue and service ttl check                   *
 *                                                                            *
 * Parameters: vmw      - [IN] vmware object                                  *
 *             time_now - [IN]                                                *
 *                                                                            *
 * Return value: job for object or NULL                                       *
 *                                                                            *
 ******************************************************************************/
static zbx_vmware_job_t	*vmware_job_get(zbx_vmware_t *vmw, time_t time_now)
{
	zbx_binary_heap_elem_t	*elem;
	zbx_vmware_job_t	*job = NULL;
	time_t			lastaccess;
	int			revision;

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
	lastaccess = (ZBX_VMWARE_UPDATE_EVENTLOG == job->type) ? job->service->eventlog.lastaccess :
			job->service->lastaccess;
	revision = (ZBX_VMWARE_UPDATE_EVENTLOG == job->type) ? job->service->eventlog.job_revision : 0;

	if ((0 != lastaccess && 0 != job->ttl && time_now - lastaccess > job->ttl) || job->revision != revision)
	{
		job->expired = SUCCEED;
	}
unlock:
	zbx_vmware_unlock();
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() queue:%d type:%s", __func__, vmw->jobs_queue.elems_num,
			NULL == job ? "none" : vmware_job_type_string(job));

	return job;
}

/******************************************************************************
 *                                                                            *
 * Purpose: executes task of job                                              *
 *                                                                            *
 * Parameters: job                     - [IN] job object                      *
 *             config_source_ip        - [IN]                                 *
 *             config_vmware_timeout   - [IN]                                 *
 *             config_vmware_frequency - [IN]                                 *
 *                                                                            *
 * Return value: count of successfully executed jobs                          *
 *                                                                            *
 ******************************************************************************/
static int	vmware_job_exec(zbx_vmware_job_t *job, const char *config_source_ip, int config_vmware_timeout,
		int config_vmware_frequency)
{
	int	ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() type:%s revision:%d", __func__, vmware_job_type_string(job),
			job->revision);

	if (ZBX_VMWARE_UPDATE_CONF != job->type && 0 == (job->service->state & ZBX_VMWARE_STATE_READY))
		goto out;

	switch (job->type)
	{
		case ZBX_VMWARE_UPDATE_CONF:
			ret = zbx_vmware_service_update(job->service, config_source_ip, config_vmware_timeout,
					config_vmware_frequency);
			break;
		case ZBX_VMWARE_UPDATE_PERFCOUNTERS:
			ret = zbx_vmware_service_update_perf(job->service, config_source_ip, config_vmware_timeout);
			break;
		case ZBX_VMWARE_UPDATE_REST_TAGS:
			ret = zbx_vmware_service_update_tags(job->service, config_source_ip, config_vmware_timeout);
			break;
		case ZBX_VMWARE_UPDATE_EVENTLOG:
			ret = zbx_vmware_service_eventlog_update(job->service, config_source_ip, config_vmware_timeout);
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
 * Purpose: sets time of next job execution and returns job to queue          *
 *                                                                            *
 * Parameters: vmw                 - [IN] vmware object                       *
 *             job                 - [IN] job object                          *
 *             time_now            - [IN] current time                        *
 *             cache_update_period - [IN]                                     *
 *             perf_update_period  - [IN]                                     *
 *                                                                            *
 ******************************************************************************/
static void	vmware_job_schedule(zbx_vmware_t *vmw, zbx_vmware_job_t *job, time_t time_now,
		int cache_update_period, int perf_update_period)
{
#define ZBX_VMWARE_SERVICE_TTL			SEC_PER_HOUR
#define ZBX_VMWARE_EVENTLOG_MIN_INTERVAL	5

	zbx_binary_heap_elem_t	elem_new = {0, job};

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() queue:%d type:%s", __func__, vmw->jobs_queue.elems_num,
			vmware_job_type_string(job));

	zbx_vmware_lock();

	switch (job->type)
	{
		case ZBX_VMWARE_UPDATE_CONF:
			if (0 == job->ttl)
				job->ttl = ZBX_VMWARE_SERVICE_TTL;

			job->nextcheck = time_now + cache_update_period;
			break;
		case ZBX_VMWARE_UPDATE_PERFCOUNTERS:
			if (0 == job->ttl)
				job->ttl = ZBX_VMWARE_SERVICE_TTL;

			job->nextcheck = time_now + perf_update_period;
			break;
		case ZBX_VMWARE_UPDATE_REST_TAGS:
			if (0 == job->ttl)
				job->ttl = ZBX_VMWARE_SERVICE_TTL;

			job->nextcheck = time_now + cache_update_period;
			break;
		case ZBX_VMWARE_UPDATE_EVENTLOG:
			job->ttl = 2 * (0 != job->service->eventlog.interval ?
					job->service->eventlog.interval : perf_update_period) +
					ZBX_VMWARE_EVENTLOG_MIN_INTERVAL;

			job->nextcheck = time_now + (0 == job->service->eventlog.interval ? perf_update_period :
					(ZBX_VMWARE_EVENTLOG_MIN_INTERVAL > job->service->eventlog.interval ?
					ZBX_VMWARE_EVENTLOG_MIN_INTERVAL : job->service->eventlog.interval));

			if (job->nextcheck > time_now + ZBX_VMWARE_SERVICE_TTL)
				job->nextcheck = time_now + ZBX_VMWARE_SERVICE_TTL;
			break;
	}

	zbx_binary_heap_insert(&vmw->jobs_queue, &elem_new);
	zbx_vmware_unlock();

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() type:%s nextcheck:%s ttl:" ZBX_FS_TIME_T, __func__,
			vmware_job_type_string(job), zbx_time2str(job->nextcheck, NULL), job->ttl);

#undef ZBX_VMWARE_EVENTLOG_MIN_INTERVAL
#undef ZBX_VMWARE_SERVICE_TTL
}

#endif

/******************************************************************************
 *                                                                            *
 * Purpose: vmware collector main loop                                        *
 *                                                                            *
 ******************************************************************************/
ZBX_THREAD_ENTRY(zbx_vmware_thread, args)
{
#if defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL)
	int				services_updated = 0, services_removed = 0,
					server_num = ((zbx_thread_args_t *)args)->info.server_num,
					process_num = ((zbx_thread_args_t *)args)->info.process_num;
	double				time_stat, time_idle = 0;
	const zbx_thread_info_t		*info = &((zbx_thread_args_t *)args)->info;
	unsigned char			process_type = ((zbx_thread_args_t *)args)->info.process_type;
	const zbx_thread_vmware_args	*vmware_args_in = (const zbx_thread_vmware_args *)
					(((zbx_thread_args_t *)args)->args);

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(info->program_type),
			server_num, get_process_type_string(process_type), process_num);

	zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_BUSY);

#define JOB_TIMEOUT	1
#define STAT_INTERVAL	5	/* if a process is busy and does not sleep then update status not faster than */
				/* once in STAT_INTERVAL seconds */

	time_stat = zbx_time();

	while (ZBX_IS_RUNNING())
	{
		zbx_vmware_job_t	*job;
		double			time_now = zbx_time();

		zbx_update_env(get_process_type_string(process_type), time_now);

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

		while (NULL != (job = vmware_job_get(zbx_vmware_get_vmware(), (time_t)time_now)))
		{
			if (SUCCEED == job->expired)
			{
				services_removed += zbx_vmware_job_remove(job);
				continue;
			}

			services_updated += vmware_job_exec(job, vmware_args_in->config_source_ip,
					vmware_args_in->config_vmware_timeout, vmware_args_in->config_vmware_frequency);
			vmware_job_schedule(zbx_vmware_get_vmware(), job, (time_t)time_now,
					vmware_args_in->config_vmware_frequency,
					vmware_args_in->config_vmware_perf_frequency);
		}

		if (zbx_time() - time_now <= JOB_TIMEOUT)
		{
			time_idle += JOB_TIMEOUT;
			zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_IDLE);
			zbx_sleep_loop(info, JOB_TIMEOUT);
			zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_BUSY);
		}
	}

	zbx_setproctitle("%s #%d [terminated]", get_process_type_string(process_type), process_num);
	xmlCleanupParser();
	curl_global_cleanup();

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
