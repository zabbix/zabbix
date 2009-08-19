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


#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <netinet/in.h>
#include <netdb.h>

#include <signal.h>

#include <string.h>

#include <time.h>

#include "common.h"
#include "comms.h"
#include "db.h"
#include "log.h"
#include "zlog.h"

#include "operations.h"

#include "poller/poller.h"
#include "poller/checks_agent.h"
#include "poller/checks_ipmi.h"

/******************************************************************************
 *                                                                            *
 * Function: run_remote_commands                                              *
 *                                                                            *
 * Purpose: run remote command on specific host                               *
 *                                                                            *
 * Parameters: host_name - host name                                          *
 *             command - remote command                                       *
 *                                                                            *
 * Return value: nothing                                                      *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/

static void run_remote_command(char* host_name, char* command)
{
	int		ret = 9;
	AGENT_RESULT	agent_result;
	DB_ITEM         item;
	DB_RESULT	result;
	DB_ROW		row;
	char		*p, *host_esc, key[ITEM_KEY_LEN_MAX];
#ifdef HAVE_OPENIPMI
	int		val;
	char		error[MAX_STRING_LEN];
#endif

	assert(host_name);
	assert(command);

	zabbix_log(LOG_LEVEL_DEBUG, "In run_remote_command(hostname:%s,command:%s)",
		host_name,
		command);

	host_esc = DBdyn_escape_string(host_name);
	result = DBselect("select distinct host,ip,useip,port,dns,useipmi,ipmi_port,ipmi_authtype,"
			"ipmi_privilege,ipmi_username,ipmi_password from hosts where host='%s'" DB_NODE,
			host_esc,
			DBnode_local("hostid"));
	zbx_free(host_esc);

	if (NULL != (row = DBfetch(result)))
	{
		*key			= '\0';
		item.key		= key;
		item.host_name		= row[0];
		item.host_ip		= row[1];
		item.useip		= atoi(row[2]);
		item.port		= atoi(row[3]);
		item.host_dns		= row[4];

		item.useipmi		= atoi(row[5]);
		item.ipmi_port		= atoi(row[6]);
		item.ipmi_authtype	= atoi(row[7]);
		item.ipmi_privilege	= atoi(row[8]);
		item.ipmi_username	= row[9];
		item.ipmi_password	= row[10];

		p = command;
		while (*p == ' ' && *p != '\0')
			p++;

#ifdef HAVE_OPENIPMI
		if (0 == strncmp(p, "IPMI", 4))
		{
			if (SUCCEED == (ret = parse_ipmi_command(p, &item.ipmi_sensor, &val)))
				ret = set_ipmi_control_value(&item, val, error, sizeof(error));
		}
		else
		{
#endif
			zbx_snprintf(key, sizeof(key), "system.run[%s,nowait]", p);

			alarm(CONFIG_TIMEOUT);

			ret = get_value_agent(&item, &agent_result);

			free_result(&agent_result);

			alarm(0);
#ifdef HAVE_OPENIPMI
		}
#endif
	}
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End run_remote_command(result:%d)",
		ret);
}

/******************************************************************************
 *                                                                            *
 * Function: get_next_command                                                 *
 *                                                                            *
 * Purpose: parse action script on remote commands                            *
 *                                                                            *
 * Parameters: command_list - command list                                    *
 *             alias - (output) of host name or group name                    *
 *             is_group - (output) 0 if alias is a host name                  *
 *                               1 if alias is a group name                   *
 *             command - (output) remote command                              *
 *                                                                            *
 * Return value: 0 - correct comand is read                                   *
 *               1 - EOL                                                      *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/

#define CMD_ALIAS 0
#define CMD_REM_COMMAND 1

static int get_next_command(char** command_list, char** alias, int* is_group, char** command)
{
	int state = CMD_ALIAS;
	int len = 0;
	int i = 0;

	assert(alias);
	assert(is_group);
	assert(command);

	zabbix_log(LOG_LEVEL_DEBUG, "In get_next_command(command_list:%s)",
		*command_list);

	*alias = NULL;
	*is_group = 0;
	*command = NULL;


	if((*command_list)[0] == '\0' || (*command_list)==NULL) {
		zabbix_log(LOG_LEVEL_DEBUG, "Result get_next_command [EOL]");
		return 1;
	}

	*alias = *command_list;
	len = strlen(*command_list);

	for(i=0; i < len; i++)
	{
		if(state == CMD_ALIAS)
		{
			if((*command_list)[i] == '#'){
				*is_group = 1;
				(*command_list)[i] = '\0';
				state = CMD_REM_COMMAND;
				*command = &(*command_list)[i+1];
			}else if((*command_list)[i] == ':'){
				*is_group = 0;
				(*command_list)[i] = '\0';
				state = CMD_REM_COMMAND;
				*command = &(*command_list)[i+1];
			}
		} else if(state == CMD_REM_COMMAND) {
			if((*command_list)[i] == '\r')
			{
				(*command_list)[i] = '\0';
			} else if((*command_list)[i] == '\n')
			{
				(*command_list)[i] = '\0';
				(*command_list) = &(*command_list)[i+1];
				break;
			}
		}
		if((*command_list)[i+1] == '\0')
		{
			(*command_list) = &(*command_list)[i+1];
			break;
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End get_next_command(alias:%s,is_group:%i,command:%s)",
		*alias,
		*is_group,
		*command);

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Function: run_commands                                                     *
 *                                                                            *
 * Purpose: run remote commandlist for specific action                        *
 *                                                                            *
 * Parameters: trigger - trigger data                                         *
 *             action  - action data                                          *
 *                                                                            *
 * Return value: nothing                                                      *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: commands separated with newline                                  *
 *                                                                            *
 ******************************************************************************/
void	op_run_commands(char *cmd_list)
{
	DB_RESULT	result;
	DB_ROW		row;
	char		*alias, *alias_esc, *command;
	int		is_group;

	assert(cmd_list);

	zabbix_log(LOG_LEVEL_DEBUG, "In run_commands()");

	while (1 != get_next_command(&cmd_list, &alias, &is_group, &command)) {
		if (!alias || *alias == '\0' || !command || *command == '\0')
			continue;

		if (is_group) {
			alias_esc = DBdyn_escape_string(alias);
			result = DBselect("select distinct h.host from hosts_groups hg,hosts h,groups g"
					" where hg.hostid=h.hostid and hg.groupid=g.groupid and g.name='%s'" DB_NODE,
					alias_esc,
					DBnode_local("h.hostid"));
			zbx_free(alias_esc);

			while (NULL != (row = DBfetch(result)))
				run_remote_command(row[0], command);

			DBfree_result(result);
		} else
			run_remote_command(alias, command);
	}
	zabbix_log( LOG_LEVEL_DEBUG, "End run_commands()");
}

/******************************************************************************
 *                                                                            *
 * Function: select hostid of discovered host                                 *
 *                                                                            *
 * Purpose: select discovered host                                            *
 *                                                                            *
 * Parameters: dhostid - discovered host id                                   *
 *                                                                            *
 * Return value: hostid - existing hostid, o - if not found                   *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static zbx_uint64_t	select_discovered_host(DB_EVENT *event)
{
	const char	*__function_name = "select_discovered_host";
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	hostid = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s(eventid:" ZBX_FS_UI64 ")",
			__function_name, event->eventid);

	switch (event->object) {
	case EVENT_OBJECT_DHOST:
		result = DBselect(
				"select h.hostid"
				" from hosts h,dservices ds"
				" where ds.ip=h.ip"
					" and ds.dhostid=" ZBX_FS_UI64,
				event->objectid);
		break;
	case EVENT_OBJECT_DSERVICE:
		result = DBselect(
				"select h.hostid"
				" from hosts h,dservices ds"
				" where ds.ip=h.ip"
					" and ds.dserviceid =" ZBX_FS_UI64,
				event->objectid);
		break;
	case EVENT_OBJECT_ZABBIX_ACTIVE:
		result = DBselect("select h.hostid from hosts h,autoreg_host a"
				" where a.proxy_hostid=h.proxyhostid and a.host=h.host and a.autoreg_hostid=" ZBX_FS_UI64,
				event->objectid);
		break;
	default:
		return 0;
	}

	if (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(hostid, row[0]);
	}
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End %s()", __function_name);

	return hostid;
}

/******************************************************************************
 *                                                                            *
 * Function: get_discovered_agent_port                                        *
 *                                                                            *
 * Purpose: return port of the discovered zabbix_agent                        *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: discovered port number, otherwise default port - 10050       *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int	get_discovered_agent_port(DB_EVENT *event)
{
	const char	*__function_name = "get_discovered_agent_port";
	DB_RESULT	result;
	DB_ROW		row;
	int		port = 10050;
	char		sql[256];

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (event->source != EVENT_SOURCE_DISCOVERY)
		return port;

	switch (event->object) {
	case EVENT_OBJECT_DHOST:
		zbx_snprintf(sql, sizeof(sql), "select port from dservices where type=%d and dhostid=" ZBX_FS_UI64
				" order by dserviceid",
				SVC_AGENT,
				event->objectid);
		break;
	case EVENT_OBJECT_DSERVICE:
		zbx_snprintf(sql, sizeof(sql), "select port from dservices where type=%d and"
				" dhostid in (select dhostid from dservices where dserviceid=" ZBX_FS_UI64 ")"
				" order by dserviceid",
				SVC_AGENT,
				event->objectid);
		break;
	default:
		return port;
	}

	result = DBselectN(sql, 1);
	if (NULL != (row = DBfetch(result)))
		port = atoi(row[0]);
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);

	return port;
}

/******************************************************************************
 *                                                                            *
 * Function: add host if not added already                                    *
 *                                                                            *
 * Purpose: add discovered host                                               *
 *                                                                            *
 * Parameters: dhostid - discovered host id                                   *
 *                                                                            *
 * Return value: hostid - new/existing hostid                                 *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static zbx_uint64_t	add_discovered_host(DB_EVENT *event)
{
	const char	*__function_name = "add_discovered_host";
	DB_RESULT	result;
	DB_RESULT	result2;
	DB_ROW		row;
	DB_ROW		row2;
	zbx_uint64_t	hostid = 0, proxy_hostid, host_proxy_hostid;
	char		host[MAX_STRING_LEN], *host_esc, *ip_esc;
	int		port;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s(eventid:" ZBX_FS_UI64 ")",
			__function_name, event->eventid);

	switch (event->object) {
	case EVENT_OBJECT_DHOST:
		result = DBselect(
				"select dr.proxy_hostid,ds.ip"
				" from drules dr,dchecks dc,dservices ds"
				" where dc.druleid=dr.druleid"
					" and ds.dcheckid=dc.dcheckid"
					" and ds.dhostid=" ZBX_FS_UI64
				" order by ds.dserviceid",
				event->objectid);
		break;
	case EVENT_OBJECT_DSERVICE:
		result = DBselect(
				"select dr.proxy_hostid,ds.ip"
				" from drules dr,dchecks dc,dservices ds,dservices ds1"
				" where dc.druleid=dr.druleid"
					" and ds.dcheckid=dc.dcheckid"
					" and ds1.dcheckid=ds.dcheckid"
					" and ds1.dserviceid=" ZBX_FS_UI64
				" order by ds.dserviceid",
				event->objectid);
		break;
	case EVENT_OBJECT_ZABBIX_ACTIVE:
		result = DBselect("select proxy_hostid,host from autoreg_host"
				" where autoreg_hostid=" ZBX_FS_UI64,
				event->objectid);
		break;
	default:
		return 0;
	}

	if (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(proxy_hostid, row[0]);

		if (EVENT_OBJECT_ZABBIX_ACTIVE == event->object)
		{
			host_esc = DBdyn_escape_string_len(row[1], HOST_HOST_LEN);

			result2 = DBselect(
					"select hostid,proxy_hostid"
					" from hosts"
					" where host='%s'"
					       	DB_NODE,
					host_esc,
					DBnode_local("hostid"));

			if (NULL == (row2 = DBfetch(result2)))
			{
				hostid = DBget_maxid("hosts", "hostid");

				DBexecute("insert into hosts (hostid,proxy_hostid,host,useip,dns)"
						" values (" ZBX_FS_UI64 "," ZBX_FS_UI64 ",'%s',0,'%s')",
						hostid,
						proxy_hostid,
						host_esc,
						host_esc);
			}
			else
			{
				ZBX_STR2UINT64(hostid, row2[0]);
				ZBX_STR2UINT64(host_proxy_hostid, row2[1]);

				if (host_proxy_hostid != proxy_hostid)
				{
					DBexecute("update hosts"
							" set proxy_hostid=" ZBX_FS_UI64
							" where hostid=" ZBX_FS_UI64,
							proxy_hostid,
							hostid);
				}
			}
			DBfree_result(result2);

			zbx_free(host_esc);
		}
		else
		{
			alarm(CONFIG_TIMEOUT);
			zbx_gethost_by_ip(row[1], host, sizeof(host));
			alarm(0);

			host_esc = DBdyn_escape_string_len(host, HOST_HOST_LEN);
			ip_esc = DBdyn_escape_string_len(row[1], HOST_IP_LEN);

			port = get_discovered_agent_port(event);

			result2 = DBselect(
					"select hostid,dns,port,proxy_hostid"
					" from hosts"
					" where ip='%s'"
					       	DB_NODE,
					ip_esc,
					DBnode_local("hostid"));

			if (NULL == (row2 = DBfetch(result2)))
			{
				hostid = DBget_maxid("hosts", "hostid");

				DBexecute("insert into hosts (hostid,proxy_hostid,host,useip,ip,dns,port)"
						" values (" ZBX_FS_UI64 "," ZBX_FS_UI64 ",'%s',1,'%s','%s',%d)",
						hostid,
						proxy_hostid,
						(*host != '\0' ? host_esc : ip_esc), /* Use host name if exists, IP otherwise */
						ip_esc,
						host_esc,
						port);
			}
			else
			{
				ZBX_STR2UINT64(hostid, row2[0]);
				ZBX_STR2UINT64(host_proxy_hostid, row2[3]);

				if (0 != strcmp(host, row2[1]) || port != atoi(row2[2]) || host_proxy_hostid != proxy_hostid)
				{
					DBexecute("update hosts"
							" set dns='%s',port=%d,proxy_hostid=" ZBX_FS_UI64
							" where hostid=" ZBX_FS_UI64,
							host_esc, port, proxy_hostid,
							hostid);
				}
			}
			DBfree_result(result2);

			zbx_free(host_esc);
			zbx_free(ip_esc);
		}
	}
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);

	return hostid;
}

/******************************************************************************
 *                                                                            *
 * Function: op_host_add                                                      *
 *                                                                            *
 * Purpose: add discovered host                                               *
 *                                                                            *
 * Parameters: trigger - trigger data                                         *
 *             action  - action data                                          *
 *                                                                            *
 * Return value: nothing                                                      *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	op_host_add(DB_EVENT *event)
{
	const char	*__function_name = "op_host_add";
	DB_RESULT	result;
	DB_ROW		row;
	DB_OPERATION	operation;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (event->source != EVENT_SOURCE_DISCOVERY && event->source != EVENT_SOURCE_AUTO_REGISTRATION)
		return;

	if (event->object != EVENT_OBJECT_DHOST && event->object != EVENT_OBJECT_DSERVICE && event->object != EVENT_OBJECT_ZABBIX_ACTIVE)
		return;

	result = DBselect(
			"select discovery_groupid"
			" from config"
			" where 1=1" DB_NODE,
			DBnode_local("configid"));

	if (NULL != (row = DBfetch(result)) && SUCCEED != DBis_null(row[0]))
	{
		memset(&operation, 0, sizeof(operation));

		operation.operationtype	= OPERATION_TYPE_GROUP_ADD;
		ZBX_STR2UINT64(operation.objectid, row[0]);

		op_group_add(event, &operation);
	}
	else
	{
		zabbix_log(LOG_LEVEL_WARNING, "Can't add discovered host: Discovery group is not defined");
	}
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: op_host_del                                                      *
 *                                                                            *
 * Purpose: delete host                                                       *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: nothing                                                      *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	op_host_del(DB_EVENT *event)
{
	const char	*__function_name = "op_host_del";
	zbx_uint64_t	hostid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (event->source != EVENT_SOURCE_DISCOVERY)
		return;

	if (event->object != EVENT_OBJECT_DHOST && event->object != EVENT_OBJECT_DSERVICE)
		return;

	if (0 == (hostid = select_discovered_host(event)))
		return;

	DBdelete_host(hostid);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: op_host_enable                                                   *
 *                                                                            *
 * Purpose: enable discovered                                                 *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: nothing                                                      *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	op_host_enable(DB_EVENT *event)
{
	const char	*__function_name = "op_host_enable";
	zbx_uint64_t	hostid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (event->source != EVENT_SOURCE_DISCOVERY)
		return;

	if (event->object != EVENT_OBJECT_DHOST && event->object != EVENT_OBJECT_DSERVICE)
		return;

	if (0 == (hostid = select_discovered_host(event)))
		return;

	DBexecute("update hosts set status=%d where hostid=" ZBX_FS_UI64, HOST_STATUS_MONITORED, hostid);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: op_host_disable                                                  *
 *                                                                            *
 * Purpose: disable host                                                      *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: nothing                                                      *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	op_host_disable(DB_EVENT *event)
{
	const char	*__function_name = "op_host_disable";
	zbx_uint64_t	hostid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (event->source != EVENT_SOURCE_DISCOVERY && event->source != EVENT_SOURCE_AUTO_REGISTRATION)
		return;

	if (event->object != EVENT_OBJECT_DHOST && event->object != EVENT_OBJECT_DSERVICE && event->object != EVENT_OBJECT_ZABBIX_ACTIVE)
		return;

	if (0 == (hostid = select_discovered_host(event)))
		return;

	DBexecute("update hosts set status=%d where hostid=" ZBX_FS_UI64, HOST_STATUS_NOT_MONITORED, hostid);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: op_group_add                                                     *
 *                                                                            *
 * Purpose: add group to discovered host                                      *
 *                                                                            *
 * Parameters: event - event data                                             *
 *             operation - operation data                                     *
 *                                                                            *
 * Return value: nothing                                                      *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	op_group_add(DB_EVENT *event, DB_OPERATION *operation)
{
	const char	*__function_name = "op_group_add";
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	hostgroupid, groupid, hostid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s(object:%d)",
			__function_name, event->object);

	if (operation->operationtype != OPERATION_TYPE_GROUP_ADD)
		return;

	if (event->source != EVENT_SOURCE_DISCOVERY && event->source != EVENT_SOURCE_AUTO_REGISTRATION)
		return;

	if (event->object != EVENT_OBJECT_DHOST && event->object != EVENT_OBJECT_DSERVICE && event->object != EVENT_OBJECT_ZABBIX_ACTIVE)
		return;

	if (0 == (hostid = add_discovered_host(event)))
		return;

	groupid = operation->objectid;

	result = DBselect("select hostgroupid from hosts_groups where groupid=" ZBX_FS_UI64 " and hostid=" ZBX_FS_UI64,
			groupid,
			hostid);

	if (NULL == (row = DBfetch(result)))
	{
		hostgroupid = DBget_maxid("hosts_groups", "hostgroupid");
		DBexecute("insert into hosts_groups (hostgroupid,hostid,groupid)"
				" values (" ZBX_FS_UI64 "," ZBX_FS_UI64 "," ZBX_FS_UI64 ")",
				hostgroupid,
				hostid,
				groupid);
	}
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: op_group_del                                                     *
 *                                                                            *
 * Purpose: delete group from discovered host                                 *
 *                                                                            *
 * Parameters: trigger - trigger data                                         *
 *             action  - action data                                          *
 *                                                                            *
 * Return value: nothing                                                      *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	op_group_del(DB_EVENT *event, DB_ACTION *action, DB_OPERATION *operation)
{
	const char	*__function_name = "op_group_del";
	zbx_uint64_t	groupid, hostid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (operation->operationtype != OPERATION_TYPE_GROUP_REMOVE)
		return;

	if (event->source != EVENT_SOURCE_DISCOVERY)
		return;

	if (event->object != EVENT_OBJECT_DHOST && event->object != EVENT_OBJECT_DSERVICE)
		return;

	if (0 == (hostid = select_discovered_host(event)))
		return;

	groupid = operation->objectid;

	DBexecute("delete from hosts_groups where hostid=" ZBX_FS_UI64 " and groupid=" ZBX_FS_UI64,
			hostid,
			groupid);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: op_template_add                                                  *
 *                                                                            *
 * Purpose: link host with template                                           *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: nothing                                                      *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	op_template_add(DB_EVENT *event, DB_ACTION *action, DB_OPERATION *operation)
{
	const char	*__function_name = "op_template_add";
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	hosttemplateid, templateid, hostid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s(object:%d)", __function_name, event->object);

	if (operation->operationtype != OPERATION_TYPE_TEMPLATE_ADD)
		return;

	if (event->source != EVENT_SOURCE_DISCOVERY && event->source != EVENT_SOURCE_AUTO_REGISTRATION)
		return;

	if (event->object != EVENT_OBJECT_DHOST && event->object != EVENT_OBJECT_DSERVICE && event->object != EVENT_OBJECT_ZABBIX_ACTIVE)
		return;

	if (0 == (hostid = add_discovered_host(event)))
		return;

	templateid = operation->objectid;

	result = DBselect("select hosttemplateid from hosts_templates where templateid=" ZBX_FS_UI64 " and hostid=" ZBX_FS_UI64,
			templateid,
			hostid);

	if (NULL == (row = DBfetch(result)))
	{
		hosttemplateid = DBget_maxid("hosts_templates", "hosttemplateid");
		DBexecute("insert into hosts_templates (hosttemplateid, hostid, templateid)"
				" values (" ZBX_FS_UI64 "," ZBX_FS_UI64 "," ZBX_FS_UI64 ")",
				hosttemplateid,
				hostid,
				templateid);

		DBsync_host_with_template(hostid, templateid);
	}
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: op_template_del                                                  *
 *                                                                            *
 * Purpose: unlink and clear host from template                               *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: nothing                                                      *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	op_template_del(DB_EVENT *event, DB_ACTION *action, DB_OPERATION *operation)
{
	const char	*__function_name = "op_template_del";
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	templateid, hostid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s(object:%d)", __function_name, event->object);

	if (operation->operationtype != OPERATION_TYPE_TEMPLATE_REMOVE)
		return;

	if (event->source != EVENT_SOURCE_DISCOVERY)
		return;

	if (event->object != EVENT_OBJECT_DHOST && event->object != EVENT_OBJECT_DSERVICE)
		return;

	if (0 == (hostid = select_discovered_host(event)))
		return;

	templateid = operation->objectid;

	result = DBselect("select hosttemplateid from hosts_templates where templateid=" ZBX_FS_UI64 " and hostid=" ZBX_FS_UI64,
			templateid,
			hostid);

	if (NULL != (row = DBfetch(result)))
	{
		DBdelete_template_elements(hostid, templateid, 0 /* not an unlink mode */);

		DBexecute("delete from hosts_templates where "
				"hostid=" ZBX_FS_UI64 " and templateid=" ZBX_FS_UI64,
				hostid,
				templateid);
	}
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}
