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

#include "cep_event.h"
#include "zbx_cep.h"
#include "zbxcacheconfig.h"
#include "zbxdbhigh.h"
#include "zbxmw.h"

char	*cep_tag_value_shift(const char *value, int shift);

#define CEP_FLAG(x)  (__UINT32_C(1) << (x))

#define CEP_OP_SET_NAME_MASK		(CEP_FLAG(ZBX_CEP_ON_EVENT_OCCURRED) | CEP_FLAG(ZBX_CEP_ON_EVENT_EVICTED) | \
					CEP_FLAG(ZBX_CEP_ON_WINDOW_CLOSED))
#define CEP_OP_CLOSE_MASK		(CEP_FLAG(ZBX_CEP_ON_EVENT_OCCURRED) | CEP_FLAG(ZBX_CEP_ON_EVENT_EVICTED) | \
					CEP_FLAG(ZBX_CEP_ON_WINDOW_CLOSED))
#define CEP_OP_SET_SEVERITY_MASK	(CEP_FLAG(ZBX_CEP_ON_EVENT_OCCURRED) | CEP_FLAG(ZBX_CEP_ON_EVENT_EVICTED) | \
					CEP_FLAG(ZBX_CEP_ON_WINDOW_CLOSED))
#define CEP_OP_INCREASE_SEVERITY_MASK	(CEP_FLAG(ZBX_CEP_ON_EVENT_OCCURRED) | CEP_FLAG(ZBX_CEP_ON_EVENT_EVICTED) | \
					CEP_FLAG(ZBX_CEP_ON_WINDOW_CLOSED))
#define CEP_OP_DECREASE_SEVERITY_MASK	(CEP_FLAG(ZBX_CEP_ON_EVENT_OCCURRED) | CEP_FLAG(ZBX_CEP_ON_EVENT_EVICTED) | \
					CEP_FLAG(ZBX_CEP_ON_WINDOW_CLOSED))
#define CEP_OP_SUPPRESS_MASK		(CEP_FLAG(ZBX_CEP_ON_EVENT_OCCURRED) | CEP_FLAG(ZBX_CEP_ON_EVENT_EVICTED) | \
					CEP_FLAG(ZBX_CEP_ON_WINDOW_CLOSED))
#define CEP_OP_COPY_MASK		(CEP_FLAG(ZBX_CEP_ON_EVENT_EVICTED)  | CEP_FLAG(ZBX_CEP_ON_WINDOW_CLOSED) | \
					CEP_FLAG(ZBX_CEP_ON_PATTERN_MATCH))
#define CEP_OP_ADD_TAG_MASK		(CEP_FLAG(ZBX_CEP_ON_EVENT_OCCURRED) | CEP_FLAG(ZBX_CEP_ON_EVENT_EVICTED) | \
					CEP_FLAG(ZBX_CEP_ON_WINDOW_CLOSED))
#define CEP_OP_SET_TAG_MASK		(CEP_FLAG(ZBX_CEP_ON_EVENT_OCCURRED) | CEP_FLAG(ZBX_CEP_ON_EVENT_EVICTED) | \
					CEP_FLAG(ZBX_CEP_ON_WINDOW_CLOSED))
#define CEP_OP_SET_TAG_VALUE_MASK	(CEP_FLAG(ZBX_CEP_ON_EVENT_OCCURRED) | CEP_FLAG(ZBX_CEP_ON_EVENT_EVICTED) | \
					CEP_FLAG(ZBX_CEP_ON_WINDOW_CLOSED))
#define CEP_OP_INCREASE_TAG_VALUE_MASK	(CEP_FLAG(ZBX_CEP_ON_EVENT_OCCURRED) | CEP_FLAG(ZBX_CEP_ON_EVENT_EVICTED) | \
					CEP_FLAG(ZBX_CEP_ON_WINDOW_CLOSED))
#define CEP_OP_DECREASE_TAG_VALUE_MASK	(CEP_FLAG(ZBX_CEP_ON_EVENT_OCCURRED) | CEP_FLAG(ZBX_CEP_ON_EVENT_EVICTED) | \
					CEP_FLAG(ZBX_CEP_ON_WINDOW_CLOSED))
#define CEP_OP_RENAME_TAG_MASK		(CEP_FLAG(ZBX_CEP_ON_EVENT_OCCURRED) | CEP_FLAG(ZBX_CEP_ON_EVENT_EVICTED) | \
					CEP_FLAG(ZBX_CEP_ON_WINDOW_CLOSED))
#define CEP_OP_REMOVE_TAG_MASK		(CEP_FLAG(ZBX_CEP_ON_EVENT_OCCURRED) | CEP_FLAG(ZBX_CEP_ON_EVENT_EVICTED) | \
					CEP_FLAG(ZBX_CEP_ON_WINDOW_CLOSED))


int	cep_operation_match_event(const zbx_cep_operation_t *op, zbx_cep_event_context_t *ctx);

int	cep_event_match_rules(zbx_cep_config_handle_t handle, const zbx_cep_rule_t ***matched_rules,
		int *matched_rules_num, zbx_cep_event_context_t *ctx);

void	cep_event_add_to_rules(zbx_cep_event_handle_t hevent, zbx_cep_event_context_t *ctx,
		const zbx_cep_rule_t **matched_rules, int matched_rules_num, zbx_vector_mw_task_ptr_t *tasks);

#endif

