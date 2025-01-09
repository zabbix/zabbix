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

#include "zbxdbwrap.h"

#include "zbxdb.h"

/* group statuses */
typedef enum
{
	GROUP_STATUS_ACTIVE = 0,
	GROUP_STATUS_DISABLED
}
zbx_group_status_type_t;

/******************************************************************************
 *                                                                            *
 * Purpose: Check user permissions to access system.                          *
 *                                                                            *
 * Parameters: userid - [IN]                                                  *
 *                                                                            *
 * Return value: SUCCEED - access allowed, FAIL - otherwise                   *
 *                                                                            *
 ******************************************************************************/
int	zbx_db_check_user_perm2system(zbx_uint64_t userid)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	int		res = SUCCEED;

	result = zbx_db_select(
			"select count(*)"
			" from usrgrp g,users_groups ug"
			" where ug.userid=" ZBX_FS_UI64
				" and g.usrgrpid=ug.usrgrpid"
				" and g.users_status=%d",
			userid, GROUP_STATUS_DISABLED);

	if (NULL != (row = zbx_db_fetch(result)) && SUCCEED != zbx_db_is_null(row[0]) && atoi(row[0]) > 0)
		res = FAIL;

	zbx_db_free_result(result);

	return res;
}

char	*zbx_db_get_user_timezone(zbx_uint64_t userid)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	char		*user_timezone;

	result = zbx_db_select("select timezone from users where userid=" ZBX_FS_UI64, userid);

	if (NULL != (row = zbx_db_fetch(result)))
		user_timezone = zbx_strdup(NULL, row[0]);
	else
		user_timezone = NULL;

	zbx_db_free_result(result);

	return user_timezone;
}
