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

#include "discoverer.h"

#include "log.h"
#include "zbxicmpping.h"
#include "discovery.h"
#include "zbxserver.h"
#include "zbxself.h"
#include "zbxrtc.h"

#include "daemon.h"
#include "../poller/checks_agent.h"
#include "../poller/checks_snmp.h"
#include "zbxcrypto.h"
#include "../events.h"

extern int				CONFIG_DISCOVERER_FORKS;
extern ZBX_THREAD_LOCAL unsigned char	process_type;
extern unsigned char			program_type;
extern ZBX_THREAD_LOCAL int		server_num, process_num;

#define ZBX_DISCOVERER_IPRANGE_LIMIT	(1 << 16)

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

	ip_esc = DBdyn_escape_field("proxy_dhistory", "ip", ip);
	dns_esc = DBdyn_escape_field("proxy_dhistory", "dns", dns);
	value_esc = DBdyn_escape_field("proxy_dhistory", "value", value);

	DBexecute("insert into proxy_dhistory (clock,druleid,dcheckid,ip,dns,port,value,status)"
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

	ip_esc = DBdyn_escape_field("proxy_dhistory", "ip", ip);
	dns_esc = DBdyn_escape_field("proxy_dhistory", "dns", dns);

	DBexecute("insert into proxy_dhistory (clock,druleid,ip,dns,status)"
			" values (%d," ZBX_FS_UI64 ",'%s','%s',%d)",
			now, druleid, ip_esc, dns_esc, status);

	zbx_free(dns_esc);
	zbx_free(ip_esc);
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if service is available                                     *
 *                                                                            *
 * Parameters: service type, ip address, port number                          *
 *                                                                            *
 * Return value: SUCCEED - service is UP, FAIL - service not discovered       *
 *                                                                            *
 ******************************************************************************/
static int	discover_service(const DB_DCHECK *dcheck, char *ip, int port, char **value, size_t *value_alloc)
{
	int		ret = SUCCEED;
	const char	*service = NULL;
	AGENT_RESULT 	result;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	init_result(&result);

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
		char		key[MAX_STRING_LEN], error[ITEM_ERROR_LEN_MAX];

		zbx_alarm_on(CONFIG_TIMEOUT);

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

				if (SUCCEED != process(key, 0, &result) || NULL == GET_UI64_RESULT(&result) ||
						0 == result.ui64)
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

				strscpy(item.key_orig, dcheck->key_);
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
							NULL != (pvalue = GET_TEXT_RESULT(&result)))
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

					substitute_simple_macros_unmasked(NULL, NULL, NULL, NULL, NULL, NULL, NULL,
							NULL, NULL, NULL, NULL, NULL, &item.snmp_community,
							MACRO_TYPE_COMMON, NULL, 0);
					substitute_key_macros(&item.snmp_oid, NULL, NULL, NULL, NULL,
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

						substitute_simple_macros_unmasked(NULL, NULL, NULL, NULL, NULL, NULL,
								NULL, NULL, NULL, NULL, NULL, NULL,
								&item.snmpv3_securityname, MACRO_TYPE_COMMON, NULL, 0);
						substitute_simple_macros_unmasked(NULL, NULL, NULL, NULL, NULL, NULL,
								NULL, NULL, NULL, NULL, NULL, NULL,
								&item.snmpv3_authpassphrase, MACRO_TYPE_COMMON, NULL,
								0);
						substitute_simple_macros_unmasked(NULL, NULL, NULL, NULL, NULL, NULL,
								NULL, NULL, NULL, NULL, NULL, NULL,
								&item.snmpv3_privpassphrase, MACRO_TYPE_COMMON, NULL, 0);
						substitute_simple_macros_unmasked(NULL, NULL, NULL, NULL, NULL, NULL,
								NULL, NULL, NULL, NULL, NULL, NULL,
								&item.snmpv3_contextname, MACRO_TYPE_COMMON, NULL, 0);
					}

					if (SUCCEED == get_value_snmp(&item, &result, ZBX_NO_POLLER) &&
							NULL != (pvalue = GET_TEXT_RESULT(&result)))
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

				if (FAIL == ret && ISSET_MSG(&result))
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

		zbx_alarm_off();
	}
	free_result(&result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if service is available and update database                 *
 *                                                                            *
 * Parameters: service - service info                                         *
 *                                                                            *
 ******************************************************************************/
static void	process_check(const DB_DCHECK *dcheck, int *host_status, char *ip, int now, zbx_vector_ptr_t *services)
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

		for (port = first; port <= last; port++)
		{
			zbx_service_t	*service;

			zabbix_log(LOG_LEVEL_DEBUG, "%s() port:%d", __func__, port);

			service = (zbx_service_t *)zbx_malloc(NULL, sizeof(zbx_service_t));
			service->status = (SUCCEED == discover_service(dcheck, ip, port, &value, &value_alloc) ?
					DOBJECT_STATUS_UP : DOBJECT_STATUS_DOWN);
			service->dcheckid = dcheck->dcheckid;
			service->itemtime = (time_t)now;
			service->port = port;
			zbx_strlcpy_utf8(service->value, value, MAX_DISCOVERED_VALUE_SIZE);
			zbx_vector_ptr_append(services, service);

			/* update host status */
			if (-1 == *host_status || DOBJECT_STATUS_UP == service->status)
				*host_status = service->status;
		}

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

static void	process_checks(const DB_DRULE *drule, int *host_status, char *ip, int unique, int now,
		zbx_vector_ptr_t *services, zbx_vector_uint64_t *dcheckids)
{
	DB_RESULT	result;
	DB_ROW		row;
	DB_DCHECK	dcheck;
	char		sql[MAX_STRING_LEN];
	size_t		offset = 0;

	offset += zbx_snprintf(sql + offset, sizeof(sql) - offset,
			"select dcheckid,type,key_,snmp_community,snmpv3_securityname,snmpv3_securitylevel,"
				"snmpv3_authpassphrase,snmpv3_privpassphrase,snmpv3_authprotocol,snmpv3_privprotocol,"
				"ports,snmpv3_contextname"
			" from dchecks"
			" where druleid=" ZBX_FS_UI64,
			drule->druleid);

	if (0 != drule->unique_dcheckid)
	{
		offset += zbx_snprintf(sql + offset, sizeof(sql) - offset, " and dcheckid%s" ZBX_FS_UI64,
				unique ? "=" : "<>", drule->unique_dcheckid);
	}

	zbx_snprintf(sql + offset, sizeof(sql) - offset, " order by dcheckid");

	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		memset(&dcheck, 0, sizeof(dcheck));

		ZBX_STR2UINT64(dcheck.dcheckid, row[0]);
		dcheck.type = atoi(row[1]);
		dcheck.key_ = row[2];
		dcheck.snmp_community = row[3];
		dcheck.snmpv3_securityname = row[4];
		dcheck.snmpv3_securitylevel = (unsigned char)atoi(row[5]);
		dcheck.snmpv3_authpassphrase = row[6];
		dcheck.snmpv3_privpassphrase = row[7];
		dcheck.snmpv3_authprotocol = (unsigned char)atoi(row[8]);
		dcheck.snmpv3_privprotocol = (unsigned char)atoi(row[9]);
		dcheck.ports = row[10];
		dcheck.snmpv3_contextname = row[11];

		zbx_vector_uint64_append(dcheckids, dcheck.dcheckid);

		process_check(&dcheck, host_status, ip, now, services);
	}
	DBfree_result(result);
}

static int	process_services(const DB_DRULE *drule, DB_DHOST *dhost, const char *ip, const char *dns, int now,
		const zbx_vector_ptr_t *services, zbx_vector_uint64_t *dcheckids)
{
	int	i, ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_sort(dcheckids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	if (SUCCEED != (ret = DBlock_ids("dchecks", "dcheckid", dcheckids)))
		goto fail;

	for (i = 0; i < services->values_num; i++)
	{
		zbx_service_t	*service = (zbx_service_t *)services->values[i];

		if (FAIL == zbx_vector_uint64_bsearch(dcheckids, service->dcheckid, ZBX_DEFAULT_UINT64_COMPARE_FUNC))
			continue;

		if (0 != (program_type & ZBX_PROGRAM_TYPE_SERVER))
		{
			discovery_update_service(drule, service->dcheckid, dhost, ip, dns, service->port,
					service->status, service->value, now);
		}
		else if (0 != (program_type & ZBX_PROGRAM_TYPE_PROXY))
		{
			proxy_update_service(drule->druleid, service->dcheckid, ip, dns, service->port,
					service->status, service->value, now);
		}
	}
fail:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: process single discovery rule                                     *
 *                                                                            *
 ******************************************************************************/
static void	process_rule(DB_DRULE *drule)
{
	DB_DHOST		dhost;
	int			host_status, now;
	char			ip[INTERFACE_IP_LEN_MAX], *start, *comma, dns[INTERFACE_DNS_LEN_MAX];
	int			ipaddress[8];
	zbx_iprange_t		iprange;
	zbx_vector_ptr_t	services;
	zbx_vector_uint64_t	dcheckids;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() rule:'%s' range:'%s'", __func__, drule->name, drule->iprange);

	zbx_vector_ptr_create(&services);
	zbx_vector_uint64_create(&dcheckids);

	for (start = drule->iprange; '\0' != *start;)
	{
		if (NULL != (comma = strchr(start, ',')))
			*comma = '\0';

		zabbix_log(LOG_LEVEL_DEBUG, "%s() range:'%s'", __func__, start);

		if (SUCCEED != iprange_parse(&iprange, start))
		{
			zabbix_log(LOG_LEVEL_WARNING, "discovery rule \"%s\": wrong format of IP range \"%s\"",
					drule->name, start);
			goto next;
		}

		if (ZBX_DISCOVERER_IPRANGE_LIMIT < iprange_volume(&iprange))
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
		iprange_first(&iprange, ipaddress);

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
			memset(&dhost, 0, sizeof(dhost));
			host_status = -1;

			now = time(NULL);

			zabbix_log(LOG_LEVEL_DEBUG, "%s() ip:'%s'", __func__, ip);

			zbx_alarm_on(CONFIG_TIMEOUT);
			zbx_gethost_by_ip(ip, dns, sizeof(dns));
			zbx_alarm_off();

			if (0 != drule->unique_dcheckid)
				process_checks(drule, &host_status, ip, 1, now, &services, &dcheckids);
			process_checks(drule, &host_status, ip, 0, now, &services, &dcheckids);

			DBbegin();

			if (SUCCEED != DBlock_druleid(drule->druleid))
			{
				DBrollback();

				zabbix_log(LOG_LEVEL_DEBUG, "discovery rule '%s' was deleted during processing,"
						" stopping", drule->name);
				zbx_vector_ptr_clear_ext(&services, zbx_ptr_free);
				goto out;
			}

			if (SUCCEED != process_services(drule, &dhost, ip, dns, now, &services, &dcheckids))
			{
				DBrollback();

				zabbix_log(LOG_LEVEL_DEBUG, "all checks where deleted for discovery rule '%s'"
						" during processing, stopping", drule->name);
				zbx_vector_ptr_clear_ext(&services, zbx_ptr_free);
				goto out;
			}

			zbx_vector_uint64_clear(&dcheckids);
			zbx_vector_ptr_clear_ext(&services, zbx_ptr_free);

			if (0 != (program_type & ZBX_PROGRAM_TYPE_SERVER))
			{
				discovery_update_host(&dhost, host_status, now);
				zbx_process_events(NULL, NULL);
				zbx_clean_events();
			}
			else if (0 != (program_type & ZBX_PROGRAM_TYPE_PROXY))
				proxy_update_host(drule->druleid, ip, dns, host_status, now);

			DBcommit();
		}
		while (SUCCEED == iprange_next(&iprange, ipaddress));
next:
		if (NULL != comma)
		{
			*comma = ',';
			start = comma + 1;
		}
		else
			break;
	}
out:
	zbx_vector_ptr_destroy(&services);
	zbx_vector_uint64_destroy(&dcheckids);

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

	result = DBselect("select iprange from drules where druleid=" ZBX_FS_UI64, druleid);

	if (NULL != (row = DBfetch(result)))
		iprange = zbx_strdup(iprange, row[0]);

	DBfree_result(result);

	if (NULL == iprange)
		goto out;

	zbx_vector_uint64_create(&keep_dhostids);
	zbx_vector_uint64_create(&del_dhostids);
	zbx_vector_uint64_create(&del_dserviceids);

	result = DBselect(
			"select dh.dhostid,ds.dserviceid,ds.ip"
			" from dhosts dh"
				" left join dservices ds"
					" on dh.dhostid=ds.dhostid"
			" where dh.druleid=" ZBX_FS_UI64,
			druleid);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(dhostid, row[0]);

		if (SUCCEED == DBis_null(row[1]))
		{
			zbx_vector_uint64_append(&del_dhostids, dhostid);
		}
		else if (SUCCEED != ip_in_list(iprange, row[2]))
		{
			ZBX_STR2UINT64(dserviceid, row[1]);

			zbx_vector_uint64_append(&del_dhostids, dhostid);
			zbx_vector_uint64_append(&del_dserviceids, dserviceid);
		}
		else
			zbx_vector_uint64_append(&keep_dhostids, dhostid);
	}
	DBfree_result(result);

	zbx_free(iprange);

	if (0 != del_dserviceids.values_num)
	{
		int	i;

		/* remove dservices */

		zbx_vector_uint64_sort(&del_dserviceids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		sql_offset = 0;
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from dservices where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "dserviceid",
				del_dserviceids.values, del_dserviceids.values_num);

		DBexecute("%s", sql);

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
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "dhostid",
				del_dhostids.values, del_dhostids.values_num);

		DBexecute("%s", sql);
	}

	zbx_free(sql);

	zbx_vector_uint64_destroy(&del_dserviceids);
	zbx_vector_uint64_destroy(&del_dhostids);
	zbx_vector_uint64_destroy(&keep_dhostids);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static int	process_discovery(void)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		rule_count = 0;
	char		*delay_str = NULL;

	result = DBselect(
			"select distinct r.druleid,r.iprange,r.name,c.dcheckid,r.proxy_hostid,r.delay"
			" from drules r"
				" left join dchecks c"
					" on c.druleid=r.druleid"
						" and c.uniq=1"
			" where r.status=%d"
				" and r.nextcheck<=%d"
				" and " ZBX_SQL_MOD(r.druleid,%d) "=%d",
			DRULE_STATUS_MONITORED,
			(int)time(NULL),
			CONFIG_DISCOVERER_FORKS,
			process_num - 1);

	while (ZBX_IS_RUNNING() && NULL != (row = DBfetch(result)))
	{
		int		now, delay;
		zbx_uint64_t	druleid;

		rule_count++;

		ZBX_STR2UINT64(druleid, row[0]);

		delay_str = zbx_strdup(delay_str, row[5]);
		substitute_simple_macros(NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL,
				&delay_str, MACRO_TYPE_COMMON, NULL, 0);

		if (SUCCEED != is_time_suffix(delay_str, &delay, ZBX_LENGTH_UNLIMITED))
		{
			zabbix_log(LOG_LEVEL_WARNING, "discovery rule \"%s\": invalid update interval \"%s\"",
					row[2], delay_str);

			now = (int)time(NULL);

			DBexecute("update drules set nextcheck=%d where druleid=" ZBX_FS_UI64,
					0 > now ? ZBX_JAN_2038 : now, druleid);

			continue;
		}

		if (SUCCEED == DBis_null(row[4]))
		{
			DB_DRULE	drule;

			memset(&drule, 0, sizeof(drule));

			drule.druleid = druleid;
			drule.iprange = row[1];
			drule.name = row[2];
			ZBX_DBROW2UINT64(drule.unique_dcheckid, row[3]);

			process_rule(&drule);
		}

		if (0 != (program_type & ZBX_PROGRAM_TYPE_SERVER))
			discovery_clean_services(druleid);

		now = (int)time(NULL);
		if (0 > now + delay)
		{
			zabbix_log(LOG_LEVEL_WARNING, "discovery rule \"%s\": nextcheck update causes overflow",
					row[2]);
			DBexecute("update drules set nextcheck=%d where druleid=" ZBX_FS_UI64, ZBX_JAN_2038, druleid);
		}
		else
			DBexecute("update drules set nextcheck=%d where druleid=" ZBX_FS_UI64, now + delay, druleid);
	}
	DBfree_result(result);

	zbx_free(delay_str);

	return rule_count;	/* performance metric */
}

static int	get_minnextcheck(void)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		res = FAIL;

	result = DBselect(
			"select count(*),min(nextcheck)"
			" from drules"
			" where status=%d"
				" and " ZBX_SQL_MOD(druleid,%d) "=%d",
			DRULE_STATUS_MONITORED, CONFIG_DISCOVERER_FORKS, process_num - 1);

	row = DBfetch(result);

	if (NULL == row || DBis_null(row[0]) == SUCCEED || DBis_null(row[1]) == SUCCEED)
		zabbix_log(LOG_LEVEL_DEBUG, "get_minnextcheck(): no items to update");
	else if (0 != atoi(row[0]))
		res = atoi(row[1]);

	DBfree_result(result);

	return res;
}

/******************************************************************************
 *                                                                            *
 * Purpose: periodically try to find new hosts and services                   *
 *                                                                            *
 ******************************************************************************/
ZBX_THREAD_ENTRY(discoverer_thread, args)
{
	int			nextcheck = 0, sleeptime = -1, rule_count = 0, old_rule_count = 0;
	double			sec, total_sec = 0.0, old_total_sec = 0.0;
	time_t			last_stat_time;
	zbx_ipc_async_socket_t	rtc;

	process_type = ((zbx_thread_args_t *)args)->process_type;
	server_num = ((zbx_thread_args_t *)args)->server_num;
	process_num = ((zbx_thread_args_t *)args)->process_num;

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(program_type),
			server_num, get_process_type_string(process_type), process_num);

	update_selfmon_counter(ZBX_PROCESS_STATE_BUSY);

#define STAT_INTERVAL	5	/* if a process is busy and does not sleep then update status not faster than */
				/* once in STAT_INTERVAL seconds */

#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	zbx_tls_init_child();
#endif
	zbx_setproctitle("%s #%d [connecting to the database]", get_process_type_string(process_type), process_num);
	last_stat_time = time(NULL);

	DBconnect(ZBX_DB_CONNECT_NORMAL);

	zbx_rtc_subscribe(&rtc, process_type, process_num);

	while (ZBX_IS_RUNNING())
	{
		zbx_uint32_t	rtc_cmd;
		unsigned char	*rtc_data;

		sec = zbx_time();
		zbx_update_env(sec);

		if (0 != sleeptime)
		{
			zbx_setproctitle("%s #%d [processed %d rules in " ZBX_FS_DBL " sec, performing discovery]",
					get_process_type_string(process_type), process_num, old_rule_count,
					old_total_sec);
		}

		if ((int)sec >= nextcheck)
		{
			rule_count += process_discovery();
			total_sec += zbx_time() - sec;

			if (FAIL == (nextcheck = get_minnextcheck()))
				nextcheck = time(NULL) + DISCOVERER_DELAY;
		}

		sleeptime = calculate_sleeptime(nextcheck, DISCOVERER_DELAY);

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

		if (SUCCEED == zbx_rtc_wait(&rtc, &rtc_cmd, &rtc_data, sleeptime) && 0 != rtc_cmd)
		{
#ifdef HAVE_NETSNMP
			if (ZBX_RTC_SNMP_CACHE_RELOAD == rtc_cmd)
				zbx_clear_cache_snmp(process_type, process_num);
#endif
			if (ZBX_RTC_SHUTDOWN == rtc_cmd)
				break;
		}

	}

	zbx_setproctitle("%s #%d [terminated]", get_process_type_string(process_type), process_num);

	while (1)
		zbx_sleep(SEC_PER_MIN);
#undef STAT_INTERVAL
}
