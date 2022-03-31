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

#ifndef ZABBIX_ZBXKSTAT_H
#define ZABBIX_ZBXKSTAT_H

#include "zbxtypes.h"

#if defined(HAVE_KSTAT_H) && defined(HAVE_VMINFO_T_UPDATES)

typedef struct
{
	zbx_uint64_t	freemem;
	zbx_uint64_t	updates;
}
zbx_kstat_vminfo_t;

typedef struct
{
	zbx_kstat_vminfo_t	vminfo[2];
	int			vminfo_index;
}
zbx_kstat_t;

int	zbx_kstat_init(zbx_kstat_t *kstat, char **error);
void	zbx_kstat_destroy(void);
void	zbx_kstat_collect(zbx_kstat_t *kstat);
int	zbx_kstat_get_freemem(zbx_uint64_t *value, char **error);

#endif

#endif
