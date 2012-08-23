/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
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

int	PROC_MEM(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	char			tmp[MAX_STRING_LEN],
				procname[MAX_STRING_LEN],
				proccomm[MAX_STRING_LEN];
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
	struct passwd		*usrinfo = NULL;
	zbx_uint64_t		value = 0;
	int			do_task;
	double			memsize = 0;
	zbx_uint64_t		proccount = 0;

	if (num_param(param) > 4)
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, procname, sizeof(procname)))
		*procname = '\0';

	if (0 != get_param(param, 2, tmp, sizeof(tmp)))
		*tmp = '\0';

	if (*tmp != '\0')
	{
		usrinfo = getpwnam(tmp);
		if (usrinfo == NULL)	/* incorrect user name */
			return SYSINFO_RET_FAIL;
	}
	else
		usrinfo = NULL;

	if (0 != get_param(param, 3, tmp, sizeof(tmp)))
		*tmp = '\0';

	if (*tmp != '\0')
	{
		if (0 == strcmp(tmp, "avg"))
			do_task = DO_AVG;
		else if (0 == strcmp(tmp, "max"))
			do_task = DO_MAX;
		else if (0 == strcmp(tmp, "min"))
			do_task = DO_MIN;
		else if (0 == strcmp(tmp, "sum"))
			do_task = DO_SUM;
		else
			return SYSINFO_RET_FAIL;
	}
	else
		do_task = DO_SUM;

	if (0 != get_param(param, 4, proccomm, sizeof(proccomm)))
		*proccomm = '\0';

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

		if ('\0' != *procname && 0 != strcmp(procname, psinfo.pr_fname))
			continue;

		if (NULL != usrinfo && usrinfo->pw_uid != psinfo.pr_uid)
			continue;

		if ('\0' != *proccomm && NULL == zbx_regexp_match(psinfo.pr_psargs, proccomm, NULL))
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
		if ('\0' != *procname && 0 != strcmp(procname, procsinfo.pi_comm))
			continue;

		if (NULL != usrinfo && usrinfo->pw_uid != procsinfo.pi_uid)
			continue;

		if ('\0' != *proccomm)
		{
			init_result(&proc_args);

			zbx_snprintf(tmp, sizeof(tmp), "ps -p %i -oargs=", procsinfo.pi_pid);

			if (SYSINFO_RET_OK != EXECUTE_STR(cmd, tmp, flags, &proc_args))
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
			if(do_task == DO_MAX)
				memsize = MAX(memsize, value);
			else if(do_task == DO_MIN)
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

int	PROC_NUM(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	char			tmp[MAX_STRING_LEN],
				procname[MAX_STRING_LEN],
				proccomm[MAX_STRING_LEN];
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
	struct passwd		*usrinfo = NULL;
	zbx_uint64_t		value = 0;
	int			zbx_proc_stat;
	zbx_uint64_t		proccount = 0;

	if (num_param(param) > 4)
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, procname, sizeof(procname)))
		*procname = '\0';

	if (0 != get_param(param, 2, tmp, sizeof(tmp)))
		*tmp = '\0';

	if (*tmp != '\0')
	{
		usrinfo = getpwnam(tmp);
		if (usrinfo == NULL)	/* incorrect user name */
			return SYSINFO_RET_FAIL;
	}
	else
		usrinfo = NULL;

	if (0 != get_param(param, 3, tmp, sizeof(tmp)))
		*tmp = '\0';

	if (*tmp != '\0')
	{
		if (0 == strcmp(tmp, "run"))
			zbx_proc_stat = ZBX_PROC_STAT_RUN;
		else if (0 == strcmp(tmp, "sleep"))
			zbx_proc_stat = ZBX_PROC_STAT_SLEEP;
		else if (0 == strcmp(tmp, "zomb"))
			zbx_proc_stat = ZBX_PROC_STAT_ZOMB;
		else if (0 == strcmp(tmp, "all"))
			zbx_proc_stat = ZBX_PROC_STAT_ALL;
		else
			return SYSINFO_RET_FAIL;
	}
	else
		zbx_proc_stat = ZBX_PROC_STAT_ALL;

	if (0 != get_param(param, 4, proccomm, sizeof(proccomm)))
		*proccomm = '\0';

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

		if ('\0' != *procname && 0 != strcmp(procname, psinfo.pr_fname))
			continue;

		if (NULL != usrinfo && usrinfo->pw_uid != psinfo.pr_uid)
			continue;

		if (FAIL == check_procstate(&psinfo, zbx_proc_stat))
			continue;

		if ('\0' != *proccomm && NULL == zbx_regexp_match(psinfo.pr_psargs, proccomm, NULL))
			continue;

		proccount++;
	}

	closedir(dir);
	if (-1 != fd)
		close(fd);
#else
	while (0 < getprocs(&procsinfo, (int)sizeof(struct procsinfo), NULL, 0, &pid, 1))
	{
		if ('\0' != *procname && 0 != strcmp(procname, procsinfo.pi_comm))
			continue;

		if (NULL != usrinfo && usrinfo->pw_uid != procsinfo.pi_uid)
			continue;

		if (FAIL == check_procstate(&procsinfo, zbx_proc_stat))
			continue;

		if ('\0' != *proccomm)
		{
			init_result(&proc_args);

			zbx_snprintf(tmp, sizeof(tmp), "ps -p %i -oargs=", procsinfo.pi_pid);

			if (SYSINFO_RET_OK != EXECUTE_STR(cmd, tmp, flags, &proc_args))
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
