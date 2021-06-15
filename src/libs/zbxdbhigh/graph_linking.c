/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

#include "db.h"
#include "graph_linking.h"

/******************************************************************************
 *                                                                            *
 * Function: DBget_same_itemid                                                *
 *                                                                            *
 * Purpose: get same itemid for selected host by itemid from template         *
 *                                                                            *
 * Parameters: hostid - host identificator from database                      *
 *             itemid - item identificator from database (from template)      *
 *                                                                            *
 * Return value: new item identificator or zero if item not found             *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments: !!! Don't forget to sync the code with PHP !!!                   *
 *                                                                            *
 ******************************************************************************/
static zbx_uint64_t	DBget_same_itemid(zbx_uint64_t hostid, zbx_uint64_t titemid)
{
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	itemid = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() hostid:" ZBX_FS_UI64
			" titemid:" ZBX_FS_UI64,
			__func__, hostid, titemid);

	result = DBselect(
			"select hi.itemid"
			" from items hi,items ti"
			" where hi.key_=ti.key_"
				" and hi.hostid=" ZBX_FS_UI64
				" and ti.itemid=" ZBX_FS_UI64,
			hostid, titemid);

	if (NULL != (row = DBfetch(result)))
		ZBX_STR2UINT64(itemid, row[0]);
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():" ZBX_FS_UI64, __func__, itemid);

	return itemid;
}



static void	DBget_graphitems(const char *sql, ZBX_GRAPH_ITEMS **gitems, size_t *gitems_alloc, size_t *gitems_num)
{
	DB_RESULT	result;
	DB_ROW		row;
	ZBX_GRAPH_ITEMS	*gitem;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	*gitems_num = 0;

	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		if (*gitems_alloc == *gitems_num)
		{
			*gitems_alloc += 16;
			*gitems = (ZBX_GRAPH_ITEMS *)zbx_realloc(*gitems, *gitems_alloc * sizeof(ZBX_GRAPH_ITEMS));
		}

		gitem = &(*gitems)[*gitems_num];

		ZBX_STR2UINT64(gitem->gitemid, row[0]);
		ZBX_STR2UINT64(gitem->itemid, row[1]);
		zbx_strlcpy(gitem->key, row[2], sizeof(gitem->key));
		gitem->drawtype = atoi(row[3]);
		gitem->sortorder = atoi(row[4]);
		zbx_strlcpy(gitem->color, row[5], sizeof(gitem->color));
		gitem->yaxisside = atoi(row[6]);
		gitem->calc_fnc = atoi(row[7]);
		gitem->type = atoi(row[8]);
		gitem->flags = (unsigned char)atoi(row[9]);

		zabbix_log(LOG_LEVEL_DEBUG, "%s() [" ZBX_FS_SIZE_T "] itemid:" ZBX_FS_UI64 " key:'%s'",
				__func__, (zbx_fs_size_t)*gitems_num, gitem->itemid, gitem->key);

		(*gitems_num)++;
	}
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Function: DBcmp_graphitems                                                 *
 *                                                                            *
 * Purpose: Compare graph items from two graphs                               *
 *                                                                            *
 * Parameters: gitems1     - [IN] first graph items, sorted by itemid         *
 *             gitems1_num - [IN] number of first graph items                 *
 *             gitems2     - [IN] second graph items, sorted by itemid        *
 *             gitems2_num - [IN] number of second graph items                *
 *                                                                            *
 * Return value: SUCCEED if graph items coincide                              *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments: !!! Don't forget to sync the code with PHP !!!                   *
 *                                                                            *
 ******************************************************************************/
static int	DBcmp_graphitems(ZBX_GRAPH_ITEMS *gitems1, int gitems1_num,
		ZBX_GRAPH_ITEMS *gitems2, int gitems2_num)
{
	int	res = FAIL, i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (gitems1_num != gitems2_num)
		goto clean;

	for (i = 0; i < gitems1_num; i++)
		if (0 != strcmp(gitems1[i].key, gitems2[i].key))
			goto clean;

	res = SUCCEED;
clean:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(res));

	return res;
}



/******************************************************************************
 *                                                                            *
 * Function: DBcopy_graph_to_host                                             *
 *                                                                            *
 * Purpose: copy specified graph to host                                      *
 *                                                                            *
 * Parameters: graphid - graph identificator from database                    *
 *             hostid - host identificator from database                      *
 *                                                                            *
 * Author: Eugene Grigorjev, Alexander Vladishev                              *
 *                                                                            *
 * Comments: !!! Don't forget to sync the code with PHP !!!                   *
 *                                                                            *
 ******************************************************************************/
static void	DBcopy_graph_to_host(zbx_uint64_t hostid, zbx_uint64_t graphid,
		const char *name, int width, int height, double yaxismin,
		double yaxismax, unsigned char show_work_period,
		unsigned char show_triggers, unsigned char graphtype,
		unsigned char show_legend, unsigned char show_3d,
		double percent_left, double percent_right,
		unsigned char ymin_type, unsigned char ymax_type,
		zbx_uint64_t ymin_itemid, zbx_uint64_t ymax_itemid,
		unsigned char flags, unsigned char discover)
{
	DB_RESULT	result;
	DB_ROW		row;
	ZBX_GRAPH_ITEMS *gitems = NULL, *chd_gitems = NULL;
	size_t		gitems_alloc = 0, gitems_num = 0,
			chd_gitems_alloc = 0, chd_gitems_num = 0;
	zbx_uint64_t	hst_graphid, hst_gitemid;
	char		*sql = NULL, *name_esc, *color_esc;
	size_t		sql_alloc = ZBX_KIBIBYTE, sql_offset, i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	sql = (char *)zbx_malloc(sql, sql_alloc * sizeof(char));

	name_esc = DBdyn_escape_string(name);

	sql_offset = 0;
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select 0,dst.itemid,dst.key_,gi.drawtype,gi.sortorder,gi.color,gi.yaxisside,gi.calc_fnc,"
				"gi.type,i.flags"
			" from graphs_items gi,items i,items dst"
			" where gi.itemid=i.itemid"
				" and i.key_=dst.key_"
				" and gi.graphid=" ZBX_FS_UI64
				" and dst.hostid=" ZBX_FS_UI64
			" order by dst.key_",
			graphid, hostid);

	DBget_graphitems(sql, &gitems, &gitems_alloc, &gitems_num);

	// select main data from target host
	result = DBselect(
			"select distinct g.graphid"
			" from graphs g,graphs_items gi,items i"
			" where g.graphid=gi.graphid"
				" and gi.itemid=i.itemid"
				" and i.hostid=" ZBX_FS_UI64
				" and g.name='%s'"
				" and g.templateid is null",
			hostid, name_esc);

	/* compare graphs */
	hst_graphid = 0;
	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(hst_graphid, row[0]);

		sql_offset = 0;
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"select gi.gitemid,i.itemid,i.key_,gi.drawtype,gi.sortorder,gi.color,gi.yaxisside,"
					"gi.calc_fnc,gi.type,i.flags"
				" from graphs_items gi,items i"
				" where gi.itemid=i.itemid"
					" and gi.graphid=" ZBX_FS_UI64
				" order by i.key_",
				hst_graphid);

		DBget_graphitems(sql, &chd_gitems, &chd_gitems_alloc, &chd_gitems_num);

		if (SUCCEED == DBcmp_graphitems(gitems, gitems_num, chd_gitems, chd_gitems_num))
			break;	/* found equal graph */

		hst_graphid = 0;
	}
	DBfree_result(result);

	sql_offset = 0;
	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (GRAPH_YAXIS_TYPE_ITEM_VALUE == ymin_type)
		ymin_itemid = DBget_same_itemid(hostid, ymin_itemid);
	else
		ymin_itemid = 0;

	if (GRAPH_YAXIS_TYPE_ITEM_VALUE == ymax_type)
		ymax_itemid = DBget_same_itemid(hostid, ymax_itemid);
	else
		ymax_itemid = 0;

	if (0 != hst_graphid)
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"update graphs"
				" set name='%s',"
					"width=%d,"
					"height=%d,"
					"yaxismin=" ZBX_FS_DBL64_SQL ","
					"yaxismax=" ZBX_FS_DBL64_SQL ","
					"templateid=" ZBX_FS_UI64 ","
					"show_work_period=%d,"
					"show_triggers=%d,"
					"graphtype=%d,"
					"show_legend=%d,"
					"show_3d=%d,"
					"percent_left=" ZBX_FS_DBL64_SQL ","
					"percent_right=" ZBX_FS_DBL64_SQL ","
					"ymin_type=%d,"
					"ymax_type=%d,"
					"ymin_itemid=%s,"
					"ymax_itemid=%s,"
					"flags=%d,"
					"discover=%d"
				" where graphid=" ZBX_FS_UI64 ";\n",
				name_esc, width, height, yaxismin, yaxismax,
				graphid, (int)show_work_period, (int)show_triggers,
				(int)graphtype, (int)show_legend, (int)show_3d,
				percent_left, percent_right, (int)ymin_type, (int)ymax_type,
				DBsql_id_ins(ymin_itemid), DBsql_id_ins(ymax_itemid), (int)flags, (int)discover,
				hst_graphid);

		for (i = 0; i < gitems_num; i++)
		{
			color_esc = DBdyn_escape_string(gitems[i].color);

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
					"update graphs_items"
					" set drawtype=%d,"
						"sortorder=%d,"
						"color='%s',"
						"yaxisside=%d,"
						"calc_fnc=%d,"
						"type=%d"
					" where gitemid=" ZBX_FS_UI64 ";\n",
					gitems[i].drawtype,
					gitems[i].sortorder,
					color_esc,
					gitems[i].yaxisside,
					gitems[i].calc_fnc,
					gitems[i].type,
					chd_gitems[i].gitemid);

			zbx_free(color_esc);
		}
	}
	else
	{
		hst_graphid = DBget_maxid("graphs");

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"insert into graphs"
				" (graphid,name,width,height,yaxismin,yaxismax,templateid,"
				"show_work_period,show_triggers,graphtype,show_legend,"
				"show_3d,percent_left,percent_right,ymin_type,ymax_type,"
				"ymin_itemid,ymax_itemid,flags,discover)"
				" values (" ZBX_FS_UI64 ",'%s',%d,%d," ZBX_FS_DBL64_SQL ","
				ZBX_FS_DBL64_SQL "," ZBX_FS_UI64 ",%d,%d,%d,%d,%d," ZBX_FS_DBL64_SQL ","
				ZBX_FS_DBL64_SQL ",%d,%d,%s,%s,%d,%d);\n",
				hst_graphid, name_esc, width, height, yaxismin, yaxismax,
				graphid, (int)show_work_period, (int)show_triggers,
				(int)graphtype, (int)show_legend, (int)show_3d,
				percent_left, percent_right, (int)ymin_type, (int)ymax_type,
				DBsql_id_ins(ymin_itemid), DBsql_id_ins(ymax_itemid), (int)flags, (int)discover);

		hst_gitemid = DBget_maxid_num("graphs_items", gitems_num);

		for (i = 0; i < gitems_num; i++)
		{
			color_esc = DBdyn_escape_string(gitems[i].color);

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
					"insert into graphs_items (gitemid,graphid,itemid,drawtype,"
					"sortorder,color,yaxisside,calc_fnc,type)"
					" values (" ZBX_FS_UI64 "," ZBX_FS_UI64 "," ZBX_FS_UI64
					",%d,%d,'%s',%d,%d,%d);\n",
					hst_gitemid, hst_graphid, gitems[i].itemid,
					gitems[i].drawtype, gitems[i].sortorder, color_esc,
					gitems[i].yaxisside, gitems[i].calc_fnc, gitems[i].type);
			hst_gitemid++;

			zbx_free(color_esc);
		}
	}

	zbx_free(name_esc);

	DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (sql_offset > 16)	/* In ORACLE always present begin..end; */
		DBexecute("%s", sql);

	zbx_free(gitems);
	zbx_free(chd_gitems);
	zbx_free(sql);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Function: DBcopy_template_graphs                                           *
 *                                                                            *
 * Purpose: copy graphs from template to host                                 *
 *                                                                            *
 * Parameters: hostid      - [IN] host identificator from database            *
 *             templateids - [IN] array of template IDs                       *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget to sync the code with PHP !!!                   *
 *                                                                            *
 ******************************************************************************/
void	DBcopy_template_graphs2(zbx_uint64_t hostid, const zbx_vector_uint64_t *templateids)
{
	char		*sql = NULL;
	size_t		sql_alloc = 512, sql_offset = 0;
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	graphid, ymin_itemid, ymax_itemid;

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
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "i.hostid", templateids->values, templateids->values_num);

	result = DBselect("%s", sql);

	zbx_free(sql);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(graphid, row[0]);
		ZBX_DBROW2UINT64(ymin_itemid, row[15]);
		ZBX_DBROW2UINT64(ymax_itemid, row[16]);

		DBcopy_graph_to_host(hostid, graphid,
				row[1],				/* name */
				atoi(row[2]),			/* width */
				atoi(row[3]),			/* height */
				atof(row[4]),			/* yaxismin */
				atof(row[5]),			/* yaxismax */
				(unsigned char)atoi(row[6]),	/* show_work_period */
				(unsigned char)atoi(row[7]),	/* show_triggers */
				(unsigned char)atoi(row[8]),	/* graphtype */
				(unsigned char)atoi(row[9]),	/* show_legend */
				(unsigned char)atoi(row[10]),	/* show_3d */
				atof(row[11]),			/* percent_left */
				atof(row[12]),			/* percent_right */
				(unsigned char)atoi(row[13]),	/* ymin_type */
				(unsigned char)atoi(row[14]),	/* ymax_type */
				ymin_itemid,
				ymax_itemid,
				(unsigned char)atoi(row[17]),	/* flags */
				(unsigned char)atoi(row[18]));	/* discover */
	}
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

////////////////////////////////////////////////

typedef struct
{
	zbx_uint64_t	itemid;
	zbx_uint64_t	gitemid;
	char		key[ITEM_KEY_LEN * ZBX_MAX_BYTES_IN_UTF8_CHAR + 1];
	int		drawtype_orig;
	int		drawtype_new;
	int		sortorder_orig;
	int		sortorder_new;
	char		color_orig[GRAPH_ITEM_COLOR_LEN_MAX];
	char		color_new[GRAPH_ITEM_COLOR_LEN_MAX];
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

#define ZBX_FLAG_LINK_GRAPHITEM_UPDATE                                                          \
	(ZBX_FLAG_LINK_GRAPHITEM_UPDATE_DRAWTYPE | ZBX_FLAG_LINK_GRAPHITEM_UPDATE_SORTORDER |   \
	 ZBX_FLAG_LINK_GRAPHITEM_UPDATE_COLOR | ZBX_FLAG_LINK_GRAPHITEM_UPDATE_YAXISSIDE |      \
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
	unsigned char	flags_orig;
	unsigned char	flags;
 	unsigned char	discover_orig;
 	unsigned char	discover;
#define ZBX_FLAG_LINK_GRAPH_UNSET			__UINT64_C(0x00000000)
#define ZBX_FLAG_LINK_GRAPH_UPDATE_NAME			__UINT64_C(0x00000001)
#define ZBX_FLAG_LINK_GRAPH_UPDATE_WIDTH		__UINT64_C(0x00000002)
#define ZBX_FLAG_LINK_GRAPH_UPDATE_HEIGHT		__UINT64_C(0x00000004)
#define ZBX_FLAG_LINK_GRAPH_UPDATE_YAXISMIN		__UINT64_C(0x00000008)
#define ZBX_FLAG_LINK_GRAPH_UPDATE_YAXISMAX		__UINT64_C(0x00000010)
#define ZBX_FLAG_LINK_GRAPH_UPDATE_SHOW_WORK_PERIOD  	__UINT64_C(0x00000020)
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
#define ZBX_FLAG_LINK_GRAPH_UPDATE_FLAGS		__UINT64_C(0x00010000)
#define ZBX_FLAG_LINK_GRAPH_UPDATE_DISCOVER		__UINT64_C(0x00020000)

#define ZBX_FLAG_LINK_GRAPH_UPDATE                                                                  \
	  (ZBX_FLAG_LINK_GRAPH_UNSET | ZBX_FLAG_LINK_GRAPH_UPDATE_NAME |                            \
	  ZBX_FLAG_LINK_GRAPH_UPDATE_WIDTH | ZBX_FLAG_LINK_GRAPH_UPDATE_HEIGHT |                    \
	  ZBX_FLAG_LINK_GRAPH_UPDATE_YAXISMIN | ZBX_FLAG_LINK_GRAPH_UPDATE_YAXISMAX |               \
	  ZBX_FLAG_LINK_GRAPH_UPDATE_SHOW_WORK_PERIOD  | ZBX_FLAG_LINK_GRAPH_UPDATE_SHOW_TRIGGERS | \
	  ZBX_FLAG_LINK_GRAPH_UPDATE_GRAPHTYPE | ZBX_FLAG_LINK_GRAPH_UPDATE_SHOW_LEGEND |           \
	  ZBX_FLAG_LINK_GRAPH_UPDATE_SHOW_3D | ZBX_FLAG_LINK_GRAPH_UPDATE_PERCENT_LEFT |            \
	  ZBX_FLAG_LINK_GRAPH_UPDATE_PERCENT_RIGHT | ZBX_FLAG_LINK_GRAPH_UPDATE_YMIN_TYPE	|  \
	  ZBX_FLAG_LINK_GRAPH_UPDATE_YMAX_TYPE | ZBX_FLAG_LINK_GRAPH_UPDATE_YMIN_ITEMID |           \
	  ZBX_FLAG_LINK_GRAPH_UPDATE_YMAX_ITEMID | ZBX_FLAG_LINK_GRAPH_UPDATE_FLAGS |               \
	  ZBX_FLAG_LINK_GRAPH_UPDATE_DISCOVER)

	zbx_uint64_t	update_flags;

	zbx_uint64_t templateid;

}
zbx_graph_copy_t;

static zbx_hash_t	graphs_copies_hash_func(const void *data)
{
	const zbx_graph_copy_t	*graph_copy = (const zbx_graph_copy_t * )data;

	return ZBX_DEFAULT_UINT64_HASH_ALGO(&((graph_copy)->graphid), sizeof((graph_copy)->graphid),
			ZBX_DEFAULT_HASH_SEED);
}

static int	graphs_copies_compare_func(const void *d1, const void *d2)
{
	const zbx_graph_copy_t	*graph_copy_1 = (const zbx_graph_copy_t * )d1;
	const zbx_graph_copy_t	*graph_copy_2 = (const zbx_graph_copy_t * )d2;

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
	      //
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
	const graphs_items_entry_t	*trigger_entry = (const graphs_items_entry_t*) data;

	return ZBX_DEFAULT_UINT64_HASH_ALGO(&(trigger_entry->graphid), sizeof((trigger_entry)->graphid),
			ZBX_DEFAULT_HASH_SEED);
}

static int	graphs_items_compare_func(const void *d1, const void *d2)
{
	const graphs_items_entry_t	*trigger_entry_1 = (const graphs_items_entry_t*) d1;
	const graphs_items_entry_t	*trigger_entry_2 = (const graphs_items_entry_t*) d2;

	ZBX_RETURN_IF_NOT_EQUAL((trigger_entry_1)->graphid, (trigger_entry_2)->graphid);

	return 0;
}

static void graph_item_entry_clean(graph_item_entry *x)
{
  zbx_free(x);
}

static void	graphs_items_clean(zbx_hashset_t *x)
{
	zbx_hashset_iter_t			iter;
	graphs_items_entry_t	*trigger_entry;

	zbx_hashset_iter_reset(x, &iter);

	while (NULL != (trigger_entry = (graphs_items_entry_t *)zbx_hashset_iter_next(&iter)))
	{
	  zbx_vector_gitems_clear_ext(&(trigger_entry->gitems), graph_item_entry_clean);
		zbx_vector_gitems_destroy(&(trigger_entry->gitems));
	}

	zbx_hashset_destroy(x);
}
/////////////


typedef struct
{
	zbx_uint64_t		key_itemid;
	zbx_uint64_t		val_itemid;
}
itemids_map_entry_t;


static zbx_hash_t	itemids_map_hash_func(const void *data)
{
	const itemids_map_entry_t	*itemids_map_entry = (const itemids_map_entry_t*) data;

	return ZBX_DEFAULT_UINT64_HASH_ALGO(&(itemids_map_entry->key_itemid),
					    sizeof(itemids_map_entry->key_itemid), ZBX_DEFAULT_HASH_SEED);
}

static int	itemids_map_compare_func(const void *d1, const void *d2)
{
	const itemids_map_entry_t	*itemids_map_entry_1 = (const itemids_map_entry_t*) d1;
	const itemids_map_entry_t	*itemids_map_entry_2 = (const itemids_map_entry_t*) d2;

	ZBX_RETURN_IF_NOT_EQUAL((itemids_map_entry_1)->key_itemid, (itemids_map_entry_2)->key_itemid);

	return 0;
}

/* static void     itemids_map_clean(zbx_hashset_t *x) */
/* { */
/* 	zbx_hashset_iter_t	iter; */
/* 	itemids_map_entry_t	*itemids_map_entry; */

/* 	zbx_hashset_iter_reset(x, &iter); */

/* 	while (NULL != (itemids_map_entry = (itemids_map_entry_t *)zbx_hashset_iter_next(&iter))) */
/* 	{ */
/* 	  //zbx_vector_gitems_destroy(&(trigger_entry->gitems)); */
/* 	} */

/* 	zbx_hashset_destroy(x); */
/* } */


/////////////

static void	get_templates_graphs_data(const zbx_vector_uint64_t *templateids, zbx_vector_graphs_copies_t
					 *graphs_copies_templates, zbx_vector_uint64_t *templates_graphs_ids,
					 zbx_vector_str_t *templates_graphs_names)
{
	char			*sql = NULL;
	size_t			sql_alloc = 512, sql_offset = 0;
	DB_RESULT		result;
	DB_ROW			row;
	zbx_graph_copy_t	*graph_copy;
	
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
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "i.hostid", templateids->values, templateids->values_num);

	result = DBselect("%s", sql);

	zbx_free(sql);

	while (NULL != (row = DBfetch(result)))
	{
		graph_copy = (zbx_graph_copy_t *)zbx_malloc(NULL, sizeof(zbx_graph_copy_t));
		ZBX_STR2UINT64(graph_copy->graphid, row[0]);
		graph_copy->name = zbx_strdup(NULL, row[1]);
		
		graph_copy->width = atoi(row[2]);
		graph_copy->height = atoi(row[3]);
		graph_copy->yaxismin = atof(row[4]);
		graph_copy->yaxismax = atof(row[5]);
		graph_copy->show_work_period = (unsigned char)atoi(row[6]);
		graph_copy->show_triggers = (unsigned char)atoi(row[7]);
		graph_copy->graphtype = (unsigned char)atoi(row[8]);
		graph_copy->show_legend = (unsigned char)atoi(row[9]);
		graph_copy->show_3d = (unsigned char)atoi(row[10]); 
		graph_copy->percent_left = atof(row[11]);
		graph_copy->percent_right = atof(row[12]);
		graph_copy->ymin_type = (unsigned char)atoi(row[13]);
		graph_copy->ymax_type = (unsigned char)atoi(row[14]);
		ZBX_DBROW2UINT64(graph_copy->ymin_itemid, row[15]);
		ZBX_DBROW2UINT64(graph_copy->ymax_itemid, row[16]);
		graph_copy->flags = (unsigned char)atoi(row[17]);
		graph_copy->discover = (unsigned char)atoi(row[18]);
		zbx_vector_graphs_copies_append(graphs_copies_templates, graph_copy);

		zbx_vector_uint64_append(templates_graphs_ids, graph_copy->graphid);
		zbx_vector_str_append(templates_graphs_names, DBdyn_escape_string(graph_copy->name));

		zabbix_log(LOG_LEVEL_INFORMATION, "INSERT BADGER1 templated: %lu", graph_copy->graphid);

	}

	DBfree_result(result);
}

static void	update_same_itemids(zbx_uint64_t hostid, zbx_vector_graphs_copies_t *graphs_copies_templates)
{
	int			i;
	char			*sql = NULL;
	size_t			sql_alloc = 256, sql_offset = 0;
	zbx_hashset_t		y_data_map;
	zbx_vector_uint64_t	y_data_ids;
	DB_RESULT		result;
	DB_ROW			row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() hostid:" ZBX_FS_UI64, __func__, hostid);

	zbx_vector_uint64_create(&y_data_ids);
#define	TRIGGER_GITEMS_HASHSET_DEF_SIZE	100
	zbx_hashset_create(&y_data_map, TRIGGER_GITEMS_HASHSET_DEF_SIZE,
			itemids_map_hash_func,
			itemids_map_compare_func);
#undef TRIGGER_GITEMS_HASHSET_DEF_SIZE

	for (i = 0; i < graphs_copies_templates->values_num; i++)
	{
		zbx_vector_uint64_append(&y_data_ids, graphs_copies_templates->values[i]->ymin_itemid);
		zbx_vector_uint64_append(&y_data_ids, graphs_copies_templates->values[i]->ymax_itemid);
	}
	
	sql = (char *)zbx_malloc(sql, sql_alloc);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			   "select ti.itemid,hi.itemid from items hi, items ti"
			   " where hi.key_=ti.key_ "
			   " and hi.hostid="ZBX_FS_UI64
			   " and", hostid);
	
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "ti.itemid", y_data_ids.values, y_data_ids.values_num);
	  
	result = DBselect("%s", sql);
	while (NULL != (row = DBfetch(result)))
	{
		itemids_map_entry_t itemids_map_entry;
		zabbix_log(LOG_LEVEL_INFORMATION, "AMERICANO STRATA: next0: %s, and next1: %s", row[0], row[1]);

		ZBX_STR2UINT64(itemids_map_entry.key_itemid, row[0]);
		ZBX_STR2UINT64(itemids_map_entry.val_itemid, row[1]);
		zbx_hashset_insert(&y_data_map, &itemids_map_entry, sizeof(itemids_map_entry));
	}

	DBfree_result(result);

	for (i = 0; i < graphs_copies_templates->values_num; i++)
	{
	  //graphs_copies_templates->values[i].ymin_type;
	  //graphs_copies_templates->values[i].ymax_type;

	  zbx_uint64_t			ymin_itemid_new = 0, ymax_itemid_new = 0;
	  itemids_map_entry_t	*found, temp_t;

	  if (GRAPH_YAXIS_TYPE_ITEM_VALUE == graphs_copies_templates->values[i]->ymin_type)
	  {
	  
		temp_t.key_itemid = graphs_copies_templates->values[i]->ymin_itemid;

		if (NULL != (found = (itemids_map_entry_t*)zbx_hashset_search(&y_data_map, &temp_t)))
		{
			ymin_itemid_new = found->val_itemid;
		}	  
	  }

	  if (GRAPH_YAXIS_TYPE_ITEM_VALUE == graphs_copies_templates->values[i]->ymax_type)
	  {	  
		temp_t.key_itemid = graphs_copies_templates->values[i]->ymax_itemid;

		if (NULL != (found = (itemids_map_entry_t*)zbx_hashset_search(&y_data_map, &temp_t)))
		{
			ymax_itemid_new = found->val_itemid;
		}	  
	  }


	  zabbix_log(LOG_LEVEL_INFORMATION, "AMERICANO ymin: before: %lu, after: %lu",  graphs_copies_templates->values[i]->ymin_itemid, ymin_itemid_new);
	  zabbix_log(LOG_LEVEL_INFORMATION, "AMERICANO ymax: before: %lu, after: %lu",  graphs_copies_templates->values[i]->ymax_itemid, ymax_itemid_new);
	  graphs_copies_templates->values[i]->ymin_itemid = ymin_itemid_new;
	  graphs_copies_templates->values[i]->ymax_itemid = ymax_itemid_new;
	  
	  
	}

	zbx_free(sql);
	zbx_hashset_destroy(&y_data_map);
	zbx_vector_uint64_destroy(&y_data_ids);
	
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():", __func__);

}
//hostid is necessary for template, for host hostid is 0
static void	get_data(zbx_uint64_t hostid, const zbx_vector_uint64_t *graphs_ids,
		zbx_hashset_t *graphs_items)
{
	char			*sql = NULL;
	size_t			sql_alloc = 512, sql_offset = 0;
	DB_RESULT		result;
	DB_ROW			row;

	if (0 != hostid)
	{
	  /* template */
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select 0,dst.itemid,dst.key_,gi.drawtype,gi.sortorder,gi.color,gi.yaxisside,gi.calc_fnc,"
				"gi.type,i.flags,gi.graphid"
			" from graphs_items gi,items i,items dst"
			" where gi.itemid=i.itemid"
			" and i.key_=dst.key_");
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " and dst.hostid=" ZBX_FS_UI64, hostid); 
	}
	else
	  {
	    /* host */
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select gi.itemid,i.itemid,i.key_,gi.drawtype,gi.sortorder,gi.color,gi.yaxisside,gi.calc_fnc,"
				"gi.type,i.flags,gi.graphid"
			" from graphs_items gi,items i"
			" where gi.itemid=i.itemid");
	  }
	//zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " and dst.hostid=" ZBX_FS_UI64, hostid); 
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, " and gi.graphid", graphs_ids->values,
			graphs_ids->values_num);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " order by i.key_");

	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		graphs_items_entry_t	*found, temp_t;
		graph_item_entry	*gitem;

		gitem = (graph_item_entry*)zbx_malloc(NULL, sizeof(graph_item_entry));
	      
		ZBX_STR2UINT64(gitem->gitemid, row[0]);
		ZBX_STR2UINT64(gitem->itemid, row[1]);
		zbx_strlcpy(gitem->key, row[2], sizeof(gitem->key));
		gitem->drawtype_orig = atoi(row[3]);
		gitem->sortorder_orig = atoi(row[4]);
		zbx_strlcpy(gitem->color_orig, row[5], sizeof(gitem->color_orig));
		gitem->yaxisside_orig = atoi(row[6]);
		gitem->calc_fnc_orig = atoi(row[7]);
		gitem->type_orig = atoi(row[8]);
		gitem->flags = (unsigned char)atoi(row[9]);

		
		ZBX_STR2UINT64(temp_t.graphid, row[10]);

		zabbix_log(LOG_LEVEL_INFORMATION, "TTT GITEM NEXT: gitemid: %lu, itemid: %lu, key: %s, graphid: ->%lu<-", gitem->gitemid, gitem->itemid, gitem->key, temp_t.graphid);

		if (NULL != (found = (graphs_items_entry_t*)zbx_hashset_search(graphs_items, &temp_t)))
		{
			zbx_vector_gitems_append(&(found->gitems), gitem);
		}
		else
		{
		  //graphs_items_entry_t local_temp_t;

			zbx_vector_gitems_create(&(temp_t.gitems));
			zbx_vector_gitems_append(&(temp_t.gitems), gitem);
			ZBX_STR2UINT64(temp_t.graphid, row[10]);

			zbx_hashset_insert(graphs_items, &temp_t, sizeof(temp_t));
		}

	}

	zbx_free(sql);
	DBfree_result(result);
}

static void	get_target_host_main_data(zbx_uint64_t hostid, zbx_vector_str_t *templates_graphs_names,
					  zbx_vector_uint64_t *host_graphs_ids, zbx_hashset_t *host_graphs_main_data)
{
	char		*sql = NULL;
	size_t		sql_alloc = 256, sql_offset = 0;
	DB_RESULT	result;
	DB_ROW		row;

	sql = (char *)zbx_malloc(sql, sql_alloc);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select distinct g.graphid,g.name,g.width,g.height,g.yaxismin,g.yaxismax,g.show_work_period"
			",g.show_triggers,g.graphtype,g.show_legend,g.show_3d,g.percent_left,g.percent_right"
			",g.ymin_type,g.ymax_type,g.ymin_itemid,g.ymax_itemid,g.flags,g.discover"
			" from graphs g,graphs_items gi,items i"
			" where g.graphid=gi.graphid"
				" and gi.itemid=i.itemid"
				" and i.hostid=" ZBX_FS_UI64
				" and g.templateid is null"
		     " and", hostid);

	  DBadd_str_condition_alloc(&sql, &sql_alloc, &sql_offset, "g.name",
			(const char**)templates_graphs_names->values, templates_graphs_names->values_num);
	  
	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		zbx_uint64_t	graphid;
		zbx_graph_copy_t graph_copy;

		ZBX_STR2UINT64(graphid, row[0]);
		zbx_vector_uint64_append(host_graphs_ids, graphid);

		// select data that might get updates for the target graph
		
		graph_copy.graphid = graphid;
		graph_copy.name_orig = zbx_strdup(NULL, row[1]);
		graph_copy.width_orig = atoi(row[2]);
		graph_copy.height_orig = atoi(row[3]);
		graph_copy.yaxismin_orig = atof(row[4]);
		graph_copy.yaxismax_orig = atof(row[5]);
		graph_copy.show_work_period_orig = (unsigned char)atoi(row[6]);
		graph_copy.show_triggers_orig = (unsigned char)atoi(row[7]); 
		graph_copy.graphtype_orig = (unsigned char)atoi(row[8]);
		graph_copy.show_legend_orig = (unsigned char)atoi(row[9]);
		graph_copy.show_3d_orig = (unsigned char)atoi(row[10]);
		graph_copy.percent_left_orig = atof(row[11]);
		graph_copy.percent_right_orig = atof(row[12]);
		graph_copy.ymin_type_orig = (unsigned char)atoi(row[13]);
		graph_copy.ymax_type_orig = (unsigned char)atoi(row[14]);
		ZBX_DBROW2UINT64(graph_copy.ymin_itemid_orig, row[15]);
		ZBX_DBROW2UINT64(graph_copy.ymax_itemid_orig, row[16]);
		graph_copy.flags_orig = (unsigned char)atoi(row[17]);
		graph_copy.discover_orig = (unsigned char)atoi(row[18]);

		zbx_hashset_insert(host_graphs_main_data, &graph_copy, sizeof(graph_copy));
	}

	zbx_free(sql);
	DBfree_result(result);
}

static int	mark_updates_for_host_graph(zbx_graph_copy_t *template_graph_copy, zbx_graph_copy_t *host_graph_copy_found)
{
	int	res = FAIL;

	if (0 != strcmp(template_graph_copy->name, host_graph_copy_found->name_orig))
	{
		host_graph_copy_found->name = zbx_strdup(NULL, template_graph_copy->name);
		host_graph_copy_found->update_flags |= ZBX_FLAG_LINK_GRAPH_UPDATE_NAME;
		res = SUCCEED;
	}

	if (template_graph_copy->width != host_graph_copy_found->width_orig)
	{
		host_graph_copy_found->width = template_graph_copy->width;
		host_graph_copy_found->update_flags |= ZBX_FLAG_LINK_GRAPH_UPDATE_WIDTH;
		res = SUCCEED;
	}

	if (template_graph_copy->height != host_graph_copy_found->height_orig)
	{
		host_graph_copy_found->height = template_graph_copy->height;
		host_graph_copy_found->update_flags |= ZBX_FLAG_LINK_GRAPH_UPDATE_HEIGHT;
		res = SUCCEED;
	}

	if (template_graph_copy->yaxismin != host_graph_copy_found->yaxismin_orig)
	{
		host_graph_copy_found->yaxismin = template_graph_copy->yaxismin;
		host_graph_copy_found->update_flags |= ZBX_FLAG_LINK_GRAPH_UPDATE_YAXISMIN;
		res = SUCCEED;
	}

	if (template_graph_copy->yaxismax != host_graph_copy_found->yaxismax_orig)
	{
		host_graph_copy_found->yaxismax = template_graph_copy->yaxismax;
		host_graph_copy_found->update_flags |= ZBX_FLAG_LINK_GRAPH_UPDATE_YAXISMAX;
		res = SUCCEED;
	}

	if (template_graph_copy->show_work_period != host_graph_copy_found->show_work_period_orig)
	{
		host_graph_copy_found->show_work_period = template_graph_copy->show_work_period;
		host_graph_copy_found->update_flags |= ZBX_FLAG_LINK_GRAPH_UPDATE_SHOW_WORK_PERIOD;
		res = SUCCEED;
	}

	if (template_graph_copy->show_triggers != host_graph_copy_found->show_triggers_orig)
	{
		host_graph_copy_found->show_triggers = template_graph_copy->show_triggers;
		host_graph_copy_found->update_flags |= ZBX_FLAG_LINK_GRAPH_UPDATE_SHOW_TRIGGERS;
		res = SUCCEED;
	}

	if (template_graph_copy->graphtype != host_graph_copy_found->graphtype_orig)
	{
		host_graph_copy_found->graphtype = template_graph_copy->graphtype;
		host_graph_copy_found->update_flags |= ZBX_FLAG_LINK_GRAPH_UPDATE_GRAPHTYPE;
		res = SUCCEED;
	}

	if (template_graph_copy->show_legend != host_graph_copy_found->show_legend_orig)
	{
		host_graph_copy_found->show_legend = template_graph_copy->show_legend;
		host_graph_copy_found->update_flags |= ZBX_FLAG_LINK_GRAPH_UPDATE_SHOW_LEGEND;
		res = SUCCEED;
	}

	if (template_graph_copy->show_3d != host_graph_copy_found->show_3d_orig)
	{
		host_graph_copy_found->show_3d = template_graph_copy->show_3d;
		host_graph_copy_found->update_flags |= ZBX_FLAG_LINK_GRAPH_UPDATE_SHOW_3D;
		res = SUCCEED;
	}

	if (template_graph_copy->percent_left != host_graph_copy_found->percent_left_orig)
	{
		host_graph_copy_found->percent_left = template_graph_copy->percent_left;
		host_graph_copy_found->update_flags |= ZBX_FLAG_LINK_GRAPH_UPDATE_PERCENT_LEFT;
		res = SUCCEED;
	}

	if (template_graph_copy->percent_right != host_graph_copy_found->percent_right_orig)
	{
		host_graph_copy_found->percent_right = template_graph_copy->percent_right;
		host_graph_copy_found->update_flags |= ZBX_FLAG_LINK_GRAPH_UPDATE_PERCENT_RIGHT;
		res = SUCCEED;
	}

	if (template_graph_copy->ymin_type != host_graph_copy_found->ymin_type_orig)
	{
		host_graph_copy_found->ymin_type = template_graph_copy->ymin_type;
		host_graph_copy_found->update_flags |= ZBX_FLAG_LINK_GRAPH_UPDATE_YMIN_TYPE;
		res = SUCCEED;
	}

	if (template_graph_copy->ymax_type != host_graph_copy_found->ymax_type_orig)
	{
		host_graph_copy_found->ymax_type = template_graph_copy->ymax_type;
		host_graph_copy_found->update_flags |= ZBX_FLAG_LINK_GRAPH_UPDATE_YMAX_TYPE;
		res = SUCCEED;
	}

	if (template_graph_copy->ymin_itemid != host_graph_copy_found->ymin_itemid_orig)
	{
		host_graph_copy_found->ymin_itemid = template_graph_copy->ymin_itemid;
		host_graph_copy_found->update_flags |= ZBX_FLAG_LINK_GRAPH_UPDATE_YMIN_ITEMID;
		res = SUCCEED;
	}

	if (template_graph_copy->ymax_itemid != host_graph_copy_found->ymax_itemid_orig)
	{
		host_graph_copy_found->ymax_itemid = template_graph_copy->ymax_itemid;
		host_graph_copy_found->update_flags |= ZBX_FLAG_LINK_GRAPH_UPDATE_YMAX_ITEMID;
		res = SUCCEED;
	}

	if (template_graph_copy->flags != host_graph_copy_found->flags_orig)
	{
		host_graph_copy_found->flags = template_graph_copy->flags;
		host_graph_copy_found->update_flags |= ZBX_FLAG_LINK_GRAPH_UPDATE_FLAGS;
		res = SUCCEED;
	}

	if (template_graph_copy->discover != host_graph_copy_found->discover_orig)
	{
		host_graph_copy_found->discover = template_graph_copy->discover;
		host_graph_copy_found->update_flags |= ZBX_FLAG_LINK_GRAPH_UPDATE_DISCOVER;
		res = SUCCEED;
	}

	return res;
}

static int	mark_updates_for_host_graph_items(graphs_items_entry_t *graphs_items_template_entry_found,
		graphs_items_entry_t *graphs_items_host_entry_found)
{
	int j, res = FAIL;

	for (j = 0; j < graphs_items_template_entry_found->gitems.values_num; j++)
	{
		graph_item_entry	*template_entry  = graphs_items_template_entry_found->gitems.values[j];
		graph_item_entry	*host_entry = graphs_items_host_entry_found->gitems.values[j];

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

		if (template_entry->color_orig != host_entry->color_orig)
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

static void	process_graphs(zbx_vector_graphs_copies_t *graphs_copies_templates,
		zbx_hashset_t *host_graphs_main_data, zbx_hashset_t *templates_graphs_items,
			       zbx_hashset_t *host_graphs_items, int *upd_graphs,
			       zbx_vector_graphs_copies_t *graphs_copies_insert, int *total_insert_gitems_count)
{
	/* iterate over templates graphs
	   iterate over target host graphs
  
      	get vector of graphs items for template and target
  	iterate and compare each
  	if they are the same - mark_for_updates in the tempate data
	otherwise - add to the new list of inserts */

	int			i, j, found_match = FAIL;
	zbx_graph_copy_t	*template_graph_copy, *host_graph_copy_found;
	zbx_hashset_iter_t	iter1;

	zabbix_log(LOG_LEVEL_INFORMATION, "RRRRRRRRRRRRRRRR");
	
	for (i = 0; i < graphs_copies_templates->values_num; i++)
	{
  		graphs_items_entry_t	*graphs_items_template_entry_found, graphs_items_template_entry_temp_t;

		template_graph_copy = graphs_copies_templates->values[i];
		graphs_items_template_entry_temp_t.graphid = template_graph_copy->graphid;

		zabbix_log(LOG_LEVEL_INFORMATION, "RRR next template graphid: %lu", template_graph_copy->graphid);

		
		// search template graph_items
		if (NULL != (graphs_items_template_entry_found = (graphs_items_entry_t*)zbx_hashset_search(templates_graphs_items,
				&graphs_items_template_entry_temp_t)))
		{
			zbx_hashset_iter_reset(host_graphs_main_data, &iter1);

			// iterate over host graphs
			while (NULL != (host_graph_copy_found = (zbx_graph_copy_t *)zbx_hashset_iter_next(&iter1)))
			{
			  
				graphs_items_entry_t	*graphs_items_host_entry_found, graphs_items_host_entry_temp_t;

			zabbix_log(LOG_LEVEL_INFORMATION, "RRR\t\t next host graph: %lu",host_graph_copy_found->graphid);

				graphs_items_host_entry_temp_t.graphid = host_graph_copy_found->graphid;

				// iterate over host graphs items
				if (NULL != (graphs_items_host_entry_found = (graphs_items_entry_t*)zbx_hashset_search(
						host_graphs_items, &graphs_items_host_entry_temp_t)))
				{
					zabbix_log(LOG_LEVEL_INFORMATION, "RRR\t\t\t first values num and second : %d, %d",graphs_items_template_entry_found->gitems.values_num, graphs_items_host_entry_found->gitems.values_num);

					
					//graphs_items_host_entry_found->gitms
					if (graphs_items_template_entry_found->gitems.values_num  !=
							graphs_items_host_entry_found->gitems.values_num)
					{
						continue;
					}

					for (j = 0; j < graphs_items_template_entry_found->gitems.values_num; j++)
					{

					  zabbix_log(LOG_LEVEL_INFORMATION, "RRR\t\t\t comparing ->%s<- and ->%s<-",graphs_items_template_entry_found->gitems.values[j]->key, graphs_items_host_entry_found->gitems.values[j]->key);

					  
			    			if (0 != strcmp(graphs_items_template_entry_found->gitems.values[j]->key,
								graphs_items_host_entry_found->gitems.values[j]->key))
						{
							continue;
						}
					}

					found_match = SUCCEED;

					if (SUCCEED == mark_updates_for_host_graph(template_graph_copy, host_graph_copy_found)
						|| SUCCEED == mark_updates_for_host_graph_items(
						graphs_items_template_entry_found, graphs_items_host_entry_found))
					{
					  // mark graph items for update

						
						(*upd_graphs)++;
					}
					break;
				}
			}

			/* not found any entries on host */
			if (FAIL == found_match)
			{

			  zbx_graph_copy_t *graph_copy;
			  graph_copy = (zbx_graph_copy_t*)zbx_malloc(NULL, sizeof(zbx_graph_copy_t));

			  // will need to extract fitemid
			  graph_copy->templateid = graphs_items_template_entry_temp_t.graphid;

			  graph_copy->name = DBdyn_escape_string(template_graph_copy->name);
			  graph_copy->width = template_graph_copy->width;
			  graph_copy->height = template_graph_copy->height;
			  graph_copy->yaxismin = template_graph_copy->yaxismin;
			  graph_copy->yaxismax = template_graph_copy->yaxismax;
			  graph_copy->show_work_period = template_graph_copy->show_work_period;
			  graph_copy->show_triggers = template_graph_copy->show_triggers;
			  graph_copy->graphtype = template_graph_copy->graphtype;
			  graph_copy->show_legend = template_graph_copy->show_legend;
			  graph_copy->show_3d = template_graph_copy->show_3d;
			  graph_copy->percent_left = template_graph_copy->percent_left;
			  graph_copy->percent_right = template_graph_copy->percent_right;
			  graph_copy->ymin_type = template_graph_copy->ymin_type;
			  graph_copy->ymax_type = template_graph_copy->ymax_type;
			  //WARNING SQL_ID_NS
			  zabbix_log(LOG_LEVEL_INFORMATION, "OMEGA ymin: %lu", template_graph_copy->ymin_itemid);
			  graph_copy->ymin_itemid = template_graph_copy->ymin_itemid;
			  graph_copy->ymax_itemid = template_graph_copy->ymax_itemid;
			  graph_copy->flags = template_graph_copy->flags;
			  graph_copy->discover = template_graph_copy->discover;

			  total_insert_gitems_count += graphs_items_template_entry_found->gitems.values_num;
			  zbx_vector_graphs_copies_append(graphs_copies_insert, graph_copy);
			}
			
		}
	}
}

static void	update_graphs_items_updates(char **sql2, size_t *sql_alloc2, size_t *sql_offset2,
		zbx_graph_copy_t *found, zbx_hashset_t *host_graphs_items)
{
	int			j;
	const char		*d2;
	graphs_items_entry_t	*graphs_items_host_entry_found, graphs_items_host_entry_temp_t;

	graphs_items_host_entry_temp_t.graphid = found->graphid;

	if (NULL != (graphs_items_host_entry_found = (graphs_items_entry_t*)zbx_hashset_search(host_graphs_items,
			&graphs_items_host_entry_temp_t)))
	{
		for (j = 0; j < graphs_items_host_entry_found->gitems.values_num; j++)
		{
			graph_item_entry	*template_entry  = graphs_items_host_entry_found->gitems.values[j];

			d2 = "";

			if (0 != (template_entry->update_flags & ZBX_FLAG_LINK_GRAPHITEM_UPDATE))
			{
				zbx_strcpy_alloc(sql2, sql_alloc2, sql_offset2, "update graphs_items set ");

				if (0 != (found->update_flags & ZBX_FLAG_LINK_GRAPHITEM_UPDATE_DRAWTYPE))
				{
					zbx_snprintf_alloc(sql2, sql_alloc2, sql_offset2, "drawtype=%d",
							template_entry->drawtype_new);
					d2 = ",";
				}

				if (0 != (found->update_flags & ZBX_FLAG_LINK_GRAPHITEM_UPDATE_SORTORDER))
				{
				  zbx_snprintf_alloc(sql2, sql_alloc2, sql_offset2, "%ssortorder=%d", d2,
							template_entry->sortorder_new);
					d2 = ",";
				}

				if (0 != (found->update_flags & ZBX_FLAG_LINK_GRAPHITEM_UPDATE_COLOR))
				{
					char *color_esc = DBdyn_escape_string(template_entry->color_new);

					zbx_snprintf_alloc(sql2, sql_alloc2, sql_offset2, "%scolor='%s'", d2,
							color_esc);
					zbx_free(color_esc);
					d2 = ",";

				}

				if (0 != (found->update_flags & ZBX_FLAG_LINK_GRAPHITEM_UPDATE_YAXISSIDE))
				{
				  zbx_snprintf_alloc(sql2, sql_alloc2, sql_offset2, "%syaxisside=%d", d2, 
							template_entry->yaxisside_new);
					d2 = ",";
				}

				if (0 != (found->update_flags & ZBX_FLAG_LINK_GRAPHITEM_UPDATE_CALC_FNC))
				{
				  zbx_snprintf_alloc(sql2, sql_alloc2, sql_offset2, "%scalc_fnc=%d", d2,
							template_entry->calc_fnc_new);
					d2 = ",";
				}

				if (0 != (found->update_flags & ZBX_FLAG_LINK_GRAPHITEM_UPDATE_TYPE))
				{
				  zbx_snprintf_alloc(sql2, sql_alloc2, sql_offset2, "%stype=%d", d2,
							template_entry->type_new);
					d2 = ",";
				}
			}

			zbx_snprintf_alloc(sql2, sql_alloc2, sql_offset2, " where gitemid=" ZBX_FS_UI64 ";\n",
					graphs_items_host_entry_found->graphid);

			DBexecute_overflowed_sql(sql2, sql_alloc2, sql_offset2);
		}
	}
}

static int	execute_graphs_updates(zbx_hashset_t *host_graphs_main_data, zbx_hashset_t *host_graphs_items)
{
	int			res = SUCCEED;
	const char		*d;
	char			*sql = NULL, *sql2 = NULL;
	size_t			sql_alloc = 512, sql_offset = 0, sql_alloc2 = 512, sql_offset2 = 0;
	zbx_hashset_iter_t	iter1;
	zbx_graph_copy_t	*found;
	
	zbx_hashset_iter_reset(host_graphs_main_data, &iter1);
	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	DBbegin_multiple_update(&sql2, &sql_alloc2, &sql_offset2);

	while (NULL != (found = (zbx_graph_copy_t *)zbx_hashset_iter_next(&iter1)))
	{
		d = "";

		if (0 != (found->update_flags & ZBX_FLAG_LINK_GRAPH_UPDATE))
		{
			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "update graphs set ");

			if (0 != (found->update_flags & ZBX_FLAG_LINK_GRAPH_UPDATE_NAME))
			{
				char	*name_esc = DBdyn_escape_string(found->name);
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "name='%s'", name_esc);
				zbx_free(name_esc);
				d = ",";
			}

			if (0 != (found->update_flags & ZBX_FLAG_LINK_GRAPH_UPDATE_WIDTH))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%swidth=%d", d, found->width);
				d = ",";
			}

			if (0 != (found->update_flags & ZBX_FLAG_LINK_GRAPH_UPDATE_HEIGHT))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%sheight=%d", d, found->width);
				d = ",";
			}

			if (0 != (found->update_flags & ZBX_FLAG_LINK_GRAPH_UPDATE_YAXISMIN))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%syaxismin=" ZBX_FS_DBL64_SQL ",", d,
						found->yaxismin);
				d = ",";
			}

			
			if (0 != (found->update_flags & ZBX_FLAG_LINK_GRAPH_UPDATE_YAXISMAX))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%syaxismax=" ZBX_FS_DBL64_SQL ",", d,
						found->yaxismax);
				d = ",";
			}

			if (0 != (found->update_flags & ZBX_FLAG_LINK_GRAPH_UPDATE_SHOW_WORK_PERIOD))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%show_work_period=%d", d,
						   (int)found->show_work_period);
				d = ",";
			}

			if (0 != (found->update_flags & ZBX_FLAG_LINK_GRAPH_UPDATE_SHOW_TRIGGERS))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%sshow_triggers=%d", d,
						(int)found->show_triggers);
				d = ",";
			}

			if (0 != (found->update_flags & ZBX_FLAG_LINK_GRAPH_UPDATE_GRAPHTYPE))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%sgraphtype=%d", d,
						(int)found->graphtype);
				d = ",";
			}

			if (0 != (found->update_flags & ZBX_FLAG_LINK_GRAPH_UPDATE_SHOW_LEGEND))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%sshow_legend=%d", d,
						(int)found->show_legend);
				d = ",";
			}

			if (0 != (found->update_flags & ZBX_FLAG_LINK_GRAPH_UPDATE_SHOW_3D))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%sshow_3d=%d", d,
						(int)found->show_3d);
				d = ",";
			}			

			if (0 != (found->update_flags & ZBX_FLAG_LINK_GRAPH_UPDATE_PERCENT_LEFT))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
						"%spercent_left=" ZBX_FS_DBL64_SQL ",", d, found->percent_left);
				d = ",";
			}			

			if (0 != (found->update_flags & ZBX_FLAG_LINK_GRAPH_UPDATE_PERCENT_RIGHT))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
						"%spercent_right=" ZBX_FS_DBL64_SQL ",", d, found->percent_right);
				d = ",";
			}			

			if (0 != (found->update_flags & ZBX_FLAG_LINK_GRAPH_UPDATE_YMIN_TYPE))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%symin_type=%d", d,
						(int)found->ymin_type);
				d = ",";
			}			

			if (0 != (found->update_flags & ZBX_FLAG_LINK_GRAPH_UPDATE_YMAX_TYPE))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%symax_type=%d", d,
						(int)found->ymax_type);
				d = ",";
			}			

			if (0 != (found->update_flags & ZBX_FLAG_LINK_GRAPH_UPDATE_YMIN_ITEMID))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%symin_itemid=%s", d,
						DBsql_id_ins(found->ymin_itemid));
				d = ",";
			}

			if (0 != (found->update_flags & ZBX_FLAG_LINK_GRAPH_UPDATE_YMAX_ITEMID))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%symax_itemid=%s", d,
						DBsql_id_ins(found->ymax_itemid));
				d = ",";
			}			

			if (0 != (found->update_flags & ZBX_FLAG_LINK_GRAPH_UPDATE_FLAGS))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%sflags=%d", d,
						(int)found->flags);
				d = ",";
			}			

			if (0 != (found->update_flags & ZBX_FLAG_LINK_GRAPH_UPDATE_DISCOVER))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%sdiscover=%d", d,
						(int)found->discover);
				d = ",";
			}

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%stemplateid=" ZBX_FS_UI64, d,
					found->graphid);
			d = ",";
			
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " where graphid=" ZBX_FS_UI64 ";\n",
					found->graphid);

			//DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset);

			// update gitems
			update_graphs_items_updates(&sql2, &sql_alloc2, &sql_offset2, found, host_graphs_items);
			
		}
	}

	DBend_multiple_update(&sql, &sql_alloc, &sql_offset);
	
	if (16 < sql_offset)
	{
		if (ZBX_DB_OK > DBexecute("%s", sql))
			res = FAIL;
	}

	zbx_free(sql);

	if (SUCCEED == res)
	{
		DBend_multiple_update(&sql2, &sql_alloc2, &sql_offset2);
	
		if (16 < sql_offset2)
		{
			if (ZBX_DB_OK > DBexecute("%s", sql2))
		       		res = FAIL;
		}

		zbx_free(sql2);
	}
	
	return res;
}

static int	execute_graphs_inserts(zbx_vector_graphs_copies_t *graphs_copies_insert, int *total_insert_gitems_count,
		zbx_hashset_t *templates_graphs_items)
{
	int			i, j, res;
	//char			*sql = NULL;
	//size_t			sql_alloc = 512, sql_offset = 0;
	zbx_db_insert_t		db_insert, db_insert_graphs_items;
	//zbx_trigger_functions_entry_t	*found, temp_t;
	zbx_uint64_t		graphid, graphs_itemsid;
	
	//DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	zbx_db_insert_prepare(&db_insert, "graphs", "graphid", "name", "width", "height", "yaxismin",
			"yaxismax", "templateid", "show_work_period", "show_triggers", "graphtype", "show_legend",
			"show_3d", "percent_left", "percent_right", "ymin_type", "ymax_type", "ymin_itemid",
			"ymax_itemid", "flags", "discover", NULL);

	zbx_db_insert_prepare(&db_insert_graphs_items, "graphs_items", "gitemid", "graphid", "itemid", "drawtype",
			"sortorder", "color", "yaxisside", "calc_fnc", "type", NULL);

	graphid = DBget_maxid_num("graphs", graphs_copies_insert->values_num);
	graphs_itemsid = DBget_maxid_num("graphs_items", *total_insert_gitems_count);

	for (i = 0; i < graphs_copies_insert->values_num; i++)
	{
		char			*graph_copy_name;
		zbx_graph_copy_t	*graph_copy = graphs_copies_insert->values[i];
  		graphs_items_entry_t	*graphs_items_template_entry_found, graphs_items_template_entry_temp_t;

		zabbix_log(LOG_LEVEL_INFORMATION, "YMIN_ITEMID1: BEFORE1: %lu", graph_copy->ymin_itemid);
		zabbix_log(LOG_LEVEL_INFORMATION, "YMIN_ITEMID1: BEFORE2: %s", DBsql_id_ins(graph_copy->ymin_itemid));

		zabbix_log(LOG_LEVEL_INFORMATION, "INSERT BADGER2 ymin_itemid: %lu", graph_copy->ymin_itemid);
		zabbix_log(LOG_LEVEL_INFORMATION, "INSERT BADGER2 templated: %lu", graph_copy->templateid);

		zabbix_log(LOG_LEVEL_INFORMATION, "INSERT BADGER2 name: %s", graph_copy->name);
		zabbix_log(LOG_LEVEL_INFORMATION, "INSERT BADGER2 width: %d", graph_copy->width);
		zabbix_log(LOG_LEVEL_INFORMATION, "INSERT BADGER2 yaxismin: %f", graph_copy->yaxismin);
		zabbix_log(LOG_LEVEL_INFORMATION, "INSERT BADGER2 ymaxtype: %d", graph_copy->ymax_type);

		graph_copy_name = DBdyn_escape_string(graph_copy->name);
										
		zbx_db_insert_add_values(&db_insert, graphid, graph_copy_name, graph_copy->width,
				graph_copy->height, graph_copy->yaxismin, graph_copy->yaxismax, graph_copy->templateid,
				(int)graph_copy->show_work_period, (int)(graph_copy->show_triggers),
				(int)(graph_copy->graphtype), (int)(graph_copy->show_legend),
				(int)(graph_copy->show_3d), graph_copy->percent_left, graph_copy->percent_right,
				(int)(graph_copy->ymin_type), (int)(graph_copy->ymax_type),
				graph_copy->ymin_itemid, graph_copy->ymax_itemid,
				(int)(graph_copy->flags), (int)(graph_copy->discover));

		zbx_free(graph_copy_name);

		graphs_items_template_entry_temp_t.graphid = graph_copy->templateid;

		if (NULL != (graphs_items_template_entry_found = (graphs_items_entry_t*)zbx_hashset_search(templates_graphs_items,
				&graphs_items_template_entry_temp_t)))
		{
			for (j = 0; j < graphs_items_template_entry_found->gitems.values_num; j++)
			{
				graph_item_entry	*template_entry;
				char			*color_orig_esc;

				template_entry = graphs_items_template_entry_found->gitems.values[j];
				color_orig_esc = DBdyn_escape_string(template_entry->color_orig);

				zbx_db_insert_add_values(&db_insert_graphs_items, graphs_itemsid++, graphid,
						template_entry->itemid, template_entry->drawtype_orig,
						template_entry->sortorder_orig,
					        color_orig_esc,
					        template_entry->yaxisside_orig, template_entry->calc_fnc_orig,
						template_entry->type_orig);
				zbx_free(color_orig_esc);
			}
		}

		graphid++;
	}

	res = zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);

	if (SUCCEED == res)
		zbx_db_insert_execute(&db_insert_graphs_items);

	zbx_db_insert_clean(&db_insert_graphs_items);

	return res;
}

int	DBcopy_template_graphs(zbx_uint64_t hostid, const zbx_vector_uint64_t *templateids)
{
	int					upd_graphs = 0, total_insert_gitems_count = 0, res = SUCCEED;
  	zbx_vector_graphs_copies_t		graphs_copies_templates;
	zbx_vector_graphs_copies_t		graphs_copies_insert;
	zbx_hashset_t				host_graphs_main_data;
	zbx_hashset_t				templates_graphs_items;
	zbx_hashset_t				host_graphs_items;

	zbx_vector_str_t			templates_graphs_names;
	zbx_vector_uint64_t			templates_graphs_ids;
	zbx_vector_uint64_t			host_graphs_ids;
	
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
#undef TRIGGER_GITEMS_HASHSET_DEF_SIZE
	zbx_vector_graphs_copies_create(&graphs_copies_templates);
	zbx_vector_graphs_copies_create(&graphs_copies_insert);
	
	get_templates_graphs_data(templateids, &graphs_copies_templates, &templates_graphs_ids, &templates_graphs_names);
	get_data(hostid, &templates_graphs_ids, &templates_graphs_items);

	update_same_itemids(hostid, &graphs_copies_templates);
	
	get_target_host_main_data(hostid, &templates_graphs_names, &host_graphs_ids, &host_graphs_main_data);

	zabbix_log(LOG_LEVEL_INFORMATION, "GETTING HOST GRAPHS_ITEMS");
	get_data(0, &host_graphs_ids, &host_graphs_items);
	zabbix_log(LOG_LEVEL_INFORMATION, "GETTING HOST GRAPHS_ITEMS DONE");
	
	zabbix_log(LOG_LEVEL_INFORMATION, "BADGER, PROCESS GRAPHS");

	process_graphs(&graphs_copies_templates, &host_graphs_main_data, &templates_graphs_items, &host_graphs_items,
			&upd_graphs, &graphs_copies_insert, &total_insert_gitems_count);

	zabbix_log(LOG_LEVEL_INFORMATION, "EXECUTE_GRAPHS_UPDATE");
	
	if (0 < upd_graphs)
		res = execute_graphs_updates(&host_graphs_main_data, &host_graphs_items);

	zabbix_log(LOG_LEVEL_INFORMATION, "EXECUTE_GRAPHS_INSERT");
	
	if (SUCCEED == res && 0 < graphs_copies_insert.values_num)
		res = execute_graphs_inserts(&graphs_copies_insert, &total_insert_gitems_count, &templates_graphs_items);
	  
	zbx_vector_str_clear_ext(&templates_graphs_names, zbx_str_free);
	zbx_vector_str_destroy(&templates_graphs_names);

	zbx_vector_uint64_destroy(&templates_graphs_ids);
	zbx_vector_uint64_destroy(&host_graphs_ids);

	zbx_vector_graphs_copies_clear_ext(&graphs_copies_templates, graphs_copies_clean_vec_entry);
	zbx_vector_graphs_copies_destroy(&graphs_copies_templates);

	zbx_vector_graphs_copies_clear_ext(&graphs_copies_insert, graphs_copies_clean_vec_entry);
	zbx_vector_graphs_copies_destroy(&graphs_copies_insert);
	
	graphs_copies_clean(&host_graphs_main_data);
	graphs_items_clean(&templates_graphs_items);
	graphs_items_clean(&host_graphs_items);

	
	
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return res;
}
