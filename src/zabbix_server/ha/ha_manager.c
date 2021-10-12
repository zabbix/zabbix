/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

#include "db.h"
#include "log.h"
#include "zbxipcservice.h"
#include "zbxserialize.h"
#include "ha.h"
#include "threads.h"
#include "zbxjson.h"
#include "../../libs/zbxalgo/vectorimpl.h"

#define ZBX_HA_POLL_PERIOD	5

// TODO: use more realistic timeout after testing
#define ZBX_HA_SERVICE_TIMEOUT	1

#define ZBX_HA_DEFAULT_FAILOVER_DELAY	SEC_PER_MIN

#define ZBX_HA_NODE_LOCK	1

static pid_t			ha_pid = ZBX_THREAD_ERROR;
static zbx_ipc_async_socket_t	ha_socket;

extern char	*CONFIG_HA_NODE_NAME;
extern char	*CONFIG_EXTERNAL_ADDRESS;
extern char	*CONFIG_LISTEN_IP;
extern int	CONFIG_LISTEN_PORT;

extern zbx_cuid_t	ha_sessionid;

#define ZBX_HA_IS_CLUSTER()	(NULL != CONFIG_HA_NODE_NAME && '\0' != *CONFIG_HA_NODE_NAME)

typedef struct
{
	zbx_cuid_t	nodeid;

	/* HA status */
	int		ha_status;

	/* database connection status */
	int		db_status;

	/* timestamp in database time */
	int		db_time;

	int		failover_delay;

	/* last access time of active node */
	int		lastaccess_active;

	/* number of 5 second ticks since HA manager restart */
	int		ticks;

	/* number of ticks without database connection */
	int		offline_ticks;

	/* number of ticks active node has not been updated its lastaccess */
	int		offline_ticks_active;

	const char	*name;
	char		*error;
}
zbx_ha_info_t;

ZBX_THREAD_ENTRY(ha_manager_thread, args);

typedef struct
{
	zbx_cuid_t	nodeid;
	zbx_cuid_t	sessionid;
	char		*name;
	char		*address;
	int		status;
	int		lastaccess;
}
zbx_ha_node_t;

ZBX_PTR_VECTOR_DECL(ha_node, zbx_ha_node_t *)
ZBX_PTR_VECTOR_IMPL(ha_node, zbx_ha_node_t *)

static void	zbx_ha_node_free(zbx_ha_node_t *node)
{
	zbx_free(node->name);
	zbx_free(node->address);
	zbx_free(node);
}

/******************************************************************************
 *                                                                            *
 * Function: ha_send_manager_message                                          *
 *                                                                            *
 * Purpose: send message to HA manager                                        *
 *                                                                            *
 ******************************************************************************/
static int	ha_send_manager_message(zbx_uint32_t code, char **error)
{
	if (FAIL == zbx_ipc_async_socket_send(&ha_socket, code, NULL, 0))
	{
		*error = zbx_strdup(NULL, "cannot queue message to HA manager service");
		return FAIL;
	}

	if (FAIL == zbx_ipc_async_socket_flush(&ha_socket, ZBX_HA_SERVICE_TIMEOUT))
	{
		*error = zbx_strdup(NULL, "cannot send message to HA manager service");
		return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: ha_notify_parent                                                 *
 *                                                                            *
 * Purpose: notify parent process                                             *
 *                                                                            *
 ******************************************************************************/
static void	ha_notify_parent(zbx_ipc_client_t *client, int status, const char *info)
{
	zbx_uint32_t	len = 0, info_len;
	unsigned char	*ptr, *data;
	int		ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() status:%s info:%s", __func__, zbx_ha_status_str(status),
			ZBX_NULL2EMPTY_STR(info));

	zbx_serialize_prepare_value(len, status);
	zbx_serialize_prepare_str(len, info);

	ptr = data = (unsigned char *)zbx_malloc(NULL, len);
	ptr += zbx_serialize_value(ptr, status);
	(void)zbx_serialize_str(ptr, info, info_len);

	ret = zbx_ipc_client_send(client, ZBX_IPC_SERVICE_HA_GET_NODES, data, len);
	zbx_free(data);

	if (SUCCEED != ret)
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot send HA notification to main process");
		exit(EXIT_FAILURE);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Function: ha_recv_status                                                   *
 *                                                                            *
 * Purpose: receive status message from HA service                            *
 *                                                                            *
 ******************************************************************************/
static int	ha_recv_status(int *status, int timeout, char **error)
{
	zbx_ipc_message_t	*message = NULL;
	int			ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (SUCCEED != zbx_ipc_async_socket_recv(&ha_socket, timeout, &message))
	{
		*error = zbx_strdup(NULL, "cannot receive message from HA manager service");
		ret = FAIL;
		goto out;
	}

	if (NULL != message)
	{
		unsigned char	*ptr;
		zbx_uint32_t	len;

		switch (message->code)
		{
			case ZBX_IPC_SERVICE_HA_GET_NODES:
				ptr = message->data;
				ptr += zbx_deserialize_value(ptr, status);
				(void)zbx_deserialize_str(ptr, error, len);

				if (ZBX_NODE_STATUS_ERROR == *status)
					ret = FAIL;
				break;
			default:
				*status = ZBX_NODE_STATUS_UNKNOWN;
		}

		zbx_ipc_message_free(message);
	}
	else
		*status = ZBX_NODE_STATUS_UNKNOWN;

out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() status:%d", __func__, *status);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_ha_set_error                                                 *
 *                                                                            *
 * Purpose: set HA manager error                                              *
 *                                                                            *
 ******************************************************************************/
static void	ha_set_error(zbx_ha_info_t *info, const char *fmt, ...)
{
	va_list	args;
	size_t	len;

	va_start(args, fmt);
	len = (size_t)vsnprintf(NULL, 0, fmt, args) + 1;
	va_end(args);

	info->error = (char *)zbx_malloc(info->error, len);

	va_start(args, fmt);
	vsnprintf(info->error, len, fmt, args);
	va_end(args);

	info->ha_status = ZBX_NODE_STATUS_ERROR;
}

/******************************************************************************
 *                                                                            *
 * Function: ha_db_update_config                                              *
 *                                                                            *
 * Purpose: update HA configuration from database                             *
 *                                                                            *
 ******************************************************************************/
static int	ha_db_update_config(zbx_ha_info_t *info)
{
	DB_RESULT	result;
	DB_ROW		row;

	result = DBselect_once("select ha_failover_delay from config");

	if (NULL == result || ZBX_DB_DOWN == (intptr_t)result)
		return FAIL;

	if (NULL == (row = DBfetch(result)) ||
			SUCCEED != is_time_suffix(row[0], &info->failover_delay, ZBX_LENGTH_UNLIMITED))
	{
		THIS_SHOULD_NEVER_HAPPEN;
	}
	DBfree_result(result);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: ha_db_get_nodes                                                  *
 *                                                                            *
 * Purpose: get all nodes from database                                       *
 *                                                                            *
 * Return value: SUCCEED - the nodes were retrived from database              *
 *               FAIL    - database/connection error                          *
 *                                                                            *
 ******************************************************************************/
static int	ha_db_get_nodes(zbx_vector_ha_node_t *nodes, int lock)
{
	DB_RESULT	result;
	DB_ROW		row;
	zbx_ha_node_t	*node;

	result = DBselect_once("select ha_nodeid,name,status,lastaccess,address,port,sessionid"
			" from ha_node order by ha_nodeid%s",
			(0 == lock ? "" : ZBX_FOR_UPDATE));

	if (NULL == result || ZBX_DB_DOWN == (intptr_t)result)
		return FAIL;

	while (NULL != (row = DBfetch(result)))
	{
		node = (zbx_ha_node_t *)zbx_malloc(NULL, sizeof(zbx_ha_node_t));
		zbx_strlcpy(node->nodeid.str, row[0], sizeof(node->nodeid));
		node->name = zbx_strdup(NULL, row[1]);
		node->status = atoi(row[2]);
		node->lastaccess = atoi(row[3]);
		node->address = zbx_dsprintf(NULL, "%s:%s", row[4], row[5]);
		zbx_strlcpy(node->sessionid.str, row[6], sizeof(node->sessionid));
		zbx_vector_ha_node_append(nodes, node);
	}

	DBfree_result(result);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: ha_get_external_address                                          *
 *                                                                            *
 * Purpose: get server external address and port from configuration           *
 *                                                                            *
 ******************************************************************************/
static void	ha_get_external_address(char **address, unsigned short *port)
{
	if (NULL != CONFIG_EXTERNAL_ADDRESS)
		(void)parse_serveractive_element(CONFIG_EXTERNAL_ADDRESS, address, port, 0);

	if (NULL == *address)
	{
		if (NULL != CONFIG_LISTEN_IP)
		{
			char	*tmp;

			zbx_strsplit(CONFIG_LISTEN_IP, ',', address, &tmp);
			zbx_free(tmp);
		}
		else
			*address = zbx_strdup(NULL, "localhost");
	}

	if (0 == *port)
		*port = CONFIG_LISTEN_PORT;
}

/******************************************************************************
 *                                                                            *
 * Function: ha_db_lock_nodes                                                 *
 *                                                                            *
 * Purpose: lock nodes in database                                            *
 *                                                                            *
 * Comments: To lock ha_node table it must have at least one node             *
 *                                                                            *
 ******************************************************************************/
static int	ha_db_lock_nodes(zbx_ha_info_t *info)
{
	DB_RESULT	result;

	result = DBselect_once("select null from ha_node" ZBX_FOR_UPDATE);

	if (NULL == result)
	{
		ha_set_error(info, "cannot connect to database");
		return FAIL;
	}

	if (ZBX_DB_DOWN == (intptr_t)result)
		return FAIL;

	DBfree_result(result);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: ha_db_begin                                                      *
 *                                                                            *
 * Purpose: start database transaction                                        *
 *                                                                            *
 ******************************************************************************/
static int	ha_db_begin(zbx_ha_info_t *info)
{
	if (ZBX_DB_DOWN == info->db_status)
		info->db_status = DBconnect(ZBX_DB_CONNECT_ONCE);

	if (ZBX_DB_OK == info->db_status)
		info->db_status = zbx_db_begin();

	return info->db_status;
}

/******************************************************************************
 *                                                                            *
 * Function: ha_db_rollback                                                   *
 *                                                                            *
 * Purpose: roll back database transaction                                    *
 *                                                                            *
 ******************************************************************************/
static int	ha_db_rollback(zbx_ha_info_t *info)
{
	if (ZBX_DB_OK > (info->db_status = zbx_db_rollback()))
	{
		if (ZBX_DB_DOWN == info->db_status)
			DBclose();
	}

	if (ZBX_DB_FAIL == info->db_status)
		ha_set_error(info, "database error");

	return info->db_status;
}

/******************************************************************************
 *                                                                            *
 * Function: ha_db_commit                                                     *
 *                                                                            *
 * Purpose: commit/rollback database transaction depending on commit result   *
 *                                                                            *
 ******************************************************************************/
static int	ha_db_commit(zbx_ha_info_t *info)
{
	if (ZBX_DB_OK == info->db_status)
		info->db_status = zbx_db_commit();

	if (ZBX_DB_OK != info->db_status)
	{
		zbx_db_rollback();

		if (ZBX_DB_FAIL == info->db_status)
			ha_set_error(info, "database error");
		else
			DBclose();
	}

	return info->db_status;
}

/******************************************************************************
 *                                                                            *
 * Function: ha_is_available                                                  *
 *                                                                            *
 * Purpose: check availability based on lastaccess timestamp, database time   *
 *          and failover delay                                                *
 *                                                                            *
 * Return value: SUCCEED - server can be started in active mode               *
 *               FAIL    - server cannot be started based on node registry    *
 *                                                                            *
 ******************************************************************************/
static int	ha_is_available(const zbx_ha_info_t *info, int lastaccess, int db_time)
{
	if (lastaccess + info->failover_delay <= db_time)
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: ha_check_standalone_config                                       *
 *                                                                            *
 * Purpose: check if server can be started in standalone configuration        *
 *                                                                            *
 * Return value: SUCCEED - server can be started in active mode               *
 *               FAIL    - server cannot be started based on node registry    *
 *                                                                            *
 ******************************************************************************/
static int	ha_check_standalone_config(zbx_ha_info_t *info, zbx_vector_ha_node_t *nodes, int db_time)
{
	int	i;

	for (i = 0; i < nodes->values_num; i++)
	{
		if ('\0' == *nodes->values[i]->name)
			continue;

		if (ZBX_NODE_STATUS_STOPPED != nodes->values[i]->status &&
				SUCCEED == ha_is_available(info, nodes->values[i]->lastaccess, db_time))
		{
			ha_set_error(info, "found %s node in standalone mode",
					zbx_ha_status_str(nodes->values[i]->status));
			return FAIL;
		}
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: ha_check_cluster_config                                          *
 *                                                                            *
 * Purpose: check if server can be started in cluster configuration           *
 *                                                                            *
 * Parameters: info     - [IN] - the HA node information                      *
 *             nodes    - [IN] - the cluster nodes                            *
 *             db_time  - [IN] - the current database timestamp               *
 *             activate - [OUT] SUCCEED - start in active mode                *
 *                              FAIL    - start in standby mode               *
 *                                                                            *
 * Return value: SUCCEED - server can be started in returned mode             *
 *               FAIL    - server cannot be started based on node registry    *
 *                                                                            *
 ******************************************************************************/
static int	ha_check_cluster_config(zbx_ha_info_t *info, zbx_vector_ha_node_t *nodes, int db_time, int *activate)
{
	int	i;

	*activate = SUCCEED;

	for (i = 0; i < nodes->values_num; i++)
	{
		if (ZBX_NODE_STATUS_STOPPED == nodes->values[i]->status ||
				SUCCEED != ha_is_available(info, nodes->values[i]->lastaccess, db_time))
		{
			continue;
		}

		if ('\0' == *nodes->values[i]->name)
		{
			ha_set_error(info, "found %s standalone node in HA mode",
					zbx_ha_status_str(nodes->values[i]->status));
			return FAIL;
		}

		if (0 == strcmp(info->name, nodes->values[i]->name))
		{
			ha_set_error(info, "found %s duplicate \"%s\" node",
					zbx_ha_status_str(nodes->values[i]->status), info->name);
			return FAIL;
		}

		if (ZBX_NODE_STATUS_ACTIVE == nodes->values[i]->status)
			*activate = FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: ha_db_create_node                                                *
 *                                                                            *
 * Purpose: add new node record in ha_node table if necessary                 *
 *                                                                            *
 * Return value: SUCCEED - node exists, was created or database is offline    *
 *               FAIL    - node configuration or database error               *
 *                                                                            *
 ******************************************************************************/
static int	ha_db_create_node(zbx_ha_info_t *info)
{
	zbx_vector_ha_node_t	nodes;
	int			i, ret = SUCCEED, activate;
	zbx_cuid_t		nodeid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_ha_node_create(&nodes);

	if (ZBX_DB_OK != ha_db_begin(info))
		goto finish;

	if (SUCCEED != ha_db_get_nodes(&nodes, 0))
		goto out;

	for (i = 0; i < nodes.values_num; i++)
	{
		if (0 == strcmp(info->name, nodes.values[i]->name))
		{
			nodeid = nodes.values[i]->nodeid;
			goto out;
		}
	}

	if (ZBX_HA_IS_CLUSTER())
		ret = ha_check_cluster_config(info, &nodes, 0, &activate);
	else
		ret = ha_check_standalone_config(info, &nodes, 0);

	if (SUCCEED == ret)
	{
		char	*name_esc;

		zbx_new_cuid(nodeid.str);
		name_esc = DBdyn_escape_string(info->name);

		(void)DBexecute_once("insert into ha_node (ha_nodeid,name,status,lastaccess)"
				" values ('%s','%s', %d," ZBX_DB_TIMESTAMP() ")",
				nodeid.str, name_esc, ZBX_NODE_STATUS_STOPPED);

		zbx_free(name_esc);
	}
out:
	if (SUCCEED == ret)
		ha_db_commit(info);
	else
		ha_db_rollback(info);
finish:
	if (SUCCEED == ret)
	{
		switch (info->db_status)
		{
			case ZBX_DB_FAIL:
				ret = FAIL;
				break;
			case ZBX_DB_OK:
				info->nodeid = nodeid;
				ZBX_FALLTHROUGH;
			case ZBX_DB_DOWN:
				ret = SUCCEED;
				break;
		}
	}

	zbx_vector_ha_node_clear_ext(&nodes, zbx_ha_node_free);
	zbx_vector_ha_node_destroy(&nodes);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: ha_db_get_time                                                   *
 *                                                                            *
 * Purpose: get current database time                                         *
 *                                                                            *
 ******************************************************************************/
static int	ha_db_get_time(int *db_time)
{
	DB_ROW			row;
	DB_RESULT		result;
	int			ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	result = DBselect_once("select " ZBX_DB_TIMESTAMP());

	if (NULL == result || ZBX_DB_DOWN == (intptr_t)result)
		goto out;

	if (NULL != (row = DBfetch(result)))
		*db_time = atoi(row[0]);
	else
		*db_time = 0;

	DBfree_result(result);

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s db_time:%d", __func__, zbx_result_string(ret), *db_time);

	return ret;
}


/******************************************************************************
 *                                                                            *
 * Function: ha_db_register_node                                              *
 *                                                                            *
 * Purpose: register server node                                              *
 *                                                                            *
 * Return value: SUCCEED - node was registered or database was offline        *
 *               FAIL    - fatal error                                        *
 *                                                                            *
 * Comments: If registration was successful the info->ha_status will be set   *
 *           to either active or standby. If database connection was lost     *
 *           the info->ha_status will stay unknown until another registration *
 *           attempt succeeds.                                                *
 *                                                                            *
 ******************************************************************************/
static int	ha_db_register_node(zbx_ha_info_t *info)
{
	zbx_vector_ha_node_t	nodes;
	int			ret, ha_status = ZBX_NODE_STATUS_UNKNOWN, activate = SUCCEED, db_time;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_ha_node_create(&nodes);

	if (SUCCEED != (ret = ha_db_create_node(info)) || SUCCEED == zbx_cuid_empty(info->nodeid))
		goto finish;

	if (ZBX_DB_OK != ha_db_begin(info))
		goto finish;

	if (SUCCEED != ha_db_get_nodes(&nodes, ZBX_HA_NODE_LOCK))
		goto out;

	if (FAIL == ha_db_update_config(info))
		goto out;

	if (SUCCEED != ha_db_get_time(&db_time))
		goto out;

	if (ZBX_HA_IS_CLUSTER())
		ret = ha_check_cluster_config(info, &nodes, db_time, &activate);
	else
		ret = ha_check_standalone_config(info, &nodes, db_time);

	if (SUCCEED == ret)
	{
		char		*address = NULL, *address_esc;
		unsigned short	port = 0;

		ha_status = SUCCEED == activate ? ZBX_NODE_STATUS_ACTIVE : ZBX_NODE_STATUS_STANDBY;

		ha_get_external_address(&address, &port);
		address_esc = DBdyn_escape_string(address);

		(void)DBexecute_once("update ha_node set status=%d,address='%s',port=%d,sessionid='%s',lastaccess="
				ZBX_DB_TIMESTAMP() " where ha_nodeid='%s'",
				ha_status, address_esc, port, ha_sessionid.str, info->nodeid.str);
		zbx_free(address_esc);
		zbx_free(address);
	}
out:
	if (SUCCEED == ret)
		ha_db_commit(info);
	else
		ha_db_rollback(info);

finish:
	if (SUCCEED == ret)
	{
		switch (info->db_status)
		{
			case ZBX_DB_FAIL:
				ret = FAIL;
				break;
			case ZBX_DB_OK:
				info->ha_status = ha_status;
				ZBX_FALLTHROUGH;
			case ZBX_DB_DOWN:
				ret = SUCCEED;
				break;
		}
	}

	zbx_vector_ha_node_clear_ext(&nodes, zbx_ha_node_free);
	zbx_vector_ha_node_destroy(&nodes);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() nodeid:%s ha_status:%s db_status:%d", __func__,
			info->nodeid.str, zbx_ha_status_str(info->ha_status), info->db_status);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: ha_check_standby_nodes                                           *
 *                                                                            *
 * Purpose: check for standby nodes being unavailable for failrover_delay     *
 *          seconds and mark them unavailable                                 *
 *                                                                            *
 ******************************************************************************/
static int	ha_check_standby_nodes(zbx_ha_info_t *info, zbx_vector_ha_node_t *nodes, int db_time)
{
	int			i, ret = SUCCEED;
	zbx_vector_str_t	unavailable_nodes;

	zbx_vector_str_create(&unavailable_nodes);

	for (i = 0; i < nodes->values_num; i++)
	{
		if (nodes->values[i]->status != ZBX_NODE_STATUS_STANDBY)
			continue;

		if (db_time >= nodes->values[i]->lastaccess + info->failover_delay)
			zbx_vector_str_append(&unavailable_nodes, nodes->values[i]->nodeid.str);
	}

	if (0 != unavailable_nodes.values_num)
	{
		char	*sql = NULL;
		size_t	sql_alloc = 0, sql_offset = 0;

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update ha_node set status=%d where",
				ZBX_NODE_STATUS_UNAVAILABLE);

		DBadd_str_condition_alloc(&sql, &sql_alloc, &sql_offset, "ha_nodeid",
				(const char **)unavailable_nodes.values, unavailable_nodes.values_num);

		if (ZBX_DB_OK > DBexecute("%s", sql))
			ret = FAIL;

		zbx_free(sql);
	}

	zbx_vector_str_destroy(&unavailable_nodes);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: ha_check_active_node                                             *
 *                                                                            *
 * Purpose: check for active nodes being unavailable for failover_delay       *
 *          seconds, mark them unavailable and set own status to active       *
 *                                                                            *
 ******************************************************************************/
static int	ha_check_active_node(zbx_ha_info_t *info, zbx_vector_ha_node_t *nodes, int *ha_status)
{
	int	i, ret = SUCCEED;

	if (ZBX_NODE_STATUS_UNKNOWN == info->ha_status)
		*ha_status = ZBX_NODE_STATUS_STANDBY;

	for (i = 0; i < nodes->values_num; i++)
	{
		if (ZBX_NODE_STATUS_ACTIVE == nodes->values[i]->status)
			break;
	}

	if ('\0' == *nodes->values[i]->name)
	{
		ha_set_error(info, "found active standalone node in HA mode");
		return FAIL;
	}

	/* 1) No active nodes - set this node as active.                */
	/* 2) This node is active - update it's status as it might have */
	/*    switched itself to standby mode in the case of prolonged  */
	/*    database connection loss.                                 */
	if (i == nodes->values_num || SUCCEED == zbx_cuid_compare(nodes->values[i]->nodeid, info->nodeid))
	{
		*ha_status = ZBX_NODE_STATUS_ACTIVE;
	}
	else
	{
		if (nodes->values[i]->lastaccess != info->lastaccess_active)
		{
			info->lastaccess_active = nodes->values[i]->lastaccess;
			info->offline_ticks_active = 0;
		}
		else
			info->offline_ticks_active++;

		if (info->failover_delay / ZBX_HA_POLL_PERIOD + 1 < info->offline_ticks_active)
		{

			if (ZBX_DB_OK > DBexecute("update ha_node set status=%d where ha_nodeid='%s'",
					ZBX_NODE_STATUS_UNAVAILABLE, nodes->values[i]->nodeid.str))
			{
				ret = FAIL;
			}

			*ha_status = ZBX_NODE_STATUS_ACTIVE;
		}
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: ha_check_registered_node                                         *
 *                                                                            *
 * Purpose: check if the node is registered in node table and get ID          *
 *                                                                            *
 ******************************************************************************/
static zbx_ha_node_t	*ha_find_node_by_name(zbx_vector_ha_node_t *nodes, const char *name)
{
	int	i;

	for (i = 0; i < nodes->values_num; i++)
	{
		if (0 == strcmp(nodes->values[i]->name, name))
			return nodes->values[i];
	}

	return NULL;
}

/******************************************************************************
 *                                                                            *
 * Function: ha_check_nodes                                                   *
 *                                                                            *
 * Purpose: check HA status based on nodes                                    *
 *                                                                            *
 ******************************************************************************/
static int	ha_check_nodes(zbx_ha_info_t *info)
{
	zbx_vector_ha_node_t	nodes;
	zbx_ha_node_t		*node;
	int			ret = SUCCEED, ha_status, db_time;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() ha_status:%s", __func__, zbx_ha_status_str(info->ha_status));

	zbx_vector_ha_node_create(&nodes);

	info->ticks++;

	if (ZBX_DB_OK != ha_db_begin(info))
		goto finish;

	ha_status = info->ha_status;

	if (SUCCEED != ha_db_get_nodes(&nodes, ZBX_HA_NODE_LOCK))
		goto out;

	if (NULL == (node = ha_find_node_by_name(&nodes, info->name)))
	{
		ha_set_error(info, "cannot find server node \"%s\" in registry", info->name);
		ret = FAIL;
		goto out;
	}

	if (SUCCEED != zbx_cuid_compare(ha_sessionid, node->sessionid))
	{
		ha_set_error(info, "node sessionid has been changed by other node", info->name);
		ret = FAIL;
		goto out;
	}

	if (SUCCEED == zbx_cuid_empty(info->nodeid))
		info->nodeid = node->nodeid;

	if (SUCCEED != ha_db_update_config(info))
		goto out;

	if (SUCCEED != ha_db_get_time(&db_time))
		goto out;

	if (ZBX_HA_IS_CLUSTER())
	{
		if (ZBX_NODE_STATUS_ACTIVE == info->ha_status)
			ret = ha_check_standby_nodes(info, &nodes, db_time);
		else /* passive status */
			ret = ha_check_active_node(info, &nodes, &ha_status);
	}
	else
		ret = SUCCEED;

	if (SUCCEED == ret)
	{
		char		*sql = NULL;
		size_t		sql_alloc = 0, sql_offset = 0;

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "update ha_node set lastaccess=" ZBX_DB_TIMESTAMP());

		if (ha_status != node->status)
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, ",status=%d", ha_status);

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " where ha_nodeid='%s'", info->nodeid.str);

		(void)DBexecute_once("%s", sql);
		zbx_free(sql);
	}
out:
	if (SUCCEED == ret)
		ha_db_commit(info);
	else
		ha_db_rollback(info);

finish:
	if (SUCCEED == ret)
	{
		switch (info->db_status)
		{
			case ZBX_DB_FAIL:
				ret = FAIL;
				break;
			case ZBX_DB_DOWN:
				info->offline_ticks++;

				if (ZBX_HA_IS_CLUSTER() && ZBX_NODE_STATUS_ACTIVE == info->ha_status)
				{
					if (info->failover_delay / ZBX_HA_POLL_PERIOD < info->offline_ticks)
						info->ha_status = ZBX_NODE_STATUS_STANDBY;
				}
				ret = SUCCEED;
				break;
			case ZBX_DB_OK:
				info->offline_ticks = 0;
				info->ha_status = ha_status;
				ret = SUCCEED;
				break;
		}
	}

	zbx_vector_ha_node_clear_ext(&nodes, zbx_ha_node_free);
	zbx_vector_ha_node_destroy(&nodes);

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() nodeid:%s ha_status:%s db_status:%d", __func__,
			info->nodeid.str, zbx_ha_status_str(info->ha_status), info->db_status);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: ha_db_get_nodes_json                                             *
 *                                                                            *
 * Purpose: get cluster status in lld compatible json format                  *
 *                                                                            *
 ******************************************************************************/
static int	ha_db_get_nodes_json(zbx_ha_info_t *info, char **nodes_json, char **error)
{
	zbx_vector_ha_node_t	nodes;
	int			i, db_time, ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (ZBX_DB_OK != info->db_status)
		goto out;

	if (SUCCEED != ha_db_get_time(&db_time))
		goto out;

	zbx_vector_ha_node_create(&nodes);

	if (SUCCEED == ha_db_get_nodes(&nodes, 0))
	{
		struct zbx_json	j;

		zbx_json_initarray(&j, 1024);

		for (i = 0; i < nodes.values_num; i++)
		{
			zbx_json_addobject(&j, NULL);

			zbx_json_addstring(&j, ZBX_PROTO_TAG_ID, nodes.values[i]->nodeid.str, ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&j, ZBX_PROTO_TAG_NAME, nodes.values[i]->name, ZBX_JSON_TYPE_STRING);
			zbx_json_addint64(&j, ZBX_PROTO_TAG_STATUS, (zbx_int64_t)nodes.values[i]->status);
			zbx_json_addint64(&j, ZBX_PROTO_TAG_LASTACCESS, (zbx_int64_t)nodes.values[i]->lastaccess);
			zbx_json_addstring(&j, ZBX_PROTO_TAG_ADDRESS, nodes.values[i]->address, ZBX_JSON_TYPE_STRING);
			zbx_json_addint64(&j, ZBX_PROTO_TAG_DB_TIMESTAMP, (zbx_int64_t)db_time);
			zbx_json_addint64(&j, ZBX_PROTO_TAG_LASTACCESS_AGE,
					(zbx_int64_t)(db_time -nodes.values[i]->lastaccess));

			zbx_json_close(&j);
		}

		*nodes_json = zbx_strdup(NULL, j.buffer);
		zbx_json_free(&j);

		ret = SUCCEED;
	}

	zbx_vector_ha_node_clear_ext(&nodes, zbx_ha_node_free);
	zbx_vector_ha_node_destroy(&nodes);
out:
	if (SUCCEED != ret)
		*error = zbx_strdup(NULL, "database error");

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: ha_remove_node_by_index                                          *
 *                                                                            *
 * Purpose: remove node by its index in node list                             *
 *                                                                            *
 ******************************************************************************/
static int	ha_remove_node_by_index(zbx_ha_info_t *info, int index, char **error)
{
	zbx_vector_ha_node_t	nodes;
	int			ret = FAIL;

	zbx_vector_ha_node_create(&nodes);

	if (ZBX_DB_OK != ha_db_begin(info))
	{
		*error = zbx_strdup(NULL, "database connection problem");
		return FAIL;
	}

	if (SUCCEED != ha_db_get_nodes(&nodes, 0))
	{
		*error = zbx_strdup(NULL, "database connection problem");
		goto out;
	}

	index--;

	if (0 > index || index >= nodes.values_num)
	{
		*error = zbx_strdup(NULL, "node index out of range");
		goto out;
	}

	if (ZBX_NODE_STATUS_ACTIVE == nodes.values[index]->status ||
			ZBX_NODE_STATUS_STANDBY == nodes.values[index]->status)
	{
		*error = zbx_dsprintf(NULL, "node is %s", zbx_ha_status_str(nodes.values[index]->status));
		goto out;
	}

	if (ZBX_DB_OK > DBexecute_once("delete from ha_node where ha_nodeid='%s'", nodes.values[index]->nodeid.str))
	{
		*error = zbx_strdup(NULL, "database connection problem");
		goto out;
	}

	ret = SUCCEED;
out:
	if (SUCCEED == ret)
	{
		if (ZBX_DB_OK <= ha_db_commit(info))
		{
			zabbix_log(LOG_LEVEL_WARNING, "removed node \"%s\" with ID \"%s\"", nodes.values[index]->name,
					nodes.values[index]->nodeid.str);
		}
	}
	else
		ha_db_rollback(info);

	zbx_vector_ha_node_clear_ext(&nodes, zbx_ha_node_free);
	zbx_vector_ha_node_destroy(&nodes);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: ha_report_cluster_status                                         *
 *                                                                            *
 * Purpose: report cluster status in log file                                 *
 *                                                                            *
 ******************************************************************************/
static void	ha_remove_node(zbx_ha_info_t *info, zbx_ipc_client_t *client, const zbx_ipc_message_t *message)
{
	int		index;
	char		*error = NULL;
	zbx_uint32_t	len = 0, error_len;
	unsigned char	*data;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	memcpy(&index, message->data, sizeof(index));

	ha_remove_node_by_index(info, index, &error);

	zbx_serialize_prepare_str(len, error);

	data = zbx_malloc(NULL, len);
	zbx_serialize_str(data, error, error_len);
	zbx_free(error);

	zbx_ipc_client_send(client, ZBX_IPC_SERVICE_HA_REMOVE_NODE, data, len);
	zbx_free(data);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Function: ha_set_failover_delay                                            *
 *                                                                            *
 * Purpose: set failover delay                                                *
 *                                                                            *
 ******************************************************************************/
static void	ha_set_failover_delay(zbx_ha_info_t *info, zbx_ipc_client_t *client, const zbx_ipc_message_t *message)
{
	int		delay;
	char		*error = NULL;
	zbx_uint32_t	len = 0, error_len;
	unsigned char	*data;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	memcpy(&delay, message->data, sizeof(delay));

	if (ZBX_DB_OK == info->db_status && ZBX_DB_OK <= DBexecute("update config set ha_failover_delay=%d", delay))
	{
		info->failover_delay = delay;
		zabbix_log(LOG_LEVEL_WARNING, "HA failover delay set to %ds", delay);
	}
	else
		error = "database error";

	zbx_serialize_prepare_str(len, error);

	data = zbx_malloc(NULL, len);
	zbx_serialize_str(data, error, error_len);

	zbx_ipc_client_send(client, ZBX_IPC_SERVICE_HA_FAILOVER_DELAY, data, len);
	zbx_free(data);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Function: ha_send_node_list                                               *
 *                                                                            *
 * Purpose: reply to get nodes request                                        *
 *                                                                            *
 ******************************************************************************/
static void	ha_send_node_list(zbx_ha_info_t *info, zbx_ipc_client_t *client)
{
	int		ret;
	char		*error = NULL, *nodes_json = NULL, *str;
	zbx_uint32_t	len = 0, str_len;
	unsigned char	*data, *ptr;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (SUCCEED == (ret = ha_db_get_nodes_json(info, &nodes_json, &error)))
		str = nodes_json;
	else
		str = error;

	zbx_serialize_prepare_value(len, ret);
	zbx_serialize_prepare_str(len, str);

	ptr = data = zbx_malloc(NULL, len);
	ptr += zbx_serialize_value(ptr, ret);
	(void)zbx_serialize_str(ptr, str, str_len);
	zbx_free(str);

	zbx_ipc_client_send(client, ZBX_IPC_SERVICE_HA_REMOVE_NODE, data, len);
	zbx_free(data);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Function: ha_db_update_exit_status                                         *
 *                                                                            *
 * Purpose: update node status in database on shutdown                        *
 *                                                                            *
 ******************************************************************************/
static void	ha_db_update_exit_status(zbx_ha_info_t *info)
{
	if (ZBX_NODE_STATUS_ACTIVE != info->ha_status && ZBX_NODE_STATUS_STANDBY != info->ha_status)
		return;

	if (ZBX_DB_OK != ha_db_begin(info))
		return;

	if (SUCCEED != ha_db_lock_nodes(info))
		goto out;

	DBexecute_once("update ha_node set status=%d where ha_nodeid='%s'",
			ZBX_NODE_STATUS_STOPPED, info->nodeid.str);
out:
	ha_db_commit(info);
}

/*
 * public API
 */

/******************************************************************************
 *                                                                            *
 * Function: zbx_ha_get_status                                                *
 *                                                                            *
 * Purpose: requests HA manager to send status update                         *
 *                                                                            *
 ******************************************************************************/
int	zbx_ha_get_status(char **error)
{
	int	ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	ret = ha_send_manager_message(ZBX_IPC_SERVICE_HA_STATUS, error);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_ha_recv_status                                               *
 *                                                                            *
 * Purpose: receive status message from HA service                            *
 *                                                                            *
 ******************************************************************************/
int	zbx_ha_recv_status(int timeout, int *status, char **error)
{
	return ha_recv_status(status, timeout, error);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_ha_get_nodes                                                 *
 *                                                                            *
 * Purpose: get HA nodes in json format                                       *
 *                                                                            *
 ******************************************************************************/
int	zbx_ha_get_nodes(char **nodes, char **error)
{
	unsigned char		*data, *ptr;
	zbx_uint32_t		str_len;
	int			ret;
	char			*str;

	if (SUCCEED != zbx_ipc_async_exchange(ZBX_IPC_SERVICE_HA, ZBX_IPC_SERVICE_HA_GET_NODES,
			ZBX_HA_SERVICE_TIMEOUT, NULL, 0, &data, error))
	{
		return FAIL;
	}

	ptr = data;
	ptr += zbx_deserialize_value(ptr, &ret);
	(void)zbx_deserialize_str(ptr, &str, str_len);
	zbx_free(data);

	if (SUCCEED == ret)
		*nodes = str;
	else
		*error = str;

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_ha_remove_node                                               *
 *                                                                            *
 * Purpose: remove HA node                                                    *
 *                                                                            *
 * Comments: A new socket is opened to avoid interfering with notification    *
 *           channel                                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_ha_remove_node(int node_num, char **error)
{
	unsigned char		*data;
	zbx_uint32_t		error_len;

	if (SUCCEED != zbx_ipc_async_exchange(ZBX_IPC_SERVICE_HA, ZBX_IPC_SERVICE_HA_REMOVE_NODE,
			ZBX_HA_SERVICE_TIMEOUT, (unsigned char *)&node_num, sizeof(node_num), &data, error))
	{
		return FAIL;
	}

	(void)zbx_deserialize_str(data, error, error_len);
	zbx_free(data);

	return (0 == error_len ? SUCCEED : FAIL);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_ha_set_failover_delay                                        *
 *                                                                            *
 * Purpose: set HA failover delay                                             *
 *                                                                            *
 * Comments: A new socket is opened to avoid interfering with notification    *
 *           channel                                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_ha_set_failover_delay(int delay, char **error)
{
	unsigned char		*data;
	zbx_uint32_t		error_len;

	if (SUCCEED != zbx_ipc_async_exchange(ZBX_IPC_SERVICE_HA, ZBX_IPC_SERVICE_HA_FAILOVER_DELAY,
			ZBX_HA_SERVICE_TIMEOUT, (unsigned char *)&delay, sizeof(delay), &data, error))
	{
		return FAIL;
	}

	(void)zbx_deserialize_str(data, error, error_len);
	zbx_free(data);

	return (0 == error_len ? SUCCEED : FAIL);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_ha_start                                                     *
 *                                                                            *
 * Purpose: start HA manager                                                  *
 *                                                                            *
 ******************************************************************************/
int	zbx_ha_start(char **error, int ha_status)
{
	char			*errmsg = NULL;
	int			ret = FAIL;
	zbx_thread_args_t	args;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	args.args = (void *)(uintptr_t)ha_status;
	zbx_thread_start(ha_manager_thread, &args, &ha_pid);

	if (ZBX_THREAD_ERROR == ha_pid)
	{
		*error = zbx_dsprintf(NULL, "cannot create HA manager process: %s", zbx_strerror(errno));
		goto out;
	}

	if (SUCCEED != zbx_ipc_async_socket_open(&ha_socket, ZBX_IPC_SERVICE_HA, ZBX_HA_SERVICE_TIMEOUT, &errmsg))
	{
		*error = zbx_dsprintf(NULL, "cannot connect to HA manager process: %s", errmsg);
		zbx_free(errmsg);
		goto out;
	}

	if (FAIL == zbx_ipc_async_socket_send(&ha_socket, ZBX_IPC_SERVICE_HA_REGISTER, NULL, 0))
	{
		*error = zbx_dsprintf(NULL, "cannot queue message to HA manager service");
		goto out;
	}

	if (FAIL == zbx_ipc_async_socket_flush(&ha_socket, ZBX_HA_SERVICE_TIMEOUT))
	{
		*error = zbx_dsprintf(NULL, "cannot send message to HA manager service");
		goto out;
	}

	ret = SUCCEED;
out:
	if (SUCCEED != ret && ZBX_THREAD_ERROR != ha_pid)
		zbx_ha_kill();

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_ha_pause                                                     *
 *                                                                            *
 * Purpose: pause HA manager                                                  *
 *                                                                            *
 * Comments: HA manager must be paused before stopping it normally            *
 *                                                                            *
 ******************************************************************************/
int	zbx_ha_pause(char **error)
{
	int	ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	ret = ha_send_manager_message(ZBX_IPC_SERVICE_HA_PAUSE, error);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_ha_stop                                                      *
 *                                                                            *
 * Purpose: stop  HA manager                                                  *
 *                                                                            *
 * Comments: This function is used to stop HA manager on normal shutdown      *
 *                                                                            *
 ******************************************************************************/
int	zbx_ha_stop(char **error)
{
	int	ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (SUCCEED == ha_send_manager_message(ZBX_IPC_SERVICE_HA_STOP, error))
	{
		if (ZBX_THREAD_ERROR == zbx_thread_wait(ha_pid))
		{
			*error = zbx_dsprintf(NULL, "failed to wait for HA manager to exit: %s", zbx_strerror(errno));
			goto out;
		}

		ret = SUCCEED;
	}
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_ha_kill                                                      *
 *                                                                            *
 * Purpose: kill HA manager                                                   *
 *                                                                            *
 ******************************************************************************/
void	zbx_ha_kill(void)
{
	kill(ha_pid, SIGKILL);
	zbx_thread_wait(ha_pid);

	if (SUCCEED == zbx_ipc_async_socket_connected(&ha_socket))
		zbx_ipc_async_socket_close(&ha_socket);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_ha_status_str                                                *
 *                                                                            *
 * Purpose: get HA status in text format                                      *
 *                                                                            *
 ******************************************************************************/
const char	*zbx_ha_status_str(int ha_status)
{
	switch (ha_status)
	{
		case ZBX_NODE_STATUS_STANDBY:
			return "standby";
		case ZBX_NODE_STATUS_STOPPED:
			return "stopped";
		case ZBX_NODE_STATUS_UNAVAILABLE:
			return "unavailable";
		case ZBX_NODE_STATUS_ACTIVE:
			return "active";
		case ZBX_NODE_STATUS_ERROR:
			return "error";
		default:
			return "unknown";
	}
}

/*
 * main process loop
 */
ZBX_THREAD_ENTRY(ha_manager_thread, args)
{
	zbx_ipc_service_t	service;
	char			*error = NULL;
	zbx_ipc_client_t	*client, *main_proc = NULL;
	zbx_ipc_message_t	*message;
	int			stop = FAIL;
	double			lastcheck, now, nextcheck, timeout;
	zbx_ha_info_t		info;

	zbx_setproctitle("ha manager");

	zabbix_log(LOG_LEVEL_INFORMATION, "starting HA manager");

	if (FAIL == zbx_ipc_service_start(&service, ZBX_IPC_SERVICE_HA, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot start HA manager service: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	zbx_cuid_clear(info.nodeid);
	info.name = ZBX_NULL2EMPTY_STR(CONFIG_HA_NODE_NAME);
	info.ha_status = (int)(uintptr_t)((zbx_thread_args_t *)args)->args;
	info.error = NULL;
	info.db_status = ZBX_DB_DOWN;
	info.ticks = 0;
	info.offline_ticks = 0;
	info.offline_ticks_active = 0;
	info.lastaccess_active = 0;
	info.failover_delay = ZBX_HA_DEFAULT_FAILOVER_DELAY;

	lastcheck = zbx_time();

	if (ZBX_NODE_STATUS_UNKNOWN == info.ha_status)
	{
		ha_db_register_node(&info);

		if (ZBX_NODE_STATUS_ERROR == info.ha_status)
			goto pause;
		else
			nextcheck = lastcheck + ZBX_HA_POLL_PERIOD;
	}
	else
		nextcheck = lastcheck + SEC_PER_MIN;

	zabbix_log(LOG_LEVEL_INFORMATION, "HA manager started in %s mode", zbx_ha_status_str(info.ha_status));

	while (SUCCEED != stop)
	{
		now = zbx_time();

		if (ZBX_NODE_STATUS_ERROR != info.ha_status && nextcheck <= now)
		{
			int	old_status = info.ha_status, ret;

			if (ZBX_NODE_STATUS_UNKNOWN == info.ha_status)
				ret = ha_db_register_node(&info);
			else
				ret = ha_check_nodes(&info);

			if (old_status != info.ha_status && ZBX_NODE_STATUS_UNKNOWN != info.ha_status)
			{
				if (NULL != main_proc)
					ha_notify_parent(main_proc, info.ha_status, info.error);
			}

			if (SUCCEED != ret)
				break;

			lastcheck = nextcheck;
			nextcheck = lastcheck + ZBX_HA_POLL_PERIOD;

			while (nextcheck <= now)
				nextcheck += ZBX_HA_POLL_PERIOD;
		}

		timeout = nextcheck - now;

		(void)zbx_ipc_service_recv(&service, timeout, &client, &message);

		if (NULL != message)
		{
			switch (message->code)
			{
				case ZBX_IPC_SERVICE_HA_REGISTER:
					main_proc = client;
					break;
				case ZBX_IPC_SERVICE_HA_STATUS:
					ha_notify_parent(main_proc, info.ha_status, info.error);
					break;
				case ZBX_IPC_SERVICE_HA_PAUSE:
					stop = SUCCEED;
					break;
				case ZBX_IPC_SERVICE_HA_GET_NODES:
					ha_send_node_list(&info, client);
					break;
				case ZBX_IPC_SERVICE_HA_REMOVE_NODE:
					ha_remove_node(&info, client, message);
					break;
				case ZBX_IPC_SERVICE_HA_FAILOVER_DELAY:
					ha_set_failover_delay(&info, client, message);
					break;
			}

			zbx_ipc_message_free(message);
		}

		if (NULL != client)
			zbx_ipc_client_release(client);
	}

	zabbix_log(LOG_LEVEL_INFORMATION, "HA manager has been paused");
pause:
	stop = FAIL;

	while (SUCCEED != stop)
	{
		(void)zbx_ipc_service_recv(&service, ZBX_IPC_WAIT_FOREVER, &client, &message);

		if (NULL != message)
		{
			switch (message->code)
			{
				case ZBX_IPC_SERVICE_HA_REGISTER:
					main_proc = client;
					break;
				case ZBX_IPC_SERVICE_HA_STATUS:
					ha_notify_parent(main_proc, info.ha_status, info.error);
					break;
				case ZBX_IPC_SERVICE_HA_STOP:
					stop = SUCCEED;
					break;
			}

			zbx_ipc_message_free(message);
		}

		if (NULL != client)
			zbx_ipc_client_release(client);
	}

	zbx_free(info.error);

	ha_db_update_exit_status(&info);

	DBclose();

	zbx_ipc_service_close(&service);

	zabbix_log(LOG_LEVEL_INFORMATION, "HA manager has been stopped");


	exit(EXIT_SUCCESS);

	return 0;
}
