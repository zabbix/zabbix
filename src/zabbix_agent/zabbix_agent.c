#include "config.h"

#include <stdlib.h>
#include <stdio.h>

#include <unistd.h>
#include <signal.h>

#include <errno.h>
#include <sys/socket.h>
#include <netinet/in.h>
#include <arpa/inet.h>

/* For config file operations */
#include <sys/types.h>
#include <sys/stat.h>
#include <fcntl.h>

#include "common.h"
#include "sysinfo.h"
#include "zabbix_agent.h"

COMMAND	commands[]=
	{
	{"memory[total]"		,TOTALMEM, 0},
	{"memory[shared]"		,SHAREDMEM, 0},
	{"memory[buffers]"		,BUFFERSMEM, 0},
	{"memory[cached]"		,CACHEDMEM, 0},
	{"memory[free]"			,FREEMEM, 0},

	{"diskfree[/]"			,DF, "/"},
	{"diskfree[/opt]"		,DF, "/opt"},
	{"diskfree[/tmp]"		,DF, "/tmp"},
	{"diskfree[/usr]"		,DF, "/usr"},
	{"diskfree[/home]"		,DF, "/home"},
	{"diskfree[/var]"		,DF, "/var"},

	{"inodefree[/]"			,INODE, "/"},
	{"inodefree[/opt]"		,INODE, "/opt"},
	{"inodefree[/tmp]"		,INODE, "/tmp"},
	{"inodefree[/usr]"		,INODE, "/usr"},
	{"inodefree[/home]"		,INODE, "/home"},
	{"inodefree[/var]"		,INODE, "/var"},

	{"cksum[/etc/inetd_conf]"	,EXECUTE, "cksum /etc/inetd.conf |cut -f1 -d' '"},
	{"cksum[/vmlinuz]"		,EXECUTE, "cksum /vmlinuz |cut -f1 -d' '"},
	{"cksum[/etc/passwd]"		,EXECUTE, "cksum /etc/passwd |cut -f1 -d' '"},

	{"filesize[/var/log/syslog]"	,FILESIZE, "/var/log/syslog"},

	{"swap[free]"			,SWAPFREE, 0},
	{"swap[total]"			,SWAPTOTAL, 0},
/*
  	All these perameters require more than 1 second to retrieve.

  	{"swap[in]"			,EXECUTE, "vmstat -n 1 2|tail -1|cut -b37-40"},
	{"swap[out]"			,EXECUTE, "vmstat -n 1 2|tail -1|cut -b41-44"},

	{"io[in]"			,EXECUTE, "vmstat -n 1 2|tail -1|cut -b45-50"},
	{"io[out]"			,EXECUTE, "vmstat -n 1 2|tail -1|cut -b51-56"},

	{"system[interrupts]"		,EXECUTE, "vmstat -n 1 2|tail -1|cut -b57-61"},
	{"system[switches]"		,EXECUTE, "vmstat -n 1 2|tail -1|cut -b62-67"},*/
	{"system[procload]"		,PROCLOAD, 0},
	{"system[procload5]"		,PROCLOAD5, 0},
	{"system[procload15]"		,PROCLOAD15, 0},
	{"system[proccount]"		,EXECUTE, "echo /proc/[0-9]*|wc -w"},
	{"system[procrunning]"		,EXECUTE, "cat /proc/loadavg|cut -f1 -d'/'|cut -f4 -d' '"},
	{"system[uptime]"		,UPTIME, 0},
	{"system[users]"		,EXECUTE, "who|wc -l"},

	{"ping"				,PING, 0},
	{"tcp_count"			,EXECUTE, "netstat -tn|grep EST|wc -l"},

	{"net[listen_21]"		,EXECUTE, "netstat -ln|grep tcp|grep -v grep|grep ':21 '|wc -l"},
	{"net[listen_22]"		,EXECUTE, "netstat -ln|grep tcp|grep -v grep|grep ':22 '|wc -l"},
	{"net[listen_23]"		,EXECUTE, "netstat -ln|grep tcp|grep -v grep|grep ':23 '|wc -l"},
	{"net[listen_25]"		,EXECUTE, "netstat -ln|grep tcp|grep -v grep|grep ':25 '|wc -l"},
	{"net[listen_80]"		,EXECUTE, "netstat -ln|grep tcp|grep -v grep|grep ':80 '|wc -l"},
	{"net[listen_110]"		,EXECUTE, "netstat -ln|grep tcp|grep -v grep|grep ':110 '|wc -l"},
	{"net[listen_143]"		,EXECUTE, "netstat -ln|grep tcp|grep -v grep|grep ':143 '|wc -l"},
	{0				,0}
	};

void	signal_handler( int sig )
{
	if( SIGALRM == sig )
	{
		signal( SIGALRM, signal_handler );
 
//		fprintf(stderr,"Timeout while executing operation.");
	}
 
	if( SIGQUIT == sig || SIGINT == sig || SIGTERM == sig )
	{
//		fprintf(stderr,"\nGot QUIT or INT or TERM signal. Exiting..." );
	}
	exit( FAIL );
}

int	check_security(void)
{
	char	*sname;
	char	*config;
	struct	sockaddr_in name;
	int	i;
	int	file;

	if(getpeername(0,  (struct sockaddr *)&name, (size_t *)&i) == 0)
	{
		config=(char *)malloc(16);

		file=open("/etc/zabbix/zabbix_agent.conf",O_RDONLY);
		if(file == -1)
		{
			return FAIL;
		}
		i=read(file, config, 16);
		config[i-1]=0;
		close(file);

		i=sizeof(struct sockaddr_in);

		sname=inet_ntoa(name.sin_addr);
		if(strcmp(sname,config)!=0)
		{
			return	FAIL;
		}
	}
	return	SUCCEED;
}

int	main()
{
	char	*s,*p;
	float	result;
	int	i;
	float	(*function)();
	char	*parameter = NULL;

	if(check_security() == FAIL)
	{
		exit(FAIL);
	}

	signal( SIGINT,  signal_handler );
	signal( SIGQUIT, signal_handler );
	signal( SIGTERM, signal_handler );
	signal( SIGALRM, signal_handler );

	alarm(AGENT_TIMEOUT);

	s=(char *) malloc( 1024 );

	fgets(s,1024,stdin);

	for( p=s+strlen(s)-1; p>s && ( *p=='\r' || *p =='\n' || *p == ' ' ); --p );
	p[1]=0;
	
	i=0;
	for(;;)
	{
		if( commands[i].key == 0)
		{
			function=0;
			break;
		}
		if( strcmp(commands[i].key,s) == 0)
		{
			function=commands[i].function;
			parameter=commands[i].parameter;
			break;
		}	
		i++;
	}

	if( function !=0 )
	{
		result=function(parameter);
		if( result == FAIL )
		{
			printf("%d\n",NOTSUPPORTED);
		}
		else
		{
			printf("%f",result);
		}
	}
	else
	{
		printf("%d\n",NOTSUPPORTED);
	}
	fflush(stdout);

	free(s);
	alarm(0);

	return SUCCEED;
}
