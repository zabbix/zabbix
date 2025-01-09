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

#include "zbxpoller.h"

#include "zbxsysinfo.h"
#include "telnet_run.h"
#include "zbxcacheconfig.h"
#include "zbxcomms.h"
#include "zbxnum.h"
#include "zbxstr.h"

int	zbx_telnet_get_value(zbx_dc_item_t *item, const char *config_source_ip, const char *config_ssh_key_location,
		AGENT_RESULT *result)
{
	AGENT_REQUEST	request;
	int		ret = NOTSUPPORTED;
	const char	*port, *encoding, *dns;

	ZBX_UNUSED(config_ssh_key_location);

	zbx_init_agent_request(&request);

	if (SUCCEED != zbx_parse_item_key(item->key, &request))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid item key format."));
		goto out;
	}

#define TELNET_RUN_KEY	"telnet.run"
	if (0 != strcmp(TELNET_RUN_KEY, get_rkey(&request)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Unsupported item key for this item type."));
		goto out;
	}
#undef TELNET_RUN_KEY

	if (4 < get_rparams_num(&request))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		goto out;
	}

	if (NULL != (dns = get_rparam(&request, 1)) && '\0' != *dns)
	{
		zbx_strscpy(item->interface.dns_orig, dns);
		item->interface.addr = item->interface.dns_orig;
	}

	if (NULL == item->interface.addr ||'\0' == *(item->interface.addr))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL,
				"Telnet checks must have IP parameter or the host interface to be specified."));
		goto out;
	}

	if (NULL != (port = get_rparam(&request, 2)) && '\0' != *port)
	{
		if (FAIL == zbx_is_ushort(port, &item->interface.port))
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid third parameter."));
			goto out;
		}
	}
	else
		item->interface.port = ZBX_DEFAULT_TELNET_PORT;

	encoding = get_rparam(&request, 3);

	ret = telnet_run(item, result, ZBX_NULL2EMPTY_STR(encoding), item->timeout, config_source_ip);
out:
	zbx_free_agent_request(&request);

	return ret;
}
