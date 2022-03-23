/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

#include "zbxlld.h"

#include "module.h"

void	zbx_lld_process_agent_result(zbx_uint64_t itemid, zbx_uint64_t hostid, AGENT_RESULT *result, zbx_timespec_t *ts, char *error)
{
	ZBX_UNUSED(itemid);
	ZBX_UNUSED(hostid);
	ZBX_UNUSED(result);
	ZBX_UNUSED(ts);
	ZBX_UNUSED(error);
}
