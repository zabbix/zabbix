/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
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

/******************************************************************************
 *                                                                            *
 * Function: validate_linked_templates                                        *
 *                                                                            *
 * Description: Check of collisions between linked templates                  *
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
static int	validate_linked_templates(zbx_vector_uint64_t *templateids, char *error, size_t max_error_len)
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

	/* applications */
	if (1 < templateids->values_num)
	{
		sql_offset = 0;
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				"select name,count(*)"
				" from applications"
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
					"template with application \"%s\" already linked to the host", row[0]);
		}
		DBfree_result(result);
	}

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
			zbx_snprintf(error, max_error_len,
					"template with item key \"%s\" already linked to the host", row[0]);
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
		zbx_uint64_t		graphid;

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

		result = DBselect("%s", sql);

		while (NULL != (row = DBfetch(result)))
		{
			ZBX_STR2UINT64(graphid, row[0]);
			zbx_vector_uint64_append(&graphids, graphid);
		}
		DBfree_result(result);

		/* check for names */
		if (0 != graphids.values_num)
		{
			zbx_vector_uint64_sort(&graphids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

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
static int	validate_inventory_links(zbx_uint64_t hostid, zbx_vector_uint64_t *templateids,
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
static int	validate_httptests(zbx_uint64_t hostid, zbx_vector_uint64_t *templateids,
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

void	DBget_graphitems(const char *sql, ZBX_GRAPH_ITEMS **gitems, size_t *gitems_alloc, size_t *gitems_num)
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
	int		res = SUCCEED;
	zbx_uint64_t	graphid, interfaceids[4];
	unsigned char	t_flags, h_flags, type;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (SUCCEED != (res = validate_inventory_links(hostid, templateids, error, max_error_len)))
		goto out;

	if (SUCCEED != (res = validate_httptests(hostid, templateids, error, max_error_len)))
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

	while (SUCCEED == res && NULL != (trow = DBfetch(tresult)))
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
				res = FAIL;
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
				res = FAIL;
				zbx_snprintf(error, max_error_len,
						"graph \"%s\" already exists on the host (items are not identical)",
						trow[1]);
				break;
			}
		}
		DBfree_result(hresult);
	}
	DBfree_result(tresult);

	if (SUCCEED == res)
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
			res = FAIL;
			zbx_snprintf(error, max_error_len,
					"item prototype and real item \"%s\" have the same key", trow[0]);
		}
		DBfree_result(tresult);
	}

	/* interfaces */
	if (SUCCEED == res)
	{
		memset(&interfaceids, 0, sizeof(interfaceids));

		tresult = DBselect(
				"select type,interfaceid"
				" from interface"
				" where hostid=" ZBX_FS_UI64
					" and type in (%d,%d,%d,%d)"
					" and main=1"
					DB_NODE,
				hostid, INTERFACE_TYPE_AGENT, INTERFACE_TYPE_SNMP,
				INTERFACE_TYPE_IPMI, INTERFACE_TYPE_JMX, DBnode_local("interfaceid"));

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
				" where type not in (%d,%d,%d,%d,%d,%d)"
					" and",
				ITEM_TYPE_TRAPPER, ITEM_TYPE_INTERNAL, ITEM_TYPE_ZABBIX_ACTIVE,
				ITEM_TYPE_AGGREGATE, ITEM_TYPE_DB_MONITOR, ITEM_TYPE_CALCULATED);
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid",
				templateids->values, templateids->values_num);

		tresult = DBselect("%s", sql);

		while (SUCCEED == res && NULL != (trow = DBfetch(tresult)))
		{
			type = (unsigned char)atoi(trow[0]);
			type = get_interface_type_by_item_type(type);

			if (INTERFACE_TYPE_ANY != type && 0 == interfaceids[type - 1])
			{
				res = FAIL;
				zbx_snprintf(error, max_error_len, "cannot find \"%s\" host interface",
						zbx_interface_type_string((zbx_interface_type_t)type));
			}
		}
		DBfree_result(tresult);
	}

	zbx_free(sql);
	zbx_free(gitems);
	zbx_free(chd_gitems);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "In %s():%s", __function_name, zbx_result_string(res));

	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: DBclear_parents_from_trigger                                     *
 *                                                                            *
 * Purpose: removes any links between trigger and service if service          *
 *          is not leaf (treenode)                                            *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget to sync the code with PHP !!!                   *
 *                                                                            *
 ******************************************************************************/
static void	DBclear_parents_from_trigger()
{
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	serviceid;

	result = DBselect("select s.serviceid"
				" from services s,services_links sl"
				" where s.serviceid=sl.serviceupid"
					" and s.triggerid is not null"
				" group by s.serviceid");

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(serviceid, row[0]);

		DBexecute("update services"
				" set triggerid=null"
				" where serviceid=" ZBX_FS_UI64, serviceid);
	}
	DBfree_result(result);
}

/******************************************************************************
 *                                                                            *
 * Function: DBget_service_status                                             *
 *                                                                            *
 * Purpose: retrieve true status                                              *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget to sync the code with PHP !!!                   *
 *                                                                            *
 ******************************************************************************/
static int	DBget_service_status(zbx_uint64_t serviceid, int algorithm, zbx_uint64_t triggerid)
{
	DB_RESULT	result;
	DB_ROW		row;

	int		status = 0;
	char		sort_order[MAX_STRING_LEN];
	char		sql[MAX_STRING_LEN];

	if (0 != triggerid)
	{
		result = DBselect("select priority"
					" from triggers"
					" where triggerid=" ZBX_FS_UI64
						" and status=0"
						" and value=%d",
					triggerid,
					TRIGGER_VALUE_TRUE);
		row = DBfetch(result);
		if (NULL != row && SUCCEED != DBis_null(row[0]))
		{
			status = atoi(row[0]);
		}
		DBfree_result(result);
	}

	if (SERVICE_ALGORITHM_MAX == algorithm || SERVICE_ALGORITHM_MIN == algorithm)
	{
		zbx_strlcpy(sort_order, (SERVICE_ALGORITHM_MAX == algorithm ? "desc" : "asc"), sizeof(sort_order));

		zbx_snprintf(sql, sizeof(sql), "select s.status"
						" from services s,services_links l"
						" where l.serviceupid=" ZBX_FS_UI64
							" and s.serviceid=l.servicedownid"
						" order by s.status %s",
						serviceid,
						sort_order);

		result = DBselectN(sql, 1);
		row = DBfetch(result);
		if (NULL != row && SUCCEED != DBis_null(row[0]))
		{
			if (atoi(row[0]) != 0)
			{
				status = atoi(row[0]);
			}
		}
		DBfree_result(result);
	}

	return status;
}

/* SUCCEED if latest service alarm has this status */
/* Rewrite required to simplify logic ?*/
static int	latest_service_alarm(zbx_uint64_t serviceid, int status)
{
	const char	*__function_name = "latest_service_alarm";
	DB_RESULT	result;
	DB_ROW		row;
	int		ret = FAIL;
	char		sql[MAX_STRING_LEN];

	zabbix_log(LOG_LEVEL_DEBUG, "In %s(): serviceid [" ZBX_FS_UI64 "] status [%d]",
			__function_name, serviceid, status);

	zbx_snprintf(sql, sizeof(sql), "select servicealarmid,value"
					" from service_alarms"
					" where serviceid=" ZBX_FS_UI64
					" order by servicealarmid desc", serviceid);

	result = DBselectN(sql, 1);
	row = DBfetch(result);

	if (NULL != row && FAIL == DBis_null(row[1]) && status == atoi(row[1]))
	{
		ret = SUCCEED;
	}

	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

static void	DBadd_service_alarm(zbx_uint64_t serviceid, int status, int clock)
{
	const char	*__function_name = "DBadd_service_alarm";

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (SUCCEED != latest_service_alarm(serviceid, status))
	{
		DBexecute("insert into service_alarms (servicealarmid,serviceid,clock,value)"
			" values(" ZBX_FS_UI64 "," ZBX_FS_UI64 ",%d,%d)",
			DBget_maxid("service_alarms"), serviceid, clock, status);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: DBupdate_services_rec                                            *
 *                                                                            *
 * Purpose: re-calculate and update status of the service and its children    *
 *                                                                            *
 * Parameters: serviceid - item to update services for                        *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: recursive function                                               *
 *           !!! Don't forget to sync the code with PHP !!!                   *
 *                                                                            *
 ******************************************************************************/
static void	DBupdate_services_rec(zbx_uint64_t serviceid, int clock)
{
	int		algorithm, status = 0;
	zbx_uint64_t	serviceupid;
	DB_RESULT	result;
	DB_ROW		row;

	result = DBselect("select l.serviceupid,s.algorithm"
			" from services_links l,services s"
			" where s.serviceid=l.serviceupid"
				" and l.servicedownid=" ZBX_FS_UI64,
			serviceid);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(serviceupid, row[0]);
		algorithm = atoi(row[1]);

		if (SERVICE_ALGORITHM_MAX == algorithm || SERVICE_ALGORITHM_MIN == algorithm)
		{
			status = DBget_service_status(serviceupid, algorithm, 0);

			DBadd_service_alarm(serviceupid, status, clock);
			DBexecute("update services set status=%d where serviceid=" ZBX_FS_UI64, status, serviceupid);
		}
		else if (SERVICE_ALGORITHM_NONE != algorithm)
			zabbix_log(LOG_LEVEL_ERR, "unknown calculation algorithm of service status [%d]", algorithm);
	}
	DBfree_result(result);

	result = DBselect("select serviceupid"
			" from services_links"
			" where servicedownid=" ZBX_FS_UI64,
			serviceid);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(serviceupid, row[0]);
		DBupdate_services_rec(serviceupid, clock);
	}
	DBfree_result(result);
}

/******************************************************************************
 *                                                                            *
 * Function: DBupdate_services_status_all                                     *
 *                                                                            *
 * Purpose: Cleaning parent nodes from triggers, updating ALL services status.*
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget to sync the code with PHP !!!                   *
 *                                                                            *
 ******************************************************************************/
static void	DBupdate_services_status_all()
{
	DB_RESULT	result;
	DB_ROW		row;

	zbx_uint64_t	serviceid = 0, triggerid = 0;
	int		status = 0, clock;

	DBclear_parents_from_trigger();

	clock = time(NULL);

	result = DBselect(
			"select serviceid,algorithm,triggerid"
			" from services"
			" where serviceid not in (select distinct serviceupid from services_links)");

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(serviceid, row[0]);
		if (SUCCEED == DBis_null(row[2]))
			triggerid = 0;
		else
			ZBX_STR2UINT64(triggerid, row[2]);

		status = DBget_service_status(serviceid, atoi(row[1]), triggerid);

		DBexecute("update services"
				" set status=%d"
				" where serviceid=" ZBX_FS_UI64,
				status, serviceid);

		DBadd_service_alarm(serviceid, status, clock);
	}
	DBfree_result(result);

	result = DBselect(
			"select max(servicedownid),serviceupid"
			" from services_links"
			" where servicedownid not in (select distinct serviceupid from services_links)"
			" group by serviceupid");

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(serviceid, row[0]);
		DBupdate_services_rec(serviceid, clock);
	}
	DBfree_result(result);
}

/******************************************************************************
 *                                                                            *
 * Function: DBupdate_services                                                *
 *                                                                            *
 * Purpose: re-calculate and update status of the service and its children    *
 *                                                                            *
 * Parameters: serviceid - item to update services for                        *
 *             status - new status of the service                             *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget to sync the code with PHP !!!                   *
 *                                                                            *
 ******************************************************************************/
void	DBupdate_services(zbx_uint64_t triggerid, int status, int clock)
{
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	serviceid;

	result = DBselect("select serviceid from services where triggerid=" ZBX_FS_UI64, triggerid);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(serviceid, row[0]);

		DBexecute("update services set status=%d where serviceid=" ZBX_FS_UI64, status, serviceid);

		DBadd_service_alarm(serviceid, status, clock);
		DBupdate_services_rec(serviceid, clock);
	}

	DBfree_result(result);
}

/******************************************************************************
 *                                                                            *
 * Function: DBdelete_services_by_triggerids                                  *
 *                                                                            *
 * Purpose: delete triggers from service                                      *
 *                                                                            *
 * Parameters: triggerids     - [IN] trigger identificators from database     *
 *             triggerids_num - [IN] number of triggers                       *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget to sync the code with PHP !!!                   *
 *                                                                            *
 ******************************************************************************/
static void	DBdelete_services_by_triggerids(zbx_uint64_t *triggerids, int triggerids_num)
{
	char	*sql = NULL;
	size_t	sql_alloc = 256, sql_offset = 0;

	if (0 == triggerids_num)
		return;

	sql = zbx_malloc(sql, sql_alloc);

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"delete from services"
			" where");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "triggerid", triggerids, triggerids_num);

	DBexecute("%s", sql);

	zbx_free(sql);

	DBupdate_services_status_all();
}

/******************************************************************************
 *                                                                            *
 * Function: DBdelete_sysmaps_elements                                        *
 *                                                                            *
 * Purpose: delete elements from map by elementtype and elementid             *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget to sync the code with PHP !!!                   *
 *                                                                            *
 ******************************************************************************/
static void	DBdelete_sysmaps_elements(int elementtype, zbx_uint64_t *elementids, int elementids_num)
{
	char	*sql = NULL;
	size_t	sql_alloc = 256, sql_offset = 0;

	if (0 == elementids_num)
		return;

	sql = zbx_malloc(sql, sql_alloc);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"delete from sysmaps_elements"
			" where elementtype=%d"
				" and",
			elementtype);
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "elementid", elementids, elementids_num);

	DBexecute("%s", sql);

	zbx_free(sql);
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
static void DBdelete_action_conditions(int conditiontype, zbx_uint64_t elementid)
{
	DB_RESULT	result;
	DB_ROW		row;

/* disable actions */
	result = DBselect("select distinct actionid from conditions where conditiontype=%d and value='" ZBX_FS_UI64 "'",
			conditiontype, elementid);

	while (NULL != (row = DBfetch(result)))
		DBexecute("update actions set status=%d where actionid=%s", ACTION_STATUS_DISABLED, row[0]);

	DBfree_result(result);

/* delete action conditions */
	DBexecute("delete from conditions where conditiontype=%d and value='" ZBX_FS_UI64 "'",
			conditiontype, elementid);
}

/******************************************************************************
 *                                                                            *
 * Function: DBdelete_triggers                                                *
 *                                                                            *
 * Purpose: delete trigger from database                                      *
 *                                                                            *
 * Parameters: triggerids     - [IN] trigger identificators from database     *
 *             triggerids_num - [IN] number of triggers                       *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget to sync the code with PHP !!!                   *
 *                                                                            *
 ******************************************************************************/
static void	DBdelete_triggers(zbx_uint64_t **triggerids, int *triggerids_alloc, int *triggerids_num)
{
	char		*sql = NULL;
	size_t		sql_alloc = 256, sql_offset;
	int		num, i;
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	triggerid;

	if (0 == *triggerids_num)
		return;

	sql = zbx_malloc(sql, sql_alloc);

	do /* add child triggers (auto-created) */
	{
		num = *triggerids_num;
		sql_offset = 0;
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				"select triggerid"
				" from trigger_discovery"
				" where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "parent_triggerid", *triggerids, *triggerids_num);

		result = DBselect("%s", sql);

		while (NULL != (row = DBfetch(result)))
		{
			ZBX_STR2UINT64(triggerid, row[0]);
			uint64_array_add(triggerids, triggerids_alloc, triggerids_num, triggerid, 64);
		}
		DBfree_result(result);
	}
	while (num != *triggerids_num);

	DBdelete_services_by_triggerids(*triggerids, *triggerids_num);
	DBdelete_sysmaps_elements(SYSMAP_ELEMENT_TYPE_TRIGGER, *triggerids, *triggerids_num);

	for (i = 0; i < *triggerids_num; i++)
		DBdelete_action_conditions(CONDITION_TYPE_TRIGGER, (*triggerids)[i]);

	sql_offset = 0;
	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"delete from events"
			" where source=%d"
				" and object=%d"
				" and",
			EVENT_SOURCE_TRIGGERS, EVENT_OBJECT_TRIGGER);
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "objectid", *triggerids, *triggerids_num);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");

	/* delete from profiles */
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"delete from profiles"
			" where idx='web.events.filter.triggerid'"
				" and");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "value_id", *triggerids, *triggerids_num);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"delete from triggers"
			" where");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "triggerid", *triggerids, *triggerids_num);
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, ";\n");

	DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	DBexecute("%s", sql);

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
	const char	*__function_name = "DBdelete_triggers_by_itemids";
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	*triggerids = NULL, triggerid;
	int		triggerids_alloc = 0, triggerids_num = 0;
	char		*sql = NULL;
	size_t		sql_alloc = 512, sql_offset = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() values_num:%d", __function_name, itemids->values_num);

	if (0 == itemids->values_num)
		goto out;

	sql = zbx_malloc(sql, sql_alloc);

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select distinct triggerid"
			" from functions"
			" where");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "itemid", itemids->values, itemids->values_num);

	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(triggerid, row[0]);
		uint64_array_add(&triggerids, &triggerids_alloc, &triggerids_num, triggerid, 64);
	}
	DBfree_result(result);

	DBdelete_triggers(&triggerids, &triggerids_alloc, &triggerids_num);

	zbx_free(triggerids);
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
	char		*sql = NULL;
	size_t		sql_alloc = 64 * ZBX_KIBIBYTE, sql_offset = 0;
	int		i, j;
	zbx_uint64_t	housekeeperid;
	const char	*ins_housekeeper_sql = "insert into housekeeper (housekeeperid,tablename,field,value) values ";
#ifdef HAVE_MULTIROW_INSERT
	const char	*row_dl = ",";
#else
	const char	*row_dl = ";\n";
#endif
#define	ZBX_HISTORY_TABLES_COUNT	7
	const char	*tables[ZBX_HISTORY_TABLES_COUNT] = {"history", "history_str", "history_uint", "history_log",
			"history_text", "trends", "trends_uint"};

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() values_num:%d", __function_name, itemids->values_num);

	if (0 == itemids->values_num)
		return;

	housekeeperid = DBget_maxid_num("housekeeper", ZBX_HISTORY_TABLES_COUNT * itemids->values_num);

	sql = zbx_malloc(sql, sql_alloc);
	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);
#ifdef HAVE_MULTIROW_INSERT
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ins_housekeeper_sql);
#endif
	for (i = 0; i < itemids->values_num; i++)
	{
		for (j = 0; j < ZBX_HISTORY_TABLES_COUNT; j++)
		{
#ifndef HAVE_MULTIROW_INSERT
			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ins_housekeeper_sql);
#endif
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
					"(" ZBX_FS_UI64 ",'%s','itemid'," ZBX_FS_UI64 ")%s",
					housekeeperid++, tables[j], itemids->values[i], row_dl);
		}
	}

	DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (sql_offset > 16)	/* In ORACLE always present begin..end; */
	{
#ifdef HAVE_MULTIROW_INSERT
		sql_offset--;
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");
#endif
		DBexecute("%s", sql);
	}

	zbx_free(sql);

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
 * Return value: upon successful completion return SUCCEED                    *
 *                                                                            *
 * Author: Eugene Grigorjev, Alexander Vladishev                              *
 *                                                                            *
 * Comments: !!! Don't forget to sync the code with PHP !!!                   *
 *                                                                            *
 ******************************************************************************/
static void	DBdelete_graphs(zbx_vector_uint64_t *graphids)
{
	const char	*__function_name = "DBdelete_graphs";
	char		*sql = NULL;
	size_t		sql_alloc = 256, sql_offset;
	int		num;
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	graphid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() values_num:%d", __function_name, graphids->values_num);

	if (0 == graphids->values_num)
		goto out;

	sql = zbx_malloc(sql, sql_alloc);

	do	/* add child graphs (auto-created) */
	{
		num = graphids->values_num;
		sql_offset = 0;
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				"select graphid"
				" from graph_discovery"
				" where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "parent_graphid",
				graphids->values, graphids->values_num);

		result = DBselect("%s", sql);

		while (NULL != (row = DBfetch(result)))
		{
			ZBX_STR2UINT64(graphid, row[0]);
			zbx_vector_uint64_append(graphids, graphid);
		}
		DBfree_result(result);

		zbx_vector_uint64_sort(graphids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_vector_uint64_uniq(graphids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	}
	while (num != graphids->values_num);

	sql_offset = 0;
	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	/* delete from screens_items */
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"delete from screens_items"
			" where resourcetype=%d"
				" and",
			SCREEN_RESOURCE_GRAPH);
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "resourceid", graphids->values, graphids->values_num);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");

	/* delete from profiles */
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"delete from profiles"
			" where idx='web.favorite.graphids'"
				" and source='graphid'"
				" and");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "value_id", graphids->values, graphids->values_num);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");

	/* delete from graphs */
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from graphs where");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "graphid", graphids->values, graphids->values_num);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");

	DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	DBexecute("%s", sql);

	zbx_free(sql);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
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
	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(graphid, row[0]);
		zbx_vector_uint64_append(&graphids, graphid);
	}
	DBfree_result(result);

	if (0 == graphids.values_num)
		goto clean;

	zbx_vector_uint64_sort(&graphids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

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

	DBdelete_graphs(&graphids);
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
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments: !!! Don't forget to sync the code with PHP !!!                   *
 *                                                                            *
 ******************************************************************************/
void	DBdelete_items(zbx_vector_uint64_t *itemids)
{
	const char	*__function_name = "DBdelete_items";
	char		*sql = NULL;
	size_t		sql_alloc = 256, sql_offset;
	int		num;
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	itemid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() values_num:%d", __function_name, itemids->values_num);

	if (0 == itemids->values_num)
		goto out;

	sql = zbx_malloc(sql, sql_alloc);

	do	/* add child items (auto-created and prototypes) */
	{
		num = itemids->values_num;
		sql_offset = 0;
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				"select itemid"
				" from item_discovery"
				" where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "parent_itemid",
				itemids->values, itemids->values_num);

		result = DBselect("%s", sql);

		while (NULL != (row = DBfetch(result)))
		{
			ZBX_STR2UINT64(itemid, row[0]);
			zbx_vector_uint64_append(itemids, itemid);
		}
		DBfree_result(result);

		zbx_vector_uint64_sort(itemids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_vector_uint64_uniq(itemids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	}
	while (num != itemids->values_num);

	DBdelete_graphs_by_itemids(itemids);
	DBdelete_triggers_by_itemids(itemids);
	DBdelete_history_by_itemids(itemids);

	sql_offset = 0;
	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	/* delete from screens_items */
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"delete from screens_items"
				" where resourcetype in (%d,%d)"
					" and",
			SCREEN_RESOURCE_PLAIN_TEXT,
			SCREEN_RESOURCE_SIMPLE_GRAPH);
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "resourceid", itemids->values, itemids->values_num);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");

	/* delete from profiles */
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"delete from profiles"
			" where idx='web.favorite.graphids'"
				" and source='itemid'"
				" and");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "value_id", itemids->values, itemids->values_num);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");

	/* delete from items */
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from items where");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "itemid", itemids->values, itemids->values_num);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");

	DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	DBexecute("%s", sql);

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
	DB_RESULT		result;
	DB_ROW			row;
	char			*sql = NULL;
	size_t			sql_alloc = 256, sql_offset = 0;
	zbx_vector_uint64_t	itemids;
	zbx_uint64_t		itemid;

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

	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(itemid, row[0]);
		zbx_vector_uint64_append(&itemids, itemid);
	}
	DBfree_result(result);

	zbx_vector_uint64_sort(&itemids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
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
static void	DBdelete_applications(zbx_uint64_t *applicationids, int applicationids_num)
{
	DB_RESULT	result;
	DB_ROW		row;
	char		*sql = NULL;
	size_t		sql_alloc = 256, sql_offset = 0;
	zbx_uint64_t	*ids = NULL, applicationid;
	int		ids_alloc = 0, ids_num = 0;

	if (0 == applicationids_num)
		return;

	sql = zbx_malloc(sql, sql_alloc);

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select applicationid,name"
			" from httptest"
			" where");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "applicationid", applicationids, applicationids_num);

	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(applicationid, row[0]);
		uint64_array_add(&ids, &ids_alloc, &ids_num, applicationid, 4);

		zabbix_log(LOG_LEVEL_DEBUG, "Application [" ZBX_FS_UI64 "] used by scenario '%s'",
				applicationid, row[1]);
	}
	DBfree_result(result);

	uint64_array_remove(applicationids, &applicationids_num, ids, ids_num);

	sql_offset = 0;
	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (0 != applicationids_num)
	{
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from applications where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset,
				"applicationid", applicationids, applicationids_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");
	}

	if (0 != ids_num)
	{
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "update applications set templateid=null where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "applicationid", ids, ids_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");
	}

	DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	DBexecute("%s", sql);

	zbx_free(ids);
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
static void	DBdelete_template_httptests(zbx_uint64_t hostid, zbx_vector_uint64_t *templateids)
{
	const char		*__function_name = "DBdelete_template_httptests";

	char			*sql = NULL;
	size_t			sql_alloc = 256, sql_offset = 0;
	DB_RESULT		result;
	DB_ROW			row;
	zbx_vector_uint64_t	httptestids;
	zbx_uint64_t		httptestid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	sql = zbx_malloc(sql, sql_alloc);
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

	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(httptestid, row[0]);
		zbx_vector_uint64_append(&httptestids, httptestid);
	}
	DBfree_result(result);

	zbx_vector_uint64_sort(&httptestids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
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
static void	DBdelete_template_graphs(zbx_uint64_t hostid, zbx_vector_uint64_t *templateids)
{
	const char		*__function_name = "DBdelete_template_graphs";

	char			*sql = NULL;
	size_t			sql_alloc = 256, sql_offset = 0;
	DB_RESULT		result;
	DB_ROW			row;
	zbx_vector_uint64_t	graphids;
	zbx_uint64_t		graphid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	sql = zbx_malloc(sql, sql_alloc);
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

	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(graphid, row[0]);
		zbx_vector_uint64_append(&graphids, graphid);
	}
	DBfree_result(result);

	zbx_vector_uint64_sort(&graphids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	DBdelete_graphs(&graphids);

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
static void	DBdelete_template_triggers(zbx_uint64_t hostid, zbx_vector_uint64_t *templateids)
{
	const char	*__function_name = "DBdelete_template_triggers";

	char		*sql = NULL;
	size_t		sql_alloc = 256, sql_offset = 0;
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	*triggerids = NULL, triggerid;
	int		triggerids_alloc = 0, triggerids_num = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	sql = zbx_malloc(sql, sql_alloc);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select distinct f.triggerid"
			" from functions f,items i,items ti"
			" where f.itemid=i.itemid"
				" and i.templateid=ti.itemid"
				" and i.hostid=" ZBX_FS_UI64
				" and",
			hostid);
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "ti.hostid", templateids->values, templateids->values_num);

	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(triggerid, row[0]);
		uint64_array_add(&triggerids, &triggerids_alloc, &triggerids_num, triggerid, 64);
	}
	DBfree_result(result);

	DBdelete_triggers(&triggerids, &triggerids_alloc, &triggerids_num);

	zbx_free(triggerids);
	zbx_free(sql);

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
static void	DBdelete_template_items(zbx_uint64_t hostid, zbx_vector_uint64_t *templateids)
{
	const char		*__function_name = "DBdelete_template_items";

	char			*sql = NULL;
	size_t			sql_alloc = 256, sql_offset = 0;
	DB_RESULT		result;
	DB_ROW			row;
	zbx_uint64_t		itemid;
	zbx_vector_uint64_t	itemids;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	sql = zbx_malloc(sql, sql_alloc);
	zbx_vector_uint64_create(&itemids);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select distinct i.itemid"
			" from items i,items ti"
			" where i.templateid=ti.itemid"
				" and i.hostid=" ZBX_FS_UI64
				" and",
			hostid);
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "ti.hostid", templateids->values, templateids->values_num);

	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(itemid, row[0]);
		zbx_vector_uint64_append(&itemids, itemid);
	}
	DBfree_result(result);

	zbx_vector_uint64_sort(&itemids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	DBdelete_items(&itemids);

	zbx_vector_uint64_destroy(&itemids);
	zbx_free(sql);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: DBdelete_template_applications                                   *
 *                                                                            *
 * Purpose: delete application                                                *
 *                                                                            *
 * Parameters: hostid      - [IN] host identificator from database            *
 *             templateids - [IN] array of template IDs                       *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget to sync the code with PHP !!!                   *
 *                                                                            *
 ******************************************************************************/
static void	DBdelete_template_applications(zbx_uint64_t hostid, zbx_vector_uint64_t *templateids)
{
	const char	*__function_name = "DBdelete_template_applications";

	char		*sql = NULL;
	size_t		sql_alloc = 256, sql_offset = 0;
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	*applicationids = NULL, applicationid;
	int		applicationids_alloc = 0, applicationids_num = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	sql = zbx_malloc(sql, sql_alloc);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select distinct a.applicationid"
			" from applications a,applications ta"
			" where a.templateid=ta.applicationid"
				" and a.hostid=" ZBX_FS_UI64
				" and",
			hostid);
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "ta.hostid", templateids->values, templateids->values_num);

	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(applicationid, row[0]);
		uint64_array_add(&applicationids, &applicationids_alloc, &applicationids_num,
				applicationid, 64);
	}
	DBfree_result(result);

	DBdelete_applications(applicationids, applicationids_num);

	zbx_free(applicationids);
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
						"comments,url,type,value,value_flags,templateid,flags)"
					" values (" ZBX_FS_UI64 ",'%s',%d,%d,"
						"'%s','%s',%d,%d,%d," ZBX_FS_UI64 ",%d);\n",
					*new_triggerid, description_esc, (int)priority,
					(int)status, comments_esc, url_esc, (int)type,
					TRIGGER_VALUE_FALSE, TRIGGER_VALUE_FLAG_UNKNOWN, triggerid, (int)flags);

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
	DB_RESULT	result;
	DB_ROW		row;
	int		alloc = 16, count = 0, i;
	zbx_uint64_t	*hst_triggerids = NULL, *tpl_triggerids = NULL,
			templateid, triggerid,
			templateid_down, templateid_up,
			triggerid_down, triggerid_up,
			triggerdepid;
	char		*sql = NULL;
	size_t		sql_alloc = 512, sql_offset;

	if (0 == trids_num)
		return SUCCEED;

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

	sql_offset = 0;
	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

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
			triggerdepid = DBget_maxid("trigger_depends");

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
					"insert into trigger_depends"
					" (triggerdepid,triggerid_down,triggerid_up)"
					" values (" ZBX_FS_UI64 "," ZBX_FS_UI64 "," ZBX_FS_UI64 ");\n",
					triggerdepid, triggerid_down, triggerid_up);
		}
	}
	DBfree_result(result);

	DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (sql_offset > 16)	/* In ORACLE always present begin..end; */
		DBexecute("%s", sql);

	zbx_free(hst_triggerids);
	zbx_free(tpl_triggerids);
	zbx_free(sql);

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
 *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
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
	DBdelete_template_items(hostid, del_templateids);
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

/******************************************************************************
 *                                                                            *
 * Function: DBcopy_template_applications                                     *
 *                                                                            *
 * Purpose: copy applications from templates to host                          *
 *                                                                            *
 * Parameters: hostid      - [IN] host id                                     *
 *             templateids - [IN] array of template IDs                       *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget to sync the code with PHP !!!                   *
 *                                                                            *
 ******************************************************************************/
static void	DBcopy_template_applications(zbx_uint64_t hostid, zbx_vector_uint64_t *templateids)
{
	typedef struct
	{
		zbx_uint64_t	applicationid;
		zbx_uint64_t	templateid;
		char		*name_esc;
	}
	zbx_app_t;

	const char	*__function_name = "DBcopy_template_applications";
	DB_RESULT	result;
	DB_ROW		row;
	char		*sql = NULL;
	size_t		sql_alloc = ZBX_KIBIBYTE, sql_offset;
	zbx_app_t	*app = NULL;
	size_t		app_alloc = 0, app_num = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	sql = zbx_malloc(sql, sql_alloc);

	sql_offset = 0;
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select ta.applicationid,ta.name,ha.applicationid"
			" from applications ta"
			" left join applications ha"
				" on ha.name=ta.name"
					" and ha.hostid=" ZBX_FS_UI64
			" where",
			hostid);
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "ta.hostid", templateids->values, templateids->values_num);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " order by ha.applicationid");

	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		if (app_num == app_alloc)
		{
			app_alloc += 16;
			app = zbx_realloc(app, app_alloc * sizeof(zbx_app_t));
		}

		ZBX_STR2UINT64(app[app_num].templateid, row[0]);

		if (SUCCEED != DBis_null(row[2]))
		{
			ZBX_STR2UINT64(app[app_num].applicationid, row[2]);
			app[app_num].name_esc = NULL;
		}
		else
		{
			app[app_num].applicationid = 0;
			app[app_num].name_esc = DBdyn_escape_string(row[1]);
		}
		app_num++;
	}
	DBfree_result(result);

	if (0 != app_num)
	{
		zbx_uint64_t	applicationid = 0;
		int		i, new_applications = app_num;
		const char	*ins_applications_sql =
				"insert into applications"
				" (applicationid,hostid,name,templateid)"
				" values ";
#ifdef HAVE_MULTIROW_INSERT
		const char	*row_dl = ",";
#else
		const char	*row_dl = ";\n";
#endif

		sql_offset = 0;
		DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

		for (i = 0; i < app_num; i++)
		{
			if (0 == app[i].applicationid)
				continue;

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
					"update applications"
					" set templateid=" ZBX_FS_UI64
					" where applicationid=" ZBX_FS_UI64 ";\n",
					app[i].templateid, app[i].applicationid);

			new_applications--;
		}

		if (0 != new_applications)
		{
			applicationid = DBget_maxid_num("applications", new_applications);
#ifdef HAVE_MULTIROW_INSERT
			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ins_applications_sql);
#endif
		}

		for (i = 0; i < app_num; i++)
		{
			if (0 != app[i].applicationid)
				continue;

#ifndef HAVE_MULTIROW_INSERT
			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ins_applications_sql);
#endif
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
					"(" ZBX_FS_UI64 "," ZBX_FS_UI64 ",'%s'," ZBX_FS_UI64 ")%s",
					applicationid++, hostid, app[i].name_esc, app[i].templateid, row_dl);

			zbx_free(app[i].name_esc);
		}

#ifdef HAVE_MULTIROW_INSERT
		if (0 != new_applications)
		{
			sql_offset--;
			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");
		}
#endif
		DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

		DBexecute("%s", sql);

		zbx_free(app);
	}

	zbx_free(sql);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/* auxiliary function for DBcopy_template_items() */
static void	DBget_interfaces_by_hostid(zbx_uint64_t hostid, zbx_uint64_t *interfaceids)
{
	DB_RESULT	result;
	DB_ROW		row;
	unsigned char	type;

	result = DBselect(
			"select type,interfaceid"
			" from interface"
			" where hostid=" ZBX_FS_UI64
				" and type in (%d,%d,%d,%d)"
				" and main=1",
			hostid, INTERFACE_TYPE_AGENT, INTERFACE_TYPE_SNMP, INTERFACE_TYPE_IPMI, INTERFACE_TYPE_JMX);

	while (NULL != (row = DBfetch(result)))
	{
		type = (unsigned char)atoi(row[0]);
		ZBX_STR2UINT64(interfaceids[type - 1], row[1]);
	}
	DBfree_result(result);
}

/******************************************************************************
 *                                                                            *
 * Function: DBcopy_template_items                                            *
 *                                                                            *
 * Purpose: copy template items to host                                       *
 *                                                                            *
 * Parameters: hostid      - [IN] host id                                     *
 *             templateids - [IN] array of template IDs                       *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget to sync the code with PHP !!!                   *
 *                                                                            *
 ******************************************************************************/
static void	DBcopy_template_items(zbx_uint64_t hostid, zbx_vector_uint64_t *templateids)
{
	typedef struct
	{
		zbx_uint64_t	itemid;
		zbx_uint64_t	valuemapid;
		zbx_uint64_t	interfaceid;
		zbx_uint64_t	templateid;
		char		*name_esc;
		char		*key_esc;
		char		*delay_flex_esc;
		char		*trapper_hosts_esc;
		char		*units_esc;
		char		*formula_esc;
		char		*logtimefmt_esc;
		char		*params_esc;
		char		*ipmi_sensor_esc;
		char		*snmp_community_esc;
		char		*snmp_oid_esc;
		char		*snmpv3_securityname_esc;
		char		*snmpv3_authpassphrase_esc;
		char		*snmpv3_privpassphrase_esc;
		char		*username_esc;
		char		*password_esc;
		char		*publickey_esc;
		char		*privatekey_esc;
		char		*filter_esc;
		char		*description_esc;
		char		*lifetime_esc;
		int		delay;
		int		history;
		int		trends;
		int		multiplier;
		int		delta;
		unsigned char	type;
		unsigned char	value_type;
		unsigned char	data_type;
		unsigned char	status;
		unsigned char	snmpv3_securitylevel;
		unsigned char	authtype;
		unsigned char	flags;
		unsigned char	inventory_link;
	}
	zbx_item_t;

	typedef struct
	{
		zbx_uint64_t	itemid;
		zbx_uint64_t	applicationid;
	}
	zbx_itemapp_t;

	typedef struct
	{
		zbx_uint64_t	itemid;
		zbx_uint64_t	parent_itemid;
	}
	zbx_proto_t;

	const char	*__function_name = "DBcopy_template_items";

	DB_RESULT	result;
	DB_ROW		row;
	char		*sql = NULL;
	size_t		sql_alloc = 16 * ZBX_KIBIBYTE, sql_offset = 0;
	int		i;
	zbx_uint64_t	interfaceids[4];
	zbx_item_t	*item = NULL;
	size_t		item_alloc = 0, item_num = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	memset(&interfaceids, 0, sizeof(interfaceids));

	DBget_interfaces_by_hostid(hostid, interfaceids);

	/* items */

	sql = zbx_malloc(sql, sql_alloc);

	sql_offset = 0;
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select ti.itemid,ti.name,ti.key_,ti.type,ti.value_type,ti.data_type,ti.delay,ti.delay_flex,"
				"ti.history,ti.trends,ti.status,ti.trapper_hosts,ti.units,ti.multiplier,ti.delta,"
				"ti.formula,ti.logtimefmt,ti.valuemapid,ti.params,ti.ipmi_sensor,ti.snmp_community,"
				"ti.snmp_oid,ti.snmpv3_securityname,ti.snmpv3_securitylevel,ti.snmpv3_authpassphrase,"
				"ti.snmpv3_privpassphrase,ti.authtype,ti.username,ti.password,ti.publickey,"
				"ti.privatekey,ti.flags,ti.filter,ti.description,ti.inventory_link,ti.lifetime,"
				"hi.itemid"
			" from items ti"
			" left join items hi on hi.key_=ti.key_"
				" and hi.hostid=" ZBX_FS_UI64
			" where",
			hostid);
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "ti.hostid", templateids->values, templateids->values_num);
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " order by hi.itemid");

	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		unsigned char	interface_type;

		if (item_num == item_alloc)
		{
			item_alloc += 64;
			item = zbx_realloc(item, item_alloc * sizeof(zbx_item_t));
		}

		ZBX_STR2UINT64(item[item_num].templateid, row[0]);
		item[item_num].name_esc = DBdyn_escape_string(row[1]);
		item[item_num].type = (unsigned char)atoi(row[3]);
		item[item_num].value_type = (unsigned char)atoi(row[4]);
		item[item_num].data_type = (unsigned char)atoi(row[5]);
		item[item_num].delay = atoi(row[6]);
		item[item_num].delay_flex_esc = DBdyn_escape_string(row[7]);
		item[item_num].history = atoi(row[8]);
		item[item_num].trends = atoi(row[9]);
		item[item_num].status = (unsigned char)atoi(row[10]);
		item[item_num].trapper_hosts_esc = DBdyn_escape_string(row[11]);
		item[item_num].units_esc = DBdyn_escape_string(row[12]);
		item[item_num].multiplier = atoi(row[13]);
		item[item_num].delta = atoi(row[14]);
		item[item_num].formula_esc = DBdyn_escape_string(row[15]);
		item[item_num].logtimefmt_esc = DBdyn_escape_string(row[16]);
		ZBX_DBROW2UINT64(item[item_num].valuemapid, row[17]);
		item[item_num].params_esc = DBdyn_escape_string(row[18]);
		item[item_num].ipmi_sensor_esc = DBdyn_escape_string(row[19]);
		item[item_num].snmp_community_esc = DBdyn_escape_string(row[20]);
		item[item_num].snmp_oid_esc = DBdyn_escape_string(row[21]);
		item[item_num].snmpv3_securityname_esc = DBdyn_escape_string(row[22]);
		item[item_num].snmpv3_securitylevel = (unsigned char)atoi(row[23]);
		item[item_num].snmpv3_authpassphrase_esc = DBdyn_escape_string(row[24]);
		item[item_num].snmpv3_privpassphrase_esc = DBdyn_escape_string(row[25]);
		item[item_num].authtype = (unsigned char)atoi(row[26]);
		item[item_num].username_esc = DBdyn_escape_string(row[27]);
		item[item_num].password_esc = DBdyn_escape_string(row[28]);
		item[item_num].publickey_esc = DBdyn_escape_string(row[29]);
		item[item_num].privatekey_esc = DBdyn_escape_string(row[30]);
		item[item_num].flags = (unsigned char)atoi(row[31]);
		item[item_num].filter_esc = DBdyn_escape_string(row[32]);
		item[item_num].description_esc = DBdyn_escape_string(row[33]);
		item[item_num].inventory_link = (unsigned char)atoi(row[34]);
		item[item_num].lifetime_esc = DBdyn_escape_string(row[35]);

		switch (interface_type = get_interface_type_by_item_type(item[item_num].type))
		{
			case INTERFACE_TYPE_UNKNOWN:
				item[item_num].interfaceid = 0;
				break;
			case INTERFACE_TYPE_ANY:
				for (i = 0; INTERFACE_TYPE_COUNT > i; i++)
				{
					if (0 != interfaceids[INTERFACE_TYPE_PRIORITY[i] - 1])
						break;
				}
				item[item_num].interfaceid = interfaceids[INTERFACE_TYPE_PRIORITY[i] - 1];
				break;
			default:
				item[item_num].interfaceid = interfaceids[interface_type - 1];
		}

		if (SUCCEED != DBis_null(row[36]))
		{
			item[item_num].key_esc = NULL;
			ZBX_STR2UINT64(item[item_num].itemid, row[36]);
		}
		else
		{
			item[item_num].key_esc = DBdyn_escape_string(row[2]);
			item[item_num].itemid = 0;
		}

		item_num++;
	}
	DBfree_result(result);

	if (0 != item_num)
	{
		zbx_uint64_t	itemid = 0;
		int		new_items = item_num;
		const char	*ins_items_sql =
				"insert into items"
				" (itemid,name,key_,hostid,type,value_type,data_type,delay,delay_flex,history,trends,"
					"status,trapper_hosts,units,multiplier,delta,formula,logtimefmt,valuemapid,"
					"params,ipmi_sensor,snmp_community,snmp_oid,snmpv3_securityname,"
					"snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,authtype,"
					"username,password,publickey,privatekey,templateid,flags,filter,description,"
					"inventory_link,interfaceid,lifetime)"
				" values ";
		zbx_uint64_t	*itemids = NULL, *protoids = NULL;
		size_t		itemids_num = 0, protoids_num = 0;
		zbx_itemapp_t	*itemapp = NULL;
		size_t		itemapp_alloc = 0, itemapp_num = 0;
#ifdef HAVE_MULTIROW_INSERT
		const char	*row_dl = ",";
#else
		const char	*row_dl = ";\n";
#endif

		itemids = zbx_malloc(itemids, item_num * sizeof(zbx_uint64_t));

		sql_offset = 0;
		DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

		for (i = 0; i < item_num; i++)
		{
			if (0 == item[i].itemid)
				continue;

			itemids[itemids_num++] = item[i].itemid;

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
					"update items"
					" set name='%s',"
						"type=%d,"
						"value_type=%d,"
						"data_type=%d,"
						"delay=%d,"
						"delay_flex='%s',"
						"history=%d,"
						"trends=%d,"
						"status=%d,"
						"trapper_hosts='%s',"
						"units='%s',"
						"multiplier=%d,"
						"delta=%d,"
						"formula='%s',"
						"logtimefmt='%s',"
						"valuemapid=%s,"
						"params='%s',"
						"ipmi_sensor='%s',"
						"snmp_community='%s',"
						"snmp_oid='%s',"
						"snmpv3_securityname='%s',"
						"snmpv3_securitylevel=%d,"
						"snmpv3_authpassphrase='%s',"
						"snmpv3_privpassphrase='%s',"
						"authtype=%d,"
						"username='%s',"
						"password='%s',"
						"publickey='%s',"
						"privatekey='%s',"
						"templateid=" ZBX_FS_UI64 ","
						"flags=%d,"
						"filter='%s',"
						"description='%s',"
						"inventory_link=%d,"
						"interfaceid=%s,"
						"lifetime='%s'"
					" where itemid=" ZBX_FS_UI64 ";\n",
					item[i].name_esc, (int)item[i].type, (int)item[i].value_type,
					(int)item[i].data_type, item[i].delay, item[i].delay_flex_esc,
					item[i].history, item[i].trends, (int)item[i].status, item[i].trapper_hosts_esc,
					item[i].units_esc, item[i].multiplier, item[i].delta, item[i].formula_esc,
					item[i].logtimefmt_esc, DBsql_id_ins(item[i].valuemapid), item[i].params_esc,
					item[i].ipmi_sensor_esc, item[i].snmp_community_esc, item[i].snmp_oid_esc,
					item[i].snmpv3_securityname_esc, (int)item[i].snmpv3_securitylevel,
					item[i].snmpv3_authpassphrase_esc, item[i].snmpv3_privpassphrase_esc,
					(int)item[i].authtype, item[i].username_esc, item[i].password_esc,
					item[i].publickey_esc, item[i].privatekey_esc, item[i].templateid,
					(int)item[i].flags, item[i].filter_esc, item[i].description_esc,
					(int)item[i].inventory_link, DBsql_id_ins(item[i].interfaceid),
					item[i].lifetime_esc, item[i].itemid);

			new_items--;
		}

		if (0 != new_items)
		{
			itemid = DBget_maxid_num("items", new_items);
			protoids = zbx_malloc(protoids, new_items * sizeof(zbx_uint64_t));
#ifdef HAVE_MULTIROW_INSERT
			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ins_items_sql);
#endif
		}

		for (i = 0; i < item_num; i++)
		{
			if (0 != item[i].itemid)
				continue;

			itemids[itemids_num++] = itemid;

#ifndef HAVE_MULTIROW_INSERT
			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ins_items_sql);
#endif
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
					"(" ZBX_FS_UI64 ",'%s','%s'," ZBX_FS_UI64 ",%d,%d,%d,%d,'%s',%d,%d,%d,'%s',"
						"'%s',%d,%d,'%s','%s',%s,'%s','%s','%s','%s','%s',%d,'%s','%s',%d,'%s',"
						"'%s','%s','%s'," ZBX_FS_UI64 ",%d,'%s','%s',%d,%s,'%s')%s",
					itemid, item[i].name_esc, item[i].key_esc, hostid, (int)item[i].type,
					(int)item[i].value_type, (int)item[i].data_type, item[i].delay,
					item[i].delay_flex_esc, item[i].history, item[i].trends, (int)item[i].status,
					item[i].trapper_hosts_esc, item[i].units_esc, item[i].multiplier,
					item[i].delta, item[i].formula_esc, item[i].logtimefmt_esc,
					DBsql_id_ins(item[i].valuemapid), item[i].params_esc, item[i].ipmi_sensor_esc,
					item[i].snmp_community_esc, item[i].snmp_oid_esc,
					item[i].snmpv3_securityname_esc, (int)item[i].snmpv3_securitylevel,
					item[i].snmpv3_authpassphrase_esc, item[i].snmpv3_privpassphrase_esc,
					(int)item[i].authtype, item[i].username_esc, item[i].password_esc,
					item[i].publickey_esc, item[i].privatekey_esc, item[i].templateid,
					(int)item[i].flags, item[i].filter_esc, item[i].description_esc,
					(int)item[i].inventory_link, DBsql_id_ins(item[i].interfaceid),
					item[i].lifetime_esc, row_dl);

			zbx_free(item[i].key_esc);

			if (0 != (ZBX_FLAG_DISCOVERY_CHILD & item[i].flags))
				protoids[protoids_num++] = itemid;

			itemid++;
		}

#ifdef HAVE_MULTIROW_INSERT
		if (0 != new_items)
		{
			sql_offset--;
			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");
		}
#endif
		DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

		DBexecute("%s", sql);

		for (i = 0; i < item_num; i++)
		{
			zbx_free(item[i].lifetime_esc);
			zbx_free(item[i].description_esc);
			zbx_free(item[i].filter_esc);
			zbx_free(item[i].privatekey_esc);
			zbx_free(item[i].publickey_esc);
			zbx_free(item[i].password_esc);
			zbx_free(item[i].username_esc);
			zbx_free(item[i].snmpv3_privpassphrase_esc);
			zbx_free(item[i].snmpv3_authpassphrase_esc);
			zbx_free(item[i].snmpv3_securityname_esc);
			zbx_free(item[i].snmp_oid_esc);
			zbx_free(item[i].snmp_community_esc);
			zbx_free(item[i].ipmi_sensor_esc);
			zbx_free(item[i].params_esc);
			zbx_free(item[i].logtimefmt_esc);
			zbx_free(item[i].formula_esc);
			zbx_free(item[i].units_esc);
			zbx_free(item[i].trapper_hosts_esc);
			zbx_free(item[i].delay_flex_esc);
			zbx_free(item[i].name_esc);
		}
		zbx_free(item);

		/* items_applications */

		sql_offset = 0;
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				"select hi.itemid,ha.applicationid"
				" from items_applications tia"
					" join items hi on hi.templateid=tia.itemid"
						" and");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "hi.itemid", itemids, itemids_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
					" join applications ha on ha.templateid=tia.applicationid"
						" and ha.hostid=hi.hostid"
						" left join items_applications hia on hia.applicationid=ha.applicationid"
							" and hia.itemid=hi.itemid"
				" where hia.itemappid is null");

		zbx_free(itemids);

		result = DBselect("%s", sql);

		while (NULL != (row = DBfetch(result)))
		{
			if (itemapp_num == itemapp_alloc)
			{
				itemapp_alloc += 64;
				itemapp = zbx_realloc(itemapp, itemapp_alloc * sizeof(zbx_itemapp_t));
			}

			ZBX_STR2UINT64(itemapp[itemapp_num].itemid, row[0]);
			ZBX_STR2UINT64(itemapp[itemapp_num].applicationid, row[1]);
			itemapp_num++;
		}
		DBfree_result(result);

		/* item_discovery */

		if (0 != itemapp_num)
		{
			zbx_uint64_t	itemappid;
			const char	*ins_itemapps_sql =
					"insert into items_applications (itemappid,itemid,applicationid) values ";

			sql_offset = 0;
			DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

			itemappid = DBget_maxid_num("items_applications", itemapp_num);
#ifdef HAVE_MULTIROW_INSERT
			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ins_itemapps_sql);
#endif

			for (i = 0; i < itemapp_num; i++)
			{
#ifndef HAVE_MULTIROW_INSERT
				zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ins_itemapps_sql);
#endif
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
						"(" ZBX_FS_UI64 "," ZBX_FS_UI64 "," ZBX_FS_UI64 ")%s",
						itemappid++, itemapp[i].itemid, itemapp[i].applicationid, row_dl);
			}

#ifdef HAVE_MULTIROW_INSERT
			sql_offset--;
			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");
#endif
			DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

			DBexecute("%s", sql);

			zbx_free(itemapp);
		}

		if (0 != protoids_num)
		{
			zbx_proto_t	*proto = NULL;
			size_t		proto_alloc = 0, proto_num = 0;

			sql_offset = 0;
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
					"select i.itemid,r.itemid"
					" from items i,item_discovery id,items r"
					" where i.templateid=id.itemid"
						" and id.parent_itemid=r.templateid"
						" and r.hostid=" ZBX_FS_UI64
						" and",
					hostid);
			DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "i.itemid", protoids, protoids_num);

			result = DBselect("%s", sql);

			while (NULL != (row = DBfetch(result)))
			{
				if (proto_num == proto_alloc)
				{
					proto_alloc += 16;
					proto = zbx_realloc(proto, proto_alloc * sizeof(zbx_proto_t));
				}

				ZBX_STR2UINT64(proto[proto_num].itemid, row[0]);
				ZBX_STR2UINT64(proto[proto_num].parent_itemid, row[1]);
				proto_num++;
			}
			DBfree_result(result);

			if (0 != proto_num)
			{
				zbx_uint64_t	itemdiscoveryid;
				const char	*ins_item_discovery_sql =
						"insert into item_discovery"
						" (itemdiscoveryid,itemid,parent_itemid)"
						" values ";

				sql_offset = 0;
				DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

				itemdiscoveryid = DBget_maxid_num("item_discovery", proto_num);
#ifdef HAVE_MULTIROW_INSERT
				zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ins_item_discovery_sql);
#endif

				for (i = 0; i < proto_num; i++)
				{
#ifndef HAVE_MULTIROW_INSERT
					zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ins_item_discovery_sql);
#endif
					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
							"(" ZBX_FS_UI64 "," ZBX_FS_UI64 "," ZBX_FS_UI64 ")%s",
							itemdiscoveryid++, proto[i].itemid, proto[i].parent_itemid,
							row_dl);
				}

#ifdef HAVE_MULTIROW_INSERT
				sql_offset--;
				zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");
#endif
				DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

				DBexecute("%s", sql);

				zbx_free(proto);
			}
		}

		zbx_free(protoids);
	}

	zbx_free(sql);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
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
static int	DBcopy_template_triggers(zbx_uint64_t hostid, zbx_vector_uint64_t *templateids)
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
	zbx_uint64_t	itemid;

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

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(itemid, row[0]);
	}
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
	int		i;
	zbx_uint64_t	hst_graphid, hst_gitemid;
	char		*sql = NULL, *name_esc, *color_esc;
	size_t		sql_alloc = ZBX_KIBIBYTE, sql_offset;

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
static void	DBcopy_template_graphs(zbx_uint64_t hostid, zbx_vector_uint64_t *templateids)
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
	char			*name_esc;
	char			*url_esc;
	char			*posts_esc;
	char			*required_esc;
	char			*status_codes_esc;
	zbx_vector_ptr_t	httpstepitems;
	int			no;
	int			timeout;
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
	char			*name_esc;
	char			*macros_esc;
	char			*agent_esc;
	char			*http_user_esc;
	char			*http_password_esc;
	char			*http_proxy_esc;
	zbx_vector_ptr_t	httpsteps;
	zbx_vector_ptr_t	httptestitems;
	int			delay;
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
static void	DBget_httptests(zbx_uint64_t hostid, zbx_vector_uint64_t *templateids, zbx_vector_ptr_t *httptests)
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
			"select t.httptestid,t.name,t.applicationid,t.delay,t.status,t.macros,t.agent,"
				"t.authentication,t.http_user,t.http_password,t.http_proxy,h.httptestid"
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
		ZBX_DBROW2UINT64(httptest->httptestid, row[11]);
		zbx_vector_ptr_create(&httptest->httpsteps);
		zbx_vector_ptr_create(&httptest->httptestitems);

		zbx_vector_ptr_append(httptests, httptest);

		if (0 == httptest->httptestid)
		{
			httptest->name_esc = DBdyn_escape_string(row[1]);
			ZBX_DBROW2UINT64(httptest->t_applicationid, row[2]);
			httptest->delay = atoi(row[3]);
			httptest->status = (unsigned char)atoi(row[4]);
			httptest->macros_esc = DBdyn_escape_string(row[5]);
			httptest->agent_esc = DBdyn_escape_string(row[6]);
			httptest->authentication = (unsigned char)atoi(row[7]);
			httptest->http_user_esc = DBdyn_escape_string(row[8]);
			httptest->http_password_esc = DBdyn_escape_string(row[9]);
			httptest->http_proxy_esc = DBdyn_escape_string(row[10]);

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
				"select httpstepid,httptestid,name,no,url,timeout,posts,required,status_codes"
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
			httpstep->name_esc = DBdyn_escape_string(row[2]);
			httpstep->no = atoi(row[3]);
			httpstep->url_esc = DBdyn_escape_string(row[4]);
			httpstep->timeout = atoi(row[5]);
			httpstep->posts_esc = DBdyn_escape_string(row[6]);
			httpstep->required_esc = DBdyn_escape_string(row[7]);
			httpstep->status_codes_esc = DBdyn_escape_string(row[8]);
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
	char		*sql = NULL, *sql1 = NULL, *sql2 = NULL, *sql3 = NULL, *sql4 = NULL, *sql5 = NULL;
	size_t		sql_alloc = 2 * ZBX_KIBIBYTE, sql_offset = 0,
			sql1_alloc = 512, sql1_offset = 0,
			sql2_alloc = 512, sql2_offset = 0,
			sql3_alloc = 512, sql3_offset = 0,
			sql4_alloc = 512, sql4_offset = 0,
			sql5_alloc = 512, sql5_offset = 0;
	httptest_t	*httptest;
	httpstep_t	*httpstep;
	httptestitem_t	*httptestitem;
	httpstepitem_t	*httpstepitem;
	zbx_uint64_t	httptestid = 0, httpstepid = 0, httptestitemid = 0, httpstepitemid = 0;
	int		i, j, k, num_httptests = 0, num_httpsteps = 0, num_httptestitems = 0, num_httpstepitems = 0;
	const char	*ins_httptest_sql =
			"insert into httptest"
			" (httptestid,name,applicationid,delay,status,macros,agent,"
				"authentication,http_user,http_password,http_proxy,hostid,templateid)"
			" values ";
	const char	*ins_httpstep_sql =
			"insert into httpstep"
			" (httpstepid,httptestid,name,no,url,timeout,posts,required,status_codes)"
			" values ";
	const char	*ins_httptestitem_sql =
			"insert into httptestitem (httptestitemid,httptestid,itemid,type) values ";
	const char	*ins_httpstepitem_sql =
			"insert into httpstepitem (httpstepitemid,httpstepid,itemid,type) values ";

	if (0 == httptests->values_num)
		return;

	sql = zbx_malloc(sql, sql_alloc);

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

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

		sql1 = zbx_malloc(sql1, sql1_alloc);
#ifdef HAVE_MULTIROW_INSERT
		zbx_strcpy_alloc(&sql1, &sql1_alloc, &sql1_offset, ins_httptest_sql);
#endif
	}

	if (httptests->values_num != num_httptests)
		sql2 = zbx_malloc(sql2, sql2_alloc);

	if (0 != num_httpsteps)
	{
		httpstepid = DBget_maxid_num("httpstep", num_httpsteps);

		sql3 = zbx_malloc(sql3, sql3_alloc);
#ifdef HAVE_MULTIROW_INSERT
		zbx_strcpy_alloc(&sql3, &sql3_alloc, &sql3_offset, ins_httpstep_sql);
#endif
	}

	if (0 != num_httptestitems)
	{
		httptestitemid = DBget_maxid_num("httptestitem", num_httptestitems);

		sql4 = zbx_malloc(sql4, sql4_alloc);
#ifdef HAVE_MULTIROW_INSERT
		zbx_strcpy_alloc(&sql4, &sql4_alloc, &sql4_offset, ins_httptestitem_sql);
#endif
	}

	if (0 != num_httpstepitems)
	{
		httpstepitemid = DBget_maxid_num("httpstepitem", num_httpstepitems);

		sql5 = zbx_malloc(sql5, sql5_alloc);
#ifdef HAVE_MULTIROW_INSERT
		zbx_strcpy_alloc(&sql5, &sql5_alloc, &sql5_offset, ins_httpstepitem_sql);
#endif
	}

	for (i = 0; i < httptests->values_num; i++)
	{
		httptest = (httptest_t *)httptests->values[i];

		if (0 == httptest->httptestid)
		{
			httptest->httptestid = httptestid++;

#ifndef HAVE_MULTIROW_INSERT
			zbx_strcpy_alloc(&sql1, &sql1_alloc, &sql1_offset, ins_httptest_sql);
#endif
			zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset,
					"(" ZBX_FS_UI64 ",'%s',%s,%d,%d,'%s','%s',%d,'%s','%s','%s',"
						ZBX_FS_UI64 "," ZBX_FS_UI64 ")" ZBX_ROW_DL,
					httptest->httptestid, httptest->name_esc, DBsql_id_ins(httptest->h_applicationid),
					httptest->delay, (int)httptest->status, httptest->macros_esc,
					httptest->agent_esc, (int)httptest->authentication, httptest->http_user_esc,
					httptest->http_password_esc, httptest->http_proxy_esc,
					hostid, httptest->templateid);

			for (j = 0; j < httptest->httpsteps.values_num; j++)
			{
				httpstep = (httpstep_t *)httptest->httpsteps.values[j];
#ifndef HAVE_MULTIROW_INSERT
				zbx_strcpy_alloc(&sql3, &sql3_alloc, &sql3_offset, ins_httpstep_sql);
#endif
				zbx_snprintf_alloc(&sql3, &sql3_alloc, &sql3_offset,
						"(" ZBX_FS_UI64 "," ZBX_FS_UI64 ",'%s',%d,'%s',"\
							"%d,'%s','%s','%s')" ZBX_ROW_DL,
						httpstepid, httptest->httptestid, httpstep->name_esc, httpstep->no,
						httpstep->url_esc, httpstep->timeout, httpstep->posts_esc,
						httpstep->required_esc, httpstep->status_codes_esc);

				for (k = 0; k < httpstep->httpstepitems.values_num; k++)
				{
					httpstepitem = (httpstepitem_t *)httpstep->httpstepitems.values[k];
#ifndef HAVE_MULTIROW_INSERT
					zbx_strcpy_alloc(&sql5, &sql5_alloc, &sql5_offset, ins_httpstepitem_sql);
#endif
					zbx_snprintf_alloc(&sql5, &sql5_alloc, &sql5_offset,
							"(" ZBX_FS_UI64 "," ZBX_FS_UI64 "," ZBX_FS_UI64 ",%d)"
							ZBX_ROW_DL, httpstepitemid, httpstepid, httpstepitem->h_itemid,
							(int)httpstepitem->type);

					httpstepitemid++;
				}

				httpstepid++;
			}

			for (j = 0; j < httptest->httptestitems.values_num; j++)
			{
				httptestitem = (httptestitem_t *)httptest->httptestitems.values[j];
#ifndef HAVE_MULTIROW_INSERT
				zbx_strcpy_alloc(&sql4, &sql4_alloc, &sql4_offset, ins_httptestitem_sql);
#endif
				zbx_snprintf_alloc(&sql4, &sql4_alloc, &sql4_offset,
						"(" ZBX_FS_UI64 "," ZBX_FS_UI64 "," ZBX_FS_UI64 ",%d)" ZBX_ROW_DL,
						httptestitemid, httptest->httptestid, httptestitem->h_itemid,
						(int)httptestitem->type);
				httptestitemid++;
			}
		}
		else
		{
			zbx_snprintf_alloc(&sql2, &sql2_alloc, &sql2_offset,
					"update httptest"
					" set templateid=" ZBX_FS_UI64
					" where httptestid=" ZBX_FS_UI64 ";\n",
					httptest->templateid, httptest->httptestid);
		}
	}

	if (0 != num_httptests)
	{
#ifdef HAVE_MULTIROW_INSERT
		sql1_offset--;
		zbx_strcpy_alloc(&sql1, &sql1_alloc, &sql1_offset, ";\n");
#endif
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, sql1);
	}

	if (httptests->values_num != num_httptests)
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, sql2);

	if (0 != num_httpsteps)
	{
#ifdef HAVE_MULTIROW_INSERT
		sql3_offset--;
		zbx_strcpy_alloc(&sql3, &sql3_alloc, &sql3_offset, ";\n");
#endif
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, sql3);
	}

	if (0 != num_httptestitems)
	{
#ifdef HAVE_MULTIROW_INSERT
		sql4_offset--;
		zbx_strcpy_alloc(&sql4, &sql4_alloc, &sql4_offset, ";\n");
#endif
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, sql4);
	}

	if (0 != num_httpstepitems)
	{
#ifdef HAVE_MULTIROW_INSERT
		sql5_offset--;
		zbx_strcpy_alloc(&sql5, &sql5_alloc, &sql5_offset, ";\n");
#endif
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, sql5);
	}

	DBend_multiple_update(&sql, &sql_alloc, &sql_offset);
	DBexecute("%s", sql);

	zbx_free(sql5);
	zbx_free(sql4);
	zbx_free(sql3);
	zbx_free(sql2);
	zbx_free(sql1);
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

		zbx_free(httptest->http_proxy_esc);
		zbx_free(httptest->http_password_esc);
		zbx_free(httptest->http_user_esc);
		zbx_free(httptest->agent_esc);
		zbx_free(httptest->macros_esc);
		zbx_free(httptest->name_esc);

		for (j = 0; j < httptest->httpsteps.values_num; j++)
		{
			httpstep = (httpstep_t *)httptest->httpsteps.values[j];

			zbx_free(httpstep->status_codes_esc);
			zbx_free(httpstep->required_esc);
			zbx_free(httpstep->posts_esc);
			zbx_free(httpstep->url_esc);
			zbx_free(httpstep->name_esc);

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
static void	DBcopy_template_httptests(zbx_uint64_t hostid, zbx_vector_uint64_t *templateids)
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
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget to sync the code with PHP !!!                   *
 *                                                                            *
 ******************************************************************************/
int	DBcopy_template_elements(zbx_uint64_t hostid, zbx_vector_uint64_t *lnk_templateids)
{
	const char		*__function_name = "DBcopy_template_elements";
	zbx_vector_uint64_t	templateids;
	zbx_uint64_t		hosttemplateid;
	int			i, res = SUCCEED;
	char			error[MAX_STRING_LEN];

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_uint64_create(&templateids);

	get_templates_by_hostid(hostid, &templateids);

	for (i = 0; i < lnk_templateids->values_num; i++)
	{
		if (FAIL != zbx_vector_uint64_bsearch(&templateids, lnk_templateids->values[i],
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
		zabbix_log(LOG_LEVEL_WARNING, "cannot link template: %s", error);
		goto clean;
	}

	if (SUCCEED != (res = validate_host(hostid, lnk_templateids, error, sizeof(error))))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot link template: %s", error);
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
 * Function: DBdelete_host                                                    *
 *                                                                            *
 * Purpose: delete host from database with all elements                       *
 *                                                                            *
 * Parameters: hostid - host identificator from database                      *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget to sync the code with PHP !!!                   *
 *                                                                            *
 ******************************************************************************/
void	DBdelete_host(zbx_uint64_t hostid)
{
	const char		*__function_name = "DBdelete_host";
	DB_RESULT		result;
	DB_ROW			row;
	zbx_uint64_t		elementid;
	zbx_vector_uint64_t	itemids, httptestids;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_uint64_create(&httptestids);
	zbx_vector_uint64_create(&itemids);

	/* delete web tests */
	result = DBselect(
			"select distinct ht.httptestid"
			" from httptest ht,applications a"
			" where ht.applicationid=a.applicationid"
				" and a.hostid=" ZBX_FS_UI64,
			hostid);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(elementid, row[0]);
		zbx_vector_uint64_append(&httptestids, elementid);
	}
	DBfree_result(result);

	zbx_vector_uint64_sort(&httptestids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	DBdelete_httptests(&httptestids);

	/* delete items -> triggers -> graphs */
	result = DBselect(
			"select itemid"
			" from items"
			" where hostid=" ZBX_FS_UI64,
			hostid);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(elementid, row[0]);
		zbx_vector_uint64_append(&itemids, elementid);
	}
	DBfree_result(result);

	zbx_vector_uint64_sort(&itemids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	DBdelete_items(&itemids);

	zbx_vector_uint64_destroy(&itemids);
	zbx_vector_uint64_destroy(&httptestids);

	/* delete host from maps */
	DBdelete_sysmaps_elements(SYSMAP_ELEMENT_TYPE_HOST, &hostid, 1);

	/* delete action conditions */
	DBdelete_action_conditions(CONDITION_TYPE_HOST, hostid);

	/* delete host */
	DBexecute("delete from hosts where hostid=" ZBX_FS_UI64, hostid);

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
		substitute_simple_macros(NULL, &hostid, NULL, NULL, NULL, &tmp, MACRO_TYPE_COMMON, NULL, 0);
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
