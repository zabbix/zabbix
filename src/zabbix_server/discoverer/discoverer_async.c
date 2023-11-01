/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

#include "discoverer.h"
#include "discoverer_job.h"
#include "discoverer_async.h"
#include "zbxlog.h"
#include "../poller/checks_snmp.h"
#include "zbxsysinfo.h"
#include "zbx_discoverer_constants.h"
#include <event2/dns.h>

static ZBX_THREAD_LOCAL int log_worker_id;

typedef struct
{
	int			processing;
	int			config_timeout;
	const char		*config_source_ip;
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
discovery_snmp_result_t;

static void	discovery_async_poller_dns_init(discovery_poller_config_t *poller_config)
{
	char	*timeout;

	if (NULL == (poller_config->dnsbase = evdns_base_new(poller_config->base, EVDNS_BASE_INITIALIZE_NAMESERVERS)))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot initialize asynchronous DNS library");
		exit(EXIT_FAILURE);
	}

	timeout = zbx_dsprintf(NULL, "%d", poller_config->config_timeout);

	if (0 != evdns_base_set_option(poller_config->dnsbase, "timeout:", timeout))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot set timeout to asynchronous DNS library");
		exit(EXIT_FAILURE);
	}

	zbx_free(timeout);
}

static void	discovery_async_poller_destroy(discovery_poller_config_t *poller_config)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	evdns_base_free(poller_config->dnsbase, 1);
	event_base_free(poller_config->base);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	discovery_async_poller_init(zbx_discoverer_manager_t *dmanager,
		discovery_poller_config_t *poller_config)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (NULL == (poller_config->base = event_base_new()))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot initialize event base");
		exit(EXIT_FAILURE);
	}

	poller_config->processing = 0;
	poller_config->config_source_ip = dmanager->source_ip;
	poller_config->config_timeout = dmanager->config_timeout;
	discovery_async_poller_dns_init(poller_config);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

struct zbx_snmp_context
{
	void			*arg;
	void			*arg_action;
	zbx_dc_item_context_t	item;
};

static void	process_snmp_result(void *data)
{
	zbx_snmp_context_t	*snmp_context = (zbx_snmp_context_t *)data;
	discovery_snmp_result_t	*snmp_result = (discovery_snmp_result_t *)snmp_context->arg;
	char			**pvalue;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() key:'%s' host:'%s' addr:'%s' ret:%s", __func__, snmp_context->item.key,
			snmp_context->item.host, snmp_context->item.interface.addr,
			zbx_result_string(snmp_context->item.ret));

	snmp_result->poller_config->processing--;

	if (SUCCEED == snmp_context->item.ret && NULL != (pvalue = ZBX_GET_TEXT_RESULT(&snmp_context->item.result)))
	{
		zbx_discoverer_dservice_t	*service;

		service = result_dservice_create(snmp_context->item.interface.port, snmp_result->dcheckid);
		zbx_strlcpy_utf8(service->value, *pvalue, ZBX_MAX_DISCOVERED_VALUE_SIZE);
		service->status = DOBJECT_STATUS_UP;
		zbx_vector_discoverer_services_ptr_append(&snmp_result->dresult->services, service);

		if (NULL ==  snmp_result->dresult->dnsname)
		{
			char	dns[ZBX_INTERFACE_DNS_LEN_MAX];

			zbx_gethost_by_ip(snmp_result->dresult->ip, dns, sizeof(dns));
			snmp_result->dresult->dnsname = zbx_strdup(NULL, dns);
		}
	}

	zbx_free(snmp_result);
	zbx_async_check_snmp_clean(snmp_context);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	discovery_snmp(discovery_poller_config_t *poller_config, const zbx_dc_dcheck_t *dcheck,
		char *ip, const int port, zbx_discoverer_results_t *dresult)
{
	zbx_dc_item_t		item;
	AGENT_RESULT		result;
	discovery_snmp_result_t	*snmp_result;

	snmp_result = (discovery_snmp_result_t *) zbx_malloc(NULL, sizeof(discovery_snmp_result_t));
	snmp_result->dresult = dresult;
	snmp_result->poller_config = poller_config;
	snmp_result->dcheckid = dcheck->dcheckid;

	zbx_init_agent_result(&result);

	memset(&item, 0, sizeof(zbx_dc_item_t));
	zbx_strscpy(item.key_orig, dcheck->key_);

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

	item.key = zbx_strdup(NULL, item.key_orig);
	item.snmp_community = zbx_strdup(NULL, dcheck->snmp_community);
	item.snmp_oid = dcheck->key_;
	item.timeout = dcheck->timeout;

	if (ZBX_IF_SNMP_VERSION_3 == item.snmp_version)
	{
		item.snmpv3_securityname = zbx_strdup(NULL,
				dcheck->snmpv3_securityname);
		item.snmpv3_authpassphrase = zbx_strdup(NULL,
				dcheck->snmpv3_authpassphrase);
		item.snmpv3_privpassphrase = zbx_strdup(NULL,
				dcheck->snmpv3_privpassphrase);

		item.snmpv3_contextname = zbx_strdup(NULL, dcheck->snmpv3_contextname);

		item.snmpv3_securitylevel = dcheck->snmpv3_securitylevel;
		item.snmpv3_authprotocol = dcheck->snmpv3_authprotocol;
		item.snmpv3_privprotocol = dcheck->snmpv3_privprotocol;
	}

	zbx_set_snmp_bulkwalk_options();

	if (FAIL == zbx_async_check_snmp(&item, &result, process_snmp_result, snmp_result, NULL,
			poller_config->base, poller_config->dnsbase, poller_config->config_source_ip, 1))
	{
		zbx_free(snmp_result);
	}
	else
		poller_config->processing++;

	zbx_free(item.key);
	zbx_free(item.snmp_community);
	zbx_free(item.snmpv3_securityname);
	zbx_free(item.snmpv3_authpassphrase);
	zbx_free(item.snmpv3_privpassphrase);
	zbx_free(item.snmpv3_contextname);
}

static void	discoverer_net_check_result_flush(zbx_discoverer_manager_t *dmanager, zbx_discoverer_task_t *task,
		zbx_vector_discoverer_results_ptr_t *results, int force)
{
	static ZBX_THREAD_LOCAL time_t	last;
	time_t				now = time(NULL);

	if (0 == force && now - last < DISCOVERER_DELAY)
		return;

	pthread_mutex_lock(&dmanager->results_lock);
	discover_results_merge(&dmanager->results, results, task);
	pthread_mutex_unlock(&dmanager->results_lock);

	zabbix_log(LOG_LEVEL_DEBUG, "[%d] %s() results:%d", log_worker_id, __func__, results->values_num);

	zbx_vector_discoverer_results_ptr_clear_ext(results, results_free);
	last = now;
}

void	discoverer_net_check_range(zbx_uint64_t druleid, zbx_discoverer_task_t *task, int worker_max, int *stop,
		zbx_discoverer_manager_t *dmanager, int worker_id)
{
	zbx_vector_discoverer_results_ptr_t	results;
	zbx_vector_portrange_t			port_ranges;
	discovery_poller_config_t		poller_config;
	char					ip[ZBX_INTERFACE_IP_LEN_MAX];

	log_worker_id = worker_id;
	zabbix_log(LOG_LEVEL_DEBUG, "[%d] In %s() druleid:" ZBX_FS_UI64 " dchecks:%d worker_max:%d", log_worker_id,
			__func__, druleid, task->dchecks.values_num, worker_max);

	if (DISCOVERY_ADDR_RANGE != task->addr_type)
	{
		zabbix_log(LOG_LEVEL_WARNING, "range checks failed with error: address type is out of range");
		return;
	}

	if (0 == worker_max)
		worker_max = DISCOVERER_JOB_TASKS_INPROGRESS_MAX;

	discovery_async_poller_init(dmanager, &poller_config);
	zbx_vector_discoverer_results_ptr_create(&results);
	zbx_vector_portrange_create(&port_ranges);
	*ip = '\0';

	while (SUCCEED == zbx_iprange_uniq_iter(task->addr.range->ipranges->values,
			task->addr.range->ipranges->values_num, &task->addr.range->state.index_ip,
			task->addr.range->state.ipaddress) && 0 != task->addr.range->state.count && 0 == *stop)

	{
		zbx_discoverer_results_t	*result;
		int				i;

		(void)zbx_iprange_ip2str(task->addr.range->ipranges->values[task->addr.range->state.index_ip].type,
				task->addr.range->state.ipaddress, ip, sizeof(ip));

		result = discovery_result_create(druleid, task->unique_dcheckid);
		result->ip = zbx_strdup(NULL, ip);
		zbx_vector_discoverer_results_ptr_append(&results, result);

		for (i = 0; i < task->dchecks.values_num && 0 != task->addr.range->state.count && 0 == *stop; i++)
		{
			zbx_dc_dcheck_t	*dcheck = task->dchecks.values[i];

			dcheck_port_ranges_get(dcheck->ports, &port_ranges);

			while (SUCCEED == zbx_portrange_uniq_iter(port_ranges.values, port_ranges.values_num,
					&task->addr.range->state.index_port, &task->addr.range->state.port) &&
					0 != task->addr.range->state.count && 0 == *stop)
			{
				switch (dcheck->type)
				{
				case SVC_SNMPv1:
				case SVC_SNMPv2c:
				case SVC_SNMPv3:
#ifdef HAVE_NETSNMP
					discovery_snmp(&poller_config, dcheck, ip, task->addr.range->state.port, result);
#else
					errcodes[i] = NOTSUPPORTED;
					SET_MSG_RESULT(&results[i], zbx_strdup(NULL,
							"Support for SNMP checks was not compiled in."));
#endif
					break;
				default:
					zabbix_log(LOG_LEVEL_WARNING, "dcheck is not supported:%u", dcheck->type);
				}

				while (worker_max == poller_config.processing)
					event_base_loop(poller_config.base, EVLOOP_ONCE);

				task->addr.range->state.count--;
			}

			task->addr.range->state.port = 0;
			zbx_vector_portrange_clear(&port_ranges);
		}
	}

	while (0 == *stop && 0 != poller_config.processing)
		event_base_loop(poller_config.base, EVLOOP_ONCE);

	discoverer_net_check_result_flush(dmanager, task, &results, 1);
	zbx_vector_discoverer_results_ptr_destroy(&results);
	zbx_vector_portrange_destroy(&port_ranges);

	discovery_async_poller_destroy(&poller_config);

	zabbix_log(LOG_LEVEL_DEBUG, "[%d] End of %s()", log_worker_id, __func__);
}

