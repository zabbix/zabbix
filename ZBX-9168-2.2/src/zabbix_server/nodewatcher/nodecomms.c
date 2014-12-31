/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "common.h"

#include "cfg.h"
#include "db.h"
#include "log.h"

#include "comms.h"
#include "nodecomms.h"

int	connect_to_node(int nodeid, zbx_sock_t *sock)
{
	DB_RESULT	result;
	DB_ROW		row;
	unsigned short	port;
	int		res = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In connect_to_node() nodeid:%d", nodeid);

	result = DBselect("select ip,port from nodes where nodeid=%d", nodeid);

	if (NULL != (row = DBfetch(result)))
	{
		port = (unsigned short)atoi(row[1]);

		if (SUCCEED == zbx_tcp_connect(sock, CONFIG_SOURCE_IP, row[0], port, 0))
			res = SUCCEED;
		else
			zabbix_log(LOG_LEVEL_ERR, "NODE %d: cannot connect to Node [%d]: %s",
					CONFIG_NODEID, nodeid, zbx_tcp_strerror());
	}
	else
		zabbix_log(LOG_LEVEL_ERR, "NODE %d: Node [%d] is unknown", CONFIG_NODEID, nodeid);

	DBfree_result(result);

	return res;
}

int	send_data_to_node(int nodeid, zbx_sock_t *sock, const char *data)
{
	int	res;

	if (FAIL == (res = zbx_tcp_send(sock, data)))
	{
		zabbix_log(LOG_LEVEL_ERR, "NODE %d: cannot send data to Node [%d]: %s",
				CONFIG_NODEID, nodeid, zbx_tcp_strerror());
	}
	else
		zabbix_log(LOG_LEVEL_DEBUG, "NODE %d: sending [%s] to Node [%d]",
				CONFIG_NODEID, data, nodeid);

	return res;
}

int	recv_data_from_node(int nodeid, zbx_sock_t *sock, char **data)
{
	int	res;

	if (FAIL == (res = zbx_tcp_recv(sock, data)))
	{
		zabbix_log(LOG_LEVEL_ERR, "NODE %d: cannot receive answer from Node [%d]: %s",
				CONFIG_NODEID, nodeid, zbx_tcp_strerror());
	}
	else
		zabbix_log(LOG_LEVEL_DEBUG, "NODE %d: receiving [%s] from Node [%d]",
				CONFIG_NODEID, *data, nodeid);

	return res;
}

void	disconnect_node(zbx_sock_t *sock)
{
	zbx_tcp_close(sock);
}

/******************************************************************************
 *                                                                            *
 * Function: send_to_node                                                     *
 *                                                                            *
 * Purpose: send configuration changes to required node                       *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: SUCCESS - processed successfully                             *
 *               FAIL - an error occurred                                     *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int	send_to_node(const char *name, int dest_nodeid, int nodeid, char *data)
{
	int		ret = FAIL;
	zbx_sock_t	sock;
	char		*answer;

	zabbix_log(LOG_LEVEL_WARNING, "NODE %d: sending %s of node %d to node %d datalen " ZBX_FS_SIZE_T,
			CONFIG_NODEID, name, nodeid, dest_nodeid, (zbx_fs_size_t)strlen(data));

	if (FAIL == connect_to_node(dest_nodeid, &sock))
		return FAIL;

	if (FAIL == send_data_to_node(dest_nodeid, &sock, data))
		goto disconnect;

	if (FAIL == recv_data_from_node(dest_nodeid, &sock, &answer))
		goto disconnect;

	if (0 == strcmp(answer, "OK"))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "OK");
		ret = SUCCEED;
	}
	else
		zabbix_log(LOG_LEVEL_WARNING, "NOT OK");
disconnect:
	disconnect_node(&sock);

	return ret;
}
