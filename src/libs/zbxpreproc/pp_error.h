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

#ifndef ZABBIX_PP_ERROR_H
#define ZABBIX_PP_ERROR_H

#include "zbxpreproc.h"
#include "zbxvariant.h"

void	pp_result_set(zbx_pp_result_t *result, const zbx_variant_t *value, int action, zbx_variant_t *value_raw);
void	pp_free_results(zbx_pp_result_t *results, int results_num);

void	pp_format_error(const zbx_variant_t *value, zbx_pp_result_t *results, int results_num, char **error);
int	pp_error_on_fail(zbx_variant_t *value, const zbx_pp_step_t *step);

#endif
