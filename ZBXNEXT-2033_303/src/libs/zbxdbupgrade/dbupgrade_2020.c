/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
 * 2.2 maintenance database patches
 */

#ifndef HAVE_SQLITE3

static int	DBpatch_2020000(void)
{
	return SUCCEED;
}


static int	DBpatch_2020002(void)
{
	const ZBX_TABLE	table = {"ticket", "ticketid", 0,
		{
			{"ticketid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
			{"externalid", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
			{"eventid", NULL, "events", "eventid", 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
			{"triggerid", NULL, "triggers", "triggerid", 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
			{"clock", NULL, NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
			{"new", NULL, NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
			{NULL}
		},
		NULL
	};

	return DBcreate_table(&table);
}

static int	DBpatch_2020003(void)
{
	return DBcreate_index("ticket", "ticket_1", "eventid", 0);
}

static int	DBpatch_2020004(void)
{
	return DBcreate_index("ticket", "ticket_2", "triggerid,clock", 0);
}

static int	DBpatch_2020005(void)
{
	return DBcreate_index("ticket", "ticket_3", "externalid,new", 0);
}

static int	DBpatch_2020006(void)
{
	const ZBX_FIELD field = {"externalid", NULL, NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBset_default("ticket", &field);
}

static int	DBpatch_2020007(void)
{
	return DBdrop_index("ticket", "ticket_1");
}

static int	DBpatch_2020008(void)
{
	return DBcreate_index("ticket", "ticket_1", "eventid,clock", 0);
}

#endif

DBPATCH_START(2020)


/* version, duplicates flag, mandatory flag */

DBPATCH_ADD(2020000, 0, 1)
DBPATCH_ADD(2020002, 0, 1)
DBPATCH_ADD(2020003, 0, 1)
DBPATCH_ADD(2020004, 0, 1)
DBPATCH_ADD(2020005, 0, 1)
DBPATCH_ADD(2020006, 0, 1)
DBPATCH_ADD(2020007, 0, 1)
DBPATCH_ADD(2020008, 0, 1)


DBPATCH_END()
