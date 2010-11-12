/* 
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
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

#include "common.h"
#include "diskdevices.h"


void	collect_stats_diskdevices(ZBX_DISKDEVICES_DATA *pdiskdevices)
{
#if defined(TODO)
#error "Realize function collect_stats_diskdevices IF needed"
#endif
}


#if OFF && (!defined(_WINDOWS) || (defined(TODO) && defined(_WINDOWS)))

/*TODO!!! Make same as cpustat.c */

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

/* for minor(), major() under Solaris */
#ifdef HAVE_SYS_SYSMACROS_H
	#include <sys/sysmacros.h>
#endif

/* Required for getpwuid */
#include <pwd.h>

#include <dirent.h>

#include "sysinfo.h"
#include "zabbix_agent.h"

#include "log.h"
#include "cfg.h"
#include "diskdevices.h"

DISKDEVICE diskdevices[MAX_DISKDEVICES];

int	get_device_name(char *device,int mjr,int diskno)
{
	DIR	*dir;
	struct	dirent *entries;
	struct	stat buf;
	char	filename[1204];

	dir=opendir("/dev");
	while((entries=readdir(dir))!=NULL)
	{
		zbx_strlcpy(filename,"/dev/",1024);	
		zbx_strlcat(filename,entries->d_name,1024);

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

void	init_stats_diskdevices()
{
	FILE	*file;
	char	*s,*s2;
	char	line[1024+1];
	char	device[1024+1];
	int	i,j;
	int	major,diskno;
	int	noinfo;
	int	read_io_ops;
	int	blks_read;
	int	write_io_ops;
	int	blks_write;

	for(i=0;i<MAX_DISKDEVICES;i++)
	{
		diskdevices[i].device=0;
		for(j=0;j<60*15;j++)
		{
			diskdevices[i].clock[j]=0;
		}
	}

	if(NULL == (file = fopen("/proc/stat","r") ))
	{
		zbx_error("Cannot open [%s] [%s].","/proc/stat", strerror(errno));
		return;
	}
	i=0;
	while(fgets(line,1024,file) != NULL)
	{
		if( (s=strstr(line,"disk_io:")) == NULL)
			continue;

		s=line;

		for(;;)
		{
			if( (s=strchr(s,' ')) == NULL)
				break;
			s++;
			if( (s2=strchr(s,')')) == NULL)
				break;
			if( (s2=strchr(s2+1,')')) == NULL)
				break;
			s2++;
	
			zbx_strlcpy(device,s,s2-s);
			sscanf(device,"(%d,%d):(%d,%d,%d,%d,%d)",&major,&diskno,&noinfo,&read_io_ops,&blks_read,&write_io_ops,&blks_write);
/*			printf("Major:[%d] Minor:[%d] read_io_ops[%d]\n",major,diskno,read_io_ops);*/
	
			if(get_device_name(device,major,diskno)==0)
			{
/*				printf("Device:%s\n",device);*/
				diskdevices[i].device=strdup(device);
				diskdevices[i].major=major;
				diskdevices[i].diskno=diskno;
				i++;
			}
			s=s2;
		}
	}

	zbx_fclose(file);
}

/*
void	init_stats_diskdevices()
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

	if(NULL == (file = fopen("/proc/stat","r") ))
	{
		zbx_error("Cannot open [%s] [%m].","/proc/stat");
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

	zbx_fclose(file);
}
*/

void	report_stats_diskdevices(FILE *file, int now)
{
	int	time=0,
		time1=0,
		time5=0,
		time15=0;

	/* [1] - avg1
	 * [2] - avg5
	 * [3] - avg15
	 */
	double   read_io_ops[4];
	double   blks_read[4];
	double   write_io_ops[4];
	double   blks_write[4];

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
				blks_read[0]=diskdevices[i].blks_read[j];
				write_io_ops[0]=diskdevices[i].write_io_ops[j];
				blks_write[0]=diskdevices[i].blks_write[j];
			}
			if(diskdevices[i].clock[j]==time1)
			{
				read_io_ops[1]=diskdevices[i].read_io_ops[j];
				blks_read[1]=diskdevices[i].blks_read[j];
				write_io_ops[1]=diskdevices[i].write_io_ops[j];
				blks_write[1]=diskdevices[i].blks_write[j];
			}
			if(diskdevices[i].clock[j]==time5)
			{
				read_io_ops[2]=diskdevices[i].read_io_ops[j];
				blks_read[2]=diskdevices[i].blks_read[j];
				write_io_ops[2]=diskdevices[i].write_io_ops[j];
				blks_write[2]=diskdevices[i].blks_write[j];
			}
			if(diskdevices[i].clock[j]==time15)
			{
				read_io_ops[3]=diskdevices[i].read_io_ops[j];
				blks_read[3]=diskdevices[i].blks_read[j];
				write_io_ops[3]=diskdevices[i].write_io_ops[j];
				blks_write[3]=diskdevices[i].blks_write[j];
			}
		}

		if((read_io_ops[0]!=0)&&(read_io_ops[1]!=0))
		{
			fprintf(file,"disk_read_ops1[%s] " ZBX_FS_DBL "\n", diskdevices[i].device, (double)((read_io_ops[0]-read_io_ops[1])/(now-time1)));
		}
		else
		{
			fprintf(file,"disk_read_ops1[%s] 0\n", diskdevices[i].device);
		}
		if((read_io_ops[0]!=0)&&(read_io_ops[2]!=0))
		{
			fprintf(file,"disk_read_ops5[%s] " ZBX_FS_DBL "\n", diskdevices[i].device, (double)((read_io_ops[0]-read_io_ops[2])/(now-time5)));
		}
		else
		{
			fprintf(file,"disk_read_ops5[%s] 0\n", diskdevices[i].device);
		}
		if((read_io_ops[0]!=0)&&(read_io_ops[3]!=0))
		{
			fprintf(file,"disk_read_ops15[%s] " ZBX_FS_DBL "\n", diskdevices[i].device, (double)((read_io_ops[0]-read_io_ops[3])/(now-time15)));
		}
		else
		{
			fprintf(file,"disk_read_ops15[%s] 0\n", diskdevices[i].device);
		}

		if((blks_read[0]!=0)&&(blks_read[1]!=0))
		{
			fprintf(file,"disk_read_blks1[%s] " ZBX_FS_DBL "\n", diskdevices[i].device, (double)((blks_read[0]-blks_read[1])/(now-time1)));
		}
		else
		{
			fprintf(file,"disk_read_blks1[%s] 0\n", diskdevices[i].device);
		}
		if((blks_read[0]!=0)&&(blks_read[2]!=0))
		{
			fprintf(file,"disk_read_blks5[%s] " ZBX_FS_DBL "\n", diskdevices[i].device, (double)((blks_read[0]-blks_read[2])/(now-time5)));
		}
		else
		{
			fprintf(file,"disk_read_blks5[%s] 0\n", diskdevices[i].device);
		}
		if((blks_read[0]!=0)&&(blks_read[3]!=0))
		{
			fprintf(file,"disk_read_blks15[%s] " ZBX_FS_DBL "\n", diskdevices[i].device, (double)((blks_read[0]-blks_read[3])/(now-time15)));
		}
		else
		{
			fprintf(file,"disk_read_blks15[%s] 0\n", diskdevices[i].device);
		}

		if((write_io_ops[0]!=0)&&(write_io_ops[1]!=0))
		{
			fprintf(file,"disk_write_ops1[%s] " ZBX_FS_DBL "\n", diskdevices[i].device, (double)((write_io_ops[0]-write_io_ops[1])/(now-time1)));
		}
		else
		{
			fprintf(file,"disk_write_ops1[%s] 0\n", diskdevices[i].device);
		}
		if((write_io_ops[0]!=0)&&(write_io_ops[2]!=0))
		{
			fprintf(file,"disk_write_ops5[%s] " ZBX_FS_DBL "\n", diskdevices[i].device, (double)((write_io_ops[0]-write_io_ops[2])/(now-time5)));
		}
		else
		{
			fprintf(file,"disk_write_ops5[%s] 0\n", diskdevices[i].device);
		}
		if((write_io_ops[0]!=0)&&(write_io_ops[3]!=0))
		{
			fprintf(file,"disk_write_ops15[%s] " ZBX_FS_DBL "\n", diskdevices[i].device, (double)((write_io_ops[0]-write_io_ops[3])/(now-time15)));
		}
		else
		{
			fprintf(file,"disk_write_ops15[%s] 0\n", diskdevices[i].device);
		}

		if((blks_write[0]!=0)&&(blks_write[1]!=0))
		{
			fprintf(file,"disk_write_blks1[%s] " ZBX_FS_DBL "\n", diskdevices[i].device, (double)((blks_write[0]-blks_write[1])/(now-time1)));
		}
		else
		{
			fprintf(file,"disk_write_blks1[%s] 0\n", diskdevices[i].device);
		}
		if((blks_write[0]!=0)&&(blks_write[2]!=0))
		{
			fprintf(file,"disk_write_blks5[%s] " ZBX_FS_DBL "\n", diskdevices[i].device, (double)((blks_write[0]-blks_write[2])/(now-time5)));
		}
		else
		{
			fprintf(file,"disk_write_blks5[%s] 0\n", diskdevices[i].device);
		}
		if((blks_write[0]!=0)&&(blks_write[3]!=0))
		{
			fprintf(file,"disk_write_blks15[%s] " ZBX_FS_DBL "\n", diskdevices[i].device, (double)((blks_write[0]-blks_write[3])/(now-time15)));
		}
		else
		{
			fprintf(file,"disk_write_blks15[%s] 0\n", diskdevices[i].device);
		}

	}

}


void	add_values_diskdevices(int now,int major,int diskno,double read_io_ops,double blks_read,double write_io_ops,double blks_write)
{
	int i,j;

/*	printf("Add_values [%s] [" ZBX_FS_DBL "] [" ZBX_FS_DBL "]\n",interface,value_sent,value_received);*/

	for(i=0;i<MAX_DISKDEVICES;i++)
	{
		if((diskdevices[i].major==major)&&(diskdevices[i].diskno==diskno))
		{
			for(j=0;j<15*60;j++)
			{
				if(diskdevices[i].clock[j]<now-15*60)
				{
					diskdevices[i].clock[j]=now;
					diskdevices[i].read_io_ops[j]=read_io_ops;
					diskdevices[i].blks_read[j]=blks_read;
					diskdevices[i].write_io_ops[j]=write_io_ops;
					diskdevices[i].blks_write[j]=blks_write;
					break;
				}
			}
			break;
		}
	}
}

void	collect_stats_diskdevices(FILE *outfile)
{
#ifdef HAVE_PROC_STAT

	FILE	*file;

	char	*s,*s2;
	char	line[MAX_STRING_LEN];
	int	i;
	char	device[MAX_STRING_LEN];
	int	now;
	int	major,diskno;
	int	noinfo;
	int	read_io_ops;
	int	blks_read;
	int	write_io_ops;
	int	blks_write;

	/* Must be static */
	static	int initialised=0;

	if( 0 == initialised)
	{
		init_stats_diskdevices();
		initialised=1;
	}

	now=time(NULL);

	if( NULL == (file = fopen("/proc/stat","r") ))
	{
		zbx_error("Cannot open [%s] [%s].","/proc/stat", strerror(errno));
		return;
	}
	i=0;
	while(fgets(line,1024,file) != NULL)
	{
		if( (s=strstr(line,"disk_io:")) == NULL)
			continue;

		s=line;

		for(;;)
		{
			if( (s=strchr(s,' ')) == NULL)
				break;
			s++;
			if( (s2=strchr(s,')')) == NULL)
				break;
			if( (s2=strchr(s2+1,')')) == NULL)
				break;
			s2++;
	
			zbx_strlcpy(device,s,s2-s);
			sscanf(device,"(%d,%d):(%d,%d,%d,%d,%d)",&major,&diskno,&noinfo,&read_io_ops,&blks_read,&write_io_ops,&blks_write);
/*			printf("Major:[%d] Minor:[%d] read_io_ops[%d]\n",major,diskno,read_io_ops);*/
			add_values_diskdevices(now,major,diskno,read_io_ops,blks_read,write_io_ops,blks_write);
	
			s=s2;
		}
	}

	zbx_fclose(file);

	report_stats_diskdevices(outfile, now);

#endif /* HAVE_PROC_STAT */
}

#endif /* TODO */
