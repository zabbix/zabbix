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

#include "cep_api.h"
#include "cep_rule.h"
#include "cep.h"
#include "cep_task.h"
#include "cep_event.h"
#include "cep_window.h"
#include "zbx_cep.h"
#include "zbx_trigger_constants.h"
#include "zbxalgo.h"
#include "zbxcacheconfig.h"
#include "zbxcommon.h"
#include "zbxdbhigh.h"
#include "zbxjson.h"

static int	cep_acknowledge_is_set(zbx_cep_acknowledge_t *ack)
{
	return (0 == ack->json.buffer_size ? FAIL : SUCCEED);
}

/******************************************************************************
 *                                                                            *
 * Purpose: initialize an acknowledge if it has not already been initialized  *
 *                                                                            *
 * Parameters: ack - [IN/OUT] acknowledge                                     *
 *                                                                            *
 ******************************************************************************/
static void	cep_acknowledge_init(zbx_cep_acknowledge_t *ack)
{
	if (SUCCEED != cep_acknowledge_is_set(ack))
	{
		zbx_json_init(&ack->json, 1024);
		zbx_json_addarray(&ack->json, "cep");
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: free resources allocated by an acknowledge                        *
 *                                                                            *
 * Parameters: ack - [IN/OUT] acknowledge                                     *
 *                                                                            *
 ******************************************************************************/
void	cep_acknowledge_clear(zbx_cep_acknowledge_t *ack)
{
	zbx_json_free(&ack->json);
}

/******************************************************************************
 *                                                                            *
 * Purpose: open a new operation entry in an acknowledge, initializing the    *
 *          acknowledge if necessary                                          *
 *                                                                            *
 * Parameters: ack     - [IN/OUT] acknowledge                                 *
 *             op      - [IN] operation type                                  *
 *             details - [IN] name of the details object to open              *
 *                                                                            *
 ******************************************************************************/
static void	cep_acknowledge_open(zbx_cep_acknowledge_t *ack, int op, const char *details)
{
	cep_acknowledge_init(ack);

	zbx_json_addobject(&ack->json, NULL);
	zbx_json_addint64(&ack->json, "operation", op);
	zbx_json_addobject(&ack->json, details);
}

/******************************************************************************
 *                                                                            *
 * Purpose: add a detail entry to an acknowledge                              *
 *                                                                            *
 * Parameters: ack       - [IN/OUT] acknowledge                               *
 *             detail    - [IN] detail name                                   *
 *             old_value - [IN] old value, can be NULL                        *
 *             new_value - [IN] new value, can be NULL                        *
 *                                                                            *
 ******************************************************************************/
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

/******************************************************************************
 *                                                                            *
 * Purpose: close an operation entry in an acknowledge                        *
 *                                                                            *
 ******************************************************************************/
static void	cep_acknowledge_close(zbx_cep_acknowledge_t *ack)
{
	zbx_json_close(&ack->json);
	zbx_json_close(&ack->json);
}

/******************************************************************************
 *                                                                            *
 * Purpose: add an operation entry to an acknowledge, initializing it if      *
 *          necessary                                                         *
 *                                                                            *
 * Parameters: ack - [IN/OUT] acknowledge                                     *
 *             op  - [IN] operation type                                      *
 *                                                                            *
 ******************************************************************************/
static void	cep_acknowledge_update(zbx_cep_acknowledge_t *ack, int op)
{
	cep_acknowledge_init(ack);
	zbx_json_addobject(&ack->json, NULL);
	zbx_json_addint64(&ack->json, "operation", op);
	zbx_json_close(&ack->json);
}

/******************************************************************************
 *                                                                            *
 * Purpose: add a severity change entry to an acknowledge                     *
 *                                                                            *
 * Parameters: ack       - [IN/OUT] acknowledge                               *
 *             op        - [IN] operation type                                *
 *             old_value - [IN] old severity                                  *
 *             new_value - [IN] new severity                                  *
 *                                                                            *
 ******************************************************************************/
static void	cep_acknowledge_update_severity(zbx_cep_acknowledge_t *ack, int op, int old_value, int new_value)
{
	cep_acknowledge_open(ack, op, "severity");
	zbx_json_addint64(&ack->json, "old", old_value);
	zbx_json_addint64(&ack->json, "new", new_value);
	cep_acknowledge_close(ack);
}

/******************************************************************************
 *                                                                            *
 * Purpose: add a name change entry to an acknowledge                         *
 *                                                                            *
 * Parameters: ack       - [IN/OUT] acknowledge                               *
 *             op        - [IN] operation type                                *
 *             old_value - [IN] old name                                      *
 *             new_value - [IN] new name                                      *
 *                                                                            *
 ******************************************************************************/
static void	cep_acknowledge_update_name(zbx_cep_acknowledge_t *ack, int op, const char *old_value,
		const char *new_value)
{
	cep_acknowledge_open(ack, op, "name");
	zbx_json_addstring(&ack->json, "old", old_value, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(&ack->json, "new", new_value, ZBX_JSON_TYPE_STRING);
	cep_acknowledge_close(ack);
}

/******************************************************************************
 *                                                                            *
 * Purpose: add a tag change entry to an acknowledge                          *
 *                                                                            *
 * Parameters: oplog     - [IN/OUT] acknowledge                               *
 *             op        - [IN] operation type                                *
 *             old_tag   - [IN] old tag name, can be NULL                     *
 *             old_value - [IN] old tag value, can be NULL                    *
 *             new_tag   - [IN] new tag name, can be NULL                     *
 *             new_value - [IN] new tag value, can be NULL                    *
 *                                                                            *
 ******************************************************************************/
void	cep_acknowledge_update_tag(zbx_cep_acknowledge_t *oplog, int op, const char *old_tag,
		const char *old_value, const char *new_tag, const char *new_value)
{
	cep_acknowledge_open(oplog, op, "tag");
	cep_acknowledge_add_detail(oplog, "tag", old_tag, new_tag);
	cep_acknowledge_add_detail(oplog, "value", old_value, new_value);
	cep_acknowledge_close(oplog);
}

/******************************************************************************
 *                                                                            *
 * Purpose: add a set-cause entry to an acknowledge                           *
 *                                                                            *
 * Parameters: oplog         - [IN/OUT] acknowledge                           *
 *             cause_eventid - [IN] identifier of the cause event             *
 *                                                                            *
 ******************************************************************************/
void	cep_acknowledge_set_cause(zbx_cep_acknowledge_t *oplog, zbx_uint64_t cause_eventid)
{
	cep_acknowledge_open(oplog, ZBX_CEP_OP_SET_CAUSE, "cause");
	zbx_json_addint64(&oplog->json, "eventid", cause_eventid);
	cep_acknowledge_close(oplog);
}

static int	cep_operation_event_execute_set_name(const zbx_cep_operation_t *op, zbx_cep_event_context_t *ctx,
		zbx_cep_acknowledge_t *ack, zbx_cep_event_t **event)
{
	int	ret = FAIL;

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

		ret = SUCCEED;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute a set-name operation on an event context's event          *
 *                                                                            *
 * Parameters: op    - [IN] operation to execute                              *
 *             ctx   - [IN/OUT] event context                                 *
 *             ack   - [IN/OUT] acknowledge                                   *
 *             event - [IN/OUT] resolved mutable event, resolved from ctx if  *
 *                     not already set                                        *
 *                                                                            *
 * Return value: SUCCEED - the event name was updated                         *
 *               FAIL - the event could not be resolved                       *
 *                                                                            *
 ******************************************************************************/
static int	cep_operation_event_execute_set_severity(const zbx_cep_operation_t *op, zbx_cep_event_context_t *ctx,
		zbx_cep_acknowledge_t *ack, zbx_cep_event_t **event)
{
	int	ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() operationid:" ZBX_FS_UI64, __func__, op->operationid);

	if (NULL == *event)
		*event = cep_event_context_get_mutable_event(ctx);

	if (NULL != *event)
	{
		cep_acknowledge_update_severity(ack, op->type, (*event)->severity, op->args.set_severity.level);
		(*event)->severity = op->args.set_severity.level;
		ctx->sync_flags |= CEP_SYNC_EVENT_SEVERITY;
		ret = SUCCEED;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute an increase-severity operation on an event context's      *
 *          event                                                             *
 *                                                                            *
 * Parameters: op    - [IN] operation to execute                              *
 *             ctx   - [IN/OUT] event context                                 *
 *             ack   - [IN/OUT] acknowledge                                   *
 *             event - [IN/OUT] resolved mutable event, resolved from ctx if  *
 *                     not already set                                        *
 *                                                                            *
 * Return value: SUCCEED - the event severity was increased                   *
 *               FAIL - the event could not be resolved, or its severity is   *
 *                      already at the maximum                                *
 *                                                                            *
 ******************************************************************************/
static int	cep_operation_event_execute_increase_severity(const zbx_cep_operation_t *op,
		zbx_cep_event_context_t *ctx, zbx_cep_acknowledge_t *ack, zbx_cep_event_t **event)
{
	int	ret = FAIL;

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
		ret = SUCCEED;
	}
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute a decrease-severity operation on an event context's       *
 *          event                                                             *
 *                                                                            *
 * Parameters: op    - [IN] operation to execute                              *
 *             ctx   - [IN/OUT] event context                                 *
 *             ack   - [IN/OUT] acknowledge                                   *
 *             event - [IN/OUT] resolved mutable event, resolved from ctx if  *
 *                     not already set                                        *
 *                                                                            *
 * Return value: SUCCEED - the event severity was decreased                   *
 *               FAIL - the event could not be resolved, or its severity is   *
 *                      already at the minimum                                *
 *                                                                            *
 ******************************************************************************/
static int	cep_operation_event_execute_decrease_severity(const zbx_cep_operation_t *op,
		zbx_cep_event_context_t *ctx, zbx_cep_acknowledge_t *ack, zbx_cep_event_t **event)
{
	int	ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() operationid:" ZBX_FS_UI64, __func__, op->operationid);

	if (NULL == *event)
		*event = cep_event_context_get_mutable_event(ctx);

	if (NULL != *event)
	{
		if (TRIGGER_SEVERITY_NOT_CLASSIFIED == (*event)->severity)
			goto out;

		cep_acknowledge_update_severity(ack, op->type, (*event)->severity, (*event)->severity - 1);
		(*event)->severity--;
		ctx->sync_flags |= CEP_SYNC_EVENT_SEVERITY;
		ret = SUCCEED;
	}
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute a suppress-event operation on an event context's event    *
 *                                                                            *
 * Parameters: ruleid - [IN] identifier of the rule the operation belongs to  *
 *             op     - [IN] operation to execute                             *
 *             ctx    - [IN/OUT] event context                                *
 *             ack    - [IN/OUT] acknowledge                                  *
 *             event  - [IN/OUT] resolved mutable event, resolved from ctx    *
 *                      if not already set                                    *
 *                                                                            *
 * Return value: SUCCEED - the suppress entry was added                       *
 *               FAIL - the suppress operation has already expired, or the    *
 *                      event could not be resolved                           *
 *                                                                            *
 ******************************************************************************/
static int	cep_operation_event_execute_suppress_event(zbx_uint64_t ruleid, const zbx_cep_operation_t *op,
		zbx_cep_event_context_t *ctx, zbx_cep_acknowledge_t *ack, zbx_cep_event_t **event)
{
	int	ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() operationid:" ZBX_FS_UI64, __func__, op->operationid);

	if (NULL == *event)
		*event = cep_event_context_get_mutable_event(ctx);

	if (NULL != *event)
	{
		zbx_db_event_suppress_t	suppress_local = {.cep_ruleid = ruleid, .until = op->args.suppress.duration};

		if (0 != suppress_local.until)
			suppress_local.until += (int)time(NULL);

		cep_acknowledge_update(ack, op->type);
		cep_event_add_suppress(*event, &suppress_local, 1);
		ctx->sync_flags |= CEP_SYNC_EVENT_SUPPRESS;
		ret = SUCCEED;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

static char	*cep_str_detach(char **src)
{
	char	*str = *src;

	*src = NULL;
	return str;
}

/******************************************************************************
 *                                                                            *
 * Purpose: create a tag with macros resolved using an event context          *
 *                                                                            *
 * Parameters: ctx   - [IN/OUT] event context                                 *
 *             name  - [IN] tag name, can be NULL                             *
 *             value - [IN] tag value, can be NULL                            *
 *                                                                            *
 * Return value: the created tag                                              *
 *                                                                            *
 ******************************************************************************/
static zbx_tag_t	cep_event_tag_create(zbx_cep_event_context_t *ctx, const char *name, const char *value)
{
	zbx_tag_t	tag;

	if (NULL != name)
	{
		tag.tag = zbx_strdup(NULL, name);
		cep_event_context_resolve_tag_macros(ctx, &tag.tag);
	}
	else
		tag.tag = NULL;

	if (NULL != value)
	{
		tag.value = zbx_strdup(NULL, value);
		cep_event_context_resolve_tag_macros(ctx, &tag.value);
	}
	else
		tag.value = NULL;

	return tag;
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute an add-tag operation on an event context's event          *
 *                                                                            *
 * Parameters: op    - [IN] operation to execute                              *
 *             ctx   - [IN/OUT] event context                                 *
 *             ack   - [IN/OUT] acknowledge                                   *
 *             event - [IN/OUT] resolved mutable event, resolved from ctx if  *
 *                     not already set                                        *
 *                                                                            *
 * Return value: SUCCEED - the tag was added                                  *
 *               FAIL - the event could not be resolved, or a tag with the    *
 *                      same name and value already exists                    *
 *                                                                            *
 ******************************************************************************/
static int	cep_operation_event_add_tag(const zbx_cep_operation_t *op, zbx_cep_event_context_t *ctx,
		zbx_cep_acknowledge_t *ack, zbx_cep_event_t **event)
{
	zbx_tag_t	tag = {0};
	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() operationid:" ZBX_FS_UI64, __func__, op->operationid);

	if (NULL == *event && NULL == (*event = cep_event_context_get_mutable_event(ctx)))
		goto out;

	tag = cep_event_tag_create(ctx, op->args.add_tag.tag, op->args.add_tag.value);

	if (SUCCEED == cep_event_validate_tag(*event, tag.tag, tag.value, NULL))
	{
		cep_acknowledge_update_tag(ack, op->type, NULL, NULL, tag.tag, tag.value);
		zbx_vector_lite_tag_append(&(*event)->tags, tag);
		tag.tag = tag.value = NULL;
		ctx->sync_flags |= CEP_SYNC_EVENT_TAGS;
		ret = SUCCEED;
	}
out:
	zbx_tag_clear(&tag);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute a set-tag operation on an event context's event           *
 *                                                                            *
 * Parameters: op    - [IN] operation to execute                              *
 *             ctx   - [IN/OUT] event context                                 *
 *             ack   - [IN/OUT] acknowledge                                   *
 *             event - [IN/OUT] resolved mutable event, resolved from ctx if  *
 *                     not already set                                        *
 *                                                                            *
 * Return value: SUCCEED - the tag was added or updated                       *
 *               FAIL - the event could not be resolved, or a tag with the    *
 *                      same name and value already exists                    *
 *                                                                            *
 * Comments: If no tag with the given name exists, a new tag is added.        *
 *           Otherwise the existing tag's value is updated.                   *
 *                                                                            *
 ******************************************************************************/
static int	cep_operation_event_set_tag(const zbx_cep_operation_t *op, zbx_cep_event_context_t *ctx,
		zbx_cep_acknowledge_t *ack, zbx_cep_event_t **event)
{
	zbx_tag_t	tag = {0};
	int		index, ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() operationid:" ZBX_FS_UI64, __func__, op->operationid);

	if (NULL == *event && NULL == (*event = cep_event_context_get_mutable_event(ctx)))
		goto out;

	tag = cep_event_tag_create(ctx, op->args.set_tag.tag, op->args.set_tag.value);

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
		ret = SUCCEED;
	}
out:
	zbx_tag_clear(&tag);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute a set-tag-value operation on an event context's event     *
 *                                                                            *
 * Parameters: op    - [IN] operation to execute                              *
 *             ctx   - [IN/OUT] event context                                 *
 *             ack   - [IN/OUT] acknowledge                                   *
 *             event - [IN/OUT] resolved mutable event, resolved from ctx if  *
 *                     not already set                                        *
 *                                                                            *
 * Return value: SUCCEED - the tag value was updated                          *
 *               FAIL - the event could not be resolved, or no tag with the   *
 *                      given name exists, or a tag with the same name and    *
 *                      value already exists                                  *
 *                                                                            *
 ******************************************************************************/
static int	cep_operation_event_set_tag_value(const zbx_cep_operation_t *op, zbx_cep_event_context_t *ctx,
		zbx_cep_acknowledge_t *ack, zbx_cep_event_t **event)
{
	zbx_tag_t	tag = {0};
	int		index, ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() operationid:" ZBX_FS_UI64, __func__, op->operationid);

	if (NULL == *event && NULL == (*event = cep_event_context_get_mutable_event(ctx)))
		goto out;

	tag = cep_event_tag_create(ctx, op->args.set_tag_value.tag, op->args.set_tag_value.value);

	if (SUCCEED == cep_event_validate_tag(*event, tag.tag, tag.value, &index))
	{
		if (FAIL != index)
		{
			zbx_tag_t	*t = &(*event)->tags.values[index];

			cep_acknowledge_update_tag(ack, op->type, t->tag, t->value, NULL, tag.value);
			zbx_free(t->value);
			t->value = cep_str_detach(&tag.value);
			ctx->sync_flags |= CEP_SYNC_EVENT_TAGS;
			ret = SUCCEED;
		}
	}
out:
	zbx_tag_clear(&tag);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute an increase-tag-value operation on an event context's     *
 *          event                                                             *
 *                                                                            *
 * Parameters: op    - [IN] operation to execute                              *
 *             ctx   - [IN/OUT] event context                                 *
 *             ack   - [IN/OUT] acknowledge                                   *
 *             event - [IN/OUT] resolved mutable event, resolved from ctx if  *
 *                     not already set                                        *
 *                                                                            *
 * Return value: SUCCEED - the tag value was incremented                      *
 *               FAIL - the event could not be resolved, no tag with the      *
 *                      given name exists, or its value could not be          *
 *                      incremented                                           *
 *                                                                            *
 ******************************************************************************/
static int	cep_operation_event_increase_tag_value(const zbx_cep_operation_t *op, zbx_cep_event_context_t *ctx,
		zbx_cep_acknowledge_t *ack, zbx_cep_event_t **event)
{
	zbx_tag_t	tag = {0};
	int		index, ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() operationid:" ZBX_FS_UI64, __func__, op->operationid);

	if (NULL == *event && NULL == (*event = cep_event_context_get_mutable_event(ctx)))
		goto out;

	tag = cep_event_tag_create(ctx, op->args.increase_tag_value.tag, NULL);

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
			ret = SUCCEED;
		}
	}
out:
	zbx_tag_clear(&tag);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;

}

/******************************************************************************
 *                                                                            *
 * Purpose: execute a decrease-tag-value operation on an event context's      *
 *          event                                                             *
 *                                                                            *
 * Parameters: op    - [IN] operation to execute                              *
 *             ctx   - [IN/OUT] event context                                 *
 *             ack   - [IN/OUT] acknowledge                                   *
 *             event - [IN/OUT] resolved mutable event, resolved from ctx if  *
 *                     not already set                                        *
 *                                                                            *
 * Return value: SUCCEED - the tag value was decremented                      *
 *               FAIL - the event could not be resolved, no tag with the      *
 *                      given name exists, or its value could not be          *
 *                      decremented                                           *
 *                                                                            *
 ******************************************************************************/
static int	cep_operation_event_decrease_tag_value(const zbx_cep_operation_t *op, zbx_cep_event_context_t *ctx,
		zbx_cep_acknowledge_t *ack, zbx_cep_event_t **event)
{
	zbx_tag_t	tag = {0};
	int		index, ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() operationid:" ZBX_FS_UI64, __func__, op->operationid);

	if (NULL == *event && NULL == (*event = cep_event_context_get_mutable_event(ctx)))
		goto out;

	tag = cep_event_tag_create(ctx, op->args.decrease_tag_value.tag, NULL);

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
			ret = SUCCEED;
		}
	}
out:
	zbx_tag_clear(&tag);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute a rename-tag operation on an event context's event        *
 *                                                                            *
 * Parameters: op    - [IN] operation to execute                              *
 *             ctx   - [IN/OUT] event context                                 *
 *             ack   - [IN/OUT] acknowledge                                   *
 *             event - [IN/OUT] resolved mutable event, resolved from ctx if  *
 *                     not already set                                        *
 *                                                                            *
 * Return value: SUCCEED - the tag was renamed                                *
 *               FAIL - the event could not be resolved, no tag with the old  *
 *                      name exists, or a tag with the new name and the       *
 *                      existing tag's value already exists                   *
 *                                                                            *
 ******************************************************************************/
static int	cep_operation_event_rename_tag(const zbx_cep_operation_t *op, zbx_cep_event_context_t *ctx,
		zbx_cep_acknowledge_t *ack, zbx_cep_event_t **event)
{
	zbx_tag_t	tag = {0};
	int		index, ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() operationid:" ZBX_FS_UI64, __func__, op->operationid);

	if (NULL == *event && NULL == (*event = cep_event_context_get_mutable_event(ctx)))
		goto out;

	/* resolve new tag name into tag.value */
	tag = cep_event_tag_create(ctx, op->args.rename_tag.old_tag, op->args.rename_tag.new_tag);

	if (FAIL != (index = cep_event_find_tag(*event, tag.tag)))
	{
		zbx_tag_t	*t = &(*event)->tags.values[index];

		if (SUCCEED == cep_event_validate_tag(*event, tag.value, t->value, NULL))
		{
			cep_acknowledge_update_tag(ack, op->type, t->tag, NULL, tag.value, NULL);
			zbx_free(t->tag);
			t->tag = cep_str_detach(&tag.value);
			ctx->sync_flags |= CEP_SYNC_EVENT_TAGS;
			ret = SUCCEED;
		}
	}
out:
	zbx_tag_clear(&tag);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute a remove-tag operation on an event context's event        *
 *                                                                            *
 * Parameters: op    - [IN] operation to execute                              *
 *             ctx   - [IN/OUT] event context                                 *
 *             ack   - [IN/OUT] acknowledge                                   *
 *             event - [IN/OUT] resolved mutable event, resolved from ctx if  *
 *                     not already set                                        *
 *                                                                            *
 * Return value: SUCCEED - the tag was removed                                *
 *               FAIL - the event could not be resolved, or no tag with the   *
 *                      given name exists                                     *
 *                                                                            *
 ******************************************************************************/
static int	cep_operation_event_remove_tag(const zbx_cep_operation_t *op, zbx_cep_event_context_t *ctx,
		zbx_cep_acknowledge_t *ack, zbx_cep_event_t **event)
{
	zbx_tag_t	tag = {0};
	int		index, ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() operationid:" ZBX_FS_UI64, __func__, op->operationid);

	if (NULL == *event && NULL == (*event = cep_event_context_get_mutable_event(ctx)))
		goto out;

	tag = cep_event_tag_create(ctx, op->args.remove_tag.tag, NULL);

	if (FAIL != (index = cep_event_find_tag(*event, tag.tag)))
	{
		zbx_tag_t	*t = &(*event)->tags.values[index];

		cep_acknowledge_update_tag(ack, op->type, t->tag, t->value, NULL, NULL);
		zbx_tag_clear(t);
		zbx_vector_lite_tag_remove_noorder(&(*event)->tags, index);
		ctx->sync_flags |= CEP_SYNC_EVENT_TAGS;
		ret = SUCCEED;
	}
out:
	zbx_tag_clear(&tag);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute a copy operation, creating a copied event task from an    *
 *          event context's event                                             *
 *                                                                            *
 * Parameters: op    - [IN] operation to execute                              *
 *             ctx   - [IN] event context                                     *
 *             pos   - [IN] position the operation applies to                 *
 *             ack   - [IN/OUT] acknowledge                                   *
 *             tasks - [OUT] vector to append the created event task to       *
 *                                                                            *
 * Return value: SUCCEED - the copy task was created                          *
 *               FAIL - the context's position does not match pos, the        *
 *                      event could not be resolved, or the db event could    *
 *                      not be created                                        *
 *                                                                            *
 ******************************************************************************/
static int	cep_operation_event_copy(const zbx_cep_operation_t *op, zbx_cep_event_context_t *ctx,
		zbx_cep_event_pos_t pos, zbx_cep_acknowledge_t *ack, zbx_vector_mw_task_ptr_t *tasks)
{
	zbx_cep_event_t	*event;
	zbx_db_event	*db_event;
	zbx_timespec_t	ts;
	zbx_mw_task_t	*t;
	int		ret = FAIL;

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
	t = cep_create_task_event_by_copy(db_event);
	zbx_vector_mw_task_ptr_append(tasks, t);
	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: create a close-event task for an event context's event            *
 *                                                                            *
 * Parameters: ruleid - [IN] identifier of the rule closing the event         *
 *             ctx    - [IN] event context                                    *
 *             tasks  - [OUT] vector to append the created task to            *
 *                                                                            *
 * Return value: SUCCEED - the close task was created                         *
 *               FAIL - the event could not be resolved                       *
 *                                                                            *
 ******************************************************************************/
static int	cep_operation_execute_close_event(zbx_uint64_t ruleid, zbx_cep_event_context_t *ctx,
		zbx_vector_mw_task_ptr_t *tasks)
{
	zbx_cep_event_t	*event;
	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (NULL != (event = cep_event_context_get_event(ctx)))
	{
		zbx_mw_task_t	*t;
		zbx_db_event	*db_event;

		db_event = cep_db_event_create(&event->origin, event->name, event->clock, event->ns, event->severity,
				TRIGGER_VALUE_OK, &event->tags);

		cep_event_expect(db_event);

		t = cep_create_task_event_by_cep_rule(db_event, event->eventid, ruleid);
		zbx_vector_mw_task_ptr_append(tasks, t);
		ret = SUCCEED;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute a valid operation on a matching event                     *
 *                                                                            *
 * Parameters: op           - [IN] operation to execute                       *
 *             execute_when - [IN] execution phase                            *
 *             ruleid       - [IN] identifier of the rule the operation       *
 *                            belongs to                                      *
 *             ctx          - [IN/OUT] event context                          *
 *             ack          - [IN/OUT] acknowledge                            *
 *             tasks        - [OUT] vector to append tasks created by the     *
 *                            operation to                                    *
 *             event        - [IN/OUT] resolved mutable event, resolved and   *
 *                            reused across calls as operations execute       *
 *                                                                            *
 * Return value: SUCCEED - the operation was executed, or does not apply at   *
 *                         execute_when                                       *
 *               FAIL - the operation did not match ctx, or its execution     *
 *                      failed                                                *
 *                                                                            *
 ******************************************************************************/
static int	cep_operation_event_execute(const zbx_cep_operation_t *op, int execute_when, zbx_uint64_t ruleid,
		zbx_cep_event_context_t *ctx, zbx_cep_acknowledge_t *ack, zbx_vector_mw_task_ptr_t *tasks,
		zbx_cep_event_t **event)
{
	int	ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() operationid:" ZBX_FS_UI64 " type:%d", __func__, op->operationid, op->type);

	if (SUCCEED != cep_operation_match_event(op, ctx))
		goto out;

	switch (op->type)
	{
		case ZBX_CEP_OP_SET_NAME:
			if (0 != (CEP_OP_SET_NAME_MASK & CEP_FLAG(execute_when)))
				ret = cep_operation_event_execute_set_name(op, ctx, ack, event);
			break;
		case ZBX_CEP_OP_SET_SEVERITY:
			if (0 != (CEP_OP_SET_SEVERITY_MASK & CEP_FLAG(execute_when)))
				ret = cep_operation_event_execute_set_severity(op, ctx, ack, event);
			break;
		case ZBX_CEP_OP_INCREASE_SEVERITY:
			if (0 != (CEP_OP_INCREASE_SEVERITY_MASK & CEP_FLAG(execute_when)))
				ret = cep_operation_event_execute_increase_severity(op, ctx, ack, event);
			break;
		case ZBX_CEP_OP_DECREASE_SEVERITY:
			if (0 != (CEP_OP_DECREASE_SEVERITY_MASK & CEP_FLAG(execute_when)))
				ret = cep_operation_event_execute_decrease_severity(op, ctx, ack, event);
			break;
		case ZBX_CEP_OP_SUPPRESS:
			if (0 != (CEP_OP_SUPPRESS_MASK & CEP_FLAG(execute_when)))
				ret = cep_operation_event_execute_suppress_event(ruleid, op, ctx, ack, event);
			break;
		case ZBX_CEP_OP_ADD_TAG:
			if (0 != (CEP_OP_ADD_TAG_MASK & CEP_FLAG(execute_when)))
				ret = cep_operation_event_add_tag(op, ctx, ack, event);
			break;
		case ZBX_CEP_OP_SET_TAG:
			if (0 != (CEP_OP_SET_TAG_MASK & CEP_FLAG(execute_when)))
				ret = cep_operation_event_set_tag(op, ctx, ack, event);
			break;
		case ZBX_CEP_OP_SET_TAG_VALUE:
			if (0 != (CEP_OP_SET_TAG_VALUE_MASK & CEP_FLAG(execute_when)))
				ret = cep_operation_event_set_tag_value(op, ctx, ack, event);
			break;
		case ZBX_CEP_OP_INCREASE_TAG_VALUE:
			if (0 != (CEP_OP_INCREASE_TAG_VALUE_MASK & CEP_FLAG(execute_when)))
				ret = cep_operation_event_increase_tag_value(op, ctx, ack, event);
			break;
		case ZBX_CEP_OP_DECREASE_TAG_VALUE:
			if (0 != (CEP_OP_DECREASE_TAG_VALUE_MASK & CEP_FLAG(execute_when)))
				ret = cep_operation_event_decrease_tag_value(op, ctx, ack, event);
			break;
		case ZBX_CEP_OP_RENAME_TAG:
			if (0 != (CEP_OP_RENAME_TAG_MASK & CEP_FLAG(execute_when)))
				ret = cep_operation_event_rename_tag(op, ctx, ack, event);
			break;
		case ZBX_CEP_OP_REMOVE_TAG:
			if (0 != (CEP_OP_REMOVE_TAG_MASK & CEP_FLAG(execute_when)))
				ret = cep_operation_event_remove_tag(op, ctx, ack, event);
			break;
		case ZBX_CEP_OP_CLOSE:
			if (0 != (CEP_OP_CLOSE_MASK & CEP_FLAG(execute_when)))
			{
				if (SUCCEED == (ret = cep_operation_execute_close_event(ruleid, ctx, tasks)))
					cep_acknowledge_update(ack, op->type);
			}
			break;
		case ZBX_CEP_OP_DISCARD:
			if (0 != (CEP_OP_DISCARD_MASK & CEP_FLAG(execute_when)))
			{
				cep_acknowledge_update(ack, op->type);
				ret = SUCCEED;
			}
			break;
		case ZBX_CEP_OP_COPY_FIRST:
			if (0 != (CEP_OP_COPY_EVENT_MASK & CEP_FLAG(execute_when)))
				ret = cep_operation_event_copy(op, ctx, CEP_POS_FIRST, ack, tasks);
			break;
		case ZBX_CEP_OP_COPY_LAST:
			if (0 != (CEP_OP_COPY_EVENT_MASK & CEP_FLAG(execute_when)))
				ret = cep_operation_event_copy(op, ctx, CEP_POS_LAST, ack, tasks);
			break;
		case ZBX_CEP_OP_CLOSE_WINDOW:
			if (0 != (CEP_OP_CLOSE_WINDOW_MASK & CEP_FLAG(execute_when)))
				ret = SUCCEED;
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN_MSG("unsupported operation type %d", op->type);
	}
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute a rule's operations that apply at a given execution       *
 *          phase against an event context                                    *
 *                                                                            *
 * Parameters: rule         - [IN] rule whose operations are executed         *
 *             execute_when - [IN] execution phase                            *
 *             ctx          - [IN/OUT] event context                          *
 *             event        - [IN/OUT] resolved mutable event, resolved and   *
 *                            reused across operations                        *
 *             tasks        - [OUT] vector to append tasks created by         *
 *                            operations to                                   *
 *                                                                            *
 * Return value: bitmask of the types of operations successfully executed     *
 *                                                                            *
 ******************************************************************************/
zbx_uint64_t	cep_rule_event_execute_ops(const zbx_cep_rule_t *rule, int execute_when, zbx_cep_event_context_t *ctx,
		zbx_cep_event_t **event, zbx_vector_mw_task_ptr_t *tasks)
{
	zbx_cep_acknowledge_t	ack = {0};
	zbx_uint64_t		opmask = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() ruleid:" ZBX_FS_UI64 " operations:%d", __func__, rule->ruleid,
			rule->operations.values_num);

	for (int i = 0; i < rule->operations.values_num; i++)
	{
		if (rule->operations.values[i].execute_when == execute_when)
		{
			if (SUCCEED == cep_operation_event_execute(&rule->operations.values[i], execute_when,
					rule->ruleid, ctx, &ack, tasks, event))
			{
				opmask |= CEP_FLAG(rule->operations.values[i].type);
			}
		}
	}

	if (SUCCEED == cep_acknowledge_is_set(&ack))
	{
		/* acknowledge data is moved to the created task */
		zbx_mw_task_t	*t = cep_create_task_acknowledge(&ack, rule->ruleid, cep_event_context_eventid(ctx));

		zbx_vector_mw_task_ptr_append(tasks, t);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() opmask:%x", __func__, opmask);

	return opmask;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check whether a rule has a matching close-window operation at a   *
 *          given execution phase                                             *
 *                                                                            *
 * Parameters: rule         - [IN] rule whose operations are checked          *
 *             execute_when - [IN] execution phase                            *
 *             ctx          - [IN] event context                              *
 *                                                                            *
 * Return value: close window operation flag if window must be closed, 0      *
 *               otherwise                                                    *
 *                                                                            *
 ******************************************************************************/
zbx_uint64_t	cep_rule_event_execute_close_window(const zbx_cep_rule_t *rule, int execute_when,
		zbx_cep_event_context_t *ctx)
{
	zbx_uint64_t	opmask = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() ruleid:" ZBX_FS_UI64 " operations:%d", __func__, rule->ruleid,
			rule->operations.values_num);

	for (int i = 0; i < rule->operations.values_num; i++)
	{
		if (rule->operations.values[i].execute_when == execute_when)
		{
			if (SUCCEED != cep_operation_match_event(&rule->operations.values[i], ctx))
				continue;

			if (ZBX_CEP_OP_CLOSE_WINDOW == rule->operations.values[i].type)
			{
				opmask = CEP_FLAG(ZBX_CEP_OP_CLOSE_WINDOW);
				break;
			}
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() opmask:%x", __func__, opmask);

	return opmask;
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute a rule's operations against an event context, syncing     *
 *          the event if it was modified                                      *
 *                                                                            *
 * Parameters: rule         - [IN] rule whose operations are executed         *
 *             ctx          - [IN/OUT] event context                          *
 *             execute_when - [IN] execution phase                            *
 *             tasks        - [OUT] vector to append tasks created by the     *
 *                            operations to                                   *
 *                                                                            *
 * Return value: bitmask of the types of operations successfully executed     *
 *                                                                            *
 * Comments: If an operation resolved a mutable event, the event handle is    *
 *           updated to reference it and a sync task is created.              *
 *                                                                            *
 ******************************************************************************/
zbx_uint64_t	cep_rule_event_context_execute_ops(const zbx_cep_rule_t *rule, zbx_cep_event_context_t *ctx,
		int execute_when, zbx_vector_mw_task_ptr_t *tasks)
{
	zbx_cep_event_t	*event = NULL;
	zbx_uint64_t	opmask = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() ruleid:" ZBX_FS_UI64, __func__, rule->ruleid);

	opmask = cep_rule_event_execute_ops(rule, execute_when, ctx, &event, tasks);

	if (NULL != event)
	{
		zbx_cep_t	*cep;

		cep_cache_acquire(&cep);
		cep_event_handle_set(ctx->hevent, event);
		cep_cache_release(&cep);

		zbx_mw_task_t	*t = cep_create_task_sync_event(ctx->hevent, ctx->sync_flags);

		zbx_vector_mw_task_ptr_append(tasks, t);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() opmask:%x", __func__, opmask);

	return opmask;
}

/******************************************************************************
 *                                                                            *
 * Purpose: add an event to the windows of matched rules                      *
 *                                                                            *
 * Parameters: ctx               - [IN/OUT] event context                     *
 *             matched_rules     - [IN] array of matched rules                *
 *             matched_rules_num - [IN] number of matched rules               *
 *             tasks             - [OUT] vector to append tasks created       *
 *                                 while processing the event to              *
 *                                                                            *
 * Comments: Rules without a window are skipped.                              *
 *                                                                            *
 ******************************************************************************/
void	cep_event_add_to_rules(zbx_cep_event_context_t *ctx, const zbx_cep_rule_t **matched_rules,
		int matched_rules_num, zbx_vector_mw_task_ptr_t *tasks)
{
	for (int i = 0; i < matched_rules_num; i++)
	{
		const zbx_cep_rule_t	*rule = matched_rules[i];

		if (NULL == rule->window)
			continue;

		switch (rule->window->type)
		{
			case ZBX_CEP_WINDOW_SIMPLE:
			case ZBX_CEP_WINDOW_CORRELATION:
				cep_window_sliding_process_event(rule, ctx, tasks);
				break;
			case ZBX_CEP_WINDOW_CAUSAL:
				cep_window_causal_process_event(rule, ctx, tasks);
				break;
			case ZBX_CEP_WINDOW_PATTERN:
				cep_window_js_process_event(rule, ctx, tasks);
				break;
		}
	}
}

