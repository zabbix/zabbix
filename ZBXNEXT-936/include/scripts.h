/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

#ifndef ZABBIX_SCRIPTS_H
#define ZABBIX_SCRIPTS_H

#include "common.h"
#include "dbcache.h"

void	zbx_script_init(zbx_script_t *script);
void	zbx_script_clean(zbx_script_t *script);
int	zbx_operation_to_script(const zbx_uint64_t operationid, DC_HOST *host, const zbx_uint64_t actionid,
		const DB_EVENT* event, zbx_script_t *script);
int	zbx_execute_script(DC_HOST *host, zbx_script_t *script, char **result, char *error, size_t max_error_len);
void	zbx_compose_script_response(const int return_code, const char* result, struct zbx_json* response);

#endif
