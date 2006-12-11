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


#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <netinet/in.h>
#include <netdb.h>

#include <string.h>

#include <time.h>

#include <sys/socket.h>
#include <errno.h>

/* Functions: pow(), round() */
#include <math.h>

#include "common.h"
#include "db.h"
#include "log.h"
#include "zlog.h"

#include "active.h"

/******************************************************************************
 *                                                                            *
 * Function: send_list_of_active_checks                                       *
 *                                                                            *
 * Purpose: send list of active checks to the host                            *
 *                                                                            *
 * Parameters: sockfd - open socket of server-agent connection                *
 *             host - hostname                                                *
 *                                                                            *
 * Return value:  SUCCEED - list of active checks sent succesfully            *
 *                FAIL - an error occured                                     *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: format of the list: key:delay:last_log_size                      *
 *                                                                            *
 ******************************************************************************/
int	send_list_of_active_checks(int sockfd, char *host)
{
	char	s[MAX_STRING_LEN];
	DB_RESULT result;
	DB_ROW	row;

	zabbix_log( LOG_LEVEL_DEBUG, "In send_list_of_active_checks()");

	result = DBselect("select i.key_,i.delay,i.lastlogsize from items i,hosts h where i.hostid=h.hostid and h.status=%d and i.status=%d and i.type=%d and h.host='%s' and" ZBX_COND_NODEID, HOST_STATUS_MONITORED, ITEM_STATUS_ACTIVE, ITEM_TYPE_ZABBIX_ACTIVE, host, LOCAL_NODE("h.hostid"));

	while((row=DBfetch(result)))
	{
		zbx_snprintf(s,sizeof(s),"%s:%s:%s\n",row[0],row[1],row[2]);
		zabbix_log( LOG_LEVEL_DEBUG, "Sending [%s]", s);
		if( write(sockfd,s,strlen(s)) == -1 )
		{
			switch (errno)
			{
				case EINTR:
					zabbix_log( LOG_LEVEL_WARNING, "Timeout while sending list of active checks");
					break;
				default:
					zabbix_log( LOG_LEVEL_WARNING, "Error while sending list of active checks [%s]", strerror(errno));
			}
			close(sockfd);
			return  FAIL;
		}
	}
	DBfree_result(result);

	zbx_snprintf(s,sizeof(s),"%s\n","ZBX_EOF");
	zabbix_log( LOG_LEVEL_DEBUG, "Sending [%s]", s);
	if( write(sockfd,s,strlen(s)) == -1 )
	{
		switch (errno)
		{
			case EINTR:
				zabbix_log( LOG_LEVEL_WARNING, "Timeout while sending list of active checks");
				break;
			default:
				zabbix_log( LOG_LEVEL_WARNING, "Error while sending list of active checks [%s]", strerror(errno));
		}
		close(sockfd);
		return  FAIL;
	}

	return  SUCCEED;
}
