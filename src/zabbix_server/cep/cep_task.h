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

#include "zbxcep.h"
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
	CEP_TASK_CLOSE_EVENT,
	CEP_TASK_COMMIT,
	CEP_TASK_ADD_TAGS
}
zbx_cep_task_type_t;

typedef struct
{
	zbx_mw_task_t		base;
	zbx_ipc_message_t	*message;
	zbx_ipc_client_t	*client;
	unsigned char		*response;
	zbx_uint32_t		response_len;
}
zbx_cep_task_remote_t;

typedef struct
{
	zbx_mw_task_t			base;
	zbx_db_event			*db_event;	/* in - event data, out - eventid */
	zbx_uint64_t			userid;		/* in - userid for manually closed event */
	zbx_cep_event_op_t		event_op;	/* out - created event state - open/close */
	zbx_cep_action_state_t		action_state;	/* out - specifies if actions must be processed */
	zbx_vector_uint64_t		eventids;	/* out - recovered problems for recovery event */
	int				obj_value;	/* out - new object value, set only if it has changed */
	zbx_vector_cep_event_update_t	updates;	/* out - updated events + actions:     */
							/*       open event - created event     */
							/*       close event - closed events    */
}
zbx_cep_task_event_t;

typedef struct
{
	zbx_cep_task_event_t	parent;
	zbx_uint64_t		eventid;	/* in - eventid to close */
	zbx_uint64_t		userid;		/* in - userid when closed manually by a user */
	zbx_uint64_t		correlationid;	/* in - correllationid when closed by correlation rules*/
}
zbx_cep_task_close_event_t;

typedef struct
{
	zbx_mw_task_t			base;
	zbx_vector_event_tags_t		cached_tags;	/* tags validated in cachce */
	zbx_vector_event_tags_t		db_tags;	/* tags to be validated in db */
	zbx_vector_cep_event_update_t	updates;	/* out - updated events + actions  */
}
zbx_cep_task_add_tags_t;

typedef struct
{
	zbx_mw_task_t			base;
	zbx_vector_mw_task_ptr_t	tasks;
}
zbx_cep_task_commit_t;

typedef struct
{
	zbx_mw_task_t	base;
}
zbx_cep_task_prune_events_t;

zbx_mw_task_t	*cep_create_task_remote(zbx_ipc_client_t *client, zbx_ipc_message_t *message, unsigned char *response,
		zbx_uint32_t response_len);
zbx_mw_task_t	*cep_create_task_event(zbx_db_event *event);
zbx_mw_task_t	*cep_create_task_commit(zbx_vector_mw_task_ptr_t *tasks);
zbx_mw_task_t	*cep_create_task_close_event(zbx_db_event *event, zbx_uint64_t eventid, zbx_uint64_t userid,
		zbx_uint64_t correlationid);
zbx_mw_task_t	*cep_create_task_add_tags(zbx_vector_event_tags_t *event_tags, zbx_vector_uint64_t *eventids);

void	cep_task_free(zbx_mw_task_t *mw_task);

const zbx_cep_task_event_t	*cep_get_event_task(const zbx_mw_task_t *task);

#endif

