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

#ifndef ZABBIX_AVAILABILITY_H
#define ZABBIX_AVAILABILITY_H

#include "zbxtypes.h"
#include "zbxdbhigh.h"
#include "zbxipcservice.h"

#define ZBX_IPC_SERVICE_AVAILABILITY		"availability"
#define ZBX_IPC_AVAILABILITY_REQUEST		1
#define ZBX_IPC_AVAILMAN_ACTIVE_HB		2
#define ZBX_IPC_AVAILMAN_ACTIVE_HOSTDATA	3
#define ZBX_IPC_AVAILMAN_ACTIVE_STATUS		4
#define ZBX_IPC_AVAILMAN_CONFSYNC_DIFF		5
#define ZBX_IPC_AVAILMAN_PROCESS_PROXY_HOSTDATA	6
#define ZBX_IPC_AVAILMAN_ACTIVE_PROXY_HB_UPDATE	7
#define ZBX_AVAIL_SERVER_CONN_TIMEOUT		3600

/* agent (ZABBIX, SNMP, IPMI, JMX) availability data */
typedef struct
{
	/* flags specifying which fields are set, see ZBX_FLAGS_AGENT_STATUS_* defines */
	unsigned char	flags;

	/* agent availability fields */
	unsigned char	available;
	char		*error;
	int		errors_from;
	int		disable_until;
}
zbx_agent_availability_t;

#define ZBX_FLAGS_AGENT_STATUS_NONE		0x00000000
#define ZBX_FLAGS_AGENT_STATUS_AVAILABLE	0x00000001
#define ZBX_FLAGS_AGENT_STATUS_ERROR		0x00000002
#define ZBX_FLAGS_AGENT_STATUS_ERRORS_FROM	0x00000004
#define ZBX_FLAGS_AGENT_STATUS_DISABLE_UNTIL	0x00000008

#define ZBX_FLAGS_AGENT_STATUS		(ZBX_FLAGS_AGENT_STATUS_AVAILABLE |	\
					ZBX_FLAGS_AGENT_STATUS_ERROR |		\
					ZBX_FLAGS_AGENT_STATUS_ERRORS_FROM |	\
					ZBX_FLAGS_AGENT_STATUS_DISABLE_UNTIL)

typedef struct
{
	zbx_uint64_t			interfaceid;
	zbx_agent_availability_t	agent;
	/* ensure chronological order in case of flapping interface availability */
	int				id;
}
zbx_interface_availability_t;

ZBX_PTR_VECTOR_DECL(availability_ptr, zbx_interface_availability_t *)

#define ZBX_IPC_SERVICE_AVAILABILITY	"availability"

void	zbx_availability_send(zbx_uint32_t code, unsigned char *data, zbx_uint32_t size, zbx_ipc_message_t *response);
void	zbx_availabilities_flush(const zbx_vector_availability_ptr_t *interface_availabilities);

typedef struct
{
	zbx_hashset_t		hosts;
	zbx_hashset_t		queue;
	zbx_hashset_t		proxy_avail;
	double			last_status_refresh;
	double			last_proxy_avail_refresh;
}
zbx_avail_active_hb_cache_t;

typedef struct
{
	zbx_uint64_t	hostid;
	int		lastaccess_active;
	int		heartbeat_freq;
	int		active_status;
}
zbx_host_active_avail_t;

ZBX_PTR_VECTOR_DECL(host_active_avail_ptr, zbx_host_active_avail_t *)

typedef struct
{
	zbx_uint64_t	hostid;
	int		status;
}
zbx_proxy_hostdata_t;

ZBX_PTR_VECTOR_DECL(proxy_hostdata_ptr, zbx_proxy_hostdata_t *)

void	zbx_availability_serialize_json_hostdata(zbx_vector_proxy_hostdata_ptr_t *hostdata, struct zbx_json *j);
int	zbx_get_active_agent_availability(zbx_uint64_t hostid);

void	zbx_interface_availability_init(zbx_interface_availability_t *availability, zbx_uint64_t interfaceid);
void	zbx_interface_availability_clean(zbx_interface_availability_t *ia);
void	zbx_interface_availability_free(zbx_interface_availability_t *availability);
void	zbx_agent_availability_init(zbx_agent_availability_t *agent, unsigned char available, const char *error,
		int errors_from, int disable_until);

int	zbx_interface_availability_is_set(const zbx_interface_availability_t *ia);

void	zbx_db_update_interface_availabilities(const zbx_vector_availability_ptr_t *interface_availabilities);

void	zbx_availability_serialize_interface(unsigned char **data, size_t *data_alloc, size_t *data_offset,
		const zbx_interface_availability_t *interface_availability);

void	zbx_availability_deserialize(const unsigned char *data, zbx_uint32_t size,
		zbx_vector_availability_ptr_t  *interface_availabilities);

void	zbx_availability_deserialize_active_hb(const unsigned char *data, zbx_host_active_avail_t *avail);

zbx_uint32_t	zbx_availability_serialize_active_heartbeat(unsigned char **data, zbx_uint64_t hostid,
		int heartbeat_freq);

zbx_uint32_t	zbx_availability_serialize_hostdata(unsigned char **data, zbx_hashset_t *queue);
void	zbx_availability_deserialize_hostdata(const unsigned char *data, zbx_vector_proxy_hostdata_ptr_t *hostdata);

zbx_uint32_t	zbx_availability_serialize_active_status_request(unsigned char **data, zbx_uint64_t hostid);
void	zbx_availability_deserialize_active_status_request(const unsigned char *data, zbx_uint64_t *hostid);

zbx_uint32_t	zbx_availability_serialize_active_status_response(unsigned char **data, int status);
void	zbx_availability_deserialize_active_status_response(const unsigned char *data, int *status);

zbx_uint32_t	zbx_availability_serialize_proxy_hostdata(unsigned char **data, zbx_vector_proxy_hostdata_ptr_t *hosts,
		zbx_uint64_t proxy_hostid);
void		zbx_availability_deserialize_proxy_hostdata(const unsigned char *data, zbx_vector_proxy_hostdata_ptr_t *hostdata,
		zbx_uint64_t *proxy_hostid);

zbx_uint32_t	zbx_availability_serialize_hostids(unsigned char **data, zbx_vector_uint64_t *hostids);
void	zbx_availability_deserialize_hostids(const unsigned char *data, zbx_vector_uint64_t *hostids);

zbx_uint32_t	zbx_availability_serialize_active_proxy_hb_update(unsigned char **data, zbx_uint64_t hostid);
void	zbx_availability_deserialize_active_proxy_hb_update(const unsigned char *data, zbx_uint64_t *hostid);

#endif /* ZABBIX_AVAILABILITY_H */
