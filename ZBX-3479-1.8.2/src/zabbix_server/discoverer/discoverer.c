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
#include "zbxicmpping.h"

#include "daemon.h"
#include "discoverer.h"
#include "../events.h"
#include "../poller/checks_agent.h"
#include "../poller/checks_snmp.h"

static zbx_process_t	zbx_process;
int			discoverer_num;

/******************************************************************************
 *                                                                            *
 * Functions: add_event                                                       *
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
static void	add_event(int object, zbx_uint64_t objectid, int now, int value)
{
	DB_EVENT	event;

	memset(&event, 0, sizeof(DB_EVENT));

	event.eventid		= 0;
	event.source		= EVENT_SOURCE_DISCOVERY;
	event.object		= object;
	event.objectid		= objectid;
	event.clock 		= now;
	event.value 		= value;
	event.acknowledged 	= 0;

	process_event(&event);
}

/******************************************************************************
 *                                                                            *
 * Function: update_dservice                                                  *
 *                                                                            *
 * Purpose: update discovered service details                                 *
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
	char	*value_esc;

	value_esc = DBdyn_escape_string_len(service->value, DSERVICE_VALUE_LEN);

	DBexecute("update dservices set status=%d,lastup=%d,lastdown=%d,value='%s' where dserviceid=" ZBX_FS_UI64,
			service->status,
			service->lastup,
			service->lastdown,
			value_esc,
			service->dserviceid);

	zbx_free(value_esc);
}

/******************************************************************************
 *                                                                            *
 * Function: update_dservice_value                                            *
 *                                                                            *
 * Purpose: update discovered service details                                 *
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
	char	*value_esc;

	value_esc = DBdyn_escape_string_len(service->value, DSERVICE_VALUE_LEN);

	DBexecute("update dservices set value='%s' where dserviceid=" ZBX_FS_UI64,
			value_esc,
			service->dserviceid);

	zbx_free(value_esc);
}

/******************************************************************************
 *                                                                            *
 * Function: update_dhost                                                     *
 *                                                                            *
 * Purpose: update discovered host details                                    *
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
	DBexecute("update dhosts set status=%d,lastup=%d,lastdown=%d where dhostid=" ZBX_FS_UI64,
			dhost->status,
			dhost->lastup,
			dhost->lastdown,
			dhost->dhostid);
}

/******************************************************************************
 *                                                                            *
 * Function: separate_host                                                    *
 *                                                                            *
 * Purpose: separate multiple-IP hosts                                        *
 *                                                                            *
 * Parameters: host ip address                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	separate_host(DB_DRULE *drule, DB_DHOST *dhost, const char *ip)
{
	const char	*__function_name = "separate_host";
	DB_RESULT	result;
	DB_ROW		row;
	char		*ip_esc, *sql = NULL;
	zbx_uint64_t	dhostid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() ip:'%s'", __function_name, ip);

	ip_esc = DBdyn_escape_string_len(ip, DHOST_IP_LEN);
	sql = zbx_dsprintf(sql,
			"select dserviceid"
			" from dservices"
			" where dhostid=" ZBX_FS_UI64
				" and ip<>'%s'",
			dhost->dhostid,
			ip_esc);

	result = DBselectN(sql, 1);

	if (NULL != (row = DBfetch(result)))
	{
		dhostid = DBget_maxid("dhosts", "dhostid");

		DBexecute("insert into dhosts (dhostid,druleid)"
				" values (" ZBX_FS_UI64 "," ZBX_FS_UI64 ")",
				dhostid,
				drule->druleid);

		DBexecute("update dservices"
				" set dhostid=" ZBX_FS_UI64
				" where dhostid=" ZBX_FS_UI64
					" and ip='%s'",
				dhostid,
				dhost->dhostid,
				ip_esc);

		dhost->dhostid	= dhostid;
		dhost->status	= DOBJECT_STATUS_DOWN;
		dhost->lastup	= 0;
		dhost->lastdown	= 0;
	}
	DBfree_result(result);

	zbx_free(sql);
	zbx_free(ip_esc);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
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
static void	register_service(DB_DRULE *drule, DB_DCHECK *dcheck, DB_DHOST *dhost, DB_DSERVICE *dservice,
		const char *ip, int port, int status, int now)
{
	const char	*__function_name = "register_service";
	DB_RESULT	result;
	DB_ROW		row;
	char		*key_esc, *ip_esc;
	zbx_uint64_t	dhostid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() ip:'%s' port:%d key:'%s'", __function_name, ip, port, dcheck->key_);

	key_esc = DBdyn_escape_string_len(dcheck->key_, DSERVICE_KEY_LEN);
	ip_esc = DBdyn_escape_string_len(ip, HOST_IP_LEN);

	result = DBselect(
			"select dserviceid,dhostid,status,lastup,lastdown,value"
			" from dservices"
			" where dcheckid=" ZBX_FS_UI64
				" and type=%d"
				" and key_='%s'"
				" and ip='%s'"
				" and port=%d",
			dcheck->dcheckid,
			dcheck->type,
			key_esc,
			ip_esc,
			port);

	if (NULL == (row = DBfetch(result)))
	{
		/* Add host only if service is up */
		if (status == DOBJECT_STATUS_UP)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "New service discovered on port %d", port);

			dservice->dserviceid	= DBget_maxid("dservices", "dserviceid");
			dservice->status	= DOBJECT_STATUS_DOWN;

			DBexecute("insert into dservices (dserviceid,dhostid,dcheckid,type,key_,ip,port,status)"
					" values (" ZBX_FS_UI64 "," ZBX_FS_UI64 "," ZBX_FS_UI64 ",%d,'%s','%s',%d,%d)",
					dservice->dserviceid,
					dhost->dhostid,
					dcheck->dcheckid,
					dcheck->type,
					key_esc,
					ip_esc,
					port,
					dservice->status);
		}
	}
	else
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Service is already in database");

		ZBX_STR2UINT64(dservice->dserviceid, row[0]);
		ZBX_STR2UINT64(dhostid, row[1]);
		dservice->status	= atoi(row[2]);
		dservice->lastup	= atoi(row[3]);
		dservice->lastdown	= atoi(row[4]);
		strscpy(dservice->value, row[5]);

		if (dhostid != dhost->dhostid)
		{
			DBexecute("update dservices"
					" set dhostid=" ZBX_FS_UI64
					" where dhostid=" ZBX_FS_UI64,
					dhost->dhostid,
					dhostid);
			DBexecute("delete from dhosts"
					" where dhostid=" ZBX_FS_UI64,
					dhostid);
		}

	}
	DBfree_result(result);

	zbx_free(ip_esc);
	zbx_free(key_esc);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static DB_RESULT	get_dhost_by_ip(zbx_uint64_t druleid, const char *ip)
{
	DB_RESULT	result;
	char		*ip_esc;

	ip_esc = DBdyn_escape_string_len(ip, DHOST_IP_LEN);

	result = DBselect(
			"select dh.dhostid,dh.status,dh.lastup,dh.lastdown"
			" from dhosts dh,dservices ds"
			" where ds.dhostid=dh.dhostid"
				" and dh.druleid=" ZBX_FS_UI64
				" and ds.ip='%s'"
			" order by dh.dhostid",
			druleid,
			ip_esc);

	zbx_free(ip_esc);

	return result;
}

static DB_RESULT	get_dhost_by_value(zbx_uint64_t dcheckid, const char *value)
{
	DB_RESULT	result;
	char		*value_esc;

	value_esc = DBdyn_escape_string_len(value, DSERVICE_VALUE_LEN);

	result = DBselect(
			"select dh.dhostid,dh.status,dh.lastup,dh.lastdown"
			" from dhosts dh,dservices ds"
			" where ds.dhostid=dh.dhostid"
				" and ds.dcheckid=" ZBX_FS_UI64
				" and ds.value='%s'"
			" order by dh.dhostid",
			dcheckid,
			value_esc);

	zbx_free(value_esc);

	return result;
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
static void	register_host(DB_DRULE *drule, DB_DCHECK *dcheck, DB_DHOST *dhost, const char *ip, int status, const char *value)
{
	const char	*__function_name = "register_host";
	DB_RESULT	result;
	DB_ROW		row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (drule->unique_dcheckid == dcheck->dcheckid)
	{
		result = get_dhost_by_value(dcheck->dcheckid, value);

		if (NULL == (row = DBfetch(result)))
		{
			DBfree_result(result);

			result = get_dhost_by_ip(drule->druleid, ip);
			row = DBfetch(result);
		}
	}
	else
	{
		result = get_dhost_by_ip(drule->druleid, ip);
		row = DBfetch(result);
	}

	if (NULL == row)
	{
		/* Add host only if service is up */
		if (status == DOBJECT_STATUS_UP)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "New host discovered at %s",
					ip);

			dhost->dhostid	= DBget_maxid("dhosts", "dhostid");
			dhost->status	= DOBJECT_STATUS_DOWN;
			dhost->lastup	= 0;
			dhost->lastdown	= 0;

			DBexecute("insert into dhosts (dhostid,druleid)"
					" values (" ZBX_FS_UI64 "," ZBX_FS_UI64 ")",
					dhost->dhostid,
					drule->druleid);
		}
	}
	else
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Host at %s is already in database",
				ip);

		ZBX_STR2UINT64(dhost->dhostid, row[0]);
		dhost->status	= atoi(row[1]);
		dhost->lastup	= atoi(row[2]);
		dhost->lastdown	= atoi(row[3]);

		if (0 == drule->unique_dcheckid)
			separate_host(drule, dhost, ip);
	}
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
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
static void update_service_status(DB_DSERVICE *dservice, int status, const char *value, int now)
{
	/* Update service status */
	if (status == DOBJECT_STATUS_UP)
	{
		if (dservice->status == DOBJECT_STATUS_DOWN || dservice->lastup == 0)
		{
			dservice->status	= status;
			dservice->lastdown	= 0;
			dservice->lastup	= now;

			strcpy(dservice->value, value);
			update_dservice(dservice);
			add_event(EVENT_OBJECT_DSERVICE, dservice->dserviceid, now, DOBJECT_STATUS_DISCOVER);
		}
		else if (0 != strcmp(dservice->value, value))
		{
			strcpy(dservice->value, value);
			update_dservice_value(dservice);
		}
	}
	else	/* DOBJECT_STATUS_DOWN */
	{
		if (dservice->status == DOBJECT_STATUS_UP || dservice->lastdown == 0)
		{
			dservice->status	= status;
			dservice->lastdown	= now;
			dservice->lastup	= 0;

			update_dservice(dservice);
			add_event(EVENT_OBJECT_DSERVICE, dservice->dserviceid, now, DOBJECT_STATUS_LOST);
		}
	}
	add_event(EVENT_OBJECT_DSERVICE, dservice->dserviceid, now, status);
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
void	update_service(DB_DRULE *drule, DB_DCHECK *dcheck, DB_DHOST *dhost, char *ip, int port, int status, const char *value, int now)
{
	const char	*__function_name = "update_service";
	DB_DSERVICE	dservice;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() ip:'%s' port:%d status:%d", __function_name, ip, port, status);

	memset(&dservice, 0, sizeof(dservice));

	/* Register host if is not registered yet */
	if (0 == dhost->dhostid)
		register_host(drule, dcheck, dhost, ip, status, value);

/*	if (0 != dhost->dhostid && dcheck->dcheckid == drule->unique_dcheckid)
		join_host(drule, dhost, value);
*/
	/* Register service if is not registered yet */
	if (0 != dhost->dhostid)
		register_service(drule, dcheck, dhost, &dservice, ip, port, status, now);

	/* Service wasn't registered because we do not add down service */
	if (0 != dservice.dserviceid)
		update_service_status(&dservice, status, value, now);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
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
static void	update_host_status(DB_DHOST *dhost, int status, int now)
{
	/* Update host status */
	if (status == DOBJECT_STATUS_UP) {
		if (dhost->status == DOBJECT_STATUS_DOWN || dhost->lastup == 0) {
			dhost->status	= status;
			dhost->lastdown	= 0;
			dhost->lastup	= now;

			update_dhost(dhost);
			add_event(EVENT_OBJECT_DHOST, dhost->dhostid, now, DOBJECT_STATUS_DISCOVER);
		}
	} else { /* DOBJECT_STATUS_DOWN */
		if (dhost->status == DOBJECT_STATUS_UP || dhost->lastdown == 0) {
			dhost->status	= status;
			dhost->lastdown	= now;
			dhost->lastup	= 0;

			update_dhost(dhost);
			add_event(EVENT_OBJECT_DHOST, dhost->dhostid, now, DOBJECT_STATUS_LOST);
		}
	}
	add_event(EVENT_OBJECT_DHOST, dhost->dhostid, now, status);
}

/******************************************************************************
 *                                                                            *
 * Function: update_host                                                      *
 *                                                                            *
 * Purpose: process new host status                                           *
 *                                                                            *
 * Parameters: host - host info                                               *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	update_host(DB_DHOST *dhost, const char *ip, int status, int now)
{
	const char	*__function_name = "update_host";

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (0 != dhost->dhostid)
		update_host_status(dhost, status, now);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
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
static void proxy_update_service(DB_DRULE *drule, DB_DCHECK *dcheck, char *ip, int port, int status, const char *value, int now)
{
	char	*ip_esc, *key_esc, *value_esc;

	ip_esc = DBdyn_escape_string_len(ip, PROXY_DHISTORY_IP_LEN);
	key_esc = DBdyn_escape_string_len(dcheck->key_, PROXY_DHISTORY_KEY_LEN);
	value_esc = DBdyn_escape_string_len(value, PROXY_DHISTORY_VALUE_LEN);

	DBexecute("insert into proxy_dhistory (clock,druleid,dcheckid,type,ip,port,key_,value,status)"
			" values (%d," ZBX_FS_UI64 "," ZBX_FS_UI64 ",%d,'%s',%d,'%s','%s',%d)",
			now,
			drule->druleid,
			dcheck->dcheckid,
			dcheck->type,
			ip_esc,
			port,
			key_esc,
			value_esc,
			status);

	zbx_free(value_esc);
	zbx_free(key_esc);
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
 * Return value:                                                              *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void proxy_update_host(DB_DRULE *drule, char *ip, int status, int now)
{
	char	*ip_esc;

	ip_esc = DBdyn_escape_string_len(ip, PROXY_DHISTORY_IP_LEN);

	DBexecute("insert into proxy_dhistory (clock,druleid,type,ip,status)"
			" values (%d," ZBX_FS_UI64 ",-1,'%s',%d)",
			now,
			drule->druleid,
			ip_esc,
			status);

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
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int discover_service(DB_DCHECK *dcheck, char *ip, int port, char *value)
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

	switch (dcheck->type) {
		case SVC_SSH:	service = "ssh"; break;
		case SVC_LDAP:	service = "ldap"; break;
		case SVC_SMTP:	service = "smtp"; break;
		case SVC_FTP:	service = "ftp"; break;
		case SVC_HTTP:	service = "http"; break;
		case SVC_POP:	service = "pop"; break;
		case SVC_NNTP:	service = "nntp"; break;
		case SVC_IMAP:	service = "imap"; break;
		case SVC_TCP:	service = "tcp"; break;
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

	if (ret == SUCCEED) {
		alarm(10);

		switch(dcheck->type) {
			/* Simple checks */
			case SVC_SSH:
			case SVC_LDAP:
			case SVC_SMTP:
			case SVC_FTP:
			case SVC_HTTP:
			case SVC_POP:
			case SVC_NNTP:
			case SVC_IMAP:
			case SVC_TCP:
				zbx_snprintf(key, sizeof(key), "net.tcp.service[%s,%s,%d]", service, ip, port);

				if (SUCCEED == process(key, 0, &result))
				{
					if (GET_UI64_RESULT(&result))
					{
						if (result.ui64 == 0)
							ret = FAIL;
					}
					else
						ret = FAIL;
				}
				else
					ret = FAIL;
				break;
			/* Agent and SNMP checks */
			case SVC_AGENT:
			case SVC_SNMPv1:
			case SVC_SNMPv2c:
			case SVC_SNMPv3:
				memset(&item, 0, sizeof(DC_ITEM));
				zbx_strlcpy(item.key_orig, dcheck->key_, sizeof(item.key_orig));
				item.key = item.key_orig;
				zbx_strlcpy(item.host.ip, ip, sizeof(item.host.ip));
				item.host.useip	= 1;
				item.host.port	= port;

				item.value_type	= ITEM_VALUE_TYPE_STR;

				switch (dcheck->type) {
				case SVC_SNMPv1:	item.type = ITEM_TYPE_SNMPv1; break;
				case SVC_SNMPv2c:	item.type = ITEM_TYPE_SNMPv2c; break;
				case SVC_SNMPv3:	item.type = ITEM_TYPE_SNMPv3; break;
				default:		item.type = ITEM_TYPE_ZABBIX; break;
				}

				if (dcheck->type == SVC_AGENT)
				{
					if(SUCCEED == get_value_agent(&item, &result))
					{
						if (GET_STR_RESULT(&result))
							zbx_strlcpy(value, result.str, DSERVICE_VALUE_LEN_MAX);
						else
							ret = FAIL;
					}
					else
						ret = FAIL;
				}
				else
#ifdef HAVE_SNMP
				{
					zbx_strlcpy(item.snmp_oid, dcheck->key_, sizeof(item.snmp_oid));
					zbx_strlcpy(item.snmp_community, dcheck->snmp_community,
							sizeof(item.snmp_community));
					zbx_strlcpy(item.snmpv3_securityname, dcheck->snmpv3_securityname,
							sizeof(item.snmpv3_securityname));
					item.snmpv3_securitylevel	= dcheck->snmpv3_securitylevel;
					zbx_strlcpy(item.snmpv3_authpassphrase, dcheck->snmpv3_authpassphrase,
							sizeof(item.snmpv3_authpassphrase));
					zbx_strlcpy(item.snmpv3_privpassphrase, dcheck->snmpv3_privpassphrase,
							sizeof(item.snmpv3_privpassphrase));
					item.snmp_port			= port;

					if(SUCCEED == get_value_snmp(&item, &result))
					{
						if (GET_STR_RESULT(&result))
							zbx_strlcpy(value, result.str, DSERVICE_VALUE_LEN_MAX);
						else
							ret = FAIL;
					}
					else
						ret = FAIL;
				}
#else
					ret = FAIL;
#endif

				if (FAIL == ret && GET_MSG_RESULT(&result))
					zabbix_log(LOG_LEVEL_DEBUG, "Discovery: Item [%s] error: %s",
							item.key, result.msg);
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
 * Return value:                                                              *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void process_check(DB_DRULE *drule, DB_DCHECK *dcheck, DB_DHOST *dhost, int *host_status, char *ip)
{
	const char	*__function_name = "process_check";
	int		port, first, last, now;
	char		*curr_range, *next_range, *last_port;
	int		status;
	char		value[DSERVICE_VALUE_LEN_MAX];

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	for (curr_range = dcheck->ports; curr_range; curr_range = next_range)
	{	/* split by ',' */
		if (NULL != (next_range = strchr(curr_range, ',')))
			*next_range = '\0';

		if (NULL != (last_port = strchr(curr_range, '-')))
		{	/* split by '-' */
			*last_port	= '\0';
			first		= atoi(curr_range);
			last		= atoi(last_port + 1);
			*last_port	= '-';
		}
		else
			first = last	= atoi(curr_range);

		if (NULL != next_range)
		{
			*next_range = ',';
			next_range++;
		}

		for (port = first; port <= last; port++)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s() port:%d", __function_name, port);

			status = (SUCCEED == discover_service(dcheck, ip, port, value)) ? DOBJECT_STATUS_UP : DOBJECT_STATUS_DOWN;

			/* Update host status */
			if (*host_status == -1 || status == DOBJECT_STATUS_UP)
				*host_status = status;

			now = time(NULL);

			DBbegin();
			switch (zbx_process) {
			case ZBX_PROCESS_SERVER	:
				update_service(drule, dcheck, dhost, ip, port, status, value, now);
				break;
			case ZBX_PROCESS_PROXY	:
				proxy_update_service(drule, dcheck, ip, port, status, value, now);
				break;
			}
			DBcommit();
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: process_checks                                                   *
 *                                                                            *
 * Purpose:                                                                   *
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
static void	process_checks(DB_DRULE *drule, DB_DHOST *dhost, int *host_status, char *ip, int unique)
{
	DB_RESULT	result;
	DB_ROW		row;
	DB_DCHECK	dcheck;
	char		sql[MAX_STRING_LEN];
	int		offset = 0;

	offset += zbx_snprintf(sql + offset, sizeof(sql) - offset,
			"select dcheckid,type,key_,snmp_community,snmpv3_securityname,snmpv3_securitylevel,"
				"snmpv3_authpassphrase,snmpv3_privpassphrase,ports"
			" from dchecks"
			" where druleid=" ZBX_FS_UI64,
			drule->druleid);

	if (drule->unique_dcheckid)
	{
		offset += zbx_snprintf(sql + offset, sizeof(sql) - offset,
				" and dcheckid%s" ZBX_FS_UI64,
				unique ? "=" : "<>",
				drule->unique_dcheckid);
	}

	offset += zbx_snprintf(sql + offset, sizeof(sql) - offset,
			" order by dcheckid");

	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result))) {
		memset(&dcheck, 0, sizeof(dcheck));

		ZBX_STR2UINT64(dcheck.dcheckid, row[0]);
		dcheck.type			= atoi(row[1]);
		dcheck.key_			= row[2];
		dcheck.snmp_community		= row[3];
		dcheck.snmpv3_securityname	= row[4];
		dcheck.snmpv3_securitylevel	= atoi(row[5]);
		dcheck.snmpv3_authpassphrase	= row[6];
		dcheck.snmpv3_privpassphrase	= row[7];
		dcheck.ports			= row[8];

		process_check(drule, &dcheck, dhost, host_status, ip);
	}
	DBfree_result(result);
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
static void process_rule(DB_DRULE *drule)
{
	const char	*__function_name = "process_rule";
	DB_DHOST	dhost;
	int		host_status, now;
	unsigned int	j[9], i, first, last, mask, network, broadcast;
	char		ip[HOST_IP_LEN_MAX], *curr_range, *next_range, *dash, *slash;
#if defined(HAVE_IPV6)
	int		ipv6;
#endif

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() rule:'%s' range:'%s'", __function_name,
			drule->name,
			drule->iprange);

	for (curr_range = drule->iprange; curr_range; curr_range = next_range)
	{ /* split by ',' */
		if (NULL != (next_range = strchr(curr_range, ',')))
			*next_range = '\0';

		zabbix_log(LOG_LEVEL_DEBUG, "%s() '%s'", __function_name,
				curr_range);

		if (NULL != (dash = strchr(curr_range, '-')))
			*dash = '\0';

		if (NULL != (slash = strchr(curr_range, '/')))
			*slash = '\0';

		first = last = 0;
#if defined(HAVE_IPV6)
		if (SUCCEED == expand_ipv6(curr_range, ip, sizeof(ip)))
		{
			ipv6 = 1;
			if (8 == sscanf(ip, "%x:%x:%x:%x:%x:%x:%x:%x", &j[0], &j[1], &j[2], &j[3], &j[4], &j[5], &j[6], &j[7]))
			{
				first = (j[6] << 16) + j[7];

				if (NULL != dash)
				{
					if (1 == sscanf(dash + 1, "%x", &j[8]))
						last = (j[6] << 16) + j[8];
				}
				else if (NULL != slash)
				{
					if (1 == sscanf(slash + 1, "%d", &j[8]) && j[8] >= 112 && j[8] <= 128)
					{
						j[8] -= 96;

						mask = (32 == j[8]) ? 0xffffffff : ~(0xffffffff >> j[8]);
						network = first & mask;
						broadcast = network + ~mask;
						first = network + 1;

						zabbix_log(LOG_LEVEL_DEBUG, "%s() IPv6 CIDR:%u", __function_name, j[8] + 96);
						zbx_snprintf(ip, sizeof(ip), "%x:%x:%x:%x:%x:%x:%x:%x",
								0xffff, 0xffff, 0xffff, 0xffff, 0xffff, 0xffff,
								(mask & 0xffff0000) >> 16, (mask & 0x0000ffff));
						zabbix_log(LOG_LEVEL_DEBUG, "%s() IPv6 Netmask:'%s'",
								__function_name, collapse_ipv6(ip, sizeof(ip)));
						zbx_snprintf(ip, sizeof(ip), "%x:%x:%x:%x:%x:%x:%x:%x",
								j[0], j[1], j[2], j[3], j[4], j[5],
								(network & 0xffff0000) >> 16, (network & 0x0000ffff));
						zabbix_log(LOG_LEVEL_DEBUG, "%s() IPv6 Network:'%s'",
								__function_name, collapse_ipv6(ip, sizeof(ip)));
						zbx_snprintf(ip, sizeof(ip), "%x:%x:%x:%x:%x:%x:%x:%x",
								j[0], j[1], j[2], j[3], j[4], j[5],
								(broadcast & 0xffff0000) >> 16, (broadcast & 0x0000ffff));
						zabbix_log(LOG_LEVEL_DEBUG, "%s() IPv6 Broadcast:'%s'",
								__function_name, collapse_ipv6(ip, sizeof(ip)));

						if (j[8] <= 30)
							last = broadcast - 1;
					}
				}
				else
					last = first;

				zbx_snprintf(ip, sizeof(ip), "%x:%x:%x:%x:%x:%x:%x:%x",
						j[0], j[1], j[2], j[3], j[4], j[5],
						(first & 0xffff0000) >> 16, (first & 0x0000ffff));
				zabbix_log(LOG_LEVEL_DEBUG, "%s() IPv6 From:'%s'",
						__function_name, collapse_ipv6(ip, sizeof(ip)));
				zbx_snprintf(ip, sizeof(ip), "%x:%x:%x:%x:%x:%x:%x:%x",
						j[0], j[1], j[2], j[3], j[4], j[5],
						(last & 0xffff0000) >> 16, (last & 0x0000ffff));
				zabbix_log(LOG_LEVEL_DEBUG, "%s() IPv6 To:'%s'",
						__function_name, collapse_ipv6(ip, sizeof(ip)));
			}
		}
		else
		{
			ipv6 = 0;
#endif /* HAVE_IPV6 */
			if (4 == sscanf(curr_range, "%d.%d.%d.%d", &j[0], &j[1], &j[2], &j[3]) &&
					j[0] >= 0 && j[0] <= 255 &&
					j[1] >= 0 && j[1] <= 255 &&
					j[2] >= 0 && j[2] <= 255 &&
					j[3] >= 0 && j[3] <= 255)
			{
				first = (j[0] << 24) + (j[1] << 16) + (j[2] << 8) + j[3];

				if (NULL != dash)
				{
					if (1 == sscanf(dash + 1, "%d", &j[4]) && j[4] >= 0 && j[4] <= 255)
						last = (j[0] << 24) + (j[1] << 16) + (j[2] << 8) + j[4];
				}
				else if (NULL != slash)
				{
					if (1 == sscanf(slash + 1, "%d", &j[4]) && j[4] >= 16 && j[4] <= 32)
					{
						mask = (32 == j[4]) ? 0xffffffff : ~(0xffffffff >> j[4]);
						network = first & mask;
						broadcast = network + ~mask;
						first = network + 1;

						zabbix_log(LOG_LEVEL_DEBUG, "%s() IPv4 CIDR:%u", __function_name, j[4]);
						zabbix_log(LOG_LEVEL_DEBUG, "%s() IPv4 Netmask:'%u.%u.%u.%u'", __function_name,
								(mask & 0xff000000) >> 24, (mask & 0x00ff0000) >> 16,
								(mask & 0x0000ff00) >> 8, (mask & 0x000000ff));
						zabbix_log(LOG_LEVEL_DEBUG, "%s() IPv4 Network:'%u.%u.%u.%u'", __function_name,
								(network & 0xff000000) >> 24, (network & 0x00ff0000) >> 16,
								(network & 0x0000ff00) >> 8, (network & 0x000000ff));
						zabbix_log(LOG_LEVEL_DEBUG, "%s() IPv4 Broadcast:'%u.%u.%u.%u'", __function_name,
								(broadcast & 0xff000000) >> 24, (broadcast & 0x00ff0000) >> 16,
								(broadcast & 0x0000ff00) >> 8, (broadcast & 0x000000ff));

						if (j[4] <= 30)
							last = broadcast - 1;
					}
				}
				else
					last = first;

				zabbix_log(LOG_LEVEL_DEBUG, "%s() IPv4 Range:'%u.%u.%u.%u' - '%u.%u.%u.%u'", __function_name,
						(first & 0xff000000) >> 24, (first & 0x00ff0000) >> 16,
						(first & 0x0000ff00) >> 8, (first & 0x000000ff),
						(last & 0xff000000) >> 24, (last & 0x00ff0000) >> 16,
						(last & 0x0000ff00) >> 8, (last & 0x000000ff));
			}
#if defined(HAVE_IPV6)
		}
#endif /* HAVE_IPV6 */

		if (NULL != dash)
		{
			*dash = '-';
			dash = NULL;
		}

		if (NULL != slash)
		{
			*slash = '/';
			slash = NULL;
		}

		if (NULL != next_range)
		{
			*next_range = ',';
			next_range ++;
		}

		if (first == 0 || last == 0 || first > last)
		{
			zabbix_log(LOG_LEVEL_WARNING, "Discovery: Wrong format of IP range '%s'",
					curr_range);
			continue;
		}

		for (i = first; i <= last; i++) {
			memset(&dhost, 0, sizeof(dhost));
			host_status	= -1;

			now = time(NULL);

#if defined(HAVE_IPV6)
			switch(ipv6) {
			case 0 :
#endif /* HAVE_IPV6 */
				zbx_snprintf(ip, sizeof(ip), "%u.%u.%u.%u",
						(i & 0xff000000) >> 24,
						(i & 0x00ff0000) >> 16,
						(i & 0x0000ff00) >> 8,
						(i & 0x000000ff));
#if defined(HAVE_IPV6)
				break;
			case 1 :
				zbx_snprintf(ip, sizeof(ip), "%x:%x:%x:%x:%x:%x:%x:%x",
						j[0], j[1], j[2], j[3], j[4], j[5],
						(i & 0xffff0000) >> 16, (i & 0x0000ffff));
				collapse_ipv6(ip, sizeof(ip));
				break;
			}
#endif /* HAVE_IPV6 */

			zabbix_log(LOG_LEVEL_DEBUG, "%s() IP:'%s'", __function_name, ip);

			if (drule->unique_dcheckid)
				process_checks(drule, &dhost, &host_status, ip, 1);
			process_checks(drule, &dhost, &host_status, ip, 0);

			DBbegin();
			switch (zbx_process) {
			case ZBX_PROCESS_SERVER	:
				update_host(&dhost, ip, host_status, now);
				break;
			case ZBX_PROCESS_PROXY	:
				proxy_update_host(drule, ip, host_status, now);
				break;
			}
			DBcommit();
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static void process_discovery(int now)
{
	DB_RESULT	result;
	DB_ROW		row;
	DB_DRULE	drule;

	result = DBselect("select druleid,iprange,name,unique_dcheckid from drules"
			" where proxy_hostid=0 and status=%d and (nextcheck<=%d or nextcheck>%d+delay)"
			" and " ZBX_SQL_MOD(druleid,%d) "=%d" DB_NODE,
			DRULE_STATUS_MONITORED,
			now,
			now,
			CONFIG_DISCOVERER_FORKS,
			discoverer_num - 1,
			DBnode_local("druleid"));

	while (NULL != (row = DBfetch(result))) {
		memset(&drule, 0, sizeof(drule));

		ZBX_STR2UINT64(drule.druleid, row[0]);
		drule.iprange 	= row[1];
		drule.name	= row[2];
		ZBX_STR2UINT64(drule.unique_dcheckid, row[3]);

		process_rule(&drule);

		DBexecute("update drules set nextcheck=%d+delay where druleid=" ZBX_FS_UI64,
				now,
				drule.druleid);
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

	zabbix_log(LOG_LEVEL_DEBUG, "In main_discoverer_loop(num:%d)",
			num);

        phan.sa_sigaction = child_signal_handler;
	sigemptyset(&phan.sa_mask);
        phan.sa_flags = SA_SIGINFO;
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
		zabbix_log(LOG_LEVEL_DEBUG, "Discoverer spent " ZBX_FS_DBL " seconds while processing rules. Nextcheck: %d Time: %d",
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
