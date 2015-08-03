/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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
#include "log.h"
#include "mutexs.h"
#include "stats.h"
#include "ipc.h"
#include "procstat.h"

#ifdef ZBX_PROCSTAT_COLLECTOR

/*
 * The process CPU statistics are stored using the following memory layout.
 *
 *  .--------------------------------------.
 *  | header                               |
 *  | ------------------------------------ |
 *  | process cpu utilization queries      |
 *  | and historical data                  |
 *  | ------------------------------------ |
 *  | free space                           |
 *  | ------------------------------------ |
 *  | per process cpu utilization snapshot |
 *  '--------------------------------------'
 *
 * Because the shared memory can be resized by other processes instead of
 * using pointers (when allocating strings, building single linked lists)
 * the memory offsets from the beginning of shared memory segment are used.
 * 0 offset is interpreted similarly to NULL pointer.
 *
 * Currently integer values are used to store offsets to internally allocated
 * memory which leads to 2GB total size limit.
 *
 * During every data collection cycle collector does the following:
 * 1) acquires list of pids for cpu utilization queries
 * 2) reads total cpu utilization snapshot for the processes
 * 3) calculates cpu utilization difference by comparing with previous snapshot
 * 4) updates cpu utilization values for queries.
 * 5) saves the last cpu utilization snapshot
 *
 * Internal workings.
 *
 * Initialisation.
 * * procstat_init() initialises procstat dshm structure but doesn't allocate memory from the system
 *   (zbx_dshm_create() called with size 0).
 * * the first call of procstat_add() allocates the shared memory for the header, the first query and
 *   cpu utilization snapshots via call to zbx_dshm_reserve().
 * * The header is initialised in procstat_copy_data() which is called back from zbx_dshm_reserve().
 *
 * Memory allocation within dshm.
 * * Ensure that memory segment has enough free space with procstat_dshm_has_enough_space() before
 *   allocating space within segment with procstat_alloc() or functions that use it.
 * * Reserve more space if needed with zbx_dshm_reserve().
 */

/* the main collector data */
extern ZBX_COLLECTOR_DATA	*collector;

/* local reference to the procstat shared memory */
static zbx_dshm_ref_t	procstat_ref;

typedef struct
{
	/* the number of processes in process statistics snapshot */
	int	pids_num;

	/* a linked list of active queries (offset of the first active query) */
	int	active_queries;

	/* a linked list of free queries (offset of the first free query) */
	int	free_queries;

	/* the total size of the allocated queries and strings */
	int	size_allocated;

	/* the total shared memory segment size */
	size_t	size;
}
zbx_procstat_header_t;

#define PROCSTAT_NULL_OFFSET		0

#define PROCSTAT_ALIGNED_SIZE(size)	(((size) + 7) & ~7)

#define PROCSTAT_ALIGNED_HEADER_SIZE	PROCSTAT_ALIGNED_SIZE(sizeof(zbx_procstat_header_t))

#define PROCSTAT_PTR(base, offset)	((char *)base + offset)

#define PROCSTAT_PTR_NULL(base, offset)									\
		(PROCSTAT_NULL_OFFSET == offset ? NULL : PROCSTAT_PTR(base, offset))

#define PROCSTAT_QUERY_FIRST(base)									\
		(zbx_procstat_query_t*)PROCSTAT_PTR_NULL(base, ((zbx_procstat_header_t *)base)->active_queries)

#define PROCSTAT_QUERY_NEXT(base, query)								\
		(zbx_procstat_query_t*)PROCSTAT_PTR_NULL(base, query->next)

#define PROCSTAT_SNAPSHOT(base)										\
		((zbx_procstat_util_t *)((char *)base + ((zbx_procstat_header_t *)base)->size - 	\
				((zbx_procstat_header_t *)base)->pids_num * sizeof(zbx_procstat_util_t)))

#define PROCSTAT_OFFSET(base, ptr) ((char *)ptr - (char *)base)

/* data sample collected every second for the process cpu utilization queries */
typedef struct
{
	zbx_uint64_t	utime;
	zbx_uint64_t	stime;
	zbx_timespec_t	timestamp;
}
zbx_procstat_data_t;

/* process cpu utilization query */
typedef struct
{
	/* the process attributes */
	size_t				procname;
	size_t				username;
	size_t				cmdline;
	zbx_uint64_t			flags;

	/* the index of first (oldest) entry in the history data */
	int				h_first;

	/* the number of entries in the history data */
	int				h_count;

	/* the last access time (request from server) */
	int				last_accessed;

	/* increasing id for every data collection run, used to       */
	/* identify queries that are processed during data collection */
	int				runid;

	/* error code */
	int				error;

	/* offset (from segment beginning) of the next process query */
	int				next;

	/* the cpu utilization history data (ring buffer) */
	zbx_procstat_data_t		h_data[MAX_COLLECTOR_HISTORY];
}
zbx_procstat_query_t;

/* process cpu utilization query data */
typedef struct
{
	/* process attributes */
	const char		*procname;
	const char		*username;
	const char		*cmdline;
	zbx_uint64_t		flags;

	/* error code */
	int			error;

	/* process cpu utilization */
	zbx_uint64_t		utime;
	zbx_uint64_t		stime;

	/* vector of pids matching the process attributes */
	zbx_vector_uint64_t	pids;
}
zbx_procstat_query_data_t;

/* external functions used by procstat collector */
int	zbx_proc_get_pids(const char *procname, const char *username, const char *cmdline, zbx_uint64_t flags,
		zbx_vector_uint64_t *pids);
void	zbx_proc_get_stats(zbx_procstat_util_t *procs, int procs_num);

/******************************************************************************
 *                                                                            *
 * Function: procstat_dshm_has_enough_space                                   *
 *                                                                            *
 * Purpose: check if the procstat shared memory segment has at least          *
 *          the specified amount of free bytes in the middle of the segment   *
 *                                                                            *
 * Parameters: base - [IN] the procstat shared memory segment                 *
 *             size - [IN] number of free bytes needed                        *
 *                                                                            *
 * Return value: SUCCEED - sufficient amount of bytes are available           *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	procstat_dshm_has_enough_space(void *base, size_t size)
{
	zbx_procstat_header_t   *header = (zbx_procstat_header_t *)base;

	if (header->size > size + header->size_allocated + header->pids_num * sizeof(zbx_procstat_util_t))
		return SUCCEED;

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: procstat_alloc                                                   *
 *                                                                            *
 * Purpose: allocates memory in the shared memory segment,                    *
 *          calls exit() if segment is too small                              *
 *                                                                            *
 * Parameters: base - [IN] the procstat shared memory segment                 *
 *             size - [IN] the number of bytes to allocate                    *
 *                                                                            *
 * Return value: The offset of allocated data from the beginning of segment   *
 *               (positive value).                                            *
 *                                                                            *
 ******************************************************************************/
static int	procstat_alloc(void *base, size_t size)
{
	zbx_procstat_header_t	*header = (zbx_procstat_header_t *)base;
	int			offset;

	size = PROCSTAT_ALIGNED_SIZE(size);

	if (FAIL == procstat_dshm_has_enough_space(header, size))
	{
		THIS_SHOULD_NEVER_HAPPEN;
		exit(EXIT_FAILURE);
	}

	offset = header->size_allocated;
	header->size_allocated += size;

	return offset;
}

/******************************************************************************
 *                                                                            *
 * Function: procstat_alloc_query                                             *
 *                                                                            *
 * Purpose: allocates memory in the shared memory segment to store process    *
 *          cpu utilization query (calls exit() if segment is too small)      *
 *                                                                            *
 * Parameters: base - [IN] the procstat shared memory segment                 *
 *                                                                            *
 * Return value: The offset of allocated data from the end of data segment    *
 *               (positive value).                                            *
 *               PROCSTAT_NULL_OFFSET if there was not enough free space.     *
 *                                                                            *
 ******************************************************************************/
static int	procstat_alloc_query(void *base)
{
	zbx_procstat_header_t	*header = (zbx_procstat_header_t *)base;
	int			offset = header->free_queries;

	if (PROCSTAT_NULL_OFFSET != offset)
	{
		zbx_procstat_query_t	*query;

		query = (zbx_procstat_query_t *)PROCSTAT_PTR(base, offset);
		header->free_queries = query->next;
	}
	else
	{
		offset = procstat_alloc(base, sizeof(zbx_procstat_query_t));
	}

	return offset;
}

/******************************************************************************
 *                                                                            *
 * Function: procstat_free_query                                              *
 *                                                                            *
 * Purpose: frees process cpu utilization query                               *
 *                                                                            *
 * Parameters: base  - [IN] the procstat shared memory segment                *
 *             query - [IN] the process cpu utilization query to remove       *
 *                                                                            *
 * Comments: The query will be simply moved from 'active' list to 'free' list *
 *                                                                            *
 ******************************************************************************/
static void	procstat_free_query(void *base, zbx_procstat_query_t *query)
{
	zbx_procstat_header_t	*header = (zbx_procstat_header_t *)base;

	if (header->active_queries == PROCSTAT_OFFSET(base, query))
		header->active_queries = query->next;

	query->next = header->free_queries;
	header->free_queries = PROCSTAT_OFFSET(base, query);
}

/******************************************************************************
 *                                                                            *
 * Function: procstat_strdup                                                  *
 *                                                                            *
 * Purpose: allocates required memory in procstat memory segment and copies   *
 *          the specified string (calls exit() if segment is too small)       *
 *                                                                            *
 * Parameters: base - [IN] the procstat shared memory segment                 *
 *             str  - [IN] the string to copy                                 *
 *                                                                            *
 * Return value: The offset to allocated data counting from the beginning     *
 *               of data segment.                                             *
 *               0 if the source string is NULL or the shared memory segment  *
 *               does not have enough free space.                             *
 *                                                                            *
 ******************************************************************************/
static size_t	procstat_strdup(void *base, const char *str)
{
	size_t	len, offset;

	if (NULL == str)
		return PROCSTAT_NULL_OFFSET;

	len = strlen(str) + 1;

	offset = procstat_alloc(base, len);
		memcpy(PROCSTAT_PTR(base, offset), str, len);

	return offset;
}

/******************************************************************************
 *                                                                            *
 * Function: procstat_reattach                                                *
 *                                                                            *
 * Purpose: reattaches the procstat_ref to the shared memory segment if it    *
 *          was 'resized' (a new segment created and the old data copied) by  *
 *          other process.                                                    *
 *                                                                            *
 * Comments: This function logs critical error and exits in the case of       *
 *           shared memory segement operation failure.                        *
 *                                                                            *
 ******************************************************************************/
static void	procstat_reattach()
{
	char	*errmsg = NULL;

	if (FAIL == zbx_dshm_validate_ref(&collector->procstat, &procstat_ref, &errmsg))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot validate process data collector reference: %s", errmsg);
		zbx_free(errmsg);
		exit(EXIT_FAILURE);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: procstat_copy_data                                               *
 *                                                                            *
 * Purpose: copies procstat data to a new shared memory segment               *
 *                                                                            *
 * Parameters: dst      - [OUT] the destination segment                       *
 *             size_dst - [IN] the size of destination segment                *
 *             src      - [IN] the source segment                             *
 *                                                                            *
 ******************************************************************************/
static void	procstat_copy_data(void *dst, size_t size_dst, const void *src)
{
	const char		*__function_name = "procstat_copy_data";

	int			offset, size_snapshot, *query_offset;
	zbx_procstat_header_t	*hsrc = (zbx_procstat_header_t *)src;
	zbx_procstat_header_t	*hdst = (zbx_procstat_header_t *)dst;
	zbx_procstat_query_t	*qsrc, *qdst = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	hdst->size = size_dst;
	hdst->size_allocated = PROCSTAT_ALIGNED_HEADER_SIZE;
	hdst->free_queries = PROCSTAT_NULL_OFFSET;
	hdst->active_queries = PROCSTAT_NULL_OFFSET;

	if (NULL != src)
	{
		query_offset = &hdst->active_queries;

		/* copy queries */
		for (qsrc = PROCSTAT_QUERY_FIRST(src); NULL != qsrc; qsrc = PROCSTAT_QUERY_NEXT(src, qsrc))
		{
			/* the new shared memory segment must have enough space */
			offset = procstat_alloc_query(dst);

			qdst = (zbx_procstat_query_t *)PROCSTAT_PTR(dst, offset);

			memcpy(qdst, qsrc, sizeof(zbx_procstat_query_t));

			qdst->procname = procstat_strdup(dst, PROCSTAT_PTR_NULL(src, qsrc->procname));
			qdst->username = procstat_strdup(dst, PROCSTAT_PTR_NULL(src, qsrc->username));
			qdst->cmdline = procstat_strdup(dst, PROCSTAT_PTR_NULL(src, qsrc->cmdline));

			*query_offset = offset;
			query_offset = &qdst->next;
		}

		/* copy process cpu utilization snapshot */
		hdst->pids_num = hsrc->pids_num;
		size_snapshot = hsrc->pids_num * sizeof(zbx_procstat_util_t);
		memcpy((char *)dst + hdst->size - size_snapshot, (char *)src + hsrc->size - size_snapshot,
				size_snapshot);
	}
	else
		hdst->pids_num = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: procstat_running                                                 *
 *                                                                            *
 * Purpose: checks if processor statistics collector is running (at least one *
 *          one process statistics query has been made).                      *
 *                                                                            *
 ******************************************************************************/
static int	procstat_running()
{
	if (ZBX_NONEXISTENT_SHMID == collector->procstat.shmid)
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: procstat_get_query                                               *
 *                                                                            *
 * Purpose: get process statistics query based on process name, user name     *
 *          and command line                                                  *
 *                                                                            *
 * Parameters: base     - [IN] the procstat shared memory segment             *
 *             procname - [IN] the process name                               *
 *             username - [IN] the user name                                  *
 *             cmdline  - [IN] the command line                               *
 *             flags    - [IN] platform specific flags                        *
 *                                                                            *
 * Return value: The process statistics query for the specified parameters or *
 *               NULL if the statistics are not being gathered for the        *
 *               specified parameters.                                        *
 *                                                                            *
 ******************************************************************************/
static	zbx_procstat_query_t	*procstat_get_query(void *base, const char *procname, const char *username,
		const char *cmdline, zbx_uint64_t flags)
{
	zbx_procstat_query_t	*query;

	if (SUCCEED != procstat_running())
		return NULL;

	for (query = PROCSTAT_QUERY_FIRST(base); NULL != query; query = PROCSTAT_QUERY_NEXT(base, query))
	{
		if (0 == zbx_strcmp_null(procname, PROCSTAT_PTR_NULL(base, query->procname)) &&
				0 == zbx_strcmp_null(username, PROCSTAT_PTR_NULL(base, query->username)) &&
				0 == zbx_strcmp_null(cmdline, PROCSTAT_PTR_NULL(base, query->cmdline)) &&
				flags == query->flags)
		{
			return query;
		}

	}

	return NULL;
}

/******************************************************************************
 *                                                                            *
 * Function: procstat_add                                                     *
 *                                                                            *
 * Purpose: adds a new query to process statistics collector                  *
 *                                                                            *
 * Parameters: procname - [IN] the process name                               *
 *             username - [IN] the user name                                  *
 *             cmdline  - [IN] the command line                               *
 *             flags    - [IN] platform specific flags                        *
 *                                                                            *
 * Return value:                                                              *
 *     This function calls exit() on shared memory errors.                    *
 *                                                                            *
 ******************************************************************************/
static void	procstat_add(const char *procname, const char *username, const char *cmdline, zbx_uint64_t flags)
{
	const char		*__function_name = "procstat_add";
	char			*errmsg = NULL;
	size_t			size = 0;
	zbx_procstat_query_t	*query;
	zbx_procstat_header_t	*header;
	int			query_offset;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	/* when allocating a new collection reserve space for procstat header */
	if (0 == collector->procstat.size)
		size += PROCSTAT_ALIGNED_HEADER_SIZE;

	/* reserve space for process attributes */
	if (NULL != procname)
		size += PROCSTAT_ALIGNED_SIZE(strlen(procname) + 1);

	if (NULL != username)
		size += PROCSTAT_ALIGNED_SIZE(strlen(username) + 1);

	if (NULL != cmdline)
		size += PROCSTAT_ALIGNED_SIZE(strlen(cmdline) + 1);

	/* reserve space for process cpu utilization snapshot */
	size += sizeof(zbx_procstat_util_t) * 32;

	/* procstat_add() is called when the shared memory reference has already been validated - */
	/* no need to call procstat_reattach()                                                    */
	header = (zbx_procstat_header_t *)procstat_ref.addr;

	/* reserve space for a new query only if there are no freed queries */
	if (NULL == header || PROCSTAT_NULL_OFFSET == header->free_queries)
		size += PROCSTAT_ALIGNED_SIZE(sizeof(zbx_procstat_query_t));

	if (NULL == header || FAIL == procstat_dshm_has_enough_space(header, size))
	{
		if (FAIL == zbx_dshm_reserve(&collector->procstat, size, &errmsg))
		{
			zabbix_log(LOG_LEVEL_CRIT, "cannot reserve memory in process data collector: %s", errmsg);
			zbx_free(errmsg);
			zbx_dshm_unlock(&collector->procstat);

			exit(EXIT_FAILURE);
		}

		/* Header initialised in procstat_copy_data() which is called back from zbx_dshm_reserve(). */

		procstat_reattach();
	}

	query_offset = procstat_alloc_query(procstat_ref.addr);

	/* initialize the created query */
	query = (zbx_procstat_query_t *)PROCSTAT_PTR_NULL(procstat_ref.addr, query_offset);

	memset(query, 0, sizeof(zbx_procstat_query_t));

	query->procname = procstat_strdup(procstat_ref.addr, procname);
	query->username = procstat_strdup(procstat_ref.addr, username);
	query->cmdline = procstat_strdup(procstat_ref.addr, cmdline);
	query->flags = flags;
	query->last_accessed = time(NULL);
	query->next = header->active_queries;
	header->active_queries = query_offset;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: procstat_free_query_data                                         *
 *                                                                            *
 * Purpose: frees the query data structure used to store queries locally      *
 *                                                                            *
 ******************************************************************************/
static void	procstat_free_query_data(zbx_procstat_query_data_t *data)
{
	zbx_vector_uint64_destroy(&data->pids);
	zbx_free(data);
}

/******************************************************************************
 *                                                                            *
 * Function: procstat_build_local_query_vector                                *
 *                                                                            *
 * Purpose: builds a local copy of the process cpu utilization queries and    *
 *          removes expired (not used during last 24 hours) queries           *
 *                                                                            *
 * Parameters: queries_ptr - [OUT] local copy of queries copied from queries  *
 *                                 in shared memory segment                   *
 *             runid       - [IN] marker for queries to be processed in the   *
 *                                current collector iteration                 *
 *                                                                            *
 * Return value: SUCCEED - unused queries removed, the rest of the queries    *
 *                         copied from shared memory segment to local vector  *
 *               FAIL    - no active quueries                                 *
 *                                                                            *
 * Comments: updates queries (runid) in shared memory segment                 *
 *                                                                            *
 ******************************************************************************/
static int	procstat_build_local_query_vector(zbx_vector_ptr_t *queries_ptr, int runid)
{
	zbx_procstat_header_t		*header;
	time_t				now;
	zbx_procstat_query_t		*query;
	zbx_procstat_query_t		*next;
	zbx_procstat_query_data_t	*qdata;
	int				ret = FAIL;

	zbx_dshm_lock(&collector->procstat);

	procstat_reattach();

	header = (zbx_procstat_header_t *)procstat_ref.addr;

	if (PROCSTAT_NULL_OFFSET == header->active_queries)
		goto out;

	now = time(NULL);
	zbx_vector_ptr_create(queries_ptr);

	for (query = PROCSTAT_QUERY_FIRST(procstat_ref.addr); NULL != query;
			query = next)
	{
		next = PROCSTAT_QUERY_NEXT(procstat_ref.addr, query);

		if (SEC_PER_DAY < now - query->last_accessed)
		{
			procstat_free_query(procstat_ref.addr, query);
			continue;
		}

		qdata = (zbx_procstat_query_data_t *)zbx_malloc(NULL, sizeof(zbx_procstat_query_data_t));
		zbx_vector_uint64_create(&qdata->pids);

		/* store the reference to query attributes, which is guaranteed to be */
		/* valid until we call process_reattach()                             */
		qdata->procname = PROCSTAT_PTR_NULL(procstat_ref.addr, query->procname);
		qdata->username = PROCSTAT_PTR_NULL(procstat_ref.addr, query->username);
		qdata->cmdline = PROCSTAT_PTR_NULL(procstat_ref.addr, query->cmdline);
		qdata->flags = query->flags;
		qdata->utime = 0;
		qdata->stime = 0;

		zbx_vector_ptr_append(queries_ptr, qdata);

		/* The order of queries can be changed only by collector itself (when removing old    */
		/* queries), but during statistics gathering the shared memory is unlocked and other  */
		/* processes might insert queries at the beginning of active queries list.            */
		/* Mark the queries being processed by current data gathering cycle with id that      */
		/* is incremented at the end of every data gathering cycle. We can be sure that       */
		/* our local copy will match the queries in shared memory having the same runid.      */
		query->runid = runid;
	}

	ret = SUCCEED;

out:
	zbx_dshm_unlock(&collector->procstat);
	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: procstat_get_pids_matching_query_attributes                      *
 *                                                                            *
 * Purpose: for every query gets the pids of processes matching query         *
 *          attributes                                                        *
 *                                                                            *
 * Parameters: queries - [IN/OUT] fills pids and error for each query         *
 *                                                                            *
 * Return value: total number of pids saved in all queries                    *
 *                                                                            *
 ******************************************************************************/
static int	procstat_get_pids_matching_query_attributes(zbx_vector_ptr_t queries)
{
	zbx_procstat_query_data_t	*qdata;
	int				i;
	int				pids_num = 0;

	for (i = 0; i < queries.values_num; i++)
	{
		qdata = (zbx_procstat_query_data_t *)queries.values[i];

		qdata->error = zbx_proc_get_pids(qdata->procname, qdata->username, qdata->cmdline, qdata->flags,
				&qdata->pids);

		if (SUCCEED == qdata->error)
			pids_num += qdata->pids.values_num;
	}

	return pids_num;
}

/******************************************************************************
 *                                                                            *
 * Function: procstat_build_unique_pids                                       *
 *                                                                            *
 * Purpose: creates a list of unique pids that are monitored by current data  *
 *          gathering cycle                                                   *
 *                                                                            *
 * Parameters: pids_ptr - [OUT] the list of unique pids                       *
 *             queries  - [IN] local, working copy of queries                 *
 *             pids_num - [IN] total number of pids saved in all queries      *
 *                                                                            *
 ******************************************************************************/
static void	procstat_build_unique_pids(zbx_vector_uint64_t *pids_ptr,
			zbx_vector_ptr_t queries, int pids_num)
{
	zbx_procstat_query_data_t	*qdata;
	int i;

	zbx_vector_uint64_create(pids_ptr);
	zbx_vector_uint64_reserve(pids_ptr, pids_num);

	for (i = 0; i < queries.values_num; i++)
	{
		qdata = (zbx_procstat_query_data_t *)queries.values[i];

		if (SUCCEED != qdata->error)
			continue;

		memcpy(pids_ptr->values + pids_ptr->values_num, qdata->pids.values,
			sizeof(zbx_uint64_t) * qdata->pids.values_num);
		pids_ptr->values_num += qdata->pids.values_num;
	}

	zbx_vector_uint64_sort(pids_ptr, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_uniq(pids_ptr, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
}

/******************************************************************************
 *                                                                            *
 * Function: procstat_get_cpu_util_snapshot_for_pids                          *
 *                                                                            *
 * Purpose: gets cpu utilization data snapshot for the monitored processes    *
 *                                                                            *
 * Parameters: stats_ptr - [OUT] current reading of the per-pid cpu usage     *
 *                               statistics (array, items correspond to pids) *
 *             pids      - [IN]  pids (unique) for which to collect data in   *
 *                               this iteration                               *
 *                                                                            *
 * Return value: timestamp of the snapshot                                    *
 *                                                                            *
 ******************************************************************************/
static zbx_timespec_t	procstat_get_cpu_util_snapshot_for_pids(zbx_procstat_util_t **stats_ptr,
				zbx_vector_uint64_t pids)
{
	zbx_timespec_t			snapshot_timestamp;
	int				i;

	*stats_ptr = (zbx_procstat_util_t *)zbx_malloc(NULL, sizeof(zbx_procstat_util_t) * pids.values_num);
	memset(*stats_ptr, 0, sizeof(zbx_procstat_util_t) * pids.values_num);

	for (i = 0; i < pids.values_num; i++)
		(*stats_ptr)[i].pid = pids.values[i];

	zbx_proc_get_stats(*stats_ptr, pids.values_num);

	zbx_timespec(&snapshot_timestamp);

	return snapshot_timestamp;
}

/******************************************************************************
 *                                                                            *
 * Function: procstat_calculate_cpu_util_for_queries                          *
 *                                                                            *
 * Purpose: calculates the cpu utilization for queries since the previous     *
 *          snapshot                                                          *
 *                                                                            *
 * Parameters: queries - [IN/OUT] local, working copy of queries, saving      *
 *                                utime, stime and error                      *
 *             pids    - [IN] pids (unique) for which to collect data in      *
 *                            this iteration                                  *
 *             stats   - [IN] current reading of the per-pid cpu usage        *
 *                            statistics (array, items correspond to pids)    *
 *                                                                            *
 ******************************************************************************/
static void	procstat_calculate_cpu_util_for_queries(zbx_vector_ptr_t queries,
			zbx_vector_uint64_t pids, const zbx_procstat_util_t *stats)
{
	zbx_procstat_query_data_t	*qdata;
	zbx_procstat_util_t		*putil;
	int				j, i;

	for (j = 0; j < queries.values_num; j++)
	{
		qdata = (zbx_procstat_query_data_t *)queries.values[j];

		if (SUCCEED != qdata->error)
			continue;

		/* sum the cpu utilization for processes that are present in current */
		/* and last process cpu utilization snapshot                         */
		for (i = 0; i < qdata->pids.values_num; i++)
		{
			zbx_uint64_t	starttime, utime, stime;

			/* find the process utilization data in current snapshot */
			putil = (zbx_procstat_util_t *)bsearch(&qdata->pids.values[i], stats, pids.values_num,
					sizeof(zbx_procstat_util_t), ZBX_DEFAULT_INT_COMPARE_FUNC);

			if (SUCCEED == qdata->error && SUCCEED != putil->error)
			{
				qdata->error = putil->error;
				break;
			}

			utime = putil->utime;
			stime = putil->stime;

			starttime = putil->starttime;

			/* find the process utilization data in last snapshot */
			putil = (zbx_procstat_util_t *)bsearch(&qdata->pids.values[i],
					PROCSTAT_SNAPSHOT(procstat_ref.addr), pids.values_num,
					sizeof(zbx_procstat_util_t), ZBX_DEFAULT_INT_COMPARE_FUNC);

			if (NULL == putil || putil->starttime != starttime)
				continue;

			qdata->utime += utime - putil->utime;
			qdata->stime += stime - putil->stime;
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Function: procstat_save_cpu_util_snapshot_in_queries                       *
 *                                                                            *
 * Purpose: updates cpu utilization and saves the new snapshot for queries in *
 *          shared memory segment                                             *
 *                                                                            *
 * Parameters: queries - [IN] local, working copy of queries (utime, stime    *
 *                            and error must be set)                          *
 *             pids    - [IN] pids (unique) for which data is collected in    *
 *                            this iteration                                  *
 *             stats   - [IN] current reading of the per-pid cpu usage        *
 *                            statistics (array, items correspond to pids)    *
 *             runid   - [IN] marker for queries to be processed in the       *
 *                            current collector iteration                     *
 *             snapshot_timestamp - [IN] timestamp of the current snapshot    *
 *                                                                            *
 * Comments: updates header (pids_num) and queries (h_data, h_count, h_first) *
 *           in shared memory segment, writes stats at the end of the shared  *
 *           memory segment                                                   *
 *                                                                            *
 ******************************************************************************/
static void	procstat_save_cpu_util_snapshot_in_queries(zbx_vector_ptr_t queries,
			zbx_vector_uint64_t pids, zbx_procstat_util_t *stats,
			int runid, zbx_timespec_t snapshot_timestamp)
{
	zbx_procstat_header_t		*header;
	zbx_procstat_query_t		*query;
	zbx_procstat_query_data_t	*qdata;
	int				index;
	int				i;
	char				*errmsg = NULL;

	zbx_dshm_lock(&collector->procstat);

	procstat_reattach();
	header = (zbx_procstat_header_t *)procstat_ref.addr;

	if (header->size - header->size_allocated < pids.values_num * sizeof(zbx_procstat_util_t))
	{
		if (FAIL == zbx_dshm_reserve(&collector->procstat, pids.values_num * sizeof(zbx_procstat_util_t) * 1.5,
				&errmsg))
		{
			zabbix_log(LOG_LEVEL_CRIT, "cannot reserve memory in process data collector: %s", errmsg);
			zbx_free(errmsg);
			zbx_dshm_unlock(&collector->procstat);

			exit(EXIT_FAILURE);
		}

		procstat_reattach();
		header = (zbx_procstat_header_t *)procstat_ref.addr;
	}

	for (query = PROCSTAT_QUERY_FIRST(procstat_ref.addr), i = 0; NULL != query;
			query = PROCSTAT_QUERY_NEXT(procstat_ref.addr, query))
	{
		if (runid != query->runid)
			continue;

		if (i >= queries.values_num)
		{
			THIS_SHOULD_NEVER_HAPPEN;
			break;
		}

		qdata = (zbx_procstat_query_data_t *)queries.values[i++];

		if (SUCCEED != (query->error = qdata->error))
			continue;

		/* find the next history data slot */
		if (0 < query->h_count)
		{
			if (MAX_COLLECTOR_HISTORY <= (index = query->h_first + query->h_count - 1))
				index -= MAX_COLLECTOR_HISTORY;

			qdata->utime += query->h_data[index].utime;
			qdata->stime += query->h_data[index].stime;

			if (MAX_COLLECTOR_HISTORY <= ++index)
				index -= MAX_COLLECTOR_HISTORY;
		}
		else
			index = 0;

		if (MAX_COLLECTOR_HISTORY == query->h_count)
		{
			if (MAX_COLLECTOR_HISTORY <= ++query->h_first)
				query->h_first = 0;
		}
		else
			query->h_count++;

		query->h_data[index].utime = qdata->utime;
		query->h_data[index].stime = qdata->stime;
		query->h_data[index].timestamp = snapshot_timestamp;
	}

	header->pids_num = pids.values_num;
	memcpy(PROCSTAT_SNAPSHOT(procstat_ref.addr), stats, sizeof(zbx_procstat_util_t) * pids.values_num);

	zbx_dshm_unlock(&collector->procstat);
}

/*
 * Public API
 */

/******************************************************************************
 *                                                                            *
 * Function: zbx_procstat_collector_started                                   *
 *                                                                            *
 * Purpose: checks if processor statistics collector is enabled (the main     *
 *          collector has been initialized)                                   *
 *                                                                            *
 ******************************************************************************/
int	zbx_procstat_collector_started()
{
	if (NULL == collector)
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_procstat_init                                                *
 *                                                                            *
 * Purpose: initializes process statistics collector                          *
 *                                                                            *
 * Parameters: shm - [IN] the dynamic shared memory segment used by process   *
 *                        statistics collector                                *
 *                                                                            *
 * Return value: This function calls exit() on shared memory errors.          *
 *                                                                            *
 ******************************************************************************/
void	zbx_procstat_init()
{
	char	*errmsg = NULL;

	if (SUCCEED != zbx_dshm_create(&collector->procstat, ZBX_IPC_COLLECTOR_PROC_ID, 0, ZBX_MUTEX_PROCSTATS,
			procstat_copy_data, &errmsg))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot initialize process data collector: %s", errmsg);
		zbx_free(errmsg);
		exit(EXIT_FAILURE);
	}

	procstat_ref.shmid = ZBX_NONEXISTENT_SHMID;
	procstat_ref.addr = NULL;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_procstat_get_util                                            *
 *                                                                            *
 * Purpose: gets process cpu utilization                                      *
 *                                                                            *
 * Parameters: procname       - [IN] the process name, NULL - all             *
 *             username       - [IN] the user name, NULL - all                *
 *             cmdline        - [IN] the command line, NULL - all             *
 *             collector_func - [IN] the callback function to use for process *
 *                              statistics gathering                          *
 *             period         - [IN] the time period                          *
 *             type           - [IN] the cpu utilization type, see            *
 *                              ZBX_PROCSTAT_CPU_* defines                    *
 *             value          - [OUT] the utilization in %                    *
 *             errmsg         - [OUT] the error message                       *
 *                                                                            *
 * Return value:                                                              *
 *     SUCCEED - the utime value was retrieved successfully                   *
 *     FAIL    - either collector does not have at least two data samples     *
 *               required to calculate the statistics, or an error occurred   *
 *               during the collection process. In the second case the errmsg *
 *               will contain an error message.                               *
 *     This function calls exit() on shared memory errors.                    *
 *                                                                            *
 ******************************************************************************/
int	zbx_procstat_get_util(const char *procname, const char *username, const char *cmdline, zbx_uint64_t flags,
		int period, int type, double *value, char **errmsg)
{
	int		ret = FAIL, current, start;
	zbx_procstat_query_t	*query;
	zbx_uint64_t	ticks_diff = 0, time_diff;

	zbx_dshm_lock(&collector->procstat);

	procstat_reattach();

	if (NULL == (query = procstat_get_query(procstat_ref.addr, procname, username, cmdline, flags)))
	{
		procstat_add(procname, username, cmdline, flags);
		goto out;
	}

	query->last_accessed = time(NULL);

	if (0 != query->error)
	{
		*errmsg = zbx_dsprintf(*errmsg, "Cannot read cpu utilization data: %s", zbx_strerror(-query->error));
		goto out;
	}

	if (1 >= query->h_count)
		goto out;

	if (period >= query->h_count)
		period = query->h_count - 1;

	if (MAX_COLLECTOR_HISTORY <= (current = query->h_first + query->h_count - 1))
		current -= MAX_COLLECTOR_HISTORY;

	if (0 > (start = current - period))
		start += MAX_COLLECTOR_HISTORY;

	if (0 != (type & ZBX_PROCSTAT_CPU_USER))
			ticks_diff += query->h_data[current].utime - query->h_data[start].utime;

	if (0 != (type & ZBX_PROCSTAT_CPU_SYSTEM))
			ticks_diff += query->h_data[current].stime - query->h_data[start].stime;

	time_diff = (zbx_uint64_t)(query->h_data[current].timestamp.sec - query->h_data[start].timestamp.sec) *
			1000000000 + query->h_data[current].timestamp.ns - query->h_data[start].timestamp.ns;

	/* 1e9 (nanoseconds) * 1e2 (percent) * 1e1 (one digit decimal place) */
	ticks_diff *= 1000000000000;
	ticks_diff /= time_diff * sysconf(_SC_CLK_TCK);
	*value = (double)ticks_diff / 10;

	ret = SUCCEED;
out:
	zbx_dshm_unlock(&collector->procstat);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_procstat_collect                                             *
 *                                                                            *
 * Purpose: performs process statistics collection                            *
 *                                                                            *
 ******************************************************************************/
void	zbx_procstat_collect()
{
	/* identifies current collection iteration */
	static int			runid = 1;

	/* number of (non-unique) pids that match queries */
	int				pids_num = 0;

	/* local, working copy of queries */
	zbx_vector_ptr_t		queries;

	/* pids (unique) for which to collect data in this iteration */
	zbx_vector_uint64_t		pids;

	/* current reading of the per-pid cpu usage statistics (array, items correspond to pids) */
	zbx_procstat_util_t		*stats;

	/* time of the per-pid usage statistics collection */
	zbx_timespec_t			snapshot_timestamp;

	if (FAIL == zbx_procstat_collector_started() || FAIL == procstat_running())
		goto out;

	if (FAIL == procstat_build_local_query_vector(&queries, runid))
		goto out;

	pids_num = procstat_get_pids_matching_query_attributes(queries);

	procstat_build_unique_pids(&pids, queries, pids_num);

	snapshot_timestamp = procstat_get_cpu_util_snapshot_for_pids(&stats, pids);

	procstat_calculate_cpu_util_for_queries(queries, pids, stats);

	procstat_save_cpu_util_snapshot_in_queries(queries, pids, stats, runid, snapshot_timestamp);

	zbx_free(stats);
	zbx_vector_uint64_destroy(&pids);
	zbx_vector_ptr_clear_ext(&queries, (zbx_mem_free_func_t)procstat_free_query_data);
	zbx_vector_ptr_destroy(&queries);

out:
	runid++;
}

#endif

