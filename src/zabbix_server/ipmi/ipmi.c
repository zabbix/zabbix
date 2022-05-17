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

#include "ipmi.h"

#include "config.h"

#ifdef HAVE_OPENIPMI

#include "log.h"
#include "zbxipcservice.h"
#include "ipmi_protocol.h"
#include "checks_ipmi.h"
#include "zbxserver.h"

/******************************************************************************
 *                                                                            *
 * Purpose: expands user macros in IPMI port value and converts the result to *
 *          to unsigned short value                                           *
 *                                                                            *
 * Parameters: hostid    - [IN] the host identifier                           *
 *             port_orig - [IN] the original port value                       *
 *             port      - [OUT] the resulting port value                     *
 *             error     - [OUT] the error message                            *
 *                                                                            *
 * Return value: SUCCEED - the value was converted successfully               *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_ipmi_port_expand_macros(zbx_uint64_t hostid, const char *port_orig, unsigned short *port, char **error)
{
	char	*tmp;
	int	ret = SUCCEED;

	tmp = zbx_strdup(NULL, port_orig);
	zbx_substitute_simple_macros(NULL, NULL, NULL, NULL, &hostid, NULL, NULL, NULL, NULL, NULL, NULL, NULL,
			&tmp, MACRO_TYPE_COMMON, NULL, 0);

	if (FAIL == is_ushort(tmp, port) || 0 == *port)
	{
		*error = zbx_dsprintf(*error, "Invalid port value \"%s\"", port_orig);
		ret = FAIL;
	}

	zbx_free(tmp);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: executes IPMI command                                             *
 *                                                                            *
 * Parameters: host          - [IN] the target host                           *
 *             command       - [IN] the command to execute                    *
 *             error         - [OUT] the error message buffer                 *
 *             max_error_len - [IN] the size of error message buffer          *
 *                                                                            *
 * Return value: SUCCEED - the command was executed successfully              *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_ipmi_execute_command(const DC_HOST *host, const char *command, char *error, size_t max_error_len)
{
	zbx_ipc_socket_t	ipmi_socket;
	zbx_ipc_message_t	message;
	char			*errmsg = NULL, sensor[ZBX_ITEM_IPMI_SENSOR_LEN_MAX], *value = NULL;
	zbx_uint32_t		data_len;
	unsigned char		*data = NULL;
	int			ret = FAIL, op;
	DC_INTERFACE		interface;
	zbx_timespec_t		ts;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() host:\"%s\" command:%s", __func__, host->host, command);

	if (SUCCEED != zbx_parse_ipmi_command(command, sensor, &op, error, max_error_len))
		goto out;

	if (FAIL == zbx_ipc_socket_open(&ipmi_socket, ZBX_IPC_SERVICE_IPMI, SEC_PER_MIN, &errmsg))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot connect to IPMI service: %s", errmsg);
		exit(EXIT_FAILURE);
	}

	zbx_ipc_message_init(&message);

	if (FAIL == DCconfig_get_interface_by_type(&interface, host->hostid, INTERFACE_TYPE_IPMI))
	{
		zbx_strlcpy(error, "cannot find host IPMI interface", max_error_len);
		goto cleanup;
	}

	if (FAIL == zbx_ipmi_port_expand_macros(host->hostid, interface.port_orig, &interface.port, &errmsg))
	{
		zbx_strlcpy(error, errmsg, max_error_len);
		zbx_free(errmsg);
		goto cleanup;
	}

	data_len = zbx_ipmi_serialize_request(&data, host->hostid, host->hostid, interface.addr, interface.port,
			host->ipmi_authtype, host->ipmi_privilege, host->ipmi_username, host->ipmi_password, sensor, op,
			NULL);

	if (FAIL == zbx_ipc_socket_write(&ipmi_socket, ZBX_IPC_IPMI_SCRIPT_REQUEST, data, data_len))
	{
		zbx_strlcpy(error, "cannot send script request message to IPMI service", max_error_len);
		goto cleanup;
	}

	zbx_ipc_message_init(&message);

	if (FAIL == zbx_ipc_socket_read(&ipmi_socket, &message))
	{
		zbx_strlcpy(error,  "cannot read script request response from IPMI service", max_error_len);
		goto cleanup;
	}

	if (ZBX_IPC_IPMI_SCRIPT_RESULT != message.code)
	{
		zbx_snprintf(error, max_error_len, "invalid response code:%u received from IPMI service", message.code);
		goto cleanup;
	}

	zbx_ipmi_deserialize_result(message.data, &ts, &ret, &value);

	if (SUCCEED != ret)
		zbx_strlcpy(error, value, max_error_len);
cleanup:
	zbx_free(value);
	zbx_free(data);
	zbx_ipc_message_clean(&message);
	zbx_ipc_socket_close(&ipmi_socket);

out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: test IPMI item                                                    *
 *                                                                            *
 * Parameters: item - [IN] IPMI item                                          *
 *             info - [OUT] result or error reason                            *
 *                                                                            *
 * Return value: SUCCEED - the test executed without errors                   *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_ipmi_test_item(const DC_ITEM *item, char **info)
{
	zbx_ipc_socket_t	ipmi_socket;
	zbx_ipc_message_t	message;
	char			*errmsg = NULL, *value = NULL;
	zbx_uint32_t		data_len;
	unsigned char		*data = NULL;
	int			ret = FAIL;
	zbx_timespec_t		ts;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() host:\"%s\"", __func__, item->host.host);

	if (FAIL == zbx_ipc_socket_open(&ipmi_socket, ZBX_IPC_SERVICE_IPMI, SEC_PER_MIN, &errmsg))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot connect to IPMI service: %s", errmsg);
		exit(EXIT_FAILURE);
	}

	data_len = zbx_ipmi_serialize_request(&data, item->host.hostid, item->host.hostid, item->interface.addr,
			item->interface.port, item->host.ipmi_authtype, item->host.ipmi_privilege,
			item->host.ipmi_username, item->host.ipmi_password, item->ipmi_sensor, 0, item->key);

	zbx_ipc_message_init(&message);

	if (FAIL == zbx_ipc_socket_write(&ipmi_socket, ZBX_IPC_IPMI_VALUE_REQUEST, data, data_len))
	{
		*info = zbx_strdup(NULL, "cannot send script request message to IPMI service");
		goto cleanup;
	}

	if (FAIL == zbx_ipc_socket_read(&ipmi_socket, &message))
	{
		*info = zbx_strdup(NULL, "cannot read script request response from IPMI service");
		goto cleanup;
	}

	if (ZBX_IPC_IPMI_VALUE_RESULT != message.code)
	{
		*info = zbx_dsprintf(NULL, "invalid response code:%u received from IPMI service", message.code);
		goto cleanup;
	}

	zbx_ipmi_deserialize_result(message.data, &ts, &ret, &value);

	if (NULL != value)
	{
		*info = value;
		value = NULL;
	}
	else
		*info = zbx_strdup(NULL, "no value");
cleanup:
	zbx_free(value);
	zbx_free(data);
	zbx_ipc_message_clean(&message);
	zbx_ipc_socket_close(&ipmi_socket);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

#endif
