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

#include "dbconfig_cep.h"
#include "dbsync.h"
#include "zbxalgo.h"
#include "zbxcacheconfig.h"
#include "dbconfig_local.h"
#include "dbconfig.h"
#include "zbxcommon.h"
#include "zbxdb.h"
#include "zbxdbhigh.h"
#include "zbxlog.h"
#include "zbxnum.h"
#include "zbxstr.h"

ZBX_PTR_VECTOR_IMPL(cep_rule_ptr, zbx_cep_rule_t *)
ZBX_VECTOR_IMPL(cep_condition, zbx_cep_condition_t)
ZBX_VECTOR_IMPL(cep_window_condition, zbx_cep_window_condition_t)
ZBX_VECTOR_IMPL(cep_operation, zbx_cep_operation_t)
ZBX_VECTOR_IMPL(cep_operation_tag, zbx_cep_operation_tag_t)

typedef struct
{
	zbx_uint64_t	objectid;
	zbx_uint64_t	parentid;
}
zbx_object_rel_t;

static void	cep_operation_tag_clear(zbx_cep_operation_tag_t *tag)
{
	zbx_free(tag->tag);
	zbx_free(tag->value);
}

static int	cep_operation_tag_compare_by_id(const void *a1, const void *a2)
{
	const zbx_cep_operation_tag_t *ot1 = (const zbx_cep_operation_tag_t *)a1;
	const zbx_cep_operation_tag_t *ot2 = (const zbx_cep_operation_tag_t *)a2;

	ZBX_RETURN_IF_NOT_EQUAL(ot1->cep_operation_tagid, ot2->cep_operation_tagid);

	return 0;
}

static void	cep_operation_clear_args(zbx_cep_operation_t *operation)
{
	switch (operation->type)
	{
		case ZBX_CEP_OP_SET_NAME:
			zbx_free(operation->args.set_name.name);
			break;
		case ZBX_CEP_OP_ADD_TAG:
			zbx_free(operation->args.add_tag.tag);
			zbx_free(operation->args.add_tag.value);
			break;
		case ZBX_CEP_OP_SET_TAG:
			zbx_free(operation->args.set_tag.tag);
			zbx_free(operation->args.set_tag.value);
			break;
		case ZBX_CEP_OP_SET_TAG_VALUE:
			zbx_free(operation->args.set_tag_value.tag);
			zbx_free(operation->args.set_tag_value.value);
			break;
		case ZBX_CEP_OP_INCREASE_TAG_VALUE:
			zbx_free(operation->args.increase_tag_value.tag);
			break;
		case ZBX_CEP_OP_DECREASE_TAG_VALUE:
			zbx_free(operation->args.decrease_tag_value.tag);
			break;
		case ZBX_CEP_OP_RENAME_TAG:
			zbx_free(operation->args.rename_tag.old_tag);
			zbx_free(operation->args.rename_tag.new_tag);
			break;
		case ZBX_CEP_OP_REMOVE_TAG:
			zbx_free(operation->args.remove_tag.tag);
			break;
	}
}

static void	cep_operation_clear(zbx_cep_operation_t *operation)
{
	cep_operation_clear_args(operation);

	for (int i = 0; i < operation->tags.values_num; i++)
		cep_operation_tag_clear(&operation->tags.values[i]);
	zbx_vector_cep_operation_tag_destroy(&operation->tags);
}

static int	cep_operation_compare_by_id(const void *a1, const void *a2)
{
	const zbx_cep_operation_t *o1 = (const zbx_cep_operation_t *)a1;
	const zbx_cep_operation_t *o2 = (const zbx_cep_operation_t *)a2;

	ZBX_RETURN_IF_NOT_EQUAL(o1->operationid, o2->operationid);

	return 0;
}

static int	cep_operation_compare_by_sortorder(const void *a1, const void *a2)
{
	const zbx_cep_operation_t *o1 = (const zbx_cep_operation_t *)a1;
	const zbx_cep_operation_t *o2 = (const zbx_cep_operation_t *)a2;

	return o1->sortorder - o2->sortorder;
}

static void	cep_condition_clear_args(zbx_cep_condition_t *condition)
{
	switch (condition->type)
	{
		case ZBX_CEP_CONDITION_EVENT_NAME:
			zbx_free(condition->args.event_name.name);
			break;
		case ZBX_CEP_CONDITION_TAG_NAME:
			zbx_free(condition->args.tag_name.tag);
			break;
		case ZBX_CEP_CONDITION_TAG_VALUE:
			zbx_free(condition->args.tag_value.tag);
			zbx_free(condition->args.tag_value.value);
			break;
		case ZBX_CEP_CONDITION_SEVERITY:
			break;
		case ZBX_CEP_CONDITION_HOST:
			zbx_free(condition->args.host.name);
			break;
		case ZBX_CEP_CONDITION_HOST_GROUP:
			zbx_free(condition->args.host_group.name);
			break;
		case ZBX_CEP_CONDITION_TIME_PERIOD:
			zbx_free(condition->args.time_period.period);
			break;
		default:
			break;
	}
}

static void	cep_condition_clear(zbx_cep_condition_t *condition)
{
	cep_condition_clear_args(condition);
}

static void	cep_window_condition_clear_args(zbx_cep_window_condition_t *condition)
{
	switch (condition->type)
	{
		case ZBX_CEP_WINDOW_CONDITION_TAG_PAIR:
			zbx_free(condition->args.tag_pair.old_tag);
			zbx_free(condition->args.tag_pair.new_tag);
			break;
		case ZBX_CEP_WINDOW_CONDITION_OLD_TAG:
			zbx_free(condition->args.old_tag.tag);
			break;
		case ZBX_CEP_WINDOW_CONDITION_OLD_TAG_VALUE:
			zbx_free(condition->args.old_tag_value.tag);
			zbx_free(condition->args.old_tag_value.value);
			break;
	}
}

static void	cep_window_condition_clear(zbx_cep_window_condition_t *condition)
{
	cep_window_condition_clear_args(condition);
}

static void	cep_window_free(zbx_cep_window_t *window)
{
	for (int i = 0; i < window->conditions.values_num; i++)
		cep_window_condition_clear(&window->conditions.values[i]);
	zbx_vector_cep_window_condition_destroy(&window->conditions);

	zbx_free(window->capacity);
	zbx_free(window->duration);
	zbx_free(window->event_count_tag);
	zbx_free(window->formula);
	zbx_free(window->script);
	zbx_free(window->group_tag);
	zbx_free(window);
}

static zbx_cep_window_t	*cep_window_create(void)
{
	zbx_cep_window_t	*window;

	window = (zbx_cep_window_t *)zbx_calloc(NULL, 1, sizeof(zbx_cep_window_t));
	zbx_vector_cep_window_condition_create(&window->conditions);

	return window;
}

static zbx_cep_window_t	*cep_window_clone(const zbx_cep_window_t *window)
{
	zbx_cep_window_t	*clone;

	if (NULL == window)
		return NULL;

	clone = cep_window_create();
	clone->type = window->type;
	clone->evaltype = window->evaltype;
	clone->group_by = window->group_by;
	clone->capacity = zbx_strdup(NULL, window->capacity);
	clone->duration = zbx_strdup(NULL, window->duration);
	clone->event_count_tag = zbx_strdup(NULL, window->event_count_tag);
	clone->formula = zbx_strdup(NULL, window->formula);
	clone->script = zbx_strdup(NULL, window->script);
	clone->group_tag = zbx_strdup(NULL, window->group_tag);

	zbx_vector_cep_window_condition_append_array(&clone->conditions, window->conditions.values,
			window->conditions.values_num);

	for (int i = 0; i < clone->conditions.values_num; i++)
	{
		zbx_cep_window_condition_t	*condition = &clone->conditions.values[i];

		switch (condition->type)
		{
			case ZBX_CEP_WINDOW_CONDITION_TAG_PAIR:
				condition->args.tag_pair.old_tag = zbx_strdup(NULL, condition->args.tag_pair.old_tag);
				condition->args.tag_pair.new_tag = zbx_strdup(NULL, condition->args.tag_pair.new_tag);
				break;
			case ZBX_CEP_WINDOW_CONDITION_OLD_TAG:
				condition->args.old_tag.tag = zbx_strdup(NULL, condition->args.old_tag.tag);
				break;
			case ZBX_CEP_WINDOW_CONDITION_OLD_TAG_VALUE:
				condition->args.old_tag_value.tag = zbx_strdup(NULL, condition->args.old_tag_value.tag);
				condition->args.old_tag_value.value = zbx_strdup(NULL,
						condition->args.old_tag_value.value);
				break;
		}
	}

	return clone;
}

static int	cep_window_condition_compare_by_id(const void *a1, const void *a2)
{
	const zbx_cep_window_condition_t *c1 = (const zbx_cep_window_condition_t *)a1;
	const zbx_cep_window_condition_t *c2 = (const zbx_cep_window_condition_t *)a2;

	ZBX_RETURN_IF_NOT_EQUAL(c1->conditionid, c2->conditionid);

	return 0;
}

static zbx_cep_rule_t	*cep_rule_create(zbx_uint64_t ruleid)
{
	zbx_cep_rule_t	*rule;

	rule = (zbx_cep_rule_t *)zbx_calloc(NULL, 1, sizeof(zbx_cep_rule_t));
	rule->ruleid = ruleid;
	rule->refcount = 1;

	zbx_vector_cep_condition_create(&rule->conditions);
	zbx_vector_cep_operation_create(&rule->operations);

	return rule;
}

static zbx_cep_rule_t	*cep_rule_addref(zbx_cep_rule_t *rule)
{
	atomic_fetch_add(&rule->refcount, 1);

	return rule;
}

static void	cep_rule_release(zbx_cep_rule_t *rule)
{
	if (1 != atomic_fetch_sub(&rule->refcount, 1))
		return;

	for (int i = 0; i < rule->conditions.values_num; i++)
		cep_condition_clear(&rule->conditions.values[i]);
	zbx_vector_cep_condition_destroy(&rule->conditions);

	for (int i = 0; i < rule->operations.values_num; i++)
		cep_operation_clear(&rule->operations.values[i]);
	zbx_vector_cep_operation_destroy(&rule->operations);

	if (NULL != rule->window)
		cep_window_free(rule->window);

	zbx_free(rule->formula);

	zbx_free(rule);
}

static void	cep_rule_copy_conditions(zbx_vector_cep_condition_t *dst, const zbx_vector_cep_condition_t *src)
{
	zbx_vector_cep_condition_append_array(dst, src->values, src->values_num);

	for (int i = 0; i < dst->values_num; i++)
	{
		zbx_cep_condition_t	*cond = &dst->values[i];

		switch (cond->type)
		{
			case ZBX_CEP_CONDITION_EVENT_NAME:
				cond->args.event_name.name = zbx_strdup(NULL, cond->args.event_name.name);
				break;
			case ZBX_CEP_CONDITION_TAG_NAME:
				cond->args.tag_name.tag = zbx_strdup(NULL, cond->args.tag_name.tag);
				break;
			case ZBX_CEP_CONDITION_TAG_VALUE:
				cond->args.tag_value.tag = zbx_strdup(NULL, cond->args.tag_value.tag);
				cond->args.tag_value.value = zbx_strdup(NULL, cond->args.tag_value.value);
				break;
			case ZBX_CEP_CONDITION_SEVERITY:
				break;
			case ZBX_CEP_CONDITION_HOST:
				cond->args.host.name = zbx_strdup(NULL, cond->args.host.name);
				break;
			case ZBX_CEP_CONDITION_HOST_GROUP:
				cond->args.host_group.name = zbx_strdup(NULL, cond->args.host_group.name);
				break;
			case ZBX_CEP_CONDITION_TIME_PERIOD:
				cond->args.time_period.period = zbx_strdup(NULL, cond->args.time_period.period);
				break;
		}
	}
}

static void	cep_operation_copy_tags(zbx_vector_cep_operation_tag_t *dst,
		const zbx_vector_cep_operation_tag_t *src)
{
	zbx_vector_cep_operation_tag_create(dst);
	zbx_vector_cep_operation_tag_append_array(dst, src->values, src->values_num);

	for (int i = 0; i < dst->values_num; i++)
	{
		zbx_cep_operation_tag_t	*tag = &dst->values[i];

		tag->tag = zbx_strdup(NULL, tag->tag);
		tag->value = zbx_strdup(NULL, tag->value);
	}
}

static void	cep_rule_copy_operations(zbx_vector_cep_operation_t *dst, const zbx_vector_cep_operation_t *src)
{
	zbx_vector_cep_operation_append_array(dst, src->values, src->values_num);

	for (int i = 0; i < dst->values_num; i++)
	{
		zbx_cep_operation_t	*op = &dst->values[i];

		switch (op->type)
		{
			case ZBX_CEP_OP_SET_NAME:
				op->args.set_name.name = zbx_strdup(NULL, op->args.set_name.name);
				break;
			case ZBX_CEP_OP_ADD_TAG:
				op->args.add_tag.tag = zbx_strdup(NULL, op->args.add_tag.tag);
				op->args.add_tag.value = zbx_strdup(NULL, op->args.add_tag.value);
				break;
			case ZBX_CEP_OP_SET_TAG:
				op->args.set_tag.tag = zbx_strdup(NULL, op->args.set_tag.tag);
				op->args.set_tag.value = zbx_strdup(NULL, op->args.set_tag.value);
				break;
			case ZBX_CEP_OP_SET_TAG_VALUE:
				op->args.set_tag_value.tag = zbx_strdup(NULL, op->args.set_tag_value.tag);
				op->args.set_tag_value.value = zbx_strdup(NULL, op->args.set_tag_value.value);
				break;
			case ZBX_CEP_OP_INCREASE_TAG_VALUE:
				op->args.increase_tag_value.tag = zbx_strdup(NULL, op->args.increase_tag_value.tag);
				break;
			case ZBX_CEP_OP_DECREASE_TAG_VALUE:
				op->args.decrease_tag_value.tag = zbx_strdup(NULL, op->args.decrease_tag_value.tag);
				break;
			case ZBX_CEP_OP_RENAME_TAG:
				op->args.rename_tag.old_tag = zbx_strdup(NULL, op->args.rename_tag.old_tag);
				op->args.rename_tag.new_tag = zbx_strdup(NULL, op->args.rename_tag.new_tag);
				break;
			case ZBX_CEP_OP_REMOVE_TAG:
				op->args.remove_tag.tag = zbx_strdup(NULL, op->args.remove_tag.tag);
				break;
		}

		cep_operation_copy_tags(&op->tags, &src->values[i].tags);
	}
}

static zbx_cep_rule_t	*cep_rule_clone(const zbx_cep_rule_t *rule)
{
	zbx_cep_rule_t	*clone = cep_rule_create(rule->ruleid);

	clone->evaltype = rule->evaltype;
	clone->sortorder = rule->sortorder;
	clone->status = rule->status;
	clone->stop = rule->stop;
	clone->formula = zbx_strdup(NULL, rule->formula);

	cep_rule_copy_conditions(&clone->conditions, &rule->conditions);
	cep_rule_copy_operations(&clone->operations, &rule->operations);
	clone->window = cep_window_clone(rule->window);

	return clone;
}

static zbx_cep_rule_t	*cep_acquire_rule(zbx_cep_rule_ref_t *ref, zbx_uint64_t revision)
{
	if (NULL == ref->rule)
	{
		ref->rule = cep_rule_create(ref->ruleid);
	}
	else if (1 != atomic_load(&ref->rule->refcount))
	{
		zbx_cep_rule_t	*rule = cep_rule_clone(ref->rule);

		cep_rule_release(ref->rule);
		ref->rule = rule;
	}

	ref->rule->revision = revision;

	return ref->rule;
}

static zbx_cep_rule_t	*cep_acquire_rule_by_id(zbx_cep_config_t *cep_config, zbx_uint64_t ruleid,
		zbx_uint64_t revision)
{
	zbx_cep_rule_ref_t	ref_local, *ref;

	ref_local.ruleid = ruleid;
	if (NULL == (ref = (zbx_cep_rule_ref_t *)zbx_hashset_search(&cep_config->rules, &ref_local)))
		return NULL;

	return cep_acquire_rule(ref, revision);
}

static void	cep_rule_ref_clear(void *a)
{
	zbx_cep_rule_ref_t	*ref = (zbx_cep_rule_ref_t *)a;

	cep_rule_release(ref->rule);
}

static void	cep_config_handle_release(zbx_cep_config_handle_t handle)
{
	if (1 != atomic_fetch_sub(&handle->refcount, 1))
		return;

	for (int i = 0; i < handle->rules.values_num; i++)
		cep_rule_release(handle->rules.values[i]);

	zbx_vector_cep_rule_ptr_destroy(&handle->rules);

	zbx_free(handle);
}

static int	cep_rule_compare_by_sortorder(const void *a1, const void *a2)
{
	const zbx_cep_rule_t	*r1 = *(const zbx_cep_rule_t * const *)a1;
	const zbx_cep_rule_t	*r2 = *(const zbx_cep_rule_t * const *)a2;

	return r1->sortorder - r2->sortorder;
}

static zbx_cep_config_handle_t	cep_config_handle_create(zbx_cep_config_t *cep_config, zbx_uint64_t revision)
{
	zbx_cep_config_handle_t	handle;
	zbx_hashset_iter_t	iter;
	zbx_cep_rule_ref_t	*ref;

	handle = (zbx_cep_config_handle_t)zbx_malloc(NULL, sizeof(struct zbx_cep_config_handle));

	zbx_vector_cep_rule_ptr_create(&handle->rules);
	zbx_vector_cep_rule_ptr_reserve(&handle->rules, cep_config->rules.num_data);
	handle->refcount = 1;
	handle->revision = revision;

	zbx_hashset_iter_reset(&cep_config->rules, &iter);
	while (NULL != (ref = (zbx_cep_rule_ref_t *)zbx_hashset_iter_next(&iter)))
	{
		if (CEP_RULE_ENABLED != ref->rule->status)
			continue;

		zbx_vector_cep_rule_ptr_append(&handle->rules, cep_rule_addref(ref->rule));
	}

	zbx_vector_cep_rule_ptr_sort(&handle->rules, cep_rule_compare_by_sortorder);

	return handle;
}

zbx_cep_config_t	*cep_config_create(void)
{
	zbx_cep_config_t	*cep_config;

	cep_config = (zbx_cep_config_t *)zbx_calloc(NULL, 1, sizeof(zbx_cep_config_t));

	if (0 != pthread_mutex_init(&cep_config->lock, NULL))
	{
		zbx_free(cep_config);
		THIS_SHOULD_NEVER_HAPPEN_MSG("failed to initialize CEP cache mutex");
		exit(EXIT_FAILURE);
	}

	zbx_hashset_create_ext(&cep_config->rules, 0, ZBX_DEFAULT_ID_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC, cep_rule_ref_clear, ZBX_DEFAULT_MEM_MALLOC_FUNC,
			ZBX_DEFAULT_MEM_REALLOC_FUNC, ZBX_DEFAULT_MEM_FREE_FUNC);

	zbx_hashset_create(&cep_config->condition_rel, 0, ZBX_DEFAULT_ID_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_hashset_create(&cep_config->window_condition_rel, 0, ZBX_DEFAULT_ID_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_hashset_create(&cep_config->operation_rel, 0, ZBX_DEFAULT_ID_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_hashset_create(&cep_config->operation_tag_rel, 0, ZBX_DEFAULT_ID_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	return cep_config;
}

void	cep_config_destroy(zbx_cep_config_t *cep_config)
{
	zbx_hashset_destroy(&cep_config->rules);
	zbx_hashset_destroy(&cep_config->condition_rel);
	zbx_hashset_destroy(&cep_config->window_condition_rel);
	zbx_hashset_destroy(&cep_config->operation_rel);
	zbx_hashset_destroy(&cep_config->operation_tag_rel);

	if (NULL != cep_config->handle)
		cep_config_handle_release(cep_config->handle);

	pthread_mutex_destroy(&cep_config->lock);

	zbx_free(cep_config);
}

static void	cep_sync_rules(zbx_cep_config_t *cep_config, zbx_dbsync_t *sync, zbx_uint64_t revision,
		zbx_vector_cep_rule_ptr_t *rules)
{
	char			**row;
	zbx_uint64_t		rowid;
	unsigned char		tag;
	int			ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_dcsync_sync_start(sync, dbconfig_used_size());

	while (SUCCEED == (ret = zbx_dbsync_next(sync, &rowid, &row, &tag)))
	{
		zbx_cep_rule_ref_t	ref_local, *ref;
		zbx_cep_rule_t		*rule;

		/* removed rows will be always added at the end */
		if (ZBX_DBSYNC_ROW_REMOVE == tag)
			break;

		ZBX_STR2UINT64(ref_local.ruleid, row[0]);
		ref_local.rule = NULL;

		ref = (zbx_cep_rule_ref_t *)zbx_hashset_insert(&cep_config->rules, &ref_local, sizeof(ref_local));

		rule = cep_acquire_rule(ref, revision);

		if (NULL == rule->formula || 0 != strcmp(rule->formula, row[1]))
			rule->formula = zbx_strdup(rule->formula, row[1]);

		rule->evaltype = atoi(row[2]);
		rule->status = atoi(row[3]);
		rule->stop = atoi(row[4]);
		rule->sortorder = atoi(row[5]);

		if (ZBX_DBSYNC_ROW_UPDATE == tag && NULL != rules)
			zbx_vector_cep_rule_ptr_append(rules, rule);
	}

	/* remove deleted cep rules */
	for (; SUCCEED == ret; ret = zbx_dbsync_next(sync, &rowid, &row, &tag))
	{
		zbx_cep_rule_ref_t	ref_local, *ref;

		ref_local.ruleid = rowid;

		if (NULL == (ref = (zbx_cep_rule_ref_t *)zbx_hashset_search(&cep_config->rules, &ref_local)))
			continue;

		zbx_hashset_remove_direct(&cep_config->rules, ref);
	}

	if (0 != sync->add_num + sync->update_num + sync->remove_num)
		cep_config->revision = revision;

	zbx_dcsync_sync_end(sync, dbconfig_used_size());

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static int	cep_condition_compare_by_id(const void *a1, const void *a2)
{
	const zbx_cep_condition_t *c1 = (const zbx_cep_condition_t *)a1;
	const zbx_cep_condition_t *c2 = (const zbx_cep_condition_t *)a2;

	ZBX_RETURN_IF_NOT_EQUAL(c1->conditionid, c2->conditionid);

	return 0;
}

static int	cep_condition_compare_by_type(const void *a1, const void *a2)
{
	const zbx_cep_condition_t *c1 = (const zbx_cep_condition_t *)a1;
	const zbx_cep_condition_t *c2 = (const zbx_cep_condition_t *)a2;

	return c1->type - c2->type;
}

static zbx_cep_condition_t	*cep_acquire_condition(zbx_cep_config_t *cep_config, zbx_uint64_t ruleid,
		zbx_uint64_t conditionid, zbx_uint64_t revision, zbx_vector_cep_rule_ptr_t *rules)
{
	zbx_cep_rule_t		*rule;
	int			index;
	zbx_cep_condition_t	condition_local = {.conditionid = conditionid};

	if (NULL == (rule = cep_acquire_rule_by_id(cep_config, ruleid, revision)))
		return NULL;

	if (FAIL == (index = zbx_vector_cep_condition_search(&rule->conditions, condition_local,
			cep_condition_compare_by_id)))
	{
		zbx_object_rel_t	rel_local = {.objectid = conditionid, .parentid = ruleid};

		index = rule->conditions.values_num;
		zbx_vector_cep_condition_append(&rule->conditions, condition_local);

		zbx_hashset_insert(&cep_config->condition_rel, &rel_local, sizeof(rel_local));
	}

	if (NULL != rules)
		zbx_vector_cep_rule_ptr_append(rules, rule);

	return &rule->conditions.values[index];
}

static void	cep_remove_condition(zbx_cep_config_t *cep_config, zbx_uint64_t conditionid, zbx_uint64_t revision,
		zbx_vector_cep_rule_ptr_t *rules)
{
	zbx_cep_rule_ref_t	ref_local, *ref;
	zbx_cep_rule_t		*rule;
	int			index;
	zbx_cep_condition_t	condition_local = {.conditionid = conditionid};
	zbx_object_rel_t	rel_local = {.objectid = conditionid}, *rel;

	if (NULL == (rel = (zbx_object_rel_t *)zbx_hashset_search(&cep_config->condition_rel, &rel_local)))
		return;

	ref_local.ruleid = rel->parentid;
	if (NULL != (ref = (zbx_cep_rule_ref_t *)zbx_hashset_search(&cep_config->rules, &ref_local)))
	{
		rule = cep_acquire_rule(ref, revision);

		if (FAIL != (index = zbx_vector_cep_condition_search(&rule->conditions, condition_local,
				cep_condition_compare_by_id)))
		{
			cep_condition_clear(&rule->conditions.values[index]);
			zbx_vector_cep_condition_remove(&rule->conditions, index);
		}

		if (NULL != rules)
			zbx_vector_cep_rule_ptr_append(rules, rule);
	}

	zbx_hashset_remove_direct(&cep_config->condition_rel, rel);
}

static void	cep_rule_update_formula(zbx_cep_rule_t *rule, char **str, size_t *str_alloc)
{
	const char	*op = NULL;
	size_t		str_offset = 0;

	if (ZBX_CONDITION_EVAL_TYPE_EXPRESSION == rule->evaltype || 0 == rule->conditions.values_num)
		return;

	switch (rule->evaltype)
	{
		case ZBX_CONDITION_EVAL_TYPE_OR:
			op = " or ";
			break;
		case ZBX_CONDITION_EVAL_TYPE_AND:
			op = " and ";
			break;
	}

	if (NULL != op)
	{
		for (int i = 0; i < rule->conditions.values_num; i++)
		{
			if (0 != str_offset)
				zbx_strcpy_alloc(str, str_alloc, &str_offset, op);

			zbx_snprintf_alloc(str, str_alloc, &str_offset, "{" ZBX_FS_UI64 "}",
					rule->conditions.values[i].conditionid);
		}
		rule->formula = zbx_strdup(rule->formula, *str);

		return;
	}

	/* convert and/or evaluation type */

	zbx_vector_cep_condition_sort(&rule->conditions, cep_condition_compare_by_type);

	for (int i = 0, j = 0; i < rule->conditions.values_num; i = j)
	{
		op = "";

		if (0 != str_offset)
			zbx_strcpy_alloc(str, str_alloc, &str_offset, " and ");

		zbx_chrcpy_alloc(str, str_alloc, &str_offset, '(');

		for (j = i; j < rule->conditions.values_num &&
				rule->conditions.values[j].type == rule->conditions.values[i].type; j++)
		{
			zbx_snprintf_alloc(str, str_alloc, &str_offset, "%s{" ZBX_FS_UI64 "}",
					op, rule->conditions.values[j].conditionid);

			op = " or ";
		}

		zbx_chrcpy_alloc(str, str_alloc, &str_offset, ')');
	}

	rule->formula = zbx_strdup(rule->formula, *str);
}

static void	cep_sync_conditions(zbx_cep_config_t *cep_config, zbx_dbsync_t *sync, zbx_uint64_t revision,
	zbx_vector_cep_rule_ptr_t *rules)
{
	char		**row;
	zbx_uint64_t	rowid;
	unsigned char	tag;
	int		ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_dcsync_sync_start(sync, dbconfig_used_size());

	while (SUCCEED == (ret = zbx_dbsync_next(sync, &rowid, &row, &tag)))
	{
		zbx_uint64_t	conditionid, ruleid;
		int		condition_type;

		zbx_cep_condition_t	*condition;

		/* removed rows will be always added at the end */
		if (ZBX_DBSYNC_ROW_REMOVE == tag)
			break;

		ZBX_STR2UINT64(conditionid, row[0]);
		ZBX_STR2UINT64(ruleid, row[1]);

		if (NULL == (condition = cep_acquire_condition(cep_config, ruleid, conditionid, revision, rules)))
			continue;

		if (condition->type != (condition_type = atoi(row[2])))
		{
			cep_condition_clear_args(condition);
			condition->type = condition_type;
		}

		condition->operator = atoi(row[3]);

		switch (condition->type)
		{
			case ZBX_CEP_CONDITION_EVENT_NAME:
				ZBX_DBROW2STR(condition->args.event_name.name, row[4]);
				break;
			case ZBX_CEP_CONDITION_TAG_NAME:
				ZBX_DBROW2STR(condition->args.tag_name.tag, row[5]);
				break;
			case ZBX_CEP_CONDITION_TAG_VALUE:
				ZBX_DBROW2STR(condition->args.tag_value.tag, row[5]);
				ZBX_DBROW2STR(condition->args.tag_value.value, row[6]);
				break;
			case ZBX_CEP_CONDITION_SEVERITY:
				condition->args.severity.level = atoi(row[10]);
				break;
			case ZBX_CEP_CONDITION_HOST:
				ZBX_DBROW2STR(condition->args.host.name, row[7]);
				break;
			case ZBX_CEP_CONDITION_HOST_GROUP:
				ZBX_DBROW2STR(condition->args.host_group.name, row[8]);
				break;
			case ZBX_CEP_CONDITION_TIME_PERIOD:
				ZBX_DBROW2STR(condition->args.time_period.period, row[9]);
				break;
		}
	}

	/* remove deleted cep conditions */
	for (; SUCCEED == ret; ret = zbx_dbsync_next(sync, &rowid, &row, &tag))
	{
		cep_remove_condition(cep_config, rowid, revision, rules);
	}

	if (0 != sync->add_num + sync->update_num + sync->remove_num)
		cep_config->revision = revision;

	zbx_dcsync_sync_end(sync, dbconfig_used_size());

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	cep_condition_dump(zbx_cep_condition_t *condition)
{
	char	*args = NULL;
	size_t	args_alloc = 0, args_offset = 0;

	switch (condition->type)
	{
		case ZBX_CEP_CONDITION_EVENT_NAME:
			zbx_snprintf_alloc(&args, &args_alloc, &args_offset, "event_name:%s",
					condition->args.event_name.name);
			break;
		case ZBX_CEP_CONDITION_TAG_NAME:
			zbx_snprintf_alloc(&args, &args_alloc, &args_offset, "tag:%s",
					condition->args.tag_name.tag);
			break;
		case ZBX_CEP_CONDITION_TAG_VALUE:
			zbx_snprintf_alloc(&args, &args_alloc, &args_offset, "tag:%s value:%s",
					condition->args.tag_value.tag, condition->args.tag_value.value);
			break;
		case ZBX_CEP_CONDITION_SEVERITY:
			zbx_snprintf_alloc(&args, &args_alloc, &args_offset, "severity:%d",
					condition->args.severity.level);
			break;
		case ZBX_CEP_CONDITION_HOST:
			zbx_snprintf_alloc(&args, &args_alloc, &args_offset, "host:%s",
					condition->args.host.name);
			break;
		case ZBX_CEP_CONDITION_HOST_GROUP:
			zbx_snprintf_alloc(&args, &args_alloc, &args_offset, "host_group:%s",
					condition->args.host_group.name);
			break;
		case ZBX_CEP_CONDITION_TIME_PERIOD:
			zbx_snprintf_alloc(&args, &args_alloc, &args_offset, "period:%s",
					condition->args.time_period.period);
			break;
	}

	zabbix_log(LOG_LEVEL_TRACE, "    conditionid:" ZBX_FS_UI64 " type:%d operator:%d %s",
			condition->conditionid, condition->type, condition->operator, args);

	zbx_free(args);
}

static void	cep_window_condition_dump(zbx_cep_window_condition_t *condition)
{
	char	*args = NULL;
	size_t	args_alloc = 0, args_offset = 0;

	switch (condition->type)
	{
		case ZBX_CEP_WINDOW_CONDITION_TAG_PAIR:
			zbx_snprintf_alloc(&args, &args_alloc, &args_offset, "old_tag:%s new_tag:%s",
					condition->args.tag_pair.old_tag, condition->args.tag_pair.new_tag);
			break;
		case ZBX_CEP_WINDOW_CONDITION_OLD_TAG:
			zbx_snprintf_alloc(&args, &args_alloc, &args_offset, "tag:%s",
					condition->args.old_tag.tag);
			break;
		case ZBX_CEP_WINDOW_CONDITION_OLD_TAG_VALUE:
			zbx_snprintf_alloc(&args, &args_alloc, &args_offset, "tag:%s value:%s",
					condition->args.old_tag_value.tag, condition->args.old_tag_value.value);
			break;
	}

	zabbix_log(LOG_LEVEL_TRACE, "      conditionid:" ZBX_FS_UI64 " type:%d operator:%d %s",
			condition->conditionid, condition->type, condition->operator, args);

	zbx_free(args);
}

static void	cep_window_dump(zbx_cep_window_t *window)
{
	zabbix_log(LOG_LEVEL_TRACE, "  window type:%d duration:%s capacity:%s evaltype:%d formula:%s group_by:%x"
			" group_tag:%s event_count_tag:%s",
		window->type,  window->duration, window->capacity, window->evaltype, window->formula, window->group_by,
		window->group_tag, window->event_count_tag);

	if ('\0' != *window->script)
		zabbix_log(LOG_LEVEL_TRACE, "    script:\n%s", window->script);

	zabbix_log(LOG_LEVEL_TRACE, "    conditions:");
	for (int i = 0; i < window->conditions.values_num; i++)
		cep_window_condition_dump(&window->conditions.values[i]);
}

static void	cep_operation_dump(zbx_cep_operation_t *operation)
{
	char	*args = NULL;
	size_t	args_alloc = 0, args_offset = 0;

	switch (operation->type)
	{
		case ZBX_CEP_OP_SET_NAME:
			zbx_snprintf_alloc(&args, &args_alloc, &args_offset, "event_name:%s",
					operation->args.set_name.name);
			break;
		case ZBX_CEP_OP_SET_SEVERITY:
			zbx_snprintf_alloc(&args, &args_alloc, &args_offset, "severity:%d",
					operation->args.set_severity.level);
			break;
		case ZBX_CEP_OP_ADD_TAG:
			zbx_snprintf_alloc(&args, &args_alloc, &args_offset, "tag:%s value:%s",
					operation->args.add_tag.tag, operation->args.add_tag.value);
			break;
		case ZBX_CEP_OP_SET_TAG:
			zbx_snprintf_alloc(&args, &args_alloc, &args_offset, "tag:%s value:%s",
					operation->args.set_tag.tag, operation->args.set_tag.value);
			break;
		case ZBX_CEP_OP_SET_TAG_VALUE:
			zbx_snprintf_alloc(&args, &args_alloc, &args_offset, "tag:%s value:%s",
					operation->args.set_tag_value.tag, operation->args.set_tag_value.value);
			break;
		case ZBX_CEP_OP_INCREASE_TAG_VALUE:
			zbx_snprintf_alloc(&args, &args_alloc, &args_offset, "tag:%s",
					operation->args.increase_tag_value.tag);
			break;
		case ZBX_CEP_OP_DECREASE_TAG_VALUE:
			zbx_snprintf_alloc(&args, &args_alloc, &args_offset, "tag:%s",
					operation->args.decrease_tag_value.tag);
			break;
		case ZBX_CEP_OP_RENAME_TAG:
			zbx_snprintf_alloc(&args, &args_alloc, &args_offset, "old_tag:%s new_tag:%s",
					operation->args.rename_tag.old_tag, operation->args.rename_tag.new_tag);
			break;
		case ZBX_CEP_OP_REMOVE_TAG:
			zbx_snprintf_alloc(&args, &args_alloc, &args_offset, "tag:%s",
					operation->args.remove_tag.tag);
			break;
		case ZBX_CEP_OP_CLOSE:
		case ZBX_CEP_OP_DISCARD:
		case ZBX_CEP_OP_INCREASE_SEVERITY:
		case ZBX_CEP_OP_DECREASE_SEVERITY:
		case ZBX_CEP_OP_SUPPRESS:
		case ZBX_CEP_OP_COPY_FIRST:
		case ZBX_CEP_OP_COPY_LAST:
			break;
	}

	zabbix_log(LOG_LEVEL_TRACE, "    operationid:" ZBX_FS_UI64 " type:%d execute_when:%d evaltype:%d"
			" sortorder:%d %s", operation->operationid, operation->type, operation->execute_when,
			operation->evaltype, operation->sortorder, ZBX_NULL2EMPTY_STR(args));

	if (0 != operation->tags.values_num)
	{
		zabbix_log(LOG_LEVEL_TRACE, "      tags:");
		for (int i = 0; i < operation->tags.values_num; i++)
		{
			zabbix_log(LOG_LEVEL_TRACE, "        tagid:" ZBX_FS_UI64 " operator:%d tag:%s value:%s",
					operation->tags.values[i].cep_operation_tagid,
					operation->tags.values[i].operator, operation->tags.values[i].tag,
					operation->tags.values[i].value);
		}
	}

	zbx_free(args);
}

static void	cep_rule_dump(zbx_cep_rule_t *rule)
{
	zabbix_log(LOG_LEVEL_TRACE, "ruleid:" ZBX_FS_UI64 " revision:" ZBX_FS_UI64 " refcount:%u status:%d stop:%d"
			" sortoder:%d evaltype:%d formula:%s",
			rule->ruleid, rule->revision, atomic_load(&rule->refcount),rule->status, rule->stop,
			rule->sortorder, rule->evaltype, rule->formula);

	if (NULL != rule->window)
		cep_window_dump(rule->window);

	zabbix_log(LOG_LEVEL_TRACE, "  conditions:");
	for (int i = 0; i < rule->conditions.values_num; i++)
		cep_condition_dump(&rule->conditions.values[i]);

	zabbix_log(LOG_LEVEL_TRACE, "  operations:");
	for (int i = 0; i < rule->operations.values_num; i++)
		cep_operation_dump(&rule->operations.values[i]);

}

static void	cep_config_dump(void)
{
	zbx_cep_config_t	*cep_config = dc_local()->cep_config;
	zbx_hashset_iter_t	iter;
	zbx_cep_rule_ref_t	*ref;

	// WDN remove
	int	loglevel = zbx_set_log_level(LOG_LEVEL_TRACE);

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);


	zbx_hashset_iter_reset(&cep_config->rules, &iter);
	while (NULL != (ref = (zbx_cep_rule_ref_t *)zbx_hashset_iter_next(&iter)))
		cep_rule_dump(ref->rule);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	// WDN remove
	zbx_set_log_level(loglevel);
}

static void	cep_config_update_handle(zbx_cep_config_t *cep_config, zbx_uint64_t revision)
{
	pthread_mutex_lock(&cep_config->lock);

	if (cep_config->revision == revision)
	{
		if (NULL != cep_config->handle)
			cep_config_handle_release(cep_config->handle);

		cep_config->handle = cep_config_handle_create(cep_config, revision);

		atomic_store(&cep_config->rules_num, cep_config->handle->rules.values_num);
	}

	pthread_mutex_unlock(&cep_config->lock);
}

static void	cep_update_rules(zbx_cep_config_t *cep_config, zbx_vector_cep_rule_ptr_t *rules_cond,
		zbx_vector_cep_rule_ptr_t *rules_op)
{
	char	*str = NULL;
	size_t	str_alloc = 0;

	if (NULL != rules_cond && NULL != rules_op)
	{
		zbx_vector_cep_rule_ptr_sort(rules_cond, ZBX_DEFAULT_PTR_COMPARE_FUNC);
		zbx_vector_cep_rule_ptr_uniq(rules_cond, ZBX_DEFAULT_PTR_COMPARE_FUNC);

		for (int i = 0; i < rules_cond->values_num; i++)
			cep_rule_update_formula(rules_cond->values[i], &str, &str_alloc);

		zbx_vector_cep_rule_ptr_sort(rules_op, ZBX_DEFAULT_PTR_COMPARE_FUNC);
		zbx_vector_cep_rule_ptr_uniq(rules_op, ZBX_DEFAULT_PTR_COMPARE_FUNC);

		for (int i = 0; i < rules_op->values_num; i++)
		{
			zbx_vector_cep_operation_sort(&rules_op->values[i]->operations,
					cep_operation_compare_by_sortorder);
		}
	}
	else
	{
		zbx_hashset_iter_t	iter;
		zbx_cep_rule_ref_t	*ref;

		zbx_hashset_iter_reset(&cep_config->rules, &iter);
		while (NULL != (ref = (zbx_cep_rule_ref_t *)zbx_hashset_iter_next(&iter)))
		{
			cep_rule_update_formula(ref->rule, &str, &str_alloc);
			zbx_vector_cep_operation_sort(&ref->rule->operations, cep_operation_compare_by_sortorder);
		}

	}

	zbx_free(str);
}

static void	cep_sync_windows(zbx_cep_config_t *cep_config, zbx_dbsync_t *sync, zbx_uint64_t revision)
{
	char		**row;
	zbx_uint64_t	rowid;
	unsigned char	tag;
	int		ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_dcsync_sync_start(sync, dbconfig_used_size());

	while (SUCCEED == (ret = zbx_dbsync_next(sync, &rowid, &row, &tag)))
	{
		zbx_cep_rule_t		*rule;
		zbx_uint64_t		ruleid;
		zbx_cep_window_t	*window;
		zbx_uint32_t		group_by = ZBX_CEP_GROUP_BY_NONE;

		/* removed rows will be always added at the end */
		if (ZBX_DBSYNC_ROW_REMOVE == tag)
			break;

		ZBX_STR2UINT64(ruleid, row[0]);
		if (NULL == (rule = cep_acquire_rule_by_id(cep_config, ruleid, revision)))
			continue;

		if (NULL == rule->window)
			rule->window = cep_window_create();

		window = rule->window;

		window->type = atoi(row[1]);
		ZBX_DBROW2STR(window->duration, row[2]);
		ZBX_DBROW2STR(window->capacity, row[3]);
		window->evaltype = atoi(row[4]);
		ZBX_DBROW2STR(window->formula, row[5]);
		ZBX_DBROW2STR(window->script, row[6]);

		if (0 != atoi(row[7]))
			group_by |= ZBX_CEP_GROUP_BY_HOSTGROUP;
		if (0 != atoi(row[8]))
			group_by |= ZBX_CEP_GROUP_BY_HOST;
		if (0 != atoi(row[9]))
			group_by |= ZBX_CEP_GROUP_BY_TAG;
		window->group_by = group_by;
		ZBX_DBROW2STR(window->group_tag, row[10]);
		ZBX_DBROW2STR(window->event_count_tag, row[11]);
	}

	/* remove deleted cep windows */
	for (; SUCCEED == ret; ret = zbx_dbsync_next(sync, &rowid, &row, &tag))
	{
		zbx_cep_rule_t	*rule;

		if (NULL == (rule = cep_acquire_rule_by_id(cep_config, rowid, revision)))
			continue;

		if (NULL != rule->window)
		{
			cep_window_free(rule->window);
			rule->window = NULL;
		}
	}

	if (0 != sync->add_num + sync->update_num + sync->remove_num)
		cep_config->revision = revision;

	zbx_dcsync_sync_end(sync, dbconfig_used_size());

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static zbx_cep_window_condition_t	*cep_acquire_window_condition(zbx_cep_config_t *cep_config, zbx_uint64_t ruleid,
		zbx_uint64_t conditionid, zbx_uint64_t revision)
{
	zbx_cep_rule_t			*rule;
	int				index;
	zbx_cep_window_condition_t	condition_local = {.conditionid = conditionid};

	if (NULL == (rule = cep_acquire_rule_by_id(cep_config, ruleid, revision)) || NULL == rule->window)
		return NULL;

	if (FAIL == (index = zbx_vector_cep_window_condition_search(&rule->window->conditions, condition_local,
		cep_window_condition_compare_by_id)))
	{
		zbx_object_rel_t	rel_local = {.objectid = conditionid, .parentid = ruleid};

		index = rule->window->conditions.values_num;
		zbx_vector_cep_window_condition_append(&rule->window->conditions, condition_local);

		zbx_hashset_insert(&cep_config->window_condition_rel, &rel_local, sizeof(rel_local));
	}

	return &rule->window->conditions.values[index];
}

static void	cep_remove_window_condition(zbx_cep_config_t *cep_config, zbx_uint64_t conditionid,
		zbx_uint64_t revision)
{
	zbx_cep_rule_ref_t		ref_local, *ref;
	zbx_cep_rule_t			*rule;
	int				index;
	zbx_cep_window_condition_t	condition_local = {.conditionid = conditionid};
	zbx_object_rel_t		rel_local = {.objectid = conditionid}, *rel;

	if (NULL == (rel = (zbx_object_rel_t *)zbx_hashset_search(&cep_config->window_condition_rel,
			&rel_local)))
	{
		return;
	}

	ref_local.ruleid = rel->parentid;
	if (NULL != (ref = (zbx_cep_rule_ref_t *)zbx_hashset_search(&cep_config->rules, &ref_local)) &&
		NULL != ref->rule->window)
	{
		rule = cep_acquire_rule(ref, revision);

		if (FAIL != (index = zbx_vector_cep_window_condition_search(&rule->window->conditions, condition_local,
				cep_window_condition_compare_by_id)))
		{
			cep_window_condition_clear(&rule->window->conditions.values[index]);
			zbx_vector_cep_window_condition_remove(&rule->window->conditions, index);
		}
	}

	zbx_hashset_remove_direct(&cep_config->window_condition_rel, rel);
}

static void	cep_sync_window_conditions(zbx_cep_config_t *cep_config, zbx_dbsync_t *sync, zbx_uint64_t revision)
{
	char		**row;
	zbx_uint64_t	rowid;
	unsigned char	tag;
	int		ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_dcsync_sync_start(sync, dbconfig_used_size());

	while (SUCCEED == (ret = zbx_dbsync_next(sync, &rowid, &row, &tag)))
	{
		zbx_uint64_t			conditionid, ruleid;
		zbx_cep_window_condition_t	*condition;
		int				condition_type;

		/* removed rows will be always added at the end */
		if (ZBX_DBSYNC_ROW_REMOVE == tag)
			break;

		ZBX_STR2UINT64(conditionid, row[0]);
		ZBX_STR2UINT64(ruleid, row[1]);

		if (NULL == (condition = cep_acquire_window_condition(cep_config, ruleid, conditionid, revision)))
			continue;

		if (condition->type != (condition_type = atoi(row[2])))
		{
			cep_window_condition_clear_args(condition);
			condition->type = condition_type;
		}
		condition->operator = atoi(row[3]);

		switch (condition->type)
		{
			case ZBX_CEP_WINDOW_CONDITION_TAG_PAIR:
				ZBX_DBROW2STR(condition->args.tag_pair.old_tag, row[4]);
				ZBX_DBROW2STR(condition->args.tag_pair.new_tag, row[6]);
				break;
			case ZBX_CEP_WINDOW_CONDITION_OLD_TAG:
				ZBX_DBROW2STR(condition->args.old_tag.tag, row[4]);
				break;
			case ZBX_CEP_WINDOW_CONDITION_OLD_TAG_VALUE:
				ZBX_DBROW2STR(condition->args.old_tag_value.tag, row[4]);
				ZBX_DBROW2STR(condition->args.old_tag_value.value, row[5]);
				break;
		}
	}

	/* remove deleted cep window conditions */
	for (; SUCCEED == ret; ret = zbx_dbsync_next(sync, &rowid, &row, &tag))
	{
		cep_remove_window_condition(cep_config, rowid, revision);
	}

	if (0 != sync->add_num + sync->update_num + sync->remove_num)
		cep_config->revision = revision;

	zbx_dcsync_sync_end(sync, dbconfig_used_size());

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static zbx_cep_operation_t	*cep_acquire_operation(zbx_cep_config_t *cep_config, zbx_uint64_t ruleid,
		zbx_uint64_t operationid, zbx_uint64_t revision, zbx_vector_cep_rule_ptr_t *rules)
{
	zbx_cep_rule_t		*rule;
	int			index;
	zbx_cep_operation_t	op_local = {.operationid = operationid};

	if (NULL == (rule = cep_acquire_rule_by_id(cep_config, ruleid, revision)))
		return NULL;

	if (FAIL == (index = zbx_vector_cep_operation_search(&rule->operations, op_local,
			cep_operation_compare_by_id)))
	{
		zbx_object_rel_t	rel_local = {.objectid = operationid, .parentid = ruleid};

		index = rule->operations.values_num;

		zbx_vector_cep_operation_tag_create(&op_local.tags);
		zbx_vector_cep_operation_append(&rule->operations, op_local);

		zbx_hashset_insert(&cep_config->operation_rel, &rel_local, sizeof(rel_local));
	}

	if (NULL != rules)
		zbx_vector_cep_rule_ptr_append(rules, rule);

	return &rule->operations.values[index];
}

static void	cep_remove_operation(zbx_cep_config_t *cep_config, zbx_uint64_t operationid, zbx_uint64_t revision,
		zbx_vector_cep_rule_ptr_t *rules)
{
	zbx_cep_rule_ref_t	ref_local, *ref;
	zbx_cep_rule_t		*rule;
	int			index;
	zbx_cep_operation_t	op_local = {.operationid = operationid};
	zbx_object_rel_t	rel_local = {.objectid = operationid}, *rel;

	if (NULL == (rel = (zbx_object_rel_t *)zbx_hashset_search(&cep_config->operation_rel, &rel_local)))
		return;

	ref_local.ruleid = rel->parentid;
	if (NULL != (ref = (zbx_cep_rule_ref_t *)zbx_hashset_search(&cep_config->rules, &ref_local)))
	{
		rule = cep_acquire_rule(ref, revision);

		if (FAIL != (index = zbx_vector_cep_operation_search(&rule->operations, op_local,
				cep_operation_compare_by_id)))
		{
			cep_operation_clear(&rule->operations.values[index]);
			zbx_vector_cep_operation_remove(&rule->operations, index);
		}

		if (NULL != rules)
			zbx_vector_cep_rule_ptr_append(rules, rule);
	}

	zbx_hashset_remove_direct(&cep_config->operation_rel, rel);
}

static void	cep_sync_operations(zbx_cep_config_t *cep_config, zbx_dbsync_t *sync, zbx_uint64_t revision,
		zbx_vector_cep_rule_ptr_t *rules)
{
	char		**row;
	zbx_uint64_t	rowid;
	unsigned char	tag;
	int		ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_dcsync_sync_start(sync, dbconfig_used_size());

	while (SUCCEED == (ret = zbx_dbsync_next(sync, &rowid, &row, &tag)))
	{
		zbx_uint64_t		operationid, ruleid;
		int			operation_type;
		zbx_cep_operation_t	*operation;

		/* removed rows will be always added at the end */
		if (ZBX_DBSYNC_ROW_REMOVE == tag)
			break;

		ZBX_STR2UINT64(operationid, row[0]);
		ZBX_STR2UINT64(ruleid, row[1]);

		if (NULL == (operation = cep_acquire_operation(cep_config, ruleid, operationid, revision, rules)))
			continue;

		if (operation->type != (operation_type = atoi(row[2])))
		{
			cep_operation_clear_args(operation);
			operation->type = operation_type;
		}

		operation->execute_when = atoi(row[3]);
		operation->evaltype = atoi(row[4]);
		operation->sortorder = atoi(row[10]);

		switch (operation->type)
		{
			case ZBX_CEP_OP_SET_NAME:
				ZBX_DBROW2STR(operation->args.set_name.name, row[5]);
				break;
			case ZBX_CEP_OP_SET_SEVERITY:
				operation->args.set_severity.level = atoi(row[9]);
				break;
			case ZBX_CEP_OP_ADD_TAG:
				ZBX_DBROW2STR(operation->args.add_tag.tag, row[6]);
				ZBX_DBROW2STR(operation->args.add_tag.value, row[7]);
				break;
			case ZBX_CEP_OP_SET_TAG:
				ZBX_DBROW2STR(operation->args.set_tag.tag, row[6]);
				ZBX_DBROW2STR(operation->args.set_tag.value, row[7]);
				break;
			case ZBX_CEP_OP_SET_TAG_VALUE:
				ZBX_DBROW2STR(operation->args.set_tag_value.tag, row[6]);
				ZBX_DBROW2STR(operation->args.set_tag_value.value, row[7]);
				break;
			case ZBX_CEP_OP_INCREASE_TAG_VALUE:
				ZBX_DBROW2STR(operation->args.increase_tag_value.tag, row[6]);
				break;
			case ZBX_CEP_OP_DECREASE_TAG_VALUE:
				ZBX_DBROW2STR(operation->args.decrease_tag_value.tag, row[6]);
				break;
			case ZBX_CEP_OP_RENAME_TAG:
				ZBX_DBROW2STR(operation->args.rename_tag.old_tag, row[6]);
				ZBX_DBROW2STR(operation->args.rename_tag.new_tag, row[8]);
				break;
			case ZBX_CEP_OP_REMOVE_TAG:
				ZBX_DBROW2STR(operation->args.remove_tag.tag, row[6]);
				break;
			case ZBX_CEP_OP_CLOSE:
			case ZBX_CEP_OP_DISCARD:
			case ZBX_CEP_OP_INCREASE_SEVERITY:
			case ZBX_CEP_OP_DECREASE_SEVERITY:
			case ZBX_CEP_OP_SUPPRESS:
			case ZBX_CEP_OP_COPY_FIRST:
			case ZBX_CEP_OP_COPY_LAST:
				break;
		}
	}

	/* remove deleted cep operations */
	for (; SUCCEED == ret; ret = zbx_dbsync_next(sync, &rowid, &row, &tag))
	{
		cep_remove_operation(cep_config, rowid, revision, rules);
	}

	if (0 != sync->add_num + sync->update_num + sync->remove_num)
		cep_config->revision = revision;

	zbx_dcsync_sync_end(sync, dbconfig_used_size());

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static zbx_cep_operation_tag_t	*cep_acquire_operation_tag(zbx_cep_config_t *cep_config, zbx_uint64_t operationid,
		zbx_uint64_t tagid, zbx_uint64_t revision)
{
	zbx_cep_operation_t		*operation;
	zbx_cep_operation_tag_t		tag_local = {.cep_operation_tagid = tagid};
	zbx_object_rel_t		rel_local = {.objectid = operationid}, *rel;
	int				index;

	if (NULL == (rel = (zbx_object_rel_t *)zbx_hashset_search(&cep_config->operation_rel, &rel_local)))
		return NULL;

	if (NULL == (operation = cep_acquire_operation(cep_config, rel->parentid, operationid, revision, NULL)))
		return NULL;

	if (FAIL == (index = zbx_vector_cep_operation_tag_search(&operation->tags, tag_local,
			cep_operation_tag_compare_by_id)))
	{
		zbx_object_rel_t	tag_rel = {.objectid = tagid, .parentid = operationid};

		index = operation->tags.values_num;
		zbx_vector_cep_operation_tag_append(&operation->tags, tag_local);
		zbx_hashset_insert(&cep_config->operation_tag_rel, &tag_rel, sizeof(tag_rel));
	}

	return &operation->tags.values[index];
}

static void	cep_remove_operation_tag(zbx_cep_config_t *cep_config, zbx_uint64_t tagid, zbx_uint64_t revision)
{
	zbx_cep_operation_t		*operation;
	zbx_cep_operation_tag_t		tag_local = {.cep_operation_tagid = tagid};
	zbx_object_rel_t		rel_local = {.objectid = tagid}, *rel;
	zbx_object_rel_t		op_rel_local, *op_rel;
	int				index;

	if (NULL == (rel = (zbx_object_rel_t *)zbx_hashset_search(&cep_config->operation_tag_rel, &rel_local)))
		return;

	op_rel_local.objectid = rel->parentid;
	if (NULL == (op_rel = (zbx_object_rel_t *)zbx_hashset_search(&cep_config->operation_rel, &op_rel_local)))
		goto out;

	if (NULL == (operation = cep_acquire_operation(cep_config, op_rel->parentid, rel->parentid, revision, NULL)))
		goto out;

	if (FAIL != (index = zbx_vector_cep_operation_tag_search(&operation->tags, tag_local,
			cep_operation_tag_compare_by_id)))
	{
		cep_operation_tag_clear(&operation->tags.values[index]);
		zbx_vector_cep_operation_tag_remove(&operation->tags, index);
	}
out:
	zbx_hashset_remove_direct(&cep_config->operation_tag_rel, rel);
}

static void	cep_sync_operation_tags(zbx_cep_config_t *cep_config, zbx_dbsync_t *sync, zbx_uint64_t revision)
{
	char		**row;
	zbx_uint64_t	rowid;
	unsigned char	tag;
	int		ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_dcsync_sync_start(sync, dbconfig_used_size());

	while (SUCCEED == (ret = zbx_dbsync_next(sync, &rowid, &row, &tag)))
	{
		zbx_uint64_t		tagid, operationid;
		zbx_cep_operation_tag_t	*operation_tag;

		/* removed rows will be always added at the end */
		if (ZBX_DBSYNC_ROW_REMOVE == tag)
			break;

		ZBX_STR2UINT64(tagid, row[0]);
		ZBX_STR2UINT64(operationid, row[1]);

		if (NULL == (operation_tag = cep_acquire_operation_tag(cep_config, operationid, tagid, revision)))
			continue;

		operation_tag->operator = atoi(row[2]);
		ZBX_DBROW2STR(operation_tag->tag, row[3]);
		ZBX_DBROW2STR(operation_tag->value, row[4]);
	}

	/* remove deleted cep operation tags */
	for (; SUCCEED == ret; ret = zbx_dbsync_next(sync, &rowid, &row, &tag))
		cep_remove_operation_tag(cep_config, rowid, revision);

	if (0 != sync->add_num + sync->update_num + sync->remove_num)
		cep_config->revision = revision;

	zbx_dcsync_sync_end(sync, dbconfig_used_size());

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

void	cep_config_sync(zbx_dbsync_t *rule_sync, zbx_dbsync_t *condition_sync, zbx_dbsync_t *window_sync,
		zbx_dbsync_t *window_condition_sync, zbx_dbsync_t *operation_sync, zbx_dbsync_t *operation_tag_sync,
		zbx_uint64_t revision)
{
	zbx_vector_cep_rule_ptr_t	rules_cond, *prules_cond = NULL, rules_op, *prules_op = NULL;
	zbx_cep_config_t		*cep_config = dc_local()->cep_config;

	if (0 != cep_config->rules.num_data)
	{
		zbx_vector_cep_rule_ptr_create(&rules_cond);
		prules_cond = &rules_cond;

		zbx_vector_cep_rule_ptr_create(&rules_op);
		prules_op = &rules_op;
	}

	cep_sync_rules(cep_config, rule_sync, revision, prules_cond);
	cep_sync_conditions(cep_config, condition_sync, revision, prules_cond);
	cep_sync_windows(cep_config, window_sync, revision);
	cep_sync_window_conditions(cep_config, window_condition_sync, revision);
	cep_sync_operations(cep_config, operation_sync, revision, prules_op);
	cep_sync_operation_tags(cep_config, operation_tag_sync, revision);

	cep_update_rules(cep_config, prules_cond, prules_op);
	cep_config_update_handle(cep_config, revision);

	cep_config_dump();

	if (NULL != prules_cond)
		zbx_vector_cep_rule_ptr_destroy(prules_cond);

	if (NULL != prules_op)
		zbx_vector_cep_rule_ptr_destroy(prules_op);
}

zbx_cep_config_handle_t	zbx_cep_config_open(void)
{
	zbx_cep_config_handle_t	handle;
	zbx_cep_config_t	*cep_config = dc_local()->cep_config;

	if (0 == atomic_load(&cep_config->rules_num))
		return NULL;

	pthread_mutex_lock(&cep_config->lock);
	handle = cep_config->handle;
	atomic_fetch_add(&handle->refcount, 1);
	pthread_mutex_unlock(&cep_config->lock);

	return handle;
}

void	zbx_cep_config_close(zbx_cep_config_handle_t handle)
{
	cep_config_handle_release(handle);
}

const zbx_vector_cep_rule_ptr_t	*zbx_cep_config_get_rules(zbx_cep_config_handle_t handle)
{
	return &handle->rules;
}

