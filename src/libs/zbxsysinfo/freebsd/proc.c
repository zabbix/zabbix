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

#include <errno.h>

#include <string.h>
#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <sys/stat.h>
#include <sys/types.h>


#ifdef HAVE_PWD_H
#	include <pwd.h>
#endif

/* Definitions of uint32_t under OS/X */
#ifdef HAVE_STDINT_H
	#include <stdint.h>
#endif
#ifdef HAVE_STRINGS_H
	#include <strings.h>
#endif
#ifdef HAVE_FCNTL_H
	#include <fcntl.h>
#endif
#ifdef HAVE_DIRENT_H
	#include <dirent.h>
#endif
/* Linux */
#ifdef HAVE_SYS_VFS_H
	#include <sys/vfs.h>
#endif
#ifdef HAVE_SYS_SYSINFO_H
	#include <sys/sysinfo.h>
#endif
/* Solaris */
#ifdef HAVE_SYS_STATVFS_H
	#include <sys/statvfs.h>
#endif

#ifdef HAVE_SYS_PROC_H
#   include <sys/proc.h>
#endif
/* Solaris */
#ifdef HAVE_SYS_PROCFS_H
/* This is needed to access the correct procfs.h definitions */
	#define _STRUCTURED_PROC 1
	#include <sys/procfs.h>
#endif
#ifdef HAVE_SYS_LOADAVG_H
	#include <sys/loadavg.h>
#endif
#ifdef HAVE_SYS_SOCKET_H
	#include <sys/socket.h>
#endif
#ifdef HAVE_NETINET_IN_H
	#include <netinet/in.h>
#endif
#ifdef HAVE_ARPA_INET_H
	#include <arpa/inet.h>
#endif
/* OpenBSD/Solaris */
#ifdef HAVE_SYS_MOUNT_H
	#include <sys/mount.h>
#endif

/* HP-UX */
#ifdef HAVE_SYS_PSTAT_H
	#include <sys/pstat.h>
#endif

#ifdef HAVE_NETDB_H
	#include <netdb.h>
#endif

/* Solaris */
#ifdef HAVE_SYS_SWAP_H
	#include <sys/swap.h>
#endif

/* FreeBSD */
#ifdef HAVE_SYS_SYSCTL_H
	#include <sys/sysctl.h>
#endif

/* Solaris */
#ifdef HAVE_SYS_SYSCALL_H
	#include <sys/syscall.h>
#endif

/* FreeBSD */
#ifdef HAVE_VM_VM_PARAM_H
	#include <vm/vm_param.h>
#endif
/* FreeBSD */
#ifdef HAVE_SYS_VMMETER_H
	#include <sys/vmmeter.h>
#endif
#ifdef HAVE_SYS_PARAM_H
	#include <sys/param.h>
#endif
/* FreeBSD */
#ifdef HAVE_SYS_TIME_H
	#include <sys/time.h>
#endif

#ifdef HAVE_MACH_HOST_INFO_H
	#include <mach/host_info.h>
#endif
#ifdef HAVE_MACH_MACH_HOST_H
	#include <mach/mach_host.h>
#endif


#ifdef HAVE_KSTAT_H
	#include <kstat.h>
#endif

#ifdef HAVE_LDAP
	#include <ldap.h>
#endif

#include "common.h"
#include "sysinfo.h"

/*
#define FDI(f, m) fprintf(stderr, "DEBUG INFO: " f "\n" , m) // show debug info to stderr
#define SDI(m) FDI("%s", m) // string info
#define IDI(i) FDI("%i", i) // integer info
*/
int	PROC_MEMORY(const char *cmd, const char *param,double  *value)
{
    /* in this moment this function for this platform unsupported */
    return	SYSINFO_RET_FAIL;
}

int	PROC_NUM(const char *cmd, const char *param,double  *value)
{   
/* ??? */
#if defined(HAVE_PROC_0_PSINFO)
    DIR	*dir;
    struct	dirent *entries;
    struct	stat buf;
    
    char	filename[MAX_STRING_LEN];
    char    procname[MAX_STRING_LEN];
    
    int     proc_ok = 0;
    int     usr_ok = 0;
    int     stat_ok = 0;

    char    pr_state = 0;
    
    int	fd;
/* In the correct procfs.h, the structure name is psinfo_t */
    psinfo_t psinfo;

        int	proccount=0;
    
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
            
            strscpy(filename,"/proc/");	
            strncat(filename,entries->d_name,MAX_STRING_LEN);
            strncat(filename,"/psinfo",MAX_STRING_LEN);
    
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

                        if(procstat[0] != 0)
                        {
                            /*  char    pr_state;           numeric lwp state */
                            if(psinfo.pr_lwp.pr_state == pr_state)
                            {
                                state_ok = 1;
                            } 
                        }
                        else
                        {
                            usr_ok = 1;
                        }
                        
                        if(proc_ok && usr_ok && state_ok)
                        {
                            proccount++;
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
        *value=(double)proccount;
        return	SYSINFO_RET_OK;
	
#elif defined(HAVE_PROC_1_STATUS)

    DIR     *dir;
    struct  dirent *entries;
    struct  stat buf;
    char    filename[MAX_STRING_LEN];
    char    line[MAX_STRING_LEN];

    char    name1[MAX_STRING_LEN];
    char    name2[MAX_STRING_LEN];

    char    procname[MAX_STRING_LEN];
    char    usrname[MAX_STRING_LEN];
    char    procstat[MAX_STRING_LEN];

    int     proc_ok = 0;
    int     usr_ok = 0;
    int     stat_ok = 0;

    struct  passwd *usrinfo = NULL;
    long int	lvalue = 0;

    FILE    *f;

        int	proccount = 0;
    
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
            strscpy(procstat,"all");
        }
    
        if(strcmp(procstat,"run") == 0)
        {
            strscpy(procstat,"R");	
        }
        else if(strcmp(procstat,"sleep") == 0)
        {
            strscpy(procstat,"S");	
        }
        else if(strcmp(procstat,"zomb") == 0)
        {
            strscpy(procstat,"Z");	
        }
        else if(strcmp(procstat,"all") == 0)
        {
            procstat[0] = 0;
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
            stat_ok = 0;
            usr_ok = 0;
    
/* Self is a symbolic link. It leads to incorrect results for proc_cnt[zabbix_agentd] */
/* Better approach: check if /proc/x/ is symbolic link */
            if(strncmp(entries->d_name,"self",MAX_STRING_LEN) == 0)
            {
                continue;
            }

            strscpy(filename,"/proc/");	
            strncat(filename,entries->d_name,MAX_STRING_LEN);
            strncat(filename,"/status",MAX_STRING_LEN);

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

                if(procstat[0] != 0)
                {
                    while(fgets(line, MAX_STRING_LEN, f) != NULL)
                    {	
                    
                        if(sscanf(line, "%s\t%s\n", name1, name2) != 2)
                        {
                            continue;
                        }
                        
                        if(strcmp(name1,"State:") != 0)
                        {
                            continue;
                        }
                        
                        if(strcmp(name2, procstat))
                        {
                            stat_ok = 1;
                            break;
                        }
                    }
                }
                else
                {
                    stat_ok = 1;
                }
                
                if(usrinfo != NULL)
                {
                    while(fgets(line, MAX_STRING_LEN, f) != NULL)
                    {	
                    
                        if(sscanf(line, "%s\t%li\n", name1, &lvalue) != 2)
                        {
                            continue;
                        }
                        
                        if(strcmp(name1,"Uid:") != 0)
                        {
                            continue;
                        }
                        
                        if(usrinfo->pw_uid == (uid_t)(lvalue))
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
                
                if(proc_ok && stat_ok && usr_ok)
                {
                    proccount++;
                }
                
                fclose(f);
            }
    }
    closedir(dir);

    *value = (double) proccount;
    return SYSINFO_RET_OK;
#else
    return	SYSINFO_RET_FAIL;
#endif
}

