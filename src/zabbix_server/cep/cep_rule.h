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

#include "zbxcommon.h"
#include "zbxdbhigh.h"

#include "zbxcep.h"
#include "zbxcacheconfig.h"
#include "zbxdbhigh.h"

typedef struct
{
	zbx_db_event		*db_event;
	zbx_cep_event_t		*event;
	zbx_cep_event_handle_t	hevent;

	zbx_vector_str_t	hosts;
	zbx_vector_str_t	groups;
}
zbx_cep_event_context_t;

#define CEP_RESULT_UPDATE_EVENT		0x01
#define CEP_RESULT_UPDATE_DB_EVENT	0x02
#define CEP_RESULT_CLOSE_EVENT		0x04
#define CEP_RESULT_SUPPRESS_EVENT	0x08
#define CEP_RESULT_COPY_EVENT		0x10
#define CEP_RESULT_SET_CAUSE_SYMPTOM	0x20

#define CEP_RESULT_TASK_MASK	(CEP_RESULT_CLOSE_EVENT | CEP_RESULT_SUPPRESS_EVENT | CEP_RESULT_COPY_EVENT | \
				CEP_RESULT_SET_CAUSE_SYMPTOM)
typedef struct
{
	zbx_db_event			*db_event;
	zbx_cep_event_t			*event;

	zbx_db_event			*close_db_event;
	zbx_uint64_t			close_ruleid;
	zbx_uint32_t			update_flags;

	zbx_vector_cep_event_handle_t	to_copy;
}
zbx_cep_result_t;

void	cep_event_context_clear(zbx_cep_event_context_t *ctx);
void	cep_result_clear_borrowed(zbx_cep_result_t *result);
void	cep_result_clear(zbx_cep_result_t *result);

int	cep_event_match_rules(zbx_cep_config_handle_t handle, const zbx_cep_rule_t ***matched_rules,
		int *matched_rules_num, zbx_cep_event_context_t *ctx);
void	cep_event_execute_ops(const zbx_cep_rule_t **matched_rules, int matched_rules_num, int execute_when,
		zbx_cep_event_context_t *ctx, zbx_cep_event_t **event);
void	cep_db_event_execute_ops(const zbx_cep_rule_t **matched_rules, int matched_rules_num, int execute_when,
	zbx_cep_event_context_t *ctx, zbx_db_event *db_event);
void	cep_event_add_to_rules(zbx_cep_event_handle_t hevent, const zbx_cep_rule_t **matched_rules,
		int matched_rules_num);

void	cep_rule_event_execute_ops(const zbx_cep_rule_t *rule, int execute_when, zbx_cep_event_context_t *ctx,
	zbx_cep_event_t **event);

#endif

