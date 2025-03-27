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

#ifndef ZABBIX_DBUPGRADE_COMPONENT_H
#define ZABBIX_DBUPGRADE_COMPONENT_H

#include "zbxtypes.h"
#include "zbxdb.h"

void	dbupgrade_copy_template_host_prototypes(zbx_uint64_t hostid, int flags, zbx_vector_uint64_t *templateids,
		zbx_db_insert_t *db_insert);

#endif
