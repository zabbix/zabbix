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

#ifndef ZABBIX_CEP_RULE_OP_EVENT_H
#define ZABBIX_CEP_RULE_OP_EVENT_H

#include "cep_rule.h"
#include "zbx_cep.h"
#include "zbxcacheconfig.h"

void	cep_event_execute_ops(const zbx_cep_rule_t **matched_rules, int matched_rules_num, int execute_when,
		zbx_cep_event_context_t *ctx, zbx_cep_event_t **event);

void	cep_rule_event_execute_ops(const zbx_cep_rule_t *rule, int execute_when, zbx_cep_event_context_t *ctx,
		zbx_cep_event_t **event);

void	cep_rule_event_handle_execute_ops(const zbx_cep_rule_t *rule, zbx_cep_event_handle_t hevent, int execute_when,
		zbx_cep_event_context_t *ctx, zbx_vector_mw_task_ptr_t *tasks);

#endif

