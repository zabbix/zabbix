/*
** Copyright (C) 2001-2026 Zabbix SIA
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

#include "zbxdb.h"

/*
 * 8.2 development database patches
 */

#ifndef HAVE_SQLITE3

static int	DBpatch_8010000(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > zbx_db_execute("delete from role_rule where name='ui.administration.data_source'"))
		return FAIL;

	return SUCCEED;
}

#endif

DBPATCH_START(8010)

/* version, duplicates flag, mandatory flag */

DBPATCH_ADD(8010000, 0, 1)

DBPATCH_END()

