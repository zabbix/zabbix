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

#include "zbxsysinfo.h"
#include "../sysinfo.h"
#include "../common/stats.h"

int	system_stat(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*section, *type;
#define ZBX_MAX_WAIT_VMSTAT	2	/* maximum seconds to wait for vmstat data on the first call */
	int	wait = ZBX_MAX_WAIT_VMSTAT;
#undef ZBX_MAX_WAIT_VMSTAT
	zbx_collector_data	*collector = get_collector();

	if (!VMSTAT_COLLECTOR_STARTED(collector))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Collector is not started."));
		return SYSINFO_RET_FAIL;
	}

	/* if vmstat data is not available yet wait for collector to gather it */
	if (0 == collector->vmstat.data_available)
	{
		collector->vmstat.enabled = 1;

		while (wait--)
		{
			zbx_sleep(1);
			if (1 == collector->vmstat.data_available)
				break;
		}

		if (0 == collector->vmstat.data_available)
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "No data available in collector."));
			return SYSINFO_RET_FAIL;
		}
	}

	if (2 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	section = get_rparam(request, 0);
	type = get_rparam(request, 1);

	if (NULL == section)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		return SYSINFO_RET_FAIL;
	}

	if (0 == strcmp(section, "ent"))
	{
		if (1 != request->nparam)
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
			return SYSINFO_RET_FAIL;
		}

		SET_DBL_RESULT(result, collector->vmstat.ent);
	}
	else if (NULL == type)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		return SYSINFO_RET_FAIL;
	}
	else if (0 == strcmp(section, "kthr"))
	{
		if (0 == strcmp(type, "r"))
			SET_DBL_RESULT(result, collector->vmstat.kthr_r);
		else if (0 == strcmp(type, "b"))
			SET_DBL_RESULT(result, collector->vmstat.kthr_b);
		else
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
			return SYSINFO_RET_FAIL;
		}
	}
	else if (0 == strcmp(section, "page"))
	{
		if (0 == strcmp(type, "fi"))
			SET_DBL_RESULT(result, collector->vmstat.fi);
		else if (0 == strcmp(type, "fo"))
			SET_DBL_RESULT(result, collector->vmstat.fo);
		else if (0 == strcmp(type, "pi"))
			SET_DBL_RESULT(result, collector->vmstat.pi);
		else if (0 == strcmp(type, "po"))
			SET_DBL_RESULT(result, collector->vmstat.po);
		else if (0 == strcmp(type, "fr"))
			SET_DBL_RESULT(result, collector->vmstat.fr);
		else if (0 == strcmp(type, "sr"))
			SET_DBL_RESULT(result, collector->vmstat.sr);
		else
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
			return SYSINFO_RET_FAIL;
		}
	}
	else if (0 == strcmp(section, "faults"))
	{
		if (0 == strcmp(type, "in"))
			SET_DBL_RESULT(result, collector->vmstat.in);
		else if (0 == strcmp(type, "sy"))
			SET_DBL_RESULT(result, collector->vmstat.sy);
		else if (0 == strcmp(type, "cs"))
			SET_DBL_RESULT(result, collector->vmstat.cs);
		else
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
			return SYSINFO_RET_FAIL;
		}
	}
	else if (0 == strcmp(section, "cpu"))
	{
		if (0 == strcmp(type, "us"))
			SET_DBL_RESULT(result, collector->vmstat.cpu_us);
		else if (0 == strcmp(type, "sy"))
			SET_DBL_RESULT(result, collector->vmstat.cpu_sy);
		else if (0 == strcmp(type, "id"))
			SET_DBL_RESULT(result, collector->vmstat.cpu_id);
		else if (0 == strcmp(type, "wa"))
			SET_DBL_RESULT(result, collector->vmstat.cpu_wa);
		else if (0 == strcmp(type, "pc"))
			SET_DBL_RESULT(result, collector->vmstat.cpu_pc);
		else if (0 == strcmp(type, "ec"))
			SET_DBL_RESULT(result, collector->vmstat.cpu_ec);
		else if (0 == strcmp(type, "lbusy"))
		{
			if (0 == collector->vmstat.shared_enabled)
			{
				SET_MSG_RESULT(result, zbx_strdup(NULL, "logical partition type is not \"shared\"."));
				return SYSINFO_RET_FAIL;
			}

			SET_DBL_RESULT(result, collector->vmstat.cpu_lbusy);
		}
		else if (0 == strcmp(type, "app"))
		{
			if (0 == collector->vmstat.shared_enabled)
			{
				SET_MSG_RESULT(result, zbx_strdup(NULL, "logical partition type is not \"shared\"."));
				return SYSINFO_RET_FAIL;
			}

			if (0 == collector->vmstat.pool_util_authority)
			{
				SET_MSG_RESULT(result, zbx_strdup(NULL, "pool utilization authority not set."));
				return SYSINFO_RET_FAIL;
			}

			SET_DBL_RESULT(result, collector->vmstat.cpu_app);
		}
		else
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
			return SYSINFO_RET_FAIL;
		}
	}
	else if (0 == strcmp(section, "disk"))
	{
		if (0 == strcmp(type, "bps"))
			SET_UI64_RESULT(result, collector->vmstat.disk_bps);
		else if (0 == strcmp(type, "tps"))
			SET_DBL_RESULT(result, collector->vmstat.disk_tps);
		else
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
			return SYSINFO_RET_FAIL;
		}
	}
	else if (0 == strcmp(section, "memory"))
	{
		if (0 == strcmp(type, "avm"))
		{
			if (0 != collector->vmstat.aix52stats)
			{
				SET_UI64_RESULT(result, collector->vmstat.mem_avm);
			}
			else
			{
				SET_MSG_RESULT(result, zbx_strdup(NULL, "Support for system.stat[memory,avm] was not"
						" compiled in."));
				return SYSINFO_RET_FAIL;
			}
		}
		else if (0 == strcmp(type, "fre"))
			SET_UI64_RESULT(result, collector->vmstat.mem_fre);
		else
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
			return SYSINFO_RET_FAIL;
		}
	}
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		return SYSINFO_RET_FAIL;
	}

	return SYSINFO_RET_OK;
}

#ifndef XINTFRAC	/* defined in IBM AIX 7.1 libperfstat.h, not defined in AIX 6.1 */
#include <sys/systemcfg.h>
#define XINTFRAC	((double)_system_configuration.Xint / _system_configuration.Xfrac)
	/* Example of XINTFRAC = 125.000000 / 64.000000 = 1.953125. Apparently XINTFRAC is a period (in nanoseconds) */
	/* of CPU ticks on a machine. For example, 1.953125 could mean there is 1.953125 nanoseconds between ticks */
	/* and number of ticks in second is 1.0 / (1.953125 * 10^-9) = 512000000. So, tick frequency is 512 MHz. */
#endif

static int		last_clock = 0;
/* --- kthr --- */
static zbx_uint64_t	last_runque = 0;		/* length of the run queue (processes ready) */
static zbx_uint64_t	last_swpque = 0;		/* length of swap queue (processes waiting to be paged in) */
/* --- page --- */
static zbx_uint64_t	last_pgins = 0;			/* number of pages paged in */
static zbx_uint64_t	last_pgouts = 0;		/* number of pages paged out */
static zbx_uint64_t	last_pgspins = 0;		/* number of page ins from paging space */
static zbx_uint64_t	last_pgspouts = 0;		/* number of page outs from paging space */
static zbx_uint64_t	last_cycles = 0;		/* number of page replacement cycles */
static zbx_uint64_t	last_scans = 0;			/* number of page scans by clock */
/* -- faults -- */
static zbx_uint64_t	last_devintrs = 0;		/* number of device interrupts */
static zbx_uint64_t	last_syscall = 0;		/* number of system calls executed */
static zbx_uint64_t	last_pswitch = 0;		/* number of process switches (change in currently running */
							/* process) */
/* --- cpu ---- */
/* Raw numbers of ticks are readings from forward-ticking counters. */
/* Only difference between 2 readings is meaningful. */
static zbx_uint64_t	last_puser = 0;			/* raw number of physical processor ticks in user mode */
static zbx_uint64_t	last_psys = 0;			/* raw number of physical processor ticks in system mode */
static zbx_uint64_t	last_pidle = 0;			/* raw number of physical processor ticks idle */
static zbx_uint64_t	last_pwait = 0;			/* raw number of physical processor ticks waiting for I/O */
static zbx_uint64_t	last_user = 0;			/* raw total number of clock ticks spent in user mode */
static zbx_uint64_t	last_sys = 0;			/* raw total number of clock ticks spent in system mode */
static zbx_uint64_t	last_idle = 0;			/* raw total number of clock ticks spent idle */
static zbx_uint64_t	last_wait = 0;			/* raw total number of clock ticks spent waiting for I/O */
static zbx_uint64_t	last_timebase_last = 0;		/* most recent processor time base timestamp */
static zbx_uint64_t	last_pool_idle_time = 0;	/* number of clock ticks a processor in the shared pool was */
							/* idle */
static zbx_uint64_t	last_idle_donated_purr = 0;	/* number of idle cycles donated by a dedicated partition */
							/* enabled for donation */
static zbx_uint64_t	last_busy_donated_purr = 0;	/* number of busy cycles donated by a dedicated partition */
							/* enabled for donation */
static zbx_uint64_t	last_idle_stolen_purr = 0;	/* number of idle cycles stolen by the hypervisor from */
							/* a dedicated partition */
static zbx_uint64_t	last_busy_stolen_purr = 0;	/* number of busy cycles stolen by the hypervisor from */
							/* a dedicated partition */
/* --- disk --- */
static zbx_uint64_t	last_xfers = 0;			/* total number of transfers to/from disk */
static zbx_uint64_t	last_wblks = 0;			/* 512 bytes blocks written to all disks */
static zbx_uint64_t	last_rblks = 0;			/* 512 bytes blocks read from all disks */

/******************************************************************************
 *                                                                            *
 * Purpose: updates vmstat values at most once per second                     *
 *                                                                            *
 * Parameters: vmstat - structure containing vmstat data                      *
 *                                                                            *
 * Comments: On the first iteration only saves last data, on the second -     *
 *           sets vmstat data and indicates that it is available.             *
 *                                                                            *
 ******************************************************************************/
static void	update_vmstat(ZBX_VMSTAT_DATA *vmstat)
{
#if defined(HAVE_LIBPERFSTAT)
	int				now;
	zbx_uint64_t			dlcpu_us, dlcpu_sy, dlcpu_id, dlcpu_wa, lcputime;
	perfstat_memory_total_t		memstats;
	perfstat_cpu_total_t		cpustats;
	perfstat_disk_total_t		diskstats;
#ifdef _AIXVERSION_530
	zbx_uint64_t			dpcpu_us, dpcpu_sy, dpcpu_id, dpcpu_wa, pcputime, dtimebase;
	zbx_uint64_t			delta_purr, entitled_purr, unused_purr;
	perfstat_partition_total_t	lparstats;
#ifdef HAVE_AIXOSLEVEL_530006
	zbx_uint64_t			didle_donated_purr, dbusy_donated_purr, didle_stolen_purr, dbusy_stolen_purr,
					r1, r2;
#endif	/* HAVE_AIXOSLEVEL_530006 */
#endif	/* _AIXVERSION_530 */

	now = (int)time(NULL);

	/* Retrieve metrics from AIX libperfstat APIs.
	 * Upon successful completion, the number of structures filled is returned.
	 * If unsuccessful, a value of -1 is returned and the errno global variable is set. */
#ifdef _AIXVERSION_530
	if (-1 == perfstat_partition_total(NULL, &lparstats, sizeof(lparstats), 1))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "perfstat_partition_total: %s", zbx_strerror(errno));
		return;
	}
#endif

	if (-1 == perfstat_cpu_total(NULL, &cpustats, sizeof(cpustats), 1))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "perfstat_cpu_total: %s", zbx_strerror(errno));
		return;
	}

	if (-1 == perfstat_memory_total(NULL, &memstats, sizeof(memstats), 1))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "perfstat_memory_total: %s", zbx_strerror(errno));
		return;
	}

	if (-1 == perfstat_disk_total(NULL, &diskstats, sizeof(diskstats), 1))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "perfstat_disk_total: %s", zbx_strerror(errno));
		return;
	}

	/* set static vmstat values on first iteration, dynamic on next iterations (at most once per second) */
	if (0 == last_clock)
	{
#ifdef _AIXVERSION_530
		vmstat->shared_enabled = (unsigned char)lparstats.type.b.shared_enabled;
		vmstat->pool_util_authority = (unsigned char)lparstats.type.b.pool_util_authority;
#endif
#ifdef HAVE_AIXOSLEVEL_520004
		vmstat->aix52stats = 1;
#endif
	}
	else if (now > last_clock)
	{
		/* --- kthr --- */
		vmstat->kthr_r = (double)(cpustats.runque - last_runque) / (now - last_clock);
		vmstat->kthr_b = (double)(cpustats.swpque - last_swpque) / (now - last_clock);
		/* --- page --- */
		vmstat->fi = (double)(memstats.pgins - last_pgins) / (now - last_clock);
		vmstat->fo = (double)(memstats.pgouts - last_pgouts) / (now - last_clock);
		vmstat->pi = (double)(memstats.pgspins - last_pgspins) / (now - last_clock);
		vmstat->po = (double)(memstats.pgspouts - last_pgspouts) / (now - last_clock);
		vmstat->fr = (double)(memstats.cycles - last_cycles) / (now - last_clock);
		vmstat->sr = (double)(memstats.scans - last_scans) / (now - last_clock);
		/* -- faults -- */
		vmstat->in = (double)(cpustats.devintrs - last_devintrs) / (now - last_clock);
		vmstat->sy = (double)(cpustats.syscall - last_syscall) / (now - last_clock);
		vmstat->cs = (double)(cpustats.pswitch - last_pswitch) / (now - last_clock);

#ifdef _AIXVERSION_530
		/* number of CPU ticks since the last measurement by mode */
		dpcpu_us = lparstats.puser - last_puser;
		dpcpu_sy = lparstats.psys  - last_psys;
		dpcpu_id = lparstats.pidle - last_pidle;
		dpcpu_wa = lparstats.pwait - last_pwait;

		/* total number of CPU ticks since the last measurement */
		delta_purr = dpcpu_us + dpcpu_sy + dpcpu_id + dpcpu_wa;
		pcputime = delta_purr;
#endif	/* _AIXVERSION_530 */
		dlcpu_us = cpustats.user - last_user;
		dlcpu_sy = cpustats.sys  - last_sys;
		dlcpu_id = cpustats.idle - last_idle;
		dlcpu_wa = cpustats.wait - last_wait;

		lcputime = dlcpu_us + dlcpu_sy + dlcpu_id + dlcpu_wa;
#ifdef _AIXVERSION_530
		/* Distribute the donated and stolen purr to the existing purr buckets in case if donation */
		/* is enabled. */
#ifdef HAVE_AIXOSLEVEL_530006
		if (lparstats.type.b.donate_enabled)
		{
			didle_donated_purr = lparstats.idle_donated_purr - last_idle_donated_purr;
			dbusy_donated_purr = lparstats.busy_donated_purr - last_busy_donated_purr;

			didle_stolen_purr = lparstats.idle_stolen_purr - last_idle_stolen_purr;
			dbusy_stolen_purr = lparstats.busy_stolen_purr - last_busy_stolen_purr;

			if (0 != dlcpu_id + dlcpu_wa)
			{
				r1 = dlcpu_id / (dlcpu_id + dlcpu_wa);
				r2 = dlcpu_wa / (dlcpu_id + dlcpu_wa);
			}
			else
				r1 = r2 = 0;

			dpcpu_us += didle_donated_purr * r1 + didle_stolen_purr * r1;
			dpcpu_wa += didle_donated_purr * r2 + didle_stolen_purr * r2;
			dpcpu_sy += dbusy_donated_purr + dbusy_stolen_purr;

			delta_purr += didle_donated_purr + dbusy_donated_purr + didle_stolen_purr + dbusy_stolen_purr;
			pcputime = delta_purr;
		}
#endif	/* HAVE_AIXOSLEVEL_530006 */

		/* number of physical processor tics between current and previous measurement */
		dtimebase = lparstats.timebase_last - last_timebase_last;

		/* 'perfstat_partition_total_t' element 'entitled_proc_capacity' is "number of processor units this */
		/* partition is entitled to receive". It is expressed as multiplied by 100 and rounded to integer, */
		/* therefore we divide it by 100 and convert to floating point number to get its real value, as */
		/* shown by 'lparstat' command. */
		vmstat->ent = lparstats.entitled_proc_capacity / 100.0;

		if (lparstats.type.b.shared_enabled)
		{
			entitled_purr = dtimebase * vmstat->ent;
			if (entitled_purr < delta_purr)
			{
				/* when above entitlement, use consumption in percentages */
				entitled_purr = delta_purr;
			}
			unused_purr = entitled_purr - delta_purr;

			/* distribute unused purr in wait and idle proportionally to logical wait and idle */
			if (0 != dlcpu_wa + dlcpu_id)
			{
				dpcpu_wa += unused_purr * ((double)dlcpu_wa / (dlcpu_wa + dlcpu_id));
				dpcpu_id += unused_purr * ((double)dlcpu_id / (dlcpu_wa + dlcpu_id));
			}

			pcputime = entitled_purr;
		}

		/* Physical Processor Utilization */
		vmstat->cpu_us = dpcpu_us * 100.0 / pcputime;
		vmstat->cpu_sy = dpcpu_sy * 100.0 / pcputime;
		vmstat->cpu_id = dpcpu_id * 100.0 / pcputime;
		vmstat->cpu_wa = dpcpu_wa * 100.0 / pcputime;

		/* Physical Processor Consumed */
		/* Interesting values only for "shared" LPARs. */
		/* For "dedicated" LPARs expect approximately the same value as assigned to the LPAR through HMC. */
		vmstat->cpu_pc = (double)delta_purr / dtimebase;

		if (lparstats.type.b.shared_enabled)
		{
			/* Percentage of Entitlement Consumed */
			vmstat->cpu_ec = (vmstat->cpu_pc / vmstat->ent) * 100.0;

			/* Logical Processor Utilization */
			vmstat->cpu_lbusy = (dlcpu_us + dlcpu_sy) * 100.0 / lcputime;

			if (lparstats.type.b.pool_util_authority)
			{
				/* Available Pool Processor (app) */
				vmstat->cpu_app = (lparstats.pool_idle_time - last_pool_idle_time) /
						(XINTFRAC * dtimebase);
			}
		}
		else
			vmstat->cpu_ec = 100.0;		/* trivial value for LPAR type "dedicated" */

#else	/* not _AIXVERSION_530 */

		/* Physical Processor Utilization */
		vmstat->cpu_us = dlcpu_us * 100.0 / lcputime;
		vmstat->cpu_sy = dlcpu_sy * 100.0 / lcputime;
		vmstat->cpu_id = dlcpu_id * 100.0 / lcputime;
		vmstat->cpu_wa = dlcpu_wa * 100.0 / lcputime;

#endif	/* _AIXVERSION_530 */
		/* --- disk --- */
		vmstat->disk_bps = 512 * ((diskstats.wblks - last_wblks) + (diskstats.rblks - last_rblks)) /
				(now - last_clock);
		vmstat->disk_tps = (double)(diskstats.xfers - last_xfers) / (now - last_clock);

		/* -- memory -- */
#ifdef HAVE_AIXOSLEVEL_520004
		/* Active virtual pages. Virtual pages are considered active if they have been accessed. */
		vmstat->mem_avm = (zbx_uint64_t)memstats.virt_active;
#endif
		vmstat->mem_fre = (zbx_uint64_t)memstats.real_free;	/* free real memory (in 4KB pages) */

		/* indicate that vmstat data is available */
		vmstat->data_available = 1;
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

#ifdef HAVE_AIXOSLEVEL_530006
	last_idle_donated_purr = (zbx_uint64_t)lparstats.idle_donated_purr;
	last_busy_donated_purr = (zbx_uint64_t)lparstats.busy_donated_purr;

	last_idle_stolen_purr = (zbx_uint64_t)lparstats.idle_stolen_purr;
	last_busy_stolen_purr = (zbx_uint64_t)lparstats.busy_stolen_purr;
#endif	/* HAVE_AIXOSLEVEL_530006 */
#endif	/* _AIXVERSION_530 */
	last_user = (zbx_uint64_t)cpustats.user;
	last_sys = (zbx_uint64_t)cpustats.sys;
	last_idle = (zbx_uint64_t)cpustats.idle;
	last_wait = (zbx_uint64_t)cpustats.wait;

	last_xfers = (zbx_uint64_t)diskstats.xfers;
	last_wblks = (zbx_uint64_t)diskstats.wblks;
	last_rblks = (zbx_uint64_t)diskstats.rblks;
#endif	/* HAVE_LIBPERFSTAT */
}

void	collect_vmstat_data(ZBX_VMSTAT_DATA *vmstat)
{
	update_vmstat(vmstat);
}
