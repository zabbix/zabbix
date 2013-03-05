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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#ifndef _WINDOWS

#include "common.h"
#include "diskdevices.h"
#include "stats.h"
#include "log.h"
#include "mutexs.h"

extern ZBX_MUTEX		diskstats_lock;
#define LOCK_DISKSTATS		zbx_mutex_lock(&diskstats_lock)
#define UNLOCK_DISKSTATS	zbx_mutex_unlock(&diskstats_lock)

static void	apply_diskstat(ZBX_SINGLE_DISKDEVICE_DATA *device, time_t now, zbx_uint64_t *dstat)
{
	register int	i;
	time_t		clock[ZBX_AVG_COUNT], sec;
	int		index[ZBX_AVG_COUNT];

	assert(device);

	device->index++;

	if (MAX_COLLECTOR_HISTORY == device->index)
		device->index = 0;

	device->clock[device->index] = now;
	device->r_sect[device->index] = dstat[ZBX_DSTAT_R_SECT];
	device->r_oper[device->index] = dstat[ZBX_DSTAT_R_OPER];
	device->r_byte[device->index] = dstat[ZBX_DSTAT_R_BYTE];
	device->w_sect[device->index] = dstat[ZBX_DSTAT_W_SECT];
	device->w_oper[device->index] = dstat[ZBX_DSTAT_W_OPER];
	device->w_byte[device->index] = dstat[ZBX_DSTAT_W_BYTE];

	clock[ZBX_AVG1] = clock[ZBX_AVG5] = clock[ZBX_AVG15] = now + 1;
	index[ZBX_AVG1] = index[ZBX_AVG5] = index[ZBX_AVG15] = -1;

	for (i = 0; i < MAX_COLLECTOR_HISTORY; i++)
	{
		if (0 == device->clock[i])
			continue;

#define DISKSTAT(t)\
		if ((device->clock[i] >= (now - (t * 60))) && (clock[ZBX_AVG ## t] > device->clock[i]))\
		{\
			clock[ZBX_AVG ## t] = device->clock[i];\
			index[ZBX_AVG ## t] = i;\
		}

		DISKSTAT(1);
		DISKSTAT(5);
		DISKSTAT(15);
	}

#define SAVE_DISKSTAT(t)\
	if (-1 == index[ZBX_AVG ## t] || 0 == now - device->clock[index[ZBX_AVG ## t]])\
	{\
		device->r_sps[ZBX_AVG ## t] = 0;\
		device->r_ops[ZBX_AVG ## t] = 0;\
		device->r_bps[ZBX_AVG ## t] = 0;\
		device->w_sps[ZBX_AVG ## t] = 0;\
		device->w_ops[ZBX_AVG ## t] = 0;\
		device->w_bps[ZBX_AVG ## t] = 0;\
	}\
	else\
	{\
		sec = now - device->clock[index[ZBX_AVG ## t]];\
		device->r_sps[ZBX_AVG ## t] = (dstat[ZBX_DSTAT_R_SECT] - device->r_sect[index[ZBX_AVG ## t]]) / (double)sec;\
		device->r_ops[ZBX_AVG ## t] = (dstat[ZBX_DSTAT_R_OPER] - device->r_oper[index[ZBX_AVG ## t]]) / (double)sec;\
		device->r_bps[ZBX_AVG ## t] = (dstat[ZBX_DSTAT_R_BYTE] - device->r_byte[index[ZBX_AVG ## t]]) / (double)sec;\
		device->w_sps[ZBX_AVG ## t] = (dstat[ZBX_DSTAT_W_SECT] - device->w_sect[index[ZBX_AVG ## t]]) / (double)sec;\
		device->w_ops[ZBX_AVG ## t] = (dstat[ZBX_DSTAT_W_OPER] - device->w_oper[index[ZBX_AVG ## t]]) / (double)sec;\
		device->w_bps[ZBX_AVG ## t] = (dstat[ZBX_DSTAT_W_BYTE] - device->w_byte[index[ZBX_AVG ## t]]) / (double)sec;\
	}

	SAVE_DISKSTAT(1);
	SAVE_DISKSTAT(5);
	SAVE_DISKSTAT(15);
}

static void	process_diskstat(ZBX_SINGLE_DISKDEVICE_DATA *device)
{
	time_t		now;
	zbx_uint64_t	dstat[ZBX_DSTAT_MAX];

	now = time(NULL);
	if (FAIL == get_diskstat(device->name, dstat))
		return;

	apply_diskstat(device, now, dstat);
}

void	collect_stats_diskdevices()
{
	int	i;

	LOCK_DISKSTATS;
	diskstat_shm_reattach();

	for (i = 0; i < diskdevices->count; i++)
		process_diskstat(&diskdevices->device[i]);

	UNLOCK_DISKSTATS;
}

ZBX_SINGLE_DISKDEVICE_DATA	*collector_diskdevice_get(const char *devname)
{
	const char			*__function_name = "collector_diskdevice_get";
	int				i;
	ZBX_SINGLE_DISKDEVICE_DATA	*device = NULL;

	assert(devname);

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() devname:'%s'", __function_name, devname);

	LOCK_DISKSTATS;
	if (0 == DISKDEVICE_COLLECTOR_STARTED(collector))
		diskstat_shm_init();
	else
		diskstat_shm_reattach();

	for (i = 0; i < diskdevices->count; i ++)
	{
		if (0 == strcmp(devname, diskdevices->device[i].name))
		{
			device = &diskdevices->device[i];
			zabbix_log(LOG_LEVEL_DEBUG, "%s() device '%s' found", __function_name, devname);
			break;
		}
	}
	UNLOCK_DISKSTATS;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%p", __function_name, device);

	return device;
}

ZBX_SINGLE_DISKDEVICE_DATA	*collector_diskdevice_add(const char *devname)
{
	const char			*__function_name = "collector_diskdevice_add";
	ZBX_SINGLE_DISKDEVICE_DATA	*device = NULL;

	assert(devname);

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() devname:'%s'", __function_name, devname);

	LOCK_DISKSTATS;
	if (0 == DISKDEVICE_COLLECTOR_STARTED(collector))
		diskstat_shm_init();
	else
		diskstat_shm_reattach();

	if (diskdevices->count == MAX_DISKDEVICES)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() collector is full", __function_name);
		goto end;
	}

	if (diskdevices->count == diskdevices->max_diskdev)
		diskstat_shm_extend();

	device = &(diskdevices->device[diskdevices->count]);
	zbx_strlcpy(device->name, devname, sizeof(device->name));
	device->index = -1;
	(diskdevices->count)++;

	process_diskstat(device);
end:
	UNLOCK_DISKSTATS;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%p", __function_name, device);

	return device;
}

#endif	/* _WINDOWS */
