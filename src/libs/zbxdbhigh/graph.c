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


#include <stdlib.h>
#include <stdio.h>

#include <string.h>
#include <strings.h>

#include "db.h"
#include "log.h"
#include "zlog.h"
#include "common.h"

int	DBget_graph_item_by_gitemid(int gitemid, DB_GRAPH_ITEM *graph_item)
{
	DB_RESULT	*result;
	char	sql[MAX_STRING_LEN];
	int	ret = SUCCEED;

	zabbix_log( LOG_LEVEL_WARNING, "In DBget_graph_item_by_gitemid(%d)", gitemid);

	snprintf(sql,sizeof(sql)-1,"select gitemid, graphid, itemid, drawtype, sortorder, color from graphs_items where gitemid=%d", gitemid);
	result=DBselect(sql);

	if(DBnum_rows(result)==0)
	{
		ret = FAIL;
	}
	else
	{
		graph_item->gitemid=atoi(DBget_field(result,0,0));
		graph_item->graphid=atoi(DBget_field(result,0,1));
		graph_item->itemid=atoi(DBget_field(result,0,2));
		graph_item->drawtype=atoi(DBget_field(result,0,3));
		graph_item->sortorder=atoi(DBget_field(result,0,4));
		strscpy(graph_item->color,DBget_field(result,0,5));
	}

	DBfree_result(result);

	return ret;
}

int	DBadd_graph_item_to_linked_hosts(int gitemid,int hostid)
{
	DB_ITEM	item;
	DB_GRAPH_ITEM	graph_item;
	DB_GRAPH	graph;
	DB_RESULT	*result,*result2,*result3;
	char	sql[MAX_STRING_LEN];
	int	ret = SUCCEED;
	int	i;

	zabbix_log( LOG_LEVEL_WARNING, "In DBadd_graph_item_to_linked_hosts(%d,%d)", gitemid, hostid);

	if(DBget_graph_item_by_gitemid(gitemid, &graph_item)==FAIL)
	{
		return FAIL;
	}

	if(DBget_graph_by_graphid(graph_item.graphid, &graph)==FAIL)
	{
		return FAIL;
	}

	if(DBget_item_by_itemid(graph_item.itemid, &item)==FAIL)
	{
		return FAIL;
	}

	if(hostid==0)
	{
		snprintf(sql,sizeof(sql)-1,"select hostid,templateid,graphs from hosts_templates where templateid=%d", item.hostid);
	}
	else
	{
		snprintf(sql,sizeof(sql)-1,"select hostid,templateid,graphs from hosts_templates where hostid=%d and templateid=%d", hostid, item.hostid);
	}

	result=DBselect(sql);
	for(i=0;i<DBnum_rows(result);i++)
	{
		if(atoi(DBget_field(result,i,2))&1 == 0)	continue;

		snprintf(sql,sizeof(sql)-1,"select i.itemid from items i where i.key_='%s' and i.hostid=%d", item.key, atoi(DBget_field(result,i,0)));

		result2=DBselect(sql);
		if(DBnum_rows(result2)==0)
		{
			DBfree_result(result2);
			continue;
		}

		$row2=DBfetch($result2);
		$itemid=$row2["itemid"];

		$sql="select distinct g.graphid from graphs g,graphs_items gi,items i where i.itemid=gi.itemid and i.hostid=".$row["hostid"]." and g.graphid=gi.graphid and g.name='".addslashes($graph["name"])."'";
		$result2=DBselect($sql);
		$host=get_host_by_hostid($row["hostid"]);
		while($row2=DBfetch($result2))
		{
			add_item_to_graph($row2["graphid"],$itemid,$graph_item["color"],$graph_item["drawtype"],$graph_item["sortorder"]);
			info("Added graph element to graph ".$graph["name"]." of linked host ".$host["host"]);
		}
		if(DBnum_rows($result2)==0)
		{
			$graphid=add_graph($graph["name"],$graph["width"],$graph["height"],$graph["yaxistype"],$graph["yaxismin"],$graph["yaxismax"]);
			info("Added graph ".$graph["name"]." of linked host ".$host["host"]);
			add_item_to_graph($graphid,$itemid,$graph_item["color"],$graph_item["drawtype"],$graph_item["sortorder"]);
			info("Added graph element to graph ".$graph["name"]." of linked host ".$host["host"]);
		}
	}
}
