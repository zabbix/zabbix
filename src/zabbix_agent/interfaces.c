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
#include "interfaces.h"

INTERFACE interfaces[MAX_INTERFACE];

/* For Solaris */
/*
#include <stdio.h>
#include <string.h>
#include <kstat.h>

main()
{
	int i;
	kstat_t *ksp;
	kstat_named_t *knp;s

	kstat_ctl_t *kc = kstat_open();
	for (ksp = kc->kc_chain; ksp; ksp = ksp->ks_next)
	{
		if (strcmp(ksp->ks_class, "net") != 0)
			continue;
		printf("interface %s:\n", ksp->ks_name);
		kstat_read(kc, ksp, NULL);
		knp = KSTAT_NAMED_PTR(ksp);
		for (i = 0; i < ksp->ks_ndata; i++)
		{
			printf("%30.30s = %u\n", knp[i].name, knp[i].value.ul);
		}
	}
}
*/

void	init_stats_interfaces()
{
	FILE	*file;
	char	*s;
	char	line[MAX_STRING_LEN];
	char	interface[MAX_STRING_LEN];
	int	i,j,j1;

	for(i=0;i<MAX_INTERFACE;i++)
	{
		interfaces[i].interface=0;
		for(j=0;j<60*15;j++)
		{
			interfaces[i].clock[j]=0;
		}
	}

	file=fopen("/proc/net/dev","r");
	if(NULL == file)
	{
		fprintf(stderr, "Cannot open config file [%s] [%s]\n","/proc/net/dev", strerror(errno));
		return;
	}
	i=0;
	while(fgets(line,MAX_STRING_LEN,file) != NULL)
	{
		if( (s=strstr(line,":")) == NULL)
			continue;
		zbx_strlcpy(interface,line,s-line+1);
		j1=0;
		for(j=0;j<(int)strlen(interface);j++)
		{
			if(interface[j]!=' ')
			{
				interface[j1++]=interface[j];
			}
		}
		interface[j1]=0;
		interfaces[i].interface=strdup(interface);
		i++;
	}

	fclose(file);
}

void	report_stats_interfaces(FILE *file, int now)
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


void	add_values_interfaces(int now,char *interface,float value_sent,float value_received)
{
	int i,j;

	int bounced;

/*	printf("Add_values [%s] [%f] [%f]\n",interface,value_sent,value_received);*/

	for(i=0;i<MAX_INTERFACE;i++)
	{
		/* Fist condition must be here! */
		if((interfaces[i].interface!=0)&&(0 == strcmp(interfaces[i].interface,interface)))
		{
			bounced=0;
			for(j=0;j<15*60;j++)
			{
				if((interfaces[i].clock[j]<now)&&
					((value_sent<interfaces[i].sent[j])||(value_received<interfaces[i].received[j])))
				{
/* Bounced interface. Reset counters. */
					bounced=1;
					break;
				}
		
			}
			if(bounced==0)
			{
				for(j=0;j<15*60;j++)
				{
					if(interfaces[i].clock[j]<now-15*60)
					{
						interfaces[i].clock[j]=now;
						interfaces[i].sent[j]=value_sent;
						interfaces[i].received[j]=value_received;
						break;
					}
				}
			}
			else
			{
				for(j=0;j<15*60;j++)
				{
					interfaces[i].clock[j]=0;
					interfaces[i].sent[j]=0;
					interfaces[i].received[j]=0;
				}
			}
		}
	}
}

void	collect_stats_interfaces(FILE *outfile)
{
#ifdef HAVE_PROC_NET_DEV
	
	FILE	*file;

	char	*s;
	char	line[MAX_STRING_LEN];
	int	i,j;
	int	i1,j1;
	char	interface[MAX_STRING_LEN];
	int	now;
	float	received=0,sent;

	/* Must be static */
	static	int initialised=0;

	if( 0 == initialised)
	{
		init_stats_interfaces();
		initialised=1;
	}

	now=time(NULL);

	file=fopen("/proc/net/dev","r");
	if(NULL == file)
	{
		fprintf(stderr, "Cannot open config file [%s] [%s]\n","/proc/net/dev", strerror(errno));
		return;
	}


	i=0;
	while(fgets(line,MAX_STRING_LEN,file) != NULL)
	{
		if( (s=strstr(line,":")) == NULL)
			continue;
		zbx_strlcpy(interface,line,s-line+1);
		j1=0;
		for(i1=0;i1<(int)strlen(interface);i1++)
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
				add_values_interfaces(now,interface,sent,received);
			}
			j++;
		}
		i++;
	}
	fclose(file);

	report_stats_interfaces(outfile, now);

#endif /* HAVE_PROC_NET_DEV */
}
