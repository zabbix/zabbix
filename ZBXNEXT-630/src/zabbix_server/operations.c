/*
** ZABBIX
** Copyright (C) 2000-2011 SIA Zabbix
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
#include "comms.h"
#include "db.h"
#include "log.h"
#include "zlog.h"
#include "zbxserver.h"

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
static void	run_remote_command(char *host_name, char *command)
{
	int		ret = FAIL;
	AGENT_RESULT	agent_result;
	DC_ITEM         item;
	DB_RESULT	result;
	DB_ROW		row;
	char		error[MAX_STRING_LEN], *p, *host_esc, *param;
#ifdef HAVE_OPENIPMI
	int		val;
	char		*port;
#endif

	assert(host_name);
	assert(command);

	zabbix_log(LOG_LEVEL_DEBUG, "In run_remote_command(hostname:%s,command:%s)",
		host_name,
		command);

	host_esc = DBdyn_escape_string(host_name);
	result = DBselect(
			"select hostid,ipmi_authtype,ipmi_privilege,ipmi_username,ipmi_password"
			" from hosts"
			" where status in (%d)"
				" and host='%s'"
				DB_NODE,
			HOST_STATUS_MONITORED,
			host_esc,
			DBnode_local("hostid"));
	zbx_free(host_esc);

	if (NULL != (row = DBfetch(result)))
	{
		memset(&item, 0, sizeof(item));

		ZBX_STR2UINT64(item.host.hostid, row[0]);
		strscpy(item.host.host, host_name);

		p = command;
		while (*p == ' ' && *p != '\0')
			p++;

		*error = '\0';

#ifdef HAVE_OPENIPMI
		if (0 == strncmp(p, "IPMI", 4))
		{
			item.host.ipmi_authtype = (signed char)atoi(row[1]);
			item.host.ipmi_privilege = (unsigned char)atoi(row[2]);
			strscpy(item.host.ipmi_username, row[3]);
			strscpy(item.host.ipmi_password, row[4]);

			if (SUCCEED == DCconfig_get_interface_by_type(&item.interface, item.host.hostid,
					INTERFACE_TYPE_IPMI, 1))
			{
				item.interface.addr = strdup(item.interface.useip ? item.interface.ip_orig : item.interface.dns_orig);
				substitute_simple_macros(NULL, NULL, &item.host, NULL,
						&item.interface.addr, MACRO_TYPE_INTERFACE_ADDR, NULL, 0);

				port = strdup(item.interface.port_orig);
				substitute_simple_macros(NULL, &item.host.hostid, NULL, NULL,
						&port, MACRO_TYPE_INTERFACE_PORT, NULL, 0);
				if (SUCCEED == is_ushort(port, &item.interface.port))
				{
					if (SUCCEED == (ret = parse_ipmi_command(p, item.ipmi_sensor, &val)))
					{
						item.key = item.ipmi_sensor;
						ret = set_ipmi_control_value(&item, val, error, sizeof(error));
					}
					else
						zbx_snprintf(error, sizeof(error), "incorrect format of IPMI command");
				}
				else
					zbx_snprintf(error, sizeof(error), "Invalid port number [%s]",
								item.interface.port_orig);

				zbx_free(port);
				zbx_free(item.interface.addr);
			}
			else
				zbx_snprintf(error, sizeof(error), "IPMI interface is not defined for host [%s]", host_name);
		}
		else
		{
#endif
			if (SUCCEED == DCconfig_get_interface_by_type(&item.interface, item.host.hostid,
					INTERFACE_TYPE_AGENT, 1))
			{
				param = dyn_escape_param(p);
				item.key = zbx_dsprintf(NULL, "system.run[\"%s\",\"nowait\"]", param);
				zbx_free(param);

				init_result(&agent_result);

				alarm(CONFIG_TIMEOUT);
				ret = get_value_agent(&item, &agent_result);
				alarm(0);

				free_result(&agent_result);

				zbx_free(item.key);
			}
			else
				zbx_snprintf(error, sizeof(error), "Zabbix Agent interface is not defined for host [%s]", host_name);
#ifdef HAVE_OPENIPMI
		}
#endif

		if (ret != SUCCEED && '\0' != *error)
		{
			zabbix_log(LOG_LEVEL_WARNING, "Cannot execute remote command [%s]: %s",
					p, error);
		}
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
 * Return value: 0 - correct command is read                                  *
 *               1 - EOL                                                      *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/

#define CMD_ALIAS 0
#define CMD_REM_COMMAND 1

static int	get_next_command(char **command_list, char **alias, int *is_group, char **command)
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
 * Function: op_run_commands                                                  *
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

	while (1 != get_next_command(&cmd_list, &alias, &is_group, &command))
	{
		if (!alias || *alias == '\0' || !command || *command == '\0')
			continue;

		if (is_group)
		{
			alias_esc = DBdyn_escape_string(alias);
			result = DBselect("select distinct h.host from hosts_groups hg,hosts h,groups g"
					" where hg.hostid=h.hostid and hg.groupid=g.groupid and g.name='%s'" DB_NODE,
					alias_esc,
					DBnode_local("h.hostid"));
			zbx_free(alias_esc);

			while (NULL != (row = DBfetch(result)))
				run_remote_command(row[0], command);

			DBfree_result(result);
		}
		else
			run_remote_command(alias, command);
	}
	zabbix_log( LOG_LEVEL_DEBUG, "End run_commands()");
}

/******************************************************************************
 *                                                                            *
 * Function: select_discovered_host                                           *
 *                                                                            *
 * Purpose: select hostid of discovered host                                  *
 *                                                                            *
 * Parameters: dhostid - discovered host id                                   *
 *                                                                            *
 * Return value: hostid - existing hostid, 0 - if not found                   *
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
	char		sql[512];

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() eventid:" ZBX_FS_UI64,
			__function_name, event->eventid);

	switch (event->object)
	{
		case EVENT_OBJECT_DHOST:
			zbx_snprintf(sql, sizeof(sql),
					"select i.hostid"
					" from interface i,dservices ds"
					" where ds.ip=i.ip"
						" and ds.dhostid=" ZBX_FS_UI64
						DB_NODE
					" order by i.hostid",
					event->objectid, DBnode_local("i.interfaceid"));
			break;
		case EVENT_OBJECT_DSERVICE:
			zbx_snprintf(sql, sizeof(sql),
					"select i.hostid"
					" from interface i,dservices ds"
					" where ds.ip=i.ip"
						" and ds.dserviceid=" ZBX_FS_UI64
						DB_NODE
					" order by i.hostid",
					event->objectid, DBnode_local("i.interfaceid"));
			break;
		default:
			goto exit;
	}

	result = DBselectN(sql, 1);

	if (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(hostid, row[0]);
	}
	DBfree_result(result);
exit:
	zabbix_log(LOG_LEVEL_DEBUG, "End %s():" ZBX_FS_UI64, __function_name, hostid);

	return hostid;
}

/******************************************************************************
 *                                                                            *
 * Function: add_discovered_host_group                                        *
 *                                                                            *
 * Purpose: add group to host if not added already                            *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	add_discovered_host_group(zbx_uint64_t hostid, zbx_uint64_t groupid)
{
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	hostgroupid;

	result = DBselect(
			"select hostgroupid"
			" from hosts_groups"
			" where groupid=" ZBX_FS_UI64
				" and hostid=" ZBX_FS_UI64,
			groupid,
			hostid);

	if (NULL == (row = DBfetch(result)))
	{
		hostgroupid = DBget_maxid("hosts_groups");
		DBexecute("insert into hosts_groups (hostgroupid,hostid,groupid)"
				" values (" ZBX_FS_UI64 "," ZBX_FS_UI64 "," ZBX_FS_UI64 ")",
				hostgroupid,
				hostid,
				groupid);
	}
	DBfree_result(result);
}

/******************************************************************************
 *                                                                            *
 * Function: add_discovered_host                                              *
 *                                                                            *
 * Purpose: add discovered host if it was not added already                   *
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
	zbx_uint64_t	dhostid, hostid = 0, proxy_hostid, host_proxy_hostid;
	char		*host = NULL, *host_esc, *host_unique;
	unsigned short	port;
	zbx_uint64_t	groupid = 0;
	unsigned char	svc_type, interface_type;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s(eventid:" ZBX_FS_UI64 ")",
			__function_name, event->eventid);

	result = DBselect(
			"select discovery_groupid"
			" from config"
			" where 1=1"
				DB_NODE,
			DBnode_local("configid"));

	if (NULL != (row = DBfetch(result)))
		ZBX_STR2UINT64(groupid, row[0]);
	DBfree_result(result);

	if (0 == groupid)
	{
		zabbix_log(LOG_LEVEL_WARNING, "Can't add discovered host:"
				" Group for discovered hosts is not defined");
		return hostid;
	}

	if (EVENT_OBJECT_DHOST == event->object || EVENT_OBJECT_DSERVICE == event->object)
	{
		if (EVENT_OBJECT_DHOST == event->object)
		{
			result = DBselect(
					"select ds.dhostid,dr.proxy_hostid,ds.ip,ds.dns,ds.port,ds.type"
					" from drules dr,dchecks dc,dservices ds"
					" where dc.druleid=dr.druleid"
						" and ds.dcheckid=dc.dcheckid"
						" and ds.dhostid=" ZBX_FS_UI64
					" order by ds.dserviceid",
					event->objectid);
		}
		else
		{
			result = DBselect(
					"select ds.dhostid,dr.proxy_hostid,ds.ip,ds.dns,ds.port,ds.type"
					" from drules dr,dchecks dc,dservices ds,dservices ds1"
					" where dc.druleid=dr.druleid"
						" and ds.dcheckid=dc.dcheckid"
						" and ds1.dhostid=ds.dhostid"
						" and ds1.dserviceid=" ZBX_FS_UI64
					" order by ds.dserviceid",
					event->objectid);
		}

		while (NULL != (row = DBfetch(result)))
		{
			ZBX_STR2UINT64(dhostid, row[0]);
			ZBX_DBROW2UINT64(proxy_hostid, row[1]);
			svc_type = (unsigned char)atoi(row[5]);

			switch (svc_type)
			{
				case SVC_AGENT:
					port = (unsigned short)atoi(row[4]);
					interface_type = INTERFACE_TYPE_AGENT;
					break;
				case SVC_SNMPv1:
				case SVC_SNMPv2c:
				case SVC_SNMPv3:
					port = (unsigned short)atoi(row[4]);
					interface_type = INTERFACE_TYPE_SNMP;
					break;
				default:
					port = ZBX_DEFAULT_AGENT_PORT;
					interface_type = INTERFACE_TYPE_AGENT;
			}

			if (0 == hostid)
			{
				result2 = DBselect(
						"select distinct h.hostid,h.proxy_hostid"
						" from hosts h,interface i,dservices ds"
						" where h.hostid=i.hostid"
							" and i.ip=ds.ip"
							" and ds.dhostid=" ZBX_FS_UI64
						       	DB_NODE
						" order by h.hostid",
						dhostid,
						DBnode_local("h.hostid"));

				if (NULL != (row2 = DBfetch(result2)))
				{
					ZBX_STR2UINT64(hostid, row2[0]);
					ZBX_DBROW2UINT64(host_proxy_hostid, row2[1]);
				}
				DBfree_result(result2);
			}

			if (0 == hostid)
			{
				hostid = DBget_maxid("hosts");

				/* for host uniqueness purposes */
				host = zbx_strdup(host, '\0' != *row[3] ? row[3] : row[2]);

				make_hostname(host);	/* replace not-allowed symbols */
				host_unique = DBget_unique_hostname_by_sample(host);
				host_esc = DBdyn_escape_string(host_unique);

				zbx_free(host);

				DBexecute("insert into hosts"
							" (hostid,proxy_hostid,host)"
						" values"
							" (" ZBX_FS_UI64 ",%s,'%s')",
						hostid, DBsql_id_ins(proxy_hostid), host_esc);

				DBadd_interface(hostid, interface_type, 1, row[2], row[3], port);

				zbx_free(host_unique);
				zbx_free(host_esc);
			}
			else
			{
				if (host_proxy_hostid != proxy_hostid)
				{
					DBexecute("update hosts"
							" set proxy_hostid=%s"
							" where hostid=" ZBX_FS_UI64,
							DBsql_id_ins(proxy_hostid),
							hostid);
				}

				DBadd_interface(hostid, interface_type, 1, row[2], row[3], port);
			}
		}
		DBfree_result(result);
	}
	else if (EVENT_OBJECT_ZABBIX_ACTIVE == event->object)
	{
		result = DBselect(
				"select proxy_hostid,host,listen_ip,listen_dns,listen_port"
				" from autoreg_host"
				" where autoreg_hostid=" ZBX_FS_UI64,
				event->objectid);

		if (NULL != (row = DBfetch(result)))
		{
			char	sql[512];

			ZBX_DBROW2UINT64(proxy_hostid, row[0]);
			host_esc = DBdyn_escape_string_len(row[1], HOST_HOST_LEN);
			port = (unsigned short)atoi(row[4]);

			zbx_snprintf(sql, sizeof(sql),
					"select hostid,proxy_hostid"
					" from hosts"
					" where host='%s'"
						DB_NODE
					" order by hostid",
					host_esc,
					DBnode_local("hostid"));

			result2 = DBselectN(sql, 1);

			if (NULL == (row2 = DBfetch(result2)))
			{
				hostid = DBget_maxid("hosts");

				DBexecute("insert into hosts"
							" (hostid,proxy_hostid,host)"
						" values"
							" (" ZBX_FS_UI64 ",%s,'%s')",
						hostid, DBsql_id_ins(proxy_hostid), host_esc);

				DBadd_interface(hostid, INTERFACE_TYPE_AGENT, 1, row[2], row[3], port);
			}
			else
			{
				ZBX_STR2UINT64(hostid, row2[0]);
				ZBX_DBROW2UINT64(host_proxy_hostid, row2[1]);

				if (host_proxy_hostid != proxy_hostid)
				{
					DBexecute("update hosts"
							" set proxy_hostid=%s"
							" where hostid=" ZBX_FS_UI64,
							DBsql_id_ins(proxy_hostid),
							hostid);
				}

				DBadd_interface(hostid, INTERFACE_TYPE_AGENT, 1, row[2], row[3], port);
			}
			DBfree_result(result2);

			zbx_free(host_esc);
		}
		DBfree_result(result);
	}

	if (0 != hostid)
		add_discovered_host_group(hostid, groupid);

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

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (event->source != EVENT_SOURCE_DISCOVERY && event->source != EVENT_SOURCE_AUTO_REGISTRATION)
		return;

	if (event->object != EVENT_OBJECT_DHOST && event->object != EVENT_OBJECT_DSERVICE && event->object != EVENT_OBJECT_ZABBIX_ACTIVE)
		return;

	add_discovered_host(event);

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

	if (0 == (hostid = add_discovered_host(event)))
		return;

	DBexecute(
			"update hosts"
			" set status=%d"
			" where hostid=" ZBX_FS_UI64,
			HOST_STATUS_MONITORED,
			hostid);

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

	if (0 == (hostid = add_discovered_host(event)))
		return;

	DBexecute(
			"update hosts"
			" set status=%d"
			" where hostid=" ZBX_FS_UI64,
			HOST_STATUS_NOT_MONITORED,
			hostid);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: op_group_add                                                     *
 *                                                                            *
 * Purpose: add group to discovered host                                      *
 *                                                                            *
 * Parameters: event   - [IN] event data                                      *
 *             groupid - [IN] group identificator from database               *
 *                                                                            *
 * Return value: nothing                                                      *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	op_group_add(DB_EVENT *event, zbx_uint64_t groupid)
{
	const char	*__function_name = "op_group_add";
	zbx_uint64_t	hostid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (event->source != EVENT_SOURCE_DISCOVERY && event->source != EVENT_SOURCE_AUTO_REGISTRATION)
		return;

	if (event->object != EVENT_OBJECT_DHOST && event->object != EVENT_OBJECT_DSERVICE && event->object != EVENT_OBJECT_ZABBIX_ACTIVE)
		return;

	if (0 == (hostid = add_discovered_host(event)))
		return;

	add_discovered_host_group(hostid, groupid);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: op_group_del                                                     *
 *                                                                            *
 * Purpose: delete group from discovered host                                 *
 *                                                                            *
 * Parameters: event   - [IN] event data                                      *
 *             groupid - [IN] group identificator from database               *
 *                                                                            *
 * Return value: nothing                                                      *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	op_group_del(DB_EVENT *event, zbx_uint64_t groupid)
{
	const char	*__function_name = "op_group_del";
	zbx_uint64_t	hostid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (event->source != EVENT_SOURCE_DISCOVERY)
		return;

	if (event->object != EVENT_OBJECT_DHOST && event->object != EVENT_OBJECT_DSERVICE)
		return;

	if (0 == (hostid = select_discovered_host(event)))
		return;

	DBexecute(
			"delete from hosts_groups"
			" where hostid=" ZBX_FS_UI64
				" and groupid=" ZBX_FS_UI64,
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
 * Parameters: event      - [IN] event data                                   *
 *             templateid - [IN] host template identificator from database    *
 *                                                                            *
 * Return value: nothing                                                      *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	op_template_add(DB_EVENT *event, zbx_uint64_t templateid)
{
	const char	*__function_name = "op_template_add";
	zbx_uint64_t	hostid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (event->source != EVENT_SOURCE_DISCOVERY && event->source != EVENT_SOURCE_AUTO_REGISTRATION)
		return;

	if (event->object != EVENT_OBJECT_DHOST && event->object != EVENT_OBJECT_DSERVICE && event->object != EVENT_OBJECT_ZABBIX_ACTIVE)
		return;

	if (0 == (hostid = add_discovered_host(event)))
		return;

	DBcopy_template_elements(hostid, templateid);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: op_template_del                                                  *
 *                                                                            *
 * Purpose: unlink and clear host from template                               *
 *                                                                            *
 * Parameters: event      - [IN] event data                                   *
 *             templateid - [IN] host template identificator from database    *
 *                                                                            *
 * Return value: nothing                                                      *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	op_template_del(DB_EVENT *event, zbx_uint64_t templateid)
{
	const char	*__function_name = "op_template_del";
	zbx_uint64_t	hostid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (event->source != EVENT_SOURCE_DISCOVERY)
		return;

	if (event->object != EVENT_OBJECT_DHOST && event->object != EVENT_OBJECT_DSERVICE)
		return;

	if (0 == (hostid = select_discovered_host(event)))
		return;

	DBdelete_template_elements(hostid, templateid);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}
