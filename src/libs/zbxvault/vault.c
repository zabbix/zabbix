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

#include "zbxvault.h"
#include "hashicorp.h"
#include "cyberark.h"

#include "zbxkvs.h"
#include "zbxstr.h"
#include "zbxjson.h"

#define ZBX_VAULT_TIMEOUT	SEC_PER_MIN

typedef	int (*zbx_vault_kvs_get_cb_t)(const char *vault_url, const char *prefix, const char *token,
		const char *ssl_cert_file, const char *ssl_key_file, const char *config_source_ip,
		const char *config_ssl_ca_location, const char *config_ssl_cert_location,
		const char *config_ssl_key_location, const char *path, long timeout, zbx_kvs_t *kvs, char **error);

typedef	void (*zbx_vault_kvs_renew_cb_t)(const char *vault_url, const char *token, const char *ssl_cert_file,
		const char *ssl_key_file, const char *config_source_ip, const char *config_ssl_ca_location,
		const char *config_ssl_cert_location, const char *config_ssl_key_location, long timeout);

static zbx_vault_kvs_get_cb_t	zbx_vault_kvs_get_cb;
static zbx_vault_kvs_renew_cb_t	zbx_vault_kvs_renew_cb;
static const char		*zbx_vault_dbuser_key, *zbx_vault_dbpassword_key;

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
		if (NULL == config_vault->token && 0 == zbx_strcmp_null(config_vault->name, ZBX_HASHICORP_NAME))
		{
			*error = zbx_dsprintf(*error, "\"Vault\" value \"%s\" requires \"VaultToken\" configuration"
					" parameter or \"VAULT_TOKEN\" environment variable", config_vault->name);
			return FAIL;
		}

		zbx_vault_kvs_get_cb = zbx_hashicorp_kvs_get;
		zbx_vault_kvs_renew_cb = zbx_hashicorp_renew_token;
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

		zbx_vault_kvs_get_cb = zbx_cyberark_kvs_get;
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

void	zbx_vault_renew_token(const zbx_config_vault_t *config_vault,
		const char *config_source_ip, const char *config_ssl_ca_location,
		const char *config_ssl_cert_location, const char *config_ssl_key_location)
{
	if (NULL == zbx_vault_kvs_renew_cb)
		return;

	zbx_vault_kvs_renew_cb(config_vault->url, config_vault->token, config_vault->tls_cert_file,
			config_vault->tls_key_file, config_source_ip, config_ssl_ca_location,
			config_ssl_cert_location, config_ssl_key_location, ZBX_VAULT_TIMEOUT);
}

int	zbx_vault_kvs_get(const char *path, zbx_kvs_t *kvs, const zbx_config_vault_t *config_vault,
		const char *config_source_ip, const char *config_ssl_ca_location,
		const char *config_ssl_cert_location, const char *config_ssl_key_location, char **error)
{
	return zbx_vault_kvs_get_cb(config_vault->url, config_vault->prefix, config_vault->token,
			config_vault->tls_cert_file, config_vault->tls_key_file, config_source_ip,
			config_ssl_ca_location, config_ssl_cert_location, config_ssl_key_location, path,
			ZBX_VAULT_TIMEOUT, kvs, error);
}

int	zbx_vault_db_credentials_get(const zbx_config_vault_t *config_vault, char **dbuser, char **dbpassword,
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

	if (SUCCEED != zbx_vault_kvs_get_cb(config_vault->url, config_vault->prefix, config_vault->token,
			config_vault->tls_cert_file, config_vault->tls_key_file, config_source_ip,
			config_ssl_ca_location, config_ssl_cert_location, config_ssl_key_location,
			config_vault->db_path, ZBX_VAULT_TIMEOUT, &kvs, error))
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
