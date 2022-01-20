/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

zbx_hash_t	zbx_kv_hash(const void *data);
int		zbx_kv_compare(const void *d1, const void *d2);
void		zbx_kv_clean(void *data);

int	zbx_kvs_json_parse_by_path(const char *path, const struct zbx_json_parse *jp_kvs_paths, zbx_hashset_t *kvs,
		char **error);
void	zbx_kvs_json_parse(const struct zbx_json_parse *jp_kvs, zbx_hashset_t *kvs);

#endif
