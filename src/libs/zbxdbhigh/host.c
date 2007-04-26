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
			" where app.applicationid=ia.applicationid and ia.itemid=" ZBX_FS_UI64, itemid);

	while( i < (max_applications - 1) && (application_data = DBfetch(db_applications)) )
	{
		ZBX_STR2UINT64(applications[i], application_data[0]);
		i++;
	}

	DBfree_result(db_applications);

	applications[i] = 0;

	return i;
}

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
	}

	applications[j] = 0;

	return j;
}

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

static int	DBdelete_service(
		zbx_uint64_t serviceid
	)
{
	DBexecute("delete from services_links where servicedownid=" ZBX_FS_UI64 " or serviceupid=" ZBX_FS_UI64, serviceid, serviceid);
	DBexecute("delete from services where serviceid=" ZBX_FS_UI64, serviceid);
	
	return SUCCEED;
}

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

static int	delete_sysmaps_element(
		zbx_uint64_t selementid
	)
{
	DBexecute("delete from sysmaps_links where selementid1=" ZBX_FS_UI64 " or selementid2=" ZBX_FS_UI64, selementid, selementid);
	DBexecute("delete from sysmaps_elements where selementid=" ZBX_FS_UI64, selementid);

	return SUCCEED;
}

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
		if( SUCCEED != (result = delete_sysmaps_element(selementid)) )
			break;
	}

	DBfree_result(db_selements);

	return result;
}

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
		db_elements = DBselect("select triggerid from triggers where triggerid=" ZBX_FS_UI64, triggerid);

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
		" values (" ZBX_FS_UI64 ",'%s',%i,%i,%i,%i,%i,%i,%i,%i," ZBX_FS_UI64,
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

	zabbix_log(LOG_LEVEL_DEBUG, "Graph '%s' added", name);
	if( new_graphid )
		*new_graphid = graphid;

	return SUCCEED;
}

static int	DBdelete_graph(
		zbx_uint64_t graphid
	)
{
	DB_RESULT	db_graphs;
	DB_RESULT	db_elements;

	DB_ROW		graph_data;
	DB_ROW		element_data;

	int result = FAIL;

	db_graphs = DBselect("select name from graphs where graphid=" ZBX_FS_UI64, graphid);

	if( (graph_data = DBfetch(db_graphs)) )
	{
		/* first delete child graphs */
		db_elements = DBselect("select name graphid graphs where templateid=" ZBX_FS_UI64, graphid);

		while( (element_data = DBfetch(db_elements)) )
		{ /* recursion */
			ZBX_STR2UINT64(graphid, element_data[0]);
			if( SUCCEED != (result = DBdelete_graph(graphid)) )
				break;
		}

		DBfree_result(db_elements);

		if( SUCCEED == result )
		{ /* delete graph */
			DBexecute("delete from graphs_items where graphid=" ZBX_FS_UI64, graphid);
			DBexecute("delete from graphs where graphid=" ZBX_FS_UI64, graphid);

			zabbix_log( LOG_LEVEL_DEBUG, "Graph '%s' deleted", graph_data[0]);
		}
	}

	DBfree_result(db_graphs);

	return result;
}

static int	DBdelete_graph_item(
		zbx_uint64_t gitemid
	)
{
	DB_RESULT	db_graphs;
	DB_RESULT	db_chd_gitems;

	DB_ROW		graph_data;
	DB_ROW		chd_gitem_data;

	zbx_uint64_t
		graphid,
		templateid,
		chd_gitemid;

	int	result = FAIL;

	db_graphs = DBselect("select g.graphid,g.name,g.templateid from graphs g,graphs_ietms gi "
			" gi.gitemid=" ZBX_FS_UI64 " and gi.graphid=g.graphid ");

	if( (graph_data = DBfetch(db_graphs)) )
	{
		ZBX_STR2UINT64(graphid, graph_data[0]);
		ZBX_STR2UINT64(templateid, graph_data[2]);

		db_chd_gitems = DBselect("select distinct cgi.gitemid from graphs cg,graphs_items cgi,graphs_items tgi,items ci,items ti "
				" where tgi.gitemid=" ZBX_FS_UI64 " and cg.templateid=tgi.graphid and cgi.graphid=cg.graphid and "
				" cgi.drawtype=tgi.drawtype and cgi.sortorder=tgi.sortorder and cgi.color=tgi.color "
				" and cgi.yaxisside=tgi.yaxisside and cgi.itemid=ci.itemid and tgi.itemid=ti.itemid and ci.key_=ti.key_");

		while( (chd_gitem_data = DBfetch(db_chd_gitems)) )
		{
			ZBX_STR2UINT64(chd_gitemid, chd_gitem_data[0]);

			/* recursion */
			if( SUCCEED != (result = DBdelete_graph_item(chd_gitemid)) )
				break;
		}

		DBfree_result(db_chd_gitems);

		if( SUCCEED == result )
		{  /* delete graph items */
			DBexecute("delete from graphs_items where gitemid=" ZBX_FS_UI64, gitemid);

			zabbix_log(LOG_LEVEL_DEBUG,"Graphitem with ID [" ZBX_FS_UI64 "] deleted from graph '%s'", gitemid, graph_data[1]);

			db_chd_gitems = DBselect("select count(*) from graphs_items where graphid=" ZBX_FS_UI64, graphid);

			if( (chd_gitem_data = DBfetch(db_chd_gitems)) )
			{
				if( templateid > 0 && 0 == atoi(chd_gitem_data[0]))
				{ /* delete empty child graphs */
					return DBdelete_graph(graphid);
				}
			}

			DBfree_result(db_chd_gitems);
		}
	}

	DBfree_result(db_graphs);

	return result;
}

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

	int result = FAIL;

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
			DBexecute("delete from graphs_items where itemid=" ZBX_FS_UI64, itemid);
			DBexecute("delete from items_applications where itemid=" ZBX_FS_UI64, itemid);
			DBexecute("delete from items where itemid=" ZBX_FS_UI64, itemid);

			zabbix_log( LOG_LEVEL_DEBUG, "Item '%s:%s' deleted", item_data[2], item_data[1]);
		}
	}

	DBfree_result(db_items);

	return result;
}

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
                        " where g.graphid=gi.graphid and gi.itemid=i.itemid and i.hostid=" ZBX_FS_UI64, hostid);

	while( (graph_data = DBfetch(db_graphs)) )
	{
		ZBX_STR2UINT64(g_templateid, graph_data[1]);
		
		if( 0 == g_templateid )
			continue;

		if( 0 != templateid )
		{
			db_tmp_hosts = DBselect("select distinct h.hostid from graphs_items gi, items i, hosts h"
						" where h.hostid=i.hostid and gi.itemid=i.itemid and gi.graphid=" ZBX_FS_UI64, g_templateid);

			tmp_host_data = DBfetch(db_tmp_hosts);

			ZBX_STR2UINT64(tmp_hostid, tmp_host_data[0]);

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
			db_tmp_hosts = DBselect("select distinct h.hostid from hosts h, functions f, items i"
						" where i.itemid=f.itemid and h.hostid=i.hostid and f.triggerid=" ZBX_FS_UI64, t_templateid);

			tmp_host_data = DBfetch(db_tmp_hosts);

			ZBX_STR2UINT64(tmp_hostid, tmp_host_data[0]);

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

			tmp_host_data = DBfetch(db_tmp_hosts);

			ZBX_STR2UINT64(tmp_hostid, tmp_host_data[0]);

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
			db_tmp_hosts = DBselect("select applicationid,templateid,name from applications where hostid=" ZBX_FS_UI64, a_templateid);

			tmp_host_data = DBfetch(db_tmp_hosts);

			ZBX_STR2UINT64(tmp_hostid, tmp_host_data[0]);

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

	zbx_uint64_t
		applicationid_new,
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
		SDI("DBcopy_template_applications - 1");
		db_elements = DBselect("select applicationid,name from application where hostid=" ZBX_FS_UI64, templateid);
		SDI("DBcopy_template_applications - 2");
		
		while( (element_data = DBfetch(db_elements)) )
		{
		SDI("DBcopy_template_applications - 3");
			ZBX_STR2UINT64(elementid, element_data[0]);
			if( SUCCEED != (result = DBadd_application(element_data[1], hostid, copy_mode ? 0 : elementid)) )
				break;
		SDI("DBcopy_template_applications - 4");
		}
		SDI("DBcopy_template_applications - 5");

		DBfree_result(db_elements);
		SDI("DBcopy_template_applications - 6");
	}

	return result;
}

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
		del_itemid,
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
	return result;
}

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

	db_hosts = DBselect("select host from hosts where hostid" ZBX_FS_UI64, hostid);

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
			db_chd_hosts = DBselect("select hostid from hosts where templateid=" ZBX_FS_UI64, hostid);

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
							element_data[0],	/* description */
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
	return result;
}

static char*	DBimplode_exp (
		char		*expression,
		zbx_uint64_t	triggerid
	)
{
	typedef enum {
		EXP_NONE = 0,
		EXP_HOST,
		EXP_KEY,
		EXP_FUNCTION,
		EXP_PARAMETER
	} expression_parsing_states;

	DB_RESULT	db_items;

	DB_ROW		item_data;

	zbx_uint64_t
		functionid,
		itemid;

	int	state = EXP_NONE;
	char	*exp = NULL;

	int
		host_len = 0,
		key_len = 0,
		function_len = 0,
		parameter_len = 0;

	char	tmp_c,
		*sql = NULL,
		*host = NULL,
		*key = NULL,
		*function = NULL,
		*parameter = NULL,
		*str_esc = NULL;

	register char *c;

	/* Translate localhost:procload.last(0)>10 to {12}>10 */

	for ( c = expression; c && *c; c++ )
	{
		if( '{' == *c && EXP_NONE == state )
		{
			state 		= EXP_HOST;

			host		= c+1;
			key		= NULL;
			function	= NULL;
			parameter	= NULL;

			host_len	= 0;
			key_len		= 0;
			function_len	= 0;
			parameter_len	= 0;
			continue;
		}
		if( '}' == *c && EXP_HOST == state )
		{ /* Processing of macros: {TRIGGER.VALUE} */
			state = EXP_NONE;

			if( host && host_len )
			{
				tmp_c = host[host_len];
				host[host_len] = '\0';
				exp = zbx_strdcatf(exp, "{%s}", host);
				host[host_len] = tmp_c;
			}
			host = NULL;
			continue;
		}
		if( '}' == *c && EXP_NONE == state )
		{
			if( host && host_len && key && key_len && function && function_len && parameter && parameter_len )
			{
				sql = zbx_strdcat(NULL, "select distinct i.itemid from items i,hosts h where h.hostid=i.hostid ");
				/* adding host */
				tmp_c = host[host_len];
				host[host_len] = '\0';
				str_esc = DBdyn_escape_string(host);
				sql = zbx_strdcatf(sql, " and h.host='%s'", str_esc);
				zbx_free(str_esc);
				host[host_len] = tmp_c;

				/* adding key */
				tmp_c = key[key_len];
				key[key_len] = '\0';
				str_esc = DBdyn_escape_string(key);
				sql = zbx_strdcatf(sql, " and i.key_='%s'", str_esc);
				zbx_free(str_esc);
				key[key_len] = tmp_c;

				db_items = DBselect(sql);

				zbx_free(sql);

				if( (item_data = DBfetch(db_items)) )
				{
					ZBX_STR2UINT64(itemid, item_data[0]);

					functionid = DBget_maxid("functions","functionid");

					sql = zbx_dsprintf(NULL, "insert into functions (functionid,itemid,triggerid,function,parameter)"
							" values (" ZBX_FS_UI64 "," ZBX_FS_UI64 "," ZBX_FS_UI64 ",",
							functionid, itemid, triggerid);
					/* adding function */
					tmp_c = function[function_len];
					function[function_len] = '\0';
					str_esc = DBdyn_escape_string(function);
					sql = zbx_strdcatf(sql, "'%s',", str_esc);
					zbx_free(str_esc);
					function[function_len] = tmp_c;
					/* adding parameter */
					tmp_c = parameter[parameter_len];
					parameter[parameter_len] = '\0';
					str_esc = DBdyn_escape_string(parameter);
					sql = zbx_strdcatf(sql, "'%s')", str_esc);
					zbx_free(str_esc);
					parameter[parameter_len] = tmp_c;

					DBexecute(sql);
					
					exp = zbx_strdcatf(exp, "{" ZBX_FS_UI64 "}", functionid);

					zbx_free(sql);
				}

				DBfree_result(db_items);
			}

			continue;
		}
		if( '(' == *c && EXP_FUNCTION == state )
		{
			state = EXP_PARAMETER;

			parameter = c+1;
			parameter_len = 0;
			continue;
		}
		if( ')' == *c && EXP_PARAMETER == state)
		{
			state = EXP_NONE;
			continue;
		}
		if( ':' == *c && EXP_HOST == state )
		{
			state = EXP_KEY;

			key = c+1;
			key_len = 0;
			continue;
		}
		if( '.' == *c )
		{
			if( EXP_KEY == state )
			{
				state = EXP_FUNCTION;

				function = c+1;
				function_len = 0;
				continue;
			}
			/* Support for '.' in KEY */
			if( EXP_FUNCTION == state )
			{
				key_len += function_len + 1;

				function = c+1;
				function_len = 0;
				continue;
			}
		}
		switch( state )
		{
			case EXP_HOST:		host_len++;				break;
			case EXP_KEY:		key_len++;				break;
			case EXP_FUNCTION:	function_len++;				break;
			case EXP_PARAMETER:	parameter_len++;			break;
			default:		exp = zbx_strdcatf(exp, "%c", *c);	break;

		}
	}

	return exp;
}

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
		*short_expression = NULL,
		*sql = NULL,
		*str_esc = NULL;

	int	i = 0;

	db_triggers = DBselect("select distinct t.description,h.host from triggers t,functions f,items i,hosts h "
		       " where t.triggerid=" ZBX_FS_UI64 " and f.triggerid=t.triggerid "
		       " and i.itemid=f.itemid and i.hostid=h.hostid", triggerid);

	if( (trigger_data = DBfetch(db_triggers)) )
	{
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

				replace = zbx_dsprintf(replace, "{%s:", db_chd_hosts[0] /* child host */);

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

		zbx_free(sql);

		DBexecute(sql);

		DBreset_items_nextcheck(triggerid);

		DBadd_event(triggerid,TRIGGER_VALUE_UNKNOWN,0);

		DBdelete_dependencies_by_triggerid(triggerid);

		for( i=0; 0 < dependences[i]; i++ )
		{
			DBinsert_dependency(triggerid, dependences[i]);
		}

		zabbix_log(LOG_LEVEL_DEBUG, "Trigger '%s' updated", trigger_data[0]);;
	}

	DBfree_result(db_triggers);

	return SUCCEED;
}

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
			" and i1.itemid=f1.itemid and i2.f2.itemid and f1.triggerid=" ZBX_FS_UI64 " and f2.triggerid=" ZBX_FS_UI64,
				triggerid1, triggerid2);

			while( (function_data = DBfetch(db_functions)) )
			{
				search = zbx_dsprintf(NULL, "{%s}", function_data[0]);
				replace = zbx_dsprintf(NULL, "{%s}", function_data[1]);

				expr = string_replace(expr, search, replace);

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
		ht_templateid,
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

		db_host_triggers = DBselect("select distinct t.triggerid,t.templateid from funtions f,items i "
				" where t.triggerid=f.triggerid and i.itemid=f.functionid and i.hostid=" ZBX_FS_UI64, hostid);

		while( (host_trigger_data = DBfetch(db_host_triggers)) )
		{
			ZBX_STR2UINT64(h_triggerid, host_trigger_data[0]);
			ZBX_STR2UINT64(ht_templateid, host_trigger_data[1]);

			if( 0 != ht_templateid )			continue;
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

			new_triggerid = DBget_maxid("triggers","triggerid");

			description_esc = DBdyn_escape_string(trigger_data[0]);
			comments_esc = DBdyn_escape_string(trigger_data[3]);
			url_esc = DBdyn_escape_string(trigger_data[4]);

			DBexecute("insert into triggers"
				" (triggerid,description,priority,status,comments,url,value,expression,templateid)"
				" values (" ZBX_FS_UI64 ",'%s',%i,%i,'%s','%s',2,'{???:???}'," ZBX_FS_UI64,
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
				}
				else
				{
					zabbix_log(LOG_LEVEL_DEBUG, "Missing similar key [" ZBX_FS_UI64 "] for host [" ZBX_FS_UI64 "]",
							itemid, hostid);
					result = FALSE;
				}

				DBfree_result(db_items);

				zbx_free(search);

				zbx_free(parameter_esc);
				zbx_free(function_esc);

			}

			zbx_free(new_expression);

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
				db_chd_hosts = DBselect("select hostid from hosts where templateid=" ZBX_FS_UI64, hostid);

				while( (chd_host_data = DBfetch(db_chd_hosts)) )
				{
					ZBX_STR2UINT64(chd_hostid, chd_host_data[0]);

					/* recursion */
					if( SUCCEED != (result = DBcopy_trigger_to_host(new_triggerid, chd_hostid, copy_mode)) )
						break;
				}

				DBfree_result(db_chd_hosts);
			}
		}
	}

	DBfree_result(db_triggers);

	return result;
}

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
	DB_RESULT	db_elements;
	DB_RESULT	db_graphs;

	DB_ROW		element_data;
	DB_ROW		graph_data;

	zbx_uint64_t
		new_graphid,
		new_itemid,
		gitemid;

	char	*color_esc = NULL;

	int	item_num,
		result = SUCCEED;

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

	/* add to child graphs */
		
	db_elements = DBselect("select count(*) as num from graphs_items where graphid=" ZBX_FS_UI64);
	if( (element_data = DBfetch(db_elements)) )
	{
		item_num = atoi(element_data[0]);
	}
	DBfree_result(db_elements);

	if( 1 == item_num )
	{ /* create graphs for childs with item */

		db_graphs = DBselect("select name,width,height,yaxistype,yaxismin,yaxismax,"
					" show_work_period,show_triggers,graphtype from graphs"
					" where graphid=" ZBX_FS_UI64, graphid);

		if( (graph_data == DBfetch(db_graphs)) )
		{
			db_elements = DBselect("select i.itemid from items i, hosts h, items i2 "
				" where i2.itemid=" ZBX_FS_UI64 " and i.key=i2.key_ and i.hostid=h.hostid and h.templateid=i2.hostid", itemid);

			while( (element_data = DBfetch(db_elements)) )
			{
				ZBX_STR2UINT64(new_itemid, element_data[0]);

				if( SUCCEED == (result = DBadd_graph(
						&new_graphid,		/* [out] graphid */
						graph_data[0],		/* name */
						atoi(graph_data[1]),	/* width */
						atoi(graph_data[2]),	/* height */
						atoi(graph_data[3]),	/* yaxistype */
						atoi(graph_data[4]),	/* yaxismin */
						atoi(graph_data[5]),	/* yaxismax */
						atoi(graph_data[6]),	/* show_work_period */
						atoi(graph_data[7]),	/* show_triggers */
						atoi(graph_data[8]),	/* graphtype */
						graphid)) )		/* templateid */
				{
					/* recursion */
					result = DBadd_item_to_graph(
							new_graphid,
							new_itemid,
							color,
							drawtype,
							sortorder,
							yaxisside,
							calc_fnc,
							type,
							periods_cnt);
				}

				if( SUCCEED != result )
					break;
			}

			DBfree_result(db_elements);
		}
		DBfree_result(db_graphs);
	}
	else
	{ /* copy items to childs */

		db_elements = DBselect("select g.graphid,i.itemid from graphs g, items i, graphs_items gi, items i2 "
					" where g.templateid=" ZBX_FS_UI64 " and gi.graphid=g.graphid "
					" and gi.itemid=i.itemid and i.key_=i2.key_ and i2.itemid=" ZBX_FS_UI64,
					graphid, itemid);

		while( (element_data = DBfetch(db_elements)) )
		{
			ZBX_STR2UINT64(new_graphid, element_data[0]);
			ZBX_STR2UINT64(new_itemid, element_data[1]);

			/* recursion */
			if( SUCCEED != (result = DBadd_item_to_graph(
					new_graphid,
					new_itemid,
					color,
					drawtype,
					sortorder,
					yaxisside,
					calc_fnc,
					type,
					periods_cnt)) )
				break;
		}

		DBfree_result(db_elements);

	}

	if( SUCCEED == result)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Added graph item with ID [" ZBX_FS_UI64 "]", gitemid);
		/* result = gitemid; // skip fo C version */
	}
	else
	{
		DBdelete_graph_item(gitemid);
	}

	return result;
}

static int	DBcopy_graphitems_for_host(
		zbx_uint64_t	src_graphid,
		zbx_uint64_t	dist_graphid,
		zbx_uint64_t	hostid
	)
{
	DB_RESULT	db_src_graphitems;
	DB_RESULT	db_items;

	DB_ROW		src_graphitem_data;
	DB_ROW		item_data;

	zbx_uint64_t
		itemid;

	char	*key_esc = NULL;

	int	result = SUCCEED;

	db_src_graphitems = DBselect("select i.key_,gi.color,gi.drawtype,gi.sortorder,gi.sortorder,"
					"gi.yaxisside,gi.calc_fnc,gi.type,gi.periods_cnt "
					" from graphs_items gi,itemsi where graphid=" ZBX_FS_UI64
					" and gi.itemid=i.itemid "
					" order by itemid,drawtype,sortorder,color,yaxisside,key_");

	while( (src_graphitem_data = DBfetch(db_src_graphitems)) )
	{
		key_esc = DBdyn_escape_string(src_graphitem_data[0]);

		db_items = DBselect("select itemid from items where hostid=" ZBX_FS_UI64 " and key_='%s'", hostid, key_esc);

		zbx_free(key_esc);

		if( (item_data = DBfetch(db_items)) )
		{
			ZBX_STR2UINT64(itemid, item_data[0]);

			result = DBadd_item_to_graph(
				dist_graphid,
				itemid,
				src_graphitem_data[1],
				atoi(src_graphitem_data[2]),
				atoi(src_graphitem_data[3]),
				atoi(src_graphitem_data[4]),
				atoi(src_graphitem_data[5]),
				atoi(src_graphitem_data[6]),
				atoi(src_graphitem_data[7]));
		}
		else
		{
			result = FAIL;

			zabbix_log(LOG_LEVEL_DEBUG, "Not found item with key '%s' for host [" ZBX_FS_UI64 "]",
					src_graphitem_data[0], hostid);
		}

		DBfree_result(db_items);

		if( SUCCEED != result )
			break;
	}

	DBfree_result(db_src_graphitems);

	return result;
}

static int	DBcopy_graph_to_host(
		zbx_uint64_t	graphid,
		zbx_uint64_t	hostid,
		unsigned char copy_mode
	)
{
	DB_RESULT	db_graphs;

	DB_ROW		graph_data;

	zbx_uint64_t
		new_graphid = 0;

	int	result = SUCCEED;

	db_graphs = DBselect("select name,width,height,yaxistype,yaxismin,yaxismax,show_work_period,show_triggers,graphtype "
			" from graphs where graphid=" ZBX_FS_UI64, graphid);

	if( (graph_data = DBfetch(db_graphs)) )
	{
		if( SUCCEED == (result = DBadd_graph(
				&new_graphid,		/* [out] graphid */
				graph_data[0],		/* name */
				atoi(graph_data[1]),	/* width */
				atoi(graph_data[2]),	/* height */
				atoi(graph_data[3]),	/* yaxistype */
				atoi(graph_data[4]),	/* yaxismin */
				atoi(graph_data[5]),	/* yaxismax */
				atoi(graph_data[6]),	/* show_work_period */
				atoi(graph_data[7]),	/* show_triggers */
				atoi(graph_data[8]),	/* graphtype */
				copy_mode ? 0 : graphid)) )
			result = DBcopy_graphitems_for_host(graphid, new_graphid, hostid);

		if( SUCCEED != result && 0 < new_graphid)
		{
			DBdelete_graph(new_graphid);
		}
	}

	DBfree_result(db_graphs);

	return result;
}

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

	int	result;

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


/* public */ void	DBdelete_template_elements(
		zbx_uint64_t  hostid,
		zbx_uint64_t templateid,
		unsigned char unlink_mode
	)
{
	SDI("DBdelete_template_elements - 1");
	DBdelete_template_graphs(hostid, templateid, unlink_mode);
	SDI("DBdelete_template_elements - 2");
	DBdelete_template_triggers(hostid, templateid, unlink_mode);
	SDI("DBdelete_template_elements - 3");
	DBdelete_template_items(hostid, templateid, unlink_mode);
	SDI("DBdelete_template_elements - 4");
	DBdelete_template_applications(hostid, templateid, unlink_mode);
	SDI("DBdelete_template_elements - 5");
}

/* public */ int	DBcopy_template_elements(
		zbx_uint64_t hostid,
		zbx_uint64_t templateid,
		unsigned char copy_mode
	)
{
	int result = SUCCEED;

	SDI("DBcopy_template_elements - 1");
	if(SUCCEED == (result = DBcopy_template_applications(hostid, templateid, copy_mode)) )
	{
	SDI("DBcopy_template_elements - 2");
	if(SUCCEED == (result = DBcopy_template_items(hostid, templateid, copy_mode)) )
	{
	SDI("DBcopy_template_elements - 3");
	if(SUCCEED == (result = DBcopy_template_triggers(hostid, templateid, copy_mode)) )
	{
	SDI("DBcopy_template_elements - 4");
		result = DBcopy_template_graphs(hostid, templateid, copy_mode);
	}
	}
	}
	SDI("DBcopy_template_elements - 5");

	return result;
}

/* public */ int	DBsync_host_with_template(
		zbx_uint64_t hostid,
		zbx_uint64_t templateid
	)
{
	DBdelete_template_elements(hostid, templateid, 0 /* not a unlink mode */);

	return DBcopy_template_elements(hostid, templateid, 0 /* not a copy mode */);
}

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
