/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

#if(__FreeBSD_version > 500000)
#	define ZBX_COMMLEN	COMMLEN
#	define ZBX_PROC_PID	ki_pid
#	define ZBX_PROC_COMM	ki_comm
#	define ZBX_PROC_STAT	ki_stat
#	define ZBX_PROC_TSIZE	ki_tsize
#	define ZBX_PROC_DSIZE	ki_dsize
#	define ZBX_PROC_SSIZE	ki_ssize
#else
#	define ZBX_COMMLEN	MAXCOMLEN
#	define ZBX_PROC_PID	kp_proc.p_pid
#	define ZBX_PROC_COMM	kp_proc.p_comm
#	define ZBX_PROC_STAT	kp_proc.p_stat
#	define ZBX_PROC_TSIZE	kp_eproc.e_vm.vm_tsize
#	define ZBX_PROC_DSIZE	kp_eproc.e_vm.vm_dsize
#	define ZBX_PROC_SSIZE	kp_eproc.e_vm.vm_ssize
#endif

static char	*get_commandline(struct kinfo_proc *proc)
{
	int		mib[4], i;
	size_t		sz;
	static char	*args = NULL;
	static int	args_alloc = 128;

	if (NULL == args)
		args = zbx_malloc(args, args_alloc);

	mib[0] = CTL_KERN;
	mib[1] = KERN_PROC;
	mib[2] = KERN_PROC_ARGS;
	mib[3] = proc->ZBX_PROC_PID;
retry:
	sz = (size_t)args_alloc;
	if (-1 == sysctl(mib, 4, args, &sz, NULL, 0))
	{
		if (errno == ENOMEM)
		{
			args_alloc *= 2;
			args = zbx_realloc(args, args_alloc);
			goto retry;
		}
		return NULL;
	}

	for (i = 0; i < (int)(sz - 1); i++)
		if (args[i] == '\0')
			args[i] = ' ';

	if (sz == 0)
		zbx_strlcpy(args, proc->ZBX_PROC_COMM, args_alloc);

	return args;
}

/*
 *	proc.mem[<process_name><,user_name><,mode><,command_line>]
 *		<mode> : *sum, avg, max, min
 *
 *	Tested: FreeBSD 6.2_i386, 7.0_i386;
 */

int     PROC_MEM(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*procname, *proccomm, *param, *args;
	int	do_task, pagesize, count, i,
		proc_ok, comm_ok,
		mib[4], mibs;

	double	value, memsize = 0;
	int	proccount = 0;

	size_t	sz;

	struct kinfo_proc	*proc = NULL;
	struct passwd		*usrinfo;

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
			if (0 == errno)
				SET_MSG_RESULT(result, zbx_strdup(NULL, "Specified user does not exist."));
			else
				SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain user information: %s",
						zbx_strerror(errno)));

			return SYSINFO_RET_FAIL;
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
#if(__FreeBSD_version > 500000)
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

	for (i = 0; i < count; i++)
	{
		proc_ok = 0;
		comm_ok = 0;
		if (NULL == procname || '\0' == *procname || 0 == strcmp(procname, proc[i].ZBX_PROC_COMM))
			proc_ok = 1;

		if (NULL != proccomm && '\0' != *proccomm)
		{
			if (NULL != (args = get_commandline(&proc[i])))
				if (NULL != zbx_regexp_match(args, proccomm, NULL))
					comm_ok = 1;
		}
		else
			comm_ok = 1;

		if (proc_ok && comm_ok)
		{
			value = proc[i].ZBX_PROC_TSIZE + proc[i].ZBX_PROC_DSIZE + proc[i].ZBX_PROC_SSIZE;
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

	if (ZBX_DO_AVG == do_task)
		SET_DBL_RESULT(result, proccount == 0 ? 0 : memsize/proccount);
	else
		SET_UI64_RESULT(result, memsize);

	return SYSINFO_RET_OK;
}

/*
 *	proc.num[<process_name><,user_name><,state><,command_line>]
 *		<state> : *all, sleep, zomb, run
 *
 *	Tested: FreeBSD 6.2_i386, 7.0_i386;
 */

int	PROC_NUM(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*procname, *proccomm, *param, *args;
	int	zbx_proc_stat, count, i,
		proc_ok, stat_ok, comm_ok,
		mib[4], mibs;

	int	proccount = 0;

	size_t	sz;

	struct kinfo_proc	*proc = NULL;
	struct passwd		*usrinfo;

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
			if (0 == errno)
				SET_MSG_RESULT(result, zbx_strdup(NULL, "Specified user does not exist."));
			else
				SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain user information: %s",
						zbx_strerror(errno)));

			return SYSINFO_RET_FAIL;
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
#if(__FreeBSD_version > 500000)
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

	for (i = 0; i < count; i++)
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
				if (proc[i].ZBX_PROC_STAT == SRUN)
					stat_ok = 1;
				break;
			case ZBX_PROC_STAT_SLEEP:
				if (proc[i].ZBX_PROC_STAT == SSLEEP)
					stat_ok = 1;
				break;
			case ZBX_PROC_STAT_ZOMB:
				if (proc[i].ZBX_PROC_STAT == SZOMB)
					stat_ok = 1;
				break;
			}
		}
		else
			stat_ok = 1;

		if (NULL != proccomm && '\0' != *proccomm)
		{
			if (NULL != (args = get_commandline(&proc[i])))
				if (NULL != zbx_regexp_match(args, proccomm, NULL))
					comm_ok = 1;
		}
		else
			comm_ok = 1;

		if (proc_ok && stat_ok && comm_ok)
			proccount++;
	}
	zbx_free(proc);

	SET_UI64_RESULT(result, proccount);

	return SYSINFO_RET_OK;
}
