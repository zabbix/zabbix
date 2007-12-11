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

#define MVAR_HOST_NAME			"{HOSTNAME}"
#define MVAR_IPADDRESS			"{IPADDRESS}"
#define MVAR_HOST_CONN			"{HOST.CONN}"


/******************************************************************************
 *                                                                            *
 * Function: execute_script                                                   *
 *                                                                            *
 * Purpose: executing command                                                 *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:  SUCCEED - processed successfully                            *
 *                FAIL - an error occured                                     *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	execute_script(const char *command, char **result, int *result_allocated)
{
	int		result_offset = 0;
	char		buffer[MAX_STRING_LEN];
	FILE		*f;

	zabbix_log(LOG_LEVEL_DEBUG, "In execute_script(command:%s)", command);

	if(0 != (f = popen(command, "r"))) {
		zbx_snprintf_alloc(result, result_allocated, &result_offset, 8, "%d%c",
			SUCCEED,
			ZBX_DM_DELIMITER);

		while (NULL != fgets(buffer, sizeof(buffer)-1, f)) {
			zbx_snprintf_alloc(result, result_allocated, &result_offset, sizeof(buffer),
				"%s",
				buffer);
		}
		(*result)[result_offset] = '\0';

		pclose(f);
	} else {
		zbx_snprintf_alloc(result, result_allocated, &result_offset, 128,
			"%d%cNODE %d: Cannot execute [%s] error:%s",
			FAIL,
			ZBX_DM_DELIMITER,
			CONFIG_NODEID,
			command,
			strerror(errno));
	}
}

/******************************************************************************
 *                                                                            *
 * Function: send_script                                                      *
 *                                                                            *
 * Purpose: sending command to slave node                                     *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:  SUCCEED - processed successfully                            *
 *                FAIL - an error occured                                     *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	send_script(int nodeid, const char *data, char **result, int *result_allocated)
{
	DB_RESULT	dbresult;
	DB_ROW		dbrow;
	int		result_offset = 0;
	zbx_sock_t	sock;
	char		*answer;

	zabbix_log(LOG_LEVEL_DEBUG, "In send_script(nodeid:%d)", nodeid);

	dbresult = DBselect("select ip,port from nodes where nodeid=%d",
		nodeid);

	if (NULL != (dbrow = DBfetch(dbresult))) {
		if (SUCCEED == zbx_tcp_connect(&sock, dbrow[0], atoi(dbrow[1]), 0)) {
			if (FAIL == zbx_tcp_send(&sock, data)) {
				zbx_snprintf_alloc(result, result_allocated, &result_offset, 128,
					"%d%cNODE %d: Error while sending data to Node [%d] error: %s",
					FAIL,
					ZBX_DM_DELIMITER,
					CONFIG_NODEID,
					nodeid,
					zbx_tcp_strerror());
				goto exit_sock;
			}

			if (SUCCEED == zbx_tcp_recv(&sock, &answer/*, ZBX_TCP_READ_UNTIL_CLOSE*/)) {
				zbx_snprintf_alloc(result, result_allocated, &result_offset, strlen(answer)+1,
				"%s",
				answer);
			} else {
				
				zbx_snprintf_alloc(result, result_allocated, &result_offset, 128,
					"%d%cNODE %d: Error while receiving answer from Node [%d] error: %s",
					FAIL,
					ZBX_DM_DELIMITER,
					CONFIG_NODEID,
					nodeid,
					zbx_tcp_strerror());
				goto exit_sock;
			}
exit_sock:
			zbx_tcp_close(&sock);
		} else {
			zbx_snprintf_alloc(result, result_allocated, &result_offset, 128,
				"%d%cNODE %d: Unable to connect to Node [%d] error: %s",
				FAIL,
				ZBX_DM_DELIMITER,
				CONFIG_NODEID,
				nodeid,
				zbx_tcp_strerror());
		}
	} else {
		zbx_snprintf_alloc(result, result_allocated, &result_offset, 128,
			"%d%cNODE %d: Node [%d] is unknown",
			FAIL,
			ZBX_DM_DELIMITER,
			CONFIG_NODEID,
			nodeid);
	}
	DBfree_result(dbresult);
}

/******************************************************************************
 *                                                                            *
 * Function: get_next_point_to_node                                           *
 *                                                                            *
 * Purpose: find next point to slave node                                     *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:  SUCCEED - processed successfully                            *
 *                FAIL - an error occured                                     *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int	get_next_point_to_node(int current_nodeid, int slave_nodeid, int *nodeid)
{
	DB_RESULT	dbresult;
	DB_ROW		dbrow;
	int		id, res = FAIL;

	dbresult = DBselect("select nodeid from nodes where masterid=%d",
		current_nodeid);

	while (NULL != (dbrow = DBfetch(dbresult))) {
		id = atoi(dbrow[0]);
		if (id == slave_nodeid || SUCCEED == get_next_point_to_node(id, slave_nodeid, NULL)) {
			if (NULL != nodeid)
				*nodeid = id;
			res = SUCCEED;
			break;
		}
	}
	DBfree_result(dbresult);

	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: node_process_command                                             *
 *                                                                            *
 * Purpose: process command received from a master node or php                *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:  SUCCEED - processed successfully                            *
 *                FAIL - an error occured                                     *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int	node_process_command(const char *data, char **result)
{
	const char	*r;
	char		*tmp = NULL;
	int		tmp_allocated = 64, result_allocated = 1024;
	int		datalen;
	int		nodeid, next_nodeid;
	int		result_offset = 0;

	*result = zbx_malloc(*result, result_allocated);
	tmp = zbx_malloc(tmp, tmp_allocated);
	datalen = strlen(data);

	zabbix_log(LOG_LEVEL_DEBUG, "In node_process_command(datalen:%d)",
		datalen);

	r = data;
	r = zbx_get_next_field(r, &tmp, &tmp_allocated, ZBX_DM_DELIMITER); /* Constant 'Command' */
	r = zbx_get_next_field(r, &tmp, &tmp_allocated, ZBX_DM_DELIMITER); /* NodeID */
	nodeid = atoi(tmp);
	r = zbx_get_next_field(r, &tmp, &tmp_allocated, ZBX_DM_DELIMITER);

	if (nodeid == CONFIG_NODEID) {
		zabbix_log(LOG_LEVEL_WARNING, "NODE %d: Received command \"%s\"",
			CONFIG_NODEID,
			tmp);

		execute_script(tmp, result, &result_allocated);
	} else if (SUCCEED == get_next_point_to_node(CONFIG_NODEID, nodeid, &next_nodeid)) {
		zabbix_log( LOG_LEVEL_WARNING, "NODE %d: Sending command \"%s\" for nodeid %d"
			"to node %d",
			CONFIG_NODEID,
			tmp,
			nodeid,
			next_nodeid);

		send_script(next_nodeid, data, result, &result_allocated);
	} else {
		zbx_snprintf_alloc(result, &result_allocated, &result_offset, 128,
			"%d%cNODE %d: Node [%d] is unknown",
			FAIL,
			ZBX_DM_DELIMITER,
			CONFIG_NODEID,
			nodeid);
	}
	zbx_free(tmp);

	return SUCCEED;
}
