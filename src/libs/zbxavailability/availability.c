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
#define ZBX_IPC_AVAILABILITY_REQUEST	1
	if (FAIL == zbx_ipc_socket_write(&socket, ZBX_IPC_AVAILABILITY_REQUEST, data, size))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot send data to availability manager service");
		exit(EXIT_FAILURE);
	}
#undef ZBX_IPC_AVAILABILITY_REQUEST
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

/******************************************************************************
 *                                                                            *
 * Purpose: adds interface availability update to sql statement               *
 *                                                                            *
 * Parameters: ia           [IN] the interface availability data              *
 *             sql        - [IN/OUT] the sql statement                        *
 *             sql_alloc  - [IN/OUT] the number of bytes allocated for sql    *
 *                                   statement                                *
 *             sql_offset - [IN/OUT] the number of bytes used in sql          *
 *                                   statement                                *
 *                                                                            *
 * Return value: SUCCEED - sql statement is created                           *
 *               FAIL    - no interface availability is set                   *
 *                                                                            *
 ******************************************************************************/
static int	zbx_sql_add_interface_availability(const zbx_interface_availability_t *ia, char **sql,
		size_t *sql_alloc, size_t *sql_offset)
{
	char		delim = ' ';

	if (FAIL == zbx_interface_availability_is_set(ia))
		return FAIL;

	zbx_strcpy_alloc(sql, sql_alloc, sql_offset, "update interface set");

	if (0 != (ia->agent.flags & ZBX_FLAGS_AGENT_STATUS_AVAILABLE))
	{
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%cavailable=%d", delim, (int)ia->agent.available);
		delim = ',';
	}

	if (0 != (ia->agent.flags & ZBX_FLAGS_AGENT_STATUS_ERROR))
	{
		char	*error_esc;

		error_esc = DBdyn_escape_field("interface", "error", ia->agent.error);
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%cerror='%s'", delim, error_esc);
		zbx_free(error_esc);
		delim = ',';
	}

	if (0 != (ia->agent.flags & ZBX_FLAGS_AGENT_STATUS_ERRORS_FROM))
	{
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%cerrors_from=%d", delim, ia->agent.errors_from);
		delim = ',';
	}

	if (0 != (ia->agent.flags & ZBX_FLAGS_AGENT_STATUS_DISABLE_UNTIL))
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%cdisable_until=%d", delim, ia->agent.disable_until);

	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, " where interfaceid=" ZBX_FS_UI64, ia->interfaceid);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: sync interface availabilities updates into database               *
 *                                                                            *
 * Parameters: interface_availabilities [IN] the interface availability data  *
 *                                                                            *
 ******************************************************************************/
void	zbx_db_update_interface_availabilities(const zbx_vector_availability_ptr_t *interface_availabilities)
{
	int	txn_error;
	char	*sql = NULL;
	size_t	sql_alloc = 4 * ZBX_KIBIBYTE;
	int	i;

	sql = (char *)zbx_malloc(sql, sql_alloc);

	do
	{
		size_t	sql_offset = 0;

		DBbegin();
		DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

		for (i = 0; i < interface_availabilities->values_num; i++)
		{
			if (SUCCEED != zbx_sql_add_interface_availability(interface_availabilities->values[i], &sql,
					&sql_alloc, &sql_offset))
			{
				continue;
			}

			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");
			DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
		}

		DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

		if (16 < sql_offset)
			DBexecute("%s", sql);

		txn_error = DBcommit();
	}
	while (ZBX_DB_DOWN == txn_error);

	zbx_free(sql);
}
