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

#include "stats_proxy.h"

#include "zbxdbwrap.h"
#include "zbxcomms.h"
#include "zbxjson.h"
#include "zbxstr.h"

/******************************************************************************
 *                                                                            *
 * Purpose: get program type (proxy) specific internal statistics             *
 *                                                                            *
 * Parameters: json         - [OUT] json data                                 *
 *             arg          - [IN] anonymous argument provided by register    *
 *                                                                            *
 * Comments: This function is used to gather proxy specific internal          *
 *           statistics.                                                      *
 *                                                                            *
 ******************************************************************************/
void	zbx_proxy_stats_ext_get(struct zbx_json *json, const void *arg)
{
	const zbx_config_comms_args_t	*config_comms = (const zbx_config_comms_args_t *)arg;
	unsigned int			encryption;

	zbx_json_addstring(json, "name", ZBX_NULL2EMPTY_STR(config_comms->hostname), ZBX_JSON_TYPE_STRING);

	if (ZBX_PROXYMODE_PASSIVE == config_comms->proxymode)
	{
		zbx_json_addstring(json, "passive", "true", ZBX_JSON_TYPE_INT);
		encryption = config_comms->config_tls->accept_modes;
	}
	else
	{
		zbx_json_addstring(json, "passive", "false", ZBX_JSON_TYPE_INT);
		encryption = config_comms->config_tls->connect_mode;
	}

	zbx_json_addstring(json, ZBX_TCP_SEC_UNENCRYPTED_TXT,
			0 < (encryption & ZBX_TCP_SEC_UNENCRYPTED) ? "true" : "false", ZBX_JSON_TYPE_INT);

	zbx_json_addstring(json, ZBX_TCP_SEC_TLS_PSK_TXT,
			0 < (encryption & ZBX_TCP_SEC_TLS_PSK) ? "true" : "false", ZBX_JSON_TYPE_INT);

	zbx_json_addstring(json, ZBX_TCP_SEC_TLS_CERT_TXT,
			0 < (encryption & ZBX_TCP_SEC_TLS_CERT) ? "true" : "false", ZBX_JSON_TYPE_INT);

	return;
}
