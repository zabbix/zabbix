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

#ifndef ZABBIX_UM_CACHE_MOCK_H
#define ZABBIX_UM_CACHE_MOCK_H

#include "zbxcacheconfig/dbconfig.h"
#include "zbxcacheconfig/dbsync.h"

typedef struct
{
	zbx_uint64_t	macroid;
	zbx_uint64_t	hostid;
	unsigned char	type;
	char		*macro;
	char		*value;
}
zbx_um_mock_macro_t;

ZBX_PTR_VECTOR_DECL(um_mock_macro, zbx_um_mock_macro_t *)

typedef struct
{
	zbx_uint64_t			hostid;
	zbx_vector_um_mock_macro_t	macros;
	zbx_vector_uint64_t		templateids;
}
zbx_um_mock_host_t;

typedef struct
{
	const char	*key;
	const char	*value;
}
zbx_um_mock_kv_t;

ZBX_PTR_VECTOR_DECL(um_mock_kv, zbx_um_mock_kv_t *)

typedef struct
{
	const char		*path;
	zbx_vector_um_mock_kv_t	kvs;
}
zbx_um_mock_kvset_t;

ZBX_PTR_VECTOR_DECL(um_mock_kvset, zbx_um_mock_kvset_t *)

typedef struct
{
	zbx_hashset_t			hosts;
	zbx_vector_um_mock_kvset_t	kvsets;
}
zbx_um_mock_cache_t;

void	um_mock_config_init(void);
void	um_mock_config_destroy(void);

void	um_mock_cache_init(zbx_um_mock_cache_t *cache, zbx_mock_handle_t handle);
void	um_mock_cache_init_from_config(zbx_um_mock_cache_t *cache, zbx_um_cache_t *cfg);
void	um_mock_cache_clear(zbx_um_mock_cache_t *cache);
void	um_mock_cache_dump(zbx_um_mock_cache_t *cache);

void	um_mock_cache_diff(zbx_um_mock_cache_t *cache1, zbx_um_mock_cache_t *cache2, zbx_dbsync_t *gmacros,
		zbx_dbsync_t *hmacros, zbx_dbsync_t *htmpls);

void	mock_dbsync_clear(zbx_dbsync_t *sync);

#endif
