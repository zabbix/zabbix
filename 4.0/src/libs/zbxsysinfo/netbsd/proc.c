/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

static kvm_t	*kd = NULL;

static char	*proc_argv(pid_t pid)
{
	size_t		sz = 0;
	int		mib[4], ret;
	int		i, len;
	static char	*argv = NULL;
	static size_t	argv_alloc = 0;

	mib[0] = CTL_KERN;
	mib[1] = KERN_PROC_ARGS;
	mib[2] = (int)pid;
	mib[3] = KERN_PROC_ARGV;

	if (0 != sysctl(mib, 4, NULL, &sz, NULL, 0))
		return NULL;

	if (argv_alloc < sz)
	{
		argv_alloc = sz;
		if (NULL == argv)
			argv = zbx_malloc(argv, argv_alloc);
		else
			argv = zbx_realloc(argv, argv_alloc);
	}

	sz = argv_alloc;
	if (0 != sysctl(mib, 4, argv, &sz, NULL, 0))
		return NULL;

	for (i = 0; i < (int)(sz - 1); i++ )
		if (argv[i] == '\0')
			argv[i] = ' ';

	return argv;
}

int     PROC_MEM(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char			*procname, *proccomm, *param, *args;
	int			do_task, pagesize, count, i, proccount = 0, invalid_user = 0, proc_ok, comm_ok, op, arg;
	double			value = 0.0, memsize = 0;
	size_t			sz;
	struct kinfo_proc2	*proc, *pproc;
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

	if (NULL == kd && NULL == (kd = kvm_open(NULL, NULL, NULL, KVM_NO_FILES, NULL)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain a descriptor to access kernel virtual memory."));
		return SYSINFO_RET_FAIL;
	}

	if (NULL != usrinfo)
	{
		op = KERN_PROC_UID;
		arg = (int)usrinfo->pw_uid;
	}
	else
	{
		op = KERN_PROC_ALL;
		arg = 0;
	}

	if (NULL == (proc = kvm_getproc2(kd, op, arg, sizeof(struct kinfo_proc2), &count)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain process information."));
		return SYSINFO_RET_FAIL;
	}

	for (pproc = proc, i = 0; i < count; pproc++, i++)
	{
		proc_ok = 0;
		comm_ok = 0;

		if (NULL == procname || '\0' == *procname || 0 == strcmp(procname, pproc->p_comm))
			proc_ok = 1;

		if (NULL != proccomm && '\0' != *proccomm)
		{
			if (NULL != (args = proc_argv(pproc->p_pid)))
			{
				if (NULL != zbx_regexp_match(args, proccomm, NULL))
					comm_ok = 1;
			}
		}
		else
			comm_ok = 1;

		if (proc_ok && comm_ok)
		{
			value = pproc->p_vm_tsize + pproc->p_vm_dsize + pproc->p_vm_ssize;
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
out:
	if (ZBX_DO_AVG == do_task)
		SET_DBL_RESULT(result, 0 == proccount ? 0 : memsize / proccount);
	else
		SET_UI64_RESULT(result, memsize);

	return SYSINFO_RET_OK;
}

int	PROC_NUM(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char			*procname, *proccomm, *param, *args;
	int			proccount = 0, invalid_user = 0, zbx_proc_stat;
	int			count, i, proc_ok, stat_ok, comm_ok, op, arg;
	size_t			sz;
	struct kinfo_proc2	*proc, *pproc;
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

	if (NULL == kd && NULL == (kd = kvm_open(NULL, NULL, NULL, KVM_NO_FILES, NULL)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain a descriptor to access kernel virtual memory."));
		return SYSINFO_RET_FAIL;
	}

	if (NULL != usrinfo)
	{
		op = KERN_PROC_UID;
		arg = (int)usrinfo->pw_uid;
	}
	else
	{
		op = KERN_PROC_ALL;
		arg = 0;
	}

	if (NULL == (proc = kvm_getproc2(kd, op, arg, sizeof(struct kinfo_proc2), &count)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain process information."));
		return SYSINFO_RET_FAIL;
	}

	for (pproc = proc, i = 0; i < count; pproc++, i++)
	{
		proc_ok = 0;
		stat_ok = 0;
		comm_ok = 0;

		if (NULL == procname || '\0' == *procname || 0 == strcmp(procname, pproc->p_comm))
			proc_ok = 1;

		if (ZBX_PROC_STAT_ALL != zbx_proc_stat)
		{
			switch (zbx_proc_stat)
			{
				case ZBX_PROC_STAT_RUN:
					if (LSRUN == pproc->p_stat || LSONPROC == pproc->p_stat)
						stat_ok = 1;
					break;
				case ZBX_PROC_STAT_SLEEP:
					if (LSSLEEP == pproc->p_stat && 0 != (pproc->p_flag & L_SINTR))
						stat_ok = 1;
					break;
				case ZBX_PROC_STAT_ZOMB:
					if (SZOMB == pproc->p_stat || LSDEAD == pproc->p_stat)
						stat_ok = 1;
					break;
				case ZBX_PROC_STAT_DISK:
					if (LSSLEEP == pproc->p_stat && 0 == (pproc->p_flag & L_SINTR))
						stat_ok = 1;
					break;
				case ZBX_PROC_STAT_TRACE:
					if (LSSTOP == pproc->p_stat)
						stat_ok = 1;
					break;
			}
		}
		else
			stat_ok = 1;

		if (NULL != proccomm && '\0' != *proccomm)
		{
			if (NULL != (args = proc_argv(pproc->p_pid)))
			{
				if (NULL != zbx_regexp_match(args, proccomm, NULL))
					comm_ok = 1;
			}
		}
		else
			comm_ok = 1;

		if (proc_ok && stat_ok && comm_ok)
			proccount++;
	}
out:
	SET_UI64_RESULT(result, proccount);

	return SYSINFO_RET_OK;
}
