/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

#ifdef HAVE_OPENIPMI

#include "ipmi_poller.h"

#include "daemon.h"
#include "zbxself.h"
#include "log.h"
#include "zbxipcservice.h"
#include "ipmi_protocol.h"
#include "checks_ipmi.h"


#define ZBX_IPMI_MANAGER_CLEANUP_DELAY		SEC_PER_DAY

extern ZBX_THREAD_LOCAL unsigned char	process_type;
extern unsigned char			program_type;
extern ZBX_THREAD_LOCAL int		server_num, process_num;

/******************************************************************************
 *                                                                            *
 * Purpose: registers IPMI poller with IPMI manager                           *
 *                                                                            *
 * Parameters: socket - [IN] the connections socket                           *
 *                                                                            *
 ******************************************************************************/
static void	ipmi_poller_register(zbx_ipc_async_socket_t *socket)
{
	pid_t	ppid;

	ppid = getppid();

	zbx_ipc_async_socket_send(socket, ZBX_IPC_IPMI_REGISTER, (unsigned char *)&ppid, sizeof(ppid));
}

/******************************************************************************
 *                                                                            *
 * Purpose: sends IPMI poll result to manager                                 *
 *                                                                            *
 * Parameters: socket  - [IN] the connections socket                          *
 *             itemid  - [IN] the item identifier                             *
 *             errcode - [IN] the result error code                           *
 *             value   - [IN] the resulting value/error message               *
 *                                                                            *
 ******************************************************************************/
static void	ipmi_poller_send_result(zbx_ipc_async_socket_t *socket, zbx_uint32_t code, int errcode,
		const char *value)
{
	unsigned char	*data;
	zbx_uint32_t	data_len;
	zbx_timespec_t	ts;

	zbx_timespec(&ts);
	data_len = zbx_ipmi_serialize_result(&data, &ts, errcode, value);
	zbx_ipc_async_socket_send(socket, code, data, data_len);

	zbx_free(data);
}

/******************************************************************************
 *                                                                            *
 * Purpose: gets IPMI sensor value from the specified host                    *
 *                                                                            *
 * Parameters: socket  - [IN] the connections socket                          *
 *             message - [IN] the value request message                       *
 *                                                                            *
 ******************************************************************************/
static void	ipmi_poller_process_value_request(zbx_ipc_async_socket_t *socket, zbx_ipc_message_t *message)
{
	zbx_uint64_t	itemid;
	char		*addr, *username, *password, *sensor, *value = NULL, *key;
	signed char	authtype;
	unsigned char	privilege;
	unsigned short	port;
	int		errcode, command;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_ipmi_deserialize_request(message->data, &itemid, &addr, &port, &authtype, &privilege, &username, &password,
			&sensor, &command, &key);

	if (0 == strcmp(key, "ipmi.get") || 0 == strncmp(key, "ipmi.get[", ZBX_CONST_STRLEN("ipmi.get[")))
	{
		zabbix_log(LOG_LEVEL_TRACE, "%s() for discovery itemid:" ZBX_FS_UI64 " addr:%s port:%d authtype:%d"
				" privilege:%d username:%s", __func__, itemid, addr, (int)port, (int)authtype,
				(int)privilege,	username);
		errcode = get_discovery_ipmi(itemid, addr, port, authtype, privilege, username, password, &value);
		ipmi_poller_send_result(socket, ZBX_IPC_IPMI_VALUE_RESULT, errcode, value);
	}
	else
	{
		zabbix_log(LOG_LEVEL_TRACE, "%s() itemid:" ZBX_FS_UI64 " addr:%s port:%d authtype:%d privilege:%d"
				" username:%s sensor:%s", __func__, itemid, addr, (int)port, (int)authtype,
				(int)privilege, username, sensor);
		errcode = get_value_ipmi(itemid, addr, port, authtype, privilege, username, password, sensor, &value);
		ipmi_poller_send_result(socket, ZBX_IPC_IPMI_VALUE_RESULT, errcode, value);
	}

	zbx_free(value);
	zbx_free(addr);
	zbx_free(username);
	zbx_free(password);
	zbx_free(sensor);
	zbx_free(key);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose:sets IPMI sensor value                                             *
 *                                                                            *
 * Parameters: socket  - [IN] the connections socket                          *
 *             message - [IN] the command request message                     *
 *                                                                            *
 ******************************************************************************/
static void	ipmi_poller_process_command_request(zbx_ipc_async_socket_t *socket, zbx_ipc_message_t *message)
{
	zbx_uint64_t	itemid;
	char		*addr, *username, *password, *sensor, *error = NULL, *key;
	signed char	authtype;
	unsigned char	privilege;
	unsigned short	port;
	int		errcode, command;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_ipmi_deserialize_request(message->data, &itemid, &addr, &port, &authtype, &privilege, &username, &password,
			&sensor, &command, &key);

	zabbix_log(LOG_LEVEL_TRACE, "%s() hostid:" ZBX_FS_UI64 " addr:%s port:%d authtype:%d privilege:%d username:%s"
			" sensor:%s", __func__, itemid, addr, (int)port, (int)authtype, (int)privilege,
			username, sensor);

	errcode = zbx_set_ipmi_control_value(itemid, addr, port, authtype, privilege, username, password, sensor,
			command, &error);

	ipmi_poller_send_result(socket, ZBX_IPC_IPMI_COMMAND_RESULT, errcode, error);

	zbx_free(error);
	zbx_free(addr);
	zbx_free(username);
	zbx_free(password);
	zbx_free(sensor);
	zbx_free(key);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

ZBX_THREAD_ENTRY(ipmi_poller_thread, args)
{
	char			*error = NULL;
	zbx_ipc_async_socket_t	ipmi_socket;
	int			polled_num = 0;
	double			time_stat, time_idle = 0, time_now, time_read;

#define	STAT_INTERVAL	5	/* if a process is busy and does not sleep then update status not faster than */
				/* once in STAT_INTERVAL seconds */

	process_type = ((zbx_thread_args_t *)args)->process_type;

	server_num = ((zbx_thread_args_t *)args)->server_num;
	process_num = ((zbx_thread_args_t *)args)->process_num;

	zbx_setproctitle("%s #%d starting", get_process_type_string(process_type), process_num);

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(program_type),
			server_num, get_process_type_string(process_type), process_num);

	update_selfmon_counter(ZBX_PROCESS_STATE_BUSY);

	if (FAIL == zbx_ipc_async_socket_open(&ipmi_socket, ZBX_IPC_SERVICE_IPMI, SEC_PER_MIN, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot connect to IPMI service: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	zbx_init_ipmi_handler();

	ipmi_poller_register(&ipmi_socket);

	time_stat = zbx_time();

	zbx_setproctitle("%s #%d started", get_process_type_string(process_type), process_num);

	while (ZBX_IS_RUNNING())
	{
		zbx_ipc_message_t	*message = NULL;

		time_now = zbx_time();

		if (STAT_INTERVAL < time_now - time_stat)
		{
			zbx_setproctitle("%s #%d [polled %d values, idle " ZBX_FS_DBL " sec during "
					ZBX_FS_DBL " sec]", get_process_type_string(process_type), process_num,
					polled_num, time_idle, time_now - time_stat);

			time_stat = time_now;
			time_idle = 0;
			polled_num = 0;
		}

		update_selfmon_counter(ZBX_PROCESS_STATE_IDLE);

		while (ZBX_IS_RUNNING())
		{
			const int ipc_timeout = 2;
			const int ipmi_timeout = 1;

			if (SUCCEED != zbx_ipc_async_socket_recv(&ipmi_socket, ipc_timeout, &message))
			{
				zabbix_log(LOG_LEVEL_CRIT, "cannot read IPMI service request");
				exit(EXIT_FAILURE);
			}

			if (NULL != message)
				break;

			zbx_perform_all_openipmi_ops(ipmi_timeout);
		}

		update_selfmon_counter(ZBX_PROCESS_STATE_BUSY);

		if (NULL == message)
			break;

		time_read = zbx_time();
		time_idle += time_read - time_now;
		zbx_update_env(time_read);

		switch (message->code)
		{
			case ZBX_IPC_IPMI_VALUE_REQUEST:
				ipmi_poller_process_value_request(&ipmi_socket, message);
				polled_num++;
				break;
			case ZBX_IPC_IPMI_COMMAND_REQUEST:
				ipmi_poller_process_command_request(&ipmi_socket, message);
				break;
			case ZBX_IPC_IPMI_CLEANUP_REQUEST:
				zbx_delete_inactive_ipmi_hosts(time(NULL));
				break;
		}

		zbx_ipc_message_free(message);
		message = NULL;
	}

	zbx_setproctitle("%s #%d [terminated]", get_process_type_string(process_type), process_num);

	while (1)
		zbx_sleep(SEC_PER_MIN);

	zbx_ipc_async_socket_close(&ipmi_socket);

	zbx_free_ipmi_handler();
#undef STAT_INTERVAL
}

#endif
