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
#include "zbxalgo.h"
#include "zbxcacheconfig.h"
#include "zbxcep.h"
#include "zbxcommon.h"
#include "zbxexpr.h"
#include "zbxstr.h"
#include "zbxtime.h"
#include "zbxtypes_ext.h"
#include <stdatomic.h>

ZBX_PTR_VECTOR_IMPL(cep_window_ptr, zbx_cep_window_t *)

static zbx_hash_t	cep_window_ref_hash(const void *a)
{
	const zbx_cep_window_ref_t	*ref = (const zbx_cep_window_ref_t *)a;

	zbx_hash_t	hash = ZBX_DEFAULT_UINT64_HASH_FUNC(&ref->ruleid);

	if (ZBX_CEP_GROUP_BY_NONE == ref->key_type)
		return hash;

	if (NULL != ref->key_value)
		hash = ZBX_DEFAULT_STRING_HASH_ALGO(ref->key_value, strlen(ref->key_value), hash);

	if (ZBX_CEP_GROUP_BY_TAG != ref->key_type)
		return hash;

	if (NULL != ref->key_tag)
		hash = ZBX_DEFAULT_STRING_HASH_ALGO(ref->key_tag, strlen(ref->key_tag), hash);

	return hash;
}

static int	cep_window_ref_compare(const void *a1, const void *a2)
{
	const zbx_cep_window_ref_t	*ref1 = (const zbx_cep_window_ref_t *)a1;
	const zbx_cep_window_ref_t	*ref2 = (const zbx_cep_window_ref_t *)a2;
	int				ret;

	ZBX_RETURN_IF_NOT_EQUAL(ref1->ruleid, ref2->ruleid);
	ZBX_RETURN_IF_NOT_EQUAL(ref1->key_type, ref2->key_type);

	if (ZBX_CEP_GROUP_BY_NONE == ref1->key_type)
		return 0;

	if (0 != (ret = zbx_strcmp_null(ref1->key_value, ref2->key_value)))
		return ret;

	if (ZBX_CEP_GROUP_BY_TAG != ref1->key_type)
		return 0;

	return zbx_strcmp_null(ref1->key_tag, ref2->key_tag);
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

	pthread_mutex_destroy(&window->lock);

	zbx_free(window);
}

static zbx_cep_window_t	*cep_window_create(const zbx_cep_rule_t *rule)
{
	zbx_cep_window_t	*window;
	int			err;
	char			*duration, *capacity;

	window = (zbx_cep_window_t *)zbx_malloc(NULL, sizeof(zbx_cep_window_t));
	window->ruleid = rule->ruleid;
	window->type = rule->window->type;

	duration = zbx_strdup(NULL, rule->window->duration);
	capacity = zbx_strdup(NULL, rule->window->capacity);

	zbx_dc_um_handle_t	*um_handle = zbx_dc_open_user_macros();

	zbx_dc_expand_user_and_func_macros(um_handle, &duration, NULL, 0, NULL);
	zbx_dc_expand_user_and_func_macros(um_handle, &capacity, NULL, 0, NULL);
	zbx_dc_close_user_macros(um_handle);

	if (SUCCEED != zbx_is_time_suffix(duration, &window->duration, ZBX_LENGTH_UNLIMITED))
	{
		THIS_SHOULD_NEVER_HAPPEN_MSG("invalid CEP window duration %s", duration);
		window->duration = SEC_PER_HOUR;
	}

	if (SUCCEED != zbx_is_int(capacity, &window->capacity))
	{
		THIS_SHOULD_NEVER_HAPPEN_MSG("invalid CEP window capacity %s", capacity);
		window->capacity = 0;
	}

	zbx_free(duration);
	zbx_free(capacity);

	window->nextcheck = 0;
	window->time_created = time(NULL);
	zbx_queue_ptr_create(&window->hevents);

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

	zbx_free(ref->key_tag);
	zbx_free(ref->key_value);
}

void	cep_window_simple_process_event(const zbx_cep_rule_t *rule, zbx_cep_event_handle_t hevent,
		zbx_cep_event_context_t *ctx, zbx_vector_mw_task_ptr_t *tasks)
{
	zbx_cep_window_pool_t	*pool;
	zbx_cep_window_t	*window;
	int			windows_num;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() ruleid:" ZBX_FS_UI64, __func__, rule->ruleid);

	cep_window_pool_acquire(&pool);
	window = cep_window_pool_get_or_create_window(pool, rule, ctx);
	cep_window_pool_acquire(&pool);

	cep_window_lock(window);

	if (zbx_queue_ptr_values_num(&window->hevents) == window->capacity)
	{
		cep_window_unlock(window);

		cep_rule_event_handle_execute_ops(rule, hevent, ZBX_CEP_ON_EVENT_EVICTED, ctx, tasks);
	}
	else
	{
		windows_num = zbx_queue_ptr_values_num(&window->hevents);
		zbx_queue_ptr_push(&window->hevents, zbx_cep_event_handle_addref(hevent));
		if (0 == windows_num)
			atomic_store(&window->nextcheck, (zbx_uint64_t)(ctx->event->clock + window->duration));

		cep_window_unlock(window);

		/* first event added to window - schedule window processing */
		if (0 == windows_num)
		{
			cep_window_pool_acquire(&pool);
			cep_window_pool_add(pool, window);
			cep_window_pool_release(&pool);
		}
	}

	cep_window_release(window);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

void	cep_window_simple_process(zbx_cep_window_t *window, time_t now, zbx_vector_mw_task_ptr_t *tasks)
{
	zbx_cep_config_handle_t	cfg;
	const zbx_cep_rule_t	*rule = NULL;
	int			pending_num;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() ruleid:" ZBX_FS_UI64, __func__, window->ruleid);

	cfg = zbx_cep_config_open();

	cep_window_lock(window);

	while (SUCCEED != zbx_queue_ptr_empty(&window->hevents))
	{
		zbx_cep_event_handle_t	h;
		zbx_cep_event_context_t	ctx = {0};

		h = (zbx_cep_event_handle_t)zbx_queue_ptr_peek(&window->hevents);
		ctx.hevent = zbx_cep_event_handle_addref(h);

		if (NULL != cep_event_context_acquire_event(&ctx))
		{
			if (ctx.event->clock + window->duration > now)
			{
				cep_event_context_clear(&ctx);
				break;
			}

			if (NULL == rule)
				rule = zbx_cep_config_get_rule(cfg, window->ruleid);

			if (NULL != rule)
			{
				cep_rule_event_handle_execute_ops(rule, ctx.hevent, ZBX_CEP_ON_EVENT_EVICTED, &ctx,
						tasks);
			}
		}

		zbx_cep_event_handle_release(h);
		zbx_queue_ptr_pop(&window->hevents);
		cep_event_context_clear(&ctx);
	}

	if (0 != (pending_num = zbx_queue_ptr_values_num(&window->hevents)))
	{
		zbx_cep_event_handle_t	h = (zbx_cep_event_handle_t)zbx_queue_ptr_peek(&window->hevents);
		zbx_cep_event_t		*event;

		zbx_cep_get_events_by_handles(&h, 1, &event);
		atomic_store(&window->nextcheck, (zbx_uint64_t)(event->clock + window->duration));
		zbx_cep_event_release(event);
	}

	cep_window_unlock(window);

	if (0 != pending_num)
	{
		zbx_cep_window_pool_t	*pool;

		cep_window_pool_acquire(&pool);
		cep_window_pool_add(pool, window);
		cep_window_pool_release(&pool);
	}

	zbx_cep_config_close(cfg);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

void	cep_window_process(zbx_cep_window_t *window, time_t now, zbx_vector_mw_task_ptr_t *tasks)
{
	switch (window->type)
	{
		case ZBX_CEP_WINDOW_SIMPLE:
			cep_window_simple_process(window, now, tasks);
			break;
		case ZBX_CEP_WINDOW_CAUSE_SYMPTOM:
		case ZBX_CEP_WINDOW_TAG_MATCH:
		case ZBX_CEP_WINDOW_PATTERN_MATCH:
			/* TODO: implement */
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
		zbx_cep_event_context_t *ctx)
{
	zbx_cep_window_ref_t	*ref, ref_local = {
		.ruleid = rule->ruleid,
		.key_type = rule->window->group_by,
	};
	int		index;
	zbx_cep_event_t	*event;

	switch (ref_local.key_type)
	{
		case ZBX_CEP_GROUP_BY_NONE:
			break;
		case ZBX_CEP_GROUP_BY_HOST:
			cep_event_context_load_hosts(ctx);
			if (0 != ctx->hosts.values_num)
				ref_local.key_value = ctx->hosts.values[0];
			break;
		case ZBX_CEP_GROUP_BY_HOSTGROUP:
			cep_event_context_load_groups(ctx);
			if (0 != ctx->groups.values_num)
				ref_local.key_value = ctx->groups.values[0];
			break;
		case ZBX_CEP_GROUP_BY_TAG:
			event = cep_event_context_acquire_event(ctx);
			if (FAIL != (index = cep_event_find_tag(event, rule->window->group_tag)))
			{
				ref_local.key_tag = (char *)rule->window->group_tag;
				ref_local.key_value = (char *)event->tags.values[index].value;
			}
			break;
	}

	ref = (zbx_cep_window_ref_t *)zbx_hashset_insert(&pool->windows, &ref_local, sizeof(ref_local));

	if (NULL == ref->window)
	{
		if (NULL != ref_local.key_tag)
			ref->key_tag = zbx_strdup(NULL, ref_local.key_tag);

		if (NULL != ref_local.key_value)
			ref->key_value = zbx_strdup(NULL, ref_local.key_value);

		ref->window = cep_window_create(rule);
	}

	return cep_window_addref(ref->window);
}

int	cep_window_pool_next_batch(zbx_cep_window_pool_t *pool, time_t now, zbx_vector_cep_window_ptr_t *windows)
{
	for (int i = pool->tick_queue.values_num ; 0 < i &&
			atomic_load(&pool->tick_queue.values[i - 1]->nextcheck) <= (zbx_uint64_t)now; i--)
	{
		zbx_vector_cep_window_ptr_append(windows, pool->tick_queue.values[i - 1]);
		zbx_vector_cep_window_ptr_remove_noorder(&pool->tick_queue, i - 1);

		if (windows->values_num == windows->values_alloc)
			return windows->values_num;
	}

	while (FAIL == zbx_binary_heap_empty(&pool->alarm_queue))
	{
		zbx_binary_heap_elem_t	*elem = zbx_binary_heap_find_min(&pool->alarm_queue);
		zbx_cep_window_t	*window = (zbx_cep_window_t *)elem->data;

		if (atomic_load(&window->nextcheck) > (zbx_uint64_t)now || windows->values_num == windows->values_alloc)
			return windows->values_num;

		zbx_vector_cep_window_ptr_append(windows, window);
		zbx_binary_heap_remove_min(&pool->alarm_queue);
	}

	return windows->values_num;
}

void	cep_window_pool_add(zbx_cep_window_pool_t *pool, zbx_cep_window_t *window)
{
	zbx_binary_heap_elem_t	elem;

	switch (window->type)
	{
		case ZBX_CEP_WINDOW_SIMPLE:
		case ZBX_CEP_WINDOW_CAUSE_SYMPTOM:
			elem.data = cep_window_addref(window);
			zbx_binary_heap_insert(&pool->alarm_queue, &elem);
			break;
		case ZBX_CEP_WINDOW_PATTERN_MATCH:
			zbx_vector_cep_window_ptr_append(&pool->tick_queue, cep_window_addref(window));
			break;
	}
}

static void	cep_window_ref_dump(const char *prefix, const zbx_cep_window_ref_t *ref)
{
	zbx_queue_ptr_iter_t		iter;
	zbx_cep_event_handle_t		hevent;
	const zbx_cep_window_t		*window = ref->window;

	zabbix_log(LOG_LEVEL_TRACE, "%sruleid:" ZBX_FS_UI64 " type:%d [group_by:%d key:%s value:%s] created:"
			ZBX_FS_TIME_T,
			prefix, window->ruleid, window->type, ref->key_type, ZBX_NULL2STR(ref->key_tag),
			ZBX_NULL2STR(ref->key_value), window->time_created);

	if (SUCCEED != zbx_queue_ptr_empty(&window->hevents))
	{
		zbx_vector_cep_event_handle_t	hevents;
		zbx_cep_event_t			**events;

		zbx_vector_cep_event_handle_create(&hevents);
		zbx_vector_cep_event_handle_reserve(&hevents, (size_t)zbx_queue_ptr_values_num(&window->hevents));

		zabbix_log(LOG_LEVEL_TRACE, "%s  eventids:", prefix);

		zbx_queue_ptr_iter_reset(&window->hevents, &iter);
		while (NULL != (hevent = (zbx_cep_event_handle_t)zbx_queue_ptr_iter_next(&iter)))
			zbx_vector_cep_event_handle_append(&hevents, hevent);

		events = (zbx_cep_event_t **)zbx_malloc(NULL, hevents.values_num);
		zbx_cep_get_events_by_handles(hevents.values, hevents.values_num, events);

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

