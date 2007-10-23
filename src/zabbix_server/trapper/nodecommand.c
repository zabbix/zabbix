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

#include "comms.h"
#include "common.h"
#include "db.h"
#include "log.h"
#include "zlog.h"

/******************************************************************************
 *                                                                            *
 * Function: node_events                                                      *
 *                                                                            *
 * Purpose: process new events received from a salve node                     *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:  SUCCEED - processed successfully                            *
 *                FAIL - an error occured                                     *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int	node_process_command(const char *data, char **result)
{
	const char	*r;
	char		*tmp = NULL;
	int		tmp_allocated = 64, result_allocated = 4*1024;
	int		datalen;
	int		nodeid, result_offset = 0;

	zbx_sock_t	sock;
	char		ip[MAX_STRING_LEN], command[MAX_STRING_LEN];
	char		buffer[MAX_STRING_LEN];
	int		port;
	DB_RESULT	dbresult;
	DB_ROW		dbrow;
	FILE		*f;

	*result = zbx_malloc(*result, result_allocated);
	tmp = zbx_malloc(tmp, tmp_allocated);
	datalen = strlen(data);

	zabbix_log( LOG_LEVEL_DEBUG, "In node_process_command(datalen:%d)", data, datalen);

	r = data;
	r = zbx_get_next_field(r, &tmp, &tmp_allocated, ZBX_DM_DELIMITER); /* Constant 'Command' */
	r = zbx_get_next_field(r, &tmp, &tmp_allocated, ZBX_DM_DELIMITER); /* NodeID */
	nodeid = atoi(tmp);
	r = zbx_get_next_field(r, &tmp, &tmp_allocated, ZBX_DM_DELIMITER);
	strscpy(command, tmp);

	zabbix_log( LOG_LEVEL_WARNING, "NODE %d: Received command for nodeid "ZBX_FS_UI64,
					CONFIG_NODEID,
					nodeid);

	if (nodeid == CONFIG_NODEID) {
		if(0 == (f = popen(command, "r"))) {
			zbx_snprintf(*result, result_allocated, "1%cNODE %d: Cannot execute [%s] error:%s",
				ZBX_DM_DELIMITER,
				CONFIG_NODEID,
				command,
				strerror(errno));
			goto exit;
		}

		zbx_snprintf_alloc(result, &result_allocated, &result_offset, sizeof(buffer), "0%c",
			ZBX_DM_DELIMITER);

		while (NULL != fgets(buffer, sizeof(buffer)-1, f)) {
			zbx_snprintf_alloc(result, &result_allocated, &result_offset, sizeof(buffer), "%s",
				buffer);
		}

		pclose(f);
	} else {
		zabbix_log( LOG_LEVEL_WARNING, "NODE %d: Sending command for nodeid "ZBX_FS_UI64
			" to node %d",
			CONFIG_NODEID,
			nodeid,
			nodeid);

		dbresult = DBselect("select ip, port from nodes where nodeid=%d",
			nodeid);

		if (NULL == (dbrow = DBfetch(dbresult))) {
			DBfree_result(dbresult);
			zbx_snprintf(*result, result_allocated, "1%cNODE %d: Node [%d] is unknown",
				ZBX_DM_DELIMITER,
				CONFIG_NODEID,
				nodeid);
			goto exit;
		}

		zbx_strlcpy(ip, dbrow[0], sizeof(ip));
		port = atoi(dbrow[1]);

		DBfree_result(dbresult);

		if (FAIL == zbx_tcp_connect(&sock, ip, port)) {
			zbx_snprintf(*result, result_allocated, "1%cNODE %d: Unable to connect to Node [%d] error: %s",
				ZBX_DM_DELIMITER,
				CONFIG_NODEID,
				nodeid,
				zbx_tcp_strerror());
			goto exit_sock;
		}

		if (FAIL == zbx_tcp_send(&sock, data)) {
			zbx_snprintf(*result, result_allocated, "1%cNODE %d: Error while sending data to Node [%d] error: %s",
				ZBX_DM_DELIMITER,
				CONFIG_NODEID,
				nodeid,
				zbx_tcp_strerror());
			zbx_tcp_close(&sock);
			goto exit_sock;
		}

		if (FAIL == zbx_tcp_recv_ext(&sock, result, ZBX_TCP_READ_UNTIL_CLOSE)) {
			zbx_snprintf(*result, result_allocated, "1%cNODE %d: Error while receiving answer from Node [%d] error: %s",
				ZBX_DM_DELIMITER,
				CONFIG_NODEID,
				nodeid,
				zbx_tcp_strerror());
			zbx_tcp_close(&sock);
			goto exit_sock;
		}
exit_sock:
		zbx_tcp_close(&sock);
	}
exit:
	zbx_free(tmp);

	return SUCCEED;
}
