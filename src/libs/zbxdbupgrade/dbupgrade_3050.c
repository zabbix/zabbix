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
#include "zbxtasks.h"
#include "zbxregexp.h"
#include "log.h"

extern unsigned char	program_type;

/*
 * 4.0 development database patches
 */

#ifndef HAVE_SQLITE3

static int	DBpatch_3050000(void)
{
	const ZBX_FIELD	field = {"proxy_address", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("hosts", &field);
}

static int	DBpatch_3050001(void)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		ret = FAIL;

	/* type : 'problem' - WIDGET_PROBLEMS */
	result = DBselect(
			"select wf.widgetid,wf.name"
			" from widget w,widget_field wf"
			" where w.widgetid=wf.widgetid"
				" and w.type='problems'"
				" and wf.name like 'tags.tag.%%'");

	while (NULL != (row = DBfetch(result)))
	{
		const char	*p;
		int		index;
		zbx_uint64_t	widget_fieldid;

		if (NULL == (p = strrchr(row[1], '.')) || SUCCEED != is_uint31(p + 1, &index))
			continue;

		widget_fieldid = DBget_maxid_num("widget_field", 1);

		/* type      : 0 - ZBX_WIDGET_FIELD_TYPE_INT32 */
		/* value_int : 0 - TAG_OPERATOR_LIKE */
		if (ZBX_DB_OK > DBexecute(
				"insert into widget_field (widget_fieldid,widgetid,type,name,value_int)"
				"values (" ZBX_FS_UI64 ",%s,0,'tags.operator.%d',0)", widget_fieldid, row[0], index)) {
			goto clean;
		}
	}

	ret = SUCCEED;
clean:
	DBfree_result(result);

	return ret;
}

static int	DBpatch_3050004(void)
{
	const ZBX_FIELD	field = {"name", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	if (SUCCEED != DBadd_field("events", &field))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_3050005(void)
{
	const ZBX_FIELD	field = {"name", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	if (SUCCEED != DBadd_field("problem", &field))
		return FAIL;

	return SUCCEED;
}

#define	ZBX_DEFAULT_INTERNAL_TRIGGER_EVENT_NAME	"Cannot calculate trigger expression."
#define	ZBX_DEFAULT_INTERNAL_ITEM_EVENT_NAME	"Cannot obtain item value."

static int	DBpatch_3050008(void)
{
	int		res;
	char		*trdefault = (char *)ZBX_DEFAULT_INTERNAL_TRIGGER_EVENT_NAME;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	res = DBexecute("update events set name='%s' where source=%d and object=%d and value=%d", trdefault,
			EVENT_SOURCE_INTERNAL, EVENT_OBJECT_TRIGGER, EVENT_STATUS_PROBLEM);

	if (ZBX_DB_OK > res)
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_3050009(void)
{
	int		res;
	char		*trdefault = (char *)ZBX_DEFAULT_INTERNAL_TRIGGER_EVENT_NAME;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	res = DBexecute("update problem set name='%s' where source=%d and object=%d ", trdefault,
			EVENT_SOURCE_INTERNAL, EVENT_OBJECT_TRIGGER);

	if (ZBX_DB_OK > res)
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_3050010(void)
{
	int		res;
	char		*itdefault = (char *)ZBX_DEFAULT_INTERNAL_ITEM_EVENT_NAME;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	res = DBexecute("update events set name='%s' where source=%d and object=%d and value=%d", itdefault,
			EVENT_SOURCE_INTERNAL, EVENT_OBJECT_ITEM, EVENT_STATUS_PROBLEM);

	if (ZBX_DB_OK > res)
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_3050011(void)
{
	int		res;
	char		*itdefault = (char *)ZBX_DEFAULT_INTERNAL_ITEM_EVENT_NAME;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	res = DBexecute("update problem set name='%s' where source=%d and object=%d", itdefault,
			EVENT_SOURCE_INTERNAL, EVENT_OBJECT_ITEM);

	if (ZBX_DB_OK > res)
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_3050012(void)
{
	int		res;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	res = DBexecute("update profiles set idx='web.problem.filter.name' where idx='web.problem.filter.problem'");

	if (ZBX_DB_OK > res)
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_3050013(void)
{
	const ZBX_FIELD	field = {"dns", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("interface", &field, NULL);
}

static int	DBpatch_3050014(void)
{
	const ZBX_FIELD	field = {"dns", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("proxy_dhistory", &field, NULL);
}

static int	DBpatch_3050015(void)
{
	const ZBX_FIELD	field = {"listen_dns", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("autoreg_host", &field, NULL);
}

static int	DBpatch_3050016(void)
{
	const ZBX_FIELD	field = {"listen_dns", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("proxy_autoreg_host", &field, NULL);
}

static int	DBpatch_3050017(void)
{
	const ZBX_FIELD	field = {"dns", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("dservices", &field, NULL);
}

static int	DBpatch_3050018(void)
{
	return DBdrop_table("graph_theme");
}

static int	DBpatch_3050019(void)
{
	const ZBX_TABLE table =
		{"graph_theme",	"graphthemeid",	0,
			{
				{"graphthemeid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
				{"theme", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{"backgroundcolor", "", NULL, NULL, 6, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{"graphcolor", "", NULL, NULL, 6, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{"gridcolor", "", NULL, NULL, 6, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{"maingridcolor", "", NULL, NULL, 6, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{"gridbordercolor", "", NULL, NULL, 6, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{"textcolor", "", NULL, NULL, 6, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{"highlightcolor", "", NULL, NULL, 6, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{"leftpercentilecolor", "", NULL, NULL, 6, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{"rightpercentilecolor", "", NULL, NULL, 6, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{"nonworktimecolor", "", NULL, NULL, 6, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{"colorpalette", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{0}
			},
			NULL
		};

	return DBcreate_table(&table);
}

static int	DBpatch_3050020(void)
{
	return DBcreate_index("graph_theme", "graph_theme_1", "theme", 1);
}

#define ZBX_COLORPALETTE_LIGHT	"1A7C11,F63100,2774A4,A54F10,FC6EA3,6C59DC,AC8C14,611F27,F230E0,5CCD18,BB2A02,"	\
				"5A2B57,89ABF8,7EC25C,274482,2B5429,8048B4,FD5434,790E1F,87AC4D,E89DF4"
#define ZBX_COLORPALETTE_DARK	"199C0D,F63100,2774A4,F7941D,FC6EA3,6C59DC,C7A72D,BA2A5D,F230E0,5CCD18,BB2A02,"	\
				"AC41A5,89ABF8,7EC25C,3165D5,79A277,AA73DE,FD5434,F21C3E,87AC4D,E89DF4"

static int	DBpatch_3050021(void)
{
	if (0 == (ZBX_PROGRAM_TYPE_SERVER & program_type))
		return SUCCEED;

	if (ZBX_DB_OK <= DBexecute(
			"insert into graph_theme"
			" values (1,'blue-theme','FFFFFF','FFFFFF','CCD5D9','ACBBC2','ACBBC2','1F2C33','E33734',"
				"'429E47','E33734','EBEBEB','" ZBX_COLORPALETTE_LIGHT "')"))
	{
		return SUCCEED;
	}

	return FAIL;
}

static int	DBpatch_3050022(void)
{
	if (0 == (ZBX_PROGRAM_TYPE_SERVER & program_type))
		return SUCCEED;

	if (ZBX_DB_OK <= DBexecute(
			"insert into graph_theme"
			" values (2,'dark-theme','2B2B2B','2B2B2B','454545','4F4F4F','4F4F4F','F2F2F2','E45959',"
				"'59DB8F','E45959','333333','" ZBX_COLORPALETTE_DARK "')"))
	{
		return SUCCEED;
	}

	return FAIL;
}

static int	DBpatch_3050023(void)
{
	if (0 == (ZBX_PROGRAM_TYPE_SERVER & program_type))
		return SUCCEED;

	if (ZBX_DB_OK <= DBexecute(
			"insert into graph_theme"
			" values (3,'hc-light','FFFFFF','FFFFFF','555555','000000','333333','000000','333333',"
				"'000000','000000','EBEBEB','" ZBX_COLORPALETTE_LIGHT "')"))
	{
		return SUCCEED;
	}

	return FAIL;
}

static int	DBpatch_3050024(void)
{
	if (0 == (ZBX_PROGRAM_TYPE_SERVER & program_type))
		return SUCCEED;

	if (ZBX_DB_OK <= DBexecute(
			"insert into graph_theme"
			" values (4,'hc-dark','000000','000000','666666','888888','4F4F4F','FFFFFF','FFFFFF',"
				"'FFFFFF','FFFFFF','333333','" ZBX_COLORPALETTE_DARK "')"))
	{
		return SUCCEED;
	}

	return FAIL;
}

#undef ZBX_COLORPALETTE_LIGHT
#undef ZBX_COLORPALETTE_DARK

static int	DBpatch_3050025(void)
{
	zbx_db_insert_t	db_insert;
	int		ret;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	zbx_db_insert_prepare(&db_insert, "task", "taskid", "type", "status", "clock", NULL);
	zbx_db_insert_add_values(&db_insert, __UINT64_C(0), ZBX_TM_TASK_UPDATE_EVENTNAMES, ZBX_TM_STATUS_NEW,
			time(NULL));
	zbx_db_insert_autoincrement(&db_insert, "taskid");
	ret = zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);

	return ret;
}

static int	DBpatch_3050026(void)
{
	int	res;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	res = DBexecute("update profiles set value_str='name' where idx='web.problem.sort' and value_str='problem'");

	if (ZBX_DB_OK > res)
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_3050027(void)
{
	const ZBX_FIELD	field = {"sendto", "", NULL, NULL, 1024, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("media", &field, NULL);
}

static int	DBpatch_3050028(void)
{
	const ZBX_FIELD	field = {"sendto", "", NULL, NULL, 1024, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("alerts", &field, NULL);
}

extern int	DBpatch_3040006(void);

static int	DBpatch_3050029(void)
{
	return DBpatch_3040006();
}

static int	DBpatch_3050030(void)
{
	const ZBX_FIELD	field = {"custom_color", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_3050031(void)
{
	const ZBX_FIELD	field = {"problem_unack_color", "CC0000", NULL, NULL, 6, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBset_default("config", &field);
}

static int	DBpatch_3050032(void)
{
	const ZBX_FIELD	field = {"problem_ack_color", "CC0000", NULL, NULL, 6, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBset_default("config", &field);
}

static int	DBpatch_3050033(void)
{
	const ZBX_FIELD	field = {"ok_unack_color", "009900", NULL, NULL, 6, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBset_default("config", &field);
}

static int	DBpatch_3050034(void)
{
	const ZBX_FIELD	field = {"ok_ack_color", "009900", NULL, NULL, 6, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBset_default("config", &field);
}

static int	DBpatch_3050035(void)
{
	int	res;

	res = DBexecute(
		"update config"
		" set custom_color=1"
		" where problem_unack_color<>'DC0000'"
			" or problem_ack_color<>'DC0000'"
			" or ok_unack_color<>'00AA00'"
			" or ok_ack_color<>'00AA00'");

	if (ZBX_DB_OK > res)
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_3050036(void)
{
	int	res;

	res = DBexecute(
		"update config"
		" set problem_unack_color='CC0000',"
			"problem_ack_color='CC0000',"
			"ok_unack_color='009900',"
			"ok_ack_color='009900'"
		" where problem_unack_color='DC0000'"
			" and problem_ack_color='DC0000'"
			" and ok_unack_color='00AA00'"
			" and ok_ack_color='00AA00'");

	if (ZBX_DB_OK > res)
		return FAIL;

	return SUCCEED;
}

extern int	DBpatch_3040007(void);

static int	DBpatch_3050037(void)
{
	return DBpatch_3040007();
}

static int	DBpatch_3050038(void)
{
	const ZBX_TABLE table =
			{"tag_filter", "tag_filterid", 0,
				{
					{"tag_filterid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"usrgrpid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"groupid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"tag", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"value", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_3050039(void)
{
	const ZBX_FIELD	field = {"usrgrpid", NULL, "usrgrp", "usrgrpid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("tag_filter", 1, &field);
}

static int	DBpatch_3050040(void)
{
	const ZBX_FIELD	field = {"groupid", NULL, "groups", "groupid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("tag_filter", 2, &field);
}

static int	DBpatch_3050041(void)
{
	const ZBX_TABLE table =
			{"task_check_now", "taskid", 0,
				{
					{"taskid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"itemid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_3050042(void)
{
	const ZBX_FIELD	field = {"taskid", NULL, "task", "taskid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("task_check_now", 1, &field);
}

static int	DBpatch_3050043(void)
{
	const char	*sql =
		"update widget_field"
		" set value_int=3"
		" where name='show_tags'"
			" and exists ("
				"select null"
				" from widget w"
				" where widget_field.widgetid=w.widgetid"
					" and w.type='problems'"
			")";

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK <= DBexecute("%s", sql))
		return SUCCEED;

	return FAIL;
}

static int	DBpatch_3050044(void)
{
	const char	*sql =
		"delete from profiles"
		" where idx in ('web.paging.lastpage','web.menu.view.last') and value_str='tr_status.php'"
			" or idx like 'web.tr_status%'";

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK <= DBexecute("%s", sql))
		return SUCCEED;

	return FAIL;
}

static int	DBpatch_3050045(void)
{
	const char	*sql = "update users set url='zabbix.php?action=problem.view' where url like '%tr_status.php%'";

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK <= DBexecute("%s", sql))
		return SUCCEED;

	return FAIL;
}

static int	DBpatch_3050046(void)
{
	const ZBX_FIELD field = {"timeout", "3s", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL | ZBX_PROXY, 0};

	return DBadd_field("items", &field);
}

static int	DBpatch_3050047(void)
{
	const ZBX_FIELD field = {"url", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL | ZBX_PROXY, 0};

	return DBadd_field("items", &field);
}

static int	DBpatch_3050048(void)
{
	const ZBX_FIELD field = {"query_fields", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL | ZBX_PROXY, 0};

	return DBadd_field("items", &field);
}

static int	DBpatch_3050049(void)
{
	const ZBX_FIELD	field = {"posts", "", NULL, NULL, 0, ZBX_TYPE_SHORTTEXT, ZBX_NOTNULL | ZBX_PROXY, 0};

	return DBadd_field("items", &field);
}

static int	DBpatch_3050050(void)
{
	const ZBX_FIELD field = {"status_codes", "200", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL | ZBX_PROXY, 0};

	return DBadd_field("items", &field);
}

static int	DBpatch_3050051(void)
{
	const ZBX_FIELD field = {"follow_redirects", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL | ZBX_PROXY, 0};

	return DBadd_field("items", &field);
}

static int	DBpatch_3050052(void)
{
	const ZBX_FIELD field = {"post_type", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL | ZBX_PROXY, 0};

	return DBadd_field("items", &field);
}

static int	DBpatch_3050053(void)
{
	const ZBX_FIELD field = {"http_proxy", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL | ZBX_PROXY, 0};

	return DBadd_field("items", &field);
}

static int	DBpatch_3050054(void)
{
	const ZBX_FIELD	field = {"headers", "", NULL, NULL, 0, ZBX_TYPE_SHORTTEXT, ZBX_NOTNULL | ZBX_PROXY, 0};

	return DBadd_field("items", &field);
}

static int	DBpatch_3050055(void)
{
	const ZBX_FIELD field = {"retrieve_mode", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL | ZBX_PROXY, 0};

	return DBadd_field("items", &field);
}

static int	DBpatch_3050056(void)
{
	const ZBX_FIELD field = {"request_method", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL | ZBX_PROXY, 0};

	return DBadd_field("items", &field);
}

static int	DBpatch_3050057(void)
{
	const ZBX_FIELD field = {"output_format", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL | ZBX_PROXY, 0};

	return DBadd_field("items", &field);
}

static int	DBpatch_3050058(void)
{
	const ZBX_FIELD field = {"ssl_cert_file", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL | ZBX_PROXY, 0};

	return DBadd_field("items", &field);
}

static int	DBpatch_3050059(void)
{
	const ZBX_FIELD field = {"ssl_key_file", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL | ZBX_PROXY, 0};

	return DBadd_field("items", &field);
}

static int	DBpatch_3050060(void)
{
	const ZBX_FIELD field = {"ssl_key_password", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL | ZBX_PROXY, 0};

	return DBadd_field("items", &field);
}

static int	DBpatch_3050061(void)
{
	const ZBX_FIELD field = {"verify_peer", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL | ZBX_PROXY, 0};

	return DBadd_field("items", &field);
}

static int	DBpatch_3050062(void)
{
	const ZBX_FIELD field = {"verify_host", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL | ZBX_PROXY, 0};

	return DBadd_field("items", &field);
}

static int	DBpatch_3050063(void)
{
	const ZBX_FIELD field = {"allow_traps", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL | ZBX_PROXY, 0};

	return DBadd_field("items", &field);
}

static int	DBpatch_3050064(void)
{
	const ZBX_FIELD	field = {"auto_compress", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("hosts", &field);
}

static int	DBpatch_3050065(void)
{
	int	ret;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	/* 5 - HOST_STATUS_PROXY_ACTIVE, 6 - HOST_STATUS_PROXY_PASSIVE */
	ret = DBexecute("update hosts set auto_compress=0 where status=5 or status=6");

	if (ZBX_DB_OK > ret)
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_3050066(void)
{
	int		i;
	const char      *types[] = {
			"actlog", "actionlog",
			"dscvry", "discovery",
			"favgrph", "favgraphs",
			"favmap", "favmaps",
			"favscr", "favscreens",
			"hoststat", "problemhosts",
			"navigationtree", "navtree",
			"stszbx", "systeminfo",
			"sysmap", "map",
			"syssum", "problemsbysv",
			"webovr", "web",
			NULL
		};

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	for (i = 0; NULL != types[i]; i += 2)
	{
		if (ZBX_DB_OK > DBexecute("update widget set type='%s' where type='%s'", types[i + 1], types[i]))
			return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_3050067(void)
{
	return DBdrop_field("config", "event_expire");
}

static int	DBpatch_3050068(void)
{
	return DBdrop_field("config", "event_show_max");
}

static int	DBpatch_3050069(void)
{
	int	res;

	res = DBexecute(
		"update widget_field"
		" set name='itemids'"
		" where name='itemid'"
			" and exists ("
				"select null"
				" from widget w"
				" where widget_field.widgetid=w.widgetid"
					" and w.type='plaintext'"
			")");

	if (ZBX_DB_OK > res)
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_3050070(void)
{
	return SUCCEED;
}

static int	DBpatch_3050071(void)
{
	return SUCCEED;
}

static int	DBpatch_3050072(void)
{
	return SUCCEED;
}

static int	DBpatch_3050073(void)
{
	return SUCCEED;
}

static int	DBpatch_3050074(void)
{
	return SUCCEED;
}

static int	DBpatch_3050075(void)
{
	return SUCCEED;
}

static int	DBpatch_3050076(void)
{
	return SUCCEED;
}

static int	DBpatch_3050077(void)
{
	return SUCCEED;
}

static int	DBpatch_3050078(void)
{
	return SUCCEED;
}

static int	DBpatch_3050079(void)
{
	return SUCCEED;
}

static int	DBpatch_3050080(void)
{
	return SUCCEED;
}

static int	DBpatch_3050081(void)
{
	return SUCCEED;
}

/* groups is reserved keyword since MySQL 8.0 */

static int	DBpatch_3050082(void)
{
	return DBrename_table("groups", "hstgrp");
}

static int	DBpatch_3050083(void)
{
	return DBrename_index("hstgrp", "groups_1", "hstgrp_1", "name", 0);
}

static int	DBpatch_3050084(void)
{
	return SUCCEED;
}

static int	DBpatch_3050085(void)
{
	return SUCCEED;
}

static int	DBpatch_3050086(void)
{
	return SUCCEED;
}

static int	DBpatch_3050087(void)
{
	return SUCCEED;
}

static int	DBpatch_3050088(void)
{
	return SUCCEED;
}

static int	DBpatch_3050089(void)
{
	return SUCCEED;
}

static int	DBpatch_3050090(void)
{
	return SUCCEED;
}

static int	DBpatch_3050091(void)
{
	return SUCCEED;
}

static int	DBpatch_3050092(void)
{
	return SUCCEED;
}

static int	DBpatch_3050093(void)
{
	return SUCCEED;
}

static int	DBpatch_3050094(void)
{
	return SUCCEED;
}

static int	DBpatch_3050095(void)
{
	return SUCCEED;
}

static int	DBpatch_3050096(void)
{
	return SUCCEED;
}

static int	DBpatch_3050097(void)
{
	return SUCCEED;
}

/* function is reserved keyword since MySQL 8.0 */

static int	DBpatch_3050098(void)
{
	const ZBX_FIELD	field = {"name", "", NULL, NULL, 12, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBrename_field("functions", "function", &field);
}

static int	DBpatch_3050099(void)
{
	return SUCCEED;
}

static int	DBpatch_3050100(void)
{
	return SUCCEED;
}

static int	DBpatch_3050101(void)
{
#ifdef HAVE_POSTGRESQL
	if (FAIL == DBindex_exists("hstgrp", "groups_pkey"))
		return SUCCEED;
	return DBrename_index("hstgrp", "groups_pkey", "hstgrp_pkey", "groupid", 0);
#else
	return SUCCEED;
#endif
}

static int	DBpatch_3050102(void)
{
	DB_RESULT		result;
	DB_ROW			row;
	int			ret = SUCCEED;
	zbx_vector_uint64_t	ids;

	zbx_vector_uint64_create(&ids);

	result = DBselect(
			"select a.autoreg_hostid,a.proxy_hostid,h.proxy_hostid"
			" from autoreg_host a"
			" left join hosts h"
				" on h.host=a.host");

	while (NULL != (row = DBfetch(result)))
	{
		zbx_uint64_t	autoreg_proxy_hostid, host_proxy_hostid;

		ZBX_DBROW2UINT64(autoreg_proxy_hostid, row[1]);
		ZBX_DBROW2UINT64(host_proxy_hostid, row[2]);

		if (autoreg_proxy_hostid != host_proxy_hostid)
		{
			zbx_uint64_t	id;

			ZBX_STR2UINT64(id, row[0]);
			zbx_vector_uint64_append(&ids, id);
		}
	}
	DBfree_result(result);

	if (0 != ids.values_num)
	{
		char	*sql = NULL;
		size_t	sql_alloc = 0, sql_offset = 0;

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "delete from autoreg_host where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "autoreg_hostid", ids.values, ids.values_num);

		if (ZBX_DB_OK > DBexecute("%s", sql))
			ret = FAIL;

		zbx_free(sql);
	}

	zbx_vector_uint64_destroy(&ids);

	return ret;
}

static int	DBpatch_3050103(void)
{
	return DBcreate_index("autoreg_host", "autoreg_host_2", "proxy_hostid", 0);
}

static int	DBpatch_3050104(void)
{
	return DBdrop_index("autoreg_host", "autoreg_host_1");
}

static int	DBpatch_3050105(void)
{
	return DBcreate_index("autoreg_host", "autoreg_host_1", "host", 0);
}

static int	DBpatch_3050106(void)
{
	int	res;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	res = DBexecute("update profiles set value_int=2 where idx='web.problem.filter.evaltype' and value_int=1");

	if (ZBX_DB_OK > res)
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_3050107(void)
{
	int	res;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	res = DBexecute(
		"update widget_field"
		" set value_int=2"
		" where name='evaltype'"
			" and value_int=1"
			" and exists ("
				"select null"
				" from widget w"
				" where widget_field.widgetid=w.widgetid"
					" and w.type='problems'"
			")");

	if (ZBX_DB_OK > res)
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_3050108(void)
{
	int	res;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	res = DBexecute(
		"delete from profiles"
		" where idx like '%%.filter.state'"
			" or idx like '%%.timelinefixed'"
			" or idx like '%%.period'"
			" or idx like '%%.stime'"
			" or idx like '%%.isnow'"
	);

	if (ZBX_DB_OK > res)
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_3050109(void)
{
	const ZBX_FIELD	field = {"ok_period", "5m", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBset_default("config", &field);
}

static int	DBpatch_3050110(void)
{
	const ZBX_FIELD	field = {"blink_period", "2m", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBset_default("config", &field);
}

static int	DBpatch_3050111(void)
{
	const ZBX_FIELD	field = {"severity", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("events", &field);
}

static int	DBpatch_3050112(void)
{
	const ZBX_FIELD	field = {"acknowledged", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("problem", &field);
}

static int	DBpatch_3050113(void)
{
	const ZBX_FIELD	field = {"severity", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("problem", &field);
}

static int	DBpatch_3050114(void)
{
	const ZBX_FIELD	field = {"old_severity", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("acknowledges", &field);
}

static int	DBpatch_3050115(void)
{
	const ZBX_FIELD	field = {"new_severity", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("acknowledges", &field);
}

static int	DBpatch_3050116(void)
{
	return DBdrop_field("config", "event_ack_enable");
}

static int	DBpatch_3050117(void)
{
	int	ret;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	ret = DBexecute("update problem set acknowledged="
			"(select acknowledged from events where events.eventid=problem.eventid)");

	if (ZBX_DB_OK > ret)
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_3050118(void)
{
	DB_ROW		row;
	DB_RESULT	result;
	int		ret = SUCCEED;
	char		*sql = NULL;
	size_t		sql_alloc = 0, sql_offset = 0;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	result = DBselect(
			"select e.eventid,t.priority"
			" from events e"
			" inner join triggers t"
				" on e.objectid=t.triggerid"
			" where e.source=0"
				" and e.object=0"
				" and e.value=1"
			);

	while (NULL != (row = DBfetch(result)))
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update events set severity=%s where eventid=%s;\n",
				row[1], row[0]);

		if (SUCCEED != (ret = DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset)))
			goto out;
	}
	DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (16 < sql_offset && ZBX_DB_OK > DBexecute("%s", sql))
		ret = FAIL;
out:
	DBfree_result(result);
	zbx_free(sql);

	return ret;
}

static int	DBpatch_3050119(void)
{
	DB_ROW		row;
	DB_RESULT	result;
	int		ret = SUCCEED;
	char		*sql = NULL;
	size_t		sql_alloc = 0, sql_offset = 0;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	result = DBselect(
			"select p.eventid,t.priority"
			" from problem p"
			" inner join triggers t"
				" on p.objectid=t.triggerid"
			" where p.source=0"
				" and p.object=0"
			);

	while (NULL != (row = DBfetch(result)))
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update problem set severity=%s where eventid=%s;\n",
				row[1], row[0]);

		if (SUCCEED != (ret = DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset)))
			goto out;
	}

	DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (16 < sql_offset && ZBX_DB_OK > DBexecute("%s", sql))
		ret = FAIL;
out:
	DBfree_result(result);
	zbx_free(sql);

	return ret;
}

static int	DBpatch_3050120(void)
{
	int		ret = SUCCEED, action;
	zbx_uint64_t	ackid, eventid;
	zbx_hashset_t	eventids;
	DB_RESULT	result;
	DB_ROW		row;
	char		*sql;
	size_t		sql_alloc = 4096, sql_offset = 0;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	sql = zbx_malloc(NULL, sql_alloc);
	zbx_hashset_create(&eventids, 1000, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	result = DBselect("select acknowledgeid,eventid,action from acknowledges order by clock");
	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(ackid, row[0]);
		ZBX_STR2UINT64(eventid, row[1]);
		action = atoi(row[2]);

		/* 0x04 - ZBX_ACKNOWLEDGE_ACTION_COMMENT */
		action |= 0x04;

		if (NULL == zbx_hashset_search(&eventids, &eventid))
		{
			zbx_hashset_insert(&eventids, &eventid, sizeof(eventid));
			/* 0x02 - ZBX_ACKNOWLEDGE_ACTION_ACKNOWLEDGE */
			action |= 0x02;
		}

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"update acknowledges set action=%d where acknowledgeid=" ZBX_FS_UI64 ";\n",
				action, ackid);

		if (SUCCEED != (ret = DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset)))
			goto out;
	}

	DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (16 < sql_offset && ZBX_DB_OK > DBexecute("%s", sql))
		ret = FAIL;
out:
	zbx_hashset_destroy(&eventids);
	DBfree_result(result);
	zbx_free(sql);

	return ret;
}

static int	DBpatch_3050121(void)
{
	int	res;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	res = DBexecute(
		"update profiles set value_str='severity' where idx='web.problem.sort' and value_str='priority'");

	if (ZBX_DB_OK > res)
		return FAIL;

	return SUCCEED;
}

static void	DBpatch_3050122_add_anchors(const char *src, char *dst, size_t src_len)
{
	*dst++ = '^';				/* start anchor */

	if (0 != src_len)
	{
		memcpy(dst, src, src_len);	/* parameter body */
		dst += src_len;
	}

	*dst++ = '$';				/* end anchor */
	*dst = '\0';
}

static int	DBpatch_3050122(void)
{
	DB_ROW		row;
	DB_RESULT	result;
	int		ret = FAIL;
	char		*sql = NULL;
	size_t		sql_alloc = 0, sql_offset = 0;

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	result = DBselect("select functionid,parameter from functions where name='logsource'");

	while (NULL != (row = DBfetch(result)))
	{
		const char	*orig_param = row[1];
		char		*processed_parameter = NULL, *unquoted_parameter, *parameter_anchored = NULL,
				*db_parameter_esc;
		size_t		param_pos, param_len, sep_pos, param_alloc = 0, param_offset = 0, current_len;
		int		was_quoted;

		zbx_function_param_parse(orig_param, &param_pos, &param_len, &sep_pos);

		/* copy leading whitespace (if any) or empty string */
		zbx_strncpy_alloc(&processed_parameter, &param_alloc, &param_offset, orig_param, param_pos);

		unquoted_parameter = zbx_function_param_unquote_dyn(orig_param + param_pos, param_len, &was_quoted);

		zbx_regexp_escape(&unquoted_parameter);

		current_len = strlen(unquoted_parameter);

		/* increasing length by 3 for ^, $, '\0' */
		parameter_anchored = (char *)zbx_malloc(NULL, current_len + 3);
		DBpatch_3050122_add_anchors(unquoted_parameter, parameter_anchored, current_len);
		zbx_free(unquoted_parameter);

		if (SUCCEED != zbx_function_param_quote(&parameter_anchored, was_quoted))
		{
			zabbix_log(LOG_LEVEL_WARNING, "Cannot convert parameter \"%s\" of trigger function"
					" logsource (functionid: %s) to regexp during database upgrade. The"
					" parameter needs to but cannot be quoted after conversion.",
					row[1], row[0]);

			zbx_free(parameter_anchored);
			zbx_free(processed_parameter);
			continue;
		}

		/* copy the parameter */
		zbx_strcpy_alloc(&processed_parameter, &param_alloc, &param_offset, parameter_anchored);
		zbx_free(parameter_anchored);

		/* copy trailing whitespace (if any) or empty string */
		zbx_strncpy_alloc(&processed_parameter, &param_alloc, &param_offset, orig_param + param_pos + param_len,
				sep_pos - param_pos - param_len + 1);

		if (FUNCTION_PARAM_LEN < (current_len = zbx_strlen_utf8(processed_parameter)))
		{
			zabbix_log(LOG_LEVEL_WARNING, "Cannot convert parameter \"%s\" of trigger function logsource"
					" (functionid: %s) to regexp during database upgrade. The converted"
					" value is too long for field \"parameter\" - " ZBX_FS_SIZE_T " characters."
					" Allowed length is %d characters.",
					row[1], row[0], (zbx_fs_size_t)current_len, FUNCTION_PARAM_LEN);

			zbx_free(processed_parameter);
			continue;
		}

		db_parameter_esc = DBdyn_escape_string_len(processed_parameter, FUNCTION_PARAM_LEN);
		zbx_free(processed_parameter);

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"update functions set parameter='%s' where functionid=%s;\n",
				db_parameter_esc, row[0]);

		zbx_free(db_parameter_esc);

		if (SUCCEED != DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset))
			goto out;
	}

	DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (16 < sql_offset)
	{
		if (ZBX_DB_OK > DBexecute("%s", sql))
			goto out;
	}

	ret = SUCCEED;
out:
	DBfree_result(result);
	zbx_free(sql);

	return ret;
}

static int	DBpatch_3050123(void)
{
	int	res;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	res = DBexecute(
		"delete from profiles where idx in ("
			"'web.toptriggers.filter.from','web.toptriggers.filter.till','web.avail_report.0.timesince',"
			"'web.avail_report.0.timetill','web.avail_report.1.timesince','web.avail_report.1.timetill'"
		")");

	if (ZBX_DB_OK > res)
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_3050124(void)
{
	const ZBX_FIELD	field = {"request_method", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBset_default("items", &field);
}

static int	DBpatch_3050125(void)
{
	return DBcreate_index("problem_tag", "problem_tag_3", "eventid,tag,value", 0);
}

static int	DBpatch_3050126(void)
{
	return DBdrop_index("problem_tag", "problem_tag_1");
}

static int	DBpatch_3050127(void)
{
	return DBdrop_index("problem_tag", "problem_tag_2");
}

static int	DBpatch_3050128(void)
{
	return DBrename_index("problem_tag", "problem_tag_3", "problem_tag_1", "eventid,tag,value", 0);
}

static int	DBpatch_3050129(void)
{
	const ZBX_TABLE table =
		{"event_suppress", "event_suppressid",	0,
			{
				{"event_suppressid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
				{"eventid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
				{"maintenanceid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0},
				{"suppress_until", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
				{0}
			},
			NULL
		};

	return DBcreate_table(&table);
}

static int	DBpatch_3050130(void)
{
	return DBcreate_index("event_suppress", "event_suppress_1", "eventid,maintenanceid", 1);
}

static int	DBpatch_3050131(void)
{
	return DBcreate_index("event_suppress", "event_suppress_2", "suppress_until", 0);
}

static int	DBpatch_3050132(void)
{
	return DBcreate_index("event_suppress", "event_suppress_3", "maintenanceid", 0);
}

static int	DBpatch_3050133(void)
{
	const ZBX_FIELD	field = {"eventid", NULL, "events", "eventid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("event_suppress", 1, &field);
}

static int	DBpatch_3050134(void)
{
	const ZBX_FIELD	field = {"maintenanceid", NULL, "maintenances", "maintenanceid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("event_suppress", 2, &field);
}

static int	DBpatch_3050135(void)
{
	const ZBX_TABLE table =
		{"maintenance_tag", "maintenancetagid", 0,
			{
				{"maintenancetagid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
				{"maintenanceid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
				{"tag", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{"operator", "2", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
				{"value", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{0}
			},
			NULL
		};

	return DBcreate_table(&table);
}

static int	DBpatch_3050136(void)
{
	return DBcreate_index("maintenance_tag", "maintenance_tag_1", "maintenanceid", 0);
}

static int	DBpatch_3050137(void)
{
	const ZBX_FIELD	field = {"maintenanceid", NULL, "maintenances", "maintenanceid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("maintenance_tag", 1, &field);
}

static int	DBpatch_3050138(void)
{
	const ZBX_FIELD	field = {"show_suppressed", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("sysmaps", &field);
}

static int	DBpatch_3050139(void)
{
	const ZBX_FIELD	field = {"tags_evaltype", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("maintenances", &field);
}

static int	DBpatch_3050140(void)
{
	const ZBX_FIELD	field = {"pause_suppressed", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBrename_field("actions", "maintenance_mode", &field);
}

static int	DBpatch_3050141(void)
{
	int		ret;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	ret = DBexecute("update profiles"
			" set idx='web.problem.filter.show_suppressed'"
			" where idx='web.problem.filter.maintenance'");

	if (ZBX_DB_OK > ret)
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_3050142(void)
{
	int		ret;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	ret = DBexecute("update profiles"
			" set idx='web.overview.filter.show_suppressed'"
			" where idx='web.overview.filter.show_maintenance'");

	if (ZBX_DB_OK > ret)
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_3050143(void)
{
	int	ret;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	ret = DBexecute("update widget_field"
			" set name='show_suppressed'"
			" where name='maintenance'"
				" and exists (select null"
					" from widget"
					" where widget.widgetid=widget_field.widgetid"
						" and widget.type in ('problems','problemhosts','problemsbysv'))");

	if (ZBX_DB_OK > ret)
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_3050144(void)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		ret = FAIL;
	zbx_db_insert_t	db_insert;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	zbx_db_insert_prepare(&db_insert, "widget_field", "widget_fieldid", "widgetid", "type", "name", "value_int",
			NULL);

	/* type : 'problem' - WIDGET_PROBLEMS */
	result = DBselect("select w.widgetid"
			" from widget w"
			" where w.type in ('problems','problemhosts','problemsbysv')"
				" and not exists (select null"
					" from widget_field wf"
					" where w.widgetid=wf.widgetid"
						" and wf.name='show_suppressed')");

	while (NULL != (row = DBfetch(result)))
	{
		zbx_uint64_t	widgetid;

		ZBX_STR2UINT64(widgetid, row[0]);
		zbx_db_insert_add_values(&db_insert, __UINT64_C(0), widgetid, 0, "show_suppressed", 1);
	}
	DBfree_result(result);

	zbx_db_insert_autoincrement(&db_insert, "widget_fieldid");
	ret = zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);

	return ret;
}

static int	DBpatch_3050145(void)
{
	int	ret;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	/* CONDITION_OPERATOR_IN (4) -> CONDITION_OPERATOR_YES (10) */
	/* for conditiontype CONDITION_TYPE_SUPPRESSED (16)         */
	ret = DBexecute("update conditions"
			" set operator=10"
			" where conditiontype=16"
				" and operator=4");

	if (ZBX_DB_OK > ret)
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_3050146(void)
{
	int	ret;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	/* CONDITION_OPERATOR_NOT_IN (7) -> CONDITION_OPERATOR_NO (11) */
	/* for conditiontype CONDITION_TYPE_SUPPRESSED (16)            */
	ret = DBexecute("update conditions"
			" set operator=11"
			" where conditiontype=16"
				" and operator=7");

	if (ZBX_DB_OK > ret)
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_3050147(void)
{
	const ZBX_FIELD	field = {"http_auth_enabled", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_3050148(void)
{
	const ZBX_FIELD	field = {"http_login_form", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_3050149(void)
{
	const ZBX_FIELD	field = {"http_strip_domains", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_3050150(void)
{
	const ZBX_FIELD	field = {"http_case_sensitive", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_3050151(void)
{
	const ZBX_FIELD	field = {"ldap_configured", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_3050152(void)
{
	const ZBX_FIELD	field = {"ldap_case_sensitive", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_3050153(void)
{
	int	res;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	/* Change ZBX_AUTH_HTTP to ZBX_AUTH_INTERNAL and enable HTTP_AUTH option. */
	res = DBexecute("update config set authentication_type=0,http_auth_enabled=1 where authentication_type=2");

	if (ZBX_DB_OK > res)
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_3050154(void)
{
	int	res;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	/* New GUI access type is added GROUP_GUI_ACCESS_LDAP, update value of GROUP_GUI_ACCESS_DISABLED. */
	/* 2 - old value of GROUP_GUI_ACCESS_DISABLED */
	/* 3 - new value of GROUP_GUI_ACCESS_DISABLED */
	res = DBexecute("update usrgrp set gui_access=3 where gui_access=2");

	if (ZBX_DB_OK > res)
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_3050155(void)
{
	int	res;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	/* Update ldap_configured to ZBX_AUTH_LDAP_ENABLED for config with default authentication type ZBX_AUTH_LDAP. */
	/* Update ldap_case_sensitive to ZBX_AUTH_CASE_SENSITIVE. */
	res = DBexecute("update config set ldap_configured=1,ldap_case_sensitive=1 where authentication_type=1");

	if (ZBX_DB_OK > res)
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_3050156(void)
{
	int	res;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	res = DBexecute(
		"delete from widget_field"
		" where (name like 'ds.order.%%' or name like 'or.order.%%')"
			" and exists ("
				"select null"
				" from widget w"
				" where widget_field.widgetid=w.widgetid"
					" and w.type='svggraph'"
			")");

	if (ZBX_DB_OK > res)
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_3050157(void)
{
	const ZBX_FIELD	field = {"passwd", "", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("users", &field, NULL);
}

static int	DBpatch_3050158(void)
{
	int res;

	res = DBexecute("update users set passwd=rtrim(passwd)");

	if (ZBX_DB_OK > res)
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_3050159(void)
{
	return DBcreate_index("escalations", "escalations_2", "eventid", 0);
}

static int	DBpatch_3050160(void)
{
	return DBdrop_index("escalations", "escalations_1");
}

static int	DBpatch_3050161(void)
{
	return DBcreate_index("escalations", "escalations_1", "triggerid,itemid,escalationid", 1);
}

static int	DBpatch_3050162(void)
{
	return DBcreate_index("escalations", "escalations_3", "nextcheck", 0);
}

#endif

DBPATCH_START(3050)

/* version, duplicates flag, mandatory flag */

DBPATCH_ADD(3050000, 0, 1)
DBPATCH_ADD(3050001, 0, 1)
DBPATCH_ADD(3050004, 0, 1)
DBPATCH_ADD(3050005, 0, 1)
DBPATCH_ADD(3050008, 0, 1)
DBPATCH_ADD(3050009, 0, 1)
DBPATCH_ADD(3050010, 0, 1)
DBPATCH_ADD(3050011, 0, 1)
DBPATCH_ADD(3050012, 0, 1)
DBPATCH_ADD(3050013, 0, 1)
DBPATCH_ADD(3050014, 0, 1)
DBPATCH_ADD(3050015, 0, 1)
DBPATCH_ADD(3050016, 0, 1)
DBPATCH_ADD(3050017, 0, 1)
DBPATCH_ADD(3050018, 0, 1)
DBPATCH_ADD(3050019, 0, 1)
DBPATCH_ADD(3050020, 0, 1)
DBPATCH_ADD(3050021, 0, 1)
DBPATCH_ADD(3050022, 0, 1)
DBPATCH_ADD(3050023, 0, 1)
DBPATCH_ADD(3050024, 0, 1)
DBPATCH_ADD(3050025, 0, 1)
DBPATCH_ADD(3050026, 0, 1)
DBPATCH_ADD(3050027, 0, 1)
DBPATCH_ADD(3050028, 0, 1)
DBPATCH_ADD(3050029, 0, 0)
DBPATCH_ADD(3050030, 0, 1)
DBPATCH_ADD(3050031, 0, 1)
DBPATCH_ADD(3050032, 0, 1)
DBPATCH_ADD(3050033, 0, 1)
DBPATCH_ADD(3050034, 0, 1)
DBPATCH_ADD(3050035, 0, 1)
DBPATCH_ADD(3050036, 0, 1)
DBPATCH_ADD(3050037, 0, 1)
DBPATCH_ADD(3050038, 0, 1)
DBPATCH_ADD(3050039, 0, 1)
DBPATCH_ADD(3050040, 0, 1)
DBPATCH_ADD(3050041, 0, 1)
DBPATCH_ADD(3050042, 0, 1)
DBPATCH_ADD(3050043, 0, 1)
DBPATCH_ADD(3050044, 0, 1)
DBPATCH_ADD(3050045, 0, 1)
DBPATCH_ADD(3050046, 0, 1)
DBPATCH_ADD(3050047, 0, 1)
DBPATCH_ADD(3050048, 0, 1)
DBPATCH_ADD(3050049, 0, 1)
DBPATCH_ADD(3050050, 0, 1)
DBPATCH_ADD(3050051, 0, 1)
DBPATCH_ADD(3050052, 0, 1)
DBPATCH_ADD(3050053, 0, 1)
DBPATCH_ADD(3050054, 0, 1)
DBPATCH_ADD(3050055, 0, 1)
DBPATCH_ADD(3050056, 0, 1)
DBPATCH_ADD(3050057, 0, 1)
DBPATCH_ADD(3050058, 0, 1)
DBPATCH_ADD(3050059, 0, 1)
DBPATCH_ADD(3050060, 0, 1)
DBPATCH_ADD(3050061, 0, 1)
DBPATCH_ADD(3050062, 0, 1)
DBPATCH_ADD(3050063, 0, 1)
DBPATCH_ADD(3050064, 0, 1)
DBPATCH_ADD(3050065, 0, 1)
DBPATCH_ADD(3050066, 0, 1)
DBPATCH_ADD(3050067, 0, 1)
DBPATCH_ADD(3050068, 0, 1)
DBPATCH_ADD(3050069, 0, 1)
DBPATCH_ADD(3050070, 0, 1)
DBPATCH_ADD(3050071, 0, 1)
DBPATCH_ADD(3050072, 0, 1)
DBPATCH_ADD(3050073, 0, 1)
DBPATCH_ADD(3050074, 0, 1)
DBPATCH_ADD(3050075, 0, 1)
DBPATCH_ADD(3050076, 0, 1)
DBPATCH_ADD(3050077, 0, 1)
DBPATCH_ADD(3050078, 0, 1)
DBPATCH_ADD(3050079, 0, 1)
DBPATCH_ADD(3050080, 0, 1)
DBPATCH_ADD(3050081, 0, 1)
DBPATCH_ADD(3050082, 0, 1)
DBPATCH_ADD(3050083, 0, 1)
DBPATCH_ADD(3050084, 0, 1)
DBPATCH_ADD(3050085, 0, 1)
DBPATCH_ADD(3050086, 0, 1)
DBPATCH_ADD(3050087, 0, 1)
DBPATCH_ADD(3050088, 0, 1)
DBPATCH_ADD(3050089, 0, 1)
DBPATCH_ADD(3050090, 0, 1)
DBPATCH_ADD(3050091, 0, 1)
DBPATCH_ADD(3050092, 0, 1)
DBPATCH_ADD(3050093, 0, 1)
DBPATCH_ADD(3050094, 0, 1)
DBPATCH_ADD(3050095, 0, 1)
DBPATCH_ADD(3050096, 0, 1)
DBPATCH_ADD(3050097, 0, 1)
DBPATCH_ADD(3050098, 0, 1)
DBPATCH_ADD(3050099, 0, 1)
DBPATCH_ADD(3050100, 0, 1)
DBPATCH_ADD(3050101, 0, 1)
DBPATCH_ADD(3050102, 0, 1)
DBPATCH_ADD(3050103, 0, 1)
DBPATCH_ADD(3050104, 0, 1)
DBPATCH_ADD(3050105, 0, 1)
DBPATCH_ADD(3050106, 0, 1)
DBPATCH_ADD(3050107, 0, 1)
DBPATCH_ADD(3050108, 0, 1)
DBPATCH_ADD(3050109, 0, 1)
DBPATCH_ADD(3050110, 0, 1)
DBPATCH_ADD(3050111, 0, 1)
DBPATCH_ADD(3050112, 0, 1)
DBPATCH_ADD(3050113, 0, 1)
DBPATCH_ADD(3050114, 0, 1)
DBPATCH_ADD(3050115, 0, 1)
DBPATCH_ADD(3050116, 0, 1)
DBPATCH_ADD(3050117, 0, 1)
DBPATCH_ADD(3050118, 0, 1)
DBPATCH_ADD(3050119, 0, 1)
DBPATCH_ADD(3050120, 0, 1)
DBPATCH_ADD(3050121, 0, 1)
DBPATCH_ADD(3050122, 0, 1)
DBPATCH_ADD(3050123, 0, 1)
DBPATCH_ADD(3050124, 0, 1)
DBPATCH_ADD(3050125, 0, 1)
DBPATCH_ADD(3050126, 0, 1)
DBPATCH_ADD(3050127, 0, 1)
DBPATCH_ADD(3050128, 0, 1)
DBPATCH_ADD(3050129, 0, 1)
DBPATCH_ADD(3050130, 0, 1)
DBPATCH_ADD(3050131, 0, 1)
DBPATCH_ADD(3050132, 0, 1)
DBPATCH_ADD(3050133, 0, 1)
DBPATCH_ADD(3050134, 0, 1)
DBPATCH_ADD(3050135, 0, 1)
DBPATCH_ADD(3050136, 0, 1)
DBPATCH_ADD(3050137, 0, 1)
DBPATCH_ADD(3050138, 0, 1)
DBPATCH_ADD(3050139, 0, 1)
DBPATCH_ADD(3050140, 0, 1)
DBPATCH_ADD(3050141, 0, 1)
DBPATCH_ADD(3050142, 0, 1)
DBPATCH_ADD(3050143, 0, 1)
DBPATCH_ADD(3050144, 0, 1)
DBPATCH_ADD(3050145, 0, 1)
DBPATCH_ADD(3050146, 0, 1)
DBPATCH_ADD(3050147, 0, 1)
DBPATCH_ADD(3050148, 0, 1)
DBPATCH_ADD(3050149, 0, 1)
DBPATCH_ADD(3050150, 0, 1)
DBPATCH_ADD(3050151, 0, 1)
DBPATCH_ADD(3050152, 0, 1)
DBPATCH_ADD(3050153, 0, 1)
DBPATCH_ADD(3050154, 0, 1)
DBPATCH_ADD(3050155, 0, 1)
DBPATCH_ADD(3050156, 0, 1)
DBPATCH_ADD(3050157, 0, 1)
DBPATCH_ADD(3050158, 0, 1)
DBPATCH_ADD(3050159, 0, 1)
DBPATCH_ADD(3050160, 0, 1)
DBPATCH_ADD(3050161, 0, 1)
DBPATCH_ADD(3050162, 0, 1)

DBPATCH_END()
