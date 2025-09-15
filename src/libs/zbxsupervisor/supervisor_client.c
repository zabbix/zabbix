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

#include "zbxsupervisor_client.h"
#include "zbxlog.h"
#include "zbxserialize.h"
#include "zbxipcservice.h"

#define SUPERVISOR_TIMEOUT        5

/******************************************************************************
 *                                                                            *
 * Purpose: notify supervisor service that process is running                 *
 *                                                                            *
 * Parameters: proc_index - [IN] process index (server_num commonly)          *
 *                                                                            *
 ******************************************************************************/
void	zbx_supervisor_set_process_running(int proc_index)
{
	zbx_ipc_socket_t	sock;
	char			*error = NULL;

	unsigned char data[sizeof(proc_index)];

	if (FAIL == zbx_ipc_socket_open(&sock, ZBX_IPC_SERVICE_SUPERVISOR, SUPERVISOR_TIMEOUT, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "Cannot connect to supervisor service: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	(void)zbx_serialize_int(data, proc_index);
	if (FAIL == zbx_ipc_socket_write(&sock, ZBX_SUPERVISOR_PROC_RUNNING, data, sizeof(data)))
	{
		zabbix_log(LOG_LEVEL_CRIT, "Cannot send message to supervisor service: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	zbx_ipc_socket_close(&sock);
}

/******************************************************************************
 *                                                                            *
 * Purpose: wait for supervisor service to reach specified runlevel           *
 *                                                                            *
 * Parameters: runlevel - [IN] runlevel to wait for                           *
 *                                                                            *
 ******************************************************************************/
void	zbx_supervisor_wait_for_runlevel(int runlevel)
{
	zbx_ipc_socket_t	sock;
	char			*error = NULL;
	zbx_ipc_message_t	message;
	unsigned char		data[sizeof(runlevel)];

	/* 0 is starting runlevel, no need to wait for it */
	if (0 == runlevel)
		return;

	if (FAIL == zbx_ipc_socket_open(&sock, ZBX_IPC_SERVICE_SUPERVISOR, SUPERVISOR_TIMEOUT, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "Cannot connect to supervisor service: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	(void)zbx_serialize_int(data, runlevel);
	if (FAIL == zbx_ipc_socket_write(&sock, ZBX_SUPERVISOR_RUNLEVEL_WAIT, data, sizeof(data)))
	{
		zabbix_log(LOG_LEVEL_CRIT, "Cannot send message to supervisor service: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	if (FAIL == zbx_ipc_socket_read(&sock, &message))
	{
		zabbix_log(LOG_LEVEL_CRIT, "Cannot read response from supervisor service: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	zbx_ipc_socket_close(&sock);

}

/******************************************************************************
 *                                                                            *
 * Purpose: get process owner and runlevel information for specified process  *
 *          type                                                              *
 *                                                                            *
 * Parameters: process_type - [IN]  process type                              *
 *             owner        - [OUT] process owner                             *
 *             runlevel     - [OUT] process runlevel                          *
 *                                                                            *
 ******************************************************************************/
void	zbx_supervisor_get_process_info(int process_type, zbx_proc_owner_t *owner, int *runlevel)
{
	*owner = PROCESS_OWNER_MAIN;
	*runlevel = ZBX_RUNLEVEL_MAX;

	switch (process_type)
	{
		case ZBX_PROCESS_TYPE_POLLER:
			break;

		case ZBX_PROCESS_TYPE_UNREACHABLE:
			break;

		case ZBX_PROCESS_TYPE_IPMIPOLLER:
			break;

		case ZBX_PROCESS_TYPE_PINGER:
			break;

		case ZBX_PROCESS_TYPE_JAVAPOLLER:
			break;

		case ZBX_PROCESS_TYPE_HTTPPOLLER:
			break;

		case ZBX_PROCESS_TYPE_TRAPPER:
			break;

		case ZBX_PROCESS_TYPE_SNMPTRAPPER:
			break;

		case ZBX_PROCESS_TYPE_PROXYPOLLER:
			break;

		case ZBX_PROCESS_TYPE_ESCALATOR:
			break;

		case ZBX_PROCESS_TYPE_HISTSYNCER:
			break;

		case ZBX_PROCESS_TYPE_DISCOVERER:
			*owner = PROCESS_OWNER_UNKNOWN;
			*runlevel = ZBX_RUNLEVEL_UNKNOWN;
			break;

		case ZBX_PROCESS_TYPE_ALERTER:
			break;

		case ZBX_PROCESS_TYPE_TIMER:
			break;

		case ZBX_PROCESS_TYPE_HOUSEKEEPER:
			break;

		case ZBX_PROCESS_TYPE_DATASENDER:
			break;

		case ZBX_PROCESS_TYPE_CONFSYNCER:
			*runlevel = ZBX_RUNLEVEL_CACHESYNC;
			break;

		case ZBX_PROCESS_TYPE_SELFMON:
			break;

		case ZBX_PROCESS_TYPE_VMWARE:
			break;

		case ZBX_PROCESS_TYPE_COLLECTOR:
			break;

		case ZBX_PROCESS_TYPE_LISTENER:
			break;

		case ZBX_PROCESS_TYPE_ACTIVE_CHECKS:
			break;

		case ZBX_PROCESS_TYPE_TASKMANAGER:
			*runlevel = ZBX_RUNLEVEL_TASKMANAGER;
			break;

		case ZBX_PROCESS_TYPE_IPMIMANAGER:
			break;

		case ZBX_PROCESS_TYPE_ALERTMANAGER:
			break;

		case ZBX_PROCESS_TYPE_PREPROCMAN:
			break;

		case ZBX_PROCESS_TYPE_PREPROCESSOR:
			*owner = PROCESS_OWNER_UNKNOWN;
			*runlevel = ZBX_RUNLEVEL_UNKNOWN;
			break;

		case ZBX_PROCESS_TYPE_LLDMANAGER:
			break;

		case ZBX_PROCESS_TYPE_LLDWORKER:
			break;

		case ZBX_PROCESS_TYPE_ALERTSYNCER:
			break;

		case ZBX_PROCESS_TYPE_HISTORYPOLLER:
			break;

		case ZBX_PROCESS_TYPE_AVAILMAN:
			break;

		case ZBX_PROCESS_TYPE_REPORTMANAGER:
			break;

		case ZBX_PROCESS_TYPE_REPORTWRITER:
			break;

		case ZBX_PROCESS_TYPE_SERVICEMAN:
			*runlevel = ZBX_RUNLEVEL_CACHESYNC;
			break;

		case ZBX_PROCESS_TYPE_TRIGGERHOUSEKEEPER:
			break;

		case ZBX_PROCESS_TYPE_ODBCPOLLER:
			break;

		case ZBX_PROCESS_TYPE_CONNECTORMANAGER:
			break;

		case ZBX_PROCESS_TYPE_CONNECTORWORKER:
			break;

		case ZBX_PROCESS_TYPE_DISCOVERYMANAGER:
			break;

		case ZBX_PROCESS_TYPE_HTTPAGENT_POLLER:
			break;

		case ZBX_PROCESS_TYPE_AGENT_POLLER:
			break;

		case ZBX_PROCESS_TYPE_SNMP_POLLER:
			break;

		case ZBX_PROCESS_TYPE_INTERNAL_POLLER:
			break;

		case ZBX_PROCESS_TYPE_DBCONFIGWORKER:
			break;

		case ZBX_PROCESS_TYPE_PG_MANAGER:
			break;

		case ZBX_PROCESS_TYPE_BROWSERPOLLER:
			break;

		case ZBX_PROCESS_TYPE_HA_MANAGER:
			*owner = PROCESS_OWNER_UNKNOWN;
			*runlevel = ZBX_RUNLEVEL_UNKNOWN;
			break;

		case ZBX_PROCESS_TYPE_SUPERVISOR:
			*runlevel = ZBX_RUNLEVEL_SUPERVISOR;
			break;

		default:
			THIS_SHOULD_NEVER_HAPPEN_MSG("Unknow process type: %d", process_type);
			exit(EXIT_FAILURE);
			break;
		}

}

/******************************************************************************
 *                                                                            *
 * Purpose: calculate total count of processes with direct ownership          *
 *                                                                            *
 * Parameters: config_forks - [IN] array of configured process counts         *
 *                                                                            *
 * Return value: total process count                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_supervisor_get_process_count(const int *config_forks)
{
	int	process_count = 0;

	for (int i = 0; i < ZBX_PROCESS_TYPE_COUNT; i++)
	{
		int			runlevel;
		zbx_proc_owner_t	owner;

		zbx_supervisor_get_process_info(i, &owner, &runlevel);

		if (PROCESS_OWNER_UNKNOWN != owner)
			process_count += config_forks[i];
	}

	return process_count;
}
