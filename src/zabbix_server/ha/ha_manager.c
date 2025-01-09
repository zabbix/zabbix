/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

#include "ha.h"

#include "../rtc/rtc_server.h"
#include "../audit/audit_server.h"

#include "zbx_ha_constants.h"
#include "zbxipcservice.h"
#include "zbxserialize.h"
#include "zbxthreads.h"
#include "zbxmutexs.h"
#include "audit/zbxaudit.h"
#include "audit/zbxaudit_ha.h"
#include "zbxnum.h"
#include "zbxtime.h"
#include "zbxip.h"
#include "zbxcomms.h"
#include "zbxstr.h"
#include "zbxrtc.h"
#include "zbxjson.h"
#include "zbxdb.h"
#include "zbxalgo.h"
#include "zbxlog.h"
#include "zbxself.h"
#include "zbxtimekeeper.h"

#define ZBX_HA_POLL_PERIOD	5

#define ZBX_HA_NODE_LOCK	1

#define zbx_cuid_empty(a)	('\0' == *(a).str ? SUCCEED : FAIL)
#define zbx_cuid_compare(a, b)	(0 == memcmp((a).str, (b).str, CUID_LEN) ? SUCCEED : FAIL)
#define zbx_cuid_clear(a)	memset((a).str, 0, CUID_LEN)

typedef struct
{
	char	str[CUID_LEN];
}
zbx_cuid_t;

static pid_t		ha_pid = ZBX_THREAD_ERROR;
static zbx_cuid_t	ha_sessionid;

typedef struct
{
	zbx_cuid_t	ha_nodeid;

	/* HA status */
	int		ha_status;

	/* database connection status */
	int		db_status;

	/* timestamp in database time */
	int		db_time;

	int		failover_delay;

	/* last access time of active node */
	int		lastaccess_active;

	/* number of ticks active node has not been updated its lastaccess */
	int		offline_ticks_active;

	/* 0 if auditlog is disabled */
	int		auditlog_enabled;

	/* 0 if audit logging for autoregistration, network discovery or LLD is disabled */
	int		auditlog_mode;

	const char	*name;
	char		*error;

	zbx_dbconn_t	*dbconn;
}
zbx_ha_info_t;

ZBX_THREAD_ENTRY(ha_manager_thread, args);

typedef struct
{
	zbx_cuid_t	ha_nodeid;
	zbx_cuid_t	ha_sessionid;
	char		*name;
	char		*address;
	unsigned short	port;
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

static void	ha_set_error(zbx_ha_info_t *info, const char *fmt, ...) __zbx_attr_format_printf(2, 3);
static zbx_db_result_t	ha_db_select(zbx_ha_info_t *info, const char *sql, ...) __zbx_attr_format_printf(2, 3);
static int	ha_db_execute(zbx_ha_info_t *info, const char *sql, ...) __zbx_attr_format_printf(2, 3);

/******************************************************************************
 *                                                                            *
 * Purpose: check if server is a part of HA cluster                           *
 *                                                                            *
 ******************************************************************************/
static int is_ha_cluster(const char *ha_node_name)
{
	return (NULL != ha_node_name && '\0' != *ha_node_name) ? 1 : 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: connect, send message and receive response in a given timeout     *
 *                                                                            *
 * Parameters: code         - [IN] message code                               *
 *             timeout      - [IN] time allowed to be spent on receive. Note  *
 *                                 that this does not include open, send and  *
 *                                 flush that have their own timeouts.        *
 *             data         - [IN] data                                       *
 *             size         - [IN] data size                                  *
 *             out          - [OUT] received message or NULL on error.        *
 *                                  The message must be freed by zbx_free().  *
 *             error        - [OUT]                                           *
 *                                                                            *
 * Return value: SUCCEED - successfully sent message and received response    *
 *                         or timeout occurred while waiting for response     *
 *               FAIL    - error occurred                                     *
 *                                                                            *
 ******************************************************************************/
static int	ha_manager_send_message(zbx_uint32_t code, int timeout, const unsigned char *data, zbx_uint32_t size,
		unsigned char **out, char **error)
{
	zbx_ipc_message_t	*message;
	zbx_ipc_async_socket_t	asocket;
	int			ret = FAIL;

	if (FAIL == zbx_ipc_async_socket_open(&asocket, ZBX_IPC_SERVICE_HA, timeout, error))
		return FAIL;

	if (FAIL == zbx_ipc_async_socket_send(&asocket, code, data, size))
	{
		*error = zbx_strdup(NULL, "Cannot send request");
		goto out;
	}

	if (FAIL == zbx_ipc_async_socket_flush(&asocket, timeout))
	{
		*error = zbx_strdup(NULL, "Cannot flush request");
		goto out;
	}

	if (FAIL == zbx_ipc_async_socket_recv(&asocket, timeout, &message))
	{
		*error = zbx_strdup(NULL, "Cannot receive response");
		goto out;
	}

	if (NULL != message)
	{
		*out = message->data;
		message->data = NULL;
		zbx_ipc_message_free(message);
	}
	else
		*out = NULL;
	ret = SUCCEED;
out:
	zbx_ipc_async_socket_close(&asocket);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: update parent process with ha_status and failover delay           *
 *                                                                            *
 ******************************************************************************/
static void	ha_update_parent(zbx_ipc_async_socket_t *rtc_socket, zbx_ha_info_t *info)
{
	zbx_uint32_t	len = 0, error_len;
	unsigned char	*ptr, *data;
	const char	*error = info->error;
	int		ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() ha_status:%s failover_delay:%d info:%s", __func__,
			zbx_ha_status_str(info->ha_status),  info->failover_delay, ZBX_NULL2EMPTY_STR(info->error));

	zbx_serialize_prepare_value(len, info->ha_status);
	zbx_serialize_prepare_value(len, info->failover_delay);
	zbx_serialize_prepare_str(len, error);

	ptr = data = (unsigned char *)zbx_malloc(NULL, len);
	ptr += zbx_serialize_value(ptr, info->ha_status);
	ptr += zbx_serialize_value(ptr, info->failover_delay);
	(void)zbx_serialize_str(ptr, error, error_len);

	if (SUCCEED == (ret = zbx_ipc_async_socket_send(rtc_socket, ZBX_IPC_SERVICE_HA_STATUS_UPDATE, data, len)))
		ret = zbx_ipc_async_socket_flush(rtc_socket, ZBX_HA_SERVICE_TIMEOUT);

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
 * Purpose: send heartbeat message to main process                            *
 *                                                                            *
 ******************************************************************************/
static void	ha_send_heartbeat(zbx_ipc_async_socket_t *rtc_socket)
{
	if (SUCCEED != zbx_ipc_async_socket_send(rtc_socket, ZBX_IPC_SERVICE_HA_HEARTBEAT, NULL, 0) ||
			SUCCEED != zbx_ipc_async_socket_flush(rtc_socket, ZBX_HA_SERVICE_TIMEOUT))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot send HA heartbeat to main process");
		exit(EXIT_FAILURE);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: set HA manager error                                              *
 *                                                                            *
 ******************************************************************************/
static void	ha_set_error(zbx_ha_info_t *info, const char *fmt, ...)
{
	va_list	args;
	size_t	len;

	/* don't override errors */
	if (ZBX_NODE_STATUS_ERROR == info->ha_status)
		return;

	va_start(args, fmt);

	/* zbx_vsnprintf_check_len() cannot return negative result */
	len = (size_t)zbx_vsnprintf_check_len(fmt, args) + 1;

	va_end(args);

	info->error = (char *)zbx_malloc(info->error, len);

	va_start(args, fmt);
	zbx_vsnprintf(info->error, len, fmt, args);
	va_end(args);

	info->ha_status = ZBX_NODE_STATUS_ERROR;
}

/******************************************************************************
 *                                                                            *
 * Purpose: start database transaction                                        *
 *                                                                            *
 * Comments: Sets error status on non-recoverable database error              *
 *                                                                            *
 ******************************************************************************/
static int	ha_db_begin(zbx_ha_info_t *info)
{
	if (ZBX_DB_DOWN == info->db_status)
		info->db_status = zbx_dbconn_open(info->dbconn);

	if (ZBX_DB_OK <= info->db_status)
		info->db_status = zbx_dbconn_begin(info->dbconn);

	if (ZBX_DB_FAIL == info->db_status)
		ha_set_error(info, "database error");
	else if (ZBX_DB_DOWN == info->db_status)
		zbx_dbconn_close(info->dbconn);

	return info->db_status;
}

/******************************************************************************
 *                                                                            *
 * Purpose: roll back database transaction                                    *
 *                                                                            *
 * Comments: Sets error status on non-recoverable database error              *
 *                                                                            *
 ******************************************************************************/
static int	ha_db_rollback(zbx_ha_info_t *info)
{
	if (ZBX_DB_OK > (info->db_status = zbx_dbconn_rollback(info->dbconn)))
	{
		if (ZBX_DB_DOWN == info->db_status)
			zbx_dbconn_close(info->dbconn);
	}

	if (ZBX_DB_FAIL == info->db_status)
		ha_set_error(info, "database error");
	else if (ZBX_DB_DOWN == info->db_status)
		zbx_dbconn_close(info->dbconn);

	return info->db_status;
}

/******************************************************************************
 *                                                                            *
 * Purpose: commit/rollback database transaction depending on commit result   *
 *                                                                            *
 * Comments: Sets error status on non-recoverable database error              *
 *                                                                            *
 ******************************************************************************/
static int	ha_db_commit(zbx_ha_info_t *info)
{
	if (ZBX_DB_OK <= info->db_status)
		info->db_status = zbx_dbconn_commit(info->dbconn);

	if (ZBX_DB_OK > info->db_status)
	{
		zbx_dbconn_rollback(info->dbconn);

		if (ZBX_DB_FAIL == info->db_status)
			ha_set_error(info, "database error");
		else
			zbx_dbconn_close(info->dbconn);
	}

	return info->db_status;
}

/******************************************************************************
 *                                                                            *
 * Purpose: perform database select sql query based on current database       *
 *          connection status                                                 *
 *                                                                            *
 ******************************************************************************/
static zbx_db_result_t	ha_db_select(zbx_ha_info_t *info, const char *sql, ...)
{
	va_list		args;
	zbx_db_result_t	result;

	if (ZBX_DB_OK > info->db_status)
		return NULL;

	va_start(args, sql);
	result = zbx_dbconn_vselect(info->dbconn, sql, args);
	va_end(args);

	if (NULL == result)
	{
		info->db_status = ZBX_DB_FAIL;
	}
	else if (ZBX_DB_DOWN == (intptr_t)result)
	{
		info->db_status = ZBX_DB_DOWN;
		result = NULL;
	}

	return result;
}

/******************************************************************************
 *                                                                            *
 * Purpose: perform database sql query based on current database              *
 *          connection status                                                 *
 *                                                                            *
 ******************************************************************************/
static int	ha_db_execute(zbx_ha_info_t *info, const char *sql, ...)
{
	va_list	args;

	if (ZBX_DB_OK > info->db_status)
		return FAIL;

	va_start(args, sql);
	info->db_status = zbx_dbconn_vexecute(info->dbconn, sql, args);
	va_end(args);

	return ZBX_DB_OK <= info->db_status ? SUCCEED : FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: update HA configuration from database                             *
 *                                                                            *
 ******************************************************************************/
static int	ha_db_update_config(zbx_ha_info_t *info)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;

	if (NULL == (result = ha_db_select(info, "select ha_failover_delay,auditlog_enabled,auditlog_mode "
			"from config")))
	{
		return FAIL;
	}

	if (NULL != (row = zbx_db_fetch(result)))
	{
		if (SUCCEED != zbx_is_time_suffix(row[0], &info->failover_delay, ZBX_LENGTH_UNLIMITED))
			THIS_SHOULD_NEVER_HAPPEN;

		info->auditlog_enabled = atoi(row[1]);
		info->auditlog_mode = atoi(row[2]);
	}
	else
		THIS_SHOULD_NEVER_HAPPEN;

	zbx_db_free_result(result);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get all nodes from database                                       *
 *                                                                            *
 * Return value: SUCCEED - the nodes were retrieved from database             *
 *               FAIL    - database/connection error                          *
 *                                                                            *
 ******************************************************************************/
static int	ha_db_get_nodes(zbx_ha_info_t *info, zbx_vector_ha_node_t *nodes, int lock)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;

	if (NULL == (result = ha_db_select(info, "select ha_nodeid,name,status,lastaccess,address,port,ha_sessionid"
			" from ha_node order by ha_nodeid%s",
			(0 == lock ? "" : ZBX_FOR_UPDATE))))
	{
		return FAIL;
	}

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_ha_node_t	*node;

		node = (zbx_ha_node_t *)zbx_malloc(NULL, sizeof(zbx_ha_node_t));
		zbx_strlcpy(node->ha_nodeid.str, row[0], sizeof(node->ha_nodeid));
		node->name = zbx_strdup(NULL, row[1]);
		node->status = atoi(row[2]);
		node->lastaccess = atoi(row[3]);
		node->address = zbx_strdup(NULL, row[4]);

		if (SUCCEED != zbx_is_ushort(row[5], &node->port))
		{
			zabbix_log(LOG_LEVEL_WARNING, "node \"%s\" has invalid port value \"%s\"", row[1], row[5]);
			node->port = 0;
		}

		zbx_strlcpy(node->ha_sessionid.str, row[6], sizeof(node->ha_sessionid));
		zbx_vector_ha_node_append(nodes, node);
	}

	zbx_db_free_result(result);

	return SUCCEED;
}

/******************************************************************************
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
 * Purpose: get server external address and port from configuration           *
 *                                                                            *
 ******************************************************************************/
static void	ha_get_external_address(char **address, unsigned short *port, zbx_ha_config_t *ha_config)
{
	if (NULL != ha_config->ha_node_address)
	{
		(void)zbx_parse_serveractive_element(ha_config->ha_node_address, address, port, 0);
	}
	else if (NULL != ha_config->default_node_ip)
	{
		char	*tmp;

		zbx_strsplit_first(ha_config->default_node_ip, ',', address, &tmp);
		zbx_free(tmp);
	}

	if (NULL == *address || 0 == strcmp(*address, "0.0.0.0") || 0 == strcmp(*address, "::"))
		*address = zbx_strdup(*address, "localhost");

	if (0 == *port)
	{
		if (0 != ha_config->default_node_port)
			*port = (unsigned short)ha_config->default_node_port;
		else
			*port = ZBX_DEFAULT_SERVER_PORT;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: lock nodes in database                                            *
 *                                                                            *
 * Comments: To lock ha_node table it must have at least one node             *
 *                                                                            *
 ******************************************************************************/
static int	ha_db_lock_nodes(zbx_ha_info_t *info)
{
	zbx_db_result_t	result;

	if (NULL == (result = ha_db_select(info, "select null from ha_node order by ha_nodeid" ZBX_FOR_UPDATE)))
		return FAIL;

	zbx_db_free_result(result);

	return SUCCEED;
}

/******************************************************************************
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
 * Purpose: check if server can be started in standalone configuration        *
 *                                                                            *
 * Return value: SUCCEED - server can be started in active mode               *
 *               FAIL    - server cannot be started based on node registry    *
 *                                                                            *
 * Comments: Sets error status on configuration errors.                       *
 *                                                                            *
 ******************************************************************************/
static int	ha_check_standalone_config(zbx_ha_info_t *info, zbx_vector_ha_node_t *nodes, int db_time)
{
	for (int i = 0; i < nodes->values_num; i++)
	{
		if ('\0' == *nodes->values[i]->name)
			continue;

		if (ZBX_NODE_STATUS_STOPPED != nodes->values[i]->status &&
				SUCCEED == ha_is_available(info, nodes->values[i]->lastaccess, db_time))
		{
			ha_set_error(info, "cannot change mode to standalone while HA node \"%s\" is %s",
					nodes->values[i]->name, zbx_ha_status_str(nodes->values[i]->status));
			return FAIL;
		}
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if server can be started in cluster configuration           *
 *                                                                            *
 * Parameters: info     - [IN] HA node information                            *
 *             nodes    - [IN] cluster nodes                                  *
 *             db_time  - [IN] current database timestamp                     *
 *             activate - [OUT] SUCCEED - start in active mode                *
 *                              FAIL    - start in standby mode               *
 *                                                                            *
 * Return value: SUCCEED - server can be started in returned mode             *
 *               FAIL    - server cannot be started based on node registry    *
 *                                                                            *
 * Comments: Sets error status on configuration errors.                       *
 *                                                                            *
 ******************************************************************************/
static int	ha_check_cluster_config(zbx_ha_info_t *info, zbx_vector_ha_node_t *nodes, int db_time, int *activate)
{
	*activate = SUCCEED;

	for (int i = 0; i < nodes->values_num; i++)
	{
		if (ZBX_NODE_STATUS_STOPPED == nodes->values[i]->status ||
				SUCCEED != ha_is_available(info, nodes->values[i]->lastaccess, db_time))
		{
			continue;
		}

		if ('\0' == *nodes->values[i]->name)
		{
			ha_set_error(info, "cannot change mode to HA while standalone node is %s",
					zbx_ha_status_str(nodes->values[i]->status));
			return FAIL;
		}

		if (0 == strcmp(info->name, nodes->values[i]->name))
		{
			ha_set_error(info, "found %s duplicate \"%s\" node",
					zbx_ha_status_str(nodes->values[i]->status), info->name);
			return FAIL;
		}

		/* immediately switch to active mode if there is no other node that can take over */
		if (ZBX_NODE_STATUS_ACTIVE == nodes->values[i]->status ||
				ZBX_NODE_STATUS_STANDBY == nodes->values[i]->status)
		{
			*activate = FAIL;
		}
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get current database time                                         *
 *                                                                            *
 ******************************************************************************/
static int	ha_db_get_time(zbx_ha_info_t *info, int *db_time)
{
	zbx_db_row_t	row;
	zbx_db_result_t	result;
	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (NULL == (result = ha_db_select(info, "select " ZBX_DB_TIMESTAMP() " from config")))
		goto out;

	if (NULL != (row = zbx_db_fetch(result)))
		*db_time = atoi(row[0]);
	else
		*db_time = 0;

	zbx_db_free_result(result);

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s db_time:%d", __func__, zbx_result_string(ret),
			SUCCEED == ret ? *db_time : -1);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: flush audit taking in account database connection status          *
 *                                                                            *
 ******************************************************************************/
static void	ha_flush_audit(zbx_ha_info_t *info)
{
	if (ZBX_DB_OK > info->db_status)
	{
		zbx_audit_clean(ZBX_AUDIT_HA_CONTEXT);
		return;
	}

	info->db_status = zbx_audit_flush_dbconn(info->dbconn, ZBX_AUDIT_HA_CONTEXT);
}

/******************************************************************************
 *                                                                            *
 * Purpose: add new node record in ha_node table if necessary                 *
 *                                                                            *
 * Return value: SUCCEED - node exists, was created or database is offline    *
 *               FAIL    - node configuration or database error               *
 *                                                                            *
 ******************************************************************************/
static void	ha_db_create_node(zbx_ha_info_t *info, zbx_ha_config_t *ha_config)
{
	zbx_vector_ha_node_t	nodes;
	int			i, activate, db_time;
	zbx_cuid_t		nodeid;
	char			*name_esc;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_ha_node_create(&nodes);

	if (ZBX_DB_OK > ha_db_begin(info))
		goto finish;

	if (SUCCEED != ha_db_get_nodes(info, &nodes, 0))
		goto out;

	if (FAIL == ha_db_update_config(info))
		goto out;

	for (i = 0; i < nodes.values_num; i++)
	{
		if (0 == strcmp(info->name, nodes.values[i]->name))
		{
			nodeid = nodes.values[i]->ha_nodeid;
			goto out;
		}
	}

	if (SUCCEED != ha_db_get_time(info, &db_time))
		goto out;

	if (0 != is_ha_cluster(ha_config->ha_node_name))
	{
		if (SUCCEED != ha_check_cluster_config(info, &nodes, db_time, &activate))
			goto out;
	}
	else
	{
		if (SUCCEED != ha_check_standalone_config(info, &nodes, db_time))
			goto out;
	}

	zbx_new_cuid(nodeid.str);
	name_esc = zbx_db_dyn_escape_string(info->name);

	if (SUCCEED == ha_db_execute(info, "insert into ha_node (ha_nodeid,name,status,lastaccess)"
			" values ('%s','%s',%d," ZBX_DB_TIMESTAMP() ")",
			nodeid.str, name_esc, ZBX_NODE_STATUS_STOPPED))
	{
		zbx_audit_init(info->auditlog_enabled, info->auditlog_mode, ZBX_AUDIT_HA_CONTEXT);
		zbx_audit_ha_create_entry(ZBX_AUDIT_ACTION_ADD, nodeid.str, info->name);
		zbx_audit_ha_add_create_fields(nodeid.str, info->name, ZBX_NODE_STATUS_STOPPED);
		ha_flush_audit(info);
	}

	zbx_free(name_esc);
out:
	if (ZBX_NODE_STATUS_ERROR != info->ha_status)
		ha_db_commit(info);
	else
		ha_db_rollback(info);

	if (ZBX_NODE_STATUS_ERROR != info->ha_status)
	{
		if (ZBX_DB_OK <= info->db_status)
			info->ha_nodeid = nodeid;
	}
finish:
	zbx_vector_ha_node_clear_ext(&nodes, zbx_ha_node_free);
	zbx_vector_ha_node_destroy(&nodes);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: check for active and standby node availability and update         *
 *          unavailable nodes accordingly                                     *
 *                                                                            *
 ******************************************************************************/
static int	ha_db_check_unavailable_nodes(zbx_ha_info_t *info, zbx_vector_ha_node_t *nodes, int db_time)
{
	int			ret = SUCCEED;
	zbx_vector_str_t	unavailable_nodes;

	zbx_vector_str_create(&unavailable_nodes);

	for (int i = 0; i < nodes->values_num; i++)
	{
		if (SUCCEED == zbx_cuid_compare(nodes->values[i]->ha_nodeid, info->ha_nodeid))
			continue;

		if (ZBX_NODE_STATUS_STANDBY != nodes->values[i]->status &&
				ZBX_NODE_STATUS_ACTIVE != nodes->values[i]->status)
		{
			continue;
		}

		if (db_time >= nodes->values[i]->lastaccess + info->failover_delay)
		{
			zbx_vector_str_append(&unavailable_nodes, nodes->values[i]->ha_nodeid.str);

			zbx_audit_ha_create_entry(ZBX_AUDIT_ACTION_UPDATE,
					nodes->values[i]->ha_nodeid.str, nodes->values[i]->name);
			zbx_audit_ha_update_field_int(nodes->values[i]->ha_nodeid.str, ZBX_AUDIT_HA_STATUS,
					nodes->values[i]->status, ZBX_NODE_STATUS_UNAVAILABLE);
		}
	}

	if (0 != unavailable_nodes.values_num)
	{
		char	*sql = NULL;
		size_t	sql_alloc = 0, sql_offset = 0;

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update ha_node set status=%d where",
				ZBX_NODE_STATUS_UNAVAILABLE);

		zbx_db_add_str_condition_alloc(&sql, &sql_alloc, &sql_offset, "ha_nodeid",
				(const char **)unavailable_nodes.values, unavailable_nodes.values_num);

		ret = ha_db_execute(info, "%s", sql);
		zbx_free(sql);
	}

	zbx_vector_str_destroy(&unavailable_nodes);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: register server node                                              *
 *                                                                            *
 * Return value: SUCCEED - node was registered or database was offline        *
 *               FAIL    - fatal error                                        *
 *                                                                            *
 * Comments: If registration was successful the status will be set to either  *
 *           active or standby. If database connection was lost the status    *
 *           will stay unknown until another registration attempt succeeds.   *
 *                                                                            *
 *           In the case of critical error the error status will be set.      *
 *                                                                            *
 ******************************************************************************/
static void	ha_db_register_node(zbx_ha_info_t *info, zbx_ha_config_t *ha_config)
{
	zbx_vector_ha_node_t	nodes;
	int			ha_status = ZBX_NODE_STATUS_UNKNOWN, activate = SUCCEED, db_time;
	char			*address = NULL, *sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	unsigned short		port = 0;
	zbx_ha_node_t		*node;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_ha_node_create(&nodes);

	ha_db_create_node(info, ha_config);

	if (SUCCEED == zbx_cuid_empty(info->ha_nodeid))
		goto finish;

	if (ZBX_DB_OK > ha_db_begin(info))
		goto finish;

	if (SUCCEED != ha_db_get_nodes(info, &nodes, ZBX_HA_NODE_LOCK))
		goto out;

	if (SUCCEED != ha_db_get_time(info, &db_time))
		goto out;

	if (0 != is_ha_cluster(ha_config->ha_node_name))
	{
		if (SUCCEED != ha_check_cluster_config(info, &nodes, db_time, &activate))
			goto out;
	}
	else
	{
		if (SUCCEED != ha_check_standalone_config(info, &nodes, db_time))
			goto out;
	}

	if (NULL == (node = ha_find_node_by_name(&nodes, info->name)))
	{
		ha_set_error(info, "cannot find server node \"%s\" in registry", info->name);
		goto out;
	}

	ha_status = SUCCEED == activate ? ZBX_NODE_STATUS_ACTIVE : ZBX_NODE_STATUS_STANDBY;
	ha_get_external_address(&address, &port, ha_config);

	zbx_audit_init(info->auditlog_enabled, info->auditlog_mode, ZBX_AUDIT_HA_CONTEXT);
	zbx_audit_ha_create_entry(ZBX_AUDIT_ACTION_UPDATE, info->ha_nodeid.str, info->name);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update ha_node set lastaccess="
				ZBX_DB_TIMESTAMP() ",ha_sessionid='%s'", ha_sessionid.str);

	if (ha_status != node->status)
	{
		zbx_audit_ha_update_field_int(info->ha_nodeid.str, ZBX_AUDIT_HA_STATUS,
				node->status, ha_status);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, ",status=%d", ha_status);
	}

	if (0 != strcmp(address, node->address))
	{
		char	*address_esc;

		address_esc = zbx_db_dyn_escape_string(address);
		zbx_audit_ha_update_field_string(node->ha_nodeid.str, ZBX_AUDIT_HA_ADDRESS,
				node->address, address);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, ",address='%s'", address_esc);
		zbx_free(address_esc);
	}

	if (port != node->port)
	{
		zbx_audit_ha_update_field_int(info->ha_nodeid.str, ZBX_AUDIT_HA_PORT, node->port,
				port);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, ",port=%d", port);
	}

	if (SUCCEED == ha_db_execute(info, "%s where ha_nodeid='%s'", sql, info->ha_nodeid.str))
	{
		if (0 != is_ha_cluster(ha_config->ha_node_name))
			ha_db_execute(info, "delete from ha_node where name=''");
		else
			ha_db_execute(info, "delete from ha_node where name<>''");
	}

	if (0 != is_ha_cluster(ha_config->ha_node_name) && ZBX_NODE_STATUS_ERROR != info->ha_status &&
			ZBX_NODE_STATUS_ACTIVE == ha_status)
	{
		ha_db_check_unavailable_nodes(info, &nodes, db_time);
	}

	ha_flush_audit(info);

	zbx_free(sql);
	zbx_free(address);
out:
	if (ZBX_NODE_STATUS_ERROR != info->ha_status)
		ha_db_commit(info);
	else
		ha_db_rollback(info);

	if (ZBX_NODE_STATUS_ERROR != info->ha_status)
	{
		if (ZBX_DB_OK <= info->db_status)
			info->ha_status = ha_status;
	}
finish:
	zbx_vector_ha_node_clear_ext(&nodes, zbx_ha_node_free);
	zbx_vector_ha_node_destroy(&nodes);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() nodeid:%s ha_status:%s db_status:%d", __func__,
			info->ha_nodeid.str, zbx_ha_status_str(info->ha_status), info->db_status);
}

/******************************************************************************
 *                                                                            *
 * Purpose: check for standby nodes being unavailable for failrover_delay     *
 *          seconds and mark them unavailable                                 *
 *                                                                            *
 ******************************************************************************/
static int	ha_check_standby_nodes(zbx_ha_info_t *info, zbx_vector_ha_node_t *nodes, int db_time)
{
	int	ret;

	zbx_audit_init(info->auditlog_enabled, info->auditlog_mode, ZBX_AUDIT_HA_CONTEXT);

	if (SUCCEED == (ret = ha_db_check_unavailable_nodes(info, nodes, db_time)))
		ha_flush_audit(info);
	else
		zbx_audit_clean(ZBX_AUDIT_HA_CONTEXT);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check for active nodes being unavailable for failover_delay       *
 *          seconds, mark them unavailable and set own status to active       *
 *                                                                            *
 ******************************************************************************/
static int	ha_check_active_node(zbx_ha_info_t *info, zbx_vector_ha_node_t *nodes, int *unavailable_index,
		int *ha_status, int *ha_status_change_reason)
{
	int	i, ret = SUCCEED;

	for (i = 0; i < nodes->values_num; i++)
	{
		if (ZBX_NODE_STATUS_ACTIVE == nodes->values[i]->status)
		{
			if ('\0' == *nodes->values[i]->name)
			{
				ha_set_error(info, "found active standalone node in HA mode");
				return FAIL;
			}

			break;
		}
	}

	/* 1) No active nodes - set this node as active.                */
	/* 2) This node is active - update its status as it might have  */
	/*    switched itself to standby mode in the case of prolonged  */
	/*    database connection loss.                                 */
	if (i == nodes->values_num || SUCCEED == zbx_cuid_compare(nodes->values[i]->ha_nodeid, info->ha_nodeid))
	{
		*ha_status = ZBX_NODE_STATUS_ACTIVE;
		*ha_status_change_reason = ZBX_AUDIT_HA_ST_CH_REASON_NO_ACTIVE_NODES;
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

		if (info->failover_delay / ZBX_HA_POLL_PERIOD < info->offline_ticks_active)
		{
			*unavailable_index = i;
			*ha_status = ZBX_NODE_STATUS_ACTIVE;
			*ha_status_change_reason = ZBX_AUDIT_HA_ST_CH_REASON_DB_CONNECTION_LOSS;
		}
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check HA status based on nodes                                    *
 *                                                                            *
 * Comments: Sets error status on critical errors forcing manager to exit     *
 *                                                                            *
 ******************************************************************************/
static void	ha_check_nodes(zbx_ha_info_t *info, zbx_ha_config_t *ha_config)
{
	zbx_vector_ha_node_t	nodes;
	zbx_ha_node_t		*node;
	int			db_time, ha_status, ha_status_change_reason = ZBX_AUDIT_HA_ST_CH_REASON_UNKNOWN,
				unavailable_index = FAIL;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() ha_status:%s db_status:%d", __func__, zbx_ha_status_str(info->ha_status),
			info->db_status);

	zbx_vector_ha_node_create(&nodes);

	if (ZBX_DB_OK > ha_db_begin(info))
		goto finish;

	ha_status = info->ha_status;

	if (SUCCEED != ha_db_get_nodes(info, &nodes, ZBX_HA_NODE_LOCK))
		goto out;

	if (NULL == (node = ha_find_node_by_name(&nodes, info->name)))
	{
		ha_set_error(info, "cannot find server node \"%s\" in registry", info->name);
		goto out;
	}

	if (SUCCEED != zbx_cuid_compare(ha_sessionid, node->ha_sessionid))
	{
		if ('\0' == *info->name)
		{
			ha_set_error(info, "multiple servers have been started without configuring \"HANodeName\" "
					"parameter");
		}
		else
			ha_set_error(info, "the server HA registry record has changed ownership");
		goto out;
	}

	/* update nodeid after manager restart */
	if (SUCCEED == zbx_cuid_empty(info->ha_nodeid))
		info->ha_nodeid = node->ha_nodeid;

	if (SUCCEED != ha_db_update_config(info))
		goto out;

	if (SUCCEED != ha_db_get_time(info, &db_time))
		goto out;

	if (0 != is_ha_cluster(ha_config->ha_node_name))
	{
		if (ZBX_NODE_STATUS_ACTIVE == info->ha_status)
		{
			if (SUCCEED != ha_check_standby_nodes(info, &nodes, db_time))
				goto out;
		}
		else /* passive status */
		{
			if (SUCCEED != ha_check_active_node(info, &nodes, &unavailable_index, &ha_status,
					&ha_status_change_reason))
			{
				goto out;
			}
		}
	}

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "update ha_node set lastaccess=" ZBX_DB_TIMESTAMP());

	zbx_audit_init(info->auditlog_enabled, info->auditlog_mode, ZBX_AUDIT_HA_CONTEXT);

	if (ha_status != node->status)
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, ",status=%d", ha_status);

		zbx_audit_ha_create_entry(ZBX_AUDIT_ACTION_UPDATE, node->ha_nodeid.str, node->name);
		zbx_audit_ha_update_field_int(node->ha_nodeid.str, ZBX_AUDIT_HA_STATUS, node->status,
				ha_status);

		if (ZBX_NODE_STATUS_ACTIVE == ha_status)
		{
			zbx_audit_ha_add_field_int(info->ha_nodeid.str, ZBX_AUDIT_HA_STATUS_CHANGE_REASON_TO_ACTIVE,
					ha_status_change_reason);
		}
	}

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " where ha_nodeid='%s'", info->ha_nodeid.str);

	if (SUCCEED == ha_db_execute(info, "%s", sql) && FAIL != unavailable_index)
	{
		zbx_ha_node_t	*last_active = nodes.values[unavailable_index];

		ha_db_execute(info, "update ha_node set status=%d where ha_nodeid='%s'",
				ZBX_NODE_STATUS_UNAVAILABLE, last_active->ha_nodeid.str);

		zbx_audit_ha_create_entry(ZBX_AUDIT_ACTION_UPDATE, last_active->ha_nodeid.str, last_active->name);
		zbx_audit_ha_update_field_int(last_active->ha_nodeid.str, ZBX_AUDIT_HA_STATUS, last_active->status,
				ZBX_NODE_STATUS_UNAVAILABLE);
	}

	ha_flush_audit(info);

	zbx_free(sql);
out:
	if (ZBX_NODE_STATUS_ERROR != info->ha_status)
		ha_db_commit(info);
	else
		ha_db_rollback(info);

	if (ZBX_NODE_STATUS_ERROR != info->ha_status)
	{
		if (ZBX_DB_OK <= info->db_status)
			info->ha_status = ha_status;
	}
finish:
	zbx_vector_ha_node_clear_ext(&nodes, zbx_ha_node_free);
	zbx_vector_ha_node_destroy(&nodes);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() nodeid:%s ha_status:%s db_status:%d", __func__,
			info->ha_nodeid.str, zbx_ha_status_str(info->ha_status), info->db_status);
}

/******************************************************************************
 *                                                                            *
 * Purpose: update node lastaccess                                            *
 *                                                                            *
 ******************************************************************************/
static void	ha_db_update_lastaccess(zbx_ha_info_t *info)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() ha_status:%s", __func__, zbx_ha_status_str(info->ha_status));

	if (ZBX_DB_OK > ha_db_begin(info))
		goto out;

	if (SUCCEED == ha_db_lock_nodes(info) &&
			SUCCEED == ha_db_execute(info, "update ha_node set lastaccess=" ZBX_DB_TIMESTAMP()
					" where ha_nodeid='%s'", info->ha_nodeid.str))
	{
		ha_db_commit(info);
	}
	else
		ha_db_rollback(info);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get cluster status in lld compatible json format                  *
 *                                                                            *
 ******************************************************************************/
static int	ha_db_get_nodes_json(zbx_ha_info_t *info, char **nodes_json, char **error, zbx_ha_config_t *ha_config)
{
	zbx_vector_ha_node_t	nodes;
	int			db_time, ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (ZBX_DB_OK > info->db_status)
		goto out;

	if (0 == is_ha_cluster(ha_config->ha_node_name))
	{
		/* return empty json array in standalone mode */
		*nodes_json = zbx_strdup(NULL, "[]");
		ret = SUCCEED;
		goto out;
	}

	if (SUCCEED != ha_db_get_time(info, &db_time))
		goto out;

	zbx_vector_ha_node_create(&nodes);

	if (SUCCEED == ha_db_get_nodes(info, &nodes, 0))
	{
		struct zbx_json	j;
		char		address[512];

		zbx_json_initarray(&j, 1024);

		for (int i = 0; i < nodes.values_num; i++)
		{
			zbx_snprintf(address, sizeof(address), "%s:%hu", nodes.values[i]->address,
					nodes.values[i]->port);
			zbx_json_addobject(&j, NULL);

			zbx_json_addstring(&j, ZBX_PROTO_TAG_ID, nodes.values[i]->ha_nodeid.str, ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&j, ZBX_PROTO_TAG_NAME, nodes.values[i]->name, ZBX_JSON_TYPE_STRING);
			zbx_json_addint64(&j, ZBX_PROTO_TAG_STATUS, (zbx_int64_t)nodes.values[i]->status);
			zbx_json_addint64(&j, ZBX_PROTO_TAG_LASTACCESS, (zbx_int64_t)nodes.values[i]->lastaccess);
			zbx_json_addstring(&j, ZBX_PROTO_TAG_ADDRESS, address, ZBX_JSON_TYPE_STRING);
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
 * Purpose: remove node by its cuid or name                                   *
 *                                                                            *
 ******************************************************************************/
static int	ha_remove_node_impl(zbx_ha_info_t *info, const char *node, char **result, char **error)
{
	zbx_vector_ha_node_t	nodes;
	int			i, ret = FAIL;

	if (ZBX_DB_OK > ha_db_begin(info))
	{
		*error = zbx_strdup(NULL, "database connection problem");
		return FAIL;
	}

	zbx_vector_ha_node_create(&nodes);

	if (SUCCEED != ha_db_get_nodes(info, &nodes, 0))
	{
		*error = zbx_strdup(NULL, "database connection problem");
		goto out;
	}

	for (i = 0; i < nodes.values_num; i++)
	{
		if (0 == strcmp(node, nodes.values[i]->ha_nodeid.str))
			break;
	}

	if (i == nodes.values_num)
	{
		for (i = 0; i < nodes.values_num; i++)
		{
			if (0 == strcmp(node, nodes.values[i]->name))
				break;
		}
	}

	if (i == nodes.values_num)
	{
		*error = zbx_dsprintf(NULL, "unknown node \"%s\"", node);
		goto out;
	}

	if (ZBX_NODE_STATUS_ACTIVE == nodes.values[i]->status || ZBX_NODE_STATUS_STANDBY == nodes.values[i]->status)
	{
		*error = zbx_dsprintf(NULL, "node \"%s\" is %s", nodes.values[i]->name,
				zbx_ha_status_str(nodes.values[i]->status));
		goto out;
	}

	if (SUCCEED != ha_db_execute(info, "delete from ha_node where ha_nodeid='%s'", nodes.values[i]->ha_nodeid.str))
	{
		*error = zbx_strdup(NULL, "database connection problem");
		goto out;
	}
	else
	{
		zbx_audit_init(info->auditlog_enabled, info->auditlog_mode, ZBX_AUDIT_HA_CONTEXT);
		zbx_audit_ha_create_entry(ZBX_AUDIT_ACTION_DELETE, nodes.values[i]->ha_nodeid.str,
				nodes.values[i]->name);
		ha_flush_audit(info);
	}

	ret = SUCCEED;
out:
	if (SUCCEED == ret)
	{
		if (ZBX_DB_OK <= ha_db_commit(info))
		{
			size_t	result_alloc = 0, result_offset = 0;

			zbx_strlog_alloc(LOG_LEVEL_WARNING, result, &result_alloc, &result_offset,
					"removed node \"%s\" with ID \"%s\"", nodes.values[i]->name,
					nodes.values[i]->ha_nodeid.str);
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
 * Purpose: report cluster status in log file                                 *
 *                                                                            *
 ******************************************************************************/
static void	ha_remove_node(zbx_ha_info_t *info, zbx_ipc_client_t *client, const zbx_ipc_message_t *message)
{
	char		*error = NULL, *result = NULL;
	zbx_uint32_t	len = 0, error_len, result_len;
	unsigned char	*data, *ptr;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	ha_remove_node_impl(info, (const char *)message->data, &result, &error);

	zbx_serialize_prepare_str(len, result);
	zbx_serialize_prepare_str(len, error);

	ptr = data = zbx_malloc(NULL, len);
	ptr += zbx_serialize_str(ptr, result, result_len);
	zbx_serialize_str(ptr, error, error_len);

	zbx_free(error);
	zbx_free(result);

	zbx_ipc_client_send(client, ZBX_IPC_SERVICE_HA_REMOVE_NODE, data, len);
	zbx_free(data);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: reply to ha_status request                                        *
 *                                                                            *
 ******************************************************************************/
static void	ha_send_status(zbx_ha_info_t *info, zbx_ipc_client_t *client)
{
	zbx_uint32_t	len = 0, error_len;
	unsigned char	*ptr, *data;
	const char	*error = info->error;
	int		ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() ha_status:%s info:%s", __func__, zbx_ha_status_str(info->ha_status),
			ZBX_NULL2EMPTY_STR(info->error));

	zbx_serialize_prepare_value(len, info->ha_status);
	zbx_serialize_prepare_value(len, info->failover_delay);
	zbx_serialize_prepare_str(len, error);

	ptr = data = (unsigned char *)zbx_malloc(NULL, len);
	ptr += zbx_serialize_value(ptr, info->ha_status);
	ptr += zbx_serialize_value(ptr, info->failover_delay);
	(void)zbx_serialize_str(ptr, error, error_len);

	ret = zbx_ipc_client_send(client, ZBX_IPC_SERVICE_HA_STATUS, data, len);
	zbx_free(data);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));
}

/******************************************************************************
 *                                                                            *
 * Purpose: set failover delay                                                *
 *                                                                            *
 ******************************************************************************/
static void	ha_set_failover_delay(zbx_ha_info_t *info, zbx_ipc_client_t *client, const zbx_ipc_message_t *message)
{
	int		delay;
	const char	*error = NULL;
	zbx_uint32_t	len = 0, error_len;
	unsigned char	*data;
	zbx_db_result_t	result;
	zbx_db_row_t	row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (NULL == (result = ha_db_select(info, "select configid,ha_failover_delay from config")))
	{
		error = "database error";
		goto out;
	}

	memcpy(&delay, message->data, sizeof(delay));

	if (NULL != (row = zbx_db_fetch(result)) &&
		SUCCEED == ha_db_execute(info, "update config set ha_failover_delay=%d", delay))
	{
		zbx_uint64_t	configid;

		info->failover_delay = delay;
		zabbix_log(LOG_LEVEL_WARNING, "HA failover delay set to %ds", delay);

		ZBX_STR2UINT64(configid, row[0]);
		zbx_audit_init(info->auditlog_enabled, info->auditlog_mode, ZBX_AUDIT_HA_CONTEXT);
		zbx_audit_settings_create_entry(ZBX_AUDIT_HA_CONTEXT, ZBX_AUDIT_ACTION_UPDATE, configid);
		zbx_audit_settings_update_field_int(ZBX_AUDIT_HA_CONTEXT, configid, "settings.ha_failover_delay",
				atoi(row[1]), delay);
		ha_flush_audit(info);
	}
	else
		error = "database error";

	zbx_db_free_result(result);
out:
	zbx_serialize_prepare_str(len, error);

	data = zbx_malloc(NULL, len);
	zbx_serialize_str(data, error, error_len);

	zbx_ipc_client_send(client, ZBX_IPC_SERVICE_HA_SET_FAILOVER_DELAY, data, len);
	zbx_free(data);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get failover delay                                                *
 *                                                                            *
 ******************************************************************************/
static void	ha_get_failover_delay(zbx_ha_info_t *info, zbx_ipc_client_t *client)
{

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_ipc_client_send(client, ZBX_IPC_SERVICE_HA_GET_FAILOVER_DELAY, (const unsigned char *)&info->failover_delay,
			(zbx_uint32_t)sizeof(info->failover_delay));

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}
/******************************************************************************
 *                                                                            *
 * Purpose: reply to get nodes request                                        *
 *                                                                            *
 ******************************************************************************/
static void	ha_send_node_list(zbx_ha_info_t *info, zbx_ipc_client_t *client, zbx_ha_config_t *ha_config)
{
	int		ret;
	char		*error = NULL, *nodes_json = NULL, *str;
	zbx_uint32_t	len = 0, str_len;
	unsigned char	*data, *ptr;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (SUCCEED == (ret = ha_db_get_nodes_json(info, &nodes_json, &error, ha_config)))
		str = nodes_json;
	else
		str = error;

	zbx_serialize_prepare_value(len, ret);
	zbx_serialize_prepare_str(len, str);

	ptr = data = zbx_malloc(NULL, len);
	ptr += zbx_serialize_value(ptr, ret);
	(void)zbx_serialize_str(ptr, str, str_len);
	zbx_free(str);

	zbx_ipc_client_send(client, ZBX_IPC_SERVICE_HA_GET_NODES, data, len);
	zbx_free(data);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: update node status in database on shutdown                        *
 *                                                                            *
 ******************************************************************************/
static void	ha_db_update_exit_status(zbx_ha_info_t *info)
{
	if (ZBX_NODE_STATUS_ACTIVE != info->ha_status && ZBX_NODE_STATUS_STANDBY != info->ha_status)
		return;

	if (ZBX_DB_OK > ha_db_begin(info))
		return;

	if (SUCCEED != ha_db_lock_nodes(info))
		goto out;

	if (SUCCEED == ha_db_execute(info, "update ha_node set status=%d where ha_nodeid='%s'",
			ZBX_NODE_STATUS_STOPPED, info->ha_nodeid.str))
	{
		zbx_audit_init(info->auditlog_enabled, info->auditlog_mode, ZBX_AUDIT_HA_CONTEXT);
		zbx_audit_ha_create_entry(ZBX_AUDIT_ACTION_UPDATE, info->ha_nodeid.str, info->name);
		zbx_audit_ha_update_field_int(info->ha_nodeid.str, ZBX_AUDIT_HA_STATUS, info->ha_status,
				ZBX_NODE_STATUS_STOPPED);
		ha_flush_audit(info);
	}
out:
	ha_db_commit(info);
}

/*
 * public API
 */

void	zbx_init_library_ha(void)
{
	zbx_new_cuid(ha_sessionid.str);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get HA manager status                                             *
 *                                                                            *
 * Comments: In the case of timeout the ha_status will be force to:           *
 *   standby - for cluster setup                                              *
 *   active  - for standalone setup                                           *
 *                                                                            *
 ******************************************************************************/
int	zbx_ha_get_status(const char *ha_node_name, int *ha_status, int *ha_failover_delay, char **error)
{
	int		ret;
	unsigned char	*result = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (SUCCEED == (ret = ha_manager_send_message(ZBX_IPC_SERVICE_HA_STATUS, ZBX_HA_SERVICE_TIMEOUT, NULL, 0,
			&result, error)))
	{
		if (NULL != result)
		{
			unsigned char	*ptr = result;
			zbx_uint32_t	len;

			ptr += zbx_deserialize_value(ptr, ha_status);
			ptr += zbx_deserialize_value(ptr, ha_failover_delay);
			(void)zbx_deserialize_str(ptr, error, len);

			zbx_free(result);

			if (ZBX_NODE_STATUS_ERROR == *ha_status)
				ret = FAIL;
		}
		else
		{
			if (0 != is_ha_cluster(ha_node_name))
				*ha_status = ZBX_NODE_STATUS_STANDBY;
			else
				*ha_status = ZBX_NODE_STATUS_ACTIVE;
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: handle HA manager notifications                                   *
 *                                                                            *
 * Comments: This function also monitors heartbeat notifications and          *
 *           returns standby status if no heartbeats are received for         *
 *           failover delay - poll period seconds. This would make main       *
 *           process to switch to standby mode and initiate teardown process  *
 *                                                                            *
 ******************************************************************************/
int	zbx_ha_dispatch_message(const char *ha_node_name, zbx_ipc_message_t *message, zbx_ha_rtc_state_t state,
		int *ha_status, int *ha_failover_delay, char **error)
{
	static time_t	last_hb;
	int		ret = SUCCEED, ha_status_old;
	time_t		now;
	unsigned char	*ptr;
	zbx_uint32_t	len;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	now = time(NULL);

	if (ZBX_HA_RTC_STATE_RESET == state)
	{
		last_hb = now;
		goto out;
	}

	if (NULL != message)
	{
		switch (message->code)
		{
			case ZBX_IPC_SERVICE_HA_STATUS_UPDATE:
				ha_status_old = *ha_status;

				ptr = message->data;
				ptr += zbx_deserialize_value(ptr, ha_status);
				ptr += zbx_deserialize_value(ptr, ha_failover_delay);
				(void)zbx_deserialize_str(ptr, error, len);

				if (ZBX_NODE_STATUS_ERROR == *ha_status)
				{
					ret = FAIL;
					goto out;
				}

				/* reset heartbeat on status change */
				if (ha_status_old != *ha_status)
					last_hb = now;
				break;
			case ZBX_IPC_SERVICE_HA_HEARTBEAT:
				last_hb = now;
				break;
		}
	}

	if (is_ha_cluster(ha_node_name) && 0 != last_hb)
	{
		if (last_hb + *ha_failover_delay - ZBX_HA_POLL_PERIOD <= now || now < last_hb)
		{
			last_hb = 0;

			if (ZBX_NODE_STATUS_ACTIVE == *ha_status)
				*ha_status = ZBX_NODE_STATUS_STANDBY;
			else
				*ha_status = ZBX_NODE_STATUS_HATIMEOUT;
		}
	}
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: start HA manager                                                  *
 *                                                                            *
 ******************************************************************************/
int	zbx_ha_start(zbx_rtc_t *rtc, zbx_ha_config_t *ha_config, char **error)
{
#define ZBX_HA_PROCESS_NUM	1
	int			ret = FAIL, status;
	zbx_uint32_t		code = 0;
	zbx_thread_args_t	args;
	zbx_ipc_client_t	*client;
	zbx_ipc_message_t	*message;
	zbx_timespec_t		rtc_timeout = {1, 0};
	time_t			now, start;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	args.args = (void *)ha_config;
	args.info.process_type = ZBX_PROCESS_TYPE_HA_MANAGER;
	args.info.process_num = ZBX_HA_PROCESS_NUM;
	zbx_thread_start(ha_manager_thread, &args, &ha_pid);

	if (ZBX_THREAD_ERROR == ha_pid)
	{
		*error = zbx_dsprintf(NULL, "cannot create HA manager process: %s", zbx_strerror(errno));
		goto out;
	}

	start = now = time(NULL);

	/* Add few seconds to allow HA manager to terminate by its own in the case of RTC timeout. */
	/* Otherwise it will get killed before logging timeout error.                              */
	while (start + ZBX_HA_SERVICE_TIMEOUT + 5 > now)
	{
		(void)zbx_ipc_service_recv(&rtc->service, &rtc_timeout, &client, &message);

		if (NULL != client)
			zbx_ipc_client_release(client);

		if (NULL != message)
		{
			code = message->code;
			zbx_ipc_message_free(message);

			if (ZBX_IPC_SERVICE_HA_REGISTER == code)
				break;
		}

		if (0 < waitpid(ha_pid, &status, WNOHANG))
		{
			ha_pid = ZBX_THREAD_ERROR;
			*error = zbx_strdup(NULL, "HA manager has stopped during startup registration");
			goto out;
		}

		now = time(NULL);
	}

	if (ZBX_IPC_SERVICE_HA_REGISTER != code)
	{
		*error = zbx_strdup(NULL, "timeout while waiting for HA manager registration");
		goto out;
	}

	ret = SUCCEED;
out:
	if (SUCCEED != ret)
	{
#ifdef HAVE_PTHREAD_PROCESS_SHARED
		zbx_locks_disable();
#endif
		zbx_ha_kill();
	}

	zbx_free(ha_config);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
#undef ZBX_HA_PROCESS_NUM
}

/******************************************************************************
 *                                                                            *
 * Purpose: pause HA manager                                                  *
 *                                                                            *
 * Comments: HA manager must be paused before stopping it normally            *
 *                                                                            *
 ******************************************************************************/
int	zbx_ha_pause(char **error)
{
	int		ret;
	unsigned char	*result = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	ret = zbx_ipc_async_exchange(ZBX_IPC_SERVICE_HA, ZBX_IPC_SERVICE_HA_PAUSE, ZBX_HA_SERVICE_TIMEOUT, NULL, 0,
			&result, error);
	zbx_free(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: stop  HA manager                                                  *
 *                                                                            *
 * Comments: This function is used to stop HA manager on normal shutdown      *
 *                                                                            *
 ******************************************************************************/
int	zbx_ha_stop(char **error)
{
	int		ret = FAIL;
	unsigned char	*result = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (ZBX_THREAD_ERROR == ha_pid || 0 != kill(ha_pid, 0))
	{
		ret = SUCCEED;
		goto out;
	}

	if (SUCCEED == zbx_ipc_async_exchange(ZBX_IPC_SERVICE_HA, ZBX_IPC_SERVICE_HA_STOP, ZBX_HA_SERVICE_TIMEOUT,
			NULL, 0, &result, error))
	{
		zbx_free(result);

		if (ZBX_THREAD_ERROR == zbx_thread_wait(ha_pid))
		{
			*error = zbx_dsprintf(NULL, "failed to wait for HA manager to exit: %s", zbx_strerror(errno));
			goto out;
		}

		ret = SUCCEED;
	}
out:
	if (SUCCEED == ret)
		ha_pid = ZBX_THREAD_ERROR;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: kill HA manager                                                   *
 *                                                                            *
 ******************************************************************************/
void	zbx_ha_kill(void)
{
	if (ZBX_THREAD_ERROR != ha_pid)
	{
		kill(ha_pid, SIGKILL);
		zbx_thread_wait(ha_pid);
		ha_pid = ZBX_THREAD_ERROR;
	}
}

/*
 * main process loop
 */
ZBX_THREAD_ENTRY(ha_manager_thread, args)
{
	zbx_ipc_service_t	service;
	char			*error = NULL;
	zbx_ipc_client_t	*client;
	zbx_ipc_async_socket_t	rtc_socket;
	zbx_ipc_message_t	*message;
	int			pause = FAIL, stop = FAIL, ticks_num = 0, nextcheck;
	double			now, tick;
	zbx_ha_info_t		info;
	zbx_timespec_t		timeout;
	zbx_ha_config_t		ha_config;
	const zbx_thread_info_t	*thread_info = &((zbx_thread_args_t *)args)->info;

	zbx_setproctitle("ha manager");

	zabbix_log(LOG_LEVEL_INFORMATION, "starting HA manager");

	ha_config = *(zbx_ha_config_t *)((zbx_thread_args_t *)args)->args;

	if (FAIL == zbx_ipc_service_start(&service, ZBX_IPC_SERVICE_HA, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot start HA manager service: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	if (FAIL == rtc_open(&rtc_socket, ZBX_HA_SERVICE_TIMEOUT, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot start HA manager service: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	if (FAIL == zbx_ipc_async_socket_send(&rtc_socket, ZBX_IPC_SERVICE_HA_REGISTER, NULL, 0) ||
			FAIL == zbx_ipc_async_socket_flush(&rtc_socket, ZBX_HA_SERVICE_TIMEOUT))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot register HA manager to runtime control service");
		exit(EXIT_FAILURE);
	}

	zbx_cuid_clear(info.ha_nodeid);
	info.name = ZBX_NULL2EMPTY_STR(ha_config.ha_node_name);
	info.ha_status = ha_config.ha_status;
	info.error = NULL;
	info.db_status = ZBX_DB_DOWN;
	info.offline_ticks_active = 0;
	info.lastaccess_active = 0;
	info.failover_delay = ZBX_HA_DEFAULT_FAILOVER_DELAY;
	info.auditlog_enabled = 0;
	info.auditlog_mode = 0;

	info.dbconn = zbx_dbconn_create();
	zbx_dbconn_set_connect_options(info.dbconn, ZBX_DB_CONNECT_ONCE);

	tick = zbx_time();

	if (ZBX_NODE_STATUS_UNKNOWN == info.ha_status)
	{
		ha_db_register_node(&info, &ha_config);

		if (ZBX_NODE_STATUS_ERROR == info.ha_status)
			goto pause;
	}

	nextcheck = ZBX_HA_POLL_PERIOD;

	/* triple the initial database check delay in standby mode to avoid the same node becoming active */
	/* immediately after switching to standby mode or crashing and being restarted                    */
	if (ZBX_NODE_STATUS_STANDBY == info.ha_status)
		nextcheck *= 3;

	zabbix_log(LOG_LEVEL_INFORMATION, "HA manager started in %s mode", zbx_ha_status_str(info.ha_status));

	zbx_update_selfmon_counter(thread_info, ZBX_PROCESS_STATE_BUSY);

	while (SUCCEED != pause && ZBX_NODE_STATUS_ERROR != info.ha_status)
	{
		if (tick <= (now = zbx_time()))
		{
			ticks_num++;

			if (nextcheck <= ticks_num)
			{
				int	old_status = info.ha_status, delay;

				if (ZBX_NODE_STATUS_UNKNOWN == info.ha_status)
					ha_db_register_node(&info, &ha_config);
				else
					ha_check_nodes(&info, &ha_config);

				if (old_status != info.ha_status && ZBX_NODE_STATUS_UNKNOWN != info.ha_status)
					ha_update_parent(&rtc_socket, &info);

				if (ZBX_NODE_STATUS_ERROR == info.ha_status)
					break;

				/* in offline mode try connecting to database every second otherwise */
				/* with small failover delay (10s) it might switch to standby mode   */
				/* despite connection being restored shortly                         */
				delay = ZBX_DB_OK <= info.db_status ? ZBX_HA_POLL_PERIOD : 1;

				while (nextcheck <= ticks_num)
					nextcheck += delay;
			}

			if (ZBX_DB_OK <= info.db_status || ZBX_NODE_STATUS_ACTIVE != info.ha_status)
				ha_send_heartbeat(&rtc_socket);

			while (tick <= now)
				tick++;
		}

		timeout.sec = (int)(tick - now);
		timeout.ns = (int)((tick - now) * 1000000000) % 1000000000;

		zbx_update_selfmon_counter(thread_info, ZBX_PROCESS_STATE_IDLE);
		(void)zbx_ipc_service_recv(&service, &timeout, &client, &message);
		zbx_update_selfmon_counter(thread_info, ZBX_PROCESS_STATE_BUSY);

		if (NULL != message)
		{
			switch (message->code)
			{
				case ZBX_IPC_SERVICE_HA_STATUS:
					ha_send_status(&info, client);
					break;
				case ZBX_IPC_SERVICE_HA_STOP:
					zbx_ipc_client_send(client, ZBX_IPC_SERVICE_HA_STOP, NULL, 0);
					pause = stop = SUCCEED;
					break;
				case ZBX_IPC_SERVICE_HA_PAUSE:
					zbx_ipc_client_send(client, ZBX_IPC_SERVICE_HA_PAUSE, NULL, 0);
					pause = SUCCEED;
					break;
				case ZBX_IPC_SERVICE_HA_GET_NODES:
					ha_send_node_list(&info, client, &ha_config);
					break;
				case ZBX_IPC_SERVICE_HA_REMOVE_NODE:
					ha_remove_node(&info, client, message);
					break;
				case ZBX_IPC_SERVICE_HA_SET_FAILOVER_DELAY:
					ha_set_failover_delay(&info, client, message);
					ha_update_parent(&rtc_socket, &info);
					break;
				case ZBX_IPC_SERVICE_HA_GET_FAILOVER_DELAY:
					ha_get_failover_delay(&info, client);
					break;
				case ZBX_IPC_SERVICE_HA_LOGLEVEL_INCREASE:
					zabbix_increase_log_level();
					zabbix_report_log_level_change();
					zbx_ipc_client_send(client, ZBX_IPC_SERVICE_HA_LOGLEVEL_INCREASE, NULL, 0);
					break;
				case ZBX_IPC_SERVICE_HA_LOGLEVEL_DECREASE:
					zabbix_decrease_log_level();
					zabbix_report_log_level_change();
					zbx_ipc_client_send(client, ZBX_IPC_SERVICE_HA_LOGLEVEL_DECREASE, NULL, 0);
					break;
			}

			zbx_ipc_message_free(message);
		}

		if (NULL != client)
			zbx_ipc_client_release(client);
	}

	zabbix_log(LOG_LEVEL_INFORMATION, "HA manager has been paused");
pause:
	timeout.sec = ZBX_HA_POLL_PERIOD;
	timeout.ns = 0;

	while (SUCCEED != stop)
	{
		zbx_update_selfmon_counter(thread_info, ZBX_PROCESS_STATE_IDLE);
		(void)zbx_ipc_service_recv(&service, &timeout, &client, &message);
		zbx_update_selfmon_counter(thread_info, ZBX_PROCESS_STATE_BUSY);

		if (ZBX_NODE_STATUS_STANDBY == info.ha_status || ZBX_NODE_STATUS_ACTIVE == info.ha_status)
			ha_db_update_lastaccess(&info);

		if (NULL != message)
		{
			switch (message->code)
			{
				case ZBX_IPC_SERVICE_HA_STATUS:
					ha_send_status(&info, client);
					break;
				case ZBX_IPC_SERVICE_HA_STOP:
					zbx_ipc_client_send(client, ZBX_IPC_SERVICE_HA_STOP, NULL, 0);
					stop = SUCCEED;
					break;
				case ZBX_IPC_SERVICE_HA_PAUSE:
					zbx_ipc_client_send(client, ZBX_IPC_SERVICE_HA_PAUSE, NULL, 0);
					break;
			}

			zbx_ipc_message_free(message);
		}

		if (NULL != client)
			zbx_ipc_client_release(client);
	}

	zbx_free(info.error);

	ha_db_update_exit_status(&info);

	zbx_dbconn_free(info.dbconn);

	zbx_ipc_async_socket_close(&rtc_socket);
	zbx_ipc_service_close(&service);

	zabbix_log(LOG_LEVEL_INFORMATION, "HA manager has been stopped");

	exit(EXIT_SUCCESS);
}
