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

#include "hashicorp.h"
#include "zbxjson.h"
#include "zbxhttp.h"
#include "zbxvault.h"
#include "../zbxkvs/kvs.h"

int	zbx_hashicorp_kvs_get(const char *path, zbx_hashset_t *kvs, char *vault_url, char *token, long timeout,
		char **error)
{
#ifndef HAVE_LIBCURL
	ZBX_UNUSED(path);
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

	zbx_strsplit(path, '/', &left, &right);
	if (NULL == right)
	{
		*error = zbx_dsprintf(*error, "cannot find separator \"\\\" in path");
		free(left);
		return FAIL;
	}
	url = zbx_dsprintf(NULL, "%s/v1/%s/data/%s", vault_url, left, right);
	zbx_free(right);
	zbx_free(left);

	zbx_snprintf(header, sizeof(header), "X-Vault-Token: %s", token);

	if (SUCCEED != zbx_http_get(url, header, timeout, &out, &response_code, error))
		goto fail;

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

	zbx_kvs_json_parse(&jp_data_data, kvs);

	ret = SUCCEED;
fail:
	zbx_free(url);
	zbx_free(out);

	return ret;
#endif
}

int	zbx_hashicorp_init_db_credentials(char *vault_url, char *token, long timeout, const char *db_path,
		char **dbuser, char **dbpassword, char **error)
{
	int		ret = FAIL;
	zbx_hashset_t	kvs;
	zbx_kv_t	*kv_username, *kv_password, kv_local;

	zbx_hashset_create_ext(&kvs, 2, zbx_kv_hash, zbx_kv_compare, zbx_kv_clean,
			ZBX_DEFAULT_MEM_MALLOC_FUNC, ZBX_DEFAULT_MEM_REALLOC_FUNC, ZBX_DEFAULT_MEM_FREE_FUNC);

	if (SUCCEED != zbx_hashicorp_kvs_get(db_path, &kvs, vault_url, token, timeout, error))
		goto fail;

	kv_local.key = ZBX_PROTO_TAG_USERNAME;

	if (NULL == (kv_username = (zbx_kv_t *)zbx_hashset_search(&kvs, &kv_local)))
	{
		*error = zbx_dsprintf(*error, "cannot retrieve value of key \"%s\"", ZBX_PROTO_TAG_USERNAME);
		goto fail;
	}

	kv_local.key = ZBX_PROTO_TAG_PASSWORD;

	if (NULL == (kv_password = (zbx_kv_t *)zbx_hashset_search(&kvs, &kv_local)))
	{
		*error = zbx_dsprintf(*error, "cannot retrieve value of key \"%s\"", ZBX_PROTO_TAG_PASSWORD);
		goto fail;
	}

	*dbuser = zbx_strdup(NULL, kv_username->value);
	*dbpassword = zbx_strdup(NULL, kv_password->value);

	ret = SUCCEED;
fail:
	zbx_hashset_destroy(&kvs);

	return ret;
}
