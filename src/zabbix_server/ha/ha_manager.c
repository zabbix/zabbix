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
	ZBX_UNUSED(error);

	// TODO: implement HA status check
	zabbix_log(LOG_LEVEL_DEBUG, "checking nodes (not implemented)");

	*ha_status = ZBX_NODE_STATUS_STANDBY;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: ha_report_status                                                 *
 *                                                                            *
 * Purpose: report cluster status in log file                                 *
 *                                                                            *
 ******************************************************************************/
static void	ha_report_status()
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
int	zbx_ha_start(char **error)
{
	char	*errmsg = NULL;

	zbx_thread_start(ha_manager_thread, NULL, &ha_pid);

	if (ZBX_THREAD_ERROR == ha_pid)
	{
		*error = zbx_dsprintf(NULL, "cannot create HA manager process: %s", zbx_strerror(errno));
		return FAIL;
	}

	if (SUCCEED != zbx_ipc_async_socket_open(&ha_socket, ZBX_IPC_SERVICE_HA, ZBX_HA_SERVICE_TIMEOUT, &errmsg))
	{
		*error = zbx_dsprintf(NULL, "cannot connect to HA manager process: %s", errmsg);
		zbx_free(errmsg);
		return FAIL;
	}

	if (FAIL == zbx_ipc_async_socket_send(&ha_socket, ZBX_IPC_SERVICE_HA_REGISTER, NULL, 0))
	{
		*error = zbx_dsprintf(NULL, "cannot queue message to HA manager service");
		zbx_free(errmsg);
		return FAIL;
	}

	if (FAIL == zbx_ipc_async_socket_flush(&ha_socket, ZBX_HA_SERVICE_TIMEOUT))
	{
		*error = zbx_dsprintf(NULL, "cannot send message to HA manager service");
		zbx_free(errmsg);
		return FAIL;
	}

	return SUCCEED;
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
	return ha_send_manager_message(ZBX_IPC_SERVICE_HA_PAUSE, error);
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
	int	status;

	if (SUCCEED == ha_send_manager_message(ZBX_IPC_SERVICE_HA_STOP, error))
	{
		while (-1 == waitpid(ha_pid, &status, 0))
		{
			if (EINTR == errno)
				continue;

			*error = zbx_dsprintf(NULL, "failed to wait for HA manager to exit: %s", zbx_strerror(errno));
			return FAIL;
		}
		return SUCCEED;
	}

	*error = zbx_dsprintf(NULL, "cannot create HA manager process: %s", zbx_strerror(errno));

	return FAIL;
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
	int			stop = FAIL, ha_status = ZBX_NODE_STATUS_UNKNOWN;
	double			lastcheck, now, nextcheck, timeout;

	ZBX_UNUSED(args);

	zbx_setproctitle("ha manager");

	zabbix_log(LOG_LEVEL_INFORMATION, "HA manager started");

	if (FAIL == zbx_ipc_service_start(&service, ZBX_IPC_SERVICE_HA, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot start HA manager service: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	DBconnect(ZBX_DB_CONNECT_ONCE);

	// TODO: get ha status from node table
	ha_status = ZBX_NODE_STATUS_ACTIVE;

	lastcheck = zbx_time();
	nextcheck = lastcheck + ZBX_HA_POLL_PERIOD;

	zabbix_log(LOG_LEVEL_DEBUG, "started HA monitoring loop");

	while (SUCCEED != stop)
	{
		now = zbx_time();

		if (nextcheck <= now)
		{
			int	status;

			if (SUCCEED != ha_check_nodes(&status, &error))
			{
				zabbix_log(LOG_LEVEL_CRIT, "cannot check HA statuse: %s", error);
				zbx_free(error);
				exit(EXIT_FAILURE);
			}

			if (status != ha_status)
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
					ha_report_status(&error);
					break;
				case ZBX_IPC_SERVICE_HA_STATUS:
					// TODO: return real HA status
					ha_notify_parent(main_proc, ZBX_IPC_SERVICE_HA_ACTIVE);
					break;
				case ZBX_IPC_SERVICE_HA_STANDBY: // TODO: debug command, remove
					ha_notify_parent(main_proc, ZBX_IPC_SERVICE_HA_STANDBY);
					break;
				case ZBX_IPC_SERVICE_HA_ACTIVE: // TODO: debug command, remove
					ha_notify_parent(main_proc, ZBX_IPC_SERVICE_HA_ACTIVE);
					break;
			}

			zbx_ipc_message_free(message);
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "paused HA monitoring loop");

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

	zabbix_log(LOG_LEVEL_DEBUG, "stopped HA monitoring loop");

	// TODO: update node status to stopped

	DBclose();

	zbx_ipc_service_close(&service);

	zabbix_log(LOG_LEVEL_INFORMATION, "HA manager stopped");

	exit(EXIT_SUCCESS);

	return 0;
}
