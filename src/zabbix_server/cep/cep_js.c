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

#include "cep_js.h"
#include "libs/zbxembed/duktape.h"
#include "zbx_cep.h"
#include "libs/zbxembed/embed.h"
#include "zbxalgo.h"
#include "zbxembed.h"

#define CEP_EVENTS_STASH_KEY	"cep_events"

typedef struct
{
	zbx_uint64_t	eventid;
	zbx_cep_event_t	*event;
}
zbx_cep_event_ref_t;

/******************************************************************************
 *                                                                            *
 * Purpose: retrieve CEP JavaScript context from Duktape heap stash           *
 *                                                                            *
 * Parameters: ctx - [IN] Duktape context                                     *
 *                                                                            *
 * Return value: pointer to the CEP JavaScript context                        *
 *                                                                            *
 ******************************************************************************/
static zbx_cep_js_ctx_t	*cep_js_get_ctx(duk_context *ctx)
{
	zbx_cep_js_ctx_t	*js_ctx;

	duk_push_heap_stash(ctx);
	duk_get_prop_string(ctx, -1, CEP_EVENTS_STASH_KEY);
	js_ctx = duk_require_pointer(ctx, -1);
	duk_pop_2(ctx);

	return js_ctx;
}

/******************************************************************************
 *                                                                            *
 * Purpose: handle property access on a CEP event JavaScript proxy object     *
 *                                                                            *
 * Parameters: ctx - [IN] Duktape context                                     *
 *                        args: 0=target, 1=key, 2=receiver                   *
 *                                                                            *
 * Return value: number of Duktape return values (always 1)                   *
 *                                                                            *
 * Comments: Tag arrays are cached on the target object after first access.   *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	cep_js_event_proxy_get(duk_context *ctx)
{
	/* args: 0=target, 1=key, 2=receiver */
	zbx_cep_js_ctx_t	*js_ctx = cep_js_get_ctx(ctx);
	zbx_cep_event_ref_t	*ref;
	zbx_uint64_t		eventid;
	const char		*key;

	key = duk_require_string(ctx, 1);
	if (1 == duk_has_prop_string(ctx, 0, key))
	{
		duk_get_prop_string(ctx, 0, key);
		return 1;
	}

	/* look up backing event */
	duk_get_prop_string(ctx, 0, "eventid");
	eventid = (zbx_uint64_t)duk_require_number(ctx, -1);
	duk_pop(ctx);

	if (NULL == (ref = zbx_hashset_search(&js_ctx->index, &eventid)))
		return duk_error(ctx, DUK_ERR_ERROR, "event not found: " ZBX_FS_UI64, eventid);

	if (0 == strcmp(key, "name"))
	{
		duk_push_string(ctx, ref->event->name);
		return 1;
	}
	if (0 == strcmp(key, "severity"))
	{
		duk_push_int(ctx, ref->event->severity);
		return 1;
	}
	if (0 == strcmp(key, "clock"))
	{
		duk_push_int(ctx, ref->event->clock);
		return 1;
	}
	if (0 == strcmp(key, "ns"))
	{
		duk_push_int(ctx, ref->event->ns);
		return 1;
	}
	if (0 == strcmp(key, "tags"))
	{
		zbx_vector_lite_tag_t	*tags = &ref->event->tags;
		duk_idx_t		arr_idx;

		arr_idx = duk_push_array(ctx);
		for (int i = 0; i < tags->values_num; i++)
		{
			zbx_tag_t	*tag = &tags->values[i];
			duk_idx_t	obj_idx;

			obj_idx = duk_push_object(ctx);
			duk_push_string(ctx, tag->tag);
			duk_put_prop_string(ctx, obj_idx, "tag");
			duk_push_string(ctx, tag->value);
			duk_put_prop_string(ctx, obj_idx, "value");

			duk_put_prop_index(ctx, arr_idx, i);
		}
		return 1;
	}
	if (0 == strcmp(key, "is_open"))
	{
		duk_push_boolean(ctx, NULL == ref->event->r_event);
		return 1;
	}
	if (0 == strcmp(key, "is_suppressed"))
	{
		duk_push_boolean(ctx, 0 != ref->event->suppress.values_num);
		return 1;
	}
	if (0 == strcmp(key, "is_symptom"))
	{
		duk_push_boolean(ctx, 0 != ref->event->cause_eventid);
		return 1;
	}
	if (0 == strcmp(key, "is_copied"))
	{
		duk_push_boolean(ctx, ZBX_EVENT_COPIED == ref->event->flags);
		return 1;
	}

	duk_push_undefined(ctx);
	return 1;
}

/******************************************************************************
 *                                                                            *
 * Purpose: reject property assignment on a CEP event JavaScript proxy object *
 *                                                                            *
 * Parameters: ctx - [IN] Duktape context                                     *
 *                        args: 0=target, 1=key, 2=value, 3=receiver         *
 *                                                                            *
 * Return value: always throws a TypeError                                    *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	cep_js_event_proxy_set(duk_context *ctx)
{
	/* args: 0=target, 1=key, 2=value, 3=receiver */
	return duk_error(ctx, DUK_ERR_TYPE_ERROR, "event properties are read-only");
}

/******************************************************************************
 *                                                                            *
 * Purpose: push a read-only JavaScript proxy for a CEP event onto the        *
 *          Duktape stack                                                     *
 *                                                                            *
 * Parameters: ctx   - [IN] Duktape context                                   *
 *             event - [IN] CEP event to wrap                                 *
 *                                                                            *
 ******************************************************************************/
static void	cep_js_push_event_proxy(duk_context *ctx, zbx_cep_event_t *event)
{
	/* target object */
	duk_push_object(ctx);
	duk_push_number(ctx, (double)event->eventid);
	duk_put_prop_string(ctx, -2, "eventid");

	/* handler object with get trap */
	duk_push_object(ctx);
	duk_push_c_function(ctx, cep_js_event_proxy_get, 3);
	duk_put_prop_string(ctx, -2, "get");

	/* force read-only  properties */
	duk_push_c_function(ctx, cep_js_event_proxy_set, 4);
	duk_put_prop_string(ctx, -2, "set");

	duk_push_proxy(ctx, 0);
}

/******************************************************************************
 *                                                                            *
 * Purpose: push the CEP event array onto the Duktape stack                   *
 *                                                                            *
 * Parameters: ctx - [IN] Duktape context                                     *
 *                                                                            *
 * Return value: number of Duktape return values (always 1)                   *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	cep_js_get_events(duk_context *ctx)
{
	zbx_cep_js_ctx_t	*js_ctx = cep_js_get_ctx(ctx);
	duk_idx_t		arr_idx;

	arr_idx = duk_push_array(ctx);

	for (int i = 0; i < js_ctx->events_num; i++)
	{
		cep_js_push_event_proxy(ctx, js_ctx->events[i]);
		duk_put_prop_index(ctx, arr_idx, (duk_uarridx_t)i);
	}

	return 1;
}

/******************************************************************************
 *                                                                            *
 * Purpose: register CEP JavaScript functions in the scripting environment    *
 *                                                                            *
 * Parameters: es - [IN/OUT] embedded scripting environment                   *
 *                                                                            *
 ******************************************************************************/
void	cep_js_init(zbx_es_t *es)
{
	duk_push_c_function(es->env->ctx, cep_js_get_events, 0);
	duk_put_global_string(es->env->ctx, "cep_get_events");
}

/******************************************************************************
 *                                                                            *
 * Purpose: initialize CEP JavaScript context with events from handles        *
 *                                                                            *
 * Parameters: js      - [OUT] CEP JavaScript context to initialize           *
 *             hevents - [IN] event handles to resolve and index              *
 *                                                                            *
 * Comments: Handles of deleted events are silently skipped.                  *
 *                                                                            *
 ******************************************************************************/
void	cep_js_ctx_init(zbx_cep_js_ctx_t *js, zbx_vector_cep_event_handle_t *hevents)
{
	zbx_hashset_create(&js->index, (size_t)hevents->values_num, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	js->events_num = 0;

	if (0 < hevents->values_num)
	{
		js->events = (zbx_cep_event_t **)zbx_malloc(NULL, sizeof(zbx_cep_event_t *) * hevents->values_num);
		zbx_cep_get_events_by_handles(hevents->values, hevents->values_num, js->events);

		for (int i = 0; i < hevents->values_num; i++)
		{
			if (NULL != js->events[i])
				js->events[js->events_num++] = js->events[i];
		}

		for (int i = 0; i < js->events_num; i++)
		{
			zbx_cep_event_ref_t	ref_local = {.eventid = js->events[i]->eventid, .event = js->events[i]};

			zbx_hashset_insert(&js->index, &ref_local, sizeof(ref_local));
		}
	}
	else
		js->events = NULL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: release resources held by a CEP JavaScript context                *
 *                                                                            *
 * Parameters: js - [IN/OUT] CEP JavaScript context to clear                  *
 *                                                                            *
 ******************************************************************************/
void	cep_js_ctx_clear(zbx_cep_js_ctx_t *js)
{
	zbx_hashset_destroy(&js->index);

	for (int i = 0; i < js->events_num; i++)
		zbx_cep_event_release(js->events[i]);
	zbx_free(js->events);
}

/******************************************************************************
 *                                                                            *
 * Purpose: store CEP JavaScript context pointer in the Duktape heap stash    *
 *                                                                            *
 * Parameters: es - [IN] embedded scripting environment                       *
 *             js - [IN] CEP JavaScript context to store                      *
 *                                                                            *
 ******************************************************************************/
void	cep_js_set_ctx(zbx_es_t *es, zbx_cep_js_ctx_t *js)
{
	duk_push_heap_stash(es->env->ctx);
	duk_push_pointer(es->env->ctx, js);
	duk_put_prop_string(es->env->ctx, -2, CEP_EVENTS_STASH_KEY);
	duk_pop(es->env->ctx);
}


