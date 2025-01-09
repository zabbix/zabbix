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

#ifndef ZABBIX_VALUECACHE_TEST_H
#define ZABBIX_VALUECACHE_TEST_H

#include "zbxmockdata.h"

void	zbx_vc_set_mode(int mode);
int	zbx_vc_get_cached_values(zbx_uint64_t itemid, unsigned char value_type, zbx_vector_history_record_t *values);
int	zbx_vc_precache_values(zbx_uint64_t itemid, int value_type, int seconds, int count, const zbx_timespec_t *ts);
int	zbx_vc_get_item_state(zbx_uint64_t itemid, int *status, int *active_range, int *values_total,
		int *db_cached_from);
int	zbx_vc_get_cache_state(int *mode, zbx_uint64_t *hits, zbx_uint64_t *misses);

void	zbx_vcmock_set_mode(zbx_mock_handle_t hitem, const char *key);
int	zbx_vcmock_str_to_cache_mode(const char *mode);

#endif
