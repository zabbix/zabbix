/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

#include "dbupgrade.h"
#include "dbupgrade_common.h"

#include "zbxdbschema.h"

/*
 * 3.4 maintenance database patches
 */

#ifndef HAVE_SQLITE3

static int	DBpatch_3040000(void)
{
	return SUCCEED;
}

static int	DBpatch_3040001(void)
{
	return delete_problems_with_nonexistent_object();
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
	const zbx_db_field_t	field = {"userid", NULL, "users", "userid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("sessions", 1, &field);
}

static int	DBpatch_3040006(void)
{
	return create_problem_3_index();
}

static int	DBpatch_3040007(void)
{
	return drop_c_problem_2_index();
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
