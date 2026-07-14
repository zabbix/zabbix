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
#include "zbxcommon.h"

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
		const zbx_vector_tags_ptr_t *tags, const zbx_vector_db_event_suppress_t *suppress)
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

