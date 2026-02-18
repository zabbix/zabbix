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

#ifndef ZABBIX_HASHICORP_H
#define ZABBIX_HASHICORP_H

#include "zbxvault.h"
#include "zbxalgo.h"

int	zbx_vault_get_kvs_hashicorp(const zbx_config_vault_t *config_vault,
		const char *ssl_cert_file, const char *ssl_key_file, const char *config_source_ip,
		const char *config_ssl_ca_location, const char *config_ssl_cert_location,
		const char *config_ssl_key_location, const char *path, long timeout, zbx_hashset_t *kvs,
		char **relog_token, char **error);

void	zbx_vault_renew_token_hashicorp(const char *vault_url, const char *token, const char *ssl_cert_file,
		const char *ssl_key_file, const char *config_source_ip, const char *config_ssl_ca_location,
		const char *config_ssl_cert_location, const char *config_ssl_key_location, long timeout);

int	zbx_vault_relogin_hashicorp(const zbx_config_vault_t *config_vault,
		const char *config_source_ip, const char *config_ssl_ca_location,
		const char *config_ssl_cert_location, const char *config_ssl_key_location, char **token, char **error);

void	zbx_vault_update_token_hashicorp(char *token);

#endif
