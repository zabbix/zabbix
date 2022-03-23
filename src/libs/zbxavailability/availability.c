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

#include "zbxavailability.h"
#include "log.h"
#include "zbxipcservice.h"
#include "avail_protocol.h"

void	zbx_availability_send(zbx_uint32_t code, unsigned char *data, zbx_uint32_t size, zbx_ipc_message_t *response)
{
	static zbx_ipc_socket_t	socket;

	/* each process has a permanent connection to availability manager */
	if (0 == socket.fd)
	{
		char	*error = NULL;

		if (FAIL == zbx_ipc_socket_open(&socket, ZBX_IPC_SERVICE_AVAILABILITY, SEC_PER_MIN, &error))
		{
			zabbix_log(LOG_LEVEL_CRIT, "cannot connect to availability manager service: %s", error);
			exit(EXIT_FAILURE);
		}
	}

	if (FAIL == zbx_ipc_socket_write(&socket, code, data, size))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot send data to availability manager service");
		exit(EXIT_FAILURE);
	}

	if (NULL != response && FAIL == zbx_ipc_socket_read(&socket, response))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot receive data from service");
		exit(EXIT_FAILURE);
	}
}

void	zbx_availabilities_flush(const zbx_vector_availability_ptr_t *interface_availabilities)
{
	unsigned char	*data = NULL;
	size_t		data_alloc = 0, data_offset = 0;
	int		i;

	for (i = 0; i < interface_availabilities->values_num; i++)
	{
		zbx_availability_serialize_interface(&data, &data_alloc, &data_offset,
				interface_availabilities->values[i]);
	}

	zbx_availability_send(ZBX_IPC_AVAILABILITY_REQUEST, data, (zbx_uint32_t)data_offset, NULL);
	zbx_free(data);
}

void	zbx_availability_serialize_json_hostdata(zbx_vector_ptr_t *hostdata, struct zbx_json *j)
{
	int	i;

	zbx_json_addarray(j, ZBX_PROTO_TAG_HOST_DATA);

	for (i = 0; i < hostdata->values_num; i++)
	{
		zbx_proxy_hostdata_t	*hd = hostdata->values[i];

		zbx_json_addobject(j, NULL);
		zbx_json_adduint64(j, ZBX_PROTO_TAG_HOSTID, hd->hostid);
		zbx_json_addint64(j, ZBX_PROTO_TAG_ACTIVE_STATUS, hd->status);
		zbx_json_close(j);
	}

	zbx_json_close(j);
}

int	zbx_get_active_agent_availability(zbx_uint64_t hostid)
{
	zbx_ipc_message_t	response;
	unsigned char		*data = NULL;
	zbx_uint32_t		data_len = 0;
	int			status;

	zbx_ipc_message_init(&response);
	data_len = zbx_availability_serialize_active_status_request(&data, hostid);
	zbx_availability_send(ZBX_IPC_AVAILMAN_ACTIVE_STATUS, data, data_len, &response);

	if (0 != response.size)
		zbx_availability_deserialize_active_status_response(response.data, &status);

	zbx_ipc_message_clean(&response);

	return status;
}