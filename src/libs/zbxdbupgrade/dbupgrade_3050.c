/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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
 * 4.0 development database patches
 */

#ifndef HAVE_SQLITE3

static int	DBpatch_3050000(void)
{
	const ZBX_FIELD	field = {"name", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	if (SUCCEED != DBadd_field("events", &field))
		return FAIL;

	if (SUCCEED != DBadd_field("problem", &field))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_3050001(void)
{
	DB_RESULT	result;
	DB_ROW		row;
	char		*description;
	zbx_uint64_t	triggerid;
	int		res;
	char		*trdefault = "cannot calculate trigger expression";
	char		*itdefault = "cannot obtain item value";

	if (NULL == (result = DBselect("select triggerid,description from triggers")))
		return FAIL;

	while (NULL != (row = DBfetch(result)))
	{
		description = row[1];
		ZBX_STR2UINT64(triggerid, row[0]);

		res = DBexecute("update events set name='%s' where objectid=%d and source=%d", description,
				triggerid, EVENT_SOURCE_TRIGGERS);

		if (ZBX_DB_OK > res)
			return FAIL;

		res = DBexecute("update problem set name='%s' where objectid=%d and source=%d", description,
				triggerid, EVENT_SOURCE_TRIGGERS);

		if (ZBX_DB_OK > res)
			return FAIL;

		res = DBexecute("update events set name='%s' where objectid=%d and source=%d "
				"and value=%d", trdefault, triggerid, EVENT_SOURCE_INTERNAL,
				EVENT_STATUS_PROBLEM);

		if (ZBX_DB_OK > res)
			return FAIL;

		res = DBexecute("update problem set name='%s' where objectid=%d and source=%d ", trdefault,
				triggerid, EVENT_SOURCE_INTERNAL, EVENT_STATUS_PROBLEM);

		if (ZBX_DB_OK > res)
			return FAIL;
	}

	res = DBexecute("update events set name='%s' where source=%d and object=%d and value = %d", itdefault,
			EVENT_SOURCE_INTERNAL, EVENT_OBJECT_ITEM, EVENT_STATUS_PROBLEM);

	if (ZBX_DB_OK > res)
		return FAIL;

	res = DBexecute("update problem set name='%s' where source=%d and object=%d", itdefault,
			EVENT_SOURCE_INTERNAL, EVENT_OBJECT_ITEM);

	if (ZBX_DB_OK > res)
		return FAIL;

	return SUCCEED;
}

#endif

DBPATCH_START(3050)

/* version, duplicates flag, mandatory flag */

DBPATCH_ADD(3050000, 0, 1)
DBPATCH_ADD(3050001, 0, 1)

DBPATCH_END()
