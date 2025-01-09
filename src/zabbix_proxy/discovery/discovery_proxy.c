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

#include "discovery_proxy.h"

#include "zbxproxybuffer.h"
#include "zbxalgo.h"

void	*zbx_discovery_open_proxy(void)
{
	return zbx_pb_discovery_open();
}

void	zbx_discovery_close_proxy(void *handle)
{
	zbx_pb_discovery_close((zbx_pb_discovery_data_t *)handle);
}

void	zbx_discovery_update_host_proxy(void *handle, zbx_uint64_t druleid, zbx_db_dhost *dhost, const char *ip,
		const char *dns, int status, time_t now, zbx_add_event_func_t add_event_cb)
{
	ZBX_UNUSED(dhost);
	ZBX_UNUSED(add_event_cb);

	zbx_pb_discovery_write_host((zbx_pb_discovery_data_t *)handle, druleid, ip, dns, status, (int)now, "");
}

void	zbx_discovery_update_service_proxy(void *handle, zbx_uint64_t druleid, zbx_uint64_t dcheckid,
		zbx_uint64_t unique_dcheckid, zbx_db_dhost *dhost, const char *ip, const char *dns, int port,
		int status, const char *value, time_t now, zbx_vector_uint64_t *dserviceids,
		zbx_add_event_func_t add_event_cb)
{
	ZBX_UNUSED(unique_dcheckid);
	ZBX_UNUSED(dhost);
	ZBX_UNUSED(dserviceids);
	ZBX_UNUSED(add_event_cb);

	zbx_pb_discovery_write_service((zbx_pb_discovery_data_t *)handle, druleid, dcheckid, ip, dns, port, status,
			value, (int)now);
}

void	zbx_discovery_find_host_proxy(const zbx_uint64_t druleid, const char *ip, zbx_db_dhost *dhost)
{
	ZBX_UNUSED(druleid);
	ZBX_UNUSED(ip);
	ZBX_UNUSED(dhost);
}

void	zbx_discovery_update_service_down_proxy(const zbx_uint64_t dhostid, const time_t now,
		zbx_vector_uint64_t *dserviceids)
{
	ZBX_UNUSED(dhostid);
	ZBX_UNUSED(now);
	ZBX_UNUSED(dserviceids);
}

/******************************************************************************
 *                                                                            *
 * Purpose: send drule error info                                             *
 *                                                                            *
******************************************************************************/
void	zbx_discovery_update_drule_proxy(void *handle, zbx_uint64_t druleid, const char *error, time_t now)
{
	zbx_pb_discovery_write_host((zbx_pb_discovery_data_t *)handle, druleid, "", "", DOBJECT_STATUS_FINALIZED,
			(int)now, error);
}
