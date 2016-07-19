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

#include "checks_telnet.h"

#include "telnet.h"
#include "comms.h"
#include "log.h"

#define TELNET_RUN_KEY	"telnet.run"

/*
 * Example: telnet.run["ls /"]
 */
static int	telnet_run(DC_ITEM *item, AGENT_RESULT *result, const char *encoding)
{
	const char	*__function_name = "telnet_run";
	zbx_socket_t	s;
	int		ret = NOTSUPPORTED, flags;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (FAIL == zbx_tcp_connect(&s, CONFIG_SOURCE_IP, item->interface.addr, item->interface.port, 0,
			ZBX_TCP_SEC_UNENCRYPTED, NULL, NULL))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot connect to TELNET server: %s",
				zbx_socket_strerror()));
		goto close;
	}

	flags = fcntl(s.socket, F_GETFL);
	if (0 == (flags & O_NONBLOCK))
		fcntl(s.socket, F_SETFL, flags | O_NONBLOCK);

	if (FAIL == telnet_login(s.socket, item->username, item->password, result))
		goto tcp_close;

	if (FAIL == telnet_execute(s.socket, item->params, result, encoding))
		goto tcp_close;

	ret = SUCCEED;
tcp_close:
	zbx_tcp_close(&s);
close:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

int	get_value_telnet(DC_ITEM *item, AGENT_RESULT *result)
{
	AGENT_REQUEST	request;
	int		ret = NOTSUPPORTED;
	const char	*port, *encoding, *dns;

	init_request(&request);

	if (SUCCEED != parse_item_key(item->key, &request))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid item key format."));
		goto out;
	}

	if (0 != strcmp(TELNET_RUN_KEY, get_rkey(&request)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Unsupported item key for this item type."));
		goto out;
	}

	if (4 < get_rparams_num(&request))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		goto out;
	}

	if (NULL != (dns = get_rparam(&request, 1)) && '\0' != *dns)
	{
		strscpy(item->interface.dns_orig, dns);
		item->interface.addr = item->interface.dns_orig;
	}

	if (NULL != (port = get_rparam(&request, 2)) && '\0' != *port)
	{
		if (FAIL == is_ushort(port, &item->interface.port))
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid third parameter."));
			goto out;
		}
	}
	else
		item->interface.port = ZBX_DEFAULT_TELNET_PORT;

	encoding = get_rparam(&request, 3);

	ret = telnet_run(item, result, ZBX_NULL2EMPTY_STR(encoding));
out:
	free_request(&request);

	return ret;
}
