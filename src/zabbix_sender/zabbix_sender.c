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
#include "zbxgetopt.h"

char *progname = NULL;
char title_message[] = "ZABBIX send";
char usage_message[] = "[-vh] {-zpsko | -i}";

#ifdef HAVE_GETOPT_LONG
char *help_message[] = {
	"Options:",
	"  -z --zabbix-server <zabbix_server>	Hostname or IP address of ZABBIX Server.",
	"  -p --port <zabbix_server_port>	Specify port number of server trapper running on the server. Default is 10051.",
	"  -s --host <hostname>			Specify hostname or IP address of a host.",
	"  -k --key <key_of_metric>		Specify metric name (key) we want to send.",
	"  -o --value <value>			Specify value of the key.",
	"  -i --input-file <input_file>		Load values from input file.",
	"					Each line of file contains: <zabbix_server> <hostname> <port> <key> <value>.",
	"  -h --help				Give this help.",
	"  -v --version				Display version number.",
        0 /* end of text */
};
#else
char *help_message[] = {
	"Options:",
	"  -z <zabbix_server>		Hostname or IP address of ZABBIX Server.",
	"  -p <zabbix_server_port>	Specify port number of server trapper running on the server. Default is 10051.",
	"  -s <hostname>		Specify hostname or IP address of a host.",
	"  -k <key_of_metric>		Specify metric name (key) we want to send.",
	"  -o <value>			Specify value of the key.",
	"  -i <input_file>		Load values from input file.",
	"				Each line of file contains: <zabbix_server> <hostname> <port> <key> <value>.",
	"  -h 				Give this help.",
	"  -v 				Display version number.",
        0 /* end of text */
};
#endif

struct zbx_option longopts[] =
{
        {"zabbix-server",	1,	NULL,	'z'},
        {"port",		1,	NULL,	'p'},
        {"host",		1,	NULL,	's'},
        {"key",			1,	NULL,	'k'},
        {"value",		1,	NULL,	'o'},
        {"input-file",		1,	NULL,	'i'},
        {"help",        	0,      NULL,	'h'},
        {"version",     	0,      NULL,	'v'},
        {0,0,0,0}
};

static void    zbx_sender_signal_handler( int sig )
{
	if( SIGALRM == sig )
	{
		signal( SIGALRM, zbx_sender_signal_handler );
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
	int	ret=SUCCEED;
	char	line[MAX_STRING_LEN];
	char	port_str[MAX_STRING_LEN]="10051"; // default value
	char	zabbix_server[MAX_STRING_LEN]="";
	char	server[MAX_STRING_LEN]="";
	char	key[MAX_STRING_LEN]="";
	char	value[MAX_STRING_LEN]="";
	char	input_file[MAX_STRING_LEN]="";
	int	port_specified = 0;
	char	*s;
	int ch;
	FILE *in;

	progname = argv[0];

	signal( SIGINT,  zbx_sender_signal_handler );
	signal( SIGQUIT, zbx_sender_signal_handler );
	signal( SIGTERM, zbx_sender_signal_handler );
	signal( SIGALRM, zbx_sender_signal_handler );

	while ((ch = zbx_getopt_long(argc, argv, "z:p:s:k:o:i:hv", longopts, NULL)) != EOF)
	{
		switch (ch) 
		{
			case 'z': if (zbx_optarg) strcpy(zabbix_server, zbx_optarg); break;
			case 'p': if (zbx_optarg) strcpy(port_str, zbx_optarg); port_specified=1; break;
			case 's': if (zbx_optarg) strcpy(server, zbx_optarg); break;
			case 'k': if (zbx_optarg) strcpy(key, zbx_optarg); break;
			case 'o': if (zbx_optarg) strcpy(value, zbx_optarg); break;
			case 'i': if (zbx_optarg) strcpy(input_file, zbx_optarg); break;
			case 'v': version(); return 0;
			case 'h': default: help(); return 0;
		}
	}


	if (zabbix_server[0] && server[0] && key[0] && value[0])
	{
		if (input_file[0]) 
		{
			help(); return FAIL;
		}
		
		alarm(SENDER_TIMEOUT);

		//printf("Run with options: z=%s, p=%s, s=%s, k=%s, o=%s\n", zabbix_server, port_str, server, key, value);
		ret = send_value(zabbix_server, atoi(port_str), server, key, value,"0");

		alarm(0);
	}
/* No parameters are given */	
	else if (input_file[0])
	{
		if (zabbix_server[0] || server[0] || key[0] || value[0] || port_specified) {
			help(); return FAIL;
		}

		in = fopen(input_file, "r");
		if (!in) 
		{
			fprintf(stderr, "%s: no such file.\n", input_file); 
			return FAIL;
		}	

		while(fgets(line, MAX_STRING_LEN, in) != NULL)
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
			//printf("Run with options from file(%s): z=%s, s=%s, p=%s, k=%s, o=%s\n", input_file, zabbix_server, server, port_str, key, value);
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

