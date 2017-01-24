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

#ifdef HAVE_OPENIPMI

#include "log.h"
#include "zbxserialize.h"
#include "dbcache.h"

#include "zbxipcservice.h"
#include "ipmi_protocol.h"
#include "checks_ipmi.h"

int	zbx_ipmi_execute_command(const DC_HOST *host, const char *command, char *error, size_t max_error_len)
{
	const char		*__function_name = "ipmi_manager_init";
	zbx_ipc_socket_t	ipmi_socket;
	zbx_ipc_message_t	message;
	char			*errmsg = NULL, sensor[ITEM_IPMI_SENSOR_LEN_MAX], *value = NULL;
	zbx_uint32_t		data_len;
	unsigned char		*data = NULL;
	int			ret = FAIL, op;
	DC_INTERFACE		interface;
	zbx_timespec_t		ts;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() host:\"%s\" command:%d", __function_name, host->host, command);

	if (SUCCEED != zbx_parse_ipmi_command(command, sensor, &op, error, max_error_len))
		return FAIL;

	if (FAIL == zbx_ipc_socket_open(&ipmi_socket, ZBX_IPC_SERVICE_IPMI, SEC_PER_MIN, &errmsg))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot connect to IPMI service: %s", errmsg);
		exit(EXIT_FAILURE);
	}

	zbx_ipc_message_init(&message);

	DCconfig_get_interface_by_type(&interface, host->hostid, INTERFACE_TYPE_IPMI);

	data_len = zbx_ipmi_serialize_request(&data, host->hostid, interface.addr, interface.port, host->ipmi_authtype,
			host->ipmi_privilege, host->ipmi_username, host->ipmi_password, sensor, op);

	if (FAIL == zbx_ipc_socket_write(&ipmi_socket, ZBX_IPC_IPMI_SCRIPT_REQUEST, data, data_len))
	{
		zbx_strlcpy(error, "cannot send script request message to IPMI service", max_error_len);
		goto out;
	}

	zbx_ipc_message_init(&message);

	if (FAIL == zbx_ipc_socket_read(&ipmi_socket, &message))
	{
		zbx_strlcpy(error,  "cannot read script request response from IPMI service", max_error_len);
		goto out;
	}

	if (ZBX_IPC_IPMI_SCRIPT_RESULT != message.code)
	{
		zbx_snprintf(error, max_error_len, "invalid response code:%u received form IPMI service", message.code);
		goto out;
	}

	zbx_ipmi_deserialize_result(message.data, &ts, &ret, &value);

	if (SUCCEED != ret)
		zbx_strlcpy(error, value, max_error_len);
out:
	zbx_free(value);
	zbx_free(data);
	zbx_ipc_message_clean(&message);
	zbx_ipc_socket_close(&ipmi_socket);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

#endif
