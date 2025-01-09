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

#ifndef _WINDOWS

#include "diskdevices.h"

#include "stats.h"

static void	apply_diskstat(zbx_single_diskdevice_data *device, time_t now, zbx_uint64_t *dstat)
{
	register int	i;
	time_t		clock[ZBX_AVG_COUNT], sec;
	int		index[ZBX_AVG_COUNT];

	assert(device);

	device->index++;

	if (ZBX_MAX_COLLECTOR_HISTORY == device->index)
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

	for (i = 0; i < ZBX_MAX_COLLECTOR_HISTORY; i++)
	{
		if (0 == device->clock[i])
			continue;

#define DISKSTAT(t)											\
	do												\
	{												\
		if ((device->clock[i] >= (now - (t * 60))) && (clock[ZBX_AVG ## t] > device->clock[i]))	\
		{											\
			clock[ZBX_AVG ## t] = device->clock[i];						\
			index[ZBX_AVG ## t] = i;							\
		}											\
	}												\
	while(0)

		DISKSTAT(1);
		DISKSTAT(5);
		DISKSTAT(15);
	}

#define SAVE_DISKSTAT(t)\
	do											\
	{											\
		if (-1 == index[ZBX_AVG ## t] || 0 == now - device->clock[index[ZBX_AVG ## t]])	\
		{										\
			device->r_sps[ZBX_AVG ## t] = 0;					\
			device->r_ops[ZBX_AVG ## t] = 0;					\
			device->r_bps[ZBX_AVG ## t] = 0;					\
			device->w_sps[ZBX_AVG ## t] = 0;					\
			device->w_ops[ZBX_AVG ## t] = 0;					\
			device->w_bps[ZBX_AVG ## t] = 0;					\
		}										\
		else										\
		{										\
			sec = now - device->clock[index[ZBX_AVG ## t]];				\
			device->r_sps[ZBX_AVG ## t] = (dstat[ZBX_DSTAT_R_SECT] - 		\
					device->r_sect[index[ZBX_AVG ## t]]) / (double)sec;	\
			device->r_ops[ZBX_AVG ## t] = (dstat[ZBX_DSTAT_R_OPER] - 		\
					device->r_oper[index[ZBX_AVG ## t]]) / (double)sec;	\
			device->r_bps[ZBX_AVG ## t] = (dstat[ZBX_DSTAT_R_BYTE] - 		\
					device->r_byte[index[ZBX_AVG ## t]]) / (double)sec;	\
			device->w_sps[ZBX_AVG ## t] = (dstat[ZBX_DSTAT_W_SECT] - 		\
					device->w_sect[index[ZBX_AVG ## t]]) / (double)sec;	\
			device->w_ops[ZBX_AVG ## t] = (dstat[ZBX_DSTAT_W_OPER] - 		\
					device->w_oper[index[ZBX_AVG ## t]]) / (double)sec;	\
			device->w_bps[ZBX_AVG ## t] = (dstat[ZBX_DSTAT_W_BYTE] - 		\
					device->w_byte[index[ZBX_AVG ## t]]) / (double)sec;	\
		}										\
	}											\
	while(0)

	SAVE_DISKSTAT(1);
	SAVE_DISKSTAT(5);
	SAVE_DISKSTAT(15);
}

static void	process_diskstat(zbx_single_diskdevice_data *device)
{
	time_t		now = time(NULL);
	zbx_uint64_t	dstat[ZBX_DSTAT_MAX];

	if (FAIL == zbx_get_diskstat(device->name, dstat))
		return;

	apply_diskstat(device, now, dstat);

	device->ticks_since_polled++;
}

void	collect_stats_diskdevices(void)
{
	stats_lock_diskstats();
	diskstat_shm_reattach();

	zbx_diskdevices_data	*diskdevices = get_diskdevices();

	for (int i = 0; i < diskdevices->count; i++)
	{
		process_diskstat(&diskdevices->device[i]);

		/* remove device from collector if not being polled for long time */
		if (DISKDEVICE_TTL <= diskdevices->device[i].ticks_since_polled)
		{
			if ((diskdevices->count - 1) > i)
			{
				memcpy(diskdevices->device + i, diskdevices->device + i + 1,
					sizeof(zbx_single_diskdevice_data) * (diskdevices->count - i));
			}

			diskdevices->count--;
			i--;
		}
	}

	stats_unlock_diskstats();
}

zbx_single_diskdevice_data	*collector_diskdevice_get(const char *devname)
{
	zbx_single_diskdevice_data	*device = NULL;

	assert(devname);

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() devname:'%s'", __func__, devname);

	stats_lock_diskstats();

	if (0 == diskdevice_collector_started())
		diskstat_shm_init();
	else
		diskstat_shm_reattach();

	zbx_diskdevices_data	*diskdevices = get_diskdevices();

	for (int i = 0; i < diskdevices->count; i++)
	{
		if (0 == strcmp(devname, diskdevices->device[i].name))
		{
			device = &diskdevices->device[i];
			device->ticks_since_polled = 0;
			zabbix_log(LOG_LEVEL_DEBUG, "%s() device '%s' found", __func__, devname);
			break;
		}
	}
	stats_unlock_diskstats();

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%p", __func__, (void *)device);

	return device;
}

zbx_single_diskdevice_data	*collector_diskdevice_add(const char *devname)
{
	zbx_single_diskdevice_data	*device = NULL;

	assert(devname);

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() devname:'%s'", __func__, devname);

	stats_lock_diskstats();

	if (0 == diskdevice_collector_started())
		diskstat_shm_init();
	else
		diskstat_shm_reattach();

	zbx_diskdevices_data	*diskdevices = get_diskdevices();

	if (diskdevices->count == MAX_DISKDEVICES)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() collector is full", __func__);
		goto end;
	}

	if (diskdevices->count == diskdevices->max_diskdev)
	{
		diskstat_shm_extend();
		diskdevices = get_diskdevices();
	}

	device = &(diskdevices->device[diskdevices->count]);
	memset(device, 0, sizeof(zbx_single_diskdevice_data));
	zbx_strlcpy(device->name, devname, sizeof(device->name));
	device->index = -1;
	device->ticks_since_polled = 0;
	(diskdevices->count)++;

	process_diskstat(device);
end:
	stats_unlock_diskstats();

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%p", __func__, (void *)device);

	return device;
}

#endif	/* _WINDOWS */
