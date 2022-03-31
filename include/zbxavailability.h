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

#define ZBX_IPC_SERVICE_AVAILABILITY	"availability"
#define ZBX_IPC_AVAILABILITY_REQUEST	1

void	zbx_availability_flush(unsigned char *data, zbx_uint32_t size);
void	zbx_availabilities_flush(const zbx_vector_availability_ptr_t *interface_availabilities);

#endif /* ZABBIX_AVAILABILITY_H */
