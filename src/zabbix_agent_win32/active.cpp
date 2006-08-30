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

static void	InitMetrics()
{
LOG_FUNC_CALL("In InitMetrics()");
	if(metrics==NULL)
	{
		metrics=(METRIC *)malloc(sizeof(METRIC));
		metrics[0].key=NULL;
	}
LOG_FUNC_CALL("End of InitMetrics()");
}

static void	FreeMetrics(void)
{
	int i;
LOG_FUNC_CALL("In FreeMetrics()");
	for(i=0;;i++)
	{
		if(metrics[i].key == NULL)	break;
		free(metrics[i].key);
		metrics[i].status = ITEM_STATUS_NOTSUPPORTED;
	}
	free(metrics);
LOG_FUNC_CALL("End of FreeMetrics()");
}

void	disable_all_metrics()
{
	int i;
LOG_FUNC_CALL("In disable_all_metrics()");
INIT_CHECK_MEMORY(main);
	for(i=0;;i++)
	{
		if(metrics[i].key == NULL)	break;

		metrics[i].status = ITEM_STATUS_NOTSUPPORTED;
	}
CHECK_MEMORY(main,"disable_all_metrics","end");
LOG_FUNC_CALL("End of disable_all_metrics()");
}

int	get_min_nextcheck()
{
	int i;
	int min=-1;
	int nodata=0;

LOG_FUNC_CALL("In get_min_nextcheck()");
INIT_CHECK_MEMORY(main);

	for(i=0;;i++)
	{
		if(metrics[i].key == NULL)	break;

		if( (metrics[i].status == ITEM_STATUS_ACTIVE) &&
		    ((metrics[i].nextcheck < min) || (min == -1)))
		{
			nodata=1;
			min=metrics[i].nextcheck;
		}
	}

	if(nodata==0)
	{
		min = FAIL;
	}

CHECK_MEMORY(main,"add_check","end");
LOG_FUNC_CALL("End of get_min_nextcheck()");
	return min;
}

void	add_check(char *key, int refresh, int lastlogsize)
{
	int i;

LOG_FUNC_CALL("In add_check()");

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
LOG_FUNC_CALL("End of add_check()");
}

// Return position of Nth delimiter from right size, 0 - otherwise
int strnrchr(char *str, int num, char delim)
{
	int i=0;
	int n=0;

LOG_FUNC_CALL("In strnrchr()");
INIT_CHECK_MEMORY(main);

	for(i=(int)strlen(str)-1;i>=0;i--)
	{
		if(str[i]==delim) n++;
		if(n==num) break;
	}
	if(i==-1) i=0;
CHECK_MEMORY(main,"strnrchr","end");
LOG_FUNC_CALL("End of strnrchr()");
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

LOG_FUNC_CALL("In parse_list_of_checks()");
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

LOG_FUNC_CALL("End of parse_list_of_checks()");
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

LOG_FUNC_CALL("In get_active_checks()");
//	zabbix_log( LOG_LEVEL_DEBUG, "get_active_checks: host[%s] port[%d]", server, port);

	servaddr_in.sin_family=AF_INET;
	hp=gethostbyname(server);

	if(hp==NULL)
	{
//		zabbix_log( LOG_LEVEL_WARNING, "gethostbyname() failed [%s]", hstrerror(h_errno));
//		_snprintf(error, max_error_len,"gethostbyname() failed [%s]", hstrerror(h_errno));
		return	NETWORK_ERROR;
	}

	servaddr_in.sin_addr.s_addr=((struct in_addr *)(hp->h_addr))->s_addr;

	servaddr_in.sin_port=htons((unsigned short)port);

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
				_snprintf(error, max_error_len, "Timeout while connecting to [%s:%d]",server,port);
				WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s",error);
				break;
			case WSAEHOSTUNREACH:
//				zabbix_log( LOG_LEVEL_WARNING, "No route to host [%s:%d]",server,port);
				_snprintf(error, max_error_len,"No route to host [%s:%d]",server,port);
				WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s",error);
				break;
			default:
//				zabbix_log( LOG_LEVEL_WARNING, "Cannot connect to [%s:%d] [%s]",server,port,strerror(errno));
				_snprintf(error, max_error_len,"Cannot connect to [%s:%d] [%s]",server,port,strerror(errno));
				WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s",error);
		} 
		closesocket(s);
		return	NETWORK_ERROR;
	}

	sprintf(c,"%s\n%s\n","ZBX_GET_ACTIVE_CHECKS",confHostname);
//	zabbix_log(LOG_LEVEL_DEBUG, "Sending [%s]", c);

	if( sendto(s,c,(int)strlen(c),0,(struct sockaddr *)&servaddr_in,(int)sizeof(struct sockaddr_in)) == -1 )
//	if( write(s,c,strlen(c)) == -1 )
	{
		switch (errno)
		{
			case WSAETIMEDOUT:
//				zabbix_log( LOG_LEVEL_WARNING, "Timeout while sending data to [%s:%d]",server,port);
				_snprintf(error, max_error_len,"Timeout while sending data to [%s:%d]",server,port);
				WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s",error);

				break;
			default:
//				zabbix_log( LOG_LEVEL_WARNING, "Error while sending data to [%s:%d] [%s]",server,port,strerror(errno));
				_snprintf(error, max_error_len,"Error while sending data to [%s:%d] [%s]",server,port,strerror(errno));
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
						_snprintf(error, max_error_len,"Timeout while receiving data from [%s:%d]",server,port);
						WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s",error);
						break;
				case	WSAECONNRESET:
//						zabbix_log( LOG_LEVEL_WARNING, "Connection reset by peer.");
						_snprintf(error, max_error_len,"Connection reset by peer.");
						WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s",error);
						closesocket(s);
						return	NETWORK_ERROR;
				default:
//						zabbix_log( LOG_LEVEL_WARNING, "Error while receiving data from [%s:%d] [%s]",server,port,strerror(errno));
						_snprintf(error, max_error_len,"Error while receiving data from [%s:%d] [%s]",server,port,strerror(errno));
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

//	LOG_DEBUG_INFO("s","get_active_checks: end");
LOG_FUNC_CALL("End of get_active_checks()");
	return SUCCEED;
}

int	send_value(char *server,int port,char *host, char *key,char *value,char *lastlogsize,
			   char *timestamp, char *source, char *severity)
{
	SOCKET	s;
	char	tosend[4*MAX_STRING_LEN];
	char	result[1024];
	char	tmp[1024];
	struct hostent *hp;
	struct sockaddr_in myaddr_in;
	struct sockaddr_in servaddr_in;
	int	i, ret = SUCCEED;

LOG_FUNC_CALL("In send_value()");
INIT_CHECK_MEMORY(main);

//LOG_DEBUG_INFO("s","send_value: start");
//LOG_DEBUG_INFO("s","send_value: key");
//LOG_DEBUG_INFO("s",key);
//LOG_DEBUG_INFO("s","send_value: value");
//LOG_DEBUG_INFO("s",value);


	servaddr_in.sin_family=AF_INET;
	hp=gethostbyname(server);

	if(hp==NULL)
	{
		sprintf(tmp,"gethostbyname() failed");
		WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s",tmp);
		ret = FAIL;
		goto lbl_End;
	}

	servaddr_in.sin_addr.s_addr=((struct in_addr *)(hp->h_addr))->s_addr;

	servaddr_in.sin_port=htons((unsigned short)port);

	s=socket(AF_INET,SOCK_STREAM,0);
	if(s == -1)
	{
//		zabbix_log( LOG_LEVEL_WARNING, "Error in socket() [%s:%d] [%s]",server,port, strerror(errno));
		sprintf(tmp,"Error in socket()");
	WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s",tmp);
		ret = FAIL;
		goto lbl_End;
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
		ret = FAIL;
		goto lbl_End;
	}
//	LOG_DEBUG_INFO("s","send_value: 1");
	comms_create_request(host,key,value,lastlogsize,timestamp,source,severity,tosend,sizeof(tosend)-1);

//	i=strlen(tosend);
//	LOG_DEBUG_INFO("d",i);

//	LOG_DEBUG_INFO("s",tosend);

//	LOG_DEBUG_INFO("s","send_value: 2");
//	sprintf(tosend,"%s:%s\n",shortname,value);

//	LOG_DEBUG_INFO("s",tosend);

	if( sendto(s,tosend,(int)strlen(tosend),0,(struct sockaddr *)&servaddr_in,(int)sizeof(struct sockaddr_in)) == -1 )
	{
//		zabbix_log( LOG_LEVEL_WARNING, "Error in sendto() [%s:%d] [%s]",server, port, strerror(errno));
sprintf(tmp,"Error in sendto()");
	WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s",tmp);

		closesocket(s);
		ret = FAIL;
		goto lbl_End;
	} 
//	LOG_DEBUG_INFO("s","send_value: 3");
	i=sizeof(struct sockaddr_in);
/*	i=recvfrom(s,result,1023,0,(struct sockaddr *)&servaddr_in,(size_t *)&i);*/
	i=recvfrom(s,result,1023,0,(struct sockaddr *)&servaddr_in,(int *)&i);
//	LOG_DEBUG_INFO("s","send_value: 4");
	if(s==-1)
	{
//		zabbix_log( LOG_LEVEL_WARNING, "Error in recvfrom() [%s:%d] [%s]",server,port, strerror(errno));
	sprintf(tmp,"Error in recvfrom()");
	WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s",tmp);

		closesocket(s);
		ret = FAIL;
		goto lbl_End;
	}

//	LOG_DEBUG_INFO("s","send_value: 5");
	result[i-1]=0;
//	LOG_DEBUG_INFO("s","send_value: 6");

	if(strcmp(result,"OK") == 0)
	{
//		zabbix_log( LOG_LEVEL_DEBUG, "OK");
//	LOG_DEBUG_INFO("s","send result = OK");
	}
	else
	{
//		zabbix_log( LOG_LEVEL_DEBUG, "NOT OK [%s]", shortname);
//	LOG_DEBUG_INFO("s","send result = NOT OK");
	}
 
	if( closesocket(s)!=0 )
	{
//		zabbix_log( LOG_LEVEL_WARNING, "Error in close() [%s] [%s]",server, strerror(errno));
	}

//	LOG_DEBUG_INFO("s","send_value: end");

lbl_End:

CHECK_MEMORY(main, "send_value", "end");
LOG_FUNC_CALL("End of send_value()");
	return ret;
}

int	process_active_checks(char *server, int port)
{
	REQUEST	rq;
	char	value[MAX_STRING_LEN];
	char	lastlogsize[MAX_STRING_LEN];
	char	timestamp[MAX_STRING_LEN];
	char	source[MAX_STRING_LEN];
	char	severity[MAX_STRING_LEN];

	int	i, now, count;
	int	ret = SUCCEED;

	char	c[MAX_STRING_LEN];
	char	*filename;

LOG_FUNC_CALL("In process_active_checks()");
INIT_CHECK_MEMORY(main);

	now = (int)time(NULL);
	for(i=0;;i++)
	{
		if(metrics[i].key == NULL)			break;
		if(metrics[i].nextcheck>now)			continue;
		if(metrics[i].status!=ITEM_STATUS_ACTIVE)	continue;

//		LOG_DEBUG_INFO("s",metrics[i].key);
		/* Special processing for log files */
		if(strncmp(metrics[i].key,"log[",4) == 0)
		{
			timestamp[0]=0;
			source[0]=0;
			severity[0]=0;
			strscpy(c,metrics[i].key);
			filename=strtok(c,"[]");
			filename=strtok(NULL,"[]");

			count=0;
			while(process_log(filename,&metrics[i].lastlogsize,value) == 0)
			{
//LOG_DEBUG_INFO("s","Active check: log[*]");
//LOG_DEBUG_INFO("s",filename);
//LOG_DEBUG_INFO("s","Active check: value");
//LOG_DEBUG_INFO("s",value);
				sprintf(lastlogsize,"%d",metrics[i].lastlogsize);
				if(send_value(server,port,confHostname,metrics[i].key, value, lastlogsize,timestamp,source,severity) == FAIL)
				{
					ret = FAIL;
//					zabbix_log( LOG_LEVEL_WARNING, "Can't send value to server for active check [%s]", metrics[i].key);
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
			while(process_eventlog(filename,&metrics[i].lastlogsize, timestamp, source, severity, value) == 0)
			{
//				sprintf(shortname, "%s:%s",confHostname,metrics[i].key);
//				zabbix_log( LOG_LEVEL_DEBUG, "%s",shortname);

//				LOG_DEBUG_INFO("s","pac:in loop()");
//				LOG_DEBUG_INFO("s","pac: metrics[i].key");
//				LOG_DEBUG_INFO("s",metrics[i].key);
//				LOG_DEBUG_INFO("s","pac: value");
//				LOG_DEBUG_INFO("s",value);

//				LOG_DEBUG_INFO("s","pac:3");
				sprintf(lastlogsize,"%d",metrics[i].lastlogsize);
				if(send_value(server,port,confHostname,metrics[i].key,value,lastlogsize,timestamp,source,severity) == FAIL)
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
		else
		{
			timestamp[0]=0;
			lastlogsize[0]=0;
			source[0]=0;
			severity[0]=0;

			// Init REQUEST
			memset(&rq, 0, sizeof(REQUEST));
			strcpy(rq.cmd,metrics[i].key);

			ProcessCommand(rq.cmd,rq.result);

			ret = send_value(server,port,confHostname,metrics[i].key,rq.result,lastlogsize,timestamp,source,severity) ;

			if(strcmp(value,"ZBX_NOTSUPPORTED\n")==0)
			{
				metrics[i].status=ITEM_STATUS_NOTSUPPORTED;
LOG_DEBUG_INFO("s","Active check is not supported. Disabled");
			}
		}

		metrics[i].nextcheck = (int)time(NULL)+metrics[i].refresh;
	}
CHECK_MEMORY(main, "process_active_checks", "end");
LOG_FUNC_CALL("End of process_active_checks()");
	return ret;
}

void	refresh_metrics(char *server, int port, char *error, int max_error_len)
{
LOG_FUNC_CALL("In refresh_metrics()");
	while(get_active_checks(server, port, error, max_error_len) != SUCCEED)
	{
LOG_DEBUG_INFO("s","Getting list of active checks failed. Will retry after 60 seconds");
#ifdef HAVE_FUNCTION_SETPROCTITLE
		setproctitle("poller [sleeping for %d seconds]", 60*1000);
#endif
		Sleep(60*1000);
	}
LOG_DEBUG_INFO("s","Out of refresh_metrics()");
}

void    ActiveChecksThread(void *)
{
	char	error[MAX_STRING_LEN];
	int	sleeptime, nextcheck;
	int	nextrefresh;

LOG_FUNC_CALL("In ActiveChecksThread()");
INIT_CHECK_MEMORY(main);

	InitMetrics();

	refresh_metrics(confServer, confServerPort, error, MAX_STRING_LEN);
	nextrefresh = (int)time(NULL) + 300;

	for(;;)	
	{
INIT_CHECK_MEMORY(for);
		if(process_active_checks(confServer, confServerPort) == FAIL)
		{
			Sleep(60*1000);
LOG_DEBUG_INFO("s","ActiveChecksThread - sleep 60000 (!!!)");
			continue;
		}

		nextcheck = get_min_nextcheck();
		if(nextcheck == FAIL)       
		{
			sleeptime=60;
LOG_DEBUG_INFO("s","ActiveChecksThread - sleep 60 (1)");
		}
		else
		{
			sleeptime = nextcheck - (int)time(NULL);
			if(sleeptime<0)
			{
				sleeptime=0;
LOG_DEBUG_INFO("s","ActiveChecksThread - sleep 0 (1)");
			}
		}
		if(sleeptime>0)
		{
			if(sleeptime > 60)
			{
				sleeptime = 60;
LOG_DEBUG_INFO("s","ActiveChecksThread - sleep 60 (2)");
			}

			Sleep( sleeptime*1000 );
		}
		else
		{
//			zabbix_log( LOG_LEVEL_DEBUG, "No sleeping" );
		}

		if(time(NULL)>=nextrefresh)
		{
			refresh_metrics(confServer, confServerPort, error, sizeof(error));
			nextrefresh = (int)time(NULL) + 300;
		}
	}
/**/
	FreeMetrics();

CHECK_MEMORY(main, "ActiveChecksThread", "end");
LOG_FUNC_CALL("End of ActiveChecksThread()");

	_endthread();
}
