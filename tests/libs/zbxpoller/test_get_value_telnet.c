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

#include "test_get_value_telnet.h"
#include "zbxmocktest.h"

#include "zbxsysinfo.h"
#include "zbxpoller.h"

int	__wrap_telnet_run(zbx_dc_item_t *item, AGENT_RESULT *result, const char *encoding);

int	zbx_get_value_telnet_test_run(zbx_dc_item_t *item, const char *config_ssh_key_location, char **error)
{
	AGENT_RESULT	result;
	int		ret;

	zbx_init_agent_result(&result);
	ret = zbx_telnet_get_value(item, get_zbx_config_source_ip(), config_ssh_key_location, &result);

	if (NULL != result.msg && '\0' != *(result.msg))
	{
		*error = zbx_malloc(NULL, sizeof(char) * strlen(result.msg));
		zbx_strlcpy(*error, result.msg, strlen(result.msg) * sizeof(char));
	}

	zbx_free_agent_result(&result);

	return ret;
}

int	__wrap_telnet_run(zbx_dc_item_t *item, AGENT_RESULT *result, const char *encoding)
{
	ZBX_UNUSED(item);
	ZBX_UNUSED(result);
	ZBX_UNUSED(encoding);

	return SYSINFO_RET_OK;
}
