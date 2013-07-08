/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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

#if !defined(HAVE_SYS_PROCFS_H)
#	include "../common/common.h"
#endif

#define DO_SUM 0
#define DO_MAX 1
#define DO_MIN 2
#define DO_AVG 3

#ifdef HAVE_SYS_PROCFS_H
static int	check_procstate(psinfo_t *psinfo, int zbx_proc_stat)
{
	if (zbx_proc_stat == ZBX_PROC_STAT_ALL)
		return SUCCEED;

	switch (zbx_proc_stat)
	{
		case ZBX_PROC_STAT_RUN:
			return (psinfo->pr_lwp.pr_sname == PR_SNAME_TSRUN) ? SUCCEED : FAIL;
		case ZBX_PROC_STAT_SLEEP:
			return (psinfo->pr_lwp.pr_sname == PR_SNAME_TSSLEEP) ? SUCCEED : FAIL;
		case ZBX_PROC_STAT_ZOMB:
			return (psinfo->pr_lwp.pr_sname == PR_SNAME_TSZOMB) ? SUCCEED : FAIL;
	}

	return FAIL;
}
#else
static int	check_procstate(struct procsinfo *procsinfo, int zbx_proc_stat)
{
	if (zbx_proc_stat == ZBX_PROC_STAT_ALL)
		return SUCCEED;

	switch (zbx_proc_stat)
	{
		case ZBX_PROC_STAT_RUN:
			return (procsinfo->pi_state == SRUN) ? SUCCEED : FAIL;
		case ZBX_PROC_STAT_SLEEP:
			return (procsinfo->pi_state == SSLEEP) ? SUCCEED : FAIL;
		case ZBX_PROC_STAT_ZOMB:
			return (procsinfo->pi_state == SZOMB) ? SUCCEED : FAIL;
	}

	return FAIL;
}
#endif /* HAVE_SYS_PROCFS_H */

int	PROC_MEM(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char			tmp[MAX_STRING_LEN], *procname, *proccomm, *param;
#ifdef HAVE_SYS_PROCFS_H
	DIR			*dir;
	struct dirent		*entries;
	struct stat		buf;
	struct psinfo		psinfo;
	int			fd = -1;
#else
	struct procsinfo	procsinfo;
	pid_t			pid = 0;
	AGENT_RESULT		proc_args;
#endif /* HAVE_SYS_PROCFS_H */
	struct passwd		*usrinfo;
	zbx_uint64_t		value = 0;
	int			do_task;
	double			memsize = 0;
	zbx_uint64_t		proccount = 0;

	if (4 < request->nparam)
		return SYSINFO_RET_FAIL;

	procname = get_rparam(request, 0);
	param = get_rparam(request, 1);

	if (NULL != param && '\0' != *param)
	{
		if (NULL == (usrinfo = getpwnam(param)))	/* incorrect user name */
			return SYSINFO_RET_FAIL;
	}
	else
		usrinfo = NULL;

	param = get_rparam(request, 2);

	if (NULL == param || '\0' == *param || 0 == strcmp(param, "sum"))
		do_task = DO_SUM;
	else if (0 == strcmp(param, "avg"))
		do_task = DO_AVG;
	else if (0 == strcmp(param, "max"))
		do_task = DO_MAX;
	else if (0 == strcmp(param, "min"))
		do_task = DO_MIN;
	else
		return SYSINFO_RET_FAIL;

	proccomm = get_rparam(request, 3);

#ifdef HAVE_SYS_PROCFS_H /* AIX 5.x */
	if (NULL == (dir = opendir("/proc")))
		return SYSINFO_RET_FAIL;

	while (NULL != (entries = readdir(dir)))
	{
		if (-1 != fd)
		{
			close(fd);
			fd = -1;
		}

		zbx_snprintf(tmp, sizeof(tmp), "/proc/%s/psinfo", entries->d_name);

		if (0 != stat(tmp, &buf))
			continue;

		if (-1 == (fd = open(tmp, O_RDONLY)))
			continue;

		if (-1 == read(fd, &psinfo, sizeof(psinfo)))
			continue;

		if (NULL != procname && '\0' != *procname && 0 != strcmp(procname, psinfo.pr_fname))
			continue;

		if (NULL != usrinfo && usrinfo->pw_uid != psinfo.pr_uid)
			continue;

		if (NULL != proccomm && '\0' != *proccomm && NULL == zbx_regexp_match(psinfo.pr_psargs, proccomm, NULL))
			continue;

		value = psinfo.pr_size;
		value <<= 10;	/* kB to Byte */

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

	closedir(dir);
	if (-1 != fd)
		close(fd);
#else
	while (0 < getprocs(&procsinfo, (int)sizeof(struct procsinfo), NULL, 0, &pid, 1))
	{
		if (NULL != procname && '\0' != *procname && 0 != strcmp(procname, procsinfo.pi_comm))
			continue;

		if (NULL != usrinfo && usrinfo->pw_uid != procsinfo.pi_uid)
			continue;

		if (NULL != proccomm && '\0' != *proccomm)
		{
			init_result(&proc_args);

			zbx_snprintf(tmp, sizeof(tmp), "ps -p %i -oargs=", procsinfo.pi_pid);

			if (SYSINFO_RET_OK != EXECUTE_STR(tmp, &proc_args))
			{
				free_result(&proc_args);
				continue;
			}
			if (NULL == zbx_regexp_match(proc_args.str, proccomm, NULL))
			{
				free_result(&proc_args);
				continue;
			}

			free_result(&proc_args);
		}

		value = procsinfo.pi_size;
		value <<= 10;	/* kB to Byte */

		if (0 == proccount++)
			memsize = value;
		else
		{
			if (DO_MAX == do_task)
				memsize = MAX(memsize, value);
			else if (DO_MIN == do_task)
				memsize = MIN(memsize, value);
			else
				memsize += value;
		}
        }
#endif /* HAVE_SYS_PROCFS_H */

	if (do_task == DO_AVG)
	{
		SET_DBL_RESULT(result, proccount == 0 ? 0 : memsize/proccount);
	}
	else
		SET_UI64_RESULT(result, memsize);

	return SYSINFO_RET_OK;
}

int	PROC_NUM(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char			tmp[MAX_STRING_LEN], *procname, *proccomm, *param;
#ifdef HAVE_SYS_PROCFS_H
	DIR			*dir;
	struct dirent		*entries;
	struct stat		buf;
	struct psinfo		psinfo;
	int			fd = -1;
#else
	struct procsinfo	procsinfo;
	pid_t			pid = 0;
	AGENT_RESULT		proc_args;
#endif /* HAVE_SYS_PROCFS_H */
	struct passwd		*usrinfo;
	int			zbx_proc_stat;
	zbx_uint64_t		proccount = 0;

	if (4 < request->nparam)
		return SYSINFO_RET_FAIL;

	procname = get_rparam(request, 0);
	param = get_rparam(request, 1);

	if (NULL != param && '\0' != *param)
	{
		if (NULL == (usrinfo = getpwnam(param)))	/* incorrect user name */
			return SYSINFO_RET_FAIL;
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
		return SYSINFO_RET_FAIL;

	proccomm = get_rparam(request, 3);

#ifdef HAVE_SYS_PROCFS_H /* AIX 5.x */
	if (NULL == (dir = opendir("/proc")))
		return SYSINFO_RET_FAIL;

	while (NULL != (entries = readdir(dir)))
	{
		if (-1 != fd)
		{
			close(fd);
			fd = -1;
		}

		zbx_snprintf(tmp, sizeof(tmp), "/proc/%s/psinfo", entries->d_name);

		if (0 != stat(tmp, &buf))
			continue;

		if (-1 == (fd = open(tmp, O_RDONLY)))
			continue;

		if (-1 == read(fd, &psinfo, sizeof(psinfo)))
			continue;

		if (NULL != procname && '\0' != *procname && 0 != strcmp(procname, psinfo.pr_fname))
			continue;

		if (NULL != usrinfo && usrinfo->pw_uid != psinfo.pr_uid)
			continue;

		if (FAIL == check_procstate(&psinfo, zbx_proc_stat))
			continue;

		if (NULL != proccomm && '\0' != *proccomm && NULL == zbx_regexp_match(psinfo.pr_psargs, proccomm, NULL))
			continue;

		proccount++;
	}

	closedir(dir);
	if (-1 != fd)
		close(fd);
#else
	while (0 < getprocs(&procsinfo, (int)sizeof(struct procsinfo), NULL, 0, &pid, 1))
	{
		if (NULL != procname && '\0' != *procname && 0 != strcmp(procname, procsinfo.pi_comm))
			continue;

		if (NULL != usrinfo && usrinfo->pw_uid != procsinfo.pi_uid)
			continue;

		if (FAIL == check_procstate(&procsinfo, zbx_proc_stat))
			continue;

		if (NULL != proccomm && '\0' != *proccomm)
		{
			init_result(&proc_args);

			zbx_snprintf(tmp, sizeof(tmp), "ps -p %i -oargs=", procsinfo.pi_pid);

			if (SYSINFO_RET_OK != EXECUTE_STR(tmp, &proc_args))
			{
				free_result(&proc_args);
				continue;
			}
			if (NULL == zbx_regexp_match(proc_args.str, proccomm, NULL))
			{
				free_result(&proc_args);
				continue;
			}

			free_result(&proc_args);
		}

		proccount++;
        }
#endif /* HAVE_SYS_PROCFS_H */

	SET_UI64_RESULT(result, proccount);

	return SYSINFO_RET_OK;
}
