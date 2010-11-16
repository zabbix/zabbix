/*
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/

#include "common.h"

#include "zbxmacros.h"
#include "zbxalgo.h"
#include "dbcache.h"
#include "db.h"
#include "log.h"

/******************************************************************************
 *                                                                            *
 * Function: zbxmacros_get_value_by_triggerid                                 *
 *                                                                            *
 * Purpose: get host macros from db and add it to the buffer                  *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	zbxmacros_get_value_by_triggerid(zbx_uint64_t triggerid, const char *macro, char **replace_to)
{
	const char		*__function_name = "zbxmacros_get_value_by_triggerid";

	DB_RESULT		result;
	DB_ROW			row;
	zbx_vector_uint64_t	hostids;
	zbx_uint64_t		hostid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() triggerid:" ZBX_FS_UI64, __function_name, triggerid);

	zbx_vector_uint64_create(&hostids);
	zbx_vector_uint64_reserve(&hostids, 8);

	result = DBselect(
			"select distinct i.hostid"
			" from items i,functions f"
			" where f.itemid=i.itemid"
				" and f.triggerid=" ZBX_FS_UI64,
			triggerid);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(hostid, row[0]);
		zbx_vector_uint64_append(&hostids, hostid);
	}
	DBfree_result(result);

	zbx_vector_uint64_sort(&hostids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	DCget_user_macro(hostids.values, hostids.values_num, macro, replace_to);

	zbx_vector_uint64_destroy(&hostids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}
