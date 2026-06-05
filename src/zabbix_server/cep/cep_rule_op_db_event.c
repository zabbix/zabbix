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

#include "cep_rule_op_db_event.h"
#include "cep_rule_op_db.h"
#include "cep_rule.h"
#include "zbxalgo.h"
#include "zbxcacheconfig.h"
#include "zbxcep.h"
#include "zbxcommon.h"
#include "zbxmw.h"
#include "zbxdbhigh.h"

static void	cep_operation_db_event_execute_set_name(const zbx_cep_operation_t *op, zbx_db_event *db_event)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() operationid:" ZBX_FS_UI64, __func__, op->operationid);

	db_event->name = zbx_strdup(db_event->name, op->args.set_name.name);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	cep_operation_db_event_update_severity(zbx_cep_event_context_t *ctx, zbx_db_event *db_event)
{
	zbx_cep_event_t	*event;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (NULL != (event = cep_event_context_acquire_event(ctx)))
		db_event->severity = event->severity;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	cep_operation_db_event_execute_suppress_event(zbx_uint64_t ruleid, const zbx_cep_operation_t *op,
		zbx_db_event *db_event)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() operationid:" ZBX_FS_UI64, __func__, op->operationid);

	if (time(NULL) < (time_t)op->args.suppress.until)
	{
		zbx_db_event_suppress_t	suppress_local = {.cep_ruleid = ruleid, .until = op->args.suppress.until};

		if (NULL == db_event->suppress)
			db_event->suppress = zbx_create_event_suppress(1);

		zbx_vector_db_event_suppress_append(db_event->suppress, suppress_local);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static int	cep_db_event_validate_tag(zbx_db_event *event, const char *tag, const char *value, int *match_index)
{
	if (NULL != match_index)
		*match_index = FAIL;

	for (int i = 0; i < event->tags.values_num; i++)
	{
		if (0 == strcmp(event->tags.values[i]->tag, tag))
		{
			if (0 == strcmp(event->tags.values[i]->value, value))
				return FAIL;

			if (NULL != match_index && FAIL == *match_index)
				*match_index = i;
		}
	}

	return SUCCEED;
}

static int	cep_db_event_find_tag(zbx_db_event *event, const char *tag)
{
	for (int i = 0; i < event->tags.values_num; i++)
	{
		if (0 == strcmp(event->tags.values[i]->tag, tag))
			return i;
	}

	return FAIL;
}

static void	cep_operation_db_event_add_tag(const zbx_cep_operation_t *op, zbx_db_event *db_event)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() operationid:" ZBX_FS_UI64, __func__, op->operationid);

	if (SUCCEED == cep_db_event_validate_tag(db_event, op->args.add_tag.tag, op->args.add_tag.value, NULL))
	{
		zbx_tag_t	*tag = (zbx_tag_t *)zbx_malloc(NULL, sizeof(zbx_tag_t));

		tag->tag = zbx_strdup(NULL, op->args.add_tag.tag);
		tag->value = zbx_strdup(NULL, op->args.add_tag.value);
		zbx_vector_tags_ptr_append(&db_event->tags, tag);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	cep_operation_db_event_set_tag(const zbx_cep_operation_t *op, zbx_db_event *db_event)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() operationid:" ZBX_FS_UI64, __func__, op->operationid);

	int	index;

	if (SUCCEED == cep_db_event_validate_tag(db_event, op->args.set_tag.tag, op->args.set_tag.value, &index))
	{
		if (FAIL == index)
		{
			zbx_tag_t	*tag = (zbx_tag_t *)zbx_malloc(NULL, sizeof(zbx_tag_t));

			tag->tag = zbx_strdup(NULL, op->args.set_tag.tag);
			tag->value = zbx_strdup(NULL, op->args.set_tag.value);
			zbx_vector_tags_ptr_append(&db_event->tags, tag);
		}
		else
		{
			zbx_tag_t	*tag = db_event->tags.values[index];

			tag->value = zbx_strdup(tag->value, op->args.set_tag.value);
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	cep_operation_db_event_set_tag_value(const zbx_cep_operation_t *op, zbx_db_event *db_event)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() operationid:" ZBX_FS_UI64, __func__, op->operationid);

	int	index;

	if (SUCCEED == cep_db_event_validate_tag(db_event, op->args.set_tag_value.tag, op->args.set_tag_value.value,
			&index))
	{
		if (FAIL != index)
		{
			zbx_tag_t	*tag = db_event->tags.values[index];

			tag->value = zbx_strdup(tag->value, op->args.set_tag_value.value);
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	cep_operation_db_event_increase_tag_value(const zbx_cep_operation_t *op, zbx_db_event *db_event)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() operationid:" ZBX_FS_UI64, __func__, op->operationid);

	int	index;

	if (FAIL != (index = cep_db_event_find_tag(db_event, op->args.increase_tag_value.tag)))
	{
		zbx_tag_t	*tag = db_event->tags.values[index];
		char		*value;

		if (NULL != (value = cep_tag_value_shift(tag->value, 1)))
		{
			zbx_free(tag->value);
			tag->value = value;
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	cep_operation_db_event_decrease_tag_value(const zbx_cep_operation_t *op, zbx_db_event *db_event)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() operationid:" ZBX_FS_UI64, __func__, op->operationid);

	int	index;

	if (FAIL != (index = cep_db_event_find_tag(db_event, op->args.decrease_tag_value.tag)))
	{
		zbx_tag_t	*tag = db_event->tags.values[index];
		char		*value;

		if (NULL != (value = cep_tag_value_shift(tag->value, -1)))
		{
			zbx_free(tag->value);
			tag->value = value;
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	cep_operation_db_event_rename_tag(const zbx_cep_operation_t *op, zbx_db_event *db_event)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() operationid:" ZBX_FS_UI64, __func__, op->operationid);

	int	index;

	if (FAIL != (index = cep_db_event_find_tag(db_event, op->args.rename_tag.old_tag)))
	{
		zbx_tag_t	*tag = db_event->tags.values[index];

		if (SUCCEED == cep_db_event_validate_tag(db_event, op->args.rename_tag.new_tag, tag->value, NULL))
			tag->tag = zbx_strdup(tag->tag, op->args.rename_tag.new_tag);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	cep_operation_db_event_remove_tag(const zbx_cep_operation_t *op, zbx_db_event *db_event)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() operationid:" ZBX_FS_UI64, __func__, op->operationid);

	int	index;

	if (FAIL != (index = cep_db_event_find_tag(db_event, op->args.remove_tag.tag)))
	{
		zbx_tag_t	*tag = db_event->tags.values[index];

		zbx_free(tag->tag);
		zbx_free(tag->value);
		zbx_free(tag);

		zbx_vector_tags_ptr_remove_noorder(&db_event->tags, index);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	cep_operation_db_event_execute(const zbx_cep_operation_t *op, int execute_when,
		zbx_uint64_t ruleid, zbx_cep_event_context_t *ctx, zbx_db_event *db_event,
		zbx_vector_mw_task_ptr_t *tasks)
{
	if (op->execute_when != execute_when)
		return;

	if (SUCCEED != cep_operation_match_event(op, ctx))
		return;

	switch (op->type)
	{
		case ZBX_CEP_OP_SET_NAME:
			if (0 != (CEP_OP_SET_NAME_MASK & CEP_FLAG(execute_when)))
				cep_operation_db_event_execute_set_name(op, db_event);
			break;
		case ZBX_CEP_OP_CLOSE:
			if (0 != (CEP_OP_CLOSE_MASK & CEP_FLAG(execute_when)))
				cep_operation_db_execute_close_event(ruleid, ctx, tasks);
			break;
		case ZBX_CEP_OP_SET_SEVERITY:
			if (0 != (CEP_OP_SET_SEVERITY_MASK & CEP_FLAG(execute_when)))
				cep_operation_db_event_update_severity(ctx, db_event);
			break;
		case ZBX_CEP_OP_INCREASE_SEVERITY:
			if (0 != (CEP_OP_INCREASE_SEVERITY_MASK & CEP_FLAG(execute_when)))
				cep_operation_db_event_update_severity(ctx, db_event);
			break;
		case ZBX_CEP_OP_DECREASE_SEVERITY:
			if (0 != (CEP_OP_DECREASE_SEVERITY_MASK & CEP_FLAG(execute_when)))
				cep_operation_db_event_update_severity(ctx, db_event);
			break;
		case ZBX_CEP_OP_SUPPRESS:
			if (0 != (CEP_OP_SUPPRESS_MASK & CEP_FLAG(execute_when)))
				cep_operation_db_event_execute_suppress_event(ruleid, op, db_event);
			break;
			case ZBX_CEP_OP_ADD_TAG:
			if (0 != (CEP_OP_ADD_TAG_MASK & CEP_FLAG(execute_when)))
				cep_operation_db_event_add_tag(op, db_event);
			break;
		case ZBX_CEP_OP_SET_TAG:
			if (0 != (CEP_OP_SET_TAG_MASK & CEP_FLAG(execute_when)))
				cep_operation_db_event_set_tag(op, db_event);
			break;
		case ZBX_CEP_OP_SET_TAG_VALUE:
			if (0 != (CEP_OP_SET_TAG_VALUE_MASK & CEP_FLAG(execute_when)))
				cep_operation_db_event_set_tag_value(op, db_event);
			break;
		case ZBX_CEP_OP_INCREASE_TAG_VALUE:
			if (0 != (CEP_OP_INCREASE_TAG_VALUE_MASK & CEP_FLAG(execute_when)))
				cep_operation_db_event_increase_tag_value(op, db_event);
			break;
		case ZBX_CEP_OP_DECREASE_TAG_VALUE:
			if (0 != (CEP_OP_DECREASE_TAG_VALUE_MASK & CEP_FLAG(execute_when)))
				cep_operation_db_event_decrease_tag_value(op, db_event);
			break;
		case ZBX_CEP_OP_RENAME_TAG:
			if (0 != (CEP_OP_RENAME_TAG_MASK & CEP_FLAG(execute_when)))
				cep_operation_db_event_rename_tag(op, db_event);
			break;
		case ZBX_CEP_OP_REMOVE_TAG:
			if (0 != (CEP_OP_REMOVE_TAG_MASK & CEP_FLAG(execute_when)))
				cep_operation_db_event_remove_tag(op, db_event);
			break;
		case ZBX_CEP_OP_DISCARD:
		case ZBX_CEP_OP_COPY_FIRST:
		case ZBX_CEP_OP_COPY_LAST:
			/* not db_event ops are handled elsewhere */
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN_MSG("unsupported operation type %d", op->type);
	}
}

static void	cep_rule_db_event_execute_ops(const zbx_cep_rule_t *rule, int execute_when,
		zbx_cep_event_context_t *ctx, zbx_db_event *db_event, zbx_vector_mw_task_ptr_t *tasks)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() ruleid:" ZBX_FS_UI64, __func__, rule->ruleid);

	for (int i = 0; i < rule->operations.values_num; i++)
	{
		if (rule->operations.values[i].execute_when == execute_when)
		{
			cep_operation_db_event_execute(&rule->operations.values[i], execute_when,  rule->ruleid, ctx,
					db_event, tasks);
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

void	cep_db_event_execute_ops(const zbx_cep_rule_t **matched_rules, int matched_rules_num, int execute_when,
	zbx_cep_event_context_t *ctx, zbx_db_event *db_event, zbx_vector_mw_task_ptr_t *tasks)
{
	for (int i = 0; i < matched_rules_num; i++)
		cep_rule_db_event_execute_ops(matched_rules[i], execute_when, ctx, db_event, tasks);
}

