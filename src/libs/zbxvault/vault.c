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

#include "vault.h"
#include "../zbxhashicorp/hashicorp.h"
#include "../zbxcyberark/cyberark.h"
#include "../zbxkvs/kvs.h"

#define ZBX_VAULT_TIMEOUT	SEC_PER_MIN

typedef	int (*zbx_vault_kvs_get_cb_t)(const char *vault_url, const char *token, const char *ssl_cert_file,
		const char *ssl_key_file, const char *path, long timeout, zbx_kvs_t *kvs, char **error);

extern char	*CONFIG_VAULT;
extern char	*CONFIG_VAULTTOKEN;
extern char	*CONFIG_VAULTURL;
extern char	*CONFIG_VAULTTLSCERTFILE;
extern char	*CONFIG_VAULTTLSKEYFILE;
extern char	*CONFIG_VAULTDBPATH;

static zbx_vault_kvs_get_cb_t	zbx_vault_kvs_get_cb;
static const char		*zbx_vault_dbuser_key, *zbx_vault_dbpassword_key;

int	zbx_vault_init(char **error)
{
	if (NULL == CONFIG_VAULT || '\0' == *CONFIG_VAULT || 0 == strcmp(CONFIG_VAULT, ZBX_HASHICORP_NAME))
	{
		if (NULL == CONFIG_VAULTTOKEN && 0 == zbx_strcmp_null(CONFIG_VAULT, ZBX_HASHICORP_NAME))
		{
			*error = zbx_dsprintf(*error, "\"Vault\" value \"%s\" requires \"VaultToken\" configuration"
					" parameter or \"VAULT_TOKEN\" environment variable", CONFIG_VAULT);
			return FAIL;
		}

		zbx_vault_kvs_get_cb = zbx_hashicorp_kvs_get;
		zbx_vault_dbuser_key = ZBX_HASHICORP_DBUSER_KEY;
		zbx_vault_dbpassword_key = ZBX_HASHICORP_DBPASSWORD_KEY;
	}
	else if (0 == strcmp(CONFIG_VAULT, ZBX_CYBERARK_NAME))
	{
		if (NULL != CONFIG_VAULTTOKEN)
		{
			*error = zbx_dsprintf(*error, "\"Vault\" value \"%s\" cannot be used when \"VaultToken\""
					" configuration parameter or \"VAULT_TOKEN\" environment variable is defined",
					CONFIG_VAULT);
			return FAIL;
		}

		zbx_vault_kvs_get_cb = zbx_cyberark_kvs_get;
		zbx_vault_dbuser_key = ZBX_CYBERARK_DBUSER_KEY;
		zbx_vault_dbpassword_key = ZBX_CYBERARK_DBPASSWORD_KEY;
	}
	else
	{
		*error = zbx_dsprintf(*error, "invalid \"Vault\" configuration parameter: '%s'", CONFIG_VAULT);
		return FAIL;
	}

	return SUCCEED;
}

int	zbx_vault_kvs_get(const char *path, zbx_kvs_t *kvs, char **error)
{
	return zbx_vault_kvs_get_cb(CONFIG_VAULTURL, CONFIG_VAULTTOKEN, CONFIG_VAULTTLSCERTFILE, CONFIG_VAULTTLSKEYFILE,
			path, ZBX_VAULT_TIMEOUT, kvs, error);
}

int	zbx_vault_db_credentials_get(char **dbuser, char **dbpassword, char **error)
{
	int		ret = FAIL;
	zbx_kvs_t	kvs;
	const zbx_kv_t	*kv_username, *kv_password;
	zbx_kv_t	kv_local;

	if (NULL == CONFIG_VAULTDBPATH)
		return SUCCEED;

	if (NULL != *dbuser)
	{
		*error = zbx_dsprintf(*error, "\"DBUser\" configuration parameter cannot be used when \"VaultDBPath\""
				" is defined");
		return FAIL;
	}

	if (NULL != *dbpassword)
	{
		*error = zbx_dsprintf(*error, "\"DBPassword\" configuration parameter cannot be used when"
				" \"VaultDBPath\" is defined");
		return FAIL;
	}

	zbx_kvs_create(&kvs, 2);

	if (SUCCEED != zbx_vault_kvs_get_cb(CONFIG_VAULTURL, CONFIG_VAULTTOKEN, CONFIG_VAULTTLSCERTFILE,
			CONFIG_VAULTTLSKEYFILE, CONFIG_VAULTDBPATH, ZBX_VAULT_TIMEOUT, &kvs,
			error))
	{
		goto fail;
	}

	kv_local.key = (char *)zbx_vault_dbuser_key;
	if (NULL == (kv_username = zbx_kvs_search(&kvs, &kv_local)))
	{
		*error = zbx_dsprintf(*error, "cannot retrieve value of key \"%s\"", ZBX_PROTO_TAG_USERNAME);
		goto fail;
	}

	kv_local.key = (char *)zbx_vault_dbpassword_key;
	if (NULL == (kv_password = zbx_kvs_search(&kvs, &kv_local)))
	{
		*error = zbx_dsprintf(*error, "cannot retrieve value of key \"%s\"", ZBX_PROTO_TAG_PASSWORD);
		goto fail;
	}

	*dbuser = zbx_strdup(NULL, kv_username->value);
	*dbpassword = zbx_strdup(NULL, kv_password->value);

	ret = SUCCEED;
fail:
	zbx_kvs_destroy(&kvs);

	return ret;
}

int	zbx_vault_token_from_env_get(char **token, char **error)
{
#if defined(HAVE_GETENV) && defined(HAVE_UNSETENV)
	char	*ptr;

	if (NULL == (ptr = getenv("VAULT_TOKEN")))
		return SUCCEED;

	if (NULL != *token)
	{
		*error = zbx_dsprintf(*error, "both \"VaultToken\" configuration parameter"
				" and \"VAULT_TOKEN\" environment variable are defined");
		return FAIL;
	}

	*token = zbx_strdup(NULL, ptr);
	unsetenv("VAULT_TOKEN");
#else
	ZBX_UNUSED(token)
	ZBX_UNUSED(error);
#endif
	return SUCCEED;
}
