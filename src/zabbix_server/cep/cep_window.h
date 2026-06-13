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

#ifndef ZABBIX_CEP_WINDOW_H
#define ZABBIX_CEP_WINDOW_H

#include "cep_rule.h"
#include "zbxcacheconfig.h"
#include "zbxcep.h"
#include "zbxtypes_ext.h"

typedef struct
{
	zbx_uint64_t		ruleid;

	int			type;
	int			duration;
	time_t			time_created;
	zbx_queue_ptr_t		hevents;

	zbx_atomic_uint32_t	refcount;
	pthread_mutex_t		lock;
}
zbx_cep_window_t;


typedef struct
{
	zbx_uint64_t		ruleid;
	int			key_type;
	char			*key_value;
	char			*key_tag;

	zbx_cep_window_t	*window;
}
zbx_cep_window_ref_t;

void	cep_window_index_init(zbx_hashset_t *windows);

zbx_cep_window_t	*cep_window_addref(zbx_cep_window_t *window);
void	cep_window_release(zbx_cep_window_t *window);

zbx_cep_window_t	*cep_get_window_or_create(zbx_hashset_t *windows, zbx_cep_rule_t *rule,
		zbx_cep_event_context_t *ctx);

void	cep_event_add_to_window(zbx_cep_event_handle_t hevent, zbx_cep_event_context_t *ctx, zbx_cep_rule_t *rule);
#endif

