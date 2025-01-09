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

#include "zbxtimekeeper.h"

#include "zbxalgo.h"

#define MAX_HISTORY	60

#define ZBX_TIMEKEEPER_FLUSH_DELAY		(ZBX_TIMEKEEPER_DELAY * 0.5)

/* unit state cache, updated only by the execution units themselves */
typedef struct
{
	/* the current usage statistics */
	zbx_uint64_t	counter[ZBX_PROCESS_STATE_COUNT];

	/* ticks of the last timekeeper update */
	clock_t		ticks;

	/* ticks of the last timekeeper cache flush */
	clock_t		ticks_flush;

	/* the current process state (see ZBX_PROCESS_STATE_* defines) */
	unsigned char	state;
}
zbx_timekeeper_unit_cache_t;

/* execution unit state statistics */
typedef struct
{
	/* historical unit state data */
	zbx_uint64_t		h_counter[ZBX_PROCESS_STATE_COUNT][MAX_HISTORY];

	/* unit state data for the current data gathering cycle */
	zbx_uint64_t		counter[ZBX_PROCESS_STATE_COUNT];

	/* the unit state that was already applied to the historical state data */
	zbx_uint64_t		counter_used[ZBX_PROCESS_STATE_COUNT];

	/* the unit state cache */
	zbx_timekeeper_unit_cache_t	cache;
}
zbx_timekeeper_unit_t;

typedef enum
{
	TIMEKEEPER_SYNC_INTERNAL,
	TIMEKEEPER_SYNC_EXTERNAL
}
zbx_timekeeper_sync_source_t;

struct zbx_timekeeper
{
	zbx_timekeeper_unit_t	*units;
	int			units_num;
	int			first;
	int			count;

	/* number of ticks per second */
	long int		ticks_per_sec;

	/* ticks of the last timekeeper sync (data gathering) */
	clock_t			ticks_sync;

	zbx_timekeeper_sync_t		*sync;
	zbx_timekeeper_sync_source_t	sync_source;

	zbx_mem_malloc_func_t	mem_malloc_func;
	zbx_mem_realloc_func_t	mem_realloc_func;
	zbx_mem_free_func_t	mem_free_func;
};

static clock_t	zbx_times(void)
{
#if !defined(TIMES_NULL_ARG)
	struct tms	buf;

	return times(&buf);
#else
	return times(NULL);
#endif
}

/******************************************************************************
 *                                                                            *
 * Purpose: initialize timekeeper sync object                                 *
 *                                                                            *
 * Parameters: sync   - [IN] the sync object to initialize                    *
 *             lock   - [IN] the lock function                                *
 *             unlock - [IN] the unlock function                              *
 *             data   - [IN] the user data passed to lock/unlock functions    *
 *                                                                            *
 ******************************************************************************/
void	zbx_timekeeper_sync_init(zbx_timekeeper_sync_t *sync, zbx_timekeeper_sync_func_t lock,
		zbx_timekeeper_sync_func_t unlock, void *data)
{
	sync->lock = lock;
	sync->unlock = unlock;
	sync->data = data;
}

/******************************************************************************
 *                                                                            *
 * Purpose: lock thread based timekeeper sync object                          *
 *                                                                            *
 ******************************************************************************/
static void	timekeeper_thread_lock(void *data)
{
	pthread_mutex_t	*mutex = (pthread_mutex_t *)data;

	pthread_mutex_lock(mutex);
}

/******************************************************************************
 *                                                                            *
 * Purpose: unlock thread based timekeeper sync object                        *
 *                                                                            *
 ******************************************************************************/
static void	timekeeper_thread_unlock(void *data)
{
	pthread_mutex_t	*mutex = (pthread_mutex_t *)data;

	pthread_mutex_unlock(mutex);
}

/******************************************************************************
 *                                                                            *
 * Purpose: create thread based timekeeper sync object                        *
 *                                                                            *
 * Return value: The created sync object.                                     *
 *                                                                            *
 ******************************************************************************/
static zbx_timekeeper_sync_t	*timekeeper_create_thread_sync(void)
{
	zbx_timekeeper_sync_t	*sync = (zbx_timekeeper_sync_t *)zbx_malloc(NULL, sizeof(zbx_timekeeper_sync_t));
	pthread_mutex_t		*mutex;
	int			err;

	mutex = (pthread_mutex_t *)zbx_malloc(NULL, sizeof(pthread_mutex_t));
	if (0 != (err = pthread_mutex_init(mutex, NULL)))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot initialize timekeeper mutex: %s", zbx_strerror(err));
		exit(EXIT_FAILURE);
	}

	zbx_timekeeper_sync_init(sync, timekeeper_thread_lock, timekeeper_thread_unlock, (void *)mutex);

	return sync;
}

/******************************************************************************
 *                                                                            *
 * Purpose: free thread based timekeeper sync object                          *
 *                                                                            *
 ******************************************************************************/
static void	timekeeper_free_thread_sync(zbx_timekeeper_sync_t *sync)
{
	pthread_mutex_t	*mutex = (pthread_mutex_t *)sync->data;

	pthread_mutex_destroy(mutex);
	zbx_free(mutex);
	zbx_free(sync);
}

/******************************************************************************
 *                                                                            *
 * Purpose: create timekeeper for process/thread load self monitoring         *
 *                                                                            *
 * Parameters: units_num - [IN] the number of monitored processes/threads     *
 *             sync      - [IN] the sync object (optional). If NULL, then     *
 *                              internal thread based sync object will be     *
 *                              created.                                      *
 *             mem_malloc_func  - the memory management functions             *
 *             mem_realloc_func -                                             *
 *             mem_free_func    -                                             *
 *                                                                            *
 * Return value: The created timekeeper object.                               *
 *                                                                            *
 ******************************************************************************/
zbx_timekeeper_t	*zbx_timekeeper_create_ext(int units_num, zbx_timekeeper_sync_t *sync,
		zbx_mem_malloc_func_t mem_malloc_func, zbx_mem_realloc_func_t mem_realloc_func,
		zbx_mem_free_func_t mem_free_func)
{
	zbx_timekeeper_t	*timekeeper;

	timekeeper = (zbx_timekeeper_t *)mem_malloc_func(NULL, sizeof(zbx_timekeeper_t));
	timekeeper->units = (zbx_timekeeper_unit_t *)mem_malloc_func(NULL, sizeof(zbx_timekeeper_unit_t) *
			(size_t)units_num);
	memset(timekeeper->units, 0, sizeof(zbx_timekeeper_unit_t) * (size_t)units_num);
	timekeeper->units_num = units_num;
	timekeeper->first = 0;
	timekeeper->count = 0;
	timekeeper->ticks_per_sec = sysconf(_SC_CLK_TCK);
	timekeeper->ticks_sync = 0;

	if (NULL == sync)
	{
		sync = timekeeper_create_thread_sync();
		timekeeper->sync_source = TIMEKEEPER_SYNC_INTERNAL;
	}
	else
		timekeeper->sync_source = TIMEKEEPER_SYNC_EXTERNAL;

	timekeeper->sync = sync;

	timekeeper->mem_malloc_func = mem_malloc_func;
	timekeeper->mem_realloc_func = mem_realloc_func;
	timekeeper->mem_free_func = mem_free_func;

	return timekeeper;
}

/******************************************************************************
 *                                                                            *
 * Purpose: create timekeeper for process/thread load self monitoring         *
 *                                                                            *
 * Parameters: units_num - [IN] the number of monitored processes/threads     *
 *             sync      - [IN] the sync object (optional). If NULL, then     *
 *                              internal thread based sync object will be     *
 *                              created.                                      *
 *                                                                            *
 * Return value: The created timekeeper object.                               *
 *                                                                            *
 ******************************************************************************/
zbx_timekeeper_t	*zbx_timekeeper_create(int units_num, zbx_timekeeper_sync_t *sync)
{
	return zbx_timekeeper_create_ext(units_num, sync, ZBX_DEFAULT_MEM_MALLOC_FUNC,
			ZBX_DEFAULT_MEM_REALLOC_FUNC, ZBX_DEFAULT_MEM_FREE_FUNC);
}

/******************************************************************************
 *                                                                            *
 * Purpose: free timekeeper object                                            *
 *                                                                            *
 ******************************************************************************/
void	zbx_timekeeper_free(zbx_timekeeper_t *timekeeper)
{
	if (TIMEKEEPER_SYNC_INTERNAL == timekeeper->sync_source)
		timekeeper_free_thread_sync(timekeeper->sync);

	timekeeper->mem_free_func(timekeeper->units);
	timekeeper->mem_free_func(timekeeper);
}

/******************************************************************************
 *                                                                            *
 * Purpose: update process/thread state                                       *
 *                                                                            *
 * Parameters: timekeeper - [IN] the timekeeper                               *
 *             index      - [IN] the process/thread index                     *
 *             state      - [IN] the new process/thread state                 *
 *                                (see  ZBX_PROCESS_STATE_* defines)          *
 *                                                                            *
 * Comments: This function is called by process/threads whenever they         *
 *           busy/idle state changes. It updates local cache and flushes it   *
 *           once per half timekeeper delay interval.                         *
 *                                                                            *
 ******************************************************************************/
void	zbx_timekeeper_update(zbx_timekeeper_t *timekeeper, int index, unsigned char state)
{
	zbx_timekeeper_unit_t	*unit;
	clock_t			ticks;

	if (0 > index || index >= timekeeper->units_num)
		return;

	unit = timekeeper->units + index;

	if (-1 == (ticks = zbx_times()))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot get process times: %s", zbx_strerror(errno));
		unit->cache.state = state;
		return;
	}

	if (0 == unit->cache.ticks_flush)
	{
		unit->cache.ticks_flush = ticks;
		unit->cache.state = state;
		unit->cache.ticks = ticks;
		return;
	}

	/* update process statistics in local cache */
	unit->cache.counter[unit->cache.state] += (zbx_uint64_t)(ticks - unit->cache.ticks);

	if (ZBX_TIMEKEEPER_FLUSH_DELAY < (double)(ticks - unit->cache.ticks_flush) / (double)timekeeper->ticks_per_sec)
	{
		timekeeper->sync->lock(timekeeper->sync->data);

		for (int s = 0; s < ZBX_PROCESS_STATE_COUNT; s++)
		{
			/* If process did not update statistic counter during one timekeeper data         */
			/* collection interval, then self timekeeper will collect statistics based on the */
			/* current process state and the ticks passed since last timekeeper data          */
			/* collection. This value is stored in counter_used and the local statistics      */
			/* must be adjusted by this (already collected) value.                            */
			if (unit->cache.counter[s] > unit->counter_used[s])
			{
				unit->cache.counter[s] -= unit->counter_used[s];
				unit->counter[s] += unit->cache.counter[s];
			}

			/* reset current cache statistics */
			unit->counter_used[s] = 0;
			unit->cache.counter[s] = 0;
		}

		unit->cache.ticks_flush = ticks;

		timekeeper->sync->unlock(timekeeper->sync->data);
	}

	/* update local timekeeper cache */
	unit->cache.state = state;
	unit->cache.ticks = ticks;
}

/******************************************************************************
 *                                                                            *
 * Purpose: collect process/thread load statistics                            *
 *                                                                            *
 * Parameters: timekeeper - [IN] the timekeeper                               *
 *                                                                            *
 * Comments: This function is called by 'manager' process once per timekeeper *
 *           delay interval.                                                  *
 *                                                                            *
 ******************************************************************************/
void	zbx_timekeeper_collect(zbx_timekeeper_t *timekeeper)
{
	zbx_timekeeper_unit_t	*unit;
	clock_t			ticks, ticks_done;
	int			i, index, last;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (-1 == (ticks = zbx_times()))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot get process times: %s", zbx_strerror(errno));
		goto out;
	}

	if (0 == timekeeper->ticks_sync)
	{
		timekeeper->ticks_sync = ticks;
		goto out;
	}

	if (MAX_HISTORY <= (index = timekeeper->first + timekeeper->count))
		index -= MAX_HISTORY;

	if (timekeeper->count < MAX_HISTORY)
		timekeeper->count++;
	else if (++timekeeper->first == MAX_HISTORY)
		timekeeper->first = 0;

	if (0 > (last = index - 1))
		last += MAX_HISTORY;

	timekeeper->sync->lock(timekeeper->sync->data);

	ticks_done = ticks - timekeeper->ticks_sync;

	for (i = 0; i < timekeeper->units_num; i++)
	{
		unit = timekeeper->units + i;

		if (unit->cache.ticks_flush < timekeeper->ticks_sync)
		{
			/* If the process local cache was not flushed during the last timekeeper       */
			/* data collection interval update the process statistics based on the current */
			/* process state and ticks passed during the collection interval.              */
			/* This will serve as good estimate until the timekeeper cache is flushed and  */
			/* its counters adjusted by those values.                                      */
			unit->counter[unit->cache.state] += (zbx_uint64_t)ticks_done;

			/* store the estimated ticks to adjust the counters when flushing local cache */
			unit->counter_used[unit->cache.state] += (zbx_uint64_t)ticks_done;
		}

		for (int s = 0; s < ZBX_PROCESS_STATE_COUNT; s++)
		{
			/* The data is gathered as ticks spent in corresponding states during the */
			/* timekeeper data collection interval. But in history the data are       */
			/* stored as relative values. To achieve it we add the collected data to  */
			/* the last values.                                                       */
			unit->h_counter[s][index] = unit->h_counter[s][last] + unit->counter[s];
			unit->counter[s] = 0;
		}
	}

	timekeeper->ticks_sync = ticks;
	timekeeper->sync->unlock(timekeeper->sync->data);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get the required space for timekeeper object using internal       *
 *          memory allocator                                                  *
 *                                                                            *
 * Parameters: units_num - [IN] the number of monitored processes/threads     *
 *                                                                            *
 * Return value: The number of bytes required to allocate timekeeper object   *
 *               using internal memory allocator.                             *
 *                                                                            *
 ******************************************************************************/
size_t	zbx_timekeeper_get_memmalloc_size(int units_num)
{
#define TIMEKEEPER_ALIGN8(x) (((x) + 7) & (~7u))
	/* timekeeper size, units array size + overhead for 2 allocations: timekeeper and units array */
	return TIMEKEEPER_ALIGN8(sizeof(zbx_timekeeper_t)) + TIMEKEEPER_ALIGN8(sizeof(zbx_timekeeper_unit_t)) *
			(size_t)units_num + 4 * sizeof(zbx_uint64_t);
#undef TIMEKEEPER_ALIGN8
}

/******************************************************************************
 *                                                                            *
 * Purpose: get statistics for specified process(es)/thread(s)                *
 *                                                                            *
 * Parameters: timekeeper - [IN] the timekeeper                               *
 *             unit_index - [IN] the target process/thread                    *
 *             count      - [IN] the number of processes/threads              *
 *             aggr_func  - [IN] the aggregation function,                    *
 *                               see ZBX_TIMEKEEPER_AGGR_FUNC_* defines       *
 *             state      - [IN] the required state,                          *
 *                               see ZBX_PROCESS_STATE_* defines              *
 *             value      - [OUT] the process load statistics in %            *
 *             error      - [OUT] the error message                           *
 *                                                                            *
 * Return value: SUCCEED - the statistics were retrieved successfully         *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_timekeeper_get_stat(zbx_timekeeper_t *timekeeper, int unit_index, int count, unsigned char aggr_func,
		unsigned char state, double *value, char **error)
{
	unsigned int	total = 0, counter = 0;
	int		i, current, ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() index:%d count:%d", __func__, unit_index, count);

	if (0 > unit_index || unit_index >= timekeeper->units_num)
	{
		*error = zbx_strdup(NULL, "index out of bounds");
		goto out;
	}

	if (0 == count)
		count = timekeeper->units_num - unit_index;

	switch (aggr_func)
	{
		case ZBX_TIMEKEEPER_AGGR_FUNC_ONE:
			count = 1;
			break;
		case ZBX_TIMEKEEPER_AGGR_FUNC_AVG:
		case ZBX_TIMEKEEPER_AGGR_FUNC_MAX:
		case ZBX_TIMEKEEPER_AGGR_FUNC_MIN:
			break;
		default:
			*error = zbx_strdup(NULL, "unknown aggregation function");
			goto out;
	}

	timekeeper->sync->lock(timekeeper->sync->data);

	if (1 >= timekeeper->count)
		goto unlock;

	if (MAX_HISTORY <= (current = (timekeeper->first + timekeeper->count - 1)))
		current -= MAX_HISTORY;

	for (i = unit_index; i < unit_index + count; i++)
	{
		unsigned int	one_total = 0, one_counter;
		unsigned char	s;

		for (s = 0; s < ZBX_PROCESS_STATE_COUNT; s++)
		{
			one_total += (unsigned short)(timekeeper->units[i].h_counter[s][current] -
					timekeeper->units[i].h_counter[s][timekeeper->first]);
		}

		one_counter = (unsigned short)(timekeeper->units[i].h_counter[state][current] -
				timekeeper->units[i].h_counter[state][timekeeper->first]);

		switch (aggr_func)
		{
			case ZBX_TIMEKEEPER_AGGR_FUNC_ONE:
			case ZBX_TIMEKEEPER_AGGR_FUNC_AVG:
				total += one_total;
				counter += one_counter;
				break;
			case ZBX_TIMEKEEPER_AGGR_FUNC_MAX:
				if (0 == i || one_counter > counter)
				{
					counter = one_counter;
					total = one_total;
				}
				break;
			case ZBX_TIMEKEEPER_AGGR_FUNC_MIN:
				if (0 == i || one_counter < counter)
				{
					counter = one_counter;
					total = one_total;
				}
				break;
		}
	}

unlock:
	timekeeper->sync->unlock(timekeeper->sync->data);

	*value = (0 == total ? 0 : 100. * (double)counter / (double)total);

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get usage statistics for all monitored processes/threads          *
 *                                                                            *
 * Parameters: timekeeper - [IN] the timekeeper                               *
 *             usage      - [IN] the busy % for all monitored units           *
 *                                                                            *
 * Return value: SUCCEED - the statistics were retrieved successfully         *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_timekeeper_get_usage(zbx_timekeeper_t *timekeeper, zbx_vector_dbl_t *usage)
{
	int	current, ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_dbl_reserve(usage, (size_t)timekeeper->units_num);

	timekeeper->sync->lock(timekeeper->sync->data);

	if (1 >= timekeeper->count)
		goto out;

	if (MAX_HISTORY <= (current = (timekeeper->first + timekeeper->count - 1)))
		current -= MAX_HISTORY;

	for (int i = 0; i < timekeeper->units_num; i++)
	{
		unsigned int	total = 0, busy;
		unsigned char	s;

		for (s = 0; s < ZBX_PROCESS_STATE_COUNT; s++)
		{
			total += (unsigned short)(timekeeper->units[i].h_counter[s][current] -
					timekeeper->units[i].h_counter[s][timekeeper->first]);
		}

		busy = (unsigned short)(timekeeper->units[i].h_counter[ZBX_PROCESS_STATE_BUSY][current] -
				timekeeper->units[i].h_counter[ZBX_PROCESS_STATE_BUSY][timekeeper->first]);

		zbx_vector_dbl_append(usage, (0 == total ? 0 : 100. * (double)busy / (double)total));
	}

	ret = SUCCEED;
out:
	timekeeper->sync->unlock(timekeeper->sync->data);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return ret;
}


/******************************************************************************
 *                                                                            *
 * Purpose: get raw counters for all processes/threads                        *
 *                                                                            *
 * Parameters: timekeeper - [IN] the timekeeper                               *
 *                                                                            *
 * Return value: The counters, must be freed by the caller.                   *
 *                                                                            *
 ******************************************************************************/
zbx_timekeeper_state_t	*zbx_timekeeper_get_counters(zbx_timekeeper_t *timekeeper)
{
	int			current, ret = FAIL;
	zbx_timekeeper_state_t	*units = (zbx_timekeeper_state_t *)zbx_malloc(NULL, (size_t)timekeeper->units_num *
					sizeof(zbx_timekeeper_state_t));

	timekeeper->sync->lock(timekeeper->sync->data);

	if (1 < timekeeper->count)
	{
		if (MAX_HISTORY <= (current = (timekeeper->first + timekeeper->count - 1)))
			current -= MAX_HISTORY;

		for (int i = 0; i < timekeeper->units_num; i++)
		{
			for (int s = 0; s < ZBX_PROCESS_STATE_COUNT; s++)
			{
				units[i].counter[s] = (unsigned short)(timekeeper->units[i].h_counter[s][current] -
						timekeeper->units[i].h_counter[s][timekeeper->first]);
			}
		}

		ret = SUCCEED;
	}

	timekeeper->sync->unlock(timekeeper->sync->data);

	if (SUCCEED != ret)
		zbx_free(units);

	return units;
}
