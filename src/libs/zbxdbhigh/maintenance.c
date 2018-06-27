/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
#include "log.h"
#include "dbcache.h"

/******************************************************************************
 *                                                                            *
 * Function: zbx_db_lock_maintenanceids                                       *
 *                                                                            *
 * Purpose: lock maintenances in base                                         *
 *                                                                            *
 * Parameters: maintenanceids - [IN/OUT] the maintenance ids                  *
 *                                                                            *
 ******************************************************************************/
void	zbx_db_lock_maintenanceids(zbx_vector_uint64_t *maintenanceids)
{
	DB_ROW		row;
	DB_RESULT	result;
	char		*sql = NULL;
	size_t		sql_alloc = 0, sql_offset = 0;
	zbx_uint64_t	maintenanceid;
	int		i;

	zbx_vector_uint64_sort(maintenanceids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_uniq(maintenanceids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select maintenanceid from maintenances where");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "maintenanceid", maintenanceids->values,
			maintenanceids->values_num);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " order by maintenanceid " ZBX_FOR_UPDATE);

	result = DBselect("%s", sql);
	zbx_free(sql);

	i = 0;
	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(maintenanceid, row[0]);

		while (maintenanceid != maintenanceids->values[i])
			zbx_vector_uint64_remove(maintenanceids, i);
		i++;
	}
	DBfree_result(result);
}
