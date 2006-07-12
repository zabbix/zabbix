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

#include "checks_agent.h"

/******************************************************************************
 *                                                                            *
 * Function: get_value_agent                                                  *
 *                                                                            *
 * Purpose: retrieve data from ZABBIX agent                                   *
 *                                                                            *
 * Parameters: item - item we are interested in                               *
 *                                                                            *
 * Return value: SUCCEED - data succesfully retrieved and stored in result    *
 *                         and result_str (as string)                         *
 *               NETWORK_ERROR - network related error occured                *
 *               NOTSUPPORTED - item not supported by the agent               *
 *               AGENT_ERROR - uncritical error on agent side occured         *
 *               FAIL - otherwise                                             *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: error will contain error message                                 *
 *                                                                            *
 ******************************************************************************/
int	get_value_agent(DB_ITEM *item, AGENT_RESULT *result)
{
	int	s;
	int	len;
	char	c[MAX_STRING_LEN];
	char	error[MAX_STRING_LEN];

	struct hostent *hp;

	struct sockaddr_in servaddr_in;

/*	struct linger ling;*/

	init_result(result);

	zabbix_log( LOG_LEVEL_DEBUG, "get_value_agent: host[%s] ip[%s] key [%s]", item->host, item->ip, item->key );

	servaddr_in.sin_family=AF_INET;
	if(item->useip==1)
	{
		hp=gethostbyname(item->ip);
	}
	else
	{
		hp=gethostbyname(item->host);
	}

	if(hp==NULL)
	{
#ifdef	HAVE_HSTRERROR
		zbx_snprintf(error,sizeof(error),"gethostbyname() failed [%s]", hstrerror(h_errno));
		zabbix_log(LOG_LEVEL_WARNING, error);
		result->msg=strdup(error);
#else
		zbx_snprintf(error,sizeof(error),"gethostbyname() failed [%d]", h_errno);
		zabbix_log(LOG_LEVEL_WARNING, error);
		result->msg=strdup(error);
#endif
		return	NETWORK_ERROR;
	}

	servaddr_in.sin_addr.s_addr=((struct in_addr *)(hp->h_addr))->s_addr;

	servaddr_in.sin_port=htons(item->port);

	s=socket(AF_INET,SOCK_STREAM,0);
/*
	if(CONFIG_NOTIMEWAIT == 1)
	{
		ling.l_onoff=1;
		ling.l_linger=0;
		if(setsockopt(s,SOL_SOCKET,SO_LINGER,&ling,sizeof(ling))==-1)
		{
			zabbix_log(LOG_LEVEL_WARNING, "Cannot setsockopt SO_LINGER [%s]", strerror(errno));
		}
	}*/
	if(s == -1)
	{
		zbx_snprintf(error,sizeof(error),"Cannot create socket [%s]", strerror(errno));
		zabbix_log(LOG_LEVEL_WARNING, error);
		result->msg=strdup(error);
		return	FAIL;
	}
 
	if( connect(s,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)) == -1 )
	{
		switch (errno)
		{
			case EINTR:
				zbx_snprintf(error,sizeof(error),"Timeout while connecting to [%s]",item->host);
				zabbix_log(LOG_LEVEL_WARNING, error);
				result->msg=strdup(error);
				break;
			case EHOSTUNREACH:
				zbx_snprintf(error,sizeof(error),"No route to host [%s]",item->host);
				zabbix_log(LOG_LEVEL_WARNING, error);
				result->msg=strdup(error);
				break;
			default:
				zbx_snprintf(error,sizeof(error),"Cannot connect to [%s] [%s]",item->host, strerror(errno));
				zabbix_log(LOG_LEVEL_WARNING, error);
				result->msg=strdup(error);
		} 
		close(s);
		return	NETWORK_ERROR;
	}

	zbx_snprintf(c, sizeof(c), "%s\n",item->key);
	zabbix_log(LOG_LEVEL_DEBUG, "Sending [%s]", c);
	if( write(s,c,strlen(c)) == -1 )
	{
		switch (errno)
		{
			case EINTR:
				zbx_snprintf(error,sizeof(error),"Timeout while sending data to [%s]",item->host);
				zabbix_log(LOG_LEVEL_WARNING, error);
				result->msg=strdup(error);
				break;
			default:
				zbx_snprintf(error,sizeof(error),"Error while sending data to [%s] [%s]",item->host, strerror(errno));
				zabbix_log(LOG_LEVEL_WARNING, error);
				result->msg=strdup(error);
		} 
		close(s);
		return	FAIL;
	} 

	memset(c,0,MAX_STRING_LEN);
	len=read(s, c, MAX_STRING_LEN);
	if(len == -1)
	{
		switch (errno)
		{
			case 	EINTR:
					zbx_snprintf(error,sizeof(error),"Timeout while receiving data from [%s]",item->host);
					zabbix_log(LOG_LEVEL_WARNING, error);
					result->msg=strdup(error);
					break;
			case	ECONNRESET:
					zbx_snprintf(error,sizeof(error),"Connection reset by peer.");
					zabbix_log(LOG_LEVEL_WARNING, error);
					result->msg=strdup(error);
					close(s);
					return	NETWORK_ERROR;
			default:
				zbx_snprintf(error,sizeof(error),"Error while receiving data from [%s] [%s]",item->host, strerror(errno));
				zabbix_log(LOG_LEVEL_WARNING, error);
				result->msg=strdup(error);
		} 
		close(s);
		return	FAIL;
	}

	if( close(s)!=0 )
	{
		zabbix_log(LOG_LEVEL_WARNING, "Problem with close [%s]", strerror(errno));
	}

	delete_reol(c);
	lrtrim_spaces(c);

/*	if(len>0)
	{
		c[len]=0;
	}*/

/*	if(item->itemid == 17828)
		zabbix_log(LOG_LEVEL_WARNING, "Got string:[%d] [%s]", len, c);*/

	if( strcmp(c,"ZBX_NOTSUPPORTED") == 0)
	{
		zbx_snprintf(error,sizeof(error),"Not supported by ZABBIX agent");
		result->msg=strdup(error);
		return NOTSUPPORTED;
	}
	else if( strcmp(c,"ZBX_ERROR") == 0)
	{
		zbx_snprintf(error,sizeof(error),"ZABBIX agent non-critical error");
		result->msg=strdup(error);
		return AGENT_ERROR;
	}
	/* The section should be improved */
	else if(c[0]==0)
	{
		zbx_snprintf(error,sizeof(error),"Got empty string from [%s] IP [%s] Parameter [%s]", item->host, item->ip, item->key);
		zabbix_log( LOG_LEVEL_WARNING, error);
		zabbix_log( LOG_LEVEL_WARNING, "Assuming that agent dropped connection because of access permissions");
		result->msg=strdup(error);
		return	NETWORK_ERROR;
	}

	if(set_result_type(result, item->value_type, c) == FAIL)
	{
		zbx_snprintf(error,sizeof(error), "Type of received value [%s] is not sutable for [%s@%s] having type [%d]", c, item->key, item->host, item->value_type);
		zabbix_log( LOG_LEVEL_WARNING, error);
		zabbix_log( LOG_LEVEL_WARNING, "Returning NOTSUPPORTED");
		result->msg=strdup(error);
		return NOTSUPPORTED;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "RESULT_STR [%c]", c);

	return SUCCEED;
}
