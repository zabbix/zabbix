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

#include <errno.h>

#include <string.h>

#include "common.h"

/******************************************************************************
 *                                                                            *
 * Function: signal_handler                                                   *
 *                                                                            *
 * Purpose: process signals                                                   *
 *                                                                            *
 * Parameters: sig - signal ID                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void    signal_handler( int sig )
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

/******************************************************************************
 *                                                                            *
 * Function: usage                                                            *
 *                                                                            *
 * Purpose: print information about command line parameters and exit          *
 *                                                                            *
 * Parameters: prog - name of process, normally 'zabbix_get'                  *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void usage(char *prog)
{
	printf("zabbix_get - Communicate with ZABBIX agent %s\n", ZABBIX_VERSION);
	printf("Usage: %s [-h] -s<host name or IP> [-p<port number>] -k<key>\n", prog);
	printf("\nOptions:\n");
	printf("  -p <port number>       Specify port number of agent running on the host. Default is 10050.\n");
	printf("  -s <host name or IP>   Specify host name or IP address of a host.\n");
	printf("  -k <key of metric>     Specify metric name (key) we want to retrieve.\n");
	printf("  -h                     Help\n");
	printf("\nExample: zabbix_get -s127.0.0.1 -p10050 -k\"system[procload]\"\n");
	exit(-1);
}


/******************************************************************************
 *                                                                            *
 * Function: get_value                                                        *
 *                                                                            *
 * Purpose: connect to ZABBIX agent and receive value for given key           *
 *                                                                            *
 * Parameters: server - serv name or IP address                               *
 *             port   - port number                                           *
 *             key    - item's key                                            *
 *                                                                            *
 * Return value: SUCCEED - ok, FAIL - otherwise                               *
 *             value   - retrieved value                                      *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int	get_value(char *server,int port,char *key,char *value)
{
	int	i,s;
	char	tosend[1024];
	struct hostent *hp;

	struct sockaddr_in myaddr_in;
	struct sockaddr_in servaddr_in;

/*	struct linger ling;*/

/*	printf("get_value([%s],[%d],[%s])",server,port,key);*/

	servaddr_in.sin_family=AF_INET;
	hp=gethostbyname(server);

	if(hp==NULL)
	{
		fprintf(stderr, "Error: %s\n", strerror(errno));
		return	FAIL;
	}

	servaddr_in.sin_addr.s_addr=((struct in_addr *)(hp->h_addr))->s_addr;

	servaddr_in.sin_port=htons(port);

	s=socket(AF_INET,SOCK_STREAM,0);
	if(s == -1)
	{
		fprintf(stderr, "Error: %s\n", strerror(errno));
		return	FAIL;
	}

	myaddr_in.sin_family = AF_INET;
	myaddr_in.sin_port=0;
	myaddr_in.sin_addr.s_addr=INADDR_ANY;

	if( connect(s,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)) == -1 )
	{
		fprintf(stderr, "Error: %s\n", strerror(errno));
		close(s);
		return	FAIL;
	}

	snprintf(tosend,sizeof(tosend)-1,"%s\n",key);

	if(write(s,tosend,strlen(tosend)) == -1)
/*	if( sendto(s,tosend,strlen(tosend),0,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)) == -1 )*/
	{
		fprintf(stderr, "Error: %s\n", strerror(errno));
		close(s);
		return	FAIL;
	} 
/*	i=sizeof(struct sockaddr_in);
	i=recvfrom(s,value,1023,0,(struct sockaddr *)&servaddr_in,(socklen_t *)&i);*/
	memset(value,0,MAX_STRING_LEN);
	i=read(s,value, MAX_STRING_LEN-1);
	if(i==-1)
	{
		fprintf(stderr, "Error: %s\n", strerror(errno));
		close(s);
		return	FAIL;
	}

	delete_reol(value);

	if( close(s)!=0 )
	{
		/* Ignore */
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: main                                                             *
 *                                                                            *
 * Purpose: main function                                                     *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int main(int argc, char **argv)
{
	int	port = 10050;
	int	ret=SUCCEED;
	char	value[MAX_STRING_LEN];
	char	*host=NULL;
	char	*key=NULL;
	int	ch;

	/* Parse the command-line. */
	while ((ch = getopt(argc, argv, "k:p:s:h")) != EOF)
	switch ((char) ch) {
		case 'k':
			key = optarg;
			break;
		case 'p':
			port = atoi(optarg);
			break;
		case 's':
			host = optarg;
			break;
		case 'h':
			usage(argv[0]);
			break;
		default:
			usage(argv[0]);
		break;
	}

	if( (host==NULL) || (key==NULL))
	{
		usage(argv[0]);
		ret = FAIL;
	}

	if(ret == SUCCEED)
	{
		signal( SIGINT,  signal_handler );
		signal( SIGQUIT, signal_handler );
		signal( SIGTERM, signal_handler );
		signal( SIGALRM, signal_handler );

		alarm(SENDER_TIMEOUT);

/*	printf("Host [%s] Port [%d] Key [%s]\n",host,port,key);*/

		ret = get_value(host,port,key,value);

		alarm(0);

		if(ret == SUCCEED)
		{
			printf("%s\n",value);
		}
	}

	return ret;
}
