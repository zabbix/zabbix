/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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

#define DO_SUM 0
#define DO_MAX 1
#define DO_MIN 2
#define DO_AVG 3

int	PROC_MEM(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	DIR	*dir;
	int	proc;

	struct dirent	*entries;
	zbx_stat_t	buf;
	struct passwd	*usrinfo = NULL;
	struct prpsinfo	psinfo;

	char	filename[MAX_STRING_LEN];

	char	procname[MAX_STRING_LEN];
	char	usrname[MAX_STRING_LEN];
	char	mode[MAX_STRING_LEN];
	char	proccomm[MAX_STRING_LEN];

	int	do_task = DO_SUM;

	double	memsize = -1;
	int	pgsize = getpagesize();
	int	proccount = 0;
	pid_t	curr_pid = getpid();

	if(num_param(param) > 4)
	{
		return SYSINFO_RET_FAIL;
	}

	if(get_param(param, 1, procname, MAX_STRING_LEN) != 0)
	{
		return SYSINFO_RET_FAIL;
	}

	if(get_param(param, 2, usrname, MAX_STRING_LEN) != 0)
	{
		usrname[0] = 0;
	}
	else
        {
		if(usrname[0] != 0)
		{
			usrinfo = getpwnam(usrname);
			if(usrinfo == NULL)
			{ /* incorrect user name */
				return SYSINFO_RET_FAIL;
			}
		}
	}

	if(get_param(param, 3, mode, MAX_STRING_LEN) != 0)
	{
		mode[0] = '\0';
	}

	if(mode[0] == '\0')
	{
		strscpy(mode, "sum");
	}

	if(strcmp(mode,"avg") == 0)
	{
		do_task = DO_AVG;
	}
	else if(strcmp(mode,"max") == 0)
	{
		do_task = DO_MAX;
	}
	else if(strcmp(mode,"min") == 0)
	{
		do_task = DO_MIN;
	}
	else if(strcmp(mode,"sum") == 0)
	{
		do_task = DO_SUM;
	}
	else
	{
		return SYSINFO_RET_FAIL;
	}

	if(get_param(param, 4, proccomm, MAX_STRING_LEN) != 0)
	{
		proccomm[0] = '\0';
	}

	dir=opendir("/proc");
	if(NULL == dir)
	{
	    return SYSINFO_RET_FAIL;
	}

	while((entries=readdir(dir))!=NULL)
	{
		strscpy(filename,"/proc/");
		zbx_strlcat(filename,entries->d_name,MAX_STRING_LEN);

		if(0 == zbx_stat(filename,&buf))
		{
			proc = open(filename,O_RDONLY);
			if(proc == -1)
				goto lbl_skip_procces;

			if(ioctl(proc,PIOCPSINFO,&psinfo) == -1)
				goto lbl_skip_procces;

			/* Self process information. It leads to incorrect results for proc_cnt[zabbix_agentd] */
			if(psinfo.pr_pid == curr_pid)
				goto lbl_skip_procces;

			if(procname[0] != 0)
				if(strcmp(procname,psinfo.pr_fname) != 0)
					goto lbl_skip_procces;


			if(usrinfo != NULL)
				if(usrinfo->pw_uid != psinfo.pr_uid)
					goto lbl_skip_procces;

			if(proccomm[0] != '\0')
				if(zbx_regexp_match(psinfo.pr_psargs,proccomm,NULL) == NULL)
					goto lbl_skip_procces;

			proccount++;

			if(memsize < 0) /* First inicialization */
			{
				memsize = (double) (psinfo.pr_rssize * pgsize);
			}
			else
			{
				if(do_task == DO_MAX)
				{
					memsize = MAX(memsize, (double) (psinfo.pr_rssize * pgsize));
				}
				else if(do_task == DO_MIN)
				{
					memsize = MIN(memsize, (double) (psinfo.pr_rssize * pgsize));
				}
				else /* SUM */
				{
					memsize +=  (double) (psinfo.pr_rssize * pgsize);
				}
			}
lbl_skip_procces:
			if(proc) close(proc);
		}
	}

	closedir(dir);

	if(memsize < 0)
	{
		/* incorrect process name */
		memsize = 0;
	}

	if(do_task == DO_AVG)
	{
		SET_DBL_RESULT(result, proccount == 0 ? 0 : ((double)memsize/(double)proccount));
	}
	else
	{
		SET_UI64_RESULT(result, memsize);
	}

	return SYSINFO_RET_OK;
}

int	PROC_NUM(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	DIR	*dir;
	int	proc;

	struct  dirent	*entries;
	zbx_stat_t	buf;
	struct passwd	*usrinfo = NULL;
	struct prpsinfo	psinfo;

	char	filename[MAX_STRING_LEN];

	char	procname[MAX_STRING_LEN];
	char	usrname[MAX_STRING_LEN];
	char	procstat[MAX_STRING_LEN];
	char	proccomm[MAX_STRING_LEN];

	int	do_task = DO_SUM;

	int	proccount = 0;
	pid_t	curr_pid = getpid();

	if(num_param(param) > 4)
	{
		return SYSINFO_RET_FAIL;
	}

	if(get_param(param, 1, procname, MAX_STRING_LEN) != 0)
	{
		return SYSINFO_RET_FAIL;
	}

	if(get_param(param, 2, usrname, MAX_STRING_LEN) != 0)
	{
		usrname[0] = 0;
	}
	else
        {
		if(usrname[0] != 0)
		{
			usrinfo = getpwnam(usrname);
			if(usrinfo == NULL)
			{ /* incorrect user name */
				return SYSINFO_RET_FAIL;
			}
		}
	}

	if(get_param(param, 3, procstat, MAX_STRING_LEN) != 0)
	{
		procstat[0] = '\0';
	}

	if(procstat[0] == '\0')
	{
		strscpy(procstat, "all");
	}

        if(strcmp(procstat,"run") == 0)
        {
		procstat[0] = PR_SRUN;		procstat[1] = '\0';
        }
        else if(strcmp(procstat,"sleep") == 0)
        {
		procstat[0] = PR_SSLEEP;	procstat[1] = '\0';
        }
        else if(strcmp(procstat,"zomb") == 0)
        {
		procstat[0] = PR_SZOMB;		procstat[1] = '\0';
        }
        else if(strcmp(procstat,"all") == 0)
        {
		procstat[0] = '\0';
        }
        else
        {
		return SYSINFO_RET_FAIL;
        }

	if(get_param(param, 4, proccomm, MAX_STRING_LEN) != 0)
	{
		proccomm[0] = '\0';
	}

	dir=opendir("/proc");
	if(NULL == dir)
	{
	    return SYSINFO_RET_FAIL;
	}

	while((entries=readdir(dir))!=NULL)
	{
		strscpy(filename,"/proc/");
		zbx_strlcat(filename,entries->d_name,MAX_STRING_LEN);

		if(0 == zbx_stat(filename,&buf))
		{
			proc = open(filename,O_RDONLY);
			if(proc == -1)
				goto lbl_skip_procces;

			if(ioctl(proc,PIOCPSINFO,&psinfo) == -1)
				goto lbl_skip_procces;

			/* Self process information. It leads to incorrect results for proc_cnt[zabbix_agentd] */
			if(psinfo.pr_pid == curr_pid)
				goto lbl_skip_procces;

			if(procname[0] != 0)
				if(strcmp(procname,psinfo.pr_fname) != 0)
					goto lbl_skip_procces;

			if(usrinfo != NULL)
				if(usrinfo->pw_uid != psinfo.pr_uid)
					goto lbl_skip_procces;

			if(procstat[0] != '\0')
				if(psinfo.pr_sname != procstat[0])
					goto lbl_skip_procces;

			if(proccomm[0] != '\0')
				if(zbx_regexp_match(psinfo.pr_psargs,proccomm,NULL) == NULL)
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
