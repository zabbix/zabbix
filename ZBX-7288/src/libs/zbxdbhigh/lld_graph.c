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

#include "lld.h"
#include "db.h"
#include "log.h"
#include "zbxalgo.h"
#include "zbxserver.h"

typedef struct
{
	zbx_uint64_t	graphid;
	char		*name;
	ZBX_GRAPH_ITEMS	*gitems;
	ZBX_GRAPH_ITEMS	*del_gitems;
	zbx_uint64_t	ymin_itemid;
	zbx_uint64_t	ymax_itemid;
	size_t		gitems_num;
	size_t		del_gitems_num;
}
zbx_lld_graph_t;

static void	DBlld_clean_graphs(zbx_vector_ptr_t *graphs)
{
	zbx_lld_graph_t	*graph;

	while (0 != graphs->values_num)
	{
		graph = (zbx_lld_graph_t *)graphs->values[--graphs->values_num];

		zbx_free(graph->del_gitems);
		zbx_free(graph->gitems);
		zbx_free(graph->name);
		zbx_free(graph);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: DBlld_graph_exists                                               *
 *                                                                            *
 * Purpose: check if graph exists                                             *
 *                                                                            *
 ******************************************************************************/
static int	DBlld_graph_exists(zbx_uint64_t hostid, zbx_uint64_t graphid, const char *name,
		zbx_vector_ptr_t *graphs)
{
	char		*name_esc, *sql = NULL;
	size_t		sql_alloc = 256, sql_offset = 0;
	DB_RESULT	result;
	int		i, res = FAIL;

	for (i = 0; i < graphs->values_num; i++)
	{
		if (0 == strcmp(name, ((zbx_lld_graph_t *)graphs->values[i])->name))
			return SUCCEED;
	}

	sql = zbx_malloc(sql, sql_alloc);
	name_esc = DBdyn_escape_string_len(name, GRAPH_NAME_LEN);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select distinct g.graphid"
			" from graphs g,graphs_items gi,items i"
			" where g.graphid=gi.graphid"
				" and gi.itemid=i.itemid"
				" and i.hostid=" ZBX_FS_UI64
				" and g.name='%s'",
			hostid, name_esc);

	if (0 != graphid)
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " and g.graphid<>" ZBX_FS_UI64, graphid);

	result = DBselect("%s", sql);

	if (NULL != DBfetch(result))
		res = SUCCEED;
	DBfree_result(result);

	zbx_free(name_esc);
	zbx_free(sql);

	return res;
}

static int	DBlld_make_graph(zbx_uint64_t hostid, zbx_uint64_t parent_graphid, zbx_vector_ptr_t *graphs,
		const char *name_proto, ZBX_GRAPH_ITEMS *gitems_proto, int gitems_proto_num,
		unsigned char ymin_type, zbx_uint64_t ymin_itemid, unsigned char ymin_flags, const char *ymin_key_proto,
		unsigned char ymax_type, zbx_uint64_t ymax_itemid, unsigned char ymax_flags, const char *ymax_key_proto,
		struct zbx_json_parse *jp_row, char **error)
{
	const char	*__function_name = "DBlld_make_graph";

	DB_RESULT	result;
	DB_ROW		row;
	char		*name_esc;
	int		res = SUCCEED, i;
	zbx_lld_graph_t	*graph;
	ZBX_GRAPH_ITEMS	*gitem;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	graph = zbx_calloc(NULL, 1, sizeof(zbx_lld_graph_t));
	graph->name = zbx_strdup(NULL, name_proto);
	substitute_discovery_macros(&graph->name, jp_row, ZBX_MACRO_SIMPLE, NULL, 0);

	name_esc = DBdyn_escape_string_len(graph->name, GRAPH_NAME_LEN);

	result = DBselect(
			"select distinct g.graphid"
			" from graphs g,graph_discovery gd"
			" where g.graphid=gd.graphid"
				" and gd.parent_graphid=" ZBX_FS_UI64
				" and g.name='%s'",
			parent_graphid, name_esc);

	if (NULL != (row = DBfetch(result)))
		ZBX_STR2UINT64(graph->graphid, row[0]);
	DBfree_result(result);

	if (0 == graph->graphid)
	{
		result = DBselect(
				"select distinct g.graphid,gd.name,g.name"
				" from graphs g,graph_discovery gd"
				" where g.graphid=gd.graphid"
					" and gd.parent_graphid=" ZBX_FS_UI64,
				parent_graphid);

		while (NULL != (row = DBfetch(result)))
		{
			char	*old_name = NULL;

			old_name = zbx_strdup(old_name, row[1]);
			substitute_discovery_macros(&old_name, jp_row, ZBX_MACRO_SIMPLE, NULL, 0);

			if (0 == strcmp(old_name, row[2]))
				ZBX_STR2UINT64(graph->graphid, row[0]);

			zbx_free(old_name);

			if (0 != graph->graphid)
				break;
		}
		DBfree_result(result);
	}

	if (SUCCEED == DBlld_graph_exists(hostid, graph->graphid, graph->name, graphs))
	{
		*error = zbx_strdcatf(*error, "Cannot %s graph \"%s\": graph already exists\n",
				0 != graph->graphid ? "update" : "create", graph->name);
		res = FAIL;
		goto out;
	}

	if (0 != gitems_proto_num)
	{
		size_t	gitems_alloc;

		graph->gitems_num = gitems_proto_num;
		gitems_alloc = graph->gitems_num * sizeof(ZBX_GRAPH_ITEMS);
		graph->gitems = zbx_malloc(graph->gitems, gitems_alloc);
		memcpy(graph->gitems, gitems_proto, gitems_alloc);

		for (i = 0; i < graph->gitems_num; i++)
		{
			gitem = &graph->gitems[i];

			if (0 != (ZBX_FLAG_DISCOVERY_PROTOTYPE & gitem->flags))
			{
				if (FAIL == (res = DBlld_get_item(hostid, gitem->key, jp_row, &gitem->itemid)))
					break;
			}
		}

		/* sort by itemid */
		qsort(graph->gitems, graph->gitems_num, sizeof(ZBX_GRAPH_ITEMS), ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	}

	if (FAIL == res)
		goto out;

	if (GRAPH_YAXIS_TYPE_ITEM_VALUE == ymin_type)
	{
		graph->ymin_itemid = ymin_itemid;

		if (0 != (ZBX_FLAG_DISCOVERY_PROTOTYPE & ymin_flags) &&
				FAIL == (res = DBlld_get_item(hostid, ymin_key_proto, jp_row, &graph->ymin_itemid)))
		{
			goto out;
		}
	}

	if (GRAPH_YAXIS_TYPE_ITEM_VALUE == ymax_type)
	{
		graph->ymax_itemid = ymax_itemid;

		if (0 != (ZBX_FLAG_DISCOVERY_PROTOTYPE & ymax_flags) &&
				FAIL == (res = DBlld_get_item(hostid, ymax_key_proto, jp_row, &graph->ymax_itemid)))
		{
			goto out;
		}
	}

	if (0 != graph->graphid)
	{
		char	*sql = NULL;
		size_t	sql_alloc = ZBX_KIBIBYTE, sql_offset = 0, sz, del_gitems_alloc = 0;
		int	idx;

		sql = zbx_malloc(sql, sql_alloc);

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"select gi.gitemid,i.itemid,i.key_,gi.drawtype,gi.sortorder,gi.color,"
					"gi.yaxisside,gi.calc_fnc,gi.type,i.flags"
				" from graphs_items gi,items i"
				" where gi.itemid=i.itemid"
					" and gi.graphid=" ZBX_FS_UI64
				" order by i.itemid",
				graph->graphid);

		DBget_graphitems(sql, &graph->del_gitems, &del_gitems_alloc, &graph->del_gitems_num);

		/* Run through graph items that must exist removing them from */
		/* del_items. What's left in del_items will be removed later. */
		for (i = 0; i < graph->gitems_num; i++)
		{
			if (NULL != (gitem = bsearch(&graph->gitems[i].itemid, graph->del_gitems, graph->del_gitems_num,
					sizeof(ZBX_GRAPH_ITEMS), ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
			{
				graph->gitems[i].gitemid = gitem->gitemid;

				graph->del_gitems_num--;

				idx = (int)(gitem - graph->del_gitems);

				if (0 != (sz = (graph->del_gitems_num - idx) * sizeof(ZBX_GRAPH_ITEMS)))
					memmove(&graph->del_gitems[idx], &graph->del_gitems[idx + 1], sz);
			}
		}
		zbx_free(sql);
	}

	zbx_vector_ptr_append(graphs, graph);
out:
	if (FAIL == res)
	{
		zbx_free(graph->gitems);
		zbx_free(graph->name);
		zbx_free(graph);
	}

	zbx_free(name_esc);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(res));

	return res;
}

static void	DBlld_save_graphs(zbx_vector_ptr_t *graphs, int width, int height, double yaxismin, double yaxismax,
		unsigned char show_work_period, unsigned char show_triggers, unsigned char graphtype,
		unsigned char show_legend, unsigned char show_3d, double percent_left, double percent_right,
		unsigned char ymin_type, unsigned char ymax_type, zbx_uint64_t parent_graphid,
		const char *name_proto_esc)
{
	int		i, j, new_graphs = 0, new_graphs_items = 0;
	zbx_lld_graph_t	*graph;
	zbx_uint64_t	graphid = 0, graphdiscoveryid = 0, gitemid = 0;
	char		*sql1 = NULL, *sql2 = NULL, *sql3 = NULL, *sql4 = NULL,
			*name_esc;
	size_t		sql1_alloc = 8 * ZBX_KIBIBYTE, sql1_offset = 0,
			sql2_alloc = 2 * ZBX_KIBIBYTE, sql2_offset = 0,
			sql3_alloc = 2 * ZBX_KIBIBYTE, sql3_offset = 0,
			sql4_alloc = 8 * ZBX_KIBIBYTE, sql4_offset = 0;
	const char	*ins_graphs_sql =
			"insert into graphs"
			" (graphid,name,width,height,yaxismin,yaxismax,show_work_period,"
				"show_triggers,graphtype,show_legend,show_3d,percent_left,"
				"percent_right,ymin_type,ymax_type,ymin_itemid,ymax_itemid,flags)"
			" values ";
	const char	*ins_graph_discovery_sql =
			"insert into graph_discovery"
			" (graphdiscoveryid,graphid,parent_graphid,name)"
			" values ";
	const char	*ins_graphs_items_sql =
			"insert into graphs_items"
			" (gitemid,graphid,itemid,drawtype,sortorder,color,yaxisside,calc_fnc,type)"
			" values ";

	for (i = 0; i < graphs->values_num; i++)
	{
		graph = (zbx_lld_graph_t *)graphs->values[i];

		if (0 == graph->graphid)
			new_graphs++;

		for (j = 0; j < graph->gitems_num; j++)
		{
			if (0 == graph->gitems[j].gitemid)
				new_graphs_items++;
		}
	}

	if (0 != new_graphs)
	{
		graphid = DBget_maxid_num("graphs", new_graphs);
		graphdiscoveryid = DBget_maxid_num("graph_discovery", new_graphs);

		sql1 = zbx_malloc(sql1, sql1_alloc);
		sql2 = zbx_malloc(sql2, sql2_alloc);
		DBbegin_multiple_update(&sql1, &sql1_alloc, &sql1_offset);
		DBbegin_multiple_update(&sql2, &sql2_alloc, &sql2_offset);
#ifdef HAVE_MULTIROW_INSERT
		zbx_strcpy_alloc(&sql1, &sql1_alloc, &sql1_offset, ins_graphs_sql);
		zbx_strcpy_alloc(&sql2, &sql2_alloc, &sql2_offset, ins_graph_discovery_sql);
#endif
	}

	if (0 != new_graphs_items)
	{
		gitemid = DBget_maxid_num("graphs_items", new_graphs_items);

		sql3 = zbx_malloc(sql3, sql3_alloc);
		DBbegin_multiple_update(&sql3, &sql3_alloc, &sql3_offset);
#ifdef HAVE_MULTIROW_INSERT
		zbx_strcpy_alloc(&sql3, &sql3_alloc, &sql3_offset, ins_graphs_items_sql);
#endif
	}

	if (new_graphs < graphs->values_num)
	{
		sql4 = zbx_malloc(sql4, sql4_alloc);
		DBbegin_multiple_update(&sql4, &sql4_alloc, &sql4_offset);
	}

	for (i = 0; i < graphs->values_num; i++)
	{
		graph = (zbx_lld_graph_t *)graphs->values[i];

		name_esc = DBdyn_escape_string_len(graph->name, GRAPH_NAME_LEN);

		if (0 == graph->graphid)
		{
			graph->graphid = graphid++;
#ifndef HAVE_MULTIROW_INSERT
			zbx_strcpy_alloc(&sql1, &sql1_alloc, &sql1_offset, ins_graphs_sql);
#endif
			zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset,
					"(" ZBX_FS_UI64 ",'%s',%d,%d," ZBX_FS_DBL ","
						ZBX_FS_DBL ",%d,%d,%d,%d,%d," ZBX_FS_DBL ","
						ZBX_FS_DBL ",%d,%d,%s,%s,%d)" ZBX_ROW_DL,
					graph->graphid, name_esc, width, height, yaxismin, yaxismax,
					(int)show_work_period, (int)show_triggers,
					(int)graphtype, (int)show_legend, (int)show_3d,
					percent_left, percent_right, (int)ymin_type, (int)ymax_type,
					DBsql_id_ins(graph->ymin_itemid), DBsql_id_ins(graph->ymax_itemid),
					ZBX_FLAG_DISCOVERY_CREATED);

#ifndef HAVE_MULTIROW_INSERT
			zbx_strcpy_alloc(&sql2, &sql2_alloc, &sql2_offset, ins_graph_discovery_sql);
#endif
			zbx_snprintf_alloc(&sql2, &sql2_alloc, &sql2_offset,
					"(" ZBX_FS_UI64 "," ZBX_FS_UI64 "," ZBX_FS_UI64 ",'%s')" ZBX_ROW_DL,
					graphdiscoveryid, graph->graphid, parent_graphid, name_proto_esc);

			graphdiscoveryid++;
		}
		else
		{
			zbx_snprintf_alloc(&sql4, &sql4_alloc, &sql4_offset,
					"update graphs"
					" set name='%s',"
						"width=%d,"
						"height=%d,"
						"yaxismin=" ZBX_FS_DBL ","
						"yaxismax=" ZBX_FS_DBL ","
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
					(int)show_work_period, (int)show_triggers,
					(int)graphtype, (int)show_legend, (int)show_3d,
					percent_left, percent_right, (int)ymin_type, (int)ymax_type,
					DBsql_id_ins(graph->ymin_itemid), DBsql_id_ins(graph->ymax_itemid),
					ZBX_FLAG_DISCOVERY_CREATED, graph->graphid);

			zbx_snprintf_alloc(&sql4, &sql4_alloc, &sql4_offset,
					"update graph_discovery"
					" set name='%s'"
					" where graphid=" ZBX_FS_UI64
						" and parent_graphid=" ZBX_FS_UI64 ";\n",
					name_proto_esc, graph->graphid, parent_graphid);
		}

		for (j = 0; j < graph->gitems_num; j++)
		{
			ZBX_GRAPH_ITEMS	*gitem;
			char		*color_esc;

			gitem = &graph->gitems[j];
			color_esc = DBdyn_escape_string_len(gitem->color, GRAPH_ITEM_COLOR_LEN);

			if (0 != gitem->gitemid)
			{
				zbx_snprintf_alloc(&sql4, &sql4_alloc, &sql4_offset,
						"update graphs_items"
						" set drawtype=%d,"
							"sortorder=%d,"
							"color='%s',"
							"yaxisside=%d,"
							"calc_fnc=%d,"
							"type=%d"
						" where gitemid=" ZBX_FS_UI64 ";\n",
						gitem->drawtype,
						gitem->sortorder,
						color_esc,
						gitem->yaxisside,
						gitem->calc_fnc,
						gitem->type,
						gitem->gitemid);
			}
			else
			{
				gitem->gitemid = gitemid++;
#ifndef HAVE_MULTIROW_INSERT
				zbx_strcpy_alloc(&sql3, &sql3_alloc, &sql3_offset, ins_graphs_items_sql);
#endif
				zbx_snprintf_alloc(&sql3, &sql3_alloc, &sql3_offset,
						"(" ZBX_FS_UI64 "," ZBX_FS_UI64 "," ZBX_FS_UI64
							",%d,%d,'%s',%d,%d,%d)" ZBX_ROW_DL,
						gitem->gitemid, graph->graphid, gitem->itemid,
						gitem->drawtype, gitem->sortorder, color_esc,
						gitem->yaxisside, gitem->calc_fnc, gitem->type);
			}

			zbx_free(color_esc);
		}

		for (j = 0; j < graph->del_gitems_num; j++)
		{
			zbx_snprintf_alloc(&sql4, &sql4_alloc, &sql4_offset,
					"delete from graphs_items"
					" where gitemid=" ZBX_FS_UI64 ";\n",
					graph->del_gitems[j].gitemid);
		}

		zbx_free(name_esc);
	}

	if (0 != new_graphs)
	{
#ifdef HAVE_MULTIROW_INSERT
		sql1_offset--;
		sql2_offset--;
		zbx_strcpy_alloc(&sql1, &sql1_alloc, &sql1_offset, ";\n");
		zbx_strcpy_alloc(&sql2, &sql2_alloc, &sql2_offset, ";\n");
#endif
		DBend_multiple_update(&sql1, &sql1_alloc, &sql1_offset);
		DBend_multiple_update(&sql2, &sql2_alloc, &sql2_offset);
		DBexecute("%s", sql1);
		DBexecute("%s", sql2);
		zbx_free(sql1);
		zbx_free(sql2);
	}

	if (0 != new_graphs_items)
	{
#ifdef HAVE_MULTIROW_INSERT
		sql3_offset--;
		zbx_strcpy_alloc(&sql3, &sql3_alloc, &sql3_offset, ";\n");
#endif
		DBend_multiple_update(&sql3, &sql3_alloc, &sql3_offset);
		DBexecute("%s", sql3);
		zbx_free(sql3);
	}

	if (new_graphs < graphs->values_num)
	{
		DBend_multiple_update(&sql4, &sql4_alloc, &sql4_offset);
		DBexecute("%s", sql4);
		zbx_free(sql4);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: DBlld_update_graphs                                              *
 *                                                                            *
 * Purpose: add or update graphs for discovery item                           *
 *                                                                            *
 * Parameters: hostid  - [IN] host identificator from database                *
 *             agent   - [IN] discovery item identificator from database      *
 *             jp_data - [IN] received data                                   *
 *                                                                            *
 ******************************************************************************/
void	DBlld_update_graphs(zbx_uint64_t hostid, zbx_uint64_t lld_ruleid, struct zbx_json_parse *jp_data,
		char **error, const char *f_macro, const char *f_regexp, zbx_vector_ptr_t *regexps)
{
	const char		*__function_name = "DBlld_update_graphs";

	struct zbx_json_parse	jp_row;
	const char		*p;
	DB_RESULT		result;
	DB_ROW			row;
	zbx_vector_ptr_t	graphs;
	zbx_vector_uint64_t	graphids;
	char			*sql = NULL;
	size_t			sql_alloc = 512, sql_offset;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_ptr_create(&graphs);
	zbx_vector_uint64_create(&graphids);
	sql = zbx_malloc(sql, sql_alloc);

	result = DBselect(
			"select distinct gd.graphid"
			" from item_discovery id,items i,graphs_items gi,graphs g"
			" left join items i1 on i1.itemid=g.ymin_itemid"
			" left join items i2 on i2.itemid=g.ymax_itemid"
			" join graph_discovery gd on gd.parent_graphid=g.graphid"
			" where id.itemid=i.itemid"
				" and i.itemid=gi.itemid"
				" and gi.graphid=g.graphid"
				" and id.parent_itemid=" ZBX_FS_UI64,
			lld_ruleid);

	while (NULL != (row = DBfetch(result)))
	{
		zbx_uint64_t	graphid;

		ZBX_STR2UINT64(graphid, row[0]);

		zbx_vector_uint64_append(&graphids, graphid);
	}
	DBfree_result(result);

	result = DBselect(
			"select distinct g.graphid,g.name,g.width,g.height,g.yaxismin,g.yaxismax,g.show_work_period,"
				"g.show_triggers,g.graphtype,g.show_legend,g.show_3d,g.percent_left,g.percent_right,"
				"g.ymin_type,i1.itemid,i1.flags,i1.key_,g.ymax_type,i2.itemid,i2.flags,i2.key_"
			" from item_discovery id,items i,graphs_items gi,graphs g"
			" left join items i1 on i1.itemid=g.ymin_itemid"
			" left join items i2 on i2.itemid=g.ymax_itemid"
			" where id.itemid=i.itemid"
				" and i.itemid=gi.itemid"
				" and gi.graphid=g.graphid"
				" and id.parent_itemid=" ZBX_FS_UI64,
			lld_ruleid);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_GRAPH_ITEMS	*gitems_proto = NULL;
		size_t		gitems_proto_alloc = 0, gitems_proto_num = 0;
		zbx_uint64_t	parent_graphid, ymin_itemid = 0, ymax_itemid = 0;
		const char	*name_proto, *ymin_key_proto = NULL, *ymax_key_proto = NULL;
		char		*name_proto_esc;
		int		width, height;
		double		yaxismin, yaxismax, percent_left, percent_right;
		unsigned char	show_work_period, show_triggers, graphtype, show_legend, show_3d,
				ymin_type = GRAPH_YAXIS_TYPE_CALCULATED, ymax_type = GRAPH_YAXIS_TYPE_CALCULATED,
				ymin_flags = 0, ymax_flags = 0;
		int		i;

		ZBX_STR2UINT64(parent_graphid, row[0]);
		name_proto = row[1];
		name_proto_esc = DBdyn_escape_string(name_proto);
		width = atoi(row[2]);
		height = atoi(row[3]);
		yaxismin = atof(row[4]);
		yaxismax = atof(row[5]);
		show_work_period = (unsigned char)atoi(row[6]);
		show_triggers = (unsigned char)atoi(row[7]);
		graphtype = (unsigned char)atoi(row[8]);
		show_legend = (unsigned char)atoi(row[9]);
		show_3d = (unsigned char)atoi(row[10]);
		percent_left = atof(row[11]);
		percent_right = atof(row[12]);
		ymin_type = (unsigned char)atoi(row[13]);
		if (GRAPH_YAXIS_TYPE_ITEM_VALUE == ymin_type && SUCCEED != DBis_null(row[14]))
		{
			ymin_type = GRAPH_YAXIS_TYPE_ITEM_VALUE;
			ZBX_STR2UINT64(ymin_itemid, row[14]);
			ymin_flags = (unsigned char)atoi(row[15]);
			ymin_key_proto = row[16];
		}
		ymax_type = (unsigned char)atoi(row[17]);
		if (GRAPH_YAXIS_TYPE_ITEM_VALUE == ymax_type && SUCCEED != DBis_null(row[18]))
		{
			ymax_type = GRAPH_YAXIS_TYPE_ITEM_VALUE;
			ZBX_STR2UINT64(ymax_itemid, row[18]);
			ymax_flags = (unsigned char)atoi(row[19]);
			ymax_key_proto = row[20];
		}

		sql_offset = 0;
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"select 0,i.itemid,i.key_,gi.drawtype,gi.sortorder,gi.color,"
					"gi.yaxisside,gi.calc_fnc,gi.type,i.flags"
				" from graphs_items gi,items i"
				" where gi.itemid=i.itemid"
					" and gi.graphid=" ZBX_FS_UI64,
				parent_graphid);

		DBget_graphitems(sql, &gitems_proto, &gitems_proto_alloc, &gitems_proto_num);

		p = NULL;
		/* {"net.if.discovery":[{"{#IFNAME}":"eth0"},{"{#IFNAME}":"lo"},...]} */
		/*                      ^                                             */
		while (NULL != (p = zbx_json_next(jp_data, p)))
		{
			/* {"net.if.discovery":[{"{#IFNAME}":"eth0"},{"{#IFNAME}":"lo"},...]} */
			/*                      ^------------------^                          */
			if (FAIL == zbx_json_brackets_open(p, &jp_row))
				continue;

			if (SUCCEED != lld_check_record(&jp_row, f_macro, f_regexp, regexps))
				continue;

			DBlld_make_graph(hostid, parent_graphid, &graphs, name_proto, gitems_proto, gitems_proto_num,
					ymin_type, ymin_itemid, ymin_flags, ymin_key_proto,
					ymax_type, ymax_itemid, ymax_flags, ymax_key_proto,
					&jp_row, error);
		}

		zbx_vector_ptr_sort(&graphs, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

		DBlld_save_graphs(&graphs, width, height, yaxismin, yaxismax, show_work_period, show_triggers,
				graphtype, show_legend, show_3d, percent_left, percent_right, ymin_type, ymax_type,
				parent_graphid, name_proto_esc);

		zbx_free(gitems_proto);
		zbx_free(name_proto_esc);

		for (i = 0; i < graphids.values_num;)
		{
			if (FAIL != zbx_vector_ptr_bsearch(&graphs, &graphids.values[i],
					ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC))
			{
				zbx_vector_uint64_remove_noorder(&graphids, i);
			}
			else
				i++;
		}

		DBlld_clean_graphs(&graphs);
	}
	DBfree_result(result);

	zbx_vector_uint64_sort(&graphids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	DBdelete_graphs(&graphids);

	zbx_free(sql);
	zbx_vector_uint64_destroy(&graphids);
	zbx_vector_ptr_destroy(&graphs);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}
