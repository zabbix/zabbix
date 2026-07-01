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

#include "cep_window.h"
#include "cep.h"
#include "cep_api.h"
#include "cep_event.h"
#include "cep_rule.h"
#include "cep_rule_op_event.h"
#include "zabbix_server/cep/cep_js.h"
#include "zabbix_server/cep/cep_task.h"
#include "zbx_cep.h"
#include "zbxalgo.h"
#include "zbxcacheconfig.h"
#include "zbxcommon.h"
#include "zbxdb.h"
#include "zbxembed.h"
#include "zbxexpr.h"
#include "zbxnum.h"
#include "zbxstr.h"
#include "zbxtime.h"
#include "zbxtypes_ext.h"
#include <stdatomic.h>
#include <stdint.h>

ZBX_PTR_VECTOR_IMPL(cep_window_ptr, zbx_cep_window_t *)

static zbx_hash_t	cep_window_ref_hash(const void *a)
{
	const zbx_cep_window_ref_t	*ref = (const zbx_cep_window_ref_t *)a;

	zbx_hash_t	hash = ZBX_DEFAULT_ID_HASH_FUNC(&ref->ruleid);

	hash = ZBX_DEFAULT_HASH_ALGO(&ref->group_by, 1, hash);

	if (ZBX_CEP_GROUP_BY_NONE == ref->group_by)
		return hash;

	if (0 != (ref->group_by & ZBX_CEP_GROUP_BY_HOST))
		hash = ZBX_DEFAULT_HASH_ALGO(&ref->hostid, sizeof(ref->hostid), hash);

	if (0 != (ref->group_by & ZBX_CEP_GROUP_BY_HOSTGROUP))
		hash = ZBX_DEFAULT_HASH_ALGO(&ref->hostgroupid, sizeof(ref->hostgroupid), hash);

	if (0 != (ref->group_by & ZBX_CEP_GROUP_BY_TAG))
	{
		if (NULL != ref->tag)
			hash = ZBX_DEFAULT_STRING_HASH_ALGO(ref->tag, strlen(ref->tag), hash);

		if (NULL != ref->tag_value)
			hash = ZBX_DEFAULT_STRING_HASH_ALGO(ref->tag_value, strlen(ref->tag_value), hash);
	}

	return hash;
}

static int	cep_window_ref_compare(const void *a1, const void *a2)
{
	const zbx_cep_window_ref_t	*ref1 = (const zbx_cep_window_ref_t *)a1;
	const zbx_cep_window_ref_t	*ref2 = (const zbx_cep_window_ref_t *)a2;
	int				ret;

	ZBX_RETURN_IF_NOT_EQUAL(ref1->ruleid, ref2->ruleid);
	ZBX_RETURN_IF_NOT_EQUAL(ref1->group_by, ref2->group_by);

	if (ZBX_CEP_GROUP_BY_NONE == ref1->group_by)
		return 0;

	if (0 != (ref1->group_by & ZBX_CEP_GROUP_BY_HOST))
	{
		ZBX_RETURN_IF_NOT_EQUAL(ref1->hostid, ref2->hostid);
	}

	if (0 != (ref1->group_by & ZBX_CEP_GROUP_BY_HOSTGROUP))
	{
		ZBX_RETURN_IF_NOT_EQUAL(ref1->hostgroupid, ref2->hostgroupid);
	}

	if (0 != (ref1->group_by & ZBX_CEP_GROUP_BY_TAG))
	{
		if (0 != (ret = zbx_strcmp_null(ref1->tag, ref2->tag)))
			return ret;

		if (0 != (ret = zbx_strcmp_null(ref1->tag_value, ref2->tag_value)))
			return ret;
	}

	return 0;
}

zbx_cep_window_t	*cep_window_addref(zbx_cep_window_t *window)
{
	atomic_fetch_add(&window->refcount, 1);

	return window;
}

void	cep_window_release(zbx_cep_window_t *window)
{
	zbx_cep_event_handle_t	h;

	if (1 != atomic_fetch_sub(&window->refcount, 1))
		return;

	while (NULL != (h = (zbx_cep_event_handle_t)zbx_queue_ptr_pop(&window->hevents)))
		zbx_cep_event_handle_release(h);

	zbx_queue_ptr_destroy(&window->hevents);

	zbx_free(window->js_script);
	zbx_free(window->js_code);

	pthread_mutex_destroy(&window->lock);

	zbx_free(window);
}

static int	cep_window_param_has_macro(const char *limit)
{
	return (NULL == strstr(limit, "{$") ? FAIL : SUCCEED);
}

static int	cep_window_get_limits(const zbx_cep_rule_t *rule, int *duration, int *capacity, char **error)
{
	char		*duration_dyn = NULL, *capacity_dyn = NULL;
	const char	*duration_str, *capacity_str;
	int		ret = FAIL;

	if (SUCCEED == cep_window_param_has_macro(rule->window->duration) ||
			SUCCEED == cep_window_param_has_macro(rule->window->capacity))
	{
		duration_dyn = zbx_strdup(NULL, rule->window->duration);
		capacity_dyn = zbx_strdup(NULL, rule->window->capacity);

		zbx_dc_um_handle_t	*um_handle = zbx_dc_open_user_macros();

		zbx_dc_expand_user_and_func_macros(um_handle, &duration_dyn, NULL, 0, NULL);
		zbx_dc_expand_user_and_func_macros(um_handle, &capacity_dyn, NULL, 0, NULL);

		zbx_dc_close_user_macros(um_handle);

		duration_str = duration_dyn;
		capacity_str = capacity_dyn;
	}
	else
	{
		duration_str = rule->window->duration;
		capacity_str = rule->window->capacity;
	}

	if (SUCCEED != zbx_is_time_suffix(duration_str, duration, ZBX_LENGTH_UNLIMITED))
	{
		if (NULL != error)
			*error = zbx_dsprintf(NULL, "Invalid CEP window duration %s.\n", duration_str);
		goto out;
	}

	if (SUCCEED != zbx_is_int(capacity_str, capacity))
	{
		if (NULL != error)
			*error = zbx_dsprintf(NULL, "Invalid CEP window capacity %s.\n", capacity_str);
		goto out;
	}

	ret = SUCCEED;
out:
	zbx_free(duration_dyn);
	zbx_free(capacity_dyn);

	return ret;
}

static zbx_cep_window_t	*cep_window_create(const zbx_cep_rule_t *rule, zbx_cep_window_ref_t *ref)
{
	zbx_cep_window_t	*window;
	int			err;

	window = (zbx_cep_window_t *)zbx_malloc(NULL, sizeof(zbx_cep_window_t));
	window->ruleid = rule->ruleid;
	window->type = rule->window->type;
	window->ref = ref;

	if (NULL != rule->window->script && '\0' != *rule->window->script)
		window->js_script = zbx_strdup(NULL, rule->window->script);
	else
		window->js_script = NULL;

	window->js_code = NULL;
	window->js_codelen = 0;
	window->duration = 0;
	window->capacity = 0;
	window->nextcheck = 0;
	window->time_created = time(NULL);
	window->flags = CEP_WINDOW_FLAGS_NONE;
	zbx_queue_ptr_create(&window->hevents);
	window->location = CEP_LOCATION_UNKNOWN;
	window->access_num = 0;

	if (0 != (err = pthread_mutex_init(&window->lock, NULL)))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot initialize CEP window mutex: %s", zbx_strerror(err));
		zbx_exit(EXIT_FAILURE);
	}

	window->refcount = 1;

	return window;
}

static void	cep_window_lock(zbx_cep_window_t *window)
{
	pthread_mutex_lock(&window->lock);
}

static void	cep_window_unlock(zbx_cep_window_t *window)
{
	pthread_mutex_unlock(&window->lock);
}

static void	cep_window_ref_clear(void *a)
{
	zbx_cep_window_ref_t	*ref = (zbx_cep_window_ref_t *)a;

	cep_window_release(ref->window);

	zbx_free(ref->tag);
	zbx_free(ref->tag_value);
}

static zbx_cep_event_pos_t	cep_window_event_pos(int index, int events_num)
{
	if (0 == index)
		return CEP_POS_FIRST;

	if (index == events_num - 1)
		return CEP_POS_LAST;

	return CEP_POS_UNKNOWN;
}

static int	cep_window_close(const zbx_cep_rule_t *rule, zbx_cep_window_t *window, zbx_vector_mw_task_ptr_t *tasks)
{
	int	events_num = zbx_queue_ptr_values_num(&window->hevents);

	for (int i = 0; i < events_num; i++)
	{
		zbx_cep_event_context_t	ctx = {
				.hevent = (zbx_cep_event_handle_t)zbx_queue_ptr_pop(&window->hevents),
				.pos = cep_window_event_pos(i, events_num)};

		cep_rule_event_context_execute_ops(rule, &ctx, ZBX_CEP_WHEN_WINDOW_CLOSED, tasks);
		cep_event_context_clear(&ctx);
	}

	return events_num;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get the next check time for a cep window                          *
 *                                                                            *
 * Parameters: window     - [IN] cep window                                   *
 *             event      - [IN] first event in simple or tag match window,   *
 *                               NULL if window is empty                      *
 *             start_time - [IN] window start time                            *
 *                                                                            *
 * Return value: next check timestamp                                         *
 *                                                                            *
 ******************************************************************************/
static zbx_uint64_t	cep_window_get_nextcheck(const zbx_cep_window_t *window, const zbx_cep_event_t *event,
		time_t start_time)
{
	switch (window->type)
	{
		case ZBX_CEP_WINDOW_SIMPLE:
		case ZBX_CEP_WINDOW_TAG_MATCH:
			if (NULL != event)
				return  (zbx_uint64_t)(event->clock + window->duration);
			else
				return  (zbx_uint64_t)time(NULL) + 1;
		case ZBX_CEP_WINDOW_PATTERN_MATCH:
			return (zbx_uint64_t)time(NULL) + 1;
		case ZBX_CEP_WINDOW_CAUSAL:
			return (zbx_uint64_t)start_time + window->duration;
		default:
			THIS_SHOULD_NEVER_HAPPEN_MSG("unknown window type: %d", window->type);
			return 0;
	}
}

void	cep_window_sliding_process_event(const zbx_cep_rule_t *rule, zbx_cep_event_context_t *ctx,
		zbx_vector_mw_task_ptr_t *tasks)
{
	zbx_cep_window_pool_t	*pool;
	zbx_cep_window_t	*window;
	char			*error = NULL;
	zbx_uint64_t		opmask = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() ruleid:" ZBX_FS_UI64, __func__, rule->ruleid);

	cep_window_pool_acquire(&pool);
	if (NULL != (window = cep_window_pool_get_or_create_window(pool, rule, ctx, &error)))
		window->access_num++;
	cep_window_pool_release(&pool);

	cep_rule_handle_error(rule, &error, tasks);
	if (NULL == window)
		goto out;

	cep_window_lock(window);

	if (0 != window->capacity && zbx_queue_ptr_values_num(&window->hevents) == window->capacity)
	{
		cep_window_unlock(window);

		opmask = cep_rule_event_context_execute_ops(rule, ctx, ZBX_CEP_WHEN_EVENT_EVICTED, tasks);
	}
	else
	{
		if (0 == zbx_queue_ptr_values_num(&window->hevents))
			atomic_store(&window->nextcheck, cep_window_get_nextcheck(window, ctx->event, 0));

		zbx_queue_ptr_push(&window->hevents, zbx_cep_event_handle_addref(ctx->hevent));

		cep_window_unlock(window);

		opmask = cep_rule_event_execute_close_window(rule, ZBX_CEP_WHEN_EVENT_OCCURRED, ctx);
	}

	if (0 != (opmask & CEP_FLAG(ZBX_CEP_OP_CLOSE_WINDOW)))
	{
		cep_window_lock(window);
		cep_window_close(rule, window, tasks);
		cep_window_unlock(window);
	}

	cep_window_pool_acquire(&pool);
	window->access_num--;
	cep_window_pool_enqueue(pool, window);
	cep_window_pool_release(&pool);

	cep_window_release(window);
out:
	zbx_free(error);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

void	cep_window_sliding_process(zbx_cep_window_t *window, time_t now, zbx_vector_mw_task_ptr_t *tasks)
{
	zbx_cep_rule_t		*rule;
	int			pending_num, duration, capacity, limit_update;
	char			*error = NULL;
	zbx_cep_window_pool_t	*pool;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() ruleid:" ZBX_FS_UI64, __func__, window->ruleid);

	if (NULL == (rule = zbx_cep_config_get_rule(window->ruleid)))
	{
		cep_window_pool_acquire(&pool);
		cep_window_pool_remove_window(pool, window);
		cep_window_pool_release(&pool);

		goto out;
	}

	limit_update = cep_window_get_limits(rule, &duration, &capacity, &error);
	cep_rule_handle_error(rule, &error, tasks);

	cep_window_lock(window);

	if (SUCCEED == limit_update)
	{
		window->duration = duration;
		window->capacity = capacity;
	}

	while (SUCCEED != zbx_queue_ptr_empty(&window->hevents))
	{
		zbx_cep_event_handle_t	h = (zbx_cep_event_handle_t)zbx_queue_ptr_peek(&window->hevents);
		zbx_cep_event_context_t	ctx = {.hevent = zbx_cep_event_handle_addref(h), .pos = CEP_POS_FIRST};
		zbx_uint64_t		opmask = 0;

		if (NULL != cep_event_context_get_event(&ctx))
		{
			if (ctx.event->clock + window->duration > now)
			{
				atomic_store(&window->nextcheck, cep_window_get_nextcheck(window, ctx.event, 0));
				cep_event_context_clear(&ctx);
				break;
			}

			opmask = cep_rule_event_context_execute_ops(rule, &ctx, ZBX_CEP_WHEN_EVENT_EVICTED, tasks);
		}

		zbx_queue_ptr_pop(&window->hevents);
		zbx_cep_event_handle_release(h);
		cep_event_context_clear(&ctx);

		if (0 != (opmask & CEP_FLAG(ZBX_CEP_OP_CLOSE_WINDOW)))
			cep_window_close(rule, window, tasks);
	}

	pending_num = zbx_queue_ptr_values_num(&window->hevents);

	cep_window_pool_acquire(&pool);
	if (0 != pending_num || 0 != window->access_num)
	{
		if (0 == pending_num)
			atomic_store(&window->nextcheck, cep_window_get_nextcheck(window, NULL, 0));

		cep_window_pool_enqueue(pool, window);
	}
	else
	{
		cep_window_pool_remove_window(pool, window);
	}
	cep_window_pool_release(&pool);

	cep_window_unlock(window);
	zbx_cep_rule_release(rule);
out:
	zbx_free(error);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	cep_window_set_event_tag(zbx_cep_window_t *window, zbx_cep_event_handle_t h, const char *tag, int value,
		zbx_vector_mw_task_ptr_t *tasks)
{
	int			index;
	zbx_cep_event_t		*event;
	zbx_cep_event_context_t	ctx = {.hevent = zbx_cep_event_handle_addref(h)};
	char			buf[MAX_ID_LEN];
	zbx_cep_acknowledge_t	ack = {0};

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() eventid:" ZBX_FS_UI64 " tag:%s value:%d", __func__,
			zbx_cep_event_handle_eventid(h), tag, value);

	if (NULL == (event = cep_event_context_get_event(&ctx)))
		goto out;

	if (FAIL != (index = cep_event_find_tag(event, tag)) && 0 == (window->flags & CEP_WINDOW_FLAGS_SYMPTOM_TAG_SET))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot set event " ZBX_FS_UI64 "tag \"%s\": tag already exists",
				event->eventid, tag);
		goto out;
	}

	zbx_snprintf(buf, sizeof(buf), "%d", value);

	if (NULL == (event = cep_event_context_get_mutable_event(&ctx)))
		goto out;

	if (FAIL == index)
	{
		zbx_tag_t	tag_local;

		cep_acknowledge_update_tag(&ack, ZBX_CEP_OP_ADD_TAG, NULL, NULL, tag, buf);

		tag_local.tag = zbx_strdup(NULL, tag);
		tag_local.value = zbx_strdup(NULL, buf);
		zbx_vector_lite_tag_append(&event->tags, tag_local);
	}
	else
	{
		zbx_tag_t	*t = &event->tags.values[index];

		cep_acknowledge_update_tag(&ack, ZBX_CEP_OP_INCREASE_TAG_VALUE, t->tag, t->value, NULL, buf);
		t->value = zbx_strdup(t->value, buf);
	}

	zbx_cep_t	*cep;

	cep_cache_acquire(&cep);
	cep_event_handle_set(h, event);
	cep_cache_release(&cep);

	zbx_vector_mw_task_ptr_append(tasks, cep_create_task_sync_event(h, CEP_SYNC_EVENT_TAGS));
	zbx_vector_mw_task_ptr_append(tasks, cep_create_task_acknowledge(&ack, window->ruleid, event->eventid));

	window->flags |= CEP_WINDOW_FLAGS_SYMPTOM_TAG_SET;
out:
	cep_event_context_clear(&ctx);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	cep_window_set_event_cause(zbx_cep_window_t *window, zbx_cep_event_context_t *ctx,
		zbx_cep_event_handle_t hcause, zbx_vector_mw_task_ptr_t *tasks)
{
	zbx_cep_t		*cep;
	zbx_cep_event_t		*event = cep_event_context_get_mutable_event(ctx);
	zbx_cep_acknowledge_t	ack = {0};

	if (NULL == event)
		return;

	event->cause_eventid = zbx_cep_event_handle_eventid(hcause);
	cep_cache_acquire(&cep);
	cep_event_handle_set(ctx->hevent, event);
	cep_cache_release(&cep);

	cep_acknowledge_set_cause(&ack, event->cause_eventid);
	zbx_vector_mw_task_ptr_append(tasks, cep_create_task_acknowledge(&ack, window->ruleid, event->eventid));
}

void	cep_window_causal_process_event(const zbx_cep_rule_t *rule, zbx_cep_event_context_t *ctx,
		zbx_vector_mw_task_ptr_t *tasks)
{
	zbx_cep_window_pool_t	*pool;
	zbx_cep_window_t	*window;
	char			*error = NULL;
	time_t			start_time = 0;
	zbx_uint64_t		opmask = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() ruleid:" ZBX_FS_UI64, __func__, rule->ruleid);

	cep_window_pool_acquire(&pool);
	if (NULL != (window = cep_window_pool_get_or_create_window(pool, rule, ctx, &error)))
		window->access_num++;
	cep_window_pool_release(&pool);

	cep_rule_handle_error(rule, &error, tasks);
	if (NULL == window)
		goto out;

	if (0 == atomic_load(&window->nextcheck))
	{
		zbx_cep_t	*cep;

		cep_cache_acquire(&cep);
		start_time = cep_rule_get_window_start_time(cep, rule->ruleid, window->duration);
		cep_cache_release(&cep);
	}

	cep_window_lock(window);

	if (0 != window->capacity && zbx_queue_ptr_values_num(&window->hevents) == window->capacity)
	{
		cep_window_unlock(window);

		opmask = cep_rule_event_context_execute_ops(rule, ctx, ZBX_CEP_WHEN_EVENT_EVICTED, tasks);
	}
	else
	{
		if (0 != zbx_queue_ptr_values_num(&window->hevents))
		{
			zbx_cep_event_handle_t	h = (zbx_cep_event_handle_t)zbx_queue_ptr_peek(&window->hevents);

			if ('\0' != *rule->window->event_count_tag)
			{
				cep_window_set_event_tag(window, h, rule->window->event_count_tag,
						zbx_queue_ptr_values_num(&window->hevents), tasks);
			}

			cep_window_set_event_cause(window, ctx, h, tasks);
		}

		zbx_queue_ptr_push(&window->hevents, zbx_cep_event_handle_addref(ctx->hevent));
		if (0 != start_time)
			atomic_store(&window->nextcheck, cep_window_get_nextcheck(window, NULL, start_time));

		cep_window_unlock(window);

		opmask = cep_rule_event_execute_close_window(rule, ZBX_CEP_WHEN_EVENT_OCCURRED, ctx);
	}

	if (0 != (opmask & CEP_FLAG(ZBX_CEP_OP_CLOSE_WINDOW)))
	{
		cep_window_lock(window);
		cep_window_close(rule, window, tasks);
		cep_window_unlock(window);
	}

	cep_window_pool_acquire(&pool);
	window->access_num--;
	cep_window_pool_enqueue(pool, window);
	cep_window_pool_release(&pool);

	cep_window_release(window);
out:
	zbx_free(error);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

void	cep_window_causal_process(zbx_cep_window_t *window, time_t now, zbx_vector_mw_task_ptr_t *tasks)
{
	zbx_cep_rule_t		*rule;
	int			duration, capacity, limit_update, events_num;
	char			*error = NULL;
	zbx_cep_window_pool_t	*pool;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() ruleid:" ZBX_FS_UI64, __func__, window->ruleid);

	if (NULL == (rule = zbx_cep_config_get_rule(window->ruleid)))
	{
		cep_window_pool_acquire(&pool);
		cep_window_pool_remove_window(pool, window);
		cep_window_pool_release(&pool);

		goto out;
	}

	limit_update = cep_window_get_limits(rule, &duration, &capacity, &error);
	cep_rule_handle_error(rule, &error, tasks);

	cep_window_lock(window);

	if (SUCCEED == limit_update)
	{
		window->duration = duration;
		window->capacity = capacity;
	}

	events_num  = cep_window_close(rule, window, tasks);

	cep_window_pool_acquire(&pool);
	if (0 != events_num || 0 != window->access_num)
	{
		zbx_uint64_t	start_time = atomic_load(&window->nextcheck);

		window->time_created = now;
		atomic_store(&window->nextcheck, cep_window_get_nextcheck(window, NULL, (time_t)start_time));
		window->flags = CEP_WINDOW_FLAGS_NONE;
		cep_window_pool_enqueue(pool, window);
	}
	else
	{
		cep_window_pool_remove_window(pool, window);
	}
	cep_window_pool_release(&pool);

	cep_window_unlock(window);
	zbx_cep_rule_release(rule);
out:
	zbx_free(error);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

void	cep_window_js_process_event(const zbx_cep_rule_t *rule, zbx_cep_event_context_t *ctx,
		zbx_vector_mw_task_ptr_t *tasks)
{
	zbx_cep_window_pool_t	*pool;
	zbx_cep_window_t	*window;
	char			*error = NULL;
	zbx_uint64_t		opmask = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() ruleid:" ZBX_FS_UI64, __func__, rule->ruleid);

	cep_window_pool_acquire(&pool);
	if (NULL != (window = cep_window_pool_get_or_create_window(pool, rule, ctx, &error)))
		window->access_num++;
	cep_window_pool_release(&pool);

	cep_rule_handle_error(rule, &error, tasks);
	if (NULL == window)
		goto out;

	cep_window_lock(window);

	if (NULL == window->js_script)
		window->js_script = zbx_strdup(NULL, rule->window->script);

	if (0 != window->capacity && zbx_queue_ptr_values_num(&window->hevents) == window->capacity)
	{
		cep_window_unlock(window);

		opmask = cep_rule_event_context_execute_ops(rule, ctx, ZBX_CEP_WHEN_EVENT_EVICTED, tasks);
	}
	else
	{
		if (0 == zbx_queue_ptr_values_num(&window->hevents))
			atomic_store(&window->nextcheck, cep_window_get_nextcheck(window, NULL, 0));

		zbx_queue_ptr_push(&window->hevents, zbx_cep_event_handle_addref(ctx->hevent));

		cep_window_unlock(window);

		opmask = cep_rule_event_execute_close_window(rule, ZBX_CEP_WHEN_EVENT_OCCURRED, ctx);
	}

	if (0 != (opmask & CEP_FLAG(ZBX_CEP_OP_CLOSE_WINDOW)))
	{
		cep_window_lock(window);
		cep_window_close(rule, window, tasks);
		cep_window_unlock(window);
	}

	cep_window_pool_acquire(&pool);
	window->access_num--;
	cep_window_pool_enqueue(pool, window);
	cep_window_pool_release(&pool);

	cep_window_release(window);
out:
	zbx_free(error);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static int	cep_window_js_process_script(zbx_cep_window_t *window, zbx_es_t *es, char **error)
{
	zbx_vector_cep_event_handle_t	hevents;
	zbx_queue_ptr_iter_t		iter;
	zbx_cep_event_handle_t		hevent;
	char				*result = NULL, *errmsg = NULL;
	int				ret = FAIL;
	zbx_cep_js_ctx_t		js_ctx;

	zbx_vector_cep_event_handle_create(&hevents);
	zbx_vector_cep_event_handle_reserve(&hevents, (size_t)zbx_queue_ptr_values_num(&window->hevents));

	zbx_queue_ptr_iter_reset(&window->hevents, &iter);
	while (NULL != (hevent = (zbx_cep_event_handle_t)zbx_queue_ptr_iter_next(&iter)))
		zbx_vector_cep_event_handle_append(&hevents, hevent);

	cep_js_ctx_init(&js_ctx, &hevents);
	cep_js_set_ctx(es, &js_ctx);

	if (FAIL != zbx_es_execute(es, NULL, window->js_code, window->js_codelen, "", &result, &errmsg))
	{
		if (NULL != result && 0 == strcmp(result, "true"))
			ret = SUCCEED;
	}
	else
	{
		*error = zbx_strdcatf(*error, "Cannot execute script: %s.\n", errmsg);
		zbx_free(errmsg);
	}

	cep_js_ctx_clear(&js_ctx);

	zbx_vector_cep_event_handle_destroy(&hevents);

	zbx_free(result);

	return ret;
}

static int	cep_window_js_prepare(zbx_cep_window_t *window, zbx_es_t *es, char **error)
{
	char	*errmsg = NULL;

	if (SUCCEED != zbx_es_init_env(es, cep_config_get_source_ip(), &errmsg))
	{
		*error = zbx_strdcatf(*error, "Cannot initialize script environment: %s.\n", errmsg);
		zbx_free(errmsg);

		return FAIL;
	}

	cep_js_init(es);

	if (SUCCEED != zbx_es_globals_make_readonly(es, &errmsg))
	{
		*error = zbx_strdcatf(*error, "Cannot force read-only globals: %s.\n", errmsg);
		zbx_free(errmsg);

		return FAIL;
	}

	if (NULL == window->js_code)
	{
		if (FAIL == zbx_es_compile(es, window->js_script, &window->js_code, &window->js_codelen, &errmsg))
		{
			*error = zbx_strdcatf(*error, "Cannot compile script: %s.\n", errmsg);
			zbx_free(errmsg);

			return FAIL;
		}
	}

	return SUCCEED;
}

void	cep_window_js_process(zbx_cep_window_t *window, zbx_vector_mw_task_ptr_t *tasks)
{
	char			*error = NULL;
	zbx_es_t		es;
	zbx_cep_window_pool_t	*pool;
	zbx_cep_rule_t		*rule;
	int			ret, limit_update, duration, capacity;
	zbx_uint64_t		opmask = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() ruleid:" ZBX_FS_UI64, __func__, window->ruleid);

	zbx_es_init(&es);

	if (NULL == (rule = zbx_cep_config_get_rule(window->ruleid)))
	{
		cep_window_pool_acquire(&pool);
		cep_window_pool_remove_window(pool, window);
		cep_window_pool_release(&pool);

		goto out;
	}

	limit_update = cep_window_get_limits(rule, &duration, &capacity, &error);
	ret = cep_window_js_prepare(window, &es, &error);

	cep_window_lock(window);

	if (SUCCEED != ret)
		goto enqueue;

	if (SUCCEED == limit_update)
	{
		window->duration = duration;
		window->capacity = capacity;
	}

	if (SUCCEED == cep_window_js_process_script(window, &es, &error))
	{
		zbx_queue_ptr_iter_t	iter;
		int			events_num = zbx_queue_ptr_values_num(&window->hevents);

		zbx_queue_ptr_iter_reset(&window->hevents, &iter);

		for (int i = 0; i < events_num; i++)
		{
			zbx_cep_event_handle_t	hevent;
			zbx_cep_event_context_t	ctx = {.pos = cep_window_event_pos(i, events_num)};

			if (NULL == (hevent = (zbx_cep_event_handle_t)zbx_queue_ptr_iter_next(&iter)))
				break;

			ctx.hevent = zbx_cep_event_handle_addref(hevent);
			opmask |= cep_rule_event_context_execute_ops(rule, &ctx, ZBX_CEP_WHEN_PATTERN_MATCH, tasks);
			cep_event_context_clear(&ctx);
		}
	}

	if (0 != (opmask & CEP_FLAG(ZBX_CEP_OP_CLOSE_WINDOW)))
	{
		cep_window_lock(window);
		cep_window_close(rule, window, tasks);
		cep_window_unlock(window);
	}
enqueue:
	cep_window_pool_acquire(&pool);
	if (0 != zbx_queue_ptr_values_num(&window->hevents) || 0 != window->access_num)
	{
		atomic_store(&window->nextcheck, cep_window_get_nextcheck(window, NULL, 0));
		cep_window_pool_enqueue(pool, window);
	}
	else
	{
		cep_window_pool_remove_window(pool, window);
	}
	cep_window_pool_release(&pool);
	cep_window_unlock(window);
	zbx_cep_rule_release(rule);

out:
	if (NULL != rule)
		cep_rule_handle_error(rule, &error, tasks);

	zbx_free(error);

	if (NULL != es.env && FAIL == zbx_es_destroy_env(&es, &error))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot destroy embedded scripting engine environment: %s", error);
		zbx_free(error);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

void	cep_window_process(zbx_cep_window_t *window, time_t now, zbx_vector_mw_task_ptr_t *tasks)
{
	switch (window->type)
	{
		case ZBX_CEP_WINDOW_SIMPLE:
		case ZBX_CEP_WINDOW_TAG_MATCH:
			cep_window_sliding_process(window, now, tasks);
			break;
		case ZBX_CEP_WINDOW_CAUSAL:
			cep_window_causal_process(window, now, tasks);
			break;
		case ZBX_CEP_WINDOW_PATTERN_MATCH:
			cep_window_js_process(window, tasks);
			break;
	}
}

/*
 * window pool
 */

struct zbx_cep_window_pool
{
	zbx_hashset_t			windows;
	zbx_binary_heap_t		alarm_queue;
	zbx_vector_cep_window_ptr_t	tick_queue;
};

static int	cep_window_compare_by_nextcheck(const void *a1, const void *a2)
{
	const zbx_binary_heap_elem_t	*e1 = (const zbx_binary_heap_elem_t *)a1;
	const zbx_binary_heap_elem_t	*e2 = (const zbx_binary_heap_elem_t *)a2;
	const zbx_cep_window_t		*w1 = (const zbx_cep_window_t *)e1->data;
	const zbx_cep_window_t		*w2 = (const zbx_cep_window_t *)e2->data;
	zbx_uint64_t			t1, t2;

	t1 = atomic_load(&w1->nextcheck);
	t2 = atomic_load(&w2->nextcheck);

	ZBX_RETURN_IF_NOT_EQUAL(t1, t2);

	return 0;
}

zbx_cep_window_pool_t	*cep_window_pool_create(void)
{
	zbx_cep_window_pool_t	*pool;

	pool = (zbx_cep_window_pool_t *)zbx_malloc(NULL, sizeof(zbx_cep_window_pool_t));

	zbx_binary_heap_create(&pool->alarm_queue, cep_window_compare_by_nextcheck, ZBX_BINARY_HEAP_OPTION_EMPTY);
	zbx_vector_cep_window_ptr_create(&pool->tick_queue);

	zbx_hashset_create_ext(&pool->windows, 0, cep_window_ref_hash, cep_window_ref_compare,
			cep_window_ref_clear, ZBX_DEFAULT_MEM_MALLOC_FUNC, ZBX_DEFAULT_MEM_REALLOC_FUNC,
			ZBX_DEFAULT_MEM_FREE_FUNC);

	return pool;
}

void	cep_window_pool_destroy(void *a)
{
	zbx_cep_window_pool_t	*pool = (zbx_cep_window_pool_t *)a;

	while (FAIL == zbx_binary_heap_empty(&pool->alarm_queue))
	{
		zbx_binary_heap_elem_t	*elem = zbx_binary_heap_find_min(&pool->alarm_queue);

		cep_window_release((zbx_cep_window_t *)elem->data);
		zbx_binary_heap_remove_min(&pool->alarm_queue);
	}
	zbx_binary_heap_destroy(&pool->alarm_queue);

	for (int i = 0; i < pool->tick_queue.values_num; i++)
		cep_window_release(pool->tick_queue.values[i]);
	zbx_vector_cep_window_ptr_destroy(&pool->tick_queue);

	zbx_hashset_destroy(&pool->windows);

	zbx_free(pool);
}

zbx_cep_window_t	*cep_window_pool_get_or_create_window(zbx_cep_window_pool_t *pool, const zbx_cep_rule_t *rule,
		zbx_cep_event_context_t *ctx, char **error)
{
	zbx_cep_window_ref_t	*ref, ref_local = {
		.ruleid = rule->ruleid,
		.group_by = rule->window->group_by,
	};
	int		index;
	zbx_cep_event_t	*event;

	if (0 != (ref_local.group_by & ZBX_CEP_GROUP_BY_HOST))
		ref_local.hostid = cep_event_context_get_hostid(ctx);

	if (0 != (ref_local.group_by & ZBX_CEP_GROUP_BY_HOSTGROUP))
		ref_local.hostgroupid = cep_event_context_get_hostgroupid(ctx);

	if (0 != (ref_local.group_by & ZBX_CEP_GROUP_BY_TAG))
	{
		event = cep_event_context_get_event(ctx);
		if (FAIL != (index = cep_event_find_any_tag(event, rule->window->group_tag)))
		{
			ref_local.tag = (char *)rule->window->group_tag;
			ref_local.tag_value = (char *)event->tags.values[index].value;
		}
	}

	ref = (zbx_cep_window_ref_t *)zbx_hashset_insert(&pool->windows, &ref_local, sizeof(ref_local));

	if (NULL == ref->window)
	{
		if (NULL != ref_local.tag)
			ref->tag = zbx_strdup(NULL, ref_local.tag);

		if (NULL != ref_local.tag_value)
			ref->tag_value = zbx_strdup(NULL, ref_local.tag_value);

		ref->window = cep_window_create(rule, ref);

		if (SUCCEED != cep_window_get_limits(rule, &ref->window->duration, &ref->window->capacity, error))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			zbx_hashset_remove_direct(&pool->windows, ref);
			return NULL;
		}
	}

	return cep_window_addref(ref->window);
}

void	cep_window_pool_remove_window(zbx_cep_window_pool_t *pool, zbx_cep_window_t *window)
{
	zbx_hashset_remove_direct(&pool->windows, window->ref);
}

int	cep_window_pool_next_batch(zbx_cep_window_pool_t *pool, time_t now, zbx_vector_cep_window_ptr_t *windows)
{
	zbx_cep_window_t	*window;

	for (int i = pool->tick_queue.values_num ; 0 < i &&
			atomic_load(&pool->tick_queue.values[i - 1]->nextcheck) <= (zbx_uint64_t)now; i--)
	{
		window = pool->tick_queue.values[i - 1];
		zbx_vector_cep_window_ptr_remove_noorder(&pool->tick_queue, i - 1);

		if (CEP_LOCATION_REMOVED == window->location)
		{
			cep_window_release(window);
			continue;
		}

		zbx_vector_cep_window_ptr_append(windows, window);
		window->location = CEP_LOCATION_UNKNOWN;

		if (windows->values_num == windows->values_alloc)
			return windows->values_num;
	}

	while (FAIL == zbx_binary_heap_empty(&pool->alarm_queue))
	{
		zbx_binary_heap_elem_t	*elem = zbx_binary_heap_find_min(&pool->alarm_queue);

		window = (zbx_cep_window_t *)elem->data;

		if (atomic_load(&window->nextcheck) > (zbx_uint64_t)now || windows->values_num == windows->values_alloc)
			return windows->values_num;

		zbx_binary_heap_remove_min(&pool->alarm_queue);

		if (CEP_LOCATION_REMOVED == window->location)
		{
			cep_window_release(window);
			continue;
		}

		window->location = CEP_LOCATION_UNKNOWN;
		zbx_vector_cep_window_ptr_append(windows, window);
	}

	return windows->values_num;
}

void	cep_window_pool_reset_rule(zbx_cep_window_pool_t *pool, zbx_uint64_t ruleid)
{
	zbx_hashset_iter_t	iter;
	zbx_cep_window_ref_t	*ref;

	zbx_hashset_iter_reset(&pool->windows, &iter);
	while (NULL != (ref = (zbx_cep_window_ref_t *)zbx_hashset_iter_next(&iter)))
	{
		if (ref->ruleid == ruleid)
		{
			THIS_SHOULD_NEVER_HAPPEN;
			ref->window->location = CEP_LOCATION_REMOVED;
			zbx_hashset_remove_direct(&pool->windows, ref);
		}
	}
}

void	cep_window_pool_enqueue(zbx_cep_window_pool_t *pool, zbx_cep_window_t *window)
{
	zbx_binary_heap_elem_t	elem;

	if (CEP_LOCATION_REMOVED == window->location)
	{
		cep_window_release(window);
		return;
	}

	if (CEP_LOCATION_QUEUE == window->location)
		return;

	window->location = CEP_LOCATION_QUEUE;

	switch (window->type)
	{
		case ZBX_CEP_WINDOW_SIMPLE:
		case ZBX_CEP_WINDOW_CAUSAL:
			elem.data = cep_window_addref(window);
			zbx_binary_heap_insert(&pool->alarm_queue, &elem);
			break;
		case ZBX_CEP_WINDOW_PATTERN_MATCH:
			zbx_vector_cep_window_ptr_append(&pool->tick_queue, cep_window_addref(window));
			break;
	}
}

void	cep_window_pool_save(zbx_cep_window_pool_t *pool, zbx_dbconn_pool_t *dbpool)
{
	zbx_dbconn_t		*db;
	zbx_db_insert_t		db_insert_groups;
	zbx_db_insert_t		db_insert_events;
	zbx_uint64_t		groupid;
	zbx_hashset_iter_t	iter;
	zbx_cep_window_ref_t	*ref;

	if (0 == pool->windows.num_data)
		return;

	db = zbx_dbconn_pool_acquire_connection(dbpool);

	zbx_dbconn_prepare_insert(db, &db_insert_groups, "cep_group", "cep_groupid", "cep_ruleid", "group_by",
			"groupid", "hostid", "tag", "tag_value", "nextcheck", NULL);
	zbx_dbconn_prepare_insert(db, &db_insert_events, "cep_group_event", "cep_group_eventid", "cep_groupid",
			"eventid", NULL);

	groupid = zbx_dbconn_get_maxid_num(db, "cep_group", pool->windows.num_data);

	zbx_hashset_iter_reset(&pool->windows, &iter);
	while (NULL != (ref = (zbx_cep_window_ref_t *)zbx_hashset_iter_next(&iter)))
	{
		zbx_db_insert_add_values(&db_insert_groups, groupid, ref->ruleid, ref->group_by, ref->hostgroupid,
				ref->hostid, ZBX_NULL2EMPTY_STR(ref->tag), ZBX_NULL2EMPTY_STR(ref->tag_value),
				ref->window->nextcheck);

		while (SUCCEED != zbx_queue_ptr_empty(&ref->window->hevents))
		{
			zbx_cep_event_handle_t	h = (zbx_cep_event_handle_t)zbx_queue_ptr_pop(&ref->window->hevents);

			zbx_db_insert_add_values(&db_insert_events, __UINT64_C(0), groupid,
					zbx_cep_event_handle_eventid(h));

			zbx_cep_event_handle_release(h);
		}

		groupid++;
	}

	zbx_db_insert_autoincrement(&db_insert_events, "cep_group_eventid");

	do
	{
		zbx_dbconn_begin(db);

		zbx_db_insert_execute(&db_insert_groups);
		zbx_db_insert_execute(&db_insert_events);
	}
	while (ZBX_DB_DOWN == zbx_dbconn_commit(db));

	zbx_db_insert_clean(&db_insert_groups);
	zbx_db_insert_clean(&db_insert_events);

	zbx_dbconn_pool_release_connection(dbpool, db);
}

typedef struct
{
	zbx_uint64_t		groupid;
	zbx_cep_window_t	*window;
}
zbx_cep_window_group_t;

static void	cep_window_pool_load_groups(zbx_cep_window_pool_t *pool, zbx_dbconn_t *db, zbx_hashset_t *groups)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	result = zbx_dbconn_select(db, "select cep_groupid,cep_ruleid,group_by,groupid,hostid,tag,tag_value,nextcheck"
					" from cep_group");

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_cep_window_ref_t	ref_local = {0}, *ref;
		zbx_cep_window_group_t	group_local;
		zbx_cep_rule_t		*rule;

		ZBX_STR2UINT64(ref_local.ruleid, row[1]);
		if (NULL == (rule = zbx_cep_config_get_rule(ref_local.ruleid)))
			continue;

		ref_local.group_by = atoi(row[2]);

		if (0 != (ref_local.group_by & ZBX_CEP_GROUP_BY_HOST))
			ZBX_STR2UINT64(ref_local.hostid, row[4]);

		if (0 != (ref_local.group_by & ZBX_CEP_GROUP_BY_HOSTGROUP))
			ZBX_STR2UINT64(ref_local.hostgroupid, row[3]);

		if (0 != (ref_local.group_by & ZBX_CEP_GROUP_BY_TAG))
		{
			ref_local.tag = zbx_strdup(NULL, row[5]);
			ref_local.tag_value = zbx_strdup(NULL, row[6]);
		}

		ref = zbx_hashset_insert(&pool->windows, &ref_local, sizeof(ref_local));
		ref->window = cep_window_create(rule,  ref);
		ref->window->nextcheck = atoi(row[7]);

		ZBX_STR2UINT64(group_local.groupid, row[0]);
		group_local.window = ref->window;

		zbx_hashset_insert(groups, &group_local, sizeof(group_local));

		zbx_cep_rule_release(rule);
	}
	zbx_db_free_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() windows:%d", __func__, pool->windows.num_data);
}

static void	cep_window_pool_load_events(zbx_hashset_t *groups, zbx_dbconn_t *db)
{
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	zbx_cep_t		*cep;
	zbx_cep_window_group_t	*group = NULL;
	zbx_uint64_t		events_num = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	result = zbx_dbconn_select(db, "select cep_groupid,eventid from cep_group_event"
					" order by cep_groupid,cep_group_eventid");

	cep_cache_acquire(&cep);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_uint64_t		groupid, eventid;
		zbx_cep_event_handle_t	h;

		ZBX_STR2UINT64(groupid, row[0]);

		if (NULL == group || group->groupid != groupid)
		{
			if (NULL == (group = zbx_hashset_search(groups, &groupid)))
				continue;
		}

		ZBX_STR2UINT64(eventid, row[1]);
		if (NULL == (h = cep_get_event(cep, eventid)))
			continue;

		events_num++;
		zbx_queue_ptr_push(&group->window->hevents, zbx_cep_event_handle_addref(h));
	}
	zbx_db_free_result(result);

	cep_cache_release(&cep);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() events:" ZBX_FS_UI64, __func__, events_num);
}

void	cep_window_pool_load(zbx_cep_window_pool_t *pool, zbx_dbconn_pool_t *dbpool)
{
	zbx_dbconn_t		*db;
	zbx_hashset_iter_t	iter;
	zbx_cep_window_ref_t	*ref;

	db = zbx_dbconn_pool_acquire_connection(dbpool);

	do
	{
		zbx_hashset_t	groups;

		zbx_hashset_create(&groups, 100, ZBX_DEFAULT_ID_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		zbx_hashset_iter_reset(&pool->windows, &iter);
		while (NULL != (ref = (zbx_cep_window_ref_t *)zbx_hashset_iter_next(&iter)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			zbx_hashset_remove_direct(&pool->windows, ref);
		}

		zbx_dbconn_begin(db);

		cep_window_pool_load_groups(pool, db, &groups);
		cep_window_pool_load_events(&groups, db);

		zbx_hashset_destroy(&groups);
	}
	while (ZBX_DB_DOWN == zbx_dbconn_commit(db));

	zbx_hashset_iter_reset(&pool->windows, &iter);
	while (NULL != (ref = (zbx_cep_window_ref_t *)zbx_hashset_iter_next(&iter)))
		cep_window_pool_enqueue(pool, ref->window);

	while (ZBX_DB_DOWN == zbx_dbconn_execute(db, "truncate table cep_group_event"))
		;
	while (ZBX_DB_DOWN == zbx_dbconn_execute(db, "truncate table cep_group"))
		;

	zbx_dbconn_pool_release_connection(dbpool, db);
}


static void	cep_window_ref_dump(const char *prefix, const zbx_cep_window_ref_t *ref)
{
	zbx_queue_ptr_iter_t		iter;
	zbx_cep_event_handle_t		hevent;
	const zbx_cep_window_t		*window = ref->window;

	zabbix_log(LOG_LEVEL_TRACE, "%sruleid:" ZBX_FS_UI64 " type:%d [group_by:%x hostid:" ZBX_FS_UI64 " hostgroupid:"
			ZBX_FS_UI64 " tag:%s=%s] created:" ZBX_FS_TIME_T " nextcheck:" ZBX_FS_UI64,
			prefix, window->ruleid, window->type, ref->group_by, ref->hostid, ref->hostgroupid,
			ZBX_NULL2STR(ref->tag), ZBX_NULL2STR(ref->tag_value), window->time_created,
			atomic_load(&window->nextcheck));

	if (SUCCEED != zbx_queue_ptr_empty(&window->hevents))
	{
		zbx_vector_cep_event_handle_t	hevents;
		zbx_cep_event_t			**events;
		zbx_cep_t			*cep;

		zbx_vector_cep_event_handle_create(&hevents);
		zbx_vector_cep_event_handle_reserve(&hevents, (size_t)zbx_queue_ptr_values_num(&window->hevents));

		zabbix_log(LOG_LEVEL_TRACE, "%s  eventids:", prefix);

		zbx_queue_ptr_iter_reset(&window->hevents, &iter);
		while (NULL != (hevent = (zbx_cep_event_handle_t)zbx_queue_ptr_iter_next(&iter)))
			zbx_vector_cep_event_handle_append(&hevents, hevent);

		events = (zbx_cep_event_t **)zbx_malloc(NULL, sizeof(zbx_cep_event_t *) * hevents.values_num);

		cep_cache_acquire(&cep);
		cep_get_events_by_handles(cep, hevents.values, hevents.values_num, events);
		cep_cache_release(&cep);

		for (int i = 0; i < hevents.values_num; i++)
		{
			if (NULL == events[i])
				continue;

			zabbix_log(LOG_LEVEL_TRACE, "%s    " ZBX_FS_UI64, prefix, events[i]->eventid);

			zbx_cep_event_release(events[i]);
		}

		zbx_free(events);
		zbx_vector_cep_event_handle_destroy(&hevents);
	}
}

void	cep_window_pool_dump(zbx_cep_window_pool_t *pool)
{
	zbx_hashset_iter_t	iter;
	zbx_cep_window_ref_t	*ref;

	if (0 == pool->windows.num_data)
		return;

	zabbix_log(LOG_LEVEL_TRACE, "windows:");

	zbx_hashset_iter_reset(&pool->windows, &iter);
	while (NULL != (ref = (zbx_cep_window_ref_t *)zbx_hashset_iter_next(&iter)))
		cep_window_ref_dump("  ", ref);
}

