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

int	DBadd_graph(char *name, int width, int height, int yaxistype, double yaxismin, double yaxismax)
{
	int	graphid;
	char	name_esc[GRAPH_NAME_LEN_MAX];
	int	exec_res;

	DBescape_string(name,name_esc,GRAPH_NAME_LEN_MAX);

	if(FAIL == (exec_res = DBexecute("insert into graphs (name,width,height,yaxistype,yaxismin,yaxismax) values ('%s',%d,%d,%d,%f,%f)", name_esc, width, height, yaxistype, yaxismin, yaxismax)))
	{
		return FAIL;
	}

	graphid = DBinsert_id(exec_res, "graphs", "graphid");

	if(graphid==0)
	{
		return FAIL;
	}

	return graphid;
}

int	DBadd_item_to_graph(int graphid,int itemid, char *color,int drawtype, int sortorder)
{
	int	gitemid;
	char	color_esc[GRAPH_ITEM_COLOR_LEN_MAX];
	int	exec_res;

	DBescape_string(color,color_esc,GRAPH_ITEM_COLOR_LEN_MAX);

	if(FAIL == (exec_res = DBexecute("insert into graphs_items (graphid,itemid,drawtype,sortorder,color) values (%d,%d,%d,%d,'%s')", graphid, itemid, drawtype, sortorder, color_esc)))
	{
		return FAIL;
	}

	gitemid = DBinsert_id(exec_res, "graphs_items", "gitemid");

	if(gitemid==0)
	{
		return FAIL;
	}

	return gitemid;
}

int	DBget_graph_item_by_gitemid(int gitemid, DB_GRAPH_ITEM *graph_item)
{
	DB_RESULT	result;
	DB_ROW		row;
	int	ret = SUCCEED;

	zabbix_log( LOG_LEVEL_DEBUG, "In DBget_graph_item_by_gitemid(%d)", gitemid);

	result = DBselect("select gitemid, graphid, itemid, drawtype, sortorder, color from graphs_items where gitemid=%d", gitemid);
	row=DBfetch(result);

	if(!row)
	{
		ret = FAIL;
	}
	else
	{
		graph_item->gitemid=atoi(row[0]);
		graph_item->graphid=atoi(row[1]);
		graph_item->itemid=atoi(row[2]);
		graph_item->drawtype=atoi(row[3]);
		graph_item->sortorder=atoi(row[4]);
		strscpy(graph_item->color,row[5]);
	}

	DBfree_result(result);

	return ret;
}

int	DBget_graph_by_graphid(int graphid, DB_GRAPH *graph)
{
	DB_RESULT	result;
	DB_ROW		row;
	int	ret = SUCCEED;

	zabbix_log( LOG_LEVEL_DEBUG, "In DBget_graph_by_graphid(%d)", graphid);

	result = DBselect("select graphid,name,width,height,yaxistype,yaxismin,yaxismax from graphs where graphid=%d", graphid);
	row=DBfetch(result);

	if(!row)
	{
		ret = FAIL;
	}
	else
	{
		graph->graphid=atoi(row[0]);
		strscpy(graph->name,row[1]);
		graph->width=atoi(row[2]);
		graph->height=atoi(row[3]);
		graph->yaxistype=atoi(row[4]);
		graph->yaxismin=atof(row[5]);
		graph->yaxismax=atof(row[6]);
	}

	DBfree_result(result);

	return ret;
}

int	DBadd_graph_item_to_linked_hosts(int gitemid,int hostid)
{
	DB_HOST	host;
	DB_ITEM	item;
	DB_GRAPH_ITEM	graph_item;
	DB_GRAPH	graph;
	DB_RESULT	result;
	DB_RESULT	result2;
	DB_ROW		row;
	DB_ROW		row2;
	char	name_esc[GRAPH_NAME_LEN_MAX];
	int	graphid;
	int	itemid;
	int	rows;

	zabbix_log( LOG_LEVEL_DEBUG, "In DBadd_graph_item_to_linked_hosts(%d,%d)", gitemid, hostid);

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
		result = DBselect("select hostid,templateid,graphs from hosts_templates where templateid=%d", item.hostid);
	}
	else
	{
		result = DBselect("select hostid,templateid,graphs from hosts_templates where hostid=%d and templateid=%d", hostid, item.hostid);
	}

	while((row=DBfetch(result)))
	{
		if( (atoi(row[2])&1) == 0)	continue;

		result2 = DBselect("select i.itemid from items i where i.key_='%s' and i.hostid=%d", item.key, atoi(row[0]));

		row2=DBfetch(result2);

		if(!row2)
		{
			DBfree_result(result2);
			continue;
		}

		itemid=atoi(row2[0]);
		DBfree_result(result2);

		DBescape_string(graph.name,name_esc,GRAPH_NAME_LEN_MAX);

		if(DBget_host_by_hostid(atoi(row[0]), &host) == FAIL)	continue;

		result2 = DBselect("select distinct g.graphid from graphs g,graphs_items gi,items i where i.itemid=gi.itemid and i.hostid=%d and g.graphid=gi.graphid and g.name='%s'", atoi(row[0]), name_esc);

		rows=0;
		while((row2=DBfetch(result2)))
		{
			DBadd_item_to_graph(atoi(row2[0]),itemid,graph_item.color,graph_item.drawtype,graph_item.sortorder);
			rows++;
		}
		if(rows==0)
		{
			graphid=DBadd_graph(graph.name,graph.width,graph.height,graph.yaxistype,graph.yaxismin,graph.yaxismax);
			if(graphid!=FAIL)
			{
				DBadd_item_to_graph(graphid,itemid,graph_item.color,graph_item.drawtype,graph_item.sortorder);
			}
		}
		DBfree_result(result2);
	}
	DBfree_result(result);

	return SUCCEED;
}
