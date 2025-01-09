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

#ifndef ZABBIX_SERVICE_ACTIONS_H
#define ZABBIX_SERVICE_ACTIONS_H

#include "service_manager_impl.h"

#include "zbxalgo.h"

void	service_update_process_actions(const zbx_service_update_t *update, zbx_hashset_t *actions,
		zbx_vector_uint64_t *actionids);

#endif
