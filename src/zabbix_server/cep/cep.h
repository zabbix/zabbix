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

#ifndef ZABBIX_CEP_H
#define ZABBIX_CEP_H

#include "zbx_cep.h"
#include "zbx_cep_client.h"
#include "zbxtypes.h"
#include "zbxalgo.h"
#include "zbxdbhigh.h"

zbx_hash_t	cep_origin_hash(const zbx_cep_origin_t *origin);
int	cep_origin_compare(const zbx_cep_origin_t *o1, const zbx_cep_origin_t *o2);

/* complex event processing */
typedef struct zbx_cep zbx_cep_t;

zbx_cep_event_handle_t	cep_add_event(zbx_cep_t *cep, zbx_cep_event_t *event);

zbx_uint32_t	cep_event_handle_unref(zbx_cep_event_handle_t h);
zbx_cep_event_t	*cep_event_handle_remove(zbx_cep_t *cep, zbx_cep_event_handle_t h);
int	cep_event_handle_compare(const void *a1, const void *a2);
void	cep_event_handle_set(zbx_cep_event_handle_t h, zbx_cep_event_t *event);

zbx_cep_t	*cep_create(void);
void	cep_init(zbx_cep_t *cep, zbx_dbconn_pool_t *dbpool, zbx_cep_init_stats_t *stats);
void	cep_destroy(void *a);

void	cep_sync_object_state(zbx_cep_t *cep, zbx_dbconn_t *db);

void	cep_assess_trigger_events(zbx_cep_t *cep, const zbx_vector_cep_assessment_query_t *queries,
		unsigned char *results);
zbx_cep_assessment_t	cep_check_trigger_deps(zbx_cep_t *cep, const zbx_vector_uint64_t *triggerids);

zbx_uint64_t	cep_open_trigger_event(zbx_cep_t *cep, zbx_uint64_t triggerid, unsigned char trigger_type,
		const zbx_vector_uint64_t *dep_triggerids, int *obj_value);

void	cep_resolve_trigger_events(zbx_cep_t *cep, zbx_cep_event_t *r_event, zbx_vector_cep_event_handle_t *events);
zbx_uint64_t	cep_close_trigger_events(zbx_cep_t *cep, zbx_uint64_t triggerid,
		const zbx_vector_uint64_t *dep_triggerids, unsigned char correlation_mode, const char *correlation_tag,
		const zbx_vector_tags_ptr_t *tags, zbx_vector_cep_event_handle_t *events, int *obj_value);
zbx_uint64_t	cep_close_trigger_event_by_eventid(zbx_cep_t *cep, zbx_uint64_t triggerid, zbx_uint64_t eventid,
		zbx_vector_cep_event_handle_t *handles, int *obj_value);

zbx_uint64_t	cep_open_internal_event(zbx_cep_t *cep, unsigned char object, zbx_uint64_t objectid);
zbx_uint64_t	cep_close_internal_event(zbx_cep_t *cep, unsigned char object, zbx_uint64_t objectid,
		zbx_vector_uint64_t *eventids);

zbx_cep_event_handle_t	cep_acquire_event_handle_by_eventid(zbx_cep_t *cep, zbx_uint64_t eventid);

int	cep_origin_problem(const zbx_cep_origin_t *origin);
void	cep_origin_pending_event_done(zbx_cep_t *cep, zbx_cep_origin_t *origin);

void	cep_dump(zbx_cep_t *cep, const char *msg);
void	cep_dump_handle(zbx_cep_event_handle_t h);

void	cep_event_add_suppress(zbx_cep_event_t *event, const zbx_db_event_suppress_t *suppress,
		int suppress_num);
void	cep_update_event_maintenances(zbx_cep_t *cep, const zbx_vector_event_maintenance_t *events,
		zbx_cep_event_op_t action, zbx_vector_cep_event_handle_t *handles);
void	cep_update_event_severities(zbx_cep_t *cep, const zbx_vector_event_severity_t *events,
		zbx_vector_cep_event_handle_t *handles);
void	cep_add_event_tags(zbx_cep_t *cep, zbx_vector_event_tags_t *events, zbx_vector_cep_event_handle_t *handles);

void	cep_get_events_by_handles(zbx_cep_t *cep, zbx_cep_event_handle_t *handles, int handles_num,
		zbx_cep_event_t **events);
void	cep_get_events_by_updates(zbx_cep_t *cep, zbx_cep_event_update_t *updates, int updates_num,
		zbx_cep_event_t **events);
void	cep_get_events(zbx_cep_t *cep, unsigned char source, zbx_vector_cep_event_handle_t *handles);
void	cep_delete_events(zbx_cep_t *cep, const zbx_vector_uint64_t *eventids, zbx_vector_cep_event_handle_t *handles);
void	cep_set_event_cause(zbx_cep_t *cep, zbx_uint64_t eventid, zbx_uint64_t cause_eventid);
void	cep_object_inc_pending(zbx_cep_t *cep, const zbx_cep_origin_t *origin);

/* rule error caching */
int	cep_rule_check_error(zbx_cep_t *cep, zbx_uint64_t ruleid, const char *error);
void	cep_rule_set_error(zbx_cep_t *cep, zbx_uint64_t ruleid, char *error);
time_t	cep_rule_get_window_start_time(zbx_cep_t *cep, zbx_uint64_t ruleid, int duration);

/* statistics */
void	cep_update_events_accessed(zbx_cep_t *cep, zbx_uint64_t value);
void	cep_update_events_processed(zbx_cep_t *cep, zbx_uint64_t value);
void	cep_update_events_discarded(zbx_cep_t *cep, zbx_uint64_t value);
void	cep_get_stats(zbx_cep_t *cep, zbx_cep_stats_t *stats);


#endif /* ZABBIX_CEP_H */
