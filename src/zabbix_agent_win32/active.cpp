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

//#include "config.h"

/*#include <netdb.h>

#include <stdlib.h>
#include <stdio.h>

#include <unistd.h>
#include <signal.h>

#include <time.h>

#include <errno.h>
#include <sys/socket.h>
#include <netinet/in.h>
#include <arpa/inet.h>*/

/* No warning for bzero */
#include <string.h>
#include <time.h>

/*#include <sys/types.h>
#include <sys/stat.h>
#include <fcntl.h>*/

/* For setpriority */
/*#include <sys/time.h>
#include <sys/resource.h>
*/

/* Required for getpwuid */
/*#include <pwd.h>

#include "common.h"
#include "sysinfo.h"

#include "pid.h"
#include "log.h"
#include "cfg.h"
#include "stats.h"
#include "active.h"
#include "logfiles.h"
*/

#include "zabbixw32.h"

#define MAX_LINES_PER_SECOND	10

#define METRIC struct metric_type
METRIC
{
	char	*key;
	int	refresh;
	int	nextcheck;
	int	status;
	int	lastlogsize;
};




METRIC	*metrics=NULL;

void	init_list()
{
	if(metrics==NULL)
	{
		metrics=(METRIC *)malloc(sizeof(METRIC));
		metrics[0].key=NULL;
	}
}

void	disable_all_metrics()
{
	int i;

	for(i=0;;i++)
	{
		if(metrics[i].key == NULL)	break;

		metrics[i].status = ITEM_STATUS_NOTSUPPORTED;
	}
}

int	get_min_nextcheck()
{
	int i;
	int min=-1;
	int nodata=0;

	for(i=0;;i++)
	{
		if(metrics[i].key == NULL)	break;

		nodata=1;
		if( (metrics[i].status == ITEM_STATUS_ACTIVE) &&
		    ((metrics[i].nextcheck < min) || (min == -1)))
		{
			min=metrics[i].nextcheck;
		}
	}

	if(nodata==0)
	{
		return	FAIL;
	}
	return min;
}

void	add_check(char *key, int refresh, int lastlogsize)
{
	int i;

	for(i=0;;i++)
	{
		if(metrics[i].key == NULL)
		{
			metrics[i].key=strdup(key);
			metrics[i].refresh=refresh;
			metrics[i].nextcheck=0;
			metrics[i].status=ITEM_STATUS_ACTIVE;
			metrics[i].lastlogsize=lastlogsize;

			metrics=(METRIC *)realloc(metrics,(i+2)*sizeof(METRIC));
			metrics[i+1].key=NULL;
			break;
		}
		else if(strcmp(metrics[i].key,key)==0)
		{
			if(metrics[i].refresh!=refresh)
			{
				metrics[i].nextcheck=0;
			}
			metrics[i].refresh=refresh;
			metrics[i].lastlogsize=lastlogsize;
			metrics[i].status=ITEM_STATUS_ACTIVE;
			break;
		}
	}
}

// Return position of Nth delimiter from right size, 0 - otherwise
int strnrchr(char *str, int num, char delim)
{
	int i=0;
	int n=0;

	for(i=strlen(str)-1;i>=0;i--)
	{
		if(str[i]==delim) n++;
		if(n==num) break;
	}
	if(i==-1) i=0;
	return i;
}

/* Parse list of active checks received from server */
int	parse_list_of_checks(char *str)
{
	char line[MAX_BUF_LEN];
	char 
		key[MAX_STRING_LEN],
		refresh[MAX_STRING_LEN],
		lastlogsize[MAX_STRING_LEN];
	//char *s1, *s2;
	char *pos;
	char *str_copy;
	int p1,p2;

	disable_all_metrics();

	str_copy=str;

	pos=strchr(str_copy,'\n');
	//line=(char *)strtok(str,"\n");
	
    while(pos!=NULL)
	{
		memset(line,0,sizeof(line));
	strncpy(line,str_copy,pos-str_copy);

		if(strcmp(line,"ZBX_EOF")==0)	break;

//		sscanf(line,"%s:%d:%d",key,&r,&l);

		p2=strnrchr(line,1,':');
		p1=strnrchr(line,2,':');
				
		memset(key,0,sizeof(key));
		memset(refresh,0,sizeof(refresh));
		memset(lastlogsize,0,sizeof(lastlogsize));

		strcpy(lastlogsize,line+p2+1);
//		WriteLog(MSG_SOCKET_ERROR,EVENTLOG_ERROR_TYPE,"s",lastlogsize);
		
		strncpy(key,line,p1);
//		WriteLog(MSG_SOCKET_ERROR,EVENTLOG_ERROR_TYPE,"s",key);
		
		strncpy(refresh,line+p1+1,p2-p1-1);
//		WriteLog(MSG_SOCKET_ERROR,EVENTLOG_ERROR_TYPE,"s",refresh);
		
//		key=(char *)strtok(line,":");
//		refresh=(char *)strtok(NULL,":");
//		lastlogsize=(char *)strtok(NULL,":");
		
		add_check(key, atoi(refresh), atoi(lastlogsize));
//		add_check(key, r, l);

//		line=(char *)strtok(NULL,"\n");
		str_copy=pos+1;
		pos=strchr(str_copy,'\n');
	}

	return SUCCEED;
}

int	get_active_checks(char *server, int port, char *error, int max_error_len)
{
//	int	s;
	SOCKET s;
	int	len,amount_read;
	char	c[MAX_BUF_LEN];

	struct hostent *hp;

	struct sockaddr_in servaddr_in;

//	zabbix_log( LOG_LEVEL_DEBUG, "get_active_checks: host[%s] port[%d]", server, port);

	servaddr_in.sin_family=AF_INET;
	hp=gethostbyname(server);

	if(hp==NULL)
	{
//		zabbix_log( LOG_LEVEL_WARNING, "gethostbyname() failed [%s]", hstrerror(h_errno));
//		sprintf(error,"gethostbyname() failed [%s]", hstrerror(h_errno));

		return	NETWORK_ERROR;
	}

	servaddr_in.sin_addr.s_addr=((struct in_addr *)(hp->h_addr))->s_addr;

	servaddr_in.sin_port=htons(port);

	s=socket(AF_INET,SOCK_STREAM,0);

	if(s == -1)
	{
//		zabbix_log(LOG_LEVEL_WARNING, "Cannot create socket [%s]",
//				strerror(errno));
		return	FAIL;
	}
	if( connect(s,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)) == -1 )
	{
		switch (errno)
		{
			case WSAETIMEDOUT:
//				zabbix_log( LOG_LEVEL_WARNING, "Timeout while connecting to [%s:%d]",server,port);
				sprintf(error,"Timeout while connecting to [%s:%d]",server,port);
				WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s",error);
				break;
			case WSAEHOSTUNREACH:
//				zabbix_log( LOG_LEVEL_WARNING, "No route to host [%s:%d]",server,port);
				sprintf(error,"No route to host [%s:%d]",server,port);
				WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s",error);
				break;
			default:
//				zabbix_log( LOG_LEVEL_WARNING, "Cannot connect to [%s:%d] [%s]",server,port,strerror(errno));
				sprintf(error,"Cannot connect to [%s:%d] [%s]",server,port,strerror(errno));
				WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s",error);
		} 
		closesocket(s);
		return	NETWORK_ERROR;
	}

	sprintf(c,"%s\n%s\n","ZBX_GET_ACTIVE_CHECKS",confHostname);
//	zabbix_log(LOG_LEVEL_DEBUG, "Sending [%s]", c);
	if( sendto(s,c,strlen(c),0,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)) == -1 )
//	if( write(s,c,strlen(c)) == -1 )
	{
		switch (errno)
		{
			case WSAETIMEDOUT:
//				zabbix_log( LOG_LEVEL_WARNING, "Timeout while sending data to [%s:%d]",server,port);
				sprintf(error,"Timeout while sending data to [%s:%d]",server,port);
				WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s",error);

				break;
			default:
//				zabbix_log( LOG_LEVEL_WARNING, "Error while sending data to [%s:%d] [%s]",server,port,strerror(errno));
				sprintf(error,"Error while sending data to [%s:%d] [%s]",server,port,strerror(errno));
				WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s",error);
		} 
		closesocket(s);
		return	FAIL;
	} 
	
	memset(c,0,MAX_BUF_LEN);

	//zabbix_log(LOG_LEVEL_DEBUG, "Before read");

	amount_read = 0;

	do
	{
		len=sizeof(struct sockaddr_in);
		len=recvfrom(s,c+amount_read,MAX_BUF_LEN-1-amount_read,0,(struct sockaddr *)&servaddr_in,(int *)&len);
		
//		len=read(s,c+amount_read,(MAX_BUF_LEN-1)-amount_read);
		if (len > 0)
		{
			amount_read += len;
		}
		if(len == -1)
		{
			switch (errno)
			{
				case 	WSAETIMEDOUT:
//						zabbix_log( LOG_LEVEL_WARNING, "Timeout while receiving data from [%s:%d]",server,port);
						sprintf(error,"Timeout while receiving data from [%s:%d]",server,port);
						WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s",error);
						break;
				case	WSAECONNRESET:
//						zabbix_log( LOG_LEVEL_WARNING, "Connection reset by peer.");
						sprintf(error,"Connection reset by peer.");
						WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s",error);
						closesocket(s);
						return	NETWORK_ERROR;
				default:
//						zabbix_log( LOG_LEVEL_WARNING, "Error while receiving data from [%s:%d] [%s]",server,port,strerror(errno));
						sprintf(error,"Error while receiving data from [%s:%d] [%s]",server,port,strerror(errno));
						WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s",error);
			} 
			closesocket(s);
			return	FAIL;
		}
	}
	while (len > 0);

/*	while((len=read(s,tmp,MAX_BUF_LEN-1))>0)
	{
		if(len == -1)
		{
			switch (errno)
			{
				case 	WSAETIMEDOUT:
						zabbix_log( LOG_LEVEL_WARNING, "Timeout while receiving data from [%s:%d]",server,port);
						snprintf(error,max_error_len-1,"Timeout while receiving data from [%s:%d]",server,port);
						break;
				case	ECONNRESET:
						zabbix_log( LOG_LEVEL_WARNING, "Connection reset by peer.");
						snprintf(error,max_error_len-1,"Connection reset by peer.");
						close(s);
						return	NETWORK_ERROR;
				default:
						zabbix_log( LOG_LEVEL_WARNING, "Error while receiving data from [%s:%d] [%s]",server,port,strerror(errno));
						snprintf(error,max_error_len-1,"Error while receiving data from [%s:%d] [%s]",server,port,strerror(errno));
			} 
			close(s);
			return	FAIL;
		}
		strncat(c,tmp,len);
	}
	zabbix_log(LOG_LEVEL_DEBUG, "Read [%s]", c);*/

	parse_list_of_checks(c);

	if( closesocket(s)!=0 )
	{
//		zabbix_log(LOG_LEVEL_WARNING, "Problem with close [%s]", strerror(errno));
	}

	return SUCCEED;
}

int	send_value(char *server,int port,char *host, char *key,char *value,char *lastlogsize)
{
	int	i,s;
	char	tosend[1024];
	char	result[1024];
	char	tmp[1024];
	struct hostent *hp;

	struct sockaddr_in myaddr_in;
	struct sockaddr_in servaddr_in;

//	zabbix_log( LOG_LEVEL_DEBUG, "In send_value()");

	servaddr_in.sin_family=AF_INET;
	hp=gethostbyname(server);

	if(hp==NULL)
	{
		sprintf(tmp,"gethostbyname() failed");
	WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s",tmp);
		return	FAIL;
	}

	servaddr_in.sin_addr.s_addr=((struct in_addr *)(hp->h_addr))->s_addr;

	servaddr_in.sin_port=htons(port);

	s=socket(AF_INET,SOCK_STREAM,0);
	if(s == -1)
	{
//		zabbix_log( LOG_LEVEL_WARNING, "Error in socket() [%s:%d] [%s]",server,port, strerror(errno));
		sprintf(tmp,"Error in socket()");
	WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s",tmp);

		return	FAIL;
	}

/*	ling.l_onoff=1;*/
/*	ling.l_linger=0;*/
/*	if(setsockopt(s,SOL_SOCKET,SO_LINGER,&ling,sizeof(ling))==-1)*/
/*	{*/
/* Ignore */
/*	}*/
 
	myaddr_in.sin_family = AF_INET;
	myaddr_in.sin_port=0;
	myaddr_in.sin_addr.s_addr=INADDR_ANY;

	if( connect(s,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)) == -1 )
	{
//		zabbix_log( LOG_LEVEL_WARNING, "Error in connect() [%s:%d] [%s]",server, port, strerror(errno));
		sprintf(tmp,"Error in connect()");
	WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s",tmp);
		closesocket(s);
		return	FAIL;
	}

	comms_create_request(host,key,value,lastlogsize,tosend,sizeof(tosend)-1);
//	sprintf(tosend,"%s:%s\n",shortname,value);

				WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s",tosend);

	if( sendto(s,tosend,strlen(tosend),0,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)) == -1 )
	{
//		zabbix_log( LOG_LEVEL_WARNING, "Error in sendto() [%s:%d] [%s]",server, port, strerror(errno));
sprintf(tmp,"Error in sendto()");
	WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s",tmp);

		closesocket(s);
		return	FAIL;
	} 
	i=sizeof(struct sockaddr_in);
/*	i=recvfrom(s,result,1023,0,(struct sockaddr *)&servaddr_in,(size_t *)&i);*/
	i=recvfrom(s,result,1023,0,(struct sockaddr *)&servaddr_in,(int *)&i);
	if(s==-1)
	{
//		zabbix_log( LOG_LEVEL_WARNING, "Error in recvfrom() [%s:%d] [%s]",server,port, strerror(errno));
sprintf(tmp,"Error in recvfrom()");
	WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s",tmp);

		closesocket(s);
		return	FAIL;
	}

	result[i-1]=0;

	if(strcmp(result,"OK") == 0)
	{
//		zabbix_log( LOG_LEVEL_DEBUG, "OK");
	}
	else
	{
//		zabbix_log( LOG_LEVEL_DEBUG, "NOT OK [%s]", shortname);
	}
 
	if( closesocket(s)!=0 )
	{
//		zabbix_log( LOG_LEVEL_WARNING, "Error in close() [%s] [%s]",server, strerror(errno));
	}

	return SUCCEED;
}

int	process_active_checks(char *server, int port)
{
	   REQUEST rq;
   HANDLE hThread=NULL;
   unsigned int tid;

	char	value[MAX_STRING_LEN];
	char	lastlogsize[MAX_STRING_LEN];
	int	i, now, count;
	int	ret = SUCCEED;

	char	c[MAX_STRING_LEN];
	char	*filename;

	now=time(NULL);

	for(i=0;;i++)
	{
		if(metrics[i].key == NULL)			break;
		if(metrics[i].nextcheck>now)			continue;
		if(metrics[i].status!=ITEM_STATUS_ACTIVE)	continue;

//		WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s",metrics[i].key);
		/* Special processing for log files */
		if(strncmp(metrics[i].key,"log[",4) == 0)
		{
			strscpy(c,metrics[i].key);
			filename=strtok(c,"[]");
			filename=strtok(NULL,"[]");

			count=0;
			while(process_log(filename,&metrics[i].lastlogsize,value) == 0)
			{
//				sprintf(shortname, "%s:%s",confHostname,metrics[i].key);
//				zabbix_log( LOG_LEVEL_DEBUG, "%s",shortname);
//				WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s",shortname);

				sprintf(lastlogsize,"%d",metrics[i].lastlogsize);
				if(send_value(server,port,confHostname,metrics[i].key, value, lastlogsize) == FAIL)
				{
					ret = FAIL;
					break;
				}
				if(strcmp(value,"ZBX_NOTSUPPORTED\n")==0)
				{
					metrics[i].status=ITEM_STATUS_NOTSUPPORTED;
//					zabbix_log( LOG_LEVEL_WARNING, "Active check [%s] is not supported. Disabled.", metrics[i].key);
					break;
				}
				count++;
				/* Do not flood ZABBIX server if file grows too fast */
				if(count >= MAX_LINES_PER_SECOND*metrics[i].refresh)	break;
			}
		}
		/* Special processing for log files */
		else if(strncmp(metrics[i].key,"eventlog[",9) == 0)
		{
			strscpy(c,metrics[i].key);
			filename=strtok(c,"[]");
			filename=strtok(NULL,"[]");

			count=0;
			while(process_eventlog_new(filename,&metrics[i].lastlogsize,value) == 0)
			{
//				sprintf(shortname, "%s:%s",confHostname,metrics[i].key);
//				zabbix_log( LOG_LEVEL_DEBUG, "%s",shortname);

//				WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s","In loop()");
//				WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s",value);

				sprintf(lastlogsize,"%d",metrics[i].lastlogsize);
				if(send_value(server,port,confHostname,metrics[i].key,value,lastlogsize) == FAIL)
				{
					ret = FAIL;
					break;
				}
				if(strcmp(value,"ZBX_NOTSUPPORTED\n")==0)
				{
					metrics[i].status=ITEM_STATUS_NOTSUPPORTED;
//					zabbix_log( LOG_LEVEL_WARNING, "Active check [%s] is not supported. Disabled.", metrics[i].key);
					break;
				}
				count++;
//				WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s","End of loop()");
				/* Do not flood ZABBIX server if file grows too fast */
				if(count >= MAX_LINES_PER_SECOND*metrics[i].refresh)	break;
			}
		}
		else
		{
			lastlogsize[0]=0;
			strcpy(rq.cmd,metrics[i].key);

			   
   hThread=(HANDLE)_beginthreadex(NULL,0,ProcessingThread,(void *)&rq,0,&tid);
   if (WaitForSingleObject(hThread,confTimeout)==WAIT_TIMEOUT)
   {
      strcpy(rq.result,"ZBX_ERROR\n");
      statTimedOutRequests++;
   }

   //send(sock,rq.result,strlen(rq.result),0);

			//process(metrics[i].key, value);

			//sprintf(shortname,"%s:%s",confHostname,metrics[i].key);
//			zabbix_log( LOG_LEVEL_DEBUG, "%s",shortname);
			if(send_value(server,port,confHostname,metrics[i].key,rq.result,lastlogsize) == FAIL)
			{
				ret = FAIL;
				break;
			}

			if(strcmp(value,"ZBX_NOTSUPPORTED\n")==0)
			{
				metrics[i].status=ITEM_STATUS_NOTSUPPORTED;
//				zabbix_log( LOG_LEVEL_WARNING, "Active check [%s] is not supported. Disabled.", metrics[i].key);
			}
		}

		metrics[i].nextcheck=time(NULL)+metrics[i].refresh;
	}
	return ret;
}

void	refresh_metrics(char *server, int port, char *error, int max_error_len)
{
//	zabbix_log( LOG_LEVEL_DEBUG, "In refresh_metrics()");
	while(get_active_checks(server, port, error, sizeof(error)) != SUCCEED)
	{
//		zabbix_log( LOG_LEVEL_WARNING, "Getting list of active checks failed. Will retry after 60 seconds");
#ifdef HAVE_FUNCTION_SETPROCTITLE
		setproctitle("poller [sleeping for %d seconds]", 60*1000);
#endif
		Sleep(60*1000);
	}
}

void    ActiveChecksThread(void *)
{
	char	error[MAX_STRING_LEN];
	int	sleeptime, nextcheck;
	int	nextrefresh;

//	zabbix_log( LOG_LEVEL_WARNING, "zabbix_agentd %ld started",(long)getpid());

//	WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"d",confServerPort);

	init_list();

	refresh_metrics(confServer, confServerPort, error, sizeof(error));
	nextrefresh=time(NULL)+300;

	for(;;)
	{
		if(process_active_checks(confServer, confServerPort) == FAIL)
		{
			Sleep(60*1000);
			continue;
		}
		nextcheck=get_min_nextcheck();
		if( FAIL == nextcheck)
		{
			sleeptime=60;
		}
		else
		{
			sleeptime=nextcheck-time(NULL);
			if(sleeptime<0)
			{
				sleeptime=0;
			}
		}
		if(sleeptime>0)
		{
			if(sleeptime > 60)
			{
				sleeptime = 60;
			}
//			zabbix_log( LOG_LEVEL_DEBUG, "Sleeping for %d seconds",
//					sleeptime );

			Sleep( sleeptime*1000 );
		}
		else
		{
//			zabbix_log( LOG_LEVEL_DEBUG, "No sleeping" );
		}

		if(time(NULL)>=nextrefresh)
		{
			refresh_metrics(confServer, confServerPort, error, sizeof(error));
			nextrefresh=time(NULL)+300;
		}
	}
}
