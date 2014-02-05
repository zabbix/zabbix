/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

#include "common.h"
#include "mutexs.h"
#include "ipc.h"
#include "log.h"

#include "memalloc.h"

/******************************************************************************
 *                                                                            *
 *                     Some information on memory layout                      *
 *                  ---------------------------------------                   *
 *                                                                            *
 *                                                                            *
 * (*) chunk: a contiguous piece of memory that is either free or used        *
 *                                                                            *
 *                                                                            *
 *                    +-------- size of + --------------+                     *
 *                    |       (8 bytes) |               |                     *
 *                    |                 v               |                     *
 *                    |                                 |                     *
 *                    |    +- allocatable memory --+    |                     *
 *                    |    | (user data goes here) |    |                     *
 *                    v    v                       v    v                     *
 *                                                                            *
 *                |--------|----------------...----|--------|                 *
 *                                                                            *
 *                ^        ^                       ^        ^                 *
 *            8-aligned    |                       |    8-aligned             *
 *                                                                            *
 *                     8-aligned               8-aligned                      *
 *                                                                            *
 *                                                                            *
 *     when a chunk is used, `size' fields have MEM_FLG_USED bit set          *
 *                                                                            *
 *     when a chunk is free, the first 2 * ZBX_PTR_SIZE bytes of allocatable  *
 *     memory contain pointers to the previous and next chunks, in that order *
 *                                                                            *
 *     notes:                                                                 *
 *                                                                            *
 *         - user data is nicely 8-aligned                                    *
 *                                                                            *
 *         - size is kept on both left and right ends for quick merging       *
 *           (when freeing a chunk, we can quickly see if the previous        *
 *           and next chunks are free, those will not have MEM_FLG_USED)      *
 *                                                                            *
 *                                                                            *
 * (*) free chunks are stored in doubly-linked lists according to their sizes *
 *                                                                            *
 *     a typical situation is thus as follows (1 used chunk, 2 free chunks)   *
 *                                                                            *
 *                                                                            *
 *  +--------------------------- shared memory ----------------------------+  *
 *  |                         (can be misaligned)                          |  *
 *  |                                                                      |  *
 *  |                                                                      |  *
 *  |  +------ chunk A ------+------ chunk B -----+------ chunk C ------+  |  *
 *  |  |       (free)        |       (used)       |       (free)        |  |  *
 *  |  |                     |                    |                     |  |  *
 *  v  v                     v                    v                     v  v  *
 *           prevnext              user data            prevnext              *
 *  #--|----|--------...|----|----|---....---|----|----|--------...|----|--#  *
 *           NULL  |                                     |  NULL              *
 *     ^           |                              ^      |              ^     *
 *     |           |                              |      |              |     *
 *     |           +------------------------------+      |              |     *
 *     |                                                 |              |     *
 *     +-------------------------------------------------+              |     *
 *     |                                                                |     *
 *                                                                            *
 *  lo_bound             `size' fields in chunk B                   hi_bound  *
 *  (aligned)            have MEM_FLG_USED bit set                 (aligned)  *
 *                                                                            *
 ******************************************************************************/

#define LOCK_INFO	if (info->use_lock) zbx_mutex_lock(&info->mem_lock)
#define UNLOCK_INFO	if (info->use_lock) zbx_mutex_unlock(&info->mem_lock)

static void	*ALIGN4(void *ptr);
static void	*ALIGN8(void *ptr);
static void	*ALIGNPTR(void *ptr);

static zbx_uint64_t	mem_proper_alloc_size(zbx_uint64_t size);
static int	mem_bucket_by_size(zbx_uint64_t size);

static void	mem_set_chunk_size(void *chunk, zbx_uint64_t size);
static void	mem_set_used_chunk_size(void *chunk, zbx_uint64_t size);

static void	*mem_get_prev_chunk(void *chunk);
static void	mem_set_prev_chunk(void *chunk, void *prev);
static void	*mem_get_next_chunk(void *chunk);
static void	mem_set_next_chunk(void *chunk, void *next);
static void	**mem_ptr_to_prev_field(void *chunk);
static void	**mem_ptr_to_next_field(void *chunk, void **first_chunk);

static void	mem_link_chunk(zbx_mem_info_t *info, void *chunk);
static void	mem_unlink_chunk(zbx_mem_info_t *info, void *chunk);

static void	*__mem_malloc(zbx_mem_info_t *info, zbx_uint64_t size);
static void	*__mem_realloc(zbx_mem_info_t *info, void *old, zbx_uint64_t size);
static void	__mem_free(zbx_mem_info_t *info, void *ptr);

#define MEM_SIZE_FIELD		sizeof(zbx_uint64_t)

#define MEM_FLG_USED		((__UINT64_C(1))<<63)

#define FREE_CHUNK(ptr)		(((*(zbx_uint64_t *)(ptr)) & MEM_FLG_USED) == 0)
#define CHUNK_SIZE(ptr)		((*(zbx_uint64_t *)(ptr)) & ~MEM_FLG_USED)

#define MEM_MIN_SIZE		__UINT64_C(128)
#define MEM_MAX_SIZE		__UINT64_C(0x1000000000)	/* 64 GB */

#define MEM_MIN_ALLOC	24	/* should be a multiple of 8 and at least (2 * ZBX_PTR_SIZE) */

#define MEM_MIN_BUCKET_SIZE	MEM_MIN_ALLOC
#define MEM_MAX_BUCKET_SIZE	256 /* starting from this size all free chunks are put into the same bucket */
#define MEM_BUCKET_COUNT	((MEM_MAX_BUCKET_SIZE - MEM_MIN_BUCKET_SIZE) / 8 + 1)

/* helper functions */

static void	*ALIGN4(void *ptr)
{
	return (void *)((uintptr_t)((char *)ptr + 3) & (uintptr_t)~3);
}

static void	*ALIGN8(void *ptr)
{
	return (void *)((uintptr_t)((char *)ptr + 7) & (uintptr_t)~7);
}

static void	*ALIGNPTR(void *ptr)
{
	if (4 == ZBX_PTR_SIZE)
		return ALIGN4(ptr);
	if (8 == ZBX_PTR_SIZE)
		return ALIGN8(ptr);
	assert(0);
}

static zbx_uint64_t	mem_proper_alloc_size(zbx_uint64_t size)
{
	if (size >= MEM_MIN_ALLOC)
		return size + ((8 - (size & 7)) & 7);	/* allocate in multiples of 8... */
	else
		return MEM_MIN_ALLOC;			/* ...and at least MEM_MIN_ALLOC */
}

static int	mem_bucket_by_size(zbx_uint64_t size)
{
	if (size < MEM_MIN_BUCKET_SIZE)
		return 0;
	if (size < MEM_MAX_BUCKET_SIZE)
		return (size - MEM_MIN_BUCKET_SIZE) >> 3;
	return MEM_BUCKET_COUNT - 1;
}

static void	mem_set_chunk_size(void *chunk, zbx_uint64_t size)
{
	*(zbx_uint64_t *)chunk = size;
	*(zbx_uint64_t *)((char *)chunk + MEM_SIZE_FIELD + size) = size;
}

static void	mem_set_used_chunk_size(void *chunk, zbx_uint64_t size)
{
	*(zbx_uint64_t *)chunk = MEM_FLG_USED | size;
	*(zbx_uint64_t *)((char *)chunk + MEM_SIZE_FIELD + size) = MEM_FLG_USED | size;
}

static void	*mem_get_prev_chunk(void *chunk)
{
	return *(void **)((char *)chunk + MEM_SIZE_FIELD);
}

static void	mem_set_prev_chunk(void *chunk, void *prev)
{
	*(void **)((char *)chunk + MEM_SIZE_FIELD) = prev;
}

static void	*mem_get_next_chunk(void *chunk)
{
	return *(void **)((char *)chunk + MEM_SIZE_FIELD + ZBX_PTR_SIZE);
}

static void	mem_set_next_chunk(void *chunk, void *next)
{
	*(void **)((char *)chunk + MEM_SIZE_FIELD + ZBX_PTR_SIZE) = next;
}

static void	**mem_ptr_to_prev_field(void *chunk)
{
	return (NULL != chunk ? (void **)((char *)chunk + MEM_SIZE_FIELD) : NULL);
}

static void	**mem_ptr_to_next_field(void *chunk, void **first_chunk)
{
	return (NULL != chunk ? (void **)((char *)chunk + MEM_SIZE_FIELD + ZBX_PTR_SIZE) : first_chunk);
}

static void	mem_link_chunk(zbx_mem_info_t *info, void *chunk)
{
	int	index;

	index = mem_bucket_by_size(CHUNK_SIZE(chunk));

	if (NULL != info->buckets[index])
		mem_set_prev_chunk(info->buckets[index], chunk);

	mem_set_prev_chunk(chunk, NULL);
	mem_set_next_chunk(chunk, info->buckets[index]);

	info->buckets[index] = chunk;
}

static void	mem_unlink_chunk(zbx_mem_info_t *info, void *chunk)
{
	int	index;
	void	*prev_chunk, *next_chunk;
	void	**next_in_prev_chunk, **prev_in_next_chunk;

	index = mem_bucket_by_size(CHUNK_SIZE(chunk));

	prev_chunk = mem_get_prev_chunk(chunk);
	next_chunk = mem_get_next_chunk(chunk);

	next_in_prev_chunk = mem_ptr_to_next_field(prev_chunk, &info->buckets[index]);
	prev_in_next_chunk = mem_ptr_to_prev_field(next_chunk);

	*next_in_prev_chunk = next_chunk;
	if (NULL != prev_in_next_chunk)
		*prev_in_next_chunk = prev_chunk;
}

/* private memory functions */

static void	*__mem_malloc(zbx_mem_info_t *info, zbx_uint64_t size)
{
	int		index;
	void		*chunk;
	zbx_uint64_t	chunk_size;

	size = mem_proper_alloc_size(size);

	/* try to find an appropriate chunk in special buckets */

	index = mem_bucket_by_size(size);

	while (index < MEM_BUCKET_COUNT - 1 && NULL == info->buckets[index])
		index++;

	chunk = info->buckets[index];

	if (index == MEM_BUCKET_COUNT - 1)
	{
		/* otherwise, find a chunk big enough according to first-fit strategy */

		int		counter = 0;
		zbx_uint64_t	skip_min = __UINT64_C(0xffffffffffffffff), skip_max = __UINT64_C(0);

		while (NULL != chunk && CHUNK_SIZE(chunk) < size)
		{
			counter++;
			skip_min = MIN(skip_min, CHUNK_SIZE(chunk));
			skip_max = MAX(skip_max, CHUNK_SIZE(chunk));
			chunk = mem_get_next_chunk(chunk);
		}

		/* don't log errors if malloc can return null in low memory situations */
		if (0 == info->allow_oom)
		{
			if (NULL == chunk)
			{
				zabbix_log(LOG_LEVEL_CRIT, "__mem_malloc: skipped %d asked %u skip_min %u skip_max %u",
						counter, size, skip_min, skip_max);
			}
			else if (counter >= 100)
			{
				zabbix_log(LOG_LEVEL_DEBUG, "__mem_malloc: skipped %d asked %u skip_min %u skip_max %u"
						" size %u", counter, size, skip_min, skip_max, CHUNK_SIZE(chunk));
			}
		}
	}

	if (NULL == chunk)
		return NULL;

	chunk_size = CHUNK_SIZE(chunk);
	mem_unlink_chunk(info, chunk);

	/* either use the full chunk or split it */

	if (chunk_size < size + 2 * MEM_SIZE_FIELD + MEM_MIN_ALLOC)
	{
		info->used_size += chunk_size;
		info->free_size -= chunk_size;

		mem_set_used_chunk_size(chunk, chunk_size);
	}
	else
	{
		void		*new_chunk;
		zbx_uint64_t	new_chunk_size;

		new_chunk = (void *)((char *)chunk + MEM_SIZE_FIELD + size + MEM_SIZE_FIELD);
		new_chunk_size = chunk_size - size - 2 * MEM_SIZE_FIELD;
		mem_set_chunk_size(new_chunk, new_chunk_size);
		mem_link_chunk(info, new_chunk);

		info->used_size += size;
		info->free_size -= chunk_size;
		info->free_size += new_chunk_size;

		mem_set_used_chunk_size(chunk, size);
	}

	return chunk;
}

static void	*__mem_realloc(zbx_mem_info_t *info, void *old, zbx_uint64_t size)
{
	void		*chunk, *new_chunk, *next_chunk;
	zbx_uint64_t	chunk_size, new_chunk_size;
	int		next_free;

	size = mem_proper_alloc_size(size);

	chunk = (void *)((char *)old - MEM_SIZE_FIELD);
	chunk_size = CHUNK_SIZE(chunk);

	next_chunk = (void *)((char *)chunk + MEM_SIZE_FIELD + chunk_size + MEM_SIZE_FIELD);
	next_free = (next_chunk < info->hi_bound && FREE_CHUNK(next_chunk));

	if (size <= chunk_size)
	{
		/* do not reallocate if not much is freed */
		/* we are likely to want more memory again */
		if (size > chunk_size / 4)
			return chunk;

		if (next_free)
		{
			/* merge with next chunk */

			info->used_size -= chunk_size;
			info->used_size += size;
			info->free_size += chunk_size + 2 * MEM_SIZE_FIELD;
			info->free_size -= size + 2 * MEM_SIZE_FIELD;

			new_chunk = (void *)((char *)chunk + MEM_SIZE_FIELD + size + MEM_SIZE_FIELD);
			new_chunk_size = CHUNK_SIZE(next_chunk) + (chunk_size - size);

			mem_unlink_chunk(info, next_chunk);

			mem_set_chunk_size(new_chunk, new_chunk_size);
			mem_link_chunk(info, new_chunk);

			mem_set_used_chunk_size(chunk, size);
		}
		else
		{
			/* split the current one */

			info->used_size -= chunk_size;
			info->used_size += size;
			info->free_size += chunk_size;
			info->free_size -= size + 2 * MEM_SIZE_FIELD;

			new_chunk = (void *)((char *)chunk + MEM_SIZE_FIELD + size + MEM_SIZE_FIELD);
			new_chunk_size = chunk_size - size - 2 * MEM_SIZE_FIELD;

			mem_set_chunk_size(new_chunk, new_chunk_size);
			mem_link_chunk(info, new_chunk);

			mem_set_used_chunk_size(chunk, size);
		}

		return chunk;
	}

	if (next_free && chunk_size + 2 * MEM_SIZE_FIELD + CHUNK_SIZE(next_chunk) >= size)
	{
		info->used_size -= chunk_size;
		info->free_size += chunk_size;

		chunk_size += 2 * MEM_SIZE_FIELD + CHUNK_SIZE(next_chunk);

		mem_unlink_chunk(info, next_chunk);

		/* either use the full next_chunk or split it */

		if (chunk_size < size + 2 * MEM_SIZE_FIELD + MEM_MIN_ALLOC)
		{
			info->used_size += chunk_size;
			info->free_size -= chunk_size;

			mem_set_used_chunk_size(chunk, chunk_size);
		}
		else
		{
			new_chunk = (void *)((char *)chunk + MEM_SIZE_FIELD + size + MEM_SIZE_FIELD);
			new_chunk_size = chunk_size - size - 2 * MEM_SIZE_FIELD;
			mem_set_chunk_size(new_chunk, new_chunk_size);
			mem_link_chunk(info, new_chunk);

			info->used_size += size;
			info->free_size -= chunk_size;
			info->free_size += new_chunk_size;

			mem_set_used_chunk_size(chunk, size);
		}

		return chunk;
	}
	else if (NULL != (new_chunk = __mem_malloc(info, size)))
	{
		memcpy((char *)new_chunk + MEM_SIZE_FIELD, (char *)chunk + MEM_SIZE_FIELD, chunk_size);

		__mem_free(info, old);

		return new_chunk;
	}
	else
	{
		void	*tmp = NULL;

		/* check if there would be enough space if the current chunk */
		/* would be freed before allocating a new one                */
		new_chunk_size = chunk_size;

		if (0 != next_free)
			new_chunk_size += CHUNK_SIZE(next_chunk) + 2 * MEM_SIZE_FIELD;

		if (info->lo_bound < chunk && FREE_CHUNK((char *)chunk - MEM_SIZE_FIELD))
			new_chunk_size += CHUNK_SIZE((char *)chunk - MEM_SIZE_FIELD) + 2 * MEM_SIZE_FIELD;

		if (size > new_chunk_size)
			return NULL;

		tmp = zbx_malloc(tmp, chunk_size);

		memcpy(tmp, (char *)chunk + MEM_SIZE_FIELD, chunk_size);

		__mem_free(info, old);

		if (NULL == (new_chunk = __mem_malloc(info, size)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			exit(FAIL);
		}

		memcpy((char *)new_chunk + MEM_SIZE_FIELD, tmp, chunk_size);

		zbx_free(tmp);

		return new_chunk;
	}
}

static void	__mem_free(zbx_mem_info_t *info, void *ptr)
{
	void		*chunk;
	void		*prev_chunk, *next_chunk;
	zbx_uint64_t	chunk_size;
	int		prev_free, next_free;

	chunk = (void *)((char *)ptr - MEM_SIZE_FIELD);
	chunk_size = CHUNK_SIZE(chunk);

	info->used_size -= chunk_size;
	info->free_size += chunk_size;

	/* see if we can merge with previous and next chunks */

	next_chunk = (void *)((char *)chunk + MEM_SIZE_FIELD + chunk_size + MEM_SIZE_FIELD);

	prev_free = (info->lo_bound < chunk && FREE_CHUNK((char *)chunk - MEM_SIZE_FIELD));
	next_free = (next_chunk < info->hi_bound && FREE_CHUNK(next_chunk));

	if (prev_free && next_free)
	{
		info->free_size += 4 * MEM_SIZE_FIELD;

		prev_chunk = (char *)chunk - MEM_SIZE_FIELD - CHUNK_SIZE((char *)chunk - MEM_SIZE_FIELD) -
				MEM_SIZE_FIELD;

		chunk_size += 4 * MEM_SIZE_FIELD + CHUNK_SIZE(prev_chunk) + CHUNK_SIZE(next_chunk);

		mem_unlink_chunk(info, prev_chunk);
		mem_unlink_chunk(info, next_chunk);

		chunk = prev_chunk;
		mem_set_chunk_size(chunk, chunk_size);
		mem_link_chunk(info, chunk);
	}
	else if (prev_free)
	{
		info->free_size += 2 * MEM_SIZE_FIELD;

		prev_chunk = (void *)((char *)chunk - MEM_SIZE_FIELD - CHUNK_SIZE((char *)chunk - MEM_SIZE_FIELD) -
				MEM_SIZE_FIELD);

		chunk_size += 2 * MEM_SIZE_FIELD + CHUNK_SIZE(prev_chunk);

		mem_unlink_chunk(info, prev_chunk);

		chunk = prev_chunk;
		mem_set_chunk_size(chunk, chunk_size);
		mem_link_chunk(info, chunk);
	}
	else if (next_free)
	{
		info->free_size += 2 * MEM_SIZE_FIELD;

		chunk_size += 2 * MEM_SIZE_FIELD + CHUNK_SIZE(next_chunk);

		mem_unlink_chunk(info, next_chunk);

		mem_set_chunk_size(chunk, chunk_size);
		mem_link_chunk(info, chunk);
	}
	else
	{
		mem_set_chunk_size(chunk, chunk_size);
		mem_link_chunk(info, chunk);
	}
}

/* public memory interface */

void	zbx_mem_create(zbx_mem_info_t **info, key_t shm_key, int lock_name, zbx_uint64_t size,
		const char *descr, const char *param, int allow_oom)
{
	const char	*__function_name = "zbx_mem_create";

	int		shm_id, index;
	void		*base;

	descr = ZBX_NULL2STR(descr);
	param = ZBX_NULL2STR(param);

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() descr:'%s' param:'%s' size:" ZBX_FS_SIZE_T,
			__function_name, descr, param, (zbx_fs_size_t)size);

	/* allocate shared memory */

	if (4 != ZBX_PTR_SIZE && 8 != ZBX_PTR_SIZE)
	{
		zabbix_log(LOG_LEVEL_CRIT, "failed assumption about pointer size (" ZBX_FS_SIZE_T " not in {4, 8})",
				(zbx_fs_size_t)ZBX_PTR_SIZE);
		exit(FAIL);
	}

	if (!(MEM_MIN_SIZE <= size && size <= MEM_MAX_SIZE))
	{
		zabbix_log(LOG_LEVEL_CRIT, "requested size " ZBX_FS_SIZE_T " not within bounds [" ZBX_FS_UI64
				" <= size <= " ZBX_FS_UI64 "]", (zbx_fs_size_t)size, MEM_MIN_SIZE, MEM_MAX_SIZE);
		exit(FAIL);
	}

	if (-1 == (shm_id = zbx_shmget(shm_key, size)))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot allocate shared memory for %s", descr);
		exit(FAIL);
	}

	if ((void *)(-1) == (base = shmat(shm_id, NULL, 0)))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot attach shared memory for %s: %s", descr, zbx_strerror(errno));
		exit(FAIL);
	}

	/* allocate zbx_mem_info_t structure, its buckets, and description inside shared memory */

	*info = ALIGN8(base);
	(*info)->shm_id = shm_id;
	(*info)->orig_size = size;
	size -= (char *)(*info + 1) - (char *)base;

	base = (void *)(*info + 1);

	(*info)->buckets = ALIGNPTR(base);
	memset((*info)->buckets, 0, MEM_BUCKET_COUNT * ZBX_PTR_SIZE);
	size -= (char *)((*info)->buckets + MEM_BUCKET_COUNT) - (char *)base;
	base = (void *)((*info)->buckets + MEM_BUCKET_COUNT);

	zbx_strlcpy(base, descr, size);
	(*info)->mem_descr = base;
	size -= strlen(descr) + 1;
	base = (void *)((char *)base + strlen(descr) + 1);

	zbx_strlcpy(base, param, size);
	(*info)->mem_param = base;
	size -= strlen(param) + 1;
	base = (void *)((char *)base + strlen(param) + 1);

	(*info)->allow_oom = allow_oom;

	/* allocate mutex */

	if (ZBX_NO_MUTEX != lock_name)
	{
		(*info)->use_lock = 1;

		if (ZBX_MUTEX_ERROR == zbx_mutex_create_force(&((*info)->mem_lock), lock_name))
		{
			zabbix_log(LOG_LEVEL_CRIT, "cannot create mutex for %s", descr);
			exit(FAIL);
		}
	}
	else
		(*info)->use_lock = 0;

	/* prepare shared memory for further allocation by creating one big chunk */
	(*info)->lo_bound = ALIGN8(base);
	(*info)->hi_bound = ALIGN8((char *)base + size - 8);

	(*info)->total_size = (zbx_uint64_t)((char *)((*info)->hi_bound) - (char *)((*info)->lo_bound) -
			2 * MEM_SIZE_FIELD);

	index = mem_bucket_by_size((*info)->total_size);
	(*info)->buckets[index] = (*info)->lo_bound;
	mem_set_chunk_size((*info)->buckets[index], (*info)->total_size);
	mem_set_prev_chunk((*info)->buckets[index], NULL);
	mem_set_next_chunk((*info)->buckets[index], NULL);

	(*info)->used_size = 0;
	(*info)->free_size = (*info)->total_size;

	zabbix_log(LOG_LEVEL_DEBUG, "valid user addresses: [%p, %p] total size: " ZBX_FS_SIZE_T,
			(char *)(*info)->lo_bound + MEM_SIZE_FIELD,
			(char *)(*info)->hi_bound - MEM_SIZE_FIELD,
			(zbx_fs_size_t)(*info)->total_size);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

void	zbx_mem_destroy(zbx_mem_info_t *info)
{
	const char	*__function_name = "zbx_mem_destroy";

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() descr:'%s'", __function_name, info->mem_descr);

	if (info->use_lock)
		zbx_mutex_destroy(&info->mem_lock);

	if (-1 == shmctl(info->shm_id, IPC_RMID, 0))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot remove shared memory for %s: %s",
				info->mem_descr, zbx_strerror(errno));
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

void	*__zbx_mem_malloc(const char *file, int line, zbx_mem_info_t *info, const void *old, size_t size)
{
	const char	*__function_name = "zbx_mem_malloc";

	void		*chunk;

	if (NULL != old)
	{
		zabbix_log(LOG_LEVEL_CRIT, "[file:%s,line:%d] %s(): allocating already allocated memory",
				file, line, __function_name);
		exit(FAIL);
	}

	if (0 == size || size > MEM_MAX_SIZE)
	{
		zabbix_log(LOG_LEVEL_CRIT, "[file:%s,line:%d] %s(): asking for a bad number of bytes (" ZBX_FS_SIZE_T
				")", file, line, __function_name, (zbx_fs_size_t)size);
		exit(FAIL);
	}

	LOCK_INFO;

	chunk = __mem_malloc(info, size);

	UNLOCK_INFO;

	if (NULL == chunk)
	{
		if (1 == info->allow_oom)
			return NULL;

		zabbix_log(LOG_LEVEL_CRIT, "[file:%s,line:%d] %s(): out of memory (requested " ZBX_FS_SIZE_T " bytes)",
				file, line, __function_name, (zbx_fs_size_t)size);
		zabbix_log(LOG_LEVEL_CRIT, "[file:%s,line:%d] %s(): please increase %s configuration parameter",
				file, line, __function_name, info->mem_param);
		exit(FAIL);
	}

	return (void *)((char *)chunk + MEM_SIZE_FIELD);
}

void	*__zbx_mem_realloc(const char *file, int line, zbx_mem_info_t *info, void *old, size_t size)
{
	const char	*__function_name = "zbx_mem_realloc";

	void		*chunk;

	if (0 == size || size > MEM_MAX_SIZE)
	{
		zabbix_log(LOG_LEVEL_CRIT, "[file:%s,line:%d] %s(): asking for a bad number of bytes (" ZBX_FS_SIZE_T
				")", file, line, __function_name, (zbx_fs_size_t)size);
		exit(FAIL);
	}

	LOCK_INFO;

	if (NULL == old)
		chunk = __mem_malloc(info, size);
	else
		chunk = __mem_realloc(info, old, size);

	UNLOCK_INFO;

	if (NULL == chunk)
	{
		if (1 == info->allow_oom)
			return NULL;

		zabbix_log(LOG_LEVEL_CRIT, "[file:%s,line:%d] %s(): out of memory (requested " ZBX_FS_SIZE_T " bytes)",
				file, line, __function_name, (zbx_fs_size_t)size);
		zabbix_log(LOG_LEVEL_CRIT, "[file:%s,line:%d] %s(): please increase %s configuration parameter",
				file, line, __function_name, info->mem_param);
		exit(FAIL);
	}

	return (void *)((char *)chunk + MEM_SIZE_FIELD);
}

void	__zbx_mem_free(const char *file, int line, zbx_mem_info_t *info, void *ptr)
{
	const char	*__function_name = "zbx_mem_free";

	if (NULL == ptr)
	{
		zabbix_log(LOG_LEVEL_CRIT, "[file:%s,line:%d] %s(): freeing a NULL pointer",
				file, line, __function_name);
		exit(FAIL);
	}

	LOCK_INFO;

	__mem_free(info, ptr);

	UNLOCK_INFO;
}

void	zbx_mem_clear(zbx_mem_info_t *info)
{
	const char	*__function_name = "zbx_mem_clear";

	int		index;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	LOCK_INFO;

	memset(info->buckets, 0, MEM_BUCKET_COUNT * ZBX_PTR_SIZE);
	index = mem_bucket_by_size(info->total_size);
	info->buckets[index] = info->lo_bound;
	mem_set_chunk_size(info->buckets[index], info->total_size);
	mem_set_prev_chunk(info->buckets[index], NULL);
	mem_set_next_chunk(info->buckets[index], NULL);
	info->used_size = 0;
	info->free_size = info->total_size;

	UNLOCK_INFO;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

void	zbx_mem_dump_stats(zbx_mem_info_t *info)
{
	void		*chunk;
	int		index;
	zbx_uint64_t	counter, total, total_free = 0;
	zbx_uint64_t	min_size = __UINT64_C(0xffffffffffffffff), max_size = __UINT64_C(0);

	LOCK_INFO;

	zabbix_log(LOG_LEVEL_DEBUG, "=== memory statistics for %s ===", info->mem_descr);

	for (index = 0; index < MEM_BUCKET_COUNT; index++)
	{
		counter = 0;
		chunk = info->buckets[index];

		while (NULL != chunk)
		{
			counter++;
			min_size = MIN(min_size, CHUNK_SIZE(chunk));
			max_size = MAX(max_size, CHUNK_SIZE(chunk));
			chunk = mem_get_next_chunk(chunk);
		}

		if (counter > 0)
		{
			total_free += counter;
			zabbix_log(LOG_LEVEL_DEBUG, "free chunks of size %2s %3d bytes: %8d",
					index == MEM_BUCKET_COUNT - 1 ? ">=" : "",
					MEM_MIN_BUCKET_SIZE + 8 * index, counter);
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "min chunk size: %10u bytes", min_size);
	zabbix_log(LOG_LEVEL_DEBUG, "max chunk size: %10u bytes", max_size);

	total = (info->total_size - info->used_size - info->free_size) / (2 * MEM_SIZE_FIELD) + 1;
	zabbix_log(LOG_LEVEL_DEBUG, "memory of total size %u bytes fragmented into %d chunks", info->total_size, total);
	zabbix_log(LOG_LEVEL_DEBUG, "of those, %10u bytes are in %8d free chunks", info->free_size, total_free);
	zabbix_log(LOG_LEVEL_DEBUG, "of those, %10u bytes are in %8d used chunks", info->used_size, total - total_free);

	zabbix_log(LOG_LEVEL_DEBUG, "================================");

	UNLOCK_INFO;
}

size_t	zbx_mem_required_size(int chunks_num, const char *descr, const char *param)
{
	const char	*__function_name = "zbx_mem_required_size";

	size_t		size = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() size:" ZBX_FS_SIZE_T " chunks_num:%d descr:'%s' param:'%s'",
			__function_name, (zbx_fs_size_t)size, chunks_num, descr, param);

	/* shared memory of what size should we allocate so that there is a guarantee */
	/* that we will be able to get ourselves 'chunks_num' pieces of memory with a */
	/* total size of 'size', given that we also have to store 'descr' and 'param'? */

	size += 7;					/* ensure we allocate enough to 8-align zbx_mem_info_t */
	size += sizeof(zbx_mem_info_t);
	size += ZBX_PTR_SIZE - 1;			/* ensure we allocate enough to align bucket pointers */
	size += ZBX_PTR_SIZE * MEM_BUCKET_COUNT;
	size += strlen(descr) + 1;
	size += strlen(param) + 1;
	size += (MEM_SIZE_FIELD - 1) + 8;		/* ensure we allocate enough to align the first chunk */
	size += (MEM_SIZE_FIELD - 1) + 8;		/* ensure we allocate enough to align right size field */

	size += (chunks_num - 1) * MEM_SIZE_FIELD * 2;	/* each additional chunk requires 16 bytes of overhead */
	size += chunks_num * (MEM_MIN_ALLOC - 1);	/* each chunk has size of at least MEM_MIN_ALLOC bytes */

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() size:" ZBX_FS_SIZE_T, __function_name, (zbx_fs_size_t)size);

	return size;
}
