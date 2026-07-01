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

#ifndef ZABBIX_CEP_JS_H
#define ZABBIX_CEP_JS_H

#include "zbx_cep.h"
#include "zbxembed.h"

typedef struct
{
	zbx_cep_event_t	**events;
	int		events_num;
	zbx_hashset_t	index;
}
zbx_cep_js_ctx_t;

void	cep_js_init(zbx_es_t *es);
void	cep_js_ctx_init(zbx_cep_js_ctx_t *js, zbx_vector_cep_event_handle_t *hevents);
void	cep_js_ctx_clear(zbx_cep_js_ctx_t *js);
void	cep_js_set_ctx(zbx_es_t *es, zbx_cep_js_ctx_t *js);

#endif
