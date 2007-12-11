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

	if( FAIL == zbx_tcp_connect(&sock, ip, port, 0))
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

int	connect_to_node(int nodeid, zbx_sock_t *sock)
{
	DB_RESULT	result;
	DB_ROW		row;
	unsigned short	port;
	int		res = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In connect_to_node(nodeid:%d)", nodeid);

	result = DBselect("select ip,port from nodes where nodeid=%d",
		nodeid);

	if (NULL != (row = DBfetch(result))) {
		port = (unsigned short)atoi(row[1]);

		if (SUCCEED == zbx_tcp_connect(sock, row[0], port, 0))
			res = SUCCEED;
		else
			zabbix_log(LOG_LEVEL_WARNING, "NODE %d: Unable to connect to Node [%d] error: %s",
				CONFIG_NODEID,
				nodeid,
				zbx_tcp_strerror());
	} else
		zabbix_log(LOG_LEVEL_WARNING, "NODE %d: Node [%d] is unknown",
			CONFIG_NODEID,
			nodeid);
	DBfree_result(result);

	return res;
}

int	send_data_to_node(int nodeid, zbx_sock_t *sock, const char *data)
{
	int	res;

	if (FAIL == (res = zbx_tcp_send_ext(sock, data, ZBX_TCP_NEW_PROTOCOL)))
		zabbix_log(LOG_LEVEL_WARNING, "NODE %d: Error while sending data to Node [%d] error: %s",
			CONFIG_NODEID,
			nodeid,
			zbx_tcp_strerror());

	return res;
}

int	recv_data_from_node(int nodeid, zbx_sock_t *sock, char **data)
{
	int	res;

	if (FAIL == (res = zbx_tcp_recv_ext(sock, data, 0)))
		zabbix_log(LOG_LEVEL_WARNING, "NODE %d: Error while receiving answer from Node [%d] error: %s",
			CONFIG_NODEID,
			nodeid,
			zbx_tcp_strerror());

	return res;
}

void	disconnect_node(zbx_sock_t *sock)
{
	zbx_tcp_close(sock);
}
