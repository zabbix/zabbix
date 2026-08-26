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

#ifndef ZABBIX_CEP_RULE_OPERATION_H
#define ZABBIX_CEP_RULE_OPERATION_H

#include "cep_event.h"
#include "zbx_cep.h"
#include "zbxmw.h"
#include "zbxcacheconfig.h"
#include "zbxjson.h"
#include "zbxtypes.h"

typedef struct
{
	struct zbx_json	json;
}
zbx_cep_acknowledge_t;

void	cep_acknowledge_clear(zbx_cep_acknowledge_t *ack);

void	cep_acknowledge_update_tag(zbx_cep_acknowledge_t *oplog, int op, const char *old_tag,
		const char *old_value, const char *new_tag, const char *new_value);
void	cep_acknowledge_set_cause(zbx_cep_acknowledge_t *oplog, zbx_uint64_t cause_eventid);

void	cep_event_add_to_rules(zbx_cep_event_context_t *ctx, const zbx_cep_rule_t **matched_rules,
		int matched_rules_num, zbx_vector_mw_task_ptr_t *tasks);

zbx_uint64_t	cep_rule_event_execute_ops(const zbx_cep_rule_t *rule, int execute_when, zbx_cep_event_context_t *ctx,
		zbx_cep_event_t **event, zbx_vector_mw_task_ptr_t *tasks);
zbx_uint64_t	cep_rule_event_context_execute_ops(const zbx_cep_rule_t *rule, zbx_cep_event_context_t *ctx,
		int execute_when, zbx_vector_mw_task_ptr_t *tasks);

#endif

