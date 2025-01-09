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

#ifndef ZABBIX_PP_ERROR_H
#define ZABBIX_PP_ERROR_H

#include "zbxpreproc.h"
#include "zbxcacheconfig.h"
#include "zbxpreprocbase.h"

void	pp_result_set(zbx_pp_result_t *result, const zbx_variant_t *value, int action, zbx_variant_t *value_raw);
void	pp_free_results(zbx_pp_result_t *results, int results_num);

void	pp_format_error(const zbx_variant_t *value, zbx_pp_result_t *results, int results_num, char **error);
int	pp_error_on_fail(zbx_dc_um_shared_handle_t *um_handle, zbx_uint64_t hostid, zbx_variant_t *value,
		const zbx_pp_step_t *step);

#endif
