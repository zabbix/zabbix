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

#ifndef ZABBIX_LLD_AUDIT_H
#define ZABBIX_LLD_AUDIT_H

#include "lld.h"

void	zbx_audit_item_update_json_add_lld_data(zbx_uint64_t itemid, const zbx_lld_item_full_t *item,
		const zbx_lld_item_prototype_t *item_prototype, zbx_uint64_t hostid);

#endif
