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

#ifndef ZABBIX_HASHICORP_H
#define ZABBIX_HASHICORP_H

#include "common.h"
#include "zbxalgo.h"

int	zbx_hashicorp_kvs_get(const char *path, zbx_hashset_t *kvs, char *url, char *token, long timeout, char **error);
int	zbx_hashicorp_init_db_credentials(char *vault_url, char *token, long timeout, const char *db_path,
		char **dbuser, char **dbpassword, char **error);

#endif
