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
#include "security.h"
#include "zabbix_agent.h"

#include "log.h"
#include "cfg.h"
#include "stats.h"

#define	LISTENQ 1024


INTERFACE interfaces[128]=
{
	{0}
};

void	collect_stat()
{
	char	*s;
	char	line[MAX_STRING_LEN+1];
	int	i,j;
	int	i1,j1;
	char	a[MAX_STRING_LEN+1];
	char	b[MAX_STRING_LEN+1];
	char	*token;
char	interface[MAX_STRING_LEN+1];


	file=fopen("/proc/net/dev","r");
	if(NULL == file)
	{
		fprintf(stderr, "Cannot open config file [%s] [%m]\n","/proc/net/dev");
		return;
	}
	fileout=fopen("/tmp/zabbix_agentd.tmp","w");

	i=0;
	while(fgets(line,MAX_STRING_LEN,file) != NULL)
	{
		if( (s=strstr(line,":")) == NULL)
			continue;
		strncpy(interface,line,s-line);
		interface[s-line]=0;
		j1=0;
		for(i1=0;i1<strlen(interface);i1++)
		{
			if(interface[i1]!=' ')
			{
				interface[j1++]=interface[i1];
			}
		}
		interface[j1]=0;
		s=strtok(line,":");
		j=0;
		while(s)
		{
			s = strtok(NULL," ");
			if(j==0)
			{
				printf("Received [%s]\n",s);
				fprintf(fileout,"netloadin1[%s] %s\n", interface, s);
			}
			else if(j==8)
			{
				printf("Sent [%s]\n",s);
				fprintf(fileout,"netloadout1[%s] %s\n", interface, s);
			}
			j++;
		}
		i++;
	}
	fclose(file);
	fclose(fileout);
}

void	collect_statistics()
{
	for(;;)
	{
		collect_stat();
		sleep(1);
	}
}
