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

#include "log.h"
#include "zbxicmpping.h"
#include "zbxdiscovery.h"
#include "zbxserver.h"
#include "zbxself.h"
#include "zbxrtc.h"
#include "zbxnix.h"
#include "../poller/checks_agent.h"
#include "../poller/checks_snmp.h"
#include "../events.h"
#include "zbxnum.h"
#include "zbxtime.h"
#include "zbxip.h"
#include "zbxsysinfo.h"
#include "zbx_rtc_constants.h"
#include "zbxtimekeeper.h"
#include "discoverer_queue.h"
#include "discoverer_job.h"

ZBX_PTR_VECTOR_DECL(discoverer_services_ptr, zbx_dservice_t*)

typedef struct
{
	zbx_vector_discoverer_services_ptr_t	services;
	zbx_uint64_t				druleid;
	char					*ip;
	char					*dnsname;
	int					now;
	zbx_uint64_t				unique_dcheckid;
}
zbx_discoverer_results_t;

ZBX_PTR_VECTOR_DECL(discoverer_results_ptr, zbx_discoverer_results_t*)

typedef struct
{
	zbx_uint64_t	druleid;
	char		ip[ZBX_INTERFACE_IP_LEN_MAX];
	zbx_uint64_t	count;
}
zbx_discoverer_check_count_t;

typedef struct
{
	zbx_uint64_t		druleid;
	zbx_discoverer_job_t	*job;
}
zbx_discoverer_job_ref_t;

ZBX_VECTOR_DECL(discoverer_job_refs, zbx_discoverer_job_ref_t)

#define DISCOVERER_WORKER_INIT_NONE	0x00
#define DISCOVERER_WORKER_INIT_THREAD	0x01

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
	zbx_vector_discoverer_job_refs_t	job_refs;
	zbx_discoverer_queue_t			queue;

	zbx_hashset_t				incomplete_checks_count;
	zbx_hashset_t				results;
	pthread_mutex_t				results_lock;

	zbx_timekeeper_t			*timekeeper;
}
zbx_discoverer_manager_t;

ZBX_PTR_VECTOR_DECL(discoverer_jobs_ptr, zbx_discoverer_job_t*)

ZBX_PTR_VECTOR_IMPL(discoverer_services_ptr, zbx_dservice_t*)
ZBX_PTR_VECTOR_IMPL(discoverer_results_ptr, zbx_discoverer_results_t*)
ZBX_PTR_VECTOR_IMPL(discoverer_jobs_ptr, zbx_discoverer_job_t*)
ZBX_VECTOR_IMPL(discoverer_job_refs, zbx_discoverer_job_ref_t)

extern unsigned char			program_type;

#define ZBX_DISCOVERER_IPRANGE_LIMIT	(1 << 16)
#define ZBX_DISCOVERER_STARTUP_TIMEOUT	30

static zbx_discoverer_manager_t 	dmanager;

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

static int	discoverer_results_compare(const void *d1, const void *d2)
{
	const zbx_discoverer_results_t	*r1 = *((const zbx_discoverer_results_t * const *)d1);
	const zbx_discoverer_results_t	*r2 = *((const zbx_discoverer_results_t * const *)d2);

	ZBX_RETURN_IF_NOT_EQUAL(r1->druleid, r2->druleid);

	return strcmp(r1->ip, r2->ip);
}

static void	discoverer_check_count_decrease(zbx_hashset_t *check_counts, zbx_uint64_t druleid, const char *ip,
		zbx_uint64_t count)
{
	zbx_discoverer_check_count_t	*check_count, cmp;

	cmp.druleid = druleid;
	zbx_strlcpy(cmp.ip, ip, sizeof(cmp.ip));

	if (NULL != (check_count = zbx_hashset_search(check_counts, &cmp)))
		check_count->count -= count;
}

/******************************************************************************
 *                                                                            *
 * Purpose: process new service status                                        *
 *                                                                            *
 * Parameters: service - service info                                         *
 *                                                                            *
 ******************************************************************************/
static void	proxy_update_service(zbx_uint64_t druleid, zbx_uint64_t dcheckid, const char *ip,
		const char *dns, int port, int status, const char *value, int now)
{
	char	*ip_esc, *dns_esc, *value_esc;

	ip_esc = zbx_db_dyn_escape_field("proxy_dhistory", "ip", ip);
	dns_esc = zbx_db_dyn_escape_field("proxy_dhistory", "dns", dns);
	value_esc = zbx_db_dyn_escape_field("proxy_dhistory", "value", value);

	zbx_db_execute("insert into proxy_dhistory (clock,druleid,dcheckid,ip,dns,port,value,status)"
			" values (%d," ZBX_FS_UI64 "," ZBX_FS_UI64 ",'%s','%s',%d,'%s',%d)",
			now, druleid, dcheckid, ip_esc, dns_esc, port, value_esc, status);

	zbx_free(value_esc);
	zbx_free(dns_esc);
	zbx_free(ip_esc);
}

/******************************************************************************
 *                                                                            *
 * Purpose: process new service status                                        *
 *                                                                            *
 * Parameters: service - service info                                         *
 *                                                                            *
 ******************************************************************************/
static void	proxy_update_host(zbx_uint64_t druleid, const char *ip, const char *dns, int status, int now)
{
	char	*ip_esc, *dns_esc;

	ip_esc = zbx_db_dyn_escape_field("proxy_dhistory", "ip", ip);
	dns_esc = zbx_db_dyn_escape_field("proxy_dhistory", "dns", dns);

	zbx_db_execute("insert into proxy_dhistory (clock,druleid,ip,dns,status)"
			" values (%d," ZBX_FS_UI64 ",'%s','%s',%d)",
			now, druleid, ip_esc, dns_esc, status);

	zbx_free(dns_esc);
	zbx_free(ip_esc);
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if service is available                                     *
 *                                                                            *
 * Parameters: dcheck         - [IN] service type                             *
 *             ip             - [IN]                                          *
 *             port           - [IN]                                          *
 *             config_timeout - [IN]                                          *
 *             value          - [OUT]                                         *
 *             value_alloc    - [IN/OUT]                                      *
 *                                                                            *
 * Return value: SUCCEED - service is UP, FAIL - service not discovered       *
 *                                                                            *
 ******************************************************************************/
static int	discover_service(const zbx_dc_dcheck_t *dcheck, char *ip, int port, int config_timeout, char **value,
		size_t *value_alloc)
{
	int		ret = SUCCEED;
	const char	*service = NULL;
	AGENT_RESULT	result;

#ifndef HAVE_NETSNMP
	ZBX_UNUSED(config_timeout);
#endif
	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

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
		case SVC_SNMPv1:
		case SVC_SNMPv2c:
		case SVC_SNMPv3:
			break;
		default:
			ret = FAIL;
			break;
	}

	if (SUCCEED == ret)
	{
		char		**pvalue;
		size_t		value_offset = 0;
		DC_ITEM		item;
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

				if (SUCCEED != zbx_execute_agent_check(key, 0, &result) || NULL ==
						ZBX_GET_UI64_RESULT(&result) || 0 == result.ui64)
				{
					ret = FAIL;
				}
				break;
			/* agent and SNMP checks */
			case SVC_AGENT:
			case SVC_SNMPv1:
			case SVC_SNMPv2c:
			case SVC_SNMPv3:
				memset(&item, 0, sizeof(DC_ITEM));

				zbx_strscpy(item.key_orig, dcheck->key_);
				item.key = item.key_orig;

				item.interface.useip = 1;
				item.interface.addr = ip;
				item.interface.port = port;

				item.value_type	= ITEM_VALUE_TYPE_STR;

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
						break;
					default:
						item.type = ITEM_TYPE_ZABBIX;
						break;
				}

				if (SVC_AGENT == dcheck->type)
				{
					item.host.tls_connect = ZBX_TCP_SEC_UNENCRYPTED;

					if (SUCCEED == get_value_agent(&item, &result) &&
							NULL != (pvalue = ZBX_GET_TEXT_RESULT(&result)))
					{
						zbx_strcpy_alloc(value, value_alloc, &value_offset, *pvalue);
					}
					else
						ret = FAIL;
				}
				else
#ifdef HAVE_NETSNMP
				{
					item.snmp_community = dcheck->snmp_community;
					item.snmp_oid = dcheck->key_;

					if (ZBX_IF_SNMP_VERSION_3 == item.snmp_version)
					{
						item.snmpv3_securityname = dcheck->snmpv3_securityname;
						item.snmpv3_securitylevel = dcheck->snmpv3_securitylevel;
						item.snmpv3_authpassphrase = dcheck->snmpv3_authpassphrase;
						item.snmpv3_privpassphrase = dcheck->snmpv3_privpassphrase;
						item.snmpv3_authprotocol = dcheck->snmpv3_authprotocol;
						item.snmpv3_privprotocol = dcheck->snmpv3_privprotocol;
						item.snmpv3_contextname = dcheck->snmpv3_contextname;
					}

					if (SUCCEED == get_value_snmp(&item, &result, ZBX_NO_POLLER, config_timeout) &&
							NULL != (pvalue = ZBX_GET_TEXT_RESULT(&result)))
					{
						zbx_strcpy_alloc(value, value_alloc, &value_offset, *pvalue);
					}
					else
						ret = FAIL;
				}
#else
					ret = FAIL;
#endif	/* HAVE_NETSNMP */

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

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

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

static void	service_free(zbx_dservice_t *service)
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

static void	results_free(zbx_discoverer_results_t *result)
{
	results_clear(result);
	zbx_free(result);
}

static zbx_uint64_t	process_check(const zbx_dc_drule_t *drule, const zbx_dc_dcheck_t *dcheck, char *ip,
		int *need_resolve, zbx_hashset_t *tasks)
{
	const char	*start;
	zbx_uint64_t	checks_count = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	for (start = dcheck->ports; '\0' != *start;)
	{
		char	*comma, *last_port;
		int	port, first, last;

		if (NULL != (comma = strchr(start, ',')))
			*comma = '\0';

		if (NULL != (last_port = strchr(start, '-')))
		{
			*last_port = '\0';
			first = atoi(start);
			last = atoi(last_port + 1);
			*last_port = '-';
		}
		else
			first = last = atoi(start);

		for (port = first; port <= last; port++)
		{
			zbx_discoverer_task_t	task_local, *task;
			zbx_dc_dcheck_t		*dcheck_ptr;

			task_local.ip = zbx_strdup(NULL, SVC_ICMPPING == dcheck->type ? "" : ip);
			task_local.port = (unsigned short)port;

			dcheck_ptr = (zbx_dc_dcheck_t*)zbx_malloc(NULL, sizeof(zbx_dc_dcheck_t));
			dcheck_copy(dcheck, dcheck_ptr);

			if (SVC_SNMPv1 == dcheck_ptr->type || SVC_SNMPv2c == dcheck_ptr->type ||
					SVC_SNMPv3 == dcheck_ptr->type)
			{
				zbx_substitute_simple_macros_unmasked(NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL,
						NULL, NULL, NULL, NULL, &dcheck_ptr->snmp_community, MACRO_TYPE_COMMON,
						NULL, 0);
				zbx_substitute_key_macros(&dcheck_ptr->key_, NULL, NULL, NULL, NULL,
						MACRO_TYPE_SNMP_OID, NULL, 0);

				if (SVC_SNMPv3 == dcheck_ptr->type)
				{
					zbx_substitute_simple_macros_unmasked(NULL, NULL, NULL, NULL, NULL, NULL,
							NULL, NULL, NULL, NULL, NULL, NULL,
							&dcheck_ptr->snmpv3_securityname, MACRO_TYPE_COMMON, NULL, 0);
					zbx_substitute_simple_macros_unmasked(NULL, NULL, NULL, NULL, NULL, NULL,
							NULL, NULL, NULL, NULL, NULL, NULL,
							&dcheck_ptr->snmpv3_authpassphrase, MACRO_TYPE_COMMON, NULL,
							0);
					zbx_substitute_simple_macros_unmasked(NULL, NULL, NULL, NULL, NULL, NULL,
							NULL, NULL, NULL, NULL, NULL, NULL,
							&dcheck_ptr->snmpv3_privpassphrase, MACRO_TYPE_COMMON, NULL, 0);
					zbx_substitute_simple_macros_unmasked(NULL, NULL, NULL, NULL, NULL, NULL,
							NULL, NULL, NULL, NULL, NULL, NULL,
							&dcheck_ptr->snmpv3_contextname, MACRO_TYPE_COMMON, NULL, 0);
				}
			}

			if (NULL != (task = zbx_hashset_search(tasks, &task_local)))
			{
				zbx_free(task_local.ip);

				if (SVC_ICMPPING == dcheck->type)
				{
					zbx_vector_str_append(task->ips, zbx_strdup(NULL, ip));
					zbx_discovery_dcheck_free(dcheck_ptr);
				}
				else
					zbx_vector_dc_dcheck_ptr_append(&task->dchecks, dcheck_ptr);
			}
			else
			{
				task_local.unique_dcheckid = drule->unique_dcheckid;

				if (SVC_ICMPPING == dcheck->type)
				{
					task_local.resolve_dns = 0;
					task_local.ips = (zbx_vector_str_t *)zbx_malloc(NULL, sizeof(zbx_vector_str_t));
					zbx_vector_str_create(task_local.ips);
					zbx_vector_str_append(task_local.ips, zbx_strdup(NULL, ip));
				}
				else
				{
					task_local.ips = NULL;
					task_local.resolve_dns = *need_resolve;

					if (1 == *need_resolve)
						*need_resolve = 0;
				}

				zbx_vector_dc_dcheck_ptr_create(&task_local.dchecks);
				zbx_vector_dc_dcheck_ptr_append(&task_local.dchecks, dcheck_ptr);
				zbx_hashset_insert(tasks, &task_local, sizeof(zbx_discoverer_task_t));
			}

			checks_count++;
		}

		if (NULL != comma)
		{
			*comma = ',';
			start = comma + 1;
		}
		else
			break;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return checks_count;
}

static zbx_uint64_t	process_checks(const zbx_dc_drule_t *drule, char *ip, int unique, int *need_resolve,
		zbx_hashset_t *tasks)
{
	int		i;
	zbx_uint64_t	checks_count = 0;

	for (i = 0; i < drule->dchecks.values_num; i++)
	{
		zbx_dc_dcheck_t	*dcheck = (zbx_dc_dcheck_t*)drule->dchecks.values[i];

		if (0 != drule->unique_dcheckid &&
				((1 == unique && drule->unique_dcheckid != dcheck->dcheckid) ||
				(0 == unique && drule->unique_dcheckid == dcheck->dcheckid)))
		{
			continue;
		}

		checks_count += process_check(drule, dcheck, ip, need_resolve, tasks);
	}

	return checks_count;
}

static int	process_services(zbx_uint64_t druleid, zbx_db_dhost *dhost, const char *ip, const char *dns,
		int now, zbx_uint64_t unique_dcheckid, const zbx_vector_discoverer_services_ptr_t *services)
{
	int	host_status = -1, i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	for (i = 0; i < services->values_num; i++)
	{
		zbx_dservice_t	*service = (zbx_dservice_t *)services->values[i];

		if ((-1 == host_status || DOBJECT_STATUS_UP == service->status) && host_status != service->status)
			host_status = service->status;

		if (0 != (program_type & ZBX_PROGRAM_TYPE_SERVER))
		{
			zbx_discovery_update_service(druleid, service->dcheckid, unique_dcheckid, dhost,
					ip, dns, service->port, service->status, service->value, now);
		}
		else if (0 != (program_type & ZBX_PROGRAM_TYPE_PROXY))
		{
			proxy_update_service(druleid, service->dcheckid, ip, dns, service->port, service->status,
					service->value, now);
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return host_status;
}

/******************************************************************************
 *                                                                            *
 * Purpose: process single discovery rule                                     *
 *                                                                            *
 ******************************************************************************/
static void	process_rule(zbx_dc_drule_t *drule, zbx_hashset_t *tasks, zbx_hashset_t *check_counts)
{
	char		ip[ZBX_INTERFACE_IP_LEN_MAX], *start, *comma;
	int		ipaddress[8];
	zbx_iprange_t	iprange;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() rule:'%s' range:'%s'", __func__, drule->name, drule->iprange);

	for (start = drule->iprange; '\0' != *start;)
	{
		if (NULL != (comma = strchr(start, ',')))
			*comma = '\0';

		zabbix_log(LOG_LEVEL_DEBUG, "%s() range:'%s'", __func__, start);

		if (SUCCEED != zbx_iprange_parse(&iprange, start))
		{
			zabbix_log(LOG_LEVEL_WARNING, "discovery rule \"%s\": wrong format of IP range \"%s\"",
					drule->name, start);
			goto next;
		}

		if (ZBX_DISCOVERER_IPRANGE_LIMIT < zbx_iprange_volume(&iprange))
		{
			zabbix_log(LOG_LEVEL_WARNING, "discovery rule \"%s\": IP range \"%s\" exceeds %d address limit",
					drule->name, start, ZBX_DISCOVERER_IPRANGE_LIMIT);
			goto next;
		}
#ifndef HAVE_IPV6
		if (ZBX_IPRANGE_V6 == iprange.type)
		{
			zabbix_log(LOG_LEVEL_WARNING, "discovery rule \"%s\": encountered IP range \"%s\","
					" but IPv6 support not compiled in", drule->name, start);
			goto next;
		}
#endif
		zbx_iprange_first(&iprange, ipaddress);

		do
		{
			int		need_resolve = 1;
			zbx_uint64_t	checks_count = 0;
#ifdef HAVE_IPV6
			if (ZBX_IPRANGE_V6 == iprange.type)
			{
				zbx_snprintf(ip, sizeof(ip), "%x:%x:%x:%x:%x:%x:%x:%x", (unsigned int)ipaddress[0],
						(unsigned int)ipaddress[1], (unsigned int)ipaddress[2],
						(unsigned int)ipaddress[3], (unsigned int)ipaddress[4],
						(unsigned int)ipaddress[5], (unsigned int)ipaddress[6],
						(unsigned int)ipaddress[7]);
			}
			else
			{
#endif
				zbx_snprintf(ip, sizeof(ip), "%u.%u.%u.%u", (unsigned int)ipaddress[0],
						(unsigned int)ipaddress[1], (unsigned int)ipaddress[2],
						(unsigned int)ipaddress[3]);
#ifdef HAVE_IPV6
			}
#endif
			zabbix_log(LOG_LEVEL_DEBUG, "%s() ip:'%s'", __func__, ip);

			if (0 != drule->unique_dcheckid)
				checks_count = process_checks(drule, ip, 1, &need_resolve, tasks);

			checks_count += process_checks(drule, ip, 0, &need_resolve, tasks);

			if (0 < checks_count)
			{
				zbx_discoverer_check_count_t	*check_count, cmp;

				cmp.druleid = drule->druleid;
				zbx_strlcpy(cmp.ip, ip, sizeof(cmp.ip));
				cmp.count = 0;

				check_count = zbx_hashset_insert(check_counts, &cmp,
						sizeof(zbx_discoverer_check_count_t));
				check_count->count += checks_count;
			}
		}
		while (SUCCEED == zbx_iprange_next(&iprange, ipaddress));
next:
		if (NULL != comma)
		{
			*comma = ',';
			start = comma + 1;
		}
		else
			break;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: clean dservices and dhosts not presenting in drule                *
 *                                                                            *
 ******************************************************************************/
static void	discovery_clean_services(zbx_uint64_t druleid)
{
	DB_RESULT		result;
	DB_ROW			row;
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

static int	process_results(zbx_discoverer_manager_t *manager)
{
	int					i, result_count = 0;
	zbx_vector_discoverer_results_ptr_t	results;
	zbx_discoverer_results_t		*result, *result_tmp;
	zbx_hashset_iter_t			iter;

	zbx_vector_discoverer_results_ptr_create(&results);

	pthread_mutex_lock(&manager->results_lock);
	zbx_hashset_iter_reset(&manager->results, &iter);

	while (NULL != (result = (zbx_discoverer_results_t *)zbx_hashset_iter_next(&iter)))
	{
		zbx_discoverer_check_count_t	*check_count, cmp;

		cmp.druleid = result->druleid;
		zbx_strlcpy(cmp.ip, result->ip, sizeof(cmp.ip));

		if (NULL != (check_count = zbx_hashset_search(&manager->incomplete_checks_count, &cmp)))
		{
			if (0 == check_count->count)
			{
				zbx_hashset_remove_direct(&manager->incomplete_checks_count, check_count);
			}
			else
				continue;
		}

		result_tmp = (zbx_discoverer_results_t*)zbx_malloc(NULL, sizeof(zbx_discoverer_results_t));
		memcpy(result_tmp, result, sizeof(zbx_discoverer_results_t));
		zbx_vector_discoverer_results_ptr_append(&results, result_tmp);
		zbx_hashset_iter_remove(&iter);
	}

	pthread_mutex_unlock(&manager->results_lock);

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

		host_status = process_services(result->druleid, &dhost, result->ip, result->dnsname, result->now,
				result->unique_dcheckid, &result->services);

		if (0 != (program_type & ZBX_PROGRAM_TYPE_SERVER))
		{
			zbx_discovery_update_host(&dhost, host_status, result->now);
			zbx_process_events(NULL, NULL);
			zbx_clean_events();
		}
		else if (0 != (program_type & ZBX_PROGRAM_TYPE_PROXY))
		{
			proxy_update_host(result->druleid, result->ip, result->dnsname, host_status,
					result->now);
		}

		result_count += result->services.values_num;
	}

	zbx_vector_discoverer_results_ptr_clear_ext(&results, results_free);
	zbx_vector_discoverer_results_ptr_destroy(&results);

	return result_count;
}

static int	process_discovery(time_t *nextcheck, int config_timeout, zbx_vector_discoverer_jobs_ptr_t *jobs,
		zbx_hashset_t *check_counts)
{
	int			rule_count = 0, delay, i;
	char			*delay_str = NULL;
	zbx_dc_um_handle_t	*um_handle;
	time_t			now;
	zbx_dc_drule_t		*drule;

	now = time(NULL);

	um_handle = zbx_dc_open_user_macros();

	while (ZBX_IS_RUNNING() && NULL != (drule = zbx_dc_drule_next(now, nextcheck)))
	{
		zbx_discoverer_job_ref_t	cmp = {.druleid = drule->druleid};

		discoverer_queue_lock(&dmanager.queue);
		i = zbx_vector_discoverer_job_refs_bsearch(&dmanager.job_refs, cmp, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		discoverer_queue_unlock(&dmanager.queue);

		if (FAIL != i)
		{
			zbx_dc_drule_queue(now, drule->druleid, drule->delay);
			zbx_discovery_drule_free(drule);
			continue;
		}

		rule_count++;

		for (i = 0; i < drule->dchecks.values_num; i++)
		{
			zbx_dc_dcheck_t	*dcheck = (zbx_dc_dcheck_t*)drule->dchecks.values[i];

			if (0 != dcheck->uniq)
			{
				drule->unique_dcheckid = dcheck->dcheckid;
				break;
			}
		}

		delay_str = zbx_strdup(delay_str, drule->delay_str);
		zbx_substitute_simple_macros(NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL,
				&delay_str, MACRO_TYPE_COMMON, NULL, 0);

		if (SUCCEED != zbx_is_time_suffix(delay_str, &delay, ZBX_LENGTH_UNLIMITED))
		{
			zabbix_log(LOG_LEVEL_WARNING, "discovery rule \"%s\": invalid update interval \"%s\"",
					drule->delay_str, delay_str);

			delay = ZBX_DEFAULT_INTERVAL;
		}
		else
		{
			zbx_discoverer_job_t	*job;
			zbx_discoverer_task_t	*task, *task_out;
			zbx_hashset_t		tasks;
			zbx_hashset_iter_t	iter;

			drule->delay = delay;

			zbx_hashset_create(&tasks, 1, discoverer_task_hash, discoverer_task_compare);

			process_rule(drule, &tasks, check_counts);

			job = discoverer_job_create(drule, config_timeout);

			zbx_hashset_iter_reset(&tasks, &iter);

			while (NULL != (task = (zbx_discoverer_task_t*)zbx_hashset_iter_next(&iter)))
			{
				task_out = (zbx_discoverer_task_t*)zbx_malloc(NULL,
						sizeof(zbx_discoverer_task_t));
				memcpy(task_out, task, sizeof(zbx_discoverer_task_t));
				zbx_list_append(&job->tasks, task_out, NULL);
			}

			zbx_hashset_destroy(&tasks);
			zbx_vector_discoverer_jobs_ptr_append(jobs, job);
		}

		zbx_dc_drule_queue(now, drule->druleid, delay);
		zbx_free(delay_str);

		if (0 != (program_type & ZBX_PROGRAM_TYPE_SERVER))
			discovery_clean_services(drule->druleid);

		zbx_discovery_drule_free(drule);

		now = time(NULL);
	}

	zbx_dc_close_user_macros(um_handle);

	return rule_count;	/* performance metric */
}

static void	discoverer_job_remove(zbx_discoverer_job_t *job)
{
	int				i;
	zbx_discoverer_job_ref_t	cmp = {.druleid = job->druleid};

	if (FAIL != (i = zbx_vector_discoverer_job_refs_bsearch(&dmanager.job_refs, cmp,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
	{
		zbx_vector_discoverer_job_refs_remove(&dmanager.job_refs, i);
	}

	discoverer_job_free(job);
}

static zbx_dservice_t	*result_dservice_create(const zbx_discoverer_task_t *task, const zbx_dc_dcheck_t *dcheck)
{
	zbx_dservice_t	*service;

	service = (zbx_dservice_t *)zbx_malloc(NULL, sizeof(zbx_dservice_t));
	service->dcheckid = dcheck->dcheckid;
	service->itemtime = time(NULL);
	service->port = task->port;

	return service;
}

static zbx_discoverer_results_t	*rdiscovery_result_create(zbx_uint64_t druleid,
		const zbx_discoverer_task_t *task)
{
	zbx_discoverer_results_t	*result;

	result = (zbx_discoverer_results_t *)zbx_malloc(NULL, sizeof(zbx_discoverer_results_t));

	zbx_vector_discoverer_services_ptr_create(&result->services);

	result->druleid = druleid;
	result->unique_dcheckid = task->unique_dcheckid;
	result->ip = result->dnsname = NULL;
	result->now = (int)time(NULL);

	return result;
}

ZBX_PTR_VECTOR_DECL(fping_host, ZBX_FPING_HOST)
ZBX_PTR_VECTOR_IMPL(fping_host, ZBX_FPING_HOST)

static void	discover_icmp(zbx_uint64_t druleid, const zbx_discoverer_task_t *task,
		const zbx_dc_dcheck_t *dcheck, zbx_vector_discoverer_results_ptr_t *results, int worker_max)
{
	zbx_vector_fping_host_t	hosts;
	char			error[ZBX_ITEM_ERROR_LEN_MAX];
	int			i;

	zbx_vector_fping_host_create(&hosts);

	for (i = 0; i < task->ips->values_num; i++)
	{
		ZBX_FPING_HOST			host;
		zbx_discoverer_results_t	*result;
		zbx_dservice_t			*service;

		memset(&host, 0, sizeof(host));
		host.addr = task->ips->values[i];
		zbx_vector_fping_host_append(&hosts, host);

		service = result_dservice_create(task, dcheck);
		service->status = DOBJECT_STATUS_DOWN;
		*service->value = '\0';
		result = rdiscovery_result_create(druleid, task);
		result->ip = zbx_strdup(result->ip, task->ips->values[i]);
		zbx_vector_discoverer_services_ptr_append(&result->services, service);
		zbx_vector_discoverer_results_ptr_append(results, result);
	}

	if (0 == worker_max)
		worker_max = hosts.values_num;

	for (i = 0; i < hosts.values_num; i += worker_max)
	{
		if (hosts.values_num - i < worker_max)
			worker_max = hosts.values_num - i;

		if (SUCCEED != zbx_ping(&hosts.values[i], worker_max, 3, 0, 0, 0, 1, error, sizeof(error)))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s() %d icmp checks failed with error:%s", __func__,
					worker_max, error);
		}
	}

	zbx_vector_discoverer_results_ptr_sort(results, discoverer_results_compare);

	for (i = 0; i < hosts.values_num; i++)
	{
		int				index;
		zbx_discoverer_results_t	result_cmp, *result;
		ZBX_FPING_HOST			*h = &hosts.values[i];

		result_cmp.ip = h->addr;
		result_cmp.druleid = druleid;

		if (FAIL == (index = zbx_vector_discoverer_results_ptr_bsearch(results, &result_cmp, discoverer_results_compare)))
		{
			zbx_str_free(h->dnsname);
			continue;
		}

		result = results->values[index];
		result->dnsname = h->dnsname;
		h->dnsname = NULL;

		if (0 != h->rcv)
		{
			((zbx_dservice_t*)result->services.values[0])->status = DOBJECT_STATUS_UP;
		}
	}

	zbx_vector_fping_host_clear(&hosts);
	zbx_vector_fping_host_destroy(&hosts);
}

static void	discover_results_merge(zbx_hashset_t *hr_dst, zbx_vector_discoverer_results_ptr_t *vr_src)
{
	int	i;

	for (i = 0; i < vr_src->values_num; i++)
	{
		zbx_discoverer_results_t	*dst, *src = vr_src->values[i];

		discoverer_check_count_decrease(&dmanager.incomplete_checks_count, src->druleid, src->ip, 1);

		/* result is incomplete without dnsname so skip it */
		if (NULL == src->dnsname)
			continue;

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
	}
}

static void	discoverer_net_check_icmp(zbx_uint64_t druleid, zbx_discoverer_task_t *task, int worker_max)
{
	zbx_dc_dcheck_t				*dcheck;
	zbx_vector_discoverer_results_ptr_t	results;

	if (0 == task->dchecks.values_num)
		return;

	dcheck = (zbx_dc_dcheck_t*)task->dchecks.values[0];

	zbx_vector_discoverer_results_ptr_create(&results);
	discover_icmp(druleid, task, dcheck, &results, worker_max);

	pthread_mutex_lock(&dmanager.results_lock);
	discover_results_merge(&dmanager.results, &results);
	pthread_mutex_unlock(&dmanager.results_lock);

	zbx_vector_discoverer_results_ptr_clear_ext(&results, results_free);
	zbx_vector_discoverer_results_ptr_destroy(&results);
}

static void	discoverer_net_check_common(zbx_uint64_t druleid, zbx_discoverer_task_t *task,
		int config_timeout)
{
	int					i;
	char					dns[ZBX_INTERFACE_DNS_LEN_MAX];
	zbx_vector_discoverer_services_ptr_t	services;
	zbx_discoverer_results_t		*result = NULL, result_cmp;
	char					*value = NULL;
	size_t					value_alloc = 128;

	if (1 == task->resolve_dns)
		zbx_gethost_by_ip(task->ip, dns, sizeof(dns));

	zbx_vector_discoverer_services_ptr_create(&services);

	value = (char *)zbx_malloc(value, value_alloc);

	for (i = 0; i < task->dchecks.values_num; i++)
	{
		zbx_dc_dcheck_t		*dcheck = (zbx_dc_dcheck_t*)task->dchecks.values[i];
		zbx_dservice_t		*service;

		service = result_dservice_create(task, dcheck);
		service->status = (SUCCEED == discover_service(dcheck, task->ip, task->port, config_timeout, &value,
				&value_alloc)) ? DOBJECT_STATUS_UP : DOBJECT_STATUS_DOWN;
		zbx_strlcpy_utf8(service->value, value, ZBX_MAX_DISCOVERED_VALUE_SIZE);

		zbx_vector_discoverer_services_ptr_append(&services, service);
	}

	zbx_free(value);

	result_cmp.druleid = druleid;
	result_cmp.ip = task->ip;

	pthread_mutex_lock(&dmanager.results_lock);

	if (NULL == (result = zbx_hashset_search(&dmanager.results, &result_cmp)))
	{
		zbx_discoverer_results_t	*r;

		r = rdiscovery_result_create(druleid, task);
		r->ip = zbx_strdup(NULL, task->ip);

		result = zbx_hashset_insert(&dmanager.results, r, sizeof(zbx_discoverer_results_t));
		zbx_free(r);
	}

	if (1 == task->resolve_dns)
		result->dnsname = zbx_strdup(result->dnsname, dns);

	zbx_vector_discoverer_services_ptr_append_array(&result->services, services.values, services.values_num);
	discoverer_check_count_decrease(&dmanager.incomplete_checks_count, druleid, task->ip,
			(zbx_uint64_t)task->dchecks.values_num);
	pthread_mutex_unlock(&dmanager.results_lock);

	zbx_vector_discoverer_services_ptr_destroy(&services);
}

static void	*discoverer_worker_entry(void *net_check_worker)
{
	int				err;
	sigset_t			mask;
	zbx_discoverer_worker_t		*worker = (zbx_discoverer_worker_t*)net_check_worker;
	zbx_discoverer_queue_t		*queue = worker->queue;

	zabbix_log(LOG_LEVEL_INFORMATION, "thread started [%s #%d]",
			get_process_type_string(ZBX_PROCESS_TYPE_DISCOVERER), worker->worker_id);

	sigemptyset(&mask);
	sigaddset(&mask, SIGQUIT);
	sigaddset(&mask, SIGALRM);
	sigaddset(&mask, SIGTERM);
	sigaddset(&mask, SIGUSR2);
	sigaddset(&mask, SIGHUP);

	if (0 > (err = zbx_sigmask(SIG_BLOCK, &mask, NULL)))
		zabbix_log(LOG_LEVEL_WARNING, "cannot block the signals: %s", zbx_strerror(err));

	zbx_init_icmpping_env("discoverer", worker->worker_id);
	worker->stop = 0;

	discoverer_queue_lock(queue);
	discoverer_queue_register_worker(queue);

	while (0 == worker->stop)
	{
		char			*error;
		zbx_discoverer_job_t	*job;

		if (NULL != (job = discoverer_queue_pop(queue)))
		{
			int			config_timeout, worker_max;
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
			queue->pending_checks_count -= (zbx_uint64_t)
					(NULL != task->ips ? task->ips->values_num : task->dchecks.values_num);

			if (0 == job->workers_max || job->workers_used != job->workers_max)
			{
				discoverer_queue_push(queue, job);
				discoverer_queue_notify(queue);
			}
			else
				job->status = DISCOVERER_JOB_STATUS_WAITING;

			config_timeout = job->config_timeout;
			druleid = job->druleid;
			worker_max = job->workers_max;

			discoverer_queue_unlock(queue);

			/* process checks */

			zbx_timekeeper_update(worker->timekeeper, worker->worker_id - 1, ZBX_PROCESS_STATE_BUSY);

			if (NULL == task->ips)
				discoverer_net_check_common(druleid, task, config_timeout);
			else
				discoverer_net_check_icmp(druleid, task, worker_max);

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

static int	discoverer_manager_init(zbx_discoverer_manager_t *manager, int workers_num, char **error)
{
	int		i, err, ret = FAIL, started_num = 0;
	time_t		time_start;
	struct timespec	poll_delay = {0, 1e8};

	memset(manager, 0, sizeof(zbx_discoverer_manager_t));

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

	zbx_hashset_create(&manager->results, 1, discoverer_result_hash, discoverer_result_compare);
	zbx_hashset_create(&manager->incomplete_checks_count, 1, discoverer_check_count_hash,
			discoverer_check_count_compare);

	zbx_vector_discoverer_job_refs_create(&manager->job_refs);

	manager->timekeeper = zbx_timekeeper_create(workers_num, NULL);

	manager->workers_num = workers_num;
	manager->workers = (zbx_discoverer_worker_t*)zbx_calloc(NULL, (size_t)workers_num,
			sizeof(zbx_discoverer_worker_t));

	for (i = 0; i < workers_num; i++)
	{
		if (SUCCEED != discoverer_worker_init(&manager->workers[i], &manager->queue, manager->timekeeper,
				discoverer_worker_entry, error))
		{
			goto out;
		}

		manager->workers[i].worker_id = i;
	}

	/* wait for threads to start */
	time_start = time(NULL);

	while (started_num != workers_num)
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
		zbx_vector_discoverer_job_refs_destroy(&manager->job_refs);

		manager->timekeeper = zbx_timekeeper_create(workers_num, NULL);
		zbx_timekeeper_free(manager->timekeeper);
	}

	return ret;
}

static void	discoverer_manager_free(zbx_discoverer_manager_t *manager)
{
	int			i;
	zbx_hashset_iter_t	iter;
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

	zbx_vector_discoverer_job_refs_clear(&manager->job_refs);
	zbx_vector_discoverer_job_refs_destroy(&manager->job_refs);

	zbx_hashset_iter_reset(&manager->results, &iter);

	while (NULL != (result = (zbx_discoverer_results_t *)zbx_hashset_iter_next(&iter)))
		results_clear(result);

	zbx_hashset_destroy(&manager->results);

	pthread_mutex_destroy(&manager->results_lock);
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
	int				rule_count = 0, old_rule_count = 0, result_count = 0, old_result_count = 0;
	zbx_uint64_t			check_count = 0, old_check_count = 0;
	double				sec, total_sec = 0.0, old_total_sec = 0.0;
	time_t				last_stat_time, nextcheck = 0;
	zbx_ipc_service_t		ipc_service;
	zbx_ipc_client_t		*client;
	zbx_ipc_message_t		*message;
	zbx_timespec_t			sleeptime = { .sec = -1, .ns = 0 };
	const zbx_thread_info_t		*info = &((zbx_thread_args_t *)args)->info;
	int				server_num = ((zbx_thread_args_t *)args)->info.server_num;
	int				process_num = ((zbx_thread_args_t *)args)->info.process_num;
	unsigned char			process_type = ((zbx_thread_args_t *)args)->info.process_type;
	char				*error;
	zbx_vector_uint64_pair_t	revisions;
	zbx_uint32_t			rtc_msgs[] = {ZBX_RTC_SNMP_CACHE_RELOAD};

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(info->program_type),
			server_num, get_process_type_string(process_type), process_num);

	zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_BUSY);

#define STAT_INTERVAL	5	/* if a process is busy and does not sleep then update status not faster than */
				/* once in STAT_INTERVAL seconds */

#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	zbx_tls_init_child(discoverer_args_in->zbx_config_tls, discoverer_args_in->zbx_get_program_type_cb_arg);
#endif
	zbx_setproctitle("%s #%d [connecting to the database]", get_process_type_string(process_type), process_num);
	last_stat_time = time(NULL);

	zbx_db_connect(ZBX_DB_CONNECT_NORMAL);

	if (FAIL == zbx_ipc_service_start(&ipc_service, ZBX_IPC_SERVICE_DISCOVERER, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot start discoverer service: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	if (FAIL == discoverer_manager_init(&dmanager, discoverer_args_in->workers_num, &error))
	{
		zabbix_log(LOG_LEVEL_ERR, "Cannot initialize discovery manager");
		goto out;
	}

	zbx_rtc_subscribe_service(ZBX_PROCESS_TYPE_DISCOVERER, 0, rtc_msgs, ARRSIZE(rtc_msgs),
			discoverer_args_in->config_timeout, ZBX_IPC_SERVICE_DISCOVERER);

#ifdef HAVE_NETSNMP
	zbx_mt_init_snmp();
#endif
	zbx_vector_uint64_pair_create(&revisions);

	while (ZBX_IS_RUNNING())
	{
		int		i;

		sec = zbx_time();
		zbx_update_env(get_process_type_string(process_type), sec);

		if (0 != sleeptime.sec)
		{
			zbx_setproctitle("%s #%d [processed %d rules, queued " ZBX_FS_UI64 " network checks, processed "
					"%d check results in " ZBX_FS_DBL " sec,performing discovery]",
					get_process_type_string(process_type), process_num, old_rule_count,
					old_check_count, old_result_count, old_total_sec);
		}

		/* update local drules revisions */
		zbx_vector_uint64_pair_clear(&revisions);
		zbx_dc_drule_revisions_get(&revisions);

		discoverer_queue_lock(&dmanager.queue);

		for (i = 0; i < dmanager.job_refs.values_num; i++)
		{
			int				k;
			zbx_uint64_pair_t		revision;
			zbx_discoverer_job_ref_t	*jref = (zbx_discoverer_job_ref_t*)&dmanager.job_refs.values[i];

			revision.first = jref->druleid;

			if (FAIL != (k = zbx_vector_uint64_pair_bsearch(&revisions, revision,
					ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
			{
				zbx_uint64_pair_t	*rev = (zbx_uint64_pair_t*)&revisions.values[k];

				if (rev->second != jref->job->drule_revision)
					discoverer_job_tasks_free(jref->job);
			}
		}

		discoverer_queue_unlock(&dmanager.queue);

		result_count += process_results(&dmanager);

		/* process discovery rules and create net check jobs */

		if ((int)sec >= nextcheck)
		{
			int					rule_count_current;
			zbx_vector_discoverer_jobs_ptr_t	jobs;
			zbx_hashset_t				check_counts;

			zbx_vector_discoverer_jobs_ptr_create(&jobs);
			zbx_hashset_create(&check_counts, 1, discoverer_check_count_hash,
					discoverer_check_count_compare);

			rule_count_current = process_discovery(&nextcheck, discoverer_args_in->config_timeout, &jobs,
					&check_counts);
			rule_count += rule_count_current;
			total_sec += zbx_time() - sec;

			if (0 == nextcheck)
				nextcheck = time(NULL) + DISCOVERER_DELAY;

			if (0 < rule_count_current)
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
					zbx_discoverer_job_t		*job;
					zbx_discoverer_job_ref_t	job_ref;

					job = (zbx_discoverer_job_t*)jobs.values[i];
					discoverer_queue_push(&dmanager.queue, job);
					job_ref.druleid = job->druleid;
					job_ref.job = job;
					zbx_vector_discoverer_job_refs_append(&dmanager.job_refs, job_ref);
				}

				zbx_vector_discoverer_job_refs_sort(&dmanager.job_refs,
						ZBX_DEFAULT_UINT64_COMPARE_FUNC);

				discoverer_queue_notify_all(&dmanager.queue);
				discoverer_queue_unlock(&dmanager.queue);

				check_count += queued;
			}

			zbx_vector_discoverer_jobs_ptr_destroy(&jobs);
			zbx_hashset_destroy(&check_counts);
		}

		/* update sleeptime and process title */

		sleeptime.sec = zbx_calculate_sleeptime(nextcheck, DISCOVERER_DELAY);

		if (0 != sleeptime.sec || STAT_INTERVAL <= time(NULL) - last_stat_time)
		{
			if (0 == sleeptime.sec)
			{
				zbx_setproctitle("%s #%d [processed %d rules, queued " ZBX_FS_UI64 " network checks, "
						"processed %d check results in " ZBX_FS_DBL " sec, performing "
						"discovery]", get_process_type_string(process_type), process_num,
						rule_count, check_count, result_count, total_sec);
			}
			else
			{
				zbx_setproctitle("%s #%d [processed %d rules, queued " ZBX_FS_UI64 " network checks, "
						"processed %d check results in " ZBX_FS_DBL " sec, idle %d sec]",
						get_process_type_string(process_type), process_num,
						rule_count, check_count, result_count, total_sec, sleeptime.sec);
				old_rule_count = rule_count;
				old_result_count = result_count;
				old_check_count = check_count;
				old_total_sec = total_sec;
			}
			rule_count = 0;
			check_count = 0;
			result_count = 0;
			total_sec = 0.0;
			last_stat_time = time(NULL);
		}

		zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_IDLE);
		(void)zbx_ipc_service_recv(&ipc_service, &sleeptime, &client, &message);
		zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_BUSY);

		if (NULL != message)
		{
			switch (message->code)
			{
				case ZBX_IPC_DISCOVERER_QUEUE:
					discoverer_queue_lock(&dmanager.queue);
					zbx_uint64_t	count = dmanager.queue.pending_checks_count;
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
	zbx_setproctitle("%s #%d [terminated]", get_process_type_string(process_type), process_num);

	discoverer_manager_free(&dmanager);

	while (1)
		zbx_sleep(SEC_PER_MIN);
#undef STAT_INTERVAL
}
