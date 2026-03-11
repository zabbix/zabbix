/*
** Copyright (C) 2001-2026 Zabbix SIA
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

#include "zbxvault.h"
#include "hashicorp.h"
#include "cyberark.h"

#include "zbxkvs.h"
#include "zbxstr.h"
#include "zbxjson.h"

typedef	int (*zbx_vault_get_kvs_cb_t)(const char *vault_url, const char *prefix, const char *token, const char *approle,
		const char *ssl_cert_file, const char *ssl_key_file, const char *config_source_ip,
		const char *config_ssl_ca_location, const char *config_ssl_cert_location,
		const char *config_ssl_key_location, const char *path, long timeout, zbx_kvs_t *kvs,
		int *vault_ret, char **error);

typedef	void (*zbx_vault_renew_token_cb_t)(const char *vault_url, const char *app_role_id, const char *app_secret_id,
		const char *ssl_cert_file, const char *ssl_key_file, const char *config_source_ip,
		const char *config_ssl_ca_location, const char *config_ssl_cert_location,
		const char *config_ssl_key_location, long timeout, char **token);

static zbx_vault_get_kvs_cb_t		zbx_vault_get_kvs_cb;
static zbx_vault_renew_token_cb_t	zbx_vault_renew_token_cb;
static const char			*zbx_vault_dbuser_key, *zbx_vault_dbpassword_key;

int	zbx_vault_init(const zbx_config_vault_t *config_vault, char **error)
{
#define ZBX_HASHICORP_NAME		"HashiCorp"
#define ZBX_HASHICORP_DBUSER_KEY	"username"
#define ZBX_HASHICORP_DBPASSWORD_KEY	"password"

#define ZBX_CYBERARK_NAME		"CyberArk"
#define ZBX_CYBERARK_DBUSER_KEY		"UserName"
#define ZBX_CYBERARK_DBPASSWORD_KEY	"Content"
	if (NULL == config_vault->name || '\0' == *(config_vault->name) || 0 == strcmp(config_vault->name,
			ZBX_HASHICORP_NAME))
	{
		if (NULL == config_vault->token && NULL == config_vault->app_role_id &&
			0 == zbx_strcmp_null(config_vault->name, ZBX_HASHICORP_NAME))
		{
			*error = zbx_dsprintf(*error, "at least one configuration parameter "
				"(\"VaultToken\" or \"VaultAppRoleID\")"
				" or corresponding environment variable is required");
			return FAIL;
		}

		if (NULL != config_vault->token && NULL != config_vault->app_role_id)
		{
			*error = zbx_dsprintf(*error, "Only one authentication method allowed"
				" \"VaultToken\" or \"VaultAppRoleID\"");
			return FAIL;
		}

		if (NULL != config_vault->app_role_id && NULL == config_vault->app_secret_id)
		{
			*error = zbx_dsprintf(*error,
					" \"VaultAppRoleID\" requires \"VaultAppSecretID\" configuration");
			return FAIL;
		}

		zbx_vault_get_kvs_cb = zbx_vault_get_kvs_hashicorp;
		zbx_vault_renew_token_cb = zbx_vault_renew_token_hashicorp;
		zbx_vault_dbuser_key = ZBX_HASHICORP_DBUSER_KEY;
		zbx_vault_dbpassword_key = ZBX_HASHICORP_DBPASSWORD_KEY;
	}
	else if (0 == strcmp(config_vault->name, ZBX_CYBERARK_NAME))
	{
		if (NULL != config_vault->token)
		{
			*error = zbx_dsprintf(*error, "\"Vault\" value \"%s\" cannot be used when \"VaultToken\""
					" configuration parameter or \"VAULT_TOKEN\" environment variable is defined",
					config_vault->name);
			return FAIL;
		}

		zbx_vault_get_kvs_cb = zbx_vault_get_kvs_cyberark;
		zbx_vault_dbuser_key = ZBX_CYBERARK_DBUSER_KEY;
		zbx_vault_dbpassword_key = ZBX_CYBERARK_DBPASSWORD_KEY;
	}
	else
	{
		*error = zbx_dsprintf(*error, "invalid \"Vault\" configuration parameter: '%s'", config_vault->name);
		return FAIL;
	}

	return SUCCEED;
#undef ZBX_HASHICORP_NAME
#undef ZBX_HASHICORP_DBUSER_KEY
#undef ZBX_HASHICORP_DBPASSWORD_KEY

#undef ZBX_CYBERARK_NAME
#undef ZBX_CYBERARK_DBUSER_KEY
#undef ZBX_CYBERARK_DBPASSWORD_KEY
}

void	zbx_vault_renew_token(const zbx_config_vault_t *config_vault, const char *config_source_ip,
		const char *config_ssl_ca_location, const char *config_ssl_cert_location,
		const char *config_ssl_key_location, char **token)
{
	if (NULL == zbx_vault_renew_token_cb)
		return;

	zbx_vault_renew_token_cb(config_vault->url, config_vault->app_role_id, config_vault->app_secret_id,
			config_vault->tls_cert_file, config_vault->tls_key_file, config_source_ip,
			config_ssl_ca_location, config_ssl_cert_location, config_ssl_key_location,
			ZBX_VAULT_TIMEOUT, token);
}

int	zbx_vault_get_kvs(const char *path, zbx_kvs_t *kvs, const zbx_config_vault_t *config_vault,
		const char *config_source_ip, const char *config_ssl_ca_location,
		const char *config_ssl_cert_location, const char *config_ssl_key_location, int *vault_ret,
		char **error)
{
	return zbx_vault_get_kvs_cb(config_vault->url, config_vault->prefix, config_vault->token,
			config_vault->app_role_id,
			config_vault->tls_cert_file, config_vault->tls_key_file, config_source_ip,
			config_ssl_ca_location, config_ssl_cert_location, config_ssl_key_location, path,
			ZBX_VAULT_TIMEOUT, kvs, vault_ret, error);
}

int	zbx_vault_db_credentials_get(zbx_config_vault_t *config_vault, char **dbuser, char **dbpassword,
		const char *config_source_ip, const char *config_ssl_ca_location, const char *config_ssl_cert_location,
		const char *config_ssl_key_location, char **error)
{
	int		ret = FAIL;
	zbx_kvs_t	kvs;
	const zbx_kv_t	*kv_username, *kv_password;
	zbx_kv_t	kv_local;

	if (NULL == config_vault->db_path)
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

	if (NULL == config_vault->token)
	{
		zbx_vault_renew_token(config_vault, config_source_ip,config_ssl_ca_location,
				config_ssl_cert_location, config_ssl_key_location, &config_vault->token);

		if (NULL == config_vault->token)
		{
			*error = zbx_dsprintf(*error, "cannot approle login");
			goto fail;
		}
	}

	if (SUCCEED != zbx_vault_get_kvs_cb(config_vault->url, config_vault->prefix, config_vault->token,
			config_vault->app_role_id,
			config_vault->tls_cert_file, config_vault->tls_key_file, config_source_ip,
			config_ssl_ca_location, config_ssl_cert_location, config_ssl_key_location,
			config_vault->db_path, ZBX_VAULT_TIMEOUT, &kvs, NULL, error))
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

int	zbx_vault_approle_from_env_get(zbx_config_vault_t *config_vault, char **error)
{
#if defined(HAVE_GETENV) && defined(HAVE_UNSETENV)
	char	*ptr;

	if (NULL != (ptr = getenv("VAULT_APPROLE")))
	{
		if (NULL != config_vault->app_role_id)
		{
			*error = zbx_dsprintf(*error, "both \"VaultAppRoleID\" configuration parameter"
					" and \"VAULT_APPROLE\" environment variable are defined");

			return FAIL;
		}

		config_vault->app_role_id = zbx_strdup(NULL, ptr);
		unsetenv("VAULT_APPROLE");
	}

	if (NULL != (ptr = getenv("VAULT_APPSECRET")))
	{
		if (NULL != config_vault->app_secret_id)
		{
			*error = zbx_dsprintf(*error, "both \"VaultAppSecretID\" configuration parameter"
					" and \"VAULT_APPSECRET\" environment variable are defined");

			return FAIL;
		}

		config_vault->app_secret_id = zbx_strdup(NULL, ptr);
		unsetenv("VAULT_APPSECRET");
	}

#else
	ZBX_UNUSED(config_vault)
	ZBX_UNUSED(error);
#endif
	return SUCCEED;
}
