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

#ifndef ZABBIX_CEP_TASK_H
#define ZABBIX_CEP_TASK_H

#include "cep_window.h"
#include "cep_rule_operation.h"
#include "cep_event.h"
#include "zbx_cep.h"
#include "zbxipcservice.h"
#include "zbxalgo.h"
#include "zbxdbhigh.h"
#include "zbxmw.h"

typedef enum
{
	CEP_ACTION_ENABLED,
	CEP_ACTION_DISABLED
}
zbx_cep_action_state_t;

typedef enum
{
	CEP_TASK_REMOTE,
	CEP_TASK_EVENT,
	CEP_TASK_COMMIT,
	CEP_TASK_ADD_TAGS,
	CEP_TASK_SYNC_EVENT,
	CEP_TASK_WINDOW,
	CEP_TASK_ACKNOWLEDGE,
	CEP_TASK_RULE_ERROR,
	CEP_TASK_RULE_RESET
}
zbx_cep_task_type_t;

typedef struct
{
	zbx_mw_task_t			base;
	zbx_vector_mw_task_ptr_t	blocked;	/* tasks blocked by this task */
	int				blockers;	/* number of tasks blocking this task */
}
zbx_cep_task_t;

typedef struct
{
	zbx_cep_task_t		base;
	zbx_ipc_message_t	*message;
	zbx_ipc_client_t	*client;
	unsigned char		*response;
	zbx_uint32_t		response_len;
}
zbx_cep_task_remote_t;

typedef struct
{
	zbx_cep_task_t			base;
	zbx_db_event			*db_event;	/* in - event data */
	unsigned char			flags;		/* in - event flags */
	zbx_uint64_t			target_eventid;	/* in - specific event to act on (0 if none) */
	zbx_cep_event_actor_t		creator;	/* in - additional event creation information */
	zbx_cep_event_op_t		event_op;	/* out - created event state - open/close */
	zbx_cep_action_state_t		action_state;	/* out - specifies if actions must be processed */
	zbx_vector_uint64_t		eventids;	/* out - recovered problems for recovery event */
	int				obj_value;	/* out - new object value, set only if it has changed */
	zbx_vector_cep_event_update_t	updates;	/* out - updated events + actions:     */
							/*       open event - created event     */
							/*       close event - closed events    */
	zbx_cep_event_t			*event;		/* out - created event */
	zbx_cep_event_handle_t		hevent;		/* out - created event handle */
}
zbx_cep_task_event_t;

typedef struct
{
	zbx_cep_task_t			base;
	zbx_vector_event_tags_t		cached_tags;	/* tags validated in cachce */
	zbx_vector_event_tags_t		db_tags;	/* tags to be validated in db */
	zbx_vector_cep_event_update_t	updates;	/* out - updated events + actions  */
}
zbx_cep_task_add_tags_t;

typedef struct
{
	zbx_cep_task_t			base;
	zbx_vector_mw_task_ptr_t	tasks;
}
zbx_cep_task_commit_t;

#define CEP_SYNC_EVENT_NAME		0x01
#define CEP_SYNC_EVENT_SEVERITY		0x02
#define CEP_SYNC_EVENT_TAGS		0x04
#define CEP_SYNC_EVENT_CAUSE		0x08
#define CEP_SYNC_EVENT_SUPPRESS		0x10

typedef struct
{
	zbx_mw_task_t		base;
	zbx_cep_event_handle_t	hevent;
	zbx_uint64_t		flags;
}
zbx_cep_task_sync_event_t;

typedef struct
{
	zbx_mw_task_t		base;
	zbx_cep_window_t	*window;
	time_t			now;
}
zbx_cep_task_window_t;

typedef struct
{
	zbx_mw_task_t		base;
	zbx_uint64_t		ruleid;
	zbx_uint64_t		eventid;
	struct zbx_json		details;
}
zbx_cep_task_acknowledge_t;

typedef struct
{
	zbx_mw_task_t	base;
	zbx_uint64_t	ruleid;
	char		*error;
}
zbx_cep_task_rule_error_t;

typedef struct
{
	zbx_mw_task_t	base;
	zbx_uint64_t	ruleid;
}
zbx_cep_task_rule_reset_t;

typedef struct
{
	zbx_mw_task_t	base;
}
zbx_cep_task_prune_events_t;

zbx_mw_task_t	*cep_create_task_remote(zbx_ipc_client_t *client, zbx_ipc_message_t *message, unsigned char *response,
		zbx_uint32_t response_len);
zbx_mw_task_t	*cep_create_task_event(zbx_db_event *event);
zbx_mw_task_t	*cep_create_task_event_by_copy(zbx_db_event *event);
zbx_mw_task_t	*cep_create_task_event_by_user(zbx_db_event *event, zbx_uint64_t eventid, zbx_uint64_t userid);
zbx_mw_task_t	*cep_create_task_event_by_correlation(zbx_db_event *event, zbx_uint64_t eventid,
		zbx_uint64_t correlationid, zbx_uint64_t c_eventid);
zbx_mw_task_t	*cep_create_task_event_by_cep_rule(zbx_db_event *event, zbx_uint64_t eventid, zbx_uint64_t cep_ruleid);
zbx_mw_task_t	*cep_create_task_commit(zbx_vector_mw_task_ptr_t *tasks);
zbx_mw_task_t	*cep_create_task_add_tags(zbx_vector_event_tags_t *event_tags, zbx_vector_uint64_t *eventids);
zbx_mw_task_t	*cep_create_task_sync_event(zbx_cep_event_handle_t hevent, zbx_uint32_t flags);
zbx_mw_task_t	*cep_create_task_window(zbx_cep_window_t *window, time_t now);
zbx_mw_task_t	*cep_create_task_acknowledge(zbx_cep_acknowledge_t *ack, zbx_uint64_t ruleid, zbx_uint64_t eventid);
zbx_mw_task_t	*cep_create_task_rule_error(zbx_uint64_t ruleid, char *error);
zbx_mw_task_t	*cep_create_task_rule_reset(zbx_uint64_t ruleid);

void	cep_task_free(zbx_mw_task_t *mw_task);

#endif

