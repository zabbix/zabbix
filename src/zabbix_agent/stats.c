/* 
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
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
#include "stats.h"

#include "log.h"
#include "mutexs.h"
#include "zbxconf.h"

#include "interfaces.h"
#include "diskdevices.h"
#include "cpustat.h"
#include "perfstat.h"
#include "log.h"
#include "cfg.h"

#if defined(_WINDOWS)
#	include "service.h"
#else
#	include "daemon.h"
#endif /* _WINDOWS */

ZBX_COLLECTOR_DATA *collector = NULL;

#define ZBX_GET_SHM_KEY(smk_key) 														\
	{if( -1 == (shm_key = ftok(CONFIG_FILE, (int)'z') )) 										\
        { 																\
                zbx_error("Can not create IPC key for path '%s', try to create for path '.' [%s]", CONFIG_FILE, strerror(errno)); 	\
                if( -1 == (shm_key = ftok(".", (int)'z') )) 										\
                { 															\
                        zbx_error("Can not create IPC key for path '.' [%s]", strerror(errno)); 					\
                        exit(1); 													\
                } 															\
        }}

/******************************************************************************
 *                                                                            *
 * Function: init_collector_data                                              *
 *                                                                            *
 * Purpose: Allocate memory for collector                                     *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: Linux version allocate memory as shared.                         *
 *                                                                            *
 ******************************************************************************/

void	init_collector_data(void)
{
#if defined (_WINDOWS)

	collector = zbx_malloc(collector, sizeof(ZBX_COLLECTOR_DATA));

	memset(collector, 0, sizeof(ZBX_COLLECTOR_DATA));

#else /* not _WINDOWS */

#define ZBX_MAX_ATTEMPTS 10
	int	attempts = 0;

	key_t	shm_key;
	int	shm_id;

	ZBX_GET_SHM_KEY(shm_key);

lbl_create:
	if ( -1 == (shm_id = shmget(shm_key, sizeof(ZBX_COLLECTOR_DATA), IPC_CREAT | IPC_EXCL | 0666 /* 0022 */)) )
	{
		if( EEXIST == errno )
		{
			zabbix_log(LOG_LEVEL_DEBUG, "Shared memory already exists for collector, trying to recreate.");

			shm_id = shmget(shm_key, 0 /* get reference */, 0666 /* 0022 */);

			shmctl(shm_id, IPC_RMID, 0);
			if ( ++attempts > ZBX_MAX_ATTEMPTS )
			{
				zabbix_log(LOG_LEVEL_CRIT, "Can't recreate shared memory for collector. [too many attempts]");
				exit(1);
			}
			if ( attempts > (ZBX_MAX_ATTEMPTS / 2) )
			{
				zabbix_log(LOG_LEVEL_DEBUG, "Wait 1 sec for next attemtion of collector shared memory allocation.");
				zbx_sleep(1);
			}
			goto lbl_create;
		}
		else
		{
			zabbix_log(LOG_LEVEL_CRIT, "Can't allocate shared memory for collector. [%s]",strerror(errno));
			exit(1);
		}
	}
	
	collector = shmat(shm_id, 0, 0);

	if ((void*)(-1) == collector)
	{
		zabbix_log(LOG_LEVEL_CRIT, "Can't attache shared memory for collector. [%s]",strerror(errno));
		exit(1);
	}

#endif /* _WINDOWS */
}

/******************************************************************************
 *                                                                            *
 * Function: free_collector_data                                              *
 *                                                                            *
 * Purpose: Free memory aloccated for collector                               *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: Linux version allocate memory as shared.                         *
 *                                                                            *
 ******************************************************************************/

void	free_collector_data(void)
{

#if defined (_WINDOWS)

	zbx_free(collector);

#else /* not _WINDOWS */

	key_t	shm_key;
	int	shm_id;

	if(NULL == collector) return;
	
	ZBX_GET_SHM_KEY(shm_key);

	shm_id = shmget(shm_key, sizeof(ZBX_COLLECTOR_DATA), 0);

	if (-1 == shm_id)
	{
		zabbix_log(LOG_LEVEL_ERR, "Can't find shared memory for collector. [%s]",strerror(errno));
		exit(1);
	}

	shmctl(shm_id, IPC_RMID, 0);

#endif /* _WINDOWS */

	collector = NULL;
}


/******************************************************************************
 *                                                                            *
 * Function: collector_thread                                                 *
 *                                                                            *
 * Purpose: Collect system information                                        *
 *                                                                            *
 * Parameters:  args - skipped                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/

ZBX_THREAD_ENTRY(collector_thread, args)
{
	zabbix_log( LOG_LEVEL_INFORMATION, "zabbix_agentd collector started");

	init_cpu_collector(&(collector->cpus));
	init_perf_collector(&(collector->perfs));

	while(ZBX_IS_RUNNING)
	{
		collect_cpustat(&(collector->cpus));
		collect_perfstat(&(collector->perfs));

		collect_stats_interfaces(&(collector->interfaces));
		collect_stats_diskdevices(&(collector->diskdevices));

		zbx_sleep(1);
	}

	close_perf_collector(&(collector->perfs));
	close_cpu_collector(&(collector->cpus));

	zabbix_log( LOG_LEVEL_INFORMATION, "zabbix_agentd collector stopped");

	ZBX_DO_EXIT();

	zbx_tread_exit(0);
}
