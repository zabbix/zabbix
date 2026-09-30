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
#include "zbxvault.h"

#ifdef HAVE_LIBCURL

#define ZBX_HTTP_STATUS_CODE_OK		200
#define ZBX_HTTP_STATUS_CODE_FORBIDDEN	403

static int	zbx_vault_app_role_login_hashicorp(const char *url, const char *app_role_id, const char *app_secret_id,
		const char *ssl_cert_file, const char *ssl_key_file, const char *config_source_ip,
		const char *config_ssl_ca_location, const char *config_ssl_cert_location,
		const char *config_ssl_key_location, long timeout, char **error, char **token)
{
	struct zbx_json		json;
	char			*out = NULL, *login_url, *new_token = NULL;
	struct zbx_json_parse	jp, jp_data;
	int			ret = FAIL;
	long			response_code;
	size_t			value_alloc = 0;

	login_url = zbx_dsprintf(NULL, "%s/v1/auth/approle/login", url);
	zbx_json_init(&json, 1024);
	zbx_json_addstring(&json, "role_id", app_role_id, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(&json, "secret_id", app_secret_id, ZBX_JSON_TYPE_STRING);

	if (SUCCEED != zbx_http_req(login_url, NULL, timeout, ssl_cert_file, ssl_key_file, config_source_ip,
			config_ssl_ca_location, config_ssl_cert_location, config_ssl_key_location, &out,
			json.buffer, &response_code, error))
	{
		goto fail;
	}

	if (ZBX_HTTP_STATUS_CODE_OK != response_code)
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

	if (SUCCEED != zbx_json_value_by_name_dyn(&jp_data, "client_token", &new_token, &value_alloc, NULL))
	{
		*error = zbx_strdup(*error, "cannot find the client_token object in the received JSON object.");
		goto fail;
	}

	if (NULL == new_token)
	{
		*error = zbx_strdup(*error, "received null token");
		goto fail;
	}

	if ('\0' == *new_token)
	{
		*error = zbx_strdup(*error, "received empty token");
		goto fail;
	}

	zbx_free(*token);
	*token = new_token;
	new_token = NULL;
	zabbix_log(LOG_LEVEL_DEBUG, "Vault AppRole login successful");
	ret = SUCCEED;
fail:
	zbx_free(new_token);
	zbx_free(out);
	zbx_free(login_url);
	zbx_json_free(&json);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get token information from vault /v1/auth/token/lookup-self       *
 *          endpoint                                                          *
 *                                                                            *
 * Parameters:                                                                *
 *     vault_url                - [IN]                                        *
 *     token                    - [IN]                                        *
 *     ssl_cert_file            - [IN]                                        *
 *     ssl_key_file             - [IN]                                        *
 *     config_source_ip         - [IN]                                        *
 *     config_ssl_ca_location   - [IN]                                        *
 *     config_ssl_cert_location - [IN]                                        *
 *     config_ssl_key_location  - [IN]                                        *
 *     timeout                  - [IN]                                        *
 *     out                      - [OUT] pointer to JSON response from vault.  *
 *                                      Must be deallocated by caller.        *
 *     response_code            - [OUT] HTTP response code                    *
 *     error                    - [OUT] error message. Must be deallocated by *
 *                                      caller.                               *
 *                                                                            *
 * Return value: SUCCEED - HTTP request succeeded, but token lookup-self may  *
 *                         have succeeded or failed. Examine 'response_code'  *
 *                         and 'out' to get token lookup-self result.         *
 *               FAIL - HTTP request failed (see 'error' message). No results *
 *                      from token lookup-self.                               *
 *                                                                            *
 * Comments: token lookup-self result and error handling ('out' and           *
 *           'response_code') are left to caller because they depend on       *
 *           caller needs.                                                    *
 *                                                                            *
 ******************************************************************************/
static int	zbx_vault_token_lookup_self(const char *vault_url, const char *token,
		const char *ssl_cert_file, const char *ssl_key_file, const char *config_source_ip,
		const char *config_ssl_ca_location, const char *config_ssl_cert_location,
		const char *config_ssl_key_location, long timeout, char **out, long *response_code,
		char **error)
{
	char	header[MAX_STRING_LEN];
	char	*url = zbx_dsprintf(NULL, "%s/v1/auth/token/lookup-self", vault_url);

	zbx_snprintf(header, sizeof(header), "X-Vault-Token: %s", token);

	int	ret = zbx_http_req(url, header, timeout, ssl_cert_file, ssl_key_file, config_source_ip,
			config_ssl_ca_location, config_ssl_cert_location, config_ssl_key_location, out,
			NULL, response_code, error);
	zbx_free(url);

	return ret;
}

#endif

int	zbx_vault_get_kvs_hashicorp(const char *vault_url, const char *prefix, const char *token, const char *approle,
		const char *ssl_cert_file, const char *ssl_key_file, const char *config_source_ip,
		const char *config_ssl_ca_location, const char *config_ssl_cert_location,
		const char *config_ssl_key_location, const char *path, long timeout, zbx_kvs_t *kvs,
		int *vault_ret,	char **error)
{
#ifndef HAVE_LIBCURL
	ZBX_UNUSED(vault_url);
	ZBX_UNUSED(prefix);
	ZBX_UNUSED(token);
	ZBX_UNUSED(approle);
	ZBX_UNUSED(ssl_cert_file);
	ZBX_UNUSED(ssl_key_file);
	ZBX_UNUSED(path);
	ZBX_UNUSED(timeout);
	ZBX_UNUSED(config_source_ip);
	ZBX_UNUSED(config_ssl_ca_location);
	ZBX_UNUSED(config_ssl_cert_location);
	ZBX_UNUSED(config_ssl_key_location);
	ZBX_UNUSED(kvs);
	ZBX_UNUSED(vault_ret);

	*error = zbx_dsprintf(*error, "missing cURL library");
	return FAIL;
#else
	char			*out = NULL, *url, header[MAX_STRING_LEN], *left, *right;
	struct zbx_json_parse	jp, jp_data, jp_data_data;
	int			ret = FAIL;
	long			response_code;

	if (NULL == token)
	{
		if (NULL == approle)
		{
			*error = zbx_dsprintf(*error, "at least one configuration parameter "
				"(\"VaultToken\" or \"VaultAppRoleID\")"
				" or corresponding environment variable required");
		}
		else
		{
			*error = zbx_dsprintf(*error, "Vault token is not available."
					" AppRole authentication failed or token expired");
		}

		return FAIL;
	}

	if (NULL == prefix || '\0' == *prefix)
	{
		zbx_strsplit_first(path, '/', &left, &right);

		if (NULL == right)
		{
			*error = zbx_dsprintf(*error, "cannot find separator \"\\\" in path");
			zbx_free(left);
			return FAIL;
		}
		url = zbx_dsprintf(NULL, "%s/v1/%s/data/%s", vault_url, left, right);

		zbx_free(right);
		zbx_free(left);
	}
	else
		url = zbx_dsprintf(NULL, "%s%s%s", vault_url, prefix, path);

	zbx_snprintf(header, sizeof(header), "X-Vault-Token: %s", token);

	if (SUCCEED != zbx_http_req(url, header, timeout, ssl_cert_file, ssl_key_file, config_source_ip,
			config_ssl_ca_location, config_ssl_cert_location, config_ssl_key_location, &out, NULL,
			&response_code, error))
	{
		goto fail;
	}

	if (ZBX_HTTP_STATUS_CODE_OK != response_code)
	{
		if (NULL != approle && ZBX_HTTP_STATUS_CODE_FORBIDDEN == response_code)
		{
			char	*out2 = NULL, *errmsg = NULL;
			long	resp_code;

			if (SUCCEED != zbx_vault_token_lookup_self(vault_url, token, ssl_cert_file, ssl_key_file,
					config_source_ip, config_ssl_ca_location, config_ssl_cert_location,
					config_ssl_key_location, timeout, &out2, &resp_code, &errmsg))
			{
				*error = zbx_dsprintf(*error, "token lookup-self request failed: %s", errmsg);
				zbx_free(errmsg);
				goto fail;
			}

			switch (resp_code)
			{
				case ZBX_HTTP_STATUS_CODE_OK:
					*error = zbx_strdup(*error, "cannot get secrets, token is valid,"
							" could be problem with policy or configuration"
							" parameter 'VaultPrefix' value");
					break;
				case ZBX_HTTP_STATUS_CODE_FORBIDDEN:
					if (NULL != vault_ret)
						*vault_ret = FAIL;

					*error = zbx_strdup(*error, "AppRole token is likely expired or revoked"
							" (lookup-self response code 403)");
					break;
				default:
					*error = zbx_dsprintf(*error, "vault token lookup-self failed with"
							" code \"%ld\"", resp_code);
			}

			zbx_free(out2);
			zbx_free(errmsg);
			goto fail;
		}

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

void	zbx_vault_renew_token_hashicorp(const char *vault_url, const char *app_role_id, const char *app_secret_id,
		const char *ssl_cert_file, const char *ssl_key_file, const char *config_source_ip,
		const char *config_ssl_ca_location, const char *config_ssl_cert_location,
		const char *config_ssl_key_location, long timeout, int force, char **token)
{
#ifndef HAVE_LIBCURL
	ZBX_UNUSED(vault_url);
	ZBX_UNUSED(app_role_id);
	ZBX_UNUSED(app_secret_id);
	ZBX_UNUSED(ssl_cert_file);
	ZBX_UNUSED(ssl_key_file);
	ZBX_UNUSED(config_source_ip);
	ZBX_UNUSED(config_ssl_ca_location);
	ZBX_UNUSED(config_ssl_cert_location);
	ZBX_UNUSED(config_ssl_key_location);
	ZBX_UNUSED(timeout);
	ZBX_UNUSED(force);
	ZBX_UNUSED(token);
#else
	char			*out = NULL, *error = NULL, header[MAX_STRING_LEN], *url = NULL, *value = NULL;
	size_t			value_alloc = 0;
	struct zbx_json_parse	jp, jp_data;
	long			response_code;
	int			status = FAIL;
	static int		renewable = 0, last_status = SUCCEED;
	static double		next_try_after_error;
	static double		next_renew;

	if (ZBX_VAULT_RENEW_TOKEN_FORCE == force)
	{
		last_status = SUCCEED;
		next_try_after_error = 0;
	}

	if (SUCCEED != last_status && zbx_time() < next_try_after_error)
		return;

	if (NULL == *token)
	{
		char	*errmsg = NULL;

		if (NULL == app_role_id)
			return;

		if (SUCCEED != zbx_vault_app_role_login_hashicorp(vault_url, app_role_id, app_secret_id, ssl_cert_file,
					ssl_key_file, config_source_ip, config_ssl_ca_location,
					config_ssl_cert_location, config_ssl_key_location, timeout, &errmsg, token))
		{
			error = zbx_dsprintf(NULL, "cannot login into HashiCorp vault with AppRole method: %s", errmsg);
			zbx_free(errmsg);

			goto out;
		}

		next_renew = 0;
	}

	if (0 == next_renew)
	{
		if (SUCCEED != zbx_vault_token_lookup_self(vault_url, *token, ssl_cert_file, ssl_key_file,
				config_source_ip, config_ssl_ca_location, config_ssl_cert_location,
				config_ssl_key_location, timeout, &out, &response_code, &error))
		{
			goto out;
		}

		if (ZBX_HTTP_STATUS_CODE_OK != response_code)
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
			int	log_level = (NULL == app_role_id) ? LOG_LEVEL_WARNING : LOG_LEVEL_DEBUG;

			zabbix_log(log_level, "cannot renew vault token: token is not renewable");
			status = SUCCEED;
			renewable = 0;
			goto out;
		}

		renewable = 1;
		zbx_free(out);
	}

	if (0 != renewable && zbx_time() >= next_renew)
	{
		zbx_uint64_t	ttl;

		zbx_snprintf(header, sizeof(header), "X-Vault-Token: %s", *token);

		url = zbx_dsprintf(url, "%s%s", vault_url, "/v1/auth/token/renew-self");

		if (SUCCEED != zbx_http_req(url, header, timeout, ssl_cert_file, ssl_key_file,
				config_source_ip, config_ssl_ca_location, config_ssl_cert_location,
				config_ssl_key_location, &out, "{}", &response_code, &error))
		{
			goto out;
		}

		if (ZBX_HTTP_STATUS_CODE_OK != response_code)
		{
			if (NULL != app_role_id && ZBX_HTTP_STATUS_CODE_FORBIDDEN == response_code)
			{
				char	*out2 = NULL, *errmsg = NULL;
				long	resp_code;

				if (SUCCEED != zbx_vault_token_lookup_self(vault_url, *token, ssl_cert_file,
						ssl_key_file, config_source_ip, config_ssl_ca_location,
						config_ssl_cert_location, config_ssl_key_location, timeout,
						&out2, &resp_code, &error))
				{
					/* HTTP request failed */
					goto out;
				}

				if (ZBX_HTTP_STATUS_CODE_OK == resp_code)
				{
					error = zbx_strdup(error, "cannot renew token, token is valid,"
							" could be problem with policy");
					zbx_free(out2);
					goto out;
				}

				if (ZBX_HTTP_STATUS_CODE_FORBIDDEN != resp_code)
				{
					error = zbx_dsprintf(error, "vault token lookup-self failed with"
							" code \"%ld\"", resp_code);
					zbx_free(out2);
					goto out;
				}

				/* 'resp_code' is ZBX_HTTP_STATUS_CODE_FORBIDDEN */

				zbx_free(out2);

				if (SUCCEED == zbx_vault_app_role_login_hashicorp(vault_url, app_role_id,
						app_secret_id, ssl_cert_file, ssl_key_file, config_source_ip,
						config_ssl_ca_location, config_ssl_cert_location,
						config_ssl_key_location, timeout, &errmsg, token))
				{
					next_renew = 0;
					status = SUCCEED;
					zbx_free(error);
					goto out;
				}

				error = zbx_dsprintf(error, "cannot re-login with AppRole method: %s", errmsg);
				zbx_free(errmsg);
				goto out;
			}

			error = zbx_dsprintf(NULL, "unsuccessful response code from renew-self request: \"%ld\"",
					response_code);
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
