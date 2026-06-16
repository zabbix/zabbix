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

#include "cep_rule_op_event.h"
#include "cep_api.h"
#include "cep_rule.h"
#include "cep.h"
#include "cep_task.h"
#include "zbx_cep.h"
#include "zbx_trigger_constants.h"
#include "zbxalgo.h"
#include "zbxcacheconfig.h"
#include "zbxcommon.h"
#include "zbxdbhigh.h"

static void	cep_operation_event_execute_set_name(const zbx_cep_operation_t *op, zbx_cep_event_context_t *ctx,
		zbx_cep_event_t **event)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() operationid:" ZBX_FS_UI64, __func__, op->operationid);

	if (NULL == *event)
		*event = cep_event_context_acquire_mutable_event(ctx);

	if (NULL != *event)
		(*event)->name = zbx_strdup((*event)->name, op->args.set_name.name);

	ctx->sync_flags |= CEP_SYNC_EVENT_NAME;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	cep_operation_event_execute_set_severity(const zbx_cep_operation_t *op, zbx_cep_event_context_t *ctx,
		zbx_cep_event_t **event)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() operationid:" ZBX_FS_UI64, __func__, op->operationid);

	if (NULL == *event)
		*event = cep_event_context_acquire_mutable_event(ctx);

	if (NULL != *event)
		(*event)->severity = op->args.set_severity.level;

	ctx->sync_flags |= CEP_SYNC_EVENT_SEVERITY;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	cep_operation_event_execute_increase_severity(const zbx_cep_operation_t *op,
		zbx_cep_event_context_t *ctx, zbx_cep_event_t **event)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() operationid:" ZBX_FS_UI64, __func__, op->operationid);

	if (NULL == *event)
		*event = cep_event_context_acquire_mutable_event(ctx);

	if (NULL != *event)
	{
		if (TRIGGER_SEVERITY_DISASTER == (*event)->severity)
			goto out;

		(*event)->severity++;
	}

	ctx->sync_flags |= CEP_SYNC_EVENT_SEVERITY;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	cep_operation_event_execute_decrease_severity(const zbx_cep_operation_t *op,
		zbx_cep_event_context_t *ctx, zbx_cep_event_t **event)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() operationid:" ZBX_FS_UI64, __func__, op->operationid);

	if (NULL == *event)
		*event = cep_event_context_acquire_mutable_event(ctx);

	if (NULL != *event)
	{
		if (TRIGGER_SEVERITY_NOT_CLASSIFIED == (*event)->severity)
			goto out;

		(*event)->severity++;
	}

	ctx->sync_flags |= CEP_SYNC_EVENT_SEVERITY;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	cep_operation_event_execute_suppress_event(zbx_uint64_t ruleid, const zbx_cep_operation_t *op,
		zbx_cep_event_context_t *ctx, zbx_cep_event_t **event)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() operationid:" ZBX_FS_UI64, __func__, op->operationid);

	if (time(NULL) >= (time_t)op->args.suppress.until)
		goto out;

	if (NULL == *event)
		*event = cep_event_context_acquire_mutable_event(ctx);

	if (NULL != *event)
	{
		zbx_db_event_suppress_t	suppress_local = {.cep_ruleid = ruleid};

		cep_event_add_suppress(*event, &suppress_local, 1);
	}
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static int	cep_event_validate_tag(zbx_cep_event_t *event, const char *tag, const char *value,
		int *match_index)
{
	if (NULL != match_index)
		*match_index = FAIL;

	for (int i = 0; i < event->tags.values_num; i++)
	{
		if (0 == strcmp(event->tags.values[i].tag, tag))
		{
			if (0 == strcmp(event->tags.values[i].value, value))
				return FAIL;

			if (NULL != match_index && FAIL == *match_index)
				*match_index = i;
		}
	}

	return SUCCEED;
}

static void	cep_operation_event_add_tag(const zbx_cep_operation_t *op, zbx_cep_event_context_t *ctx,
		zbx_cep_event_t **event)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() operationid:" ZBX_FS_UI64, __func__, op->operationid);

	if (NULL == *event && NULL == (*event = cep_event_context_acquire_mutable_event(ctx)))
		goto out;

	if (SUCCEED == cep_event_validate_tag(*event, op->args.add_tag.tag, op->args.add_tag.value, NULL))
	{
		zbx_tag_t	tag_local;

		tag_local.tag = zbx_strdup(NULL, op->args.add_tag.tag);
		tag_local.value = zbx_strdup(NULL, op->args.add_tag.value);
		zbx_vector_tag_append(&(*event)->tags, tag_local);

		ctx->sync_flags |= CEP_SYNC_EVENT_TAGS;
	}
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	cep_operation_event_set_tag(const zbx_cep_operation_t *op, zbx_cep_event_context_t *ctx,
		zbx_cep_event_t **event)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() operationid:" ZBX_FS_UI64, __func__, op->operationid);

	int	index;

	if (NULL == *event && NULL == (*event = cep_event_context_acquire_mutable_event(ctx)))
		goto out;

	if (SUCCEED == cep_event_validate_tag(*event, op->args.set_tag.tag, op->args.set_tag.value, &index))
	{
		if (FAIL == index)
		{
			zbx_tag_t	tag_local;

			tag_local.tag = zbx_strdup(NULL, op->args.set_tag.tag);
			tag_local.value = zbx_strdup(NULL, op->args.set_tag.value);
			zbx_vector_tag_append(&(*event)->tags, tag_local);
		}
		else
		{
			zbx_tag_t	*tag = &(*event)->tags.values[index];

			tag->value = zbx_strdup(tag->value, op->args.set_tag.value);
		}

		ctx->sync_flags |= CEP_SYNC_EVENT_TAGS;
	}
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	cep_operation_event_set_tag_value(const zbx_cep_operation_t *op, zbx_cep_event_context_t *ctx,
		zbx_cep_event_t **event)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() operationid:" ZBX_FS_UI64, __func__, op->operationid);

	int	index;

	if (NULL == *event && NULL == (*event = cep_event_context_acquire_mutable_event(ctx)))
		goto out;

	if (SUCCEED == cep_event_validate_tag(*event, op->args.set_tag_value.tag, op->args.set_tag_value.value, &index))
	{
		if (FAIL != index)
		{
			zbx_tag_t	*tag = &(*event)->tags.values[index];

			tag->value = zbx_strdup(tag->value, op->args.set_tag_value.value);
			ctx->sync_flags |= CEP_SYNC_EVENT_TAGS;
		}
	}
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	cep_operation_event_increase_tag_value(const zbx_cep_operation_t *op, zbx_cep_event_context_t *ctx,
		zbx_cep_event_t **event)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() operationid:" ZBX_FS_UI64, __func__, op->operationid);

	int	index;

	if (NULL == *event && NULL == (*event = cep_event_context_acquire_mutable_event(ctx)))
		goto out;

	if (FAIL != (index = cep_event_find_tag(*event, op->args.increase_tag_value.tag)))
	{
		zbx_tag_t	*tag = &(*event)->tags.values[index];
		char		*value;

		if (NULL != (value = cep_tag_value_shift(tag->value, 1)))
		{
			zbx_free(tag->value);
			tag->value = value;
			ctx->sync_flags |= CEP_SYNC_EVENT_TAGS;
		}
	}
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	cep_operation_event_decrease_tag_value(const zbx_cep_operation_t *op, zbx_cep_event_context_t *ctx,
		zbx_cep_event_t **event)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() operationid:" ZBX_FS_UI64, __func__, op->operationid);

	int	index;

	if (NULL == *event && NULL == (*event = cep_event_context_acquire_mutable_event(ctx)))
		goto out;

	if (FAIL != (index = cep_event_find_tag(*event, op->args.decrease_tag_value.tag)))
	{
		zbx_tag_t	*tag = &(*event)->tags.values[index];
		char	*value;

		if (NULL != (value = cep_tag_value_shift(tag->value, -1)))
		{
			zbx_free(tag->value);
			tag->value = value;
			ctx->sync_flags |= CEP_SYNC_EVENT_TAGS;
		}
	}
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	cep_operation_event_rename_tag(const zbx_cep_operation_t *op, zbx_cep_event_context_t *ctx,
		zbx_cep_event_t **event)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() operationid:" ZBX_FS_UI64, __func__, op->operationid);

	int	index;

	if (NULL == *event && NULL == (*event = cep_event_context_acquire_mutable_event(ctx)))
		goto out;

	if (FAIL != (index = cep_event_find_tag(*event, op->args.rename_tag.old_tag)))
	{
		zbx_tag_t	*tag = &(*event)->tags.values[index];

		if (SUCCEED == cep_event_validate_tag(*event, op->args.rename_tag.new_tag, tag->value, NULL))
		{
			tag->tag = zbx_strdup(tag->tag, op->args.rename_tag.new_tag);
			ctx->sync_flags |= CEP_SYNC_EVENT_TAGS;
		}
	}
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	cep_operation_event_remove_tag(const zbx_cep_operation_t *op, zbx_cep_event_context_t *ctx,
		zbx_cep_event_t **event)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() operationid:" ZBX_FS_UI64, __func__, op->operationid);

	int	index;

	if (NULL == *event && NULL == (*event = cep_event_context_acquire_mutable_event(ctx)))
		goto out;

	if (FAIL != (index = cep_event_find_tag(*event, op->args.remove_tag.tag)))
	{
		zbx_tag_t	*tag = &(*event)->tags.values[index];

		zbx_free(tag->tag);
		zbx_free(tag->value);
		zbx_vector_tag_remove_noorder(&(*event)->tags, index);
		ctx->sync_flags |= CEP_SYNC_EVENT_TAGS;
	}
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	cep_operation_event_execute(const zbx_cep_operation_t *op, int execute_when, zbx_uint64_t ruleid,
		zbx_cep_event_context_t *ctx, zbx_cep_event_t **event)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() operationid:" ZBX_FS_UI64 " type:%d", __func__, op->operationid, op->type);

	if (SUCCEED != cep_operation_match_event(op, ctx))
		return;

	switch (op->type)
	{
		case ZBX_CEP_OP_SET_NAME:
			if (0 != (CEP_OP_SET_NAME_MASK & CEP_FLAG(execute_when)))
				cep_operation_event_execute_set_name(op, ctx, event);
			break;
		case ZBX_CEP_OP_SET_SEVERITY:
			if (0 != (CEP_OP_SET_SEVERITY_MASK & CEP_FLAG(execute_when)))
				cep_operation_event_execute_set_severity(op, ctx, event);
			break;
		case ZBX_CEP_OP_INCREASE_SEVERITY:
			if (0 != (CEP_OP_INCREASE_SEVERITY_MASK & CEP_FLAG(execute_when)))
				cep_operation_event_execute_increase_severity(op, ctx, event);
			break;
		case ZBX_CEP_OP_DECREASE_SEVERITY:
			if (0 != (CEP_OP_DECREASE_SEVERITY_MASK & CEP_FLAG(execute_when)))
				cep_operation_event_execute_decrease_severity(op, ctx, event);
			break;
		case ZBX_CEP_OP_SUPPRESS:
			if (0 != (CEP_OP_SUPPRESS_MASK & CEP_FLAG(execute_when)))
				cep_operation_event_execute_suppress_event(ruleid, op, ctx, event);
			break;
		case ZBX_CEP_OP_ADD_TAG:
			if (0 != (CEP_OP_ADD_TAG_MASK & CEP_FLAG(execute_when)))
				cep_operation_event_add_tag(op, ctx, event);
			break;
		case ZBX_CEP_OP_SET_TAG:
			if (0 != (CEP_OP_SET_TAG_MASK & CEP_FLAG(execute_when)))
				cep_operation_event_set_tag(op, ctx, event);
			break;
		case ZBX_CEP_OP_SET_TAG_VALUE:
			if (0 != (CEP_OP_SET_TAG_VALUE_MASK & CEP_FLAG(execute_when)))
				cep_operation_event_set_tag_value(op, ctx, event);
			break;
		case ZBX_CEP_OP_INCREASE_TAG_VALUE:
			if (0 != (CEP_OP_INCREASE_TAG_VALUE_MASK & CEP_FLAG(execute_when)))
				cep_operation_event_increase_tag_value(op, ctx, event);
			break;
		case ZBX_CEP_OP_DECREASE_TAG_VALUE:
			if (0 != (CEP_OP_DECREASE_TAG_VALUE_MASK & CEP_FLAG(execute_when)))
				cep_operation_event_decrease_tag_value(op, ctx, event);
			break;
		case ZBX_CEP_OP_RENAME_TAG:
			if (0 != (CEP_OP_RENAME_TAG_MASK & CEP_FLAG(execute_when)))
				cep_operation_event_rename_tag(op, ctx, event);
			break;
		case ZBX_CEP_OP_REMOVE_TAG:
			if (0 != (CEP_OP_REMOVE_TAG_MASK & CEP_FLAG(execute_when)))
				cep_operation_event_remove_tag(op, ctx, event);
			break;
		case ZBX_CEP_OP_CLOSE:
		case ZBX_CEP_OP_DISCARD:
		case ZBX_CEP_OP_COPY_FIRST:
		case ZBX_CEP_OP_COPY_LAST:
			/* non event ops are handled elsewhere */
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN_MSG("unsupported operation type %d", op->type);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

void	cep_rule_event_execute_ops(const zbx_cep_rule_t *rule, int execute_when, zbx_cep_event_context_t *ctx,
		zbx_cep_event_t **event)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() ruleid:" ZBX_FS_UI64 " operations:%d", __func__, rule->ruleid,
			rule->operations.values_num);

	for (int i = 0; i < rule->operations.values_num; i++)
	{
		if (rule->operations.values[i].execute_when == execute_when)
		{
			cep_operation_event_execute(&rule->operations.values[i], execute_when,  rule->ruleid, ctx,
					event);
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

void	cep_rule_event_handle_execute_ops(const zbx_cep_rule_t *rule, zbx_cep_event_handle_t hevent, int execute_when,
		zbx_cep_event_context_t *ctx, zbx_vector_mw_task_ptr_t *tasks)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() ruleid:" ZBX_FS_UI64, __func__, rule->ruleid);

	zbx_cep_event_t	*event = NULL;

	cep_event_context_set_handle(ctx, hevent);
	cep_rule_event_execute_ops(rule, execute_when, ctx, &event);

	if (NULL != event)
	{
		zbx_cep_t	*cep;

		cep_cache_acquire(&cep);
		cep_event_handle_set(hevent, event);
		cep_cache_release(&cep);

		zbx_cep_event_release(event);

		zbx_mw_task_t	*t = cep_create_task_sync_event(hevent, ctx->sync_flags);

		zbx_vector_mw_task_ptr_append(tasks, t);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

void	cep_event_execute_ops(const zbx_cep_rule_t **matched_rules, int matched_rules_num, int execute_when,
		zbx_cep_event_context_t *ctx, zbx_cep_event_t **event)
{
	for (int i = 0; i < matched_rules_num; i++)
		cep_rule_event_execute_ops(matched_rules[i], execute_when, ctx, event);
}

void	cep_event_add_to_rules(zbx_cep_event_handle_t hevent, zbx_cep_event_context_t *ctx,
		const zbx_cep_rule_t **matched_rules, int matched_rules_num, zbx_vector_mw_task_ptr_t *tasks)
{
	for (int i = 0; i < matched_rules_num; i++)
	{
		const zbx_cep_rule_t	*rule = matched_rules[i];

		if (NULL == rule->window)
			continue;

		switch (rule->window->type)
		{
			case ZBX_CEP_WINDOW_SIMPLE:
				cep_window_simple_process_event(rule, hevent, ctx, tasks);
				break;
			case ZBX_CEP_WINDOW_CAUSE_SYMPTOM:
			case ZBX_CEP_WINDOW_TAG_MATCH:
			case ZBX_CEP_WINDOW_PATTERN_MATCH:
				/* TODO: implement */
				break;
		}
	}
	/* TODO: implementation */
}

