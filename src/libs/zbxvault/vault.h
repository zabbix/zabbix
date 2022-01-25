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

#ifndef ZABBIX_ZBXVAULT_H
#define ZABBIX_ZBXVAULT_H

#include "../zbxkvs/kvs.h"

int	zbx_vault_init(char **error);
int	zbx_vault_kvs_get(const char *path, zbx_kvs_t *kvs, char **error);
int	zbx_vault_db_credentials_get(char **dbuser, char **dbpassword, char **error);

int	zbx_vault_token_from_env_get(char **token, char **error);

#endif
