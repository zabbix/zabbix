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

#ifndef ZABBIX_SUPERVISOR_H
#define ZABBIX_SUPERVISOR_H

#include "zbxcommon.h"
#include "zbxalgo.h"
#include "zbxnix.h"

typedef struct
{
	unsigned char		type;
	int			num;
	int			index;
	zbx_proc_owner_t	owner;
}
zbx_proc_info_t;

ZBX_VECTOR_DECL(proc_info, zbx_proc_info_t)

typedef struct
{
	zbx_vector_proc_info_t	processes;
}
zbx_proc_startup_t;

typedef struct
{
	void *(*entry)(void *);
	void	*args;
}
zbx_supervisor_unit_def_t;

typedef int (*zbx_get_unit_def_cb_t)(unsigned char process_type, zbx_supervisor_unit_def_t *entry);

typedef struct
{
	zbx_proc_startup_t		*runlevels;
	int				config_timeout;
	unsigned char			program_type;

	zbx_supervisor_unit_def_t	unit_defs[ZBX_PROCESS_TYPE_COUNT];
}
zbx_thread_supervisor_args_t;

zbx_proc_startup_t	*zbx_proc_startup_create(int threads_num,
		zbx_get_process_info_by_thread_f get_process_info_by_thread_cb);
void	zbx_proc_startup_free(zbx_proc_startup_t *runlevels);

ZBX_THREAD_ENTRY(zbx_supervisor_thread, args);

#endif
