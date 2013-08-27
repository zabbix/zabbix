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
	zbx_sock_t	s;
	int		ret = NOTSUPPORTED, flags;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (FAIL == zbx_tcp_connect(&s, CONFIG_SOURCE_IP, item->interface.addr, item->interface.port, 0))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "cannot connect to TELNET server: %s", zbx_tcp_strerror()));
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
	char	cmd[MAX_STRING_LEN], params[MAX_STRING_LEN], dns[INTERFACE_DNS_LEN_MAX], port[8], encoding[32];

	if (0 == parse_command(item->key, cmd, sizeof(cmd), params, sizeof(params)))
		return NOTSUPPORTED;

	if (0 != strcmp(TELNET_RUN_KEY, cmd))
		return NOTSUPPORTED;

	if (4 < num_param(params))
		return NOTSUPPORTED;

	if (0 != get_param(params, 2, dns, sizeof(dns)))
		*dns = '\0';

	if ('\0' != *dns)
	{
		strscpy(item->interface.dns_orig, dns);
		item->interface.addr = item->interface.dns_orig;
	}

	if (0 != get_param(params, 3, port, sizeof(port)))
		*port = '\0';

	if (0 != get_param(params, 4, encoding, sizeof(encoding)))
		*encoding = '\0';

	if ('\0' != *port)
	{
		if (FAIL == is_ushort(port, &item->interface.port))
			return NOTSUPPORTED;
	}
	else
		item->interface.port = ZBX_DEFAULT_TELNET_PORT;

	return telnet_run(item, result, encoding);
}
