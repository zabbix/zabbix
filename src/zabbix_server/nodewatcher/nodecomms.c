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

#include "common.h"

#include "cfg.h"
#include "db.h"
#include "log.h"
#include "zlog.h"

#include "comms.h"
#include "nodecomms.h"

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
int send_to_node(char *name, int dest_nodeid, int nodeid, char *data)
{
	char	ip[MAX_STRING_LEN];
	int	port;
	int	ret = FAIL;

	DB_RESULT	result;
	DB_ROW		row;

	zbx_sock_t	sock;
	char		*answer;

	zabbix_log( LOG_LEVEL_WARNING, "NODE %d: Sending %s of node %d to node %d datalen %d",
		CONFIG_NODEID,
		name,
		nodeid,
		dest_nodeid,
		strlen(data));
/*	zabbix_log( LOG_LEVEL_WARNING, "Data [%s]", data);*/

	result = DBselect("select ip, port from nodes where nodeid=%d",
		dest_nodeid);
	row = DBfetch(result);
	if(!row)
	{
		DBfree_result(result);
		zabbix_log( LOG_LEVEL_WARNING, "Node [%d] is unknown",
			dest_nodeid);
		return FAIL;
	}
	zbx_strlcpy(ip,row[0],sizeof(ip));
	port=atoi(row[1]);
	DBfree_result(result);

	if( FAIL == zbx_tcp_connect(&sock, ip, port))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Unable to connect to Node [%d] error: %s", dest_nodeid, zbx_tcp_strerror());
		return  FAIL;
	}


	if( FAIL == zbx_tcp_send(&sock, data))
	{
		zabbix_log( LOG_LEVEL_WARNING, "Error while sending data to Node [%d]",
			dest_nodeid);
		zbx_tcp_close(&sock);
		return  FAIL;
	}

	if( FAIL == zbx_tcp_recv(&sock, &answer))
	{
		zabbix_log( LOG_LEVEL_WARNING, "Error while receiving answer from Node [%d]",
			dest_nodeid);
		zbx_tcp_close(&sock);
		return  FAIL;
	}

	zabbix_log( LOG_LEVEL_DEBUG, "Answer [%s]",
		answer);

	if(strcmp(answer,"OK") == 0)
	{
		zabbix_log( LOG_LEVEL_DEBUG, "OK");
		ret = SUCCEED;
	}
	else
	{
		zabbix_log( LOG_LEVEL_WARNING, "NOT OK");
	}

	zbx_tcp_close(&sock);

	return ret;
}
