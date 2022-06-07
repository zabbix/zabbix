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

ZBX_PTR_VECTOR_IMPL(proxy_hostdata_ptr, zbx_proxy_hostdata_t *)
ZBX_PTR_VECTOR_IMPL(host_active_avail_ptr, zbx_host_active_avail_t *)

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

void	zbx_availability_serialize_json_hostdata(zbx_vector_proxy_hostdata_ptr_t *hostdata, struct zbx_json *j)
{
	int	i;

	if (0 == hostdata->values_num)
		return;

	zbx_json_addarray(j, ZBX_PROTO_TAG_PROXY_ACTIVE_AVAIL_DATA);

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
	int			status = INTERFACE_AVAILABLE_UNKNOWN;

	zbx_ipc_message_init(&response);
	data_len = zbx_availability_serialize_active_status_request(&data, hostid);
	zbx_availability_send(ZBX_IPC_AVAILMAN_ACTIVE_STATUS, data, data_len, &response);

	if (0 != response.size)
		zbx_availability_deserialize_active_status_response(response.data, &status);

	zbx_ipc_message_clean(&response);
	zbx_free(data);

	return status;
}

/******************************************************************************
 *                                                                            *
 * Purpose: initializes interface availability data                           *
 *                                                                            *
 * Parameters: availability - [IN/OUT] interface availability data            *
 *             interfaceid  - [IN]                                            *
 *                                                                            *
 ******************************************************************************/
void	zbx_interface_availability_init(zbx_interface_availability_t *availability, zbx_uint64_t interfaceid)
{
	memset(availability, 0, sizeof(zbx_interface_availability_t));
	availability->interfaceid = interfaceid;
}

/********************************************************************************
 *                                                                              *
 * Purpose: releases resources allocated to store interface availability data   *
 *                                                                              *
 * Parameters: ia - [IN] interface availability data                            *
 *                                                                              *
 ********************************************************************************/
void	zbx_interface_availability_clean(zbx_interface_availability_t *ia)
{
	zbx_free(ia->agent.error);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees interface availability data                                 *
 *                                                                            *
 * Parameters: availability - [IN] interface availability data                *
 *                                                                            *
 ******************************************************************************/
void	zbx_interface_availability_free(zbx_interface_availability_t *availability)
{
	zbx_interface_availability_clean(availability);
	zbx_free(availability);
}

ZBX_PTR_VECTOR_IMPL(availability_ptr, zbx_interface_availability_t *)
/******************************************************************************
 *                                                                            *
 * Purpose: initializes agent availability with the specified data            *
 *                                                                            *
 * Parameters: agent         - [IN/OUT] agent availability data               *
 *             available     - [IN] the availability data                     *
 *             error         - [IN] the availability error                    *
 *             errors_from   - [IN] error starting timestamp                  *
 *             disable_until - [IN] disable until timestamp                   *
 *                                                                            *
 ******************************************************************************/
void	zbx_agent_availability_init(zbx_agent_availability_t *agent, unsigned char available, const char *error,
		int errors_from, int disable_until)
{
	agent->flags = ZBX_FLAGS_AGENT_STATUS;
	agent->available = available;
	agent->error = zbx_strdup(NULL, error);
	agent->errors_from = errors_from;
	agent->disable_until = disable_until;
}

/******************************************************************************
 *                                                                            *
 * Purpose: checks interface availability if agent availability field is set  *
 *                                                                            *
 * Parameters: ia - [IN] interface availability data                          *
 *                                                                            *
 * Return value: SUCCEED - an agent availability field is set                 *
 *               FAIL - no agent availability field is set                    *
 *                                                                            *
 ******************************************************************************/
int	zbx_interface_availability_is_set(const zbx_interface_availability_t *ia)
{
	if (ZBX_FLAGS_AGENT_STATUS_NONE != ia->agent.flags)
		return SUCCEED;

	return FAIL;
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
		zbx_DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

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

		zbx_DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

		if (16 < sql_offset)
			DBexecute("%s", sql);

		txn_error = DBcommit();
	}
	while (ZBX_DB_DOWN == txn_error);

	zbx_free(sql);
}
