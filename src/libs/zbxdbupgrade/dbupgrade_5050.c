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

#include "common.h"
#include "db.h"
#include "dbupgrade.h"

extern unsigned char	program_type;

/*
 * 6.0 development database patches
 */

#ifndef HAVE_SQLITE3

static int	DBpatch_5050000(void)
{
	return DBdrop_table("auditlog_details");
}

static int	DBpatch_5050001(void)
{
	return DBdrop_table("auditlog");
}

static int	DBpatch_5050002(void)
{
	const ZBX_TABLE table =
		{"auditlog", "auditid", 0,
			{
				{"auditid", NULL, NULL, NULL, 25, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{"userid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
				{"clock", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
				{"action", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
				{"ip", "", NULL, NULL, 39, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{"resourceid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
				{"resourcename", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{"resourcetype", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
				{"recordsetid", NULL, NULL, NULL, 25, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{"details", "", NULL, NULL, 0, ZBX_TYPE_SHORTTEXT, ZBX_NOTNULL, 0},
				{0}
			},
			NULL
		};

	return DBcreate_table(&table);
}

static int	DBpatch_5050003(void)
{
	return DBcreate_index("auditlog", "auditlog_1", "userid,clock", 0);
}

static int	DBpatch_5050004(void)
{
	return DBcreate_index("auditlog", "auditlog_2", "clock", 0);
}

static int	DBpatch_5050005(void)
{
	return DBcreate_index("auditlog", "auditlog_3", "resourcetype,resourceid", 0);
}

#endif

DBPATCH_START(5050)

/* version, duplicates flag, mandatory flag */

DBPATCH_ADD(5050000, 0, 1)
DBPATCH_ADD(5050001, 0, 1)
DBPATCH_ADD(5050002, 0, 1)
DBPATCH_ADD(5050003, 0, 1)
DBPATCH_ADD(5050004, 0, 1)
DBPATCH_ADD(5050005, 0, 1)

DBPATCH_END()
