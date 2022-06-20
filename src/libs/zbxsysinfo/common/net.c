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

#include "net.h"

#include "common.h"
#include "zbxcomms.h"
#include "log.h"
#include "cfg.h"

int	tcp_expect(const char *host, unsigned short port, int timeout, const char *request,
		int (*validate_func)(const char *), const char *sendtoclose, int *value_int)
{
	zbx_socket_t	s;
	const char	*buf;
	int		net, val = ZBX_TCP_EXPECT_OK;

	*value_int = 0;

	if (SUCCEED != (net = zbx_tcp_connect(&s, CONFIG_SOURCE_IP, host, port, timeout, ZBX_TCP_SEC_UNENCRYPTED, NULL,
			NULL)))
	{
		goto out;
	}

	if (NULL != request)
		net = zbx_tcp_send_raw(&s, request);

	if (NULL != validate_func && SUCCEED == net)
	{
		val = ZBX_TCP_EXPECT_FAIL;

		while (NULL != (buf = zbx_tcp_recv_line(&s)))
		{
			val = validate_func(buf);

			if (ZBX_TCP_EXPECT_OK == val)
				break;

			if (ZBX_TCP_EXPECT_FAIL == val)
			{
				zabbix_log(LOG_LEVEL_DEBUG, "TCP expect content error, received [%s]", buf);
				break;
			}
		}
	}

	if (NULL != sendtoclose && SUCCEED == net && ZBX_TCP_EXPECT_OK == val)
		(void)zbx_tcp_send_raw(&s, sendtoclose);

	if (SUCCEED == net && ZBX_TCP_EXPECT_OK == val)
		*value_int = 1;

	zbx_tcp_close(&s);
out:
	if (SUCCEED != net)
		zabbix_log(LOG_LEVEL_DEBUG, "TCP expect network error: %s", zbx_socket_strerror());

	return SYSINFO_RET_OK;
}

int	NET_TCP_PORT(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	unsigned short	port;
	int		value_int, ret;
	char		*ip_str, ip[ZBX_MAX_DNSNAME_LEN + 1], *port_str;

	if (2 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	ip_str = get_rparam(request, 0);
	port_str = get_rparam(request, 1);

	if (NULL == ip_str || '\0' == *ip_str)
		strscpy(ip, "127.0.0.1");
	else
		strscpy(ip, ip_str);

	if (NULL == port_str || SUCCEED != is_ushort(port_str, &port))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		return SYSINFO_RET_FAIL;
	}

	if (SYSINFO_RET_OK == (ret = tcp_expect(ip, port, CONFIG_TIMEOUT, NULL, NULL, NULL, &value_int)))
		SET_UI64_RESULT(result, value_int);

	return ret;
}
