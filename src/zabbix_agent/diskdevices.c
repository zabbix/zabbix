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
#include "diskdevices.h"
#include "stats.h"
#include "log.h"

static void	apply_diskstat(ZBX_SINGLE_DISKDEVICE_DATA *device, time_t now,
		zbx_uint64_t r_oper, zbx_uint64_t r_sect, zbx_uint64_t w_oper, zbx_uint64_t w_sect)
{
	register int	i;
	int		clock[ZBX_AVGMAX], index[ZBX_AVGMAX];

	assert(device);

	device->index++;

	if (device->index == MAX_COLLECTOR_HISTORY)
		device->index = 0;

	device->clock[device->index] = now;
	device->r_oper[device->index] = r_oper;
	device->r_sect[device->index] = r_sect;
	device->w_oper[device->index] = w_oper;
	device->w_sect[device->index] = w_sect;

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
		device->r_ops[ZBX_AVG ## t] = 0;\
		device->r_sps[ZBX_AVG ## t] = 0;\
		device->w_ops[ZBX_AVG ## t] = 0;\
		device->w_sps[ZBX_AVG ## t] = 0;\
	}\
	else\
	{\
		device->r_ops[ZBX_AVG ## t] = (r_oper - device->r_oper[index[ZBX_AVG ## t]]) / (now - device->clock[index[ZBX_AVG ## t]]);\
		device->r_sps[ZBX_AVG ## t] = (r_sect - device->r_sect[index[ZBX_AVG ## t]]) / (now - device->clock[index[ZBX_AVG ## t]]);\
		device->w_ops[ZBX_AVG ## t] = (w_oper - device->w_oper[index[ZBX_AVG ## t]]) / (now - device->clock[index[ZBX_AVG ## t]]);\
		device->w_sps[ZBX_AVG ## t] = (w_sect - device->w_sect[index[ZBX_AVG ## t]]) / (now - device->clock[index[ZBX_AVG ## t]]);\
	}

	SAVE_DISKSTAT(1);
	SAVE_DISKSTAT(5);
	SAVE_DISKSTAT(15);
}

static void	process_diskstat(ZBX_SINGLE_DISKDEVICE_DATA *device)
{
	time_t		now = 0;
	zbx_uint64_t	r_oper, r_sect, w_oper, w_sect;

	if (FAIL == get_diskstat(device->name, &now, &r_oper, &r_sect, &w_oper, &w_sect))
		return;

	apply_diskstat(device, now, r_oper, r_sect, w_oper, w_sect);
}

void	collect_stats_diskdevices(ZBX_DISKDEVICES_DATA *diskdevices)
{
	register int	i;

	for (i = 0; i < diskdevices->count; i++)
		process_diskstat(&diskdevices->device[i]);
}

ZBX_SINGLE_DISKDEVICE_DATA	*collector_diskdevice_get(const char *devname)
{
	int	i;

	assert(devname);

	zabbix_log(LOG_LEVEL_DEBUG, "In collector_diskdevice_get(\"%s\")", devname);

	for (i = 0; i < collector->diskdevices.count; i ++)
		if (0 == strcmp(devname, collector->diskdevices.device[i].name))
			return &collector->diskdevices.device[i];

	return NULL;
}

ZBX_SINGLE_DISKDEVICE_DATA	*collector_diskdevice_add(const char *devname)
{
	ZBX_SINGLE_DISKDEVICE_DATA	*device;

	assert(devname);

	zabbix_log(LOG_LEVEL_DEBUG, "In collector_diskdevice_add(\"%s\")", devname);

	/* collector is full */
	if (collector->diskdevices.count == MAX_DISKDEVICES)
		return NULL;

	device = &collector->diskdevices.device[collector->diskdevices.count];
	zbx_strlcpy(device->name, devname, sizeof(device->name));
	device->index = -1;
	collector->diskdevices.count++;

	process_diskstat(device);

	return device;
}
