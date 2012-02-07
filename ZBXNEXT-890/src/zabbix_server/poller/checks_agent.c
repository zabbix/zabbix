/*
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
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
	zbx_sock_t	s;
	char		*buf, buffer[MAX_STRING_LEN], *conn;
	int		ret = SUCCEED;

	conn = item->host.useip == 1 ? item->host.ip : item->host.dns;
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() host:'%s' addr:'%s' key:'%s'",
			__function_name, item->host.host, conn, item->key_orig);

	if (SUCCEED == (ret = zbx_tcp_connect(&s, CONFIG_SOURCE_IP, conn, item->host.port, 0)))
	{
		zbx_snprintf(buffer, sizeof(buffer), "%s\n", item->key);
		zabbix_log(LOG_LEVEL_DEBUG, "Sending [%s]", buffer);

		/* send requests using old protocol */
		if (SUCCEED == (ret = zbx_tcp_send_raw(&s, buffer)))
			ret = zbx_tcp_recv_ext(&s, &buf, ZBX_TCP_READ_UNTIL_CLOSE, 0);
	}

	if (SUCCEED == ret)
	{
		zbx_rtrim(buf, " \r\n");
		zbx_ltrim(buf, " ");

		zabbix_log(LOG_LEVEL_DEBUG, "get value from agent result: '%s'", buf);

		if (0 == strcmp(buf, "ZBX_NOTSUPPORTED"))
		{
			zbx_snprintf(buffer, sizeof(buffer), "Not supported by Zabbix Agent");
			SET_MSG_RESULT(result, strdup(buffer));
			ret = NOTSUPPORTED;
		}
		else if (0 == strcmp(buf, "ZBX_ERROR"))
		{
			zbx_snprintf(buffer, sizeof(buffer), "Zabbix Agent non-critical error");
			SET_MSG_RESULT(result, strdup(buffer));
			ret = AGENT_ERROR;
		}
		else if ('\0' == *buf)	/* this section should be improved */
		{
			zbx_snprintf(buffer, sizeof(buffer), "Got empty string from [%s]."
					" Assuming that agent dropped connection because of access permissions",
					conn);
			SET_MSG_RESULT(result, strdup(buffer));
			ret = NETWORK_ERROR;
		}
		else if (SUCCEED != set_result_type(result, item->value_type, item->data_type, buf))
			ret = NOTSUPPORTED;
	}
	else
	{
		zbx_snprintf(buffer, sizeof(buffer), "Get value from agent failed: %s",
				zbx_tcp_strerror());
		SET_MSG_RESULT(result, strdup(buffer));
		ret = NETWORK_ERROR;
	}
	zbx_tcp_close(&s);

	return ret;
}
