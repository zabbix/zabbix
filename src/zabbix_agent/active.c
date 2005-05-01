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

#include <netdb.h>

#include <stdlib.h>
#include <stdio.h>

#include <unistd.h>
#include <signal.h>

#include <time.h>

#include <errno.h>
#include <sys/socket.h>
#include <netinet/in.h>
#include <arpa/inet.h>

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

#include "pid.h"
#include "log.h"
#include "cfg.h"
#include "stats.h"
#include "active.h"

METRIC	*metrics=NULL;

int	get_min_nextcheck()
{
	int i;
	int min=-1;
	int nodata=0;

	for(i=0;;i++)
	{
		if(metrics[i].key == NULL)	break;

		nodata=1;
		if( (metrics[i].nextcheck < min) || (min == -1))
		{
			min=metrics[i].nextcheck;
		}
	}

	if(nodata==0)
	{
		return	FAIL;
	}
	return min;
}

void	add_check(char *key, int refresh)
{

	int i;

	for(i=0;;i++)
	{
		if(metrics[i].key == NULL)
		{

			metrics[i].key=strdup(key);
			metrics[i].refresh=refresh;
			metrics[i].nextcheck=0;
			metrics[i].status=ITEM_STATUS_ACTIVE;

			metrics=realloc(metrics,(i+2)*sizeof(METRIC));
			metrics[i+1].key=NULL;
			break;
		}
	}
}

/* Parse list of active checks received from server */
int	parse_list_of_checks(char *str)
{
	char *line;
	char *key, *refresh;
	char *s1, *s2;

	metrics=malloc(sizeof(METRIC));
	metrics[0].key=NULL;

	line=(char *)strtok_r(str,"\n",&s1);
	while(line!=NULL)
	{
		zabbix_log( LOG_LEVEL_WARNING, "Parsed [%s]", line);

		if(strcmp(line,"ZBX_EOF")==0)	break;

		key=(char *)strtok_r(line,":",&s2);
		zabbix_log( LOG_LEVEL_WARNING, "Key [%s]", key);
		refresh=(char *)strtok_r(NULL,":",&s2);
		zabbix_log( LOG_LEVEL_WARNING, "Refresh [%s]", refresh);

		add_check(key, atoi(refresh));

		line=(char *)strtok_r(NULL,"\n",&s1);
	}

	return SUCCEED;
}

int	get_active_checks(char *server, int port, char *error, int max_error_len)
{
	int	s;
	int	len;
	char	c[MAX_BUF_LEN];

	struct hostent *hp;

	struct sockaddr_in servaddr_in;

	zabbix_log( LOG_LEVEL_WARNING, "get_active_checks: host[%s] port[%d]", server, port);

	servaddr_in.sin_family=AF_INET;
	hp=gethostbyname(server);

	if(hp==NULL)
	{
		zabbix_log( LOG_LEVEL_WARNING, "gethostbyname() failed [%s]", hstrerror(h_errno));
		snprintf(error,max_error_len-1,"gethostbyname() failed [%s]", hstrerror(h_errno));
		return	NETWORK_ERROR;
	}

	servaddr_in.sin_addr.s_addr=((struct in_addr *)(hp->h_addr))->s_addr;

	servaddr_in.sin_port=htons(port);

	s=socket(AF_INET,SOCK_STREAM,0);

	if(s == -1)
	{
		zabbix_log(LOG_LEVEL_WARNING, "Cannot create socket [%s]",
				strerror(errno));
		snprintf(error,max_error_len-1,"Cannot create socket [%s]", strerror(errno));
		return	FAIL;
	}
 
	if( connect(s,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)) == -1 )
	{
		switch (errno)
		{
			case EINTR:
				zabbix_log( LOG_LEVEL_WARNING, "Timeout while connecting to [%s]",server);
				snprintf(error,max_error_len-1,"Timeout while connecting to [%s]",server);
				break;
			case EHOSTUNREACH:
				zabbix_log( LOG_LEVEL_WARNING, "No route to host [%s]",server);
				snprintf(error,max_error_len-1,"No route to host [%s]",server);
				break;
			default:
				zabbix_log( LOG_LEVEL_WARNING, "Cannot connect to [%s] [%s]",server, strerror(errno));
				snprintf(error,max_error_len-1,"Cannot connect to [%s] [%s]",server, strerror(errno));
		} 
		close(s);
		return	NETWORK_ERROR;
	}

	snprintf(c,sizeof(c)-1,"%s\n%s\n","ZBX_GET_ACTIVE_CHECKS",CONFIG_HOSTNAME);
	zabbix_log(LOG_LEVEL_WARNING, "Sending [%s]", c);
	if( write(s,c,strlen(c)) == -1 )
	{
		switch (errno)
		{
			case EINTR:
				zabbix_log( LOG_LEVEL_WARNING, "Timeout while sending data to [%s]",server );
				snprintf(error,max_error_len-1,"Timeout while sending data to [%s]",server);
				break;
			default:
				zabbix_log( LOG_LEVEL_WARNING, "Error while sending data to [%s] [%s]",server, strerror(errno));
				snprintf(error,max_error_len-1,"Error while sending data to [%s] [%s]",server, strerror(errno));
		} 
		close(s);
		return	FAIL;
	} 

	memset(c,0,MAX_STRING_LEN);


	zabbix_log(LOG_LEVEL_WARNING, "Reading");
	len=read(s,c,MAX_BUF_LEN-1);
	if(len == -1)
	{
		switch (errno)
		{
			case 	EINTR:
					zabbix_log( LOG_LEVEL_WARNING, "Timeout while receiving data from [%s]",server );
					snprintf(error,max_error_len-1,"Timeout while receiving data from [%s]",server);
					break;
			case	ECONNRESET:
					zabbix_log( LOG_LEVEL_WARNING, "Connection reset by peer.");
					snprintf(error,max_error_len-1,"Connection reset by peer.");
					close(s);
					return	NETWORK_ERROR;
			default:
					zabbix_log( LOG_LEVEL_WARNING, "Error while receiving data from [%s] [%s]",server, strerror(errno));
					snprintf(error,max_error_len-1,"Error while receiving data from [%s] [%s]",server, strerror(errno));
		} 
		close(s);
		return	FAIL;
	}
	zabbix_log(LOG_LEVEL_WARNING, "Read [%s]", c);

	parse_list_of_checks(c);

	if( close(s)!=0 )
	{
		zabbix_log(LOG_LEVEL_WARNING, "Problem with close [%s]", strerror(errno));
	}

	return SUCCEED;
}

int	send_value(char *server,int port,char *shortname,char *value)
{
	int	i,s;
	char	tosend[1024];
	char	result[1024];
	struct hostent *hp;

	struct sockaddr_in myaddr_in;
	struct sockaddr_in servaddr_in;

	zabbix_log( LOG_LEVEL_DEBUG, "In send_value()");

	servaddr_in.sin_family=AF_INET;
	hp=gethostbyname(server);

	if(hp==NULL)
	{
		return	FAIL;
	}

	servaddr_in.sin_addr.s_addr=((struct in_addr *)(hp->h_addr))->s_addr;

	servaddr_in.sin_port=htons(port);

	s=socket(AF_INET,SOCK_STREAM,0);
	if(s == -1)
	{
		zabbix_log( LOG_LEVEL_WARNING, "Error in socket() [%s] [%s]",server, strerror(errno));
		return	FAIL;
	}

/*	ling.l_onoff=1;*/
/*	ling.l_linger=0;*/
/*	if(setsockopt(s,SOL_SOCKET,SO_LINGER,&ling,sizeof(ling))==-1)*/
/*	{*/
/* Ignore */
/*	}*/
 
	myaddr_in.sin_family = AF_INET;
	myaddr_in.sin_port=0;
	myaddr_in.sin_addr.s_addr=INADDR_ANY;

	if( connect(s,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)) == -1 )
	{
		zabbix_log( LOG_LEVEL_WARNING, "Error in connect() [%s] [%s]",server, strerror(errno));
		close(s);
		return	FAIL;
	}

	snprintf(tosend,sizeof(tosend)-1,"%s:%s\n",shortname,value);

	if( sendto(s,tosend,strlen(tosend),0,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)) == -1 )
	{
		zabbix_log( LOG_LEVEL_WARNING, "Error in sendto() [%s] [%s]",server, strerror(errno));
		perror("sendto");
		close(s);
		return	FAIL;
	} 
	i=sizeof(struct sockaddr_in);
/*	i=recvfrom(s,result,1023,0,(struct sockaddr *)&servaddr_in,(size_t *)&i);*/
	i=recvfrom(s,result,1023,0,(struct sockaddr *)&servaddr_in,(socklen_t *)&i);
	if(s==-1)
	{
		zabbix_log( LOG_LEVEL_WARNING, "Error in recvfrom() [%s] [%s]",server, strerror(errno));
		perror("recfrom");
		close(s);
		return	FAIL;
	}

	result[i-1]=0;

	if(strcmp(result,"OK") == 0)
	{
		zabbix_log( LOG_LEVEL_DEBUG, "OK");
	}
	else
	{
		zabbix_log( LOG_LEVEL_WARNING, "NOT OK [%s]", shortname);
	}
 
	if( close(s)!=0 )
	{
		zabbix_log( LOG_LEVEL_WARNING, "Error in close() [%s] [%s]",server, strerror(errno));
		perror("close");
		
	}

	return SUCCEED;
}

void	process_active_checks()
{
	char	value[MAX_STRING_LEN];
	int	i, now;

	char	shortname[MAX_STRING_LEN];

	now=time(NULL);

	for(i=0;;i++)
	{
		if(metrics[i].key == NULL)	break;
		if(metrics[i].nextcheck>now)	continue;

		process(metrics[i].key, value);

		snprintf(shortname, MAX_STRING_LEN-1,"%s:%s",CONFIG_HOSTNAME,metrics[i].key);
		zabbix_log( LOG_LEVEL_WARNING, "%s",shortname);
		send_value("127.0.0.1",10051,shortname,value);

		metrics[i].nextcheck=time(NULL)+metrics[i].refresh;
	}
}

void    child_active_main(int i,char *server, int port)
{
	char	error[MAX_STRING_LEN];
	int	sleeptime, nextcheck;

	zabbix_log( LOG_LEVEL_WARNING, "zabbix_agentd %ld started",(long)getpid());

#ifdef HAVE_FUNCTION_SETPROCTITLE
	setproctitle("before getting list of active checks");
#endif
	get_active_checks(server, port, error, sizeof(error));

	for(;;)
	{
#ifdef HAVE_FUNCTION_SETPROCTITLE
		setproctitle("processing active checks");
#endif
		process_active_checks();
#ifdef HAVE_FUNCTION_SETPROCTITLE
		setproctitle("sleeping for 10 seconds");
#endif
		nextcheck=get_min_nextcheck();
		if( FAIL == nextcheck)
		{
			sleeptime=60;
		}
		else
		{
			sleeptime=nextcheck-time(NULL);
			if(sleeptime<0)
			{
				sleeptime=0;
			}
		}
		if(sleeptime>0)
		{
			if(sleeptime > 60)
			{
				sleeptime = 60;
			}
			zabbix_log( LOG_LEVEL_WARNING, "Sleeping for %d seconds",
					sleeptime );
#ifdef HAVE_FUNCTION_SETPROCTITLE
			setproctitle("sucker [sleeping for %d seconds]", 
					sleeptime);
#endif
			sleep( sleeptime );
		}
		else
		{
			zabbix_log( LOG_LEVEL_DEBUG, "No sleeping" );
		}
//		sleep(1);
	}
}

pid_t	child_active_make(int i,char *server, int port)
{
	pid_t	pid;

	if((pid = fork()) >0)
	{
			return (pid);
	}

	/* never returns */
	child_active_main(i, server, port);

	/* avoid compilator warning */
	return 0;
}
