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

int	get_active_checks(char *server, int port, char *error, int max_error_len)
{
	int	s;
	int	len;
	char	c[MAX_STRING_LEN];

	struct hostent *hp;

	struct sockaddr_in servaddr_in;

	struct linger ling;

	zabbix_log( LOG_LEVEL_DEBUG, "get_active_checks: host[%s] port[%d]", server, port);

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

	if(CONFIG_NOTIMEWAIT == 1)
	{
		ling.l_onoff=1;
		ling.l_linger=0;
		if(setsockopt(s,SOL_SOCKET,SO_LINGER,&ling,sizeof(ling))==-1)
		{
			zabbix_log(LOG_LEVEL_WARNING, "Cannot setsockopt SO_LINGER [%s]", strerror(errno));
		}
	}
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

	snprintf(c,sizeof(c)-1,"%s\n","ZBX_SEND_ACTIVE_ITEMS");
	zabbix_log(LOG_LEVEL_DEBUG, "Sending [%s]", c);
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


	for(;;)
	{
		len=read(s,c,MAX_STRING_LEN);
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
		else
		{
			if(len>0)
			{
				c[len-1]=0;
			}
			if(strcmp(c,"ZBX_EOF") == 0)
			{
/* No more checks. End of file. */
				break;
			}
			// Add new check to the list
			// add_new_check(c);
		}
	}

	if( close(s)!=0 )
	{
		zabbix_log(LOG_LEVEL_WARNING, "Problem with close [%s]", strerror(errno));
	}

	return SUCCEED;
}

void    child_active_main(int i,char *server, int port)
{
	char	error[MAX_STRING_LEN];

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
		sleep(10);
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
