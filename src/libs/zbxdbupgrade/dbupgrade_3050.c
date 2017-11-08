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

	if (SUCCEED != DBadd_field("problem", &field))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_3050005(void)
{
	DB_RESULT	result;
	DB_ROW		row;
	char		*description;
	zbx_uint64_t	triggerid;
	int		res;
	char		*trdefault = "cannot calculate trigger expression";
	char		*itdefault = "cannot obtain item value";

	if (NULL == (result = DBselect("select triggerid,description from triggers")))
		return FAIL;

	while (NULL != (row = DBfetch(result)))
	{
		description = row[1];
		ZBX_STR2UINT64(triggerid, row[0]);

		res = DBexecute("update events set name='%s' where objectid=%d and source=%d", description,
				triggerid, EVENT_SOURCE_TRIGGERS);

		if (ZBX_DB_OK > res)
			return FAIL;

		res = DBexecute("update problem set name='%s' where objectid=%d and source=%d", description,
				triggerid, EVENT_SOURCE_TRIGGERS);

		if (ZBX_DB_OK > res)
			return FAIL;

		res = DBexecute("update events set name='%s' where objectid=%d and source=%d "
				"and value=%d", trdefault, triggerid, EVENT_SOURCE_INTERNAL,
				EVENT_STATUS_PROBLEM);

		if (ZBX_DB_OK > res)
			return FAIL;

		res = DBexecute("update problem set name='%s' where objectid=%d and source=%d ", trdefault,
				triggerid, EVENT_SOURCE_INTERNAL, EVENT_STATUS_PROBLEM);

		if (ZBX_DB_OK > res)
			return FAIL;
	}

	res = DBexecute("update events set name='%s' where source=%d and object=%d and value = %d", itdefault,
			EVENT_SOURCE_INTERNAL, EVENT_OBJECT_ITEM, EVENT_STATUS_PROBLEM);

	if (ZBX_DB_OK > res)
		return FAIL;

	res = DBexecute("update problem set name='%s' where source=%d and object=%d", itdefault,
			EVENT_SOURCE_INTERNAL, EVENT_OBJECT_ITEM);

	if (ZBX_DB_OK > res)
		return FAIL;

	return SUCCEED;
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

DBPATCH_END()
