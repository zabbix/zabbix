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
#include "nodecommand.h"
#include "comms.h"
#include "zbxserver.h"
#include "db.h"
#include "log.h"
#include "zlog.h"
#include "zbxexec.h"
#include "../poller/checks_ipmi.h"

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
static char	*get_command_by_scriptid(zbx_uint64_t scriptid)
{
	DB_RESULT	db_result;
	DB_ROW		db_row;
	char		*command = NULL;

	db_result = DBselect(
			"select command"
			" from scripts"
			" where scriptid=" ZBX_FS_UI64,
			scriptid);

	if (NULL != (db_row = DBfetch(db_result)))
		command = strdup(db_row[0]);
	DBfree_result(db_result);

	return command;
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
	char		*p, *command, error[MAX_STRING_LEN];
	int		ret = FAIL;
	DC_ITEM		item;
#ifdef HAVE_OPENIPMI
	int		val;
	char		*port;
#endif

	zabbix_log(LOG_LEVEL_DEBUG, "In execute_script() scriptid:" ZBX_FS_UI64
			" hostid:" ZBX_FS_UI64, scriptid, hostid);

	memset(&item, 0, sizeof(item));

	if (FAIL == DCget_host_by_hostid(&item.host, hostid))
	{
		*result = zbx_dsprintf(*result, "NODE %d: Unknown Host ID [" ZBX_FS_UI64 "]",
				CONFIG_NODEID, hostid);
		return ret;
	}

	if (NULL == (command = get_command_by_scriptid(scriptid)))
	{
		*result = zbx_dsprintf(*result, "NODE %d: Unknown Script ID [" ZBX_FS_UI64 "]",
				CONFIG_NODEID, scriptid);
		return ret;
	}

	substitute_simple_macros(NULL, NULL, &item.host, NULL,
			&command, MACRO_TYPE_SCRIPT, NULL, 0);

	zabbix_log(LOG_LEVEL_WARNING, "NODE %d: Executing command: '%s'",
			CONFIG_NODEID, command);

	p = command;
	while (*p == ' ' && *p != '\0')
		p++;

#ifdef HAVE_OPENIPMI
	if (0 == strncmp(p, "IPMI", 4))
	{
		if (SUCCEED == (ret = DCconfig_get_interface_by_type(&item.interface, item.host.hostid,
				INTERFACE_TYPE_IPMI, 1)))
		{
			item.interface.addr = strdup(item.interface.useip ? item.interface.ip_orig : item.interface.dns_orig);
			substitute_simple_macros(NULL, NULL, &item.host, NULL,
					&item.interface.addr, MACRO_TYPE_INTERFACE_ADDR, NULL, 0);

			port = strdup(item.interface.port_orig);
			substitute_simple_macros(NULL, &item.host.hostid, NULL, NULL,
					&port, MACRO_TYPE_INTERFACE_PORT, NULL, 0);
			if (SUCCEED == (ret = is_ushort(port, &item.interface.port)))
			{
				if (SUCCEED == (ret = parse_ipmi_command(p, item.ipmi_sensor, &val)))
				{
					if (SUCCEED == (ret = set_ipmi_control_value(&item, val,
							error, sizeof(error))))
					{
						*result = zbx_dsprintf(*result, "NODE %d: IPMI command successfully executed",
								CONFIG_NODEID);
					}
					else
					{
						*result = zbx_dsprintf(*result, "NODE %d: Cannot execute IPMI command: %s",
								CONFIG_NODEID, error);
						ret = FAIL;
					}
				}
				else
					 *result = zbx_dsprintf(*result, "NODE %d: Cannot parse IPMI command",
							CONFIG_NODEID);
			}
			else
				*result = zbx_dsprintf(*result, "NODE %d: Invalid port number [%s]",
						CONFIG_NODEID, item.interface.port_orig);

			zbx_free(port);
			zbx_free(item.interface.addr);
		}
		else
			*result = zbx_dsprintf(*result, "NODE %d: IPMI host interface is not defined for host [%s]",
					CONFIG_NODEID, item.host.host);
	}
	else
	{
#endif
		alarm(CONFIG_TRAPPER_TIMEOUT);

		if (SUCCEED != (ret = zbx_execute(p, result, error, sizeof(error))))
			*result = zbx_dsprintf(*result, "NODE %d: Cannot execute command: %s",
					CONFIG_NODEID, error);

		alarm(0);
#ifdef HAVE_OPENIPMI
	}
#endif

	zbx_free(command);

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
