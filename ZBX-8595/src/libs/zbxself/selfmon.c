/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
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

#include "zbxself.h"
#include "common.h"
#include "mutexs.h"
#include "ipc.h"
#include "log.h"

#define MAX_HISTORY	60

typedef struct
{
	unsigned short	h_counter[ZBX_PROCESS_STATE_COUNT][MAX_HISTORY];
	unsigned short	counter[ZBX_PROCESS_STATE_COUNT];
	clock_t		last_ticks;
	unsigned char	last_state;
}
zbx_stat_process_t;

typedef struct
{
	zbx_stat_process_t	**process;
	int			first;
	int			count;
}
zbx_selfmon_collector_t;

static zbx_selfmon_collector_t	*collector = NULL;
static int			shm_id;

#define	LOCK_SM		zbx_mutex_lock(&sm_lock)
#define	UNLOCK_SM	zbx_mutex_unlock(&sm_lock)

static ZBX_MUTEX	sm_lock;

extern char	*CONFIG_FILE;
extern int	CONFIG_POLLER_FORKS;
extern int	CONFIG_PINGER_FORKS;
extern int	CONFIG_IPMIPOLLER_FORKS;
extern int	CONFIG_UNREACHABLE_POLLER_FORKS;
extern int	CONFIG_HTTPPOLLER_FORKS;
extern int	CONFIG_TRAPPER_FORKS;
extern int	CONFIG_PROXYPOLLER_FORKS;
extern int	CONFIG_ESCALATOR_FORKS;
extern int	CONFIG_HISTSYNCER_FORKS;
extern int	CONFIG_DISCOVERER_FORKS;
extern int	CONFIG_ALERTER_FORKS;
extern int	CONFIG_TIMER_FORKS;
extern int	CONFIG_NODEWATCHER_FORKS;
extern int	CONFIG_HOUSEKEEPER_FORKS;
extern int	CONFIG_WATCHDOG_FORKS;
extern int	CONFIG_DATASENDER_FORKS;
extern int	CONFIG_CONFSYNCER_FORKS;
extern int	CONFIG_HEARTBEAT_FORKS;
extern int	CONFIG_SELFMON_FORKS;

/******************************************************************************
 *                                                                            *
 * Function: get_process_type_forks                                           *
 *                                                                            *
 * Purpose: Returns number of processes depending on process type             *
 *                                                                            *
 * Parameters: process_type - [IN] process type; ZBX_PROCESS_TYPE_*           *
 *                                                                            *
 * Return value: number of processes                                          *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
int	get_process_type_forks(unsigned char process_type)
{
	switch (process_type)
	{
		case ZBX_PROCESS_TYPE_POLLER:
			return CONFIG_POLLER_FORKS;
		case ZBX_PROCESS_TYPE_UNREACHABLE:
			return CONFIG_UNREACHABLE_POLLER_FORKS;
		case ZBX_PROCESS_TYPE_IPMIPOLLER:
			return CONFIG_IPMIPOLLER_FORKS;
		case ZBX_PROCESS_TYPE_PINGER:
			return CONFIG_PINGER_FORKS;
		case ZBX_PROCESS_TYPE_HTTPPOLLER:
			return CONFIG_HTTPPOLLER_FORKS;
		case ZBX_PROCESS_TYPE_TRAPPER:
			return CONFIG_TRAPPER_FORKS;
		case ZBX_PROCESS_TYPE_PROXYPOLLER:
			return CONFIG_PROXYPOLLER_FORKS;
		case ZBX_PROCESS_TYPE_ESCALATOR:
			return CONFIG_ESCALATOR_FORKS;
		case ZBX_PROCESS_TYPE_HISTSYNCER:
			return CONFIG_HISTSYNCER_FORKS;
		case ZBX_PROCESS_TYPE_DISCOVERER:
			return CONFIG_DISCOVERER_FORKS;
		case ZBX_PROCESS_TYPE_ALERTER:
			return CONFIG_ALERTER_FORKS;
		case ZBX_PROCESS_TYPE_TIMER:
			return CONFIG_TIMER_FORKS;
		case ZBX_PROCESS_TYPE_NODEWATCHER:
			return CONFIG_NODEWATCHER_FORKS;
		case ZBX_PROCESS_TYPE_HOUSEKEEPER:
			return CONFIG_HOUSEKEEPER_FORKS;
		case ZBX_PROCESS_TYPE_WATCHDOG:
			return CONFIG_WATCHDOG_FORKS;
		case ZBX_PROCESS_TYPE_DATASENDER:
			return CONFIG_DATASENDER_FORKS;
		case ZBX_PROCESS_TYPE_CONFSYNCER:
			return CONFIG_CONFSYNCER_FORKS;
		case ZBX_PROCESS_TYPE_HEARTBEAT:
			return CONFIG_HEARTBEAT_FORKS;
		case ZBX_PROCESS_TYPE_SELFMON:
			return CONFIG_SELFMON_FORKS;
	}

	assert(0);
}

/******************************************************************************
 *                                                                            *
 * Function: get_process_type_string                                          *
 *                                                                            *
 * Purpose: Returns process name                                              *
 *                                                                            *
 * Parameters: process_type - [IN] process type; ZBX_PROCESS_TYPE_*           *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments: used in internals checks zabbix["process",...], process titles   *
 *           and log files                                                    *
 *                                                                            *
 ******************************************************************************/
const char	*get_process_type_string(unsigned char process_type)
{
	switch (process_type)
	{
		case ZBX_PROCESS_TYPE_POLLER:
			return "poller";
		case ZBX_PROCESS_TYPE_UNREACHABLE:
			return "unreachable poller";
		case ZBX_PROCESS_TYPE_IPMIPOLLER:
			return "ipmi poller";
		case ZBX_PROCESS_TYPE_PINGER:
			return "icmp pinger";
		case ZBX_PROCESS_TYPE_HTTPPOLLER:
			return "http poller";
		case ZBX_PROCESS_TYPE_TRAPPER:
			return "trapper";
		case ZBX_PROCESS_TYPE_PROXYPOLLER:
			return "proxy poller";
		case ZBX_PROCESS_TYPE_ESCALATOR:
			return "escalator";
		case ZBX_PROCESS_TYPE_HISTSYNCER:
			return "history syncer";
		case ZBX_PROCESS_TYPE_DISCOVERER:
			return "discoverer";
		case ZBX_PROCESS_TYPE_ALERTER:
			return "alerter";
		case ZBX_PROCESS_TYPE_TIMER:
			return "timer";
		case ZBX_PROCESS_TYPE_NODEWATCHER:
			return "node watcher";
		case ZBX_PROCESS_TYPE_HOUSEKEEPER:
			return "housekeeper";
		case ZBX_PROCESS_TYPE_WATCHDOG:
			return "db watchdog";
		case ZBX_PROCESS_TYPE_DATASENDER:
			return "data sender";
		case ZBX_PROCESS_TYPE_CONFSYNCER:
			return "configuration syncer";
		case ZBX_PROCESS_TYPE_HEARTBEAT:
			return "heartbeat sender";
		case ZBX_PROCESS_TYPE_SELFMON:
			return "self-monitoring";
	}

	assert(0);
}

/******************************************************************************
 *                                                                            *
 * Function: init_selfmon_collector                                           *
 *                                                                            *
 * Purpose: Initialize structures and prepare state                           *
 *          for self-monitoring collector                                     *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
void	init_selfmon_collector()
{
	const char	*__function_name = "init_selfmon_collector";
	size_t		sz, sz_array, sz_process[ZBX_PROCESS_TYPE_COUNT], sz_total;
	key_t		shm_key;
	char		*p;
	clock_t		ticks;
	struct tms	buf;
	unsigned char	process_type;
	int		process_num, process_forks;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	sz_total = sz = sizeof(zbx_selfmon_collector_t);
	sz_total += sz_array = sizeof(zbx_stat_process_t *) * ZBX_PROCESS_TYPE_COUNT;

	for (process_type = 0; ZBX_PROCESS_TYPE_COUNT > process_type; process_type++)
		sz_total += sz_process[process_type] = sizeof(zbx_stat_process_t) * get_process_type_forks(process_type);

	zabbix_log(LOG_LEVEL_DEBUG, "%s() size:" ZBX_FS_SIZE_T, __function_name, (zbx_fs_size_t)sz_total);

	if (-1 == (shm_key = zbx_ftok(CONFIG_FILE, ZBX_IPC_SELFMON_ID)))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot create IPC key for a self-monitoring collector");
		exit(FAIL);
	}

	if (ZBX_MUTEX_ERROR == zbx_mutex_create_force(&sm_lock, ZBX_MUTEX_SELFMON))
	{
		zbx_error("unable to create mutex for a self-monitoring collector");
		exit(FAIL);
	}

	if (-1 == (shm_id = zbx_shmget(shm_key, sz_total)))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot allocate shared memory for a self-monitoring collector");
		exit(FAIL);
	}

	if ((void *)(-1) == (p = shmat(shm_id, NULL, 0)))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot attach shared memory for a self-monitoring collector: %s",
				zbx_strerror(errno));
		exit(FAIL);
	}

	collector = (zbx_selfmon_collector_t *)p; p += sz;
	collector->process = (zbx_stat_process_t **)p; p += sz_array;

	ticks = times(&buf);

	for (process_type = 0; ZBX_PROCESS_TYPE_COUNT > process_type; process_type++)
	{
		collector->process[process_type] = (zbx_stat_process_t *)p; p += sz_process[process_type];
		memset(collector->process[process_type], 0, sz_process[process_type]);

		process_forks = get_process_type_forks(process_type);
		for (process_num = 0; process_num < process_forks; process_num++)
		{
			collector->process[process_type][process_num].last_ticks = ticks;
			collector->process[process_type][process_num].last_state = ZBX_PROCESS_STATE_BUSY;
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() collector:%p", __function_name, collector);
}

/******************************************************************************
 *                                                                            *
 * Function: free_selfmon_collector                                           *
 *                                                                            *
 * Purpose: Free memory allocated for self-monitoring collector               *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
void	free_selfmon_collector()
{
	const char	*__function_name = "free_selfmon_collector";

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() collector:%p", __function_name, collector);

	if (NULL == collector)
		return;

	LOCK_SM;

	collector = NULL;

	if (-1 == shmctl(shm_id, IPC_RMID, 0))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot remove shared memory for self-monitoring collector: %s",
				zbx_strerror(errno));
	}

	UNLOCK_SM;

	zbx_mutex_destroy(&sm_lock);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: update_selfmon_counter                                           *
 *                                                                            *
 * Parameters: state - [IN] new process state; ZBX_PROCESS_STATE_*            *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
void	update_selfmon_counter(unsigned char state)
{
	extern int		process_num;
	extern unsigned char	process_type;

	zbx_stat_process_t	*process;
	clock_t			ticks;
	struct tms		buf;

	if (ZBX_PROCESS_TYPE_UNKNOWN == process_type)
		return;

	process = &collector->process[process_type][process_num - 1];
	ticks = times(&buf);

	LOCK_SM;

	if (ticks > process->last_ticks)
		process->counter[process->last_state] += ticks - process->last_ticks;
	process->last_ticks = ticks;
	process->last_state = state;

	UNLOCK_SM;
}

/******************************************************************************
 *                                                                            *
 * Function: collect_selfmon_stats                                            *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
void	collect_selfmon_stats()
{
	const char		*__function_name = "collect_selfmon_stats";
	zbx_stat_process_t	*process;
	clock_t			ticks;
	struct tms		buf;
	unsigned char		process_type, state;
	int			process_num, process_forks, index;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	ticks = times(&buf);

	LOCK_SM;

	if (MAX_HISTORY <= (index = collector->first + collector->count))
		index -= MAX_HISTORY;

	if (collector->count < MAX_HISTORY)
		collector->count++;
	else if (++collector->first == MAX_HISTORY)
		collector->first = 0;

	for (process_type = 0; process_type < ZBX_PROCESS_TYPE_COUNT; process_type++)
	{
		process_forks = get_process_type_forks(process_type);
		for (process_num = 0; process_num < process_forks; process_num++)
		{
			process = &collector->process[process_type][process_num];
			for (state = 0; state < ZBX_PROCESS_STATE_COUNT; state++)
				process->h_counter[state][index] = process->counter[state];
			if (ticks > process->last_ticks)
				process->h_counter[process->last_state][index] += ticks - process->last_ticks;
		}
	}

	UNLOCK_SM;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: get_selfmon_stats                                                *
 *                                                                            *
 * Purpose: calculate statistics for selected process                         *
 *                                                                            *
 * Parameters: process_type - [IN] type of process; ZBX_PROCESS_TYPE_*        *
 *             aggr_func    - [IN] one of ZBX_AGGR_FUNC_*                     *
 *             process_num  - [IN] process number; 1 - first process;         *
 *                                 0 - all processes                          *
 *             state        - [IN] process state; ZBX_PROCESS_STATE_*         *
 *             value        - [OUT] a pointer to a variable that receives     *
 *                                  requested statistics                      *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
void	get_selfmon_stats(unsigned char process_type, unsigned char aggr_func, int process_num,
		unsigned char state, double *value)
{
	const char	*__function_name = "get_selfmon_stats";
	unsigned int	total = 0, counter = 0;
	unsigned char	s;
	int		process_forks, current;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	process_forks = get_process_type_forks(process_type);

	switch (aggr_func)
	{
		case ZBX_AGGR_FUNC_ONE:
			assert(0 < process_num && process_num <= process_forks);
			process_forks = process_num--;
			break;
		case ZBX_AGGR_FUNC_AVG:
		case ZBX_AGGR_FUNC_MAX:
		case ZBX_AGGR_FUNC_MIN:
			assert(0 == process_num && 0 < process_forks);
			break;
		default:
			assert(0);
	}

	LOCK_SM;

	if (1 >= collector->count)
		goto unlock;

	if (MAX_HISTORY <= (current = (collector->first + collector->count - 1)))
		current -= MAX_HISTORY;

	for (; process_num < process_forks; process_num++)
	{
		zbx_stat_process_t	*process;
		unsigned short		one_total = 0, one_counter;

		process = &collector->process[process_type][process_num];

		for (s = 0; s < ZBX_PROCESS_STATE_COUNT; s++)
			one_total += process->h_counter[s][current] - process->h_counter[s][collector->first];

		one_counter = process->h_counter[state][current] - process->h_counter[state][collector->first];

		switch (aggr_func)
		{
			case ZBX_AGGR_FUNC_ONE:
			case ZBX_AGGR_FUNC_AVG:
				total += one_total;
				counter += one_counter;
				break;
			case ZBX_AGGR_FUNC_MAX:
				if (0 == process_num || one_counter > counter)
				{
					counter = one_counter;
					total = one_total;
				}
				break;
			case ZBX_AGGR_FUNC_MIN:
				if (0 == process_num || one_counter < counter)
				{
					counter = one_counter;
					total = one_total;
				}
				break;
		}
	}

unlock:
	UNLOCK_SM;

	*value = (0 == total ? 0 : 100. * (double)counter / (double)total);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static int	sleep_remains;

/******************************************************************************
 *                                                                            *
 * Function: zbx_sleep_loop                                                   *
 *                                                                            *
 * Purpose: sleeping process                                                  *
 *                                                                            *
 * Parameters: sleeptime - [IN] required sleeptime, in seconds                *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
void	zbx_sleep_loop(int sleeptime)
{
#ifdef HAVE_FUNCTION_SETPROCTITLE
	extern unsigned char	process_type;
	const char		*process_type_string;
#endif

	if (0 >= sleeptime)
		return;

	sleep_remains = sleeptime;

	zabbix_log(LOG_LEVEL_DEBUG, "sleeping for %d seconds", sleep_remains);

	update_selfmon_counter(ZBX_PROCESS_STATE_IDLE);

#ifdef HAVE_FUNCTION_SETPROCTITLE
	process_type_string = get_process_type_string(process_type);
#endif

	do
	{
#ifdef HAVE_FUNCTION_SETPROCTITLE
		zbx_setproctitle("%s [sleeping for %d seconds]", process_type_string, sleep_remains);
#endif
		sleep(1);
	}
	while (0 < --sleep_remains);

	update_selfmon_counter(ZBX_PROCESS_STATE_BUSY);
}

void	zbx_wakeup()
{
	sleep_remains = 0;
}
