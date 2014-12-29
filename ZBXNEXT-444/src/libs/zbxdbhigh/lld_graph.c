/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

#include "lld.h"
#include "db.h"
#include "log.h"
#include "zbxalgo.h"
#include "zbxserver.h"

typedef struct
{
	zbx_uint64_t		graphid;
	char			*name;
	char			*name_orig;
	zbx_uint64_t		ymin_itemid;
	zbx_uint64_t		ymax_itemid;
	zbx_vector_ptr_t	gitems;
#define ZBX_FLAG_LLD_GRAPH_UNSET			__UINT64_C(0x00000000)
#define ZBX_FLAG_LLD_GRAPH_DISCOVERED			__UINT64_C(0x00000001)
#define ZBX_FLAG_LLD_GRAPH_UPDATE_NAME			__UINT64_C(0x00000002)
#define ZBX_FLAG_LLD_GRAPH_UPDATE_WIDTH			__UINT64_C(0x00000004)
#define ZBX_FLAG_LLD_GRAPH_UPDATE_HEIGHT		__UINT64_C(0x00000008)
#define ZBX_FLAG_LLD_GRAPH_UPDATE_YAXISMIN		__UINT64_C(0x00000010)
#define ZBX_FLAG_LLD_GRAPH_UPDATE_YAXISMAX		__UINT64_C(0x00000020)
#define ZBX_FLAG_LLD_GRAPH_UPDATE_SHOW_WORK_PERIOD	__UINT64_C(0x00000040)
#define ZBX_FLAG_LLD_GRAPH_UPDATE_SHOW_TRIGGERS		__UINT64_C(0x00000080)
#define ZBX_FLAG_LLD_GRAPH_UPDATE_GRAPHTYPE		__UINT64_C(0x00000100)
#define ZBX_FLAG_LLD_GRAPH_UPDATE_SHOW_LEGEND		__UINT64_C(0x00000200)
#define ZBX_FLAG_LLD_GRAPH_UPDATE_SHOW_3D		__UINT64_C(0x00000400)
#define ZBX_FLAG_LLD_GRAPH_UPDATE_PERCENT_LEFT		__UINT64_C(0x00000800)
#define ZBX_FLAG_LLD_GRAPH_UPDATE_PERCENT_RIGHT		__UINT64_C(0x00001000)
#define ZBX_FLAG_LLD_GRAPH_UPDATE_YMIN_TYPE		__UINT64_C(0x00002000)
#define ZBX_FLAG_LLD_GRAPH_UPDATE_YMIN_ITEMID		__UINT64_C(0x00004000)
#define ZBX_FLAG_LLD_GRAPH_UPDATE_YMAX_TYPE		__UINT64_C(0x00008000)
#define ZBX_FLAG_LLD_GRAPH_UPDATE_YMAX_ITEMID		__UINT64_C(0x00010000)
#define ZBX_FLAG_LLD_GRAPH_UPDATE									\
		(ZBX_FLAG_LLD_GRAPH_UPDATE_NAME | ZBX_FLAG_LLD_GRAPH_UPDATE_WIDTH |			\
		ZBX_FLAG_LLD_GRAPH_UPDATE_HEIGHT | ZBX_FLAG_LLD_GRAPH_UPDATE_YAXISMIN |			\
		ZBX_FLAG_LLD_GRAPH_UPDATE_YAXISMAX | ZBX_FLAG_LLD_GRAPH_UPDATE_SHOW_WORK_PERIOD |	\
		ZBX_FLAG_LLD_GRAPH_UPDATE_SHOW_TRIGGERS | ZBX_FLAG_LLD_GRAPH_UPDATE_GRAPHTYPE |		\
		ZBX_FLAG_LLD_GRAPH_UPDATE_SHOW_LEGEND | ZBX_FLAG_LLD_GRAPH_UPDATE_SHOW_3D |		\
		ZBX_FLAG_LLD_GRAPH_UPDATE_PERCENT_LEFT | ZBX_FLAG_LLD_GRAPH_UPDATE_PERCENT_RIGHT |	\
		ZBX_FLAG_LLD_GRAPH_UPDATE_YMIN_TYPE | ZBX_FLAG_LLD_GRAPH_UPDATE_YMIN_ITEMID |		\
		ZBX_FLAG_LLD_GRAPH_UPDATE_YMAX_TYPE | ZBX_FLAG_LLD_GRAPH_UPDATE_YMAX_ITEMID)
	zbx_uint64_t		flags;
}
zbx_lld_graph_t;

typedef struct
{
	zbx_uint64_t		gitemid;
	zbx_uint64_t		itemid;
	char			*color;
	int			sortorder;
	unsigned char		drawtype;
	unsigned char		yaxisside;
	unsigned char		calc_fnc;
	unsigned char		type;
#define ZBX_FLAG_LLD_GITEM_UNSET			__UINT64_C(0x0000)
#define ZBX_FLAG_LLD_GITEM_DISCOVERED			__UINT64_C(0x0001)
#define ZBX_FLAG_LLD_GITEM_UPDATE_ITEMID		__UINT64_C(0x0002)
#define ZBX_FLAG_LLD_GITEM_UPDATE_DRAWTYPE		__UINT64_C(0x0004)
#define ZBX_FLAG_LLD_GITEM_UPDATE_SORTORDER		__UINT64_C(0x0008)
#define ZBX_FLAG_LLD_GITEM_UPDATE_COLOR			__UINT64_C(0x0010)
#define ZBX_FLAG_LLD_GITEM_UPDATE_YAXISSIDE		__UINT64_C(0x0020)
#define ZBX_FLAG_LLD_GITEM_UPDATE_CALC_FNC		__UINT64_C(0x0040)
#define ZBX_FLAG_LLD_GITEM_UPDATE_TYPE			__UINT64_C(0x0080)
#define ZBX_FLAG_LLD_GITEM_UPDATE								\
		(ZBX_FLAG_LLD_GITEM_UPDATE_ITEMID | ZBX_FLAG_LLD_GITEM_UPDATE_DRAWTYPE |	\
		ZBX_FLAG_LLD_GITEM_UPDATE_SORTORDER | ZBX_FLAG_LLD_GITEM_UPDATE_COLOR |		\
		ZBX_FLAG_LLD_GITEM_UPDATE_YAXISSIDE | ZBX_FLAG_LLD_GITEM_UPDATE_CALC_FNC |	\
		ZBX_FLAG_LLD_GITEM_UPDATE_TYPE)
#define ZBX_FLAG_LLD_GITEM_DELETE			__UINT64_C(0x0100)
	zbx_uint64_t		flags;
}
zbx_lld_gitem_t;

typedef struct
{
	zbx_uint64_t	itemid;
	unsigned char	flags;
}
zbx_lld_item_t;

static void	lld_item_free(zbx_lld_item_t *item)
{
	zbx_free(item);
}

static void	lld_items_free(zbx_vector_ptr_t *items)
{
	while (0 != items->values_num)
		lld_item_free((zbx_lld_item_t *)items->values[--items->values_num]);
}

static void	lld_gitem_free(zbx_lld_gitem_t *gitem)
{
	zbx_free(gitem->color);
	zbx_free(gitem);
}

static void	lld_gitems_free(zbx_vector_ptr_t *gitems)
{
	while (0 != gitems->values_num)
		lld_gitem_free((zbx_lld_gitem_t *)gitems->values[--gitems->values_num]);
}

static void	lld_graph_free(zbx_lld_graph_t *graph)
{
	lld_gitems_free(&graph->gitems);
	zbx_vector_ptr_destroy(&graph->gitems);
	zbx_free(graph->name_orig);
	zbx_free(graph->name);
	zbx_free(graph);
}

static void	lld_graphs_free(zbx_vector_ptr_t *graphs)
{
	while (0 != graphs->values_num)
		lld_graph_free((zbx_lld_graph_t *)graphs->values[--graphs->values_num]);
}

/******************************************************************************
 *                                                                            *
 * Function: lld_graphs_get                                                   *
 *                                                                            *
 * Purpose: retrieve graphs which were created by the specified graph         *
 *          prototype                                                         *
 *                                                                            *
 * Parameters: parent_graphid - [IN] graph prototype identificator            *
 *             graphs         - [OUT] sorted list of graphs                   *
 *                                                                            *
 ******************************************************************************/
static void	lld_graphs_get(zbx_uint64_t parent_graphid, zbx_vector_ptr_t *graphs, int width, int height,
		double yaxismin, double yaxismax, unsigned char show_work_period, unsigned char show_triggers,
		unsigned char graphtype, unsigned char show_legend, unsigned char show_3d, double percent_left,
		double percent_right, unsigned char ymin_type, unsigned char ymax_type)
{
	const char	*__function_name = "lld_graphs_get";

	DB_RESULT	result;
	DB_ROW		row;
	zbx_lld_graph_t	*graph;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	result = DBselect(
			"select g.graphid,g.name,g.width,g.height,g.yaxismin,g.yaxismax,g.show_work_period,"
				"g.show_triggers,g.graphtype,g.show_legend,g.show_3d,g.percent_left,g.percent_right,"
				"g.ymin_type,g.ymin_itemid,g.ymax_type,g.ymax_itemid"
			" from graphs g,graph_discovery gd"
			" where g.graphid=gd.graphid"
				" and gd.parent_graphid=" ZBX_FS_UI64,
			parent_graphid);

	while (NULL != (row = DBfetch(result)))
	{
		graph = zbx_malloc(NULL, sizeof(zbx_lld_graph_t));

		ZBX_STR2UINT64(graph->graphid, row[0]);
		graph->name = zbx_strdup(NULL, row[1]);
		graph->name_orig = NULL;

		graph->flags = ZBX_FLAG_LLD_GRAPH_UNSET;

		if (atoi(row[2]) != width)
			graph->flags |= ZBX_FLAG_LLD_GRAPH_UPDATE_WIDTH;

		if (atoi(row[3]) != height)
			graph->flags |= ZBX_FLAG_LLD_GRAPH_UPDATE_HEIGHT;

		if (atof(row[4]) != yaxismin)
			graph->flags |= ZBX_FLAG_LLD_GRAPH_UPDATE_YAXISMIN;

		if (atof(row[5]) != yaxismax)
			graph->flags |= ZBX_FLAG_LLD_GRAPH_UPDATE_YAXISMAX;

		if ((unsigned char)atoi(row[6]) != show_work_period)
			graph->flags |= ZBX_FLAG_LLD_GRAPH_UPDATE_SHOW_WORK_PERIOD;

		if ((unsigned char)atoi(row[7]) != show_triggers)
			graph->flags |= ZBX_FLAG_LLD_GRAPH_UPDATE_SHOW_TRIGGERS;

		if ((unsigned char)atoi(row[8]) != graphtype)
			graph->flags |= ZBX_FLAG_LLD_GRAPH_UPDATE_GRAPHTYPE;

		if ((unsigned char)atoi(row[9]) != show_legend)
			graph->flags |= ZBX_FLAG_LLD_GRAPH_UPDATE_SHOW_LEGEND;

		if ((unsigned char)atoi(row[10]) != show_3d)
			graph->flags |= ZBX_FLAG_LLD_GRAPH_UPDATE_SHOW_3D;

		if (atof(row[11]) != percent_left)
			graph->flags |= ZBX_FLAG_LLD_GRAPH_UPDATE_PERCENT_LEFT;

		if (atof(row[12]) != percent_right)
			graph->flags |= ZBX_FLAG_LLD_GRAPH_UPDATE_PERCENT_RIGHT;

		if ((unsigned char)atoi(row[13]) != ymin_type)
			graph->flags |= ZBX_FLAG_LLD_GRAPH_UPDATE_YMIN_TYPE;

		ZBX_DBROW2UINT64(graph->ymin_itemid, row[14]);

		if ((unsigned char)atoi(row[15]) != ymax_type)
			graph->flags |= ZBX_FLAG_LLD_GRAPH_UPDATE_YMAX_TYPE;

		ZBX_DBROW2UINT64(graph->ymax_itemid, row[16]);

		zbx_vector_ptr_create(&graph->gitems);

		zbx_vector_ptr_append(graphs, graph);
	}
	DBfree_result(result);

	zbx_vector_ptr_sort(graphs, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: lld_gitems_get                                                   *
 *                                                                            *
 * Purpose: retrieve graphs_items which are used by the graph prototype and   *
 *          by selected graphs                                                *
 *                                                                            *
 ******************************************************************************/
static void	lld_gitems_get(zbx_uint64_t parent_graphid, zbx_vector_ptr_t *gitems_proto,
		zbx_vector_ptr_t *graphs)
{
	const char		*__function_name = "lld_gitems_get";

	int			i, index;
	zbx_lld_graph_t		*graph;
	zbx_lld_gitem_t		*gitem;
	zbx_uint64_t		graphid;
	zbx_vector_uint64_t	graphids;
	DB_RESULT		result;
	DB_ROW			row;
	char			*sql = NULL;
	size_t			sql_alloc = 256, sql_offset = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_uint64_create(&graphids);
	zbx_vector_uint64_append(&graphids, parent_graphid);

	for (i = 0; i < graphs->values_num; i++)
	{
		graph = (zbx_lld_graph_t *)graphs->values[i];

		zbx_vector_uint64_append(&graphids, graph->graphid);
	}

	zbx_vector_uint64_sort(&graphids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	sql = zbx_malloc(sql, sql_alloc);

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select gitemid,graphid,itemid,drawtype,sortorder,color,yaxisside,calc_fnc,type"
			" from graphs_items"
			" where");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "graphid",
			graphids.values, graphids.values_num);

	result = DBselect("%s", sql);

	zbx_free(sql);

	while (NULL != (row = DBfetch(result)))
	{
		gitem = zbx_malloc(NULL, sizeof(zbx_lld_gitem_t));

		ZBX_STR2UINT64(gitem->gitemid, row[0]);
		ZBX_STR2UINT64(graphid, row[1]);
		ZBX_STR2UINT64(gitem->itemid, row[2]);
		ZBX_STR2UCHAR(gitem->drawtype, row[3]);
		gitem->sortorder = atoi(row[4]);
		gitem->color = zbx_strdup(NULL, row[5]);
		ZBX_STR2UCHAR(gitem->yaxisside, row[6]);
		ZBX_STR2UCHAR(gitem->calc_fnc, row[7]);
		ZBX_STR2UCHAR(gitem->type, row[8]);

		gitem->flags = ZBX_FLAG_LLD_GITEM_UNSET;

		if (graphid == parent_graphid)
		{
			zbx_vector_ptr_append(gitems_proto, gitem);
		}
		else if (FAIL != (index = zbx_vector_ptr_bsearch(graphs, &graphid,
				ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
		{
			graph = (zbx_lld_graph_t *)graphs->values[index];

			zbx_vector_ptr_append(&graph->gitems, gitem);
		}
		else
		{
			THIS_SHOULD_NEVER_HAPPEN;
			lld_gitem_free(gitem);
		}
	}
	DBfree_result(result);

	zbx_vector_ptr_sort(gitems_proto, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

	for (i = 0; i < graphs->values_num; i++)
	{
		graph = (zbx_lld_graph_t *)graphs->values[i];

		zbx_vector_ptr_sort(&graph->gitems, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);
	}

	zbx_vector_uint64_destroy(&graphids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: lld_items_get                                                    *
 *                                                                            *
 * Purpose: returns the list of items which are related to the graph          *
 *          prototype                                                         *
 *                                                                            *
 * Parameters: gitems_proto      - [IN] graph prototype's graphs_items        *
 *             ymin_itemid_proto - [IN] graph prototype's ymin_itemid         *
 *             ymax_itemid_proto - [IN] graph prototype's ymax_itemid         *
 *             items             - [OUT] sorted list of items                 *
 *                                                                            *
 ******************************************************************************/
static void	lld_items_get(zbx_vector_ptr_t *gitems_proto, zbx_uint64_t ymin_itemid_proto,
		zbx_uint64_t ymax_itemid_proto, zbx_vector_ptr_t *items)
{
	const char		*__function_name = "lld_items_get";

	DB_RESULT		result;
	DB_ROW			row;
	zbx_lld_gitem_t		*gitem;
	zbx_lld_item_t		*item;
	zbx_vector_uint64_t	itemids;
	int			i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_uint64_create(&itemids);

	for (i = 0; i < gitems_proto->values_num; i++)
	{
		gitem = (zbx_lld_gitem_t *)gitems_proto->values[i];

		zbx_vector_uint64_append(&itemids, gitem->itemid);
	}

	if (0 != ymin_itemid_proto)
		zbx_vector_uint64_append(&itemids, ymin_itemid_proto);

	if (0 != ymax_itemid_proto)
		zbx_vector_uint64_append(&itemids, ymax_itemid_proto);

	if (0 != itemids.values_num)
	{
		char	*sql = NULL;
		size_t	sql_alloc = 256, sql_offset = 0;

		zbx_vector_uint64_sort(&itemids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		sql = zbx_malloc(sql, sql_alloc);

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				"select itemid,flags"
				" from items"
				" where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "itemid", itemids.values, itemids.values_num);

		result = DBselect("%s", sql);

		zbx_free(sql);

		while (NULL != (row = DBfetch(result)))
		{
			item = zbx_malloc(NULL, sizeof(zbx_lld_item_t));

			ZBX_STR2UINT64(item->itemid, row[0]);
			ZBX_STR2UCHAR(item->flags, row[1]);

			zbx_vector_ptr_append(items, item);
		}
		DBfree_result(result);

		zbx_vector_ptr_sort(items, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);
	}

	zbx_vector_uint64_destroy(&itemids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: lld_graph_by_item                                                *
 *                                                                            *
 * Purpose: finds already existing graph, using an item                       *
 *                                                                            *
 * Return value: upon successful completion return pointer to the graph       *
 *                                                                            *
 ******************************************************************************/
static zbx_lld_graph_t	*lld_graph_by_item(zbx_vector_ptr_t *graphs, zbx_uint64_t itemid)
{
	int		i, j;
	zbx_lld_graph_t	*graph;
	zbx_lld_gitem_t	*gitem;

	for (i = 0; i < graphs->values_num; i++)
	{
		graph = (zbx_lld_graph_t *)graphs->values[i];

		if (0 != (graph->flags & ZBX_FLAG_LLD_GRAPH_DISCOVERED))
			continue;

		for (j = 0; j < graph->gitems.values_num; j++)
		{
			gitem = (zbx_lld_gitem_t *)graph->gitems.values[j];

			if (gitem->itemid == itemid)
				return graph;
		}
	}

	return NULL;
}

/******************************************************************************
 *                                                                            *
 * Function: lld_graph_get                                                    *
 *                                                                            *
 * Purpose: finds already existing graph, using an item prototype and items   *
 *          already created by it                                             *
 *                                                                            *
 * Return value: upon successful completion return pointer to the graph       *
 *                                                                            *
 ******************************************************************************/
static zbx_lld_graph_t	*lld_graph_get(zbx_vector_ptr_t *graphs, zbx_vector_ptr_t *item_links)
{
	int		i;
	zbx_lld_graph_t	*graph;

	for (i = 0; i < item_links->values_num; i++)
	{
		zbx_lld_item_link_t	*item_link = (zbx_lld_item_link_t *)item_links->values[i];

		if (NULL != (graph = lld_graph_by_item(graphs, item_link->itemid)))
			return graph;
	}

	return NULL;
}

/******************************************************************************
 *                                                                            *
 * Function: lld_item_get                                                     *
 *                                                                            *
 * Purpose: finds already created item when itemid_proto is an item prototype *
 *          or return itemid_proto as itemid if it's a normal item            *
 *                                                                            *
 * Return value: SUCCEED if item successfully processed, FAIL - otherwise     *
 *                                                                            *
 ******************************************************************************/
static int	lld_item_get(zbx_uint64_t itemid_proto, zbx_vector_ptr_t *items, zbx_vector_ptr_t *item_links,
		zbx_uint64_t *itemid)
{
	int			index;
	zbx_lld_item_t		*item_proto;
	zbx_lld_item_link_t	*item_link;

	if (FAIL == (index = zbx_vector_ptr_bsearch(items, &itemid_proto, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
		return FAIL;

	item_proto = (zbx_lld_item_t *)items->values[index];

	if (0 != (item_proto->flags & ZBX_FLAG_DISCOVERY_PROTOTYPE))
	{
		index = zbx_vector_ptr_bsearch(item_links, &item_proto->itemid, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

		if (FAIL == index)
			return FAIL;

		item_link = (zbx_lld_item_link_t *)item_links->values[index];

		*itemid = item_link->itemid;
	}
	else
		*itemid = item_proto->itemid;

	return SUCCEED;
}

static int	lld_gitems_make(zbx_vector_ptr_t *gitems_proto, zbx_vector_ptr_t *gitems, zbx_vector_ptr_t *items,
		zbx_vector_ptr_t *item_links)
{
	const char		*__function_name = "lld_gitems_make";

	int			i, ret = FAIL;
	zbx_lld_gitem_t		*gitem_proto, *gitem;
	zbx_uint64_t		itemid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	for (i = 0; i < gitems_proto->values_num; i++)
	{
		gitem_proto = (zbx_lld_gitem_t *)gitems_proto->values[i];

		if (SUCCEED != lld_item_get(gitem_proto->itemid, items, item_links, &itemid))
			goto out;

		if (i == gitems->values_num)
		{
			gitem = zbx_malloc(NULL, sizeof(zbx_lld_gitem_t));

			gitem->gitemid = 0;
			gitem->itemid = itemid;
			gitem->drawtype = gitem_proto->drawtype;
			gitem->sortorder = gitem_proto->sortorder;
			gitem->color = zbx_strdup(NULL, gitem_proto->color);
			gitem->yaxisside = gitem_proto->yaxisside;
			gitem->calc_fnc = gitem_proto->calc_fnc;
			gitem->type = gitem_proto->type;

			gitem->flags = ZBX_FLAG_LLD_GITEM_DISCOVERED;

			zbx_vector_ptr_append(gitems, gitem);
		}
		else
		{
			gitem = (zbx_lld_gitem_t *)gitems->values[i];

			if (gitem->itemid != itemid)
			{
				gitem->itemid = itemid;
				gitem->flags |= ZBX_FLAG_LLD_GITEM_UPDATE_ITEMID;
			}

			if (gitem->drawtype != gitem_proto->drawtype)
			{
				gitem->drawtype = gitem_proto->drawtype;
				gitem->flags |= ZBX_FLAG_LLD_GITEM_UPDATE_DRAWTYPE;
			}

			if (gitem->sortorder != gitem_proto->sortorder)
			{
				gitem->sortorder = gitem_proto->sortorder;
				gitem->flags |= ZBX_FLAG_LLD_GITEM_UPDATE_SORTORDER;
			}

			if (0 != strcmp(gitem->color, gitem_proto->color))
			{
				gitem->color = zbx_strdup(gitem->color, gitem_proto->color);
				gitem->flags |= ZBX_FLAG_LLD_GITEM_UPDATE_COLOR;
			}

			if (gitem->yaxisside != gitem_proto->yaxisside)
			{
				gitem->yaxisside = gitem_proto->yaxisside;
				gitem->flags |= ZBX_FLAG_LLD_GITEM_UPDATE_YAXISSIDE;
			}

			if (gitem->calc_fnc != gitem_proto->calc_fnc)
			{
				gitem->calc_fnc = gitem_proto->calc_fnc;
				gitem->flags |= ZBX_FLAG_LLD_GITEM_UPDATE_CALC_FNC;
			}

			if (gitem->type != gitem_proto->type)
			{
				gitem->type = gitem_proto->type;
				gitem->flags |= ZBX_FLAG_LLD_GITEM_UPDATE_TYPE;
			}

			gitem->flags |= ZBX_FLAG_LLD_GITEM_DISCOVERED;
		}
	}

	for (; i < gitems->values_num; i++)
	{
		gitem = (zbx_lld_gitem_t *)gitems->values[i];

		gitem->flags |= ZBX_FLAG_LLD_GITEM_DELETE;
	}

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: lld_graph_make                                                   *
 *                                                                            *
 * Purpose: create a graph based on lld rule and add it to the list           *
 *                                                                            *
 ******************************************************************************/
static void 	lld_graph_make(zbx_vector_ptr_t *gitems_proto, zbx_vector_ptr_t *graphs, zbx_vector_ptr_t *items,
		const char *name_proto, zbx_uint64_t ymin_itemid_proto, zbx_uint64_t ymax_itemid_proto,
		zbx_lld_row_t *lld_row)
{
	const char		*__function_name = "lld_graph_make";

	zbx_lld_graph_t		*graph = NULL;
	char			*buffer = NULL;
	struct zbx_json_parse	*jp_row = &lld_row->jp_row;
	zbx_uint64_t		ymin_itemid, ymax_itemid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (0 == ymin_itemid_proto)
		ymin_itemid = 0;
	else if (SUCCEED != lld_item_get(ymin_itemid_proto, items, &lld_row->item_links, &ymin_itemid))
		goto out;

	if (0 == ymax_itemid_proto)
		ymax_itemid = 0;
	else if (SUCCEED != lld_item_get(ymax_itemid_proto, items, &lld_row->item_links, &ymax_itemid))
		goto out;

	if (NULL != (graph = lld_graph_get(graphs, &lld_row->item_links)))
	{
		buffer = zbx_strdup(buffer, name_proto);
		substitute_discovery_macros(&buffer, jp_row, ZBX_MACRO_SIMPLE, NULL, 0);
		zbx_lrtrim(buffer, ZBX_WHITESPACE);
		if (0 != strcmp(graph->name, buffer))
		{
			graph->name_orig = graph->name;
			graph->name = buffer;
			buffer = NULL;
			graph->flags |= ZBX_FLAG_LLD_GRAPH_UPDATE_NAME;
		}

		if (graph->ymin_itemid != ymin_itemid)
		{
			graph->ymin_itemid = ymin_itemid;
			graph->flags |= ZBX_FLAG_LLD_GRAPH_UPDATE_YMIN_ITEMID;
		}

		if (graph->ymax_itemid != ymax_itemid)
		{
			graph->ymax_itemid = ymax_itemid;
			graph->flags |= ZBX_FLAG_LLD_GRAPH_UPDATE_YMAX_ITEMID;
		}
	}
	else
	{
		graph = zbx_malloc(NULL, sizeof(zbx_lld_graph_t));

		graph->graphid = 0;

		graph->name = zbx_strdup(NULL, name_proto);
		graph->name_orig = NULL;
		substitute_discovery_macros(&graph->name, jp_row, ZBX_MACRO_SIMPLE, NULL, 0);
		zbx_lrtrim(graph->name, ZBX_WHITESPACE);

		graph->ymin_itemid = ymin_itemid;
		graph->ymax_itemid = ymax_itemid;

		zbx_vector_ptr_create(&graph->gitems);

		graph->flags = ZBX_FLAG_LLD_GRAPH_UNSET;

		zbx_vector_ptr_append(graphs, graph);
	}

	zbx_free(buffer);

	if (SUCCEED != lld_gitems_make(gitems_proto, &graph->gitems, items, &lld_row->item_links))
		return;

	graph->flags |= ZBX_FLAG_LLD_GRAPH_DISCOVERED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static void	lld_graphs_make(zbx_vector_ptr_t *gitems_proto, zbx_vector_ptr_t *graphs, zbx_vector_ptr_t *items,
		const char *name_proto, zbx_uint64_t ymin_itemid_proto, zbx_uint64_t ymax_itemid_proto,
		zbx_vector_ptr_t *lld_rows)
{
	int	i;

	for (i = 0; i < lld_rows->values_num; i++)
	{
		zbx_lld_row_t	*lld_row = (zbx_lld_row_t *)lld_rows->values[i];

		lld_graph_make(gitems_proto, graphs, items, name_proto, ymin_itemid_proto, ymax_itemid_proto, lld_row);
	}

	zbx_vector_ptr_sort(graphs, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);
}

/******************************************************************************
 *                                                                            *
 * Function: lld_validate_graph_field                                         *
 *                                                                            *
 ******************************************************************************/
static void	lld_validate_graph_field(zbx_lld_graph_t *graph, char **field, char **field_orig, zbx_uint64_t flag,
		size_t field_len, char **error)
{
	if (0 == (graph->flags & ZBX_FLAG_LLD_GRAPH_DISCOVERED))
		return;

	/* only new graphs or graphs with changed data will be validated */
	if (0 != graph->graphid && 0 == (graph->flags & flag))
		return;

	if (SUCCEED != zbx_is_utf8(*field))
	{
		zbx_replace_invalid_utf8(*field);
		*error = zbx_strdcatf(*error, "Cannot %s graph: value \"%s\" has invalid UTF-8 sequence.\n",
				(0 != graph->graphid ? "update" : "create"), *field);
	}
	else if (zbx_strlen_utf8(*field) > field_len)
	{
		*error = zbx_strdcatf(*error, "Cannot %s graph: value \"%s\" is too long.\n",
				(0 != graph->graphid ? "update" : "create"), *field);
	}
	else
		return;

	if (0 != graph->graphid)
		lld_field_str_rollback(field, field_orig, &graph->flags, flag);
	else
		graph->flags &= ~ZBX_FLAG_LLD_GRAPH_DISCOVERED;
}

/******************************************************************************
 *                                                                            *
 * Function: lld_graphs_validate                                              *
 *                                                                            *
 * Parameters: graphs - [IN] sorted list of graphs                            *
 *                                                                            *
 ******************************************************************************/
static void	lld_graphs_validate(zbx_uint64_t hostid, zbx_vector_ptr_t *graphs, char **error)
{
	const char		*__function_name = "lld_graphs_validate";

	int			i, j;
	zbx_lld_graph_t		*graph, *graph_b;
	zbx_vector_uint64_t	graphids;
	zbx_vector_str_t	names;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_uint64_create(&graphids);
	zbx_vector_str_create(&names);		/* list of graph names */

	/* checking a validity of the fields */

	for (i = 0; i < graphs->values_num; i++)
	{
		graph = (zbx_lld_graph_t *)graphs->values[i];

		lld_validate_graph_field(graph, &graph->name, &graph->name_orig,
				ZBX_FLAG_LLD_GRAPH_UPDATE_NAME, GRAPH_NAME_LEN, error);
	}

	/* checking duplicated graph names */
	for (i = 0; i < graphs->values_num; i++)
	{
		graph = (zbx_lld_graph_t *)graphs->values[i];

		if (0 == (graph->flags & ZBX_FLAG_LLD_GRAPH_DISCOVERED))
			continue;

		/* only new graphs or graphs with changed name will be validated */
		if (0 != graph->graphid && 0 == (graph->flags & ZBX_FLAG_LLD_GRAPH_UPDATE_NAME))
			continue;

		for (j = 0; j < graphs->values_num; j++)
		{
			graph_b = (zbx_lld_graph_t *)graphs->values[j];

			if (0 == (graph_b->flags & ZBX_FLAG_LLD_GRAPH_DISCOVERED) || i == j)
				continue;

			if (0 != strcmp(graph->name, graph_b->name))
				continue;

			*error = zbx_strdcatf(*error, "Cannot %s graph:"
						" graph with the same name \"%s\" already exists.\n",
						(0 != graph->graphid ? "update" : "create"), graph->name);

			if (0 != graph->graphid)
			{
				lld_field_str_rollback(&graph->name, &graph->name_orig, &graph->flags,
						ZBX_FLAG_LLD_GRAPH_UPDATE_NAME);
			}
			else
				graph->flags &= ~ZBX_FLAG_LLD_GRAPH_DISCOVERED;

			break;
		}
	}

	/* checking duplicated graphs in DB */

	for (i = 0; i < graphs->values_num; i++)
	{
		graph = (zbx_lld_graph_t *)graphs->values[i];

		if (0 == (graph->flags & ZBX_FLAG_LLD_GRAPH_DISCOVERED))
			continue;

		if (0 != graph->graphid)
		{
			zbx_vector_uint64_append(&graphids, graph->graphid);

			if (0 == (graph->flags & ZBX_FLAG_LLD_GRAPH_UPDATE_NAME))
				continue;
		}

		zbx_vector_str_append(&names, graph->name);
	}

	if (0 != names.values_num)
	{
		DB_RESULT	result;
		DB_ROW		row;
		char		*sql = NULL;
		size_t		sql_alloc = 256, sql_offset = 0;

		sql = zbx_malloc(sql, sql_alloc);

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"select g.name"
				" from graphs g,graphs_items gi,items i"
				" where g.graphid=gi.graphid"
					" and gi.itemid=i.itemid"
					" and i.hostid=" ZBX_FS_UI64
					" and",
				hostid);
		DBadd_str_condition_alloc(&sql, &sql_alloc, &sql_offset, "g.name",
				(const char **)names.values, names.values_num);

		if (0 != graphids.values_num)
		{
			zbx_vector_uint64_sort(&graphids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " and not");
			DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "g.graphid",
					graphids.values, graphids.values_num);
		}

		result = DBselect("%s", sql);

		while (NULL != (row = DBfetch(result)))
		{
			for (i = 0; i < graphs->values_num; i++)
			{
				graph = (zbx_lld_graph_t *)graphs->values[i];

				if (0 == (graph->flags & ZBX_FLAG_LLD_GRAPH_DISCOVERED))
					continue;

				if (0 == strcmp(graph->name, row[0]))
				{
					*error = zbx_strdcatf(*error, "Cannot %s graph:"
							" graph with the same name \"%s\" already exists.\n",
							(0 != graph->graphid ? "update" : "create"), graph->name);

					if (0 != graph->graphid)
					{
						lld_field_str_rollback(&graph->name, &graph->name_orig, &graph->flags,
								ZBX_FLAG_LLD_GRAPH_UPDATE_NAME);
					}
					else
						graph->flags &= ~ZBX_FLAG_LLD_GRAPH_DISCOVERED;

					continue;
				}
			}
		}
		DBfree_result(result);

		zbx_free(sql);
	}

	zbx_vector_str_destroy(&names);
	zbx_vector_uint64_destroy(&graphids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static void	lld_graphs_save(zbx_uint64_t parent_graphid, zbx_vector_ptr_t *graphs, int width, int height,
		double yaxismin, double yaxismax, unsigned char show_work_period, unsigned char show_triggers,
		unsigned char graphtype, unsigned char show_legend, unsigned char show_3d, double percent_left,
		double percent_right, unsigned char ymin_type, unsigned char ymax_type)
{
	const char		*__function_name = "lld_graphs_save";

	int			i, j, new_graphs = 0, upd_graphs = 0, new_gitems = 0;
	zbx_lld_graph_t		*graph;
	zbx_lld_gitem_t		*gitem;
	zbx_vector_ptr_t	upd_gitems; 	/* the ordered list of graphs_items which will be updated */
	zbx_vector_uint64_t	del_gitemids;

	zbx_uint64_t		graphid = 0, gitemid = 0;
	char			*sql = NULL, *name_esc, *color_esc;
	size_t			sql_alloc = 8 * ZBX_KIBIBYTE, sql_offset = 0;
	zbx_db_insert_t		db_insert, db_insert_gdiscovery, db_insert_gitems;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_ptr_create(&upd_gitems);
	zbx_vector_uint64_create(&del_gitemids);

	for (i = 0; i < graphs->values_num; i++)
	{
		graph = (zbx_lld_graph_t *)graphs->values[i];

		if (0 == (graph->flags & ZBX_FLAG_LLD_GRAPH_DISCOVERED))
			continue;

		if (0 == graph->graphid)
			new_graphs++;
		else if (0 != (graph->flags & ZBX_FLAG_LLD_GRAPH_UPDATE))
			upd_graphs++;

		for (j = 0; j < graph->gitems.values_num; j++)
		{
			gitem = (zbx_lld_gitem_t *)graph->gitems.values[j];

			if (0 != (gitem->flags & ZBX_FLAG_LLD_GITEM_DELETE))
			{
				zbx_vector_uint64_append(&del_gitemids, gitem->gitemid);
				continue;
			}

			if (0 == (gitem->flags & ZBX_FLAG_LLD_GITEM_DISCOVERED))
				continue;

			if (0 == gitem->gitemid)
				new_gitems++;
			else if (0 != (gitem->flags & ZBX_FLAG_LLD_GITEM_UPDATE))
				zbx_vector_ptr_append(&upd_gitems, gitem);
		}
	}

	if (0 == new_graphs && 0 == new_gitems && 0 == upd_graphs && 0 == upd_gitems.values_num &&
			0 == del_gitemids.values_num)
	{
		goto out;
	}

	DBbegin();

	if (0 != new_graphs)
	{
		graphid = DBget_maxid_num("graphs", new_graphs);

		zbx_db_insert_prepare(&db_insert, "graphs", "graphid", "name", "width", "height", "yaxismin",
				"yaxismax", "show_work_period", "show_triggers", "graphtype", "show_legend", "show_3d",
				"percent_left", "percent_right", "ymin_type", "ymin_itemid", "ymax_type",
				"ymax_itemid", "flags", NULL);

		zbx_db_insert_prepare(&db_insert_gdiscovery, "graph_discovery", "graphid", "parent_graphid", NULL);
	}

	if (0 != new_gitems)
	{
		gitemid = DBget_maxid_num("graphs_items", new_gitems);

		zbx_db_insert_prepare(&db_insert_gitems, "graphs_items", "gitemid", "graphid", "itemid", "drawtype",
				"sortorder", "color", "yaxisside", "calc_fnc", "type", NULL);
	}

	if (0 != upd_graphs || 0 != upd_gitems.values_num || 0 != del_gitemids.values_num)
	{
		sql = zbx_malloc(sql, sql_alloc);
		DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);
	}

	for (i = 0; i < graphs->values_num; i++)
	{
		graph = (zbx_lld_graph_t *)graphs->values[i];

		if (0 == (graph->flags & ZBX_FLAG_LLD_GRAPH_DISCOVERED))
			continue;

		if (0 == graph->graphid)
		{
			zbx_db_insert_add_values(&db_insert, graphid, graph->name, width, height, yaxismin, yaxismax,
					(int)show_work_period, (int)show_triggers, (int)graphtype, (int)show_legend,
					(int)show_3d, percent_left, percent_right, (int)ymin_type, graph->ymin_itemid,
					(int)ymax_type, graph->ymax_itemid, (int)ZBX_FLAG_DISCOVERY_CREATED);

			zbx_db_insert_add_values(&db_insert_gdiscovery, graphid, parent_graphid);

			graph->graphid = graphid++;
		}
		else if (0 != (graph->flags & ZBX_FLAG_LLD_GRAPH_UPDATE))
		{
			const char	*d = "";

			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "update graphs set ");

			if (0 != (graph->flags & ZBX_FLAG_LLD_GRAPH_UPDATE_NAME))
			{
				name_esc = DBdyn_escape_string(graph->name);
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "name='%s'", name_esc);
				zbx_free(name_esc);
				d = ",";
			}

			if (0 != (graph->flags & ZBX_FLAG_LLD_GRAPH_UPDATE_WIDTH))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%swidth=%d", d, width);
				d = ",";
			}

			if (0 != (graph->flags & ZBX_FLAG_LLD_GRAPH_UPDATE_HEIGHT))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%sheight=%d", d, height);
				d = ",";
			}

			if (0 != (graph->flags & ZBX_FLAG_LLD_GRAPH_UPDATE_YAXISMIN))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%syaxismin=" ZBX_FS_DBL, d,
						yaxismin);
				d = ",";
			}

			if (0 != (graph->flags & ZBX_FLAG_LLD_GRAPH_UPDATE_YAXISMAX))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%syaxismax=" ZBX_FS_DBL, d,
						yaxismax);
				d = ",";
			}

			if (0 != (graph->flags & ZBX_FLAG_LLD_GRAPH_UPDATE_SHOW_WORK_PERIOD))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%sshow_work_period=%d", d,
						(int)show_work_period);
				d = ",";
			}

			if (0 != (graph->flags & ZBX_FLAG_LLD_GRAPH_UPDATE_SHOW_TRIGGERS))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%sshow_triggers=%d", d,
						(int)show_triggers);
				d = ",";
			}

			if (0 != (graph->flags & ZBX_FLAG_LLD_GRAPH_UPDATE_GRAPHTYPE))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%sgraphtype=%d", d,
						(int)graphtype);
				d = ",";
			}

			if (0 != (graph->flags & ZBX_FLAG_LLD_GRAPH_UPDATE_SHOW_LEGEND))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%sshow_legend=%d", d,
						(int)show_legend);
				d = ",";
			}

			if (0 != (graph->flags & ZBX_FLAG_LLD_GRAPH_UPDATE_SHOW_3D))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%sshow_3d=%d", d, (int)show_3d);
				d = ",";
			}

			if (0 != (graph->flags & ZBX_FLAG_LLD_GRAPH_UPDATE_PERCENT_LEFT))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%spercent_left=" ZBX_FS_DBL, d,
						percent_left);
				d = ",";
			}

			if (0 != (graph->flags & ZBX_FLAG_LLD_GRAPH_UPDATE_PERCENT_RIGHT))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%spercent_right=" ZBX_FS_DBL, d,
						percent_right);
				d = ",";
			}

			if (0 != (graph->flags & ZBX_FLAG_LLD_GRAPH_UPDATE_YMIN_TYPE))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%symin_type=%d", d,
						(int)ymin_type);
				d = ",";
			}

			if (0 != (graph->flags & ZBX_FLAG_LLD_GRAPH_UPDATE_YMIN_ITEMID))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%symin_itemid=%s", d,
						DBsql_id_ins(graph->ymin_itemid));
				d = ",";
			}

			if (0 != (graph->flags & ZBX_FLAG_LLD_GRAPH_UPDATE_YMAX_TYPE))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%symax_type=%d", d,
						(int)ymax_type);
				d = ",";
			}

			if (0 != (graph->flags & ZBX_FLAG_LLD_GRAPH_UPDATE_YMAX_ITEMID))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%symax_itemid=%s", d,
						DBsql_id_ins(graph->ymax_itemid));
			}

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " where graphid=" ZBX_FS_UI64 ";\n",
					graph->graphid);
		}

		for (j = 0; j < graph->gitems.values_num; j++)
		{
			gitem = (zbx_lld_gitem_t *)graph->gitems.values[j];

			if (0 != (gitem->flags & ZBX_FLAG_LLD_GITEM_DELETE))
				continue;

			if (0 == (gitem->flags & ZBX_FLAG_LLD_GITEM_DISCOVERED))
				continue;

			if (0 == gitem->gitemid)
			{
				zbx_db_insert_add_values(&db_insert_gitems, gitemid, graph->graphid, gitem->itemid,
						(int)gitem->drawtype, gitem->sortorder, gitem->color,
						(int)gitem->yaxisside, (int)gitem->calc_fnc, (int)gitem->type);

				gitem->gitemid = gitemid++;
			}
		}
	}

	for (i = 0; i < upd_gitems.values_num; i++)
	{
		const char	*d = "";

		gitem = (zbx_lld_gitem_t *)upd_gitems.values[i];

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "update graphs_items set ");

		if (0 != (gitem->flags & ZBX_FLAG_LLD_GITEM_UPDATE_ITEMID))
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "itemid=" ZBX_FS_UI64, gitem->itemid);
			d = ",";
		}

		if (0 != (gitem->flags & ZBX_FLAG_LLD_GITEM_UPDATE_DRAWTYPE))
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%sdrawtype=%d", d, (int)gitem->drawtype);
			d = ",";
		}

		if (0 != (gitem->flags & ZBX_FLAG_LLD_GITEM_UPDATE_SORTORDER))
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%ssortorder=%d", d, gitem->sortorder);
			d = ",";
		}

		if (0 != (gitem->flags & ZBX_FLAG_LLD_GITEM_UPDATE_COLOR))
		{
			color_esc = DBdyn_escape_string(gitem->color);
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%scolor='%s'", d, color_esc);
			zbx_free(color_esc);
			d = ",";
		}

		if (0 != (gitem->flags & ZBX_FLAG_LLD_GITEM_UPDATE_YAXISSIDE))
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%syaxisside=%d", d,
					(int)gitem->yaxisside);
			d = ",";
		}

		if (0 != (gitem->flags & ZBX_FLAG_LLD_GITEM_UPDATE_CALC_FNC))
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%scalc_fnc=%d", d, (int)gitem->calc_fnc);
			d = ",";
		}

		if (0 != (gitem->flags & ZBX_FLAG_LLD_GITEM_UPDATE_TYPE))
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%stype=%d", d, (int)gitem->type);

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " where gitemid=" ZBX_FS_UI64 ";\n",
				gitem->gitemid);
	}

	if (0 != del_gitemids.values_num)
	{
		zbx_vector_uint64_sort(&del_gitemids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from graphs_items where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "gitemid",
				del_gitemids.values, del_gitemids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");
	}

	if (0 != upd_graphs || 0 != upd_gitems.values_num || 0 != del_gitemids.values_num)
	{
		DBend_multiple_update(&sql, &sql_alloc, &sql_offset);
		DBexecute("%s", sql);
		zbx_free(sql);
	}

	if (0 != new_graphs)
	{
		zbx_db_insert_execute(&db_insert);
		zbx_db_insert_clean(&db_insert);

		zbx_db_insert_execute(&db_insert_gdiscovery);
		zbx_db_insert_clean(&db_insert_gdiscovery);
	}

	if (0 != new_gitems)
	{
		zbx_db_insert_execute(&db_insert_gitems);
		zbx_db_insert_clean(&db_insert_gitems);
	}

	DBcommit();
out:
	zbx_vector_uint64_destroy(&del_gitemids);
	zbx_vector_ptr_destroy(&upd_gitems);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: lld_update_graphs                                                *
 *                                                                            *
 * Purpose: add or update graphs for discovery item                           *
 *                                                                            *
 * Parameters: hostid  - [IN] host identificator from database                *
 *             agent   - [IN] discovery item identificator from database      *
 *             jp_data - [IN] received data                                   *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
void	lld_update_graphs(zbx_uint64_t hostid, zbx_uint64_t lld_ruleid, zbx_vector_ptr_t *lld_rows, char **error)
{
	const char		*__function_name = "lld_update_graphs";

	DB_RESULT		result;
	DB_ROW			row;
	zbx_vector_ptr_t	graphs;
	zbx_vector_ptr_t	gitems_proto;
	zbx_vector_ptr_t	items;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_ptr_create(&graphs);		/* list of graphs which were created or will be created or */
						/* updated by the graph prototype */
	zbx_vector_ptr_create(&gitems_proto);	/* list of graphs_items which are used by the graph prototype */
	zbx_vector_ptr_create(&items);		/* list of items which are related to the graph prototype */

	result = DBselect(
			"select distinct g.graphid,g.name,g.width,g.height,g.yaxismin,g.yaxismax,g.show_work_period,"
				"g.show_triggers,g.graphtype,g.show_legend,g.show_3d,g.percent_left,g.percent_right,"
				"g.ymin_type,g.ymin_itemid,g.ymax_type,g.ymax_itemid"
			" from graphs g,graphs_items gi,items i,item_discovery id"
			" where g.graphid=gi.graphid"
				" and gi.itemid=i.itemid"
				" and i.itemid=id.itemid"
				" and id.parent_itemid=" ZBX_FS_UI64,
			lld_ruleid);

	while (NULL != (row = DBfetch(result)))
	{
		zbx_uint64_t	parent_graphid, ymin_itemid_proto, ymax_itemid_proto;
		const char	*name_proto;
		int		width, height;
		double		yaxismin, yaxismax, percent_left, percent_right;
		unsigned char	show_work_period, show_triggers, graphtype, show_legend, show_3d,
				ymin_type, ymax_type;

		ZBX_STR2UINT64(parent_graphid, row[0]);
		name_proto = row[1];
		width = atoi(row[2]);
		height = atoi(row[3]);
		yaxismin = atof(row[4]);
		yaxismax = atof(row[5]);
		ZBX_STR2UCHAR(show_work_period, row[6]);
		ZBX_STR2UCHAR(show_triggers, row[7]);
		ZBX_STR2UCHAR(graphtype, row[8]);
		ZBX_STR2UCHAR(show_legend, row[9]);
		ZBX_STR2UCHAR(show_3d, row[10]);
		percent_left = atof(row[11]);
		percent_right = atof(row[12]);
		ZBX_STR2UCHAR(ymin_type, row[13]);
		ZBX_DBROW2UINT64(ymin_itemid_proto, row[14]);
		ZBX_STR2UCHAR(ymax_type, row[15]);
		ZBX_DBROW2UINT64(ymax_itemid_proto, row[16]);

		lld_graphs_get(parent_graphid, &graphs, width, height, yaxismin, yaxismax, show_work_period,
				show_triggers, graphtype, show_legend, show_3d, percent_left, percent_right,
				ymin_type, ymax_type);
		lld_gitems_get(parent_graphid, &gitems_proto, &graphs);
		lld_items_get(&gitems_proto, ymin_itemid_proto, ymax_itemid_proto, &items);

		/* making graphs */

		lld_graphs_make(&gitems_proto, &graphs, &items, name_proto, ymin_itemid_proto, ymax_itemid_proto,
				lld_rows);
		lld_graphs_validate(hostid, &graphs, error);
		lld_graphs_save(parent_graphid, &graphs, width, height, yaxismin, yaxismax, show_work_period,
				show_triggers, graphtype, show_legend, show_3d, percent_left, percent_right,
				ymin_type, ymax_type);

		lld_items_free(&items);
		lld_gitems_free(&gitems_proto);
		lld_graphs_free(&graphs);
	}
	DBfree_result(result);

	zbx_vector_ptr_destroy(&items);
	zbx_vector_ptr_destroy(&gitems_proto);
	zbx_vector_ptr_destroy(&graphs);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}
