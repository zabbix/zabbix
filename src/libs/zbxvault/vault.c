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

#include "zbxvault.h"

#define ZBX_VAULT_TIMEOUT	SEC_PER_MIN

extern char	*CONFIG_VAULTTOKEN;
extern char	*CONFIG_VAULTURL;
extern char	*CONFIG_VAULTTLSCERTFILE;
extern char	*CONFIG_VAULTTLSKEYFILE;
extern char	*CONFIG_VAULTDBPATH;

extern char	*CONFIG_DBUSER;
extern char	*CONFIG_DBPASSWORD;

zbx_vault_kvs_get_cb_t			zbx_vault_kvs_get_cb;
zbx_vault_init_db_credentials_cb_t	zbx_vault_init_db_credentials_cb;

int	zbx_vault_init_token_from_env(char **error)
{
#if defined(HAVE_GETENV) && defined(HAVE_UNSETENV)
	char	*ptr;

	if (NULL == (ptr = getenv("VAULT_TOKEN")))
		return SUCCEED;

	if (NULL != CONFIG_VAULTTOKEN)
	{
		*error = zbx_dsprintf(*error, "both \"VaultToken\" configuration parameter"
				" and \"VAULT_TOKEN\" environment variable are defined");
		return FAIL;
	}

	CONFIG_VAULTTOKEN = zbx_strdup(NULL, ptr);
	unsetenv("VAULT_TOKEN");
#endif
	return SUCCEED;
}

void	zbx_vault_init_cb(zbx_vault_kvs_get_cb_t vault_kvs_get_cb,
		zbx_vault_init_db_credentials_cb_t vault_init_db_credentials)
{
	zbx_vault_kvs_get_cb = vault_kvs_get_cb;
	zbx_vault_init_db_credentials_cb = vault_init_db_credentials;
}

int	zbx_vault_kvs_get(const char *path, zbx_hashset_t *kvs, char **error)
{
	if (NULL == zbx_vault_kvs_get_cb)
	{
		*error = zbx_dsprintf(*error, "missing vault library");
		return FAIL;
	}

	return zbx_vault_kvs_get_cb(CONFIG_VAULTURL, CONFIG_VAULTTOKEN, CONFIG_VAULTTLSCERTFILE, CONFIG_VAULTTLSKEYFILE,
			path, ZBX_VAULT_TIMEOUT, kvs, error);
}

int	zbx_vault_init_db_credentials(char **error)
{
	if (NULL == zbx_vault_init_db_credentials_cb)
	{
		*error = zbx_dsprintf(*error, "missing vault library");
		return FAIL;
	}

	if (NULL == CONFIG_VAULTDBPATH)
		return SUCCEED;

	if (NULL != CONFIG_DBUSER)
	{
		*error = zbx_dsprintf(*error, "\"DBUser\" configuration parameter cannot be used when \"VaultDBPath\""
				" is defined");
		return FAIL;
	}

	if (NULL != CONFIG_DBPASSWORD)
	{
		*error = zbx_dsprintf(*error, "\"DBPassword\" configuration parameter cannot be used when"
				" \"VaultDBPath\" is defined");
		return FAIL;
	}

	return zbx_vault_init_db_credentials_cb(CONFIG_VAULTURL, CONFIG_VAULTTOKEN,
			CONFIG_VAULTTLSCERTFILE, CONFIG_VAULTTLSKEYFILE, CONFIG_VAULTDBPATH, ZBX_VAULT_TIMEOUT,
			&CONFIG_DBUSER, &CONFIG_DBPASSWORD, error);
}
