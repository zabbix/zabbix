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

#include "zbxtelemetry.h"

#include "zbxcommon.h"
#include "zbxcfg.h"
#include "zbxnum.h"
#include "zbxtypes.h"
#include "zbxvault.h"

#define APM_PROVIDER_NAME_CLICKHOUSE	"clickhouse"

#define APM_PROVIDER_OPTION_URL			"url"
#define APM_PROVIDER_OPTION_USERNAME		"username"
#define APM_PROVIDER_OPTION_PASSWORD		"password"
#define APM_PROVIDER_OPTION_DB			"db"
#define APM_PROVIDER_OPTION_SOURCE_IP		"source_ip"
#define APM_PROVIDER_OPTION_VAULT_PATH		"vault_path"
#define APM_PROVIDER_OPTION_SSL_CERT_FILE	"ssl_cert_file"
#define APM_PROVIDER_OPTION_SSL_KEY_FILE	"ssl_key_file"
#define APM_PROVIDER_OPTION_SSL_KEY_PASSWORD	"ssl_key_password"
#define APM_PROVIDER_OPTION_SSL_VERIFY_PEER	"ssl_verify_peer"
#define APM_PROVIDER_OPTION_SSL_VERIFY_HOST	"ssl_verify_host"
#define APM_PROVIDER_OPTION_SSL_CA_LOCATION	"ssl_ca_location"
#define APM_PROVIDER_OPTION_SSL_CERT_LOCATION	"ssl_cert_location"
#define APM_PROVIDER_OPTION_SSL_KEY_LOCATION	"ssl_key_location"

static char	*get_option_value_str_dyn(const zbx_vector_config_option_t *options, const char *name,
		const char *default_value)
{
	const char *str = zbx_config_option_value(options->values, options->values_num, name);

	if (NULL == str)
		return (NULL == default_value ? NULL : zbx_strdup(NULL, default_value));

	return zbx_strdup(NULL, str);
}

static int	get_option_value_int(const zbx_vector_config_option_t *options, const char *name, int default_value,
		int *out, char **error)
{
	const char *str = zbx_config_option_value(options->values, options->values_num, name);

	if (NULL == str)
	{
		*out = default_value;
		return SUCCEED;
	}

	if (SUCCEED != zbx_is_int(str, out))
	{
		*error = zbx_dsprintf(NULL, "invalid \"%s\" in TelemetryProvider: \"%s\"", name, str);
		return FAIL;
	}

	return SUCCEED;
}

static int	get_option_value_bool_uchar(const zbx_vector_config_option_t *options, const char *name,
		unsigned char default_value, unsigned char *out, char **error)
{
	int	i;

	if (SUCCEED != get_option_value_int(options, name, default_value, &i, error))
		return FAIL;

	if (0 != i && 1 != i)
	{
		*error = zbx_dsprintf(NULL, "invalid \"%s\" in TelemetryProvider: %d", name, i);
		return FAIL;
	}

	*out = (unsigned char)i;

	return SUCCEED;
}

static void	log_unsupported_options(const zbx_vector_config_option_t *options)
{
	const char * const supported_options[] = {
		APM_PROVIDER_OPTION_URL,
		APM_PROVIDER_OPTION_USERNAME,
		APM_PROVIDER_OPTION_PASSWORD,
		APM_PROVIDER_OPTION_DB,
		APM_PROVIDER_OPTION_SOURCE_IP,
		APM_PROVIDER_OPTION_VAULT_PATH,
		APM_PROVIDER_OPTION_SSL_CERT_FILE,
		APM_PROVIDER_OPTION_SSL_KEY_FILE,
		APM_PROVIDER_OPTION_SSL_KEY_PASSWORD,
		APM_PROVIDER_OPTION_SSL_VERIFY_PEER,
		APM_PROVIDER_OPTION_SSL_VERIFY_HOST,
		APM_PROVIDER_OPTION_SSL_CA_LOCATION,
		APM_PROVIDER_OPTION_SSL_CERT_LOCATION,
		APM_PROVIDER_OPTION_SSL_KEY_LOCATION,
		NULL,
	};

	for (int i = 0; i < options->values_num; i++)
	{
		int	found = FAIL;

		for (int j = 0; NULL != supported_options[j]; j++)
		{
			if (0 == strcmp(options->values[i].name, supported_options[j]))
			{
				found = SUCCEED;
				break;
			}
		}

		if (SUCCEED != found)
		{
			zabbix_log(LOG_LEVEL_WARNING, "Unsupported TelemetryProvider option: \"%s\"",
					options->values[i].name);
		}
	}
}

static int	parse_apm_provider(zbx_apm_db_config_t *apm_db_config, const char *config_apm_provider,
		const char *config_source_ip, const char *config_ssl_ca_location, const char *config_ssl_cert_location,
		const char *config_ssl_key_location, char **error)
{
	int		ret = FAIL;
	const char	*p = config_apm_provider;
	const char	*p2;

	zbx_vector_config_option_t	options;

	zbx_vector_config_option_create(&options);

	if (NULL == (p2 = strchr(p, ';')))
	{
		*error = zbx_dsprintf(NULL, "invalid TelemetryProvider value \"%s\"", config_apm_provider);
		goto out;
	}

	if (ZBX_CONST_STRLEN(APM_PROVIDER_NAME_CLICKHOUSE) == (size_t)(p2 - p) &&
			0 == strncmp(p, APM_PROVIDER_NAME_CLICKHOUSE, p2 - p))
	{
		apm_db_config->db_type = ZBX_APM_DB_TYPE_CLICKHOUSE;
	}
	else
	{
		*error = zbx_dsprintf(NULL, "invalid database type in TelemetryProvider: \"%.*s\"", (int)(p2 - p), p);
		goto out;
	}

	p = p2 + 1;

	while (' ' == *p)
		p++;

	/* assuming there is at least 1 mandatory option */
	if (SUCCEED != zbx_config_option_parse_options(p, &options, error))
		goto out;

	log_unsupported_options(&options);

	apm_db_config->url = get_option_value_str_dyn(&options, APM_PROVIDER_OPTION_URL,
			NULL);
	apm_db_config->username = get_option_value_str_dyn(&options, APM_PROVIDER_OPTION_USERNAME,
			NULL);
	apm_db_config->password = get_option_value_str_dyn(&options, APM_PROVIDER_OPTION_PASSWORD,
			NULL);
	apm_db_config->db = get_option_value_str_dyn(&options, APM_PROVIDER_OPTION_DB,
			NULL);
	apm_db_config->source_ip = get_option_value_str_dyn(&options, APM_PROVIDER_OPTION_SOURCE_IP,
			config_source_ip);
	apm_db_config->vault_path = get_option_value_str_dyn(&options, APM_PROVIDER_OPTION_VAULT_PATH,
			NULL);
	apm_db_config->ssl_cert_file = get_option_value_str_dyn(&options, APM_PROVIDER_OPTION_SSL_CERT_FILE,
			NULL);
	apm_db_config->ssl_key_file = get_option_value_str_dyn(&options, APM_PROVIDER_OPTION_SSL_KEY_FILE,
			NULL);
	apm_db_config->ssl_key_password = get_option_value_str_dyn(&options, APM_PROVIDER_OPTION_SSL_KEY_PASSWORD,
			NULL);

	if (SUCCEED != get_option_value_bool_uchar(&options, APM_PROVIDER_OPTION_SSL_VERIFY_PEER,
			1, &apm_db_config->ssl_verify_peer, error))
	{
		goto out;
	}

	if (SUCCEED != get_option_value_bool_uchar(&options, APM_PROVIDER_OPTION_SSL_VERIFY_HOST,
			1, &apm_db_config->ssl_verify_host, error))
	{
		goto out;
	}

	apm_db_config->ssl_ca_location = get_option_value_str_dyn(&options, APM_PROVIDER_OPTION_SSL_CA_LOCATION,
			config_ssl_ca_location);
	apm_db_config->ssl_cert_location = get_option_value_str_dyn(&options, APM_PROVIDER_OPTION_SSL_CERT_LOCATION,
			config_ssl_cert_location);
	apm_db_config->ssl_key_location = get_option_value_str_dyn(&options, APM_PROVIDER_OPTION_SSL_KEY_LOCATION,
			config_ssl_key_location);

	ret = SUCCEED;
out:
	zbx_config_option_clear_options(options.values, options.values_num);
	zbx_vector_config_option_destroy(&options);

	return ret;
}

static int	validate_config(const zbx_apm_db_config_t *apm_db_config, char **error)
{
	if (NULL == apm_db_config->url)
	{
		*error = zbx_dsprintf(NULL, "missing mandatory \"%s\" option in TelemetryProvider",
				APM_PROVIDER_OPTION_URL);
		return FAIL;
	}

	if (NULL == apm_db_config->db)
	{
		*error = zbx_dsprintf(NULL, "missing mandatory \"%s\" option in TelemetryProvider",
				APM_PROVIDER_OPTION_DB);
		return FAIL;
	}

	if (NULL != apm_db_config->vault_path)
	{
		if (NULL != apm_db_config->username || NULL != apm_db_config->password)
		{
			*error = zbx_dsprintf(NULL,
					"\"%s\" cannot be set when \"%s\" or \"%s\" is set in TelemetryProvider",
					APM_PROVIDER_OPTION_VAULT_PATH, APM_PROVIDER_OPTION_USERNAME,
					APM_PROVIDER_OPTION_PASSWORD);
			return FAIL;
		}
	}
	else /* NULL == apm_db_config->vault_path */
	{
		/* either both username and password must be set or both must be unset */

		if (NULL != apm_db_config->username && NULL == apm_db_config->password)
		{
			*error = zbx_dsprintf(NULL, "missing \"%s\" option in TelemetryProvider",
					APM_PROVIDER_OPTION_PASSWORD);
			return FAIL;
		}

		if (NULL != apm_db_config->password && NULL == apm_db_config->username)
		{
			*error = zbx_dsprintf(NULL, "missing \"%s\" option in TelemetryProvider",
					APM_PROVIDER_OPTION_USERNAME);
			return FAIL;
		}
	}

	return SUCCEED;
}

int	zbx_apm_db_config_init(zbx_apm_db_config_t *apm_db_config, const char *config_apm_provider,
		const char *config_source_ip, const char *config_ssl_ca_location, const char *config_ssl_cert_location,
		const char *config_ssl_key_location, const zbx_config_vault_t *config_vault, char **error)
{
	memset(apm_db_config, 0, sizeof(*apm_db_config));

	if (NULL == config_apm_provider)
		return SUCCEED;

	if (SUCCEED != parse_apm_provider(apm_db_config, config_apm_provider, config_source_ip, config_ssl_ca_location,
			config_ssl_cert_location, config_ssl_key_location, error))
		goto fail;

	if (SUCCEED != validate_config(apm_db_config, error))
		goto fail;

	if (NULL != apm_db_config->vault_path &&
			SUCCEED != zbx_vault_apm_db_credentials_get(config_vault, &apm_db_config->username,
			&apm_db_config->password, apm_db_config->vault_path, config_source_ip, config_ssl_ca_location,
			config_ssl_cert_location, config_ssl_key_location, error))
	{
		*error = zbx_dsprintf(*error, "cannot initialize apm database credentials from vault: %s", *error);
		goto fail;
	}

	apm_db_config->have_local_config = 1;

	return SUCCEED;
fail:
	zbx_apm_db_config_clear(apm_db_config);

	return FAIL;
}

void	zbx_apm_db_config_clear(zbx_apm_db_config_t *config)
{
	config->have_local_config = 0;

	zbx_free(config->url);
	zbx_free(config->username);
	zbx_free(config->password);
	zbx_free(config->db);
	zbx_free(config->source_ip);
	zbx_free(config->vault_path);
	zbx_free(config->ssl_cert_file);
	zbx_free(config->ssl_key_file);
	zbx_free(config->ssl_key_password);
	zbx_free(config->ssl_ca_location);
	zbx_free(config->ssl_cert_location);
	zbx_free(config->ssl_key_location);
}
