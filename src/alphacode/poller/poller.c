#include <stdio.h>
#include <unistd.h>
#include <fcntl.h> 
#include <sys/types.h> 
#include <sys/socket.h> 
#include <sys/poll.h> 
#include <netdb.h> 
#include <errno.h> 

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

int	s;
struct pollfd poll_cli[2];

void	wait_connect()
{
	int retval;

	printf("Waiting for connect\n");

	poll_cli[0].fd = s;
	poll_cli[0].events = POLLOUT;

	retval = poll(poll_cli,1,-1);

	if(retval == 0)
	{
		printf("Poll timed out\n");
		exit(-1);
	}
	if(retval == -1)
	{
		perror("Poll\n");
		exit(-1);
	}
	printf("Connected\n");
}

void	wait_read()
{
	int retval;

	printf("Waiting data to read\n");

	poll_cli[0].fd = s;
	poll_cli[0].events = POLLIN;

	retval = poll(poll_cli,1,-1);

	if(retval == 0)
	{
		printf("Poll timed out\n");
		exit(-1);
	}
	if(retval == -1)
	{
		perror("Poll\n");
		exit(-1);
	}
	printf("Data ready\n");
}


int	main()
{
	int	len;
	char	c[1024];
	char	error[1024];
	char	ip[128]="127.0.0.1";
	int	port=10050;

	struct hostent *hp;

	struct sockaddr_in servaddr_in;

	struct linger ling;
	int	retval;


	servaddr_in.sin_family=AF_INET;
	hp=gethostbyname(ip);

	if(hp==NULL)
	{
		perror("gethostbyname() failed");
	}

	servaddr_in.sin_addr.s_addr=((struct in_addr *)(hp->h_addr))->s_addr;

	servaddr_in.sin_port=htons(port);

	s=socket(AF_INET,SOCK_STREAM,0);

	if(s == -1)
	{
		perror("socket() failed");
	}

	if(fcntl(s, F_SETFL, O_NONBLOCK) == -1)
	{
		perror("fcntl() failed\n");
		exit(-1);
	}
 
	retval = connect(s,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in));
	if(retval == 0)
	{
		printf("Socket connected immediately");
	}
	else if(retval == -1)
	{
		if(errno == EINPROGRESS)
		{
			printf("Connection in progress\n");
		}
		else
		{
			perror("connect");
		exit(-1);
		}
	}
	wait_connect();

	snprintf(c, 1024 - 1, "%s\n", "system.uptime.error\n");
	printf("Before write\n");
	if( write(s,c,strlen(c)) == -1 )
	{
		perror("write");
		exit(-1);
	} 
	printf("After write [%d]\n",s);

	wait_read();

	memset(c,0,1024);
	len=read(s, c, 1024);
	if(len == -1)
	{
		perror("read");
		exit(-1);
	}

	if(len == 0)
	{
		printf("Read on bytes\n");
		exit(-1);
	}

	if( close(s)!=0 )
	{
		perror("close");
		exit(-1);
	}
	printf("RESULT_STR [%d] [%c]\n", len, c);
}
