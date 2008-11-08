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

#include "common.h"
#include "db.h"
#include "log.h"

#include "active.h"

/******************************************************************************
 *                                                                            *
 * Function: send_list_of_active_checks                                       *
 *                                                                            *
 * Purpose: send list of active checks to the host                            *
 *                                                                            *
 * Parameters: sock - open socket of server-agent connection                  *
 *             request - request buffer                                       *
 *                                                                            *
 * Return value:  SUCCEED - list of active checks sent succesfully            *
 *                FAIL - an error occured                                     *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: format of the request: ZBX_GET_ACTIVE_CHECKS\n<host name>\n      *
 *           format of the list: key:delay:last_log_size                      *
 *                                                                            *
 ******************************************************************************/
int	send_list_of_active_checks(zbx_sock_t *sock, char *request)
{
	char		*host = NULL, *host_esc, *p;
	DB_RESULT	result;
	DB_ROW		row;
	char		*buffer = NULL;
	int		buffer_alloc = 2048;
	int		buffer_offset = 0;
	int		res = SUCCEED;

	zabbix_log( LOG_LEVEL_DEBUG, "In send_list_of_active_checks()");

	if (NULL != (host = strchr(request, '\n')))
		host++;
	if (NULL != (p = strchr(host, '\n')))
		*p = '\0';

	if (NULL == host)
	{
		zabbix_log(LOG_LEVEL_ERR, "ZBX_GET_ACTIVE_CHECKS: host is null. Ignoring.");
		return FAIL;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "Host:%s", host);

	buffer = zbx_malloc(buffer, buffer_alloc);

	host_esc = DBdyn_escape_string(host);

	buffer_offset = 0;
	zbx_snprintf_alloc(&buffer, &buffer_alloc, &buffer_offset, 1024,
			"select i.key_,i.delay,i.lastlogsize from items i,hosts h"
			" where i.hostid=h.hostid and h.status=%d and i.type=%d and h.host='%s' and h.proxy_hostid=0",
			HOST_STATUS_MONITORED,
			ITEM_TYPE_ZABBIX_ACTIVE,
			host_esc);

	if (0 != CONFIG_REFRESH_UNSUPPORTED) {
		zbx_snprintf_alloc(&buffer, &buffer_alloc, &buffer_offset, 256,
				" and (i.status=%d or (i.status=%d and i.nextcheck<=%d))" DB_NODE,
				ITEM_STATUS_ACTIVE, ITEM_STATUS_NOTSUPPORTED, time(NULL),
				DBnode_local("h.hostid"));
	} else {
		zbx_snprintf_alloc(&buffer, &buffer_alloc, &buffer_offset, 256,
				" and i.status=%d" DB_NODE,
				ITEM_STATUS_ACTIVE,
				DBnode_local("h.hostid"));
	}

	zbx_free(host_esc);

	result = DBselect("%s", buffer);

	buffer_offset = 0;
	while (NULL != (row = DBfetch(result)))
	{
		zbx_snprintf_alloc(&buffer, &buffer_alloc, &buffer_offset, 512, "%s:%s:%s\n",
				row[0],
				row[1],
				row[2]);
	}
	DBfree_result(result);

	zbx_snprintf_alloc(&buffer, &buffer_alloc, &buffer_offset, 512, "ZBX_EOF\n");

	zabbix_log(LOG_LEVEL_DEBUG, "Sending [%s]",
			buffer);

	if (SUCCEED != zbx_tcp_send_raw(sock, buffer))
	{
		zabbix_log(LOG_LEVEL_WARNING, "Error while sending list of active checks");
		res = FAIL;
	}

	zbx_free(buffer);

	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: send_list_of_active_checks_json                                  *
 *                                                                            *
 * Purpose: send list of active checks to the host                            *
 *                                                                            *
 * Parameters: sock - open socket of server-agent connection                  *
 *             json - request buffer                                          *
 *                                                                            *
 * Return value:  SUCCEED - list of active checks sent succesfully            *
 *                FAIL - an error occured                                     *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int	send_list_of_active_checks_json(zbx_sock_t *sock, struct zbx_json_parse *jp)
{
	char		host[MAX_STRING_LEN], tmp[32];
	DB_RESULT	result;
	DB_ROW		row;
	DB_ITEM		item;
	struct zbx_json	json;

	zabbix_log( LOG_LEVEL_DEBUG, "In send_list_of_active_checks_json()");

	if (FAIL == zbx_json_value_by_name(jp, ZBX_PROTO_TAG_HOST, host, sizeof(host)))
	{
		zabbix_log( LOG_LEVEL_WARNING, "No tag \"%s\" in JSON request",
			ZBX_PROTO_TAG_HOST);
		return FAIL;
	}

	zabbix_log( LOG_LEVEL_DEBUG, "Host:%s", host);

	if (0 != CONFIG_REFRESH_UNSUPPORTED) {
		result = DBselect("select %s where i.hostid=h.hostid and h.status=%d and i.type=%d and h.host='%s'"
			" and h.proxy_hostid=0 and (i.status=%d or (i.status=%d and i.nextcheck<=%d))" DB_NODE,
			ZBX_SQL_ITEM_SELECT,
			HOST_STATUS_MONITORED,
			ITEM_TYPE_ZABBIX_ACTIVE,
			host,
			ITEM_STATUS_ACTIVE, ITEM_STATUS_NOTSUPPORTED, time(NULL),
			DBnode_local("h.hostid"));
	} else {
		result = DBselect("select %s where i.hostid=h.hostid and h.status=%d and i.type=%d and h.host='%s'"
			" and h.proxy_hostid=0 and i.status=%d" DB_NODE,
			ZBX_SQL_ITEM_SELECT,
			HOST_STATUS_MONITORED,
			ITEM_TYPE_ZABBIX_ACTIVE,
			host,
			ITEM_STATUS_ACTIVE,
			DBnode_local("h.hostid"));
	}

	zbx_json_init(&json, 8*1024);
	zbx_json_addstring(&json, ZBX_PROTO_TAG_RESPONSE, ZBX_PROTO_VALUE_SUCCESS, ZBX_JSON_TYPE_STRING);
	zbx_json_addarray(&json, ZBX_PROTO_TAG_DATA);

	while((row=DBfetch(result)))
	{
		DBget_item_from_db(&item, row);

		zbx_json_addobject(&json, NULL);
		zbx_json_addstring(&json, ZBX_PROTO_TAG_KEY, item.key, ZBX_JSON_TYPE_STRING);
		if (0 != strcmp(item.key, item.key_orig))
			zbx_json_addstring(&json, ZBX_PROTO_TAG_KEY_ORIG, item.key_orig, ZBX_JSON_TYPE_STRING);
		zbx_snprintf(tmp, sizeof(tmp), "%d", item.delay);
		zbx_json_addstring(&json, ZBX_PROTO_TAG_DELAY, tmp, ZBX_JSON_TYPE_STRING);
		zbx_snprintf(tmp, sizeof(tmp), "%d", item.lastlogsize);
		zbx_json_addstring(&json, ZBX_PROTO_TAG_LOGLASTSIZE, tmp, ZBX_JSON_TYPE_STRING);
		zbx_json_close(&json);
	}
	DBfree_result(result);

	zabbix_log( LOG_LEVEL_DEBUG, "Sending [%s]",
		json.buffer);

	if( zbx_tcp_send_raw(sock, json.buffer) != SUCCEED )
	{
		zabbix_log( LOG_LEVEL_WARNING, "Error while sending list of active checks");
		return  FAIL;
	}

	zbx_json_free(&json);

	return  SUCCEED;
}
