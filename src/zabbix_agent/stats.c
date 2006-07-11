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

#ifndef WIN32
#warning REMOVE sustem includes fron here!
#include <sys/types.h>
#include <sys/ipc.h>
#include <sys/shm.h>
#endif

#include "log.h"
#include "mutexs.h"
#include "zbxconf.h"

#include "interfaces.h"
#include "diskdevices.h"
#include "cpustat.h"
#include "log.h"
#include "cfg.h"

#if defined(ZABBIX_SERVICE)
#	include "service.h"
#elif defined(ZABBIX_DAEMON) /* ZABBIX_SERVICE */
#	include "daemon.h"
#endif /* ZABBIX_DAEMON */

ZBX_COLLECTOR_DATA *collector = NULL;

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
#if defined (WIN32)

	collector = calloc(1, sizeof(ZBX_COLLECTOR_DATA));

	if(NULL == collector)
	{
		zabbix_log(LOG_LEVEL_CRIT, "Can't allocate memory for collector.");
		exit(1);

	}

#else /* not WIN32 */

	key_t	shm_key;
	int	shm_id;

//	shm_key = ftok("/tmp/zbxshm", (int)'z');
        if( -1 == (shm_key = ftok(CONFIG_FILE, (int)'z') ))
        {
                zbx_error("Can not create IPC key for path '%s', try to create for path '.' [%s]", CONFIG_FILE, strerror(errno));
                if( -1 == (shm_key = ftok(".", (int)'z') ))
                {
                        zbx_error("Can not create IPC key for path '.' [%s]", strerror(errno));
                        return ZBX_MUTEX_ERROR;
                }
        }

	shm_id = shmget(shm_key, sizeof(ZBX_COLLECTOR_DATA), IPC_CREAT | 0666);

	if (-1 == shm_id)
	{
		zabbix_log(LOG_LEVEL_CRIT, "Can't allocate shared memory for collector. [%s]",strerror(errno));
		exit(1);
	}

	collector = shmat(shm_id, 0, 0);

	if ((void*)(-1) == collector)
	{
		zabbix_log(LOG_LEVEL_CRIT, "Can't attache shared memory for collector. [%s]",strerror(errno));
		exit(1);
	}

#endif /* WIN32 */
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
	if(NULL == collector) return;

#if defined (WIN32)

	free(collector);

#else /* not WIN32 */

	key_t	shm_key;
	int	shm_id;

	shm_key = ftok("/tmp/zbxshm", 'z');

	shm_id = shmget(shm_key, sizeof(ZBX_COLLECTOR_DATA), 0);

	if (-1 == shm_id)
	{
		zabbix_log(LOG_LEVEL_ERR, "Can't find shared memory for collector. [%s]",strerror(errno));
		exit(1);
	}

	shmctl(shm_id, IPC_RMID, 0);

#endif /* WIN32 */

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

	while(ZBX_IS_RUNNING)
	{
		collect_cpustat(&(collector->cpus));

		collect_stats_interfaces(&(collector->interfaces));
		collect_stats_diskdevices(&(collector->diskdevices));

		zbx_sleep(1);
	}

	close_cpu_collector(&(collector->cpus));

	zabbix_log( LOG_LEVEL_INFORMATION, "zabbix_agentd collector stopped");

	ZBX_DO_EXIT();

	zbx_tread_exit(0);
}
