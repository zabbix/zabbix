/*
** Copyright (C) 2001-2025 Zabbix SIA
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

#ifndef ZABBIX_PP_TASK_H
#define ZABBIX_PP_TASK_H

#include "zbxpreproc.h"
#include "pp_cache.h"

#include "zbxalgo.h"
#include "zbxvariant.h"
#include "zbxtime.h"
#include "zbxipcservice.h"
#include "zbxcacheconfig.h"
#include "zbxcachehistory.h"
#include "zbxpreprocbase.h"

#define PP_TASK_DATA(x)		(&x->data)

typedef struct
{
	zbx_variant_t		value;
	zbx_timespec_t		ts;

	zbx_variant_t		result;
	zbx_pp_result_t		*results;
	int			results_num;

	zbx_pp_item_preproc_t	*preproc; /* created from the data provided in request */

	zbx_ipc_client_t	*client;
}
zbx_pp_task_test_t;

typedef struct
{
	zbx_variant_t			value;
	zbx_variant_t			result;
	zbx_timespec_t			ts;
	zbx_pp_value_opt_t		opt;

	zbx_pp_item_preproc_t		*preproc;
	zbx_pp_cache_t			*cache;
	zbx_dc_um_shared_handle_t	*um_handle;
}
zbx_pp_task_value_t;

typedef struct
{
	zbx_pp_item_preproc_t	*preproc;
	zbx_pp_task_t		*primary;
	zbx_pp_cache_t		*cache;
}
zbx_pp_task_dependent_t;

typedef struct
{
	zbx_list_t	tasks;
}
zbx_pp_task_sequence_t;

void	pp_task_free(zbx_pp_task_t *task);

zbx_pp_task_t	*pp_task_test_create(zbx_pp_item_preproc_t *preproc, zbx_variant_t *value, zbx_timespec_t ts,
		zbx_ipc_client_t *client);
zbx_pp_task_t	*pp_task_value_create(zbx_uint64_t itemid, zbx_pp_item_preproc_t *preproc,
		zbx_dc_um_shared_handle_t *um_handle, zbx_variant_t *value, zbx_timespec_t ts,
		const zbx_pp_value_opt_t *value_opt, zbx_pp_cache_t *cache);
zbx_pp_task_t	*pp_task_dependent_create(zbx_uint64_t itemid, zbx_pp_item_preproc_t *preproc);
zbx_pp_task_t	*pp_task_value_seq_create(zbx_uint64_t itemid, zbx_pp_item_preproc_t *preproc,
		zbx_dc_um_shared_handle_t *um_handle, zbx_variant_t *value, zbx_timespec_t ts,
		const zbx_pp_value_opt_t *value_opt, zbx_pp_cache_t *cache);
zbx_pp_task_t	*pp_task_sequence_create(zbx_uint64_t itemid);

#endif
