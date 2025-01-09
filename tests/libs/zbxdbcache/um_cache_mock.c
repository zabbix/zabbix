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

#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "zbxmockassert.h"
#include "zbxmockutil.h"

#include "zbxnum.h"
#include "zbxalgo.h"
#include "zbxcacheconfig/user_macro.h"
#include "um_cache_mock.h"
#include "zbxshmem.h"

char	*um_mock_format_macro(const char *name, const char *context);
void	*__wrap___zbx_shmem_malloc(const char *file, int line, zbx_shmem_info_t *info, const void *old, size_t size);
void	*__wrap___zbx_shmem_realloc(const char *file, int line, zbx_shmem_info_t *info, void *old, size_t size);
void	__wrap___zbx_shmem_free(const char *file, int line, zbx_shmem_info_t *info, void *ptr);

ZBX_PTR_VECTOR_IMPL(um_mock_macro, zbx_um_mock_macro_t *)

ZBX_PTR_VECTOR_DECL(um_mock_host, zbx_um_mock_host_t *)
ZBX_PTR_VECTOR_IMPL(um_mock_host, zbx_um_mock_host_t *)

ZBX_PTR_VECTOR_IMPL(um_mock_kv, zbx_um_mock_kv_t *)
ZBX_PTR_VECTOR_IMPL(um_mock_kvset, zbx_um_mock_kvset_t *)

static void	um_mock_macro_free(zbx_um_mock_macro_t *macro)
{
	zbx_free(macro->macro);
	zbx_free(macro->value);
	zbx_free(macro);
}

static void	um_mock_macro_init(zbx_um_mock_macro_t *macro, zbx_uint64_t hostid, zbx_mock_handle_t hmacro)
{
	zbx_mock_handle_t	handle;
	const char		*str;
	zbx_mock_error_t	err;

	macro->macroid = zbx_mock_get_object_member_uint64(hmacro, "macroid");
	macro->hostid = hostid;
	macro->macro = zbx_strdup(NULL, zbx_mock_get_object_member_string(hmacro, "macro"));

	if (ZBX_MOCK_SUCCESS == zbx_mock_object_member(hmacro, "value", &handle))
	{
		if (ZBX_MOCK_SUCCESS != (err = zbx_mock_string(handle, &str)))
			fail_msg("Cannot read macro value: %s", zbx_mock_error_string(err));

		macro->value = zbx_strdup(NULL, str);
	}
	else
		macro->value = NULL;

	if (ZBX_MOCK_SUCCESS == zbx_mock_object_member(hmacro, "type", &handle))
	{
		if (ZBX_MOCK_SUCCESS != (err = zbx_mock_string(handle, &str)))
			fail_msg("Cannot read macro type: %s", zbx_mock_error_string(err));

		if (0 == strcmp(str, "ZBX_MACRO_VALUE_TEXT"))
			macro->type = ZBX_MACRO_VALUE_TEXT;
		else if (0 == strcmp(str, "ZBX_MACRO_VALUE_SECRET"))
			macro->type = ZBX_MACRO_VALUE_SECRET;
		else if (0 == strcmp(str, "ZBX_MACRO_VALUE_VAULT"))
			macro->type = ZBX_MACRO_VALUE_VAULT;
		else
			fail_msg("unknown macro type '%s'", str);
	}
	else
		macro->type = ZBX_MACRO_VALUE_TEXT;
}

/*********************************************************************************
 *                                                                               *
 * Purpose: initialize mock user macro host from test data                       *
 *                                                                               *
 *********************************************************************************/
static void	um_mock_host_init(zbx_um_mock_host_t *host, zbx_mock_handle_t handle)
{
	zbx_mock_handle_t	hmacros, hmacro, htemplates, htemplate;
	zbx_mock_error_t	err;

	host->hostid = zbx_mock_get_object_member_uint64(handle, "hostid");
	zbx_vector_um_mock_macro_create(&host->macros);
	zbx_vector_uint64_create(&host->templateids);

	hmacros = zbx_mock_get_object_member_handle(handle, "macros");
	while (ZBX_MOCK_END_OF_VECTOR != (err = (zbx_mock_vector_element(hmacros, &hmacro))))
	{
		zbx_um_mock_macro_t	*macro;

		macro = (zbx_um_mock_macro_t *)zbx_malloc(NULL, sizeof(zbx_um_mock_macro_t));
		um_mock_macro_init(macro, host->hostid, hmacro);
		zbx_vector_um_mock_macro_append(&host->macros, macro);
	}

	htemplates = zbx_mock_get_object_member_handle(handle, "templates");
	while (ZBX_MOCK_END_OF_VECTOR != (err = (zbx_mock_vector_element(htemplates, &htemplate))))
	{
		const char	*template;
		zbx_uint64_t	templateid;

		if (ZBX_MOCK_SUCCESS != (err = zbx_mock_string(htemplate, &template)))
			fail_msg("Cannot read templateid: %s", zbx_mock_error_string(err));

		if (SUCCEED != zbx_is_uint64(template, &templateid))
			fail_msg("Invalid templateid: %s", template);

		zbx_vector_uint64_append(&host->templateids, templateid);
	}
}

/*********************************************************************************
 *                                                                               *
 * Purpose: initialize mock kv path                                              *
 *                                                                               *
 *********************************************************************************/
static void	um_mock_kvset_init(zbx_um_mock_kvset_t *kvset, zbx_mock_handle_t handle)
{
	zbx_mock_handle_t	hvalues, hvalue;
	zbx_mock_error_t	err;

	kvset->path = zbx_mock_get_object_member_string(handle, "path");
	zbx_vector_um_mock_kv_create(&kvset->kvs);

	hvalues = zbx_mock_get_object_member_handle(handle, "values");
	while (ZBX_MOCK_END_OF_VECTOR != (err = (zbx_mock_vector_element(hvalues, &hvalue))))
	{
		zbx_um_mock_kv_t	*kv;

		kv = (zbx_um_mock_kv_t *)zbx_malloc(NULL, sizeof(zbx_um_mock_kv_t));
		kv->key = zbx_mock_get_object_member_string(hvalue, "key");
		kv->value = zbx_mock_get_object_member_string(hvalue, "value");
		zbx_vector_um_mock_kv_append(&kvset->kvs, kv);
	}
}

/*********************************************************************************
 *                                                                               *
 * Purpose: free mock mock kv path                                               *
 *                                                                               *
 *********************************************************************************/
static void	um_mock_kvset_free(zbx_um_mock_kvset_t *kvset)
{
	zbx_vector_um_mock_kv_clear_ext(&kvset->kvs, (zbx_um_mock_kv_free_func_t)zbx_ptr_free);
	zbx_vector_um_mock_kv_destroy(&kvset->kvs);
	zbx_free(kvset);
}

/*********************************************************************************
 *                                                                               *
 * Purpose: initialize mock user macro cache from test data                      *
 *                                                                               *
 *********************************************************************************/
void	um_mock_cache_init(zbx_um_mock_cache_t *cache, zbx_mock_handle_t handle)
{
	zbx_mock_handle_t	hhost, hhosts, hvault, hset;
	zbx_mock_error_t	err;

	zbx_hashset_create(&cache->hosts, 10, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_um_mock_kvset_create(&cache->kvsets);

	if (-1 == handle)
		return;

	hhosts = zbx_mock_get_object_member_handle(handle, "hosts");

	while (ZBX_MOCK_END_OF_VECTOR != (err = (zbx_mock_vector_element(hhosts, &hhost))))
	{
		zbx_um_mock_host_t	host_local;

		um_mock_host_init(&host_local, hhost);
		zbx_hashset_insert(&cache->hosts, &host_local, sizeof(host_local));
	}

	hvault = zbx_mock_get_object_member_handle(handle, "vault");

	while (ZBX_MOCK_END_OF_VECTOR != (err = (zbx_mock_vector_element(hvault, &hset))))
	{
		zbx_um_mock_kvset_t	*kvset;

		kvset = (zbx_um_mock_kvset_t *)zbx_malloc(NULL, sizeof(zbx_um_mock_kvset_t));
		um_mock_kvset_init(kvset, hset);
		zbx_vector_um_mock_kvset_append(&cache->kvsets, kvset);
	}

}

/*********************************************************************************
 *                                                                               *
 * Purpose: restore database macro format from cached macro name/context         *
 *                                                                               *
 * Comments: In database user macro has format '{$NAME:context}' (:context being *
 *           optional), but in cache macro name and context are stored separately*
 *           - '{$NAME}' and 'context' (context can be NULL). This function takes*
 *           the macro name and context from cache and assembles in original     *
 *           format (note that spacing/quotes around the context might be lost). *
 *                                                                               *
 *                                                                               *
 *********************************************************************************/
char	*um_mock_format_macro(const char *name, const char *context)
{
	char	*context_esc, *macro = NULL;
	size_t	macro_alloc = 0, macro_offset = 0;

	zbx_strcpy_alloc(&macro, &macro_alloc, &macro_offset, name);

	if (NULL != context)
	{
		macro_offset--;
		zbx_chrcpy_alloc(&macro, &macro_alloc, &macro_offset, ':');

		if (zbx_get_escape_string_len(context, "\"") != strlen(context))
		{
			context_esc = zbx_dyn_escape_string(context, "\"");
			zbx_snprintf_alloc(&macro, &macro_alloc, &macro_offset, "\"%s\"", context_esc);
			zbx_free(context_esc);
		}
		else
			zbx_strcpy_alloc(&macro, &macro_alloc, &macro_offset, context);

		zbx_chrcpy_alloc(&macro, &macro_alloc, &macro_offset, '}');
	}

	return macro;
}

/*********************************************************************************
 *                                                                               *
 * Purpose: initialize mock user macro cache from cache                          *
 *                                                                               *
 *********************************************************************************/
void	um_mock_cache_init_from_config(zbx_um_mock_cache_t *cache, zbx_um_cache_t *cfg)
{
	zbx_hashset_iter_t	iter;
	zbx_um_host_t		**phost;

	zbx_hashset_create(&cache->hosts, (size_t)cfg->hosts.num_data, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_vector_um_mock_kvset_create(&cache->kvsets);

	zbx_hashset_iter_reset(&cfg->hosts, &iter);
	while (NULL != (phost = (zbx_um_host_t **)zbx_hashset_iter_next(&iter)))
	{
		zbx_um_mock_host_t	host_local;
		int			i;

		host_local.hostid = (*phost)->hostid;
		zbx_vector_um_mock_macro_create(&host_local.macros);
		zbx_vector_uint64_create(&host_local.templateids);
		zbx_vector_uint64_append_array(&host_local.templateids, (*phost)->templateids.values,
				(*phost)->templateids.values_num);

		for (i = 0; i < (*phost)->macros.values_num; i++)
		{
			zbx_um_mock_macro_t	*macro;

			macro = (zbx_um_mock_macro_t *)zbx_malloc(NULL, sizeof(zbx_um_mock_macro_t));
			macro->hostid = (*phost)->hostid;
			macro->macroid = (*phost)->macros.values[i]->macroid;
			macro->macro = um_mock_format_macro((*phost)->macros.values[i]->name,
					(*phost)->macros.values[i]->context);
			macro->value = zbx_strdup(NULL, (*phost)->macros.values[i]->value);
			macro->type = (*phost)->macros.values[i]->type;

			zbx_vector_um_mock_macro_append(&host_local.macros, macro);
		}

		zbx_hashset_insert(&cache->hosts, &host_local, sizeof(host_local));
	}
}

/*********************************************************************************
 *                                                                               *
 * Purpose: frees resources used by mock macro cache                             *
 *                                                                               *
 *********************************************************************************/
void	um_mock_cache_clear(zbx_um_mock_cache_t *cache)
{
	zbx_hashset_iter_t	iter;
	zbx_um_mock_host_t	*host;

	zbx_hashset_iter_reset(&cache->hosts, &iter);
	while (NULL != (host = (zbx_um_mock_host_t *)zbx_hashset_iter_next(&iter)))
	{
		zbx_vector_um_mock_macro_clear_ext(&host->macros, um_mock_macro_free);
		zbx_vector_um_mock_macro_destroy(&host->macros);
		zbx_vector_uint64_destroy(&host->templateids);
	}
	zbx_hashset_destroy(&cache->hosts);

	zbx_vector_um_mock_kvset_clear_ext(&cache->kvsets, um_mock_kvset_free);
	zbx_vector_um_mock_kvset_destroy(&cache->kvsets);
}

static int	um_mock_compare_macros_by_id(const void *d1, const void *d2)
{
	const zbx_um_mock_macro_t	*m1 = *(const zbx_um_mock_macro_t * const *)d1;
	const zbx_um_mock_macro_t	*m2 = *(const zbx_um_mock_macro_t * const *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(m1->macroid, m2->macroid);

	return 0;
}

/*********************************************************************************
 *                                                                               *
 * Purpose: compare macros by name, context and value                            *
 *                                                                               *
 * Comments: Macros are parsed and the name/context are compared separately to   *
 *           handle any spacing/quoting loss because of formatting.              *
 *                                                                               *
 *********************************************************************************/
static int	um_mock_compare_macros_by_content(const zbx_um_mock_macro_t *m1, const zbx_um_mock_macro_t *m2)
{
	int		ret;
	char		*name1 = NULL, *name2 = NULL, *context1 = NULL, *context2 = NULL;
	unsigned char	context_op1, context_op2;

	if (SUCCEED != zbx_user_macro_parse_dyn(m1->macro, &name1, &context1, NULL, &context_op1))
	{
		ret = -1;
		goto out;
	}

	if (SUCCEED != zbx_user_macro_parse_dyn(m2->macro, &name2, &context2, NULL, &context_op2))
	{
		ret = 1;
		goto out;
	}

	if (0 != (ret = strcmp(name1, name2)))
		goto out;

	if (0 != (ret = zbx_strcmp_null(context1, context2)))
		goto out;

	if (context_op1 > context_op2)
	{
		ret = 1;
		goto out;
	}

	if (context_op1 < context_op2)
	{
		ret = -1;
		goto out;
	}

	if (0 != (ret = (int)m1->type - (int)m2->type))
		goto out;

	ret = strcmp(m1->value, m2->value);
out:
	zbx_free(name1);
	zbx_free(name2);
	zbx_free(context1);
	zbx_free(context2);

	return ret;
}

static void	um_mock_dbsync_update_stats(zbx_dbsync_t *sync, unsigned char tag)
{
	switch (tag)
	{
		case ZBX_DBSYNC_ROW_ADD:
			sync->add_num++;
			break;
		case ZBX_DBSYNC_ROW_UPDATE:
			sync->update_num++;
			break;
		case ZBX_DBSYNC_ROW_REMOVE:
			sync->remove_num++;
			break;
	}
}

/*********************************************************************************
 *                                                                               *
 * Purpose: add macro as dbsync row with the specified dbsync operation tag      *
 *                                                                               *
 * Comments: Global macros have 0 hostid and it will be omitted from dbsync row. *
 *                                                                               *
 *********************************************************************************/
static void	um_mock_dbsync_add_macro(zbx_dbsync_t *sync, unsigned char tag, const zbx_um_mock_macro_t *macro)
{
	zbx_dbsync_row_t	*row;
	char			**prow;

	if (0 == sync->columns_num)
		sync->columns_num = (0 == macro->hostid ? 4 : 5);

	row = (zbx_dbsync_row_t *)zbx_malloc(NULL, sizeof(zbx_dbsync_row_t));
	row->rowid = macro->macroid;
	row->tag = tag;

	prow = row->row = (char **)zbx_malloc(NULL, sizeof(char *) * (size_t)sync->columns_num);
	*prow++ = zbx_dsprintf(NULL, ZBX_FS_UI64, macro->macroid);
	if (0 != macro->hostid)
		*prow++ = zbx_dsprintf(NULL, ZBX_FS_UI64, macro->hostid);
	*prow++ = zbx_strdup(NULL, macro->macro);
	*prow++ = zbx_strdup(NULL, macro->value);
	*prow++ = zbx_dsprintf(NULL, "%u", macro->type);

	zbx_vector_ptr_append(&sync->rows, row);

	um_mock_dbsync_update_stats(sync, tag);
}

/*********************************************************************************
 *                                                                               *
 * Purpose: add hosts_templates dbsync row                                       *
 *                                                                               *
 *********************************************************************************/
static void	um_mock_dbsync_add_htmpl(zbx_dbsync_t *sync, unsigned char tag, zbx_uint64_t hostid,
		zbx_uint64_t templateid)
{
	zbx_dbsync_row_t	*row;

	if (0 == sync->columns_num)
		sync->columns_num = 2;

	row = (zbx_dbsync_row_t *)zbx_malloc(NULL, sizeof(zbx_dbsync_row_t));
	row->rowid = 0;
	row->tag = tag;

	row->row = (char **)zbx_malloc(NULL, sizeof(char *) * (size_t)sync->columns_num);
	row->row[0] = zbx_dsprintf(NULL, ZBX_FS_UI64, hostid);
	row->row[1] = zbx_dsprintf(NULL, ZBX_FS_UI64, templateid);

	zbx_vector_ptr_append(&sync->rows, row);

	um_mock_dbsync_update_stats(sync, tag);
}

/*********************************************************************************
 *                                                                               *
 * Purpose:  compare macros on two hosts and:                                    *
 *              1) add new/updated macros to the sync object                     *
 *              2) remove macros by adding them into the del_macros vector       *
 *                                                                               *
 *********************************************************************************/
static void	um_mock_host_macro_diff(const zbx_um_mock_host_t *host1, const zbx_um_mock_host_t *host2,
		zbx_dbsync_t *sync, zbx_vector_um_mock_macro_t *del_macros)
{
	zbx_vector_um_mock_macro_t	macros;
	int				i, j;

	zbx_vector_um_mock_macro_create(&macros);

	if (NULL != host2)
		zbx_vector_um_mock_macro_append_array(&macros, host2->macros.values, host2->macros.values_num);

	if (NULL != host1)
	{
		for (i = 0; i < host1->macros.values_num; i++)
		{
			if (FAIL != (j = zbx_vector_um_mock_macro_search(&macros, host1->macros.values[i],
					um_mock_compare_macros_by_id)))
			{
				if (0 != um_mock_compare_macros_by_content(host1->macros.values[i], macros.values[j]))
					um_mock_dbsync_add_macro(sync, ZBX_DBSYNC_ROW_UPDATE, macros.values[j]);

				zbx_vector_um_mock_macro_remove_noorder(&macros, j);
			}
			else
				zbx_vector_um_mock_macro_append(del_macros, host1->macros.values[i]);
		}
	}

	for (i = 0; i < macros.values_num; i++)
		um_mock_dbsync_add_macro(sync, ZBX_DBSYNC_ROW_ADD, macros.values[i]);

	zbx_vector_um_mock_macro_destroy(&macros);
}

/*********************************************************************************
 *                                                                               *
 * Purpose: compare template ids on two hosts and add new/updated ids to sync and*
 *          removed ids to del_templateids vector                                *
 *                                                                               *
 *********************************************************************************/
static void	um_mock_host_template_diff(const zbx_um_mock_host_t *host1, const zbx_um_mock_host_t *host2,
		zbx_dbsync_t *sync, zbx_vector_uint64_pair_t *del_templateids)
{
	zbx_vector_uint64_t	templateids;
	int			i, j;
	zbx_uint64_pair_t	pair;

	zbx_vector_uint64_create(&templateids);

	if (NULL != host2)
		zbx_vector_uint64_append_array(&templateids, host2->templateids.values, host2->templateids.values_num);

	if (NULL != host1)
	{
		for (i = 0; i < host1->templateids.values_num; i++)
		{
			if (FAIL != (j = zbx_vector_uint64_search(&templateids, host1->templateids.values[i],
					ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
			{
				zbx_vector_uint64_remove_noorder(&templateids, j);
			}
			else
			{
				pair.first = host1->hostid;
				pair.second = host1->templateids.values[i];
				zbx_vector_uint64_pair_append(del_templateids, pair);
			}

		}
	}

	for (i = 0; i < templateids.values_num; i++)
		um_mock_dbsync_add_htmpl(sync, ZBX_DBSYNC_ROW_ADD, host2->hostid, templateids.values[i]);

	zbx_vector_uint64_destroy(&templateids);
}

static int	um_mock_compare_hosts_by_id(const void *d1, const void *d2)
{
	const zbx_um_mock_host_t	*h1 = *(const zbx_um_mock_host_t * const *)d1;
	const zbx_um_mock_host_t	*h2 = *(const zbx_um_mock_host_t * const *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(h1->hostid, h2->hostid);
	return 0;
}

/*********************************************************************************
 *                                                                               *
 * Purpose: compare two mock caches and output difference as database rows       *
 *          stored into sync objects                                             *
 *                                                                               *
 *********************************************************************************/
void	um_mock_cache_diff(zbx_um_mock_cache_t *cache1, zbx_um_mock_cache_t *cache2,
		zbx_dbsync_t *gmacros, zbx_dbsync_t *hmacros, zbx_dbsync_t *htmpls)
{
	zbx_hashset_iter_t		iter;
	zbx_vector_um_mock_host_t	hosts;
	zbx_vector_um_mock_macro_t	del_macros;
	zbx_vector_uint64_pair_t	del_templateids;
	zbx_um_mock_host_t		*host1, *host2;
	int				i;
	zbx_uint64_t			hostid = 0;

	zbx_vector_um_mock_host_create(&hosts);
	zbx_vector_um_mock_macro_create(&del_macros);
	zbx_vector_uint64_pair_create(&del_templateids);

	/* compare global macros */

	host1 = (zbx_um_mock_host_t *)zbx_hashset_search(&cache1->hosts, &hostid);
	host2 = (zbx_um_mock_host_t *)zbx_hashset_search(&cache2->hosts, &hostid);

	um_mock_host_macro_diff(host1, host2, gmacros, &del_macros);

	for (i = 0; i < del_macros.values_num; i++)
		um_mock_dbsync_add_macro(gmacros, ZBX_DBSYNC_ROW_REMOVE, del_macros.values[i]);

	zbx_vector_um_mock_macro_clear(&del_macros);

	/* compare host macros and templates */

	zbx_hashset_iter_reset(&cache2->hosts, &iter);
	while (NULL != (host2 = (zbx_um_mock_host_t *)zbx_hashset_iter_next(&iter)))
	{
		if (0 == host2->hostid)
			continue;

		zbx_vector_um_mock_host_append(&hosts, host2);
	}

	zbx_hashset_iter_reset(&cache1->hosts, &iter);
	while (NULL != (host1 = (zbx_um_mock_host_t *)zbx_hashset_iter_next(&iter)))
	{
		if (0 == host1->hostid)
			continue;

		if (FAIL != (i = zbx_vector_um_mock_host_search(&hosts, host1, um_mock_compare_hosts_by_id)))
		{
			um_mock_host_macro_diff(host1, hosts.values[i], hmacros, &del_macros);
			um_mock_host_template_diff(host1, hosts.values[i], htmpls, &del_templateids);

			zbx_vector_um_mock_host_remove_noorder(&hosts, i);
		}
		else
		{
			um_mock_host_macro_diff(host1, NULL, hmacros, &del_macros);
			um_mock_host_template_diff(host1, NULL, htmpls, &del_templateids);
		}
	}

	for (i = 0; i < hosts.values_num; i++)
	{
		um_mock_host_macro_diff(NULL, hosts.values[i], hmacros, &del_macros);
		um_mock_host_template_diff(NULL, hosts.values[i], htmpls, &del_templateids);
	}

	for (i = 0; i < del_macros.values_num; i++)
		um_mock_dbsync_add_macro(hmacros, ZBX_DBSYNC_ROW_REMOVE, del_macros.values[i]);

	for (i = 0; i < del_templateids.values_num; i++)
	{
		um_mock_dbsync_add_htmpl(htmpls, ZBX_DBSYNC_ROW_REMOVE, del_templateids.values[i].first,
				del_templateids.values[i].second);
	}

	zbx_vector_uint64_pair_destroy(&del_templateids);
	zbx_vector_um_mock_macro_destroy(&del_macros);
	zbx_vector_um_mock_host_destroy(&hosts);
}

#define	REFCOUNT_FIELD_SIZE	sizeof(zbx_uint32_t)

static zbx_hash_t	mock_strpool_hash(const void *data)
{
	return ZBX_DEFAULT_STRING_HASH_FUNC((char *)data + REFCOUNT_FIELD_SIZE);
}

static int	mock_strpool_compare(const void *d1, const void *d2)
{
	return strcmp((char *)d1 + REFCOUNT_FIELD_SIZE, (char *)d2 + REFCOUNT_FIELD_SIZE);
}

/*********************************************************************************
 *                                                                               *
 * Purpose: initialize configuration cache with mocked user macros and string    *
 *          pool                                                                 *
 *                                                                               *
 *********************************************************************************/
void	um_mock_config_init(void)
{
	zbx_dc_config_t	*config = (zbx_dc_config_t *)zbx_malloc(NULL, sizeof(zbx_dc_config_t));
	memset(config, 0, sizeof(zbx_dc_config_t));

	zbx_hashset_create(&config->gmacros, 100, um_macro_hash, um_macro_compare);
	zbx_hashset_create(&config->hmacros, 100, um_macro_hash, um_macro_compare);
	zbx_hashset_create(&config->strpool, 100, mock_strpool_hash, mock_strpool_compare);

	zbx_vector_ptr_create(&config->kvs_paths);

	zbx_hashset_create(&config->gmacro_kv, 100, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_hashset_create(&config->hmacro_kv, 100, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	set_dc_config(config);
}

static void	um_mock_kv_path_free(zbx_dc_kvs_path_t *kvspath)
{
	zbx_hashset_iter_t	iter;
	zbx_dc_kv_t		*kv;

	zbx_hashset_iter_reset(&kvspath->kvs, &iter);
	while (NULL != (kv = (zbx_dc_kv_t *)zbx_hashset_iter_next(&iter)))
		zbx_vector_uint64_pair_destroy(&kv->macros);

	zbx_hashset_destroy(&kvspath->kvs);
	zbx_free(kvspath);
}

/*********************************************************************************
 *                                                                               *
 * Purpose: destroy configuration cache                                          *
 *                                                                               *
 *********************************************************************************/
void	um_mock_config_destroy(void)
{
	zbx_hashset_iter_t	iter;
	zbx_um_macro_t		**pmacro;

	zbx_dc_config_t		*config = get_dc_config();

	zbx_hashset_iter_reset(&config->gmacros, &iter);
	while (NULL != (pmacro = (zbx_um_macro_t **)zbx_hashset_iter_next(&iter)))
		um_macro_release(*pmacro);

	zbx_hashset_iter_reset(&config->hmacros, &iter);
	while (NULL != (pmacro = (zbx_um_macro_t **)zbx_hashset_iter_next(&iter)))
		um_macro_release(*pmacro);

	zbx_vector_ptr_clear_ext(&config->kvs_paths, (zbx_ptr_free_func_t)um_mock_kv_path_free);
	zbx_vector_ptr_destroy(&config->kvs_paths);

	zbx_hashset_destroy(&config->gmacro_kv);
	zbx_hashset_destroy(&config->hmacro_kv);
	zbx_hashset_destroy(&config->gmacros);
	zbx_hashset_destroy(&config->hmacros);
	zbx_hashset_destroy(&config->strpool);

	zbx_free(config);
}

/*********************************************************************************
 *                                                                               *
 * Purpose: clear mocked sync data                                               *
 *                                                                               *
 *********************************************************************************/
void	mock_dbsync_clear(zbx_dbsync_t *sync)
{
	/* free the resources allocated by row pre-processing */
	zbx_vector_ptr_clear_ext(&sync->columns, zbx_ptr_free);
	zbx_vector_ptr_destroy(&sync->columns);

	zbx_free(sync->row);

	if (ZBX_DBSYNC_UPDATE == sync->mode)
	{
		int			i, j;
		zbx_dbsync_row_t	*row;

		for (i = 0; i < sync->rows.values_num; i++)
		{
			row = (zbx_dbsync_row_t *)sync->rows.values[i];

			if (NULL != row->row)
			{
				for (j = 0; j < sync->columns_num; j++)
					zbx_free(row->row[j]);

				zbx_free(row->row);
			}

			zbx_free(row);
		}

		zbx_vector_ptr_destroy(&sync->rows);
	}
	else
	{
		zbx_db_free_result(sync->dbresult);
		sync->dbresult = NULL;
	}
}

/* mocked functions */

void	*__wrap___zbx_shmem_malloc(const char *file, int line, zbx_shmem_info_t *info, const void *old, size_t size)
{
	ZBX_UNUSED(file);
	ZBX_UNUSED(line);
	ZBX_UNUSED(info);
	ZBX_UNUSED(old);

	return zbx_malloc(NULL, size);
}

void	*__wrap___zbx_shmem_realloc(const char *file, int line, zbx_shmem_info_t *info, void *old, size_t size)
{
	ZBX_UNUSED(file);
	ZBX_UNUSED(line);
	ZBX_UNUSED(info);

	return zbx_realloc(old, size);
}

void	__wrap___zbx_shmem_free(const char *file, int line, zbx_shmem_info_t *info, void *ptr)
{
	ZBX_UNUSED(file);
	ZBX_UNUSED(line);
	ZBX_UNUSED(info);

	zbx_free(ptr);
}

/* debug */

static void	um_mock_host_dump(const zbx_um_mock_host_t *host)
{
	int		i;
	const char	*separator;

	printf("\thostid: " ZBX_FS_UI64 "\n\t\tmacros:\n", host->hostid);

	for (i = 0; i < host->macros.values_num; i++)
	{
		printf("\t\t\t" ZBX_FS_UI64 " => %s:%s\n", host->macros.values[i]->macroid,
				host->macros.values[i]->macro, host->macros.values[i]->value);
	}

	separator = "";
	printf("\n\t\ttemplates: [");
	for (i = 0; i < host->templateids.values_num; i++)
	{
		printf("%s" ZBX_FS_UI64 , separator, host->templateids.values[i]);
		separator = ", ";
	}
	printf("]\n");
}

static void	um_mock_kvset_dump(zbx_um_mock_kvset_t *kvset)
{
	int	i;

	printf("\tpath:%s\n", kvset->path);

	for (i = 0; i < kvset->kvs.values_num; i++)
		printf("\t\t%s:%s\n", kvset->kvs.values[i]->key, kvset->kvs.values[i]->value);
}

void	um_mock_cache_dump(zbx_um_mock_cache_t *cache)
{
	zbx_hashset_iter_t	iter;
	zbx_um_mock_host_t	*host;
	int			i;

	printf("\nhosts:\n");
	zbx_hashset_iter_reset(&cache->hosts, &iter);
	while (NULL != (host = (zbx_um_mock_host_t *)zbx_hashset_iter_next(&iter)))
		um_mock_host_dump(host);

	printf("vault:\n");
	for (i = 0; i < cache->kvsets.values_num; i++)
		um_mock_kvset_dump(cache->kvsets.values[i]);

	printf("---\n");
}
