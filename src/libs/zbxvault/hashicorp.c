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

#include "hashicorp.h"
#include "zbxcommon.h"

#ifdef HAVE_LIBCURL
#	include "zbxhttp.h"
#	include "zbxstr.h"
#endif

#include "zbxkvs.h"
#include "zbxjson.h"
#include "zbxtime.h"
#include "zbxnum.h"

static char		*approle_token;
#ifdef HAVE_LIBCURL
static double		next_renew;

static int	zbx_vault_app_role_login(const zbx_config_vault_t *config_vault,
		const char *ssl_cert_file, const char *ssl_key_file, const char *config_source_ip,
		const char *config_ssl_ca_location, const char *config_ssl_cert_location,
		const char *config_ssl_key_location, long timeout, char **error)
{
	struct zbx_json		json;
	char			*out = NULL, *login_url;
	struct zbx_json_parse	jp, jp_data;
	int			ret = FAIL;
	long			response_code;
	size_t			value_alloc = 0;

	login_url = zbx_dsprintf(NULL, "%s/v1/auth/approle/login", config_vault->url);
	zbx_json_init(&json, 1024);
	zbx_json_addstring(&json, "role_id", config_vault->app_role_id, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(&json, "secret_id", config_vault->app_secret_id, ZBX_JSON_TYPE_STRING);

	if (SUCCEED != zbx_http_req(login_url, NULL, timeout, ssl_cert_file, ssl_key_file, config_source_ip,
			config_ssl_ca_location, config_ssl_cert_location, config_ssl_key_location, &out,
			json.buffer, &response_code, error))
	{
		goto fail;
	}

	if (200 != response_code && 204 != response_code)
	{
		*error = zbx_dsprintf(*error, "unsuccessful response code \"%ld\"", response_code);
		goto fail;
	}

	if (SUCCEED != zbx_json_open(out, &jp))
	{
		*error = zbx_dsprintf(*error, "cannot parse secrets from vault: %s", zbx_json_strerror());
		goto fail;
	}

	if (SUCCEED != zbx_json_brackets_by_name(&jp, ZBX_PROTO_TAG_AUTH, &jp_data))
	{
		*error = zbx_dsprintf(*error, "cannot find the \"%s\" object in the received JSON object.",
				ZBX_PROTO_TAG_AUTH);
		goto fail;
	}

	zbx_free(approle_token);

	if (SUCCEED != zbx_json_value_by_name_dyn(&jp_data, "client_token", &approle_token, &value_alloc, NULL))
	{
		*error = zbx_dsprintf(*error, "cannot find the client_token object in the received JSON object.");
		goto fail;
	}

	if (NULL == approle_token || '\0' == *approle_token)
	{
		*error = zbx_dsprintf(*error, "unable to receive token");
		goto fail;
	}

	ret = SUCCEED;
fail:
	zbx_free(out);
	zbx_json_free(&json);

	return ret;
}

#endif

int	zbx_vault_get_kvs_hashicorp(const zbx_config_vault_t *config_vault,
		const char *ssl_cert_file, const char *ssl_key_file, const char *config_source_ip,
		const char *config_ssl_ca_location, const char *config_ssl_cert_location,
		const char *config_ssl_key_location, const char *path, long timeout, zbx_kvs_t *kvs,
		char **relog_token, char **error)
{
#ifndef HAVE_LIBCURL
	ZBX_UNUSED(config_vault);
	ZBX_UNUSED(ssl_cert_file);
	ZBX_UNUSED(ssl_key_file);
	ZBX_UNUSED(path);
	ZBX_UNUSED(timeout);
	ZBX_UNUSED(config_source_ip);
	ZBX_UNUSED(config_ssl_ca_location);
	ZBX_UNUSED(config_ssl_cert_location);
	ZBX_UNUSED(config_ssl_key_location);
	ZBX_UNUSED(kvs);
	ZBX_UNUSED(rtc);
	*error = zbx_dsprintf(*error, "missing cURL library");
	return FAIL;
#else
	char			*out = NULL, *url, header[MAX_STRING_LEN], *left, *right;
	struct zbx_json_parse	jp, jp_data, jp_data_data;
	int			ret = FAIL;
	long			response_code;

	if (NULL == approle_token && NULL != config_vault->app_role_id)
	{
		if (FAIL == (ret = zbx_vault_app_role_login(config_vault, ssl_cert_file, ssl_key_file, config_source_ip,
				config_ssl_ca_location, config_ssl_cert_location, config_ssl_key_location, timeout,
				error)))
		{
			return FAIL;
		}
	}

	if (NULL == config_vault->prefix || '\0' == *config_vault->prefix)
	{
		zbx_strsplit_first(path, '/', &left, &right);

		if (NULL == right)
		{
			*error = zbx_dsprintf(*error, "cannot find separator \"\\\" in path");
			free(left);
			return FAIL;
		}
		url = zbx_dsprintf(NULL, "%s/v1/%s/data/%s", config_vault->url, left, right);

		zbx_free(right);
		zbx_free(left);
	}
	else
		url = zbx_dsprintf(NULL, "%s%s%s", config_vault->url, config_vault->prefix, path);

	zbx_snprintf(header, sizeof(header), "X-Vault-Token: %s",
		(NULL != approle_token) ? approle_token : config_vault->token);

	if (SUCCEED != zbx_http_req(url, header, timeout, ssl_cert_file, ssl_key_file, config_source_ip,
			config_ssl_ca_location, config_ssl_cert_location, config_ssl_key_location, &out, NULL,
			&response_code, error))
	{
		goto fail;
	}

	if (403 == response_code && NULL != approle_token && NULL != relog_token)
	{
		*relog_token = approle_token;
		*error = zbx_dsprintf(*error, "unsuccessful response code \"%ld\" try relogin", response_code);
		goto fail;
	}

	if (200 != response_code && 204 != response_code)
	{
		*error = zbx_dsprintf(*error, "unsuccessful response code \"%ld\"", response_code);
		goto fail;
	}

	if (SUCCEED != zbx_json_open(out, &jp))
	{
		*error = zbx_dsprintf(*error, "cannot parse secrets from vault: %s", zbx_json_strerror());
		goto fail;
	}

	if (SUCCEED != zbx_json_brackets_by_name(&jp, ZBX_PROTO_TAG_DATA, &jp_data))
	{
		*error = zbx_dsprintf(*error, "cannot find the \"%s\" object in the received JSON object.",
				ZBX_PROTO_TAG_DATA);
		goto fail;
	}

	if (SUCCEED != zbx_json_brackets_by_name(&jp_data, ZBX_PROTO_TAG_DATA, &jp_data_data))
	{
		*error = zbx_dsprintf(*error, "cannot find the \"%s\" object in the received \"%s\" JSON object.",
				ZBX_PROTO_TAG_DATA, ZBX_PROTO_TAG_DATA);
		goto fail;
	}

	zbx_kvs_from_json_get(&jp_data_data, kvs);

	ret = SUCCEED;
fail:
	zbx_free(url);
	zbx_free(out);

	return ret;
#endif
}

void	zbx_vault_update_token_hashicorp(char *token)
{
	approle_token = zbx_strdup(approle_token, token);
}

int	zbx_vault_relogin_hashicorp(const zbx_config_vault_t *config_vault, const char *config_source_ip,
		const char *config_ssl_ca_location, const char *config_ssl_cert_location,
		const char *config_ssl_key_location, char **token, char **error)
{
#ifndef HAVE_LIBCURL
	ZBX_UNUSED(config_vault);
	ZBX_UNUSED(token);
	ZBX_UNUSED(config_source_ip);
	ZBX_UNUSED(config_ssl_ca_location);
	ZBX_UNUSED(config_ssl_cert_location);
	ZBX_UNUSED(config_ssl_key_location);
	*error = zbx_dsprintf(*error, "missing cURL library");
	return FAIL;
#else
	int	ret;

	if (0 != strcmp((char*) *token, approle_token))
	{
		*error = NULL;
		return FAIL;
	}

	if (SUCCEED == (ret = zbx_vault_app_role_login(config_vault, config_vault->tls_cert_file,
			config_vault->tls_key_file,
			config_source_ip, config_ssl_ca_location, config_ssl_cert_location,
			config_ssl_key_location, ZBX_VAULT_TIMEOUT, error)))
	{
		next_renew = 0;
		*token = approle_token;
	}

	return ret;
#endif
}

void	zbx_vault_renew_token_hashicorp(const char *vault_url, const char *token, const char *ssl_cert_file,
		const char *ssl_key_file, const char *config_source_ip, const char *config_ssl_ca_location,
		const char *config_ssl_cert_location, const char *config_ssl_key_location, long timeout)
{
#ifndef HAVE_LIBCURL
	ZBX_UNUSED(vault_url);
	ZBX_UNUSED(token);
	ZBX_UNUSED(ssl_cert_file);
	ZBX_UNUSED(ssl_key_file);
	ZBX_UNUSED(config_source_ip);
	ZBX_UNUSED(config_ssl_ca_location);
	ZBX_UNUSED(config_ssl_cert_location);
	ZBX_UNUSED(config_ssl_key_location);
	ZBX_UNUSED(timeout);
#else
	char			*out = NULL, *error = NULL, header[MAX_STRING_LEN], *url = NULL, *value = NULL;
	size_t			value_alloc = 0;
	struct zbx_json_parse	jp, jp_data;
	long			response_code;
	int			status = FAIL;
	static int		renewable, last_status = SUCCEED;
	static double		next_try_after_error;

	if (NULL != approle_token)
		token = approle_token;
	else if (NULL == token)
		return;

	if (SUCCEED != last_status && zbx_time() < next_try_after_error)
		return;

	zbx_snprintf(header, sizeof(header), "X-Vault-Token: %s", token);

	if (0 == (unsigned long)next_renew)
	{
		url = zbx_dsprintf(NULL, "%s%s", vault_url, "/v1/auth/token/lookup-self");

		if (SUCCEED != zbx_http_req(url, header, timeout, ssl_cert_file, ssl_key_file, config_source_ip,
				config_ssl_ca_location, config_ssl_cert_location, config_ssl_key_location, &out, NULL,
				&response_code, &error))
		{
			goto out;
		}

		if (200 != response_code && 204 != response_code)
		{
			error = zbx_dsprintf(NULL, "unsuccessful response code \"%ld\"", response_code);
			goto out;
		}

		if (SUCCEED != zbx_json_open(out, &jp))
		{
			error = zbx_dsprintf(NULL, "%s", zbx_json_strerror());
			goto out;
		}

		if (SUCCEED != zbx_json_brackets_by_name(&jp, ZBX_PROTO_TAG_DATA, &jp_data))
		{
			error = zbx_dsprintf(NULL, "cannot find the \"%s\" object in the received JSON object",
					ZBX_PROTO_TAG_DATA);
			goto out;
		}

		next_renew = zbx_time(); /* skip lookup for next calls */

		if (SUCCEED != zbx_json_value_by_name_dyn(&jp_data, "renewable", &value, &value_alloc, NULL) ||
				0 != strcmp(value, "true"))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot renew vault token: token is not renewable");
			status = SUCCEED;
			goto out;
		}

		renewable = 1;
		zbx_free(out);
	}

	if (0 != renewable && zbx_time() >= next_renew)
	{
		zbx_uint64_t	ttl;

		url = zbx_dsprintf(url, "%s%s", vault_url, "/v1/auth/token/renew-self");

		if (SUCCEED != zbx_http_req(url, header, timeout, ssl_cert_file, ssl_key_file,
				config_source_ip, config_ssl_ca_location, config_ssl_cert_location,
				config_ssl_key_location, &out, "{}", &response_code, &error))
		{
			goto out;
		}

		if (200 != response_code && 204 != response_code)
		{
			error = zbx_dsprintf(NULL, "unsuccessful response code \"%ld\"", response_code);
			goto out;
		}

		if (SUCCEED != zbx_json_open(out, &jp))
		{
			error = zbx_dsprintf(NULL, "%s", zbx_json_strerror());
			goto out;
		}

		if (SUCCEED != zbx_json_brackets_by_name(&jp, ZBX_PROTO_TAG_AUTH, &jp_data))
		{
			error = zbx_dsprintf(NULL, "cannot find the \"%s\" object in the received JSON object",
					ZBX_PROTO_TAG_AUTH);
			goto out;
		}

		if (FAIL == zbx_json_value_by_name_dyn(&jp_data, ZBX_PROTO_TAG_LEASE_DURATION, &value, &value_alloc,
				NULL))
		{
			error = zbx_dsprintf(NULL, "cannot find the \"%s\" object in the received JSON object",
					ZBX_PROTO_TAG_LEASE_DURATION);
			goto out;
		}

		if (FAIL == zbx_is_uint64(value, &ttl))
		{
			error = zbx_dsprintf(NULL, "\"%s\" is not a valid numeric", ZBX_PROTO_TAG_LEASE_DURATION);
			goto out;
		}

		next_renew = zbx_time() + (double)ttl * 2 / 3;

		zabbix_log(LOG_LEVEL_DEBUG, "Vault token renewed");
	}

	if (FAIL == last_status && 1 == renewable)
		zabbix_log(LOG_LEVEL_WARNING, "Vault token renew is working again");

	status = SUCCEED;
out:
	if (FAIL == status)
	{
		next_try_after_error = zbx_time() + 60;
		if (NULL != error)
		{
			if (SUCCEED == last_status)
				zabbix_log(LOG_LEVEL_WARNING, "Vault token renew started to fail: %s", error);
			else
				zabbix_log(LOG_LEVEL_DEBUG, "Vault token renew failed: %s", error);
		}
	}

	last_status = status;
	zbx_free(value);
	zbx_free(url);
	zbx_free(out);
	zbx_free(error);
#endif
}

