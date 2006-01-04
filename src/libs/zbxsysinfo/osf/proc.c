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
#if defined(HAVE_PROC_1_STATUS)

    DIR     *dir;
    struct  dirent *entries;
    struct  stat buf;
    char    filename[MAX_STRING_LEN];
    char    line[MAX_STRING_LEN];

    char    name1[MAX_STRING_LEN];
    char    name2[MAX_STRING_LEN];

    char    procname[MAX_STRING_LEN];
    char    usrname[MAX_STRING_LEN];
    char    mode[MAX_STRING_LEN];

    int     proc_ok = 0;
    int     usr_ok = 0;
    int	    do_task = DO_SUM;


    struct  passwd *usrinfo = NULL;
    long long int	llvalue = 0;

    FILE    *f;

    double	memsize = -1;
    int	proccount = 0;

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
    
    dir=opendir("/proc");
    if(NULL == dir)
    {
            return SYSINFO_RET_FAIL;
    }

    while((entries=readdir(dir))!=NULL)
    {
        proc_ok = 0;
        usr_ok = 0;
    
        strscpy(filename,"/proc/");	
        strncat(filename,entries->d_name,MAX_STRING_LEN);
        strncat(filename,"/status",MAX_STRING_LEN);
    
    /* Self is a symbolic link. It leads to incorrect results for proc_cnt[zabbix_agentd] */
    /* Better approach: check if /proc/x/ is symbolic link */
        if(strncmp(entries->d_name,"self",MAX_STRING_LEN) == 0)
        {
            continue;
        }
    
        if(stat(filename,&buf)==0)
        {
            f=fopen(filename,"r");
            if(f==NULL)
            {
                continue;
            }
    
            if(procname[0] != 0)
            {
                fgets(line,MAX_STRING_LEN,f);
                if(sscanf(line,"%s\t%s\n",name1,name2)==2)
                            {
                                    if(strcmp(name1,"Name:") == 0)
                                    {
                                            if(strcmp(procname,name2)==0)
                                            {
                                                    proc_ok = 1;
                                            }
                                    }
                            }
            
                if(proc_ok == 0) 
                {
                    fclose(f);
                    continue;
                }
            }
            else
            {
                proc_ok = 1;
            }
            
            if(usrinfo != NULL)
            {
                while(fgets(line, MAX_STRING_LEN, f) != NULL)
                {	
                
                    if(sscanf(line, "%s\t%lli\n", name1, &llvalue) != 2)
                    {
                        continue;
                    }
                    
                    if(strcmp(name1,"Uid:") != 0)
                    {
                        continue;
                    }
                    
                    if(usrinfo->pw_uid == (uid_t)(llvalue))
                    {
                        usr_ok = 1;
                        break;
                    }
                }
            }
            else
            {
                usr_ok = 1;
            }
            
            if(proc_ok && usr_ok)
            {
                while(fgets(line, MAX_STRING_LEN, f) != NULL)
                {	
                
                    if(sscanf(line, "%s\t%lli %s\n", name1, &llvalue, name2) != 3)
                    {
                        continue;
                    }
                    
                    if(strcmp(name1,"VmSize:") != 0)
                    {
                        continue;
                    }
                    
                    proccount++;
                    
                    if(strcasecmp(name2, "kB") == 0)
                    {
                        llvalue <<= 10;
                    }
                    else if(strcasecmp(name2, "mB") == 0)
                    {
                        llvalue <<= 20;
                    }
                    else if(strcasecmp(name2, "GB") == 0)
                    {
                        llvalue <<= 30;
                    }
                    else if(strcasecmp(name2, "TB") == 0)
                    {
                        llvalue <<= 40;
                    }
                    
                    if(memsize < 0)
                    {
                        memsize = (double) llvalue;
                    }
                    else
                    {
                        if(do_task == DO_MAX)
                        {
                            memsize = MAX(memsize, (double) llvalue);
                        }
                        else if(do_task == DO_MIN)
                        {
                            memsize = MIN(memsize, (double) llvalue);
                        }
                        else
                        {
                            memsize +=  (double) llvalue;
                        }
                    }
                    
                    break;
                }
            }
            
                    
            fclose(f);
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
#else
	return	SYSINFO_RET_FAIL;
#endif
}

int	    PROC_NUM(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{

    char    line[MAX_STRING_LEN];

    char    proccomm[MAX_STRING_LEN];
    char    procname[MAX_STRING_LEN];
    char    usrname[MAX_STRING_LEN];
    char    procstat[MAX_STRING_LEN];

        assert(result);

        init_result(result);
	
    
        if(num_param(param) > 4)
        {
                return SYSINFO_RET_FAIL;
        }
    
        if(get_param(param, 1, line, MAX_STRING_LEN) != 0)
        {
                line[0] = '\0';
        }
	if(line[0] == '\0')
	{
		procname[0] = '\0';
	}
	else
	{
		snprintf(procname,MAX_STRING_LEN-1,"$3==\"%s\"",line);	
	}
    
        if(get_param(param, 2, line, MAX_STRING_LEN) != 0)
        {
                line[0] = '\0';
        }
	if(line[0] == '\0')
	{
		usrname[0] = '\0';
	}
	else
	{
		if(procname[0]=='\0')
			snprintf(usrname,MAX_STRING_LEN-1,"$2==\"%s\"",line);	
		else
			snprintf(usrname,MAX_STRING_LEN-1,"&&$2==\"%s\"",line);	
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
		if(procname[0] == '\0' && usrname[0] == '\0')
		{
			strscpy(procstat,"$1==\"R\"");	
		}
		else
		{
			strscpy(procstat,"&&$1==\"R\"");
		}
        }
        else if(strcmp(procstat,"sleep") == 0)
        {
		if(procname[0] == '\0' && usrname[0] == '\0')
		{
			strscpy(procstat,"($1==\"U\"||$1==\"I\"||$1==\"S\")");	
		}
		else
		{
			strscpy(procstat,"&&($1==\"U\"||$1==\"I\"||$1==\"S\")");
		}	
        }
        else if(strcmp(procstat,"zomb") == 0)
        {
		if(procname[0] == '\0' && usrname[0] == '\0')
		{
			strscpy(procstat,"$1==\"T\"");	
		}
		else
		{
			strscpy(procstat,"&&$1==\"T\"");	
		}
        }
        else if(strcmp(procstat,"all") == 0)
        {
		procstat[0] = '\0';
        }
        else
        {
		return SYSINFO_RET_FAIL;
        }

        if(get_param(param, 4, line, MAX_STRING_LEN) != 0)
        {
                line[0] = '\0';
        }
	if(line[0] == '\0')
	{
		proccomm[0] = '\0';
	}
	else
	{
		snprintf(proccomm,MAX_STRING_LEN-1,"c=$4;for(i=5;i<=NF;i++)c=c" "$i;if(match(c,\"%s\")==0)next;",line);
	}
    
	snprintf(line, MAX_STRING_LEN-1,"ps -eostate,user,comm,args|tail -n+2|cut -b1,5-|awk 'BEGIN{j=0}%s%s%s{%sj++}END{print j}'",procname,usrname,procstat,proccomm);
	
	return EXECUTE(cmd, line, flags, result);
		

    return SYSINFO_RET_OK;
}
