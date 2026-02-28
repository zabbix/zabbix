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

#include "cep.h"
#include "zbxcep.h"
#include "zbxcep_client.h"

#include "zbxcommon.h"
#include "zbxalgo.h"
#include "zbxdb.h"
#include "zbx_trigger_constants.h"
#include "zbx_item_constants.h"
#include "zbxdbhigh.h"
#include "zbxlog.h"
#include "zbxnum.h"
#include "zbxtypes_ext.h"

/*
 * Event cache
 *
 * Event data storage:
 * - The cache keeps zbx_cep_event_ptr_t entries in a hash set keyed by eventid.
 * - Each zbx_cep_event_ptr_t is the handle implementation and guards a pointer to
 *   the current refcounted event object (zbx_cep_event_t).
 * - Handles (zbx_cep_event_handle_t == zbx_cep_event_ptr_t *) are refcounted too:
 *   retain/acquire increments the handle refcount; release decrements it.
 *
 * Event data access:
 * - To inspect an event, temporarily obtain a zbx_cep_event_t * from the handle,
 *   read it, then release it immediately (do not retain the event pointer).
 * - Get/release of the event pointer from a handle is protected by a mutex.
 *
 * Event data update:
 * - On event changes, create a new zbx_cep_event_t instance with updated data.
 * - Under the same mutex, replace the handle’s zbx_cep_event_t * with the new one
 *   and release the old zbx_cep_event_t.
 */

ZBX_VECTOR_IMPL(cep_event, zbx_cep_event_t)
ZBX_PTR_VECTOR_IMPL(cep_event_ptr, zbx_cep_event_t *)

/* Ehen CEP is notified about deleted events it might not be able to remove them from cache */
/* if their handles are held by other entities. In this case mark them as deleted in cache  */
/* and pretend those events are not cached when requested.                                  */
typedef enum
{
	CEP_EVENT_STATE_ACTIVE,
	CEP_EVENT_STATE_DELETED
}
zbx_cep_event_state_t;

/* event handle implementation */
struct zbx_cep_event_ptr
{
	zbx_uint64_t		eventid;
	zbx_cep_event_t		*event;
	zbx_atomic_uint32_t	refcount;
	zbx_cep_event_state_t	state;
};

ZBX_VECTOR_IMPL(cep_event_handle, zbx_cep_event_handle_t)

typedef struct
{
	zbx_cep_origin_t	origin;
	zbx_vector_cep_event_t	events;
}
zbx_cep_task_group_t;

/* object that creates events, for example this would be trigger for trigger events */
typedef struct
{
	zbx_cep_origin_t		origin;

	/* open problems created by the object */
	zbx_vector_cep_event_handle_t	events;

	/* number count of events that have been approved/expected for this object */
	/* but have not yet been processed by the cache                            */
	zbx_uint32_t			pending_events_num;
}
zbx_cep_object_t;

struct zbx_cep
{
	zbx_hashset_t			events;

	/* object -> events index */
	zbx_hashset_t			objects;

	zbx_uint64_t			eventid_next;
	zbx_uint64_t			eventid_last;

	zbx_dbconn_pool_t		*dbpool;
};

static void	cep_event_clear(zbx_cep_event_t *event)
{
	if (NULL != event->r_event)
		zbx_cep_event_release(event->r_event);

	for (int i = 0; i < event->tags.values_num; i++)
	{
		zbx_free(event->tags.values[i].tag);
		zbx_free(event->tags.values[i].value);
	}
	zbx_vector_tag_destroy(&event->tags);

	zbx_vector_uint64_destroy(&event->maintenanceids);
}

void	zbx_cep_event_release(zbx_cep_event_t *event)
{
	if (1 != atomic_fetch_sub(&event->refcount, 1))
		return;

	zabbix_log(LOG_LEVEL_ERR, "[WDN] free event %lu", event->eventid);

	cep_event_clear(event);
	zbx_free(event);
}

zbx_cep_event_t	*cep_event_create(zbx_uint64_t eventid, unsigned char source, unsigned char object,
		zbx_uint64_t objectid, int clock, int ns, int value, int serverity,
		const zbx_vector_tags_ptr_t *tags, const zbx_vector_db_event_suppress_t *suppress)
{
	zbx_cep_event_t	*event;

	event = (zbx_cep_event_t *)zbx_malloc(NULL, sizeof(zbx_cep_event_t));
	event->eventid = eventid;
	event->r_event = NULL;
	event->refcount = 0;
	event->origin.source = source;
	event->origin.object = object;
	event->origin.objectid = objectid;
	event->clock = clock;
	event->ns = ns;
	event->value = value;
	event->severity = serverity;
	event->suppress_mtime = 0;

	zbx_vector_tag_create(&event->tags);
	if (NULL != tags)
	{
		zbx_vector_tag_reserve(&event->tags, (size_t)tags->values_num);
		for (int i = 0; i < tags->values_num; i++)
		{
			zbx_tag_t	tag;

			tag.tag = zbx_strdup(NULL, tags->values[i]->tag);
			tag.value = zbx_strdup(NULL, tags->values[i]->value);
			zbx_vector_tag_append(&event->tags, tag);
		}
	}

	zbx_vector_uint64_create(&event->maintenanceids);
	if (NULL != suppress)
	{
		zbx_vector_uint64_reserve(&event->maintenanceids, (size_t)suppress->values_num);
		for (int i = 0; i < suppress->values_num; i++)
			zbx_vector_uint64_append(&event->maintenanceids, suppress->values[i].maintenanceid);
	}

	return event;
}

zbx_cep_event_t	*cep_event_addref(zbx_cep_event_t *event)
{
	atomic_fetch_add(&event->refcount, 1);

	return event;
}

static zbx_hash_t	cep_event_ptr_hash(const void *a)
{
	const zbx_cep_event_ptr_t	*ref = (const zbx_cep_event_ptr_t *)a;

	return ZBX_DEFAULT_UINT64_HASH_FUNC(&ref->eventid);
}

static int	cep_event_ptr_compare(const void *a1, const void *a2)
{
	const zbx_cep_event_ptr_t	*h1 = (const zbx_cep_event_ptr_t *)a1;
	const zbx_cep_event_ptr_t	*h2 = (const zbx_cep_event_ptr_t *)a2;

	ZBX_RETURN_IF_NOT_EQUAL(h1->eventid, h2->eventid);

	return 0;
}

zbx_cep_event_handle_t	zbx_cep_event_handle_addref(zbx_cep_event_handle_t h)
{
	atomic_fetch_add(&h->refcount, 1);

	return h;
}

zbx_uint32_t	cep_event_handle_release(zbx_cep_event_handle_t h)
{
	return atomic_fetch_sub(&h->refcount, 1);
}

zbx_cep_event_t	*cep_event_handle_remove(zbx_cep_t *cep, zbx_cep_event_handle_t h)
{
	zbx_cep_event_t	*event = h->event;

	zbx_hashset_remove_direct(&cep->events, h);

	return event;
}

static zbx_cep_event_handle_t	cep_create_event_handle(zbx_cep_t *cep, zbx_cep_event_t *event)
{
	zbx_cep_event_ptr_t	handle_local = {
			.eventid = event->eventid,
			.event = cep_event_addref(event),
			.state = CEP_EVENT_STATE_ACTIVE,
			.refcount = 1
		};

	return (zbx_cep_event_handle_t)zbx_hashset_insert(&cep->events, &handle_local, sizeof(handle_local));
}

zbx_hash_t	cep_origin_hash(const zbx_cep_origin_t *origin)
{
	zbx_hash_t	hash;
	unsigned char	stream[] = {origin->source, origin->object};

	hash = ZBX_DEFAULT_UINT64_HASH_FUNC(&origin->objectid);
	hash = ZBX_DEFAULT_STRING_HASH_ALGO(stream, sizeof(stream), hash);

	return hash;
}

int	cep_origin_compare(const zbx_cep_origin_t *o1, const zbx_cep_origin_t *o2)
{
	ZBX_RETURN_IF_NOT_EQUAL(o1->source, o2->source);
	ZBX_RETURN_IF_NOT_EQUAL(o1->object, o2->object);
	ZBX_RETURN_IF_NOT_EQUAL(o1->objectid, o2->objectid);

	return 0;
}

static void	cep_object_clear(void *a)
{
	zbx_cep_object_t	*object = (zbx_cep_object_t *)a;

	zbx_vector_cep_event_handle_destroy(&object->events);
}

static zbx_hash_t	cep_object_hash(const void *a)
{
	zbx_cep_object_t	*object = (zbx_cep_object_t *)a;

	return cep_origin_hash(&object->origin);
}

static int	cep_object_compare(const void *a1, const void *a2)
{
	zbx_cep_object_t	*o1 = (zbx_cep_object_t *)a1;
	zbx_cep_object_t	*o2 = (zbx_cep_object_t *)a2;

	return cep_origin_compare(&o1->origin, &o2->origin);
}

zbx_cep_t	*cep_create(void)
{
	zbx_cep_t	*cep;

	cep = (zbx_cep_t *)zbx_malloc(NULL, sizeof(zbx_cep_t));

	zbx_hashset_create_ext(&cep->events, 100, cep_event_ptr_hash, cep_event_ptr_compare,
			NULL, ZBX_DEFAULT_MEM_MALLOC_FUNC, ZBX_DEFAULT_MEM_REALLOC_FUNC,
			ZBX_DEFAULT_MEM_FREE_FUNC);

	zbx_hashset_create_ext(&cep->objects, 100, cep_object_hash, cep_object_compare, cep_object_clear,
			ZBX_DEFAULT_MEM_MALLOC_FUNC, ZBX_DEFAULT_MEM_REALLOC_FUNC, ZBX_DEFAULT_MEM_FREE_FUNC);

	cep->eventid_next = 0;
	cep->eventid_last = 0;

	return cep;
}

void	cep_destroy(zbx_cep_t *cep)
{
	zbx_hashset_destroy(&cep->objects);

	zbx_hashset_iter_t	iter;
	zbx_cep_event_handle_t	h;

	zbx_hashset_iter_reset(&cep->events, &iter);
	while (NULL !=  (h = (zbx_cep_event_handle_t)zbx_hashset_iter_next(&iter)))
	{
		zbx_cep_event_release(h->event);
	}

	zbx_hashset_destroy(&cep->events);

	zbx_free(cep);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get next event ID                                                 *
 *                                                                            *
 * Parameters: cep - [IN/OUT] cache                                           *
 *                                                                            *
 * Return value: next event ID                                                *
 *                                                                            *
 * Comments: Event IDs are allocated in batches to reduce database queries.   *
 *           When the current batch is exhausted, a new batch is reserved     *
 *           from the database and subsequent calls return IDs from it.       *
 *                                                                            *
 ******************************************************************************/
static zbx_uint64_t	cep_eventid_next(zbx_cep_t *cep)
{
#define CEP_EVENTID_BATCH_SIZE	1000

	if (cep->eventid_next == cep->eventid_last)
	{
		zbx_dbconn_t	*db = zbx_dbconn_pool_acquire_connection(cep->dbpool);

		cep->eventid_next = zbx_dbconn_get_maxid_num(db, "events", CEP_EVENTID_BATCH_SIZE);

		zbx_dbconn_pool_release_connection(cep->dbpool, db);
	}

	return cep->eventid_next++;

#undef CEP_EVENTID_BATCH_SIZE
}

/******************************************************************************
 *                                                                            *
 * Purpose: get event handle by event ID                                      *
 *                                                                            *
 * Parameters: cache   - [IN] cache context                                   *
 *             eventid - [IN] event ID                                        *
 *                                                                            *
 * Return value: event handle or NULL if not found                            *
 *                                                                            *
 ******************************************************************************/
static zbx_cep_event_handle_t	cep_get_event(zbx_cep_t *cep, zbx_uint64_t eventid)
{
	return (zbx_cep_event_handle_t)zbx_hashset_search(&cep->events, &eventid);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get object by origin                                              *
 *                                                                            *
 * Parameters: cache  - [IN] cache context                                    *
 *             origin - [IN] object origin                                    *
 *                                                                            *
 * Return value: object or NULL if not found                                  *
 *                                                                            *
 ******************************************************************************/
static zbx_cep_object_t	*cep_get_object(zbx_cep_t *cep, const zbx_cep_origin_t *origin)
{
	return (zbx_cep_object_t *)zbx_hashset_search(&cep->objects, origin);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get object by origin or create it if not found                    *
 *                                                                            *
 * Parameters: cache  - [IN]     cache context                                *
 *             origin - [IN]     object origin                                *
 *                                                                            *
 * Return value: existing or newly created object                             *
 *                                                                            *
 ******************************************************************************/
static zbx_cep_object_t	*cep_get_object_or_create(zbx_cep_t *cep, const zbx_cep_origin_t *origin)
{
	zbx_cep_object_t	obj_local = {.origin = *origin};
	zbx_cep_object_t	*obj;
	int			obj_num = cep->objects.num_data;

	obj = (zbx_cep_object_t *)zbx_hashset_insert(&cep->objects, &obj_local, sizeof(obj_local));

	if (obj_num != cep->objects.num_data)
	{
		zbx_vector_cep_event_handle_create(&obj->events);
		zbx_vector_cep_event_handle_reserve(&obj->events, 1);
	}

	return obj;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get problem value for the given origin                            *
 *                                                                            *
 * Parameters: origin - [IN] object origin                                    *
 *                                                                            *
 * Return value: problem value depending on origin source and object, or      *
 *               FAIL if the origin does not produce problem events           *
 *                                                                            *
 ******************************************************************************/
int	cep_origin_problem(const zbx_cep_origin_t *origin)
{
	if (EVENT_SOURCE_TRIGGERS == origin->source)
	{
		if (EVENT_OBJECT_TRIGGER == origin->object)
			return TRIGGER_VALUE_PROBLEM;

		return FAIL;
	}

	if (EVENT_SOURCE_INTERNAL == origin->source)
	{
		switch (origin->object)
		{
			case EVENT_OBJECT_ITEM:
			case EVENT_OBJECT_LLDRULE:
				return ITEM_STATE_NOTSUPPORTED;
			case EVENT_OBJECT_TRIGGER:
				return TRIGGER_STATE_UNKNOWN;
		}
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: load open problems from database into cache                       *
 *                                                                            *
 * Parameters: cache - [IN/OUT] cache context                                 *
 *             db    - [IN]     database connection                           *
 *                                                                            *
 ******************************************************************************/
static void	cep_load_problems(zbx_cep_t *cep, zbx_dbconn_t *db)
{
	zbx_cep_event_t	*event = NULL;
	zbx_db_result_t	result;
	zbx_db_row_t	row;

	result = zbx_dbconn_select(db, "select p.eventid,p.clock,p.severity,t.tag,t.value,p.ns,p.source,p.object,"
			"p.objectid"
		" from problem p"
		" left join problem_tag t"
			" on p.eventid=t.eventid"
		" where r_eventid is null"
			" and (p.source=%d or p.source=%d)"
		" order by p.eventid",
		EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_INTERNAL);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_uint64_t	eventid;

		ZBX_STR2UINT64(eventid, row[0]);

		if (NULL == event || eventid != event->eventid)
		{
			event = (zbx_cep_event_t *)zbx_malloc(NULL, sizeof(zbx_cep_event_t));
			event->eventid = eventid;
			event->r_event = NULL;
			event->clock = atoi(row[1]);
			event->ns = atoi(row[5]);
			event->severity = atoi(row[2]);
			event->suppress_mtime = 0;
			event->refcount = 0;
			zbx_vector_tag_create(&event->tags);
			zbx_vector_uint64_create(&event->maintenanceids);

			zbx_cep_object_t	*obj;
			zbx_cep_origin_t	origin;
			zbx_cep_event_handle_t	h;

			ZBX_STR2UCHAR(origin.source, row[6]);
			ZBX_STR2UCHAR(origin.object, row[7]);
			ZBX_STR2UINT64(origin.objectid, row[8]);

			event->value = cep_origin_problem(&origin);

			obj = cep_get_object_or_create(cep, &origin);
			h = cep_create_event_handle(cep, event);
			zbx_vector_cep_event_handle_append(&obj->events, zbx_cep_event_handle_addref(h));
		}

		if (FAIL == zbx_db_is_null(row[3]))
		{
			zbx_tag_t	tag;

			tag.tag = zbx_strdup(NULL, row[3]);
			tag.value = zbx_strdup(NULL, row[4]);
			zbx_vector_tag_append(&event->tags, tag);
		}
	}

	zbx_db_free_result(result);
}

/******************************************************************************
 *                                                                            *
 * Purpose: load maintenance data from database into cache                    *
 *                                                                            *
 * Parameters: cache - [IN/OUT] cache context                                 *
 *             db    - [IN]     database connection                           *
 *                                                                            *
 ******************************************************************************/
static void	cep_load_maintenances(zbx_cep_t *cep, zbx_dbconn_t *db)
{
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	zbx_cep_event_handle_t	h = NULL;

	result = zbx_dbconn_select(db, "select eventid,maintenanceid from event_suppress order by eventid");

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_uint64_t		eventid;
		zbx_uint64_t		maintenanceid;

		ZBX_STR2UINT64(eventid, row[0]);
		ZBX_DBROW2UINT64(maintenanceid, row[1]);

		if (NULL == h || h->eventid != eventid)
		{
			if (NULL == (h = cep_get_event(cep, eventid)))
			{
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
			}
		}
		zbx_vector_uint64_append(&h->event->maintenanceids, maintenanceid);
	}

	zbx_db_free_result(result);
}

/******************************************************************************
 *                                                                            *
 * Purpose: initialize cache                                                  *
 *                                                                            *
 * Parameters: cache  - [IN/OUT] cache context                                *
 *             dbpool - [IN]     database connection pool                     *
 *                                                                            *
 ******************************************************************************/
void	cep_init(zbx_cep_t *cep, zbx_dbconn_pool_t *dbpool)
{
	zbx_dbconn_t	*db;

	cep->dbpool = dbpool;

	db = zbx_dbconn_pool_acquire_connection(dbpool);

	cep_load_problems(cep, db);
	cep_load_maintenances(cep, db);

	zbx_dbconn_pool_release_connection(dbpool, db);
}

/******************************************************************************
 *                                                                            *
 * Purpose: add event to cache and link it to its object                      *
 *                                                                            *
 * Parameters: cache - [IN/OUT] cache context                                 *
 *             event - [IN]     event to add                                  *
 *                                                                            *
 * Return value: event handle                                                 *
 *                                                                            *
 ******************************************************************************/
zbx_cep_event_handle_t	cep_add_event(zbx_cep_t *cep, zbx_cep_event_t *event)
{
	zbx_cep_event_handle_t	h;
	zbx_cep_object_t	*obj;

	obj = cep_get_object_or_create(cep, &event->origin);
	h = cep_create_event_handle(cep, event);
	zbx_vector_cep_event_handle_append(&obj->events, h);

	obj->pending_events_num--;

	return h;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check trigger dependencies for event processing                   *
 *                                                                            *
 * Parameters: cache        - [IN] cache context                              *
 *             triggerids   - [IN] trigger IDs processed in current batch or  *
 *                                 NULL                                       *
 *             dep_triggerids - [IN] dependency trigger IDs                   *
 *                                                                            *
 * Return value: CEP_EVENT_ALLOW, CEP_EVENT_DEPENDENCY_DENY or                *
 *               CEP_EVENT_DEPENDENCY_DEFER.                                  *
 *                                                                            *
 * Comments: Fails immediately if any dependency trigger has open problems.   *
 *           Defers if any dependency trigger has pending events or is also   *
 *           scheduled in the current batch.                                  *
 *                                                                            *
 ******************************************************************************/
static zbx_cep_result_t	cep_check_trigger_dependency(zbx_cep_t *cep, const zbx_hashset_t *triggerids,
		const zbx_vector_uint64_t *dep_triggerids)
{
	zbx_cep_result_t	ret = CEP_EVENT_ALLOW;
	zbx_cep_origin_t	origin = {.source = EVENT_SOURCE_TRIGGERS, .object = EVENT_OBJECT_TRIGGER};

	for (int i = 0; i < dep_triggerids->values_num; i++)
	{
		zbx_cep_object_t	*obj;

		origin.objectid = dep_triggerids->values[i];

		if (NULL == (obj = cep_get_object(cep, &origin)))
			continue;

		/* trigger has open problems - immediate dependency check fail*/
		if (0 != obj->events.values_num)
			return CEP_EVENT_DEPENDENCY_DENY;

		/* trigger has pending events - dependency state unknown*/
		if (0 != obj->pending_events_num)
			ret = CEP_EVENT_DEPENDENCY_DEFER;

		/* trigger depends on another trigger being processed in this batch */
		if (NULL != triggerids &&  NULL != zbx_hashset_search(triggerids, &origin.objectid))
			ret = CEP_EVENT_DEPENDENCY_DEFER;
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: assess trigger events against cache state                         *
 *                                                                            *
 * Parameters: cache   - [IN/OUT] cache context                               *
 *             queries - [IN]     trigger assessment queries                  *
 *             results - [OUT]    assessment results                          *
 *                                                                            *
 * Comments: Uses cache state and trigger dependencies to decide whether      *
 *           to allow, deny or defer event generation and updates per-object  *
 *           pending event counters accordingly.                              *
 *                                                                            *
 ******************************************************************************/
void	cep_assess_trigger_events(zbx_cep_t *cep, const zbx_vector_cep_assessment_query_t *queries,
		unsigned char *results)
{
	int			dropped_num = 0;
	zbx_hashset_t		triggerids;
	zbx_cep_origin_t	origin = {.source = EVENT_SOURCE_TRIGGERS, .object = EVENT_OBJECT_TRIGGER};

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() queries:%d", __func__, queries->values_num);

	zbx_hashset_create(&triggerids, (size_t)queries->values_num, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	for (int i = 0; i < queries->values_num; i++)
		zbx_hashset_insert(&triggerids, &queries->values[i].triggerid, sizeof(queries->values[i].triggerid));

	for (int i = 0; i < queries->values_num; i++)
	{
		zbx_cep_assessment_query_t	*query = &queries->values[i];
		zbx_cep_object_t	*obj;
		int			result = CEP_EVENT_ALLOW;

		origin.objectid = query->triggerid;

		if (0 != (query->flags & CEP_QUERY_FLAG_DEPS) &&
				CEP_EVENT_ALLOW != (result = cep_check_trigger_dependency(cep, &triggerids,
						&query->dep_triggerids)))
		{
			if (CEP_EVENT_DEPENDENCY_DENY == result)
			{
				/* trigger depdency check fail, drop any event */
				results[i] = CEP_EVENT_DEPENDENCY_DENY;
				dropped_num++;

				continue;
			}

			/* dependency check unknown - request event to be sent and register pending event */
			obj = cep_get_object_or_create(cep, &origin);
		}
		else if (TRIGGER_VALUE_OK == (query->flags & CEP_QUERY_MASK_VALUE))
		{
			if (NULL == (obj = cep_get_object(cep, &origin)))
			{
				/* no open problems or pending events - drop OK event*/
				results[i] = CEP_EVENT_DENY;
				dropped_num++;

				continue;
			}

			/* if there are open problems or pending events - */
			/* OK event might close something, request it     */
			if (0 != obj->pending_events_num)
				results[i] = CEP_EVENT_DEFER;
		}
		else
		{
			/* either there is existing trigger with problem and event might get dropped */
			/* or event will be created so trigger must be added                         */
			obj = cep_get_object_or_create(cep, &origin);

			if (0 == obj->pending_events_num)
			{
				if (0 == (query->flags & CEP_QUERY_FLAG_MULTI) && 0 != obj->events.values_num)
				{
					/* open problem without pending events for single event */
					/* generation - drop problem event                      */
					results[i] = CEP_EVENT_DENY;
					dropped_num++;

					continue;
				}
			}
			else
				result = CEP_EVENT_DEFER;
		}

		results[i] = result;
		obj->pending_events_num++;
	}

	zbx_hashset_destroy(&triggerids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() dropped:%d", __func__, dropped_num);
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if event has a tag with matching name and value             *
 *                                                                            *
 * Parameters: event - [IN] event to check                                    *
 *             tag   - [IN] tag name to match                                 *
 *             tags  - [IN] list of tags to match against                     *
 *                                                                            *
 * Return value: SUCCEED if a tag with the given name and value is found,     *
 *               otherwise FAIL                                               *
 *                                                                            *
 ******************************************************************************/
static int	cep_event_match_tag(const zbx_cep_event_t *event, const char *tag, const zbx_vector_tags_ptr_t *tags)
{
	for (int i = 0; i < event->tags.values_num; i++)
	{
		if (0 != strcmp(event->tags.values[i].tag, tag))
			continue;

		for (int j = 0; j < tags->values_num; j++)
		{
			if (0 != strcmp(tags->values[j]->tag, tag))
				continue;

			if (0 == strcmp(event->tags.values[i].value, tags->values[j]->value))
				return SUCCEED;
		}
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: open problem event for trigger                                    *
 *                                                                            *
 * Parameters: cache          - [IN/OUT] cache context                        *
 *             triggerid      - [IN]     trigger ID                           *
 *             trigger_type   - [IN]     trigger type                         *
 *             dep_triggerids - [IN]     dependency trigger IDs               *
 *             obj_value      - [OUT]    resulting trigger value              *
 *                                                                            *
 * Return value: new event ID or 0 if event must not be created based on      *
 *               actual cache state                                           *
 *                                                                            *
 ******************************************************************************/
zbx_uint64_t	cep_open_trigger_event(zbx_cep_t *cep, zbx_uint64_t triggerid, unsigned char trigger_type,
		const zbx_vector_uint64_t *dep_triggerids, int *obj_value)
{
	zbx_cep_origin_t	origin = {
					.source = EVENT_SOURCE_TRIGGERS,
					.object = EVENT_OBJECT_TRIGGER,
					.objectid = triggerid
					};
	zbx_cep_object_t	*obj;

	if (NULL == (obj = cep_get_object(cep, &origin)))
	{
		THIS_SHOULD_NEVER_HAPPEN_MSG("detected incoming trigger event without creation check");
		return 0;
	}

	if (CEP_EVENT_DEPENDENCY_DENY == cep_check_trigger_dependency(cep, NULL, dep_triggerids))
		return 0;

	if (0 != obj->events.values_num)
	{
		if (TRIGGER_TYPE_MULTIPLE_TRUE != trigger_type)
			return 0;
	}
	else
		*obj_value = TRIGGER_VALUE_PROBLEM;

	return cep_eventid_next(cep);
}

/******************************************************************************
 *                                                                            *
 * Purpose: ensure writable event instance for handle                         *
 *                                                                            *
 * Parameters: h - [IN/OUT] event handle                                      *
 *                                                                            *
 * Return value: event associated with the handle                             *
 *                                                                            *
 * Comments: If the current event is shared (refcount > 1), creates a full    *
 *           clone, releases the old event and rebinds the handle to the new  *
 *           instance.                                                        *
 *                                                                            *
 ******************************************************************************/
static zbx_cep_event_t	*cep_event_handle_replace_event(zbx_cep_event_handle_t h)
{
	zbx_cep_event_t	*event, *e = h->event;

	if (1 == atomic_load(&h->event->refcount))
		return h->event;

	event = (zbx_cep_event_t *)zbx_malloc(NULL, sizeof(zbx_cep_event_t));
	*event = *e;
	event->refcount = 0;
	event->suppress_mtime = 0;

	zbx_vector_tag_create(&event->tags);
	zbx_vector_tag_reserve(&event->tags, (size_t)e->tags.values_num);
	for (int i = 0; i < e->tags.values_num; i++)
	{
		zbx_tag_t	tag_local;

		tag_local.tag = zbx_strdup(NULL, e->tags.values[i].tag);
		tag_local.value = zbx_strdup(NULL, e->tags.values[i].value);
		zbx_vector_tag_append(&event->tags, tag_local);
	}

	zbx_vector_uint64_create(&event->maintenanceids);
	zbx_vector_uint64_append_array(&event->maintenanceids, e->maintenanceids.values, e->maintenanceids.values_num);

	zbx_cep_event_release(e);
	h->event = cep_event_addref(event);

	return event;
}

/******************************************************************************
 *                                                                            *
 * Purpose: resolve trigger events with result event                          *
 *                                                                            *
 * Parameters: cache   - [IN]     cache (to ensure it's locked)               *
 *             r_event - [IN]     result event                                *
 *             events  - [IN/OUT] trigger events to resolve                   *
 *                                                                            *
 * Comments: For each trigger event handle, updates event value to OK and     *
 *           replaces the event using copy-on-write if needed.                *
 *                                                                            *
 ******************************************************************************/
void	cep_resolve_trigger_events(zbx_cep_t *cep, zbx_cep_event_t *r_event, zbx_vector_cep_event_handle_t *events)
{
	/* require cep parameter to force caller to lock cep cache */
	ZBX_UNUSED(cep);

	for (int i = 0; i < events->values_num; i++)
	{
		zbx_cep_event_handle_t	h = events->values[i];
		zbx_cep_event_t		*event;

		event = cep_event_handle_replace_event(h);
		event->r_event = cep_event_addref(r_event);
		event->value = TRIGGER_VALUE_OK;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: close matching trigger events                                     *
 *                                                                            *
 * Parameters: cache            - [IN/OUT] cache context                      *
 *             triggerid        - [IN]     trigger ID                         *
 *             dep_triggerids   - [IN]     dependency trigger IDs             *
 *             correlation_mode - [IN]     correlation mode                   *
 *             correlation_tag  - [IN]     correlation tag name               *
 *             tags             - [IN]     correlation tags                   *
 *             events           - [OUT]    matching events to close           *
 *                                                                            *
 * Return value: new event ID or 0 if no events can be closed                 *
 *                                                                            *
 ******************************************************************************/
zbx_uint64_t	cep_close_trigger_events(zbx_cep_t *cep, zbx_uint64_t triggerid,
		const zbx_vector_uint64_t *dep_triggerids, unsigned char correlation_mode, const char *correlation_tag,
		const zbx_vector_tags_ptr_t *tags, zbx_vector_cep_event_handle_t *events)
{
	zbx_cep_origin_t	origin = {
					.source = EVENT_SOURCE_TRIGGERS,
					.object = EVENT_OBJECT_TRIGGER,
					.objectid = triggerid
					};
	zbx_cep_object_t	*obj;

	if (NULL == (obj = cep_get_object(cep, &origin)))
	{
		THIS_SHOULD_NEVER_HAPPEN_MSG("detected incoming trigger event without creation check");
		return 0;
	}

	obj->pending_events_num--;

	if (CEP_EVENT_DEPENDENCY_DENY == cep_check_trigger_dependency(cep, NULL, dep_triggerids))
		return 0;

	for (int i = obj->events.values_num - 1; i >= 0 && 0 != obj->events.values_num; i--)
	{
		zbx_cep_event_t	*event = obj->events.values[i]->event;

		if (ZBX_TRIGGER_CORRELATION_TAG == correlation_mode &&
				SUCCEED != cep_event_match_tag(event, correlation_tag, tags))
		{
			continue;
		}

		zbx_vector_cep_event_handle_append(events, obj->events.values[i]);
		zbx_vector_cep_event_handle_remove_noorder(&obj->events, i);
	}

	/* no events to recover */
	if (0 == events->values_num)
		return 0;

	if (0 == obj->events.values_num && 0 == obj->pending_events_num)
		zbx_hashset_remove_direct(&cep->objects, obj);

	return cep_eventid_next(cep);
}

/******************************************************************************
 *                                                                            *
 * Purpose: close specified trigger event                                     *
 *                                                                            *
 * Parameters: cache     - [IN/OUT] cache context                             *
 *             triggerid - [IN]  trigger ID                                   *
 *             eventid   - [IN]  event ID                                     *
 *             handles   - [OUT] handle of event to close                     *
 *                                                                            *
 * Return value: new event ID or 0 if the specified event cannot be closed    *
 *                                                                            *
 ******************************************************************************/
zbx_uint64_t	cep_close_trigger_event_by_eventid(zbx_cep_t *cep, zbx_uint64_t triggerid, zbx_uint64_t eventid,
		zbx_vector_cep_event_handle_t *handles)
{
	zbx_cep_object_t	*obj;
	zbx_cep_origin_t        origin = {
			.source = EVENT_SOURCE_TRIGGERS,
			.object = EVENT_OBJECT_TRIGGER,
			.objectid = triggerid
	};


	if (NULL == (obj = cep_get_object(cep, &origin)))
	{
		THIS_SHOULD_NEVER_HAPPEN_MSG("detected incoming trigger event without creation check");
		return 0;
	}

	obj->pending_events_num--;

	for (int i = 0; i < obj->events.values_num; i++)
	{
		if (obj->events.values[i]->event->eventid == eventid)
		{
			zbx_vector_cep_event_handle_append(handles, obj->events.values[i]);
			zbx_vector_cep_event_handle_remove_noorder(&obj->events, i);
			break;
		}
	}

	/* no events to recover */
	if (0 == handles->values_num)
		return 0;

	if (0 == obj->events.values_num && 0 == obj->pending_events_num)
		zbx_hashset_remove_direct(&cep->objects, obj);

	return cep_eventid_next(cep);
}

/******************************************************************************
 *                                                                            *
 * Purpose: open internal event                                               *
 *                                                                            *
 * Parameters: cache   - [IN/OUT] cache context                               *
 *             object  - [IN]     object type                                 *
 *             objectid- [IN]     object ID                                   *
 *                                                                            *
 * Return value: new event ID or 0 if event must not be created               *
 *                                                                            *
 ******************************************************************************/
zbx_uint64_t	cep_open_internal_event(zbx_cep_t *cep, unsigned char object, zbx_uint64_t objectid)
{
	zbx_cep_origin_t	origin = {
					.source = EVENT_SOURCE_INTERNAL,
					.object = object,
					.objectid = objectid
					};
	zbx_cep_object_t	*obj;

	obj = cep_get_object_or_create(cep, &origin);

	if (0 != obj->events.values_num)
		return 0;

	return cep_eventid_next(cep);
}

/******************************************************************************
 *                                                                            *
 * Purpose: close internal event                                              *
 *                                                                            *
 * Parameters: cache    - [IN/OUT] cache context                              *
 *             object   - [IN]     object type                                *
 *             objectid - [IN]     object ID                                  *
 *             eventids - [OUT]    closed event ID                            *
 *                                                                            *
 * Return value: new event ID or 0 if no recovery event is created            *
 *                                                                            *
 ******************************************************************************/
zbx_uint64_t	cep_close_internal_event(zbx_cep_t *cep, unsigned char object, zbx_uint64_t objectid,
		zbx_vector_uint64_t *eventids)
{
	zbx_cep_origin_t	origin = {
					.source = EVENT_SOURCE_INTERNAL,
					.object = object,
					.objectid = objectid
					};
	zbx_cep_object_t	*obj;

	if (NULL == (obj = cep_get_object(cep, &origin)))
		return 0;

	obj->pending_events_num--;

	/* internal event objects in cache as exactly one event */
	zbx_cep_event_handle_t	h = obj->events.values[0];

	zbx_vector_cep_event_handle_clear(&obj->events);
	zbx_hashset_remove_direct(&cep->objects, obj);

	zbx_uint64_t	eventid = cep_eventid_next(cep);

	zbx_vector_uint64_reserve(eventids, 1);
	zbx_vector_uint64_append(eventids, h->event->eventid);

	/* update event if something holds reference to it, otherwise just remove from cache */
	if (1 != cep_event_handle_release(h))
	{
		zbx_cep_event_t		*event;

		event = cep_event_handle_replace_event(h);

		switch (object)
		{
			case EVENT_OBJECT_ITEM:
			case EVENT_OBJECT_LLDRULE:
				event->value = ITEM_STATE_NORMAL;
				break;
			case EVENT_OBJECT_TRIGGER:
				event->value = TRIGGER_STATE_NORMAL;
				break;
		}

		cep_event_handle_release(h);
	}
	else
	{
		zbx_cep_event_release(h->event);
		zbx_hashset_remove_direct(&cep->events, h);
	}

	return eventid;
}

/******************************************************************************
 *                                                                            *
 * Purpose: add maintenance IDs to event and update suppression time          *
 *                                                                            *
 * Parameters: h             - [IN/OUT] event handle                          *
 *             maintenanceids- [IN/OUT] maintenance IDs to add                *
 *                                                                            *
 ******************************************************************************/
static void	cep_event_add_maintenaces(zbx_cep_event_handle_t h, zbx_vector_uint64_t *maintenanceids)
{
	for (int i = 0; i < h->event->maintenanceids.values_num; i++)
	{
		int	index;

		if (FAIL != (index = zbx_vector_uint64_search(maintenanceids, h->event->maintenanceids.values[i],
			ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
		{
			zbx_vector_uint64_remove_noorder(maintenanceids, index);
		}
	}

	if (0 == maintenanceids->values_num)
		return;

	zbx_cep_event_t	*event = cep_event_handle_replace_event(h);

	if (0 == event->maintenanceids.values_num)
		event->suppress_mtime = time(NULL);

	zbx_vector_uint64_append_array(&event->maintenanceids, maintenanceids->values, maintenanceids->values_num);
}

/******************************************************************************
 *                                                                            *
 * Purpose: remove maintenance IDs from event and update suppression time     *
 *                                                                            *
 * Parameters: h             - [IN/OUT] event handle                          *
 *             maintenanceids- [IN]     maintenance IDs to remove             *
 *                                                                            *
 ******************************************************************************/
static void	cep_event_remove_maintenaces(zbx_cep_event_handle_t h, zbx_vector_uint64_t *maintenanceids)
{
	zbx_vector_uint64_t	ids;

	zbx_vector_uint64_create(&ids);
	zbx_vector_uint64_append_array(&ids, h->event->maintenanceids.values,
			h->event->maintenanceids.values_num);

	for (int i = 0; i < ids.values_num;)
	{
		if (FAIL != zbx_vector_uint64_search(maintenanceids, ids.values[i], ZBX_DEFAULT_UINT64_COMPARE_FUNC))
			zbx_vector_uint64_remove_noorder(&ids, i);
		else
			i++;
	}

	if (ids.values_num != h->event->maintenanceids.values_num)
	{
		zbx_cep_event_t	*event = cep_event_handle_replace_event(h);

		if (0 != ids.values_num)
		{
			zbx_vector_uint64_clear(&event->maintenanceids);
			zbx_vector_uint64_append_array(&event->maintenanceids, ids.values, ids.values_num);
		}
		else
		{
			zbx_vector_uint64_destroy(&event->maintenanceids);
			zbx_vector_uint64_create(&event->maintenanceids);
		}

		if (0 == event->maintenanceids.values_num)
			event->suppress_mtime = time(NULL);
	}

	zbx_vector_uint64_destroy(&ids);
}


/******************************************************************************
 *                                                                            *
 * Purpose: update event maintenances                                         *
 *                                                                            *
 * Parameters: h             - [IN/OUT] event handle                          *
 *             maintenanceids- [IN/OUT] maintenance IDs                       *
 *             action        - [IN]     maintenance operation                 *
 *                                                                            *
 * Comments: Suppresses or unsuppresses event by adding or removing           *
 *           maintenances depending on action.                                *
 *                                                                            *
 ******************************************************************************/
static  void	cep_event_update_maintenances(zbx_cep_event_handle_t h, zbx_vector_uint64_t *maintenanceids,
	zbx_cep_event_op_t action)
{
	if (CEP_EVENT_SUPPRESS == action)
		cep_event_add_maintenaces(h, maintenanceids);
	else
		cep_event_remove_maintenaces(h, maintenanceids);
}

/******************************************************************************
 *                                                                            *
 * Purpose: update maintenances for events                                    *
 *                                                                            *
 * Parameters: cache   - [IN]     cache                                       *
 *             events  - [IN]     event-maintenance pairs                     *
 *             action  - [IN]     maintenance operation                       *
 *             handles - [OUT]    updated event handles                       *
 *                                                                            *
 ******************************************************************************/
void	cep_update_event_maintenances(zbx_cep_t *cep, const zbx_vector_event_maintenance_t *events,
		zbx_cep_event_op_t action, zbx_vector_cep_event_handle_t *handles)
{
	zbx_cep_event_handle_t	h = NULL;
	zbx_vector_uint64_t	maintenanceids;

	zbx_vector_uint64_create(&maintenanceids);

	for (int i = 0; i < events->values_num; i++)
	{
		if (NULL == h || h->eventid != events->values[i].eventid)
		{
			if (NULL != h)
				cep_event_update_maintenances(h, &maintenanceids, action);

			if (NULL == (h = cep_get_event(cep, events->values[i].eventid)))
				continue;

			zbx_vector_cep_event_handle_append(handles, zbx_cep_event_handle_addref(h));
		}

		zbx_vector_uint64_append(&maintenanceids, events->values[i].maintenanceid);
	}

	cep_event_update_maintenances(h, &maintenanceids, action);

	zbx_vector_uint64_destroy(&maintenanceids);
}

/******************************************************************************
 *                                                                            *
 * Purpose: update severities for events                                      *
 *                                                                            *
 * Parameters: cache   - [IN]  cache                                          *
 *             events  - [IN]  event-severity pairs                           *
 *             handles - [OUT] updated event handles                          *
 *                                                                            *
 ******************************************************************************/
void	cep_update_event_severities(zbx_cep_t *cep, const zbx_vector_event_severity_t *events,
		zbx_vector_cep_event_handle_t *handles)
{
	for (int i = 0; i < events->values_num; i++)
	{
		zbx_cep_event_handle_t	h;

		if (NULL == (h = cep_get_event(cep, events->values[i].eventid)))
			continue;

		zbx_cep_event_t	*event = cep_event_handle_replace_event(h);

		event->severity = events->values[i].severity;
		zbx_vector_cep_event_handle_append(handles, zbx_cep_event_handle_addref(h));
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: compare tags by name and value                                    *
 *                                                                            *
 ******************************************************************************/
static int	tag_compare(const void *a1, const void *a2)
{
	const zbx_tag_t	*tag1 = (const zbx_tag_t *)a1;
	const zbx_tag_t	*tag2 = (const zbx_tag_t *)a2;
	int		ret;

	if (0 != (ret = strcmp(tag1->tag, tag2->tag)))
		return ret;

	return strcmp(tag1->value, tag2->value);
}

/******************************************************************************
 *                                                                            *
 * Purpose: filter out tags already present on event                          *
 *                                                                            *
 * Parameters: h    - [IN]  event handle                                      *
 *             tags - [IN]  candidate tags                                    *
 *                                                                            *
 * Return value: number of remaining new tags                                 *
 *                                                                            *
 ******************************************************************************/
static int	cep_event_validate_new_tags(zbx_cep_event_handle_t h, zbx_vector_tag_t *tags)
{
	for (int i = 0; i < tags->values_num;)
	{
		if (FAIL != zbx_vector_tag_search(&h->event->tags, tags->values[i], tag_compare))
		{
			zbx_free(tags->values[i].tag);
			zbx_free(tags->values[i].value);
			zbx_vector_tag_remove_noorder(tags, i);
		}
		else
			i++;
	}

	return tags->values_num;
}

/******************************************************************************
 *                                                                            *
 * Purpose: add tags to event                                                 *
 *                                                                            *
 * Parameters: h    - [IN/OUT] event handle                                   *
 *             tags - [IN]     tags to add                                    *
 *                                                                            *
 ******************************************************************************/
static void	cep_event_add_tags(zbx_cep_event_handle_t h, const zbx_vector_tag_t *tags)
{
	zbx_cep_event_t	*event = cep_event_handle_replace_event(h);

	for (int i = 0; i < tags->values_num; i++)
	{
		zbx_tag_t	tag_local = {
			.tag = zbx_strdup(NULL, tags->values[i].tag),
			.value = zbx_strdup(NULL, tags->values[i].value),
		};

		zbx_vector_tag_append(&event->tags, tag_local);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: add new tags to events                                            *
 *                                                                            *
 * Parameters: cache   - [IN]  cache                                          *
 *             events  - [IN]  event-tags pairs                               *
 *             handles - [OUT] updated event handles                          *
 *                                                                            *
 ******************************************************************************/
void	cep_add_event_tags(zbx_cep_t *cep, zbx_vector_event_tags_t *events, zbx_vector_cep_event_handle_t *handles)
{
	for (int i = 0; i < events->values_num;)
	{
		zbx_cep_event_handle_t	h;

		if (NULL == (h = cep_get_event(cep, events->values[i].eventid)))
		{
			i++;
			continue;
		}

		if (0 != cep_event_validate_new_tags(h, &events->values[i].tags))
		{
			cep_event_add_tags(h, &events->values[i].tags);
			zbx_vector_cep_event_handle_append(handles, zbx_cep_event_handle_addref(h));
			i++;
		}
		else
		{
			zbx_vector_tag_destroy(&events->values[i].tags);
			zbx_vector_event_tags_remove_noorder(events, i);
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: get events by handles                                             *
 *                                                                            *
 * Parameters: cache       - [IN]  cache (to ensure it's locked)              *
 *             handles     - [IN]  event handles                              *
 *             handles_num - [IN] number of event handles                     *
 *             events      - [OUT] events referenced by handles               *
 *                                                                            *
 * Comments: Returns NULL events for handles referencing deleted events.      *
 *                                                                            *
 ******************************************************************************/
void	cep_get_events_by_handles(zbx_cep_t *cep, zbx_cep_event_handle_t *handles, int handles_num,
		zbx_cep_event_t **events)
{
	ZBX_UNUSED(cep);

	for (int i = 0; i < handles_num; i++)
	{
		if (CEP_EVENT_STATE_ACTIVE == handles[i]->state)
			events[i] = cep_event_addref(handles[i]->event);
		else
			events[i] = NULL;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: get events by updates                                             *
 *                                                                            *
 * Parameters: cache       - [IN]  cache                                      *
 *             updates     - [IN]  event updates                              *
 *             updates_num - [IN]  number of event updates                    *
 *             events      - [OUT] events referenced by updates               *
 *                                                                            *
 * Comments: Returns NULL for updates referencing deleted events.             *
 *                                                                            *
 ******************************************************************************/
void	cep_get_events_by_updates(zbx_cep_t *cep, zbx_cep_event_update_t *updates, int updates_num,
		zbx_cep_event_t **events)
{
	ZBX_UNUSED(cep);

	for (int i = 0; i < updates_num; i++)
	{
		if (CEP_EVENT_STATE_ACTIVE == updates[i].handle->state)
			events[i] = cep_event_addref(updates[i].handle->event);
		else
			events[i] = NULL;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: get event IDs from handles                                        *
 *                                                                            *
 * Parameters: handles     - [IN]  event handles                              *
 *             handles_num - [IN]  number of event handles                    *
 *             eventids    - [OUT] event IDs                                  *
 *                                                                            *
 * Comments: NULL event is returned when handle refers to deleted event      *
 *                                                                            *
 ******************************************************************************/
void	zbx_cep_get_eventids_from_handles(const zbx_cep_event_handle_t *handles, int handles_num,
		zbx_vector_uint64_t *eventids)
{
	if (0 == handles_num)
		return;

	zbx_vector_uint64_reserve(eventids, (size_t)handles_num);
	for (int i = 0; i < handles_num; i++)
		zbx_vector_uint64_append(eventids, handles[i]->eventid);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get all active event handles                                      *
 *                                                                            *
 * Parameters: cep     - [IN]  cache context                                  *
 *             handles - [OUT] active event handles                           *
 *                                                                            *
 ******************************************************************************/
void	cep_get_events(zbx_cep_t *cep, zbx_vector_cep_event_handle_t *handles)
{
	zbx_hashset_iter_t	iter;
	zbx_cep_event_handle_t	h;

	zbx_vector_cep_event_handle_reserve(handles, (size_t)cep->events.num_data);
	zbx_hashset_iter_reset(&cep->events, &iter);
	while (NULL != (h = (zbx_cep_event_handle_t)zbx_hashset_iter_next(&iter)))
	{
		if (CEP_EVENT_STATE_DELETED == h->state)
			continue;

		zbx_vector_cep_event_handle_append(handles, zbx_cep_event_handle_addref(h));
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: delete events by event IDs                                        *
 *                                                                            *
 * Parameters: cep      - [IN]  CEP context                                   *
 *             eventids - [IN]  IDs of events to delete                       *
 *             handles  - [OUT] handles of deleted events                     *
 *                                                                            *
 * Comments: Marks events as deleted and returns their handles.               *
 *                                                                            *
 ******************************************************************************/
void	cep_delete_events(zbx_cep_t *cep, const zbx_vector_uint64_t *eventids, zbx_vector_cep_event_handle_t *handles)
{
	for (int i = 0; i < eventids->values_num; i++)
	{
		zbx_cep_event_handle_t	h;
		zbx_cep_event_ptr_t	ptr_local;
		zbx_cep_object_t	*obj;

		ptr_local.eventid = eventids->values[i];

		if (NULL == (h = (zbx_cep_event_handle_t)zbx_hashset_search(&cep->events, &ptr_local)))
			continue;

		if (NULL != (obj = cep_get_object(cep, &h->event->origin)))
		{
			for (int j = 0; j < obj->events.values_num; j++)
			{
				if (obj->events.values[j]->event->eventid == h->event->eventid)
				{
					zbx_vector_cep_event_handle_remove_noorder(&obj->events, j);
					break;
				}
			}
		}

		h->state = CEP_EVENT_STATE_DELETED;
		zbx_vector_cep_event_handle_append(handles, h);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: dump event data for debugging                                     *
 *                                                                            *
 * Parameters: indent - [IN]  log message indentation prefix                  *
 *             event  - [IN]  event to dump                                   *
 *                                                                            *
 ******************************************************************************/
static void	cep_dump_event(const char *indent, zbx_cep_event_t *event)
{
	/* WDN remove */
	zabbix_increase_log_level();

	zabbix_log(LOG_LEVEL_DEBUG, "%seventid:" ZBX_FS_UI64, indent, event->eventid);
	zabbix_log(LOG_LEVEL_DEBUG, "%s  clock:%d ns:%d severity:%d refs:%u tags:",
			indent, event->clock, event->ns, event->severity, event->refcount);

	for (int i = 0; i < event->tags.values_num; i++)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s    %s:%s", indent, event->tags.values[i].tag,
				event->tags.values[i].value);
	}

	if (0 != event->maintenanceids.values_num)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s  maintenances:", indent);
		for (int i = 0; i < event->maintenanceids.values_num; i++)
			zabbix_log(LOG_LEVEL_DEBUG, "%s    " ZBX_FS_UI64, indent, event->maintenanceids.values[i]);
	}

	/* WDN remove */
	zabbix_decrease_log_level();
}

/******************************************************************************
 *                                                                            *
 * Purpose: dump event cache contents for debugging                           *
 *                                                                            *
 * Parameters: cep - [IN]  cache                                              *
 *             msg - [IN]  description of the cache change                    *
 *                                                                            *
 ******************************************************************************/
void	cep_dump(zbx_cep_t *cep, const char *msg)
{
	zbx_hashset_iter_t	iter;
	zbx_cep_object_t	*obj;

	/* WDN remove */
	zabbix_increase_log_level();

	if (SUCCEED != ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_DEBUG))
		return;

	zabbix_log(LOG_LEVEL_DEBUG, "CEP cache, changed by %s", msg);

	zbx_hashset_iter_reset(&cep->objects, &iter);
	while (NULL != (obj = (zbx_cep_object_t *)zbx_hashset_iter_next(&iter)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "object %d:%d -> " ZBX_FS_UI64, obj->origin.source, obj->origin.object,
				obj->origin.objectid);
		zabbix_log(LOG_LEVEL_DEBUG, "  pending events:%d events:", obj->pending_events_num);

		for (int i = 0; i < obj->events.values_num; i++)
		{
			cep_dump_event("    ", obj->events.values[i]->event);
		}
	}

	/* WDN remove */
	zabbix_decrease_log_level();
}

