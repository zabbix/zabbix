#include <stdio.h>
#include <unistd.h>
#include <fcntl.h> 
#include <sys/types.h> 
#include <sys/socket.h> 
#include <sys/epoll.h> 
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

#define NUM	1000

int	s;

int 	epfd;
struct epoll_event ev; 
struct epoll_event *events; 

void	wait_connect()
{
	int len;
	char c[1024];
	int retval,i,j;

	printf("Waiting for connect\n");

	for(;;)
	{
		
		printf("epfd [%d]\n", epfd);
		printf("NUM [%d]\n", NUM);
		events=malloc(NUM*sizeof(struct epoll_event));
		retval = epoll_wait(epfd, events, NUM, -1);
		if(retval == -1)
		{
			perror("epoll_wait");
			printf("Retval [%d]\n", errno);
			exit(-1);
		}
		printf("Retval [%d]\n", retval);
		sleep(1);
		continue;

		for(i=0;i<retval;i++)
		{
			printf("[%d] fd [%X]\n",i,events[i].data.fd);
		}
	}
}

int	main()
{
	int	len,i;
	char	c[1024];
	char	error[1024];
	char	ip[128]="127.0.0.1";
	int	port=10050;

	struct hostent *hp;

	struct sockaddr_in servaddr_in;

	int	retval;


	epfd = epoll_create(NUM); 
	if(!epfd)
	{
		perror("epoll_create\n");
		exit(1);
	} 

	for(i=0;i<NUM;i++)
	{
		servaddr_in.sin_family=AF_INET;
		if(NULL == (hp = zbx_gethost(ip)))
		{
			perror("gethost() failed");
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
/*				printf("Connection in progress\n");*/
			}
			else
			{
				perror("connect");
				exit(-1);
			}
		}

/*		if(fcntl(s, F_SETFL, O_NONBLOCK) == -1)
		{
			perror("fcntl() failed\n");
			exit(-1);
		}*/

		ev.events = EPOLLIN | EPOLLERR | EPOLLHUP | EPOLLOUT;
		ev.data.fd = s;
		if(epoll_ctl(epfd, EPOLL_CTL_ADD, s, &ev) < 0)
		{
			perror("epoll_ctl, adding listenfd\n");
			exit(1);
		} 
/*		printf("epoll_ctl ok fd [%d]\n", s); */
	}

	wait_connect();
}
