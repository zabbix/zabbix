/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
#include "comms.h"
#include "log.h"
#include "../../libs/zbxcrypto/tls_tcp_active.h"

#include "checks_agent.h"

#if !(defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL))
extern unsigned char	program_type;
#endif

/******************************************************************************
 *                                                                            *
 * Function: get_value_agent                                                  *
 *                                                                            *
 * Purpose: retrieve data from Zabbix agent                                   *
 *                                                                            *
 * Parameters: item - item we are interested in                               *
 *                                                                            *
 * Return value: SUCCEED - data successfully retrieved and stored in result   *
 *                         and result_str (as string)                         *
 *               NETWORK_ERROR - network related error occurred               *
 *               NOTSUPPORTED - item not supported by the agent               *
 *               AGENT_ERROR - uncritical error on agent side occurred        *
 *               FAIL - otherwise                                             *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: error will contain error message                                 *
 *                                                                            *
 ******************************************************************************/
int	get_value_agent(DC_ITEM *item, AGENT_RESULT *result)
{
	const char	*__function_name = "get_value_agent";
	zbx_socket_t	s;
	char		*buffer = NULL, *tls_arg1, *tls_arg2;
	int		ret = SUCCEED;
	ssize_t		received_len;

	if (SUCCEED == zabbix_check_log_level(LOG_LEVEL_DEBUG))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "In %s() host:'%s' addr:'%s' key:'%s' conn:'%s'", __function_name,
				item->host.host, item->interface.addr, item->key,
				zbx_tls_connection_type_name(item->host.tls_connect));
	}

	switch (item->host.tls_connect)
	{
		case ZBX_TCP_SEC_UNENCRYPTED:
			tls_arg1 = NULL;
			tls_arg2 = NULL;
			break;
#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
		case ZBX_TCP_SEC_TLS_CERT:
			tls_arg1 = item->host.tls_issuer;
			tls_arg2 = item->host.tls_subject;
			break;
		case ZBX_TCP_SEC_TLS_PSK:
			tls_arg1 = item->host.tls_psk_identity;
			tls_arg2 = item->host.tls_psk;
			break;
#else
		case ZBX_TCP_SEC_TLS_CERT:
		case ZBX_TCP_SEC_TLS_PSK:
			SET_MSG_RESULT(result, zbx_dsprintf(NULL, "A TLS connection is configured to be used with agent"
					" but support for TLS was not compiled into %s.",
					get_program_type_string(program_type)));
			ret = CONFIG_ERROR;
			goto out;
#endif
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid TLS connection parameters."));
			ret = CONFIG_ERROR;
			goto out;
	}

	if (SUCCEED == (ret = zbx_tcp_connect(&s, CONFIG_SOURCE_IP, item->interface.addr, item->interface.port, 0,
			item->host.tls_connect, tls_arg1, tls_arg2)))
	{
		buffer = zbx_dsprintf(buffer, "%s\n", item->key);
		zabbix_log(LOG_LEVEL_DEBUG, "Sending [%s]", buffer);

		/* send requests using old protocol */
		if (SUCCEED != zbx_tcp_send_raw(&s, buffer))
			ret = NETWORK_ERROR;
		else if (FAIL != (received_len = zbx_tcp_recv_ext(&s, ZBX_TCP_READ_UNTIL_CLOSE, 0)))
			ret = SUCCEED;
		else if (SUCCEED == zbx_alarm_timed_out())
			ret = TIMEOUT_ERROR;
		else
			ret = NETWORK_ERROR;

		zbx_free(buffer);
	}
	else
		ret = NETWORK_ERROR;

	if (SUCCEED == ret)
	{
		zbx_rtrim(s.buffer, " \r\n");
		zbx_ltrim(s.buffer, " ");

		zabbix_log(LOG_LEVEL_DEBUG, "get value from agent result: '%s'", s.buffer);

		if (0 == strcmp(s.buffer, ZBX_NOTSUPPORTED))
		{
			/* 'ZBX_NOTSUPPORTED\0<error message>' */
			if (sizeof(ZBX_NOTSUPPORTED) < s.read_bytes)
				SET_MSG_RESULT(result, zbx_dsprintf(NULL, "%s", s.buffer + sizeof(ZBX_NOTSUPPORTED)));
			else
				SET_MSG_RESULT(result, zbx_strdup(NULL, "Not supported by Zabbix Agent"));

			ret = NOTSUPPORTED;
		}
		else if (0 == strcmp(s.buffer, ZBX_ERROR))
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Zabbix Agent non-critical error"));
			ret = AGENT_ERROR;
		}
		else if (0 == received_len)
		{
			SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Received empty response from Zabbix Agent at [%s]."
					" Assuming that agent dropped connection because of access permissions.",
					item->interface.addr));
			ret = NETWORK_ERROR;
		}
		else if (SUCCEED != set_result_type(result, item->value_type, item->data_type, s.buffer))
			ret = NOTSUPPORTED;
	}
	else
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Get value from agent failed: %s", zbx_socket_strerror()));

	zbx_tcp_close(&s);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}
