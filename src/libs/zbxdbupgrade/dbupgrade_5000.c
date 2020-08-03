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

extern unsigned char	program_type;

/*
 * 5.0 maintenance database patches
 */

#ifndef HAVE_SQLITE3

static int	DBpatch_5000000(void)
{
	return SUCCEED;
}

static int DBpatch_5000001(void)
{
	const ZBX_FIELD	old_field = {"value_str", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};
	const ZBX_FIELD	field = {"value_str", "", NULL, NULL, 0, ZBX_TYPE_TEXT, ZBX_NOTNULL, 0};

	return DBmodify_field_type("profiles", &field, &old_field);
}

static int DBpatch_5000002(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("delete from profiles where idx like 'web.hostsmon.filter.%%'"))
		return FAIL;

	return SUCCEED;
}

#endif

DBPATCH_START(5000)

/* version, duplicates flag, mandatory flag */

DBPATCH_ADD(5000000, 0, 1)
DBPATCH_ADD(5000001, 0, 1)
DBPATCH_ADD(5000002, 0, 1)

DBPATCH_END()
