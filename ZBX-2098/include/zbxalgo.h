/*
** ZABBIX
** Copyright (C) 2000-2010 SIA Zabbix
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/

#ifndef ZABBIX_ZBXALGO_H
#define ZABBIX_ZBXALGO_H

/* generic */

typedef uint32_t zbx_hash_t;

typedef zbx_hash_t (*zbx_hash_func_t)(const void *data);

zbx_hash_t	zbx_string_hash_lookup2(const void *data);
zbx_hash_t	zbx_string_hash_modfnv(const void *data);
zbx_hash_t	zbx_string_hash_murmur2(const void *data);
zbx_hash_t	zbx_string_hash_sdbm(const void *data);
zbx_hash_t	zbx_string_hash_djb2(const void *data);

#define	ZBX_DEFAULT_STRING_HASH_FUNC	zbx_string_hash_modfnv

typedef int (*zbx_compare_func_t)(const void *d1, const void *d2);

typedef void *(*zbx_mem_malloc_func_t)(void *old, size_t size);
typedef void *(*zbx_mem_realloc_func_t)(void *old, size_t size);
typedef void (*zbx_mem_free_func_t)(void *ptr);

void	*zbx_default_mem_malloc_func(void *old, size_t size);
void	*zbx_default_mem_realloc_func(void *old, size_t size);
void	zbx_default_mem_free_func(void *ptr);

#define	ZBX_DEFAULT_MEM_MALLOC_FUNC	zbx_default_mem_malloc_func
#define	ZBX_DEFAULT_MEM_REALLOC_FUNC	zbx_default_mem_realloc_func
#define	ZBX_DEFAULT_MEM_FREE_FUNC	zbx_default_mem_free_func

/* hashset */

#define	ZBX_HASHSET_ENTRY_T	struct zbx_hashset_entry_s

ZBX_HASHSET_ENTRY_T
{
	ZBX_HASHSET_ENTRY_T	*next;
	void			*data;
	zbx_hash_t		hash;
};

typedef struct
{
	ZBX_HASHSET_ENTRY_T	**slots;
	int			num_slots;
	int			num_data;
	zbx_hash_func_t		hash_func;
	zbx_compare_func_t	compare_func;
	zbx_mem_malloc_func_t	mem_malloc_func;
	zbx_mem_realloc_func_t	mem_realloc_func;
	zbx_mem_free_func_t	mem_free_func;
}
zbx_hashset_t;

void	zbx_hashset_create(zbx_hashset_t *hs, size_t init_size,
				zbx_hash_func_t hash_func,
				zbx_compare_func_t compare_func);
void	zbx_hashset_create_ext(zbx_hashset_t *hs, size_t init_size,
				zbx_hash_func_t hash_func,
				zbx_compare_func_t compare_func,
				zbx_mem_malloc_func_t mem_malloc_func,
				zbx_mem_realloc_func_t mem_realloc_func,
				zbx_mem_free_func_t mem_free_func);
void	zbx_hashset_destroy(zbx_hashset_t *hs);

void	*zbx_hashset_insert(zbx_hashset_t *hs, const void *data, size_t size);
void	*zbx_hashset_search(zbx_hashset_t *hs, const void *data);
void	zbx_hashset_remove(zbx_hashset_t *hs, const void *data);

void	zbx_hashset_clear(zbx_hashset_t *hs);

#endif
