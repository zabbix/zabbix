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

void	zbx_vcmock_ds_init(void);
void	zbx_vcmock_ds_destroy(void);
void	zbx_vcmock_ds_dump(void);
zbx_vcmock_ds_item_t	*zbx_vcmock_ds_first_item(void);

int	zbx_vcmock_str_to_cache_mode(const char *mode);
int	zbx_vcmock_str_to_item_status(const char *str);

void	zbx_vcmock_read_values(zbx_mock_handle_t hdata, unsigned char value_type, zbx_vector_history_record_t *values);
void	zbx_vcmock_check_records(const char *prefix, unsigned char value_type,
		const zbx_vector_history_record_t *expected_values, const zbx_vector_history_record_t *returned_values);

void	zbx_vcmock_set_available_mem(size_t size);
size_t	zbx_vcmock_get_available_mem(void);

void	zbx_vcmock_set_time(zbx_mock_handle_t hitem, const char *key);
zbx_timespec_t	zbx_vcmock_get_ts(void);
void	zbx_vcmock_set_cache_size(zbx_mock_handle_t hitem, const char *key);
void	zbx_vcmock_get_request_params(zbx_mock_handle_t handle, zbx_uint64_t *itemid, unsigned char *value_type,
		int *seconds, int *count, zbx_timespec_t *end);
void	zbx_vcmock_set_mode(zbx_mock_handle_t hitem, const char *key);

void	zbx_vcmock_get_dc_history(zbx_mock_handle_t handle, zbx_vector_ptr_t *history);
void	zbx_vcmock_free_dc_history(void *ptr);

#endif
