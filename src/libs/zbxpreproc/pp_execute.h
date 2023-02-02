/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

#ifndef ZABBIX_PP_EXECUTE_H
#define ZABBIX_PP_EXECUTE_H

#include "pp_cache.h"
#include "zbxembed.h"

typedef struct
{
	int		es_initialized;
	zbx_es_t	es_engine;
}
zbx_pp_context_t;

void	pp_context_init(zbx_pp_context_t *ctx);
void	pp_context_destroy(zbx_pp_context_t *ctx);
zbx_es_t	*pp_context_es_engine(zbx_pp_context_t *ctx);

void	pp_execute(zbx_pp_context_t *ctx, zbx_pp_item_preproc_t *preproc, zbx_pp_cache_t *cache,
		zbx_variant_t *value_in, zbx_timespec_t ts, zbx_variant_t *value_out, zbx_pp_result_t **results_out,
		int *results_num_out);

int	pp_execute_step(zbx_pp_context_t *ctx, zbx_pp_cache_t *cache, unsigned char value_type,
		zbx_variant_t *value, zbx_timespec_t ts, zbx_pp_step_t *step, zbx_variant_t *history_value,
		zbx_timespec_t *history_ts);
int	pp_error_on_fail(zbx_variant_t *value, const zbx_pp_step_t *step);

#endif
