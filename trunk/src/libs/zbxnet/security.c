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


//#include <sys/types.h>
//#include <sys/socket.h>
//#include <netinet/in.h>
//#include <arpa/inet.h>
//#include <netdb.h>

//#include <string.h>

//#include <errno.h>

#include "common.h"
#include "zbxsock.h"
#include "log.h"

/******************************************************************************
 *                                                                            *
 * Function: check_security                                                   *
 *                                                                            *
 * Purpose: check if connection initiator is in list of IP addresses          *
 *                                                                            *
 * Parameters: sockfd - socker descriptor                                     *
 *             ip_list - comma-delimited list of IP addresses                 *
 *             allow_if_empty - allow connection if no IP given               *
 *                                                                            *
 * Return value: SUCCEED - connection allowed                                 *
 *               FAIL - connection is not allowed                             *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/

int	check_security(ZBX_SOCKET sock, char *ip_list, int allow_if_empty)
{
	ZBX_SOCKADDR name;
	socklen_t	nlen;

	struct  hostent *hp;

	char	tmp[MAX_STRING_LEN], 
		sname[MAX_STRING_LEN],
		*sip, 
		*host;

        zabbix_log( LOG_LEVEL_DEBUG, "In check_security()");

	if( (1 == allow_if_empty) && (0 == strlen(ip_list)) )
	{
		return SUCCEED;
	}

	nlen = sizeof(ZBX_SOCKADDR);
	if( 0 != getpeername(sock,  (struct sockaddr*)&name, &nlen))
	{
		zabbix_log( LOG_LEVEL_WARNING, "Connection rejected. Getpeername failed [%s].",strerror(errno));
		return FAIL;
	}
	else
	{
		strcpy(sname, inet_ntoa(name.sin_addr));

		strscpy(tmp,ip_list);

        	host = (char *)strtok(tmp,",");

		while( NULL != host )
		{
			/* Allow IP addresses or DNS names for authorization */
			if( 0 == (hp = gethostbyname(host)))
			{
				zabbix_log( LOG_LEVEL_WARNING, "Error on gethostbyname, can not resolve [%s]",host);
			}
			else
			{
				sip = inet_ntoa(*((struct in_addr *)hp->h_addr));
				if( 0 == strcmp(sname, sip))
				{
					zabbix_log( LOG_LEVEL_DEBUG, "Connection from [%s] accepted. Allowed servers [%s] ",sname, ip_list);
					return	SUCCEED;
				}
			}
                	host = (char *)strtok(NULL,",");
		}
	}
	zabbix_log( LOG_LEVEL_WARNING, "Connection from [%s] rejected. Allowed server is [%s] ",sname, ip_list);
	return	FAIL;
}
