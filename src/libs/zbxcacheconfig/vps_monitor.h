/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

#ifndef ZABBIX_VPS_MONITOR_H
#define ZABBIX_VPS_MONITOR_H

#include "zbxcommon.h"

typedef struct
{
	zbx_uint64_t	total_values_num;
	zbx_uint64_t	values_num;
	zbx_uint64_t	values_limit;
	zbx_uint64_t	overcommit_limit;
	zbx_uint64_t	overcommit;

	time_t		last_flush;
} zbx_vps_monitor_t;

int	vps_monitor_create(zbx_vps_monitor_t *monitor, char **error);
void	vps_monitor_destroy(void);

#endif
