/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

#include "active.h"

#include "zbxtrapper.h"

#include "zbxexpression.h"
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
#include "zbxscripts.h"
#include "zbxcommshigh.h"
#include "zbxalgo.h"
#include "zbxcacheconfig.h"
#include "zbxexpr.h"
#include "zbxjson.h"
#include "zbxstr.h"
#include "zbxautoreg.h"

/*************************************************************************************
 *                                                                                   *
 * Purpose: performs active agent auto registration                                  *
 *                                                                                   *
 * Parameters:                                                                       *
 *    host                        - [IN] name of host to be added or updated         *
 *    ip                          - [IN] IP address of host                          *
 *    port                        - [IN] port of host                                *
 *    connection_type             - [IN] ZBX_TCP_SEC_UNENCRYPTED,                    *
 *                                       ZBX_TCP_SEC_TLS_PSK or ZBX_TCP_SEC_TLS_CERT *
 *                                       flag                                        *
 *    host_metadata               - [IN]                                             *
 *    flag                        - [IN] flag describing interface type              *
 *    interface                   - [IN] interface value if flag is not default      *
 *    events_cbs                  - [IN]                                             *
 *    config_timeout              - [IN]                                             *
 *    autoreg_update_host_func_cb - [IN]                                             *
 *                                                                                   *
 * Comments: helper function for get_hostid_by_host                                  *
 *                                                                                   *
 *************************************************************************************/
static void	db_register_host(const char *host, const char *ip, unsigned short port, unsigned int connection_type,
		const char *host_metadata, zbx_conn_flags_t flag, const char *interface,
		const zbx_events_funcs_t *events_cbs, int config_timeout,
		zbx_autoreg_update_host_func_t autoreg_update_host_func_cb)
{
	char		dns[ZBX_INTERFACE_DNS_LEN_MAX], ip_addr[ZBX_INTERFACE_IP_LEN_MAX];
	const char	*p, *p_ip, *p_dns;
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
	zbx_dc_config_update_autoreg_host(host, p_ip, p_dns, port, host_metadata, flag, now);

	autoreg_update_host_func_cb(NULL, host, p_ip, p_dns, port, connection_type, host_metadata, (unsigned short)flag,
			now, events_cbs);
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

/************************************************************************************
 *                                                                                  *
 * Purpose: checks for host name and returns hostid                                 *
 *                                                                                  *
 * Parameters:                                                                      *
 *    sock                        - [IN] open socket of server-agent connection     *
 *    host                        - [IN] host name                                  *
 *    ip                          - [IN] IP address of host                         *
 *    port                        - [IN] port of host                               *
 *    host_metadata               - [IN]                                            *
 *    flag                        - [IN] flag describing interface type             *
 *    interface                   - [IN] interface value if flag is not default     *
 *    events_cbs                  - [IN]                                            *
 *    config_timeout              - [IN]                                            *
 *    autoreg_update_host_func_cb - [IN]                                            *
 *    hostid                      - [OUT]                                           *
 *    revision                    - [OUT] host configuration revision               *
 *    error                       - [OUT] error message (buffer provided by caller) *
 *                                                                                  *
 * Return value:  SUCCEED - host is found                                           *
 *                FAIL - error occurred or host not found                           *
 *                                                                                  *
 * Comments: NB! adds host to the database if it does not exist or if it            *
 *           exists but metadata, interface, interface type or port has             *
 *           changed                                                                *
 *                                                                                  *
 ************************************************************************************/
static int	get_hostid_by_host_or_autoregister(const zbx_socket_t *sock, const char *host, const char *ip,
		unsigned short port, const char *host_metadata, zbx_conn_flags_t flag, const char *interface,
		const zbx_events_funcs_t *events_cbs, int config_timeout,
		zbx_autoreg_update_host_func_t autoreg_update_host_func_cb, zbx_uint64_t *hostid,
		zbx_uint64_t *revision, zbx_comms_redirect_t *redirect, char *error)
{
#define AUTOREG_ENABLED			0
#define AUTOREG_DISABLED		1

	char		*ch_error;
	int		ret = FAIL;
	int		autoreg = AUTOREG_ENABLED;
	unsigned char	status, monitored_by;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() host:'%s' metadata:'%s'", __func__, host, host_metadata);

	if (FAIL == zbx_check_hostname(host, &ch_error))
	{
		zbx_snprintf(error, MAX_STRING_LEN, "invalid host name [%s]: %s", host, ch_error);
		zbx_free(ch_error);
		goto out;
	}

	/* if host exists then check host connection permissions */
	if (FAIL == zbx_dc_check_host_conn_permissions(host, sock, hostid, &status, &monitored_by, revision, redirect,
			&ch_error))
	{
		zbx_snprintf(error, MAX_STRING_LEN, "%s", ch_error);
		zbx_free(ch_error);
		goto out;
	}

	if (0 != (trapper_get_program_type()() & ZBX_PROGRAM_TYPE_SERVER))
	{
		if (0 == zbx_dc_get_auto_registration_action_count())
			autoreg = AUTOREG_DISABLED;
	}

	/* if host does not exist then check autoregistration connection permissions */
	if (0 == *hostid && AUTOREG_ENABLED == autoreg &&
		SUCCEED != zbx_autoreg_host_check_permissions(host, ip, port, sock))
	{
		autoreg = AUTOREG_DISABLED;
	}

	if (AUTOREG_ENABLED == autoreg && SUCCEED == zbx_dc_is_autoreg_host_changed(host, port, host_metadata, flag,
			interface, (int)time(NULL)))
	{
		db_register_host(host, ip, port, sock->connection_type, host_metadata, flag, interface, events_cbs,
				config_timeout, autoreg_update_host_func_cb);
	}

	if (0 == *hostid)
	{
		zbx_snprintf(error, MAX_STRING_LEN, "host [%s] not found", host);
		goto out;
	}

	if (HOST_STATUS_MONITORED != status)
	{
		zbx_snprintf(error, MAX_STRING_LEN, "host \"%s\" not monitored", host);
		goto out;
	}

	if (HOST_MONITORED_BY_SERVER != monitored_by)
	{
		zbx_snprintf(error, MAX_STRING_LEN, "host \"%s\" is monitored by a proxy", host);
		goto out;
	}

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

#undef AUTOREG_DISABLED
#undef AUTOREG_ENABLED
	return ret;
}

/***************************************************************************
 *                                                                         *
 * Purpose: sends list of active checks to host (older version agent)      *
 *                                                                         *
 * Parameters:                                                             *
 *    sock                   - [IN] open socket of server-agent connection *
 *    request                - [IN] request buffer                         *
 *    events_cbs             - [IN]                                        *
 *    config_timeout         - [IN]                                        *
 *    autoreg_update_host_cb - [IN]                                        *
 *                                                                         *
 * Return value:  SUCCEED - list of active checks sent successfully        *
 *                FAIL - error occurred                                    *
 *                                                                         *
 * Comments: format of the request: ZBX_GET_ACTIVE_CHECKS\n<host name>\n   *
 *           format of the list: key:delay:last_log_size                   *
 *                                                                         *
 ***************************************************************************/
int	send_list_of_active_checks(zbx_socket_t *sock, char *request, const zbx_events_funcs_t *events_cbs,
		int config_timeout, zbx_autoreg_update_host_func_t autoreg_update_host_cb)
{
	char		*host = NULL, *p, *buffer = NULL, error[MAX_STRING_LEN];
	size_t		buffer_alloc = 8 * ZBX_KIBIBYTE, buffer_offset = 0;
	int		ret = FAIL, i, num = 0;
	zbx_uint64_t	hostid, revision;

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
	if (FAIL == get_hostid_by_host_or_autoregister(sock, host, sock->peer, ZBX_DEFAULT_AGENT_PORT, "", 0, "",
			events_cbs, config_timeout, autoreg_update_host_cb, &hostid, &revision, NULL, error))
	{
		goto out;
	}

	num = zbx_dc_config_get_active_items_count_by_hostid(hostid);

	buffer = (char *)zbx_malloc(buffer, buffer_alloc);

	if (0 != num)
	{
		zbx_dc_item_t		*dc_items;
		int			*errcodes;
		zbx_dc_um_handle_t	*um_handle;

		um_handle = zbx_dc_open_user_macros();

		dc_items = (zbx_dc_item_t *)zbx_malloc(NULL, sizeof(zbx_dc_item_t) * num);
		errcodes = (int *)zbx_malloc(NULL, sizeof(int) * num);

		zbx_dc_config_get_active_items_by_hostid(dc_items, hostid, errcodes, num);

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

			zbx_substitute_simple_macros(NULL, NULL, NULL, NULL, &dc_items[i].host.hostid, NULL, NULL, NULL,
					NULL, NULL, NULL, NULL, &dc_items[i].delay, ZBX_MACRO_TYPE_COMMON, NULL, 0);

			if (SUCCEED != zbx_interval_preproc(dc_items[i].delay, &delay, NULL, NULL))
				continue;

			zbx_snprintf_alloc(&buffer, &buffer_alloc, &buffer_offset, "%s:%d:" ZBX_FS_UI64 "\n",
					dc_items[i].key_orig, delay, dc_items[i].lastlogsize);
		}

		zbx_dc_config_clean_items(dc_items, errcodes, num);

		zbx_free(errcodes);
		zbx_free(dc_items);

		zbx_dc_close_user_macros(um_handle);
	}

	zbx_strcpy_alloc(&buffer, &buffer_alloc, &buffer_offset, "ZBX_EOF\n");

	zabbix_log(LOG_LEVEL_DEBUG, "%s() sending [%s]", __func__, buffer);

	if (SUCCEED != zbx_tcp_send_ext(sock, buffer, strlen(buffer), 0, 0, config_timeout))
		zbx_strlcpy(error, zbx_socket_strerror(), MAX_STRING_LEN);
	else
		ret = SUCCEED;

	zbx_free(buffer);
out:
	if (FAIL == ret)
		zabbix_log(LOG_LEVEL_WARNING, "cannot send list of active checks to \"%s\": %s", sock->peer, error);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: appends non duplicate string to string vector                     *
 *                                                                            *
 * Parameters: vector - [IN/OUT] string vector                                *
 *             str    - [IN] string to append                                 *
 *                                                                            *
 ******************************************************************************/
static void	zbx_vector_str_append_uniq(zbx_vector_str_t *vector, const char *str)
{
	if (FAIL == zbx_vector_str_search(vector, str, ZBX_DEFAULT_STR_COMPARE_FUNC))
		zbx_vector_str_append(vector, zbx_strdup(NULL, str));
}

/******************************************************************************
 *                                                                            *
 * Purpose: extracts global regular expression names from item key            *
 *                                                                            *
 * Parameters: key     - [IN] item key to parse                               *
 *             regexps - [OUT] extracted regular expression names             *
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
	{
		item_key = ZBX_KEY_LOG;
	}
	else if (0 == strncmp(key, "eventlog[", 9) || 0 == strncmp(key, "eventlog.count[", 15))
	{
		item_key = ZBX_KEY_EVENTLOG;
	}
	else
	{
		return;
	}

	zbx_init_agent_request(&request);

	if (SUCCEED != zbx_parse_item_key(key, &request))
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
#undef ZBX_KEY_LOG
#undef ZBX_KEY_EVENTLOG
}

/********************************************************************************
 *                                                                              *
 * Purpose: sends list of active checks to host                                 *
 *                                                                              *
 * Parameters:                                                                  *
 *    sock                        - [IN] open socket of server-agent connection *
 *    jp                          - [IN] request buffer                         *
 *    events_cbs                  - [IN]                                        *
 *    config_timeout              - [IN]                                        *
 *    autoreg_update_host_func_cb - [IN]                                        *
 *                                                                              *
 * Return value:  SUCCEED - list of active checks sent successfully             *
 *                FAIL - an error occurred                                      *
 *                                                                              *
 ********************************************************************************/
int	send_list_of_active_checks_json(zbx_socket_t *sock, zbx_json_parse_t *jp,
		const zbx_events_funcs_t *events_cbs, int config_timeout,
		zbx_autoreg_update_host_func_t autoreg_update_host_cb)
{
	char			host[ZBX_HOSTNAME_BUF_LEN], tmp[MAX_STRING_LEN], ip[ZBX_INTERFACE_IP_LEN_MAX],
				error[MAX_STRING_LEN], *host_metadata = NULL, *interface = NULL, *buffer = NULL;
	struct zbx_json		json;
	int			ret = FAIL, version, num = 0;
	zbx_uint64_t		hostid, revision, agent_config_revision;
	size_t			host_metadata_alloc = 1,	/* for at least NUL-terminated string */
				interface_alloc = 1,		/* for at least NUL-terminated string */
				buffer_size, reserved = 0;
	unsigned short		port;
	zbx_conn_flags_t	flag = ZBX_CONN_DEFAULT;
	zbx_session_t		*session = NULL;
	zbx_vector_expression_t	regexps;
	zbx_vector_str_t	names;
	zbx_comms_redirect_t	redirect = {0};

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

	if (FAIL == get_hostid_by_host_or_autoregister(sock, host, ip, port, host_metadata, flag, interface, events_cbs,
			config_timeout, autoreg_update_host_cb, &hostid, &revision, &redirect, error))
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
		num = zbx_dc_config_get_active_items_count_by_hostid(hostid);
	}

	if (0 != num)
	{
		zbx_dc_item_t		*dc_items;
		int			*errcodes, delay;
		zbx_dc_um_handle_t	*um_handle;
		char			*timeout = NULL;

		dc_items = (zbx_dc_item_t *)zbx_malloc(NULL, sizeof(zbx_dc_item_t) * num);
		errcodes = (int *)zbx_malloc(NULL, sizeof(int) * num);
		zbx_dc_config_get_active_items_by_hostid(dc_items, hostid, errcodes, num);

		um_handle = zbx_dc_open_user_macros();

		for (int i = 0; i < num; i++)
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

			zbx_substitute_simple_macros(NULL, NULL, NULL, NULL, &dc_items[i].host.hostid, NULL, NULL, NULL,
					NULL, NULL, NULL, NULL, &dc_items[i].delay, ZBX_MACRO_TYPE_COMMON, NULL, 0);

			if (ZBX_COMPONENT_VERSION(4, 4, 0) > version &&
					SUCCEED != zbx_interval_preproc(dc_items[i].delay, &delay, NULL, NULL))
			{
				continue;
			}

			dc_items[i].key = zbx_strdup(dc_items[i].key, dc_items[i].key_orig);
			zbx_substitute_key_macros_unmasked(&dc_items[i].key, NULL, &dc_items[i], NULL, NULL,
					ZBX_MACRO_TYPE_ITEM_KEY, NULL, 0);

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

			timeout = zbx_strdup(NULL, dc_items[i].timeout_orig);

			zbx_substitute_simple_macros(NULL, NULL, NULL, NULL, &dc_items[i].host.hostid, NULL, NULL,
						NULL, NULL, NULL, NULL, NULL, &timeout, ZBX_MACRO_TYPE_COMMON, NULL,
						0);

			zbx_json_addstring(&json, ZBX_PROTO_TAG_TIMEOUT, timeout, ZBX_JSON_TYPE_STRING);

			zbx_json_close(&json);

			zbx_itemkey_extract_global_regexps(dc_items[i].key, &names);

			zbx_free(dc_items[i].key);
			zbx_free(timeout);
		}

		zbx_dc_config_clean_items(dc_items, errcodes, num);

		zbx_free(errcodes);
		zbx_free(dc_items);

		zbx_dc_close_user_macros(um_handle);
	}

	zbx_json_close(&json);

	zbx_remote_commands_prepare_to_send(&json, hostid, config_timeout);

	if (SUCCEED == zbx_vps_monitor_capped())
	{
		zbx_json_addstring(&json, ZBX_PROTO_TAG_HISTORY_UPLOAD, ZBX_PROTO_VALUE_HISTORY_UPLOAD_DISABLED,
				ZBX_JSON_TYPE_STRING);
	}

	if (ZBX_COMPONENT_VERSION(4, 4, 0) == version || ZBX_COMPONENT_VERSION(5, 0, 0) == version)
		zbx_json_adduint64(&json, ZBX_PROTO_TAG_REFRESH_UNSUPPORTED, 600);

	zbx_dc_get_expressions_by_names(&regexps, (const char * const *)names.values, names.values_num);

	if (0 < regexps.values_num)
	{
		char	str[32];

		zbx_json_addarray(&json, ZBX_PROTO_TAG_REGEXP);

		for (int i = 0; i < regexps.values_num; i++)
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

	if (0 != redirect.revision || ZBX_REDIRECT_NONE != redirect.reset)
		zbx_add_redirect_response(&json, &redirect);
	else
		zbx_json_addstring(&json, ZBX_PROTO_TAG_INFO, error, ZBX_JSON_TYPE_STRING);

	zabbix_log(LOG_LEVEL_DEBUG, "%s() sending [%s]", __func__, json.buffer);

	ret = zbx_tcp_send_to(sock, json.buffer, config_timeout);

	zbx_json_free(&json);
out:
	for (int i = 0; i < names.values_num; i++)
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
