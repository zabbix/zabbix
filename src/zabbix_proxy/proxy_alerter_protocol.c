/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
#include "../zabbix_server/alerter/alerter_protocol.h"

zbx_uint32_t	zbx_alerter_serialize_alert_send(unsigned char **data, zbx_uint64_t mediatypeid, unsigned char type,
		const char *smtp_server, const char *smtp_helo, const char *smtp_email, const char *exec_path,
		const char *gsm_modem, const char *username, const char *passwd, unsigned short smtp_port,
		unsigned char smtp_security, unsigned char smtp_verify_peer, unsigned char smtp_verify_host,
		unsigned char smtp_authentication, const char *exec_params, int maxsessions, int maxattempts,
		const char *attempt_interval, unsigned char content_type, const char *script, const char *timeout,
		const char *sendto, const char *subject, const char *message, const char *params)
{
	ZBX_UNUSED(data);
	ZBX_UNUSED(mediatypeid);
	ZBX_UNUSED(type);
	ZBX_UNUSED(smtp_server);
	ZBX_UNUSED(smtp_helo);
	ZBX_UNUSED(smtp_email);
	ZBX_UNUSED(exec_path);
	ZBX_UNUSED(gsm_modem);
	ZBX_UNUSED(username);
	ZBX_UNUSED(passwd);
	ZBX_UNUSED(smtp_port);
	ZBX_UNUSED(smtp_security);
	ZBX_UNUSED(smtp_verify_peer);
	ZBX_UNUSED(smtp_verify_host);
	ZBX_UNUSED(smtp_authentication);
	ZBX_UNUSED(exec_params);
	ZBX_UNUSED(maxsessions);
	ZBX_UNUSED(maxattempts);
	ZBX_UNUSED(attempt_interval);
	ZBX_UNUSED(content_type);
	ZBX_UNUSED(script);
	ZBX_UNUSED(timeout);
	ZBX_UNUSED(sendto);
	ZBX_UNUSED(subject);
	ZBX_UNUSED(message);
	ZBX_UNUSED(params);

	THIS_SHOULD_NEVER_HAPPEN;

	return 0;
}

void	zbx_alerter_deserialize_result(const unsigned char *data, char **value, int *errcode, char **errmsg)
{
	ZBX_UNUSED(value);
	ZBX_UNUSED(data);
	ZBX_UNUSED(errcode);
	ZBX_UNUSED(errmsg);

	THIS_SHOULD_NEVER_HAPPEN;
}
