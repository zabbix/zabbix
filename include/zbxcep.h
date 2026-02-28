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

#ifndef ZABBIX_ZBX_CEP_H
#define ZABBIX_ZBX_CEP_H

#include "zbxalgo.h"
#include "zbxtypes_ext.h"

typedef struct
{
	int	workers_num;
	int	config_timeout;
}
zbx_thread_cep_manager_args_t;

void	*zbx_cep_manager_thread(void *args);

typedef struct
{
	unsigned char		source;
	unsigned char		object;
	zbx_uint64_t		objectid;

}
zbx_cep_origin_t;

typedef struct zbx_cep_event zbx_cep_event_t;

struct zbx_cep_event
{
	zbx_uint64_t		eventid;
	int			clock;
	int			ns;
	int			value;
	int			severity;
	time_t			suppress_mtime;

	zbx_cep_event_t		*r_event;

	zbx_cep_origin_t	origin;

	zbx_vector_tag_t	tags;
	zbx_vector_uint64_t	maintenanceids;

	zbx_atomic_uint32_t	refcount;

};

ZBX_VECTOR_DECL(cep_event, zbx_cep_event_t)
ZBX_PTR_VECTOR_DECL(cep_event_ptr, zbx_cep_event_t *)

typedef struct zbx_cep_event_ptr zbx_cep_event_ptr_t;
typedef zbx_cep_event_ptr_t * zbx_cep_event_handle_t;

ZBX_VECTOR_DECL(cep_event_handle, zbx_cep_event_handle_t)

typedef enum
{
	CEP_EVENT_NONE,
	CEP_EVENT_OPEN,
	CEP_EVENT_CLOSE,
	CEP_EVENT_SUPPRESS,
	CEP_EVENT_UNSUPPRESS,
	CEP_EVENT_ADD_TAG,
	CEP_EVENT_UPDATE_SEVERITY,
	CEP_EVENT_DELETE
}
zbx_cep_event_op_t;

typedef struct
{
	zbx_cep_event_handle_t	handle;
	zbx_cep_event_op_t	op;
}
zbx_cep_event_update_t;

ZBX_VECTOR_DECL(cep_event_update, zbx_cep_event_update_t)

int	zbx_cep_recv_event_updates(zbx_cep_event_update_t *updates, int updates_num);

void	zbx_cep_event_release(zbx_cep_event_t *event);
zbx_cep_event_handle_t	zbx_cep_event_handle_addref(zbx_cep_event_handle_t h);
void	zbx_cep_event_handle_release(zbx_cep_event_handle_t h);
void	zbx_cep_get_events_by_handles(zbx_cep_event_handle_t *handles, int handles_num, zbx_cep_event_t **events);
void	zbx_cep_get_events_by_updates(zbx_cep_event_update_t *updates, int updates_num, zbx_cep_event_t **events);
void	zbx_cep_get_events(zbx_vector_cep_event_handle_t *handles);

void	zbx_cep_get_eventids_from_handles(const zbx_cep_event_handle_t *handles, int handles_num,
		zbx_vector_uint64_t *eventids);


#endif
