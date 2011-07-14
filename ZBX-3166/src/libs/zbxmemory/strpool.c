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

#include "common.h"
#include "mutexs.h"
#include "ipc.h"
#include "log.h"

#include "zbxalgo.h"
#include "memalloc.h"

#include "strpool.h"

#define LOCK_POOL	zbx_mutex_lock(&strpool.pool_lock)
#define UNLOCK_POOL	zbx_mutex_unlock(&strpool.pool_lock)

extern char		*CONFIG_FILE;

static zbx_strpool_t	strpool;

static zbx_hash_t	__strpool_hash_func(const void *data);
static int		__strpool_compare_func(const void *d1, const void *d2);

ZBX_MEM_FUNC_DECL(__strpool);

#define INIT_HASHSET_SIZE	1000
#define	REFCOUNT_FIELD_SIZE	sizeof(uint32_t)

/* private strpool functions */

static zbx_hash_t	__strpool_hash_func(const void *data)
{
	return ZBX_DEFAULT_STRING_HASH_FUNC(data + REFCOUNT_FIELD_SIZE);
}

static int	__strpool_compare_func(const void *d1, const void *d2)
{
	return strcmp(d1 + REFCOUNT_FIELD_SIZE, d2 + REFCOUNT_FIELD_SIZE);
}

ZBX_MEM_FUNC_IMPL(__strpool, strpool.mem_info);

/* public strpool interface */

void	zbx_strpool_create(size_t size)
{
	const char	*__function_name = "zbx_strpool_create";

	key_t		shm_key;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (-1 == (shm_key = zbx_ftok(CONFIG_FILE, ZBX_IPC_STRPOOL_ID)))
	{	
		zabbix_log(LOG_LEVEL_CRIT, "cannot create IPC key for string pool");
		exit(FAIL);
	}

	zbx_mem_create(&strpool.mem_info, shm_key, ZBX_NO_MUTEX, size, "string pool", "CacheSize");

	if (ZBX_MUTEX_ERROR == zbx_mutex_create_force(&strpool.pool_lock, ZBX_MUTEX_STRPOOL))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot create mutex for string pool");
		exit(FAIL);
	}

	strpool.hashset = __strpool_mem_malloc_func(NULL, sizeof(zbx_hashset_t));
	zbx_hashset_create_ext(strpool.hashset, INIT_HASHSET_SIZE,
				__strpool_hash_func, __strpool_compare_func,
				__strpool_mem_malloc_func, __strpool_mem_realloc_func, __strpool_mem_free_func);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

void	zbx_strpool_destroy()
{
	const char	*__function_name = "zbx_strpool_destroy";

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_mem_destroy(strpool.mem_info);
	zbx_mutex_destroy(&strpool.pool_lock);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

const char	*zbx_strpool_intern(const char *str)
{
	void		*record;
	uint32_t	*refcount;

	LOCK_POOL;

	record = zbx_hashset_search(strpool.hashset, str - REFCOUNT_FIELD_SIZE);

	if (NULL == record)
	{
		record = zbx_hashset_insert_ext(strpool.hashset,
						str - REFCOUNT_FIELD_SIZE,
						REFCOUNT_FIELD_SIZE + strlen(str) + 1,
						REFCOUNT_FIELD_SIZE);
		*(uint32_t *)record = 0;
	}

	refcount = (uint32_t *)record;
	(*refcount)++;

	UNLOCK_POOL;

	return record + REFCOUNT_FIELD_SIZE;
}

const char	*zbx_strpool_acquire(const char *str)
{
	uint32_t	*refcount;

	LOCK_POOL;

	refcount = (uint32_t *)(str - REFCOUNT_FIELD_SIZE);
	(*refcount)++;

	UNLOCK_POOL;

	return str;
}

void	zbx_strpool_release(const char *str)
{
	uint32_t	*refcount;

	LOCK_POOL;

	refcount = (uint32_t *)(str - REFCOUNT_FIELD_SIZE);
	if (--(*refcount) == 0)
		zbx_hashset_remove(strpool.hashset, str - REFCOUNT_FIELD_SIZE);

	UNLOCK_POOL;
}

void	zbx_strpool_clear()
{
	const char	*__function_name = "zbx_strpool_clear";

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	LOCK_POOL;

	zbx_mem_clear(strpool.mem_info);

	strpool.hashset = __strpool_mem_malloc_func(NULL, sizeof(zbx_hashset_t));
	zbx_hashset_create_ext(strpool.hashset, INIT_HASHSET_SIZE,
				__strpool_hash_func, __strpool_compare_func,
				__strpool_mem_malloc_func, __strpool_mem_realloc_func, __strpool_mem_free_func);

	UNLOCK_POOL;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

const zbx_strpool_t	*zbx_strpool_info()
{
	return &strpool;
}
