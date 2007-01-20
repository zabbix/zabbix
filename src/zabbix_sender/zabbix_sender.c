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
#include <unistd.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <netinet/in.h>
#include <netdb.h>

#include <string.h>

/* OpenBSD*/
#ifdef HAVE_SYS_SOCKET_H
	#include <sys/socket.h>
#endif

#include <signal.h>
#include <time.h>

#include "common.h"
#include "comms.h"

char *progname = NULL;
char title_message[] = "ZABBIX send";
char usage_message[] = "[<Zabbix server> <port> <server> <key> <value>]";
char *help_message[] = {
	"",
	"  If no arguments are given, zabbix_sender expects list of parameters",
	"  from standard input.",
	"",
        0 /* end of text */
};


void    signal_handler( int sig )
{
	if( SIGALRM == sig )
	{
		signal( SIGALRM, signal_handler );
		fprintf(stderr,"Timeout while executing operation.\n");
	}
 
	if( SIGQUIT == sig || SIGINT == sig || SIGTERM == sig )
	{
/*		fprintf(stderr,"\nGot QUIT or INT or TERM signal. Exiting..." ); */
	}
	exit( FAIL );
}

static int send_value(char *server,int port,char *hostname, char *key,char *value, char *lastlogsize)
{
	char	tosend[MAX_STRING_LEN];

	char	foo[MAX_STRING_LEN];
	
	zbx_sock_t	sock;
	char	*answer;

	if( FAIL == zbx_tcp_connect(&sock, server, port))
	{
		return 	FAIL;
	}

	foo[0] = '\0';
	comms_create_request(hostname, key, value, lastlogsize, foo, foo, foo, tosend, sizeof(tosend)-1);
	if( FAIL == zbx_tcp_send(&sock, tosend))
	{
		zbx_tcp_close(&sock);
		return 	FAIL;
	}

	if( FAIL == zbx_tcp_recv(&sock, &answer))
	{
		zbx_tcp_close(&sock);
		return 	FAIL;
	}

	if(strcmp(answer,"OK") == 0)
	{
		printf("OK\n");
	}

	zbx_tcp_close(&sock);

	return SUCCEED;
}

int main(int argc, char **argv)
{
	int	port;
	int	ret=SUCCEED;
	char	line[MAX_STRING_LEN];
	char	port_str[MAX_STRING_LEN];
	char	zabbix_server[MAX_STRING_LEN];
	char	server[MAX_STRING_LEN];
	char	key[MAX_STRING_LEN];
	char	value[MAX_STRING_LEN];
	char	*s;

	progname = argv[0];

	signal( SIGINT,  signal_handler );
	signal( SIGQUIT, signal_handler );
	signal( SIGTERM, signal_handler );
	signal( SIGALRM, signal_handler );

	if(argc == 6)
	{
		port=atoi(argv[2]);

		alarm(SENDER_TIMEOUT);

		ret = send_value(argv[1],port,argv[3],argv[4],argv[5],"0");

		alarm(0);
	}
/* No parameters are given */	
	else if(argc == 1)
	{
		while(fgets(line,MAX_STRING_LEN,stdin) != NULL)
		{
			alarm(SENDER_TIMEOUT);
	
			s=(char *)strtok(line," ");
			strscpy(zabbix_server,s);
			s=(char *)strtok(NULL," ");
			strscpy(server,s);
			s=(char *)strtok(NULL," ");
			strscpy(port_str,s);
			s=(char *)strtok(NULL," ");
			strscpy(key,s);
			s=(char *)strtok(NULL," ");
			strscpy(value,s);
			ret = send_value(zabbix_server,atoi(port_str),server,key,value,"0");

			alarm(0);
		}
	}
	else
	{
		help();
		ret = FAIL;
	}

	return ret;
}
