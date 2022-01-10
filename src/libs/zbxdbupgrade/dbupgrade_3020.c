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

#include "common.h"
#include "db.h"
#include "dbupgrade.h"

/*
 * 3.2 maintenance database patches
 */

#ifndef HAVE_SQLITE3

int	DBpatch_3020001(void);

static int	DBpatch_3020000(void)
{
	return SUCCEED;
}

int	DBpatch_3020001(void)
{
	DB_RESULT		result;
	zbx_vector_uint64_t	eventids;
	DB_ROW			row;
	zbx_uint64_t		eventid;
	int			sources[] = {EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_INTERNAL};
	int			objects[] = {EVENT_OBJECT_ITEM, EVENT_OBJECT_LLDRULE}, i;

	zbx_vector_uint64_create(&eventids);

	for (i = 0; i < (int)ARRSIZE(sources); i++)
	{
		result = DBselect(
				"select p.eventid"
				" from problem p"
				" where p.source=%d and p.object=%d and not exists ("
					"select null"
					" from triggers t"
					" where t.triggerid=p.objectid"
				")",
				sources[i], EVENT_OBJECT_TRIGGER);

		while (NULL != (row = DBfetch(result)))
		{
			ZBX_STR2UINT64(eventid, row[0]);
			zbx_vector_uint64_append(&eventids, eventid);
		}
		DBfree_result(result);
	}

	for (i = 0; i < (int)ARRSIZE(objects); i++)
	{
		result = DBselect(
				"select p.eventid"
				" from problem p"
				" where p.source=%d and p.object=%d and not exists ("
					"select null"
					" from items i"
					" where i.itemid=p.objectid"
				")",
				EVENT_SOURCE_INTERNAL, objects[i]);

		while (NULL != (row = DBfetch(result)))
		{
			ZBX_STR2UINT64(eventid, row[0]);
			zbx_vector_uint64_append(&eventids, eventid);
		}
		DBfree_result(result);
	}

	zbx_vector_uint64_sort(&eventids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	if (0 != eventids.values_num)
		DBexecute_multiple_query("delete from problem where", "eventid", &eventids);

	zbx_vector_uint64_destroy(&eventids);

	return SUCCEED;
}

#endif

DBPATCH_START(3020)

/* version, duplicates flag, mandatory flag */

DBPATCH_ADD(3020000, 0, 1)
DBPATCH_ADD(3020001, 0, 0)

DBPATCH_END()
