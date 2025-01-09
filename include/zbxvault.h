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

#ifndef ZABBIX_ZBXVAULT_H
#define ZABBIX_ZBXVAULT_H

#include "zbxkvs.h"

typedef struct
{
	char	*name;
	char	*url;
	char	*token;
	char	*tls_cert_file;
	char	*tls_key_file;
	char	*db_path;
	char	*prefix;
}
zbx_config_vault_t;

int	zbx_vault_init(const zbx_config_vault_t *config_vault, char **error);
int	zbx_vault_kvs_get(const char *path, zbx_kvs_t *kvs, const zbx_config_vault_t *config_vault,
		const char *config_source_ip, const char *config_ssl_ca_location, const char *config_ssl_cert_location,
		const char *config_ssl_key_location, char **error);
int	zbx_vault_db_credentials_get(const zbx_config_vault_t *config_vault, char **dbuser, char **dbpassword,
		const char *config_source_ip, const char *config_ssl_ca_location, const char *config_ssl_cert_location,
		const char *config_ssl_key_location, char **error);
void	zbx_vault_renew_token(const zbx_config_vault_t *config_vault,
		const char *config_source_ip, const char *config_ssl_ca_location,
		const char *config_ssl_cert_location, const char *config_ssl_key_location);

int	zbx_vault_token_from_env_get(char **token, char **error);

#endif
