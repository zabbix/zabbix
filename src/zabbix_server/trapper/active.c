/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "common.h"
#include "db.h"
#include "dbcache.h"
#include "log.h"
#include "zbxserver.h"

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
static int	get_hostid_by_host(const char *host, const char *ip, unsigned short port, zbx_uint64_t *hostid, char *error)
{
	const char	*__function_name = "get_hostid_by_host";

	char		*host_esc, dns[INTERFACE_DNS_LEN_MAX];
	DB_RESULT	result;
	DB_ROW		row;
	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() host:'%s'", __function_name, host);

	if (FAIL == zbx_check_hostname(host))
	{
		zbx_snprintf(error, MAX_STRING_LEN, "invalid host name [%s]", host);
		goto out;
	}

	host_esc = DBdyn_escape_string(host);

	result = DBselect(
			"select hostid,status"
			" from hosts"
			" where host='%s'"
				" and status in (%d,%d)"
		       		" and proxy_hostid is null"
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
			ret = SUCCEED;
		}
		else
			zbx_snprintf(error, MAX_STRING_LEN, "host [%s] not monitored", host);
	}
	else
	{
		zbx_snprintf(error, MAX_STRING_LEN, "host [%s] not found", host);

		/* remove ::ffff: prefix from IPv4-mapped IPv6 addresses */
		if (0 == strncmp("::ffff:", ip, 7) && SUCCEED == is_ip4(ip + 7))
			ip += 7;

		alarm(CONFIG_TIMEOUT);
		zbx_gethost_by_ip(ip, dns, sizeof(dns));
		alarm(0);

		DBbegin();

		if (0 != (daemon_type & ZBX_DAEMON_TYPE_SERVER))
		{
			DBregister_host(0, host, ip, dns, port, (int)time(NULL));
		}
		else if (0 != (daemon_type & ZBX_DAEMON_TYPE_PROXY))
		{
			DBproxy_register_host(host, ip, dns, port);
		}

		DBcommit();
	}

	DBfree_result(result);

	zbx_free(host_esc);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

typedef struct
{
	zbx_uint64_t	lastlogsize;
	int		mtime;
}
zbx_active_t;

static void	get_list_of_active_checks(zbx_uint64_t hostid, zbx_uint64_t **itemids, zbx_active_t **items, size_t *items_num)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		refresh_unsupported;
	char		*sql = NULL;
	size_t		sql_alloc = 256, sql_offset = 0, items_alloc = 0;

	assert(NULL == *itemids);
	assert(NULL == *items);
	assert(0 == *items_num);

	sql = zbx_malloc(sql, sql_alloc);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select i.itemid,i.lastlogsize,i.mtime"
			" from items i,hosts h"
			" where i.hostid=h.hostid"
				" and h.status=%d"
				" and i.type=%d"
				" and i.flags<>%d"
				" and h.hostid=" ZBX_FS_UI64
				" and h.proxy_hostid is null",
			HOST_STATUS_MONITORED, ITEM_TYPE_ZABBIX_ACTIVE, ZBX_FLAG_DISCOVERY_CHILD, hostid);

	if (0 != *(int *)DCconfig_get_config_data(&refresh_unsupported, CONFIG_REFRESH_UNSUPPORTED))
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				" and (i.status=%d or (i.status=%d and i.lastclock+%d<=%d))",
				ITEM_STATUS_ACTIVE, ITEM_STATUS_NOTSUPPORTED, refresh_unsupported, time(NULL));
	}
	else
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " and i.status=%d", ITEM_STATUS_ACTIVE);

	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		if (items_alloc == *items_num)
		{
			items_alloc += 256;
			*itemids = zbx_realloc(*itemids, sizeof(zbx_uint64_t) * items_alloc);
			*items = zbx_realloc(*items, sizeof(zbx_active_t) * items_alloc);
		}

		ZBX_STR2UINT64((*itemids)[*items_num], row[0]);
		ZBX_STR2UINT64((*items)[*items_num].lastlogsize, row[1]);
		(*items)[*items_num].mtime = atoi(row[2]);
		(*items_num)++;
	}
	DBfree_result(result);

	zbx_free(sql);
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
 * Comments: format of the request: ZBX_GET_ACTIVE_CHECKS\n<host name>\n      *
 *           format of the list: key:delay:last_log_size                      *
 *                                                                            *
 ******************************************************************************/
int	send_list_of_active_checks(zbx_sock_t *sock, char *request)
{
	const char	*__function_name = "send_list_of_active_checks";

	char		*host = NULL, *p, *buffer = NULL, error[MAX_STRING_LEN], ip[INTERFACE_IP_LEN_MAX];
	size_t		buffer_alloc = 8 * ZBX_KIBIBYTE, buffer_offset = 0, items_num = 0;
	int		ret = FAIL, *errcodes, i;
	zbx_uint64_t	hostid, *itemids = NULL;
	zbx_active_t	*items = NULL;
	DC_ITEM		*dc_items;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

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

	strscpy(ip, get_ip_by_socket(sock));

	if (FAIL == get_hostid_by_host(host, ip, ZBX_DEFAULT_AGENT_PORT, &hostid, error))
		goto out;

	get_list_of_active_checks(hostid, &itemids, &items, &items_num);

	buffer = zbx_malloc(buffer, buffer_alloc);

	if (0 != items_num)
	{
		dc_items = zbx_malloc(NULL, sizeof(DC_ITEM) * items_num);
		errcodes = zbx_malloc(NULL, sizeof(int) * items_num);

		DCconfig_get_items_by_itemids(dc_items, itemids, errcodes, items_num);

		for (i = 0; i < items_num; i++)
		{
			if (SUCCEED != errcodes[i])
			{
				zabbix_log(LOG_LEVEL_DEBUG, "%s() Item [" ZBX_FS_UI64 "] was not found in the"
						" server cache. Not sending now.", __function_name, itemids[i]);
				continue;
			}

			zbx_snprintf_alloc(&buffer, &buffer_alloc, &buffer_offset, "%s:%d:" ZBX_FS_UI64 "\n",
					dc_items[i].key_orig, dc_items[i].delay, items[i].lastlogsize);
		}

		DCconfig_clean_items(dc_items, errcodes, items_num);

		zbx_free(errcodes);
		zbx_free(dc_items);
	}

	zbx_free(items);
	zbx_free(itemids);

	zbx_strcpy_alloc(&buffer, &buffer_alloc, &buffer_offset, "ZBX_EOF\n");

	zabbix_log(LOG_LEVEL_DEBUG, "%s() sending [%s]", __function_name, buffer);

	alarm(CONFIG_TIMEOUT);
	if (SUCCEED != zbx_tcp_send_raw(sock, buffer))
		zbx_strlcpy(error, zbx_tcp_strerror(), MAX_STRING_LEN);
	else
		ret = SUCCEED;
	alarm(0);

	zbx_free(buffer);
out:
	if (FAIL == ret)
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot send list of active checks to [%s]: %s",
				get_ip_by_socket(sock), error);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
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
	const char	*__function_name = "send_list_of_active_checks_json";

#define ZBX_KEY_OTHER		0
#define ZBX_KEY_LOG		1
#define ZBX_KEY_EVENTLOG	2

	char		host[HOST_HOST_LEN_MAX], params[MAX_STRING_LEN], tmp[MAX_STRING_LEN],
			ip[INTERFACE_IP_LEN_MAX], error[MAX_STRING_LEN];
	struct zbx_json	json;
	int		ret = FAIL, *errcodes, i;
	zbx_uint64_t	hostid, *itemids = NULL;
	zbx_active_t	*items = NULL;
	size_t		items_num = 0;
	DC_ITEM		*dc_items;
	unsigned short	port;

	unsigned char	item_key;
	char		**regexp = NULL;
	int		regexp_alloc = 0, regexp_num = 0, n;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (FAIL == zbx_json_value_by_name(jp, ZBX_PROTO_TAG_HOST, host, sizeof(host)))
	{
		zbx_snprintf(error, MAX_STRING_LEN, "%s", zbx_json_strerror());
		goto error;
	}

	if (FAIL == zbx_json_value_by_name(jp, ZBX_PROTO_TAG_IP, ip, sizeof(ip)))
		strscpy(ip, get_ip_by_socket(sock));

	if (FAIL == zbx_json_value_by_name(jp, ZBX_PROTO_TAG_PORT, tmp, sizeof(tmp)))
		*tmp = '\0';

	if (FAIL == is_ushort(tmp, &port))
		port = ZBX_DEFAULT_AGENT_PORT;

	if (FAIL == get_hostid_by_host(host, ip, port, &hostid, error))
		goto error;

	get_list_of_active_checks(hostid, &itemids, &items, &items_num);

	zbx_json_init(&json, ZBX_JSON_STAT_BUF_LEN);
	zbx_json_addstring(&json, ZBX_PROTO_TAG_RESPONSE, ZBX_PROTO_VALUE_SUCCESS, ZBX_JSON_TYPE_STRING);
	zbx_json_addarray(&json, ZBX_PROTO_TAG_DATA);

	if (0 != items_num)
	{
		dc_items = zbx_malloc(NULL, sizeof(DC_ITEM) * items_num);
		errcodes = zbx_malloc(NULL, sizeof(int) * items_num);

		DCconfig_get_items_by_itemids(dc_items, itemids, errcodes, items_num);

		for (i = 0; i < items_num; i++)
		{
			if (SUCCEED != errcodes[i])
			{
				zabbix_log(LOG_LEVEL_DEBUG, "%s() Item [" ZBX_FS_UI64 "] was not found in the"
						" server cache. Not sending now.", __function_name, itemids[i]);
				continue;
			}

			dc_items[i].key = zbx_strdup(dc_items[i].key, dc_items[i].key_orig);
			substitute_key_macros(&dc_items[i].key, NULL, &dc_items[i], NULL, MACRO_TYPE_ITEM_KEY, NULL, 0);

			zbx_json_addobject(&json, NULL);
			zbx_json_addstring(&json, ZBX_PROTO_TAG_KEY, dc_items[i].key, ZBX_JSON_TYPE_STRING);
			if (0 != strcmp(dc_items[i].key, dc_items[i].key_orig))
			{
				zbx_json_addstring(&json, ZBX_PROTO_TAG_KEY_ORIG,
						dc_items[i].key_orig, ZBX_JSON_TYPE_STRING);
			}
			zbx_json_adduint64(&json, ZBX_PROTO_TAG_DELAY, dc_items[i].delay);
			/* The agent expects ALWAYS to have lastlogsize and mtime tags. */
			/* Removing those would cause older agents to fail. */
			zbx_json_adduint64(&json, ZBX_PROTO_TAG_LOGLASTSIZE, items[i].lastlogsize);
			zbx_json_adduint64(&json, ZBX_PROTO_TAG_MTIME, items[i].mtime);
			zbx_json_close(&json);

			if (0 == strncmp(dc_items[i].key, "log[", 4) || 0 == strncmp(dc_items[i].key, "logrt[", 6))
				item_key = ZBX_KEY_LOG;
			else if (0 == strncmp(dc_items[i].key, "eventlog[", 9))
				item_key = ZBX_KEY_EVENTLOG;
			else
				item_key = ZBX_KEY_OTHER;

			if (ZBX_KEY_OTHER != item_key && ZBX_COMMAND_WITH_PARAMS == parse_command(dc_items[i].key, NULL, 0, params, sizeof(params)))
			{
				/* "params" paramater */
				if (0 == get_param(params, 2, tmp, sizeof(tmp)) && '@' == *tmp)
					add_regexp_name(&regexp, &regexp_alloc, &regexp_num, tmp + 1);

				if (ZBX_KEY_EVENTLOG == item_key)
				{
					/* "severity" parameter */
					if (0 == get_param(params, 3, tmp, sizeof(tmp)) && '@' == *tmp)
						add_regexp_name(&regexp, &regexp_alloc, &regexp_num, tmp + 1);

					/* "logeventid" parameter */
					if (0 == get_param(params, 5, tmp, sizeof(tmp)) && '@' == *tmp)
						add_regexp_name(&regexp, &regexp_alloc, &regexp_num, tmp + 1);
				}
			}

			zbx_free(dc_items[i].key);
		}

		DCconfig_clean_items(dc_items, errcodes, items_num);

		zbx_free(errcodes);
		zbx_free(dc_items);
	}

	zbx_free(items);
	zbx_free(itemids);

	zbx_json_close(&json);

	if (0 != regexp_num)
	{
		DB_RESULT	result;
		DB_ROW		row;
		char		*sql = NULL, *name_esc;
		size_t		sql_alloc = 2 * ZBX_KIBIBYTE, sql_offset = 0;

		zbx_json_addarray(&json, ZBX_PROTO_TAG_REGEXP);

		sql = zbx_malloc(sql, sql_alloc);

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				"select r.name,e.expression,e.expression_type,e.exp_delimiter,e.case_sensitive"
				" from regexps r,expressions e"
				" where r.regexpid=e.regexpid"
					" and r.name in (");

		for (n = 0; n < regexp_num; n++)
		{
			name_esc = DBdyn_escape_string(regexp[n]);
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%s'%s'",
					n == 0 ? "" : ",",
					name_esc);
			zbx_free(name_esc);
			zbx_free(regexp[n]);
		}
		zbx_chrcpy_alloc(&sql, &sql_alloc, &sql_offset, ')');

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

		zbx_free(sql);

		zbx_json_close(&json);
	}
	zbx_free(regexp);

	zabbix_log(LOG_LEVEL_DEBUG, "%s() sending [%s]", __function_name, json.buffer);

	alarm(CONFIG_TIMEOUT);
	if (SUCCEED != zbx_tcp_send(sock, json.buffer))
		strscpy(error, zbx_tcp_strerror());
	else
		ret = SUCCEED;
	alarm(0);

	zbx_json_free(&json);

	goto out;
error:
	zabbix_log(LOG_LEVEL_WARNING, "cannot send list of active checks to [%s]: %s", get_ip_by_socket(sock), error);

	zbx_json_init(&json, ZBX_JSON_STAT_BUF_LEN);
	zbx_json_addstring(&json, ZBX_PROTO_TAG_RESPONSE, ZBX_PROTO_VALUE_FAILED, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(&json, ZBX_PROTO_TAG_INFO, error, ZBX_JSON_TYPE_STRING);

	zabbix_log(LOG_LEVEL_DEBUG, "%s() sending [%s]", __function_name, json.buffer);

	ret = zbx_tcp_send(sock, json.buffer);

	zbx_json_free(&json);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}
