/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

#include "active.h"

#include "log.h"
#include "zbxserver.h"
#include "zbxregexp.h"
#include "zbxcompress.h"

#include "../../libs/zbxcrypto/tls_tcp_active.h"

extern unsigned char	program_type;

/******************************************************************************
 *                                                                            *
 * Purpose: perform active agent auto registration                            *
 *                                                                            *
 * Parameters: host          - [IN] name of the host to be added or updated   *
 *             ip            - [IN] IP address of the host                    *
 *             port          - [IN] port of the host                          *
 *             connection_type - [IN] ZBX_TCP_SEC_UNENCRYPTED,                *
 *                             ZBX_TCP_SEC_TLS_PSK or ZBX_TCP_SEC_TLS_CERT    *
 *             host_metadata - [IN] host metadata                             *
 *             flag          - [IN] flag describing interface type            *
 *             interface     - [IN] interface value if flag is not default    *
 *                                                                            *
 * Comments: helper function for get_hostid_by_host                           *
 *                                                                            *
 ******************************************************************************/
static void	db_register_host(const char *host, const char *ip, unsigned short port, unsigned int connection_type,
		const char *host_metadata, zbx_conn_flags_t flag, const char *interface)
{
	char		dns[INTERFACE_DNS_LEN_MAX];
	char		ip_addr[INTERFACE_IP_LEN_MAX];
	const char	*p;
	const char	*p_ip, *p_dns;

	p_ip = ip;
	p_dns = dns;

	if (ZBX_CONN_DEFAULT == flag)
		p = ip;
	else if (ZBX_CONN_IP  == flag)
		p_ip = p = interface;

	zbx_alarm_on(CONFIG_TIMEOUT);
	if (ZBX_CONN_DEFAULT == flag || ZBX_CONN_IP == flag)
	{
		if (0 == strncmp("::ffff:", p, 7) && SUCCEED == is_ip4(p + 7))
			p += 7;

		zbx_gethost_by_ip(p, dns, sizeof(dns));
	}
	else if (ZBX_CONN_DNS == flag)
	{
		zbx_getip_by_host(interface, ip_addr, sizeof(ip_addr));
		p_ip = ip_addr;
		p_dns = interface;
	}
	zbx_alarm_off();

	DBbegin();

	if (0 != (program_type & ZBX_PROGRAM_TYPE_SERVER))
	{
		DBregister_host(0, host, p_ip, p_dns, port, connection_type, host_metadata, (unsigned short)flag,
				(int)time(NULL));
	}
	else if (0 != (program_type & ZBX_PROGRAM_TYPE_PROXY))
		DBproxy_register_host(host, p_ip, p_dns, port, connection_type, host_metadata, (unsigned short)flag);

	DBcommit();
}

static int	zbx_autoreg_check_permissions(const char *host, const char *ip, unsigned short port,
		const zbx_socket_t *sock)
{
	zbx_config_t	cfg;
	int		ret = FAIL;

	zbx_config_get(&cfg, ZBX_CONFIG_FLAGS_AUTOREG_TLS_ACCEPT);

	if (0 == (cfg.autoreg_tls_accept & sock->connection_type))
	{
		zabbix_log(LOG_LEVEL_WARNING, "autoregistration from \"%s\" denied (host:\"%s\" ip:\"%s\""
				" port:%hu): connection type \"%s\" is not allowed for autoregistration",
				sock->peer, host, ip, port, zbx_tcp_connection_type_name(sock->connection_type));
		goto out;
	}

#if defined(HAVE_GNUTLS) || (defined(HAVE_OPENSSL) && defined(HAVE_OPENSSL_WITH_PSK))
	if (ZBX_TCP_SEC_TLS_PSK == sock->connection_type)
	{
		if (0 == (ZBX_PSK_FOR_AUTOREG & zbx_tls_get_psk_usage()))
		{
			zabbix_log(LOG_LEVEL_WARNING, "autoregistration from \"%s\" denied (host:\"%s\" ip:\"%s\""
					" port:%hu): connection used PSK which is not configured for autoregistration",
					sock->peer, host, ip, port);
			goto out;
		}

		ret = SUCCEED;
	}
	else if (ZBX_TCP_SEC_UNENCRYPTED == sock->connection_type)
	{
		ret = SUCCEED;
	}
	else
		THIS_SHOULD_NEVER_HAPPEN;
#else
	ret = SUCCEED;
#endif
out:
	zbx_config_clean(&cfg);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check for host name and return hostid                             *
 *                                                                            *
 * Parameters: sock          - [IN] open socket of server-agent connection    *
 *             host          - [IN] host name                                 *
 *             ip            - [IN] IP address of the host                    *
 *             port          - [IN] port of the host                          *
 *             host_metadata - [IN] host metadata                             *
 *             flag          - [IN] flag describing interface type            *
 *             interface     - [IN] interface value if flag is not default    *
 *             hostid        - [OUT] host ID                                  *
 *             error         - [OUT] error message                            *
 *                                                                            *
 * Return value:  SUCCEED - host is found                                     *
 *                FAIL - an error occurred or host not found                  *
 *                                                                            *
 * Comments: NB! adds host to the database if it does not exist or if it      *
 *           exists but metadata, interface, interface type or port has       *
 *           changed                                                          *
 *                                                                            *
 ******************************************************************************/
static int	get_hostid_by_host(const zbx_socket_t *sock, const char *host, const char *ip, unsigned short port,
		const char *host_metadata, zbx_conn_flags_t flag, const char *interface, zbx_uint64_t *hostid,
		char *error)
{
	char			*host_esc, *ch_error, *old_metadata, *old_ip, *old_dns, *old_flag, *old_port;
	DB_RESULT		result;
	DB_ROW			row;
	unsigned short		old_port_v;
	int			tls_offset = 0, ret = FAIL;
	zbx_conn_flags_t	old_flag_v;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() host:'%s' metadata:'%s'", __func__, host, host_metadata);

	if (FAIL == zbx_check_hostname(host, &ch_error))
	{
		zbx_snprintf(error, MAX_STRING_LEN, "invalid host name [%s]: %s", host, ch_error);
		zbx_free(ch_error);
		goto out;
	}

	host_esc = DBdyn_escape_string(host);

	result =
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
		DBselect(
			"select h.hostid,h.status,h.tls_accept,h.tls_issuer,h.tls_subject,h.tls_psk_identity,"
			"a.host_metadata,a.listen_ip,a.listen_dns,a.listen_port,a.flags"
			" from hosts h"
				" left join autoreg_host a"
					" on a.proxy_hostid is null and a.host=h.host"
			" where h.host='%s'"
				" and h.status in (%d,%d)"
				" and h.flags<>%d"
				" and h.proxy_hostid is null",
			host_esc, HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED, ZBX_FLAG_DISCOVERY_PROTOTYPE);
#else
		DBselect(
			"select h.hostid,h.status,h.tls_accept,a.host_metadata,a.listen_ip,a.listen_dns,a.listen_port,"
			"a.flags"
			" from hosts h"
				" left join autoreg_host a"
					" on a.proxy_hostid is null and a.host=h.host"
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

#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
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
#if defined(HAVE_GNUTLS) || (defined(HAVE_OPENSSL) && defined(HAVE_OPENSSL_WITH_PSK))
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
		tls_offset = 3;
#endif
		old_metadata = row[3 + tls_offset];
		old_ip = row[4 + tls_offset];
		old_dns = row[5 + tls_offset];
		old_port = row[6 + tls_offset];
		old_flag = row[7 + tls_offset];
		old_port_v = (unsigned short)(SUCCEED == DBis_null(old_port)) ? 0 : atoi(old_port);
		old_flag_v = (zbx_conn_flags_t)(SUCCEED == DBis_null(old_flag)) ? ZBX_CONN_DEFAULT : atoi(old_flag);
		/* metadata is available only on Zabbix server */
		if (SUCCEED == DBis_null(old_flag) || 0 != strcmp(old_metadata, host_metadata) ||
				(ZBX_CONN_IP  == flag && ( 0 != strcmp(old_ip, interface)  || old_port_v != port)) ||
				(ZBX_CONN_DNS == flag && ( 0 != strcmp(old_dns, interface) || old_port_v != port)) ||
				(old_flag_v != flag))
		{
			db_register_host(host, ip, port, sock->connection_type, host_metadata, flag, interface);
		}

		if (HOST_STATUS_MONITORED != atoi(row[1]))
		{
			zbx_snprintf(error, MAX_STRING_LEN, "host [%s] not monitored", host);
			goto done;
		}

		ZBX_STR2UINT64(*hostid, row[0]);
		ret = SUCCEED;
	}
	else
	{
		zbx_snprintf(error, MAX_STRING_LEN, "host [%s] not found", host);

		if (SUCCEED == zbx_autoreg_check_permissions(host, ip, port, sock))
			db_register_host(host, ip, port, sock->connection_type, host_metadata, flag, interface);
	}
done:
	DBfree_result(result);

	zbx_free(host_esc);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

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
	char			*host = NULL, *p, *buffer = NULL, error[MAX_STRING_LEN];
	size_t			buffer_alloc = 8 * ZBX_KIBIBYTE, buffer_offset = 0;
	int			ret = FAIL, i;
	zbx_uint64_t		hostid;
	zbx_vector_uint64_t	itemids;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

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
	if (FAIL == get_hostid_by_host(sock, host, sock->peer, ZBX_DEFAULT_AGENT_PORT, "", 0, "",  &hostid, error))
		goto out;

	zbx_vector_uint64_create(&itemids);

	get_list_of_active_checks(hostid, &itemids);

	buffer = (char *)zbx_malloc(buffer, buffer_alloc);

	if (0 != itemids.values_num)
	{
		DC_ITEM		*dc_items;
		int		*errcodes;

		dc_items = (DC_ITEM *)zbx_malloc(NULL, sizeof(DC_ITEM) * itemids.values_num);
		errcodes = (int *)zbx_malloc(NULL, sizeof(int) * itemids.values_num);

		DCconfig_get_items_by_itemids(dc_items, itemids.values, errcodes, itemids.values_num);

		for (i = 0; i < itemids.values_num; i++)
		{
			int	delay;

			if (SUCCEED != errcodes[i])
			{
				zabbix_log(LOG_LEVEL_DEBUG, "%s() Item [" ZBX_FS_UI64 "] was not found in the"
						" server cache. Not sending now.", __func__, itemids.values[i]);
				continue;
			}

			if (ITEM_STATUS_ACTIVE != dc_items[i].status)
				continue;

			if (HOST_STATUS_MONITORED != dc_items[i].host.status)
				continue;

			if (SUCCEED != zbx_interval_preproc(dc_items[i].delay, &delay, NULL, NULL))
				continue;

			zbx_snprintf_alloc(&buffer, &buffer_alloc, &buffer_offset, "%s:%d:" ZBX_FS_UI64 "\n",
					dc_items[i].key_orig, delay, dc_items[i].lastlogsize);
		}

		DCconfig_clean_items(dc_items, errcodes, itemids.values_num);

		zbx_free(errcodes);
		zbx_free(dc_items);
	}

	zbx_vector_uint64_destroy(&itemids);

	zbx_strcpy_alloc(&buffer, &buffer_alloc, &buffer_offset, "ZBX_EOF\n");

	zabbix_log(LOG_LEVEL_DEBUG, "%s() sending [%s]", __func__, buffer);

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

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
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
 * Purpose: send list of active checks to the host                            *
 *                                                                            *
 * Parameters: sock - open socket of server-agent connection                  *
 *             jp   - request buffer                                          *
 *                                                                            *
 * Return value:  SUCCEED - list of active checks sent successfully           *
 *                FAIL - an error occurred                                    *
 *                                                                            *
 ******************************************************************************/
int	send_list_of_active_checks_json(zbx_socket_t *sock, struct zbx_json_parse *jp)
{
	char			host[HOST_HOST_LEN_MAX], tmp[MAX_STRING_LEN], ip[INTERFACE_IP_LEN_MAX],
				error[MAX_STRING_LEN], *host_metadata = NULL, *interface = NULL, *buffer = NULL;
	struct zbx_json		json;
	int			ret = FAIL, i, version;
	zbx_uint64_t		hostid;
	size_t			host_metadata_alloc = 1;	/* for at least NUL-terminated string */
	size_t			interface_alloc = 1;		/* for at least NUL-terminated string */
	size_t			buffer_size, reserved = 0;
	unsigned short		port;
	zbx_vector_uint64_t	itemids;
	zbx_conn_flags_t	flag = ZBX_CONN_DEFAULT;

	zbx_vector_ptr_t	regexps;
	zbx_vector_str_t	names;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_ptr_create(&regexps);
	zbx_vector_str_create(&names);

	if (FAIL == zbx_json_value_by_name(jp, ZBX_PROTO_TAG_HOST, host, sizeof(host), NULL))
	{
		zbx_snprintf(error, MAX_STRING_LEN, "%s", zbx_json_strerror());
		goto error;
	}

	host_metadata = (char *)zbx_malloc(host_metadata, host_metadata_alloc);

	if (FAIL == zbx_json_value_by_name_dyn(jp, ZBX_PROTO_TAG_HOST_METADATA,
			&host_metadata, &host_metadata_alloc, NULL))
	{
		*host_metadata = '\0';
	}

	interface = (char *)zbx_malloc(interface, interface_alloc);

	if (FAIL == zbx_json_value_by_name_dyn(jp, ZBX_PROTO_TAG_INTERFACE, &interface, &interface_alloc, NULL))
	{
		*interface = '\0';
	}
	else if (SUCCEED == is_ip(interface))
	{
		flag = ZBX_CONN_IP;
	}
	else if (SUCCEED == zbx_validate_hostname(interface))
	{
		flag = ZBX_CONN_DNS;
	}
	else
	{
		zbx_snprintf(error, MAX_STRING_LEN, "\"%s\" is not a valid IP or DNS", interface);
		goto error;
	}

	if (FAIL == zbx_json_value_by_name(jp, ZBX_PROTO_TAG_IP, ip, sizeof(ip), NULL))
		strscpy(ip, sock->peer);

	if (FAIL == is_ip(ip))	/* check even if 'ip' came from get_ip_by_socket() - it can return not a valid IP */
	{
		zbx_snprintf(error, MAX_STRING_LEN, "\"%s\" is not a valid IP address", ip);
		goto error;
	}

	if (FAIL == zbx_json_value_by_name(jp, ZBX_PROTO_TAG_PORT, tmp, sizeof(tmp), NULL))
	{
		port = ZBX_DEFAULT_AGENT_PORT;
	}
	else if (FAIL == is_ushort(tmp, &port))
	{
		zbx_snprintf(error, MAX_STRING_LEN, "\"%s\" is not a valid port", tmp);
		goto error;
	}

	if (FAIL == get_hostid_by_host(sock, host, ip, port, host_metadata, flag, interface, &hostid, error))
		goto error;

	if (SUCCEED != zbx_json_value_by_name(jp, ZBX_PROTO_TAG_VERSION, tmp, sizeof(tmp), NULL) ||
			FAIL == (version = zbx_get_component_version(tmp)))
	{
		version = ZBX_COMPONENT_VERSION(4, 2);
	}

	zbx_vector_uint64_create(&itemids);

	get_list_of_active_checks(hostid, &itemids);

	zbx_json_init(&json, ZBX_JSON_STAT_BUF_LEN);
	zbx_json_addstring(&json, ZBX_PROTO_TAG_RESPONSE, ZBX_PROTO_VALUE_SUCCESS, ZBX_JSON_TYPE_STRING);
	zbx_json_addarray(&json, ZBX_PROTO_TAG_DATA);

	if (0 != itemids.values_num)
	{
		DC_ITEM		*dc_items;
		int		*errcodes, delay;

		dc_items = (DC_ITEM *)zbx_malloc(NULL, sizeof(DC_ITEM) * itemids.values_num);
		errcodes = (int *)zbx_malloc(NULL, sizeof(int) * itemids.values_num);

		DCconfig_get_items_by_itemids(dc_items, itemids.values, errcodes, itemids.values_num);

		for (i = 0; i < itemids.values_num; i++)
		{
			if (SUCCEED != errcodes[i])
			{
				zabbix_log(LOG_LEVEL_DEBUG, "%s() Item [" ZBX_FS_UI64 "] was not found in the"
						" server cache. Not sending now.", __func__, itemids.values[i]);
				continue;
			}

			if (ITEM_STATUS_ACTIVE != dc_items[i].status)
				continue;

			if (HOST_STATUS_MONITORED != dc_items[i].host.status)
				continue;

			if (SUCCEED != zbx_interval_preproc(dc_items[i].delay, &delay, NULL, NULL))
				continue;

			dc_items[i].key = zbx_strdup(dc_items[i].key, dc_items[i].key_orig);
			substitute_key_macros_unmasked(&dc_items[i].key, NULL, &dc_items[i], NULL, NULL,
					MACRO_TYPE_ITEM_KEY, NULL, 0);

			zbx_json_addobject(&json, NULL);
			zbx_json_addstring(&json, ZBX_PROTO_TAG_KEY, dc_items[i].key, ZBX_JSON_TYPE_STRING);

			if (ZBX_COMPONENT_VERSION(4,4) > version)
			{
				if (0 != strcmp(dc_items[i].key, dc_items[i].key_orig))
				{
					zbx_json_addstring(&json, ZBX_PROTO_TAG_KEY_ORIG,
							dc_items[i].key_orig, ZBX_JSON_TYPE_STRING);
				}

				/* in the case scheduled/flexible interval set delay to 0 causing */
				/* 'Incorrect update interval' error in agent                     */
				if (NULL != strchr(dc_items[i].delay, ';'))
					delay = 0;

				zbx_json_adduint64(&json, ZBX_PROTO_TAG_DELAY, delay);
			}
			else
			{
				zbx_json_adduint64(&json, ZBX_PROTO_TAG_ITEMID, dc_items[i].itemid);
				zbx_json_addstring(&json, ZBX_PROTO_TAG_DELAY, dc_items[i].delay, ZBX_JSON_TYPE_STRING);
			}

			/* The agent expects ALWAYS to have lastlogsize and mtime tags. */
			/* Removing those would cause older agents to fail. */
			zbx_json_adduint64(&json, ZBX_PROTO_TAG_LASTLOGSIZE, dc_items[i].lastlogsize);
			zbx_json_adduint64(&json, ZBX_PROTO_TAG_MTIME, dc_items[i].mtime);
			zbx_json_close(&json);

			zbx_itemkey_extract_global_regexps(dc_items[i].key, &names);

			zbx_free(dc_items[i].key);
		}

		DCconfig_clean_items(dc_items, errcodes, itemids.values_num);

		zbx_free(errcodes);
		zbx_free(dc_items);
	}

	zbx_json_close(&json);

	if (ZBX_COMPONENT_VERSION(4,4) == version || ZBX_COMPONENT_VERSION(5,0) == version)
		zbx_json_adduint64(&json, ZBX_PROTO_TAG_REFRESH_UNSUPPORTED, 600);

	zbx_vector_uint64_destroy(&itemids);

	DCget_expressions_by_names(&regexps, (const char * const *)names.values, names.values_num);

	if (0 < regexps.values_num)
	{
		char	str[32];

		zbx_json_addarray(&json, ZBX_PROTO_TAG_REGEXP);

		for (i = 0; i < regexps.values_num; i++)
		{
			zbx_expression_t	*regexp = (zbx_expression_t *)regexps.values[i];

			zbx_json_addobject(&json, NULL);
			zbx_json_addstring(&json, "name", regexp->name, ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&json, "expression", regexp->expression, ZBX_JSON_TYPE_STRING);

			zbx_snprintf(str, sizeof(str), "%d", regexp->expression_type);
			zbx_json_addstring(&json, "expression_type", str, ZBX_JSON_TYPE_INT);

			zbx_snprintf(str, sizeof(str), "%c", regexp->exp_delimiter);
			zbx_json_addstring(&json, "exp_delimiter", str, ZBX_JSON_TYPE_STRING);

			zbx_snprintf(str, sizeof(str), "%d", regexp->case_sensitive);
			zbx_json_addstring(&json, "case_sensitive", str, ZBX_JSON_TYPE_INT);

			zbx_json_close(&json);
		}

		zbx_json_close(&json);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "%s() sending [%s]", __func__, json.buffer);

	if (0 != (ZBX_TCP_COMPRESS & sock->protocol))
	{
		if (SUCCEED != zbx_compress(json.buffer, json.buffer_size, &buffer, &buffer_size))
		{
			zbx_snprintf(error, MAX_STRING_LEN, "cannot compress data: %s", zbx_compress_strerror());
			goto error;
		}

		reserved = json.buffer_size;
		zbx_json_free(&json);	/* json buffer can be large, free as fast as possible */

		if (SUCCEED != (ret = zbx_tcp_send_ext(sock, buffer, buffer_size, reserved, sock->protocol,
				CONFIG_TIMEOUT)))
		{
			strscpy(error, zbx_socket_strerror());
		}
	}
	else
	{
		if (SUCCEED != (ret = zbx_tcp_send_ext(sock, json.buffer, json.buffer_size, 0, sock->protocol,
				CONFIG_TIMEOUT)))
		{
			strscpy(error, zbx_socket_strerror());
		}
	}

	zbx_json_free(&json);

	goto out;
error:
	zabbix_log(LOG_LEVEL_WARNING, "cannot send list of active checks to \"%s\": %s", sock->peer, error);

	zbx_json_init(&json, ZBX_JSON_STAT_BUF_LEN);
	zbx_json_addstring(&json, ZBX_PROTO_TAG_RESPONSE, ZBX_PROTO_VALUE_FAILED, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(&json, ZBX_PROTO_TAG_INFO, error, ZBX_JSON_TYPE_STRING);

	zabbix_log(LOG_LEVEL_DEBUG, "%s() sending [%s]", __func__, json.buffer);

	ret = zbx_tcp_send(sock, json.buffer);

	zbx_json_free(&json);
out:
	for (i = 0; i < names.values_num; i++)
		zbx_free(names.values[i]);

	zbx_vector_str_destroy(&names);

	zbx_regexp_clean_expressions(&regexps);
	zbx_vector_ptr_destroy(&regexps);

	zbx_free(host_metadata);
	zbx_free(interface);
	zbx_free(buffer);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}
