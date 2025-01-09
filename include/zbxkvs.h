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

#ifndef ZABBIX_KVS_H
#define ZABBIX_KVS_H

#include "zbxalgo.h"
#include "zbxjson.h"

typedef struct
{
	char	*key;
	char	*value;
}
zbx_kv_t;

typedef zbx_hashset_t zbx_kvs_t;

void		zbx_kvs_create(zbx_kvs_t *kvs, size_t init_size);
void		zbx_kvs_clear(zbx_kvs_t *kvs);
void		zbx_kvs_destroy(zbx_kvs_t *kvs);
zbx_kv_t	*zbx_kvs_search(zbx_kvs_t *kvs, const zbx_kv_t *data);

int	zbx_kvs_from_json_by_path_get(const char *path, const struct zbx_json_parse *jp_kvs_paths, zbx_kvs_t *kvs,
		char **error);

void	zbx_kvs_from_json_get(const struct zbx_json_parse *jp_kvs, zbx_kvs_t *kvs);

#endif
