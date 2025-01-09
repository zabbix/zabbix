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

#include "user_macro.h"
#include "dbsync.h"
#include "dbconfig.h"

#include "zbxdb.h"
#include "zbxjson.h"
#include "zbxvault.h"
#include "zbxstr.h"
#include "zbxexpr.h"
#include "zbxregexp.h"
#include "zbxnum.h"
#include "zbx_expression_constants.h"

ZBX_PTR_VECTOR_IMPL(um_macro, zbx_um_macro_t *)
ZBX_PTR_VECTOR_IMPL(um_host, zbx_um_host_t *)

#define ZBX_MACRO_NO_KVS_VALUE	STR_UNKNOWN_VARIABLE

typedef enum
{
	ZBX_UM_UPDATE_HOST,
	ZBX_UM_UPDATE_MACRO
}
zbx_um_update_cause_t;

/*********************************************************************************
 *                                                                               *
 * Purpose: create duplicate user macro cache                                    *
 *                                                                               *
 * Parameters:  cache - [IN] the user macro cache to duplicate                   *
 *                                                                               *
 * Return value: The duplicated user macro cache.                                *
 *                                                                               *
 * Comments: The internal structures are duplicated copying references of        *
 *           cached objects and incrementing their reference counters.           *
 *                                                                               *
 *********************************************************************************/
static zbx_um_cache_t	*um_cache_dup(zbx_um_cache_t *cache)
{
	zbx_um_cache_t		*dup;
	zbx_um_host_t		**phost;
	zbx_hashset_iter_t	iter;

	dup = (zbx_um_cache_t *)dbconfig_shmem_malloc_func(NULL, sizeof(zbx_um_cache_t));
	dup->refcount = 1;

	zbx_hashset_copy(&dup->hosts, &cache->hosts, sizeof(zbx_um_host_t *));
	zbx_hashset_iter_reset(&dup->hosts, &iter);
	while (NULL != (phost = (zbx_um_host_t **)zbx_hashset_iter_next(&iter)))
		(*phost)->refcount++;

	return dup;
}

/* macro sorting */

static int	um_macro_compare_by_name_context(const void *d1, const void *d2)
{
	const zbx_um_macro_t	*m1 = *(const zbx_um_macro_t * const *)d1;
	const zbx_um_macro_t	*m2 = *(const zbx_um_macro_t * const *)d2;
	int		ret;

	if (0 != (ret = strcmp(m1->name, m2->name)))
		return ret;

	/* ZBX_CONDITION_OPERATOR_EQUAL (0) has higher priority than ZBX_CONDITION_OPERATOR_REGEXP (8) */
	ZBX_RETURN_IF_NOT_EQUAL(m1->context_op, m2->context_op);

	return zbx_strcmp_null(m1->context, m2->context);
}

/* host hashset support */

static zbx_hash_t	um_host_hash(const void *d)
{
	const zbx_um_host_t	*host = *(const zbx_um_host_t * const *)d;

	return ZBX_DEFAULT_UINT64_HASH_FUNC(&host->hostid);
}

static int	um_host_compare(const void *d1, const void *d2)
{
	const zbx_um_host_t	*h1 = *(const zbx_um_host_t * const *)d1;
	const zbx_um_host_t	*h2 = *(const zbx_um_host_t * const *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(h1->hostid, h2->hostid);

	return 0;
}

/* user macro hashset support */

zbx_hash_t	um_macro_hash(const void *d)
{
	const zbx_um_macro_t	*macro = *(const zbx_um_macro_t * const *)d;

	return ZBX_DEFAULT_UINT64_HASH_FUNC(&macro->macroid);
}

int	um_macro_compare(const void *d1, const void *d2)
{
	const zbx_um_macro_t	*m1 = *(const zbx_um_macro_t * const *)d1;
	const zbx_um_macro_t	*m2 = *(const zbx_um_macro_t * const *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(m1->macroid, m2->macroid);

	return 0;
}

/*********************************************************************************
 *                                                                               *
 * Purpose: create user macro cache                                              *
 *                                                                               *
 *********************************************************************************/
zbx_um_cache_t	*um_cache_create(void)
{
	zbx_um_cache_t	*cache;

	cache = (zbx_um_cache_t *)dbconfig_shmem_malloc_func(NULL, sizeof(zbx_um_cache_t));
	cache->refcount = 1;
	cache->revision = 0;
	zbx_hashset_create_ext(&cache->hosts, 10, um_host_hash, um_host_compare, NULL,
			dbconfig_shmem_malloc_func, dbconfig_shmem_realloc_func, dbconfig_shmem_free_func);

	return cache;
}

/*********************************************************************************
 *                                                                               *
 * Purpose: release user macro                                                   *
 *                                                                               *
 *********************************************************************************/
void	um_macro_release(zbx_um_macro_t *macro)
{
	if (0 != --macro->refcount)
		return;

	dc_strpool_release(macro->name);
	if (NULL != macro->context)
		dc_strpool_release(macro->context);
	if (NULL != macro->value)
		dc_strpool_release(macro->value);

	dbconfig_shmem_free_func(macro);
}

/*********************************************************************************
 *                                                                               *
 * Purpose: release user macro host                                              *
 *                                                                               *
 *********************************************************************************/
static void	um_host_release(zbx_um_host_t *host)
{
	int	i;

	if (0 != --host->refcount)
		return;

	for (i = 0; i < host->macros.values_num; i++)
		um_macro_release(host->macros.values[i]);
	zbx_vector_um_macro_destroy(&host->macros);

	zbx_vector_uint64_destroy(&host->templateids);
	dbconfig_shmem_free_func(host);
}

/*********************************************************************************
 *                                                                               *
 * Purpose: release user macro cache                                             *
 *                                                                               *
 *********************************************************************************/
void	um_cache_release(zbx_um_cache_t *cache)
{
	zbx_um_host_t		**host;
	zbx_hashset_iter_t	iter;

	if (0 != --cache->refcount)
		return;

	zbx_hashset_iter_reset(&cache->hosts, &iter);
	while (NULL != (host = (zbx_um_host_t **)zbx_hashset_iter_next(&iter)))
		um_host_release(*host);
	zbx_hashset_destroy(&cache->hosts);

	dbconfig_shmem_free_func(cache);
}

/*********************************************************************************
 *                                                                               *
 * Purpose: duplicate user macro                                                 *
 *                                                                               *
 *********************************************************************************/
static zbx_um_macro_t	*um_macro_dup(zbx_um_macro_t *macro)
{
	zbx_um_macro_t	*dup;

	dup = (zbx_um_macro_t *)dbconfig_shmem_malloc_func(NULL, sizeof(zbx_um_macro_t));
	dup->macroid = macro->macroid;
	dup->hostid = macro->hostid;
	dup->name = dc_strpool_acquire(macro->name);
	dup->context = dc_strpool_acquire(macro->context);
	dup->value = dc_strpool_acquire(macro->value);
	dup->type = macro->type;
	dup->context_op = macro->context_op;
	dup->refcount = 1;

	return dup;
}

/*********************************************************************************
 *                                                                               *
 * Purpose: duplicate user macro host                                            *
 *                                                                               *
 * Comment: macro references are copied over with incremented reference counters.*
 *                                                                               *
 *********************************************************************************/
static zbx_um_host_t	*um_host_dup(zbx_um_host_t *host)
{
	zbx_um_host_t	*dup;
	int		i;

	dup = (zbx_um_host_t *)dbconfig_shmem_malloc_func(NULL, sizeof(zbx_um_host_t));
	dup->hostid = host->hostid;
	dup->refcount = 1;
	dup->macro_revision = host->macro_revision;
	dup->link_revision = host->link_revision;

	zbx_vector_uint64_create_ext(&dup->templateids, dbconfig_shmem_malloc_func, dbconfig_shmem_realloc_func,
			dbconfig_shmem_free_func);

	if (0 != host->templateids.values_num)
	{
		zbx_vector_uint64_append_array(&dup->templateids, host->templateids.values,
				host->templateids.values_num);
	}

	zbx_vector_um_macro_create_ext(&dup->macros, dbconfig_shmem_malloc_func, dbconfig_shmem_realloc_func,
			dbconfig_shmem_free_func);

	if (0 != host->macros.values_num)
		zbx_vector_um_macro_append_array(&dup->macros, host->macros.values, host->macros.values_num);

	for (i = 0; i < host->macros.values_num; i++)
		host->macros.values[i]->refcount++;

	return dup;
}

/* checks if object is locked and cannot be updated */

static int	um_macro_is_locked(const zbx_um_macro_t *macro)
{
	/* macro is referred by cache and host, so refcount 2 means nothing else is using it */
	return 2 != macro->refcount ? SUCCEED : FAIL;
}

static int	um_macro_has_host(const zbx_um_macro_t *macro)
{
	/* if macro is referred only by cache it does not belong to any host */
	return 1 != macro->refcount ? SUCCEED : FAIL;
}

static int	um_host_is_locked(const zbx_um_host_t *host)
{
	return 1 != host->refcount ? SUCCEED : FAIL;
}

static int	um_cache_is_locked(const zbx_um_cache_t *cache)
{
	return 1 != cache->refcount ? SUCCEED : FAIL;
}

/*********************************************************************************
 *                                                                               *
 * Purpose: create user macro host                                               *
 *                                                                               *
 *********************************************************************************/
static zbx_um_host_t	*um_cache_create_host(zbx_um_cache_t *cache, zbx_uint64_t hostid)
{
	zbx_um_host_t	*host;

	host = (zbx_um_host_t *)dbconfig_shmem_malloc_func(NULL, sizeof(zbx_um_host_t));
	host->hostid = hostid;
	host->refcount = 1;
	host->macro_revision = cache->revision;
	host->link_revision = cache->revision;
	zbx_vector_uint64_create_ext(&host->templateids, dbconfig_shmem_malloc_func, dbconfig_shmem_realloc_func,
			dbconfig_shmem_free_func);
	zbx_vector_um_macro_create_ext(&host->macros, dbconfig_shmem_malloc_func, dbconfig_shmem_realloc_func,
			dbconfig_shmem_free_func);

	zbx_hashset_insert(&cache->hosts, &host, sizeof(host));

	return host;
}

/*********************************************************************************
 *                                                                               *
 * Purpose: acquire user macro host for update                                   *
 *                                                                               *
 * Comments: If the host is used by other processes it will be duplicated.       *
 *                                                                               *
 *********************************************************************************/
static zbx_um_host_t	*um_cache_acquire_host(zbx_um_cache_t *cache, zbx_uint64_t hostid,
		zbx_um_update_cause_t cause)
{
	zbx_uint64_t	*phostid = &hostid;
	zbx_um_host_t	**phost;

	if (NULL != (phost = (zbx_um_host_t **)zbx_hashset_search(&cache->hosts, &phostid)))
	{
		if (SUCCEED == um_host_is_locked(*phost))
		{
			um_host_release(*phost);
			*phost = um_host_dup(*phost);
		}

		/* hosts are acquired when there are changes to be made, */
		/* meaning host revision must be updated                 */
		switch (cause)
		{
			case ZBX_UM_UPDATE_HOST:
				(*phost)->link_revision = cache->revision;
				break;
			case ZBX_UM_UPDATE_MACRO:
				(*phost)->macro_revision = cache->revision;
				break;
		}

		return *phost;

	}

	return NULL;
}

/*********************************************************************************
 *                                                                               *
 * Purpose: remove user macro from the host                                      *
 *                                                                               *
 *********************************************************************************/
static void	um_host_remove_macro(zbx_um_host_t *host, zbx_um_macro_t *macro)
{
	int	i;

	for (i = 0; i < host->macros.values_num; i++)
	{
		if (macro->macroid == host->macros.values[i]->macroid)
		{
			um_macro_release(host->macros.values[i]);
			zbx_vector_um_macro_remove_noorder(&host->macros, i);
			break;
		}
	}
}

/* user macro cache sync */

#define ZBX_UM_MACRO_UPDATE_HOSTID	0x0001
#define ZBX_UM_MACRO_UPDATE_NAME	0x0002
#define ZBX_UM_MACRO_UPDATE_CONTEXT	0x0004
#define ZBX_UM_MACRO_UPDATE_VALUE	0x0008

#define ZBX_UM_INDEX_UPDATE	(ZBX_UM_MACRO_UPDATE_HOSTID | ZBX_UM_MACRO_UPDATE_NAME)

static int	dc_compare_kvs_path(const void *d1, const void *d2)
{
	const zbx_dc_kvs_path_t	*ptr1 = *((const zbx_dc_kvs_path_t * const *)d1);
	const zbx_dc_kvs_path_t	*ptr2 = *((const zbx_dc_kvs_path_t * const *)d2);

	return strcmp(ptr1->path, ptr2->path);
}

static zbx_hash_t	dc_kv_hash(const void *data)
{
	return ZBX_DEFAULT_STRING_HASH_FUNC(((const zbx_dc_kv_t *)data)->key);
}

static int	dc_kv_compare(const void *d1, const void *d2)
{
	return strcmp(((const zbx_dc_kv_t *)d1)->key, ((const zbx_dc_kv_t *)d2)->key);
}

/*********************************************************************************
 *                                                                               *
 * Purpose: remove kvs path from configuration cache                             *
 *                                                                               *
 *********************************************************************************/
static void	dc_kvs_path_remove(zbx_dc_kvs_path_t *kvs_path)
{
	zbx_dc_kvs_path_t	kvs_path_local;
	int			i;

	kvs_path_local.path = kvs_path->path;

	if (FAIL != (i = zbx_vector_ptr_search(&(get_dc_config())->kvs_paths, &kvs_path_local, dc_compare_kvs_path)))
		zbx_vector_ptr_remove_noorder(&(get_dc_config())->kvs_paths, i);

	zbx_hashset_destroy(&kvs_path->kvs);
	dc_strpool_release(kvs_path->path);

	dbconfig_shmem_free_func(kvs_path);
}

/*********************************************************************************
 *                                                                               *
 * Purpose: remove macro from key-value storage, releasing unused storage        *
 *          elements when necessary                                              *
 *                                                                               *
 *********************************************************************************/
static void	um_macro_kv_remove(zbx_um_macro_t *macro, zbx_dc_macro_kv_t *mkv)
{
	int			i;
	zbx_uint64_pair_t	pair = {macro->hostid, macro->macroid};

	if (FAIL != (i = zbx_vector_uint64_pair_search(&mkv->kv->macros, pair, ZBX_DEFAULT_UINT64_PAIR_COMPARE_FUNC)))
	{
		zbx_vector_uint64_pair_remove_noorder(&mkv->kv->macros, i);
		if (0 == mkv->kv->macros.values_num)
		{
			zbx_vector_uint64_pair_destroy(&mkv->kv->macros);
			dc_strpool_release(mkv->kv->key);
			if (NULL != mkv->kv->value)
				dc_strpool_release(mkv->kv->value);

			zbx_hashset_remove_direct(&mkv->kv_path->kvs, mkv->kv);
			if (0 == mkv->kv_path->kvs.num_data)
				dc_kvs_path_remove(mkv->kv_path);
		}
	}
}

/*********************************************************************************
 *                                                                               *
 * Purpose: register vault macro in key value storage                            *
 *                                                                               *
 *********************************************************************************/
static void	um_macro_register_kvs(zbx_um_macro_t *macro, const char *location,
		const zbx_config_vault_t *config_vault, unsigned char program_type)
{
	zbx_dc_kvs_path_t	*kvs_path, kvs_path_local;
	zbx_dc_kv_t		*kv, kv_local;
	int			i;
	zbx_uint64_pair_t	pair = {macro->hostid, macro->macroid};
	char			*path, *key;
	zbx_dc_config_t		*config = get_dc_config();
	zbx_hashset_t		*macro_kv = (0 == macro->hostid ? &config->gmacro_kv :
				&(get_dc_config())->hmacro_kv);
	zbx_dc_macro_kv_t	*mkv;

	zbx_strsplit_last(location, ':', &path, &key);

	if (NULL == key)
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot parse host \"" ZBX_FS_UI64 "\" macro \"" ZBX_FS_UI64 "\""
				" Vault location \"%s\": missing separator \":\"",
				macro->hostid, macro->macroid, location);
		goto out;
	}

	if (0 != (program_type & ZBX_PROGRAM_TYPE_SERVER) && NULL != config_vault->db_path &&
			0 == strcasecmp(config_vault->db_path, path) &&
			(0 == strcasecmp(key, ZBX_PROTO_TAG_PASSWORD)
					|| 0 == strcasecmp(key, ZBX_PROTO_TAG_USERNAME)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot parse host \"" ZBX_FS_UI64 "\" macro \"" ZBX_FS_UI64 "\""
				" Vault location \"%s\": database credentials should not be used with Vault macros",
				macro->hostid, macro->macroid, location);

		zbx_free(path);
		zbx_free(key);

		goto out;
	}

	kvs_path_local.path = path;

	if (FAIL == (i = zbx_vector_ptr_search(&config->kvs_paths, &kvs_path_local, dc_compare_kvs_path)))
	{
		kvs_path = (zbx_dc_kvs_path_t *)dbconfig_shmem_malloc_func(NULL, sizeof(zbx_dc_kvs_path_t));
		kvs_path->path = dc_strpool_intern(path);
		zbx_hashset_create_ext(&kvs_path->kvs, 0, dc_kv_hash, dc_kv_compare, NULL,
				dbconfig_shmem_malloc_func, dbconfig_shmem_realloc_func, dbconfig_shmem_free_func);

		zbx_vector_ptr_append(&config->kvs_paths, kvs_path);
		kv = NULL;
	}
	else
	{
		kvs_path = (zbx_dc_kvs_path_t *)config->kvs_paths.values[i];
		kv_local.key = key;
		kv = (zbx_dc_kv_t *)zbx_hashset_search(&kvs_path->kvs, &kv_local);
	}

	if (NULL == kv)
	{
		kv_local.key = dc_strpool_intern(key);
		kv_local.value = NULL;
		zbx_vector_uint64_pair_create_ext(&kv_local.macros, dbconfig_shmem_malloc_func,
				dbconfig_shmem_realloc_func, dbconfig_shmem_free_func);

		kv = (zbx_dc_kv_t *)zbx_hashset_insert(&kvs_path->kvs, &kv_local, sizeof(zbx_dc_kv_t));
	}

	if (NULL != (mkv = zbx_hashset_search(macro_kv, &macro->macroid)))
	{
		/* no kvs location changes - skip updates */
		if (mkv->kv == kv)
			goto out;

		/* remove from old kv location */
		um_macro_kv_remove(macro, mkv);

		if (NULL != macro->value)
		{
			dc_strpool_release(macro->value);
			macro->value = NULL;
		}
	}
	else
	{
		zbx_dc_macro_kv_t	mkv_local;

		mkv_local.macroid = macro->macroid;
		mkv = (zbx_dc_macro_kv_t *)zbx_hashset_insert(macro_kv, &mkv_local, sizeof(mkv_local));
	}

	kv->update = 1;
	mkv->kv = kv;
	mkv->kv_path = kvs_path;
	zbx_vector_uint64_pair_append(&kv->macros, pair);
out:
	zbx_free(path);
	zbx_free(key);
}

/*********************************************************************************
 *                                                                               *
 * Purpose: deregister vault macro from key value storage, releasing unused      *
 *          storage elements when necessary                                      *
 *                                                                               *
 *********************************************************************************/
static void	um_macro_deregister_kvs(zbx_um_macro_t *macro)
{
	zbx_hashset_t	*macro_kv = (0 == macro->hostid ? &(get_dc_config())->gmacro_kv : &(get_dc_config())->hmacro_kv);

	zbx_dc_macro_kv_t	*mkv;

	if (NULL != (mkv = zbx_hashset_search(macro_kv, &macro->macroid)))
	{
		um_macro_kv_remove(macro, mkv);
		zbx_hashset_remove_direct(macro_kv, mkv);
	}
}

/*********************************************************************************
 *                                                                               *
 * Purpose: check if the macro vault location has not changed                    *
 *                                                                               *
 *********************************************************************************/
int	um_macro_check_vault_location(const zbx_um_macro_t *macro, const char *location)
{
	zbx_dc_config_t		*config = get_dc_config();
	zbx_hashset_t		*macro_kv = (0 == macro->hostid ? &config->gmacro_kv : &config->hmacro_kv);
	zbx_dc_macro_kv_t	*mkv;
	char			*path, *key;
	int			i, ret = FAIL;
	zbx_dc_kvs_path_t	*kvs_path, kvs_path_local;
	zbx_dc_kv_t		*kv, kv_local;

	if (NULL == (mkv = zbx_hashset_search(macro_kv, &macro->macroid)))
		return FAIL;

	zbx_strsplit_first(location, ':', &path, &key);

	kvs_path_local.path = path;
	if (FAIL == (i = zbx_vector_ptr_search(&config->kvs_paths, &kvs_path_local, dc_compare_kvs_path)))
		goto out;

	kvs_path = (zbx_dc_kvs_path_t *)config->kvs_paths.values[i];
	kv_local.key = key;

	kv = (zbx_dc_kv_t *)zbx_hashset_search(&kvs_path->kvs, &kv_local);
	if (kv == mkv->kv)
		ret = SUCCEED;
out:
	zbx_free(path);
	zbx_free(key);

	return ret;
}

/*********************************************************************************
 *                                                                               *
 * Purpose: sync global/host user macros                                         *
 *                                                                               *
 * Parameters: cache        - [IN] user macro cache                              *
 *             sync         - [IN] synchronization object containing inserted,   *
 *                            updated and deleted rows                           *
 *             offset       - [IN] macro column offset in row                    *
 *             config_vault - [IN]                                               *
 *             program_type - [IN]                                               *
 *                                                                               *
 *********************************************************************************/
static void	um_cache_sync_macros(zbx_um_cache_t *cache, zbx_dbsync_t *sync, int offset,
		const zbx_config_vault_t *config_vault, unsigned char program_type)
{
	unsigned char		tag;
	int			ret, i;
	zbx_uint64_t		rowid, macroid, hostid = ZBX_UM_CACHE_GLOBAL_MACRO_HOSTID, *pmacroid = &macroid;
	char			**row;
	zbx_um_macro_t		**pmacro;
	zbx_um_host_t		*host;
	zbx_vector_um_host_t	hosts;
	zbx_hashset_t		*user_macros;

	zbx_dcsync_sync_start(sync, dbconfig_used_size());

	user_macros = (2 == offset ? &(get_dc_config())->hmacros : &(get_dc_config())->gmacros);

	zbx_vector_um_host_create(&hosts);

	while (SUCCEED == (ret = zbx_dbsync_next(sync, &rowid, &row, &tag)))
	{
		char		*name = NULL, *context = NULL;
		const char	*dc_name, *dc_context, *dc_value;
		unsigned char	context_op, type;
		zbx_um_macro_t	*macro;

		/* removed rows will be always at the end of sync list */
		if (ZBX_DBSYNC_ROW_REMOVE == tag)
			break;

		if (SUCCEED != zbx_user_macro_parse_dyn(row[offset], &name, &context, NULL, &context_op))
		{
			if (2 == offset)
				zabbix_log(LOG_LEVEL_WARNING, "cannot parse host \"%s\" macro \"%s\"", row[1], row[2]);
			else
				zabbix_log(LOG_LEVEL_WARNING, "cannot parse global macro \"%s\"", row[1]);
			continue;
		}

		ZBX_STR2UINT64(macroid, row[0]);

		dc_name = dc_strpool_intern(name);
		dc_context = dc_strpool_intern(context);
		zbx_free(name);
		zbx_free(context);

		if (2 == offset)
			ZBX_STR2UINT64(hostid, row[1]);

		ZBX_STR2UCHAR(type, row[offset + 2]);

		/* acquire new value before releasing old value to avoid value being */
		/*  removed and added back to string pool if it was not changed      */
		if (ZBX_MACRO_VALUE_VAULT != type)
			dc_value = dc_strpool_intern(row[offset + 1]);

		if (NULL != (pmacro = (zbx_um_macro_t **)zbx_hashset_search(user_macros, &pmacroid)))
		{
			host = um_cache_acquire_host(cache, (*pmacro)->hostid, ZBX_UM_UPDATE_MACRO);

			if (SUCCEED == um_macro_is_locked(*pmacro))
			{
				if (NULL != host)
				{
					/* remove old macro from host so a new (duped) one can be added later */
					um_host_remove_macro(host, *pmacro);
					zbx_vector_um_host_append(&hosts, host);
				}

				um_macro_release(*pmacro);
				*pmacro = um_macro_dup(*pmacro);
			}

			if ((*pmacro)->hostid != hostid)
			{
				if (SUCCEED == um_macro_has_host(*pmacro) && NULL != host)
				{
					/* remove macro from old host */
					um_host_remove_macro(host, *pmacro);
					zbx_vector_um_host_append(&hosts, host);
				}

				/* acquire new host */
				host = um_cache_acquire_host(cache, hostid, ZBX_UM_UPDATE_MACRO);
			}

			dc_strpool_release((*pmacro)->name);
			if (NULL != (*pmacro)->context)
				dc_strpool_release((*pmacro)->context);

			if (ZBX_MACRO_VALUE_VAULT != (*pmacro)->type)
			{
				/* release macro value if it was not stored in vault */
				dc_strpool_release((*pmacro)->value);
				(*pmacro)->value = NULL;
			}
			else
			{
				if (ZBX_MACRO_VALUE_VAULT != type)
					um_macro_deregister_kvs(*pmacro);
			}
		}
		else
		{
			macro = (zbx_um_macro_t *)dbconfig_shmem_malloc_func(NULL, sizeof(zbx_um_macro_t));
			macro->macroid = macroid;
			macro->refcount = 1;
			macro->value = NULL;
			pmacro = zbx_hashset_insert(user_macros, &macro, sizeof(macro));

			host = um_cache_acquire_host(cache, hostid, ZBX_UM_UPDATE_MACRO);
		}

		(*pmacro)->hostid = hostid;
		(*pmacro)->name = dc_name;
		(*pmacro)->context = dc_context;
		(*pmacro)->type = type;
		(*pmacro)->context_op = context_op;

		if (ZBX_MACRO_VALUE_VAULT == type)
			um_macro_register_kvs(*pmacro, row[offset + 1], config_vault, program_type);
		else
			(*pmacro)->value = dc_value;

		if (NULL == host)
			host = um_cache_create_host(cache, hostid);

		/* append created macros to host */
		if (1 == (*pmacro)->refcount)
		{
			(*pmacro)->refcount++;
			zbx_vector_um_macro_append(&host->macros, *pmacro);
		}

		zbx_vector_um_host_append(&hosts, host);
	}

	/* handle removed macros */
	for (macroid = rowid; SUCCEED == ret; ret = zbx_dbsync_next(sync, &macroid, &row, &tag))
	{
		if (NULL == (pmacro = (zbx_um_macro_t **)zbx_hashset_search(user_macros, &pmacroid)))
			continue;

		if (ZBX_MACRO_VALUE_VAULT == (*pmacro)->type)
			um_macro_deregister_kvs(*pmacro);

		if (NULL != (host = um_cache_acquire_host(cache, (*pmacro)->hostid, ZBX_UM_UPDATE_MACRO)))
		{
			um_host_remove_macro(host, *pmacro);
			zbx_vector_um_host_append(&hosts, host);
		}
		um_macro_release(*pmacro);
		zbx_hashset_remove_direct(user_macros, pmacro);
	}

	/* sort macros, remove unused hosts */

	zbx_vector_um_host_sort(&hosts, ZBX_DEFAULT_PTR_COMPARE_FUNC);
	zbx_vector_um_host_uniq(&hosts, ZBX_DEFAULT_PTR_COMPARE_FUNC);

	for (i = 0; i < hosts.values_num; i++)
	{
		if (0 == hosts.values[i]->macros.values_num)
		{
			/* recreate empty-macros vector to release memory */
			zbx_vector_um_macro_destroy(&hosts.values[i]->macros);
			zbx_vector_um_macro_create_ext(&hosts.values[i]->macros, dbconfig_shmem_malloc_func,
					dbconfig_shmem_realloc_func, dbconfig_shmem_free_func);
		}
		else
			zbx_vector_um_macro_sort(&hosts.values[i]->macros, um_macro_compare_by_name_context);
	}

	zbx_vector_um_host_destroy(&hosts);

	zbx_dcsync_sync_end(sync, dbconfig_used_size());
}

/*********************************************************************************
 *                                                                               *
 * Purpose: sync host-template links                                             *
 *                                                                               *
 *********************************************************************************/
static void	um_cache_sync_hosts(zbx_um_cache_t *cache, zbx_dbsync_t *sync)
{
	unsigned char	tag;
	int		ret;
	zbx_uint64_t	rowid, hostid, templateid;
	char		**row;
	zbx_um_host_t	*host;

	while (SUCCEED == (ret = zbx_dbsync_next(sync, &rowid, &row, &tag)))
	{
		/* removed rows will be always at the end of sync list */
		if (ZBX_DBSYNC_ROW_REMOVE == tag)
			break;

		ZBX_STR2UINT64(hostid, row[0]);

		if (NULL == (host = um_cache_acquire_host(cache, hostid, ZBX_UM_UPDATE_HOST)))
			host = um_cache_create_host(cache, hostid);

		ZBX_DBROW2UINT64(templateid, row[1]);
		zbx_vector_uint64_append(&host->templateids, templateid);
	}

	/* handle removed host template links */
	for (; SUCCEED == ret; ret = zbx_dbsync_next(sync, &rowid, &row, &tag))
	{
		int	i;

		ZBX_STR2UINT64(hostid, row[0]);

		if (NULL == (host = um_cache_acquire_host(cache, hostid, ZBX_UM_UPDATE_HOST)))
			continue;

		ZBX_DBROW2UINT64(templateid, row[1]);

		for (i = 0; i < host->templateids.values_num; i++)
		{
			if (host->templateids.values[i] == templateid)
			{
				zbx_vector_uint64_remove_noorder(&host->templateids, i);
				if (0 == host->templateids.values_num)
				{
					zbx_vector_uint64_destroy(&host->templateids);
					zbx_vector_uint64_create_ext(&host->templateids, dbconfig_shmem_malloc_func,
							dbconfig_shmem_realloc_func, dbconfig_shmem_free_func);
				}
				break;
			}
		}
	}
}

/*********************************************************************************
 *                                                                               *
 * Purpose: sync user macro cache                                                *
 *                                                                               *
 *********************************************************************************/
zbx_um_cache_t	*um_cache_sync(zbx_um_cache_t *cache, zbx_uint64_t revision, zbx_dbsync_t *gmacros,
		zbx_dbsync_t *hmacros, zbx_dbsync_t *htmpls, const zbx_config_vault_t *config_vault,
		unsigned char program_type)
{
	if (ZBX_DBSYNC_INIT != gmacros->mode && ZBX_DBSYNC_INIT != hmacros->mode && ZBX_DBSYNC_INIT != htmpls->mode &&
			0 == gmacros->rows.values_num && 0 == hmacros->rows.values_num && 0 == htmpls->rows.values_num)
	{
		return cache;
	}

	if (SUCCEED == um_cache_is_locked(cache))
	{
		um_cache_release(cache);
		cache = um_cache_dup(cache);
	}

	cache->revision = revision;

	um_cache_sync_macros(cache, gmacros, 1, config_vault, program_type);
	um_cache_sync_macros(cache, hmacros, 2, config_vault, program_type);
	um_cache_sync_hosts(cache, htmpls);

	return cache;
}

#define ZBX_UM_MATCH_FAIL	(-1)
#define ZBX_UM_MATCH_FULL	0
#define ZBX_UM_MATCH_NAME	1

/*********************************************************************************
 *                                                                               *
 * Purpose: match user macro against macro name and context                      *
 *                                                                               *
 *********************************************************************************/
static int	um_macro_match(const zbx_um_macro_t *macro, const char *name, const char *context)
{
	if (0 != strcmp(macro->name, name))
		return ZBX_UM_MATCH_FAIL;

	if (NULL == macro->context)
		return NULL == context ? ZBX_UM_MATCH_FULL : ZBX_UM_MATCH_NAME;

	if (NULL != context)
	{
		switch (macro->context_op)
		{
			case ZBX_CONDITION_OPERATOR_EQUAL:
				if (0 == strcmp(macro->context, context))
					return ZBX_UM_MATCH_FULL;
				break;
			case ZBX_CONDITION_OPERATOR_REGEXP:
				if (NULL != zbx_regexp_match(context, macro->context, NULL))
					return ZBX_UM_MATCH_FULL;
				break;
			default:
				THIS_SHOULD_NEVER_HAPPEN;
				break;
		}
	}

	return ZBX_UM_MATCH_NAME;
}

/*********************************************************************************
 *                                                                               *
 * Purpose: get user macro from host                                             *
 *                                                                               *
 * Parameters: host    - [IN] the host                                           *
 *             name    - [IN] the macro name ({$NAME})                           *
 *             context - [IN] the macro context                                  *
 *             macro   - [OUT] the macro                                         *
 *                                                                               *
 * Return value: SUCCEED - the macro with specified context was found            *
 *               FAIL    - the macro was not found, but value will contain base  *
 *                         macro value if context was specified and base macro   *
 *                         was found                                             *
 *                                                                               *
 *********************************************************************************/
static int	um_host_get_macro(const zbx_um_host_t *host, const char *name, const char *context,
		const zbx_um_macro_t **macro)
{
	zbx_um_macro_t	macro_local;
	int		i, ret;

	if (0 == host->macros.values_num)
		return FAIL;

	macro_local.name = name;
	macro_local.context = NULL;
	macro_local.context_op = 0;

	i = zbx_vector_um_macro_nearestindex(&host->macros, &macro_local, um_macro_compare_by_name_context);

	if (i >= host->macros.values_num)
		return FAIL;

	for (; i < host->macros.values_num; i++)
	{
		if (ZBX_UM_MATCH_FAIL == (ret = um_macro_match(host->macros.values[i], name, context)))
			return FAIL;

		if (ZBX_UM_MATCH_FULL == ret)
		{
			*macro = host->macros.values[i];
			return SUCCEED;
		}

		if (NULL == host->macros.values[i]->context && NULL == *macro)
			*macro = host->macros.values[i];
	}

	return FAIL;
}

/*********************************************************************************
 *                                                                               *
 * Purpose: get user macro in the scope of specified hosts                       *
 *                                                                               *
 * Return value: SUCCEED - the macro with specified context was found            *
 *               FAIL    - the macro was not found, but value will contain base  *
 *                         macro value if context was specified and base macro   *
 *                         was found                                             *
 *                                                                               *
 *********************************************************************************/
static int	um_cache_get_host_macro(const zbx_um_cache_t *cache, const zbx_uint64_t *hostids, int hostids_num,
		const char *name, const char *context, const zbx_um_macro_t **macro)
{
	int			i, ret = SUCCEED;
	zbx_vector_uint64_t	templateids;
	const zbx_um_host_t	* const *phost;

	zbx_vector_uint64_create(&templateids);

	for (i = 0; i < hostids_num; i++)
	{
		const zbx_uint64_t	*phostid = &hostids[i];

		if (NULL != (phost = (const zbx_um_host_t * const *)zbx_hashset_search(&cache->hosts, &phostid)))
		{
			if (SUCCEED == um_host_get_macro(*phost, name, context, macro))
				goto out;

			zbx_vector_uint64_append_array(&templateids, (*phost)->templateids.values,
					(*phost)->templateids.values_num);
		}
	}

	if (0 != templateids.values_num)
		ret = um_cache_get_host_macro(cache, templateids.values, templateids.values_num, name, context, macro);
	else
		ret = FAIL;
out:
	zbx_vector_uint64_destroy(&templateids);

	return ret;
}

/*********************************************************************************
 *                                                                               *
 * Purpose: get user macro (host/global)                                         *
 *                                                                               *
 * Parameters: cache       - [IN] the user macro cache                           *
 *             hostids     - [IN] the host identifiers                           *
 *             hostids_num - [IN] the number of host identifiers                 *
 *             macro       - [IN] the macro with optional context                *
 *             um_macro    - [OUT] the cached macro                              *
 *                                                                               *
 *********************************************************************************/
static void	um_cache_get_macro(const zbx_um_cache_t *cache, const zbx_uint64_t *hostids, int hostids_num,
		const char *macro, const zbx_um_macro_t **um_macro)
{
	char		*name = NULL, *context = NULL;
	unsigned char	context_op;

	if (SUCCEED != zbx_user_macro_parse_dyn(macro, &name, &context, NULL, &context_op))
		return;

	/* User macros should be expanded according to the following priority: */
	/*                                                                     */
	/*  1) host context macro                                              */
	/*  2) global context macro                                            */
	/*  3) host base (default) macro                                       */
	/*  4) global base (default) macro                                     */
	/*                                                                     */
	/* We try to expand host macros first. If there is no perfect match on */
	/* the host level, we try to expand global macros, passing the default */
	/* macro value found on the host level, if any.                        */

	if (SUCCEED != um_cache_get_host_macro(cache, hostids, hostids_num, name, context, um_macro))
	{
		zbx_uint64_t	hostid = ZBX_UM_CACHE_GLOBAL_MACRO_HOSTID;

		um_cache_get_host_macro(cache, &hostid, 1, name, context, um_macro);
	}

	zbx_free(name);
	zbx_free(context);
}

/*********************************************************************************
 *                                                                               *
 * Purpose: resolve user macro (host/global)                                     *
 *                                                                               *
 * Parameters: cache       - [IN] the user macro cache                           *
 *             hostids     - [IN] the host identifiers                           *
 *             hostids_num - [IN] the number of host identifiers                 *
 *             macro       - [IN] the macro with optional context                *
 *             env         - [IN] the environment flag:                          *
 *                                  0 - secure                                   *
 *                                  1 - non-secure (secure macros are resolved   *
 *                                                  to ***** )                   *
 *             value       - [OUT] macro value, must not be freed by the caller  *
 *                                                                               *
 *********************************************************************************/
void	um_cache_resolve_const(const zbx_um_cache_t *cache, const zbx_uint64_t *hostids, int hostids_num,
		const char *macro, int env, const char **value)
{
	const zbx_um_macro_t	*um_macro = NULL;

	um_cache_get_macro(cache, hostids, hostids_num, macro, &um_macro);

	if (NULL != um_macro)
	{
		if (ZBX_MACRO_ENV_NONSECURE == env && ZBX_MACRO_VALUE_TEXT != um_macro->type)
			*value = ZBX_MACRO_SECRET_MASK;
		else
			*value = (NULL != um_macro->value ? um_macro->value : ZBX_MACRO_NO_KVS_VALUE);
	}
}

/*********************************************************************************
 *                                                                               *
 * Purpose: resolve user macro (host/global)                                     *
 *                                                                               *
 * Parameters: cache       - [IN] the user macro cache                           *
 *             hostids     - [IN] the host identifiers                           *
 *             hostids_num - [IN] the number of host identifiers                 *
 *             macro       - [IN] the macro with optional context                *
 *             env         - [IN] the environment flag:                          *
 *                                  0 - secure                                   *
 *                                  1 - non-secure (secure macros are resolved   *
 *                                                  to ***** )                   *
 *             value       - [OUT] macro value, must be freed by the caller      *
 *                                                                               *
 *********************************************************************************/
void	um_cache_resolve(const zbx_um_cache_t *cache, const zbx_uint64_t *hostids, int hostids_num, const char *macro,
		int env, char **value)
{
	const zbx_um_macro_t	*um_macro = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() macro:'%s'", __func__, macro);

	um_cache_get_macro(cache, hostids, hostids_num, macro, &um_macro);

	if (NULL != um_macro)
	{
		if (ZBX_MACRO_ENV_NONSECURE == env && ZBX_MACRO_VALUE_TEXT != um_macro->type)
			*value = zbx_strdup(*value, ZBX_MACRO_SECRET_MASK);
		else
			*value = zbx_strdup(NULL, (NULL != um_macro->value ? um_macro->value : ZBX_MACRO_NO_KVS_VALUE));
	}

	if (SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_DEBUG))
	{
		const char	*out = NULL;

		if (NULL != um_macro)
		{
			if (ZBX_MACRO_VALUE_TEXT == um_macro->type)
				out = um_macro->value;
			else
				out = (NULL == um_macro->value ? ZBX_MACRO_SECRET_MASK : ZBX_MACRO_NO_KVS_VALUE);
		}

		zabbix_log(LOG_LEVEL_DEBUG, "End of %s(): %s", __func__, ZBX_NULL2EMPTY_STR(out));
	}
}

/*********************************************************************************
 *                                                                               *
 * Purpose: set value to the specified macros                                    *
 *                                                                               *
 * Parameters: cache          - [IN] the user macro cache                        *
 *             revision       - [IN] the configuration revision                  *
 *             host_macro_ids - [IN] a vector of hostid,macroid pairs            *
 *             value          - [IN] the new value (stored in string pool)       *
 *                                                                               *
 *********************************************************************************/
zbx_um_cache_t	*um_cache_set_value_to_macros(zbx_um_cache_t *cache, zbx_uint64_t revision,
		const zbx_vector_uint64_pair_t *host_macro_ids, const char *value)
{
	int			i;
	zbx_vector_um_host_t	hosts;

	zbx_vector_um_host_create(&hosts);

	if (SUCCEED == um_cache_is_locked(cache))
	{
		um_cache_release(cache);
		cache = um_cache_dup(cache);
	}

	cache->revision = revision;

	for (i = 0; i < host_macro_ids->values_num; i++)
	{
		zbx_um_macro_t		**pmacro;
		zbx_uint64_pair_t	*pair = &host_macro_ids->values[i];
		zbx_hashset_t		*user_macros = (0 != pair->first ? &(get_dc_config())->hmacros :
					&(get_dc_config())->gmacros);
		zbx_uint64_t		*pmacroid = &pair->second;
		zbx_um_host_t		*host;

		if (NULL == (pmacro = (zbx_um_macro_t **)zbx_hashset_search(user_macros, &pmacroid)))
			continue;

		if (NULL == (host = um_cache_acquire_host(cache, (*pmacro)->hostid, ZBX_UM_UPDATE_MACRO)))
			continue;

		if (SUCCEED == um_macro_is_locked(*pmacro))
		{
			um_host_remove_macro(host, *pmacro);
			um_macro_release(*pmacro);
			*pmacro = um_macro_dup(*pmacro);

			(*pmacro)->refcount++;
			zbx_vector_um_macro_append(&host->macros, *pmacro);
		}

		if (NULL != (*pmacro)->value)
			dc_strpool_release((*pmacro)->value);

		(*pmacro)->value = dc_strpool_acquire(value);

		zbx_vector_um_host_append(&hosts, host);
	}

	zbx_vector_um_host_sort(&hosts, ZBX_DEFAULT_PTR_COMPARE_FUNC);
	zbx_vector_um_host_uniq(&hosts, ZBX_DEFAULT_PTR_COMPARE_FUNC);

	for (i = 0; i < hosts.values_num; i++)
		zbx_vector_um_macro_sort(&hosts.values[i]->macros, um_macro_compare_by_name_context);

	zbx_vector_um_host_destroy(&hosts);

	return cache;
}

void	um_cache_dump(zbx_um_cache_t *cache)
{
	zbx_hashset_iter_t	iter;
	zbx_um_host_t		**phost;
	zbx_vector_uint64_t	ids;
	int			i;

	zabbix_log(LOG_LEVEL_TRACE, "In %s() hosts:%d refcount:%u revision:" ZBX_FS_UI64, __func__,
			cache->hosts.num_data, cache->refcount, cache->revision);

	zbx_vector_uint64_create(&ids);

	zbx_hashset_iter_reset(&cache->hosts, &iter);
	while (NULL != (phost = (zbx_um_host_t **)zbx_hashset_iter_next(&iter)))
	{
		zabbix_log(LOG_LEVEL_TRACE, "hostid:" ZBX_FS_UI64 " refcount:%u link_revision:" ZBX_FS_UI64
				" macro_revision:" ZBX_FS_UI64, (*phost)->hostid,
				(*phost)->refcount, (*phost)->link_revision, (*phost)->macro_revision);

		zabbix_log(LOG_LEVEL_TRACE, "  macros:");

		for (i = 0; i < (*phost)->macros.values_num; i++)
		{
			zbx_um_macro_t	*macro = (*phost)->macros.values[i];
			const char	*value;

			switch (macro->type)
			{
				case ZBX_MACRO_VALUE_SECRET:
				case ZBX_MACRO_VALUE_VAULT:
					value = ZBX_MACRO_SECRET_MASK;
					break;
				default:
					value = macro->value;
					break;
			}

			zabbix_log(LOG_LEVEL_TRACE, "    macroid:" ZBX_FS_UI64 " name:'%s' context:'%s' op:'%u'"
					" value:'%s' type:%u refcount:%u", macro->macroid, macro->name,
					ZBX_NULL2EMPTY_STR(macro->context), macro->context_op,
					ZBX_NULL2EMPTY_STR(value), macro->type, macro->refcount);
		}

		if (0 != (*phost)->templateids.values_num)
		{
			zbx_vector_uint64_append_array(&ids, (*phost)->templateids.values,
					(*phost)->templateids.values_num);
			zbx_vector_uint64_sort(&ids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

			zabbix_log(LOG_LEVEL_TRACE, "  templateids:");

			for (i = 0; i < ids.values_num; i++)
				zabbix_log(LOG_LEVEL_TRACE, "    " ZBX_FS_UI64, ids.values[i]);

			zbx_vector_uint64_clear(&ids);
		}
	}

	zbx_vector_uint64_destroy(&ids);

	zabbix_log(LOG_LEVEL_TRACE, "End of %s()", __func__);
}

int	um_cache_get_host_revision(const zbx_um_cache_t *cache, zbx_uint64_t hostid, zbx_uint64_t *revision)
{
	const zbx_um_host_t	* const *phost;
	int			i;
	zbx_uint64_t		*phostid = &hostid;

	if (NULL == (phost = (const zbx_um_host_t * const *)zbx_hashset_search(&cache->hosts, &phostid)))
		return FAIL;

	if ((*phost)->macro_revision > *revision)
		*revision = (*phost)->macro_revision;

	for (i = 0; i < (*phost)->templateids.values_num; i++)
		um_cache_get_host_revision(cache, (*phost)->templateids.values[i], revision);

	return SUCCEED;
}

static void	um_cache_get_hosts(const zbx_um_cache_t *cache, const zbx_uint64_t *phostid, zbx_uint64_t revision,
		zbx_vector_um_host_t *hosts)
{
	zbx_um_host_t	**phost;
	int		i;

	if (NULL == (phost = (zbx_um_host_t **)zbx_hashset_search(&cache->hosts, &phostid)))
		return;

	/* if host-template linking has changed, force macro update for all children */
	if ((*phost)->link_revision > revision)
		revision = 0;

	if ((*phost)->macro_revision > revision || (*phost)->link_revision > revision)
		zbx_vector_um_host_append(hosts, *phost);

	for (i = 0; i < (*phost)->templateids.values_num; i++)
		um_cache_get_hosts(cache, &(*phost)->templateids.values[i], revision, hosts);
}

/*********************************************************************************
 *                                                                               *
 * Purpose: get identifiers of user macro host objects that were updated since   *
 *          the specified revision                                               *
 *                                                                               *
 * Parameters: cache             - [IN] the user macro cache                     *
 *             hostids           - [IN] identifiers of the hosts to check        *
 *             hostids_num       - [IN] the number of hosts to check             *
 *             revision          - [IN] the revision                             *
 *             macro_hostids     - [OUT] the identifiers of updated host objects *
 *             del_macro_hostids - [OUT] the identifiers of cleared host objects *
 *                                  (without macros or linked templates),        *
 *                                  optional                                     *
 *                                                                               *
 *********************************************************************************/
void	um_cache_get_macro_updates(const zbx_um_cache_t *cache, const zbx_uint64_t *hostids, int hostids_num,
		zbx_uint64_t revision, zbx_vector_uint64_t *macro_hostids, zbx_vector_uint64_t *del_macro_hostids)
{
	int			i;
	zbx_vector_um_host_t	hosts;

	zbx_vector_um_host_create(&hosts);

	for (i = 0; i < hostids_num; i++)
		um_cache_get_hosts(cache, &hostids[i], revision, &hosts);

	if (0 != hosts.values_num)
	{
		zbx_vector_um_host_sort(&hosts, ZBX_DEFAULT_PTR_COMPARE_FUNC);
		zbx_vector_um_host_uniq(&hosts, ZBX_DEFAULT_PTR_COMPARE_FUNC);

		for (i = 0; i < hosts.values_num; i++)
		{
			if (0 != hosts.values[i]->macros.values_num || 0 != hosts.values[i]->templateids.values_num)
				zbx_vector_uint64_append(macro_hostids, hosts.values[i]->hostid);
			else
				zbx_vector_uint64_append(del_macro_hostids, hosts.values[i]->hostid);
		}
	}

	zbx_vector_um_host_destroy(&hosts);
}

/*********************************************************************************
 *                                                                               *
 * Purpose: recursively remove templates linked to the hostid from unused_hosts  *
 *          the specified revision                                               *
 *                                                                               *
 * Parameters: cache        - [IN] the user macro cache                          *
 *             hostid       - [IN] the parent hostid                             *
 *             templates    - [IN/OUT] the leftover (not linked to hosts)        *
 *                                     templates                                 *
 *                                                                               *
 *********************************************************************************/
static void	um_cache_check_used_templates(const zbx_um_cache_t *cache, zbx_uint64_t hostid,
		zbx_hashset_t *templates)
{
	void		*data;
	zbx_um_host_t	**phost;
	zbx_uint64_t	*phostid = &hostid;
	int		i;

	if (NULL != (data = zbx_hashset_search(templates, &hostid)))
		zbx_hashset_remove_direct(templates, data);

	if (NULL == (phost = (zbx_um_host_t **)zbx_hashset_search(&cache->hosts, &phostid)))
		return;

	for (i = 0; i < (*phost)->templateids.values_num; i++)
		um_cache_check_used_templates(cache, (*phost)->templateids.values[i], templates);
}

/*********************************************************************************
 *                                                                               *
 * Purpose: get identifiers of templates not linked to the specified hosts       *
 *          neither directly nor through other templates                         *
 *                                                                               *
 * Parameters: cache       - [IN] the user macro cache                           *
 *             templates   - [IN] the database templates                         *
 *             hostids     - [IN] the database hosts                             *
 *             templateids - [IN/OUT] the templates not linked to any host       *
 *                                     neither directly nor through other        *
 *                                     templates                                 *
 *                                                                               *
 *********************************************************************************/
void	um_cache_get_unused_templates(zbx_um_cache_t *cache, zbx_hashset_t *templates,
		const zbx_vector_uint64_t *hostids, zbx_vector_uint64_t *templateids)
{
	zbx_hashset_iter_t	iter;
	int			i;
	zbx_uint64_t		*phostid;

	for (i = 0; i < hostids->values_num; i++)
		um_cache_check_used_templates(cache, hostids->values[i], templates);

	zbx_hashset_iter_reset(templates, &iter);
	while (NULL != (phostid = (zbx_uint64_t *)zbx_hashset_iter_next(&iter)))
		zbx_vector_uint64_append(templateids, *phostid);
}

/*********************************************************************************
 *                                                                               *
 * Purpose: remove deleted hosts/templates from user macro cache                 *
 *                                                                               *
 * Parameters: cache   - [IN] the user macro cache                               *
 *             hostids - [IN] the deleted host/template identifiers              *
 *                                                                               *
 *********************************************************************************/
void	um_cache_remove_hosts(zbx_um_cache_t *cache, const zbx_vector_uint64_t *hostids)
{
	zbx_um_host_t	**phost;
	int		i;

	for (i = 0; i < hostids->values_num; i++)
	{
		zbx_uint64_t	*phostid = &hostids->values[i];

		if (NULL != (phost = (zbx_um_host_t **)zbx_hashset_search(&cache->hosts, &phostid)))
		{
			zbx_hashset_remove_direct(&cache->hosts, phost);
			um_host_release(*phost);
		}
	}
}
