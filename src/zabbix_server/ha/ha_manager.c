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
#include "ha.h"
#include "threads.h"

#define ZBX_HA_POLL_PERIOD	5

// TODO: use more realistic timeout after testing
#define ZBX_HA_SERVICE_TIMEOUT	1

static pid_t			ha_pid;
static zbx_ipc_async_socket_t	ha_socket;

ZBX_THREAD_ENTRY(ha_manager_thread, args);

/******************************************************************************
 *                                                                            *
 * Function: ha_check_nodes                                                   *
 *                                                                            *
 * Purpose: check HA status based on nodes                                    *
 *                                                                            *
 ******************************************************************************/
static int	ha_check_nodes(int *ha_status, char **error)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	ZBX_UNUSED(error);

	// TODO: implement HA status check
	zabbix_log(LOG_LEVEL_DEBUG, "checking nodes (not implemented)");

	*ha_status = ZBX_NODE_STATUS_UNKNOWN;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() status:%d", __func__, *ha_status);

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
static void	ha_notify_parent(zbx_ipc_client_t *client, int status)
{
	zbx_uint32_t	ha_codes[] = {ZBX_IPC_SERVICE_HA_STANDBY, 0, 0, ZBX_IPC_SERVICE_HA_ACTIVE, 0};

	if (0 > status || status >= (int)ARRSIZE(ha_codes))
	{
		zabbix_log(LOG_LEVEL_CRIT, "invalid status: %d", status);
		THIS_SHOULD_NEVER_HAPPEN;
		exit(EXIT_FAILURE);
	}

	if (SUCCEED != zbx_ipc_client_send(client, ha_codes[status], NULL, 0))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot send HA notification to main process");
		exit(EXIT_FAILURE);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: ha_recv_status                                                   *
 *                                                                            *
 * Purpose: receive status message from HA service                            *
 *                                                                            *
 ******************************************************************************/
static int	ha_recv_status(int *status, char **error)
{
	zbx_ipc_message_t	*message = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (SUCCEED != zbx_ipc_async_socket_recv(&ha_socket, 1, &message))
	{
		*error = zbx_strdup(NULL, "cannot receive message from HA manager service");
		return FAIL;
	}

	if (NULL != message)
	{
		switch (message->code)
		{
			case ZBX_IPC_SERVICE_HA_STANDBY:
				*status = ZBX_NODE_STATUS_STANDBY;
				break;
			case ZBX_IPC_SERVICE_HA_ACTIVE:
				*status = ZBX_NODE_STATUS_ACTIVE;
				break;
			default:
				*status = ZBX_NODE_STATUS_UNKNOWN;
		}
	}
	else
		*status = ZBX_NODE_STATUS_UNKNOWN;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() status:%d", __func__, *status);

	return SUCCEED;
}

/*
 * public API
 */

/******************************************************************************
 *                                                                            *
 * Function: zbx_ha_recv_status                                               *
 *                                                                            *
 * Purpose: receive status message from HA service                            *
 *                                                                            *
 ******************************************************************************/
int	zbx_ha_recv_status(int *status, char **error)
{
	return ha_recv_status(status, error);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_ha_get_status                                                *
 *                                                                            *
 * Purpose: get status from HA service                                        *
 *                                                                            *
 ******************************************************************************/
int	zbx_ha_get_status(int *status, char **error)
{
	if (FAIL == zbx_ipc_async_socket_send(&ha_socket, ZBX_IPC_SERVICE_HA_STATUS, NULL, 0))
	{
		*error = zbx_strdup(NULL, "cannot queue message to HA manager service");
		return FAIL;
	}

	if (FAIL == zbx_ipc_async_socket_flush(&ha_socket, ZBX_HA_SERVICE_TIMEOUT))
	{
		*error = zbx_strdup(NULL, "cannot send message to HA manager service");
		return FAIL;
	}

	return ha_recv_status(status, error);
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
	int			stop = FAIL, ha_status;
	double			lastcheck, now, nextcheck, timeout;

	ha_status = (int)(uintptr_t)((zbx_thread_args_t *)args)->args;

	zbx_setproctitle("ha manager");

	zabbix_log(LOG_LEVEL_INFORMATION, "starting HA manager");

	if (FAIL == zbx_ipc_service_start(&service, ZBX_IPC_SERVICE_HA, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot start HA manager service: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	DBconnect(ZBX_DB_CONNECT_ONCE);

	lastcheck = zbx_time();

	if (ZBX_NODE_STATUS_UNKNOWN == ha_status)
	{
		// TODO: get ha status from node table if not forced by start
		ha_status = ZBX_NODE_STATUS_ACTIVE;

		nextcheck = lastcheck + ZBX_HA_POLL_PERIOD;
	}
	else
		nextcheck = lastcheck + SEC_PER_MIN;

	zabbix_log(LOG_LEVEL_INFORMATION, "HA manager started in %s mode", zbx_ha_status_str(ha_status));

	while (SUCCEED != stop)
	{
		now = zbx_time();

		if (nextcheck <= now)
		{
			int	status;

			if (SUCCEED != ha_check_nodes(&status, &error))
			{
				zabbix_log(LOG_LEVEL_CRIT, "cannot check HA status: %s", error);
				zbx_free(error);
				exit(EXIT_FAILURE);
			}

			if (status != ha_status && ZBX_NODE_STATUS_UNKNOWN != status)
			{
				ha_status = status;
				if (NULL != main_proc)
					ha_notify_parent(main_proc, status);
			}

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
				case ZBX_IPC_SERVICE_HA_PAUSE:
					stop = SUCCEED;
					break;
				case ZBX_IPC_SERVICE_HA_REPORT:
					ha_report_status();
					break;
				case ZBX_IPC_SERVICE_HA_STATUS:
					ha_notify_parent(main_proc, ha_status);
					break;
				case ZBX_IPC_SERVICE_HA_STANDBY: // TODO: debug command, remove
					ha_status = ZBX_NODE_STATUS_STANDBY;
					ha_notify_parent(main_proc, ha_status);
					break;
				case ZBX_IPC_SERVICE_HA_ACTIVE: // TODO: debug command, remove
					ha_status = ZBX_NODE_STATUS_ACTIVE;
					ha_notify_parent(main_proc, ha_status);
					break;
			}

			zbx_ipc_message_free(message);
		}
	}

	zabbix_log(LOG_LEVEL_INFORMATION, "paused HA manager");

	stop = FAIL;

	while (SUCCEED != stop)
	{
		(void)zbx_ipc_service_recv(&service, ZBX_IPC_WAIT_FOREVER, &client, &message);

		if (NULL != message)
		{
			switch (message->code)
			{
				case ZBX_IPC_SERVICE_HA_STOP:
					stop = SUCCEED;
					break;
			}

			zbx_ipc_message_free(message);
		}
	}


	// TODO: update node status to stopped

	DBclose();

	zbx_ipc_service_close(&service);

	zabbix_log(LOG_LEVEL_INFORMATION, "stopped HA manager");


	exit(EXIT_SUCCESS);

	return 0;
}
