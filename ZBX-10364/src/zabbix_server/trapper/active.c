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
#include "dbcache.h"
#include "log.h"

#include "active.h"

extern unsigned char	daemon_type;

/******************************************************************************
 *                                                                            *
 * Function: get_hostid_by_host                                               *
 *                                                                            *
 * Purpose: check for host name and return hostid                             *
 *                                                                            *
 * Parameters: host - [IN] require size 'HOST_HOST_LEN_MAX'                   *
 *                                                                            *
 * Return value:  SUCCEED - host is found                                     *
 *                FAIL - an error occurred or host not found                  *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int	get_hostid_by_host(const char *host, zbx_uint64_t *hostid, char *error)
{
	char		*host_esc;
	DB_RESULT	result;
	DB_ROW		row;
	int		res = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In get_hostid_by_host() host:'%s'", host);

	if (FAIL == zbx_check_hostname(host))
	{
		zbx_snprintf(error, MAX_STRING_LEN, "invalid host name [%s]", host);
		return res;
	}

	host_esc = DBdyn_escape_string(host);

	result = DBselect(
			"select hostid,status"
			" from hosts"
			" where host='%s'"
				" and status in (%d,%d)"
		       		" and proxy_hostid=0"
				DB_NODE,
			host_esc,
			HOST_STATUS_MONITORED,
			HOST_STATUS_NOT_MONITORED,
			DBnode_local("hostid"));

	if (NULL != (row = DBfetch(result)))
	{
		if (HOST_STATUS_MONITORED == atoi(row[1]))
		{
			ZBX_STR2UINT64(*hostid, row[0]);
			res = SUCCEED;
		}
		else
			zbx_snprintf(error, MAX_STRING_LEN, "host [%s] not monitored", host);
	}
	else
	{
		zbx_snprintf(error, MAX_STRING_LEN, "host [%s] not found", host);

		DBbegin();

		if (0 != (daemon_type & ZBX_DAEMON_TYPE_SERVER))
		{
			DBregister_host(0, host, (int)time(NULL));
		}
		else if (0 != (daemon_type & ZBX_DAEMON_TYPE_PROXY))
		{
			DBproxy_register_host(host);
		}

		DBcommit();
	}

	DBfree_result(result);

	zbx_free(host_esc);

	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: send_list_of_active_checks                                       *
 *                                                                            *
 * Purpose: send list of active checks to the host                            *
 *                                                                            *
 * Parameters: sock - open socket of server-agent connection                  *
 *             request - request buffer                                       *
 *                                                                            *
 * Return value:  SUCCEED - list of active checks sent successfully           *
 *                FAIL - an error occurred                                    *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: format of the request: ZBX_GET_ACTIVE_CHECKS\n<host name>\n      *
 *           format of the list: key:delay:last_log_size                      *
 *                                                                            *
 ******************************************************************************/
int	send_list_of_active_checks(zbx_sock_t *sock, char *request)
{
	char		*host = NULL, *p;
	DB_RESULT	result;
	DB_ROW		row;
	char		*buffer = NULL;
	int		buffer_alloc = 2048;
	int		buffer_offset = 0;
	int		res = FAIL;
	zbx_uint64_t	hostid;
	char		error[MAX_STRING_LEN];
	DC_ITEM		dc_item;

	zabbix_log(LOG_LEVEL_DEBUG, "In send_list_of_active_checks()");

	if (NULL != (host = strchr(request, '\n')))
	{
		host++;

		if (NULL != (p = strchr(host, '\n')))
			*p = '\0';
	}
	else
	{
		zbx_snprintf(error, sizeof(error), "host is null");
		goto out;
	}

	if (FAIL == get_hostid_by_host(host, &hostid, error))
		goto out;

	buffer = zbx_malloc(buffer, buffer_alloc);

	buffer_offset = 0;
	zbx_snprintf_alloc(&buffer, &buffer_alloc, &buffer_offset, 1024,
			"select i.key_,i.delay,i.lastlogsize from items i,hosts h"
			" where i.hostid=h.hostid and h.status=%d and i.type=%d and h.hostid=" ZBX_FS_UI64
			" and h.proxy_hostid=0",
			HOST_STATUS_MONITORED,
			ITEM_TYPE_ZABBIX_ACTIVE,
			hostid);

	if (0 != CONFIG_REFRESH_UNSUPPORTED)
	{
		zbx_snprintf_alloc(&buffer, &buffer_alloc, &buffer_offset, 256,
				" and (i.status=%d or (i.status=%d and i.lastclock+%d<=%d))",
				ITEM_STATUS_ACTIVE, ITEM_STATUS_NOTSUPPORTED,
				CONFIG_REFRESH_UNSUPPORTED, time(NULL));
	}
	else
	{
		zbx_snprintf_alloc(&buffer, &buffer_alloc, &buffer_offset, 256,
				" and i.status=%d",
				ITEM_STATUS_ACTIVE);
	}

	result = DBselect("%s", buffer);

	buffer_offset = 0;
	while (NULL != (row = DBfetch(result)))
	{
		if (FAIL == DCconfig_get_item_by_key(&dc_item, (zbx_uint64_t)0, host, row[0]))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "Item '%s' was not found in the server cache. Not sending now.", row[0]);
			continue;
		}

		zabbix_log(LOG_LEVEL_DEBUG, "Item '%s' was successfully found in the server cache. Sending.", row[0]);

		zbx_snprintf_alloc(&buffer, &buffer_alloc, &buffer_offset, 512, "%s:%s:%s\n",
				row[0],		/* item key */
				row[1],		/* item delay */
				row[2]);	/* item lastlogsize */
	}
	DBfree_result(result);

	zbx_snprintf_alloc(&buffer, &buffer_alloc, &buffer_offset, 512, "ZBX_EOF\n");

	zabbix_log(LOG_LEVEL_DEBUG, "Sending [%s]",
			buffer);

	alarm(CONFIG_TIMEOUT);
	if (SUCCEED != zbx_tcp_send_raw(sock, buffer))
		zbx_snprintf(error, MAX_STRING_LEN, "%s", zbx_tcp_strerror());
	else
		res = SUCCEED;
	alarm(0);

	zbx_free(buffer);

out:
	if (FAIL == res)
		zabbix_log(LOG_LEVEL_WARNING, "Send list of active checks to [%s] failed: %s",
				get_ip_by_socket(sock), error);

	return res;
}

static void	add_regexp_name(char ***regexp, int *regexp_alloc, int *regexp_num, const char *regexp_name)
{
	int	i;

	for (i = 0; i < *regexp_num; i++)
		if (0 == strcmp((*regexp)[i], regexp_name))
			return;

	if (i == *regexp_num) {
		if (*regexp_num == *regexp_alloc) {
			*regexp_alloc += 32;
			*regexp = zbx_realloc(*regexp, sizeof(char *) * *regexp_alloc);
		}
		(*regexp)[(*regexp_num)++] = strdup(regexp_name);
	}
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
 * Return value:  SUCCEED - list of active checks sent successfully           *
 *                FAIL - an error occurred                                    *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int	send_list_of_active_checks_json(zbx_sock_t *sock, struct zbx_json_parse *jp)
{
	char		host[HOST_HOST_LEN_MAX], *name_esc, params[MAX_STRING_LEN],
			pattern[MAX_STRING_LEN], tmp[32],
			key_severity[MAX_STRING_LEN], key_logeventid[MAX_STRING_LEN];
	DB_RESULT	result;
	DB_ROW		row;
	DB_ITEM		item;
	struct zbx_json	json;
	int		res = FAIL;
	zbx_uint64_t	hostid;
	char		error[MAX_STRING_LEN];
	DC_ITEM		dc_item;

	char		**regexp = NULL;
	int		regexp_alloc = 0;
	int		regexp_num = 0, n;

	char		*sql = NULL;
	int		sql_alloc = 2048;
	int		sql_offset;

	zabbix_log(LOG_LEVEL_DEBUG, "In send_list_of_active_checks_json()");

	if (FAIL == zbx_json_value_by_name(jp, ZBX_PROTO_TAG_HOST, host, sizeof(host)))
	{
		zbx_snprintf(error, MAX_STRING_LEN, "%s", zbx_json_strerror());
		goto error;
	}

	if (FAIL == get_hostid_by_host(host, &hostid, error))
		goto error;

	sql = zbx_malloc(sql, sql_alloc);

	name_esc = DBdyn_escape_string(host);

	sql_offset = 0;
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 1024,
			"select %s where i.hostid=h.hostid and h.status=%d and i.type=%d and h.hostid=" ZBX_FS_UI64
			" and h.proxy_hostid=0",
			ZBX_SQL_ITEM_SELECT,
			HOST_STATUS_MONITORED,
			ITEM_TYPE_ZABBIX_ACTIVE,
			hostid);

	if (0 != CONFIG_REFRESH_UNSUPPORTED)
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 256, " and (i.status=%d or (i.status=%d and i.lastclock+%d<=%d))",
				ITEM_STATUS_ACTIVE, ITEM_STATUS_NOTSUPPORTED,
				CONFIG_REFRESH_UNSUPPORTED, time(NULL));
	else
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 256, " and i.status=%d",
				ITEM_STATUS_ACTIVE);

	zbx_free(name_esc);

	zbx_json_init(&json, ZBX_JSON_STAT_BUF_LEN);
	zbx_json_addstring(&json, ZBX_PROTO_TAG_RESPONSE, ZBX_PROTO_VALUE_SUCCESS, ZBX_JSON_TYPE_STRING);
	zbx_json_addarray(&json, ZBX_PROTO_TAG_DATA);

	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		if (FAIL == DCconfig_get_item_by_key(&dc_item, (zbx_uint64_t)0, host, row[1]))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "Item '%s' was not found in the server cache. Not sending now.", row[1]);
			continue;
		}

		zabbix_log(LOG_LEVEL_DEBUG, "Item '%s' was successfully found in the server cache. Sending.", row[1]);

		DBget_item_from_db(&item, row);

		zbx_json_addobject(&json, NULL);
		zbx_json_addstring(&json, ZBX_PROTO_TAG_KEY, item.key, ZBX_JSON_TYPE_STRING);
		if (0 != strcmp(item.key, item.key_orig))
			zbx_json_addstring(&json, ZBX_PROTO_TAG_KEY_ORIG, item.key_orig, ZBX_JSON_TYPE_STRING);
		zbx_snprintf(tmp, sizeof(tmp), "%d", item.delay);
		zbx_json_addstring(&json, ZBX_PROTO_TAG_DELAY, tmp, ZBX_JSON_TYPE_STRING);
		/* The agent expects ALWAYS to have lastlogsize and mtime tags. Removing those would cause older agents to fail. */
		zbx_snprintf(tmp, sizeof(tmp), "%d", item.lastlogsize);
		zbx_json_addstring(&json, ZBX_PROTO_TAG_LOGLASTSIZE, tmp, ZBX_JSON_TYPE_STRING);
		zbx_snprintf(tmp, sizeof(tmp), "%d", item.mtime);
		zbx_json_addstring(&json, ZBX_PROTO_TAG_MTIME, tmp, ZBX_JSON_TYPE_STRING);
		zbx_json_close(&json);

		/* Special processing for log[] and logrt[] items */
		do {	/* simple try realization */

			/* log[filename,pattern,encoding,maxlinespersec] */
			/* logrt[filename_format,pattern,encoding,maxlinespersec] */

			if (0 != strncmp(item.key, "log[", 4) && 0 != strncmp(item.key, "logrt[", 6))
				break;

			if (2 != parse_command(item.key, NULL, 0, params, MAX_STRING_LEN))
				break;

			/*dealing with `pattern' parameter*/
			if (0 == get_param(params, 2, pattern, sizeof(pattern)) &&
				*pattern == '@')
					add_regexp_name(&regexp, &regexp_alloc, &regexp_num, pattern + 1);
		} while (0);	/* simple try realization */

		/* Special processing for eventlog[] items */
		do {	/* simple try realization */

			/* eventlog[filename,pattern,severity,source,logeventid,maxlinespersec] */

			if (0 != strncmp(item.key, "eventlog[", 9))
				break;

			if (2 != parse_command(item.key, NULL, 0, params, MAX_STRING_LEN))
				break;

			/*dealing with `pattern' parameter*/
			if (0 == get_param(params, 2, pattern, sizeof(pattern)) &&
				*pattern == '@')
					add_regexp_name(&regexp, &regexp_alloc, &regexp_num, pattern + 1);

			/*dealing with `severity' parameter*/
			if (0 == get_param(params, 3, key_severity, sizeof(key_severity)) &&
				*key_severity == '@')
					add_regexp_name(&regexp, &regexp_alloc, &regexp_num, key_severity + 1);

			/*dealing with `logeventid' parameter*/
			if (0 == get_param(params, 5, key_logeventid, sizeof(key_logeventid)) &&
				*key_logeventid == '@')
					add_regexp_name(&regexp, &regexp_alloc, &regexp_num, key_logeventid + 1);
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

	alarm(CONFIG_TIMEOUT);
	if (SUCCEED != zbx_tcp_send(sock, json.buffer))
		zbx_snprintf(error, MAX_STRING_LEN, "%s", zbx_tcp_strerror());
	else
		res = SUCCEED;
	alarm(0);

	zbx_json_free(&json);
	zbx_free(sql);

	return res;
error:
	zabbix_log(LOG_LEVEL_WARNING, "Sending list of active checks to [%s] failed: %s",
			get_ip_by_socket(sock), error);

	zbx_json_init(&json, ZBX_JSON_STAT_BUF_LEN);
	zbx_json_addstring(&json, ZBX_PROTO_TAG_RESPONSE, ZBX_PROTO_VALUE_FAILED, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(&json, ZBX_PROTO_TAG_INFO, error, ZBX_JSON_TYPE_STRING);

	zabbix_log(LOG_LEVEL_DEBUG, "Sending [%s]",
			json.buffer);

	res = zbx_tcp_send(sock, json.buffer);

	zbx_json_free(&json);

	return res;
}
