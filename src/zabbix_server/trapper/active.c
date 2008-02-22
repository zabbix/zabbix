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
int	send_list_of_active_checks(zbx_sock_t *sock, const char *host)
{
	char	s[MAX_STRING_LEN];
	DB_RESULT result;
	DB_ROW	row;

	zabbix_log( LOG_LEVEL_DEBUG, "In send_list_of_active_checks()");

	if (0 != CONFIG_REFRESH_UNSUPPORTED) {
		result = DBselect("select i.key_,i.delay,i.lastlogsize from items i,hosts h"
			" where i.hostid=h.hostid and h.status=%d and i.type=%d and h.host='%s'"
			" and h.proxyid=0 and (i.status=%d or (i.status=%d and i.nextcheck<=%d))" DB_NODE,
			HOST_STATUS_MONITORED,
			ITEM_TYPE_ZABBIX_ACTIVE,
			host,
			ITEM_STATUS_ACTIVE, ITEM_STATUS_NOTSUPPORTED, time(NULL),
			DBnode_local("h.hostid"));
	} else {
		result = DBselect("select i.key_,i.delay,i.lastlogsize from items i,hosts h"
			" where i.hostid=h.hostid and h.status=%d and i.type=%d and h.host='%s'"
			" and h.proxyid=0 and i.status=%d" DB_NODE,
			HOST_STATUS_MONITORED,
			ITEM_TYPE_ZABBIX_ACTIVE,
			host,
			ITEM_STATUS_ACTIVE,
			DBnode_local("h.hostid"));
	}

	while((row=DBfetch(result)))
	{

		zbx_snprintf(s,sizeof(s),"%s:%s:%s\n",
			row[0],
			row[1],
			row[2]);
		zabbix_log( LOG_LEVEL_DEBUG, "Sending [%s]",
			s);

		if( zbx_tcp_send_raw(sock,s) != SUCCEED )
		{
			zabbix_log( LOG_LEVEL_WARNING, "Error while sending list of active checks");
			return  FAIL;
		}
	}
	DBfree_result(result);

	zbx_snprintf(s,sizeof(s),"%s\n",
		"ZBX_EOF");
	zabbix_log( LOG_LEVEL_DEBUG, "Sending [%s]",
		s);

	if( zbx_tcp_send_raw(sock,s) != SUCCEED )
	{
		zabbix_log( LOG_LEVEL_WARNING, "Error while sending list of active checks");
		return  FAIL;
	}

	return  SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: send_list_of_active_checks_json                                  *
 *                                                                            *
 * Purpose: send list of active checks to the host                            *
 *                                                                            *
 * Parameters: sockfd - open socket of server-agent connection                *
 *             json - request buffer                                          *
 *                                                                            *
 * Return value:  SUCCEED - list of active checks sent succesfully            *
 *                FAIL - an error occured                                     *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int	send_list_of_active_checks_json(zbx_sock_t *sock, struct zbx_json_parse *json)
{
	char	host[MAX_STRING_LEN];
	char	s[MAX_STRING_LEN];
	const	char *pf;
	DB_RESULT result;
	DB_ROW	row;

	struct zbx_json response;

	zabbix_log( LOG_LEVEL_WARNING, "In send_list_of_active_checks()");

	if (NULL == (pf = zbx_json_pair_by_name(json, ZBX_PROTO_TAG_HOST)) ||
			NULL == (pf = zbx_json_decodevalue(pf, host, sizeof(host))))
	{
		zabbix_log( LOG_LEVEL_WARNING, "No tag \"%s\" in JSON request",
			ZBX_PROTO_TAG_HOST);
		return FAIL;
	}

	zabbix_log( LOG_LEVEL_WARNING, "Host:%s", host);

	if (0 != CONFIG_REFRESH_UNSUPPORTED) {
		result = DBselect("select i.key_,i.delay,i.lastlogsize from items i,hosts h"
			" where i.hostid=h.hostid and h.status=%d and i.type=%d and h.host='%s'"
			" and h.proxyid=0 and (i.status=%d or (i.status=%d and i.nextcheck<=%d))" DB_NODE,
			HOST_STATUS_MONITORED,
			ITEM_TYPE_ZABBIX_ACTIVE,
			host,
			ITEM_STATUS_ACTIVE, ITEM_STATUS_NOTSUPPORTED, time(NULL),
			DBnode_local("h.hostid"));
	} else {
		result = DBselect("select i.key_,i.delay,i.lastlogsize from items i,hosts h"
			" where i.hostid=h.hostid and h.status=%d and i.type=%d and h.host='%s'"
			" and h.proxyid=0 and i.status=%d" DB_NODE,
			HOST_STATUS_MONITORED,
			ITEM_TYPE_ZABBIX_ACTIVE,
			host,
			ITEM_STATUS_ACTIVE,
			DBnode_local("h.hostid"));
	}

	zbx_json_init(&response, 8*1024);
	zbx_json_addstring(&response, ZBX_PROTO_TAG_RESPONSE, ZBX_PROTO_VALUE_SUCCESS, ZBX_JSON_TYPE_STRING);
	zbx_json_addarray(&response, ZBX_PROTO_TAG_DATA);

	while((row=DBfetch(result)))
	{
		zbx_json_addobject(&response, NULL);
		zbx_json_addstring(&response, ZBX_PROTO_TAG_KEY, row[0], ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&response, ZBX_PROTO_TAG_DELAY, row[1], ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&response, ZBX_PROTO_TAG_LOGLASTSIZE, row[2], ZBX_JSON_TYPE_STRING);
		zbx_json_close(&response);
	}
	DBfree_result(result);

	zabbix_log( LOG_LEVEL_DEBUG, "Sending [%s]",
		response.buffer);

	if( zbx_tcp_send_raw(sock, response.buffer) != SUCCEED )
	{
		zabbix_log( LOG_LEVEL_WARNING, "Error while sending list of active checks");
		return  FAIL;
	}

	zbx_json_free(&response);

	return  SUCCEED;
}
