/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

#include "log.h"
#include "zbxserver.h"
#include "../../libs/zbxaudit/audit.h"
#include "../../libs/zbxaudit/audit_graph.h"

typedef struct
{
	zbx_uint64_t		graphid;
	char			*name;
	char			*name_orig;
	zbx_uint64_t		ymin_itemid_orig;
	zbx_uint64_t		ymin_itemid;
	zbx_uint64_t		ymax_itemid_orig;
	zbx_uint64_t		ymax_itemid;
	int 			width_orig;
	int 			height_orig;
	double 			yaxismin_orig;
	double 			yaxismax_orig;
	unsigned char 		show_work_period_orig;
	unsigned char 		show_triggers_orig;
	unsigned char 		graphtype_orig;
	unsigned char 		show_legend_orig;
	unsigned char 		show_3d_orig;
	double 			percent_left_orig;
	double 			percent_right_orig;
	unsigned char 		ymin_type_orig;
	unsigned char 		ymax_type_orig;
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
	int			lastcheck;
	int			ts_delete;
}
zbx_lld_graph_t;

typedef struct
{
	zbx_uint64_t		gitemid;
	zbx_uint64_t		itemid_orig;
	zbx_uint64_t		itemid;
	char			*color_orig;
	char			*color;
	int			sortorder_orig;
	int			sortorder;
	unsigned char		drawtype_orig;
	unsigned char		drawtype;
	unsigned char		yaxisside_orig;
	unsigned char		yaxisside;
	unsigned char		calc_fnc_orig;
	unsigned char		calc_fnc;
	unsigned char		type_orig;
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
	zbx_uint64_t		graphid;
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
	zbx_free(gitem->color_orig);
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
 * Purpose: retrieve graphs which were created by the specified graph         *
 *          prototype                                                         *
 *                                                                            *
 * Parameters: parent_graphid - [IN] graph prototype identifier               *
 *             graphs         - [OUT] sorted list of graphs                   *
 *                                                                            *
 ******************************************************************************/
static void	lld_graphs_get(zbx_uint64_t parent_graphid, zbx_vector_ptr_t *graphs, int width, int height,
		double yaxismin, double yaxismax, unsigned char show_work_period, unsigned char show_triggers,
		unsigned char graphtype, unsigned char show_legend, unsigned char show_3d, double percent_left,
		double percent_right, unsigned char ymin_type, unsigned char ymax_type)
{
	DB_RESULT	result;
	DB_ROW		row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	result = DBselect(
			"select g.graphid,g.name,g.width,g.height,g.yaxismin,g.yaxismax,g.show_work_period,"
				"g.show_triggers,g.graphtype,g.show_legend,g.show_3d,g.percent_left,g.percent_right,"
				"g.ymin_type,g.ymin_itemid,g.ymax_type,g.ymax_itemid,gd.lastcheck,gd.ts_delete"
			" from graphs g,graph_discovery gd"
			" where g.graphid=gd.graphid"
				" and gd.parent_graphid=" ZBX_FS_UI64,
			parent_graphid);

	while (NULL != (row = DBfetch(result)))
	{
		zbx_lld_graph_t	*graph = (zbx_lld_graph_t *)zbx_malloc(NULL, sizeof(zbx_lld_graph_t));

		ZBX_STR2UINT64(graph->graphid, row[0]);
		graph->name = zbx_strdup(NULL, row[1]);
		graph->name_orig = NULL;

		graph->flags = ZBX_FLAG_LLD_GRAPH_UNSET;

		graph->width_orig = atoi(row[2]);
		if (graph->width_orig != width)
			graph->flags |= ZBX_FLAG_LLD_GRAPH_UPDATE_WIDTH;

		graph->height_orig = atoi(row[3]);
		if (graph->height_orig != height)
			graph->flags |= ZBX_FLAG_LLD_GRAPH_UPDATE_HEIGHT;

		graph->yaxismin_orig = atof(row[4]);
		if (FAIL == zbx_double_compare(graph->yaxismin_orig, yaxismin))
			graph->flags |= ZBX_FLAG_LLD_GRAPH_UPDATE_YAXISMIN;

		graph->yaxismax_orig = atof(row[5]);
		if (FAIL == zbx_double_compare(graph->yaxismax_orig, yaxismax))
			graph->flags |= ZBX_FLAG_LLD_GRAPH_UPDATE_YAXISMAX;

		graph->show_work_period_orig = (unsigned char)atoi(row[6]);
		if (graph->show_work_period_orig != show_work_period)
			graph->flags |= ZBX_FLAG_LLD_GRAPH_UPDATE_SHOW_WORK_PERIOD;

		graph->show_triggers_orig = (unsigned char)atoi(row[7]);
		if (graph->show_triggers_orig != show_triggers)
			graph->flags |= ZBX_FLAG_LLD_GRAPH_UPDATE_SHOW_TRIGGERS;

		graph->graphtype_orig = (unsigned char)atoi(row[8]);
		if (graph->graphtype_orig != graphtype)
			graph->flags |= ZBX_FLAG_LLD_GRAPH_UPDATE_GRAPHTYPE;

		graph->show_legend_orig = (unsigned char)atoi(row[9]);
		if (graph->show_legend_orig != show_legend)
			graph->flags |= ZBX_FLAG_LLD_GRAPH_UPDATE_SHOW_LEGEND;

		graph->show_3d_orig = (unsigned char)atoi(row[10]);
		if (graph->show_3d_orig != show_3d)
			graph->flags |= ZBX_FLAG_LLD_GRAPH_UPDATE_SHOW_3D;

		graph->percent_left_orig = atof(row[11]);
		if (FAIL == zbx_double_compare(graph->percent_left_orig, percent_left))
			graph->flags |= ZBX_FLAG_LLD_GRAPH_UPDATE_PERCENT_LEFT;

		graph->percent_right_orig = atof(row[12]);
		if (FAIL == zbx_double_compare(graph->percent_right_orig, percent_right))
			graph->flags |= ZBX_FLAG_LLD_GRAPH_UPDATE_PERCENT_RIGHT;

		graph->ymin_type_orig = (unsigned char)atoi(row[13]);
		if (graph->ymin_type_orig != ymin_type)
			graph->flags |= ZBX_FLAG_LLD_GRAPH_UPDATE_YMIN_TYPE;

		ZBX_DBROW2UINT64(graph->ymin_itemid, row[14]);
		graph->ymin_itemid_orig = graph->ymin_itemid;

		graph->ymax_type_orig = (unsigned char)atoi(row[15]);
		if (graph->ymax_type_orig != ymax_type)
			graph->flags |= ZBX_FLAG_LLD_GRAPH_UPDATE_YMAX_TYPE;

		ZBX_DBROW2UINT64(graph->ymax_itemid, row[16]);
		graph->ymax_itemid_orig = graph->ymax_itemid;

		graph->lastcheck = atoi(row[17]);
		graph->ts_delete = atoi(row[18]);

		zbx_vector_ptr_create(&graph->gitems);

		zbx_vector_ptr_append(graphs, graph);
	}
	DBfree_result(result);

	zbx_vector_ptr_sort(graphs, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieve graphs_items which are used by the graph prototype and   *
 *          by selected graphs                                                *
 *                                                                            *
 ******************************************************************************/
static void	lld_gitems_get(zbx_uint64_t parent_graphid, zbx_vector_ptr_t *gitems_proto,
		zbx_vector_ptr_t *graphs)
{
	int			i;
	zbx_lld_graph_t		*graph;
	zbx_vector_uint64_t	graphids;
	DB_RESULT		result;
	DB_ROW			row;
	char			*sql = NULL;
	size_t			sql_alloc = 256, sql_offset = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&graphids);
	zbx_vector_uint64_append(&graphids, parent_graphid);

	for (i = 0; i < graphs->values_num; i++)
	{
		graph = (zbx_lld_graph_t *)graphs->values[i];

		zbx_vector_uint64_append(&graphids, graph->graphid);
	}

	zbx_vector_uint64_sort(&graphids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	sql = (char *)zbx_malloc(sql, sql_alloc);

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
		int			index;
		zbx_uint64_t		graphid;
		zbx_lld_gitem_t		*gitem = (zbx_lld_gitem_t *)zbx_malloc(NULL, sizeof(zbx_lld_gitem_t));

		ZBX_STR2UINT64(gitem->gitemid, row[0]);
		ZBX_STR2UINT64(graphid, row[1]);
		ZBX_STR2UINT64(gitem->itemid, row[2]);
		gitem->itemid_orig = gitem->itemid;
		ZBX_STR2UCHAR(gitem->drawtype, row[3]);
		gitem->drawtype_orig = gitem->drawtype;
		gitem->sortorder = atoi(row[4]);
		gitem->sortorder_orig = gitem->sortorder;
		gitem->color = zbx_strdup(NULL, row[5]);
		gitem->color_orig = NULL;
		ZBX_STR2UCHAR(gitem->yaxisside, row[6]);
		gitem->yaxisside_orig = gitem->yaxisside;
		ZBX_STR2UCHAR(gitem->calc_fnc, row[7]);
		gitem->calc_fnc_orig = gitem->calc_fnc;
		ZBX_STR2UCHAR(gitem->type, row[8]);
		gitem->type_orig = gitem->type;
		gitem->graphid = graphid;

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

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
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
static void	lld_items_get(const zbx_vector_ptr_t *gitems_proto, zbx_uint64_t ymin_itemid_proto,
		zbx_uint64_t ymax_itemid_proto, zbx_vector_ptr_t *items)
{
	DB_RESULT		result;
	DB_ROW			row;
	zbx_vector_uint64_t	itemids;
	int			i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&itemids);

	for (i = 0; i < gitems_proto->values_num; i++)
	{
		const zbx_lld_gitem_t	*gitem = (const zbx_lld_gitem_t *)gitems_proto->values[i];

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

		sql = (char *)zbx_malloc(sql, sql_alloc);

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				"select itemid,flags"
				" from items"
				" where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "itemid", itemids.values, itemids.values_num);

		result = DBselect("%s", sql);

		zbx_free(sql);

		while (NULL != (row = DBfetch(result)))
		{
			zbx_lld_item_t	*item = (zbx_lld_item_t *)zbx_malloc(NULL, sizeof(zbx_lld_item_t));

			ZBX_STR2UINT64(item->itemid, row[0]);
			ZBX_STR2UCHAR(item->flags, row[1]);

			zbx_vector_ptr_append(items, item);
		}
		DBfree_result(result);

		zbx_vector_ptr_sort(items, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);
	}

	zbx_vector_uint64_destroy(&itemids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
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
 * Purpose: finds already existing graph, using an item prototype and items   *
 *          already created by it                                             *
 *                                                                            *
 * Return value: upon successful completion return pointer to the graph       *
 *                                                                            *
 ******************************************************************************/
static zbx_lld_graph_t	*lld_graph_get(zbx_vector_ptr_t *graphs, const zbx_vector_ptr_t *item_links)
{
	int		i;
	zbx_lld_graph_t	*graph;

	for (i = 0; i < item_links->values_num; i++)
	{
		const zbx_lld_item_link_t	*item_link = (const zbx_lld_item_link_t *)item_links->values[i];

		if (NULL != (graph = lld_graph_by_item(graphs, item_link->itemid)))
			return graph;
	}

	return NULL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: finds already created item when itemid_proto is an item prototype *
 *          or return itemid_proto as itemid if it's a normal item            *
 *                                                                            *
 * Return value: SUCCEED if item successfully processed, FAIL - otherwise     *
 *                                                                            *
 ******************************************************************************/
static int	lld_item_get(zbx_uint64_t itemid_proto, const zbx_vector_ptr_t *items,
		const zbx_vector_ptr_t *item_links, zbx_uint64_t *itemid)
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

static int	lld_gitems_make(const zbx_vector_ptr_t *gitems_proto, zbx_vector_ptr_t *gitems,
		const zbx_vector_ptr_t *items, const zbx_vector_ptr_t *item_links, uint64_t grpahid)
{
	int			i, ret = FAIL;
	zbx_lld_gitem_t		*gitem;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	for (i = 0; i < gitems_proto->values_num; i++)
	{
		zbx_uint64_t		itemid;
		const zbx_lld_gitem_t	*gitem_proto = (const zbx_lld_gitem_t *)gitems_proto->values[i];

		if (SUCCEED != lld_item_get(gitem_proto->itemid, items, item_links, &itemid))
			goto out;

		if (i == gitems->values_num)
		{
			gitem = (zbx_lld_gitem_t *)zbx_malloc(NULL, sizeof(zbx_lld_gitem_t));

			gitem->gitemid = 0;
			gitem->itemid = itemid;
			gitem->itemid_orig = gitem->itemid;
			gitem->drawtype = gitem_proto->drawtype;
			gitem->drawtype_orig = gitem->drawtype;
			gitem->sortorder = gitem_proto->sortorder;
			gitem->sortorder_orig = gitem->sortorder;
			gitem->color = zbx_strdup(NULL, gitem_proto->color);
			gitem->color_orig = NULL;
			gitem->yaxisside = gitem_proto->yaxisside;
			gitem->yaxisside_orig = gitem->yaxisside;
			gitem->calc_fnc = gitem_proto->calc_fnc;
			gitem->calc_fnc_orig = gitem->calc_fnc;
			gitem->type = gitem_proto->type;
			gitem->type_orig = gitem->type;
			gitem->graphid = grpahid;

			gitem->flags = ZBX_FLAG_LLD_GITEM_DISCOVERED;

			zbx_vector_ptr_append(gitems, gitem);
		}
		else
		{
			gitem = (zbx_lld_gitem_t *)gitems->values[i];

			if (gitem->itemid != itemid)
			{
				gitem->itemid_orig = gitem->itemid;
				gitem->itemid = itemid;
				gitem->flags |= ZBX_FLAG_LLD_GITEM_UPDATE_ITEMID;
			}

			if (gitem->drawtype != gitem_proto->drawtype)
			{
				gitem->drawtype_orig = gitem->drawtype;
				gitem->drawtype = gitem_proto->drawtype;
				gitem->flags |= ZBX_FLAG_LLD_GITEM_UPDATE_DRAWTYPE;
			}

			if (gitem->sortorder != gitem_proto->sortorder)
			{
				gitem->sortorder_orig = gitem->sortorder;
				gitem->sortorder = gitem_proto->sortorder;
				gitem->flags |= ZBX_FLAG_LLD_GITEM_UPDATE_SORTORDER;
			}

			if (0 != strcmp(gitem->color, gitem_proto->color))
			{
				gitem->color_orig = gitem->color;
				gitem->color = zbx_strdup(NULL, gitem_proto->color);
				gitem->flags |= ZBX_FLAG_LLD_GITEM_UPDATE_COLOR;
			}

			if (gitem->yaxisside != gitem_proto->yaxisside)
			{
				gitem->yaxisside_orig = gitem->yaxisside;
				gitem->yaxisside = gitem_proto->yaxisside;
				gitem->flags |= ZBX_FLAG_LLD_GITEM_UPDATE_YAXISSIDE;
			}

			if (gitem->calc_fnc != gitem_proto->calc_fnc)
			{
				gitem->calc_fnc_orig = gitem->calc_fnc;
				gitem->calc_fnc = gitem_proto->calc_fnc;
				gitem->flags |= ZBX_FLAG_LLD_GITEM_UPDATE_CALC_FNC;
			}

			if (gitem->type != gitem_proto->type)
			{
				gitem->type_orig = gitem->type;
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
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: create a graph based on lld rule and add it to the list           *
 *                                                                            *
 ******************************************************************************/
static void 	lld_graph_make(const zbx_vector_ptr_t *gitems_proto, zbx_vector_ptr_t *graphs, zbx_vector_ptr_t *items,
		const char *name_proto, zbx_uint64_t ymin_itemid_proto, zbx_uint64_t ymax_itemid_proto,
		unsigned char discover_proto, const zbx_lld_row_t *lld_row, const zbx_vector_ptr_t *lld_macro_paths)
{
	zbx_lld_graph_t			*graph = NULL;
	const struct zbx_json_parse	*jp_row = &lld_row->jp_row;
	zbx_uint64_t			ymin_itemid, ymax_itemid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

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
		char	*buffer = zbx_strdup(NULL, name_proto);

		substitute_lld_macros(&buffer, jp_row, lld_macro_paths, ZBX_MACRO_ANY, NULL, 0);
		zbx_lrtrim(buffer, ZBX_WHITESPACE);

		if (0 != strcmp(graph->name, buffer))
		{
			graph->name_orig = graph->name;
			graph->name = buffer;
			buffer = NULL;
			graph->flags |= ZBX_FLAG_LLD_GRAPH_UPDATE_NAME;
		}
		else
			zbx_free(buffer);

		lld_override_graph(&lld_row->overrides, graph->name, &discover_proto);

		if (ZBX_PROTOTYPE_NO_DISCOVER == discover_proto)
			goto out;

		if (graph->ymin_itemid != ymin_itemid)
		{
			graph->ymin_itemid_orig = graph->ymin_itemid;
			graph->ymin_itemid = ymin_itemid;
			graph->flags |= ZBX_FLAG_LLD_GRAPH_UPDATE_YMIN_ITEMID;
		}

		if (graph->ymax_itemid != ymax_itemid)
		{
			graph->ymax_itemid_orig = graph->ymax_itemid;
			graph->ymax_itemid = ymax_itemid;
			graph->flags |= ZBX_FLAG_LLD_GRAPH_UPDATE_YMAX_ITEMID;
		}
	}
	else
	{
		graph = (zbx_lld_graph_t *)zbx_malloc(NULL, sizeof(zbx_lld_graph_t));

		graph->graphid = 0;
		graph->lastcheck = 0;
		graph->ts_delete = 0;

		graph->name = zbx_strdup(NULL, name_proto);
		graph->name_orig = NULL;
		substitute_lld_macros(&graph->name, jp_row, lld_macro_paths, ZBX_MACRO_ANY, NULL, 0);
		zbx_lrtrim(graph->name, ZBX_WHITESPACE);

		lld_override_graph(&lld_row->overrides, graph->name, &discover_proto);

		if (ZBX_PROTOTYPE_NO_DISCOVER == discover_proto)
		{
			zbx_free(graph->name);
			zbx_free(graph);
			goto out;
		}

		graph->ymin_itemid = ymin_itemid;
		graph->ymax_itemid = ymax_itemid;

		zbx_vector_ptr_create(&graph->gitems);

		graph->flags = ZBX_FLAG_LLD_GRAPH_UNSET;

		zbx_vector_ptr_append(graphs, graph);
	}

	if (SUCCEED != lld_gitems_make(gitems_proto, &graph->gitems, items, &lld_row->item_links, graph->graphid))
		return;

	graph->flags |= ZBX_FLAG_LLD_GRAPH_DISCOVERED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	lld_graphs_make(const zbx_vector_ptr_t *gitems_proto, zbx_vector_ptr_t *graphs, zbx_vector_ptr_t *items,
		const char *name_proto, zbx_uint64_t ymin_itemid_proto, zbx_uint64_t ymax_itemid_proto,
		unsigned char discover_proto, const zbx_vector_ptr_t *lld_rows, const zbx_vector_ptr_t *lld_macro_paths)
{
	int	i;

	for (i = 0; i < lld_rows->values_num; i++)
	{
		zbx_lld_row_t	*lld_row = (zbx_lld_row_t *)lld_rows->values[i];

		lld_graph_make(gitems_proto, graphs, items, name_proto, ymin_itemid_proto, ymax_itemid_proto,
				discover_proto, lld_row, lld_macro_paths);
	}

	zbx_vector_ptr_sort(graphs, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);
}

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
	else if (ZBX_FLAG_LLD_GRAPH_UPDATE_NAME == flag && '\0' == **field)
	{
		*error = zbx_strdcatf(*error, "Cannot %s graph: name is empty.\n",
				(0 != graph->graphid ? "update" : "create"));
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
 * Parameters: graphs - [IN] sorted list of graphs                            *
 *                                                                            *
 ******************************************************************************/
static void	lld_graphs_validate(zbx_uint64_t hostid, zbx_vector_ptr_t *graphs, char **error)
{
	int			i, j;
	zbx_lld_graph_t		*graph, *graph_b;
	zbx_vector_uint64_t	graphids;
	zbx_vector_str_t	names;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

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

		sql = (char *)zbx_malloc(sql, sql_alloc);

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

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: add or update graphs in database based on discovery rule          *
 *                                                                            *
 * Return value: SUCCEED - if graphs were successfully saved or saving        *
 *                         was not necessary                                  *
 *               FAIL    - graphs cannot be saved                             *
 *                                                                            *
 ******************************************************************************/
static int	lld_graphs_save(zbx_uint64_t hostid, zbx_uint64_t parent_graphid, zbx_vector_ptr_t *graphs, int width,
		int height, double yaxismin, double yaxismax, unsigned char show_work_period,
		unsigned char show_triggers, unsigned char graphtype, unsigned char show_legend, unsigned char show_3d,
		double percent_left, double percent_right, unsigned char ymin_type, unsigned char ymax_type)
{
	int			ret = SUCCEED, i, j, new_graphs = 0, upd_graphs = 0, new_gitems = 0;
	zbx_vector_ptr_t	upd_gitems; 	/* the ordered list of graphs_items which will be updated */
	zbx_vector_uint64_t	del_gitemids;

	zbx_uint64_t		graphid = 0, gitemid = 0;
	char			*sql = NULL;
	size_t			sql_alloc = 8 * ZBX_KIBIBYTE, sql_offset = 0;
	zbx_db_insert_t		db_insert, db_insert_gdiscovery, db_insert_gitems;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_ptr_create(&upd_gitems);
	zbx_vector_uint64_create(&del_gitemids);

	for (i = 0; i < graphs->values_num; i++)
	{
		const zbx_lld_graph_t	*graph = (const zbx_lld_graph_t *)graphs->values[i];

		if (0 == (graph->flags & ZBX_FLAG_LLD_GRAPH_DISCOVERED))
			continue;

		if (0 == graph->graphid)
			new_graphs++;
		else if (0 != (graph->flags & ZBX_FLAG_LLD_GRAPH_UPDATE))
			upd_graphs++;

		if (0 != graph->graphid)
		{
			zbx_audit_graph_create_entry(AUDIT_ACTION_UPDATE, graph->graphid, (NULL == graph->name_orig) ?
					graph->name : graph->name_orig, ZBX_FLAG_DISCOVERY_CREATED);
		}

		for (j = 0; j < graph->gitems.values_num; j++)
		{
			zbx_lld_gitem_t	*gitem = (zbx_lld_gitem_t *)graph->gitems.values[j];

			if (0 != (gitem->flags & ZBX_FLAG_LLD_GITEM_DELETE))
			{
				zbx_vector_uint64_append(&del_gitemids, gitem->gitemid);

				zbx_audit_graph_update_json_delete_gitems(graph->graphid,
						ZBX_FLAG_DISCOVERY_CREATED, gitem->gitemid);
				continue;
			}

			if (0 == (gitem->flags & ZBX_FLAG_LLD_GITEM_DISCOVERED))
				continue;

			gitem->graphid = graph->graphid;

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

	if (SUCCEED != (ret = DBlock_hostid(hostid)) ||
			SUCCEED != (ret = DBlock_graphid(parent_graphid)))
	{
		/* the host or graph prototype was removed while processing lld rule */
		DBrollback();
		goto out;
	}

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
		sql = (char *)zbx_malloc(sql, sql_alloc);
		DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);
	}

	for (i = 0; i < graphs->values_num; i++)
	{
		zbx_lld_graph_t	*graph = (zbx_lld_graph_t *)graphs->values[i];

		if (0 == (graph->flags & ZBX_FLAG_LLD_GRAPH_DISCOVERED))
			continue;

		if (0 == graph->graphid)
		{
			zbx_db_insert_add_values(&db_insert, graphid, graph->name, width, height, yaxismin, yaxismax,
					(int)show_work_period, (int)show_triggers, (int)graphtype, (int)show_legend,
					(int)show_3d, percent_left, percent_right, (int)ymin_type, graph->ymin_itemid,
					(int)ymax_type, graph->ymax_itemid, (int)ZBX_FLAG_DISCOVERY_CREATED);

			zbx_audit_graph_create_entry(AUDIT_ACTION_ADD, graphid, graph->name,
					ZBX_FLAG_DISCOVERY_CREATED);

			zbx_audit_graph_update_json_add_data(graphid, graph->name, width, height, yaxismin, yaxismax, 0,
					(int)show_work_period, (int)show_triggers, (int)graphtype, (int)show_legend,
					(int)show_3d, percent_left, percent_right, (int)ymin_type, (int)ymax_type,
					graph->ymin_itemid, graph->ymax_itemid,	ZBX_FLAG_DISCOVERY_CREATED, 0);

			zbx_db_insert_add_values(&db_insert_gdiscovery, graphid, parent_graphid);

			graph->graphid = graphid++;
		}
		else if (0 != (graph->flags & ZBX_FLAG_LLD_GRAPH_UPDATE))
		{
			const char	*d = "";

			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "update graphs set ");

			if (0 != (graph->flags & ZBX_FLAG_LLD_GRAPH_UPDATE_NAME))
			{
				char	*name_esc = DBdyn_escape_string(graph->name);

				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "name='%s'", name_esc);
				zbx_free(name_esc);
				d = ",";

				zbx_audit_graph_update_json_update_name(graph->graphid, (int)ZBX_FLAG_DISCOVERY_CREATED,
						graph->name_orig, graph->name);
			}

			if (0 != (graph->flags & ZBX_FLAG_LLD_GRAPH_UPDATE_WIDTH))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%swidth=%d", d, width);
				d = ",";

				zbx_audit_graph_update_json_update_width(graph->graphid,
						(int)ZBX_FLAG_DISCOVERY_CREATED, graph->width_orig, width);
			}

			if (0 != (graph->flags & ZBX_FLAG_LLD_GRAPH_UPDATE_HEIGHT))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%sheight=%d", d, height);
				d = ",";

				zbx_audit_graph_update_json_update_height(graph->graphid,
						(int)ZBX_FLAG_DISCOVERY_CREATED, graph->height_orig, height);
			}

			if (0 != (graph->flags & ZBX_FLAG_LLD_GRAPH_UPDATE_YAXISMIN))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%syaxismin=" ZBX_FS_DBL64_SQL, d,
						yaxismin);
				d = ",";

				zbx_audit_graph_update_json_update_yaxismin(graph->graphid,
						(int)ZBX_FLAG_DISCOVERY_CREATED, graph->yaxismin_orig, yaxismin);
			}

			if (0 != (graph->flags & ZBX_FLAG_LLD_GRAPH_UPDATE_YAXISMAX))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%syaxismax=" ZBX_FS_DBL64_SQL, d,
						yaxismax);
				d = ",";

				zbx_audit_graph_update_json_update_yaxismax(graph->graphid,
						(int)ZBX_FLAG_DISCOVERY_CREATED, graph->yaxismax_orig, yaxismax);
			}

			if (0 != (graph->flags & ZBX_FLAG_LLD_GRAPH_UPDATE_SHOW_WORK_PERIOD))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%sshow_work_period=%d", d,
						(int)show_work_period);
				d = ",";

				zbx_audit_graph_update_json_update_show_work_period(graph->graphid,
						(int)ZBX_FLAG_DISCOVERY_CREATED, graph->show_work_period_orig,
						(int)show_work_period);
			}

			if (0 != (graph->flags & ZBX_FLAG_LLD_GRAPH_UPDATE_SHOW_TRIGGERS))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%sshow_triggers=%d", d,
						(int)show_triggers);
				d = ",";

				zbx_audit_graph_update_json_update_show_triggers(graph->graphid,
						(int)ZBX_FLAG_DISCOVERY_CREATED, graph->show_triggers_orig,
						(int)show_triggers);
			}

			if (0 != (graph->flags & ZBX_FLAG_LLD_GRAPH_UPDATE_GRAPHTYPE))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%sgraphtype=%d", d,
						(int)graphtype);
				d = ",";

				zbx_audit_graph_update_json_update_graphtype(graph->graphid,
						(int)ZBX_FLAG_DISCOVERY_CREATED, (int)graph->graphtype_orig,
						(int)graphtype);
			}

			if (0 != (graph->flags & ZBX_FLAG_LLD_GRAPH_UPDATE_SHOW_LEGEND))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%sshow_legend=%d", d,
						(int)show_legend);
				d = ",";

				zbx_audit_graph_update_json_update_show_legend(graph->graphid,
						(int)ZBX_FLAG_DISCOVERY_CREATED, (int)graph->show_legend_orig,
						(int)show_legend);
			}

			if (0 != (graph->flags & ZBX_FLAG_LLD_GRAPH_UPDATE_SHOW_3D))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%sshow_3d=%d", d, (int)show_3d);
				d = ",";

				zbx_audit_graph_update_json_update_show_3d(graph->graphid,
						(int)ZBX_FLAG_DISCOVERY_CREATED, (int)graph->show_3d_orig,
						(int)show_3d);
			}

			if (0 != (graph->flags & ZBX_FLAG_LLD_GRAPH_UPDATE_PERCENT_LEFT))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%spercent_left=" ZBX_FS_DBL64_SQL, d,
						percent_left);
				d = ",";

				zbx_audit_graph_update_json_update_percent_left(graph->graphid,
						(int)ZBX_FLAG_DISCOVERY_CREATED, graph->percent_left_orig,
						percent_left);
			}

			if (0 != (graph->flags & ZBX_FLAG_LLD_GRAPH_UPDATE_PERCENT_RIGHT))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
						"%spercent_right=" ZBX_FS_DBL64_SQL, d, percent_right);
				d = ",";

				zbx_audit_graph_update_json_update_percent_right(graph->graphid,
						(int)ZBX_FLAG_DISCOVERY_CREATED, graph->percent_right_orig,
						percent_right);
			}

			if (0 != (graph->flags & ZBX_FLAG_LLD_GRAPH_UPDATE_YMIN_TYPE))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%symin_type=%d", d,
						(int)ymin_type);
				d = ",";

				zbx_audit_graph_update_json_update_ymin_type(graph->graphid,
						(int)ZBX_FLAG_DISCOVERY_CREATED, (int)graph->ymin_type_orig,
						(int)ymin_type);
			}

			if (0 != (graph->flags & ZBX_FLAG_LLD_GRAPH_UPDATE_YMIN_ITEMID))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%symin_itemid=%s", d,
						DBsql_id_ins(graph->ymin_itemid));
				d = ",";

				zbx_audit_graph_update_json_update_ymin_itemid(graph->graphid,
						(int)ZBX_FLAG_DISCOVERY_CREATED, graph->ymin_itemid_orig,
						graph->ymin_itemid);
			}

			if (0 != (graph->flags & ZBX_FLAG_LLD_GRAPH_UPDATE_YMAX_TYPE))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%symax_type=%d", d,
						(int)ymax_type);
				d = ",";

				zbx_audit_graph_update_json_update_ymax_type(graph->graphid,
						(int)ZBX_FLAG_DISCOVERY_CREATED, (int)graph->ymax_type_orig,
						(int)ymax_type);
			}

			if (0 != (graph->flags & ZBX_FLAG_LLD_GRAPH_UPDATE_YMAX_ITEMID))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%symax_itemid=%s", d,
						DBsql_id_ins(graph->ymax_itemid));

				zbx_audit_graph_update_json_update_ymax_itemid(graph->graphid,
						(int)ZBX_FLAG_DISCOVERY_CREATED, graph->ymax_itemid_orig,
						graph->ymax_itemid);
			}

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " where graphid=" ZBX_FS_UI64 ";\n",
					graph->graphid);
		}

		for (j = 0; j < graph->gitems.values_num; j++)
		{
			zbx_lld_gitem_t	*gitem = (zbx_lld_gitem_t *)graph->gitems.values[j];

			if (0 != (gitem->flags & ZBX_FLAG_LLD_GITEM_DELETE))
				continue;

			if (0 == (gitem->flags & ZBX_FLAG_LLD_GITEM_DISCOVERED))
				continue;

			if (0 == gitem->gitemid)
			{
				zbx_db_insert_add_values(&db_insert_gitems, gitemid, graph->graphid, gitem->itemid,
						(int)gitem->drawtype, gitem->sortorder, gitem->color,
						(int)gitem->yaxisside, (int)gitem->calc_fnc, (int)gitem->type);

				zbx_audit_graph_update_json_add_gitems(graph->graphid, (int)ZBX_FLAG_DISCOVERY_CREATED,
						gitemid, (int)gitem->drawtype, gitem->sortorder, gitem->color,
						(int)gitem->yaxisside, (int)gitem->calc_fnc,(int)gitem->type,
						gitem->itemid);

				gitem->gitemid = gitemid++;
			}
		}
	}

	for (i = 0; i < upd_gitems.values_num; i++)
	{
		const char		*d = "";
		const zbx_lld_gitem_t	*gitem = (const zbx_lld_gitem_t *)upd_gitems.values[i];

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "update graphs_items set ");

		if (0 != (gitem->flags & ZBX_FLAG_LLD_GITEM_UPDATE_ITEMID))
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "itemid=" ZBX_FS_UI64, gitem->itemid);
			d = ",";

			zbx_audit_graph_update_json_update_gitem_update_itemid(gitem->graphid,
					(int)ZBX_FLAG_DISCOVERY_CREATED, gitem->gitemid, gitem->itemid_orig,
					gitem->itemid);
		}

		if (0 != (gitem->flags & ZBX_FLAG_LLD_GITEM_UPDATE_DRAWTYPE))
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%sdrawtype=%d", d, (int)gitem->drawtype);
			d = ",";

			zbx_audit_graph_update_json_update_gitem_update_drawtype(gitem->graphid,
					(int)ZBX_FLAG_DISCOVERY_CREATED, gitem->gitemid, (int)gitem->drawtype_orig,
					(int)gitem->drawtype);
		}

		if (0 != (gitem->flags & ZBX_FLAG_LLD_GITEM_UPDATE_SORTORDER))
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%ssortorder=%d", d, gitem->sortorder);
			d = ",";

			zbx_audit_graph_update_json_update_gitem_update_sortorder(gitem->graphid,
					(int)ZBX_FLAG_DISCOVERY_CREATED, gitem->gitemid, gitem->sortorder_orig,
					gitem->sortorder);
		}

		if (0 != (gitem->flags & ZBX_FLAG_LLD_GITEM_UPDATE_COLOR))
		{
			char	*color_esc = DBdyn_escape_string(gitem->color);

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%scolor='%s'", d, color_esc);
			zbx_free(color_esc);
			d = ",";

			zbx_audit_graph_update_json_update_gitem_update_color(gitem->graphid,
					(int)ZBX_FLAG_DISCOVERY_CREATED, gitem->gitemid, gitem->color_orig,
					gitem->color);
		}

		if (0 != (gitem->flags & ZBX_FLAG_LLD_GITEM_UPDATE_YAXISSIDE))
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%syaxisside=%d", d,
					(int)gitem->yaxisside);
			d = ",";

			zbx_audit_graph_update_json_update_gitem_update_yaxisside(gitem->graphid,
					(int)ZBX_FLAG_DISCOVERY_CREATED, gitem->gitemid, (int)gitem->yaxisside_orig,
					(int)gitem->yaxisside);
		}

		if (0 != (gitem->flags & ZBX_FLAG_LLD_GITEM_UPDATE_CALC_FNC))
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%scalc_fnc=%d", d, (int)gitem->calc_fnc);
			d = ",";

			zbx_audit_graph_update_json_update_gitem_update_calc_fnc(gitem->graphid,
					(int)ZBX_FLAG_DISCOVERY_CREATED, gitem->gitemid, (int)gitem->calc_fnc_orig,
					(int)gitem->calc_fnc);
		}

		if (0 != (gitem->flags & ZBX_FLAG_LLD_GITEM_UPDATE_TYPE))
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%stype=%d", d, (int)gitem->type);

			zbx_audit_graph_update_json_update_gitem_update_type(gitem->graphid,
					(int)ZBX_FLAG_DISCOVERY_CREATED, gitem->gitemid, (int)gitem->type_orig,
					(int)gitem->type);
		}

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

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return ret;
}

static	void	get_graph_info(const void *object, zbx_uint64_t *id, int *discovery_flag, int *lastcheck,
		int *ts_delete, const char **name)
{
	const zbx_lld_graph_t	*graph;

	graph = (const zbx_lld_graph_t *)object;

	*id = graph->graphid;
	*discovery_flag = graph->flags & ZBX_FLAG_LLD_GRAPH_DISCOVERED;
	*lastcheck = graph->lastcheck;
	*ts_delete = graph->ts_delete;
	*name = graph->name;
}

/******************************************************************************
 *                                                                            *
 * Purpose: add or update graphs for discovery item                           *
 *                                                                            *
 * Parameters: hostid  - [IN] host identifier from database                   *
 *             agent   - [IN] discovery item identifier from database         *
 *             jp_data - [IN] received data                                   *
 *                                                                            *
 * Return value: SUCCEED - if graphs were successfully added/updated or       *
 *                         adding/updating was not necessary                  *
 *               FAIL    - graphs cannot be added/updated                     *
 *                                                                            *
 ******************************************************************************/
int	lld_update_graphs(zbx_uint64_t hostid, zbx_uint64_t lld_ruleid, const zbx_vector_ptr_t *lld_rows,
		const zbx_vector_ptr_t *lld_macro_paths, char **error, int lifetime, int lastcheck)
{
	int			ret = SUCCEED;
	DB_RESULT		result;
	DB_ROW			row;
	zbx_vector_ptr_t	graphs;
	zbx_vector_ptr_t	gitems_proto;
	zbx_vector_ptr_t	items;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_ptr_create(&graphs);		/* list of graphs which were created or will be created or */
						/* updated by the graph prototype */
	zbx_vector_ptr_create(&gitems_proto);	/* list of graphs_items which are used by the graph prototype */
	zbx_vector_ptr_create(&items);		/* list of items which are related to the graph prototype */

	result = DBselect(
			"select distinct g.graphid,g.name,g.width,g.height,g.yaxismin,g.yaxismax,g.show_work_period,"
				"g.show_triggers,g.graphtype,g.show_legend,g.show_3d,g.percent_left,g.percent_right,"
				"g.ymin_type,g.ymin_itemid,g.ymax_type,g.ymax_itemid,g.discover"
			" from graphs g,graphs_items gi,items i,item_discovery id"
			" where g.graphid=gi.graphid"
				" and gi.itemid=i.itemid"
				" and i.itemid=id.itemid"
				" and id.parent_itemid=" ZBX_FS_UI64,
			lld_ruleid);

	while (SUCCEED == ret && NULL != (row = DBfetch(result)))
	{
		zbx_uint64_t	parent_graphid, ymin_itemid_proto, ymax_itemid_proto;
		const char	*name_proto;
		int		width, height;
		double		yaxismin, yaxismax, percent_left, percent_right;
		unsigned char	show_work_period, show_triggers, graphtype, show_legend, show_3d,
				ymin_type, ymax_type, discover_proto;

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
		ZBX_STR2UCHAR(discover_proto, row[17]);

		lld_graphs_get(parent_graphid, &graphs, width, height, yaxismin, yaxismax, show_work_period,
				show_triggers, graphtype, show_legend, show_3d, percent_left, percent_right,
				ymin_type, ymax_type);
		lld_gitems_get(parent_graphid, &gitems_proto, &graphs);
		lld_items_get(&gitems_proto, ymin_itemid_proto, ymax_itemid_proto, &items);

		/* making graphs */

		lld_graphs_make(&gitems_proto, &graphs, &items, name_proto, ymin_itemid_proto, ymax_itemid_proto,
				discover_proto, lld_rows, lld_macro_paths);
		lld_graphs_validate(hostid, &graphs, error);
		ret = lld_graphs_save(hostid, parent_graphid, &graphs, width, height, yaxismin, yaxismax,
				show_work_period, show_triggers, graphtype, show_legend, show_3d, percent_left,
				percent_right, ymin_type, ymax_type);

		lld_remove_lost_objects("graph_discovery", "graphid", &graphs, lifetime, lastcheck, DBdelete_graphs,
				get_graph_info);

		lld_items_free(&items);
		lld_gitems_free(&gitems_proto);
		lld_graphs_free(&graphs);
	}
	DBfree_result(result);

	zbx_vector_ptr_destroy(&items);
	zbx_vector_ptr_destroy(&gitems_proto);
	zbx_vector_ptr_destroy(&graphs);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return ret;
}
