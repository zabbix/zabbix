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

#include "discoverer_async.h"

#include "discoverer_job.h"
#include "async_tcpsvc.h"
#include "async_telnet.h"

#ifdef HAVE_LIBCURL
#	include "async_http.h"
#endif

#include "zbxsysinfo.h"
#include "zbx_discoverer_constants.h"
#include "zbxasyncpoller.h"
#include "zbxpoller.h"

#ifdef HAVE_LIBCURL
#	include "zbxasynchttppoller.h"
#endif

#include "zbxcacheconfig.h"
#include "zbxcomms.h"
#include "zbxdbhigh.h"
#include "zbxip.h"
#include "zbxstr.h"

#ifndef EVDNS_BASE_INITIALIZE_NAMESERVERS
#	define EVDNS_BASE_INITIALIZE_NAMESERVERS	1
#endif

static ZBX_THREAD_LOCAL int log_worker_id;

static int	discovery_async_poller_dns_init(discovery_poller_config_t *poller_config)
{
	char	*timeout;

	if (NULL == (poller_config->dnsbase = evdns_base_new(poller_config->base, EVDNS_BASE_INITIALIZE_NAMESERVERS)))
	{
		int	ret;

		zabbix_log(LOG_LEVEL_ERR, "cannot initialize asynchronous DNS library with resolv.conf");

		if (NULL == (poller_config->dnsbase = evdns_base_new(poller_config->base, 0)))
		{
			zabbix_log(LOG_LEVEL_ERR, "cannot initialize asynchronous DNS library");
			return FAIL;
		}

		if (0 != (ret = evdns_base_resolv_conf_parse(poller_config->dnsbase, DNS_OPTIONS_ALL,
				ZBX_RES_CONF_FILE)))
		{
			zabbix_log(LOG_LEVEL_ERR, "cannot parse resolv.conf result: %s", zbx_resolv_conf_errstr(ret));
		}
	}

	timeout = zbx_dsprintf(NULL, "%d", poller_config->config_timeout);

	if (0 != evdns_base_set_option(poller_config->dnsbase, "timeout:", timeout))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot set timeout to asynchronous DNS library");
		evdns_base_free(poller_config->dnsbase, 1);
		zbx_free(timeout);
		return FAIL;
	}

	zbx_free(timeout);

	return SUCCEED;
}

static void	discovery_async_poller_destroy(discovery_poller_config_t *poller_config)
{
	zabbix_log(LOG_LEVEL_DEBUG, "[%d] In %s()", log_worker_id, __func__);

	evdns_base_free(poller_config->dnsbase, 1);
	event_base_free(poller_config->base);

	zabbix_log(LOG_LEVEL_DEBUG, "[%d] End of %s()", log_worker_id, __func__);
}

static int	discovery_async_poller_init(zbx_discoverer_manager_t *dmanager,
		discovery_poller_config_t *poller_config)
{
	int	ret;

	zabbix_log(LOG_LEVEL_DEBUG, "[%d] In %s()", log_worker_id, __func__);

	if (NULL == (poller_config->base = event_base_new()))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot initialize event base");
		ret = FAIL;
		goto out;
	}

	poller_config->processing = 0;
	poller_config->config_source_ip = dmanager->source_ip;
	poller_config->config_timeout = dmanager->config_timeout;
	poller_config->progname = dmanager->progname;

	if (FAIL == (ret = discovery_async_poller_dns_init(poller_config)))
		event_base_free(poller_config->base);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "[%d] End of %s()", log_worker_id, __func__, zbx_result_string(ret));

	return ret;
}

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
			poller_config->base, poller_config->dnsbase, poller_config->config_source_ip,
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

#ifdef HAVE_LIBCURL
void	process_http_result(void *data)
{
	zbx_discovery_async_http_context_t	*http_context = (zbx_discovery_async_http_context_t *)data;
	discovery_async_result_t		*async_result = (discovery_async_result_t *)http_context->async_result;

	async_result->poller_config->processing--;
	async_result->dresult->processed_checks_per_ip++;

	if (SUCCEED == http_context->res)
	{
		zbx_discoverer_dservice_t	*service;

		service = result_dservice_create(http_context->port, async_result->dcheckid);
		service->status = DOBJECT_STATUS_UP;
		zbx_vector_discoverer_services_ptr_append(&async_result->dresult->services, service);

		if (NULL ==  async_result->dresult->dnsname || '\0' == *async_result->dresult->dnsname)
		{
			const char	*rdns = ZBX_NULL2EMPTY_STR(http_context->reverse_dns);

			if ('\0' != *rdns && SUCCEED != zbx_validate_hostname(rdns))
				rdns = "";

			async_result->dresult->dnsname = zbx_strdup(async_result->dresult->dnsname, rdns);
		}
	}

	zbx_free(async_result);
	zbx_discovery_async_http_context_destroy(http_context);
}

static int	discovery_http(discovery_poller_config_t *poller_config, zbx_asynchttppoller_config *http_config,
		const zbx_dc_dcheck_t *dcheck, const unsigned short port, zbx_discoverer_results_t *dresult,
		char **error)
{
	int					ret;
	discovery_async_result_t		*async_result;
	zbx_discovery_async_http_context_t	*async_http_context;

	async_result = (discovery_async_result_t *) zbx_malloc(NULL, sizeof(discovery_async_result_t));
	async_result->dresult = dresult;
	async_result->poller_config = poller_config;
	async_result->dcheckid = dcheck->dcheckid;

	async_http_context = (zbx_discovery_async_http_context_t*)zbx_malloc(NULL,
			sizeof(zbx_discovery_async_http_context_t));
	async_http_context->port = port;
	async_http_context->resolve_reverse_dns = ZABBIX_ASYNC_RESOLVE_REVERSE_DNS_YES;
	async_http_context->reverse_dns = NULL;
	async_http_context->step = ZBX_ASYNC_HTTP_STEP_INIT;
	async_http_context->async_result = async_result;
	async_http_context->config_timeout = dcheck->timeout;

	if (SUCCEED != (ret = zbx_discovery_async_check_http(http_config->curl_handle, poller_config->config_source_ip,
			dcheck->timeout, dresult->ip, port, dcheck->type, async_http_context, error)))
	{
		zbx_free(async_result);
	}
	else
		poller_config->processing++;

	return ret;
}
#endif

static int	discovery_net_check_result_flush(zbx_discoverer_manager_t *dmanager, zbx_discoverer_task_t *task,
		zbx_vector_discoverer_results_ptr_t *results, int force)
{
	static ZBX_THREAD_LOCAL time_t	last;
	time_t				now = time(NULL);
	int				ret = SUCCEED, n = results->values_num;

	if (0 == force && now - last < DISCOVERER_DELAY)
		return ret;

	pthread_mutex_lock(&dmanager->results_lock);
	ret = discoverer_results_partrange_merge(&dmanager->results, results, task, force);
	pthread_mutex_unlock(&dmanager->results_lock);

	zabbix_log(LOG_LEVEL_DEBUG, "[%d] %s() results:%d saved:%d ret:%d", log_worker_id, __func__,
			results->values_num, n - results->values_num, ret);

	last = now;
	return ret;
}

int	discovery_pending_checks_count_decrease(zbx_discoverer_queue_t *queue, int concurrency_max,
		zbx_uint64_t total, zbx_uint64_t dec_counter)
{
	if ((0 != total && 0 != total % (zbx_uint64_t)concurrency_max) || total == (zbx_uint64_t)concurrency_max)
		return FAIL;

	if (0 != dec_counter)
	{
		discoverer_queue_lock(queue);
		queue->pending_checks_count -= dec_counter;
		discoverer_queue_unlock(queue);
	}

	return SUCCEED;
}

int	discovery_net_check_range(zbx_uint64_t druleid, zbx_discoverer_task_t *task, int concurrency_max, int *stop,
		zbx_discoverer_manager_t *dmanager, int worker_id, char **error)
{
	zbx_vector_discoverer_results_ptr_t	results;
	discovery_poller_config_t		poller_config;
#if defined(HAVE_LIBCURL)
	zbx_asynchttppoller_config		*http_config = NULL;
#endif
	char					ip[ZBX_INTERFACE_IP_LEN_MAX], first_ip[ZBX_INTERFACE_IP_LEN_MAX];
	int					ret = FAIL, abort = SUCCEED;
	zbx_uint64_t				dec_counter = 0;

	if (0 == log_worker_id)
		log_worker_id = worker_id;

	zabbix_log(LOG_LEVEL_DEBUG, "[%d] In %s() druleid:" ZBX_FS_UI64 " range id:" ZBX_FS_UI64 " state.count:"
			ZBX_FS_UI64 " checks per ip:%u dchecks:%d type:%u concurrency_max:%d checks_per_worker_max:%d",
			log_worker_id, __func__, druleid, task->range.id, task->range.state.count,
			task->range.state.checks_per_ip, task->ds_dchecks.values_num,
			task->ds_dchecks.values[task->range.state.index_dcheck]->dcheck.type, concurrency_max,
			dmanager->queue.checks_per_worker_max);

	if (0 == concurrency_max)
		concurrency_max = dmanager->queue.checks_per_worker_max;

	if (SUCCEED != discovery_async_poller_init(dmanager, &poller_config))
	{
		*error = zbx_strdup(*error, "Cannot initialize discovery async poller.");
		goto poller_fail;
	}

	zbx_vector_discoverer_results_ptr_create(&results);
	*first_ip = '\0';
#ifdef HAVE_LIBCURL
	if ((SVC_HTTP == GET_DTYPE(task) || SVC_HTTPS == GET_DTYPE(task)) &&
			NULL == (http_config = zbx_async_httpagent_create(poller_config.base, process_http_response,
			NULL, &poller_config, error)))
	{
		goto out;
	}
#endif
	do
	{
		zbx_discoverer_results_t	*result;
		zbx_dc_dcheck_t			*dcheck;

		TASK_IP2STR(task, ip);

		if (SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_DEBUG) && '\0' == *first_ip)
			zbx_strlcpy(first_ip, ip, sizeof(first_ip));

		result = discoverer_result_create(druleid, task->unique_dcheckid);
		result->ip = zbx_strdup(NULL, ip);
		zbx_vector_discoverer_results_ptr_append(&results, result);
		dcheck = &task->ds_dchecks.values[task->range.state.index_dcheck]->dcheck;

		switch (dcheck->type)
		{
			case SVC_SNMPv1:
			case SVC_SNMPv2c:
			case SVC_SNMPv3:
#ifdef HAVE_NETSNMP
				ret = discovery_snmp(&poller_config, dcheck, ip,
						(unsigned short)task->range.state.port, result, error);
#else
				ret = FAIL;
				*error = zbx_strdup(*error, "Support for SNMP checks was not compiled in.");
#endif
				break;
			case SVC_AGENT:
				ret = discovery_agent(&poller_config, dcheck, ip,
						(unsigned short)task->range.state.port, result, error);
				break;
			case SVC_HTTPS:
#ifdef HAVE_LIBCURL
			case SVC_HTTP:
				ret = discovery_http(&poller_config, http_config, dcheck,
						(unsigned short)task->range.state.port, result, error);
				break;
#else
				ret = FAIL;
				*error = zbx_strdup(*error, "Support for HTTPS checks was not compiled in.");
				break;
			case SVC_HTTP:
#endif
			case SVC_SSH:
			case SVC_SMTP:
			case SVC_FTP:
			case SVC_POP:
			case SVC_NNTP:
			case SVC_IMAP:
			case SVC_TCP:
				ret = discovery_tcpsvc(&poller_config, dcheck, ip,
						(unsigned short)task->range.state.port, result, error);
				break;
			case SVC_TELNET:
				ret = discovery_telnet(&poller_config, dcheck, ip,
						(unsigned short)task->range.state.port, result);
				break;
			default:
				ret = FAIL;
				*error = zbx_dsprintf(*error, "Unsupported check type %u.", dcheck->type);
		}

		if (FAIL == ret)
			goto out;

		while (concurrency_max == poller_config.processing)
			event_base_loop(poller_config.base, EVLOOP_ONCE);

		abort = discovery_net_check_result_flush(dmanager, task, &results, 0);

		if (SUCCEED == discovery_pending_checks_count_decrease(&dmanager->queue, concurrency_max,
				task->range.state.count, ++dec_counter))
		{
			dec_counter = 0;
		}
	}			/* we have to decrease range.state.count before exit by abort*/
	while (0 == *stop && SUCCEED == discoverer_range_check_iter(task) && SUCCEED == abort);
out:
	while (0 != poller_config.processing)	/* try to close all handles if they are exhausted */
	{
		event_base_loop(poller_config.base, EVLOOP_ONCE);
		discovery_net_check_result_flush(dmanager, task, &results, 0);
	}

	discovery_net_check_result_flush(dmanager, task, &results, 1);
	zbx_vector_discoverer_results_ptr_clear_ext(&results, results_free);	/* Incomplete results*/
	zbx_vector_discoverer_results_ptr_destroy(&results);
#ifdef HAVE_LIBCURL
	if (NULL != http_config)
	{
		zbx_async_httpagent_clean(http_config);
		zbx_free(http_config);
	}
#endif
	discovery_async_poller_destroy(&poller_config);
	(void)discovery_pending_checks_count_decrease(&dmanager->queue, concurrency_max, 0,
			task->range.state.count + dec_counter);
#ifdef HAVE_NETSNMP
	/* we must clear the EnginID cache before the next snmpv3 dcheck and */
	/* remove unused collected values in any case */
	if (SVC_SNMPv3 == GET_DTYPE(task))
		zbx_clear_cache_snmp(dmanager->process_type, FAIL);
#endif
poller_fail:
	zabbix_log(LOG_LEVEL_DEBUG, "[%d] End of %s() druleid:" ZBX_FS_UI64 " type:%u state.count:" ZBX_FS_UI64
			" first ip:%s last ip:%s abort:%d ret:%d", log_worker_id, __func__, druleid,
			task->ds_dchecks.values[task->range.state.index_dcheck]->dcheck.type,
			task->range.state.count, first_ip, ip, abort, ret);

	return ret;
}
