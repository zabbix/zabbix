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
#include "zbxjson.h"

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
	char	*host_esc;
	DB_RESULT result;
	DB_ROW	row;

	zabbix_log( LOG_LEVEL_DEBUG, "In send_list_of_active_checks()");

	host_esc = DBdyn_escape_string(host);

	if (0 != CONFIG_REFRESH_UNSUPPORTED) {
		result = DBselect("select i.key_,i.delay,i.lastlogsize from items i,hosts h "
			"where i.hostid=h.hostid and h.status=%d and i.type=%d and h.host='%s' "
			"and (i.status=%d or (i.status=%d and i.nextcheck<=%d)) and"ZBX_COND_NODEID,
			HOST_STATUS_MONITORED,
			ITEM_TYPE_ZABBIX_ACTIVE,
			host_esc,
			ITEM_STATUS_ACTIVE, ITEM_STATUS_NOTSUPPORTED, time(NULL),
			LOCAL_NODE("h.hostid"));
	} else {
		result = DBselect("select i.key_,i.delay,i.lastlogsize from items i,hosts h "
			"where i.hostid=h.hostid and h.status=%d and i.type=%d and h.host='%s' "
			"and i.status=%d and"ZBX_COND_NODEID,
			HOST_STATUS_MONITORED,
			ITEM_TYPE_ZABBIX_ACTIVE,
			host_esc,
			ITEM_STATUS_ACTIVE,
			LOCAL_NODE("h.hostid"));
	}

	zbx_free(host_esc);

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
			DBfree_result(result);
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
int	send_list_of_active_checks_json(zbx_sock_t *sock, struct zbx_json_parse *jp)
{
	char		host[HOST_HOST_LEN_MAX], *name_esc, params[MAX_STRING_LEN], pattern[MAX_STRING_LEN];
	DB_RESULT	result;
	DB_ROW		row;
	struct zbx_json	json;
	int		res = SUCCEED;

	char		**regexp = NULL;
	int		regexp_alloc = 32;
	int		regexp_num = 0, n;

	char		*sql = NULL;
	int		sql_alloc = 1024;
	int		sql_offset;

	zabbix_log(LOG_LEVEL_DEBUG, "In send_list_of_active_checks_json()");

	if (FAIL == zbx_json_value_by_name(jp, ZBX_PROTO_TAG_HOST, host, sizeof(host)))
	{
		zabbix_log(LOG_LEVEL_ERR, "No tag \"%s\" in JSON request",
				ZBX_PROTO_TAG_HOST);
		return FAIL;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "Host:%s", host);

	regexp = zbx_malloc(regexp, regexp_alloc);
	sql = zbx_malloc(sql, sql_alloc);

	name_esc = DBdyn_escape_string(host);

	sql_offset = 0;
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 512,
			"select i.key_,i.delay,i.lastlogsize from items i,hosts h "
			"where i.hostid=h.hostid and h.status=%d and i.type=%d and h.host='%s'",
			HOST_STATUS_MONITORED,
			ITEM_TYPE_ZABBIX_ACTIVE,
			name_esc);

	if (0 != CONFIG_REFRESH_UNSUPPORTED) {
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 256,
				" and (i.status=%d or (i.status=%d and i.nextcheck<=%d)) and" ZBX_COND_NODEID,
				ITEM_STATUS_ACTIVE, ITEM_STATUS_NOTSUPPORTED, time(NULL),
				LOCAL_NODE("h.hostid"));
	} else {
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 256,
				" and i.status=%d and" ZBX_COND_NODEID,
				ITEM_STATUS_ACTIVE,
				LOCAL_NODE("h.hostid"));
	}

	zbx_free(name_esc);

	zbx_json_init(&json, 8 * 1024);
	zbx_json_addstring(&json, ZBX_PROTO_TAG_RESPONSE, ZBX_PROTO_VALUE_SUCCESS, ZBX_JSON_TYPE_STRING);
	zbx_json_addarray(&json, ZBX_PROTO_TAG_DATA);

	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		zbx_json_addobject(&json, NULL);
		zbx_json_addstring(&json, ZBX_PROTO_TAG_KEY, row[0], ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&json, ZBX_PROTO_TAG_DELAY, row[1], ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&json, ZBX_PROTO_TAG_LOGLASTSIZE, row[2], ZBX_JSON_TYPE_STRING);
		zbx_json_close(&json);

		/* Special processing for log[] and eventlog[] items */
		do {	/* simple try realization */
			if (0 != strncmp(row[0], "log[", 4) && 0 != strncmp(row[0], "eventlog[", 9))
				break;

			if (2 != parse_command(row[0], NULL, 0, params, MAX_STRING_LEN))
				break;;
				
			if (0 != get_param(params, 2, pattern, sizeof(pattern)))
				break;

			if (*pattern != '@')
				break;

			for (n = 0; n < regexp_num; n++)
				if (0 == strcmp(regexp[n], pattern + 1))
					break;

			if (n != regexp_num)
				break;

			if (regexp_num == regexp_alloc)
			{
				regexp_alloc += 32;
				regexp = zbx_realloc(regexp, regexp_alloc);
			}

			regexp[regexp_num++] = strdup(pattern + 1);
		} while (0);	/* simple try realization */
	}
	zbx_json_close(&json);

	DBfree_result(result);

	if (0 != regexp_num)
	{
		zbx_json_addarray(&json, ZBX_PROTO_TAG_REGEXP);

		sql_offset = 0;
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 512,
				"select r.name,e.expression,e.expression_type,e.exp_delimiter,e.case_sensitive"
				" from regexps r,expressions e where r.regexpid=e.regexpid and r.name in (");

		for (n = 0; n < regexp_num; n++)
		{
			name_esc = DBdyn_escape_string(regexp[n]);
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 512, "%s'%s'",
					n == 0 ? "" : ",",
					name_esc);
			zbx_free(name_esc);
			zbx_free(regexp[n]);
		}
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 8, ")");

		result = DBselect("%s", sql);
		while (NULL != (row = DBfetch(result)))
		{
			zbx_json_addobject(&json, NULL);
			zbx_json_addstring(&json, "name", row[0], ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&json, "expression", row[1], ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&json, "expression_type", row[2], ZBX_JSON_TYPE_INT);
			zbx_json_addstring(&json, "exp_delimiter", row[3], ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&json, "case_sensitive", row[4], ZBX_JSON_TYPE_INT);
			zbx_json_close(&json);
		}
		DBfree_result(result);
	}
	zbx_free(regexp);

	zabbix_log(LOG_LEVEL_DEBUG, "Sending [%s]",
			json.buffer);

	if (SUCCEED != zbx_tcp_send_raw(sock, json.buffer))
	{
		zabbix_log(LOG_LEVEL_WARNING, "Error while sending list of active checks");
		res = FAIL;
	}

	zbx_json_free(&json);
	zbx_free(sql);

	return res;
}
