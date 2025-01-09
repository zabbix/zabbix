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

#ifndef ZABBIX_SNMPTRAPPER_H
#define ZABBIX_SNMPTRAPPER_H

#include "zbxthreads.h"

typedef struct
{
	const char	*config_snmptrap_file;
	const char	*config_ha_node_name;
}
zbx_thread_snmptrapper_args;

ZBX_THREAD_ENTRY(zbx_snmptrapper_thread, args);

#endif
