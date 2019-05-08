/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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

zbx_uint32_t	zbx_alerter_serialize_alert_send(unsigned char **data, zbx_uint64_t mediatypeid, const char *sendto,
		const char *subject, const char *message)
{
	ZBX_UNUSED(data);
	ZBX_UNUSED(mediatypeid);
	ZBX_UNUSED(sendto);
	ZBX_UNUSED(subject);
	ZBX_UNUSED(message);

	THIS_SHOULD_NEVER_HAPPEN;

	return 0;
}

void	zbx_alerter_deserialize_result(const unsigned char *data, int *errcode, char **errmsg)
{
	ZBX_UNUSED(data);
	ZBX_UNUSED(errcode);
	ZBX_UNUSED(errmsg);

	THIS_SHOULD_NEVER_HAPPEN;
}
