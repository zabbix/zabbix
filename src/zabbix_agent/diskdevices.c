/* 
** Zabbix
** Copyright (C) 2000,2001,2002,2003 Alexei Vladishev
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

#include <netdb.h>

#include <stdlib.h>
#include <stdio.h>

#include <unistd.h>
#include <signal.h>

#include <errno.h>
#include <sys/socket.h>
#include <netinet/in.h>
#include <arpa/inet.h>

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

#include "common.h"
#include "sysinfo.h"
#include "security.h"
#include "zabbix_agent.h"

#include "log.h"
#include "cfg.h"
#include "diskdevices.h"

DISKDEVICE diskdevices[MAX_DISKDEVICES];

void	init_stats()
{
	FILE	*file;
	char	*s;
	char	line[MAX_STRING_LEN+1];
	char	interface[MAX_STRING_LEN+1];
	int	i,j,j1;

	for(i=0;i<MAX_DISKDEVICES;i++)
	{
		diskdevices[i].device=0;
		for(j=0;j<60*15;j++)
		{
			diskdevices[i].clock[j]=0;
		}
	}

	file=fopen("/proc/stat","r");
	if(NULL == file)
	{
		fprintf(stderr, "Cannot open [%s] [%m]\n","/proc/stat");
		return;
	}
	i=0;
	while(fgets(line,MAX_STRING_LEN,file) != NULL)
	{
		if( (s=strstr(line,":")) == NULL)
			continue;
		strncpy(interface,line,s-line);
		interface[s-line]=0;
		j1=0;
		for(j=0;j<strlen(interface);j++)
		{
			if(interface[j]!=' ')
			{
				interface[j1++]=interface[j];
			}
		}
		interface[j1]=0;
		diskdevices[i].device=strdup(interface);
		i++;
	}

	fclose(file);
}

void	report_stats(FILE *file, int now)
{
	int	time=0,
		time1=0,
		time5=0,
		time15=0;

	/* [1] - avg1
	 * [2] - avg5
	 * [3] - avg15
	 */
	float   read_io_ops[4];
	float   blks_read[4];
	float   write_io_ops[4];
	float   blks_write[4];

	int	i,j;

	for(i=0;i<MAX_DISKDEVICES;i++)
	{
		if(diskdevices[i].device==0)
		{
			break;
		}
/*		printf("IF [%s]\n",diskdevices[i].interface);*/
		for(j=0;j<4;j++)
		{
			read_io_ops[j]=0;
			blks_read[j]=0;
			write_io_ops[j]=0;
			blks_write[j]=0;
		}

		time=now+1;
		time1=now+1;
		time5=now+1;
		time15=now+1;
		for(j=0;j<60*15;j++)
		{
			if(diskdevices[i].clock[j]==0)
			{
				continue;
			}
			if(diskdevices[i].clock[j]==now)
			{
				continue;
			}
			if((diskdevices[i].clock[j] >= now-60) && (time1 > diskdevices[i].clock[j]))
			{
				time1=diskdevices[i].clock[j];
			}
			if((diskdevices[i].clock[j] >= now-5*60) && (time5 > diskdevices[i].clock[j]))
			{
				time5=diskdevices[i].clock[j];
			}
			if((diskdevices[i].clock[j] >= now-15*60) && (time15 > diskdevices[i].clock[j]))
			{
				time15=diskdevices[i].clock[j];
			}
		}
		for(j=0;j<60*15;j++)
		{
			if(diskdevices[i].clock[j]==now)
			{
				read_io_ops[0]=diskdevices[i].read_io_ops[j];
				write_io_ops[0]=diskdevices[i].write_io_ops[j];
				received=diskdevices[i].received[j];
			}
			if(diskdevices[i].clock[j]==time1)
			{
				read_io_ops[1]=diskdevices[i].read_io_ops[j];
				write_io_ops[1]=diskdevices[i].write_io_ops[j];
				received1=diskdevices[i].received[j];
			}
			if(diskdevices[i].clock[j]==time5)
			{
				read_io_ops[2]=diskdevices[i].read_io_ops[j];
				write_io_ops[2]=diskdevices[i].write_io_ops[j];
				received5=diskdevices[i].received[j];
			}
			if(diskdevices[i].clock[j]==time15)
			{
				read_io_ops[3]=diskdevices[i].read_io_ops[j];
				write_io_ops[3]=diskdevices[i].write_io_ops[j];
				received15=diskdevices[i].received[j];
			}
		}
		if((read_io_ops[0]!=0)&&(read_io_ops[1]!=0))
		{
			fprintf(file,"netloadout1[%s] %f\n", diskdevices[i].device, (float)((read_io_ops[0]-read_io_ops[1])/(now-time1)));
		}
		else
		{
			fprintf(file,"netloadout1[%s] 0\n", diskdevices[i].device);
		}
		if((read_io_ops[0]!=0)&&(read_io_ops[2]!=0))
		{
			fprintf(file,"netloadout5[%s] %f\n", diskdevices[i].device, (float)((read_io_ops[0]-read_io_ops[2])/(now-time5)));
		}
		else
		{
			fprintf(file,"netloadout5[%s] 0\n", diskdevices[i].device);
		}
		if((read_io_ops[0]!=0)&&(read_io_ops[3]!=0))
		{
			fprintf(file,"netloadout15[%s] %f\n", diskdevices[i].device, (float)((read_io_ops[0]-read_io_ops[3])/(now-time15)));
		}
		else
		{
			fprintf(file,"netloadout15[%s] 0\n", diskdevices[i].device);
		}

		if((received!=0)&&(received1!=0))
		{
			fprintf(file,"netloadin1[%s] %f\n", diskdevices[i].interface, (float)((received-received1)/(now-time1)));
		}
		else
		{
			fprintf(file,"netloadin1[%s] 0\n", diskdevices[i].interface);
		}
		if((received!=0)&&(received5!=0))
		{
			fprintf(file,"netloadin5[%s] %f\n", diskdevices[i].interface, (float)((received-received5)/(now-time5)));
		}
		else
		{
			fprintf(file,"netloadin5[%s] 0\n", diskdevices[i].interface);
		}
		if((received!=0)&&(received15!=0))
		{
			fprintf(file,"netloadin15[%s] %f\n", diskdevices[i].interface, (float)((received-received15)/(now-time15)));
		}
		else
		{
			fprintf(file,"netloadin15[%s] 0\n", diskdevices[i].interface);
		}
	}

}


void	add_values(int now,char *interface,float value_sent,float value_received)
{
	int i,j;

/*	printf("Add_values [%s] [%f] [%f]\n",interface,value_sent,value_received);*/

	for(i=0;i<MAX_DISKDEVICES;i++)
	{
		if(0 == strcmp(diskdevices[i].interface,interface))
		{
			for(j=0;j<15*60;j++)
			{
				if(diskdevices[i].clock[j]<now-15*60)
				{
					diskdevices[i].clock[j]=now;
					diskdevices[i].sent[j]=value_sent;
					diskdevices[i].received[j]=value_received;
					break;
				}
			}
			break;
		}
	}
}

void	collect_stats_diskdevices(FILE *outfile)
{
	FILE	*file;

	char	*s;
	char	line[MAX_STRING_LEN+1];
	int	i,j;
	int	i1,j1;
	char	interface[MAX_STRING_LEN+1];
	int	now;
	float	received,sent;

	/* Must be static */
	static	int initialised=0;

	if( 0 == initialised)
	{
		init_stats();
		initialised=1;
	}

	now=time(NULL);

	file=fopen("/proc/net/dev","r");
	if(NULL == file)
	{
		fprintf(stderr, "Cannot open config file [%s] [%m]\n","/proc/net/dev");
		return;
	}


	i=0;
	while(fgets(line,MAX_STRING_LEN,file) != NULL)
	{
		if( (s=strstr(line,":")) == NULL)
			continue;
		strncpy(interface,line,s-line);
		interface[s-line]=0;
		j1=0;
		for(i1=0;i1<strlen(interface);i1++)
		{
			if(interface[i1]!=' ')
			{
				interface[j1++]=interface[i1];
			}
		}
		interface[j1]=0;
		s=strtok(line,":");
		j=0;
/*		printf("Interface [%s]\n",interface);*/
		while(s)
		{
			s = strtok(NULL," ");
			if(j==0)
			{
/*				printf("Received [%s]\n",s);*/
				received=atof(s);
			}
			else if(j==8)
			{
/*				printf("Sent [%s]\n",s);*/
				sent=atof(s);
				add_values(now,interface,sent,received);
			}
			j++;
		}
		i++;
	}
	fclose(file);

	report_stats(outfile, now);
}
