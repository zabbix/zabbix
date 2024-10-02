/*
** Copyright (C) 2001-2024 Zabbix SIA
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

#ifdef HAVE_LIBCURL
#	include "zbxhttp.h"
#	include "zbxstr.h"
#endif

#include "zbxkvs.h"
#include "zbxjson.h"
#include "zbxlog.h"
#include "zbxtime.h"

int	zbx_hashicorp_kvs_get(const char *vault_url, const char *prefix, const char *token, const char *ssl_cert_file,
		const char *ssl_key_file, const char *config_source_ip, const char *config_ssl_ca_location,
		const char *config_ssl_cert_location, const char *config_ssl_key_location, const char *path,
		long timeout, zbx_kvs_t *kvs, char **error)
{
#ifndef HAVE_LIBCURL
	ZBX_UNUSED(vault_url);
	ZBX_UNUSED(prefix);
	ZBX_UNUSED(token);
	ZBX_UNUSED(ssl_cert_file);
	ZBX_UNUSED(ssl_key_file);
	ZBX_UNUSED(path);
	ZBX_UNUSED(timeout);
	ZBX_UNUSED(config_source_ip);
	ZBX_UNUSED(config_ssl_ca_location);
	ZBX_UNUSED(config_ssl_cert_location);
	ZBX_UNUSED(config_ssl_key_location);
	ZBX_UNUSED(kvs);
	*error = zbx_dsprintf(*error, "missing cURL library");
	return FAIL;
#else
	char			*out = NULL, *url, header[MAX_STRING_LEN], *left, *right;
	struct zbx_json_parse	jp, jp_data, jp_data_data;
	int			ret = FAIL;
	long			response_code;

	if (NULL == token)
	{
		*error = zbx_dsprintf(*error, "\"VaultToken\" configuration parameter or \"VAULT_TOKEN\" environment"
				" variable should be defined");
		return FAIL;
	}

	if (NULL == prefix || '\0' == *prefix)
	{
		zbx_strsplit_first(path, '/', &left, &right);

		if (NULL == right)
		{
			*error = zbx_dsprintf(*error, "cannot find separator \"\\\" in path");
			free(left);
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

	if (SUCCEED != zbx_json_brackets_by_name(&jp, "data", &jp_data))
	{
		*error = zbx_dsprintf(*error, "cannot find the \"%s\" object in the received JSON object.",
				ZBX_PROTO_TAG_DATA);
		goto fail;
	}

	if (SUCCEED != zbx_json_brackets_by_name(&jp_data, "data", &jp_data_data))
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

void	zbx_hashicorp_renew_token(const char *vault_url, const char *token, const char *ssl_cert_file,
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
	char			*out = NULL, *error = NULL, header[MAX_STRING_LEN], *urlstat = NULL, *value = NULL;
	static char		*errormsg = "cannot renew vault token:";
	size_t			value_alloc = 0;
	struct zbx_json_parse	jp, jp_data;
	long			response_code;
	static int		renew_possible = 1;
	static double		mtime = 0;

	if (NULL == token)
		return;

	zbx_snprintf(header, sizeof(header), "X-Vault-Token: %s", token);

	if (mtime == 0)
	{
		urlstat = zbx_dsprintf(NULL, "%s%s", vault_url, "v1/auth/token/lookup-self");

		if (SUCCEED != zbx_http_req(urlstat, header, timeout, ssl_cert_file, ssl_key_file, config_source_ip,
				config_ssl_ca_location, config_ssl_cert_location, config_ssl_key_location, &out, NULL,
				&response_code, &error))
		{
			zabbix_log(LOG_LEVEL_WARNING, "%s %s", errormsg, error);
			zbx_free(error);
			goto out;
		}

		if (200 != response_code && 204 != response_code)
		{
			zabbix_log(LOG_LEVEL_WARNING, "%s unsuccessful response code \"%ld\"", errormsg, response_code);
			goto out;
		}
		mtime = zbx_time();

		if (SUCCEED != zbx_json_open(out, &jp))
		{
			zabbix_log(LOG_LEVEL_WARNING, "%s %s", errormsg, zbx_json_strerror());
			goto out;
		}

		if (SUCCEED != zbx_json_brackets_by_name(&jp, ZBX_PROTO_TAG_DATA, &jp_data))
		{
			zabbix_log(LOG_LEVEL_WARNING, "%s cannot find the \"%s\" object in the received JSON object",
					errormsg, ZBX_PROTO_TAG_DATA);
			goto out;
		}

		if (SUCCEED != zbx_json_value_by_name_dyn(&jp_data, "renewable", &value, &value_alloc, NULL) ||
				0 != strcmp(value, "true"))
		{
			renew_possible = 0;
			zabbix_log(LOG_LEVEL_WARNING, "%s token is not renewable", errormsg);
			goto out;
		}
		zbx_free(urlstat);
		zbx_free(out);
	}

	if (0 != renew_possible && zbx_time() >= mtime)
	{
		urlstat = zbx_dsprintf(NULL, "%s%s", vault_url, "v1/auth/token/renew-self");

		if (SUCCEED != zbx_http_req(urlstat, header, timeout, ssl_cert_file, ssl_key_file,
				config_source_ip, config_ssl_ca_location, config_ssl_cert_location,
				config_ssl_key_location, &out, "{}",  &response_code, &error))
		{
			zabbix_log(LOG_LEVEL_WARNING, "%s %s", errormsg, error);
			zbx_free(error);
			goto out;
		}

		if (200 != response_code && 204 != response_code)
		{
			zabbix_log(LOG_LEVEL_WARNING, "%s unsuccessful response code \"%ld\"", errormsg, response_code);
			goto out;
		}

		if (SUCCEED != zbx_json_open(out, &jp))
		{
			zabbix_log(LOG_LEVEL_WARNING, "%s %s", errormsg, zbx_json_strerror());
			goto out;
		}

		if (SUCCEED != zbx_json_brackets_by_name(&jp, ZBX_PROTO_TAG_AUTH, &jp_data))
		{
			zabbix_log(LOG_LEVEL_WARNING, "%s cannot find the \"%s\" object in the received JSON object",
				errormsg, ZBX_PROTO_TAG_AUTH);
			goto out;
		}

		if (SUCCEED == zbx_json_value_by_name_dyn(&jp_data, "lease_duration", &value, &value_alloc, NULL))
		{
			mtime = zbx_time() + atoi(value) * 2 / 3;
		}
		else
		{
			zabbix_log(LOG_LEVEL_WARNING, "%s \"lease_duration\" not found in the received JSON object",
				errormsg);
		}

		zabbix_log(LOG_LEVEL_DEBUG, "Vault token renewed");
	}

out:
	zbx_free(value);
	zbx_free(urlstat);
	zbx_free(out);
#endif
}

