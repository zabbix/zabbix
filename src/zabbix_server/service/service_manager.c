/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "common.h"
#include "log.h"
#include "zbxself.h"
#include "zbxservice.h"
#include "zbxipcservice.h"
#include "service_manager.h"
#include "daemon.h"
#include "sighandler.h"
#include "dbcache.h"
#include "zbxalgo.h"
#include "zbxalgo.h"
#include "service_protocol.h"

extern unsigned char	process_type, program_type;
extern int		server_num, process_num;
extern int		CONFIG_SERVICEMAN_SYNC_FREQUENCY;

/* keep deleted problem eventids up to 2 hours in case problem deletion arrived before problem or before recovery */
#define ZBX_PROBLEM_CLEANUP_AGE		(SEC_PER_HOUR * 2)
#define ZBX_PROBLEM_CLEANUP_FREQUENCY	SEC_PER_HOUR

static volatile sig_atomic_t	service_cache_reload_requested;

typedef struct
{
	zbx_uint64_t		serviceid;
	zbx_uint64_t		current_eventid;
	zbx_vector_ptr_t	service_problems;
	zbx_vector_ptr_t	service_problem_tags;
	zbx_vector_ptr_t	children;
	zbx_vector_ptr_t	parents;
	int			status;
	int			algorithm;
	int			revision;
}
zbx_service_t;

typedef struct
{
	zbx_uint64_t	eventid;
	zbx_uint64_t	service_problemid;
	zbx_uint64_t	serviceid;
	int		severity;
	int		clock;
}
zbx_service_problem_t;

typedef struct
{
	zbx_uint64_t		eventid;
	zbx_vector_ptr_t	services;
}
zbx_service_problem_index_t;

typedef struct
{
	zbx_uint64_t	service_problem_tagid;
	zbx_uint64_t	current_eventid;
	zbx_service_t	*service;
	char		*tag;
	char		*value;
	int		operator;
	int		revision;
}
zbx_service_problem_tag_t;

typedef struct
{
	char			*tag;
	zbx_hashset_t		values;
	zbx_vector_ptr_t	service_problem_tags_like;
}
zbx_tag_services_t;

typedef struct
{
	char			*value;
	zbx_vector_ptr_t	service_problem_tags;
}
zbx_values_eq_t;

typedef struct
{
	zbx_uint64_t	linkid;
	int		revision;
}
zbx_services_link_t;

typedef struct
{
	zbx_uint64_t		serviceid;
	zbx_vector_ptr_t	service_problems;
	zbx_vector_ptr_t	service_problems_recovered;
#define ZBX_FLAG_SERVICE_UPDATE		__UINT64_C(0x00)
#define ZBX_FLAG_SERVICE_RECALCULATE	__UINT64_C(0x01)
	int			flags;
}
zbx_services_diff_t;

/* preprocessing manager data */
typedef struct
{
	zbx_hashset_t	services;
	zbx_hashset_t	service_diffs;
	zbx_hashset_t	service_problem_tags;
	zbx_hashset_t	service_problem_tags_index;
	zbx_hashset_t	services_links;
	zbx_hashset_t	service_problems_index;
	zbx_hashset_t	problem_events;
	zbx_hashset_t	recovery_events;
	zbx_hashset_t	deleted_eventids;

}
zbx_service_manager_t;

/*#define ZBX_AVAILABILITY_MANAGER_DELAY		1*/

static void	event_free(zbx_event_t *event)
{
	zbx_vector_ptr_clear_ext(&event->tags, (zbx_clean_func_t)zbx_free_tag);
	zbx_vector_ptr_destroy(&event->tags);
	zbx_free(event);
}

static void	event_ptr_free(zbx_event_t **event)
{
	event_free(*event);
}

static zbx_hash_t	default_uint64_ptr_hash_func(const void *d)
{
	return ZBX_DEFAULT_UINT64_HASH_FUNC(*(const zbx_uint64_t **)d);
}

static void	match_event_to_service_problem_tags(zbx_event_t *event, zbx_hashset_t *service_problem_tags_index,
		zbx_hashset_t *services_diffs, int flags)
{
	int			i, j;
	zbx_vector_ptr_t	candidates;

	if (TRIGGER_SEVERITY_NOT_CLASSIFIED == event->severity)
		return;

	zbx_vector_ptr_create(&candidates);

	for (i = 0; i < event->tags.values_num; i++)
	{
		zbx_tag_services_t	tag_services_local, *tag_services;
		const zbx_tag_t		*tag = (const zbx_tag_t *)event->tags.values[i];

		tag_services_local.tag = tag->tag;

		if (NULL != (tag_services = (zbx_tag_services_t *)zbx_hashset_search(service_problem_tags_index,
				&tag_services_local)))
		{
			zbx_values_eq_t	values_eq_local = {.value = tag->value}, *values_eq;

			if (NULL != (values_eq = (zbx_values_eq_t *)zbx_hashset_search(&tag_services->values,
					&values_eq_local)))
			{
				for (j = 0; j < values_eq->service_problem_tags.values_num; j++)
				{
					zbx_service_problem_tag_t	*service_problem_tag;

					service_problem_tag = (zbx_service_problem_tag_t *)values_eq->service_problem_tags.values[j];

					service_problem_tag->current_eventid = event->eventid;

					if (service_problem_tag->service->current_eventid != event->eventid ||
							0 == candidates.values_num)
					{
						service_problem_tag->service->current_eventid = event->eventid;
						zbx_vector_ptr_append(&candidates, service_problem_tag->service);
					}
				}
			}

			for (j = 0; j < tag_services->service_problem_tags_like.values_num; j++)
			{
				zbx_service_problem_tag_t	*service_problem_tag;

				service_problem_tag = (zbx_service_problem_tag_t *)tag_services->service_problem_tags_like.values[j];

				if (NULL == strstr(tag->value, service_problem_tag->value))
					continue;

				service_problem_tag->current_eventid = event->eventid;

				if (service_problem_tag->service->current_eventid != event->eventid ||
						0 == candidates.values_num)
				{
					service_problem_tag->service->current_eventid = event->eventid;
					zbx_vector_ptr_append(&candidates, service_problem_tag->service);
				}
			}
		}
	}

	for (i = 0; i < candidates.values_num; i++)
	{
		zbx_service_t	*service = (zbx_service_t *)candidates.values[i];

		for (j = 0; j < service->service_problem_tags.values_num; j++)
		{
			zbx_service_problem_tag_t	*service_problem_tag;

			service_problem_tag = (zbx_service_problem_tag_t *)service->service_problem_tags.values[j];

			if (service->current_eventid != service_problem_tag->current_eventid)
				break;
		}

		if (j == service->service_problem_tags.values_num)
		{
			zbx_services_diff_t	services_diff = {.serviceid = service->serviceid}, *pservices_diff;
			zbx_service_problem_t	*service_problem;

			if (NULL == (pservices_diff = zbx_hashset_search(services_diffs, &services_diff)))
			{
				zbx_vector_ptr_create(&services_diff.service_problems);
				zbx_vector_ptr_create(&services_diff.service_problems_recovered);
				services_diff.flags = flags;
				pservices_diff = zbx_hashset_insert(services_diffs, &services_diff, sizeof(services_diff));
			}

			service_problem = zbx_malloc(NULL, sizeof(zbx_service_problem_t));
			service_problem->eventid = event->eventid;
			service_problem->service_problemid = 0;
			service_problem->serviceid = pservices_diff->serviceid;
			service_problem->severity = event->severity;
			service_problem->clock = event->clock;

			zbx_vector_ptr_append(&pservices_diff->service_problems, service_problem);
		}
	}

	zbx_vector_ptr_destroy(&candidates);
}

static void	db_get_events(zbx_hashset_t *problem_events)
{
	DB_RESULT	result;
	zbx_event_t	*event = NULL;
	DB_ROW		row;
	zbx_uint64_t	eventid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	result = DBselect("select p.eventid,p.clock,p.severity,t.tag,t.value"
			" from problem p"
			" left join problem_tag t"
				" on p.eventid=t.eventid"
			" where p.source=%d"
				" and p.object=%d"
				" and r_eventid is null"
			" order by p.eventid",
			EVENT_SOURCE_TRIGGERS, EVENT_OBJECT_TRIGGER);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(eventid, row[0]);

		if (NULL == event || eventid != event->eventid)
		{
			event = (zbx_event_t *)zbx_malloc(NULL, sizeof(zbx_event_t));

			event->eventid = eventid;
			event->clock = atoi(row[1]);
			event->value = TRIGGER_VALUE_PROBLEM;
			event->severity = atoi(row[2]);
			zbx_vector_ptr_create(&event->tags);
			zbx_hashset_insert(problem_events, &event, sizeof(zbx_event_t *));
		}

		if (FAIL == DBis_null(row[3]))
		{
			zbx_tag_t	*tag;

			tag = (zbx_tag_t *)zbx_malloc(NULL, sizeof(zbx_tag_t));
			tag->tag = zbx_strdup(NULL, row[3]);
			tag->value = zbx_strdup(NULL, row[4]);
			zbx_vector_ptr_append(&event->tags, tag);
		}
	}
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	add_service_problem(zbx_service_t *service, zbx_hashset_t *service_problems_index,
		zbx_service_problem_t *service_problem)
{
	zbx_service_problem_index_t	*service_problem_index, service_problem_index_local;

	service_problem_index_local.eventid = service_problem->eventid;
	if (NULL == (service_problem_index = zbx_hashset_search(service_problems_index, &service_problem_index_local)))
	{
		zbx_vector_ptr_create(&service_problem_index_local.services);
		service_problem_index = zbx_hashset_insert(service_problems_index, &service_problem_index_local,
				sizeof(service_problem_index_local));
	}

	zbx_vector_ptr_append(&service_problem_index->services, service);

	zbx_vector_ptr_append(&service->service_problems, service_problem);
}

static void	remove_service_problem(zbx_service_t *service, int index, zbx_hashset_t *service_problems_index)
{
	zbx_service_problem_index_t	*service_problem_index, service_problem_index_local;
	int				i;
	zbx_service_problem_t		*service_problem;

	service_problem = (zbx_service_problem_t *)service->service_problems.values[index];

	service_problem_index_local.eventid = service_problem->eventid;
	if (NULL == (service_problem_index = zbx_hashset_search(service_problems_index, &service_problem_index_local)))
	{
		THIS_SHOULD_NEVER_HAPPEN;
	}
	else
	{
		if (FAIL == (i = zbx_vector_ptr_search(&service_problem_index->services, service,
				ZBX_DEFAULT_PTR_COMPARE_FUNC)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
		}
		else
		{
			zbx_vector_ptr_remove_noorder(&service_problem_index->services, i);

			if (0 == service_problem_index->services.values_num)
				zbx_hashset_remove_direct(service_problems_index, service_problem_index);
		}
	}

	zbx_vector_ptr_remove_noorder(&service->service_problems, index);
	zbx_free(service_problem);
}

static zbx_hash_t	values_eq_hash(const void *data)
{
	zbx_values_eq_t	*d = (zbx_values_eq_t *)data;

	return ZBX_DEFAULT_STRING_HASH_ALGO(d->value, strlen(d->value), ZBX_DEFAULT_HASH_SEED);
}

static int	values_eq_compare(const void *d1, const void *d2)
{
	return strcmp(((zbx_values_eq_t *)d1)->value, ((zbx_values_eq_t *)d2)->value);
}

static void	values_eq_clean(void *data)
{
	zbx_values_eq_t	*d = (zbx_values_eq_t *)data;

	zbx_vector_ptr_destroy(&d->service_problem_tags);
	zbx_free(d->value);
}

static void	add_service_problem_tag_index(zbx_hashset_t *service_problem_tags_index,
		zbx_service_problem_tag_t *service_problem_tag)
{
	zbx_tag_services_t	tag_services_local, *tag_services;
	zbx_values_eq_t		value_eq_local, *value_eq;

	tag_services_local.tag = service_problem_tag->tag;

	if (NULL == (tag_services = zbx_hashset_search(service_problem_tags_index, &tag_services_local)))
	{
		tag_services_local.tag = zbx_strdup(NULL, service_problem_tag->tag);

		zbx_hashset_create_ext(&tag_services_local.values, 1,
				values_eq_hash, values_eq_compare, values_eq_clean, ZBX_DEFAULT_MEM_MALLOC_FUNC,
				ZBX_DEFAULT_MEM_REALLOC_FUNC, ZBX_DEFAULT_MEM_FREE_FUNC);
		zbx_vector_ptr_create(&tag_services_local.service_problem_tags_like);

		tag_services = zbx_hashset_insert(service_problem_tags_index, &tag_services_local,
				sizeof(tag_services_local));

	}

	if (ZBX_SERVICE_TAG_OPERATOR_LIKE == service_problem_tag->operator)
	{
		zbx_vector_ptr_append(&tag_services->service_problem_tags_like, service_problem_tag);
	}
	else
	{
		/* add value to index */
		value_eq_local.value = service_problem_tag->value;
		if (NULL == (value_eq = zbx_hashset_search(&tag_services->values, &value_eq_local)))
		{
			value_eq_local.value = zbx_strdup(NULL, service_problem_tag->value);
			zbx_vector_ptr_create(&value_eq_local.service_problem_tags);
			value_eq = zbx_hashset_insert(&tag_services->values, &value_eq_local, sizeof(value_eq_local));
		}

		zbx_vector_ptr_append(&value_eq->service_problem_tags, service_problem_tag);
	}
}
static void	remove_service_problem_tag_index(zbx_hashset_t *service_problem_tags_index,
		zbx_service_problem_tag_t *service_problem_tag)
{
	zbx_tag_services_t	tag_services_local, *tag_services;
	zbx_values_eq_t		value_eq_local, *value_eq;
	int			i;

	tag_services_local.tag = service_problem_tag->tag;

	if (NULL == (tag_services = zbx_hashset_search(service_problem_tags_index, &tag_services_local)))
	{
		THIS_SHOULD_NEVER_HAPPEN;
	}
	else
	{
		if (ZBX_SERVICE_TAG_OPERATOR_LIKE == service_problem_tag->operator)
		{
			i = zbx_vector_ptr_search(&tag_services->service_problem_tags_like, service_problem_tag,
					ZBX_DEFAULT_PTR_COMPARE_FUNC);

			if (FAIL == i)
			{
				THIS_SHOULD_NEVER_HAPPEN;
			}
			else
				zbx_vector_ptr_remove_noorder(&tag_services->service_problem_tags_like, i);
		}
		else
		{
			value_eq_local.value = service_problem_tag->value;
			if (NULL != (value_eq = zbx_hashset_search(&tag_services->values, &value_eq_local)))
			{
				i = zbx_vector_ptr_search(&value_eq->service_problem_tags, service_problem_tag,
						ZBX_DEFAULT_PTR_COMPARE_FUNC);

				if (FAIL == i)
				{
					THIS_SHOULD_NEVER_HAPPEN;
				}
				else
				{
					zbx_vector_ptr_remove_noorder(&value_eq->service_problem_tags, i);
					if (0 == value_eq->service_problem_tags.values_num)
						zbx_hashset_remove_direct(&tag_services->values, value_eq);
				}
			}
		}

		if (0 == tag_services->values.num_data && 0 == tag_services->service_problem_tags_like.values_num)
			zbx_hashset_remove_direct(service_problem_tags_index, tag_services);
	}
}

static void	sync_service_problem_tags(zbx_service_manager_t *service_manager, int *updated, int revision)
{
	DB_RESULT			result;
	DB_ROW				row;
	zbx_service_problem_tag_t	service_problem_tag_local, *service_problem_tag;
	zbx_hashset_iter_t		iter;

	result = DBselect("select service_problem_tagid,serviceid,tag,operator,value"
			" from service_problem_tag"
			" order by serviceid");

	while (NULL != (row = DBfetch(result)))
	{
		zbx_uint64_t	serviceid;
		unsigned char	operator;
		zbx_service_t	*service = NULL;

		ZBX_STR2UINT64(service_problem_tag_local.service_problem_tagid, row[0]);
		ZBX_STR2UINT64(serviceid, row[1]);

		if (NULL == (service_problem_tag = zbx_hashset_search(&service_manager->service_problem_tags,
				&service_problem_tag_local)))
		{
			if (NULL == service || serviceid != service->serviceid)
			{
				if (NULL == (service = zbx_hashset_search(&service_manager->services, &serviceid)))
				{
					THIS_SHOULD_NEVER_HAPPEN;
					continue;
				}
			}

			service_problem_tag_local.revision = revision;
			service_problem_tag_local.current_eventid = 0;
			service_problem_tag_local.tag = zbx_strdup(NULL, row[2]);
			service_problem_tag_local.operator = atoi(row[3]);
			service_problem_tag_local.value = zbx_strdup(NULL, row[4]);
			service_problem_tag_local.service = service;

			service_problem_tag = zbx_hashset_insert(&service_manager->service_problem_tags,
					&service_problem_tag_local, sizeof(service_problem_tag_local));

			zbx_vector_ptr_append(&service_problem_tag_local.service->service_problem_tags,
					service_problem_tag);

			add_service_problem_tag_index(&service_manager->service_problem_tags_index, service_problem_tag);
			(*updated)++;

			continue;
		}

		service_problem_tag->revision = revision;

		operator = (unsigned char)atoi(row[3]);

		if (0 != strcmp(service_problem_tag->tag, row[2]) || service_problem_tag->operator != operator ||
				0 != strcmp(service_problem_tag->value, row[4]))
		{
			remove_service_problem_tag_index(&service_manager->service_problem_tags_index,
					service_problem_tag);

			(*updated)++;
			service_problem_tag->tag = zbx_strdup(service_problem_tag->tag, row[2]);
			service_problem_tag->operator = operator;
			service_problem_tag->value = zbx_strdup(service_problem_tag->value, row[4]);

			add_service_problem_tag_index(&service_manager->service_problem_tags_index,
					service_problem_tag);
		}
	}
	DBfree_result(result);

	zbx_hashset_iter_reset(&service_manager->service_problem_tags, &iter);
	while (NULL != (service_problem_tag = (zbx_service_problem_tag_t *)zbx_hashset_iter_next(&iter)))
	{
		int	i;

		if (revision == service_problem_tag->revision)
			continue;

		remove_service_problem_tag_index(&service_manager->service_problem_tags_index, service_problem_tag);

		i = zbx_vector_ptr_search(&service_problem_tag->service->service_problem_tags,
				service_problem_tag, ZBX_DEFAULT_PTR_COMPARE_FUNC);
		if (FAIL == i)
			THIS_SHOULD_NEVER_HAPPEN;
		else
			zbx_vector_ptr_remove_noorder(&service_problem_tag->service->service_problem_tags, i);

		(*updated)++;
		zbx_hashset_iter_remove(&iter);
	}
}

static void	sync_services(zbx_service_manager_t *service_manager, int *updated, int revision)
{
	DB_RESULT		result;
	DB_ROW			row;
	zbx_service_t		service_local, *service;
	zbx_hashset_iter_t	iter;

	result = DBselect("select serviceid,status,algorithm from services");

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(service_local.serviceid, row[0]);
		service_local.status = atoi(row[1]);
		service_local.algorithm = atoi(row[2]);

		if (NULL == (service = zbx_hashset_search(&service_manager->services, &service_local)))
		{
			service_local.revision = revision;
			service_local.current_eventid = 0;

			zbx_vector_ptr_create(&service_local.children);
			zbx_vector_ptr_create(&service_local.parents);
			zbx_vector_ptr_create(&service_local.service_problem_tags);
			zbx_vector_ptr_create(&service_local.service_problems);

			zbx_hashset_insert(&service_manager->services, &service_local, sizeof(service_local));

			(*updated)++;
			continue;
		}

		service->revision = revision;

		zbx_vector_ptr_clear(&service->children);
		zbx_vector_ptr_clear(&service->parents);

		if (service->status != service_local.status || service->algorithm != service_local.algorithm)
		{
			service->status = service_local.status;
			service->algorithm = service_local.algorithm;
			(*updated)++;
		}
	}
	DBfree_result(result);

	zbx_hashset_iter_reset(&service_manager->services, &iter);
	while (NULL != (service = (zbx_service_t *)zbx_hashset_iter_next(&iter)))
	{
		int	i;

		if (revision == service->revision)
			continue;

		for (i = 0; i < service->service_problem_tags.values_num; i++)
		{
			zbx_service_problem_tag_t	*service_problem_tag;

			service_problem_tag = (zbx_service_problem_tag_t *)service->service_problem_tags.values[i];

			remove_service_problem_tag_index(&service_manager->service_problem_tags_index,
					service_problem_tag);

			zbx_hashset_remove_direct(&service_manager->service_problem_tags, service_problem_tag);
		}

		for (i = 0; i < service->service_problems.values_num; i++)
		{
			remove_service_problem(service, i, &service_manager->service_problems_index);
			i--;
		}

		zbx_hashset_iter_remove(&iter);
		(*updated)++;
	}
}

static void	sync_services_links(zbx_service_manager_t *service_manager, int *updated, int revision)
{
	DB_RESULT		result;
	DB_ROW			row;
	zbx_hashset_iter_t	iter;
	zbx_services_link_t	services_link_local, *services_link;

	result = DBselect("select linkid,serviceupid,servicedownid from services_links");

	while (NULL != (row = DBfetch(result)))
	{
		zbx_service_t	*parent, *child;
		zbx_uint64_t	serviceupid;
		zbx_uint64_t	servicedownid;

		ZBX_STR2UINT64(services_link_local.linkid, row[0]);
		ZBX_STR2UINT64(serviceupid, row[1]);
		ZBX_STR2UINT64(servicedownid, row[2]);

		if (NULL == (services_link = zbx_hashset_search(&service_manager->services_links,
				&services_link_local)))
		{
			(*updated)++;
			services_link = zbx_hashset_insert(&service_manager->services_links, &services_link_local,
					sizeof(services_link_local));
		}

		services_link->revision = revision;

		parent = zbx_hashset_search(&service_manager->services, &serviceupid);
		child = zbx_hashset_search(&service_manager->services, &servicedownid);

		if (NULL == parent || NULL == child)
		{
			/* it is not possible for link to exist without corresponding service */
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		zbx_vector_ptr_append(&parent->children, child);
		zbx_vector_ptr_append(&child->parents, parent);
	}
	DBfree_result(result);

	zbx_hashset_iter_reset(&service_manager->services_links, &iter);
	while (NULL != (services_link = (zbx_services_link_t *)zbx_hashset_iter_next(&iter)))
	{
		if (revision == services_link->revision)
			continue;

		zbx_hashset_iter_remove(&iter);
		(*updated)++;
	}
}

static void	sync_service_problems(zbx_hashset_t *services, zbx_hashset_t *service_problems_index)
{
	DB_RESULT	result;
	DB_ROW		row;
	zbx_service_t	service_local, *service;

	result = DBselect("select service_problemid,eventid,serviceid,severity from service_problem");

	while (NULL != (row = DBfetch(result)))
	{
		zbx_service_problem_t	*service_problem;

		ZBX_STR2UINT64(service_local.serviceid, row[2]);

		if (NULL == (service = zbx_hashset_search(services, &service_local)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		service_problem = zbx_malloc(NULL, sizeof(zbx_service_problem_t));
		ZBX_STR2UINT64(service_problem->service_problemid, row[0]);
		ZBX_STR2UINT64(service_problem->eventid, row[1]);
		service_problem->serviceid = service_local.serviceid;
		service_problem->severity = atoi(row[3]);
		service_problem->clock = 0;

		add_service_problem(service, service_problems_index, service_problem);
	}
	DBfree_result(result);
}

static void	service_clean(zbx_service_t *service)
{
	zbx_vector_ptr_destroy(&service->children);
	zbx_vector_ptr_destroy(&service->parents);
	zbx_vector_ptr_destroy(&service->service_problem_tags);
	zbx_vector_ptr_clear_ext(&service->service_problems, zbx_ptr_free);
	zbx_vector_ptr_destroy(&service->service_problems);

}

static void	service_problem_tag_clean(zbx_service_problem_tag_t *service_problem_tag)
{
	zbx_free(service_problem_tag->tag);
	zbx_free(service_problem_tag->value);
}

static void	service_diff_clean(void *data)
{
	zbx_services_diff_t	*d = (zbx_services_diff_t *)data;

	zbx_vector_ptr_clear_ext(&d->service_problems_recovered, zbx_ptr_free);
	zbx_vector_ptr_clear_ext(&d->service_problems, zbx_ptr_free);
	zbx_vector_ptr_destroy(&d->service_problems);
	zbx_vector_ptr_destroy(&d->service_problems_recovered);
}

static zbx_hash_t	tag_services_hash(const void *data)
{
	zbx_tag_services_t	*d = (zbx_tag_services_t *)data;

	return ZBX_DEFAULT_STRING_HASH_ALGO(d->tag, strlen(d->tag), ZBX_DEFAULT_HASH_SEED);
}

static int	tag_services_compare(const void *d1, const void *d2)
{
	return strcmp(((zbx_tag_services_t *)d1)->tag, ((zbx_tag_services_t *)d2)->tag);
}

static void	tag_services_clean(void *data)
{
	zbx_tag_services_t	*d = (zbx_tag_services_t *)data;

	zbx_vector_ptr_destroy(&d->service_problem_tags_like);
	zbx_hashset_destroy(&d->values);
	zbx_free(d->tag);
}

static void	service_problems_index_clean(void *data)
{
	zbx_service_problem_index_t	*d = (zbx_service_problem_index_t *)data;

	zbx_vector_ptr_destroy(&d->services);
}

/* status update queue items */
typedef struct
{
	/* the update source id */
	zbx_uint64_t	sourceid;
	/* the new status */
	int		status;
	/* timestamp */
	int		clock;
}
zbx_status_update_t;

/* status update queue items */
typedef struct
{
	zbx_uint64_t	serviceid;
	int		old_status;
	int		status;
}
zbx_service_update_t;

/******************************************************************************
 *                                                                            *
 * Function: its_updates_append                                               *
 *                                                                            *
 * Purpose: adds an update to the queue                                       *
 *                                                                            *
 * Parameters: updates   - [OUT] the update queue                             *
 *             sourceid  - [IN] the update source id                          *
 *             status    - [IN] the update status                             *
 *             clock     - [IN] the update timestamp                          *
 *                                                                            *
 ******************************************************************************/
static void	its_updates_append(zbx_vector_ptr_t *updates, zbx_uint64_t sourceid, int status, int clock)
{
	zbx_status_update_t	*update;

	update = (zbx_status_update_t *)zbx_malloc(NULL, sizeof(zbx_status_update_t));

	update->sourceid = sourceid;
	update->status = status;
	update->clock = clock;

	zbx_vector_ptr_append(updates, update);
}

static void	update_service(zbx_hashset_t *service_updates, zbx_uint64_t serviceid, int old_status, int status)
{
	zbx_service_update_t	update_local = {.serviceid = serviceid}, *update;

	if (NULL == (update = (zbx_service_update_t *)zbx_hashset_search(service_updates, &update_local)))
	{
		update_local.old_status = old_status;
		update = (zbx_service_update_t *)zbx_hashset_insert(service_updates, &update_local,
				sizeof(update_local));
	}

	update->status = status;
}

/******************************************************************************
 *                                                                            *
 * Function: its_updates_compare                                              *
 *                                                                            *
 * Purpose: used to sort service updates by source id                         *
 *                                                                            *
 ******************************************************************************/
static int	its_updates_compare(const zbx_status_update_t **update1, const zbx_status_update_t **update2)
{
	ZBX_RETURN_IF_NOT_EQUAL((*update1)->sourceid, (*update2)->sourceid);

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Function: its_write_status_and_alarms                                      *
 *                                                                            *
 * Purpose: writes service status changes and generated service alarms into   *
 *          database                                                          *
 *                                                                            *
 * Parameters: itservices - [IN] the services data                            *
 *             alarms     - [IN] the service alarms update queue              *
 *                                                                            *
 * Return value: SUCCEED - the data was written successfully                  *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	its_write_status_and_alarms(zbx_vector_ptr_t *alarms, zbx_hashset_t *service_updates,
		zbx_vector_ptr_t *service_problems_new, zbx_vector_uint64_t *service_problemids)
{
	int			i, ret = FAIL;
	zbx_vector_ptr_t	updates;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_uint64_t		alarmid;
	zbx_hashset_iter_t	iter;
	zbx_service_update_t	*itservice;

	/* get a list of service status updates that must be written to database */
	zbx_vector_ptr_create(&updates);

	zbx_hashset_iter_reset(service_updates, &iter);
	while (NULL != (itservice = (zbx_service_update_t *)zbx_hashset_iter_next(&iter)))
	{
		if (itservice->old_status != itservice->status)
			its_updates_append(&updates, itservice->serviceid, itservice->status, 0);
	}

	/* write service status changes into database */
	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (0 != updates.values_num)
	{
		zbx_vector_ptr_sort(&updates, (zbx_compare_func_t)its_updates_compare);

		for (i = 0; i < updates.values_num; i++)
		{
			zbx_status_update_t	*update = (zbx_status_update_t *)updates.values[i];

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
					"update services"
					" set status=%d"
					" where serviceid=" ZBX_FS_UI64 ";\n",
					update->status, update->sourceid);

			if (SUCCEED != DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset))
				goto out;
		}
	}

	zbx_vector_uint64_sort(service_problemids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	if (0 != service_problemids->values_num)
	{
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from service_problem where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "service_problemid", service_problemids->values,
				service_problemids->values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");
	}

	DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (16 < sql_offset)
	{
		if (ZBX_DB_OK > DBexecute("%s", sql))
			goto out;
	}

	ret = SUCCEED;

	/* write generated service alarms into database */
	if (0 != alarms->values_num)
	{
		zbx_db_insert_t	db_insert;

		alarmid = DBget_maxid_num("service_alarms", alarms->values_num);

		zbx_db_insert_prepare(&db_insert, "service_alarms", "servicealarmid", "serviceid", "value", "clock",
				NULL);

		for (i = 0; i < alarms->values_num; i++)
		{
			zbx_status_update_t	*update = (zbx_status_update_t *)alarms->values[i];

			zbx_db_insert_add_values(&db_insert, alarmid++, update->sourceid, update->status,
					update->clock);
		}

		ret = zbx_db_insert_execute(&db_insert);

		zbx_db_insert_clean(&db_insert);
	}

	if (0 != service_problems_new->values_num)
	{
		zbx_db_insert_t		db_insert;
		zbx_uint64_t		service_problemid;
		zbx_vector_uint64_t	ids;

		zbx_vector_uint64_create(&ids);

		service_problemid = DBget_maxid_num("service_problem", service_problems_new->values_num);

		zbx_db_insert_prepare(&db_insert, "service_problem", "service_problemid", "eventid", "serviceid",
				"severity", NULL);

		zbx_vector_ptr_sort(service_problems_new, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);
		for (i = 0; i < service_problems_new->values_num; i++)
		{
			zbx_service_problem_t	*service_problem;

			service_problem = (zbx_service_problem_t *)service_problems_new->values[i];
			zbx_vector_uint64_append(&ids, service_problem->eventid);
		}

		DBlock_ids("problem", "eventid", &ids);

		for (i = 0; i < service_problems_new->values_num; i++)
		{
			zbx_service_problem_t	*service_problem;

			service_problem = (zbx_service_problem_t *)service_problems_new->values[i];

			if (FAIL == zbx_vector_uint64_bsearch(&ids, service_problem->eventid,
					ZBX_DEFAULT_UINT64_COMPARE_FUNC))
			{
				continue;
			}

			service_problem->service_problemid = service_problemid++;
			zbx_db_insert_add_values(&db_insert, service_problem->service_problemid,
					service_problem->eventid, service_problem->serviceid,
					service_problem->severity);
		}

		zbx_vector_uint64_destroy(&ids);
		ret = zbx_db_insert_execute(&db_insert);

		zbx_db_insert_clean(&db_insert);
	}
out:
	zbx_free(sql);

	zbx_vector_ptr_clear_ext(&updates, zbx_ptr_free);
	zbx_vector_ptr_destroy(&updates);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: its_itservice_update_status                                      *
 *                                                                            *
 * Purpose: updates service and its parents statuses                          *
 *                                                                            *
 * Parameters: service    - [IN] the service to update                        *
 *             clock      - [IN] the update timestamp                         *
 *             alarms     - [OUT] the alarms update queue                     *
 *                                                                            *
 * Comments: This function recalculates service status according to the       *
 *           algorithm and status of the children services. If the status     *
 *           has been changed, an alarm is generated and parent services      *
 *           (up until the root service) are updated too.                     *
 *                                                                            *
 ******************************************************************************/
static void	its_itservice_update_status(zbx_service_t *itservice, int clock, zbx_vector_ptr_t *alarms,
		zbx_hashset_t *service_updates, int flags)
{
	int	status, i;

	switch (itservice->algorithm)
	{
		case SERVICE_ALGORITHM_MIN:
			status = TRIGGER_SEVERITY_COUNT;
			for (i = 0; i < itservice->children.values_num; i++)
			{
				zbx_service_t	*child = (zbx_service_t *)itservice->children.values[i];

				if (child->status < status)
					status = child->status;
			}
			break;
		case SERVICE_ALGORITHM_MAX:
			status = 0;
			for (i = 0; i < itservice->children.values_num; i++)
			{
				zbx_service_t	*child = (zbx_service_t *)itservice->children.values[i];

				if (child->status > status)
					status = child->status;
			}
			break;
		case SERVICE_ALGORITHM_NONE:
			return;
		default:
			zabbix_log(LOG_LEVEL_ERR, "unknown calculation algorithm of service status [%d]",
					itservice->algorithm);
			return;
	}

	if (itservice->status != status)
	{
		update_service(service_updates, itservice->serviceid, itservice->status, status);
		itservice->status = status;

		its_updates_append(alarms, itservice->serviceid, status, clock);

		/* update parent services */
		for (i = 0; i < itservice->parents.values_num; i++)
		{
			its_itservice_update_status((zbx_service_t *)itservice->parents.values[i], clock, alarms,
					service_updates, flags);
		}
	}
	else if (0 != (ZBX_FLAG_SERVICE_RECALCULATE & flags))
	{
		/* update parent services */
		for (i = 0; i < itservice->parents.values_num; i++)
		{
			its_itservice_update_status((zbx_service_t *)itservice->parents.values[i], clock, alarms,
					service_updates, flags);
		}
	}
}

static void	db_update_services(zbx_hashset_t *services, zbx_hashset_t *service_diffs,
		zbx_hashset_t *service_problems_index)
{
	zbx_hashset_iter_t	iter;
	zbx_services_diff_t	*service_diff;
	zbx_vector_ptr_t	alarms, service_problems_new;
	zbx_vector_uint64_t	service_problemids;
	zbx_hashset_t		service_updates;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_ptr_create(&alarms);
	zbx_vector_ptr_create(&service_problems_new);
	zbx_vector_uint64_create(&service_problemids);
	zbx_hashset_create(&service_updates, 100, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_hashset_iter_reset(service_diffs, &iter);
	while (NULL != (service_diff = (zbx_services_diff_t *)zbx_hashset_iter_next(&iter)))
	{
		zbx_service_t	service_local = {.serviceid = service_diff->serviceid}, *service;
		int		status = TRIGGER_SEVERITY_NOT_CLASSIFIED, i, clock = 0;

		service = zbx_hashset_search(services, &service_local);

		if (NULL == service)
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		if (service_diff->flags & ZBX_FLAG_SERVICE_RECALCULATE)
		{
			for (i = 0; i < service->service_problems.values_num; i++)
			{
				zbx_service_problem_t	*service_problem;
				int			index;

				service_problem = (zbx_service_problem_t *)service->service_problems.values[i];

				if (FAIL == (index = zbx_vector_ptr_search(&service_diff->service_problems,
						&service_problem->eventid, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
				{
					zbx_vector_uint64_append(&service_problemids, service_problem->service_problemid);
					remove_service_problem(service, i, service_problems_index);
					i--;
					continue;
				}

				zbx_free(service_diff->service_problems.values[index]);
				zbx_vector_ptr_remove_noorder(&service_diff->service_problems, index);
			}
		}

		for (i = 0; i < service_diff->service_problems.values_num; i++)
		{
			zbx_service_problem_t	*service_problem;

			service_problem = (zbx_service_problem_t *)service_diff->service_problems.values[i];
			add_service_problem(service, service_problems_index, service_problem);
			zbx_vector_ptr_append(&service_problems_new, service_problem);
		}
		service_diff->service_problems.values_num = 0;

		for (i = 0; i < service_diff->service_problems_recovered.values_num; i++)
		{
			zbx_service_problem_t	*service_problem;
			int			index;

			service_problem = (zbx_service_problem_t *)service_diff->service_problems_recovered.values[i];

			if (FAIL == (index = zbx_vector_ptr_search(&service->service_problems,
					&service_problem->eventid, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
			{
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
			}
			else
			{
				if (clock < service_problem->clock)
					clock = service_problem->clock;

				service_problem = (zbx_service_problem_t *)service->service_problems.values[index];
				zbx_vector_uint64_append(&service_problemids, service_problem->service_problemid);

				remove_service_problem(service, index, service_problems_index);
			}
		}

		for (i = 0; i < service->service_problems.values_num; i++)
		{
			zbx_service_problem_t	*service_problem;

			service_problem = (zbx_service_problem_t *)service->service_problems.values[i];

			if (service_problem->severity > status)
			{
				status = service_problem->severity;
				clock = service_problem->clock;
			}
		}

		if (0 == clock)
			clock = time(NULL);

		if (SERVICE_ALGORITHM_NONE == service->algorithm)
			continue;

		if (service->status != status)
		{
			update_service(&service_updates, service->serviceid, service->status, status);
			service->status = status;

			its_updates_append(&alarms, service->serviceid, service->status, clock);

			/* update parent services */
			for (i = 0; i < service->parents.values_num; i++)
			{
				its_itservice_update_status((zbx_service_t *)service->parents.values[i], clock, &alarms,
						&service_updates, service_diff->flags);
			}
		}
		else if (0 != (ZBX_FLAG_SERVICE_RECALCULATE & service_diff->flags))
		{
			/* update parent services */
			for (i = 0; i < service->parents.values_num; i++)
			{
				its_itservice_update_status((zbx_service_t *)service->parents.values[i], clock, &alarms,
						&service_updates, service_diff->flags);
			}
		}
	}

	do
	{
		DBbegin();
		its_write_status_and_alarms(&alarms, &service_updates, &service_problems_new, &service_problemids);
	}
	while (ZBX_DB_DOWN == DBcommit());

	zbx_vector_uint64_destroy(&service_problemids);
	zbx_vector_ptr_destroy(&service_problems_new);
	zbx_hashset_destroy(&service_updates);
	zbx_vector_ptr_clear_ext(&alarms, zbx_ptr_free);
	zbx_vector_ptr_destroy(&alarms);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	recover_services_problem(zbx_service_manager_t *service_manager, const zbx_event_t *event)
{
	zbx_service_problem_index_t	*service_problem_index, service_problem_index_local;

	service_problem_index_local.eventid = event->eventid;
	if (NULL != (service_problem_index = zbx_hashset_search(&service_manager->service_problems_index,
			&service_problem_index_local)))
	{
		int	i;

		for (i = 0; i < service_problem_index->services.values_num; i++)
		{
			zbx_service_t		*service;
			zbx_services_diff_t	service_diff_local, *service_diff;
			zbx_service_problem_t	*service_problem;

			service = (zbx_service_t *)service_problem_index->services.values[i];

			service_diff_local.serviceid = service->serviceid;
			if (NULL == (service_diff = zbx_hashset_search(&service_manager->service_diffs,
					&service_diff_local)))
			{
				zbx_vector_ptr_create(&service_diff_local.service_problems);
				zbx_vector_ptr_create(&service_diff_local.service_problems_recovered);
				service_diff_local.flags = ZBX_FLAG_SERVICE_UPDATE;
				service_diff = zbx_hashset_insert(&service_manager->service_diffs,
						&service_diff_local, sizeof(service_diff_local));
			}

			service_problem = zbx_malloc(NULL, sizeof(zbx_service_problem_t));
			service_problem->eventid = event->eventid;
			service_problem->serviceid = service_diff->serviceid;
			service_problem->severity = event->severity;
			service_problem->clock = event->clock;

			zbx_vector_ptr_append(&service_diff->service_problems_recovered, service_problem);
		}
	}
}

static void	process_deleted_problems(zbx_vector_uint64_t *eventids, zbx_service_manager_t *service_manager)
{
	int	i, now;

	now = time(NULL);

	for (i = 0; i < eventids->values_num; i++)
	{
		zbx_event_t		*event, event_local = {.eventid = eventids->values[i]}, **ptr;
		zbx_uint64_pair_t	pair;

		pair.first = eventids->values[i];
		pair.second = now;

		zbx_hashset_insert(&service_manager->deleted_eventids, &pair, sizeof(pair));

		event = &event_local;

		if (NULL == (ptr = zbx_hashset_search(&service_manager->problem_events, &event)))
			continue;

		event = *ptr;
		event->clock = now;
		recover_services_problem(service_manager, event);

		zbx_hashset_remove_direct(&service_manager->problem_events, ptr);
	}

	db_update_services(&service_manager->services, &service_manager->service_diffs,
			&service_manager->service_problems_index);
	zbx_hashset_clear(&service_manager->service_diffs);
}

static void	process_problem_tags(zbx_vector_ptr_t *events, zbx_service_manager_t *service_manager)
{
	int	i, j;

	for (i = 0; i < events->values_num; i++)
	{
		zbx_event_t	*event, **ptr;

		event = (zbx_event_t *)events->values[i];

		if (NULL == (ptr = zbx_hashset_search(&service_manager->problem_events, &event)))
		{
			event_free(event);
			continue;
		}

		for (j = 0; j < event->tags.values_num; j++)
			zbx_vector_ptr_append(&(*ptr)->tags, event->tags.values[j]);

		event->tags.values_num = 0;
		event_free(event);

		match_event_to_service_problem_tags(*ptr, &service_manager->service_problem_tags_index,
				&service_manager->service_diffs, ZBX_FLAG_SERVICE_RECALCULATE);
	}

	db_update_services(&service_manager->services, &service_manager->service_diffs,
			&service_manager->service_problems_index);
	zbx_hashset_clear(&service_manager->service_diffs);
}

static void	process_events(zbx_vector_ptr_t *events, zbx_service_manager_t *service_manager)
{
	int	i;

	for (i = 0; i < events->values_num; i++)
	{
		zbx_event_t	*event, **ptr;

		event = events->values[i];

		/*  skip problem or recovery if trigger and it's associated problems are already deleted */
		if (NULL != (zbx_hashset_search(&service_manager->deleted_eventids, &event->eventid)))
			continue;

		switch (event->value)
		{
			case TRIGGER_VALUE_OK:
				if (NULL == (ptr = zbx_hashset_search(&service_manager->problem_events, &event)))
				{
					/* handle possible race condition when recovery is received before problem */
					zbx_hashset_insert(&service_manager->recovery_events, &event,
							sizeof(zbx_event_t *));
					continue;
				}

				recover_services_problem(service_manager, event);

				event_free(event);
				zbx_hashset_remove_direct(&service_manager->problem_events, ptr);
				break;
			case TRIGGER_VALUE_PROBLEM:
				if (NULL != (ptr = zbx_hashset_search(&service_manager->problem_events, &event)))
				{
					zabbix_log(LOG_LEVEL_ERR, "cannot process event \"" ZBX_FS_UI64 "\": event"
							" already processed", event->eventid);
					THIS_SHOULD_NEVER_HAPPEN;
					event_free(event);
					continue;
				}

				if (NULL != (ptr = zbx_hashset_search(&service_manager->recovery_events, &event)))
				{
					/* handle possible race condition when recovery is received before problem */
					zbx_hashset_remove_direct(&service_manager->recovery_events, ptr);
					event_free(event);
					continue;
				}

				zbx_hashset_insert(&service_manager->problem_events, &event, sizeof(zbx_event_t *));

				match_event_to_service_problem_tags(event,
						&service_manager->service_problem_tags_index,
						&service_manager->service_diffs,
						ZBX_FLAG_SERVICE_UPDATE);

				break;
			default:
				zabbix_log(LOG_LEVEL_ERR, "cannot process event \"" ZBX_FS_UI64 "\" unexpected value:%d",
						event->eventid, event->value);
				THIS_SHOULD_NEVER_HAPPEN;
				event_free(event);
		}
	}

	db_update_services(&service_manager->services, &service_manager->service_diffs,
			&service_manager->service_problems_index);
	zbx_hashset_clear(&service_manager->service_diffs);
}

static void	service_manager_init(zbx_service_manager_t *service_manager)
{
	zbx_hashset_create_ext(&service_manager->problem_events, 1000, default_uint64_ptr_hash_func,
			ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC, (zbx_clean_func_t)event_ptr_free,
			ZBX_DEFAULT_MEM_MALLOC_FUNC, ZBX_DEFAULT_MEM_REALLOC_FUNC, ZBX_DEFAULT_MEM_FREE_FUNC);

	zbx_hashset_create_ext(&service_manager->recovery_events, 1, default_uint64_ptr_hash_func,
			ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC, (zbx_clean_func_t)event_ptr_free,
			ZBX_DEFAULT_MEM_MALLOC_FUNC, ZBX_DEFAULT_MEM_REALLOC_FUNC, ZBX_DEFAULT_MEM_FREE_FUNC);

	zbx_hashset_create_ext(&service_manager->services, 1000, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC, (zbx_clean_func_t)service_clean,
			ZBX_DEFAULT_MEM_MALLOC_FUNC, ZBX_DEFAULT_MEM_REALLOC_FUNC, ZBX_DEFAULT_MEM_FREE_FUNC);

	zbx_hashset_create_ext(&service_manager->service_problem_tags, 1000, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC, (zbx_clean_func_t)service_problem_tag_clean,
			ZBX_DEFAULT_MEM_MALLOC_FUNC, ZBX_DEFAULT_MEM_REALLOC_FUNC, ZBX_DEFAULT_MEM_FREE_FUNC);

	zbx_hashset_create_ext(&service_manager->service_problem_tags_index, 1000, tag_services_hash,
			tag_services_compare, tag_services_clean, ZBX_DEFAULT_MEM_MALLOC_FUNC,
			ZBX_DEFAULT_MEM_REALLOC_FUNC, ZBX_DEFAULT_MEM_FREE_FUNC);

	zbx_hashset_create_ext(&service_manager->service_problems_index, 1000, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC, service_problems_index_clean, ZBX_DEFAULT_MEM_MALLOC_FUNC,
			ZBX_DEFAULT_MEM_REALLOC_FUNC, ZBX_DEFAULT_MEM_FREE_FUNC);

	zbx_hashset_create(&service_manager->services_links, 1000, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_hashset_create_ext(&service_manager->service_diffs, 1000, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC, service_diff_clean, ZBX_DEFAULT_MEM_MALLOC_FUNC,
			ZBX_DEFAULT_MEM_REALLOC_FUNC, ZBX_DEFAULT_MEM_FREE_FUNC);

	zbx_hashset_create(&service_manager->deleted_eventids, 1000, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
}

static void	service_manager_free(zbx_service_manager_t *service_manager)
{
	zbx_hashset_destroy(&service_manager->service_problems_index);
	zbx_hashset_destroy(&service_manager->services_links);
	zbx_hashset_destroy(&service_manager->service_problem_tags);
	zbx_hashset_destroy(&service_manager->services);
	zbx_hashset_destroy(&service_manager->problem_events);
	zbx_hashset_destroy(&service_manager->recovery_events);
	zbx_hashset_destroy(&service_manager->deleted_eventids);
}

static void	service_manager_trace(zbx_service_manager_t *service_manager)
{

	zabbix_log(LOG_LEVEL_TRACE, "problem events  : %d (%d slots)",
					service_manager->problem_events.num_data,
					service_manager->problem_events.num_slots);
	zabbix_log(LOG_LEVEL_TRACE, "recovery events  : %d (%d slots)",
					service_manager->recovery_events.num_data,
					service_manager->recovery_events.num_slots);
	zabbix_log(LOG_LEVEL_TRACE, "deleted events  : %d (%d slots)",
			service_manager->deleted_eventids.num_data, service_manager->deleted_eventids.num_slots);

}

static void	recalculate_services(zbx_service_manager_t *service_manager)
{
	zbx_hashset_iter_t	iter;
	zbx_event_t		**event;
	zbx_service_t		*service;
	zbx_services_diff_t	services_diff_local, *services_diff;
	int			flags;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	flags = ZBX_FLAG_SERVICE_RECALCULATE;

	zbx_hashset_iter_reset(&service_manager->problem_events, &iter);
	while (NULL != (event = (zbx_event_t **)zbx_hashset_iter_next(&iter)))
	{
		match_event_to_service_problem_tags(*event, &service_manager->service_problem_tags_index,
				&service_manager->service_diffs, flags);
	}

	zbx_hashset_iter_reset(&service_manager->services, &iter);
	while (NULL != (service = (zbx_service_t *)zbx_hashset_iter_next(&iter)))
	{
		if (0 != service->children.values_num)
			continue;

		services_diff_local.serviceid = service->serviceid;
		services_diff = zbx_hashset_search(&service_manager->service_diffs, &services_diff_local);

		if (NULL == services_diff)
		{
			zbx_vector_ptr_create(&services_diff_local.service_problems);
			zbx_vector_ptr_create(&services_diff_local.service_problems_recovered);
			services_diff_local.flags = flags;
			zbx_hashset_insert(&service_manager->service_diffs, &services_diff_local,
					sizeof(services_diff_local));
		}
	}

	db_update_services(&service_manager->services, &service_manager->service_diffs,
			&service_manager->service_problems_index);
	zbx_hashset_clear(&service_manager->service_diffs);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	cleanup_deleted_problems(zbx_service_manager_t *service_manager, int now)
{
	zbx_hashset_iter_t	iter;
	zbx_uint64_pair_t	*pair;

	zbx_hashset_iter_reset(&service_manager->deleted_eventids, &iter);
	while (NULL != (pair = (zbx_uint64_pair_t *)zbx_hashset_iter_next(&iter)))
	{
		if (ZBX_PROBLEM_CLEANUP_AGE < now - (int)pair->second)
			zbx_hashset_iter_remove(&iter);
	}
}

static void	zbx_serviceman_sigusr_handler(int flags)
{
	if (ZBX_RTC_SERVICE_CACHE_RELOAD != ZBX_RTC_GET_MSG(flags))
		return;

	if (1 == service_cache_reload_requested)
	{
		zabbix_log(LOG_LEVEL_WARNING, "service manager cache reloading is already in progress");
	}
	else
	{
		service_cache_reload_requested = 1;
		zabbix_log(LOG_LEVEL_WARNING, "forced reloading of the service manager cache");
	}
}

ZBX_THREAD_ENTRY(service_manager_thread, args)
{
	zbx_ipc_service_t	service;
	char			*error = NULL;
	zbx_ipc_client_t	*client;
	zbx_ipc_message_t	*message;
	int			ret, processed_num = 0;
	double			time_stat, time_idle = 0, time_now, time_flush = 0, time_cleanup = 0, sec;
	zbx_service_manager_t	service_manager;

#define	STAT_INTERVAL	5	/* if a process is busy and does not sleep then update status not faster than */
				/* once in STAT_INTERVAL seconds */

	process_type = ((zbx_thread_args_t *)args)->process_type;
	server_num = ((zbx_thread_args_t *)args)->server_num;
	process_num = ((zbx_thread_args_t *)args)->process_num;

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(program_type),
				server_num, get_process_type_string(process_type), process_num);

	update_selfmon_counter(ZBX_PROCESS_STATE_BUSY);

	zbx_setproctitle("%s #%d [connecting to the database]", get_process_type_string(process_type), process_num);

	DBconnect(ZBX_DB_CONNECT_NORMAL);

	zbx_set_sigusr_handler(zbx_serviceman_sigusr_handler);

	if (FAIL == zbx_ipc_service_start(&service, ZBX_IPC_SERVICE_SERVICE, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot start service manager service: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	/* initialize statistics */
	time_stat = zbx_time();

	service_manager_init(&service_manager);

	DBbegin();
	db_get_events(&service_manager.problem_events);
	DBcommit();

	zbx_setproctitle("%s #%d started", get_process_type_string(process_type), process_num);

	while (ZBX_IS_RUNNING())
	{
		time_now = zbx_time();
//
//		if (STAT_INTERVAL < time_now - time_stat)
//		{
//			zbx_setproctitle("%s #%d [queued %d, processed %d values, idle "
//					ZBX_FS_DBL " sec during " ZBX_FS_DBL " sec]",
//					get_process_type_string(process_type), process_num,
//					interface_availabilities.values_num, processed_num, time_idle, time_now - time_stat);
//
//			time_stat = time_now;
//			time_idle = 0;
//			processed_num = 0;
//		}
//
		if (CONFIG_SERVICEMAN_SYNC_FREQUENCY < time_now - time_flush || 1 == service_cache_reload_requested)
		{
			int	updated = 0, revision = time(NULL);

			service_cache_reload_requested = 0;

			DBbegin();
			sync_services(&service_manager, &updated, revision);
			sync_service_problem_tags(&service_manager, &updated, revision);
			sync_services_links(&service_manager, &updated, revision);

			/* load service problems once during startup */
			if (0 == time_flush)
				sync_service_problems(&service_manager.services, &service_manager.service_problems_index);

			DBcommit();

			if (0 != updated)
				recalculate_services(&service_manager);

			time_flush = time_now;
			time_now = zbx_time();

			service_manager_trace(&service_manager);
		}

		if (ZBX_PROBLEM_CLEANUP_FREQUENCY < time_now - time_cleanup)
		{
			cleanup_deleted_problems(&service_manager, time_now);

			time_cleanup = time_now;
			time_now = zbx_time();
		}

		update_selfmon_counter(ZBX_PROCESS_STATE_IDLE);
		ret = zbx_ipc_service_recv(&service, 1, &client, &message);
		update_selfmon_counter(ZBX_PROCESS_STATE_BUSY);
		sec = zbx_time();
		zbx_update_env(sec);

		if (ZBX_IPC_RECV_IMMEDIATE != ret)
			time_idle += sec - time_now;

		if (NULL != message)
		{
			zbx_vector_ptr_t	events;
			zbx_vector_uint64_t	eventids;

			zbx_vector_ptr_create(&events);
			zbx_vector_uint64_create(&eventids);

			switch (message->code)
			{
				case ZBX_IPC_SERVICE_SERVICE_PROBLEMS:
					zbx_service_deserialize(message->data, message->size, &events);
					process_events(&events, &service_manager);
					break;
				case ZBX_IPC_SERVICE_SERVICE_PROBLEMS_TAGS:
					zbx_service_deserialize_problem_tags(message->data, message->size, &events);
					process_problem_tags(&events, &service_manager);
					break;
				case ZBX_IPC_SERVICE_SERVICE_PROBLEMS_DELETE:
					zbx_service_deserialize_eventids(message->data, message->size, &eventids);
					process_deleted_problems(&eventids, &service_manager);
					break;
				default:
					THIS_SHOULD_NEVER_HAPPEN;
			}

			zbx_ipc_message_free(message);
			zbx_vector_uint64_destroy(&eventids);
			zbx_vector_ptr_destroy(&events);
		}

		if (NULL != client)
			zbx_ipc_client_release(client);
	}

	service_manager_free(&service_manager);

	DBclose();

	exit(EXIT_SUCCESS);
#undef STAT_INTERVAL
}

