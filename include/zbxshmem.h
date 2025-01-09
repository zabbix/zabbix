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

#ifndef ZABBIX_SHMEM_H
#define ZABBIX_SHMEM_H

#include "zbxnix.h"

#define SHMEM_MIN_ALLOC	24		/* should be a multiple of 8 and at least (2 * ZBX_PTR_SIZE) */

#define ZBX_SHMEM_MIN_BUCKET_SIZE	SHMEM_MIN_ALLOC
#define SHMEM_MAX_BUCKET_SIZE		256 /* starting from this size all free chunks are put into the same bucket */
#define ZBX_SHMEM_BUCKET_COUNT		((SHMEM_MAX_BUCKET_SIZE - ZBX_SHMEM_MIN_BUCKET_SIZE) / 8 + 1)

typedef struct
{
	void		*base;
	void		**buckets;
	void		*lo_bound;
	void		*hi_bound;
	zbx_uint64_t	free_size;
	zbx_uint64_t	used_size;
	zbx_uint64_t	orig_size;
	zbx_uint64_t	total_size;
	int		shm_id;

	/* Continue execution in out of memory situation.                         */
	/* Normally allocator forces exit when it runs out of allocatable memory. */
	/* Set this flag to 1 to allow execution in out of memory situations.     */
	char		allow_oom;

	const char	*mem_descr;
	const char	*mem_param;
}
zbx_shmem_info_t;

typedef struct
{
	zbx_uint64_t	free_size;
	zbx_uint64_t	used_size;
	zbx_uint64_t	min_chunk_size;
	zbx_uint64_t	max_chunk_size;
	zbx_uint64_t	overhead;
	unsigned int	chunks_num[ZBX_SHMEM_BUCKET_COUNT];
	unsigned int	free_chunks;
	unsigned int	used_chunks;
}
zbx_shmem_stats_t;

int	zbx_shmem_create(zbx_shmem_info_t **info, zbx_uint64_t size, const char *descr, const char *param,
		int allow_oom, char **error);
int	zbx_shmem_create_min(zbx_shmem_info_t **info, zbx_uint64_t size, const char *descr, const char *param,
		int allow_oom, char **error);
void	zbx_shmem_destroy(zbx_shmem_info_t *info);

#define	zbx_shmem_malloc(info, old, size) __zbx_shmem_malloc(__FILE__, __LINE__, info, old, size)
#define	zbx_shmem_realloc(info, old, size) __zbx_shmem_realloc(__FILE__, __LINE__, info, old, size)
#define	zbx_shmem_free(info, ptr)			\
							\
do							\
{							\
	__zbx_shmem_free(__FILE__, __LINE__, info, ptr);\
	ptr = NULL;					\
}							\
while (0)

void	*__zbx_shmem_malloc(const char *file, int line, zbx_shmem_info_t *info, const void *old, size_t size);
void	*__zbx_shmem_realloc(const char *file, int line, zbx_shmem_info_t *info, void *old, size_t size);
void	__zbx_shmem_free(const char *file, int line, zbx_shmem_info_t *info, void *ptr);

void	zbx_shmem_clear(zbx_shmem_info_t *info);

void	zbx_shmem_get_stats(const zbx_shmem_info_t *info, zbx_shmem_stats_t *stats);
void	zbx_shmem_dump_stats(int level, zbx_shmem_info_t *info);

size_t		zbx_shmem_required_size(int chunks_num, const char *descr, const char *param);
zbx_uint64_t	zbx_shmem_required_chunk_size(zbx_uint64_t size);

#define ZBX_SHMEM_FUNC1_DECL_MALLOC(__prefix)				\
static void	*__prefix ## _shmem_malloc_func(void *old, size_t size)
#define ZBX_SHMEM_FUNC1_DECL_REALLOC(__prefix)				\
static void	*__prefix ## _shmem_realloc_func(void *old, size_t size)
#define ZBX_SHMEM_FUNC1_DECL_FREE(__prefix)				\
static void	__prefix ## _shmem_free_func(void *ptr)

#define ZBX_SHMEM_FUNC1_IMPL_MALLOC(__prefix, __info)			\
									\
static void	*__prefix ## _shmem_malloc_func(void *old, size_t size)	\
{									\
	return zbx_shmem_malloc(__info, old, size);			\
}

#define ZBX_SHMEM_FUNC1_IMPL_REALLOC(__prefix, __info)			\
									\
static void	*__prefix ## _shmem_realloc_func(void *old, size_t size)\
{									\
	return zbx_shmem_realloc(__info, old, size);			\
}

#define ZBX_SHMEM_FUNC1_IMPL_FREE(__prefix, __info)			\
									\
static void	__prefix ## _shmem_free_func(void *ptr)			\
{									\
	zbx_shmem_free(__info, ptr);					\
}

#define ZBX_SHMEM_FUNC_DECL(__prefix)					\
									\
ZBX_SHMEM_FUNC1_DECL_MALLOC(__prefix);					\
ZBX_SHMEM_FUNC1_DECL_REALLOC(__prefix);					\
ZBX_SHMEM_FUNC1_DECL_FREE(__prefix);

#define ZBX_SHMEM_FUNC_IMPL(__prefix, __info)				\
									\
ZBX_SHMEM_FUNC1_IMPL_MALLOC(__prefix, __info)				\
ZBX_SHMEM_FUNC1_IMPL_REALLOC(__prefix, __info)				\
ZBX_SHMEM_FUNC1_IMPL_FREE(__prefix, __info)

#endif /* ZABBIX_SHMEM_H */
