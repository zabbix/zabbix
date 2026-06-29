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

#include "cep_event.h"
#include "cep_api.h"
#include "zbx_cep.h"
#include "zbx_trigger_constants.h"
#include "zbxcacheconfig.h"
#include "zbxcommon.h"
#include "zbxdbhigh.h"
#include "zbxdbwrap.h"

void	cep_event_clear(zbx_cep_event_t *event)
{
	if (NULL != event->r_event)
		zbx_cep_event_release(event->r_event);

	for (int i = 0; i < event->tags.values_num; i++)
	{
		zbx_free(event->tags.values[i].tag);
		zbx_free(event->tags.values[i].value);
	}
	zbx_vector_lite_tag_destroy(&event->tags);

	zbx_vector_db_event_suppress_destroy(&event->suppress);

	zbx_free(event->name);
}

void	zbx_cep_event_release(zbx_cep_event_t *event)
{
	if (1 != atomic_fetch_sub(&event->refcount, 1))
		return;

	cep_event_clear(event);
	zbx_free(event);
}

zbx_cep_event_t	*cep_event_create(zbx_uint64_t eventid, unsigned char source, unsigned char object,
		zbx_uint64_t objectid, const char *name, int clock, int ns, int value, int severity,
		unsigned char flags, zbx_uint64_t cause_eventid, const zbx_vector_tags_ptr_t *tags,
		const zbx_vector_db_event_suppress_t *suppress)
{
	zbx_cep_event_t	*event;

	event = (zbx_cep_event_t *)zbx_malloc(NULL, sizeof(zbx_cep_event_t));
	event->eventid = eventid;
	event->r_event = NULL;
	event->refcount = 1;
	event->origin.source = source;
	event->origin.object = object;
	event->origin.objectid = objectid;
	event->clock = clock;
	event->ns = ns;
	event->value = value;
	event->severity = severity;
	event->suppress_mtime = 0;
	event->name = zbx_strdup(NULL, name);
	event->cause_eventid = 0;
	event->flags = flags;
	event->cause_eventid = cause_eventid;

	zbx_vector_lite_tag_create(&event->tags);
	if (NULL != tags)
	{
		zbx_vector_lite_tag_reserve(&event->tags, (size_t)tags->values_num);
		for (int i = 0; i < tags->values_num; i++)
		{
			zbx_tag_t	tag;

			tag.tag = zbx_strdup(NULL, tags->values[i]->tag);
			tag.value = zbx_strdup(NULL, tags->values[i]->value);
			zbx_vector_lite_tag_append(&event->tags, tag);
		}
	}

	zbx_vector_db_event_suppress_create(&event->suppress);
	if (NULL != suppress)
		zbx_vector_db_event_suppress_append_array(&event->suppress, suppress->values, suppress->values_num);

	return event;
}

zbx_cep_event_t	*cep_event_clone(const zbx_cep_event_t *event)
{
	zbx_cep_event_t	*clone;

	clone = (zbx_cep_event_t *)zbx_malloc(NULL, sizeof(zbx_cep_event_t));
	clone->eventid = event->eventid;
	clone->r_event = (NULL != event->r_event ? cep_event_addref(event->r_event) : NULL);
	clone->refcount = 1;
	clone->origin = event->origin;
	clone->clock = event->clock;
	clone->ns = event->ns;
	clone->value = event->value;
	clone->severity = event->severity;
	clone->suppress_mtime = event->suppress_mtime;
	clone->name = zbx_strdup(NULL, ZBX_NULL2EMPTY_STR(event->name));
	clone->cause_eventid = 0;
	clone->flags = event->flags;

	zbx_vector_lite_tag_create(&clone->tags);
	zbx_vector_lite_tag_reserve(&clone->tags, (size_t)event->tags.values_num);
	for (int i = 0; i < event->tags.values_num; i++)
	{
		zbx_tag_t	tag_local;

		tag_local.tag = zbx_strdup(NULL, event->tags.values[i].tag);
		tag_local.value = zbx_strdup(NULL, event->tags.values[i].value);
		zbx_vector_lite_tag_append(&clone->tags, tag_local);
	}

	zbx_vector_db_event_suppress_create(&clone->suppress);
	zbx_vector_db_event_suppress_append_array(&clone->suppress, event->suppress.values, event->suppress.values_num);

	return clone;
}

zbx_cep_event_t	*cep_event_get_mutable(zbx_cep_event_t *event)
{
	/* with 1 refcount event is not yet added to cache, so other threads cannot access it */
	if (1 == atomic_load(&event->refcount))
		return cep_event_addref(event);

	return cep_event_clone(event);
}

zbx_cep_event_t	*cep_event_addref(zbx_cep_event_t *event)
{
	atomic_fetch_add(&event->refcount, 1);

	return event;
}

int	cep_event_find_tag(zbx_cep_event_t *event, const char *tag)
{
	for (int i = 0; i < event->tags.values_num; i++)
	{
		if (0 == strcmp(event->tags.values[i].tag, tag))
			return i;
	}

	return FAIL;
}

int	cep_event_find_any_tag(const zbx_cep_event_t *event, const char *tags)
{
	for (const char *tag = tags; '\0' != *tag;)
	{
		const char	*next_tag = strchr(tag, '\n');

		size_t	len1 = (NULL == next_tag ? strlen(tag) : next_tag - tag);

		for (int i = 0; i < event->tags.values_num; i++)
		{
			size_t	len2 = strlen(event->tags.values[i].tag);

			if (len1 == len2 && 0 == memcmp(tag, event->tags.values[i].tag, len1))
				return i;
		}

		if (NULL == next_tag)
			break;

		tag = next_tag + 1;
	}

	return FAIL;
}

int	cep_event_validate_tag(zbx_cep_event_t *event, const char *tag, const char *value,
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

/******************************************************************************
 *                                                                            *
 * Purpose: create db event                                                   *
 *                                                                            *
 * Parameters: origin   - [IN] CEP event origin                               *
 *             name     - [IN] event name                                     *
 *             clock    - [IN] event timestamp (seconds)                      *
 *             ns       - [IN] event timestamp (nanoseconds)                  *
 *             severity - [IN] event severity                                 *
 *             value    - [IN] event value                                    *
 *             tags     - [IN] event tags (optional)                          *
 *                                                                            *
 * Return value: pointer to the created db event                              *
 *                                                                            *
 * Comments: The created event is registered as pending for its corresponding *
 *           object as it will be processed later.                            *
 *                                                                            *
 ******************************************************************************/
zbx_db_event	*cep_db_event_create(const zbx_cep_origin_t *origin, const char *name, int clock, int ns,
		int serverity, int value, const zbx_vector_lite_tag_t *tags)
{
	zbx_db_event	*db_event;

	db_event = (zbx_db_event *)zbx_calloc(NULL, 1, sizeof(zbx_db_event));

	db_event->source = origin->source;
	db_event->object = origin->object;
	db_event->objectid = origin->objectid;
	db_event->clock = clock;
	db_event->ns = ns;
	db_event->severity = serverity;
	db_event->value = value;
	db_event->name = zbx_strdup(NULL, name);

	zbx_vector_tags_ptr_create(&db_event->tags);
	if (NULL != tags)
	{
		zbx_vector_tags_ptr_reserve(&db_event->tags, (size_t)tags->values_num);
		for (int i = 0; i < tags->values_num; i++)
		{
			zbx_tag_t	*tag = (zbx_tag_t *)zbx_malloc(NULL, sizeof(zbx_tag_t));

			tag->tag = zbx_strdup(NULL, tags->values[i].tag);
			tag->value = zbx_strdup(NULL, tags->values[i].value);

			zbx_vector_tags_ptr_append(&db_event->tags, tag);
		}
	}

	if (EVENT_SOURCE_TRIGGERS == db_event->source)
	{
		db_event->trigger.triggerid = db_event->objectid;
		zbx_vector_uint64_create(&db_event->trigger.dep_triggerids);

		/* created problem events might get processed by CEP rules - */
		/* need to get more trigger data to expose hosts/groups      */
		if (TRIGGER_VALUE_PROBLEM == value)
		{
			zbx_dc_trigger_t	dc_trigger;
			int			err;

			zbx_dc_config_get_triggers_by_triggerids(&dc_trigger, &origin->objectid, &err, 1);

			if (SUCCEED != err)
			{
				zbx_db_free_event(db_event);
				return NULL;
			}

			db_event->trigger.type = dc_trigger.type;
			db_event->trigger.recovery_mode = dc_trigger.recovery_mode;
			db_event->trigger.expression = dc_trigger.expression;
			db_event->trigger.recovery_expression = dc_trigger.recovery_expression;

			dc_trigger.expression = NULL;
			dc_trigger.recovery_expression = NULL;
			zbx_dc_config_clean_triggers(&dc_trigger, &err, 1);
		}
	}

	return db_event;
}

/******************************************************************************
 *                                                                            *
 * Purpose: register a pending event for corresponding object                 *
 *                                                                            *
 * Parameters: db_event - [IN] database event to register                     *
 *                                                                            *
 ******************************************************************************/
void	cep_event_expect(const zbx_db_event *db_event)
{
	zbx_cep_t		*cep;
	zbx_cep_origin_t	origin = {.source = db_event->source, .object = db_event->object,
					.objectid = db_event->objectid};

	cep_cache_acquire(&cep);
	cep_object_inc_pending(cep, &origin);
	cep_cache_release(&cep);
}

/*
 * event context
 */

/******************************************************************************
 *                                                                            *
 * Purpose: free resources allocated in event context                         *
 *                                                                            *
 ******************************************************************************/
void	cep_event_context_clear(zbx_cep_event_context_t *ctx)
{
	if (NULL != ctx->hosts.values)
	{
		zbx_vector_str_clear_ext(&ctx->hosts, zbx_str_free);
		zbx_vector_str_destroy(&ctx->hosts);
	}

	if (NULL != ctx->groups.values)
	{
		zbx_vector_str_clear_ext(&ctx->groups, zbx_str_free);
		zbx_vector_str_destroy(&ctx->groups);
	}

	if (NULL != ctx->event)
		zbx_cep_event_release(ctx->event);

	if (NULL != ctx->hevent)
		zbx_cep_event_handle_release(ctx->hevent);

	/* db_event is owned by the task, not event context */
}

/******************************************************************************
 *                                                                            *
 * Purpose: load host names associated with event trigger into context        *
 *                                                                            *
 * Parameters: ctx - [IN/OUT] event context to populate with host names       *
 *                                                                            *
 * Comments: If host names are already loaded then do nothing.                *
 *                                                                            *
 ******************************************************************************/
const zbx_vector_str_t	*cep_event_context_get_hosts(zbx_cep_event_context_t *ctx)
{
	zbx_vector_uint64_t	functionids;

	if (NULL == ctx->hosts.values)
	{
		zbx_vector_uint64_create(&functionids);

		zbx_db_trigger_get_all_functionids(&ctx->db_event->trigger, &functionids);

		zbx_vector_str_create(&ctx->hosts);
		zbx_dc_get_host_names_by_functionids(&functionids, &ctx->hosts);

		zbx_vector_uint64_destroy(&functionids);
	}

	return &ctx->hosts;
}

/******************************************************************************
 *                                                                            *
 * Purpose: load host group names associated with event trigger into context  *
 *                                                                            *
 * Parameters: ctx - [IN/OUT] event context to populate with host group names *
 *                                                                            *
 * Comments: If host grouup names are already loaded then do nothing.         *
 *                                                                            *
 ******************************************************************************/
const zbx_vector_str_t	*cep_event_context_get_groups(zbx_cep_event_context_t *ctx)
{
	zbx_vector_uint64_t	functionids;

	if (NULL == ctx->groups.values)
	{
		zbx_vector_uint64_create(&functionids);

		zbx_db_trigger_get_all_functionids(&ctx->db_event->trigger, &functionids);

		zbx_vector_str_create(&ctx->groups);
		zbx_dc_get_hostgroup_names_by_functionids(&functionids, &ctx->groups);

		zbx_vector_uint64_destroy(&functionids);
	}

	return &ctx->groups;
}

static zbx_uint64_t	cep_event_context_get_functionid(zbx_cep_event_context_t *ctx)
{
	if (0 == ctx->functionid)
		ctx->functionid = zbx_db_trigger_get_first_functionid(&ctx->db_event->trigger);

	return ctx->functionid;
}

zbx_uint64_t	cep_event_context_get_hostid(zbx_cep_event_context_t *ctx)
{
	if (0 == ctx->hostid)
	{
		zbx_uint64_t	functionid = cep_event_context_get_functionid(ctx);

		if (0 != functionid)
			ctx->hostid = zbx_dc_get_hostid_by_functionid(functionid);
		else
			ctx->hostid = 0;
	}

	return ctx->hostid;
}

zbx_uint64_t	cep_event_context_get_hostgroupid(zbx_cep_event_context_t *ctx)
{
	if (0 == ctx->hostgroupid)
	{
		zbx_uint64_t	functionid = cep_event_context_get_functionid(ctx);

		if (0 != functionid)
			ctx->hostgroupid = zbx_dc_get_hostgroupid_by_functionid(functionid);
		else
			ctx->hostgroupid = 0;
	}

	return ctx->hostgroupid;
}

zbx_cep_event_t	*cep_event_context_get_event(zbx_cep_event_context_t *ctx)
{
	if (NULL == ctx->event)
	{
		if (NULL != ctx->hevent)
		{
			zbx_cep_t	*cep;

			cep_cache_acquire(&cep);
			cep_get_events_by_handles(cep, &ctx->hevent, 1, &ctx->event);
			cep_cache_release(&cep);
		}
	}

	return ctx->event;
}

zbx_cep_event_t *cep_event_context_get_mutable_event(zbx_cep_event_context_t *ctx)
{
	if (NULL != cep_event_context_get_event(ctx))
	{
		zbx_cep_event_t	*event = cep_event_get_mutable(ctx->event);

		zbx_cep_event_release(ctx->event);
		ctx->event = event;

		return ctx->event;
	}

	return NULL;
}

zbx_db_event *cep_event_context_get_db_event(zbx_cep_event_context_t *ctx)
{
	if (NULL == ctx->db_event)
	{
		zbx_cep_event_t	*event = cep_event_context_get_event(ctx);

		if (NULL != event)
		{
			ctx->db_event = cep_db_event_create(&event->origin, event->name, event->clock, event->ns,
					event->severity, event->value, &event->tags);
		}
	}

	return ctx->db_event;
}

static int	cep_event_context_same_event(zbx_cep_event_context_t *ctx, zbx_cep_event_handle_t hevent)
{
	if (NULL != ctx->hevent)
		return (ctx->hevent == hevent ? SUCCEED : FAIL);

	if (NULL != ctx->event)
	{
		zbx_uint64_t	eventid = zbx_cep_event_handle_eventid(hevent);

		return (eventid == ctx->event->eventid ? SUCCEED : FAIL);
	}

	return FAIL;
}

void	cep_event_context_set_handle(zbx_cep_event_context_t *ctx, zbx_cep_event_handle_t hevent)
{
	if (SUCCEED == cep_event_context_same_event(ctx, hevent))
	{
		if (NULL != ctx->hevent)
			zbx_cep_event_handle_release(ctx->hevent);

		if (NULL != ctx->event)
		{
			zbx_cep_event_release(ctx->event);
			ctx->event = NULL;
		}
	}
	else
	{
		cep_event_context_clear(ctx);
		memset(ctx, 0, sizeof(zbx_cep_event_context_t));
	}

	ctx->hevent = zbx_cep_event_handle_addref(hevent);
}

zbx_uint64_t	cep_event_context_eventid(zbx_cep_event_context_t *ctx)
{
	if (NULL != cep_event_context_get_event(ctx))
		return ctx->event->eventid;

	return 0;
}

const char	*cep_event_context_get_builtin_tag(zbx_cep_event_context_t *ctx, const char *tag)
{
#define CEP_TAG_IS_COPIED	"$IS.COPIED"
#define CEP_TAG_IS_FIRST	"$IS.FIRST"
#define CEP_TAG_IS_LAST		"$IS.LAST"
#define CEP_TAG_IS_SYMPTOM	"$IS.SYMPTOM"
#define CEP_TAG_IS_OPEN		"$IS.OPEN"
#define CEP_VALUE_TRUE		"true"
#define CEP_VALUE_FALSE		"false"
#define CEP_VALUE_UNKNOWN	"unknown"

	zbx_cep_event_t	*event;

	if (0 == strcmp(CEP_TAG_IS_COPIED, tag))
	{
		if (NULL != (event = cep_event_context_get_event(ctx)))
			return (event->flags == ZBX_EVENT_COPIED ? CEP_VALUE_TRUE : CEP_VALUE_FALSE);
	}
	else if(0 == strcmp(CEP_TAG_IS_FIRST, tag))
	{
		return (ctx->pos == CEP_POS_FIRST ? CEP_VALUE_TRUE : CEP_VALUE_FALSE);
	}
	else if(0 == strcmp(CEP_TAG_IS_LAST, tag))
	{
		return (ctx->pos == CEP_POS_LAST ? CEP_VALUE_TRUE : CEP_VALUE_FALSE);
	}
	else if(0 == strcmp(CEP_TAG_IS_SYMPTOM, tag))
	{
		if (NULL != (event = cep_event_context_get_event(ctx)) && 0 != event->cause_eventid)
			return CEP_VALUE_TRUE;

		return CEP_VALUE_FALSE;
	}
	else if(0 == strcmp(CEP_TAG_IS_OPEN, tag))
	{
		if (NULL != (event = cep_event_context_get_event(ctx)))
			return (NULL == event->r_event ? CEP_VALUE_TRUE : CEP_VALUE_FALSE);
	}

	return NULL;

	#undef CEP_VALUE_UNKNOWN
	#undef CEP_VALUE_FALSE
	#undef CEP_VALUE_TRUE
	#undef CEP_TAG_IS_OPEN
	#undef CEP_TAG_IS_SYMPTOM
	#undef CEP_TAG_IS_LAST
	#undef CEP_TAG_IS_FIRST
	#undef CEP_TAG_IS_COPIED
}

void	cep_event_context_resolve_name_macros(zbx_cep_event_context_t *ctx, char **str)
{
	zbx_db_event		*db_event;
	zbx_dc_um_handle_t	*um_handle;

	if (NULL == strchr(*str, '{'))
		return;

	if (NULL == (db_event = cep_event_context_get_db_event(ctx)))
		return;

	um_handle = zbx_dc_open_user_macros();

	zbx_substitute_macros_ext_search(ZBX_TOKEN_SEARCH_REFERENCES | ZBX_TOKEN_SEARCH_EXPRESSION_MACRO, str, NULL, 0,
			zbx_macro_event_name_resolv, um_handle, db_event, NULL);

	zbx_dc_close_user_macros(um_handle);
}

void	cep_event_context_resolve_tag_macros(zbx_cep_event_context_t *ctx, char **str)
{
	zbx_db_event		*db_event;
	zbx_dc_um_handle_t	*um_handle;

	if (NULL == strchr(*str, '{'))
		return;

	if (NULL == (db_event = cep_event_context_get_db_event(ctx)))
		return;

	um_handle = zbx_dc_open_user_macros();

	zbx_substitute_macros(str, NULL, 0, zbx_macro_trigger_tag_resolv, um_handle, db_event, NULL);

	zbx_dc_close_user_macros(um_handle);
}


