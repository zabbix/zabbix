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
