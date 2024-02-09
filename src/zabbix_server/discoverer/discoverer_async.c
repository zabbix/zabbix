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

#include "discoverer_job.h"
#include "discoverer_async.h"
#include "../poller/checks_snmp.h"
#include "../poller/async_agent.h"
#include "async_tcpsvc.h"
#include "async_telnet.h"
#include "zbxsysinfo.h"
#include "zbx_discoverer_constants.h"
#include <event2/dns.h>
#include "zbxasyncpoller.h"
#include "zbxcacheconfig.h"
#include "zbxcomms.h"
#include "zbxdbhigh.h"
#include "zbxip.h"
#include "zbxstr.h"

static ZBX_THREAD_LOCAL int log_worker_id;

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
	zabbix_log(LOG_LEVEL_DEBUG, "[%d] In %s()", log_worker_id, __func__);

	evdns_base_free(poller_config->dnsbase, 1);
	event_base_free(poller_config->base);

	zabbix_log(LOG_LEVEL_DEBUG, "[%d] End of %s()", log_worker_id, __func__);
}

static void	discovery_async_poller_init(zbx_discoverer_manager_t *dmanager,
		discovery_poller_config_t *poller_config)
{
	zabbix_log(LOG_LEVEL_DEBUG, "[%d] In %s()", log_worker_id, __func__);

	if (NULL == (poller_config->base = event_base_new()))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot initialize event base");
		exit(EXIT_FAILURE);
	}

	poller_config->processing = 0;
	poller_config->config_source_ip = dmanager->source_ip;
	poller_config->config_timeout = dmanager->config_timeout;
	poller_config->progname = dmanager->progname;
	discovery_async_poller_dns_init(poller_config);

	zabbix_log(LOG_LEVEL_DEBUG, "[%d] End of %s()", log_worker_id, __func__);
}

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
			poller_config->base, poller_config->dnsbase, poller_config->config_source_ip,
			ZABBIX_ASYNC_RESOLVE_REVERSE_DNS_YES)))
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

static void	process_agent_result(void *data)
{
	zbx_agent_context		*agent_context = (zbx_agent_context *)data;
	discovery_async_result_t	*async_result = (discovery_async_result_t *)agent_context->arg;
	zbx_dc_item_context_t		*item = &agent_context->item;
	char				**pvalue;

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
			const char	*rdns = ZBX_NULL2EMPTY_STR(agent_context->reverse_dns);

			if ('\0' != *rdns && SUCCEED != zbx_validate_hostname(rdns))
				rdns = "";

			async_result->dresult->dnsname = zbx_strdup(async_result->dresult->dnsname, rdns);
		}
	}

	zbx_free(async_result);
	zbx_async_check_agent_clean(agent_context);
	zbx_free(agent_context);

	zabbix_log(LOG_LEVEL_DEBUG, "[%d] End of %s()", log_worker_id, __func__);
}

static int	discovery_agent(discovery_poller_config_t *poller_config, const zbx_dc_dcheck_t *dcheck,
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
	item.type = ITEM_TYPE_ZABBIX;

	item.host.tls_connect = ZBX_TCP_SEC_UNENCRYPTED;
	item.timeout = dcheck->timeout;

	if (SUCCEED != (ret = zbx_async_check_agent(&item, &result, process_agent_result, async_result, NULL,
			poller_config->base, poller_config->dnsbase, poller_config->config_source_ip,
			ZABBIX_ASYNC_RESOLVE_REVERSE_DNS_YES)))
	{
		if (ZBX_ISSET_MSG(&result))
			*error = zbx_strdup(*error, *ZBX_GET_MSG_RESULT(&result));
		else
			*error = zbx_strdup(*error, "Error of agent check");

		zbx_free(async_result);
	}
	else
		poller_config->processing++;

	zbx_free_agent_result(&result);
	zabbix_log(LOG_LEVEL_DEBUG, "[%d] %s() ip:%s port:%d, key:%s ret:%d", log_worker_id, __func__,
			ip, port, item.key_orig, ret);
	return ret;
}

static void	process_tcpsvc_result(void *data)
{
	zbx_tcpsvc_context_t		*tcpsvc_context = (zbx_tcpsvc_context_t *)data;
	discovery_async_result_t	*async_result = (discovery_async_result_t *)tcpsvc_context->arg;
	zbx_dc_item_context_t		*item = &tcpsvc_context->item;

	zabbix_log(LOG_LEVEL_DEBUG, "[%d] In %s() key:'%s' host:'%s' addr:'%s' ret:%s", log_worker_id, __func__,
			item->key, item->host, item->interface.addr, zbx_result_string(item->ret));

	async_result->poller_config->processing--;
	async_result->dresult->processed_checks_per_ip++;

	if (SUCCEED == item->ret && ZBX_ISSET_UI64(&item->result) && 0 != item->result.ui64)
	{
		zbx_discoverer_dservice_t	*service;

		service = result_dservice_create(item->interface.port, async_result->dcheckid);
		service->status = DOBJECT_STATUS_UP;
		zbx_vector_discoverer_services_ptr_append(&async_result->dresult->services, service);

		if (NULL ==  async_result->dresult->dnsname || '\0' == *async_result->dresult->dnsname)
		{
			const char	*rdns = ZBX_NULL2EMPTY_STR(tcpsvc_context->reverse_dns);

			if ('\0' != *rdns && SUCCEED != zbx_validate_hostname(rdns))
				rdns = "";

			async_result->dresult->dnsname = zbx_strdup(async_result->dresult->dnsname, rdns);
		}
	}

	zbx_free(async_result);
	zbx_async_check_tcpsvc_free(tcpsvc_context);

	zabbix_log(LOG_LEVEL_DEBUG, "[%d] End of %s()", log_worker_id, __func__);
}

static int	discovery_tcpsvc(discovery_poller_config_t *poller_config, const zbx_dc_dcheck_t *dcheck,
		char *ip, const unsigned short port, zbx_discoverer_results_t *dresult, char **error)
{
	int				ret;
	const char			*service = NULL;
	zbx_dc_item_t			item;
	AGENT_RESULT			result;
	discovery_async_result_t	*async_result;

	switch (dcheck->type)
	{
		case SVC_SSH:
			service = "ssh";
			break;
		case SVC_SMTP:
			service = "smtp";
			break;
		case SVC_FTP:
			service = "ftp";
			break;
		case SVC_HTTP:
			service = "http";
			break;
		case SVC_POP:
			service = "pop";
			break;
		case SVC_NNTP:
			service = "nntp";
			break;
		case SVC_IMAP:
			service = "imap";
			break;
		case SVC_TCP:
			service = "tcp";
			break;
		default:
			*error = zbx_dsprintf(*error, "Error of unknown service:%u", dcheck->type);
			return FAIL;
	}

	zbx_init_agent_result(&result);

	async_result = (discovery_async_result_t *) zbx_malloc(NULL, sizeof(discovery_async_result_t));
	async_result->dresult = dresult;
	async_result->poller_config = poller_config;
	async_result->dcheckid = dcheck->dcheckid;

	memset(&item, 0, sizeof(zbx_dc_item_t));
	zbx_snprintf(item.key_orig, sizeof(item.key_orig), "net.tcp.service[%s,%s,%d]", service, ip, (int)port);
	item.key = item.key_orig;

	item.interface.useip = 1;
	zbx_strscpy(item.interface.ip_orig, ip);
	item.interface.addr = item.interface.ip_orig;
	item.interface.port = port;

	item.value_type = ITEM_VALUE_TYPE_UINT64;
	item.type = ITEM_TYPE_SIMPLE;

	item.host.tls_connect = ZBX_TCP_SEC_UNENCRYPTED;
	item.timeout = dcheck->timeout;

	if (SUCCEED != (ret = zbx_async_check_tcpsvc(&item, dcheck->type, &result, process_tcpsvc_result, async_result,
			NULL, poller_config->base, poller_config->dnsbase, poller_config->config_source_ip,
			ZABBIX_ASYNC_RESOLVE_REVERSE_DNS_YES)))
	{
		if (ZBX_ISSET_MSG(&result))
			*error = zbx_strdup(*error, *ZBX_GET_MSG_RESULT(&result));
		else
			*error = zbx_strdup(*error, "Error of net.tcp.service check");

		zbx_free(async_result);
	}
	else
		poller_config->processing++;

	zbx_free_agent_result(&result);
	zabbix_log(LOG_LEVEL_DEBUG, "[%d] %s() ip:%s port:%d, key:%s ret:%d", log_worker_id, __func__,
			ip, port, item.key_orig, ret);
	return ret;
}

static void	process_telnet_result(void *data)
{
	zbx_telnet_context_t		*telnet_context = (zbx_telnet_context_t *)data;
	discovery_async_result_t	*async_result = (discovery_async_result_t *)telnet_context->arg;
	zbx_dc_item_context_t		*item = &telnet_context->item;

	zabbix_log(LOG_LEVEL_DEBUG, "[%d] In %s() key:'%s' host:'%s' addr:'%s' ret:%s", log_worker_id, __func__,
			item->key, item->host, item->interface.addr, zbx_result_string(item->ret));

	async_result->poller_config->processing--;
	async_result->dresult->processed_checks_per_ip++;

	if (SUCCEED == item->ret && ZBX_ISSET_UI64(&item->result) && 0 != item->result.ui64)
	{
		zbx_discoverer_dservice_t	*service;

		service = result_dservice_create(item->interface.port, async_result->dcheckid);
		service->status = DOBJECT_STATUS_UP;
		zbx_vector_discoverer_services_ptr_append(&async_result->dresult->services, service);

		if (NULL ==  async_result->dresult->dnsname || '\0' == *async_result->dresult->dnsname)
		{
			const char	*rdns = ZBX_NULL2EMPTY_STR(telnet_context->reverse_dns);

			if ('\0' != *rdns && SUCCEED != zbx_validate_hostname(rdns))
				rdns = "";

			async_result->dresult->dnsname = zbx_strdup(async_result->dresult->dnsname, rdns);
		}
	}

	zbx_free(async_result);
	zbx_async_check_telnet_free(telnet_context);

	zabbix_log(LOG_LEVEL_DEBUG, "[%d] End of %s()", log_worker_id, __func__);
}

static int	discovery_telnet(discovery_poller_config_t *poller_config, const zbx_dc_dcheck_t *dcheck,
		char *ip, const unsigned short port, zbx_discoverer_results_t *dresult)
{
	zbx_dc_item_t			item;
	discovery_async_result_t	*async_result;

	async_result = (discovery_async_result_t *) zbx_malloc(NULL, sizeof(discovery_async_result_t));
	async_result->dresult = dresult;
	async_result->poller_config = poller_config;
	async_result->dcheckid = dcheck->dcheckid;

	memset(&item, 0, sizeof(zbx_dc_item_t));
	zbx_snprintf(item.key_orig, sizeof(item.key_orig), "net.tcp.service[telnet,%s,%d]", ip, (int)port);
	item.key = item.key_orig;

	item.interface.useip = 1;
	zbx_strscpy(item.interface.ip_orig, ip);
	item.interface.addr = item.interface.ip_orig;
	item.interface.port = port;

	item.value_type = ITEM_VALUE_TYPE_UINT64;
	item.type = ITEM_TYPE_SIMPLE;

	item.host.tls_connect = ZBX_TCP_SEC_UNENCRYPTED;
	item.timeout = dcheck->timeout;

	zbx_async_check_telnet(&item, process_telnet_result, async_result, NULL, poller_config->base,
			poller_config->dnsbase, poller_config->config_source_ip, ZABBIX_ASYNC_RESOLVE_REVERSE_DNS_YES);
	poller_config->processing++;

	zabbix_log(LOG_LEVEL_DEBUG, "[%d] %s() ip:%s port:%d, key:%s", log_worker_id, __func__,
			ip, port, item.key_orig);
	return SUCCEED;
}

static void	discoverer_net_check_result_flush(zbx_discoverer_manager_t *dmanager, zbx_discoverer_task_t *task,
		zbx_vector_discoverer_results_ptr_t *results, int force)
{
	static ZBX_THREAD_LOCAL time_t	last;
	time_t				now = time(NULL);
	int				n = results->values_num;

	if (0 == force && now - last < DISCOVERER_DELAY)
		return;

	pthread_mutex_lock(&dmanager->results_lock);
	discover_results_partrange_merge(&dmanager->results, results, task, force);
	pthread_mutex_unlock(&dmanager->results_lock);

	zabbix_log(LOG_LEVEL_DEBUG, "[%d] %s() results:%d saved:%d", log_worker_id, __func__, results->values_num,
			n - results->values_num);

	last = now;
}

int	discoverer_net_check_range(zbx_uint64_t druleid, zbx_discoverer_task_t *task, int worker_max, int *stop,
		zbx_discoverer_manager_t *dmanager, int worker_id, char **error)
{
	zbx_vector_discoverer_results_ptr_t	results;
	zbx_vector_portrange_t			port_ranges;
	discovery_poller_config_t		poller_config;
	char					ip[ZBX_INTERFACE_IP_LEN_MAX], first_ip[ZBX_INTERFACE_IP_LEN_MAX];
	int					ret = FAIL, z[ZBX_IPRANGE_GROUPS_V6] = {0, 0, 0, 0, 0, 0, 0, 0};

	if (0 == log_worker_id)
		log_worker_id = worker_id;

	zabbix_log(LOG_LEVEL_DEBUG, "[%d] In %s() druleid:" ZBX_FS_UI64 " range id:" ZBX_FS_UI64 " state.count:%d"
			" checks per ip:%u dchecks:%d type:%u worker_max:%d", log_worker_id, __func__, druleid,
			task->range->id, task->range->state.count, task->range->state.checks_per_ip,
			task->dchecks.values_num, task->dchecks.values[task->range->state.dcheck_index]->type,
			worker_max);

	if (0 == worker_max)
		worker_max = DISCOVERER_JOB_TASKS_INPROGRESS_MAX;

	discovery_async_poller_init(dmanager, &poller_config);
	zbx_vector_discoverer_results_ptr_create(&results);
	zbx_vector_portrange_create(&port_ranges);
	*first_ip = '\0';

	if (0 == memcmp(task->range->state.ipaddress, z,
			ZBX_IPRANGE_V4 == task->range->ipranges->values[task->range->state.index_ip].type ?
			ZBX_IPRANGE_GROUPS_V4 : ZBX_IPRANGE_GROUPS_V6))
	{
		task->range->state.index_ip = 0;
		zbx_iprange_first(task->range->ipranges->values, task->range->state.ipaddress);
	}

	do
	{
		zbx_discoverer_results_t	*result;

		(void)zbx_iprange_ip2str(task->range->ipranges->values[task->range->state.index_ip].type,
				task->range->state.ipaddress, ip, sizeof(ip));

		if ('\0' == *first_ip)
			zbx_strlcpy(first_ip, ip, sizeof(first_ip));

		result = discovery_result_create(druleid, task->unique_dcheckid);
		result->ip = zbx_strdup(NULL, ip);
		zbx_vector_discoverer_results_ptr_append(&results, result);

		for (; task->range->state.dcheck_index < task->dchecks.values_num && 0 == *stop &&
				0 != task->range->state.count; task->range->state.dcheck_index++)
		{
			zbx_dc_dcheck_t	*dcheck = task->dchecks.values[task->range->state.dcheck_index];

			dcheck_port_ranges_get(dcheck->ports, &port_ranges);

			if (ZBX_PORTRANGE_INIT_PORT == task->range->state.port)
			{
				task->range->state.index_port = 0;
				task->range->state.port = port_ranges.values->from;
			}

			do
			{
				switch (dcheck->type)
				{
				case SVC_SNMPv1:
				case SVC_SNMPv2c:
				case SVC_SNMPv3:
#ifdef HAVE_NETSNMP
					ret = discovery_snmp(&poller_config, dcheck, ip,
							(unsigned short)task->range->state.port, result, error);
#else
					ret = FAIL;
					*error = zbx_strdup(*error, "Support for SNMP checks was not compiled in.");
#endif
					break;
				case SVC_AGENT:
					ret = discovery_agent(&poller_config, dcheck, ip,
							(unsigned short)task->range->state.port, result, error);
					break;
				case SVC_SSH:
				case SVC_LDAP:
				case SVC_SMTP:
				case SVC_FTP:
				case SVC_HTTP:
				case SVC_POP:
				case SVC_NNTP:
				case SVC_IMAP:
				case SVC_TCP:
				case SVC_HTTPS:
					ret = discovery_tcpsvc(&poller_config, dcheck, ip,
							(unsigned short)task->range->state.port, result, error);
					break;
				case SVC_TELNET:
					ret = discovery_telnet(&poller_config, dcheck, ip,
							(unsigned short)task->range->state.port, result);
					break;
				default:
					ret = FAIL;
					*error = zbx_dsprintf(*error, "Support check type %u was not compiled in.",
							dcheck->type);
				}

				if (FAIL == ret)
					goto out;

				while (worker_max == poller_config.processing)
					event_base_loop(poller_config.base, EVLOOP_ONCE);

				task->range->state.count--;
			}
			while (SUCCEED == zbx_portrange_uniq_iter(port_ranges.values, port_ranges.values_num,
					&task->range->state.index_port, &task->range->state.port) &&
					0 != task->range->state.count && 0 == *stop);

			task->range->state.port = ZBX_PORTRANGE_INIT_PORT;
			zbx_vector_portrange_clear(&port_ranges);

		}

		task->range->state.dcheck_index = 0;
		discoverer_net_check_result_flush(dmanager, task, &results, 0);
	}
	while (SUCCEED == zbx_iprange_uniq_iter(task->range->ipranges->values,
			task->range->ipranges->values_num, &task->range->state.index_ip,
			task->range->state.ipaddress) && 0 != task->range->state.count && 0 == *stop);

out:	/* try to close all handles if they are exhausted */
	while (0 != poller_config.processing)
	{
		event_base_loop(poller_config.base, EVLOOP_ONCE);
		discoverer_net_check_result_flush(dmanager, task, &results, 0);
	}

	discoverer_net_check_result_flush(dmanager, task, &results, 1);
	zbx_vector_discoverer_results_ptr_clear_ext(&results, results_free);	/* Incomplete results*/
	zbx_vector_discoverer_results_ptr_destroy(&results);
	zbx_vector_portrange_destroy(&port_ranges);

	discovery_async_poller_destroy(&poller_config);

	zabbix_log(LOG_LEVEL_DEBUG, "[%d] End of %s() druleid:" ZBX_FS_UI64 " type:%u state.count:%d first ip:%s"
			" last ip:%s", log_worker_id, __func__, druleid,
			task->dchecks.values[task->range->state.dcheck_index]->type,
			task->range->state.count, first_ip, ip);

	return ret;
}
