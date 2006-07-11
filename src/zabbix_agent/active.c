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

#include "common.h"
#include "active.h"

#include "cfg.h"
#include "log.h"
#include "sysinfo.h"
#include "logfiles.h"
#include "zbxsock.h"
#include "threads.h"

#if defined(ZABBIX_SERVICE)
#	include "service.h"
#elif defined(ZABBIX_DAEMON) /* ZABBIX_SERVICE */
#	include "daemon.h"
#endif /* ZABBIX_DAEMON */

static ZBX_ACTIVE_METRIC *active_metrics = NULL;

static void	init_active_metrics()
{
	zabbix_log( LOG_LEVEL_DEBUG, "In init_active_metrics()");

	if(NULL == active_metrics)
	{
		active_metrics = calloc(sizeof(ZBX_ACTIVE_METRIC), 1);
	}
	else
	{
		zabbix_log(LOG_LEVEL_WARNING, "Metrics are already initialised.");
	}
}

static void	disable_all_metrics()
{
	int i;

 	zabbix_log( LOG_LEVEL_DEBUG, "In disable_all_metrics()");

	if(NULL == active_metrics) 
	{
		zabbix_log(LOG_LEVEL_DEBUG, "No meters to desabling.");
		return;
	}

	for(i=0; NULL != active_metrics[i].key; i++)
	{
		active_metrics[i].status = ITEM_STATUS_NOTSUPPORTED;
	}
}


static void	free_metrics(void)
{
	int i;

	zabbix_log( LOG_LEVEL_DEBUG, "In free_metrics()");

	if(NULL == active_metrics)
	{
		zabbix_log(LOG_LEVEL_WARNING, "Metrics are already freed.");
		return;
	}

	for(i = 0; NULL != active_metrics[i].key;i++)
	{
		free(active_metrics[i].key);
		active_metrics[i].status = ITEM_STATUS_NOTSUPPORTED;
	}

	free(active_metrics);
	active_metrics = NULL;
}

static int	get_min_nextcheck()
{
	int i;
	int min = -1;

	zabbix_log( LOG_LEVEL_DEBUG, "In get_min_nextcheck()");

	for(i = 0; NULL != active_metrics[i].key; i++)
	{
		if(ITEM_STATUS_ACTIVE == active_metrics[i].status)
			continue;

		if(active_metrics[i].nextcheck < min || ((-1) == min))
			min = active_metrics[i].nextcheck;
	}

	if((-1) == min)
		return	FAIL;

	return min;
}

static void	add_check(char *key, int refresh, int lastlogsize)
{
	int i;

	zabbix_log( LOG_LEVEL_DEBUG, "In add_check('%s', %i, %i)", key, refresh, lastlogsize);

	for(i=0; NULL != active_metrics[i].key; i++)
	{
		if(strcmp(active_metrics[i].key,key) != 0)
			continue;

		/* replace metric */
		if(active_metrics[i].refresh != refresh)
		{
			active_metrics[i].nextcheck = 0;
		}
		active_metrics[i].refresh	= refresh;
		active_metrics[i].lastlogsize	= lastlogsize;
		active_metrics[i].status	= ITEM_STATUS_ACTIVE;

		return;
	}

	/* add new metric */
	active_metrics[i].key		= strdup(key);
	active_metrics[i].refresh	= refresh;
	active_metrics[i].nextcheck	= 0;
	active_metrics[i].status	= ITEM_STATUS_ACTIVE;
	active_metrics[i].lastlogsize	= lastlogsize;

	/* move to the last metric */
	i++;

	/* allocate memory for last metric */
	active_metrics	= realloc(active_metrics, (i+1) * sizeof(ZBX_ACTIVE_METRIC));

	/* inicialize last metric */
	memset(&active_metrics[i], 0, sizeof(ZBX_ACTIVE_METRIC));
}

/******************************************************************************
 *                                                                            *
 * Function: parse_list_of_checks                                             *
 *                                                                            *
 * Purpose: Parse list of active checks received from server                  *
 *                                                                            *
 * Parameters: str - NULL terminated string received from server              *
 *                                                                            *
 * Return value: returns SUCCEED on succesfull parsing,                       *
 *               FAIL on an incoorrect format of string                       *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *    String reprents as "ZBX_EOF" termination list                           *
 *    With '\n' delimeter between elements.                                   *
 *    Each element represents as:                                             *
 *           <key>:<refresh time>:<last log size>                             *
 *                                                                            *
 ******************************************************************************/

static int	parse_list_of_checks(char *str)
{
	char 
		*p = NULL, 
		*pstrend = NULL, 
		*key = NULL, 
		*refresh = NULL, 
		*lastlogsize = NULL;

	zabbix_log( LOG_LEVEL_DEBUG, "In parse_list_of_checks('%s')", str);

	disable_all_metrics();

	while(str)
	{
		pstrend = strchr(str,'\n');
		if(pstrend) *pstrend = '\0'; /* prepare line */

		zabbix_log(LOG_LEVEL_DEBUG, "Parsed [%s]", str);

		if(strcmp(str, "ZBX_EOF") == 0)	break;
		if(pstrend == NULL) break;

		/* parse string from end of line */

		/* Lastlogsize */
		for(p = str + strlen(str); p != str; p--)
		{
			if(*p == ':')
			{
				*p = '\0';

				lastlogsize = p+1;
				break;
			}
		}

		/* Refresh */
		for(; p != str; p--)
		{
			if(*p == ':')
			{
				*p = '\0';

				refresh = p+1;
				break;
			}
		}

		key = str;

		str = pstrend+1;
		
		add_check(key, atoi(refresh), atoi(lastlogsize));
	}

	return SUCCEED;
}

static int	get_active_checks(char *server, unsigned short port, char *error, int max_error_len)
{

	ZBX_SOCKET	s;
	ZBX_SOCKADDR servaddr_in;

	struct hostent *hp;

	char	buf[MAX_BUF_LEN];

	int	len;
	int	amount_read;


	zabbix_log( LOG_LEVEL_DEBUG, "get_active_checks('%s',%u)", server, port);

	servaddr_in.sin_family = AF_INET;
	hp = gethostbyname(server);

	if(hp==NULL)
	{
#ifdef	HAVE_HSTRERROR		
		zbx_snprintf(error, max_error_len,"gethostbyname() failed for server '%s' [%s]", server, (char*)hstrerror((int)h_errno));
#else
		zbx_snprintf(error, max_error_len,"gethostbyname() failed for server '%s' [%d]", server, h_errno);
#endif
		zabbix_log( LOG_LEVEL_WARNING, error);
		return	NETWORK_ERROR;
	}

	servaddr_in.sin_addr.s_addr = ((struct in_addr *)(hp->h_addr))->s_addr;

	servaddr_in.sin_port = htons(port);

	if(INVALID_SOCKET == (s = socket(AF_INET,SOCK_STREAM,0)))
	{
		zbx_snprintf(error, max_error_len, "Cannot create socket [%s]", strerror_from_system(errno));
		zabbix_log(LOG_LEVEL_WARNING, error);
		return	FAIL;
	}
 
	if(SOCKET_ERROR == connect(s,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)))
	{
		switch (errno)
		{
			case EINTR:
				zbx_snprintf(error,max_error_len,"Timeout while connecting to [%s:%u]",server,port);
				break;
			case EHOSTUNREACH:
				zbx_snprintf(error,max_error_len,"No route to host [%s:%u]",server,port);
				break;
			default:
				zbx_snprintf(error,max_error_len,"Cannot connect to [%s:%u] [%s]",server,port,strerror(errno));
				break;
		} 
		zabbix_log(LOG_LEVEL_WARNING, error);
		zbx_sock_close(s);
		return	NETWORK_ERROR;
	}

	zbx_snprintf(buf, sizeof(buf), "%s\n%s\n","ZBX_GET_ACTIVE_CHECKS", CONFIG_HOSTNAME);
	zabbix_log(LOG_LEVEL_DEBUG, "Sending [%s]", buf);

	if(SOCKET_ERROR == zbx_sock_write(s, buf, strlen(buf)))
	{
		switch (errno)
		{
			case EINTR:
				zbx_snprintf(error,max_error_len,"Timeout while sending data to [%s:%u]",server,port);
				break;
			default:
				zbx_snprintf(error,max_error_len,"Error while sending data to [%s:%u] [%s]",server,port,strerror(errno));
				break;
		} 
		zabbix_log(LOG_LEVEL_WARNING, error);
		zbx_sock_close(s);
		return	FAIL;
	} 


	zabbix_log(LOG_LEVEL_DEBUG, "Before read");

	amount_read = 0;
	memset(buf, 0, MAX_BUF_LEN);

	do
	{
		len = zbx_sock_read(s, buf + amount_read, (MAX_BUF_LEN-1) - amount_read, CONFIG_TIMEOUT);

		if(SOCKET_ERROR == len)
		{
			switch (errno)
			{
				case 	EINTR:
						zbx_snprintf(error,max_error_len,"Timeout while receiving data from [%s:%u]",server,port);
						break;
				case	ECONNRESET:
						zbx_snprintf(error,max_error_len,"Connection reset by peer.");
						break;
				default:
						zbx_snprintf(error,max_error_len,"Error while receiving data from [%s:%u] [%s]",server,port,strerror(errno));
						break;
			} 
			zabbix_log( LOG_LEVEL_WARNING, error);
			zbx_sock_close(s);
			return	FAIL;
		}

		amount_read += len;
	}
	while (len > 0);

	parse_list_of_checks(buf);

	zbx_sock_close(s);
	
	return SUCCEED;
}

static int	send_value(char *server,unsigned short port,char *host, char *key,char *value, char *lastlogsize)
{
	ZBX_SOCKET	s;
	ZBX_SOCKADDR myaddr_in;
	ZBX_SOCKADDR servaddr_in;

	char	buf[MAX_STRING_LEN];
	int	len;

	struct hostent *hp;

	zabbix_log( LOG_LEVEL_DEBUG, "In send_value('%s',%u,'%s','%s','%s')", server, port, host, key, lastlogsize);

	servaddr_in.sin_family=AF_INET;
	hp = gethostbyname(server);

	if(hp==NULL)
	{
#ifdef	HAVE_HSTRERROR		
		zabbix_log( LOG_LEVEL_WARNING, "gethostbyname() failed for server '%s' [%d]", server, (char*)hstrerror((int)h_errno));
#else
		zabbix_log( LOG_LEVEL_WARNING, "gethostbyname() failed for server '%s' [%d]", server, h_errno);
#endif
		return	FAIL;
	}

	servaddr_in.sin_addr.s_addr=((struct in_addr *)(hp->h_addr))->s_addr;

	servaddr_in.sin_port = htons(port);

	if(INVALID_SOCKET == (s = socket(AF_INET,SOCK_STREAM,0)))
	{
		zabbix_log( LOG_LEVEL_WARNING, "Error in socket() [%s:%u] [%s]",server, port, strerror(errno));
		return	FAIL;
	}
	 
	myaddr_in.sin_family = AF_INET;
	myaddr_in.sin_port=0;
	myaddr_in.sin_addr.s_addr=INADDR_ANY;

	if(SOCKET_ERROR == connect(s,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)))
	{
		zabbix_log( LOG_LEVEL_WARNING, "Error in connect() [%s:%u] [%s]",server, port, strerror(errno));
		zbx_sock_close(s);
		return	FAIL;
	}

	comms_create_request(host, key, value, lastlogsize, buf, MAX_STRING_LEN-1);

	zabbix_log(LOG_LEVEL_DEBUG, "XML before sending [%s]",buf);

	if(SOCKET_ERROR == zbx_sock_write(s, buf, strlen(buf)))
	{
		zabbix_log( LOG_LEVEL_WARNING, "Error during sending [%s:%u] [%s]",server, port, strerror(errno));
		zbx_sock_close(s);
		return	FAIL;
	} 

	memset(buf, 0, MAX_STRING_LEN);

	if(SOCKET_ERROR == (len = zbx_sock_read(s, buf, MAX_STRING_LEN-1, CONFIG_TIMEOUT)))
	{
		zabbix_log( LOG_LEVEL_WARNING, "Error in recvfrom() [%s:%u] [%s]",server, port, strerror(errno));
		zbx_sock_close(s);
		return	FAIL;
	}

	/* !!! REMOVE '\n' AT THE AND (always must be present) !!! */
	buf[len-1] = '\0';

	if(strcmp(buf,"OK") == 0)
	{
		zabbix_log( LOG_LEVEL_DEBUG, "OK");
	}
	else
	{
		zabbix_log( LOG_LEVEL_DEBUG, "NOT OK [%s:%s] [%s]", host, key, buf);
	}
 
	zbx_sock_close(s);

	return SUCCEED;
}

static int	process_active_checks(char *server, unsigned short port)
{
	char	value[MAX_STRING_LEN];
	char	lastlogsize[MAX_STRING_LEN];
	int	i, now, count;
	int	ret = SUCCEED;

	char	c[MAX_STRING_LEN];
	char	*filename;

	AGENT_RESULT	result;

	zabbix_log( LOG_LEVEL_DEBUG, "In process_active_checks('%s',%u)",server, port);

	init_result(&result);

	now = time(NULL);

	for(i=0; NULL != active_metrics[i].key; i++)
	{
		if(active_metrics[i].nextcheck > now)			continue;
		if(active_metrics[i].status != ITEM_STATUS_ACTIVE)	continue;

		/* Special processing for log files */
		if(strncmp(active_metrics[i].key,"log[",4) == 0)
		{
			strscpy(c,active_metrics[i].key);
			filename=strtok(c,"[]");
			filename=strtok(NULL,"[]");

			count = 0;
			while(process_log(filename,&active_metrics[i].lastlogsize,value) == 0)
			{
				zbx_snprintf(lastlogsize, MAX_STRING_LEN,"%d",active_metrics[i].lastlogsize);

				if(send_value(server,port,CONFIG_HOSTNAME,active_metrics[i].key,value,lastlogsize) == FAIL)
				{
					ret = FAIL;
					break;
				}
				if(strcmp(value,"ZBX_NOTSUPPORTED\n")==0)
				{
					active_metrics[i].status = ITEM_STATUS_NOTSUPPORTED;
					zabbix_log( LOG_LEVEL_WARNING, "Active check [%s] is not supported. Disabled.", active_metrics[i].key);
					break;
				}
				count++;
				/* Do not flood ZABBIX server if file grows too fast */
				if(count >= (MAX_LINES_PER_SECOND * active_metrics[i].refresh))	break;
			}
		}
		else
		{
			lastlogsize[0]=0;
			
			process(active_metrics[i].key, 0, &result);
			if(result.type & AR_DOUBLE)
				 zbx_snprintf(value, sizeof(value), ZBX_FS_DBL, result.dbl);
			else if(result.type & AR_UINT64)
                                 zbx_snprintf(value, sizeof(value), ZBX_FS_UI64, result.ui64);
			else if(result.type & AR_STRING)
                                 zbx_snprintf(value, sizeof(value), "%s", result.str);
			else if(result.type & AR_TEXT)
                                 zbx_snprintf(value, sizeof(value), "%s", result.text);
			else if(result.type & AR_MESSAGE)
                                 zbx_snprintf(value, sizeof(value), "%s", result.msg);
			free_result(&result);

			zabbix_log( LOG_LEVEL_DEBUG, "For key [%s] received value [%s]", active_metrics[i].key, value);

			if(send_value(server,port,CONFIG_HOSTNAME,active_metrics[i].key,value,lastlogsize) == FAIL)
			{
				ret = FAIL;
				break;
			}

			if(strcmp(value,"ZBX_NOTSUPPORTED\n")==0)
			{
				active_metrics[i].status=ITEM_STATUS_NOTSUPPORTED;
				zabbix_log( LOG_LEVEL_WARNING, "Active check [%s] is not supported. Disabled.", active_metrics[i].key);
			}
		}

		active_metrics[i].nextcheck = time(NULL)+active_metrics[i].refresh;
	}
	return ret;
}

static void	refresh_metrics(char *server, unsigned short port, char *error, int max_error_len)
{
	zabbix_log( LOG_LEVEL_DEBUG, "In refresh_metrics('%s',%u)",server, port);

	while(get_active_checks(server, port, error, max_error_len) != SUCCEED)
	{
		zabbix_log( LOG_LEVEL_WARNING, "Getting list of active checks failed. Will retry after 60 seconds");

		zbx_setproctitle("poller [sleeping for %d seconds]", 60);

		zbx_sleep(60);
	}
}

ZBX_THREAD_ENTRY(active_checks_thread, args)
{
	ZBX_THREAD_ACTIVECHK_ARGS activechk_args;

	char	error[MAX_STRING_LEN];
	int	sleeptime, nextcheck;
	int	nextrefresh;
	char	*p = NULL;

	activechk_args.host = strdup(((ZBX_THREAD_ACTIVECHK_ARGS *)args)->host);
	activechk_args.port = ((ZBX_THREAD_ACTIVECHK_ARGS *)args)->port;

	assert(activechk_args.host);
	
	p = strchr(activechk_args.host,',');
	if(p) *p = '\0';

	zabbix_log( LOG_LEVEL_INFORMATION, "zabbix_agentd active check started [%s:%u]", activechk_args.host, activechk_args.port);

	zbx_setproctitle("getting list of active checks");

	init_active_metrics();

	refresh_metrics(activechk_args.host, activechk_args.port, error, MAX_STRING_LEN);
	nextrefresh = time(NULL) + CONFIG_REFRESH_ACTIVE_CHECKS;

	while(ZBX_IS_RUNNING)
	{

		zbx_setproctitle("processing active checks");

		if(process_active_checks(activechk_args.host, activechk_args.port) == FAIL)
		{
			zbx_sleep(60);
			continue;
		}

		nextcheck = get_min_nextcheck();
		if(FAIL == nextcheck)
		{
			sleeptime = 60;
		}
		else
		{
			sleeptime = nextcheck - time(NULL);

			sleeptime = MAX(sleeptime, 0);
		}

		if(sleeptime > 0)
		{
			sleeptime = MIN(sleeptime, 60);

			zabbix_log(LOG_LEVEL_DEBUG, "Sleeping for %d seconds", sleeptime );

			zbx_setproctitle("poller [sleeping for %d seconds]", sleeptime);

			zbx_sleep( sleeptime );
			continue;
		}
		else
		{
			zabbix_log(LOG_LEVEL_DEBUG, "No sleeping" );
		}

		if(time(NULL) >= nextrefresh)
		{
			refresh_metrics(activechk_args.host, activechk_args.port, error, MAX_STRING_LEN);
			nextrefresh=time(NULL) + CONFIG_REFRESH_ACTIVE_CHECKS;
		}
	}

	free(activechk_args.host);
	free_metrics();

	zabbix_log( LOG_LEVEL_INFORMATION, "zabbix_agentd active check stopped");

	ZBX_DO_EXIT();

	zbx_tread_exit(0);

}

