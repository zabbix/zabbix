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

#include "zbxregexp.h"
#include "zbxjson.h"
#include "zbxstr.h"

#if HAVE_LIBJAIL
#	include <jail.h>
#endif

#if (__FreeBSD_version) < 500000
#	define ZBX_PROC_PID		kp_proc.p_pid
#	define ZBX_PROC_PPID		kp_eproc.e_ppid
#	define ZBX_PROC_COMM		kp_proc.p_comm
#	define ZBX_PROC_STAT		kp_proc.p_stat
#	define ZBX_PROC_TSIZE		kp_eproc.e_vm.vm_tsize
#	define ZBX_PROC_DSIZE		kp_eproc.e_vm.vm_dsize
#	define ZBX_PROC_SSIZE		kp_eproc.e_vm.vm_ssize
#	define ZBX_PROC_RSSIZE		kp_eproc.e_vm.vm_rssize
#	define ZBX_PROC_VSIZE		kp_eproc.e_vm.vm_map.size
#	define ZBX_PROC_NUMTHREADS	kp_proc.p_nthreads
#	define ZBX_PROC_MAJFLT		kp_eproc.e_pstats.p_ru.ru_majflt
#	define ZBX_PROC_SWAP		kp_eproc.e_pstats.p_ru.ru_nswap
#	define ZBX_PROC_INBLOCK		kp_eproc.e_pstats.p_ru.ru_inblock
#	define ZBX_PROC_OUBLOCK		kp_eproc.e_pstats.p_ru.ru_oublock
#	define ZBX_PROC_NVCSW		kp_eproc.e_pstats.p_ru.ru_nvcsw
#	define ZBX_PROC_NIVCSW		kp_eproc.e_pstats.p_ru.ru_nivcsw
#	define ZBX_PROC_UTIME		kp_eproc.e_pstats.p_ru.ru_utime.tv_sec
#	define ZBX_PROC_STIME		kp_eproc.e_pstats.p_ru.ru_stime.tv_sec
#	define ZBX_PROC_UID		kp_proc.p_ruid
#	define ZBX_PROC_GID		kp_proc.p_rgid
#else
#	define ZBX_PROC_PID		ki_pid
#	define ZBX_PROC_PPID		ki_ppid
#	define ZBX_PROC_JID		ki_jid
#	define ZBX_PROC_TID		ki_tid
#	define ZBX_PROC_TNAME		ki_ocomm
#	define ZBX_PROC_COMM		ki_comm
#	define ZBX_PROC_STAT		ki_stat
#	define ZBX_PROC_TSIZE		ki_tsize
#	define ZBX_PROC_DSIZE		ki_dsize
#	define ZBX_PROC_SSIZE		ki_ssize
#	define ZBX_PROC_RSSIZE		ki_rssize
#	define ZBX_PROC_VSIZE		ki_size
#	define ZBX_PROC_NUMTHREADS	ki_numthreads
#	define ZBX_PROC_MAJFLT		ki_rusage.ru_majflt
#	define ZBX_PROC_SWAP		ki_rusage.ru_nswap
#	define ZBX_PROC_INBLOCK		ki_rusage.ru_inblock
#	define ZBX_PROC_OUBLOCK		ki_rusage.ru_oublock
#	define ZBX_PROC_NVCSW		ki_rusage.ru_nvcsw
#	define ZBX_PROC_NIVCSW		ki_rusage.ru_nivcsw
#	define ZBX_PROC_UTIME		ki_rusage.ru_utime.tv_sec
#	define ZBX_PROC_STIME		ki_rusage.ru_stime.tv_sec
#	define ZBX_PROC_UID		ki_ruid
#	define ZBX_PROC_GID		ki_rgid
#endif

#if (__FreeBSD_version) < 500000
#	define ZBX_PROC_FLAG	kp_proc.p_flag
#	define ZBX_PROC_MASK	P_INMEM
#elif (__FreeBSD_version) < 700000
#	define ZBX_PROC_FLAG	ki_sflag
#	define ZBX_PROC_MASK	PS_INMEM
#else
#	define ZBX_PROC_TDFLAG	ki_tdflags
#	define ZBX_PROC_FLAG	ki_flag
#	define ZBX_PROC_MASK	P_INMEM
#endif

typedef struct
{
	int		pid;
	int		ppid;
	int		tid;
	int		jid;

	char		*name;
	char		*jname;
	char		*tname;
	char		*cmdline;
	char		*state;
	zbx_uint64_t	processes;

	char		*user;
	char		*group;
	zbx_uint64_t	uid;
	zbx_uint64_t	gid;

	zbx_uint64_t	cputime_user;
	zbx_uint64_t	cputime_system;
	zbx_uint64_t	ctx_switches;
	zbx_uint64_t	threads;
	zbx_uint64_t	page_faults;
	zbx_uint64_t	io_read_op;
	zbx_uint64_t	io_write_op;

	zbx_uint64_t	vsize;
	double		pmem;
	zbx_uint64_t	rss;
	zbx_uint64_t	size;
	zbx_uint64_t	tsize;
	zbx_uint64_t	dsize;
	zbx_uint64_t	ssize;
	zbx_uint64_t	swap;
}
proc_data_t;

ZBX_PTR_VECTOR_DECL(proc_data_ptr, proc_data_t *)
ZBX_PTR_VECTOR_IMPL(proc_data_ptr, proc_data_t *)

/******************************************************************************
 *                                                                            *
 * Purpose: frees process data structure                                      *
 *                                                                            *
 ******************************************************************************/
static void	proc_data_free(proc_data_t *proc_data)
{
	zbx_free(proc_data->name);
	zbx_free(proc_data->jname);
	zbx_free(proc_data->tname);
	zbx_free(proc_data->cmdline);
	zbx_free(proc_data->state);
	zbx_free(proc_data->user);
	zbx_free(proc_data->group);

	zbx_free(proc_data);
}

#define ARGV_START_SIZE	64
static char	*get_commandline(struct kinfo_proc *proc)
{
	int		mib[4];
	size_t		sz;
	static char	*args = NULL;
#if (__FreeBSD_version >= 802510)
	static int	args_alloc = 0;
#else
	int		argv_max, err = -1;
	static int	args_alloc = ARGV_START_SIZE;
#endif

	mib[0] = CTL_KERN;
	mib[1] = KERN_PROC;
	mib[2] = KERN_PROC_ARGS;
	mib[3] = proc->ZBX_PROC_PID;

#if (__FreeBSD_version >= 802510)
	if (-1 == sysctl(mib, 4, NULL, &sz, NULL, 0))
		return NULL;

	if (NULL == args)
	{
		args = zbx_malloc(args, sz);
		args_alloc = sz;
	}
	else if (sz > args_alloc)
	{
		args = zbx_realloc(args, sz);
		args_alloc = sz;
	}

	if (-1 == sysctl(mib, 4, args, &sz, NULL, 0))
		return NULL;
#else
	/*
	 * Before FreeBSD 8.3 sysctl() API for kern.proc.args didn't follow the regular convention
	 * that a user can query the needed size for results by passing in a NULL old pointer
	 * and a valid oldsize, given that we have to estimate the required output buffer size manually:
	 *
	 * https://github.com/freebsd/freebsd-src/commit/9f688f2ce3c01f30b0c98d17c6ce057660819c8c
	*/

	if (NULL == args)
		args = zbx_malloc(args, args_alloc);

	if (-1 == (argv_max = sysconf(_SC_ARG_MAX)))
		return NULL;

	while (0 != err && args_alloc < argv_max)
	{
		sz = (size_t)args_alloc;

		if (-1 == (err = sysctl(mib, 4, args, &sz, NULL, 0)))
		{
			if (ENOMEM == errno)
			{
				args_alloc *= 2;
				args = zbx_realloc(args, args_alloc);
			}
			else
				return NULL;
		}
	}

	if (-1 == err)
		return NULL;
#endif
	for (int i = 0; i < (int)(sz - 1); i++)
	{
		if (args[i] == '\0')
			args[i] = ' ';
	}

	if (0 == sz)
		zbx_strlcpy(args, proc->ZBX_PROC_COMM, args_alloc);

	return args;
}
#undef ARGV_START_SIZE

int	proc_mem(AGENT_REQUEST *request, AGENT_RESULT *result)
{
#define ZBX_SIZE	1
#define ZBX_RSS		2
#define ZBX_VSIZE	3
#define ZBX_PMEM	4
#define ZBX_TSIZE	5
#define ZBX_DSIZE	6
#define ZBX_SSIZE	7
	char		*procname, *proccomm, *param, *args, *mem_type = NULL, *rxp_error = NULL;
	int		do_task, pagesize, count, proccount = 0, invalid_user = 0, mem_type_code, mib[4],
			ret = SYSINFO_RET_OK;
	unsigned int	mibs;
	zbx_uint64_t	mem_size = 0, byte_value = 0;
	double		pct_size = 0.0, pct_value = 0.0;
#if (__FreeBSD_version) < 500000
	int		mem_pages;
#else
	unsigned long	mem_pages;
#endif
	size_t	sz;
	zbx_regexp_t	*proccomm_rxp = NULL;

	struct kinfo_proc	*proc = NULL;
	struct passwd		*usrinfo;

	if (5 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		ret = SYSINFO_RET_FAIL;
		goto clean;
	}

	procname = get_rparam(request, 0);
	param = get_rparam(request, 1);

	if (NULL != param && '\0' != *param)
	{
		errno = 0;

		if (NULL == (usrinfo = getpwnam(param)))
		{
			if (0 != errno)
			{
				SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain user information: %s",
						zbx_strerror(errno)));
				ret = SYSINFO_RET_FAIL;
				goto clean;
			}

			invalid_user = 1;
		}
	}
	else
		usrinfo = NULL;

	param = get_rparam(request, 2);

	if (NULL == param || '\0' == *param || 0 == strcmp(param, "sum"))
		do_task = ZBX_DO_SUM;
	else if (0 == strcmp(param, "avg"))
		do_task = ZBX_DO_AVG;
	else if (0 == strcmp(param, "max"))
		do_task = ZBX_DO_MAX;
	else if (0 == strcmp(param, "min"))
		do_task = ZBX_DO_MIN;
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid third parameter."));
		ret = SYSINFO_RET_FAIL;
		goto clean;
	}

	proccomm = get_rparam(request, 3);

	if (NULL != proccomm && '\0' != *proccomm)
	{
		if (SUCCEED != zbx_regexp_compile(proccomm, &proccomm_rxp, &rxp_error))
		{
			SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Invalid regular expression in fourth parameter: "
					"%s", rxp_error));

			zbx_free(rxp_error);
			ret = SYSINFO_RET_FAIL;
			goto clean;
		}
	}

	mem_type = get_rparam(request, 4);

	if (NULL == mem_type || '\0' == *mem_type || 0 == strcmp(mem_type, "size"))
	{
		mem_type_code = ZBX_SIZE;		/* size of process (code + data + stack) */
	}
	else if (0 == strcmp(mem_type, "rss"))
	{
		mem_type_code = ZBX_RSS;		/* resident set size */
	}
	else if (0 == strcmp(mem_type, "vsize"))
	{
		mem_type_code = ZBX_VSIZE;		/* virtual size */
	}
	else if (0 == strcmp(mem_type, "pmem"))
	{
		mem_type_code = ZBX_PMEM;		/* percentage of real memory used by process */
	}
	else if (0 == strcmp(mem_type, "tsize"))
	{
		mem_type_code = ZBX_TSIZE;		/* text size */
	}
	else if (0 == strcmp(mem_type, "dsize"))
	{
		mem_type_code = ZBX_DSIZE;		/* data size */
	}
	else if (0 == strcmp(mem_type, "ssize"))
	{
		mem_type_code = ZBX_SSIZE;		/* stack size */
	}
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid fifth parameter."));
		ret = SYSINFO_RET_FAIL;
		goto clean;
	}

	if (1 == invalid_user)	/* handle 0 for non-existent user after all parameters have been parsed and validated */
		goto out;

	pagesize = getpagesize();

	mib[0] = CTL_KERN;
	mib[1] = KERN_PROC;
	if (NULL != usrinfo)
	{
		mib[2] = KERN_PROC_UID;
		mib[3] = usrinfo->pw_uid;
		mibs = 4;
	}
	else
	{
#if (__FreeBSD_version) < 500000
		mib[2] = KERN_PROC_ALL;
#else
		mib[2] = KERN_PROC_PROC;
#endif
		mib[3] = 0;
		mibs = 3;
	}

	if (ZBX_PMEM == mem_type_code)
	{
		sz = sizeof(mem_pages);

		if (0 != sysctlbyname("hw.availpages", &mem_pages, &sz, NULL, (size_t)0))
		{
			SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain number of physical pages: %s",
					zbx_strerror(errno)));
			ret = SYSINFO_RET_FAIL;
			goto clean;
		}
	}

	sz = 0;
	if (0 != sysctl(mib, mibs, NULL, &sz, NULL, (size_t)0))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain necessary buffer size from system: %s",
				zbx_strerror(errno)));
		ret = SYSINFO_RET_FAIL;
		goto clean;
	}

	proc = (struct kinfo_proc *)zbx_malloc(proc, sz);
	if (0 != sysctl(mib, mibs, proc, &sz, NULL, (size_t)0))
	{
		zbx_free(proc);
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain process information: %s",
				zbx_strerror(errno)));
		ret = SYSINFO_RET_FAIL;
		goto clean;
	}

	count = sz / sizeof(struct kinfo_proc);

	for (int i = 0; i < count; i++)
	{
		if (NULL != procname && '\0' != *procname && 0 != strcmp(procname, proc[i].ZBX_PROC_COMM))
			continue;

		if (NULL != proccomm && '\0' != *proccomm)
		{
			if (NULL == (args = get_commandline(&proc[i])))
				continue;

			if (0 != zbx_regexp_match_precompiled(args, proccomm_rxp))
				continue;
		}

		switch (mem_type_code)
		{
			case ZBX_SIZE:
				byte_value = (proc[i].ZBX_PROC_TSIZE + proc[i].ZBX_PROC_DSIZE + proc[i].ZBX_PROC_SSIZE)
						* pagesize;
				break;
			case ZBX_RSS:
				byte_value = proc[i].ZBX_PROC_RSSIZE * pagesize;
				break;
			case ZBX_VSIZE:
				byte_value = proc[i].ZBX_PROC_VSIZE;
				break;
			case ZBX_PMEM:
				if (0 != (proc[i].ZBX_PROC_FLAG & ZBX_PROC_MASK))
#if (__FreeBSD_version) < 500000
					pct_value = ((float)(proc[i].ZBX_PROC_RSSIZE + UPAGES) / mem_pages) * 100.0;
#else
					pct_value = ((float)proc[i].ZBX_PROC_RSSIZE / mem_pages) * 100.0;
#endif
				else
					pct_value = 0.0;
				break;
			case ZBX_TSIZE:
				byte_value = proc[i].ZBX_PROC_TSIZE * pagesize;
				break;
			case ZBX_DSIZE:
				byte_value = proc[i].ZBX_PROC_DSIZE * pagesize;
				break;
			case ZBX_SSIZE:
				byte_value = proc[i].ZBX_PROC_SSIZE * pagesize;
				break;
		}

		if (ZBX_PMEM != mem_type_code)
		{
			if (0 != proccount++)
			{
				if (ZBX_DO_MAX == do_task)
					mem_size = MAX(mem_size, byte_value);
				else if (ZBX_DO_MIN == do_task)
					mem_size = MIN(mem_size, byte_value);
				else
					mem_size += byte_value;
			}
			else
				mem_size = byte_value;
		}
		else
		{
			if (0 != proccount++)
			{
				if (ZBX_DO_MAX == do_task)
					pct_size = MAX(pct_size, pct_value);
				else if (ZBX_DO_MIN == do_task)
					pct_size = MIN(pct_size, pct_value);
				else
					pct_size += pct_value;
			}
			else
				pct_size = pct_value;
		}
	}

	zbx_free(proc);
out:
	if (ZBX_PMEM != mem_type_code)
	{
		if (ZBX_DO_AVG == do_task)
			SET_DBL_RESULT(result, 0 == proccount ? 0.0 : (double)mem_size / (double)proccount);
		else
			SET_UI64_RESULT(result, mem_size);
	}
	else
	{
		if (ZBX_DO_AVG == do_task)
			SET_DBL_RESULT(result, 0 == proccount ? 0.0 : pct_size / (double)proccount);
		else
			SET_DBL_RESULT(result, pct_size);
	}
clean:
	if (NULL != proccomm_rxp)
		zbx_regexp_free(proccomm_rxp);

	return ret;
#undef ZBX_SIZE
#undef ZBX_RSS
#undef ZBX_VSIZE
#undef ZBX_PMEM
#undef ZBX_TSIZE
#undef ZBX_DSIZE
#undef ZBX_SSIZE
}

int	proc_num(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char			*procname, *proccomm, *param, *args, *rxp_error = NULL;
	int			zbx_proc_stat, count, proc_ok, stat_ok, comm_ok, mib[4], mibs, proccount = 0,
				invalid_user = 0, ret = SYSINFO_RET_OK;
	size_t			sz;
	struct kinfo_proc	*proc = NULL;
	struct passwd		*usrinfo;
	zbx_regexp_t		*proccomm_rxp = NULL;

	if (4 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		ret = SYSINFO_RET_FAIL;
		goto clean;
	}

	procname = get_rparam(request, 0);
	param = get_rparam(request, 1);

	if (NULL != param && '\0' != *param)
	{
		errno = 0;

		if (NULL == (usrinfo = getpwnam(param)))
		{
			if (0 != errno)
			{
				SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain user information: %s",
						zbx_strerror(errno)));
				ret = SYSINFO_RET_FAIL;
				goto clean;
			}

			invalid_user = 1;
		}
	}
	else
		usrinfo = NULL;

	param = get_rparam(request, 2);

	if (NULL == param || '\0' == *param || 0 == strcmp(param, "all"))
		zbx_proc_stat = ZBX_PROC_STAT_ALL;
	else if (0 == strcmp(param, "run"))
		zbx_proc_stat = ZBX_PROC_STAT_RUN;
	else if (0 == strcmp(param, "sleep"))
		zbx_proc_stat = ZBX_PROC_STAT_SLEEP;
	else if (0 == strcmp(param, "zomb"))
		zbx_proc_stat = ZBX_PROC_STAT_ZOMB;
	else if (0 == strcmp(param, "disk"))
		zbx_proc_stat = ZBX_PROC_STAT_DISK;
	else if (0 == strcmp(param, "trace"))
		zbx_proc_stat = ZBX_PROC_STAT_TRACE;
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid third parameter."));
		ret = SYSINFO_RET_FAIL;
		goto clean;
	}

	proccomm = get_rparam(request, 3);

	if (NULL != proccomm && '\0' != *proccomm)
	{
		if (SUCCEED != zbx_regexp_compile(proccomm, &proccomm_rxp, &rxp_error))
		{
			SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Invalid regular expression in fourth parameter: "
					"%s", rxp_error));

			zbx_free(rxp_error);
			ret = SYSINFO_RET_FAIL;
			goto clean;
		}
	}

	if (1 == invalid_user)	/* handle 0 for non-existent user after all parameters have been parsed and validated */
		goto out;

	mib[0] = CTL_KERN;
	mib[1] = KERN_PROC;
	if (NULL != usrinfo)
	{
		mib[2] = KERN_PROC_UID;
		mib[3] = usrinfo->pw_uid;
		mibs = 4;
	}
	else
	{
#if (__FreeBSD_version) > 500000
		mib[2] = KERN_PROC_PROC;
#else
		mib[2] = KERN_PROC_ALL;
#endif
		mib[3] = 0;
		mibs = 3;
	}

	sz = 0;
	if (0 != sysctl(mib, mibs, NULL, &sz, NULL, 0))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain necessary buffer size from system: %s",
				zbx_strerror(errno)));
		ret = SYSINFO_RET_FAIL;
		goto clean;
	}

	proc = (struct kinfo_proc *)zbx_malloc(proc, sz);
	if (0 != sysctl(mib, mibs, proc, &sz, NULL, 0))
	{
		zbx_free(proc);
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain process information: %s",
				zbx_strerror(errno)));
		ret = SYSINFO_RET_FAIL;
		goto clean;
	}

	count = sz / sizeof(struct kinfo_proc);

	for (int i = 0; i < count; i++)
	{
		proc_ok = 0;
		stat_ok = 0;
		comm_ok = 0;

		if (NULL == procname || '\0' == *procname || 0 == strcmp(procname, proc[i].ZBX_PROC_COMM))
			proc_ok = 1;

		if (zbx_proc_stat != ZBX_PROC_STAT_ALL)
		{
			switch (zbx_proc_stat) {
			case ZBX_PROC_STAT_RUN:
				if (SRUN == proc[i].ZBX_PROC_STAT)
					stat_ok = 1;
				break;
			case ZBX_PROC_STAT_TRACE:
				if (SSTOP == proc[i].ZBX_PROC_STAT)
					stat_ok = 1;
				break;
			case ZBX_PROC_STAT_ZOMB:
				if (SZOMB == proc[i].ZBX_PROC_STAT)
					stat_ok = 1;
				break;
#if (__FreeBSD_version) < 700000
			case ZBX_PROC_STAT_SLEEP:
			case ZBX_PROC_STAT_DISK:
				if (SSLEEP == proc[i].ZBX_PROC_STAT)
					stat_ok = 1;
				break;
#else
			case ZBX_PROC_STAT_SLEEP:
				if (SSLEEP == proc[i].ZBX_PROC_STAT && 0 != (proc[i].ZBX_PROC_TDFLAG & TDF_SINTR))
					stat_ok = 1;
				break;
			case ZBX_PROC_STAT_DISK:
				if (SSLEEP == proc[i].ZBX_PROC_STAT && 0 == (proc[i].ZBX_PROC_TDFLAG & TDF_SINTR))
					stat_ok = 1;
				break;
#endif
			}
		}
		else
			stat_ok = 1;

		if (NULL != proccomm && '\0' != *proccomm)
		{
			if (NULL != (args = get_commandline(&proc[i])))
				if (0 == zbx_regexp_match_precompiled(args, proccomm_rxp))
					comm_ok = 1;
		}
		else
			comm_ok = 1;

		if (proc_ok && stat_ok && comm_ok)
			proccount++;
	}
	zbx_free(proc);
out:
	SET_UI64_RESULT(result, proccount);
clean:
	if (NULL != proccomm_rxp)
		zbx_regexp_free(proccomm_rxp);

	return ret;
}

static char	*get_state(struct kinfo_proc *proc)
{
	char	*state;

	switch (proc->ZBX_PROC_STAT)
	{
		case SRUN:
			state = zbx_strdup(NULL, "running");
			break;
		case SZOMB:
			state = zbx_strdup(NULL, "zombie");
			break;
		case SSTOP:
			state = zbx_strdup(NULL, "tracing stop");
			break;
		case SSLEEP:
#if (__FreeBSD_version) < 700000
			state = zbx_strdup(NULL, "sleeping");
#else
			if (0 != (proc->ZBX_PROC_TDFLAG & TDF_SINTR))
				state = zbx_strdup(NULL, "sleeping");
			else
				state = zbx_strdup(NULL, "disk sleep");
#endif

			break;
		default:
			state = zbx_strdup(NULL, "other");
	}

	return state;
}

int	proc_get(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char				*procname, *proccomm, *param, *args, *rxp_error = NULL;
	int				count, mib[4], mibs, zbx_proc_mode, pagesize, invalid_user = 0;
	size_t				sz;
	struct kinfo_proc		*proc = NULL;
	struct passwd			*usrinfo;
	zbx_vector_proc_data_ptr_t	proc_data_ctx;
	struct zbx_json			j;
#if (__FreeBSD_version) < 500000
	int				mem_pages;
#else
	unsigned long			mem_pages;
#endif
	zbx_regexp_t			*proccomm_rxp = NULL;

	if (4 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	procname = get_rparam(request, 0);
	param = get_rparam(request, 1);

	if (NULL != param && '\0' != *param)
	{
		errno = 0;

		if (NULL == (usrinfo = getpwnam(param)))
		{
			if (0 != errno)
			{
				SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain user information: %s",
						zbx_strerror(errno)));
				return SYSINFO_RET_FAIL;
			}

			invalid_user = 1;
		}
	}
	else
		usrinfo = NULL;

	proccomm = get_rparam(request, 2);

	if (NULL != proccomm && '\0' != *proccomm)
	{
		if (SUCCEED != zbx_regexp_compile(proccomm, &proccomm_rxp, &rxp_error))
		{
			SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Invalid regular expression in third parameter: "
					"%s", rxp_error));

			zbx_free(rxp_error);
			return SYSINFO_RET_FAIL;
		}
	}

	param = get_rparam(request, 3);

	if (NULL == param || '\0' == *param || 0 == strcmp(param, "process"))
	{
		zbx_proc_mode = ZBX_PROC_MODE_PROCESS;
	}
	else if (0 == strcmp(param, "thread"))
	{
		zbx_proc_mode = ZBX_PROC_MODE_THREAD;
	}
	else if (0 == strcmp(param, "summary") && (NULL == proccomm || '\0' == *proccomm))
	{
		zbx_proc_mode = ZBX_PROC_MODE_SUMMARY;
	}
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid fourth parameter."));
		return SYSINFO_RET_FAIL;
	}

	if (1 == invalid_user)
	{
		zbx_json_initarray(&j, ZBX_JSON_STAT_BUF_LEN);
		goto out;
	}

	pagesize = getpagesize();
	sz = sizeof(mem_pages);

	if (0 != sysctlbyname("hw.availpages", &mem_pages, &sz, NULL, (size_t)0))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain number of physical pages: %s",
				zbx_strerror(errno)));
		return SYSINFO_RET_FAIL;
	}

	mib[0] = CTL_KERN;
	mib[1] = KERN_PROC;

	if (NULL != usrinfo)
	{
		mib[2] = KERN_PROC_UID;
		mib[3] = usrinfo->pw_uid;
		mibs = 4;
	}
	else
	{
#if (__FreeBSD_version) > 500000
		mib[2] = KERN_PROC_PROC;
#else
		mib[2] = KERN_PROC_ALL;
#endif
		mib[3] = 0;
		mibs = 3;
	}

	sz = 0;

	if (0 != sysctl(mib, mibs, NULL, &sz, NULL, 0))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain necessary buffer size from system: %s",
				zbx_strerror(errno)));
		return SYSINFO_RET_FAIL;
	}

	proc = (struct kinfo_proc *)zbx_malloc(proc, sz);

	if (0 != sysctl(mib, mibs, proc, &sz, NULL, 0))
	{
		zbx_free(proc);
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain process information: %s",
				zbx_strerror(errno)));
		return SYSINFO_RET_FAIL;
	}

	count = sz / sizeof(struct kinfo_proc);
	zbx_vector_proc_data_ptr_create(&proc_data_ctx);

	for (int i = 0; i < count; i++)
	{
		proc_data_t	*proc_data;
		struct passwd	*pw;
		struct group	*gr;

		if (NULL != procname && '\0' != *procname && 0 != strcmp(procname, proc[i].ZBX_PROC_COMM))
			continue;

		if (NULL == (args = get_commandline(&proc[i])))
			continue;

		if (NULL != proccomm && '\0' != *proccomm && 0 != zbx_regexp_match_precompiled(args, proccomm_rxp))
			continue;

		pw = getpwuid(proc[i].ZBX_PROC_UID);
		gr = getgrgid(proc[i].ZBX_PROC_GID);

		if (ZBX_PROC_MODE_THREAD == zbx_proc_mode)
		{
			int			count_thread, mib_thread[4], mibs_thread;
			struct kinfo_proc	*proc_thread = NULL;

			sz = 0;
			mib_thread[0] = CTL_KERN;
			mib_thread[1] = KERN_PROC;
			mib_thread[2] = KERN_PROC_PID | KERN_PROC_INC_THREAD;
			mib_thread[3] = proc[i].ZBX_PROC_PID;
			mibs_thread = 4;

			if (0 != sysctl(mib_thread, mibs_thread, NULL, &sz, NULL, 0))
				continue;

			proc_thread = (struct kinfo_proc *)zbx_malloc(proc_thread, sz);

			if (0 != sysctl(mib_thread, mibs_thread, proc_thread, &sz, NULL, 0))
			{
				zbx_free(proc_thread);
				continue;
			}

			count_thread = sz / sizeof(struct kinfo_proc);

			for (int k = 0; k < count_thread; k++)
			{
				proc_data = (proc_data_t *)zbx_malloc(NULL, sizeof(proc_data_t));

#if (__FreeBSD_version) < 500000
				proc_data->tid = proc_data->jid = 0;
				proc_data->tname = NULL;
#else
				proc_data->tid = proc_thread[k].ZBX_PROC_TID;
				proc_data->jid = proc_thread[k].ZBX_PROC_JID;
				proc_data->tname = zbx_strdup(NULL, proc_thread[k].ZBX_PROC_TNAME);
#endif
				proc_data->pid = proc_thread[k].ZBX_PROC_PID;
				proc_data->ppid = proc_thread[k].ZBX_PROC_PPID;
#if HAVE_LIBJAIL
				proc_data->jname = jail_getname(proc_data->jid);
#else
				proc_data->jname = NULL;
#endif
				proc_data->name = zbx_strdup(NULL, proc_thread[k].ZBX_PROC_COMM);
				proc_data->state = get_state(&proc_thread[k]);
				proc_data->uid = proc[i].ZBX_PROC_UID;
				proc_data->gid = proc[i].ZBX_PROC_GID;
				proc_data->user = NULL != pw ? zbx_strdup(NULL, pw->pw_name) :
						zbx_dsprintf(NULL, ZBX_FS_UI64, proc_data->uid);
				proc_data->group = NULL != gr ? zbx_strdup(NULL, gr->gr_name) :
						zbx_dsprintf(NULL, ZBX_FS_UI64, proc_data->gid);
				proc_data->cputime_user = proc_thread[k].ZBX_PROC_UTIME;
				proc_data->cputime_system = proc_thread[k].ZBX_PROC_STIME;
				proc_data->io_write_op = proc_thread[k].ZBX_PROC_INBLOCK;
				proc_data->io_read_op = proc_thread[k].ZBX_PROC_OUBLOCK;
				proc_data->ctx_switches = proc_thread[k].ZBX_PROC_NVCSW +
						proc_thread[k].ZBX_PROC_NIVCSW;

				proc_data->cmdline = NULL;

				zbx_vector_proc_data_ptr_append(&proc_data_ctx, proc_data);
			}

			zbx_free(proc_thread);
		}
		else
		{
			proc_data = (proc_data_t *)zbx_malloc(NULL, sizeof(proc_data_t));

			proc_data->name = zbx_strdup(NULL, proc[i].ZBX_PROC_COMM);
			proc_data->threads = proc[i].ZBX_PROC_NUMTHREADS;
			proc_data->size = (proc[i].ZBX_PROC_TSIZE + proc[i].ZBX_PROC_DSIZE + proc[i].ZBX_PROC_SSIZE)
					* pagesize;
			proc_data->rss = proc[i].ZBX_PROC_RSSIZE * pagesize;
			proc_data->vsize = proc[i].ZBX_PROC_VSIZE;
			proc_data->tsize = proc[i].ZBX_PROC_TSIZE * pagesize;
			proc_data->dsize = proc[i].ZBX_PROC_DSIZE * pagesize;
			proc_data->ssize = proc[i].ZBX_PROC_SSIZE * pagesize;
			proc_data->page_faults = proc[i].ZBX_PROC_MAJFLT;
			proc_data->swap = proc[i].ZBX_PROC_SWAP;
			proc_data->ctx_switches = proc[i].ZBX_PROC_NVCSW + proc[i].ZBX_PROC_NIVCSW;
			proc_data->io_write_op = proc[i].ZBX_PROC_INBLOCK;
			proc_data->io_read_op = proc[i].ZBX_PROC_OUBLOCK;
			proc_data->cputime_user = proc[i].ZBX_PROC_UTIME;
			proc_data->cputime_system = proc[i].ZBX_PROC_STIME;

			if (0 != (proc[i].ZBX_PROC_FLAG & ZBX_PROC_MASK))
			{
#if (__FreeBSD_version) < 500000
				proc_data->pmem = ((float)(proc[i].ZBX_PROC_RSSIZE + UPAGES) / mem_pages) * 100.0;
#else
				proc_data->pmem = ((float)proc[i].ZBX_PROC_RSSIZE / mem_pages) * 100.0;
#endif
			}
			else
				proc_data->pmem = 0.0;

			if (ZBX_PROC_MODE_PROCESS == zbx_proc_mode)
			{
#if (__FreeBSD_version) < 500000
				proc_data->jid = 0;
#else
				proc_data->jid = proc[i].ZBX_PROC_JID;
#endif
				proc_data->pid = proc[i].ZBX_PROC_PID;
				proc_data->ppid = proc[i].ZBX_PROC_PPID;
#if HAVE_LIBJAIL
				proc_data->jname = jail_getname(proc_data->jid);
#else
				proc_data->jname = NULL;
#endif
				proc_data->cmdline = zbx_strdup(NULL, args);
				proc_data->state = get_state(&proc[i]);
				proc_data->uid = proc[i].ZBX_PROC_UID;
				proc_data->gid = proc[i].ZBX_PROC_GID;
				proc_data->user = NULL != pw ? zbx_strdup(NULL, pw->pw_name) :
						zbx_dsprintf(NULL, ZBX_FS_UI64, proc_data->uid);
				proc_data->group = NULL != gr ? zbx_strdup(NULL, gr->gr_name) :
						zbx_dsprintf(NULL, ZBX_FS_UI64, proc_data->gid);
			}
			else
			{
				proc_data->jname = NULL;
				proc_data->cmdline = NULL;
				proc_data->state = NULL;
				proc_data->user = NULL;
				proc_data->group = NULL;
			}

			proc_data->tname = NULL;

			zbx_vector_proc_data_ptr_append(&proc_data_ctx, proc_data);
		}
	}

	zbx_free(proc);

	if (ZBX_PROC_MODE_SUMMARY == zbx_proc_mode)
	{
		for (int i = 0; i < proc_data_ctx.values_num; i++)
		{
			proc_data_t	*pdata = proc_data_ctx.values[i];

			pdata->processes = 1;

			for (int k = i + 1; k < proc_data_ctx.values_num; k++)
			{
				proc_data_t	*pdata_cmp = proc_data_ctx.values[k];

				if (0 == strcmp(pdata->name, pdata_cmp->name))
				{
					pdata->processes++;
					pdata->threads += pdata_cmp->threads;
					pdata->rss += pdata_cmp->rss;
					pdata->vsize += pdata_cmp->vsize;
					pdata->tsize += pdata_cmp->tsize;
					pdata->dsize += pdata_cmp->dsize;
					pdata->ssize += pdata_cmp->ssize;
					pdata->pmem += pdata_cmp->pmem;
					pdata->size += pdata_cmp->size;
					pdata->swap += pdata_cmp->swap;
					pdata->cputime_user += pdata_cmp->cputime_user;
					pdata->cputime_system += pdata_cmp->cputime_system;
					pdata->ctx_switches += pdata_cmp->ctx_switches;
					pdata->page_faults += pdata_cmp->page_faults;
					pdata->io_read_op += pdata_cmp->io_read_op;
					pdata->io_write_op += pdata_cmp->io_write_op;

					proc_data_free(pdata_cmp);
					zbx_vector_proc_data_ptr_remove(&proc_data_ctx, k--);
				}
			}
		}
	}

	zbx_json_initarray(&j, ZBX_JSON_STAT_BUF_LEN);

	for (int i = 0; i < proc_data_ctx.values_num; i++)
	{
		proc_data_t	*pdata = proc_data_ctx.values[i];

		zbx_json_addobject(&j, NULL);

		if (ZBX_PROC_MODE_PROCESS == zbx_proc_mode)
		{
			zbx_json_addint64(&j, "pid", pdata->pid);
			zbx_json_addint64(&j, "ppid", pdata->ppid);
			zbx_json_addint64(&j, "jid", pdata->jid);
			zbx_json_addstring(&j, "name", ZBX_NULL2EMPTY_STR(pdata->name), ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&j, "jname", pdata->jname, ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&j, "cmdline", ZBX_NULL2EMPTY_STR(pdata->cmdline), ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&j, "user", ZBX_NULL2EMPTY_STR(pdata->user), ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&j, "group", ZBX_NULL2EMPTY_STR(pdata->group), ZBX_JSON_TYPE_STRING);
			zbx_json_adduint64(&j, "uid", pdata->uid);
			zbx_json_adduint64(&j, "gid", pdata->gid);
			zbx_json_adduint64(&j, "vsize", pdata->vsize);
			zbx_json_addfloat(&j, "pmem", pdata->pmem);
			zbx_json_adduint64(&j, "rss", pdata->rss);
			zbx_json_adduint64(&j, "size", pdata->size);
			zbx_json_adduint64(&j, "tsize", pdata->tsize);
			zbx_json_adduint64(&j, "dsize", pdata->dsize);
			zbx_json_adduint64(&j, "ssize", pdata->ssize);
			zbx_json_adduint64(&j, "cputime_user", pdata->cputime_user);
			zbx_json_adduint64(&j, "cputime_system", pdata->cputime_system);
			zbx_json_addstring(&j, "state", ZBX_NULL2EMPTY_STR(pdata->state), ZBX_JSON_TYPE_STRING);
			zbx_json_adduint64(&j, "ctx_switches", pdata->ctx_switches);
			zbx_json_adduint64(&j, "threads", pdata->threads);
			zbx_json_adduint64(&j, "page_faults", pdata->page_faults);
			zbx_json_adduint64(&j, "swap", pdata->swap);
			zbx_json_adduint64(&j, "io_read_op", pdata->io_read_op);
			zbx_json_adduint64(&j, "io_write_op", pdata->io_write_op);
		}
		else if (ZBX_PROC_MODE_THREAD == zbx_proc_mode)
		{
			zbx_json_addint64(&j, "pid", pdata->pid);
			zbx_json_addint64(&j, "ppid", pdata->ppid);
			zbx_json_addint64(&j, "jid", pdata->jid);
			zbx_json_addstring(&j, "name", ZBX_NULL2EMPTY_STR(pdata->name), ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&j, "jname", pdata->jname, ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&j, "user", ZBX_NULL2EMPTY_STR(pdata->user), ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&j, "group", ZBX_NULL2EMPTY_STR(pdata->group), ZBX_JSON_TYPE_STRING);
			zbx_json_adduint64(&j, "uid", pdata->uid);
			zbx_json_adduint64(&j, "gid", pdata->gid);
			zbx_json_addint64(&j, "tid", pdata->tid);
			zbx_json_addstring(&j, "tname", ZBX_NULL2EMPTY_STR(pdata->tname), ZBX_JSON_TYPE_STRING);
			zbx_json_adduint64(&j, "cputime_user", pdata->cputime_user);
			zbx_json_adduint64(&j, "cputime_system", pdata->cputime_system);
			zbx_json_addstring(&j, "state", ZBX_NULL2EMPTY_STR(pdata->state), ZBX_JSON_TYPE_STRING);
			zbx_json_adduint64(&j, "ctx_switches", pdata->ctx_switches);
			zbx_json_adduint64(&j, "io_read_op", pdata->io_read_op);
			zbx_json_adduint64(&j, "io_write_op", pdata->io_write_op);
		}
		else
		{
			zbx_json_addstring(&j, "name", ZBX_NULL2EMPTY_STR(pdata->name), ZBX_JSON_TYPE_STRING);
			zbx_json_adduint64(&j, "processes", pdata->processes);
			zbx_json_adduint64(&j, "vsize", pdata->vsize);
			zbx_json_addfloat(&j, "pmem", pdata->pmem);
			zbx_json_adduint64(&j, "rss", pdata->rss);
			zbx_json_adduint64(&j, "size", pdata->size);
			zbx_json_adduint64(&j, "tsize", pdata->tsize);
			zbx_json_adduint64(&j, "dsize", pdata->dsize);
			zbx_json_adduint64(&j, "ssize", pdata->ssize);
			zbx_json_adduint64(&j, "cputime_user", pdata->cputime_user);
			zbx_json_adduint64(&j, "cputime_system", pdata->cputime_system);
			zbx_json_adduint64(&j, "ctx_switches", pdata->ctx_switches);
			zbx_json_adduint64(&j, "threads", pdata->threads);
			zbx_json_adduint64(&j, "page_faults", pdata->page_faults);
			zbx_json_adduint64(&j, "swap", pdata->swap);
			zbx_json_adduint64(&j, "io_read_op", pdata->io_read_op);
			zbx_json_adduint64(&j, "io_write_op", pdata->io_write_op);
		}

		zbx_json_close(&j);
	}

	zbx_vector_proc_data_ptr_clear_ext(&proc_data_ctx, proc_data_free);
	zbx_vector_proc_data_ptr_destroy(&proc_data_ctx);
out:
	zbx_json_close(&j);
	SET_STR_RESULT(result, zbx_strdup(NULL, j.buffer));
	zbx_json_free(&j);

	if (NULL != proccomm_rxp)
		zbx_regexp_free(proccomm_rxp);

	return SYSINFO_RET_OK;
}
