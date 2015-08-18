/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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

#include "checks_agent.h"

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
	char		buffer[MAX_STRING_LEN];
	int		ret = SUCCEED;
	ssize_t		received_len;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() host:'%s' addr:'%s' key:'%s'",
			__function_name, item->host.host, item->interface.addr, item->key);

	if (SUCCEED == (ret = zbx_tcp_connect(&s, CONFIG_SOURCE_IP, item->interface.addr, item->interface.port, 0)))
	{
		zbx_snprintf(buffer, sizeof(buffer), "%s\n", item->key);
		zabbix_log(LOG_LEVEL_DEBUG, "Sending [%s]", buffer);

		/* send requests using old protocol */
		if (SUCCEED != zbx_tcp_send_raw(&s, buffer))
			ret = NETWORK_ERROR;
		else if (FAIL != (received_len = zbx_tcp_recv_ext(&s, ZBX_TCP_READ_UNTIL_CLOSE, 0)))
			ret = SUCCEED;
		else
			ret = TIMEOUT_ERROR;
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
				zbx_snprintf(buffer, sizeof(buffer), "%s", s.buffer + sizeof(ZBX_NOTSUPPORTED));
			else
				zbx_snprintf(buffer, sizeof(buffer), "Not supported by Zabbix Agent");

			SET_MSG_RESULT(result, strdup(buffer));
			ret = NOTSUPPORTED;
		}
		else if (0 == strcmp(s.buffer, ZBX_ERROR))
		{
			zbx_snprintf(buffer, sizeof(buffer), "Zabbix Agent non-critical error");
			SET_MSG_RESULT(result, strdup(buffer));
			ret = AGENT_ERROR;
		}
		else if (0 == received_len)
		{
			zbx_snprintf(buffer, sizeof(buffer), "Received empty response from Zabbix Agent at [%s]."
					" Assuming that agent dropped connection because of access permissions.",
					item->interface.addr);
			SET_MSG_RESULT(result, strdup(buffer));
			ret = NETWORK_ERROR;
		}
		else if (SUCCEED != set_result_type(result, item->value_type, item->data_type, s.buffer))
			ret = NOTSUPPORTED;
	}
	else
	{
		zbx_snprintf(buffer, sizeof(buffer), "Get value from agent failed: %s",
				zbx_socket_strerror());
		SET_MSG_RESULT(result, strdup(buffer));
	}

	zbx_tcp_close(&s);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}
