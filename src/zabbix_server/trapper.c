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

#include <stdio.h>
#include <stdlib.h>
#include <sys/stat.h>

#include <string.h>


/* Required for getpwuid */
#include <pwd.h>

#include <signal.h>
#include <errno.h>

#include <sys/types.h>
#include <sys/socket.h>

#include <time.h>
/* getopt() */
#include <unistd.h>

#include "cfg.h"
#include "pid.h"
#include "db.h"
#include "log.h"
#include "zlog.h"

#include "common.h"
#include "functions.h"
#include "expression.h"

#include "trapper.h"

int	process_trap(int sockfd,char *s, int max_len)
{
	char	*p,*line,*host;
	char	*server,*key,*value_string;
	char	copy[MAX_STRING_LEN];
	char	result[MAX_STRING_LEN];

	int	ret=SUCCEED;

	for( p=s+strlen(s)-1; p>s && ( *p=='\r' || *p =='\n' || *p == ' ' ); --p );
	p[1]=0;

	zabbix_log( LOG_LEVEL_DEBUG, "Trapper got [%s]", s);

/* Request for list of active checks */
	if(strncmp(s,"ZBX_GET_ACTIVE_CHECKS", strlen("ZBX_GET_ACTIVE_CHECKS")) == 0)
	{
		line=strtok(s,"\n");
		host=strtok(NULL,"\n");
		ret=send_list_of_active_checks(sockfd, host);
	}
/* Process information sent by zabbix_sender */
	else
	{
		strscpy(copy,s);

		server=(char *)strtok(s,":");
		if(NULL == server)
		{
			return FAIL;
		}

		key=(char *)strtok(NULL,":");
		if(NULL == key)
		{
			return FAIL;
		}

		value_string=strchr(copy,':');
		value_string=strchr(value_string+1,':');

/*		value_string=(char *)strtok(NULL,":");*/
		if(NULL == value_string)
		{
			return FAIL;
		}
		/* It points to ':', so have to increment */
		value_string++;

		ret=process_data(sockfd,server,key,value_string);
		if( SUCCEED == ret)
		{
			snprintf(result,sizeof(result)-1,"OK\n");
		}
		else
		{
			snprintf(result,sizeof(result)-1,"NOT OK\n");
		}
		zabbix_log( LOG_LEVEL_DEBUG, "Sending back [%s]", result);
		zabbix_log( LOG_LEVEL_DEBUG, "Length [%d]", strlen(result));
		zabbix_log( LOG_LEVEL_DEBUG, "Sockfd [%d]", sockfd);
		if( write(sockfd,result,strlen(result)) == -1)
		{
			zabbix_log( LOG_LEVEL_WARNING, "Error sending result back [%s]",strerror(errno));
			zabbix_syslog("Trapper: error sending result back [%s]",strerror(errno));
		}
		zabbix_log( LOG_LEVEL_DEBUG, "After write()");
	}	
	return ret;
}

void	process_trapper_child(int sockfd)
{
	ssize_t	nread;
	char	line[MAX_STRING_LEN];
	static struct  sigaction phan;

	phan.sa_handler = &signal_handler;
	sigemptyset(&phan.sa_mask);
	phan.sa_flags = 0;
	sigaction(SIGALRM, &phan, NULL);

	alarm(CONFIG_TIMEOUT);

	zabbix_log( LOG_LEVEL_DEBUG, "Before read()");
	if( (nread = read(sockfd, line, MAX_STRING_LEN)) < 0)
	{
		if(errno == EINTR)
		{
			zabbix_log( LOG_LEVEL_DEBUG, "Read timeout");
		}
		else
		{
			zabbix_log( LOG_LEVEL_DEBUG, "read() failed");
		}
		zabbix_log( LOG_LEVEL_DEBUG, "After read() 1");
		alarm(0);
		return;
	}

	zabbix_log( LOG_LEVEL_DEBUG, "After read() 2 [%d]",nread);

	if(nread>0)
	{
		line[nread-1]=0;
	}

	zabbix_log( LOG_LEVEL_DEBUG, "Got line:%s", line);

	process_trap(sockfd,line, MAX_STRING_LEN);

	alarm(0);
}

void	child_trapper_main(int i,int listenfd, int addrlen)
{
	int	connfd;
	socklen_t	clilen;
	struct sockaddr cliaddr;

	zabbix_log( LOG_LEVEL_DEBUG, "In child_main()");

/*	zabbix_log( LOG_LEVEL_WARNING, "zabbix_trapperd %ld started",(long)getpid());*/
	zabbix_log( LOG_LEVEL_WARNING, "server #%d started [Trapper]", i);

	zabbix_log( LOG_LEVEL_DEBUG, "Before DBconnect()");
	DBconnect();
	zabbix_log( LOG_LEVEL_DEBUG, "After DBconnect()");

	for(;;)
	{
		clilen = addrlen;
#ifdef HAVE_FUNCTION_SETPROCTITLE
		setproctitle("waiting for connection");
#endif
		zabbix_log( LOG_LEVEL_DEBUG, "Before accept()");
		connfd=accept(listenfd,&cliaddr, &clilen);
		zabbix_log( LOG_LEVEL_DEBUG, "After accept()");
#ifdef HAVE_FUNCTION_SETPROCTITLE
		setproctitle("processing data");
#endif

		process_trapper_child(connfd);

		close(connfd);
	}
	DBclose();
}

pid_t	child_trapper_make(int i,int listenfd, int addrlen)
{
	pid_t	pid;

	if((pid = fork()) >0)
	{
		return (pid);
	}
	else
	{
//		sucker_num=i;
	}

	/* never returns */
	child_trapper_main(i, listenfd, addrlen);

	/* avoid compilator warning */
	return 0;
}
