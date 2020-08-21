/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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

extern char	*CONFIG_VAULTTOKEN;
extern char	*CONFIG_VAULTURL;
extern char	*CONFIG_VAULTDBPATH;

extern char	*CONFIG_DBUSER;
extern char	*CONFIG_DBPASSWORD;

static zbx_hash_t	kv_hash(const void *data)
{
	zbx_kv_t	*kv = (zbx_kv_t *)data;

	return ZBX_DEFAULT_STRING_HASH_ALGO(kv->key, strlen(kv->key), ZBX_DEFAULT_HASH_SEED);
}

static int	kv_compare(const void *d1, const void *d2)
{
	return strcmp(((zbx_kv_t *)d1)->key, ((zbx_kv_t *)d2)->key);
}

static void	kv_clean(void *data)
{
	zbx_kv_t	*kv = (zbx_kv_t *)data;

	zbx_free(kv->key);

	zbx_guaranteed_memset(kv->value, 0, strlen(kv->value));
	zbx_free(kv->value);
}

static void	get_kvs_from_json(const struct zbx_json_parse *jp_kvs, zbx_hashset_t *kvs)
{
	char		tmp[MAX_STRING_LEN], *string = NULL;
	const char	*pnext = NULL;
	size_t	string_alloc = 0;

	while (NULL != (pnext = zbx_json_pair_next(jp_kvs, pnext, tmp, sizeof(tmp))))
	{
		zbx_kv_t	kv_local;

		kv_local.key = tmp;
		if (NULL != (zbx_hashset_search(kvs, &kv_local)))
			continue;

		if (NULL == zbx_json_decodevalue_dyn(pnext, &string, &string_alloc, NULL))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "invalid tag value starting with %s", pnext);
			continue;
		}

		kv_local.key = zbx_strdup(NULL, tmp);
		kv_local.value = string;
		string = NULL;
		string_alloc = 0;
		zbx_hashset_insert(kvs, &kv_local, sizeof(zbx_kv_t));
	}
}

int	zbx_kvs_from_vault_create(const char *path, zbx_hashset_t *kvs, char **error)
{
	char			*out = NULL, tmp[MAX_STRING_LEN], header[MAX_STRING_LEN], *left, *right;
	struct zbx_json_parse	jp, jp_data, jp_data_data;
	int			ret = FAIL, ret_get;

	if (NULL == CONFIG_VAULTTOKEN)
	{
		*error = zbx_dsprintf(*error, "cannot retrieve secrets, token must be defined");
		return FAIL;
	}

	zbx_strsplit(path, '/', &left, &right);
	zbx_snprintf(tmp, sizeof(tmp), "%s/v1/%s/data/%s", CONFIG_VAULTURL, left, right);
	zbx_free(right);
	zbx_free(left);

	zbx_snprintf(header, sizeof(header), "X-Vault-Token: %s", CONFIG_VAULTTOKEN);

	ret_get = zbx_http_get(tmp, header, &out, error);
	zbx_guaranteed_memset(header, 0, sizeof(header));

	if (SUCCEED != ret_get)
		goto fail;

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

	zbx_hashset_create_ext(kvs, 100, kv_hash, kv_compare, kv_clean, ZBX_DEFAULT_MEM_MALLOC_FUNC,
				ZBX_DEFAULT_MEM_REALLOC_FUNC, ZBX_DEFAULT_MEM_FREE_FUNC);
	get_kvs_from_json(&jp_data_data, kvs);

	ret = SUCCEED;
fail:
	if (NULL != out)
		zbx_guaranteed_memset(out, 0, strlen(out));
	zbx_free(out);

	return ret;
}

void	zbx_kvs_from_vault_destroy(zbx_hashset_t *kvs)
{
	zbx_hashset_destroy(kvs);
}

int	init_database_credentials_from_vault(char **error)
{
	int		ret = FAIL;
	zbx_hashset_t	kvs;
	zbx_kv_t	*kv_username, *kv_password, kv_local;

	if (NULL == CONFIG_VAULTDBPATH)
		return SUCCEED;

	if (NULL != CONFIG_DBUSER)
	{
		*error = zbx_dsprintf(*error,
				"cannot retrieve database user name, both DBName and VaultDBPath are defined");
		return FAIL;
	}

	if (NULL != CONFIG_DBPASSWORD)
	{
		*error = zbx_dsprintf(*error,
				"cannot retrieve database password, both DBPassword and VaultDBPath are defined");
		return FAIL;
	}

	if (SUCCEED != zbx_kvs_from_vault_create(CONFIG_VAULTDBPATH, &kvs, error))
		return FAIL;

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

	CONFIG_DBUSER = zbx_strdup(NULL, kv_username->value);		/* TODO encrypt */
	CONFIG_DBPASSWORD = zbx_strdup(NULL, kv_password->value);	/* TODO encrypt */

	ret = SUCCEED;
fail:
	zbx_kvs_from_vault_destroy(&kvs);

	return ret;
}

