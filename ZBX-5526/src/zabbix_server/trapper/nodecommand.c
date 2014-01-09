/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "common.h"
#include "nodecommand.h"
#include "comms.h"
#include "zbxserver.h"
#include "db.h"
#include "log.h"
#include "../scripts.h"

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
	const char	*__function_name = "execute_script";
	char		error[MAX_STRING_LEN];
	int		ret = FAIL, rc;
	DC_HOST		host;
	DB_RESULT	db_result;
	DB_ROW		db_row;
	zbx_script_t	script;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() scriptid:" ZBX_FS_UI64 " hostid:" ZBX_FS_UI64,
			__function_name, scriptid, hostid);

	*error = '\0';

	if (SUCCEED != (rc = DCget_host_by_hostid(&host, hostid)))
	{
		/* let's try to get a host from a database (the host can be disabled) */
		db_result = DBselect("select host,name from hosts where hostid=" ZBX_FS_UI64, hostid);

		if (NULL != (db_row = DBfetch(db_result)))
		{
			memset(&host, 0, sizeof(host));
			host.hostid = hostid;
			strscpy(host.host, db_row[0]);
			strscpy(host.name, db_row[1]);

			rc = SUCCEED;
		}
		DBfree_result(db_result);
	}

	if (SUCCEED != rc)
	{
		zbx_snprintf(error, sizeof(error), "Unknown Host ID [" ZBX_FS_UI64 "]", hostid);
		goto fail;
	}

	zbx_script_init(&script);

	script.type = ZBX_SCRIPT_TYPE_GLOBAL_SCRIPT;
	script.scriptid = scriptid;

	ret = zbx_execute_script(&host, &script, result, error, sizeof(error));

	zbx_script_clean(&script);
fail:
	if (SUCCEED != ret)
	{
		if (0 != CONFIG_NODEID)
			*result = zbx_dsprintf(*result, "NODE %d: %s", CONFIG_NODEID, error);
		else
			*result = zbx_strdup(*result, error);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

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
	if (SUCCEED != zbx_tcp_send_raw(sock, send))
	{
		zabbix_log(LOG_LEVEL_WARNING, "NODE %d: Error sending result of command to node %d",
				CONFIG_NODEID, nodeid);
	}
	alarm(0);

	zbx_json_free(&j);
	zbx_free(result);

	return SUCCEED;
}
