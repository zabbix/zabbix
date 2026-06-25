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
#include "zabbix_server/cep/cep_event.h"
#include "zabbix_server/cep/cep_rule_op_db.h"
#include "zabbix_server/cep/cep_window.h"
#include "zbx_cep.h"
#include "zbx_trigger_constants.h"
#include "zbxalgo.h"
#include "zbxcacheconfig.h"
#include "zbxcommon.h"
#include "zbxdbhigh.h"
#include "zbxexpr.h"
#include "zbxjson.h"

static int	cep_acknowledge_is_set(zbx_cep_acknowledge_t *ack)
{
	return (0 == ack->json.buffer_size ? FAIL : SUCCEED);
}

static void	cep_acknowledge_init(zbx_cep_acknowledge_t *ack)
{
	if (SUCCEED != cep_acknowledge_is_set(ack))
	{
		zbx_json_init(&ack->json, 1024);
		zbx_json_addarray(&ack->json, "cep");
	}
}

void	cep_acknowledge_clear(zbx_cep_acknowledge_t *ack)
{
	zbx_json_free(&ack->json);
}

static void	cep_acknowledge_open(zbx_cep_acknowledge_t *ack, int op, const char *details)
{
	cep_acknowledge_init(ack);

	zbx_json_addobject(&ack->json, NULL);
	zbx_json_addint64(&ack->json, "operation", op);
	zbx_json_addobject(&ack->json, details);
}

static void	cep_acknowledge_add_detail(zbx_cep_acknowledge_t *ack, const char *detail, const char *old_value,
		const char *new_value)
{
	if (NULL != old_value)
	{
		if (NULL != new_value)
		{
			zbx_json_addobject(&ack->json, detail);
			zbx_json_addstring(&ack->json, "old", old_value, ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&ack->json, "new", new_value, ZBX_JSON_TYPE_STRING);
			zbx_json_close(&ack->json);
		}
		else
			zbx_json_addstring(&ack->json, detail, old_value, ZBX_JSON_TYPE_STRING);
	}
	else if (NULL != new_value)
		zbx_json_addstring(&ack->json, detail, new_value, ZBX_JSON_TYPE_STRING);
}

static void	cep_acknowledge_close(zbx_cep_acknowledge_t *ack)
{
	zbx_json_close(&ack->json);
	zbx_json_close(&ack->json);
}

static void	cep_acknowledge_update(zbx_cep_acknowledge_t *ack, int op)
{
	cep_acknowledge_init(ack);
	zbx_json_addobject(&ack->json, NULL);
	zbx_json_addint64(&ack->json, "operation", op);
	zbx_json_close(&ack->json);
}

static void	cep_acknowledge_update_severity(zbx_cep_acknowledge_t *ack, int op, int old_value, int new_value)
{
	cep_acknowledge_open(ack, op, "severity");
	zbx_json_addint64(&ack->json, "old", old_value);
	zbx_json_addint64(&ack->json, "new", new_value);
	cep_acknowledge_close(ack);
}

static void	cep_acknowledge_update_name(zbx_cep_acknowledge_t *ack, int op, const char *old_value,
		const char *new_value)
{
	cep_acknowledge_open(ack, op, "name");
	zbx_json_addstring(&ack->json, "old", old_value, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(&ack->json, "new", new_value, ZBX_JSON_TYPE_STRING);
	cep_acknowledge_close(ack);
}

static void	cep_acknowledge_update_tag(zbx_cep_acknowledge_t *oplog, int op, const char *old_tag,
		const char *old_value, const char *new_tag, const char *new_value)
{
	cep_acknowledge_open(oplog, op, "tag");
	cep_acknowledge_add_detail(oplog, "tag", old_tag, new_tag);
	cep_acknowledge_add_detail(oplog, "value", old_value, new_value);
	cep_acknowledge_close(oplog);
}

static void	cep_operation_event_execute_set_name(const zbx_cep_operation_t *op, zbx_cep_event_context_t *ctx,
		zbx_cep_acknowledge_t *ack, zbx_cep_event_t **event)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() operationid:" ZBX_FS_UI64, __func__, op->operationid);

	if (NULL == *event)
		*event = cep_event_context_get_mutable_event(ctx);

	if (NULL != *event)
	{
		char	*name = zbx_strdup(NULL, op->args.set_name.name);

		cep_event_context_resolve_name_macros(ctx, &name);
		cep_acknowledge_update_name(ack, op->type, (*event)->name, name);
		zbx_free((*event)->name);
		(*event)->name = name;
		ctx->sync_flags |= CEP_SYNC_EVENT_NAME;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	cep_operation_event_execute_set_severity(const zbx_cep_operation_t *op, zbx_cep_event_context_t *ctx,
		zbx_cep_acknowledge_t *ack, zbx_cep_event_t **event)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() operationid:" ZBX_FS_UI64, __func__, op->operationid);

	if (NULL == *event)
		*event = cep_event_context_get_mutable_event(ctx);

	if (NULL != *event)
	{
		cep_acknowledge_update_severity(ack, op->type, (*event)->severity, op->args.set_severity.level);
		(*event)->severity = op->args.set_severity.level;
		ctx->sync_flags |= CEP_SYNC_EVENT_SEVERITY;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	cep_operation_event_execute_increase_severity(const zbx_cep_operation_t *op,
		zbx_cep_event_context_t *ctx, zbx_cep_acknowledge_t *ack, zbx_cep_event_t **event)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() operationid:" ZBX_FS_UI64, __func__, op->operationid);

	if (NULL == *event)
		*event = cep_event_context_get_mutable_event(ctx);

	if (NULL != *event)
	{
		if (TRIGGER_SEVERITY_DISASTER == (*event)->severity)
			goto out;

		cep_acknowledge_update_severity(ack, op->type, (*event)->severity, (*event)->severity + 1);
		(*event)->severity++;
		ctx->sync_flags |= CEP_SYNC_EVENT_SEVERITY;

	}
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	cep_operation_event_execute_decrease_severity(const zbx_cep_operation_t *op,
		zbx_cep_event_context_t *ctx, zbx_cep_acknowledge_t *ack, zbx_cep_event_t **event)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() operationid:" ZBX_FS_UI64, __func__, op->operationid);

	if (NULL == *event)
		*event = cep_event_context_get_mutable_event(ctx);

	if (NULL != *event)
	{
		if (TRIGGER_SEVERITY_NOT_CLASSIFIED == (*event)->severity)
			goto out;

		cep_acknowledge_update_severity(ack, op->type, (*event)->severity, (*event)->severity - 1);
		(*event)->severity++;
		ctx->sync_flags |= CEP_SYNC_EVENT_SEVERITY;
	}
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	cep_operation_event_execute_suppress_event(zbx_uint64_t ruleid, const zbx_cep_operation_t *op,
		zbx_cep_event_context_t *ctx, zbx_cep_acknowledge_t *ack, zbx_cep_event_t **event)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() operationid:" ZBX_FS_UI64, __func__, op->operationid);

	if (time(NULL) >= (time_t)op->args.suppress.until)
		goto out;

	if (NULL == *event)
		*event = cep_event_context_get_mutable_event(ctx);

	if (NULL != *event)
	{
		zbx_db_event_suppress_t	suppress_local = {.cep_ruleid = ruleid};

		cep_acknowledge_update(ack, op->type);
		cep_event_add_suppress(*event, &suppress_local, 1);
	}
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static char	*cep_str_detach(char **src)
{
	char	*str = *src;

	*src = NULL;
	return str;
}

static zbx_tag_t	cep_event_tag_copy(zbx_cep_event_context_t *ctx, const char *tag, const char *value)
{
	zbx_tag_t	copy;

	if (NULL != tag)
	{
		copy.tag = zbx_strdup(NULL, tag);
		cep_event_context_resolve_tag_macros(ctx, &copy.tag);
	}
	else
		copy.tag = NULL;

	if (NULL != value)
	{
		copy.value = zbx_strdup(NULL, value);
		cep_event_context_resolve_tag_macros(ctx, &copy.value);
	}
	else
		copy.value = NULL;

	return copy;
}

static void	cep_operation_event_add_tag(const zbx_cep_operation_t *op, zbx_cep_event_context_t *ctx,
		zbx_cep_acknowledge_t *ack, zbx_cep_event_t **event)
{
	zbx_tag_t	tag = {0};

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() operationid:" ZBX_FS_UI64, __func__, op->operationid);

	if (NULL == *event && NULL == (*event = cep_event_context_get_mutable_event(ctx)))
		goto out;

	tag = cep_event_tag_copy(ctx, op->args.add_tag.tag, op->args.add_tag.value);

	if (SUCCEED == cep_event_validate_tag(*event, tag.tag, tag.value, NULL))
	{
		cep_acknowledge_update_tag(ack, op->type, NULL, NULL, tag.tag, tag.value);
		zbx_vector_lite_tag_append(&(*event)->tags, tag);
		tag.tag = tag.value = NULL;
		ctx->sync_flags |= CEP_SYNC_EVENT_TAGS;
	}
out:
	zbx_tag_clear(&tag);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	cep_operation_event_set_tag(const zbx_cep_operation_t *op, zbx_cep_event_context_t *ctx,
		zbx_cep_acknowledge_t *ack, zbx_cep_event_t **event)
{
	zbx_tag_t	tag = {0};
	int		index;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() operationid:" ZBX_FS_UI64, __func__, op->operationid);

	if (NULL == *event && NULL == (*event = cep_event_context_get_mutable_event(ctx)))
		goto out;

	tag = cep_event_tag_copy(ctx, op->args.set_tag.tag, op->args.set_tag.value);

	if (SUCCEED == cep_event_validate_tag(*event, tag.tag, tag.value, &index))
	{
		if (FAIL == index)
		{
			cep_acknowledge_update_tag(ack, op->type, NULL, NULL, tag.tag, tag.value);
			zbx_vector_lite_tag_append(&(*event)->tags, tag);
			tag.tag = tag.value = NULL;
		}
		else
		{
			zbx_tag_t	*t = &(*event)->tags.values[index];

			cep_acknowledge_update_tag(ack, op->type, t->tag, t->value, NULL, tag.value);
			zbx_free(t->value);
			t->value = cep_str_detach(&tag.value);
		}

		ctx->sync_flags |= CEP_SYNC_EVENT_TAGS;
	}
out:
	zbx_tag_clear(&tag);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	cep_operation_event_set_tag_value(const zbx_cep_operation_t *op, zbx_cep_event_context_t *ctx,
		zbx_cep_acknowledge_t *ack, zbx_cep_event_t **event)
{
	zbx_tag_t	tag = {0};
	int		index;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() operationid:" ZBX_FS_UI64, __func__, op->operationid);

	if (NULL == *event && NULL == (*event = cep_event_context_get_mutable_event(ctx)))
		goto out;

	tag = cep_event_tag_copy(ctx, op->args.set_tag_value.tag, op->args.set_tag_value.value);

	if (SUCCEED == cep_event_validate_tag(*event, tag.tag, tag.value, &index))
	{
		if (FAIL != index)
		{
			zbx_tag_t	*t = &(*event)->tags.values[index];

			cep_acknowledge_update_tag(ack, op->type, t->tag, t->value, NULL, tag.value);
			zbx_free(t->value);
			t->value = cep_str_detach(&tag.value);
			ctx->sync_flags |= CEP_SYNC_EVENT_TAGS;
		}
	}
out:
	zbx_tag_clear(&tag);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	cep_operation_event_increase_tag_value(const zbx_cep_operation_t *op, zbx_cep_event_context_t *ctx,
		zbx_cep_acknowledge_t *ack, zbx_cep_event_t **event)
{
	zbx_tag_t	tag = {0};
	int		index;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() operationid:" ZBX_FS_UI64, __func__, op->operationid);

	if (NULL == *event && NULL == (*event = cep_event_context_get_mutable_event(ctx)))
		goto out;

	tag = cep_event_tag_copy(ctx, op->args.increase_tag_value.tag, NULL);

	if (FAIL != (index = cep_event_find_tag(*event, tag.tag)))
	{
		zbx_tag_t	*t = &(*event)->tags.values[index];
		char		*value;

		if (NULL != (value = cep_tag_value_shift(t->value, 1)))
		{
			cep_acknowledge_update_tag(ack, op->type, t->tag, t->value, NULL, value);
			zbx_free(t->value);
			t->value = value;
			ctx->sync_flags |= CEP_SYNC_EVENT_TAGS;
		}
	}
out:
	zbx_tag_clear(&tag);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	cep_operation_event_decrease_tag_value(const zbx_cep_operation_t *op, zbx_cep_event_context_t *ctx,
		zbx_cep_acknowledge_t *ack, zbx_cep_event_t **event)
{
	zbx_tag_t	tag = {0};
	int		index;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() operationid:" ZBX_FS_UI64, __func__, op->operationid);

	if (NULL == *event && NULL == (*event = cep_event_context_get_mutable_event(ctx)))
		goto out;

	tag = cep_event_tag_copy(ctx, op->args.decrease_tag_value.tag, NULL);

	if (FAIL != (index = cep_event_find_tag(*event, tag.tag)))
	{
		zbx_tag_t	*t = &(*event)->tags.values[index];
		char	*value;

		if (NULL != (value = cep_tag_value_shift(t->value, -1)))
		{
			cep_acknowledge_update_tag(ack, op->type, t->tag, t->value, NULL, value);
			zbx_free(t->value);
			t->value = value;
			ctx->sync_flags |= CEP_SYNC_EVENT_TAGS;
		}
	}
out:
	zbx_tag_clear(&tag);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	cep_operation_event_rename_tag(const zbx_cep_operation_t *op, zbx_cep_event_context_t *ctx,
		zbx_cep_acknowledge_t *ack, zbx_cep_event_t **event)
{
	zbx_tag_t	tag = {0};
	int		index;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() operationid:" ZBX_FS_UI64, __func__, op->operationid);

	if (NULL == *event && NULL == (*event = cep_event_context_get_mutable_event(ctx)))
		goto out;

	/* resolve new tag name into tag.value */
	tag = cep_event_tag_copy(ctx, op->args.rename_tag.old_tag, op->args.rename_tag.new_tag);

	if (FAIL != (index = cep_event_find_tag(*event, tag.tag)))
	{
		zbx_tag_t	*t = &(*event)->tags.values[index];

		if (SUCCEED == cep_event_validate_tag(*event, tag.value, t->value, NULL))
		{
			cep_acknowledge_update_tag(ack, op->type, t->tag, NULL, tag.value, NULL);
			zbx_free(t->tag);
			t->tag = cep_str_detach(&tag.value);
			ctx->sync_flags |= CEP_SYNC_EVENT_TAGS;
		}
	}
out:
	zbx_tag_clear(&tag);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	cep_operation_event_remove_tag(const zbx_cep_operation_t *op, zbx_cep_event_context_t *ctx,
		zbx_cep_acknowledge_t *ack, zbx_cep_event_t **event)
{
	zbx_tag_t	tag = {0};
	int		index;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() operationid:" ZBX_FS_UI64, __func__, op->operationid);

	if (NULL == *event && NULL == (*event = cep_event_context_get_mutable_event(ctx)))
		goto out;

	tag = cep_event_tag_copy(ctx, op->args.remove_tag.tag, NULL);

	if (FAIL != (index = cep_event_find_tag(*event, tag.tag)))
	{
		zbx_tag_t	*t = &(*event)->tags.values[index];

		cep_acknowledge_update_tag(ack, op->type, t->tag, t->value, NULL, NULL);
		zbx_tag_clear(t);
		zbx_vector_lite_tag_remove_noorder(&(*event)->tags, index);
		ctx->sync_flags |= CEP_SYNC_EVENT_TAGS;
	}
out:
	zbx_tag_clear(&tag);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	cep_operation_event_copy(const zbx_cep_operation_t *op, zbx_cep_event_context_t *ctx,
		zbx_cep_event_pos_t pos, zbx_cep_acknowledge_t *ack, zbx_vector_mw_task_ptr_t *tasks)
{
	zbx_cep_event_t	*event;
	zbx_db_event	*db_event;
	zbx_timespec_t	ts;
	zbx_mw_task_t	*t;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() operationid:" ZBX_FS_UI64 " pos:%d", __func__, op->operationid, pos);

	if (ctx->pos != pos)
		goto out;

	if (NULL == (event = cep_event_context_get_event(ctx)))
		goto out;

	zbx_timespec(&ts);

	if (NULL == (db_event = cep_db_event_create(&event->origin, event->name, ts.sec, ts.ns, event->severity,
			event->value, &event->tags)))
	{
		goto out;
	}

	cep_event_expect(db_event);
	cep_acknowledge_update(ack, op->type);
	t = cep_create_task_event(db_event, ZBX_EVENT_COPIED);
	zbx_vector_mw_task_ptr_append(tasks, t);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	cep_operation_event_execute(const zbx_cep_operation_t *op, int execute_when, zbx_uint64_t ruleid,
		zbx_cep_event_context_t *ctx, zbx_cep_acknowledge_t *ack, zbx_vector_mw_task_ptr_t *tasks,
		zbx_cep_event_t **event)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() operationid:" ZBX_FS_UI64 " type:%d", __func__, op->operationid, op->type);

	if (SUCCEED != cep_operation_match_event(op, ctx))
		return;

	switch (op->type)
	{
		case ZBX_CEP_OP_SET_NAME:
			if (0 != (CEP_OP_SET_NAME_MASK & CEP_FLAG(execute_when)))
				cep_operation_event_execute_set_name(op, ctx, ack, event);
			break;
		case ZBX_CEP_OP_SET_SEVERITY:
			if (0 != (CEP_OP_SET_SEVERITY_MASK & CEP_FLAG(execute_when)))
				cep_operation_event_execute_set_severity(op, ctx, ack, event);
			break;
		case ZBX_CEP_OP_INCREASE_SEVERITY:
			if (0 != (CEP_OP_INCREASE_SEVERITY_MASK & CEP_FLAG(execute_when)))
				cep_operation_event_execute_increase_severity(op, ctx, ack, event);
			break;
		case ZBX_CEP_OP_DECREASE_SEVERITY:
			if (0 != (CEP_OP_DECREASE_SEVERITY_MASK & CEP_FLAG(execute_when)))
				cep_operation_event_execute_decrease_severity(op, ctx, ack, event);
			break;
		case ZBX_CEP_OP_SUPPRESS:
			if (0 != (CEP_OP_SUPPRESS_MASK & CEP_FLAG(execute_when)))
				cep_operation_event_execute_suppress_event(ruleid, op, ctx, ack, event);
			break;
		case ZBX_CEP_OP_ADD_TAG:
			if (0 != (CEP_OP_ADD_TAG_MASK & CEP_FLAG(execute_when)))
				cep_operation_event_add_tag(op, ctx, ack, event);
			break;
		case ZBX_CEP_OP_SET_TAG:
			if (0 != (CEP_OP_SET_TAG_MASK & CEP_FLAG(execute_when)))
				cep_operation_event_set_tag(op, ctx, ack, event);
			break;
		case ZBX_CEP_OP_SET_TAG_VALUE:
			if (0 != (CEP_OP_SET_TAG_VALUE_MASK & CEP_FLAG(execute_when)))
				cep_operation_event_set_tag_value(op, ctx, ack, event);
			break;
		case ZBX_CEP_OP_INCREASE_TAG_VALUE:
			if (0 != (CEP_OP_INCREASE_TAG_VALUE_MASK & CEP_FLAG(execute_when)))
				cep_operation_event_increase_tag_value(op, ctx, ack, event);
			break;
		case ZBX_CEP_OP_DECREASE_TAG_VALUE:
			if (0 != (CEP_OP_DECREASE_TAG_VALUE_MASK & CEP_FLAG(execute_when)))
				cep_operation_event_decrease_tag_value(op, ctx, ack, event);
			break;
		case ZBX_CEP_OP_RENAME_TAG:
			if (0 != (CEP_OP_RENAME_TAG_MASK & CEP_FLAG(execute_when)))
				cep_operation_event_rename_tag(op, ctx, ack, event);
			break;
		case ZBX_CEP_OP_REMOVE_TAG:
			if (0 != (CEP_OP_REMOVE_TAG_MASK & CEP_FLAG(execute_when)))
				cep_operation_event_remove_tag(op, ctx, ack, event);
			break;
		case ZBX_CEP_OP_CLOSE:
			cep_operation_db_execute_close_event(ruleid, ctx, tasks);
			cep_acknowledge_update(ack, op->type);
			break;
		case ZBX_CEP_OP_DISCARD:
			cep_acknowledge_update(ack, op->type);
			break;
		case ZBX_CEP_OP_COPY_FIRST:
			if (0 != (CEP_OP_COPY_EVENT & CEP_FLAG(execute_when)))
				cep_operation_event_copy(op, ctx, CEP_POS_FIRST, ack, tasks);
			break;
		case ZBX_CEP_OP_COPY_LAST:
			if (0 != (CEP_OP_COPY_EVENT & CEP_FLAG(execute_when)))
				cep_operation_event_copy(op, ctx, CEP_POS_LAST, ack, tasks);
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN_MSG("unsupported operation type %d", op->type);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

void	cep_rule_event_execute_ops(const zbx_cep_rule_t *rule, int execute_when, zbx_cep_event_context_t *ctx,
		zbx_cep_event_t **event, zbx_vector_mw_task_ptr_t *tasks)
{
	zbx_cep_acknowledge_t	ack = {0};

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() ruleid:" ZBX_FS_UI64 " operations:%d", __func__, rule->ruleid,
			rule->operations.values_num);

	for (int i = 0; i < rule->operations.values_num; i++)
	{
		if (rule->operations.values[i].execute_when == execute_when)
		{
			cep_operation_event_execute(&rule->operations.values[i], execute_when,  rule->ruleid, ctx,
					&ack, tasks, event);
		}
	}

	if (SUCCEED == cep_acknowledge_is_set(&ack))
	{
		/* acknowledge data is moved to the created task */
		zbx_mw_task_t	*t = cep_create_task_acknowledge(&ack, rule->ruleid, cep_event_context_eventid(ctx));

		zbx_vector_mw_task_ptr_append(tasks, t);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

void	cep_rule_event_context_execute_ops(const zbx_cep_rule_t *rule, zbx_cep_event_context_t *ctx, int execute_when,
		zbx_vector_mw_task_ptr_t *tasks)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() ruleid:" ZBX_FS_UI64, __func__, rule->ruleid);

	zbx_cep_event_t	*event = NULL;

	cep_rule_event_execute_ops(rule, execute_when, ctx, &event, tasks);

	if (NULL != event)
	{
		zbx_cep_t	*cep;

		cep_cache_acquire(&cep);
		cep_event_handle_set(ctx->hevent, event);
		cep_cache_release(&cep);

		zbx_mw_task_t	*t = cep_create_task_sync_event(ctx->hevent, ctx->sync_flags);

		zbx_vector_mw_task_ptr_append(tasks, t);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

void	cep_event_execute_ops(const zbx_cep_rule_t **matched_rules, int matched_rules_num, int execute_when,
		zbx_cep_event_context_t *ctx, zbx_cep_event_t **event, zbx_vector_mw_task_ptr_t *tasks)
{
	for (int i = 0; i < matched_rules_num; i++)
		cep_rule_event_execute_ops(matched_rules[i], execute_when, ctx, event, tasks);
}

void	cep_event_add_to_rules(zbx_cep_event_context_t *ctx,
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
				cep_window_simple_process_event(rule, ctx, tasks);
				break;
			case ZBX_CEP_WINDOW_CAUSAL:
				cep_window_causal_process_event(rule, ctx, tasks);
				break;
			case ZBX_CEP_WINDOW_TAG_MATCH:
			case ZBX_CEP_WINDOW_PATTERN_MATCH:
				/* TODO: implement */
				break;
		}
	}
	/* TODO: implementation */
}

