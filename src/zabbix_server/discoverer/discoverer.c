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

#define SERVICE_UP	0
#define SERVICE_DOWN	1

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
static void add_host_event(DB_DHOST *host, DB_DSERVICE *service)
{
	DB_EVENT	event;
	int		now;

	zabbix_log(LOG_LEVEL_DEBUG, "In add_host_event()");

	now = time(NULL); 

	memset(&event,0,sizeof(DB_EVENT));

	event.eventid		= 0;
	event.source		= EVENT_SOURCE_DISCOVERY;
	event.object		= EVENT_OBJECT_DHOST;
	event.objectid		= service->dhostid;
	event.clock 		= now;
	event.value 		= host->status;
	event.acknowledged 	= 0;

	process_event(&event);
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

	zabbix_log(LOG_LEVEL_DEBUG, "In add_host_event()");

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
	DBexecute("update dservices set dhostid=" ZBX_FS_UI64 ",type=%d,port=%d,status=%d,lastup=%d,lastdown=%d,eventsent=%d where dserviceid=" ZBX_FS_UI64,
			service->dhostid,
			service->type,
			service->port,
			service->status,
			service->lastup,
			service->lastdown,
			service->eventsent,
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
	DBexecute("update dhosts set druleid=" ZBX_FS_UI64 ",ip='%s',status=%d,lastup=%d,lastdown=%d,eventsent=%d where dhostid=" ZBX_FS_UI64,
			host->druleid,
			host->ip,
			host->status,
			host->lastup,
			host->lastdown,
			host->eventsent,
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
	zbx_uint64_t	dserviceid = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In register_service(ip:%s,port:%d)",
		ip,
		port);

	result = DBselect("select dserviceid,dhostid,type,port,status,lastup,lastdown,eventsent from dservices where dhostid=" ZBX_FS_UI64 " and type=%d and port=%d",
		dhostid,
		check->type,
		port);
	row=DBfetch(result);
	if(!row || DBis_null(row[0])==SUCCEED)
	{
		/* Add host only if service is up */
		if(check->status == SERVICE_UP)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "New service discovered on port %d", port);
			dserviceid = DBget_maxid("dservices","dserviceid");
			DBexecute("insert into dservices (dhostid,dserviceid,type,port,status) values (" ZBX_FS_UI64 "," ZBX_FS_UI64 ",%d,%d,%d)",
				dhostid,
				dserviceid,
				check->type,
				port,
				SERVICE_UP);

			service->dserviceid	= dserviceid;
			service->dhostid	= dhostid;
			service->type		= check->type;
			service->port		= port;
			service->status		= SERVICE_UP;
			service->lastup		= 0;
			service->lastdown	= 0;
			service->eventsent	= 0;
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
		service->eventsent	= atoi(row[7]);
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

	zabbix_log(LOG_LEVEL_DEBUG, "In register_host(ip:%s)",
		ip);

	host->dhostid=0;
	result = DBselect("select dhostid,druleid,ip,status,lastup,lastdown,eventsent from dhosts where ip='%s' and " ZBX_COND_NODEID,
		ip,
		LOCAL_NODE("dhostid"));
	row=DBfetch(result);
	if(!row || DBis_null(row[0])==SUCCEED)
	{
		/* Add host only if service is up */
		if(check->status == SERVICE_UP)
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
			host->eventsent	= 0;
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
		host->eventsent		= atoi(row[6]);
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

	zabbix_log(LOG_LEVEL_DEBUG, "In update_check(ip:%s, port:%d, status:%s)",
		ip,
		port,
		(check->status==SERVICE_UP?"up":"down"));

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
	if(check->status == SERVICE_UP)
	{
		/* Update host status */
		if((host.status == SERVICE_DOWN)||(host.lastup==0 && host.lastdown==0))
		{
			host.status=SERVICE_UP;
			host.lastdown=0;
			host.lastup=now;
			host.eventsent=0;
			update_dhost(&host);
		}
		/* Update service status */
		if((service.status == SERVICE_DOWN)||(service.lastup==0 && service.lastdown==0))
		{
			service.status=SERVICE_UP;
			service.lastdown=0;
			service.lastup=now;
			service.eventsent=0;
			update_dservice(&service);
		}
	}
	/* SERVICE_DOWN */
	else
	{
		if((host.status == SERVICE_UP)||(host.lastup==0 && host.lastdown==0))
		{
			host.status=SERVICE_DOWN;
			host.lastup=now;
			host.lastdown=0;
			host.eventsent=0;
			update_dhost(&host);
		}
		/* Update service status */
		if((service.status == SERVICE_UP)||(service.lastup==0 && service.lastdown==0))
		{
			service.status=SERVICE_DOWN;
			service.lastup=now;
			service.lastdown=0;
			service.eventsent=0;
			update_dservice(&service);
		}
	}

	/* Generating host events */
	if(host.eventsent == 0)
	{
		if(host.status == SERVICE_UP && (host.lastup<=now-rule->upevent))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "Generating host event for %s",
				host.ip);
			host.eventsent=1;

			update_dhost(&host);
			add_host_event(&host,&service);
		}
		if(host.status == SERVICE_DOWN && (host.lastdown<=now-rule->downevent))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "Generating host event for %s",
				host.ip);
			host.eventsent=1;

			update_dhost(&host);
			add_host_event(&host,&service);
		}
	}

	/* Generating service events */
	if(service.eventsent == 0)
	{
		if(service.status == SERVICE_UP && (service.lastup<=now-rule->svcupevent))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "Generating service event for %s",
				host.ip);
			service.eventsent=1;

			update_dservice(&service);
			add_service_event(&service);
		}
		if(service.status == SERVICE_DOWN && (service.lastdown<=now-rule->svcdownevent))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "Generating service event for %s",
				host.ip);
			service.eventsent=1;

			update_dservice(&service);
			add_service_event(&service);
		}
	}
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
static int discover_service(zbx_dservice_type_t type, char *ip, int port)
{
	int		ret = SUCCEED;
	char		key[MAX_STRING_LEN];
	AGENT_RESULT 	value;
	struct	sigaction phan;


	zabbix_log(LOG_LEVEL_DEBUG, "In discover_service(ip:%s, port:%d, type:%d)",
		ip,
		port,
		type);

	init_result(&value);

	switch(type) {
		case SVC_SSH:
			zbx_snprintf(key,sizeof(key)-1,"net.tcp.service[ssh,%s,%d]",
				ip,
				port);
			break;
		case SVC_LDAP:
			zbx_snprintf(key,sizeof(key)-1,"net.tcp.service[ldap,%s,%d]",
				ip,
				port);
			break;
		case SVC_SMTP:
			zbx_snprintf(key,sizeof(key)-1,"net.tcp.service[smtp,%s,%d]",
				ip,
				port);
			break;
		case SVC_FTP:
			zbx_snprintf(key,sizeof(key)-1,"net.tcp.service[ftp,%s,%d]",
				ip,
				port);
			break;
		case SVC_HTTP:
			zbx_snprintf(key,sizeof(key)-1,"net.tcp.service[http,%s,%d]",
				ip,
				port);
			break;
		case SVC_POP:
			zbx_snprintf(key,sizeof(key)-1,"net.tcp.service[pop,%s,%d]",
				ip,
				port);
			break;
		case SVC_NNTP:
			zbx_snprintf(key,sizeof(key)-1,"net.tcp.service[nntp,%s,%d]",
				ip,
				port);
			break;
		case SVC_IMAP:
			zbx_snprintf(key,sizeof(key)-1,"net.tcp.service[imap,%s,%d]",
				ip,
				port);
			break;
		case SVC_TCP:
			zbx_snprintf(key,sizeof(key)-1,"net.tcp.service[tcp,%s,%d]",
				ip,
				port);
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

		if(process(key, 0, &value) == SUCCEED)
		{
			if(GET_UI64_RESULT(&value))
			{
				if(value.ui64 == 0)	ret = FAIL;
			}
			else ret = FAIL;
		}
		else	ret = FAIL;
		alarm(0);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End discover_service()");

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: process_service                                                  *
 *                                                                            *
 * Purpose: check if service is avaiable and update database                  *
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
static void process_check(DB_DRULE *rule, DB_DCHECK *check, char *ip)
{
	int	port;
	char	*s,*c;
	char	tmp[MAX_STRING_LEN];
	int	first,last;

	zabbix_log(LOG_LEVEL_DEBUG, "In process_check(ip:%s, ports:%s, type:%d)",
		ip,
		check->ports,
		check->type);

	zbx_snprintf(tmp,sizeof(tmp)-1,"%s",
		check->ports);

	s=(char *)strtok(tmp,",");
	while(s!=NULL)
	{
		c=strchr(s,'-');
		if(c == NULL)
		{
			first=atoi(s);
			last=first;
		}
		else
		{
			c[0] = 0;
			first=atoi(s);
			last=atoi(c+1);
			c[0] = '-';
		}

		for(port=first;port<=last;port++)
		{	
			check->status = discover_service(check->type,ip,port);
			update_service(rule, check, ip, port);
		}
		s=(char *)strtok(NULL,"\n");
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
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void process_rule(DB_DRULE *rule)
{
	DB_RESULT	result;
	DB_ROW		row;
	DB_DCHECK	check;

	char		ip[MAX_STRING_LEN];
	int		first,last;
	char		*c;

	int		i;

	zabbix_log( LOG_LEVEL_DEBUG, "In process_rule(name:%s)",
		rule->name);

	result = DBselect("select dcheckid,druleid,type,ports from dchecks where druleid=" ZBX_FS_UI64,
		rule->druleid);
	while((row=DBfetch(result)))
	{
		ZBX_STR2UINT64(check.dcheckid,row[0]);
		ZBX_STR2UINT64(check.druleid,row[1]);
		check.type		= atoi(row[2]);
		check.ports		= row[3];

		first=atoi(strrchr(rule->ipfirst,'.')+1);
		last=atoi(strrchr(rule->iplast,'.')+1);

		c = strrchr(rule->ipfirst,'.');
		c[0] = 0;
		for(i=first;i<=last;i++)
		{
			zbx_snprintf(ip,MAX_STRING_LEN-1,"%s.%d",
				rule->ipfirst,
				i);

			process_check(rule, &check, ip);
		}
		c[0] = '.';
	}
	DBfree_result(result);

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

		result = DBselect("select druleid,ipfirst,iplast,delay,nextcheck,name,status,upevent,downevent,svcupevent,svcdownevent from drules where status=%d and nextcheck<=%d and " ZBX_SQL_MOD(druleid,%d) "=%d and" ZBX_COND_NODEID,
			DRULE_STATUS_MONITORED,
			now,
			CONFIG_DISCOVERER_FORKS,
			discoverer_num-1,
			LOCAL_NODE("druleid"));
		while((row=DBfetch(result)))
		{
			ZBX_STR2UINT64(rule.druleid,row[0]);
			rule.ipfirst 		= row[1];
			rule.iplast		= row[2];
			rule.delay		= atoi(row[3]);
			rule.nextcheck		= atoi(row[4]);
			rule.name		= row[5];
			rule.status		= atoi(row[6]);
			rule.upevent		= atoi(row[7]);
			rule.downevent		= atoi(row[8]);
			rule.svcupevent		= atoi(row[9]);
			rule.svcdownevent	= atoi(row[10]);
			
			process_rule(&rule);
		}
		DBfree_result(result);

		zbx_setproctitle("sleeping for 30 sec");

		sleep(30);
	}
	DBclose();
}
