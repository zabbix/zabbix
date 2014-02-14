/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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
#include "log.h"
#include "sysinfo.h"
#include "zbxdbupgrade.h"
#include "dbupgrade.h"

/*
 * 2.4 development database patches
 */

#ifndef HAVE_SQLITE3

static int	DBpatch_2030000(void)
{
	return SUCCEED;
}

static int	DBpatch_2030001(void)
{
	const ZBX_FIELD	field = {"every", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBset_default("timeperiods", &field);
}

static int	DBpatch_2030002(void)
{
	const ZBX_TABLE table =
			{"trigger_discovery_tmp", "", 0,
				{
					{"triggerid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"parent_triggerid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_2030003(void)
{
	if (ZBX_DB_OK <= DBexecute(
			"insert into trigger_discovery_tmp (select triggerid,parent_triggerid from trigger_discovery)"))
	{
		return SUCCEED;
	}

	return FAIL;
}

static int	DBpatch_2030004(void)
{
	return DBdrop_table("trigger_discovery");
}

static int	DBpatch_2030005(void)
{
	const ZBX_TABLE table =
			{"trigger_discovery", "triggerid", 0,
				{
					{"triggerid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"parent_triggerid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_2030006(void)
{
	return DBcreate_index("trigger_discovery", "trigger_discovery_1", "parent_triggerid", 0);
}

static int	DBpatch_2030007(void)
{
	const ZBX_FIELD	field = {"triggerid", NULL, "triggers", "triggerid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("trigger_discovery", 1, &field);
}

static int	DBpatch_2030008(void)
{
	const ZBX_FIELD	field = {"parent_triggerid", NULL, "triggers", "triggerid", 0, 0, 0, 0};

	return DBadd_foreign_key("trigger_discovery", 2, &field);
}

static int	DBpatch_2030009(void)
{
	if (ZBX_DB_OK <= DBexecute(
			"insert into trigger_discovery (select triggerid,parent_triggerid from trigger_discovery_tmp)"))
	{
		return SUCCEED;
	}

	return FAIL;
}

static int	DBpatch_2030010(void)
{
	return DBdrop_table("trigger_discovery_tmp");
}

static int	DBpatch_2030011(void)
{
	const ZBX_FIELD field = {"application", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("sysmaps_elements", &field);
}

static int	DBpatch_2030012(void)
{
	const ZBX_TABLE table =
			{"graph_discovery_tmp", "", 0,
				{
					{"graphid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"parent_graphid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_2030013(void)
{
	if (ZBX_DB_OK <= DBexecute(
			"insert into graph_discovery_tmp (select graphid,parent_graphid from graph_discovery)"))
	{
		return SUCCEED;
	}

	return FAIL;
}

static int	DBpatch_2030014(void)
{
	return DBdrop_table("graph_discovery");
}

static int	DBpatch_2030015(void)
{
	const ZBX_TABLE table =
			{"graph_discovery", "graphid", 0,
				{
					{"graphid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"parent_graphid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_2030016(void)
{
	return DBcreate_index("graph_discovery", "graph_discovery_1", "parent_graphid", 0);
}

static int	DBpatch_2030017(void)
{
	const ZBX_FIELD	field = {"graphid", NULL, "graphs", "graphid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("graph_discovery", 1, &field);
}

static int	DBpatch_2030018(void)
{
	const ZBX_FIELD	field = {"parent_graphid", NULL, "graphs", "graphid", 0, 0, 0, 0};

	return DBadd_foreign_key("graph_discovery", 2, &field);
}

static int	DBpatch_2030019(void)
{
	if (ZBX_DB_OK <= DBexecute(
			"insert into graph_discovery (select graphid,parent_graphid from graph_discovery_tmp)"))
	{
		return SUCCEED;
	}

	return FAIL;
}

static int	DBpatch_2030020(void)
{
	return DBdrop_table("graph_discovery_tmp");
}

#endif

DBPATCH_START(2030)

/* version, duplicates flag, mandatory flag */

DBPATCH_ADD(2030000, 0, 1)
DBPATCH_ADD(2030001, 0, 1)
DBPATCH_ADD(2030002, 0, 1)
DBPATCH_ADD(2030003, 0, 1)
DBPATCH_ADD(2030004, 0, 1)
DBPATCH_ADD(2030005, 0, 1)
DBPATCH_ADD(2030006, 0, 1)
DBPATCH_ADD(2030007, 0, 1)
DBPATCH_ADD(2030008, 0, 1)
DBPATCH_ADD(2030009, 0, 1)
DBPATCH_ADD(2030010, 0, 1)
DBPATCH_ADD(2030011, 2020001, 1)
DBPATCH_ADD(2030012, 0, 1)
DBPATCH_ADD(2030013, 0, 1)
DBPATCH_ADD(2030014, 0, 1)
DBPATCH_ADD(2030015, 0, 1)
DBPATCH_ADD(2030016, 0, 1)
DBPATCH_ADD(2030017, 0, 1)
DBPATCH_ADD(2030018, 0, 1)
DBPATCH_ADD(2030019, 0, 1)
DBPATCH_ADD(2030020, 0, 1)

DBPATCH_END()
