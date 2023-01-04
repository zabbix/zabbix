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

#ifndef ZABBIX_PP_MANAGER_H
#define ZABBIX_PP_MANAGER_H

#include "pp_worker.h"
#include "pp_queue.h"
#include "zbxpreproc.h"
#include "zbxalgo.h"
#include "zbxtimekeeper.h"

typedef struct
{
	zbx_pp_worker_t		*workers;
	int			workers_num;
	int			program_type;

	zbx_hashset_t		items;
	zbx_uint64_t		revision;

	zbx_pp_queue_t		queue;

	zbx_timekeeper_t	*timekeeper;
}
zbx_pp_manager_t;

int	pp_manager_init(zbx_pp_manager_t *manager, int program_type, int workers_num, char **error);
void	pp_manager_destroy(zbx_pp_manager_t *manager);
int	pp_manager_queue_preproc(zbx_pp_manager_t *manager, zbx_uint64_t itemid, zbx_variant_t *value,
		zbx_timespec_t ts, const zbx_pp_value_opt_t *value_opt);
void	pp_manager_process_finished(zbx_pp_manager_t *manager);
void	pp_manager_dump_items(zbx_pp_manager_t *manager);

void	pp_manager_flush_value(zbx_pp_manager_t *manager, zbx_uint64_t itemid, unsigned char value_type,
		unsigned char flags, zbx_variant_t *value, zbx_timespec_t ts, zbx_pp_value_opt_t *value_opt);

#endif
