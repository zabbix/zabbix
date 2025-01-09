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

#include "dbupgrade_common.h"
#include "dbupgrade.h"

#include "zbxdb.h"
#include "zbxalgo.h"
#include "zbxnum.h"

int	delete_problems_with_nonexistent_object(void)
{
	zbx_db_result_t		result;
	zbx_vector_uint64_t	eventids;
	zbx_db_row_t		row;
	zbx_uint64_t		eventid;
	int			sources[] = {EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_INTERNAL};
	int			objects[] = {EVENT_OBJECT_ITEM, EVENT_OBJECT_LLDRULE}, i;

	zbx_vector_uint64_create(&eventids);

	for (i = 0; i < (int)ARRSIZE(sources); i++)
	{
		result = zbx_db_select(
				"select p.eventid"
				" from problem p"
				" where p.source=%d and p.object=%d and not exists ("
					"select null"
					" from triggers t"
					" where t.triggerid=p.objectid"
				")",
				sources[i], EVENT_OBJECT_TRIGGER);

		while (NULL != (row = zbx_db_fetch(result)))
		{
			ZBX_STR2UINT64(eventid, row[0]);
			zbx_vector_uint64_append(&eventids, eventid);
		}
		zbx_db_free_result(result);
	}

	for (i = 0; i < (int)ARRSIZE(objects); i++)
	{
		result = zbx_db_select(
				"select p.eventid"
				" from problem p"
				" where p.source=%d and p.object=%d and not exists ("
					"select null"
					" from items i"
					" where i.itemid=p.objectid"
				")",
				EVENT_SOURCE_INTERNAL, objects[i]);

		while (NULL != (row = zbx_db_fetch(result)))
		{
			ZBX_STR2UINT64(eventid, row[0]);
			zbx_vector_uint64_append(&eventids, eventid);
		}
		zbx_db_free_result(result);
	}

	zbx_vector_uint64_sort(&eventids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	if (0 != eventids.values_num)
		zbx_db_execute_multiple_query("delete from problem where", "eventid", &eventids);

	zbx_vector_uint64_destroy(&eventids);

	return SUCCEED;
}
#ifndef HAVE_SQLITE3
int	create_problem_3_index(void)
{
	if (FAIL == zbx_db_index_exists("problem", "problem_3"))
		return DBcreate_index("problem", "problem_3", "r_eventid", 0);

	return SUCCEED;
}

int	drop_c_problem_2_index(void)
{
#ifdef HAVE_MYSQL	/* MySQL automatically creates index and might not remove it on some conditions */
	if (SUCCEED == zbx_db_index_exists("problem", "c_problem_2"))
		return DBdrop_index("problem", "c_problem_2");
#endif
	return SUCCEED;
}
#endif
