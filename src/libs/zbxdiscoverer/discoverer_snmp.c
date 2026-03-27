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

#include "discoverer_snmp.h"
#include "discoverer_async.h"
#include "../../libs/zbxpoller/checks_snmp.h"
#include "../../libs/zbxpoller/async_agent.h"
#include "zbxsysinfo.h"
#include "zbx_discoverer_constants.h"
#include "zbxpoller.h"

static ZBX_THREAD_LOCAL int log_worker_id;

#ifdef HAVE_NETSNMP
static void	process_snmp_result(void *data)
{
	discovery_async_result_t	*async_result = zbx_async_check_snmp_get_arg(data);
	zbx_dc_item_context_t	*item = zbx_async_check_snmp_get_item_context(data);
	char			**pvalue;

	zabbix_log(LOG_LEVEL_DEBUG, "[%d] In %s() key:'%s' host:'%s' addr:'%s' ret:%s", log_worker_id, __func__,
			item->key, item->host, item->interface.addr, zbx_result_string(item->ret));

	async_result->poller_config->processing--;
	async_result->dresult->processed_checks_per_ip++;

	if (SUCCEED == item->ret && NULL != (pvalue = ZBX_GET_TEXT_RESULT(&item->result)))
	{
		zbx_discoverer_dservice_t	*service;

		service = result_dservice_create(item->interface.port, async_result->dcheckid);
		zbx_strlcpy_utf8(service->value, *pvalue, ZBX_MAX_DISCOVERED_VALUE_SIZE);
		service->status = DOBJECT_STATUS_UP;
		zbx_vector_discoverer_services_ptr_append(&async_result->dresult->services, service);

		if (NULL ==  async_result->dresult->dnsname || '\0' == *async_result->dresult->dnsname)
		{
			const char	*rdns = ZBX_NULL2EMPTY_STR(zbx_async_check_snmp_get_reverse_dns(data));

			if ('\0' != *rdns && SUCCEED != zbx_validate_hostname(rdns))
				rdns = "";

			async_result->dresult->dnsname = zbx_strdup(async_result->dresult->dnsname, rdns);
		}
	}

	zbx_free(async_result);
	zbx_async_check_snmp_clean(data);

	zabbix_log(LOG_LEVEL_DEBUG, "[%d] End of %s()", log_worker_id, __func__);
}

static int	discovery_snmp(discovery_poller_config_t *poller_config, const zbx_dc_dcheck_t *dcheck,
		char *ip, const unsigned short port, zbx_discoverer_results_t *dresult, char **error)
{
	int				ret;
	zbx_dc_item_t			item;
	AGENT_RESULT			result;
	discovery_async_result_t	*async_result;

	async_result = (discovery_async_result_t *) zbx_malloc(NULL, sizeof(discovery_async_result_t));
	async_result->dresult = dresult;
	async_result->poller_config = poller_config;
	async_result->dcheckid = dcheck->dcheckid;

	zbx_init_agent_result(&result);

	memset(&item, 0, sizeof(zbx_dc_item_t));
	zbx_strscpy(item.key_orig, dcheck->key_);
	item.key = item.key_orig;

	item.interface.useip = 1;
	zbx_strscpy(item.interface.ip_orig, ip);
	item.interface.addr = item.interface.ip_orig;
	item.interface.port = port;

	item.value_type = ITEM_VALUE_TYPE_STR;

	switch (dcheck->type)
	{
		case SVC_SNMPv1:
			item.snmp_version = ZBX_IF_SNMP_VERSION_1;
			item.type = ITEM_TYPE_SNMP;
			break;
		case SVC_SNMPv2c:
			item.snmp_version = ZBX_IF_SNMP_VERSION_2;
			item.type = ITEM_TYPE_SNMP;
			break;
		case SVC_SNMPv3:
			item.snmp_version = ZBX_IF_SNMP_VERSION_3;
			item.type = ITEM_TYPE_SNMP;
	}

	item.snmp_community = zbx_strdup(NULL, dcheck->snmp_community);
	item.snmp_oid = dcheck->key_;
	item.timeout = dcheck->timeout;

	if (ZBX_IF_SNMP_VERSION_3 == item.snmp_version)
	{
		item.snmpv3_securityname = zbx_strdup(NULL, dcheck->snmpv3_securityname);
		item.snmpv3_authpassphrase = zbx_strdup(NULL, dcheck->snmpv3_authpassphrase);
		item.snmpv3_privpassphrase = zbx_strdup(NULL, dcheck->snmpv3_privpassphrase);

		item.snmpv3_contextname = zbx_strdup(NULL, dcheck->snmpv3_contextname);

		item.snmpv3_securitylevel = dcheck->snmpv3_securitylevel;
		item.snmpv3_authprotocol = dcheck->snmpv3_authprotocol;
		item.snmpv3_privprotocol = dcheck->snmpv3_privprotocol;
	}

	zbx_set_snmp_bulkwalk_options(poller_config->progname);

	if (SUCCEED != (ret = zbx_async_check_snmp(&item, &result, process_snmp_result, async_result, NULL,
			poller_config->base, NULL, poller_config->dnsbase, poller_config->config_source_ip,
			ZABBIX_ASYNC_RESOLVE_REVERSE_DNS_YES, 0)))
	{
		if (ZBX_ISSET_MSG(&result))
			*error = zbx_strdup(*error, *ZBX_GET_MSG_RESULT(&result));
		else
			*error = zbx_strdup(*error, "Error of snmp check");

		zbx_free(async_result);
	}
	else
		poller_config->processing++;

	zbx_free(item.snmp_community);
	zbx_free(item.snmpv3_securityname);
	zbx_free(item.snmpv3_authpassphrase);
	zbx_free(item.snmpv3_privpassphrase);
	zbx_free(item.snmpv3_contextname);
	zbx_free_agent_result(&result);

	zabbix_log(LOG_LEVEL_DEBUG, "[%d] %s() ip:%s port:%d, key:%s ret:%d", log_worker_id, __func__,
			ip, port, item.key_orig, ret);
	return ret;
}
#endif

static int	snmp_jobs_total_concurrency(zbx_discoverer_manager_t *dmanager)
{
	int	cuncurrent_check_max = 0;

	discoverer_queue_lock(&dmanager->queue);

	for (int i = 0; i < dmanager->job_refs.values_num; i++)
	{
		int			is_snmp = FAIL;
		zbx_discoverer_job_t	*job = dmanager->job_refs.values[i];
		zbx_list_iterator_t	li;

		zbx_list_iterator_init(&job->tasks, &li);

		do
		{
			zbx_discoverer_task_t	*task;
			unsigned char		dtype;

			(void)zbx_list_iterator_peek(&li, (void*)&task);
			dtype = GET_DTYPE(task);

			if (SVC_SNMPv3 == dtype || SVC_SNMPv2c == dtype || SVC_SNMPv1 == dtype)
			{
				is_snmp = SUCCEED;
				break;
			}
		}
		while (SUCCEED == zbx_list_iterator_next(&li));

		if (FAIL == is_snmp)
			continue;

		if (0 != job->concurrency_max)
			cuncurrent_check_max += job->concurrency_max;
		else
			cuncurrent_check_max += DISCOVERER_JOB_TASKS_INPROGRESS_MAX;
	}

	discoverer_queue_unlock(&dmanager->queue);

	if (DISCOVERER_SNMP_CHECKS_INPROGRESS_MAX < cuncurrent_check_max)
		cuncurrent_check_max = DISCOVERER_SNMP_CHECKS_INPROGRESS_MAX;

	return cuncurrent_check_max;
}

static int	discovery_task_is_finished(const zbx_discoverer_task_t *task, zbx_uint64_t druleid)
{
	int ret = (0 == discoverer_task_check_count_get(task) ? SUCCEED : FAIL);

	zabbix_log(LOG_LEVEL_DEBUG, "[%d] %s() druleid:" ZBX_FS_UI64 " type:%u ret:%s", log_worker_id, __func__,
			druleid, task->ds_dchecks.values[task->range.state.index_dcheck]->dcheck.type,
			SUCCEED == ret ? "SUCCEED" : "FAIL");
	return ret;
}

int	discovery_jobs_check_snmp(zbx_uint64_t druleid, zbx_discoverer_task_t *first_task, int concurrency_max,
		int *stop, zbx_discoverer_manager_t *dmanager, int worker_id, char **error)
{
	zbx_vector_discoverer_results_ptr_t	results;
	discovery_poller_config_t		poller_config;
	char					ip[ZBX_INTERFACE_IP_LEN_MAX], first_ip[ZBX_INTERFACE_IP_LEN_MAX];
	int					ret = FAIL, abort = SUCCEED, is_snmpv3 = FAIL, drule_n = 0, task_n = 0,
						check_n = 0;
	zbx_uint64_t				dec_counter = 0;
	zbx_discoverer_task_t			*task = NULL;

	if (0 == log_worker_id)
		log_worker_id = worker_id;

	zabbix_log(LOG_LEVEL_DEBUG, "[%d] In %s() first druleid:" ZBX_FS_UI64 " range id:" ZBX_FS_UI64 " type:%u",
			log_worker_id, __func__, druleid, first_task->range.id, GET_DTYPE(first_task));

	*first_ip = *ip = '\0';
#ifndef HAVE_NETSNMP
	*error = zbx_strdup(*error, "Support for SNMP checks was not compiled in.");
	goto general_fail;
#endif
	if (SUCCEED == (ret = discovery_task_is_finished(first_task, druleid)))
		goto general_fail;

	concurrency_max = snmp_jobs_total_concurrency(dmanager);

	if (SUCCEED != discovery_async_poller_init(dmanager, &poller_config))
	{
		*error = zbx_strdup(*error, "Cannot initialize discovery async poller.");
		goto general_fail;
	}

	zbx_vector_discoverer_results_ptr_create(&results);
	druleid = 0;

	while (NULL != (task = discoverer_queue_snmp_task_get(&dmanager->queue, task)))
	{
		if (druleid != task->ds_dchecks.values[task->range.state.index_dcheck]->dcheck.druleid)
		{
			druleid = task->ds_dchecks.values[task->range.state.index_dcheck]->dcheck.druleid;
			drule_n++;
		}

		task_n++;
		zabbix_log(LOG_LEVEL_DEBUG, "[%d] %s() start druleid:" ZBX_FS_UI64 " range id:" ZBX_FS_UI64
				" state.count:" ZBX_FS_UI64 " checks per ip:%u dchecks:%d type:%u concurrency_max:%d "
				"checks_per_worker_max:%d drule_n:%d task_n:%d check_n:%d", log_worker_id, __func__,
				druleid, task->range.id, task->range.state.count, task->range.state.checks_per_ip,
				task->ds_dchecks.values_num, GET_DTYPE(task), concurrency_max,
				dmanager->queue.checks_per_worker_max, drule_n, task_n, check_n);

		if (SVC_SNMPv3 == GET_DTYPE(task))
			is_snmpv3 = SUCCEED;

		do
		{
			zbx_discoverer_results_t	*result;
			zbx_dc_dcheck_t			*dcheck;

			TASK_IP2STR(task, ip);

			if (SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_DEBUG) && '\0' == *first_ip)
				zbx_strlcpy(first_ip, ip, sizeof(first_ip));

			result = discoverer_result_create(druleid, task);
			result->ip = zbx_strdup(NULL, ip);
			zbx_vector_discoverer_results_ptr_append(&results, result);
			dcheck = &task->ds_dchecks.values[task->range.state.index_dcheck]->dcheck;
			check_n++;

			ret = discovery_snmp(&poller_config, dcheck, ip,
					(unsigned short)task->range.state.port, result, error);

			if (FAIL == ret)
				goto out;

			while (concurrency_max == poller_config.processing)
				event_base_loop(poller_config.base, EVLOOP_ONCE);

			abort = discovery_net_check_result_flush(dmanager, &results, 0);

			if (SUCCEED == discovery_pending_checks_count_decrease(&dmanager->queue, concurrency_max,
					task->range.state.count, ++dec_counter))
			{
				dec_counter = 0;
			}
		}			/* we have to decrease range.state.count before exit by abort*/
		while (0 == *stop && SUCCEED == discoverer_range_check_iter(task) && SUCCEED == abort);

		(void)discovery_pending_checks_count_decrease(&dmanager->queue, concurrency_max, 0,
				task->range.state.count + dec_counter);

		zabbix_log(LOG_LEVEL_DEBUG, "[%d] %s() end druleid:" ZBX_FS_UI64 " type:%u state.count:" ZBX_FS_UI64
				" first ip:%s last ip:%s abort:%d ret:%d drule_n:%d task_n:%d check_n:%d",
				log_worker_id, __func__, druleid, GET_DTYPE(task), task->range.state.count, first_ip,
				ip, abort, ret, drule_n, task_n, check_n);
		*first_ip = '\0';
	}
out:
	while (0 != poller_config.processing)	/* try to close all handles if they are exhausted */
	{
		event_base_loop(poller_config.base, EVLOOP_ONCE);
		discovery_net_check_result_flush(dmanager, &results, 0);
	}

	discovery_net_check_result_flush(dmanager, &results, 1);
	zbx_vector_discoverer_results_ptr_clear_ext(&results, results_free);	/* Incomplete results*/
	zbx_vector_discoverer_results_ptr_destroy(&results);
	discovery_async_poller_destroy(&poller_config);
	/* we must clear the EnginID cache before the next snmpv3 dcheck and */
	/* remove unused collected values in any case */
	if (SUCCEED == is_snmpv3)
		zbx_clear_cache_snmp(dmanager->process_type, FAIL);
general_fail:
	zabbix_log(LOG_LEVEL_DEBUG, "[%d] End of %s()  drule_n:%d task_n:%d check_n:%d abort:%d ret:%d", log_worker_id,
			__func__, drule_n, task_n, check_n, abort, ret);

	return ret;
}
