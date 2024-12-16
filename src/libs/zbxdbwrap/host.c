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

#include "zbxdbwrap.h"

#include "template.h"
#include "trigger_linking.h"
#include "graph_linking.h"

#include "zbxcacheconfig.h"
#include "zbxexpression.h"
#include "audit/zbxaudit_host.h"
#include "audit/zbxaudit_item.h"
#include "audit/zbxaudit_trigger.h"
#include "audit/zbxaudit_httptest.h"
#include "audit/zbxaudit_graph.h"
#include "audit/zbxaudit.h"
#include "zbxnum.h"
#include "zbx_host_constants.h"
#include "zbxcrypto.h"
#include "zbxalgo.h"
#include "zbxdb.h"
#include "zbxdbhigh.h"
#include "zbxhash.h"
#include "zbxstr.h"
#include "zbxinterface.h"

typedef enum
{
	SYSMAP_ELEMENT_TYPE_HOST = 0,
	SYSMAP_ELEMENT_TYPE_MAP,
	SYSMAP_ELEMENT_TYPE_TRIGGER,
	SYSMAP_ELEMENT_TYPE_HOST_GROUP,
	SYSMAP_ELEMENT_TYPE_IMAGE
}
zbx_sysmap_element_types_t;

typedef struct
{
	zbx_uint64_t	id;
	char		*name;
}
zbx_id_name_pair_t;

typedef struct
{
	zbx_uint64_t	ugsetid;
	int		permission;
} zbx_hgset_permission_t;

ZBX_VECTOR_DECL(hgset_permission, zbx_hgset_permission_t)
ZBX_VECTOR_IMPL(hgset_permission, zbx_hgset_permission_t)

static zbx_hash_t	zbx_ids_names_hash_func(const void *data)
{
	const zbx_id_name_pair_t	*id_name_pair_entry = (const zbx_id_name_pair_t *)data;

	return ZBX_DEFAULT_UINT64_HASH_ALGO(&(id_name_pair_entry->id), sizeof(id_name_pair_entry->id),
			ZBX_DEFAULT_HASH_SEED);
}

static int	zbx_ids_names_compare_func(const void *d1, const void *d2)
{
	const zbx_id_name_pair_t	*id_name_pair_entry_1 = (const zbx_id_name_pair_t *)d1;
	const zbx_id_name_pair_t	*id_name_pair_entry_2 = (const zbx_id_name_pair_t *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(id_name_pair_entry_1->id, id_name_pair_entry_2->id);

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Parameters: sql   - [IN] sql statement                                     *
 *             ids   - [OUT] sorted list of selected uint64 values            *
 *             names - [OUT] list of names of the requested resource, order   *
 *                     matches the order of ids list                          *
 *                                                                            *
 * Return value: SUCCEED - query for selecting ids and names SUCCEEDED        *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	DBselect_ids_names(const char *sql, zbx_vector_uint64_t *ids, zbx_vector_str_t *names)
{
	int		i, ret = FAIL;
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	zbx_uint64_t	id;
	zbx_hashset_t	ids_names;

	if (NULL == (result = zbx_db_select("%s", sql)))
		goto out;

#define	IDS_NAMES_HASHSET_DEF_SIZE	100
	zbx_hashset_create(&ids_names, IDS_NAMES_HASHSET_DEF_SIZE,
			zbx_ids_names_hash_func,
			zbx_ids_names_compare_func);
#undef IDS_NAMES_HASHSET_DEF_SIZE

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_id_name_pair_t	local_id_name_pair;

		ZBX_STR2UINT64(id, row[0]);
		zbx_vector_uint64_append(ids, id);
		local_id_name_pair.id = id;
		local_id_name_pair.name = zbx_strdup(NULL, row[1]);
		zbx_hashset_insert(&ids_names, &local_id_name_pair, sizeof(local_id_name_pair));
	}
	zbx_db_free_result(result);

	zbx_vector_uint64_sort(ids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	for (i = 0; i < ids->values_num; i++)
	{
		zbx_id_name_pair_t	*found, temp_t;

		temp_t.id = ids->values[i];
		if (NULL != (found = (zbx_id_name_pair_t *)zbx_hashset_search(&ids_names, &temp_t)))
		{
			zbx_vector_str_append(names, zbx_strdup(NULL, found->name));
			zbx_free(found->name);
		}
		else
		{
			THIS_SHOULD_NEVER_HAPPEN;
			goto clean;
		}
	}
	ret = SUCCEED;
clean:
	zbx_hashset_destroy(&ids_names);
out:
	return ret;
}

typedef struct _zbx_template_graph_valid_t zbx_template_graph_valid_t;
ZBX_PTR_VECTOR_DECL(graph_valid_ptr, zbx_template_graph_valid_t *)

struct _zbx_template_graph_valid_t
{
	zbx_uint64_t		tgraphid;
	zbx_uint64_t		hgraphid;
	char			*name;
	zbx_vector_str_t	tkeys;
	zbx_vector_str_t	hkeys;
};

ZBX_PTR_VECTOR_IMPL(graph_valid_ptr, zbx_template_graph_valid_t *)

static char	*get_template_names(const zbx_vector_uint64_t *templateids)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	char		*sql = NULL, *template_names = NULL;
	size_t		sql_alloc = 256, sql_offset=0, tmp_alloc = 64, tmp_offset = 0;

	sql = (char *)zbx_malloc(sql, sql_alloc);
	template_names = (char *)zbx_malloc(template_names, tmp_alloc);

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select host"
			" from hosts"
			" where");

	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid",
			templateids->values, templateids->values_num);

	result = zbx_db_select("%s", sql);

	while (NULL != (row = zbx_db_fetch(result)))
		zbx_snprintf_alloc(&template_names, &tmp_alloc, &tmp_offset, "\"%s\", ", row[0]);

	template_names[tmp_offset - 2] = '\0';

	zbx_db_free_result(result);
	zbx_free(sql);

	return template_names;
}

/******************************************************************************
 *                                                                            *
 * Description: gets a vector of profile identifiers used with the specified  *
 *              source, indexes and value identifiers                         *
 *                                                                            *
 * Parameters: profileids - [OUT] the screen item identifiers                 *
 *             source     - [IN] the source                                   *
 *             idxs       - [IN] an array of index values                     *
 *             idxs_num   - [IN] the number of values in idxs array           *
 *             value_ids  - [IN] the resource identifiers                     *
 *                                                                            *
 ******************************************************************************/
static void	DBget_profiles_by_source_idxs_values(zbx_vector_uint64_t *profileids, const char *source,
		const char **idxs, int idxs_num, zbx_vector_uint64_t *value_ids)
{
	char	*sql = NULL;
	size_t	sql_alloc = 0, sql_offset = 0;

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select profileid from profiles where");

	if (NULL != source)
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " source='%s' and", source);

	if (0 != idxs_num)
	{
		zbx_db_add_str_condition_alloc(&sql, &sql_alloc, &sql_offset, "idx", idxs, idxs_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " and");
	}

	zbx_db_large_query_t	query;
	zbx_db_row_t		row;

	zbx_db_large_query_prepare_uint(&query, &sql, &sql_alloc, &sql_offset, "value_id", value_ids);

	while (NULL != (row = zbx_db_large_query_fetch(&query)))
	{
		zbx_uint64_t	profileid;

		ZBX_STR2UINT64(profileid, row[0]);
		zbx_vector_uint64_append(profileids, profileid);
	}
	zbx_db_large_query_clear(&query);
	zbx_free(sql);

	zbx_vector_uint64_sort(profileids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_uniq(profileids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
}

/******************************************************************************
 *                                                                            *
 * Description: gets a vector of sysmap element identifiers used with the     *
 *              specified element type and identifiers                        *
 *                                                                            *
 * Parameters: selementids - [OUT] the sysmap element identifiers             *
 *             elementtype - [IN] the element type                            *
 *             elementids  - [IN] the element identifiers                     *
 *                                                                            *
 ******************************************************************************/
static void	DBget_sysmapelements_by_element_type_ids(zbx_vector_uint64_t *selementids, int elementtype,
		const zbx_vector_uint64_t *elementids)
{
	char		*sql = NULL;
	size_t		sql_alloc = 0, sql_offset = 0;
	zbx_db_row_t	row;

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select distinct selementid"
			" from sysmaps_elements"
			" where elementtype=%d"
				" and",
			elementtype);

	zbx_db_large_query_t	query;

	zbx_db_large_query_prepare_uint(&query, &sql, &sql_alloc, &sql_offset, "elementid", elementids);

	while (NULL != (row = zbx_db_large_query_fetch(&query)))
	{
		zbx_uint64_t	elementid;

		ZBX_STR2UINT64(elementid, row[0]);
		zbx_vector_uint64_append(selementids, elementid);
	}
	zbx_db_large_query_clear(&query);
	zbx_free(sql);

	zbx_vector_uint64_sort(selementids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_uniq(selementids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
}

/******************************************************************************
 *                                                                            *
 * Description: Check collisions between linked templates                     *
 *                                                                            *
 * Parameters: templateids - [IN] array of template IDs                       *
 *                                                                            *
 * Return value: SUCCEED if no collisions found                               *
 *                                                                            *
 * Comments: !!! Don't forget to sync the code with PHP !!!                   *
 *                                                                            *
 ******************************************************************************/
static int	validate_linked_templates(const zbx_vector_uint64_t *templateids, char *error, size_t max_error_len)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	char		*sql = NULL;
	size_t		sql_alloc = 256, sql_offset;
	int		ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (0 == templateids->values_num)
		goto out;

	sql = (char *)zbx_malloc(sql, sql_alloc);

	/* items */
	if (SUCCEED == ret && 1 < templateids->values_num)
	{
		sql_offset = 0;
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				"select key_,count(*)"
				" from items"
				" where");
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid",
				templateids->values, templateids->values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				" group by key_"
				" having count(*)>1");

		result = zbx_db_select_n(sql, 1);

		if (NULL != (row = zbx_db_fetch(result)))
		{
			ret = FAIL;
			zbx_snprintf(error, max_error_len, "conflicting item key \"%s\" found", row[0]);
		}
		zbx_db_free_result(result);
	}

	/* trigger expressions */
	if (SUCCEED == ret)
	{
		sql_offset = 0;
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"select t1.description,h2.host"
				" from items i1,functions f1,triggers t1,functions f2,items i2,hosts h2"
				" where i1.itemid=f1.itemid"
					" and f1.triggerid=t1.triggerid"
					" and t1.triggerid=f2.triggerid"
					" and f2.itemid=i2.itemid"
					" and i2.hostid=h2.hostid"
					" and h2.status=%d"
					" and",
				HOST_STATUS_TEMPLATE);
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "i1.hostid",
				templateids->values, templateids->values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " and not");
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "i2.hostid",
				templateids->values, templateids->values_num);

		result = zbx_db_select_n(sql, 1);

		if (NULL != (row = zbx_db_fetch(result)))
		{
			ret = FAIL;
			zbx_snprintf(error, max_error_len,
					"trigger \"%s\" has items from template \"%s\"",
					row[0], row[1]);
		}
		zbx_db_free_result(result);
	}

	/* trigger dependencies */
	if (SUCCEED == ret)
	{
		sql_offset = 0;
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				"select t1.description,h1.host,t2.description,h2.host"
				" from trigger_depends td,triggers t1,functions f1,items i1,hosts h1,"
					"triggers t2,functions f2,items i2,hosts h2"
				" where td.triggerid_down=t1.triggerid"
					" and t1.triggerid=f1.triggerid"
					" and f1.itemid=i1.itemid"
					" and i1.hostid=h1.hostid"
					" and td.triggerid_up=t2.triggerid"
					" and t2.triggerid=f2.triggerid"
					" and f2.itemid=i2.itemid"
					" and i2.hostid=h2.hostid"
					" and");
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "i1.hostid",
				templateids->values, templateids->values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " and not");
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "i2.hostid",
				templateids->values, templateids->values_num);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " and h2.status=%d", HOST_STATUS_TEMPLATE);

		result = zbx_db_select_n(sql, 1);

		if (NULL != (row = zbx_db_fetch(result)))
		{
			ret = FAIL;
			zbx_snprintf(error, max_error_len,
					"trigger \"%s\" in template \"%s\""
					" has dependency from trigger \"%s\" in template \"%s\"",
					row[0], row[1], row[2], row[3]);
		}
		zbx_db_free_result(result);
	}

	/* graphs */
	if (SUCCEED == ret && 1 < templateids->values_num)
	{
		zbx_vector_uint64_t	graphids;

		zbx_vector_uint64_create(&graphids);

		/* select all linked graphs */
		sql_offset = 0;
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				"select distinct gi.graphid"
				" from graphs_items gi,items i"
				" where gi.itemid=i.itemid"
					" and");
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "i.hostid",
				templateids->values, templateids->values_num);

		zbx_db_select_uint64(sql, &graphids);

		/* check for names */
		if (0 != graphids.values_num)
		{
			sql_offset = 0;
			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
					"select name,count(*)"
					" from graphs"
					" where");
			zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "graphid",
					graphids.values, graphids.values_num);
			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
					" group by name"
					" having count(*)>1");

			result = zbx_db_select("%s", sql);

			if (NULL != (row = zbx_db_fetch(result)))
			{
				ret = FAIL;
				zbx_snprintf(error, max_error_len,
						"template with graph \"%s\" already linked to the host", row[0]);
			}
			zbx_db_free_result(result);
		}

		zbx_vector_uint64_destroy(&graphids);
	}

	/* httptests */
	if (SUCCEED == ret && 1 < templateids->values_num)
	{
		sql_offset = 0;
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				"select name,count(*)"
				" from httptest"
				" where");
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid",
				templateids->values, templateids->values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				" group by name"
				" having count(*)>1");

		result = zbx_db_select_n(sql, 1);

		if (NULL != (row = zbx_db_fetch(result)))
		{
			ret = FAIL;
			zbx_snprintf(error, max_error_len,
					"template with web scenario \"%s\" already linked to the host", row[0]);
		}
		zbx_db_free_result(result);
	}

	zbx_free(sql);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Description: Check collisions in item inventory links                      *
 *                                                                            *
 * Parameters: hostid      - [IN] host identifier from database               *
 *             templateids - [IN] array of template IDs                       *
 *                                                                            *
 * Return value: SUCCEED if no collisions found                               *
 *                                                                            *
 * Comments: !!! Don't forget to sync the code with PHP !!!                   *
 *                                                                            *
 ******************************************************************************/
static int	validate_inventory_links(zbx_uint64_t hostid, const zbx_vector_uint64_t *templateids,
		char *error, size_t max_error_len)
{
	zbx_db_result_t	result;
	char		*sql = NULL;
	size_t		sql_alloc = 512, sql_offset;
	int		ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	sql = (char *)zbx_malloc(sql, sql_alloc);

	sql_offset = 0;
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select inventory_link,count(*)"
			" from items"
			" where inventory_link<>0"
				" and");
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid",
			templateids->values, templateids->values_num);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			" group by inventory_link"
			" having count(*)>1");

	result = zbx_db_select_n(sql, 1);

	if (NULL != zbx_db_fetch(result))
	{
		ret = FAIL;
		zbx_strlcpy(error, "two items cannot populate one host inventory field", max_error_len);
	}
	zbx_db_free_result(result);

	if (FAIL == ret)
		goto out;

	sql_offset = 0;
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select ti.itemid"
			" from items ti,items i"
			" where ti.key_<>i.key_"
				" and ti.inventory_link=i.inventory_link"
				" and");
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "ti.hostid",
			templateids->values, templateids->values_num);
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				" and i.hostid=" ZBX_FS_UI64
				" and ti.inventory_link<>0"
				" and not exists ("
					"select *"
					" from items",
				hostid);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " where");
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "items.hostid",
			templateids->values, templateids->values_num);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
						" and items.key_=i.key_"
					")");

	result = zbx_db_select_n(sql, 1);

	if (NULL != zbx_db_fetch(result))
	{
		ret = FAIL;
		zbx_strlcpy(error, "two items cannot populate one host inventory field", max_error_len);
	}
	zbx_db_free_result(result);
out:
	zbx_free(sql);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Description: checking collisions on linking of web scenarios               *
 *                                                                            *
 * Parameters: hostid      - [IN] host identifier from database               *
 *             templateids - [IN] array of template IDs                       *
 *                                                                            *
 * Return value: SUCCEED if no collisions found                               *
 *                                                                            *
 * Comments: !!! Don't forget to sync the code with PHP !!!                   *
 *                                                                            *
 ******************************************************************************/
static int	validate_httptests(zbx_uint64_t hostid, const zbx_vector_uint64_t *templateids,
		char *error, size_t max_error_len)
{
	zbx_db_result_t	tresult;
	zbx_db_result_t	sresult;
	zbx_db_row_t	trow;
	char		*sql = NULL;
	size_t		sql_alloc = 512, sql_offset = 0;
	int		ret = SUCCEED;
	zbx_uint64_t	t_httptestid, h_httptestid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	sql = (char *)zbx_malloc(sql, sql_alloc);

	/* selects web scenarios from templates and host with identical names */
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select t.httptestid,t.name,h.httptestid"
			" from httptest t"
				" inner join httptest h"
					" on h.name=t.name"
						" and h.hostid=" ZBX_FS_UI64
			" where", hostid);
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "t.hostid", templateids->values,
			templateids->values_num);

	tresult = zbx_db_select("%s", sql);

	while (NULL != (trow = zbx_db_fetch(tresult)))
	{
		ZBX_STR2UINT64(t_httptestid, trow[0]);
		ZBX_STR2UINT64(h_httptestid, trow[2]);

		sql_offset = 0;
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"select t.httpstepid,h.httpstepid"
				" from httpstep t"
					" left join httpstep h"
						" on h.httptestid=" ZBX_FS_UI64
							" and h.no=t.no"
							" and h.name=t.name"
				" where t.httptestid=" ZBX_FS_UI64
					" and h.httpstepid is null"
				" union "
				"select t.httpstepid,h.httpstepid"
				" from httpstep h"
					" left outer join httpstep t"
						" on t.httptestid=" ZBX_FS_UI64
							" and t.no=h.no"
							" and t.name=h.name"
				" where h.httptestid=" ZBX_FS_UI64
					" and t.httpstepid is null",
				h_httptestid, t_httptestid, t_httptestid, h_httptestid);

		sresult = zbx_db_select_n(sql, 1);

		if (NULL != zbx_db_fetch(sresult))
		{
			ret = FAIL;
			zbx_snprintf(error, max_error_len,
					"web scenario \"%s\" already exists on the host (steps are not identical)",
					trow[1]);
		}
		zbx_db_free_result(sresult);

		if (SUCCEED != ret)
			break;
	}
	zbx_db_free_result(tresult);

	zbx_free(sql);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

static void	zbx_graph_valid_free(zbx_template_graph_valid_t *graph)
{
	zbx_vector_str_clear_ext(&graph->tkeys, zbx_str_free);
	zbx_vector_str_clear_ext(&graph->hkeys, zbx_str_free);
	zbx_vector_str_destroy(&graph->tkeys);
	zbx_vector_str_destroy(&graph->hkeys);
	zbx_free(graph->name);
	zbx_free(graph);
}

/******************************************************************************
 *                                                                            *
 * Description: Check collisions between host and linked template             *
 *                                                                            *
 * Parameters: hostid      - [IN] host identifier from database               *
 *             templateids - [IN] array of template IDs                       *
 *                                                                            *
 * Return value: SUCCEED if no collisions found                               *
 *                                                                            *
 * Comments: !!! Don't forget to sync the code with PHP !!!                   *
 *                                                                            *
 ******************************************************************************/
static int	validate_host(zbx_uint64_t hostid, zbx_vector_uint64_t *templateids, char *error, size_t max_error_len)
{
	int				ret = SUCCEED, i, j;
	char				*sql = NULL;
	unsigned char			t_flags, h_flags, type;
	zbx_db_result_t			tresult;
	zbx_db_row_t			trow;
	size_t				sql_alloc = 256, sql_offset;
	zbx_uint64_t			graphid, interfaceids[INTERFACE_TYPE_COUNT];
	zbx_vector_graph_valid_ptr_t	graphs;
	zbx_vector_uint64_t		graphids;
	zbx_template_graph_valid_t	*graph;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (SUCCEED != (ret = validate_inventory_links(hostid, templateids, error, max_error_len)))
		goto out;

	if (SUCCEED != (ret = validate_httptests(hostid, templateids, error, max_error_len)))
		goto out;

	zbx_vector_graph_valid_ptr_create(&graphs);
	zbx_vector_uint64_create(&graphids);

	sql = (char *)zbx_malloc(NULL, sql_alloc);

	sql_offset = 0;

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select i.key_,h.name,ir.key_,ihr.key_ from items ir,item_discovery id,items i"
			" inner join hosts h on i.hostid=h.hostid"
			" left join items ih on ih.key_=i.key_"
			" left join item_discovery ihd on ih.itemid=ihd.itemid"
			" left join items ihr on ihr.itemid=ihd.parent_itemid"
			" where ir.itemid=id.parent_itemid and i.itemid=id.itemid and ih.hostid=" ZBX_FS_UI64 " and "
			" (ihr.key_ is null or ihr.key_<>ir.key_) and", hostid);

	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "i.hostid", templateids->values,
			templateids->values_num);

	tresult = zbx_db_select("%s", sql);

	if (NULL != (trow = zbx_db_fetch(tresult)))
	{
		ret = FAIL;

		if (SUCCEED == zbx_db_is_null(trow[3]))
		{
			zbx_snprintf(error, max_error_len, "cannot inherit item prototype with key \"%s\" of "
					"template \"%s\" and LLD rule \"%s\", because an item with the same key already"
					" exists", trow[0], trow[1], trow[2]);
		}
		else
		{
			zbx_snprintf(error, max_error_len, "cannot inherit item prototype with key \"%s\" of "
					"template \"%s\" and LLD rule \"%s\", because an item prototype with the "
					"same key already belongs to LLD rule \"%s\"", trow[0], trow[1], trow[2],
					trow[3]);
		}

		zbx_db_free_result(tresult);
		goto out;
	}
	zbx_db_free_result(tresult);
	sql_offset = 0;

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select distinct g.graphid,g.name,g.flags,g2.graphid,g2.flags"
			" from graphs_items gi,items i,graphs g"
			" join graphs g2 on g2.name=g.name and g2.templateid is null"
			" join graphs_items gi2 on gi2.graphid=g2.graphid"
			" join items i2 on i2.itemid=gi2.itemid and i2.hostid=" ZBX_FS_UI64
			" where g.graphid=gi.graphid and gi.itemid=i.itemid and",
			hostid);
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "i.hostid", templateids->values,
			templateids->values_num);

	tresult = zbx_db_select("%s", sql);

	while (NULL != (trow = zbx_db_fetch(tresult)))
	{
		t_flags = (unsigned char)atoi(trow[2]);
		h_flags = (unsigned char)atoi(trow[4]);

		if (t_flags != h_flags)
		{
			ret = FAIL;
			zbx_snprintf(error, max_error_len,
					"graph prototype and real graph \"%s\" have the same name", trow[1]);
			break;
		}

		graph = (zbx_template_graph_valid_t *)zbx_malloc(NULL, sizeof(zbx_template_graph_valid_t));

		ZBX_STR2UINT64(graph->tgraphid, trow[0]);
		ZBX_STR2UINT64(graph->hgraphid, trow[3]);

		zbx_vector_uint64_append(&graphids, graph->tgraphid);
		zbx_vector_uint64_append(&graphids, graph->hgraphid);

		graph->name = zbx_strdup(NULL, trow[1]);

		zbx_vector_str_create(&graph->hkeys);
		zbx_vector_str_create(&graph->tkeys);

		zbx_vector_graph_valid_ptr_append(&graphs, graph);
	}

	zbx_db_free_result(tresult);

	if (0 != graphids.values_num)
	{
		sql_offset = 0;

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				"select gi.graphid,i.key_"
				" from items i,graphs_items gi"
				" where gi.itemid=i.itemid"
				" and");
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "gi.graphid", graphids.values,
				graphids.values_num);

		tresult = zbx_db_select("%s", sql);

		while (NULL != (trow = zbx_db_fetch(tresult)))
		{
			ZBX_STR2UINT64(graphid, trow[0]);

			for (i = 0; i < graphs.values_num; i++)
			{
				graph = (zbx_template_graph_valid_t *)graphs.values[i];

				if (graphid == graph->tgraphid)
				{
					zbx_vector_str_append(&graph->tkeys, zbx_strdup(NULL, trow[1]));
					break;
				}

				if (graphid == graph->hgraphid)
				{
					zbx_vector_str_append(&graph->hkeys, zbx_strdup(NULL, trow[1]));
					break;
				}
			}
		}
		zbx_db_free_result(tresult);
	}

	for (i = 0; i < graphs.values_num; i++)
	{
		graph = (zbx_template_graph_valid_t *)graphs.values[i];

		if (graph->tkeys.values_num != graph->hkeys.values_num )
		{
			ret = FAIL;
			break;
		}

		zbx_vector_str_sort(&graph->tkeys, ZBX_DEFAULT_STR_COMPARE_FUNC);
		zbx_vector_str_sort(&graph->hkeys, ZBX_DEFAULT_STR_COMPARE_FUNC);

		for (j = 0; j < graph->tkeys.values_num; j++)
		{
			if (0 != strcmp(graph->tkeys.values[j], graph->hkeys.values[j]))
			{
				ret = FAIL;
				break;
			}
		}

		if (FAIL == ret)
			break;
	}

	if (FAIL == ret && 0 < graphs.values_num)
	{
		graph = (zbx_template_graph_valid_t *)graphs.values[i];

		zbx_snprintf(error, max_error_len, "graph \"%s\" already exists on the host (items are not identical)",
				graph->name);
	}

	if (SUCCEED == ret)
	{
		sql_offset = 0;
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"select i.key_"
				" from items i,items t"
				" where i.key_=t.key_"
					" and i.flags<>t.flags"
					" and i.hostid=" ZBX_FS_UI64
					" and",
				hostid);
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "t.hostid",
				templateids->values, templateids->values_num);

		tresult = zbx_db_select_n(sql, 1);

		if (NULL != (trow = zbx_db_fetch(tresult)))
		{
			ret = FAIL;
			zbx_snprintf(error, max_error_len,
					"item prototype and real item \"%s\" have the same key", trow[0]);
		}
		zbx_db_free_result(tresult);
	}

	/* interfaces */
	if (SUCCEED == ret)
	{
		memset(&interfaceids, 0, sizeof(interfaceids));

		tresult = zbx_db_select(
				"select type,interfaceid"
				" from interface"
				" where hostid=" ZBX_FS_UI64
					" and type in (%d,%d,%d,%d)"
					" and main=1",
				hostid, INTERFACE_TYPE_AGENT, INTERFACE_TYPE_SNMP,
				INTERFACE_TYPE_IPMI, INTERFACE_TYPE_JMX);

		while (NULL != (trow = zbx_db_fetch(tresult)))
		{
			type = (unsigned char)atoi(trow[0]);
			ZBX_STR2UINT64(interfaceids[type - 1], trow[1]);
		}
		zbx_db_free_result(tresult);

		sql_offset = 0;

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"select distinct type"
				" from items"
				" where type not in (%d,%d,%d,%d,%d,%d,%d,%d,%d,%d)"
					" and",
				/* item types with interface types INTERFACE_TYPE_OPT or INTERFACE_TYPE_UNKNOWN */
				ITEM_TYPE_TRAPPER, ITEM_TYPE_INTERNAL, ITEM_TYPE_ZABBIX_ACTIVE,
				ITEM_TYPE_HTTPTEST, ITEM_TYPE_DB_MONITOR, ITEM_TYPE_CALCULATED, ITEM_TYPE_DEPENDENT,
				ITEM_TYPE_HTTPAGENT, ITEM_TYPE_SCRIPT, ITEM_TYPE_BROWSER);
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid",
				templateids->values, templateids->values_num);

		tresult = zbx_db_select("%s", sql);

		while (SUCCEED == ret && NULL != (trow = zbx_db_fetch(tresult)))
		{
			type = (unsigned char)atoi(trow[0]);
			type = zbx_get_interface_type_by_item_type(type);

			if (INTERFACE_TYPE_ANY == type)
			{
				for (i = 0; INTERFACE_TYPE_COUNT > i; i++)
				{
					if (0 != interfaceids[i])
						break;
				}

				if (INTERFACE_TYPE_COUNT == i)
				{
					zbx_strlcpy(error, "cannot find any interfaces on host", max_error_len);
					ret = FAIL;
				}
			}
			else if (0 == interfaceids[type - 1])
			{
				zbx_snprintf(error, max_error_len, "cannot find \"%s\" host interface",
						zbx_interface_type_string((zbx_interface_type_t)type));
				ret = FAIL;
			}
		}
		zbx_db_free_result(tresult);
	}

	zbx_vector_graph_valid_ptr_clear_ext(&graphs, zbx_graph_valid_free);
	zbx_vector_graph_valid_ptr_destroy(&graphs);
	zbx_vector_uint64_destroy(&graphids);
out:
	zbx_free(sql);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: delete action conditions by condition type and id                 *
 *                                                                            *
 ******************************************************************************/
static void	DBdelete_action_conditions(int conditiontype, zbx_uint64_t elementid)
{
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	zbx_uint64_t		id;
	zbx_vector_uint64_t	actionids, conditionids;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;

	zbx_vector_uint64_create(&actionids);
	zbx_vector_uint64_create(&conditionids);

	/* disable actions */
	result = zbx_db_select("select actionid,conditionid from conditions where conditiontype=%d and"
			" value='" ZBX_FS_UI64 "'", conditiontype, elementid);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		ZBX_STR2UINT64(id, row[0]);
		zbx_vector_uint64_append(&actionids, id);

		ZBX_STR2UINT64(id, row[1]);
		zbx_vector_uint64_append(&conditionids, id);
	}

	zbx_db_free_result(result);

	if (0 != actionids.values_num)
	{
		zbx_vector_uint64_sort(&actionids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_vector_uint64_uniq(&actionids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update actions set status=%d where",
				ZBX_ACTION_STATUS_DISABLED);
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "actionid", actionids.values,
				actionids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");
	}

	if (0 != conditionids.values_num)
	{
		zbx_vector_uint64_sort(&conditionids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from conditions where");
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "conditionid", conditionids.values,
				conditionids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");
	}

	(void)zbx_db_flush_overflowed_sql(sql, sql_offset);

	zbx_free(sql);

	zbx_vector_uint64_destroy(&conditionids);
	zbx_vector_uint64_destroy(&actionids);
}

/******************************************************************************
 *                                                                            *
 * Purpose:  adds table and field with specific id to housekeeper list        *
 *                                                                            *
 * Parameters: ids       - [IN] identifiers for data removal                  *
 *             field     - [IN] field name from table                         *
 *             tables_hk - [IN] table name to delete information from         *
 *             count     - [IN] number of tables in tables array              *
 *                                                                            *
 * Comments: !!! Don't forget to sync the code with PHP !!!                   *
 *                                                                            *
 ******************************************************************************/
static void	DBadd_to_housekeeper(const zbx_vector_uint64_t *ids, const char *field, const char * const *tables_hk,
		int count)
{
	int		i, j;
	zbx_uint64_t	housekeeperid;
	zbx_db_insert_t	db_insert;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() values_num:%d", __func__, ids->values_num);

	if (0 == ids->values_num)
		goto out;

	housekeeperid = zbx_db_get_maxid_num("housekeeper", count * ids->values_num);

	zbx_db_insert_prepare(&db_insert, "housekeeper", "housekeeperid", "tablename", "field", "value", (char *)NULL);

	for (i = 0; i < ids->values_num; i++)
	{
		for (j = 0; j < count; j++)
			zbx_db_insert_add_values(&db_insert, housekeeperid++, tables_hk[j], field, ids->values[i]);
	}

	zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: deletes trigger from database                                     *
 *                                                                            *
 * Parameters:                                                                *
 *             triggerids         - [IN] trigger ids from database            *
 *             audit_context_mode - [IN]                                      *
 *                                                                            *
 ******************************************************************************/
void	zbx_db_delete_triggers(zbx_vector_uint64_t *triggerids, int audit_context_mode)
{
	zbx_vector_uint64_t	selementids;
	const char		*event_tables[] = {"events"};

	ZBX_UNUSED(audit_context_mode);

	if (0 == triggerids->values_num)
		return;

	zbx_vector_uint64_create(&selementids);

	DBget_sysmapelements_by_element_type_ids(&selementids, SYSMAP_ELEMENT_TYPE_TRIGGER, triggerids);
	if (0 != selementids.values_num)
		zbx_db_execute_multiple_query("delete from sysmaps_elements where", "selementid", &selementids);

	zbx_audit_trigger_delete(audit_context_mode, triggerids);

	for (int i = 0; i < triggerids->values_num; i++)
		DBdelete_action_conditions(ZBX_CONDITION_TYPE_TRIGGER, triggerids->values[i]);

	zbx_db_execute_multiple_query("delete from trigger_tag where", "triggerid", triggerids);
	zbx_db_execute_multiple_query("delete from functions where", "triggerid", triggerids);
	zbx_db_execute_multiple_query("delete from triggers where", "triggerid", triggerids);

	/* add housekeeper task to delete problems associated with trigger, this allows old events to be deleted */
	DBadd_to_housekeeper(triggerids, "triggerid", event_tables, ARRSIZE(event_tables));

	zbx_vector_uint64_destroy(&selementids);
}

/******************************************************************************
 *                                                                            *
 * Purpose: deletes parent triggers and auto-created children from database   *
 *                                                                            *
 * Parameters:                                                                *
 *             triggerids         - [IN] trigger ids from database            *
 *             audit_context_mode - [IN]                                      *
 *                                                                            *
 ******************************************************************************/
static void	DBdelete_trigger_hierarchy(zbx_vector_uint64_t *triggerids, int audit_context_mode)
{
	char			*sql = NULL;
	size_t			sql_alloc = 256, sql_offset = 0;
	zbx_vector_uint64_t	children_triggerids;
	zbx_db_large_query_t	query;
	zbx_db_row_t		row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (0 == triggerids->values_num)
		goto out;

	sql = (char *)zbx_malloc(sql, sql_alloc);

	zbx_vector_uint64_create(&children_triggerids);

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select triggerid from trigger_discovery where");

	zbx_db_large_query_prepare_uint(&query, &sql, &sql_alloc, &sql_offset, "parent_triggerid", triggerids);

	while (NULL != (row = zbx_db_large_query_fetch(&query)))
	{
		zbx_uint64_t	triggerid;

		ZBX_STR2UINT64(triggerid, row[0]);
		zbx_vector_uint64_append(&children_triggerids, triggerid);
	}
	zbx_db_large_query_clear(&query);

	zbx_vector_uint64_sort(&children_triggerids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_uniq(&children_triggerids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_vector_uint64_setdiff(triggerids, &children_triggerids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_db_delete_triggers(&children_triggerids, audit_context_mode);
	zbx_db_delete_triggers(triggerids, audit_context_mode);

	zbx_vector_uint64_destroy(&children_triggerids);

	zbx_free(sql);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: deletes triggers by itemid                                        *
 *                                                                            *
 * Parameters:                                                                *
 *             itemids            - [IN] item ids from database               *
 *             audit_context_mode - [IN]                                      *
 *                                                                            *
 * Comments: !!! Don't forget to sync the code with PHP !!!                   *
 *                                                                            *
 ******************************************************************************/
static void	DBdelete_triggers_by_itemids(zbx_vector_uint64_t *itemids, int audit_context_mode)
{
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_vector_uint64_t	triggerids;
	zbx_db_large_query_t	query;
	zbx_db_row_t		row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() values_num:%d", __func__, itemids->values_num);

	if (0 == itemids->values_num)
		goto out;

	zbx_vector_uint64_create(&triggerids);

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select triggerid from functions where");
	zbx_db_large_query_prepare_uint(&query, &sql, &sql_alloc, &sql_offset, "itemid", itemids);

	while (NULL != (row = zbx_db_large_query_fetch(&query)))
	{
		zbx_uint64_t	triggerid;

		ZBX_STR2UINT64(triggerid, row[0]);
		zbx_vector_uint64_append(&triggerids, triggerid);
	}
	zbx_db_large_query_clear(&query);

	zbx_vector_uint64_sort(&triggerids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_uniq(&triggerids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	DBdelete_trigger_hierarchy(&triggerids, audit_context_mode);
	zbx_vector_uint64_destroy(&triggerids);
	zbx_free(sql);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: deletes graph from database                                       *
 *                                                                            *
 * Parameters:                                                                *
 *             graphids           - [IN] graph ids from database              *
 *             audit_context_mode - [IN]                                      *
 *                                                                            *
 ******************************************************************************/
void	zbx_db_delete_graphs(zbx_vector_uint64_t *graphids, int audit_context_mode)
{
	zbx_vector_uint64_t	profileids;
	const char		*profile_idx =  "web.favorite.graphids";

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() values_num:%d", __func__, graphids->values_num);

	ZBX_UNUSED(audit_context_mode);

	if (0 == graphids->values_num)
		goto out;

	zbx_vector_uint64_create(&profileids);

	/* delete from profiles */
	DBget_profiles_by_source_idxs_values(&profileids, "graphid", &profile_idx, 1, graphids);
	if (0 != profileids.values_num)
		zbx_db_execute_multiple_query("delete from profiles where", "profileid", &profileids);

	zbx_audit_graph_delete(audit_context_mode, graphids);

	/* delete from graphs */
	zbx_db_execute_multiple_query("delete from graphs where", "graphid", graphids);

	zbx_vector_uint64_destroy(&profileids);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: deletes parent graphs and auto-created children from database     *
 *                                                                            *
 * Parameters:                                                                *
 *             graphids           - [IN] graph ids from database              *
 *             audit_context_mode - [IN]                                      *
 *                                                                            *
 ******************************************************************************/
static void	DBdelete_graph_hierarchy(zbx_vector_uint64_t *graphids, int audit_context_mode)
{
	char			*sql = NULL;
	size_t			sql_alloc = 256, sql_offset = 0;
	zbx_vector_uint64_t	children_graphids;
	zbx_db_large_query_t	query;
	zbx_db_row_t		row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (0 == graphids->values_num)
		goto out;

	sql = (char *)zbx_malloc(sql, sql_alloc);

	zbx_vector_uint64_create(&children_graphids);

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select graphid from graph_discovery where");
	zbx_db_large_query_prepare_uint(&query, &sql, &sql_alloc,  &sql_offset, "parent_graphid", graphids);

	while (NULL != (row = zbx_db_large_query_fetch(&query)))
	{
		zbx_uint64_t	graphid;

		ZBX_STR2UINT64(graphid, row[0]);
		zbx_vector_uint64_append(&children_graphids, graphid);
	}
	zbx_db_large_query_clear(&query);

	if (0 != children_graphids.values_num)
	{
		zbx_vector_uint64_sort(&children_graphids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_vector_uint64_uniq(&children_graphids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		zbx_vector_uint64_setdiff(graphids, &children_graphids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_db_delete_graphs(&children_graphids, audit_context_mode);
	}

	zbx_db_delete_graphs(graphids, audit_context_mode);

	zbx_vector_uint64_destroy(&children_graphids);

	zbx_free(sql);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Parameters:                                                                *
 *             itemids            - [IN] item ids from database               *
 *             audit_context_mode - [IN]                                      *
 *                                                                            *
 ******************************************************************************/
static void	DBdelete_graphs_by_itemids(const zbx_vector_uint64_t *itemids, int audit_context_mode)
{
	char			*sql = NULL;
	size_t			sql_alloc = 256, sql_offset = 0;
	zbx_db_row_t		row;
	zbx_vector_uint64_t	graphids;
	zbx_db_large_query_t	query;
	zbx_hashset_t		item_index, graph_index;
	zbx_hashset_iter_t	iter;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() values_num:%d", __func__, itemids->values_num);

	if (0 == itemids->values_num)
		goto out;

	sql = (char *)zbx_malloc(sql, sql_alloc);
	zbx_hashset_create(&graph_index, itemids->values_num, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_create(&graphids);

	/* select all graphs with items */
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "select graphid from graphs_items where");
	zbx_db_large_query_prepare_uint(&query, &sql, &sql_alloc, &sql_offset, "itemid", itemids);

	while (NULL != (row = zbx_db_large_query_fetch(&query)))
	{
		zbx_uint64_t	graphid;

		ZBX_STR2UINT64(graphid, row[0]);
		zbx_hashset_insert(&graph_index, &graphid, sizeof(graphid));
		zbx_vector_uint64_append(&graphids, graphid);
	}
	zbx_db_large_query_clear(&query);

	if (0 == graphids.values_num)
		goto clean;

	zbx_vector_uint64_sort(&graphids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_uniq(&graphids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	/* filter out graphs with other items */

	zbx_hashset_create(&item_index, itemids->values_num, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	for (int i = 0; i < itemids->values_num; i++)
		zbx_hashset_insert(&item_index, &itemids->values[i], sizeof(itemids->values[i]));

	sql_offset = 0;
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select distinct graphid,itemid"
			" from graphs_items"
			" where");

	zbx_db_large_query_prepare_uint(&query, &sql, &sql_alloc, &sql_offset, "graphid", &graphids);

	while (NULL != (row = zbx_db_large_query_fetch(&query)))
	{
		zbx_uint64_t	graphid, itemid;

		ZBX_STR2UINT64(itemid, row[1]);

		if (NULL != zbx_hashset_search(&item_index, &itemid))
			continue;

		ZBX_STR2UINT64(graphid, row[0]);
		zbx_hashset_remove(&graph_index, &graphid);
	}
	zbx_db_large_query_clear(&query);
	zbx_hashset_destroy(&item_index);

	if (0 != graph_index.num_data)
	{
		zbx_uint64_t	*graphid;

		zbx_vector_uint64_clear(&graphids);
		zbx_hashset_iter_reset(&graph_index, &iter);
		while (NULL != (graphid = (zbx_uint64_t *)zbx_hashset_iter_next(&iter)))
			zbx_vector_uint64_append(&graphids, *graphid);

		zbx_vector_uint64_sort(&graphids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_vector_uint64_uniq(&graphids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		DBdelete_graph_hierarchy(&graphids, audit_context_mode);
	}
clean:
	zbx_vector_uint64_destroy(&graphids);
	zbx_hashset_destroy(&graph_index);
	zbx_free(sql);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: gets linked (discovered, dependent, etc) items                    *
 *                                                                            *
 ******************************************************************************/
static void	db_get_linked_items(zbx_vector_uint64_t *itemids, const char *filter, const char *field)
{
	char			*sql = NULL;
	size_t			sql_alloc = 256, sql_offset = 0;
	zbx_db_large_query_t	query;
	zbx_db_row_t		row;
	zbx_uint64_t		itemid;

	sql = (char *)zbx_malloc(sql, sql_alloc);

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select i.itemid from ");
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, filter);

	zbx_db_large_query_prepare_uint(&query, &sql, &sql_alloc, &sql_offset, field, itemids);
	while (NULL != (row = zbx_db_large_query_fetch(&query)))
	{
		ZBX_STR2UINT64(itemid, row[0]);
		zbx_vector_uint64_append(itemids, itemid);

	}
	zbx_db_large_query_clear(&query);
	zbx_free(sql);

	zbx_vector_uint64_sort(itemids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_uniq(itemids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_free(sql);
}

/******************************************************************************
 *                                                                            *
 * Purpose: deletes items from database                                       *
 *                                                                            *
 * Parameters:                                                                *
 *             itemids            - [IN] item ids from database               *
 *             audit_context_mode - [IN]                                      *
 *                                                                            *
 ******************************************************************************/
void	zbx_db_delete_items(zbx_vector_uint64_t *itemids, int audit_context_mode)
{
	zbx_vector_uint64_t	profileids;
	const char		*event_tables[] = {"events"};
	const char		*profile_idx = "web.favorite.graphids";
	unsigned char		history_mode, trends_mode;
	zbx_vector_str_t	hk_history;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() values_num:%d", __func__, itemids->values_num);

	if (0 == itemids->values_num)
		goto out;

	db_get_linked_items(itemids, "item_discovery i where", "i.parent_itemid");
	db_get_linked_items(itemids, "items i where", "i.master_itemid");

	zbx_audit_item_delete(audit_context_mode, itemids);

	zbx_vector_uint64_create(&profileids);

	DBdelete_graphs_by_itemids(itemids, audit_context_mode);
	DBdelete_triggers_by_itemids(itemids, audit_context_mode);

	zbx_config_get_hk_mode(&history_mode, &trends_mode);

	zbx_vector_str_create(&hk_history);

	if (ZBX_HK_MODE_REGULAR == history_mode)
	{
		zbx_vector_str_append(&hk_history, "history");
		zbx_vector_str_append(&hk_history, "history_str");
		zbx_vector_str_append(&hk_history, "history_uint");
		zbx_vector_str_append(&hk_history, "history_log");
		zbx_vector_str_append(&hk_history, "history_text");
		zbx_vector_str_append(&hk_history, "history_bin");
	}

	if (ZBX_HK_MODE_REGULAR == trends_mode)
	{
		zbx_vector_str_append(&hk_history, "trends");
		zbx_vector_str_append(&hk_history, "trends_uint");
	}

	if (0 != hk_history.values_num)
		DBadd_to_housekeeper(itemids, "itemid", (const char * const *)hk_history.values, hk_history.values_num);

	zbx_vector_str_destroy(&hk_history);

	/* add housekeeper task to delete problems associated with item, this allows old events to be deleted */
	DBadd_to_housekeeper(itemids, "itemid", event_tables, ARRSIZE(event_tables));
	DBadd_to_housekeeper(itemids, "lldruleid", event_tables, ARRSIZE(event_tables));

	/* delete from profiles */
	DBget_profiles_by_source_idxs_values(&profileids, "itemid", &profile_idx, 1, itemids);
	if (0 != profileids.values_num)
		zbx_db_execute_multiple_query("delete from profiles where", "profileid", &profileids);

	zbx_db_execute_multiple_query("delete from item_tag where", "itemid", itemids);
	zbx_db_execute_multiple_query("delete from item_preproc where", "itemid", itemids);
	zbx_db_execute_multiple_query("delete from functions where", "itemid", itemids);
	zbx_db_execute_multiple_query("update items set master_itemid=null where master_itemid is not null and",
			"itemid", itemids);
	zbx_db_execute_multiple_query("delete from items where", "itemid", itemids);

	zbx_vector_uint64_destroy(&profileids);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: deletes web tests from database                                   *
 *                                                                            *
 * Parameters:                                                                *
 *             httptestids        - [IN] httptest ids from database           *
 *             audit_context_mode - [IN]                                      *
 *                                                                            *
 * Comments: !!! Don't forget to sync the code with PHP !!!                   *
 *                                                                            *
 ******************************************************************************/
static void	DBdelete_httptests(const zbx_vector_uint64_t *httptestids, int audit_context_mode)
{
	char			*sql = NULL;
	size_t			sql_alloc = 256, sql_offset = 0;
	zbx_vector_uint64_t	itemids;
	zbx_vector_uint64_t	httpstepids;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() values_num:%d", __func__, httptestids->values_num);

	if (0 == httptestids->values_num)
		goto out;

	sql = (char *)zbx_malloc(sql, sql_alloc);
	zbx_vector_uint64_create(&itemids);
	zbx_vector_uint64_create(&httpstepids);

	/* httpstepitem, httptestitem */
	sql_offset = 0;
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select hsi.itemid,i.name,i.flags"
			" from httpstepitem hsi,httpstep hs,items i"
			" where hsi.httpstepid=hs.httpstepid and i.itemid=hsi.itemid"
				" and");
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "hs.httptestid",
			httptestids->values, httptestids->values_num);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			" union all "
			"select i.itemid,i.name,i.flags"
			" from httptestitem ht,items i"
			" where ht.itemid=i.itemid and");
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "httptestid",
			httptestids->values, httptestids->values_num);

	zbx_db_select_uint64(sql, &itemids);

	sql_offset = 0;
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select httpstepid from httpstep where");
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "httptestid",
			httptestids->values, httptestids->values_num);
	zbx_db_select_uint64(sql, &httpstepids);

	sql_offset = 0;
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from httptest_field where");
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "httptestid",
			httptestids->values, httptestids->values_num);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from httptestitem where");
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "httptestid",
			httptestids->values, httptestids->values_num);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from httpstep_field where");
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "httpstepid",
			httpstepids.values, httpstepids.values_num);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from httpstepitem where");
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "httpstepid",
			httpstepids.values, httpstepids.values_num);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from httpstep where");
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "httpstepid",
			httpstepids.values, httpstepids.values_num);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");

	zbx_db_execute("%s", sql);

	zbx_db_delete_items(&itemids, audit_context_mode);

	sql_offset = 0;
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from httptest where");
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "httptestid",
			httptestids->values, httptestids->values_num);
	zbx_db_execute("%s", sql);

	zbx_vector_uint64_destroy(&httpstepids);
	zbx_vector_uint64_destroy(&itemids);
	zbx_free(sql);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Parameters: del_group_prototypeids - [IN] list of group_prototypeids which *
 *                                           will be deleted                  *
 *                                                                            *
 ******************************************************************************/
static void	DBgroup_prototypes_delete(const zbx_vector_uint64_t *del_group_prototypeids)
{
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset;
	zbx_vector_uint64_t	groupids;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (0 == del_group_prototypeids->values_num)
		return;

	zbx_vector_uint64_create(&groupids);

	sql_offset = 0;
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select groupid from group_discovery where");
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "parent_group_prototypeid",
			del_group_prototypeids->values, del_group_prototypeids->values_num);

	zbx_db_select_uint64(sql, &groupids);

	zbx_db_delete_groups(&groupids);

	sql_offset = 0;
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from group_prototype where");
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "group_prototypeid",
			del_group_prototypeids->values, del_group_prototypeids->values_num);

	zbx_db_execute("%s", sql);

	zbx_vector_uint64_destroy(&groupids);
	zbx_free(sql);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: deletes host prototypes from database                             *
 *                                                                            *
 * Parameters:                                                                *
 *             host_prototype_ids   - [IN]                                    *
 *             host_prototype_names - [IN]                                    *
 *             audit_context_mode   - [IN]                                    *
 *                                                                            *
 ******************************************************************************/
static void	DBdelete_host_prototypes(const zbx_vector_uint64_t *host_prototype_ids,
		const zbx_vector_str_t *host_prototype_names, int audit_context_mode)
{
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset;
	zbx_vector_uint64_t	hostids, group_prototype_ids;
	zbx_vector_str_t	hostnames;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (0 == host_prototype_ids->values_num)
		goto out;

	/* delete discovered hosts */

	zbx_vector_uint64_create(&hostids);
	zbx_vector_str_create(&hostnames);
	zbx_vector_uint64_create(&group_prototype_ids);

	sql_offset = 0;
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select hd.hostid,h.name from host_discovery hd,hosts h "
			"where hd.hostid=h.hostid and");
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "parent_hostid",
			host_prototype_ids->values, host_prototype_ids->values_num);

	if (FAIL == DBselect_ids_names(sql, &hostids, &hostnames))
		goto clean;

	if (0 != hostids.values_num)
		zbx_db_delete_hosts(&hostids, &hostnames, audit_context_mode);

	/* delete group prototypes */

	sql_offset = 0;
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select group_prototypeid from group_prototype where");
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid",
			host_prototype_ids->values, host_prototype_ids->values_num);

	zbx_db_select_uint64(sql, &group_prototype_ids);
	DBgroup_prototypes_delete(&group_prototype_ids);

	/* delete host prototypes */

	sql_offset = 0;
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from host_tag where");
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid",
			host_prototype_ids->values, host_prototype_ids->values_num);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");
	zbx_db_execute_overflowed_sql(&sql, &sql_alloc, &sql_offset);

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from hosts where");
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid",
			host_prototype_ids->values, host_prototype_ids->values_num);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");

	zbx_db_execute("%s", sql);

	for (int i = 0; i < host_prototype_ids->values_num; i++)
	{
		zbx_audit_host_prototype_del(audit_context_mode, host_prototype_ids->values[i],
				host_prototype_names->values[i]);
	}
clean:
	zbx_vector_uint64_destroy(&group_prototype_ids);
	zbx_vector_uint64_destroy(&hostids);
	zbx_vector_str_clear_ext(&hostnames, zbx_str_free);
	zbx_vector_str_destroy(&hostnames);
	zbx_free(sql);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: deletes template web scenarios from host                          *
 *                                                                            *
 * Parameters:                                                                *
 *             hostid             - [IN] host id from database                *
 *             templateids        - [IN]                                      *
 *             audit_context_mode - [IN]                                      *
 *                                                                            *
 ******************************************************************************/
static void	DBdelete_template_httptests(zbx_uint64_t hostid, const zbx_vector_uint64_t *templateids,
		int audit_context_mode)
{
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_vector_uint64_t	httptestids;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&httptestids);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select h.httptestid,h.name"
			" from httptest h"
				" join httptest t"
					" on");
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "t.hostid", templateids->values,
			templateids->values_num);
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
						" and t.httptestid=h.templateid"
			" where h.hostid=" ZBX_FS_UI64, hostid);

	if (FAIL == zbx_audit_DBselect_delete_for_httptest(audit_context_mode, sql, &httptestids))
		goto clean;

	DBdelete_httptests(&httptestids, audit_context_mode);
clean:
	zbx_vector_uint64_destroy(&httptestids);
	zbx_free(sql);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: deletes template graphs from host                                 *
 *                                                                            *
 * Parameters:                                                                *
 *             hostid             - [IN] host id from database                *
 *             templateids        - [IN]                                      *
 *             audit_context_mode - [IN]                                      *
 *                                                                            *
 * Comments: !!! Don't forget to sync the code with PHP !!!                   *
 *                                                                            *
 ******************************************************************************/
static void	DBdelete_template_graphs(zbx_uint64_t hostid, const zbx_vector_uint64_t *templateids,
		int audit_context_mode)
{
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_vector_uint64_t	graphids;
	zbx_db_large_query_t	query;
	zbx_db_row_t		row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&graphids);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select gi.graphid"
			" from graphs_items gi,items i,items ti"
			" where gi.itemid=i.itemid"
				" and i.templateid=ti.itemid"
				" and i.hostid=" ZBX_FS_UI64
				" and",
			hostid);

	zbx_db_large_query_prepare_uint(&query, &sql, &sql_alloc, &sql_offset, "ti.hostid", templateids);

	while (NULL != (row = zbx_db_large_query_fetch(&query)))
	{
		zbx_uint64_t	graphid;

		ZBX_STR2UINT64(graphid, row[0]);
		zbx_vector_uint64_append(&graphids, graphid);
	}
	zbx_db_large_query_clear(&query);

	if (0 != graphids.values_num)
	{
		zbx_vector_uint64_sort(&graphids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_vector_uint64_uniq(&graphids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		DBdelete_graph_hierarchy(&graphids, audit_context_mode);
	}

	zbx_vector_uint64_destroy(&graphids);
	zbx_free(sql);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: deletes template triggers from host                               *
 *                                                                            *
 * Parameters:                                                                *
 *             hostid             - [IN] host id from database                *
 *             templateids        - [IN]                                      *
 *             audit_context_mode - [IN]                                      *
 *                                                                            *
 * Comments: !!! Don't forget to sync the code with PHP !!!                   *
 *                                                                            *
 ******************************************************************************/
static void	DBdelete_template_triggers(zbx_uint64_t hostid, const zbx_vector_uint64_t *templateids,
		int audit_context_mode)
{
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_vector_uint64_t	triggerids;
	zbx_db_large_query_t	query;
	zbx_db_row_t		row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&triggerids);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select f.triggerid"
			" from functions f,items i,items ti"
			" where f.itemid=i.itemid"
				" and i.templateid=ti.itemid"
				" and i.hostid=" ZBX_FS_UI64
				" and",
			hostid);

	zbx_db_large_query_prepare_uint(&query, &sql, &sql_alloc, &sql_offset, "ti.hostid", templateids);

	while (NULL != (row = zbx_db_large_query_fetch(&query)))
	{
		zbx_uint64_t	triggerid;

		ZBX_STR2UINT64(triggerid, row[0]);
		zbx_vector_uint64_append(&triggerids, triggerid);
	}
	zbx_db_large_query_clear(&query);

	if (0 < triggerids.values_num)
	{
		zbx_vector_uint64_sort(&triggerids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_vector_uint64_uniq(&triggerids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		DBdelete_trigger_hierarchy(&triggerids, audit_context_mode);
	}

	zbx_vector_uint64_destroy(&triggerids);
	zbx_free(sql);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: deletes template host prototypes from host                        *
 *                                                                            *
 * Parameters:                                                                *
 *             hostid             - [IN] host id from database                *
 *             templateids        - [IN]                                      *
 *             audit_context_mode - [IN]                                      *
 *                                                                            *
 ******************************************************************************/
static void	DBdelete_template_host_prototypes(zbx_uint64_t hostid, const zbx_vector_uint64_t *templateids,
		int audit_context_mode)
{
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_vector_uint64_t	host_prototype_ids;
	zbx_vector_str_t	host_prototype_names;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&host_prototype_ids);
	zbx_vector_str_create(&host_prototype_names);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select hp.hostid,hp.name"
			" from items hi,host_discovery hhd,hosts hp,host_discovery thd,items ti"
			" where hi.itemid=hhd.parent_itemid"
				" and hhd.hostid=hp.hostid"
				" and hp.templateid=thd.hostid"
				" and thd.parent_itemid=ti.itemid"
				" and hi.hostid=" ZBX_FS_UI64
				" and",
			hostid);
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "ti.hostid", templateids->values,
			templateids->values_num);
	if (FAIL == DBselect_ids_names(sql, &host_prototype_ids, &host_prototype_names))
		goto clean;
	DBdelete_host_prototypes(&host_prototype_ids, &host_prototype_names, audit_context_mode);
clean:
	zbx_free(sql);

	zbx_vector_uint64_destroy(&host_prototype_ids);
	zbx_vector_str_clear_ext(&host_prototype_names, zbx_str_free);
	zbx_vector_str_destroy(&host_prototype_names);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: deletes template items from host                                  *
 *                                                                            *
 * Parameters:                                                                *
 *             hostid             - [IN] host id from database                *
 *             templateids        - [IN]                                      *
 *             audit_context_mode - [IN]                                      *
 *                                                                            *
 * Comments: !!! Don't forget to sync the code with PHP !!!                   *
 *                                                                            *
 ******************************************************************************/
static void	DBdelete_template_items(zbx_uint64_t hostid, const zbx_vector_uint64_t *templateids,
		int audit_context_mode)
{
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_vector_uint64_t	itemids;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&itemids);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select distinct i.itemid,i.name,i.flags"
			" from items i,items ti"
			" where i.templateid=ti.itemid"
				" and i.hostid=" ZBX_FS_UI64
				" and",
			hostid);
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "ti.hostid", templateids->values,
			templateids->values_num);

	zbx_db_select_uint64(sql, &itemids);
	zbx_db_delete_items(&itemids, audit_context_mode);

	zbx_vector_uint64_destroy(&itemids);
	zbx_free(sql);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Description: retrieves already linked templates for specified host         *
 *                                                                            *
 * Parameters: hostid      - [IN] host id from database                       *
 *             templateids - [IN/OUT]                                         *
 *                                                                            *
 ******************************************************************************/
static void	get_templates_by_hostid(zbx_uint64_t hostid, zbx_vector_uint64_t *templateids)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	zbx_uint64_t	templateid;

	result = zbx_db_select(
			"select templateid"
			" from hosts_templates"
			" where hostid=" ZBX_FS_UI64,
			hostid);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		ZBX_STR2UINT64(templateid, row[0]);
		zbx_vector_uint64_append(templateids, templateid);
	}
	zbx_db_free_result(result);

	zbx_vector_uint64_sort(templateids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
}

/******************************************************************************
 *                                                                            *
 * Parameters:                                                                *
 *             hostid             - [IN] host id from database                *
 *             hostname           - [IN]                                      *
 *             del_templateids    - [IN]                                      *
 *             audit_context_mode - [IN]                                      *
 *             error              - [OUT] error message                       *
 *                                                                            *
 * Comments: !!! Don't forget to sync the code with PHP !!!                   *
 *                                                                            *
 ******************************************************************************/
int	zbx_db_delete_template_elements(zbx_uint64_t hostid, const char *hostname, zbx_vector_uint64_t *del_templateids,
		int audit_context_mode, char **error)
{
	char			*sql = NULL, err[MAX_STRING_LEN];
	size_t			sql_alloc = 128, sql_offset = 0;
	zbx_vector_uint64_t	templateids;
	int			res = SUCCEED;
	zbx_db_result_t		result;
	zbx_db_row_t		row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&templateids);

	get_templates_by_hostid(hostid, &templateids);

	for (int i = 0; i < del_templateids->values_num; i++)
	{
		int	index;

		if (FAIL == (index = zbx_vector_uint64_bsearch(&templateids, del_templateids->values[i],
				ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
		{
			/* template already unlinked */
			zbx_vector_uint64_remove(del_templateids, i--);
		}
		else
			zbx_vector_uint64_remove(&templateids, index);
	}

	/* all templates already unlinked */
	if (0 == del_templateids->values_num)
		goto clean;

	if (SUCCEED != (res = validate_linked_templates(&templateids, err, sizeof(err))))
	{
		*error = zbx_strdup(NULL, err);
		goto clean;
	}

	zbx_audit_host_create_entry(audit_context_mode, ZBX_AUDIT_ACTION_UPDATE, hostid, hostname);

	DBdelete_template_httptests(hostid, del_templateids, audit_context_mode);
	DBdelete_template_graphs(hostid, del_templateids, audit_context_mode);
	DBdelete_template_triggers(hostid, del_templateids, audit_context_mode);
	DBdelete_template_host_prototypes(hostid, del_templateids, audit_context_mode);

	/* removing items will remove discovery rules related to them */
	DBdelete_template_items(hostid, del_templateids, audit_context_mode);

	/* need to find hosttemplateids for audit */
	sql = (char *)zbx_malloc(sql, sql_alloc);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select hosttemplateid,templateid from hosts_templates"
			" where hostid=" ZBX_FS_UI64
				" and",
			hostid);
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "templateid",
			del_templateids->values, del_templateids->values_num);

	result = zbx_db_select("%s", sql);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_uint64_t	hosttemplateid, templateid;

		ZBX_STR2UINT64(hosttemplateid, row[0]);
		ZBX_STR2UINT64(templateid, row[1]);
		zbx_audit_host_update_json_delete_parent_template(audit_context_mode, hostid, hosttemplateid);
	}

	zbx_db_free_result(result);

	sql_offset = 0;

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"delete from hosts_templates"
			" where hostid=" ZBX_FS_UI64
				" and",
			hostid);
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "templateid",
			del_templateids->values, del_templateids->values_num);
	zbx_db_execute("%s", sql);

	zbx_free(sql);
clean:
	zbx_vector_uint64_destroy(&templateids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(res));

	return res;
}

typedef struct
{
	zbx_uint64_t	group_prototypeid;
	zbx_uint64_t	groupid;
	zbx_uint64_t	templateid_host;	/* for audit update */
	zbx_uint64_t	templateid;		/* reference to parent group_prototypeid */
	char		*name;
}
zbx_group_prototype_t;

static void	DBgroup_prototype_clean(zbx_group_prototype_t *group_prototype)
{
	zbx_free(group_prototype->name);
	zbx_free(group_prototype);
}

static void	DBgroup_prototypes_clean(zbx_vector_ptr_t *group_prototypes)
{
	int	i;

	for (i = 0; i < group_prototypes->values_num; i++)
		DBgroup_prototype_clean((zbx_group_prototype_t *)group_prototypes->values[i]);
}

typedef struct
{
	zbx_uint64_t	hostmacroid;
	char		*macro;
	char		*value_orig;
	char		*value;
	char		*description_orig;
	char		*description;
	unsigned char	type_orig;
	unsigned char	type;
	unsigned char	automatic;
#define ZBX_FLAG_HPMACRO_RESET_FLAG		__UINT64_C(0x00000000)
#define ZBX_FLAG_HPMACRO_UPDATE_VALUE		__UINT64_C(0x00000001)
#define ZBX_FLAG_HPMACRO_UPDATE_DESCRIPTION	__UINT64_C(0x00000002)
#define ZBX_FLAG_HPMACRO_UPDATE_TYPE		__UINT64_C(0x00000004)
#define ZBX_FLAG_HPMACRO_UPDATE	\
		(ZBX_FLAG_HPMACRO_UPDATE_VALUE | ZBX_FLAG_HPMACRO_UPDATE_DESCRIPTION | ZBX_FLAG_HPMACRO_UPDATE_TYPE)
	zbx_uint64_t	flags;
}
zbx_macros_prototype_t;

ZBX_PTR_VECTOR_DECL(macros, zbx_macros_prototype_t *)
ZBX_PTR_VECTOR_IMPL(macros, zbx_macros_prototype_t *)

typedef struct
{
	char		*community;
	char		*securityname;
	char		*authpassphrase;
	char		*privpassphrase;
	char		*contextname;
	unsigned char	securitylevel;
	unsigned char	authprotocol;
	unsigned char	privprotocol;
	unsigned char	version;
	unsigned char	bulk;
}
zbx_interface_prototype_snmp_t;

typedef struct
{
	zbx_uint64_t	interfaceid;
	unsigned char	main;
	unsigned char	type;
	unsigned char	useip;
	char		*ip;
	char		*dns;
	char		*port;

	union _data
	{
		zbx_interface_prototype_snmp_t	*snmp;
	}
	data;
}
zbx_interfaces_prototype_t;

ZBX_PTR_VECTOR_DECL(interfaces, zbx_interfaces_prototype_t *)
ZBX_PTR_VECTOR_IMPL(interfaces, zbx_interfaces_prototype_t *)

typedef struct
{
	zbx_uint64_t			templateid;		/* link to parent template */
	zbx_uint64_t			hostid;
	zbx_uint64_t			itemid;			/* discovery rule id */
	zbx_vector_uint64_t		lnk_templateids;	/* list of templates which should be linked */
	zbx_vector_ptr_t		group_prototypes;	/* list of group prototypes */
	zbx_vector_macros_t		hostmacros;		/* list of user macros */
	zbx_vector_db_tag_ptr_t		tags;			/* list of host prototype tags */
	zbx_vector_db_tag_ptr_t		new_tags;		/* list of host prototype template tags */
	zbx_vector_interfaces_t		interfaces;		/* list of interfaces */
	char				*host;
	char				*name_orig;
	char				*name;
	unsigned char			status_orig;
	unsigned char			status;
#define ZBX_FLAG_HPLINK_RESET_FLAG			0x00
#define ZBX_FLAG_HPLINK_UPDATE_NAME			0x01
#define ZBX_FLAG_HPLINK_UPDATE_STATUS			0x02
#define ZBX_FLAG_HPLINK_UPDATE_DISCOVER			0x04
#define ZBX_FLAG_HPLINK_UPDATE_CUSTOM_INTERFACES	0x08
#define ZBX_FLAG_HPLINK_UPDATE_INVENTORY_MODE		0x10
	unsigned char			flags;
	unsigned char			discover_orig;
	unsigned char			discover;
	unsigned char			custom_interfaces_orig;
	unsigned char			custom_interfaces;
	signed char			inventory_mode_orig;
	signed char			inventory_mode;
	zbx_uint64_t			templateid_host;
}
zbx_host_prototype_t;

static void	DBhost_macro_free(zbx_macros_prototype_t *hostmacro)
{
	if (0 != (hostmacro->flags & ZBX_FLAG_HPMACRO_UPDATE_VALUE))
		zbx_free(hostmacro->value_orig);

	if (0 != (hostmacro->flags & ZBX_FLAG_HPMACRO_UPDATE_DESCRIPTION))
		zbx_free(hostmacro->description_orig);

	zbx_free(hostmacro->macro);
	zbx_free(hostmacro->value);
	zbx_free(hostmacro->description);
	zbx_free(hostmacro);
}

static void	DBhost_interface_free(zbx_interfaces_prototype_t *interface)
{
	zbx_free(interface->ip);
	zbx_free(interface->dns);
	zbx_free(interface->port);

	if (INTERFACE_TYPE_SNMP == interface->type)
	{
		zbx_free(interface->data.snmp->community);
		zbx_free(interface->data.snmp->securityname);
		zbx_free(interface->data.snmp->authpassphrase);
		zbx_free(interface->data.snmp->privpassphrase);
		zbx_free(interface->data.snmp->contextname);
		zbx_free(interface->data.snmp);
	}

	zbx_free(interface);
}

static void	DBhost_prototype_clean(zbx_host_prototype_t *host_prototype)
{
	if (0 != (host_prototype->flags & ZBX_FLAG_HPLINK_UPDATE_NAME))
		zbx_free(host_prototype->name_orig);

	zbx_free(host_prototype->name);
	zbx_free(host_prototype->host);
	zbx_vector_macros_clear_ext(&host_prototype->hostmacros, DBhost_macro_free);
	zbx_vector_macros_destroy(&host_prototype->hostmacros);
	zbx_vector_db_tag_ptr_clear_ext(&host_prototype->tags, zbx_db_tag_free);
	zbx_vector_db_tag_ptr_destroy(&host_prototype->tags);
	zbx_vector_db_tag_ptr_clear_ext(&host_prototype->new_tags, zbx_db_tag_free);
	zbx_vector_db_tag_ptr_destroy(&host_prototype->new_tags);
	zbx_vector_interfaces_clear_ext(&host_prototype->interfaces, DBhost_interface_free);
	zbx_vector_interfaces_destroy(&host_prototype->interfaces);
	DBgroup_prototypes_clean(&host_prototype->group_prototypes);
	zbx_vector_ptr_destroy(&host_prototype->group_prototypes);
	zbx_vector_uint64_destroy(&host_prototype->lnk_templateids);
	zbx_free(host_prototype);
}

static void	DBhost_prototypes_clean(zbx_vector_ptr_t *host_prototypes)
{
	int	i;

	for (i = 0; i < host_prototypes->values_num; i++)
		DBhost_prototype_clean((zbx_host_prototype_t *)host_prototypes->values[i]);
}

/******************************************************************************
 *                                                                            *
 * Comments: auxiliary function for DBcopy_template_host_prototypes()         *
 *                                                                            *
 ******************************************************************************/
static int	DBis_regular_host(zbx_uint64_t hostid)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	int		ret = FAIL;

	result = zbx_db_select("select flags from hosts where hostid=" ZBX_FS_UI64, hostid);

	if (NULL != (row = zbx_db_fetch(result)))
	{
		if (0 == atoi(row[0]))
			ret = SUCCEED;
	}
	zbx_db_free_result(result);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Comments: auxiliary function for DBcopy_template_host_prototypes()         *
 *                                                                            *
 ******************************************************************************/
static void	DBhost_prototypes_make(zbx_uint64_t hostid, zbx_vector_uint64_t *templateids,
		zbx_vector_ptr_t *host_prototypes)
{
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_vector_uint64_t	itemids;
	zbx_host_prototype_t	*host_prototype;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&itemids);

	/* selects host prototypes from templates */

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select hi.itemid,th.hostid,th.host,th.name,th.status,th.discover,th.custom_interfaces,"
				"hinv.inventory_mode"
			" from items hi,items ti,host_discovery thd,hosts th"
			" left join host_inventory hinv on hinv.hostid=th.hostid"
			" where hi.templateid=ti.itemid"
				" and ti.itemid=thd.parent_itemid"
				" and thd.hostid=th.hostid"
				" and hi.hostid=" ZBX_FS_UI64
				" and",
			hostid);
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "ti.hostid", templateids->values,
			templateids->values_num);

	result = zbx_db_select("%s", sql);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		host_prototype = (zbx_host_prototype_t *)zbx_malloc(NULL, sizeof(zbx_host_prototype_t));

		host_prototype->hostid = 0;
		ZBX_STR2UINT64(host_prototype->itemid, row[0]);
		ZBX_STR2UINT64(host_prototype->templateid, row[1]);
		zbx_vector_uint64_create(&host_prototype->lnk_templateids);
		zbx_vector_ptr_create(&host_prototype->group_prototypes);
		zbx_vector_macros_create(&host_prototype->hostmacros);
		zbx_vector_db_tag_ptr_create(&host_prototype->tags);
		zbx_vector_db_tag_ptr_create(&host_prototype->new_tags);
		zbx_vector_interfaces_create(&host_prototype->interfaces);
		host_prototype->host = zbx_strdup(NULL, row[2]);
		host_prototype->name = zbx_strdup(NULL, row[3]);
		ZBX_STR2UCHAR(host_prototype->status, row[4]);
		host_prototype->flags = ZBX_FLAG_HPLINK_RESET_FLAG;
		ZBX_STR2UCHAR(host_prototype->discover, row[5]);
		ZBX_STR2UCHAR(host_prototype->custom_interfaces, row[6]);
		host_prototype->name_orig = NULL;
		host_prototype->status_orig = 0;
		host_prototype->discover_orig = 0;
		host_prototype->templateid_host = 0;
		host_prototype->custom_interfaces_orig = 0;

		if (SUCCEED == zbx_db_is_null(row[7]))
			host_prototype->inventory_mode = HOST_INVENTORY_DISABLED;
		else
			host_prototype->inventory_mode = (signed char)atoi(row[7]);

		host_prototype->inventory_mode_orig = HOST_INVENTORY_DISABLED;

		zbx_vector_ptr_append(host_prototypes, host_prototype);
		zbx_vector_uint64_append(&itemids, host_prototype->itemid);
	}
	zbx_db_free_result(result);

	if (0 != host_prototypes->values_num)
	{
		zbx_uint64_t	itemid;
		unsigned char	status;
		int		i;

		zbx_vector_uint64_sort(&itemids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_vector_uint64_uniq(&itemids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		/* selects host prototypes from host */

		sql_offset = 0;
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"select i.itemid,h.hostid,h.host,h.name,h.status,h.discover,h.custom_interfaces,"
					"h.templateid,hinv.inventory_mode"
				" from items i,host_discovery hd,hosts h"
				" left join host_inventory hinv on hinv.hostid=h.hostid"
				" where i.itemid=hd.parent_itemid"
					" and hd.hostid=h.hostid"
					" and i.hostid=" ZBX_FS_UI64
					" and",
				hostid);
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "i.itemid", itemids.values,
				itemids.values_num);

		result = zbx_db_select("%s", sql);

		while (NULL != (row = zbx_db_fetch(result)))
		{
			ZBX_STR2UINT64(itemid, row[0]);

			for (i = 0; i < host_prototypes->values_num; i++)
			{
				host_prototype = (zbx_host_prototype_t *)host_prototypes->values[i];

				if (host_prototype->itemid == itemid && 0 == strcmp(host_prototype->host, row[2]))
				{
					signed char	inventory_mode_null_processed;

					ZBX_STR2UINT64(host_prototype->hostid, row[1]);

					if (0 != strcmp(host_prototype->name, row[3]))
					{
						host_prototype->flags |= ZBX_FLAG_HPLINK_UPDATE_NAME;
						host_prototype->name_orig = zbx_strdup(NULL, row[3]);
					}

					if (host_prototype->status != (status = (unsigned char)atoi(row[4])))
					{
						host_prototype->flags |= ZBX_FLAG_HPLINK_UPDATE_STATUS;
						host_prototype->status_orig = status;
					}

					if (host_prototype->discover != (unsigned char)atoi(row[5]))
					{
						host_prototype->flags |= ZBX_FLAG_HPLINK_UPDATE_DISCOVER;
						host_prototype->discover_orig = (unsigned char)atoi(row[5]);
					}

					if (host_prototype->custom_interfaces != (unsigned char)atoi(row[6]))
					{
						host_prototype->flags |= ZBX_FLAG_HPLINK_UPDATE_CUSTOM_INTERFACES;
						host_prototype->custom_interfaces_orig = (unsigned char)atoi(row[6]);
					}

					if (SUCCEED == zbx_db_is_null(row[8]))
						inventory_mode_null_processed = HOST_INVENTORY_DISABLED;
					else
						inventory_mode_null_processed = (signed char)atoi(row[8]);

					if (host_prototype->inventory_mode != inventory_mode_null_processed)
					{
						host_prototype->flags |= ZBX_FLAG_HPLINK_UPDATE_INVENTORY_MODE;
						host_prototype->inventory_mode_orig = inventory_mode_null_processed;
					}

					ZBX_DBROW2UINT64(host_prototype->templateid_host, row[7]);

					break;
				}
			}
		}
		zbx_db_free_result(result);
	}

	zbx_free(sql);

	zbx_vector_uint64_destroy(&itemids);

	/* sort by templateid */
	zbx_vector_ptr_sort(host_prototypes, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Parameters:                                                                *
 *             host_prototypes     - [IN/OUT] list of host prototypes, should *
 *                                            be sorted by templateid         *
 *             audit_context_mode  - [IN]                                     *
 *             del_hosttemplateids - [OUT] list of hosttemplateids which      *
 *                                         should be deleted                  *
 *                                                                            *
 * Comments: auxiliary function for DBcopy_template_host_prototypes()         *
 *                                                                            *
 ******************************************************************************/
static void	DBhost_prototypes_templates_make(zbx_vector_ptr_t *host_prototypes,
		int audit_context_mode, zbx_vector_uint64_t *del_hosttemplateids)
{
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_vector_uint64_t	hostids;
	zbx_uint64_t		hostid, templateid, hosttemplateid;
	zbx_host_prototype_t	*host_prototype;
	int			i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&hostids);

	/* select list of templates which should be linked to host prototypes */

	for (i = 0; i < host_prototypes->values_num; i++)
	{
		host_prototype = (zbx_host_prototype_t *)host_prototypes->values[i];

		zbx_vector_uint64_append(&hostids, host_prototype->templateid);
	}

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select hostid,templateid"
			" from hosts_templates"
			" where");
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid", hostids.values, hostids.values_num);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " order by hostid,templateid");

	result = zbx_db_select("%s", sql);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		ZBX_STR2UINT64(hostid, row[0]);
		ZBX_STR2UINT64(templateid, row[1]);

		if (FAIL == (i = zbx_vector_ptr_bsearch(host_prototypes, &hostid, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		host_prototype = (zbx_host_prototype_t *)host_prototypes->values[i];

		zbx_vector_uint64_append(&host_prototype->lnk_templateids, templateid);
	}
	zbx_db_free_result(result);

	/* select list of templates which are already linked to host prototypes */

	zbx_vector_uint64_clear(&hostids);

	for (i = 0; i < host_prototypes->values_num; i++)
	{
		host_prototype = (zbx_host_prototype_t *)host_prototypes->values[i];

		if (0 == host_prototype->hostid)
			continue;

		zbx_vector_uint64_append(&hostids, host_prototype->hostid);
	}

	if (0 != hostids.values_num)
	{
		zbx_vector_uint64_sort(&hostids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		sql_offset = 0;
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				"select hostid,templateid,hosttemplateid"
				" from hosts_templates"
				" where");
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid", hostids.values, hostids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " order by hosttemplateid");

		result = zbx_db_select("%s", sql);

		while (NULL != (row = zbx_db_fetch(result)))
		{
			ZBX_STR2UINT64(hostid, row[0]);
			ZBX_STR2UINT64(templateid, row[1]);

			for (i = 0; i < host_prototypes->values_num; i++)
			{
				host_prototype = (zbx_host_prototype_t *)host_prototypes->values[i];

				if (host_prototype->hostid == hostid)
				{
					if (FAIL == (i = zbx_vector_uint64_bsearch(&host_prototype->lnk_templateids,
							templateid, ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
					{
						ZBX_STR2UINT64(hosttemplateid, row[2]);
						zbx_vector_uint64_append(del_hosttemplateids, hosttemplateid);

						zbx_audit_host_prototype_create_entry(audit_context_mode,
								ZBX_AUDIT_ACTION_UPDATE, host_prototype->hostid,
								host_prototype->host);

						zbx_audit_host_prototype_update_json_delete_parent_template(
								audit_context_mode, host_prototype->hostid,
								hosttemplateid);
					}
					else
						zbx_vector_uint64_remove(&host_prototype->lnk_templateids, i);

					break;
				}
			}

			if (i == host_prototypes->values_num)
				THIS_SHOULD_NEVER_HAPPEN;
		}
		zbx_db_free_result(result);
	}

	zbx_vector_uint64_destroy(&hostids);

	zbx_free(sql);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/********************************************************************************
 *                                                                              *
 * Parameters:                                                                  *
 *             host_prototypes        - [IN/OUT] list of host prototypes,       *
 *                                               should be sorted by templateid *
 *             del_group_prototypeids - [OUT] sorted list of group_prototypeid  *
 *                                            which should be deleted           *
 *                                                                              *
 * Comments: auxiliary function for DBcopy_template_host_prototypes()           *
 *                                                                              *
 ********************************************************************************/
static void	DBhost_prototypes_groups_make(zbx_vector_ptr_t *host_prototypes,
		zbx_vector_uint64_t *del_group_prototypeids)
{
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_vector_uint64_t	hostids;
	zbx_uint64_t		hostid, groupid, group_prototypeid;
	zbx_host_prototype_t	*host_prototype;
	zbx_group_prototype_t	*group_prototype;
	int			i;

	zbx_vector_uint64_create(&hostids);

	/* select list of groups which should be linked to host prototypes */

	for (i = 0; i < host_prototypes->values_num; i++)
	{
		host_prototype = (zbx_host_prototype_t *)host_prototypes->values[i];

		zbx_vector_uint64_append(&hostids, host_prototype->templateid);
	}

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select hostid,name,groupid,group_prototypeid"
			" from group_prototype"
			" where");
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid", hostids.values, hostids.values_num);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " order by hostid");

	result = zbx_db_select("%s", sql);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		ZBX_STR2UINT64(hostid, row[0]);

		if (FAIL == (i = zbx_vector_ptr_bsearch(host_prototypes, &hostid, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		host_prototype = (zbx_host_prototype_t *)host_prototypes->values[i];

		group_prototype = (zbx_group_prototype_t *)zbx_malloc(NULL, sizeof(zbx_group_prototype_t));
		group_prototype->group_prototypeid = 0;
		group_prototype->name = zbx_strdup(NULL, row[1]);
		ZBX_DBROW2UINT64(group_prototype->groupid, row[2]);
		ZBX_STR2UINT64(group_prototype->templateid, row[3]);
		group_prototype->templateid_host = 0;

		zbx_vector_ptr_append(&host_prototype->group_prototypes, group_prototype);
	}
	zbx_db_free_result(result);

	/* select list of group prototypes which already linked to host prototypes */

	zbx_vector_uint64_clear(&hostids);

	for (i = 0; i < host_prototypes->values_num; i++)
	{
		host_prototype = (zbx_host_prototype_t *)host_prototypes->values[i];

		if (0 == host_prototype->hostid)
			continue;

		zbx_vector_uint64_append(&hostids, host_prototype->hostid);
	}

	if (0 != hostids.values_num)
	{
		zbx_vector_uint64_sort(&hostids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		sql_offset = 0;
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				"select hostid,group_prototypeid,groupid,name,templateid from group_prototype where");
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid", hostids.values, hostids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " order by group_prototypeid");

		result = zbx_db_select("%s", sql);

		while (NULL != (row = zbx_db_fetch(result)))
		{
			ZBX_STR2UINT64(hostid, row[0]);

			for (i = 0; i < host_prototypes->values_num; i++)
			{
				host_prototype = (zbx_host_prototype_t *)host_prototypes->values[i];

				if (host_prototype->hostid == hostid)
				{
					int	k;

					ZBX_STR2UINT64(group_prototypeid, row[1]);
					ZBX_DBROW2UINT64(groupid, row[2]);

					for (k = 0; k < host_prototype->group_prototypes.values_num; k++)
					{
						group_prototype = (zbx_group_prototype_t *)
								host_prototype->group_prototypes.values[k];

						if (0 != group_prototype->group_prototypeid)
							continue;

						if (group_prototype->groupid == groupid &&
								0 == strcmp(group_prototype->name, row[3]))
						{
							zbx_uint64_t	templateid_host;

							ZBX_DBROW2UINT64(templateid_host, row[4]);
							group_prototype->templateid_host = templateid_host;
							group_prototype->group_prototypeid = group_prototypeid;
							break;
						}
					}

					if (k == host_prototype->group_prototypes.values_num)
						zbx_vector_uint64_append(del_group_prototypeids, group_prototypeid);

					break;
				}
			}

			if (i == host_prototypes->values_num)
				THIS_SHOULD_NEVER_HAPPEN;
		}
		zbx_db_free_result(result);
	}

	zbx_vector_uint64_sort(del_group_prototypeids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_vector_uint64_destroy(&hostids);
	zbx_free(sql);
}

/******************************************************************************
 *                                                                            *
 * Purpose: validate hostmacros value changes                                 *
 *                                                                            *
 * Parameters: hostmacros  - [IN/OUT] list of hostmacros                      *
 *             hostmacroid - [IN] hostmacro id                                *
 *             macro       - [IN] hostmacro key                               *
 *             value       - [IN] hostmacro value                             *
 *             description - [IN] hostmacro description                       *
 *             type        - [IN] hostmacro type                              *
 *                                                                            *
 * Return value: SUCCEED - the host macro was found                           *
 *               FAIL    - in the other case                                  *
 ******************************************************************************/
static int	DBhost_prototypes_macro_make(zbx_vector_macros_t *hostmacros, zbx_uint64_t hostmacroid,
		const char *macro, const char *value, const char *description, unsigned char type)
{
	zbx_macros_prototype_t	*hostmacro;
	int			i;

	for (i = 0; i < hostmacros->values_num; i++)
	{
		hostmacro = hostmacros->values[i];

		/* check if host macro has already been added */
		if (0 == hostmacro->hostmacroid && 0 == strcmp(hostmacro->macro, macro))
		{
			hostmacro->hostmacroid = hostmacroid;

			if (0 != strcmp(hostmacro->value, value))
			{
				hostmacro->flags |= ZBX_FLAG_HPMACRO_UPDATE_VALUE;
				hostmacro->value_orig = zbx_strdup(NULL, value);
			}

			if (0 != strcmp(hostmacro->description, description))
			{
				hostmacro->flags |= ZBX_FLAG_HPMACRO_UPDATE_DESCRIPTION;
				hostmacro->description_orig = zbx_strdup(NULL, description);
			}

			if (hostmacro->type != type)
			{
				hostmacro->flags |= ZBX_FLAG_HPMACRO_UPDATE_TYPE;
				hostmacro->type_orig = type;
			}

			return SUCCEED;
		}
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Parameters:                                                                *
 *             host_prototypes    - [IN/OUT] list of host prototypes,         *
 *                                           should be sorted by templateid   *
 *             audit_context_mode - [IN]                                      *
 *             del_macroids       - [OUT] sorted list of host macroids which  *
 *                                        should be deleted                   *
 *                                                                            *
 * Comments: auxiliary function for DBcopy_template_host_prototypes()         *
 *                                                                            *
 ******************************************************************************/
static void	DBhost_prototypes_macros_make(zbx_vector_ptr_t *host_prototypes, int audit_context_mode,
		zbx_vector_uint64_t *del_macroids)
{
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_vector_uint64_t	hostids;
	zbx_uint64_t		hostid, hostmacroid;
	zbx_host_prototype_t	*host_prototype;
	zbx_macros_prototype_t	*hostmacro;
	int			i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&hostids);

	/* select list of macros prototypes which should be linked to host prototypes */

	for (i = 0; i < host_prototypes->values_num; i++)
	{
		host_prototype = (zbx_host_prototype_t *)host_prototypes->values[i];

		zbx_vector_uint64_append(&hostids, host_prototype->templateid);
	}

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select hostid,macro,value,description,type,automatic"
			" from hostmacro"
			" where");
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid", hostids.values, hostids.values_num);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " order by hostid");

	result = zbx_db_select("%s", sql);
	host_prototype = NULL;

	while (NULL != (row = zbx_db_fetch(result)))
	{
		ZBX_STR2UINT64(hostid, row[0]);

		if (NULL == host_prototype || host_prototype->templateid != hostid)
		{
			if (FAIL == (i = zbx_vector_ptr_bsearch(host_prototypes, &hostid,
					ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
			{
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
			}

			host_prototype = (zbx_host_prototype_t *)host_prototypes->values[i];
		}

		hostmacro = (zbx_macros_prototype_t *)zbx_malloc(NULL, sizeof(zbx_macros_prototype_t));
		hostmacro->hostmacroid = 0;
		hostmacro->macro = zbx_strdup(NULL, row[1]);
		hostmacro->value = zbx_strdup(NULL, row[2]);
		hostmacro->description = zbx_strdup(NULL, row[3]);
		ZBX_STR2UCHAR(hostmacro->type, row[4]);
		ZBX_STR2UCHAR(hostmacro->automatic, row[5]);
		hostmacro->flags = ZBX_FLAG_HPMACRO_RESET_FLAG;
		hostmacro->value_orig = NULL;
		hostmacro->description_orig = NULL;
		hostmacro->type_orig = 0;

		zbx_vector_macros_append(&host_prototype->hostmacros, hostmacro);
	}
	zbx_db_free_result(result);

	/* select list of macros prototypes which already linked to host prototypes */

	zbx_vector_uint64_clear(&hostids);

	for (i = 0; i < host_prototypes->values_num; i++)
	{
		host_prototype = (zbx_host_prototype_t *)host_prototypes->values[i];

		if (0 == host_prototype->hostid)
			continue;

		zbx_vector_uint64_append(&hostids, host_prototype->hostid);
	}

	if (0 != hostids.values_num)
	{
		zbx_vector_uint64_sort(&hostids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		sql_offset = 0;
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				"select hostmacroid,hostid,macro,value,description,type from hostmacro where");
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid", hostids.values, hostids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " order by hostid");

		result = zbx_db_select("%s", sql);

		while (NULL != (row = zbx_db_fetch(result)))
		{
			ZBX_STR2UINT64(hostid, row[1]);

			for (i = 0; i < host_prototypes->values_num; i++)
			{
				host_prototype = (zbx_host_prototype_t *)host_prototypes->values[i];

				if (host_prototype->hostid == hostid)
				{
					unsigned char	type;

					ZBX_STR2UINT64(hostmacroid, row[0]);
					ZBX_STR2UCHAR(type, row[5]);

					if (FAIL == DBhost_prototypes_macro_make(&host_prototype->hostmacros,
							hostmacroid, row[2], row[3], row[4], type))
					{
						zbx_vector_uint64_append(del_macroids, hostmacroid);

						zbx_audit_host_prototype_create_entry(audit_context_mode,
								ZBX_AUDIT_ACTION_UPDATE, host_prototype->hostid,
								host_prototype->host);

						zbx_audit_host_prototype_update_json_delete_hostmacro(
								audit_context_mode, host_prototype->hostid,
								hostmacroid);
					}

					break;
				}
			}

			if (i == host_prototypes->values_num)
				THIS_SHOULD_NEVER_HAPPEN;
		}
		zbx_db_free_result(result);
	}

	zbx_vector_uint64_sort(del_macroids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_vector_uint64_destroy(&hostids);
	zbx_free(sql);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Parameters: host_prototypes - [IN/OUT] list of host prototypes             *
 *                                        should be sorted by templateid      *
 *                                                                            *
 * Comments: auxiliary function for DBcopy_template_host_prototypes()         *
 *                                                                            *
 ******************************************************************************/
static void	DBhost_prototypes_tags_make(zbx_vector_ptr_t *host_prototypes)
{
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_vector_uint64_t	hostids;
	zbx_uint64_t		hostid;
	zbx_host_prototype_t	*host_prototype = NULL;
	zbx_db_tag_t		*tag;
	int			i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&hostids);

	/* get template host prototype tags that must be added to host prototypes */

	for (i = 0; i < host_prototypes->values_num; i++)
		zbx_vector_uint64_append(&hostids, ((zbx_host_prototype_t *)host_prototypes->values[i])->templateid);

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select hostid,tag,value"
			" from host_tag"
			" where");
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid", hostids.values, hostids.values_num);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " order by hostid");

	result = zbx_db_select("%s", sql);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		ZBX_STR2UINT64(hostid, row[0]);

		if (NULL == host_prototype || host_prototype->templateid != hostid)
		{
			if (FAIL == (i = zbx_vector_ptr_bsearch(host_prototypes, &hostid,
					ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
			{
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
			}

			host_prototype = (zbx_host_prototype_t *)host_prototypes->values[i];
		}

		tag = zbx_db_tag_create(row[1], row[2]);
		zbx_vector_db_tag_ptr_append(&host_prototype->new_tags, tag);
	}
	zbx_db_free_result(result);

	/* get tags of existing host prototypes */

	zbx_vector_uint64_clear(&hostids);

	for (i = 0; i < host_prototypes->values_num; i++)
	{
		host_prototype = (zbx_host_prototype_t *)host_prototypes->values[i];

		if (0 == host_prototype->hostid)
			continue;

		zbx_vector_uint64_append(&hostids, host_prototype->hostid);
	}

	if (0 != hostids.values_num)
	{
		host_prototype = NULL;

		zbx_vector_uint64_sort(&hostids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		sql_offset = 0;
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				"select hosttagid,hostid,tag,value from host_tag where");
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid", hostids.values, hostids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " order by hostid");
		result = zbx_db_select("%s", sql);

		while (NULL != (row = zbx_db_fetch(result)))
		{
			ZBX_DBROW2UINT64(hostid, row[1]);

			if (NULL == host_prototype || host_prototype->hostid != hostid)
			{
				for (i = 0; i < host_prototypes->values_num; i++)
				{
					host_prototype = (zbx_host_prototype_t *)host_prototypes->values[i];

					if (host_prototype->hostid == hostid)
						break;
				}

				if (NULL == host_prototype || host_prototype->hostid != hostid)
				{
					THIS_SHOULD_NEVER_HAPPEN;
					continue;
				}
			}

			tag = zbx_db_tag_create(row[2], row[3]);
			ZBX_DBROW2UINT64(tag->tagid, row[0]);
			zbx_vector_db_tag_ptr_append(&host_prototype->tags, tag);
		}
		zbx_db_free_result(result);
	}

	for (i = 0; i < host_prototypes->values_num; i++)
	{
		host_prototype = (zbx_host_prototype_t *)host_prototypes->values[i];

		(void)zbx_merge_tags(&host_prototype->tags, &host_prototype->new_tags, NULL, NULL);
	}

	zbx_vector_uint64_destroy(&hostids);
	zbx_free(sql);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static int	host_prototype_interfaces_compare(const zbx_interfaces_prototype_t *ifold,
		const zbx_interfaces_prototype_t *ifnew)
{
	if (ifold->type != ifnew->type)
		return FAIL;

	if (ifold->main != ifnew->main)
		return FAIL;

	if (ifold->useip != ifnew->useip)
		return FAIL;

	if (0 != strcmp(ifold->ip, ifnew->ip))
		return FAIL;

	if (0 != strcmp(ifold->dns, ifnew->dns))
		return FAIL;

	if (0 != strcmp(ifold->port, ifnew->port))
		return FAIL;

	if (INTERFACE_TYPE_SNMP == ifold->type)
	{
		if (ifold->data.snmp->version != ifnew->data.snmp->version)
			return FAIL;

		if (ifold->data.snmp->bulk != ifnew->data.snmp->bulk)
			return FAIL;

		if (0 != strcmp(ifold->data.snmp->community, ifnew->data.snmp->community))
			return FAIL;

		if (0 != strcmp(ifold->data.snmp->securityname, ifnew->data.snmp->securityname))
			return FAIL;

		if (ifold->data.snmp->securitylevel != ifnew->data.snmp->securitylevel)
			return FAIL;

		if (0 != strcmp(ifold->data.snmp->authpassphrase, ifnew->data.snmp->authpassphrase))
			return FAIL;

		if (0 != strcmp(ifold->data.snmp->privpassphrase, ifnew->data.snmp->privpassphrase))
			return FAIL;

		if (ifold->data.snmp->authprotocol != ifnew->data.snmp->authprotocol)
			return FAIL;

		if (ifold->data.snmp->privprotocol != ifnew->data.snmp->privprotocol)
			return FAIL;

		if (0 != strcmp(ifold->data.snmp->contextname, ifnew->data.snmp->contextname))
			return FAIL;
	}

	return SUCCEED;
}

static void	host_prototype_interfaces_make(zbx_uint64_t hostid, zbx_vector_ptr_t *host_prototypes,
		zbx_vector_interfaces_t *old_interfaces, zbx_vector_uint64_t *del_interfaceids)
{
	int			i, j;
	zbx_host_prototype_t	*host_prototype;

	for (i = 0; i < host_prototypes->values_num; i++)
	{
		host_prototype = (zbx_host_prototype_t *)host_prototypes->values[i];

		if (host_prototype->hostid == hostid)
			break;
	}

	if (i == host_prototypes->values_num)
	{
		THIS_SHOULD_NEVER_HAPPEN;
		goto out;
	}

	/* find matching interfaces */

	for (i = 0; i < host_prototype->interfaces.values_num; i++)
	{
		zbx_interfaces_prototype_t	*interface = (zbx_interfaces_prototype_t *)
				host_prototype->interfaces.values[i];

		if (0 != interface->interfaceid)
			continue;

		for (j = 0; j < old_interfaces->values_num; j++)
		{
			if (SUCCEED == host_prototype_interfaces_compare(old_interfaces->values[j], interface))
			{
				interface->interfaceid = old_interfaces->values[j]->interfaceid;
				DBhost_interface_free(old_interfaces->values[j]);
				zbx_vector_interfaces_remove_noorder(old_interfaces, j);
				break;
			}
		}
	}

	/* remove deleted/modified interfaces */
	for (i = 0; i < old_interfaces->values_num; i++)
		zbx_vector_uint64_append(del_interfaceids, old_interfaces->values[i]->interfaceid);
out:
	zbx_vector_interfaces_clear_ext(old_interfaces, DBhost_interface_free);
}

/******************************************************************************
 *                                                                            *
 * Purpose: prepares interfaces to be added, updated or removed from DB       *
 *                                                                            *
 * Parameters: host_prototypes  - [IN/OUT] list of host prototypes,           *
 *                                         should be sorted by templateid     *
 *             del_interfaceids - [OUT] sorted list of host interface         *
 *                                      ids which should be deleted           *
 *                                                                            *
 * Comments: auxiliary function for DBcopy_template_host_prototypes()         *
 *                                                                            *
 ******************************************************************************/
static void	DBhost_prototypes_interfaces_make(zbx_vector_ptr_t *host_prototypes,
		zbx_vector_uint64_t *del_interfaceids)
{
	zbx_db_result_t			result;
	zbx_db_row_t			row;
	char				*sql = NULL;
	size_t				sql_alloc = 0, sql_offset = 0;
	zbx_vector_uint64_t		hostids;
	zbx_uint64_t			hostid;
	zbx_host_prototype_t		*host_prototype;
	zbx_interfaces_prototype_t	*interface;
	int				i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&hostids);

	/* select list of interfaces which should be linked to host prototypes */

	for (i = 0; i < host_prototypes->values_num; i++)
	{
		host_prototype = (zbx_host_prototype_t *)host_prototypes->values[i];

		zbx_vector_uint64_append(&hostids, host_prototype->templateid);
	}

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select hi.hostid,hi.main,hi.type,hi.useip,hi.ip,hi.dns,hi.port,s.version,s.bulk,s.community,"
				"s.securityname,s.securitylevel,s.authpassphrase,s.privpassphrase,s.authprotocol,"
				"s.privprotocol,s.contextname"
			" from interface hi"
				" left join interface_snmp s"
					" on hi.interfaceid=s.interfaceid"
			" where");
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "hi.hostid", hostids.values, hostids.values_num);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " order by hi.hostid");

	result = zbx_db_select("%s", sql);
	host_prototype = NULL;

	while (NULL != (row = zbx_db_fetch(result)))
	{
		ZBX_STR2UINT64(hostid, row[0]);

		if (NULL == host_prototype || host_prototype->templateid != hostid)
		{
			if (FAIL == (i = zbx_vector_ptr_bsearch(host_prototypes, &hostid,
					ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
			{
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
			}

			host_prototype = (zbx_host_prototype_t *)host_prototypes->values[i];
		}

		interface = (zbx_interfaces_prototype_t *)zbx_malloc(NULL, sizeof(zbx_interfaces_prototype_t));
		interface->interfaceid = 0;
		ZBX_STR2UCHAR(interface->main, row[1]);
		ZBX_STR2UCHAR(interface->type, row[2]);
		ZBX_STR2UCHAR(interface->useip, row[3]);
		interface->ip = zbx_strdup(NULL, row[4]);
		interface->dns = zbx_strdup(NULL, row[5]);
		interface->port = zbx_strdup(NULL, row[6]);

		if (INTERFACE_TYPE_SNMP == interface->type)
		{
			zbx_interface_prototype_snmp_t	*snmp;

			snmp = (zbx_interface_prototype_snmp_t *)zbx_malloc(NULL,
					sizeof(zbx_interface_prototype_snmp_t));
			ZBX_STR2UCHAR(snmp->version, row[7]);
			ZBX_STR2UCHAR(snmp->bulk, row[8]);
			snmp->community = zbx_strdup(NULL, row[9]);
			snmp->securityname = zbx_strdup(NULL, row[10]);
			ZBX_STR2UCHAR(snmp->securitylevel, row[11]);
			snmp->authpassphrase = zbx_strdup(NULL, row[12]);
			snmp->privpassphrase = zbx_strdup(NULL, row[13]);
			ZBX_STR2UCHAR(snmp->authprotocol, row[14]);
			ZBX_STR2UCHAR(snmp->privprotocol, row[15]);
			snmp->contextname = zbx_strdup(NULL, row[16]);
			interface->data.snmp = snmp;
		}
		else
			interface->data.snmp = NULL;

		zbx_vector_interfaces_append(&host_prototype->interfaces, interface);
	}
	zbx_db_free_result(result);

	/* select list of interfaces which are already linked to host prototypes */

	zbx_vector_uint64_clear(&hostids);

	for (i = 0; i < host_prototypes->values_num; i++)
	{
		host_prototype = (zbx_host_prototype_t *)host_prototypes->values[i];

		/* host prototype is not saved yet */
		if (0 == host_prototype->hostid)
			continue;

		zbx_vector_uint64_append(&hostids, host_prototype->hostid);
	}

	if (0 != hostids.values_num)
	{
		zbx_vector_interfaces_t	old_interfaces;
		zbx_uint64_t		last_hostid = 0;

		zbx_vector_interfaces_create(&old_interfaces);

		zbx_vector_uint64_sort(&hostids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		sql_offset = 0;
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				"select hi.interfaceid,hi.hostid,hi.main,hi.type,hi.useip,hi.ip,hi.dns,hi.port,"
					"s.version,s.bulk,s.community,s.securityname,s.securitylevel,s.authpassphrase,"
					"s.privpassphrase,s.authprotocol,s.privprotocol,s.contextname"
				" from interface hi"
					" left join interface_snmp s"
						" on hi.interfaceid=s.interfaceid"
				" where");
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "hi.hostid", hostids.values,
				hostids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " order by hi.hostid");

		result = zbx_db_select("%s", sql);

		while (NULL != (row = zbx_db_fetch(result)))
		{
			ZBX_STR2UINT64(hostid, row[1]);

			if (0 != last_hostid && hostid != last_hostid)
			{
				host_prototype_interfaces_make(last_hostid, host_prototypes, &old_interfaces,
						del_interfaceids);
			}

			last_hostid = hostid;

			interface = (zbx_interfaces_prototype_t *)zbx_malloc(NULL, sizeof(zbx_interfaces_prototype_t));
			ZBX_STR2UINT64(interface->interfaceid, row[0]);
			ZBX_STR2UCHAR(interface->main, row[2]);
			ZBX_STR2UCHAR(interface->type, row[3]);
			ZBX_STR2UCHAR(interface->useip, row[4]);
			interface->ip = zbx_strdup(NULL, row[5]);
			interface->dns = zbx_strdup(NULL, row[6]);
			interface->port = zbx_strdup(NULL, row[7]);

			if (INTERFACE_TYPE_SNMP == interface->type)
			{
				zbx_interface_prototype_snmp_t	*snmp;

				snmp = (zbx_interface_prototype_snmp_t *)zbx_malloc(NULL,
						sizeof(zbx_interface_prototype_snmp_t));
				ZBX_STR2UCHAR(snmp->version, row[8]);
				ZBX_STR2UCHAR(snmp->bulk, row[9]);
				snmp->community = zbx_strdup(NULL, row[10]);
				snmp->securityname = zbx_strdup(NULL, row[11]);
				ZBX_STR2UCHAR(snmp->securitylevel, row[12]);
				snmp->authpassphrase = zbx_strdup(NULL, row[13]);
				snmp->privpassphrase = zbx_strdup(NULL, row[14]);
				ZBX_STR2UCHAR(snmp->authprotocol, row[15]);
				ZBX_STR2UCHAR(snmp->privprotocol, row[16]);
				snmp->contextname = zbx_strdup(NULL, row[17]);
				interface->data.snmp = snmp;
			}
			else
				interface->data.snmp = NULL;

			zbx_vector_interfaces_append(&old_interfaces, interface);
		}
		zbx_db_free_result(result);

		if (0 != old_interfaces.values_num)
			host_prototype_interfaces_make(last_hostid, host_prototypes, &old_interfaces, del_interfaceids);

		zbx_vector_interfaces_destroy(&old_interfaces);
	}

	zbx_vector_uint64_sort(del_interfaceids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_vector_uint64_destroy(&hostids);
	zbx_free(sql);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: auxiliary function for DBcopy_template_host_prototypes()          *
 *                                                                            *
 * Parameters: host_prototypes      - [IN]                                    *
 *             del_hosttemplateids  - [IN] host template ids for delete       *
 *             del_hostmacroids     - [IN] host macro ids for delete          *
 *             del_interfaceids     - [IN] interface ids for delete           *
 *             audit_context_mode   - [IN]                                    *
 *             db_insert_htemplates - [IN/OUT] templates insert structure     *
 *                                                                            *
 ******************************************************************************/
static void	DBhost_prototypes_save(const zbx_vector_ptr_t *host_prototypes,
		const zbx_vector_uint64_t *del_hosttemplateids, const zbx_vector_uint64_t *del_hostmacroids,
		const zbx_vector_uint64_t *del_interfaceids, int audit_context_mode,
		zbx_db_insert_t *db_insert_htemplates)
{
	char				*sql1 = NULL, *sql2 = NULL, *name_esc, *value_esc;
	size_t				sql1_alloc = ZBX_KIBIBYTE, sql1_offset = 0,
					sql2_alloc = ZBX_KIBIBYTE, sql2_offset = 0;
	const zbx_group_prototype_t	*group_prototype;
	const zbx_macros_prototype_t	*hostmacro;
	zbx_interfaces_prototype_t	*interface;
	zbx_uint64_t			hostid = 0, hosttemplateid = 0, group_prototypeid = 0, new_hostmacroid = 0,
					hosttagid = 0, interfaceid = 0;
	int				i, j, new_hosts = 0, new_hosts_templates = 0, new_group_prototypes = 0,
					upd_group_prototypes = 0, new_hostmacros = 0, upd_hostmacros = 0,
					new_tags = 0, new_interfaces = 0, new_snmp = 0,
					new_inventory_modes = 0, upd_inventory_modes = 0, res = SUCCEED;
	zbx_db_insert_t			db_insert, db_insert_hdiscovery, db_insert_gproto,
					db_insert_hmacro, db_insert_tag, db_insert_iface, db_insert_snmp,
					db_insert_inventory_mode;
	zbx_vector_db_tag_ptr_t		upd_tags;
	zbx_vector_uint64_t		del_inventory_modes_hostids, del_tagids;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_db_tag_ptr_create(&upd_tags);
	zbx_vector_uint64_create(&del_inventory_modes_hostids);
	zbx_vector_uint64_create(&del_tagids);

	for (i = 0; i < host_prototypes->values_num; i++)
	{
		const zbx_host_prototype_t	*host_prototype;

		host_prototype = (zbx_host_prototype_t *)host_prototypes->values[i];

		if (0 == host_prototype->hostid)
		{
			new_hosts++;

			if (HOST_INVENTORY_DISABLED != host_prototype->inventory_mode)
				new_inventory_modes++;
		}
		else
		{
			zbx_audit_host_prototype_create_entry(audit_context_mode, ZBX_AUDIT_ACTION_UPDATE,
					host_prototype->hostid, host_prototype->host);

			if (0 != (host_prototype->flags & ZBX_FLAG_HPLINK_UPDATE_INVENTORY_MODE))
			{
				if (HOST_INVENTORY_DISABLED == host_prototype->inventory_mode)
					zbx_vector_uint64_append(&del_inventory_modes_hostids, host_prototype->hostid);
				else if (HOST_INVENTORY_DISABLED == host_prototype->inventory_mode_orig)
					new_inventory_modes++;
				else
					upd_inventory_modes++;
			}
		}

		new_hosts_templates += host_prototype->lnk_templateids.values_num;

		for (j = 0; j < host_prototype->group_prototypes.values_num; j++)
		{
			group_prototype = (zbx_group_prototype_t *)host_prototype->group_prototypes.values[j];

			if (0 == group_prototype->group_prototypeid)
				new_group_prototypes++;
			else
				upd_group_prototypes++;
		}

		for (j = 0; j < host_prototype->hostmacros.values_num; j++)
		{
			hostmacro = host_prototype->hostmacros.values[j];

			if (0 == hostmacro->hostmacroid)
				new_hostmacros++;
			else if (0 != (hostmacro->flags & ZBX_FLAG_HPMACRO_UPDATE))
				upd_hostmacros++;
		}

		for (j = 0; j < host_prototype->tags.values_num; j++)
		{
			zbx_db_tag_t	*tag = host_prototype->tags.values[j];

			if (0 == tag->tagid)
			{
				new_tags++;
			}
			else if (0 != (tag->flags & ZBX_FLAG_DB_TAG_UPDATE))
			{
				zbx_vector_db_tag_ptr_append(&upd_tags, tag);

				zbx_audit_host_prototype_update_json_update_tag_create_entry(audit_context_mode,
						host_prototype->hostid, tag->tagid);

				if (0 != (tag->flags & ZBX_FLAG_DB_TAG_UPDATE_TAG))
				{
					zbx_audit_host_prototype_update_json_update_tag_tag(audit_context_mode,
							host_prototype->hostid, tag->tagid, tag->tag_orig, tag->tag);
				}

				if (0 != (tag->flags & ZBX_FLAG_DB_TAG_UPDATE_VALUE))
				{
					zbx_audit_host_prototype_update_json_update_tag_value(audit_context_mode,
							host_prototype->hostid, tag->tagid, tag->value_orig,
							tag->value);
				}
			}
			else if (ZBX_FLAG_DB_TAG_REMOVE == tag->flags)
			{
				zbx_audit_host_prototype_create_entry(audit_context_mode, ZBX_AUDIT_ACTION_UPDATE,
						host_prototype->hostid, host_prototype->host);
				zbx_audit_host_prototype_update_json_delete_tag(audit_context_mode,
						host_prototype->hostid, tag->tagid);
				zbx_vector_uint64_append(&del_tagids, tag->tagid);
			}
		}

		for (j = 0; j < host_prototype->interfaces.values_num; j++)
		{
			interface = host_prototype->interfaces.values[j];

			if (0 == interface->interfaceid)
			{
				new_interfaces++;

				if (INTERFACE_TYPE_SNMP == interface->type)
						new_snmp++;
			}
		}

		for (j = 0; j < del_interfaceids->values_num; j++)
		{
			zbx_audit_host_prototype_create_entry(audit_context_mode, ZBX_AUDIT_ACTION_UPDATE,
					host_prototype->hostid, host_prototype->host);
			zbx_audit_host_prototype_update_json_delete_interface(audit_context_mode,
					host_prototype->hostid, del_interfaceids->values[j]);
		}
	}

	if (0 != new_hosts)
	{
		hostid = zbx_db_get_maxid_num("hosts", new_hosts);

		zbx_db_insert_prepare(&db_insert, "hosts", "hostid", "host", "name", "status", "flags", "templateid",
				"discover", "custom_interfaces", (char *)NULL);

		zbx_db_insert_prepare(&db_insert_hdiscovery, "host_discovery", "hostid", "parent_itemid", (char *)NULL);
	}

	if (new_hosts != host_prototypes->values_num || 0 != upd_group_prototypes || 0 != upd_hostmacros ||
			0 != upd_tags.values_num)
	{
		sql1 = (char *)zbx_malloc(sql1, sql1_alloc);
	}

	if (0 != new_hosts_templates)
		hosttemplateid = zbx_db_get_maxid_num("hosts_templates", new_hosts_templates);

	if (0 != del_hosttemplateids->values_num || 0 != del_hostmacroids->values_num || 0 != del_tagids.values_num ||
			0 != del_interfaceids->values_num || 0 != del_inventory_modes_hostids.values_num)
	{
		sql2 = (char *)zbx_malloc(sql2, sql2_alloc);
	}

	if (0 != del_hosttemplateids->values_num)
	{
		zbx_strcpy_alloc(&sql2, &sql2_alloc, &sql2_offset, "delete from hosts_templates where");
		zbx_db_add_condition_alloc(&sql2, &sql2_alloc, &sql2_offset, "hosttemplateid",
				del_hosttemplateids->values, del_hosttemplateids->values_num);
		zbx_strcpy_alloc(&sql2, &sql2_alloc, &sql2_offset, ";\n");
	}

	if (0 != del_hostmacroids->values_num)
	{
		zbx_strcpy_alloc(&sql2, &sql2_alloc, &sql2_offset, "delete from hostmacro where");
		zbx_db_add_condition_alloc(&sql2, &sql2_alloc, &sql2_offset, "hostmacroid",
				del_hostmacroids->values, del_hostmacroids->values_num);
		zbx_strcpy_alloc(&sql2, &sql2_alloc, &sql2_offset, ";\n");
	}

	if (0 != del_tagids.values_num)
	{
		zbx_strcpy_alloc(&sql2, &sql2_alloc, &sql2_offset, "delete from host_tag where");
		zbx_db_add_condition_alloc(&sql2, &sql2_alloc, &sql2_offset, "hosttagid", del_tagids.values,
				del_tagids.values_num);
		zbx_strcpy_alloc(&sql2, &sql2_alloc, &sql2_offset, ";\n");
	}

	if (0 != del_interfaceids->values_num)
	{
		zbx_strcpy_alloc(&sql2, &sql2_alloc, &sql2_offset, "delete from interface where");
		zbx_db_add_condition_alloc(&sql2, &sql2_alloc, &sql2_offset, "interfaceid",
				del_interfaceids->values, del_interfaceids->values_num);
		zbx_strcpy_alloc(&sql2, &sql2_alloc, &sql2_offset, ";\n");
	}

	if (0 != del_inventory_modes_hostids.values_num)
	{
		zbx_strcpy_alloc(&sql2, &sql2_alloc, &sql2_offset, "delete from host_inventory where");
		zbx_db_add_condition_alloc(&sql2, &sql2_alloc, &sql2_offset, "hostid",
				del_inventory_modes_hostids.values, del_inventory_modes_hostids.values_num);
		zbx_strcpy_alloc(&sql2, &sql2_alloc, &sql2_offset, ";\n");
	}

	if (0 != new_group_prototypes)
	{
		group_prototypeid = zbx_db_get_maxid_num("group_prototype", new_group_prototypes);

		zbx_db_insert_prepare(&db_insert_gproto, "group_prototype", "group_prototypeid", "hostid", "name",
				"groupid", "templateid", (char *)NULL);
	}

	if (0 != new_hostmacros)
	{
		new_hostmacroid = zbx_db_get_maxid_num("hostmacro", new_hostmacros);

		zbx_db_insert_prepare(&db_insert_hmacro, "hostmacro", "hostmacroid", "hostid", "macro", "value",
				"description", "type", "automatic", (char *)NULL);
	}

	if (0 != new_tags)
	{
		hosttagid = zbx_db_get_maxid_num("host_tag", new_tags);

		zbx_db_insert_prepare(&db_insert_tag, "host_tag", "hosttagid", "hostid", "tag", "value", "automatic",
				(char *)NULL);
	}

	if (0 != new_interfaces)
	{
		interfaceid = zbx_db_get_maxid_num("interface", new_interfaces);

		zbx_db_insert_prepare(&db_insert_iface, "interface", "interfaceid", "hostid", "main", "type",
				"useip", "ip", "dns", "port", (char *)NULL);
	}

	if (0 != new_snmp)
	{
		zbx_db_insert_prepare(&db_insert_snmp, "interface_snmp", "interfaceid", "version", "bulk", "community",
				"securityname", "securitylevel", "authpassphrase", "privpassphrase", "authprotocol",
				"privprotocol", "contextname", (char *)NULL);
	}

	if (0 != new_inventory_modes)
		zbx_db_insert_prepare(&db_insert_inventory_mode, "host_inventory", "hostid", "inventory_mode",
				(char *)NULL);

	for (i = 0; i < host_prototypes->values_num; i++)
	{
		zbx_host_prototype_t	*host_prototype;

		host_prototype = (zbx_host_prototype_t *)host_prototypes->values[i];

		if (0 == host_prototype->hostid)
		{
			host_prototype->hostid = hostid++;

			zbx_db_insert_add_values(&db_insert, host_prototype->hostid, host_prototype->host,
					host_prototype->name, (int)host_prototype->status,
					(int)ZBX_FLAG_DISCOVERY_PROTOTYPE, host_prototype->templateid,
					(int)host_prototype->discover, (int)host_prototype->custom_interfaces);

			zbx_audit_host_prototype_create_entry(audit_context_mode, ZBX_AUDIT_ACTION_ADD,
					host_prototype->hostid, host_prototype->host);

			zbx_db_insert_add_values(&db_insert_hdiscovery, host_prototype->hostid, host_prototype->itemid);

			zbx_audit_host_prototype_update_json_add_lldruleid(audit_context_mode,
					host_prototype->hostid, host_prototype->itemid);

			if (HOST_INVENTORY_DISABLED != host_prototype->inventory_mode)
			{
				zbx_db_insert_add_values(&db_insert_inventory_mode, host_prototype->hostid,
						host_prototype->inventory_mode);
			}

			zbx_audit_host_prototype_update_json_add_details(audit_context_mode, host_prototype->hostid,
					host_prototype->templateid, host_prototype->name, (int)host_prototype->status,
					(int)host_prototype->discover, (int)host_prototype->custom_interfaces,
					host_prototype->inventory_mode, host_prototype->host);
		}
		else
		{
			zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset, "update hosts set templateid=" ZBX_FS_UI64,
					host_prototype->templateid);

			zbx_audit_host_prototype_update_json_update_templateid(audit_context_mode,
					host_prototype->hostid, host_prototype->templateid_host,
					host_prototype->templateid);

			if (0 != (host_prototype->flags & ZBX_FLAG_HPLINK_UPDATE_NAME))
			{
				name_esc = zbx_db_dyn_escape_string(host_prototype->name);
				zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset, ",name='%s'", name_esc);
				zbx_audit_host_prototype_update_json_update_name(audit_context_mode,
						host_prototype->hostid, host_prototype->name_orig, name_esc);
				zbx_free(name_esc);
			}
			if (0 != (host_prototype->flags & ZBX_FLAG_HPLINK_UPDATE_STATUS))
			{
				zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset, ",status=%d",
						host_prototype->status);
				zbx_audit_host_prototype_update_json_update_status(audit_context_mode,
						host_prototype->hostid, host_prototype->status_orig,
						host_prototype->status);
			}
			if (0 != (host_prototype->flags & ZBX_FLAG_HPLINK_UPDATE_DISCOVER))
			{
				zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset, ",discover=%d",
						host_prototype->discover);
				zbx_audit_host_prototype_update_json_update_discover(audit_context_mode,
						host_prototype->hostid, host_prototype->discover_orig,
						host_prototype->discover);
			}
			if (0 != (host_prototype->flags & ZBX_FLAG_HPLINK_UPDATE_CUSTOM_INTERFACES))
			{
				zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset, ",custom_interfaces=%d",
						host_prototype->custom_interfaces);
				zbx_audit_host_prototype_update_json_update_custom_interfaces(audit_context_mode,
						host_prototype->hostid, host_prototype->custom_interfaces_orig,
						host_prototype->custom_interfaces);
			}

			zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset, " where hostid=" ZBX_FS_UI64 ";\n",
					host_prototype->hostid);

			if (FAIL == (res = zbx_db_execute_overflowed_sql(&sql1, &sql1_alloc, &sql1_offset)))
				break;

			if (0 != (host_prototype->flags & ZBX_FLAG_HPLINK_UPDATE_INVENTORY_MODE))
			{
				/* new host inventory value which is HOST_INVENTORY_DISABLED is handled later */
				if (HOST_INVENTORY_DISABLED != host_prototype->inventory_mode)
				{
					if (HOST_INVENTORY_DISABLED == host_prototype->inventory_mode_orig)
					{
						zbx_db_insert_add_values(&db_insert_inventory_mode,
								host_prototype->hostid, host_prototype->inventory_mode);
					}
					else
					{
						zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset,
								"update host_inventory set inventory_mode=%d"
								" where hostid=" ZBX_FS_UI64 ";\n",
								host_prototype->inventory_mode, host_prototype->hostid);

						if (FAIL == (res = zbx_db_execute_overflowed_sql(&sql1, &sql1_alloc,
								&sql1_offset)))
						{
							break;
						}
					}
				}

				zbx_audit_host_prototype_update_json_update_inventory_mode(audit_context_mode,
						host_prototype->hostid, (int)host_prototype->inventory_mode_orig,
						(int)host_prototype->inventory_mode);
			}
		}

		for (j = 0; j < host_prototype->lnk_templateids.values_num; j++)
		{
			zbx_db_insert_add_values(db_insert_htemplates, hosttemplateid, host_prototype->hostid,
					host_prototype->lnk_templateids.values[j]);

			zbx_audit_host_prototype_update_json_add_parent_template(audit_context_mode,
					host_prototype->hostid, hosttemplateid,
					host_prototype->lnk_templateids.values[j], ZBX_TEMPLATE_LINK_MANUAL);

			hosttemplateid++;
		}

		for (j = 0; j < host_prototype->group_prototypes.values_num; j++)
		{
			group_prototype = (zbx_group_prototype_t *)host_prototype->group_prototypes.values[j];

			if (0 == group_prototype->group_prototypeid)
			{
				zbx_db_insert_add_values(&db_insert_gproto, group_prototypeid, host_prototype->hostid,
						group_prototype->name, group_prototype->groupid,
						group_prototype->templateid);

				zbx_audit_host_prototype_update_json_add_group_details(audit_context_mode,
						host_prototype->hostid, group_prototypeid, group_prototype->name,
						group_prototype->groupid, group_prototype->templateid);

				group_prototypeid++;
			}
			else
			{
				zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset,
						"update group_prototype"
						" set templateid=" ZBX_FS_UI64
						" where group_prototypeid=" ZBX_FS_UI64 ";\n",
						group_prototype->templateid, group_prototype->group_prototypeid);

				if (FAIL == (res = zbx_db_execute_overflowed_sql(&sql1, &sql1_alloc, &sql1_offset)))
					break;

				zbx_audit_host_prototype_update_json_update_group_details(audit_context_mode,
						host_prototype->hostid, group_prototype->group_prototypeid,
						group_prototype->name, group_prototype->groupid,
						group_prototype->templateid_host, group_prototype->templateid);
			}
		}

		for (j = 0; j < host_prototype->hostmacros.values_num; j++)
		{
			hostmacro = host_prototype->hostmacros.values[j];

			if (0 == hostmacro->hostmacroid)
			{
				zbx_db_insert_add_values(&db_insert_hmacro, new_hostmacroid, host_prototype->hostid,
						hostmacro->macro, hostmacro->value, hostmacro->description,
						(int)hostmacro->type, (int)hostmacro->automatic);

				zbx_audit_host_update_json_add_hostmacro(audit_context_mode,
						host_prototype->hostid, ZBX_AUDIT_RESOURCE_HOST_PROTOTYPE,
						new_hostmacroid, hostmacro->macro, (ZBX_MACRO_VALUE_SECRET ==
						(int)hostmacro->type) ? ZBX_MACRO_SECRET_MASK : hostmacro->value,
						hostmacro->description, (int)hostmacro->type,
						(int)hostmacro->automatic);
				new_hostmacroid++;
			}
			else if (0 != (hostmacro->flags & ZBX_FLAG_HPMACRO_UPDATE))
			{
				const char	*d = "";

				zbx_strcpy_alloc(&sql1, &sql1_alloc, &sql1_offset, "update hostmacro set ");

				zbx_audit_host_prototype_update_json_update_hostmacro_create_entry(
						audit_context_mode, host_prototype->hostid, hostmacro->hostmacroid);

				if (0 != (hostmacro->flags & ZBX_FLAG_HPMACRO_UPDATE_VALUE))
				{
					value_esc = zbx_db_dyn_escape_string(hostmacro->value);
					zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset, "value='%s'", value_esc);
					zbx_free(value_esc);
					d = ",";

					zbx_audit_host_prototype_update_json_update_hostmacro_value(
							audit_context_mode, host_prototype->hostid,
							hostmacro->hostmacroid,
							((0 != (hostmacro->flags & ZBX_FLAG_HPMACRO_UPDATE_TYPE) &&
							ZBX_MACRO_VALUE_SECRET == (int)hostmacro->type_orig) ||
							(0 == (hostmacro->flags & ZBX_FLAG_HPMACRO_UPDATE_TYPE) &&
							ZBX_MACRO_VALUE_SECRET == (int)hostmacro->type)) ?
							ZBX_MACRO_SECRET_MASK : hostmacro->value_orig,
							(ZBX_MACRO_VALUE_SECRET == (int)hostmacro->type) ?
							ZBX_MACRO_SECRET_MASK : hostmacro->value);
				}

				if (0 != (hostmacro->flags & ZBX_FLAG_HPMACRO_UPDATE_DESCRIPTION))
				{
					value_esc = zbx_db_dyn_escape_string(hostmacro->description);
					zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset, "%sdescription='%s'",
							d, value_esc);
					zbx_free(value_esc);
					d = ",";

					zbx_audit_host_prototype_update_json_update_hostmacro_description(
							audit_context_mode, host_prototype->hostid,
							hostmacro->hostmacroid, hostmacro->description_orig,
							hostmacro->description);
				}

				if (0 != (hostmacro->flags & ZBX_FLAG_HPMACRO_UPDATE_TYPE))
				{
					zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset, "%stype=%d",
							d, hostmacro->type);

					zbx_audit_host_prototype_update_json_update_hostmacro_type(
							audit_context_mode, host_prototype->hostid,
							hostmacro->hostmacroid, hostmacro->type_orig, hostmacro->type);
				}

				zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset,
						" where hostmacroid=" ZBX_FS_UI64 ";\n", hostmacro->hostmacroid);
				if (FAIL == (res = zbx_db_execute_overflowed_sql(&sql1, &sql1_alloc, &sql1_offset)))
					break;
			}
		}

		for (j = 0; j < host_prototype->tags.values_num; j++)
		{
			zbx_db_tag_t	*tag = host_prototype->tags.values[j];

			if (0 == tag->tagid)
			{
				zbx_db_insert_add_values(&db_insert_tag, hosttagid, host_prototype->hostid,
						tag->tag, tag->value, ZBX_DB_TAG_NORMAL);

				zbx_audit_host_prototype_update_json_add_tag(audit_context_mode, host_prototype->hostid,
						hosttagid, tag->tag, tag->value, ZBX_DB_TAG_NORMAL);

				hosttagid++;
			}
		}

		for (j = 0; j < host_prototype->interfaces.values_num; j++)
		{
			interface = host_prototype->interfaces.values[j];

			if (0 == interface->interfaceid)
			{
				interface->interfaceid = interfaceid++;
				zbx_db_insert_add_values(&db_insert_iface, interface->interfaceid,
						host_prototype->hostid, (int)interface->main, (int)interface->type,
						(int)interface->useip, interface->ip, interface->dns, interface->port);

				zbx_audit_host_prototype_update_json_add_interfaces(audit_context_mode,
						host_prototype->hostid, interface->interfaceid, interface->main,
						interface->type, interface->useip, interface->ip, interface->dns,
						atoi(interface->port));

				if (INTERFACE_TYPE_SNMP == interface->type)
				{
					zbx_db_insert_add_values(&db_insert_snmp, interface->interfaceid,
							(int)interface->data.snmp->version,
							(int)interface->data.snmp->bulk,
							interface->data.snmp->community,
							interface->data.snmp->securityname,
							(int)interface->data.snmp->securitylevel,
							interface->data.snmp->authpassphrase,
							interface->data.snmp->privpassphrase,
							(int)interface->data.snmp->authprotocol,
							(int)interface->data.snmp->privprotocol,
							interface->data.snmp->contextname);

					zbx_audit_host_prototype_update_json_add_snmp_interface(audit_context_mode,
							host_prototype->hostid, interface->data.snmp->version,
							interface->data.snmp->bulk, interface->data.snmp->community,
							interface->data.snmp->securityname,
							interface->data.snmp->securitylevel,
							interface->data.snmp->authpassphrase,
							interface->data.snmp->privpassphrase,
							interface->data.snmp->authprotocol,
							interface->data.snmp->privprotocol,
							interface->data.snmp->contextname,
							interface->interfaceid);
				}
			}
		}
	}

	if (0 != upd_tags.values_num)
	{
		zbx_vector_db_tag_ptr_sort(&upd_tags, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

		for (i = 0; i < upd_tags.values_num; i++)
		{
			char		delim = ' ';
			zbx_db_tag_t	*tag = upd_tags.values[i];

			zbx_strcpy_alloc(&sql1, &sql1_alloc, &sql1_offset, "update host_tag set");

			if (0 != (tag->flags & ZBX_FLAG_DB_TAG_UPDATE_TAG))
			{
				value_esc = zbx_db_dyn_escape_string(tag->tag);
				zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset, "%ctag='%s'", delim,
						value_esc);
				zbx_free(value_esc);
				delim = ',';
			}

			if (0 != (tag->flags & ZBX_FLAG_DB_TAG_UPDATE_VALUE))
			{
				value_esc = zbx_db_dyn_escape_string(tag->value);
				zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset, "%cvalue='%s'", delim,
						value_esc);
				zbx_free(value_esc);
			}

			zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset,
					" where hosttagid=" ZBX_FS_UI64 ";\n", tag->tagid);

			if (FAIL == (res = zbx_db_execute_overflowed_sql(&sql1, &sql1_alloc, &sql1_offset)))
				break;
		}
	}

	if (0 != new_hosts)
	{
		zbx_db_insert_execute(&db_insert);
		zbx_db_insert_clean(&db_insert);

		zbx_db_insert_execute(&db_insert_hdiscovery);
		zbx_db_insert_clean(&db_insert_hdiscovery);
	}

	if (0 != new_group_prototypes)
	{
		zbx_db_insert_execute(&db_insert_gproto);
		zbx_db_insert_clean(&db_insert_gproto);
	}

	if (0 != new_hostmacros)
	{
		zbx_db_insert_execute(&db_insert_hmacro);
		zbx_db_insert_clean(&db_insert_hmacro);
	}

	if (0 != new_tags)
	{
		zbx_db_insert_execute(&db_insert_tag);
		zbx_db_insert_clean(&db_insert_tag);
	}

	if (0 != new_interfaces)
	{
		zbx_db_insert_execute(&db_insert_iface);
		zbx_db_insert_clean(&db_insert_iface);
	}

	if (0 != new_snmp)
	{
		zbx_db_insert_execute(&db_insert_snmp);
		zbx_db_insert_clean(&db_insert_snmp);
	}

	if (0 != new_inventory_modes)
	{
		zbx_db_insert_execute(&db_insert_inventory_mode);
		zbx_db_insert_clean(&db_insert_inventory_mode);
	}

	if (SUCCEED == res && (NULL != sql1 || new_hosts != host_prototypes->values_num || 0 != upd_group_prototypes ||
			0 != upd_hostmacros || 0 != upd_inventory_modes))
	{
		(void)zbx_db_flush_overflowed_sql(sql1, sql1_offset);
	}

	if (SUCCEED == res && (NULL != sql2 || 0 != del_hosttemplateids->values_num ||
			0 != del_hostmacroids->values_num || 0 != del_tagids.values_num ||
			0 != del_interfaceids->values_num || 0 != del_inventory_modes_hostids.values_num))
	{
		(void)zbx_db_flush_overflowed_sql(sql2, sql2_offset);
	}

	zbx_free(sql1);
	zbx_free(sql2);

	zbx_vector_db_tag_ptr_destroy(&upd_tags);
	zbx_vector_uint64_destroy(&del_inventory_modes_hostids);
	zbx_vector_uint64_destroy(&del_tagids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: copies host prototypes from templates and creates links between   *
 *          them and discovery rules                                          *
 *                                                                            *
 * Parameters: hostid               - [IN]                                    *
 *             templateids          - [IN] host template ids                  *
 *             audit_context_mode   - [IN]                                    *
 *             db_insert_htemplates - [IN/OUT] templates insert structure     *
 *                                                                            *
 * Comments: auxiliary function for zbx_db_copy_template_elements()           *
 *                                                                            *
 ******************************************************************************/
static void	DBcopy_template_host_prototypes(zbx_uint64_t hostid, zbx_vector_uint64_t *templateids,
		int audit_context_mode, zbx_db_insert_t *db_insert_htemplates)
{
	zbx_vector_ptr_t	host_prototypes;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	/* only regular hosts can have host prototypes */
	if (SUCCEED != DBis_regular_host(hostid))
		return;

	zbx_vector_ptr_create(&host_prototypes);

	DBhost_prototypes_make(hostid, templateids, &host_prototypes);

	if (0 != host_prototypes.values_num)
	{
		zbx_vector_uint64_t	del_hosttemplateids, del_group_prototypeids, del_macroids, del_interfaceids;

		zbx_vector_uint64_create(&del_hosttemplateids);
		zbx_vector_uint64_create(&del_group_prototypeids);
		zbx_vector_uint64_create(&del_macroids);
		zbx_vector_uint64_create(&del_interfaceids);

		DBhost_prototypes_templates_make(&host_prototypes, audit_context_mode, &del_hosttemplateids);
		DBhost_prototypes_groups_make(&host_prototypes, &del_group_prototypeids);
		DBhost_prototypes_macros_make(&host_prototypes, audit_context_mode, &del_macroids);
		DBhost_prototypes_tags_make(&host_prototypes);
		DBhost_prototypes_interfaces_make(&host_prototypes, &del_interfaceids);
		DBhost_prototypes_save(&host_prototypes, &del_hosttemplateids, &del_macroids,
				&del_interfaceids, audit_context_mode, db_insert_htemplates);
		DBgroup_prototypes_delete(&del_group_prototypeids);

		zbx_vector_uint64_destroy(&del_macroids);
		zbx_vector_uint64_destroy(&del_group_prototypeids);
		zbx_vector_uint64_destroy(&del_interfaceids);
		zbx_vector_uint64_destroy(&del_hosttemplateids);
	}

	DBhost_prototypes_clean(&host_prototypes);
	zbx_vector_ptr_destroy(&host_prototypes);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

typedef struct
{
	zbx_uint64_t		t_itemid;
	zbx_uint64_t		h_itemid;
	unsigned char		type;
}
httpstepitem_t;

typedef struct
{
	zbx_uint64_t		httpstepid;
	zbx_uint64_t		hoststepid;
	char			*name;
	char			*url_orig;
	char			*url;
	char			*posts_orig;
	char			*posts;
	char			*required_orig;
	char			*required;
	char			*status_codes_orig;
	char			*status_codes;
	zbx_vector_ptr_t	httpstepitems;
	zbx_vector_ptr_t	fields;
	char			*timeout_orig;
	char			*timeout;
	int			no;
	int			follow_redirects_orig;
	int			follow_redirects;
	int			retrieve_mode_orig;
	int			retrieve_mode;
	int			post_type_orig;
	int			post_type;
#define ZBX_FLAG_HTTPSTEP_RESET_FLAG			__UINT64_C(0x000000000000)
#define ZBX_FLAG_HTTPSTEP_UPDATE_URL			__UINT64_C(0x000000000001)
#define ZBX_FLAG_HTTPSTEP_UPDATE_POSTS			__UINT64_C(0x000000000002)
#define ZBX_FLAG_HTTPSTEP_UPDATE_REQUIRED		__UINT64_C(0x000000000004)
#define ZBX_FLAG_HTTPSTEP_UPDATE_STATUS_CODES		__UINT64_C(0x000000000008)
#define ZBX_FLAG_HTTPSTEP_UPDATE_TIMEOUT		__UINT64_C(0x000000000010)
#define ZBX_FLAG_HTTPSTEP_UPDATE_FOLLOW_REDIRECTS	__UINT64_C(0x000000000020)
#define ZBX_FLAG_HTTPSTEP_UPDATE_RETRIEVE_MODE		__UINT64_C(0x000000000040)
#define ZBX_FLAG_HTTPSTEP_UPDATE_POST_TYPE		__UINT64_C(0x000000000080)
#define ZBX_FLAG_HTTPSTEP_UPDATE									\
		(ZBX_FLAG_HTTPSTEP_UPDATE_URL | ZBX_FLAG_HTTPSTEP_UPDATE_POSTS |			\
		ZBX_FLAG_HTTPSTEP_UPDATE_REQUIRED | ZBX_FLAG_HTTPSTEP_UPDATE_STATUS_CODES |		\
		ZBX_FLAG_HTTPSTEP_UPDATE_TIMEOUT | ZBX_FLAG_HTTPSTEP_UPDATE_FOLLOW_REDIRECTS |		\
		ZBX_FLAG_HTTPSTEP_UPDATE_RETRIEVE_MODE | ZBX_FLAG_HTTPSTEP_UPDATE_POST_TYPE		\
		)
	zbx_uint64_t		upd_flags;
}
httpstep_t;

typedef struct
{
	zbx_uint64_t		httptesttagid;
	char			*tag;
	char			*value;
}
httptesttag_t;

typedef struct
{
	zbx_uint64_t		t_itemid;
	zbx_uint64_t		h_itemid;
	unsigned char		type;
}
httptestitem_t;

typedef struct
{
	zbx_uint64_t		templateid;
	zbx_uint64_t		templateid_host;
	zbx_uint64_t		httptestid;
	char			*name;
	char			*delay_orig;
	char			*delay;
	zbx_vector_ptr_t	fields;
	char			*agent_orig;
	char			*agent;
	char			*http_user_orig;
	char			*http_user;
	char			*http_password_orig;
	char			*http_password;
	char			*http_proxy_orig;
	char			*http_proxy;
	zbx_vector_ptr_t	httpsteps;
	zbx_vector_ptr_t	httptestitems;
	zbx_vector_ptr_t	httptesttags;
	int			retries_orig;
	int			retries;
	unsigned char		status_orig;
	unsigned char		status;
	unsigned char		authentication_orig;
	unsigned char		authentication;
	char			*ssl_cert_file_orig;
	char			*ssl_cert_file;
	char			*ssl_key_file_orig;
	char			*ssl_key_file;
	char			*ssl_key_password_orig;
	char			*ssl_key_password;
	int			verify_peer_orig;
	int			verify_peer;
	int			verify_host_orig;
	int			verify_host;
#define ZBX_FLAG_HTTPTEST_RESET_FLAG			__UINT64_C(0x000000000000)
#define ZBX_FLAG_HTTPTEST_UPDATE_DELAY			__UINT64_C(0x000000000001)
#define ZBX_FLAG_HTTPTEST_UPDATE_AGENT			__UINT64_C(0x000000000002)
#define ZBX_FLAG_HTTPTEST_UPDATE_HTTP_USER		__UINT64_C(0x000000000004)
#define ZBX_FLAG_HTTPTEST_UPDATE_HTTP_PASSWORD		__UINT64_C(0x000000000008)
#define ZBX_FLAG_HTTPTEST_UPDATE_HTTP_PROXY		__UINT64_C(0x000000000010)
#define ZBX_FLAG_HTTPTEST_UPDATE_RETRIES		__UINT64_C(0x000000000020)
#define ZBX_FLAG_HTTPTEST_UPDATE_STATUS			__UINT64_C(0x000000000040)
#define ZBX_FLAG_HTTPTEST_UPDATE_AUTHENTICATION		__UINT64_C(0x000000000080)
#define ZBX_FLAG_HTTPTEST_UPDATE_SSL_CERT_FILE		__UINT64_C(0x000000000100)
#define ZBX_FLAG_HTTPTEST_UPDATE_SSL_KEY_FILE		__UINT64_C(0x000000000200)
#define ZBX_FLAG_HTTPTEST_UPDATE_SSL_KEY_PASSWORD	__UINT64_C(0x000000000400)
#define ZBX_FLAG_HTTPTEST_UPDATE_VERIFY_PEER		__UINT64_C(0x000000000800)
#define ZBX_FLAG_HTTPTEST_UPDATE_VERIFY_HOST		__UINT64_C(0x000000001000)
#define ZBX_FLAG_HTTPTEST_UPDATE									\
		(ZBX_FLAG_HTTPTEST_UPDATE_DELAY | ZBX_FLAG_HTTPTEST_UPDATE_AGENT |			\
		ZBX_FLAG_HTTPTEST_UPDATE_HTTP_USER | ZBX_FLAG_HTTPTEST_UPDATE_HTTP_PASSWORD |		\
		ZBX_FLAG_HTTPTEST_UPDATE_HTTP_PROXY | ZBX_FLAG_HTTPTEST_UPDATE_RETRIES |		\
		ZBX_FLAG_HTTPTEST_UPDATE_STATUS | ZBX_FLAG_HTTPTEST_UPDATE_AUTHENTICATION |		\
		ZBX_FLAG_HTTPTEST_UPDATE_SSL_CERT_FILE | ZBX_FLAG_HTTPTEST_UPDATE_SSL_KEY_FILE |	\
		ZBX_FLAG_HTTPTEST_UPDATE_SSL_KEY_PASSWORD | ZBX_FLAG_HTTPTEST_UPDATE_VERIFY_PEER |	\
		ZBX_FLAG_HTTPTEST_UPDATE_VERIFY_HOST							\
		)
	zbx_uint64_t		upd_flags;
}
httptest_t;

typedef struct
{
	int			type;
	char			*name;
	char			*value;
}
httpfield_t;

static void	DBget_httptests(const zbx_uint64_t hostid, const zbx_vector_uint64_t *templateids,
		zbx_vector_ptr_t *httptests)
{
	int			i, j, k, int_orig;
	char			*sql = NULL;
	size_t			sql_alloc = 512, sql_offset = 0;
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	httptest_t		*httptest;
	httpstep_t		*httpstep;
	httpfield_t		*httpfield;
	httptestitem_t		*httptestitem;
	httpstepitem_t		*httpstepitem;
	zbx_vector_uint64_t	httptestids;	/* the list of web scenarios which should be added to a host */
	zbx_vector_uint64_t	items;
	zbx_uint64_t		httptestid, httpstepid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&httptestids);
	zbx_vector_uint64_create(&items);

	sql = (char *)zbx_malloc(sql, sql_alloc);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select t.httptestid,t.name,t.delay,t.status,t.agent,t.authentication,"
				"t.http_user,t.http_password,t.http_proxy,t.retries,h.httptestid,h.templateid,h.delay,"
				"h.status,h.agent,h.authentication,h.http_user,h.http_password,h.http_proxy,h.retries,"
				"t.ssl_cert_file,t.ssl_key_file,t.ssl_key_password,t.verify_peer,t.verify_host,"
				"h.ssl_cert_file,h.ssl_key_file,h.ssl_key_password,h.verify_peer,h.verify_host"
			" from httptest t"
				" left join httptest h"
					" on h.hostid=" ZBX_FS_UI64
						" and h.name=t.name"
			" where", hostid);
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "t.hostid", templateids->values,
			templateids->values_num);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " order by t.httptestid");

	result = zbx_db_select("%s", sql);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		httptest = (httptest_t *)zbx_calloc(NULL, 1, sizeof(httptest_t));

		httptest->upd_flags = ZBX_FLAG_HTTPTEST_RESET_FLAG;

		ZBX_STR2UINT64(httptest->templateid, row[0]);
		ZBX_DBROW2UINT64(httptest->templateid_host, row[11]);
		ZBX_DBROW2UINT64(httptest->httptestid, row[10]);
		zbx_vector_ptr_create(&httptest->httpsteps);
		zbx_vector_ptr_create(&httptest->httptestitems);
		zbx_vector_ptr_create(&httptest->fields);
		zbx_vector_ptr_create(&httptest->httptesttags);
		httptest->name = zbx_strdup(NULL, row[1]);
		httptest->delay = zbx_strdup(NULL, row[2]);
		httptest->delay_orig = NULL;
		httptest->status = (unsigned char)atoi(row[3]);
		httptest->status_orig = httptest->status;
		httptest->agent = zbx_strdup(NULL, row[4]);
		httptest->agent_orig = NULL;
		httptest->authentication = (unsigned char)atoi(row[5]);
		httptest->authentication_orig = httptest->authentication;
		httptest->http_user = zbx_strdup(NULL, row[6]);
		httptest->http_user_orig = NULL;
		httptest->http_password = zbx_strdup(NULL, row[7]);
		httptest->http_password_orig = NULL;
		httptest->http_proxy = zbx_strdup(NULL, row[8]);
		httptest->http_proxy_orig = NULL;
		httptest->retries = atoi(row[9]);
		httptest->retries_orig = httptest->retries;

		httptest->ssl_cert_file = zbx_strdup(NULL, row[20]);
		httptest->ssl_cert_file_orig = NULL;
		httptest->ssl_key_file = zbx_strdup(NULL, row[21]);
		httptest->ssl_key_file_orig = NULL;
		httptest->ssl_key_password = zbx_strdup(NULL, row[22]);
		httptest->ssl_key_password_orig = NULL;
		httptest->verify_peer = atoi(row[23]);
		httptest->verify_peer_orig = httptest->verify_peer;
		httptest->verify_host = atoi(row[24]);
		httptest->verify_host_orig = httptest->verify_host;

		zbx_vector_ptr_append(httptests, httptest);

		if (0 != httptest->httptestid)
		{
			unsigned char		uchar_orig;

#define SET_FLAG_STR(r, i, f, s)			\
	do						\
	{						\
		if (0 != strcmp(r, (i)))		\
		{					\
			s->upd_flags |= f;		\
			i##_orig = zbx_strdup(NULL, r);	\
		}					\
	}						\
	while(0)

#define SET_FLAG_UCHAR(r, i, f, s)			\
	do 						\
	{						\
		ZBX_STR2UCHAR(uchar_orig, (r));		\
		if (uchar_orig != (i))			\
		{					\
			s->upd_flags |= f;		\
			i##_orig = uchar_orig;		\
		}					\
	}						\
	while(0)

#define SET_FLAG_INT(r, i, f, s)			\
	do						\
	{						\
		int_orig = atoi(r);			\
		if (int_orig != (i))			\
		{					\
			s->upd_flags |= f;		\
			i##_orig = int_orig;		\
		}					\
	}						\
	while(0)

			SET_FLAG_STR(row[12], httptest->delay, ZBX_FLAG_HTTPTEST_UPDATE_DELAY, httptest);
			SET_FLAG_UCHAR(row[13], httptest->status, ZBX_FLAG_HTTPTEST_UPDATE_STATUS, httptest);
			SET_FLAG_STR(row[14], httptest->agent, ZBX_FLAG_HTTPTEST_UPDATE_AGENT, httptest);
			SET_FLAG_UCHAR(row[15], httptest->authentication, ZBX_FLAG_HTTPTEST_UPDATE_AUTHENTICATION,
					httptest);
			SET_FLAG_STR(row[16], httptest->http_user, ZBX_FLAG_HTTPTEST_UPDATE_HTTP_USER, httptest);
			SET_FLAG_STR(row[17], httptest->http_password, ZBX_FLAG_HTTPTEST_UPDATE_HTTP_PASSWORD,
					httptest);
			SET_FLAG_STR(row[18], httptest->http_proxy, ZBX_FLAG_HTTPTEST_UPDATE_HTTP_PROXY, httptest);
			SET_FLAG_INT(row[19], httptest->retries, ZBX_FLAG_HTTPTEST_UPDATE_RETRIES, httptest);
			SET_FLAG_STR(row[25], httptest->ssl_cert_file, ZBX_FLAG_HTTPTEST_UPDATE_SSL_CERT_FILE,
					httptest);
			SET_FLAG_STR(row[26], httptest->ssl_key_file, ZBX_FLAG_HTTPTEST_UPDATE_SSL_KEY_FILE, httptest);
			SET_FLAG_STR(row[27], httptest->ssl_key_password, ZBX_FLAG_HTTPTEST_UPDATE_SSL_KEY_PASSWORD,
					httptest);
			SET_FLAG_INT(row[28], httptest->verify_peer, ZBX_FLAG_HTTPTEST_UPDATE_VERIFY_PEER, httptest);
			SET_FLAG_INT(row[29], httptest->verify_host, ZBX_FLAG_HTTPTEST_UPDATE_VERIFY_HOST, httptest);
		}

		zbx_vector_uint64_append(&httptestids, httptest->templateid);
	}

	zbx_db_free_result(result);

	if (0 != httptestids.values_num)
	{
		httptest = NULL;

		/* web scenario fields */
		sql_offset = 0;
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				"select httptestid,type,name,value"
				" from httptest_field"
				" where");
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "httptestid",
				httptestids.values, httptestids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " order by httptestid,httptest_fieldid");

		result = zbx_db_select("%s", sql);

		while (NULL != (row = zbx_db_fetch(result)))
		{
			ZBX_STR2UINT64(httptestid, row[0]);

			if (NULL == httptest || httptest->templateid != httptestid)
			{
				if (FAIL == (i = zbx_vector_ptr_bsearch(httptests, &httptestid,
						ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
				{
					THIS_SHOULD_NEVER_HAPPEN;
					continue;
				}

				httptest = (httptest_t *)httptests->values[i];
			}

			httpfield = (httpfield_t *)zbx_malloc(NULL, sizeof(httpfield_t));

			httpfield->type = atoi(row[1]);
			httpfield->name = zbx_strdup(NULL, row[2]);
			httpfield->value = zbx_strdup(NULL, row[3]);

			zbx_vector_ptr_append(&httptest->fields, httpfield);
		}
		zbx_db_free_result(result);

		/* web scenario steps */
		httptest = NULL;

		sql_offset = 0;
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"select ts.httpstepid,ts.httptestid,ts.name,ts.no,ts.url,ts.timeout,ts.posts,"
				"ts.required,ts.status_codes,ts.follow_redirects,ts.retrieve_mode,ts.post_type,"
				"hs.httpstepid,hs.url,hs.timeout,hs.posts,hs.required,hs.status_codes,"
				"hs.follow_redirects,hs.retrieve_mode,hs.post_type"
				" from httpstep ts"
				" left join httptest tt on tt.httptestid=ts.httptestid"
				" left join httptest ht on ht.hostid=" ZBX_FS_UI64 " and ht.name=tt.name"
				" left join httpstep hs on hs.httptestid=ht.httptestid and hs.no=ts.no"
				" where", hostid);
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "ts.httptestid",
				httptestids.values, httptestids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " order by ts.httptestid");

		result = zbx_db_select("%s", sql);

		while (NULL != (row = zbx_db_fetch(result)))
		{
			ZBX_STR2UINT64(httptestid, row[1]);

			if (NULL == httptest || httptest->templateid != httptestid)
			{
				if (FAIL == (i = zbx_vector_ptr_bsearch(httptests, &httptestid,
						ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
				{
					THIS_SHOULD_NEVER_HAPPEN;
					continue;
				}

				httptest = (httptest_t *)httptests->values[i];
			}

			httpstep = (httpstep_t *)zbx_malloc(NULL, sizeof(httpstep_t));

			ZBX_STR2UINT64(httpstep->httpstepid, row[0]);
			httpstep->name = zbx_strdup(NULL, row[2]);
			httpstep->no = atoi(row[3]);
			httpstep->url = zbx_strdup(NULL, row[4]);
			httpstep->url_orig = NULL;
			httpstep->timeout = zbx_strdup(NULL, row[5]);
			httpstep->timeout_orig = NULL;
			httpstep->posts = zbx_strdup(NULL, row[6]);
			httpstep->posts_orig = NULL;
			httpstep->required = zbx_strdup(NULL, row[7]);
			httpstep->required_orig = NULL;
			httpstep->status_codes = zbx_strdup(NULL, row[8]);
			httpstep->status_codes_orig = NULL;
			httpstep->follow_redirects = atoi(row[9]);
			httpstep->follow_redirects_orig = httpstep->follow_redirects;
			httpstep->retrieve_mode = atoi(row[10]);
			httpstep->retrieve_mode_orig = httpstep->retrieve_mode;
			httpstep->post_type = atoi(row[11]);
			httpstep->post_type_orig = httpstep->post_type;
			httpstep->upd_flags = ZBX_FLAG_HTTPSTEP_RESET_FLAG;
			zbx_vector_ptr_create(&httpstep->httpstepitems);
			zbx_vector_ptr_create(&httpstep->fields);

			ZBX_DBROW2UINT64(httpstep->hoststepid, row[12]);

			if (0 != httpstep->hoststepid)
			{
				SET_FLAG_STR(row[13], httpstep->url, ZBX_FLAG_HTTPSTEP_UPDATE_URL, httpstep);
				SET_FLAG_STR(row[14], httpstep->timeout, ZBX_FLAG_HTTPSTEP_UPDATE_TIMEOUT, httpstep);
				SET_FLAG_STR(row[15], httpstep->posts, ZBX_FLAG_HTTPSTEP_UPDATE_POSTS, httpstep);
				SET_FLAG_STR(row[16], httpstep->required, ZBX_FLAG_HTTPSTEP_UPDATE_REQUIRED, httpstep);
				SET_FLAG_STR(row[17], httpstep->status_codes, ZBX_FLAG_HTTPSTEP_UPDATE_STATUS_CODES,
						httpstep);
				SET_FLAG_INT(row[18], httpstep->follow_redirects,
						ZBX_FLAG_HTTPSTEP_UPDATE_FOLLOW_REDIRECTS, httpstep);
				SET_FLAG_INT(row[19], httpstep->retrieve_mode, ZBX_FLAG_HTTPSTEP_UPDATE_RETRIEVE_MODE,
						httpstep);
				SET_FLAG_INT(row[20], httpstep->post_type, ZBX_FLAG_HTTPSTEP_UPDATE_POST_TYPE,
						httpstep);
			}

			zbx_vector_ptr_append(&httptest->httpsteps, httpstep);
		}

		zbx_db_free_result(result);

		for (i = 0; i < httptests->values_num; i++)
		{
			httptest = (httptest_t *)httptests->values[i];
			zbx_vector_ptr_sort(&httptest->httpsteps, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);
		}

		/* web scenario step fields */
		httptest = NULL;

		sql_offset = 0;
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				"select s.httptestid,f.httpstepid,f.type,f.name,f.value"
				" from httpstep_field f"
					" join httpstep s"
						" on f.httpstepid=s.httpstepid"
							" and");
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "s.httptestid",
				httptestids.values, httptestids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				" order by s.httptestid,f.httpstepid,f.httpstep_fieldid");

		result = zbx_db_select("%s", sql);

		while (NULL != (row = zbx_db_fetch(result)))
		{
			ZBX_STR2UINT64(httptestid, row[0]);
			ZBX_STR2UINT64(httpstepid, row[1]);

			if (NULL == httptest || httptest->templateid != httptestid)
			{
				if (FAIL == (i = zbx_vector_ptr_bsearch(httptests, &httptestid,
						ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
				{
					THIS_SHOULD_NEVER_HAPPEN;
					continue;
				}

				httptest = (httptest_t *)httptests->values[i];
				httpstep = NULL;
			}

			if (NULL == httpstep || httpstep->httpstepid != httpstepid)
			{
				if (FAIL == (i = zbx_vector_ptr_bsearch(&httptest->httpsteps, &httpstepid,
						ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
				{
					THIS_SHOULD_NEVER_HAPPEN;
					continue;
				}

				httpstep = (httpstep_t *)httptest->httpsteps.values[i];
			}

			httpfield = (httpfield_t *)zbx_malloc(NULL, sizeof(httpfield_t));

			httpfield->type = atoi(row[2]);
			httpfield->name = zbx_strdup(NULL, row[3]);
			httpfield->value = zbx_strdup(NULL, row[4]);

			zbx_vector_ptr_append(&httpstep->fields, httpfield);
		}
		zbx_db_free_result(result);

		/* web scenario tags */
		httptest = NULL;

		sql_offset = 0;
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				"select httptesttagid,httptestid,tag,value"
				" from httptest_tag"
				" where");
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "httptestid",
				httptestids.values, httptestids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " order by httptestid");

		result = zbx_db_select("%s", sql);

		while (NULL != (row = zbx_db_fetch(result)))
		{
			httptesttag_t	*httptesttag;

			ZBX_STR2UINT64(httptestid, row[1]);

			if (NULL == httptest || httptest->templateid != httptestid)
			{
				if (FAIL == (i = zbx_vector_ptr_bsearch(httptests, &httptestid,
						ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
				{
					THIS_SHOULD_NEVER_HAPPEN;
					continue;
				}

				httptest = (httptest_t *)httptests->values[i];
			}

			httptesttag = (httptesttag_t *)zbx_malloc(NULL, sizeof(httptesttag_t));

			ZBX_STR2UINT64(httptesttag->httptesttagid, row[0]);
			httptesttag->tag = zbx_strdup(NULL, row[2]);
			httptesttag->value = zbx_strdup(NULL, row[3]);

			zbx_vector_ptr_append(&httptest->httptesttags, httptesttag);
		}
		zbx_db_free_result(result);

		for (i = 0; i < httptests->values_num; i++)
		{
			httptest = (httptest_t *)httptests->values[i];
			zbx_vector_ptr_sort(&httptest->httptesttags, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);
		}
	}

	/* web scenario items */
	if (0 != httptestids.values_num)
	{
		httptest = NULL;

		sql_offset = 0;
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				"select httptestid,itemid,type"
				" from httptestitem"
				" where");
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "httptestid",
				httptestids.values, httptestids.values_num);

		result = zbx_db_select("%s", sql);

		while (NULL != (row = zbx_db_fetch(result)))
		{
			ZBX_STR2UINT64(httptestid, row[0]);

			if (NULL == httptest || httptest->templateid != httptestid)
			{
				if (FAIL == (i = zbx_vector_ptr_bsearch(httptests, &httptestid,
						ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
				{
					THIS_SHOULD_NEVER_HAPPEN;
					continue;
				}

				httptest = (httptest_t *)httptests->values[i];
			}

			httptestitem = (httptestitem_t *)zbx_calloc(NULL, 1, sizeof(httptestitem_t));

			ZBX_STR2UINT64(httptestitem->t_itemid, row[1]);
			httptestitem->type = (unsigned char)atoi(row[2]);

			zbx_vector_ptr_append(&httptest->httptestitems, httptestitem);

			zbx_vector_uint64_append(&items, httptestitem->t_itemid);
		}
		zbx_db_free_result(result);
	}

	/* web scenario step items */
	if (0 != httptestids.values_num)
	{
		httptest = NULL;
		httpstep = NULL;

		sql_offset = 0;
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				"select hs.httptestid,hsi.httpstepid,hsi.itemid,hsi.type"
				" from httpstepitem hsi"
					" join httpstep hs"
						" on");
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "hs.httptestid",
				httptestids.values, httptestids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
							" and hs.httpstepid=hsi.httpstepid"
				" order by hs.httptestid,hsi.httpstepid");

		result = zbx_db_select("%s", sql);

		while (NULL != (row = zbx_db_fetch(result)))
		{
			ZBX_STR2UINT64(httptestid, row[0]);
			ZBX_STR2UINT64(httpstepid, row[1]);

			if (NULL == httptest || httptest->templateid != httptestid)
			{
				if (FAIL == (i = zbx_vector_ptr_bsearch(httptests, &httptestid,
						ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
				{
					THIS_SHOULD_NEVER_HAPPEN;
					continue;
				}

				httptest = (httptest_t *)httptests->values[i];
				httpstep = NULL;
			}

			if (NULL == httpstep || httpstep->httpstepid != httpstepid)
			{
				if (FAIL == (i = zbx_vector_ptr_bsearch(&httptest->httpsteps, &httpstepid,
						ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
				{
					THIS_SHOULD_NEVER_HAPPEN;
					continue;
				}

				httpstep = (httpstep_t *)httptest->httpsteps.values[i];
			}

			httpstepitem = (httpstepitem_t *)zbx_calloc(NULL, 1, sizeof(httpstepitem_t));

			ZBX_STR2UINT64(httpstepitem->t_itemid, row[2]);
			httpstepitem->type = (unsigned char)atoi(row[3]);

			zbx_vector_ptr_append(&httpstep->httpstepitems, httpstepitem);

			zbx_vector_uint64_append(&items, httpstepitem->t_itemid);
		}
		zbx_db_free_result(result);
	}

	/* items */
	if (0 != items.values_num)
	{
		zbx_vector_uint64_sort(&items, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		sql_offset = 0;
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"select t.itemid,h.itemid"
				" from items t"
					" join items h"
						" on h.hostid=" ZBX_FS_UI64
							" and h.key_=t.key_"
				" where", hostid);
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "t.itemid",
				items.values, items.values_num);

		result = zbx_db_select("%s", sql);

		while (NULL != (row = zbx_db_fetch(result)))
		{
			zbx_uint64_t	itemid;

			ZBX_STR2UINT64(itemid, row[0]);

			for (i = 0; i < httptests->values_num; i++)
			{
				httptest = (httptest_t *)httptests->values[i];

				for (j = 0; j < httptest->httptestitems.values_num; j++)
				{
					httptestitem = (httptestitem_t *)httptest->httptestitems.values[j];

					if (httptestitem->t_itemid == itemid)
						ZBX_STR2UINT64(httptestitem->h_itemid, row[1]);
				}

				for (j = 0; j < httptest->httpsteps.values_num; j++)
				{
					httpstep = (httpstep_t *)httptest->httpsteps.values[j];

					for (k = 0; k < httpstep->httpstepitems.values_num; k++)
					{
						httpstepitem = (httpstepitem_t *)httpstep->httpstepitems.values[k];

						if (httpstepitem->t_itemid == itemid)
							ZBX_STR2UINT64(httpstepitem->h_itemid, row[1]);
					}
				}
			}
		}
		zbx_db_free_result(result);
	}

	zbx_free(sql);

	zbx_vector_uint64_destroy(&items);
	zbx_vector_uint64_destroy(&httptestids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	DBsave_httptests(zbx_uint64_t hostid, const zbx_vector_ptr_t *httptests, int audit_context_mode)
{
	char			*sql;
	size_t			sql_alloc = 512, sql_offset = 0;
	httptest_t		*httptest;
	httpfield_t		*httpfield;
	httpstep_t		*httpstep;
	zbx_uint64_t		httptestid = 0, httpstepid = 0, httptestitemid = 0, httpstepitemid = 0,
				httptestfieldid = 0, httpstepfieldid = 0, httptesttagid = 0;
	int			i, j, k, num_httpsteps = 0, num_httptestitems = 0, num_httpstepitems = 0,
				num_httptestfields = 0, num_httpstepfields = 0, num_httptesttags = 0, num_httptests = 0;
	zbx_db_insert_t		db_insert_htest, db_insert_hstep, db_insert_htitem, db_insert_hsitem, db_insert_tfield,
				db_insert_sfield, db_insert_httag;
	zbx_vector_uint64_t	httpupdtestids, httpupdstepids, deletefieldsids, deletestepfieldsids, deletetagids;
	zbx_db_result_t		result;
	zbx_db_row_t		row;

	if (0 == httptests->values_num)
		return;

	sql = (char *)zbx_malloc(NULL, sql_alloc);

	zbx_vector_uint64_create(&httpupdtestids);
	zbx_vector_uint64_create(&httpupdstepids);
	zbx_vector_uint64_create(&deletefieldsids);
	zbx_vector_uint64_create(&deletestepfieldsids);
	zbx_vector_uint64_create(&deletetagids);

	for (i = 0; i < httptests->values_num; i++)
	{
		httptest = (httptest_t *)httptests->values[i];

		num_httptestfields += httptest->fields.values_num;
		num_httptesttags += httptest->httptesttags.values_num;

		if (0 == httptest->httptestid)
		{
			num_httptests++;
			num_httpsteps += httptest->httpsteps.values_num;
			num_httptestitems += httptest->httptestitems.values_num;

			for (j = 0; j < httptest->httpsteps.values_num; j++)
			{
				httpstep = (httpstep_t *)httptest->httpsteps.values[j];

				num_httpstepfields += httpstep->fields.values_num;
				num_httpstepitems += httpstep->httpstepitems.values_num;
			}
		}
		else
		{
			zbx_vector_uint64_append(&httpupdtestids, httptest->httptestid);

			for (j = 0; j < httptest->httpsteps.values_num; j++)
			{
				httpstep = (httpstep_t *)httptest->httpsteps.values[j];

				num_httpstepfields += httpstep->fields.values_num;
				zbx_vector_uint64_append(&httpupdstepids, httpstep->hoststepid);
			}

			zbx_audit_httptest_create_entry(audit_context_mode, ZBX_AUDIT_ACTION_UPDATE,
					httptest->httptestid, httptest->name);
		}
	}

	if (0 != httpupdtestids.values_num)
	{
		sql_offset = 0;
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				"select httptest_fieldid,httptestid,type"
				" from httptest_field where");
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "httptestid", httpupdtestids.values,
				httpupdtestids.values_num);

		result = zbx_db_select("%s", sql);

		while (NULL != (row = zbx_db_fetch(result)))
		{
			zbx_uint64_t	httpfieldid, testid;
			int		type;

			ZBX_STR2UINT64(httpfieldid, row[0]);
			ZBX_STR2UINT64(testid, row[1]);
			type = atoi(row[2]);
			zbx_vector_uint64_append(&deletefieldsids, httpfieldid);
			zbx_audit_httptest_update_json_delete_httptest_field(audit_context_mode, testid, httpfieldid,
					type);
		}

		zbx_db_free_result(result);

		sql_offset = 0;
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				"select httptesttagid,httptestid"
				" from httptest_tag where"
				);
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "httptestid", httpupdtestids.values,
				httpupdtestids.values_num);

		result = zbx_db_select("%s", sql);

		while (NULL != (row = zbx_db_fetch(result)))
		{
			zbx_uint64_t	httptagid, testid;

			ZBX_STR2UINT64(httptagid, row[0]);
			ZBX_STR2UINT64(testid, row[1]);
			zbx_vector_uint64_append(&deletetagids, httptagid);
			zbx_audit_httptest_update_json_delete_tags(audit_context_mode, testid, httptagid);
		}

		zbx_db_free_result(result);
	}

	if (0 != httpupdstepids.values_num)
	{
		sql_offset = 0;
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				"select sf.httpstep_fieldid,sf.httpstepid,s.httptestid,sf.type"
				" from httpstep_field sf"
				" join httpstep s on s.httpstepid=sf.httpstepid"
				" where");
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "sf.httpstepid", httpupdstepids.values,
				httpupdstepids.values_num);

		result = zbx_db_select("%s", sql);

		while (NULL != (row = zbx_db_fetch(result)))
		{
			zbx_uint64_t	stepfieldid, stepid, testid;
			int		type;

			ZBX_STR2UINT64(stepfieldid, row[0]);
			ZBX_STR2UINT64(stepid, row[1]);
			ZBX_STR2UINT64(testid, row[2]);
			type = atoi(row[3]);
			zbx_vector_uint64_append(&deletestepfieldsids, stepfieldid);
			zbx_audit_httptest_update_json_delete_httpstep_field(audit_context_mode, testid, stepid,
					stepfieldid, type);
		}

		zbx_db_free_result(result);
	}

	if (0 != deletefieldsids.values_num)
	{
		sql_offset = 0;
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "delete from httptest_field where");
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "httptest_fieldid", deletefieldsids.values,
				deletefieldsids.values_num);
		zbx_db_execute("%s", sql);
	}

	if (0 != deletestepfieldsids.values_num)
	{
		sql_offset = 0;
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "delete from httpstep_field where");
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "httpstep_fieldid",
				deletestepfieldsids.values, deletestepfieldsids.values_num);
		zbx_db_execute("%s", sql);
	}

	if (0 != deletetagids.values_num)
	{
		sql_offset = 0;
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "delete from httptest_tag where");
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "httptesttagid", deletetagids.values,
				deletetagids.values_num);
		zbx_db_execute("%s", sql);
	}

	sql_offset = 0;

	if (0 != num_httptests)
	{
		httptestid = zbx_db_get_maxid_num("httptest", num_httptests);

		zbx_db_insert_prepare(&db_insert_htest, "httptest", "httptestid", "name", "delay", "status", "agent",
				"authentication", "http_user", "http_password", "http_proxy", "retries", "hostid",
				"templateid", "ssl_cert_file", "ssl_key_file", "ssl_key_password", "verify_peer",
				"verify_host", (char *)NULL);
	}

	if (0 != num_httptestfields)
	{
		httptestfieldid = zbx_db_get_maxid_num("httptest_field", num_httptestfields);

		zbx_db_insert_prepare(&db_insert_tfield, "httptest_field", "httptest_fieldid", "httptestid", "type",
				"name", "value", (char *)NULL);
	}

	if (0 != num_httpsteps)
	{
		httpstepid = zbx_db_get_maxid_num("httpstep", num_httpsteps);

		zbx_db_insert_prepare(&db_insert_hstep, "httpstep", "httpstepid", "httptestid", "name", "no", "url",
				"timeout", "posts", "required", "status_codes", "follow_redirects", "retrieve_mode",
				"post_type", (char *)NULL);
	}

	if (0 != num_httptestitems)
	{
		httptestitemid = zbx_db_get_maxid_num("httptestitem", num_httptestitems);

		zbx_db_insert_prepare(&db_insert_htitem, "httptestitem", "httptestitemid", "httptestid", "itemid",
				"type", (char *)NULL);
	}

	if (0 != num_httpstepitems)
	{
		httpstepitemid = zbx_db_get_maxid_num("httpstepitem", num_httpstepitems);

		zbx_db_insert_prepare(&db_insert_hsitem, "httpstepitem", "httpstepitemid", "httpstepid", "itemid",
				"type", (char *)NULL);
	}

	if (0 != num_httpstepfields)
	{
		httpstepfieldid = zbx_db_get_maxid_num("httpstep_field", num_httpstepfields);

		zbx_db_insert_prepare(&db_insert_sfield, "httpstep_field", "httpstep_fieldid", "httpstepid", "type",
				"name", "value", (char *)NULL);
	}

	if (0 != num_httptesttags)
	{
		httptesttagid = zbx_db_get_maxid_num("httptest_tag", num_httptesttags);

		zbx_db_insert_prepare(&db_insert_httag, "httptest_tag", "httptesttagid", "httptestid", "tag", "value",
				(char *)NULL);
	}

	for (i = 0; i < httptests->values_num; i++)
	{
		httptest = (httptest_t *)httptests->values[i];

		if (0 == httptest->httptestid)
		{
			httptest->httptestid = httptestid++;

			zbx_audit_httptest_create_entry(audit_context_mode, ZBX_AUDIT_ACTION_ADD, httptest->httptestid,
					httptest->name);

			zbx_db_insert_add_values(&db_insert_htest, httptest->httptestid, httptest->name,
					httptest->delay, (int)httptest->status, httptest->agent,
					(int)httptest->authentication, httptest->http_user, httptest->http_password,
					httptest->http_proxy, httptest->retries, hostid, httptest->templateid,
					httptest->ssl_cert_file, httptest->ssl_key_file, httptest->ssl_key_password,
					httptest->verify_peer, httptest->verify_host);

			zbx_audit_httptest_update_json_add_data(audit_context_mode, httptest->httptestid,
					httptest->templateid, httptest->name, httptest->delay, (int)httptest->status,
					httptest->agent, (int)httptest->authentication, httptest->http_user,
					httptest->http_proxy, httptest->retries, httptest->ssl_cert_file,
					httptest->ssl_key_file, httptest->verify_peer, httptest->verify_host, hostid);

			for (j = 0; j < httptest->httpsteps.values_num; j++)
			{
				httpstep = (httpstep_t *)httptest->httpsteps.values[j];

				zbx_db_insert_add_values(&db_insert_hstep, httpstepid, httptest->httptestid,
						httpstep->name, httpstep->no, httpstep->url, httpstep->timeout,
						httpstep->posts, httpstep->required, httpstep->status_codes,
						httpstep->follow_redirects, httpstep->retrieve_mode,
						httpstep->post_type);

				httpstep->hoststepid = httpstepid;

				zbx_audit_httptest_update_json_add_httptest_httpstep(audit_context_mode,
						httptest->httptestid, httpstepid, httpstep->name, httpstep->no,
						httpstep->url, httpstep->timeout, httpstep->posts, httpstep->required,
						httpstep->status_codes, httpstep->follow_redirects,
						httpstep->retrieve_mode, httpstep->post_type);

				for (k = 0; k < httpstep->httpstepitems.values_num; k++)
				{
					httpstepitem_t	*httpstepitem;

					httpstepitem = (httpstepitem_t *)httpstep->httpstepitems.values[k];

					zbx_db_insert_add_values(&db_insert_hsitem,  httpstepitemid, httpstepid,
							httpstepitem->h_itemid, (int)httpstepitem->type);

					httpstepitemid++;
				}

				httpstepid++;
			}

			for (j = 0; j < httptest->httptestitems.values_num; j++)
			{
				httptestitem_t	*httptestitem;

				httptestitem = (httptestitem_t *)httptest->httptestitems.values[j];

				zbx_db_insert_add_values(&db_insert_htitem, httptestitemid, httptest->httptestid,
						httptestitem->h_itemid, (int)httptestitem->type);

				httptestitemid++;
			}
		}
		else
		{
			const char	*d = ",";

			zbx_audit_httptest_create_entry(audit_context_mode, ZBX_AUDIT_ACTION_UPDATE,
					httptest->httptestid, httptest->name);

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update httptest"
					" set templateid=" ZBX_FS_UI64, httptest->templateid);

			zbx_audit_httptest_update_json_update_templateid(audit_context_mode, httptest->httptestid,
					httptest->templateid_host, httptest->templateid);

			if (0 != (httptest->upd_flags & ZBX_FLAG_HTTPTEST_UPDATE))
			{

#define PREPARE_UPDATE_HTTPTEST_STR(FLAG, field)								\
		if (0 != (httptest->upd_flags & FLAG))								\
		{												\
			char	*str_esc = zbx_db_dyn_escape_string(httptest->field);				\
														\
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%s"#field"='%s'", d, str_esc);	\
			d = ",";										\
			zbx_free(str_esc);									\
														\
			zbx_audit_httptest_update_json_update_##field(audit_context_mode, httptest->httptestid, \
					httptest->field##_orig, httptest->field);				\
		}

#define PREPARE_UPDATE_HTTPTEST_STR_SECRET(FLAG, field)								\
		if (0 != (httptest->upd_flags & FLAG))								\
		{												\
			char	*str_esc = zbx_db_dyn_escape_string(httptest->field);				\
														\
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%s"#field"='%s'", d, str_esc);	\
			d = ",";										\
			zbx_free(str_esc);									\
														\
			zbx_audit_httptest_update_json_update_##field(audit_context_mode, httptest->httptestid, \
					(0 == strcmp("", httptest->field##_orig) ? "" :ZBX_MACRO_SECRET_MASK),	\
					(0 == strcmp("", httptest->field) ? "" : ZBX_MACRO_SECRET_MASK));	\
		}

#define PREPARE_UPDATE_HTTPTEST_INT(FLAG, field)								\
		if (0 != (httptest->upd_flags & FLAG))								\
		{												\
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%s"#field"=%d", d, httptest->field);	\
			d = ",";										\
														\
			zbx_audit_httptest_update_json_update_##field(audit_context_mode, httptest->httptestid, \
					httptest->field##_orig, httptest->field);				\
		}

#define PREPARE_UPDATE_HTTPTEST_UC(FLAG, field)									\
		if (0 != (httptest->upd_flags & FLAG))								\
		{												\
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%s"#field"=%d", d,			\
					(int)httptest->field);							\
			d = ",";										\
														\
			zbx_audit_httptest_update_json_update_##field(audit_context_mode, httptest->httptestid, \
					httptest->field##_orig, httptest->field);				\
		}
				PREPARE_UPDATE_HTTPTEST_STR(ZBX_FLAG_HTTPTEST_UPDATE_DELAY, delay)
				PREPARE_UPDATE_HTTPTEST_STR(ZBX_FLAG_HTTPTEST_UPDATE_AGENT, agent)
				PREPARE_UPDATE_HTTPTEST_STR(ZBX_FLAG_HTTPTEST_UPDATE_HTTP_USER, http_user)
				PREPARE_UPDATE_HTTPTEST_STR_SECRET(ZBX_FLAG_HTTPTEST_UPDATE_HTTP_PASSWORD,
						http_password)
				PREPARE_UPDATE_HTTPTEST_STR(ZBX_FLAG_HTTPTEST_UPDATE_HTTP_PROXY, http_proxy)
				PREPARE_UPDATE_HTTPTEST_INT(ZBX_FLAG_HTTPTEST_UPDATE_RETRIES, retries)
				PREPARE_UPDATE_HTTPTEST_UC(ZBX_FLAG_HTTPTEST_UPDATE_STATUS, status)
				PREPARE_UPDATE_HTTPTEST_UC(ZBX_FLAG_HTTPTEST_UPDATE_AUTHENTICATION, authentication)
				PREPARE_UPDATE_HTTPTEST_STR(ZBX_FLAG_HTTPTEST_UPDATE_SSL_CERT_FILE, ssl_cert_file)
				PREPARE_UPDATE_HTTPTEST_STR(ZBX_FLAG_HTTPTEST_UPDATE_SSL_KEY_FILE, ssl_key_file)
				PREPARE_UPDATE_HTTPTEST_STR_SECRET(ZBX_FLAG_HTTPTEST_UPDATE_SSL_KEY_PASSWORD,
						ssl_key_password)
				PREPARE_UPDATE_HTTPTEST_INT(ZBX_FLAG_HTTPTEST_UPDATE_VERIFY_PEER, verify_peer)
				PREPARE_UPDATE_HTTPTEST_INT(ZBX_FLAG_HTTPTEST_UPDATE_VERIFY_HOST, verify_host)
			}

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " where httptestid=" ZBX_FS_UI64 ";\n",
					httptest->httptestid);

			for (j = 0; j < httptest->httpsteps.values_num; j++)
			{
				httpstep = (httpstep_t *)httptest->httpsteps.values[j];

				if (0 != (httpstep->upd_flags & ZBX_FLAG_HTTPSTEP_UPDATE))
				{

#define PREPARE_UPDATE_HTTPSTEP_STR(FLAG, field)								\
		if (0 != (httpstep->upd_flags & FLAG))								\
		{												\
			char	*str_esc = zbx_db_dyn_escape_string(httpstep->field);				\
														\
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%s"#field"='%s'", d, str_esc);	\
			d = ",";										\
			zbx_free(str_esc);									\
														\
			zbx_audit_httptest_update_json_httpstep_update_##field(audit_context_mode,		\
					httptest->httptestid, httpstep->httpstepid, httpstep->field##_orig,	\
					httpstep->field);							\
		}												\

#define PREPARE_UPDATE_HTTPSTEP_INT(FLAG, field)								\
		if (0 != (httpstep->upd_flags & FLAG))								\
		{												\
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%s"#field"=%d", d, httpstep->field);	\
			d = ",";										\
														\
			zbx_audit_httptest_update_json_httpstep_update_##field(audit_context_mode,		\
					httptest->httptestid, httpstep->httpstepid, httpstep->field##_orig,	\
					httpstep->field);							\
		}

					d = "";
					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update httpstep set ");

					PREPARE_UPDATE_HTTPSTEP_STR(ZBX_FLAG_HTTPSTEP_UPDATE_URL, url)
					PREPARE_UPDATE_HTTPSTEP_STR(ZBX_FLAG_HTTPSTEP_UPDATE_POSTS, posts)
					PREPARE_UPDATE_HTTPSTEP_STR(ZBX_FLAG_HTTPSTEP_UPDATE_REQUIRED, required)
					PREPARE_UPDATE_HTTPSTEP_STR(ZBX_FLAG_HTTPSTEP_UPDATE_STATUS_CODES,
							status_codes)
					PREPARE_UPDATE_HTTPSTEP_STR(ZBX_FLAG_HTTPSTEP_UPDATE_TIMEOUT, timeout)
					PREPARE_UPDATE_HTTPSTEP_INT(ZBX_FLAG_HTTPSTEP_UPDATE_FOLLOW_REDIRECTS,
							follow_redirects)
					PREPARE_UPDATE_HTTPSTEP_INT(ZBX_FLAG_HTTPSTEP_UPDATE_RETRIEVE_MODE,
							retrieve_mode)
					PREPARE_UPDATE_HTTPSTEP_INT(ZBX_FLAG_HTTPSTEP_UPDATE_POST_TYPE, post_type)

					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
							" where httpstepid=" ZBX_FS_UI64 ";\n", httpstep->hoststepid);
				}
			}

			zbx_audit_httptest_update_json_update_templateid(audit_context_mode, httptest->httptestid,
					httptest->templateid_host, httptest->templateid);
		}

		for (j = 0; j < httptest->fields.values_num; j++)
		{
			httpfield = (httpfield_t *)httptest->fields.values[j];

			zbx_db_insert_add_values(&db_insert_tfield, httptestfieldid, httptest->httptestid,
					httpfield->type, httpfield->name, httpfield->value);

			zbx_audit_httptest_update_json_add_httptest_field(audit_context_mode, httptest->httptestid,
						httptestfieldid, httpfield->type, httpfield->name, httpfield->value);

			httptestfieldid++;
		}

		for (j = 0; j < httptest->httptesttags.values_num; j++)
		{
			httptesttag_t	*httptesttag;

			httptesttag = (httptesttag_t *)httptest->httptesttags.values[j];

			zbx_db_insert_add_values(&db_insert_httag, httptesttagid, httptest->httptestid,
					httptesttag->tag, httptesttag->value);

			zbx_audit_httptest_update_json_add_httptest_tag(audit_context_mode, httptest->httptestid,
					httptesttagid, httptesttag->tag, httptesttag->value);

			httptesttagid++;
		}

		for (j = 0; j < httptest->httpsteps.values_num; j++)
		{
			httpstep = (httpstep_t *)httptest->httpsteps.values[j];

			for (k = 0; k < httpstep->fields.values_num; k++)
			{
				httpfield = (httpfield_t *)httpstep->fields.values[k];

				zbx_db_insert_add_values(&db_insert_sfield, httpstepfieldid, httpstep->hoststepid,
						httpfield->type, httpfield->name, httpfield->value);

				zbx_audit_httptest_update_json_add_httpstep_field(audit_context_mode,
						httptest->httptestid, httpstep->hoststepid, httpstepfieldid,
						httpfield->type, httpfield->name, httpfield->value);
				httpstepfieldid++;
			}
		}
	}

	if (0 != num_httptests)
	{
		zbx_db_insert_execute(&db_insert_htest);
		zbx_db_insert_clean(&db_insert_htest);
	}

	if (0 != num_httpsteps)
	{
		zbx_db_insert_execute(&db_insert_hstep);
		zbx_db_insert_clean(&db_insert_hstep);
	}

	if (0 != num_httptestitems)
	{
		zbx_db_insert_execute(&db_insert_htitem);
		zbx_db_insert_clean(&db_insert_htitem);
	}

	if (0 != num_httpstepitems)
	{
		zbx_db_insert_execute(&db_insert_hsitem);
		zbx_db_insert_clean(&db_insert_hsitem);
	}

	if (0 != num_httptestfields)
	{
		zbx_db_insert_execute(&db_insert_tfield);
		zbx_db_insert_clean(&db_insert_tfield);
	}

	if (0 != num_httpstepfields)
	{
		zbx_db_insert_execute(&db_insert_sfield);
		zbx_db_insert_clean(&db_insert_sfield);
	}

	if (0 != num_httptesttags)
	{
		zbx_db_insert_execute(&db_insert_httag);
		zbx_db_insert_clean(&db_insert_httag);
	}

	(void)zbx_db_flush_overflowed_sql(sql, sql_offset);

	zbx_free(sql);

	zbx_vector_uint64_destroy(&httpupdtestids);
	zbx_vector_uint64_destroy(&httpupdstepids);
	zbx_vector_uint64_destroy(&deletefieldsids);
	zbx_vector_uint64_destroy(&deletestepfieldsids);
	zbx_vector_uint64_destroy(&deletetagids);
}

static void	clean_httptests(zbx_vector_ptr_t *httptests)
{
	httptest_t	*httptest;
	httpfield_t	*httpfield;
	httpstep_t	*httpstep;
	httptesttag_t	*httptesttag;
	int		i, j, k;

	for (i = 0; i < httptests->values_num; i++)
	{
		httptest = (httptest_t *)httptests->values[i];

		zbx_free(httptest->http_proxy);
		zbx_free(httptest->http_proxy_orig);
		zbx_free(httptest->http_password);
		zbx_free(httptest->http_password_orig);
		zbx_free(httptest->http_user);
		zbx_free(httptest->http_user_orig);
		zbx_free(httptest->agent);
		zbx_free(httptest->agent_orig);
		zbx_free(httptest->delay);
		zbx_free(httptest->delay_orig);
		zbx_free(httptest->name);
		zbx_free(httptest->ssl_cert_file);
		zbx_free(httptest->ssl_cert_file_orig);
		zbx_free(httptest->ssl_key_file);
		zbx_free(httptest->ssl_key_file_orig);
		zbx_free(httptest->ssl_key_password);
		zbx_free(httptest->ssl_key_password_orig);

		for (j = 0; j < httptest->fields.values_num; j++)
		{
			httpfield = (httpfield_t *)httptest->fields.values[j];

			zbx_free(httpfield->name);
			zbx_free(httpfield->value);

			zbx_free(httpfield);
		}

		zbx_vector_ptr_destroy(&httptest->fields);

		for (j = 0; j < httptest->httptesttags.values_num; j++)
		{
			httptesttag = (httptesttag_t *)httptest->httptesttags.values[j];

			zbx_free(httptesttag->tag);
			zbx_free(httptesttag->value);

			zbx_free(httptesttag);
		}

		zbx_vector_ptr_destroy(&httptest->httptesttags);

		for (j = 0; j < httptest->httpsteps.values_num; j++)
		{
			httpstep = (httpstep_t *)httptest->httpsteps.values[j];

			zbx_free(httpstep->status_codes);
			zbx_free(httpstep->status_codes_orig);
			zbx_free(httpstep->required);
			zbx_free(httpstep->required_orig);
			zbx_free(httpstep->posts);
			zbx_free(httpstep->posts_orig);
			zbx_free(httpstep->timeout);
			zbx_free(httpstep->timeout_orig);
			zbx_free(httpstep->url);
			zbx_free(httpstep->url_orig);
			zbx_free(httpstep->name);

			for (k = 0; k < httpstep->fields.values_num; k++)
			{
				httpfield = (httpfield_t *)httpstep->fields.values[k];

				zbx_free(httpfield->name);
				zbx_free(httpfield->value);

				zbx_free(httpfield);
			}

			zbx_vector_ptr_destroy(&httpstep->fields);

			for (k = 0; k < httpstep->httpstepitems.values_num; k++)
				zbx_free(httpstep->httpstepitems.values[k]);

			zbx_vector_ptr_destroy(&httpstep->httpstepitems);

			zbx_free(httpstep);
		}

		zbx_vector_ptr_destroy(&httptest->httpsteps);

		for (j = 0; j < httptest->httptestitems.values_num; j++)
			zbx_free(httptest->httptestitems.values[j]);

		zbx_vector_ptr_destroy(&httptest->httptestitems);

		zbx_free(httptest);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: copies web scenarios from template to host                        *
 *                                                                            *
 * Parameters:                                                                *
 *             hostid             - [IN] host id from database                *
 *             templateids        - [IN]                                      *
 *             audit_context_mode - [IN]                                      *
 *                                                                            *
 ******************************************************************************/
static void	DBcopy_template_httptests(zbx_uint64_t hostid, const zbx_vector_uint64_t *templateids,
		int audit_context_mode)
{
	zbx_vector_ptr_t	httptests;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_ptr_create(&httptests);

	DBget_httptests(hostid, templateids, &httptests);
	DBsave_httptests(hostid, &httptests, audit_context_mode);

	clean_httptests(&httptests);
	zbx_vector_ptr_destroy(&httptests);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: copies elements from specified template                           *
 *                                                                            *
 * Parameters:                                                                *
 *             hostid             - [IN] host id from database                *
 *             lnk_templateids    - [IN]                                      *
 *             link_type          - [IN] 0 - manual, 1 - LLD automatic        *
 *             audit_context_mode - [IN]                                      *
 *             error              - [OUT] error message                       *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *                                                                            *
 ******************************************************************************/
int	zbx_db_copy_template_elements(zbx_uint64_t hostid, zbx_vector_uint64_t *lnk_templateids,
		zbx_host_template_link_type link_type, int audit_context_mode, char **error)
{
	zbx_vector_uint64_t	templateids;
	zbx_uint64_t		hosttemplateid;
	int			i, res = SUCCEED;
	char			*template_names, err[MAX_STRING_LEN];
	zbx_db_insert_t		*db_insert_htemplates;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&templateids);

	get_templates_by_hostid(hostid, &templateids);

	for (i = 0; i < lnk_templateids->values_num; i++)
	{
		if (FAIL != zbx_vector_uint64_search(&templateids, lnk_templateids->values[i],
				ZBX_DEFAULT_UINT64_COMPARE_FUNC))
		{
			/* template already linked */
			zbx_vector_uint64_remove(lnk_templateids, i--);
		}
		else
			zbx_vector_uint64_append(&templateids, lnk_templateids->values[i]);
	}

	/* all templates already linked */
	if (0 == lnk_templateids->values_num)
		goto clean;

	zbx_vector_uint64_sort(&templateids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	if (SUCCEED != (res = validate_linked_templates(&templateids, err, sizeof(err))))
	{
		template_names = get_template_names(lnk_templateids);

		*error = zbx_dsprintf(NULL, "%s to host \"%s\": %s", template_names, zbx_host_string(hostid), err);

		zbx_free(template_names);
		goto clean;
	}

	if (SUCCEED != (res = validate_host(hostid, lnk_templateids, err, sizeof(err))))
	{
		template_names = get_template_names(lnk_templateids);

		*error = zbx_dsprintf(NULL, "%s to host \"%s\": %s", template_names, zbx_host_string(hostid), err);

		zbx_free(template_names);
		goto clean;
	}

	hosttemplateid = zbx_db_get_maxid_num("hosts_templates", lnk_templateids->values_num);

	db_insert_htemplates = zbx_malloc(NULL, sizeof(zbx_db_insert_t));

	zbx_db_insert_prepare(db_insert_htemplates, "hosts_templates",  "hosttemplateid", "hostid", "templateid",
			"link_type", (char *)NULL);

	for (i = 0; i < lnk_templateids->values_num; i++)
	{
		zbx_db_insert_add_values(db_insert_htemplates, hosttemplateid, hostid, lnk_templateids->values[i],
				link_type);
		zbx_audit_host_update_json_add_parent_template(audit_context_mode, hostid, hosttemplateid,
				lnk_templateids->values[i], link_type);

		hosttemplateid++;
	}

	DBcopy_template_items(hostid, lnk_templateids, audit_context_mode);
	DBcopy_template_host_prototypes(hostid, lnk_templateids, audit_context_mode, db_insert_htemplates);

	zbx_db_insert_execute(db_insert_htemplates);
	zbx_db_insert_clean(db_insert_htemplates);
	zbx_free(db_insert_htemplates);

	if (SUCCEED == (res = DBcopy_template_triggers(hostid, lnk_templateids, audit_context_mode, error)))
	{
		res = DBcopy_template_graphs(hostid, lnk_templateids, audit_context_mode);
		DBcopy_template_httptests(hostid, lnk_templateids, audit_context_mode);
	}
clean:
	zbx_vector_uint64_destroy(&templateids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(res));

	return res;
}

static zbx_uint64_t	permission_hgset_get(const char *hash_str)
{
	char		*sql;
	zbx_uint64_t	hgsetid;
	zbx_db_result_t	result;
	zbx_db_row_t	row;

	sql = zbx_dsprintf(NULL, "select hgsetid from hgset where hash='%s'", hash_str);
	result = zbx_db_select_n(sql, 1);
	zbx_free(sql);

	if (NULL != (row = zbx_db_fetch(result)))
		ZBX_STR2UINT64(hgsetid, row[0]);
	else
		hgsetid = 0;

	zbx_db_free_result(result);

	return hgsetid;
}

static zbx_uint64_t	permission_hgset_add(const char *hash_str, zbx_vector_uint64_t *groupids)
{
	int				i;
	char				*sql = NULL;
	size_t				sql_alloc = 0, sql_offset = 0;
	zbx_uint64_t			hgsetid;
	zbx_db_insert_t			db_insert;
	zbx_db_result_t			result;
	zbx_db_row_t			row;
	zbx_vector_hgset_permission_t	permissions;

	hgsetid = zbx_db_get_maxid("hgset");
	zbx_db_insert_prepare(&db_insert, "hgset", "hgsetid", "hash", NULL);
	zbx_db_insert_add_values(&db_insert, hgsetid, hash_str);
	zbx_db_insert_execute(&db_insert);

	zbx_db_insert_clean(&db_insert);
	zbx_db_insert_prepare(&db_insert, "hgset_group", "hgsetid", "groupid", NULL);

	for (i = 0; i < groupids->values_num; i++)
		zbx_db_insert_add_values(&db_insert, hgsetid, groupids->values[i]);

	zbx_db_insert_execute(&db_insert);

	zbx_vector_hgset_permission_create(&permissions);

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select distinct u.ugsetid,r.permission from ugset_group u"
			" join rights r on u.usrgrpid=r.groupid"
			" where");
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "r.id", groupids->values, groupids->values_num);
	result = zbx_db_select("%s order by u.ugsetid,r.permission", sql);
	zbx_free(sql);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_hgset_permission_t	*permission_last, permission;

		ZBX_STR2UINT64(permission.ugsetid, row[0]);
		permission.permission = atoi(row[1]);

		if (0 == permissions.values_num ||
				permissions.values[permissions.values_num - 1].ugsetid != permission.ugsetid)
		{
			zbx_vector_hgset_permission_append(&permissions, permission);
			continue;
		}

		permission_last = &permissions.values[permissions.values_num - 1];

		if (PERM_DENY != permission_last->permission && permission_last->permission < permission.permission)
			permission_last->permission = permission.permission;
	}
	zbx_db_free_result(result);

	zbx_db_insert_clean(&db_insert);
	zbx_db_insert_prepare(&db_insert, "permission", "ugsetid", "hgsetid", "permission", NULL);

	for (i = 0; i < permissions.values_num; i++)
	{
		zbx_hgset_permission_t	*permission = &permissions.values[i];

		if (PERM_DENY != permission->permission)
			zbx_db_insert_add_values(&db_insert, permission->ugsetid, hgsetid, permission->permission);
	}

	zbx_vector_hgset_permission_destroy(&permissions);

	zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);

	return hgsetid;
}

static void	permission_hgset_remove(zbx_uint64_t hgsetid)
{
	char		*sql;
	zbx_db_result_t	result;

	sql = zbx_dsprintf(NULL, "select null from host_hgset where hgsetid=" ZBX_FS_UI64, hgsetid);
	result = zbx_db_select_n(sql, 1);
	zbx_free(sql);

	if (NULL == zbx_db_fetch(result))
		zbx_db_execute("delete from hgset where hgsetid=" ZBX_FS_UI64, hgsetid);

	zbx_db_free_result(result);
}

static void	host_permissions_update(zbx_uint64_t hostid)
{
	zbx_uint64_t		hgsetid = 0, hgsetid_old = 0;
	size_t			sql_alloc = 0, sql_offset = 0;
	char			*sql = NULL, hash_str[ZBX_SHA256_DIGEST_SIZE * 2 + 1];
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	zbx_vector_uint64_t	groupids;

	zbx_vector_uint64_create(&groupids);
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select groupid from hosts_groups where hostid=" ZBX_FS_UI64, hostid);
	zbx_db_select_uint64(sql, &groupids);

	if (0 == groupids.values_num)
		goto out;

	zbx_hgset_hash_calculate(&groupids, hash_str, sizeof(hash_str));

	if (0 == (hgsetid = permission_hgset_get(hash_str)) &&
			0 == (hgsetid = permission_hgset_add(hash_str, &groupids)))
	{
		goto out;
	}

	sql_offset = 0;
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "select hgsetid from host_hgset where hostid=" ZBX_FS_UI64,
			hostid);
	result = zbx_db_select_n(sql, 1);

	if (NULL != (row = zbx_db_fetch(result)))
		ZBX_STR2UINT64(hgsetid_old, row[0]);

	zbx_db_free_result(result);

	if (0 != hgsetid_old)
	{
		if (ZBX_DB_OK <= zbx_db_execute("update host_hgset set hgsetid=" ZBX_FS_UI64
				" where hostid=" ZBX_FS_UI64, hgsetid, hostid))
		{
			permission_hgset_remove(hgsetid_old);
		}
	}
	else
	{
		zbx_db_insert_t	db_insert;

		zbx_db_insert_prepare(&db_insert, "host_hgset", "hostid", "hgsetid", NULL);
		zbx_db_insert_add_values(&db_insert, hostid, hgsetid);

		zbx_db_insert_execute(&db_insert);
		zbx_db_insert_clean(&db_insert);
	}
out:
	zbx_vector_uint64_destroy(&groupids);
	zbx_free(sql);
}

void	zbx_host_groups_add(zbx_uint64_t hostid, zbx_vector_uint64_t *groupids, int audit_context_mode)
{
	int		i;
	zbx_uint64_t	hostgroupid;
	zbx_db_insert_t	db_insert;

	hostgroupid = zbx_db_get_maxid_num("hosts_groups", groupids->values_num);

	zbx_db_insert_prepare(&db_insert, "hosts_groups", "hostgroupid", "hostid", "groupid", (char *)NULL);

	zbx_vector_uint64_sort(groupids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	for (i = 0; i < groupids->values_num; i++)
	{
		zbx_db_insert_add_values(&db_insert, hostgroupid, hostid, groupids->values[i]);
		zbx_audit_hostgroup_update_json_add_group(audit_context_mode, hostid, hostgroupid++, groupids->values[i]);
	}

	zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);

	host_permissions_update(hostid);
}

void	zbx_host_groups_remove(zbx_uint64_t hostid, zbx_vector_uint64_t *hostgroupids)
{
	size_t		sql_alloc = 0, sql_offset = 0;
	char		*sql = NULL;

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"delete from hosts_groups"
			" where");
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostgroupid", hostgroupids->values,
			hostgroupids->values_num);

	zbx_db_execute("%s", sql);
	zbx_free(sql);

	host_permissions_update(hostid);
}

/******************************************************************************
 *                                                                            *
 * Purpose: deletes hosts from database with all elements                     *
 *                                                                            *
 * Parameters:                                                                *
 *             hostids            - [IN] host ids from database               *
 *             hostnames          - [IN]                                      *
 *             audit_context_mode - [IN]                                      *
 *                                                                            *
 ******************************************************************************/
void	zbx_db_delete_hosts(const zbx_vector_uint64_t *hostids, const zbx_vector_str_t *hostnames,
		int audit_context_mode)
{
	int			i;
	zbx_vector_uint64_t	itemids, httptestids, hgsetids_del, selementids;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (SUCCEED != zbx_db_lock_hostids(hostids))
		goto out;

	zbx_vector_uint64_create(&httptestids);
	zbx_vector_uint64_create(&selementids);
	zbx_vector_uint64_create(&hgsetids_del);

	/* delete web tests */

	sql_offset = 0;
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select httptestid"
			" from httptest"
			" where");
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid", hostids->values, hostids->values_num);

	zbx_db_select_uint64(sql, &httptestids);

	DBdelete_httptests(&httptestids, audit_context_mode);

	zbx_vector_uint64_destroy(&httptestids);

	zbx_vector_uint64_create(&itemids);

	sql_offset = 0;
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select itemid,name,flags"
			" from items"
			" where");
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid", hostids->values, hostids->values_num);

	zbx_db_select_uint64(sql, &itemids);
	zbx_db_delete_items(&itemids, audit_context_mode);

	zbx_vector_uint64_destroy(&itemids);

	sql_offset = 0;
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select distinct hgsetid"
			" from host_hgset"
			" where");
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid", hostids->values, hostids->values_num);

	zbx_db_select_uint64(sql, &hgsetids_del);

	if (0 < hgsetids_del.values_num)
	{
		zbx_vector_uint64_t	hgsetids_keep;

		zbx_vector_uint64_create(&hgsetids_keep);

		sql_offset = 0;
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				"select distinct hgsetid"
				" from host_hgset"
				" where");
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "hgsetid", hgsetids_del.values,
				hgsetids_del.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				" and not");
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid", hostids->values,
				hostids->values_num);

		zbx_db_select_uint64(sql, &hgsetids_keep);

		for (i = 0; i < hgsetids_del.values_num; i++)
		{
			if (FAIL != zbx_vector_uint64_bsearch(&hgsetids_keep, hgsetids_del.values[i],
					ZBX_DEFAULT_UINT64_COMPARE_FUNC))
			{
				zbx_vector_uint64_remove(&hgsetids_del, i--);
			}
		}

		zbx_vector_uint64_destroy(&hgsetids_keep);
	}

	sql_offset = 0;

	/* delete host from maps */
	DBget_sysmapelements_by_element_type_ids(&selementids, SYSMAP_ELEMENT_TYPE_HOST, hostids);
	if (0 != selementids.values_num)
	{
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from sysmaps_elements where");
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "selementid", selementids.values,
				selementids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");
	}

	/* delete action conditions */
	for (i = 0; i < hostids->values_num; i++)
		DBdelete_action_conditions(ZBX_CONDITION_TYPE_HOST, hostids->values[i]);

	/* delete host tags */
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from host_tag where");
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid", hostids->values, hostids->values_num);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");
	zbx_db_execute_overflowed_sql(&sql, &sql_alloc, &sql_offset);

	/* delete host_proxy links */
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from host_proxy where");
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid", hostids->values, hostids->values_num);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");
	zbx_db_execute_overflowed_sql(&sql, &sql_alloc, &sql_offset);

	/* delete host */
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from hosts where");
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid", hostids->values, hostids->values_num);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");

	/* delete host groups sets */
	if (0 < hgsetids_del.values_num)
	{
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from hgset where");
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "hgsetid", hgsetids_del.values,
				hgsetids_del.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");
	}

	zbx_db_execute("%s", sql);

	for (i = 0; i < hostids->values_num; i++)
		zbx_audit_host_del(audit_context_mode, hostids->values[i], hostnames->values[i]);

	zbx_free(sql);

	zbx_vector_uint64_destroy(&selementids);
	zbx_vector_uint64_destroy(&hgsetids_del);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: Deletes hosts from database, checks if there are any hosts        *
 *          prototypes and deletes them first.                                *
 *                                                                            *
 * Parameters:                                                                *
 *             hostids            - [IN] host ids from database               *
 *             hostnames          - [IN]                                      *
 *             audit_context_mode - [IN]                                      *
 *                                                                            *
 ******************************************************************************/
void	zbx_db_delete_hosts_with_prototypes(const zbx_vector_uint64_t *hostids, const zbx_vector_str_t *hostnames,
		int audit_context_mode)
{
	zbx_vector_uint64_t	host_prototype_ids;
	zbx_vector_str_t	host_prototype_names;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&host_prototype_ids);
	zbx_vector_str_create(&host_prototype_names);

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select hd.hostid,h.name"
			" from items i,host_discovery hd, hosts h"
			" where hd.hostid=h.hostid and i.itemid=hd.parent_itemid"
				" and");
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "i.hostid", hostids->values, hostids->values_num);

	if (FAIL == DBselect_ids_names(sql, &host_prototype_ids, &host_prototype_names))
		goto clean;

	DBdelete_host_prototypes(&host_prototype_ids, &host_prototype_names, audit_context_mode);

	zbx_db_delete_hosts(hostids, hostnames, audit_context_mode);
clean:
	zbx_free(sql);
	zbx_vector_uint64_destroy(&host_prototype_ids);
	zbx_vector_str_clear_ext(&host_prototype_names, zbx_str_free);
	zbx_vector_str_destroy(&host_prototype_names);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: adds new interface to specified host                              *
 *                                                                            *
 * Parameters:                                                                *
 *             hostid             - [IN] host id from database                *
 *             type               - [IN] new interface type                   *
 *             useip              - [IN] how to connect to host 0/1 - DNS/IP  *
 *             ip                 - [IN]                                      *
 *             dns                - [IN]                                      *
 *             port               - [IN]                                      *
 *             flags              - [IN] used connection type                 *
 *             audit_context_mode - [IN]                                      *
 *                                                                            *
 * Return value: upon successful completion return interface identifier       *
 *                                                                            *
 ******************************************************************************/
zbx_uint64_t	zbx_db_add_interface(zbx_uint64_t hostid, unsigned char type, unsigned char useip,
		const char *ip, const char *dns, unsigned short port, zbx_conn_flags_t flags, int audit_context_mode)
{
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	char			*ip_esc, *dns_esc, *tmp = NULL;
	zbx_uint64_t		interfaceid = 0;
	unsigned char		main_ = 1, db_main, db_useip;
	unsigned short		db_port;
	const char		*db_ip, *db_dns;
	zbx_dc_um_handle_t	*um_handle;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	um_handle = zbx_dc_open_user_macros();

	result = zbx_db_select(
			"select interfaceid,useip,ip,dns,port,main"
			" from interface"
			" where hostid=" ZBX_FS_UI64
				" and type=%d",
			hostid, (int)type);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		db_useip = (unsigned char)atoi(row[1]);
		db_ip = row[2];
		db_dns = row[3];
		db_main = (unsigned char)atoi(row[5]);
		if (1 == db_main)
			main_ = 0;

		if (ZBX_CONN_DEFAULT == flags)
		{
			if (db_useip != useip)
				continue;
			if (useip && 0 != strcmp(db_ip, ip))
				continue;

			if (!useip && 0 != strcmp(db_dns, dns))
				continue;

			zbx_free(tmp);
			tmp = strdup(row[4]);
			zbx_substitute_simple_macros(NULL, NULL, NULL, NULL, &hostid, NULL, NULL, NULL, NULL, NULL,
					NULL, NULL, &tmp, ZBX_MACRO_TYPE_COMMON, NULL, 0);
			if (FAIL == zbx_is_ushort(tmp, &db_port) || db_port != port)
				continue;

			ZBX_STR2UINT64(interfaceid, row[0]);
			break;
		}

		/* update main interface if explicit connection flags were passed (flags != ZBX_CONN_DEFAULT) */
		if (1 == db_main)
		{
			char	*update = NULL, delim = ' ';
			size_t	update_alloc = 0, update_offset = 0;

			ZBX_STR2UINT64(interfaceid, row[0]);

			if (db_useip != useip)
			{
				zbx_snprintf_alloc(&update, &update_alloc, &update_offset, "%cuseip=%d", delim, useip);
				delim = ',';
				zbx_audit_host_update_json_update_interface_useip(audit_context_mode, hostid,
						interfaceid, db_useip, useip);
			}

			if (ZBX_CONN_IP == flags && 0 != strcmp(db_ip, ip))
			{
				ip_esc = zbx_db_dyn_escape_field("interface", "ip", ip);
				zbx_snprintf_alloc(&update, &update_alloc, &update_offset, "%cip='%s'", delim, ip_esc);
				zbx_free(ip_esc);
				delim = ',';
				zbx_audit_host_update_json_update_interface_ip(audit_context_mode, hostid, interfaceid,
						db_ip, ip);
			}

			if (ZBX_CONN_DNS == flags && 0 != strcmp(db_dns, dns))
			{
				dns_esc = zbx_db_dyn_escape_field("interface", "dns", dns);
				zbx_snprintf_alloc(&update, &update_alloc, &update_offset, "%cdns='%s'", delim,
						dns_esc);
				zbx_free(dns_esc);
				delim = ',';
				zbx_audit_host_update_json_update_interface_dns(audit_context_mode, hostid, interfaceid,
						db_dns, dns);
			}

			if (FAIL == zbx_is_ushort(row[4], &db_port) || db_port != port)
			{
				zbx_snprintf_alloc(&update, &update_alloc, &update_offset, "%cport=%u", delim, port);
				zbx_audit_host_update_json_update_interface_port(audit_context_mode, hostid,
						interfaceid, db_port, port);
			}

			if (0 != update_alloc)
			{
				zbx_db_execute("update interface set%s where interfaceid=" ZBX_FS_UI64, update,
						interfaceid);
				zbx_free(update);
			}
			break;
		}
	}
	zbx_db_free_result(result);

	zbx_free(tmp);

	if (0 != interfaceid)
		goto out;

	ip_esc = zbx_db_dyn_escape_field("interface", "ip", ip);
	dns_esc = zbx_db_dyn_escape_field("interface", "dns", dns);

	interfaceid = zbx_db_get_maxid_num("interface", 1);

	zbx_db_execute("insert into interface"
			" (interfaceid,hostid,main,type,useip,ip,dns,port)"
		" values"
			" (" ZBX_FS_UI64 "," ZBX_FS_UI64 ",%d,%d,%d,'%s','%s',%d)",
		interfaceid, hostid, (int)main_, (int)type, (int)useip, ip_esc, dns_esc, (int)port);

	zbx_audit_host_update_json_add_interfaces(audit_context_mode, hostid, interfaceid, main_, type, useip, ip_esc,
			dns_esc, (int)port);
	zbx_free(dns_esc);
	zbx_free(ip_esc);
out:
	zbx_dc_close_user_macros(um_handle);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():" ZBX_FS_UI64, __func__, interfaceid);

	return interfaceid;
}

/******************************************************************************
 *                                                                            *
 * Purpose: adds new or updates interface options to specified interface      *
 *                                                                            *
 * Parameters:                                                                *
 *             interfaceid        - [IN] interface id from database           *
 *             version            - [IN] snmp version                         *
 *             bulk               - [IN] snmp bulk options                    *
 *             community          - [IN] snmp community name                  *
 *             securityname       - [IN] snmp v3 security name                *
 *             securitylevel      - [IN] snmp v3 security level               *
 *             authpassphrase     - [IN] snmp v3 authentication passphrase    *
 *             privpassphrase     - [IN] snmp v3 private passphrase           *
 *             authprotocol       - [IN] snmp v3 authentication protocol      *
 *             privprotocol       - [IN] snmp v3 private protocol             *
 *             contextname        - [IN] snmp v3 context name                 *
 *             hostid             - [IN]                                      *
 *             audit_context_mode - [IN]                                      *
 *                                                                            *
 ******************************************************************************/
void	zbx_db_add_interface_snmp(const zbx_uint64_t interfaceid, const unsigned char version,
		const unsigned char bulk, const char *community, const char *securityname,
		const unsigned char securitylevel, const char *authpassphrase, const char *privpassphrase,
		const unsigned char authprotocol, const unsigned char privprotocol, const char *contextname,
		const zbx_uint64_t hostid, int audit_context_mode)
{
	char		*community_esc, *securityname_esc, *authpassphrase_esc, *privpassphrase_esc, *contextname_esc;
	unsigned char	db_version, db_bulk, db_securitylevel, db_authprotocol, db_privprotocol;
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	int		break_loop = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() interfaceid:" ZBX_FS_UI64, __func__, interfaceid);

	result = zbx_db_select(
			"select version,bulk,community,securityname,securitylevel,authpassphrase,privpassphrase,"
			"authprotocol,privprotocol,contextname"
			" from interface_snmp"
			" where interfaceid=" ZBX_FS_UI64,
			interfaceid);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		ZBX_STR2UCHAR(db_version, row[0]);

		if (version != db_version)
			break_loop = 1;

		ZBX_STR2UCHAR(db_bulk, row[1]);

		if (bulk != db_bulk)
			break_loop = 1;

		if (0 != strcmp(community, row[2]))
			break_loop = 1;

		if (0 != strcmp(securityname, row[3]))
			break_loop = 1;

		ZBX_STR2UCHAR(db_securitylevel, row[4]);

		if (securitylevel != db_securitylevel)
			break_loop = 1;

		if (0 != strcmp(authpassphrase, row[5]))
			break_loop = 1;

		if (0 != strcmp(privpassphrase, row[6]))
			break_loop = 1;

		ZBX_STR2UCHAR(db_authprotocol, row[7]);

		if (authprotocol != db_authprotocol)
			break_loop = 1;

		ZBX_STR2UCHAR(db_privprotocol, row[8]);

		if (privprotocol != db_privprotocol)
			break_loop = 1;

		if (0 != strcmp(contextname, row[9]))
			break_loop = 1;

		if (1 == break_loop)
			break;

		goto out;
	}

	community_esc = zbx_db_dyn_escape_field("interface_snmp", "community", community);
	securityname_esc = zbx_db_dyn_escape_field("interface_snmp", "securityname", securityname);
	authpassphrase_esc = zbx_db_dyn_escape_field("interface_snmp", "authpassphrase", authpassphrase);
	privpassphrase_esc = zbx_db_dyn_escape_field("interface_snmp", "privpassphrase", privpassphrase);
	contextname_esc = zbx_db_dyn_escape_field("interface_snmp", "contextname", contextname);

	if (NULL == row)
	{
		zbx_db_execute("insert into interface_snmp"
				" (interfaceid,version,bulk,community,securityname,securitylevel,authpassphrase,"
				" privpassphrase,authprotocol,privprotocol,contextname)"
			" values"
				" (" ZBX_FS_UI64 ",%d,%d,'%s','%s',%d,'%s','%s',%d,%d,'%s')",
			interfaceid, (int)version, (int)bulk, community_esc, securityname_esc, (int)securitylevel,
			authpassphrase_esc, privpassphrase_esc, (int)authprotocol, (int)privprotocol, contextname_esc);

		zbx_audit_host_update_json_add_snmp_interface(audit_context_mode, hostid, version, bulk, community_esc,
				securityname_esc, securitylevel, authpassphrase_esc, privpassphrase_esc, authprotocol,
				privprotocol, contextname_esc, interfaceid);
	}
	else
	{
		zbx_db_execute(
			"update interface_snmp"
			" set version=%d"
				",bulk=%d"
				",community='%s'"
				",securityname='%s'"
				",securitylevel=%d"
				",authpassphrase='%s'"
				",privpassphrase='%s'"
				",authprotocol=%d"
				",privprotocol=%d"
				",contextname='%s'"
			" where interfaceid=" ZBX_FS_UI64,
			(int)version, (int)bulk, community_esc, securityname_esc, (int)securitylevel,
			authpassphrase_esc, privpassphrase_esc, (int)authprotocol, (int)privprotocol, contextname_esc,
			interfaceid);

		zbx_audit_host_update_json_update_snmp_interface(audit_context_mode, hostid, db_version, version,
				db_bulk, bulk, row[2], community_esc, row[3], securityname_esc, db_securitylevel,
				securitylevel, row[5], authpassphrase_esc, row[6], privpassphrase_esc, db_authprotocol,
				authprotocol, db_privprotocol, privprotocol, row[9], contextname_esc, interfaceid);
	}

	zbx_free(community_esc);
	zbx_free(securityname_esc);
	zbx_free(authpassphrase_esc);
	zbx_free(privpassphrase_esc);
	zbx_free(contextname_esc);
out:
	zbx_db_free_result(result);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: Removes the groupids from the list which cannot be deleted        *
 *          (host or template can remain without groups or it's an internal   *
 *          group or it's used by a host prototype).                          *
 *                                                                            *
 ******************************************************************************/
static void	DBdelete_groups_validate(zbx_vector_uint64_t *groupids)
{
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_vector_uint64_t	hostids;
	zbx_uint64_t		groupid;
	int			index;

	if (0 == groupids->values_num)
		return;

	zbx_vector_uint64_create(&hostids);

	/* select of the list of hosts which remain without groups */

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select hg.hostid"
			" from hosts_groups hg"
			" where");
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "hg.groupid", groupids->values, groupids->values_num);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			" and not exists ("
				"select null"
				" from hosts_groups hg2"
				" where hg.hostid=hg2.hostid"
					" and not");
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "hg2.groupid", groupids->values,
			groupids->values_num);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ")");

	zbx_db_select_uint64(sql, &hostids);

	/* select of the list of groups which cannot be deleted */

	sql_offset = 0;
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select g.groupid,g.name,c.discovery_groupid"
			" from hstgrp g,config c"
			" where");
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "g.groupid", groupids->values, groupids->values_num);
	if (0 < hostids.values_num)
	{
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				" and (g.groupid=c.discovery_groupid"
					" or exists ("
						"select null"
						" from hosts_groups hg"
						" where g.groupid=hg.groupid"
							" and");
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "hg.hostid", hostids.values,
				hostids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "))");
	}
	else
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " and g.groupid=c.discovery_groupid");

	result = zbx_db_select("%s", sql);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		ZBX_STR2UINT64(groupid, row[0]);

		if (FAIL != (index = zbx_vector_uint64_bsearch(groupids, groupid, ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
			zbx_vector_uint64_remove(groupids, index);

		if (0 == hostids.values_num || 0 == strcmp(row[0], row[2]))
		{
			zabbix_log(LOG_LEVEL_WARNING, "host group \"%s\" is used for network discovery"
					" and cannot be deleted", row[1]);
		}
		else
		{
			zabbix_log(LOG_LEVEL_WARNING, "host group \"%s\" cannot be deleted,"
					" because some hosts or templates depend on it", row[1]);
		}
	}
	zbx_db_free_result(result);

	/* check if groups is used in the groups prototypes */

	if (0 != groupids->values_num)
	{
		sql_offset = 0;
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				"select g.groupid,g.name"
				" from hstgrp g"
				" where");
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "g.groupid",
				groupids->values, groupids->values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
					" and exists ("
						"select null"
						" from group_prototype gp"
						" where g.groupid=gp.groupid"
					")");

		result = zbx_db_select("%s", sql);

		while (NULL != (row = zbx_db_fetch(result)))
		{
			ZBX_STR2UINT64(groupid, row[0]);

			if (FAIL != (index = zbx_vector_uint64_bsearch(groupids, groupid,
					ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
			{
				zbx_vector_uint64_remove(groupids, index);
			}

			zabbix_log(LOG_LEVEL_WARNING, "host group \"%s\" cannot be deleted,"
					" because it is used by a host prototype", row[1]);
		}
		zbx_db_free_result(result);
	}

	zbx_vector_uint64_destroy(&hostids);
	zbx_free(sql);
}

typedef struct
{
	zbx_uint64_t		hgsetid;
	char			hash_str[ZBX_SHA256_DIGEST_SIZE * 2 + 1];
	zbx_vector_uint64_t	hgroupids;
	zbx_vector_uint64_t	hostids;
} zbx_hgset_t;

ZBX_PTR_VECTOR_DECL(hgset_ptr, zbx_hgset_t*)
ZBX_PTR_VECTOR_IMPL(hgset_ptr, zbx_hgset_t*)

static int	hgset_compare_hash(const void *d1, const void *d2)
{
	const zbx_hgset_t	*h1 = *((const zbx_hgset_t * const *)d1);
	const zbx_hgset_t	*h2 = *((const zbx_hgset_t * const *)d2);

	return strcmp(h1->hash_str, h2->hash_str);
}

static void	hgset_free(zbx_hgset_t *hgset)
{
	zbx_vector_uint64_destroy(&hgset->hgroupids);
	zbx_vector_uint64_destroy(&hgset->hostids);
	zbx_free(hgset);
}

static int	hgset_hash_search(const void *d1, const void *d2)
{
	const zbx_hgset_t	*h1 = *((const zbx_hgset_t * const *)d1);
	const char		*h2 = *((const char * const *)d2);

	return strcmp(h1->hash_str, h2);
}

typedef struct
{
	zbx_uint64_t		ugsetid;
	zbx_uint64_t		hgsetid;
	int			permission;
} zbx_host_permission_t;

ZBX_VECTOR_DECL(host_permission, zbx_host_permission_t)
ZBX_VECTOR_IMPL(host_permission, zbx_host_permission_t)

static int	host_permission_compare(const void *d1, const void *d2)
{
	const zbx_host_permission_t	*p1 = (const zbx_host_permission_t * )d1;
	const zbx_host_permission_t	*p2 = (const zbx_host_permission_t * )d2;

	ZBX_RETURN_IF_NOT_EQUAL(p1->ugsetid, p2->ugsetid);
	ZBX_RETURN_IF_NOT_EQUAL(p1->hgsetid, p2->hgsetid);

	return 0;
}

static void	hgset_hash_add(zbx_hgset_t *hgset)
{
	zbx_hgset_hash_calculate(&hgset->hgroupids, hgset->hash_str, sizeof(hgset->hash_str));
}

/******************************************************************************
 *                                                                            *
 * Purpose: deletes host groups from database                                 *
 *                                                                            *
 * Parameters: groupids - [IN] array of group ids from database               *
 *                                                                            *
 ******************************************************************************/
void	zbx_db_delete_groups(zbx_vector_uint64_t *groupids)
{
	int			i, new_hgsets = 0, ret = FAIL;
	char			*sql = NULL;
	size_t			sql_alloc = 256, sql_offset = 0;
	zbx_uint64_t		hgsetid;
	zbx_vector_uint64_t	ids, hgsetids;
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	zbx_db_insert_t		db_insert, db_insert_group;
	zbx_hgset_t		*hgset;
	zbx_vector_hgset_ptr_t	hgsets;
	zbx_vector_str_t	hashes;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() values_num:%d", __func__, groupids->values_num);

	DBdelete_groups_validate(groupids);

	if (0 == groupids->values_num)
		goto out;

	for (i = 0; i < groupids->values_num; i++)
		DBdelete_action_conditions(ZBX_CONDITION_TYPE_HOST_GROUP, groupids->values[i]);

	zbx_vector_uint64_create(&ids);
	zbx_vector_uint64_create(&hgsetids);
	zbx_vector_hgset_ptr_create(&hgsets);

	sql = (char *)zbx_malloc(sql, sql_alloc);

	/* get affected hgsets with host groups and host ids */

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select h.hgsetid,h.groupid"
			" from hgset_group h"
			" where exists ("
				"select null"
				" from hgset_group h2"
				" where h.hgsetid=h2.hgsetid"
				" and");
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "h2.groupid", groupids->values, groupids->values_num);
	result = zbx_db_select("%s) order by h.hgsetid,h.groupid", sql);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_uint64_t	hostgroupid;

		ZBX_STR2UINT64(hgsetid, row[0]);
		ZBX_STR2UINT64(hostgroupid, row[1]);

		zbx_vector_uint64_append(&hgsetids, hgsetid);

		if (FAIL != zbx_vector_uint64_bsearch(groupids, hostgroupid, ZBX_DEFAULT_UINT64_COMPARE_FUNC))
			continue;

		if (0 == hgsets.values_num || hgsetid != hgsets.values[hgsets.values_num - 1]->hgsetid)
		{
			if (0 != hgsets.values_num)
				hgset_hash_add(hgsets.values[hgsets.values_num - 1]);

			hgset = zbx_malloc(NULL, sizeof(zbx_hgset_t));

			hgset->hgsetid = hgsetid;
			zbx_vector_uint64_create(&hgset->hostids);
			zbx_vector_uint64_create(&hgset->hgroupids);
			zbx_vector_hgset_ptr_append(&hgsets, hgset);
		}

		hgset = hgsets.values[hgsets.values_num - 1];
		zbx_vector_uint64_append(&hgset->hgroupids, hostgroupid);
	}
	zbx_db_free_result(result);

	sql_offset = 0;

	if (0 == hgsets.values_num)
	{
		goto skip_permissions;
	}

	/* calculate hash for last hgset in vector */
	hgset_hash_add(hgsets.values[hgsets.values_num - 1]);

	zbx_vector_uint64_sort(&hgsetids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_uniq(&hgsetids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select hostid,hgsetid from host_hgset where");
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "hgsetid", hgsetids.values, hgsetids.values_num);

	result = zbx_db_select("%s order by hostid", sql);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_uint64_t	hostid;

		ZBX_STR2UINT64(hostid, row[0]);
		ZBX_STR2UINT64(hgsetid, row[1]);

		if (FAIL != (i = zbx_vector_hgset_ptr_search(&hgsets, (void*)&hgsetid,
				ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
		{
			zbx_vector_uint64_append(&hgsets.values[i]->hostids, hostid);
		}
	}
	zbx_db_free_result(result);

	/* find existing hgsets */

	zbx_vector_str_create(&hashes);
	zbx_vector_hgset_ptr_sort(&hgsets, hgset_compare_hash);

	for (i = 0; i < hgsets.values_num - 1; i++)
	{
		zbx_hgset_t	*hgset_next = hgsets.values[i + 1];

		hgset = hgsets.values[i];

		if (0 == strcmp(hgset->hash_str, hgset_next->hash_str))
		{
			zbx_vector_uint64_append_array(&hgset->hostids, hgset_next->hostids.values,
					hgset_next->hostids.values_num);
			hgset_free(hgset_next);
			zbx_vector_hgset_ptr_remove(&hgsets, i + 1);
			i--;
		}
		else
		{
			zbx_vector_str_append(&hashes, hgset->hash_str);
			hgset->hgsetid = 0;
		}
	}

	hgsets.values[hgsets.values_num - 1]->hgsetid = 0;
	zbx_vector_str_append(&hashes, hgsets.values[hgsets.values_num - 1]->hash_str);

	new_hgsets = hgsets.values_num;

	sql_offset = 0;
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select hgsetid,hash from hgset where");
	zbx_db_add_str_condition_alloc(&sql, &sql_alloc, &sql_offset, "hash", (const char * const *)hashes.values,
			hashes.values_num);
	zbx_vector_str_destroy(&hashes);

	result = zbx_db_select("%s", sql);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		if (FAIL == (i = zbx_vector_hgset_ptr_search(&hgsets, (void*)row[1], hgset_hash_search)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		ZBX_STR2UINT64(hgsets.values[i]->hgsetid, row[0]);
		new_hgsets--;
	}
	zbx_db_free_result(result);

	/* insert hgsets with permissions */

	if (0 < new_hgsets)
	{
		zbx_vector_host_permission_t	permissions;
		zbx_uint64_t			new_permission_hgsetid;

		zbx_db_insert_prepare(&db_insert, "hgset", "hgsetid", "hash", (char *)NULL);
		zbx_db_insert_prepare(&db_insert_group, "hgset_group", "hgsetid", "groupid", (char *)NULL);

		new_permission_hgsetid = hgsetid = zbx_db_get_maxid_num("hgset", new_hgsets);

		for (i = 0; i < hgsets.values_num; i++)
		{
			if (0 != hgsets.values[i]->hgsetid)
				continue;

			hgsets.values[i]->hgsetid = hgsetid++;
			zbx_db_insert_add_values(&db_insert, hgsets.values[i]->hgsetid, hgsets.values[i]->hash_str);

			for (int j = 0; j < hgsets.values[i]->hgroupids.values_num; j++)
			{
				zbx_db_insert_add_values(&db_insert_group, hgsets.values[i]->hgsetid,
						hgsets.values[i]->hgroupids.values[j]);
			}

			zbx_vector_uint64_append_array(&ids, hgsets.values[i]->hgroupids.values,
					hgsets.values[i]->hgroupids.values_num);
		}

		zbx_db_insert_execute(&db_insert);
		zbx_db_insert_clean(&db_insert);

		zbx_db_insert_execute(&db_insert_group);
		zbx_db_insert_clean(&db_insert_group);

		zbx_vector_uint64_sort(&ids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_vector_uint64_uniq(&ids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		zbx_vector_host_permission_create(&permissions);

		sql_offset = 0;
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				"select distinct u.ugsetid,r.id,r.permission from ugset_group u"
				" join rights r on u.usrgrpid=r.groupid"
				" where");
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "r.id", ids.values,
				ids.values_num);

		zbx_vector_uint64_clear(&ids);

		result = zbx_db_select("%s", sql);

		while (NULL != (row = zbx_db_fetch(result)))
		{
			zbx_uint64_t		hostgroupid;
			zbx_host_permission_t	prm;

			ZBX_STR2UINT64(prm.ugsetid, row[0]);
			ZBX_STR2UINT64(hostgroupid, row[1]);
			prm.permission = atoi(row[2]);

			for (i = 0; i < hgsets.values_num; i++)
			{
				int	j;

				hgset = hgsets.values[i];

				if (hgset->hgsetid < new_permission_hgsetid ||
						FAIL == zbx_vector_uint64_bsearch(&hgset->hgroupids, hostgroupid,
						ZBX_DEFAULT_UINT64_COMPARE_FUNC))
				{
					continue;
				}

				prm.hgsetid = hgset->hgsetid;

				if (FAIL != (j = zbx_vector_host_permission_search(&permissions, prm,
						host_permission_compare)))
				{
					int	*permission_old = &permissions.values[j].permission;

					if (PERM_DENY != *permission_old && (PERM_DENY == prm.permission ||
							*permission_old < prm.permission))
					{
						*permission_old = prm.permission;
					}
				}
				else
					zbx_vector_host_permission_append(&permissions, prm);
			}
		}
		zbx_db_free_result(result);

		zbx_db_insert_prepare(&db_insert, "permission", "ugsetid", "hgsetid", "permission", (char *)NULL);

		for (i = 0; i < permissions.values_num; i++)
		{
			zbx_host_permission_t	*permission = &permissions.values[i];

			if (PERM_DENY < permission->permission)
			{
				zbx_db_insert_add_values(&db_insert, permission->ugsetid, permission->hgsetid,
						permission->permission);
			}
		}

		zbx_db_insert_execute(&db_insert);
		zbx_db_insert_clean(&db_insert);

		zbx_vector_host_permission_destroy(&permissions);
	}

	/* update hosts to use new hgsets */

	sql_offset = 0;

	for (i = 0; i < hgsets.values_num; i ++)
	{
		char	update[MAX_STRING_LEN];

		hgset = hgsets.values[i];

		zbx_snprintf(update, MAX_STRING_LEN, "update host_hgset set hgsetid=" ZBX_FS_UI64 " where",
				hgset->hgsetid);

		zbx_vector_uint64_sort(&hgset->hostids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_vector_uint64_uniq(&hgset->hostids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		ret = zbx_db_prepare_multiple_query(update, "hostid", &hgset->hostids, &sql, &sql_alloc, &sql_offset);
	}

	if (SUCCEED == ret)
		(void)zbx_db_flush_overflowed_sql(sql, sql_offset);

	/* delete hgsets */

	sql_offset = 0;

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from hgset where");
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "hgsetid", hgsetids.values,
			hgsetids.values_num);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");

skip_permissions:
	/* delete sysmaps_elements */
	DBget_sysmapelements_by_element_type_ids(&ids, SYSMAP_ELEMENT_TYPE_HOST_GROUP, groupids);
	if (0 != ids.values_num)
	{
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from sysmaps_elements where");
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "selementid", ids.values,
				ids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");
	}

	/* delete groups */
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from hstgrp where");
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "groupid", groupids->values, groupids->values_num);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");

	zbx_db_execute("%s", sql);

	zbx_vector_uint64_destroy(&ids);
	zbx_vector_uint64_destroy(&hgsetids);
	zbx_vector_hgset_ptr_clear_ext(&hgsets, hgset_free);
	zbx_vector_hgset_ptr_destroy(&hgsets);

	zbx_free(sql);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: adds host inventory to host                                       *
 *                                                                            *
 * Parameters:                                                                *
 *             hostid             - [IN]                                      *
 *             inventory_mode     - [IN] host inventory mode                  *
 *             audit_context_mode - [IN]                                      *
 *                                                                            *
 ******************************************************************************/
void	zbx_db_add_host_inventory(zbx_uint64_t hostid, int inventory_mode, int audit_context_mode)
{
	zbx_db_insert_t	db_insert;

	zbx_db_insert_prepare(&db_insert, "host_inventory", "hostid", "inventory_mode", (char *)NULL);
	zbx_db_insert_add_values(&db_insert, hostid, inventory_mode);
	zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);
	zbx_audit_host_update_json_add_inventory_mode(audit_context_mode, hostid, inventory_mode);
}

/******************************************************************************
 *                                                                            *
 * Purpose: sets host inventory mode for specified host                       *
 *                                                                            *
 * Parameters:                                                                *
 *             hostid             - [IN]                                      *
 *             inventory_mode     - [IN] host inventory mode                  *
 *             audit_context_mode - [IN]                                      *
 *                                                                            *
 * Comments: The host_inventory table record is created if absent.            *
 *                                                                            *
 *           This function does not allow disabling host inventory - only     *
 *           setting manual or automatic host inventory mode is supported.    *
 *                                                                            *
 ******************************************************************************/
void	zbx_db_set_host_inventory(zbx_uint64_t hostid, int inventory_mode, int audit_context_mode)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	result = zbx_db_select("select inventory_mode from host_inventory where hostid=" ZBX_FS_UI64, hostid);

	if (NULL == (row = zbx_db_fetch(result)))
	{
		zbx_db_add_host_inventory(hostid, inventory_mode, audit_context_mode);
	}
	else if (inventory_mode != atoi(row[0]))
	{
		zbx_db_execute("update host_inventory set inventory_mode=%d where hostid=" ZBX_FS_UI64, inventory_mode,
				hostid);
		zbx_audit_host_update_json_update_inventory_mode(audit_context_mode, hostid, atoi(row[0]),
				inventory_mode);
	}

	zbx_db_free_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

void	zbx_hgset_hash_calculate(zbx_vector_uint64_t *groupids, char *hash_str, size_t hash_len)
{
	char		hash[ZBX_SHA256_DIGEST_SIZE], id[MAX_ID_LEN + 2], *id_ins, *id_p;
	sha256_ctx	ctx;

	id_p = id_ins = id + 1;
	zbx_sha256_init(&ctx);

	for (int i = 0; i < groupids->values_num; i++)
	{
		if (1 == i)
		{
			id_p = id;
			*id_p = '|';
		}

		zbx_snprintf(id_ins, MAX_ID_LEN + 1, ZBX_FS_UI64, groupids->values[i]);
		zbx_sha256_process_bytes(id_p, strlen(id_p), &ctx);
	}

	zbx_sha256_finish(&ctx, hash);
	(void)zbx_bin2hex((const unsigned char *)hash, sizeof(hash), hash_str, hash_len);
}
