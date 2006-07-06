/*
 * ** ZABBIX
 * ** Copyright (C) 2000-2005 SIA Zabbix
 * **
 * ** This program is free software; you can redistribute it and/or modify
 * ** it under the terms of the GNU General Public License as published by
 * ** the Free Software Foundation; either version 2 of the License, or
 * ** (at your option) any later version.
 * **
 * ** This program is distributed in the hope that it will be useful,
 * ** but WITHOUT ANY WARRANTY; without even the implied warranty of
 * ** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * ** GNU General Public License for more details.
 * **
 * ** You should have received a copy of the GNU General Public License
 * ** along with this program; if not, write to the Free Software
 * ** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
 * **/

#include "config.h"

#include "common.h"
#include "sysinfo.h"

#define DO_SUM 0
#define DO_MAX 1
#define DO_MIN 2
#define DO_AVG 3
				    

#ifndef HAVE_SYS_PROCFS_H
	extern int getprocs(
		struct procsinfo *ProcessBuffer,
		int ProcessSize,
		struct fdsinfo *FileBuffer,
		int FileSize,
		pid_t *IndexPointer,
		int Count
		);
#endif /* ndef HAVE_SYS_PROCFS_H */

int     PROC_MEMORY(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{ /* usage: <function name>[ <process name>, <user name>, <mode>, <command> ] */
	
#ifdef HAVE_SYS_PROCFS_H
    DIR		*dir;
    int		proc;

    struct psinfo	psinfo;
#else
	struct procsinfo	ProcessBuffer;
	pid_t			IndexPointer;

	AGENT_RESULT	proc_args;
	char		get_args_cmd[MAX_STRING_LEN];
#endif /* HAVE_SYS_PROCFS_H */
    
    struct  dirent	*entries;
    struct  stat	buf;
    struct passwd	*usrinfo = NULL;
    
    char	filename[MAX_STRING_LEN];

    char	procname[MAX_STRING_LEN];
    char	usrname[MAX_STRING_LEN];
    char	mode[MAX_STRING_LEN];
    char	proccomm[MAX_STRING_LEN];

    int		do_task = DO_SUM;

    double	memsize = -1;
    int		pgsize = 1024;
    int		proccount = 0;
    pid_t	curr_pid = getpid();

	assert(result);

	init_result(result);
	
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

#ifdef HAVE_SYS_PROCFS_H /* AIX 5.x */
	dir=opendir("/proc");
	if(NULL == dir)
	{
	    return SYSINFO_RET_FAIL;
	}

	while((entries=readdir(dir))!=NULL)
	{
		zbx_snprintf(filename, sizeof(filename), "/proc/%s/psinfo",entries->d_name);

		if(stat(filename,&buf)==0)
		{
			proc = open(filename,O_RDONLY);
			if(proc < 0)
				goto lbl_skip_procces;

			if(read(proc,&psinfo,sizeof(psinfo)) < 0)
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
#else
        while(getprocs(&ProcessBuffer, sizeof(struct procsinfo), NULL, sizeof(struct fdsinfo), &IndexPointer, 1) > 0)
        {
			/* Self process information. It leads to incorrect results for proc_cnt[zabbix_agentd] */
			if(ProcessBuffer.pi_pid == curr_pid)
				continue;
			
			if(procname[0] != 0)
				if(strcmp(procname,ProcessBuffer.pi_comm) != 0)
					continue;
			
			if(usrinfo != NULL)
				if(usrinfo->pw_uid != ProcessBuffer.pi_uid)
					continue;
			
			if(proccomm[0] != '\0')
			{
				init_result(&proc_args);
				zbx_snprintf(get_args_cmd, sizeof(get_args_cmd), "ps -p %i -oargs=", ProcessBuffer.pi_pid);
				if(EXECUTE_STR(cmd, get_args_cmd, flags, &proc_args) != SYSINFO_RET_OK)
				{
					free_result(&proc_args);
					continue;
				}
				if(zbx_regexp_match(proc_args.str,proccomm,NULL) == NULL)
				{
					free_result(&proc_args);
					continue;
				}
				free_result(&proc_args);
			}
			
			proccount++;
			
			if(memsize < 0) /* First inicialization */
			{
				memsize = (double) (ProcessBuffer.pi_size * pgsize);
			}
			else
			{
				if(do_task == DO_MAX)
				{
					memsize = MAX(memsize, (double) (ProcessBuffer.pi_size * pgsize));
				}
				else if(do_task == DO_MIN)
				{
					memsize = MIN(memsize, (double) (ProcessBuffer.pi_size * pgsize));
				}
				else /* SUM */
				{
					memsize +=  (double) (ProcessBuffer.pi_size * pgsize);
				}
			}
        }

#endif /* HAVE_SYS_PROCFS_H */

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

int	    PROC_NUM(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{ /* usage: <function name>[ <process name>, <user name>, <process state>, <command> ] */
	
#ifdef HAVE_SYS_PROCFS_H /* AIX 5.x */
	DIR		*dir;
	int		proc;
	
	struct psinfo	psinfo;
#else
	struct procsinfo	ProcessBuffer;
	pid_t			IndexPointer;

	AGENT_RESULT	proc_args;
	char		get_args_cmd[MAX_STRING_LEN];
#endif /* HAVE_SYS_PROCFS_H */

    struct  dirent	*entries;
    struct  stat	buf;
    struct passwd	*usrinfo = NULL;
    
    char	filename[MAX_STRING_LEN];

    char	procname[MAX_STRING_LEN];
    char	usrname[MAX_STRING_LEN];
    char	procstat[MAX_STRING_LEN];
    char	proccomm[MAX_STRING_LEN];

    int		do_task = DO_SUM;

    int		proccount = 0;
    pid_t	curr_pid = getpid();

	assert(result);

	init_result(result);
	
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
#ifdef HAVE_SYS_PROCFS_H /* AIX 5.x */
		procstat[0] = PR_SNAME_TSRUN;		procstat[1] = '\0';
#else
		procstat[0] = SRUN;
#endif
        }
        else if(strcmp(procstat,"sleep") == 0)
        {
#ifdef HAVE_SYS_PROCFS_H /* AIX 5.x */
		procstat[0] = PR_SNAME_TSSLEEP;		procstat[1] = '\0';
#else
		procstat[0] = SSLEEP;
#endif
        }
#ifdef HAVE_SYS_PROCFS_H /* AIX 5.x */
        else if(strcmp(procstat,"zomb") == 0)
        {
		procstat[0] = PR_SNAME_TSZOMB;		procstat[1] = '\0';
        }
#endif
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

#ifdef HAVE_SYS_PROCFS_H /* AIX 5.x */
	dir=opendir("/proc");
	if(NULL == dir)
	{
	    return SYSINFO_RET_FAIL;
	}

	while((entries=readdir(dir))!=NULL)
	{
		zbx_snprintf(filename, sizeof(filename),"/proc/%s/psinfo",entries->d_name);

		if(stat(filename,&buf)==0)
		{
			proc = open(filename,O_RDONLY);
			if(proc < 0)
				goto lbl_skip_procces;
			
			if(read(proc,&psinfo,sizeof(psinfo)) < 0)
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
				if(psinfo.pr_lwp.pr_sname != procstat[0])
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
#else
        while(getprocs(&ProcessBuffer, sizeof(struct procsinfo), NULL, sizeof(struct fdsinfo), &IndexPointer, 1) > 0)
        {
			/* Self process information. It leads to incorrect results for proc_cnt[zabbix_agentd] */
			if(ProcessBuffer.pi_pid == curr_pid)
				continue;
			
			if(procname[0] != 0)
				if(strcmp(procname,ProcessBuffer.pi_comm) != 0)
					continue;
			
			if(usrinfo != NULL)
				if(usrinfo->pw_uid != ProcessBuffer.pi_uid)
					continue;
			
			if(procstat[0] != '\0')
				if(ProcessBuffer.pi_state != procstat[0])
					continue;

			if(proccomm[0] != '\0')
			{
				init_result(&proc_args);
				zbx_snprintf(get_args_cmd, sizeof(get_args_cmd), "ps -p %i -oargs=", ProcessBuffer.pi_pid);
				if(EXECUTE_STR(cmd, get_args_cmd, flags, &proc_args) != SYSINFO_RET_OK)
				{
					free_result(&proc_args);
					continue;
				}
				if(zbx_regexp_match(proc_args.str,proccomm,NULL) == NULL)
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
