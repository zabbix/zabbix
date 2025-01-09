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

#include "zbxshmem.h"

#include "zbxstr.h"
#include "zbxnix.h"

/******************************************************************************
 *                                                                            *
 *                     Some information on memory layout                      *
 *                  ---------------------------------------                   *
 *                                                                            *
 * (*) chunk: a contiguous piece of memory that is either free or used        *
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
 *     when a chunk is used, `size' fields have SHMEM_FLG_USED bit set        *
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
 *           and next chunks are free, those will not have SHMEM_FLG_USED)    *
 *                                                                            *
 * (*) free chunks are stored in doubly-linked lists according to their sizes *
 *                                                                            *
 *     a typical situation is thus as follows (1 used chunk, 2 free chunks)   *
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
 *  (aligned)            have SHMEM_FLG_USED bit set               (aligned)  *
 *                                                                            *
 ******************************************************************************/

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

static void	mem_link_chunk(zbx_shmem_info_t *info, void *chunk);
static void	mem_unlink_chunk(zbx_shmem_info_t *info, void *chunk);

static void	*__mem_malloc(zbx_shmem_info_t *info, zbx_uint64_t size);
static void	*__mem_realloc(zbx_shmem_info_t *info, void *old, zbx_uint64_t size);
static void	__mem_free(zbx_shmem_info_t *info, void *ptr);

#define SHMEM_SIZE_FIELD	sizeof(zbx_uint64_t)

#define SHMEM_FLG_USED		((__UINT64_C(1))<<63)

#define FREE_CHUNK(ptr)		(((*(zbx_uint64_t *)(ptr)) & SHMEM_FLG_USED) == 0)
#define CHUNK_SIZE(ptr)		((*(zbx_uint64_t *)(ptr)) & ~SHMEM_FLG_USED)

#define SHMEM_MIN_SIZE		__UINT64_C(128)
#define SHMEM_MAX_SIZE		__UINT64_C(0x1000000000)	/* 64 GB */

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

	zbx_this_should_never_happen_backtrace();
	assert(0);
}

static zbx_uint64_t	mem_proper_alloc_size(zbx_uint64_t size)
{
	if (size >= SHMEM_MIN_ALLOC)
		return size + ((8 - (size & 7)) & 7);	/* allocate in multiples of 8... */
	else
		return SHMEM_MIN_ALLOC;			/* ...and at least SHMEM_MIN_ALLOC */
}

static int	mem_bucket_by_size(zbx_uint64_t size)
{
	if (size < ZBX_SHMEM_MIN_BUCKET_SIZE)
		return 0;
	if (size < SHMEM_MAX_BUCKET_SIZE)
		return (size - ZBX_SHMEM_MIN_BUCKET_SIZE) >> 3;
	return ZBX_SHMEM_BUCKET_COUNT - 1;
}

static void	mem_set_chunk_size(void *chunk, zbx_uint64_t size)
{
	*(zbx_uint64_t *)chunk = size;
	*(zbx_uint64_t *)((char *)chunk + SHMEM_SIZE_FIELD + size) = size;
}

static void	mem_set_used_chunk_size(void *chunk, zbx_uint64_t size)
{
	*(zbx_uint64_t *)chunk = SHMEM_FLG_USED | size;
	*(zbx_uint64_t *)((char *)chunk + SHMEM_SIZE_FIELD + size) = SHMEM_FLG_USED | size;
}

static void	*mem_get_prev_chunk(void *chunk)
{
	return *(void **)((char *)chunk + SHMEM_SIZE_FIELD);
}

static void	mem_set_prev_chunk(void *chunk, void *prev)
{
	*(void **)((char *)chunk + SHMEM_SIZE_FIELD) = prev;
}

static void	*mem_get_next_chunk(void *chunk)
{
	return *(void **)((char *)chunk + SHMEM_SIZE_FIELD + ZBX_PTR_SIZE);
}

static void	mem_set_next_chunk(void *chunk, void *next)
{
	*(void **)((char *)chunk + SHMEM_SIZE_FIELD + ZBX_PTR_SIZE) = next;
}

static void	**mem_ptr_to_prev_field(void *chunk)
{
	return (NULL != chunk ? (void **)((char *)chunk + SHMEM_SIZE_FIELD) : NULL);
}

static void	**mem_ptr_to_next_field(void *chunk, void **first_chunk)
{
	return (NULL != chunk ? (void **)((char *)chunk + SHMEM_SIZE_FIELD + ZBX_PTR_SIZE) : first_chunk);
}

static void	mem_link_chunk(zbx_shmem_info_t *info, void *chunk)
{
	int	index;

	index = mem_bucket_by_size(CHUNK_SIZE(chunk));

	if (NULL != info->buckets[index])
		mem_set_prev_chunk(info->buckets[index], chunk);

	mem_set_prev_chunk(chunk, NULL);
	mem_set_next_chunk(chunk, info->buckets[index]);

	info->buckets[index] = chunk;
}

static void	mem_unlink_chunk(zbx_shmem_info_t *info, void *chunk)
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

static void	*__mem_malloc(zbx_shmem_info_t *info, zbx_uint64_t size)
{
	int		index;
	void		*chunk;
	zbx_uint64_t	chunk_size;

	size = mem_proper_alloc_size(size);

	/* try to find an appropriate chunk in special buckets */

	index = mem_bucket_by_size(size);

	while (index < ZBX_SHMEM_BUCKET_COUNT - 1 && NULL == info->buckets[index])
		index++;

	chunk = info->buckets[index];

	if (index == ZBX_SHMEM_BUCKET_COUNT - 1)
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
				zabbix_log(LOG_LEVEL_CRIT, "__mem_malloc: skipped %d asked " ZBX_FS_UI64 " skip_min "
						ZBX_FS_UI64 " skip_max " ZBX_FS_UI64,
						counter, size, skip_min, skip_max);
			}
			else if (counter >= 100)
			{
				zabbix_log(LOG_LEVEL_DEBUG, "__mem_malloc: skipped %d asked " ZBX_FS_UI64 " skip_min "
						ZBX_FS_UI64 " skip_max " ZBX_FS_UI64 " size " ZBX_FS_UI64, counter,
						size, skip_min, skip_max, CHUNK_SIZE(chunk));
			}
		}
	}

	if (NULL == chunk)
		return NULL;

	chunk_size = CHUNK_SIZE(chunk);
	mem_unlink_chunk(info, chunk);

	/* either use the full chunk or split it */

	if (chunk_size < size + 2 * SHMEM_SIZE_FIELD + SHMEM_MIN_ALLOC)
	{
		info->used_size += chunk_size;
		info->free_size -= chunk_size;

		mem_set_used_chunk_size(chunk, chunk_size);
	}
	else
	{
		void		*new_chunk;
		zbx_uint64_t	new_chunk_size;

		new_chunk = (void *)((char *)chunk + SHMEM_SIZE_FIELD + size + SHMEM_SIZE_FIELD);
		new_chunk_size = chunk_size - size - 2 * SHMEM_SIZE_FIELD;
		mem_set_chunk_size(new_chunk, new_chunk_size);
		mem_link_chunk(info, new_chunk);

		info->used_size += size;
		info->free_size -= chunk_size;
		info->free_size += new_chunk_size;

		mem_set_used_chunk_size(chunk, size);
	}

	return chunk;
}

static void	*__mem_realloc(zbx_shmem_info_t *info, void *old, zbx_uint64_t size)
{
	void		*chunk, *new_chunk, *next_chunk;
	zbx_uint64_t	chunk_size, new_chunk_size;
	int		next_free;

	size = mem_proper_alloc_size(size);

	chunk = (void *)((char *)old - SHMEM_SIZE_FIELD);
	chunk_size = CHUNK_SIZE(chunk);

	next_chunk = (void *)((char *)chunk + SHMEM_SIZE_FIELD + chunk_size + SHMEM_SIZE_FIELD);
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

			info->used_size -= chunk_size - size;
			info->free_size += chunk_size - size;

			new_chunk = (void *)((char *)chunk + SHMEM_SIZE_FIELD + size + SHMEM_SIZE_FIELD);
			new_chunk_size = CHUNK_SIZE(next_chunk) + (chunk_size - size);

			mem_unlink_chunk(info, next_chunk);

			mem_set_chunk_size(new_chunk, new_chunk_size);
			mem_link_chunk(info, new_chunk);

			mem_set_used_chunk_size(chunk, size);
		}
		else
		{
			/* split the current one */

			info->used_size -= chunk_size - size;
			info->free_size += chunk_size - size - 2 * SHMEM_SIZE_FIELD;

			new_chunk = (void *)((char *)chunk + SHMEM_SIZE_FIELD + size + SHMEM_SIZE_FIELD);
			new_chunk_size = chunk_size - size - 2 * SHMEM_SIZE_FIELD;

			mem_set_chunk_size(new_chunk, new_chunk_size);
			mem_link_chunk(info, new_chunk);

			mem_set_used_chunk_size(chunk, size);
		}

		return chunk;
	}

	if (next_free && chunk_size + 2 * SHMEM_SIZE_FIELD + CHUNK_SIZE(next_chunk) >= size)
	{
		info->used_size -= chunk_size;
		info->free_size += chunk_size + 2 * SHMEM_SIZE_FIELD;

		chunk_size += 2 * SHMEM_SIZE_FIELD + CHUNK_SIZE(next_chunk);

		mem_unlink_chunk(info, next_chunk);

		/* either use the full next_chunk or split it */

		if (chunk_size < size + 2 * SHMEM_SIZE_FIELD + SHMEM_MIN_ALLOC)
		{
			info->used_size += chunk_size;
			info->free_size -= chunk_size;

			mem_set_used_chunk_size(chunk, chunk_size);
		}
		else
		{
			new_chunk = (void *)((char *)chunk + SHMEM_SIZE_FIELD + size + SHMEM_SIZE_FIELD);
			new_chunk_size = chunk_size - size - 2 * SHMEM_SIZE_FIELD;
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
		memcpy((char *)new_chunk + SHMEM_SIZE_FIELD, (char *)chunk + SHMEM_SIZE_FIELD, chunk_size);

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
			new_chunk_size += CHUNK_SIZE(next_chunk) + 2 * SHMEM_SIZE_FIELD;

		if (info->lo_bound < chunk && FREE_CHUNK((char *)chunk - SHMEM_SIZE_FIELD))
			new_chunk_size += CHUNK_SIZE((char *)chunk - SHMEM_SIZE_FIELD) + 2 * SHMEM_SIZE_FIELD;

		if (size > new_chunk_size)
			return NULL;

		tmp = zbx_malloc(tmp, chunk_size);

		memcpy(tmp, (char *)chunk + SHMEM_SIZE_FIELD, chunk_size);

		__mem_free(info, old);

		if (NULL == (new_chunk = __mem_malloc(info, size)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			exit(EXIT_FAILURE);
		}

		memcpy((char *)new_chunk + SHMEM_SIZE_FIELD, tmp, chunk_size);

		zbx_free(tmp);

		return new_chunk;
	}
}

static void	__mem_free(zbx_shmem_info_t *info, void *ptr)
{
	void		*chunk;
	void		*prev_chunk, *next_chunk;
	zbx_uint64_t	chunk_size;
	int		prev_free, next_free;

	chunk = (void *)((char *)ptr - SHMEM_SIZE_FIELD);
	chunk_size = CHUNK_SIZE(chunk);

	info->used_size -= chunk_size;
	info->free_size += chunk_size;

	/* see if we can merge with previous and next chunks */

	next_chunk = (void *)((char *)chunk + SHMEM_SIZE_FIELD + chunk_size + SHMEM_SIZE_FIELD);

	prev_free = (info->lo_bound < chunk && FREE_CHUNK((char *)chunk - SHMEM_SIZE_FIELD));
	next_free = (next_chunk < info->hi_bound && FREE_CHUNK(next_chunk));

	if (prev_free && next_free)
	{
		info->free_size += 4 * SHMEM_SIZE_FIELD;

		prev_chunk = (char *)chunk - SHMEM_SIZE_FIELD - CHUNK_SIZE((char *)chunk - SHMEM_SIZE_FIELD) -
				SHMEM_SIZE_FIELD;

		chunk_size += 4 * SHMEM_SIZE_FIELD + CHUNK_SIZE(prev_chunk) + CHUNK_SIZE(next_chunk);

		mem_unlink_chunk(info, prev_chunk);
		mem_unlink_chunk(info, next_chunk);

		chunk = prev_chunk;
		mem_set_chunk_size(chunk, chunk_size);
		mem_link_chunk(info, chunk);
	}
	else if (prev_free)
	{
		info->free_size += 2 * SHMEM_SIZE_FIELD;

		prev_chunk = (void *)((char *)chunk - SHMEM_SIZE_FIELD - CHUNK_SIZE((char *)chunk - SHMEM_SIZE_FIELD) -
				SHMEM_SIZE_FIELD);

		chunk_size += 2 * SHMEM_SIZE_FIELD + CHUNK_SIZE(prev_chunk);

		mem_unlink_chunk(info, prev_chunk);

		chunk = prev_chunk;
		mem_set_chunk_size(chunk, chunk_size);
		mem_link_chunk(info, chunk);
	}
	else if (next_free)
	{
		info->free_size += 2 * SHMEM_SIZE_FIELD;

		chunk_size += 2 * SHMEM_SIZE_FIELD + CHUNK_SIZE(next_chunk);

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

int	zbx_shmem_create(zbx_shmem_info_t **info, zbx_uint64_t size, const char *descr, const char *param,
		int allow_oom, char **error)
{
	int	shm_id, index, ret = FAIL;
	void	*base;

	descr = ZBX_NULL2STR(descr);
	param = ZBX_NULL2STR(param);

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() param:'%s' size:" ZBX_FS_SIZE_T, __func__, param, (zbx_fs_size_t)size);

	/* allocate shared memory */

	if (4 != ZBX_PTR_SIZE && 8 != ZBX_PTR_SIZE)
	{
		*error = zbx_dsprintf(*error, "failed assumption about pointer size (" ZBX_FS_SIZE_T " not in {4, 8})",
				(zbx_fs_size_t)ZBX_PTR_SIZE);
		goto out;
	}

	if (!(SHMEM_MIN_SIZE <= size && size <= SHMEM_MAX_SIZE))
	{
		*error = zbx_dsprintf(*error, "requested size " ZBX_FS_UI64 " not within bounds [" ZBX_FS_UI64
				" <= size <= " ZBX_FS_UI64 "]", size, SHMEM_MIN_SIZE, SHMEM_MAX_SIZE);
		goto out;
	}

	if (-1 == (shm_id = shmget(IPC_PRIVATE, size, 0600)))
	{
		*error = zbx_dsprintf(*error, "cannot get private shared memory of size " ZBX_FS_SIZE_T " for %s: %s",
				(zbx_fs_size_t)size, descr, zbx_strerror(errno));
		goto out;
	}

	if ((void *)(-1) == (base = shmat(shm_id, NULL, 0)))
	{
		*error = zbx_dsprintf(*error, "cannot attach shared memory for %s: %s", descr, zbx_strerror(errno));
		goto out;
	}

	if (-1 == shmctl(shm_id, IPC_RMID, NULL))
		zbx_error("cannot mark shared memory %d for destruction: %s", shm_id, zbx_strerror(errno));

	ret = SUCCEED;

	/* allocate zbx_shmem_info_t structure, its buckets, and description inside shared memory */

	*info = (zbx_shmem_info_t *)ALIGN8(base);
	(*info)->base = base;
	(*info)->shm_id = shm_id;
	(*info)->orig_size = size;
	size -= (char *)(*info + 1) - (char *)base;

	base = (void *)(*info + 1);

	(*info)->buckets = (void **)ALIGNPTR(base);
	memset((*info)->buckets, 0, ZBX_SHMEM_BUCKET_COUNT * ZBX_PTR_SIZE);
	size -= (char *)((*info)->buckets + ZBX_SHMEM_BUCKET_COUNT) - (char *)base;
	base = (void *)((*info)->buckets + ZBX_SHMEM_BUCKET_COUNT);

	zbx_strlcpy((char *)base, descr, size);
	(*info)->mem_descr = (char *)base;
	size -= strlen(descr) + 1;
	base = (void *)((char *)base + strlen(descr) + 1);

	zbx_strlcpy((char *)base, param, size);
	(*info)->mem_param = (char *)base;
	size -= strlen(param) + 1;
	base = (void *)((char *)base + strlen(param) + 1);

	(*info)->allow_oom = allow_oom;

	/* prepare shared memory for further allocation by creating one big chunk */
	(*info)->lo_bound = ALIGN8(base);
	(*info)->hi_bound = ALIGN8((char *)base + size - 8);

	(*info)->total_size = (zbx_uint64_t)((char *)((*info)->hi_bound) - (char *)((*info)->lo_bound) -
			2 * SHMEM_SIZE_FIELD);

	index = mem_bucket_by_size((*info)->total_size);
	(*info)->buckets[index] = (*info)->lo_bound;
	mem_set_chunk_size((*info)->buckets[index], (*info)->total_size);
	mem_set_prev_chunk((*info)->buckets[index], NULL);
	mem_set_next_chunk((*info)->buckets[index], NULL);

	(*info)->used_size = 0;
	(*info)->free_size = (*info)->total_size;

	zabbix_log(LOG_LEVEL_DEBUG, "valid user addresses: [%p, %p] total size: " ZBX_FS_SIZE_T,
			(void *)((char *)(*info)->lo_bound + SHMEM_SIZE_FIELD),
			(void *)((char *)(*info)->hi_bound - SHMEM_SIZE_FIELD),
			(zbx_fs_size_t)(*info)->total_size);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: allocate the required shared memory size                          *
 *                                                                            *
 * Return value: SUCCEED - the memory was allocated successfully              *
 *               FAIL - otherwise                                             *
 *                                                                            *
 * Comments: When allocating shared memory with default zbx_shmem_create()    *
 *           function the available memory will reduced by the allocator      *
 *           overhead. This function estimates the overhead and requests      *
 *           enough memory so the available memory is greater or equal to the *
 *           requested size.                                                  *
 *                                                                            *
 ******************************************************************************/
int	zbx_shmem_create_min(zbx_shmem_info_t **info, zbx_uint64_t size, const char *descr, const char *param,
		int allow_oom, char **error)
{
	void	*base = NULL;

	descr = ZBX_NULL2STR(descr);
	param = ZBX_NULL2STR(param);

	base = (void *)((zbx_shmem_info_t *)(base) + 1);
	base = ALIGNPTR(base);
	base = (void *)((void **)base + ZBX_SHMEM_BUCKET_COUNT);
	base = (void *)((char *)base + strlen(descr) + 1);
	base = (void *)((char *)base + strlen(param) + 1);
	base = ALIGN8(base);

	size += (size_t )base;

	size += 8;
	size += 2 * SHMEM_SIZE_FIELD;

	return zbx_shmem_create(info, size, descr, param, allow_oom, error);
}

void	zbx_shmem_destroy(zbx_shmem_info_t *info)
{
	(void)shmdt(info->base);
}

void	*__zbx_shmem_malloc(const char *file, int line, zbx_shmem_info_t *info, const void *old, size_t size)
{
	void	*chunk;

	if (NULL != old)
	{
		zabbix_log(LOG_LEVEL_CRIT, "[file:%s,line:%d] %s(): allocating already allocated memory",
				file, line, __func__);
		exit(EXIT_FAILURE);
	}

	if (0 == size || size > SHMEM_MAX_SIZE)
	{
		zabbix_log(LOG_LEVEL_CRIT, "[file:%s,line:%d] %s(): asking for a bad number of bytes (" ZBX_FS_SIZE_T
				")", file, line, __func__, (zbx_fs_size_t)size);
		exit(EXIT_FAILURE);
	}

	chunk = __mem_malloc(info, size);

	if (NULL == chunk)
	{
		if (1 == info->allow_oom)
			return NULL;

		zbx_shmem_dump_stats(LOG_LEVEL_CRIT, info);
		zbx_backtrace();

		zabbix_log(LOG_LEVEL_CRIT, "[file:%s,line:%d] %s(): out of memory (requested " ZBX_FS_SIZE_T " bytes)",
				file, line, __func__, (zbx_fs_size_t)size);
		zabbix_log(LOG_LEVEL_CRIT, "[file:%s,line:%d] %s(): please increase %s configuration parameter",
				file, line, __func__, info->mem_param);
		exit(EXIT_FAILURE);
	}

	return (void *)((char *)chunk + SHMEM_SIZE_FIELD);
}

void	*__zbx_shmem_realloc(const char *file, int line, zbx_shmem_info_t *info, void *old, size_t size)
{
	void	*chunk;

	if (0 == size || size > SHMEM_MAX_SIZE)
	{
		zabbix_log(LOG_LEVEL_CRIT, "[file:%s,line:%d] %s(): asking for a bad number of bytes (" ZBX_FS_SIZE_T
				")", file, line, __func__, (zbx_fs_size_t)size);
		exit(EXIT_FAILURE);
	}

	if (NULL == old)
		chunk = __mem_malloc(info, size);
	else
		chunk = __mem_realloc(info, old, size);

	if (NULL == chunk)
	{
		if (1 == info->allow_oom)
			return NULL;

		zbx_shmem_dump_stats(LOG_LEVEL_CRIT, info);
		zbx_backtrace();

		zabbix_log(LOG_LEVEL_CRIT, "[file:%s,line:%d] %s(): out of memory (requested " ZBX_FS_SIZE_T " bytes)",
				file, line, __func__, (zbx_fs_size_t)size);
		zabbix_log(LOG_LEVEL_CRIT, "[file:%s,line:%d] %s(): please increase %s configuration parameter",
				file, line, __func__, info->mem_param);
		exit(EXIT_FAILURE);
	}

	return (void *)((char *)chunk + SHMEM_SIZE_FIELD);
}

void	__zbx_shmem_free(const char *file, int line, zbx_shmem_info_t *info, void *ptr)
{
	if (NULL == ptr)
	{
		zabbix_log(LOG_LEVEL_CRIT, "[file:%s,line:%d] %s(): freeing a NULL pointer", file, line, __func__);
		exit(EXIT_FAILURE);
	}

	__mem_free(info, ptr);
}

void	zbx_shmem_clear(zbx_shmem_info_t *info)
{
	int	index;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	memset(info->buckets, 0, ZBX_SHMEM_BUCKET_COUNT * ZBX_PTR_SIZE);
	index = mem_bucket_by_size(info->total_size);
	info->buckets[index] = info->lo_bound;
	mem_set_chunk_size(info->buckets[index], info->total_size);
	mem_set_prev_chunk(info->buckets[index], NULL);
	mem_set_next_chunk(info->buckets[index], NULL);
	info->used_size = 0;
	info->free_size = info->total_size;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

void	zbx_shmem_get_stats(const zbx_shmem_info_t *info, zbx_shmem_stats_t *stats)
{
	void		*chunk;
	int		i;
	zbx_uint64_t	counter;

	stats->free_chunks = 0;
	stats->max_chunk_size = __UINT64_C(0);
	stats->min_chunk_size = __UINT64_C(0xffffffffffffffff);

	for (i = 0; i < ZBX_SHMEM_BUCKET_COUNT; i++)
	{
		counter = 0;
		chunk = info->buckets[i];

		while (NULL != chunk)
		{
			counter++;
			stats->min_chunk_size = MIN(stats->min_chunk_size, CHUNK_SIZE(chunk));
			stats->max_chunk_size = MAX(stats->max_chunk_size, CHUNK_SIZE(chunk));
			chunk = mem_get_next_chunk(chunk);
		}

		stats->free_chunks += counter;
		stats->chunks_num[i] = counter;
	}

	if (__UINT64_C(0xffffffffffffffff) == stats->min_chunk_size)
		stats->min_chunk_size = 0;

	stats->overhead = info->total_size - info->used_size - info->free_size;
	stats->used_chunks = stats->overhead / (2 * SHMEM_SIZE_FIELD) + 1 - stats->free_chunks;
	stats->free_size = info->free_size;
	stats->used_size = info->used_size;
}

void	zbx_shmem_dump_stats(int level, zbx_shmem_info_t *info)
{
	zbx_shmem_stats_t	stats;
	int		i;

	zbx_shmem_get_stats(info, &stats);

	zabbix_log(level, "=== memory statistics for %s ===", info->mem_descr);

	for (i = 0; i < ZBX_SHMEM_BUCKET_COUNT; i++)
	{
		if (0 == stats.chunks_num[i])
			continue;

		zabbix_log(level, "free chunks of size %2s %3d bytes: %8u", i == ZBX_SHMEM_BUCKET_COUNT - 1 ? ">=" : "",
			ZBX_SHMEM_MIN_BUCKET_SIZE + 8 * i, stats.chunks_num[i]);
	}

	zabbix_log(level, "min chunk size: %10llu bytes", (unsigned long long)stats.min_chunk_size);
	zabbix_log(level, "max chunk size: %10llu bytes", (unsigned long long)stats.max_chunk_size);

	zabbix_log(level, "memory of total size %llu bytes fragmented into %llu chunks",
			(unsigned long long)stats.free_size + stats.used_size,
			(unsigned long long)stats.free_chunks + stats.used_chunks);
	zabbix_log(level, "of those, %10llu bytes are in %8llu free chunks",
			(unsigned long long)stats.free_size, (unsigned long long)stats.free_chunks);
	zabbix_log(level, "of those, %10llu bytes are in %8llu used chunks",
			(unsigned long long)stats.used_size, (unsigned long long)stats.used_chunks);
	zabbix_log(level, "of those, %10llu bytes are used by allocation overhead",
			(unsigned long long)stats.overhead);

	zabbix_log(level, "================================");
}

size_t	zbx_shmem_required_size(int chunks_num, const char *descr, const char *param)
{
	size_t	size = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() size:" ZBX_FS_SIZE_T " chunks_num:%d descr:'%s' param:'%s'",
			__func__, (zbx_fs_size_t)size, chunks_num, descr, param);

	/* shared memory of what size should we allocate so that there is a guarantee */
	/* that we will be able to get ourselves 'chunks_num' pieces of memory with a */
	/* total size of 'size', given that we also have to store 'descr' and 'param'? */

	size += 7;					/* ensure we allocate enough to 8-align zbx_shmem_info_t */
	size += sizeof(zbx_shmem_info_t);
	size += ZBX_PTR_SIZE - 1;			/* ensure we allocate enough to align bucket pointers */
	size += ZBX_PTR_SIZE * ZBX_SHMEM_BUCKET_COUNT;
	size += strlen(descr) + 1;
	size += strlen(param) + 1;
	size += (SHMEM_SIZE_FIELD - 1) + 8;		/* ensure we allocate enough to align the first chunk */
	size += (SHMEM_SIZE_FIELD - 1) + 8;		/* ensure we allocate enough to align right size field */

	size += (chunks_num - 1) * SHMEM_SIZE_FIELD * 2;/* each additional chunk requires 16 bytes of overhead */
	size += chunks_num * (SHMEM_MIN_ALLOC - 1);	/* each chunk has size of at least SHMEM_MIN_ALLOC bytes */

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() size:" ZBX_FS_SIZE_T, __func__, (zbx_fs_size_t)size);

	return size;
}

zbx_uint64_t	zbx_shmem_required_chunk_size(zbx_uint64_t size)
{
	if (0 == size)
		return 0;

	return mem_proper_alloc_size(size) + SHMEM_SIZE_FIELD * 2;
}
