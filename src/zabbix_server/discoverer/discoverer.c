/* 
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/

#include "common.h"

#include "cfg.h"
#include "pid.h"
#include "db.h"
#include "log.h"
#include "sysinfo.h"
#include "zlog.h"

#include "daemon.h"
#include "discoverer.h"
#include "../events.h"
#include "../poller/checks_agent.h"
#include "../poller/checks_snmp.h"

int	discoverer_num;

/******************************************************************************
 *                                                                            *
 * Function: add_host_event                                                   *
 *                                                                            *
 * Purpose: generate host UP/DOWN event if required                           *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void add_host_event(char *ip)
{
	DB_RESULT	result;
	DB_ROW		row;
	DB_EVENT	event;
	int		now;
	int		status;
	zbx_uint64_t	dhostid;

	assert(ip);

	zabbix_log(LOG_LEVEL_DEBUG, "In add_host_event(ip:%s)",
		ip);

	result = DBselect("select status,dhostid from dhosts where ip='%s'",
		ip);
	row=DBfetch(result);
	if(row && DBis_null(row[0])!=SUCCEED)
	{
		now = time(NULL); 
		status = atoi(row[0]);
		ZBX_STR2UINT64(dhostid, row[1]);

		memset(&event,0,sizeof(DB_EVENT));

		event.eventid		= 0;
		event.source		= EVENT_SOURCE_DISCOVERY;
		event.object		= EVENT_OBJECT_DHOST;
		event.objectid		= dhostid;
		event.clock 		= now;
		event.value 		= status;
		event.acknowledged 	= 0;

		process_event(&event);
	}
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End add_host_event()");
}

/******************************************************************************
 *                                                                            *
 * Function: add_service_event                                                *
 *                                                                            *
 * Purpose: generate service UP/DOWN event if required                        *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void add_service_event(DB_DSERVICE *service)
{
	DB_EVENT	event;
	int		now;

	assert(service);

	zabbix_log(LOG_LEVEL_DEBUG, "In add_service_event()");

	now = time(NULL); 

	memset(&event,0,sizeof(DB_EVENT));

	event.eventid		= 0;
	event.source		= EVENT_SOURCE_DISCOVERY;
	event.object		= EVENT_OBJECT_DSERVICE;
	event.objectid		= service->dserviceid;
	event.clock 		= now;
	event.value 		= service->status;
	event.acknowledged 	= 0;

	process_event(&event);

	zabbix_log(LOG_LEVEL_DEBUG, "End add_service_event()");
}

/******************************************************************************
 *                                                                            *
 * Function: update_dservice                                                  *
 *                                                                            *
 * Purpose: update descovered service details                                 *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void update_dservice(DB_DSERVICE *service)
{
	char	key_esc[MAX_STRING_LEN];

	assert(service);

	DBescape_string(service->key_, key_esc, sizeof(key_esc)-1);

	DBexecute("update dservices set dhostid=" ZBX_FS_UI64 ",type=%d,port=%d,status=%d,lastup=%d,lastdown=%d,key_='%s' where dserviceid=" ZBX_FS_UI64,
			service->dhostid,
			service->type,
			service->port,
			service->status,
			service->lastup,
			service->lastdown,
			key_esc,
			service->dserviceid);
}

/******************************************************************************
 *                                                                            *
 * Function: update_host                                                      *
 *                                                                            *
 * Purpose: update descovered host details                                    *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void update_dhost(DB_DHOST *host)
{
	assert(host);

	DBexecute("update dhosts set druleid=" ZBX_FS_UI64 ",ip='%s',status=%d,lastup=%d,lastdown=%d where dhostid=" ZBX_FS_UI64,
			host->druleid,
			host->ip,
			host->status,
			host->lastup,
			host->lastdown,
			host->dhostid);
}

/******************************************************************************
 *                                                                            *
 * Function: register_service                                                 *
 *                                                                            *
 * Purpose: register service if one does not exist                            *
 *                                                                            *
 * Parameters: host ip address                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void register_service(DB_DSERVICE *service,DB_DRULE *rule,DB_DCHECK *check,zbx_uint64_t dhostid,char *ip,int port)
{
	DB_RESULT	result;
	DB_ROW		row;
	char		value_esc[MAX_STRING_LEN];
	char		key_esc[MAX_STRING_LEN];

	assert(service);
	assert(rule);
	assert(check);
	assert(ip);

	zabbix_log(LOG_LEVEL_DEBUG, "In register_service(ip:%s,port:%d)",
		ip,
		port);

	DBescape_string(check->key_, key_esc, sizeof(key_esc)-1);

	result = DBselect("select dserviceid,dhostid,type,port,status,lastup,lastdown,value,key_ from dservices where dhostid=" ZBX_FS_UI64 " and type=%d and port=%d and key_='%s'",
		dhostid,
		check->type,
		port,
		key_esc);
	row=DBfetch(result);
	if(!row || DBis_null(row[0])==SUCCEED)
	{
		/* Add host only if service is up */
		if(check->status == DOBJECT_STATUS_UP)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "New service discovered on port %d", port);

			service->dserviceid	= DBget_maxid("dservices","dserviceid");
			service->dhostid	= dhostid;
			service->type		= check->type;
			service->port		= port;
			service->status		= DOBJECT_STATUS_UP;
			service->lastup		= 0;
			service->lastdown	= 0;
			strscpy(service->value, check->value);
			strscpy(service->key_, check->key_);

			DBescape_string(service->value, value_esc, sizeof(value_esc)-1);
			DBescape_string(service->key_, key_esc, sizeof(key_esc)-1);

			DBexecute("insert into dservices (dhostid,dserviceid,type,port,status,value,key_) values (" ZBX_FS_UI64 "," ZBX_FS_UI64 ",%d,%d,%d,'%s','%s')",
				service->dhostid,
				service->dserviceid,
				check->type,
				service->port,
				service->status,
				value_esc,
				key_esc);
		}
	}
	else
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Service is already in database");

		ZBX_STR2UINT64(service->dserviceid,	row[0]);
		ZBX_STR2UINT64(service->dhostid,	row[1]);
		service->type		= atoi(row[2]);
		service->port		= atoi(row[3]);
		service->status		= atoi(row[4]);
		service->lastup		= atoi(row[5]);
		service->lastdown	= atoi(row[6]);
		strscpy(service->value,row[7]);
		strscpy(service->key_,row[8]);
	}
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End register_service()");
}

/******************************************************************************
 *                                                                            *
 * Function: register_host                                                    *
 *                                                                            *
 * Purpose: register host if one does not exist                               *
 *                                                                            *
 * Parameters: host ip address                                                *
 *                                                                            *
 * Return value: dhostid or 0 if we didn't add host                           *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void register_host(DB_DHOST *host,DB_DCHECK *check, zbx_uint64_t druleid, char *ip)
{
	DB_RESULT	result;
	DB_ROW		row;

	assert(host);
	assert(check);
	assert(ip);

	zabbix_log(LOG_LEVEL_DEBUG, "In register_host(ip:%s)",
		ip);

	host->dhostid=0;
	result = DBselect("select dhostid,druleid,ip,status,lastup,lastdown from dhosts where ip='%s' and " ZBX_COND_NODEID,
		ip,
		LOCAL_NODE("dhostid"));
	row=DBfetch(result);
	if(!row || DBis_null(row[0])==SUCCEED)
	{
		/* Add host only if service is up */
		if(check->status == DOBJECT_STATUS_UP)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "New host discovered at %s",
				ip);
			host->dhostid = DBget_maxid("dhosts","dhostid");
			DBexecute("insert into dhosts (dhostid,druleid,ip) values (" ZBX_FS_UI64 "," ZBX_FS_UI64 ",'%s')",
				host->dhostid,
				druleid,
				ip);
			host->druleid	= druleid;
			strscpy(host->ip,ip);
			host->status	= 0;
			host->lastup	= 0;
			host->lastdown  = 0;
		}
	}
	else
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Host is already in database");
		ZBX_STR2UINT64(host->dhostid,row[0]);
		ZBX_STR2UINT64(host->druleid,row[1]);
		strscpy(host->ip,	row[2]);
		host->status		= atoi(row[3]);
		host->lastup		= atoi(row[4]);
		host->lastdown		= atoi(row[5]);
	}
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End register_host()");
}

/******************************************************************************
 *                                                                            *
 * Function: update_service                                                   *
 *                                                                            *
 * Purpose: process new service status                                        *
 *                                                                            *
 * Parameters: service - service info                                         *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void update_service(DB_DRULE *rule, DB_DCHECK *check, char *ip, int port)
{
	int		now;
	DB_DHOST	host;
	DB_DSERVICE	service;

	assert(rule);
	assert(check);
	assert(ip);

	zabbix_log(LOG_LEVEL_DEBUG, "In update_service(ip:%s, port:%d, status:%s)",
		ip,
		port,
		(check->status==DOBJECT_STATUS_UP?"up":"down"));

	service.dserviceid=0;

	/* Register host if is not registered yet */
	register_host(&host,check,rule->druleid,ip);

	if(host.dhostid>0)
	{
		/* Register service if is not registered yet */
/*		dserviceid = register_service(rule,check,host.dhostid,ip,port);*/
		register_service(&service,rule,check,host.dhostid,ip,port);
	}

	if(service.dserviceid == 0)
	{
		/* Service wasn't registered because we do not add down service */
		return;
	}

	now = time(NULL);
	if(check->status == DOBJECT_STATUS_UP)
	{
		/* Update host status */
		if(host.status == DOBJECT_STATUS_DOWN || host.lastup==0)
		{
			host.status=DOBJECT_STATUS_UP;
			host.lastdown=0;
			host.lastup=now;
			update_dhost(&host);
		}
		/* Update service status */
		if(service.status == DOBJECT_STATUS_DOWN || service.lastup==0)
		{
			service.status=DOBJECT_STATUS_UP;
			service.lastdown=0;
			service.lastup=now;
			update_dservice(&service);
		}
	}
	/* DOBJECT_STATUS_DOWN */
	else
	{
		if(host.status == DOBJECT_STATUS_UP || host.lastdown==0)
		{
			host.status=DOBJECT_STATUS_DOWN;
			host.lastdown=now;
			host.lastup=0;
			update_dhost(&host);
		}
		/* Update service status */
		if(service.status == DOBJECT_STATUS_UP || service.lastdown==0)
		{
			service.status=DOBJECT_STATUS_DOWN;
			service.lastdown=now;
			service.lastup=0;
			update_dservice(&service);
		}
	}

	add_service_event(&service);
}

/******************************************************************************
 *                                                                            *
 * Function: discover_service                                                 *
 *                                                                            *
 * Purpose: check if service is avaiable and update database                  *
 *                                                                            *
 * Parameters: service typ,e ip address, port number                          *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int discover_service(DB_DCHECK *check, char *ip, int port)
{
	int		ret = SUCCEED;
	char		key[MAX_STRING_LEN];
	AGENT_RESULT 	value;
	DB_ITEM		item;
	struct	sigaction phan;

	assert(check);
	assert(ip);

	zabbix_log(LOG_LEVEL_DEBUG, "In discover_service(ip:%s, port:%d, type:%d)",
		ip,
		port,
		check->type);

	init_result(&value);

	switch(check->type) {
		case SVC_SSH:
			zbx_snprintf(key,sizeof(key),"net.tcp.service[ssh,%s,%d]",
				ip,
				port);
			break;
		case SVC_LDAP:
			zbx_snprintf(key,sizeof(key),"net.tcp.service[ldap,%s,%d]",
				ip,
				port);
			break;
		case SVC_SMTP:
			zbx_snprintf(key,sizeof(key),"net.tcp.service[smtp,%s,%d]",
				ip,
				port);
			break;
		case SVC_FTP:
			zbx_snprintf(key,sizeof(key),"net.tcp.service[ftp,%s,%d]",
				ip,
				port);
			break;
		case SVC_HTTP:
			zbx_snprintf(key,sizeof(key),"net.tcp.service[http,%s,%d]",
				ip,
				port);
			break;
		case SVC_POP:
			zbx_snprintf(key,sizeof(key),"net.tcp.service[pop,%s,%d]",
				ip,
				port);
			break;
		case SVC_NNTP:
			zbx_snprintf(key,sizeof(key),"net.tcp.service[nntp,%s,%d]",
				ip,
				port);
			break;
		case SVC_IMAP:
			zbx_snprintf(key,sizeof(key),"net.tcp.service[imap,%s,%d]",
				ip,
				port);
			break;
		case SVC_TCP:
			zbx_snprintf(key,sizeof(key),"net.tcp.service[tcp,%s,%d]",
				ip,
				port);
			break;
		case SVC_AGENT:
		case SVC_SNMPv1:
		case SVC_SNMPv2c:
			break;
		default:
			ret = FAIL;
			break;
	}

	if(ret == SUCCEED)
	{
		phan.sa_handler = &child_signal_handler;
		sigemptyset(&phan.sa_mask);
		phan.sa_flags = 0;
		sigaction(SIGALRM, &phan, NULL);
		alarm(10);

		switch(check->type) {
			/* Agent and SNMP checks */
			case SVC_AGENT:
			case SVC_SNMPv1:
			case SVC_SNMPv2c:
				memset(&item,0,sizeof(DB_ITEM));
				strscpy(item.key,check->key_);
				item.host_name	= ip;
				item.host_ip	= ip;
				item.host_dns	= ip;
				item.useip	= 1;
				item.port	= port;

				item.value_type	= ITEM_VALUE_TYPE_STR;

				if(check->type == SVC_SNMPv1)
				{
					item.type = ITEM_TYPE_SNMPv1;
				}
				else
				{
					item.type = ITEM_TYPE_SNMPv2c;
				}

				item.snmp_oid		= check->key_;
				item.snmp_community	= check->snmp_community;
				item.snmp_port		= port;

				if(check->type==SVC_AGENT)
				{
					if(SUCCEED == get_value_agent(&item, &value))
					{
						if(GET_STR_RESULT(&value))
						{
							strscpy(check->value, value.str);
						}
						else ret = FAIL;
					}
					else
					{
						ret = FAIL;
					}
				}
				else
#ifdef HAVE_SNMP
				{
					if(SUCCEED == get_value_snmp(&item, &value))
					{
						if(GET_STR_RESULT(&value))
						{
							strscpy(check->value, value.str);
						}
						else ret = FAIL;
					}
					else
					{
						ret = FAIL;
					}
				}
#else
				{
					ret = FAIL;
				}
#endif
				break;
			/* Simple checks */
			default:
				if(process(key, 0, &value) == SUCCEED)
				{
					if(GET_UI64_RESULT(&value))
					{
						if(value.ui64 == 0)	ret = FAIL;
					}
					else ret = FAIL;
				}
				else	ret = FAIL;
				break;
		}
		alarm(0);
	}
	free_result(&value);

	zabbix_log(LOG_LEVEL_DEBUG, "End discover_service()");

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: process_check                                                    *
 *                                                                            *
 * Purpose: check if service is avaiable and update database                  *
 *                                                                            *
 * Parameters: service - service info                                         *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void process_check(DB_DRULE *rule, DB_DCHECK *check, char *ip)
{
	int	port,
		first,
		last;

	char	*curr_range = NULL,
		*next_range = NULL,
		*last_port = NULL;

	assert(rule);
	assert(check);
	assert(ip);

	zabbix_log(LOG_LEVEL_DEBUG, "In process_check(ip:%s, ports:%s, type:%d)",
		ip,
		check->ports,
		check->type);

	for ( curr_range = check->ports; curr_range; curr_range = next_range )
	{ /* split by ',' */
		if ( (next_range = strchr(curr_range, ',')) )
		{
			*next_range = '\0';
		}

		if ( (last_port = strchr(curr_range, '-')) )
		{ /* split by '-' */
			*last_port	= '\0';
			first		= atoi(curr_range);
			last		= atoi(last_port);
			*last_port	= '-';
		}
		else
		{
			first = last	= atoi(curr_range);
		}

		if ( next_range ) 
		{
			*next_range = ',';
			next_range++;
		}

		for ( port = first; port <= last; port++)
		{	
			check->status = discover_service(check,ip,port);
			update_service(rule, check, ip, port);
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End process_check()");
}

/******************************************************************************
 *                                                                            *
 * Function: process_rule                                                     *
 *                                                                            *
 * Purpose: process single discovery rule                                     *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void process_rule(DB_DRULE *rule)
{
	DB_RESULT	result;
	DB_ROW		row;
	DB_DCHECK	check;

	char		ip[MAX_STRING_LEN], prefix[MAX_STRING_LEN];
	unsigned int	j[9], i;
	int		first, last, ipv6;

	char	*curr_range = NULL,
		*next_range = NULL,
		*dash = NULL, *colon;

	assert(rule);

	zabbix_log(LOG_LEVEL_DEBUG, "In process_rule(name:%s,range:%s)",
		rule->name,
		rule->iprange);

	for ( curr_range = rule->iprange; curr_range; curr_range = next_range )
	{ /* split by ',' */
		if ( NULL != (next_range = strchr(curr_range, ',')) )
		{
			next_range[0] = '\0';
		}

		if ( NULL != (dash = strchr(curr_range, '-')) )
		{
			dash[0] = '\0';
		}

		first = last = -1;
#if defined(HAVE_IPV6)
		if ( SUCCEED == expand_ipv6(curr_range, ip, sizeof(ip)) )
		{
			ipv6 = 1;
			if( sscanf(ip, "%x:%x:%x:%x:%x:%x:%x:%x", &j[0], &j[1], &j[2], &j[3], &j[4], &j[5], &j[6], &j[7]) == 8 )
			{
				first = j[7];

				zbx_strlcpy( prefix, curr_range, sizeof(prefix) );
				if( NULL != (colon = strrchr(prefix, ':')) )
				{
					( colon + 1 )[0] = '\0';
				}
			}

			if( dash != NULL )
			{
				if( sscanf(dash + 1, "%x", &j[8]) == 1 )
				{
					last = j[8];
				}
			}
			else
			{
				last = first;
			}
		}
		else
		{
#endif /* HAVE_IPV6 */
			ipv6  = 0;
			if( sscanf(curr_range, "%d.%d.%d.%d", &j[0], &j[1], &j[2], &j[3]) == 4 )
			{
				first = j[3];
			}

			if( dash != NULL )
			{
				if( sscanf(dash + 1, "%d", &j[4]) == 1 )
				{
					last = j[4];
				}
			}
			else
			{
				last = first;
			}
#if defined(HAVE_IPV6)
		}
#endif /* HAVE_IPV6 */

		if( dash )
		{
			dash[0] = '-';
			dash = NULL;
		}

		if ( next_range ) 
		{
			next_range[0] = ',';
			next_range ++;
		}

		if( first < 0 || last < 0 )
		{
			zabbix_log(LOG_LEVEL_WARNING, "Wrong format of IP range [%s]",
				rule->iprange);
			continue;
		}

		for ( i = first; i <= last; i++ )
		{
			switch( ipv6 )
			{
				case 0 : zbx_snprintf(ip, sizeof(ip), "%d.%d.%d.%d", j[0], j[1], j[2], i); break;
				case 1 : zbx_snprintf(ip, sizeof(ip), "%s%x", prefix, i); break;
			}

			zabbix_log(LOG_LEVEL_DEBUG, "Discovery: process_rule() [first %d] [last %d] [IP %s]", prefix, first, last, ip);

			result = DBselect("select dcheckid,druleid,type,key_,snmp_community,ports from dchecks where druleid=" ZBX_FS_UI64,
				rule->druleid);
			while((row=DBfetch(result)))
			{
				memset(&check, 0, sizeof(DB_RESULT));

				ZBX_STR2UINT64(check.dcheckid,row[0]);
				ZBX_STR2UINT64(check.druleid,row[1]);
				check.type		= atoi(row[2]);
				check.key_		= row[3];
				check.snmp_community	= row[4];
				check.ports		= row[5];
		
				process_check(rule, &check, ip);
			}
			DBfree_result(result);

			add_host_event(ip);
		}
	}

	zabbix_log( LOG_LEVEL_DEBUG, "End process_rule()");
}

/******************************************************************************
 *                                                                            *
 * Function: main_discoverer_loop                                             *
 *                                                                            *
 * Purpose: periodically try to find new hosts and services                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: executes once per 30 seconds (hardcoded)                         *
 *                                                                            *
 ******************************************************************************/
void main_discoverer_loop(int num)
{
	int	now;

	DB_RESULT	result;
	DB_ROW		row;
	DB_DRULE	rule;

	zabbix_log( LOG_LEVEL_DEBUG, "In main_discoverer_loop(num:%d)",
		num);

	discoverer_num = num;

	DBconnect(ZBX_DB_CONNECT_NORMAL);

	for(;;)
	{
		now=time(NULL);

		result = DBselect("select druleid,iprange,delay,nextcheck,name,status from drules where status=%d and nextcheck<=%d and " ZBX_SQL_MOD(druleid,%d) "=%d and" ZBX_COND_NODEID,
			DRULE_STATUS_MONITORED,
			now,
			CONFIG_DISCOVERER_FORKS,
			discoverer_num-1,
			LOCAL_NODE("druleid"));
		while((row=DBfetch(result)))
		{
			memset(&rule, 0, sizeof(DB_DRULE));

			ZBX_STR2UINT64(rule.druleid,row[0]);
			rule.iprange 		= row[1];
			rule.delay		= atoi(row[2]);
			rule.nextcheck		= atoi(row[3]);
			rule.name		= row[4];
			rule.status		= atoi(row[5]);
			
			process_rule(&rule);
		}
		DBfree_result(result);

		zbx_setproctitle("sleeping for 30 sec");

		sleep(30);
	}
	DBclose();
}
