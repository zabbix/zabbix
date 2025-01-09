/*
** Copyright (C) 2001-2025 Zabbix SIA
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

#include "graph_linking.h"

#include "zbxdb.h"
#include "zbxdbhigh.h"
#include "audit/zbxaudit.h"
#include "audit/zbxaudit_graph.h"
#include "zbxnum.h"
#include "zbxstr.h"

typedef enum
{
	GRAPH_YAXIS_TYPE_CALCULATED = 0,
	GRAPH_YAXIS_TYPE_FIXED,
	GRAPH_YAXIS_TYPE_ITEM_VALUE
}
zbx_graph_yaxis_types_t;

typedef struct
{
	zbx_uint64_t	itemid;
	zbx_uint64_t	gitemid;
	char		key[ZBX_ITEM_KEY_LEN * ZBX_MAX_BYTES_IN_UTF8_CHAR + 1];
	int		drawtype_orig;
	int		drawtype_new;
	int		sortorder_orig;
	int		sortorder_new;
	char		color_orig[ZBX_GRAPH_ITEM_COLOR_LEN_MAX];
	char		color_new[ZBX_GRAPH_ITEM_COLOR_LEN_MAX];
	int		yaxisside_orig;
	int		yaxisside_new;
	int		calc_fnc_orig;
	int		calc_fnc_new;
	int		type_orig;
	int		type_new;
	unsigned char	flags;

#define ZBX_FLAG_LINK_GRAPHITEM_UNSET			__UINT64_C(0x00)
#define ZBX_FLAG_LINK_GRAPHITEM_UPDATE_DRAWTYPE		__UINT64_C(0x01)
#define ZBX_FLAG_LINK_GRAPHITEM_UPDATE_SORTORDER	__UINT64_C(0x02)
#define ZBX_FLAG_LINK_GRAPHITEM_UPDATE_COLOR		__UINT64_C(0x04)
#define ZBX_FLAG_LINK_GRAPHITEM_UPDATE_YAXISSIDE	__UINT64_C(0x08)
#define ZBX_FLAG_LINK_GRAPHITEM_UPDATE_CALC_FNC		__UINT64_C(0x10)
#define ZBX_FLAG_LINK_GRAPHITEM_UPDATE_TYPE		__UINT64_C(0x20)
#define ZBX_FLAG_LINK_GRAPHITEM_UPDATE								\
	(ZBX_FLAG_LINK_GRAPHITEM_UPDATE_DRAWTYPE | ZBX_FLAG_LINK_GRAPHITEM_UPDATE_SORTORDER |	\
	ZBX_FLAG_LINK_GRAPHITEM_UPDATE_COLOR | ZBX_FLAG_LINK_GRAPHITEM_UPDATE_YAXISSIDE |	\
	ZBX_FLAG_LINK_GRAPHITEM_UPDATE_CALC_FNC | ZBX_FLAG_LINK_GRAPHITEM_UPDATE_TYPE)

	zbx_uint64_t	update_flags;
}
graph_item_entry;

typedef struct
{
	zbx_uint64_t	graphid;
	char		*name_orig;
	char		*name;
	int		width_orig;
	int		width;
	int		height_orig;
	int		height;
	double		yaxismin_orig;
	double		yaxismin;
	double		yaxismax_orig;
	double		yaxismax;
	unsigned char	show_work_period_orig;
	unsigned char	show_work_period;
	unsigned char	show_triggers_orig;
	unsigned char	show_triggers;
	unsigned char	graphtype_orig;
	unsigned char	graphtype;
	unsigned char	show_legend_orig;
	unsigned char	show_legend;
	unsigned char	show_3d_orig;
	unsigned char	show_3d;
	double		percent_left_orig;
	double		percent_left;
	double		percent_right_orig;
	double		percent_right;
	unsigned char	ymin_type_orig;
	unsigned char	ymin_type;
	unsigned char	ymax_type_orig;
	unsigned char	ymax_type;
	zbx_uint64_t	ymin_itemid_orig;
	zbx_uint64_t	ymin_itemid;
	zbx_uint64_t	ymax_itemid_orig;
	zbx_uint64_t	ymax_itemid;
	unsigned char	flags;
	unsigned char	discover_orig;
	unsigned char	discover;
#define ZBX_FLAG_LINK_GRAPH_UNSET			__UINT64_C(0x00000000)
#define ZBX_FLAG_LINK_GRAPH_UPDATE_NAME			__UINT64_C(0x00000001)
#define ZBX_FLAG_LINK_GRAPH_UPDATE_WIDTH		__UINT64_C(0x00000002)
#define ZBX_FLAG_LINK_GRAPH_UPDATE_HEIGHT		__UINT64_C(0x00000004)
#define ZBX_FLAG_LINK_GRAPH_UPDATE_YAXISMIN		__UINT64_C(0x00000008)
#define ZBX_FLAG_LINK_GRAPH_UPDATE_YAXISMAX		__UINT64_C(0x00000010)
#define ZBX_FLAG_LINK_GRAPH_UPDATE_SHOW_WORK_PERIOD	__UINT64_C(0x00000020)
#define ZBX_FLAG_LINK_GRAPH_UPDATE_SHOW_TRIGGERS	__UINT64_C(0x00000040)
#define ZBX_FLAG_LINK_GRAPH_UPDATE_GRAPHTYPE		__UINT64_C(0x00000080)
#define ZBX_FLAG_LINK_GRAPH_UPDATE_SHOW_LEGEND		__UINT64_C(0x00000100)
#define ZBX_FLAG_LINK_GRAPH_UPDATE_SHOW_3D		__UINT64_C(0x00000200)
#define ZBX_FLAG_LINK_GRAPH_UPDATE_PERCENT_LEFT		__UINT64_C(0x00000400)
#define ZBX_FLAG_LINK_GRAPH_UPDATE_PERCENT_RIGHT	__UINT64_C(0x00000800)
#define ZBX_FLAG_LINK_GRAPH_UPDATE_YMIN_TYPE		__UINT64_C(0x00001000)
#define ZBX_FLAG_LINK_GRAPH_UPDATE_YMAX_TYPE		__UINT64_C(0x00002000)
#define ZBX_FLAG_LINK_GRAPH_UPDATE_YMIN_ITEMID		__UINT64_C(0x00004000)
#define ZBX_FLAG_LINK_GRAPH_UPDATE_YMAX_ITEMID		__UINT64_C(0x00008000)
#define ZBX_FLAG_LINK_GRAPH_UPDATE_DISCOVER		__UINT64_C(0x00020000)
	zbx_uint64_t	update_flags;
	zbx_uint64_t	templateid_orig;
	zbx_uint64_t	templateid;
}
zbx_graph_copy_t;

static zbx_graph_copy_t	*zbx_graph_copy_init_new(zbx_uint64_t graphid, const char *name, int width, int height,
		double yaxismin, double yaxismax, unsigned char show_work_period, unsigned char show_triggers,
		unsigned char graphtype, unsigned char show_legend, unsigned char show_3d, double percent_left,
		double percent_right, unsigned char ymin_type, unsigned char ymax_type, zbx_uint64_t ymin_itemid,
		zbx_uint64_t ymax_itemid, unsigned char flags, unsigned char discover, zbx_uint64_t templateid)
{
	zbx_graph_copy_t	*graph_copy;

	graph_copy = (zbx_graph_copy_t*)zbx_malloc(NULL, sizeof(zbx_graph_copy_t));
	graph_copy->graphid = graphid;
#define INIT_STR_N(r) graph_copy->r = zbx_strdup(NULL, r); \
	graph_copy->r##_orig = NULL;
#define INIT_INT_OR_DBL_N(r) graph_copy->r = r; \
	graph_copy->r##_orig = 0;
	INIT_STR_N(name)
	INIT_INT_OR_DBL_N(width)
	INIT_INT_OR_DBL_N(height)
	INIT_INT_OR_DBL_N(yaxismin)
	INIT_INT_OR_DBL_N(yaxismax)
	INIT_INT_OR_DBL_N(show_work_period)
	INIT_INT_OR_DBL_N(show_triggers)
	INIT_INT_OR_DBL_N(graphtype)
	INIT_INT_OR_DBL_N(show_legend)
	INIT_INT_OR_DBL_N(show_3d)
	INIT_INT_OR_DBL_N(percent_left)
	INIT_INT_OR_DBL_N(percent_right)
	INIT_INT_OR_DBL_N(ymin_type)
	INIT_INT_OR_DBL_N(ymax_type)
	INIT_INT_OR_DBL_N(ymin_itemid)
	INIT_INT_OR_DBL_N(ymax_itemid)
	graph_copy->flags = flags;
	INIT_INT_OR_DBL_N(discover)
#undef INIT_STR_N
#undef INIT_INT_OR_DBL_N
	graph_copy->update_flags = ZBX_FLAG_LINK_GRAPH_UNSET;
	graph_copy->templateid = templateid;
	graph_copy->templateid_orig = 0;

	return graph_copy;
}

static void	zbx_graph_copy_init_orig(zbx_graph_copy_t *graph_copy, zbx_uint64_t graphid, const char *name,
		int width, int height, double yaxismin, double yaxismax, unsigned char show_work_period,
		unsigned char show_triggers, unsigned char graphtype, unsigned char show_legend, unsigned char show_3d,
		double percent_left, double percent_right, unsigned char ymin_type, unsigned char ymax_type,
		zbx_uint64_t ymin_itemid, zbx_uint64_t ymax_itemid, unsigned char flags, unsigned char discover,
		zbx_uint64_t templateid_orig)
{
	graph_copy->graphid = graphid;
#define INIT_STR_ORIG(r) graph_copy->r = NULL; \
	graph_copy->r##_orig = zbx_strdup(NULL, r);
#define INIT_INT_OR_DBL_ORIG(r) graph_copy->r = 0; \
	graph_copy->r##_orig = r;
	INIT_STR_ORIG(name)
	INIT_INT_OR_DBL_ORIG(width)
	INIT_INT_OR_DBL_ORIG(height)
	INIT_INT_OR_DBL_ORIG(yaxismin)
	INIT_INT_OR_DBL_ORIG(yaxismax)
	INIT_INT_OR_DBL_ORIG(show_work_period)
	INIT_INT_OR_DBL_ORIG(show_triggers)
	INIT_INT_OR_DBL_ORIG(graphtype)
	INIT_INT_OR_DBL_ORIG(show_legend)
	INIT_INT_OR_DBL_ORIG(show_3d)
	INIT_INT_OR_DBL_ORIG(percent_left)
	INIT_INT_OR_DBL_ORIG(percent_right)
	INIT_INT_OR_DBL_ORIG(ymin_type)
	INIT_INT_OR_DBL_ORIG(ymax_type)
	INIT_INT_OR_DBL_ORIG(ymin_itemid)
	INIT_INT_OR_DBL_ORIG(ymax_itemid)
	graph_copy->flags = flags;
	INIT_INT_OR_DBL_ORIG(discover)
#undef INIT_STR_ORIG
#undef INIT_INT_OR_DBL_ORIG
	graph_copy->update_flags = ZBX_FLAG_LINK_GRAPH_UNSET;
	graph_copy->templateid = 0;
	graph_copy->templateid_orig = templateid_orig;
}

static zbx_hash_t	graphs_copies_hash_func(const void *data)
{
	const zbx_graph_copy_t	*graph_copy = (const zbx_graph_copy_t *)data;

	return ZBX_DEFAULT_UINT64_HASH_ALGO(&((graph_copy)->graphid), sizeof((graph_copy)->graphid),
			ZBX_DEFAULT_HASH_SEED);
}

static int	graphs_copies_compare_func(const void *d1, const void *d2)
{
	const zbx_graph_copy_t	*graph_copy_1 = (const zbx_graph_copy_t *)d1;
	const zbx_graph_copy_t	*graph_copy_2 = (const zbx_graph_copy_t *)d2;

	ZBX_RETURN_IF_NOT_EQUAL((graph_copy_1)->graphid, (graph_copy_2)->graphid);

	return 0;
}

static void	graphs_copies_clean(zbx_hashset_t *x)
{
	zbx_hashset_iter_t	iter;
	zbx_graph_copy_t	*graph_copy;

	zbx_hashset_iter_reset(x, &iter);

	while (NULL != (graph_copy = (zbx_graph_copy_t *)zbx_hashset_iter_next(&iter)))
	{
		zbx_free(graph_copy->name_orig);

		if (0 != (graph_copy->update_flags & ZBX_FLAG_LINK_GRAPH_UPDATE_NAME))
			zbx_free(graph_copy->name);
	}

	zbx_hashset_destroy(x);
}

typedef struct zbx_graph_names_entry
{
	const char		*name;
	zbx_vector_uint64_t	graphids;
} zbx_graph_names_entry_t;

static zbx_hash_t	zbx_graphs_names_hash_func(const void *data)
{
	const zbx_graph_names_entry_t	*graph_names_entry = (const zbx_graph_names_entry_t *)data;

	return  ZBX_DEFAULT_STRING_HASH_ALGO(graph_names_entry->name, strlen(graph_names_entry->name),
			ZBX_DEFAULT_HASH_SEED);
}

static int	zbx_graphs_names_compare_func(const void *d1, const void *d2)
{
	const zbx_graph_names_entry_t	*graph_names_entry_1 = (const zbx_graph_names_entry_t *)d1;
	const zbx_graph_names_entry_t	*graph_names_entry_2 = (const zbx_graph_names_entry_t *)d2;

	return strcmp((graph_names_entry_1)->name, (graph_names_entry_2)->name);
}

static void	graphs_names_clean(zbx_hashset_t *x)
{
	zbx_hashset_iter_t	iter;
	zbx_graph_names_entry_t	*graph_name_entry;

	zbx_hashset_iter_reset(x, &iter);

	while (NULL != (graph_name_entry = (zbx_graph_names_entry_t *)zbx_hashset_iter_next(&iter)))
	{
		zbx_vector_uint64_destroy(&(graph_name_entry->graphids));
	}

	zbx_hashset_destroy(x);
}

static void	graphs_copies_clean_vec_entry(zbx_graph_copy_t *copy)
{
	zbx_free(copy->name);
	zbx_free(copy);
}

ZBX_PTR_VECTOR_DECL(graphs_copies, zbx_graph_copy_t*)
ZBX_PTR_VECTOR_IMPL(graphs_copies, zbx_graph_copy_t*)

ZBX_PTR_VECTOR_DECL(gitems, graph_item_entry*)
ZBX_PTR_VECTOR_IMPL(gitems, graph_item_entry*)

typedef struct
{
	zbx_uint64_t		graphid;
	zbx_vector_gitems_t	gitems;
}
graphs_items_entry_t;

static zbx_hash_t	graphs_items_hash_func(const void *data)
{
	const graphs_items_entry_t	*trigger_entry = (const graphs_items_entry_t*)data;

	return ZBX_DEFAULT_UINT64_HASH_ALGO(&(trigger_entry->graphid), sizeof((trigger_entry)->graphid),
			ZBX_DEFAULT_HASH_SEED);
}

static int	graphs_items_compare_func(const void *d1, const void *d2)
{
	const graphs_items_entry_t	*trigger_entry_1 = (const graphs_items_entry_t*)d1;
	const graphs_items_entry_t	*trigger_entry_2 = (const graphs_items_entry_t*)d2;

	ZBX_RETURN_IF_NOT_EQUAL((trigger_entry_1)->graphid, (trigger_entry_2)->graphid);

	return 0;
}

static void	graph_item_entry_clean(graph_item_entry *x)
{
	zbx_free(x);
}

static void	graphs_items_clean(zbx_hashset_t *x)
{
	zbx_hashset_iter_t	iter;
	graphs_items_entry_t	*trigger_entry;

	zbx_hashset_iter_reset(x, &iter);

	while (NULL != (trigger_entry = (graphs_items_entry_t *)zbx_hashset_iter_next(&iter)))
	{
		zbx_vector_gitems_clear_ext(&(trigger_entry->gitems), graph_item_entry_clean);
		zbx_vector_gitems_destroy(&(trigger_entry->gitems));
	}

	zbx_hashset_destroy(x);
}

typedef struct
{
	zbx_uint64_t	key_itemid;
	zbx_uint64_t	val_itemid;
}
itemids_map_entry_t;

static zbx_hash_t	itemids_map_hash_func(const void *data)
{
	const itemids_map_entry_t	*itemids_map_entry = (const itemids_map_entry_t*)data;

	return ZBX_DEFAULT_UINT64_HASH_ALGO(&(itemids_map_entry->key_itemid),
			sizeof(itemids_map_entry->key_itemid), ZBX_DEFAULT_HASH_SEED);
}

static int	itemids_map_compare_func(const void *d1, const void *d2)
{
	const itemids_map_entry_t	*itemids_map_entry_1 = (const itemids_map_entry_t*)d1;
	const itemids_map_entry_t	*itemids_map_entry_2 = (const itemids_map_entry_t*)d2;

	ZBX_RETURN_IF_NOT_EQUAL((itemids_map_entry_1)->key_itemid, (itemids_map_entry_2)->key_itemid);

	return 0;
}

static int	get_templates_graphs_data(const zbx_vector_uint64_t *templateids,
		zbx_vector_graphs_copies_t *graphs_copies_templates, zbx_vector_uint64_t *templates_graphs_ids,
		zbx_vector_str_t *templates_graphs_names)
{
	char			*sql = NULL;
	size_t			sql_alloc = 512, sql_offset = 0;
	int			res = SUCCEED;
	zbx_db_result_t		result;
	zbx_db_row_t		row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	sql = (char *)zbx_malloc(sql, sql_alloc);

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select distinct g.graphid,g.name,g.width,g.height,g.yaxismin,"
				"g.yaxismax,g.show_work_period,g.show_triggers,"
				"g.graphtype,g.show_legend,g.show_3d,g.percent_left,"
				"g.percent_right,g.ymin_type,g.ymax_type,g.ymin_itemid,"
				"g.ymax_itemid,g.flags,g.discover"
			" from graphs g,graphs_items gi,items i"
			" where g.graphid=gi.graphid"
				" and gi.itemid=i.itemid"
				" and");

	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "i.hostid", templateids->values,
			templateids->values_num);

	if (NULL == (result = zbx_db_select("%s", sql)))
	{
		res = FAIL;
		goto clean;
	}

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_uint64_t		graphid, ymin_itemid, ymax_itemid;
		zbx_graph_copy_t	*graph_copy;

		ZBX_STR2UINT64(graphid, row[0]);
		ZBX_DBROW2UINT64(ymin_itemid, row[15]);
		ZBX_DBROW2UINT64(ymax_itemid, row[16]);

		graph_copy = zbx_graph_copy_init_new(graphid, row[1], atoi(row[2]), atoi(row[3]), atof(row[4]),
				atof(row[5]), (unsigned char)atoi(row[6]), (unsigned char)atoi(row[7]),
				(unsigned char)atoi(row[8]), (unsigned char)atoi(row[9]), (unsigned char)atoi(row[10]),
				atof(row[11]), atof(row[12]), (unsigned char)atoi(row[13]),
				(unsigned char)atoi(row[14]), ymin_itemid, ymax_itemid, (unsigned char)atoi(row[17]),
				(unsigned char)atoi(row[18]), 0);

		zbx_vector_graphs_copies_append(graphs_copies_templates, graph_copy);
		zbx_vector_uint64_append(templates_graphs_ids, graph_copy->graphid);
		zbx_vector_str_append(templates_graphs_names, zbx_strdup(NULL,graph_copy->name));
	}
clean:
	zbx_db_free_result(result);
	zbx_free(sql);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(res));

	return res;
}

static int	update_same_itemids(zbx_uint64_t hostid, zbx_vector_graphs_copies_t *graphs_copies_templates)
{
	int			i, res = SUCCEED;
	char			*sql = NULL;
	size_t			sql_alloc = 256, sql_offset = 0;
	zbx_hashset_t		y_data_map;
	zbx_vector_uint64_t	y_data_ids;
	zbx_db_result_t		result;
	zbx_db_row_t		row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() hostid:" ZBX_FS_UI64, __func__, hostid);

	zbx_vector_uint64_create(&y_data_ids);

	for (i = 0; i < graphs_copies_templates->values_num; i++)
	{
		zbx_vector_uint64_append(&y_data_ids, graphs_copies_templates->values[i]->ymin_itemid);
		zbx_vector_uint64_append(&y_data_ids, graphs_copies_templates->values[i]->ymax_itemid);
	}

	if (0 == y_data_ids.values_num)
		goto out;
#define	TRIGGER_GITEMS_HASHSET_DEF_SIZE	100
	zbx_hashset_create(&y_data_map, TRIGGER_GITEMS_HASHSET_DEF_SIZE,
			itemids_map_hash_func,
			itemids_map_compare_func);
#undef TRIGGER_GITEMS_HASHSET_DEF_SIZE
	sql = (char *)zbx_malloc(sql, sql_alloc);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select ti.itemid,hi.itemid from items hi,items ti"
			" where hi.key_=ti.key_ "
			" and hi.hostid="ZBX_FS_UI64
			" and", hostid);

	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "ti.itemid", y_data_ids.values,
			y_data_ids.values_num);

	if (NULL == (result = zbx_db_select("%s", sql)))
	{
		res = FAIL;
		goto clean;
	}

	while (NULL != (row = zbx_db_fetch(result)))
	{
		itemids_map_entry_t	itemids_map_entry;

		ZBX_STR2UINT64(itemids_map_entry.key_itemid, row[0]);
		ZBX_STR2UINT64(itemids_map_entry.val_itemid, row[1]);
		zbx_hashset_insert(&y_data_map, &itemids_map_entry, sizeof(itemids_map_entry));
	}

	for (i = 0; i < graphs_copies_templates->values_num; i++)
	{
		zbx_uint64_t		ymin_itemid_new = 0, ymax_itemid_new = 0;
		itemids_map_entry_t	*found, temp_t;

		if (GRAPH_YAXIS_TYPE_ITEM_VALUE == graphs_copies_templates->values[i]->ymin_type)
		{
			temp_t.key_itemid = graphs_copies_templates->values[i]->ymin_itemid;

			if (NULL != (found = (itemids_map_entry_t*)zbx_hashset_search(&y_data_map, &temp_t)))
				ymin_itemid_new = found->val_itemid;
		}

		if (GRAPH_YAXIS_TYPE_ITEM_VALUE == graphs_copies_templates->values[i]->ymax_type)
		{
			temp_t.key_itemid = graphs_copies_templates->values[i]->ymax_itemid;

			if (NULL != (found = (itemids_map_entry_t*)zbx_hashset_search(&y_data_map, &temp_t)))
				ymax_itemid_new = found->val_itemid;
		}

		graphs_copies_templates->values[i]->ymin_itemid = ymin_itemid_new;
		graphs_copies_templates->values[i]->ymax_itemid = ymax_itemid_new;
	}
clean:
	zbx_free(sql);
	zbx_hashset_destroy(&y_data_map);
	zbx_db_free_result(result);
out:
	zbx_vector_uint64_destroy(&y_data_ids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(res));

	return res;
}

/* hostid is necessary for template, for host hostid is 0 */
static int	get_graphs_items(zbx_uint64_t hostid, const zbx_vector_uint64_t *graphs_ids,
		zbx_hashset_t *graphs_items)
{
	char		*sql = NULL;
	size_t		sql_alloc = 512, sql_offset = 0;
	int		res = SUCCEED;
	zbx_db_result_t	result;
	zbx_db_row_t	row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (0 == graphs_ids->values_num)
		goto out;

	if (0 != hostid)
	{
		/* template */
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				"select 0,dst.itemid,dst.key_,gi.drawtype,gi.sortorder,gi.color,gi.yaxisside,"
					"gi.calc_fnc,gi.type,i.flags,gi.graphid"
				" from graphs_items gi,items i,items dst"
				" where gi.itemid=i.itemid"
				" and i.key_=dst.key_");
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " and dst.hostid=" ZBX_FS_UI64, hostid);
	}
	else
	{
		/* host */
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				"select gi.gitemid,i.itemid,i.key_,gi.drawtype,gi.sortorder,gi.color,gi.yaxisside,"
					"gi.calc_fnc,gi.type,i.flags,gi.graphid"
				" from graphs_items gi,items i"
				" where gi.itemid=i.itemid");
	}

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " and");

	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "gi.graphid", graphs_ids->values,
			graphs_ids->values_num);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " order by i.key_");

	if (NULL == (result = zbx_db_select("%s", sql)))
	{
		res = FAIL;
		goto clean;
	}

	while (NULL != (row = zbx_db_fetch(result)))
	{
		graphs_items_entry_t	*found, temp_t;
		graph_item_entry	*gitem;

		gitem = (graph_item_entry*)zbx_malloc(NULL, sizeof(graph_item_entry));

		ZBX_STR2UINT64(gitem->itemid, row[1]);
		ZBX_STR2UINT64(gitem->gitemid, row[0]);
		zbx_strlcpy(gitem->key, row[2], sizeof(gitem->key));
		gitem->drawtype_orig = atoi(row[3]);
		gitem->drawtype_new = 0;
		gitem->sortorder_orig = atoi(row[4]);
		gitem->sortorder_new = 0;
		zbx_strlcpy(gitem->color_orig, row[5], sizeof(gitem->color_orig));
		gitem->yaxisside_orig = atoi(row[6]);
		gitem->yaxisside_new = 0;
		gitem->calc_fnc_orig = atoi(row[7]);
		gitem->calc_fnc_new = 0;
		gitem->type_orig = atoi(row[8]);
		gitem->type_new = 0;
		gitem->flags = (unsigned char)atoi(row[9]);
		gitem->update_flags = ZBX_FLAG_LINK_GRAPHITEM_UNSET;

		ZBX_STR2UINT64(temp_t.graphid, row[10]);

		if (NULL != (found = (graphs_items_entry_t*)zbx_hashset_search(graphs_items, &temp_t)))
		{
			zbx_vector_gitems_append(&(found->gitems), gitem);
		}
		else
		{
			zbx_vector_gitems_create(&(temp_t.gitems));
			zbx_vector_gitems_append(&(temp_t.gitems), gitem);
			ZBX_STR2UINT64(temp_t.graphid, row[10]);

			zbx_hashset_insert(graphs_items, &temp_t, sizeof(temp_t));
		}
	}
clean:
	zbx_free(sql);
	zbx_db_free_result(result);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(res));

	return res;
}

static int	get_target_host_main_data(zbx_uint64_t hostid, zbx_vector_str_t *templates_graphs_names,
		zbx_vector_uint64_t *host_graphs_ids, zbx_hashset_t *host_graphs_main_data,
		zbx_hashset_t *host_graphs_names)
{
	char		*sql = NULL;
	size_t		sql_alloc = 256, sql_offset = 0;
	int		res = SUCCEED;
	zbx_db_result_t	result;
	zbx_db_row_t	row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() hostid:" ZBX_FS_UI64, __func__, hostid);

	sql = (char *)zbx_malloc(sql, sql_alloc);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select distinct g.graphid,g.name,g.width,g.height,g.yaxismin,g.yaxismax,g.show_work_period"
			",g.show_triggers,g.graphtype,g.show_legend,g.show_3d,g.percent_left,g.percent_right"
			",g.ymin_type,g.ymax_type,g.ymin_itemid,g.ymax_itemid,g.discover,g.templateid,g.flags"
			" from graphs g,graphs_items gi,items i"
			" where g.graphid=gi.graphid"
				" and gi.itemid=i.itemid"
				" and i.hostid=" ZBX_FS_UI64
				" and g.templateid is null"
			" and", hostid);

	zbx_db_add_str_condition_alloc(&sql, &sql_alloc, &sql_offset, "g.name",
			(const char**)templates_graphs_names->values, templates_graphs_names->values_num);

	if (NULL == (result = zbx_db_select("%s", sql)))
	{
		res = FAIL;
		goto clean;
	}

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_uint64_t		graphid, ymin_itemid, ymax_itemid, templateid_orig;
		zbx_graph_copy_t	graph_copy;
		zbx_graph_names_entry_t	temp_t, *found;

		graph_copy.update_flags = ZBX_FLAG_LINK_GRAPH_UNSET;
		ZBX_STR2UINT64(graphid, row[0]);
		zbx_vector_uint64_append(host_graphs_ids, graphid);

		ZBX_DBROW2UINT64(ymin_itemid, row[15]);
		ZBX_DBROW2UINT64(ymax_itemid, row[16]);
		ZBX_DBROW2UINT64(templateid_orig, row[18]);

		zbx_graph_copy_init_orig(&graph_copy, graphid, row[1], atoi(row[2]), atoi(row[3]), atof(row[4]),
				atof(row[5]), (unsigned char)atoi(row[6]), (unsigned char)atoi(row[7]),
				(unsigned char)atoi(row[8]), (unsigned char)atoi(row[9]), (unsigned char)atoi(row[10]),
				atof(row[11]), atof(row[12]), (unsigned char)atoi(row[13]),
				(unsigned char)atoi(row[14]), ymin_itemid, ymax_itemid, (unsigned char)atoi(row[19]),
				(unsigned char)atoi(row[17]), templateid_orig);

		zbx_hashset_insert(host_graphs_main_data, &graph_copy, sizeof(graph_copy));

		temp_t.name = graph_copy.name_orig;

		if (NULL != (found = (zbx_graph_names_entry_t *)zbx_hashset_search(host_graphs_names, &temp_t)))
		{
			zbx_vector_uint64_append(&(found->graphids), graphid);
		}
		else
		{
			zbx_graph_names_entry_t	local_temp_t;

			zbx_vector_uint64_create(&(local_temp_t.graphids));
			local_temp_t.name = graph_copy.name_orig;
			zbx_vector_uint64_append(&(local_temp_t.graphids), graphid);
			zbx_hashset_insert(host_graphs_names, &local_temp_t, sizeof(local_temp_t));
		}
	}
clean:
	zbx_free(sql);
	zbx_db_free_result(result);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(res));

	return res;
}

static void	mark_updates_for_host_graph(zbx_graph_copy_t *template_graph_copy,
		zbx_graph_copy_t *host_graph_copy_found)
{
	if (0 != strcmp(template_graph_copy->name, host_graph_copy_found->name_orig))
	{
		host_graph_copy_found->name = zbx_strdup(NULL, template_graph_copy->name);
		host_graph_copy_found->update_flags |= ZBX_FLAG_LINK_GRAPH_UPDATE_NAME;
	}

	if (template_graph_copy->width != host_graph_copy_found->width_orig)
	{
		host_graph_copy_found->width = template_graph_copy->width;
		host_graph_copy_found->update_flags |= ZBX_FLAG_LINK_GRAPH_UPDATE_WIDTH;
	}

	if (template_graph_copy->height != host_graph_copy_found->height_orig)
	{
		host_graph_copy_found->height = template_graph_copy->height;
		host_graph_copy_found->update_flags |= ZBX_FLAG_LINK_GRAPH_UPDATE_HEIGHT;
	}

	if (FAIL == zbx_double_compare(template_graph_copy->yaxismin, host_graph_copy_found->yaxismin_orig))
	{
		host_graph_copy_found->yaxismin = template_graph_copy->yaxismin;
		host_graph_copy_found->update_flags |= ZBX_FLAG_LINK_GRAPH_UPDATE_YAXISMIN;
	}

	if (FAIL == zbx_double_compare(template_graph_copy->yaxismax, host_graph_copy_found->yaxismax_orig))
	{
		host_graph_copy_found->yaxismax = template_graph_copy->yaxismax;
		host_graph_copy_found->update_flags |= ZBX_FLAG_LINK_GRAPH_UPDATE_YAXISMAX;
	}

	if (template_graph_copy->show_work_period != host_graph_copy_found->show_work_period_orig)
	{
		host_graph_copy_found->show_work_period = template_graph_copy->show_work_period;
		host_graph_copy_found->update_flags |= ZBX_FLAG_LINK_GRAPH_UPDATE_SHOW_WORK_PERIOD;
	}

	if (template_graph_copy->show_triggers != host_graph_copy_found->show_triggers_orig)
	{
		host_graph_copy_found->show_triggers = template_graph_copy->show_triggers;
		host_graph_copy_found->update_flags |= ZBX_FLAG_LINK_GRAPH_UPDATE_SHOW_TRIGGERS;
	}

	if (template_graph_copy->graphtype != host_graph_copy_found->graphtype_orig)
	{
		host_graph_copy_found->graphtype = template_graph_copy->graphtype;
		host_graph_copy_found->update_flags |= ZBX_FLAG_LINK_GRAPH_UPDATE_GRAPHTYPE;
	}

	if (template_graph_copy->show_legend != host_graph_copy_found->show_legend_orig)
	{
		host_graph_copy_found->show_legend = template_graph_copy->show_legend;
		host_graph_copy_found->update_flags |= ZBX_FLAG_LINK_GRAPH_UPDATE_SHOW_LEGEND;
	}

	if (template_graph_copy->show_3d != host_graph_copy_found->show_3d_orig)
	{
		host_graph_copy_found->show_3d = template_graph_copy->show_3d;
		host_graph_copy_found->update_flags |= ZBX_FLAG_LINK_GRAPH_UPDATE_SHOW_3D;
	}

	if (FAIL == zbx_double_compare(template_graph_copy->percent_left, host_graph_copy_found->percent_left_orig))
	{
		host_graph_copy_found->percent_left = template_graph_copy->percent_left;
		host_graph_copy_found->update_flags |= ZBX_FLAG_LINK_GRAPH_UPDATE_PERCENT_LEFT;
	}

	if (FAIL == zbx_double_compare(template_graph_copy->percent_right, host_graph_copy_found->percent_right_orig))
	{
		host_graph_copy_found->percent_right = template_graph_copy->percent_right;
		host_graph_copy_found->update_flags |= ZBX_FLAG_LINK_GRAPH_UPDATE_PERCENT_RIGHT;
	}

	if (template_graph_copy->ymin_type != host_graph_copy_found->ymin_type_orig)
	{
		host_graph_copy_found->ymin_type = template_graph_copy->ymin_type;
		host_graph_copy_found->update_flags |= ZBX_FLAG_LINK_GRAPH_UPDATE_YMIN_TYPE;
	}

	if (template_graph_copy->ymax_type != host_graph_copy_found->ymax_type_orig)
	{
		host_graph_copy_found->ymax_type = template_graph_copy->ymax_type;
		host_graph_copy_found->update_flags |= ZBX_FLAG_LINK_GRAPH_UPDATE_YMAX_TYPE;
	}

	if (template_graph_copy->ymin_itemid != host_graph_copy_found->ymin_itemid_orig)
	{
		host_graph_copy_found->ymin_itemid = template_graph_copy->ymin_itemid;
		host_graph_copy_found->update_flags |= ZBX_FLAG_LINK_GRAPH_UPDATE_YMIN_ITEMID;
	}

	if (template_graph_copy->ymax_itemid != host_graph_copy_found->ymax_itemid_orig)
	{
		host_graph_copy_found->ymax_itemid = template_graph_copy->ymax_itemid;
		host_graph_copy_found->update_flags |= ZBX_FLAG_LINK_GRAPH_UPDATE_YMAX_ITEMID;
	}

	if (template_graph_copy->discover != host_graph_copy_found->discover_orig)
	{
		host_graph_copy_found->discover = template_graph_copy->discover;
		host_graph_copy_found->update_flags |= ZBX_FLAG_LINK_GRAPH_UPDATE_DISCOVER;
	}

	host_graph_copy_found->templateid = template_graph_copy->graphid;
}

static int	mark_updates_for_host_graph_items(graphs_items_entry_t *graphs_items_template_entry_found,
		graphs_items_entry_t *graphs_items_host_entry_found)
{
	int			j, res = FAIL;
	graph_item_entry	*template_entry, *host_entry;

	for (j = 0; j < graphs_items_template_entry_found->gitems.values_num; j++)
	{
		template_entry = graphs_items_template_entry_found->gitems.values[j];
		host_entry = graphs_items_host_entry_found->gitems.values[j];

		if (template_entry->drawtype_orig != host_entry->drawtype_orig)
		{
			host_entry->drawtype_new = template_entry->drawtype_orig;
			host_entry->update_flags |= ZBX_FLAG_LINK_GRAPHITEM_UPDATE_DRAWTYPE;
			res = SUCCEED;
		}

		if (template_entry->sortorder_orig != host_entry->sortorder_orig)
		{
			host_entry->sortorder_new = template_entry->sortorder_orig;
			host_entry->update_flags |= ZBX_FLAG_LINK_GRAPHITEM_UPDATE_SORTORDER;
			res = SUCCEED;
		}

		if (0 != strcmp(template_entry->color_orig, host_entry->color_orig))
		{
			zbx_strlcpy(host_entry->color_new, template_entry->color_orig,
					sizeof(template_entry->color_orig));
			host_entry->update_flags |= ZBX_FLAG_LINK_GRAPHITEM_UPDATE_COLOR;
			res = SUCCEED;
		}

		if (template_entry->yaxisside_orig != host_entry->yaxisside_orig)
		{
			host_entry->yaxisside_new = template_entry->yaxisside_orig;
			host_entry->update_flags |= ZBX_FLAG_LINK_GRAPHITEM_UPDATE_YAXISSIDE;
			res = SUCCEED;
		}

		if (template_entry->calc_fnc_orig != host_entry->calc_fnc_orig)
		{
			host_entry->calc_fnc_new = template_entry->calc_fnc_orig;
			host_entry->update_flags |= ZBX_FLAG_LINK_GRAPHITEM_UPDATE_CALC_FNC;
			res = SUCCEED;
		}

		if (template_entry->type_orig != host_entry->type_orig)
		{
			host_entry->type_new = template_entry->type_orig;
			host_entry->update_flags |= ZBX_FLAG_LINK_GRAPHITEM_UPDATE_TYPE;
			res = SUCCEED;
		}
	}

	return res;
}

static void	prepare_graph_for_insert(graphs_items_entry_t *graphs_items_template_entry_temp,
		zbx_vector_graphs_copies_t *graphs_copies_insert, zbx_graph_copy_t *template_graph_copy)
{
	zbx_graph_copy_t	*graph_copy;

	graph_copy = zbx_graph_copy_init_new(0, template_graph_copy->name, template_graph_copy->width,
			template_graph_copy->height, template_graph_copy->yaxismin, template_graph_copy->yaxismax,
			template_graph_copy->show_work_period, template_graph_copy->show_triggers,
			template_graph_copy->graphtype, template_graph_copy->show_legend, template_graph_copy->show_3d,
			template_graph_copy->percent_left, template_graph_copy->percent_right,
			template_graph_copy->ymin_type, template_graph_copy->ymax_type,
			template_graph_copy->ymin_itemid, template_graph_copy->ymax_itemid, template_graph_copy->flags,
			template_graph_copy->discover, graphs_items_template_entry_temp->graphid);

	zbx_vector_graphs_copies_append(graphs_copies_insert, graph_copy);
}

/************************************************************************************
 *                                                                                  *
 * Description: 1) gets a template graph and host graph and compares them           *
 *              2) if they are the same (they have same names and all of            *
 *                 their items keys are the same) and checks which fields           *
 *                 on the target host graph need to be updated and marks            *
 *                 them for update                                                  *
 *                                                                                  *
 * Parameters: host_graphid                        - [IN] target host graphid       *
 *             host_graphs_main_data               - [IN] set of host graph copies  *
 *                                                        with names that match the *
 *                                                        graphs from the templates *
 *             host_graphs_items                   - [IN] helper set for the        *
 *                                                        host_graphs_main_data     *
 *             graphs_items_template_entry_found   - [IN/OUT] helper set for        *
 *                                                        template_graph_copy       *
 *             template_graph_copy                 - [IN] template data             *
 *             upd_graphs_or_graphs_items          - [IN/OUT] counter of updated    *
 *                                                        graphs or graphs items    *
 *                                                                                  *
 * Return value: SUCCEED - the templates graph and host graph are the same          *
 *               FAIL    - otherwise                                                *
 *                                                                                  *
 ***********************************************************************************/
static int	process_template_graph(zbx_uint64_t host_graphid, zbx_hashset_t *host_graphs_main_data,
		zbx_hashset_t *host_graphs_items, graphs_items_entry_t *graphs_items_template_entry_found,
		zbx_graph_copy_t *template_graph_copy, int *upd_graphs_or_graphs_items)
{
	int			j, found_match = FAIL;
	zbx_graph_copy_t	main_temp_t, *host_graph_copy_found;
	graphs_items_entry_t	*graphs_items_host_entry_found, graphs_items_host_entry_temp_t;

	main_temp_t.graphid = host_graphid;

	if (NULL != (host_graph_copy_found = (zbx_graph_copy_t *)zbx_hashset_search(host_graphs_main_data,
			&main_temp_t)))
	{
		graphs_items_host_entry_temp_t.graphid = host_graph_copy_found->graphid;

		/* iterate over host graphs items */
		if (NULL != (graphs_items_host_entry_found = (graphs_items_entry_t*)zbx_hashset_search(
				host_graphs_items, &graphs_items_host_entry_temp_t)))
		{
			int	same_gitems = SUCCEED;

			if (graphs_items_template_entry_found->gitems.values_num !=
					graphs_items_host_entry_found->gitems.values_num)
			{
				return FAIL;
			}

			for (j = 0; j < graphs_items_template_entry_found->gitems.values_num; j++)
			{
				if (0 != strcmp(graphs_items_template_entry_found->gitems.values[j]->key,
						graphs_items_host_entry_found->gitems.values[j]->key))
				{
					same_gitems = FAIL;
					break;
				}
			}

			if (SUCCEED == same_gitems)
				found_match = SUCCEED;
		}
	}

	if (SUCCEED == found_match)
	{
		mark_updates_for_host_graph(template_graph_copy, host_graph_copy_found);
		mark_updates_for_host_graph_items(graphs_items_template_entry_found, graphs_items_host_entry_found);

		(*upd_graphs_or_graphs_items)++;
	}

	return found_match;
}

/************************************************************************************
 *                                                                                  *
 * Description: 1) gets a list graph from the templates                             *
 *              2) gets a list of graphs_items for that graph                       *
 *              3) gets all the current host graphs (with names that match any of   *
 *                 the templates graphs names)                                      *
 *              4) iterates over template graphs                                    *
 *              5) for each template graph finds the target host graphs with names  *
 *                 that match the template graph                                    *
 *              6) calls the process_template_graph() that compares both graphs and *
 *                 if they are the same marks the target host graph fields for      *
 *                 update                                                           *
 *              7) if process_template_graph() returns they are not the same, the   *
 *                 copy graph from the template is prepared for insert into a       *
 *                 target host                                                      *
 *                                                                                  *
 * Parameters: template_graph_copy        - [IN]     main template graph data       *
 *             host_graphs_main_data      - [IN]     set of host graph copies       *
 *                                                   with names that match the      *
 *                                                   graphs from the templates      *
 *             host_graphs_names          - [IN]     helper set for faster search   *
 *                                                   of graphs by names             *
 *             templates_graphs_items     - [IN]     helper set for the             *
 *                                                   template_graph_copy            *
 *             host_graphs_items          - [IN/OUT] helper set for the             *
 *                                                   host_graphs_main_data          *
 *             upd_graphs_or_graphs_items - [IN/OUT] counter of updated graphs      *
 *             graphs_copies_insert       - [OUT]    prepared graphs for insert     *
 *             total_insert_gitems_count  - [OUT]    total number of total          *
 *                                                   insert gitems_count            *
 *                                                                                  *
 ***********************************************************************************/
static void	process_graphs(zbx_graph_copy_t *template_graph_copy, zbx_hashset_t *host_graphs_main_data,
		zbx_hashset_t *host_graphs_names, zbx_hashset_t *templates_graphs_items,
		zbx_hashset_t *host_graphs_items, int *upd_graphs_or_graphs_items,
		zbx_vector_graphs_copies_t *graphs_copies_insert, int *total_insert_gitems_count)
{
	int			i;
	graphs_items_entry_t	*graphs_items_template_entry_found, graphs_items_template_entry_temp;

	graphs_items_template_entry_temp.graphid = template_graph_copy->graphid;

	if (NULL != (graphs_items_template_entry_found = (graphs_items_entry_t*)zbx_hashset_search(
			templates_graphs_items, &graphs_items_template_entry_temp)))
	{
		int			found_match = FAIL;
		zbx_graph_names_entry_t	temp_t, *found;

		temp_t.name = template_graph_copy->name;

		if (NULL != (found =  (zbx_graph_names_entry_t *)zbx_hashset_search(host_graphs_names, &temp_t)))
		{
			for (i = 0; i < found->graphids.values_num; i++)
			{
				if (SUCCEED == process_template_graph(found->graphids.values[i], host_graphs_main_data,
						host_graphs_items, graphs_items_template_entry_found,
						template_graph_copy, upd_graphs_or_graphs_items))
				{
					found_match = SUCCEED;
					break;
				}
			}
		}

		if (FAIL == found_match)
		{
			/* not found any entries on host */
			prepare_graph_for_insert(&graphs_items_template_entry_temp, graphs_copies_insert,
					template_graph_copy);
			*total_insert_gitems_count += graphs_items_template_entry_found->gitems.values_num;
		}
	}
}

static int	update_graphs_items_updates(char **sql, size_t *sql_alloc, size_t *sql_offset,
		zbx_uint64_t graphid, int graph_flags, zbx_hashset_t *host_graphs_items, int audit_context_mode)
{
	int			j, res = SUCCEED;
	graphs_items_entry_t	*graphs_items_host_entry_found, graphs_items_host_entry_temp_t;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	graphs_items_host_entry_temp_t.graphid = graphid;

	if (NULL != (graphs_items_host_entry_found = (graphs_items_entry_t*)zbx_hashset_search(host_graphs_items,
			&graphs_items_host_entry_temp_t)))
	{
		for (j = 0; j < graphs_items_host_entry_found->gitems.values_num; j++)
		{
			const char		*d2;
			graph_item_entry	*host_items_entry;

			host_items_entry = graphs_items_host_entry_found->gitems.values[j];

			d2 = "";

			if (0 != (host_items_entry->update_flags & ZBX_FLAG_LINK_GRAPHITEM_UPDATE))
			{
				zbx_audit_graph_update_json_update_gitem_create_entry(audit_context_mode, graphid,
						graph_flags, host_items_entry->gitemid);

				zbx_strcpy_alloc(sql, sql_alloc, sql_offset, "update graphs_items set ");

				if (0 != (host_items_entry->update_flags & ZBX_FLAG_LINK_GRAPHITEM_UPDATE_DRAWTYPE))
				{
					zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "drawtype=%d",
							host_items_entry->drawtype_new);
					d2 = ",";

					zbx_audit_graph_update_json_update_gitem_update_drawtype(audit_context_mode,
							graphid, graph_flags, host_items_entry->gitemid,
							host_items_entry->drawtype_orig,
							host_items_entry->drawtype_new);
				}

				if (0 != (host_items_entry->update_flags & ZBX_FLAG_LINK_GRAPHITEM_UPDATE_SORTORDER))
				{
					zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%ssortorder=%d", d2,
							host_items_entry->sortorder_new);
					d2 = ",";

					zbx_audit_graph_update_json_update_gitem_update_sortorder(audit_context_mode,
							graphid, graph_flags, host_items_entry->gitemid,
							host_items_entry->sortorder_orig,
							host_items_entry->sortorder_new);
				}

				if (0 != (host_items_entry->update_flags & ZBX_FLAG_LINK_GRAPHITEM_UPDATE_COLOR))
				{
					char	*color_esc = zbx_db_dyn_escape_string(host_items_entry->color_new);

					zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%scolor='%s'", d2,
							color_esc);
					zbx_free(color_esc);
					d2 = ",";

					zbx_audit_graph_update_json_update_gitem_update_color(audit_context_mode,
							graphid, graph_flags, host_items_entry->gitemid,
							host_items_entry->color_orig, host_items_entry->color_new);
				}

				if (0 != (host_items_entry->update_flags & ZBX_FLAG_LINK_GRAPHITEM_UPDATE_YAXISSIDE))
				{
					zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%syaxisside=%d", d2,
							host_items_entry->yaxisside_new);
					d2 = ",";

					zbx_audit_graph_update_json_update_gitem_update_yaxisside(audit_context_mode,
							graphid, graph_flags, host_items_entry->gitemid,
							host_items_entry->yaxisside_orig,
							host_items_entry->yaxisside_new);
				}

				if (0 != (host_items_entry->update_flags & ZBX_FLAG_LINK_GRAPHITEM_UPDATE_CALC_FNC))
				{
					zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%scalc_fnc=%d", d2,
							host_items_entry->calc_fnc_new);
					d2 = ",";

					zbx_audit_graph_update_json_update_gitem_update_calc_fnc(audit_context_mode,
							graphid, graph_flags, host_items_entry->gitemid,
							host_items_entry->calc_fnc_orig,
							host_items_entry->calc_fnc_new);
				}

				if (0 != (host_items_entry->update_flags & ZBX_FLAG_LINK_GRAPHITEM_UPDATE_TYPE))
				{
					zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%stype=%d", d2,
							host_items_entry->type_new);

					zbx_audit_graph_update_json_update_gitem_update_type(audit_context_mode,
							graphid, graph_flags, host_items_entry->gitemid,
							host_items_entry->type_orig, host_items_entry->type_new);
				}

				zbx_snprintf_alloc(sql, sql_alloc, sql_offset, " where gitemid=" ZBX_FS_UI64 ";\n",
						host_items_entry->gitemid);

				if (SUCCEED != (res = zbx_db_execute_overflowed_sql(sql, sql_alloc, sql_offset)))
					goto out;
			}
		}
	}
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(res));

	return res;
}

static int	execute_graphs_updates(zbx_hashset_t *host_graphs_main_data, zbx_hashset_t *host_graphs_items,
		int audit_context_mode)
{
	int			res = SUCCEED;
	const char		*d;
	char			*sql = NULL, *sql2 = NULL;
	size_t			sql_alloc = 512, sql_offset = 0, sql_alloc2 = 512, sql_offset2 = 0;
	zbx_hashset_iter_t	iter1;
	zbx_graph_copy_t	*found;

	zbx_hashset_iter_reset(host_graphs_main_data, &iter1);

	while (SUCCEED == res && NULL != (found = (zbx_graph_copy_t *)zbx_hashset_iter_next(&iter1)))
	{
		d = "";

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "update graphs set ");

		zbx_audit_graph_create_entry(audit_context_mode, ZBX_AUDIT_ACTION_UPDATE, found->graphid,
				found->name_orig, (int)(found->flags));

		if (0 != (found->update_flags & ZBX_FLAG_LINK_GRAPH_UPDATE_NAME))
		{
			char	*name_esc = zbx_db_dyn_escape_string(found->name);
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "name='%s'", name_esc);
			zbx_free(name_esc);
			d = ",";

			zbx_audit_graph_update_json_update_name(audit_context_mode, found->graphid, (int)(found->flags),
					found->name_orig, found->name);
		}

		if (0 != (found->update_flags & ZBX_FLAG_LINK_GRAPH_UPDATE_WIDTH))
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%swidth=%d", d, found->width);
			d = ",";

			zbx_audit_graph_update_json_update_width(audit_context_mode, found->graphid,
					(int)(found->flags), found->width_orig, found->width);
		}

		if (0 != (found->update_flags & ZBX_FLAG_LINK_GRAPH_UPDATE_HEIGHT))
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%sheight=%d", d, found->height);
			d = ",";

			zbx_audit_graph_update_json_update_height(audit_context_mode, found->graphid,
					(int)(found->flags), found->height_orig, found->height);
		}

		if (0 != (found->update_flags & ZBX_FLAG_LINK_GRAPH_UPDATE_YAXISMIN))
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%syaxismin=" ZBX_FS_DBL64_SQL, d,
					found->yaxismin);
			d = ",";

			zbx_audit_graph_update_json_update_yaxismin(audit_context_mode, found->graphid,
					(int)(found->flags), found->yaxismin_orig, found->yaxismin);
		}

		if (0 != (found->update_flags & ZBX_FLAG_LINK_GRAPH_UPDATE_YAXISMAX))
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%syaxismax=" ZBX_FS_DBL64_SQL, d,
					found->yaxismax);
			d = ",";

			zbx_audit_graph_update_json_update_yaxismax(audit_context_mode, found->graphid,
					(int)(found->flags), found->yaxismax_orig, found->yaxismax);
		}

		if (0 != (found->update_flags & ZBX_FLAG_LINK_GRAPH_UPDATE_SHOW_WORK_PERIOD))
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%sshow_work_period=%d", d,
					(int)found->show_work_period);
			d = ",";

			zbx_audit_graph_update_json_update_show_work_period(audit_context_mode, found->graphid,
					(int)(found->flags), found->show_work_period_orig, found->show_work_period);
		}

		if (0 != (found->update_flags & ZBX_FLAG_LINK_GRAPH_UPDATE_SHOW_TRIGGERS))
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%sshow_triggers=%d", d,
					(int)found->show_triggers);
			d = ",";

			zbx_audit_graph_update_json_update_show_triggers(audit_context_mode, found->graphid,
					(int)(found->flags), found->show_triggers_orig, found->show_triggers);
		}

		if (0 != (found->update_flags & ZBX_FLAG_LINK_GRAPH_UPDATE_GRAPHTYPE))
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%sgraphtype=%d", d,
					(int)found->graphtype);
			d = ",";

			zbx_audit_graph_update_json_update_graphtype(audit_context_mode, found->graphid,
					(int)(found->flags), (int)found->graphtype_orig, (int)found->graphtype);
		}

		if (0 != (found->update_flags & ZBX_FLAG_LINK_GRAPH_UPDATE_SHOW_LEGEND))
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%sshow_legend=%d", d,
					(int)found->show_legend);
			d = ",";

			zbx_audit_graph_update_json_update_show_legend(audit_context_mode, found->graphid,
					(int)(found->flags), (int)found->show_legend_orig, (int)found->show_legend);
		}

		if (0 != (found->update_flags & ZBX_FLAG_LINK_GRAPH_UPDATE_SHOW_3D))
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%sshow_3d=%d", d, (int)found->show_3d);
			d = ",";

			zbx_audit_graph_update_json_update_show_3d(audit_context_mode, found->graphid,
					(int)(found->flags), (int)found->show_3d_orig, (int)found->show_3d);
		}

		if (0 != (found->update_flags & ZBX_FLAG_LINK_GRAPH_UPDATE_PERCENT_LEFT))
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%spercent_left=" ZBX_FS_DBL64_SQL, d,
					found->percent_left);
			d = ",";

			zbx_audit_graph_update_json_update_percent_left(audit_context_mode, found->graphid,
					(int)(found->flags), found->percent_left_orig, found->percent_left);
		}

		if (0 != (found->update_flags & ZBX_FLAG_LINK_GRAPH_UPDATE_PERCENT_RIGHT))
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%spercent_right=" ZBX_FS_DBL64_SQL, d,
					found->percent_right);
			d = ",";

			zbx_audit_graph_update_json_update_percent_right(audit_context_mode, found->graphid,
					(int)(found->flags), found->percent_right_orig, found->percent_right);
		}

		if (0 != (found->update_flags & ZBX_FLAG_LINK_GRAPH_UPDATE_YMIN_TYPE))
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%symin_type=%d", d,
					(int)found->ymin_type);
			d = ",";

			zbx_audit_graph_update_json_update_ymin_type(audit_context_mode, found->graphid,
					(int)(found->flags), found->ymin_type_orig, found->ymin_type);
		}

		if (0 != (found->update_flags & ZBX_FLAG_LINK_GRAPH_UPDATE_YMAX_TYPE))
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%symax_type=%d", d, (int)found->ymax_type);
			d = ",";

			zbx_audit_graph_update_json_update_ymax_type(audit_context_mode, found->graphid,
					(int)(found->flags), found->ymax_type_orig, found->ymax_type);
		}

		if (0 != (found->update_flags & ZBX_FLAG_LINK_GRAPH_UPDATE_YMIN_ITEMID))
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%symin_itemid=%s", d,
					zbx_db_sql_id_ins(found->ymin_itemid));
			d = ",";

			zbx_audit_graph_update_json_update_ymin_itemid(audit_context_mode, found->graphid,
					(int)(found->flags), found->ymin_itemid_orig, found->ymin_itemid);
		}

		if (0 != (found->update_flags & ZBX_FLAG_LINK_GRAPH_UPDATE_YMAX_ITEMID))
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%symax_itemid=%s", d,
					zbx_db_sql_id_ins(found->ymax_itemid));
			d = ",";

			zbx_audit_graph_update_json_update_ymax_itemid(audit_context_mode, found->graphid,
					(int)(found->flags), found->ymax_itemid_orig, found->ymax_itemid);
		}

		if (0 != (found->update_flags & ZBX_FLAG_LINK_GRAPH_UPDATE_DISCOVER))
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%sdiscover=%d", d, (int)found->discover);
			d = ",";

			zbx_audit_graph_update_json_update_discover(audit_context_mode, found->graphid,
					(int)(found->flags), found->discover_orig, found->discover);
		}

		zbx_audit_graph_update_json_update_templateid(audit_context_mode, found->graphid, (int)(found->flags),
				found->templateid_orig, found->templateid);

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%stemplateid=" ZBX_FS_UI64, d,
				found->templateid);

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " where graphid=" ZBX_FS_UI64 ";\n",
				found->graphid);

		res = zbx_db_execute_overflowed_sql(&sql, &sql_alloc, &sql_offset);

		if (SUCCEED == res)
		{
			res = update_graphs_items_updates(&sql2, &sql_alloc2, &sql_offset2, found->graphid,
					found->flags, host_graphs_items, audit_context_mode);
		}
	}
	if (SUCCEED == res && ZBX_DB_OK > zbx_db_flush_overflowed_sql(sql, sql_offset))
	{
		zabbix_log(LOG_LEVEL_WARNING, "failed to execute graphs updates");
		res = FAIL;
	}

	zbx_free(sql);

	if (SUCCEED == res && ZBX_DB_OK > zbx_db_flush_overflowed_sql(sql2, sql_offset2))
	{
		zabbix_log(LOG_LEVEL_WARNING, "failed to execute graphs items updates");
		res = FAIL;
	}

	zbx_free(sql2);

	return res;
}

static int	execute_graphs_inserts(zbx_vector_graphs_copies_t *graphs_copies_insert, int *total_insert_gitems_count,
		zbx_hashset_t *templates_graphs_items, int audit_context_mode)
{
	int		i, j, res;
	zbx_db_insert_t	db_insert, db_insert_graphs_items;
	zbx_uint64_t	graphid, graphs_itemsid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_db_insert_prepare(&db_insert, "graphs", "graphid", "name", "width", "height", "yaxismin",
			"yaxismax", "templateid", "show_work_period", "show_triggers", "graphtype", "show_legend",
			"show_3d", "percent_left", "percent_right", "ymin_type", "ymax_type", "ymin_itemid",
			"ymax_itemid", "flags", "discover", (char *)NULL);

	zbx_db_insert_prepare(&db_insert_graphs_items, "graphs_items", "gitemid", "graphid", "itemid", "drawtype",
			"sortorder", "color", "yaxisside", "calc_fnc", "type", (char *)NULL);

	graphid = zbx_db_get_maxid_num("graphs", graphs_copies_insert->values_num);
	graphs_itemsid = zbx_db_get_maxid_num("graphs_items", *total_insert_gitems_count);

	for (i = 0; i < graphs_copies_insert->values_num; i++)
	{
		zbx_graph_copy_t	*graph_copy = graphs_copies_insert->values[i];
		graphs_items_entry_t	*graphs_items_template_entry_found, graphs_items_template_entry_temp_t;

		zbx_db_insert_add_values(&db_insert, graphid, graph_copy->name, graph_copy->width,
				graph_copy->height, graph_copy->yaxismin, graph_copy->yaxismax, graph_copy->templateid,
				(int)graph_copy->show_work_period, (int)(graph_copy->show_triggers),
				(int)(graph_copy->graphtype), (int)(graph_copy->show_legend),
				(int)(graph_copy->show_3d), graph_copy->percent_left, graph_copy->percent_right,
				(int)(graph_copy->ymin_type), (int)(graph_copy->ymax_type),
				graph_copy->ymin_itemid, graph_copy->ymax_itemid,
				(int)(graph_copy->flags), (int)(graph_copy->discover));

		zbx_audit_graph_create_entry(audit_context_mode, ZBX_AUDIT_ACTION_ADD, graphid, graph_copy->name,
				(int)(graph_copy->flags));
		zbx_audit_graph_update_json_add_data(audit_context_mode, graphid, graph_copy->name, graph_copy->width,
				graph_copy->height, graph_copy->yaxismin, graph_copy->yaxismax, graph_copy->templateid,
				(int)graph_copy->show_work_period, (int)(graph_copy->show_triggers),
				(int)(graph_copy->graphtype), (int)(graph_copy->show_legend),
				(int)(graph_copy->show_3d), graph_copy->percent_left, graph_copy->percent_right,
				(int)(graph_copy->ymin_type), (int)(graph_copy->ymax_type),
				graph_copy->ymin_itemid, graph_copy->ymax_itemid,
				(int)(graph_copy->flags), (int)(graph_copy->discover));

		graphs_items_template_entry_temp_t.graphid = graph_copy->templateid;

		if (NULL != (graphs_items_template_entry_found = (graphs_items_entry_t*)zbx_hashset_search(
				templates_graphs_items, &graphs_items_template_entry_temp_t)))
		{
			for (j = 0; j < graphs_items_template_entry_found->gitems.values_num; j++)
			{
				char			*color_orig_esc;
				graph_item_entry	*template_entry;

				template_entry = graphs_items_template_entry_found->gitems.values[j];
				color_orig_esc = zbx_db_dyn_escape_string(template_entry->color_orig);

				zbx_db_insert_add_values(&db_insert_graphs_items, graphs_itemsid, graphid,
						template_entry->itemid, template_entry->drawtype_orig,
						template_entry->sortorder_orig,
						color_orig_esc,
						template_entry->yaxisside_orig, template_entry->calc_fnc_orig,
						template_entry->type_orig);
				zbx_free(color_orig_esc);

				zbx_audit_graph_update_json_add_gitems(audit_context_mode, graphid,
						(int)(graph_copy->flags), graphs_itemsid, template_entry->drawtype_orig,
						template_entry->sortorder_orig, template_entry->color_orig,
						template_entry->yaxisside_orig, template_entry->calc_fnc_orig,
						template_entry->type_orig, template_entry->itemid);
				graphs_itemsid++;
			}
		}

		graphid++;
	}

	res = zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);

	if (SUCCEED == res)
		res = zbx_db_insert_execute(&db_insert_graphs_items);

	zbx_db_insert_clean(&db_insert_graphs_items);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(res));

	return res;
}

/******************************************************************************
 *                                                                            *
 * Purpose: copies graphs from template to host                               *
 *                                                                            *
 * Parameters:                                                                *
 *             hostid             - [IN] host id from database                *
 *             templateids        - [IN]                                      *
 *             audit_context_mode - [IN]                                      *
 *                                                                            *
 * Return value: SUCCEED - db operations successful                           *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	DBcopy_template_graphs(zbx_uint64_t hostid, const zbx_vector_uint64_t *templateids, int audit_context_mode)
{
	int				i, upd_graphs_or_graphs_items = 0, total_insert_gitems_count = 0,
					res = SUCCEED;
	zbx_vector_graphs_copies_t	graphs_copies_templates, graphs_copies_insert;
	zbx_hashset_t			host_graphs_main_data, templates_graphs_items, host_graphs_items,
					host_graphs_names;
	zbx_vector_str_t		templates_graphs_names;
	zbx_vector_uint64_t		templates_graphs_ids, host_graphs_ids;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_str_create(&templates_graphs_names);
	zbx_vector_uint64_create(&templates_graphs_ids);
	zbx_vector_uint64_create(&host_graphs_ids);
#define	TRIGGER_GITEMS_HASHSET_DEF_SIZE	100
	zbx_hashset_create(&templates_graphs_items, TRIGGER_GITEMS_HASHSET_DEF_SIZE,
			graphs_items_hash_func,
			graphs_items_compare_func);
	zbx_hashset_create(&host_graphs_items, TRIGGER_GITEMS_HASHSET_DEF_SIZE,
			graphs_items_hash_func,
			graphs_items_compare_func);
	zbx_hashset_create(&host_graphs_main_data, TRIGGER_GITEMS_HASHSET_DEF_SIZE,
			graphs_copies_hash_func,
			graphs_copies_compare_func);
	zbx_hashset_create(&host_graphs_names, TRIGGER_GITEMS_HASHSET_DEF_SIZE,
			zbx_graphs_names_hash_func,
			zbx_graphs_names_compare_func);
#undef TRIGGER_GITEMS_HASHSET_DEF_SIZE
	zbx_vector_graphs_copies_create(&graphs_copies_templates);
	zbx_vector_graphs_copies_create(&graphs_copies_insert);

	res = get_templates_graphs_data(templateids, &graphs_copies_templates, &templates_graphs_ids,
			&templates_graphs_names);

	if (0 == templates_graphs_names.values_num)
		goto end;

	if (SUCCEED == res)
		res = get_graphs_items(hostid, &templates_graphs_ids, &templates_graphs_items);

	if (SUCCEED == res)
		res = update_same_itemids(hostid, &graphs_copies_templates);

	if (SUCCEED == res)
	{
		res = get_target_host_main_data(hostid, &templates_graphs_names, &host_graphs_ids,
				&host_graphs_main_data, &host_graphs_names);
	}

	if (SUCCEED == res)
		res = get_graphs_items(0, &host_graphs_ids, &host_graphs_items);

	if (SUCCEED == res)
	{
		for (i = 0; i < graphs_copies_templates.values_num; i++)
		{
			process_graphs(graphs_copies_templates.values[i], &host_graphs_main_data, &host_graphs_names,
					&templates_graphs_items, &host_graphs_items, &upd_graphs_or_graphs_items,
					&graphs_copies_insert, &total_insert_gitems_count);
		}

		if (0 < upd_graphs_or_graphs_items)
			res = execute_graphs_updates(&host_graphs_main_data, &host_graphs_items, audit_context_mode);
	}

	if (SUCCEED == res && 0 < graphs_copies_insert.values_num)
	{
		res = execute_graphs_inserts(&graphs_copies_insert, &total_insert_gitems_count,
				&templates_graphs_items, audit_context_mode);
	}
end:
	zbx_vector_str_clear_ext(&templates_graphs_names, zbx_str_free);
	zbx_vector_str_destroy(&templates_graphs_names);

	zbx_vector_uint64_destroy(&templates_graphs_ids);
	zbx_vector_uint64_destroy(&host_graphs_ids);

	zbx_vector_graphs_copies_clear_ext(&graphs_copies_templates, graphs_copies_clean_vec_entry);
	zbx_vector_graphs_copies_destroy(&graphs_copies_templates);

	zbx_vector_graphs_copies_clear_ext(&graphs_copies_insert, graphs_copies_clean_vec_entry);
	zbx_vector_graphs_copies_destroy(&graphs_copies_insert);

	graphs_names_clean(&host_graphs_names);
	graphs_copies_clean(&host_graphs_main_data);
	graphs_items_clean(&templates_graphs_items);
	graphs_items_clean(&host_graphs_items);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(res));

	return res;
}
