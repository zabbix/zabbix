/* 
** Zabbix
** Copyright (C) 2000,2001,2002,2003,2004 Alexei Vladishev
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

#include "config.h"

#include <stdlib.h>
#include <stdio.h>

#include <unistd.h>
#include <signal.h>

#include <errno.h>

#include <time.h>

/* No warning for bzero */
#include <string.h>
#include <strings.h>

/* For config file operations */
#include <sys/types.h>
#include <sys/stat.h>
#include <fcntl.h>

/* For setpriority */
#include <sys/time.h>
#include <sys/resource.h>

/* Required for getpwuid */
#include <pwd.h>

#include <dirent.h>

#include "common.h"
#include "sysinfo.h"
#include "security.h"
#include "zabbix_agent.h"

#include "log.h"
#include "cfg.h"
#include "cpustat.h"

CPUSTAT cpustat;

void	init_stats_cpustat()
{
	int	i;

	for(i=0;i<60*15;i++)
	{
		cpustat.clock[i]=0;
	}
}

void	report_stats_cpustat(FILE *file, int now)
{
	int	time=0,
		time1=0,
		time5=0,
		time15=0;
	float
		cpu_idle=0,
		cpu_idle1=0,
		cpu_idle5=0,
		cpu_idle15=0,
		cpu_user=0,
		cpu_user1=0,
		cpu_user5=0,
		cpu_user15=0;

	int	i;

	time=now+1;
	time1=now+1;
	time5=now+1;
	time15=now+1;
	for(i=0;i<60*15;i++)
	{
		if(cpustat.clock[i]==0)
		{
			continue;
		}
		if(cpustat.clock[i]==now)
		{
			continue;
		}
		if((cpustat.clock[i] >= now-60) && (time1 > cpustat.clock[i]))
		{
			time1=cpustat.clock[i];
		}
		if((cpustat.clock[i] >= now-5*60) && (time5 > cpustat.clock[i]))
		{
			time5=cpustat.clock[i];
		}
		if((cpustat.clock[i] >= now-15*60) && (time15 > cpustat.clock[i]))
		{
			time15=cpustat.clock[i];
		}
	}
	for(i=0;i<60*15;i++)
	{
		if(cpustat.clock[i]==now)
		{
			cpu_idle=cpustat.cpu_idle[i];
			cpu_user=cpustat.cpu_user[i];
		}
		if(cpustat.clock[i]==time1)
		{
			cpu_idle1=cpustat.cpu_idle[i];
			cpu_user1=cpustat.cpu_user[i];
		}
		if(cpustat.clock[i]==time5)
		{
			cpu_idle5=cpustat.cpu_idle[i];
			cpu_user5=cpustat.cpu_user[i];
		}
		if(cpustat.clock[i]==time15)
		{
			cpu_idle15=cpustat.cpu_idle[i];
			cpu_user15=cpustat.cpu_user[i];
		}
	}
	if((sent!=0)&&(sent1!=0))
	{
		fprintf(file,"netloadout1[%s] %f\n", cpustat[i].interface, (float)((sent-sent1)/(now-time1)));
	}
	else
	{
		fprintf(file,"netloadout1[%s] 0\n", cpustat[i].interface);
	}
	if((sent!=0)&&(sent5!=0))
	{
		fprintf(file,"netloadout5[%s] %f\n", cpustat[i].interface, (float)((sent-sent5)/(now-time5)));
	}
	else
	{
		fprintf(file,"netloadout5[%s] 0\n", cpustat[i].interface);
	}
	if((sent!=0)&&(sent15!=0))
	{
		fprintf(file,"netloadout15[%s] %f\n", cpustat[i].interface, (float)((sent-sent15)/(now-time15)));
	}
	else
	{
		fprintf(file,"netloadout15[%s] 0\n", cpustat[i].interface);
	}
	if((received!=0)&&(received1!=0))
	{
		fprintf(file,"netloadin1[%s] %f\n", cpustat[i].interface, (float)((received-received1)/(now-time1)));
	}
	else
	{
		fprintf(file,"netloadin1[%s] 0\n", cpustat[i].interface);
	}
	if((received!=0)&&(received5!=0))
	{
		fprintf(file,"netloadin5[%s] %f\n", cpustat[i].interface, (float)((received-received5)/(now-time5)));
	}
	else
	{
		fprintf(file,"netloadin5[%s] 0\n", cpustat[i].interface);
	}
	if((received!=0)&&(received15!=0))
	{
		fprintf(file,"netloadin15[%s] %f\n", cpustat[i].interface, (float)((received-received15)/(now-time15)));
	}
	else
	{
		fprintf(file,"netloadin15[%s] 0\n", cpustat[i].interface);
	}
}


void	add_values_diskdevices(int now,float cpu_user,float cpu_system,float cpu_nice,float cpu_idle)
{
	int i;

/*	printf("Add_values [%s] [%f] [%f]\n",interface,value_sent,value_received);*/

	for(i=0;i<15*60;i++)
	{
		if(cpustat.clock[i]<now-15*60)
		{
			cpustat.clock[i]=now;
			cpustat.cpu_user[i]=cpu_user;;
			cpustat.cpu_system[i]=cpu_system;
			cpustat.cpu_nice[i]=cpu_nice;
			cpustat.cpu_idle[i]=cpu_idle;
			break;
		}
	}
}

void	collect_stats_diskdevices(FILE *outfile)
{
	FILE	*file;

	char	*s;
	char	line[MAX_STRING_LEN];
	int	i;
	int	now;
	float	cpu_user, cpu_nice, cpu_system, cpu_idle;

	/* Must be static */
	static	int initialised=0;

	if( 0 == initialised)
	{
		init_stats_cpustat();
		initialised=1;
	}

	now=time(NULL);

	file=fopen("/proc/stat","r");
	if(NULL == file)
	{
		fprintf(stderr, "Cannot open [%s] [%m]\n","/proc/stat");
		return;
	}
	i=0;
	while(fgets(line,1024,file) != NULL)
	{
		if( (s=strstr(line,"cpu ")) == NULL)
			continue;

		s=line;

		sscanf(s,"%f %f %f %f",&cpu_user, &cpu_nice, &cpu_system, &cpu_idle);
		add_values_cpustat(now,cpu_user, cpu_system, cpu_nice, cpu_idle);
		break;
	}

	fclose(file);

	report_stats_cpustat(outfile, now);
}
