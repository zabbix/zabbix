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

#ifndef ZABBIX_HISTORY_H
#define ZABBIX_HISTORY_H

#include "zbxjson.h"
#include "zbxhistory.h"

#define ZBX_HISTORY_IFACE_SQL		0
#define ZBX_HISTORY_IFACE_ELASTIC	1

typedef struct zbx_history_iface zbx_history_iface_t;

typedef void (*zbx_history_destroy_func_t)(struct zbx_history_iface *hist);
typedef int (*zbx_history_add_values_func_t)(struct zbx_history_iface *hist, const zbx_vector_dc_history_ptr_t *history,
		int config_history_storage_pipelines);
typedef int (*zbx_history_get_values_func_t)(struct zbx_history_iface *hist, zbx_uint64_t itemid, int start,
		int count, int end, zbx_vector_history_record_t *values);
typedef int (*zbx_history_flush_func_t)(struct zbx_history_iface *hist);

typedef void (*zbx_history_func_t)(const zbx_vector_dc_history_ptr_t *);

struct zbx_history_iface
{
	unsigned char			value_type;
	unsigned char			requires_trends;
	union
	{
		void				*elastic_data;
		zbx_history_func_t		sql_history_func;
	} data;
	zbx_history_destroy_func_t	destroy;
	zbx_history_add_values_func_t	add_values;
	zbx_history_get_values_func_t	get_values;
	zbx_history_flush_func_t	flush;
};

/* SQL hist */
void	zbx_history_sql_init(zbx_history_iface_t *hist, unsigned char value_type);

/* elastic hist */
int	zbx_history_elastic_init(zbx_history_iface_t *hist, unsigned char value_type,
		const char *config_history_storage_url, char **error);
void	zbx_elastic_version_extract(struct zbx_json *json, int *result, int config_allow_unsupported_db_versions,
		const char *config_history_storage_url);
zbx_uint32_t	zbx_elastic_version_get(void);

#endif
