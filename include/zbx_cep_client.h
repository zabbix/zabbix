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

#ifndef ZABBIX_ZBX_CEP_CLIENT_H
#define ZABBIX_ZBX_CEP_CLIENT_H

#include "zbxalgo.h"
#include "zbxtypes.h"
#include "zbxdbhigh.h"
#include "zbx_rtc_constants.h"
#include "zbxtypes_ext.h"

#define ZBX_IPC_SERVICE_CEP	"cep"

#define ZBX_CEP_ASSESS_TRIGGER_EVENTS	(ZBX_IPC_RTC_MAX + 1)
#define ZBX_CEP_ADD_EVENTS		(ZBX_IPC_RTC_MAX + 2)
#define ZBX_CEP_ADD_USER_CLOSE_EVENT	(ZBX_IPC_RTC_MAX + 3)
#define ZBX_CEP_UPDATE_TRIGGER		(ZBX_IPC_RTC_MAX + 4)
#define ZBX_CEP_SUPPRESS_EVENTS		(ZBX_IPC_RTC_MAX + 5)
#define ZBX_CEP_UNSUPPRESS_EVENTS	(ZBX_IPC_RTC_MAX + 6)
#define ZBX_CEP_UPDATE_SEVERITIES	(ZBX_IPC_RTC_MAX + 7)
#define ZBX_CEP_ADD_EVENT_TAGS		(ZBX_IPC_RTC_MAX + 8)
#define ZBX_CEP_DELETE_EVENTS		(ZBX_IPC_RTC_MAX + 9)
#define ZBX_CEP_CHECK_TRIGGER_DEPS	(ZBX_IPC_RTC_MAX + 10)
#define ZBX_CEP_GET_STATS		(ZBX_IPC_RTC_MAX + 11)
#define ZBX_CEP_GET_DIAGINFO		(ZBX_IPC_RTC_MAX + 12)
#define ZBX_CEP_SYNC_OBJECT_STATE	(ZBX_IPC_RTC_MAX + 13)

typedef enum
{
	CEP_EVENT_ALLOW,
	CEP_EVENT_DEFER,
	CEP_EVENT_DEPENDENCY_DEFER,
	CEP_EVENT_DENY,
	CEP_EVENT_DEPENDENCY_DENY
}
zbx_cep_result_t;

#define CEP_QUERY_MASK_VALUE	0x0f
#define CEP_QUERY_FLAG_MULTI	0x10
#define CEP_QUERY_FLAG_DEPS	0x20

typedef struct
{
	zbx_uint64_t		triggerid;
	unsigned char		flags;
	zbx_vector_uint64_t	dep_triggerids;
}
zbx_cep_assessment_query_t;

ZBX_VECTOR_DECL(cep_assessment_query, zbx_cep_assessment_query_t)

zbx_uint32_t	zbx_cep_serialize_ids(const zbx_vector_uint64_t *ids, unsigned char **data);

void	zbx_cep_assess_trigger_events(const zbx_cep_assessment_query_t *queries, int queries_num,
		unsigned char **results);
int	zbx_cep_peek_event_queries(const unsigned char *data);
void	zbx_cep_deserialize_event_queries(const unsigned char *data, zbx_vector_cep_assessment_query_t *queries);
void	zbx_cep_assessment_query_clear(zbx_cep_assessment_query_t *query);

int	zbx_cep_send_events(zbx_db_event * const *events, int events_num);
void	zbx_cep_deserialize_events(const unsigned char *data, zbx_vector_db_event_t *events);

void	zbx_cep_close_problem_by_user(const zbx_db_event *event, zbx_uint64_t eventid, zbx_uint64_t userid);
void	zbx_cep_deserialize_close_problem(const unsigned char  *data, zbx_db_event **event, zbx_uint64_t *eventid,
	zbx_uint64_t *userid);

typedef struct
{
	zbx_uint64_t	eventid;
	zbx_uint64_t	maintenanceid;
}
zbx_event_maintenance_t;

ZBX_VECTOR_DECL(event_maintenance, zbx_event_maintenance_t)

void	zbx_cep_send_event_maintenance_on(const zbx_event_maintenance_t *events, int events_num);
void	zbx_cep_send_event_maintenance_off(const zbx_event_maintenance_t *events, int events_num);
void	zbx_cep_deserialize_event_maintenance(const unsigned char *data, zbx_vector_event_maintenance_t *events);
typedef struct
{
	zbx_uint64_t	eventid;
	int		severity;
}
zbx_event_severity_t;

ZBX_VECTOR_DECL(event_severity, zbx_event_severity_t)

void	zbx_cep_send_event_severities(const zbx_event_severity_t *events, int events_num);
void	zbx_cep_deserialize_event_severities(const unsigned char *data, zbx_vector_event_severity_t *events);

void	zbx_cep_send_event_tags(zbx_event_tags_t * const *events, int events_num);
void	zbx_deserialize_event_tags(const unsigned char *data, zbx_vector_event_tags_t *events);
void	zbx_buffer_serialize_event_tags(unsigned char **data, zbx_uint32_t *data_alloc, zbx_uint32_t *data_offset,
	const zbx_event_tags_t *event_tags);

void	zbx_cep_send_deleted_events(const zbx_uint64_t *eventids, int eventids_num);
void	zbx_cep_deserialize_ids(const unsigned char *data, zbx_vector_uint64_t *ids);

int	zbx_cep_check_trigger_deps(zbx_uint64_t triggerid);

typedef struct
{
	zbx_uint64_t	events_accessed;
	zbx_uint64_t	events_processed;
	zbx_uint64_t	events_discarded;

	int		task_remote_num;
	int		task_internal_num;
	int		task_completed_num;

	int		events_num;
	int		objects_num;
}
zbx_cep_stats_t;

int	zbx_cep_get_stats(zbx_cep_stats_t *stats, char **error);

typedef struct
{
	int	events_num;
	double	events_time;
	int	tags_num;
	double	tags_time;
	int	suppress_num;
	double	suppress_time;
}
zbx_cep_init_stats_t;

typedef struct
{
	zbx_cep_init_stats_t	startup;
	int			blocked_commit_num;
	int			commits_num;
	int			commit_task_num;
	int			workers_num;
}
zbx_cep_diaginfo_t;

int	zbx_cep_get_diaginfo(zbx_cep_diaginfo_t *stats, char **error);

int	zbx_cep_sync_object_state(char **error);

#endif

