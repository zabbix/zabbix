/*
** Copyright (C) 2001-2024 Zabbix SIA
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

#ifndef ZABBIX_PP_EXECUTE_H
#define ZABBIX_PP_EXECUTE_H

#include "pp_cache.h"
#include "zbxembed.h"
#include "zbxpreproc.h"
#include "zbxtime.h"
#include "zbxcacheconfig.h"
#include "zbxpreprocbase.h"

typedef struct
{
	int		es_initialized;
	zbx_es_t	es_engine;
}
zbx_pp_context_t;

void		pp_context_init(zbx_pp_context_t *ctx);
void		pp_context_destroy(zbx_pp_context_t *ctx);
zbx_es_t	*pp_context_es_engine(zbx_pp_context_t *ctx);

void	pp_execute(zbx_pp_context_t *ctx, zbx_pp_item_preproc_t *preproc, zbx_pp_cache_t *cache,
		zbx_dc_um_shared_handle_t *um_handle, zbx_variant_t *value_in, zbx_timespec_t ts,
		const char *config_source_ip, zbx_variant_t *value_out, zbx_pp_result_t **results_out,
		int *results_num_out);

int	pp_execute_step(zbx_pp_context_t *ctx, zbx_pp_cache_t *cache, zbx_dc_um_shared_handle_t *um_handle,
		zbx_uint64_t hostid, unsigned char value_type, zbx_variant_t *value, zbx_timespec_t ts,
		zbx_pp_step_t *step, const zbx_variant_t *history_value_last, zbx_variant_t *history_value,
		zbx_timespec_t *history_ts, const char *config_source_ip);

#endif
