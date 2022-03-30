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

#include "kvs.h"
#include "log.h"

static zbx_hash_t	zbx_kv_hash(const void *data)
{
	const zbx_kv_t	*kv = (const zbx_kv_t *)data;

	return ZBX_DEFAULT_STRING_HASH_ALGO(kv->key, strlen(kv->key), ZBX_DEFAULT_HASH_SEED);
}

static int	zbx_kv_compare(const void *d1, const void *d2)
{
	return strcmp(((const zbx_kv_t *)d1)->key, ((const zbx_kv_t *)d2)->key);
}

static void	zbx_kv_clean(void *data)
{
	zbx_kv_t	*kv = (zbx_kv_t *)data;

	zbx_free(kv->key);
	zbx_free(kv->value);
}

void	zbx_kvs_create(zbx_kvs_t *kvs, size_t init_size)
{
	zbx_hashset_create_ext(kvs, init_size, zbx_kv_hash, zbx_kv_compare, zbx_kv_clean,
			ZBX_DEFAULT_MEM_MALLOC_FUNC, ZBX_DEFAULT_MEM_REALLOC_FUNC, ZBX_DEFAULT_MEM_FREE_FUNC);
}

void	zbx_kvs_clear(zbx_kvs_t *kvs)
{
	zbx_hashset_clear(kvs);
}

void	zbx_kvs_destroy(zbx_kvs_t *kvs)
{
	zbx_hashset_destroy(kvs);
}

zbx_kv_t	*zbx_kvs_search(zbx_kvs_t *kvs, const zbx_kv_t *data)
{
	return zbx_hashset_search(kvs, data);
}

void	zbx_kvs_from_json_get(const struct zbx_json_parse *jp_kvs, zbx_kvs_t *kvs)
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
			zabbix_log(LOG_LEVEL_DEBUG, "key '%s' is defined multiple times", key);
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

int	zbx_kvs_from_json_by_path_get(const char *path, const struct zbx_json_parse *jp_kvs_paths, zbx_kvs_t *kvs,
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

		zbx_kvs_from_json_get(&jp_kvs, kvs);
		return SUCCEED;
	}
	else
	{
		*error = zbx_strdup(*error, "no data");
		return FAIL;
	}
}
