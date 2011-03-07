/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
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
#include "nodecommand.h"
#include "comms.h"
#include "zbxserver.h"
#include "db.h"
#include "log.h"
#include "zlog.h"
#include "zbxexec.h"
#include "../poller/checks_ipmi.h"
#include "../poller/checks_agent.h"

/******************************************************************************
 *                                                                            *
 * Function: get_command_by_scriptid                                          *
 *                                                                            *
 * Purpose: get script by scriptid                                            *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: NULL if script not found                                     *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int	DBget_script_by_scriptid(zbx_uint64_t scriptid, DB_SCRIPT *script)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		res = FAIL;

	result = DBselect(
			"select command,groupid,type,execute_on"
			" from scripts"
			" where scriptid=" ZBX_FS_UI64,
			scriptid);

	if (NULL != (row = DBfetch(result)))
	{
		script->command = zbx_strdup(script->command, row[0]);
		ZBX_DBROW2UINT64(script->groupid, row[1]);
		script->type = (unsigned char)atoi(row[2]);
		script->execute_on = (unsigned char)atoi(row[3]);
		res = SUCCEED;
	}
	DBfree_result(result);

	return res;
}

static int	zbx_execute_ipmi_command(DC_ITEM *item, const char *command, char *error, size_t max_error_len)
{
	int	ret = FAIL;
#ifdef HAVE_OPENIPMI
	int	val;
	char	*port = NULL;
#endif	/* HAVE_OPENIPMI */

#ifdef HAVE_OPENIPMI
	if (SUCCEED != (ret = DCconfig_get_interface_by_type(&item->interface, item->host.hostid, INTERFACE_TYPE_IPMI)))
	{
		zbx_snprintf(error, max_error_len, "IPMI interface is not defined for host [%s]", item->host.host);
		return ret;
	}

	item->interface.addr = zbx_strdup(item->interface.addr,
			item->interface.useip ? item->interface.ip_orig : item->interface.dns_orig);
	substitute_simple_macros(NULL, NULL, &item->host, NULL,
			&item->interface.addr, MACRO_TYPE_INTERFACE_ADDR, NULL, 0);

	port = zbx_strdup(port, item->interface.port_orig);
	substitute_simple_macros(NULL, &item->host.hostid, NULL, NULL, &port, MACRO_TYPE_INTERFACE_PORT, NULL, 0);

	if (SUCCEED != (ret = is_ushort(port, &item->interface.port)))
	{
		zbx_snprintf(error, max_error_len, "Invalid port number [%s]", item->interface.port_orig);
		goto clean;
	}

	if (SUCCEED == (ret = parse_ipmi_command(command, item->ipmi_sensor, &val, error, max_error_len)))
	{
		if (SUCCEED != (ret = set_ipmi_control_value(item, val, error, max_error_len)))
			ret = FAIL;
	}
clean:
	zbx_free(port);
	zbx_free(item->interface.addr);
#else
	zbx_strlcpy(error, "Support for IPMI commands was not compiled in", max_error_len);
#endif	/* HAVE_OPENIPMI */

	return ret;
}

static int	zbx_execute_script_on_agent(DC_ITEM *item, char *command, char **result,
		char *error, size_t max_error_len)
{
	int		ret;
	AGENT_RESULT	agent_result;
	char		*param, *port = NULL;

	if (SUCCEED != (ret = DCconfig_get_interface_by_type(&item->interface, item->host.hostid, INTERFACE_TYPE_AGENT)))
	{
		zbx_snprintf(error, max_error_len, "Zabbix agent interface is not defined for host [%s]", item->host.host);
		return ret;
	}

	item->interface.addr = (item->interface.useip ? item->interface.ip_orig : item->interface.dns_orig);

	port = zbx_strdup(port, item->interface.port_orig);
	substitute_simple_macros(NULL, &item->host.hostid, NULL, NULL, &port, MACRO_TYPE_INTERFACE_PORT, NULL, 0);

	if (SUCCEED != (ret = is_ushort(port, &item->interface.port)))
	{
		zbx_snprintf(error, max_error_len, "Invalid port number [%s]", item->interface.port_orig);
		goto clean;
	}

	dos2unix(command);	/* CR+LF (Windows) => LF (Unix) */

	param = dyn_escape_param(command);
	item->key = zbx_dsprintf(NULL, "system.run[\"%s\",\"wait\"]", param);
	item->value_type = ITEM_VALUE_TYPE_TEXT;
	zbx_free(param);

	init_result(&agent_result);

	alarm(CONFIG_TIMEOUT);

	if (SUCCEED != (ret = get_value_agent(item, &agent_result)))
	{
		if (ISSET_MSG(&agent_result))
			zbx_strlcpy(error, agent_result.msg, max_error_len);
		else
		{
			THIS_SHOULD_NEVER_HAPPEN;
			*error = '\0';
		}
		ret = FAIL;
	}
	else
	{
		if (ISSET_TEXT(&agent_result))
			*result = zbx_strdup(*result, agent_result.text);
		else
		{
			THIS_SHOULD_NEVER_HAPPEN;
			*result = zbx_strdup(*result, "");
		}
	}

	alarm(0);

	free_result(&agent_result);

	zbx_free(item->key);
clean:
	zbx_free(port);

	return ret;
}

static int	zbx_execute_script(DC_ITEM *item, unsigned char execute_on, char *command, char **result,
		char *error, size_t max_error_len)
{
	int	ret = FAIL;

	switch (execute_on)
	{
		case ZBX_SCRIPT_EXECUTE_ON_AGENT:
			ret = zbx_execute_script_on_agent(item, command, result, error, max_error_len);
			break;
		case ZBX_SCRIPT_EXECUTE_ON_SERVER:
			ret = zbx_execute(command, result, error, max_error_len, CONFIG_TRAPPER_TIMEOUT);
			break;
		default:
			zbx_snprintf(error, max_error_len, "Invalid 'Execute on' option [%d]", (int)execute_on);
	}

	return ret;
}

static int	check_script_permissions(zbx_uint64_t groupid, zbx_uint64_t hostid, char *error, size_t max_error_len)
{
	DB_RESULT	result;
	int		ret = SUCCEED;

	if (0 == groupid)
		return ret;

	result = DBselect(
			"select hostid"
			" from hosts_groups"
			" where hostid=" ZBX_FS_UI64
				" and groupid=" ZBX_FS_UI64,
			hostid, groupid);

	if (NULL == DBfetch(result))
	{
		zbx_strlcpy(error, "Insufficient permissions. Host is not in an allowed host group.", max_error_len);
		ret = FAIL;
	}
	DBfree_result(result);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: execute_script                                                   *
 *                                                                            *
 * Purpose: executing command                                                 *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:  SUCCEED - processed successfully                            *
 *                FAIL - an error occurred                                    *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int	execute_script(zbx_uint64_t scriptid, zbx_uint64_t hostid, char **result)
{
	char		error[MAX_STRING_LEN];
	int		ret = FAIL;
	DC_ITEM		item;
	DB_SCRIPT	script;

	zabbix_log(LOG_LEVEL_DEBUG, "In execute_script() scriptid:" ZBX_FS_UI64
			" hostid:" ZBX_FS_UI64, scriptid, hostid);

	*error = '\0';
	memset(&item, 0, sizeof(item));
	memset(&script, 0, sizeof(script));

	if (SUCCEED != DCget_host_by_hostid(&item.host, hostid))
	{
		*result = zbx_dsprintf(*result, "NODE %d: Unknown Host ID [" ZBX_FS_UI64 "]",
				CONFIG_NODEID, hostid);
		return ret;
	}

	if (SUCCEED != DBget_script_by_scriptid(scriptid, &script))
	{
		*result = zbx_dsprintf(*result, "NODE %d: Unknown Script ID [" ZBX_FS_UI64 "]",
				CONFIG_NODEID, scriptid);
		return ret;
	}

	if (SUCCEED != check_script_permissions(script.groupid, hostid, error, sizeof(error)))
	{
		*result = zbx_dsprintf(*result, "NODE %d: %s", CONFIG_NODEID, error);
		return ret;
	}

	substitute_simple_macros(NULL, NULL, &item.host, NULL,
			&script.command, MACRO_TYPE_SCRIPT, NULL, 0);

	zabbix_log(LOG_LEVEL_WARNING, "NODE %d: Executing command: '%s'",
			CONFIG_NODEID, script.command);

	switch (script.type)
	{
		case ZBX_SCRIPT_TYPE_IPMI:
			if (SUCCEED == (ret = zbx_execute_ipmi_command(&item, script.command, error, sizeof(error))))
				*result = zbx_strdup(*result, "IPMI command successfully executed");
			break;
		case ZBX_SCRIPT_TYPE_SCRIPT:
			ret = zbx_execute_script(&item, script.execute_on, script.command, result,
					error, sizeof(error));
			break;
		default:
			zbx_strlcpy(error, "Invalid command type [%d]", (int)script.type);
	}

	if (SUCCEED != ret)
	{
		if (0 != CONFIG_NODEID)
			*result = zbx_dsprintf(*result, "NODE %d: %s", CONFIG_NODEID, error);
		else
			*result = zbx_strdup(*result, error);
	}
	else if (NULL == *result)
		*result = zbx_strdup(*result, "");

	zbx_free(script.command);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: send_script                                                      *
 *                                                                            *
 * Purpose: sending command to slave node                                     *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:  SUCCEED - processed successfully                            *
 *                FAIL - an error occurred                                    *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int	send_script(int nodeid, const char *data, char **result)
{
	DB_RESULT		db_result;
	DB_ROW			db_row;
	int			ret = FAIL;
	zbx_sock_t		sock;
	char			*answer;

	zabbix_log(LOG_LEVEL_DEBUG, "In send_script(nodeid:%d)", nodeid);

	db_result = DBselect(
			"select ip,port"
			" from nodes"
			" where nodeid=%d",
			nodeid);

	if (NULL != (db_row = DBfetch(db_result)))
	{
		if (SUCCEED == (ret = zbx_tcp_connect(&sock, CONFIG_SOURCE_IP,
				db_row[0], atoi(db_row[1]), CONFIG_TRAPPER_TIMEOUT)))
		{
			if (FAIL == (ret = zbx_tcp_send(&sock, data)))
			{
				*result = zbx_dsprintf(*result, "NODE %d: Error while sending data to Node [%d]: %s",
						CONFIG_NODEID, nodeid, zbx_tcp_strerror());
				goto exit_sock;
			}

			if (SUCCEED == (ret = zbx_tcp_recv(&sock, &answer)))
				*result = zbx_dsprintf(*result, "%s", answer);
			else
				*result = zbx_dsprintf(*result, "NODE %d: Error while receiving data from Node [%d]: %s",
						CONFIG_NODEID, nodeid, zbx_tcp_strerror());
exit_sock:
			zbx_tcp_close(&sock);
		}
		else
			*result = zbx_dsprintf(*result, "NODE %d: Unable to connect to Node [%d]: %s",
					CONFIG_NODEID, nodeid, zbx_tcp_strerror());
	}
	else
		*result = zbx_dsprintf(*result, "NODE %d: Unknown Node ID [%d]",
				CONFIG_NODEID, nodeid);

	DBfree_result(db_result);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: get_next_point_to_node                                           *
 *                                                                            *
 * Purpose: find next point to slave node                                     *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:  SUCCEED - processed successfully                            *
 *                FAIL - an error occurred                                    *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int	get_next_point_to_node(int current_nodeid, int slave_nodeid, int *nodeid)
{
	DB_RESULT	db_result;
	DB_ROW		db_row;
	int		id, res = FAIL;

	db_result = DBselect("select nodeid from nodes where masterid=%d",
		current_nodeid);

	while (NULL != (db_row = DBfetch(db_result)))
	{
		id = atoi(db_row[0]);
		if (id == slave_nodeid || SUCCEED == get_next_point_to_node(id, slave_nodeid, NULL))
		{
			if (NULL != nodeid)
				*nodeid = id;
			res = SUCCEED;
			break;
		}
	}
	DBfree_result(db_result);

	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: node_process_command                                             *
 *                                                                            *
 * Purpose: process command received from a master node or php                *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:  SUCCEED - processed successfully                            *
 *                FAIL - an error occurred                                    *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int	node_process_command(zbx_sock_t *sock, const char *data, struct zbx_json_parse *jp)
{
	char		*result = NULL, *send, tmp[64];
	const char	*response;
	int		nodeid, next_nodeid, ret = FAIL;
	zbx_uint64_t	scriptid, hostid;
	struct zbx_json	j;

	zabbix_log(LOG_LEVEL_DEBUG, "In node_process_command()");

	if (SUCCEED != zbx_json_value_by_name(jp, ZBX_PROTO_TAG_NODEID, tmp, sizeof(tmp)))
		return FAIL;
	nodeid = atoi(tmp);

	if (SUCCEED != zbx_json_value_by_name(jp, ZBX_PROTO_TAG_SCRIPTID, tmp, sizeof(tmp)))
		return FAIL;
	ZBX_STR2UINT64(scriptid, tmp);

	if (SUCCEED != zbx_json_value_by_name(jp, ZBX_PROTO_TAG_HOSTID, tmp, sizeof(tmp)))
		return FAIL;
	ZBX_STR2UINT64(hostid, tmp);

	zbx_json_init(&j, 256);

	if (nodeid == CONFIG_NODEID)
	{
		ret = execute_script(scriptid, hostid, &result);

		response = (FAIL == ret) ? ZBX_PROTO_VALUE_FAILED : ZBX_PROTO_VALUE_SUCCESS;

		zbx_json_addstring(&j, ZBX_PROTO_TAG_RESPONSE, response, ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&j, ZBX_PROTO_TAG_VALUE, result, ZBX_JSON_TYPE_STRING);
		send = j.buffer;
	}
	else if (SUCCEED == get_next_point_to_node(CONFIG_NODEID, nodeid, &next_nodeid))
	{
		zabbix_log(LOG_LEVEL_WARNING, "NODE %d: Sending command for Node %d to Node %d",
				CONFIG_NODEID, nodeid, next_nodeid);

		if (FAIL == (ret = send_script(next_nodeid, data, &result)))
		{
			zbx_json_addstring(&j, ZBX_PROTO_TAG_RESPONSE, ZBX_PROTO_VALUE_FAILED, ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&j, ZBX_PROTO_TAG_VALUE, result, ZBX_JSON_TYPE_STRING);
			send = j.buffer;
		}
		else
			send = result;
	}
	else
	{
		result = zbx_dsprintf(result, "NODE %d: Unknown Node ID [%d]",
				CONFIG_NODEID, nodeid);

		zbx_json_addstring(&j, ZBX_PROTO_TAG_RESPONSE, ZBX_PROTO_VALUE_FAILED, ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&j, ZBX_PROTO_TAG_VALUE, result, ZBX_JSON_TYPE_STRING);
		send = j.buffer;
	}

	alarm(CONFIG_TIMEOUT);
	if (zbx_tcp_send_raw(sock, send) != SUCCEED)
	{
		zabbix_log(LOG_LEVEL_WARNING, "NODE %d: Error sending result of command to node %d",
			CONFIG_NODEID,
			nodeid);
	}
	alarm(0);

	zbx_json_free(&j);
	zbx_free(result);

	return SUCCEED;
}
