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

#ifndef ZABBIX_CEP_EVENT_H
#define ZABBIX_CEP_EVENT_H

#include "zbx_cep.h"

zbx_cep_event_t	*cep_event_create(zbx_uint64_t eventid, unsigned char source, unsigned char object,
		zbx_uint64_t objectid, const char *name, int clock, int ns, int value, int severity,
		const zbx_vector_tags_ptr_t *tags, const zbx_vector_db_event_suppress_t *suppress);

zbx_cep_event_t	*cep_event_clone(const zbx_cep_event_t *event);
void	cep_event_clear(zbx_cep_event_t *event);
zbx_cep_event_t	*cep_event_addref(zbx_cep_event_t *event);
zbx_cep_event_t	*cep_event_get_mutable(zbx_cep_event_t *event);
int	cep_event_find_tag(zbx_cep_event_t *event, const char *tag);

typedef struct
{
	zbx_db_event		*db_event;
	zbx_cep_event_t		*event;
	zbx_cep_event_handle_t	hevent;

	zbx_vector_str_t	hosts;
	zbx_vector_str_t	groups;

	zbx_uint32_t		sync_flags;
}
zbx_cep_event_context_t;

void	cep_event_context_clear(zbx_cep_event_context_t *ctx);
zbx_cep_event_t *cep_event_context_acquire_event(zbx_cep_event_context_t *ctx);
zbx_cep_event_t *cep_event_context_acquire_mutable_event(zbx_cep_event_context_t *ctx);
zbx_uint64_t	cep_event_context_eventid(zbx_cep_event_context_t *ctx);
void	cep_event_context_load_hosts(zbx_cep_event_context_t *ctx);
void	cep_event_context_load_groups(zbx_cep_event_context_t *ctx);
void	cep_event_context_set_handle(zbx_cep_event_context_t *ctx, zbx_cep_event_handle_t hevent);

#endif
