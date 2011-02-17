/*
** ZABBIX
** Copyright (C) 2000-2011 SIA Zabbix
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
zbx_sm_collector_t;

static zbx_sm_collector_t	*sm_collector = NULL;
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
extern int	CONFIG_DBSYNCER_FORKS;
extern int	CONFIG_DISCOVERER_FORKS;
extern int	CONFIG_ALERTER_FORKS;
extern int	CONFIG_TIMER_FORKS;

/******************************************************************************
 *                                                                            *
 * Function: get_process_forks                                                *
 *                                                                            *
 * Purpose: Returns number of processes depending on process type             *
 *                                                                            *
 * Parameters: process_type - [IN] process type; ZBX_PROCESS_TYPE_*           *
 *                                                                            *
 * Return value: number of processes                                          *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int	get_process_forks(unsigned char process_type)
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
		case ZBX_PROCESS_TYPE_DBSYNCER:
			return CONFIG_DBSYNCER_FORKS;
		case ZBX_PROCESS_TYPE_DISCOVERER:
			return CONFIG_DISCOVERER_FORKS;
		case ZBX_PROCESS_TYPE_ALERTER:
			return CONFIG_ALERTER_FORKS;
		case ZBX_PROCESS_TYPE_TIMER:
			return CONFIG_TIMER_FORKS;
	}

	assert(0);
}

/******************************************************************************
 *                                                                            *
 * Function: init_collector_data                                              *
 *                                                                            *
 * Purpose: Initialize structures and prepare state                           *
 *          for self-monitoring collector                                     *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	init_sm_collector()
{
	const char	*__function_name = "init_sm_collector";
	size_t		sz, sz_array, sz_process[ZBX_PROCESS_TYPE_COUNT], sz_total;
	key_t		shm_key;
	char		*p;
	clock_t		ticks;
	struct tms	buf;
	unsigned char	process_type;
	int		process_num, process_forks;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	sz_total = sz = sizeof(zbx_sm_collector_t);
	sz_total += sz_array = sizeof(zbx_stat_process_t *) * ZBX_PROCESS_TYPE_COUNT;
	for (process_type = 0; process_type < ZBX_PROCESS_TYPE_COUNT; process_type++)
		sz_total += sz_process[process_type] = sizeof(zbx_stat_process_t) * get_process_forks(process_type);

	zabbix_log(LOG_LEVEL_DEBUG, "%s() size:%d", __function_name, (int)sz_total);

	if (-1 == (shm_key = zbx_ftok(CONFIG_FILE, ZBX_IPC_SELF_STATS_ID)))
	{
		zabbix_log(LOG_LEVEL_CRIT, "Cannot create IPC key for a self-monitoring collector");
		exit(FAIL);
	}

	if (ZBX_MUTEX_ERROR == zbx_mutex_create_force(&sm_lock, ZBX_MUTEX_SM))
	{
		zbx_error("Unable to create mutex for a self-monitoring collector");
		exit(FAIL);
	}

	if (-1 == (shm_id = zbx_shmget(shm_key, sz_total)))
	{
		zabbix_log(LOG_LEVEL_CRIT, "Cannot allocate shared memory for a self-monitoring collector");
		exit(FAIL);
	}

	if ((void *)(-1) == (p = shmat(shm_id, NULL, 0)))
	{
		zabbix_log(LOG_LEVEL_CRIT, "Cannot attach shared memory for a self-monitoring collector [%s]",
				strerror(errno));
		exit(FAIL);
	}

	sm_collector = (zbx_sm_collector_t *)p; p += sz;
	sm_collector->process = (zbx_stat_process_t **)p; p += sz_array;

	ticks = times(&buf);

	for (process_type = 0; process_type < ZBX_PROCESS_TYPE_COUNT; process_type++)
	{
		sm_collector->process[process_type] = (zbx_stat_process_t *)p; p += sz_process[process_type];
		memset(sm_collector->process[process_type], 0, sz_process[process_type]);

		process_forks = get_process_forks(process_type);
		for (process_num = 0; process_num < process_forks; process_num++)
		{
			sm_collector->process[process_type][process_num].last_ticks = ticks;
			sm_collector->process[process_type][process_num].last_state = ZBX_PROCESS_STATE_BUSY;
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() sm_collector:%p", __function_name, sm_collector);
}

/******************************************************************************
 *                                                                            *
 * Function: free_sm_collector                                                *
 *                                                                            *
 * Purpose: Free memory allocated for self-monitoring collector               *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	free_sm_collector()
{
	const char	*__function_name = "free_sm_collector";

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() sm_collector:%p", __function_name, sm_collector);

	if (NULL == sm_collector)
		return;

	LOCK_SM;

	sm_collector = NULL;

	if (-1 == shmctl(shm_id, IPC_RMID, 0))
		zabbix_log(LOG_LEVEL_WARNING, "Cannot remove shared memory for self-monitoring collector [%s]",
				strerror(errno));

	UNLOCK_SM;

	zbx_mutex_destroy(&sm_lock);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: update_sm_counter                                                *
 *                                                                            *
 * Purpose:                                                                   *
 *                                                                            *
 * Parameters: state - [IN] new process state; ZBX_PROCESS_STATE_*            *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	update_sm_counter(unsigned char state)
{
	extern int		process_num;
	extern unsigned char	process_type;

	zbx_stat_process_t	*process;
	clock_t			ticks;
	struct tms		buf;

	if (ZBX_PROCESS_TYPE_UNKNOWN == process_type)
		return;

	process = &sm_collector->process[process_type][process_num - 1];
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
 * Function: collect_selfstats                                                *
 *                                                                            *
 * Purpose:                                                                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	collect_selfstats()
{
	const char		*__function_name = "collect_selfstats";
	zbx_stat_process_t	*process;
	clock_t			ticks;
	struct tms		buf;
	unsigned char		process_type, state;
	int			process_num, process_forks, index;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	ticks = times(&buf);

	LOCK_SM;

	index = (sm_collector->first + sm_collector->count) % MAX_HISTORY;

	if (sm_collector->count < MAX_HISTORY)
		sm_collector->count++;
	else if (++sm_collector->first == MAX_HISTORY)
		sm_collector->first = 0;

	for (process_type = 0; process_type < ZBX_PROCESS_TYPE_COUNT; process_type++)
	{
		process_forks = get_process_forks(process_type);
		for (process_num = 0; process_num < process_forks; process_num++)
		{
			process = &sm_collector->process[process_type][process_num];
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
 * Function: get_selfstats                                                    *
 *                                                                            *
 * Purpose: calculate statistics for selected process                         *
 *                                                                            *
 * Parameters: process_type - [IN] type of process; ZBX_PROCESS_TYPE_*        *
 *             process_num  - [IN] process number; 1 - first process;         *
 *                                 0 - all processes                          *
 *             state        - [IN] process state; ZBX_PROCESS_STATE_*         *
 *             value        - [OUT] a pointer to a variable that receives     *
 *                                  requested statistics                      *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int	get_selfstats(unsigned char process_type, int process_num, unsigned char state, double *value)
{
	const char		*__function_name = "get_selfstats";
	unsigned int		total = 0, counter = 0;
	zbx_stat_process_t	*process;
	unsigned char		s;
	int			process_forks, current, res = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	process_forks = get_process_forks(process_type);

	if (0 != process_num)
	{
		if (process_num > process_forks)
		{
			res = NOTSUPPORTED;
			goto exit;
		}

		process_forks = process_num--;
	}

	LOCK_SM;

	if (0 == sm_collector->count)
		goto unlock;

	current = (sm_collector->first + sm_collector->count - 1) % MAX_HISTORY;

	for (; process_num < process_forks; process_num++)
	{
		process = &sm_collector->process[process_type][process_num];

		for (s = 0; s < ZBX_PROCESS_STATE_COUNT; s++)
			total += (unsigned short)(process->h_counter[s][current] -
					process->h_counter[s][sm_collector->first]);
		counter += (unsigned short)(process->h_counter[state][current] -
				process->h_counter[state][sm_collector->first]);
	}

unlock:
	UNLOCK_SM;

	*value = (0 == total ? 0 : 100. * (double)counter / (double)total);

exit:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(res));

	return res;
}
