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

#ifndef ZABBIX_EVALFUNC_H
#define ZABBIX_EVALFUNC_H

#include "zbxeval.h"

#include "zbxtypes.h"
#include "zbxcacheconfig.h"
#include "zbxhistory.h"
#include "zbxalgo.h"
#include "zbxtime.h"

#define ZBX_VALUEMAP_STRING_LEN	64

typedef enum
{
	ZBX_VALUE_NONE,
	ZBX_VALUE_SECONDS,
	ZBX_VALUE_NVALUES
}
zbx_value_type_t;

typedef struct
{
	char	value[ZBX_VALUEMAP_STRING_LEN];
	char	newvalue[ZBX_VALUEMAP_STRING_LEN];
	int	type;
}
zbx_valuemaps_t;

ZBX_PTR_VECTOR_DECL(valuemaps_ptr, zbx_valuemaps_t *)

void	zbx_valuemaps_free(zbx_valuemaps_t *valuemap);

int	zbx_evaluate_RATE(zbx_variant_t *value, zbx_dc_item_t *item, const char *parameters, const zbx_timespec_t *ts,
		char **error);

int	evaluate_value_by_map(char *value, size_t max_len, zbx_vector_valuemaps_ptr_t *valuemaps,
		unsigned char value_type);

int	zbx_is_trigger_function(const char *name, size_t len);

int	zbx_execute_count_with_pattern(char *pattern, unsigned char value_type, zbx_eval_count_pattern_data_t *pdata,
		zbx_vector_history_record_t *records, int limit, int *count, char **error);
#endif
