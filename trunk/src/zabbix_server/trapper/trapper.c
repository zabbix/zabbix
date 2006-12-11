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
#include "../functions.h"
#include "../expression.h"

#include "autoregister.h"
#include "nodesync.h"
#include "nodeevents.h"
#include "nodehistory.h"
#include "trapper.h"

#include "daemon.h"

extern int    send_list_of_active_checks(int sockfd, char *host);

int	process_trap(int sockfd,char *s, int max_len)
{
	char	*p,*line,*host;
	char	*server,*key,*value_string;
	char	copy[MAX_STRING_LEN];
	char	result[MAX_STRING_LEN];
	char	host_dec[MAX_STRING_LEN],key_dec[MAX_STRING_LEN],value_dec[MAX_STRING_LEN];
	char	lastlogsize[MAX_STRING_LEN];
	char	timestamp[MAX_STRING_LEN];
	char	source[MAX_STRING_LEN];
	char	severity[MAX_STRING_LEN];

	int	ret=SUCCEED;

	for( p=s+strlen(s)-1; p>s && ( *p=='\r' || *p =='\n' || *p == ' ' ); --p );
	p[1]=0;

	zabbix_log( LOG_LEVEL_DEBUG, "Trapper got [%s]", s);

/* Request for list of active checks */
	if(strncmp(s,"ZBX_GET_ACTIVE_CHECKS", strlen("ZBX_GET_ACTIVE_CHECKS")) == 0)
	{
		line=strtok(s,"\n");
		host=strtok(NULL,"\n");
		if(host == NULL)
		{
			zabbix_log( LOG_LEVEL_WARNING, "ZBX_GET_ACTIVE_CHECKS: host is null. Ignoring.");
		}
		else
		{
			if(autoregister(host) == SUCCEED)
			{
				zabbix_log( LOG_LEVEL_DEBUG, "New host registered [%s]", host);
			}
			else
			{
				zabbix_log( LOG_LEVEL_DEBUG, "Host already exists [%s]", host);
			}
			ret=send_list_of_active_checks(sockfd, host);
		}
	}
/* Process information sent by zabbix_sender */
	else
	{
		/* Node data exchange? */
		if(strncmp(s,"Data",4) == 0)
		{
//			zabbix_log( LOG_LEVEL_WARNING, "Node data received [len:%d]", strlen(s));
			if(node_sync(s) == SUCCEED)
			{
				zbx_snprintf(result,sizeof(result),"OK\n");
				if( write(sockfd,result,strlen(result)) == -1)
				{
					zabbix_log( LOG_LEVEL_WARNING, "Error sending confirmation to node [%s]",strerror(errno));
					zabbix_syslog("Trapper: error sending confirmation to node [%s]",strerror(errno));
				}
			}
			return ret;
		}
		/* Slave node events? */
		if(strncmp(s,"Events",6) == 0)
		{
//			zabbix_log( LOG_LEVEL_WARNING, "Slave node events received [len:%d]", strlen(s));
			if(node_events(s) == SUCCEED)
			{
				zbx_snprintf(result,sizeof(result),"OK\n");
				if( write(sockfd,result,strlen(result)) == -1)
				{
					zabbix_log( LOG_LEVEL_WARNING, "Error sending confirmation to node [%s]",strerror(errno));
					zabbix_syslog("Trapper: error sending confirmation to node [%s]",strerror(errno));
				}
			}
			return ret;
		}
		/* Slave node history ? */
		if(strncmp(s,"History",7) == 0)
		{
//			zabbix_log( LOG_LEVEL_WARNING, "Slave node history received [len:%d]", strlen(s));
			if(node_history(s) == SUCCEED)
			{
				zbx_snprintf(result,sizeof(result),"OK\n");
				if( write(sockfd,result,strlen(result)) == -1)
				{
					zabbix_log( LOG_LEVEL_WARNING, "Error sending confirmation to node [%s]",strerror(errno));
					zabbix_syslog("Trapper: error sending confirmation to node [%s]",strerror(errno));
				}
			}
			return ret;
		}
		/* New XML protocol? */
		else if(s[0]=='<')
		{
			zabbix_log( LOG_LEVEL_DEBUG, "XML received [%s]", s);

			comms_parse_response(s,host_dec,key_dec,value_dec,lastlogsize,timestamp,source,severity,sizeof(host_dec)-1);

			server=host_dec;
			value_string=value_dec;
			key=key_dec;
		}
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

			if(NULL == value_string)
			{
				return FAIL;
			}
			/* It points to ':', so have to increment */
			value_string++;
			lastlogsize[0]=0;
			timestamp[0]=0;
			source[0]=0;
			severity[0]=0;
		}

		DBbegin();
		ret=process_data(sockfd,server,key,value_string,lastlogsize,timestamp,source,severity);
		DBcommit();
		
		if( SUCCEED == ret)
		{
			zbx_snprintf(result,sizeof(result),"OK\n");
		}
		else
		{
			zbx_snprintf(result,sizeof(result),"NOT OK\n");
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
	ssize_t	nbytes;
	char	*buffer;
	static struct  sigaction phan;
	char	*bufptr;
	zbx_uint64_t	expected_len;
	zbx_uint64_t	read_len=0;

#define ZBX_MAX_PACKET_LEN	16*1024*1024

//	zabbix_log( LOG_LEVEL_WARNING, "In process_trapper_child");

	
	buffer=malloc(ZBX_MAX_PACKET_LEN);

	phan.sa_handler = &child_signal_handler;
	sigemptyset(&phan.sa_mask);
	phan.sa_flags = 0;
	sigaction(SIGALRM, &phan, NULL);

//	alarm(CONFIG_TIMEOUT);

//	zabbix_log( LOG_LEVEL_WARNING, "Before read(%d)", ZBX_MAX_PACKET_LEN);
/*	if( (nbytes = read(sockfd, line, MAX_BUF_LEN)) < 0)*/
	memset(buffer,0,ZBX_MAX_PACKET_LEN);
	bufptr = buffer;

	nbytes=read(sockfd, bufptr, 5);

	if(nbytes==5 && strncmp(bufptr,"ZBXD",4)==0 && bufptr[4] == 1)
	{
//		zabbix_log( LOG_LEVEL_WARNING, "Got new protocol");
		nbytes=read(sockfd, (zbx_uint64_t *)&expected_len, sizeof(expected_len));
//		zabbix_log( LOG_LEVEL_WARNING, "Read %d bytes", nbytes);
		if(nbytes==sizeof(expected_len))
		{
//			zabbix_log( LOG_LEVEL_WARNING, "Expected data len [" ZBX_FS_UI64 "]", expected_len);
		}
		while ((nbytes = read(sockfd, bufptr, 1024)) != -1 && nbytes != 0)
		{
//			zabbix_log( LOG_LEVEL_WARNING, "Read %d bytes", nbytes);
			read_len+=nbytes;
			if(read_len >= expected_len)
			{
				break;
			}
			bufptr += nbytes;
		}
//		zabbix_log( LOG_LEVEL_WARNING, "Read total %d bytes", read_len);
	}
	else
	{
		bufptr += nbytes;
		zabbix_log( LOG_LEVEL_DEBUG, "Old protocol");
		while ((nbytes = read(sockfd, bufptr, buffer + ZBX_MAX_PACKET_LEN - bufptr - 1)) != -1 && nbytes != 0)
		{
			if(read_len < buffer + ZBX_MAX_PACKET_LEN - bufptr - 1)
			{
				bufptr += nbytes;
				break;
			}
			bufptr += nbytes;
		}
	}
//	zabbix_log( LOG_LEVEL_WARNING, "Read total %d bytes", nbytes);

	if(nbytes < 0)
	{
		if(errno == EINTR)
		{
			zabbix_log( LOG_LEVEL_WARNING, "Read timeout");
		}
		else
		{
			zabbix_log( LOG_LEVEL_WARNING, "read() failed");
		}
		alarm(0);
		free(buffer);
		return;
	}

	zabbix_log( LOG_LEVEL_DEBUG, "After read() 3 [%d]",nbytes);

	zabbix_log( LOG_LEVEL_DEBUG, "Got data:%s", buffer);

	process_trap(sockfd,buffer, sizeof(buffer));

	free(buffer);
	alarm(0);
}

void	child_trapper_main(int i,int listenfd, int addrlen)
{
	int	connfd;
	socklen_t	clilen;
	struct sockaddr cliaddr;

	zabbix_log( LOG_LEVEL_DEBUG, "In child_trapper_main()");

/*	zabbix_log( LOG_LEVEL_WARNING, "zabbix_trapperd %ld started",(long)getpid());*/
	zabbix_log( LOG_LEVEL_WARNING, "server #%d started [Trapper]", i);

	DBconnect();

	for(;;)
	{
		clilen = addrlen;

		zbx_setproctitle("waiting for connection");

		zabbix_log( LOG_LEVEL_DEBUG, "Before accept()");
		connfd=accept(listenfd,&cliaddr, &clilen);
		zabbix_log( LOG_LEVEL_DEBUG, "After accept()");

		zbx_setproctitle("processing data");

		process_trapper_child(connfd);

		close(connfd);
	}
	DBclose();
}

/*
pid_t	child_trapper_make(int i,int listenfd, int addrlen)
{
	pid_t	pid;

	if((pid = fork()) >0)
	{
		return (pid);
	}

	child_trapper_main(i, listenfd, addrlen);

	return 0;
}*/
