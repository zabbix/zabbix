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

#ifndef ZABBIX_OPERATIONS_H
#define ZABBIX_OPERATIONS_H

#include "zbxalgo.h"
#include "zbxdbhigh.h"
#include "zbxcacheconfig.h"

void	op_template_add(const zbx_db_event *event, zbx_config_t *cfg, zbx_vector_uint64_t *lnk_templateids);
void	op_template_del(const zbx_db_event *event, zbx_vector_uint64_t *del_templateids);
void	op_groups_add(const zbx_db_event *event,  zbx_config_t *cfg, zbx_vector_uint64_t *groupids);
void	op_groups_del(const zbx_db_event *event, zbx_vector_uint64_t *groupids);
void	op_host_add(const zbx_db_event *event, zbx_config_t *cfg);
void	op_host_del(const zbx_db_event *event);
void	op_host_enable(const zbx_db_event *event, zbx_config_t *cfg);
void	op_host_disable(const zbx_db_event *event, zbx_config_t *cfg);
void	op_host_inventory_mode(const zbx_db_event *event, zbx_config_t *cfg, int inventory_mode);
void	op_add_del_tags(const zbx_db_event *event, zbx_config_t *cfg, zbx_vector_uint64_t *new_optagids,
		zbx_vector_uint64_t *del_optagids);

int	zbx_map_db_event_to_audit_context(const zbx_db_event *event);

#endif
