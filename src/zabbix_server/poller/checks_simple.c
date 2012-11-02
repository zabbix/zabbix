/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "checks_simple.h"
#include "simple.h"
#include "log.h"

int	get_value_simple(DC_ITEM *item, AGENT_RESULT *result)
{
	const char	*__function_name = "get_value_simple";

	char		key[32], params[MAX_STRING_LEN];
	int		ret = NOTSUPPORTED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() key_orig:'%s' addr:'%s'",
			__function_name, item->key_orig, item->interface.addr);

	if (ZBX_COMMAND_ERROR == parse_command(item->key, key, sizeof(key), params, sizeof(params)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Key is badly formatted"));
		goto notsupported;
	}

	if (0 == strcmp(key, "net.tcp.service"))
	{
		if (SYSINFO_RET_OK == check_service(params, item->interface.addr, result, 0))
			ret = SUCCEED;
	}
	else if (0 == strcmp(key, "net.tcp.service.perf"))
	{
		if (SYSINFO_RET_OK == check_service(params, item->interface.addr, result, 1))
			ret = SUCCEED;
	}

	if (NOTSUPPORTED == ret && !ISSET_MSG(result))
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Simple check is not supported"));
notsupported:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}
