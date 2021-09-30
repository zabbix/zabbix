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

#include "common.h"
#include "db.h"
#include "log.h"
#include "zbxipcservice.h"
#include "zbxserialize.h"
#include "ha.h"
#include "threads.h"
#include "../../libs/zbxalgo/vectorimpl.h"

#define ZBX_HA_POLL_PERIOD	5

// TODO: use more realistic timeout after testing
#define ZBX_HA_SERVICE_TIMEOUT	1

static pid_t			ha_pid;
static zbx_ipc_async_socket_t	ha_socket;

extern char	*CONFIG_HA_NODE_NAME;
extern char	*CONFIG_EXTERNAL_ADDRESS;
extern char	*CONFIG_LISTEN_IP;
extern int	CONFIG_LISTEN_PORT;

#define ZBX_HA_IS_CLUSTER()	(NULL != CONFIG_HA_NODE_NAME && '\0' != *CONFIG_HA_NODE_NAME)

typedef struct
{
	zbx_uint64_t	nodeid;
	int		ha_status;
	int		db_status;
	const char	*name;
	char		*error;
}
zbx_ha_info_t;

ZBX_THREAD_ENTRY(ha_manager_thread, args);

typedef struct
{
	zbx_uint64_t	nodeid;
	char		*name;
	int		status;
}
zbx_ha_node_t;

ZBX_PTR_VECTOR_DECL(ha_node, zbx_ha_node_t *)
ZBX_PTR_VECTOR_IMPL(ha_node, zbx_ha_node_t *)

static void	zbx_ha_node_free(zbx_ha_node_t *node)
{
	zbx_free(node->name);
	zbx_free(node);
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
	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	// TODO: implement HA status check
	zabbix_log(LOG_LEVEL_DEBUG, "checking nodes (not implemented)");

	//info->ha_status = ZBX_NODE_STATUS_UNKNOWN;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() status:%d", __func__, info->ha_status);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: ha_report_status                                                 *
 *                                                                            *
 * Purpose: report cluster status in log file                                 *
 *                                                                            *
 ******************************************************************************/
static void	ha_report_status(void)
{
	// TODO: implement cluster status reporting in log file
	zabbix_log(LOG_LEVEL_INFORMATION, "status reporting is not yet implemented ");
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

	ret = zbx_ipc_client_send(client, ZBX_IPC_SERVICE_HA_STATUS, data, len);
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
			case ZBX_IPC_SERVICE_HA_STATUS:
				ptr = message->data;
				ptr += zbx_deserialize_value(ptr, status);
				(void)zbx_deserialize_str(ptr, error, len);

				if (ZBX_NODE_STATUS_ERROR == *status)
					ret = FAIL;
				break;
			default:
				*status = ZBX_NODE_STATUS_UNKNOWN;
		}
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
 * Function: ha_db_get_nodes                                                  *
 *                                                                            *
 * Purpose: get all nodes from database                                       *
 *                                                                            *
 ******************************************************************************/
static int	ha_db_get_nodes(zbx_vector_ha_node_t *nodes)
{
	DB_RESULT	result;
	DB_ROW		row;
	zbx_ha_node_t	*node;

	if (NULL == (result = DBselect_once("select ha_nodeid,name,status from ha_node")))
		return FAIL;

	while (NULL != (row = DBfetch(result)))
	{
		node = (zbx_ha_node_t *)zbx_malloc(NULL, sizeof(zbx_ha_node_t));
		ZBX_STR2UINT64(node->nodeid, row[0]);
		node->name = zbx_strdup(NULL, row[1]);
		node->status = atoi(row[2]);
		zbx_vector_ha_node_append(nodes, node);
	}

	DBfree_result(result);

	return DBtxn_status();
}

/******************************************************************************
 *                                                                            *
 * Function: ha_check_cluster_config                                          *
 *                                                                            *
 * Purpose: check if server can be started in cluster configuration           *
 *                                                                            *
 ******************************************************************************/
static int	ha_check_cluster_config(zbx_ha_info_t *info, zbx_vector_ha_node_t *nodes)
{
	int	i, activate = SUCCEED;

	for (i = 0; i < nodes->values_num; i++)
	{
		if ('\0' == *nodes->values[i]->name && ZBX_NODE_STATUS_STOPPED != nodes->values[i]->status)
		{
			ha_set_error(info, "found %s standalone node in HA mode",
					zbx_ha_status_str(nodes->values[i]->status));
			return FAIL;
		}

		if (0 == strcmp(info->name, nodes->values[i]->name))
		{
			if (ZBX_NODE_STATUS_STOPPED != nodes->values[i]->status)
			{
				ha_set_error(info, "found %s duplicate \"%s\" node in HA mode",
						zbx_ha_status_str(nodes->values[i]->status), info->name);
				return FAIL;
			}

			info->nodeid = nodes->values[i]->nodeid;
		}

		if (ZBX_NODE_STATUS_ACTIVE == nodes->values[i]->status)
			activate = FAIL;
	}

	info->ha_status = SUCCEED == activate ? ZBX_NODE_STATUS_ACTIVE : ZBX_NODE_STATUS_STANDBY;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: ha_check_standalone_config                                       *
 *                                                                            *
 * Purpose: check if server can be started in standalone configuration        *
 *                                                                            *
 ******************************************************************************/
static int	ha_check_standalone_config(zbx_ha_info_t *info, zbx_vector_ha_node_t *nodes)
{
	int	i;

	for (i = 0; i < nodes->values_num; i++)
	{
		if (ZBX_NODE_STATUS_STOPPED != nodes->values[i]->status)
		{
			ha_set_error(info, "found %s node in standalone mode",
					zbx_ha_status_str(nodes->values[i]->status));
			return FAIL;
		}

		if (0 == strcmp(info->name, nodes->values[i]->name))
			info->nodeid = nodes->values[i]->nodeid;
	}

	info->ha_status = ZBX_NODE_STATUS_ACTIVE;

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
 * Function: ha_db_register_node                                              *
 *                                                                            *
 * Purpose: register server node                                              *
 *                                                                            *
 * Return value: SUCCEED - node was registered or database connection was lost*
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
	int			ret;
	DB_RESULT		result;

	if (ZBX_DB_DOWN == info->db_status)
	{
		info->db_status = DBconnect(ZBX_DB_CONNECT_ONCE);

		if (ZBX_DB_FAIL == info->db_status)
		{
			ha_set_error(info, "cannot connect to database");
			return FAIL;
		}
	}

	if (ZBX_DB_DOWN == info->db_status)
	{
		info->ha_status = ZBX_NODE_STATUS_UNKNOWN;
		return SUCCEED;
	}

	zbx_vector_ha_node_create(&nodes);

	DBbegin();

	result = DBselect_once("select null from table_lock where table_name='ha_node'" ZBX_FOR_UPDATE);
	if (NULL == result)
	{
		ha_set_error(info, "cannot connect to database");
		return FAIL;
	}

	if (ZBX_DB_DOWN == (intptr_t)result)
		goto out;

	DBfree_result(result);

	if (SUCCEED != ha_db_get_nodes(&nodes))
		goto out;

	if (ZBX_HA_IS_CLUSTER())
		ret = ha_check_cluster_config(info, &nodes);
	else
		ret = ha_check_standalone_config(info, &nodes);

	if (SUCCEED == ret)
	{
		char		*sql = NULL, *name_esc, *address = NULL, *address_esc;
		size_t		sql_alloc = 0, sql_offset = 0;
		unsigned short	port = 0;

		ha_get_external_address(&address, &port);
		address_esc = DBdyn_escape_string(address);

		if (0 == info->nodeid)
		{

			info->nodeid = DBget_maxid("ha_node");
			name_esc = DBdyn_escape_string(info->name);

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "insert into ha_node"
					" (ha_nodeid,name,address,port,status,lastaccess) values"
					" (" ZBX_FS_UI64 ",'%s','%s',%hu, %d," ZBX_DB_TIMESTAMP() ")",
					info->nodeid, name_esc, address_esc, port, info->ha_status);
		}
		else
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
					"update ha_node set status=%d,address='%s',port=%d,lastaccess="
					ZBX_DB_TIMESTAMP() " where ha_nodeid=" ZBX_FS_UI64,
					info->ha_status, address_esc, port, info->nodeid);
		}

		zbx_free(address_esc);
		zbx_free(address);
		zbx_free(name_esc);

		ret = DBexecute_once("%s", sql);
		zbx_free(sql);
	}
out:
	zbx_vector_ha_node_clear_ext(&nodes, zbx_ha_node_free);
	zbx_vector_ha_node_destroy(&nodes);

	info->db_status = DBcommit_without_reconnect();

	if (ZBX_DB_FAIL == info->db_status)
	{
		ha_set_error(info, "database error while registering node");
		return FAIL;
	}

	if (ZBX_DB_DOWN == info->db_status)
		info->ha_status = ZBX_NODE_STATUS_UNKNOWN;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: ha_db_update_status                                              *
 *                                                                            *
 * Purpose: update node status in database                                    *
 *                                                                            *
 ******************************************************************************/
static void	ha_db_update_status(zbx_ha_info_t *info)
{
	DB_RESULT	result;

	if (ZBX_NODE_STATUS_ACTIVE != info->ha_status && ZBX_NODE_STATUS_STANDBY != info->ha_status)
		return;

	DBbegin();

	result = DBselect_once("select null from table_lock where table_name='ha_node'" ZBX_FOR_UPDATE);
	DBfree_result(result);

	DBexecute_once("update ha_node set status=%d where ha_nodeid=" ZBX_FS_UI64,
			ZBX_NODE_STATUS_STOPPED, info->nodeid);

	DBcommit_without_reconnect();
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
 * Function: zbx_ha_report_status                                             *
 *                                                                            *
 * Purpose: report cluster status in log file                                 *
 *                                                                            *
 ******************************************************************************/
int	zbx_ha_report_status(char **error)
{
	if (FAIL == zbx_ipc_async_socket_send(&ha_socket, ZBX_IPC_SERVICE_HA_REPORT, NULL, 0))
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
		zbx_free(errmsg);
		goto out;
	}

	if (FAIL == zbx_ipc_async_socket_flush(&ha_socket, ZBX_HA_SERVICE_TIMEOUT))
	{
		*error = zbx_dsprintf(NULL, "cannot send message to HA manager service");
		zbx_free(errmsg);
		goto out;
	}

	ret = SUCCEED;
out:

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
	int	status, ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (SUCCEED == ha_send_manager_message(ZBX_IPC_SERVICE_HA_STOP, error))
	{
		while (-1 == waitpid(ha_pid, &status, 0))
		{
			if (EINTR == errno)
				continue;

			*error = zbx_dsprintf(NULL, "failed to wait for HA manager to exit: %s", zbx_strerror(errno));
			goto out;
		}

		ret = SUCCEED;
		goto out;
	}

	*error = zbx_dsprintf(NULL, "cannot create HA manager process: %s", zbx_strerror(errno));

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
	int	status;

	kill(ha_pid, SIGKILL);
	waitpid(ha_pid, &status, 0);

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

	info.nodeid = 0;
	info.name = ZBX_NULL2EMPTY_STR(CONFIG_HA_NODE_NAME);
	info.ha_status = (int)(uintptr_t)((zbx_thread_args_t *)args)->args;
	info.error = NULL;
	info.db_status = ZBX_DB_DOWN;

	lastcheck = zbx_time();

	if (ZBX_NODE_STATUS_UNKNOWN == info.ha_status)
	{
		ha_db_register_node(&info);

		if (ZBX_NODE_STATUS_ERROR == info.ha_status)
			stop = SUCCEED;
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
				case ZBX_IPC_SERVICE_HA_REPORT:
					ha_report_status();
					break;
				case 100: // TODO: debug command, remove
					info.ha_status = ZBX_NODE_STATUS_STANDBY;
					ha_notify_parent(main_proc, info.ha_status, info.error);
					break;
				case 101: // TODO: debug command, remove
					info.ha_status = ZBX_NODE_STATUS_ACTIVE;
					ha_notify_parent(main_proc, info.ha_status, info.error);
					break;
			}

			zbx_ipc_message_free(message);
		}
	}

	zabbix_log(LOG_LEVEL_INFORMATION, "HA manager has been paused");

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
	}

	zbx_free(info.error);

	ha_db_update_status(&info);

	DBclose();

	zbx_ipc_service_close(&service);

	zabbix_log(LOG_LEVEL_INFORMATION, "HA manager has been stopped");


	exit(EXIT_SUCCESS);

	return 0;
}
