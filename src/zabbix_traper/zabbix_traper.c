#include <stdlib.h>
#include <stdio.h>

#include <unistd.h>
#include <signal.h>

#include <errno.h>
#include <sys/socket.h>
#include <netinet/in.h>
#include <arpa/inet.h>

/* For strtok */
#include <string.h>

/* For config file operations */
#include <sys/types.h>
#include <sys/stat.h>
#include <fcntl.h>

#include <syslog.h>

#include "common.h"
#include "db.h"
#include "functions.h"

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

int	process_data(char *server,char *key, double value)
{
	char	sql[1024];
	int	itemid;
	double	lastvalue;

	DB_RESULT       *result;
	DB_ROW          row;

	sprintf(sql,"select i.itemid,i.lastvalue from items i,hosts h where h.status=0 and h.hostid=i.hostid and h.host='%s' and i.key_='%s' and i.status=2;",server,key);
	DBexecute(sql);
	 
	result = DBget_result();
	if(result==NULL)
	{
		DBfree_result(result);
		return  FAIL;
	}
	if(DBnum_rows(result)==0)
	{
		DBfree_result(result);
		return  FAIL;
	}
	row = DBfetch_row(result);
	if( row[0] == NULL )
	{
		DBfree_result(result);
		return  FAIL;
	}

	itemid=atoi(row[0]);

	sprintf(sql,"insert into history (itemid,clock,value) values (%d,unix_timestamp(),%g);",itemid,value);
	DBexecute(sql);

	if(NULL == row[1])
	{
		sprintf(sql,"update items set lastvalue=%g,lastclock=unix_timestamp() where itemid=%d;",value,itemid);
	}
	else
	{
		lastvalue=atof(row[1]);
		sprintf(sql,"update items set prevvalue=%g,lastvalue=%g,lastclock=unix_timestamp() where itemid=%d;",lastvalue,value,itemid);
	}

	DBexecute(sql);

	update_functions( itemid );
 
	DBfree_result(result);

	return SUCCEED;
}

int	main()
{
	char	*s,*p;
	char	*server,*key,*value_string;
	double	value;

	int	ret=SUCCEED;


//	if(check_security() == FAIL)
//	{
//		exit(FAIL);
//	}

	signal( SIGINT,  signal_handler );
	signal( SIGQUIT, signal_handler );
	signal( SIGTERM, signal_handler );
	signal( SIGALRM, signal_handler );

	s=(char *) malloc( 1024 );

	alarm(TRAPER_TIMEOUT);

	openlog("zabbix_traper",LOG_PID,LOG_USER);
	//	ret=setlogmask(LOG_UPTO(LOG_DEBUG));
	ret=setlogmask(LOG_UPTO(LOG_WARNING));
	
	fgets(s,1024,stdin);
	for( p=s+strlen(s)-1; p>s && ( *p=='\r' || *p =='\n' || *p == ' ' ); --p );
	p[1]=0;

	server=(char *)strtok(s,":");
	if(NULL == server)
	{
		return FAIL;
	}

	key=(char *)strtok(NULL,":");
	if(NULL == key)
	{
		return FAIL;
	}

	value_string=(char *)strtok(NULL,":");
	if(NULL == value_string)
	{
		return FAIL;
	}
	value=atof(value_string);


	DBconnect();

	ret=process_data(server,key,value);

	alarm(0);

	if(SUCCEED == ret)
	{
		printf("OK\n");
	}

	free(s);

	return ret;
}
