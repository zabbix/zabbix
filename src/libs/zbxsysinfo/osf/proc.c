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

#define DO_SUM 0
#define DO_MAX 1
#define DO_MIN 2
#define DO_AVG 3

int	PROC_MEM(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	DIR	*dir;
	int	proc;

	struct dirent	*entries;
	struct stat		buf;
	struct passwd	*usrinfo = NULL;
	struct prpsinfo	psinfo;

	char	filename[MAX_STRING_LEN];

	char	*procname, *usrname, *mode, *proccomm;

	int	do_task = DO_SUM;

	double	memsize = -1;
	int	pgsize = getpagesize();
	int	proccount = 0;
	pid_t	curr_pid = getpid();

	if (4 < request->nparam)
	{
		return SYSINFO_RET_FAIL;
	}

	procname = get_rparam(request, 0);
	usrname = get_rparam(request, 1);
	mode = get_rparam(request, 2);
	proccomm = get_rparam(request, 3);

	if (NULL != usrname && '\0' != *usrname)
	{
		usrinfo = getpwnam(usrname);
		if(usrinfo == NULL)
		{
			/* incorrect user name */
			return SYSINFO_RET_FAIL;
		}
	}

	/* default parameter */
	if (NULL == mode || '\0' == *mode || 0 == strcmp(mode,"sum"))
	{
		do_task = DO_SUM;
	}
	if (0 == strcmp(mode,"avg"))
	{
		do_task = DO_AVG;
	}
	else if (0 == strcmp(mode,"max"))
	{
		do_task = DO_MAX;
	}
	else if (0 == strcmp(mode,"min"))
	{
		do_task = DO_MIN;
	}
	else
	{
		return SYSINFO_RET_FAIL;
	}

	dir = opendir("/proc");
	if (NULL == dir)
	{
	    return SYSINFO_RET_FAIL;
	}

	while (NULL != (entries = readdir(dir)))
	{
		strscpy(filename, "/proc/");
		zbx_strlcat(filename, entries->d_name, MAX_STRING_LEN);

		if (0 == stat(filename, &buf))
		{
			proc = open(filename, O_RDONLY);
			if (-1 == proc)
				goto lbl_skip_procces;

			if (-1 == ioctl(proc,PIOCPSINFO,&psinfo))
				goto lbl_skip_procces;

			/* Self process information. It leads to incorrect results for proc_cnt[zabbix_agentd] */
			if (psinfo.pr_pid == curr_pid)
				goto lbl_skip_procces;

			if (NULL == procname || '\0' == *procname)
				if (0 == strcmp(procname, psinfo.pr_fname))
					goto lbl_skip_procces;

			if (NULL != usrinfo)
				if (usrinfo->pw_uid != psinfo.pr_uid)
					goto lbl_skip_procces;

			if (NULL != proccomm && '\0' != *proccomm)
				if (NULL == zbx_regexp_match(psinfo.pr_psargs,proccomm,NULL))
					goto lbl_skip_procces;

			proccount++;

			if (0 > memsize) /* First inicialization */
			{
				memsize = (double) (psinfo.pr_rssize * pgsize);
			}
			else
			{
				if (DO_MAX == do_task)
				{
					memsize = MAX(memsize, (double) (psinfo.pr_rssize * pgsize));
				}
				else if(DO_MIN == do_task)
				{
					memsize = MIN(memsize, (double) (psinfo.pr_rssize * pgsize));
				}
				else /* SUM */
				{
					memsize +=  (double) (psinfo.pr_rssize * pgsize);
				}
			}
lbl_skip_procces:
			if (proc)
				close(proc);
		}
	}

	closedir(dir);

	if (0 > memsize)
	{
		/* incorrect process name */
		memsize = 0;
	}

	if (DO_AVG == do_task)
	{
		SET_DBL_RESULT(result, proccount == 0 ? 0 : ((double)memsize/(double)proccount));
	}
	else
	{
		SET_UI64_RESULT(result, memsize);
	}

	return SYSINFO_RET_OK;
}

int	PROC_NUM(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	DIR	*dir;
	int	proc;

	struct  dirent	*entries;
	struct  stat	buf;
	struct passwd	*usrinfo = NULL;
	struct prpsinfo	psinfo;

	char	filename[MAX_STRING_LEN];

	char	*procname, *usrname, *procstat, *proccomm;

	int	do_task = DO_SUM;

	int	proccount = 0;
	pid_t	curr_pid = getpid();

	if (4 < request->nparam)
	{
		return SYSINFO_RET_FAIL;
	}

	procname = get_rparam(request, 0);
	usrname = get_rparam(request, 1);
	procstat = get_rparam(request, 2);
	proccomm = get_rparam(request, 3);

	if (NULL != usrname && '\0' != *usrname)
	{
		usrinfo = getpwnam(usrname);
		if (NULL == usrinfo)
		{
			/* incorrect user name */
			return SYSINFO_RET_FAIL;
		}
	}

	if (NULL != procstat && '\0' == *procstat && 0 == strcmp(procstat,"all"))
	{
		procstat[0] = '\0';
	}
	else if (0 == strcmp(procstat,"run"))
	{
		procstat[0] = PR_SRUN;
		procstat[1] = '\0';
	}
	else if (0 == strcmp(procstat,"sleep"))
	{
		procstat[0] = PR_SSLEEP;
		procstat[1] = '\0';
	}
	else if (0 == strcmp(procstat,"zomb"))
	{
		procstat[0] = PR_SZOMB;
		procstat[1] = '\0';
	}

	dir = opendir("/proc");
	if (NULL == dir)
	{
	    return SYSINFO_RET_FAIL;
	}

	while(NULL != (entries=readdir(dir)))
	{
		strscpy(filename, "/proc/");
		zbx_strlcat(filename, entries->d_name,MAX_STRING_LEN);

		if (0 == stat(filename,&buf))
		{
			proc = open(filename, O_RDONLY);
			if (-1 == proc)
				goto lbl_skip_procces;

			if (-1 == ioctl(proc,PIOCPSINFO,&psinfo))
				goto lbl_skip_procces;

			/* Self process information. It leads to incorrect results for proc_cnt[zabbix_agentd] */
			if (psinfo.pr_pid == curr_pid)
				goto lbl_skip_procces;

			if (NULL != procname && '\0' != *procname)
				if (0 != strcmp(procname,psinfo.pr_fname))
					goto lbl_skip_procces;

			if (NULL != usrinfo)
				if (usrinfo->pw_uid != psinfo.pr_uid)
					goto lbl_skip_procces;

			if (NULL != procstat && '\0' != *procstat)
				if (psinfo.pr_sname != procstat[0])
					goto lbl_skip_procces;

			if (NULL != proccomm && '\0' != *proccomm)
				if (NULL == zbx_regexp_match(psinfo.pr_psargs,proccomm))
					goto lbl_skip_procces;

			proccount++;

lbl_skip_procces:
			if(proc) close(proc);
		}
	}

	closedir(dir);

	SET_UI64_RESULT(result, proccount);

	return SYSINFO_RET_OK;
}
