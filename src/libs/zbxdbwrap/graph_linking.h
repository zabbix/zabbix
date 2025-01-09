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

#ifndef ZABBIX_GRAPH_LINKING_H
#define ZABBIX_GRAPH_LINKING_H

#include "zbxcommon.h"
#include "zbxalgo.h"

int	DBcopy_template_graphs(zbx_uint64_t hostid, const zbx_vector_uint64_t *templateids, int audit_context_mode);

#endif
