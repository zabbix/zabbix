/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

#ifndef ZABBIX_VALUECACHE_TEST_H
#define ZABBIX_VALUECACHE_TEST_H

void	zbx_vc_set_mode(int mode);
int	zbx_vc_get_cached_values(zbx_uint64_t itemid, unsigned char value_type, zbx_vector_history_record_t *values);
int	zbx_vc_precache_values(zbx_uint64_t itemid, int value_type, int seconds, int count, const zbx_timespec_t *end);
int	zbx_vc_get_item_state(zbx_uint64_t itemid, int *status, int *active_range, int *values_total,
		int *db_cached_from);
int	zbx_vc_get_cache_state(int *mode, zbx_uint64_t *hits, zbx_uint64_t *misses);

#endif
