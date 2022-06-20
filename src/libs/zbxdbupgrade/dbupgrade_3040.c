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

#include "dbupgrade.h"

#include "common.h"
#include "zbxdbhigh.h"

/*
 * 3.4 maintenance database patches
 */

#ifndef HAVE_SQLITE3

int	DBpatch_3040006(void);
int	DBpatch_3040007(void);

static int	DBpatch_3040000(void)
{
	return SUCCEED;
}

extern int	DBpatch_3020001(void);

static int	DBpatch_3040001(void)
{
	return DBpatch_3020001();
}

static int	DBpatch_3040002(void)
{
	return DBdrop_foreign_key("sessions", 1);
}

static int	DBpatch_3040003(void)
{
	return DBdrop_index("sessions", "sessions_1");
}

static int	DBpatch_3040004(void)
{
	return DBcreate_index("sessions", "sessions_1", "userid,status,lastaccess", 0);
}

static int	DBpatch_3040005(void)
{
	const ZBX_FIELD	field = {"userid", NULL, "users", "userid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("sessions", 1, &field);
}

int	DBpatch_3040006(void)
{
	if (FAIL == DBindex_exists("problem", "problem_3"))
		return DBcreate_index("problem", "problem_3", "r_eventid", 0);

	return SUCCEED;
}

int	DBpatch_3040007(void)
{
#ifdef HAVE_MYSQL	/* MySQL automatically creates index and might not remove it on some conditions */
	if (SUCCEED == DBindex_exists("problem", "c_problem_2"))
		return DBdrop_index("problem", "c_problem_2");
#endif
	return SUCCEED;
}

#endif

DBPATCH_START(3040)

/* version, duplicates flag, mandatory flag */

DBPATCH_ADD(3040000, 0, 1)
DBPATCH_ADD(3040001, 0, 0)
DBPATCH_ADD(3040002, 0, 0)
DBPATCH_ADD(3040003, 0, 0)
DBPATCH_ADD(3040004, 0, 0)
DBPATCH_ADD(3040005, 0, 0)
DBPATCH_ADD(3040006, 0, 0)
DBPATCH_ADD(3040007, 0, 0)

DBPATCH_END()
