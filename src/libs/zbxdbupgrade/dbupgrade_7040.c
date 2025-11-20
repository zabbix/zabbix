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

#include "zbxdb.h"

/*
 * 7.4 maintenance database patches
 */

#ifndef HAVE_SQLITE3

static int	DBpatch_7040000(void)
{
	return SUCCEED;
}

static int	DBpatch_7040001(void)
{
	/* 3 - HOST_STATUS_TEMPLATE */
	if (ZBX_DB_OK > zbx_db_execute("delete from item_rtdata"
			" where exists ("
				"select null from items i,hosts h"
				" where item_rtdata.itemid=i.itemid"
					" and i.hostid=h.hostid and h.status=3"
				")"))
	{
		return FAIL;
	}

	return SUCCEED;
}

#endif

DBPATCH_START(7040)

/* version, duplicates flag, mandatory flag */

DBPATCH_ADD(7040000, 0, 1)
DBPATCH_ADD(7040001, 0, 0)

DBPATCH_END()
