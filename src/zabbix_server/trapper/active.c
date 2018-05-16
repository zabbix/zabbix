/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
#include "../../libs/zbxcrypto/tls_tcp_active.h"

extern unsigned char	program_type;

static void	db_register_host(const char *host, const char *ip, unsigned short port, const char *host_metadata)
{
	char	dns[INTERFACE_DNS_LEN_MAX];

	if (0 == strncmp("::ffff:", ip, 7) && SUCCEED == is_ip4(ip + 7))
		ip += 7;

	zbx_alarm_on(CONFIG_TIMEOUT);
	zbx_gethost_by_ip(ip, dns, sizeof(dns));
	zbx_alarm_off();

	DBbegin();

	if (0 != (program_type & ZBX_PROGRAM_TYPE_SERVER))
		DBregister_host(0, host, ip, dns, port, host_metadata, (int)time(NULL));
	else if (0 != (program_type & ZBX_PROGRAM_TYPE_PROXY))
		DBproxy_register_host(host, ip, dns, port, host_metadata);

	DBcommit();
}

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
static int	get_hostid_by_host(const zbx_socket_t *sock, const char *host, const char *ip, unsigned short port,
		const char *host_metadata, zbx_uint64_t *hostid, char *error)
{
	const char	*__function_name = "get_hostid_by_host";

	char		*host_esc, *ch_error, *old_metadata;
	DB_RESULT	result;
	DB_ROW		row;
	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() host:'%s'", __function_name, host);

	if (FAIL == zbx_check_hostname(host, &ch_error))
	{
		zbx_snprintf(error, MAX_STRING_LEN, "invalid host name [%s]: %s", host, ch_error);
		zbx_free(ch_error);
		goto out;
	}

	host_esc = DBdyn_escape_string(host);

	result =
#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
		DBselect(
			"select h.hostid,h.status,h.tls_accept,h.tls_issuer,h.tls_subject,h.tls_psk_identity,"
			"a.host_metadata"
			" from hosts h"
				" left join autoreg_host a"
					" on a.proxy_hostid is null and h.host=a.host"
			" where h.host='%s'"
				" and h.status in (%d,%d)"
				" and h.flags<>%d"
				" and h.proxy_hostid is null",
			host_esc, HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED, ZBX_FLAG_DISCOVERY_PROTOTYPE);
#else
		DBselect(
			"select h.hostid,h.status,h.tls_accept,a.host_metadata"
			" from hosts h"
				" left join autoreg_host a"
					" on a.proxy_hostid is null and h.host=a.host"
			" where h.host='%s'"
				" and h.status in (%d,%d)"
				" and h.flags<>%d"
				" and h.proxy_hostid is null",
			host_esc, HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED, ZBX_FLAG_DISCOVERY_PROTOTYPE);
#endif
	if (NULL != (row = DBfetch(result)))
	{
		if (0 == ((unsigned int)atoi(row[2]) & sock->connection_type))
		{
			zbx_snprintf(error, MAX_STRING_LEN, "connection of type \"%s\" is not allowed for host"
					" \"%s\"", zbx_tcp_connection_type_name(sock->connection_type), host);
			goto done;
		}

#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
		if (ZBX_TCP_SEC_TLS_CERT == sock->connection_type)
		{
			zbx_tls_conn_attr_t	attr;

			if (SUCCEED != zbx_tls_get_attr_cert(sock, &attr))
			{
				THIS_SHOULD_NEVER_HAPPEN;

				zbx_snprintf(error, MAX_STRING_LEN, "cannot get connection attributes for host"
						" \"%s\"", host);
				goto done;
			}

			/* simplified match, not compliant with RFC 4517, 4518 */
			if ('\0' != *row[3] && 0 != strcmp(row[3], attr.issuer))
			{
				zbx_snprintf(error, MAX_STRING_LEN, "certificate issuer does not match for"
						" host \"%s\"", host);
				goto done;
			}

			/* simplified match, not compliant with RFC 4517, 4518 */
			if ('\0' != *row[4] && 0 != strcmp(row[4], attr.subject))
			{
				zbx_snprintf(error, MAX_STRING_LEN, "certificate subject does not match for"
						" host \"%s\"", host);
				goto done;
			}
		}
		else if (ZBX_TCP_SEC_TLS_PSK == sock->connection_type)
		{
			zbx_tls_conn_attr_t	attr;

			if (SUCCEED != zbx_tls_get_attr_psk(sock, &attr))
			{
				THIS_SHOULD_NEVER_HAPPEN;

				zbx_snprintf(error, MAX_STRING_LEN, "cannot get connection attributes for host"
						" \"%s\"", host);
				goto done;
			}

			if (strlen(row[5]) != attr.psk_identity_len ||
					0 != memcmp(row[5], attr.psk_identity, attr.psk_identity_len))
			{
				zbx_snprintf(error, MAX_STRING_LEN, "false PSK identity for host \"%s\"", host);
				goto done;
			}
		}
#endif
		if (HOST_STATUS_MONITORED == atoi(row[1]))
		{
			ZBX_STR2UINT64(*hostid, row[0]);
			ret = SUCCEED;
		}
		else
			zbx_snprintf(error, MAX_STRING_LEN, "host [%s] not monitored", host);

#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
		old_metadata = row[6];
#else
		old_metadata = row[3];
#endif
		if (FAIL == DBis_null(old_metadata) && 0 != strcmp(old_metadata, host_metadata))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "host [%s] has changed metadata from [%s] to [%s]", host,
					old_metadata, host_metadata);
			db_register_host(host, ip, port, host_metadata);
		}
	}
	else
	{
		zbx_snprintf(error, MAX_STRING_LEN, "host [%s] not found", host);
		db_register_host(host, ip, port, host_metadata);
	}
done:
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
int	send_list_of_active_checks(zbx_socket_t *sock, char *request)
{
	const char		*__function_name = "send_list_of_active_checks";

	char			*host = NULL, *p, *buffer = NULL, error[MAX_STRING_LEN];
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

	/* no host metadata in older versions of agent */
	if (FAIL == get_hostid_by_host(sock, host, sock->peer, ZBX_DEFAULT_AGENT_PORT, "", &hostid, error))
		goto out;

	zbx_vector_uint64_create(&itemids);

	get_list_of_active_checks(hostid, &itemids);

	buffer = (char *)zbx_malloc(buffer, buffer_alloc);

	if (0 != itemids.values_num)
	{
		DC_ITEM		*dc_items;
		int		*errcodes, now;
		zbx_config_t	cfg;

		dc_items = (DC_ITEM *)zbx_malloc(NULL, sizeof(DC_ITEM) * itemids.values_num);
		errcodes = (int *)zbx_malloc(NULL, sizeof(int) * itemids.values_num);

		DCconfig_get_items_by_itemids(dc_items, itemids.values, errcodes, itemids.values_num);
		zbx_config_get(&cfg, ZBX_CONFIG_FLAGS_REFRESH_UNSUPPORTED);

		now = time(NULL);

		for (i = 0; i < itemids.values_num; i++)
		{
			if (SUCCEED != errcodes[i])
			{
				zabbix_log(LOG_LEVEL_DEBUG, "%s() Item [" ZBX_FS_UI64 "] was not found in the"
						" server cache. Not sending now.", __function_name, itemids.values[i]);
				continue;
			}

			if (ITEM_STATUS_ACTIVE != dc_items[i].status)
				continue;

			if (HOST_STATUS_MONITORED != dc_items[i].host.status)
				continue;

			if (ITEM_STATE_NOTSUPPORTED == dc_items[i].state)
			{
				if (0 == cfg.refresh_unsupported)
					continue;

				if (dc_items[i].lastclock + cfg.refresh_unsupported > now)
					continue;
			}

			zbx_snprintf_alloc(&buffer, &buffer_alloc, &buffer_offset, "%s:%d:" ZBX_FS_UI64 "\n",
					dc_items[i].key_orig, dc_items[i].delay, dc_items[i].lastlogsize);
		}

		zbx_config_clean(&cfg);

		DCconfig_clean_items(dc_items, errcodes, itemids.values_num);

		zbx_free(errcodes);
		zbx_free(dc_items);
	}

	zbx_vector_uint64_destroy(&itemids);

	zbx_strcpy_alloc(&buffer, &buffer_alloc, &buffer_offset, "ZBX_EOF\n");

	zabbix_log(LOG_LEVEL_DEBUG, "%s() sending [%s]", __function_name, buffer);

	zbx_alarm_on(CONFIG_TIMEOUT);
	if (SUCCEED != zbx_tcp_send_raw(sock, buffer))
		zbx_strlcpy(error, zbx_socket_strerror(), MAX_STRING_LEN);
	else
		ret = SUCCEED;
	zbx_alarm_off();

	zbx_free(buffer);
out:
	if (FAIL == ret)
		zabbix_log(LOG_LEVEL_WARNING, "cannot send list of active checks to \"%s\": %s", sock->peer, error);

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
static void	zbx_vector_str_append_uniq(zbx_vector_str_t *vector, const char *str)
{
	if (FAIL == zbx_vector_str_search(vector, str, ZBX_DEFAULT_STR_COMPARE_FUNC))
		zbx_vector_str_append(vector, zbx_strdup(NULL, str));
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_itemkey_extract_global_regexps                               *
 *                                                                            *
 * Purpose: extract global regular expression names from item key             *
 *                                                                            *
 * Parameters: key     - [IN] the item key to parse                           *
 *             regexps - [OUT] the extracted regular expression names         *
 *                                                                            *
 ******************************************************************************/
static void	zbx_itemkey_extract_global_regexps(const char *key, zbx_vector_str_t *regexps)
{
#define ZBX_KEY_LOG		1
#define ZBX_KEY_EVENTLOG	2

	AGENT_REQUEST	request;
	int		item_key;
	const char	*param;

	if (0 == strncmp(key, "log[", 4) || 0 == strncmp(key, "logrt[", 6) || 0 == strncmp(key, "log.count[", 10) ||
			0 == strncmp(key, "logrt.count[", 12))
		item_key = ZBX_KEY_LOG;
	else if (0 == strncmp(key, "eventlog[", 9))
		item_key = ZBX_KEY_EVENTLOG;
	else
		return;

	init_request(&request);

	if(SUCCEED != parse_item_key(key, &request))
		goto out;

	/* "params" parameter */
	if (NULL != (param = get_rparam(&request, 1)) && '@' == *param)
		zbx_vector_str_append_uniq(regexps, param + 1);

	if (ZBX_KEY_EVENTLOG == item_key)
	{
		/* "severity" parameter */
		if (NULL != (param = get_rparam(&request, 2)) && '@' == *param)
			zbx_vector_str_append_uniq(regexps, param + 1);

		/* "source" parameter */
		if (NULL != (param = get_rparam(&request, 3)) && '@' == *param)
			zbx_vector_str_append_uniq(regexps, param + 1);

		/* "logeventid" parameter */
		if (NULL != (param = get_rparam(&request, 4)) && '@' == *param)
			zbx_vector_str_append_uniq(regexps, param + 1);
	}
out:
	free_request(&request);
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
int	send_list_of_active_checks_json(zbx_socket_t *sock, struct zbx_json_parse *jp)
{
	const char		*__function_name = "send_list_of_active_checks_json";

	char			host[HOST_HOST_LEN_MAX], tmp[MAX_STRING_LEN], ip[INTERFACE_IP_LEN_MAX],
				error[MAX_STRING_LEN], *host_metadata = NULL;
	struct zbx_json		json;
	int			ret = FAIL, i;
	zbx_uint64_t		hostid;
	size_t			host_metadata_alloc = 1;	/* for at least NUL-termination char */
	unsigned short		port;
	zbx_vector_uint64_t	itemids;

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

	host_metadata = (char *)zbx_malloc(host_metadata, host_metadata_alloc);

	if (FAIL == zbx_json_value_by_name_dyn(jp, ZBX_PROTO_TAG_HOST_METADATA,
			&host_metadata, &host_metadata_alloc))
	{
		*host_metadata = '\0';
	}

	if (FAIL == zbx_json_value_by_name(jp, ZBX_PROTO_TAG_IP, ip, sizeof(ip)))
		strscpy(ip, sock->peer);

	if (FAIL == is_ip(ip))	/* check even if 'ip' came from get_ip_by_socket() - it can return not a valid IP */
	{
		zbx_snprintf(error, MAX_STRING_LEN, "\"%s\" is not a valid IP address", ip);
		goto error;
	}

	if (FAIL == zbx_json_value_by_name(jp, ZBX_PROTO_TAG_PORT, tmp, sizeof(tmp)))
	{
		port = ZBX_DEFAULT_AGENT_PORT;
	}
	else if (FAIL == is_ushort(tmp, &port))
	{
		zbx_snprintf(error, MAX_STRING_LEN, "\"%s\" is not a valid port", tmp);
		goto error;
	}

	if (FAIL == get_hostid_by_host(sock, host, ip, port, host_metadata, &hostid, error))
		goto error;

	zbx_vector_uint64_create(&itemids);

	get_list_of_active_checks(hostid, &itemids);

	zbx_json_init(&json, ZBX_JSON_STAT_BUF_LEN);
	zbx_json_addstring(&json, ZBX_PROTO_TAG_RESPONSE, ZBX_PROTO_VALUE_SUCCESS, ZBX_JSON_TYPE_STRING);
	zbx_json_addarray(&json, ZBX_PROTO_TAG_DATA);

	if (0 != itemids.values_num)
	{
		DC_ITEM		*dc_items;
		int		*errcodes, now, delay;
		zbx_config_t	cfg;

		dc_items = (DC_ITEM *)zbx_malloc(NULL, sizeof(DC_ITEM) * itemids.values_num);
		errcodes = (int *)zbx_malloc(NULL, sizeof(int) * itemids.values_num);

		DCconfig_get_items_by_itemids(dc_items, itemids.values, errcodes, itemids.values_num);
		zbx_config_get(&cfg, ZBX_CONFIG_FLAGS_REFRESH_UNSUPPORTED);

		now = time(NULL);

		for (i = 0; i < itemids.values_num; i++)
		{
			if (SUCCEED != errcodes[i])
			{
				zabbix_log(LOG_LEVEL_DEBUG, "%s() Item [" ZBX_FS_UI64 "] was not found in the"
						" server cache. Not sending now.", __function_name, itemids.values[i]);
				continue;
			}

			if (ITEM_STATUS_ACTIVE != dc_items[i].status)
				continue;

			if (HOST_STATUS_MONITORED != dc_items[i].host.status)
				continue;

			if (ITEM_STATE_NOTSUPPORTED == dc_items[i].state)
			{
				if (0 == cfg.refresh_unsupported)
					continue;

				if (dc_items[i].lastclock + cfg.refresh_unsupported > now)
					continue;
			}

			if (SUCCEED != zbx_interval_preproc(dc_items[i].delay, &delay, NULL, NULL))
				continue;

			dc_items[i].key = zbx_strdup(dc_items[i].key, dc_items[i].key_orig);
			substitute_key_macros(&dc_items[i].key, NULL, &dc_items[i], NULL, MACRO_TYPE_ITEM_KEY, NULL, 0);

			zbx_json_addobject(&json, NULL);
			zbx_json_addstring(&json, ZBX_PROTO_TAG_KEY, dc_items[i].key, ZBX_JSON_TYPE_STRING);
			if (0 != strcmp(dc_items[i].key, dc_items[i].key_orig))
			{
				zbx_json_addstring(&json, ZBX_PROTO_TAG_KEY_ORIG,
						dc_items[i].key_orig, ZBX_JSON_TYPE_STRING);
			}
			zbx_json_adduint64(&json, ZBX_PROTO_TAG_DELAY, delay);
			/* The agent expects ALWAYS to have lastlogsize and mtime tags. */
			/* Removing those would cause older agents to fail. */
			zbx_json_adduint64(&json, ZBX_PROTO_TAG_LASTLOGSIZE, dc_items[i].lastlogsize);
			zbx_json_adduint64(&json, ZBX_PROTO_TAG_MTIME, dc_items[i].mtime);
			zbx_json_close(&json);

			zbx_itemkey_extract_global_regexps(dc_items[i].key, &names);

			zbx_free(dc_items[i].key);
		}

		zbx_config_clean(&cfg);

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
			zbx_expression_t	*regexp = (zbx_expression_t *)regexps.values[i];

			zbx_json_addobject(&json, NULL);
			zbx_json_addstring(&json, "name", regexp->name, ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&json, "expression", regexp->expression, ZBX_JSON_TYPE_STRING);

			zbx_snprintf(buffer, sizeof(buffer), "%d", regexp->expression_type);
			zbx_json_addstring(&json, "expression_type", buffer, ZBX_JSON_TYPE_INT);

			zbx_snprintf(buffer, sizeof(buffer), "%c", regexp->exp_delimiter);
			zbx_json_addstring(&json, "exp_delimiter", buffer, ZBX_JSON_TYPE_STRING);

			zbx_snprintf(buffer, sizeof(buffer), "%d", regexp->case_sensitive);
			zbx_json_addstring(&json, "case_sensitive", buffer, ZBX_JSON_TYPE_INT);

			zbx_json_close(&json);
		}

		zbx_json_close(&json);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "%s() sending [%s]", __function_name, json.buffer);

	zbx_alarm_on(CONFIG_TIMEOUT);
	if (SUCCEED != zbx_tcp_send(sock, json.buffer))
		strscpy(error, zbx_socket_strerror());
	else
		ret = SUCCEED;
	zbx_alarm_off();

	zbx_json_free(&json);

	goto out;
error:
	zabbix_log(LOG_LEVEL_WARNING, "cannot send list of active checks to \"%s\": %s", sock->peer, error);

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
