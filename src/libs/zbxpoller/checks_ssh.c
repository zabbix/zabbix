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

#include "zbxcommon.h"

#if defined(HAVE_SSH2) || defined(HAVE_SSH)
#include "ssh_run.h"

#include "zbxcacheconfig.h"
#include "zbxpoller.h"
#include "zbxcomms.h"
#include "zbxsysinfo.h"
#include "zbxnum.h"
#include "zbxstr.h"

int	zbx_ssh_get_value(zbx_dc_item_t *item, const char *config_source_ip, const char *config_ssh_key_location,
		AGENT_RESULT *result)
{
	AGENT_REQUEST	request;
	int		ret = NOTSUPPORTED;
	const char	*port, *dns, *encoding, *ssh_options;

	zbx_init_agent_request(&request);

	if (SUCCEED != zbx_parse_item_key(item->key, &request))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid item key format."));
		goto out;
	}

#define SSH_RUN_KEY	"ssh.run"
	if (0 != strcmp(SSH_RUN_KEY, get_rkey(&request)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Unsupported item key for this item type."));
		goto out;
	}
#undef SSH_RUN_KEY

	if (6 < get_rparams_num(&request))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		goto out;
	}

	if (NULL != (dns = get_rparam(&request, 1)) && '\0' != *dns)
	{
		zbx_strscpy(item->interface.dns_orig, dns);
		item->interface.addr = item->interface.dns_orig;
	}

	if (NULL == item->interface.addr || '\0' == *(item->interface.addr))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL,
				"SSH checks must have IP parameter or the host interface to be specified."));
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
		item->interface.port = ZBX_DEFAULT_SSH_PORT;

	encoding = get_rparam(&request, 3);
	ssh_options = get_rparam(&request, 4);

	ret = ssh_run(item, result, ZBX_NULL2EMPTY_STR(encoding), ZBX_NULL2EMPTY_STR(ssh_options), item->timeout,
			config_source_ip, config_ssh_key_location, get_rparam(&request, 5));
out:
	zbx_free_agent_request(&request);

	return ret;
}
#endif	/* defined(HAVE_SSH2) || defined(HAVE_SSH) */
