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


#include <sys/types.h>
#include <sys/socket.h>
#include <netinet/in.h>
#include <arpa/inet.h>

#include <string.h>

#include <errno.h>

/* config.h is required for socklen_t (undefined under Solaris) */
#include "config.h"
#include "common.h"
#include "log.h"

int	check_security(int sockfd, char *ip_list, int allow_if_empty)
{
	char	*sname;
	struct	sockaddr_in name;
	int	i;
	char	*s;

	char	tmp[MAX_STRING_LEN];

        zabbix_log( LOG_LEVEL_DEBUG, "In check_security()");

	if( (1 == allow_if_empty) && (strlen(ip_list)==0) )
	{
		return SUCCEED;
	}

	i=sizeof(name);

/*	if(getpeername(sockfd,  (struct sockaddr *)&name, (size_t *)&i) == 0)*/
	if(getpeername(sockfd,  (struct sockaddr *)&name, (socklen_t *)&i) == 0)
	{
		i=sizeof(struct sockaddr_in);

		sname=inet_ntoa(name.sin_addr);

		zabbix_log( LOG_LEVEL_DEBUG, "Connection from [%s]. Allowed servers [%s] ",sname, ip_list);

		strscpy(tmp,ip_list);
        	s=(char *)strtok(tmp,",");
		while(s!=NULL)
		{
			if(strcmp(sname, s)==0)
			{
				return	SUCCEED;
			}
                	s=(char *)strtok(NULL,",");
		}
	}
	else
	{
		zabbix_log( LOG_LEVEL_WARNING, "Error getpeername [%s]",strerror(errno));
		zabbix_log( LOG_LEVEL_WARNING, "Connection rejected");
		return FAIL;
	}
	zabbix_log( LOG_LEVEL_WARNING, "Connection from [%s] rejected. Allowed server is [%s] ",sname, ip_list);
	return	FAIL;
}
