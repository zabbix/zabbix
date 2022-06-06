/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "common.h"
#include "sysinfo.h"
#include "zbxregexp.h"
#include "log.h"
#include "zbxjson.h"

#include <sys/sysctl.h>

#define ARGS_START_SIZE 64

/* in OpenBSD 5.1 KERN_PROC2 became KERN_PROC and structure kinfo_proc2 became kinfo_proc */
#if OpenBSD >= 201205		/* OpenBSD 5.1 version as year and month */
#	ifndef KERN_PROC2
#		define KERN_PROC2	KERN_PROC
#	endif
#	ifndef kinfo_proc2
#		define kinfo_proc2	kinfo_proc
#	endif
#endif

#ifdef KERN_PROC2
#	define ZBX_P_COMM		p_comm
#	define ZBX_P_FLAG		p_flag
#	define ZBX_P_PID		p_pid
#	define ZBX_P_PPID		p_ppid
#	define ZBX_P_TID		p_tid
#	define ZBX_P_STAT		p_stat
#	define ZBX_P_VM_RSSIZE		p_vm_rssize
#	define ZBX_P_VM_VSIZE		p_vm_map_size
#	define ZBX_P_VM_TSIZE		p_vm_tsize
#	define ZBX_P_VM_DSIZE		p_vm_dsize
#	define ZBX_P_VM_SSIZE		p_vm_ssize
#	define ZBX_P_MAJFLT		p_uru_majflt
#	define ZBX_P_SWAP		p_uru_nswap
#	define ZBX_P_INBLOCK		p_uru_inblock
#	define ZBX_P_OUBLOCK		p_uru_oublock
#	define ZBX_P_NVCSW		p_uru_nvcsw
#	define ZBX_P_NIVCSW		p_uru_nivcsw
#	define ZBX_P_UTIME		p_uutime_sec
#	define ZBX_P_STIME		p_ustime_sec
#	define ZBX_P_UID		p_ruid
#	define ZBX_P_GID		p_rgid
#	define ZBX_STRUCT_KINFO_PROC	kinfo_proc2
#	define ZBX_KINFO_MIBS_NUM	6
#else
#	define ZBX_P_COMM		kp_proc.p_comm
#	define ZBX_P_FLAG		kp_proc.p_flag
#	define ZBX_P_PID		kp_proc.p_pid
#	define ZBX_P_PPID		kp_eproc.e_ppid
#	define ZBX_P_TID		kp_proc.p_tid
#	define ZBX_P_STAT		kp_proc.p_stat
#	define ZBX_P_VM_RSSIZE		kp_eproc.e_vm.vm_rssize
#	define ZBX_P_VM_VSIZE		kp_eproc.e_vm.vm_map.size
#	define ZBX_P_VM_TSIZE		kp_eproc.e_vm.vm_tsize
#	define ZBX_P_VM_DSIZE		kp_eproc.e_vm.vm_dsize
#	define ZBX_P_VM_SSIZE		kp_eproc.e_vm.vm_ssize
#	define ZBX_P_MAJFLT		kp_eproc.e_pstats.p_ru.ru_majflt
#	define ZBX_P_SWAP		kp_eproc.e_pstats.p_ru.ru_nswap
#	define ZBX_P_INBLOCK		kp_eproc.e_pstats.p_ru.ru_inblock
#	define ZBX_P_OUBLOCK		kp_eproc.e_pstats.p_ru.ru_oublock
#	define ZBX_P_NVCSW		kp_eproc.e_pstats.p_ru.ru_nvcsw
#	define ZBX_P_NIVCSW		kp_eproc.e_pstats.p_ru.ru_nivcsw
#	define ZBX_P_UTIME		kp_eproc.e_pstats.p_ru.ru_utime.tv_sec
#	define ZBX_P_STIME		kp_eproc.e_pstats.p_ru.ru_stime.tv_sec
#	define ZBX_P_UID		kp_proc.p_ruid
#	define ZBX_P_GID		kp_proc.p_rgid
#	define ZBX_STRUCT_KINFO_PROC	kinfo_proc
#	define ZBX_KINFO_MIBS_NUM	4
#endif

typedef struct
{
	int		pid;
	int		ppid;
	int		tid;

	char		*name;
	char		*cmdline;
	char		*state;
	char		*tname;
	zbx_uint64_t	processes;

	char		*user;
	char		*group;
	zbx_uint64_t	uid;
	zbx_uint64_t	gid;

	zbx_uint64_t	cputime_user;
	zbx_uint64_t	cputime_system;
	zbx_uint64_t	ctx_switches;
	zbx_int64_t	threads;
	zbx_uint64_t	page_faults;
	zbx_int64_t	fds;
	zbx_uint64_t	io_read_op;
	zbx_uint64_t	io_write_op;

	zbx_uint64_t	vsize;
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
	zbx_free(proc_data->cmdline);
	zbx_free(proc_data->state);
	zbx_free(proc_data->tname);
	zbx_free(proc_data->user);
	zbx_free(proc_data->group);

	zbx_free(proc_data);
}

static int	proc_argv(pid_t pid, char ***argv, size_t *argv_alloc, int *argc)
{
	size_t	sz;
	int	mib[4];

	if (NULL == *argv)
	{
		*argv_alloc = ARGS_START_SIZE;
		*argv = zbx_malloc(*argv, *argv_alloc);
	}

	mib[0] = CTL_KERN;
	mib[1] = KERN_PROC_ARGS;
	mib[2] = (int)pid;
	mib[3] = KERN_PROC_ARGV;
retry:
	sz = *argv_alloc;
	if (0 != sysctl(mib, 4, *argv, &sz, NULL, 0))
	{
		if (errno == ENOMEM)
		{
			*argv_alloc *= 2;
			*argv = zbx_realloc(*argv, *argv_alloc);
			goto retry;
		}
		return FAIL;
	}

	mib[3] = KERN_PROC_NARGV;

	sz = sizeof(int);
	if (0 != sysctl(mib, 4, argc, &sz, NULL, 0))
		return FAIL;

	return SUCCEED;
}

static void	collect_args(char **argv, int argc, char **args, size_t *args_alloc)
{
	int	i;
	size_t	args_offset = 0;

	if (0 == *args_alloc)
	{
		*args_alloc = ARGS_START_SIZE;
		*args = zbx_malloc(*args, *args_alloc);
	}

	for (i = 0; i < argc; i++)
		zbx_snprintf_alloc(args, args_alloc, &args_offset, "%s ", argv[i]);

	if (0 != args_offset)
		args_offset--; /* ' ' */
	(*args)[args_offset] = '\0';
}

int     PROC_MEM(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char			*procname, *proccomm, *param;
	int			do_task, pagesize, count, i, proccount = 0, invalid_user = 0, proc_ok, comm_ok;
	double			value = 0.0, memsize = 0;
	size_t			sz;
	struct passwd		*usrinfo;
#ifdef KERN_PROC2
	int			mib[6];
	struct kinfo_proc2	*proc = NULL;
#else
	int			mib[4];
	struct kinfo_proc	*proc = NULL;
#endif
	char			**argv = NULL, *args = NULL;
	size_t			argv_alloc = 0, args_alloc = 0;
	int			argc;

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
		return SYSINFO_RET_FAIL;
	}

	proccomm = get_rparam(request, 3);

	if (1 == invalid_user)	/* handle 0 for non-existent user after all parameters have been parsed and validated */
		goto out;

	pagesize = getpagesize();

	mib[0] = CTL_KERN;
	if (NULL != usrinfo)
	{
		mib[2] = KERN_PROC_UID;
		mib[3] = usrinfo->pw_uid;
	}
	else
	{
		mib[2] = KERN_PROC_ALL;
		mib[3] = 0;
	}

#ifdef KERN_PROC2
	mib[1] = KERN_PROC2;
	mib[4] = sizeof(struct kinfo_proc2);
	mib[5] = 0;

	sz = 0;
	if (0 != sysctl(mib, 6, NULL, &sz, NULL, 0))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain necessary buffer size from system: %s",
				zbx_strerror(errno)));
		return SYSINFO_RET_FAIL;
	}

	proc = (struct kinfo_proc2 *)zbx_malloc(proc, sz);
	mib[5] = (int)(sz / sizeof(struct kinfo_proc2));
	if (0 != sysctl(mib, 6, proc, &sz, NULL, 0))
	{
		zbx_free(proc);
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain process information: %s",
				zbx_strerror(errno)));
		return SYSINFO_RET_FAIL;
	}

	count = sz / sizeof(struct kinfo_proc2);
#else
	mib[1] = KERN_PROC;

	sz = 0;
	if (0 != sysctl(mib, 4, NULL, &sz, NULL, 0))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain necessary buffer size from system: %s",
				zbx_strerror(errno)));
		return SYSINFO_RET_FAIL;
	}

	proc = (struct kinfo_proc *)zbx_malloc(proc, sz);
	if (0 != sysctl(mib, 4, proc, &sz, NULL, 0))
	{
		zbx_free(proc);
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain process information: %s",
				zbx_strerror(errno)));
		return SYSINFO_RET_FAIL;
	}

	count = sz / sizeof(struct kinfo_proc);
#endif
	for (i = 0; i < count; i++)
	{
		proc_ok = 0;
		comm_ok = 0;

		if (NULL == procname || '\0' == *procname || 0 == strcmp(procname, proc[i].ZBX_P_COMM))
			proc_ok = 1;

		if (NULL != proccomm && '\0' != *proccomm)
		{
			if (SUCCEED == proc_argv(proc[i].ZBX_P_PID, &argv, &argv_alloc, &argc))
			{
				collect_args(argv, argc, &args, &args_alloc);
				if (NULL != zbx_regexp_match(args, proccomm, NULL))
					comm_ok = 1;
			}
		}
		else
			comm_ok = 1;

		if (proc_ok && comm_ok)
		{
			value = proc[i].ZBX_P_VM_TSIZE + proc[i].ZBX_P_VM_DSIZE + proc[i].ZBX_P_VM_SSIZE;
			value *= pagesize;

			if (0 == proccount++)
				memsize = value;
			else
			{
				if (ZBX_DO_MAX == do_task)
					memsize = MAX(memsize, value);
				else if (ZBX_DO_MIN == do_task)
					memsize = MIN(memsize, value);
				else
					memsize += value;
			}
		}
	}
	zbx_free(proc);
	zbx_free(argv);
	zbx_free(args);
out:
	if (ZBX_DO_AVG == do_task)
		SET_DBL_RESULT(result, 0 == proccount ? 0 : memsize / proccount);
	else
		SET_UI64_RESULT(result, memsize);

	return SYSINFO_RET_OK;
}

int	PROC_NUM(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char			*procname, *proccomm, *param;
	int			proccount = 0, invalid_user = 0, zbx_proc_stat, count, i, proc_ok, stat_ok, comm_ok;
	size_t			sz;
	struct passwd		*usrinfo;
#ifdef KERN_PROC2
	int			mib[6];
	struct kinfo_proc2	*proc = NULL;
#else
	int			mib[4];
	struct kinfo_proc	*proc = NULL;
#endif
	char			**argv = NULL, *args = NULL;
	size_t			argv_alloc = 0, args_alloc = 0;
	int			argc;

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
		return SYSINFO_RET_FAIL;
	}

	proccomm = get_rparam(request, 3);

	if (1 == invalid_user)	/* handle 0 for non-existent user after all parameters have been parsed and validated */
		goto out;

	mib[0] = CTL_KERN;
	if (NULL != usrinfo)
	{
		mib[2] = KERN_PROC_UID;
		mib[3] = usrinfo->pw_uid;
	}
	else
	{
		mib[2] = KERN_PROC_ALL;
		mib[3] = 0;
	}

#ifdef KERN_PROC2
	mib[1] = KERN_PROC2;
	mib[4] = sizeof(struct kinfo_proc2);
	mib[5] = 0;

	sz = 0;
	if (0 != sysctl(mib, 6, NULL, &sz, NULL, 0))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain necessary buffer size from system: %s",
				zbx_strerror(errno)));
		return SYSINFO_RET_FAIL;
	}

	proc = (struct kinfo_proc2 *)zbx_malloc(proc, sz);
	mib[5] = (int)(sz / sizeof(struct kinfo_proc2));
	if (0 != sysctl(mib, 6, proc, &sz, NULL, 0))
	{
		zbx_free(proc);
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain process information: %s",
				zbx_strerror(errno)));
		return SYSINFO_RET_FAIL;
	}

	count = sz / sizeof(struct kinfo_proc2);
#else
	mib[1] = KERN_PROC;

	sz = 0;
	if (0 != sysctl(mib, 4, NULL, &sz, NULL, 0))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain necessary buffer size from system: %s",
				zbx_strerror(errno)));
		return SYSINFO_RET_FAIL;
	}

	proc = (struct kinfo_proc *)zbx_malloc(proc, sz);
	if (0 != sysctl(mib, 4, proc, &sz, NULL, 0))
	{
		zbx_free(proc);
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain process information: %s",
				zbx_strerror(errno)));
		return SYSINFO_RET_FAIL;
	}

	count = sz / sizeof(struct kinfo_proc);
#endif

	for (i = 0; i < count; i++)
	{
		proc_ok = 0;
		stat_ok = 0;
		comm_ok = 0;

		if (NULL == procname || '\0' == *procname || 0 == strcmp(procname, proc[i].ZBX_P_COMM))
			proc_ok = 1;

		if (ZBX_PROC_STAT_ALL != zbx_proc_stat)
		{
			switch (zbx_proc_stat)
			{
				case ZBX_PROC_STAT_RUN:
					if (SRUN == proc[i].ZBX_P_STAT || SONPROC == proc[i].ZBX_P_STAT)
						stat_ok = 1;
					break;
				case ZBX_PROC_STAT_SLEEP:
					if (SSLEEP == proc[i].ZBX_P_STAT && 0 != (proc[i].ZBX_P_FLAG & P_SINTR))
						stat_ok = 1;
					break;
				case ZBX_PROC_STAT_ZOMB:
					if (SZOMB == proc[i].ZBX_P_STAT || SDEAD == proc[i].ZBX_P_STAT)
						stat_ok = 1;
					break;
				case ZBX_PROC_STAT_DISK:
					if (SSLEEP == proc[i].ZBX_P_STAT && 0 == (proc[i].ZBX_P_FLAG & P_SINTR))
						stat_ok = 1;
					break;
				case ZBX_PROC_STAT_TRACE:
					if (SSTOP == proc[i].ZBX_P_STAT)
						stat_ok = 1;
					break;
			}
		}
		else
			stat_ok = 1;

		if (NULL != proccomm && '\0' != *proccomm)
		{
			if (SUCCEED == proc_argv(proc[i].ZBX_P_PID, &argv, &argv_alloc, &argc))
			{
				collect_args(argv, argc, &args, &args_alloc);
				if (NULL != zbx_regexp_match(args, proccomm, NULL))
					comm_ok = 1;
			}
		}
		else
			comm_ok = 1;

		if (proc_ok && stat_ok && comm_ok)
			proccount++;
	}
	zbx_free(proc);
	zbx_free(argv);
	zbx_free(args);
out:
	SET_UI64_RESULT(result, proccount);

	return SYSINFO_RET_OK;
}

static zbx_int64_t	get_fds(int pid)
{
	int			mib[ZBX_KINFO_MIBS_NUM], num;
	size_t			sz;
	struct kinfo_file	*kf = NULL;

	mib[0] = CTL_KERN;
	mib[1] = KERN_FILE;
	mib[2] = KERN_FILE_BYPID;
	mib[3] = pid;
#ifdef KERN_PROC2
	mib[4] = sizeof(struct kinfo_file);
	mib[5] = 0;
#endif

	if (0 != sysctl(mib, ZBX_KINFO_MIBS_NUM, NULL, &sz, NULL, 0))
		return -1;

	kf = (struct kinfo_file*)zbx_malloc(kf, sz);

	if (0 != sysctl(mib, ZBX_KINFO_MIBS_NUM, kf, &sz, NULL, 0))
	{
		zbx_free(kf);
		return -1;
	}

	num = sz / sizeof(struct kinfo_file);
	zbx_free(kf);

	return num;
}

static int	get_kinfo_proc(struct ZBX_STRUCT_KINFO_PROC **proc, struct passwd *usrinfo, int pid, int *count,
		char **error)
{
	int	mib[ZBX_KINFO_MIBS_NUM];
	size_t	sz = 0;

	mib[0] = CTL_KERN;
#ifdef KERN_PROC2
	mib[1] = KERN_PROC2;
	mib[4] = sizeof(struct kinfo_proc2);
	mib[5] = 0;
#else
	mib[1] = KERN_PROC;
#endif

	if (-1 != pid)
	{
		mib[2] = KERN_PROC_PID | KERN_PROC_SHOW_THREADS;
		mib[3] = pid;
	}
	else if (NULL != usrinfo)
	{
		mib[2] = KERN_PROC_UID;
		mib[3] = usrinfo->pw_uid;
	}
	else
	{
		mib[2] = KERN_PROC_ALL;
		mib[3] = 0;
	}

	if (0 != sysctl(mib, ZBX_KINFO_MIBS_NUM, NULL, &sz, NULL, 0))
	{
		if (NULL != error)
		{
			*error = zbx_dsprintf(*error, "Cannot obtain necessary buffer size from system: %s",
					zbx_strerror(errno));
		}

		return FAIL;
	}

	*proc = (struct ZBX_STRUCT_KINFO_PROC *)zbx_malloc(NULL, sz);
#ifdef KERN_PROC2
	mib[5] = (int)(sz / sizeof(struct kinfo_proc2));
#endif

	if (0 != sysctl(mib, ZBX_KINFO_MIBS_NUM, *proc, &sz, NULL, 0))
	{
		if (NULL != error)
			*error = zbx_dsprintf(*error, "Cannot obtain process information: %s", zbx_strerror(errno));

		zbx_free(proc);
		return FAIL;
	}

	*count = sz / sizeof(struct ZBX_STRUCT_KINFO_PROC);

	return SUCCEED;
}

static char	*get_state(struct ZBX_STRUCT_KINFO_PROC *proc)
{
	char	*state;

	if (SRUN == proc->ZBX_P_STAT || SONPROC == proc->ZBX_P_STAT)
		state = zbx_strdup(NULL, "running");
	else if (SSLEEP == proc->ZBX_P_STAT && 0 != (proc->ZBX_P_FLAG & P_SINTR))
		state = zbx_strdup(NULL, "sleeping");
	else if (SZOMB == proc->ZBX_P_STAT || SDEAD == proc->ZBX_P_STAT)
		state = zbx_strdup(NULL, "zombie");
	else if (SSLEEP == proc->ZBX_P_STAT && 0 == (proc->ZBX_P_FLAG & P_SINTR))
		state = zbx_strdup(NULL, "disk sleep");
	else if (SSTOP == proc->ZBX_P_STAT)
		state = zbx_strdup(NULL, "tracing stop");
	else
		state = zbx_strdup(NULL, "other");

	return state;
}

int	PROC_GET(AGENT_REQUEST *request, AGENT_RESULT *result)
{
#define SUM_PROC_VALUE(param)					\
	do							\
	{							\
		if (0 <= pdata->param && 0 <= pdata_cmp->param)	\
			pdata->param += pdata_cmp->param;	\
		else if (0 <= pdata->param)			\
			pdata->param = -1;			\
	} while(0)

	char				*procname, *proccomm, *param, *args = NULL, **argv = NULL, *error = NULL;
	int				invalid_user = 0, count, i, k, zbx_proc_mode, argc, pagesize;
	size_t				argv_alloc = 0, args_alloc = 0;
	struct passwd			*usrinfo;
	zbx_vector_proc_data_ptr_t	proc_data_ctx;
	struct zbx_json			j;
	struct ZBX_STRUCT_KINFO_PROC	*proc = NULL;

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

	if (SUCCEED != get_kinfo_proc(&proc, usrinfo, -1, &count, &error))
	{
		SET_MSG_RESULT(result, error);
		return SYSINFO_RET_FAIL;
	}

	zbx_vector_proc_data_ptr_create(&proc_data_ctx);

	for (i = 0; i < count; i++)
	{
		proc_data_t			*proc_data;
		int				count_thread;
		struct ZBX_STRUCT_KINFO_PROC	*proc_thread;
		struct passwd			*pw;
		struct group			*gr;

		if (NULL != procname && '\0' != *procname && 0 != strcmp(procname, proc[i].ZBX_P_COMM))
			continue;

		if (SUCCEED == proc_argv(proc[i].ZBX_P_PID, &argv, &argv_alloc, &argc))
			collect_args(argv, argc, &args, &args_alloc);
		else
			continue;

		if (NULL != proccomm && '\0' != *proccomm && NULL == zbx_regexp_match(args, proccomm, NULL))
			continue;

		pw = getpwuid(proc[i].ZBX_P_UID);
		gr = getgrgid(proc[i].ZBX_P_GID);

		if (ZBX_PROC_MODE_THREAD == zbx_proc_mode)
		{
			if (SUCCEED != get_kinfo_proc(&proc_thread, NULL, proc[i].ZBX_P_PID, &count_thread, NULL))
				continue;

			for (k = 0; k < count_thread; k++)
			{
				if (-1 == proc_thread[k].ZBX_P_TID)
					continue;

				proc_data = (proc_data_t *)zbx_malloc(NULL, sizeof(proc_data_t));

				proc_data->tid = proc_thread[k].ZBX_P_TID;
				proc_data->pid = proc_thread[k].ZBX_P_PID;
				proc_data->ppid = proc_thread[k].ZBX_P_PPID;
				proc_data->name = zbx_strdup(NULL, proc[i].ZBX_P_COMM);
				proc_data->tname = zbx_strdup(NULL, proc_thread[k].ZBX_P_COMM);
				proc_data->state = get_state(&proc_thread[k]);
				proc_data->uid = proc[i].ZBX_P_UID;
				proc_data->gid = proc[i].ZBX_P_GID;
				proc_data->user = NULL != pw ? zbx_strdup(NULL, pw->pw_name) :
						zbx_dsprintf(NULL, ZBX_FS_UI64, proc_data->uid);
				proc_data->group = NULL != gr ? zbx_strdup(NULL, gr->gr_name) :
						zbx_dsprintf(NULL, ZBX_FS_UI64, proc_data->gid);
				proc_data->cputime_user = proc_thread[k].ZBX_P_UTIME;
				proc_data->cputime_system = proc_thread[k].ZBX_P_STIME;
				proc_data->ctx_switches = proc_thread[k].ZBX_P_NVCSW + proc_thread[k].ZBX_P_NIVCSW;
				proc_data->io_read_op = proc_thread[k].ZBX_P_OUBLOCK;
				proc_data->io_write_op = proc_thread[k].ZBX_P_INBLOCK;

				proc_data->cmdline = NULL;

				zbx_vector_proc_data_ptr_append(&proc_data_ctx, proc_data);
			}

			zbx_free(proc_thread);
		}
		else
		{
			proc_data = (proc_data_t *)zbx_malloc(NULL, sizeof(proc_data_t));

			proc_data->name = zbx_strdup(NULL, proc[i].ZBX_P_COMM);
			proc_data->size = (proc[i].ZBX_P_VM_TSIZE + proc[i].ZBX_P_VM_DSIZE + proc[i].ZBX_P_VM_SSIZE)
					* pagesize;
			proc_data->rss = proc[i].ZBX_P_VM_RSSIZE * pagesize;
			proc_data->vsize = proc[i].ZBX_P_VM_VSIZE;
			proc_data->tsize = proc[i].ZBX_P_VM_TSIZE * pagesize;
			proc_data->dsize = proc[i].ZBX_P_VM_DSIZE * pagesize;
			proc_data->ssize = proc[i].ZBX_P_VM_SSIZE * pagesize;
			proc_data->cputime_user = proc[i].ZBX_P_UTIME;
			proc_data->cputime_system = proc[i].ZBX_P_STIME;
			proc_data->ctx_switches = proc[i].ZBX_P_NVCSW + proc[i].ZBX_P_NIVCSW;
			proc_data->page_faults = proc[i].ZBX_P_MAJFLT;
			proc_data->fds = get_fds((int)proc[i].ZBX_P_PID);
			proc_data->io_read_op = proc[i].ZBX_P_OUBLOCK;
			proc_data->io_write_op = proc[i].ZBX_P_INBLOCK;
			proc_data->swap = proc[i].ZBX_P_SWAP;

			if (ZBX_PROC_MODE_PROCESS == zbx_proc_mode)
			{
				proc_data->pid = proc[i].ZBX_P_PID;
				proc_data->ppid = proc[i].ZBX_P_PPID;
				proc_data->cmdline = zbx_strdup(NULL, args);
				proc_data->state = get_state(&proc[i]);
				proc_data->uid = proc[i].ZBX_P_UID;
				proc_data->gid = proc[i].ZBX_P_GID;
				proc_data->user = NULL != pw ? zbx_strdup(NULL, pw->pw_name) :
						zbx_dsprintf(NULL, ZBX_FS_UI64, proc_data->uid);
				proc_data->group = NULL != gr ? zbx_strdup(NULL, gr->gr_name) :
						zbx_dsprintf(NULL, ZBX_FS_UI64, proc_data->gid);
			}
			else
			{
				proc_data->cmdline = NULL;
				proc_data->state = NULL;
				proc_data->user = NULL;
				proc_data->group = NULL;
			}

			proc_data->tname = NULL;

			if (SUCCEED == get_kinfo_proc(&proc_thread, NULL, proc[i].ZBX_P_PID, &count_thread, NULL) &&
					1 < count_thread)
			{
				proc_data->threads = count_thread - 1;
				zbx_free(proc_thread);
			}
			else
				proc_data->threads = -1;

			zbx_vector_proc_data_ptr_append(&proc_data_ctx, proc_data);
		}
	}

	zbx_free(proc);

	if (ZBX_PROC_MODE_SUMMARY == zbx_proc_mode)
	{
		for (i = 0; i < proc_data_ctx.values_num; i++)
		{
			proc_data_t	*pdata = proc_data_ctx.values[i];

			pdata->processes = 1;

			for (k = i + 1; k < proc_data_ctx.values_num; k++)
			{
				proc_data_t	*pdata_cmp = proc_data_ctx.values[k];

				if (0 == strcmp(pdata->name, pdata_cmp->name))
				{
					pdata->processes++;
					pdata->rss += pdata_cmp->rss;
					pdata->vsize += pdata_cmp->vsize;
					pdata->tsize += pdata_cmp->tsize;
					pdata->dsize += pdata_cmp->dsize;
					pdata->ssize += pdata_cmp->ssize;
					pdata->size += pdata_cmp->size;
					pdata->swap += pdata_cmp->swap;
					pdata->cputime_user += pdata_cmp->cputime_user;
					pdata->cputime_system += pdata_cmp->cputime_system;
					pdata->ctx_switches += pdata_cmp->ctx_switches;
					pdata->page_faults += pdata_cmp->page_faults;
					pdata->io_read_op += pdata_cmp->io_read_op;
					pdata->io_write_op += pdata_cmp->io_write_op;

					SUM_PROC_VALUE(threads);
					SUM_PROC_VALUE(fds);

					proc_data_free(pdata_cmp);
					zbx_vector_proc_data_ptr_remove(&proc_data_ctx, k--);
				}
			}
		}
	}

	zbx_json_initarray(&j, ZBX_JSON_STAT_BUF_LEN);

	for (i = 0; i < proc_data_ctx.values_num; i++)
	{
		proc_data_t	*pdata;

		pdata = proc_data_ctx.values[i];

		zbx_json_addobject(&j, NULL);

		if (ZBX_PROC_MODE_PROCESS == zbx_proc_mode)
		{
			zbx_json_addint64(&j, "pid", pdata->pid);
			zbx_json_addint64(&j, "ppid", pdata->ppid);
			zbx_json_addstring(&j, "name", ZBX_NULL2EMPTY_STR(pdata->name), ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&j, "cmdline", ZBX_NULL2EMPTY_STR(pdata->cmdline), ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&j, "user", ZBX_NULL2EMPTY_STR(pdata->user), ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&j, "group", ZBX_NULL2EMPTY_STR(pdata->group), ZBX_JSON_TYPE_STRING);
			zbx_json_adduint64(&j, "uid", pdata->uid);
			zbx_json_adduint64(&j, "gid", pdata->gid);
			zbx_json_adduint64(&j, "vsize", pdata->vsize);
			zbx_json_adduint64(&j, "rss", pdata->rss);
			zbx_json_adduint64(&j, "size", pdata->size);
			zbx_json_adduint64(&j, "tsize", pdata->tsize);
			zbx_json_adduint64(&j, "dsize", pdata->dsize);
			zbx_json_adduint64(&j, "ssize", pdata->ssize);
			zbx_json_adduint64(&j, "cputime_user", pdata->cputime_user);
			zbx_json_adduint64(&j, "cputime_system", pdata->cputime_system);
			zbx_json_addstring(&j, "state", ZBX_NULL2EMPTY_STR(pdata->state), ZBX_JSON_TYPE_STRING);
			zbx_json_adduint64(&j, "ctx_switches", pdata->ctx_switches);
			zbx_json_addint64(&j, "threads", pdata->threads);
			zbx_json_adduint64(&j, "page_faults", pdata->page_faults);
			zbx_json_addint64(&j, "fds", pdata->fds);
			zbx_json_adduint64(&j, "swap", pdata->swap);
			zbx_json_adduint64(&j, "io_read_op", pdata->io_read_op);
			zbx_json_adduint64(&j, "io_write_op", pdata->io_write_op);
		}
		else if (ZBX_PROC_MODE_THREAD == zbx_proc_mode)
		{
			zbx_json_addint64(&j, "pid", pdata->pid);
			zbx_json_addint64(&j, "ppid", pdata->ppid);
			zbx_json_addstring(&j, "name", ZBX_NULL2EMPTY_STR(pdata->name), ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&j, "user", ZBX_NULL2EMPTY_STR(pdata->user), ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&j, "group", ZBX_NULL2EMPTY_STR(pdata->group), ZBX_JSON_TYPE_STRING);
			zbx_json_adduint64(&j, "uid", pdata->uid);
			zbx_json_adduint64(&j, "gid", pdata->gid);
			zbx_json_addstring(&j, "tname", ZBX_NULL2EMPTY_STR(pdata->tname), ZBX_JSON_TYPE_STRING);
			zbx_json_addint64(&j, "tid", pdata->tid);
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
			zbx_json_adduint64(&j, "rss", pdata->rss);
			zbx_json_adduint64(&j, "size", pdata->size);
			zbx_json_adduint64(&j, "tsize", pdata->tsize);
			zbx_json_adduint64(&j, "dsize", pdata->dsize);
			zbx_json_adduint64(&j, "ssize", pdata->ssize);
			zbx_json_adduint64(&j, "cputime_user", pdata->cputime_user);
			zbx_json_adduint64(&j, "cputime_system", pdata->cputime_system);
			zbx_json_adduint64(&j, "ctx_switches", pdata->ctx_switches);
			zbx_json_addint64(&j, "threads", pdata->threads);
			zbx_json_adduint64(&j, "page_faults", pdata->page_faults);
			zbx_json_addint64(&j, "fds", pdata->fds);
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

	return SYSINFO_RET_OK;
#undef SUM_PROC_VALUE_DBL
}
