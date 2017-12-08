/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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

static int	DBpatch_3050002(void)
{
	const ZBX_FIELD field = {"colorpalette", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("graph_theme", &field);
}

static int	DBpatch_3050003(void)
{
	const char	*colorpalette = "1A7C11,F63100,2774A4,A54F10,FC6EA3,6C59DC,AC8C14,611F27,F230E0,5CCD18,BB2A02,"
			"5A2B57,89ABF8,7EC25C,274482,2B5429,8048B4,FD5434,790E1F,87AC4D,E89DF4";

	if (ZBX_DB_OK > DBexecute("update graph_theme set colorpalette='%s'", colorpalette))
		return FAIL;

	return SUCCEED;
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

static int	DBpatch_3050006(void)
{
	zbx_db_insert_t	db_insert;
	int		ret;

	zbx_db_insert_prepare(&db_insert, "task", "taskid", "type", "status", "clock", NULL);
	zbx_db_insert_add_values(&db_insert, __UINT64_C(0), ZBX_TM_TASK_UPDATE_EVENTNAMES, ZBX_TM_STATUS_NEW,
			time(NULL));
	zbx_db_insert_autoincrement(&db_insert, "taskid");
	ret = zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);

	return ret;
}

#define	ZBX_DEFAULT_INTERNAL_TRIGGER_EVENT_NAME	"Cannot calculate trigger expression."
#define	ZBX_DEFAULT_INTERNAL_ITEM_EVENT_NAME	"Cannot obtain item value."

static int	DBpatch_3050008(void)
{
	int		res;
	char		*trdefault = ZBX_DEFAULT_INTERNAL_TRIGGER_EVENT_NAME;

	res = DBexecute("update events set name='%s' where source=%d and object=%d and value=%d", trdefault,
			EVENT_SOURCE_INTERNAL, EVENT_OBJECT_TRIGGER, EVENT_STATUS_PROBLEM);

	if (ZBX_DB_OK > res)
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_3050009(void)
{
	int		res;
	char		*trdefault = ZBX_DEFAULT_INTERNAL_TRIGGER_EVENT_NAME;

	res = DBexecute("update problem set name='%s' where source=%d and object=%d ", trdefault,
			EVENT_SOURCE_INTERNAL, EVENT_OBJECT_TRIGGER);

	if (ZBX_DB_OK > res)
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_3050010(void)
{
	int		res;
	char		*itdefault = ZBX_DEFAULT_INTERNAL_ITEM_EVENT_NAME;

	res = DBexecute("update events set name='%s' where source=%d and object=%d and value=%d", itdefault,
			EVENT_SOURCE_INTERNAL, EVENT_OBJECT_ITEM, EVENT_STATUS_PROBLEM);

	if (ZBX_DB_OK > res)
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_3050011(void)
{
	int		res;
	char		*itdefault = ZBX_DEFAULT_INTERNAL_ITEM_EVENT_NAME;

	res = DBexecute("update problem set name='%s' where source=%d and object=%d", itdefault,
			EVENT_SOURCE_INTERNAL, EVENT_OBJECT_ITEM);

	if (ZBX_DB_OK > res)
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_3050012(void)
{
	int		res;

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
#endif

DBPATCH_START(3050)

/* version, duplicates flag, mandatory flag */

DBPATCH_ADD(3050000, 0, 1)
DBPATCH_ADD(3050001, 0, 1)
DBPATCH_ADD(3050002, 0, 1)
DBPATCH_ADD(3050003, 0, 1)
DBPATCH_ADD(3050004, 0, 1)
DBPATCH_ADD(3050005, 0, 1)
DBPATCH_ADD(3050006, 0, 1)
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

DBPATCH_END()
