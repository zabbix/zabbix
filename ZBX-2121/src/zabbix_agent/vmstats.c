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
#include "vmstats.h"
#include "log.h"

#ifdef _AIX

#define XINTFRAC	((double)_system_configuration.Xint / (double)_system_configuration.Xfrac)

static int		last_clock = 0;
/* --- kthr --- */
static zbx_uint64_t	last_runque = 0;		/* length of the run queue (processes ready) */
static zbx_uint64_t	last_swpque = 0;		/* length of the swap queue (processes waiting to be paged in) */
/* --- page --- */
static zbx_uint64_t	last_pgins = 0;			/* number of pages paged in */
static zbx_uint64_t	last_pgouts = 0;		/* number of pages paged out */
static zbx_uint64_t	last_pgspins = 0;		/* number of page ins from paging space */
static zbx_uint64_t	last_pgspouts = 0;			/* number of page outs from paging space */
static zbx_uint64_t	last_cycles = 0;		/* number of page replacement cycles */
static zbx_uint64_t	last_scans = 0;			/* number of page scans by clock */
/* -- faults -- */
static zbx_uint64_t	last_devintrs = 0;		/* number of device interrupts */
static zbx_uint64_t	last_syscall = 0;		/* number of system calls executed */
static zbx_uint64_t	last_pswitch = 0;		/* number of process switches (change in currently running process) */
/* --- cpu ---- */
static zbx_uint64_t	last_puser = 0;			/* raw number of physical processor tics in user mode */
static zbx_uint64_t	last_psys = 0;			/* raw number of physical processor tics in system mode */
static zbx_uint64_t	last_pidle = 0;			/* raw number of physical processor tics idle */
static zbx_uint64_t	last_pwait = 0;			/* raw number of physical processor tics waiting for I/O */
static zbx_uint64_t	last_user = 0;			/* raw total number of clock ticks spent in user mode */
static zbx_uint64_t	last_sys = 0;			/* raw total number of clock ticks spent in system mode */
static zbx_uint64_t	last_idle = 0;			/* raw total number of clock ticks spent idle */
static zbx_uint64_t	last_wait = 0;			/* raw total number of clock ticks spent waiting for I/O */
static zbx_uint64_t	last_timebase_last = 0;		/* most recently cpu time base */
static zbx_uint64_t	last_pool_idle_time = 0;	/* number of clock tics a processor in the shared pool was idle */
static zbx_uint64_t	last_idle_donated_purr = 0;	/* number of idle cycles donated by a dedicated partition enabled for donation */
static zbx_uint64_t	last_busy_donated_purr = 0;	/* number of busy cycles donated by a dedicated partition enabled for donation */
static zbx_uint64_t	last_idle_stolen_purr = 0;	/* number of idle cycles stolen by the hypervisor from a dedicated partition */
static zbx_uint64_t	last_busy_stolen_purr = 0;	/* number of busy cycles stolen by the hypervisor from a dedicated partition */
/* --- disk --- */
static zbx_uint64_t	last_xfers = 0;			/* total number of transfers to/from disk */
static zbx_uint64_t	last_wblks = 0;			/* 512 bytes blocks written to all disks */
static zbx_uint64_t	last_rblks = 0;			/* 512 bytes blocks read from all disks */

static void	update_vmstat(ZBX_VMSTAT_DATA *vmstat)
{
#if defined(HAVE_LIBPERFSTAT)
	int		now;
	zbx_uint64_t	dpcpu_us, dpcpu_sy, dpcpu_id, dpcpu_wa,
			dlcpu_us, dlcpu_sy, dlcpu_id, dlcpu_wa,
			delta_purr, pcputime, lcputime,
			dtimebase;
	zbx_uint64_t	r1, r2, didle_donated_purr, dbusy_donated_purr, didle_stolen_purr, dbusy_stolen_purr,
			entitled_purr, unused_purr;
	perfstat_memory_total_t		memstats;
#ifdef _AIXVERSION_530
	perfstat_partition_total_t	lparstats;
#endif
	perfstat_cpu_total_t		cpustats;
	perfstat_disk_total_t		diskstats;

	now = (int)time(NULL);

	/* retrieve the metrics
	 * Upon successful completion, the number of structures filled is returned.
	 * If unsuccessful, a value of -1 is returned and the errno global variable is set */
#ifdef _AIXVERSION_530
	if (-1 == perfstat_partition_total(NULL, &lparstats, sizeof(perfstat_partition_total_t), 1))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "perfstat_partition_total: %s", strerror(errno));
		return;
	}
#endif

	if (-1 == perfstat_cpu_total(NULL, &cpustats, sizeof(perfstat_cpu_total_t), 1))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "perfstat_cpu_total: %s", strerror(errno));
		return;
	}

	if (-1 == perfstat_memory_total(NULL, &memstats, sizeof(memstats), 1))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "perfstat_memory_total: %s", strerror(errno));
		return;
	}

	if (-1 == perfstat_disk_total(NULL, &diskstats, sizeof(diskstats), 1))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "perfstat_disk_total: %s", strerror(errno));
		return;
	}

	if (last_clock && now > last_clock)
	{
		/* --- kthr --- */
		vmstat->kthr_r = (double)(cpustats.runque - last_runque) / (double)(now - last_clock);
		vmstat->kthr_b = (double)(cpustats.swpque - last_swpque) / (double)(now - last_clock);
		/* --- page --- */
		vmstat->fi = (double)(memstats.pgins - last_pgins) / (double)(now - last_clock);
		vmstat->fo = (double)(memstats.pgouts - last_pgouts) / (double)(now - last_clock);
		vmstat->pi = (double)(memstats.pgspins - last_pgspins) / (double)(now - last_clock);
		vmstat->po = (double)(memstats.pgspouts - last_pgspouts) / (double)(now - last_clock);
		vmstat->fr = (double)(memstats.cycles - last_cycles) / (double)(now - last_clock);
		vmstat->sr = (double)(memstats.scans - last_scans) / (double)(now - last_clock);

		/* -- faults -- */
		vmstat->in = (double)(cpustats.devintrs - last_devintrs) / (double)(now - last_clock);
		vmstat->sy = (double)(cpustats.syscall - last_syscall) / (double)(now - last_clock);
		vmstat->cs = (double)(cpustats.pswitch - last_pswitch) / (double)(now - last_clock);

#ifdef _AIXVERSION_530
		/* --- cpu ---- */
		dpcpu_us = lparstats.puser - last_puser;
		dpcpu_sy = lparstats.psys  - last_psys;
		dpcpu_id = lparstats.pidle - last_pidle;
		dpcpu_wa = lparstats.pwait - last_pwait;

		delta_purr = pcputime = dpcpu_us + dpcpu_sy + dpcpu_id + dpcpu_wa;
#endif	/* _AIXVERSION_530 */
		dlcpu_us = cpustats.user - last_user;
		dlcpu_sy = cpustats.sys  - last_sys;
		dlcpu_id = cpustats.idle - last_idle;
		dlcpu_wa = cpustats.wait - last_wait;

		lcputime = dlcpu_us + dlcpu_sy + dlcpu_id + dlcpu_wa;
#ifdef _AIXVERSION_530
		/* Distribute the donated and stolen purr to the existing purr buckets in case if donation is 
		enabled.*/
		if (lparstats.type.b.donate_enabled)
		{
			didle_donated_purr = lparstats.idle_donated_purr - last_idle_donated_purr;
			dbusy_donated_purr = lparstats.busy_donated_purr - last_busy_donated_purr;

			didle_stolen_purr = lparstats.idle_stolen_purr - last_idle_stolen_purr;
			dbusy_stolen_purr = lparstats.busy_stolen_purr - last_busy_stolen_purr;

			if (0 != (dlcpu_id + dlcpu_wa))
			{
				r1 = dlcpu_id / (dlcpu_id + dlcpu_wa);
				r2 = dlcpu_wa / (dlcpu_id + dlcpu_wa);
			}
			else
				r1 = r2 = 0;

			dpcpu_us += didle_donated_purr *r1 + didle_stolen_purr * r1;
			dpcpu_wa += didle_donated_purr *r2 + didle_stolen_purr * r2;
			dpcpu_sy += dbusy_donated_purr + dbusy_stolen_purr;

			delta_purr += didle_donated_purr + dbusy_donated_purr + didle_stolen_purr
					+ dbusy_stolen_purr;
			pcputime = delta_purr; 
		}

		dtimebase = lparstats.timebase_last - last_timebase_last;
		vmstat->ent = (double)lparstats.entitled_proc_capacity / 100.0;

		if (lparstats.type.b.shared_enabled)
		{
			entitled_purr = dtimebase * vmstat->ent;
			if (entitled_purr < delta_purr)
			{
				/* when above entitlement, use consumption in percentages */
				entitled_purr = delta_purr;
			}
			unused_purr = entitled_purr - delta_purr;

			/* distributed unused purr in wait and idle proportionally to logical wait and idle */
			dpcpu_wa += unused_purr * ((double)dlcpu_wa / (double)(dlcpu_wa + dlcpu_id));
			dpcpu_id += unused_purr * ((double)dlcpu_id / (double)(dlcpu_wa + dlcpu_id));

			pcputime = entitled_purr;
		}

		/* Physical Processor Utilization */
		vmstat->cpu_us = (double)dpcpu_us * 100.0 / (double)pcputime;
		vmstat->cpu_sy = (double)dpcpu_sy * 100.0 / (double)pcputime;
		vmstat->cpu_id = (double)dpcpu_id * 100.0 / (double)pcputime;
		vmstat->cpu_wa = (double)dpcpu_wa * 100.0 / (double)pcputime;

		if (lparstats.type.b.shared_enabled)
		{   
			/* Physical Processor Consumed */  
			vmstat->cpu_pc = (double)delta_purr / (double)dtimebase;

			/* Percentage of Entitlement Consumed */
			vmstat->cpu_ec = (double)(vmstat->cpu_pc / vmstat->ent) * 100.0;

			/* Logical Processor Utilization */
			vmstat->cpu_lbusy = (double)(dlcpu_us + dlcpu_sy) * 100.0 / (double)lcputime;

			if (lparstats.type.b.pool_util_authority)
			{ 
				/* Available Pool Processor (app) */ 
				vmstat->cpu_app = (double)(lparstats.pool_idle_time - last_pool_idle_time) / 
						(XINTFRAC * (double)dtimebase);
			}
		}
#else	/* not _AIXVERSION_530 */
		dlcpu_us = cpustats.user - last_user;
		dlcpu_sy = cpustats.sys  - last_sys;
		dlcpu_id = cpustats.idle - last_idle;
		dlcpu_wa = cpustats.wait - last_wait;

		lcputime = dlcpu_us + dlcpu_sy + dlcpu_id + dlcpu_wa;

		/* Physical Processor Utilization */
		vmstat->cpu_us = (double)dlcpu_us * 100.0 / (double)lcputime;
		vmstat->cpu_sy = (double)dlcpu_sy * 100.0 / (double)lcputime;
		vmstat->cpu_id = (double)dlcpu_id * 100.0 / (double)lcputime;
		vmstat->cpu_wa = (double)dlcpu_wa * 100.0 / (double)lcputime;

#endif	/* _AIXVERSION_530 */
		/* --- disk --- */
		vmstat->disk_bps = 512 * ((diskstats.wblks - last_wblks) + (diskstats.rblks - last_rblks)) /
				(now - last_clock);
		vmstat->disk_tps = (double)(diskstats.xfers - last_xfers) / (double)(now - last_clock);

		/* -- memory -- */
#ifdef _AIXVERSION_520
		vmstat->mem_avm = (zbx_uint64_t)memstats.virt_active;	/* Active virtual pages. Virtual pages are considered
									   active if they have been accessed */
#endif	/* _AIXVERSION_520 */
		vmstat->mem_fre = (zbx_uint64_t)memstats.real_free;	/* free real memory (in 4KB pages) */
	}
	else
	{
#ifdef _AIXVERSION_530
		vmstat->shared_enabled = (unsigned char)lparstats.type.b.shared_enabled;
		vmstat->pool_util_authority = (unsigned char)lparstats.type.b.pool_util_authority;
#endif	/* _AIXVERSION_530 */
#ifdef _AIXVERSION_520
		vmstat->aix52stats = 1;
#endif	/* _AIXVERSION_520 */
	}

	/* saving last values */
	last_clock = now;
	/* --- kthr -- */
	last_runque = (zbx_uint64_t)cpustats.runque;
	last_swpque = (zbx_uint64_t)cpustats.swpque;
	/* --- page --- */
	last_pgins = (zbx_uint64_t)memstats.pgins;
	last_pgouts = (zbx_uint64_t)memstats.pgouts;
	last_pgspins = (zbx_uint64_t)memstats.pgspins;
	last_pgspouts = (zbx_uint64_t)memstats.pgspouts;
	last_cycles = (zbx_uint64_t)memstats.cycles;
	last_scans = (zbx_uint64_t)memstats.scans;
	/* -- faults -- */
	last_devintrs = (zbx_uint64_t)cpustats.devintrs;
	last_syscall = (zbx_uint64_t)cpustats.syscall;
	last_pswitch = (zbx_uint64_t)cpustats.pswitch;
	/* --- cpu ---- */
#ifdef _AIXVERSION_530
	last_puser = (zbx_uint64_t)lparstats.puser;
	last_psys = (zbx_uint64_t)lparstats.psys;
	last_pidle = (zbx_uint64_t)lparstats.pidle;
	last_pwait = (zbx_uint64_t)lparstats.pwait;

	last_timebase_last = (zbx_uint64_t)lparstats.timebase_last;

	last_pool_idle_time = (zbx_uint64_t)lparstats.pool_idle_time;

	last_idle_donated_purr = (zbx_uint64_t)lparstats.idle_donated_purr;
	last_busy_donated_purr = (zbx_uint64_t)lparstats.busy_donated_purr;

	last_idle_stolen_purr = (zbx_uint64_t)lparstats.idle_stolen_purr;
	last_busy_stolen_purr = (zbx_uint64_t)lparstats.busy_stolen_purr;
#endif	/* not _AIXVERSION_530 */
	last_user = (zbx_uint64_t)cpustats.user;
	last_sys = (zbx_uint64_t)cpustats.sys;
	last_idle = (zbx_uint64_t)cpustats.idle;
	last_wait = (zbx_uint64_t)cpustats.wait;

	last_xfers = (zbx_uint64_t)diskstats.xfers;
	last_wblks = (zbx_uint64_t)diskstats.wblks;
	last_rblks = (zbx_uint64_t)diskstats.rblks;
#endif	/* _AIXVERSION_530 */
}

void	collect_vmstat_data(ZBX_VMSTAT_DATA *vmstat)
{
	update_vmstat(vmstat);
}
#endif	/* _AIX */
