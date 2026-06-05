/*
** Copyright (C) 2001-2026 Zabbix SIA
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

#ifndef ZABBIX_CEP_RULE_OP_DB_H
#define ZABBIX_CEP_RULE_OP_DB_H

#include "cep_rule.h"
#include "zbxmw.h"

void	cep_operation_db_execute_close_event(zbx_uint64_t ruleid, zbx_cep_event_context_t *ctx,
	zbx_vector_mw_task_ptr_t *tasks);


#endif

