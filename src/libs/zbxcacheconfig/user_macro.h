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

#ifndef ZBX_USER_MACRO_H
#define ZBX_USER_MACRO_H

#include "zbxalgo.h"
#include "zbxcacheconfig.h"

#define ZBX_UM_CACHE_GLOBAL_MACRO_HOSTID	0

typedef struct
{
	zbx_uint64_t	macroid;
	zbx_uint64_t	hostid;
	const char	*name;
	const char	*context;
	const char	*value;
	zbx_uint32_t	refcount;
	unsigned char	type;
	unsigned char	context_op;
}
zbx_um_macro_t;

ZBX_PTR_VECTOR_DECL(um_macro, zbx_um_macro_t *)

typedef struct
{
	zbx_uint64_t		hostid;
	zbx_vector_uint64_t	templateids;
	zbx_vector_um_macro_t	macros;
	zbx_uint32_t		refcount;
	zbx_uint64_t		macro_revision;
	zbx_uint64_t		link_revision;
}
zbx_um_host_t;

ZBX_PTR_VECTOR_DECL(um_host, zbx_um_host_t *)

struct zbx_um_cache
{
	zbx_hashset_t	hosts;
	zbx_uint32_t	refcount;
	zbx_uint64_t	revision;
};

zbx_hash_t	um_macro_hash(const void *d);
int	um_macro_compare(const void *d1, const void *d2);

zbx_um_cache_t	*um_cache_create(void);
void	um_cache_release(zbx_um_cache_t *cache);
void	um_macro_release(zbx_um_macro_t *macro);

zbx_um_cache_t	*um_cache_set_value_to_macros(zbx_um_cache_t *cache, zbx_uint64_t revision,
		const zbx_vector_uint64_pair_t *host_macro_ids, const char *value);

int	um_macro_check_vault_location(const zbx_um_macro_t *macro, const char *location);

void	um_cache_resolve_const(const zbx_um_cache_t *cache, const zbx_uint64_t *hostids, int hostids_num,
		const char *macro, int env, const char **value);
void	um_cache_resolve(const zbx_um_cache_t *cache, const zbx_uint64_t *hostids, int hostids_num, const char *macro,
		int env, char **value);
int	um_cache_get_host_revision(const zbx_um_cache_t *cache, zbx_uint64_t hostid, zbx_uint64_t *revision);
void	um_cache_get_macro_updates(const zbx_um_cache_t *cache, const zbx_uint64_t *hostids, int hostids_num,
		zbx_uint64_t revision, zbx_vector_uint64_t *macro_hostids, zbx_vector_uint64_t *del_macro_hostids);

void	um_cache_get_unused_templates(zbx_um_cache_t *cache, zbx_hashset_t *templates,
		const zbx_vector_uint64_t *hostids, zbx_vector_uint64_t *templateids);
void	um_cache_remove_hosts(zbx_um_cache_t *cache, const zbx_vector_uint64_t *hostids);

void	um_cache_dump(zbx_um_cache_t *cache);

#endif
