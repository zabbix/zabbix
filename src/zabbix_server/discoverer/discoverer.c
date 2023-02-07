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

#define DISCOVERER_JOB_QUEUE_INIT_NONE		0x00
#define DISCOVERER_JOB_QUEUE_INIT_LOCK		0x01
#define DISCOVERER_JOB_QUEUE_INIT_EVENT		0x02

typedef struct
{
	int		workers_num;
	zbx_list_t	jobs;
	pthread_mutex_t	lock;
	pthread_cond_t	event;
	int		flags;
}
zbx_discoverer_jobs_queue_t;

typedef struct
{
	zbx_discoverer_jobs_queue_t	*queue;
	pthread_t			thread;
	int				worker_id;
	int				stop;
}
zbx_discoverer_worker_t;

typedef struct
{
	zbx_uint64_t	druleid;
	DC_DRULE	*drule;
	DC_DCHECK	*dcheck;
	char		*ip;
	char		*dns;
	unsigned short	port;
	int		now;
	int		config_timeout;
}
zbx_discoverer_net_check_job_t;

typedef struct
{
	zbx_vector_ptr_t	services;
	DC_DRULE		*drule;
	char			*ip;
	char			*dns;
	int			now;
}
zbx_discovery_results_t;

typedef struct
{
	int				workers_num;
	zbx_discoverer_worker_t		*workers;
	zbx_discoverer_jobs_queue_t	queue;

	zbx_vector_ptr_t		results;
	pthread_rwlock_t		results_rwlock;

	zbx_vector_uint64_pair_t	revisions;
	pthread_rwlock_t		revisions_rwlock;
}
zbx_discoverer_manager_t;

extern unsigned char			program_type;

#define ZBX_DISCOVERER_IPRANGE_LIMIT	(1 << 16)
#define ZBX_DISCOVERER_STARTUP_TIMEOUT	30

static zbx_discoverer_manager_t 	dmanager;

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

static int	results_compare(const void *d1, const void *d2)
{
	int				ret;
	const zbx_discovery_results_t	*r1 = *((const zbx_discovery_results_t * const *)d1);
	const zbx_discovery_results_t	*r2 = *((const zbx_discovery_results_t * const *)d2);

	if (0 == (ret = (int)r1->drule->druleid - (int)r2->drule->druleid))
		ret = strcmp(r1->ip, r2->ip);

	return ret;
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
static int	discover_service(const DC_DCHECK *dcheck, char *ip, int port, int config_timeout, char **value,
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
		case SVC_ICMPPING:
			break;
		default:
			ret = FAIL;
			break;
	}

	if (SUCCEED == ret)
	{
		char		**pvalue;
		size_t		value_offset = 0;
		ZBX_FPING_HOST	host;
		DC_ITEM		item;
		char		key[MAX_STRING_LEN], error[ZBX_ITEM_ERROR_LEN_MAX];

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
					item.snmp_community = strdup(dcheck->snmp_community);
					item.snmp_oid = strdup(dcheck->key_);

					zbx_substitute_simple_macros_unmasked(NULL, NULL, NULL, NULL, NULL, NULL, NULL,
							NULL, NULL, NULL, NULL, NULL, &item.snmp_community,
							MACRO_TYPE_COMMON, NULL, 0);
					zbx_substitute_key_macros(&item.snmp_oid, NULL, NULL, NULL, NULL,
							MACRO_TYPE_SNMP_OID, NULL, 0);

					if (ZBX_IF_SNMP_VERSION_3 == item.snmp_version)
					{
						item.snmpv3_securityname =
								zbx_strdup(NULL, dcheck->snmpv3_securityname);
						item.snmpv3_securitylevel = dcheck->snmpv3_securitylevel;
						item.snmpv3_authpassphrase =
								zbx_strdup(NULL, dcheck->snmpv3_authpassphrase);
						item.snmpv3_privpassphrase =
								zbx_strdup(NULL, dcheck->snmpv3_privpassphrase);
						item.snmpv3_authprotocol = dcheck->snmpv3_authprotocol;
						item.snmpv3_privprotocol = dcheck->snmpv3_privprotocol;
						item.snmpv3_contextname = zbx_strdup(NULL, dcheck->snmpv3_contextname);

						zbx_substitute_simple_macros_unmasked(NULL, NULL, NULL, NULL, NULL, NULL,
								NULL, NULL, NULL, NULL, NULL, NULL,
								&item.snmpv3_securityname, MACRO_TYPE_COMMON, NULL, 0);
						zbx_substitute_simple_macros_unmasked(NULL, NULL, NULL, NULL, NULL, NULL,
								NULL, NULL, NULL, NULL, NULL, NULL,
								&item.snmpv3_authpassphrase, MACRO_TYPE_COMMON, NULL,
								0);
						zbx_substitute_simple_macros_unmasked(NULL, NULL, NULL, NULL, NULL, NULL,
								NULL, NULL, NULL, NULL, NULL, NULL,
								&item.snmpv3_privpassphrase, MACRO_TYPE_COMMON, NULL, 0);
						zbx_substitute_simple_macros_unmasked(NULL, NULL, NULL, NULL, NULL, NULL,
								NULL, NULL, NULL, NULL, NULL, NULL,
								&item.snmpv3_contextname, MACRO_TYPE_COMMON, NULL, 0);
					}

					if (SUCCEED == get_value_snmp(&item, &result, ZBX_NO_POLLER, config_timeout) &&
							NULL != (pvalue = ZBX_GET_TEXT_RESULT(&result)))
					{
						zbx_strcpy_alloc(value, value_alloc, &value_offset, *pvalue);
					}
					else
						ret = FAIL;

					zbx_free(item.snmp_community);
					zbx_free(item.snmp_oid);

					if (ZBX_IF_SNMP_VERSION_3 == item.snmp_version)
					{
						zbx_free(item.snmpv3_securityname);
						zbx_free(item.snmpv3_authpassphrase);
						zbx_free(item.snmpv3_privpassphrase);
						zbx_free(item.snmpv3_contextname);
					}
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
			case SVC_ICMPPING:
				memset(&host, 0, sizeof(host));
				host.addr = strdup(ip);

				if (SUCCEED != zbx_ping(&host, 1, 3, 0, 0, 0, error, sizeof(error)) || 0 == host.rcv)
					ret = FAIL;

				zbx_free(host.addr);
				break;
			default:
				break;
		}
	}
	zbx_free_agent_result(&result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

static void	drule_copy(const DC_DRULE *src, DC_DRULE *dst)
{
	dst->druleid = src->druleid;
	dst->proxy_hostid = src->proxy_hostid;
	dst->nextcheck = src->nextcheck;
	dst->delay = src->delay;
	dst->delay_str = zbx_strdup(NULL, src->delay_str);
	dst->iprange = zbx_strdup(NULL, src->iprange);
	dst->status = src->status;
	dst->location = src->location;
	dst->revision = src->revision;
	dst->name = zbx_strdup(NULL, src->name);
	dst->unique_dcheckid = src->unique_dcheckid;
}

static void	dcheck_copy(const DC_DCHECK *src, DC_DCHECK *dst)
{
	dst->dcheckid = src->dcheckid;
	dst->druleid = src->druleid;
	dst->type = src->type;
	dst->key_ = zbx_strdup(NULL, src->key_);
	dst->snmp_community = zbx_strdup(NULL, src->snmp_community);
	dst->ports = zbx_strdup(NULL, src->ports);
	dst->snmpv3_securityname = zbx_strdup(NULL, src->snmpv3_securityname);
	dst->snmpv3_securitylevel = src->snmpv3_securitylevel;
	dst->snmpv3_authpassphrase = zbx_strdup(NULL, src->snmpv3_authpassphrase);
	dst->snmpv3_privpassphrase = zbx_strdup(NULL, src->snmpv3_privpassphrase);
	dst->uniq = src->uniq;
	dst->snmpv3_authprotocol = src->snmpv3_authprotocol;
	dst->snmpv3_privprotocol = src->snmpv3_privprotocol;
	dst->snmpv3_contextname = zbx_strdup(NULL, src->snmpv3_contextname);
}

static void	dcheck_free(DC_DCHECK *dcheck)
{
	zbx_free(dcheck->key_);
	zbx_free(dcheck->snmp_community);
	zbx_free(dcheck->snmpv3_securityname);
	zbx_free(dcheck->ports);
	zbx_free(dcheck->snmpv3_authpassphrase);
	zbx_free(dcheck->snmpv3_privpassphrase);
	zbx_free(dcheck->snmpv3_contextname);
	zbx_free(dcheck);
}

static void	drule_free(DC_DRULE *drule)
{
	zbx_free(drule->delay_str);
	zbx_free(drule->iprange);
	zbx_free(drule->name);
	zbx_vector_ptr_clear_ext(&drule->dchecks, (zbx_clean_func_t)dcheck_free);
	zbx_vector_ptr_destroy(&drule->dchecks);
	zbx_free(drule);
}

static void	results_free(zbx_discovery_results_t *result)
{
	drule_free(result->drule);
	zbx_free(result->ip);
	zbx_free(result->dns);
	zbx_vector_ptr_clear_ext(&result->services, zbx_ptr_free);
	zbx_vector_ptr_destroy(&result->services);
	zbx_free(result);
}

static void	discoverer_job_net_check_push(zbx_discoverer_jobs_queue_t *queue,
		zbx_discoverer_net_check_job_t *net_check)
{
	zbx_list_append(&queue->jobs, net_check, NULL);
}

static void	process_check(const DC_DRULE *drule, const DC_DCHECK *dcheck, char *ip, char *dns, int now,
		int config_timeout)
{
	const char	*start;
	char		*value = NULL;
	size_t		value_alloc = 128;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	value = (char *)zbx_malloc(value, value_alloc);

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

		pthread_mutex_lock(&dmanager.queue.lock);

		for (port = first; port <= last; port++)
		{
			zbx_discoverer_net_check_job_t	*net_check;

			net_check = (zbx_discoverer_net_check_job_t*)zbx_malloc(NULL,
					sizeof(zbx_discoverer_net_check_job_t));
			net_check->druleid = drule->druleid;

			net_check->drule = (DC_DRULE*)zbx_malloc(NULL, sizeof(DC_DRULE));
			drule_copy(drule, net_check->drule);
			zbx_vector_ptr_create(&net_check->drule->dchecks);

			net_check->dcheck = (DC_DCHECK*)zbx_malloc(NULL, sizeof(DC_DCHECK));
			dcheck_copy(dcheck, net_check->dcheck);

			net_check->ip = zbx_strdup(NULL, ip);
			net_check->dns = zbx_strdup(NULL, dns);
			net_check->port = (unsigned short)port;
			net_check->config_timeout = config_timeout;
			net_check->now = now;

			discoverer_job_net_check_push(&dmanager.queue, net_check);
		}

		pthread_mutex_unlock(&dmanager.queue.lock);

		if (NULL != comma)
		{
			*comma = ',';
			start = comma + 1;
		}
		else
			break;
	}
	zbx_free(value);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	process_checks(const DC_DRULE *drule, char *ip, char *dns, int unique, int now, int config_timeout)
{
	int		i;

	for (i = 0; i < drule->dchecks.values_num; i++)
	{
		DC_DCHECK	*dcheck = (DC_DCHECK*)drule->dchecks.values[i];

		if (0 != drule->unique_dcheckid &&
				((1 == unique && drule->unique_dcheckid != dcheck->dcheckid) ||
				(0 == unique && drule->unique_dcheckid == dcheck->dcheckid)))
		{
			continue;
		}

		process_check(drule, dcheck, ip, dns, now, config_timeout);
	}
}

static void	process_services(const DC_DRULE *drule, zbx_db_dhost *dhost, const char *ip, const char *dns,
		int now, const zbx_vector_ptr_t *services)
{
	int	i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	for (i = 0; i < services->values_num; i++)
	{
		zbx_dservice_t	*service = (zbx_dservice_t *)services->values[i];

		if (0 != (program_type & ZBX_PROGRAM_TYPE_SERVER))
		{
			zbx_discovery_update_service(drule->druleid, service->dcheckid, drule->unique_dcheckid, dhost,
					ip, dns, service->port, service->status, service->value, now);
		}
		else if (0 != (program_type & ZBX_PROGRAM_TYPE_PROXY))
		{
			proxy_update_service(drule->druleid, service->dcheckid, ip, dns, service->port,
					service->status, service->value, now);
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: process single discovery rule                                     *
 *                                                                            *
 ******************************************************************************/
static void	process_rule(DC_DRULE *drule, int config_timeout)
{
	char			ip[ZBX_INTERFACE_IP_LEN_MAX], *start, *comma, dns[ZBX_INTERFACE_DNS_LEN_MAX];
	int			ipaddress[8], now;
	zbx_iprange_t		iprange;

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
			now = time(NULL);

			zabbix_log(LOG_LEVEL_DEBUG, "%s() ip:'%s'", __func__, ip);

			zbx_alarm_on(config_timeout);
			zbx_gethost_by_ip(ip, dns, sizeof(dns));
			zbx_alarm_off();

			if (0 != drule->unique_dcheckid)
				process_checks(drule, ip, dns, 1, now, config_timeout);

			process_checks(drule, ip, dns, 0, now, config_timeout);
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

static void	process_results(zbx_discoverer_manager_t *manager)
{
	int	i;

	pthread_rwlock_wrlock(&manager->results_rwlock);

	for (i = 0; i < manager->results.values_num; i++)
	{
		zbx_discovery_results_t	*result = manager->results.values[i];
		zbx_db_dhost		dhost;
		int			host_status = -1, k;

		for (k = 0; k < result->services.values_num; k++)
		{
			zbx_dservice_t	*service = result->services.values[k];

			if (-1 == host_status || DOBJECT_STATUS_UP == service->status)
			{
				host_status = service->status;

				if (DOBJECT_STATUS_UP == service->status)
					break;
			}
		}

		memset(&dhost, 0, sizeof(zbx_db_dhost));

		process_services(result->drule, &dhost, result->ip, result->dns, result->now, &result->services);

		if (0 != (program_type & ZBX_PROGRAM_TYPE_SERVER))
		{
			zbx_discovery_update_host(&dhost, host_status, result->now);
			zbx_process_events(NULL, NULL);
			zbx_clean_events();
		}
		else if (0 != (program_type & ZBX_PROGRAM_TYPE_PROXY))
		{
			proxy_update_host(result->drule->druleid, result->ip, result->dns, host_status, result->now);
		}
	}

	zbx_vector_ptr_clear_ext(&manager->results, (zbx_clean_func_t)results_free);
	pthread_rwlock_unlock(&manager->results_rwlock);
}

static int	process_discovery(time_t *nextcheck, int config_timeout)
{
	int			rule_count = 0, delay, i;
	char			*delay_str = NULL;
	zbx_dc_um_handle_t	*um_handle;
	time_t			now;
	DC_DRULE		*drule;

	now = time(NULL);

	um_handle = zbx_dc_open_user_macros();

	while (ZBX_IS_RUNNING() && NULL != (drule = zbx_dc_drule_next(now, nextcheck)))
	{
		rule_count++;

		for (i = 0; i < drule->dchecks.values_num; i++)
		{
			DC_DCHECK	*dcheck = (DC_DCHECK*)drule->dchecks.values[i];

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
			drule->delay = delay;
			process_rule(drule, config_timeout);
		}

		zbx_dc_drule_queue(now, drule->druleid, delay);
		zbx_free(delay_str);

		if (0 != (program_type & ZBX_PROGRAM_TYPE_SERVER))
			discovery_clean_services(drule->druleid);

		drule_free(drule);

		now = time(NULL);
	}

	zbx_dc_close_user_macros(um_handle);

	return rule_count;	/* performance metric */
}

static zbx_discoverer_net_check_job_t	*discoverer_job_net_check_pop(zbx_discoverer_jobs_queue_t *queue)
{
	void	*job;

	if (SUCCEED == zbx_list_pop(&queue->jobs, &job))
		return (zbx_discoverer_net_check_job_t*)job;

	return NULL;
}

static void	*discoverer_net_check(void *net_check_worker)
{
	char				*value = NULL;
	int				err;
	size_t				value_alloc = 128;
	zbx_discoverer_worker_t		*worker = (zbx_discoverer_worker_t*)net_check_worker;
	zbx_discoverer_net_check_job_t	*job;

	worker->queue->workers_num++;
	worker->stop = 0;

	value = (char *)zbx_malloc(value, value_alloc);

	pthread_mutex_lock(&dmanager.queue.lock);

	while (0 == worker->stop)
	{
		if (NULL != (job = discoverer_job_net_check_pop(worker->queue)))
		{
			int			index, skip = 1;
			zbx_uint64_pair_t	revision, *revision_updated;
			zbx_dservice_t		*service = NULL;
			zbx_discovery_results_t	*result = NULL, result_cmp;
			DC_DRULE		drule_cmp;

			pthread_mutex_unlock(&dmanager.queue.lock);

			/* check if drule was updated or deleted */

			revision.first = job->druleid;
			pthread_rwlock_rdlock(&dmanager.revisions_rwlock);

			if (FAIL != (index = zbx_vector_uint64_pair_bsearch(&dmanager.revisions, revision,
					ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
			{
				revision_updated = (zbx_uint64_pair_t*)&dmanager.revisions.values[index];

				if (revision_updated->second == job->drule->revision)
					skip = 0;
			}

			pthread_rwlock_unlock(&dmanager.revisions_rwlock);

			if (0 != skip)
			{
				pthread_mutex_lock(&dmanager.queue.lock);
				continue;
			}

			/* perform net checks */

			service = (zbx_dservice_t *)zbx_malloc(service, sizeof(zbx_dservice_t));

			service->status = (SUCCEED == discover_service(job->dcheck, job->ip, job->port,
					job->config_timeout, &value, &value_alloc)) ? DOBJECT_STATUS_UP :
					DOBJECT_STATUS_DOWN;

			service->dcheckid = job->dcheck->dcheckid;
			service->itemtime = (time_t)job->now;
			service->port = job->port;
			zbx_strlcpy_utf8(service->value, value, ZBX_MAX_DISCOVERED_VALUE_SIZE);

			drule_cmp.druleid = job->druleid;
			result_cmp.drule = &drule_cmp;
			result_cmp.ip = job->ip;

			pthread_rwlock_wrlock(&dmanager.results_rwlock);

			if (FAIL == (index = zbx_vector_ptr_search(&dmanager.results, &result_cmp, results_compare)))
			{
				result = (zbx_discovery_results_t *)zbx_malloc(result, sizeof(zbx_discovery_results_t));
				zbx_vector_ptr_create(&result->services);
				result->drule = job->drule;
				result->ip = job->ip;
				result->dns = job->dns;
				result->now = job->now;
				zbx_vector_ptr_append(&dmanager.results, result);
			}
			else
			{
				drule_free(job->drule);
				zbx_free(job->ip);
				zbx_free(job->dns);
				result = (zbx_discovery_results_t *)dmanager.results.values[index];
			}

			zbx_vector_ptr_append(&result->services, service);

			pthread_rwlock_unlock(&dmanager.results_rwlock);

			dcheck_free(job->dcheck);
			zbx_free(job);

			pthread_mutex_lock(&dmanager.queue.lock);
			continue;
		}

		if (0 != (err = pthread_cond_wait(&dmanager.queue.event, &dmanager.queue.lock)))
		{
			zabbix_log(LOG_LEVEL_WARNING, "[%d] cannot wait for conditional variable : %s",
					worker->worker_id, zbx_strerror(err));
			worker->stop = 1;
		}
	}

	zbx_free(value);

	return (void*)0;
}

static void	discoverer_job_net_check_free(zbx_discoverer_net_check_job_t *job)
{
	drule_free(job->drule);
	dcheck_free(job->dcheck);
	zbx_free(job->ip);
	zbx_free(job->dns);

	zbx_free(job);
}

static void	discoverer_jobs_queue_clear(zbx_list_t *jobs)
{
	zbx_discoverer_net_check_job_t	*job = NULL;

	while (SUCCEED == zbx_list_pop(jobs, (void **)&job))
		discoverer_job_net_check_free(job);
}

static void	discoverer_jobs_queue_destroy(zbx_discoverer_jobs_queue_t *queue)
{
	if (0 != (queue->flags & DISCOVERER_JOB_QUEUE_INIT_LOCK))
		pthread_mutex_destroy(&queue->lock);

	if (0 != (queue->flags & DISCOVERER_JOB_QUEUE_INIT_EVENT))
		pthread_cond_destroy(&queue->event);

	discoverer_jobs_queue_clear(&queue->jobs);
	zbx_list_destroy(&queue->jobs);

	queue->flags = DISCOVERER_JOB_QUEUE_INIT_NONE;
}

static int	discoverer_jobs_queue_init(zbx_discoverer_jobs_queue_t *queue, char **error)
{
	int	err, ret = FAIL;

	queue->workers_num = 0;
	queue->flags = DISCOVERER_JOB_QUEUE_INIT_NONE;

	zbx_list_create(&queue->jobs);

	if (0 != (err = pthread_mutex_init(&queue->lock, NULL)))
	{
		*error = zbx_dsprintf(NULL, "cannot initialize queue mutex: %s", zbx_strerror(err));
		goto out;
	}

	queue->flags |= DISCOVERER_JOB_QUEUE_INIT_LOCK;

	if (0 != (err = pthread_cond_init(&queue->event, NULL)))
	{
		*error = zbx_dsprintf(NULL, "cannot initialize conditional variable: %s", zbx_strerror(err));
		goto out;
	}

	queue->flags |= DISCOVERER_JOB_QUEUE_INIT_EVENT;

	ret = SUCCEED;
out:
	if (FAIL == ret)
		discoverer_jobs_queue_destroy(queue);

	return ret;
}

static int	discoverer_workers_init(zbx_discoverer_worker_t *worker, zbx_discoverer_jobs_queue_t *queue,
		void *func(void *), char **error)
{
	int	err;

	worker->queue = queue;
	worker->stop = 1;

	if (0 != (err = pthread_create(&worker->thread, NULL, func, (void *)worker)))
	{
		*error = zbx_dsprintf(NULL, "cannot craete thread: %s", zbx_strerror(err));
		return FAIL;
	}

	return SUCCEED;
}

static int	discoverer_manager_init(zbx_discoverer_manager_t *manager, int workers_num, char **error)
{
	int		i, started_num = 0, err;
	time_t		time_start;
	struct timespec	poll_delay = {0, 1e8};

	memset(manager, 0, sizeof(zbx_discoverer_manager_t));

	zbx_vector_ptr_create(&manager->results);
	zbx_vector_uint64_pair_create(&manager->revisions);

	if (0 != (err = pthread_rwlock_init(&manager->revisions_rwlock, NULL)) ||
			0 != (err = pthread_rwlock_init(&manager->results_rwlock, NULL)))
	{
		*error = zbx_dsprintf(NULL, "cannot initialize mutex: %s", zbx_strerror(err));
		return FAIL;
	}

	if (SUCCEED != discoverer_jobs_queue_init(&manager->queue, error))
		return FAIL;

	manager->workers_num = workers_num;
	manager->workers = (zbx_discoverer_worker_t*)zbx_calloc(NULL, (size_t)workers_num,
			sizeof(zbx_discoverer_worker_t));

	for (i = 0; i < workers_num; i++)
	{
		if (SUCCEED != discoverer_workers_init(&manager->workers[i], &manager->queue, discoverer_net_check,
				error))
		{
			return FAIL;
		}

		manager->workers[i].worker_id = i;
	}

	/* wait for threads to start */
	time_start = time(NULL);

	while (started_num != workers_num)
	{
		if (time_start + ZBX_DISCOVERER_STARTUP_TIMEOUT < time(NULL))
		{
			*error = zbx_strdup(NULL, "timeout occured while waiting for workers to start");
			return FAIL;
		}

		pthread_mutex_lock(&manager->queue.lock);
		started_num = manager->queue.workers_num;
		pthread_mutex_unlock(&manager->queue.lock);

		nanosleep(&poll_delay, NULL);
	}

	return SUCCEED;
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
	int				sleeptime = -1, sleeptime_res = -1, rule_count = 0, old_rule_count = 0;
	double				sec, total_sec = 0.0, old_total_sec = 0.0;
	time_t				last_stat_time, nextcheck = 0, nextresult = 0;
	zbx_ipc_async_socket_t		rtc;
	const zbx_thread_info_t		*info = &((zbx_thread_args_t *)args)->info;
	int				server_num = ((zbx_thread_args_t *)args)->info.server_num;
	int				process_num = ((zbx_thread_args_t *)args)->info.process_num;
	unsigned char			process_type = ((zbx_thread_args_t *)args)->info.process_type;
	char				*error;

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

	zbx_rtc_subscribe(process_type, process_num, discoverer_args_in->config_timeout, &rtc);

	if (FAIL == discoverer_manager_init(&dmanager, discoverer_args_in->workers_num, &error))
	{
		zabbix_log(LOG_LEVEL_ERR, "Cannot initialize discovery manager");
		goto out;
	}

	while (ZBX_IS_RUNNING())
	{
		zbx_uint32_t	rtc_cmd;
		unsigned char	*rtc_data;

		sec = zbx_time();
		zbx_update_env(get_process_type_string(process_type), sec);

		if (0 != sleeptime)
		{
			zbx_setproctitle("%s #%d [processed %d rules in " ZBX_FS_DBL " sec, performing discovery]",
					get_process_type_string(process_type), process_num, old_rule_count,
					old_total_sec);
		}

		/* update local drules revisions */
		pthread_rwlock_wrlock(&dmanager.revisions_rwlock);
		zbx_vector_uint64_pair_clear(&dmanager.revisions);
		zbx_dc_drule_revisions_get(&dmanager.revisions);
		pthread_rwlock_unlock(&dmanager.revisions_rwlock);

		if ((int)sec >= nextresult)
		{
			process_results(&dmanager);
			nextresult = time(NULL) + DISCOVERER_DELAY;
		}

		/* process discovery rules and create net check jobs */

		if ((int)sec >= nextcheck)
		{
			rule_count += process_discovery(&nextcheck, discoverer_args_in->config_timeout);
			total_sec += zbx_time() - sec;

			if (0 == nextcheck)
				nextcheck = time(NULL) + DISCOVERER_DELAY;

			if (0 < rule_count)
			{
				pthread_mutex_lock(&dmanager.queue.lock);
				pthread_cond_broadcast(&dmanager.queue.event);
				pthread_mutex_unlock(&dmanager.queue.lock);
			}
		}

		/* update sleeptime and process title */

		sleeptime = zbx_calculate_sleeptime(nextcheck, DISCOVERER_DELAY);

		if (sleeptime > (sleeptime_res = zbx_calculate_sleeptime(nextresult, DISCOVERER_DELAY)))
			sleeptime = sleeptime_res;

		if (0 != sleeptime || STAT_INTERVAL <= time(NULL) - last_stat_time)
		{
			if (0 == sleeptime)
			{
				zbx_setproctitle("%s #%d [processed %d rules in " ZBX_FS_DBL " sec, performing "
						"discovery]", get_process_type_string(process_type), process_num,
						rule_count, total_sec);
			}
			else
			{
				zbx_setproctitle("%s #%d [processed %d rules in " ZBX_FS_DBL " sec, idle %d sec]",
						get_process_type_string(process_type), process_num, rule_count,
						total_sec, sleeptime);
				old_rule_count = rule_count;
				old_total_sec = total_sec;
			}
			rule_count = 0;
			total_sec = 0.0;
			last_stat_time = time(NULL);
		}

		if (SUCCEED == zbx_rtc_wait(&rtc, info, &rtc_cmd, &rtc_data, sleeptime) && 0 != rtc_cmd)
		{
#ifdef HAVE_NETSNMP
			if (ZBX_RTC_SNMP_CACHE_RELOAD == rtc_cmd)
				zbx_clear_cache_snmp(process_type, process_num);
#endif
			if (ZBX_RTC_SHUTDOWN == rtc_cmd)
				break;
		}

	}
out:
	zbx_setproctitle("%s #%d [terminated]", get_process_type_string(process_type), process_num);

	while (1)
		zbx_sleep(SEC_PER_MIN);
#undef STAT_INTERVAL
}
