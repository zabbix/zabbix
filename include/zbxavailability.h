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
#include "db.h"

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

#define ZBX_AGENT_ZABBIX	(INTERFACE_TYPE_AGENT - 1)
#define ZBX_AGENT_SNMP		(INTERFACE_TYPE_SNMP - 1)
#define ZBX_AGENT_IPMI		(INTERFACE_TYPE_IPMI - 1)
#define ZBX_AGENT_JMX		(INTERFACE_TYPE_JMX - 1)
#define ZBX_AGENT_UNKNOWN	255
#define ZBX_AGENT_MAX		INTERFACE_TYPE_COUNT

typedef struct
{
	zbx_uint64_t			interfaceid;
	zbx_agent_availability_t	agent;
	/* ensure chronological order in case of flapping interface availability */
	int				id;
}
zbx_interface_availability_t;

ZBX_PTR_VECTOR_DECL(availability_ptr, zbx_interface_availability_t *)

void	zbx_db_update_interface_availabilities(const zbx_vector_availability_ptr_t *interface_availabilities);

#define ZBX_IPC_SERVICE_AVAILABILITY	"availability"

void	zbx_availability_flush(unsigned char *data, zbx_uint32_t size);
void	zbx_availabilities_flush(const zbx_vector_availability_ptr_t *interface_availabilities);

void	zbx_availability_serialize(unsigned char **data, size_t *data_alloc, size_t *data_offset,
		const zbx_interface_availability_t *interface_availability);
void	zbx_availability_deserialize(const unsigned char *data, zbx_uint32_t size,
		zbx_vector_availability_ptr_t  *interface_availabilities);

#endif /* ZABBIX_AVAILABILITY_H */
