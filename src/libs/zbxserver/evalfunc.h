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

#ifndef ZABBIX_EVALFUNC_H
#define ZABBIX_EVALFUNC_H

#include "zbxtypes.h"
#include "zbxcacheconfig.h"
#include "zbxhistory.h"
#include "zbxserver.h"

int	zbx_evaluatable_for_notsupported(const char *fn);
int	zbx_evaluate_RATE(zbx_variant_t *value, zbx_dc_item_t *item, const char *parameters, const zbx_timespec_t *ts,
		char **error);

int	evaluate_function(zbx_variant_t *value, const zbx_dc_evaluate_item_t *item, const char *function,
		const char *parameter, const zbx_timespec_t *ts, char **error);

int	zbx_is_trigger_function(const char *name, size_t len);

int	zbx_execute_count_with_pattern(char *pattern, unsigned char value_type, zbx_eval_count_pattern_data_t *pdata,
		zbx_vector_history_record_t *records, int limit, int *count, char **error);
#endif
