/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

#include "zbxdbwrap.h"
#include "zbxtypes.h"
#include "zbxdbhigh.h"

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
 * Purpose: Return user permissions for access to the host                    *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: PERM_DENY - if host or user not found,                       *
 *                   or permission otherwise                                  *
 *                                                                            *
 ******************************************************************************/
int	zbx_get_hostgroups_permission(zbx_uint64_t userid, zbx_vector_uint64_t *hostgroupids)
{
	int		perm = PERM_DENY;
	char		*sql = NULL;
	size_t		sql_alloc = 0, sql_offset = 0;
	zbx_db_result_t	result;
	zbx_db_row_t	row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (0 == hostgroupids->values_num)
		goto out;

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select min(r.permission)"
			" from rights r"
			" join users_groups ug on ug.usrgrpid=r.groupid"
				" where ug.userid=" ZBX_FS_UI64 " and", userid);
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "r.id",
			hostgroupids->values, hostgroupids->values_num);
	result = zbx_db_select("%s", sql);

	if (NULL != (row = zbx_db_fetch(result)) && FAIL == zbx_db_is_null(row[0]))
		perm = atoi(row[0]);

	zbx_db_free_result(result);
	zbx_free(sql);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_permission_string(perm));

	return perm;
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
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	int			perm = PERM_DENY;
	zbx_vector_uint64_t	hostgroupids;
	zbx_uint64_t		hostgroupid, roleid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&hostgroupids);

	if (USER_TYPE_SUPER_ADMIN == zbx_get_user_info(userid, &roleid, user_timezone))
	{
		perm = PERM_READ_WRITE;
		goto out;
	}

	result = zbx_db_select(
			"select hg.groupid from items i"
			" join hosts_groups hg on hg.hostid=i.hostid"
			" where i.itemid=" ZBX_FS_UI64,
			itemid);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		ZBX_STR2UINT64(hostgroupid, row[0]);
		zbx_vector_uint64_append(&hostgroupids, hostgroupid);
	}
	zbx_db_free_result(result);

	zbx_vector_uint64_sort(&hostgroupids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	perm = zbx_get_hostgroups_permission(userid, &hostgroupids);
out:
	zbx_vector_uint64_destroy(&hostgroupids);

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
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	int			perm = PERM_DENY;
	zbx_vector_uint64_t	hostgroupids;
	zbx_uint64_t		hostgroupid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&hostgroupids);

	if (USER_TYPE_SUPER_ADMIN == user->type)
	{
		perm = PERM_READ_WRITE;
		goto out;
	}

	result = zbx_db_select("select groupid from hosts_groups where hostid=" ZBX_FS_UI64, hostid);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		ZBX_STR2UINT64(hostgroupid, row[0]);
		zbx_vector_uint64_append(&hostgroupids, hostgroupid);
	}
	zbx_db_free_result(result);

	zbx_vector_uint64_sort(&hostgroupids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	perm = zbx_get_hostgroups_permission(user->userid, &hostgroupids);
out:
	zbx_vector_uint64_destroy(&hostgroupids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_permission_string(perm));

	return perm;
}
