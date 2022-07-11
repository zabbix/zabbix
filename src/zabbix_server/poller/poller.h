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

#ifndef ZABBIX_POLLER_H
#define ZABBIX_POLLER_H

#include "threads.h"
#include "dbcache.h"

extern int	CONFIG_TIMEOUT;
extern int	CONFIG_UNAVAILABLE_DELAY;
extern int	CONFIG_UNREACHABLE_PERIOD;
extern int	CONFIG_UNREACHABLE_DELAY;

ZBX_THREAD_ENTRY(poller_thread, args);

void	zbx_activate_item_interface(zbx_timespec_t *ts, DC_ITEM *item, unsigned char **data, size_t *data_alloc,
		size_t *data_offset);
void	zbx_deactivate_item_interface(zbx_timespec_t *ts, DC_ITEM *item,  unsigned char **data, size_t *data_alloc,
		size_t *data_offset, const char *error);
void	zbx_prepare_items(DC_ITEM *items, int *errcodes, int num, AGENT_RESULT *results, unsigned char expand_macros);
void	zbx_check_items(DC_ITEM *items, int *errcodes, int num, AGENT_RESULT *results, zbx_vector_ptr_t *add_results,
		unsigned char poller_type);
void	zbx_clean_items(DC_ITEM *items, int num, AGENT_RESULT *results);
void	zbx_free_result_ptr(AGENT_RESULT *result);

#endif
