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

#include "daemon.h"
#include "discoverer.h"
#include "../events.h"
#include "../poller/checks_agent.h"
#include "../poller/checks_snmp.h"

#define DISCOVERER_DELAY 600

static zbx_process_t	zbx_process;
int			discoverer_num;

/******************************************************************************
 *                                                                            *
 * Function: add_event                                                        *
 *                                                                            *
 * Purpose: generate UP/DOWN event if required                                *
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
static void	add_event(int object, zbx_uint64_t objectid, int value)
{
	DB_EVENT	event;

	memset(&event, 0, sizeof(DB_EVENT));

	event.eventid		= 0;
	event.source		= EVENT_SOURCE_DISCOVERY;
	event.object		= object;
	event.objectid		= objectid;
	event.clock 		= time(NULL);
	event.value 		= value;
	event.acknowledged 	= 0;

	process_event(&event);
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
static void	update_dservice(DB_DSERVICE *service)
{
	char	value_esc[MAX_STRING_LEN];

	assert(service);

	DBescape_string(service->value, value_esc, sizeof(value_esc));

	DBexecute("update dservices set status=%d,lastup=%d,lastdown=%d,value='%s' where dserviceid=" ZBX_FS_UI64,
			service->status,
			service->lastup,
			service->lastdown,
			value_esc,
			service->dserviceid);
}

/******************************************************************************
 *                                                                            *
 * Function: update_dservice_value                                            *
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
static void	update_dservice_value(DB_DSERVICE *service)
{
	char	value_esc[MAX_STRING_LEN];

	assert(service);

	DBescape_string(service->value, value_esc, sizeof(value_esc));

	DBexecute("update dservices set value='%s' where dserviceid=" ZBX_FS_UI64,
			value_esc,
			service->dserviceid);
}

/******************************************************************************
 *                                                                            *
 * Function: update_dhost                                                      *
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
static void	update_dhost(DB_DHOST *dhost)
{
	assert(dhost);

	DBexecute("update dhosts set status=%d,lastup=%d,lastdown=%d where dhostid=" ZBX_FS_UI64,
			dhost->status,
			dhost->lastup,
			dhost->lastdown,
			dhost->dhostid);
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
static void	register_service(DB_DSERVICE *service, const char *ip, int port, int status)
{
	DB_RESULT	result;
	DB_ROW		row;
	char		key_esc[MAX_STRING_LEN];

	assert(service);
	assert(ip);

	zabbix_log(LOG_LEVEL_DEBUG, "In register_service(ip:%s,port:%d,key:%s)",
			ip,
			port,
			service->key_);

	DBescape_string(service->key_, key_esc, sizeof(key_esc));

	result = DBselect("select dserviceid,status,lastup,lastdown,value"
			" from dservices where dhostid=" ZBX_FS_UI64 " and type=%d and port=%d and key_='%s'",
			service->dhostid,
			service->type,
			port,
			key_esc);

	if (NULL == (row = DBfetch(result)) || DBis_null(row[0]) == SUCCEED) {
		/* Add host only if service is up */
		if (status == DOBJECT_STATUS_UP) {
			zabbix_log(LOG_LEVEL_DEBUG, "New service discovered on port %d", port);

			service->dserviceid	= DBget_maxid("dservices","dserviceid");
			service->port		= port;
			service->status		= DOBJECT_STATUS_DOWN;

			DBexecute("insert into dservices (dhostid,dserviceid,type,port,status,key_) values (" ZBX_FS_UI64 "," ZBX_FS_UI64 ",%d,%d,%d,'%s')",
				service->dhostid,
				service->dserviceid,
				service->type,
				service->port,
				service->status,
				key_esc);
		}
	} else {
		zabbix_log(LOG_LEVEL_DEBUG, "Service is already in database");
		
		service->dserviceid	= zbx_atoui64(row[0]);
		service->port		= port;
		service->status		= atoi(row[1]);
		service->lastup		= atoi(row[2]);
		service->lastdown	= atoi(row[3]);
		strscpy(service->value, row[4]);
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
void	register_host(DB_DHOST *dhost, const char *ip, int status)
{
	DB_RESULT	result;
	DB_ROW		row;
	char		ip_esc[MAX_STRING_LEN];

	assert(dhost);
	assert(ip);

	zabbix_log(LOG_LEVEL_DEBUG, "In register_host(ip:%s)",
			ip);

	DBescape_string(ip, ip_esc, sizeof(ip_esc));

	result = DBselect("select dhostid,ip,status,lastup,lastdown from dhosts"
			" where druleid=" ZBX_FS_UI64 " and ip='%s'" DB_NODE,
			dhost->druleid,
			ip_esc,
			DBnode_local("dhostid"));

	if (NULL == (row = DBfetch(result)) || DBis_null(row[0]) == SUCCEED) {
		/* Add host only if service is up */
		if (status == DOBJECT_STATUS_UP) {
			zabbix_log(LOG_LEVEL_DEBUG, "New host discovered at %s",
					ip);

			dhost->dhostid	= DBget_maxid("dhosts", "dhostid");
			dhost->status	= DOBJECT_STATUS_DOWN;
			strscpy(dhost->ip, ip);

			DBexecute("insert into dhosts (dhostid,druleid,ip) values (" ZBX_FS_UI64 "," ZBX_FS_UI64 ",'%s')",
					dhost->dhostid,
					dhost->druleid,
					ip_esc);
		}
	} else {
		zabbix_log(LOG_LEVEL_DEBUG, "Host at %s is already in database",
				ip);

		dhost->dhostid	= zbx_atoui64(row[0]);
		dhost->status	= atoi(row[2]);
		dhost->lastup	= atoi(row[3]);
		dhost->lastdown	= atoi(row[4]);
		strscpy(dhost->ip, row[1]);
	}
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End register_host()");
}

/******************************************************************************
 *                                                                            *
 * Function: update_service_status                                            *
 *                                                                            *
 * Purpose: process new service status                                        *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void update_service_status(DB_DSERVICE *service, DB_DCHECK *check, int now)
{
	assert(service);
	assert(check);

	/* Update service status */
	if (check->status == DOBJECT_STATUS_UP) {
		if (service->status == DOBJECT_STATUS_DOWN || service->lastup == 0) {
			service->status		= check->status;
			service->lastdown	= 0;
			service->lastup		= now;
			strcpy(service->value, check->value);

			update_dservice(service);

			add_event(EVENT_OBJECT_DSERVICE, service->dserviceid, DOBJECT_STATUS_DISCOVER);
		} else if (0 != strcmp(service->value, check->value)) {
			strcpy(service->value, check->value);

			update_dservice_value(service);
		}
	} else { /* DOBJECT_STATUS_DOWN */
		if (service->status == DOBJECT_STATUS_UP || service->lastdown == 0) {
			service->status		= check->status;
			service->lastdown	= now;
			service->lastup		= 0;

			update_dservice(service);

			add_event(EVENT_OBJECT_DSERVICE, service->dserviceid, DOBJECT_STATUS_LOST);
		}
	}
	add_event(EVENT_OBJECT_DSERVICE, service->dserviceid, check->status);
}

/******************************************************************************
 *                                                                            *
 * Function: update_host_status                                               *
 *                                                                            *
 * Purpose: update new host status                                            *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void update_host_status(DB_DHOST *dhost, int status, int now)
{
	assert(dhost);

	/* Update host status */
	if (status == DOBJECT_STATUS_UP) {
		if (dhost->status == DOBJECT_STATUS_DOWN || dhost->lastup == 0) {
			dhost->status	= status;
			dhost->lastdown	= 0;
			dhost->lastup	= now;

			update_dhost(dhost);

			add_event(EVENT_OBJECT_DHOST, dhost->dhostid, DOBJECT_STATUS_DISCOVER);
		}
	} else { /* DOBJECT_STATUS_DOWN */
		if (dhost->status == DOBJECT_STATUS_UP || dhost->lastdown == 0) {
			dhost->status	= status;
			dhost->lastdown	= now;
			dhost->lastup	= 0;

			update_dhost(dhost);

			add_event(EVENT_OBJECT_DHOST, dhost->dhostid, DOBJECT_STATUS_LOST);
		}
	}
	add_event(EVENT_OBJECT_DHOST, dhost->dhostid, status);
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
void update_service(DB_DHOST *dhost, DB_DCHECK *check, char *ip, int port, int now)
{
	DB_DSERVICE	service;

	assert(dhost);
	assert(check);
	assert(ip);

	zabbix_log(LOG_LEVEL_DEBUG, "In update_service(ip:%s,port:%d,status:%s)",
			ip,
			port,
			(check->status == DOBJECT_STATUS_UP ? "up" : "down"));

	memset(&service, 0, sizeof(service));

	/* Register host if is not registered yet */
	if (dhost->dhostid == 0)
		register_host(dhost, ip, check->status);

	/* Register service if is not registered yet */
	if (dhost->dhostid > 0) {
		service.dhostid = dhost->dhostid;
		service.type	= check->type;
		strscpy(service.key_, check->key_);
		register_service(&service, ip, port, check->status);
	}

	/* Service wasn't registered because we do not add down service */
	if (service.dserviceid == 0)
		return;

	update_service_status(&service, check, now);

	zabbix_log(LOG_LEVEL_DEBUG, "End update_service()");
}

/******************************************************************************
 *                                                                            *
 * Function: proxy_update_service                                             *
 *                                                                            *
 * Purpose: process new service status                                        *
 *                                                                            *
 * Parameters: service - service info                                         *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void proxy_update_service(DB_DCHECK *check, char *ip, int port, int now)
{
	char	ip_esc[MAX_STRING_LEN],
		key_esc[MAX_STRING_LEN],
		value_esc[MAX_STRING_LEN];

	assert(check);
	assert(ip);

	DBescape_string(ip, ip_esc, sizeof(ip_esc));
	DBescape_string(check->key_, key_esc, sizeof(key_esc));
	DBescape_string(check->value, value_esc, sizeof(value_esc));

	DBexecute("insert into proxy_dhistory (clock,druleid,type,ip,port,key_,value,status)"
			" values (%d," ZBX_FS_UI64 ",%d,'%s',%d,'%s','%s',%d)",
			now,
			check->druleid,
			check->type,
			ip_esc,
			port,
			key_esc,
			value_esc,	
			check->status);
}

/******************************************************************************
 *                                                                            *
 * Function: proxy_update_host                                                *
 *                                                                            *
 * Purpose: process new service status                                        *
 *                                                                            *
 * Parameters: service - service info                                         *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void proxy_update_host(zbx_uint64_t druleid, char *ip, int status, int now)
{
	char	ip_esc[MAX_STRING_LEN];

	assert(ip);

	DBescape_string(ip, ip_esc, sizeof(ip_esc));

	DBexecute("insert into proxy_dhistory (clock,druleid,type,ip,status)"
			" values (%d," ZBX_FS_UI64 ",-1,'%s',%d)",
			now,
			druleid,
			ip_esc,
			status);
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

	if (ret == SUCCEED) {
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
static void process_check(DB_DRULE *rule, DB_DHOST *dhost, int *host_status, DB_DCHECK *check, char *ip)
{
	int	port,
		first,
		last,
		now;
	char	*curr_range = NULL,
		*next_range = NULL,
		*last_port = NULL;

	assert(rule);
	assert(dhost);
	assert(host_status);
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

		for (port = first; port <= last; port++) {	
			check->status = SUCCEED == discover_service(check, ip, port) ? DOBJECT_STATUS_UP : DOBJECT_STATUS_DOWN;

			/* Update host status */
			if (*host_status == -1 || check->status == DOBJECT_STATUS_UP)
				*host_status = check->status;

			now = time(NULL);

			switch (zbx_process) {
			case ZBX_PROCESS_SERVER	:
				update_service(dhost, check, ip, port, now);
				break;
			case ZBX_PROCESS_PROXY	:
				proxy_update_service(check, ip, port, now);
				break;
			}
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
	DB_DHOST	dhost;
	int		host_status, now;

	char		ip[MAX_STRING_LEN], prefix[MAX_STRING_LEN];
	unsigned int	j[9], i;
	int		first, last, ipv6;

	char	*curr_range = NULL,
		*next_range = NULL,
		*dash = NULL;
#if defined(HAVE_IPV6)
	char	*colon;
#endif

	assert(rule);

	zabbix_log(LOG_LEVEL_DEBUG, "In process_rule() [name:%s] [range:%s]",
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
			zabbix_log(LOG_LEVEL_WARNING, "Discovery: Wrong format of IP range [%s]",
				rule->iprange);
			continue;
		}

		for (i = first; i <= last; i++) {
			memset(&dhost, 0, sizeof(dhost));
			dhost.druleid	= rule->druleid;
			host_status	= -1;

			now = time(NULL);

			switch(ipv6) {
				case 0 : zbx_snprintf(ip, sizeof(ip), "%d.%d.%d.%d", j[0], j[1], j[2], i); break;
				case 1 : zbx_snprintf(ip, sizeof(ip), "%s%x", prefix, i); break;
			}

			zabbix_log(LOG_LEVEL_DEBUG, "Discovery: process_rule() [IP:%s]", ip);

			result = DBselect("select dcheckid,type,key_,snmp_community,ports"
					" from dchecks where druleid=" ZBX_FS_UI64,
					rule->druleid);

			while (NULL != (row = DBfetch(result))) {
				memset(&check, 0, sizeof(check));

				ZBX_STR2UINT64(check.dcheckid,row[0]);
				check.druleid		= rule->druleid;
				check.type		= atoi(row[1]);
				check.key_		= row[2];
				check.snmp_community	= row[3];
				check.ports		= row[4];
		
				process_check(rule, &dhost, &host_status, &check, ip);
			}
			DBfree_result(result);

			switch (zbx_process) {
			case ZBX_PROCESS_SERVER	:
				if (dhost.dhostid == 0)
					break;

				update_host_status(&dhost, host_status, now);
				break;
			case ZBX_PROCESS_PROXY	:
				proxy_update_host(rule->druleid, ip, host_status, now);
				break;
			}
		}
	}

	zabbix_log( LOG_LEVEL_DEBUG, "End process_rule()");
}

static void process_discovery(int now)
{
	DB_RESULT	result;
	DB_ROW		row;
	DB_DRULE	rule;

	result = DBselect("select druleid,iprange,delay,nextcheck,name,status from drules"
			" where proxy_hostid=0 and status=%d and (nextcheck<=%d or nextcheck>%d+delay)"
			" and " ZBX_SQL_MOD(druleid,%d) "=%d" DB_NODE,
			DRULE_STATUS_MONITORED,
			now,
			now,
			CONFIG_DISCOVERER_FORKS,
			discoverer_num - 1,
			DBnode_local("druleid"));

	while (NULL != (row = DBfetch(result))) {
		memset(&rule, 0, sizeof(DB_DRULE));

		ZBX_STR2UINT64(rule.druleid,row[0]);
		rule.iprange 	= row[1];
		rule.delay	= atoi(row[2]);
		rule.nextcheck	= atoi(row[3]);
		rule.name	= row[4];
		rule.status	= atoi(row[5]);

		process_rule(&rule);

		DBexecute("update drules set nextcheck=%d where druleid=" ZBX_FS_UI64,
				now + rule.delay,
				rule.druleid);
	}
	DBfree_result(result);
}

static int get_minnextcheck(int now)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		res = FAIL;

	result = DBselect("select count(*),min(nextcheck) from drules where proxy_hostid=0 and status=%d"
			" and " ZBX_SQL_MOD(druleid,%d) "=%d" DB_NODE,
			DRULE_STATUS_MONITORED,
			CONFIG_DISCOVERER_FORKS,
			discoverer_num - 1,
			DBnode_local("druleid"));

	row = DBfetch(result);

	if (NULL == row || DBis_null(row[0]) == SUCCEED || DBis_null(row[1]) == SUCCEED)
		zabbix_log(LOG_LEVEL_DEBUG, "No items to update for minnextcheck.");
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
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: executes once per 30 seconds (hardcoded)                         *
 *                                                                            *
 ******************************************************************************/
void main_discoverer_loop(zbx_process_t p, int num)
{
	struct		sigaction phan;
	int		now, nextcheck, sleeptime;
	double		sec;

	zabbix_log( LOG_LEVEL_DEBUG, "In main_discoverer_loop(num:%d)",
			num);

	phan.sa_handler = child_signal_handler;
	sigemptyset(&phan.sa_mask);
	phan.sa_flags = 0;
	sigaction(SIGALRM, &phan, NULL);

	zbx_process	= p;
	discoverer_num	= num;

	DBconnect(ZBX_DB_CONNECT_NORMAL);

	for(;;) {
		now = time(NULL);
		sec = zbx_time();

		process_discovery(now);

		sec = zbx_time() - sec;

		nextcheck = get_minnextcheck(now);

		now = time(NULL);
		zabbix_log(LOG_LEVEL_DEBUG, "Discoverer spent %f seconds while processing rules. Nextcheck: %d Time: %d",
				sec,
				nextcheck,
				now);

		if (FAIL == nextcheck)
			sleeptime = DISCOVERER_DELAY;
		else
			sleeptime = nextcheck - now;

		if (sleeptime > 0) {
			if (sleeptime > DISCOVERER_DELAY)
				sleeptime = DISCOVERER_DELAY;

			zabbix_log(LOG_LEVEL_DEBUG, "Sleeping for %d seconds",
					sleeptime);

			zbx_setproctitle("discoverer [sleeping for %d seconds]", 
					sleeptime);

			sleep( sleeptime );
		} else
			zabbix_log(LOG_LEVEL_DEBUG, "No sleeping" );
	}
}
