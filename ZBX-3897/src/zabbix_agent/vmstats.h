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

#ifndef ZABBIX_VMSTAT_H
#define ZABBIX_VMSTAT_H

#include "sysinfo.h"

#ifdef _AIX

typedef struct
{
	/* public */
	unsigned char	enabled;		/* collecting enabled */
	unsigned char	data_available;		/* data is collected and available */
	unsigned char	shared_enabled; 	/* partition runs in shared mode */
	unsigned char	pool_util_authority;	/* pool utilization available */
	unsigned char	aix52stats;
	/* - general -- */
	double		ent;
	/* --- kthr --- */
	double		kthr_r, kthr_b/*, kthr_p*/;
	/* --- page --- */
	double		fi, fo, pi, po, fr, sr;
	/* -- faults -- */
	double		in, sy, cs;
	/* --- cpu ---- */
	double		cpu_us, cpu_sy, cpu_id, cpu_wa, cpu_pc, cpu_ec, cpu_lbusy, cpu_app;
	/* --- disk --- */
	zbx_uint64_t	disk_bps;
	double		disk_tps;
	/* -- memory -- */
	zbx_uint64_t	mem_avm, mem_fre;
}
ZBX_VMSTAT_DATA;

#define VMSTAT_COLLECTOR_STARTED(collector)	(collector)

void	collect_vmstat_data(ZBX_VMSTAT_DATA *vmstat);

#endif /* _AIX */

#endif
