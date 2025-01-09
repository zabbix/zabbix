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

#ifndef ZABBIX_DISKDEVICES_H
#define ZABBIX_DISKDEVICES_H

#ifdef _WINDOWS
#	error "This module allowed only for Unix OS"
#endif

#include "zbxsysinfo.h"

#define	MAX_DISKDEVICES	1024

/* Disk device time to live: if disk statistics is being collected but not polled (using passive  */
/* or active check) DISKDEVICE_TTL or more seconds then delete this disk from collector.          */
/* Update interval for vfs.dev.read[] and vfs.dev.write[] items must be less than DISKDEVICE_TTL. */
#define	DISKDEVICE_TTL	(3 * SEC_PER_HOUR)

typedef struct c_single_diskdevice_data
{
	char		name[32];
	int		index;
	/* Counter used to detect devices no longer polled and to delete them from collector. It is set */
	/* to 0 when disk statistics is polled and incremented when disk statistics is updated. For     */
	/* example, value 3600 means that approximately 1 hour statistics was not polled for this disk. */
	int 		ticks_since_polled;
	time_t		clock[ZBX_MAX_COLLECTOR_HISTORY];
	zbx_uint64_t	r_sect[ZBX_MAX_COLLECTOR_HISTORY];
	zbx_uint64_t	r_oper[ZBX_MAX_COLLECTOR_HISTORY];
	zbx_uint64_t	r_byte[ZBX_MAX_COLLECTOR_HISTORY];
	zbx_uint64_t	w_sect[ZBX_MAX_COLLECTOR_HISTORY];
	zbx_uint64_t	w_oper[ZBX_MAX_COLLECTOR_HISTORY];
	zbx_uint64_t	w_byte[ZBX_MAX_COLLECTOR_HISTORY];
	double		r_sps[ZBX_AVG_COUNT];
	double		r_ops[ZBX_AVG_COUNT];
	double		r_bps[ZBX_AVG_COUNT];
	double		w_sps[ZBX_AVG_COUNT];
	double		w_ops[ZBX_AVG_COUNT];
	double		w_bps[ZBX_AVG_COUNT];
} zbx_single_diskdevice_data;

typedef struct c_diskdevices_data
{
	int				count;		/* number of disks to collect statistics for */
	int				max_diskdev;	/* number of "slots" for disk statistics */
	zbx_single_diskdevice_data	device[1];	/* more "slots" for disk statistics added dynamically */
} zbx_diskdevices_data;

zbx_single_diskdevice_data	*collector_diskdevice_get(const char *devname);
zbx_single_diskdevice_data	*collector_diskdevice_add(const char *devname);
void				collect_stats_diskdevices(void);

#endif
