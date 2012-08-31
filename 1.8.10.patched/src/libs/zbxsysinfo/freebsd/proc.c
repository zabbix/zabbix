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
#include "sysinfo.h"
#include "log.h"

#define DO_SUM 0
#define DO_MAX 1
#define DO_MIN 2
#define DO_AVG 3

#define ZBX_PROC_STAT_ALL 0
#define ZBX_PROC_STAT_RUN 1
#define ZBX_PROC_STAT_SLEEP 2
#define ZBX_PROC_STAT_ZOMB 3

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
		if (errno == ENOMEM) {
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

int     PROC_MEM(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	char	procname[MAX_STRING_LEN],
		buffer[MAX_STRING_LEN],
		proccomm[MAX_STRING_LEN], *args;
	int	do_task, pagesize, count, i,
		proc_ok, comm_ok,
		mib[4], mibs;

	double	value = 0.0,
		memsize = 0;
	int	proccount = 0;

	size_t	sz;

	struct kinfo_proc	*proc = NULL;
	struct passwd		*usrinfo;

	if (num_param(param) > 4)
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, procname, sizeof(procname)))
		*procname = '\0';
	else if (strlen(procname) > ZBX_COMMLEN)
		procname[ZBX_COMMLEN] = '\0';

	if (0 != get_param(param, 2, buffer, sizeof(buffer)))
		*buffer = '\0';

	if (*buffer != '\0')
	{
		usrinfo = getpwnam(buffer);
		if (usrinfo == NULL)	/* incorrect user name */
			return SYSINFO_RET_FAIL;
	}
	else
		usrinfo = NULL;

	if (0 != get_param(param, 3, buffer, sizeof(buffer)))
		*buffer = '\0';

	if (*buffer != '\0')
	{
		if (0 == strcmp(buffer, "avg"))
			do_task = DO_AVG;
		else if (0 == strcmp(buffer, "max"))
			do_task = DO_MAX;
		else if (0 == strcmp(buffer, "min"))
			do_task = DO_MIN;
		else if (0 == strcmp(buffer, "sum"))
			do_task = DO_SUM;
		else
			return SYSINFO_RET_FAIL;
	}
	else
		do_task = DO_SUM;

	if (0 != get_param(param, 4, proccomm, sizeof(proccomm)))
		*proccomm = '\0';

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
		mib[2] = KERN_PROC_ALL;
		mib[3] = 0;
		mibs = 3;
	}

	sz = 0;
	if (0 != sysctl(mib, mibs, NULL, &sz, NULL, 0))
		return SYSINFO_RET_FAIL;

	proc = (struct kinfo_proc *)zbx_malloc(proc, sz);
	if (0 != sysctl(mib, mibs, proc, &sz, NULL, 0))
	{
		zbx_free(proc);
		return SYSINFO_RET_FAIL;
	}

	count = sz / sizeof(struct kinfo_proc);

	for (i = 0; i < count; i++)
	{
#if(__FreeBSD_version > 500000)
		if (proc[i].ki_flag & P_KTHREAD)	/* skip a system thread */
			continue;
#endif

		proc_ok = 0;
		comm_ok = 0;
		if (*procname == '\0' || 0 == strcmp(procname, proc[i].ZBX_PROC_COMM))
			proc_ok = 1;

		if (*proccomm != '\0')
		{
			if (NULL != (args = get_commandline(&proc[i])))
				if (zbx_regexp_match(args, proccomm, NULL) != NULL)
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
				if (do_task == DO_MAX)
					memsize = MAX(memsize, value);
				else if (do_task == DO_MIN)
					memsize = MIN(memsize, value);
				else
					memsize += value;
			}
		}
	}
	zbx_free(proc);

	if (do_task == DO_AVG)
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

int	PROC_NUM(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	char	procname[MAX_STRING_LEN],
		buffer[MAX_STRING_LEN],
		proccomm[MAX_STRING_LEN], *args;
	int	zbx_proc_stat, count, i,
		proc_ok, stat_ok, comm_ok,
		mib[4], mibs;

	int	proccount = 0;

	size_t	sz;

	struct kinfo_proc	*proc = NULL;
	struct passwd		*usrinfo;

	if (num_param(param) > 4)
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, procname, sizeof(procname)))
		*procname = '\0';
	else if (strlen(procname) > ZBX_COMMLEN)
		procname[ZBX_COMMLEN] = '\0';

	if (0 != get_param(param, 2, buffer, sizeof(buffer)))
		*buffer = '\0';

	if (*buffer != '\0')
	{
		usrinfo = getpwnam(buffer);
		if (usrinfo == NULL)	/* incorrect user name */
			return SYSINFO_RET_FAIL;
	}
	else
		usrinfo = NULL;

	if (0 != get_param(param, 3, buffer, sizeof(buffer)))
		*buffer = '\0';

	if (*buffer != '\0')
	{
		if (0 == strcmp(buffer, "run"))
			zbx_proc_stat = ZBX_PROC_STAT_RUN;
		else if (0 == strcmp(buffer, "sleep"))
			zbx_proc_stat = ZBX_PROC_STAT_SLEEP;
		else if (0 == strcmp(buffer, "zomb"))
			zbx_proc_stat = ZBX_PROC_STAT_ZOMB;
		else if (0 == strcmp(buffer, "all"))
			zbx_proc_stat = ZBX_PROC_STAT_ALL;
		else
			return SYSINFO_RET_FAIL;
	}
	else
		zbx_proc_stat = ZBX_PROC_STAT_ALL;

	if (0 != get_param(param, 4, proccomm, sizeof(proccomm)))
		*proccomm = '\0';

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
		mib[2] = KERN_PROC_ALL;
		mib[3] = 0;
		mibs = 3;
	}

	sz = 0;
	if (0 != sysctl(mib, mibs, NULL, &sz, NULL, 0))
		return SYSINFO_RET_FAIL;

	proc = (struct kinfo_proc *)zbx_malloc(proc, sz);
	if (0 != sysctl(mib, mibs, proc, &sz, NULL, 0))
	{
		zbx_free(proc);
		return SYSINFO_RET_FAIL;
	}

	count = sz / sizeof(struct kinfo_proc);

	for (i = 0; i < count; i++)
	{
#if(__FreeBSD_version > 500000)
		if (proc[i].ki_flag & P_KTHREAD)	/* skip a system thread */
			continue;
#endif

		proc_ok = 0;
		stat_ok = 0;
		comm_ok = 0;

		if (*procname == '\0' || 0 == strcmp(procname, proc[i].ZBX_PROC_COMM))
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

		if (*proccomm != '\0')
		{
			if (NULL != (args = get_commandline(&proc[i])))
				if (zbx_regexp_match(args, proccomm, NULL) != NULL)
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
