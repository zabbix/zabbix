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
#include "zbxjson.h"

#define ZBX_VAULT_TIMEOUT	SEC_PER_MIN
#define ZBX_HASHICORP_NAME	"HashiCorp"
#define ZBX_CYBERARK_NAME	"CyberArk"

typedef	int (*zbx_vault_get_kvs_cb_t)(const char *vault_url, const char *prefix, const char *token, const char *approle,
		const char *ssl_cert_file, const char *ssl_key_file, const char *config_source_ip,
		const char *config_ssl_ca_location, const char *config_ssl_cert_location,
		const char *config_ssl_key_location, const char *path, long timeout, zbx_kvs_t *kvs,
		int *vault_ret, char **error);

typedef	void (*zbx_vault_renew_token_cb_t)(const char *vault_url, const char *app_role_id, const char *app_secret_id,
		const char *ssl_cert_file, const char *ssl_key_file, const char *config_source_ip,
		const char *config_ssl_ca_location, const char *config_ssl_cert_location,
		const char *config_ssl_key_location, long timeout, int force, char **token);

static zbx_vault_get_kvs_cb_t		zbx_vault_get_kvs_cb = NULL;
static zbx_vault_renew_token_cb_t	zbx_vault_renew_token_cb = NULL;
static const char			*zbx_vault_dbuser_key = NULL, *zbx_vault_dbpassword_key = NULL;

/**********************************************************************************************
 *                                                                                            *
 * Purpose: check if any vault configuration parameter is defined to non-default value        *
 *                                                                                            *
 * Return value: SUCCEED - at least one vault parameter defined                               *
 *               FAIL - no vault parameters defined                                           *
 *                                                                                            *
 *********************************************************************************************/
int	zbx_vault_is_configured(const zbx_config_vault_t *conf)
{
	if (NULL == conf->token &&
			NULL == conf->name &&
			NULL == conf->tls_cert_file &&
			NULL == conf->tls_key_file &&
			0 == strcmp(conf->url, ZBX_VAULT_DEFAULT_URL) &&
			NULL == conf->prefix &&
			NULL == conf->db_path &&
			NULL == conf->app_role_id &&
			NULL == conf->app_secret_id)
	{
		return	FAIL;
	}
	else
		return	SUCCEED;
}

/**********************************************************************************************
 *                                                                                            *
 * Purpose: check for allowed combinations of vault configuration parameters on               *
 *          server or proxy start up                                                          *
 *                                                                                            *
 * Precondition: vault configuration has non-default parameters (checked with                 *
 *               zbx_vault_is_configured() which returned SUCCEED)                            *
 *                                                                                            *
 * Return value: SUCCEED - vault parameters are valid                                         *
 *               FAIL - invalid vault parameter value or combination                          *
 *                                                                                            *
 *********************************************************************************************/
int	zbx_vault_validate_config(const zbx_config_vault_t *conf, const char *dbuser,
		const char *dbpassword, char **error)
{
	/* Vault configuration parameters:
		VaultToken		conf->token, cannot be used with app_role_id, app_secret_id
		Vault			conf->name
		VaultTLSCertFile	conf->tls_cert_file
		VaultTLSKeyFile		conf->tls_key_file, requires tls_key_file
		VaultURL		conf->url
		VaultPrefix		conf->prefix
		VaultDBPath		conf->db_path, cannot be used with dbuser, dbpassword
		VaultAppRoleID		conf->app_role_id, requires app_secret_id
		VaultAppSecretID	conf->app_secret_id, requires app_role_id
	*/

	int	is_hashicorp = 0, is_cyberark = 0;

	/* Default is Vault=HashiCorp */
	if (NULL == conf->name || '\0' == *conf->name || 0 == strcmp(conf->name, ZBX_HASHICORP_NAME))
		is_hashicorp = 1;
	else if (0 == strcmp(conf->name, ZBX_CYBERARK_NAME))
		is_cyberark = 1;

	if (0 == is_hashicorp && 0 == is_cyberark)
	{
		*error = zbx_strdup(*error, "invalid value of configuration parameter \"Vault\"");
		return FAIL;
	}

	/* VaultDBPath is common for HashiCorp and CyberArk */
	if (NULL != conf->db_path)
	{
		if (NULL != dbuser)
		{
			*error = zbx_strdup(*error, "\"DBUser\" configuration parameter cannot be used"
					" when \"VaultDBPath\" is defined");
			return FAIL;
		}

		if (NULL != dbpassword)
		{
			*error = zbx_strdup(*error, "\"DBPassword\" configuration parameter cannot be used"
					" when \"VaultDBPath\" is defined");
			return FAIL;
		}
	}

	/* VaultTLSCertFile can be specified with or without VaultTLSKeyFile (if VaultTLSCertFile */
	/* contains also the private key). VaultTLSKeyFile requires VaultTLSCertFile. */
	if (NULL == conf->tls_cert_file && NULL != conf->tls_key_file)
	{
		*error = zbx_strdup(*error, "\"VaultTLSKeyFile\" is defined but \"VaultTLSCertFile\" is not defined");
		return FAIL;
	}

	if (1 == is_hashicorp)
	{
		/* partial, mixed or otherwise invalid configurations */

		if (NULL != conf->token)
		{
			if (NULL != conf->app_role_id)
			{
				*error = zbx_strdup(*error, "either \"VaultToken\" or \"VaultAppRoleID\""
						" configuration parameter or corresponding environment variable"
						" can be defined for HashiCorp vault but not both");
				return FAIL;
			}

			if (NULL != conf->app_secret_id)
			{
				*error = zbx_strdup(*error, "\"VaultToken\" and \"VaultAppSecretID\" configuration"
						" parameters or corresponding environment variables cannot be defined"
						" at the same time for HashiCorp vault");
				return FAIL;
			}
		}
		else	/* conf->token is not defined */
		{
			if (NULL == conf->app_role_id)
			{
				*error = zbx_strdup(*error, "either \"VaultToken\" or \"VaultAppRoleID\""
						" configuration parameter should be defined for HashiCorp vault");
				return FAIL;
			}

			if (NULL == conf->app_secret_id)
			{
				*error = zbx_strdup(*error, "\"VaultAppRoleID\" is defined but \"VaultAppSecretID\""
						" is not defined");
				return FAIL;
			}
		}
	}
	else	/* CyberArk */
	{
		if (NULL != conf->token)
		{
			*error = zbx_strdup(*error, "configuration parameter \"VaultToken\" or \"VAULT_TOKEN\""
					" environment variable cannot be used with CyberArk vault");
			return FAIL;
		}

		if (NULL != conf->app_role_id)
		{
			*error = zbx_strdup(*error, "configuration parameter \"VaultAppRoleID\" cannot be used"
					" with CyberArk vault");
			return FAIL;
		}

		if (NULL != conf->app_secret_id)
		{
			*error = zbx_strdup(*error, "configuration parameter \"VaultAppSecretID\" cannot be used"
					" with CyberArk vault");
			return FAIL;
		}
	}

	return SUCCEED;
}

void	zbx_vault_init(const char *vault_name)
{
#define ZBX_HASHICORP_DBUSER_KEY	"username"
#define ZBX_HASHICORP_DBPASSWORD_KEY	"password"

#define ZBX_CYBERARK_DBUSER_KEY		"UserName"
#define ZBX_CYBERARK_DBPASSWORD_KEY	"Content"
	if (NULL == vault_name || '\0' == *vault_name || 0 == strcmp(vault_name, ZBX_HASHICORP_NAME))
	{
		zbx_vault_get_kvs_cb = zbx_vault_get_kvs_hashicorp;
		zbx_vault_renew_token_cb = zbx_vault_renew_token_hashicorp;
		zbx_vault_dbuser_key = ZBX_HASHICORP_DBUSER_KEY;
		zbx_vault_dbpassword_key = ZBX_HASHICORP_DBPASSWORD_KEY;
	}
	else	/* CyberArk */
	{
		zbx_vault_get_kvs_cb = zbx_vault_get_kvs_cyberark;
		zbx_vault_dbuser_key = ZBX_CYBERARK_DBUSER_KEY;
		zbx_vault_dbpassword_key = ZBX_CYBERARK_DBPASSWORD_KEY;
	}
#undef ZBX_HASHICORP_DBUSER_KEY
#undef ZBX_HASHICORP_DBPASSWORD_KEY

#undef ZBX_CYBERARK_DBUSER_KEY
#undef ZBX_CYBERARK_DBPASSWORD_KEY
}

void	zbx_vault_renew_token(const zbx_config_vault_t *config_vault, const char *config_source_ip,
		const char *config_ssl_ca_location, const char *config_ssl_cert_location,
		const char *config_ssl_key_location, int force, char **token)
{
	/* caller does not need to be aware of vault type */
	if (NULL == zbx_vault_renew_token_cb)
		return;

	zbx_vault_renew_token_cb(config_vault->url, config_vault->app_role_id, config_vault->app_secret_id,
			config_vault->tls_cert_file, config_vault->tls_key_file, config_source_ip,
			config_ssl_ca_location, config_ssl_cert_location, config_ssl_key_location,
			ZBX_VAULT_TIMEOUT, force, token);
}

int	zbx_vault_get_kvs(const char *path, zbx_kvs_t *kvs, const zbx_config_vault_t *config_vault,
		const char *config_source_ip, const char *config_ssl_ca_location,
		const char *config_ssl_cert_location, const char *config_ssl_key_location, int *vault_ret,
		char **error)
{
	if (NULL == zbx_vault_get_kvs_cb)
	{
		*error = zbx_strdup(*error, "vault is not configured");
		return FAIL;
	}

	return zbx_vault_get_kvs_cb(config_vault->url, config_vault->prefix, config_vault->token,
			config_vault->app_role_id,
			config_vault->tls_cert_file, config_vault->tls_key_file, config_source_ip,
			config_ssl_ca_location, config_ssl_cert_location, config_ssl_key_location, path,
			ZBX_VAULT_TIMEOUT, kvs, vault_ret, error);
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

	/* defensive programming */
	if (NULL == zbx_vault_get_kvs_cb)
	{
		*error = zbx_strdup(*error, "vault is not configured");
		return FAIL;
	}

	zbx_kvs_create(&kvs, 2);

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
