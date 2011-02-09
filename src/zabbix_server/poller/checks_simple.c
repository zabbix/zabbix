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

#include "checks_simple.h"
#include "log.h"

int	get_value_simple(DC_ITEM *item, AGENT_RESULT *result)
{
	const char	*__function_name = "get_value_simple";

	char		*p, *error = NULL;
	char		check[MAX_STRING_LEN];
	char		service[MAX_STRING_LEN];
	char		port[8];
	char		net_tcp_service[MAX_STRING_LEN];
	const char	*conn;
	int		ret = SUCCEED;

	/* assumption: host name does not contain '_perf' */

	zabbix_log(LOG_LEVEL_DEBUG, "In %s(): key_orig [%s]", __function_name, item->key_orig);

	init_result(result);

	conn = item->host.useip == 1 ? item->host.ip : item->host.dns;

	*service = '\0';
	*port = '\0';

	if (1 == num_param(item->key))
	{
		if (0 != get_param(item->key, 1, service, MAX_STRING_LEN))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			ret = NOTSUPPORTED;
		}
		else if (0 == strcmp(service, "tcp") || 0 == strcmp(service, "tcp_perf"))
		{
			error = zbx_dsprintf(error, "Simple check [%s] requires a mandatory 'port' parameter", service);
			ret = NOTSUPPORTED;
		}
	}
	else if (2 == num_param(item->key))
	{
		if (0 != get_param(item->key, 1, service, MAX_STRING_LEN))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			ret = NOTSUPPORTED;
		}
		else if (0 != get_param(item->key, 2, port, sizeof(port)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			ret = NOTSUPPORTED;
		}
		else if (SUCCEED != is_ushort(port, NULL))
		{
			error = zbx_strdup(error, "Incorrect port number");
			ret = NOTSUPPORTED;
		}
	}
	else
	{
		error = zbx_strdup(error, "Too many parameters");
		ret = NOTSUPPORTED;
	}

	if (SUCCEED == ret)
	{
		if (NULL != (p = strstr(service, "_perf")))
		{
			*p = '\0';
			strscpy(net_tcp_service, "net.tcp.service.perf");
		}
		else
			strscpy(net_tcp_service, "net.tcp.service");

		if ('\0' == *port)
			zbx_snprintf(check, sizeof(check), "%s[%s,%s]", net_tcp_service, service, conn);
		else
			zbx_snprintf(check, sizeof(check), "%s[%s,%s,%s]", net_tcp_service, service, conn, port);

		zabbix_log(LOG_LEVEL_DEBUG, "Transformed [%s] into [%s]", item->key, check);
	}

	if (SUCCEED == ret && SUCCEED != process(check, 0, result))
		ret = NOTSUPPORTED;

	if (NOTSUPPORTED == ret && NULL == error)
		error = zbx_strdup(error, "Simple check is not supported");

	if (NOTSUPPORTED == ret)
		SET_MSG_RESULT(result, error);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}
