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
#include "cep_rule.h"
#include "zbxalgo.h"
#include "zbxcacheconfig.h"
#include "zbxcommon.h"
#include "zbxexpr.h"
#include "zbxstr.h"
#include "zbxtime.h"
#include "zbxtypes_ext.h"
#include <stdatomic.h>

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

	zabbix_log(LOG_LEVEL_ERR, "[WDN] cep_window_release()");

	if (1 != atomic_fetch_sub(&window->refcount, 1))
		return;

	zabbix_log(LOG_LEVEL_ERR, "[WDN]   free window");

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
	char			*duration;

	window = (zbx_cep_window_t *)zbx_malloc(NULL, sizeof(zbx_cep_window_t));
	window->ruleid = rule->ruleid;
	window->type = rule->window->type;

	duration = zbx_strdup(NULL, rule->window->duration);

	zbx_dc_um_handle_t	*um_handle = zbx_dc_open_user_macros();
	zbx_dc_expand_user_and_func_macros(um_handle, &duration, NULL, 0, NULL);
	zbx_dc_close_user_macros(um_handle);

	if (SUCCEED != zbx_is_time_suffix(duration, &window->duration, ZBX_LENGTH_UNLIMITED))
	{
		THIS_SHOULD_NEVER_HAPPEN_MSG("invalid CEP window duration %s", duration);
		window->duration = SEC_PER_HOUR;
	}

	zbx_free(duration);

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

static void	cep_window_ref_clear(void *a)
{
	zbx_cep_window_ref_t	*ref = (zbx_cep_window_ref_t *)a;

	cep_window_release(ref->window);

	zbx_free(ref->key_tag);
	zbx_free(ref->key_value);
}

void	cep_window_index_init(zbx_hashset_t *windows)
{
	zbx_hashset_create_ext(windows, 0, cep_window_ref_hash, cep_window_ref_compare, cep_window_ref_clear,
			ZBX_DEFAULT_MEM_MALLOC_FUNC, ZBX_DEFAULT_MEM_REALLOC_FUNC, ZBX_DEFAULT_MEM_FREE_FUNC);
}

zbx_cep_window_t	*cep_get_window_or_create(zbx_hashset_t *windows, zbx_cep_rule_t *rule,
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
				ref_local.key_tag = rule->window->group_tag;
				ref_local.key_value = event->tags.values[index].value;
			}
			break;
	}

	ref = (zbx_cep_window_ref_t *)zbx_hashset_insert(windows, &ref_local, sizeof(ref_local));

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

void	cep_event_add_to_window(zbx_cep_event_handle_t hevent, zbx_cep_event_context_t *ctx, zbx_cep_rule_t *rule)
{
	zbx_cep_t		*cep;
	zbx_cep_window_t	*window;

	cep_cache_acquire(&cep);
	window = cep_acquire_window(cep, rule, ctx);
	cep_cache_release(&cep);

	zbx_queue_ptr_push(&window->hevents, zbx_cep_event_handle_addref(hevent));

	cep_window_release(window);
}
