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

#include "zbxtypes.h"
#include "zbxdb.h"
#include "zbxnum.h"

const char	*zbx_permission_string(int perm)
{
	switch (perm)
	{
		case PERM_DENY:
			return "dn";
		case PERM_READ:
			return "r";
		case PERM_READ_WRITE:
			return "rw";
		default:
			return "unknown";
	}
}

int	zbx_get_user_info(zbx_uint64_t userid, zbx_uint64_t *roleid, char **user_timezone)
{
	int		user_type = -1;
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	char		*user_tz = NULL;

	result = zbx_db_select("select r.type,u.roleid,u.timezone from users u,role r where u.roleid=r.roleid and"
			" userid=" ZBX_FS_UI64, userid);

	if (NULL != (row = zbx_db_fetch(result)) && FAIL == zbx_db_is_null(row[0]))
	{
		user_type = atoi(row[0]);
		ZBX_STR2UINT64(*roleid, row[1]);

		user_tz = row[2];
	}

	if (NULL != user_timezone)
		*user_timezone = (NULL != user_tz ? zbx_strdup(NULL, user_tz) : NULL);

	zbx_db_free_result(result);

	return user_type;
}

/******************************************************************************
 *                                                                            *
 * Purpose: Return user permissions for access to item                        *
 *                                                                            *
 * Return value: PERM_DENY - if host or user not found,                       *
 *                   or permission otherwise                                  *
 *                                                                            *
 ******************************************************************************/
int	zbx_get_item_permission(zbx_uint64_t userid, zbx_uint64_t itemid, char **user_timezone)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	int		perm = PERM_DENY;
	char		*sql;
	zbx_uint64_t	roleid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (USER_TYPE_SUPER_ADMIN == zbx_get_user_info(userid, &roleid, user_timezone))
	{
		perm = PERM_READ_WRITE;
		goto out;
	}

	sql = zbx_dsprintf(NULL,
			"select p.permission from items i"
			" join host_hgset h on i.hostid=h.hostid"
			" join permission p on h.hgsetid=p.hgsetid"
			" join user_ugset u on p.ugsetid=u.ugsetid"
			" where i.itemid=" ZBX_FS_UI64
				" and u.userid=" ZBX_FS_UI64,
			itemid, userid);

	result = zbx_db_select_n(sql, 1);
	zbx_free(sql);

	if (NULL != (row = zbx_db_fetch(result)) && SUCCEED != zbx_db_is_null(row[0]))
		perm = atoi(row[0]);

	zbx_db_free_result(result);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_permission_string(perm));

	return perm;
}

/******************************************************************************
 *                                                                            *
 * Purpose: Return user permissions for host access                           *
 *                                                                            *
 * Return value: PERM_DENY - if user not found, or permission otherwise       *
 *                                                                            *
 ******************************************************************************/
int	zbx_get_host_permission(const zbx_user_t *user, zbx_uint64_t hostid)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	char		*sql;
	int		perm = PERM_DENY;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (USER_TYPE_SUPER_ADMIN == user->type)
	{
		perm = PERM_READ_WRITE;
		goto out;
	}

	sql = zbx_dsprintf(NULL,
			"select p.permission from host_hgset h"
			" join permission p on h.hgsetid=p.hgsetid"
			" join user_ugset u on p.ugsetid=u.ugsetid"
			" where h.hostid=" ZBX_FS_UI64
				" and u.userid=" ZBX_FS_UI64,
			hostid, user->userid);

	result = zbx_db_select_n(sql, 1);
	zbx_free(sql);

	if (NULL != (row = zbx_db_fetch(result)) && SUCCEED != zbx_db_is_null(row[0]))
		perm = atoi(row[0]);

	zbx_db_free_result(result);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_permission_string(perm));

	return perm;
}
