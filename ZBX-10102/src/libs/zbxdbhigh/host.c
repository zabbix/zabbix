/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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

#include "db.h"
#include "log.h"
#include "dbcache.h"
#include "zbxserver.h"
#include "template.h"

static char	*get_template_names(const zbx_vector_uint64_t *templateids)
{
	DB_RESULT	result;
	DB_ROW		row;
	char		*sql = NULL, *template_names = NULL;
	size_t		sql_alloc = 256, sql_offset=0, tmp_alloc = 64, tmp_offset = 0;

	sql = zbx_malloc(sql, sql_alloc);
	template_names = zbx_malloc(template_names, tmp_alloc);

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select host"
			" from hosts"
			" where");

	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid",
			templateids->values, templateids->values_num);

	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
		zbx_snprintf_alloc(&template_names, &tmp_alloc, &tmp_offset, "\"%s\", ", row[0]);

	template_names[tmp_offset - 2] = '\0';

	DBfree_result(result);
	zbx_free(sql);

	return template_names;
}

/******************************************************************************
 *                                                                            *
 * Function: DBget_screenitems_by_resource_types_ids                          *
 *                                                                            *
 * Description: gets a vector of screen item identifiers used with the        *
 *              specified resource types and identifiers                      *
 *                                                                            *
 * Parameters: screen_itemids - [OUT] the screen item identifiers             *
 *             types          - [IN] an array of resource types               *
 *             types_num      - [IN] the number of values in types array      *
 *             resourceids    - [IN] the resource identifiers                 *
 *                                                                            *
 ******************************************************************************/
static void	DBget_screenitems_by_resource_types_ids(zbx_vector_uint64_t *screen_itemids, const zbx_uint64_t *types,
		int types_num, const zbx_vector_uint64_t *resourceids)
{
	char	*sql = NULL;
	size_t	sql_alloc = 0, sql_offset = 0;

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select distinct screenitemid from screens_items where");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "resourcetype", types, types_num);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " and");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "resourceid", resourceids->values,
			resourceids->values_num);

	DBselect_uint64(sql, screen_itemids);

	zbx_free(sql);

	zbx_vector_uint64_sort(screen_itemids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
}

/******************************************************************************
 *                                                                            *
 * Function: DBget_profiles_by_source_idxs_values                             *
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

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select distinct profileid from profiles where");

	if (NULL != source)
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " source='%s' and", source);

	if (0 != idxs_num)
	{
		DBadd_str_condition_alloc(&sql, &sql_alloc, &sql_offset, "idx", idxs, idxs_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " and");
	}

	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "value_id", value_ids->values, value_ids->values_num);

	DBselect_uint64(sql, profileids);

	zbx_free(sql);

	zbx_vector_uint64_sort(profileids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
}

/******************************************************************************
 *                                                                            *
 * Function: DBget_sysmapelements_by_element_type_ids                         *
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
		zbx_vector_uint64_t *elementids)
{
	char	*sql = NULL;
	size_t	sql_alloc = 0, sql_offset = 0;

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select distinct selementid"
			" from sysmaps_elements"
			" where elementtype=%d"
				" and",
			elementtype);

	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "elementid", elementids->values, elementids->values_num);
	DBselect_uint64(sql, selementids);

	zbx_free(sql);

	zbx_vector_uint64_sort(selementids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
}

/******************************************************************************
 *                                                                            *
 * Function: validate_linked_templates                                        *
 *                                                                            *
 * Description: Check collisions between linked templates                     *
 *                                                                            *
 * Parameters: templateids - [IN] array of template IDs                       *
 *                                                                            *
 * Return value: SUCCEED if no collisions found                               *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments: !!! Don't forget to sync the code with PHP !!!                   *
 *                                                                            *
 ******************************************************************************/
static int	validate_linked_templates(const zbx_vector_uint64_t *templateids, char *error, size_t max_error_len)
{
	const char	*__function_name = "validate_linked_templates";

	DB_RESULT	result;
	DB_ROW		row;
	char		*sql = NULL;
	size_t		sql_alloc = 256, sql_offset;
	int		ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (0 == templateids->values_num)
		goto out;

	sql = zbx_malloc(sql, sql_alloc);

	/* items */
	if (SUCCEED == ret && 1 < templateids->values_num)
	{
		sql_offset = 0;
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				"select key_,count(*)"
				" from items"
				" where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid",
				templateids->values, templateids->values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				" group by key_"
				" having count(*)>1");

		result = DBselectN(sql, 1);

		if (NULL != (row = DBfetch(result)))
		{
			ret = FAIL;
			zbx_snprintf(error, max_error_len, "conflicting item key \"%s\" found", row[0]);
		}
		DBfree_result(result);
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
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "i1.hostid",
				templateids->values, templateids->values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " and not");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "i2.hostid",
				templateids->values, templateids->values_num);

		result = DBselectN(sql, 1);

		if (NULL != (row = DBfetch(result)))
		{
			ret = FAIL;
			zbx_snprintf(error, max_error_len,
					"trigger \"%s\" has items from template \"%s\"",
					row[0], row[1]);
		}
		DBfree_result(result);
	}

	/* trigger dependencies */
	if (SUCCEED == ret)
	{
		sql_offset = 0;
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				/* don't remove "description2 and host2" aliases, the ORACLE needs them */
				"select t1.description,h1.host,t2.description as description2,h2.host as host2"
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
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "i1.hostid",
				templateids->values, templateids->values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " and not");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "i2.hostid",
				templateids->values, templateids->values_num);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " and h2.status=%d", HOST_STATUS_TEMPLATE);

		result = DBselectN(sql, 1);

		if (NULL != (row = DBfetch(result)))
		{
			ret = FAIL;
			zbx_snprintf(error, max_error_len,
					"trigger \"%s\" in template \"%s\""
					" has dependency from trigger \"%s\" in template \"%s\"",
					row[0], row[1], row[2], row[3]);
		}
		DBfree_result(result);
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
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "i.hostid",
				templateids->values, templateids->values_num);

		DBselect_uint64(sql, &graphids);

		/* check for names */
		if (0 != graphids.values_num)
		{
			sql_offset = 0;
			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
					"select name,count(*)"
					" from graphs"
					" where");
			DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "graphid",
					graphids.values, graphids.values_num);
			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
					" group by name"
					" having count(*)>1");

			result = DBselect("%s", sql);

			if (NULL != (row = DBfetch(result)))
			{
				ret = FAIL;
				zbx_snprintf(error, max_error_len,
						"template with graph \"%s\" already linked to the host", row[0]);
			}
			DBfree_result(result);
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
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid",
				templateids->values, templateids->values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				" group by name"
				" having count(*)>1");

		result = DBselectN(sql, 1);

		if (NULL != (row = DBfetch(result)))
		{
			ret = FAIL;
			zbx_snprintf(error, max_error_len,
					"template with web scenario \"%s\" already linked to the host", row[0]);
		}
		DBfree_result(result);
	}

	zbx_free(sql);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: DBcmp_triggers                                                   *
 *                                                                            *
 * Purpose: compare two triggers                                              *
 *                                                                            *
 * Parameters: triggerid1 - first trigger identificator from database         *
 *             triggerid2 - second trigger identificator from database        *
 *                                                                            *
 * Return value: SUCCEED - if triggers coincide                               *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget to sync the code with PHP !!!                   *
 *                                                                            *
 ******************************************************************************/
static int	DBcmp_triggers(zbx_uint64_t triggerid1, const char *expression1,
		zbx_uint64_t triggerid2, const char *expression2)
{
	DB_RESULT	result;
	DB_ROW		row;
	char		*search = NULL,
			*replace = NULL,
			*old_expr = NULL,
			*expr = NULL;
	int		res = SUCCEED;

	expr = strdup(expression2);

	result = DBselect(
			"select f1.functionid,f2.functionid"
			" from functions f1,functions f2,items i1,items i2"
			" where f1.function=f2.function"
				" and f1.parameter=f2.parameter"
				" and i1.key_=i2.key_"
				" and i1.itemid=f1.itemid"
				" and i2.itemid=f2.itemid"
				" and f1.triggerid=" ZBX_FS_UI64
				" and f2.triggerid=" ZBX_FS_UI64,
				triggerid1, triggerid2);

	while (NULL != (row = DBfetch(result)))
	{
		search = zbx_dsprintf(NULL, "{%s}", row[1]);
		replace = zbx_dsprintf(NULL, "{%s}", row[0]);

		old_expr = expr;
		expr = string_replace(old_expr, search, replace);
		zbx_free(old_expr);

		zbx_free(replace);
		zbx_free(search);
	}
	DBfree_result(result);

	if (0 != strcmp(expression1, expr))
		res = FAIL;

	zbx_free(expr);

	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: validate_inventory_links                                         *
 *                                                                            *
 * Description: Check collisions in item inventory links                      *
 *                                                                            *
 * Parameters: hostid      - [IN] host identificator from database            *
 *             templateids - [IN] array of template IDs                       *
 *                                                                            *
 * Return value: SUCCEED if no collisions found                               *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments: !!! Don't forget to sync the code with PHP !!!                   *
 *                                                                            *
 ******************************************************************************/
static int	validate_inventory_links(zbx_uint64_t hostid, const zbx_vector_uint64_t *templateids,
		char *error, size_t max_error_len)
{
	const char	*__function_name = "validate_inventory_links";
	DB_RESULT	result;
	DB_ROW		row;
	char		*sql = NULL;
	size_t		sql_alloc = 512, sql_offset;
	int		ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	sql = zbx_malloc(sql, sql_alloc);

	sql_offset = 0;
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select inventory_link,count(*)"
			" from items"
			" where inventory_link<>0"
				" and");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid",
			templateids->values, templateids->values_num);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			" group by inventory_link"
			" having count(*)>1");

	result = DBselectN(sql, 1);

	if (NULL != (row = DBfetch(result)))
	{
		ret = FAIL;
		zbx_strlcpy(error, "two items cannot populate one host inventory field", max_error_len);
	}
	DBfree_result(result);

	if (FAIL == ret)
		goto out;

	sql_offset = 0;
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select ti.itemid"
			" from items ti,items i"
			" where ti.key_<>i.key_"
				" and ti.inventory_link=i.inventory_link"
				" and");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "ti.hostid",
			templateids->values, templateids->values_num);
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				" and i.hostid=" ZBX_FS_UI64
				" and ti.inventory_link<>0"
				" and not exists ("
					"select *"
					" from items",
				hostid);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " where");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "items.hostid",
			templateids->values, templateids->values_num);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
						" and items.key_=i.key_"
					")");

	result = DBselectN(sql, 1);

	if (NULL != (row = DBfetch(result)))
	{
		ret = FAIL;
		zbx_strlcpy(error, "two items cannot populate one host inventory field", max_error_len);
	}
	DBfree_result(result);
out:
	zbx_free(sql);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: validate_httptests                                               *
 *                                                                            *
 * Description: checking collisions on linking of web scenarios               *
 *                                                                            *
 * Parameters: hostid      - [IN] host identificator from database            *
 *             templateids - [IN] array of template IDs                       *
 *                                                                            *
 * Return value: SUCCEED if no collisions found                               *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments: !!! Don't forget to sync the code with PHP !!!                   *
 *                                                                            *
 ******************************************************************************/
static int	validate_httptests(zbx_uint64_t hostid, const zbx_vector_uint64_t *templateids,
		char *error, size_t max_error_len)
{
	const char	*__function_name = "validate_httptests";
	DB_RESULT	tresult;
	DB_RESULT	sresult;
	DB_ROW		trow;
	char		*sql = NULL;
	size_t		sql_alloc = 512, sql_offset = 0;
	int		ret = SUCCEED;
	zbx_uint64_t	t_httptestid, h_httptestid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	sql = zbx_malloc(sql, sql_alloc);

	/* selects web scenarios from templates and host with identical names */
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select t.httptestid,t.name,h.httptestid"
			" from httptest t"
				" inner join httptest h"
					" on h.name=t.name"
						" and h.hostid=" ZBX_FS_UI64
			" where", hostid);
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "t.hostid", templateids->values, templateids->values_num);

	tresult = DBselect("%s", sql);

	while (NULL != (trow = DBfetch(tresult)))
	{
		ZBX_STR2UINT64(t_httptestid, trow[0]);
		ZBX_STR2UINT64(h_httptestid, trow[2]);

		sql_offset = 0;
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				/* don't remove "h_httpstepid" alias, the ORACLE needs it */
				"select t.httpstepid,h.httpstepid as h_httpstepid"
				" from httpstep t"
					" left join httpstep h"
						" on h.httptestid=" ZBX_FS_UI64
							" and h.no=t.no"
							" and h.name=t.name"
				" where t.httptestid=" ZBX_FS_UI64
					" and h.httpstepid is null"
				" union "
				"select t.httpstepid,h.httpstepid as h_httpstepid"
				" from httpstep h"
					" left outer join httpstep t"
						" on t.httptestid=" ZBX_FS_UI64
							" and t.no=h.no"
							" and t.name=h.name"
				" where h.httptestid=" ZBX_FS_UI64
					" and t.httpstepid is null",
				h_httptestid, t_httptestid, t_httptestid, h_httptestid);

		sresult = DBselectN(sql, 1);

		if (NULL != DBfetch(sresult))
		{
			ret = FAIL;
			zbx_snprintf(error, max_error_len,
					"web scenario \"%s\" already exists on the host (steps are not identical)",
					trow[1]);
		}
		DBfree_result(sresult);

		if (SUCCEED != ret)
			break;
	}
	DBfree_result(tresult);

	zbx_free(sql);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

static void	DBget_graphitems(const char *sql, ZBX_GRAPH_ITEMS **gitems, size_t *gitems_alloc, size_t *gitems_num)
{
	const char	*__function_name = "DBget_graphitems";
	DB_RESULT	result;
	DB_ROW		row;
	ZBX_GRAPH_ITEMS	*gitem;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	*gitems_num = 0;

	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		if (*gitems_alloc == *gitems_num)
		{
			*gitems_alloc += 16;
			*gitems = zbx_realloc(*gitems, *gitems_alloc * sizeof(ZBX_GRAPH_ITEMS));
		}

		gitem = &(*gitems)[*gitems_num];

		ZBX_STR2UINT64(gitem->gitemid, row[0]);
		ZBX_STR2UINT64(gitem->itemid, row[1]);
		zbx_strlcpy(gitem->key, row[2], sizeof(gitem->key));
		gitem->drawtype = atoi(row[3]);
		gitem->sortorder = atoi(row[4]);
		zbx_strlcpy(gitem->color, row[5], sizeof(gitem->color));
		gitem->yaxisside = atoi(row[6]);
		gitem->calc_fnc = atoi(row[7]);
		gitem->type = atoi(row[8]);
		gitem->flags = (unsigned char)atoi(row[9]);

		zabbix_log(LOG_LEVEL_DEBUG, "%s() [%d] itemid:" ZBX_FS_UI64 " key:'%s'",
				__function_name, *gitems_num, gitem->itemid, gitem->key);

		(*gitems_num)++;
	}
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: DBcmp_graphitems                                                 *
 *                                                                            *
 * Purpose: Compare graph items from two graphs                               *
 *                                                                            *
 * Parameters: gitems1     - [IN] first graph items, sorted by itemid         *
 *             gitems1_num - [IN] number of first graph items                 *
 *             gitems2     - [IN] second graph items, sorted by itemid        *
 *             gitems2_num - [IN] number of second graph items                *
 *                                                                            *
 * Return value: SUCCEED if graph items coincide                              *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments: !!! Don't forget to sync the code with PHP !!!                   *
 *                                                                            *
 ******************************************************************************/
static int	DBcmp_graphitems(ZBX_GRAPH_ITEMS *gitems1, int gitems1_num,
		ZBX_GRAPH_ITEMS *gitems2, int gitems2_num)
{
	const char	*__function_name = "DBcmp_graphitems";
	int		res = FAIL, i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (gitems1_num != gitems2_num)
		goto clean;

	for (i = 0; i < gitems1_num; i++)
		if (0 != strcmp(gitems1[i].key, gitems2[i].key))
			goto clean;

	res = SUCCEED;
clean:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(res));

	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: validate_host                                                    *
 *                                                                            *
 * Description: Check collisions between host and linked template             *
 *                                                                            *
 * Parameters: hostid      - [IN] host identificator from database            *
 *             templateids - [IN] array of template IDs                       *
 *                                                                            *
 * Return value: SUCCEED if no collisions found                               *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments: !!! Don't forget to sync the code with PHP !!!                   *
 *                                                                            *
 ******************************************************************************/
static int	validate_host(zbx_uint64_t hostid, zbx_vector_uint64_t *templateids,
		char *error, size_t max_error_len)
{
	const char	*__function_name = "validate_host";
	DB_RESULT	tresult;
	DB_RESULT	hresult;
	DB_ROW		trow;
	DB_ROW		hrow;
	char		*sql = NULL, *name_esc;
	size_t		sql_alloc = 256, sql_offset;
	ZBX_GRAPH_ITEMS *gitems = NULL, *chd_gitems = NULL;
	size_t		gitems_alloc = 0, gitems_num = 0,
			chd_gitems_alloc = 0, chd_gitems_num = 0;
	int		ret = SUCCEED, i;
	zbx_uint64_t	graphid, interfaceids[INTERFACE_TYPE_COUNT];
	unsigned char	t_flags, h_flags, type;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (SUCCEED != (ret = validate_inventory_links(hostid, templateids, error, max_error_len)))
		goto out;

	if (SUCCEED != (ret = validate_httptests(hostid, templateids, error, max_error_len)))
		goto out;

	sql = zbx_malloc(sql, sql_alloc);

	sql_offset = 0;
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select distinct g.graphid,g.name,g.flags"
			" from graphs g,graphs_items gi,items i"
			" where g.graphid=gi.graphid"
				" and gi.itemid=i.itemid"
				" and");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "i.hostid", templateids->values, templateids->values_num);

	tresult = DBselect("%s", sql);

	while (SUCCEED == ret && NULL != (trow = DBfetch(tresult)))
	{
		ZBX_STR2UINT64(graphid, trow[0]);
		t_flags = (unsigned char)atoi(trow[2]);

		sql_offset = 0;
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"select 0,0,i.key_,gi.drawtype,gi.sortorder,gi.color,gi.yaxisside,gi.calc_fnc,"
					"gi.type,i.flags"
				" from graphs_items gi,items i"
				" where gi.itemid=i.itemid"
					" and gi.graphid=" ZBX_FS_UI64
				" order by i.key_",
				graphid);

		DBget_graphitems(sql, &gitems, &gitems_alloc, &gitems_num);

		name_esc = DBdyn_escape_string(trow[1]);

		hresult = DBselect(
				"select distinct g.graphid,g.flags"
				" from graphs g,graphs_items gi,items i"
				" where g.graphid=gi.graphid"
					" and gi.itemid=i.itemid"
					" and i.hostid=" ZBX_FS_UI64
					" and g.name='%s'"
					" and g.templateid is null",
				hostid, name_esc);

		zbx_free(name_esc);

		/* compare graphs */
		while (NULL != (hrow = DBfetch(hresult)))
		{
			ZBX_STR2UINT64(graphid, hrow[0]);
			h_flags = (unsigned char)atoi(hrow[1]);

			if (t_flags != h_flags)
			{
				ret = FAIL;
				zbx_snprintf(error, max_error_len,
						"graph prototype and real graph \"%s\" have the same name", trow[1]);
				break;
			}

			sql_offset = 0;
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
					"select gi.gitemid,i.itemid,i.key_,gi.drawtype,gi.sortorder,gi.color,"
						"gi.yaxisside,gi.calc_fnc,gi.type,i.flags"
					" from graphs_items gi,items i"
					" where gi.itemid=i.itemid"
						" and gi.graphid=" ZBX_FS_UI64
					" order by i.key_",
					graphid);

			DBget_graphitems(sql, &chd_gitems, &chd_gitems_alloc, &chd_gitems_num);

			if (SUCCEED != DBcmp_graphitems(gitems, gitems_num, chd_gitems, chd_gitems_num))
			{
				ret = FAIL;
				zbx_snprintf(error, max_error_len,
						"graph \"%s\" already exists on the host (items are not identical)",
						trow[1]);
				break;
			}
		}
		DBfree_result(hresult);
	}
	DBfree_result(tresult);

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
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "t.hostid",
				templateids->values, templateids->values_num);

		tresult = DBselectN(sql, 1);

		if (NULL != (trow = DBfetch(tresult)))
		{
			ret = FAIL;
			zbx_snprintf(error, max_error_len,
					"item prototype and real item \"%s\" have the same key", trow[0]);
		}
		DBfree_result(tresult);
	}

	/* interfaces */
	if (SUCCEED == ret)
	{
		memset(&interfaceids, 0, sizeof(interfaceids));

		tresult = DBselect(
				"select type,interfaceid"
				" from interface"
				" where hostid=" ZBX_FS_UI64
					" and type in (%d,%d,%d,%d)"
					" and main=1",
				hostid, INTERFACE_TYPE_AGENT, INTERFACE_TYPE_SNMP,
				INTERFACE_TYPE_IPMI, INTERFACE_TYPE_JMX);

		while (NULL != (trow = DBfetch(tresult)))
		{
			type = (unsigned char)atoi(trow[0]);
			ZBX_STR2UINT64(interfaceids[type - 1], trow[1]);
		}
		DBfree_result(tresult);

		sql_offset = 0;
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"select distinct type"
				" from items"
				" where type not in (%d,%d,%d,%d,%d,%d,%d)"
					" and",
				ITEM_TYPE_TRAPPER, ITEM_TYPE_INTERNAL, ITEM_TYPE_ZABBIX_ACTIVE, ITEM_TYPE_AGGREGATE,
				ITEM_TYPE_HTTPTEST, ITEM_TYPE_DB_MONITOR, ITEM_TYPE_CALCULATED);
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid",
				templateids->values, templateids->values_num);

		tresult = DBselect("%s", sql);

		while (SUCCEED == ret && NULL != (trow = DBfetch(tresult)))
		{
			type = (unsigned char)atoi(trow[0]);
			type = get_interface_type_by_item_type(type);

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
		DBfree_result(tresult);
	}

	zbx_free(sql);
	zbx_free(gitems);
	zbx_free(chd_gitems);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "In %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: DBdelete_action_conditions                                       *
 *                                                                            *
 * Purpose: delete action conditions by condition type and id                 *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
static void	DBdelete_action_conditions(int conditiontype, zbx_uint64_t elementid)
{
	DB_RESULT		result;
	DB_ROW			row;
	zbx_uint64_t		id;
	zbx_vector_uint64_t	actionids, conditionids;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;

	zbx_vector_uint64_create(&actionids);
	zbx_vector_uint64_create(&conditionids);

	/* disable actions */
	result = DBselect("select actionid,conditionid from conditions where conditiontype=%d and"
			" value='" ZBX_FS_UI64 "'", conditiontype, elementid);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(id, row[0]);
		zbx_vector_uint64_append(&actionids, id);

		ZBX_STR2UINT64(id, row[1]);
		zbx_vector_uint64_append(&conditionids, id);
	}

	DBfree_result(result);

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (0 != actionids.values_num)
	{
		zbx_vector_uint64_sort(&actionids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_vector_uint64_uniq(&actionids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update actions set status=%d where",
				ACTION_STATUS_DISABLED);
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "actionid", actionids.values,
				actionids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");
	}

	if (0 != conditionids.values_num)
	{
		zbx_vector_uint64_sort(&conditionids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from conditions where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "conditionid", conditionids.values,
				conditionids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");
	}

	DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	/* in ORACLE always present begin..end; */
	if (16 < sql_offset)
		DBexecute("%s", sql);

	zbx_free(sql);

	zbx_vector_uint64_destroy(&conditionids);
	zbx_vector_uint64_destroy(&actionids);
}

/******************************************************************************
 *                                                                            *
 * Function: DBdelete_triggers                                                *
 *                                                                            *
 * Purpose: delete trigger from database                                      *
 *                                                                            *
 * Parameters: triggerids - [IN] trigger identificators from database         *
 *                                                                            *
 ******************************************************************************/
static void	DBdelete_triggers(zbx_vector_uint64_t *triggerids)
{
	char			*sql = NULL;
	size_t			sql_alloc = 256, sql_offset;
	int			i;
	zbx_vector_uint64_t	profileids, selementids;
	const char		*profile_idx = "web.events.filter.triggerid";

	if (0 == triggerids->values_num)
		return;

	sql = zbx_malloc(sql, sql_alloc);

	zbx_vector_uint64_create(&profileids);
	zbx_vector_uint64_create(&selementids);

	DBremove_triggers_from_itservices(triggerids->values, triggerids->values_num);

	sql_offset = 0;
	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	DBget_sysmapelements_by_element_type_ids(&selementids, SYSMAP_ELEMENT_TYPE_TRIGGER, triggerids);
	if (0 != selementids.values_num)
	{
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from sysmaps_elements where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "selementid", selementids.values,
				selementids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");
	}

	for (i = 0; i < triggerids->values_num; i++)
		DBdelete_action_conditions(CONDITION_TYPE_TRIGGER, triggerids->values[i]);

	DBget_profiles_by_source_idxs_values(&profileids, NULL, &profile_idx, 1, triggerids);
	if (0 != profileids.values_num)
	{
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from profiles where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "profileid", profileids.values,
				profileids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");
	}

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"delete from triggers"
			" where");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "triggerid", triggerids->values, triggerids->values_num);
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, ";\n");

	DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	DBexecute("%s", sql);

	zbx_vector_uint64_destroy(&selementids);
	zbx_vector_uint64_destroy(&profileids);

	zbx_free(sql);
}

/******************************************************************************
 *                                                                            *
 * Function: DBdelete_trigger_hierarchy                                       *
 *                                                                            *
 * Purpose: delete parent triggers and auto-created childs from database      *
 *                                                                            *
 * Parameters: triggerids - [IN] trigger identificators from database         *
 *                                                                            *
 ******************************************************************************/
static void	DBdelete_trigger_hierarchy(zbx_vector_uint64_t *triggerids)
{
	char			*sql = NULL;
	size_t			sql_alloc = 256, sql_offset = 0;
	zbx_vector_uint64_t	children_triggerids;

	if (0 == triggerids->values_num)
		return;

	sql = zbx_malloc(sql, sql_alloc);

	zbx_vector_uint64_create(&children_triggerids);

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select distinct triggerid from trigger_discovery where");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "parent_triggerid", triggerids->values,
			triggerids->values_num);

	DBselect_uint64(sql, &children_triggerids);
	zbx_vector_uint64_setdiff(triggerids, &children_triggerids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	DBdelete_triggers(&children_triggerids);
	DBdelete_triggers(triggerids);

	zbx_vector_uint64_destroy(&children_triggerids);

	zbx_free(sql);
}

/******************************************************************************
 *                                                                            *
 * Function: DBdelete_triggers_by_itemids                                     *
 *                                                                            *
 * Purpose: delete triggers by itemid                                         *
 *                                                                            *
 * Parameters: itemids - [IN] item identificators from database               *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget to sync the code with PHP !!!                   *
 *                                                                            *
 ******************************************************************************/
static void	DBdelete_triggers_by_itemids(zbx_vector_uint64_t *itemids)
{
	const char		*__function_name = "DBdelete_triggers_by_itemids";

	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_vector_uint64_t	triggerids;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() values_num:%d", __function_name, itemids->values_num);

	if (0 == itemids->values_num)
		goto out;

	zbx_vector_uint64_create(&triggerids);

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select distinct triggerid from functions where");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "itemid", itemids->values, itemids->values_num);

	DBselect_uint64(sql, &triggerids);

	DBdelete_trigger_hierarchy(&triggerids);

	zbx_vector_uint64_destroy(&triggerids);
	zbx_free(sql);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: DBdelete_history_by_itemids                                      *
 *                                                                            *
 * Purpose: delete item history                                               *
 *                                                                            *
 * Parameters: itemids - [IN] item identificators from database               *
 *                                                                            *
 * Author: Eugene Grigorjev, Alexander Vladishev                              *
 *                                                                            *
 * Comments: !!! Don't forget to sync the code with PHP !!!                   *
 *                                                                            *
 ******************************************************************************/
static void	DBdelete_history_by_itemids(zbx_vector_uint64_t *itemids)
{
	const char	*__function_name = "DBdelete_history_by_itemids";
	int		i, j;
	zbx_uint64_t	housekeeperid;
	zbx_db_insert_t	db_insert;

#define        ZBX_HISTORY_TABLES_COUNT        7
	const char	*tables[ZBX_HISTORY_TABLES_COUNT] = {"history", "history_str", "history_uint", "history_log",
			"history_text", "trends", "trends_uint"};

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() values_num:%d", __function_name, itemids->values_num);

	if (0 == itemids->values_num)
		goto out;

	housekeeperid = DBget_maxid_num("housekeeper", ZBX_HISTORY_TABLES_COUNT * itemids->values_num);

	zbx_db_insert_prepare(&db_insert, "housekeeper", "housekeeperid", "tablename", "field", "value", NULL);

	for (i = 0; i < itemids->values_num; i++)
	{
		for (j = 0; j < ZBX_HISTORY_TABLES_COUNT; j++)
			zbx_db_insert_add_values(&db_insert, housekeeperid++, tables[j], "itemid", itemids->values[i]);
	}

	zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: DBdelete_graphs                                                  *
 *                                                                            *
 * Purpose: delete graph from database                                        *
 *                                                                            *
 * Parameters: graphids - [IN] array of graph id's from database              *
 *                                                                            *
 ******************************************************************************/
void	DBdelete_graphs(zbx_vector_uint64_t *graphids)
{
	const char		*__function_name = "DBdelete_graphs";

	char			*sql = NULL;
	size_t			sql_alloc = 256, sql_offset = 0;
	zbx_vector_uint64_t	profileids, screen_itemids;
	zbx_uint64_t		resource_type = SCREEN_RESOURCE_GRAPH;
	const char		*profile_idx =  "web.favorite.graphids";

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() values_num:%d", __function_name, graphids->values_num);

	if (0 == graphids->values_num)
		goto out;

	sql = zbx_malloc(sql, sql_alloc);

	zbx_vector_uint64_create(&profileids);
	zbx_vector_uint64_create(&screen_itemids);

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	/* delete from screens_items */
	DBget_screenitems_by_resource_types_ids(&screen_itemids, &resource_type, 1, graphids);
	if (0 != screen_itemids.values_num)
	{
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from screens_items where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "screenitemid", screen_itemids.values,
				screen_itemids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");
	}

	/* delete from profiles */
	DBget_profiles_by_source_idxs_values(&profileids, "graphid", &profile_idx, 1, graphids);
	if (0 != profileids.values_num)
	{
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from profiles where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "profileid", profileids.values,
				profileids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");
	}

	/* delete from graphs */
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from graphs where");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "graphid", graphids->values, graphids->values_num);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");

	DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	DBexecute("%s", sql);

	zbx_vector_uint64_destroy(&screen_itemids);
	zbx_vector_uint64_destroy(&profileids);

	zbx_free(sql);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: DBdelete_graph_hierarchy                                         *
 *                                                                            *
 * Purpose: delete parent graphs and auto-created childs from database        *
 *                                                                            *
 * Parameters: graphids - [IN] array of graph id's from database              *
 *                                                                            *
 ******************************************************************************/
static void	DBdelete_graph_hierarchy(zbx_vector_uint64_t *graphids)
{
	char			*sql = NULL;
	size_t			sql_alloc = 256, sql_offset = 0;
	zbx_vector_uint64_t	children_graphids;

	if (0 == graphids->values_num)
		return;

	sql = zbx_malloc(sql, sql_alloc);

	zbx_vector_uint64_create(&children_graphids);

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select distinct graphid from graph_discovery where");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "parent_graphid", graphids->values,
			graphids->values_num);

	DBselect_uint64(sql, &children_graphids);
	zbx_vector_uint64_setdiff(graphids, &children_graphids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	DBdelete_graphs(&children_graphids);
	DBdelete_graphs(graphids);

	zbx_vector_uint64_destroy(&children_graphids);

	zbx_free(sql);
}

/******************************************************************************
 *                                                                            *
 * Function: DBdelete_graphs_by_itemids                                       *
 *                                                                            *
 * Parameters: itemids - [IN] item identificators from database               *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
static void	DBdelete_graphs_by_itemids(zbx_vector_uint64_t *itemids)
{
	const char		*__function_name = "DBdelete_graphs_by_itemids";
	char			*sql = NULL;
	size_t			sql_alloc = 256, sql_offset;
	DB_RESULT		result;
	DB_ROW			row;
	zbx_uint64_t		graphid;
	zbx_vector_uint64_t	graphids;
	int			index;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() values_num:%d", __function_name, itemids->values_num);

	if (0 == itemids->values_num)
		goto out;

	sql = zbx_malloc(sql, sql_alloc);
	zbx_vector_uint64_create(&graphids);

	/* select all graphs with items */
	sql_offset = 0;
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "select distinct graphid from graphs_items where");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "itemid", itemids->values, itemids->values_num);

	DBselect_uint64(sql, &graphids);

	if (0 == graphids.values_num)
		goto clean;

	/* select graphs with other items */
	sql_offset = 0;
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select distinct graphid"
			" from graphs_items"
			" where");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "graphid", graphids.values, graphids.values_num);
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " and not");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "itemid", itemids->values, itemids->values_num);
	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(graphid, row[0]);
		if (FAIL != (index = zbx_vector_uint64_bsearch(&graphids, graphid, ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
			zbx_vector_uint64_remove(&graphids, index);
	}
	DBfree_result(result);

	DBdelete_graph_hierarchy(&graphids);
clean:
	zbx_vector_uint64_destroy(&graphids);
	zbx_free(sql);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: DBdelete_items                                                   *
 *                                                                            *
 * Purpose: delete items from database                                        *
 *                                                                            *
 * Parameters: itemids - [IN] array of item identificators from database      *
 *                                                                            *
 ******************************************************************************/
void	DBdelete_items(zbx_vector_uint64_t *itemids)
{
	const char		*__function_name = "DBdelete_items";

	char			*sql = NULL;
	size_t			sql_alloc = 256, sql_offset;
	zbx_vector_uint64_t	screen_itemids, profileids;
	int			num;
	zbx_uint64_t		resource_types[] = {SCREEN_RESOURCE_PLAIN_TEXT, SCREEN_RESOURCE_SIMPLE_GRAPH};
	const char		*profile_idx = "web.favorite.graphids";

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() values_num:%d", __function_name, itemids->values_num);

	if (0 == itemids->values_num)
		goto out;

	sql = zbx_malloc(sql, sql_alloc);
	zbx_vector_uint64_create(&screen_itemids);
	zbx_vector_uint64_create(&profileids);

	do	/* add child items (auto-created and prototypes) */
	{
		num = itemids->values_num;
		sql_offset = 0;
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select distinct itemid from item_discovery where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "parent_itemid",
				itemids->values, itemids->values_num);

		DBselect_uint64(sql, itemids);
		zbx_vector_uint64_uniq(itemids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	}
	while (num != itemids->values_num);

	DBdelete_graphs_by_itemids(itemids);
	DBdelete_triggers_by_itemids(itemids);
	DBdelete_history_by_itemids(itemids);

	sql_offset = 0;
	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	/* delete from screens_items */
	DBget_screenitems_by_resource_types_ids(&screen_itemids, resource_types, ARRSIZE(resource_types), itemids);
	if (0 != screen_itemids.values_num)
	{
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from screens_items where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "screenitemid", screen_itemids.values,
				screen_itemids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");
	}

	/* delete from profiles */
	DBget_profiles_by_source_idxs_values(&profileids, "itemid", &profile_idx, 1, itemids);
	if (0 != profileids.values_num)
	{
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from profiles where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "profileid", profileids.values,
				profileids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");
	}

	/* delete from items */
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from items where");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "itemid", itemids->values, itemids->values_num);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");

	DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	DBexecute("%s", sql);

	zbx_vector_uint64_destroy(&profileids);
	zbx_vector_uint64_destroy(&screen_itemids);

	zbx_free(sql);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: DBdelete_httptests                                               *
 *                                                                            *
 * Purpose: delete web tests from database                                    *
 *                                                                            *
 * Parameters: httptestids - [IN] array of httptest id's from database        *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments: !!! Don't forget to sync the code with PHP !!!                   *
 *                                                                            *
 ******************************************************************************/
static void	DBdelete_httptests(zbx_vector_uint64_t *httptestids)
{
	const char		*__function_name = "DBdelete_httptests";

	char			*sql = NULL;
	size_t			sql_alloc = 256, sql_offset = 0;
	zbx_vector_uint64_t	itemids;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() values_num:%d", __function_name, httptestids->values_num);

	if (0 == httptestids->values_num)
		goto out;

	sql = zbx_malloc(sql, sql_alloc);
	zbx_vector_uint64_create(&itemids);

	/* httpstepitem, httptestitem */
	sql_offset = 0;
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select hsi.itemid"
			" from httpstepitem hsi,httpstep hs"
			" where hsi.httpstepid=hs.httpstepid"
				" and");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "hs.httptestid",
			httptestids->values, httptestids->values_num);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			" union all "
			"select itemid"
			" from httptestitem"
			" where");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "httptestid",
			httptestids->values, httptestids->values_num);

	DBselect_uint64(sql, &itemids);

	DBdelete_items(&itemids);

	sql_offset = 0;
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from httptest where");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "httptestid",
			httptestids->values, httptestids->values_num);
	DBexecute("%s", sql);

	zbx_vector_uint64_destroy(&itemids);
	zbx_free(sql);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: DBdelete_application                                             *
 *                                                                            *
 * Purpose: delete application                                                *
 *                                                                            *
 * Parameters: applicationid - [IN] application identificator from database   *
 *                                                                            *
 * Author: Eugene Grigorjev, Alexander Vladishev                              *
 *                                                                            *
 * Comments: !!! Don't forget to sync the code with PHP !!!                   *
 *                                                                            *
 ******************************************************************************/
static void	DBdelete_applications(zbx_vector_uint64_t *applicationids)
{
	DB_RESULT	result;
	DB_ROW		row;
	char		*sql = NULL;
	size_t		sql_alloc = 0, sql_offset = 0;
	zbx_uint64_t	applicationid;
	int		index;

	if (0 == applicationids->values_num)
		goto out;

	/* don't delete applications used in web scenarios */
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select distinct applicationid"
			" from httptest"
			" where");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "applicationid", applicationids->values,
			applicationids->values_num);

	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(applicationid, row[0]);

		index = zbx_vector_uint64_bsearch(applicationids, applicationid, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_vector_uint64_remove(applicationids, index);
	}
	DBfree_result(result);

	if (0 == applicationids->values_num)
		goto out;

	/* don't delete applications with items assigned to them */
	sql_offset = 0;
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select distinct applicationid"
			" from items_applications"
			" where");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "applicationid", applicationids->values,
			applicationids->values_num);

	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(applicationid, row[0]);

		index = zbx_vector_uint64_bsearch(applicationids, applicationid, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_vector_uint64_remove(applicationids, index);
	}
	DBfree_result(result);

	if (0 == applicationids->values_num)
		goto out;

	sql_offset = 0;
	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from applications where");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset,
			"applicationid", applicationids->values, applicationids->values_num);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");

	DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	DBexecute("%s", sql);
out:
	zbx_free(sql);
}

/******************************************************************************
 *                                                                            *
 * Function: DBgroup_prototypes_delete                                        *
 *                                                                            *
 * Parameters: del_group_prototypeids - [IN] list of group_prototypeids which *
 *                                      will be deleted                       *
 *                                                                            *
 ******************************************************************************/
static void	DBgroup_prototypes_delete(zbx_vector_uint64_t *del_group_prototypeids)
{
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset;
	zbx_vector_uint64_t	groupids;

	if (0 == del_group_prototypeids->values_num)
		return;

	zbx_vector_uint64_create(&groupids);

	sql_offset = 0;
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select groupid from group_discovery where");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "parent_group_prototypeid",
			del_group_prototypeids->values, del_group_prototypeids->values_num);

	DBselect_uint64(sql, &groupids);

	DBdelete_groups(&groupids);

	sql_offset = 0;
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from group_prototype where");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "group_prototypeid",
			del_group_prototypeids->values, del_group_prototypeids->values_num);

	DBexecute("%s", sql);

	zbx_vector_uint64_destroy(&groupids);
	zbx_free(sql);
}

/******************************************************************************
 *                                                                            *
 * Function: DBdelete_host_prototypes                                         *
 *                                                                            *
 * Purpose: deletes host prototypes from database                             *
 *                                                                            *
 * Parameters: host_prototypeids - [IN] list of host prototypes               *
 *                                                                            *
 ******************************************************************************/
static void	DBdelete_host_prototypes(zbx_vector_uint64_t *host_prototypeids)
{
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset;
	zbx_vector_uint64_t	hostids, group_prototypeids;

	if (0 == host_prototypeids->values_num)
		return;

	/* delete discovered hosts */

	zbx_vector_uint64_create(&hostids);
	zbx_vector_uint64_create(&group_prototypeids);

	sql_offset = 0;
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select hostid from host_discovery where");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "parent_hostid",
			host_prototypeids->values, host_prototypeids->values_num);

	DBselect_uint64(sql, &hostids);

	if (0 != hostids.values_num)
		DBdelete_hosts(&hostids);

	/* delete group prototypes */

	sql_offset = 0;
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select group_prototypeid from group_prototype where");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid",
			host_prototypeids->values, host_prototypeids->values_num);

	DBselect_uint64(sql, &group_prototypeids);

	DBgroup_prototypes_delete(&group_prototypeids);

	/* delete host prototypes */

	sql_offset = 0;
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from hosts where");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid",
			host_prototypeids->values, host_prototypeids->values_num);

	DBexecute("%s", sql);

	zbx_vector_uint64_destroy(&group_prototypeids);
	zbx_vector_uint64_destroy(&hostids);
	zbx_free(sql);
}

/******************************************************************************
 *                                                                            *
 * Function: DBdelete_template_httptests                                      *
 *                                                                            *
 * Purpose: delete template web scenatios from host                           *
 *                                                                            *
 * Parameters: hostid      - [IN] host identificator from database            *
 *             templateids - [IN] array of template IDs                       *
 *                                                                            *
 ******************************************************************************/
static void	DBdelete_template_httptests(zbx_uint64_t hostid, const zbx_vector_uint64_t *templateids)
{
	const char		*__function_name = "DBdelete_template_httptests";

	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_vector_uint64_t	httptestids;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_uint64_create(&httptestids);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select h.httptestid"
			" from httptest h"
				" join httptest t"
					" on");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "t.hostid", templateids->values, templateids->values_num);
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
						" and t.httptestid=h.templateid"
			" where h.hostid=" ZBX_FS_UI64, hostid);

	DBselect_uint64(sql, &httptestids);

	DBdelete_httptests(&httptestids);

	zbx_vector_uint64_destroy(&httptestids);
	zbx_free(sql);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: DBdelete_template_graphs                                         *
 *                                                                            *
 * Purpose: delete template graphs from host                                  *
 *                                                                            *
 * Parameters: hostid      - [IN] host identificator from database            *
 *             templateids - [IN] array of template IDs                       *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget to sync the code with PHP !!!                   *
 *                                                                            *
 ******************************************************************************/
static void	DBdelete_template_graphs(zbx_uint64_t hostid, const zbx_vector_uint64_t *templateids)
{
	const char		*__function_name = "DBdelete_template_graphs";

	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_vector_uint64_t	graphids;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_uint64_create(&graphids);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select distinct gi.graphid"
			" from graphs_items gi,items i,items ti"
			" where gi.itemid=i.itemid"
				" and i.templateid=ti.itemid"
				" and i.hostid=" ZBX_FS_UI64
				" and",
			hostid);
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "ti.hostid", templateids->values, templateids->values_num);

	DBselect_uint64(sql, &graphids);

	DBdelete_graph_hierarchy(&graphids);

	zbx_vector_uint64_destroy(&graphids);
	zbx_free(sql);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: DBdelete_template_triggers                                       *
 *                                                                            *
 * Purpose: delete template triggers from host                                *
 *                                                                            *
 * Parameters: hostid      - [IN] host identificator from database            *
 *             templateids - [IN] array of template IDs                       *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget to sync the code with PHP !!!                   *
 *                                                                            *
 ******************************************************************************/
static void	DBdelete_template_triggers(zbx_uint64_t hostid, const zbx_vector_uint64_t *templateids)
{
	const char		*__function_name = "DBdelete_template_triggers";

	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_vector_uint64_t	triggerids;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_uint64_create(&triggerids);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select distinct f.triggerid"
			" from functions f,items i,items ti"
			" where f.itemid=i.itemid"
				" and i.templateid=ti.itemid"
				" and i.hostid=" ZBX_FS_UI64
				" and",
			hostid);
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "ti.hostid", templateids->values, templateids->values_num);

	DBselect_uint64(sql, &triggerids);

	DBdelete_trigger_hierarchy(&triggerids);

	zbx_vector_uint64_destroy(&triggerids);
	zbx_free(sql);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: DBdelete_template_host_prototypes                                *
 *                                                                            *
 * Purpose: delete template host prototypes from host                         *
 *                                                                            *
 * Parameters: hostid      - [IN] host identificator from database            *
 *             templateids - [IN] array of template IDs                       *
 *                                                                            *
 ******************************************************************************/
static void	DBdelete_template_host_prototypes(zbx_uint64_t hostid, zbx_vector_uint64_t *templateids)
{
	const char		*__function_name = "DBdelete_template_host_prototypes";

	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_vector_uint64_t	host_prototypeids;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_uint64_create(&host_prototypeids);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select hp.hostid"
			" from items hi,host_discovery hhd,hosts hp,host_discovery thd,items ti"
			" where hi.itemid=hhd.parent_itemid"
				" and hhd.hostid=hp.hostid"
				" and hp.templateid=thd.hostid"
				" and thd.parent_itemid=ti.itemid"
				" and hi.hostid=" ZBX_FS_UI64
				" and",
			hostid);
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "ti.hostid", templateids->values, templateids->values_num);

	DBselect_uint64(sql, &host_prototypeids);

	DBdelete_host_prototypes(&host_prototypeids);

	zbx_free(sql);

	zbx_vector_uint64_destroy(&host_prototypeids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: DBdelete_template_items                                          *
 *                                                                            *
 * Purpose: delete template items from host                                   *
 *                                                                            *
 * Parameters: hostid      - [IN] host identificator from database            *
 *             templateids - [IN] array of template IDs                       *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget to sync the code with PHP !!!                   *
 *                                                                            *
 ******************************************************************************/
static void	DBdelete_template_items(zbx_uint64_t hostid, const zbx_vector_uint64_t *templateids)
{
	const char		*__function_name = "DBdelete_template_items";

	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_vector_uint64_t	itemids;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_uint64_create(&itemids);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select distinct i.itemid"
			" from items i,items ti"
			" where i.templateid=ti.itemid"
				" and i.hostid=" ZBX_FS_UI64
				" and",
			hostid);
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "ti.hostid", templateids->values, templateids->values_num);

	DBselect_uint64(sql, &itemids);

	DBdelete_items(&itemids);

	zbx_vector_uint64_destroy(&itemids);
	zbx_free(sql);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: DBdelete_template_applications                                   *
 *                                                                            *
 * Purpose: delete host applications that belong to an unlinked template      *
 *                                                                            *
 * Parameters: hostid      - [IN] host identificator from database            *
 *             templateids - [IN] array of template IDs                       *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget to sync the code with PHP !!!                   *
 *                                                                            *
 *           This function does not remove applications discovered by         *
 *           application prototypes.                                          *
 *           Use DBdelete_template_discovered_applications() for that.        *
 *                                                                            *
 ******************************************************************************/
static void	DBdelete_template_applications(zbx_uint64_t hostid, const zbx_vector_uint64_t *templateids)
{
	const char		*__function_name = "DBdelete_template_applications";

	DB_RESULT		result;
	DB_ROW			row;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_uint64_t		id;
	zbx_vector_uint64_t	applicationids, apptemplateids;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_uint64_create(&applicationids);
	zbx_vector_uint64_create(&apptemplateids);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select t.application_templateid,t.applicationid"
			" from application_template t,applications a,applications ta"
			" where t.applicationid=a.applicationid"
				" and t.templateid=ta.applicationid"
				" and a.hostid=" ZBX_FS_UI64
				" and a.flags=%d"
				" and",
			hostid, ZBX_FLAG_DISCOVERY_NORMAL);
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "ta.hostid", templateids->values, templateids->values_num);

	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(id, row[0]);
		zbx_vector_uint64_append(&apptemplateids, id);

		ZBX_STR2UINT64(id, row[1]);
		zbx_vector_uint64_append(&applicationids, id);
	}
	DBfree_result(result);

	if (0 != apptemplateids.values_num)
	{
		zbx_vector_uint64_sort(&apptemplateids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		zbx_vector_uint64_sort(&applicationids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_vector_uint64_uniq(&applicationids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		sql_offset = 0;
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from application_template where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "application_templateid",
				apptemplateids.values, apptemplateids.values_num);

		DBexecute("%s", sql);

		DBdelete_applications(&applicationids);
	}

	zbx_vector_uint64_destroy(&apptemplateids);
	zbx_vector_uint64_destroy(&applicationids);
	zbx_free(sql);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: DBdelete_template_discovered_applications                        *
 *                                                                            *
 * Purpose: delete host applications that belong to an unlinked template      *
 *                                                                            *
 * Parameters: hostid      - [IN] host identificator from database            *
 *             templateids - [IN] array of template IDs                       *
 *                                                                            *
 * Comments: !!! Don't forget to sync the code with PHP !!!                   *
 *                                                                            *
 ******************************************************************************/
static void	DBdelete_template_discovered_applications(zbx_uint64_t hostid, const zbx_vector_uint64_t *templateids)
{
	const char		*__function_name = "DBdelete_template_discovered_applications";

	DB_RESULT		result;
	DB_ROW			row;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_uint64_t		id;
	zbx_vector_uint64_t	applicationids, lld_ruleids;
	int			index;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_uint64_create(&applicationids);
	zbx_vector_uint64_create(&lld_ruleids);

	/* get the discovery rules */

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select i.itemid from items i"
			" left join items ti"
				" on i.templateid=ti.itemid"
			" where i.hostid=" ZBX_FS_UI64
				" and i.flags=%d"
				" and",
			hostid, ZBX_FLAG_DISCOVERY_RULE);
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "ti.hostid", templateids->values, templateids->values_num);

	DBselect_uint64(sql, &lld_ruleids);

	if (0 == lld_ruleids.values_num)
		goto out;

	/* get the applications discovered by those rules */

	sql_offset = 0;
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select ad.applicationid"
			" from application_discovery ad"
			" left join application_prototype ap"
				" on ap.application_prototypeid=ad.application_prototypeid"
			" where");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "ap.itemid", lld_ruleids.values, lld_ruleids.values_num);

	zbx_vector_uint64_clear(&applicationids);
	DBselect_uint64(sql, &applicationids);

	if (0 == applicationids.values_num)
		goto out;

	/* check if the applications are not discovered by other discovery rules */

	sql_offset = 0;
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select ad.applicationid"
			" from application_discovery ad"
			" left join application_prototype ap"
				" on ad.application_prototypeid=ap.application_prototypeid"
			" where not");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "ap.itemid", lld_ruleids.values, lld_ruleids.values_num);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " and");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "ad.applicationid", applicationids.values,
			applicationids.values_num);

	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(id, row[0]);

		if (FAIL != (index = zbx_vector_uint64_bsearch(&applicationids, id, ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
			zbx_vector_uint64_remove(&applicationids, index);
	}
	DBfree_result(result);

	if (0 == applicationids.values_num)
		goto out;

	/* discovered applications must be always removed, that's why we are  */
	/* doing it directly instead of using DBdelete_applications()         */
	sql_offset = 0;
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from applications where");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "applicationid", applicationids.values
			, applicationids.values_num);
	DBexecute("%s", sql);
out:
	zbx_vector_uint64_destroy(&lld_ruleids);
	zbx_vector_uint64_destroy(&applicationids);
	zbx_free(sql);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: DBcopy_trigger_to_host                                           *
 *                                                                            *
 * Purpose: copy specified trigger to host                                    *
 *                                                                            *
 * Parameters: hostid - host identificator from database                      *
 *             triggerid - trigger identificator from database                *
 *             description - trigger description                              *
 *             expression - trigger expression                                *
 *             status - trigger status                                        *
 *             type - trigger type                                            *
 *             priority - trigger priority                                    *
 *             comments - trigger comments                                    *
 *             url - trigger url                                              *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *                                                                            *
 * Author: Eugene Grigorjev, Alexander Vladishev                              *
 *                                                                            *
 * Comments: !!! Don't forget to sync the code with PHP !!!                   *
 *                                                                            *
 ******************************************************************************/
static int	DBcopy_trigger_to_host(zbx_uint64_t *new_triggerid, zbx_uint64_t hostid,
		zbx_uint64_t triggerid, const char *description, const char *expression,
		unsigned char status, unsigned char type, unsigned char priority,
		const char *comments, const char *url, unsigned char flags)
{
	DB_RESULT	result;
	DB_ROW		row;
	char		*sql = NULL;
	size_t		sql_alloc = 256, sql_offset = 0;
	zbx_uint64_t	itemid,	h_triggerid, functionid;
	char		*old_expression = NULL,
			*new_expression = NULL,
			*expression_esc = NULL,
			*search = NULL,
			*replace = NULL,
			*description_esc = NULL,
			*comments_esc = NULL,
			*url_esc = NULL,
			*function_esc = NULL,
			*parameter_esc = NULL;
	int		res = FAIL;

	sql = zbx_malloc(sql, sql_alloc);

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	description_esc = DBdyn_escape_string(description);

	result = DBselect(
			"select distinct t.triggerid,t.expression"
			" from triggers t,functions f,items i"
			" where t.triggerid=f.triggerid"
				" and f.itemid=i.itemid"
				" and t.templateid is null"
				" and i.hostid=" ZBX_FS_UI64
				" and t.description='%s'",
			hostid, description_esc);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(h_triggerid, row[0]);

		if (SUCCEED != DBcmp_triggers(triggerid, expression,
				h_triggerid, row[1]))
			continue;

		/* link not linked trigger with same description and expression */
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"update triggers"
				" set templateid=" ZBX_FS_UI64 ","
					"flags=%d"
				" where triggerid=" ZBX_FS_UI64 ";\n",
				triggerid, (int)flags, h_triggerid);

		res = SUCCEED;
		break;
	}
	DBfree_result(result);

	/* create trigger if no updated triggers */
	if (SUCCEED != res)
	{
		res = SUCCEED;

		*new_triggerid = DBget_maxid("triggers");
		new_expression = strdup(expression);

		comments_esc = DBdyn_escape_string(comments);
		url_esc = DBdyn_escape_string(url);

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"insert into triggers"
					" (triggerid,description,priority,status,"
						"comments,url,type,value,state,templateid,flags)"
					" values (" ZBX_FS_UI64 ",'%s',%d,%d,"
						"'%s','%s',%d,%d,%d," ZBX_FS_UI64 ",%d);\n",
					*new_triggerid, description_esc, (int)priority, (int)status, comments_esc,
					url_esc, (int)type, TRIGGER_VALUE_OK, TRIGGER_STATE_NORMAL, triggerid,
					(int)flags);

		zbx_free(url_esc);
		zbx_free(comments_esc);

		/* Loop: functions */
		result = DBselect(
				"select hi.itemid,tf.functionid,tf.function,tf.parameter,ti.key_"
				" from functions tf,items ti"
				" left join items hi"
					" on hi.key_=ti.key_"
						" and hi.hostid=" ZBX_FS_UI64
				" where tf.itemid=ti.itemid"
					" and tf.triggerid=" ZBX_FS_UI64,
				hostid, triggerid);

		while (SUCCEED == res && NULL != (row = DBfetch(result)))
		{
			if (SUCCEED != DBis_null(row[0]))
			{
				ZBX_STR2UINT64(itemid, row[0]);

				functionid = DBget_maxid("functions");

				search = zbx_dsprintf(NULL, "{%s}", row[1]);
				replace = zbx_dsprintf(NULL, "{" ZBX_FS_UI64 "}", functionid);

				function_esc = DBdyn_escape_string(row[2]);
				parameter_esc = DBdyn_escape_string(row[3]);

				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
						"insert into functions"
						" (functionid,itemid,triggerid,function,parameter)"
						" values (" ZBX_FS_UI64 "," ZBX_FS_UI64 ","
							ZBX_FS_UI64 ",'%s','%s');\n",
						functionid, itemid, *new_triggerid,
						function_esc, parameter_esc);

				old_expression = new_expression;
				new_expression = string_replace(old_expression, search, replace);

				zbx_free(old_expression);
				zbx_free(parameter_esc);
				zbx_free(function_esc);
				zbx_free(replace);
				zbx_free(search);
			}
			else
			{
				zabbix_log(LOG_LEVEL_DEBUG, "Missing similar key '%s'"
						" for host [" ZBX_FS_UI64 "]",
						row[4], hostid);
				res = FAIL;
			}
		}
		DBfree_result(result);

		if (SUCCEED == res)
		{
			expression_esc = DBdyn_escape_string_len(new_expression, TRIGGER_EXPRESSION_LEN);

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
					"update triggers set expression='%s' where triggerid=" ZBX_FS_UI64 ";\n",
					expression_esc, *new_triggerid);

			zbx_free(expression_esc);
		}

		zbx_free(new_expression);
	}
	else
		*new_triggerid = 0;

	DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (sql_offset > 16)	/* In ORACLE always present begin..end; */
		DBexecute("%s", sql);

	zbx_free(sql);
	zbx_free(description_esc);

	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: DBadd_template_dependencies_for_new_triggers                     *
 *                                                                            *
 * Purpose: update trigger dependencies for specified host                    *
 *                                                                            *
 * Parameters: trids - array of trigger identifiers from database             *
 *             trids_num - trigger count in trids array                       *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget to sync the code with PHP !!!                   *
 *                                                                            *
 ******************************************************************************/
static int	DBadd_template_dependencies_for_new_triggers(zbx_uint64_t *trids, int trids_num)
{
	DB_RESULT			result;
	DB_ROW				row;
	int				alloc = 16, count = 0, i;
	zbx_uint64_t			*hst_triggerids = NULL, *tpl_triggerids = NULL,
					templateid, triggerid,
					templateid_down, templateid_up,
					triggerid_down, triggerid_up,
					triggerdepid;
	char				*sql = NULL;
	size_t				sql_alloc = 512, sql_offset;
	zbx_db_insert_t			db_insert;
	zbx_vector_uint64_pair_t	links;


	if (0 == trids_num)
		return SUCCEED;

	zbx_vector_uint64_pair_create(&links);

	sql = zbx_malloc(sql, sql_alloc);
	tpl_triggerids = zbx_malloc(tpl_triggerids, alloc * sizeof(zbx_uint64_t));
	hst_triggerids = zbx_malloc(hst_triggerids, alloc * sizeof(zbx_uint64_t));

	sql_offset = 0;
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select triggerid,templateid"
			" from triggers"
			" where");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "triggerid", trids, trids_num);

	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(triggerid, row[0]);
		ZBX_DBROW2UINT64(templateid, row[1]);

		if (alloc == count)
		{
			alloc += 16;
			hst_triggerids = zbx_realloc(hst_triggerids, alloc * sizeof(zbx_uint64_t));
			tpl_triggerids = zbx_realloc(tpl_triggerids, alloc * sizeof(zbx_uint64_t));
		}

		hst_triggerids[count] = triggerid;
		tpl_triggerids[count] = templateid;
		count++;
	}
	DBfree_result(result);

	sql_offset = 0;
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select distinct td.triggerid_down,td.triggerid_up"
			" from triggers t,trigger_depends td"
			" where t.templateid in (td.triggerid_up,td.triggerid_down)"
				" and");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "t.triggerid", trids, trids_num);

	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(templateid_down, row[0]);
		ZBX_STR2UINT64(templateid_up, row[1]);

		triggerid_down = 0;
		triggerid_up = templateid_up;

		for (i = 0; i < count; i++)
		{
			if (tpl_triggerids[i] == templateid_down)
				triggerid_down = hst_triggerids[i];
			if (tpl_triggerids[i] == templateid_up)
				triggerid_up = hst_triggerids[i];
		}

		if (0 != triggerid_down)
		{
			zbx_uint64_pair_t	link = {triggerid_down, triggerid_up};

			zbx_vector_uint64_pair_append(&links, link);
		}
	}
	DBfree_result(result);

	if (0 < links.values_num)
	{
		triggerdepid = DBget_maxid_num("trigger_depends", links.values_num);

		zbx_db_insert_prepare(&db_insert, "trigger_depends", "triggerdepid", "triggerid_down", "triggerid_up",
				NULL);

		for (i = 0; i < links.values_num; i++)
		{
			zbx_db_insert_add_values(&db_insert, triggerdepid++, links.values[i].first,
					links.values[i].second);
		}

		zbx_db_insert_execute(&db_insert);
		zbx_db_insert_clean(&db_insert);
	}

	zbx_free(hst_triggerids);
	zbx_free(tpl_triggerids);
	zbx_free(sql);

	zbx_vector_uint64_pair_destroy(&links);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: get_templates_by_hostid                                          *
 *                                                                            *
 * Description: Retrieve already linked templates for specified host          *
 *                                                                            *
 * Parameters: hostid      - [IN] host identificator from database            *
 *             templateids - [IN] array of template IDs                       *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
static void	get_templates_by_hostid(zbx_uint64_t hostid, zbx_vector_uint64_t *templateids)
{
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	templateid;

	result = DBselect(
			"select templateid"
			" from hosts_templates"
			" where hostid=" ZBX_FS_UI64,
			hostid);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(templateid, row[0]);
		zbx_vector_uint64_append(templateids, templateid);
	}
	DBfree_result(result);

	zbx_vector_uint64_sort(templateids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
}

/******************************************************************************
 *                                                                            *
 * Function: DBdelete_template_elements                                       *
 *                                                                            *
 * Purpose: delete template elements from host                                *
 *                                                                            *
 * Parameters: hostid          - [IN] host identificator from database        *
 *             del_templateids - [IN] array of template IDs                   *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget to sync the code with PHP !!!                   *
 *                                                                            *
 ******************************************************************************/
int	DBdelete_template_elements(zbx_uint64_t hostid, zbx_vector_uint64_t *del_templateids)
{
	const char		*__function_name = "DBdelete_template_elements";

	char			*sql = NULL;
	size_t			sql_alloc = 128, sql_offset = 0;
	zbx_vector_uint64_t	templateids;
	int			i, index, res = SUCCEED;
	char			error[MAX_STRING_LEN];

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_uint64_create(&templateids);

	get_templates_by_hostid(hostid, &templateids);

	for (i = 0; i < del_templateids->values_num; i++)
	{
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

	if (SUCCEED != (res = validate_linked_templates(&templateids, error, sizeof(error))))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot unlink template: %s", error);
		goto clean;
	}

	DBdelete_template_httptests(hostid, del_templateids);
	DBdelete_template_graphs(hostid, del_templateids);
	DBdelete_template_triggers(hostid, del_templateids);
	DBdelete_template_host_prototypes(hostid, del_templateids);

	/* Removing items will remove discovery rules and all application discovery records */
	/* related to them. Because of that discovered applications must be removed before  */
	/* removing items.                                                                  */
	DBdelete_template_discovered_applications(hostid, del_templateids);
	DBdelete_template_items(hostid, del_templateids);

	/* normal applications must be removed after items are removed to cleanup */
	/* unlinked applications                                                  */
	DBdelete_template_applications(hostid, del_templateids);

	sql = zbx_malloc(sql, sql_alloc);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"delete from hosts_templates"
			" where hostid=" ZBX_FS_UI64
				" and",
			hostid);
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "templateid",
			del_templateids->values, del_templateids->values_num);
	DBexecute("%s", sql);

	zbx_free(sql);
clean:
	zbx_vector_uint64_destroy(&templateids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(res));

	return res;
}

typedef struct
{
	zbx_uint64_t		applicationid;
	char			*name;
	zbx_vector_uint64_t	templateids;
}
zbx_application_t;

static void	zbx_application_clean(zbx_application_t *application)
{
	zbx_vector_uint64_destroy(&application->templateids);
	zbx_free(application->name);
	zbx_free(application);
}

/******************************************************************************
 *                                                                            *
 * Function: DBcopy_template_application_prototypes                           *
 *                                                                            *
 * Purpose: copy application prototypes from templates to host                *
 *                                                                            *
 * Parameters: hostid      - [IN] host id                                     *
 *             templateids - [IN] array of template IDs                       *
 *                                                                            *
 * Comments: The host items must be already copied.                           *
 *                                                                            *
 ******************************************************************************/
static void	DBcopy_template_application_prototypes(zbx_uint64_t hostid, const zbx_vector_uint64_t *templateids)
{
	const char	*__function_name = "DBcopy_template_application_prototypes";
	DB_RESULT	result;
	DB_ROW		row;
	char		*sql = NULL;
	size_t		sql_alloc = 0, sql_offset = 0;
	zbx_db_insert_t	db_insert;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select ap.application_prototypeid,ap.name,i_t.itemid"
			" from application_prototype ap"
			" left join items i"
				" on ap.itemid=i.itemid"
			" left join items i_t"
				" on i_t.templateid=i.itemid"
			" where i.flags=%d"
				" and i_t.hostid=" ZBX_FS_UI64
				" and",
			ZBX_FLAG_DISCOVERY_RULE, hostid);

	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "i.hostid", templateids->values, templateids->values_num);

	result = DBselect("%s", sql);

	zbx_free(sql);

	if (NULL == (row = DBfetch(result)))
		goto out;

	zbx_db_insert_prepare(&db_insert, "application_prototype", "application_prototypeid", "itemid", "templateid",
			"name", NULL);
	do
	{
		zbx_uint64_t	application_prototypeid, lld_ruleid;

		ZBX_STR2UINT64(application_prototypeid, row[0]);
		ZBX_STR2UINT64(lld_ruleid, row[2]);

		zbx_db_insert_add_values(&db_insert, __UINT64_C(0), lld_ruleid, application_prototypeid, row[1]);
	}
	while (NULL != (row = DBfetch(result)));

	zbx_db_insert_autoincrement(&db_insert, "application_prototypeid");
	zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);
out:
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: DBcopy_template_item_application_prototypes                      *
 *                                                                            *
 * Purpose: copy application prototypes from templates to host                *
 *                                                                            *
 * Parameters: hostid      - [IN] host id                                     *
 *             templateids - [IN] array of template IDs                       *
 *                                                                            *
 * Comments: The host items and application prototypes must be already copied.*
 *                                                                            *
 ******************************************************************************/
static void	DBcopy_template_item_application_prototypes(zbx_uint64_t hostid, const zbx_vector_uint64_t *templateids)
{
	const char	*__function_name = "DBcopy_template_item_application_prototypes";
	DB_RESULT	result;
	DB_ROW		row;
	char		*sql = NULL;
	size_t		sql_alloc = 0, sql_offset = 0;
	zbx_db_insert_t	db_insert;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select ap.application_prototypeid,i.itemid"
			" from items i_ap,item_application_prototype iap"
			" left join application_prototype ap"
				" on ap.templateid=iap.application_prototypeid"
			" left join items i_t"
				" on i_t.itemid=iap.itemid"
			" left join items i"
				" on i.templateid=i_t.itemid"
			" where i.hostid=" ZBX_FS_UI64
				" and i_ap.itemid=ap.itemid"
				" and i_ap.hostid=" ZBX_FS_UI64
				" and",
			hostid, hostid);

	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "i_t.hostid", templateids->values,
			templateids->values_num);

	result = DBselect("%s", sql);

	zbx_free(sql);

	if (NULL == (row = DBfetch(result)))
		goto out;

	zbx_db_insert_prepare(&db_insert, "item_application_prototype", "item_application_prototypeid",
			"application_prototypeid", "itemid", NULL);

	do
	{
		zbx_uint64_t	application_prototypeid, itemid;

		ZBX_STR2UINT64(application_prototypeid, row[0]);
		ZBX_STR2UINT64(itemid, row[1]);

		zbx_db_insert_add_values(&db_insert, __UINT64_C(0), application_prototypeid, itemid);
	}
	while (NULL != (row = DBfetch(result)));

	zbx_db_insert_autoincrement(&db_insert, "item_application_prototypeid");
	zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);
out:
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: DBcopy_template_applications                                     *
 *                                                                            *
 * Purpose: copy applications from templates to host                          *
 *                                                                            *
 * Parameters: hostid      - [IN] host id                                     *
 *             templateids - [IN] array of template IDs                       *
 *                                                                            *
 ******************************************************************************/
static void	DBcopy_template_applications(zbx_uint64_t hostid, const zbx_vector_uint64_t *templateids)
{
	const char		*__function_name = "DBcopy_template_applications";
	DB_RESULT		result;
	DB_ROW			row;
	char			*sql = NULL;
	size_t			sql_alloc = ZBX_KIBIBYTE, sql_offset = 0;
	zbx_application_t	*application = NULL;
	zbx_vector_ptr_t	applications;
	int			i, j, new_applications = 0, new_application_templates = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_ptr_create(&applications);

	sql = zbx_malloc(sql, sql_alloc);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select applicationid,hostid,name"
			" from applications"
			" where hostid=" ZBX_FS_UI64
				" or", hostid);
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid", templateids->values, templateids->values_num);

	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		zbx_uint64_t	db_applicationid, db_hostid;

		ZBX_STR2UINT64(db_applicationid, row[0]);
		ZBX_STR2UINT64(db_hostid, row[1]);

		for (i = 0; i < applications.values_num; i++)
		{
			application = (zbx_application_t *)applications.values[i];

			if (0 == strcmp(application->name, row[2]))
				break;
		}

		if (i == applications.values_num)
		{
			application = (zbx_application_t *)zbx_malloc(NULL, sizeof(zbx_application_t));

			application->applicationid = 0;
			application->name = zbx_strdup(NULL, row[2]);
			zbx_vector_uint64_create(&application->templateids);

			zbx_vector_ptr_append(&applications, application);
		}

		if (db_hostid == hostid)
			application->applicationid = db_applicationid;
		else
			zbx_vector_uint64_append(&application->templateids, db_applicationid);
	}
	DBfree_result(result);

	for (i = 0; i < applications.values_num; i++)
	{
		application = (zbx_application_t *)applications.values[i];

		if (0 == application->applicationid)
			new_applications++;

		new_application_templates += application->templateids.values_num;
	}

	if (0 != new_applications)
	{
		zbx_uint64_t	applicationid;
		zbx_db_insert_t	db_insert;

		applicationid = DBget_maxid_num("applications", new_applications);

		zbx_db_insert_prepare(&db_insert, "applications", "applicationid", "hostid", "name", NULL);

		for (i = 0; i < applications.values_num; i++)
		{
			application = (zbx_application_t *)applications.values[i];

			if (0 != application->applicationid)
				continue;

			zbx_db_insert_add_values(&db_insert, applicationid, hostid, application->name);

			application->applicationid = applicationid++;
		}

		zbx_db_insert_execute(&db_insert);
		zbx_db_insert_clean(&db_insert);
	}

	if (0 != new_application_templates)
	{
		zbx_uint64_t	application_templateid;
		zbx_db_insert_t	db_insert;

		application_templateid = DBget_maxid_num("application_template", new_application_templates);

		zbx_db_insert_prepare(&db_insert,"application_template", "application_templateid", "applicationid",
				"templateid", NULL);

		for (i = 0; i < applications.values_num; i++)
		{
			application = (zbx_application_t *)applications.values[i];

			for (j = 0; j < application->templateids.values_num; j++)
			{
				zbx_db_insert_add_values(&db_insert, application_templateid++,
						application->applicationid, application->templateids.values[j]);
			}
		}

		zbx_db_insert_execute(&db_insert);
		zbx_db_insert_clean(&db_insert);
	}

	zbx_vector_ptr_clear_ext(&applications, (zbx_clean_func_t)zbx_application_clean);
	zbx_vector_ptr_destroy(&applications);
	zbx_free(sql);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

typedef struct
{
	zbx_uint64_t	group_prototypeid;
	zbx_uint64_t	groupid;
	zbx_uint64_t	templateid;	/* reference to parent group_prototypeid */
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
	zbx_uint64_t		templateid;		/* link to parent template */
	zbx_uint64_t		hostid;
	zbx_uint64_t		itemid;			/* discovery rule id */
	zbx_vector_uint64_t	lnk_templateids;	/* list of templates which should be linked */
	zbx_vector_ptr_t	group_prototypes;	/* list of group prototypes */
	char			*host;
	char			*name;
	unsigned char		status;
#define ZBX_FLAG_HPLINK_UPDATE_NAME	0x01
#define ZBX_FLAG_HPLINK_UPDATE_STATUS	0x02
	unsigned char		flags;
}
zbx_host_prototype_t;

static void	DBhost_prototype_clean(zbx_host_prototype_t *host_prototype)
{
	zbx_free(host_prototype->name);
	zbx_free(host_prototype->host);
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
 * Function: DBis_regular_host                                                *
 *                                                                            *
 * Comments: auxiliary function for DBcopy_template_host_prototypes()         *
 *                                                                            *
 ******************************************************************************/
static int	DBis_regular_host(zbx_uint64_t hostid)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		ret = FAIL;

	result = DBselect("select flags from hosts where hostid=" ZBX_FS_UI64, hostid);

	if (NULL != (row = DBfetch(result)))
	{
		if (0 == atoi(row[0]))
			ret = SUCCEED;
	}
	DBfree_result(result);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: DBhost_prototypes_make                                           *
 *                                                                            *
 * Comments: auxiliary function for DBcopy_template_host_prototypes()         *
 *                                                                            *
 ******************************************************************************/
static void	DBhost_prototypes_make(zbx_uint64_t hostid, zbx_vector_uint64_t *templateids,
		zbx_vector_ptr_t *host_prototypes)
{
	DB_RESULT		result;
	DB_ROW			row;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_vector_uint64_t	itemids;
	zbx_host_prototype_t	*host_prototype;

	zbx_vector_uint64_create(&itemids);

	/* selects host prototypes from templates */

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select hi.itemid,th.hostid,th.host,th.name,th.status"
			" from items hi,items ti,host_discovery thd,hosts th"
			" where hi.templateid=ti.itemid"
				" and ti.itemid=thd.parent_itemid"
				" and thd.hostid=th.hostid"
				" and hi.hostid=" ZBX_FS_UI64
				" and",
			hostid);
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "ti.hostid", templateids->values, templateids->values_num);

	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		host_prototype = (zbx_host_prototype_t *)zbx_malloc(NULL, sizeof(zbx_host_prototype_t));

		host_prototype->hostid = 0;
		ZBX_STR2UINT64(host_prototype->itemid, row[0]);
		ZBX_STR2UINT64(host_prototype->templateid, row[1]);
		zbx_vector_uint64_create(&host_prototype->lnk_templateids);
		zbx_vector_ptr_create(&host_prototype->group_prototypes);
		host_prototype->host = zbx_strdup(NULL, row[2]);
		host_prototype->name = zbx_strdup(NULL, row[3]);
		host_prototype->status = (unsigned char)atoi(row[4]);
		host_prototype->flags = 0;

		zbx_vector_ptr_append(host_prototypes, host_prototype);
		zbx_vector_uint64_append(&itemids, host_prototype->itemid);
	}
	DBfree_result(result);

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
				"select i.itemid,h.hostid,h.host,h.name,h.status"
				" from items i,host_discovery hd,hosts h"
				" where i.itemid=hd.parent_itemid"
					" and hd.hostid=h.hostid"
					" and i.hostid=" ZBX_FS_UI64
					" and",
				hostid);
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "i.itemid", itemids.values, itemids.values_num);

		result = DBselect("%s", sql);

		while (NULL != (row = DBfetch(result)))
		{
			ZBX_STR2UINT64(itemid, row[0]);

			for (i = 0; i < host_prototypes->values_num; i++)
			{
				host_prototype = (zbx_host_prototype_t *)host_prototypes->values[i];

				if (host_prototype->itemid == itemid && 0 == strcmp(host_prototype->host, row[2]))
				{
					ZBX_STR2UINT64(host_prototype->hostid, row[1]);
					if (0 != strcmp(host_prototype->name, row[3]))
						host_prototype->flags |= ZBX_FLAG_HPLINK_UPDATE_NAME;
					if (host_prototype->status != (status = (unsigned char)atoi(row[4])))
						host_prototype->flags |= ZBX_FLAG_HPLINK_UPDATE_STATUS;
					break;
				}
			}
		}
		DBfree_result(result);
	}

	zbx_free(sql);

	zbx_vector_uint64_destroy(&itemids);

	/* sort by templateid */
	zbx_vector_ptr_sort(host_prototypes, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);
}

/******************************************************************************
 *                                                                            *
 * Function: DBhost_prototypes_templates_make                                 *
 *                                                                            *
 * Parameters: host_prototypes     - [IN/OUT] list of host prototypes         *
 *                                   should be sorted by templateid           *
 *             del_hosttemplateids - [OUT] list of hosttemplateids which      *
 *                                   should be deleted                        *
 *                                                                            *
 * Comments: auxiliary function for DBcopy_template_host_prototypes()         *
 *                                                                            *
 ******************************************************************************/
static void	DBhost_prototypes_templates_make(zbx_vector_ptr_t *host_prototypes,
		zbx_vector_uint64_t *del_hosttemplateids)
{
	DB_RESULT		result;
	DB_ROW			row;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_vector_uint64_t	hostids;
	zbx_uint64_t		hostid, templateid, hosttemplateid;
	zbx_host_prototype_t	*host_prototype;
	int			i;

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
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid", hostids.values, hostids.values_num);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " order by hostid,templateid");

	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
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
	DBfree_result(result);

	/* select list of templates which already linked to host prototypes */

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
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid", hostids.values, hostids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " order by hosttemplateid");

		result = DBselect("%s", sql);

		while (NULL != (row = DBfetch(result)))
		{
			ZBX_STR2UINT64(hostid, row[0]);
			ZBX_STR2UINT64(templateid, row[1]);

			for (i = 0; i < host_prototypes->values_num; i++)
			{
				host_prototype = (zbx_host_prototype_t *)host_prototypes->values[i];

				if (host_prototype->hostid == hostid)
					break;
			}

			if (i == host_prototypes->values_num)
			{
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
			}

			if (FAIL == (i = zbx_vector_uint64_bsearch(&host_prototype->lnk_templateids, templateid,
						ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
			{
				ZBX_STR2UINT64(hosttemplateid, row[2]);
				zbx_vector_uint64_append(del_hosttemplateids, hosttemplateid);
			}
			else
				zbx_vector_uint64_remove(&host_prototype->lnk_templateids, i);
		}
		DBfree_result(result);
	}

	zbx_vector_uint64_destroy(&hostids);

	zbx_free(sql);
}

/******************************************************************************
 *                                                                            *
 * Function: DBhost_prototypes_groups_make                                    *
 *                                                                            *
 * Parameters: host_prototypes        - [IN/OUT] list of host prototypes      *
 *                                      should be sorted by templateid        *
 *             del_group_prototypeids - [OUT] list of group_prototypeid which *
 *                                      should be deleted                     *
 *                                                                            *
 * Comments: auxiliary function for DBcopy_template_host_prototypes()         *
 *                                                                            *
 ******************************************************************************/
static void	DBhost_prototypes_groups_make(zbx_vector_ptr_t *host_prototypes,
		zbx_vector_uint64_t *del_group_prototypeids)
{
	DB_RESULT		result;
	DB_ROW			row;
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
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid", hostids.values, hostids.values_num);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " order by hostid");

	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
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

		zbx_vector_ptr_append(&host_prototype->group_prototypes, group_prototype);
	}
	DBfree_result(result);

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
				"select hostid,group_prototypeid,groupid,name from group_prototype where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid", hostids.values, hostids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " order by group_prototypeid");

		result = DBselect("%s", sql);

		while (NULL != (row = DBfetch(result)))
		{
			ZBX_STR2UINT64(hostid, row[0]);

			for (i = 0; i < host_prototypes->values_num; i++)
			{
				host_prototype = (zbx_host_prototype_t *)host_prototypes->values[i];

				if (host_prototype->hostid == hostid)
					break;
			}

			if (i == host_prototypes->values_num)
			{
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
			}

			ZBX_STR2UINT64(group_prototypeid, row[1]);
			ZBX_DBROW2UINT64(groupid, row[2]);

			for (i = 0; i < host_prototype->group_prototypes.values_num; i++)
			{
				group_prototype = (zbx_group_prototype_t *)host_prototype->group_prototypes.values[i];

				if (0 != group_prototype->group_prototypeid)
					continue;

				if (group_prototype->groupid == groupid && 0 == strcmp(group_prototype->name, row[3]))
				{
					group_prototype->group_prototypeid = group_prototypeid;
					break;
				}
			}

			if (i == host_prototype->group_prototypes.values_num)
				zbx_vector_uint64_append(del_group_prototypeids, group_prototypeid);
		}
		DBfree_result(result);
	}

	zbx_vector_uint64_sort(del_group_prototypeids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_vector_uint64_destroy(&hostids);
	zbx_free(sql);
}

/******************************************************************************
 *                                                                            *
 * Function: DBhost_prototypes_save                                           *
 *                                                                            *
 * Comments: auxiliary function for DBcopy_template_host_prototypes()         *
 *                                                                            *
 ******************************************************************************/
static void	DBhost_prototypes_save(zbx_vector_ptr_t *host_prototypes, zbx_vector_uint64_t *del_hosttemplateids)
{
	char			*sql1 = NULL, *sql2 = NULL, *name_esc;
	size_t			sql1_alloc = ZBX_KIBIBYTE, sql1_offset = 0,
				sql2_alloc = ZBX_KIBIBYTE, sql2_offset = 0;
	zbx_host_prototype_t	*host_prototype;
	zbx_group_prototype_t	*group_prototype;
	zbx_uint64_t		hostid = 0, hosttemplateid = 0, group_prototypeid = 0;
	int			i, j, new_hosts = 0, new_hosts_templates = 0, new_group_prototypes = 0,
				upd_group_prototypes = 0;
	zbx_db_insert_t		db_insert, db_insert_hdiscovery, db_insert_htemplates, db_insert_gproto;

	for (i = 0; i < host_prototypes->values_num; i++)
	{
		host_prototype = (zbx_host_prototype_t *)host_prototypes->values[i];

		if (0 == host_prototype->hostid)
			new_hosts++;

		new_hosts_templates += host_prototype->lnk_templateids.values_num;

		for (j = 0; j < host_prototype->group_prototypes.values_num; j++)
		{
			group_prototype = (zbx_group_prototype_t *)host_prototype->group_prototypes.values[j];

			if (0 == group_prototype->group_prototypeid)
				new_group_prototypes++;
			else
				upd_group_prototypes++;
		}
	}

	if (0 != new_hosts)
	{
		hostid = DBget_maxid_num("hosts", new_hosts);

		zbx_db_insert_prepare(&db_insert, "hosts", "hostid", "host", "name", "status", "flags", "templateid",
				NULL);

		zbx_db_insert_prepare(&db_insert_hdiscovery, "host_discovery", "hostid", "parent_itemid", NULL);
	}

	if (new_hosts != host_prototypes->values_num || 0 != upd_group_prototypes)
	{
		sql1 = zbx_malloc(sql1, sql1_alloc);
		DBbegin_multiple_update(&sql1, &sql1_alloc, &sql1_offset);
	}

	if (0 != new_hosts_templates)
	{
		hosttemplateid = DBget_maxid_num("hosts_templates", new_hosts_templates);

		zbx_db_insert_prepare(&db_insert_htemplates, "hosts_templates",  "hosttemplateid", "hostid",
				"templateid", NULL);
	}

	if (0 != del_hosttemplateids->values_num)
	{
		sql2 = zbx_malloc(sql2, sql2_alloc);
		zbx_strcpy_alloc(&sql2, &sql2_alloc, &sql2_offset, "delete from hosts_templates where");
		DBadd_condition_alloc(&sql2, &sql2_alloc, &sql2_offset, "hosttemplateid",
				del_hosttemplateids->values, del_hosttemplateids->values_num);
	}

	if (0 != new_group_prototypes)
	{
		group_prototypeid = DBget_maxid_num("group_prototype", new_group_prototypes);

		zbx_db_insert_prepare(&db_insert_gproto, "group_prototype", "group_prototypeid", "hostid", "name",
				"groupid", "templateid", NULL);
	}

	for (i = 0; i < host_prototypes->values_num; i++)
	{
		host_prototype = (zbx_host_prototype_t *)host_prototypes->values[i];

		if (0 == host_prototype->hostid)
		{
			host_prototype->hostid = hostid++;

			zbx_db_insert_add_values(&db_insert, host_prototype->hostid, host_prototype->host,
					host_prototype->name, (int)host_prototype->status,
					(int)ZBX_FLAG_DISCOVERY_PROTOTYPE, host_prototype->templateid);

			zbx_db_insert_add_values(&db_insert_hdiscovery, host_prototype->hostid, host_prototype->itemid);
		}
		else
		{
			zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset, "update hosts set templateid=" ZBX_FS_UI64,
					host_prototype->templateid);
			if (0 != (host_prototype->flags & ZBX_FLAG_HPLINK_UPDATE_NAME))
			{
				name_esc = DBdyn_escape_string(host_prototype->name);
				zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset, ",name='%s'", name_esc);
				zbx_free(name_esc);
			}
			if (0 != (host_prototype->flags & ZBX_FLAG_HPLINK_UPDATE_STATUS))
			{
				zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset, ",status=%d",
						host_prototype->status);
			}
			zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset, " where hostid=" ZBX_FS_UI64 ";\n",
					host_prototype->hostid);
		}

		for (j = 0; j < host_prototype->lnk_templateids.values_num; j++)
		{
			zbx_db_insert_add_values(&db_insert_htemplates, hosttemplateid++, host_prototype->hostid,
					host_prototype->lnk_templateids.values[j]);
		}

		for (j = 0; j < host_prototype->group_prototypes.values_num; j++)
		{
			group_prototype = (zbx_group_prototype_t *)host_prototype->group_prototypes.values[j];

			if (0 == group_prototype->group_prototypeid)
			{
				zbx_db_insert_add_values(&db_insert_gproto, group_prototypeid++, host_prototype->hostid,
						group_prototype->name, group_prototype->groupid,
						group_prototype->templateid);
			}
			else
			{
				zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset,
						"update group_prototype"
						" set templateid=" ZBX_FS_UI64
						" where group_prototypeid=" ZBX_FS_UI64 ";\n",
						group_prototype->templateid, group_prototype->group_prototypeid);
			}
		}
	}

	if (0 != new_hosts)
	{
		zbx_db_insert_execute(&db_insert);
		zbx_db_insert_clean(&db_insert);

		zbx_db_insert_execute(&db_insert_hdiscovery);
		zbx_db_insert_clean(&db_insert_hdiscovery);
	}

	if (0 != new_hosts_templates)
	{
		zbx_db_insert_execute(&db_insert_htemplates);
		zbx_db_insert_clean(&db_insert_htemplates);
	}

	if (0 != new_group_prototypes)
	{
		zbx_db_insert_execute(&db_insert_gproto);
		zbx_db_insert_clean(&db_insert_gproto);
	}

	if (new_hosts != host_prototypes->values_num || 0 != upd_group_prototypes)
	{
		DBend_multiple_update(&sql1, &sql1_alloc, &sql1_offset);
		DBexecute("%s", sql1);
		zbx_free(sql1);
	}

	if (0 != del_hosttemplateids->values_num)
	{
		DBexecute("%s", sql2);
		zbx_free(sql2);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: DBcopy_template_host_prototypes                                  *
 *                                                                            *
 * Purpose: copy host prototypes from templates and create links between      *
 *          them and discovery rules                                          *
 *                                                                            *
 * Comments: auxiliary function for DBcopy_template_elements()                *
 *                                                                            *
 ******************************************************************************/
static void	DBcopy_template_host_prototypes(zbx_uint64_t hostid, zbx_vector_uint64_t *templateids)
{
	zbx_vector_ptr_t	host_prototypes;

	/* only regular hosts can have host prototypes */
	if (SUCCEED != DBis_regular_host(hostid))
		return;

	zbx_vector_ptr_create(&host_prototypes);

	DBhost_prototypes_make(hostid, templateids, &host_prototypes);

	if (0 != host_prototypes.values_num)
	{
		zbx_vector_uint64_t	del_hosttemplateids, del_group_prototypeids;

		zbx_vector_uint64_create(&del_hosttemplateids);
		zbx_vector_uint64_create(&del_group_prototypeids);

		DBhost_prototypes_templates_make(&host_prototypes, &del_hosttemplateids);
		DBhost_prototypes_groups_make(&host_prototypes, &del_group_prototypeids);
		DBhost_prototypes_save(&host_prototypes, &del_hosttemplateids);
		DBgroup_prototypes_delete(&del_group_prototypeids);

		zbx_vector_uint64_destroy(&del_group_prototypeids);
		zbx_vector_uint64_destroy(&del_hosttemplateids);
	}

	DBhost_prototypes_clean(&host_prototypes);
	zbx_vector_ptr_destroy(&host_prototypes);
}

/******************************************************************************
 *                                                                            *
 * Function: DBcopy_template_triggers                                         *
 *                                                                            *
 * Purpose: Copy template triggers to host                                    *
 *                                                                            *
 * Parameters: hostid      - [IN] host identificator from database            *
 *             templateids - [IN] array of template IDs                       *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget to sync the code with PHP !!!                   *
 *                                                                            *
 ******************************************************************************/
static int	DBcopy_template_triggers(zbx_uint64_t hostid, const zbx_vector_uint64_t *templateids)
{
	const char	*__function_name = "DBcopy_template_triggers";
	char		*sql = NULL;
	size_t		sql_alloc = 512, sql_offset = 0;
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	triggerid, new_triggerid;
	int		res = SUCCEED;
	zbx_uint64_t	*trids = NULL;
	int		trids_alloc = 0, trids_num = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	sql = zbx_malloc(sql, sql_alloc);

	sql_offset = 0;
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select distinct t.triggerid,t.description,t.expression,t.status,"
				"t.type,t.priority,t.comments,t.url,t.flags"
			" from triggers t,functions f,items i"
			" where t.triggerid=f.triggerid"
				" and f.itemid=i.itemid"
				" and");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "i.hostid", templateids->values, templateids->values_num);

	result = DBselect("%s", sql);

	zbx_free(sql);

	while (SUCCEED == res && NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(triggerid, row[0]);

		res = DBcopy_trigger_to_host(&new_triggerid, hostid, triggerid,
				row[1],				/* description */
				row[2],				/* expression */
				(unsigned char)atoi(row[3]),	/* status */
				(unsigned char)atoi(row[4]),	/* type */
				(unsigned char)atoi(row[5]),	/* priority */
				row[6],				/* comments */
				row[7],				/* url */
				(unsigned char)atoi(row[8]));	/* flags */

		if (0 != new_triggerid)				/* new trigger added */
			uint64_array_add(&trids, &trids_alloc, &trids_num, new_triggerid, 64);
	}
	DBfree_result(result);

	if (SUCCEED == res)
		res = DBadd_template_dependencies_for_new_triggers(trids, trids_num);

	zbx_free(trids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(res));

	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: DBget_same_itemid                                                *
 *                                                                            *
 * Purpose: get same itemid for selected host by itemid from template         *
 *                                                                            *
 * Parameters: hostid - host identificator from database                      *
 *             itemid - item identificator from database (from template)      *
 *                                                                            *
 * Return value: new item identificator or zero if item not found             *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments: !!! Don't forget to sync the code with PHP !!!                   *
 *                                                                            *
 ******************************************************************************/
static zbx_uint64_t	DBget_same_itemid(zbx_uint64_t hostid, zbx_uint64_t titemid)
{
	const char	*__function_name = "DBget_same_itemid";
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	itemid = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() hostid:" ZBX_FS_UI64
			" titemid:" ZBX_FS_UI64,
			__function_name, hostid, titemid);

	result = DBselect(
			"select hi.itemid"
			" from items hi,items ti"
			" where hi.key_=ti.key_"
				" and hi.hostid=" ZBX_FS_UI64
				" and ti.itemid=" ZBX_FS_UI64,
			hostid, titemid);

	if (NULL != (row = DBfetch(result)))
		ZBX_STR2UINT64(itemid, row[0]);
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():" ZBX_FS_UI64, __function_name, itemid);

	return itemid;
}

/******************************************************************************
 *                                                                            *
 * Function: DBcopy_graph_to_host                                             *
 *                                                                            *
 * Purpose: copy specified graph to host                                      *
 *                                                                            *
 * Parameters: graphid - graph identificator from database                    *
 *             hostid - host identificator from database                      *
 *                                                                            *
 * Author: Eugene Grigorjev, Alexander Vladishev                              *
 *                                                                            *
 * Comments: !!! Don't forget to sync the code with PHP !!!                   *
 *                                                                            *
 ******************************************************************************/
static void	DBcopy_graph_to_host(zbx_uint64_t hostid, zbx_uint64_t graphid,
		const char *name, int width, int height, double yaxismin,
		double yaxismax, unsigned char show_work_period,
		unsigned char show_triggers, unsigned char graphtype,
		unsigned char show_legend, unsigned char show_3d,
		double percent_left, double percent_right,
		unsigned char ymin_type, unsigned char ymax_type,
		zbx_uint64_t ymin_itemid, zbx_uint64_t ymax_itemid,
		unsigned char flags)
{
	const char	*__function_name = "DBcopy_graph_to_host";
	DB_RESULT	result;
	DB_ROW		row;
	ZBX_GRAPH_ITEMS *gitems = NULL, *chd_gitems = NULL;
	size_t		gitems_alloc = 0, gitems_num = 0,
			chd_gitems_alloc = 0, chd_gitems_num = 0;
	zbx_uint64_t	hst_graphid, hst_gitemid;
	char		*sql = NULL, *name_esc, *color_esc;
	size_t		sql_alloc = ZBX_KIBIBYTE, sql_offset, i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	sql = zbx_malloc(sql, sql_alloc * sizeof(char));

	name_esc = DBdyn_escape_string(name);

	sql_offset = 0;
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select 0,dst.itemid,dst.key_,gi.drawtype,gi.sortorder,gi.color,gi.yaxisside,gi.calc_fnc,"
				"gi.type,i.flags"
			" from graphs_items gi,items i,items dst"
			" where gi.itemid=i.itemid"
				" and i.key_=dst.key_"
				" and gi.graphid=" ZBX_FS_UI64
				" and dst.hostid=" ZBX_FS_UI64
			" order by dst.key_",
			graphid, hostid);

	DBget_graphitems(sql, &gitems, &gitems_alloc, &gitems_num);

	result = DBselect(
			"select distinct g.graphid"
			" from graphs g,graphs_items gi,items i"
			" where g.graphid=gi.graphid"
				" and gi.itemid=i.itemid"
				" and i.hostid=" ZBX_FS_UI64
				" and g.name='%s'"
				" and g.templateid is null",
			hostid, name_esc);

	/* compare graphs */
	hst_graphid = 0;
	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(hst_graphid, row[0]);

		sql_offset = 0;
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"select gi.gitemid,i.itemid,i.key_,gi.drawtype,gi.sortorder,gi.color,gi.yaxisside,"
					"gi.calc_fnc,gi.type,i.flags"
				" from graphs_items gi,items i"
				" where gi.itemid=i.itemid"
					" and gi.graphid=" ZBX_FS_UI64
				" order by i.key_",
				hst_graphid);

		DBget_graphitems(sql, &chd_gitems, &chd_gitems_alloc, &chd_gitems_num);

		if (SUCCEED == DBcmp_graphitems(gitems, gitems_num, chd_gitems, chd_gitems_num))
			break;	/* found equal graph */

		hst_graphid = 0;
	}
	DBfree_result(result);

	sql_offset = 0;
	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (GRAPH_YAXIS_TYPE_ITEM_VALUE == ymin_type)
		ymin_itemid = DBget_same_itemid(hostid, ymin_itemid);
	else
		ymin_itemid = 0;

	if (GRAPH_YAXIS_TYPE_ITEM_VALUE == ymax_type)
		ymax_itemid = DBget_same_itemid(hostid, ymax_itemid);
	else
		ymax_itemid = 0;

	if (0 != hst_graphid)
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"update graphs"
				" set name='%s',"
					"width=%d,"
					"height=%d,"
					"yaxismin=" ZBX_FS_DBL ","
					"yaxismax=" ZBX_FS_DBL ","
					"templateid=" ZBX_FS_UI64 ","
					"show_work_period=%d,"
					"show_triggers=%d,"
					"graphtype=%d,"
					"show_legend=%d,"
					"show_3d=%d,"
					"percent_left=" ZBX_FS_DBL ","
					"percent_right=" ZBX_FS_DBL ","
					"ymin_type=%d,"
					"ymax_type=%d,"
					"ymin_itemid=%s,"
					"ymax_itemid=%s,"
					"flags=%d"
				" where graphid=" ZBX_FS_UI64 ";\n",
				name_esc, width, height, yaxismin, yaxismax,
				graphid, (int)show_work_period, (int)show_triggers,
				(int)graphtype, (int)show_legend, (int)show_3d,
				percent_left, percent_right, (int)ymin_type, (int)ymax_type,
				DBsql_id_ins(ymin_itemid), DBsql_id_ins(ymax_itemid), (int)flags,
				hst_graphid);

		for (i = 0; i < gitems_num; i++)
		{
			color_esc = DBdyn_escape_string(gitems[i].color);

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
					"update graphs_items"
					" set drawtype=%d,"
						"sortorder=%d,"
						"color='%s',"
						"yaxisside=%d,"
						"calc_fnc=%d,"
						"type=%d"
					" where gitemid=" ZBX_FS_UI64 ";\n",
					gitems[i].drawtype,
					gitems[i].sortorder,
					color_esc,
					gitems[i].yaxisside,
					gitems[i].calc_fnc,
					gitems[i].type,
					chd_gitems[i].gitemid);

			zbx_free(color_esc);
		}
	}
	else
	{
		hst_graphid = DBget_maxid("graphs");

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"insert into graphs"
				" (graphid,name,width,height,yaxismin,yaxismax,templateid,"
				"show_work_period,show_triggers,graphtype,show_legend,"
				"show_3d,percent_left,percent_right,ymin_type,ymax_type,"
				"ymin_itemid,ymax_itemid,flags)"
				" values (" ZBX_FS_UI64 ",'%s',%d,%d," ZBX_FS_DBL ","
				ZBX_FS_DBL "," ZBX_FS_UI64 ",%d,%d,%d,%d,%d," ZBX_FS_DBL ","
				ZBX_FS_DBL ",%d,%d,%s,%s,%d);\n",
				hst_graphid, name_esc, width, height, yaxismin, yaxismax,
				graphid, (int)show_work_period, (int)show_triggers,
				(int)graphtype, (int)show_legend, (int)show_3d,
				percent_left, percent_right, (int)ymin_type, (int)ymax_type,
				DBsql_id_ins(ymin_itemid), DBsql_id_ins(ymax_itemid), (int)flags);

		hst_gitemid = DBget_maxid_num("graphs_items", gitems_num);

		for (i = 0; i < gitems_num; i++)
		{
			color_esc = DBdyn_escape_string(gitems[i].color);

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
					"insert into graphs_items (gitemid,graphid,itemid,drawtype,"
					"sortorder,color,yaxisside,calc_fnc,type)"
					" values (" ZBX_FS_UI64 "," ZBX_FS_UI64 "," ZBX_FS_UI64
					",%d,%d,'%s',%d,%d,%d);\n",
					hst_gitemid, hst_graphid, gitems[i].itemid,
					gitems[i].drawtype, gitems[i].sortorder, color_esc,
					gitems[i].yaxisside, gitems[i].calc_fnc, gitems[i].type);
			hst_gitemid++;

			zbx_free(color_esc);
		}
	}

	zbx_free(name_esc);

	DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (sql_offset > 16)	/* In ORACLE always present begin..end; */
		DBexecute("%s", sql);

	zbx_free(gitems);
	zbx_free(chd_gitems);
	zbx_free(sql);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: DBcopy_template_graphs                                           *
 *                                                                            *
 * Purpose: copy graphs from template to host                                 *
 *                                                                            *
 * Parameters: hostid      - [IN] host identificator from database            *
 *             templateids - [IN] array of template IDs                       *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget to sync the code with PHP !!!                   *
 *                                                                            *
 ******************************************************************************/
static void	DBcopy_template_graphs(zbx_uint64_t hostid, const zbx_vector_uint64_t *templateids)
{
	const char	*__function_name = "DBcopy_template_graphs";
	char		*sql = NULL;
	size_t		sql_alloc = 512, sql_offset = 0;
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	graphid, ymin_itemid, ymax_itemid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	sql = zbx_malloc(sql, sql_alloc);

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select distinct g.graphid,g.name,g.width,g.height,g.yaxismin,"
				"g.yaxismax,g.show_work_period,g.show_triggers,"
				"g.graphtype,g.show_legend,g.show_3d,g.percent_left,"
				"g.percent_right,g.ymin_type,g.ymax_type,g.ymin_itemid,"
				"g.ymax_itemid,g.flags"
			" from graphs g,graphs_items gi,items i"
			" where g.graphid=gi.graphid"
				" and gi.itemid=i.itemid"
				" and");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "i.hostid", templateids->values, templateids->values_num);

	result = DBselect("%s", sql);

	zbx_free(sql);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(graphid, row[0]);
		ZBX_DBROW2UINT64(ymin_itemid, row[15]);
		ZBX_DBROW2UINT64(ymax_itemid, row[16]);

		DBcopy_graph_to_host(hostid, graphid,
				row[1],				/* name */
				atoi(row[2]),			/* width */
				atoi(row[3]),			/* height */
				atof(row[4]),			/* yaxismin */
				atof(row[5]),			/* yaxismax */
				(unsigned char)atoi(row[6]),	/* show_work_period */
				(unsigned char)atoi(row[7]),	/* show_triggers */
				(unsigned char)atoi(row[8]),	/* graphtype */
				(unsigned char)atoi(row[9]),	/* show_legend */
				(unsigned char)atoi(row[10]),	/* show_3d */
				atof(row[11]),			/* percent_left */
				atof(row[12]),			/* percent_right */
				(unsigned char)atoi(row[13]),	/* ymin_type */
				(unsigned char)atoi(row[14]),	/* ymax_type */
				ymin_itemid,
				ymax_itemid,
				(unsigned char)atoi(row[17]));	/* flags */
	}
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
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
	char			*name;
	char			*url;
	char			*posts;
	char			*required;
	char			*status_codes;
	zbx_vector_ptr_t	httpstepitems;
	int			no;
	int			timeout;
	char			*variables;
}
httpstep_t;

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
	zbx_uint64_t		httptestid;
	zbx_uint64_t		t_applicationid;
	zbx_uint64_t		h_applicationid;
	char			*name;
	char			*variables;
	char			*agent;
	char			*http_user;
	char			*http_password;
	char			*http_proxy;
	zbx_vector_ptr_t	httpsteps;
	zbx_vector_ptr_t	httptestitems;
	int			delay;
	int			retries;
	unsigned char		status;
	unsigned char		authentication;
}
httptest_t;

/******************************************************************************
 *                                                                            *
 * Function: DBget_httptests                                                  *
 *                                                                            *
 * Purpose: helper function for DCmass_add_history()                          *
 *                                                                            *
 ******************************************************************************/
static void	DBget_httptests(zbx_uint64_t hostid, const zbx_vector_uint64_t *templateids, zbx_vector_ptr_t *httptests)
{
	const char		*__function_name = "DBget_httptests";

	char			*sql = NULL;
	size_t			sql_alloc = 512, sql_offset = 0;
	DB_RESULT		result;
	DB_ROW			row;
	httptest_t		*httptest;
	httpstep_t		*httpstep;
	httptestitem_t		*httptestitem;
	httpstepitem_t		*httpstepitem;
	zbx_vector_uint64_t	httptestids;	/* the list of web scenarios which should be added to a host */
	zbx_vector_uint64_t	applications;
	zbx_vector_uint64_t	items;
	zbx_uint64_t		httptestid, httpstepid, applicationid, itemid;
	int			i, j, k;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_uint64_create(&httptestids);
	zbx_vector_uint64_create(&applications);
	zbx_vector_uint64_create(&items);

	sql = zbx_malloc(sql, sql_alloc);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select t.httptestid,t.name,t.applicationid,t.delay,t.status,t.variables,t.agent,"
				"t.authentication,t.http_user,t.http_password,t.http_proxy,t.retries,h.httptestid"
			" from httptest t"
				" left join httptest h"
					" on h.hostid=" ZBX_FS_UI64
						" and h.name=t.name"
			" where", hostid);
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "t.hostid", templateids->values, templateids->values_num);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " order by t.httptestid");

	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		httptest = zbx_calloc(NULL, 1, sizeof(httptest_t));

		ZBX_STR2UINT64(httptest->templateid, row[0]);
		ZBX_DBROW2UINT64(httptest->httptestid, row[12]);
		zbx_vector_ptr_create(&httptest->httpsteps);
		zbx_vector_ptr_create(&httptest->httptestitems);

		zbx_vector_ptr_append(httptests, httptest);

		if (0 == httptest->httptestid)
		{
			httptest->name = zbx_strdup(NULL, row[1]);
			ZBX_DBROW2UINT64(httptest->t_applicationid, row[2]);
			httptest->delay = atoi(row[3]);
			httptest->status = (unsigned char)atoi(row[4]);
			httptest->variables = zbx_strdup(NULL, row[5]);
			httptest->agent = zbx_strdup(NULL, row[6]);
			httptest->authentication = (unsigned char)atoi(row[7]);
			httptest->http_user = zbx_strdup(NULL, row[8]);
			httptest->http_password = zbx_strdup(NULL, row[9]);
			httptest->http_proxy = zbx_strdup(NULL, row[10]);
			httptest->retries = atoi(row[11]);

			zbx_vector_uint64_append(&httptestids, httptest->templateid);

			if (0 != httptest->t_applicationid)
				zbx_vector_uint64_append(&applications, httptest->t_applicationid);
		}
	}
	DBfree_result(result);

	/* web scenario steps */
	if (0 != httptestids.values_num)
	{
		httptest = NULL;

		sql_offset = 0;
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				"select httpstepid,httptestid,name,no,url,timeout,posts,required,status_codes,variables"
				" from httpstep"
				" where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "httptestid",
				httptestids.values, httptestids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " order by httptestid");

		result = DBselect("%s", sql);

		while (NULL != (row = DBfetch(result)))
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

			httpstep = zbx_malloc(NULL, sizeof(httptest_t));

			ZBX_STR2UINT64(httpstep->httpstepid, row[0]);
			httpstep->name = zbx_strdup(NULL, row[2]);
			httpstep->no = atoi(row[3]);
			httpstep->url = zbx_strdup(NULL, row[4]);
			httpstep->timeout = atoi(row[5]);
			httpstep->posts = zbx_strdup(NULL, row[6]);
			httpstep->required = zbx_strdup(NULL, row[7]);
			httpstep->status_codes = zbx_strdup(NULL, row[8]);
			httpstep->variables = zbx_strdup(NULL, row[9]);
			zbx_vector_ptr_create(&httpstep->httpstepitems);

			zbx_vector_ptr_append(&httptest->httpsteps, httpstep);
		}
		DBfree_result(result);

		for (i = 0; i < httptests->values_num; i++)
		{
			httptest = (httptest_t *)httptests->values[i];
			zbx_vector_ptr_sort(&httptest->httpsteps, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);
		}
	}

	/* applications */
	if (0 != applications.values_num)
	{
		zbx_vector_uint64_sort(&applications, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_vector_uint64_uniq(&applications, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		sql_offset = 0;
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"select t.applicationid,h.applicationid"
				" from applications t"
					" join applications h"
						" on h.hostid=" ZBX_FS_UI64
							" and h.name=t.name"
				" where", hostid);
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "t.applicationid",
				applications.values, applications.values_num);

		result = DBselect("%s", sql);

		while (NULL != (row = DBfetch(result)))
		{
			ZBX_STR2UINT64(applicationid, row[0]);

			for (i = 0; i < httptests->values_num; i++)
			{
				httptest = (httptest_t *)httptests->values[i];

				if (httptest->t_applicationid == applicationid)
					ZBX_STR2UINT64(httptest->h_applicationid, row[1]);
			}
		}
		DBfree_result(result);
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
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "httptestid",
				httptestids.values, httptestids.values_num);

		result = DBselect("%s", sql);

		while (NULL != (row = DBfetch(result)))
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

			httptestitem = zbx_calloc(NULL, 1, sizeof(httptestitem_t));

			ZBX_STR2UINT64(httptestitem->t_itemid, row[1]);
			httptestitem->type = (unsigned char)atoi(row[2]);

			zbx_vector_ptr_append(&httptest->httptestitems, httptestitem);

			zbx_vector_uint64_append(&items, httptestitem->t_itemid);
		}
		DBfree_result(result);
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
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "hs.httptestid",
				httptestids.values, httptestids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
							" and hs.httpstepid=hsi.httpstepid"
				" order by hs.httptestid,hsi.httpstepid");

		result = DBselect("%s", sql);

		while (NULL != (row = DBfetch(result)))
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

			httpstepitem = zbx_calloc(NULL, 1, sizeof(httpstepitem_t));

			ZBX_STR2UINT64(httpstepitem->t_itemid, row[2]);
			httpstepitem->type = (unsigned char)atoi(row[3]);

			zbx_vector_ptr_append(&httpstep->httpstepitems, httpstepitem);

			zbx_vector_uint64_append(&items, httpstepitem->t_itemid);
		}
		DBfree_result(result);
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
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "t.itemid",
				items.values, items.values_num);

		result = DBselect("%s", sql);

		while (NULL != (row = DBfetch(result)))
		{
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
		DBfree_result(result);
	}

	zbx_free(sql);

	zbx_vector_uint64_destroy(&items);
	zbx_vector_uint64_destroy(&applications);
	zbx_vector_uint64_destroy(&httptestids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: DBsave_httptests                                                 *
 *                                                                            *
 * Purpose: helper function for DCmass_add_history()                          *
 *                                                                            *
 ******************************************************************************/
static void	DBsave_httptests(zbx_uint64_t hostid, zbx_vector_ptr_t *httptests)
{
	char		*sql = NULL;
	size_t		sql_alloc = 512, sql_offset = 0;
	httptest_t	*httptest;
	httpstep_t	*httpstep;
	httptestitem_t	*httptestitem;
	httpstepitem_t	*httpstepitem;
	zbx_uint64_t	httptestid = 0, httpstepid = 0, httptestitemid = 0, httpstepitemid = 0;
	int		i, j, k, num_httptests = 0, num_httpsteps = 0, num_httptestitems = 0, num_httpstepitems = 0;
	zbx_db_insert_t	db_insert_htest, db_insert_hstep, db_insert_htitem, db_insert_hsitem;

	if (0 == httptests->values_num)
		return;

	for (i = 0; i < httptests->values_num; i++)
	{
		httptest = (httptest_t *)httptests->values[i];

		if (0 == httptest->httptestid)
		{
			num_httptests++;
			num_httpsteps += httptest->httpsteps.values_num;
			num_httptestitems += httptest->httptestitems.values_num;

			for (j = 0; j < httptest->httpsteps.values_num; j++)
			{
				httpstep = (httpstep_t *)httptest->httpsteps.values[j];

				num_httpstepitems += httpstep->httpstepitems.values_num;
			}
		}
	}

	if (0 != num_httptests)
	{
		httptestid = DBget_maxid_num("httptest", num_httptests);

		zbx_db_insert_prepare(&db_insert_htest, "httptest", "httptestid", "name", "applicationid", "delay",
				"status", "variables", "agent", "authentication", "http_user", "http_password",
				"http_proxy", "retries", "hostid", "templateid", NULL);
	}

	if (httptests->values_num != num_httptests)
		sql = zbx_malloc(sql, sql_alloc);

	if (0 != num_httpsteps)
	{
		httpstepid = DBget_maxid_num("httpstep", num_httpsteps);

		zbx_db_insert_prepare(&db_insert_hstep, "httpstep", "httpstepid", "httptestid", "name", "no", "url",
				"timeout", "posts", "required", "status_codes", "variables", NULL);
	}

	if (0 != num_httptestitems)
	{
		httptestitemid = DBget_maxid_num("httptestitem", num_httptestitems);

		zbx_db_insert_prepare(&db_insert_htitem, "httptestitem", "httptestitemid", "httptestid", "itemid",
				"type", NULL);
	}

	if (0 != num_httpstepitems)
	{
		httpstepitemid = DBget_maxid_num("httpstepitem", num_httpstepitems);

		zbx_db_insert_prepare(&db_insert_hsitem, "httpstepitem", "httpstepitemid", "httpstepid", "itemid",
				"type", NULL);
	}

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	for (i = 0; i < httptests->values_num; i++)
	{
		httptest = (httptest_t *)httptests->values[i];

		if (0 == httptest->httptestid)
		{
			httptest->httptestid = httptestid++;

			zbx_db_insert_add_values(&db_insert_htest, httptest->httptestid, httptest->name,
					httptest->h_applicationid, httptest->delay, (int)httptest->status,
					httptest->variables, httptest->agent, (int)httptest->authentication,
					httptest->http_user, httptest->http_password, httptest->http_proxy,
					httptest->retries, hostid, httptest->templateid);

			for (j = 0; j < httptest->httpsteps.values_num; j++)
			{
				httpstep = (httpstep_t *)httptest->httpsteps.values[j];

				zbx_db_insert_add_values(&db_insert_hstep, httpstepid, httptest->httptestid,
						httpstep->name, httpstep->no, httpstep->url, httpstep->timeout,
						httpstep->posts, httpstep->required, httpstep->status_codes,
						httpstep->variables);

				for (k = 0; k < httpstep->httpstepitems.values_num; k++)
				{
					httpstepitem = (httpstepitem_t *)httpstep->httpstepitems.values[k];

					zbx_db_insert_add_values(&db_insert_hsitem,  httpstepitemid, httpstepid,
							httpstepitem->h_itemid, (int)httpstepitem->type);

					httpstepitemid++;
				}

				httpstepid++;
			}

			for (j = 0; j < httptest->httptestitems.values_num; j++)
			{
				httptestitem = (httptestitem_t *)httptest->httptestitems.values[j];

				zbx_db_insert_add_values(&db_insert_htitem, httptestitemid, httptest->httptestid,
						httptestitem->h_itemid, (int)httptestitem->type);

				httptestitemid++;
			}
		}
		else
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
					"update httptest"
					" set templateid=" ZBX_FS_UI64
					" where httptestid=" ZBX_FS_UI64 ";\n",
					httptest->templateid, httptest->httptestid);
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

	DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (16 < sql_offset)
		DBexecute("%s", sql);

	zbx_free(sql);
}

/******************************************************************************
 *                                                                            *
 * Function: clean_httptests                                                  *
 *                                                                            *
 * Purpose: helper function for DCmass_add_history()                          *
 *                                                                            *
 ******************************************************************************/
static void	clean_httptests(zbx_vector_ptr_t *httptests)
{
	httptest_t	*httptest;
	httpstep_t	*httpstep;
	int		i, j, k;

	for (i = 0; i < httptests->values_num; i++)
	{
		httptest = (httptest_t *)httptests->values[i];

		zbx_free(httptest->http_proxy);
		zbx_free(httptest->http_password);
		zbx_free(httptest->http_user);
		zbx_free(httptest->agent);
		zbx_free(httptest->variables);
		zbx_free(httptest->name);

		for (j = 0; j < httptest->httpsteps.values_num; j++)
		{
			httpstep = (httpstep_t *)httptest->httpsteps.values[j];

			zbx_free(httpstep->status_codes);
			zbx_free(httpstep->required);
			zbx_free(httpstep->posts);
			zbx_free(httpstep->url);
			zbx_free(httpstep->name);
			zbx_free(httpstep->variables);

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
 * Function: DBcopy_template_httptests                                        *
 *                                                                            *
 * Purpose: copy web scenarios from template to host                          *
 *                                                                            *
 * Parameters: hostid      - [IN] host identificator from database            *
 *             templateids - [IN] array of template IDs                       *
 *                                                                            *
 ******************************************************************************/
static void	DBcopy_template_httptests(zbx_uint64_t hostid, const zbx_vector_uint64_t *templateids)
{
	const char		*__function_name = "DBcopy_template_httptests";
	zbx_vector_ptr_t	httptests;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_ptr_create(&httptests);

	DBget_httptests(hostid, templateids, &httptests);
	DBsave_httptests(hostid, &httptests);

	clean_httptests(&httptests);
	zbx_vector_ptr_destroy(&httptests);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: DBcopy_template_elements                                         *
 *                                                                            *
 * Purpose: copy elements from specified template                             *
 *                                                                            *
 * Parameters: hostid          - [IN] host identificator from database        *
 *             lnk_templateids - [IN] array of template IDs                   *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *                                                                            *
 ******************************************************************************/
int	DBcopy_template_elements(zbx_uint64_t hostid, zbx_vector_uint64_t *lnk_templateids)
{
	const char		*__function_name = "DBcopy_template_elements";
	zbx_vector_uint64_t	templateids;
	zbx_uint64_t		hosttemplateid;
	int			i, res = SUCCEED;
	char			error[MAX_STRING_LEN], *template_names;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

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

	if (SUCCEED != (res = validate_linked_templates(&templateids, error, sizeof(error))))
	{
		template_names = get_template_names(lnk_templateids);

		zabbix_log(LOG_LEVEL_WARNING, "cannot link template(s) %s to host \"%s\": %s",
				template_names, zbx_host_string(hostid), error);

		zbx_free(template_names);
		goto clean;
	}

	if (SUCCEED != (res = validate_host(hostid, lnk_templateids, error, sizeof(error))))
	{
		template_names = get_template_names(lnk_templateids);

		zabbix_log(LOG_LEVEL_WARNING, "cannot link template(s) %s to host \"%s\": %s",
				template_names, zbx_host_string(hostid), error);

		zbx_free(template_names);
		goto clean;
	}

	hosttemplateid = DBget_maxid_num("hosts_templates", lnk_templateids->values_num);

	for (i = 0; i < lnk_templateids->values_num; i++)
	{
		DBexecute("insert into hosts_templates (hosttemplateid,hostid,templateid)"
				" values (" ZBX_FS_UI64 "," ZBX_FS_UI64 "," ZBX_FS_UI64 ")",
				hosttemplateid++, hostid, lnk_templateids->values[i]);
	}

	DBcopy_template_applications(hostid, lnk_templateids);
	DBcopy_template_items(hostid, lnk_templateids);
	DBcopy_template_application_prototypes(hostid, lnk_templateids);
	DBcopy_template_item_application_prototypes(hostid, lnk_templateids);
	DBcopy_template_host_prototypes(hostid, lnk_templateids);
	if (SUCCEED == (res = DBcopy_template_triggers(hostid, lnk_templateids)))
	{
		DBcopy_template_graphs(hostid, lnk_templateids);
		DBcopy_template_httptests(hostid, lnk_templateids);
	}
clean:
	zbx_vector_uint64_destroy(&templateids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(res));

	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: DBdelete_hosts                                                   *
 *                                                                            *
 * Purpose: delete hosts from database with all elements                      *
 *                                                                            *
 * Parameters: hostids - [IN] host identificators from database               *
 *                                                                            *
 ******************************************************************************/
void	DBdelete_hosts(zbx_vector_uint64_t *hostids)
{
	const char		*__function_name = "DBdelete_hosts";

	zbx_vector_uint64_t	itemids, httptestids, selementids;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset;
	int			i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (SUCCEED != DBlock_hostids(hostids))
		goto out;

	zbx_vector_uint64_create(&httptestids);
	zbx_vector_uint64_create(&selementids);

	/* delete web tests */

	sql_offset = 0;
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select httptestid"
			" from httptest"
			" where");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid", hostids->values, hostids->values_num);

	DBselect_uint64(sql, &httptestids);

	DBdelete_httptests(&httptestids);

	zbx_vector_uint64_destroy(&httptestids);

	/* delete items -> triggers -> graphs */

	zbx_vector_uint64_create(&itemids);

	sql_offset = 0;
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select itemid"
			" from items"
			" where");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid", hostids->values, hostids->values_num);

	DBselect_uint64(sql, &itemids);

	DBdelete_items(&itemids);

	zbx_vector_uint64_destroy(&itemids);

	sql_offset = 0;
	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	/* delete host from maps */
	DBget_sysmapelements_by_element_type_ids(&selementids, SYSMAP_ELEMENT_TYPE_HOST, hostids);
	if (0 != selementids.values_num)
	{
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from sysmaps_elements where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "selementid", selementids.values,
				selementids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");
	}

	/* delete action conditions */
	for (i = 0; i < hostids->values_num; i++)
		DBdelete_action_conditions(CONDITION_TYPE_HOST, hostids->values[i]);

	/* delete host */
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from hosts where");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid", hostids->values, hostids->values_num);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");

	DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	DBexecute("%s", sql);

	zbx_free(sql);

	zbx_vector_uint64_destroy(&selementids);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: DBadd_interface                                                  *
 *                                                                            *
 * Purpose: add new interface to specified host                               *
 *                                                                            *
 * Parameters: hostid - [IN] host identificator from database                 *
 *             type   - [IN] new interface type                               *
 *             useip  - [IN] how to connect to the host 0/1 - DNS/IP          *
 *             ip     - [IN] IP address                                       *
 *             dns    - [IN] DNS address                                      *
 *             port   - [IN] port                                             *
 *                                                                            *
 * Return value: upon successful completion return interface identificator    *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
zbx_uint64_t	DBadd_interface(zbx_uint64_t hostid, unsigned char type,
		unsigned char useip, const char *ip, const char *dns, unsigned short port)
{
	const char	*__function_name = "DBadd_interface";

	DB_RESULT	result;
	DB_ROW		row;
	char		*ip_esc, *dns_esc, *tmp = NULL;
	zbx_uint64_t	interfaceid = 0;
	unsigned char	main_ = 1, db_main, db_useip;
	unsigned short	db_port;
	const char	*db_ip, *db_dns;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	result = DBselect(
			"select interfaceid,useip,ip,dns,port,main"
			" from interface"
			" where hostid=" ZBX_FS_UI64
				" and type=%d",
			hostid, (int)type);

	while (NULL != (row = DBfetch(result)))
	{
		db_useip = (unsigned char)atoi(row[1]);
		db_ip = row[2];
		db_dns = row[3];
		db_main = (unsigned char)atoi(row[5]);
		if (1 == db_main)
			main_ = 0;

		if (db_useip != useip)
			continue;

		if (useip && 0 != strcmp(db_ip, ip))
			continue;

		if (!useip && 0 != strcmp(db_dns, dns))
			continue;

		zbx_free(tmp);
		tmp = strdup(row[4]);
		substitute_simple_macros(NULL, NULL, NULL, NULL, &hostid, NULL, NULL,  NULL, NULL,
				&tmp, MACRO_TYPE_COMMON, NULL, 0);
		if (FAIL == is_ushort(tmp, &db_port) || db_port != port)
			continue;

		ZBX_STR2UINT64(interfaceid, row[0]);
		break;
	}
	DBfree_result(result);

	zbx_free(tmp);

	if (0 != interfaceid)
		goto out;

	ip_esc = DBdyn_escape_string_len(ip, INTERFACE_IP_LEN);
	dns_esc = DBdyn_escape_string_len(dns, INTERFACE_DNS_LEN);

	interfaceid = DBget_maxid("interface");

	DBexecute("insert into interface"
			" (interfaceid,hostid,main,type,useip,ip,dns,port)"
		" values"
			" (" ZBX_FS_UI64 "," ZBX_FS_UI64 ",%d,%d,%d,'%s','%s',%d)",
		interfaceid, hostid, (int)main_, (int)type, (int)useip, ip_esc, dns_esc, (int)port);

	zbx_free(dns_esc);
	zbx_free(ip_esc);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():" ZBX_FS_UI64, __function_name, interfaceid);

	return interfaceid;
}

/******************************************************************************
 *                                                                            *
 * Function: DBdelete_groups_validate                                         *
 *                                                                            *
 * Purpose: removes the groupids from the list which cannot be deleted        *
 *          (host or template can remain without groups or it's an internal   *
 *          group or it's used by a host prototype)                           *
 *                                                                            *
 ******************************************************************************/
static void	DBdelete_groups_validate(zbx_vector_uint64_t *groupids)
{
	DB_RESULT		result;
	DB_ROW			row;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_vector_uint64_t	hostids;
	zbx_uint64_t		groupid;
	int			index, internal;

	if (0 == groupids->values_num)
		return;

	zbx_vector_uint64_create(&hostids);

	/* select of the list of hosts which remain without groups */

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select hg.hostid"
			" from hosts_groups hg"
			" where");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "hg.groupid", groupids->values, groupids->values_num);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			" and not exists ("
				"select null"
				" from hosts_groups hg2"
				" where hg.hostid=hg2.hostid"
					" and not");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "hg2.groupid", groupids->values, groupids->values_num);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			")");

	DBselect_uint64(sql, &hostids);

	/* select of the list of groups which cannot be deleted */

	sql_offset = 0;
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select g.groupid,g.internal,g.name"
			" from groups g"
			" where");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "g.groupid", groupids->values, groupids->values_num);
	if (0 < hostids.values_num)
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				" and (g.internal=%d"
					" or exists ("
						"select null"
						" from hosts_groups hg"
						" where g.groupid=hg.groupid"
							" and",
				ZBX_INTERNAL_GROUP);
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "hg.hostid", hostids.values, hostids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "))");
	}
	else
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " and g.internal=%d", ZBX_INTERNAL_GROUP);

	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(groupid, row[0]);
		internal = atoi(row[1]);

		if (FAIL != (index = zbx_vector_uint64_bsearch(groupids, groupid, ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
			zbx_vector_uint64_remove(groupids, index);

		if (ZBX_INTERNAL_GROUP == internal)
		{
			zabbix_log(LOG_LEVEL_WARNING, "host group \"%s\" is internal and cannot be deleted", row[2]);
		}
		else
		{
			zabbix_log(LOG_LEVEL_WARNING, "host group \"%s\" cannot be deleted,"
					" because some hosts or templates depend on it", row[2]);
		}
	}
	DBfree_result(result);

	/* check if groups is used in the groups prototypes */

	if (0 != groupids->values_num)
	{
		sql_offset = 0;
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				"select g.groupid,g.name"
				" from groups g"
				" where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "g.groupid",
				groupids->values, groupids->values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
					" and exists ("
						"select null"
						" from group_prototype gp"
						" where g.groupid=gp.groupid"
					")");

		result = DBselect("%s", sql);

		while (NULL != (row = DBfetch(result)))
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
		DBfree_result(result);
	}

	zbx_vector_uint64_destroy(&hostids);
	zbx_free(sql);
}

/******************************************************************************
 *                                                                            *
 * Function: DBdelete_groups                                                  *
 *                                                                            *
 * Purpose: delete host groups from database                                  *
 *                                                                            *
 * Parameters: groupids - [IN] array of group identificators from database    *
 *                                                                            *
 ******************************************************************************/
void	DBdelete_groups(zbx_vector_uint64_t *groupids)
{
	const char		*__function_name = "DBdelete_groups";

	char			*sql = NULL;
	size_t			sql_alloc = 256, sql_offset = 0;
	int			i;
	zbx_vector_uint64_t	profileids, screen_itemids, selementids;
	zbx_uint64_t		resource_types_delete[] = {SCREEN_RESOURCE_DATA_OVERVIEW,
						SCREEN_RESOURCE_TRIGGERS_OVERVIEW};
	zbx_uint64_t		resource_types_update[] = {SCREEN_RESOURCE_HOSTS_INFO, SCREEN_RESOURCE_TRIGGERS_INFO,
						SCREEN_RESOURCE_HOSTGROUP_TRIGGERS, SCREEN_RESOURCE_HOST_TRIGGERS};
	const char		*profile_idxs[] = {"web.dashconf.groups.groupids", "web.dashconf.groups.hide.groupids"};

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() values_num:%d", __function_name, groupids->values_num);

	DBdelete_groups_validate(groupids);

	if (0 == groupids->values_num)
		goto out;

	for (i = 0; i < groupids->values_num; i++)
		DBdelete_action_conditions(CONDITION_TYPE_HOST_GROUP, groupids->values[i]);

	sql = zbx_malloc(sql, sql_alloc);

	zbx_vector_uint64_create(&profileids);
	zbx_vector_uint64_create(&screen_itemids);
	zbx_vector_uint64_create(&selementids);

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	/* delete sysmaps_elements */
	DBget_sysmapelements_by_element_type_ids(&selementids, SYSMAP_ELEMENT_TYPE_HOST_GROUP, groupids);
	if (0 != selementids.values_num)
	{
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from sysmaps_elements where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "selementid", selementids.values,
				selementids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");
	}

	/* delete screens_items (host group is mandatory for this elements) */
	DBget_screenitems_by_resource_types_ids(&screen_itemids, resource_types_delete, ARRSIZE(resource_types_delete),
			groupids);
	if (0 != screen_itemids.values_num)
	{
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from screens_items where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "screenitemid", screen_itemids.values,
				screen_itemids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");
	}

	/* update screens_items (host group isn't mandatory for this elements) */
	zbx_vector_uint64_clear(&screen_itemids);
	DBget_screenitems_by_resource_types_ids(&screen_itemids, resource_types_update, ARRSIZE(resource_types_update),
			groupids);

	if (0 != screen_itemids.values_num)
	{
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "update screens_items set resourceid=0 where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "screenitemid", screen_itemids.values,
				screen_itemids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");
	}

	DBget_profiles_by_source_idxs_values(&profileids, NULL, profile_idxs, ARRSIZE(profile_idxs), groupids);
	if (0 != profileids.values_num)
	{
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from profiles where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "profileid", profileids.values,
				profileids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");
	}

	/* groups */
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from groups where");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "groupid", groupids->values, groupids->values_num);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");

	DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	DBexecute("%s", sql);

	zbx_vector_uint64_destroy(&selementids);
	zbx_vector_uint64_destroy(&screen_itemids);
	zbx_vector_uint64_destroy(&profileids);

	zbx_free(sql);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: DBadd_host_inventory                                             *
 *                                                                            *
 * Purpose: adds host inventory to the host                                   *
 *                                                                            *
 * Parameters: hostid         - [IN] host identifier                          *
 *             inventory_mode - [IN] the host inventory mode                  *
 *                                                                            *
 ******************************************************************************/
void	DBadd_host_inventory(zbx_uint64_t hostid, int inventory_mode)
{
	zbx_db_insert_t	db_insert;

	zbx_db_insert_prepare(&db_insert, "host_inventory", "hostid", "inventory_mode", NULL);
	zbx_db_insert_add_values(&db_insert, hostid, inventory_mode);
	zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);
}

/******************************************************************************
 *                                                                            *
 * Function: DBset_host_inventory                                             *
 *                                                                            *
 * Purpose: sets host inventory mode for the specified host                   *
 *                                                                            *
 * Parameters: hostid         - [IN] host identifier                          *
 *             inventory_mode - [IN] the host inventory mode                  *
 *                                                                            *
 * Comments: The host_inventory table record is created if absent.            *
 *                                                                            *
 *           This function does not allow disabling host inventory - only     *
 *           setting manual or automatic host inventory mode is supported.    *
 *                                                                            *
 ******************************************************************************/
void	DBset_host_inventory(zbx_uint64_t hostid, int inventory_mode)
{
	DB_RESULT	result;
	DB_ROW		row;

	result = DBselect("select inventory_mode from host_inventory where hostid=" ZBX_FS_UI64, hostid);

	if (NULL == (row = DBfetch(result)))
	{
		DBadd_host_inventory(hostid, inventory_mode);
	}
	else if (inventory_mode != atoi(row[0]))
	{
		DBexecute("update host_inventory set inventory_mode=%d where hostid=" ZBX_FS_UI64, inventory_mode,
				hostid);
	}

	DBfree_result(result);
}
