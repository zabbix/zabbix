/*
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
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
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/

#include "common.h"

#include "db.h"
#include "log.h"
#include "dbcache.h"

/******************************************************************************
 *                                                                            *
 * Type: ZBX_GRAPH_ITEMS                                                      *
 *                                                                            *
 * Purpose: represent graph item data                                         *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 ******************************************************************************/
typedef struct
{
	zbx_uint64_t	gitemid, itemid;
	char		key[ITEM_KEY_LEN_MAX];
	int		drawtype;
	int		sortorder;
	char		color[GRAPH_ITEM_COLOR_LEN_MAX];
	int		yaxisside;
	int		calc_fnc;
	int		type;
	int		periods_cnt;
}
ZBX_GRAPH_ITEMS;

/******************************************************************************
 *                                                                            *
 * Function: validate_template                                                *
 *                                                                            *
 * Description: Check collisions between templates                            *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Parameters: templateids - array of templates identificators from database  *
 *             templateids_num - templates count in templateids array         *
 *                                                                            *
 * Return value: SUCCEED if no collisions found                               *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
static int	validate_template(zbx_uint64_t *templateids, int templateids_num,
		char *error, int max_error_len)
{
	DB_RESULT	result;
	DB_ROW		row;
	char		*sql = NULL;
	int		sql_offset, sql_alloc = 256,
			ret = SUCCEED;
	zbx_uint64_t	*ids = NULL, id;
	int		ids_alloc = 0, ids_num;

	if (templateids_num < 2)
		return ret;

	sql = zbx_malloc(sql, sql_alloc);

	/* applications */
	if (SUCCEED == ret)
	{
		sql_offset = 0;
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 128,
				"select name,count(*)"
				" from applications"
				" where");

		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset,
				"hostid", templateids, templateids_num);

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 64,
				" group by name"
				" having count(*)>1");

		result = DBselect("%s", sql);

		if (NULL != (row = DBfetch(result)))
		{
			ret = FAIL;
			zbx_snprintf(error, max_error_len,
					"Template with application [%s]"
					" already linked to the host", row[0]);
		}
		DBfree_result(result);
	}

	/* items */
	if (SUCCEED == ret)
	{
		sql_offset = 0;
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 128,
				"select key_,count(*)"
				" from items"
				" where");

		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset,
				"hostid", templateids, templateids_num);

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 64,
				" group by key_"
				" having count(*)>1");

		result = DBselect("%s", sql);

		if (NULL != (row = DBfetch(result)))
		{
			ret = FAIL;
			zbx_snprintf(error, max_error_len,
					"Template with item key [%s]"
					" already linked to the host", row[0]);
		}
		DBfree_result(result);
	}

	/* graphs */
	if (SUCCEED == ret)
	{
		/* select all linked graphs */
		sql_offset = 0;
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 128,
				"select distinct gi.graphid"
				" from graphs_items gi,items i"
				" where gi.itemid=i.itemid"
					" and");

		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset,
				"i.hostid", templateids, templateids_num);

		result = DBselect("%s", sql);

		ids_num = 0;

		while (NULL != (row = DBfetch(result)))
		{
			ZBX_STR2UINT64(id, row[0]);
			uint64_array_add(&ids, &ids_alloc, &ids_num, id, 4);
		}
		DBfree_result(result);

		/* check for names */
		if (ids_num > 1)
		{
			sql_offset = 0;
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 128,
					"select name,count(*)"
					" from graphs"
					" where");

			DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset,
					"graphid", ids, ids_num);

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 64,
					" group by name"
					" having count(*)>1");

			result = DBselect("%s", sql);

			if (NULL != (row = DBfetch(result)))
			{
				ret = FAIL;
				zbx_snprintf(error, max_error_len,
						"Template with graph [%s]"
						" already linked to the host",
						row[0]);
			}
			DBfree_result(result);
		}
	}

	zbx_free(ids);
	zbx_free(sql);

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
 * Comments: !!! Don't forget sync code with PHP !!!                          *
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
				" and i1.key_=i2.key_ "
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

static void	DBget_graphitems(const char *sql, ZBX_GRAPH_ITEMS **gitems,
		int *gitems_alloc, int *gitems_num)
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
		gitem->periods_cnt = atoi(row[9]);

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
 *          Also assigns appropriate identifiers (gitemid,itemid)             *
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
 * Comments: !!! Don't forget sync code with PHP !!!                          *
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
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s",
			__function_name, zbx_result_string(res));

	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: validate_host                                                    *
 *                                                                            *
 * Description: Check collisions between host and linked template             *
 *                                                                            *
 * Parameters: hostid - host identificator from database                      *
 *             templateid - template identificator from database              *
 *                                                                            *
 * Return value: SUCCEED if no collisions found                               *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
static int	validate_host(zbx_uint64_t hostid, zbx_uint64_t templateid,
		char *error, int max_error_len)
{
	DB_RESULT	tresult;
	DB_RESULT	hresult;
	DB_ROW		trow;
	DB_ROW		hrow;
	char		*sql = NULL, *name_esc;
	int		sql_offset, sql_alloc = 256;
	ZBX_GRAPH_ITEMS *gitems = NULL, *chd_gitems = NULL;
	int		gitems_alloc = 0, gitems_num = 0,
			chd_gitems_alloc = 0, chd_gitems_num = 0,
			res = SUCCEED;
	zbx_uint64_t	graphid;

	sql = zbx_malloc(sql, sql_alloc);

	tresult = DBselect(
			"select distinct g.graphid,g.name"
			" from graphs g,graphs_items gi,items i"
			" where g.graphid=gi.graphid"
				" and gi.itemid=i.itemid"
				" and i.hostid=" ZBX_FS_UI64,
			templateid);

	while (SUCCEED == res && NULL != (trow = DBfetch(tresult)))
	{
		ZBX_STR2UINT64(graphid, trow[0]);

		sql_offset = 0;
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 256,
				"select 0,0,i.key_,gi.drawtype,gi.sortorder,"
					"gi.color,gi.yaxisside,gi.calc_fnc,"
					"gi.type,gi.periods_cnt"
				" from graphs_items gi,items i"
				" where gi.itemid=i.itemid"
					" and gi.graphid=" ZBX_FS_UI64
				" order by i.key_",
				graphid);

		DBget_graphitems(sql, &gitems, &gitems_alloc, &gitems_num);

		name_esc = DBdyn_escape_string(trow[1]);

		hresult = DBselect(
				"select distinct g.graphid"
				" from graphs g,graphs_items gi,items i "
				" where g.graphid=gi.graphid"
					" and gi.itemid=i.itemid"
					" and i.hostid=" ZBX_FS_UI64
					" and g.name='%s'"
					" and g.templateid=0",
				hostid, name_esc);

		zbx_free(name_esc);

		/* compare graphs */
		while (NULL != (hrow = DBfetch(hresult)))
		{
			ZBX_STR2UINT64(graphid, hrow[0]);

			sql_offset = 0;
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 256,
					"select gi.gitemid,i.itemid,i.key_,"
						"gi.drawtype,gi.sortorder,"
						"gi.color,gi.yaxisside,"
						"gi.calc_fnc,gi.type,"
						"gi.periods_cnt"
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
						"Graph [%s] already exists on the host (items are not identical)",
						trow[1]);
				break;	/* found graph with equal name, but items are not identical*/
			}
		}
		DBfree_result(hresult);
	}
	DBfree_result(tresult);

	zbx_free(sql);
	zbx_free(gitems);
	zbx_free(chd_gitems);

	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: DBget_same_applications_by_itemid                                *
 *                                                                            *
 * Purpose: retrieve same applications for specified templated item           *
 *                                                                            *
 * Parameters:  hostid - host identificator from database                     *
 *              template_itemid - template item identificator from database   *
 *              appids - result buffer                                        *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
static int	DBget_same_applications_by_itemid(zbx_uint64_t hostid,
		zbx_uint64_t itemid, zbx_uint64_t template_itemid,
		zbx_uint64_t **appids, int *appids_alloc, int *appids_num)
{
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	applicationid;

	result = DBselect(
			"select hi.itemappid,ha.applicationid"
			" from applications ha"
			" join applications ta"
				" on ta.name=ha.name"
			" join items_applications ti"
				" on ti.applicationid=ta.applicationid"
					" and ti.itemid=" ZBX_FS_UI64
			" left join items_applications hi"
				" on hi.applicationid=ha.applicationid"
					" and hi.itemid=" ZBX_FS_UI64
			" where ha.hostid=" ZBX_FS_UI64
				" and hi.itemappid is null",
			template_itemid, itemid, hostid);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(applicationid, row[1]);
		uint64_array_add(appids, appids_alloc, appids_num, applicationid, 4);
	}
	DBfree_result(result);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: DBclear_parents_from_trigger                                     *
 *                                                                            *
 * Purpose: removes any links between trigger and service if service          *
 *          is not leaf (treenode)                                            *
 *                                                                            *
 * Parameters: serviceid - id of service                                      *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
static void	DBclear_parents_from_trigger(zbx_uint64_t serviceid)
{
	DB_RESULT	result;
	DB_ROW		row;

	if (0 != serviceid)
	{
		DBexecute("update services"
				" set triggerid=null"
				" where serviceid=" ZBX_FS_UI64,
				serviceid);
	}
	else
	{
		result = DBselect("select s.serviceid"
					" from services s,services_links sl"
					" where s.serviceid = sl.serviceupid"
						" and s.triggerid is not null"
					" group by s.serviceid");

		while (NULL != (row = DBfetch(result)))
		{
			ZBX_STR2UINT64(serviceid, row[0]);

			DBexecute("update services"
					" set triggerid=null"
					" where serviceid=" ZBX_FS_UI64,
					serviceid);
		}
		DBfree_result(result);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: DBget_service_status                                             *
 *                                                                            *
 * Purpose: retrieve true status                                              *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
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
 *           !!! Don't forget sync code with PHP !!!                          *
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
			DBexecute("update services set status=%d where serviceid=" ZBX_FS_UI64,
				status, serviceupid);
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
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
static void	DBupdate_services_status_all(void)
{
	DB_RESULT	result;
	DB_ROW		row;

	zbx_uint64_t	serviceid = 0, triggerid = 0;
	int		status = 0, clock;

	DBclear_parents_from_trigger(0);

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
 * Comments: !!! Don't forget sync code with PHP !!!                          *
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
 * Function: DBdelete_services_by_triggerid                                   *
 *                                                                            *
 * Purpose: delete triggers from service                                      *
 *                                                                            *
 * Parameters: triggerid - trigger identificator from database                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
static void	DBdelete_services_by_triggerid(zbx_uint64_t triggerid)
{
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	serviceid;
	char		*sql = NULL;
	int		sql_offset = 0, sql_alloc = 256;

	sql = zbx_malloc(sql, sql_alloc);

#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 8, "begin\n");
#endif

	result = DBselect(
			"select serviceid"
			" from services"
			" where triggerid=" ZBX_FS_UI64,
			triggerid);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(serviceid, row[0]);

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 92,
				"delete from service_alarms"
				" where serviceid=" ZBX_FS_UI64 ";\n",
				serviceid);

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 92,
				"delete from services_links"
				" where servicedownid=" ZBX_FS_UI64
					" or serviceupid=" ZBX_FS_UI64 ";\n",
				serviceid, serviceid);

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 92,
				"delete from services"
				" where serviceid=" ZBX_FS_UI64 ";\n",
				serviceid);

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 92,
				"delete from services_times"
				" where serviceid=" ZBX_FS_UI64 ";\n",
				serviceid);
	}
	DBfree_result(result);

#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 8, "end;\n");
#endif

	if (sql_offset > 16)	/* In ORACLE always present begin..end; */
		DBexecute("%s", sql);

	zbx_free(sql);

	DBupdate_services_status_all();
}

/******************************************************************************
 *                                                                            *
 * Function: DBdelete_sysmaps_element                                         *
 *                                                                            *
 * Purpose: delete specified map element                                      *
 *                                                                            *
 * Parameters: selementid - map element identificator from database           *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
static void	DBdelete_sysmaps_element(zbx_uint64_t selementid)
{
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	linkid;

	result = DBselect(
			"select linkid"
			" from sysmaps_links"
			" where " ZBX_FS_UI64 " in (selementid1,selementid2)",
			selementid);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(linkid, row[0]);
		DBexecute("delete from sysmaps_links where linkid=" ZBX_FS_UI64, linkid);
		DBexecute("delete from sysmaps_link_triggers where linkid=" ZBX_FS_UI64, linkid);
	}
	DBfree_result(result);

	DBexecute("delete from sysmaps_elements where selementid=" ZBX_FS_UI64, selementid);
}

/******************************************************************************
 *                                                                            *
 * Function: DBdelete_sysmaps_elements                                        *
 *                                                                            *
 * Purpose: delete elements from map by elementtype and elementid             *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
static void	DBdelete_sysmaps_elements(int elementtype, zbx_uint64_t elementid)
{
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	selementid;

	result = DBselect(
			"select distinct selementid"
			" from sysmaps_elements"
			" where elementtype=%d"
				" and elementid=" ZBX_FS_UI64,
			elementtype, elementid);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(selementid, row[0]);

		DBdelete_sysmaps_element(selementid);
	}
	DBfree_result(result);
}

/******************************************************************************
 *                                                                            *
 * Function: DBdelete_action_conditions                                       *
 *                                                                            *
 * Purpose: delete action conditions by condition type and id                 *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
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
 * Function: DBdelete_trigger                                                 *
 *                                                                            *
 * Purpose: delete trigger from database                                      *
 *                                                                            *
 * Parameters: triggerid - trigger identificator from database                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
static void	DBdelete_trigger(zbx_uint64_t triggerid)
{
	char	*sql = NULL;
	int	sql_offset = 0, sql_alloc = 256;

	DBdelete_services_by_triggerid(triggerid);
	DBdelete_sysmaps_elements(SYSMAP_ELEMENT_TYPE_TRIGGER, triggerid);
	DBdelete_action_conditions(CONDITION_TYPE_TRIGGER, triggerid);

	sql = zbx_malloc(sql, sql_alloc);

#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 8, "begin\n");
#endif

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 92,
			"delete from trigger_depends"
			" where triggerid_down=" ZBX_FS_UI64 ";\n",
			triggerid);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 92,
			"delete from trigger_depends"
			" where triggerid_up=" ZBX_FS_UI64 ";\n",
			triggerid);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 92,
			"delete from functions"
			" where triggerid=" ZBX_FS_UI64 ";\n",
			triggerid);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 92,
			"delete from events"
			" where object=%d"
				" and objectid=" ZBX_FS_UI64 ";\n",
			EVENT_OBJECT_TRIGGER, triggerid);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 92,
			"delete from sysmaps_link_triggers"
			" where triggerid=" ZBX_FS_UI64 ";\n",
			triggerid);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 92,
			"delete from triggers"
			" where triggerid=" ZBX_FS_UI64 ";\n",
			triggerid);

#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 8, "end;\n");
#endif

	DBexecute("%s", sql);

	zbx_free(sql);
}

/******************************************************************************
 *                                                                            *
 * Function: DBdelete_triggers_by_itemids                                     *
 *                                                                            *
 * Purpose: delete triggers by itemid                                         *
 *                                                                            *
 * Parameters: itemids     - [IN] item identificators from database           *
 *             itemids_num - [IN] number of items                             *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
static void	DBdelete_triggers_by_itemids(zbx_uint64_t *itemids, int itemids_num)
{
	const char	*__function_name = "DBdelete_triggers_by_itemids";
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	triggerid;
	char		*sql = NULL;
	int		sql_offset = 0, sql_alloc = 512;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() itemids_num:%d",
			__function_name, itemids_num);

	if (0 == itemids_num)
		return;

	sql = zbx_malloc(sql, sql_alloc);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 64,
			"select distinct triggerid"
			" from functions"
			" where");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset,
			"itemid", itemids, itemids_num);

	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(triggerid, row[0]);
		DBdelete_trigger(triggerid);
	}
	DBfree_result(result);

	zbx_free(sql);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: DBdelete_history_by_itemids                                      *
 *                                                                            *
 * Purpose: delete item history                                               *
 *                                                                            *
 * Parameters: itemids     - [IN] item identificators from database           *
 *             itemids_num - [IN] number of items                             *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Eugene Grigorjev, Alexander Vladishev                              *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
static void	DBdelete_history_by_itemids(zbx_uint64_t *itemids, int itemids_num)
{
	const char	*__function_name = "DBdelete_history_by_itemids";
	char		*sql = NULL;
	int		sql_offset = 0, sql_alloc = 65536, i;
#define	sql_st	"insert into housekeeper (housekeeperid,tablename,field,value)"	\
		" values (" ZBX_FS_UI64 ",'%s','itemid'," ZBX_FS_UI64 ");\n"
	zbx_uint64_t	housekeeperid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() itemids_num:%d",
			__function_name, itemids_num);

	if (0 == itemids_num)
		return;

	sql = zbx_malloc(sql, sql_alloc);

#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 8, "begin\n");
#endif

	housekeeperid = DBget_maxid_num("housekeeper", 7 * itemids_num);

	for (i = 0; i < itemids_num; i++)
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 192, sql_st, housekeeperid++, "history", itemids[i]);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 192, sql_st, housekeeperid++, "history_str", itemids[i]);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 192, sql_st, housekeeperid++, "history_uint", itemids[i]);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 192, sql_st, housekeeperid++, "history_log", itemids[i]);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 192, sql_st, housekeeperid++, "history_text", itemids[i]);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 192, sql_st, housekeeperid++, "trends", itemids[i]);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 192, sql_st, housekeeperid++, "trends_uint", itemids[i]);

		DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
	}

#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 8, "end;\n");
#endif

	if (sql_offset > 16)	/* In ORACLE always present begin..end; */
		DBexecute("%s", sql);

	zbx_free(sql);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: DBdelete_graphs                                                  *
 *                                                                            *
 * Purpose: delete graph from database                                        *
 *                                                                            *
 * Parameters: graphids     - [IN] array of graph id's from database          *
 *             graphids_num - [IN] number of array elements                   *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *                                                                            *
 * Author: Eugene Grigorjev, Alexander Vladishev                              *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
static void	DBdelete_graphs(zbx_uint64_t *graphids, int graphids_num)
{
	const char	*__function_name = "DBdelete_graphs";
	char		*sql = NULL;
	int		sql_alloc = 256, sql_offset = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() graphids_num:%d",
			__function_name, graphids_num);

	if (0 == graphids_num)
		return;

	sql = zbx_malloc(sql, sql_alloc);

#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 8, "begin\n");
#endif

	/* delete graph */
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 64,
			"delete from screens_items"
			" where resourcetype=%d"
				" and",
			SCREEN_RESOURCE_GRAPH);
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset,
			"resourceid", graphids, graphids_num);
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 3, ";\n");

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 64,
			"delete from graphs_items"
			" where");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset,
			"graphid", graphids, graphids_num);
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 3, ";\n");

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 64,
			"delete from graphs"
			" where");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset,
			"graphid", graphids, graphids_num);
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 3, ";\n");

#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 8, "end;\n");
#endif

	DBexecute("%s", sql);

	zbx_free(sql);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: DBdelete_items                                                   *
 *                                                                            *
 * Purpose: delete items from database                                        *
 *                                                                            *
 * Parameters: itemids     - [IN] array of item identificators from database  *
 *             itemids_num - [IN] number of item ids                          *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
static void	DBdelete_items(zbx_uint64_t *itemids, int itemids_num)
{
	const char	*__function_name = "DBdelete_items";
	char		*sql = NULL;
	int		sql_offset = 0, sql_alloc = 256;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() itemids_num:%d",
			__function_name, itemids_num);

	if (0 == itemids_num)
		return;

	DBdelete_triggers_by_itemids(itemids, itemids_num);
	DBdelete_history_by_itemids(itemids, itemids_num);

	sql = zbx_malloc(sql, sql_alloc);
#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 8, "begin\n");
#endif

/* delete from screens_items */
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 64,
			"delete from screens_items"
				" where resourcetype in (%d,%d)"
					" and",
			SCREEN_RESOURCE_PLAIN_TEXT,
			SCREEN_RESOURCE_SIMPLE_GRAPH);
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset,
			"resourceid", itemids, itemids_num);
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 3, ";\n");

/* delete from graphs_items */
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 64,
			"delete from graphs_items where");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset,
			"itemid", itemids, itemids_num);
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 3, ";\n");

/* delete from items_applications */
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 64,
			"delete from items_applications where");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset,
			"itemid", itemids, itemids_num);
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 3, ";\n");

/* delete from profiles */
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 92,
			"delete from profiles"
			" where idx='web.favorite.graphids'"
				" and source='itemid'"
				" and");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset,
			"value_id", itemids, itemids_num);
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 3, ";\n");

/* delete from items */
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 32,
			"delete from items where");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset,
			"itemid", itemids, itemids_num);
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 3, ";\n");

#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 8, "end;\n");
#endif

	DBexecute("%s", sql);

	zbx_free(sql);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: DBdelete_httptests                                               *
 *                                                                            *
 * Purpose: delete web tests from database                                    *
 *                                                                            *
 * Parameters: htids     - [IN] array of httptest id's from database          *
 *             htids_num - [IN] number of array elements                      *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
static void	DBdelete_httptests(zbx_uint64_t *htids, int htids_num)
{
	const char	*__function_name = "DBdelete_httptests";
	DB_RESULT	result;
	DB_ROW		row;
	char		*sql = NULL;
	int		sql_offset, sql_alloc = 256;
	zbx_uint64_t	httpstepid, itemid;
	zbx_uint64_t	*hsids = NULL, *itemids = NULL;
	int		hsids_alloc = 0, hsids_num = 0,
			itemids_alloc = 0, itemids_num = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() htids_num:%d",
			__function_name, htids_num);

	if (0 == htids_num)
		return;

	sql = zbx_malloc(sql, sql_alloc);

/* httpstep's */
	sql_offset = 0;
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 32,
			"select httpstepid"
			" from httpstep"
			" where");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset,
			"httptestid", htids, htids_num);

	result = DBselect("%s", sql);

	if (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(httpstepid, row[0]);
		uint64_array_add(&hsids, &hsids_alloc, &hsids_num, httpstepid, 8);
	}
	DBfree_result(result);

/* httpstepitem's */
	sql_offset = 0;
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 32,
			"select itemid"
			" from httpstepitem"
			" where");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset,
			"httpstepid", hsids, hsids_num);

	result = DBselect("%s", sql);

	if (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(itemid, row[0]);
		uint64_array_add(&itemids, &itemids_alloc, &itemids_num,
				itemid, 8);
	}
	DBfree_result(result);

/* httptestitem's */
	sql_offset = 0;
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 32,
			"select itemid"
			" from httptestitem"
			" where");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset,
			"httptestid", htids, htids_num);

	result = DBselect("%s", sql);

	if (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(itemid, row[0]);
		uint64_array_add(&itemids, &itemids_alloc, &itemids_num,
				itemid, 8);
	}
	DBfree_result(result);

	sql_offset = 0;
#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 8, "begin\n");
#endif

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 32,
			"delete from httptest where");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset,
			"httptestid", htids, htids_num);
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 3, ";\n");

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 32,
			"delete from httpstep where");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset,
			"httptestid", htids, htids_num);
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 3, ";\n");

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 32,
			"delete from httpstepitem where");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset,
			"httpstepid", hsids, hsids_num);
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 3, ";\n");

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 32,
			"delete from httptestitem where");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset,
			"httptestid", htids, htids_num);
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 3, ";\n");

#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 8, "end;\n");
#endif

	DBexecute("%s", sql);

	DBdelete_items(itemids, itemids_num);

	zbx_free(hsids);
	zbx_free(itemids);
	zbx_free(sql);

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
 * Return value:                                                              *
 *                                                                            *
 * Author: Eugene Grigorjev, Alexander Vladishev                              *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
static void	DBdelete_applications(zbx_uint64_t *applicationids, int applicationids_num)
{
	DB_RESULT	result;
	DB_ROW		row;
	char		*sql = NULL;
	int		sql_offset, sql_alloc = 256;
	zbx_uint64_t	*ids = NULL, applicationid;
	int		ids_alloc = 0, ids_num = 0;

	if (0 == applicationids_num)
		return;

	sql = zbx_malloc(sql, sql_alloc);

	sql_offset = 0;
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 64,
			"select applicationid,name"
			" from httptest"
			" where");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset,
			"applicationid", applicationids, applicationids_num);

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
#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 8, "begin\n");
#endif

	if (0 != applicationids_num)
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 64,
				"delete from items_applications where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset,
				"applicationid", applicationids, applicationids_num);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 3, ";\n");

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 64,
				"delete from applications where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset,
				"applicationid", applicationids, applicationids_num);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 3, ";\n");
	}

	if (0 != ids_num)
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 64,
				"update applications set templateid=0 where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset,
				"applicationid", ids, ids_num);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 3, ";\n");
	}

#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 8, "end;\n");
#endif

	DBexecute("%s", sql);

	zbx_free(ids);
	zbx_free(sql);
}

/******************************************************************************
 *                                                                            *
 * Function: DBdelete_template_graphs                                         *
 *                                                                            *
 * Purpose: delete template graphs from host                                  *
 *                                                                            *
 * Parameters: hostid - host identificator from database                      *
 *             templateid - template identificator from database              *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
static void	DBdelete_template_graphs(zbx_uint64_t hostid, zbx_uint64_t templateid)
{
	const char	*__function_name = "DBdelete_template_graphs";
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	*graphids = NULL, graphid;
	int		graphids_alloc = 0, graphids_num = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	result = DBselect(
			"select distinct gi.graphid"
			" from graphs_items gi,items i,items ti"
			" where gi.itemid=i.itemid"
				" and i.templateid=ti.itemid"
				" and i.hostid=" ZBX_FS_UI64
				" and ti.hostid=" ZBX_FS_UI64,
			hostid, templateid);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(graphid, row[0]);
		uint64_array_add(&graphids, &graphids_alloc, &graphids_num, graphid, 4);
	}
	DBfree_result(result);

	DBdelete_graphs(graphids, graphids_num);

	zbx_free(graphids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: DBdelete_template_triggers                                       *
 *                                                                            *
 * Purpose: delete template triggers from host                                *
 *                                                                            *
 * Parameters: hostid - host identificator from database                      *
 *             templateid - template identificator from database              *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
static void	DBdelete_template_triggers(zbx_uint64_t hostid, zbx_uint64_t templateid)
{
	const char	*__function_name = "DBdelete_template_triggers";
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	triggerid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	result = DBselect(
			"select distinct f.triggerid"
			" from functions f,items i,items ti"
			" where f.itemid=i.itemid"
				" and i.templateid=ti.itemid"
				" and i.hostid=" ZBX_FS_UI64
				" and ti.hostid=" ZBX_FS_UI64,
			hostid, templateid);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(triggerid, row[0]);
		DBdelete_trigger(triggerid);
	}
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: DBdelete_template_items                                          *
 *                                                                            *
 * Purpose: delete template items from host                                   *
 *                                                                            *
 * Parameters: hostid - host identificator from database                      *
 *             templateid - template identificator from database              *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
static void	DBdelete_template_items(zbx_uint64_t hostid,
		zbx_uint64_t templateid)
{
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	itemid;
	zbx_uint64_t	*itemids = NULL;
	int		itemids_alloc = 0, itemids_num = 0;

	result = DBselect(
			"select distinct i.itemid"
			" from items i,items ti"
			" where i.templateid=ti.itemid"
				" and i.hostid=" ZBX_FS_UI64
				" and ti.hostid=" ZBX_FS_UI64,
			hostid, templateid);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(itemid, row[0]);
		uint64_array_add(&itemids, &itemids_alloc, &itemids_num,
				itemid, 64);
	}
	DBfree_result(result);

	DBdelete_items(itemids, itemids_num);

	zbx_free(itemids);
}

/******************************************************************************
 *                                                                            *
 * Function: DBdelete_template_applications                                   *
 *                                                                            *
 * Purpose: delete application                                                *
 *                                                                            *
 * Parameters: hostid - host identificator from database                      *
 *             templateid - template identificator from database              *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
static void	DBdelete_template_applications(zbx_uint64_t hostid,
		zbx_uint64_t templateid)
{
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	*applicationids = NULL, applicationid;
	int		applicationids_alloc = 0, applicationids_num = 0;

	result = DBselect(
			"select distinct a.applicationid"
			" from applications a,applications ta"
			" where a.templateid=ta.applicationid"
				" and a.hostid=" ZBX_FS_UI64
				" and ta.hostid=" ZBX_FS_UI64,
			hostid, templateid);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(applicationid, row[0]);
		uint64_array_add(&applicationids, &applicationids_alloc, &applicationids_num,
				applicationid, 64);
	}
	DBfree_result(result);

	DBdelete_applications(applicationids, applicationids_num);

	zbx_free(applicationids);
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
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
static int	DBcopy_trigger_to_host(zbx_uint64_t *new_triggerid, zbx_uint64_t hostid,
		zbx_uint64_t triggerid, const char *description, const char *expression,
		unsigned char status, unsigned char type, unsigned char priority,
		const char *comments, const char *url)
{
	DB_RESULT	result;
	DB_ROW		row;
	char		*sql = NULL;
	int		sql_alloc = 256, sql_offset = 0;
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

#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 8, "begin\n");
#endif

	description_esc = DBdyn_escape_string(description);

	result = DBselect(
			"select distinct t.triggerid,t.expression"
			" from triggers t,functions f,items i"
			" where t.triggerid=f.triggerid"
				" and f.itemid=i.itemid"
				" and t.templateid=0"
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
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 92,
				"update triggers"
				" set templateid=" ZBX_FS_UI64
				" where triggerid=" ZBX_FS_UI64 ";\n",
				triggerid, h_triggerid);

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
						160 + strlen(function_esc) + strlen(parameter_esc),
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
			comments_esc = DBdyn_escape_string(comments);
			url_esc = DBdyn_escape_string(url);

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 192 +
					strlen(description_esc) + strlen(expression_esc) +
					strlen(comments_esc) + strlen(url_esc),
					"insert into triggers"
						" (triggerid,description,expression,priority,status,"
							"comments,url,type,value,templateid)"
						" values (" ZBX_FS_UI64 ",'%s','%s',%d,%d,"
							"'%s','%s',%d,%d," ZBX_FS_UI64 ");\n",
						*new_triggerid, description_esc, expression_esc,
						(int)priority, (int)status, comments_esc, url_esc,
						(int)type, TRIGGER_VALUE_UNKNOWN, triggerid);

			zbx_free(url_esc);
			zbx_free(comments_esc);
			zbx_free(expression_esc);
		}

		zbx_free(new_expression);
	}
	else
		*new_triggerid = 0;

#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 8, "end;\n");
#endif

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
 * Comments: !!! Don't forget sync code with PHP !!!                          *
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
	int		sql_alloc = 512, sql_offset;

	if (0 == trids_num)
		return SUCCEED;

	sql = zbx_malloc(sql, sql_alloc * sizeof(char));
	tpl_triggerids = zbx_malloc(tpl_triggerids, alloc * sizeof(zbx_uint64_t));
	hst_triggerids = zbx_malloc(hst_triggerids, alloc * sizeof(zbx_uint64_t));

	sql_offset = 0;
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 64,
			"select triggerid,templateid"
			" from triggers"
			" where");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "triggerid", trids, trids_num);

	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(triggerid, row[0]);
		ZBX_STR2UINT64(templateid, row[1]);

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
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 256,
			"select distinct td.triggerid_down,td.triggerid_up"
			" from triggers t,trigger_depends td"
			" where t.templateid in (td.triggerid_up,td.triggerid_down)"
				" and");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "t.triggerid", trids, trids_num);

	result = DBselect("%s", sql);

	sql_offset = 0;
#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 8, "begin\n");
#endif

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

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 160,
					"insert into trigger_depends"
					" (triggerdepid,triggerid_down,triggerid_up)"
					" values (" ZBX_FS_UI64 "," ZBX_FS_UI64 "," ZBX_FS_UI64 ");\n",
					triggerdepid, triggerid_down, triggerid_up);
		}
	}
	DBfree_result(result);

#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 8, "end;\n");
#endif

	if (sql_offset > 16)	/* In ORACLE always present begin..end; */
		DBexecute("%s", sql);

	zbx_free(hst_triggerids);
	zbx_free(tpl_triggerids);
	zbx_free(sql);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: DBdelete_template_elements                                       *
 *                                                                            *
 * Purpose: delete template elements from host                                *
 *                                                                            *
 * Parameters: hostid - host identificator from database                      *
 *             templateid - template identificator from database              *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
int	DBdelete_template_elements(zbx_uint64_t hostid, zbx_uint64_t templateid)
{
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	*graphids = NULL, graphid, hosttemplateid = 0;
	int		graphids_alloc = 0, graphids_num = 0;
	char		*sql = NULL;
	int		sql_alloc = 256, sql_offset;

	result = DBselect("select hosttemplateid from hosts_templates"
			" where hostid=" ZBX_FS_UI64
				" and templateid=" ZBX_FS_UI64,
			hostid, templateid);

	if (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(hosttemplateid, row[0]);
	}
	DBfree_result(result);

	if (0 == hosttemplateid)
		return SUCCEED;

	/* select graphs with host items */
	result = DBselect(
			"select distinct gi.graphid"
			" from graphs_items gi,items i"
			" where gi.itemid=i.itemid"
				" and i.hostid=" ZBX_FS_UI64,
			hostid);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(graphid, row[0]);
		uint64_array_add(&graphids, &graphids_alloc, &graphids_num,
				graphid, 4);
	}
	DBfree_result(result);

	DBdelete_template_graphs(hostid, templateid);
	DBdelete_template_triggers(hostid, templateid);
	DBdelete_template_items(hostid, templateid);
	DBdelete_template_applications(hostid, templateid);

	/* delete empty graphs */
	if (graphids_num != 0)
	{
		sql = zbx_malloc(sql, sql_alloc);

		sql_offset = 0;
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 92,
				"select distinct graphid"
				" from graphs_items"
				" where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset,
				"graphid", graphids, graphids_num);

		result = DBselect("%s", sql);

		while (NULL != (row = DBfetch(result)))
		{
			ZBX_STR2UINT64(graphid, row[0]);
			uint64_array_remove(graphids, &graphids_num, &graphid, 1);
		}
		DBfree_result(result);

		DBdelete_graphs(graphids, graphids_num);

		zbx_free(sql);
		zbx_free(graphids);
	}

	DBexecute("delete from hosts_templates"
			" where hosttemplateid=" ZBX_FS_UI64,
			hosttemplateid);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: DBcopy_template_applications                                     *
 *                                                                            *
 * Purpose: copy applications from template to host                           *
 *                                                                            *
 * Parameters: hostid - host identificator from database                      *
 *             templateid - template identificator from database              *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
static int	DBcopy_template_applications(zbx_uint64_t hostid,
		zbx_uint64_t templateid)
{
	const char	*__function_name = "DBcopy_template_applications";
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	template_applicationid, applicationid;
	char		*name_esc;
	char		*sql = NULL;
	int		sql_offset = 0, sql_alloc = 1024,
			res = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	result = DBselect(
			"select ta.applicationid,ta.name,ha.applicationid"
			" from applications ta"
			" left join applications ha"
				" on ha.name=ta.name"
					" and ha.hostid=" ZBX_FS_UI64
			" where ta.hostid=" ZBX_FS_UI64,
			hostid, templateid);

	sql = zbx_malloc(sql, sql_alloc);

#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 8, "begin\n");
#endif

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(template_applicationid, row[0]);

		if (SUCCEED != DBis_null(row[2]))
		{
			ZBX_STR2UINT64(applicationid, row[2]);

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 128,
					"update applications"
					" set templateid=" ZBX_FS_UI64
					" where applicationid=" ZBX_FS_UI64 ";\n",
					template_applicationid,
					applicationid);
		}
		else
		{
			applicationid = DBget_maxid("applications");

			name_esc = DBdyn_escape_string(row[1]);

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 512,
					"insert into applications"
						" (applicationid,hostid,name,templateid)"
					" values"
						" (" ZBX_FS_UI64 "," ZBX_FS_UI64 ",'%s'," ZBX_FS_UI64 ");\n",
					applicationid, hostid, name_esc, template_applicationid);

			zbx_free(name_esc);
		}
	}
	DBfree_result(result);

#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 8, "end;\n");
#endif

	if (sql_offset > 16)	/* In ORACLE always present begin..end; */
		DBexecute("%s", sql);

	zbx_free(sql);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(res));

	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: DBcopy_template_items                                            *
 *                                                                            *
 * Purpose: copy template items to host                                       *
 *                                                                            *
 * Parameters: hostid - host identificator from database                      *
 *             templateid - template identificator from database              *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
static int	DBcopy_template_items(zbx_uint64_t hostid, zbx_uint64_t templateid)
{
	const char	*__function_name = "DBcopy_template_items";
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	template_itemid, itemid, itemappid;
	char		*description_esc, *key_esc, *delay_flex_esc, *trapper_hosts_esc,
			*units_esc, *formula_esc, *logtimefmt_esc, *params_esc,
			*ipmi_sensor_esc, *snmp_community_esc, *snmp_oid_esc,
			*snmpv3_securityname_esc, *snmpv3_authpassphrase_esc,
			*snmpv3_privpassphrase_esc, *username_esc, *password_esc,
			*publickey_esc, *privatekey_esc;
	char		*sql = NULL;
	int		sql_offset = 0, sql_alloc = 16384,
			i, res = SUCCEED;
	zbx_uint64_t	*appids = NULL;
	int		appids_alloc = 0, appids_num;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	result = DBselect(
			"select ti.itemid,ti.description,ti.key_,ti.type,ti.value_type,"
				"ti.data_type,ti.delay,ti.delay_flex,ti.history,ti.trends,"
				"ti.status,ti.trapper_hosts,ti.units,ti.multiplier,"
				"ti.delta,ti.formula,ti.logtimefmt,ti.valuemapid,"
				"ti.params,ti.ipmi_sensor,ti.snmp_community,ti.snmp_oid,"
				"ti.snmp_port,ti.snmpv3_securityname,"
				"ti.snmpv3_securitylevel,ti.snmpv3_authpassphrase,"
				"ti.snmpv3_privpassphrase,ti.authtype,ti.username,"
				"ti.password,ti.publickey,ti.privatekey,hi.itemid"
			" from items ti"
			" left join items hi on hi.key_=ti.key_"
				" and hi.hostid=" ZBX_FS_UI64
			" where ti.hostid=" ZBX_FS_UI64,
			hostid, templateid);

	sql = zbx_malloc(sql, sql_alloc);

#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 8, "begin\n");
#endif

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(template_itemid, row[0]);

		description_esc			= DBdyn_escape_string(row[1]);
		delay_flex_esc			= DBdyn_escape_string(row[7]);
		trapper_hosts_esc		= DBdyn_escape_string(row[11]);
		units_esc			= DBdyn_escape_string(row[12]);
		formula_esc			= DBdyn_escape_string(row[15]);
		logtimefmt_esc			= DBdyn_escape_string(row[16]);
		params_esc			= DBdyn_escape_string(row[18]);
		ipmi_sensor_esc			= DBdyn_escape_string(row[19]);
		snmp_community_esc		= DBdyn_escape_string(row[20]);
		snmp_oid_esc			= DBdyn_escape_string(row[21]);
		snmpv3_securityname_esc		= DBdyn_escape_string(row[23]);
		snmpv3_authpassphrase_esc	= DBdyn_escape_string(row[25]);
		snmpv3_privpassphrase_esc	= DBdyn_escape_string(row[26]);
		username_esc			= DBdyn_escape_string(row[28]);
		password_esc			= DBdyn_escape_string(row[29]);
		publickey_esc			= DBdyn_escape_string(row[30]);
		privatekey_esc			= DBdyn_escape_string(row[31]);

		if (SUCCEED != (DBis_null(row[32])))
		{
			ZBX_STR2UINT64(itemid, row[32]);

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 8192,
					"update items"
						" set description='%s',"
						"type=%s,"
						"value_type=%s,"
						"data_type=%s,"
						"delay=%s,"
						"delay_flex='%s',"
						"history=%s,"
						"trends=%s,"
						"status=%s,"
						"trapper_hosts='%s',"
						"units='%s',"
						"multiplier=%s,"
						"delta=%s,"
						"formula='%s',"
						"logtimefmt='%s',"
						"valuemapid=%s,"
						"params='%s',"
						"ipmi_sensor='%s',"
						"snmp_community='%s',"
						"snmp_oid='%s',"
						"snmp_port=%s,"
						"snmpv3_securityname='%s',"
						"snmpv3_securitylevel=%s,"
						"snmpv3_authpassphrase='%s',"
						"snmpv3_privpassphrase='%s',"
						"authtype=%s,"
						"username='%s',"
						"password='%s',"
						"publickey='%s',"
						"privatekey='%s',"
						"templateid=" ZBX_FS_UI64
					" where itemid=" ZBX_FS_UI64 ";\n",
					description_esc,
					row[3],		/* type */
					row[4],		/* value_type */
					row[5],		/* data_type */
					row[6],		/* delay */
					delay_flex_esc,
					row[8],		/* history */
					row[9],		/* trends */
					row[10],	/* status */
					trapper_hosts_esc,
					units_esc,
					row[13],	/* multiplier */
					row[14],	/* delta */
					formula_esc,
					logtimefmt_esc,
					row[17],	/* valuemapid */
					params_esc,
					ipmi_sensor_esc,
					snmp_community_esc,
					snmp_oid_esc,
					row[22],	/* snmp_port */
					snmpv3_securityname_esc,
					row[24],	/* snmpv3_securitylevel */
					snmpv3_authpassphrase_esc,
					snmpv3_privpassphrase_esc,
					row[27],	/* authtype */
					username_esc,
					password_esc,
					publickey_esc,
					privatekey_esc,
					template_itemid,
					itemid);
		}
		else
		{
			itemid = DBget_maxid("items");

			key_esc = DBdyn_escape_string(row[2]);

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 8192,
					"insert into items"
						" (itemid,description,key_,hostid,type,value_type,data_type,"
						"delay,delay_flex,history,trends,status,trapper_hosts,units,"
						"multiplier,delta,formula,logtimefmt,valuemapid,params,"
						"ipmi_sensor,snmp_community,snmp_oid,snmp_port,"
						"snmpv3_securityname,snmpv3_securitylevel,"
						"snmpv3_authpassphrase,snmpv3_privpassphrase,"
						"authtype,username,password,publickey,privatekey,templateid)"
					" values"
						" (" ZBX_FS_UI64 ",'%s','%s'," ZBX_FS_UI64 ",%s,%s,%s,"
						"%s,'%s',%s,%s,%s,'%s','%s',%s,%s,'%s','%s',%s,'%s','%s',"
						"'%s','%s',%s,'%s',%s,'%s','%s',%s,'%s','%s','%s',"
						"'%s'," ZBX_FS_UI64 ");\n",
					itemid,
					description_esc,
					key_esc,
					hostid,
					row[3],		/* type */
					row[4],		/* value_type */
					row[5],		/* data_type */
					row[6],		/* delay */
					delay_flex_esc,
					row[8],		/* history */
					row[9],		/* trends */
					row[10],	/* status */
					trapper_hosts_esc,
					units_esc,
					row[13],	/* multiplier */
					row[14],	/* delta */
					formula_esc,
					logtimefmt_esc,
					row[17],	/* valuemapid */
					params_esc,
					ipmi_sensor_esc,
					snmp_community_esc,
					snmp_oid_esc,
					row[22],	/* snmp_port */
					snmpv3_securityname_esc,
					row[24],	/* snmpv3_securitylevel */
					snmpv3_authpassphrase_esc,
					snmpv3_privpassphrase_esc,
					row[27],	/* authtype */
					username_esc,
					password_esc,
					publickey_esc,
					privatekey_esc,
					template_itemid);

			zbx_free(key_esc);
		}

		DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset);

		zbx_free(privatekey_esc);
		zbx_free(publickey_esc);
		zbx_free(password_esc);
		zbx_free(username_esc);
		zbx_free(snmpv3_privpassphrase_esc);
		zbx_free(snmpv3_authpassphrase_esc);
		zbx_free(snmpv3_securityname_esc);
		zbx_free(snmp_oid_esc);
		zbx_free(snmp_community_esc);
		zbx_free(ipmi_sensor_esc);
		zbx_free(params_esc);
		zbx_free(logtimefmt_esc);
		zbx_free(formula_esc);
		zbx_free(units_esc);
		zbx_free(trapper_hosts_esc);
		zbx_free(delay_flex_esc);
		zbx_free(description_esc);

		appids_num = 0;

		if (SUCCEED == DBget_same_applications_by_itemid(hostid, itemid, template_itemid,
				&appids, &appids_alloc, &appids_num))
		{
			itemappid = DBget_maxid_num("items_applications", appids_num);

			for (i = 0; i < appids_num; i++)
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 160,
						"insert into items_applications"
						" (itemappid,itemid,applicationid)"
						" values"
						" (" ZBX_FS_UI64 "," ZBX_FS_UI64 "," ZBX_FS_UI64 ");\n",
						itemappid++, itemid, appids[i]);

				DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
			}
		}
	}
	DBfree_result(result);

#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 8, "end;\n");
#endif

	if (sql_offset > 16)	/* In ORACLE always present begin..end; */
		DBexecute("%s", sql);

	zbx_free(sql);
	zbx_free(appids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(res));

	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: DBcopy_template_triggers                                         *
 *                                                                            *
 * Purpose: Copy template triggers to host                                    *
 *                                                                            *
 * Parameters: hostid - host identificator from database                      *
 *             templateid - host template identificator from database         *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
static int	DBcopy_template_triggers(zbx_uint64_t hostid, zbx_uint64_t templateid)
{
	const char	*__function_name = "DBcopy_template_triggers";
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	triggerid, new_triggerid;
	int		res = SUCCEED;
	zbx_uint64_t	*trids = NULL;
	int		trids_alloc = 0, trids_num = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	result = DBselect(
			"select distinct t.triggerid,t.description,t.expression,t.status,"
				"t.type,t.priority,t.comments,t.url"
			" from triggers t,functions f,items i"
			" where i.hostid=" ZBX_FS_UI64
				" and f.itemid=i.itemid"
				" and f.triggerid=t.triggerid",
			templateid);

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
				row[7]);			/* url */

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
 * Comments: !!! Don't forget sync code with PHP !!!                          *
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

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():" ZBX_FS_UI64,
			__function_name, itemid);

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
 * Return value: upon successful completion return SUCCEED                    *
 *                                                                            *
 * Author: Eugene Grigorjev, Alexander Vladishev                              *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
static int	DBcopy_graph_to_host(zbx_uint64_t hostid, zbx_uint64_t graphid,
		const char *name, int width, int height, double yaxismin,
		double yaxismax, unsigned char show_work_period,
		unsigned char show_triggers, unsigned char graphtype,
		unsigned char show_legend, unsigned char show_3d,
		double percent_left, double percent_right,
		unsigned char ymin_type, unsigned char ymax_type,
		zbx_uint64_t ymin_itemid, zbx_uint64_t ymax_itemid)
{
	const char	*__function_name = "DBcopy_graph_to_host";
	DB_RESULT	result;
	DB_ROW		row;
	ZBX_GRAPH_ITEMS *gitems = NULL, *chd_gitems = NULL;
	int		gitems_alloc = 0, gitems_num = 0,
			chd_gitems_alloc = 0, chd_gitems_num = 0,
			i, res = SUCCEED;
	zbx_uint64_t	hst_graphid, hst_gitemid;
	char		*sql = NULL, *name_esc, *color_esc;
	int		sql_alloc = 1024, sql_offset;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	sql = zbx_malloc(sql, sql_alloc * sizeof(char));

	name_esc = DBdyn_escape_string(name);

	sql_offset = 0;
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 320,
			"select 0,dst.itemid,dst.key_,gi.drawtype,gi.sortorder,"
				"gi.color,gi.yaxisside,gi.calc_fnc,"
				"gi.type,gi.periods_cnt"
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
			" from graphs g,graphs_items gi,items i "
			" where g.graphid=gi.graphid"
				" and gi.itemid=i.itemid"
				" and i.hostid=" ZBX_FS_UI64
				" and g.name='%s'"
				" and g.templateid=0",
			hostid, name_esc);

	/* compare graphs */
	hst_graphid = 0;
	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(hst_graphid, row[0]);

		sql_offset = 0;
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 256,
				"select gi.gitemid,i.itemid,i.key_,gi.drawtype,"
					"gi.sortorder,gi.color,gi.yaxisside,"
					"gi.calc_fnc,gi.type,gi.periods_cnt"
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
#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 8, "begin\n");
#endif

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
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 1024,
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
					"ymin_itemid=" ZBX_FS_UI64 ","
					"ymax_itemid=" ZBX_FS_UI64
				" where graphid=" ZBX_FS_UI64 ";\n",
				name_esc, width, height, yaxismin, yaxismax,
				graphid, (int)show_work_period, (int)show_triggers,
				(int)graphtype, (int)show_legend, (int)show_3d, 
				percent_left, percent_right, (int)ymin_type, (int)ymax_type,
				ymin_itemid, ymax_itemid, hst_graphid);

		for (i = 0; i < gitems_num; i++)
		{
			color_esc = DBdyn_escape_string(gitems[i].color);

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 256,
					"update graphs_items"
					" set drawtype=%d,"
						"sortorder=%d,"
						"color='%s',"
						"yaxisside=%d,"
						"calc_fnc=%d,"
						"type=%d,"
						"periods_cnt=%d"
					" where gitemid=" ZBX_FS_UI64 ";\n",
					gitems[i].drawtype,
					gitems[i].sortorder,
					color_esc,
					gitems[i].yaxisside,
					gitems[i].calc_fnc,
					gitems[i].type,
					gitems[i].periods_cnt,
					chd_gitems[i].gitemid);

			zbx_free(color_esc);
		}
	}
	else
	{
		hst_graphid = DBget_maxid("graphs");

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 1024,
				"insert into graphs"
				" (graphid,name,width,height,yaxismin,yaxismax,templateid,"
				"show_work_period,show_triggers,graphtype,show_legend,"
				"show_3d,percent_left,percent_right,ymin_type,ymax_type,"
				"ymin_itemid,ymax_itemid)"
				" values (" ZBX_FS_UI64 ",'%s',%d,%d," ZBX_FS_DBL ","
				ZBX_FS_DBL "," ZBX_FS_UI64 ",%d,%d,%d,%d,%d," ZBX_FS_DBL ","
				ZBX_FS_DBL ",%d,%d," ZBX_FS_UI64 "," ZBX_FS_UI64 ");\n",
				hst_graphid, name_esc, width, height, yaxismin, yaxismax,
				graphid, (int)show_work_period, (int)show_triggers,
				(int)graphtype, (int)show_legend, (int)show_3d,
				percent_left, percent_right, (int)ymin_type, (int)ymax_type,
				ymin_itemid, ymax_itemid);

		hst_gitemid = DBget_maxid_num("graphs_items", gitems_num);

		for (i = 0; i < gitems_num; i++)
		{
			color_esc = DBdyn_escape_string(gitems[i].color);

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 256,
					"insert into graphs_items (gitemid,graphid,itemid,drawtype,"
					"sortorder,color,yaxisside,calc_fnc,type,periods_cnt)"
					" values (" ZBX_FS_UI64 "," ZBX_FS_UI64 "," ZBX_FS_UI64
					",%d,%d,'%s',%d,%d,%d,%d);\n",
					hst_gitemid, hst_graphid, gitems[i].itemid,
					gitems[i].drawtype, gitems[i].sortorder, color_esc,
					gitems[i].yaxisside, gitems[i].calc_fnc, gitems[i].type,
					gitems[i].periods_cnt);
			hst_gitemid++;

			zbx_free(color_esc);
		}
	}

	zbx_free(name_esc);

#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 8, "end;\n");
#endif

	if (sql_offset > 16)	/* In ORACLE always present begin..end; */
		DBexecute("%s", sql);

	zbx_free(gitems);
	zbx_free(chd_gitems);
	zbx_free(sql);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s",
			__function_name, zbx_result_string(res));

	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: DBcopy_template_graphs                                           *
 *                                                                            *
 * Purpose: copy graphs from template to host                                 *
 *                                                                            *
 * Parameters: hostid - host identificator from database                      *
 *             templateid - template identificator from database              *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
static int	DBcopy_template_graphs(zbx_uint64_t hostid, zbx_uint64_t templateid)
{
	const char	*__function_name = "DBcopy_template_graphs";
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	graphid, ymin_itemid, ymax_itemid;
	int		res = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	result = DBselect(
			"select distinct g.graphid,g.name,g.width,g.height,g.yaxismin,"
				"g.yaxismax,g.show_work_period,g.show_triggers,"
				"g.graphtype,g.show_legend,g.show_3d,g.percent_left,"
				"g.percent_right,g.ymin_type,g.ymax_type,g.ymin_itemid,"
				"g.ymax_itemid"
			" from graphs g,graphs_items gi,items i"
			" where g.graphid=gi.graphid"
				" and gi.itemid=i.itemid"
				" and i.hostid=" ZBX_FS_UI64,
			templateid);

	while (SUCCEED == res && NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(graphid, row[0]);
		ZBX_STR2UINT64(ymin_itemid, row[15]);
		ZBX_STR2UINT64(ymax_itemid, row[16]);

		res = DBcopy_graph_to_host(hostid, graphid,
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
				ymax_itemid);
	}
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s",
			__function_name, zbx_result_string(res));

	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: get_templates_by_hostid                                          *
 *                                                                            *
 * Description: Retrieve already linked templates for specified host          *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	get_templates_by_hostid(zbx_uint64_t hostid,
		zbx_uint64_t **templateids, int *templateids_alloc,
		int *templateids_num)
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
		uint64_array_add(templateids, templateids_alloc,
				templateids_num, templateid, 16);
	}
	DBfree_result(result);
}

/******************************************************************************
 *                                                                            *
 * Function: DBcopy_template_elements                                         *
 *                                                                            *
 * Purpose: copy elements from specified template                             *
 *                                                                            *
 * Parameters: hostid - host identificator from database                      *
 *             templateid - template identificator from database              *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
int	DBcopy_template_elements(zbx_uint64_t hostid, zbx_uint64_t templateid)
{
	const char	*__function_name = "DBcopy_template_elements";
	zbx_uint64_t	*templateids = NULL, hosttemplateid;
	int		templateids_alloc = 0, templateids_num = 0,
			res = SUCCEED;
	char		error[MAX_STRING_LEN];

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	get_templates_by_hostid(hostid, &templateids, &templateids_alloc,
			&templateids_num);

	if (SUCCEED == uint64_array_exists(templateids, templateids_num, templateid))
		goto clean;	/* template already linked */

	uint64_array_add(&templateids, &templateids_alloc, &templateids_num,
			templateid, 1);

	if (SUCCEED != (res = validate_template(templateids, templateids_num,
			error, sizeof(error))))
	{
		zabbix_log(LOG_LEVEL_WARNING, "Can not link template '%s': %s",
				zbx_host_string(templateid), error);
		goto clean;
	}

	if (SUCCEED != (res = validate_host(hostid, templateid,
			error, sizeof(error))))
	{
		zabbix_log(LOG_LEVEL_WARNING, "Can not link template '%s': %s",
				zbx_host_string(templateid), error);
		goto clean;
	}

	hosttemplateid = DBget_maxid("hosts_templates");

	DBexecute("insert into hosts_templates"
			" (hosttemplateid,hostid,templateid)"
			" values"
			" (" ZBX_FS_UI64 "," ZBX_FS_UI64 "," ZBX_FS_UI64 ")",
			hosttemplateid,
			hostid,
			templateid);

	if (SUCCEED == (res = DBcopy_template_applications(hostid, templateid)))
		if (SUCCEED == (res = DBcopy_template_items(hostid, templateid)))
			if (SUCCEED == (res = DBcopy_template_triggers(hostid, templateid)))
				res = DBcopy_template_graphs(hostid, templateid);

clean:
	zbx_free(templateids);

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
 * Return value: always SUCCEED                                               *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
int	DBdelete_host(zbx_uint64_t hostid)
{
	const char	*__function_name = "DBdelete_host";
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	elementid;
	zbx_uint64_t	*graphids = NULL, *itemids = NULL,
			*htids = NULL;
	char		*sql = NULL;
	int		graphids_alloc = 0, graphids_num = 0,
			itemids_alloc = 0, itemids_num = 0,
			htids_alloc = 0, htids_num = 0,
			sql_alloc = 128, sql_offset = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	sql = zbx_malloc(sql, sql_alloc);

	/* select graphs with host items */
	result = DBselect(
			"select distinct gi.graphid"
			" from graphs_items gi,items i"
			" where gi.itemid=i.itemid"
				" and i.hostid=" ZBX_FS_UI64,
			hostid);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(elementid, row[0]);
		uint64_array_add(&graphids, &graphids_alloc, &graphids_num, elementid, 4);
	}
	DBfree_result(result);

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
		uint64_array_add(&htids, &htids_alloc, &htids_num, elementid, 4);
	}
	DBfree_result(result);
	
	DBdelete_httptests(htids, htids_num);

	zbx_free(htids);

	/* delete items -> triggers -> graphs */
	result = DBselect(
			"select itemid"
			" from items"
			" where hostid=" ZBX_FS_UI64,
			hostid);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(elementid, row[0]);
		uint64_array_add(&itemids, &itemids_alloc, &itemids_num, elementid, 64);
	}
	DBfree_result(result);

	DBdelete_items(itemids, itemids_num);

	zbx_free(itemids);

	/* delete empty graphs */
	if (graphids_num != 0)
	{
		sql_offset = 0;
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 92,
				"select distinct graphid"
				" from graphs_items"
				" where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset,
				"graphid", graphids, graphids_num);

		result = DBselect("%s", sql);

		while (NULL != (row = DBfetch(result)))
		{
			ZBX_STR2UINT64(elementid, row[0]);
			uint64_array_remove(graphids, &graphids_num, &elementid, 1);
		}
		DBfree_result(result);

		DBdelete_graphs(graphids, graphids_num);

		zbx_free(graphids);
	}

	/* delete host from maps */
	DBdelete_sysmaps_elements(SYSMAP_ELEMENT_TYPE_HOST, hostid);

	/* delete action conditions */
	DBdelete_action_conditions(CONDITION_TYPE_HOST, hostid);

	sql_offset = 0;
#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 8, "begin\n");
#endif

	/* delete host from template linkages */
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 92,
			"delete from hosts_templates"
			" where hostid=" ZBX_FS_UI64 ";\n",
			hostid);

	/* delete host profile */
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 92,
			"delete from hosts_profiles"
			" where hostid=" ZBX_FS_UI64 ";\n",
			hostid);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 92,
			"delete from hosts_profiles_ext"
			" where hostid=" ZBX_FS_UI64 ";\n",
			hostid);

	/* delete applications */
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 92,
			"delete from applications"
			" where hostid=" ZBX_FS_UI64 ";\n",
			hostid);

	/* delete host level macros */
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 92,
			"delete from hostmacro"
			" where hostid=" ZBX_FS_UI64 ";\n",
			hostid);

	/* delete maintenance hosts */
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 92,
			"delete from maintenances_hosts"
			" where hostid=" ZBX_FS_UI64 ";\n",
			hostid);

	/* delete host from group */
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 92,
			"delete from hosts_groups"
			" where hostid=" ZBX_FS_UI64 ";\n",
			hostid);

	/* delete host */
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 92,
			"delete from hosts"
			" where hostid=" ZBX_FS_UI64 ";\n",
			hostid);

#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 8, "end;\n");
#endif

	DBexecute("%s", sql);

	zbx_free(sql);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);

	return SUCCEED;
}
