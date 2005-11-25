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
				    
int     PROC_MEMORY(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	struct kinfo_proc *proc;
	
	char    procname[MAX_STRING_LEN];
	char    usrname[MAX_STRING_LEN];
	char    mode[MAX_STRING_LEN];

	int	proc_ok = 0;
	int	usr_ok = 0;

	int	do_task = DO_SUM;

	struct  passwd *usrinfo = NULL;

	kvm_t   *kp;

	double	value = 0.0;
	double	memsize = -1.0;
    
	int	proccount = 0;
	int	count;
	int	i;
	int	ret = SYSINFO_RET_FAIL;

        assert(result);

        init_result(result);
	
    
        if(num_param(param) > 3)
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
                {
                    /* incorrect user name */
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


	kp = kvm_open(NULL,NULL,NULL,O_RDONLY,NULL);
	if(kp)
	{
		proc = kvm_getprocs(kp, KERN_PROC_ALL, 0, &count);
		if (proc)
		{
			for (i = 0; i < count; i++)
			{
				proc_ok = 0;
				usr_ok = 0;
                
				if(procname[0] != 0)
                		{
					if(strcmp(procname, proc[i].kp_proc.p_comm)==0)
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
					if(usrinfo->pw_uid == proc[i].kp_proc.p_cred->p_ruid)
					{
						usr_ok = 1;
					}
				}
				else
				{
					usr_ok = 1;
				}
	
				if(proc_ok && usr_ok)
				{
					proccount++;

					value = proc[i].kp_proc.p_vmspace->vm_tsize 
						+ proc[i].kp_proc.p_vmspace->vm_dsize
						+ proc[i].kp_proc.p_vmspace->vm_ssize;
	    	    
					if(memsize < 0)
					{
						memsize = value;
					}
					else
					{
						if(do_task == DO_MAX)
						{
							memsize = MAX(memsize, value);
						}
						else if(do_task == DO_MIN)
						{
							memsize = MIN(memsize, value);
						}
						else
						{
							memsize +=  value;
						}
					}
				}
			}
		}
		
		kvm_close(kp);
	
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
		ret = SYSINFO_RET_OK;
	}
	return ret;
}

int	    PROC_NUM(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	struct kinfo_proc *proc;
	
	char    procname[MAX_STRING_LEN];
	char    usrname[MAX_STRING_LEN];
	char    procstat[MAX_STRING_LEN];

	char	p_stat = 0;
    
	int	proc_ok = 0;
	int	usr_ok = 0;
	int	stat_ok = 0;

	struct  passwd *usrinfo = NULL;

	kvm_t   *kp;
    
	int	proccount = 0;
	int	count;
	int	i;
	int	ret = SYSINFO_RET_FAIL;

        assert(result);

        init_result(result);
	
    
        if(num_param(param) > 3)
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
                {
                    /* incorrect user name */
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
		p_stat = SRUN;
        }
        else if(strcmp(procstat,"sleep") == 0)
        {
		p_stat = SSLEEP;
        }
        else if(strcmp(procstat,"zomb") == 0)
        {
		p_stat = SZOMB;
        }
        else if(strcmp(procstat,"all") == 0)
        {
            procstat[0] = 0;
        }
        else
        {
            return SYSINFO_RET_FAIL;
        }
		
	kp = kvm_open(NULL,NULL,NULL,O_RDONLY,NULL);
	if(kp)
	{
		proc = kvm_getprocs(kp, KERN_PROC_ALL, 0, &count);
		if (proc)
		{
			for (i = 0; i < count; i++)
			{
				proc_ok = 0;
				stat_ok = 0;
				usr_ok = 0;
                
				if(procname[0] != 0)
                		{
					if(strcmp(procname, proc[i].kp_proc.p_comm)==0)
					{
						proc_ok = 1;
					}
				}
				else
				{
					proc_ok = 1;
				}

				if(procstat[0] != 0)
				{
					if(p_stat == proc[i].kp_proc.p_stat 
						|| (proc[i].kp_proc.p_stat == SDEAD && p_stat == SZOMB))
					{
						stat_ok = 1;
					}
				}
				else
				{
					stat_ok = 1;
				}
                
				if(usrinfo != NULL)
				{
					if(usrinfo->pw_uid == proc[i].kp_proc.p_cred->p_ruid)
					{
						usr_ok = 1;
					}
				}
				else
				{
					usr_ok = 1;
				}
                
				if(proc_ok && stat_ok && usr_ok)
				{
					proccount++;
				}
			}
		}
		kvm_close(kp);
	
		SET_UI64_RESULT(result, proccount);
		ret = SYSINFO_RET_OK;
	}

	return ret;
}

