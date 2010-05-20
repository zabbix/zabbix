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

#ifndef ZABBIX_MEMALLOC_H
#define ZABBIX_MEMALLOC_H

#include "common.h"
#include "mutexs.h"

typedef struct
{
	void		**buckets;
	void		*lo_bound;
	void		*hi_bound;
	uint32_t	free_size;
	uint32_t	used_size;
	uint32_t	orig_size;
	uint32_t	total_size;
	int		shm_id;
	char		use_lock;
	ZBX_MUTEX	mem_lock;
	char		*mem_descr;
}
zbx_mem_info_t;

void	zbx_mem_create(zbx_mem_info_t **info, key_t shm_key, int lock_name, size_t size, const char *descr);
void	zbx_mem_destroy(zbx_mem_info_t *info);

#define	zbx_mem_malloc(info, old, size) __zbx_mem_malloc(__FILE__, __LINE__, info, old, size)
#define	zbx_mem_realloc(info, old, size) __zbx_mem_realloc(__FILE__, __LINE__, info, old, size)
#define	zbx_mem_free(info, ptr) do { __zbx_mem_free(__FILE__, __LINE__, info, ptr); ptr = NULL; } while (0)

void	*__zbx_mem_malloc(const char *file, int line, zbx_mem_info_t *info, const void *old, size_t size);
void	*__zbx_mem_realloc(const char *file, int line, zbx_mem_info_t *info, void *old, size_t size);
void	__zbx_mem_free(const char *file, int line, zbx_mem_info_t *info, void *ptr);

void	zbx_mem_clear(zbx_mem_info_t *info);

void	zbx_mem_dump_stats(zbx_mem_info_t *info);

#endif
