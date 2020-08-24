/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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

#ifndef ZABBIX_ZBXVAULT_H
#define ZABBIX_ZBXVAULT_H

#include "common.h"
#include "zbxalgo.h"

typedef struct
{
	char	*key;
	char	*value;
}
zbx_kv_t;

int	init_token_from_env(char **error);
int	init_database_credentials_from_vault(char **error);
int	zbx_kvs_from_vault_create(const char *path, zbx_hashset_t *kvs, char **error);
int	zbx_kvs_from_json_create(const char *path, const struct zbx_json_parse *jp_kvs_paths, zbx_hashset_t *kvs,
		char **error);
void	zbx_kvs_from_vault_destroy(zbx_hashset_t *kvs);

#endif
