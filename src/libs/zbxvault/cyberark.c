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

#include "cyberark.h"

#ifdef HAVE_LIBCURL
#	include "zbxhttp.h"
#endif

#include "zbxjson.h"

int	zbx_cyberark_kvs_get(const char *vault_url, const char *prefix, const char *token, const char *ssl_cert_file,
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
	ZBX_UNUSED(config_source_ip);
	ZBX_UNUSED(config_ssl_ca_location);
	ZBX_UNUSED(config_ssl_cert_location);
	ZBX_UNUSED(config_ssl_key_location);
	ZBX_UNUSED(path);
	ZBX_UNUSED(timeout);
	ZBX_UNUSED(kvs);

	*error = zbx_dsprintf(*error, "missing cURL library");
	return FAIL;
#else
	char			*out = NULL, *url;
	struct zbx_json_parse	jp, jp_data;
	int			ret = FAIL;
	long			response_code;

	ZBX_UNUSED(token);

	if (NULL == prefix || '\0' == *prefix)
		prefix = "/AIMWebService/api/Accounts?";

	url = zbx_dsprintf(NULL, "%s%s%s", vault_url, prefix, path);

	if (SUCCEED != zbx_http_req(url, "Content-Type: application/json", timeout, ssl_cert_file, ssl_key_file,
			config_source_ip, config_ssl_ca_location, config_ssl_cert_location, config_ssl_key_location,
			&out, NULL, &response_code, error))
	{
		goto fail;
	}

	if (200 != response_code)
	{
		*error = zbx_dsprintf(*error, "unsuccessful response code \"%ld\"", response_code);
		goto fail;
	}

	if (SUCCEED != zbx_json_open(out, &jp))
	{
		*error = zbx_dsprintf(*error, "cannot parse secrets from vault: %s", zbx_json_strerror());
		goto fail;
	}

	if (SUCCEED != zbx_json_brackets_open(out, &jp_data))
	{
		*error = zbx_dsprintf(*error, "cannot parse secrets from vault: %s", zbx_json_strerror());
		goto fail;
	}

	zbx_kvs_from_json_get(&jp_data, kvs);

	ret = SUCCEED;
fail:
	zbx_free(url);
	zbx_free(out);

	return ret;
#endif
}
