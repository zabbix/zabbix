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
#include "zlog.h"
#include "dbcache.h"

#define ZBX_MAX_APPLICATIONS 64
#define ZBX_MAX_DEPENDENCES	128

/******************************************************************************
 *                                                                            *
 * Function: validate_template                                                *
 *                                                                            *
 * Description: Check collisions between templates                            *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Parameters: templateids - array of templates identificators from database  *
 *             templateids_num - templates count in hostids array             *
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
	int		sql_offset = 0, sql_alloc = 256,
			ret = SUCCEED;

	if (templateids_num < 2)
		return ret;

	sql = zbx_malloc(sql, sql_alloc);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 64,
			"select key_,count(*)"
			" from items"
			" where");

	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid",
			templateids, templateids_num);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 64,
			" group by key_"
			" having count(*)>1");

	result = DBselect("%s", sql);

	if (NULL != (row = DBfetch(result)))
	{
		ret = FAIL;
		zbx_snprintf(error, max_error_len, "Template with item key [%s]"
				" already linked to the host",
				row[0]);
	}
	DBfree_result(result);

	if (SUCCEED == ret)
	{
		sql_offset = 0;
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 64,
				"select name,count(*)"
				" from applications"
				" where");

		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid",
				templateids, templateids_num);

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 64,
				" group by name"
				" having count(*)>1");

		result = DBselect("%s", sql);

		if (NULL != (row = DBfetch(result)))
		{
			ret = FAIL;
			zbx_snprintf(error, max_error_len, "Template with application [%s]"
					" already linked to the host",
					row[0]);
		}
		DBfree_result(result);
	}

	zbx_free(sql);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: DBget_applications_by_itemid                                     *
 *                                                                            *
 * Purpose: retrieve applications by itemid                                   *
 *                                                                            *
 * Parameters: itemid - item identificator from database                      *
 *             applications - result buffer                                   *
 *             max_applications - size of result buffer in counts             *
 *                                                                            *
 * Return value: count of results                                             *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
static int	DBget_applications_by_itemid(
		zbx_uint64_t	itemid,
		zbx_uint64_t	*applications,
		int		max_applications
	)
{
	register int	i = 0;

	DB_RESULT	db_applications;

	DB_ROW		application_data;

	db_applications = DBselect("select distinct applicationid from items_applications "
			" where itemid=" ZBX_FS_UI64, itemid);

	while( i < (max_applications - 1) && (application_data = DBfetch(db_applications)) )
	{
		ZBX_STR2UINT64(applications[i], application_data[0]);
		i++;
	}

	DBfree_result(db_applications);

	applications[i] = 0;

	return i;
}

/******************************************************************************
 *                                                                            *
 * Function: DBget_same_applications_for_host                                 *
 *                                                                            *
 * Purpose: retrieve same applications for specified host                     *
 *                                                                            *
 * Parameters:  applications_in - zero terminated list of applicationid's     *
 *              hostid - host identificator from database                     *
 *              applications - result buffer                                  *
 *              max_applications - size of result buffer in count             *
 *                                                                            *
 * Return value: count of results                                             *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
static int	DBget_same_applications_for_host(
		zbx_uint64_t    *applications_in,
		zbx_uint64_t	hostid,
		zbx_uint64_t	*applications,
		int		max_applications
	)
{
	register int	i = 0, j = 0;

	DB_RESULT	db_applications;

	DB_ROW		application_data;

	while( 0 < applications_in[i] && j < (max_applications - 1) )
	{
		db_applications = DBselect("select a1.applicationid from applications a1, applications a2"
				" where a1.name=a2.name and a1.hostid=" ZBX_FS_UI64 " and a2.applicationid=" ZBX_FS_UI64,
					hostid, applications_in[i]);

		if( (application_data = DBfetch(db_applications)) )
		{
			ZBX_STR2UINT64(applications[j], application_data[0]);
			j++;
		}

		DBfree_result(db_applications);
		i++;
	}

	applications[j] = 0;

	return j;
}

/******************************************************************************
 *                                                                            *
 * Function: DBinsert_dependency                                              *
 *                                                                            *
 * Purpose: create dependencies for triggers                                  *
 *                                                                            *
 * Parameters: triggerid_down - down trigger identificator from database      *
 *             triggerid_up - up trigger identificator from database          *
 *                                                                            *
 * Return value: always SUCCEED                                               *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
static int	DBinsert_dependency(
		zbx_uint64_t triggerid_down,
		zbx_uint64_t triggerid_up
	)
{
	DBexecute("insert into trigger_depends (triggerdepid,triggerid_down,triggerid_up)"
			" values (" ZBX_FS_UI64 "," ZBX_FS_UI64 "," ZBX_FS_UI64 ")",
			DBget_maxid("trigger_depends","triggerdepid"), triggerid_down, triggerid_up);

	DBexecute("update triggers set dep_level=dep_level+1 where triggerid=" ZBX_FS_UI64, triggerid_up);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: DBdelete_dependencies_by_triggerid                               *
 *                                                                            *
 * Purpose: delete dependencies from triggers                                 *
 *                                                                            *
 * Parameters: triggerid - trigger identificator from database                *
 *                                                                            *
 * Return value: always SUCCEED                                               *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
static int	DBdelete_dependencies_by_triggerid(
	zbx_uint64_t triggerid
	)
{
	DB_RESULT	db_trig_deps;

	DB_ROW		trig_dep_data;

	zbx_uint64_t
		triggerid_down,
		triggerid_up;

	db_trig_deps = DBselect("select triggerid_up, triggerid_down from trigger_depends where triggerid_down=" ZBX_FS_UI64, triggerid);

	while( (trig_dep_data = DBfetch(db_trig_deps)) )
	{
		ZBX_STR2UINT64(triggerid_up,	trig_dep_data[0]);
		ZBX_STR2UINT64(triggerid_down,	trig_dep_data[1]);

		DBexecute("update triggers set dep_level=dep_level-1 where triggerid=" ZBX_FS_UI64, triggerid_up);
		DBexecute("delete from trigger_depends where triggerid_up=" ZBX_FS_UI64 " and triggerid_down=" ZBX_FS_UI64,
						triggerid_up, triggerid_down);
	}

	DBfree_result(db_trig_deps);

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
static void	DBclear_parents_from_trigger(
		zbx_uint64_t serviceid
	)
{
	DB_RESULT res;
	DB_ROW rows;

	if( serviceid != 0 )
	{
		DBexecute("UPDATE services as s "
				" SET s.triggerid = null "
				" WHERE s.serviceid = " ZBX_FS_UI64, serviceid);
	}
	else
	{

		res = DBselect("SELECT s.serviceid "
					" FROM services as s, services_links as sl "
					" WHERE s.serviceid = sl.serviceupid "
					" AND NOT(s.triggerid IS NULL) "
					" GROUP BY s.serviceid");
		while( (rows = DBfetch(res)) )
		{
			ZBX_STR2UINT64(serviceid, rows[0]);

			DBexecute("UPDATE services as s "
					" SET s.triggerid = null "
					" WHERE s.serviceid = " ZBX_FS_UI64, serviceid);
		}
		DBfree_result(res);
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
static int	DBget_service_status(
		zbx_uint64_t	serviceid,
		int		algorithm,
		zbx_uint64_t	triggerid
	)
{
	DB_RESULT	result;
	DB_ROW		row;

	int status = 0;
	char sort_order[MAX_STRING_LEN];
	char sql[MAX_STRING_LEN];

	if( 0 != triggerid )
	{
		result = DBselect("select priority from triggers where triggerid=" ZBX_FS_UI64 " and status=0 and value=%d", triggerid, TRIGGER_VALUE_TRUE);
		row = DBfetch(result);
		if(row && (DBis_null(row[0])!=SUCCEED))
		{
			status = atoi(row[0]);
		}
		DBfree_result(result);
	}

	if((SERVICE_ALGORITHM_MAX == algorithm) || (SERVICE_ALGORITHM_MIN == algorithm))
	{

		strcpy(sort_order,((SERVICE_ALGORITHM_MAX == algorithm)?" DESC ":" ASC "));

		zbx_snprintf(sql,sizeof(sql),"select s.status from services s,services_links l where l.serviceupid=" ZBX_FS_UI64 " and s.serviceid=l.servicedownid order by s.status %s",
			serviceid,
			sort_order);

		result = DBselectN(sql,1);

		row=DBfetch(result);
		if(row && DBis_null(row[0]) != SUCCEED)
		{
			if(atoi(row[0])!=0)
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
 * Purpose: re-calculate and updates status of the service and its childs     *
 *                                                                            *
 * Parameters: serviceid - item to update services for                        *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: recursive function                                               *
 *           !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
static void	DBupdate_services_rec(zbx_uint64_t serviceid, int clock)
{
	int	status;
	zbx_uint64_t	serviceupid;
	int	algorithm;

	DB_RESULT result;
	DB_ROW	row;

	result = DBselect("select l.serviceupid,s.algorithm from services_links l,services s where s.serviceid=l.serviceupid and l.servicedownid=" ZBX_FS_UI64,
		serviceid);
	status=0;
	while((row=DBfetch(result)))
	{
		ZBX_STR2UINT64(serviceupid,row[0]);
		algorithm=atoi(row[1]);
		if(SERVICE_ALGORITHM_NONE == algorithm)
		{
/* Do nothing */
		}
		else if((SERVICE_ALGORITHM_MAX == algorithm) ||
			(SERVICE_ALGORITHM_MIN == algorithm))
		{
			status = DBget_service_status(serviceupid, algorithm, 0);

			DBadd_service_alarm(serviceupid, status, clock);
			DBexecute("update services set status=%d where serviceid=" ZBX_FS_UI64,
				status,
				serviceupid);
		}
		else
		{
			zabbix_log( LOG_LEVEL_ERR, "Unknown calculation algorithm of service status [%d]",
				algorithm);
			zabbix_syslog("Unknown calculation algorithm of service status [%d]",
				algorithm);
		}
	}
	DBfree_result(result);

	result = DBselect("select serviceupid from services_links where servicedownid=" ZBX_FS_UI64,
		serviceid);

	while((row=DBfetch(result)))
	{
		ZBX_STR2UINT64(serviceupid,row[0]);
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
static void DBupdate_services_status_all(void)
{
	DB_RESULT result;
	DB_ROW rows;

	zbx_uint64_t
		serviceid = 0,
		triggerid = 0;

	int	status = 0, clock;

	DBclear_parents_from_trigger(0);

	clock = time(NULL);

	result = DBselect("SELECT s.serviceid,s.algorithm,s.triggerid "
			" FROM services AS s "
			" WHERE s.serviceid NOT IN (SELECT DISTINCT sl.serviceupid FROM services_links AS sl)");

	while( (rows = DBfetch(result)) )
	{
		ZBX_STR2UINT64(serviceid, rows[0]);
		ZBX_STR2UINT64(triggerid, rows[2]);

		status = DBget_service_status(serviceid, atoi(rows[1]), triggerid);

		DBexecute("UPDATE services SET status=%i WHERE serviceid=" ZBX_FS_UI64,
			status,
			serviceid);

		DBadd_service_alarm(serviceid, status, clock);
	}
	DBfree_result(result);

	result = DBselect("SELECT MAX(sl.servicedownid) as serviceid, sl.serviceupid "
			" FROM services_links AS sl "
			" WHERE sl.servicedownid NOT IN (select distinct sl.serviceupid from services_links as sl) "
			" GROUP BY sl.serviceupid");

	while( (rows = DBfetch(result)) )
	{
		ZBX_STR2UINT64(serviceid, rows[0]);
		DBupdate_services_rec(serviceid, clock);
	}
	DBfree_result(result);
}

/******************************************************************************
 *                                                                            *
 * Function: DBupdate_services                                                *
 *                                                                            *
 * Purpose: re-calculate and update status of the service and its childs      *
 *                                                                            *
 * Parameters: serviceid - item to update services for                        *
 *             status - new status of the service                             *
 *                                                                            *
 * Return value:                                                              *
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

	result = DBselect("select serviceid from services where triggerid=" ZBX_FS_UI64,
			triggerid);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(serviceid, row[0]);

		DBexecute("update services set status=%d where serviceid=" ZBX_FS_UI64,
				status,
				serviceid);

		DBadd_service_alarm(serviceid, status, clock);
		DBupdate_services_rec(serviceid, clock);
	}

	DBfree_result(result);
}

/******************************************************************************
 *                                                                            *
 * Function: DBdelete_service                                                 *
 *                                                                            *
 * Purpose: delete service from database                                      *
 *                                                                            *
 * Parameters: serviceid - service identificator from database                *
 *                                                                            *
 * Return value: always SUCCEED                                               *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
static int	DBdelete_service(
		zbx_uint64_t serviceid
	)
{
	DBexecute("delete from service_alarms WHERE serviceid=" ZBX_FS_UI64, serviceid);
	DBexecute("delete from services_links where servicedownid=" ZBX_FS_UI64 " or serviceupid=" ZBX_FS_UI64, serviceid, serviceid);
	DBexecute("delete from services where serviceid=" ZBX_FS_UI64, serviceid);
	DBexecute("delete from services_times where serviceid=" ZBX_FS_UI64, serviceid);

	DBupdate_services_status_all();

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: DBdelete_services_by_triggerid                                   *
 *                                                                            *
 * Purpose: delete triggers from service                                      *
 *                                                                            *
 * Parameters: triggerid - trigger identificator from database                *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
static int	DBdelete_services_by_triggerid(
		zbx_uint64_t triggerid
	)
{
	DB_RESULT	db_services;

	DB_ROW		service_data;

	zbx_uint64_t
		serviceid;

	int	result = SUCCEED;

	db_services = DBselect("select serviceid from services where triggerid=" ZBX_FS_UI64, triggerid);

	while( (service_data = DBfetch(db_services)) )
	{
		ZBX_STR2UINT64(serviceid, service_data[0]);
		if( SUCCEED != (result = DBdelete_service(serviceid)) )
			break;
	}

	DBfree_result(db_services);

	return  result;
}


/******************************************************************************
 *                                                                            *
 * Function: DBdelete_link                                                    *
 *                                                                            *
 * Purpose: delete sysmap links from sysmap element                           *
 *                                                                            *
 * Parameters: linkid - link identificator from database                      *
 *                                                                            *
 * Return value: always return SUCCEED                                        *
 *                                                                            *
 * Author: Aly                                                                *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
static int	DBdelete_link(zbx_uint64_t linkid)
{
	DBexecute("delete from sysmaps_links WHERE linkid=" ZBX_FS_UI64, linkid);
	DBexecute("delete from sysmaps_link_triggers WHERE linkid=" ZBX_FS_UI64, linkid);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: DBdelete_sysmaps_element                                         *
 *                                                                            *
 * Purpose: delete specified map element                                      *
 *                                                                            *
 * Parameters: selementid - map element identificator from database           *
 *                                                                            *
 * Return value: always return SUCCEED                                        *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
static int	DBdelete_sysmaps_element(zbx_uint64_t selementid)
{
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	linkid;

	result = DBselect("select linkid from sysmaps_links"
			" where selementid1=" ZBX_FS_UI64 " or selementid2=" ZBX_FS_UI64,
			selementid,
			selementid);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(linkid, row[0]);
		DBdelete_link(linkid);
	}

	DBfree_result(result);

	DBexecute("delete from sysmaps_elements where selementid=" ZBX_FS_UI64, selementid);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: DBdelete_sysmaps_elements                                        *
 *                                                                            *
 * Purpose: delete elements from map by elementtype and elementid             *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
static int	DBdelete_sysmaps_elements(int elementtype, zbx_uint64_t elementid)
{
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	selementid;
	int		res = SUCCEED;

	result = DBselect("select distinct selementid from sysmaps_elements"
			" where elementtype=%d and elementid=" ZBX_FS_UI64,
			elementtype,
			elementid);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(selementid, row[0]);
		if (SUCCEED != (res = DBdelete_sysmaps_element(selementid)))
			break;
	}

	DBfree_result(result);

	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: DBdelete_action_conditions                                       *
 *                                                                            *
 * Purpose: delete action conditions by condition type and id                 *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
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
	DBexecute("delete from conditions where conditiontype=%d and value=" ZBX_FS_UI64,
			conditiontype, elementid);
}

/******************************************************************************
 *                                                                            *
 * Function: DBdelete_trigger                                                 *
 *                                                                            *
 * Purpose: delete trigger from database                                      *
 *                                                                            *
 * Parameters: triggerid - trigger dentificator from database                 *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
static int	DBdelete_trigger(
		zbx_uint64_t triggerid
	)
{
	DB_RESULT	db_triggers;
	DB_RESULT	db_elements;

	DB_ROW		trigger_data;
	DB_ROW		element_data;

	zbx_uint64_t
		elementid;

	int result = FAIL;

	db_triggers = DBselect("select description from triggers where triggerid=" ZBX_FS_UI64, triggerid);

	if( (trigger_data = DBfetch(db_triggers)) )
	{
		result = SUCCEED;

		/* first delete child items */
		db_elements = DBselect("select triggerid from triggers where templateid=" ZBX_FS_UI64, triggerid);

		while( (element_data = DBfetch(db_elements)) )
		{ /* recursion */
			ZBX_STR2UINT64(elementid, element_data[0]);
			if( SUCCEED != (result = DBdelete_trigger(elementid)) )
				break;
		}

		DBfree_result(db_elements);

		if( SUCCEED == result)
		if( SUCCEED == (result = DBdelete_dependencies_by_triggerid(triggerid)) )
		if( SUCCEED == (result = DBdelete_services_by_triggerid(triggerid)) )
		if( SUCCEED == (result = DBdelete_sysmaps_elements(SYSMAP_ELEMENT_TYPE_TRIGGER, triggerid)) )
		{
			DBexecute("delete from trigger_depends where triggerid_up=" ZBX_FS_UI64, triggerid);
			DBexecute("delete from functions where triggerid=" ZBX_FS_UI64, triggerid);
			DBexecute("delete from events where objectid=" ZBX_FS_UI64 " and object=%i", triggerid, EVENT_OBJECT_TRIGGER);

			DBexecute("delete from sysmaps_link_triggers where triggerid=" ZBX_FS_UI64, triggerid);

			/* delete action conditions */
			DBdelete_action_conditions(CONDITION_TYPE_TRIGGER, triggerid);

			DBexecute("delete from triggers where triggerid=" ZBX_FS_UI64, triggerid);

			zabbix_log( LOG_LEVEL_DEBUG, "Trigger '%s' deleted", trigger_data[0]);
		}
	}

	DBfree_result(db_triggers);

	return result;
}

/******************************************************************************
 *                                                                            *
 * Function: DBdelete_triggers_by_itemid                                      *
 *                                                                            *
 * Purpose: delete triggers by itemid                                         *
 *                                                                            *
 * Parameters: itemid - item identificator from database                      *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
static int	DBdelete_triggers_by_itemid(
		zbx_uint64_t itemid
	)
{
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	triggerid;
	int		res = SUCCEED;

	result = DBselect("select distinct triggerid from functions where itemid=" ZBX_FS_UI64, itemid);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(triggerid, row[0]);
		if (SUCCEED != (res = DBdelete_trigger(triggerid)))
			break;
	}
	DBfree_result(result);

	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: DBdelete_trends_by_itemid                                        *
 *                                                                            *
 * Purpose: delete item trends                                                *
 *                                                                            *
 * Parameters: itemid - item identificator from database                      *
 *             use_housekeeper - 0 - to delete immediately                    *
 *                               1 - delete with housekeeper                  *
 *                                                                            *
 * Return value: always SUCCEED                                               *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
static int	DBdelete_trends_by_itemid(
		zbx_uint64_t itemid,
		unsigned char use_housekeeper
	)
{
	if( use_housekeeper )
	{
		DBexecute("insert into housekeeper (housekeeperid,tablename,field,value)"
			" values (" ZBX_FS_UI64 ", 'trends','itemid'," ZBX_FS_UI64 ")",
				DBget_maxid("housekeeper","housekeeperid"), itemid);
	}
	else
	{
		DBexecute("delete from trends where itemid=" ZBX_FS_UI64, itemid);
	}
	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: DBdelete_history_by_itemid                                       *
 *                                                                            *
 * Purpose: delete item history                                               *
 *                                                                            *
 * Parameters: itemid - item identificator from database                      *
 *             use_housekeeper - 0 - to delete immediately                    *
 *                               1 - delete with housekeeper                  *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
static int	DBdelete_history_by_itemid(
		zbx_uint64_t itemid,
		unsigned char use_housekeeper
	)
{
	int	result;

	if( SUCCEED == (result = DBdelete_trends_by_itemid(itemid, use_housekeeper)) )
	{
		if( use_housekeeper )
		{
			DBexecute("insert into housekeeper (housekeeperid,tablename,field,value)"
					" values (" ZBX_FS_UI64 ",'history_text','itemid'," ZBX_FS_UI64 ")",
						DBget_maxid("housekeeper","housekeeperid"), itemid);
			DBexecute("insert into housekeeper (housekeeperid,tablename,field,value)"
					" values (" ZBX_FS_UI64 ",'history_log','itemid'," ZBX_FS_UI64 ")",
						DBget_maxid("housekeeper","housekeeperid"), itemid);
			DBexecute("insert into housekeeper (housekeeperid,tablename,field,value)"
							" values (" ZBX_FS_UI64 ",'history_uint','itemid'," ZBX_FS_UI64 ")",
								DBget_maxid("housekeeper","housekeeperid"), itemid);
			DBexecute("insert into housekeeper (housekeeperid,tablename,field,value)"
							" values (" ZBX_FS_UI64 ",'history_str','itemid'," ZBX_FS_UI64 ")",
								DBget_maxid("housekeeper","housekeeperid"), itemid);
			DBexecute("insert into housekeeper (housekeeperid,tablename,field,value)"
								" values (" ZBX_FS_UI64 ",'history','itemid'," ZBX_FS_UI64 ")",
									DBget_maxid("housekeeper","housekeeperid"), itemid);
		}
		else
		{
			DBexecute("delete from history_text where itemid=" ZBX_FS_UI64, itemid);
			DBexecute("delete from history_log where itemid=" ZBX_FS_UI64, itemid);
			DBexecute("delete from history_uint where itemid=" ZBX_FS_UI64, itemid);
			DBexecute("delete from history_str where itemid=" ZBX_FS_UI64, itemid);
			DBexecute("delete from history where itemid=" ZBX_FS_UI64, itemid);
		}
	}
	return result;
}

/******************************************************************************
 *                                                                            *
 * Type: ZBX_GRAPH_ITEMS                                                      *
 *                                                                            *
 * Purpose: represent graph item data                                         *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 ******************************************************************************/
typedef struct {
	zbx_uint64_t	itemid;
	int		drawtype;
	int		sortorder;
	char		color[GRAPH_ITEM_COLOR_LEN_MAX];
	int		yaxisside;
	int		calc_fnc;
	int		type;
	int		periods_cnt;
} ZBX_GRAPH_ITEMS;

/******************************************************************************
 *                                                                            *
 * Function: DBcmp_graphitems                                                 *
 *                                                                            *
 * Purpose: Compare two graph items                                           *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
static int	DBcmp_graphitems(ZBX_GRAPH_ITEMS *gitem1, ZBX_GRAPH_ITEMS *gitem2)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		res = 0;

/*	if (gitem1->drawtype != gitem2->drawtype)	return 1;
	if (gitem1->sortorder != gitem2->sortorder)	return 2;
	if (strcmp(gitem1->color, gitem2->color))	return 3;
	if (gitem1->yaxisside != gitem2->yaxisside)	return 4;*/

	result = DBselect(
			"select distinct i2.itemid"
			" from items i1,items i2 "
			" where i1.itemid=" ZBX_FS_UI64
				" and i2.itemid=" ZBX_FS_UI64
				" and i1.key_=i2.key_ ",
			gitem1->itemid,
			gitem2->itemid);

	if (NULL == (row = DBfetch(result)))
		res = 5;
	DBfree_result(result);

	return res;
}
/******************************************************************************
 *                                                                            *
 * Function: DBget_same_graphitems_for_host                                   *
 *                                                                            *
 * Purpose: Replace items for specified host                                  *
 *                                                                            *
 * Parameters: gitems - zero terminated array of graph items                  *
 *             dest_hostid - destination host id                              *
 *                                                                            *
 * Return value: always SUCCEED                                               *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
static int	DBget_same_graphitems_for_host(ZBX_GRAPH_ITEMS *gitems, int gitems_num,
		ZBX_GRAPH_ITEMS **new_gitems, int *new_gitems_num,
		zbx_uint64_t dest_hostid)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		new_gitems_alloc = 0;
	ZBX_GRAPH_ITEMS *new_gitem;
	int		i, res = SUCCEED;

	*new_gitems_num = 0;

	for (i = 0; i < gitems_num; i++)
	{
		result = DBselect(
				"select src.itemid"
				" from items src,items dest "
				" where dest.itemid=" ZBX_FS_UI64
					" and src.key_=dest.key_"
					" and src.hostid=" ZBX_FS_UI64,
				gitems[i].itemid,
				dest_hostid);

		if (NULL != (row = DBfetch(result)))
		{
			if (new_gitems_alloc == *new_gitems_num)
			{
				new_gitems_alloc += 16;
				*new_gitems = zbx_realloc(*new_gitems,
						new_gitems_alloc * sizeof(ZBX_GRAPH_ITEMS));
			}

			new_gitem = &(*new_gitems)[(*new_gitems_num)++];

			ZBX_STR2UINT64(new_gitem->itemid, row[0]);
			new_gitem->drawtype	= gitems[i].drawtype;
			new_gitem->sortorder	= gitems[i].sortorder;
			zbx_strlcpy(new_gitem->color, gitems[i].color, sizeof(new_gitem->color));
			new_gitem->yaxisside	= gitems[i].yaxisside;
			new_gitem->calc_fnc	= gitems[i].calc_fnc;
			new_gitem->type		= gitems[i].type;
			new_gitem->periods_cnt	= gitems[i].periods_cnt;
		}
		DBfree_result(result);

		if (NULL == row)
		{
			zbx_free(*new_gitems);
			res = FAIL;
			break;
		}
	}

	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: DBadd_graph_item                                                 *
 *                                                                            *
 * Purpose: add item to the graph                                             *
 *                                                                            *
 * Parameters: graphid - graph identificator from database                    *
 *             gitem - pointer to ZBX_GRAPH_ITEMS structure                   *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
static int	DBadd_graph_item(zbx_uint64_t graphid, ZBX_GRAPH_ITEMS *gitem)
{
	zbx_uint64_t	gitemid;
	char		*color_esc = NULL;

	gitemid = DBget_maxid("graphs_items", "gitemid");

	color_esc = DBdyn_escape_string(gitem->color);

	DBexecute("insert into graphs_items (gitemid,graphid,itemid,drawtype,"
			"sortorder,color,yaxisside,calc_fnc,type,periods_cnt)"
			" values (" ZBX_FS_UI64 "," ZBX_FS_UI64 "," ZBX_FS_UI64
			",%d,%d,'%s',%d,%d,%d,%d)",
			gitemid,
			graphid,
			gitem->itemid,
			gitem->drawtype,
			gitem->sortorder,
			color_esc,
			gitem->yaxisside,
			gitem->calc_fnc,
			gitem->type,
			gitem->periods_cnt);

	zbx_free(color_esc);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: DBdelete_graph                                                   *
 *                                                                            *
 * Purpose: delete graph from database                                        *
 *                                                                            *
 * Parameters: graphid - graph identificator from database                    *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
static int	DBdelete_graph(zbx_uint64_t graphid)
{
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	elementid;
	int		res = SUCCEED;

	/* first delete child graphs */
	result = DBselect("select graphid from graphs where templateid=" ZBX_FS_UI64, graphid);

	while (NULL != (row = DBfetch(result)))
	{	/* recursion */
		ZBX_STR2UINT64(elementid, row[0]);
		if (SUCCEED != (res = DBdelete_graph(elementid)))
			break;
	}
	DBfree_result(result);

	if (SUCCEED != res)
		return res;

	result = DBselect("select name from graphs where graphid=" ZBX_FS_UI64, graphid);

	if (NULL != (row = DBfetch(result)))
	{
		/* delete graph */
		DBexecute("delete from screens_items where resourceid=" ZBX_FS_UI64 " and resourcetype=%i",
				graphid, SCREEN_RESOURCE_GRAPH);
		DBexecute("delete from graphs_items where graphid=" ZBX_FS_UI64, graphid);
		DBexecute("delete from graphs where graphid=" ZBX_FS_UI64, graphid);

		zabbix_log( LOG_LEVEL_DEBUG, "Graph '%s' deleted", row[0]);
	}
	DBfree_result(result);

	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: DBupdate_graph                                                   *
 *                                                                            *
 * Purpose: Update graph without items and recursion for template             *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: always SUCCEED                                               *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
static int	DBupdate_graph(
		zbx_uint64_t	graphid,
		const char	*name,
		int		width,
		int		height,
		int		yaxismin,
		int		yaxismax,
		zbx_uint64_t	templateid,
		int		show_work_period,
		int		show_triggers,
		int		graphtype,
		int		show_legend,
		int		show_3d,
		double		percent_left,
		double		percent_right,
		int		ymin_type,
		int		ymax_type,
		zbx_uint64_t	ymin_itemid,
		zbx_uint64_t	ymax_itemid
	)
{
	DB_RESULT	result;
	DB_ROW		row;
	char		*name_esc = NULL;
	int		old_graphtype = 0;

	result = DBselect(
			"select graphtype"
			" from graphs"
			" where graphid=" ZBX_FS_UI64,
			graphid);

	if (NULL != (row = DBfetch(result)))
		old_graphtype = atoi(row[0]);
	DBfree_result(result);

	name_esc = DBdyn_escape_string(name);

	DBexecute(
		"update graphs"
		" set name='%s',"
			"width=%d,"
			"height=%d,"
			"yaxismin=%d,"
			"yaxismax=%d,"
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
		" where graphid=" ZBX_FS_UI64,
		name_esc,
		width,
		height,
		yaxismin,
		yaxismax,
		templateid,
		show_work_period,
		show_triggers,
		graphtype,
		show_legend,
		show_3d,
		percent_left,
		percent_right,
		ymin_type,
		ymax_type,
		ymin_itemid,
		ymax_itemid,
		graphid);

	zbx_free(name_esc);

	if (old_graphtype != graphtype && graphtype == GRAPH_TYPE_STACKED)
	{
		DBexecute(
			"update graphs_items"
			" set calc_fnc=%d,"
				"drawtype=1,"
				"type=%d"
			" where graphid=" ZBX_FS_UI64,
			CALC_FNC_AVG,
			GRAPH_ITEM_SIMPLE,
			graphid);
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: DBupdate_graph_with_items                                        *
 *                                                                            *
 * Purpose: Update graph with items and recursion for template                *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
static int	DBupdate_graph_with_items(
		zbx_uint64_t	graphid,
		const char	*name,
		int		width,
		int		height,
		int		yaxismin,
		int		yaxismax,
		int		show_work_period,
		int		show_triggers,
		int		graphtype,
		int		show_legend,
		int		show_3d,
		double		percent_left,
		double		percent_right,
		int		ymin_type,
		int		ymax_type,
		zbx_uint64_t	ymin_itemid,
		zbx_uint64_t	ymax_itemid,
		ZBX_GRAPH_ITEMS	*gitems,
		int		gitems_num,
		zbx_uint64_t	templateid
	)
{
	DB_RESULT	db_hosts;
	DB_ROW		db_host_data;
	DB_RESULT	db_item_hosts;
	DB_ROW		db_item_data;
	DB_RESULT	chd_graphs;
	DB_ROW		chd_graph_data;
	zbx_uint64_t	curr_hostid = 0;
	int		curr_host_status = 0;
	zbx_uint64_t	new_hostid, chd_graphid = 0, chd_hostid;
	ZBX_GRAPH_ITEMS	*new_gitems = NULL;
	int		new_gitems_num = 0;
	int		i = 0;
	int		res = SUCCEED;

	zbx_uint64_t	*ids = NULL;
	int		ids_alloc = 0, ids_num = 0;

	char		*sql = NULL;
	int		sql_offset = 0, sql_alloc = 256;

	if (NULL == gitems)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Missed items for graph '%s'", name);
		return FAIL;
	}

	/* check items for template graph */
	db_hosts = DBselect(
			"select distinct h.hostid,h.status"
			" from hosts h,items i,graphs_items gi"
			" where h.hostid=i.hostid"
				" and i.itemid=gi.itemid"
				" and gi.graphid=" ZBX_FS_UI64,
			graphid);

	if (NULL != (db_host_data = DBfetch(db_hosts)))
	{
		ZBX_STR2UINT64(curr_hostid, db_host_data[0]);

		curr_host_status = atoi(db_host_data[1]);
	}
	DBfree_result(db_hosts);

	if (curr_host_status == HOST_STATUS_TEMPLATE)
	{
		for (i = 0; i < gitems_num; i++)
			uint64_array_add(&ids, &ids_alloc, &ids_num, gitems[i].itemid, 16);

		sql = zbx_malloc(sql, sql_alloc);

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 64,
				"select distinct hostid"
				" from items"
				" where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "itemid", ids, ids_num);

		db_item_hosts = DBselect("%s", sql);

		zbx_free(ids);
		zbx_free(sql);

		new_hostid = 0;
		while (NULL != (db_item_data = DBfetch(db_item_hosts)))
		{
			if (new_hostid)
			{
				zabbix_log(LOG_LEVEL_DEBUG, "Can not use multiple host items"
						" for template graph '%s'", name);
				res = FAIL;
				break;
			}

			ZBX_STR2UINT64(new_hostid, db_item_data[0]);
		}
		DBfree_result(db_item_hosts);

		if (SUCCEED == res && curr_hostid != new_hostid)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "You must use items only from host "
					ZBX_FS_UI64 " for template graph '%s'",
					curr_hostid, name);
			res = FAIL;
		}

		if (SUCCEED != res)
			return res;
	}

	/* first update child graphs */
	chd_graphs = DBselect(
			"select graphid"
			" from graphs"
			" where templateid=" ZBX_FS_UI64,
			graphid);

	while (NULL != (chd_graph_data = DBfetch(chd_graphs)))
	{
		ZBX_STR2UINT64(chd_graphid, chd_graph_data[0]);

		db_hosts = DBselect(
				"select distinct i.hostid"
				" from items i,graphs_items gi"
				" where i.itemid=gi.itemid"
					" and gi.graphid=" ZBX_FS_UI64,
				chd_graphid);

		chd_hostid = 0;
		if (NULL != (db_host_data = DBfetch(db_hosts)))
		{
			ZBX_STR2UINT64(chd_hostid, db_host_data[0]);
		}
		DBfree_result(db_hosts);

		if (FAIL == DBget_same_graphitems_for_host(gitems, gitems_num,
					&new_gitems, &new_gitems_num, chd_hostid))
		{	/* skip host with missing items */
			zabbix_log(LOG_LEVEL_DEBUG, "Can not update graph '%s'"
					" for host " ZBX_FS_UI64, name, chd_hostid);
			res = FAIL;
		}
		else
		{
			/* recursion */
			res = DBupdate_graph_with_items(chd_graphid, name, width, height,
					yaxismin, yaxismax, show_work_period, show_triggers,
					graphtype, show_legend, show_3d, percent_left,
					percent_right, ymin_type, ymax_type, ymin_itemid,
					ymax_itemid, new_gitems, new_gitems_num, graphid);

			zbx_free(new_gitems);
		}

		if (SUCCEED != res)
			break;
	}
	DBfree_result(chd_graphs);

	if (SUCCEED != res)
		return res;

	DBexecute("delete from graphs_items where graphid=" ZBX_FS_UI64, graphid);

	for (i = 0; i < gitems_num; i++)
		if (SUCCEED != (res = DBadd_graph_item(graphid, &gitems[i])))
			return res;

	if (SUCCEED == (res = DBupdate_graph(graphid,name,width,height,yaxismin,yaxismax,
			templateid,show_work_period,show_triggers,graphtype,show_legend,
			show_3d,percent_left,percent_right,ymin_type,ymax_type,
			ymin_itemid,ymax_itemid)))
		zabbix_log(LOG_LEVEL_DEBUG, "Graph '%s' updated for hosts " ZBX_FS_UI64, name, curr_hostid);

	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: DBadd_graph                                                      *
 *                                                                            *
 * Purpose: add graph                                                         *
 *                                                                            *
 * Parameters: new_graphid - return created graph database identificator      *
 *                                                                            *
 * Return value: always SUCCEED                                               *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
static int	DBadd_graph(
		zbx_uint64_t	*new_graphid,
		const char	*name,
		int		width,
		int		height,
		int		yaxismin,
		int		yaxismax,
		zbx_uint64_t	templateid,
		int		show_work_period,
		int		show_triggers,
		int		graphtype,
		int		show_legend,
		int		show_3d,
		double		percent_left,
		double		percent_right,
		int		ymin_type,
		int		ymax_type,
		zbx_uint64_t	ymin_itemid,
		zbx_uint64_t	ymax_itemid
	)
{
	char	*name_esc;

	*new_graphid = DBget_maxid("graphs", "graphid");

	name_esc = DBdyn_escape_string(name);

	DBexecute("insert into graphs"
			" (graphid,name,width,height,yaxismin,yaxismax,templateid,"
			"show_work_period,show_triggers,graphtype,show_legend,"
			"show_3d,percent_left,percent_right,ymin_type,ymax_type,"
			"ymin_itemid,ymax_itemid)"
			" values (" ZBX_FS_UI64 ",'%s',%d,%d,%d,%d," ZBX_FS_UI64
			",%d,%d,%d,%d,%d," ZBX_FS_DBL "," ZBX_FS_DBL ",%d,%d,"
			ZBX_FS_UI64 "," ZBX_FS_UI64 ")",
				*new_graphid,
				name_esc,
				width,
				height,
				yaxismin,
				yaxismax,
				templateid,
				show_work_period,
				show_triggers,
				graphtype,
				show_legend,
				show_3d,
				percent_left,
				percent_right,
				ymin_type,
				ymax_type,
				ymin_itemid,
				ymax_itemid);
	zbx_free(name_esc);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: DBadd_graph_with_items                                           *
 *                                                                            *
 * Purpose: Add graph with items and recursion for templates                  *
 *                                                                            *
 * Parameters: new_graphid - return created graph database identificator      *
 *                                                                            *
 * Return value: always SUCCEED                                               *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
static int	DBcopy_graph_to_host(zbx_uint64_t graphid, zbx_uint64_t hostid);

static int	DBadd_graph_with_items(
		zbx_uint64_t	*new_graphid,
		const char	*name,
		int		width,
		int		height,
		int		yaxismin,
		int		yaxismax,
		int		show_work_period,
		int		show_triggers,
		int		graphtype,
		int		show_legend,
		int		show_3d,
		double		percent_left,
		double		percent_right,
		int		ymin_type,
		int		ymax_type,
		zbx_uint64_t	ymin_itemid,
		zbx_uint64_t	ymax_itemid,
		ZBX_GRAPH_ITEMS	*gitems,
		int		gitems_num,
		zbx_uint64_t	templateid
	)
{
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	chd_hostid = 0;
	int		i = 0, new_host_count = 0,
			new_host_is_template = 0;
	int		res = FAIL;

	zbx_uint64_t	*ids = NULL;
	int		ids_alloc = 0, ids_num = 0;

	char		*sql = NULL;
	int		sql_offset = 0, sql_alloc = 256;


	if (NULL == gitems || 0 == gitems_num)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Missing items for graph '%s'", name);
		return res;
	}

	/* check items for template graph */
	for (i = 0; i < gitems_num; i++)
		uint64_array_add(&ids, &ids_alloc, &ids_num, gitems[i].itemid, 16);

	sql = zbx_malloc(sql, sql_alloc);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 128,
			"select distinct h.hostid,h.status"
			" from items i,hosts h"
			" where h.hostid=i.hostid"
				" and");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "i.itemid", ids, ids_num);

	result = DBselect("%s", sql);

	zbx_free(ids);
	zbx_free(sql);

	while (NULL != (row = DBfetch(result)))
	{
		new_host_count++;
		if (HOST_STATUS_TEMPLATE == atoi(row[1]))
			new_host_is_template = 1;
	}
	DBfree_result(result);

	if (new_host_is_template && new_host_count > 1)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Graph '%s' with template host"
				" can not contain items from other hosts.", name);
		return res;
	}

	if (SUCCEED == (res = DBadd_graph(new_graphid,name,width,height,yaxismin,yaxismax,
			templateid,show_work_period,show_triggers,graphtype,show_legend,
			show_3d,percent_left,percent_right,ymin_type,ymax_type,
			ymin_itemid,ymax_itemid)))
	{
		for (i = 0; i < gitems_num; i++)
			if (SUCCEED != (res = DBadd_graph_item(*new_graphid, &gitems[i])))
				break;
	}

	if (SUCCEED == res)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Graph '%s' added", name);

		/* add graphs for child hosts */
		result = DBselect(
				"select distinct ht.hostid"
				" from hosts_templates ht,items i,graphs_items gi"
				" where ht.templateid=i.hostid"
					" and i.itemid=gi.itemid"
					" and gi.graphid=" ZBX_FS_UI64,
				*new_graphid);

		while (NULL != (row = DBfetch(result)))
		{
			ZBX_STR2UINT64(chd_hostid, row[0]);
			DBcopy_graph_to_host(*new_graphid, chd_hostid);
		}

		DBfree_result(result);
	}

	if (SUCCEED != res && *new_graphid)
	{
		DBdelete_graph(*new_graphid);
		*new_graphid = 0;
	}

	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: DBdelete_item                                                    *
 *                                                                            *
 * Purpose: delete item from database                                         *
 *                                                                            *
 * Parameters: itemid - item identificator from database                      *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
static int	DBdelete_item(
		zbx_uint64_t itemid
	)
{
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	elementid;
	char		*graphs = NULL;
	int		graphs_alloc = 32, graphs_offset = 0;
	int		res = SUCCEED;

	/* first delete child items */
	result = DBselect("select itemid from items where templateid=" ZBX_FS_UI64, itemid);

	while (NULL != (row = DBfetch(result)))
	{	/* recursion */
		ZBX_STR2UINT64(elementid, row[0]);
		if (SUCCEED != (res = DBdelete_item(elementid)))
			break;
	}
	DBfree_result(result);

	graphs = zbx_malloc(graphs, graphs_alloc);
	*graphs = '\0';

	/* select graphs with this item */
	if (SUCCEED == res)
	{
		result = DBselect("select distinct graphid from graphs_items where itemid=" ZBX_FS_UI64, itemid);

		while (NULL != (row = DBfetch(result)))
		{
			zbx_snprintf_alloc(&graphs, &graphs_alloc, &graphs_offset, 22, "%s%s",
					0 == graphs_offset ? "" : ",", row[0]);
		}
		DBfree_result(result);
	}

	if (SUCCEED == res)
	{
		result = DBselect("select i.itemid,i.key_,h.host from items i,"
					" hosts h where h.hostid=i.hostid and i.itemid=" ZBX_FS_UI64, itemid);

		if (NULL != (row = DBfetch(result)))
		{
			if (SUCCEED == (res = DBdelete_triggers_by_itemid(itemid)))
			if (SUCCEED == (res = DBdelete_history_by_itemid(itemid, 1 /* use housekeeper */)))
			{
				DBexecute("delete from screens_items where resourceid=" ZBX_FS_UI64 " and resourcetype in (%i,%i)",
						itemid, SCREEN_RESOURCE_PLAIN_TEXT, SCREEN_RESOURCE_SIMPLE_GRAPH);

				DBexecute("delete from graphs_items where itemid=" ZBX_FS_UI64, itemid);
				DBexecute("delete from items_applications where itemid=" ZBX_FS_UI64, itemid);
				DBexecute("delete from items where itemid=" ZBX_FS_UI64, itemid);

				zabbix_log( LOG_LEVEL_DEBUG, "Item '%s:%s' deleted", row[2], row[1]);
			}
		}
		DBfree_result(result);
	}

	/* delete graphs without items */
	if (SUCCEED == res && 0 != graphs_offset)
	{
		result = DBselect("select graphid from graphs"
				" where not graphid in (select graphid from graphs_items)"
					" and graphid in (%s)", graphs);
		while (NULL != (row = DBfetch(result)))
		{
			ZBX_STR2UINT64(elementid, row[0]);
			if (SUCCEED != (res = DBdelete_graph(elementid)))
				break;
		}
		DBfree_result(result);
	}

	zbx_free(graphs);

	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: DBdelete_application                                             *
 *                                                                            *
 * Purpose: delete application                                                *
 *                                                                            *
 * Parameters: applicationid - application identificator from database        *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
static int	DBdelete_application(
		zbx_uint64_t applicationid
	)
{
	DB_RESULT	db_applicatoins;
	DB_RESULT	db_elements;

	DB_ROW		application_data;
	DB_ROW		element_data;

	zbx_uint64_t
		elementid;

	int result = FAIL;

	db_applicatoins = DBselect("select a.applicationid,a.name,h.host from applications a,"
				" hosts h where h.hostid=a.hostid and a.applicationid=" ZBX_FS_UI64, applicationid);

	if( (application_data = DBfetch(db_applicatoins)) )
	{
		result = SUCCEED;

		/* first delete child applications */
		db_elements = DBselect("select applicationid from applications where templateid=" ZBX_FS_UI64, applicationid);

		while( (element_data = DBfetch(db_elements)) )
		{/* recursion */
			ZBX_STR2UINT64(elementid, element_data[0]);
			if( SUCCEED != (result = DBdelete_application(elementid)) )
				break;
		}

		DBfree_result(db_elements);

		if( SUCCEED == result )
		{
			db_elements = DBselect("select name from httptest where applicationid=" ZBX_FS_UI64, applicationid);

			if( (element_data = DBfetch(db_elements)) )
			{
				zabbix_log( LOG_LEVEL_DEBUG, "Application '%s:%s' used by scenario '%s'", application_data[2], application_data[1], element_data[0]);
				result = FAIL;
			}

			DBfree_result(db_elements);
		}

		if( SUCCEED == result )
		{
			db_elements = DBselect("select i.key_, i.description from items_applications ia, items i "
					" where i.type=%i and i.itemid=ia.itemid and ia.applicationid=" ZBX_FS_UI64, ITEM_TYPE_HTTPTEST, applicationid);
			if( (element_data = DBfetch(db_elements)) )
			{
				zabbix_log( LOG_LEVEL_DEBUG, "Application '%s:%s' used by item '%s'", application_data[2], application_data[1], element_data[0]);
				result = FAIL;
			}

			DBfree_result(db_elements);
		}

		if( SUCCEED == result )
		{
			DBexecute("delete from items_applications where applicationid=" ZBX_FS_UI64, applicationid);
			DBexecute("delete from applications where applicationid=" ZBX_FS_UI64, applicationid);

			zabbix_log( LOG_LEVEL_DEBUG, "Application '%s:%s' deleted", application_data[2], application_data[1]);
		}
	}

	DBfree_result(db_applicatoins);

	return result;
}

/******************************************************************************
 *                                                                            *
 * Function: DBdelete_template_graphs                                         *
 *                                                                            *
 * Purpose: delete template graphs from host                                  *
 *                                                                            *
 * Parameters: hostid - host identificator from database                      *
 *             templateid - template identificator from database              *
 *             unlink_mode - 1 - only unlink elements without deletion        *
 *                           0 - delete elements                              *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
static void	DBdelete_template_graphs(
		zbx_uint64_t hostid,
		zbx_uint64_t templateid,
		unsigned char unlink_mode
	)
{
	const char	*__function_name = "DBdelete_template_graphs";
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	graphid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	result = DBselect("select distinct g.graphid,g.name"
			" from items ti,graphs_items tgi,graphs tg,graphs g,graphs_items gi,items i"
			" where ti.hostid=" ZBX_FS_UI64 " and ti.itemid=tgi.itemid and tgi.graphid=tg.graphid"
				" and g.templateid=tg.graphid and gi.graphid=g.graphid and i.itemid=gi.itemid"
				" and i.hostid=" ZBX_FS_UI64,
			templateid,
			hostid);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(graphid, row[0]);

		if (unlink_mode)
		{
			DBexecute("update graphs set templateid=0 where graphid=" ZBX_FS_UI64, graphid);

			zabbix_log(LOG_LEVEL_DEBUG, "Graph '%s' unlinked", row[1]);
		}
		else
			DBdelete_graph(graphid);
	}
	DBfree_result(result);

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
 *             unlink_mode - 1 - only unlink elements without deletion        *
 *                           0 - delete elements                              *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
static void	DBdelete_template_triggers(
		zbx_uint64_t hostid,
		zbx_uint64_t templateid,
		unsigned char unlink_mode
	)
{
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	triggerid;

	result = DBselect("select distinct g.triggerid,g.description"
			" from items ti,functions tf,triggers tg,triggers g,functions f,items i"
			" where ti.hostid=" ZBX_FS_UI64 " and ti.itemid=tf.itemid and tf.triggerid=tg.triggerid"
				" and g.templateid=tg.triggerid and f.triggerid=g.triggerid and i.itemid=f.itemid"
				" and i.hostid=" ZBX_FS_UI64,
			templateid,
			hostid);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(triggerid, row[0]);

		if (unlink_mode)
		{
			DBexecute("update triggers set templateid=0 where triggerid=" ZBX_FS_UI64, triggerid);

			zabbix_log(LOG_LEVEL_DEBUG, "Trigger '%s' unlinked", row[1]);
		}
		else
			DBdelete_trigger(triggerid);
	}
	DBfree_result(result);
}

/******************************************************************************
 *                                                                            *
 * Function: DBdelete_template_items                                          *
 *                                                                            *
 * Purpose: delete template items from host                                   *
 *                                                                            *
 * Parameters: hostid - host identificator from database                      *
 *             templateid - template identificator from database              *
 *             unlink_mode - 1 - only unlink elements without deletion        *
 *                           0 - delete elements                              *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
static void	DBdelete_template_items(
		zbx_uint64_t hostid,
		zbx_uint64_t templateid,
		unsigned char unlink_mode
	)
{
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	itemid;

	result = DBselect("select distinct i.itemid,i.key_ from items i,items t where t.itemid=i.templateid"
			" and i.hostid=" ZBX_FS_UI64 " and t.hostid=" ZBX_FS_UI64,
			hostid,
			templateid);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(itemid, row[0]);

		if (unlink_mode)
		{
			DBexecute("update items set templateid=0 where itemid=" ZBX_FS_UI64, itemid);

			zabbix_log(LOG_LEVEL_DEBUG, "Item '%s' unlinked", row[1]);
		}
		else
			DBdelete_item(itemid);
	}
	DBfree_result(result);
}

/******************************************************************************
 *                                                                            *
 * Function: DBdelete_template_applications                                   *
 *                                                                            *
 * Purpose: delete application                                                *
 *                                                                            *
 * Parameters: hostid - host identificator from database                      *
 *             templateid - template identificator from database              *
 *             unlink_mode - 1 - only unlink elements without deletion        *
 *                           0 - delete elements                              *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
static void	DBdelete_template_applications(
		zbx_uint64_t hostid,
		zbx_uint64_t templateid,
		unsigned char unlink_mode
	)
{
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	applicationid;

	result = DBselect("select a.applicationid,a.name from applications a,applications ta"
			" where a.hostid=" ZBX_FS_UI64 " and ta.hostid=" ZBX_FS_UI64 " and ta.applicationid=a.templateid",
			hostid,
			templateid);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(applicationid, row[0]);

		if (unlink_mode)
		{
			DBexecute("update applications set templateid=0 where applicationid=" ZBX_FS_UI64, applicationid);

			zabbix_log( LOG_LEVEL_DEBUG, "Application '%s' unlinked", row[1]);
		}
		else
			DBdelete_application(applicationid);
	}

	DBfree_result(result);
}

/******************************************************************************
 *                                                                            *
 * Function: DBreset_items_nextcheck                                          *
 *                                                                            *
 * Purpose: reset next check timestamps for items                             *
 *                                                                            *
 * Parameters: triggerid = trigger identificator from database                *
 *                                                                            *
 * Return value: always SUCCEED                                               *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
static int	DBreset_items_nextcheck(zbx_uint64_t triggerid)
{
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	itemid;

	result = DBselect(
			"select itemid"
			" from functions"
			" where triggerid=" ZBX_FS_UI64,
			triggerid);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(itemid, row[0]);
		DCreset_item_nextcheck(itemid);
	}
	DBfree_result(result);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: DBreplace_template_dependences                                   *
 *                                                                            *
 * Purpose: replace trigger dependencies by specified host                    *
 *                                                                            *
 * Parameters: dependences_in - zero terminated array of dependencies         *
 *             hostid - host identificator from database                      *
 *             dependences - buffer for result values                         *
 *             max_dependences - size of result buffer in counts              *
 *                                                                            *
 * Return value: count of results                                             *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
static int	DBreplace_template_dependences(
		zbx_uint64_t	*dependences_in,
		zbx_uint64_t	hostid,
		zbx_uint64_t	*dependences,
		int		max_dependences
	)
{
	DB_RESULT	db_triggers;

	DB_ROW		trigger_data;

	register int	i = 0, j = 0;

	while( 0 < dependences_in[i] )
	{
		db_triggers = DBselect("select t.triggerid from triggers t,functions f,items i "
				" where t.templateid=" ZBX_FS_UI64 " and f.triggerid=t.triggerid "
				" and f.itemid=i.itemid and i.hostid=" ZBX_FS_UI64,
				dependences_in[i], hostid);

		if( j < (max_dependences - 1) && (trigger_data = DBfetch(db_triggers)) )
		{
			ZBX_STR2UINT64(dependences[j], trigger_data[0]);
			j++;
		}

		DBfree_result(db_triggers);

		i++;
	}

	dependences[j] = 0;

	return j;
}

/******************************************************************************
 *                                                                            *
 * Function: DBadd_event                                                      *
 *                                                                            *
 * Purpose: add event without actions processing                              *
 *                                                                            *
 * Parameters: triggerid - trigger identificator from database                *
 *             value - event value                                            *
 *             now - timestamp                                                *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
static int	DBadd_event(
		zbx_uint64_t	triggerid,
		int		value,
		time_t		now
		)
{
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	eventid;
	int		res = FAIL;

	if (0 == now)
		now = time(NULL);

	result = DBselect(
			"select value"
			" from triggers"
			" where triggerid=" ZBX_FS_UI64,
			triggerid);

	if (NULL != (row = DBfetch(result)))
	{
		if (value != atoi(row[0]))
			res = SUCCEED;
	}
	DBfree_result(result);

	if (SUCCEED == res)
	{
		eventid = DBget_maxid("events", "eventid");

		DBexecute(
				"insert into events"
					" (eventid,source,object,objectid,clock,value)"
				" values"
					" (" ZBX_FS_UI64 ",%d,%d," ZBX_FS_UI64 ",%d,%d)",
				eventid, EVENT_SOURCE_TRIGGERS, EVENT_OBJECT_TRIGGER,
				triggerid, (int)now, value);
	}

	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: DBimplode_exp                                                    *
 *                                                                            *
 * Purpose: implode normal mode expression to short mode                      *
 *          {hostX:keyY.functionZ(parameterN)}=1 implode to {11}=1            *
 *                                                                            *
 * Parameters: expression - null terminated string                            *
 *             triggerid - trigger identificator from database                *
 *                                                                            *
 * Return value: dynamically allocated memory for imploded expression         *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: function dynamically allocates memory, don't forget to free it   *
 *           !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
static char*	DBimplode_exp (
		char		*expression,
		zbx_uint64_t	triggerid
	)
{
	DB_RESULT	db_items;

	DB_ROW		item_data;

	zbx_uint64_t
		functionid,
		itemid;

	char	*exp = NULL;

	int
		host_len = 0,
		key_len = 0,
		function_len = 0,
		parameter_len = 0;

	char	*sql = NULL,
		*simple_start = NULL,
		*simple_end = NULL,
		*pos = NULL,
		*p = NULL,
		*host = NULL,
		*key = NULL,
		*function = NULL,
		*parameter = NULL,
		*str_esc = NULL;

	/* Translate localhost:procload.last(0)>10 to {12}>10 */

	for ( pos = expression; pos && *pos; )
	{
		if( NULL == (simple_start = strchr(pos, '{')) )	break;
		if( NULL == (simple_end = strchr(simple_start, '}')) )	break;

		host		= NULL;
		key		= NULL;
		function	= NULL;
		parameter	= NULL;

		host_len	= 0;
		key_len		= 0;
		function_len	= 0;
		parameter_len	= 0;

		*simple_start = '\0';
		exp = zbx_strdcat(exp, pos);
		*simple_start = '{';

		simple_start++;
		pos = simple_end+1;

		*simple_end = '\0';
		do { /* simple try for C */
			/* determine host */
			host = simple_start;
			if( NULL == (p = strchr(host, ':'))) break; /* simple throw for C */
			host_len = p - host;

			/* determine parameter */
			if( NULL == (p = strrchr(p+1, '(')) ) break; /* simple throw for C */
			parameter = p+1;

			if( NULL == (p = strrchr(parameter, ')')) ) break; /* simple throw for C */
			parameter_len = p - parameter;

			/* determine key and function */
			p = parameter-1; /* position of '(' character */
			*p = '\0';

			key = host + host_len + 1;
			if( NULL == (p = strrchr(key, '.'))) break; /* simple throw for C */
			key_len = p - key;

			function = p + 1;
			function_len = parameter - 1 - function;

			p = parameter-1; /* position of '(' character */
			*p = '(';

		} while(0);/* simple finally for C */

		if( host && host_len && key && key_len && function && function_len && parameter && parameter_len )
		{
			sql = zbx_strdcat(NULL, "select distinct i.itemid from items i,hosts h where h.hostid=i.hostid ");
			/* adding host */
			host[host_len] = '\0';
			str_esc = DBdyn_escape_string(host);
			sql = zbx_strdcatf(sql, " and h.host='%s'", str_esc);
			zbx_free(str_esc);
			host[host_len] = ':';

			/* adding key */
			key[key_len] = '\0';
			str_esc = DBdyn_escape_string(key);
			sql = zbx_strdcatf(sql, " and i.key_='%s'", str_esc);
			zbx_free(str_esc);
			key[key_len] = '.';

			db_items = DBselect("%s",sql);

			zbx_free(sql);

			if( (item_data = DBfetch(db_items)) )
			{
				ZBX_STR2UINT64(itemid, item_data[0]);
				functionid = DBget_maxid("functions","functionid");

				sql = zbx_dsprintf(NULL, "insert into functions (functionid,itemid,triggerid,function,parameter)"
						" values (" ZBX_FS_UI64 "," ZBX_FS_UI64 "," ZBX_FS_UI64 ",",
						functionid, itemid, triggerid);
				/* adding function */
				function[function_len] = '\0';
				str_esc = DBdyn_escape_string_len(function, FUNCTION_FUNCTION_LEN);
				sql = zbx_strdcatf(sql, "'%s',", str_esc);
				zbx_free(str_esc);
				function[function_len] = '(';
				/* adding parameter */
				parameter[parameter_len] = '\0';
				str_esc = DBdyn_escape_string_len(parameter, FUNCTION_PARAMETER_LEN);
				sql = zbx_strdcatf(sql, "'%s')", str_esc);
				zbx_free(str_esc);
				parameter[parameter_len] = ')';

				DBexecute("%s",sql);

				exp = zbx_strdcatf(exp, "{" ZBX_FS_UI64 "}", functionid);

				zbx_free(sql);
			}

			DBfree_result(db_items);
		}
		else
		{
			exp = zbx_strdcatf(exp, "{%s}", simple_start);
		}
		*simple_end = '}';
	}

	if( pos )  exp = zbx_strdcat(exp, pos);

	return exp;
}

/******************************************************************************
 *                                                                            *
 * Function: DBget_trigger_dependences_by_triggerid                           *
 *                                                                            *
 * Purpose: retrieve trigger dependencies                                     *
 *                                                                            *
 * Parameters: triggerid - trigger identificator from database                *
 *             dependences - buffer for result                                *
 *             max_dependences - size of buffer in counts                     *
 *                                                                            *
 * Return value: count of dependencies                                         *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
static int	DBget_trigger_dependences_by_triggerid(
		zbx_uint64_t	triggerid,
		zbx_uint64_t	*dependences,
		int		max_dependences
	)
{
	DB_RESULT	db_triggers;

	DB_ROW		trigger_data;

	register int	i = 0;

	db_triggers = DBselect("select triggerid_up from trigger_depends where triggerid_down=" ZBX_FS_UI64, triggerid);

	while( i < (max_dependences - 1) && (trigger_data = DBfetch(db_triggers)) )
	{
		ZBX_STR2UINT64(dependences[i], trigger_data[0]);
		i++;
	}

	DBfree_result(db_triggers);

	dependences[i] = 0;

	return i;
}

/******************************************************************************
 *                                                                            *
 * Function: DBexplode_exp                                                    *
 *                                                                            *
 * Purpose: explode short trigger expression to normal mode                   *
 *          {11}=1 explode to {hostX:keyY.functionZ(parameterN)}=1            *
 *                                                                            *
 * Parameters: short_expression - null terminated string                      *
 *                                                                            *
 * Return value: dynamically allocated memory for exploded expression         *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: function dynamically allocates memory, don't forget to free it   *
 *           !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 *                                                                            *
 ******************************************************************************/
static char* DBexplode_exp (char *short_expression)
{
	typedef enum {
		EXP_NONE,
		EXP_FUNCTIONID
	} expression_parsing_states;

	int state = EXP_NONE;

	DB_RESULT	db_functions;

	DB_ROW		function_data;

	char
		tmp_chr,
		*p_functionid = NULL,
		*exp = NULL;

	int	p_functionid_len=0;

	zbx_uint64_t
		functionid;


	register char	*c;

	for( c=short_expression; c && *c; c++ )
	{
		if( '{' == *c )
		{
			state = EXP_FUNCTIONID;
			p_functionid = c+1;
			p_functionid_len = 0;
		}
		else if( '}' == *c && EXP_FUNCTIONID == state && p_functionid &&  p_functionid_len > 0)
		{
			state = EXP_NONE;

			tmp_chr = p_functionid[p_functionid_len];
			p_functionid[p_functionid_len] = '\0';

			if( 0 == strcmp("TRIGGER.VALUE", p_functionid))
			{
				exp = zbx_strdcatf(exp, "{%s}", p_functionid);
			}
			else
			{
				ZBX_STR2UINT64(functionid, p_functionid);

				db_functions = DBselect("select h.host,i.key_,f.function,f.parameter,i.itemid,i.value_type "
						" from items i,functions f,hosts h "
						" where functionid=" ZBX_FS_UI64 " and i.itemid=f.itemid and h.hostid=i.hostid",
						functionid);

				if( (function_data = DBfetch(db_functions)) )
				{
					exp = zbx_strdcatf(exp, "{%s:%s.%s(%s)}", function_data[0],
							function_data[1], function_data[2], function_data[3]);
				}
				else
				{
					exp = zbx_strdcat(exp, "{*ERROR*}");
				}

				DBfree_result(db_functions);
			}

			p_functionid[p_functionid_len] = tmp_chr;
		}
		else if( EXP_FUNCTIONID == state )
		{
			p_functionid_len++;
		}
		else
		{
			exp = zbx_strdcatf(exp, "%c", *c);
		}
	}

	return exp;
}

/******************************************************************************
 *                                                                            *
 * Function: DBupdate_trigger                                                 *
 *                                                                            *
 * Purpose: update trigger                                                    *
 *                                                                            *
 * Parameters: triggerid - trigger identificator from database                *
 *             expression - new expression (NULL to skip update)              *
 *             description - new description (NULL to skip update)            *
 *             priority - new priority (-1 to skip updete)                    *
 *             status - new status (-1 to skip updete)                        *
 *             comments - new comments (NULL to skip update)                  *
 *             url - new url (NULL to skip update)                            *
 *             dependences - null terminated array with dependencies          *
 *             templateid - template trigger identificator from database      *
 *                                                                            *
 * Return value: always SUCCEED                                               *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
static int	DBupdate_trigger(
		zbx_uint64_t	triggerid,
		char		*expression,
		const char	*description,
		int		priority,
		int		status,
		const char	*comments,
		const char	*url,
		int		type,
		zbx_uint64_t	*dependences,
		zbx_uint64_t	templateid
	)
{
	DB_RESULT	db_triggers;
	DB_RESULT	db_chd_triggers;
	DB_RESULT	db_chd_hosts;

	DB_ROW		trigger_data;
	DB_ROW		chd_trigger_data;
	DB_ROW		chd_host_data;

	zbx_uint64_t
		chd_hostid,
		chd_triggerid,
		new_dependences[ZBX_MAX_DEPENDENCES];

	char
		*search = NULL,
		*replace = NULL,
		*new_expression = NULL,
		*exp_expression = NULL,
		*short_expression = NULL,
		*sql = NULL,
		*str_esc = NULL;

	int	i = 0;

	db_triggers = DBselect("select distinct t.description,h.host,t.expression,t.priority,t.status,t.comments,t.url,t.type "
			" from triggers t,functions f,items i,hosts h "
			" where t.triggerid=" ZBX_FS_UI64 " and f.triggerid=t.triggerid "
			" and i.itemid=f.itemid and i.hostid=h.hostid", triggerid);

	if( (trigger_data = DBfetch(db_triggers)) )
	{
		if( !expression )	expression = exp_expression = DBexplode_exp(trigger_data[2]);
		if( !description )	description = trigger_data[0];
		if( -1 == priority )	priority = atoi(trigger_data[3]);
		if( -1 == status )	priority = atoi(trigger_data[4]);
		if( !comments )		comments = trigger_data[5];
		if( !url )		url = trigger_data[6];
		if( -1 == type )	type = atoi(trigger_data[7]);

		search = zbx_dsprintf(search, "{%s:", trigger_data[1] /* template host */);

		db_chd_triggers = DBselect("select distinct triggerid from triggers where templateid=" ZBX_FS_UI64, triggerid);

		while( (chd_trigger_data = DBfetch(db_chd_triggers)) )
		{
			ZBX_STR2UINT64(chd_triggerid, chd_trigger_data[0]);

			db_chd_hosts = DBselect("select distinct h.hostid,h.host from hosts h,items i,functions f "
					" where f.triggerid=" ZBX_FS_UI64 " and f.itemid=i.itemid and i.hostid=h.hostid");

			if( (chd_host_data = DBfetch(db_chd_hosts)) )
			{
				ZBX_STR2UINT64(chd_hostid, chd_host_data[0]);

				replace = zbx_dsprintf(replace, "{%s:", chd_host_data[0] /* child host */);

				new_expression = string_replace(expression, search, replace);

				zbx_free(replace);

				DBreplace_template_dependences(dependences, chd_hostid, new_dependences, sizeof(new_dependences) / sizeof(zbx_uint64_t));

				/* recursion */
				DBupdate_trigger(
					chd_triggerid,
					new_expression,
					description,
					priority,
					-1,           /* status */
					comments,
					url,
					type,
					new_dependences,
					triggerid);

				zbx_free(new_expression);
			}

			DBfree_result(db_chd_hosts);
		}
		zbx_free(search);

		DBfree_result(db_chd_triggers);

		DBexecute("delete from functions where triggerid=" ZBX_FS_UI64, triggerid);

		sql = zbx_strdcat(NULL, "update triggers set");

		if( expression ) {
			short_expression = DBimplode_exp(expression, triggerid);
			str_esc = DBdyn_escape_string_len(short_expression, TRIGGER_EXPRESSION_LEN);
			sql = zbx_strdcatf(sql, " expression='%s',", str_esc);
			zbx_free(str_esc);
			zbx_free(short_expression);

		}
		if( description ) {
			str_esc = DBdyn_escape_string(description);
			sql = zbx_strdcatf(sql, " description='%s',", str_esc);
			zbx_free(str_esc);
		}
		if( priority >= 0 )	sql = zbx_strdcatf(sql, " priority=%i,", priority);
		if( status >= 0 )	sql = zbx_strdcatf(sql, " status=%i,", status);
		if( comments )
		{
			str_esc = DBdyn_escape_string(comments);
			sql = zbx_strdcatf(sql, " comments='%s',", str_esc);
			zbx_free(str_esc);
		}
		if( url ) {
			str_esc = DBdyn_escape_string_len(url, TRIGGER_URL_LEN);
			sql = zbx_strdcatf(sql, " url='%s',", str_esc);
			zbx_free(str_esc);
		}
		if( templateid )	sql = zbx_strdcatf(sql, " templateid=" ZBX_FS_UI64 ",", templateid);

		if( type >= 0 )		sql = zbx_strdcatf(sql, " type=%i,", type);

		sql = zbx_strdcatf(sql, " value=2 where triggerid=" ZBX_FS_UI64,	triggerid);

		DBexecute("%s",sql);

		zbx_free(sql);

		DBreset_items_nextcheck(triggerid);

		DBadd_event(triggerid, TRIGGER_VALUE_UNKNOWN, 0);

		DBdelete_dependencies_by_triggerid(triggerid);

		for( i=0; 0 < dependences[i]; i++ )
		{
			DBinsert_dependency(triggerid, dependences[i]);
		}

		zbx_free(exp_expression);

		zabbix_log(LOG_LEVEL_DEBUG, "Trigger '%s' updated", trigger_data[0]);;
	}

	DBfree_result(db_triggers);

	return SUCCEED;
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
 * Return value: 0 - if triggers coincide                                     *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
static int	DBcmp_triggers(
		zbx_uint64_t triggerid1,
		zbx_uint64_t triggerid2
	)
{
	DB_RESULT	db_trigers1;
	DB_RESULT	db_trigers2;
	DB_RESULT	db_functions;

	DB_ROW		trigger1_data;
	DB_ROW		trigger2_data;
	DB_ROW		function_data;

	char
		*search = NULL,
		*replace = NULL,
		*old_expr = NULL,
		*expr = NULL;

	int	result = 1;

	db_trigers1 = DBselect("select expression from triggers where triggerid=" ZBX_FS_UI64, triggerid1);

	if( (trigger1_data = DBfetch(db_trigers1)) )
	{
		db_trigers2 = DBselect("select expression from triggers where triggerid=" ZBX_FS_UI64, triggerid2);

		if( (trigger2_data = DBfetch(db_trigers2)) )
		{
			expr = strdup(trigger2_data[0]);

			db_functions = DBselect("select f1.functionid,f2.functionid from functions f1,functions f2,items i1,items i2 "
			" where f1.function=f2.function and f1.parameter=f2.parameter and i1.key_=i2.key_ "
			" and i1.itemid=f1.itemid and i2.itemid=f2.itemid and f1.triggerid=" ZBX_FS_UI64 " and f2.triggerid=" ZBX_FS_UI64,
				triggerid1, triggerid2);

			while( (function_data = DBfetch(db_functions)) )
			{
				search = zbx_dsprintf(NULL, "{%s}", function_data[1]);
				replace = zbx_dsprintf(NULL, "{%s}", function_data[0]);

				old_expr = expr;
				expr = string_replace(old_expr, search, replace);
				zbx_free(old_expr);

				zbx_free(replace);
				zbx_free(search);
			}

			DBfree_result(db_functions);

			result = strcmp(trigger1_data[0], expr);

			zbx_free(expr);
		}

		DBfree_result(db_trigers2);
	}

	DBfree_result(db_trigers1);

	return result;
}

/******************************************************************************
 *                                                                            *
 * Function: DBcopy_trigger_to_host                                           *
 *                                                                            *
 * Purpose: copy specified trigger to host                                    *
 *                                                                            *
 * Parameters: triggerid - trigger identificator from database                *
 *             hostid - host identificator from database                      *
 *             copy_mode - 1 - only copy elements without linkage             *
 *                         0 - copy and link elements                         *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
static int	DBcopy_trigger_to_host(
		zbx_uint64_t triggerid,
		zbx_uint64_t hostid)
{
	DB_RESULT	db_triggers;
	DB_RESULT	db_host_triggers;
	DB_RESULT	db_chd_hosts;
	DB_RESULT	db_functions;
	DB_RESULT	db_items;

	DB_ROW		trigger_data;
	DB_ROW		host_trigger_data;
	DB_ROW		chd_host_data;
	DB_ROW		function_data;
	DB_ROW		item_data;

	zbx_uint64_t
		itemid,
		h_triggerid,
		chd_hostid,
		functionid,
		new_triggerid,
		new_functionid,
		dependences[ZBX_MAX_DEPENDENCES],
		new_dependences[ZBX_MAX_DEPENDENCES];

	char
		*old_expression = NULL,
		*new_expression = NULL,
		*new_expression_esc = NULL,
		*search = NULL,
		*replace = NULL,
		*description_esc = NULL,
		*comments_esc = NULL,
		*url_esc = NULL,
		*function_esc = NULL,
		*parameter_esc = NULL;

	int	i = 0;

	int	result = SUCCEED;

	db_triggers = DBselect("select description,priority,status,comments,url,expression,type from triggers where triggerid=" ZBX_FS_UI64, triggerid);

	if( (trigger_data = DBfetch(db_triggers)) )
	{
		result = FAIL;

		DBget_trigger_dependences_by_triggerid(triggerid, dependences, sizeof(dependences) / sizeof(zbx_uint64_t));
		DBreplace_template_dependences(dependences, hostid, new_dependences, sizeof(new_dependences) / sizeof(zbx_uint64_t));

		db_host_triggers = DBselect("select distinct t.triggerid from functions f,items i,triggers t "
				" where t.templateid=0 and t.triggerid=f.triggerid and i.itemid=f.itemid and i.hostid=" ZBX_FS_UI64, hostid);

		while( (host_trigger_data = DBfetch(db_host_triggers)) )
		{
			ZBX_STR2UINT64(h_triggerid, host_trigger_data[0]);

			if( DBcmp_triggers(triggerid, h_triggerid) )	continue;

			/* link not linked trigger with same expression */
			result = DBupdate_trigger(
				h_triggerid,
				NULL,			/* expression */
				trigger_data[0],	/* description */
				atoi(trigger_data[1]),	/* priority */
				-1,			/* status */
				trigger_data[3],	/* comments */
				trigger_data[4],	/* url */
				atoi(trigger_data[6]),	/* type */
				new_dependences,
				triggerid);

			break;
		}

		DBfree_result(db_host_triggers);

		if( SUCCEED != result )
		{ /* create trigger if no updated triggers */
			result = SUCCEED;

			new_triggerid = DBget_maxid("triggers","triggerid");

			description_esc = DBdyn_escape_string(trigger_data[0]);
			comments_esc = DBdyn_escape_string(trigger_data[3]);
			url_esc = DBdyn_escape_string(trigger_data[4]);

			DBexecute("insert into triggers"
				" (triggerid,description,priority,status,comments,url,type,value,expression,templateid)"
				" values (" ZBX_FS_UI64 ",'%s',%i,%i,'%s','%s',%i,2,'0'," ZBX_FS_UI64 ")",
					new_triggerid,
					description_esc,	/* description */
					atoi(trigger_data[1]),	/* priority */
					atoi(trigger_data[2]),	/* status */
					comments_esc,		/* comments */
					url_esc,		/* url */
					atoi(trigger_data[6]),	/* type */
					triggerid);

			zbx_free(url_esc);
			zbx_free(comments_esc);
			zbx_free(description_esc);

			new_expression = strdup(trigger_data[5]);

			/* Loop: functions */
			db_functions = DBselect("select itemid,function,parameter,functionid from functions "
						" where triggerid=" ZBX_FS_UI64, triggerid);
/* TODO: out of memory!!! */
			while( SUCCEED == result && (function_data = DBfetch(db_functions)) )
			{
				ZBX_STR2UINT64(itemid, function_data[0]);
				ZBX_STR2UINT64(functionid, function_data[3]);

				function_esc = DBdyn_escape_string(function_data[1]);
				parameter_esc = DBdyn_escape_string(function_data[2]);

				search = zbx_dsprintf(NULL, "{" ZBX_FS_UI64 "}", functionid);

				db_items = DBselect("select i2.itemid from items i1, items i2 "
						" where i2.key_=i1.key_ and i2.hostid=" ZBX_FS_UI64
						" and i1.itemid=" ZBX_FS_UI64, hostid, itemid);

				if( (item_data = DBfetch(db_items)) )
				{
					ZBX_STR2UINT64(itemid, item_data[0]);

					new_functionid = DBget_maxid("functions","functionid");

					replace = zbx_dsprintf(NULL, "{" ZBX_FS_UI64 "}", new_functionid);

					DBexecute("insert into functions (functionid,itemid,triggerid,function,parameter)"
						" values (" ZBX_FS_UI64 "," ZBX_FS_UI64 "," ZBX_FS_UI64 ",'%s','%s')",
								new_functionid,
								itemid,
								new_triggerid,
								function_esc,
								parameter_esc
							);

					old_expression = new_expression;
					new_expression = string_replace(old_expression, search, replace);

					zbx_free(old_expression);
					zbx_free(replace);
				}
				else
				{
					zabbix_log(LOG_LEVEL_DEBUG, "Missing similar key [" ZBX_FS_UI64 "] for host [" ZBX_FS_UI64 "]",
							itemid, hostid);
					result = FAIL;
				}

				DBfree_result(db_items);

				zbx_free(search);

				zbx_free(parameter_esc);
				zbx_free(function_esc);

			}

			DBfree_result(db_functions);

			if( SUCCEED == result )
			{
				new_expression_esc = DBdyn_escape_string_len(new_expression, TRIGGER_EXPRESSION_LEN);
				DBexecute("update triggers set expression='%s' where triggerid=" ZBX_FS_UI64, new_expression_esc, new_triggerid);
				zbx_free(new_expression_esc);

				/* copy dependencies */
				DBdelete_dependencies_by_triggerid(new_triggerid);
				for( i=0; 0 < new_dependences[i]; i++ )
				{
					DBinsert_dependency(new_triggerid, new_dependences[i]);
				}

				zabbix_log(LOG_LEVEL_DEBUG, "Added trigger '%s' to host [" ZBX_FS_UI64 "]", trigger_data[0], hostid);

				/* Copy triggers to the child hosts */
				db_chd_hosts = DBselect("select hostid from hosts_templates where templateid=" ZBX_FS_UI64, hostid);

				while( (chd_host_data = DBfetch(db_chd_hosts)) )
				{
					ZBX_STR2UINT64(chd_hostid, chd_host_data[0]);

					/* recursion */
					if( SUCCEED != (result = DBcopy_trigger_to_host(new_triggerid, chd_hostid)) )
						break;
				}

				DBfree_result(db_chd_hosts);
			}
			zbx_free(new_expression);

		}
	}

	DBfree_result(db_triggers);

	return result;
}

/******************************************************************************
 *                                                                            *
 * Function: DBupdate_template_dependencies_for_host                          *
 *                                                                            *
 * Purpose: update trigger dependencies for specified host                    *
 *                                                                            *
 * Parameters: hostid - host identificator from database                      *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
static int DBupdate_template_dependencies_for_host(
		zbx_uint64_t hostid
		)
{
	DB_RESULT	db_triggers;
	DB_RESULT	db_dependencies;

	DB_ROW		trigger_data;
	DB_ROW		dependency_data;

	int		alloc = 16, count = 0, i,
			flag_down,
			flag_up;

	zbx_uint64_t	*tpl_triggerids = NULL,
			*hst_triggerids = NULL,
			templateid,
			triggerid,
			templateid_up,
			templateid_down;

	tpl_triggerids = zbx_malloc(tpl_triggerids, alloc * sizeof(zbx_uint64_t));
	hst_triggerids = zbx_malloc(hst_triggerids, alloc * sizeof(zbx_uint64_t));

	db_triggers = DBselect("select distinct t.triggerid, t.templateid "
							"from triggers t, functions f, items i"
							" where i.hostid=" ZBX_FS_UI64
								" and f.itemid=i.itemid "
								" and f.triggerid=t.triggerid"
								" and t.templateid > 0", hostid);

	while( (trigger_data = DBfetch(db_triggers)) )
	{
		ZBX_STR2UINT64(templateid, trigger_data[1]);
		ZBX_STR2UINT64(triggerid, trigger_data[0]);
		DBdelete_dependencies_by_triggerid(triggerid);

		if (alloc == count)
		{
			alloc += 16;
			tpl_triggerids = zbx_realloc(tpl_triggerids, alloc * sizeof(zbx_uint64_t));
			hst_triggerids = zbx_realloc(hst_triggerids, alloc * sizeof(zbx_uint64_t));
		}

		tpl_triggerids[count] = templateid;
		hst_triggerids[count] = triggerid;
		count++;
	}

	DBfree_result(db_triggers);

	db_dependencies = DBselect("SELECT DISTINCT td.triggerdepid, td.triggerid_down, td.triggerid_up "
			"FROM items i, functions f, triggers t, trigger_depends td "
			" WHERE i.hostid=" ZBX_FS_UI64
				" AND f.itemid=i.itemid "
				" AND t.triggerid=f.triggerid "
				" AND ( (td.triggerid_up=t.templateid) OR (td.triggerid_down=t.templateid) )", hostid);

	while( (dependency_data = DBfetch(db_dependencies)) )
	{
		flag_down=0;
		flag_up=0;

		ZBX_STR2UINT64(templateid_down, dependency_data[1]);
		ZBX_STR2UINT64(templateid_up, dependency_data[2]);

		for (i = 0; i < count; i++)
		{
			if (tpl_triggerids[i] == templateid_down)
				flag_down = i;
			if (tpl_triggerids[i] == templateid_up)
				flag_up = i;

			if (flag_down && flag_up)
				break;
		}

		if (flag_down && flag_up)
		{
			DBinsert_dependency(hst_triggerids[flag_down], hst_triggerids[flag_up]);
		}
	}

	DBfree_result(db_dependencies);

	zbx_free(tpl_triggerids);
	zbx_free(hst_triggerids);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: DBcopy_graph_to_host                                             *
 *                                                                            *
 * Purpose: copy specified graph to host                                      *
 *                                                                            *
 * Parameters: graphid - graph identificator from database                    *
 *             hostid - host identificator from database                      *
 *             copy_mode - 1 - only copy elements without linkage             *
 *                         0 - copy and link elements                         *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
static int	DBcopy_graph_to_host(zbx_uint64_t graphid, zbx_uint64_t hostid)
{
	DB_RESULT	result;
	DB_ROW		row;
	DB_RESULT	chd_graphs;
	DB_ROW		chd_graph_data;
	DB_RESULT	chd_items;
	DB_ROW		chd_item_row;
	ZBX_GRAPH_ITEMS chd_gitem;
	ZBX_GRAPH_ITEMS *gitem, *gitems = NULL;
	int		gitems_alloc = 0, gitems_num = 0;
	zbx_uint64_t	chd_graphid, ymin_itemid, ymax_itemid;
	int		i, equal, res = FAIL;

	result = DBselect(
			"select dst.itemid,gi.drawtype,gi.sortorder,gi.color,gi.yaxisside,gi.calc_fnc,gi.type,gi.periods_cnt"
			" from graphs_items gi,items i,items dst"
			" where gi.itemid=i.itemid"
				" and i.key_=dst.key_"
				" and dst.hostid=" ZBX_FS_UI64
				" and gi.graphid=" ZBX_FS_UI64,
			hostid,
			graphid);

	while (NULL != (row = DBfetch(result)))
	{
		if (gitems_alloc == gitems_num)
		{
			gitems_alloc += 16;
			gitems = zbx_realloc(gitems, gitems_alloc * sizeof(ZBX_GRAPH_ITEMS));
		}

		gitem = &gitems[gitems_num++];

		ZBX_STR2UINT64(gitem->itemid, row[0]);
		gitem->drawtype = atoi(row[1]);
		gitem->sortorder = atoi(row[2]);
		zbx_strlcpy(gitem->color, row[3], sizeof(gitem->color));
		gitem->yaxisside = atoi(row[4]);
		gitem->calc_fnc = atoi(row[5]);
		gitem->type = atoi(row[6]);
		gitem->periods_cnt = atoi(row[7]);
	}
	DBfree_result(result);

	if (NULL == gitems)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Skipped copying of graph to host " ZBX_FS_UI64
				": no graph items", hostid);
		return res;
	}

	result = DBselect(
			"select name,width,height,yaxismin,yaxismax,show_work_period,"
				"show_triggers,graphtype,show_legend,show_3d,percent_left,"
				"percent_right,ymin_type,ymax_type,ymin_itemid,ymax_itemid"
			" from graphs"
			" where graphid=" ZBX_FS_UI64,
			graphid);

	if (NULL != (row = DBfetch(result)))
	{
		chd_graphs = DBselect(
				"select distinct g.graphid"
				" from graphs g,graphs_items gi,items i "
				" where g.graphid=gi.graphid"
					" and gi.itemid=i.itemid"
					" and i.hostid=" ZBX_FS_UI64
					" and g.templateid=0",
				hostid);

		chd_graphid = 0;
		/* compare graphs */
		while (NULL != (chd_graph_data = DBfetch(chd_graphs)))
		{
			ZBX_STR2UINT64(chd_graphid, chd_graph_data[0]);

			chd_items = DBselect(
					"select itemid,drawtype,sortorder,color,yaxisside,calc_fnc,type,periods_cnt "
					" from graphs_items"
					" where graphid=" ZBX_FS_UI64,
					graphid);

			equal = 0;
			while (NULL != (chd_item_row = DBfetch(chd_items)))
			{
				ZBX_STR2UINT64(chd_gitem.itemid, chd_item_row[0]);
				chd_gitem.drawtype	= atoi(chd_item_row[1]);
				chd_gitem.sortorder	= atoi(chd_item_row[2]);
				zbx_strlcpy(chd_gitem.color, chd_item_row[3], sizeof(chd_gitem.color));
				chd_gitem.yaxisside	= atoi(chd_item_row[4]);
				chd_gitem.calc_fnc	= atoi(chd_item_row[5]);
				chd_gitem.type		= atoi(chd_item_row[6]);
				chd_gitem.periods_cnt	= atoi(chd_item_row[7]);

				for (i = 0; i < gitems_num; i++)
					if (0 == DBcmp_graphitems(&gitems[i], &chd_gitem))
					{
						equal++;
						break;
					}

				if (i == gitems_num)
				{
					equal = 0;
					break;
				}
			}
			DBfree_result(chd_items);

			if (equal && gitems_num == equal)
				break;	/* found equal graph */

			chd_graphid = 0;
		}
		DBfree_result(chd_graphs);

		ZBX_STR2UINT64(ymin_itemid, row[14]);
		ZBX_STR2UINT64(ymax_itemid, row[15]);

		if (0 != chd_graphid)
		{
			res = DBupdate_graph_with_items(
				chd_graphid,
				row[0],		/* name */
				atoi(row[1]),	/* width */
				atoi(row[2]),	/* height */
				atoi(row[3]),	/* yaxismin */
				atoi(row[4]),	/* yaxismax */
				atoi(row[5]),	/* show_work_period */
				atoi(row[6]),	/* show_triggers */
				atoi(row[7]),	/* graphtype */
				atoi(row[8]),	/* show_legend */
				atoi(row[9]),	/* show_3d */
				atof(row[10]),	/* percent_left */
				atof(row[11]),	/* percent_right */
				atoi(row[12]),	/* ymin_type */
				atoi(row[13]),	/* ymax_type */
				ymin_itemid,	/* ymin_itemid */
				ymax_itemid,	/* ymax_itemid */
				gitems,
				gitems_num,
				graphid);
		}
		else
		{
			res = DBadd_graph_with_items(
				&chd_graphid,
				row[0],		/* name */
				atoi(row[1]),	/* width */
				atoi(row[2]),	/* height */
				atoi(row[3]),	/* yaxismin */
				atoi(row[4]),	/* yaxismax */
				atoi(row[5]),	/* show_work_period */
				atoi(row[6]),	/* show_triggers */
				atoi(row[7]),	/* graphtype */
				atoi(row[8]),	/* show_legend */
				atoi(row[9]),	/* show_3d */
				atof(row[10]),	/* percent_left */
				atof(row[11]),	/* percent_right */
				atoi(row[12]),	/* ymin_type */
				atoi(row[13]),	/* ymax_type */
				ymin_itemid,	/* ymin_itemid */
				ymax_itemid,	/* ymax_itemid */
				gitems,
				gitems_num,
				graphid);
		}
	}
	else
		zabbix_log(LOG_LEVEL_DEBUG, "Skipped copying of graph to host " ZBX_FS_UI64
				": no graph " ZBX_FS_UI64, hostid, graphid);

	DBfree_result(result);

	zbx_free(gitems);

	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: DBdelete_template_elements                                       *
 *                                                                            *
 * Purpose: delete template elements from host                                *
 *                                                                            *
 * Parameters: hostid - host identificator from database                      *
 *             templateid - template identificator from database              *
 *             unlink_mode - 1 - only unlink elements without deletion        *
 *                           0 - delete elements                              *
 * Return value:                                                              *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
int	DBdelete_template_elements(zbx_uint64_t hostid, zbx_uint64_t templateid,
		unsigned char unlink_mode)
{
	DBdelete_template_graphs(hostid, templateid, unlink_mode);
	DBdelete_template_triggers(hostid, templateid, unlink_mode);
	DBdelete_template_items(hostid, templateid, unlink_mode);
	DBdelete_template_applications(hostid, templateid, unlink_mode);

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
 *             copy_mode - 1 - only copy elements without linkage             *
 *                         0 - copy and link elements                         *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
static int	DBcopy_template_applications(zbx_uint64_t hostid, zbx_uint64_t templateid)
{
	const char	*__function_name = "DBcopy_template_applications";
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	template_applicationid, applicationid;
	char		*name_esc, *host = NULL;
	char		*sql = NULL;
	int		sql_offset = 0, sql_alloc = 1024,
			res = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	result = DBselect(
			"select host"
			" from hosts"
			" where hostid=" ZBX_FS_UI64,
			hostid);

	if (NULL != (row = DBfetch(result)))
		host = strdup(row[0]);
	DBfree_result(result);

	if (NULL == host)
	{
		zabbix_log(LOG_LEVEL_WARNING, "Can not link applications:"
				" host [" ZBX_FS_UI64 "] not found",
				hostid);
		return FAIL;
	}

	result = DBselect(
			"select ta.applicationid,ta.name,ha.applicationid"
			" from applications ta"
			" left join applications ha"
				" on ha.name=ta.name"
					" and ha.hostid=" ZBX_FS_UI64
			" where ta.hostid=" ZBX_FS_UI64,
			hostid,
			templateid);

	sql = zbx_malloc(sql, sql_alloc);

#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 8, "begin\n");
#endif

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(template_applicationid, row[0]);

		name_esc = DBdyn_escape_string(row[1]);

		if (SUCCEED != (DBis_null(row[2])))
		{
			ZBX_STR2UINT64(applicationid, row[2]);

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 128,
					"update applications"
					" set templateid=" ZBX_FS_UI64
					" where applicationid=" ZBX_FS_UI64 ";\n",
					template_applicationid,
					applicationid);

			zabbix_log(LOG_LEVEL_DEBUG, "Updated application [" ZBX_FS_UI64 "] '%s:%s'",
					applicationid, host, row[1]);
		}
		else
		{
			applicationid = DBget_maxid("applications", "applicationid");

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 512,
					"insert into applications"
						" (applicationid,hostid,name,templateid)"
					" values"
						" (" ZBX_FS_UI64 "," ZBX_FS_UI64 ",'%s'," ZBX_FS_UI64 ");\n",
					applicationid, hostid, name_esc, template_applicationid);

			zabbix_log(LOG_LEVEL_DEBUG, "Added new application ["ZBX_FS_UI64"] '%s:%s'",
					applicationid, host, row[1]);
		}

		DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset);

		zbx_free(name_esc);
	}
	DBfree_result(result);

#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 8, "end;\n");
#endif

	if (sql_offset > 16)	/* In ORACLE always present begin..end; */
		DBexecute("%s", sql);

	zbx_free(sql);
	zbx_free(host);

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
 *             copy_mode - 1 - only copy elements without linkage             *
 *                         0 - copy and link elements                         *
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
	char		*host = NULL;
	zbx_uint64_t	tmp_apps[ZBX_MAX_APPLICATIONS], apps[ZBX_MAX_APPLICATIONS];
	char		*description_esc, *key_esc, *delay_flex_esc, *trapper_hosts_esc,
			*units_esc, *formula_esc, *logtimefmt_esc, *params_esc,
			*ipmi_sensor_esc, *snmp_community_esc, *snmp_oid_esc,
			*snmpv3_securityname_esc, *snmpv3_authpassphrase_esc,
			*snmpv3_privpassphrase_esc, *username_esc, *password_esc,
			*publickey_esc, *privatekey_esc;
	char		*sql = NULL;
	int		sql_offset = 0, sql_alloc = 16384,
			i, res = SUCCEED;


	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	result = DBselect(
			"select host"
			" from hosts"
			" where hostid=" ZBX_FS_UI64,
			hostid);

	if (NULL != (row = DBfetch(result)))
		host = strdup(row[0]);
	DBfree_result(result);

	if (NULL == host)
	{
		zabbix_log(LOG_LEVEL_WARNING, "Can not link items:"
				" host [" ZBX_FS_UI64 "] not found",
				hostid);
		return FAIL;
	}

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
			" left join items hi"
				" on hi.key_=ti.key_"
					" and hi.hostid=" ZBX_FS_UI64
			" where ti.hostid=" ZBX_FS_UI64,
			hostid,
			templateid);

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
						"hostid=" ZBX_FS_UI64 ","
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
					template_itemid,
					itemid);

			zabbix_log(LOG_LEVEL_DEBUG, "Updated item ["ZBX_FS_UI64"] '%s:%s'",
					itemid, host, row[2]);
		}
		else
		{
			itemid = DBget_maxid("items","itemid");

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

			zabbix_log(LOG_LEVEL_DEBUG, "Added new item ["ZBX_FS_UI64"] '%s:%s'",
					itemid, host, row[2]);
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

		DBget_applications_by_itemid(template_itemid, tmp_apps, sizeof(tmp_apps) / sizeof(zbx_uint64_t));
		DBget_same_applications_for_host(tmp_apps, hostid, apps, sizeof(apps) / sizeof(zbx_uint64_t));

		for (i = 0; 0 < apps[i]; i++)
		{
			itemappid = DBget_maxid("items_applications", "itemappid");

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 160,
					"insert into items_applications"
						" (itemappid,itemid,applicationid) "
					" values"
						" (" ZBX_FS_UI64 "," ZBX_FS_UI64 "," ZBX_FS_UI64 ");\n",
					itemappid, itemid, apps[i]);

			DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
		}
	}
	DBfree_result(result);

#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 8, "end;\n");
#endif

	if (sql_offset > 16)	/* In ORACLE always present begin..end; */
		DBexecute("%s", sql);

	zbx_free(sql);
	zbx_free(host);

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
 *             templateid - template identificator from database              *
 *             copy_mode - 1 - only copy elements without linkage             *
 *                         0 - copy and link elements                         *
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
	zbx_uint64_t	triggerid;
	int		res = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	result = DBselect(
			"select distinct t.triggerid"
			" from triggers t,functions f,items i"
			" where i.hostid=" ZBX_FS_UI64
				" and f.itemid=i.itemid"
				" and f.triggerid=t.triggerid",
			templateid);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(triggerid, row[0]);

		if (SUCCEED != (res = DBcopy_trigger_to_host(triggerid, hostid)))
			break;
	}
	DBfree_result(result);

	if (SUCCEED == res)
		res = DBupdate_template_dependencies_for_host(hostid);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(res));

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
 *             copy_mode - 1 - only copy elements without linkage             *
 *                         0 - copy and link elements                         *
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
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	graphid;
	int		res = SUCCEED;

	result = DBselect(
			"select distinct g.graphid"
			" from graphs g,graphs_items gi,items i"
			" where g.graphid=gi.graphid"
				" and gi.itemid=i.itemid"
				" and i.hostid=" ZBX_FS_UI64,
			templateid);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(graphid, row[0]);

		if (SUCCEED != (res = DBcopy_graph_to_host(graphid, hostid)))
			break;
	}
	DBfree_result(result);

	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: get_templates_by_hostid                                          *
 *                                                                            *
 * Description: Retrive already linked templates for specified host           *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
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
			res;
	char		error[MAX_STRING_LEN];

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	get_templates_by_hostid(hostid, &templateids, &templateids_alloc,
			&templateids_num);

	uint64_array_add(&templateids, &templateids_alloc, &templateids_num,
			templateid, 1);

	if (SUCCEED == (res = validate_template(templateids, templateids_num,
			error, sizeof(error))))
	{
		hosttemplateid = DBget_maxid("hosts_templates", "hosttemplateid");

		DBexecute(
				"insert into hosts_templates"
					" (hosttemplateid, hostid, templateid)"
				" values"
					" (" ZBX_FS_UI64 "," ZBX_FS_UI64 "," ZBX_FS_UI64 ")",
				hosttemplateid,
				hostid,
				templateid);

		if (SUCCEED == (res = DBcopy_template_applications(hostid, templateid)))
			if (SUCCEED == (res = DBcopy_template_items(hostid, templateid)))
				if (SUCCEED == (res = DBcopy_template_triggers(hostid, templateid)))
					res = DBcopy_template_graphs(hostid, templateid);
	}
	else
		zabbix_log(LOG_LEVEL_WARNING, "Can not link template '%s': %s",
			zbx_host_string(templateid), error);

	zbx_free(templateids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(res));

	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: DBunlink_template                                                *
 *                                                                            *
 * Purpose: unlink template from host without element deletion                *
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
/* public */ void	DBunlink_template(
		zbx_uint64_t	hostid,
		zbx_uint64_t	templateid
	)
{
	DBdelete_template_elements(hostid, templateid, 1 /* unlink, not delete */);

	DBexecute("delete from hosts_templates where hostid=" ZBX_FS_UI64 " and templateid=" ZBX_FS_UI64,
			hostid, templateid);
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
/* public */ int	DBdelete_host(
		zbx_uint64_t hostid
	)
{
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	elementid;

	/* unlink child hosts */
	result = DBselect("select hostid from hosts_templates where templateid=" ZBX_FS_UI64, hostid);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(elementid, row[0]);
		DBunlink_template(elementid, hostid);
	}
	DBfree_result(result);

	/* unlink templates */
	result = DBselect("select templateid from hosts_templates where hostid=" ZBX_FS_UI64, hostid);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(elementid, row[0]);
		DBdelete_template_elements(hostid, elementid, 0 /* delete elements */);
	}
	DBfree_result(result);

	/* delete items -> triggers -> graphs */
	result = DBselect("select itemid from items where hostid=" ZBX_FS_UI64, hostid);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(elementid, row[0]);
		DBdelete_item(elementid);
	}
	DBfree_result(result);

	/* delete host from maps */
	DBdelete_sysmaps_elements(SYSMAP_ELEMENT_TYPE_HOST, hostid);

	/* delete host from group */
	DBexecute("delete from hosts_groups where hostid=" ZBX_FS_UI64, hostid);

	/* delete host from template linkages */
	DBexecute("delete from hosts_templates where hostid=" ZBX_FS_UI64, hostid);

	/* delete action conditions */
	DBdelete_action_conditions(CONDITION_TYPE_HOST, hostid);

	/* delete host profile */
	DBexecute("delete from hosts_profiles where hostid=" ZBX_FS_UI64, hostid);
	DBexecute("delete from hosts_profiles_ext where hostid=" ZBX_FS_UI64, hostid);

	/* delete host */
	DBexecute("delete from hosts where hostid=" ZBX_FS_UI64, hostid);

	return SUCCEED;
}
