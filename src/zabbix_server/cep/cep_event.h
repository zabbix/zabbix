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

typedef struct
{
	zbx_uint64_t	userid;
	zbx_uint64_t	correlationid;
	zbx_uint64_t	c_eventid;
	zbx_uint64_t	cep_ruleid;
}
zbx_cep_event_actor_t;

zbx_cep_event_t	*cep_event_create(zbx_uint64_t eventid, unsigned char source, unsigned char object,
		zbx_uint64_t objectid, const char *name, int clock, int ns, int value, int severity,
		unsigned char flags, zbx_uint64_t cause_eventid, const zbx_vector_tags_ptr_t *tags,
		const zbx_vector_db_event_suppress_t *suppress);

zbx_cep_event_t	*cep_event_clone(const zbx_cep_event_t *event);
zbx_cep_event_t	*cep_event_addref(zbx_cep_event_t *event);
zbx_cep_event_t	*cep_event_get_mutable(zbx_cep_event_t *event);

int	cep_event_find_tag(zbx_cep_event_t *event, const char *tag);
int	cep_event_find_any_tag(const zbx_cep_event_t *event, const char *tags);
int	cep_event_validate_tag(zbx_cep_event_t *event, const char *tag, const char *value, int *match_index);

typedef enum
{
	CEP_POS_UNKNOWN,
	CEP_POS_FIRST,
	CEP_POS_LAST
}
zbx_cep_event_pos_t;

typedef struct
{
	const zbx_db_event	*db_event;
	zbx_db_event		*db_event_local;
	zbx_cep_event_t		*event;
	zbx_cep_event_handle_t	hevent;

	zbx_cep_event_pos_t	pos;

	zbx_uint64_t		functionid;	/* first function identifier */
	zbx_uint64_t		hostid;		/* first host identifier */
	zbx_uint64_t		hostgroupid;	/* first host group identifier */

	zbx_vector_str_t	hosts;
	zbx_vector_str_t	groups;

	zbx_uint32_t		sync_flags;
}
zbx_cep_event_context_t;

void	cep_event_context_clear(zbx_cep_event_context_t *ctx);
zbx_cep_event_t *cep_event_context_get_event(zbx_cep_event_context_t *ctx);
zbx_cep_event_t *cep_event_context_get_mutable_event(zbx_cep_event_context_t *ctx);
const zbx_db_event *cep_event_context_get_db_event(zbx_cep_event_context_t *ctx);
zbx_uint64_t	cep_event_context_eventid(zbx_cep_event_context_t *ctx);
void	cep_event_context_resolve_name_macros(zbx_cep_event_context_t *ctx, char **str);
void	cep_event_context_resolve_tag_macros(zbx_cep_event_context_t *ctx, char **str);

const zbx_vector_str_t	*cep_event_context_get_hosts(zbx_cep_event_context_t *ctx);
const zbx_vector_str_t	*cep_event_context_get_groups(zbx_cep_event_context_t *ctx);
zbx_uint64_t	cep_event_context_get_hostid(zbx_cep_event_context_t *ctx);
zbx_uint64_t	cep_event_context_get_hostgroupid(zbx_cep_event_context_t *ctx);
void	cep_event_context_set_handle(zbx_cep_event_context_t *ctx, zbx_cep_event_handle_t hevent);

zbx_db_event	*cep_db_event_create(const zbx_cep_origin_t *origin, const char *name, int clock, int ns,
	int serverity, int value, const zbx_vector_lite_tag_t *tags);
void	cep_event_expect(const zbx_db_event *db_event);

int	db_event_suppress_compare(const void *a1, const void *a2);
void	cep_event_add_suppress(zbx_cep_event_t *event, const zbx_db_event_suppress_t *suppress,
	int suppress_num);
void	cep_event_remove_suppress(zbx_cep_event_t *event, const zbx_db_event_suppress_t *suppress,
	int suppress_num);

#endif
