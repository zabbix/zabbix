/* 
** ZABBIX
** Copyright (C) 2000-2006 SIA Zabbix
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

#include "config.h"

#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <sys/socket.h>
#include <netinet/in.h>

#include <sys/wait.h>

#include <string.h>

#ifdef HAVE_NETDB_H
	#include <netdb.h>
#endif

/* Required for getpwuid */
#include <pwd.h>

#include <signal.h>
#include <errno.h>

#include <time.h>

#include "common.h"
#include "cfg.h"
#include "db.h"
#include "log.h"
#include "zlog.h"

#include "nodecomms.h"

extern     int     CONFIG_NODEID;

/******************************************************************************
 *                                                                            *
 * Function: send_to_node                                                     *
 *                                                                            *
 * Purpose: send configuration changes to required node                       *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: SUCCESS - processed succesfully                              * 
 *               FAIL - an error occured                                      *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int send_to_node(int dest_nodeid, int nodeid, char *data)
{
	int	i,s;
	char	answer[MAX_STRING_LEN];
	struct hostent *hp;

	struct sockaddr_in myaddr_in;
	struct sockaddr_in servaddr_in;
	char	ip[MAX_STRING_LEN];
	int	port;
	int	ret = FAIL;
	int	written;

	char	header[5]="ZBXD\1";
	zbx_uint64_t	len64;

	DB_RESULT	result;
	DB_ROW		row;

	zabbix_log( LOG_LEVEL_WARNING, "NODE %d: Sending data of node %d to node %d datalen %d", CONFIG_NODEID, nodeid, dest_nodeid, strlen(data));

	result = DBselect("select ip, port from nodes where nodeid=%d", dest_nodeid);
	row = DBfetch(result);
	if(!row)
	{
		DBfree_result(result);
		zabbix_log( LOG_LEVEL_WARNING, "Node [%d] in unknown", dest_nodeid);
		return FAIL;
	}
	zbx_strlcpy(ip,row[0],sizeof(ip));
	port=atoi(row[1]);
	DBfree_result(result);

	servaddr_in.sin_family=AF_INET;
	hp=gethostbyname(ip);

	if(hp==NULL)
	{
		zabbix_log( LOG_LEVEL_WARNING, "Cannot resolve [%s] for node [%d]", ip, dest_nodeid);
		return	FAIL;
	}

	servaddr_in.sin_addr.s_addr=((struct in_addr *)(hp->h_addr))->s_addr;

	servaddr_in.sin_port=htons(port);

	s=socket(AF_INET,SOCK_STREAM,0);
	if(s == -1)
	{
		zabbix_log( LOG_LEVEL_WARNING, "Cannot create socket [%s] for node [%d]", ip, dest_nodeid);
		return	FAIL;
	}

	myaddr_in.sin_family = AF_INET;
	myaddr_in.sin_port=0;
	myaddr_in.sin_addr.s_addr=INADDR_ANY;

	if( connect(s,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)) == -1 )
	{
		zabbix_log( LOG_LEVEL_WARNING, "Cannot connect [%s] to node [%d]", ip, dest_nodeid);
		close(s);
		return	FAIL;
	}

	written = 0;

	/* Write header */
	i=write(s, header, 5);
	if(i == -1)
	{
		zabbix_log( LOG_LEVEL_WARNING, "Error writing to node [%d] [%s]", dest_nodeid, strerror(errno));
		close(s);
		return	FAIL;
	}
	len64 = (zbx_uint64_t)strlen(data);

	/* Write data length */
	i=write(s, &len64, sizeof(len64));
	if(i == -1)
	{
		zabbix_log( LOG_LEVEL_WARNING, "Error writing to node [%d] [%s]", dest_nodeid, strerror(errno));
		close(s);
		return	FAIL;
	}

	while(written<strlen(data))
	{
		i=write(s, data+written,strlen(data)-written);
		if(i == -1)
		{
			zabbix_log( LOG_LEVEL_WARNING, "Error writing to node [%d] [%s]", dest_nodeid, strerror(errno));
			close(s);
			return	FAIL;
		}
		written+=i;
	}
	i=sizeof(struct sockaddr_in);
	i=read(s,answer,MAX_STRING_LEN-1);
	if(i==-1)
	{
		zabbix_log( LOG_LEVEL_WARNING, "Error reading from node [%d]", dest_nodeid);
		close(s);
		return	FAIL;
	}

	answer[i-1]=0;

	if(strcmp(answer,"OK") == 0)
	{
		zabbix_log( LOG_LEVEL_DEBUG, "OK");
		ret = SUCCEED;
	}
	else
	{
		zabbix_log( LOG_LEVEL_DEBUG, "NOT OK");
	}
 
	if( close(s)!=0 )
	{
	}

	return ret;
}
