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

#define NUM	256

#define ZBXPOOL struct zbxpool_type

ZBXPOOL
{
	char    ip[128];
	int     port;
	int	socket;
	int	status; /* 0 - not connected, 1 - connected, 2 - wrote data */
};

ZBXPOOL	pool[NUM];

int	s;

struct pollfd poll_cli[NUM];

void	init_pool()
{
	int i;

	for(i=0;i<NUM;i++)
	{
		pool[i].status=0;
	}
}

void	wait_connect()
{
	int len;
	char c[1024];
	int retval,i,j;

	int READ=0;

	printf("Waiting for connect\n");


	for(;;)
	{
/*		printf("To process [%d]\n", NUM-READ);
		printf("-----------------------\n");
		for(j=0;j<NUM-READ;j++)
		{
			printf("[%d] fd [%d] revents [%X]\n",j,poll_cli[j].fd,poll_cli[j].revents);
		}
		printf("-----------------------\n");
*/
		if(NUM-READ<=0) break;
		retval = poll(poll_cli,NUM-READ,-1);

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

		for(i=0;i<NUM-READ;i++)
		{
/*			printf("[%d] remote socket [%X]\n",i,poll_cli[i].revents); */
			if ((poll_cli[i].revents&POLLHUP)==POLLHUP)
			{
				printf("[%d] remote socket has closed\n",i);
				return;
			} 
			if ((poll_cli[i].revents&POLLNVAL)==POLLNVAL)
			{
				memmove(&poll_cli[i], &poll_cli[i+1],(NUM-READ-i-1)*sizeof(struct pollfd));
				READ++;
/*				for(j=i;j<NUM-READ;j++)
				{
					memmove(&poll_cli[j], &poll_cli[j+1],sizeof(struct pollfd));
					poll_cli[j].fd=poll_cli[j+1].fd;
					poll_cli[j].events=poll_cli[j+1].events;
					poll_cli[j].revents=poll_cli[j+1].revents;
				}
*/
				break;
			} 
			if ((poll_cli[i].revents&POLLERR)==POLLERR)
			{
				printf("[%d] remote socket has error\n",i);
				return;
			} 
			if ((poll_cli[i].revents&POLLIN)==POLLIN)
			{
				printf("[%d] remote socket has data\n",i);
				memset(c,0,1024);
				len=read(poll_cli[i].fd, c, 1024);
				printf("RESULT_STR [%d] [%s]\n", len, c);
				if(len == -1)
				{
					perror("read");
					exit(-1);
				}
			
				if(len == 0)
				{
					printf("Read 0 bytes\n");
					exit(-1);
				}
				close(poll_cli[i].fd);
				poll_cli[i].events=-1;
				break;
			}
			if ((poll_cli[i].revents&POLLOUT)==POLLOUT)
			{
				printf("[%d] remote socket ready for writing\n",i);
				zbx_snprintf(c, sizeof(c), "%s\n", "system.uptime\n");
				if( write(poll_cli[i].fd,c,strlen(c)) == -1 )
				{
					perror("write");
					exit(-1);
				} 
				poll_cli[i].events=POLLIN;
				break;
			} 
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

	struct linger ling;
	int	retval;

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
				printf("Connection in progress\n");
			}
			else
			{
				perror("connect");
				exit(-1);
			}
		}
		poll_cli[i].fd = s;
		poll_cli[i].events = POLLOUT;
	}

	wait_connect();
}
