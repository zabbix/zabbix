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
 * 1) acquires list of all processes running on system
 * 2) builds a list of processes monitored by queries
 * 3) reads total cpu utilization snapshot for the monitored processes
 * 4) calculates cpu utilization difference by comparing with previous snapshot
 * 5) updates cpu utilization values for queries.
 * 6) saves the last cpu utilization snapshot
 *
 * Initialisation.
 * * procstat_init() initialises procstat dshm structure but doesn't allocate memory from the system
 *   (zbx_dshm_create() called with size 0).
 * * the first call of procstat_add() allocates the shared memory for the header and the first query
 *   via call to zbx_dshm_realloc().
 * * The header is initialised in procstat_copy_data() which is called back from zbx_dshm_realloc().
 *
 * Memory allocation within dshm.
 * * Ensure that memory segment has enough free space with procstat_dshm_has_enough_space() before
 *   allocating space within segment with procstat_alloc() or functions that use it.
 * * Check how much of the allocated dshm is actually used by procstat by procstat_dshm_used_size().
 * * Change the dshm size with with zbx_dshm_realloc().
 *
 * Synchronisation.
 * * agentd processes share a single instance of ZBX_COLLECTOR_DATA (*collector) containing reference
 *   to shared procstat memory segment.
 * * Each agentd process also holds local reference to procstat shared memory segment.
 * * The system keeps the shared memory segment until the last process detaches from it.
 * * Synchronise both references with procstat_reattach() before using procstat shared memory segment.
 */

/* the main collector data */
extern ZBX_COLLECTOR_DATA	*collector;

/* local reference to the procstat shared memory */
static zbx_dshm_ref_t	procstat_ref;

typedef struct
{
	/* a linked list of active queries (offset of the first active query) */
	int	queries;

	/* the total size of the allocated queries and strings */
	int	size_allocated;

	/* the total shared memory segment size */
	size_t	size;
}
zbx_procstat_header_t;

#define PROCSTAT_NULL_OFFSET		0

#define PROCSTAT_ALIGNED_HEADER_SIZE	ZBX_SIZE_T_ALIGN8(sizeof(zbx_procstat_header_t))

#define PROCSTAT_PTR(base, offset)	((char *)base + offset)

#define PROCSTAT_PTR_NULL(base, offset)									\
		(PROCSTAT_NULL_OFFSET == offset ? NULL : PROCSTAT_PTR(base, offset))

#define PROCSTAT_QUERY_FIRST(base)									\
		(zbx_procstat_query_t*)PROCSTAT_PTR_NULL(base, ((zbx_procstat_header_t *)base)->queries)

#define PROCSTAT_QUERY_NEXT(base, query)								\
		(zbx_procstat_query_t*)PROCSTAT_PTR_NULL(base, query->next)

#define PROCSTAT_OFFSET(base, ptr) ((char *)ptr - (char *)base)

/* maximum number of active procstat queries */
#define PROCSTAT_MAX_QUERIES	1024

/* the time period after which inactive queries (not accessed during this period) can be removed */
#define PROCSTAT_MAX_INACTIVITY_PERIOD	SEC_PER_DAY

/* the time interval between compressing (inactive query removal) attempts */
#define PROCSTAT_COMPRESS_PERIOD	SEC_PER_DAY

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

/* the process cpu utilization snapshot */
static zbx_procstat_util_t	*procstat_snapshot;
/* the number of processes in process cpu utilization snapshot */
static int			procstat_snapshot_num;

/* external functions used by procstat collector */
int	zbx_proc_get_processes(zbx_vector_ptr_t *processes, unsigned int flags);

void	zbx_proc_get_matching_pids(const zbx_vector_ptr_t *processes, const char *procname, const char *username,
		const char *cmdline, zbx_uint64_t flags, zbx_vector_uint64_t *pids);

void	zbx_proc_get_process_stats(zbx_procstat_util_t *procs, int procs_num);

void	zbx_proc_free_processes(zbx_vector_ptr_t *processes);

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
	zbx_procstat_header_t	*header = (zbx_procstat_header_t *)base;

	if (header->size >= size + header->size_allocated)
		return SUCCEED;

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: procstat_dshm_used_size                                          *
 *                                                                            *
 * Purpose: calculate the actual shared memory size used by procstat          *
 *                                                                            *
 * Parameters: base - [IN] the procstat shared memory segment                 *
 *                                                                            *
 * Return value: The number of bytes required to store current procstat data. *
 *                                                                            *
 ******************************************************************************/
static size_t	procstat_dshm_used_size(void *base)
{
	const zbx_procstat_query_t	*query;
	size_t				size;

	if (NULL == base)
		return 0;

	size = PROCSTAT_ALIGNED_HEADER_SIZE;

	for (query = PROCSTAT_QUERY_FIRST(base); NULL != query; query = PROCSTAT_QUERY_NEXT(base, query))
	{
		if (PROCSTAT_NULL_OFFSET != query->procname)
			size += ZBX_SIZE_T_ALIGN8(strlen(PROCSTAT_PTR(base, query->procname)) + 1);

		if (PROCSTAT_NULL_OFFSET != query->username)
			size += ZBX_SIZE_T_ALIGN8(strlen(PROCSTAT_PTR(base, query->username)) + 1);

		if (PROCSTAT_NULL_OFFSET != query->cmdline)
			size += ZBX_SIZE_T_ALIGN8(strlen(PROCSTAT_PTR(base, query->cmdline)) + 1);

		size += ZBX_SIZE_T_ALIGN8(sizeof(zbx_procstat_query_t));
	}

	return size;
}

/******************************************************************************
 *                                                                            *
 * Function: procstat_queries_num                                             *
 *                                                                            *
 * Purpose: calculate the number of active queries                            *
 *                                                                            *
 * Parameters: base - [IN] the procstat shared memory segment                 *
 *                                                                            *
 * Return value: The number of active queries.                                *
 *                                                                            *
 ******************************************************************************/
static int	procstat_queries_num(void *base)
{
	const zbx_procstat_query_t	*query;
	int				queries_num;

	if (NULL == base)
		return 0;

	queries_num = 0;

	for (query = PROCSTAT_QUERY_FIRST(base); NULL != query; query = PROCSTAT_QUERY_NEXT(base, query))
		queries_num++;

	return queries_num;
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

	size = ZBX_SIZE_T_ALIGN8(size);

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

	int			offset, *query_offset;
	zbx_procstat_header_t	*hdst = (zbx_procstat_header_t *)dst;
	zbx_procstat_query_t	*qsrc, *qdst = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	hdst->size = size_dst;
	hdst->size_allocated = PROCSTAT_ALIGNED_HEADER_SIZE;
	hdst->queries = PROCSTAT_NULL_OFFSET;

	if (NULL != src)
	{
		query_offset = &hdst->queries;

		/* copy queries */
		for (qsrc = PROCSTAT_QUERY_FIRST(src); NULL != qsrc; qsrc = PROCSTAT_QUERY_NEXT(src, qsrc))
		{
			/* the new shared memory segment must have enough space */
			offset = procstat_alloc(dst, sizeof(zbx_procstat_query_t));

			qdst = (zbx_procstat_query_t *)PROCSTAT_PTR(dst, offset);

			memcpy(qdst, qsrc, sizeof(zbx_procstat_query_t));

			qdst->procname = procstat_strdup(dst, PROCSTAT_PTR_NULL(src, qsrc->procname));
			qdst->username = procstat_strdup(dst, PROCSTAT_PTR_NULL(src, qsrc->username));
			qdst->cmdline = procstat_strdup(dst, PROCSTAT_PTR_NULL(src, qsrc->cmdline));

			*query_offset = offset;
			query_offset = &qdst->next;
		}
	}

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
		size += ZBX_SIZE_T_ALIGN8(strlen(procname) + 1);

	if (NULL != username)
		size += ZBX_SIZE_T_ALIGN8(strlen(username) + 1);

	if (NULL != cmdline)
		size += ZBX_SIZE_T_ALIGN8(strlen(cmdline) + 1);

	/* procstat_add() is called when the shared memory reference has already been validated - */
	/* no need to call procstat_reattach()                                                    */

	/* reserve space for query container */
	size += ZBX_SIZE_T_ALIGN8(sizeof(zbx_procstat_query_t));

	if (NULL == procstat_ref.addr || FAIL == procstat_dshm_has_enough_space(procstat_ref.addr, size))
	{
		/* recalculate the space required to store existing data + new query */
		size += procstat_dshm_used_size(procstat_ref.addr);

		if (FAIL == zbx_dshm_realloc(&collector->procstat, size, &errmsg))
		{
			zabbix_log(LOG_LEVEL_CRIT, "cannot reallocate memory in process data collector: %s", errmsg);
			zbx_free(errmsg);
			zbx_dshm_unlock(&collector->procstat);

			exit(EXIT_FAILURE);
		}

		/* header initialised in procstat_copy_data() which is called back from zbx_dshm_realloc() */
		procstat_reattach();
	}

	header = (zbx_procstat_header_t *)procstat_ref.addr;

	query_offset = procstat_alloc(procstat_ref.addr, sizeof(zbx_procstat_query_t));

	/* initialize the created query */
	query = (zbx_procstat_query_t *)PROCSTAT_PTR_NULL(procstat_ref.addr, query_offset);

	memset(query, 0, sizeof(zbx_procstat_query_t));

	query->procname = procstat_strdup(procstat_ref.addr, procname);
	query->username = procstat_strdup(procstat_ref.addr, username);
	query->cmdline = procstat_strdup(procstat_ref.addr, cmdline);
	query->flags = flags;
	query->last_accessed = time(NULL);
	query->next = header->queries;
	header->queries = query_offset;

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
 * Function: procstat_try_compress                                            *
 *                                                                            *
 * Purpose: try to compress (remove inactive queries) the procstat shared     *
 *          memory segment once per day                                       *
 *                                                                            *
 * Parameters: base - [IN] the procstat shared memory segment                 *
 *                                                                            *
 ******************************************************************************/
static void	procstat_try_compress(void *base)
{
	static int	collector_iteration = 0;

	/* The iteration counter ~ the number seconds collector has been running */
	/* because collector data gathering is done once per second.             */
	/* This approximation is done to avoid calling time() function if there  */
	/* are no defined queries.                                               */
	if (0 == (++collector_iteration % PROCSTAT_COMPRESS_PERIOD))
	{
		zbx_procstat_header_t	*header = (zbx_procstat_header_t *)procstat_ref.addr;
		size_t			size;
		char			*errmsg = NULL;

		size = procstat_dshm_used_size(base);

		if (size < header->size && FAIL == zbx_dshm_realloc(&collector->procstat, size, &errmsg))
		{
			zabbix_log(LOG_LEVEL_CRIT, "cannot reallocate memory in process data collector: %s", errmsg);
			zbx_free(errmsg);
			zbx_dshm_unlock(&collector->procstat);

			exit(EXIT_FAILURE);
		}
	}
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
 * Return value: The flags defining the process properties to be retrieved.   *
 *               See ZBX_SYSINFO_PROC_ defines.                               *
 *                                                                            *
 * Comments: updates queries (runid) in shared memory segment                 *
 *                                                                            *
 ******************************************************************************/
static int	procstat_build_local_query_vector(zbx_vector_ptr_t *queries_ptr, int runid)
{
	zbx_procstat_header_t		*header;
	time_t				now;
	zbx_procstat_query_t		*query;
	zbx_procstat_query_data_t	*qdata;
	int				flags = ZBX_SYSINFO_PROC_NONE, *pnext_query;

	zbx_dshm_lock(&collector->procstat);

	procstat_reattach();

	header = (zbx_procstat_header_t *)procstat_ref.addr;

	if (PROCSTAT_NULL_OFFSET == header->queries)
		goto out;

	flags = ZBX_SYSINFO_PROC_PID;

	now = time(NULL);
	pnext_query = &header->queries;

	for (query = PROCSTAT_QUERY_FIRST(procstat_ref.addr); NULL != query;
			query = PROCSTAT_QUERY_NEXT(procstat_ref.addr, query))
	{
		/* remove unused queries, the data is still allocated until the next resize */
		if (PROCSTAT_MAX_INACTIVITY_PERIOD < now - query->last_accessed)
		{
			*pnext_query = query->next;
			continue;
		}

		qdata = (zbx_procstat_query_data_t *)zbx_malloc(NULL, sizeof(zbx_procstat_query_data_t));
		zbx_vector_uint64_create(&qdata->pids);

		/* store the reference to query attributes, which is guaranteed to be */
		/* valid until we call process_reattach()                             */
		if (NULL != (qdata->procname = PROCSTAT_PTR_NULL(procstat_ref.addr, query->procname)))
			flags |= ZBX_SYSINFO_PROC_NAME;

		if (NULL != (qdata->username = PROCSTAT_PTR_NULL(procstat_ref.addr, query->username)))
			flags |= ZBX_SYSINFO_PROC_USER;

		if (NULL != (qdata->cmdline = PROCSTAT_PTR_NULL(procstat_ref.addr, query->cmdline)))
			flags |= ZBX_SYSINFO_PROC_CMDLINE;

		qdata->flags = query->flags;
		qdata->utime = 0;
		qdata->stime = 0;
		qdata->error = 0;

		zbx_vector_ptr_append(queries_ptr, qdata);

		/* The order of queries can be changed only by collector itself (when removing old    */
		/* queries), but during statistics gathering the shared memory is unlocked and other  */
		/* processes might insert queries at the beginning of active queries list.            */
		/* Mark the queries being processed by current data gathering cycle with id that      */
		/* is incremented at the end of every data gathering cycle. We can be sure that       */
		/* our local copy will match the queries in shared memory having the same runid.      */
		query->runid = runid;

		pnext_query = &query->next;
	}

out:
	procstat_try_compress(procstat_ref.addr);

	zbx_dshm_unlock(&collector->procstat);

	return flags;
}

/******************************************************************************
 *                                                                            *
 * Function: procstat_scan_query_pids                                         *
 *                                                                            *
 * Purpose: for every query gets the pids of processes matching query         *
 *          attributes                                                        *
 *                                                                            *
 * Parameters: queries - [IN/OUT] fills pids and error for each query         *
 *                                                                            *
 * Return value: total number of pids saved in all queries                    *
 *                                                                            *
 ******************************************************************************/
static int	procstat_scan_query_pids(zbx_vector_ptr_t *queries, const zbx_vector_ptr_t *processes)
{
	zbx_procstat_query_data_t	*qdata;
	int				i, pids_num = 0;

	for (i = 0; i < queries->values_num; i++)
	{
		qdata = (zbx_procstat_query_data_t *)queries->values[i];

		zbx_proc_get_matching_pids(processes, qdata->procname, qdata->username, qdata->cmdline, qdata->flags,
				&qdata->pids);

		pids_num += qdata->pids.values_num;
	}

	return pids_num;
}

/******************************************************************************
 *                                                                            *
 * Function: procstat_get_monitored_pids                                      *
 *                                                                            *
 * Purpose: creates a list of unique pids that are monitored by current data  *
 *          gathering cycle                                                   *
 *                                                                            *
 * Parameters: pids     - [OUT] a sorted vector of unique pids                *
 *             queries  - [IN] local, working copy of queries                 *
 *             pids_num - [IN] the total number of pids monitored by queries  *
 *                             (might contain duplicated pids)                *
 *                                                                            *
 ******************************************************************************/
static void	procstat_get_monitored_pids(zbx_vector_uint64_t *pids, const zbx_vector_ptr_t *queries, int pids_num)
{
	zbx_procstat_query_data_t	*qdata;
	int				i;

	zbx_vector_uint64_reserve(pids, pids_num);

	for (i = 0; i < queries->values_num; i++)
	{
		qdata = (zbx_procstat_query_data_t *)queries->values[i];

		if (SUCCEED != qdata->error)
			continue;

		memcpy(pids->values + pids->values_num, qdata->pids.values,
				sizeof(zbx_uint64_t) * qdata->pids.values_num);
		pids->values_num += qdata->pids.values_num;
	}

	zbx_vector_uint64_sort(pids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_uniq(pids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
}

/******************************************************************************
 *                                                                            *
 * Function: procstat_get_cpu_util_snapshot_for_pids                          *
 *                                                                            *
 * Purpose: gets cpu utilization data snapshot for the monitored processes    *
 *                                                                            *
 * Parameters: stats - [OUT] current reading of the per-pid cpu usage         *
 *                               statistics (array, items correspond to pids) *
 *             pids  - [IN]  pids (unique) for which to collect data in this  *
 *                               iteration                                    *
 *                                                                            *
 * Return value: timestamp of the snapshot                                    *
 *                                                                            *
 ******************************************************************************/
static zbx_timespec_t	procstat_get_cpu_util_snapshot_for_pids(zbx_procstat_util_t *stats,
				zbx_vector_uint64_t *pids)
{
	zbx_timespec_t	snapshot_timestamp;
	int		i;

	for (i = 0; i < pids->values_num; i++)
		stats[i].pid = pids->values[i];

	zbx_proc_get_process_stats(stats, pids->values_num);

	zbx_timespec(&snapshot_timestamp);

	return snapshot_timestamp;
}

/******************************************************************************
 *                                                                            *
 * Function: procstat_util_compare                                            *
 *                                                                            *
 * Purpose: compare process utilization data by their pids                    *
 *                                                                            *
 ******************************************************************************/
static int	procstat_util_compare(const void *d1, const void *d2)
{
	const zbx_procstat_util_t	*u1 = (zbx_procstat_util_t *)d1;
	const zbx_procstat_util_t	*u2 = (zbx_procstat_util_t *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(u1->pid, u2->pid);

	return 0;
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
static void	procstat_calculate_cpu_util_for_queries(zbx_vector_ptr_t *queries,
			zbx_vector_uint64_t *pids, const zbx_procstat_util_t *stats)
{
	zbx_procstat_query_data_t	*qdata;
	zbx_procstat_util_t		*putil;
	int				j, i;

	for (j = 0; j < queries->values_num; j++)
	{
		qdata = (zbx_procstat_query_data_t *)queries->values[j];

		/* sum the cpu utilization for processes that are present in current */
		/* and last process cpu utilization snapshot                         */
		for (i = 0; i < qdata->pids.values_num; i++)
		{
			zbx_uint64_t		starttime, utime, stime;
			zbx_procstat_util_t	util_local;

			util_local.pid = qdata->pids.values[i];

			/* find the process utilization data in current snapshot */
			putil = (zbx_procstat_util_t *)bsearch(&util_local, stats, pids->values_num,
					sizeof(zbx_procstat_util_t), procstat_util_compare);

			if (NULL == putil || SUCCEED != putil->error)
				continue;

			utime = putil->utime;
			stime = putil->stime;

			starttime = putil->starttime;

			/* find the process utilization data in last snapshot */
			putil = (zbx_procstat_util_t *)bsearch(&util_local, procstat_snapshot, procstat_snapshot_num,
					sizeof(zbx_procstat_util_t), procstat_util_compare);

			if (NULL == putil || SUCCEED != putil->error || putil->starttime != starttime)
				continue;

			qdata->utime += utime - putil->utime;
			qdata->stime += stime - putil->stime;
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Function: procstat_update_query_statistics                                 *
 *                                                                            *
 * Purpose: updates cpu utilization and saves the new snapshot for queries in *
 *          shared memory segment                                             *
 *                                                                            *
 * Parameters: queries - [IN] local, working copy of queries (utime, stime    *
 *                            and error must be set)                          *
 *             runid   - [IN] marker for queries to be processed in the       *
 *                            current collector iteration                     *
 *             snapshot_timestamp - [IN] timestamp of the current snapshot    *
 *                                                                            *
 * Comments: updates header (pids_num) and queries (h_data, h_count, h_first) *
 *           in shared memory segment, writes stats at the end of the shared  *
 *           memory segment                                                   *
 *                                                                            *
 ******************************************************************************/
static void	procstat_update_query_statistics(zbx_vector_ptr_t *queries, int runid,
		const zbx_timespec_t *snapshot_timestamp)
{
	zbx_procstat_query_t		*query;
	zbx_procstat_query_data_t	*qdata;
	int				index;
	int				i;

	zbx_dshm_lock(&collector->procstat);

	procstat_reattach();

	for (query = PROCSTAT_QUERY_FIRST(procstat_ref.addr), i = 0; NULL != query;
			query = PROCSTAT_QUERY_NEXT(procstat_ref.addr, query))
	{
		if (runid != query->runid)
			continue;

		if (i >= queries->values_num)
		{
			THIS_SHOULD_NEVER_HAPPEN;
			break;
		}

		qdata = (zbx_procstat_query_data_t *)queries->values[i++];

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
		query->h_data[index].timestamp = *snapshot_timestamp;
	}

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

	if (SUCCEED != zbx_dshm_create(&collector->procstat, ZBX_IPC_COLLECTOR_PROC_ID, 0, ZBX_MUTEX_PROCSTAT,
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
 * Function: zbx_procstat_destroy                                             *
 *                                                                            *
 * Purpose: destroys process statistics collector                             *
 *                                                                            *
 ******************************************************************************/
void	zbx_procstat_destroy()
{
	char	*errmsg = NULL;

	if (SUCCEED != zbx_dshm_destroy(&collector->procstat, &errmsg))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot free resources allocated by process data collector: %s", errmsg);
		zbx_free(errmsg);
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
	int			ret = FAIL, current, start;
	zbx_procstat_query_t	*query;
	zbx_uint64_t		ticks_diff = 0, time_diff;

	zbx_dshm_lock(&collector->procstat);

	procstat_reattach();

	if (NULL == (query = procstat_get_query(procstat_ref.addr, procname, username, cmdline, flags)))
	{
		if (procstat_queries_num(procstat_ref.addr) == PROCSTAT_MAX_QUERIES)
			*errmsg = zbx_strdup(*errmsg, "Maximum number of queries reached.");
		else
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

	/* flags specifying what process properties must be retrieved */
	int				flags;

	/* local, working copy of queries */
	zbx_vector_ptr_t		queries;

	/* data about all processes on system */
	zbx_vector_ptr_t		processes;

	/* pids (unique) for which to collect data in this iteration */
	zbx_vector_uint64_t		pids;

	/* current reading of the per-pid cpu usage statistics (array, items correspond to pids) */
	zbx_procstat_util_t		*stats;

	/* time of the per-pid usage statistics collection */
	zbx_timespec_t			snapshot_timestamp;

	if (FAIL == zbx_procstat_collector_started() || FAIL == procstat_running())
		goto out;

	zbx_vector_ptr_create(&queries);
	zbx_vector_ptr_create(&processes);
	zbx_vector_uint64_create(&pids);

	if (ZBX_SYSINFO_PROC_NONE == (flags = procstat_build_local_query_vector(&queries, runid)))
		goto clean;

	if (SUCCEED != zbx_proc_get_processes(&processes, flags))
		goto clean;

	pids_num = procstat_scan_query_pids(&queries, &processes);

	procstat_get_monitored_pids(&pids, &queries, pids_num);

	stats = (zbx_procstat_util_t *)zbx_malloc(NULL, sizeof(zbx_procstat_util_t) * pids.values_num);
	snapshot_timestamp = procstat_get_cpu_util_snapshot_for_pids(stats, &pids);

	procstat_calculate_cpu_util_for_queries(&queries, &pids, stats);

	procstat_update_query_statistics(&queries, runid, &snapshot_timestamp);

	/* replace the current snapshot with the new stats */
	zbx_free(procstat_snapshot);
	procstat_snapshot = stats;
	procstat_snapshot_num = pids.values_num;
clean:
	zbx_vector_uint64_destroy(&pids);

	zbx_proc_free_processes(&processes);
	zbx_vector_ptr_destroy(&processes);

	zbx_vector_ptr_clear_ext(&queries, (zbx_mem_free_func_t)procstat_free_query_data);
	zbx_vector_ptr_destroy(&queries);
out:
	runid++;
}

#endif
