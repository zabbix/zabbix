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

#include "zbxdbhigh.h"

#include "zbxnum.h"
#include "zbxalgo.h"
#include "zbxdb.h"
#include "zbxstr.h"

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
	zbx_db_result_t	result;
	zbx_db_row_t	row;

	zbx_vector_uint64_sort(maintenanceids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select maintenanceid from maintenances where");
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "maintenanceid", maintenanceids->values,
			maintenanceids->values_num);
#if defined(HAVE_MYSQL)
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " order by maintenanceid lock in share mode");
#else
	/* For PostgreSQL table level locks are used because row level shared locks have reader preference which */
	/* could lead to theoretical situation when server blocks out frontend from maintenances updates.        */
	zbx_db_execute("lock table maintenances in share mode");
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " order by maintenanceid");
#endif
	result = zbx_db_select("%s", sql);
	zbx_free(sql);

	for (i = 0; NULL != (row = zbx_db_fetch(result)); i++)
	{
		ZBX_STR2UINT64(maintenanceid, row[0]);

		while (maintenanceid != maintenanceids->values[i])
			zbx_vector_uint64_remove(maintenanceids, i);
	}
	zbx_db_free_result(result);

	while (i != maintenanceids->values_num)
		zbx_vector_uint64_remove_noorder(maintenanceids, i);

	return (0 != maintenanceids->values_num ? SUCCEED : FAIL);
}
