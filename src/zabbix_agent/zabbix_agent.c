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
	{"freemem"	,FREEMEM, 0},
	{"root_free"	,DF, "/"},
	{"opt_free"	,DF, "/opt"},
	{"tmp_free"	,DF, "/tmp"},
	{"usr_free"	,DF, "/usr"},
	{"home_free"	,DF, "/home"},
	{"root_inode"	,INODE, "/"},
	{"opt_inode"	,INODE, "/opt"},
	{"tmp_inode"	,INODE, "/tmp"},
	{"usr_inode"	,INODE, "/usr"},
	{"home_inode"	,INODE, "/home"},
	{"md5sum_inetd"	,EXECUTE, "md5sum /etc/inetd.conf |cut -b0-32|tr 'abcdef' 'ABCDEF'|bc|cut -b0-8"},
	{"md5sum_kernel",EXECUTE, "md5sum /vmlinuz |cut -b0-32|tr 'abcdef' 'ABCDEF'|bc|cut -b0-8"},
	{"md5sum_passwd",EXECUTE, "md5sum /etc/passwd |cut -b0-32|tr 'abcdef' 'ABCDEF'|bc|cut -b0-8"},
	{"mon_history"	,EXECUTE, "echo 'select count(*) from history'|mysql monitor -uroot|tail -1"},
	{"mon_sucker_load"	,EXECUTE, "ps aux|grep mon_sucker|grep -v grep|cut -b16-20"},
	{"mon_alarmer_load"	,EXECUTE, "ps aux|grep mon_alarmer|grep -v grep|cut -b16-20"},
	{"proccount"	,EXECUTE, "cat /proc/loadavg|cut -f2 -d'/'|cut -f1 -d' '"},
	{"ping"		,PING, 0},
	{"procidle"	,EXECUTE, "vmstat 1 1|tail -1|awk {'print $16'}"},
	{"procload"	,PROCLOAD, 0},
	{"procload5"	,PROCLOAD5, 0},
	{"procload15"	,PROCLOAD15, 0},
	{"procrunning"	,EXECUTE, "cat /proc/loadavg|cut -f1 -d'/'|cut -f4 -d' '"},
	{"procsystem"	,EXECUTE, "vmstat 1 1|tail -1|awk {'print $15'}"},
	{"procuser"	,EXECUTE, "vmstat 1 1|tail -1|awk {'print $14'}"},
	{"swapfree"	,SWAPFREE, 0},
	{"syslog_size"	,FILESIZE, "/var/log/syslog"},
	{"tcp_count"	,EXECUTE, "netstat -tn|grep EST|wc -l"},
	{"users"	,EXECUTE, "who|wc -l"},
	{0		,0}
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

	config=(char *)malloc(16);

	file=open("/etc/zabbix/zabbix_agent.conf",O_RDONLY);
	if(file == -1)
	{
//		printf("Open failed");
		return FAIL;
	}
	i=read(file, config, 16);
	config[i-1]=0;
	close(file);

	i=sizeof(struct sockaddr_in);

	if(getpeername(0,  &name, &i) == 0)
	{
//		printf("%d\n",name.sin_port);
		sname=inet_ntoa(name.sin_addr);
//		printf("From:=%s=\n",sname);
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

	s=(char *) malloc( 1024 );

	fgets(s,1024,stdin);

	for( p=s+strlen(s)-1; p>s && ( *p=='\r' || *p =='\n' || *p == ' ' ); --p );
	p[1]=0;
//	printf("=%s=\n",s);
//	s[strlen(s)-2]=0;
	
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
		alarm(AGENT_TIMEOUT);
		result=function(parameter);
		alarm(0);
		printf("%f",result);
	}
	else
	{
		printf("%d\n",NOTSUPPORTED);
	}
	fflush(stdout);

	free(s);
	return SUCCEED;
}
