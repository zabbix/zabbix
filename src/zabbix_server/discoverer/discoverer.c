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

#include "zbxlog.h"
#include "zbxcacheconfig.h"
#include "zbxicmpping.h"
#include "zbxdiscovery.h"
#include "zbxexpression.h"
#include "zbxself.h"
#include "zbxrtc.h"
#include "zbxnix.h"
#include "../poller/checks_agent.h"
#include "zbxnum.h"
#include "zbxtime.h"
#include "zbxip.h"
#include "zbxsysinfo.h"
#include "zbx_rtc_constants.h"
#include "discoverer_queue.h"
#include "discoverer_job.h"
#include "discoverer_async.h"
#include "zbxproxybuffer.h"
#include "zbx_discoverer_constants.h"
#include "../poller/checks_snmp.h"

#ifdef HAVE_LDAP
#	include <ldap.h>
#endif

static ZBX_THREAD_LOCAL int	log_worker_id;

typedef struct
{
	zbx_uint64_t	druleid;
	char		ip[ZBX_INTERFACE_IP_LEN_MAX];
	zbx_uint64_t	count;
}
zbx_discoverer_check_count_t;

ZBX_PTR_VECTOR_IMPL(discoverer_services_ptr, zbx_discoverer_dservice_t*)
ZBX_PTR_VECTOR_IMPL(discoverer_results_ptr, zbx_discoverer_results_t*)
ZBX_PTR_VECTOR_IMPL(discoverer_jobs_ptr, zbx_discoverer_job_t*)

extern unsigned char			program_type;

#define ZBX_DISCOVERER_IPRANGE_LIMIT	(1 << 16)
#define ZBX_DISCOVERER_STARTUP_TIMEOUT	30

static zbx_discoverer_manager_t		dmanager;

ZBX_VECTOR_IMPL(portrange, zbx_range_t)

static zbx_hash_t	discoverer_check_count_hash(const void *data)
{
	const zbx_discoverer_check_count_t	*count = (const zbx_discoverer_check_count_t *)data;
	zbx_hash_t				hash;

	hash = ZBX_DEFAULT_UINT64_HASH_FUNC(&count->druleid);
	hash = ZBX_DEFAULT_STRING_HASH_ALGO(count->ip, strlen(count->ip), hash);

	return hash;
}

static int	discoverer_check_count_compare(const void *d1, const void *d2)
{
	const zbx_discoverer_check_count_t	*count1 = (const zbx_discoverer_check_count_t *)d1;
	const zbx_discoverer_check_count_t	*count2 = (const zbx_discoverer_check_count_t *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(count1->druleid, count2->druleid);

	return strcmp(count1->ip, count2->ip);
}

static zbx_hash_t	discoverer_result_hash(const void *data)
{
	const zbx_discoverer_results_t	*result = (const zbx_discoverer_results_t *)data;
	zbx_hash_t			hash;

	hash = ZBX_DEFAULT_UINT64_HASH_FUNC(&result->druleid);
	hash = ZBX_DEFAULT_STRING_HASH_ALGO(result->ip, strlen(result->ip), hash);

	return hash;
}

static int	discoverer_result_compare(const void *d1, const void *d2)
{
	const zbx_discoverer_results_t	*r1 = (const zbx_discoverer_results_t *)d1;
	const zbx_discoverer_results_t	*r2 = (const zbx_discoverer_results_t *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(r1->druleid, r2->druleid);

	return strcmp(r1->ip, r2->ip);
}

static int	discoverer_results_ptr_compare(const void *d1, const void *d2)
{
	const zbx_discoverer_results_t	*r1 = *((const zbx_discoverer_results_t * const *)d1);
	const zbx_discoverer_results_t	*r2 = *((const zbx_discoverer_results_t * const *)d2);

	return discoverer_result_compare(r1, r2);
}

static int	discoverer_check_count_decrease(zbx_hashset_t *check_counts, zbx_uint64_t druleid, const char *ip,
		zbx_uint64_t count, zbx_uint64_t *current_count)
{
	zbx_discoverer_check_count_t	*check_count, cmp;

	cmp.druleid = druleid;
	zbx_strlcpy(cmp.ip, ip, sizeof(cmp.ip));

	if (NULL == (check_count = zbx_hashset_search(check_counts, &cmp)) || 0 == check_count->count)
		return FAIL;

	check_count->count -= count;

	if (NULL != current_count)
		*current_count = check_count->count;

	return SUCCEED;
}

static int	dcheck_get_timeout(unsigned char type, int *timeout_sec)
{
	char	*tmt, error_val[MAX_STRING_LEN];
	int	ret;

	tmt = zbx_dc_get_global_item_type_timeout(type);

	zbx_substitute_simple_macros(NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL,
			NULL, NULL, &tmt, ZBX_MACRO_TYPE_COMMON, NULL, 0);

	ret = zbx_validate_item_timeout(tmt, timeout_sec, error_val, sizeof(error_val));
	zbx_free(tmt);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if service is available                                     *
 *                                                                            *
 * Parameters: dcheck           - [IN] service type                           *
 *             ip               - [IN]                                        *
 *             port             - [IN]                                        *
 *             value            - [OUT]                                       *
 *             value_alloc      - [IN/OUT]                                    *
 *                                                                            *
 * Return value: SUCCEED - service is UP, FAIL - service not discovered       *
 *                                                                            *
 ******************************************************************************/
static int	discover_service(const zbx_dc_dcheck_t *dcheck, char *ip, int port, char **value, size_t *value_alloc)
{
	int		ret = SUCCEED;
	const char	*service = NULL;
	AGENT_RESULT	result;

	zabbix_log(LOG_LEVEL_DEBUG, "[%d] In %s()", log_worker_id, __func__);

	zbx_init_agent_result(&result);

	**value = '\0';

	switch (dcheck->type)
	{
		case SVC_SSH:
			service = "ssh";
			break;
		case SVC_LDAP:
			service = "ldap";
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
		case SVC_HTTPS:
			service = "https";
			break;
		case SVC_TELNET:
			service = "telnet";
			break;
		case SVC_AGENT:
			break;
		default:
			ret = FAIL;
			break;
	}

	if (SUCCEED == ret)
	{
		char		**pvalue;
		size_t		value_offset = 0;
		zbx_dc_item_t	item;
		char		key[MAX_STRING_LEN];

		switch (dcheck->type)
		{
			/* simple checks */
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
			case SVC_TELNET:
				zbx_snprintf(key, sizeof(key), "net.tcp.service[%s,%s,%d]", service, ip, port);

				if (SUCCEED != zbx_execute_agent_check(key, 0, &result, dcheck->timeout) ||
						NULL == ZBX_GET_UI64_RESULT(&result) || 0 == result.ui64)
				{
					ret = FAIL;
				}

				break;
			case SVC_AGENT:
				memset(&item, 0, sizeof(zbx_dc_item_t));

				zbx_strscpy(item.key_orig, dcheck->key_);

				item.interface.useip = 1;
				zbx_strscpy(item.interface.ip_orig, ip);
				item.interface.addr = item.interface.ip_orig;
				item.interface.port = port;

				item.value_type	= ITEM_VALUE_TYPE_STR;
				item.type = ITEM_TYPE_ZABBIX;

				item.key = item.key_orig;
				item.host.tls_connect = ZBX_TCP_SEC_UNENCRYPTED;
				item.timeout = dcheck->timeout;

				if (SUCCEED == get_value_agent(&item, dmanager.source_ip, &result) &&
						NULL != (pvalue = ZBX_GET_TEXT_RESULT(&result)))
				{
					zbx_strcpy_alloc(value, value_alloc, &value_offset, *pvalue);
				}
				else
					ret = FAIL;

				if (FAIL == ret && ZBX_ISSET_MSG(&result))
				{
					zabbix_log(LOG_LEVEL_DEBUG, "discovery: item [%s] error: %s",
							item.key, result.msg);
				}

				break;
			default:
				break;
		}
	}
	zbx_free_agent_result(&result);

	zabbix_log(LOG_LEVEL_DEBUG, "[%d] End of %s() ret:%s", log_worker_id, __func__, zbx_result_string(ret));

	return ret;
}

static void	dcheck_copy(const zbx_dc_dcheck_t *src, zbx_dc_dcheck_t *dst)
{
	dst->dcheckid = src->dcheckid;
	dst->druleid = src->druleid;
	dst->key_ = zbx_strdup(NULL, src->key_);
	dst->ports = zbx_strdup(NULL, src->ports);
	dst->uniq = src->uniq;
	dst->type = src->type;
	dst->allow_redirect = src->allow_redirect;
	dst->timeout = src->timeout;

	if (SVC_SNMPv1 == src->type || SVC_SNMPv2c == src->type || SVC_SNMPv3 == src->type)
	{
		dst->snmp_community = zbx_strdup(NULL, src->snmp_community);
		dst->snmpv3_securityname = zbx_strdup(NULL, src->snmpv3_securityname);
		dst->snmpv3_securitylevel = src->snmpv3_securitylevel;
		dst->snmpv3_authpassphrase = zbx_strdup(NULL, src->snmpv3_authpassphrase);
		dst->snmpv3_privpassphrase = zbx_strdup(NULL, src->snmpv3_privpassphrase);
		dst->snmpv3_authprotocol = src->snmpv3_authprotocol;
		dst->snmpv3_privprotocol = src->snmpv3_privprotocol;
		dst->snmpv3_contextname = zbx_strdup(NULL, src->snmpv3_contextname);
	}
}

static void	service_free(zbx_discoverer_dservice_t *service)
{
	zbx_free(service);
}

static void	results_clear(zbx_discoverer_results_t *result)
{
	zbx_free(result->ip);
	zbx_free(result->dnsname);
	zbx_vector_discoverer_services_ptr_clear_ext(&result->services, service_free);
	zbx_vector_discoverer_services_ptr_destroy(&result->services);
}

void	results_free(zbx_discoverer_results_t *result)
{
	results_clear(result);
	zbx_free(result);
}

void	dcheck_port_ranges_get(const char *ports, zbx_vector_portrange_t *ranges)
{
	const char		*start;

	for (start = ports; '\0' != *start;)
	{
		char		*comma, *last_port;
		zbx_range_t	r;

		if (NULL != (comma = strchr(start, ',')))
			*comma = '\0';

		if (NULL != (last_port = strchr(start, '-')))
		{
			*last_port = '\0';
			r.from = atoi(start);
			r.to = atoi(last_port + 1);
			*last_port = '-';
		}
		else
			r.from = r.to = atoi(start);

		zbx_vector_portrange_append(ranges, r);

		if (NULL != comma)
		{
			*comma = ',';
			start = comma + 1;
		}
		else
			break;
	}
}

static zbx_uint64_t	process_check(const zbx_dc_drule_t *drule, zbx_dc_dcheck_t *dcheck, const char *ip,
		unsigned char *need_resolve, zbx_uint64_t *queue_capacity, zbx_hashset_t *tasks)
{
	int			port = 0;
	zbx_uint64_t		checks_count = 0;
	zbx_vector_portrange_t	port_ranges;

	zbx_vector_portrange_create(&port_ranges);
	dcheck_port_ranges_get(dcheck->ports, &port_ranges);

	while (SUCCEED == zbx_portrange_uniq_next(port_ranges.values, port_ranges.values_num, &port))
	{
		zbx_discoverer_task_t	task_local, *task;

		task_local.addr_type = DISCOVERY_ADDR_IP;
		task_local.addr.ip = (char *)ip;
		task_local.port = (unsigned short)port;

		if (NULL == (task = zbx_hashset_search(tasks, &task_local)))
		{
			task_local.addr.ip = zbx_strdup(NULL, ip);
			task_local.unique_dcheckid = drule->unique_dcheckid;
			task_local.resolve_dns = *need_resolve;

			if (1 == *need_resolve)
				*need_resolve = 0;

			zbx_vector_dc_dcheck_ptr_create(&task_local.dchecks);
			zbx_vector_dc_dcheck_ptr_append(&task_local.dchecks, dcheck);
			zbx_hashset_insert(tasks, &task_local, sizeof(zbx_discoverer_task_t));
		}
		else
		{
			zbx_vector_dc_dcheck_ptr_append(&task->dchecks, dcheck);
		}

		(*queue_capacity)--;
		checks_count++;
	}

	zbx_vector_portrange_destroy(&port_ranges);

	return checks_count;
}

static zbx_dc_dcheck_t	*dcheck_clone_get(zbx_dc_dcheck_t *dcheck, zbx_vector_dc_dcheck_ptr_t *dchecks_common)
{
	zbx_dc_dcheck_t	*dcheck_ptr, dcheck_cmp = {.dcheckid = dcheck->dcheckid};
	int		idx;

	if (FAIL != (idx = zbx_vector_dc_dcheck_ptr_search(dchecks_common, &dcheck_cmp,
							ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
	{
		return dchecks_common->values[idx];
	}

	dcheck_ptr = (zbx_dc_dcheck_t*)zbx_malloc(NULL, sizeof(zbx_dc_dcheck_t));
	dcheck_copy(dcheck, dcheck_ptr);

	if (SVC_SNMPv1 == dcheck_ptr->type || SVC_SNMPv2c == dcheck_ptr->type ||
			SVC_SNMPv3 == dcheck_ptr->type)
	{
		zbx_substitute_simple_macros_unmasked(NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL,
				NULL, NULL, NULL, NULL, &dcheck_ptr->snmp_community,
				ZBX_MACRO_TYPE_COMMON, NULL, 0);
		zbx_substitute_key_macros(&dcheck_ptr->key_, NULL, NULL, NULL, NULL,
				ZBX_MACRO_TYPE_SNMP_OID, NULL, 0);

		if (SVC_SNMPv3 == dcheck_ptr->type)
		{
			zbx_substitute_simple_macros_unmasked(NULL, NULL, NULL, NULL, NULL, NULL,
					NULL, NULL, NULL, NULL, NULL, NULL,
					&dcheck_ptr->snmpv3_securityname, ZBX_MACRO_TYPE_COMMON, NULL,
					0);
			zbx_substitute_simple_macros_unmasked(NULL, NULL, NULL, NULL, NULL, NULL,
					NULL, NULL, NULL, NULL, NULL, NULL,
					&dcheck_ptr->snmpv3_authpassphrase, ZBX_MACRO_TYPE_COMMON, NULL,
					0);
			zbx_substitute_simple_macros_unmasked(NULL, NULL, NULL, NULL, NULL, NULL,
					NULL, NULL, NULL, NULL, NULL, NULL,
					&dcheck_ptr->snmpv3_privpassphrase, ZBX_MACRO_TYPE_COMMON, NULL,
					0);
			zbx_substitute_simple_macros_unmasked(NULL, NULL, NULL, NULL, NULL, NULL,
					NULL, NULL, NULL, NULL, NULL, NULL,
					&dcheck_ptr->snmpv3_contextname, ZBX_MACRO_TYPE_COMMON, NULL,
					0);
		}
	}

	zbx_vector_dc_dcheck_ptr_append(dchecks_common, dcheck_ptr);

	return dcheck_ptr;
}

static int	dcheck_is_async(zbx_dc_dcheck_t *dcheck)
{
	switch(dcheck->type)
	{
	case SVC_ICMPPING:
	case SVC_SNMPv1:
	case SVC_SNMPv2c:
	case SVC_SNMPv3:
		return SUCCEED;
	default:
		return FAIL;
	}
}

static zbx_uint64_t	process_check_range(const zbx_dc_drule_t *drule, zbx_dc_dcheck_t *dcheck,
		zbx_vector_iprange_t *ipranges, unsigned char *need_resolve, zbx_hashset_t *tasks)
{
	zbx_discoverer_task_t	task_local, *task;
	zbx_vector_portrange_t	port_ranges;
	int			port = 0, checks_count = 0;

	if (SVC_ICMPPING != dcheck->type)
	{
		zbx_vector_portrange_create(&port_ranges);
		dcheck_port_ranges_get(dcheck->ports, &port_ranges);

		while (SUCCEED == zbx_portrange_uniq_next(port_ranges.values, port_ranges.values_num, &port))
		{
			checks_count++;
		}

		zbx_vector_portrange_destroy(&port_ranges);
	}
	else
		checks_count = 1;

	task_local.addr_type = DISCOVERY_ADDR_RANGE;
	task_local.port = 0;
	zbx_vector_dc_dcheck_ptr_create(&task_local.dchecks);
	zbx_vector_dc_dcheck_ptr_append(&task_local.dchecks, dcheck);

	if (NULL != (task = zbx_hashset_search(tasks, &task_local)))
	{
		zbx_vector_dc_dcheck_ptr_destroy(&task_local.dchecks);

		if (FAIL == zbx_vector_dc_dcheck_ptr_search(&task->dchecks, dcheck,
				ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC))
		{
			zbx_vector_dc_dcheck_ptr_append(&task->dchecks, dcheck);
			task->addr.range->state.checks_per_ip += checks_count;
		}
	}
	else
	{
		task_local.addr.range = (zbx_task_range_t *)zbx_malloc(NULL, sizeof(zbx_task_range_t));
		memset(task_local.addr.range, 0, sizeof(zbx_task_range_t));
		task_local.addr.range->ipranges = ipranges;
		task_local.addr.range->state.count = DISCOVERER_JOB_TASKS_SKIP_LIMIT;
		task_local.addr.range->state.checks_per_ip += checks_count;
		task_local.unique_dcheckid = drule->unique_dcheckid;
		task_local.resolve_dns = *need_resolve;
		zbx_hashset_insert(tasks, &task_local, sizeof(zbx_discoverer_task_t));
	}

	if (1 == *need_resolve)
		*need_resolve = 0;

	return checks_count;
}

static zbx_uint64_t	process_checks(const zbx_dc_drule_t *drule, char *ip, int unique, unsigned char *need_resolve,
		zbx_uint64_t *queue_capacity, zbx_hashset_t *tasks, zbx_vector_dc_dcheck_ptr_t *dchecks_common,
		zbx_vector_iprange_t *ipranges)
{
	int		i;
	zbx_uint64_t	checks_count = 0;

	for (i = 0; i < drule->dchecks.values_num; i++)
	{
		zbx_dc_dcheck_t	*dcheck_common, *dcheck = (zbx_dc_dcheck_t*)drule->dchecks.values[i];

		if (0 == *queue_capacity)
			break;

		if (0 != drule->unique_dcheckid &&
				((1 == unique && drule->unique_dcheckid != dcheck->dcheckid) ||
				(0 == unique && drule->unique_dcheckid == dcheck->dcheckid)))
		{
			continue;
		}

		dcheck_common = dcheck_clone_get(dcheck, dchecks_common);

		if (SUCCEED == dcheck_is_async(dcheck))
		{
			(*queue_capacity)--;
			checks_count += process_check_range(drule, dcheck_common, ipranges, need_resolve, tasks);
		}
		else
		{
			checks_count += process_check(drule, dcheck_common, ip, need_resolve, queue_capacity, tasks);
		}
	}

	return checks_count;
}

static int	process_services(void *handle, zbx_uint64_t druleid, zbx_db_dhost *dhost, const char *ip,
		const char *dns, time_t now, zbx_uint64_t unique_dcheckid,
		const zbx_vector_discoverer_services_ptr_t *services, zbx_add_event_func_t add_event_cb)
{
	int			host_status = -1, i;
	zbx_vector_uint64_t	dserviceids;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);
	zbx_vector_uint64_create(&dserviceids);

	for (i = 0; i < services->values_num; i++)
	{
		zbx_discoverer_dservice_t	*service = (zbx_discoverer_dservice_t *)services->values[i];

		if ((-1 == host_status || DOBJECT_STATUS_UP == service->status) && host_status != service->status)
			host_status = service->status;

		zbx_discovery_update_service(handle, druleid, service->dcheckid, unique_dcheckid, dhost,
				ip, dns, service->port, service->status, service->value, now, &dserviceids,
				add_event_cb);

	}

	if (0 == services->values_num)
	{
		zbx_discovery_find_host(druleid, ip, dhost);
		host_status = DOBJECT_STATUS_DOWN;
	}

	if (0 != dhost->dhostid)
		zbx_discovery_update_service_down(dhost->dhostid, now, &dserviceids);

	zbx_vector_uint64_destroy(&dserviceids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return host_status;
}

/******************************************************************************
 *                                                                            *
 * Purpose: process single discovery rule                                     *
 *                                                                            *
 ******************************************************************************/
static void	process_rule(zbx_dc_drule_t *drule, zbx_uint64_t *queue_capacity, zbx_hashset_t *tasks,
		zbx_hashset_t *check_counts, zbx_vector_dc_dcheck_ptr_t *dchecks_common, zbx_vector_iprange_t *ipranges)
{
	char		ip[ZBX_INTERFACE_IP_LEN_MAX], *comma, *start = drule->iprange;
	int		i = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() rule:'%s' range:'%s'", __func__, drule->name, drule->iprange);

	for (i = 0; '\0' != start[i]; start[i] == ',' ? i++ : *start++);

	zbx_vector_iprange_reserve(ipranges, i);

	for (start = drule->iprange; '\0' != *start;)
	{
		zbx_iprange_t	ipr;

		if (NULL != (comma = strchr(start, ',')))
			*comma = '\0';

		zabbix_log(LOG_LEVEL_DEBUG, "%s() range:'%s'", __func__, start);

		if (SUCCEED != zbx_iprange_parse(&ipr, start))
		{
			zabbix_log(LOG_LEVEL_WARNING, "discovery rule \"%s\": wrong format of IP range \"%s\"",
					drule->name, start);
			goto next;
		}

		if (ZBX_DISCOVERER_IPRANGE_LIMIT < zbx_iprange_volume(&ipr))
		{
			zabbix_log(LOG_LEVEL_WARNING, "discovery rule \"%s\": IP range \"%s\" exceeds %d address limit",
					drule->name, start, ZBX_DISCOVERER_IPRANGE_LIMIT);
			goto next;
		}
#ifndef HAVE_IPV6
		if (ZBX_IPRANGE_V6 == ipr.addr_type)
		{
			zabbix_log(LOG_LEVEL_WARNING, "discovery rule \"%s\": encountered IP range \"%s\","
					" but IPv6 support not compiled in", drule->name, start);
			goto next;
		}
#endif
		zbx_vector_iprange_append(ipranges, ipr);
next:
		if (NULL != comma)
		{
			*comma = ',';
			start = comma + 1;
		}
		else
			break;
	}

	*ip = '\0';

	while (SUCCEED == zbx_iprange_uniq_next(ipranges->values, ipranges->values_num, ip, sizeof(ip)))
	{
		unsigned char	need_resolve = 1;
		zbx_uint64_t	checks_count = 0;

		zabbix_log(LOG_LEVEL_DEBUG, "%s() ip:'%s'", __func__, ip);

		if (0 != drule->unique_dcheckid)
		{
			checks_count = process_checks(drule, ip, 1, &need_resolve, queue_capacity, tasks,
					dchecks_common, ipranges);
		}

		checks_count += process_checks(drule, ip, 0, &need_resolve, queue_capacity, tasks, dchecks_common,
				ipranges);

		if (0 == *queue_capacity)
			goto out;

		if (0 < checks_count)
		{
			zbx_discoverer_check_count_t	*check_count, cmp;

			cmp.druleid = drule->druleid;
			zbx_strlcpy(cmp.ip, ip, sizeof(cmp.ip));
			cmp.count = 0;

			check_count = zbx_hashset_insert(check_counts, &cmp, sizeof(zbx_discoverer_check_count_t));
			check_count->count += checks_count;
		}
	}
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: clean dservices and dhosts not presenting in drule                *
 *                                                                            *
 ******************************************************************************/
static void	discovery_clean_services(zbx_uint64_t druleid)
{
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	char			*iprange = NULL;
	zbx_vector_uint64_t	keep_dhostids, del_dhostids, del_dserviceids;
	zbx_uint64_t		dhostid, dserviceid;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	result = zbx_db_select("select iprange from drules where druleid=" ZBX_FS_UI64, druleid);

	if (NULL != (row = zbx_db_fetch(result)))
		iprange = zbx_strdup(iprange, row[0]);

	zbx_db_free_result(result);

	if (NULL == iprange)
		goto out;

	zbx_vector_uint64_create(&keep_dhostids);
	zbx_vector_uint64_create(&del_dhostids);
	zbx_vector_uint64_create(&del_dserviceids);

	result = zbx_db_select(
			"select dh.dhostid,ds.dserviceid,ds.ip"
			" from dhosts dh"
				" left join dservices ds"
					" on dh.dhostid=ds.dhostid"
			" where dh.druleid=" ZBX_FS_UI64,
			druleid);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		ZBX_STR2UINT64(dhostid, row[0]);

		if (SUCCEED == zbx_db_is_null(row[1]))
		{
			zbx_vector_uint64_append(&del_dhostids, dhostid);
		}
		else if (SUCCEED != zbx_ip_in_list(iprange, row[2]))
		{
			ZBX_STR2UINT64(dserviceid, row[1]);

			zbx_vector_uint64_append(&del_dhostids, dhostid);
			zbx_vector_uint64_append(&del_dserviceids, dserviceid);
		}
		else
			zbx_vector_uint64_append(&keep_dhostids, dhostid);
	}
	zbx_db_free_result(result);

	zbx_free(iprange);

	if (0 != del_dserviceids.values_num)
	{
		int	i;

		/* remove dservices */

		zbx_vector_uint64_sort(&del_dserviceids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		sql_offset = 0;
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from dservices where");
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "dserviceid",
				del_dserviceids.values, del_dserviceids.values_num);

		zbx_db_execute("%s", sql);

		/* remove dhosts */

		zbx_vector_uint64_sort(&keep_dhostids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_vector_uint64_uniq(&keep_dhostids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		zbx_vector_uint64_sort(&del_dhostids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_vector_uint64_uniq(&del_dhostids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		for (i = 0; i < del_dhostids.values_num; i++)
		{
			dhostid = del_dhostids.values[i];

			if (FAIL != zbx_vector_uint64_bsearch(&keep_dhostids, dhostid, ZBX_DEFAULT_UINT64_COMPARE_FUNC))
				zbx_vector_uint64_remove_noorder(&del_dhostids, i--);
		}
	}

	if (0 != del_dhostids.values_num)
	{
		zbx_vector_uint64_sort(&del_dhostids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		sql_offset = 0;
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from dhosts where");
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "dhostid",
				del_dhostids.values, del_dhostids.values_num);

		zbx_db_execute("%s", sql);
	}

	zbx_free(sql);

	zbx_vector_uint64_destroy(&del_dserviceids);
	zbx_vector_uint64_destroy(&del_dhostids);
	zbx_vector_uint64_destroy(&keep_dhostids);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	process_results_incompletecheckscount_remove(zbx_discoverer_manager_t *manager,
		zbx_vector_uint64_t *del_druleids)
{
	int	i;

	for (i = 0; i < del_druleids->values_num; i++)
	{
		zbx_hashset_iter_t		iter;
		zbx_discoverer_check_count_t	*dcc;

		zbx_hashset_iter_reset(&manager->incomplete_checks_count, &iter);

		while (NULL != (dcc = (zbx_discoverer_check_count_t *)zbx_hashset_iter_next(&iter)))
		{
			if (dcc->druleid == del_druleids->values[i])
				zbx_hashset_iter_remove(&iter);
		}
	}
}

static int	process_results(zbx_discoverer_manager_t *manager, zbx_vector_uint64_t *del_druleids,
		zbx_hashset_t *incomplete_druleids, zbx_uint64_t *unsaved_checks, const zbx_events_funcs_t *events_cbs)
{
#define DISCOVERER_BATCH_RESULTS_NUM	1000
	int					i;
	zbx_uint64_t				res_check_total = 0,res_check_count = 0;
	zbx_vector_discoverer_results_ptr_t	results;
	zbx_discoverer_results_t		*result, *result_tmp;
	zbx_hashset_iter_t			iter;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() del_druleids:%d", __func__, del_druleids->values_num);

	zbx_vector_discoverer_results_ptr_create(&results);
	zbx_hashset_clear(incomplete_druleids);

	pthread_mutex_lock(&manager->results_lock);

	/* protection against returning values from removed revision of druleid */
	process_results_incompletecheckscount_remove(manager, del_druleids);

	zbx_hashset_iter_reset(&manager->results, &iter);

	while (NULL != (result = (zbx_discoverer_results_t *)zbx_hashset_iter_next(&iter)))
	{
		zbx_discoverer_check_count_t	*check_count, cmp;

		cmp.druleid = result->druleid;
		zbx_strlcpy(cmp.ip, result->ip, sizeof(cmp.ip));

		if (FAIL != zbx_vector_uint64_bsearch(del_druleids, cmp.druleid, ZBX_DEFAULT_UINT64_COMPARE_FUNC))
		{
			results_clear(result);
			zbx_hashset_iter_remove(&iter);
			continue;
		}

		res_check_total += (zbx_uint64_t)result->services.values_num;

		if (DISCOVERER_BATCH_RESULTS_NUM <= res_check_count ||
				(NULL != (check_count = zbx_hashset_search(&manager->incomplete_checks_count, &cmp)) &&
				0 != check_count->count))
		{
			zbx_hashset_insert(incomplete_druleids, &cmp.druleid, sizeof(zbx_uint64_t));
			continue;
		}

		res_check_count += (zbx_uint64_t)result->services.values_num;

		if (NULL != check_count)
			zbx_hashset_remove_direct(&manager->incomplete_checks_count, check_count);

		result_tmp = (zbx_discoverer_results_t*)zbx_malloc(NULL, sizeof(zbx_discoverer_results_t));
		memcpy(result_tmp, result, sizeof(zbx_discoverer_results_t));
		zbx_vector_discoverer_results_ptr_append(&results, result_tmp);
		zbx_hashset_iter_remove(&iter);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "%s() results=%d checks:" ZBX_FS_UI64 "/" ZBX_FS_UI64 " del_druleids=%d"
			" incomplete_druleids=%d", __func__, results.values_num, res_check_count, res_check_total,
			del_druleids->values_num, incomplete_druleids->num_data);

	pthread_mutex_unlock(&manager->results_lock);

	if (0 != results.values_num)
	{
		void	*handle;

		handle = zbx_discovery_open();

		for (i = 0; i < results.values_num; i++)
		{
			zbx_db_dhost	dhost;
			int		host_status;

			result = results.values[i];

			if (NULL == result->dnsname)
			{
				zabbix_log(LOG_LEVEL_WARNING,
						"Missing 'dnsname', result skipped (druleid=" ZBX_FS_UI64 ", ip: '%s')",
						result->druleid, result->ip);
				continue;
			}

			memset(&dhost, 0, sizeof(zbx_db_dhost));
			host_status = process_services(handle, result->druleid, &dhost, result->ip,
					result->dnsname,result->now, result->unique_dcheckid, &result->services,
					events_cbs->add_event_cb);

			zbx_discovery_update_host(handle, result->druleid, &dhost, result->ip, result->dnsname,
					host_status, result->now, events_cbs->add_event_cb);

			if (NULL != events_cbs->process_events_cb)
				events_cbs->process_events_cb(NULL, NULL);

			if (NULL != events_cbs->clean_events_cb)
				events_cbs->clean_events_cb();
		}

		zbx_discovery_close(handle);
	}

	*unsaved_checks = res_check_total - res_check_count;

	zbx_vector_discoverer_results_ptr_clear_ext(&results, results_free);
	zbx_vector_discoverer_results_ptr_destroy(&results);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() ret:%d", __func__,
			DISCOVERER_BATCH_RESULTS_NUM <= res_check_count ? 1 : 0);

	return DISCOVERER_BATCH_RESULTS_NUM <= res_check_count ? 1 : 0;
#undef DISCOVERER_BATCH_RESULTS_NUM
}

static int	process_discovery(time_t *nextcheck, zbx_hashset_t *incomplete_druleids,
		zbx_vector_discoverer_jobs_ptr_t *jobs, zbx_hashset_t *check_counts)
{
	int				rule_count = 0, delay, i, k, tmt_simple = 0, tmt_agent = 0, tmt_snmp = 0;
	char				*delay_str = NULL;
	zbx_uint64_t			queue_checks_count = 0;
	zbx_dc_um_handle_t		*um_handle;
	time_t				now;

	zbx_vector_dc_drule_ptr_t	drules;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	now = time(NULL);

	zbx_vector_dc_drule_ptr_create(&drules);
	zbx_dc_drules_get(now, &drules, nextcheck);

	um_handle = zbx_dc_open_user_macros();

	for (k = 0; ZBX_IS_RUNNING() && k < drules.values_num; k++)
	{
		zbx_uint64_t			queue_capacity, queue_capacity_local;
		zbx_hashset_t			tasks, drule_check_counts;
		zbx_hashset_iter_t		iter;
		zbx_discoverer_task_t		*task, *task_out;
		zbx_discoverer_check_count_t	*count;
		zbx_discoverer_job_t		*job, cmp;
		zbx_dc_drule_t			*drule = drules.values[k];
		zbx_vector_dc_dcheck_ptr_t	*dchecks_common;
		zbx_vector_iprange_t		*ipranges;

		now = time(NULL);

		delay_str = zbx_strdup(delay_str, drule->delay_str);
		zbx_substitute_simple_macros(NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL,
				&delay_str, ZBX_MACRO_TYPE_COMMON, NULL, 0);

		if (SUCCEED != zbx_is_time_suffix(delay_str, &delay, ZBX_LENGTH_UNLIMITED))
		{
			zabbix_log(LOG_LEVEL_WARNING, "discovery rule \"%s\": invalid update interval \"%s\"",
					drule->delay_str, delay_str);

			delay = ZBX_DEFAULT_INTERVAL;
			goto next;
		}

		drule->delay = delay;

		cmp.druleid = drule->druleid;
		discoverer_queue_lock(&dmanager.queue);
		i = zbx_vector_discoverer_jobs_ptr_bsearch(&dmanager.job_refs, &cmp,
				ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);
		queue_capacity = DISCOVERER_QUEUE_MAX_SIZE - dmanager.queue.pending_checks_count;
		discoverer_queue_unlock(&dmanager.queue);
		queue_capacity_local = queue_capacity - queue_checks_count;

		if (FAIL != i || NULL != zbx_hashset_search(incomplete_druleids, &drule->druleid))
			goto next;

		for (i = 0; i < drule->dchecks.values_num; i++)
		{
			zbx_dc_dcheck_t	*dcheck = (zbx_dc_dcheck_t*)drule->dchecks.values[i];

			if (SVC_AGENT == dcheck->type)
			{
				if (0 == tmt_agent && FAIL == dcheck_get_timeout(ITEM_TYPE_ZABBIX, &tmt_agent))
				{
					zabbix_log(LOG_LEVEL_WARNING, "discovery rule \"%s\": invalid global timeout "
							"\"%i\" for Zabbix Agent checks", drule->name, tmt_agent);
					goto next;
				}

				dcheck->timeout = tmt_agent;
			}
			else if (SVC_SNMPv1 == dcheck->type || SVC_SNMPv2c == dcheck->type ||
					SVC_SNMPv3 == dcheck->type)
			{
				if (0 == tmt_snmp && FAIL == dcheck_get_timeout(ITEM_TYPE_SNMP, &tmt_snmp))
				{
					zabbix_log(LOG_LEVEL_WARNING, "discovery rule \"%s\": invalid global timeout "
							"\"%i\" for SNMP checks", drule->name, tmt_snmp);
					goto next;
				}

				dcheck->timeout = tmt_snmp;
			}
			else
			{
				if (0 == tmt_simple && FAIL == dcheck_get_timeout(ITEM_TYPE_SIMPLE, &tmt_simple))
				{
					zabbix_log(LOG_LEVEL_WARNING, "discovery rule \"%s\": invalid global timeout "
							"\"%i\" for simple checks", drule->name, tmt_simple);
					goto next;
				}

				dcheck->timeout = tmt_simple;
			}

			if (0 != dcheck->uniq)
			{
				drule->unique_dcheckid = dcheck->dcheckid;
				break;
			}
		}

		zbx_hashset_create(&tasks, 1, discoverer_task_hash, discoverer_task_compare);
		zbx_hashset_create(&drule_check_counts, 1, discoverer_check_count_hash,
				discoverer_check_count_compare);

		dchecks_common = (zbx_vector_dc_dcheck_ptr_t *)zbx_malloc(NULL, sizeof(zbx_vector_dc_dcheck_ptr_t));
		zbx_vector_dc_dcheck_ptr_create(dchecks_common);
		ipranges = (zbx_vector_iprange_t *)zbx_malloc(NULL, sizeof(zbx_vector_iprange_t));
		zbx_vector_iprange_create(ipranges);

		process_rule(drule, &queue_capacity_local, &tasks, &drule_check_counts, dchecks_common, ipranges);
		zbx_hashset_iter_reset(&tasks, &iter);

		if (0 == queue_capacity_local)
		{
			zabbix_log(LOG_LEVEL_WARNING, "discoverer queue is full, skipping discovery rule '%s'",
					drule->name);

			while (NULL != (task = (zbx_discoverer_task_t*)zbx_hashset_iter_next(&iter)))
				discoverer_task_clear(task);

			zbx_hashset_destroy(&tasks);
			zbx_hashset_destroy(&drule_check_counts);

			zbx_vector_dc_dcheck_ptr_clear_ext(dchecks_common, zbx_discovery_dcheck_free);
			zbx_vector_dc_dcheck_ptr_destroy(dchecks_common);
			zbx_free(dchecks_common);

			zbx_vector_iprange_destroy(ipranges);
			zbx_free(ipranges);
			goto next;
		}

		queue_checks_count = queue_capacity - queue_capacity_local;

		job = discoverer_job_create(drule, dchecks_common, ipranges);

		while (NULL != (task = (zbx_discoverer_task_t*)zbx_hashset_iter_next(&iter)))
		{
			task_out = (zbx_discoverer_task_t*)zbx_malloc(NULL, sizeof(zbx_discoverer_task_t));
			memcpy(task_out, task, sizeof(zbx_discoverer_task_t));
			(void)zbx_list_append(&job->tasks, task_out, NULL);
		}

		zbx_hashset_destroy(&tasks);
		zbx_hashset_iter_reset(&drule_check_counts, &iter);

		while (NULL != (count = (zbx_discoverer_check_count_t *)zbx_hashset_iter_next(&iter)))
			zbx_hashset_insert(check_counts, count, sizeof(zbx_discoverer_check_count_t));

		zbx_hashset_destroy(&drule_check_counts);
		zbx_vector_discoverer_jobs_ptr_append(jobs, job);
		rule_count++;
next:
		if (0 != (program_type & ZBX_PROGRAM_TYPE_SERVER))
			discovery_clean_services(drule->druleid);

		zbx_dc_drule_queue(now, drule->druleid, delay);
	}

	zbx_dc_close_user_macros(um_handle);
	zbx_free(delay_str);

	zbx_vector_dc_drule_ptr_clear_ext(&drules, zbx_discovery_drule_free);
	zbx_vector_dc_drule_ptr_destroy(&drules);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() rule_count:%d", __func__, rule_count);

	return rule_count;	/* performance metric */
}

static void	discoverer_job_remove(zbx_discoverer_job_t *job)
{
	int			i;
	zbx_discoverer_job_t	cmp = {.druleid = job->druleid};

	if (FAIL != (i = zbx_vector_discoverer_jobs_ptr_bsearch(&dmanager.job_refs, &cmp,
			ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
	{
		zbx_vector_discoverer_jobs_ptr_remove(&dmanager.job_refs, i);
	}

	discoverer_job_free(job);
}

zbx_discoverer_dservice_t	*result_dservice_create(const unsigned short port,
		const zbx_uint64_t dcheckid)
{
	zbx_discoverer_dservice_t	*service;

	service = (zbx_discoverer_dservice_t *)zbx_malloc(NULL, sizeof(zbx_discoverer_dservice_t));
	service->dcheckid = dcheckid;
	service->port = port;

	return service;
}

zbx_discoverer_results_t	*discovery_result_create(zbx_uint64_t druleid, const zbx_uint64_t unique_dcheckid)
{
	zbx_discoverer_results_t	*result;

	result = (zbx_discoverer_results_t *)zbx_malloc(NULL, sizeof(zbx_discoverer_results_t));

	zbx_vector_discoverer_services_ptr_create(&result->services);

	result->druleid = druleid;
	result->unique_dcheckid = unique_dcheckid;
	result->ip = result->dnsname = NULL;
	result->now = (int)time(NULL);
	result->processed_checks_per_ip = 0;

	return result;
}

ZBX_PTR_VECTOR_DECL(fping_host, ZBX_FPING_HOST)
ZBX_PTR_VECTOR_IMPL(fping_host, ZBX_FPING_HOST)

static void	discovery_icmp_result_proc(const zbx_uint64_t druleid, const int dcheck_idx,
		const zbx_discoverer_task_t *task, const zbx_vector_fping_host_t *hosts,
		zbx_vector_discoverer_results_ptr_t *results)
{
	int			i;
	const zbx_uint64_t	unique_dcheckid = task->unique_dcheckid,
				dcheckid = task->dchecks.values[dcheck_idx]->dcheckid;

	zabbix_log(LOG_LEVEL_DEBUG, "[%d] In %s()", log_worker_id, __func__);

	for (i = 0; i < hosts->values_num; i++)
	{
		ZBX_FPING_HOST			*h = &hosts->values[i];
		zbx_discoverer_dservice_t	*service;
		zbx_discoverer_results_t	result_cmp, *result;
		int				idx;

		if (0 == h->rcv)
			continue;

		result_cmp.ip = h->addr;
		result_cmp.druleid = druleid;

		if (0 == dcheck_idx || FAIL == (idx = zbx_vector_discoverer_results_ptr_bsearch(results,
				&result_cmp, discoverer_results_ptr_compare)))
		{
			result = discovery_result_create(druleid, unique_dcheckid);
			result->ip = h->addr;
			h->addr = NULL;

			if (0 != dcheck_idx)
			{
				idx = zbx_vector_discoverer_results_ptr_nearestindex(results, result,
						discoverer_results_ptr_compare);
				zbx_vector_discoverer_results_ptr_insert(results, result, idx);
			}
			else
				zbx_vector_discoverer_results_ptr_append(results, result);
		}
		else
			result = results->values[idx];

		if (NULL == result->dnsname)
		{
			result->dnsname = h->dnsname;
			h->dnsname = NULL;
		}

		service = result_dservice_create(task->port, dcheckid);
		service->status = DOBJECT_STATUS_UP;
		*service->value = '\0';
		zbx_vector_discoverer_services_ptr_append(&result->services, service);
	}

	if (0 == dcheck_idx)
		zbx_vector_discoverer_results_ptr_sort(results, discoverer_results_ptr_compare);

	zabbix_log(LOG_LEVEL_DEBUG, "[%d] End of %s() results:%d", log_worker_id, __func__, results->values_num);
}

static int	discover_icmp(const zbx_uint64_t druleid, const zbx_discoverer_task_t *task,
		const int dcheck_idx, int worker_max, zbx_vector_discoverer_results_ptr_t *results, char **error)
{
	char				err[ZBX_ITEM_ERROR_LEN_MAX], ip[ZBX_INTERFACE_IP_LEN_MAX];
	int				i, count = 0, ret = SUCCEED;
	zbx_vector_fping_host_t		hosts;
	const zbx_dc_dcheck_t		*dcheck = task->dchecks.values[dcheck_idx];

	zabbix_log(LOG_LEVEL_DEBUG, "[%d] In %s() ranges:%d resolve_dns:%d dchecks:%d key[0]:%s", log_worker_id,
			__func__, DISCOVERY_ADDR_RANGE == task->addr_type ? task->addr.range->ipranges->values_num : -1,
			task->resolve_dns, task->dchecks.values_num, 0 != task->dchecks.values_num ?
			task->dchecks.values[0]->key_ : "empty");

	zbx_vector_fping_host_create(&hosts);

	if (0 == worker_max)
		worker_max = DISCOVERER_JOB_TASKS_INPROGRESS_MAX;

	for (i = 0; i < task->addr.range->ipranges->values_num; i++)
	{
		count += zbx_iprange_volume(&task->addr.range->ipranges->values[i]);
	}

	zbx_vector_fping_host_reserve(&hosts, hosts.values_num + count);
	*ip = '\0';

	while (SUCCEED == zbx_iprange_uniq_next(task->addr.range->ipranges->values,
			task->addr.range->ipranges->values_num, ip, sizeof(ip)))
	{
		ZBX_FPING_HOST	host;

		memset(&host, 0, sizeof(host));
		host.addr = zbx_strdup(NULL, ip);
		zbx_vector_fping_host_append(&hosts, host);

		if (worker_max > hosts.values_num)
			continue;

		if (SUCCEED != (ret = zbx_ping(&hosts.values[0], hosts.values_num, 3, 0, 0, 0, dcheck->allow_redirect,
				1, err, sizeof(err))))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "[%d] %s() %d icmp checks failed with err:%s",
					log_worker_id, __func__, worker_max, err);
			*error = zbx_strdup(*error, err);
			break;
		}
		else
		{
			discovery_icmp_result_proc(druleid, dcheck_idx, task, &hosts, results);
		}

		for (i = 0; i < hosts.values_num; i++)
		{
			zbx_str_free(hosts.values[i].addr);
			zbx_str_free(hosts.values[i].dnsname);
		}

		zbx_vector_fping_host_clear(&hosts);
	}

	if (0 != hosts.values_num && ret == SUCCEED)
	{
		if (SUCCEED != (ret = zbx_ping(&hosts.values[0], hosts.values_num, 3, 0, 0, 0, dcheck->allow_redirect,
				1, err, sizeof(err))))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "[%d] %s() %d icmp checks failed with err:%s", log_worker_id,
					__func__, worker_max, err);
			*error = zbx_strdup(*error, err);
		}
		else
		{
			discovery_icmp_result_proc(druleid, dcheck_idx, task, &hosts, results);
		}

		for (i = 0; i < hosts.values_num; i++)
		{
			zbx_str_free(hosts.values[i].addr);
			zbx_str_free(hosts.values[i].dnsname);
		}

		zbx_vector_fping_host_clear(&hosts);
	}

	zbx_vector_fping_host_destroy(&hosts);

	zabbix_log(LOG_LEVEL_DEBUG, "[%d] End of %s() results:%d", log_worker_id, __func__, results->values_num);

	return ret;
}

static zbx_discoverer_results_t	*discover_results_host_reg(zbx_hashset_t *hr_dst, zbx_uint64_t druleid,
		zbx_uint64_t unique_dcheckid, char *ip)
{
	zbx_discoverer_results_t	*dst, src = {.druleid = druleid, .ip = ip};

	if (NULL == (dst = zbx_hashset_search(hr_dst, &src)))
	{
		dst = zbx_hashset_insert(hr_dst, &src, sizeof(zbx_discoverer_results_t));

		zbx_vector_discoverer_services_ptr_create(&dst->services);
		dst->ip = zbx_strdup(NULL, ip);
		dst->now = (int)time(NULL);
		dst->unique_dcheckid = unique_dcheckid;
		dst->dnsname = zbx_strdup(NULL, "");
	}

	return dst;
}

static void	discover_results_move_value(zbx_discoverer_results_t *src, zbx_hashset_t *hr_dst)
{
	zbx_discoverer_results_t *dst;

	if (NULL == (dst = zbx_hashset_search(hr_dst, src)))
	{
		dst = zbx_hashset_insert(hr_dst, src, sizeof(zbx_discoverer_results_t));
		zbx_vector_discoverer_services_ptr_create(&dst->services);

		src->dnsname = NULL;
		src->ip = NULL;
	}
	else if (NULL == dst->dnsname)
	{
		dst->dnsname = src->dnsname;
		src->dnsname = NULL;
	}

	zbx_vector_discoverer_services_ptr_append_array(&dst->services, src->services.values,
			src->services.values_num);
	zbx_vector_discoverer_services_ptr_clear(&src->services);
	results_free(src);
}

void	discover_results_partrange_merge(zbx_hashset_t *hr_dst, zbx_vector_discoverer_results_ptr_t *vr_src,
		zbx_discoverer_task_t *task, int force)
{
	int		i;
	zbx_uint64_t	druleid = task->dchecks.values[0]->druleid;

	zabbix_log(LOG_LEVEL_DEBUG, "[%d] In %s() src:%d dst:%d", log_worker_id, __func__, vr_src->values_num,
			hr_dst->num_data);

	for (i = vr_src->values_num - 1; i >= 0; i--)
	{
		zbx_discoverer_results_t	*src = vr_src->values[i];
		zbx_uint64_t 			check_count_rest;

		if (0 == force && src->processed_checks_per_ip != task->addr.range->state.checks_per_ip)
			continue;

		if (FAIL == discoverer_check_count_decrease(&dmanager.incomplete_checks_count, druleid,
				src->ip, src->processed_checks_per_ip, &check_count_rest))
		{
			continue;	/* config revision id was changed */
		}

		discover_results_move_value(src, hr_dst);
		zbx_vector_discoverer_results_ptr_remove(vr_src, i);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "[%d] End of %s() src:%d dst:%d", log_worker_id, __func__, vr_src->values_num,
			hr_dst->num_data);
}

static void	discover_results_merge(zbx_hashset_t *hr_dst, zbx_vector_discoverer_results_ptr_t *vr_src,
		zbx_discoverer_task_t *task)
{
	char		ip[ZBX_INTERFACE_IP_LEN_MAX];
	zbx_uint64_t	druleid = task->dchecks.values[0]->druleid;

	zabbix_log(LOG_LEVEL_DEBUG, "[%d] In %s() src:%d dst:%d", log_worker_id, __func__, vr_src->values_num,
			hr_dst->num_data);

	*ip = '\0';

	while (SUCCEED == zbx_iprange_uniq_next(task->addr.range->ipranges->values,
			task->addr.range->ipranges->values_num, ip, sizeof(ip)))
	{
		zbx_discoverer_results_t	cmp;
		int				i;
		zbx_uint64_t 			check_count_rest;

		if (FAIL == discoverer_check_count_decrease(&dmanager.incomplete_checks_count, druleid,
				ip, discoverer_task_ip_check_count_get(task), &check_count_rest))
		{
			continue;	/* config revision id was changed */
		}

		cmp.druleid = druleid;
		cmp.ip = ip;

		if (FAIL == (i = zbx_vector_discoverer_results_ptr_bsearch(vr_src, &cmp,
				discoverer_results_ptr_compare)))
		{
			if (0 == check_count_rest)
				(void)discover_results_host_reg(hr_dst, druleid, task->unique_dcheckid, ip);

			continue;
		}

		discover_results_move_value(vr_src->values[i], hr_dst);
		zbx_vector_discoverer_results_ptr_remove(vr_src, i);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "[%d] End of %s() src:%d dst:%d", log_worker_id, __func__, vr_src->values_num,
			hr_dst->num_data);
}

static int	discoverer_net_check_icmp(zbx_uint64_t druleid, zbx_discoverer_task_t *task, int worker_max,
		char **error)
{
	zbx_vector_discoverer_results_ptr_t	results;
	int					i, ret = SUCCEED;

	if (DISCOVERY_ADDR_RANGE != task->addr_type)
	{
		*error = zbx_strdup(*error, "icmp checks failed with error: address type is out of range");
		return FAIL;
	}

	zbx_vector_discoverer_results_ptr_create(&results);

	for (i = 0; i < task->dchecks.values_num && SUCCEED == ret; i++)
	{
		ret = discover_icmp(druleid, task, i, worker_max, &results, error);
	}

	pthread_mutex_lock(&dmanager.results_lock);
	discover_results_merge(&dmanager.results, &results, task);
	pthread_mutex_unlock(&dmanager.results_lock);

	zbx_vector_discoverer_results_ptr_clear_ext(&results, results_free);
	zbx_vector_discoverer_results_ptr_destroy(&results);

	return ret;
}

static int	discoverer_net_check_common(zbx_uint64_t druleid, zbx_discoverer_task_t *task, char **error)
{
	int					i, ret = SUCCEED;
	char					dns[ZBX_INTERFACE_DNS_LEN_MAX];
	zbx_vector_discoverer_services_ptr_t	services;
	zbx_discoverer_results_t		*result = NULL, result_cmp;
	char					*value = NULL;
	size_t					value_alloc = 128;

	zabbix_log(LOG_LEVEL_DEBUG, "[%d] In %s() ip:%s resolve_dns:%d dchecks:%d key[0]:%s", log_worker_id, __func__,
			DISCOVERY_ADDR_IP == task->addr_type ? task->addr.ip : "ipranges",
			task->resolve_dns, task->dchecks.values_num, 0 != task->dchecks.values_num ?
			task->dchecks.values[0]->key_ : "empty");

	if (DISCOVERY_ADDR_IP != task->addr_type)
	{
		*error = zbx_strdup(*error, "simple check failed with error: address type is out of range");
		return FAIL;
	}

	if (1 == task->resolve_dns)
		zbx_gethost_by_ip(task->addr.ip, dns, sizeof(dns));

	zbx_vector_discoverer_services_ptr_create(&services);

	value = (char *)zbx_malloc(value, value_alloc);

	for (i = 0; i < task->dchecks.values_num; i++)
	{
		zbx_dc_dcheck_t			*dcheck = (zbx_dc_dcheck_t*)task->dchecks.values[i];
		zbx_discoverer_dservice_t	*service;

		if (FAIL == discover_service(dcheck, task->addr.ip, task->port, &value, &value_alloc))
			continue;

		service = result_dservice_create(task->port, dcheck->dcheckid);
		service->status = DOBJECT_STATUS_UP;
		zbx_strlcpy_utf8(service->value, value, ZBX_MAX_DISCOVERED_VALUE_SIZE);
		zbx_vector_discoverer_services_ptr_append(&services, service);
	}

	zbx_free(value);

	result_cmp.druleid = druleid;
	result_cmp.ip = task->addr.ip;

	pthread_mutex_lock(&dmanager.results_lock);

	if (FAIL == discoverer_check_count_decrease(&dmanager.incomplete_checks_count, druleid, task->addr.ip,
			discoverer_task_check_count_get(task), NULL))
	{
		zbx_vector_discoverer_services_ptr_clear_ext(&services, service_free);
		goto out;
	}

	result = discover_results_host_reg(&dmanager.results, druleid, task->unique_dcheckid, task->addr.ip);

	if (1 == task->resolve_dns)
		result->dnsname = zbx_strdup(result->dnsname, dns);

	zbx_vector_discoverer_services_ptr_append_array(&result->services, services.values, services.values_num);
out:
	pthread_mutex_unlock(&dmanager.results_lock);

	zbx_vector_discoverer_services_ptr_destroy(&services);

	zabbix_log(LOG_LEVEL_DEBUG, "[%d] End of %s() ip:%s dresult services:%d", log_worker_id, __func__,
			task->addr.ip, NULL != result ? result->services.values_num : -1);

	return ret;
}

static void	*discoverer_worker_entry(void *net_check_worker)
{
	int				err;
	sigset_t			mask;
	zbx_discoverer_worker_t		*worker = (zbx_discoverer_worker_t*)net_check_worker;
	zbx_discoverer_queue_t		*queue = worker->queue;

	zabbix_log(LOG_LEVEL_INFORMATION, "thread started [%s #%d]",
			get_process_type_string(ZBX_PROCESS_TYPE_DISCOVERER), worker->worker_id);

	log_worker_id = worker->worker_id;
	sigemptyset(&mask);
	sigaddset(&mask, SIGQUIT);
	sigaddset(&mask, SIGALRM);
	sigaddset(&mask, SIGTERM);
	sigaddset(&mask, SIGUSR1);
	sigaddset(&mask, SIGUSR2);
	sigaddset(&mask, SIGHUP);
	sigaddset(&mask, SIGINT);

	if (0 > (err = pthread_sigmask(SIG_BLOCK, &mask, NULL)))
		zabbix_log(LOG_LEVEL_WARNING, "cannot block the signals: %s", zbx_strerror(err));

	zbx_init_icmpping_env(get_process_type_string(ZBX_PROCESS_TYPE_DISCOVERER), worker->worker_id);
	worker->stop = 0;

	discoverer_queue_lock(queue);
	discoverer_queue_register_worker(queue);

	while (0 == worker->stop)
	{
		char			*error = NULL;
		int			ret;
		zbx_discoverer_job_t	*job;

		if (NULL != (job = discoverer_queue_pop(queue)))
		{
			int			worker_max;
			zbx_uint64_t		druleid;
			zbx_discoverer_task_t	*task;

			if (SUCCEED != zbx_list_pop(&job->tasks, (void*)&task))
			{
				if (0 == job->workers_used)
					discoverer_job_remove(job);
				else
					job->status = DISCOVERER_JOB_STATUS_REMOVING;

				continue;
			}

			job->workers_used++;
			queue->pending_checks_count -= discoverer_task_check_count_get(task);

			if (0 == job->workers_max || job->workers_used != job->workers_max)
			{
				discoverer_queue_push(queue, job);
				discoverer_queue_notify(queue);
			}
			else
				job->status = DISCOVERER_JOB_STATUS_WAITING;

			druleid = job->druleid;
			worker_max = job->workers_max;

			discoverer_queue_unlock(queue);

			/* process checks */

			zbx_timekeeper_update(worker->timekeeper, worker->worker_id - 1, ZBX_PROCESS_STATE_BUSY);

			if (DISCOVERY_ADDR_IP == task->addr_type)
			{
				ret = discoverer_net_check_common(druleid, task, &error);
			}
			else if (SVC_ICMPPING == task->dchecks.values[0]->type)
			{
				ret = discoverer_net_check_icmp(druleid, task, worker_max, &error);
			}
			else
			{
				ret = discoverer_net_check_range(druleid, task, worker_max, &worker->stop, &dmanager,
						log_worker_id, &error);
			}

			if (FAIL == ret)
			{
				zabbix_log(LOG_LEVEL_WARNING, "[%d] %s", worker->worker_id, error);
				zbx_free(error);
			}

			discoverer_task_free(task);
			zbx_timekeeper_update(worker->timekeeper, worker->worker_id - 1, ZBX_PROCESS_STATE_IDLE);

			/* proceed to the next job */

			discoverer_queue_lock(queue);
			job->workers_used--;

			if (DISCOVERER_JOB_STATUS_WAITING == job->status)
			{
				job->status = DISCOVERER_JOB_STATUS_QUEUED;
				discoverer_queue_push(queue, job);
			}
			else if (DISCOVERER_JOB_STATUS_REMOVING == job->status && 0 == job->workers_used)
			{
				discoverer_job_remove(job);
			}

			continue;
		}

		if (SUCCEED != discoverer_queue_wait(queue, &error))
		{
			zabbix_log(LOG_LEVEL_WARNING, "[%d] %s", worker->worker_id, error);
			zbx_free(error);
			worker->stop = 1;
		}
	}

	discoverer_queue_deregister_worker(queue);
	discoverer_queue_unlock(queue);

	zabbix_log(LOG_LEVEL_INFORMATION, "thread stopped [%s #%d]",
			get_process_type_string(ZBX_PROCESS_TYPE_DISCOVERER), worker->worker_id);

	return (void*)0;
}

static int	discoverer_worker_init(zbx_discoverer_worker_t *worker, zbx_discoverer_queue_t *queue,
		zbx_timekeeper_t *timekeeper, void *func(void *), char **error)
{
	int	err;

	worker->flags = DISCOVERER_WORKER_INIT_NONE;
	worker->queue = queue;
	worker->timekeeper = timekeeper;
	worker->stop = 1;

	if (0 != (err = pthread_create(&worker->thread, NULL, func, (void *)worker)))
	{
		*error = zbx_dsprintf(NULL, "cannot create thread: %s", zbx_strerror(err));
		return FAIL;
	}

	worker->flags |= DISCOVERER_WORKER_INIT_THREAD;

	return SUCCEED;
}

static void	discoverer_worker_destroy(zbx_discoverer_worker_t *worker)
{
	if (0 != (worker->flags & DISCOVERER_WORKER_INIT_THREAD))
	{
		void	*dummy;

		pthread_join(worker->thread, &dummy);
	}

	worker->flags = DISCOVERER_WORKER_INIT_NONE;
}

static void	discoverer_worker_stop(zbx_discoverer_worker_t *worker)
{
	if (0 != (worker->flags & DISCOVERER_WORKER_INIT_THREAD))
		worker->stop = 1;
}

/******************************************************************************
 *                                                                            *
 * Purpose: initialize libraries, called before creating worker threads       *
 *                                                                            *
 ******************************************************************************/
static void	discoverer_libs_init(void)
{
#ifdef HAVE_NETSNMP
	zbx_init_library_mt_snmp();
#endif
#ifdef HAVE_LIBCURL
	curl_global_init(CURL_GLOBAL_DEFAULT);
#endif
#ifdef HAVE_LDAP
	ldap_get_option(NULL, 0, NULL);
#endif
}

/******************************************************************************
 *                                                                            *
 * Purpose: release libraries resources                                       *
 *                                                                            *
 ******************************************************************************/
static void	discoverer_libs_destroy(void)
{
#ifdef HAVE_NETSNMP
	zbx_shutdown_library_mt_snmp();
#endif
#ifdef HAVE_LIBCURL
	curl_global_cleanup();
#endif
}

static int	discoverer_manager_init(zbx_discoverer_manager_t *manager, zbx_thread_discoverer_args *args_in,
		char **error)
{
	int		i, err, ret = FAIL, started_num = 0;
	time_t		time_start;
	struct timespec	poll_delay = {0, 1e8};

	memset(manager, 0, sizeof(zbx_discoverer_manager_t));
	manager->config_timeout = args_in->config_timeout;
	manager->source_ip = args_in->config_source_ip;

	if (0 != (err = pthread_mutex_init(&manager->results_lock, NULL)))
	{
		*error = zbx_dsprintf(NULL, "cannot initialize results mutex: %s", zbx_strerror(err));
		return FAIL;
	}

	if (SUCCEED != discoverer_queue_init(&manager->queue, error))
	{
		pthread_mutex_destroy(&manager->results_lock);
		return FAIL;
	}

	discoverer_libs_init();

	zbx_hashset_create(&manager->results, 1, discoverer_result_hash, discoverer_result_compare);
	zbx_hashset_create(&manager->incomplete_checks_count, 1, discoverer_check_count_hash,
			discoverer_check_count_compare);

	zbx_vector_discoverer_jobs_ptr_create(&manager->job_refs);

	manager->timekeeper = zbx_timekeeper_create(args_in->workers_num, NULL);

	manager->workers_num = args_in->workers_num;
	manager->workers = (zbx_discoverer_worker_t*)zbx_calloc(NULL, (size_t)args_in->workers_num,
			sizeof(zbx_discoverer_worker_t));

	for (i = 0; i < args_in->workers_num; i++)
	{
		manager->workers[i].worker_id = i + 1;

		if (SUCCEED != discoverer_worker_init(&manager->workers[i], &manager->queue, manager->timekeeper,
				discoverer_worker_entry, error))
		{
			goto out;
		}
	}

	/* wait for threads to start */
	time_start = time(NULL);

	while (started_num != args_in->workers_num)
	{
		if (time_start + ZBX_DISCOVERER_STARTUP_TIMEOUT < time(NULL))
		{
			*error = zbx_strdup(NULL, "timeout occurred while waiting for workers to start");
			goto out;
		}

		discoverer_queue_lock(&manager->queue);
		started_num = manager->queue.workers_num;
		discoverer_queue_unlock(&manager->queue);

		nanosleep(&poll_delay, NULL);
	}

	ret = SUCCEED;
out:
	if (FAIL == ret)
	{
		for (i = 0; i < manager->workers_num; i++)
			discoverer_worker_stop(&manager->workers[i]);

		discoverer_queue_destroy(&manager->queue);

		zbx_hashset_destroy(&manager->results);
		zbx_hashset_destroy(&manager->incomplete_checks_count);
		zbx_vector_discoverer_jobs_ptr_destroy(&manager->job_refs);

		zbx_timekeeper_free(manager->timekeeper);
		discoverer_libs_destroy();
	}

	return ret;
}

static void	discoverer_manager_free(zbx_discoverer_manager_t *manager)
{
	int				i;
	zbx_hashset_iter_t		iter;
	zbx_discoverer_results_t	*result;

	discoverer_queue_lock(&manager->queue);

	for (i = 0; i < manager->workers_num; i++)
		discoverer_worker_stop(&manager->workers[i]);

	discoverer_queue_notify_all(&manager->queue);
	discoverer_queue_unlock(&manager->queue);

	for (i = 0; i < manager->workers_num; i++)
		discoverer_worker_destroy(&manager->workers[i]);

	zbx_free(manager->workers);

	discoverer_queue_destroy(&manager->queue);

	zbx_timekeeper_free(manager->timekeeper);

	zbx_hashset_destroy(&manager->incomplete_checks_count);

	zbx_vector_discoverer_jobs_ptr_clear(&manager->job_refs);
	zbx_vector_discoverer_jobs_ptr_destroy(&manager->job_refs);

	zbx_hashset_iter_reset(&manager->results, &iter);

	while (NULL != (result = (zbx_discoverer_results_t *)zbx_hashset_iter_next(&iter)))
		results_clear(result);

	zbx_hashset_destroy(&manager->results);

	pthread_mutex_destroy(&manager->results_lock);

	discoverer_libs_destroy();
}

/******************************************************************************
 *                                                                            *
 * Purpose: respond to worker usage statistics request                        *
 *                                                                            *
 * Parameters: manager     - [IN] discovery manager                           *
 *             client      - [IN] the request source                          *
 *                                                                            *
 ******************************************************************************/
static void	discoverer_reply_usage_stats(zbx_discoverer_manager_t *manager, zbx_ipc_client_t *client)
{
	zbx_vector_dbl_t	usage;
	unsigned char		*data;
	zbx_uint32_t		data_len;

	zbx_vector_dbl_create(&usage);
	(void)zbx_timekeeper_get_usage(manager->timekeeper, &usage);

	data_len = zbx_discovery_pack_usage_stats(&data, &usage,  manager->workers_num);

	zbx_ipc_client_send(client, ZBX_IPC_DISCOVERER_USAGE_STATS_RESULT, data, data_len);

	zbx_free(data);
	zbx_vector_dbl_destroy(&usage);
}

/******************************************************************************
 *                                                                            *
 * Purpose: periodically try to find new hosts and services                   *
 *                                                                            *
 ******************************************************************************/
ZBX_THREAD_ENTRY(discoverer_thread, args)
{
	zbx_thread_discoverer_args	*discoverer_args_in = (zbx_thread_discoverer_args *)
							(((zbx_thread_args_t *)args)->args);
	double				sec;
	time_t				nextcheck = 0;
	zbx_ipc_service_t		ipc_service;
	zbx_ipc_client_t		*client;
	zbx_ipc_message_t		*message;
	zbx_timespec_t			sleeptime = { .sec = DISCOVERER_DELAY, .ns = 0 };
	const zbx_thread_info_t		*info = &((zbx_thread_args_t *)args)->info;
	int				server_num = ((zbx_thread_args_t *)args)->info.server_num;
	int				process_num = ((zbx_thread_args_t *)args)->info.process_num;
	unsigned char			process_type = ((zbx_thread_args_t *)args)->info.process_type;
	char				*error = NULL;
	zbx_vector_uint64_pair_t	revisions;
	zbx_vector_uint64_t		del_druleids;
	zbx_hashset_t			incomplete_druleids;
	zbx_uint32_t			rtc_msgs[] = {ZBX_RTC_SNMP_CACHE_RELOAD};
	zbx_uint64_t			rev_last = 0;

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(info->program_type),
			server_num, get_process_type_string(process_type), process_num);

	zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_BUSY);
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	zbx_tls_init_child(discoverer_args_in->zbx_config_tls, discoverer_args_in->zbx_get_program_type_cb_arg);
#endif
	zbx_setproctitle("%s #%d [connecting to the database]", get_process_type_string(process_type), process_num);

	zbx_db_connect(ZBX_DB_CONNECT_NORMAL);

	if (FAIL == zbx_ipc_service_start(&ipc_service, ZBX_IPC_SERVICE_DISCOVERER, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot start discoverer service: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	if (FAIL == discoverer_manager_init(&dmanager, discoverer_args_in, &error))
	{
		zabbix_log(LOG_LEVEL_ERR, "Cannot initialize discovery manager: %s", error);
		zbx_free(error);
		zbx_ipc_service_close(&ipc_service);
		exit(EXIT_FAILURE);
	}

	zbx_rtc_subscribe_service(ZBX_PROCESS_TYPE_DISCOVERYMANAGER, 0, rtc_msgs, ARRSIZE(rtc_msgs),
			discoverer_args_in->config_timeout, ZBX_IPC_SERVICE_DISCOVERER);

	zbx_vector_uint64_pair_create(&revisions);
	zbx_vector_uint64_create(&del_druleids);
	zbx_hashset_create(&incomplete_druleids, 1, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_setproctitle("%s #%d [started]", get_process_type_string(process_type), process_num);

	while (ZBX_IS_RUNNING())
	{
		int		processing_rules_num, i, more_results, is_drules_rev_updated;
		zbx_uint64_t	queue_used, unsaved_checks;
		char		msg[255];

		sec = zbx_time();
		zbx_update_env(get_process_type_string(process_type), sec);

		/* update local drules revisions */

		zbx_vector_uint64_clear(&del_druleids);
		zbx_vector_uint64_pair_clear(&revisions);
		is_drules_rev_updated = zbx_dc_drule_revisions_get(&rev_last, &revisions);

		discoverer_queue_lock(&dmanager.queue);

		if (SUCCEED == is_drules_rev_updated)
		{
			for (i = 0; i < dmanager.job_refs.values_num; i++)
			{
				int			k;
				zbx_uint64_pair_t	revision;
				zbx_discoverer_job_t	*job = dmanager.job_refs.values[i];

				revision.first = job->druleid;

				if (FAIL == (k = zbx_vector_uint64_pair_bsearch(&revisions, revision,
						ZBX_DEFAULT_UINT64_COMPARE_FUNC)) ||
						revisions.values[k].second != job->drule_revision)
				{
					zbx_vector_uint64_append(&del_druleids, job->druleid);
					dmanager.queue.pending_checks_count -= discoverer_job_tasks_free(job);
					zabbix_log(LOG_LEVEL_DEBUG, "%s() changed revision of druleid:" ZBX_FS_UI64,
							__func__, job->druleid);
				}
			}

			nextcheck = 0;
		}

		processing_rules_num = dmanager.job_refs.values_num;
		queue_used = dmanager.queue.pending_checks_count;

		discoverer_queue_unlock(&dmanager.queue);

		zbx_vector_uint64_sort(&del_druleids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		more_results = process_results(&dmanager, &del_druleids, &incomplete_druleids, &unsaved_checks,
				discoverer_args_in->events_cbs);

		zbx_snprintf(msg, sizeof(msg), "%s #%d [processing %d rules, " ZBX_FS_DBL "%% of queue used, "
				ZBX_FS_UI64 " unsaved checks]", get_process_type_string(process_type), process_num,
				processing_rules_num, 100 * ((double)queue_used / DISCOVERER_QUEUE_MAX_SIZE),
				unsaved_checks);
		zbx_setproctitle("%s",msg);
		zabbix_log(LOG_LEVEL_DEBUG, msg);

		/* process discovery rules and create net check jobs */

		sec = zbx_time();

		if ((int)sec >= nextcheck)
		{
			int					rule_count;
			zbx_vector_discoverer_jobs_ptr_t	jobs;
			zbx_hashset_t				check_counts;

			zbx_vector_discoverer_jobs_ptr_create(&jobs);
			zbx_hashset_create(&check_counts, 1, discoverer_check_count_hash,
					discoverer_check_count_compare);

			rule_count = process_discovery(&nextcheck, &incomplete_druleids, &jobs, &check_counts);

			if (0 < rule_count)
			{
				zbx_hashset_iter_t		iter;
				zbx_discoverer_check_count_t	*count;
				zbx_uint64_t			queued = 0;

				zbx_hashset_iter_reset(&check_counts, &iter);
				pthread_mutex_lock(&dmanager.results_lock);

				while (NULL != (count = (zbx_discoverer_check_count_t *)zbx_hashset_iter_next(&iter)))
				{
					queued += count->count;
					zbx_hashset_insert(&dmanager.incomplete_checks_count, count,
							sizeof(zbx_discoverer_check_count_t));
				}

				pthread_mutex_unlock(&dmanager.results_lock);
				discoverer_queue_lock(&dmanager.queue);
				dmanager.queue.pending_checks_count += queued;

				for (i = 0; i < jobs.values_num; i++)
				{
					zbx_discoverer_job_t	*job;

					job = jobs.values[i];
					discoverer_queue_push(&dmanager.queue, job);
					zbx_vector_discoverer_jobs_ptr_append(&dmanager.job_refs, job);
				}

				zbx_vector_discoverer_jobs_ptr_sort(&dmanager.job_refs,
						ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

				discoverer_queue_notify_all(&dmanager.queue);
				discoverer_queue_unlock(&dmanager.queue);
			}

			zbx_vector_discoverer_jobs_ptr_destroy(&jobs);
			zbx_hashset_destroy(&check_counts);
		}

		/* update sleeptime */

		sleeptime.sec = 0 != more_results ? 0 : zbx_calculate_sleeptime(nextcheck, DISCOVERER_DELAY);

		if (0 == processing_rules_num && 0 == sleeptime.sec)
			sleeptime.sec = DISCOVERER_DELAY;

		zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_IDLE);
		(void)zbx_ipc_service_recv(&ipc_service, &sleeptime, &client, &message);
		zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_BUSY);

		if (NULL != message)
		{
			zbx_uint64_t	count;

			switch (message->code)
			{
				case ZBX_IPC_DISCOVERER_QUEUE:
					discoverer_queue_lock(&dmanager.queue);
					count = dmanager.queue.pending_checks_count;
					discoverer_queue_unlock(&dmanager.queue);

					zbx_ipc_client_send(client, ZBX_IPC_DISCOVERER_QUEUE, (unsigned char *)&count,
							sizeof(count));
					break;
				case ZBX_IPC_DISCOVERER_USAGE_STATS:
					discoverer_reply_usage_stats(&dmanager, client);
					break;
#ifdef HAVE_NETSNMP
				case ZBX_RTC_SNMP_CACHE_RELOAD:
					zbx_clear_cache_snmp(process_type, process_num);
					break;
#endif
				case ZBX_RTC_SHUTDOWN:
					zabbix_log(LOG_LEVEL_DEBUG, "shutdown message received, terminating...");
					goto out;
			}

			zbx_ipc_message_free(message);
		}

		if (NULL != client)
			zbx_ipc_client_release(client);

		zbx_timekeeper_collect(dmanager.timekeeper);
	}
out:
	zbx_setproctitle("%s #%d [terminating]", get_process_type_string(process_type), process_num);

	zbx_vector_uint64_pair_destroy(&revisions);
	zbx_vector_uint64_destroy(&del_druleids);
	zbx_hashset_destroy(&incomplete_druleids);
	discoverer_manager_free(&dmanager);
	zbx_ipc_service_close(&ipc_service);

	exit(EXIT_SUCCESS);
}

