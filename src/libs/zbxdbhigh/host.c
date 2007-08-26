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

#define ZBX_MAX_APPLICATIONS 64
#define ZBX_MAX_DEPENDENCES	128


#define	DBadd_application(name, hostid, templateid)	DBdb_save_application(name, hostid, 0, templateid)
#define DBupdate_application(applicationid, name, hostid, templateid)	DBdb_save_application(name, hostid, applicationid, templateid)

/******************************************************************************
 *                                                                            *
 * Function: DBget_applications_by_itemid                                     *
 *                                                                            *
 * Purpose: retrive applications by itemid                                    *
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
 * Purpose: retrive same applications for specified host                      *
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
 * Purpose: create dependences for triggers                                   *
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
 * Purpose: delete dependences from triggers                                  *
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
	}
}

/******************************************************************************
 *                                                                            *
 * Function: DBget_service_status                                             *
 *                                                                            *
 * Purpose: retrive true status                                               *
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

	if( 0 != triggerid )
	{
		result = DBselect("select priority from triggers where trigerid=" ZBX_FS_UI64 " and status=0 and value=%d", triggerid, TRIGGER_VALUE_TRUE);
		if( (row = DBfetch(result)) )
		{
			status = atoi(row[0]);
		}
		DBfree_result(result);
	}

	if((SERVICE_ALGORITHM_MAX == algorithm) || (SERVICE_ALGORITHM_MIN == algorithm))
	{
		if(SERVICE_ALGORITHM_MAX == algorithm)
		{
			result = DBselect("select count(*),max(status) from services s,services_links l "
					"where l.serviceupid=" ZBX_FS_UI64 " and s.serviceid=l.servicedownid",
					serviceid);
		}
		/* MIN otherwise */
		else
		{
			result = DBselect("select count(*),min(status) from services s,services_links l "
					"where l.serviceupid=" ZBX_FS_UI64 " and s.serviceid=l.servicedownid",
					serviceid);
		}
		row=DBfetch(result);
		if(row && DBis_null(row[0]) != SUCCEED && DBis_null(row[1]) != SUCCEED)
		{
			if(atoi(row[0])!=0)
			{
				status = atoi(row[1]);
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
void	DBupdate_services_rec(
		zbx_uint64_t serviceid
	)
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
		else if((SERVICE_ALGORITHM_MAX == algorithm)
			||
			(SERVICE_ALGORITHM_MIN == algorithm))
		{
			status = DBget_service_status(serviceupid, algorithm, 0);

			DBadd_service_alarm(serviceupid,status,time(NULL));
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
		DBupdate_services_rec(serviceupid);
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

	int	status = 0;

	DBclear_parents_from_trigger(0);

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

		DBadd_service_alarm(serviceid, status, time(NULL));
	}
	DBfree_result(result);

	result = DBselect("SELECT MAX(sl.servicedownid) as serviceid, sl.serviceupid "
			" FROM services_links AS sl "
			" WHERE sl.servicedownid NOT IN (select distinct sl.serviceupid from services_links as sl) "
			" GROUP BY sl.serviceupid");

	while( (rows = DBfetch(result)) )
	{
		ZBX_STR2UINT64(serviceid, rows[0]);
		DBupdate_services_rec(serviceid);
	}
	DBfree_result(result);
}

/******************************************************************************
 *                                                                            *
 * Function: DBupdate_services                                                *
 *                                                                            *
 * Purpose: re-calculate and updates status of the service and its childs     *
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
void	DBupdate_services(
		zbx_uint64_t triggerid,
		int status
	)
{
	DB_ROW	row;
	zbx_uint64_t	serviceid;

	DB_RESULT result;

	DBexecute("update services set status=%d where triggerid=" ZBX_FS_UI64,
		status,
		triggerid);

	result = DBselect("select serviceid,algorithm from services where triggerid=" ZBX_FS_UI64,
		triggerid);

	while((row=DBfetch(result)))
	{
		ZBX_STR2UINT64(serviceid,row[0]);

		DBadd_service_alarm(
			serviceid,
			DBget_service_status(
				serviceid,
				atoi(row[1]),
				0),
			time(NULL)
			);

		DBupdate_services_rec(serviceid);
	}

	DBfree_result(result);
	return;
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
	DBexecute("DELETE FROM service_alarms WHERE serviceid=" ZBX_FS_UI64, serviceid);
	DBexecute("delete from services_links where servicedownid=" ZBX_FS_UI64 " or serviceupid=" ZBX_FS_UI64, serviceid, serviceid);
	DBexecute("delete from services where serviceid=" ZBX_FS_UI64, serviceid);

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
 * Function: DBdelete_sysmaps_element                                         *
 *                                                                            *
 * Purpose: delete specified map element                                      *
 *                                                                            *
 * Parameters: selementid - map element identificator from database           *
 *                                                                            *
 * Return value: always SUCCEED                                               *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
static int	DBdelete_sysmaps_element(
		zbx_uint64_t	selementid
	)
{
	DBexecute("delete from sysmaps_links"
		" where selementid1=" ZBX_FS_UI64 " or selementid2=" ZBX_FS_UI64,
		selementid, selementid);

	DBexecute("delete from sysmaps_elements where selementid=" ZBX_FS_UI64, selementid);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: DBdelete_sysmaps_elements_with_triggerid                         *
 *                                                                            *
 * Purpose: delete triggers from map                                          *
 *                                                                            *
 * Parameters: triggerid - trigger dientificator from database                *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
static int	DBdelete_sysmaps_elements_with_triggerid(
		zbx_uint64_t triggerid
	)
{
	DB_RESULT	db_selements;

	DB_ROW		selement_data;

	zbx_uint64_t
		selementid;

	int	result = SUCCEED;

	db_selements = DBselect("select distinct selementid from sysmaps_elements"
		" where elementid=" ZBX_FS_UI64 " and elementtype=%i", triggerid, SYSMAP_ELEMENT_TYPE_TRIGGER);

	while( (selement_data = DBfetch(db_selements)) )
	{
		ZBX_STR2UINT64(selementid, selement_data[0]);
		if( SUCCEED != (result = DBdelete_sysmaps_element(selementid)) )
			break;
	}

	DBfree_result(db_selements);

	return result;
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
		if( SUCCEED == (result = DBdelete_sysmaps_elements_with_triggerid(triggerid)) )
		{
			DBexecute("delete from trigger_depends where triggerid_up=" ZBX_FS_UI64, triggerid);
			DBexecute("delete from functions where triggerid=" ZBX_FS_UI64, triggerid);
			DBexecute("delete from events where objectid=" ZBX_FS_UI64 " and object=%i", triggerid, EVENT_OBJECT_TRIGGER);
			DBexecute("delete from alerts where triggerid=" ZBX_FS_UI64, triggerid);

			DBexecute("update sysmaps_links set triggerid=NULL where triggerid=" ZBX_FS_UI64, triggerid);

			/* disable actions */
			db_elements = DBselect("select distinct actionid from conditions "
				" where conditiontype=%i and value=" ZBX_FS_UI64, CONDITION_TYPE_TRIGGER, triggerid);
			while( (element_data = DBfetch(db_elements)) )
			{
				ZBX_STR2UINT64(elementid, element_data[0]);
				DBexecute("update actions set status=%i where actionid=" ZBX_FS_UI64, ACTION_STATUS_DISABLED, elementid);
			}

			DBfree_result(db_elements);

			/* delete action conditions */
			DBexecute("delete from conditions where conditiontype=%i and value=" ZBX_FS_UI64, CONDITION_TYPE_TRIGGER, triggerid);
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
	DB_RESULT	db_triggers;

	DB_ROW		trigger_data;

	zbx_uint64_t
		triggerid;

	int	result = SUCCEED;

	db_triggers = DBselect("select triggerid from functions where itemid=" ZBX_FS_UI64, itemid);

	while( (trigger_data = DBfetch(db_triggers)) )
	{
		ZBX_STR2UINT64(triggerid, trigger_data[0]);

		if( SUCCEED != ( result = DBdelete_trigger(triggerid)) )
			break;
	}

	DBfree_result(db_triggers);

	return result;
}

/******************************************************************************
 *                                                                            *
 * Function: DBdelete_trends_by_itemid                                        *
 *                                                                            *
 * Purpose: delete item trends                                                *
 *                                                                            *
 * Parameters: itemid - item identificator from database                      *
 *             use_housekeeper - 0 - to delete imidietly                      *
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
 *             use_housekeeper - 0 - to delete imidietly                      *
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
	char		*color;
	int		drawtype;
	int		sortorder;
	int		yaxisside;
	int		calc_fnc;
	int		type;
	int		periods_cnt;
} ZBX_GRAPH_ITEMS;

/******************************************************************************
 *                                                                            *
 * Function: zbx_free_gitems                                                  *
 *                                                                            *
 * Purpose: free allocated memory by DBget_same_graphitems_for_host           *
 *                                                                            *
 * Parameters: gitems - zero terminated array of graph items                  *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 ******************************************************************************/
#define zbx_free_gitems(gitems) __zbx_free_gitems(&gitems)

static void	__zbx_free_gitems(
		ZBX_GRAPH_ITEMS **gitems
	)
{
	int i = 0;

	if ( !*gitems )	return;

	for ( i=0; (*gitems)[i].itemid != 0; i++ )
		zbx_free((*gitems)[i].color);

	zbx_free(*gitems)
}

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
static int	DBcmp_graphitems(
		ZBX_GRAPH_ITEMS *gitem1,
		ZBX_GRAPH_ITEMS	*gitem2
	)
{
	DB_RESULT	db_items;
	DB_ROW		db_item_data;

	if(gitem1->drawtype	!= gitem2->drawtype)	return 1;
	if(gitem1->sortorder	!= gitem2->sortorder)	return 2;
	if(strcmp(gitem1->color, gitem2->color))	return 3;
	if(gitem1->yaxisside	!= gitem2->yaxisside)	return 4;

	db_items = DBselect("select distinct i2.itemid from items i1, items i2 "
			" where i1.itemid=" ZBX_FS_UI64 " and i2.itemid=" ZBX_FS_UI64
			" and i1.key_=i2.key_ ", gitem1->itemid, gitem2->itemid);

	db_item_data = DBfetch(db_items);

	DBfree_result(db_items);
	if ( !db_item_data )				return 5;
	return 0;
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
static ZBX_GRAPH_ITEMS* DBget_same_graphitems_for_host(
		ZBX_GRAPH_ITEMS	*gitems,
		zbx_uint64_t	dest_hostid
	)
{
	DB_RESULT db_items;
	DB_ROW db_item_data;

	ZBX_GRAPH_ITEMS *new_gitems = NULL;

	int i = 0,
	    new_gi = 0;

	new_gitems = zbx_malloc(new_gitems, sizeof(ZBX_GRAPH_ITEMS));
	new_gitems[0].itemid = 0;

	for ( i=0; gitems[i].itemid != 0; i++ )
	{
		db_items = DBselect("select src.itemid from items src, items dest "
				" where dest.itemid=" ZBX_FS_UI64
				" and src.key_=dest.key_ and src.hostid=" ZBX_FS_UI64,
				gitems[i].itemid, dest_hostid);

		if ( (db_item_data = DBfetch(db_items)) )
		{
			ZBX_STR2UINT64(new_gitems[new_gi].itemid, db_item_data[0]);

			new_gitems[new_gi].color	= strdup(gitems[i].color);
			new_gitems[new_gi].drawtype	= gitems[i].drawtype;
			new_gitems[new_gi].sortorder	= gitems[i].sortorder;
			new_gitems[new_gi].yaxisside	= gitems[i].yaxisside;
			new_gitems[new_gi].calc_fnc	= gitems[i].calc_fnc;
			new_gitems[new_gi].type		= gitems[i].type;
			new_gitems[new_gi].periods_cnt	= gitems[i].periods_cnt;

			new_gitems = zbx_realloc(new_gitems, (new_gi+2)*sizeof(ZBX_GRAPH_ITEMS));
			new_gitems[new_gi+1].itemid = 0;

			new_gi++;
		}

		DBfree_result(db_items);

		if ( !db_item_data )
		{
			zbx_free_gitems(new_gitems);
			break;
		}

	}

	return new_gitems;
}

/******************************************************************************
 *                                                                            *
 * Function: DBadd_item_to_graph                                              *
 *                                                                            *
 * Purpose: add item to the graph                                             *
 *                                                                            *
 * Parameters: graphid - graph identificator from database                    *
 *             itemid - item identificator from database                      *
 *             color - character representation of HEX color 'RRGGBB'         *
 *             drawtype - type of line                                        *
 *             sortorder - sort order                                         *
 *             yaxisside - 0 - use x-axis                                     *
 *                         1 - use y-axis                                     *
 *             calc_fnc - type of calculation function                        *
 *             type - type item (simple, aggregated, ...)                     *
 *             periods_cnt - count of aggregated periods                      *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
static int	DBadd_item_to_graph(
		zbx_uint64_t	graphid,
		zbx_uint64_t	itemid,
		const char	*color,
		int		drawtype,
		int		sortorder,
		int		yaxisside,
		int		calc_fnc,
		int		type,
		int		periods_cnt
	)
{
	zbx_uint64_t
		gitemid;

	char	*color_esc = NULL;

	int	result = SUCCEED;

	gitemid = DBget_maxid("graphs_items","gitemid");

	color_esc = DBdyn_escape_string(color);

	DBexecute("insert into graphs_items"
		" (gitemid,graphid,itemid,color,drawtype,sortorder,yaxisside,calc_fnc,type,periods_cnt)"
		" values (" ZBX_FS_UI64 "," ZBX_FS_UI64 "," ZBX_FS_UI64 ",'%s',%i,%i,%i,%i,%i,%i)",
				gitemid,
				graphid,
				itemid,
				color_esc,
				drawtype,
				sortorder,
				yaxisside,
				calc_fnc,
				type,
				periods_cnt
			);

	zbx_free(color_esc);

	return result;
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
static int	DBdelete_graph(
		zbx_uint64_t graphid
	)
{
	DB_RESULT	db_graphs;
	DB_RESULT	db_elements;

	DB_ROW		graph_data;
	DB_ROW		element_data;

	int result = SUCCEED;

	db_graphs = DBselect("select name from graphs where graphid=" ZBX_FS_UI64, graphid);

	if( (graph_data = DBfetch(db_graphs)) )
	{
		/* first delete child graphs */
		db_elements = DBselect("select graphid from graphs where templateid=" ZBX_FS_UI64, graphid);

		while( (element_data = DBfetch(db_elements)) )
		{ /* recursion */
			ZBX_STR2UINT64(graphid, element_data[0]);
			if( SUCCEED != (result = DBdelete_graph(graphid)) )
				return result;
		}

		DBfree_result(db_elements);

		if( SUCCEED == result )
		{ /* delete graph */
			DBexecute("delete from screens_items where resourceid=" ZBX_FS_UI64 " and resourcetype=%i", graphid, SCREEN_RESOURCE_GRAPH);

			DBexecute("delete from graphs_items where graphid=" ZBX_FS_UI64, graphid);
			DBexecute("delete from graphs where graphid=" ZBX_FS_UI64, graphid);

			zabbix_log( LOG_LEVEL_DEBUG, "Graph '%s' deleted", graph_data[0]);
		}
	}

	DBfree_result(db_graphs);

	return result;
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
		char 		*name,
		int		width,
		int		height,
		int		yaxistype,
		int		yaxismin,
		int		yaxismax,
		int		show_work_period,
		int		show_triggers,
		int		graphtype,
		zbx_uint64_t	templateid
	)
{
	DB_RESULT db_graphs;
	DB_ROW db_graph_data;

	char	*name_esc = NULL;
	int	old_graphtype = 0;

	db_graphs = DBselect("select graphtype from graphs where graphid=" ZBX_FS_UI64, graphid);
	if ( (db_graph_data = DBfetch(db_graphs)) )
	{
		old_graphtype = atoi(db_graph_data[0]);
	}
	DBfree_result(db_graphs);

	name_esc = DBdyn_escape_string(name);

	DBexecute("update graphs set name='%s',width=%i,height=%i,"
		"yaxistype=%i,yaxismin=%i,yaxismax=%i,templateid=" ZBX_FS_UI64 ","
		"show_work_period=%i,show_triggers=%i,graphtype=%i"
		" where graphid=" ZBX_FS_UI64,
		name,width,height,yaxistype,yaxismin,yaxismax,templateid,show_work_period,show_triggers,graphtype,
		graphid);

	zbx_free(name_esc);

	if( old_graphtype != graphtype && graphtype == GRAPH_TYPE_STACKED)
	{
		DBexecute("update graphs_items set calc_fnc=%i,drawtype=1,type=%i "
			" where graphid=" ZBX_FS_UI64,
			CALC_FNC_AVG, GRAPH_ITEM_SIMPLE, graphid);
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
		char		*name,
		int		width,
		int		height,
		int		yaxistype,
		int		yaxismin,
		int		yaxismax,
		int		show_work_period,
		int		show_triggers,
		int		graphtype,
		ZBX_GRAPH_ITEMS	*gitems,
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

	zbx_uint64_t
		new_hostid = 0,
		chd_graphid = 0,
		chd_hostid = 0;

	char		*itemids = NULL;

	ZBX_GRAPH_ITEMS	*new_gitems = NULL;

	int	i = 0;
	int	result = SUCCEED;

	if ( !gitems )
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Missed items for graph '%s'", name);
		return FAIL;
	}

	/* check items for template graph */
	db_hosts = DBselect("select distinct h.hostid, h.status from hosts h, items i, graphs_items gi "
			" where h.hostid=i.hostid and i.itemid=gi.itemid and gi.graphid=" ZBX_FS_UI64, graphid);
	if ( (db_host_data = DBfetch(db_hosts)) )
	{
		ZBX_STR2UINT64(curr_hostid, db_host_data[0]);

		curr_host_status = atoi(db_host_data[1]);
	}
	DBfree_result(db_hosts);

	if ( curr_host_status == HOST_STATUS_TEMPLATE )
	{
		itemids = zbx_dsprintf(itemids, "0");
		for ( i = 0; gitems[i].itemid != 0; i++ )
		{
			itemids = zbx_strdcatf(itemids, "," ZBX_FS_UI64, gitems[i].itemid);
		}

		db_item_hosts = DBselect("select distinct hostid from items where itemid in (%s)", itemids);

		zbx_free(itemids);

		new_hostid = 0;

		while( (db_item_data = DBfetch(db_item_hosts)) )
		{
			if ( new_hostid )
			{
				zabbix_log(LOG_LEVEL_DEBUG, "Can not use multiple host items for template graph '%s'", name);
				result = FAIL;
				break;
			}

			ZBX_STR2UINT64(new_hostid, db_item_data[0]);
		}

		DBfree_result(db_item_hosts);

		if ( SUCCEED == result && curr_hostid != new_hostid )
		{
			zabbix_log(LOG_LEVEL_DEBUG, "You must use items only from host " ZBX_FS_UI64 " for template graph '%s'", curr_hostid, name);
			result = FAIL;
		}

		if ( SUCCEED != result )
		{
			return result;
		}
	}

	/* firstly update child graphs */
	chd_graphs = DBselect("select g.graphid from graphs g where g.templateid=" ZBX_FS_UI64, graphid);
	while ( (chd_graph_data = DBfetch(chd_graphs)) )
	{
		chd_hostid = 0;
		ZBX_STR2UINT64(chd_graphid, chd_graph_data[0]);

		db_hosts = DBselect("select distinct i.hostid from items i, graphs_items gi "
				" i.itemid=gi.itemid and gi.graphid=" ZBX_FS_UI64, chd_graphid);
		if ( (db_host_data = DBfetch(db_hosts)) )
		{
			ZBX_STR2UINT64(chd_hostid, db_host_data[0]);
		}
		DBfree_result(db_hosts);

		if ( ! (new_gitems = DBget_same_graphitems_for_host(gitems, chd_hostid)) )
		{ /* skip host with missed items */
			zabbix_log(LOG_LEVEL_DEBUG, "Can not update graph '%s' for host " ZBX_FS_UI64, name, chd_hostid);
			result = FAIL;
		}
		else
		{
			result = DBupdate_graph_with_items(chd_graphid, name, width, height,
				yaxistype, yaxismin, yaxismax,
				show_work_period, show_triggers, graphtype, new_gitems, graphid);

			zbx_free_gitems(new_gitems);
		}

		if ( SUCCEED != result )
		{
			return result;
		}
	}
	DBfree_result(chd_graphs);

	DBexecute("delete from graphs_items where graphid=" ZBX_FS_UI64, graphid);

	for ( i=0; gitems[i].itemid != 0; i++ )
	{
		if ( SUCCEED != (result = DBadd_item_to_graph(
				graphid,
				gitems[i].itemid,
				gitems[i].color,
				gitems[i].drawtype,
				gitems[i].sortorder,
				gitems[i].yaxisside,
				gitems[i].calc_fnc,
				gitems[i].type,
				gitems[i].periods_cnt)) )
		{
			return result;
		}
	}

	if ( SUCCEED == (result = DBupdate_graph(graphid,name,width,height,yaxistype,yaxismin,yaxismax,show_work_period,
					show_triggers,graphtype,templateid)) )
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Graph '%s' updated for hosts " ZBX_FS_UI64, name, curr_hostid);
	}

	return result;
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
		int		yaxistype,
		int		yaxismin,
		int		yaxismax,
		int		show_work_period,
		int		show_triggers,
		int		graphtype,
		zbx_uint64_t	templateid
	)
{
	zbx_uint64_t
		graphid;

	char	*name_esc = NULL;

	graphid = DBget_maxid("graphs","graphid");

	assert(name);

	name_esc = DBdyn_escape_string(name);

	DBexecute("insert into graphs"
		" (graphid,name,width,height,yaxistype,yaxismin,yaxismax,show_work_period,show_triggers,graphtype,templateid)"
		" values (" ZBX_FS_UI64 ",'%s',%i,%i,%i,%i,%i,%i,%i,%i," ZBX_FS_UI64 ")",
				graphid,
				name_esc,
				width,
				height,
				yaxistype,
				yaxismin,
				yaxismax,
				show_work_period,
				show_triggers,
				graphtype,
				templateid);
	zbx_free(name_esc);

	if( new_graphid )
		*new_graphid = graphid;

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
static int	DBcopy_graph_to_host(
		zbx_uint64_t	graphid,
		zbx_uint64_t	hostid,
		unsigned char copy_mode
	);
static int	DBadd_graph_with_items(
		zbx_uint64_t	*new_graphid,
		const char	*name,
		int		width,
		int		height,
		int		yaxistype,
		int		yaxismin,
		int		yaxismax,
		int		show_work_period,
		int		show_triggers,
		int		graphtype,
		ZBX_GRAPH_ITEMS *gitems,
		zbx_uint64_t	templateid
	)
{
	DB_RESULT	db_item_hosts;
	DB_ROW		db_item_host;

	DB_RESULT	db_chd_hosts;
	DB_ROW		chd_host_data;
	
	zbx_uint64_t	chd_hostid = 0;

	char *itemids = NULL;

	int 	i = 0,
		new_host_count = 0,
		new_host_is_template = 0;

	int result = FAIL;

	if ( !gitems )
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Missed items for graph '%s'", name);
		return result;
	}

	/* check items for template graph */
	itemids = zbx_dsprintf(itemids, "0");
	for ( i=0; gitems[i].itemid != 0; i++ )
		itemids = zbx_strdcatf(itemids, "," ZBX_FS_UI64, gitems[i].itemid);

	db_item_hosts = DBselect("select distinct h.hostid,h.host,h.status "
			" from items i, hosts h where h.hostid=i.hostid and i.itemid in (%s)", itemids);

	zbx_free(itemids);

	new_host_count = 0;
	new_host_is_template = 0;
	while( (db_item_host = DBfetch(db_item_hosts)) )
	{
		new_host_count++;
		if ( HOST_STATUS_TEMPLATE == atoi(db_item_host[2]) )
			new_host_is_template = 1;
	}
	DBfree_result(db_item_hosts);

	if ( new_host_is_template && new_host_count > 1 )
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Graph '%s' with template host can not contain items from other hosts.", name);
		return result;
	}


	if ( SUCCEED == (result = DBadd_graph(new_graphid, name,width,height,yaxistype,yaxismin,yaxismax,show_work_period,show_triggers,graphtype,templateid)) )
	{
		for ( i=0; gitems[i].itemid != 0; i++ )
		{
			if ( SUCCEED != (result = DBadd_item_to_graph(
				*new_graphid,
				gitems[i].itemid,
				gitems[i].color,
				gitems[i].drawtype,
				gitems[i].sortorder,
				gitems[i].yaxisside,
				gitems[i].calc_fnc,
				gitems[i].type,
				gitems[i].periods_cnt)) )
			{
				break;
			}
		}
	}

	if ( SUCCEED == result )
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Graph '%s' added", name);

		/* add graphs for child hosts */
		db_chd_hosts = DBselect("select distinct ht.hostid "
				" from hosts_templates ht, items i, graphs_items gi "
				" where ht.templateid=i.hostid and i.itemid=gi.itemid and gi.graphid=" ZBX_FS_UI64, *new_graphid);

		while( (chd_host_data = DBfetch(db_chd_hosts)) )
		{
			ZBX_STR2UINT64(chd_hostid, chd_host_data[0]);

			DBcopy_graph_to_host(*new_graphid, chd_hostid, 0);
		}

		DBfree_result(db_chd_hosts);
	}

	if ( SUCCEED != result && *new_graphid )
	{
		DBdelete_graph(*new_graphid);
		*new_graphid = 0;
	}

	return result;
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
	DB_RESULT	db_items;
	DB_RESULT	db_elements;

	DB_ROW		item_data;
	DB_ROW		element_data;

	zbx_uint64_t
		elementid;

	int result = SUCCEED;

	db_items = DBselect("select i.itemid ,i.key_,h.host from items i,"
				" hosts h where h.hostid=i.hostid and i.itemid=" ZBX_FS_UI64, itemid);

	if( (item_data = DBfetch(db_items)) )
	{
		/* first delete child items */
		db_elements = DBselect("select itemid from items where templateid=" ZBX_FS_UI64, itemid);

		while( (element_data = DBfetch(db_elements)) )
		{/* recursion */
			ZBX_STR2UINT64(elementid, element_data[0]);
			if( SUCCEED != (result = DBdelete_item(elementid)) )
				break;
		}
		
		DBfree_result(db_elements);

		if( SUCCEED == result)
		if( SUCCEED == (result = DBdelete_triggers_by_itemid(itemid)) )
		if( SUCCEED == (result = DBdelete_history_by_itemid(itemid, 1 /* use housekeeper */)) )
		{
			DBexecute("delete from screens_items where resourceid=" ZBX_FS_UI64 " and resourcetype in (%i,%i)",
					itemid, SCREEN_RESOURCE_PLAIN_TEXT, SCREEN_RESOURCE_SIMPLE_GRAPH);

			DBexecute("delete from graphs_items where itemid=" ZBX_FS_UI64, itemid);
			DBexecute("delete from items_applications where itemid=" ZBX_FS_UI64, itemid);
			DBexecute("delete from items where itemid=" ZBX_FS_UI64, itemid);

			zabbix_log( LOG_LEVEL_DEBUG, "Item '%s:%s' deleted", item_data[2], item_data[1]);
		}
	}

	DBfree_result(db_items);

	return result;
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
	DB_RESULT	db_graphs;
	DB_RESULT	db_tmp_hosts;

	DB_ROW		graph_data;
	DB_ROW		tmp_host_data;

	zbx_uint64_t
		g_templateid,
		tmp_hostid,
		graphid;

	db_graphs = DBselect("select distinct g.graphid,g.templateid,g.name from graphs g, graphs_items gi, items i"
                        " where g.templateid<> 0 and g.graphid=gi.graphid and gi.itemid=i.itemid and i.hostid=" ZBX_FS_UI64, hostid);

	while( (graph_data = DBfetch(db_graphs)) )
	{
		ZBX_STR2UINT64(g_templateid, graph_data[1]);
		
		if( 0 != templateid )
		{
			tmp_hostid = 0;

			db_tmp_hosts = DBselect("select distinct h.hostid from graphs_items gi, items i, hosts h"
						" where h.hostid=i.hostid and gi.itemid=i.itemid and gi.graphid=" ZBX_FS_UI64, g_templateid);

			if( (tmp_host_data = DBfetch(db_tmp_hosts)) )
			{
				ZBX_STR2UINT64(tmp_hostid, tmp_host_data[0]);
			}

			DBfree_result(db_tmp_hosts);

			if(tmp_hostid != templateid)
				continue;
		}

		ZBX_STR2UINT64(graphid, graph_data[0]);

		if( unlink_mode )
		{
			DBexecute("update graphs set templateid=0 where graphid=" ZBX_FS_UI64, graphid);

			zabbix_log( LOG_LEVEL_DEBUG, "Graph '%s' unlinked", graph_data[2]);
		}
		else
		{
			DBdelete_graph(graphid);
		}
	}

	DBfree_result(db_graphs);
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
	DB_RESULT	db_triggers;
	DB_RESULT	db_tmp_hosts;

	DB_ROW		trigger_data;
	DB_ROW		tmp_host_data;

	zbx_uint64_t
		t_templateid,
		tmp_hostid,
		triggerid;
	
	db_triggers = DBselect("select distinct t.triggerid,t.templateid,t.description from triggers t, functions f, items i"
                        " where i.hostid=" ZBX_FS_UI64 " and f.itemid=i.itemid and f.triggerid=t.triggerid", hostid);

	while( (trigger_data = DBfetch(db_triggers)) )
	{
		ZBX_STR2UINT64(t_templateid, trigger_data[1]);

		
		if( 0 == t_templateid )
			continue;

		if( 0 != templateid )
		{
			tmp_hostid = 0;

			db_tmp_hosts = DBselect("select distinct h.hostid from hosts h, functions f, items i"
						" where i.itemid=f.itemid and h.hostid=i.hostid and f.triggerid=" ZBX_FS_UI64, t_templateid);

			if( (tmp_host_data = DBfetch(db_tmp_hosts)) )
			{
				ZBX_STR2UINT64(tmp_hostid, tmp_host_data[0]);
			}

			DBfree_result(db_tmp_hosts);

			if(tmp_hostid != templateid)
				continue;
		}

		ZBX_STR2UINT64(triggerid, trigger_data[0]);

		if( unlink_mode )
		{
			DBexecute("update triggers set templateid=0 where triggerid=" ZBX_FS_UI64, triggerid);

			zabbix_log( LOG_LEVEL_DEBUG, "Trigger '%s' unlinked", trigger_data[2]);
		}
		else
		{
			DBdelete_trigger(triggerid);
		}
	}

	DBfree_result(db_triggers);
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
	DB_RESULT	db_items;
	DB_RESULT	db_tmp_hosts;

	DB_ROW		item_data;
	DB_ROW		tmp_host_data;

	zbx_uint64_t
		i_templateid,
		tmp_hostid,
		itemid;
	
	db_items = DBselect("select itemid,templateid,key_ from items where hostid=" ZBX_FS_UI64, hostid);

	while( (item_data = DBfetch(db_items)) )
	{
		ZBX_STR2UINT64(i_templateid, item_data[1]);
		
		if( 0 == i_templateid )
			continue;

		if( 0 != templateid )
		{
			db_tmp_hosts = DBselect("select itemid,templateid,key_ from items where hostid=" ZBX_FS_UI64, i_templateid);

			tmp_hostid = 0;

			if( (tmp_host_data = DBfetch(db_tmp_hosts)) )
			{
				ZBX_STR2UINT64(tmp_hostid, tmp_host_data[0]);
			}

			DBfree_result(db_tmp_hosts);

			if(tmp_hostid != templateid)
				continue;
		}

		ZBX_STR2UINT64(itemid, item_data[0]);

		if( unlink_mode )
		{
			DBexecute("update items set templateid=0 where itemid=" ZBX_FS_UI64, itemid);

			zabbix_log( LOG_LEVEL_DEBUG, "Item '%s' unlinked", item_data[2]);
		}
		else
		{
			DBdelete_item(itemid);
		}
	}

	DBfree_result(db_items);
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
	DB_RESULT	db_applications;
	DB_RESULT	db_tmp_hosts;

	DB_ROW		application_data;
	DB_ROW		tmp_host_data;

	zbx_uint64_t
		a_templateid,
		tmp_hostid,
		applicationid;
	
	db_applications = DBselect("select applicationid,templateid,name from applications where hostid=" ZBX_FS_UI64, hostid);

	while( (application_data = DBfetch(db_applications)) )
	{
		ZBX_STR2UINT64(a_templateid, application_data[1]);

		
		if( 0 == a_templateid )
			continue;

		if( 0 != templateid )
		{
			tmp_hostid = 0;

			db_tmp_hosts = DBselect("select hostid from applications where applicationid=" ZBX_FS_UI64, a_templateid);

			if( (tmp_host_data = DBfetch(db_tmp_hosts) ))
			{
				ZBX_STR2UINT64(tmp_hostid, tmp_host_data[0]);
			}

			DBfree_result(db_tmp_hosts);

			if(tmp_hostid != templateid)
				continue;
		}

		ZBX_STR2UINT64(applicationid, application_data[0]);

		if( unlink_mode )
		{
			DBexecute("update applications set templateid=0 where applicationid=" ZBX_FS_UI64, applicationid);

			zabbix_log( LOG_LEVEL_DEBUG, "Application '%s' unlinked", application_data[2]);
		}
		else
		{
			DBdelete_application(applicationid);
		}
	}

	DBfree_result(db_applications);
}

/******************************************************************************
 *                                                                            *
 * Function: DBdb_save_application                                            *
 *                                                                            *
 * Purpose: add or update application                                         *
 *                                                                            *
 * Parameters: name                                                           *
 *             hostid - host identificator from database                      *
 *             applicationid - application identificator from database        *
 *                             0 - for adding                                 *
 *             templateid - template application identificator from database  *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
static int	DBdb_save_application(
		const char	*name,
		zbx_uint64_t	hostid,
		zbx_uint64_t	applicationid,
		zbx_uint64_t	templateid
	)
{
	DB_RESULT	db_elements;
	DB_RESULT	db_hosts;

	DB_ROW		element_data;
	DB_ROW		host_data;

	zbx_uint64_t	applicationid_new = 0,
			elementid,
			db_hostid;

	char	*name_esc = NULL;

	int	result = SUCCEED;

	assert(name);

	name_esc = DBdyn_escape_string(name);

	if( 0 == applicationid )
		db_elements = DBselect("select distinct applicationid from applications "
				" where name='%s' and hostid=" ZBX_FS_UI64, name_esc, hostid);
	else
		db_elements = DBselect("select distinct applicationid from applications "
				" where name='%s' and hostid=" ZBX_FS_UI64
				" and applicationid<>" ZBX_FS_UI64, name_esc, hostid, applicationid);

	if( (element_data = DBfetch(db_elements)) )
	{
		ZBX_STR2UINT64(elementid, element_data[0]);

		if( 0 == templateid )
		{
			zabbix_log(LOG_LEVEL_DEBUG, "Application '%s' already exists", name);
			result = FAIL;
		}
		else if ( 0 != applicationid )
		{ /* delete old application with same name */
			DBdelete_application(elementid);
		}
		else
		{ /* if found application with same name update them, adding not needed */
			applicationid = elementid;
		}
	}

	DBfree_result(db_elements);

	if( SUCCEED == result )
	{
		db_hosts = DBselect("select host from hosts where hostid=" ZBX_FS_UI64, hostid);

		if( (host_data = DBfetch(db_hosts)) )
		{
			if( 0 == applicationid )
			{
				applicationid_new = DBget_maxid("applications","applicationid");

				DBexecute("insert into applications (applicationid,name,hostid,templateid)"
					" values (" ZBX_FS_UI64 ",'%s'," ZBX_FS_UI64 "," ZBX_FS_UI64 ")",
					applicationid_new, name_esc, hostid, templateid);

				zabbix_log( LOG_LEVEL_DEBUG, "Added new application '%s:%s'", host_data[0], name);
			}
			else
			{
				DBexecute("update applications set name='%s',hostid=" ZBX_FS_UI64 ",templateid=" ZBX_FS_UI64
					" where applicationid=" ZBX_FS_UI64, name_esc, hostid, templateid, applicationid);

				zabbix_log( LOG_LEVEL_DEBUG, "Updated application [" ZBX_FS_UI64 "] '%s:%s'", applicationid, host_data[0], name);
			}
		}

		DBfree_result(db_hosts);
	}

	if( SUCCEED == result)
	{
		if( 0 == applicationid )
		{ /* create application for childs */
			applicationid = applicationid_new;

			db_hosts = DBselect("select distinct hostid from hosts_templates where templateid=" ZBX_FS_UI64, hostid);

			while( (host_data = DBfetch(db_hosts)) )
			{ /* recursion */
				ZBX_STR2UINT64(elementid, host_data[0]);
				if( SUCCEED != (result = DBadd_application(name, elementid, applicationid)) )
					break;
			}

			DBfree_result(db_hosts);
		}
		else
		{
			db_elements = DBselect("select applicationid,hostid from applications where templateid=" ZBX_FS_UI64, applicationid);

			while( (element_data = DBfetch(db_elements)) )
			{ /* recursion */
				ZBX_STR2UINT64(elementid, element_data[0]);
				ZBX_STR2UINT64(db_hostid, element_data[1]);
				if( SUCCEED != (result = DBupdate_application(elementid, name, db_hostid, applicationid)) )
					break;
			}
			
			DBfree_result(db_elements);
		}

		if( SUCCEED != result && 0 == templateid )
		{
			DBdelete_application(applicationid);
		}
	}

	zbx_free(name_esc);

	return result;
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
 *             	           0 - copy and link elements                         *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
static int	DBcopy_template_applications(
		zbx_uint64_t hostid,
		zbx_uint64_t templateid,
		unsigned char copy_mode
	)
{
	DB_RESULT	db_elements;

	DB_ROW		element_data;

	zbx_uint64_t
		elementid;

	int	result = SUCCEED;

	if( 0 == templateid)
	{ /* sync with all linkage templates */
		db_elements = DBselect("select templateid from hosts_templates where hostid=" ZBX_FS_UI64, hostid);

		while( (element_data = DBfetch(db_elements)) )
		{
			ZBX_STR2UINT64(elementid, element_data[0]);
			if( 0 == elementid ) continue;

			/* recursion */
			if( SUCCEED != (result = DBcopy_template_applications(hostid, elementid, copy_mode)) )
				break;
		}

		DBfree_result(db_elements);
	}
	else
	{
		db_elements = DBselect("select applicationid,name from applications where hostid=" ZBX_FS_UI64, templateid);
		
		while( (element_data = DBfetch(db_elements)) )
		{
			ZBX_STR2UINT64(elementid, element_data[0]);
			if( SUCCEED != (result = DBadd_application(element_data[1], hostid, copy_mode ? 0 : elementid)) )
				break;
		}

		DBfree_result(db_elements);
	}

	return result;
}

/******************************************************************************
 *                                                                            *
 * Function: DBupdate_item                                                    *
 *                                                                            *
 * Purpose: udate item                                                        *
 *                                                                            *
 * Parameters: itemid - item identificator from database                      *
 *             description                                                    *
 *             key                                                            *
 *             hostid - host identificator from database                      *
 *             delay                                                          *
 *             history                                                        *
 *             status                                                         *
 *             type                                                           *
 *             snmp_community                                                 *
 *             snmp_oid                                                       *
 *             value_type                                                     *
 *             trapper_hosts                                                  *
 *             snmp_port                                                      *
 *             units                                                          *
 *             multiplier                                                     *
 *             delta                                                          *
 *             snmpv3_securityname                                            *
 *             snmpv3_securitylevel                                           *
 *             snmpv3_authpassphrase                                          *
 *             snmpv3_privpassphrase                                          *
 *             formula                                                        *
 *             trends                                                         *
 *             logtimefmt                                                     *
 *             valuemapid                                                     *
 *             delay_flex                                                     *
 *             apps - zero teminated array of applicationid                   *
 *             templateid - template item identificator from database         *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
static int	DBupdate_item(
		zbx_uint64_t	itemid,
		const char	*description,
		const char	*key,
		zbx_uint64_t	hostid,
		int 		delay,
		int 		history,
		int		status,
		int		type,
		const char	*snmp_community,
		const char	*snmp_oid,
		int		value_type,
		const char      *trapper_hosts,
		int		snmp_port,
		const char      *units,
		int		multiplier,
		int		delta,
		const char      *snmpv3_securityname,
		int		snmpv3_securitylevel,
		const char      *snmpv3_authpassphrase,
		const char      *snmpv3_privpassphrase,
		const char      *formula,
		int		trends,
		const char      *logtimefmt,
		zbx_uint64_t	valuemapid,
		const char      *delay_flex,
		zbx_uint64_t	*apps,
		zbx_uint64_t	templateid
	)
{
	register int	i = 0;

	DB_RESULT	db_hosts;
	DB_RESULT	db_items;
	DB_RESULT	db_chd_items;

	DB_ROW		host_data;
	DB_ROW		item_data;
	DB_ROW		chd_item_data;

	zbx_uint64_t
		itemappid,
		del_itemid = 0,
		chd_itemidm,
		chd_hostid,
		applications[ZBX_MAX_APPLICATIONS];

	char	*description_esc,
		*key_esc,
		*snmp_community_esc,
		*snmp_oid_esc,
		*trapper_hosts_esc,
		*units_esc,
		*snmpv3_securityname_esc,
		*snmpv3_authpassphrase_esc,
		*snmpv3_privpassphrase_esc,
		*formula_esc,
		*logtimefmt_esc,
		*delay_flex_esc;

	int	result = SUCCEED;

	db_hosts = DBselect("select host from hosts where hostid=" ZBX_FS_UI64, hostid);

	if( (host_data = DBfetch(db_hosts)) )
	{
		key_esc	= DBdyn_escape_string(key);

		if( ITEM_VALUE_TYPE_STR == value_type )
		{
			delta = 0;
		}

		if( (ITEM_TYPE_AGGREGATE == type) )
		{
			value_type = ITEM_VALUE_TYPE_FLOAT;
		}

		db_items = DBselect("select distinct itemid from items"
				" where hostid=" ZBX_FS_UI64 " and itemid<> " ZBX_FS_UI64 " and key_='%s'",
				hostid, itemid, key_esc);

		if( (item_data = DBfetch(db_items)) )
		{
			if( templateid == 0)
			{
				zabbix_log(LOG_LEVEL_DEBUG, "An item with the Key [%s] already exists for host [%s]."
					" The key must be unique.", key, host_data[0]);

				result = FAIL;
			}
			else
			{ /* delete dublicated items */
				ZBX_STR2UINT64(del_itemid, item_data[0]);
			}
		}

		DBfree_result(db_items);

		if( SUCCEED == result )
		{

			/* first update child items */
			db_chd_items = DBselect("select itemid, hostid from items where templateid=" ZBX_FS_UI64, itemid);

			while( (chd_item_data = DBfetch(db_chd_items)) )
			{
				ZBX_STR2UINT64(chd_itemidm, chd_item_data[0]);
				ZBX_STR2UINT64(chd_hostid, chd_item_data[1]);

				DBget_same_applications_for_host(apps, chd_hostid, applications, sizeof(applications) / sizeof(zbx_uint64_t));

				/* recursion */
				if( SUCCEED != (result = DBupdate_item(
					chd_itemidm, description, key, chd_hostid, 
					delay, history, status, type, snmp_community, snmp_oid,
					value_type, trapper_hosts, snmp_port, units, multiplier,
					delta, snmpv3_securityname, snmpv3_securitylevel,
					snmpv3_authpassphrase, snmpv3_privpassphrase, formula,
					trends, logtimefmt, valuemapid,delay_flex,
					applications,
					itemid)) )
					break;
			}

			DBfree_result(db_chd_items);

			if( 0 < del_itemid )
			{
				DBdelete_item(del_itemid);
			}

			if( SUCCEED == result )
			{
				DBexecute("update items set lastlogsize=0 where itemid=" ZBX_FS_UI64 " and key_<>'%s'", itemid, key_esc);

				DBexecute("delete from items_applications where itemid=" ZBX_FS_UI64, itemid);

				for( i=0; 0 < apps[i]; i++ )
				{
					itemappid = DBget_maxid("items_applications","itemappid");
					DBexecute("insert into items_applications (itemappid,itemid,applicationid) "
						" values(" ZBX_FS_UI64 "," ZBX_FS_UI64 "," ZBX_FS_UI64 ")",
						itemappid, itemid, apps[i]);
				}

				description_esc			= DBdyn_escape_string(description);
				snmp_community_esc		= DBdyn_escape_string(snmp_community);
				snmp_oid_esc			= DBdyn_escape_string(snmp_oid);
				trapper_hosts_esc		= DBdyn_escape_string(trapper_hosts);
				units_esc			= DBdyn_escape_string(units);
				snmpv3_securityname_esc		= DBdyn_escape_string(snmpv3_securityname);
				snmpv3_authpassphrase_esc	= DBdyn_escape_string(snmpv3_authpassphrase);
				snmpv3_privpassphrase_esc	= DBdyn_escape_string(snmpv3_privpassphrase);
				formula_esc			= DBdyn_escape_string(formula);
				logtimefmt_esc			= DBdyn_escape_string(logtimefmt);
				delay_flex_esc			= DBdyn_escape_string(delay_flex);

				DBexecute(
					"update items set description='%s',key_='%s',"
					"hostid=" ZBX_FS_UI64 ",delay=%i,history=%i,nextcheck=0,status=%i,type=%i,"
					"snmp_community='%s',snmp_oid='%s',"
					"value_type=%i,trapper_hosts='%s',"
					"snmp_port=%i,units='%s',multiplier=%i,delta=%i,"
					"snmpv3_securityname='%s',"
					"snmpv3_securitylevel=%i,"
					"snmpv3_authpassphrase='%s',"
					"snmpv3_privpassphrase='%s',"
					"formula='%s',trends=%i,logtimefmt='%s',"
					"valuemapid=" ZBX_FS_UI64 ",delay_flex='%s',"
					"templateid=" ZBX_FS_UI64 " where itemid=" ZBX_FS_UI64,
						description_esc,
						key_esc,
						hostid,
						delay,
						history,
						status,
						type,
						snmp_community_esc,
						snmp_oid_esc,
						value_type,
						trapper_hosts_esc,
						snmp_port,
						units_esc,
						multiplier,
						delta,
						snmpv3_securityname_esc,
						snmpv3_securitylevel,
						snmpv3_authpassphrase_esc,
						snmpv3_privpassphrase_esc,
						formula_esc,
						trends,
						logtimefmt_esc,
						valuemapid,
						delay_flex_esc,
						templateid,
						itemid);

				zbx_free(description_esc);
				zbx_free(snmp_community_esc);
				zbx_free(snmp_oid_esc);
				zbx_free(trapper_hosts_esc);
				zbx_free(units_esc);
				zbx_free(snmpv3_securityname_esc);
				zbx_free(snmpv3_authpassphrase_esc);
				zbx_free(snmpv3_privpassphrase_esc);
				zbx_free(formula_esc);
				zbx_free(logtimefmt_esc);
				zbx_free(delay_flex_esc);

				zabbix_log(LOG_LEVEL_DEBUG, "Item '%s:%s' updated", host_data[0], key);
			}
		}
		zbx_free(key_esc);
	}

	DBfree_result(db_hosts);

	return result;
}

/******************************************************************************
 *                                                                            *
 * Function: DBadd_item                                                       *
 *                                                                            *
 * Purpose: add item to database                                              *
 *                                                                            *
 * Parameters: description                                                    *
 *             key                                                            *
 *             hostid - host identificator from database                      *
 *             delay                                                          *
 *             history                                                        *
 *             status                                                         *
 *             type                                                           *
 *             snmp_community                                                 *
 *             snmp_oid                                                       *
 *             value_type                                                     *
 *             trapper_hosts                                                  *
 *             snmp_port                                                      *
 *             units                                                          *
 *             multiplier                                                     *
 *             delta                                                          *
 *             snmpv3_securityname                                            *
 *             snmpv3_securitylevel                                           *
 *             snmpv3_authpassphrase                                          *
 *             snmpv3_privpassphrase                                          *
 *             formula                                                        *
 *             trends                                                         *
 *             logtimefmt                                                     *
 *             valuemapid                                                     *
 *             delay_flex                                                     *
 *             apps - zero teminated array of applicationid                   *
 *             templateid - template item identificator from database         *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
static int	DBadd_item(
		const char	*description,
		const char	*key,
		zbx_uint64_t	hostid,
		int 		delay,
		int 		history,
		int		status,
		int		type,
		const char	*snmp_community,
		const char	*snmp_oid,
		int		value_type,
		const char      *trapper_hosts,
		int		snmp_port,
		const char      *units,
		int		multiplier,
		int		delta,
		const char      *snmpv3_securityname,
		int		snmpv3_securitylevel,
		const char      *snmpv3_authpassphrase,
		const char      *snmpv3_privpassphrase,
		const char      *formula,
		int		trends,
		const char      *logtimefmt,
		zbx_uint64_t	valuemapid,
		const char      *delay_flex,
		zbx_uint64_t	*apps,
		zbx_uint64_t	templateid
	)
{
	register int	i = 0;

	DB_RESULT	db_hosts;
	DB_RESULT	db_items;
	DB_RESULT	db_chd_hosts;

	DB_ROW		host_data;
	DB_ROW		item_data;
	DB_ROW		chd_host_data;

	zbx_uint64_t
		itemid,
		chd_hostid,
		itemappid,
		applications[ZBX_MAX_APPLICATIONS];

	char	*description_esc,
		*key_esc,
		*snmp_community_esc,
		*snmp_oid_esc,
		*trapper_hosts_esc,
		*units_esc,
		*snmpv3_securityname_esc,
		*snmpv3_authpassphrase_esc,
		*snmpv3_privpassphrase_esc,
		*formula_esc,
		*logtimefmt_esc,
		*delay_flex_esc;

	int	result = SUCCEED;

	db_hosts = DBselect("select host from hosts where hostid=" ZBX_FS_UI64, hostid);

	if( (host_data = DBfetch(db_hosts)) )
	{
		key_esc	= DBdyn_escape_string(key);

		if( ITEM_VALUE_TYPE_STR == value_type )
		{
			delta = 0;
		}

		if( (ITEM_TYPE_AGGREGATE == type) )
		{
			value_type = ITEM_VALUE_TYPE_FLOAT;
		}

		db_items = DBselect("select distinct itemid from items"
				" where hostid=" ZBX_FS_UI64 " and key_='%s'", hostid, key_esc);

		itemid = 0;

		if( (item_data = DBfetch(db_items)) )
		{
			if( templateid == 0)
			{
				zabbix_log(LOG_LEVEL_DEBUG, "An item with the Key [%s] already exists for host [%s]."
					" The key must be unique.", key, host_data[0]);

				result = FAIL;
			} else {
				ZBX_STR2UINT64(itemid, item_data[0]);

				result = DBupdate_item(
					itemid, description, key, hostid,
					delay, history, status, type, snmp_community, snmp_oid,
					value_type, trapper_hosts, snmp_port, units, multiplier,
					delta, snmpv3_securityname, snmpv3_securitylevel,
					snmpv3_authpassphrase, snmpv3_privpassphrase, formula,
					trends, logtimefmt, valuemapid, delay_flex,
					apps,
					templateid);
			}
		}

		DBfree_result(db_items);

		if( (itemid && SUCCEED != result) || !item_data )
		{
			// first add mother item
			itemid = DBget_maxid("items","itemid");

			description_esc			= DBdyn_escape_string(description);
			snmp_community_esc		= DBdyn_escape_string(snmp_community);
			snmp_oid_esc			= DBdyn_escape_string(snmp_oid);
			trapper_hosts_esc		= DBdyn_escape_string(trapper_hosts);
			units_esc			= DBdyn_escape_string(units);
			snmpv3_securityname_esc		= DBdyn_escape_string(snmpv3_securityname);
			snmpv3_authpassphrase_esc	= DBdyn_escape_string(snmpv3_authpassphrase);
			snmpv3_privpassphrase_esc	= DBdyn_escape_string(snmpv3_privpassphrase);
			formula_esc			= DBdyn_escape_string(formula);
			logtimefmt_esc			= DBdyn_escape_string(logtimefmt);
			delay_flex_esc			= DBdyn_escape_string(delay_flex);

			DBexecute("insert into items"
				" (itemid,description,key_,hostid,delay,history,nextcheck,status,type,"
				"snmp_community,snmp_oid,value_type,trapper_hosts,snmp_port,units,multiplier,"
				"delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,"
				"snmpv3_privpassphrase,formula,trends,logtimefmt,valuemapid,delay_flex,templateid)"
				" values (" ZBX_FS_UI64 ",'%s','%s'," ZBX_FS_UI64 ",%i,%i,0,"
				" %i,%i,'%s','%s',%i,'%s',%i,'%s',%i,%i,'%s',%i,'%s','%s','%s',%i,'%s'," ZBX_FS_UI64 ","
				" '%s'," ZBX_FS_UI64 ")",
					itemid,
					description_esc,
					key_esc,
					hostid,
					delay,
					history,
					status,
					type,
					snmp_community_esc,
					snmp_oid_esc,
					value_type,
					trapper_hosts_esc,
					snmp_port,
					units_esc,
					multiplier,
					delta,
					snmpv3_securityname_esc,
					snmpv3_securitylevel,
					snmpv3_authpassphrase_esc,
					snmpv3_privpassphrase_esc,
					formula_esc,
					trends,
					logtimefmt_esc,
					valuemapid,
					delay_flex_esc,
					templateid);

			zbx_free(description_esc);
			zbx_free(snmp_community_esc);
			zbx_free(snmp_oid_esc);
			zbx_free(trapper_hosts_esc);
			zbx_free(units_esc);
			zbx_free(snmpv3_securityname_esc);
			zbx_free(snmpv3_authpassphrase_esc);
			zbx_free(snmpv3_privpassphrase_esc);
			zbx_free(formula_esc);
			zbx_free(logtimefmt_esc);
			zbx_free(delay_flex_esc);

			for( i=0; 0 < apps[i]; i++)
			{
				itemappid = DBget_maxid("items_applications","itemappid");

				DBexecute("insert into items_applications (itemappid,itemid,applicationid) "
					" values(" ZBX_FS_UI64 "," ZBX_FS_UI64 "," ZBX_FS_UI64 ")",
					itemappid, itemid, apps[i]);
			}

			/* add items to child hosts */
			db_chd_hosts = DBselect("select hostid from hosts_templates where templateid=" ZBX_FS_UI64, hostid);

			while( (chd_host_data = DBfetch(db_chd_hosts)) )
			{	/* recursion */

				ZBX_STR2UINT64(chd_hostid, chd_host_data[0]);

				DBget_same_applications_for_host(apps, chd_hostid, applications, sizeof(applications) / sizeof(zbx_uint64_t));

				if( SUCCEED != (result = DBadd_item(description, key, chd_hostid,
					delay, history, status, type, snmp_community, snmp_oid,
					value_type, trapper_hosts, snmp_port, units, multiplier,
					delta, snmpv3_securityname, snmpv3_securitylevel,
					snmpv3_authpassphrase, snmpv3_privpassphrase, formula,
					trends, logtimefmt, valuemapid,delay_flex,
					applications,
					itemid)) )
						break;
			}

			DBfree_result(db_chd_hosts);

			if( SUCCEED != result )
			{
				DBdelete_item(itemid);
			}
			else
			{
				zabbix_log(LOG_LEVEL_DEBUG, "Added new item '%s:%s'", host_data[0], key);
			}
		}
		zbx_free(key_esc);
	}

	DBfree_result(db_hosts);

	return result;
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
 *             	           0 - copy and link elements                         *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
static int	DBcopy_template_items(
		zbx_uint64_t hostid,
		zbx_uint64_t templateid,
		unsigned char copy_mode
	)
{
	DB_RESULT	db_elements;

	DB_ROW		element_data;

	zbx_uint64_t
		elementid,
		valuemapid;

	int	result = SUCCEED;

	zbx_uint64_t
		tmp_apps[ZBX_MAX_APPLICATIONS],
		apps[ZBX_MAX_APPLICATIONS];

	if( 0 == templateid)
	{ /* sync with all linkage templates */
		db_elements = DBselect("select templateid from hosts_templates where hostid=" ZBX_FS_UI64, hostid);

		while( (element_data = DBfetch(db_elements)) )
		{
			ZBX_STR2UINT64(elementid, element_data[0]);
			if( 0 == elementid ) continue;

			/* recursion */
			if( SUCCEED != (result = DBcopy_template_items(hostid, elementid, copy_mode)) )
				break;
		}

		DBfree_result(db_elements);
	}
	else
	{
		db_elements = DBselect("select itemid,description,key_,delay,history,status,type,snmp_community,"
					"snmp_oid,value_type,trapper_hosts,snmp_port,units,multiplier,delta,"
					"snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,"
					"snmpv3_privpassphrase,formula,trends,logtimefmt,valuemapid,delay_flex "
					" from items where hostid=" ZBX_FS_UI64, templateid);
		
		while( (element_data = DBfetch(db_elements)) )
		{
			ZBX_STR2UINT64(elementid, element_data[0]);
			ZBX_STR2UINT64(valuemapid, element_data[22]);

			DBget_applications_by_itemid(elementid, tmp_apps, sizeof(tmp_apps) / sizeof(zbx_uint64_t));
			DBget_same_applications_for_host(tmp_apps, hostid, apps, sizeof(apps) / sizeof(zbx_uint64_t));

			if( SUCCEED != (result = DBadd_item(
							element_data[1],	/* description */
							element_data[2],	/* key_ */
							hostid,
							atoi(element_data[3]),	/* delay */
							atoi(element_data[4]),	/* history */
							atoi(element_data[5]),	/* status */
							atoi(element_data[6]),	/* type */
							element_data[7],	/* snmp_community */
							element_data[8],	/* snmp_oid */
							atoi(element_data[9]),	/* value_type */
							element_data[10],	/* trapper_hosts */
							atoi(element_data[11]),	/* snmp_port */
							element_data[12],	/* units */
							atoi(element_data[13]),	/* multiplier */
							atoi(element_data[14]),	/* delta */
							element_data[15],	/* snmpv3_securityname */
							atoi(element_data[16]),	/* snmpv3_securitylevel */
							element_data[17],	/* snmpv3_authpassphrase */
							element_data[18],	/* snmpv3_privpassphrase */
							element_data[19],	/* formula */
							atoi(element_data[20]),	/* trends */
							element_data[21],	/* logtimefmt */
							valuemapid,		/* valuemapid */
							element_data[23],	/* delay_flex */
							apps,
							copy_mode ? 0 : elementid))
				)
				break;
		}

		DBfree_result(db_elements);
	}

	return result;
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
static int	DBreset_items_nextcheck(
		zbx_uint64_t	triggerid
	)
{
	DB_RESULT	db_functions;

	DB_ROW		function_data;

	zbx_uint64_t
		itemid;

	db_functions = DBselect("select itemid from functions where triggerid=" ZBX_FS_UI64, triggerid);

	while( (function_data = DBfetch(db_functions)) )
	{
		ZBX_STR2UINT64(itemid, function_data[0]);

		DBexecute("update items set nextcheck=0 where itemid=" ZBX_FS_UI64, itemid);
	}

	DBfree_result(db_functions);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: DBreplace_template_dependences                                   *
 *                                                                            *
 * Purpose: replace trigger dependences by specified host                     *
 *                                                                            *
 * Parameters: dependences_in - zero terminated array of dependences          *
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
	DB_RESULT	db_events;

	DB_ROW		event_data;

	zbx_uint64_t
		eventid;

	int	result = FAIL;

	if( !now )	now = time(NULL);

	db_events = DBselect("select value,clock from events where objectid=" ZBX_FS_UI64 " and object=%i "
		" order by clock desc", triggerid, EVENT_OBJECT_TRIGGER);

	if( (event_data = DBfetch(db_events)) )
	{
		if( value != atoi(event_data[0]) )
			result = SUCCEED;
	}

	if( SUCCEED == result )
	{
		eventid = DBget_maxid("events","eventid");

		DBexecute("insert into events(eventid,source,object,objectid,clock,value) "
				" values(" ZBX_FS_UI64 ",%i,%i," ZBX_FS_UI64 ",%lu,%i)",
				eventid, EVENT_SOURCE_TRIGGERS, EVENT_OBJECT_TRIGGER, triggerid, now, value);

		if(value == TRIGGER_VALUE_FALSE || value == TRIGGER_VALUE_TRUE)
		{
			DBexecute("update alerts set retries=3,error='Trigger changed its status. WIll not send repeats.'"
				" where triggerid=" ZBX_FS_UI64 " and repeats>0 and status=%i",
				triggerid, ALERT_STATUS_NOT_SENT);
		}
	}

	DBfree_result(db_events);

	return result;
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
 * Comments: function dynamically allocate memory, don't forget free them     *
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
			if( NULL == (p = strchr(host, ':'))) break; /* simpla throw for C */
			host_len = p - host;

			/* determine parameter */
			if( NULL == (p = strrchr(p+1, '(')) ) break; /* simpla throw for C */
			parameter = p+1;

			if( NULL == (p = strrchr(parameter, ')')) ) break; /* simpla throw for C */
			parameter_len = p - parameter;

			/* determine key and function */
			p = parameter-1; /* position of '(' character */
			*p = '\0';
			do { /* simple try for C */
				key = host + host_len + 1;
				if( NULL == (p = strrchr(key, '.'))) break; /* simpla throw for C */
				key_len = p - key;

				function = p + 1;
				function_len = parameter - 1 - function;

			} while(0); /* simpla finally for C */
			*p = '(';

		} while(0);/* simpla finally for C */

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
				str_esc = DBdyn_escape_string(function);
				sql = zbx_strdcatf(sql, "'%s',", str_esc);
				zbx_free(str_esc);
				function[function_len] = '(';
				/* adding parameter */
				parameter[parameter_len] = '\0';
				str_esc = DBdyn_escape_string(parameter);
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
 * Purpose: retrive trigger dependences                                       *
 *                                                                            *
 * Parameters: triggerid - trigger identificator from database                *
 *             dependences - buffer for result                                *
 *             max_dependences - size of buffer in counts                     *
 *                                                                            *
 * Return value: count of dependences                                         *
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
 * Comments: function dynamically allocate memory, don't forget free them     *
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
 *             dependences - null terminated array with dependences           *
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

	db_triggers = DBselect("select distinct t.description,h.host,t.expression,t.priority,t.status,t.comments,t.url "
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
			str_esc = DBdyn_escape_string(short_expression);
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
			str_esc = DBdyn_escape_string(url);
			sql = zbx_strdcatf(sql, " url='%s',", str_esc);
			zbx_free(str_esc);
		}
		if( templateid )	sql = zbx_strdcatf(sql, " templateid=" ZBX_FS_UI64 ",", templateid);

		sql = zbx_strdcatf(sql, " value=2 where triggerid=" ZBX_FS_UI64,	triggerid);

		DBexecute("%s",sql);

		zbx_free(sql);

		DBreset_items_nextcheck(triggerid);

		DBadd_event(triggerid,TRIGGER_VALUE_UNKNOWN,0);

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
 *             	           0 - copy and link elements                         *
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
		zbx_uint64_t hostid,
		unsigned char copy_mode
	)
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

	db_triggers = DBselect("select description,priority,status,comments,url,expression from triggers where triggerid=" ZBX_FS_UI64, triggerid);

	if( (trigger_data = DBfetch(db_triggers)) )
	{
		result = FAIL;

		DBget_trigger_dependences_by_triggerid(triggerid, dependences, sizeof(dependences) / sizeof(zbx_uint64_t));
		DBreplace_template_dependences(dependences, hostid, new_dependences, sizeof(new_dependences) / sizeof(zbx_uint64_t));

		db_host_triggers = DBselect("select distinct t.triggerid,t.templateid from functions f,items i,triggers t "
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
				new_dependences,
				copy_mode ? 0 : triggerid);

			break;
		}

		DBfree_result(db_host_triggers);

		if( SUCCEED != result )
		{ /* create triger if no updated triggers */
			result = SUCCEED;

			new_triggerid = DBget_maxid("triggers","triggerid");

			description_esc = DBdyn_escape_string(trigger_data[0]);
			comments_esc = DBdyn_escape_string(trigger_data[3]);
			url_esc = DBdyn_escape_string(trigger_data[4]);

			DBexecute("insert into triggers"
				" (triggerid,description,priority,status,comments,url,value,expression,templateid)"
				" values (" ZBX_FS_UI64 ",'%s',%i,%i,'%s','%s',2,'{???:???}'," ZBX_FS_UI64 ")",
					new_triggerid,
					description_esc,	/* description */
					atoi(trigger_data[1]),	/* priority */
					atoi(trigger_data[2]),	/* status */
					comments_esc,		/* comments */
					url_esc,		/* url */
					copy_mode ? 0 : triggerid);

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
				new_expression_esc = DBdyn_escape_string(new_expression);
				DBexecute("update triggers set expression='%s' where triggerid=" ZBX_FS_UI64, new_expression, new_triggerid);
				zbx_free(new_expression_esc);

				/* copy dependences */
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
					if( SUCCEED != (result = DBcopy_trigger_to_host(new_triggerid, chd_hostid, copy_mode)) )
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
 * Function: DBupdate_template_dependences_for_host                           *
 *                                                                            *
 * Purpose: update trigger dependences for specified host                     *
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
static int	DBupdate_template_dependences_for_host(
		zbx_uint64_t hostid
	)
{
	DB_RESULT	db_triggers;
	DB_RESULT	db_chd_triggers;

	DB_ROW		trigger_data;
	DB_ROW		chd_trigger_data;

	int	result = SUCCEED;

	zbx_uint64_t
		triggerid,
		chd_triggerid,
		dependences[ZBX_MAX_DEPENDENCES],
		new_dependences[ZBX_MAX_DEPENDENCES];

	db_triggers = DBselect("select distinct t.triggerid from triggers t, functions f, items i"
		" where i.hostid=" ZBX_FS_UI64 " and f.itemid=i.itemid and f.triggerid=t.triggerid", hostid);

	while( (trigger_data = DBfetch(db_triggers)) )
	{
		ZBX_STR2UINT64(triggerid, trigger_data[0]);

		db_chd_triggers = DBselect("select distinct triggerid from triggers where templateid=" ZBX_FS_UI64, triggerid);

		while( (chd_trigger_data = DBfetch(db_chd_triggers)) )
		{
			ZBX_STR2UINT64(chd_triggerid, chd_trigger_data[0]);

			DBget_trigger_dependences_by_triggerid(triggerid, dependences, sizeof(dependences) / sizeof(zbx_uint64_t));
			DBreplace_template_dependences(dependences, hostid, new_dependences, sizeof(new_dependences) / sizeof(zbx_uint64_t));

			if( SUCCEED != (result = DBupdate_trigger(
				chd_triggerid,
				/* expression */         NULL,
				/* description */        NULL,
				/* priority */           -1,
				/* status */             -1,
				/* comments */           NULL,
				/* url */                NULL,
				new_dependences,
				triggerid)) )
					break;
		}

		DBfree_result(db_chd_triggers);

		if( SUCCEED != result ) break;
	}

	DBfree_result(db_triggers);

	return result;
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
 *             	           0 - copy and link elements                         *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
static int	DBcopy_template_triggers(
		zbx_uint64_t hostid,
		zbx_uint64_t templateid,
		unsigned char copy_mode
	)
{
	DB_RESULT	db_elements;

	DB_ROW		element_data;

	zbx_uint64_t
		elementid;

	int	result = SUCCEED;

	if( 0 == templateid)
	{ /* sync with all linkage templates */
		db_elements = DBselect("select templateid from hosts_templates where hostid=" ZBX_FS_UI64, hostid);

		while( (element_data = DBfetch(db_elements)) )
		{
			ZBX_STR2UINT64(elementid, element_data[0]);
			if( 0 == elementid ) continue;

			/* recursion */
			if( SUCCEED != (result = DBcopy_template_triggers(hostid, elementid, copy_mode)) )
				break;
		}

		DBfree_result(db_elements);
	}
	else
	{
		db_elements = DBselect("select distinct t.triggerid from triggers t, functions f, items i"
		                        " where i.hostid=" ZBX_FS_UI64 " and f.itemid=i.itemid and f.triggerid=t.triggerid", templateid);
		
		while( (element_data = DBfetch(db_elements)) )
		{
			ZBX_STR2UINT64(elementid, element_data[0]);

			if( SUCCEED != (result = DBcopy_trigger_to_host(elementid, hostid, copy_mode)) )
				break;
		}

		DBfree_result(db_elements);

		if( SUCCEED == result)
			result = DBupdate_template_dependences_for_host(hostid);
	}

	return result;
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
 *             	           0 - copy and link elements                         *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
static int	DBcopy_graph_to_host(
		zbx_uint64_t	graphid,
		zbx_uint64_t	hostid,
		unsigned char copy_mode
	)
{
	DB_RESULT	db_items;
	DB_ROW		db_item_data;

	DB_RESULT	db_graphs;
	DB_ROW		db_graph_data;

	DB_RESULT	chd_graphs;
	DB_ROW		chd_graph_data;

	ZBX_GRAPH_ITEMS chd_gitem;
	ZBX_GRAPH_ITEMS *gitems = NULL;
	ZBX_GRAPH_ITEMS *new_gitems = NULL;

	zbx_uint64_t	chd_graphid = 0;
	zbx_uint64_t	chd_templateid = 0;

	int	gitems_count = 0,
		i = 0,
		gitem_equal = 0,
		equal = 0;

	int	result = SUCCEED;

	result = FAIL;

	gitems = (ZBX_GRAPH_ITEMS*)zbx_malloc((void*)gitems, sizeof(ZBX_GRAPH_ITEMS));
	gitems[0].itemid = 0;
	gitems_count = 0;

	db_items = DBselect("select gi.itemid,gi.color,gi.drawtype,gi.sortorder,gi.yaxisside,gi.calc_fnc,gi.type,gi.periods_cnt "
			"from graphs_items gi where gi.graphid=" ZBX_FS_UI64, graphid);
	while ( (db_item_data = DBfetch(db_items)) )
	{
		ZBX_STR2UINT64(gitems[gitems_count].itemid, db_item_data[0]);

		gitems[gitems_count].color	= strdup(db_item_data[1]);
		gitems[gitems_count].drawtype	= atoi(db_item_data[2]);
		gitems[gitems_count].sortorder	= atoi(db_item_data[3]);
		gitems[gitems_count].yaxisside	= atoi(db_item_data[4]);
		gitems[gitems_count].calc_fnc	= atoi(db_item_data[5]);
		gitems[gitems_count].type	= atoi(db_item_data[6]);
		gitems[gitems_count].periods_cnt= atoi(db_item_data[7]);

		gitems = zbx_realloc(gitems, (gitems_count+2)*sizeof(ZBX_GRAPH_ITEMS));
		gitems[gitems_count+1].itemid = 0;

		gitems_count++;
	}
	DBfree_result(db_items);

	db_graphs = DBselect("select name,width,height,yaxistype,yaxismin,yaxismax,show_work_period,"
			"show_triggers,graphtype from graphs where graphid=" ZBX_FS_UI64, graphid);

	db_graph_data = DBfetch(db_graphs);

	if ( (new_gitems = DBget_same_graphitems_for_host(gitems, hostid)) )
	{
		chd_graphid = 0;
		chd_graphs = DBselect("select distinct g.graphid,g.name,g.width,g.height,g.yaxistype,g.yaxismin,g.yaxismax,g.show_work_period,"
				"g.show_triggers,g.graphtype,g.templateid from graphs g, graphs_items gi, items i "
				" where g.graphid=gi.graphid and gi.itemid=i.itemid and i.hostid=" ZBX_FS_UI64, hostid);
		while( !chd_graphid && (chd_graph_data = DBfetch(chd_graphs)))
		{ /* compare graphs */
			ZBX_STR2UINT64(chd_graphid, chd_graph_data[0]);
			ZBX_STR2UINT64(chd_templateid, chd_graph_data[10]);

			if ( chd_templateid != 0 ) continue;

			equal = 0;
			db_items = DBselect("select gi.itemid,gi.color,gi.drawtype,gi.sortorder,"
					"gi.yaxisside,gi.calc_fnc,gi.type,gi.periods_cnt "
					" from graphs_items gi where gi.graphid=" ZBX_FS_UI64, graphid);
			while( (db_item_data = DBfetch(db_items)) )
			{
				ZBX_STR2UINT64(chd_gitem.itemid, db_item_data[0]);

				chd_gitem.color		= db_item_data[1]; /* NOTE: copy refernce only */
				chd_gitem.drawtype	= atoi(db_item_data[2]);
				chd_gitem.sortorder	= atoi(db_item_data[3]);
				chd_gitem.yaxisside	= atoi(db_item_data[4]);
				chd_gitem.calc_fnc	= atoi(db_item_data[5]);
				chd_gitem.type		= atoi(db_item_data[6]);
				chd_gitem.periods_cnt	= atoi(db_item_data[7]);

				gitem_equal = 0;
				for ( i = 0; new_gitems[i].itemid != 0; i++ )
				{
					if(DBcmp_graphitems(&new_gitems[i], &chd_gitem))	continue;

					gitem_equal = 1;
					break;
				}

				if ( !gitem_equal )
				{
					equal = 0;
					break;
				}

				/* founded equal graph item */
				equal++;
			}

			DBfree_result(db_items);

			if ( equal && gitems_count == equal )
			{ /* founded equal graph */
				break;
			}

			chd_graphid = 0;
		}

		DBfree_result(chd_graphs);

		if ( chd_graphid )
		{
			result = DBupdate_graph_with_items(
				chd_graphid,
				db_graph_data[0],		/* name */
				atoi(db_graph_data[1]),	/* width */
				atoi(db_graph_data[2]),	/* height */
				atoi(db_graph_data[3]),	/* yaxistype */
				atoi(db_graph_data[4]),	/* yaxismin */
				atoi(db_graph_data[5]),	/* yaxismax */
				atoi(db_graph_data[6]),	/* show_work_period */
				atoi(db_graph_data[7]),	/* show_triggers */
				atoi(db_graph_data[8]),	/* graphtype */
				new_gitems,
				copy_mode ? 0 : graphid);
		}
		else
		{
			result = DBadd_graph_with_items(
				&chd_graphid,
				db_graph_data[0],	/* name */
				atoi(db_graph_data[1]),	/* width */
				atoi(db_graph_data[2]),	/* height */
				atoi(db_graph_data[3]),	/* yaxistype */
				atoi(db_graph_data[4]),	/* yaxismin */
				atoi(db_graph_data[5]),	/* yaxismax */
				atoi(db_graph_data[6]),	/* show_work_period */
				atoi(db_graph_data[7]),	/* show_triggers */
				atoi(db_graph_data[8]),	/* graphtype */
				new_gitems,
				copy_mode ? 0 : graphid);
		}

		zbx_free_gitems(new_gitems);
	}
	else
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Skipped coping of graph '%s' to host " ZBX_FS_UI64, db_graph_data[0], hostid);
		result = FAIL;
	}

	zbx_free_gitems(gitems);
	DBfree_result(db_graphs);

	return result;
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
 *             	           0 - copy and link elements                         *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
static int	DBcopy_template_graphs(
		zbx_uint64_t hostid,
		zbx_uint64_t templateid,
		unsigned char copy_mode
	)
{
	DB_RESULT	db_elements;

	DB_ROW		element_data;

	zbx_uint64_t
		elementid;

	int	result = SUCCEED;

	if( 0 == templateid)
	{ /* sync with all linkage templates */
		db_elements = DBselect("select templateid from hosts_templates where hostid=" ZBX_FS_UI64, hostid);

		while( (element_data = DBfetch(db_elements)) )
		{
			ZBX_STR2UINT64(elementid, element_data[0]);
			if( 0 == elementid ) continue;

			/* recursion */
			if( SUCCEED != (result = DBcopy_template_triggers(hostid, elementid, copy_mode)) )
				break;
		}

		DBfree_result(db_elements);
	}
	else
	{
		db_elements = DBselect("select distinct g.graphid from graphs g, graphs_items gi, items i"
				" where g.graphid=gi.graphid and gi.itemid=i.itemid and i.hostid=" ZBX_FS_UI64, templateid);
		
		while( (element_data = DBfetch(db_elements)) )
		{
			ZBX_STR2UINT64(elementid, element_data[0]);

			if( SUCCEED != (result = DBcopy_graph_to_host(elementid, hostid, copy_mode)) )
				break;
		}

		DBfree_result(db_elements);
	}

	return result;
}

/******************************************************************************
 *                                                                            *
 * Function: DBdelete_sysmaps_elements_with_hostid                            *
 *                                                                            *
 * Purpose: delete hosts from maps                                            *
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
static int	DBdelete_sysmaps_elements_with_hostid(
		zbx_uint64_t	hostid
	)
{
	DB_RESULT	db_elements;

	DB_ROW		element_data;

	zbx_uint64_t
		elementid;

	db_elements = DBselect("select selementid from sysmaps_elements"
		" where elementid=" ZBX_FS_UI64 " and elementtype=%i", hostid, SYSMAP_ELEMENT_TYPE_HOST);

	while( (element_data = DBfetch(db_elements)) )
	{
		ZBX_STR2UINT64(elementid, element_data[0]);

		DBdelete_sysmaps_element(elementid);
	}

	DBfree_result(db_elements);

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
 *             unlink_mode - 1 - only unlink elements without deletion        *
 *                           0 - delete elements                              *
 * Return value:                                                              *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
/* public */ void	DBdelete_template_elements(
		zbx_uint64_t  hostid,
		zbx_uint64_t templateid,
		unsigned char unlink_mode
	)
{
	DBdelete_template_graphs(hostid, templateid, unlink_mode);
	DBdelete_template_triggers(hostid, templateid, unlink_mode);
	DBdelete_template_items(hostid, templateid, unlink_mode);
	DBdelete_template_applications(hostid, templateid, unlink_mode);
}

/******************************************************************************
 *                                                                            *
 * Function: DBcopy_template_elements                                         *
 *                                                                            *
 * Purpose: copy elements from specified template                             *
 *                                                                            *
 * Parameters: hostid - host identificator from database                      *
 *             templateid - template identificator from database              *
 *             copy_mode - 1 - only copy elements without linkage             *
 *             	           0 - copy and link elements                         *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget sync code with PHP !!!                          *
 *                                                                            *
 ******************************************************************************/
/* public */ int	DBcopy_template_elements(
		zbx_uint64_t hostid,
		zbx_uint64_t templateid,
		unsigned char copy_mode
	)
{
	int result = SUCCEED;

	if(SUCCEED == (result = DBcopy_template_applications(hostid, templateid, copy_mode)) )
	if(SUCCEED == (result = DBcopy_template_items(hostid, templateid, copy_mode)) )
	if(SUCCEED == (result = DBcopy_template_triggers(hostid, templateid, copy_mode)) )
		result = DBcopy_template_graphs(hostid, templateid, copy_mode);

	return result;
}

/******************************************************************************
 *                                                                            *
 * Function: DBsync_host_with_template                                        *
 *                                                                            *
 * Purpose: synchronize elements from specified template                      *
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
/* public */ int	DBsync_host_with_template(
		zbx_uint64_t hostid,
		zbx_uint64_t templateid
	)
{
	DBdelete_template_elements(hostid, templateid, 0 /* not a unlink mode */);

	return DBcopy_template_elements(hostid, templateid, 0 /* not a copy mode */);
}

/******************************************************************************
 *                                                                            *
 * Function: DBsync_host_with_templates                                       *
 *                                                                            *
 * Purpose: synchronize elements from linked templates                        *
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
/* public */ int	DBsync_host_with_templates(
		zbx_uint64_t hostid
	)
{
	DB_RESULT	db_templates;

	DB_ROW		template_data;

	zbx_uint64_t
		triggerid;

	int	result = SUCCEED;

	zabbix_log( LOG_LEVEL_DEBUG, "In DBsync_host_with_templates(%d)", hostid);

	db_templates = DBselect("select templateid,items,triggers,graphs from hosts_templates where hostid=" ZBX_FS_UI64, hostid);

	while( (template_data = DBfetch(db_templates)) )
	{
		ZBX_STR2UINT64(triggerid, template_data[0]);

		if( SUCCEED != (result = DBsync_host_with_template(hostid, triggerid)) )
			break;
	}

	DBfree_result(db_templates);

	return result;
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
 * Purpose: delete host from databases with all elements                      *
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
	DB_RESULT	db_elements;

	DB_ROW		element_data;

	zbx_uint64_t
		elementid;

	/* unlink child hosts */
	db_elements = DBselect("select hostid from hosts_templates where templateid=" ZBX_FS_UI64, hostid);

	while( (element_data = DBfetch(db_elements)) )
	{
		ZBX_STR2UINT64(elementid, element_data[0]);
		DBunlink_template(elementid, hostid);
	}

	DBfree_result(db_elements);

	/* delete items -> triggers -> graphs */
	db_elements = DBselect("select itemid from items where hostid=" ZBX_FS_UI64, hostid);

	while( (element_data = DBfetch(db_elements)) )
	{
		ZBX_STR2UINT64(elementid, element_data[0]);

		DBdelete_item(elementid);
	}

	DBfree_result(db_elements);

	/* delete host from maps */
	DBdelete_sysmaps_elements_with_hostid(hostid);

	/* delete host from group */
	DBexecute("delete from hosts_groups where hostid=" ZBX_FS_UI64, hostid);

	/* delete host from template linkages */
	DBexecute("delete from hosts_templates where hostid=" ZBX_FS_UI64, hostid);

	/* disable actions */
	db_elements = DBselect("select distinct actionid from conditions "
		" where conditiontype=%i and value=" ZBX_FS_UI64,CONDITION_TYPE_HOST, hostid);

	while( (element_data = DBfetch(db_elements)) )
	{
		ZBX_STR2UINT64(elementid, element_data[0]);

		DBexecute("update actions set status=%s where actionid=" ZBX_FS_UI64, 
			ACTION_STATUS_DISABLED, elementid);
	}

	DBfree_result(db_elements);

	/* delete action conditions */
	DBexecute("delete from conditions where conditiontype=%i and value=" ZBX_FS_UI64, CONDITION_TYPE_HOST, hostid);

	/* delete host profile */
	DBexecute("delete from hosts_profiles where hostid=" ZBX_FS_UI64, hostid);

	/* delete host */
	DBexecute("delete from hosts where hostid=" ZBX_FS_UI64, hostid);

	return SUCCEED;
}
