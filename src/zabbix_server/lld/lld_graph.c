/*
** Copyright (C) 2001-2024 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

#include "lld.h"
#include "zbxexpression.h"

#include "zbxdbwrap.h"
#include "audit/zbxaudit.h"
#include "audit/zbxaudit_graph.h"
#include "zbxnum.h"
#include "zbxstr.h"
#include "zbxalgo.h"
#include "zbxcacheconfig.h"
#include "zbxdb.h"
#include "zbxdbhigh.h"

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

ZBX_PTR_VECTOR_DECL(lld_gitem_ptr, zbx_lld_gitem_t*)
ZBX_PTR_VECTOR_IMPL(lld_gitem_ptr, zbx_lld_gitem_t*)

static int	lld_gitem_compare_func(const void *d1, const void *d2)
{
	const zbx_lld_gitem_t  *lld_gitem_1 = *(const zbx_lld_gitem_t **)d1;
	const zbx_lld_gitem_t  *lld_gitem_2 = *(const zbx_lld_gitem_t **)d2;

	ZBX_RETURN_IF_NOT_EQUAL(lld_gitem_1->gitemid, lld_gitem_2->gitemid);

	return 0;
}

typedef struct
{
	zbx_uint64_t			graphid;
	char				*name;
	char				*name_orig;
	zbx_uint64_t			ymin_itemid_orig;
	zbx_uint64_t			ymin_itemid;
	zbx_uint64_t			ymax_itemid_orig;
	zbx_uint64_t			ymax_itemid;
	int				width_orig;
	int				height_orig;
	double				yaxismin_orig;
	double				yaxismax_orig;
	unsigned char			show_work_period_orig;
	unsigned char			show_triggers_orig;
	unsigned char			graphtype_orig;
	unsigned char			show_legend_orig;
	unsigned char			show_3d_orig;
	double				percent_left_orig;
	double				percent_right_orig;
	unsigned char			ymin_type_orig;
	unsigned char			ymax_type_orig;
	zbx_vector_lld_gitem_ptr_t	gitems;
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
	zbx_uint64_t			flags;
	int				lastcheck;
	unsigned char			discovery_status;
	int				ts_delete;
}
zbx_lld_graph_t;

ZBX_PTR_VECTOR_DECL(lld_graph_ptr, zbx_lld_graph_t*)
ZBX_PTR_VECTOR_IMPL(lld_graph_ptr, zbx_lld_graph_t*)

typedef struct
{
	zbx_uint64_t	itemid;
	zbx_lld_graph_t	*graph;
}
zbx_lld_item_graph_t;

typedef struct
{
	zbx_lld_graph_t	*graph;
}
zbx_lld_graph_ref_t;

static zbx_hash_t	lld_graph_ref_name_hash(const void *d)
{
	const zbx_lld_graph_ref_t	*ref = (zbx_lld_graph_ref_t *)d;

	return ZBX_DEFAULT_STRING_HASH_FUNC(ref->graph->name);
}

static int	lld_graph_ref_name_compare(const void *d1, const void *d2)
{
	const zbx_lld_graph_ref_t	*ref1 = (zbx_lld_graph_ref_t *)d1;
	const zbx_lld_graph_ref_t	*ref2 = (zbx_lld_graph_ref_t *)d2;

	return strcmp(ref1->graph->name, ref2->graph->name);
}

static zbx_hash_t	lld_graph_ref_id_hash(const void *d)
{
	const zbx_lld_graph_ref_t	*ref = (zbx_lld_graph_ref_t *)d;

	return ZBX_DEFAULT_UINT64_HASH_FUNC(&ref->graph->graphid);
}

static int	lld_graph_ref_id_compare(const void *d1, const void *d2)
{
	const zbx_lld_graph_ref_t	*ref1 = (zbx_lld_graph_ref_t *)d1;
	const zbx_lld_graph_ref_t	*ref2 = (zbx_lld_graph_ref_t *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(ref1->graph->graphid, ref2->graph->graphid);
	return 0;
}

static int	lld_graph_compare_func(const void *d1, const void *d2)
{
	const zbx_lld_graph_t	*lld_graph_1 = *(const zbx_lld_graph_t **)d1;
	const zbx_lld_graph_t	*lld_graph_2 = *(const zbx_lld_graph_t **)d2;

	ZBX_RETURN_IF_NOT_EQUAL(lld_graph_1->graphid, lld_graph_2->graphid);

	return 0;
}

static void	lld_item_free(zbx_lld_item_t *item)
{
	zbx_free(item);
}

static void	lld_items_free(zbx_vector_lld_item_ptr_t *items)
{
	while (0 != items->values_num)
		lld_item_free(items->values[--items->values_num]);
}

static void	lld_gitem_free(zbx_lld_gitem_t *gitem)
{
	zbx_free(gitem->color);
	zbx_free(gitem->color_orig);
	zbx_free(gitem);
}

static void	lld_gitems_free(zbx_vector_lld_gitem_ptr_t *gitems)
{
	while (0 != gitems->values_num)
		lld_gitem_free(gitems->values[--gitems->values_num]);
}

static void	lld_graph_free(zbx_lld_graph_t *graph)
{
	lld_gitems_free(&graph->gitems);
	zbx_vector_lld_gitem_ptr_destroy(&graph->gitems);
	zbx_free(graph->name_orig);
	zbx_free(graph->name);
	zbx_free(graph);
}

static void	lld_graphs_free(zbx_vector_lld_graph_ptr_t *graphs)
{
	while (0 != graphs->values_num)
		lld_graph_free(graphs->values[--graphs->values_num]);
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieves graphs which were created by specified graph prototype  *
 *                                                                            *
 * Parameters: parent_graphid - [IN] graph prototype id                       *
 *             graphs         - [OUT] sorted list of graphs                   *
 *             ...            - [IN] new values which should be updated if    *
 *                                   different from original                  *
 *                                                                            *
 ******************************************************************************/
static void	lld_graphs_get(zbx_uint64_t parent_graphid, zbx_vector_lld_graph_ptr_t *graphs, int width, int height,
		double yaxismin, double yaxismax, unsigned char show_work_period, unsigned char show_triggers,
		unsigned char graphtype, unsigned char show_legend, unsigned char show_3d, double percent_left,
		double percent_right, unsigned char ymin_type, unsigned char ymax_type)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	result = zbx_db_select(
			"select g.graphid,g.name,g.width,g.height,g.yaxismin,g.yaxismax,g.show_work_period,"
				"g.show_triggers,g.graphtype,g.show_legend,g.show_3d,g.percent_left,g.percent_right,"
				"g.ymin_type,g.ymin_itemid,g.ymax_type,g.ymax_itemid,gd.lastcheck,gd.status,"
				"gd.ts_delete"
			" from graphs g,graph_discovery gd"
			" where g.graphid=gd.graphid"
				" and gd.parent_graphid=" ZBX_FS_UI64,
			parent_graphid);

	while (NULL != (row = zbx_db_fetch(result)))
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
		ZBX_STR2UCHAR(graph->discovery_status, row[18]);
		graph->ts_delete = atoi(row[19]);

		zbx_vector_lld_gitem_ptr_create(&graph->gitems);

		zbx_vector_lld_graph_ptr_append(graphs, graph);
	}
	zbx_db_free_result(result);

	zbx_vector_lld_graph_ptr_sort(graphs, lld_graph_compare_func);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: Retrieves graphs_items which are used by graph prototype and by   *
 *          selected graphs.                                                  *
 *                                                                            *
 ******************************************************************************/
static void	lld_gitems_get(zbx_uint64_t parent_graphid, zbx_vector_lld_gitem_ptr_t *gitems_proto,
		zbx_vector_lld_graph_ptr_t *graphs)
{
	zbx_lld_graph_t		*graph;
	zbx_vector_uint64_t	graphids;
	zbx_db_large_query_t	query;
	zbx_db_row_t		row;
	char			*sql = NULL;
	size_t			sql_alloc = 256, sql_offset = 0;
	zbx_hashset_t		graph_index;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_hashset_create(&graph_index, graphs->values_num, lld_graph_ref_id_hash, lld_graph_ref_id_compare);
	zbx_vector_uint64_create(&graphids);
	zbx_vector_uint64_append(&graphids, parent_graphid);

	for (int i = 0; i < graphs->values_num; i++)
	{
		zbx_lld_graph_ref_t	ref_local;

		graph = graphs->values[i];

		zbx_vector_uint64_append(&graphids, graph->graphid);

		ref_local.graph = graph;
		zbx_hashset_insert(&graph_index, &ref_local, sizeof(ref_local));
	}

	zbx_vector_uint64_sort(&graphids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	sql = (char *)zbx_malloc(sql, sql_alloc);

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select gitemid,graphid,itemid,drawtype,sortorder,color,yaxisside,calc_fnc,type"
			" from graphs_items"
			" where");

	zbx_db_large_query_prepare_uint(&query, &sql, &sql_alloc, &sql_offset, "graphid", &graphids);

	while (NULL != (row = zbx_db_large_query_fetch(&query)))
	{
		zbx_uint64_t		graphid;
		zbx_lld_gitem_t		*gitem = (zbx_lld_gitem_t *)zbx_malloc(NULL, sizeof(zbx_lld_gitem_t));
		zbx_lld_graph_ref_t	*ref;

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
			zbx_vector_lld_gitem_ptr_append(gitems_proto, gitem);
		}
		else
		{
			zbx_lld_graph_t		cmp = {.graphid = graphid};
			zbx_lld_graph_ref_t	ref_local = {.graph = &cmp};

			if (NULL != (ref = (zbx_lld_graph_ref_t *)zbx_hashset_search(&graph_index, &ref_local)))
			{
				zbx_vector_lld_gitem_ptr_append(&ref->graph->gitems, gitem);
			}
			else
			{
				THIS_SHOULD_NEVER_HAPPEN;
				lld_gitem_free(gitem);
			}
		}
	}

	zbx_db_large_query_clear(&query);

	zbx_free(sql);

	zbx_vector_lld_gitem_ptr_sort(gitems_proto, lld_gitem_compare_func);

	for (int i = 0; i < graphs->values_num; i++)
	{
		graph = graphs->values[i];

		zbx_vector_lld_gitem_ptr_sort(&graph->gitems, lld_gitem_compare_func);
	}

	zbx_vector_uint64_destroy(&graphids);
	zbx_hashset_destroy(&graph_index);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: Returns list of items which are related to graph prototype.       *
 *                                                                            *
 * Parameters: gitems_proto      - [IN] graph prototype's graphs_items        *
 *             ymin_itemid_proto - [IN] graph prototype's ymin_itemid         *
 *             ymax_itemid_proto - [IN] graph prototype's ymax_itemid         *
 *             items             - [OUT] sorted list of items                 *
 *                                                                            *
 ******************************************************************************/
static void	lld_items_get(const zbx_vector_lld_gitem_ptr_t *gitems_proto, zbx_uint64_t ymin_itemid_proto,
		zbx_uint64_t ymax_itemid_proto, zbx_vector_lld_item_ptr_t *items)
{
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	zbx_vector_uint64_t	itemids;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&itemids);

	for (int i = 0; i < gitems_proto->values_num; i++)
	{
		const zbx_lld_gitem_t	*gitem = gitems_proto->values[i];

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
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "itemid", itemids.values, itemids.values_num);

		result = zbx_db_select("%s", sql);

		zbx_free(sql);

		while (NULL != (row = zbx_db_fetch(result)))
		{
			zbx_lld_item_t	*item = (zbx_lld_item_t *)zbx_malloc(NULL, sizeof(zbx_lld_item_t));

			ZBX_STR2UINT64(item->itemid, row[0]);
			ZBX_STR2UCHAR(item->flags, row[1]);

			zbx_vector_lld_item_ptr_append(items, item);
		}
		zbx_db_free_result(result);

		zbx_vector_lld_item_ptr_sort(items, lld_item_compare_func);
	}

	zbx_vector_uint64_destroy(&itemids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s(): items:" ZBX_FS_UI64 , __func__, items->values_num);
}

/******************************************************************************
 *                                                                            *
 * Purpose: Finds already existing graph, using an item prototype and items   *
 *          already created by it.                                            *
 *                                                                            *
 * Return value: upon successful completion returns pointer to graph          *
 *                                                                            *
 ******************************************************************************/
static zbx_lld_graph_t	*lld_graph_get(zbx_hashset_t *graph_index, const zbx_vector_lld_item_link_ptr_t *item_links)
{
	for (int i = 0; i < item_links->values_num; i++)
	{
		const zbx_lld_item_link_t	*item_link = item_links->values[i];
		zbx_lld_item_graph_t		*ig;

		if (NULL != (ig = (zbx_lld_item_graph_t *)zbx_hashset_search(graph_index, &item_link->itemid)))
			return ig->graph;
	}

	return NULL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: Finds already created item when itemid_proto is an item prototype *
 *          or return itemid_proto as itemid if it's a normal item.           *
 *                                                                            *
 * Return value: SUCCEED if item successfully processed, FAIL - otherwise     *
 *                                                                            *
 ******************************************************************************/
static int	lld_item_get(zbx_uint64_t itemid_proto, const zbx_vector_lld_item_ptr_t *items,
		const zbx_vector_lld_item_link_ptr_t *item_links, zbx_uint64_t *itemid)
{
	int			index;
	zbx_lld_item_t		*item_proto, lld_item_cmp = {.itemid = itemid_proto};
	zbx_lld_item_link_t	*item_link;

	if (FAIL == (index = zbx_vector_lld_item_ptr_bsearch(items, &lld_item_cmp, lld_item_compare_func)))
		return FAIL;

	item_proto = items->values[index];

	if (0 != (item_proto->flags & ZBX_FLAG_DISCOVERY_PROTOTYPE))
	{
		zbx_lld_item_link_t	lld_item_link_cmp = {.parent_itemid = item_proto->itemid};

		index = zbx_vector_lld_item_link_ptr_bsearch(item_links, &lld_item_link_cmp,
				lld_item_link_compare_func);

		if (FAIL == index)
			return FAIL;

		item_link = item_links->values[index];

		*itemid = item_link->itemid;
	}
	else
		*itemid = item_proto->itemid;

	return SUCCEED;
}

static int	lld_gitems_make(const zbx_vector_lld_gitem_ptr_t *gitems_proto, zbx_vector_lld_gitem_ptr_t *gitems,
		const zbx_vector_lld_item_ptr_t *items, const zbx_vector_lld_item_link_ptr_t *item_links,
		uint64_t graphid)
{
	int			i, ret = FAIL;
	zbx_lld_gitem_t		*gitem;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	for (i = 0; i < gitems_proto->values_num; i++)
	{
		zbx_uint64_t		itemid;
		const zbx_lld_gitem_t	*gitem_proto = gitems_proto->values[i];

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
			gitem->graphid = graphid;

			gitem->flags = ZBX_FLAG_LLD_GITEM_DISCOVERED;

			zbx_vector_lld_gitem_ptr_append(gitems, gitem);
		}
		else
		{
			gitem = gitems->values[i];

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
		gitem = gitems->values[i];

		gitem->flags |= ZBX_FLAG_LLD_GITEM_DELETE;
	}

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: creates graph based on LLD rule and adds it to list               *
 *                                                                            *
 ******************************************************************************/
static void	lld_graph_make(const zbx_vector_lld_gitem_ptr_t *gitems_proto, zbx_vector_lld_graph_ptr_t *graphs,
		zbx_vector_lld_item_ptr_t *items, zbx_hashset_t *graph_index, const char *name_proto,
		zbx_uint64_t ymin_itemid_proto, zbx_uint64_t ymax_itemid_proto, unsigned char discover_proto,
		int lastcheck, const zbx_lld_row_t *lld_row, const zbx_vector_lld_macro_path_ptr_t *lld_macro_paths)
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

	if (NULL != (graph = lld_graph_get(graph_index, &lld_row->item_links)))
	{
		char	*buffer = zbx_strdup(NULL, name_proto);

		zbx_substitute_lld_macros(&buffer, jp_row, lld_macro_paths, ZBX_MACRO_ANY, NULL, 0);
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
		graph->lastcheck = lastcheck;
		graph->discovery_status = ZBX_LLD_DISCOVERY_STATUS_NORMAL;
		graph->ts_delete = 0;

		graph->name = zbx_strdup(NULL, name_proto);
		graph->name_orig = NULL;
		zbx_substitute_lld_macros(&graph->name, jp_row, lld_macro_paths, ZBX_MACRO_ANY, NULL, 0);
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

		zbx_vector_lld_gitem_ptr_create(&graph->gitems);

		graph->flags = ZBX_FLAG_LLD_GRAPH_UNSET;

		zbx_vector_lld_graph_ptr_append(graphs, graph);
	}

	if (SUCCEED != lld_gitems_make(gitems_proto, &graph->gitems, items, &lld_row->item_links, graph->graphid))
		return;

	graph->flags |= ZBX_FLAG_LLD_GRAPH_DISCOVERED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	lld_graph_index_update(zbx_hashset_t *graph_index, zbx_lld_graph_t *graph)
{
	for (int i = 0; i < graph->gitems.values_num; i++)
	{
		if (0 == graph->gitems.values[i]->itemid)
			continue;

		zbx_lld_item_graph_t	ig_local = {.itemid = graph->gitems.values[i]->itemid, .graph = graph};

		zbx_hashset_insert(graph_index, &ig_local, sizeof(ig_local));
	}
}

static void	lld_graphs_make(const zbx_vector_lld_gitem_ptr_t *gitems_proto, zbx_vector_lld_graph_ptr_t *graphs,
		zbx_vector_lld_item_ptr_t *items, const char *name_proto, zbx_uint64_t ymin_itemid_proto,
		zbx_uint64_t ymax_itemid_proto, unsigned char discover_proto, int lastcheck,
		const zbx_vector_lld_row_ptr_t *lld_rows, const zbx_vector_lld_macro_path_ptr_t *lld_macro_paths)
{
	zbx_hashset_t	graph_index;

	zbx_hashset_create(&graph_index, graphs->values_num, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	for (int i = 0; i < graphs->values_num; i++)
		lld_graph_index_update(&graph_index, graphs->values[i]);

	for (int i = 0; i < lld_rows->values_num; i++)
	{
		zbx_lld_row_t	*lld_row = lld_rows->values[i];

		lld_graph_make(gitems_proto, graphs, items, &graph_index, name_proto, ymin_itemid_proto,
				ymax_itemid_proto, discover_proto, lastcheck, lld_row, lld_macro_paths);
	}

	zbx_vector_lld_graph_ptr_sort(graphs, lld_graph_compare_func);

	zbx_hashset_destroy(&graph_index);
}

static void	lld_validate_graph_field(zbx_lld_graph_t *graph, char **field, char **field_orig, zbx_uint64_t flag,
		size_t field_len, char **error)
{
	/* only new graphs or graphs with changed data will be validated */
	if (0 != graph->graphid && 0 == (graph->flags & flag))
		return;

	if (SUCCEED != zbx_is_utf8(*field))
	{
		zbx_replace_invalid_utf8(*field);
		*error = zbx_strdcatf(*error, "Cannot %s graph \"%s\": value \"%s\" has invalid UTF-8 sequence.\n",
				(0 != graph->graphid ? "update" : "create"), graph->name, *field);
	}
	else if (zbx_strlen_utf8(*field) > field_len)
	{
		char	value_short[VALUE_ERRMSG_MAX * ZBX_MAX_BYTES_IN_UTF8_CHAR + 1];

		zbx_truncate_value(*field, VALUE_ERRMSG_MAX, value_short, sizeof(value_short));

		if (0 != (flag & ZBX_FLAG_LLD_GRAPH_UPDATE_NAME))
		{
			*error = zbx_strdcatf(*error, "Cannot %s graph \"%s\": name is too long.\n",
					(0 != graph->graphid ? "update" : "create"), value_short);
		}
		else
		{
			*error = zbx_strdcatf(*error, "Cannot %s graph \"%s\": value \"%s\" is too long.\n",
					(0 != graph->graphid ? "update" : "create"), graph->name, value_short);
		}
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
 * Purpose: check for duplicated keys in database                             *
 *                                                                            *
 *****************************************************************************/
static void	lld_graphs_validate_db_name(zbx_uint64_t hostid, zbx_vector_lld_graph_ptr_t *graphs,
		zbx_hashset_t *name_index, char **error)
{
	zbx_db_large_query_t	query;
	zbx_db_row_t		row;
	zbx_vector_str_t	names;
	char			*sql = NULL;
	size_t			sql_alloc = 256, sql_offset = 0;

	zbx_vector_str_create(&names);		/* list of item keys */

	for (int i = 0; i < graphs->values_num; i++)
	{
		zbx_lld_graph_t		*graph;

		graph = graphs->values[i];

		if (0 == (graph->flags & ZBX_FLAG_LLD_GRAPH_DISCOVERED))
			continue;

		if (0 == graph->graphid || 0 != (graph->flags & ZBX_FLAG_LLD_GRAPH_UPDATE_NAME))
			zbx_vector_str_append(&names, graph->name);
	}

	if (0 == names.values_num)
		goto out;

	sql = (char *)zbx_malloc(sql, sql_alloc);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select g.graphid,g.name"
			" from graphs g,graphs_items gi,items i"
			" where g.graphid=gi.graphid"
				" and gi.itemid=i.itemid"
				" and i.hostid=" ZBX_FS_UI64
				" and",
			hostid);

	zbx_db_large_query_prepare_str(&query, &sql, &sql_alloc, &sql_offset, "g.name", &names);

	while (NULL != (row = zbx_db_large_query_fetch(&query)))
	{
		zbx_lld_graph_t		*graph, graph_stub;
		zbx_lld_graph_ref_t	*ref, ref_local = {.graph = &graph_stub};

		ZBX_STR2UINT64(graph_stub.graphid, row[0]);
		graph_stub.name = row[1];

		if (NULL == (ref = (zbx_lld_graph_ref_t *)zbx_hashset_search(name_index, &ref_local)) ||
				ref->graph->graphid == ref_local.graph->graphid ||
				0 == (ref->graph->flags & ZBX_FLAG_LLD_GRAPH_DISCOVERED))
		{
			continue;
		}

		graph = ref->graph;

		*error = zbx_strdcatf(*error, "Cannot %s graph:"
				" graph with the same name \"%s\" already exists.\n",
				(0 != graph->graphid ? "update" : "create"), graph->name);

		if (0 != graph->graphid)
		{
			lld_field_str_rollback(&graph->name, &graph->name_orig, &graph->flags,
					ZBX_FLAG_LLD_GRAPH_UPDATE_NAME);
		}
		else
			graph->flags &= ~ZBX_FLAG_LLD_ITEM_DISCOVERED;
	}

	zbx_db_large_query_clear(&query);

	zbx_free(sql);
out:
	zbx_vector_str_destroy(&names);
}

/******************************************************************************
 *                                                                            *
 * Purpose: validates sorted graph                                            *
 *                                                                            *
 * Parameters: hostid - [IN]                                                  *
 *             graphs - [IN] sorted list of graphs                            *
 *             error  - [OUT]                                                 *
 *                                                                            *
 ******************************************************************************/
static void	lld_graphs_validate(zbx_uint64_t hostid, zbx_vector_lld_graph_ptr_t *graphs, char **error)
{
	zbx_lld_graph_t		*graph;
	zbx_hashset_t		name_index;
	zbx_lld_graph_ref_t	ref_local, *ref;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_hashset_create(&name_index, graphs->values_num, lld_graph_ref_name_hash, lld_graph_ref_name_compare);

	/* checking a validity of the fields */

	for (int i = 0; i < graphs->values_num; i++)
	{
		graph = graphs->values[i];

		if (0 == (graph->flags & ZBX_FLAG_LLD_GRAPH_DISCOVERED))
			continue;

		lld_validate_graph_field(graph, &graph->name, &graph->name_orig,
				ZBX_FLAG_LLD_GRAPH_UPDATE_NAME, ZBX_GRAPH_NAME_LEN, error);

		/* index existing graphs without pending name updates */
		if (0 != graph->graphid && 0 == (graph->flags & ZBX_FLAG_LLD_GRAPH_UPDATE_NAME))
		{
			ref_local.graph = graph;
			(void)zbx_hashset_insert(&name_index, &ref_local, sizeof(ref_local));
		}
	}

	/* checking duplicated graph names */
	for (int i = 0; i < graphs->values_num; i++)
	{
		graph = graphs->values[i];

		if (0 == (graph->flags & ZBX_FLAG_LLD_GRAPH_DISCOVERED))
			continue;

		/* only new graphs or graphs with changed name will be validated */
		if (0 != graph->graphid && 0 == (graph->flags & ZBX_FLAG_LLD_GRAPH_UPDATE_NAME))
			continue;

		ref_local.graph = graph;
		ref = (zbx_lld_graph_ref_t *)zbx_hashset_insert(&name_index, &ref_local, sizeof(ref_local));

		if (ref->graph != graph)	/* another graph with the same name was already indexed */
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
		}
	}

	lld_graphs_validate_db_name(hostid, graphs, &name_index, error);

	zbx_hashset_destroy(&name_index);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: adds or updates graphs in database based on discovery rule        *
 *                                                                            *
 * Return value: SUCCEED - if graphs were successfully saved or saving        *
 *                         was not necessary                                  *
 *               FAIL    - graphs cannot be saved                             *
 *                                                                            *
 ******************************************************************************/
static int	lld_graphs_save(zbx_uint64_t hostid, zbx_uint64_t parent_graphid, zbx_vector_lld_graph_ptr_t *graphs,
		int width, int height, double yaxismin, double yaxismax, unsigned char show_work_period,
		unsigned char show_triggers, unsigned char graphtype, unsigned char show_legend, unsigned char show_3d,
		double percent_left, double percent_right, unsigned char ymin_type, unsigned char ymax_type)
{
	int				ret = SUCCEED, new_graphs = 0, upd_graphs = 0, new_gitems = 0;
	zbx_vector_lld_gitem_ptr_t	upd_gitems;	/* the ordered list of graphs_items which will be updated */
	zbx_vector_uint64_t		del_gitemids;

	zbx_uint64_t		graphid = 0, gitemid = 0;
	char			*sql = NULL;
	size_t			sql_alloc = 8 * ZBX_KIBIBYTE, sql_offset = 0;
	zbx_db_insert_t		db_insert, db_insert_gdiscovery, db_insert_gitems;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_lld_gitem_ptr_create(&upd_gitems);
	zbx_vector_uint64_create(&del_gitemids);

	for (int i = 0; i < graphs->values_num; i++)
	{
		const zbx_lld_graph_t	*graph = graphs->values[i];

		if (0 == (graph->flags & ZBX_FLAG_LLD_GRAPH_DISCOVERED))
			continue;

		if (0 == graph->graphid)
			new_graphs++;
		else if (0 != (graph->flags & ZBX_FLAG_LLD_GRAPH_UPDATE))
			upd_graphs++;

		if (0 != graph->graphid)
		{
			zbx_audit_graph_create_entry(ZBX_AUDIT_LLD_CONTEXT,ZBX_AUDIT_ACTION_UPDATE, graph->graphid,
					(NULL == graph->name_orig) ? graph->name : graph->name_orig,
					ZBX_FLAG_DISCOVERY_CREATED);
		}

		for (int j = 0; j < graph->gitems.values_num; j++)
		{
			zbx_lld_gitem_t	*gitem = graph->gitems.values[j];

			if (0 != (gitem->flags & ZBX_FLAG_LLD_GITEM_DELETE))
			{
				zbx_vector_uint64_append(&del_gitemids, gitem->gitemid);

				zbx_audit_graph_update_json_delete_gitems(ZBX_AUDIT_LLD_CONTEXT, graph->graphid,
						ZBX_FLAG_DISCOVERY_CREATED, gitem->gitemid);
				continue;
			}

			if (0 == (gitem->flags & ZBX_FLAG_LLD_GITEM_DISCOVERED))
				continue;

			gitem->graphid = graph->graphid;

			if (0 == gitem->gitemid)
				new_gitems++;
			else if (0 != (gitem->flags & ZBX_FLAG_LLD_GITEM_UPDATE))
				zbx_vector_lld_gitem_ptr_append(&upd_gitems, gitem);
		}
	}

	if (0 == new_graphs && 0 == new_gitems && 0 == upd_graphs && 0 == upd_gitems.values_num &&
			0 == del_gitemids.values_num)
	{
		goto out;
	}

	zbx_db_begin();

	if (SUCCEED != (ret = zbx_db_lock_hostid(hostid)) ||
			SUCCEED != (ret = zbx_db_lock_graphid(parent_graphid)))
	{
		/* the host or graph prototype was removed while processing lld rule */
		zbx_db_rollback();
		goto out;
	}

	if (0 != new_graphs)
	{
		graphid = zbx_db_get_maxid_num("graphs", new_graphs);

		zbx_db_insert_prepare(&db_insert, "graphs", "graphid", "name", "width", "height", "yaxismin",
				"yaxismax", "show_work_period", "show_triggers", "graphtype", "show_legend", "show_3d",
				"percent_left", "percent_right", "ymin_type", "ymin_itemid", "ymax_type",
				"ymax_itemid", "flags", (char *)NULL);

		zbx_db_insert_prepare(&db_insert_gdiscovery, "graph_discovery", "graphid", "parent_graphid",
				"lastcheck", (char *)NULL);
	}

	if (0 != new_gitems)
	{
		gitemid = zbx_db_get_maxid_num("graphs_items", new_gitems);

		zbx_db_insert_prepare(&db_insert_gitems, "graphs_items", "gitemid", "graphid", "itemid", "drawtype",
				"sortorder", "color", "yaxisside", "calc_fnc", "type", (char *)NULL);
	}

	if (0 != upd_graphs || 0 != upd_gitems.values_num)
	{
		sql = (char *)zbx_malloc(sql, sql_alloc);
	}

	for (int i = 0; i < graphs->values_num; i++)
	{
		zbx_lld_graph_t	*graph = graphs->values[i];

		if (0 == (graph->flags & ZBX_FLAG_LLD_GRAPH_DISCOVERED))
			continue;

		if (0 == graph->graphid)
		{
			zbx_db_insert_add_values(&db_insert, graphid, graph->name, width, height, yaxismin, yaxismax,
					(int)show_work_period, (int)show_triggers, (int)graphtype, (int)show_legend,
					(int)show_3d, percent_left, percent_right, (int)ymin_type, graph->ymin_itemid,
					(int)ymax_type, graph->ymax_itemid, (int)ZBX_FLAG_DISCOVERY_CREATED);

			zbx_audit_graph_create_entry(ZBX_AUDIT_LLD_CONTEXT, ZBX_AUDIT_ACTION_ADD, graphid, graph->name,
					ZBX_FLAG_DISCOVERY_CREATED);

			zbx_audit_graph_update_json_add_data(ZBX_AUDIT_LLD_CONTEXT, graphid, graph->name, width,
					height, yaxismin, yaxismax, 0, (int)show_work_period, (int)show_triggers,
					(int)graphtype, (int)show_legend, (int)show_3d, percent_left, percent_right,
					(int)ymin_type, (int)ymax_type, graph->ymin_itemid, graph->ymax_itemid,
					ZBX_FLAG_DISCOVERY_CREATED, 0);

			zbx_db_insert_add_values(&db_insert_gdiscovery, graphid, parent_graphid, graph->lastcheck);

			graph->graphid = graphid++;
		}
		else if (0 != (graph->flags & ZBX_FLAG_LLD_GRAPH_UPDATE))
		{
			const char	*d = "";

			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "update graphs set ");

			if (0 != (graph->flags & ZBX_FLAG_LLD_GRAPH_UPDATE_NAME))
			{
				char	*name_esc = zbx_db_dyn_escape_string(graph->name);

				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "name='%s'", name_esc);
				zbx_free(name_esc);
				d = ",";

				zbx_audit_graph_update_json_update_name(ZBX_AUDIT_LLD_CONTEXT, graph->graphid,
						(int)ZBX_FLAG_DISCOVERY_CREATED, graph->name_orig, graph->name);
			}

			if (0 != (graph->flags & ZBX_FLAG_LLD_GRAPH_UPDATE_WIDTH))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%swidth=%d", d, width);
				d = ",";

				zbx_audit_graph_update_json_update_width(ZBX_AUDIT_LLD_CONTEXT, graph->graphid,
						(int)ZBX_FLAG_DISCOVERY_CREATED, graph->width_orig, width);
			}

			if (0 != (graph->flags & ZBX_FLAG_LLD_GRAPH_UPDATE_HEIGHT))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%sheight=%d", d, height);
				d = ",";

				zbx_audit_graph_update_json_update_height(ZBX_AUDIT_LLD_CONTEXT, graph->graphid,
						(int)ZBX_FLAG_DISCOVERY_CREATED, graph->height_orig, height);
			}

			if (0 != (graph->flags & ZBX_FLAG_LLD_GRAPH_UPDATE_YAXISMIN))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%syaxismin=" ZBX_FS_DBL64_SQL, d,
						yaxismin);
				d = ",";

				zbx_audit_graph_update_json_update_yaxismin(ZBX_AUDIT_LLD_CONTEXT, graph->graphid,
						(int)ZBX_FLAG_DISCOVERY_CREATED, graph->yaxismin_orig, yaxismin);
			}

			if (0 != (graph->flags & ZBX_FLAG_LLD_GRAPH_UPDATE_YAXISMAX))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%syaxismax=" ZBX_FS_DBL64_SQL, d,
						yaxismax);
				d = ",";

				zbx_audit_graph_update_json_update_yaxismax(ZBX_AUDIT_LLD_CONTEXT, graph->graphid,
						(int)ZBX_FLAG_DISCOVERY_CREATED, graph->yaxismax_orig, yaxismax);
			}

			if (0 != (graph->flags & ZBX_FLAG_LLD_GRAPH_UPDATE_SHOW_WORK_PERIOD))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%sshow_work_period=%d", d,
						(int)show_work_period);
				d = ",";

				zbx_audit_graph_update_json_update_show_work_period(ZBX_AUDIT_LLD_CONTEXT,
						graph->graphid, (int)ZBX_FLAG_DISCOVERY_CREATED,
						graph->show_work_period_orig, (int)show_work_period);
			}

			if (0 != (graph->flags & ZBX_FLAG_LLD_GRAPH_UPDATE_SHOW_TRIGGERS))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%sshow_triggers=%d", d,
						(int)show_triggers);
				d = ",";

				zbx_audit_graph_update_json_update_show_triggers(ZBX_AUDIT_LLD_CONTEXT, graph->graphid,
						(int)ZBX_FLAG_DISCOVERY_CREATED, graph->show_triggers_orig,
						(int)show_triggers);
			}

			if (0 != (graph->flags & ZBX_FLAG_LLD_GRAPH_UPDATE_GRAPHTYPE))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%sgraphtype=%d", d,
						(int)graphtype);
				d = ",";

				zbx_audit_graph_update_json_update_graphtype(ZBX_AUDIT_LLD_CONTEXT, graph->graphid,
						(int)ZBX_FLAG_DISCOVERY_CREATED, (int)graph->graphtype_orig,
						(int)graphtype);
			}

			if (0 != (graph->flags & ZBX_FLAG_LLD_GRAPH_UPDATE_SHOW_LEGEND))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%sshow_legend=%d", d,
						(int)show_legend);
				d = ",";

				zbx_audit_graph_update_json_update_show_legend(ZBX_AUDIT_LLD_CONTEXT, graph->graphid,
						(int)ZBX_FLAG_DISCOVERY_CREATED, (int)graph->show_legend_orig,
						(int)show_legend);
			}

			if (0 != (graph->flags & ZBX_FLAG_LLD_GRAPH_UPDATE_SHOW_3D))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%sshow_3d=%d", d, (int)show_3d);
				d = ",";

				zbx_audit_graph_update_json_update_show_3d(ZBX_AUDIT_LLD_CONTEXT, graph->graphid,
						(int)ZBX_FLAG_DISCOVERY_CREATED, (int)graph->show_3d_orig,
						(int)show_3d);
			}

			if (0 != (graph->flags & ZBX_FLAG_LLD_GRAPH_UPDATE_PERCENT_LEFT))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%spercent_left=" ZBX_FS_DBL64_SQL, d,
						percent_left);
				d = ",";

				zbx_audit_graph_update_json_update_percent_left(ZBX_AUDIT_LLD_CONTEXT, graph->graphid,
						(int)ZBX_FLAG_DISCOVERY_CREATED, graph->percent_left_orig,
						percent_left);
			}

			if (0 != (graph->flags & ZBX_FLAG_LLD_GRAPH_UPDATE_PERCENT_RIGHT))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
						"%spercent_right=" ZBX_FS_DBL64_SQL, d, percent_right);
				d = ",";

				zbx_audit_graph_update_json_update_percent_right(ZBX_AUDIT_LLD_CONTEXT, graph->graphid,
						(int)ZBX_FLAG_DISCOVERY_CREATED, graph->percent_right_orig,
						percent_right);
			}

			if (0 != (graph->flags & ZBX_FLAG_LLD_GRAPH_UPDATE_YMIN_TYPE))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%symin_type=%d", d,
						(int)ymin_type);
				d = ",";

				zbx_audit_graph_update_json_update_ymin_type(ZBX_AUDIT_LLD_CONTEXT, graph->graphid,
						(int)ZBX_FLAG_DISCOVERY_CREATED, (int)graph->ymin_type_orig,
						(int)ymin_type);
			}

			if (0 != (graph->flags & ZBX_FLAG_LLD_GRAPH_UPDATE_YMIN_ITEMID))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%symin_itemid=%s", d,
						zbx_db_sql_id_ins(graph->ymin_itemid));
				d = ",";

				zbx_audit_graph_update_json_update_ymin_itemid(ZBX_AUDIT_LLD_CONTEXT, graph->graphid,
						(int)ZBX_FLAG_DISCOVERY_CREATED, graph->ymin_itemid_orig,
						graph->ymin_itemid);
			}

			if (0 != (graph->flags & ZBX_FLAG_LLD_GRAPH_UPDATE_YMAX_TYPE))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%symax_type=%d", d,
						(int)ymax_type);
				d = ",";

				zbx_audit_graph_update_json_update_ymax_type(ZBX_AUDIT_LLD_CONTEXT, graph->graphid,
						(int)ZBX_FLAG_DISCOVERY_CREATED, (int)graph->ymax_type_orig,
						(int)ymax_type);
			}

			if (0 != (graph->flags & ZBX_FLAG_LLD_GRAPH_UPDATE_YMAX_ITEMID))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%symax_itemid=%s", d,
						zbx_db_sql_id_ins(graph->ymax_itemid));

				zbx_audit_graph_update_json_update_ymax_itemid(ZBX_AUDIT_LLD_CONTEXT, graph->graphid,
						(int)ZBX_FLAG_DISCOVERY_CREATED, graph->ymax_itemid_orig,
						graph->ymax_itemid);
			}

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " where graphid=" ZBX_FS_UI64 ";\n",
					graph->graphid);
		}

		for (int j = 0; j < graph->gitems.values_num; j++)
		{
			zbx_lld_gitem_t	*gitem = graph->gitems.values[j];

			if (0 != (gitem->flags & ZBX_FLAG_LLD_GITEM_DELETE))
				continue;

			if (0 == (gitem->flags & ZBX_FLAG_LLD_GITEM_DISCOVERED))
				continue;

			if (0 == gitem->gitemid)
			{
				zbx_db_insert_add_values(&db_insert_gitems, gitemid, graph->graphid, gitem->itemid,
						(int)gitem->drawtype, gitem->sortorder, gitem->color,
						(int)gitem->yaxisside, (int)gitem->calc_fnc, (int)gitem->type);

				zbx_audit_graph_update_json_add_gitems(ZBX_AUDIT_LLD_CONTEXT, graph->graphid,
						(int)ZBX_FLAG_DISCOVERY_CREATED, gitemid, (int)gitem->drawtype,
						gitem->sortorder, gitem->color, (int)gitem->yaxisside,
						(int)gitem->calc_fnc, (int)gitem->type, gitem->itemid);

				gitem->gitemid = gitemid++;
			}
		}
	}

	for (int i = 0; i < upd_gitems.values_num; i++)
	{
		const char		*d = "";
		const zbx_lld_gitem_t	*gitem = (const zbx_lld_gitem_t *)upd_gitems.values[i];

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "update graphs_items set ");

		if (0 != (gitem->flags & ZBX_FLAG_LLD_GITEM_UPDATE_ITEMID))
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "itemid=" ZBX_FS_UI64, gitem->itemid);
			d = ",";

			zbx_audit_graph_update_json_update_gitem_update_itemid(ZBX_AUDIT_LLD_CONTEXT, gitem->graphid,
					(int)ZBX_FLAG_DISCOVERY_CREATED, gitem->gitemid, gitem->itemid_orig,
					gitem->itemid);
		}

		if (0 != (gitem->flags & ZBX_FLAG_LLD_GITEM_UPDATE_DRAWTYPE))
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%sdrawtype=%d", d, (int)gitem->drawtype);
			d = ",";

			zbx_audit_graph_update_json_update_gitem_update_drawtype(ZBX_AUDIT_LLD_CONTEXT, gitem->graphid,
					(int)ZBX_FLAG_DISCOVERY_CREATED, gitem->gitemid, (int)gitem->drawtype_orig,
					(int)gitem->drawtype);
		}

		if (0 != (gitem->flags & ZBX_FLAG_LLD_GITEM_UPDATE_SORTORDER))
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%ssortorder=%d", d, gitem->sortorder);
			d = ",";

			zbx_audit_graph_update_json_update_gitem_update_sortorder(ZBX_AUDIT_LLD_CONTEXT, gitem->graphid,
					(int)ZBX_FLAG_DISCOVERY_CREATED, gitem->gitemid, gitem->sortorder_orig,
					gitem->sortorder);
		}

		if (0 != (gitem->flags & ZBX_FLAG_LLD_GITEM_UPDATE_COLOR))
		{
			char	*color_esc = zbx_db_dyn_escape_string(gitem->color);

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%scolor='%s'", d, color_esc);
			zbx_free(color_esc);
			d = ",";

			zbx_audit_graph_update_json_update_gitem_update_color(ZBX_AUDIT_LLD_CONTEXT, gitem->graphid,
					(int)ZBX_FLAG_DISCOVERY_CREATED, gitem->gitemid, gitem->color_orig,
					gitem->color);
		}

		if (0 != (gitem->flags & ZBX_FLAG_LLD_GITEM_UPDATE_YAXISSIDE))
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%syaxisside=%d", d,
					(int)gitem->yaxisside);
			d = ",";

			zbx_audit_graph_update_json_update_gitem_update_yaxisside(ZBX_AUDIT_LLD_CONTEXT, gitem->graphid,
					(int)ZBX_FLAG_DISCOVERY_CREATED, gitem->gitemid, (int)gitem->yaxisside_orig,
					(int)gitem->yaxisside);
		}

		if (0 != (gitem->flags & ZBX_FLAG_LLD_GITEM_UPDATE_CALC_FNC))
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%scalc_fnc=%d", d, (int)gitem->calc_fnc);
			d = ",";

			zbx_audit_graph_update_json_update_gitem_update_calc_fnc(ZBX_AUDIT_LLD_CONTEXT, gitem->graphid,
					(int)ZBX_FLAG_DISCOVERY_CREATED, gitem->gitemid, (int)gitem->calc_fnc_orig,
					(int)gitem->calc_fnc);
		}

		if (0 != (gitem->flags & ZBX_FLAG_LLD_GITEM_UPDATE_TYPE))
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%stype=%d", d, (int)gitem->type);

			zbx_audit_graph_update_json_update_gitem_update_type(ZBX_AUDIT_LLD_CONTEXT, gitem->graphid,
					(int)ZBX_FLAG_DISCOVERY_CREATED, gitem->gitemid, (int)gitem->type_orig,
					(int)gitem->type);
		}

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " where gitemid=" ZBX_FS_UI64 ";\n",
				gitem->gitemid);
	}

	if (0 != del_gitemids.values_num)
	{
		zbx_vector_uint64_sort(&del_gitemids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_db_execute_multiple_query("delete from graphs_items where", "gitemid", &del_gitemids);
	}

	if (0 != upd_graphs || 0 != upd_gitems.values_num)
	{
		zbx_db_execute("%s", sql);
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

	zbx_db_commit();
out:
	zbx_vector_uint64_destroy(&del_gitemids);
	zbx_vector_lld_gitem_ptr_destroy(&upd_gitems);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: process lost graph resources                                      *
 *                                                                            *
 ******************************************************************************/
static void	lld_process_lost_graphs(zbx_vector_lld_graph_ptr_t *graphs, const zbx_lld_lifetime_t *lifetime, int now)
{
	zbx_hashset_t	discoveries;

	zbx_hashset_create(&discoveries, (size_t)graphs->values_num, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	for (int i = 0; i < graphs->values_num; i++)
	{
		zbx_lld_graph_t	*graph = graphs->values[i];
		zbx_lld_discovery_t	*discovery;

		discovery = lld_add_discovery(&discoveries, graph->graphid, graph->name);

		if (0 != (graph->flags & ZBX_FLAG_LLD_GRAPH_DISCOVERED))
		{
			lld_process_discovered_object(discovery, graph->discovery_status, graph->ts_delete,
					graph->lastcheck, now);
			continue;
		}

		/* process lost graphs */

		lld_process_lost_object(discovery, ZBX_LLD_OBJECT_STATUS_ENABLED, graph->lastcheck, now, lifetime,
				graph->discovery_status, 0, graph->ts_delete);
	}

	lld_flush_discoveries(&discoveries, "graphid", NULL, "graph_discovery", now, NULL, zbx_db_delete_graphs,
			zbx_audit_graph_create_entry, NULL);

	zbx_hashset_destroy(&discoveries);
}

/******************************************************************************
 *                                                                            *
 * Purpose: adds or updates graphs for discovery item                         *
 *                                                                            *
 * Return value: SUCCEED - if graphs were successfully added/updated or       *
 *                         adding/updating was not necessary                  *
 *               FAIL    - graphs cannot be added/updated                     *
 *                                                                            *
 ******************************************************************************/
int	lld_update_graphs(zbx_uint64_t hostid, zbx_uint64_t lld_ruleid, const zbx_vector_lld_row_ptr_t *lld_rows,
		const zbx_vector_lld_macro_path_ptr_t *lld_macro_paths, char **error,
		const zbx_lld_lifetime_t *lifetime, int lastcheck)
{
	int				ret = SUCCEED;
	zbx_db_result_t			result;
	zbx_db_row_t			row;
	zbx_vector_lld_graph_ptr_t	graphs;
	zbx_vector_lld_gitem_ptr_t	gitems_proto;
	zbx_vector_lld_item_ptr_t	items;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_lld_graph_ptr_create(&graphs);	/* list of graphs which were created or will be created or */
							/* updated by the graph prototype */
	zbx_vector_lld_gitem_ptr_create(&gitems_proto);	/* list of graphs_items which are used by the graph prototype */
	zbx_vector_lld_item_ptr_create(&items);		/* list of items which are related to the graph prototype */

	result = zbx_db_select(
			"select distinct g.graphid,g.name,g.width,g.height,g.yaxismin,g.yaxismax,g.show_work_period,"
				"g.show_triggers,g.graphtype,g.show_legend,g.show_3d,g.percent_left,g.percent_right,"
				"g.ymin_type,g.ymin_itemid,g.ymax_type,g.ymax_itemid,g.discover"
			" from graphs g,graphs_items gi,items i,item_discovery id"
			" where g.graphid=gi.graphid"
				" and gi.itemid=i.itemid"
				" and i.itemid=id.itemid"
				" and id.parent_itemid=" ZBX_FS_UI64,
			lld_ruleid);

	while (SUCCEED == ret && NULL != (row = zbx_db_fetch(result)))
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
				discover_proto, lastcheck, lld_rows, lld_macro_paths);

		lld_graphs_validate(hostid, &graphs, error);

		ret = lld_graphs_save(hostid, parent_graphid, &graphs, width, height, yaxismin, yaxismax,
				show_work_period, show_triggers, graphtype, show_legend, show_3d, percent_left,
				percent_right, ymin_type, ymax_type);

		lld_process_lost_graphs(&graphs, lifetime, lastcheck);

		lld_items_free(&items);
		lld_gitems_free(&gitems_proto);
		lld_graphs_free(&graphs);
	}
	zbx_db_free_result(result);

	zbx_vector_lld_item_ptr_destroy(&items);
	zbx_vector_lld_gitem_ptr_destroy(&gitems_proto);
	zbx_vector_lld_graph_ptr_destroy(&graphs);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return ret;
}
