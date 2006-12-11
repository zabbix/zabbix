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

int	PROC_MEMORY(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{ /* usage: <function name>[ <process name>, <user name>, <mode>, <command> ] */

    DIR     *dir;
    struct  dirent *entries;
    struct  stat buf;
    char    filename[MAX_STRING_LEN];

    char    procname[MAX_STRING_LEN];
    char    usrname[MAX_STRING_LEN];
    char    mode[MAX_STRING_LEN];
    char    proccomm[MAX_STRING_LEN];

    int     proc_ok = 0;
    int     usr_ok = 0;
    int	    do_task = DO_SUM;
    int	    comm_ok = 0;

    struct  passwd *usrinfo = NULL;
    long int	lvalue = 0;

    int		fd;
    psinfo_t	psinfo;

    double	memsize = -1;
    int		proccount = 0;

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
		usrname[0] = '\0';
	}
	    
	if(usrname[0] == '\0')
	{
		usrname[0] = 0;
	}

	if(usrname[0] != 0)
	{
		usrinfo = getpwnam(usrname);
		if(usrinfo == NULL)
		{
			/* incorrect user name */
			return SYSINFO_RET_FAIL;
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
        proc_ok = 0;
        usr_ok = 0;
	comm_ok = 0;
        
        strscpy(filename,"/proc/");	
        zbx_strlcat(filename,entries->d_name,MAX_STRING_LEN);
        zbx_strlcat(filename,"/psinfo",MAX_STRING_LEN);

        if(stat(filename,&buf)==0)
        {
            fd = open (filename, O_RDONLY);
            if (fd != -1)
            {
                if(read(fd, &psinfo, sizeof(psinfo)) == -1)
                {
                    close(fd);
                    closedir(dir);
                    return SYSINFO_RET_FAIL;
                }
                else
                {
                    if(procname[0] != 0)
                    {
                        if(strcmp(procname, psinfo.pr_fname) == 0)
                        {
                            proc_ok = 1;
                        }
                    }
                    else
                    {
                        proc_ok = 1;
                    }
                    
                    if(usrinfo != NULL)
                    {
                        /* uid_t    pr_uid;         real user id */
                        if(usrinfo->pw_uid == psinfo.pr_uid)
                        {
                            usr_ok = 1;
                        } 
                    }
                    else
                    {
                        usr_ok = 1;
                    }
		    
		    if(proccomm[0] != 0)
		    {
			if(zbx_regexp_match(psinfo.pr_psargs, proccomm, NULL) != NULL)
			{
			    comm_ok = 1;
			}
		    }
		    else
		    {
			comm_ok = 1;
		    }

                    if(proc_ok && usr_ok && comm_ok)
                    {
                        lvalue = psinfo.pr_size;
                        lvalue <<= 10; /* kB to Byte */

                        if(memsize < 0)
                        {
                            memsize = (double) lvalue;
                        }
                        else
                        {
                            if(do_task == DO_MAX)
                            {
                                memsize = MAX(memsize, (double) lvalue);
                            }
                            else if(do_task == DO_MIN)
                            {
                                memsize = MIN(memsize, (double) lvalue);
                            }
                            else
                            {
                                memsize +=  (double) lvalue;
                            }
                        }
                    }
                }
                close (fd);
            }
            else
            {
                continue;
            }
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
	SET_DBL_RESULT(result,  proccount == 0 ? 0 : ((double)memsize/(double)proccount));
    }
    else
    {
	SET_UI64_RESULT(result, memsize);
    }
    return SYSINFO_RET_OK;
}

int	PROC_NUM(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{ /* usage: <function name>[ <process name>, <user name>, <process state>, <command> ] */

    DIR	*dir;
    struct	dirent *entries;
    struct	stat buf;
    
    char	filename[MAX_STRING_LEN];
    
    char    procname[MAX_STRING_LEN];
    char    usrname[MAX_STRING_LEN];
    char    procstat[MAX_STRING_LEN];
    char    proccomm[MAX_STRING_LEN];

    int     proc_ok = 0;
    int     usr_ok = 0;
    int     stat_ok = 0;
    int	    comm_ok = 0;

    struct  passwd *usrinfo = NULL;
    char    pr_state = 0;
    
    int	    fd;
/* In the correct procfs.h, the structure name is psinfo_t */
    psinfo_t	psinfo;

    int		proccount=0;
   
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
	    usrname[0] = '\0';
	}

	if(usrname[0] == '\0')
        {
                usrname[0] = 0;
        }
        if(usrname[0] != 0)
        {
            usrinfo = getpwnam(usrname);
            if(usrinfo == NULL)
            {
                /* incorrect user name */
                return SYSINFO_RET_FAIL;
            }			        
        }
    
        if(get_param(param, 3, procstat, MAX_STRING_LEN) != 0)
	{
	    procstat[0] = '\0';
	}

	if(procstat[0] == '\0')
        {
            strscpy(procstat,"all");
        }
    
        if(strcmp(procstat,"run") == 0)
        {
            /* running */
            pr_state = SRUN;
        }
        else if(strcmp(procstat,"sleep") == 0)
        {
            /* awaiting an event */
            pr_state = SSLEEP;
        }
        else if(strcmp(procstat,"zomb") == 0)
        {
            pr_state = SZOMB;
        }
        else if(strcmp(procstat,"all") == 0)
        {
            procstat[0] = 0;
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
            proc_ok = 0;
            usr_ok = 0;
            stat_ok = 0;
	    comm_ok = 0;
            
            strscpy(filename,"/proc/");	
            zbx_strlcat(filename,entries->d_name,MAX_STRING_LEN);
            zbx_strlcat(filename,"/psinfo",MAX_STRING_LEN);
    
            if(stat(filename,&buf)==0)
            {
                fd = open (filename, O_RDONLY);
                if (fd != -1)
                {
                    if(read(fd, &psinfo, sizeof(psinfo)) == -1)
                    {
                        close(fd);
                        closedir(dir);
                        return SYSINFO_RET_FAIL;
                    }

                    close (fd);
		    
		    if(procname[0] != 0)
		    {
			if(strcmp(procname, psinfo.pr_fname) == 0)
			{
			    proc_ok = 1;
			}
		    }
		    else
		    {
			proc_ok = 1;
		    }
		    
		    if(usrinfo != NULL)
		    {
			/* uid_t    pr_uid;         real user id */
			if(usrinfo->pw_uid == psinfo.pr_uid)
			{
			    usr_ok = 1;
			} 
		    }
		    else
		    {
			usr_ok = 1;
		    }

		    if(procstat[0] != 0)
		    {
			/*  char    pr_state;           numeric lwp state */
			if(psinfo.pr_lwp.pr_state == pr_state)
			{
			    stat_ok = 1;
			} 
		    }
		    else
		    {
			stat_ok = 1;
		    }
		    
		    if(proccomm[0] != 0)
		    {
			if(zbx_regexp_match(psinfo.pr_psargs, proccomm, NULL) != NULL)
			{
			    comm_ok = 1;
			}
		    }
		    else
		    {
			comm_ok = 1;
		    }
		    
		    
		    if(proc_ok && usr_ok && stat_ok && comm_ok)
		    {
			proccount++;
		    }
                }
                else
                {
                    continue;
                }
            }
        }
        closedir(dir);

	SET_UI64_RESULT(result, proccount);
        return	SYSINFO_RET_OK;
}

