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

#include "zbxdbhigh.h"

#include "log.h"
#include "zbxsysinfo.h"
#include "zbxserver.h"
#include "zbxtasks.h"
#include "zbxdiscovery.h"
#include "zbxalgo.h"
#include "preproc.h"
#include "zbxcrypto.h"
#include "../zbxkvs/kvs.h"
#include "zbxlld.h"
#include "events.h"
#include "../zbxvault/vault.h"
#include "zbxavailability.h"
#include "zbxcommshigh.h"
#include "zbxnum.h"
#include "zbxtime.h"
#include "zbxip.h"
#include "version.h"
#include "zbxversion.h"

extern char	*CONFIG_SERVER;

/******************************************************************************
 *                                                                            *
 * Purpose:                                                                   *
 *     Check access rights to a passive proxy for the given connection and    *
 *     send a response if denied.                                             *
 *                                                                            *
 * Parameters:                                                                *
 *     sock           - [IN] connection socket context                        *
 *     send_response  - [IN] to send or not to send a response to server.     *
 *                          Value: ZBX_SEND_RESPONSE or                       *
 *                          ZBX_DO_NOT_SEND_RESPONSE                          *
 *     req            - [IN] request, included into error message             *
 *     zbx_config_tls - [IN] configured requirements to allow access          *
 *                                                                            *
 * Return value:                                                              *
 *     SUCCEED - access is allowed                                            *
 *     FAIL    - access is denied                                             *
 *                                                                            *
 ******************************************************************************/
int	check_access_passive_proxy(zbx_socket_t *sock, int send_response, const char *req,
		const zbx_config_tls_t *zbx_config_tls)
{
	char	*msg = NULL;

	if (FAIL == zbx_tcp_check_allowed_peers(sock, CONFIG_SERVER))
	{
		zabbix_log(LOG_LEVEL_WARNING, "%s from server \"%s\" is not allowed: %s", req, sock->peer,
				zbx_socket_strerror());

		if (ZBX_SEND_RESPONSE == send_response)
			zbx_send_proxy_response(sock, FAIL, "connection is not allowed", CONFIG_TIMEOUT);

		return FAIL;
	}

	if (0 == (zbx_config_tls->accept_modes & sock->connection_type))
	{
		msg = zbx_dsprintf(NULL, "%s over connection of type \"%s\" is not allowed", req,
				zbx_tcp_connection_type_name(sock->connection_type));

		zabbix_log(LOG_LEVEL_WARNING, "%s from server \"%s\" by proxy configuration parameter \"TLSAccept\"",
				msg, sock->peer);

		if (ZBX_SEND_RESPONSE == send_response)
			zbx_send_proxy_response(sock, FAIL, msg, CONFIG_TIMEOUT);

		zbx_free(msg);
		return FAIL;
	}

#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	if (ZBX_TCP_SEC_TLS_CERT == sock->connection_type)
	{
		if (SUCCEED == zbx_check_server_issuer_subject(sock, zbx_config_tls->server_cert_issuer,
				zbx_config_tls->server_cert_subject, &msg))
		{
			return SUCCEED;
		}

		zabbix_log(LOG_LEVEL_WARNING, "%s from server \"%s\" is not allowed: %s", req, sock->peer, msg);

		if (ZBX_SEND_RESPONSE == send_response)
			zbx_send_proxy_response(sock, FAIL, "certificate issuer or subject mismatch", CONFIG_TIMEOUT);

		zbx_free(msg);
		return FAIL;
	}
	else if (ZBX_TCP_SEC_TLS_PSK == sock->connection_type)
	{
		if (0 != (ZBX_PSK_FOR_PROXY & zbx_tls_get_psk_usage()))
			return SUCCEED;

		zabbix_log(LOG_LEVEL_WARNING, "%s from server \"%s\" is not allowed: it used PSK which is not"
				" configured for proxy communication with server", req, sock->peer);

		if (ZBX_SEND_RESPONSE == send_response)
			zbx_send_proxy_response(sock, FAIL, "wrong PSK used", CONFIG_TIMEOUT);

		return FAIL;
	}
#endif
	return SUCCEED;
}

void	calc_timestamp(const char *line, int *timestamp, const char *format)
{
	int		hh, mm, ss, yyyy, dd, MM;
	int		hhc = 0, mmc = 0, ssc = 0, yyyyc = 0, ddc = 0, MMc = 0;
	int		i, num;
	struct tm	tm;
	time_t		t;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	hh = mm = ss = yyyy = dd = MM = 0;

	for (i = 0; '\0' != format[i] && '\0' != line[i]; i++)
	{
		if (0 == isdigit(line[i]))
			continue;

		num = (int)line[i] - 48;

		switch ((char)format[i])
		{
			case 'h':
				hh = 10 * hh + num;
				hhc++;
				break;
			case 'm':
				mm = 10 * mm + num;
				mmc++;
				break;
			case 's':
				ss = 10 * ss + num;
				ssc++;
				break;
			case 'y':
				yyyy = 10 * yyyy + num;
				yyyyc++;
				break;
			case 'd':
				dd = 10 * dd + num;
				ddc++;
				break;
			case 'M':
				MM = 10 * MM + num;
				MMc++;
				break;
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "%s() %02d:%02d:%02d %02d/%02d/%04d", __func__, hh, mm, ss, MM, dd, yyyy);

	/* seconds can be ignored, no ssc here */
	if (0 != hhc && 0 != mmc && 0 != yyyyc && 0 != ddc && 0 != MMc)
	{
		tm.tm_sec = ss;
		tm.tm_min = mm;
		tm.tm_hour = hh;
		tm.tm_mday = dd;
		tm.tm_mon = MM - 1;
		tm.tm_year = yyyy - 1900;
		tm.tm_isdst = -1;

		if (0 < (t = mktime(&tm)))
			*timestamp = t;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() timestamp:%d", __func__, *timestamp);
}

/******************************************************************************
 *                                                                            *
 * Purpose: extracts protocol version from json data                          *
 *                                                                            *
 * Parameters:                                                                *
 *     jp      - [IN] JSON with the proxy version                             *
 *                                                                            *
 * Return value: The protocol version in textual representation, for example, *
 *               "6.4.0alpha1",                                               *
 *     actual proxy version - if proxy version was successfully extracted     *
 *     undefined version    - otherwise                                       *
 *                                                                            *
 * Comments: allocates memory                                                 *
 *                                                                            *
 ******************************************************************************/
char	*zbx_get_proxy_protocol_version_str(struct zbx_json_parse *jp)
{
	char	value[MAX_STRING_LEN];

	if (NULL != jp && SUCCEED == zbx_json_value_by_name(jp, ZBX_PROTO_TAG_VERSION, value, sizeof(value), NULL))
		return strdup(value);

	return strdup(ZBX_VERSION_UNDEFINED_STR);
}

/******************************************************************************
 *                                                                            *
 * Purpose: converts protocol version fom textual to numeric representation   *
 *          for version comparison. The function truncates release candidate  *
 *          part of the version.                                              *
 *                                                                            *
 * Parameters:                                                                *
 *     version_str - [IN] proxy version, for example "6.4.0alpha1".           *
 *                                                                            *
 * Return value: The protocol version in numeric representation, for example, *
 *               060400                                                       *
 *     actual proxy version - if proxy version was successfully extracted     *
 *     proxy version 3.2    - otherwise                                       *
 *                                                                            *
 ******************************************************************************/
int	zbx_get_proxy_protocol_version_int(const char *version_str)
{
	int	version_int;

	if (0 != strcmp(ZBX_VERSION_UNDEFINED_STR, version_str) &&
			FAIL != (version_int = zbx_get_component_version(version_str)))
	{
		return version_int;
	}

	return ZBX_COMPONENT_VERSION(3, 2, 0);
}

