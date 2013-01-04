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
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 ******************************************************************************/

static void run_remote_command(char* host_name, char* command)
{
	int		ret = 9;
	AGENT_RESULT	agent_result;
	DC_ITEM         item;
	DB_RESULT	result;
	DB_ROW		row;
	char		*p, *host_esc, *param;
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
	result = DBselect(
			"select hostid,host,useip,ip,dns,port,useipmi,ipmi_ip,ipmi_port,ipmi_authtype,"
				"ipmi_privilege,ipmi_username,ipmi_password"
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
		zbx_strlcpy(item.host.host, row[1], sizeof(item.host.host));
		item.host.useip = (unsigned char)atoi(row[2]);
		zbx_strlcpy(item.host.ip, row[3], sizeof(item.host.ip));
		zbx_strlcpy(item.host.dns, row[4], sizeof(item.host.dns));
		item.host.port = (unsigned short)atoi(row[5]);

		p = command;
		while (*p == ' ' && *p != '\0')
			p++;

#ifdef HAVE_OPENIPMI
		if (0 == strncmp(p, "IPMI", 4))
		{
			if (1 == atoi(row[6]))
			{
				zbx_strlcpy(item.host.ipmi_ip_orig, row[7], sizeof(item.host.ipmi_ip_orig));
				item.host.ipmi_port = (unsigned short)atoi(row[8]);
				item.host.ipmi_authtype = atoi(row[9]);
				item.host.ipmi_privilege = atoi(row[10]);
				zbx_strlcpy(item.host.ipmi_username, row[11], sizeof(item.host.ipmi_username));
				zbx_strlcpy(item.host.ipmi_password, row[12], sizeof(item.host.ipmi_password));
			}

			if (SUCCEED == (ret = parse_ipmi_command(p, item.ipmi_sensor, &val)))
			{
				item.key = item.ipmi_sensor;
				ret = set_ipmi_control_value(&item, val, error, sizeof(error));
			}
		}
		else
		{
#endif
			param = zbx_dyn_escape_string(p, "\"");
			item.key = zbx_dsprintf(NULL, "system.run[\"%s\",\"nowait\"]", param);
			zbx_free(param);

			init_result(&agent_result);

			alarm(CONFIG_TIMEOUT);
			ret = get_value_agent(&item, &agent_result);
			alarm(0);

			free_result(&agent_result);

			zbx_free(item.key);
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
 * Return value: 0 - correct command is read                                  *
 *               1 - EOL                                                      *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
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
 * Function: select hostid of discovered host                                 *
 *                                                                            *
 * Purpose: select discovered host                                            *
 *                                                                            *
 * Parameters: dhostid - discovered host id                                   *
 *                                                                            *
 * Return value: hostid - existing hostid, 0 - if not found                   *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
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
					"select h.hostid"
					" from hosts h,dservices ds"
					" where ds.ip=h.ip"
						" and ds.dhostid=" ZBX_FS_UI64
						DB_NODE
					" order by h.hostid",
					event->objectid, DBnode_local("h.hostid"));
			break;
		case EVENT_OBJECT_DSERVICE:
			zbx_snprintf(sql, sizeof(sql),
					"select h.hostid"
					" from hosts h,dservices ds"
					" where ds.ip=h.ip"
						" and ds.dserviceid=" ZBX_FS_UI64
						DB_NODE
					" order by h.hostid",
					event->objectid, DBnode_local("h.hostid"));
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
 * Function: get_discovered_agent_port                                        *
 *                                                                            *
 * Purpose: return port of the discovered zabbix_agent                        *
 *                                                                            *
 * Return value: discovered port number, otherwise default port - 10050       *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
static unsigned short	get_discovered_agent_port(DB_EVENT *event)
{
	const char	*__function_name = "get_discovered_agent_port";
	DB_RESULT	result;
	DB_ROW		row;
	unsigned short	port = 10050;
	char		sql[256];

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (event->source != EVENT_SOURCE_DISCOVERY)
		goto end;

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
		goto end;
	}

	result = DBselectN(sql, 1);
	if (NULL != (row = DBfetch(result)))
	{
		port = atoi(row[0]);

		zabbix_log(LOG_LEVEL_DEBUG, "%s() port:%d",
				__function_name, (int)port);
	}
	DBfree_result(result);
end:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);

	return port;
}

/******************************************************************************
 *                                                                            *
 * Function: add_discovered_host_groups                                       *
 *                                                                            *
 * Purpose: add group to host if not added already                            *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
static void	add_discovered_host_groups(zbx_uint64_t hostid, zbx_vector_uint64_t *groupids)
{
	const char	*__function_name = "add_discovered_host_groups";

	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	groupid;
	char		*sql = NULL;
	int		sql_alloc = 256, sql_offset = 0, i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	sql = zbx_malloc(sql, sql_alloc);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 256,
			"select groupid"
			" from hosts_groups"
			" where hostid=" ZBX_FS_UI64
				" and",
			hostid);
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "groupid", groupids->values, groupids->values_num);

	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(groupid, row[0]);

		if (FAIL == (i = zbx_vector_uint64_bsearch(groupids, groupid, ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		zbx_vector_uint64_remove_noorder(groupids, i);
	}
	DBfree_result(result);

	if (0 != groupids->values_num)
	{
		const char	*ins_hosts_groups_sql = "insert into hosts_groups (hostgroupid,hostid,groupid) values ";
		zbx_uint64_t	hostgroupid;
#ifdef HAVE_MULTIROW_INSERT
		const char	*row_dl = ",";
#else
		const char	*row_dl = ";\n";
#endif

		hostgroupid = DBget_maxid_num("hosts_groups", groupids->values_num);

		sql_offset = 0;
#ifdef HAVE_ORACLE
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 7, "begin\n");
#endif
#ifdef HAVE_MULTIROW_INSERT
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ins_hosts_groups_sql);
#endif
		for (i = 0; i < groupids->values_num; i++)
		{
#ifndef HAVE_MULTIROW_INSERT
			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ins_hosts_groups_sql);
#endif
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 67,
					"(" ZBX_FS_UI64 "," ZBX_FS_UI64 "," ZBX_FS_UI64 ")%s",
					hostgroupid++, hostid, groupids->values[i], row_dl);

#ifdef HAVE_ORACLE
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 6, "end;\n");
#endif
		}

#ifdef HAVE_MULTIROW_INSERT
		sql_offset--;
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");
#endif
		DBexecute("%s", sql);
	}

	zbx_free(sql);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
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
 ******************************************************************************/
static zbx_uint64_t	add_discovered_host(DB_EVENT *event)
{
	const char		*__function_name = "add_discovered_host";

	DB_RESULT		result;
	DB_RESULT		result2;
	DB_ROW			row;
	DB_ROW			row2;
	zbx_uint64_t		hostid = 0, proxy_hostid, host_proxy_hostid;
	char			host[MAX_STRING_LEN], *host_esc, *ip_esc, *host_unique, *host_unique_esc;
	int			port;
	zbx_uint64_t		groupid;
	zbx_vector_uint64_t	groupids;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() eventid:" ZBX_FS_UI64, __function_name, event->eventid);

	zbx_vector_uint64_create(&groupids);

	result = DBselect(
			"select discovery_groupid"
			" from config"
			" where 1=1"
				DB_NODE,
			DBnode_local("configid"));

	if (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(groupid, row[0]);
		zbx_vector_uint64_append(&groupids, groupid);
	}
	else
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot add discovered host: group for discovered hosts is not defined");
		goto clean;
	}
	DBfree_result(result);

	switch (event->object)
	{
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
						" and ds1.dhostid=ds.dhostid"
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
			goto clean;
	}

	if (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(proxy_hostid, row[0]);

		if (EVENT_OBJECT_ZABBIX_ACTIVE == event->object)
		{
			char	sql[512];

			host_esc = DBdyn_escape_string_len(row[1], HOST_HOST_LEN);

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

				DBexecute("insert into hosts (hostid,proxy_hostid,host,useip,dns)"
						" values (" ZBX_FS_UI64 "," ZBX_FS_UI64 ",'%s',0,'%s')",
						hostid, proxy_hostid, host_esc, host_esc);

				add_discovered_host_groups(hostid, &groupids);
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
							proxy_hostid, hostid);
				}
			}
			DBfree_result(result2);

			zbx_free(host_esc);
		}
		else	/* EVENT_OBJECT_DHOST, EVENT_OBJECT_DSERVICE */
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
				hostid = DBget_maxid("hosts");

				/* for host uniqueness purposes */
				if ('\0' != *host)
				{
					/* by host name */
					make_hostname(host);	/* replace not-allowed characters */
					host_unique = DBget_unique_hostname_by_sample(host);
				}
				else
				{
					/* by ip */
					make_hostname(row[1]);	/* replace not-allowed characters */
					host_unique = DBget_unique_hostname_by_sample(row[1]);
				}

				host_unique_esc = DBdyn_escape_string(host_unique);

				DBexecute("insert into hosts (hostid,proxy_hostid,host,useip,ip,dns,port)"
						" values (" ZBX_FS_UI64 "," ZBX_FS_UI64 ",'%s',1,'%s','%s',%d)",
						hostid, proxy_hostid, host_unique_esc, ip_esc, host_esc, port);

				zbx_free(host_unique);
				zbx_free(host_unique_esc);

				add_discovered_host_groups(hostid, &groupids);
			}
			else
			{
				ZBX_STR2UINT64(hostid, row2[0]);
				ZBX_STR2UINT64(host_proxy_hostid, row2[3]);

				if (0 != strcmp(host, row2[1]) || host_proxy_hostid != proxy_hostid)
				{
					DBexecute("update hosts"
							" set dns='%s',proxy_hostid=" ZBX_FS_UI64
							" where hostid=" ZBX_FS_UI64,
							host_esc, proxy_hostid, hostid);
				}
			}
			DBfree_result(result2);

			zbx_free(host_esc);
			zbx_free(ip_esc);
		}
	}
	DBfree_result(result);
clean:
	zbx_vector_uint64_destroy(&groupids);

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
 * Author: Alexei Vladishev                                                   *
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
 * Author: Eugene Grigorjev                                                   *
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
 * Author: Alexander Vladishev                                                *
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
 * Author: Alexander Vladishev                                                *
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
 * Function: op_groups_add                                                    *
 *                                                                            *
 * Purpose: add groups to discovered host                                     *
 *                                                                            *
 * Parameters: event    - [IN] event data                                     *
 *             groupids - [IN] IDs of groups to add                           *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 ******************************************************************************/
void	op_groups_add(DB_EVENT *event, zbx_vector_uint64_t *groupids)
{
	const char	*__function_name = "op_groups_add";
	zbx_uint64_t	hostid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (event->source != EVENT_SOURCE_DISCOVERY && event->source != EVENT_SOURCE_AUTO_REGISTRATION)
		return;

	if (event->object != EVENT_OBJECT_DHOST && event->object != EVENT_OBJECT_DSERVICE && event->object != EVENT_OBJECT_ZABBIX_ACTIVE)
		return;

	if (0 == (hostid = add_discovered_host(event)))
		return;

	add_discovered_host_groups(hostid, groupids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: op_groups_del                                                    *
 *                                                                            *
 * Purpose: delete groups from discovered host                                *
 *                                                                            *
 * Parameters: event    - [IN] event data                                     *
 *             groupids - [IN] IDs of groups to delete                        *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 ******************************************************************************/
void	op_groups_del(DB_EVENT *event, zbx_vector_uint64_t *groupids)
{
	const char	*__function_name = "op_groups_del";

	DB_RESULT	result;
	zbx_uint64_t	hostid;
	char		*sql = NULL;
	int		sql_alloc = 256, sql_offset = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (event->source != EVENT_SOURCE_DISCOVERY)
		return;

	if (event->object != EVENT_OBJECT_DHOST && event->object != EVENT_OBJECT_DSERVICE)
		return;

	if (0 == (hostid = select_discovered_host(event)))
		return;

	sql = zbx_malloc(sql, sql_alloc);

	/* make sure host belongs to at least one hostgroup */
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 256,
		"select groupid"
		" from hosts_groups"
		" where hostid=" ZBX_FS_UI64
			" and not",
		hostid);
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "groupid", groupids->values, groupids->values_num);

	result = DBselectN(sql, 1);

	if (NULL == DBfetch(result))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot remove host \"%s\" from all host groups:"
				" it must belong to at least one", zbx_host_string(hostid));
	}
	else
	{
		sql_offset = 0;
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 256,
				"delete from hosts_groups"
				" where hostid=" ZBX_FS_UI64
					" and",
				hostid);
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "groupid", groupids->values, groupids->values_num);

		DBexecute("%s", sql);
	}
	DBfree_result(result);

	zbx_free(sql);

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
 * Author: Eugene Grigorjev                                                   *
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
 * Author: Eugene Grigorjev                                                   *
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
