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

#include "telnet_run.h"
#include "zbxcomms.h"

/*
 * Example: telnet.run["ls /"]
 */
int	telnet_run(zbx_dc_item_t *item, AGENT_RESULT *result, const char *encoding, int timeout,
		const char *config_source_ip)
{
	zbx_socket_t	s;
	int		ret = NOTSUPPORTED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (FAIL == zbx_tcp_connect(&s, config_source_ip, item->interface.addr, item->interface.port, timeout,
			ZBX_TCP_SEC_UNENCRYPTED, NULL, NULL))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot connect to TELNET server: %s",
				zbx_socket_strerror()));
		goto close;
	}

	if (FAIL == zbx_telnet_login(&s, item->username, item->password, result))
		goto tcp_close;

	if (FAIL == zbx_telnet_execute(&s, item->params, result, encoding))
		goto tcp_close;

	ret = SUCCEED;
tcp_close:
	zbx_tcp_close(&s);
close:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}
