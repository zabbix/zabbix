/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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
#	define ZBX_P_COMM	p_comm
#	define ZBX_P_PID	p_pid
#	define ZBX_P_STAT	p_stat
#	define ZBX_P_VM_TSIZE	p_vm_tsize
#	define ZBX_P_VM_DSIZE	p_vm_dsize
#	define ZBX_P_VM_SSIZE	p_vm_ssize
#else
#	define ZBX_P_COMM	kp_proc.p_comm
#	define ZBX_P_PID	kp_proc.p_pid
#	define ZBX_P_STAT	kp_proc.p_stat
#	define ZBX_P_VM_TSIZE	kp_eproc.e_vm.vm_tsize
#	define ZBX_P_VM_DSIZE	kp_eproc.e_vm.vm_dsize
#	define ZBX_P_VM_SSIZE	kp_eproc.e_vm.vm_ssize
#endif

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
					if (SSLEEP == proc[i].ZBX_P_STAT)
						stat_ok = 1;
					break;
				case ZBX_PROC_STAT_ZOMB:
					if (SZOMB == proc[i].ZBX_P_STAT || SDEAD == proc[i].ZBX_P_STAT)
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
