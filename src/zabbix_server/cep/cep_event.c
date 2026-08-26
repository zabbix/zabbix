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
#include "zabbix_server/cep/cep_task.h"
#include "zbx_cep.h"
#include "zbx_trigger_constants.h"
#include "zbxcacheconfig.h"
#include "zbxcachevalue.h"
#include "zbxcommon.h"
#include "zbxdb.h"
#include "zbxdbhigh.h"
#include "zbxdbwrap.h"

/******************************************************************************
 *                                                                            *
 * Purpose: release a reference to a cep event, freeing it once the last      *
 *          reference is released                                             *
 *                                                                            *
 * Parameters: event - [IN] event to release                                  *
 *                                                                            *
 ******************************************************************************/
void	zbx_cep_event_release(zbx_cep_event_t *event)
{
	if (1 != atomic_fetch_sub(&event->refcount, 1))
		return;

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
	zbx_free(event);
}

/******************************************************************************
 *                                                                            *
 * Purpose: create an  event                                                  *
 *                                                                            *
 * Parameters: eventid       - [IN] event identifier                          *
 *             source        - [IN] event source                              *
 *             object        - [IN] event object type                         *
 *             objectid      - [IN] identifier of the related object          *
 *             name          - [IN] event name                                *
 *             clock         - [IN] event time, seconds                       *
 *             ns            - [IN] event time, nanoseconds                   *
 *             value         - [IN] event value                               *
 *             severity      - [IN] event severity                            *
 *             flags         - [IN] event flags                               *
 *             cause_eventid - [IN] identifier of the cause event             *
 *             tags          - [IN] event tags, can be NULL                   *
 *             suppress      - [IN] event suppress data, can be NULL          *
 *                                                                            *
 * Return value: the created event                                            *
 *                                                                            *
 ******************************************************************************/
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

/******************************************************************************
 *                                                                            *
 * Purpose: clone an event                                                    *
 *                                                                            *
 * Parameters: event - [IN] event to clone                                    *
 *                                                                            *
 * Return value: the cloned event                                             *
 *                                                                            *
 ******************************************************************************/
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
	clone->cause_eventid = event->cause_eventid;
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

/******************************************************************************
 *                                                                            *
 * Purpose: get a mutable reference to an event                               *
 *                                                                            *
 * Parameters: event - [IN] event to get a mutable reference for              *
 *                                                                            *
 * Return value: a reference to the event if it is not yet shared, or a       *
 *               clone of the event otherwise                                 *
 *                                                                            *
 ******************************************************************************/
zbx_cep_event_t	*cep_event_get_mutable(zbx_cep_event_t *event)
{
	/* with 1 refcount event is not yet added to cache, so other threads cannot access it */
	if (1 == atomic_load(&event->refcount))
		return cep_event_addref(event);

	return cep_event_clone(event);
}

/******************************************************************************
 *                                                                            *
 * Purpose: acquire a reference to an event                                   *
 *                                                                            *
 * Parameters: event - [IN] event to acquire a reference for                  *
 *                                                                            *
 * Return value: the same event, with its reference count incremented         *
 *                                                                            *
 ******************************************************************************/
zbx_cep_event_t	*cep_event_addref(zbx_cep_event_t *event)
{
	atomic_fetch_add(&event->refcount, 1);

	return event;
}

/******************************************************************************
 *                                                                            *
 * Purpose: find a tag by name in an event                                    *
 *                                                                            *
 * Parameters: event - [IN] event to search                                   *
 *             tag   - [IN] tag name to search for                            *
 *                                                                            *
 * Return value: index of the tag if found, FAIL otherwise                    *
 *                                                                            *
 ******************************************************************************/
int	cep_event_find_tag(zbx_cep_event_t *event, const char *tag)
{
	for (int i = 0; i < event->tags.values_num; i++)
	{
		if (0 == strcmp(event->tags.values[i].tag, tag))
			return i;
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: find the first tag of an event matching any of a set of tag       *
 *          names                                                             *
 *                                                                            *
 * Parameters: event - [IN] event to search                                   *
 *             tags  - [IN] newline-separated list of tag names to match      *
 *                                                                            *
 * Return value: index of the first matching tag if found, FAIL otherwise     *
 *                                                                            *
 ******************************************************************************/
int	cep_event_find_any_tag(const zbx_cep_event_t *event, const char *tags)
{
	for (const char *tag = tags; '\0' != *tag;)
	{
		const char	*next_tag = strchr(tag, '\n');

		size_t	len1 = (NULL == next_tag ? strlen(tag) : (size_t)(next_tag - tag));

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

/******************************************************************************
 *                                                                            *
 * Purpose: validate that an event does not already have a tag matching a     *
 *          given name and value                                              *
 *                                                                            *
 * Parameters: event       - [IN] event to validate                           *
 *             tag         - [IN] tag name to validate                        *
 *             value       - [IN] tag value to validate                       *
 *             match_index - [OUT] index of the first tag matching by name    *
 *                           but not by value, can be NULL                    *
 *                                                                            *
 * Return value: SUCCEED - no tag with the given name and value exists        *
 *               FAIL - a tag with the given name and value already exists    *
 *                                                                            *
 ******************************************************************************/
int	cep_event_validate_tag(zbx_cep_event_t *event, const char *tag, const char *value, int *match_index)
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
 * Purpose: register a pending events for corresponding objects               *
 *                                                                            *
 * Parameters: db_events  - [IN] database events to expect                    *
 *             events_num - [IN] number of events to expect                   *
 *                                                                            *
 ******************************************************************************/
void	cep_events_expect(zbx_db_event * const *db_events, int events_num)
{
	zbx_cep_t		*cep;

	cep_cache_acquire(&cep);
	for (int i = 0; i < events_num; i++)
	{
		zbx_cep_origin_t	origin = {
			.source = (unsigned char)db_events[i]->source,
			.object = (unsigned char)db_events[i]->object,
			.objectid = db_events[i]->objectid
		};

		cep_object_inc_pending(cep, &origin);
	}
	cep_cache_release(&cep);
}

int	db_event_suppress_compare(const void *a1, const void *a2)
{
	const zbx_db_event_suppress_t	*s1 = (const zbx_db_event_suppress_t *)a1;
	const zbx_db_event_suppress_t	*s2 = (const zbx_db_event_suppress_t *)a2;

	ZBX_RETURN_IF_NOT_EQUAL(s1->maintenanceid, s2->maintenanceid);
	ZBX_RETURN_IF_NOT_EQUAL(s1->cep_ruleid, s2->cep_ruleid);

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: add suppress records to event and update suppression time         *
 *                                                                            *
 * Parameters: event        - [IN/OUT]                                        *
 *             suppress     - [IN] event suppress data to add                 *
 *             suppress_num - [IN] number of suppress entries                 *
 *                                                                            *
 ******************************************************************************/
void	cep_event_add_suppress(zbx_cep_event_t *event, const zbx_db_event_suppress_t *suppress,
		int suppress_num)
{
	int	event_suppress_num = event->suppress.values_num;

	for (int i = 0; i < suppress_num; i++)
	{
		int	index;

		if (FAIL == (index = zbx_vector_db_event_suppress_search(&event->suppress, suppress[i],
				db_event_suppress_compare)))
		{
			zbx_vector_db_event_suppress_append(&event->suppress, suppress[i]);
		}
	}

	if (event->suppress.values_num == event_suppress_num)
		return;

	if (0 == event_suppress_num)
		event->suppress_mtime = time(NULL);
}


/******************************************************************************
 *                                                                            *
 * Purpose: remove suppress records from event and update suppression time    *
 *                                                                            *
 * Parameters: event        - [IN/OUT]                                        *
 *             suppress     - [IN] event suppress data to remove              *
 *             suppress_num - [IN] number of suppress entries                 *
 *                                                                            *
 ******************************************************************************/
void	cep_event_remove_suppress(zbx_cep_event_t *event, const zbx_db_event_suppress_t *suppress,
		int suppress_num)
{
	for (int i = 0; i < suppress_num; i++)
	{
		int	index;

		if (FAIL != (index = zbx_vector_db_event_suppress_search(&event->suppress, suppress[i],
				db_event_suppress_compare)))
		{
			zbx_vector_db_event_suppress_remove_noorder(&event->suppress, index);
		}
	}

	if (0 == event->suppress.values_num)
	{
		zbx_vector_db_event_suppress_reset(&event->suppress);
		event->suppress_mtime = time(NULL);
	}
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

	if (NULL != ctx->db_event_local)
		zbx_db_free_event(ctx->db_event_local);

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

/******************************************************************************
 *                                                                            *
 * Purpose: lazy load and return the function id of the first function of     *
 *          an event context's trigger                                        *
 *                                                                            *
 * Parameters: ctx - [IN/OUT] event context                                   *
 *                                                                            *
 * Return value: the function id                                              *
 *                                                                            *
 ******************************************************************************/
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

/******************************************************************************
 *                                                                            *
 * Purpose: lazy load and return the host group id associated with an event   *
 *          context's first function                                          *
 *                                                                            *
 * Parameters: ctx - [IN/OUT] event context                                   *
 *                                                                            *
 * Return value: the host group id, or 0 if it could not be determined        *
 *                                                                            *
 ******************************************************************************/
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

/******************************************************************************
 *                                                                            *
 * Purpose: lazy load and return the event associated with an event context   *
 *                                                                            *
 * Parameters: ctx - [IN/OUT] event context                                   *
 *                                                                            *
 * Return value: the associated event, or NULL if it could not be resolved    *
 *                                                                            *
 ******************************************************************************/
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

/******************************************************************************
 *                                                                            *
 * Purpose: get a mutable reference to the event associated with an event     *
 *          context                                                           *
 *                                                                            *
 * Parameters: ctx - [IN/OUT] event context                                   *
 *                                                                            *
 * Return value: the mutable event, or NULL if it could not be resolved       *
 *                                                                            *
 * Comments: Replaces the context's stored event reference with the mutable   *
 *           one.                                                             *
 *                                                                            *
 ******************************************************************************/
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


/******************************************************************************
 *                                                                            *
 * Purpose: lazily create and return the db event associated with an event    *
 *          context                                                           *
 *                                                                            *
 * Parameters: ctx - [IN/OUT] event context                                   *
 *                                                                            *
 * Return value: the db event, or NULL if it could not be resolved            *
 *                                                                            *
 * Comments: The db event is created from the event backed by with context.   *
 *                                                                            *
 ******************************************************************************/
const zbx_db_event *cep_event_context_get_db_event(zbx_cep_event_context_t *ctx)
{
	if (NULL == ctx->db_event)
	{
		if (NULL == ctx->db_event_local)
		{
			zbx_cep_event_t	*event = cep_event_context_get_event(ctx);

			if (NULL != event)
			{
				ctx->db_event_local = cep_db_event_create(&event->origin, event->name, event->clock,
						event->ns, event->severity, event->value, &event->tags);
			}
		}
		ctx->db_event = ctx->db_event_local;
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

/******************************************************************************
 *                                                                            *
 * Purpose: check whether an event handle refers to the same event as an      *
 *          event context                                                     *
 *                                                                            *
 * Parameters: ctx    - [IN] event context                                    *
 *             hevent - [IN] event handle to compare against                  *
 *                                                                            *
 * Return value: SUCCEED - the handle refers to the same event                *
 *               FAIL - the handle refers to a different event, or the        *
 *                      context has neither a handle nor an event set         *
 *                                                                            *
 ******************************************************************************/
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

/******************************************************************************
 *                                                                            *
 * Purpose: return the event id associated with an event context              *
 *                                                                            *
 * Parameters: ctx - [IN] event context                                       *
 *                                                                            *
 * Return value: the event id, or 0 if it could not be resolved               *
 *                                                                            *
 ******************************************************************************/
zbx_uint64_t	cep_event_context_eventid(zbx_cep_event_context_t *ctx)
{
	if (NULL != cep_event_context_get_event(ctx))
		return ctx->event->eventid;

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: resolve macros in a string using an event context, restricted     *
 *          to a given macro search scope                                     *
 *                                                                            *
 * Parameters: ctx      - [IN/OUT] event context                              *
 *             scope    - [IN] macro token search scope                       *
 *             resolver - [IN] macro resolver function                        *
 *             str      - [IN/OUT] string to resolve macros in                   *
 *                                                                            *
 ******************************************************************************/
static void	cep_event_context_resolve_macros(zbx_cep_event_context_t *ctx, int scope,
		zbx_macro_resolv_func_t resolver, char **str)
{
	const zbx_db_event	*db_event;
	zbx_dc_um_handle_t	*um_handle;
	zbx_dbconn_t		*db;

	if (NULL == strchr(*str, '{') && NULL == strchr(*str, '$'))
		return;

	if (NULL == (db_event = cep_event_context_get_db_event(ctx)))
		return;

	um_handle = zbx_dc_open_user_macros();

	db = zbx_dbconn_pool_acquire_connection(ctx->dbpool);
	zbx_db_stash_connection(db);

	zbx_substitute_macros_ext_search(scope, str, NULL, 0, resolver, um_handle, db_event, NULL);

	zbx_db_unstash_connection(db);
	zbx_dbconn_pool_release_connection(ctx->dbpool, db);

	zbx_dc_close_user_macros(um_handle);

	zbx_vc_flush_stats();
}

/******************************************************************************
 *                                                                            *
 * Purpose: resolve macros in an event name                                   *
 *                                                                            *
 * Parameters: ctx - [IN/OUT] event context                                   *
 *             str - [IN/OUT] string to resolve macros in                     *
 *                                                                            *
 ******************************************************************************/
void	cep_event_context_resolve_name_macros(zbx_cep_event_context_t *ctx, char **str)
{
	cep_event_context_resolve_macros(ctx, ZBX_TOKEN_SEARCH_EXPRESSION_MACRO | ZBX_TOKEN_SEARCH_REFERENCES,
			zbx_macro_event_name_resolv, str);
}

/******************************************************************************
 *                                                                            *
 * Purpose: resolve macros in a event tag                                     *
 *                                                                            *
 * Parameters: ctx - [IN/OUT] event context                                   *
 *             str - [IN/OUT] string to resolve macros in                     *
 *                                                                            *
 ******************************************************************************/
void	cep_event_context_resolve_tag_macros(zbx_cep_event_context_t *ctx, char **str)
{
	cep_event_context_resolve_macros(ctx, 0, zbx_macro_trigger_tag_resolv, str);
}

/******************************************************************************
 *                                                                            *
 * Purpose: initialize cep event context with existing event handle           *
 *                                                                            *
 * Parameters: ctx    - [OUT] event context to initialize                     *
 *             hevent - [IN] event handle                                     *
 *             pos    - [IN] event position                                   *
 *             dbpool - [IN] database connection pool                         *
 *                                                                            *
 ******************************************************************************/
void	cep_event_context_init_with_handle(zbx_cep_event_context_t *ctx, zbx_cep_event_handle_t hevent,
		zbx_cep_event_pos_t pos, zbx_dbconn_pool_t *dbpool)
{
	memset(ctx, 0, sizeof(zbx_cep_event_context_t));
	ctx->hevent = hevent;
	ctx->pos = pos;
	ctx->dbpool = dbpool;
}

/******************************************************************************
 *                                                                            *
 * Purpose: initialize cep event context with existing event object           *
 *                                                                            *
 * Parameters: ctx      - [OUT] event context to initialize                   *
 *             event    - [IN] cep event                                      *
 *             db_event - [IN] database event                                 *
 *             dbpool   - [IN] database connection pool                       *
 *                                                                            *
 ******************************************************************************/
void	cep_event_context_init_with_event(zbx_cep_event_context_t *ctx, zbx_cep_event_t *event,
		zbx_db_event *db_event, zbx_dbconn_pool_t *dbpool)
{
	memset(ctx, 0, sizeof(zbx_cep_event_context_t));
	ctx->event = event;
	ctx->db_event = db_event;
	ctx->pos = CEP_POS_LAST;
	ctx->sync_flags = CEP_SYNC_IGNORE;
	ctx->dbpool = dbpool;
}

