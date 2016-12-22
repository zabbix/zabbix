/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
#include "dbcache.h"
#include "daemon.h"
#include "zbxself.h"
#include "log.h"
#include "zbxipcservice.h"

#include "ipmi_manager.h"
#include "ipmi_protocol.h"

#define ZBX_IPMI_MANAGER_DELAY	1

extern unsigned char	process_type, program_type;
extern int		server_num, process_num;

/******************************************************************************
 *                                                                            *
 * Function: ipmi_poller_register                                             *
 *                                                                            *
 * Purpose: registers IPMI poller with IPMI manager                           *
 *                                                                            *
 * Parameters: socket - [IN] the connections socket                           *
 *                                                                            *
 ******************************************************************************/
static void	ipmi_poller_register(zbx_ipc_socket_t *socket)
{
	zbx_ipc_socket_write(socket, ZBX_IPC_IPMI_REGISTER, NULL, 0);
}

/******************************************************************************
 *                                                                            *
 * Function: ipmi_poller_send_result                                          *
 *                                                                            *
 * Purpose: sends IPMI poll result to manager                                 *
 *                                                                            *
 * Parameters: socket  - [IN] the connections socket                          *
 *             itemid  - [IN] the item identifier                             *
 *             ts      - [IN] the poll timestamp                              *
 *             errcode - [IN] the result error code                           *
 *             value   - [IN] the resulting value/error message               *
 *                                                                            *
 ******************************************************************************/
static void	ipmi_poller_send_result(zbx_ipc_socket_t *socket, zbx_uint64_t itemid, const zbx_timespec_t *ts,
		int errcode, const char *value)
{
	unsigned char	*data;
	zbx_uint32_t	data_len;

	data_len = zbx_ipmi_serialize_value_response(&data, itemid, ts, errcode, value);
	zbx_ipc_socket_write(socket, ZBX_IPC_IPMI_RESULT, data, data_len);

	zbx_free(data);
}


/* TODO: replace with real IPMI device polling */
static int	ipmi_poller_read_item(zbx_uint64_t itemid, char *value, size_t value_size)
{
	char	buffer[MAX_STRING_LEN], *pend;
	FILE	*fp;
	int	ret;

	zbx_snprintf(buffer, sizeof(buffer), "/tmp/zabbix/items/" ZBX_FS_UI64, itemid);

	if (NULL == (fp = fopen(buffer, "r")))
	{
		zbx_strlcpy(value, "0", 2);
		return SUCCEED;
	}

	fgets(buffer, sizeof(buffer), fp);

	switch (*buffer)
	{
		case 'S':
			ret = SUCCEED;
			break;
		case 'A':
			ret = AGENT_ERROR;
			break;
		case 'G':
			ret = GATEWAY_ERROR;
			break;
		case 'T':
			ret = TIMEOUT_ERROR;
			break;
		case 'N':
			ret = ('O' == buffer[1] ? NOTSUPPORTED : NETWORK_ERROR);
			break;
		default:
			ret = CONFIG_ERROR;

	}

	fgets(value, value_size, fp);
	pend = value + strlen(value) - 1;
	if ('\n' == *pend)
		*pend = '\0';

	fclose(fp);

	return ret;
}

static void	ipmi_poller_process_request(zbx_ipc_socket_t *socket, zbx_ipc_message_t *message)
{
	const char	*__function_name = "ipmi_poller_process_request";
	zbx_uint64_t	itemid;
	char		*addr, *username, *password, *sensor, value[MAX_STRING_LEN];
	signed char	authtype;
	unsigned char	privilege;
	unsigned short	port;
	zbx_timespec_t	ts;
	int		errcode;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_ipmi_deserialize_value_request(message->data, &itemid, &addr, &port, &authtype,
			&privilege, &username, &password, &sensor);

	zabbix_log(LOG_LEVEL_TRACE, "%s() itemid:" ZBX_FS_UI64 " addr:%s port:%d authtype:%d privilege:%d username:%s"
			" sensor:%s", __function_name, itemid, addr, (int)port, (int)authtype, (int)privilege,
			username, sensor);

	zbx_timespec(&ts);

	errcode = ipmi_poller_read_item(itemid, value, sizeof(value));

	ipmi_poller_send_result(socket, itemid, &ts, errcode, value);

	zbx_free(addr);
	zbx_free(username);
	zbx_free(password);
	zbx_free(sensor);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

ZBX_THREAD_ENTRY(ipmi_poller_thread, args)
{
	char			*error = NULL;
	zbx_ipc_socket_t	ipmi_socket;
	zbx_ipc_message_t	message;

#define	STAT_INTERVAL	5	/* if a process is busy and does not sleep then update status not faster than */
				/* once in STAT_INTERVAL seconds */

	process_type = ((zbx_thread_args_t *)args)->process_type;

	server_num = ((zbx_thread_args_t *)args)->server_num;
	process_num = ((zbx_thread_args_t *)args)->process_num;

	zbx_ipc_message_init(&message);

	if (FAIL == zbx_ipc_socket_open(&ipmi_socket, ZBX_IPC_SERVICE_IPMI, 10, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot connect to IPMI service: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	zbx_setproctitle("%s #%d starting", get_process_type_string(process_type), process_num);

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(program_type),
			server_num, get_process_type_string(process_type), process_num);

	ipmi_poller_register(&ipmi_socket);

	for (;;)
	{
		if (SUCCEED != zbx_ipc_socket_read(&ipmi_socket, &message))
		{
			zabbix_log(LOG_LEVEL_CRIT, "cannot read IPMI service request");
			exit(EXIT_FAILURE);
		}

		switch (message.header[ZBX_IPC_MESSAGE_CODE])
		{
			case ZBX_IPC_IPMI_REQUEST:
				ipmi_poller_process_request(&ipmi_socket, &message);
				break;
		}

		zbx_ipc_message_clean(&message);
	}

	zbx_ipc_socket_close(&ipmi_socket);

#undef STAT_INTERVAL
}
