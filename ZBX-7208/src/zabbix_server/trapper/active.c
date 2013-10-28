/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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
#include "db.h"
#include "dbcache.h"
#include "log.h"
#include "zbxserver.h"
#include "zbxregexp.h"

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
 * Comments: NB! adds host to the database if it does not exist               *
 *                                                                            *
 ******************************************************************************/
static int	get_hostid_by_host(const char *host, const char *ip, unsigned short port, const char *host_metadata,
		zbx_uint64_t *hostid, char *error)
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
				" and flags<>%d"
		       		" and proxy_hostid is null"
				ZBX_SQL_NODE,
			host_esc,
			HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED,
			ZBX_FLAG_DISCOVERY_PROTOTYPE,
			DBand_node_local("hostid"));

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
			DBregister_host(0, host, ip, dns, port, host_metadata, (int)time(NULL));
		else if (0 != (daemon_type & ZBX_DAEMON_TYPE_PROXY))
			DBproxy_register_host(host, ip, dns, port, host_metadata);

		DBcommit();
	}

	DBfree_result(result);

	zbx_free(host_esc);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

static void	get_list_of_active_checks(zbx_uint64_t hostid, zbx_vector_uint64_t *itemids)
{
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	itemid;

	result = DBselect(
			"select itemid"
			" from items"
			" where type=%d"
				" and flags<>%d"
				" and hostid=" ZBX_FS_UI64,
			ITEM_TYPE_ZABBIX_ACTIVE, ZBX_FLAG_DISCOVERY_PROTOTYPE, hostid);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(itemid, row[0]);
		zbx_vector_uint64_append(itemids, itemid);
	}
	DBfree_result(result);
}

/******************************************************************************
 *                                                                            *
 * Function: send_list_of_active_checks                                       *
 *                                                                            *
 * Purpose: send list of active checks to the host (older version agent)      *
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
	const char		*__function_name = "send_list_of_active_checks";

	char			*host = NULL, *p, *buffer = NULL, error[MAX_STRING_LEN], ip[INTERFACE_IP_LEN_MAX];
	size_t			buffer_alloc = 8 * ZBX_KIBIBYTE, buffer_offset = 0;
	int			ret = FAIL, i;
	zbx_uint64_t		hostid;
	zbx_vector_uint64_t	itemids;

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

	/* no host metadata in older versions of agent */
	if (FAIL == get_hostid_by_host(host, ip, ZBX_DEFAULT_AGENT_PORT, "", &hostid, error))
		goto out;

	zbx_vector_uint64_create(&itemids);

	get_list_of_active_checks(hostid, &itemids);

	buffer = zbx_malloc(buffer, buffer_alloc);

	if (0 != itemids.values_num)
	{
		DC_ITEM	*dc_items;
		int	*errcodes, refresh_unsupported, now;

		dc_items = zbx_malloc(NULL, sizeof(DC_ITEM) * itemids.values_num);
		errcodes = zbx_malloc(NULL, sizeof(int) * itemids.values_num);

		DCconfig_get_items_by_itemids(dc_items, itemids.values, errcodes, itemids.values_num);
		DCconfig_get_config_data(&refresh_unsupported, CONFIG_REFRESH_UNSUPPORTED);

		now = time(NULL);

		for (i = 0; i < itemids.values_num; i++)
		{
			if (SUCCEED != errcodes[i])
			{
				zabbix_log(LOG_LEVEL_DEBUG, "%s() Item [" ZBX_FS_UI64 "] was not found in the"
						" server cache. Not sending now.", __function_name, itemids.values[i]);
				continue;
			}

			if (ITEM_STATE_NOTSUPPORTED == dc_items[i].state)
			{
				if (0 == refresh_unsupported || dc_items[i].lastclock + refresh_unsupported > now)
					continue;
			}

			zbx_snprintf_alloc(&buffer, &buffer_alloc, &buffer_offset, "%s:%d:" ZBX_FS_UI64 "\n",
					dc_items[i].key_orig, dc_items[i].delay, dc_items[i].lastlogsize);
		}

		DCconfig_clean_items(dc_items, errcodes, itemids.values_num);

		zbx_free(errcodes);
		zbx_free(dc_items);
	}

	zbx_vector_uint64_destroy(&itemids);

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

/******************************************************************************
 *                                                                            *
 * Function: zbx_vector_str_append_uniq                                       *
 *                                                                            *
 * Purpose: append non duplicate string to the string vector                  *
 *                                                                            *
 * Parameters: vector - [IN/OUT] the string vector                            *
 *             str    - [IN] the string to append                             *
 *                                                                            *
 ******************************************************************************/
static void	zbx_vector_str_append_uniq(zbx_vector_str_t *vector, char *str)
{
	if (FAIL == zbx_vector_str_search(vector, str, ZBX_DEFAULT_STR_COMPARE_FUNC))
		zbx_vector_str_append(vector, zbx_strdup(NULL, str));
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
	const char		*__function_name = "send_list_of_active_checks_json";

#define ZBX_KEY_OTHER		0
#define ZBX_KEY_LOG		1
#define ZBX_KEY_EVENTLOG	2

	char			host[HOST_HOST_LEN_MAX], params[MAX_STRING_LEN], tmp[MAX_STRING_LEN],
				ip[INTERFACE_IP_LEN_MAX], error[MAX_STRING_LEN], *host_metadata = NULL;
	struct zbx_json		json;
	int			ret = FAIL, i;
	zbx_uint64_t		hostid;
	size_t			host_metadata_alloc = 1;	/* for at least NUL-termination char */
	unsigned short		port;
	zbx_vector_uint64_t	itemids;

	unsigned char		item_key;
	zbx_vector_ptr_t	regexps;
	zbx_vector_str_t	names;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_ptr_create(&regexps);
	zbx_vector_str_create(&names);

	if (FAIL == zbx_json_value_by_name(jp, ZBX_PROTO_TAG_HOST, host, sizeof(host)))
	{
		zbx_snprintf(error, MAX_STRING_LEN, "%s", zbx_json_strerror());
		goto error;
	}

	host_metadata = zbx_malloc(host_metadata, host_metadata_alloc);

	if (FAIL == zbx_json_value_by_name_dyn(jp, ZBX_PROTO_TAG_HOST_METADATA,
			&host_metadata, &host_metadata_alloc))
	{
		*host_metadata = '\0';
	}

	if (FAIL == zbx_json_value_by_name(jp, ZBX_PROTO_TAG_IP, ip, sizeof(ip)))
		strscpy(ip, get_ip_by_socket(sock));

	if (FAIL == zbx_json_value_by_name(jp, ZBX_PROTO_TAG_PORT, tmp, sizeof(tmp)))
		*tmp = '\0';

	if (FAIL == is_ushort(tmp, &port))
		port = ZBX_DEFAULT_AGENT_PORT;

	if (FAIL == get_hostid_by_host(host, ip, port, host_metadata, &hostid, error))
		goto error;

	zbx_vector_uint64_create(&itemids);

	get_list_of_active_checks(hostid, &itemids);

	zbx_json_init(&json, ZBX_JSON_STAT_BUF_LEN);
	zbx_json_addstring(&json, ZBX_PROTO_TAG_RESPONSE, ZBX_PROTO_VALUE_SUCCESS, ZBX_JSON_TYPE_STRING);
	zbx_json_addarray(&json, ZBX_PROTO_TAG_DATA);

	if (0 != itemids.values_num)
	{
		DC_ITEM	*dc_items;
		int	*errcodes, refresh_unsupported, now;

		dc_items = zbx_malloc(NULL, sizeof(DC_ITEM) * itemids.values_num);
		errcodes = zbx_malloc(NULL, sizeof(int) * itemids.values_num);

		DCconfig_get_items_by_itemids(dc_items, itemids.values, errcodes, itemids.values_num);
		DCconfig_get_config_data(&refresh_unsupported, CONFIG_REFRESH_UNSUPPORTED);

		now = time(NULL);

		for (i = 0; i < itemids.values_num; i++)
		{
			if (SUCCEED != errcodes[i])
			{
				zabbix_log(LOG_LEVEL_DEBUG, "%s() Item [" ZBX_FS_UI64 "] was not found in the"
						" server cache. Not sending now.", __function_name, itemids.values[i]);
				continue;
			}

			if (ITEM_STATE_NOTSUPPORTED == dc_items[i].state)
			{
				if (0 == refresh_unsupported || dc_items[i].lastclock + refresh_unsupported > now)
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
			zbx_json_adduint64(&json, ZBX_PROTO_TAG_LOGLASTSIZE, dc_items[i].lastlogsize);
			zbx_json_adduint64(&json, ZBX_PROTO_TAG_MTIME, dc_items[i].mtime);
			zbx_json_close(&json);

			if (0 == strncmp(dc_items[i].key, "log[", 4) || 0 == strncmp(dc_items[i].key, "logrt[", 6))
				item_key = ZBX_KEY_LOG;
			else if (0 == strncmp(dc_items[i].key, "eventlog[", 9))
				item_key = ZBX_KEY_EVENTLOG;
			else
				item_key = ZBX_KEY_OTHER;

			if (ZBX_KEY_OTHER != item_key && ZBX_COMMAND_WITH_PARAMS == parse_command(dc_items[i].key, NULL, 0, params, sizeof(params)))
			{
				/* "params" parameter */
				if (0 == get_param(params, 2, tmp, sizeof(tmp)) && '@' == *tmp)
					zbx_vector_str_append_uniq(&names, tmp + 1);

				if (ZBX_KEY_EVENTLOG == item_key)
				{
					/* "severity" parameter */
					if (0 == get_param(params, 3, tmp, sizeof(tmp)) && '@' == *tmp)
						zbx_vector_str_append_uniq(&names, tmp + 1);

					/* "logeventid" parameter */
					if (0 == get_param(params, 5, tmp, sizeof(tmp)) && '@' == *tmp)
						zbx_vector_str_append_uniq(&names, tmp + 1);
				}
			}

			zbx_free(dc_items[i].key);
		}

		DCconfig_clean_items(dc_items, errcodes, itemids.values_num);

		zbx_free(errcodes);
		zbx_free(dc_items);
	}

	zbx_vector_uint64_destroy(&itemids);

	zbx_json_close(&json);

	DCget_expressions_by_names(&regexps, (const char * const *)names.values, names.values_num);

	if (0 < regexps.values_num)
	{
		char	buffer[32];

		zbx_json_addarray(&json, ZBX_PROTO_TAG_REGEXP);

		for (i = 0; i < regexps.values_num; i++)
		{
			zbx_expression_t	*regexp = regexps.values[i];

			zbx_json_addobject(&json, NULL);
			zbx_json_addstring(&json, "name", regexp->name, ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&json, "expression", regexp->expression, ZBX_JSON_TYPE_STRING);

			zbx_snprintf(buffer, sizeof(buffer), "%d", regexp->expression_type);
			zbx_json_addstring(&json, "expression_type",buffer, ZBX_JSON_TYPE_INT);

			zbx_snprintf(buffer, sizeof(buffer), "%c", regexp->exp_delimiter);
			zbx_json_addstring(&json, "exp_delimiter", buffer, ZBX_JSON_TYPE_STRING);

			zbx_snprintf(buffer, sizeof(buffer), "%d", regexp->case_sensitive);
			zbx_json_addstring(&json, "case_sensitive", buffer, ZBX_JSON_TYPE_INT);

			zbx_json_close(&json);
		}

		zbx_json_close(&json);
	}

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
	for (i = 0; i < names.values_num; i++)
		zbx_free(names.values[i]);

	zbx_vector_str_destroy(&names);

	zbx_regexp_clean_expressions(&regexps);
	zbx_vector_ptr_destroy(&regexps);

	zbx_free(host_metadata);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}
