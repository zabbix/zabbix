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

#include "audit_server.h"
#include "audit/zbxaudit.h"

#include "zbxalgo.h"

void	zbx_audit_proxy_config_reload(int audit_context_mode, zbx_uint64_t proxyid, const char *name)
{
	zbx_audit_entry_t	local_audit_entry, *plocal_audit_entry = &local_audit_entry;

	RETURN_IF_AUDIT_OFF(audit_context_mode);

	local_audit_entry.id = proxyid;
	local_audit_entry.cuid = NULL;
	local_audit_entry.id_table = AUDIT_HOST_ID; /* proxies are stored in host table */

	if (NULL == zbx_hashset_search(zbx_get_audit_hashset(), &plocal_audit_entry))
	{
		zbx_audit_entry_t	*new_entry;

		new_entry = zbx_audit_entry_init(proxyid, AUDIT_HOST_ID, name, ZBX_AUDIT_ACTION_CONFIG_REFRESH,
				ZBX_AUDIT_RESOURCE_PROXY);
		zbx_hashset_insert(zbx_get_audit_hashset(), &new_entry, sizeof(new_entry));
	}
}
