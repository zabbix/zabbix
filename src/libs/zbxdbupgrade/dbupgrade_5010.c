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

#include "common.h"
#include "db.h"
#include "dbupgrade.h"
#include "log.h"
#include "zbxalgo.h"
#include "../zbxalgo/vectorimpl.h"

/*
 * 5.2 development database patches
 */

#ifndef HAVE_SQLITE3

extern unsigned char	program_type;

static int	DBpatch_5010000(void)
{
	const ZBX_FIELD	field = {"default_lang", "en_GB", NULL, NULL, 5, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_5010001(void)
{
	const ZBX_FIELD	field = {"lang", "default", NULL, NULL, 7, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("users", &field, NULL);
}

static int	DBpatch_5010002(void)
{
	if (ZBX_DB_OK > DBexecute("update users set lang='default',theme='default' where alias='guest'"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_5010003(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("delete from profiles where idx in ('web.latest.toggle','web.latest.toggle_other')"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_5010004(void)
{
	DB_ROW		row;
	DB_RESULT	result;
	int		ret = SUCCEED;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	result = DBselect("select userid from profiles where idx='web.latest.sort' and value_str='lastclock'");

	while (NULL != (row = DBfetch(result)))
	{
		if (ZBX_DB_OK > DBexecute(
			"delete from profiles"
			" where userid='%s'"
				" and idx in ('web.latest.sort','web.latest.sortorder')", row[0]))
		{
			ret = FAIL;
			break;
		}
	}
	DBfree_result(result);

	return ret;
}

static int	DBpatch_5010005(void)
{
	const ZBX_FIELD	field = {"default_timezone", "system", NULL, NULL, 50, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_5010006(void)
{
	const ZBX_FIELD	field = {"timezone", "default", NULL, NULL, 50, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("users", &field);
}

static int	DBpatch_5010007(void)
{
	const ZBX_FIELD	field = {"login_attempts", "5", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_5010008(void)
{
	const ZBX_FIELD	field = {"login_block", "30s", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_5010009(void)
{
	const ZBX_FIELD	field = {"show_technical_errors", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_5010010(void)
{
	const ZBX_FIELD	field = {"validate_uri_schemes", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_5010011(void)
{
	const ZBX_FIELD	field = {"uri_valid_schemes", "http,https,ftp,file,mailto,tel,ssh", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_5010012(void)
{
	const ZBX_FIELD	field = {"x_frame_options", "SAMEORIGIN", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_5010013(void)
{
	const ZBX_FIELD	field = {"iframe_sandboxing_enabled", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_5010014(void)
{
	const ZBX_FIELD	field = {"iframe_sandboxing_exceptions", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_5010015(void)
{
	const ZBX_FIELD	field = {"max_overview_table_size", "50", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_5010016(void)
{
	const ZBX_FIELD	field = {"history_period", "24h", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_5010017(void)
{
	const ZBX_FIELD	field = {"period_default", "1h", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_5010018(void)
{
	const ZBX_FIELD	field = {"max_period", "2y", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_5010019(void)
{
	const ZBX_FIELD	field = {"socket_timeout", "3s", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_5010020(void)
{
	const ZBX_FIELD	field = {"connect_timeout", "3s", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_5010021(void)
{
	const ZBX_FIELD	field = {"media_type_test_timeout", "65s", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_5010022(void)
{
	const ZBX_FIELD	field = {"script_timeout", "60s", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_5010023(void)
{
	const ZBX_FIELD	field = {"item_test_timeout", "60s", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_5010024(void)
{
	const ZBX_FIELD	field = {"session_key", "", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_5010025(void)
{
	const ZBX_FIELD field = {"value", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("hostmacro", &field, NULL);
}

static int	DBpatch_5010026(void)
{
	const ZBX_FIELD field = {"value", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("globalmacro", &field, NULL);
}

static int	DBpatch_5010027(void)
{
	const ZBX_FIELD	old_field = {"data", "", NULL, NULL, 0, ZBX_TYPE_SHORTTEXT, ZBX_NOTNULL, 0};
	const ZBX_FIELD	field = {"data", "", NULL, NULL, 0, ZBX_TYPE_TEXT, ZBX_NOTNULL, 0};

	return DBmodify_field_type("task_data", &field, &old_field);
}

static int	DBpatch_5010028(void)
{
	const ZBX_FIELD	old_field = {"info", "", NULL, NULL, 0, ZBX_TYPE_SHORTTEXT, ZBX_NOTNULL, 0};
	const ZBX_FIELD	field = {"info", "", NULL, NULL, 0, ZBX_TYPE_TEXT, ZBX_NOTNULL, 0};

	return DBmodify_field_type("task_result", &field, &old_field);
}

static int	DBpatch_5010029(void)
{
	const ZBX_FIELD	old_field = {"params", "", NULL, NULL, 0, ZBX_TYPE_SHORTTEXT, ZBX_NOTNULL, 0};
	const ZBX_FIELD	field = {"params", "", NULL, NULL, 0, ZBX_TYPE_TEXT, ZBX_NOTNULL, 0};

	return DBmodify_field_type("items", &field, &old_field);
}

static int	DBpatch_5010030(void)
{
	const ZBX_FIELD	old_field = {"description", "", NULL, NULL, 0, ZBX_TYPE_SHORTTEXT, ZBX_NOTNULL, 0};
	const ZBX_FIELD	field = {"description", "", NULL, NULL, 0, ZBX_TYPE_TEXT, ZBX_NOTNULL, 0};

	return DBmodify_field_type("items", &field, &old_field);
}

static int	DBpatch_5010031(void)
{
	const ZBX_FIELD	old_field = {"posts", "", NULL, NULL, 0, ZBX_TYPE_SHORTTEXT, ZBX_NOTNULL, 0};
	const ZBX_FIELD	field = {"posts", "", NULL, NULL, 0, ZBX_TYPE_TEXT, ZBX_NOTNULL, 0};

	return DBmodify_field_type("items", &field, &old_field);
}

static int	DBpatch_5010032(void)
{
	const ZBX_FIELD	old_field = {"headers", "", NULL, NULL, 0, ZBX_TYPE_SHORTTEXT, ZBX_NOTNULL, 0};
	const ZBX_FIELD	field = {"headers", "", NULL, NULL, 0, ZBX_TYPE_TEXT, ZBX_NOTNULL, 0};

	return DBmodify_field_type("items", &field, &old_field);
}

static int	DBpatch_5010033(void)
{
	const ZBX_FIELD	field = {"custom_interfaces", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("hosts", &field);
}

static int	DBpatch_5010034(void)
{
	const ZBX_FIELD	old_field = {"value_str", "", NULL, NULL, 255, ZBX_TYPE_SHORTTEXT, ZBX_NOTNULL, 0};
	const ZBX_FIELD	field = {"value_str", "", NULL, NULL, 0, ZBX_TYPE_TEXT, ZBX_NOTNULL, 0};

	return DBmodify_field_type("profiles", &field, &old_field);
}

static int	DBpatch_5010035(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("delete from profiles where idx like 'web.hostsmon.filter.%%' or idx like 'web.problem.filter%%'"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_5010036(void)
{
	const ZBX_FIELD	field = {"event_name", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("triggers", &field);
}

static int	DBpatch_5010038(void)
{
	const ZBX_FIELD field = {"templateid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_field("dashboard", &field);
}

static int	DBpatch_5010039(void)
{
	const ZBX_FIELD field = {"userid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0};

	return DBdrop_not_null("dashboard", &field);
}

static int	DBpatch_5010040(void)
{
	return DBcreate_index("dashboard", "dashboard_1", "userid", 0);
}

static int	DBpatch_5010041(void)
{
#ifdef HAVE_MYSQL	/* MySQL automatically creates index and might not remove it on some conditions */
	if (SUCCEED == DBindex_exists("dashboard", "c_dashboard_1"))
		return DBdrop_index("dashboard", "c_dashboard_1");
#endif
	return SUCCEED;
}

static int	DBpatch_5010042(void)
{
	return DBcreate_index("dashboard", "dashboard_2", "templateid", 0);
}

static int	DBpatch_5010043(void)
{
	const ZBX_FIELD	field = {"templateid", 0, "hosts", "hostid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("dashboard", 2, &field);
}

typedef struct
{
	uint64_t	screenitemid;
	uint64_t	screenid;
	int		resourcetype;
	uint64_t	resourceid;
	int		width;
	int		height;
	int		x;
	int		y;
	int		colspan;
	int		rowspan;
	int		elements;
	int		style;
	char		*url;
	int		max_columns;
}
zbx_db_screen_item_t;

typedef struct
{
	uint64_t	widget_fieldid;
	int		type;
	char		*name;
	int		value_int;
	char		*value_str;
	uint64_t	value_itemid;
	uint64_t	value_graphid;
}
zbx_db_widget_field_t;

typedef struct
{
	int	position;
	int	span;
	int	size;
}
zbx_screen_item_dim_t;

typedef struct
{
	uint64_t	dashboardid;
	char		*name;
	uint64_t	userid;
	int		private;
	uint64_t	templateid;
}
zbx_db_dashboard_t;

typedef struct
{
	uint64_t	widgetid;
	uint64_t	dashboardid;
	char		*type;
	char		*name;
	int		x;
	int		y;
	int		width;
	int		height;
	int		view_mode;
}
zbx_db_widget_t;

#define DASHBOARD_MAX_COLS			(24)
#define DASHBOARD_MAX_ROWS			(64)
#define DASHBOARD_WIDGET_MIN_ROWS		(2)
#define DASHBOARD_WIDGET_MAX_ROWS		(32)
#define SCREEN_MAX_ROWS				(100)
#define SCREEN_MAX_COLS				(100)

#undef SCREEN_RESOURCE_CLOCK
#define SCREEN_RESOURCE_CLOCK			(7)
#undef SCREEN_RESOURCE_GRAPH
#define SCREEN_RESOURCE_GRAPH			(0)
#undef SCREEN_RESOURCE_SIMPLE_GRAPH
#define SCREEN_RESOURCE_SIMPLE_GRAPH		(1)
#undef SCREEN_RESOURCE_LLD_GRAPH
#define SCREEN_RESOURCE_LLD_GRAPH		(20)
#undef SCREEN_RESOURCE_LLD_SIMPLE_GRAPH
#define SCREEN_RESOURCE_LLD_SIMPLE_GRAPH	(19)
#undef SCREEN_RESOURCE_PLAIN_TEXT
#define SCREEN_RESOURCE_PLAIN_TEXT		(3)
#undef SCREEN_RESOURCE_URL
#define SCREEN_RESOURCE_URL			(11)

#define ZBX_WIDGET_FIELD_TYPE_INT32		(0)
#define ZBX_WIDGET_FIELD_TYPE_STR		(1)
#define ZBX_WIDGET_FIELD_TYPE_ITEM		(4)
#define ZBX_WIDGET_FIELD_TYPE_ITEM_PROTOTYPE	(5)
#define ZBX_WIDGET_FIELD_TYPE_GRAPH		(6)
#define ZBX_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE	(7)

/* #define ZBX_WIDGET_FIELD_RESOURCE_GRAPH				(0) */
/* #define ZBX_WIDGET_FIELD_RESOURCE_SIMPLE_GRAPH			(1) */
/* #define ZBX_WIDGET_FIELD_RESOURCE_GRAPH_PROTOTYPE		(2) */
/* #define ZBX_WIDGET_FIELD_RESOURCE_SIMPLE_GRAPH_PROTOTYPE	(3) */

#define ZBX_WIDGET_TYPE_CLOCK			("clock")
#define ZBX_WIDGET_TYPE_GRAPH_CLASSIC		("graph")
#define ZBX_WIDGET_TYPE_GRAPH_PROTOTYPE		("graphprototype")
#define ZBX_WIDGET_TYPE_PLAIN_TEXT		("plaintext")
#define ZBX_WIDGET_TYPE_URL			("url")

#define POS_EMPTY	(127)
#define POS_TAKEN	(1)

ZBX_VECTOR_DECL(scitem_dim, zbx_screen_item_dim_t)
ZBX_VECTOR_IMPL(scitem_dim, zbx_screen_item_dim_t)
ZBX_VECTOR_DECL(char, char)
ZBX_VECTOR_IMPL(char, char)

#define SKIP_EMPTY(vector,index)	if (POS_EMPTY == vector->values[index]) continue

static void	DBpatch_init_dashboard(zbx_db_dashboard_t *dashboard, char *name, uint64_t templateid)
{
	memset((void *)dashboard, 0, sizeof(zbx_db_dashboard_t));
	dashboard->templateid = templateid;
	dashboard->name = zbx_strdup(NULL, name);
}

static void	DBpatch_widget_field_free(zbx_db_widget_field_t *field)
{
	zbx_free(field->name);
	zbx_free(field->value_str);
	zbx_free(field);
}

static void	DBpatch_screen_item_free(zbx_db_screen_item_t *si)
{
	zbx_free(si->url);
	zbx_free(si);
}

static int	DBpatch_is_convertible_screen_item(int rt)
{
	return SCREEN_RESOURCE_CLOCK == rt || SCREEN_RESOURCE_GRAPH  == rt || SCREEN_RESOURCE_SIMPLE_GRAPH == rt ||
			SCREEN_RESOURCE_LLD_GRAPH == rt || SCREEN_RESOURCE_LLD_SIMPLE_GRAPH == rt ||
			SCREEN_RESOURCE_PLAIN_TEXT == rt || SCREEN_RESOURCE_URL == rt;
}

static size_t	DBpatch_array_max_used_index(char *array, size_t arr_size)
{
	size_t	i, m = 0;

	for (i = 0; i < arr_size; i++)
	{
		if (0 != array[i])
			m = i;
	}

	return m;
}

static void DBpatch_normalize_screen_items_pos(zbx_vector_ptr_t *scr_items)
{
	char	used_x[SCREEN_MAX_COLS], used_y[SCREEN_MAX_ROWS];
	char	keep_x[SCREEN_MAX_COLS], keep_y[SCREEN_MAX_ROWS];
	int	i, n, x;

	memset((void *)used_x, 0, sizeof(used_x));
	memset((void *)used_y, 0, sizeof(used_y));
	memset((void *)keep_x, 0, sizeof(keep_x));
	memset((void *)keep_y, 0, sizeof(keep_y));

	for (i = 0; i < scr_items->values_num; i++)
	{
		zbx_db_screen_item_t	*c = (zbx_db_screen_item_t *)scr_items->values[i];

		for (n = c->x; n < c->x + c->colspan && n < SCREEN_MAX_COLS; n++)
			used_x[n] = 1;
		for (n = c->y; n < c->y + c->rowspan && n < SCREEN_MAX_ROWS; n++)
			used_y[n] = 1;

		keep_x[c->x] = 1;
		if (c->x + c->colspan < SCREEN_MAX_COLS)
			keep_x[c->x + c->colspan] = 1;
		keep_y[c->y] = 1;
		if (c->y + c->rowspan < SCREEN_MAX_ROWS)
			keep_y[c->y + c->rowspan] = 1;
	}

#define COMPRESS_SCREEN_ITEMS(axis, span, a_size)							\
													\
do {													\
	for (x = (int)DBpatch_array_max_used_index(keep_ ## axis, a_size); x >= 0; x--)			\
	{												\
		if (0 != keep_ ## axis[x] && 0 != used_ ## axis[x])					\
			continue;									\
													\
		for (i = 0; i < scr_items->values_num; i++)						\
		{											\
			zbx_db_screen_item_t	*c = (zbx_db_screen_item_t *)scr_items->values[i];	\
													\
			if (x < c->axis)								\
				c->axis--;								\
													\
			if (x > c->axis && x < c->axis + c->span)					\
				c->span--;								\
		}											\
	}												\
} while (0)

	COMPRESS_SCREEN_ITEMS(x, colspan, SCREEN_MAX_COLS);
	COMPRESS_SCREEN_ITEMS(y, rowspan, SCREEN_MAX_ROWS);

#undef COMPRESS_SCREEN_ITEMS
}

static void	DBpatch_get_preferred_widget_size(zbx_db_screen_item_t *item, int *w, int *h)
{
	*w = item->width;
	*h = item->height;

	if (SCREEN_RESOURCE_LLD_GRAPH == item->resourcetype || SCREEN_RESOURCE_LLD_SIMPLE_GRAPH == item->resourcetype ||
			SCREEN_RESOURCE_GRAPH == item->resourcetype || SCREEN_RESOURCE_SIMPLE_GRAPH == item->resourcetype)
	{
		*h += 215;	/* SCREEN_LEGEND_HEIGHT */
	}

	if (SCREEN_RESOURCE_PLAIN_TEXT == item->resourcetype)
		*h = 2 + 2 * MIN(25, item->elements) / 5;
	else
		*h = (int)round((double)*h / 70);			/* WIDGET_ROW_HEIGHT */

	*w = (int)round((double)*w / 1920 * DASHBOARD_MAX_COLS);	/* DISPLAY_WIDTH */

	*w = MIN(DASHBOARD_MAX_COLS, MAX(1, *w));
	*h = MIN(DASHBOARD_WIDGET_MAX_ROWS, MAX(DASHBOARD_WIDGET_MIN_ROWS, *h));
}

static void	DBpatch_get_min_widget_size(zbx_db_screen_item_t *item, int *w, int *h)
{
	switch (item->resourcetype)
	{
		case SCREEN_RESOURCE_CLOCK:
			*w = 1; *h = 2;
			break;
		case SCREEN_RESOURCE_GRAPH:
		case SCREEN_RESOURCE_SIMPLE_GRAPH:
		case SCREEN_RESOURCE_LLD_GRAPH:
		case SCREEN_RESOURCE_LLD_SIMPLE_GRAPH:
			*w = 4; *h = 4;
			break;
		case SCREEN_RESOURCE_PLAIN_TEXT:
		case SCREEN_RESOURCE_URL:
			*w = 4; *h = 2;
			break;
		default:
			zabbix_log(LOG_LEVEL_WARNING, "%s: unknown resource type %d", __func__, item->resourcetype);
	}
}

static char	*lw_array_to_str(zbx_vector_char_t *v)
{
	static char	str[MAX_STRING_LEN];
	char		*ptr;
	int		i, max = MAX_STRING_LEN, len;

	ptr = str;
	len = (int)zbx_snprintf(ptr, (size_t)max, "[ ");
	ptr += len;
	max -= len;

	for (i = 0; 0 < max && i < v->values_num; i++)
	{
		if (POS_EMPTY != v->values[i])
		{
			len = (int)zbx_snprintf(ptr, (size_t)max, "%d:%d ", i, (int)v->values[i]);
			ptr += len;
			max -= len;
		}
	}

	if (max > 1)
		strcat(ptr, "]");

	return str;
}

static void	lw_array_debug(char *pfx, zbx_vector_char_t *v)
{
	zabbix_log(LOG_LEVEL_TRACE, "%s: %s", pfx, lw_array_to_str(v));
}

static void	int_array_debug(char *pfx, int *a, int alen, int emptyval)
{
	static char	str[MAX_STRING_LEN];
	char		*ptr;
	int		i, max = MAX_STRING_LEN, len;

	ptr = str;
	len = (int)zbx_snprintf(ptr, (size_t)max, "[ ");
	ptr += len;
	max -= len;

	for (i = 0; 0 < max && i < alen; i++)
	{
		if (emptyval != a[i])
		{
			len = (int)zbx_snprintf(ptr, (size_t)max, "%d:%d ", i, a[i]);
			ptr += len;
			max -= len;
		}
	}

	if (max > 1)
		strcat(ptr, "]");

	zabbix_log(LOG_LEVEL_TRACE, "%s: %s", pfx, str);
}

static zbx_vector_char_t	*lw_array_create(void)
{
	zbx_vector_char_t	*v;
	static char		fill[SCREEN_MAX_ROWS];

	if (0 == fill[0])
		memset(fill, POS_EMPTY, SCREEN_MAX_ROWS);

	v = (zbx_vector_char_t *)malloc(sizeof(zbx_vector_char_t));

	zbx_vector_char_create(v);
	zbx_vector_char_append_array(v, fill, SCREEN_MAX_ROWS);

	return v;
}

static void	lw_array_free(zbx_vector_char_t *v)
{
	if (NULL != v)
	{
		zbx_vector_char_destroy(v);
		zbx_free(v);
	}
}

static zbx_vector_char_t	*lw_array_create_fill(int start, size_t num)
{
	size_t			i;
	zbx_vector_char_t	*v;

	v = lw_array_create();

	for (i = (size_t)start; i < (size_t)start + num && i < (size_t)v->values_num; i++)
		v->values[i] = POS_TAKEN;

	return v;
}

static zbx_vector_char_t	*lw_array_diff(zbx_vector_char_t *a, zbx_vector_char_t *b)
{
	int			i;
	zbx_vector_char_t	*v;

	v = lw_array_create();

	for (i = 0; i < a->values_num; i++)
	{
		SKIP_EMPTY(a, i);
		if (POS_EMPTY == b->values[i])
			v->values[i] = a->values[i];
	}

	return v;
}

static zbx_vector_char_t	*lw_array_intersect(zbx_vector_char_t *a, zbx_vector_char_t *b)
{
	int			i;
	zbx_vector_char_t	*v;

	v = lw_array_create();

	for (i = 0; i < a->values_num; i++)
	{
		SKIP_EMPTY(a, i);
		if (POS_EMPTY != b->values[i])
			v->values[i] = a->values[i];
	}

	return v;
}

static int	lw_array_count(zbx_vector_char_t *v)
{
	int	i, c = 0;

	for (i = 0; i < v->values_num; i++)
	{
		if (POS_EMPTY != v->values[i])
			c++;
	}

	return c;
}

static int	lw_array_sum(zbx_vector_char_t *v)
{
	int	i, c = 0;

	for (i = 0; i < v->values_num; i++)
	{
		if (POS_EMPTY != v->values[i])
			c += v->values[i];
	}

	return c;
}

typedef struct
{
	int			index;	/* index for zbx_vector_scitem_dim_t */
	zbx_vector_char_t	*r_block;
}
sciitem_block_t;

static zbx_vector_char_t	*sort_dimensions;

static int	DBpatch_block_compare_func(const void *d1, const void *d2)
{
	const sciitem_block_t	*i1 = *(const sciitem_block_t **)d1;
	const sciitem_block_t	*i2 = *(const sciitem_block_t **)d2;
	zbx_vector_char_t	*diff1, *diff2;
	int			unsized_a, unsized_b;

	diff1 = lw_array_diff(i1->r_block, sort_dimensions);
	diff2 = lw_array_diff(i2->r_block, sort_dimensions);

	unsized_a = lw_array_count(diff1);
	unsized_b = lw_array_count(diff2);

	lw_array_free(diff1);
	lw_array_free(diff2);

	ZBX_RETURN_IF_NOT_EQUAL(unsized_a, unsized_b);

	return 0;
}

static zbx_vector_char_t	*DBpatch_get_axis_dimensions(zbx_vector_scitem_dim_t *scitems)
{
	int			i;
	zbx_vector_ptr_t	blocks;
	sciitem_block_t		*block;
	zbx_vector_char_t	*dimensions;

	zabbix_log(LOG_LEVEL_TRACE, "In %s()", __func__);

	zbx_vector_ptr_create(&blocks);
	dimensions = lw_array_create();

	for (i = 0; i < scitems->values_num; i++)
	{
		block = (sciitem_block_t *)malloc(sizeof(sciitem_block_t));
		block->r_block = lw_array_create_fill(scitems->values[i].position, (size_t)scitems->values[i].span);
		block->index = i;
		zbx_vector_ptr_append(&blocks, (void *)block);
	}

	sort_dimensions = dimensions;

	while (0 < blocks.values_num)
	{
		zbx_vector_char_t	*block_dimensions, *block_unsized, *r_block;
		int			block_dimensions_sum, block_unsized_count, size_overflow, n;

		zbx_vector_ptr_sort(&blocks, DBpatch_block_compare_func);
		block = blocks.values[0];
		r_block = block->r_block;

		block_dimensions = lw_array_intersect(dimensions, r_block);
		block_dimensions_sum = lw_array_sum(block_dimensions);
		lw_array_free(block_dimensions);

		block_unsized = lw_array_diff(r_block, dimensions);
		block_unsized_count = lw_array_count(block_unsized);
		size_overflow = scitems->values[block->index].size - block_dimensions_sum;

		if (0 < block_unsized_count)
		{
			for (n = 0; n < block_unsized->values_num; n++)
			{
				SKIP_EMPTY(block_unsized, n);
				dimensions->values[n] = (char)MAX(1, size_overflow / block_unsized_count);
				size_overflow -= dimensions->values[n];
				block_unsized_count--;
			}
		}
		else if (0 < size_overflow)
		{
			for (n = 0; n < r_block->values_num; n++)
			{
				double	factor;
				int	new_dimension;

				SKIP_EMPTY(r_block, n);
				factor = (double)(size_overflow + block_dimensions_sum) / block_dimensions_sum;
				new_dimension = (int)round(factor * dimensions->values[n]);
				block_dimensions_sum -= dimensions->values[n];
				size_overflow -= new_dimension - dimensions->values[n];
				dimensions->values[n] = (char)new_dimension;
			}
		}

		lw_array_free(block->r_block);
		zbx_free(block);
		lw_array_free(block_unsized);
		zbx_vector_ptr_remove(&blocks, 0);
	}

	zbx_vector_ptr_destroy(&blocks);

	zabbix_log(LOG_LEVEL_TRACE, "End of %s(): dim:%s", __func__, lw_array_to_str(dimensions));

	return dimensions;
}

/* modifies widget units in first argument */
static void	DBpatch_adjust_axis_dimensions(zbx_vector_char_t *d, zbx_vector_char_t *d_min, int target)
{
	int	dimensions_sum, i;

	zabbix_log(LOG_LEVEL_TRACE, "In %s(): d:%s", __func__, lw_array_to_str(d));
	zabbix_log(LOG_LEVEL_TRACE, "  d_min:%s", lw_array_to_str(d_min));

	dimensions_sum = lw_array_sum(d);

	while (dimensions_sum != target)
	{
		int	potential_index = -1;
		double	potential_value;

		for (i = 0; i < d->values_num; i++)
		{
			double	value;

			SKIP_EMPTY(d, i);
			value = (double)d->values[i] / d_min->values[i];

			if (0 > potential_index ||
					(dimensions_sum > target && value > potential_value) ||
					(dimensions_sum < target && value < potential_value))
			{
				potential_index = i;
				potential_value = value;
			}
		}

		if (0 <= potential_index)
		{
			zabbix_log(LOG_LEVEL_TRACE, "dim_sum:%d pot_idx/val:%d/%.2lf", dimensions_sum,
					potential_index, potential_value);
		}

		if (dimensions_sum > target && d->values[potential_index] == d_min->values[potential_index])
			break;

		if (dimensions_sum > target)
		{
			d->values[potential_index]--;
			dimensions_sum--;
		}
		else
		{
			d->values[potential_index]++;
			dimensions_sum++;
		}
	}

	zabbix_log(LOG_LEVEL_TRACE, "End of %s(): d:%s", __func__, lw_array_to_str(d));
}

static void	DBpatch_get_dashboard_dimensions(zbx_vector_ptr_t *scr_items, zbx_vector_char_t **x,
		zbx_vector_char_t **y)
{
	zbx_vector_char_t	*dim_x_pref, *dim_x_min;
	zbx_vector_char_t	*dim_y_pref, *dim_y_min;
	zbx_vector_scitem_dim_t	items_x_pref, items_y_pref;
	zbx_vector_scitem_dim_t	items_x_min, items_y_min;
	int			i;

	zabbix_log(LOG_LEVEL_TRACE, "In %s()", __func__);

	zbx_vector_scitem_dim_create(&items_x_pref);
	zbx_vector_scitem_dim_create(&items_y_pref);
	zbx_vector_scitem_dim_create(&items_x_min);
	zbx_vector_scitem_dim_create(&items_y_min);

	for (i = 0; i < scr_items->values_num; i++)
	{
		int			pref_size_w, pref_size_h;
		int			min_size_w, min_size_h;
		zbx_screen_item_dim_t	item;
		zbx_db_screen_item_t	*si;

		si = scr_items->values[i];
		DBpatch_get_preferred_widget_size(si, &pref_size_w, &pref_size_h);
		DBpatch_get_min_widget_size(si, &min_size_w, &min_size_h);

		item.position = si->x;
		item.span = si->colspan;
		item.size = MAX(pref_size_w, min_size_w);
		zbx_vector_scitem_dim_append(&items_x_pref, item);

		item.position = si->y;
		item.span = si->rowspan;
		item.size = MAX(pref_size_h, min_size_h);
		zbx_vector_scitem_dim_append(&items_y_pref, item);

		item.position = si->x;
		item.span = si->colspan;
		item.size = min_size_w;
		zbx_vector_scitem_dim_append(&items_x_min, item);

		item.position = si->y;
		item.span = si->rowspan;
		item.size = min_size_h;
		zbx_vector_scitem_dim_append(&items_y_min, item);
	}

	dim_x_pref = DBpatch_get_axis_dimensions(&items_x_pref);
	dim_x_min = DBpatch_get_axis_dimensions(&items_x_min);

	zabbix_log(LOG_LEVEL_TRACE, "%s: dim_x_pref:%s", __func__, lw_array_to_str(dim_x_pref));
	zabbix_log(LOG_LEVEL_TRACE, "  dim_x_min:%s", lw_array_to_str(dim_x_min));

	DBpatch_adjust_axis_dimensions(dim_x_pref, dim_x_min, DASHBOARD_MAX_COLS);

	dim_y_pref = DBpatch_get_axis_dimensions(&items_y_pref);
	dim_y_min = DBpatch_get_axis_dimensions(&items_y_min);

	if (DASHBOARD_MAX_ROWS < lw_array_sum(dim_y_pref))
		DBpatch_adjust_axis_dimensions(dim_y_pref, dim_y_min, DASHBOARD_MAX_ROWS);

	lw_array_free(dim_x_min);
	lw_array_free(dim_y_min);
	zbx_vector_scitem_dim_destroy(&items_x_pref);
	zbx_vector_scitem_dim_destroy(&items_y_pref);
	zbx_vector_scitem_dim_destroy(&items_x_min);
	zbx_vector_scitem_dim_destroy(&items_y_min);

	*x = dim_x_pref;
	*y = dim_y_pref;

	zabbix_log(LOG_LEVEL_TRACE, "End of %s(): x:%s y:%s", __func__, lw_array_to_str(*x), lw_array_to_str(*y));
}

static zbx_db_widget_field_t	*DBpatch_make_widget_field(int type, char *name, void *value)
{
	zbx_db_widget_field_t	*wf;

	wf = (zbx_db_widget_field_t *)zbx_calloc(NULL, 1, sizeof(zbx_db_widget_field_t));
	wf->name = zbx_strdup(NULL, name);
	wf->type = type;

	switch (type)
	{
		case ZBX_WIDGET_FIELD_TYPE_INT32:
			wf->value_int = *((int *)value);
			break;
		case ZBX_WIDGET_FIELD_TYPE_STR:
			wf->value_str = zbx_strdup(NULL, (char *)value);
			break;
		case ZBX_WIDGET_FIELD_TYPE_ITEM:
		case ZBX_WIDGET_FIELD_TYPE_ITEM_PROTOTYPE:
			wf->value_itemid = *((uint64_t *)value);
			break;
		case ZBX_WIDGET_FIELD_TYPE_GRAPH:
		case ZBX_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE:
			wf->value_graphid = *((uint64_t *)value);
			break;
		default:
			zabbix_log(LOG_LEVEL_WARNING, "%s: unknown field type: %d", __func__, type);
	}

	if (NULL == wf->value_str)
		wf->value_str = zbx_strdup(NULL, "");

	return wf;
}

static void DBpatch_widget_from_screen_item(zbx_db_screen_item_t *si, zbx_db_widget_t *w, zbx_vector_ptr_t *fields)
{
	zbx_db_widget_field_t	*f;
	int			tmp;

	w->name = zbx_strdup(NULL, "");
	w->view_mode = 0;	/* ZBX_WIDGET_VIEW_MODE_NORMAL */

#define ADD_FIELD(a, b, c)				\
							\
do {							\
	f = DBpatch_make_widget_field(a, b, c);		\
	zbx_vector_ptr_append(fields, (void *)f);	\
} while (0)

	switch (si->resourcetype)
	{
		case SCREEN_RESOURCE_CLOCK:
			w->type = zbx_strdup(NULL, ZBX_WIDGET_TYPE_CLOCK);

			/* here are below in this switch we add only those fields that are not */
			/* considered default by frontend API */

			if (0 != si->style)	/* style 0 is default, don't add */
				ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_INT32, "time_type", (void *)&si->style);
			if (2 == si->style)	/* TIME_TYPE_HOST */
				ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_ITEM, "itemid", (void *)&si->resourceid);
			break;
		case SCREEN_RESOURCE_GRAPH:
			w->type = zbx_strdup(NULL, ZBX_WIDGET_TYPE_GRAPH_CLASSIC);
			/* source_type = ZBX_WIDGET_FIELD_RESOURCE_GRAPH (0); don't add because it's default */
			ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_GRAPH, "graphid", (void *)&si->resourceid);
			break;
		case SCREEN_RESOURCE_SIMPLE_GRAPH:
			w->type = zbx_strdup(NULL, ZBX_WIDGET_TYPE_GRAPH_CLASSIC);
			tmp = 1;	/* source_type = ZBX_WIDGET_FIELD_RESOURCE_SIMPLE_GRAPH */
			ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_INT32, "source_type", (void *)&tmp);
			ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_ITEM, "itemid", (void *)&si->resourceid);
			break;
		case SCREEN_RESOURCE_LLD_GRAPH:
			w->type = zbx_strdup(NULL, ZBX_WIDGET_TYPE_GRAPH_PROTOTYPE);
			/* source_type = ZBX_WIDGET_FIELD_RESOURCE_GRAPH_PROTOTYPE (2); don't add because it's default */
			ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE, "graphid", (void *)&si->resourceid);
			/* add field "columns" because the default value is 2 */
			tmp = 1;
			ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_INT32, "columns", (void *)&tmp);
			/* don't add field "rows" because 1 is default */
			break;
		case SCREEN_RESOURCE_LLD_SIMPLE_GRAPH:
			w->type = zbx_strdup(NULL, ZBX_WIDGET_TYPE_GRAPH_PROTOTYPE);
			tmp = 3;	/* source_type = ZBX_WIDGET_FIELD_RESOURCE_SIMPLE_GRAPH_PROTOTYPE */
			ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_INT32, "source_type", (void *)&tmp);
			ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_ITEM_PROTOTYPE, "itemid", (void *)&si->resourceid);
			tmp = 1;
			ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_INT32, "columns", (void *)&tmp);
			/* don't add field "rows" because 1 is default */
			break;
		case SCREEN_RESOURCE_PLAIN_TEXT:
			w->type = zbx_strdup(NULL, ZBX_WIDGET_TYPE_PLAIN_TEXT);
			ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_ITEM, "itemids", (void *)&si->resourceid);
			if (0 != si->style)
				ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_INT32, "show_as_html", (void *)&si->style);
			if (25 != si->elements)
				ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_INT32, "show_lines", (void *)&si->elements);
			break;
		case SCREEN_RESOURCE_URL:
			w->type = zbx_strdup(NULL, ZBX_WIDGET_TYPE_URL);
			ADD_FIELD(ZBX_WIDGET_FIELD_TYPE_STR, "url", (void *)si->url);
			break;
		default:
			zabbix_log(LOG_LEVEL_WARNING, "%s: unknown screen resource type: %d", __func__,
					si->resourcetype);
	}
#undef ADD_FIELD
}

static char	*DBpatch_resourcetype_str(int rtype)
{
	switch (rtype)
	{
		case SCREEN_RESOURCE_CLOCK:
			return "clock";
		case SCREEN_RESOURCE_GRAPH:
			return "graph";
		case SCREEN_RESOURCE_SIMPLE_GRAPH:
			return "simplegraph";
		case SCREEN_RESOURCE_LLD_GRAPH:
			return "lldgraph";
		case SCREEN_RESOURCE_LLD_SIMPLE_GRAPH:
			return "lldsimplegraph";
		case SCREEN_RESOURCE_PLAIN_TEXT:
			return "plaintext";
		case SCREEN_RESOURCE_URL:
			return "url";
	}

	return "*unknown*";
}

static void	DBpatch_trace_screen_item(zbx_db_screen_item_t *item)
{
	zabbix_log(LOG_LEVEL_TRACE, "    screenitemid:" ZBX_FS_UI64 " screenid:" ZBX_FS_UI64,
			item->screenitemid, item->screenid);
	zabbix_log(LOG_LEVEL_TRACE, "        resourcetype: %s resourceid:" ZBX_FS_UI64,
			DBpatch_resourcetype_str(item->resourcetype), item->resourceid);
	zabbix_log(LOG_LEVEL_TRACE, "        w/h: %dx%d (x,y): (%d,%d) (c,rspan): (%d,%d)",
			item->width, item->height, item->x, item->y, item->colspan, item->rowspan);
}

static void	DBpatch_trace_widget(zbx_db_widget_t *w)
{
	zabbix_log(LOG_LEVEL_TRACE, "    widgetid:" ZBX_FS_UI64 " dbid:" ZBX_FS_UI64 " type:%s",
			w->widgetid, w->dashboardid, w->type);
	zabbix_log(LOG_LEVEL_TRACE, "    widget type: %s w/h: %dx%d (x,y): (%d,%d)",
			w->type, w->width, w->height, w->x, w->y);
}

/* adds new dashboard to the DB, sets new dashboardid in the struct */
static int 	DBpatch_add_dashboard(zbx_db_dashboard_t *dashboard)
{
	char	*name_esc;
	int	res;

	dashboard->dashboardid = DBget_maxid("dashboard");
	name_esc = DBdyn_escape_string(dashboard->name);

	zabbix_log(LOG_LEVEL_TRACE, "adding dashboard id:" ZBX_FS_UI64, dashboard->dashboardid);

	res = DBexecute("insert into dashboard (dashboardid,name,templateid) values ("
			ZBX_FS_UI64 ",'%s'," ZBX_FS_UI64 ")", dashboard->dashboardid, name_esc,
			dashboard->templateid);

	zbx_free(name_esc);

	return ZBX_DB_OK > res ? FAIL : SUCCEED;
}

/* adds new widget and widget fields to the DB */
static int	DBpatch_add_widget(uint64_t dashboardid, zbx_db_widget_t *widget, zbx_vector_ptr_t *fields)
{
	uint64_t	new_fieldid;
	int		i, ret = SUCCEED;
	char		*name_esc;

	widget->widgetid = DBget_maxid("widget");
	widget->dashboardid = dashboardid;
	name_esc = DBdyn_escape_string(widget->name);

	zabbix_log(LOG_LEVEL_TRACE, "adding widget id: " ZBX_FS_UI64 ", type: %s", widget->widgetid, widget->type);

	if (ZBX_DB_OK > DBexecute("insert into widget (widgetid,dashboardid,type,name,x,y,width,height,view_mode) "
			"values (" ZBX_FS_UI64 "," ZBX_FS_UI64 ",'%s','%s',%d,%d,%d,%d,%d)",
			widget->widgetid, widget->dashboardid, widget->type, name_esc, widget->x, widget->y,
			widget->width, widget->height, widget->view_mode))
	{
		ret = FAIL;
	}

	zbx_free(name_esc);

	if (SUCCEED == ret && 0 < fields->values_num)
		new_fieldid = DBget_maxid_num("widget_field", fields->values_num);

	for (i = 0; SUCCEED == ret && i < fields->values_num; i++)
	{
		char			*url_esc;
		zbx_db_widget_field_t	*f;

		f = (zbx_db_widget_field_t *)fields->values[i];
		url_esc = DBdyn_escape_string(f->value_str);

		if (ZBX_DB_OK > DBexecute("insert into widget_field (widget_fieldid,widgetid,type,name,value_int,"
				"value_str,value_itemid,value_graphid) values (" ZBX_FS_UI64 "," ZBX_FS_UI64 ",%d,"
				"'%s',%d,'%s',%s,%s)", new_fieldid++, widget->widgetid, f->type, f->name, f->value_int,
				url_esc, DBsql_id_ins(f->value_itemid), DBsql_id_ins(f->value_graphid)))
		{
			ret = FAIL;
		}

		zbx_free(url_esc);
	}

	return ret;
}

static int	DBpatch_delete_screen(uint64_t screenid)
{
	if (ZBX_DB_OK > DBexecute("delete from screens_items where screenid=" ZBX_FS_UI64, screenid))
		return FAIL;

	if (ZBX_DB_OK > DBexecute("delete from screens where screenid=" ZBX_FS_UI64, screenid))
		return FAIL;

	return SUCCEED;
}

#define OFFSET_ARRAY_SIZE	(SCREEN_MAX_ROWS + 1)

static int	DBpatch_convert_screen(uint64_t screenid, char *name, uint64_t templateid)
{
	DB_RESULT		result;
	DB_ROW			row;
	int			i, ret;
	zbx_db_screen_item_t	*scr_item;
	zbx_db_dashboard_t	dashboard;
	zbx_vector_ptr_t	screen_items;
	zbx_vector_char_t	*dim_x, *dim_y;
	int			offsets_x[OFFSET_ARRAY_SIZE], offsets_y[OFFSET_ARRAY_SIZE];

	result = DBselect(
			"select screenitemid,screenid,resourcetype,resourceid,width,height,x,y,colspan,rowspan"
			",elements,style,url,max_columns from screens_items where screenid=" ZBX_FS_UI64,
			screenid);

	if (NULL == result)
		return FAIL;

	zbx_vector_ptr_create(&screen_items);
	DBpatch_init_dashboard(&dashboard, name, templateid);

	while (NULL != (row = DBfetch(result)))
	{
		scr_item = (zbx_db_screen_item_t*)zbx_calloc(NULL, 1, sizeof(zbx_db_screen_item_t));

		ZBX_DBROW2UINT64(scr_item->screenitemid, row[0]);
		ZBX_DBROW2UINT64(scr_item->screenid, row[1]);
		scr_item->resourcetype = atoi(row[2]);
		ZBX_DBROW2UINT64(scr_item->resourceid, row[3]);
		scr_item->width = atoi(row[4]);
		scr_item->height = atoi(row[5]);
		scr_item->x = atoi(row[6]);
		scr_item->y = atoi(row[7]);
		scr_item->colspan = atoi(row[8]);
		scr_item->rowspan = atoi(row[9]);
		scr_item->elements = atoi(row[10]);
		scr_item->style = atoi(row[11]);
		scr_item->url = zbx_strdup(NULL, row[12]);
		scr_item->max_columns = atoi(row[13]);

		if (0 == scr_item->colspan)
		{
			scr_item->colspan = 1;
			zabbix_log(LOG_LEVEL_WARNING, "warning: colspan is 0, converted to 1 for item " ZBX_FS_UI64,
					scr_item->screenitemid);
		}

		if (0 == scr_item->rowspan)
		{
			scr_item->rowspan = 1;
			zabbix_log(LOG_LEVEL_WARNING, "warning: rowspan is 0, converted to 1 for item " ZBX_FS_UI64,
					scr_item->screenitemid);
		}

		if (SCREEN_MAX_COLS <= scr_item->x)
		{
			scr_item->x = SCREEN_MAX_COLS - 1;
			zabbix_log(LOG_LEVEL_WARNING, "warning: x is more than %d, limited for item " ZBX_FS_UI64,
					scr_item->x, scr_item->screenitemid);
		}

		if (0 > scr_item->x)
		{
			scr_item->x = 0;
			zabbix_log(LOG_LEVEL_WARNING, "warning: x is negative, set to 0 for item " ZBX_FS_UI64,
					scr_item->screenitemid);
		}

		if (SCREEN_MAX_ROWS <= scr_item->y)
		{
			scr_item->y = SCREEN_MAX_ROWS - 1;
			zabbix_log(LOG_LEVEL_WARNING, "warning: y is more than %d, limited for item " ZBX_FS_UI64,
					scr_item->y, scr_item->screenitemid);
		}

		if (0 > scr_item->y)
		{
			scr_item->y = 0;
			zabbix_log(LOG_LEVEL_WARNING, "warning: y is negative, set to 0 for item " ZBX_FS_UI64,
					scr_item->screenitemid);
		}

		DBpatch_trace_screen_item(scr_item);

		if (0 == DBpatch_is_convertible_screen_item(scr_item->resourcetype))
		{
			zabbix_log(LOG_LEVEL_WARNING, "discarding screen item " ZBX_FS_UI64
					" because it is not convertible", scr_item->screenitemid);
			DBpatch_screen_item_free(scr_item);
			continue;
		}

		zbx_vector_ptr_append(&screen_items, (void *)scr_item);
	}

	DBfree_result(result);

	if (screen_items.values_num > 0)
	{
		zabbix_log(LOG_LEVEL_TRACE, "total %d screen items", screen_items.values_num);

		DBpatch_normalize_screen_items_pos(&screen_items);
		DBpatch_get_dashboard_dimensions(&screen_items, &dim_x, &dim_y);

		lw_array_debug("dim_x", dim_x);
		lw_array_debug("dim_y", dim_y);

		offsets_x[0] = 0;
		offsets_y[0] = 0;
		for (i = 1; i < OFFSET_ARRAY_SIZE; i++)
		{
			offsets_x[i] = -1;
			offsets_y[i] = -1;
		}

		for (i = 0; i < dim_x->values_num; i++)
		{
			if (POS_EMPTY != dim_x->values[i])
				offsets_x[i + 1] = i == 0 ? dim_x->values[i] : offsets_x[i] + dim_x->values[i];
			if (POS_EMPTY != dim_y->values[i])
				offsets_y[i + 1] = i == 0 ? dim_y->values[i] : offsets_y[i] + dim_y->values[i];
		}

		int_array_debug("offsets_x", offsets_x, OFFSET_ARRAY_SIZE, -1);
		int_array_debug("offsets_y", offsets_y, OFFSET_ARRAY_SIZE, -1);
	}

	ret = DBpatch_add_dashboard(&dashboard);

	for (i = 0; SUCCEED == ret && i < screen_items.values_num; i++)
	{
		int			offset_idx_x, offset_idx_y;
		zbx_db_widget_t		w;
		zbx_vector_ptr_t	widget_fields;
		zbx_db_screen_item_t	*si;

		si = screen_items.values[i];

		offset_idx_x = si->x + si->colspan;
		if (offset_idx_x > OFFSET_ARRAY_SIZE - 1)
		{
			offset_idx_x = OFFSET_ARRAY_SIZE - 1;
			zabbix_log(LOG_LEVEL_WARNING, "config error, x screen size overflow for item " ZBX_FS_UI64,
					si->screenitemid);
		}

		offset_idx_y = si->y + si->rowspan;
		if (offset_idx_y > OFFSET_ARRAY_SIZE - 1)
		{
			offset_idx_y = OFFSET_ARRAY_SIZE - 1;
			zabbix_log(LOG_LEVEL_WARNING, "config error, y screen size overflow for item " ZBX_FS_UI64,
					si->screenitemid);
		}

		memset((void *)&w, 0, sizeof(zbx_db_widget_t));
		w.x = offsets_x[si->x];
		w.y = offsets_y[si->y];
		w.width = offsets_x[offset_idx_x] - offsets_x[si->x];
		w.height = offsets_y[offset_idx_y] - offsets_y[si->y];

		/* skip screen items not fitting on the dashboard */
		if (w.x + w.width > DASHBOARD_MAX_COLS || w.y + w.height > DASHBOARD_MAX_ROWS)
		{
			zabbix_log(LOG_LEVEL_WARNING, "skipping screenitemid " ZBX_FS_UI64
					" (too wide, tall or offscreen)", si->screenitemid);
			continue;
		}

		zbx_vector_ptr_create(&widget_fields);

		DBpatch_widget_from_screen_item(si, &w, &widget_fields);

		ret = DBpatch_add_widget(dashboard.dashboardid, &w, &widget_fields);

		DBpatch_trace_widget(&w);

		zbx_vector_ptr_clear_ext(&widget_fields, (zbx_clean_func_t)DBpatch_widget_field_free);
		zbx_vector_ptr_destroy(&widget_fields);
		zbx_free(w.name);
		zbx_free(w.type);
	}

	zbx_free(dashboard.name);

	if (screen_items.values_num > 0)
	{
		lw_array_free(dim_x);
		lw_array_free(dim_y);
	}

	zbx_vector_ptr_clear_ext(&screen_items, (zbx_clean_func_t)DBpatch_screen_item_free);
	zbx_vector_ptr_destroy(&screen_items);

	return ret;
}

#undef OFFSET_ARRAY_SIZE

static int	DBpatch_5010044(void)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		ret = SUCCEED;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return ret;

	result = DBselect("select screenid,name,templateid from screens where templateid is not null");

	while (SUCCEED == ret && NULL != (row = DBfetch(result)))
	{
		uint64_t	screenid, templateid;

		ZBX_DBROW2UINT64(screenid, row[0]);
		ZBX_DBROW2UINT64(templateid, row[2]);

		if (SUCCEED == (ret = DBpatch_convert_screen(screenid, row[1], templateid)))
			ret = DBpatch_delete_screen(screenid);
	}

	DBfree_result(result);

	return ret;
}

#undef DASHBOARD_MAX_COLS
#undef DASHBOARD_MAX_ROWS
#undef DASHBOARD_WIDGET_MIN_ROWS
#undef DASHBOARD_WIDGET_MAX_ROWS

#undef SCREEN_MAX_ROWS
#undef SCREEN_MAX_COLS
#undef SCREEN_RESOURCE_CLOCK
#undef SCREEN_RESOURCE_GRAPH
#undef SCREEN_RESOURCE_SIMPLE_GRAPH
#undef SCREEN_RESOURCE_LLD_GRAPH
#undef SCREEN_RESOURCE_LLD_SIMPLE_GRAPH
#undef SCREEN_RESOURCE_PLAIN_TEXT
#undef SCREEN_RESOURCE_URL

#undef ZBX_WIDGET_FIELD_TYPE_INT32
#undef ZBX_WIDGET_FIELD_TYPE_STR
#undef ZBX_WIDGET_FIELD_TYPE_ITEM
#undef ZBX_WIDGET_FIELD_TYPE_ITEM_PROTOTYPE
#undef ZBX_WIDGET_FIELD_TYPE_GRAPH
#undef ZBX_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE

/* #undef ZBX_WIDGET_FIELD_RESOURCE_GRAPH */
/* #undef ZBX_WIDGET_FIELD_RESOURCE_SIMPLE_GRAPH */
/* #undef ZBX_WIDGET_FIELD_RESOURCE_GRAPH_PROTOTYPE */
/* #undef ZBX_WIDGET_FIELD_RESOURCE_SIMPLE_GRAPH_PROTOTYPE */

#undef ZBX_WIDGET_TYPE_CLOCK
#undef ZBX_WIDGET_TYPE_GRAPH_CLASSIC
#undef ZBX_WIDGET_TYPE_GRAPH_PROTOTYPE
#undef ZBX_WIDGET_TYPE_PLAIN_TEXT
#undef ZBX_WIDGET_TYPE_URL
#undef POS_EMPTY
#undef POS_TAKEN
#undef SKIP_EMPTY

static int	DBpatch_5010045(void)
{
	return DBdrop_foreign_key("screens", 1);
}

static int	DBpatch_5010046(void)
{
	return DBdrop_field("screens", "templateid");
}

static int	DBpatch_5010047(void)
{
	return DBcreate_index("screens", "screens_1", "userid", 0);
}

static int	DBpatch_5010048(void)
{
#ifdef HAVE_MYSQL	/* fix automatic index name on MySQL */
	if (SUCCEED == DBindex_exists("screens", "c_screens_3"))
	{
		return DBdrop_index("screens", "c_screens_3");
	}
#endif
	return SUCCEED;
}

static int	DBpatch_5010049(void)
{
	const ZBX_TABLE	table =
			{"item_parameter", "item_parameterid", 0,
				{
					{"item_parameterid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"itemid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"name", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"value", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_5010050(void)
{
	return DBcreate_index("item_parameter", "item_parameter_1", "itemid", 0);
}

static int	DBpatch_5010051(void)
{
	const ZBX_FIELD	field = {"itemid", NULL, "items", "itemid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("item_parameter", 1, &field);
}

static int	DBpatch_5010052(void)
{
	return DBdrop_field("config", "refresh_unsupported");
}

static int      DBpatch_5010053(void)
{
	const ZBX_TABLE table =
		{"role", "roleid", 0,
			{
				{"roleid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
				{"name", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{"type", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
				{"readonly", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
				{0}
			},
			NULL
		};

	return DBcreate_table(&table);
}

static int	DBpatch_5010054(void)
{
	return DBcreate_index("role", "role_1", "name", 1);
}

static int	DBpatch_5010055(void)
{
	const ZBX_TABLE table =
		{"role_rule", "role_ruleid", 0,
			{
				{"role_ruleid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
				{"roleid", NULL, "role", "roleid", 0, ZBX_TYPE_ID, ZBX_NOTNULL, ZBX_FK_CASCADE_DELETE},
				{"type", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
				{"name", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{"value_int", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
				{"value_str", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{"value_moduleid", NULL, "module", "moduleid", 0, ZBX_TYPE_ID, 0, 0},
				{0}
			},
			NULL
		};

	return DBcreate_table(&table);
}

static int	DBpatch_5010056(void)
{
	return DBcreate_index("role_rule", "role_rule_1", "roleid", 0);
}

static int	DBpatch_5010057(void)
{
	return DBcreate_index("role_rule", "role_rule_2", "value_moduleid", 0);
}

static int	DBpatch_5010058(void)
{
	int		i;
	const char	*columns = "roleid,name,type,readonly";
	const char	*values[] = {
			"1,'User role',1,0",
			"2,'Admin role',2,0",
			"3,'Super admin role',3,1",
			"4,'Guest role',1,0",
			NULL
		};

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	for (i = 0; NULL != values[i]; i++)
	{
		if (ZBX_DB_OK > DBexecute("insert into role (%s) values (%s)", columns, values[i]))
			return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_5010059(void)
{
	int		i;
	const char	*columns = "role_ruleid,roleid,type,name,value_int,value_str,value_moduleid";
	const char	*values[] = {
			"1,1,0,'ui.default_access',1,'',NULL",
			"2,1,0,'modules.default_access',1,'',NULL",
			"3,1,0,'api.access',1,'',NULL",
			"4,1,0,'actions.default_access',1,'',NULL",
			"5,2,0,'ui.default_access',1,'',NULL",
			"6,2,0,'modules.default_access',1,'',NULL",
			"7,2,0,'api.access',1,'',NULL",
			"8,2,0,'actions.default_access',1,'',NULL",
			"9,3,0,'ui.default_access',1,'',NULL",
			"10,3,0,'modules.default_access',1,'',NULL",
			"11,3,0,'api.access',1,'',NULL",
			"12,3,0,'actions.default_access',1,'',NULL",
			"13,4,0,'ui.default_access',1,'',NULL",
			"14,4,0,'modules.default_access',1,'',NULL",
			"15,4,0,'api.access',0,'',NULL",
			"16,4,0,'actions.default_access',0,'',NULL",
			NULL
		};

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	for (i = 0; NULL != values[i]; i++)
	{
		if (ZBX_DB_OK > DBexecute("insert into role_rule (%s) values (%s)", columns, values[i]))
			return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_5010060(void)
{
	const ZBX_FIELD field = {"roleid", NULL, "role", "roleid", 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_field("users", &field);
}

static int	DBpatch_5010061(void)
{
	int	i;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	for (i = 1; i <= 3; i++)
	{
		if (ZBX_DB_OK > DBexecute("update users set roleid=%d where type=%d", i, i))
			return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_5010062(void)
{
	const ZBX_FIELD field = {"roleid", NULL, "role", "roleid", 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0};

	return DBset_not_null("users", &field);
}

static int	DBpatch_5010063(void)
{
	return DBdrop_field("users", "type");
}

static int	DBpatch_5010064(void)
{
	const ZBX_FIELD field = {"roleid", NULL, "role", "roleid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("users", 1, &field);
}

static int	DBpatch_5010065(void)
{
	const ZBX_FIELD field = {"roleid", NULL, "role", "roleid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("role_rule", 1, &field);
}

static int	DBpatch_5010066(void)
{
	const ZBX_FIELD field = {"value_moduleid", NULL, "module", "moduleid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("role_rule", 2, &field);
}

static int	DBpatch_5010067(void)
{
	int	i;

	/* 1 - USER TYPE / USER ROLE */
	/* 2 - ADMIN TYPE / ADMIN ROLE */
	/* 3 - SUPER ADMIN TYPE / SUPER ADMIN ROLE */
	const char	*values[] = {
			"1",
			"2",
			"3",
			NULL
		};

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	for (i = 0; NULL != values[i]; i++)
	{
		if (ZBX_DB_OK > DBexecute("update profiles set value_id=%s,type=1,value_int=0 "
				"where idx='web.user.filter_type' and value_int=%s", values[i], values[i]))
		{
			return FAIL;
		}
	}

	/* -1 - ANY PROFILE */
	if (ZBX_DB_OK > DBexecute("delete from profiles where idx='web.user.filter_type' and value_int=-1"))
		return FAIL;

	return SUCCEED;
}
#endif

DBPATCH_START(5010)

/* version, duplicates flag, mandatory flag */

DBPATCH_ADD(5010000, 0, 1)
DBPATCH_ADD(5010001, 0, 1)
DBPATCH_ADD(5010002, 0, 1)
DBPATCH_ADD(5010003, 0, 1)
DBPATCH_ADD(5010004, 0, 1)
DBPATCH_ADD(5010005, 0, 1)
DBPATCH_ADD(5010006, 0, 1)
DBPATCH_ADD(5010007, 0, 1)
DBPATCH_ADD(5010008, 0, 1)
DBPATCH_ADD(5010009, 0, 1)
DBPATCH_ADD(5010010, 0, 1)
DBPATCH_ADD(5010011, 0, 1)
DBPATCH_ADD(5010012, 0, 1)
DBPATCH_ADD(5010013, 0, 1)
DBPATCH_ADD(5010014, 0, 1)
DBPATCH_ADD(5010015, 0, 1)
DBPATCH_ADD(5010016, 0, 1)
DBPATCH_ADD(5010017, 0, 1)
DBPATCH_ADD(5010018, 0, 1)
DBPATCH_ADD(5010019, 0, 1)
DBPATCH_ADD(5010020, 0, 1)
DBPATCH_ADD(5010021, 0, 1)
DBPATCH_ADD(5010022, 0, 1)
DBPATCH_ADD(5010023, 0, 1)
DBPATCH_ADD(5010024, 0, 1)
DBPATCH_ADD(5010025, 0, 1)
DBPATCH_ADD(5010026, 0, 1)
DBPATCH_ADD(5010027, 0, 1)
DBPATCH_ADD(5010028, 0, 1)
DBPATCH_ADD(5010029, 0, 1)
DBPATCH_ADD(5010030, 0, 1)
DBPATCH_ADD(5010031, 0, 1)
DBPATCH_ADD(5010032, 0, 1)
DBPATCH_ADD(5010033, 0, 1)
DBPATCH_ADD(5010034, 0, 1)
DBPATCH_ADD(5010035, 0, 1)
DBPATCH_ADD(5010036, 0, 1)
DBPATCH_ADD(5010038, 0, 1)
DBPATCH_ADD(5010039, 0, 1)
DBPATCH_ADD(5010040, 0, 1)
DBPATCH_ADD(5010041, 0, 1)
DBPATCH_ADD(5010042, 0, 1)
DBPATCH_ADD(5010043, 0, 1)
DBPATCH_ADD(5010044, 0, 1)
DBPATCH_ADD(5010045, 0, 1)
DBPATCH_ADD(5010046, 0, 1)
DBPATCH_ADD(5010047, 0, 1)
DBPATCH_ADD(5010048, 0, 1)
DBPATCH_ADD(5010049, 0, 1)
DBPATCH_ADD(5010050, 0, 1)
DBPATCH_ADD(5010051, 0, 1)
DBPATCH_ADD(5010052, 0, 1)
DBPATCH_ADD(5010053, 0, 1)
DBPATCH_ADD(5010054, 0, 1)
DBPATCH_ADD(5010055, 0, 1)
DBPATCH_ADD(5010056, 0, 1)
DBPATCH_ADD(5010057, 0, 1)
DBPATCH_ADD(5010058, 0, 1)
DBPATCH_ADD(5010059, 0, 1)
DBPATCH_ADD(5010060, 0, 1)
DBPATCH_ADD(5010061, 0, 1)
DBPATCH_ADD(5010062, 0, 1)
DBPATCH_ADD(5010063, 0, 1)
DBPATCH_ADD(5010064, 0, 1)
DBPATCH_ADD(5010065, 0, 1)
DBPATCH_ADD(5010066, 0, 1)
DBPATCH_ADD(5010067, 0, 1)

DBPATCH_END()
