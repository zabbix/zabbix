/* 
** Zabbix
** Copyright (C) 2000,2001,2002,2003,2004 Alexei Vladishev
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

int	get_value_agent(double *result,char *result_str,DB_ITEM *item)
{
	int	s;
	int	len;
	char	c[MAX_STRING_LEN];
	char	*e;

	struct hostent *hp;

	struct sockaddr_in servaddr_in;

	struct linger ling;

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
		zabbix_log( LOG_LEVEL_WARNING, "gethostbyname() failed" );
		return	NETWORK_ERROR;
	}

	servaddr_in.sin_addr.s_addr=((struct in_addr *)(hp->h_addr))->s_addr;

	servaddr_in.sin_port=htons(item->port);

	s=socket(AF_INET,SOCK_STREAM,0);

	if(CONFIG_NOTIMEWAIT == 1)
	{
		ling.l_onoff=1;
		ling.l_linger=0;
		if(setsockopt(s,SOL_SOCKET,SO_LINGER,&ling,sizeof(ling))==-1)
		{
			zabbix_log(LOG_LEVEL_WARNING, "Cannot setsockopt SO_LINGER [%s]", strerror(errno));
		}
	}
	if(s == -1)
	{
		zabbix_log(LOG_LEVEL_WARNING, "Cannot create socket [%s]",
				strerror(errno));
		return	FAIL;
	}
 
	if( connect(s,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)) == -1 )
	{
		switch (errno)
		{
			case EINTR:
				zabbix_log( LOG_LEVEL_WARNING, "Timeout while connecting to [%s]",item->host );
				break;
			case EHOSTUNREACH:
				zabbix_log( LOG_LEVEL_WARNING, "No route to host [%s]",item->host );
				break;
			default:
				zabbix_log( LOG_LEVEL_WARNING, "Cannot connect to [%s] [%s]",item->host, strerror(errno));
		} 
		close(s);
		return	NETWORK_ERROR;
	}

	snprintf(c,sizeof(c)-1,"%s\n",item->key);
	zabbix_log(LOG_LEVEL_DEBUG, "Sending [%s]", c);
	if( write(s,c,strlen(c)) == -1 )
	{
		switch (errno)
		{
			case EINTR:
				zabbix_log( LOG_LEVEL_WARNING, "Timeout while sending data to [%s]",item->host );
				break;
			default:
				zabbix_log( LOG_LEVEL_WARNING, "Error while sending data to [%s] [%s]",item->host, strerror(errno));
		} 
		close(s);
		return	FAIL;
	} 

	memset(c,0,MAX_STRING_LEN);
	len=read(s,c,MAX_STRING_LEN);
	if(len == -1)
	{
		switch (errno)
		{
			case 	EINTR:
					zabbix_log( LOG_LEVEL_WARNING, "Timeout while receiving data from [%s]",item->host );
					break;
			case	ECONNRESET:
					zabbix_log( LOG_LEVEL_WARNING, "Connection reset by peer. Host [%s] Parameter [%s]",item->host, item->key );
					close(s);
					return	NETWORK_ERROR;
			default:
				zabbix_log( LOG_LEVEL_WARNING, "Error while receiving data from [%s] [%s]",item->host, strerror(errno));
		} 
		close(s);
		return	FAIL;
	}

	if( close(s)!=0 )
	{
		zabbix_log(LOG_LEVEL_WARNING, "Problem with close [%s]", strerror(errno));
	}
	zabbix_log(LOG_LEVEL_DEBUG, "Got string:[%d] [%s]", len, c);
	if(len>0)
	{
		c[len-1]=0;
	}

	*result=strtod(c,&e);

	/* The section should be improved */
	if( (*result==0) && (c==e) && (item->value_type==0) && (strcmp(c,"ZBX_NOTSUPPORTED") != 0) && (strcmp(c,"ZBX_ERROR") != 0) )
	{
		zabbix_log( LOG_LEVEL_WARNING, "Got empty string from [%s] IP [%s] Parameter [%s]", item->host, item->ip, item->key);
		zabbix_log( LOG_LEVEL_WARNING, "Assuming that agent dropped connection because of access permissions");
		return	NETWORK_ERROR;
	}

	/* Should be deleted in Zabbix 1.0 stable */
	if( cmp_double(*result,NOTSUPPORTED) == 0)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "NOTSUPPORTED1 [%s]", c );
		return NOTSUPPORTED;
	}
	if( strcmp(c,"ZBX_NOTSUPPORTED") == 0)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "NOTSUPPORTED2 [%s]", c );
		return NOTSUPPORTED;
	}
	if( strcmp(c,"ZBX_ERROR") == 0)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "AGENT_ERROR [%s]", c );
		return AGENT_ERROR;
	}

	strcpy(result_str,c);

	zabbix_log(LOG_LEVEL_DEBUG, "RESULT_STR [%s]", c );

	return SUCCEED;
}
