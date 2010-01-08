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
#include "nodecommand.h"
#include "comms.h"
#include "zbxserver.h"
#include "db.h"
#include "log.h"
#include "zlog.h"
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
 * Author: Aleksander Vladishev                                               *
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
 *                FAIL - an error occured                                     *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int	execute_script(zbx_uint64_t scriptid, zbx_uint64_t hostid, char **result)
{
	char		*p, buffer[MAX_STRING_LEN];
	char		*command = NULL;
	int		result_alloc = 256, result_offset = 0,
			ret = FAIL;
	FILE		*f;
	DB_RESULT	db_result;
	DB_ROW		db_row;
	DB_ITEM		item;
#ifdef HAVE_OPENIPMI
	int		val;
	char		error[MAX_STRING_LEN];
#endif

	zabbix_log(LOG_LEVEL_DEBUG, "In execute_script() scriptid:" ZBX_FS_UI64
			" hostid:" ZBX_FS_UI64, scriptid, hostid);

	memset(&item, 0, sizeof(item));

	db_result = DBselect(
			"select hostid,host,useip,dns,ip"
			" from hosts"
			" where hostid=" ZBX_FS_UI64
				DB_NODE,
			hostid,
			DBnode_local("hostid"));

	if (NULL != (db_row = DBfetch(db_result)))
	{
		ZBX_STR2UINT64(item.hostid, db_row[0]);
		item.host_name = strdup(db_row[1]);
		item.useip = atoi(db_row[2]);
		item.host_dns = strdup(db_row[3]);
		item.host_ip = strdup(db_row[4]);
	}
	DBfree_result(db_result);

	if (0 == item.hostid)
	{
		*result = zbx_dsprintf(*result, "NODE %d: Unknown Host ID [" ZBX_FS_UI64 "]",
				CONFIG_NODEID, hostid);
		goto clean;
	}

	if (NULL == (command = get_command_by_scriptid(scriptid)))
	{
		*result = zbx_dsprintf(*result, "NODE %d: Unknowh Script ID [" ZBX_FS_UI64 "]",
				CONFIG_NODEID, scriptid);
		goto clean;
	}

	substitute_simple_macros(NULL, NULL, &item, NULL,
			&command, MACRO_TYPE_SCRIPT);

	zabbix_log(LOG_LEVEL_WARNING, "NODE %d: Executing command: '%s'",
			CONFIG_NODEID, command);

	p = command;
	while (*p == ' ' && *p != '\0')
		p++;

#ifdef HAVE_OPENIPMI
	if (0 == strncmp(p, "IPMI", 4))
	{
		db_result = DBselect("select distinct host,ip,useip,port,dns,useipmi,ipmi_port,ipmi_authtype,"
				"ipmi_privilege,ipmi_username,ipmi_password from hosts where hostid=" ZBX_FS_UI64 DB_NODE,
				hostid,
				DBnode_local("hostid"));

		if (NULL != (db_row = DBfetch(db_result)))
		{
			item.host_name		= db_row[0];
			item.host_ip		= db_row[1];
			item.useip		= atoi(db_row[2]);
			item.port		= atoi(db_row[3]);
			item.host_dns		= db_row[4];

			item.useipmi		= atoi(db_row[5]);
			item.ipmi_port		= atoi(db_row[6]);
			item.ipmi_authtype	= atoi(db_row[7]);
			item.ipmi_privilege	= atoi(db_row[8]);
			item.ipmi_username	= db_row[9];
			item.ipmi_password	= db_row[10];

			if (SUCCEED == (ret = parse_ipmi_command(p, &item.ipmi_sensor, &val)))
			{
				if (SUCCEED == (ret = set_ipmi_control_value(&item, val, buffer, sizeof(buffer))))
				{
					*result = zbx_dsprintf(*result, "NODE %d: IPMI command successfully executed",
							CONFIG_NODEID);
				}
				else
					*result = zbx_dsprintf(*result, "NODE %d: Cannot execute IPMI command: %s",
							CONFIG_NODEID, error);
			}
			else
				*result = zbx_dsprintf(*result, "NODE %d: Cannot parse IPMI command",
						CONFIG_NODEID);
		}
		else
			*result = zbx_dsprintf(*result, "NODE %d: Unknown Host ID [" ZBX_FS_UI64 "]",
					CONFIG_NODEID, hostid);
		DBfree_result(db_result);
	}
	else
	{
#endif
		if(0 != (f = popen(p, "r")))
		{
			*result = zbx_malloc(*result, result_alloc);
			**result = '\0';

			while (NULL != fgets(buffer, sizeof(buffer), f))
				zbx_snprintf_alloc(result, &result_alloc, &result_offset,
						strlen(buffer) + 1, "%s", buffer);

			pclose(f);

			ret = SUCCEED;
		}
		else
			*result = zbx_dsprintf(*result, "NODE %d: Cannot execute command: %s",
					CONFIG_NODEID, strerror(errno));
#ifdef HAVE_OPENIPMI
	}
#endif
clean:
	zbx_free(command);
	zbx_free(item.host_name);
	zbx_free(item.host_ip);
	zbx_free(item.host_dns);

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
 *                FAIL - an error occured                                     *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int	send_script(int nodeid, const char *data, char **result)
{
	DB_RESULT	dbresult;
	DB_ROW		dbrow;
	int		ret = FAIL;
	zbx_sock_t	sock;
	char		*answer;

	zabbix_log(LOG_LEVEL_DEBUG, "In send_script(nodeid:%d)", nodeid);

	dbresult = DBselect(
			"select ip,port"
			" from nodes"
			" where nodeid=%d",
			nodeid);

	if (NULL != (dbrow = DBfetch(dbresult))) {
		if (SUCCEED == (ret = zbx_tcp_connect(&sock, CONFIG_SOURCE_IP, dbrow[0], atoi(dbrow[1]), ZABBIX_TRAPPER_TIMEOUT))) {
			if (FAIL == (ret = zbx_tcp_send(&sock, data))) {
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

	DBfree_result(dbresult);

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
 *                FAIL - an error occured                                     *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int	get_next_point_to_node(int current_nodeid, int slave_nodeid, int *nodeid)
{
	DB_RESULT	dbresult;
	DB_ROW		dbrow;
	int		id, res = FAIL;

	dbresult = DBselect("select nodeid from nodes where masterid=%d",
		current_nodeid);

	while (NULL != (dbrow = DBfetch(dbresult))) {
		id = atoi(dbrow[0]);
		if (id == slave_nodeid || SUCCEED == get_next_point_to_node(id, slave_nodeid, NULL)) {
			if (NULL != nodeid)
				*nodeid = id;
			res = SUCCEED;
			break;
		}
	}
	DBfree_result(dbresult);

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
 *                FAIL - an error occured                                     *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int	node_process_command(zbx_sock_t *sock, const char *data, struct zbx_json_parse *jp)
{
	char		*result = NULL, *send = NULL, tmp[64];
	int		nodeid, next_nodeid, ret = FAIL;
	zbx_uint64_t	scriptid, hostid;

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

	if (nodeid == CONFIG_NODEID)
	{
		ret = execute_script(scriptid, hostid, &result);
		send = zbx_dsprintf(send, "%d%c%s", ret, ZBX_DM_DELIMITER, result);
	}
	else if (SUCCEED == get_next_point_to_node(CONFIG_NODEID, nodeid, &next_nodeid))
	{
		zabbix_log(LOG_LEVEL_WARNING, "NODE %d: Sending command for Node %d to Node %d",
				CONFIG_NODEID, nodeid, next_nodeid);

		if (FAIL == (ret = send_script(next_nodeid, data, &result)))
			send = zbx_dsprintf(send, "%d%c%s", ret, ZBX_DM_DELIMITER, result);
		else
			send = strdup(result);
	}
	else
	{
		send = zbx_dsprintf(send, "%d%cNODE %d: Unknown Node ID [%d]",
				ret, ZBX_DM_DELIMITER, CONFIG_NODEID, nodeid);
	}

	alarm(CONFIG_TIMEOUT);
	if (zbx_tcp_send_raw(sock, send) != SUCCEED)
	{
		zabbix_log(LOG_LEVEL_WARNING, "NODE %d: Error sending result of command to node %d",
			CONFIG_NODEID,
			nodeid);
	}
	alarm(0);

	zbx_free(result);
	zbx_free(send);

	return SUCCEED;
}
