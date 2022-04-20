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

#include "zbxalgo.h"
#include "audit.h"
#include "audit/zbxaudit_proxy.h"

void	zbx_audit_proxy_config_reload(zbx_uint64_t proxy_hostid, const char *name)
{
	zbx_audit_entry_t	local_audit_entry, *plocal_audit_entry = &local_audit_entry;

	RETURN_IF_AUDIT_OFF();

	local_audit_entry.id = proxy_hostid;
	local_audit_entry.cuid = NULL;
	local_audit_entry.id_table = AUDIT_HOST_ID; /* proxies are stored in host table */

	if (NULL == zbx_hashset_search(zbx_get_audit_hashset(), &plocal_audit_entry))
	{
		zbx_audit_entry_t	*new_entry;

		new_entry = zbx_audit_entry_init(proxy_hostid, AUDIT_HOST_ID, name, AUDIT_ACTION_CONFIG_REFRESH,
				AUDIT_RESOURCE_PROXY);
		zbx_hashset_insert(zbx_get_audit_hashset(), &new_entry, sizeof(new_entry));
	}
}
