/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

#include "common.h"

#include "db.h"
#include "log.h"
#include "sysinfo.h"
#include "zbxicmpping.h"
#include "discovery.h"
#include "zbxserver.h"
#include "zbxself.h"

#include "daemon.h"
#include "discoverer.h"
#include "../poller/checks_agent.h"
#include "../poller/checks_snmp.h"

extern int		CONFIG_DISCOVERER_FORKS;
extern unsigned char	daemon_type;
extern unsigned char	process_type;
extern int		process_num;

/******************************************************************************
 *                                                                            *
 * Function: proxy_update_service                                             *
 *                                                                            *
 * Purpose: process new service status                                        *
 *                                                                            *
 * Parameters: service - service info                                         *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
static void	proxy_update_service(DB_DRULE *drule, DB_DCHECK *dcheck, const char *ip,
		const char *dns, int port, int status, const char *value, int now)
{
	char	*ip_esc, *dns_esc, *key_esc, *value_esc;

	ip_esc = DBdyn_escape_string_len(ip, INTERFACE_IP_LEN);
	dns_esc = DBdyn_escape_string_len(dns, INTERFACE_DNS_LEN);
	key_esc = DBdyn_escape_string_len(dcheck->key_, PROXY_DHISTORY_KEY_LEN);
	value_esc = DBdyn_escape_string_len(value, PROXY_DHISTORY_VALUE_LEN);

	DBexecute("insert into proxy_dhistory (clock,druleid,dcheckid,type,ip,dns,port,key_,value,status)"
			" values (%d," ZBX_FS_UI64 "," ZBX_FS_UI64 ",%d,'%s','%s',%d,'%s','%s',%d)",
			now, drule->druleid, dcheck->dcheckid, dcheck->type,
			ip_esc, dns_esc, port, key_esc, value_esc, status);

	zbx_free(value_esc);
	zbx_free(key_esc);
	zbx_free(dns_esc);
	zbx_free(ip_esc);
}

/******************************************************************************
 *                                                                            *
 * Function: proxy_update_host                                                *
 *                                                                            *
 * Purpose: process new service status                                        *
 *                                                                            *
 * Parameters: service - service info                                         *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
static void	proxy_update_host(DB_DRULE *drule, const char *ip, const char *dns, int status, int now)
{
	char	*ip_esc, *dns_esc;

	ip_esc = DBdyn_escape_string_len(ip, INTERFACE_IP_LEN);
	dns_esc = DBdyn_escape_string_len(dns, INTERFACE_DNS_LEN);

	DBexecute("insert into proxy_dhistory (clock,druleid,type,ip,dns,status)"
			" values (%d," ZBX_FS_UI64 ",-1,'%s','%s',%d)",
			now, drule->druleid, ip_esc, dns_esc, status);

	zbx_free(dns_esc);
	zbx_free(ip_esc);
}

/******************************************************************************
 *                                                                            *
 * Function: discover_service                                                 *
 *                                                                            *
 * Purpose: check if service is available and update database                 *
 *                                                                            *
 * Parameters: service type, ip address, port number                          *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 ******************************************************************************/
static int	discover_service(DB_DCHECK *dcheck, char *ip, int port, char *value)
{
	const char	*__function_name = "discover_service";
	int		ret = SUCCEED;
	char		key[MAX_STRING_LEN], error[ITEM_ERROR_LEN_MAX];
	const char	*service = NULL;
	AGENT_RESULT 	result;
	DC_ITEM		item;
	ZBX_FPING_HOST	host;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	init_result(&result);
	*value = '\0';

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
		alarm(CONFIG_TIMEOUT);

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
						item.type = ITEM_TYPE_SNMPv1;
						break;
					case SVC_SNMPv2c:
						item.type = ITEM_TYPE_SNMPv2c;
						break;
					case SVC_SNMPv3:
						item.type = ITEM_TYPE_SNMPv3;
						break;
					default:
						item.type = ITEM_TYPE_ZABBIX;
						break;
				}

				if (SVC_AGENT == dcheck->type)
				{
					if (SUCCEED == get_value_agent(&item, &result) && NULL != GET_STR_RESULT(&result))
						zbx_strlcpy(value, result.str, DSERVICE_VALUE_LEN_MAX);
					else
						ret = FAIL;
				}
				else
#ifdef HAVE_SNMP
				{
					item.snmp_community = strdup(dcheck->snmp_community);
					item.snmp_oid = strdup(dcheck->key_);

					substitute_simple_macros(NULL, NULL, NULL, NULL, NULL, NULL,
							&item.snmp_community, MACRO_TYPE_ITEM_FIELD, NULL, 0);
					substitute_key_macros(&item.snmp_oid, NULL, NULL, NULL,
							MACRO_TYPE_SNMP_OID, NULL, 0);

					if (ITEM_TYPE_SNMPv3 == item.type)
					{
						item.snmpv3_securityname = strdup(dcheck->snmpv3_securityname);
						item.snmpv3_securitylevel = dcheck->snmpv3_securitylevel;
						item.snmpv3_authpassphrase = strdup(dcheck->snmpv3_authpassphrase);
						item.snmpv3_privpassphrase = strdup(dcheck->snmpv3_privpassphrase);

						substitute_simple_macros(NULL, NULL, NULL, NULL, NULL, NULL,
								&item.snmpv3_securityname, MACRO_TYPE_ITEM_FIELD, NULL, 0);
						substitute_simple_macros(NULL, NULL, NULL, NULL, NULL, NULL,
								&item.snmpv3_authpassphrase, MACRO_TYPE_ITEM_FIELD, NULL, 0);
						substitute_simple_macros(NULL, NULL, NULL, NULL, NULL, NULL,
								&item.snmpv3_privpassphrase, MACRO_TYPE_ITEM_FIELD, NULL, 0);
					}

					if (SUCCEED == get_value_snmp(&item, &result) && NULL != GET_STR_RESULT(&result))
						zbx_strlcpy(value, result.str, DSERVICE_VALUE_LEN_MAX);
					else
						ret = FAIL;

					zbx_free(item.snmp_community);
					zbx_free(item.snmp_oid);

					if (ITEM_TYPE_SNMPv3 == item.type)
					{
						zbx_free(item.snmpv3_securityname);
						zbx_free(item.snmpv3_authpassphrase);
						zbx_free(item.snmpv3_privpassphrase);
					}
				}
#else
					ret = FAIL;
#endif	/* HAVE_SNMP */

				if (FAIL == ret && ISSET_MSG(&result))
				{
					zabbix_log(LOG_LEVEL_DEBUG, "discovery: item [%s] error: %s",
							item.key, result.msg);
				}
				break;
			case SVC_ICMPPING:
				memset(&host, 0, sizeof(host));
				host.addr = strdup(ip);

				if (SUCCEED != do_ping(&host, 1, 3, 0, 0, 0, error, sizeof(error)) || 0 == host.rcv)
					ret = FAIL;

				zbx_free(host.addr);
				break;
			default:
				break;
		}

		alarm(0);
	}
	free_result(&result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: process_check                                                    *
 *                                                                            *
 * Purpose: check if service is available and update database                 *
 *                                                                            *
 * Parameters: service - service info                                         *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 ******************************************************************************/
static void	process_check(DB_DRULE *drule, DB_DCHECK *dcheck, DB_DHOST *dhost, int *host_status, char *ip,
		const char *dns)
{
	const char	*__function_name = "process_check";
	int		port, first, last, now;
	char		*start, *comma, *last_port;
	int		status;
	char		value[DSERVICE_VALUE_LEN_MAX];

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	for (start = dcheck->ports; '\0' != *start;)
	{
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
			zabbix_log(LOG_LEVEL_DEBUG, "%s() port:%d", __function_name, port);

			status = (SUCCEED == discover_service(dcheck, ip, port, value)) ? DOBJECT_STATUS_UP : DOBJECT_STATUS_DOWN;

			/* update host status */
			if (-1 == *host_status || DOBJECT_STATUS_UP == status)
				*host_status = status;

			now = time(NULL);

			DBbegin();

			if (0 != (daemon_type & ZBX_DAEMON_TYPE_SERVER))
				discovery_update_service(drule, dcheck, dhost, ip, dns, port, status, value, now);
			else if (0 != (daemon_type & ZBX_DAEMON_TYPE_PROXY))
				proxy_update_service(drule, dcheck, ip, dns, port, status, value, now);

			DBcommit();
		}

		if (NULL != comma)
		{
			*comma = ',';
			start = comma + 1;
		}
		else
			break;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: process_checks                                                   *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
static void	process_checks(DB_DRULE *drule, DB_DHOST *dhost, int *host_status,
		char *ip, const char *dns, int unique)
{
	DB_RESULT	result;
	DB_ROW		row;
	DB_DCHECK	dcheck;
	char		sql[MAX_STRING_LEN];
	size_t		offset = 0;

	offset += zbx_snprintf(sql + offset, sizeof(sql) - offset,
			"select dcheckid,type,key_,snmp_community,snmpv3_securityname,snmpv3_securitylevel,"
				"snmpv3_authpassphrase,snmpv3_privpassphrase,ports"
			" from dchecks"
			" where druleid=" ZBX_FS_UI64,
			drule->druleid);

	if (0 != drule->unique_dcheckid)
	{
		offset += zbx_snprintf(sql + offset, sizeof(sql) - offset, " and dcheckid%s" ZBX_FS_UI64,
				unique ? "=" : "<>", drule->unique_dcheckid);
	}

	offset += zbx_snprintf(sql + offset, sizeof(sql) - offset, " order by dcheckid");

	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		memset(&dcheck, 0, sizeof(dcheck));

		ZBX_STR2UINT64(dcheck.dcheckid, row[0]);
		dcheck.type = atoi(row[1]);
		dcheck.key_ = row[2];
		dcheck.snmp_community = row[3];
		dcheck.snmpv3_securityname = row[4];
		dcheck.snmpv3_securitylevel = atoi(row[5]);
		dcheck.snmpv3_authpassphrase = row[6];
		dcheck.snmpv3_privpassphrase = row[7];
		dcheck.ports = row[8];

		process_check(drule, &dcheck, dhost, host_status, ip, dns);
	}
	DBfree_result(result);
}

/******************************************************************************
 *                                                                            *
 * Function: process_rule                                                     *
 *                                                                            *
 * Purpose: process single discovery rule                                     *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 ******************************************************************************/
static void	process_rule(DB_DRULE *drule)
{
	const char	*__function_name = "process_rule";

	DB_DHOST	dhost;
	int		host_status, now;
	unsigned short	j[9];
	unsigned int	i, first, last, ip_dig;
	char		ip[INTERFACE_IP_LEN_MAX], *start, *comma, *dash, *slash, dns[INTERFACE_DNS_LEN_MAX];
	int		invalid_range;
#ifdef HAVE_IPV6
	int		ipv6;
#endif

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() rule:'%s' range:'%s'", __function_name,
			drule->name, drule->iprange);

	for (start = drule->iprange; '\0' != *start;)
	{
		invalid_range = 0;

		if (NULL != (comma = strchr(start, ',')))
			*comma = '\0';

		zabbix_log(LOG_LEVEL_DEBUG, "%s() range:'%s'", __function_name, start);

		if (NULL != (dash = strchr(start, '-')))
			*dash = '\0';
		else if (NULL != (slash = strchr(start, '/')))
			*slash = '\0';

		if (SUCCEED == ip6_str2dig(start, j))
		{
#ifdef HAVE_IPV6
			ipv6 = 1;

			if (NULL != dash)
			{
				if (1 != sscanf(dash + 1, "%hx", &j[8]))
				{
					invalid_range = 1;
					goto next;
				}

				first = j[7];
				last = j[8];
			}
			else if (NULL != slash)
			{
				unsigned short	mask;

				if (1 != sscanf(slash + 1, "%hu", &j[8]) || 112 > j[8] || j[8] > 128)
				{
					invalid_range = 1;
					goto next;
				}

				mask = 0xffff << (128 - j[8]);
				first = j[7] & mask;
				last = 0xffff & (j[7] | ~mask);
			}
			else
			{
				first = j[7];
				last = j[7];
			}
#else
			invalid_range = 2;
			goto next;
#endif
		}
		else if (SUCCEED == ip4_str2dig(start, &ip_dig))
		{
#ifdef HAVE_IPV6
			ipv6 = 0;
#endif
			if (NULL != dash)
			{
				if (1 != sscanf(dash + 1, "%hu", &j[4]) || 255 < j[4])
				{
					invalid_range = 1;
					goto next;
				}

				first = ip_dig;
				last = (ip_dig & 0xffffff00) + j[4];
			}
			else if (NULL != slash)
			{
				unsigned int	mask;

				if (1 != sscanf(slash + 1, "%hu", &j[4]) || 16 > j[4] || j[4] > 30)
				{
					invalid_range = 1;
					goto next;
				}

				mask = 0xffffffff << (32 - j[4]);
				first = (ip_dig & mask) + 1;
				last = (ip_dig | ~mask) - 1;
			}
			else
			{
				first = ip_dig;
				last = ip_dig;
			}
		}
		else
		{
			invalid_range = 1;
			goto next;
		}

		if (first > last)
		{
			invalid_range = 1;
			goto next;
		}

		for (i = first; i <= last; i++)
		{
			memset(&dhost, 0, sizeof(dhost));
			host_status = -1;

			now = time(NULL);
#ifdef HAVE_IPV6
			switch (ipv6)
			{
				case 0:
#endif
					zbx_snprintf(ip, sizeof(ip), "%u.%u.%u.%u",
							(i & 0xff000000) >> 24, (i & 0x00ff0000) >> 16,
							(i & 0x0000ff00) >> 8, i & 0x000000ff);
#ifdef HAVE_IPV6
					break;
				case 1:
					j[7] = i;
					ip6_dig2str(j, ip, sizeof(ip));
					break;
			}
#endif
			zabbix_log(LOG_LEVEL_DEBUG, "%s() ip:'%s'", __function_name, ip);

			alarm(CONFIG_TIMEOUT);
			zbx_gethost_by_ip(ip, dns, sizeof(dns));
			alarm(0);

			if (drule->unique_dcheckid)
				process_checks(drule, &dhost, &host_status, ip, dns, 1);
			process_checks(drule, &dhost, &host_status, ip, dns, 0);

			DBbegin();

			if (0 != (daemon_type & ZBX_DAEMON_TYPE_SERVER))
				discovery_update_host(&dhost, ip, host_status, now);
			else if (0 != (daemon_type & ZBX_DAEMON_TYPE_PROXY))
				proxy_update_host(drule, ip, dns, host_status, now);

			DBcommit();

		}
next:
		if (NULL != dash)
			*dash = '-';
		else if (NULL != slash)
			*slash = '/';

		switch (invalid_range)
		{
			case 1:
				zabbix_log(LOG_LEVEL_WARNING, "discovery rule \"%s\": wrong format of IP range \"%s\"",
						drule->name, start);
				break;
			case 2:
				zabbix_log(LOG_LEVEL_WARNING, "discovery rule \"%s\": encountered IP range \"%s\","
						" but IPv6 support not compiled in", drule->name, start);
				break;
		}

		if (NULL != comma)
		{
			*comma = ',';
			start = comma + 1;
		}
		else
			break;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static void	process_discovery(int now)
{
	DB_RESULT	result;
	DB_ROW		row;
	DB_DRULE	drule;

	result = DBselect(
			"select distinct r.druleid,r.iprange,r.name,c.dcheckid"
			" from drules r"
				" left join dchecks c"
					" on c.druleid=r.druleid"
						" and uniq=1"
			" where r.proxy_hostid is null"
				" and r.status=%d"
				" and (r.nextcheck<=%d or r.nextcheck>%d+r.delay)"
				" and " ZBX_SQL_MOD(r.druleid,%d) "=%d"
				DB_NODE,
			DRULE_STATUS_MONITORED,
			now,
			now,
			CONFIG_DISCOVERER_FORKS,
			process_num - 1,
			DBnode_local("r.druleid"));

	while (NULL != (row = DBfetch(result)))
	{
		memset(&drule, 0, sizeof(drule));

		ZBX_STR2UINT64(drule.druleid, row[0]);
		drule.iprange = row[1];
		drule.name = row[2];
		ZBX_DBROW2UINT64(drule.unique_dcheckid, row[3]);

		process_rule(&drule);

		DBexecute("update drules set nextcheck=%d+delay where druleid=" ZBX_FS_UI64,
				now, drule.druleid);
	}
	DBfree_result(result);
}

static int	get_minnextcheck(int now)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		res = FAIL;

	result = DBselect(
			"select count(*),min(nextcheck)"
			" from drules"
			" where proxy_hostid is null"
				" and status=%d"
				" and " ZBX_SQL_MOD(druleid,%d) "=%d"
				DB_NODE,
			DRULE_STATUS_MONITORED, CONFIG_DISCOVERER_FORKS, process_num - 1,
			DBnode_local("druleid"));

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
 * Function: main_discoverer_loop                                             *
 *                                                                            *
 * Purpose: periodically try to find new hosts and services                   *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: executes once per 30 seconds (hardcoded)                         *
 *                                                                            *
 ******************************************************************************/
void	main_discoverer_loop()
{
	int	now, nextcheck, sleeptime;
	double	sec;

	zabbix_log(LOG_LEVEL_DEBUG, "In main_discoverer_loop() process_num:%d", process_num);

	zbx_setproctitle("%s [connecting to the database]", get_process_type_string(process_type));

	DBconnect(ZBX_DB_CONNECT_NORMAL);

	for (;;)
	{
		zbx_setproctitle("%s [performing discovery]", get_process_type_string(process_type));

		now = time(NULL);
		sec = zbx_time();
		process_discovery(now);
		sec = zbx_time() - sec;

		zabbix_log(LOG_LEVEL_DEBUG, "%s #%d spent " ZBX_FS_DBL " seconds while processing rules",
				get_process_type_string(process_type), process_num, sec);

		nextcheck = get_minnextcheck(now);
		sleeptime = calculate_sleeptime(nextcheck, DISCOVERER_DELAY);

		zbx_sleep_loop(sleeptime);
	}
}
