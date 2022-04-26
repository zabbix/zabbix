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

void	zbx_availability_flush(unsigned char *data, zbx_uint32_t size);
void	zbx_availabilities_flush(const zbx_vector_availability_ptr_t *interface_availabilities);

void	zbx_interface_availability_init(zbx_interface_availability_t *availability, zbx_uint64_t interfaceid);
void	zbx_interface_availability_clean(zbx_interface_availability_t *ia);
void	zbx_interface_availability_free(zbx_interface_availability_t *availability);
void	zbx_agent_availability_init(zbx_agent_availability_t *agent, unsigned char available, const char *error,
		int errors_from, int disable_until);

int	zbx_interface_availability_is_set(const zbx_interface_availability_t *ia);

void	zbx_db_update_interface_availabilities(const zbx_vector_availability_ptr_t *interface_availabilities);

void	zbx_availability_serialize(unsigned char **data, size_t *data_alloc, size_t *data_offset,
		const zbx_interface_availability_t *interface_availability);
void	zbx_availability_deserialize(const unsigned char *data, zbx_uint32_t size,
		zbx_vector_availability_ptr_t  *interface_availabilities);
#endif /* ZABBIX_AVAILABILITY_H */
