/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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
 * 4.4 development database patches
 */

#ifndef HAVE_SQLITE3

static int	DBpatch_4030000(void)
{
	const ZBX_FIELD	field = {"host", "", NULL, NULL, 128, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("autoreg_host", &field, NULL);
}

static int	DBpatch_4030001(void)
{
	const ZBX_FIELD	field = {"host", "", NULL, NULL, 128, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("proxy_autoreg_host", &field, NULL);
}

static int	DBpatch_4030002(void)
{
	const ZBX_FIELD	field = {"host", "", NULL, NULL, 128, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("host_discovery", &field, NULL);
}

static int	DBpatch_4030003(void)
{
	int	res;

    res = DBexecute("update widget set x=x*2, width=width*2;");

    if (ZBX_DB_OK > res)
        return FAIL;

    return SUCCEED;
}

#endif

DBPATCH_START(4030)

/* version, duplicates flag, mandatory flag */

DBPATCH_ADD(4030000, 0, 1)
DBPATCH_ADD(4030001, 0, 1)
DBPATCH_ADD(4030002, 0, 1)
DBPATCH_ADD(4030003, 0, 1)

DBPATCH_END()
