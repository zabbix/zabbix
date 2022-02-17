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

#ifndef ZABBIX_CYBERARK_H
#define ZABBIX_CYBERARK_H

#include "../zbxkvs/kvs.h"

#define ZBX_CYBERARK_NAME		"CyberArk"
#define ZBX_CYBERARK_DBUSER_KEY		"UserName"
#define ZBX_CYBERARK_DBPASSWORD_KEY	"Content"

int	zbx_cyberark_kvs_get(const char *vault_url, const char *token, const char *ssl_cert_file,
		const char *ssl_key_file, const char *path, long timeout, zbx_kvs_t *kvs, char **error);
#endif
