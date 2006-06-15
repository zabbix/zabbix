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

#include "cfg.h"
#include "stats.h"

/* Number of processed requests */
long int stats_request=0;
long int stats_request_failed = 0;
long int stats_request_accepted = 0;
long int stats_request_rejected = 0;

ZBX_THREAD_ENTRY(CollectorThread, pSemColectorStarted)
{
	FILE	*file;

	zbx_semaphore_unloc((ZBX_SEM_HANDLE *)pSemColectorStarted);

	for(;;)
	{
		file=fopen(CONFIG_STAT_FILE_TMP,"w");
		if(NULL == file)
		{
//TODO			zabbix_log( LOG_LEVEL_CRIT, "Cannot open file [%s] [%s]\n","/tmp/zabbix_agentd.tmp2", strerror(errno));
			zbx_tread_exit(1);
		}
		else
		{
			/* Here is list of functions to call periodically */
/*TODO			collect_stats_interfaces(file);
			collect_stats_diskdevices(file);
			collect_stats_cpustat(file);
*/
			fclose(file);
			rename(CONFIG_STAT_FILE_TMP, CONFIG_STAT_FILE);
		}
		zbx_sleep(1);
	}
	zbx_tread_exit(0);
}
