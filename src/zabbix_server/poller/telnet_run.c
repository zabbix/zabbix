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

#include "telnet_run.h"

#include "zbxcomms.h"
#include "log.h"
#include "zbxnum.h"

extern char    *CONFIG_SOURCE_IP;

/*
 * Example: telnet.run["ls /"]
 */
int	telnet_run(DC_ITEM *item, AGENT_RESULT *result, const char *encoding)
{
	zbx_socket_t	s;
	int		ret = NOTSUPPORTED, flags;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (FAIL == zbx_tcp_connect(&s, CONFIG_SOURCE_IP, item->interface.addr, item->interface.port, 0,
			ZBX_TCP_SEC_UNENCRYPTED, NULL, NULL))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot connect to TELNET server: %s",
				zbx_socket_strerror()));
		goto close;
	}

	flags = fcntl(s.socket, F_GETFL);

	if (-1 == flags)
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, " error in getting the status flag: %s",
				zbx_strerror(errno)));
	}

	if (0 == (flags & O_NONBLOCK) && (-1 == fcntl(s.socket, F_SETFL, flags | O_NONBLOCK)))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, " error in setting the status flag: %s",
				zbx_strerror(errno)));
	}

	if (FAIL == zbx_telnet_login(s.socket, item->username, item->password, result))
		goto tcp_close;

	if (FAIL == zbx_telnet_execute(s.socket, item->params, result, encoding))
		goto tcp_close;

	ret = SUCCEED;
tcp_close:
	zbx_tcp_close(&s);
close:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}
