/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
 * 5.4 development database patches
 */

#ifndef HAVE_SQLITE3

extern unsigned char	program_type;

static int	DBpatch_5030000(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("delete from profiles where idx='web.queue.config'"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_5030001(void)
{
	const ZBX_FIELD	field = {"display_period", "30", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("dashboard", &field);
}

static int	DBpatch_5030002(void)
{
	const ZBX_FIELD	field = {"auto_start", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("dashboard", &field);
}

static int	DBpatch_5030003(void)
{
	const ZBX_TABLE	table =
			{"dashboard_page", "dashboard_pageid", 0,
				{
					{"dashboard_pageid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"dashboardid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"name", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"display_period", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"sortorder", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_5030004(void)
{
	const ZBX_FIELD field = {"dashboardid", NULL, "dashboard", "dashboardid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("dashboard_page", 1, &field);
}

static int	DBpatch_5030005(void)
{
	return DBcreate_index("dashboard_page", "dashboard_page_1", "dashboardid", 0);
}

static int	DBpatch_5030006(void)
{
	if (0 == (ZBX_PROGRAM_TYPE_SERVER & program_type))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute(
			"insert into dashboard_page (dashboard_pageid,dashboardid)"
			" select dashboardid,dashboardid from dashboard"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_5030007(void)
{
	if (0 == (ZBX_PROGRAM_TYPE_SERVER & program_type))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute(
			"insert into ids select 'dashboard_page','dashboardpage_id',nextid from ids"
			" where table_name='dashboard' and field_name='dashboardid'"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_5030008(void)
{
	const ZBX_FIELD	field = {"dashboard_pageid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0};

	if (SUCCEED != DBadd_field("widget", &field))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_5030009(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("update widget set dashboard_pageid=dashboardid"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_5030010(void)
{
	const ZBX_FIELD	field = {"dashboard_pageid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0};

	return DBset_not_null("widget", &field);
}

static int	DBpatch_5030011(void)
{
	return DBdrop_foreign_key("widget", 1);
}

static int	DBpatch_5030012(void)
{
	return DBdrop_field("widget", "dashboardid");
}

static int	DBpatch_5030013(void)
{
	return DBcreate_index("widget", "widget_1", "dashboard_pageid", 0);
}

static int	DBpatch_5030014(void)
{
	const ZBX_FIELD field = {"dashboard_pageid", NULL, "dashboard_page", "dashboard_pageid", 0, 0, 0,
			ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("widget", 1, &field);
}
#endif

DBPATCH_START(5030)

/* version, duplicates flag, mandatory flag */

DBPATCH_ADD(5030000, 0, 1)
DBPATCH_ADD(5030001, 0, 1)
DBPATCH_ADD(5030002, 0, 1)
DBPATCH_ADD(5030003, 0, 1)
DBPATCH_ADD(5030004, 0, 1)
DBPATCH_ADD(5030005, 0, 1)
DBPATCH_ADD(5030006, 0, 1)
DBPATCH_ADD(5030007, 0, 1)
DBPATCH_ADD(5030008, 0, 1)
DBPATCH_ADD(5030009, 0, 1)
DBPATCH_ADD(5030010, 0, 1)
DBPATCH_ADD(5030011, 0, 1)
DBPATCH_ADD(5030012, 0, 1)
DBPATCH_ADD(5030013, 0, 1)
DBPATCH_ADD(5030014, 0, 1)

DBPATCH_END()
