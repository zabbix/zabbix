/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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
#include "zbxserver.h"

#include "log.h"
#include "zbxregexp.h"
#include "zbxcompress.h"
#include "zbxcrypto.h"
#include "zbxnum.h"
#include "zbxcomms.h"
#include "zbxip.h"
#include "zbxsysinfo.h"
#include "zbxversion.h"
#include "zbx_host_constants.h"
#include "zbx_item_constants.h"

extern unsigned char	program_type;

/**********************************************************************************
 *                                                                                *
 * Purpose: perform active agent auto registration                                *
 *                                                                                *
 * Parameters: host            - [IN] name of the host to be added or updated     *
 *             ip              - [IN] IP address of the host                      *
 *             port            - [IN] port of the host                            *
 *             connection_type - [IN] ZBX_TCP_SEC_UNENCRYPTED,                    *
 *                                    ZBX_TCP_SEC_TLS_PSK or ZBX_TCP_SEC_TLS_CERT *
 *             host_metadata   - [IN] host metadata                               *
 *             flag            - [IN] flag describing interface type              *
 *             interface       - [IN] interface value if flag is not default      *
 *             config_timeout  - [IN]                                             *
 *                                                                                *
 * Comments: helper function for get_hostid_by_host                               *
 *                                                                                *
 **********************************************************************************/
static void	db_register_host(const char *host, const char *ip, unsigned short port, unsigned int connection_type,
		const char *host_metadata, zbx_conn_flags_t flag, const char *interface, int config_timeout)
{
	char		dns[ZBX_INTERFACE_DNS_LEN_MAX];
	char		ip_addr[ZBX_INTERFACE_IP_LEN_MAX];
	const char	*p;
	const char	*p_ip, *p_dns;
	int		now;

	p_ip = ip;
	p_dns = dns;

	if (ZBX_CONN_DEFAULT == flag)
		p = ip;
	else if (ZBX_CONN_IP == flag)
		p_ip = p = interface;

	zbx_alarm_on(config_timeout);
	if (ZBX_CONN_DEFAULT == flag || ZBX_CONN_IP == flag)
	{
		if (0 == strncmp("::ffff:", p, 7) && SUCCEED == zbx_is_ip4(p + 7))
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

	now = time(NULL);

	/* update before changing database in case Zabbix proxy also changed database and then deleted from cache */
	DCconfig_update_autoreg_host(host, p_ip, p_dns, port, host_metadata, flag, now);

	do
	{
		zbx_db_begin();

		if (0 != (program_type & ZBX_PROGRAM_TYPE_SERVER))
		{
			zbx_db_register_host(0, host, p_ip, p_dns, port, connection_type, host_metadata,
					(unsigned short)flag, now);
		}
		else
		{
			zbx_db_proxy_register_host(host, p_ip, p_dns, port, connection_type, host_metadata,
					(unsigned short)flag, now);
		}
	}
	while (ZBX_DB_DOWN == zbx_db_commit());
}

static int	zbx_autoreg_host_check_permissions(const char *host, const char *ip, unsigned short port,
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
 * Parameters: sock           - [IN] open socket of server-agent connection   *
 *             host           - [IN] host name                                *
 *             ip             - [IN] IP address of the host                   *
 *             port           - [IN] port of the host                         *
 *             host_metadata  - [IN] host metadata                            *
 *             flag           - [IN] flag describing interface type           *
 *             interface      - [IN] interface value if flag is not default   *
 *             config_timeout - [IN]                                          *
 *             hostid         - [OUT] host ID                                 *
 *             revision       - [OUT] host configuration revision             *
 *             error          - [OUT] error message                           *
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
		const char *host_metadata, zbx_conn_flags_t flag, const char *interface, int config_timeout,
		zbx_uint64_t *hostid, zbx_uint64_t *revision, char *error)
{
#define AUTO_REGISTRATION_HEARTBEAT	120
	char	*ch_error;
	int	ret = FAIL, heartbeat;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() host:'%s' metadata:'%s'", __func__, host, host_metadata);

	if (FAIL == zbx_check_hostname(host, &ch_error))
	{
		zbx_snprintf(error, MAX_STRING_LEN, "invalid host name [%s]: %s", host, ch_error);
		zbx_free(ch_error);
		goto out;
	}

	/* if host exists then check host connection permissions */
	if (FAIL == DCcheck_host_permissions(host, sock, hostid, revision, &ch_error))
	{
		zbx_snprintf(error, MAX_STRING_LEN, "%s", ch_error);
		zbx_free(ch_error);
		goto out;
	}

	/* if host does not exist then check autoregistration connection permissions */
	if (0 == *hostid)
	{
		zbx_snprintf(error, MAX_STRING_LEN, "host [%s] not found", host);

		if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER) || 0 != DCget_auto_registration_action_count())
		{
			if (SUCCEED == zbx_autoreg_host_check_permissions(host, ip, port, sock))
			{
				if (SUCCEED == DCis_autoreg_host_changed(host, port, host_metadata, flag, interface,
						(int)time(NULL), AUTO_REGISTRATION_HEARTBEAT))
				{
					db_register_host(host, ip, port, sock->connection_type, host_metadata, flag,
							interface, config_timeout);
				}
			}
		}

		goto out;
	}

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		heartbeat = AUTO_REGISTRATION_HEARTBEAT;
	else
		heartbeat = 0;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER) || 0 != DCget_auto_registration_action_count())
	{
		if (SUCCEED == DCis_autoreg_host_changed(host, port, host_metadata, flag, interface, (int)time(NULL),
				heartbeat))
		{
			db_register_host(host, ip, port, sock->connection_type, host_metadata, flag, interface,
					config_timeout);
		}
	}

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));
#undef PROXY_AUTO_REGISTRATION_HEARTBEAT
	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: send list of active checks to the host (older version agent)      *
 *                                                                            *
 * Parameters: sock           - open socket of server-agent connection        *
 *             request        - request buffer                                *
 *             config_timeout - [IN]                                          *
 *                                                                            *
 * Return value:  SUCCEED - list of active checks sent successfully           *
 *                FAIL - an error occurred                                    *
 *                                                                            *
 * Comments: format of the request: ZBX_GET_ACTIVE_CHECKS\n<host name>\n      *
 *           format of the list: key:delay:last_log_size                      *
 *                                                                            *
 ******************************************************************************/
int	send_list_of_active_checks(zbx_socket_t *sock, char *request, int config_timeout)
{
	char			*host = NULL, *p, *buffer = NULL, error[MAX_STRING_LEN];
	size_t			buffer_alloc = 8 * ZBX_KIBIBYTE, buffer_offset = 0;
	int			ret = FAIL, i, num = 0;
	zbx_uint64_t		hostid, revision;

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
	if (FAIL == get_hostid_by_host(sock, host, sock->peer, ZBX_DEFAULT_AGENT_PORT, "", 0, "", config_timeout,
			&hostid, &revision, error))
	{
		goto out;
	}

	num = DCconfig_get_active_items_count_by_hostid(hostid);

	buffer = (char *)zbx_malloc(buffer, buffer_alloc);

	if (0 != num)
	{
		DC_ITEM			*dc_items;
		int			*errcodes;
		zbx_dc_um_handle_t	*um_handle;

		um_handle = zbx_dc_open_user_macros();

		dc_items = (DC_ITEM *)zbx_malloc(NULL, sizeof(DC_ITEM) * num);
		errcodes = (int *)zbx_malloc(NULL, sizeof(int) * num);

		DCconfig_get_active_items_by_hostid(dc_items, hostid, errcodes, num);

		for (i = 0; i < num; i++)
		{
			int	delay;

			if (SUCCEED != errcodes[i])
			{
				zabbix_log(LOG_LEVEL_DEBUG, "%s() Item for host [" ZBX_FS_UI64 "] was not found in the"
						" server cache.", __func__, hostid);
				continue;
			}

			if (ITEM_STATUS_ACTIVE != dc_items[i].status)
				continue;

			if (HOST_STATUS_MONITORED != dc_items[i].host.status)
				continue;

			zbx_substitute_simple_macros(NULL, NULL, NULL, NULL, &dc_items[i].host.hostid, NULL, NULL,
					NULL, NULL, NULL, NULL, NULL, &dc_items[i].delay, MACRO_TYPE_COMMON, NULL, 0);

			if (SUCCEED != zbx_interval_preproc(dc_items[i].delay, &delay, NULL, NULL))
				continue;

			zbx_snprintf_alloc(&buffer, &buffer_alloc, &buffer_offset, "%s:%d:" ZBX_FS_UI64 "\n",
					dc_items[i].key_orig, delay, dc_items[i].lastlogsize);
		}

		DCconfig_clean_items(dc_items, errcodes, num);

		zbx_free(errcodes);
		zbx_free(dc_items);

		zbx_dc_close_user_macros(um_handle);
	}

	zbx_strcpy_alloc(&buffer, &buffer_alloc, &buffer_offset, "ZBX_EOF\n");

	zabbix_log(LOG_LEVEL_DEBUG, "%s() sending [%s]", __func__, buffer);

	zbx_alarm_on(config_timeout);
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

	zbx_init_agent_request(&request);

	if(SUCCEED != zbx_parse_item_key(key, &request))
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
	zbx_free_agent_request(&request);
}

/******************************************************************************
 *                                                                            *
 * Purpose: send list of active checks to the host                            *
 *                                                                            *
 * Parameters: sock           - open socket of server-agent connection        *
 *             jp             - request buffer                                *
 *             config_timeout - [IN]                                          *
 *                                                                            *
 * Return value:  SUCCEED - list of active checks sent successfully           *
 *                FAIL - an error occurred                                    *
 *                                                                            *
 ******************************************************************************/
int	send_list_of_active_checks_json(zbx_socket_t *sock, struct zbx_json_parse *jp, int config_timeout)
{
	char			host[ZBX_HOSTNAME_BUF_LEN], tmp[MAX_STRING_LEN], ip[ZBX_INTERFACE_IP_LEN_MAX],
				error[MAX_STRING_LEN], *host_metadata = NULL, *interface = NULL, *buffer = NULL;
	struct zbx_json		json;
	int			ret = FAIL, i, version, num = 0;
	zbx_uint64_t		hostid, revision, agent_config_revision;
	size_t			host_metadata_alloc = 1;	/* for at least NUL-terminated string */
	size_t			interface_alloc = 1;		/* for at least NUL-terminated string */
	size_t			buffer_size, reserved = 0;
	unsigned short		port;
	zbx_conn_flags_t	flag = ZBX_CONN_DEFAULT;
	zbx_session_t		*session = NULL;
	zbx_vector_expression_t	regexps;
	zbx_vector_str_t	names;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_expression_create(&regexps);
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
	else if (SUCCEED == zbx_is_ip(interface))
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
		zbx_strscpy(ip, sock->peer);

	/* check even if 'ip' came from zbx_socket_peer_ip_save() - it can return not a valid IP */
	if (FAIL == zbx_is_ip(ip))
	{
		zbx_snprintf(error, MAX_STRING_LEN, "\"%s\" is not a valid IP address", ip);
		goto error;
	}

	if (FAIL == zbx_json_value_by_name(jp, ZBX_PROTO_TAG_PORT, tmp, sizeof(tmp), NULL))
	{
		port = ZBX_DEFAULT_AGENT_PORT;
	}
	else if (FAIL == zbx_is_ushort(tmp, &port))
	{
		zbx_snprintf(error, MAX_STRING_LEN, "\"%s\" is not a valid port", tmp);
		goto error;
	}

	if (FAIL == zbx_json_value_by_name(jp, ZBX_PROTO_TAG_CONFIG_REVISION, tmp, sizeof(tmp), NULL))
	{
		agent_config_revision = 0;
	}
	else if (FAIL == zbx_is_uint64(tmp, &agent_config_revision))
	{
		zbx_snprintf(error, MAX_STRING_LEN, "\"%s\" is not a valid revision", tmp);
		goto error;
	}

	if (FAIL == get_hostid_by_host(sock, host, ip, port, host_metadata, flag, interface, config_timeout, &hostid,
			&revision, error))
	{
		goto error;
	}

	if (SUCCEED != zbx_json_value_by_name(jp, ZBX_PROTO_TAG_VERSION, tmp, sizeof(tmp), NULL) ||
			FAIL == (version = zbx_get_component_version_without_patch(tmp)))
	{
		version = ZBX_COMPONENT_VERSION(4, 2, 0);
	}

	if (SUCCEED == zbx_json_value_by_name(jp, ZBX_PROTO_TAG_SESSION, tmp, sizeof(tmp), NULL))
	{
		size_t	token_len;

		if (ZBX_SESSION_TOKEN_SIZE != (token_len = strlen(tmp)))
		{
			zbx_snprintf(error, MAX_STRING_LEN, "invalid session token length %d", (int)token_len);
			goto error;
		}

		session = zbx_dc_get_or_create_session(hostid, tmp, ZBX_SESSION_TYPE_CONFIG);
	}

	zbx_json_init(&json, ZBX_JSON_STAT_BUF_LEN);
	zbx_json_addstring(&json, ZBX_PROTO_TAG_RESPONSE, ZBX_PROTO_VALUE_SUCCESS, ZBX_JSON_TYPE_STRING);

	if (NULL == session || 0 == session->last_id || agent_config_revision != revision)
	{
		zbx_json_adduint64(&json, ZBX_PROTO_TAG_CONFIG_REVISION, (zbx_uint64_t)revision);
		zbx_json_addarray(&json, ZBX_PROTO_TAG_DATA);
		/* determine items count to ensure allocation is done outside of a lock */
		num = DCconfig_get_active_items_count_by_hostid(hostid);
	}

	if (0 != num)
	{
		DC_ITEM			*dc_items;
		int			*errcodes, delay;
		zbx_dc_um_handle_t	*um_handle;

		dc_items = (DC_ITEM *)zbx_malloc(NULL, sizeof(DC_ITEM) * num);
		errcodes = (int *)zbx_malloc(NULL, sizeof(int) * num);
		DCconfig_get_active_items_by_hostid(dc_items, hostid, errcodes, num);

		um_handle = zbx_dc_open_user_macros();

		for (i = 0; i < num; i++)
		{
			if (SUCCEED != errcodes[i])
			{
				/* items or host removed between checking item count and retrieving items */
				zabbix_log(LOG_LEVEL_DEBUG, "%s() Item for host [" ZBX_FS_UI64 "] was not found in the"
						" server cache.", __func__, hostid);
				continue;
			}

			if (ITEM_STATUS_ACTIVE != dc_items[i].status)
				continue;

			if (HOST_STATUS_MONITORED != dc_items[i].host.status)
				continue;

			zbx_substitute_simple_macros(NULL, NULL, NULL, NULL, &dc_items[i].host.hostid, NULL, NULL,
					NULL, NULL, NULL, NULL, NULL, &dc_items[i].delay, MACRO_TYPE_COMMON, NULL, 0);

			if (SUCCEED != zbx_interval_preproc(dc_items[i].delay, &delay, NULL, NULL))
				continue;

			dc_items[i].key = zbx_strdup(dc_items[i].key, dc_items[i].key_orig);
			zbx_substitute_key_macros_unmasked(&dc_items[i].key, NULL, &dc_items[i], NULL, NULL,
					MACRO_TYPE_ITEM_KEY, NULL, 0);

			zbx_json_addobject(&json, NULL);
			zbx_json_addstring(&json, ZBX_PROTO_TAG_KEY, dc_items[i].key, ZBX_JSON_TYPE_STRING);

			if (ZBX_COMPONENT_VERSION(4, 4, 0) > version)
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

		DCconfig_clean_items(dc_items, errcodes, num);

		zbx_free(errcodes);
		zbx_free(dc_items);

		zbx_dc_close_user_macros(um_handle);
	}

	zbx_json_close(&json);

	if (ZBX_COMPONENT_VERSION(4, 4, 0) == version || ZBX_COMPONENT_VERSION(5, 0, 0) == version)
		zbx_json_adduint64(&json, ZBX_PROTO_TAG_REFRESH_UNSUPPORTED, 600);

	DCget_expressions_by_names(&regexps, (const char * const *)names.values, names.values_num);

	if (0 < regexps.values_num)
	{
		char	str[32];

		zbx_json_addarray(&json, ZBX_PROTO_TAG_REGEXP);

		for (i = 0; i < regexps.values_num; i++)
		{
			zbx_expression_t	*regexp = regexps.values[i];

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
				config_timeout)))
		{
			zbx_strscpy(error, zbx_socket_strerror());
		}
	}
	else
	{
		if (SUCCEED != (ret = zbx_tcp_send_ext(sock, json.buffer, json.buffer_size, 0, sock->protocol,
				config_timeout)))
		{
			zbx_strscpy(error, zbx_socket_strerror());
		}
	}

	zbx_json_free(&json);

	if (SUCCEED == ret)
	{
		/* remember if configuration was successfully sent for new session */
		if (NULL != session)
			session->last_id = (zbx_uint64_t)revision;
	}

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
	zbx_vector_expression_destroy(&regexps);

	zbx_free(host_metadata);
	zbx_free(interface);
	zbx_free(buffer);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}
