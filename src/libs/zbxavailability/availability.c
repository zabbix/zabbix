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

void	zbx_availability_flush(unsigned char *data, zbx_uint32_t size)
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

	if (FAIL == zbx_ipc_socket_write(&socket, ZBX_IPC_AVAILABILITY_REQUEST, data, size))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot send data to availability manager service");
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
		zbx_availability_serialize(&data, &data_alloc, &data_offset,
				interface_availabilities->values[i]);
	}

	zbx_availability_flush(data, data_offset);
	zbx_free(data);
}
