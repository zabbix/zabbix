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

#include "common.h"
#include "zbxjson.h"
#include "zbxvault.h"
#include "zbxhttp.h"
#include "zbxalgo.h"

#include "log.h"

#define ZBX_VAULT_TIMEOUT	SEC_PER_MIN

extern char	*CONFIG_VAULTTOKEN;
extern char	*CONFIG_VAULTURL;
extern char	*CONFIG_VAULTDBPATH;

extern char	*CONFIG_DBUSER;
extern char	*CONFIG_DBPASSWORD;

zbx_hash_t	zbx_vault_kv_hash(const void *data)
{
	zbx_kv_t	*kv = (zbx_kv_t *)data;

	return ZBX_DEFAULT_STRING_HASH_ALGO(kv->key, strlen(kv->key), ZBX_DEFAULT_HASH_SEED);
}

int	zbx_vault_kv_compare(const void *d1, const void *d2)
{
	return strcmp(((zbx_kv_t *)d1)->key, ((zbx_kv_t *)d2)->key);
}

void	zbx_vault_kv_clean(void *data)
{
	zbx_kv_t	*kv = (zbx_kv_t *)data;

	zbx_free(kv->key);
	zbx_free(kv->value);
}

static void	vault_json_kvs_parse(const struct zbx_json_parse *jp_kvs, zbx_hashset_t *kvs)
{
	char		key[MAX_STRING_LEN], *value = NULL;
	const char	*pnext = NULL;
	size_t		string_alloc = 0;

	while (NULL != (pnext = zbx_json_pair_next(jp_kvs, pnext, key, sizeof(key))))
	{
		zbx_kv_t	kv_local;

		kv_local.key = key;
		if (NULL != (zbx_hashset_search(kvs, &kv_local)))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "key '%s' is multiply defined", key);
			continue;
		}

		if (NULL == zbx_json_decodevalue_dyn(pnext, &value, &string_alloc, NULL))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "invalid tag value starting with %s", pnext);
			continue;
		}

		kv_local.key = zbx_strdup(NULL, key);
		kv_local.value = value;
		value = NULL;
		string_alloc = 0;
		zbx_hashset_insert(kvs, &kv_local, sizeof(zbx_kv_t));
	}

	zbx_free(value);
}

int	zbx_vault_json_kvs_get(const char *path, const struct zbx_json_parse *jp_kvs_paths, zbx_hashset_t *kvs,
		char **error)
{
	const char		*p;
	struct zbx_json_parse	jp_kvs;

	if (NULL != (p = zbx_json_pair_by_name(jp_kvs_paths, path)))
	{
		if (FAIL == zbx_json_brackets_open(p, &jp_kvs))
		{
			*error = zbx_strdup(*error, zbx_json_strerror());
			return FAIL;
		}

		vault_json_kvs_parse(&jp_kvs, kvs);
		return SUCCEED;
	}
	else
	{
		*error = zbx_strdup(*error, "no data");
		return FAIL;
	}
}

int	zbx_vault_kvs_get(const char *path, zbx_hashset_t *kvs, char **error)
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

	if (NULL == CONFIG_VAULTTOKEN)
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
	url = zbx_dsprintf(NULL, "%s/v1/%s/data/%s", CONFIG_VAULTURL, left, right);
	zbx_free(right);
	zbx_free(left);

	zbx_snprintf(header, sizeof(header), "X-Vault-Token: %s", CONFIG_VAULTTOKEN);

	if (SUCCEED != zbx_http_get(url, header, ZBX_VAULT_TIMEOUT, &out, &response_code, error))
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

	vault_json_kvs_parse(&jp_data_data, kvs);

	ret = SUCCEED;
fail:
	zbx_free(url);
	zbx_free(out);

	return ret;
#endif
}

int	zbx_vault_init_token_from_env(char **error)
{
#if defined(HAVE_GETENV) && defined(HAVE_UNSETENV)
	char	*ptr;

	if (NULL == (ptr = getenv("VAULT_TOKEN")))
		return SUCCEED;

	if (NULL != CONFIG_VAULTTOKEN)
	{
		*error = zbx_dsprintf(*error, "both \"VaultToken\" configuration parameter"
				" and \"VAULT_TOKEN\" environment variable are defined");
		return FAIL;
	}

	CONFIG_VAULTTOKEN = zbx_strdup(NULL, ptr);
	unsetenv("VAULT_TOKEN");
#endif
	return SUCCEED;
}

int	zbx_vault_init_db_credentials(char **error)
{
	int		ret = FAIL;
	zbx_hashset_t	kvs;
	zbx_kv_t	*kv_username, *kv_password, kv_local;

	if (NULL == CONFIG_VAULTDBPATH)
		return SUCCEED;

	if (NULL != CONFIG_DBUSER)
	{
		*error = zbx_dsprintf(*error, "\"DBUser\" configuration parameter cannot be used when \"VaultDBPath\""
				" is defined");
		return FAIL;
	}

	if (NULL != CONFIG_DBPASSWORD)
	{
		*error = zbx_dsprintf(*error, "\"DBPassword\" configuration parameter cannot be used when"
				" \"VaultDBPath\" is defined");
		return FAIL;
	}

	zbx_hashset_create_ext(&kvs, 2, zbx_vault_kv_hash, zbx_vault_kv_compare, zbx_vault_kv_clean,
			ZBX_DEFAULT_MEM_MALLOC_FUNC, ZBX_DEFAULT_MEM_REALLOC_FUNC, ZBX_DEFAULT_MEM_FREE_FUNC);

	if (SUCCEED != zbx_vault_kvs_get(CONFIG_VAULTDBPATH, &kvs, error))
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

	CONFIG_DBUSER = zbx_strdup(NULL, kv_username->value);
	CONFIG_DBPASSWORD = zbx_strdup(NULL, kv_password->value);

	ret = SUCCEED;
fail:
	zbx_hashset_destroy(&kvs);

	return ret;
}
