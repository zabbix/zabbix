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
/*
 * 3.2 maintenance database patches
 */

#ifndef HAVE_SQLITE3

static int	DBpatch_3020000(void)
{
	return SUCCEED;
}

static int	DBpatch_3020001(void)
{
	return delete_problems_with_nonexistent_object();
}

#endif

DBPATCH_START(3020)

/* version, duplicates flag, mandatory flag */

DBPATCH_ADD(3020000, 0, 1)
DBPATCH_ADD(3020001, 0, 0)

DBPATCH_END()
