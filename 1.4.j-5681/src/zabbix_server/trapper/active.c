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
#include <iconv.h>

#include "common.h"
#include "db.h"
#include "log.h"
#include "zlog.h"

#include "active.h"

/******************************************************************************
 *                                                                            *
 * Function: check_encode                                                     *
 *                                                                            *
 * Purpose: convert encoding.                                                 *
 *                                                                            *
 * Parameters:                                                                * 
 *                                                                            *
 * Return value:                                                              * 
 *                                                                            *
 * Author:                                                                    *
 *                                                                            *
 * Comments:                                                                  * 
 *                                                                            *
 ******************************************************************************/
void check_encode(const char *key, char *s)
{
	char	s2[MAX_STRING_LEN];
	char	params[MAX_STRING_LEN];
	char	encoding[MAX_STRING_LEN];
	size_t	ss;
	size_t	ss2;
	char	*ps;
	char	*ps2;
	iconv_t	ic;

	if (strncmp(key,"log[",4) != 0 && strncmp(key,"eventlog[",9) != 0)
		return;

	if (parse_command(key, NULL, 0, params, sizeof(params)) != 2)
		return;
			
	if (num_param(params) < 3)
		return;

	if (get_param(params, 3, encoding, sizeof(encoding)) != 0)
		return;

	if (strcmp(encoding, "sjis") != 0 &&
		strcmp(encoding, "cp932") != 0 &&
		strcmp(encoding, "ujis") != 0 &&
		strcmp(encoding, "eucjpms") != 0 &&
		strcmp(encoding, "eucjp-ms") != 0 )
		return;

	ic = iconv_open(encoding, "utf8");
	if (ic == (iconv_t)(-1))
		return;

	ss = strlen(s);
	ss2 = MAX_STRING_LEN;
	ps = s;
	ps2 = s2;
	if (iconv(ic, &ps, &ss, &ps2, &ss2) != (size_t)(-1))
		zbx_strlcpy(s, s2, MAX_STRING_LEN - ss2 + 1);

	iconv_close(ic);
}

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
		result = DBselect("select i.key_,i.delay,i.lastlogsize from items i,hosts h "
			"where i.hostid=h.hostid and h.status=%d and i.type=%d and h.host='%s' "
			"and (i.status=%d or (i.status=%d and i.nextcheck<=%d)) and"ZBX_COND_NODEID,
			HOST_STATUS_MONITORED,
			ITEM_TYPE_ZABBIX_ACTIVE,
			host,
			ITEM_STATUS_ACTIVE, ITEM_STATUS_NOTSUPPORTED, time(NULL),
			LOCAL_NODE("h.hostid"));
	} else {
		result = DBselect("select i.key_,i.delay,i.lastlogsize from items i,hosts h "
			"where i.hostid=h.hostid and h.status=%d and i.type=%d and h.host='%s' "
			"and i.status=%d and"ZBX_COND_NODEID,
			HOST_STATUS_MONITORED,
			ITEM_TYPE_ZABBIX_ACTIVE,
			host,
			ITEM_STATUS_ACTIVE,
			LOCAL_NODE("h.hostid"));
	}

	while((row=DBfetch(result)))
	{
		zbx_snprintf(s,sizeof(s),"%s:%s:%s\n",
			row[0],
			row[1],
			row[2]);
		check_encode(row[0], s);

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
