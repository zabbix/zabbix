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

#ifndef VALUECACHE_MOCK_H
#define VALUECACHE_MOCK_H

typedef struct
{
	zbx_uint64_t			itemid;
	unsigned char			value_type;
	zbx_vector_history_record_t	data;
}
zbx_vcmock_ds_item_t;

typedef struct
{
	zbx_hashset_t	items;
}
zbx_vcmock_ds_t;

unsigned char	zbx_mock_get_value_type(const char *value_type);

void	zbx_vcmock_ds_init();
void	zbx_vcmock_ds_destroy();
void	zbx_vcmock_ds_dump();

int	zbx_vcmock_get_cache_mode(const char *mode);
int	zbx_vcmock_get_item_status(const char *status);

void	zbx_vcmock_read_values(zbx_mock_handle_t handle, unsigned char value_type, zbx_vector_history_record_t *values);
void	zbx_vcmock_check_records(const char *prefix, unsigned char value_type,
		const zbx_vector_history_record_t *expected_values, const zbx_vector_history_record_t *returned_values);

void	zbx_vcmock_set_time(time_t new_time);

void	zbx_vcmock_set_available_mem(size_t size);
size_t	zbx_vcmock_get_available_set();

#endif
