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

int	get_device_name(char *device,int mjr,int diskno)
{
	DIR	*dir;
	struct	dirent *entries;
	struct	stat buf;
	char	filename[1204];

	dir=opendir("/dev");
	while((entries=readdir(dir))!=NULL)
	{
		strncpy(filename,"/dev/",1024);	
		strncat(filename,entries->d_name,1024);

		if(stat(filename,&buf)==0)
		{
/*			printf("%s %d %d\n",filename,major(buf.st_rdev),minor(buf.st_rdev));*/
			if(S_ISBLK(buf.st_mode)&&(mjr==major(buf.st_rdev))&&(0 == minor(buf.st_rdev)))
			{
				/* We've gor /dev/hda here */
				strcpy(device,entries->d_name);
				/* diskno specifies a,b,c,d,e,f,g,h etc */
				device[strlen(device)-1] = (char)((int)'a' + (int)diskno);

/*				printf("%s [%d %d] %d %d\n",filename,mjr, diskno, major(buf.st_rdev),minor(buf.st_rdev));*/
				closedir(dir);
				return 0;
			}
		}
	}
	closedir(dir);
	return	1;
}

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
		sent=0,
		sent1=0,
		sent5=0,
		sent15=0,
		received=0,
		received1=0,
		received5=0,
		received15=0;

	int	i,j;

	for(i=0;i<MAX_INTERFACE;i++)
	{
		if(interfaces[i].interface==0)
		{
			break;
		}
/*		printf("IF [%s]\n",interfaces[i].interface);*/
		sent=0;sent1=0;received1=0;
		received=0;sent5=0;received5=0;
		sent15=0;received15=0;

		time=now+1;
		time1=now+1;
		time5=now+1;
		time15=now+1;
		for(j=0;j<60*15;j++)
		{
			if(interfaces[i].clock[j]==0)
			{
				continue;
			}
			if(interfaces[i].clock[j]==now)
			{
				continue;
			}
			if((interfaces[i].clock[j] >= now-60) && (time1 > interfaces[i].clock[j]))
			{
				time1=interfaces[i].clock[j];
			}
			if((interfaces[i].clock[j] >= now-5*60) && (time5 > interfaces[i].clock[j]))
			{
				time5=interfaces[i].clock[j];
			}
			if((interfaces[i].clock[j] >= now-15*60) && (time15 > interfaces[i].clock[j]))
			{
				time15=interfaces[i].clock[j];
			}
		}
		for(j=0;j<60*15;j++)
		{
			if(interfaces[i].clock[j]==now)
			{
				sent=interfaces[i].sent[j];
				received=interfaces[i].received[j];
			}
			if(interfaces[i].clock[j]==time1)
			{
				sent1=interfaces[i].sent[j];
				received1=interfaces[i].received[j];
			}
			if(interfaces[i].clock[j]==time5)
			{
				sent5=interfaces[i].sent[j];
				received5=interfaces[i].received[j];
			}
			if(interfaces[i].clock[j]==time15)
			{
				sent15=interfaces[i].sent[j];
				received15=interfaces[i].received[j];
			}
		}
		if((sent!=0)&&(sent1!=0))
		{
			fprintf(file,"netloadout1[%s] %f\n", interfaces[i].interface, (float)((sent-sent1)/(now-time1)));
		}
		else
		{
			fprintf(file,"netloadout1[%s] 0\n", interfaces[i].interface);
		}
		if((sent!=0)&&(sent5!=0))
		{
			fprintf(file,"netloadout5[%s] %f\n", interfaces[i].interface, (float)((sent-sent5)/(now-time5)));
		}
		else
		{
			fprintf(file,"netloadout5[%s] 0\n", interfaces[i].interface);
		}
		if((sent!=0)&&(sent15!=0))
		{
			fprintf(file,"netloadout15[%s] %f\n", interfaces[i].interface, (float)((sent-sent15)/(now-time15)));
		}
		else
		{
			fprintf(file,"netloadout15[%s] 0\n", interfaces[i].interface);
		}
		if((received!=0)&&(received1!=0))
		{
			fprintf(file,"netloadin1[%s] %f\n", interfaces[i].interface, (float)((received-received1)/(now-time1)));
		}
		else
		{
			fprintf(file,"netloadin1[%s] 0\n", interfaces[i].interface);
		}
		if((received!=0)&&(received5!=0))
		{
			fprintf(file,"netloadin5[%s] %f\n", interfaces[i].interface, (float)((received-received5)/(now-time5)));
		}
		else
		{
			fprintf(file,"netloadin5[%s] 0\n", interfaces[i].interface);
		}
		if((received!=0)&&(received15!=0))
		{
			fprintf(file,"netloadin15[%s] %f\n", interfaces[i].interface, (float)((received-received15)/(now-time15)));
		}
		else
		{
			fprintf(file,"netloadin15[%s] 0\n", interfaces[i].interface);
		}
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
