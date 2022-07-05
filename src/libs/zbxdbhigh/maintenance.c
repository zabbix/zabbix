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
#include "zbxdbhigh.h"

/******************************************************************************
 *                                                                            *
 * Purpose: lock maintenances in database                                     *
 *                                                                            *
 * Parameters: maintenanceids - [IN/OUT] a vector of unique maintenance ids   *
 *                                 IN - the maintenances to lock              *
 *                                 OUT - the locked maintenance ids (sorted)  *
 *                                                                            *
 * Return value: SUCCEED - at least one maintenance was locked                *
 *               FAIL    - no maintenances were locked (all target            *
 *                         maintenances were removed by user and              *
 *                         configuration cache was not yet updated)           *
 *                                                                            *
 * Comments: This function locks maintenances in database to avoid foreign    *
 *           key errors when a maintenance is removed in the middle of        *
 *           processing.                                                      *
 *           The output vector might contain less values than input vector if *
 *           a maintenance was removed before lock attempt.                   *
 *                                                                            *
 ******************************************************************************/
int	zbx_db_lock_maintenanceids(zbx_vector_uint64_t *maintenanceids)
{
	char		*sql = NULL;
	size_t		sql_alloc = 0, sql_offset = 0;
	zbx_uint64_t	maintenanceid;
	int		i;
	DB_RESULT	result;
	DB_ROW		row;

	zbx_vector_uint64_sort(maintenanceids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select maintenanceid from maintenances where");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "maintenanceid", maintenanceids->values,
			maintenanceids->values_num);
#if defined(HAVE_MYSQL)
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " order by maintenanceid lock in share mode");
#elif defined(HAVE_ORACLE)
	/* Row level shared locks are not supported in Oracle. Table lock in share mode leads to deadlock on */
	/* event_suppress insertion due to locking that occurs in order to satisfy foreign key constraint.   */
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " order by maintenanceid" ZBX_FOR_UPDATE);
#else
	/* For PostgreSQL table level locks are used because row level shared locks have reader preference which */
	/* could lead to theoretical situation when server blocks out frontend from maintenances updates.        */
	DBexecute("lock table maintenances in share mode");
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " order by maintenanceid");
#endif
	result = DBselect("%s", sql);
	zbx_free(sql);

	for (i = 0; NULL != (row = DBfetch(result)); i++)
	{
		ZBX_STR2UINT64(maintenanceid, row[0]);

		while (maintenanceid != maintenanceids->values[i])
			zbx_vector_uint64_remove(maintenanceids, i);
	}
	DBfree_result(result);

	while (i != maintenanceids->values_num)
		zbx_vector_uint64_remove_noorder(maintenanceids, i);

	return (0 != maintenanceids->values_num ? SUCCEED : FAIL);
}
