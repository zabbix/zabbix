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

#ifndef ZABBIX_CEP_RULE_H
#define ZABBIX_CEP_RULE_H

#define CEP_ON_EVENT_OCCURRED	1

#include "zbxcep.h"
#include "zbxcacheconfig.h"

int	zbx_cep_event_match_rules(const zbx_cep_event_t *event, zbx_cep_config_handle_t handle,
		const zbx_cep_rule_t ***matched_rules, int *matched_rules_num);
void	zbx_cep_event_execute_ops(zbx_cep_event_t *event, const zbx_vector_cep_rule_ptr_t *matched_rules,
		int op_condition);
void	zbx_cep_event_add_to_rules(zbx_cep_event_t *event, const zbx_vector_cep_rule_ptr_t *matched_rules);

#endif

