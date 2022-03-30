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
#include "zbxalgo.h"
#include "memalloc.h"
#include "zbxalgo.h"
#include "../zbxalgo/vectorimpl.h"
#include "dbsync.h"
#include "dbconfig.h"
#include "user_macro.h"

extern zbx_mem_info_t	*config_mem;
ZBX_MEM_FUNC_IMPL(__config, config_mem)

ZBX_PTR_VECTOR_IMPL(um_macro, zbx_um_macro_t *)

ZBX_PTR_VECTOR_DECL(um_host, zbx_um_host_t *)
ZBX_PTR_VECTOR_IMPL(um_host, zbx_um_host_t *)

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

	dup = (zbx_um_cache_t *)__config_mem_malloc_func(NULL, sizeof(zbx_um_cache_t));
	dup->refcount = 1;

	zbx_hashset_copy(&dup->hosts, &cache->hosts, sizeof(zbx_um_host_t *));
	zbx_hashset_iter_reset(&dup->hosts, &iter);
	while (NULL != (phost = (zbx_um_host_t **)zbx_hashset_iter_next(&iter)))
		(*phost)->refcount++;

	return dup;
}

/* macro sorting */

static int	um_macro_compare_by_name(const void *d1, const void *d2)
{
	const zbx_um_macro_t	*m1 = *(const zbx_um_macro_t * const *)d1;
	const zbx_um_macro_t	*m2 = *(const zbx_um_macro_t * const *)d2;
	int		ret;

	if (0 != (ret = strcmp(m1->name, m2->name)))
		return ret;

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
zbx_um_cache_t	*um_cache_create()
{
	zbx_um_cache_t	*cache;

	cache = (zbx_um_cache_t *)__config_mem_malloc_func(NULL, sizeof(zbx_um_cache_t));
	cache->refcount = 1;
	zbx_hashset_create(&cache->hosts, 10, um_host_hash, um_host_compare);

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
	dc_strpool_release(macro->value);

	__config_mem_free_func(macro);
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
	__config_mem_free_func(host);
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

	__config_mem_free_func(cache);
}

/*********************************************************************************
 *                                                                               *
 * Purpose: duplicate user macro                                                 *
 *                                                                               *
 *********************************************************************************/
static zbx_um_macro_t	*um_macro_dup(zbx_um_macro_t *macro)
{
	zbx_um_macro_t	*dup;

	dup = (zbx_um_macro_t *)__config_mem_malloc_func(NULL, sizeof(zbx_um_macro_t));
	dup->macroid = macro->macroid;
	dup->hostid = macro->hostid;
	dup->name = dc_strpool_acquire(macro->name);
	dup->context = (NULL != macro->context ? dc_strpool_acquire(macro->context) : NULL);
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
static zbx_um_host_t *um_host_dup(zbx_um_host_t *host)
{
	zbx_um_host_t	*dup;
	int		i;

	dup = (zbx_um_host_t *)__config_mem_malloc_func(NULL, sizeof(zbx_um_host_t));
	dup->hostid = host->hostid;
	dup->refcount = 1;

	zbx_vector_uint64_create_ext(&dup->templateids, __config_mem_malloc_func, __config_mem_realloc_func,
			__config_mem_free_func);
	zbx_vector_uint64_append_array(&dup->templateids, host->templateids.values, host->templateids.values_num);

	zbx_vector_um_macro_create_ext(&dup->macros, __config_mem_malloc_func, __config_mem_realloc_func,
			__config_mem_free_func);
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

	host = (zbx_um_host_t *)__config_mem_malloc_func(NULL, sizeof(zbx_um_host_t));
	host->hostid = hostid;
	host->refcount = 1;
	zbx_vector_uint64_create_ext(&host->templateids, __config_mem_malloc_func, __config_mem_realloc_func,
			__config_mem_free_func);
	zbx_vector_um_macro_create_ext(&host->macros, __config_mem_malloc_func, __config_mem_realloc_func,
			__config_mem_free_func);

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
static zbx_um_host_t	*um_cache_acquire_host(zbx_um_cache_t *cache, zbx_uint64_t hostid)
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

/*********************************************************************************
 *                                                                               *
 * Purpose: sync global/host user macros                                         *
 *                                                                               *
 * Parameters: cache  - [IN] the user macro cache                                *
 *             sync   - [IN] the synchronization object containing inserted,     *
 *                            updated and deleted rows                           *
 *             offset - [IN] macro column offset in the row                      *
 *                                                                               *
 *********************************************************************************/
static void	um_cache_sync_macros(zbx_um_cache_t *cache, zbx_dbsync_t *sync, int offset)
{
	unsigned char		tag;
	int			ret, i;
	zbx_uint64_t		rowid, hostid = 0, *pmacroid = &rowid;
	char			**row;
	zbx_um_macro_t		**pmacro;
	zbx_um_host_t		*host;
	zbx_vector_um_host_t	hosts;

	zbx_vector_um_host_create(&hosts);

	while (SUCCEED == (ret = zbx_dbsync_next(sync, &rowid, &row, &tag)))
	{
		char		*name = NULL, *context = NULL;
		const char	*dc_name, *dc_context, *dc_value;
		unsigned char	context_op;
		zbx_um_macro_t	*macro;

		/* removed rows will be always at the end of sync list */
		if (ZBX_DBSYNC_ROW_REMOVE == tag)
			break;

		if (SUCCEED != zbx_user_macro_parse_dyn(row[offset], &name, &context, NULL, &context_op))
			continue;

		dc_name = dc_strpool_intern(name);
		dc_context = (NULL != context ? dc_strpool_intern(context) : NULL);
		dc_value = dc_strpool_intern(row[offset + 1]);
		zbx_free(name);
		zbx_free(context);

		if (2 == offset)
			ZBX_STR2UINT64(hostid, row[1]);
		host = NULL;

		if (NULL != (pmacro = (zbx_um_macro_t **)zbx_hashset_search(&config->user_macros, &pmacroid)))
		{
			host = um_cache_acquire_host(cache, (*pmacro)->hostid);

			if (SUCCEED == um_macro_is_locked(*pmacro) || (*pmacro)->hostid != hostid)
			{
				if (NULL != host)
				{
					um_host_remove_macro(host, *pmacro);
					zbx_vector_um_host_append(&hosts, host);
				}
				else
					THIS_SHOULD_NEVER_HAPPEN;

				um_macro_release(*pmacro);
				*pmacro = um_macro_dup(*pmacro);
			}

			dc_strpool_release((*pmacro)->name);
			if (NULL != (*pmacro)->context)
				dc_strpool_release((*pmacro)->context);
			dc_strpool_release((*pmacro)->value);
		}
		else
		{
			macro = (zbx_um_macro_t *)__config_mem_malloc_func(NULL, sizeof(zbx_um_macro_t));
			macro->macroid = rowid;
			macro->refcount = 1;
			pmacro = zbx_hashset_insert(&config->user_macros, &macro, sizeof(macro));
		}

		(*pmacro)->hostid = hostid;
		(*pmacro)->name = dc_name;
		(*pmacro)->context = dc_context;
		(*pmacro)->value = dc_value;
		(*pmacro)->type = atoi(row[offset + 2]);
		(*pmacro)->context_op = context_op;

		if (NULL == host || host->hostid != hostid)
		{
			if (NULL == (host = um_cache_acquire_host(cache, hostid)))
				host = um_cache_create_host(cache, hostid);
		}

		/* append created macros to host */
		if (1 == (*pmacro)->refcount)
		{
			(*pmacro)->refcount++;
			zbx_vector_um_macro_append(&host->macros, *pmacro);
		}

		zbx_vector_um_host_append(&hosts, host);
	}

	/* handle removed macros */
	for (; SUCCEED == ret; ret = zbx_dbsync_next(sync, &rowid, &row, &tag))
	{
		if (NULL == (pmacro = (zbx_um_macro_t **)zbx_hashset_search(&config->user_macros, &pmacroid)))
			continue;

		if (NULL != (host = um_cache_acquire_host(cache, (*pmacro)->hostid)))
		{
			um_host_remove_macro(host, *pmacro);
			zbx_vector_um_host_append(&hosts, host);
		}
		um_macro_release(*pmacro);
		zbx_hashset_remove_direct(&config->user_macros, pmacro);
	}

	/* sort macros, remove unused hosts */

	zbx_vector_um_host_sort(&hosts, ZBX_DEFAULT_PTR_COMPARE_FUNC);
	zbx_vector_um_host_uniq(&hosts, ZBX_DEFAULT_PTR_COMPARE_FUNC);

	for (i = 0; i < hosts.values_num; i++)
	{
		if (0 == hosts.values[i]->macros.values_num)
		{
			if (0 == hosts.values[i]->templateids.values_num)
			{
				zbx_hashset_remove(&cache->hosts, &hosts.values[i]);
				um_host_release(hosts.values[i]);
			}
			else
			{
				/* recreate empty-macros vector to release memory */
				zbx_vector_um_macro_destroy(&host->macros);
				zbx_vector_um_macro_create_ext(&host->macros, __config_mem_malloc_func,
						__config_mem_realloc_func, __config_mem_free_func);
			}
		}
		else
			zbx_vector_um_macro_sort(&hosts.values[i]->macros, um_macro_compare_by_name);
	}

	zbx_vector_um_host_destroy(&hosts);
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
	zbx_uint64_t	rowid, templateid;
	char		**row;
	zbx_um_host_t	*host;

	while (SUCCEED == (ret = zbx_dbsync_next(sync, &rowid, &row, &tag)))
	{
		/* removed rows will be always at the end of sync list */
		if (ZBX_DBSYNC_ROW_REMOVE == tag)
			break;

		if (NULL == (host = um_cache_acquire_host(cache, rowid)))
			host = um_cache_create_host(cache, rowid);

		ZBX_DBROW2UINT64(templateid, row[1]);
		zbx_vector_uint64_append(&host->templateids, templateid);
	}

	/* handle removed host template links */
	for (; SUCCEED == ret; ret = zbx_dbsync_next(sync, &rowid, &row, &tag))
	{
		int	i;

		if (NULL == (host = um_cache_acquire_host(cache, rowid)))
			continue;

		ZBX_DBROW2UINT64(templateid, row[1]);

		for (i = 0; i < host->templateids.values_num; i++)
		{
			if (host->templateids.values[i] == templateid)
			{
				zbx_vector_uint64_remove_noorder(&host->templateids, i);
				if (0 == host->templateids.values_num)
				{
					if (0 == host->macros.values_num)
					{
						zbx_hashset_remove(&cache->hosts, &host);
						um_host_release(host);
					}
					else
					{
						zbx_vector_uint64_destroy(&host->templateids);
						zbx_vector_uint64_create_ext(&host->templateids,
								__config_mem_malloc_func, __config_mem_realloc_func,
								__config_mem_free_func);
					}
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
zbx_um_cache_t	*um_cache_sync(zbx_um_cache_t *cache, zbx_dbsync_t *gmacros, zbx_dbsync_t *hmacros,
		zbx_dbsync_t *htmpls)
{
	if (0 == gmacros->rows.values_num && 0 == hmacros->rows.values_num && 0 == htmpls->rows.values_num)
		return cache;

	if (SUCCEED == um_cache_is_locked(cache))
	{
		um_cache_release(cache);
		cache = um_cache_dup(cache);
	}

	um_cache_sync_macros(cache, gmacros, 1);
	um_cache_sync_macros(cache, hmacros, 2);
	um_cache_sync_hosts(cache, htmpls);

	return cache;
}
